<?php

namespace App\Jobs;

use App\Ai\Adapters\LaravelToolAdapter;
use App\Ai\Agents\RgSocEngineerMain;
use App\Ai\Analytics\LaravelAnalyticsCollector;
use App\Ai\Routing\DiracObserverRouter;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Phpkaiharness\Core\AgentLoop;
use Phpkaiharness\Core\Registry\ToolRegistry;
use Phpkaiharness\Llm\LaravelAiClient;
use Psr\Log\AbstractLogger;

class HarnessAgentLoopIterationJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $sessionId,
        protected int $iteration
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $stateKey = "harness:session:{$this->sessionId}:state";
        $stateJson = Redis::get($stateKey);
        if (! $stateJson) {
            Log::error('HarnessAgentLoopIterationJob: State not found in Redis', ['session_id' => $this->sessionId]);

            return;
        }

        $state = json_decode($stateJson, true);
        if (($state['status'] ?? '') === 'completed') {
            return;
        }

        Log::info("HarnessAgentLoopIterationJob: Starting iteration {$this->iteration}", ['session_id' => $this->sessionId]);

        $mainAgent = new RgSocEngineerMain;
        $providerName = $state['provider'];
        $model = $state['model'];

        $llmClient = new LaravelAiClient($providerName, $model);

        $registry = new ToolRegistry;
        foreach ($mainAgent->tools() as $laravelTool) {
            $registry->attach(new LaravelToolAdapter($laravelTool));
        }

        $logger = new class extends AbstractLogger
        {
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                Log::log($level, (string) $message, $context);
            }
        };

        $agentLoop = new AgentLoop(
            llmClient: $llmClient,
            registry: $registry,
            systemPrompt: $state['systemPrompt'],
            model: $model,
            maxIterations: 1, // Execute exactly 1 iteration per job
            logger: $logger
        );

        $agentLoop->setAgentName('RgSocEngineerMain');

        try {
            $analytics = new LaravelAnalyticsCollector;
        } catch (\Throwable $e) {
            $analytics = null;
        }

        $history = $state['history'] ?? [];
        $userPrompt = $state['userPrompt'];

        $effectivePrompt = $this->iteration === 0
            ? "TASK: {$userPrompt}\n\nCONVERSATION CONTEXT:\n{$userPrompt}"
            : $userPrompt;

        $responseText = $agentLoop->run($effectivePrompt, $history, $this->sessionId, $analytics);
        $toolCalls = $agentLoop->getExecutedToolCalls();

        // Accumulate tool calls in Redis state
        $accumulatedToolCalls = $state['toolCalls'] ?? [];
        foreach ($toolCalls as $tc) {
            $accumulatedToolCalls[] = $tc;
        }
        $state['toolCalls'] = $accumulatedToolCalls;

        // Run the second Dirac Router (observer)
        $observer = new DiracObserverRouter;
        $decision = $observer->evaluate($history, $toolCalls);

        $nextIteration = $this->iteration + 1;
        $maxIterations = $state['maxIterations'] ?? 10;

        if ($decision === DiracObserverRouter::DECISION_ITERATE && $nextIteration < $maxIterations) {
            $state['history'] = $history;
            $state['iteration'] = $nextIteration;
            Redis::set($stateKey, json_encode($state));

            // Queue the next iteration job to the active batch
            $this->batch()->add(new HarnessAgentLoopIterationJob($this->sessionId, $nextIteration));
            Log::info("HarnessAgentLoopIterationJob: Observer decided to ITERATE. Scheduled iteration {$nextIteration}", ['session_id' => $this->sessionId]);
        } else {
            $state['history'] = $history;
            $state['result'] = $responseText;
            $state['status'] = 'completed';
            Redis::set($stateKey, json_encode($state));

            Log::info('HarnessAgentLoopIterationJob: Observer decided to COMPLETE. Loop finished.', ['session_id' => $this->sessionId]);
        }
    }
}
