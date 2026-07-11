<?php

use App\Ai\Tools\CompareScenariosTool;
use App\Ai\Tools\ExportMsspProposalTool;
use App\Ai\Tools\SelectBestScenarioTool;
use App\Models\Client;
use App\Models\Scenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request as AiToolRequest;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->client = Client::create([
        'name' => 'Test Client',
        'description' => 'A test client for AI tools',
    ]);

    $this->scenarioA = Scenario::create([
        'name' => 'Small',
        'description' => 'Small deployment',
        'workload_profile' => 'min',
        'retention_days' => 0,
        'hot_days' => 0,
        'warm_days' => 0,
        'cold_days' => 0,
        'frozen_days' => 0,
    ]);

    $this->scenarioB = Scenario::create([
        'name' => 'Large',
        'description' => 'Large deployment',
        'workload_profile' => 'max',
        'retention_days' => 0,
        'hot_days' => 0,
        'warm_days' => 0,
        'cold_days' => 0,
        'frozen_days' => 0,
    ]);
});

test('export_mssp_proposal_tool returns a valid download url for the current scenario', function () {
    $tool = app(ExportMsspProposalTool::class);
    $response = $tool->handle(new AiToolRequest([
        'client_id' => $this->client->id,
        'scenario_id' => $this->scenarioA->id,
        'format' => 'markdown',
    ]));

    $data = json_decode((string) $response, true);

    expect($data['client_id'])->toBe($this->client->id)
        ->and($data['scenario_id'])->toBe($this->scenarioA->id)
        ->and($data['format'])->toBe('markdown')
        ->and($data['download_url'])->toContain('/mssp-cost/export/markdown')
        ->and($data['download_url'])->toContain('clients/'.$this->client->id)
        ->and($data['download_url'])->toContain('scenarios/'.$this->scenarioA->id);
});

test('export_mssp_proposal_tool returns an error for invalid format', function () {
    $tool = app(ExportMsspProposalTool::class);
    $response = $tool->handle(new AiToolRequest([
        'client_id' => $this->client->id,
        'scenario_id' => $this->scenarioA->id,
        'format' => 'pdf',
    ]));

    $data = json_decode((string) $response, true);

    expect($data['error'] ?? null)->toContain('Invalid format');
});

test('compare_scenarios_tool returns a comparison for all scenarios', function () {
    $tool = app(CompareScenariosTool::class);
    $response = $tool->handle(new AiToolRequest([
        'client_id' => $this->client->id,
    ]));

    $data = json_decode((string) $response, true);

    expect($data['client_id'])->toBe($this->client->id)
        ->and($data['comparisons'])->toHaveCount(2)
        ->and($data['best_overall']['scenario_id'])->toBeIn([$this->scenarioA->id, $this->scenarioB->id]);
});

test('select_best_scenario_tool returns the recommended scenario with export links', function () {
    $tool = app(SelectBestScenarioTool::class);
    $response = $tool->handle(new AiToolRequest([
        'client_id' => $this->client->id,
        'preference' => 'overall',
    ]));

    $data = json_decode((string) $response, true);

    expect($data['client_id'])->toBe($this->client->id)
        ->and($data['preference'])->toBe('overall')
        ->and($data['recommended_scenario_id'])->toBeIn([$this->scenarioA->id, $this->scenarioB->id])
        ->and($data['view_url'])->toContain('/mssp-cost')
        ->and($data['export_urls'])->toHaveKeys(['markdown', 'word', 'excel'])
        ->and($data['export_urls']['markdown'])->toContain('/mssp-cost/export/markdown');
});
