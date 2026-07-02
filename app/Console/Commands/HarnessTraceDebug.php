<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Phpkaiharness\Core\AgentLoop;
use Phpkaiharness\Llm\LlmClientFactory;
use Phpkaiharness\Monitor\SqliteMonitorStore;
use Phpkaiharness\Session\SessionManager;
use Phpkaiharness\Support\TraceEvaluator;

/**
 * Evaluate phpkaiharness execution traces.
 *
 * Two modes:
 *  - Without --session: runs a new prompt and evaluates the trace.
 *  - With --session=ID: evaluates an existing session from the DB
 *    (use this after running prompts manually from the dashboard).
 */
class HarnessTraceDebug extends Command
{
    protected $signature = 'harness:trace-debug
        {--session= : Evaluate an existing session by ID (no new prompt)}
        {--prompt= : Custom prompt to send (ignored if --session is set)}
        {--provider=lmstudio : LLM provider}
        {--model= : Model name}
        {--url= : Provider base URL}
        {--output= : Output file path}';

    protected $description = 'Run a prompt through phpkaiharness (or evaluate an existing session), capture the full execution trace, evaluate every feature node, and write a debug report.';

    public function handle(): int
    {
        $evaluator = new TraceEvaluator;
        $outputFile = $this->option('output') ?: storage_path('app/harness-trace-debug.log');

        // ── Session evaluation mode (no new prompt) ──
        if ($sessionId = $this->option('session')) {
            $this->info("Evaluating existing session: {$sessionId}");

            $result = $evaluator->evaluateSession($sessionId);
            if (! $result) {
                $this->error("Session '{$sessionId}' not found in telemetry DB.");

                return 1;
            }

            file_put_contents($outputFile, $result['report']);
            $this->info("Debug report written to: {$outputFile}");
            $this->printSummary($result['evaluation']);

            return 0;
        }

        // ── New prompt mode ──
        $prompt = $this->option('prompt') ?? 'Say hello and tell me what 2+2 is. Keep it brief.';
        $provider = $this->option('provider') ?? 'lmstudio';
        $model = $this->option('model') ?: (string) config('harness.default.model', '');
        $url = $this->option('url') ?: '';

        $this->info('Starting harness trace debug...');
        $this->info("Provider: {$provider}, Model: {$model}");
        $this->info("Prompt: {$prompt}");

        $factory = new LlmClientFactory;
        $client = $factory->make($provider, $model, [
            'url' => $url,
            'connection' => (string) config('ai.default', 'ollama'),
        ]);

        $sessionId = bin2hex(random_bytes(8));
        $this->info("Session ID: {$sessionId}");

        $dbPath = config('harness.cache.db_path') ?: SqliteMonitorStore::defaultDbPath();
        $isolationEnabled = config('harness.session_isolation.enabled', false);
        if ($isolationEnabled) {
            try {
                $sessionManager = app(SessionManager::class);
                $sessionManager->activate($sessionId);
                $perSessionDb = $sessionManager->findMonitorDbForSession($sessionId);
                if ($perSessionDb) {
                    $dbPath = $perSessionDb;
                }
            } catch (\Throwable $e) {
            }
        }
        $store = new SqliteMonitorStore($dbPath);

        $agentLoop = new AgentLoop(
            llmClient: $client,
            systemPrompt: 'You are a helpful assistant. Answer concisely.',
            model: $model,
        );
        $agentLoop->setAgentName('debug-trace-agent');

        $startTime = microtime(true);
        $history = [];
        try {
            $response = $agentLoop->run(
                userPrompt: $prompt,
                history: $history,
                sessionId: $sessionId,
                collector: $store
            );
        } catch (\Throwable $e) {
            $response = 'ERROR: '.$e->getMessage();
            $this->error('AgentLoop failed: '.$e->getMessage());
        }
        $totalMs = (int) ((microtime(true) - $startTime) * 1000);

        $this->info("Execution completed in {$totalMs}ms");
        $this->info('Response: '.mb_substr($response, 0, 200));

        $trace = $evaluator->extractTrace($store, $sessionId);
        $evaluation = $evaluator->evaluateNodes($trace, $prompt, $response, $totalMs);
        $report = $evaluator->buildReport($sessionId, $prompt, $provider, $model, $trace, $evaluation, $response, $totalMs);

        file_put_contents($outputFile, $report);
        $this->info("Debug report written to: {$outputFile}");
        $this->printSummary($evaluation);

        return 0;
    }

    private function printSummary(array $evaluation): void
    {
        $this->newLine();
        $this->info('=== TRACE NODE EVALUATION SUMMARY ===');
        foreach ($evaluation as $node) {
            $status = $node['status'];
            $icon = match ($status) {
                'PASS' => '✓',
                'FAIL' => '✗',
                'WARN' => '⚠',
                'SKIP' => '○',
                'INFO' => 'ℹ',
                default => '?',
            };
            $line = "  {$icon} [{$status}] {$node['node_type']}: {$node['node_title']}";
            if (! empty($node['issue'])) {
                $line .= " → {$node['issue']}";
            }
            $this->line($line);
        }
    }
}
