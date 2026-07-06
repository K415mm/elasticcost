<?php

namespace App\Services;

use App\Models\GlobalSetting;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\AiManager;
use Laravel\Ai\Enums\Lab;

class AiConfigHelper
{
    /**
     * Configure the AI SDK settings at runtime based on active database parameters
     * and return the resolved provider and model.
     *
     * @return array{provider: string|Lab, model: string}
     */
    public static function configure(): array
    {
        $providerKey = 'ollama';
        $model = 'gemma4:e2b';

        try {
            // Check if global_settings table exists first (for migrations/tests)
            if (Schema::hasTable('global_settings')) {
                self::configureEmbeddings();
                $providerKey = GlobalSetting::getValue('ai_provider', 'ollama');

                if ($providerKey === 'ollama') {
                    $model = GlobalSetting::getValue('ollama_model', 'gemma4:e2b');
                    $url = GlobalSetting::getValue('ollama_url', 'http://localhost:11434');
                    $url = self::resolveUrlForEnvironment($url);

                    config([
                        'ai.providers.ollama.url' => $url,
                    ]);

                    // Purge resolved instance to apply new config
                    if (app()->bound(AiManager::class)) {
                        app(AiManager::class)->forgetInstance('ollama');
                    }

                    return [
                        'provider' => Lab::Ollama,
                        'model' => $model,
                    ];
                }

                if ($providerKey === 'lmstudio') {
                    $model = GlobalSetting::getValue('lmstudio_model', 'qwen2.5-coder-7b-instruct');
                    $url = GlobalSetting::getValue('lmstudio_url', 'http://localhost:1234/v1');
                    $url = self::resolveUrlForEnvironment($url);

                    config([
                        'ai.providers.lmstudio.driver' => 'groq',
                        'ai.providers.lmstudio.key' => 'lm-studio',
                        'ai.providers.lmstudio.url' => $url,
                        'ai.providers.lmstudio.models.embeddings.default' => 'text-embedding-embeddinggemma-300m',
                    ]);

                    if (app()->bound(AiManager::class)) {
                        app(AiManager::class)->forgetInstance('lmstudio');
                    }

                    return [
                        'provider' => 'lmstudio',
                        'model' => $model,
                    ];
                }

                if ($providerKey === 'gemini') {
                    $model = GlobalSetting::getValue('gemini_model', 'gemini-1.5-flash');
                    $apiKey = GlobalSetting::getValue('gemini_api_key', '');

                    config([
                        'ai.providers.gemini.key' => $apiKey,
                    ]);

                    if (app()->bound(AiManager::class)) {
                        app(AiManager::class)->forgetInstance('gemini');
                    }

                    return [
                        'provider' => Lab::Gemini,
                        'model' => $model,
                    ];
                }

                if ($providerKey === 'openrouter') {
                    $model = GlobalSetting::getValue('openrouter_model', 'meta-llama/llama-3-8b-instruct:free');
                    $apiKey = GlobalSetting::getValue('openrouter_api_key', '');

                    config([
                        'ai.providers.openrouter.key' => $apiKey,
                    ]);

                    if (app()->bound(AiManager::class)) {
                        app(AiManager::class)->forgetInstance('openrouter');
                    }

                    return [
                        'provider' => Lab::OpenRouter,
                        'model' => $model,
                    ];
                }

                if ($providerKey === 'qwen') {
                    $model = GlobalSetting::getValue('qwen_model', 'qwen-plus');
                    $apiKey = GlobalSetting::getValue('qwen_api_key') ?: (env('PHPKAIHARNESS_QWEN_KEY') ?: (env('QWEN_API_KEY') ?: env('DASHSCOPE_API_KEY', '')));
                    $url = GlobalSetting::getValue('qwen_url', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1');

                    config([
                        'ai.providers.qwen.driver' => 'openai',
                        'ai.providers.qwen.key' => $apiKey,
                        'ai.providers.qwen.url' => $url,
                    ]);

                    if (app()->bound(AiManager::class)) {
                        app(AiManager::class)->forgetInstance('qwen');
                    }

                    return [
                        'provider' => 'qwen',
                        'model' => $model,
                    ];
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('AI dynamic configuration failed: '.$e->getMessage().'. Falling back to env configurations.');
        }

        // Fallback default
        return [
            'provider' => Lab::Ollama,
            'model' => env('OLLAMA_MODEL', 'gemma4:e2b'),
        ];
    }

    /**
     * Configure the AI SDK settings and return the resolved main and light configurations.
     *
     * @return array{
     *     main: array{provider: string|Lab, model: string},
     *     light: array{provider: string|Lab, model: string}
     * }
     */
    public static function configureMultiModel(): array
    {
        $main = self::configure();
        $light = $main; // default fallback

        try {
            if (Schema::hasTable('global_settings')) {
                $multiAgentEnabled = (bool) GlobalSetting::getValue('ai_multi_agent_enabled', false);
                if (! $multiAgentEnabled) {
                    return [
                        'main' => $main,
                        'light' => $main,
                    ];
                }

                $providerKey = GlobalSetting::getValue('ai_provider', 'ollama');

                if ($providerKey === 'lmstudio') {
                    $lightModel = GlobalSetting::getValue('lmstudio_light_model', 'gemma-3-1b-it-glm-4.7-flash-heretic-uncensored-thinking_gguf');
                    $light = [
                        'provider' => 'lmstudio',
                        'model' => $lightModel,
                    ];
                } elseif ($providerKey === 'ollama') {
                    $lightModel = GlobalSetting::getValue('ollama_light_model', 'gemma-3-1b-it-glm-4.7-flash-heretic-uncensored-thinking_gguf');
                    $light = [
                        'provider' => Lab::Ollama,
                        'model' => $lightModel,
                    ];
                } elseif ($providerKey === 'gemini') {
                    $lightModel = GlobalSetting::getValue('gemini_light_model', 'gemini-1.5-flash');
                    $light = [
                        'provider' => Lab::Gemini,
                        'model' => $lightModel,
                    ];
                } elseif ($providerKey === 'openrouter') {
                    $lightModel = GlobalSetting::getValue('openrouter_light_model', 'meta-llama/llama-3-8b-instruct:free');
                    $light = [
                        'provider' => Lab::OpenRouter,
                        'model' => $lightModel,
                    ];
                } elseif ($providerKey === 'qwen') {
                    $lightModel = GlobalSetting::getValue('qwen_light_model', 'qwen-turbo');
                    $light = [
                        'provider' => 'qwen',
                        'model' => $lightModel,
                    ];
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('AI dynamic multi-model configuration failed: '.$e->getMessage());
        }

        return [
            'main' => $main,
            'light' => $light,
        ];
    }

    /**
     * Configure the AI SDK settings specifically for RAG vector embeddings,
     * independent of the main conversation LLM provider.
     *
     * @return array{provider: string, model: string}
     */
    public static function configureEmbeddings(): array
    {
        $providerKey = 'ollama';
        $model = 'nomic-embed-text';

        try {
            if (Schema::hasTable('global_settings')) {
                $providerKey = GlobalSetting::getValue('rag_embedding_provider', 'ollama');
                $model = GlobalSetting::getValue('rag_embedding_model', 'nomic-embed-text');

                if ($providerKey === 'ollama') {
                    $url = GlobalSetting::getValue('ollama_url', 'http://localhost:11434');
                    $url = self::resolveUrlForEnvironment($url);

                    config([
                        'ai.providers.ollama.url' => $url,
                        'ai.default_for_embeddings' => 'ollama',
                    ]);

                    if (app()->bound(AiManager::class)) {
                        app(AiManager::class)->forgetInstance('ollama');
                    }
                } elseif ($providerKey === 'lmstudio') {
                    $url = GlobalSetting::getValue('lmstudio_url', 'http://localhost:1234/v1');
                    $url = self::resolveUrlForEnvironment($url);

                    config([
                        'ai.providers.lmstudio.driver' => 'openai',
                        'ai.providers.lmstudio.key' => 'lm-studio',
                        'ai.providers.lmstudio.url' => $url,
                        'ai.providers.lmstudio.models.embeddings.default' => $model,
                        'ai.default_for_embeddings' => 'lmstudio',
                    ]);

                    if (app()->bound(AiManager::class)) {
                        app(AiManager::class)->forgetInstance('lmstudio');
                    }
                } elseif ($providerKey === 'gemini') {
                    $apiKey = GlobalSetting::getValue('gemini_api_key', '');

                    config([
                        'ai.providers.gemini.key' => $apiKey,
                        'ai.default_for_embeddings' => 'gemini',
                    ]);

                    if (app()->bound(AiManager::class)) {
                        app(AiManager::class)->forgetInstance('gemini');
                    }
                } elseif ($providerKey === 'openrouter') {
                    $apiKey = GlobalSetting::getValue('openrouter_api_key', '');

                    config([
                        'ai.providers.openrouter.key' => $apiKey,
                        'ai.default_for_embeddings' => 'openrouter',
                    ]);

                    if (app()->bound(AiManager::class)) {
                        app(AiManager::class)->forgetInstance('openrouter');
                    }
                } elseif ($providerKey === 'qwen') {
                    $apiKey = GlobalSetting::getValue('qwen_api_key') ?: (env('PHPKAIHARNESS_QWEN_KEY') ?: (env('QWEN_API_KEY') ?: env('DASHSCOPE_API_KEY', '')));
                    $url = GlobalSetting::getValue('qwen_url', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1');

                    config([
                        'ai.providers.qwen.driver' => 'openai',
                        'ai.providers.qwen.key' => $apiKey,
                        'ai.providers.qwen.url' => $url,
                        'ai.providers.qwen.models.embeddings.default' => $model,
                        'ai.providers.qwen.models.embeddings.dimensions' => 1024,
                        'ai.default_for_embeddings' => 'qwen',
                    ]);

                    if (app()->bound(AiManager::class)) {
                        app(AiManager::class)->forgetInstance('qwen');
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('RAG embedding configuration failed: '.$e->getMessage().'. Falling back to default embeddings.');
        }

        return [
            'provider' => $providerKey,
            'model' => $model,
        ];
    }

    /**
     * Resolve the API URL for WSL compatibility if running inside WSL.
     */
    public static function resolveUrlForEnvironment(string $url): string
    {
        $isWsl = str_contains(php_uname('r'), '-microsoft') || str_contains(php_uname('v'), 'Microsoft') || file_exists('/run/WSL');

        if (! $isWsl) {
            return $url;
        }

        // Parse host and port to check if it's already reachable locally (e.g. using WSL Mirrored Networking)
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? 'localhost';
        $port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);

        if (in_array($host, ['localhost', '127.0.0.1'])) {
            $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.05);
            if (is_resource($connection)) {
                fclose($connection);

                return $url; // Already reachable on local loopback!
            }
        }

        // Parse default route to find Windows host IP
        $hostIp = '127.0.0.1';
        if (file_exists('/proc/net/route')) {
            $routes = file_get_contents('/proc/net/route');
            foreach (explode("\n", $routes) as $line) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 3 && $parts[1] === '00000000') {
                    $gatewayHex = $parts[2];
                    if (strlen($gatewayHex) === 8) {
                        $octets = [];
                        for ($i = 6; $i >= 0; $i -= 2) {
                            $octets[] = hexdec(substr($gatewayHex, $i, 2));
                        }
                        $hostIp = implode('.', $octets);
                        break;
                    }
                }
            }
        }

        if ($hostIp !== '127.0.0.1') {
            $url = str_replace(['localhost', '127.0.0.1'], $hostIp, $url);
        }

        return $url;
    }
}
