<?php

namespace App\Ai\Analytics;

use Illuminate\Support\Facades\Log;
use Phpkaiharness\Contracts\AnalyticsCollectorInterface;
use Phpkaiharness\Monitor\SqliteMonitorStore;

class LaravelAnalyticsCollector implements AnalyticsCollectorInterface
{
    private ?SqliteMonitorStore $store = null;

    public function __construct(?string $dbPath = null)
    {
        try {
            $resolvedPath = $dbPath ?? config('harness.cache.db_path');
            if ($resolvedPath) {
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

    private function isValidJson(string $string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
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
    }

    /**
     * Finalize tracking of an agent session with outputs.
     */
    public function endSession(string $sessionId, string $response, int $totalDurationMs, int $iterations): void
    {
        $this->runOnSqlite(fn ($store) => $store->endSession($sessionId, $response, $totalDurationMs, $iterations));
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
        $this->runOnSqlite(fn ($store) => $store->recordLlmCall($sessionId, $model, $payload, $response, $durationMs, $usage));
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
        $this->runOnSqlite(fn ($store) => $store->recordToolCall($sessionId, $toolName, $arguments, $result, $durationMs));
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
        // Write to SQLite
        $this->runOnSqlite(fn ($store) => $store->recordFact($sessionId, $fact));
    }
}
