<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientAsset;
use App\Models\ClientScenarioAnalystAllocation;
use App\Models\ClientScenarioMsspDetail;
use App\Models\Scenario;
use App\Models\User;
use App\Services\MsspCostingEngine;
use Database\Seeders\MsspSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SizingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Phpkaiharness\Llm\LaravelAiClient;
use Tests\TestCase;

class MsspCostingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed standard sizing, permissions, and MSSP role templates
        $this->seed(PermissionSeeder::class);
        $this->seed(SizingSeeder::class);
        $this->seed(MsspSeeder::class);
        Embeddings::fake();
        config([
            'harness.feature_graph.nodes.semantic_cache.enabled' => false,
            'harness.failover.enabled' => false,
        ]);
        $user = User::factory()->ceo()->create();
        $this->actingAs($user);
    }

    public function test_mssp_costing_calculates_correct_prices(): void
    {
        $client = Client::create(['name' => 'Acme SOC client']);

        // Active Directory asset
        ClientAsset::create([
            'client_id' => $client->id,
            'asset_type_id' => 1,
            'device_count' => 2,
        ]);

        $scenario = Scenario::find(2); // Min profile multi-tier

        /** @var MsspCostingEngine $engine */
        $engine = app(MsspCostingEngine::class);
        $costData = $engine->calculate($client, $scenario);

        // Assert setup and maintenance costs exist
        $this->assertEquals(5000.00, $costData['onetime_setup_cost']);
        $this->assertEquals(1500.00, $costData['monthly_maintenance_cost']);

        // Assert analyst allocations exist and compute to correct ratio
        // Default allocation for L1 Analyst (role id 1) is 10%
        // L1 default salary is 4000.00 => expected cost 400.00
        $l1Alloc = collect($costData['analysts']['roles'])->firstWhere('role_id', 1);
        $this->assertNotNull($l1Alloc);
        $this->assertEquals(10.00, $l1Alloc['allocation_percentage']);
        $this->assertEquals(400.00, $l1Alloc['client_cost']);
    }

    public function test_mssp_cost_dashboard_renders_successfully(): void
    {
        $client = Client::create(['name' => 'Acme SOC client']);
        ClientAsset::create([
            'client_id' => $client->id,
            'asset_type_id' => 1,
            'device_count' => 5,
        ]);

        $scenario = Scenario::first();

        $response = $this->get(route('mssp.show', [$client->id, $scenario->id]));
        $response->assertStatus(200);
        $response->assertSee('SOC Cost Proposal');
        $response->assertSee('Upfront Setup Cost');
    }

    public function test_mssp_cost_settings_update_saves_to_database(): void
    {
        $client = Client::create(['name' => 'Acme SOC client']);
        $scenario = Scenario::first();

        // Perform initial calculation to trigger detail record creation
        $engine = app(MsspCostingEngine::class);
        $costData = $engine->calculate($client, $scenario);
        $msspDetail = $costData['raw_mssp_detail'];

        $postData = [
            'one_time_setup_cost' => 7500.00,
            'monthly_maintenance_cost' => 2000.00,
            'ram_monthly_cost_per_gb' => 1.80,
            'nvme_ssd_monthly_cost_per_gb' => 0.20,
            'sata_ssd_monthly_cost_per_gb' => 0.10,
            'local_ssd_monthly_cost_per_gb' => 0.15,
            'elastic_cloud_monthly_cost_per_gb_ram' => 55.00,
            'elastic_cloud_subscription_tier' => 'platinum',
            'siem_agent_monthly_cost_per_device' => 18.00,
            'mdr_agent_monthly_cost_per_device' => 35.00,
            'edr_agent_monthly_cost_per_device' => 12.00,
            'allocations' => [
                1 => ['percentage' => 20.00, 'custom_salary' => 4500.00, 'staff_count' => 1], // Override L1
                2 => ['percentage' => 10.00, 'custom_salary' => '', 'staff_count' => 1],
                3 => ['percentage' => 0.00, 'custom_salary' => '', 'staff_count' => 1],
                4 => ['percentage' => 0.00, 'custom_salary' => '', 'staff_count' => 1],
                5 => ['percentage' => 0.00, 'custom_salary' => '', 'staff_count' => 1],
            ],
        ];

        $response = $this->post(route('mssp.update', [$client->id, $scenario->id]), $postData);
        $response->assertRedirect(route('mssp.show', [$client->id, $scenario->id]));
        $response->assertSessionHas('success');

        // Check DB updates
        $this->assertDatabaseHas('client_scenario_mssp_details', [
            'id' => $msspDetail->id,
            'one_time_setup_cost' => 7500.00,
            'monthly_maintenance_cost' => 2000.00,
            'ram_monthly_cost_per_gb' => 1.8000,
            'elastic_cloud_monthly_cost_per_gb_ram' => 55.0000,
            'siem_agent_monthly_cost_per_device' => 18.0000,
            'mdr_agent_monthly_cost_per_device' => 35.0000,
            'edr_agent_monthly_cost_per_device' => 12.0000,
        ]);

        $this->assertDatabaseHas('client_scenario_analyst_allocations', [
            'mssp_details_id' => $msspDetail->id,
            'soc_role_id' => 1,
            'allocation_percentage' => 20.00,
            'custom_monthly_salary' => 4500.00,
        ]);
    }

    public function test_mssp_costing_incorporates_staff_count_multiplier(): void
    {
        $client = Client::create(['name' => 'Acme Staff Cost client']);
        $scenario = Scenario::first();

        $engine = app(MsspCostingEngine::class);
        $costData = $engine->calculate($client, $scenario);
        $msspDetail = $costData['raw_mssp_detail'];

        // Standard L1 Analyst has default monthly salary of 4000.00
        // Standard allocation percentage for L1 is 10%
        // Standard L1 cost: 4000 * 10% = 400.00

        // Find L1 allocation and update staff_count to 3
        $allocation = ClientScenarioAnalystAllocation::where([
            'mssp_details_id' => $msspDetail->id,
            'soc_role_id' => 1,
        ])->first();

        $this->assertNotNull($allocation);
        $allocation->update(['staff_count' => 3]);

        // Recalculate
        $costData = $engine->calculate($client, $scenario);
        $l1Alloc = collect($costData['analysts']['roles'])->firstWhere('role_id', 1);

        $this->assertEquals(3, $l1Alloc['staff_count']);
        // 4000.00 * 0.10 * 3 = 1200.00
        $this->assertEquals(1200.00, $l1Alloc['client_cost']);
    }

    public function test_mssp_costing_shares_license_cost_by_percentage(): void
    {
        $client = Client::create(['name' => 'Acme Share Cost client']);
        // Active Directory asset to give some data volume & license cost
        ClientAsset::create([
            'client_id' => $client->id,
            'asset_type_id' => 1,
            'device_count' => 10,
        ]);
        $scenario = Scenario::first();

        $engine = app(MsspCostingEngine::class);

        // 1. Unshared license calculation
        $costDataUnshared = $engine->calculate($client, $scenario);
        $originalMonthlyLicense = $costDataUnshared['sizing_summary']['monthly_license_usd'];
        $this->assertGreaterThan(0, $originalMonthlyLicense);

        // 2. Shared license calculation at 25%
        $msspDetail = $costDataUnshared['raw_mssp_detail'];
        $msspDetail->update([
            'is_license_shared' => true,
            'license_share_percentage' => 25.00,
        ]);

        $costDataShared = $engine->calculate($client, $scenario);
        $sharedMonthlyLicense = $costDataShared['sizing_summary']['monthly_license_usd'];

        $this->assertEquals(round($originalMonthlyLicense * 0.25, 2), $sharedMonthlyLicense);
    }

    public function test_mssp_update_persists_license_sharing_and_staff_count(): void
    {
        $client = Client::create(['name' => 'Acme Persist Cost client']);
        $scenario = Scenario::first();

        $engine = app(MsspCostingEngine::class);
        $costData = $engine->calculate($client, $scenario);
        $msspDetail = $costData['raw_mssp_detail'];

        $postData = [
            'one_time_setup_cost' => 5000.00,
            'monthly_maintenance_cost' => 1500.00,
            'ram_monthly_cost_per_gb' => 1.50,
            'nvme_ssd_monthly_cost_per_gb' => 0.15,
            'sata_ssd_monthly_cost_per_gb' => 0.08,
            'local_ssd_monthly_cost_per_gb' => 0.12,
            'elastic_cloud_monthly_cost_per_gb_ram' => 45.00,
            'elastic_cloud_subscription_tier' => 'platinum',
            'siem_agent_monthly_cost_per_device' => 15.00,
            'mdr_agent_monthly_cost_per_device' => 30.00,
            'edr_agent_monthly_cost_per_device' => 10.00,
            'is_license_shared' => '1',
            'license_share_percentage' => 45.50,
            'allocations' => [
                1 => ['percentage' => 10.00, 'custom_salary' => '', 'staff_count' => 3],
                2 => ['percentage' => 5.00, 'custom_salary' => '', 'staff_count' => 2],
                3 => ['percentage' => 2.00, 'custom_salary' => '', 'staff_count' => 1],
                4 => ['percentage' => 5.00, 'custom_salary' => '', 'staff_count' => 1],
                5 => ['percentage' => 2.00, 'custom_salary' => '', 'staff_count' => 1],
            ],
        ];

        $response = $this->post(route('mssp.update', [$client->id, $scenario->id]), $postData);
        $response->assertRedirect(route('mssp.show', [$client->id, $scenario->id]));

        // Assert updates in database
        $this->assertDatabaseHas('client_scenario_mssp_details', [
            'id' => $msspDetail->id,
            'is_license_shared' => true,
            'license_share_percentage' => 45.50,
        ]);

        $this->assertDatabaseHas('client_scenario_analyst_allocations', [
            'mssp_details_id' => $msspDetail->id,
            'soc_role_id' => 1,
            'staff_count' => 3,
        ]);

        $this->assertDatabaseHas('client_scenario_analyst_allocations', [
            'mssp_details_id' => $msspDetail->id,
            'soc_role_id' => 2,
            'staff_count' => 2,
        ]);
    }

    public function test_mssp_costing_calculates_correct_profit_margins(): void
    {
        $client = Client::create(['name' => 'Acme Profit Calc client']);
        $scenario = Scenario::first();

        $engine = app(MsspCostingEngine::class);
        $costData = $engine->calculate($client, $scenario);
        $msspDetail = $costData['raw_mssp_detail'];

        $msspDetail->update([
            'assurance_benefit_percentage' => 5.00,
            'marketing_benefit_percentage' => 10.00,
            'soc_manager_benefit_percentage' => 5.00,
            'ceo_benefit_percentage' => 5.00,
            'fixed_profit_percentage' => 10.00,
        ]);

        $recalculated = $engine->calculate($client, $scenario);

        $this->assertEquals(35.00, $recalculated['total_profit_percentage']);
        $expectedProfitAmount = round($recalculated['total_monthly_service_cost'] * 0.35, 2);
        $this->assertEquals($expectedProfitAmount, $recalculated['total_profit_amount']);
        $this->assertEquals(round($recalculated['total_monthly_service_cost'] + $expectedProfitAmount, 2), $recalculated['client_offered_price_mrc']);
    }

    public function test_mssp_update_persists_profit_margins(): void
    {
        $client = Client::create(['name' => 'Acme Profit Update client']);
        $scenario = Scenario::first();

        $engine = app(MsspCostingEngine::class);
        $costData = $engine->calculate($client, $scenario);
        $msspDetail = $costData['raw_mssp_detail'];

        $postData = [
            'one_time_setup_cost' => 5000.00,
            'monthly_maintenance_cost' => 1500.00,
            'ram_monthly_cost_per_gb' => 1.50,
            'nvme_ssd_monthly_cost_per_gb' => 0.15,
            'sata_ssd_monthly_cost_per_gb' => 0.08,
            'local_ssd_monthly_cost_per_gb' => 0.12,
            'elastic_cloud_monthly_cost_per_gb_ram' => 45.00,
            'elastic_cloud_subscription_tier' => 'platinum',
            'siem_agent_monthly_cost_per_device' => 15.00,
            'mdr_agent_monthly_cost_per_device' => 30.00,
            'edr_agent_monthly_cost_per_device' => 10.00,
            'assurance_benefit_percentage' => 4.50,
            'marketing_benefit_percentage' => 8.25,
            'soc_manager_benefit_percentage' => 5.00,
            'ceo_benefit_percentage' => 2.50,
            'fixed_profit_percentage' => 12.00,
            'allocations' => [
                1 => ['percentage' => 10.00, 'custom_salary' => '', 'staff_count' => 1],
                2 => ['percentage' => 5.00, 'custom_salary' => '', 'staff_count' => 1],
                3 => ['percentage' => 2.00, 'custom_salary' => '', 'staff_count' => 1],
                4 => ['percentage' => 5.00, 'custom_salary' => '', 'staff_count' => 1],
                5 => ['percentage' => 2.00, 'custom_salary' => '', 'staff_count' => 1],
            ],
        ];

        $response = $this->post(route('mssp.update', [$client->id, $scenario->id]), $postData);
        $response->assertRedirect(route('mssp.show', [$client->id, $scenario->id]));

        $this->assertDatabaseHas('client_scenario_mssp_details', [
            'id' => $msspDetail->id,
            'assurance_benefit_percentage' => 4.50,
            'marketing_benefit_percentage' => 8.25,
            'soc_manager_benefit_percentage' => 5.00,
            'ceo_benefit_percentage' => 2.50,
            'fixed_profit_percentage' => 12.00,
        ]);
    }

    public function test_mssp_costing_proposal_exports_successfully(): void
    {
        $client = Client::create(['name' => 'Acme Profit Export client']);
        $scenario = Scenario::first();

        // Trigger detail creation
        $engine = app(MsspCostingEngine::class);
        $engine->calculate($client, $scenario);

        // 1. Export Markdown
        $responseMd = $this->get(route('mssp.export.markdown', [$client->id, $scenario->id]));
        $responseMd->assertStatus(200);
        $responseMd->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $responseMd->assertHeader('Content-Disposition', 'attachment; filename="'.strtolower(str_replace(' ', '_', $client->name)).'_mssp_cost_proposal.md"');

        // 2. Export Word
        $responseDoc = $this->get(route('mssp.export.word', [$client->id, $scenario->id]));
        $responseDoc->assertStatus(200);
        $responseDoc->assertHeader('Content-Type', 'application/msword');
        $responseDoc->assertHeader('Content-Disposition', 'attachment; filename="'.strtolower(str_replace(' ', '_', $client->name)).'_mssp_cost_proposal.doc"');

        // 3. Export Excel
        $responseXls = $this->get(route('mssp.export.excel', [$client->id, $scenario->id]));
        $responseXls->assertStatus(200);
        $responseXls->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $responseXls->assertHeader('Content-Disposition', 'attachment; filename="'.strtolower(str_replace(' ', '_', $client->name)).'_mssp_cost_proposal.xlsx"');
    }

    public function test_mssp_costing_ask_ai_successful(): void
    {
        $client = Client::create(['name' => 'Acme AI Test client']);
        $scenario = Scenario::first();

        // Mock LaravelAiClient to return a successful critique
        $mockClient = \Mockery::mock(LaravelAiClient::class);
        $mockClient->shouldReceive('chat')->andReturn([
            'content' => 'Mock AI Analysis Output from Gemma',
            'tool_calls' => [],
        ]);
        $mockClient->shouldReceive('getResolvedModel')->andReturn('gemma4:e2b');
        $this->app->bind(LaravelAiClient::class, fn () => $mockClient);

        $response = $this->post(route('mssp.ask-ai', [$client->id, $scenario->id]));

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertStringContainsString('Mock AI Analysis Output from Gemma', $response->json('analysis'));
        $response->assertJsonStructure(['success', 'analysis', 'html']);

        // Fetch detail from database and assert it saved the analysis containing the mocked response
        $detail = ClientScenarioMsspDetail::where([
            'client_id' => $client->id,
            'scenario_id' => $scenario->id,
        ])->first();

        $this->assertNotNull($detail);
        $this->assertStringContainsString('Mock AI Analysis Output from Gemma', $detail->ai_analysis);
    }

    public function test_mssp_costing_ask_ai_handles_exceptions_gracefully(): void
    {
        $client = Client::create(['name' => 'Acme AI Test client']);
        $scenario = Scenario::first();

        // Mock LaravelAiClient to throw an exception
        $mockClient = \Mockery::mock(LaravelAiClient::class);
        $mockClient->shouldReceive('chat')->andThrow(new \Exception('Connection refused to local Ollama'));
        $mockClient->shouldReceive('getResolvedModel')->andReturn('gemma4:e2b');
        $this->app->bind(LaravelAiClient::class, fn () => $mockClient);

        $response = $this->post(route('mssp.ask-ai', [$client->id, $scenario->id]));

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('Connection refused to local Ollama', $response->json('message'));
    }

    public function test_mssp_costing_exports_incorporate_ai_analysis(): void
    {
        $client = Client::create(['name' => 'Acme AI Export client']);
        $scenario = Scenario::first();

        // Create detail with ai_analysis populated
        $engine = app(MsspCostingEngine::class);
        $costData = $engine->calculate($client, $scenario);
        $costData['raw_mssp_detail']->update([
            'ai_analysis' => 'Special AI Report Section Content',
        ]);

        // 1. Export Markdown should include it
        $responseMd = $this->get(route('mssp.export.markdown', [$client->id, $scenario->id]));
        $responseMd->assertStatus(200);
        $this->assertStringContainsString('AI Cost & Logic Analysis', $responseMd->streamedContent());
        $this->assertStringContainsString('Special AI Report Section Content', $responseMd->streamedContent());

        // 2. Export Word should include it
        $responseDoc = $this->get(route('mssp.export.word', [$client->id, $scenario->id]));
        $responseDoc->assertStatus(200);
        $this->assertStringContainsString('AI Cost & Logic Analysis', $responseDoc->streamedContent());
        $this->assertStringContainsString('Special AI Report Section Content', $responseDoc->streamedContent());
    }

    public function test_mssp_update_converts_rates_correctly_for_tnd(): void
    {
        $client = Client::create(['name' => 'Acme TND Update client']);
        $scenario = Scenario::first();

        $engine = app(MsspCostingEngine::class);
        $costData = $engine->calculate($client, $scenario);
        $msspDetail = $costData['raw_mssp_detail'];

        // Let's set TND as active currency in session
        $this->withSession(['currency' => 'TND']);

        // The default USD to TND exchange rate is 3.10
        // We will send inputs in TND:
        // Setup Cost: 9300.00 TND => expected USD value: 9300 / 3.10 = 3000.00 USD
        // Maintenance Cost: 3100.00 TND => expected USD value: 3100 / 3.10 = 1000.00 USD
        $postData = [
            'one_time_setup_cost' => 9300.00,
            'monthly_maintenance_cost' => 3100.00,
            'ram_monthly_cost_per_gb' => 4.65, // 1.5 * 3.10
            'nvme_ssd_monthly_cost_per_gb' => 0.465,
            'sata_ssd_monthly_cost_per_gb' => 0.248,
            'local_ssd_monthly_cost_per_gb' => 0.372,
            'elastic_cloud_monthly_cost_per_gb_ram' => 139.50, // 45 * 3.10
            'elastic_cloud_subscription_tier' => 'platinum',
            'siem_agent_monthly_cost_per_device' => 46.50, // 15 * 3.10
            'mdr_agent_monthly_cost_per_device' => 93.00, // 30 * 3.10
            'edr_agent_monthly_cost_per_device' => 31.00, // 10 * 3.10
            'allocations' => [
                1 => ['percentage' => 10.00, 'custom_salary' => 12400.00, 'staff_count' => 1], // 4000 * 3.10
                2 => ['percentage' => 5.00, 'custom_salary' => '', 'staff_count' => 1],
                3 => ['percentage' => 2.00, 'custom_salary' => '', 'staff_count' => 1],
                4 => ['percentage' => 5.00, 'custom_salary' => '', 'staff_count' => 1],
                5 => ['percentage' => 2.00, 'custom_salary' => '', 'staff_count' => 1],
            ],
        ];

        $response = $this->post(route('mssp.update', [$client->id, $scenario->id]), $postData);
        $response->assertRedirect(route('mssp.show', [$client->id, $scenario->id]));

        // Assert updates in database are correctly converted back to USD
        $this->assertDatabaseHas('client_scenario_mssp_details', [
            'id' => $msspDetail->id,
            'one_time_setup_cost' => 3000.00,
            'monthly_maintenance_cost' => 1000.00,
        ]);

        $this->assertDatabaseHas('client_scenario_analyst_allocations', [
            'mssp_details_id' => $msspDetail->id,
            'soc_role_id' => 1,
            'custom_monthly_salary' => 4000.00, // 12400 / 3.10
        ]);
    }

    public function test_mssp_costing_uses_cloud_datacenter_pricing(): void
    {
        $client = Client::create(['name' => 'Acme Cloud Partner Cost client']);
        $scenario = Scenario::first();

        // 1. Initial calculation using generic rates
        $engine = app(MsspCostingEngine::class);
        $costDataBefore = $engine->calculate($client, $scenario);

        // Assert it doesn't have cloud pricing details initially
        $this->assertNull($costDataBefore['infrastructure']['nodes'][0]['matched_vm_name']);

        // 2. Perform updates to choose 'Dataxion' datacenter
        $postData = [
            'cloud_datacenter' => 'Dataxion',
            'one_time_setup_cost' => 5000.00,
            'monthly_maintenance_cost' => 1500.00,
            'ram_monthly_cost_per_gb' => 1.50,
            'nvme_ssd_monthly_cost_per_gb' => 0.15,
            'sata_ssd_monthly_cost_per_gb' => 0.08,
            'local_ssd_monthly_cost_per_gb' => 0.12,
            'elastic_cloud_monthly_cost_per_gb_ram' => 45.00,
            'elastic_cloud_subscription_tier' => 'platinum',
            'siem_agent_monthly_cost_per_device' => 15.00,
            'mdr_agent_monthly_cost_per_device' => 30.00,
            'edr_agent_monthly_cost_per_device' => 10.00,
            'allocations' => [
                1 => ['percentage' => 10.00, 'custom_salary' => '', 'staff_count' => 1],
                2 => ['percentage' => 5.00, 'custom_salary' => '', 'staff_count' => 1],
                3 => ['percentage' => 2.00, 'custom_salary' => '', 'staff_count' => 1],
                4 => ['percentage' => 5.00, 'custom_salary' => '', 'staff_count' => 1],
                5 => ['percentage' => 2.00, 'custom_salary' => '', 'staff_count' => 1],
            ],
        ];

        $response = $this->post(route('mssp.update', [$client->id, $scenario->id]), $postData);
        $response->assertRedirect(route('mssp.show', [$client->id, $scenario->id]));

        // Check DB updates
        $this->assertDatabaseHas('client_scenario_mssp_details', [
            'client_id' => $client->id,
            'scenario_id' => $scenario->id,
            'cloud_datacenter' => 'Dataxion',
        ]);

        // 3. Recalculate using engine to verify custom datacenter pricing matching
        $costDataAfter = $engine->calculate($client, $scenario);

        // Let's inspect the nodes. Since we matched Dataxion VM / disk, matched_vm_name and matched_disk_desc should be populated
        foreach ($costDataAfter['infrastructure']['nodes'] as $node) {
            $this->assertEquals('Dataxion', $node['cloud_datacenter']);
            $this->assertNotNull($node['matched_vm_name']);
            $this->assertNotNull($node['matched_disk_desc']);
            $this->assertGreaterThan(0, $node['ram_monthly_cost']);
            $this->assertGreaterThan(0, $node['storage_monthly_cost']);
        }
    }

    public function test_elastic_cloud_and_agent_calculations(): void
    {
        $client = Client::create(['name' => 'Elastic Cloud Test Client']);

        // Create assets mapped to various agent combinations:
        // AssetType 1 (Active Directory) -> SIEM (runs_siem_agent: true)
        ClientAsset::create([
            'client_id' => $client->id,
            'asset_type_id' => 1,
            'device_count' => 2,
        ]);

        // AssetType 2 (FortiGate) -> SIEM & MDR
        ClientAsset::create([
            'client_id' => $client->id,
            'asset_type_id' => 2,
            'device_count' => 1,
        ]);

        // AssetType 4 (Windows Server) -> SIEM & EDR
        ClientAsset::create([
            'client_id' => $client->id,
            'asset_type_id' => 4,
            'device_count' => 5,
        ]);

        $scenario = Scenario::first();

        /** @var MsspCostingEngine $engine */
        $engine = app(MsspCostingEngine::class);
        $costData = $engine->calculate($client, $scenario);

        $cloudData = $costData['cloud_option'];

        // Expected agent counts:
        // SIEM: 2 (AD) + 1 (FortiGate) + 5 (Windows Server) = 8
        // MDR: 1 (FortiGate) = 1
        // EDR: 5 (Windows Server) = 5
        $this->assertEquals(8, $cloudData['total_siem_count']);
        $this->assertEquals(1, $cloudData['total_mdr_count']);
        $this->assertEquals(5, $cloudData['total_edr_count']);

        // Check costs (using defaults: SIEM=$15, MDR=$30, EDR=$10)
        // SIEM monthly cost: 8 * 15 = 120
        // MDR monthly cost: 1 * 30 = 30
        // EDR monthly cost: 5 * 10 = 50
        // Total Agent Package cost: 120 + 30 + 50 = 200
        $this->assertEquals(120.00, $cloudData['siem_monthly_cost']);
        $this->assertEquals(30.00, $cloudData['mdr_monthly_cost']);
        $this->assertEquals(50.00, $cloudData['edr_monthly_cost']);
        $this->assertEquals(200.00, $cloudData['total_agents_monthly_cost']);

        // Check Elastic Cloud subscription cost calculations (node-by-node matching):
        $this->assertEquals(149.07, $cloudData['elastic_cloud_subscription_cost']);
        $this->assertNotEmpty($cloudData['matched_nodes']);

        // Assert simplified costing for Option B
        $this->assertEquals(200.00, $cloudData['total_monthly_service_cost']);
        $this->assertEquals(200.00, $cloudData['client_offered_price_mrc']);
        $this->assertEquals(0.00, $cloudData['total_profit_amount']);
    }

    public function test_client_asset_custom_agent_mapping_override(): void
    {
        $client = Client::create(['name' => 'Custom Mappings Client']);

        // Active Directory asset (runs_siem_agent default: true, runs_mdr_agent default: false, runs_edr_agent default: false)
        // We override runs_mdr_agent => true, runs_siem_agent => false
        $asset = ClientAsset::create([
            'client_id' => $client->id,
            'asset_type_id' => 1,
            'device_count' => 10,
            'runs_siem_agent' => false,
            'runs_mdr_agent' => true,
            'runs_edr_agent' => false,
        ]);

        $this->assertFalse($asset->runs_siem_agent);
        $this->assertTrue($asset->runs_mdr_agent);
        $this->assertFalse($asset->runs_edr_agent);

        $scenario = Scenario::first();

        /** @var MsspCostingEngine $engine */
        $engine = app(MsspCostingEngine::class);
        $costData = $engine->calculate($client, $scenario);
        $cloudData = $costData['cloud_option'];

        // Assert counts reflect custom overrides
        $this->assertEquals(0, $cloudData['total_siem_count']);
        $this->assertEquals(10, $cloudData['total_mdr_count']);
        $this->assertEquals(0, $cloudData['total_edr_count']);

        // Assert cloud option pricing is simplified
        $this->assertEquals(10 * 30.00, $cloudData['total_agents_monthly_cost']);
        $this->assertEquals($cloudData['total_agents_monthly_cost'], $cloudData['client_offered_price_mrc']);
        $this->assertEquals(0.00, $cloudData['total_profit_amount']);
    }
}
