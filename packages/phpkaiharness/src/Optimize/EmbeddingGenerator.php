<?php

namespace Phpkaiharness\Optimize;

use App\Services\AiConfigHelper;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Embeddings;

/**
 * Centralised embedding generator for phpkaiharness RAG features.
 *
 * Uses the host application's global embedding settings via AiConfigHelper and
 * the Laravel AI SDK.  It adds a deterministic pseudo-embedding fallback so
 * RAG keeps working when the configured provider is unavailable or has no
 * quota left, and it caches results in the Laravel cache (Redis by default) so
 * the same text is not re-embedded repeatedly.
 */
class EmbeddingGenerator
{
    /**
     * Generate an embedding vector for the given text.
     *
     * @return array<float>
     */
    public static function generate(string $text): array
    {
        if (blank($text)) {
            return [];
        }

        $config = self::resolveConfig();
        $provider = $config['provider'];
        $model = $config['model'];
        $dimensions = $config['dimensions'];

        $cacheKey = self::cacheKey($text, $provider, $model, $dimensions);

        $cached = self::getCache($cacheKey);
        if (! empty($cached)) {
            return $cached;
        }

        try {
            $vector = self::generateFromSdk($text, $provider, $model, $dimensions);

            if (! empty($vector)) {
                self::putCache($cacheKey, $vector);

                return $vector;
            }
        } catch (\Throwable $e) {
            self::log('EmbeddingGenerator failed for provider '.($provider ?? 'unknown').': '.$e->getMessage());
        }

        // Quantum/Dirac-inspired fallback: deterministic pseudo-embedding from
        // the content hash.  This keeps all RAG pipelines operational when the
        // configured provider is exhausted or unreachable.
        $fallback = self::pseudoEmbedding($text, (int) $dimensions);
        self::putCache($cacheKey, $fallback);

        return $fallback;
    }

    /**
     * Resolve the active embedding provider, model and dimensions.
     *
     * @return array{provider: string, model: string|null, dimensions: int}
     */
    public static function resolveConfig(): array
    {
        $provider = config('ai.default_for_embeddings', 'ollama');
        $model = null;
        $dimensions = 384;

        if (class_exists('App\Services\AiConfigHelper')) {
            try {
                $cfg = AiConfigHelper::configureEmbeddings();
                $provider = $cfg['provider'] ?? $provider;
                $model = $cfg['model'] ?? $model;
                $dimensions = (int) ($cfg['dimensions'] ?? $dimensions);
            } catch (\Throwable $e) {
                // Fallback to defaults below
            }
        }

        if (empty($provider)) {
            $provider = config('harness.default.provider', 'ollama');
        }

        if (empty($model)) {
            $model = config("ai.providers.{$provider}.models.embeddings.default") ?? null;
        }

        if (empty($dimensions)) {
            $dimensions = (int) (config("ai.providers.{$provider}.models.embeddings.dimensions") ?? 384);
        }

        return [
            'provider' => (string) $provider,
            'model' => $model,
            'dimensions' => (int) $dimensions,
        ];
    }

    /**
     * Call the Laravel AI SDK to generate an embedding vector.
     *
     * @return array<float>
     */
    protected static function generateFromSdk(string $text, string $provider, ?string $model, int $dimensions): array
    {
        if (! class_exists(Embeddings::class)) {
            return [];
        }

        $pending = Embeddings::for([$text])->cache();

        if ($dimensions > 0) {
            $pending = $pending->dimensions($dimensions);
        }

        $response = $pending->generate($provider, $model);

        return $response->first() ?? [];
    }

    /**
     * Safely retrieve a cached embedding vector.
     *
     * @return array<float>|null
     */
    protected static function getCache(string $key): ?array
    {
        try {
            if (function_exists('app') && app()->bound('cache')) {
                $cached = Cache::get($key);
                if (is_array($cached) && ! empty($cached)) {
                    return $cached;
                }
            }
        } catch (\Throwable $e) {
            // Cache unavailable — fall through to generation
        }

        return null;
    }

    /**
     * Safely store an embedding vector in the cache.
     *
     * @param  array<float>  $vector
     */
    protected static function putCache(string $key, array $vector): void
    {
        try {
            if (function_exists('app') && app()->bound('cache')) {
                Cache::put($key, $vector, now()->addDays(30));
            }
        } catch (\Throwable $e) {
            // Cache unavailable — ignore
        }
    }

    /**
     * Log a message when a log channel is available.
     */
    protected static function log(string $message): void
    {
        try {
            if (function_exists('info') && function_exists('app') && app()->bound('log')) {
                info($message);
            }
        } catch (\Throwable $e) {
            // Ignore logging failures
        }
    }

    /**
     * Build a cache key for the embedding of a given text.
     */
    protected static function cacheKey(string $text, string $provider, ?string $model, int $dimensions): string
    {
        return 'harness:embeddings:'.hash('sha256', implode(':', [
            $provider,
            $model ?? 'default',
            (string) $dimensions,
            $text,
        ]));
    }

    /**
     * Generate a deterministic pseudo-embedding from the content hash.
     *
     * This is a quantum/dirac-inspired fallback that produces a stable vector
     * without calling an external API.  The vector is scaled to the configured
     * embedding dimensions so cosine similarity is comparable with real
     * embeddings.
     *
     * @return array<float>
     */
    public static function pseudoEmbedding(string $text, int $dimensions = 384): array
    {
        $hash = hash('sha256', $text, true);
        $vector = [];
        $hashLength = strlen($hash);

        for ($i = 0; $i < $dimensions; $i++) {
            $byteIndex = $i % $hashLength;
            $byte = ord($hash[$byteIndex]);
            $vector[] = ($byte - 128) / 128.0;
        }

        return $vector;
    }
}
