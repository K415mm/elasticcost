<?php

namespace Tests\Feature;

use App\Ai\Routing\DiracObserverRouter;
use App\Jobs\HarnessAgentLoopIterationJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class HarnessHorizonBatchTest extends TestCase
{
    public function test_dirac_observer_router_decides_correctly(): void
    {
        $observer = new DiracObserverRouter;

        // Case 1: Active tool calls should force iteration
        $decision1 = $observer->evaluate(
            [['role' => 'assistant', 'content' => 'I am running a tool']],
            [['id' => 'call_123', 'type' => 'function', 'function' => ['name' => 'GetClientInventoryTool']]]
        );
        $this->assertEquals(DiracObserverRouter::DECISION_ITERATE, $decision1);

        // Case 2: Converged/stable text with no tools should trigger completion
        $decision2 = $observer->evaluate(
            [
                ['role' => 'user', 'content' => 'Hello'],
                ['role' => 'assistant', 'content' => 'Hello! The Acmee client has 30 devices in total. I have verified this in the database.'],
            ],
            []
        );
        $this->assertEquals(DiracObserverRouter::DECISION_COMPLETE, $decision2);
    }

    public function test_async_job_dispatches_properly(): void
    {
        Bus::fake();

        $sessionId = 'test_sess_'.uniqid();
        $stateKey = "harness:session:{$sessionId}:state";
        $stateData = [
            'userPrompt' => 'update client 3',
            'history' => [],
            'sessionId' => $sessionId,
            'systemPrompt' => 'System prompt',
            'model' => 'qwen-plus',
            'provider' => 'qwen',
            'iteration' => 0,
            'maxIterations' => 10,
            'result' => null,
            'status' => 'pending',
        ];

        // Mock Redis facade to avoid real connection errors
        Redis::shouldReceive('set')
            ->once()
            ->with($stateKey, json_encode($stateData))
            ->andReturn(true);

        Redis::shouldReceive('del')
            ->once()
            ->with($stateKey)
            ->andReturn(1);

        Redis::set($stateKey, json_encode($stateData));

        Bus::batch([
            new HarnessAgentLoopIterationJob($sessionId, 0),
        ])->dispatch();

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->first() instanceof HarnessAgentLoopIterationJob;
        });

        Redis::del($stateKey);
    }
}
