<?php

namespace App\Ai\Middleware;

use App\Ai\Analytics\LaravelAnalyticsCollector;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Prompts\AgentPrompt;
use Phpkaiharness\Contracts\AnalyticsCollectorInterface;
use Phpkaiharness\Session\SessionManager;

class TelemetryMiddleware
{
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $phpSessionId = null;
        if (isset($prompt->agent) && isset($prompt->agent->phpSessionId) && $prompt->agent->phpSessionId) {
            $phpSessionId = $prompt->agent->phpSessionId;
        } else {
            $phpSessionId = 'phpsess_'.session()->getId();
        }

        $subSessionId = (string) Str::uuid7();
        $analytics = null;

        try {
            /** @var SessionManager $sessionManager */
            $sessionManager = app(SessionManager::class);
            $sessionManager->activateSession($phpSessionId);
            $analytics = new LaravelAnalyticsCollector($sessionManager->resolveMonitorDbPath($phpSessionId));
            Log::info('TelemetryMiddleware: LaravelAnalyticsCollector created', ['php_session_id' => $phpSessionId, 'sub_session_id' => $subSessionId]);

            // Bind session references and collector to container for AgentLoop and Agents to pick up
            if (function_exists('app')) {
                app()->instance('harness.active_session_id', $subSessionId);
                app()->instance('harness.parent_session_id', $phpSessionId);
                app()->instance('harness.root_session_id', $phpSessionId);
                if (! app()->bound(AnalyticsCollectorInterface::class)) {
                    app()->instance(AnalyticsCollectorInterface::class, $analytics);
                }
            }

            $analytics->startSession(
                sessionId: $subSessionId,
                prompt: $prompt->prompt,
                method: 'telemetry-middleware',
                parentSessionId: $phpSessionId,
                interactionIndex: 0,
                rootSessionId: $phpSessionId
            );
            Log::info('TelemetryMiddleware: startSession OK', ['sub_session_id' => $subSessionId]);
        } catch (\Throwable $e) {
            Log::error('TelemetryMiddleware: Failed to initialize', ['php_session_id' => $phpSessionId, 'error' => $e->getMessage()]);
        }

        $startTime = microtime(true);
        $response = $next($prompt);
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        if ($analytics) {
            try {
                $analytics->endSession($subSessionId, $response->text ?? '', $durationMs, 1);
                Log::info('TelemetryMiddleware: endSession OK', ['sub_session_id' => $subSessionId]);
            } catch (\Throwable $e) {
                Log::error('TelemetryMiddleware: endSession failed', ['sub_session_id' => $subSessionId, 'error' => $e->getMessage()]);
            }
        }

        return $response;
    }
}
