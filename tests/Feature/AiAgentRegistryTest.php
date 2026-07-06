<?php

namespace Tests\Feature;

use App\Ai\Agents\OfferAnalyst;
use App\Ai\Agents\RgSocEngineer;
use App\Ai\Agents\SizingRegulator;
use App\Models\Client;
use App\Models\GlobalSetting;
use App\Models\Scenario;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class AiAgentRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed standard templates and permissions
        $this->seed(DatabaseSeeder::class);
        Embeddings::fake();
        $user = User::factory()->ceo()->create();
        $this->actingAs($user);
    }

    public function test_agents_page_renders_successfully(): void
    {
        $response = $this->get(route('settings.agents'));
        $response->assertStatus(200);
        $response->assertSee('AI Agent Registry');
        $response->assertSee('ElasticCost Assistant');
        $response->assertSee('RG SOC Engineer');
        $response->assertSee('Offer Analyst');
        $response->assertSee('Sizing Regulator');
        $response->assertSee('Orchestration Sandbox');
    }

    public function test_update_config_updates_settings_successfully(): void
    {
        $postData = [
            'ai_orchestration_mode' => 'autonomous',
            'ai_delegation_enabled' => '1',
            'ai_max_delegation_hops' => 5,
        ];

        $response = $this->post(route('settings.agents.config'), $postData);
        $response->assertRedirect(route('settings.agents'));
        $response->assertSessionHas('success');

        $this->assertEquals('autonomous', GlobalSetting::getValue('ai_orchestration_mode'));
        $this->assertEquals('1', GlobalSetting::getValue('ai_delegation_enabled'));
        $this->assertEquals('5', GlobalSetting::getValue('ai_max_delegation_hops'));
    }

    public function test_run_analysis_sizing_regulator_successfully(): void
    {
        $client = Client::create(['name' => 'Registry Test Client']);
        $scenario = Scenario::first();

        SizingRegulator::fake([
            [
                'verdict' => 'Adequate',
                'health_score' => 9,
                'ratio_audit' => [],
                'ha_check' => [
                    'master_eligible_count' => 3,
                    'quorum_met' => true,
                    'remarks' => 'Optimal master counts',
                ],
                'recommendations' => ['Ensure replicas are enabled on hot tier'],
                'full_critique' => 'Mocked Sizing Regulator Critique Output',
            ],
        ]);

        $postData = [
            'client_id' => $client->id,
            'scenario_id' => $scenario->id,
            'agent' => 'SizingRegulator',
        ];

        $response = $this->post(route('settings.agents.analyze'), $postData);
        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('Sizing Regulator', $response->json('agent'));
        $this->assertStringContainsString('Mocked Sizing Regulator Critique Output', $response->json('output'));
        $this->assertStringContainsString('Mocked Sizing Regulator Critique Output', $response->json('html'));
    }

    public function test_run_analysis_offer_analyst_successfully(): void
    {
        $client = Client::create(['name' => 'Registry Test Client 2']);
        $scenario = Scenario::first();

        // Populate required inventory/MSPP config details so calculation succeeds
        $client->clientAssets()->create([
            'asset_type_id' => 1,
            'device_count' => 50,
        ]);

        OfferAnalyst::fake([
            [
                'health_score' => 8,
                'margin_status' => 'Optimal',
                'sanity_checks' => ['Ingestion check passed'],
                'staffing_status' => 'Balanced',
                'infrastructure_status' => 'Optimal',
                'recommendations' => ['Markup is competitive'],
                'full_critique' => 'Mocked Offer Analyst Critique Output',
            ],
        ]);

        $postData = [
            'client_id' => $client->id,
            'scenario_id' => $scenario->id,
            'agent' => 'OfferAnalyst',
        ];

        $response = $this->post(route('settings.agents.analyze'), $postData);
        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('Offer Analyst', $response->json('agent'));
        $this->assertStringContainsString('Mocked Offer Analyst Critique Output', $response->json('output'));
    }

    public function test_run_orchestrated_action_successfully(): void
    {
        RgSocEngineer::fake(['Mock response: System settings are updated.']);

        $postData = [
            'context' => 'Offer Analyst: change USD to EUR rate to 0.95',
            'instruction' => 'Perform setting updates mentioned.',
        ];

        $response = $this->post(route('settings.agents.orchestrate'), $postData);
        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertStringContainsString('Mock response: System settings are updated.', $response->json('output'));
        $this->assertIsArray($response->json('executed_tools'));
    }

    public function test_run_analysis_simulation_mode(): void
    {
        $client = Client::create(['name' => 'Registry Test Client']);
        $scenario = Scenario::first();

        $postData = [
            'client_id' => $client->id,
            'scenario_id' => $scenario->id,
            'agent' => 'SizingRegulator',
            'simulation' => '1',
        ];

        $response = $this->post(route('settings.agents.analyze'), $postData);
        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('Sizing Regulator', $response->json('agent'));
        $this->assertStringContainsString('Sizing Regulator Recommendations', $response->json('output'));
    }

    public function test_run_orchestrated_action_simulation_mode(): void
    {
        $postData = [
            'context' => 'Sizing Regulator Recommendations: usd_to_eur_rate to 0.95',
            'instruction' => 'Perform updates',
            'simulation' => '1',
        ];

        $response = $this->post(route('settings.agents.orchestrate'), $postData);
        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertStringContainsString('successfully analyzed', $response->json('output'));
        $this->assertCount(1, $response->json('executed_tools'));
        $this->assertEquals('UpdateGlobalSettingTool', $response->json('executed_tools.0.name'));
        $this->assertEquals('0.95', GlobalSetting::getValue('usd_to_eur_rate'));
    }
}
