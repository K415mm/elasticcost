<?php

namespace Phpkaiharness\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class HarnessConfig
{
    /**
     * Check if a feature graph node or setting is enabled.
     * Priority: feature_graph.nodes.<name>.enabled > legacy config key > default
     */
    public static function isNodeEnabled(string $nodeName, ?string $legacyKey, bool $default): bool
    {
        if (! function_exists('config')) {
            return $default;
        }

        try {
            // Check feature_graph first
            $graphEnabled = config("harness.feature_graph.nodes.{$nodeName}.enabled");
            if ($graphEnabled !== null) {
                return (bool) $graphEnabled;
            }

            // Fall back to legacy config key
            if ($legacyKey !== null) {
                return (bool) config($legacyKey, $default);
            }
        } catch (\Throwable $e) {
            return $default;
        }

        return $default;
    }

    /**
     * Return the active config mode.
     *
     * - 'philosophy' : The Dirac Complexity Router adapts which features are
     *                  *used* inside the loop. Cache and memory are ALWAYS
     *                  initialised and checked; a miss is telemetry, not a
     *                  teardown. The router never overwrites the manager's
     *                  feature-flag config.
     *
     * - 'force'      : The manager's saved config is used exactly as-is.
     *                  The Dirac router still classifies complexity for
     *                  telemetry, but does NOT override any feature flags.
     */
    public static function getConfigMode(): string
    {
        if (! function_exists('config')) {
            return 'force';
        }

        try {
            return (string) config('harness.config_mode', 'force');
        } catch (\Throwable $e) {
            return 'force';
        }
    }

    /**
     * Reload the saved config_overrides.json into the running Laravel config.
     *
     * Called at the start of every AgentLoop::run() to guarantee that any
     * config change saved by the manager — even mid-session — takes immediate
     * effect without requiring an Octane worker restart.
     */
    public static function reloadOverrides(): void
    {
        if (function_exists('app') && app()->runningUnitTests()) {
            return;
        }

        if (! function_exists('config')) {
            return;
        }

        try {
            $overridePath = storage_path('app/phpkaiharness/config_overrides.json');

            if (! function_exists('storage_path') || ! file_exists($overridePath)) {
                return;
            }

            $raw = file_get_contents($overridePath);
            if ($raw === false || $raw === '') {
                return;
            }

            $overrides = json_decode($raw, true);
            if (! is_array($overrides)) {
                return;
            }

            // Flatten to dot-notation and re-apply into the running config
            foreach (self::flattenArray($overrides, 'harness') as $key => $value) {
                config([$key => $value]);
            }
        } catch (\Throwable $e) {
            // Non-fatal: continue with whatever config is in memory
        }
    }

    /**
     * Flatten a nested array into dot-notation keys.
     *
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    private static function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            if (is_array($value)) {
                $result = array_merge($result, self::flattenArray($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Sync the harness LLM config from the host app's global_settings.
     *
     * This keeps the phpkaiharness package aligned with the System Settings UI
     * and disables the default localhost fallbacks when no harness config exists.
     */
    public static function syncFromGlobalSettings(): void
    {
        if (! function_exists('config') || ! function_exists('storage_path') || ! function_exists('app')) {
            return;
        }

        if (! app()->bound('config')) {
            return;
        }

        try {
            if (! class_exists('App\Models\GlobalSetting') || ! class_exists('Illuminate\Support\Facades\Schema')) {
                return;
            }

            if (! Schema::hasTable('global_settings')) {
                return;
            }

            $provider = GlobalSetting::getValue('ai_provider');
            if (empty($provider)) {
                return;
            }

            $overridePath = storage_path('app/phpkaiharness/config_overrides.json');
            $existing = [];
            if (file_exists($overridePath)) {
                $raw = file_get_contents($overridePath);
                if ($raw !== false && $raw !== '') {
                    $existing = json_decode($raw, true) ?: [];
                }
            }

            $harness = $existing;
            $harness['default']['provider'] = $provider;

            $model = '';
            if (class_exists('App\Services\AiConfigHelper')) {
                try {
                    $cfg = \App\Services\AiConfigHelper::configure();
                    $resolvedProvider = $cfg['provider'] ?? $provider;
                    if (is_object($resolvedProvider) && method_exists($resolvedProvider, 'value')) {
                        $resolvedProvider = $resolvedProvider->value;
                    }
                    $resolvedModel = $cfg['model'] ?? '';
                    if (! empty($resolvedProvider)) {
                        $harness['default']['provider'] = (string) $resolvedProvider;
                    }
                    if (! empty($resolvedModel)) {
                        $model = (string) $resolvedModel;
                        $harness['default']['model'] = $model;
                    }
                } catch (\Throwable $e) {
                    // fall through to global settings
                }
            }

            if (empty($harness['default']['model'])) {
                $model = GlobalSetting::getValue($provider.'_model');
                if (! empty($model)) {
                    $harness['default']['model'] = (string) $model;
                }
            }

            if ($provider === 'qwen') {
                $harness['qwen_provider']['enabled'] = true;
                $harness['qwen_provider']['api_key'] = (string) GlobalSetting::getValue('qwen_api_key', '');
                $harness['qwen_provider']['url'] = (string) GlobalSetting::getValue('qwen_url', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1');
                $harness['qwen_provider']['model'] = (string) ($model ?: GlobalSetting::getValue('qwen_model', 'qwen-plus'));
                $harness['qwen_provider']['light_model'] = (string) GlobalSetting::getValue('qwen_light_model', 'qwen-turbo');
                $harness['qwen_provider']['structured_output'] = 'json_object';
                $harness['qwen_provider']['max_tokens'] = 4096;
            } elseif ($provider === 'ollama') {
                $harness['default']['model'] = (string) GlobalSetting::getValue('ollama_model', 'gemma4:e2b');
                $harness['ollama_url'] = (string) GlobalSetting::getValue('ollama_url', 'http://localhost:11434');
            } elseif ($provider === 'lmstudio') {
                $harness['default']['model'] = (string) GlobalSetting::getValue('lmstudio_model', 'qwen2.5-coder-7b-instruct');
                $harness['lmstudio_url'] = (string) GlobalSetting::getValue('lmstudio_url', 'http://localhost:1234/v1');
            } elseif ($provider === 'gemini') {
                $harness['default']['model'] = (string) GlobalSetting::getValue('gemini_model', 'gemini-1.5-flash');
                $harness['gemini_api_key'] = (string) GlobalSetting::getValue('gemini_api_key', '');
            } elseif ($provider === 'openrouter') {
                $harness['default']['model'] = (string) GlobalSetting::getValue('openrouter_model', 'meta-llama/llama-3-8b-instruct:free');
                $harness['openrouter_api_key'] = (string) GlobalSetting::getValue('openrouter_api_key', '');
            }

            if (! isset($harness['failover'])) {
                $harness['failover'] = [
                    'enabled' => false,
                    'clients' => [],
                ];
            }

            $directory = dirname($overridePath);
            if (! File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true, true);
            }

            File::put($overridePath, json_encode($harness, JSON_PRETTY_PRINT));
            config(['harness' => array_replace_recursive(config('harness'), $harness)]);
        } catch (\Throwable $e) {
            // Non-fatal: continue with whatever harness config is already loaded
        }
    }
}
