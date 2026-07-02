<?php

namespace App\Ai\Middleware;

use App\Models\DocumentationChunk;
use App\Models\GlobalSetting;
use App\Services\AiConfigHelper;
use Closure;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\AgentPrompt;

class InjectDocumentation
{
    /**
     * Handle the incoming prompt.
     */
    public function handle(AgentPrompt $prompt, Closure $next)
    {
        $query = $prompt->prompt;

        if (blank($query)) {
            return $next($prompt);
        }

        try {
            $agentName = class_basename($prompt->agent);
            $ragEnabled = (bool) GlobalSetting::getValue("ai_rag_enabled_{$agentName}", true);

            if (! $ragEnabled) {
                return $next($prompt);
            }

            $threshold = (float) GlobalSetting::getValue("ai_rag_threshold_{$agentName}", 0.30);
            $maxChunks = (int) GlobalSetting::getValue("ai_rag_max_chunks_{$agentName}", 3);

            $embeddingConfig = AiConfigHelper::configureEmbeddings();
            $provider = $embeddingConfig['provider'];

            // Generate query embedding vector using the SDK
            $response = Embeddings::for([$query])->generate($provider);
            $queryVector = $response->first();

            $isSqlite = DB::connection()->getDriverName() === 'sqlite';

            if ($isSqlite) {
                // Fall back to in-memory similarity search for SQLite (e.g. testing)
                $chunks = DocumentationChunk::all();
                $scoredChunks = $chunks->map(function ($chunk) use ($queryVector) {
                    return [
                        'chunk' => $chunk,
                        'score' => $chunk->similarity($queryVector),
                    ];
                })
                    ->filter(fn ($item) => $item['score'] >= $threshold)
                    ->sortByDesc('score')
                    ->take($maxChunks);

                $referencedDocs = '';
                foreach ($scoredChunks as $item) {
                    $chunk = $item['chunk'];
                    $score = number_format($item['score'], 4);
                    $referencedDocs .= "\n--- (Source: {$chunk->source_file}, Similarity Score: {$score}) ---\n{$chunk->chunk_text}\n";
                }
            } else {
                // Perform similarity search directly in PostgreSQL using pgvector (cosine distance <=> operator)
                $vectorString = '['.implode(',', $queryVector).']';
                $scoredChunks = DocumentationChunk::query()
                    ->select('*')
                    ->selectRaw('(1 - (embedding <=> ?::vector)) as similarity_score', [$vectorString])
                    ->whereRaw('(1 - (embedding <=> ?::vector)) >= ?', [$vectorString, $threshold])
                    ->orderByRaw('embedding <=> ?::vector', [$vectorString])
                    ->take($maxChunks)
                    ->get();

                $referencedDocs = '';
                foreach ($scoredChunks as $chunk) {
                    $score = number_format($chunk->similarity_score, 4);
                    $referencedDocs .= "\n--- (Source: {$chunk->source_file}, Similarity Score: {$score}) ---\n{$chunk->chunk_text}\n";
                }
            }

            if (! empty($referencedDocs)) {
                $injection = "\n## REFERENCED DOCUMENTATION SECTION\n".
                    'The following sections from the Sizing & Architectural Reference Guide are semantically relevant to the user query. '.
                    "Use them as factual reference guidelines for your analysis/answers:\n".
                    $referencedDocs."\n";

                $prompt = $prompt->append($injection);
            }
        } catch (\Throwable $e) {
            \Log::warning('Documentation injection failed: '.$e->getMessage());
        }

        return $next($prompt);
    }
}
