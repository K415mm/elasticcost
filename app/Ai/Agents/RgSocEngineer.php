<?php

namespace App\Ai\Agents;

use App\Ai\Adapters\LaravelToolAdapter;
use App\Ai\Analytics\LaravelAnalyticsCollector;
use App\Ai\Middleware\TelemetryMiddleware;
use App\Ai\Routing\LocalIntentRouter;
use App\Services\AiConfigHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Phpkaiharness\Core\AgentLoop;
use Phpkaiharness\Core\Registry\ToolRegistry;
use Phpkaiharness\Http\Middleware\PolicyGuardrailMiddleware;
use Phpkaiharness\Llm\LaravelAiClient;
use Psr\Log\AbstractLogger;
use Stringable;

class RgSocEngineer implements Agent, HasMiddleware, HasTools
{
    use Promptable;

    public ?string $phpSessionId = null;

    /**
     * Get the agent's prompt middleware.
     */
    public function middleware(): array
    {
        return [
            new TelemetryMiddleware,
            new PolicyGuardrailMiddleware,
        ];
    }

    /**
     * Override the prompt method to implement a structured classification and programmatic routing.
     */
    public function prompt(
        string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null
    ): AgentResponse {
        if (static::isFaked()) {
            return $this->withModelFailover(
                fn ($resolvedProvider, $resolvedModel) => $resolvedProvider->prompt(
                    new AgentPrompt($this, $prompt, $attachments, $resolvedProvider, $resolvedModel, $this->getTimeout($timeout))
                ),
                $provider,
                $model,
            );
        }

        $config = AiConfigHelper::configureMultiModel();
        $lightProvider = $provider ?? $config['light']['provider'];
        $lightModel = $model ?? $config['light']['model'];

        $sessionId = $this->phpSessionId ?: (
            function_exists('app') && app()->bound('harness.active_session_id')
                ? app('harness.active_session_id')
                : (string) Str::uuid7()
        );
        Log::info('RgSocEngineer::prompt() called', ['session_id' => $sessionId, 'isFaked' => static::isFaked(), 'prompt_preview' => mb_substr($prompt, 0, 100, 'UTF-8')]);
        try {
            $analytics = new LaravelAnalyticsCollector;
            Log::info('RgSocEngineer: LaravelAnalyticsCollector created', ['session_id' => $sessionId]);
        } catch (\Throwable $e) {
            Log::error('RgSocEngineer: Failed to create LaravelAnalyticsCollector', ['session_id' => $sessionId, 'error' => $e->getMessage()]);
            $analytics = null;
        }

        // Extract the latest clean user query from the compiled sliding-window history prompt
        $cleanUserQuery = $prompt;
        $lastUserPos = mb_strrpos($prompt, '### User:', 0, 'UTF-8');
        if ($lastUserPos !== false) {
            $afterUser = mb_substr($prompt, $lastUserPos + 9, null, 'UTF-8');
            $lastAgentPos = mb_strrpos($afterUser, '### RG SOC Engineer:', 0, 'UTF-8');
            if ($lastAgentPos === false) {
                $lastAgentPos = mb_strrpos($afterUser, '### ElasticCost Assistant:', 0, 'UTF-8');
            }
            if ($lastAgentPos !== false) {
                $cleanUserQuery = trim(mb_substr($afterUser, 0, $lastAgentPos, 'UTF-8'));
            } else {
                $cleanUserQuery = trim($afterUser);
            }
        }

        $localRoutingEnabled = (bool) config('harness.routing.local_intent.enabled', true);
        $localRoutingThreshold = (float) config('harness.routing.local_intent.confidence_threshold', 0.9);
        $routingDecision = (new LocalIntentRouter)->decide(
            prompt: $cleanUserQuery,
            enabled: $localRoutingEnabled,
            threshold: $localRoutingThreshold,
        );
        $forceAction = $routingDecision->isLocalAction();

        if ($forceAction) {
            Log::info('RgSocEngineer: local intent route selected', [
                'session_id' => $sessionId,
                'confidence' => $routingDecision->confidence,
                'signals' => $routingDecision->signals,
            ]);
        }

        $requiresAction = false;
        $actionInstruction = $cleanUserQuery;
        $chatResponse = '';
        $routerResponse = null;
        $routerDurationMs = 0;

        if ($forceAction) {
            $requiresAction = true;
            if ($analytics) {
                try {
                    $analytics->startSession($sessionId, $cleanUserQuery, 'local-intent-action');
                    Log::info('RgSocEngineer: startSession OK (local intent)', [
                        'session_id' => $sessionId,
                        'confidence' => $routingDecision->confidence,
                        'signals' => $routingDecision->signals,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('RgSocEngineer: startSession failed', ['session_id' => $sessionId, 'error' => $e->getMessage()]);
                }
            }
        } else {
            $router = new SocEngineerRouter;
            $routerStartTime = microtime(true);
            $routerResponse = $router->prompt($prompt, $attachments, $lightProvider, $lightModel, $timeout);
            $routerDurationMs = (int) ((microtime(true) - $routerStartTime) * 1000);

            $requiresAction = $routerResponse['requires_action'] ?? false;
            $actionInstruction = $requiresAction ? ($routerResponse['action_instruction'] ?? $cleanUserQuery) : $cleanUserQuery;
            $chatResponse = $routerResponse['chat_response'] ?? '';

            $method = $requiresAction ? 'router-classified-action' : 'router-classified-chat';
            if ($analytics) {
                try {
                    $analytics->startSession($sessionId, $cleanUserQuery, $method);
                    Log::info('RgSocEngineer: startSession OK (router)', ['session_id' => $sessionId, 'method' => $method]);
                } catch (\Throwable $e) {
                    Log::error('RgSocEngineer: startSession failed (router)', ['session_id' => $sessionId, 'error' => $e->getMessage()]);
                }
            }

            // Record the intent classification call
            if ($analytics) {
                try {
                    $analytics->recordLlmCall(
                        $sessionId,
                        $lightModel instanceof Lab ? $lightModel->value : (string) $lightModel,
                        ['prompt' => $cleanUserQuery],
                        [
                            'requires_action' => $requiresAction,
                            'action_instruction' => $actionInstruction,
                            'chat_response' => $chatResponse,
                        ],
                        $routerDurationMs,
                        [
                            'prompt_tokens' => $routerResponse->usage->prompt ?? 0,
                            'completion_tokens' => $routerResponse->usage->completion ?? 0,
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error('RgSocEngineer: recordLlmCall failed (router)', ['session_id' => $sessionId, 'error' => $e->getMessage()]);
                }
            }
        }

        if ($requiresAction) {
            $mainAgent = new RgSocEngineerMain;
            $mainProvider = $config['main']['provider'];
            $mainModel = $config['main']['model'];

            // Support laravel/ai's testing fake environment
            if ($mainAgent::isFaked()) {
                return $mainAgent->prompt($actionInstruction ?: $prompt, $attachments, $mainProvider, $mainModel, $timeout);
            }

            $providerName = $mainProvider instanceof Lab ? $mainProvider->value : (string) $mainProvider;

            $effectivePrompt = $actionInstruction
                ? "TASK: {$actionInstruction}\n\nCONVERSATION CONTEXT:\n{$prompt}"
                : $prompt;

            $loopStartTime = microtime(true);

            $llmClient = new LaravelAiClient($providerName, $mainModel);

            $registry = new ToolRegistry;
            foreach ($mainAgent->tools() as $laravelTool) {
                $registry->attach(new LaravelToolAdapter($laravelTool));
            }

            $logger = new class extends AbstractLogger
            {
                public function log($level, string|Stringable $message, array $context = []): void
                {
                    Log::log($level, (string) $message, $context);
                }
            };

            $agentLoop = new AgentLoop(
                llmClient: $llmClient,
                registry: $registry,
                systemPrompt: (string) $mainAgent->instructions(),
                model: $mainModel,
                maxIterations: (int) config('harness.default.max_iterations', 10),
                logger: $logger
            );

            $agentLoop->setAgentName('RgSocEngineerMain');

            try {
                $history = [];
                $responseText = $agentLoop->run($effectivePrompt, $history, $sessionId, $analytics);
            } catch (\Throwable $e) {
                $responseText = '⚠️ Agent execution error: '.$e->getMessage();
                Log::error('RgSocEngineer: AgentLoop execution failed', ['session_id' => $sessionId, 'error' => $e->getMessage()]);
            }

            $executedToolCalls = $agentLoop->getExecutedToolCalls();

            if ($analytics) {
                try {
                    $durationMs = (int) ((microtime(true) - $loopStartTime) * 1000);
                    $analytics->endSession($sessionId, $responseText, $durationMs, 1);
                    Log::info('RgSocEngineer: endSession OK (sync)', ['session_id' => $sessionId]);
                } catch (\Throwable $e) {
                    Log::error('RgSocEngineer: endSession failed (sync)', ['session_id' => $sessionId, 'error' => $e->getMessage()]);
                }
            }

            $response = new AgentResponse(
                (string) Str::uuid7(),
                $responseText,
                new Usage,
                new Meta($mainProvider instanceof Lab ? $mainProvider->value : $mainProvider, $mainModel)
            );
            foreach ($executedToolCalls as $tc) {
                $response->toolCalls->push((object) $tc);
            }

            return $response;
        }

        // Finalize simple chat session
        if ($analytics) {
            try {
                $analytics->endSession($sessionId, $chatResponse ?: ($routerResponse ? $routerResponse->text : ''), $routerDurationMs, 1);
            } catch (\Throwable $e) {
                Log::error('RgSocEngineer: endSession failed (chat)', ['session_id' => $sessionId, 'error' => $e->getMessage()]);
            }
        }

        return new AgentResponse(
            (string) Str::uuid7(),
            $chatResponse ?: ($routerResponse ? $routerResponse->text : ''),
            new Usage,
            new Meta(
                $lightProvider instanceof Lab ? $lightProvider->value : $lightProvider,
                $lightModel
            )
        );
    }

    /**
     * Get the runtime configured provider for the Light Model.
     */
    public function provider(): string|Lab
    {
        $config = AiConfigHelper::configureMultiModel();

        return $config['light']['provider'];
    }

    /**
     * Get the runtime configured model for the Light Model.
     */
    public function model(): string
    {
        $config = AiConfigHelper::configureMultiModel();

        return $config['light']['model'];
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are the "RG SOC Engineer" (Light Router), the primary classifier and router for the Security Operations Center.
Your job is to read the user request and decide how to respond:
1. If the request requires any action (such as querying database tables, inspecting current system settings, listing clients, showing settings, checking status, updating device counts, or modifying settings), delegate it immediately to the `execute_action` sub-agent tool. Pass the user's request as a clean, direct task instruction to the tool.
2. If the request is a simple greeting (e.g. "hello", "hi"), conversational exchange, or general query that does not require database access or updates, answer it directly and concisely.
3. If you do not know what to do or if the request is ambiguous, delegate it to the `execute_action` sub-agent tool to inspect details and process it.
INSTRUCTIONS;
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        return [
            new RgSocEngineerMain,
        ];
    }

    /**
     * Get the default timeout (in seconds) for the agent.
     */
    public function timeout(): int
    {
        return 600;
    }
}
