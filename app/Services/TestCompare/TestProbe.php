<?php

namespace App\Services\TestCompare;

/**
 * Instrumentation probe that captures every aspect of a single test request.
 * Acts as a data container that collects metrics during execution.
 */
class TestProbe
{
    public string $testMode;

    public int $requestIndex;

    public string $agent;

    public string $category;

    public string $description;

    public string $rawPrompt;

    public string $effectivePrompt = '';

    public string $originalSystemPrompt = '';

    public string $optimizedSystemPrompt = '';

    public string $finalResponse = '';

    public string $model = '';

    public string $provider = '';

    public float $startTime;

    public float $endTime = 0.0;

    public int $latencyMs = 0;

    public int $iterations = 0;

    public int $llmCalls = 0;

    public array $toolCalls = [];

    public array $contextInjected = [];

    public array $quantumMemoryNodes = [];

    public bool $cacheHit = false;

    public ?string $cacheKey = null;

    public int $promptTokens = 0;

    public int $completionTokens = 0;

    public int $totalTokens = 0;

    public array $pipelineStages = [];

    public array $errors = [];

    public bool $success = true;

    /** @var array{score: int, accuracy: int, completeness: int, relevance: int, quality: int, verdict: string, strengths: string, weaknesses: string, error: string|null}|null */
    public ?array $aiEvaluation = null;

    public ?string $draftContent = null;

    public ?string $evidenceContent = null;

    public ?string $runId = null;

    public function __construct(string $testMode, int $requestIndex, array $dataset)
    {
        $this->testMode = $testMode;
        $this->requestIndex = $requestIndex;
        $this->agent = $dataset['agent'];
        $this->category = $dataset['category'];
        $this->description = $dataset['description'];
        $this->rawPrompt = $dataset['prompt'];
        $this->startTime = microtime(true);
    }

    /**
     * Mark the end of the test and compute latency.
     */
    public function finish(string $response): void
    {
        $this->finalResponse = $response;
        $this->endTime = microtime(true);
        $this->latencyMs = (int) (($this->endTime - $this->startTime) * 1000);
    }

    /**
     * Record a tool call.
     */
    public function recordToolCall(string $name, array $args, string $result): void
    {
        $this->toolCalls[] = [
            'name' => $name,
            'arguments' => $args,
            'result_length' => strlen($result),
            'result_preview' => mb_substr($result, 0, 500),
        ];
    }

    /**
     * Record a pipeline stage execution.
     */
    public function recordStage(string $stage, string $status, ?string $detail = null): void
    {
        $this->pipelineStages[] = [
            'stage' => $stage,
            'status' => $status,
            'detail' => $detail,
        ];
    }

    /**
     * Record context injection.
     */
    public function recordContextInjection(string $source, int $recordCount, string $preview): void
    {
        $this->contextInjected[] = [
            'source' => $source,
            'record_count' => $recordCount,
            'preview' => mb_substr($preview, 0, 300),
        ];
    }

    /**
     * Record an error.
     */
    public function recordError(string $error): void
    {
        $this->errors[] = $error;
        $this->success = false;
    }

    /**
     * Estimate token count (rough: 1 token ≈ 4 chars).
     */
    public static function estimateTokens(string $text): int
    {
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * Compute token estimates after execution.
     */
    public function computeTokens(): void
    {
        $promptText = $this->optimizedSystemPrompt."\n".$this->effectivePrompt;
        $this->promptTokens = self::estimateTokens($promptText);
        $this->completionTokens = self::estimateTokens($this->finalResponse);
        $this->totalTokens = $this->promptTokens + $this->completionTokens;
    }

    /**
     * Serialize to array for JSON output.
     */
    public function toArray(): array
    {
        $this->computeTokens();

        return [
            'test_mode' => $this->testMode,
            'request_index' => $this->requestIndex,
            'run_id' => $this->runId,
            'agent' => $this->agent,
            'category' => $this->category,
            'description' => $this->description,
            'timing' => [
                'start_time' => $this->startTime,
                'end_time' => $this->endTime,
                'latency_ms' => $this->latencyMs,
            ],
            'model' => [
                'name' => $this->model,
                'provider' => $this->provider,
            ],
            'tokens' => [
                'prompt_tokens' => $this->promptTokens,
                'completion_tokens' => $this->completionTokens,
                'total_tokens' => $this->totalTokens,
            ],
            'prompts' => [
                'raw_user_prompt' => $this->rawPrompt,
                'effective_user_prompt' => mb_substr($this->effectivePrompt, 0, 2000),
                'original_system_prompt' => mb_substr($this->originalSystemPrompt, 0, 1000),
                'optimized_system_prompt' => mb_substr($this->optimizedSystemPrompt, 0, 1000),
            ],
            'pipeline_stages' => $this->pipelineStages,
            'context_injected' => $this->contextInjected,
            'quantum_memory' => [
                'nodes_retrieved' => count($this->quantumMemoryNodes),
                'nodes' => array_slice($this->quantumMemoryNodes, 0, 5),
            ],
            'tool_calls' => [
                'count' => count($this->toolCalls),
                'calls' => $this->toolCalls,
            ],
            'cache' => [
                'hit' => $this->cacheHit,
                'key' => $this->cacheKey,
            ],
            'features_eval' => [
                'draft_verification' => [
                    'enabled' => str_contains($this->testMode, 'B-'),
                    'draft_generated' => ! empty($this->draftContent),
                    'evidence_verified' => ! empty($this->evidenceContent),
                ],
                'ontology_rag' => [
                    'enabled' => str_contains($this->testMode, 'B-'),
                    'injected' => count($this->contextInjected) > 0,
                    'record_count' => array_sum(array_column($this->contextInjected, 'record_count')),
                ],
                'semantic_cache' => [
                    'enabled' => str_contains($this->testMode, 'B-'),
                    'hit' => $this->cacheHit,
                ],
                'quantum_memory' => [
                    'enabled' => str_contains($this->testMode, 'B-'),
                    'nodes_retrieved' => count($this->quantumMemoryNodes),
                ],
                'context_compression' => [
                    'enabled' => str_contains($this->testMode, 'B-'),
                    'prompt_tokens' => $this->promptTokens,
                ],
                'compaction' => [
                    'enabled' => str_contains($this->testMode, 'B-'),
                    'iterations' => $this->iterations,
                ],
                'cognitive_graph_memory' => [
                    'enabled' => str_contains($this->testMode, 'B-'),
                    'tools_used' => count(array_filter($this->toolCalls, fn ($t) => ($t['name'] ?? '') === 'query_graph_memory')),
                ],
            ],
            'iterations' => $this->iterations,
            'llm_calls' => $this->llmCalls,
            'draft_verification' => [
                'draft' => $this->draftContent ? mb_substr($this->draftContent, 0, 500) : null,
                'evidence' => $this->evidenceContent ? mb_substr($this->evidenceContent, 0, 500) : null,
            ],
            'response' => $this->finalResponse,
            'response_length' => strlen($this->finalResponse),
            'success' => $this->success,
            'errors' => $this->errors,
            'ai_evaluation' => $this->aiEvaluation,
        ];
    }
}
