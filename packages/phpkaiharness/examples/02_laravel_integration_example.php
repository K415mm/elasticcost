<?php

/**
 * PHP Kai Harness - Laravel Integration Example
 *
 * Demonstrates how phpkaiharness integrates seamlessly into Laravel applications.
 *
 * 1. Automatic Service Provider Registration (Composer auto-discovery)
 *    PhpkaiharnessServiceProvider is automatically loaded into Laravel.
 *
 * 2. Configuration Publishing
 *    Run: `php artisan vendor:publish --tag=phpkaiharness-config`
 *
 * 3. Dependency Injection in Controllers / Jobs
 */

namespace App\Http\Controllers;

use Phpkaiharness\Core\AgentHarness;
use Phpkaiharness\Llm\LaravelAiClient;
use Phpkaiharness\Session\SessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAgentController
{
    /**
     * Handle incoming agent request within a Laravel controller.
     */
    public function handleAgentQuery(Request $request, SessionManager $sessionManager): JsonResponse
    {
        $userQuery = $request->input('prompt', 'Analyze current system health');
        $sessionId = session()->getId();

        // 1. Activate session isolation for this request
        $sessionManager->activateSession($sessionId);

        // 2. Initialize harness with configured LLM client
        $client = new LaravelAiClient(
            provider: config('harness.default.provider', 'ollama'),
            model: config('harness.default.model', 'llama3.2')
        );

        $harness = new AgentHarness(
            client: $client,
            maxIterations: (int) config('harness.default.max_iterations', 10)
        );

        // 3. Run agent with system instructions and user input
        $systemInstructions = "You are an AI system assistant running inside a Laravel application.";
        $result = $harness->run(
            systemPrompt: $systemInstructions,
            userPrompt: $userQuery,
            sessionId: $sessionId
        );

        return response()->json([
            'status' => 'success',
            'session_id' => $sessionId,
            'response' => $result['response'] ?? '',
            'iterations' => $result['iterations'] ?? 1,
            'dashboard_url' => route('harness.dashboard'),
        ]);
    }
}
