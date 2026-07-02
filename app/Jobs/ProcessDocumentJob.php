<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\DocumentationChunk;
use App\Services\AiConfigHelper;
use App\Services\DocumentParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\Ai\Embeddings;

class ProcessDocumentJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $documentId) {}

    /**
     * Execute the job.
     */
    public function handle(DocumentParser $parser): void
    {
        $document = Document::find($this->documentId);

        if (! $document) {
            return;
        }

        try {
            $document->update(['status' => 'processing']);

            // Get absolute file path
            $filePath = storage_path('app/private/documents/'.$document->filename);

            if (! file_exists($filePath)) {
                $filePath = storage_path('app/documents/'.$document->filename);
            }

            // Extract the text
            $text = $parser->parse($filePath);

            if (blank($text)) {
                throw new \Exception('The document contains no readable text.');
            }

            // Generate chunks
            $chunks = $this->chunkContent($text);

            // Configure embeddings model dynamically
            $embeddingConfig = AiConfigHelper::configureEmbeddings();
            $provider = $embeddingConfig['provider'];

            $totalChunks = 0;

            foreach ($chunks as $chunk) {
                if (blank($chunk)) {
                    continue;
                }

                // Call Laravel AI SDK Embeddings
                $response = Embeddings::for([$chunk])->generate($provider);
                $vector = $response->first();

                DocumentationChunk::create([
                    'document_id' => $document->id,
                    'source_file' => $document->original_name,
                    'chunk_text' => $chunk,
                    'embedding' => $vector,
                ]);

                $totalChunks++;
            }

            $document->update([
                'status' => 'completed',
                'chunk_count' => $totalChunks,
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            $document->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            \Log::error("Failed to process document ID {$this->documentId}: ".$e->getMessage());
        }
    }

    /**
     * Helper to split content into semantic paragraph chunks.
     *
     * @return array<string>
     */
    protected function chunkContent(string $content): array
    {
        if (strlen($content) <= 1200) {
            return [trim($content)];
        }

        $parts = preg_split('/\n{2,}/', $content);
        $chunks = [];
        $currentChunk = '';

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            // If adding this part exceeds 1200 characters, save current chunk and start a new one
            if (strlen($currentChunk) + strlen($part) > 1200) {
                if (! empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);
                }
                $currentChunk = $part;
            } else {
                $currentChunk = empty($currentChunk) ? $part : $currentChunk."\n\n".$part;
            }
        }

        if (! empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }
}
