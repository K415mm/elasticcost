<?php

namespace Phpkaiharness\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
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

        $overridePath = storage_path('app/phpkaiharness/config_overrides.json');
        $directory = dirname($overridePath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }

        File::put($overridePath, json_encode($updated, JSON_PRETTY_PRINT));

        // Reload config in current process
        foreach ($this->flattenConfig($updated, 'harness') as $key => $value) {
            config([$key => $value]);
        }

        return redirect()->route('harness.config')
            ->with('success', 'Configuration saved successfully.');
    }

    /**
     * Build the updated config array from POST data.
     *
     * @return array<string, mixed>
     */
    private function buildUpdatedConfig(Request $request): array
    {
        return [
            'default' => [
                'provider' => $request->input('default_provider', config('harness.default.provider')),
                'model' => $request->input('default_model', config('harness.default.model')),
                'max_iterations' => (int) $request->input('default_max_iterations', config('harness.default.max_iterations')),
            ],
            'failover' => [
                'enabled' => $request->boolean('failover_enabled'),
                'clients' => config('harness.failover.clients'),
            ],
            'feature_graph' => [
                'nodes' => [
                    'draft_verification' => ['enabled' => $request->boolean('fg_draft_verification')],
                    'environment_bootstrap' => ['enabled' => $request->boolean('fg_environment_bootstrap')],
                    'context_compression' => ['enabled' => $request->boolean('fg_context_compression')],
                    'model_optimizer' => ['enabled' => $request->boolean('fg_model_optimizer')],
                    'ontology_injection' => ['enabled' => $request->boolean('fg_ontology_injection')],
                    'semantic_cache' => ['enabled' => $request->boolean('fg_semantic_cache')],
                    'context_compactor' => ['enabled' => $request->boolean('fg_context_compactor')],
                    'guardrails' => ['enabled' => $request->boolean('fg_guardrails')],
                    'cognitive_memory' => ['enabled' => $request->boolean('fg_cognitive_memory')],
                    'quantum_harness' => ['enabled' => $request->boolean('fg_quantum_harness')],
                ],
            ],
            'cache' => [
                'enabled' => $request->boolean('cache_enabled'),
                'threshold' => (float) $request->input('cache_threshold', config('harness.cache.threshold')),
                'db_path' => config('harness.cache.db_path'),
            ],
            'pii_masking' => [
                'enabled' => $request->boolean('pii_masking_enabled'),
                'patterns' => config('harness.pii_masking.patterns'),
            ],
            'rate_limiting' => [
                'enabled' => $request->boolean('rate_limiting_enabled'),
                'requests_per_minute' => (int) $request->input('rate_limiting_rpm', config('harness.rate_limiting.requests_per_minute')),
                'cooldown_ms' => (int) $request->input('rate_limiting_cooldown_ms', config('harness.rate_limiting.cooldown_ms')),
            ],
            'guardrails' => [
                'enabled' => $request->boolean('guardrails_enabled'),
                'high_risk_tools' => config('harness.guardrails.high_risk_tools'),
                'authorized_scopes' => config('harness.guardrails.authorized_scopes'),
                'tool_scope_map' => config('harness.guardrails.tool_scope_map'),
            ],
            'optimizer' => [
                'enabled' => $request->boolean('optimizer_enabled'),
            ],
            'ontology' => [
                'enabled' => $request->boolean('ontology_enabled'),
                'embedding_column' => $request->input('ontology_embedding_column', config('harness.ontology.embedding_column')),
                'similarity_threshold' => (float) $request->input('ontology_similarity_threshold', config('harness.ontology.similarity_threshold')),
                'max_records' => (int) $request->input('ontology_max_records', config('harness.ontology.max_records')),
            ],
            'policy_guardrail' => [
                'enabled' => $request->boolean('policy_guardrail_enabled'),
            ],
            'compaction' => [
                'strategy' => $request->input('compaction_strategy', config('harness.compaction.strategy')),
                'max_turns' => (int) $request->input('compaction_max_turns', config('harness.compaction.max_turns')),
                'max_tokens_threshold' => (int) $request->input('compaction_max_tokens_threshold', config('harness.compaction.max_tokens_threshold')),
                'compression' => [
                    'enabled' => $request->boolean('compression_enabled'),
                    'line_threshold' => (int) $request->input('compression_line_threshold', config('harness.compaction.compression.line_threshold', 150)),
                ],
            ],
            'bootstrap' => [
                'enabled' => $request->boolean('bootstrap_enabled'),
            ],
            'budget' => [
                'enabled' => $request->boolean('budget_enabled'),
                'max_tokens' => (int) $request->input('budget_max_tokens', config('harness.budget.max_tokens', 30000)),
            ],
            'cognitive_memory' => [
                'enabled' => $request->boolean('cognitive_memory_enabled'),
            ],
            'draft_verification' => [
                'enabled' => $request->boolean('draft_verification_enabled'),
            ],
            'quantum_harness' => [
                'enabled' => $request->boolean('quantum_harness_enabled'),
                'db_path' => $request->input('quantum_db_path', config('harness.quantum_harness.db_path')),
                'alpha' => (float) $request->input('quantum_alpha', config('harness.quantum_harness.alpha', 0.7)),
                'beta' => (float) $request->input('quantum_beta', config('harness.quantum_harness.beta', 0.3)),
                'similarity_threshold' => (float) $request->input('quantum_similarity_threshold', config('harness.quantum_harness.similarity_threshold', 0.30)),
                'max_anchors' => (int) $request->input('quantum_max_anchors', config('harness.quantum_harness.max_anchors', 3)),
            ],
            'qwen_provider' => [
                'enabled' => $request->boolean('qwen_provider_enabled'),
                'structured_output' => $request->input('qwen_provider_structured_output', config('harness.qwen_provider.structured_output', 'json_object')),
                'max_tokens' => (int) $request->input('qwen_provider_max_tokens', config('harness.qwen_provider.max_tokens', 4096)),
            ],
            'session_isolation' => [
                'enabled' => $request->boolean('session_isolation_enabled'),
                'base_path' => $request->input('session_isolation_base_path', config('harness.session_isolation.base_path')),
                'cleanup_hours' => (int) $request->input('session_isolation_cleanup_hours', config('harness.session_isolation.cleanup_hours', 24)),
            ],
            'telemetry' => [
                'enabled' => $request->boolean('telemetry_enabled'),
                'route_prefix' => $request->input('telemetry_route_prefix', config('harness.telemetry.route_prefix', 'harness')),
                'middleware' => config('harness.telemetry.middleware'),
            ],
        ];
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
