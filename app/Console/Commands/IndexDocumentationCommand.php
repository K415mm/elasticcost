<?php

namespace App\Console\Commands;

use App\Models\DocumentationChunk;
use App\Services\AiConfigHelper;
use Illuminate\Console\Command;
use Laravel\Ai\Embeddings;

class IndexDocumentationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docs:index';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index system sizing guidelines and reference guides into embeddings database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Initializing document indexing...');

        // Configure AI provider dynamically
        $embeddingConfig = AiConfigHelper::configureEmbeddings();
        $provider = $embeddingConfig['provider'];

        $this->info("Using embeddings provider: {$provider}");

        // Clear existing chunks
        DocumentationChunk::truncate();
        $this->warn('Cleared existing documentation chunks.');

        $basePath = base_path();
        $filesToProcess = [
            'doc/learn/elasticsearch_sizing_standards.md',
            'elastic_reference_guide.md',
            'Elasticsearch On-Premise Sizing Reference Report.md',
            'check_sizing.php',
        ];

        $totalChunks = 0;

        foreach ($filesToProcess as $relativePath) {
            $fullPath = $basePath.'/'.$relativePath;

            if (! file_exists($fullPath)) {
                $this->error("File not found: {$relativePath}");

                continue;
            }

            $this->info("Processing: {$relativePath}");
            $content = file_get_contents($fullPath);

            // Chunk the file
            $chunks = $this->chunkContent($content, $relativePath);

            foreach ($chunks as $chunk) {
                if (blank($chunk)) {
                    continue;
                }

                $this->info('Generating embedding for a chunk of length: '.strlen($chunk));

                try {
                    // Call Laravel AI SDK Embeddings
                    $response = Embeddings::for([$chunk])->generate($provider);
                    $vector = $response->first();

                    DocumentationChunk::create([
                        'source_file' => $relativePath,
                        'chunk_text' => $chunk,
                        'embedding' => $vector,
                    ]);

                    $totalChunks++;
                } catch (\Throwable $e) {
                    $this->error('Failed to generate embedding: '.$e->getMessage());
                }
            }
        }

        $this->info("Successfully indexed {$totalChunks} chunks!");

        return Command::SUCCESS;
    }

    /**
     * Helper to split content into semantic paragraph chunks.
     *
     * @return array<string>
     */
    protected function chunkContent(string $content, string $filename): array
    {
        // For short scripts/files under 1500 chars, keep them as a single chunk
        if (strlen($content) <= 1500) {
            return [trim($content)];
        }

        // Split markdown by double newlines
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
