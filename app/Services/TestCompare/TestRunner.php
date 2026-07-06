<?php

namespace App\Services\TestCompare;

use App\Ai\Adapters\LaravelToolAdapter;
use App\Ai\Agents\ElasticCostAssistant;
use App\Ai\Agents\RgSocEngineer;
use App\Ai\Agents\RgSocEngineerMain;
use App\Ai\Analytics\LaravelAnalyticsCollector;
use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\GlobalSetting;
use App\Services\AiConfigHelper;
use App\Services\TestCompare\AiEvaluator;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Laravel\Ai\Contracts\Tool;
use Laravel\Horizon\Contracts\JobRepository;
use Phpkaiharness\Core\AgentLoop;
use Phpkaiharness\Core\Registry\ToolRegistry;
use Phpkaiharness\Events\AgentFinished;
use Phpkaiharness\Events\AgentStarted;
use Phpkaiharness\Events\LlmCallFinished;
use Phpkaiharness\Events\LlmCallStarted;
use Phpkaiharness\Events\ToolCallFinished;
use Phpkaiharness\Events\ToolCallStarted;
use Phpkaiharness\Llm\LaravelAiClient;
use Phpkaiharness\Session\SessionManager;

/**
 * Executes test requests in 4 modes and collects probe data:
 * - A1:       Direct Qwen Cloud API call (no harness, no tools, no pipeline)
 * - A2:       AgentLoop with all features disabled (loop overhead baseline)
 * - B-cold:   Full phpkaiharness (all features enabled, tools, pipeline) — cold cache
 * - B-warm:   Full phpkaiharness — warm cache (runs after B-cold, skips cache clear)
 */
class TestRunner
{
    private string $outputDir;

    private string $runId;

    private float $runStartTime;

    public function __construct(?string $outputDir = null)
    {
        $baseDir = $outputDir ?? base_path('testandcompare');
        $this->runId = date('Ymd-His');
        // Per-run subdirectory: testandcompare/runs/{run_id}/
        $this->outputDir = $baseDir.'/runs/'.$this->runId;
        $this->runStartTime = microtime(true);

        // Pre-create the run directory to catch permission issues early
        if (! is_dir($this->outputDir)) {
            @mkdir($this->outputDir, 0775, true);
        }
    }

    public function getOutputDir(): string
    {
        return $this->outputDir;
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    /**
     * Run all tests across all 3 modes.
     *
     * @param  callable|null  $onProgress  Called with (mode, index, total, status) for progress tracking.
     * @return array{summary: array, traces: array}
     */
    public function runAll(?callable $onProgress = null, ?string $filterMode = null): array
    {
        $dataset = TestDataset::all();
        $total = count($dataset);
        $allTraces = [];

        // Run order: A1 (baseline) → A2 (loop overhead) → B-cold (full harness, cold cache) → B-warm (warm cache)
        $allModes = ['A1-direct-api', 'A2-loop-no-features', 'B-full-harness', 'B-warm-harness'];
        $modes = $filterMode ? [$filterMode] : $allModes;

        // Load existing traces for modes not being re-run
        foreach ($allModes as $existingMode) {
            if (! in_array($existingMode, $modes)) {
                $existingTraces = $this->loadExistingTraces($existingMode);
                if (! empty($existingTraces)) {
                    $allTraces[$existingMode] = $existingTraces;
                }
            }
        }

        foreach ($modes as $mode) {
            // B-warm skips cache clear — the whole point is that the semantic cache is warm from B-cold
            if ($mode !== 'B-warm-harness') {
                $this->clearCacheAndReconfigure();
            } else {
                // Re-configure AI but do NOT clear cache — warm cache from B-cold run
                AiConfigHelper::configure();
            }

            $traces = [];

            for ($i = 0; $i < $total; $i++) {
                if ($onProgress) {
                    $onProgress($mode, $i, $total, 'running');
                }

                try {
                    $probe = match ($mode) {
                        'B-full-harness', 'B-warm-harness' => $this->runFullHarness($i, $dataset[$i], $mode),
                        'A2-loop-no-features' => $this->runLoopNoFeatures($i, $dataset[$i]),
                        'A1-direct-api' => $this->runDirectApi($i, $dataset[$i]),
                    };
                    $probe->runId = $this->runId;
                    $traces[] = $probe->toArray();
                    if ($onProgress) {
                        $onProgress($mode, $i, $total, 'done');
                    }
                } catch (\Throwable $e) {
                    $probe = new TestProbe($mode, $i, $dataset[$i]);
                    $probe->recordError($e->getMessage());
                    $probe->finish('');
                    $traces[] = $probe->toArray();
                    if ($onProgress) {
                        $onProgress($mode, $i, $total, 'error');
                    }
                    Log::error("TestRunner error [{$mode}] #{$i}: ".$e->getMessage());
                }
            }

            $allTraces[$mode] = $traces;
            $this->saveTraces($mode, $traces);

            // Clear cache after each mode to start fresh — EXCEPT B-warm which needs the warm cache from B-cold
            if ($mode !== 'B-warm-harness') {
                $this->clearCacheAndReconfigure();
            }
        }

        // Run AI evaluations across all modes for all 20 requests
        $allTraces = $this->runAiEvaluations($allTraces);

        $summary = $this->computeSummary($allTraces);
        $summary['_meta'] = [
            'run_id' => $this->runId,
            'run_start' => date('c', (int) $this->runStartTime),
            'run_end' => date('c'),
            'total_modes' => count($allModes),
            'total_executions' => count($allModes) * $total,
            'modes_run' => $modes,
        ];
        $this->saveSummary($summary);

        return ['summary' => $summary, 'traces' => $allTraces];
    }

    /**
     * Clear phpkaiharness cache, semantic cache, and reconfigure AI provider from system settings.
     */
    private function clearCacheAndReconfigure(): void
    {
        // Re-read AI config from database settings
        AiConfigHelper::configure();

        // Clear semantic cache from monitor.db (harness_details with type=cache)
        $cacheDbPath = config('harness.cache.db_path');
        if ($cacheDbPath && file_exists($cacheDbPath)) {
            try {
                $pdo = new \PDO('sqlite:'.$cacheDbPath);
                // Try cache_entries table first, then fall back to harness_details
                try {
                    $pdo->exec('DELETE FROM cache_entries WHERE 1=1');
                } catch (\Throwable $e) {
                    $pdo->exec("DELETE FROM harness_details WHERE type = 'cache' AND 1=1");
                }
                $pdo = null;
            } catch (\Throwable $e) {
                // Ignore cache clear errors
            }
        } else {
            // Fallback: clear from monitor.db directly
            $monitorPath = storage_path('app/phpkaiharness/monitor.db');
            if (file_exists($monitorPath)) {
                try {
                    $pdo = new \PDO('sqlite:'.$monitorPath);
                    $pdo->exec("DELETE FROM harness_details WHERE type = 'cache' AND 1=1");
                    $pdo = null;
                } catch (\Throwable $e) {
                    // Ignore
                }
            }
        }

        // Clear quantum memory session data for fresh start
        $quantumDbPath = config('harness.quantum_harness.db_path');
        if ($quantumDbPath && file_exists($quantumDbPath)) {
            try {
                $pdo = new \PDO('sqlite:'.$quantumDbPath);
                $pdo->exec("DELETE FROM memory_nodes WHERE session_id LIKE 'testcmp_%");
                $pdo = null;
            } catch (\Throwable $e) {
                // Ignore quantum clear errors
            }
        }

        // Clear only phpkaiharness-related Redis keys (NOT Horizon keys)
        try {
            $prefix = config('database.redis.default.prefix', '');
            $keys = Redis::keys($prefix.'phpkaiharness:*');
            foreach ($keys as $key) {
                Redis::del($key);
            }
        } catch (\Throwable $e) {
            // Redis may not be available
        }
    }

    /**
     * Mode A1: Direct Qwen Cloud API call — no harness, no tools, no pipeline.
     */
    private function runDirectApi(int $index, array $data): TestProbe
    {
        $probe = new TestProbe('A1-direct-api', $index, $data);

        $aiConfig = AiConfigHelper::configure();
        $provider = $aiConfig['provider'];
        $providerStr = $provider instanceof \BackedEnum ? $provider->value : (string) $provider;
        $model = $aiConfig['model'];

        $probe->model = $model;
        $probe->provider = $providerStr;
        $probe->effectivePrompt = $data['prompt'];

        // Get the agent instructions as system prompt (no optimization)
        $agent = $data['agent'] === 'RgSocEngineer'
            ? new RgSocEngineerMain
            : new ElasticCostAssistant;
        $probe->originalSystemPrompt = (string) $agent->instructions();
        $probe->optimizedSystemPrompt = $probe->originalSystemPrompt;

        // Build API payload directly (bypassing AgentLoop, QwenClient, and all pipeline stages)
        $messages = [
            ['role' => 'system', 'content' => $probe->originalSystemPrompt],
            ['role' => 'user', 'content' => $data['prompt']],
        ];

        $baseUrl = $this->getProviderBaseUrl($providerStr);
        $apiKey = $this->getProviderApiKey($providerStr);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => 12000,
            'temperature' => 0.7,
            'top_p' => 0.8,
        ];

        // Only reasoning models support enable_thinking — flash/omni models do not
        $isThinkingModel = (str_starts_with($model, 'qwen3') || str_starts_with($model, 'qwq'))
            && ! str_contains($model, 'flash')
            && ! str_contains($model, 'omni');
        if ($isThinkingModel) {
            $payload['enable_thinking'] = true;
        }

        $httpClient = new Client([
            'timeout' => 300,
            'verify' => config('app.env') === 'local' ? false : true,
        ]);
        $response = $httpClient->post($baseUrl.'/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        $body = json_decode($response->getBody()->getContents(), true);
        $content = $body['choices'][0]['message']['content'] ?? '';

        // Strip thinking tags (same as QwenClient does)
        $content = $this->stripThinking($content);

        $probe->llmCalls = 1;
        $probe->iterations = 1;
        $probe->finish($content);

        return $probe;
    }

    /**
     * Mode A2: AgentLoop with all feature_graph nodes disabled.
     */
    private function runLoopNoFeatures(int $index, array $data): TestProbe
    {
        $probe = new TestProbe('A2-loop-no-features', $index, $data);

        // Temporarily disable all feature_graph nodes
        $originalConfig = config('harness.feature_graph.nodes');
        $originalCache = config('harness.cache.enabled');
        $originalCompaction = config('harness.compaction');
        $originalBudget = config('harness.budget.enabled');
        $originalCompression = config('harness.compression.enabled');

        Config::set('harness.feature_graph.nodes', array_map(
            fn ($node) => array_merge($node, ['enabled' => false]),
            $originalConfig
        ));
        Config::set('harness.cache.enabled', false);
        Config::set('harness.compaction.strategy', 'none');
        Config::set('harness.budget.enabled', false);
        Config::set('harness.compression.enabled', false);
        Config::set('harness.failover.enabled', false);

        $originalFailover = config('harness.failover.enabled');

        try {
            $this->executeViaAgentLoop($probe, $data, withTools: true);
        } finally {
            // Restore config
            Config::set('harness.feature_graph.nodes', $originalConfig);
            Config::set('harness.cache.enabled', $originalCache);
            Config::set('harness.compaction', $originalCompaction);
            Config::set('harness.budget.enabled', $originalBudget);
            Config::set('harness.compression.enabled', $originalCompression);
            Config::set('harness.failover.enabled', $originalFailover);
        }

        return $probe;
    }

    /**
     * Mode B: Full phpkaiharness with all features enabled.
     * RgSocEngineer uses queue() → Horizon job → poll DB message (real app flow).
     * ElasticCostAssistant uses prompt() directly (synchronous).
     */
    private function runFullHarness(int $index, array $data, string $mode = 'B-full-harness'): TestProbe
    {
        $probe = new TestProbe($mode, $index, $data);

        // Listen to harness events to capture pipeline stages and tool calls
        $eventListeners = $this->attachEventListeners($probe);

        // Configure AI provider from app settings (same as real app)
        $aiConfig = AiConfigHelper::configure();
        $provider = $aiConfig['provider'];
        $providerStr = $provider instanceof \BackedEnum ? $provider->value : (string) $provider;
        $model = $aiConfig['model'];

        // ── Enable ALL phpkaiharness features for B mode (full harness) ──
        Config::set('harness.feature_graph.nodes.draft_verification.enabled', true);
        Config::set('harness.feature_graph.nodes.prompt_middleware.enabled', true);
        Config::set('harness.feature_graph.nodes.model_optimizer.enabled', true);
        Config::set('harness.feature_graph.nodes.ontology_injection.enabled', true);
        Config::set('harness.feature_graph.nodes.semantic_cache.enabled', true);
        Config::set('harness.feature_graph.nodes.context_compactor.enabled', true);
        Config::set('harness.feature_graph.nodes.guardrails.enabled', true);
        Config::set('harness.feature_graph.nodes.cognitive_memory.enabled', true);
        Config::set('harness.feature_graph.nodes.quantum_harness.enabled', true);

        // Also set legacy config keys for components that read them directly
        Config::set('harness.cache.enabled', true);
        Config::set('harness.guardrails.enabled', true);
        Config::set('harness.optimizer.enabled', true);
        Config::set('harness.ontology.enabled', true);
        Config::set('harness.compression.enabled', true);
        Config::set('harness.budget.enabled', true);
        Config::set('harness.cognitive_memory.enabled', true);
        Config::set('harness.draft_verification.enabled', true);
        Config::set('harness.quantum_harness.enabled', true);
        Config::set('harness.failover.enabled', true);
        Config::set('harness.compaction.strategy', 'sliding_window');

        $probe->model = $model;
        $probe->provider = $providerStr;
        $probe->effectivePrompt = $data['prompt'];

        // Use the actual agent class
        $agent = $data['agent'] === 'RgSocEngineer'
            ? new RgSocEngineer
            : new ElasticCostAssistant;
        $probe->originalSystemPrompt = (string) $agent->instructions();
        $probe->optimizedSystemPrompt = (string) $agent->instructions();

        $maxRetries = 3;
        $retryDelay = 10;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                if ($data['agent'] === 'RgSocEngineer') {
                    // Real app flow: create conversation + pending message, dispatch to Horizon, poll for result
                    $multiConfig = AiConfigHelper::configureMultiModel();
                    $lightProvider = $multiConfig['light']['provider'];
                    $lightModel = $multiConfig['light']['model'];

                    $conversation = AgentConversation::create([
                        'title' => 'Test #'.($index + 1).' — '.$data['category'],
                        'agent' => 'RgSocEngineer',
                    ]);

                    $userMessage = $conversation->messages()->create([
                        'role' => 'user',
                        'content' => $data['prompt'],
                        'agent' => 'RgSocEngineer',
                    ]);

                    $pendingMessage = $conversation->messages()->create([
                        'role' => 'assistant',
                        'content' => '_Agent is working on your request..._',
                        'agent' => 'RgSocEngineer',
                        'meta' => ['status' => 'pending', 'job_id' => null],
                    ]);

                    $messageId = $pendingMessage->id;
                    $sessionId = "testcmp_{$this->runId}_{$mode}_{$index}";
                    $agent->phpSessionId = $sessionId;
                    // Dispatch to Horizon — callbacks update the DB message inside the worker
                    $agent->queue($data['prompt'], [], $lightProvider, $lightModel)
                        ->then(function ($response) use ($messageId, $conversation) {
                            $message = AgentConversationMessage::find($messageId);
                            if ($message) {
                                $toolCallsMeta = [];
                                if (property_exists($response, 'toolCalls') && $response->toolCalls instanceof Collection) {
                                    foreach ($response->toolCalls as $tc) {
                                        $toolCallsMeta[] = [
                                            'name' => $tc->name ?? 'unknown',
                                            'arguments' => $tc->arguments ?? [],
                                            'result' => $tc->result ?? '',
                                        ];
                                    }
                                }
                                $message->update([
                                    'content' => $response->text,
                                    'meta' => [
                                        'status' => 'completed',
                                        'job_id' => $message->meta['job_id'] ?? null,
                                        'tool_calls' => $toolCallsMeta,
                                    ],
                                ]);
                                $conversation->touch();
                            }
                        })
                        ->catch(function (\Throwable $e) use ($messageId) {
                            $message = AgentConversationMessage::find($messageId);
                            if ($message) {
                                $message->update([
                                    'content' => 'Agent error: '.$e->getMessage(),
                                    'meta' => ['status' => 'failed', 'job_id' => $message->meta['job_id'] ?? null],
                                ]);
                            }
                        });

                    $probe->recordStage('horizon_dispatch', 'dispatched', 'RgSocEngineer queued to Horizon');

                    // Poll the DB message for completion (up to 300s)
                    $waitStart = time();
                    $maxWait = 300;
                    $responseText = '';
                    $jobError = null;

                    while (time() - $waitStart < $maxWait) {
                        sleep(5);

                        $msg = AgentConversationMessage::find($messageId);
                        if (! $msg) {
                            break;
                        }

                        $status = $msg->meta['status'] ?? 'pending';

                        // Check Horizon pending jobs count
                        try {
                            $jobRepo = app(JobRepository::class);
                            $pending = $jobRepo->countPending();
                            $probe->recordStage('horizon_poll', 'waiting', "status={$status}, {$pending} Horizon jobs pending, waited ".(time() - $waitStart).'s');
                        } catch (\Throwable $e) {
                            // Horizon not available
                        }

                        if ($status === 'completed') {
                            $responseText = $msg->content;
                            break;
                        }
                        if ($status === 'failed') {
                            $jobError = $msg->content;
                            break;
                        }
                    }

                    if ($jobError !== null) {
                        throw new \RuntimeException($jobError);
                    }

                    if ($responseText === '') {
                        $responseText = '(timeout: Horizon job did not complete within 300s)';
                    }

                    // Extract tool calls from conversation messages (Horizon worker may have recorded them)
                    $this->extractToolCallsFromConversation($conversation, $probe);

                    // Strip Qwen thinking tags
                    $responseText = $this->stripThinking($responseText);

                    $probe->iterations = 1;
                    $probe->llmCalls = 1;

                    // Adjust latency to exclude Horizon polling overhead
                    // The probe started at construction, but we spent time sleeping in 5s intervals
                    // Subtract the polling sleep time to get actual agent execution latency
                    $pollingOverhead = (int) ((time() - $waitStart) - ($responseText !== '' ? 0 : 0));
                    // The actual agent time is from dispatch to completion, not including our sleep intervals
                    // We approximate: total probe time - sum of 5s sleeps
                    $pollCount = 0;
                    $totalSleepTime = 0;
                    foreach ($probe->pipelineStages as $stage) {
                        if ($stage['stage'] === 'horizon_poll' && $stage['status'] === 'waiting') {
                            $pollCount++;
                        }
                    }
                    $totalSleepTime = $pollCount * 5; // 5s per poll
                    if ($totalSleepTime > 0 && $probe->latencyMs > $totalSleepTime * 1000) {
                        $probe->latencyMs -= $totalSleepTime * 1000;
                    }

                    $probe->finish($responseText);

                    // Re-apply adjusted latency (finish() recalculates it)
                    if ($totalSleepTime > 0) {
                        $rawLatency = (int) (($probe->endTime - $probe->startTime) * 1000);
                        $probe->latencyMs = $rawLatency - ($totalSleepTime * 1000);
                    }

                    $this->detachEventListeners($eventListeners);

                    return $probe;
                } else {
                    // ElasticCostAssistant: use AgentLoop directly (correct /chat/completions endpoint)
                    $this->executeViaAgentLoop($probe, $data, withTools: true, skipListeners: true);

                    $this->detachEventListeners($eventListeners);

                    return $probe;
                }
            } catch (\Throwable $e) {
                $errorMsg = $e->getMessage();

                // Check if it's a quota error — retry with delay
                if (str_contains($errorMsg, 'quota') || str_contains($errorMsg, '403')) {
                    if ($attempt < $maxRetries) {
                        $probe->recordStage('retry', 'waiting', "Attempt {$attempt} failed (quota), waiting {$retryDelay}s...");
                        sleep($retryDelay);
                        $retryDelay += 15;

                        continue;
                    }
                }

                $probe->recordError($errorMsg);
                $probe->finish('');
                $this->detachEventListeners($eventListeners);

                return $probe;
            }
        }

        $probe->finish('');
        $this->detachEventListeners($eventListeners);

        return $probe;
    }

    /**
     * Extract tool calls from conversation messages saved by the Horizon worker.
     */
    private function extractToolCallsFromConversation(AgentConversation $conversation, TestProbe $probe): void
    {
        // Check all assistant messages for tool call traces
        $messages = $conversation->messages()->where('role', 'assistant')->get();
        foreach ($messages as $msg) {
            $meta = $msg->meta ?? [];
            if (isset($meta['tool_calls'])) {
                foreach ($meta['tool_calls'] as $tc) {
                    $probe->recordToolCall($tc['name'] ?? 'unknown', $tc['arguments'] ?? [], $tc['result'] ?? '');
                }
            }
        }
    }

    /**
     * Execute a request through a bare AgentLoop (used by A2 mode only).
     * No failover, no pipeline stages, no sub-agent routing.
     */
    private function executeViaAgentLoop(TestProbe $probe, array $data, bool $withTools, bool $skipListeners = false): void
    {
        $aiConfig = AiConfigHelper::configure();
        $provider = $aiConfig['provider'];
        $providerStr = $provider instanceof \BackedEnum ? $provider->value : (string) $provider;
        $model = $aiConfig['model'];

        $probe->model = $model;
        $probe->provider = $providerStr;
        $probe->effectivePrompt = $data['prompt'];

        // A2 uses RgSocEngineerMain directly (no router) for RG SOC requests
        $agent = $data['agent'] === 'RgSocEngineer'
            ? new RgSocEngineerMain
            : new ElasticCostAssistant;
        $probe->originalSystemPrompt = (string) $agent->instructions();
        $probe->optimizedSystemPrompt = (string) $agent->instructions();

        $llmClient = new LaravelAiClient($providerStr, $model);

        $registry = new ToolRegistry;
        if ($withTools && method_exists($agent, 'tools')) {
            foreach ($agent->tools() as $laravelTool) {
                // Only attach actual Tool implementations, skip sub-agent references
                if (! ($laravelTool instanceof Tool)) {
                    continue;
                }
                $registry->attach(new LaravelToolAdapter($laravelTool));
            }
        }

        $sessionId = "testcmp_{$probe->runId}_{$probe->testMode}_{$probe->requestIndex}";
        $sessionManager = app(SessionManager::class);
        $sessionManager->activateSession($sessionId);
        $analytics = new LaravelAnalyticsCollector($sessionManager->resolveMonitorDbPath($sessionId));

        $loop = new AgentLoop(
            llmClient: $llmClient,
            registry: $registry,
            systemPrompt: (string) $agent->instructions(),
            model: $model,
            maxIterations: 5
        );
        $loop->setAgentName($data['agent']);

        // Attach event listeners for tool call capture (unless already attached by caller)
        $localListeners = $skipListeners ? [] : $this->attachEventListeners($probe);

        $history = [];
        $responseText = $loop->run(
            userPrompt: $data['prompt'],
            history: $history,
            sessionId: $sessionId,
            collector: $analytics
        );

        // Extract tool calls from AgentLoop (fallback if event listeners didn't capture them)
        if (count($probe->toolCalls) === 0) {
            foreach ($loop->getExecutedToolCalls() as $tc) {
                $probe->recordToolCall($tc['name'], $tc['arguments'], $tc['result']);
            }
        }

        // Strip Qwen thinking tags from response
        $responseText = $this->stripThinking($responseText);

        $probe->iterations = count($history);
        $probe->llmCalls = max($probe->llmCalls, 1);
        $probe->finish($responseText);

        if (! $skipListeners) {
            $this->detachEventListeners($localListeners);
        }
    }

    /**
     * Attach event listeners to capture pipeline stages and tool calls.
     * Returns an array of listeners for later cleanup.
     */
    private function attachEventListeners(TestProbe $probe): array
    {
        $listeners = [];

        $listeners[] = Event::listen(AgentStarted::class, fn ($e) => $probe->recordStage('agent_started', 'started'));
        $listeners[] = Event::listen(LlmCallStarted::class, function ($e) use ($probe) {
            $probe->llmCalls++;
            $probe->recordStage('llm_call', 'started', "Iteration {$e->getIteration()}");
        });
        $listeners[] = Event::listen(LlmCallFinished::class, function ($e) use ($probe) {
            $probe->recordStage('llm_call', 'finished', "Iteration {$e->getIteration()}, {$e->getDurationMs()}ms");
        });
        $listeners[] = Event::listen(ToolCallStarted::class, function ($e) use ($probe) {
            $probe->recordStage('tool_call', 'started', $e->getToolName());
        });
        $listeners[] = Event::listen(ToolCallFinished::class, function ($e) use ($probe) {
            $probe->recordToolCall($e->getToolName(), $e->getArguments(), (string) $e->getResult());
            $probe->recordStage('tool_call', 'finished', $e->getToolName());
        });
        $listeners[] = Event::listen(AgentFinished::class, fn ($e) => $probe->recordStage('agent_finished', 'finished'));

        return $listeners;
    }

    /**
     * Detach event listeners to prevent accumulation across test requests.
     */
    private function detachEventListeners(array $listeners): void
    {
        foreach ($listeners as $listener) {
            try {
                Event::forget($listener);
            } catch (\Throwable $e) {
                // Ignore cleanup errors
            }
        }
    }

    /**
     * Extract tool calls from an AgentResponse object.
     */
    private function extractToolCallsFromResponse(object $response, TestProbe $probe): void
    {
        if (property_exists($response, 'toolCalls') && $response->toolCalls instanceof Collection) {
            foreach ($response->toolCalls as $toolCall) {
                $name = $toolCall->name ?? 'unknown';
                $args = [];
                if (property_exists($toolCall, 'arguments')) {
                    $args = is_array($toolCall->arguments) ? $toolCall->arguments : [];
                }
                $probe->recordToolCall($name, $args, '');
            }
        }
        if (property_exists($response, 'steps') && $response->steps instanceof Collection) {
            $probe->iterations = $response->steps->count();
        }
    }

    /**
     * Strip Qwen thinking tags from content.
     */
    private function stripThinking(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        // Strip
        if (preg_match('/(.*?)<\/think>/s', $content, $matches)) {
            $after = trim(str_replace($matches[0], '', $content));

            return $after !== '' ? $after : $content;
        }

        // Strip unclosed  tag
        if (preg_match('/^(.*?)$/s', $content, $matches)) {
            $after = trim(str_replace($matches[0], '', $content));

            return $after !== '' ? $after : $content;
        }

        // Strip <thought> tags
        if (preg_match('/<thought>(.*?)<\/thought>/s', $content, $matches)) {
            $after = trim(str_replace($matches[0], '', $content));

            return $after !== '' ? $after : trim($matches[1]);
        }

        return $content;
    }

    /**
     * Get the API base URL for a provider.
     */
    private function getProviderBaseUrl(string $provider): string
    {
        // Always read from config (set by AiConfigHelper from database settings)
        $configUrl = config("ai.providers.{$provider}.url");
        if ($configUrl) {
            return rtrim($configUrl, '/');
        }

        return match ($provider) {
            'qwen' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
            'lmstudio' => 'http://127.0.0.1:1234/v1',
            'openrouter' => 'https://openrouter.ai/api/v1',
            'ollama' => 'http://127.0.0.1:11434/v1',
            'gemini' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            default => config("ai.providers.{$provider}.base_url", 'http://127.0.0.1:1234/v1'),
        };
    }

    /**
     * Get the API key for a provider.
     */
    private function getProviderApiKey(string $provider): string
    {
        // AiConfigHelper stores keys at 'ai.providers.{provider}.key' (not 'api_key')
        $key = match ($provider) {
            'qwen' => config('ai.providers.qwen.key', ''),
            'lmstudio' => 'lm-studio',
            'ollama' => 'ollama',
            'openrouter' => config('ai.providers.openrouter.key', ''),
            'gemini' => config('ai.providers.gemini.key', ''),
            default => config("ai.providers.{$provider}.key", ''),
        };

        // Fallback to GlobalSetting if config is empty
        if (empty($key) && class_exists(GlobalSetting::class)) {
            $key = GlobalSetting::getValue("{$provider}_api_key", '');
        }

        return $key;
    }

    /**
     * Load existing traces from disk for a mode (used when re-running only specific modes).
     */
    private function loadExistingTraces(string $mode): array
    {
        $dir = $this->outputDir.'/traces/'.$mode;
        if (! is_dir($dir)) {
            return [];
        }

        $traces = [];
        $files = glob($dir.'/request-*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data)) {
                $traces[] = $data;
            }
        }
        usort($traces, fn ($a, $b) => ($a['request_index'] ?? 0) <=> ($b['request_index'] ?? 0));

        return $traces;
    }

    /**
     * Get the base directory (parent of runs/{run_id}).
     */
    private function getBaseDir(): string
    {
        return dirname(dirname($this->outputDir));
    }

    /**
     * Update the 'latest' symlink to point to this run.
     */
    private function updateLatestSymlink(): void
    {
        $baseDir = $this->getBaseDir();
        $latest = $baseDir.'/latest';

        // Remove existing symlink/file
        if (is_link($latest) || file_exists($latest)) {
            @unlink($latest);
        }

        // Create symlink: testandcompare/latest -> runs/{run_id}
        @symlink($this->outputDir, $latest);

        // Fallback: if symlink fails, copy summary file
        if (! is_link($latest)) {
            if (! is_dir($latest)) {
                @mkdir($latest, 0775, true);
            }
            $summaryFile = $this->outputDir.'/comparison-summary.json';
            if (file_exists($summaryFile)) {
                @copy($summaryFile, $latest.'/comparison-summary.json');
            }
        }
    }

    /**
     * Save traces for a specific mode.
     */
    private function saveTraces(string $mode, array $traces): void
    {
        $dir = $this->outputDir.'/traces/'.$mode;
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        foreach ($traces as $trace) {
            $trace['run_id'] = $this->runId;

            // Enrich trace using predictable request session ID
            $sessionId = "testcmp_{$this->runId}_{$mode}_{$trace['request_index']}";
            $trace = $this->enrichTraceFromSqlite($trace, $sessionId);

            $filename = sprintf('%s/request-%02d-%s.json', $dir, $trace['request_index'] + 1, strtolower($trace['agent']));
            file_put_contents($filename, json_encode($trace, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // Update latest symlink after each mode is saved (so progress is visible)
        $this->updateLatestSymlink();
    }

    /**
     * Enrich a trace using the session's SQLite monitor database and prompt structure.
     */
    private function enrichTraceFromSqlite(array $trace, string $sessionId): array
    {
        $sessionDbPath = storage_path("app/phpkaiharness/sessions/{$sessionId}/monitor.db");
        if (file_exists($sessionDbPath)) {
            try {
                $pdo = new \PDO('sqlite:'.$sessionDbPath);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

                // 1. Query the main session info
                $stmt = $pdo->prepare('SELECT * FROM harness_sessions ORDER BY created_at DESC LIMIT 1');
                $stmt->execute();
                $session = $stmt->fetch();

                if ($session) {
                    $trace['iterations'] = $session['iterations'] ?? $trace['iterations'];
                }

                // 2. Query details/events
                $stmt = $pdo->prepare('SELECT * FROM harness_details ORDER BY id ASC');
                $stmt->execute();
                $details = $stmt->fetchAll();

                $cacheHit = false;
                $toolCalls = [];
                $pipelineStages = [];
                $llmCalls = 0;
                $totalPromptTokens = 0;
                $totalCompletionTokens = 0;

                foreach ($details as $detail) {
                    $type = $detail['type'];
                    $name = $detail['name'];
                    $payload = json_decode($detail['payload'] ?? '{}', true) ?: [];
                    $response = json_decode($detail['response'] ?? '{}', true) ?: [];
                    if (empty($response) && !empty($detail['response'])) {
                        $response = ['message' => $detail['response']];
                    }

                    // Track pipeline stages
                    $pipelineStages[] = [
                        'stage' => $type,
                        'status' => $name,
                        'detail' => $detail['response'] ?? null,
                    ];

                    if ($type === 'llm_call') {
                        $llmCalls++;
                        $totalPromptTokens += (int) ($detail['tokens_prompt'] ?? 0);
                        $totalCompletionTokens += (int) ($detail['tokens_completion'] ?? 0);
                    }

                    if ($type === 'tool_call') {
                        $toolCalls[] = [
                            'name' => $name,
                            'arguments' => $payload,
                            'result_length' => strlen($detail['response'] ?? ''),
                            'result_preview' => mb_substr($detail['response'] ?? '', 0, 500),
                        ];
                    }

                    if ($type === 'cache') {
                        if ($name === 'hit') {
                            $cacheHit = true;
                        }
                    }
                }

                if ($cacheHit) {
                    $trace['cache']['hit'] = true;
                }

                if (! empty($toolCalls)) {
                    $trace['tool_calls']['count'] = count($toolCalls);
                    $trace['tool_calls']['calls'] = $toolCalls;
                }

                if (! empty($pipelineStages)) {
                    $trace['pipeline_stages'] = $pipelineStages;
                }

                if ($llmCalls > 0) {
                    $trace['llm_calls'] = $llmCalls;
                }
                if ($totalPromptTokens > 0) {
                    $trace['tokens']['prompt_tokens'] = $totalPromptTokens;
                    $trace['tokens']['completion_tokens'] = $totalCompletionTokens;
                    $trace['tokens']['total_tokens'] = $totalPromptTokens + $totalCompletionTokens;
                }

                $pdo = null;
            } catch (\Throwable $e) {
                // Ignore DB query errors
            }
        }

        // Always apply the reliable prompt/system prompt parsing for quantum memory & ontology RAG
        $promptToCheck = ($trace['prompts']['effective_user_prompt'] ?? '') . "\n" . ($trace['prompts']['optimized_system_prompt'] ?? '');
        
        // 1. Quantum memory
        if (str_contains($promptToCheck, '[QUANTUM-HARNESS MEMORY ENVELOPE]:')) {
            $parts = explode('[QUANTUM-HARNESS MEMORY ENVELOPE]:', $promptToCheck);
            $envelope = end($parts);
            $nodes = [];
            if (preg_match_all('/-\s+\[([^\]]+)\]\s+\[Type:\s*([^\]]+)\]/i', $envelope, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $nodes[] = [
                        'id' => $match[1],
                        'type' => $match[2],
                    ];
                }
            }
            if (! empty($nodes)) {
                $trace['quantum_memory']['nodes_retrieved'] = count($nodes);
                $trace['quantum_memory']['nodes'] = array_slice($nodes, 0, 5);
            }
        }

        // 2. Ontology RAG
        if (str_contains($promptToCheck, '## ONTOLOGICAL CONTEXT SECTION')) {
            $parts = explode('## ONTOLOGICAL CONTEXT SECTION', $promptToCheck);
            $section = end($parts);
            $records = [];
            if (preg_match_all('/-\s+\[Record ID:\s*([^\]]+)\]/i', $section, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $records[] = [
                        'source' => 'ontology',
                        'record_count' => 1,
                        'preview' => $match[0],
                    ];
                }
            }
            if (! empty($records)) {
                $trace['context_injected'] = $records;
            }
        }

        // Update the feature matrix enablement statuses based on the run data
        if (str_contains($trace['test_mode'], 'B-')) {
            $trace['features_eval']['draft_verification']['enabled'] = true;
            $trace['features_eval']['draft_verification']['draft_generated'] = ! empty($trace['draft_verification']['draft']);
            $trace['features_eval']['draft_verification']['evidence_verified'] = ! empty($trace['draft_verification']['evidence']);

            $trace['features_eval']['ontology_rag']['enabled'] = true;
            $trace['features_eval']['ontology_rag']['injected'] = count($trace['context_injected'] ?? []) > 0;
            $trace['features_eval']['ontology_rag']['record_count'] = array_sum(array_column($trace['context_injected'] ?? [], 'record_count'));

            $trace['features_eval']['semantic_cache']['enabled'] = true;
            $trace['features_eval']['semantic_cache']['hit'] = $trace['cache']['hit'] ?? false;

            $trace['features_eval']['quantum_memory']['enabled'] = true;
            $trace['features_eval']['quantum_memory']['nodes_retrieved'] = $trace['quantum_memory']['nodes_retrieved'] ?? 0;

            $trace['features_eval']['context_compression']['enabled'] = true;
            $trace['features_eval']['context_compression']['prompt_tokens'] = $trace['tokens']['prompt_tokens'] ?? 0;

            $trace['features_eval']['compaction']['enabled'] = true;
            $trace['features_eval']['compaction']['iterations'] = $trace['iterations'] ?? 1;

            $trace['features_eval']['cognitive_graph_memory']['enabled'] = true;
            $trace['features_eval']['cognitive_graph_memory']['tools_used'] = count(array_filter($trace['tool_calls']['calls'] ?? [], fn ($t) => ($t['name'] ?? '') === 'query_graph_memory'));
        }

        return $trace;
    }

    /**
     * Compute aggregate summary across all modes.
     */
    private function computeSummary(array $allTraces): array
    {
        $summary = [];

        foreach ($allTraces as $mode => $traces) {
            $latencies = array_map(fn ($t) => $t['timing']['latency_ms'], $traces);
            $totalTokens = array_map(fn ($t) => $t['tokens']['total_tokens'], $traces);
            $toolCallCounts = array_map(fn ($t) => $t['tool_calls']['count'], $traces);
            $responseLengths = array_map(fn ($t) => $t['response_length'], $traces);
            $successCount = count(array_filter($traces, fn ($t) => $t['success']));
            $count = max(count($traces), 1);

            // Collect AI eval scores
            $aiScores = array_filter(array_map(fn ($t) => $t['ai_evaluation']['score'] ?? null, $traces), fn ($s) => $s !== null);
            $winCount = count(array_filter($traces, fn ($t) => ($t['ai_evaluation']['is_winner'] ?? false) === true));

            $summary[$mode] = [
                'total_requests' => count($traces),
                'successful' => $successCount,
                'failed' => count($traces) - $successCount,
                'avg_latency_ms' => (int) round(array_sum($latencies) / $count),
                'min_latency_ms' => empty($latencies) ? 0 : min($latencies),
                'max_latency_ms' => empty($latencies) ? 0 : max($latencies),
                'avg_total_tokens' => (int) round(array_sum($totalTokens) / $count),
                'avg_tool_calls' => round(array_sum($toolCallCounts) / $count, 2),
                'avg_response_length' => (int) round(array_sum($responseLengths) / $count),
                'pipeline_stages_avg' => (int) round(array_sum(array_map(fn ($t) => count($t['pipeline_stages']), $traces)) / $count),
                'avg_ai_score' => ! empty($aiScores) ? round(array_sum($aiScores) / count($aiScores), 1) : null,
                'ai_win_count' => $winCount,
            ];
        }

        return $summary;
    }

    /**
     * Run AI evaluation for every request across all modes.
     * Determines the winning mode per request and writes ai_evaluation + winner back to trace files.
     */
    private function runAiEvaluations(array $allTraces): array
    {
        try {
            $evaluator = new AiEvaluator;
        } catch (\Throwable $e) {
            Log::warning('AiEvaluator init failed: '.$e->getMessage());

            return $allTraces;
        }

        $total = count(reset($allTraces) ?: []);
        $modeOrder = ['A1-direct-api', 'A2-loop-no-features', 'B-full-harness', 'B-warm-harness'];

        for ($i = 0; $i < $total; $i++) {
            $result = $evaluator->evaluateRequest($allTraces, $i);

            foreach ($modeOrder as $mode) {
                if (! isset($allTraces[$mode][$i])) {
                    continue;
                }

                $eval = $result['evaluations'][$mode] ?? null;
                if ($eval === null) {
                    continue;
                }

                $allTraces[$mode][$i]['ai_evaluation'] = array_merge($eval, [
                    'is_winner' => ($result['winner'] === $mode),
                    'winner_mode' => $result['winner'],
                    'winner_score' => $result['winner_score'],
                ]);

                // Re-save the updated trace file
                $dir = $this->outputDir.'/traces/'.$mode;
                $filename = sprintf('%s/request-%02d-%s.json', $dir, $i + 1, strtolower($allTraces[$mode][$i]['agent'] ?? 'unknown'));
                if (file_exists($filename)) {
                    file_put_contents($filename, json_encode($allTraces[$mode][$i], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }
        }

        return $allTraces;
    }

    /**
     * Save summary to JSON file.
     */
    private function saveSummary(array $summary): void
    {
        if (! is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0775, true);
        }
        file_put_contents($this->outputDir.'/comparison-summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Update latest symlink to point to this run
        $this->updateLatestSymlink();
    }
}
