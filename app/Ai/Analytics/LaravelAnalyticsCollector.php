<?php

namespace App\Ai\Analytics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Phpkaiharness\Contracts\AnalyticsCollectorInterface;
use Phpkaiharness\Monitor\SqliteMonitorStore;
use Phpkaiharness\Session\SessionManager;

class LaravelAnalyticsCollector implements AnalyticsCollectorInterface
{
    private ?SqliteMonitorStore $store = null;

    public function __construct(?string $dbPath = null)
    {
        try {
            $resolvedPath = $dbPath;
            if (! $resolvedPath && function_exists('app') && app()->bound(SessionManager::class)) {
                $sessionId = null;
                foreach (['harness.parent_session_id', 'harness.active_session_id', 'harness.root_session_id'] as $binding) {
                    if (app()->bound($binding) && app($binding)) {
                        $sessionId = (string) app($binding);
                        break;
                    }
                }

                if ($sessionId) {
                    $resolvedPath = app(SessionManager::class)->resolveMonitorDbPath($sessionId);
                }
            }

            $resolvedPath ??= config('harness.cache.db_path');
            if ($resolvedPath) {
                $dir = dirname($resolvedPath);
                if (! file_exists($dir)) {
                    @mkdir($dir, 0777, true);
                }
                $this->store = new SqliteMonitorStore($resolvedPath);
            }
        } catch (\Throwable $e) {
            Log::warning('LaravelAnalyticsCollector: Failed to initialize SqliteMonitorStore: '.$e->getMessage());
        }
    }

    private function runOnSqlite(callable $callback): void
    {
        if ($this->store) {
            try {
                $callback($this->store);
            } catch (\Throwable $e) {
                Log::warning('LaravelAnalyticsCollector: SQLite tracking failed: '.$e->getMessage());
            }
        }
    }

    /**
     * Start tracking a new agent session.
     */
    public function startSession(
        string $sessionId,
        string $prompt,
        string $method,
        ?string $parentSessionId = null,
        int $interactionIndex = 0,
        ?string $rootSessionId = null,
        ?string $requestId = null,
        string $sessionType = 'interaction'
    ): void {
        // 1. Write to SQLite store
        $this->runOnSqlite(fn ($store) => $store->startSession(
            $sessionId,
            $prompt,
            $method,
            $parentSessionId,
            $interactionIndex,
            $rootSessionId,
            $requestId,
            $sessionType
        ));

        // 2. Write to MySQL database harness_sessions table for the web dashboard
        try {
            DB::table('harness_sessions')->updateOrInsert(
                ['id' => $sessionId],
                [
                    'prompt' => $prompt,
                    'method' => $method ?: 'agent-loop',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('LaravelAnalyticsCollector MySQL startSession failed: '.$e->getMessage());
        }
    }

    /**
     * Finalize tracking of an agent session with outputs.
     */
    public function endSession(string $sessionId, string $response, int $totalDurationMs, int $iterations): void
    {
        // 1. Write to SQLite store
        $this->runOnSqlite(fn ($store) => $store->endSession($sessionId, $response, $totalDurationMs, $iterations));

        // 2. Update MySQL database harness_sessions table
        try {
            DB::table('harness_sessions')->where('id', $sessionId)->update([
                'response' => $response,
                'total_duration_ms' => $totalDurationMs,
                'iterations' => $iterations,
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('LaravelAnalyticsCollector MySQL endSession failed: '.$e->getMessage());
        }
    }

    /**
     * Record a specific LLM chat/tool-call invocation.
     */
    public function recordLlmCall(
        string $sessionId,
        string $model,
        array $payload,
        array $response,
        int $durationMs,
        array $usage
    ): void {
        // 1. Write to SQLite store
        $this->runOnSqlite(fn ($store) => $store->recordLlmCall($sessionId, $model, $payload, $response, $durationMs, $usage));

        // 2. Insert into MySQL database harness_details table
        try {
            DB::table('harness_details')->insert([
                'session_id' => $sessionId,
                'type' => 'llm_call',
                'name' => $model ?: 'llm-agent',
                'payload' => json_encode($payload),
                'response' => json_encode($response),
                'duration_ms' => $durationMs,
                'tokens_prompt' => $usage['prompt_tokens'] ?? $usage['tokens_prompt'] ?? 0,
                'tokens_completion' => $usage['completion_tokens'] ?? $usage['tokens_completion'] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('LaravelAnalyticsCollector MySQL recordLlmCall failed: '.$e->getMessage());
        }
    }

    /**
     * Record a specific tool call execution inside the harness loop.
     */
    public function recordToolCall(
        string $sessionId,
        string $toolName,
        array $arguments,
        string $result,
        int $durationMs
    ): void {
        // 1. Write to SQLite store
        $this->runOnSqlite(fn ($store) => $store->recordToolCall($sessionId, $toolName, $arguments, $result, $durationMs));

        // 2. Insert into MySQL database harness_details table
        try {
            DB::table('harness_details')->insert([
                'session_id' => $sessionId,
                'type' => 'tool_call',
                'name' => $toolName ?: 'tool',
                'payload' => json_encode($arguments),
                'response' => $result,
                'duration_ms' => $durationMs,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('LaravelAnalyticsCollector MySQL recordToolCall failed: '.$e->getMessage());
        }
    }

    public function recordEvent(
        string $sessionId,
        string $type,
        string $name,
        array $payload,
        string $response,
        int $durationMs = 0
    ): void {
        $this->runOnSqlite(fn ($store) => $store->recordEvent($sessionId, $type, $name, $payload, $response, $durationMs));
    }

    public function recordFact(string $sessionId, string $fact): void
    {
        $this->runOnSqlite(fn ($store) => $store->recordFact($sessionId, $fact));
    }
}
