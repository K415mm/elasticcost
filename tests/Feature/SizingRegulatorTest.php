<?php

namespace Tests\Feature;

use App\Ai\Agents\SizingRegulator;
use App\Ai\Middleware\InjectDocumentation;
use App\Models\Client;
use App\Models\ClientScenarioMsspDetail;
use App\Models\Scenario;
use Database\Seeders\SizingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class SizingRegulatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed standard sizing templates
        $this->seed(SizingSeeder::class);
        Embeddings::fake();
    }

    public function test_sizing_regulator_agent_implements_interface_correctly(): void
    {
        $agent = new SizingRegulator;
        $this->assertInstanceOf(Agent::class, $agent);
        $this->assertInstanceOf(HasMiddleware::class, $agent);
        $this->assertInstanceOf(HasStructuredOutput::class, $agent);

        $instructions = $agent->instructions();
        $this->assertStringContainsString('AUDIT & CRITIQUE INSTRUCTIONS', $instructions);
        $this->assertStringContainsString('RAM-to-Disk Ratios Audit', $instructions);

        $middleware = $agent->middleware();
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(InjectDocumentation::class, $middleware[0]);
    }

    public function test_sizing_analyze_ai_endpoint_is_successful_and_caches_response(): void
    {
        $client = Client::create(['name' => 'Acme Test Sizing Client']);
        $scenario = Scenario::first();

        // Fake the AI agent response with structured output
        SizingRegulator::fake([
            [
                'verdict' => 'Adequate',
                'health_score' => 8,
                'ratio_audit' => [],
                'ha_check' => [
                    'master_eligible_count' => 3,
                    'quorum_met' => true,
                    'remarks' => 'Good',
                ],
                'recommendations' => [],
                'full_critique' => 'Mock AI Sizing critique output from Gemma',
            ],
        ]);

        $response = $this->post(route('sizing.analyze-ai', [$client->id, $scenario->id]));

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertStringContainsString('Mock AI Sizing critique output from Gemma', $response->json('analysis'));
        $response->assertJsonStructure(['success', 'analysis', 'html']);

        // Assert it cached the response to the database
        $detail = ClientScenarioMsspDetail::where([
            'client_id' => $client->id,
            'scenario_id' => $scenario->id,
        ])->first();

        $this->assertNotNull($detail);
        $this->assertStringContainsString('Mock AI Sizing critique output from Gemma', $detail->ai_sizing_analysis);
    }

    public function test_sizing_analyze_ai_handles_exceptions_gracefully(): void
    {
        $client = Client::create(['name' => 'Acme Test Sizing Client Failure']);
        $scenario = Scenario::first();

        // Fake a failure by making the agent throw an exception when prompted
        SizingRegulator::fake(function () {
            throw new \Exception('Connection refused to local Ollama server');
        });

        $response = $this->post(route('sizing.analyze-ai', [$client->id, $scenario->id]));

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('Connection refused to local Ollama server', $response->json('message'));
    }
}
