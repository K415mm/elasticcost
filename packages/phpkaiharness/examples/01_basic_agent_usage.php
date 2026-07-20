<?php

/**
 * PHP Kai Harness - Basic Standalone Agent Usage Example
 *
 * Demonstrates initializing and running an autonomous agent loop in a standard PHP script
 * using the Phpkaiharness core classes.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Phpkaiharness\Core\AgentHarness;
use Phpkaiharness\Llm\LaravelAiClient;
use Phpkaiharness\Monitor\SqliteMonitorStore;

echo "=== PHP Kai Harness: Standalone Agent Example ===\n\n";

// 1. Initialize SQLite Monitor Store for telemetry tracking
$dbPath = __DIR__ . '/harness_telemetry.sqlite';
$monitorStore = new SqliteMonitorStore($dbPath);

// 2. Configure LLM Client (Ollama / Local / OpenRouter / Qwen)
$llmClient = new LaravelAiClient(
    provider: 'ollama',
    model: 'llama3.2'
);

// 3. Initialize Agent Harness
$harness = new AgentHarness(
    client: $llmClient,
    maxIterations: 5
);

// 4. Define system instructions and user prompt
$systemPrompt = "You are a helpful software assistant. Be concise and precise.";
$userPrompt = "Explain the concept of Dependency Injection in two clear sentences.";

echo "Sending Prompt: \"{$userPrompt}\"\n";
echo "Executing Agent Loop...\n\n";

try {
    // 5. Execute Agent Loop
    $result = $harness->run(
        systemPrompt: $systemPrompt,
        userPrompt: $userPrompt,
        sessionId: 'standalone-session-' . time()
    );

    echo "--- Agent Response ---\n";
    echo $result['response'] ?? 'No response returned.';
    echo "\n----------------------\n\n";

    echo "Execution Completed successfully!\n";
    echo "Telemetry logged to: {$dbPath}\n";

} catch (\Throwable $e) {
    echo "Error executing agent: " . $e->getMessage() . "\n";
}
