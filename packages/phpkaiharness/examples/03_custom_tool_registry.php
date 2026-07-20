<?php

/**
 * PHP Kai Harness - Custom Tool Registry Example
 *
 * Demonstrates registering custom executable tools into the harness.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Phpkaiharness\Core\AgentHarness;
use Phpkaiharness\Llm\LaravelAiClient;
use Phpkaiharness\Tools\ToolRegistry;

echo "=== PHP Kai Harness: Custom Tool Registry Example ===\n\n";

// 1. Create a Tool Registry and register custom tools
$toolRegistry = new ToolRegistry();

// Register a custom tool via callback
$toolRegistry->register('calculate_discount', [
    'name' => 'calculate_discount',
    'description' => 'Calculates discounted price based on original price and percentage off.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'price' => ['type' => 'number', 'description' => 'Original price in USD'],
            'discount_percent' => ['type' => 'number', 'description' => 'Discount percentage (0-100)'],
        ],
        'required' => ['price', 'discount_percent'],
    ],
], function (array $args): string {
    $price = (float) ($args['price'] ?? 0);
    $percent = (float) ($args['discount_percent'] ?? 0);
    $finalPrice = $price * (1 - ($percent / 100));

    return json_encode([
        'original_price' => $price,
        'discount_percent' => $percent,
        'final_price' => round($finalPrice, 2),
    ]);
});

echo "Registered Tools: " . implode(', ', array_keys($toolRegistry->getTools())) . "\n\n";

// 2. Instantiate Client and Harness with Tool Registry
$client = new LaravelAiClient('ollama', 'llama3.2');
$harness = new AgentHarness($client, maxIterations: 5, toolRegistry: $toolRegistry);

echo "Harness configured with custom tools ready for agent execution.\n";
