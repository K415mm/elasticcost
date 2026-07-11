<?php

namespace Phpkaiharness\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * phpkaiharness Configuration UI Controller.
 *
 * Reads and writes the published harness.php config file,
 * enabling live enable/disable of package features from the browser.
 */
class HarnessConfigController extends Controller
{
    /**
     * Render the configuration UI.
     *
     * GET /harness/config
     */
    public function index(): Response
    {
        $config = config('harness');

        return response()->view('harness::config', compact('config'));
    }

    /**
     * Save updated configuration values.
     *
     * POST /harness/config
     */
    public function save(Request $request): RedirectResponse
    {
        $configPath = config_path('harness.php');

        if (! File::exists($configPath)) {
            return redirect()->route('harness.config')
                ->with('error', 'harness.php not found in config/. Run: php artisan vendor:publish --tag=harness-config');
        }

        $updated = $this->buildUpdatedConfig($request);

        // Write overrides JSON (for service provider to load at boot).
        // This is the primary persistence mechanism — storage/ is always
        // writable by the web server user, unlike config/ which may be
        // owned by a deploy user (e.g. root) after a git pull.
        $overridePath = storage_path('app/phpkaiharness/config_overrides.json');
        $directory = dirname($overridePath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }
        File::put($overridePath, json_encode($updated, JSON_PRETTY_PRINT));

        // Best-effort: also write directly to config/harness.php so changes
        // are visible in the file itself. This can fail with a permission
        // error if config/ is owned by a different user (e.g. after a
        // deploy script running as root/deploy-user overwrote ownership).
        // We do not let that failure surface as a 500 — the overrides JSON
        // above already guarantees the settings take effect.
        // Clear config cache so the new file is read on next request
        $configWriteWarning = null;
        try {
            $phpConfig = $this->buildPhpConfigString($updated);
            File::put($configPath, $phpConfig);

            $cachedConfigPath = base_path('bootstrap/cache/config.php');
            if (File::exists($cachedConfigPath)) {
                File::delete($cachedConfigPath);
            }
        } catch (\Throwable $e) {
            $configWriteWarning = 'Note: config/harness.php could not be updated directly ('.$e->getMessage().'). Settings were saved to overrides and will still apply.';
        }

        // Reload config in current process
        foreach ($this->flattenConfig($updated, 'harness') as $key => $value) {
            config([$key => $value]);
        }

        // Programmatically reload Octane and Horizon workers to apply changes to all long-running processes
        if (function_exists('app')) {
            try {
                Artisan::call('config:clear');
            } catch (\Throwable $e) {
            }
            try {
                Artisan::call('route:clear');
            } catch (\Throwable $e) {
            }
            try {
                Artisan::call('view:clear');
            } catch (\Throwable $e) {
            }
            try {
                Artisan::call('event:clear');
            } catch (\Throwable $e) {
            }
            try {
                if (app()->bound('octane') || config('octane.server')) {
                    Artisan::call('octane:reload');
                }
            } catch (\Throwable $e) {
            }
            try {
                Artisan::call('reverb:restart');
            } catch (\Throwable $e) {
            }
            try {
                Artisan::call('pulse:restart');
            } catch (\Throwable $e) {
            }
            try {
                Artisan::call('horizon:terminate');
            } catch (\Throwable $e) {
            }

            // Reset PHP OPcache so the updated config/harness.php is not served from memory
            try {
                if (function_exists('opcache_get_status') && function_exists('opcache_reset')) {
                    if (opcache_get_status() !== false) {
                        opcache_reset();
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $message = $configWriteWarning ?? 'Configuration saved, all compiled caches cleared, and workers restarted.';

        return redirect()->route('harness.config')
            ->with($configWriteWarning ? 'warning' : 'success', $message);
    }

    /**
     * Build a PHP config file string from the config array.
     *
     * @param  array<string, mixed>  $config
     */
    private function buildPhpConfigString(array $config): string
    {
        $export = $this->varExportPretty($config, 1);

        return "<?php\n\nreturn {$export};\n";
    }

    /**
     * Export a PHP array with clean indentation (no numeric keys for associative arrays).
     */
    private function varExportPretty(mixed $value, int $indent = 1): string
    {
        if (is_array($value)) {
            if (empty($value)) {
                return '[]';
            }

            $isAssoc = ! array_is_list($value);
            $pad = str_repeat('    ', $indent);
            $lines = [];
            foreach ($value as $key => $item) {
                $keyStr = $isAssoc ? "'{$key}' => " : '';
                $lines[] = $pad.$keyStr.$this->varExportPretty($item, $indent + 1);
            }

            $closePad = str_repeat('    ', $indent - 1);

            return "[\n".implode(",\n", $lines).",\n".$closePad.']';
        }
        if (is_string($value)) {
            return "'".str_replace("'", "\\'", $value)."'";
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if ($value === null) {
            return 'null';
        }

        return var_export($value, true);
    }

    /**
     * Build the updated config array from POST data.
     *
     * Starts from the current effective configuration (defaults + overrides)
     * so values that are not exposed in the UI are never lost. Then it applies
     * the UI-submitted values, including the advanced JSON array fields.
     *
     * @return array<string, mixed>
     */
    private function buildUpdatedConfig(Request $request): array
    {
        $updated = config('harness') ?? [];

        $updated['config_mode'] = $request->input('config_mode', config('harness.config_mode', 'force'));

        $updated['routing']['local_intent']['enabled'] = $request->boolean('routing_local_intent_enabled', (bool) config('harness.routing.local_intent.enabled', true));
        $updated['routing']['local_intent']['confidence_threshold'] = (float) $request->input('routing_local_intent_confidence_threshold', config('harness.routing.local_intent.confidence_threshold', 0.9));

        $updated['default'] = [
            'provider' => $request->input('default_provider', config('harness.default.provider', 'ollama')),
            'model' => $request->input('default_model', config('harness.default.model', '')),
            'max_iterations' => (int) $request->input('default_max_iterations', config('harness.default.max_iterations', 10)),
        ];

        $updated['failover'] = [
            'enabled' => $request->boolean('failover_enabled', (bool) config('harness.failover.enabled', false)),
            'clients' => $this->parseJsonArray($request->input('failover_clients_json'), config('harness.failover.clients', [
                ['provider' => 'ollama', 'model' => 'llama3.2'],
                ['provider' => 'lmstudio', 'model' => 'gemma-2b-it'],
            ])),
        ];

        $updated['feature_graph']['nodes'] = [
            'draft_verification' => ['enabled' => $request->boolean('fg_draft_verification', (bool) config('harness.feature_graph.nodes.draft_verification.enabled', true))],
            'environment_bootstrap' => ['enabled' => $request->boolean('fg_environment_bootstrap', (bool) config('harness.feature_graph.nodes.environment_bootstrap.enabled', false))],
            'context_compression' => ['enabled' => $request->boolean('fg_context_compression', (bool) config('harness.feature_graph.nodes.context_compression.enabled', true))],
            'model_optimizer' => ['enabled' => $request->boolean('fg_model_optimizer', (bool) config('harness.feature_graph.nodes.model_optimizer.enabled', true))],
            'ontology_injection' => ['enabled' => $request->boolean('fg_ontology_injection', (bool) config('harness.feature_graph.nodes.ontology_injection.enabled', true))],
            'semantic_cache' => ['enabled' => $request->boolean('fg_semantic_cache', (bool) config('harness.feature_graph.nodes.semantic_cache.enabled', true))],
            'context_compactor' => ['enabled' => $request->boolean('fg_context_compactor', (bool) config('harness.feature_graph.nodes.context_compactor.enabled', true))],
            'guardrails' => ['enabled' => $request->boolean('fg_guardrails', (bool) config('harness.feature_graph.nodes.guardrails.enabled', true))],
            'cognitive_memory' => ['enabled' => $request->boolean('fg_cognitive_memory', (bool) config('harness.feature_graph.nodes.cognitive_memory.enabled', true))],
            'quantum_harness' => ['enabled' => $request->boolean('fg_quantum_harness', (bool) config('harness.feature_graph.nodes.quantum_harness.enabled', true))],
        ];

        $updated['cache'] = [
            'enabled' => $request->boolean('cache_enabled', (bool) config('harness.cache.enabled', true)),
            'threshold' => (float) $request->input('cache_threshold', config('harness.cache.threshold', 0.88)),
            'db_path' => $request->input('cache_db_path', config('harness.cache.db_path', '')),
            'redis' => [
                'enabled' => $request->boolean('cache_redis_enabled', (bool) config('harness.cache.redis.enabled', true)),
                'connection' => $request->input('cache_redis_connection', config('harness.cache.redis.connection', 'default')),
                'decay_mode' => $request->input('cache_redis_decay_mode', config('harness.cache.redis.decay_mode', 'dissipative')),
                'subjective_field' => [
                    'enabled' => $request->boolean('cache_redis_subjective_field_enabled', (bool) config('harness.cache.redis.subjective_field.enabled', true)),
                    'bias_weight' => (float) $request->input('cache_redis_subjective_field_bias_weight', config('harness.cache.redis.subjective_field.bias_weight', 0.15)),
                ],
                'order_sensitive' => $request->boolean('cache_redis_order_sensitive', (bool) config('harness.cache.redis.order_sensitive', true)),
            ],
            'verify_with_llm' => $request->boolean('cache_verify_with_llm', (bool) config('harness.cache.verify_with_llm', false)),
            'verify_model' => $request->input('cache_verify_model', config('harness.cache.verify_model', 'qwen-turbo')),
        ];

        $updated['pii_masking'] = [
            'enabled' => $request->boolean('pii_masking_enabled', (bool) config('harness.pii_masking.enabled', true)),
            'patterns' => $this->parseJsonArray($request->input('pii_masking_patterns_json'), config('harness.pii_masking.patterns', [
                'EMAIL' => '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
                'IP' => '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
                'CREDIT_CARD' => '/\b(?:\d[ \-]*?){13,16}\b/',
                'API_KEY' => '/\b[A-Za-z0-9_\-]{32,64}\b/',
                'PHONE' => '/\b\d{3}[\s-]?\d{3}[\s-]?\d{4}\b/',
            ])),
        ];

        $updated['rate_limiting'] = [
            'enabled' => $request->boolean('rate_limiting_enabled', (bool) config('harness.rate_limiting.enabled', false)),
            'requests_per_minute' => (int) $request->input('rate_limiting_rpm', config('harness.rate_limiting.requests_per_minute', 1200)),
            'cooldown_ms' => (int) $request->input('rate_limiting_cooldown_ms', config('harness.rate_limiting.cooldown_ms', 0)),
        ];

        $updated['guardrails'] = [
            'enabled' => $request->boolean('guardrails_enabled', (bool) config('harness.guardrails.enabled', true)),
            'high_risk_tools' => $this->parseJsonArray($request->input('guardrails_high_risk_tools_json'), config('harness.guardrails.high_risk_tools', ['wsl_command', 'delete_*', 'execute_*', 'rm_*'])),
            'authorized_scopes' => $this->parseJsonArray($request->input('guardrails_authorized_scopes_json'), config('harness.guardrails.authorized_scopes', ['admin', 'sizing', 'analytics', 'read-only'])),
            'tool_scope_map' => $this->parseJsonArray($request->input('guardrails_tool_scope_map_json'), config('harness.guardrails.tool_scope_map', [
                'wsl_command' => ['admin'],
                'delete_*' => ['admin', 'write'],
                'execute_*' => ['admin'],
            ])),
        ];

        $updated['optimizer'] = [
            'enabled' => $request->boolean('optimizer_enabled', (bool) config('harness.optimizer.enabled', true)),
        ];

        $updated['ontology'] = [
            'enabled' => $request->boolean('ontology_enabled', (bool) config('harness.ontology.enabled', true)),
            'embedding_column' => $request->input('ontology_embedding_column', config('harness.ontology.embedding_column', 'embedding')),
            'similarity_threshold' => (float) $request->input('ontology_similarity_threshold', config('harness.ontology.similarity_threshold', 0.15)),
            'max_records' => (int) $request->input('ontology_max_records', config('harness.ontology.max_records', 5)),
            'db_path' => $request->input('ontology_db_path', config('harness.ontology.db_path', '')),
            'namespaces' => [
                'enabled' => $request->boolean('ontology_namespaces_enabled', (bool) config('harness.ontology.namespaces.enabled', true)),
            ],
        ];

        $updated['policy_guardrail'] = [
            'enabled' => $request->boolean('policy_guardrail_enabled', (bool) config('harness.policy_guardrail.enabled', true)),
        ];

        $updated['compaction'] = [
            'strategy' => $request->input('compaction_strategy', config('harness.compaction.strategy', 'sliding_window')),
            'max_turns' => (int) $request->input('compaction_max_turns', config('harness.compaction.max_turns', 200)),
            'max_tokens_threshold' => (int) $request->input('compaction_max_tokens_threshold', config('harness.compaction.max_tokens_threshold', 40000)),
            'compression' => [
                'enabled' => $request->boolean('compression_enabled', (bool) config('harness.compaction.compression.enabled', true)),
                'line_threshold' => (int) $request->input('compression_line_threshold', config('harness.compaction.compression.line_threshold', 150)),
            ],
        ];

        $updated['bootstrap'] = [
            'enabled' => $request->boolean('bootstrap_enabled', (bool) config('harness.bootstrap.enabled', false)),
        ];

        $updated['budget'] = [
            'enabled' => $request->boolean('budget_enabled', (bool) config('harness.budget.enabled', false)),
            'max_tokens' => (int) $request->input('budget_max_tokens', config('harness.budget.max_tokens', 30000000)),
        ];

        $updated['cognitive_memory'] = [
            'enabled' => $request->boolean('cognitive_memory_enabled', (bool) config('harness.cognitive_memory.enabled', true)),
            'max_depth' => (int) $request->input('cognitive_memory_max_depth', config('harness.cognitive_memory.max_depth', 3)),
            'coherence_threshold' => (float) $request->input('cognitive_memory_coherence_threshold', config('harness.cognitive_memory.coherence_threshold', 0.15)),
            'decay_rate' => (float) $request->input('cognitive_memory_decay_rate', config('harness.cognitive_memory.decay_rate', 0.05)),
        ];

        $updated['draft_verification'] = [
            'enabled' => $request->boolean('draft_verification_enabled', (bool) config('harness.draft_verification.enabled', true)),
        ];

        $updated['quantum_harness'] = [
            'enabled' => $request->boolean('quantum_harness_enabled', (bool) config('harness.quantum_harness.enabled', true)),
            'db_path' => $request->input('quantum_harness_db_path', config('harness.quantum_harness.db_path', '')),
            'alpha' => (float) $request->input('quantum_alpha', config('harness.quantum_harness.alpha', 0.7)),
            'beta' => (float) $request->input('quantum_beta', config('harness.quantum_harness.beta', 0.3)),
            'similarity_threshold' => (float) $request->input('quantum_similarity_threshold', config('harness.quantum_harness.similarity_threshold', 0.30)),
            'max_anchors' => (int) $request->input('quantum_max_anchors', config('harness.quantum_harness.max_anchors', 3)),
            'coherence_decay' => (float) $request->input('quantum_coherence_decay', config('harness.quantum_harness.coherence_decay', 0.05)),
            'density_matrix_bias' => (float) $request->input('quantum_density_matrix_bias', config('harness.quantum_harness.density_matrix_bias', 0.10)),
        ];

        $updated['qwen_provider'] = [
            'enabled' => $request->boolean('qwen_provider_enabled', (bool) config('harness.qwen_provider.enabled', true)),
            'api_key' => $request->input('qwen_provider_api_key', config('harness.qwen_provider.api_key', '')),
            'url' => $request->input('qwen_provider_url', config('harness.qwen_provider.url', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1')),
            'model' => $request->input('qwen_provider_model', config('harness.qwen_provider.model', 'qwen-plus')),
            'light_model' => $request->input('qwen_provider_light_model', config('harness.qwen_provider.light_model', 'qwen-turbo')),
            'structured_output' => $request->input('qwen_provider_structured_output', config('harness.qwen_provider.structured_output', 'json_object')),
            'max_tokens' => (int) $request->input('qwen_provider_max_tokens', config('harness.qwen_provider.max_tokens', 4096)),
        ];

        $updated['session_isolation'] = [
            'enabled' => $request->boolean('session_isolation_enabled', (bool) config('harness.session_isolation.enabled', true)),
            'base_path' => $request->input('session_isolation_base_path', config('harness.session_isolation.base_path', '')),
            'cleanup_hours' => (int) $request->input('session_isolation_cleanup_hours', config('harness.session_isolation.cleanup_hours', 24)),
        ];

        $updated['telemetry'] = [
            'enabled' => $request->boolean('telemetry_enabled', (bool) config('harness.telemetry.enabled', true)),
            'route_prefix' => $request->input('telemetry_route_prefix', config('harness.telemetry.route_prefix', 'harness')),
            'middleware' => $this->parseJsonArray($request->input('telemetry_middleware_json'), config('harness.telemetry.middleware', ['web', 'auth', 'permission:harness_analytics'])),
        ];

        return $updated;
    }

    /**
     * Parse a JSON string from the UI into an array. Falls back to the default
     * value if the input is empty or invalid, so saving the config never
     * corrupts array-shaped settings.
     *
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    private static function parseJsonArray(?string $json, array $default): array
    {
        if ($json === null || trim($json) === '') {
            return $default;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Flatten a nested config array into dot-notation keys.
     *
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    private function flattenConfig(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenConfig($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }
}
