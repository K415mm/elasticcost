<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Scenario;
use App\Models\ClientAsset;
use App\Models\SocRole;
use App\Models\ClientScenarioMsspDetail;
use App\Models\ClientScenarioAnalystAllocation;
use App\Services\MsspCostingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Ai\Agents\OfferAnalyst;

class MsspCostingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed both standard sizing and MSSP role templates
        $this->seed(\Database\Seeders\SizingSeeder::class);
        $this->seed(\Database\Seeders\MsspSeeder::class);
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
            'allocations' => [
                1 => ['percentage' => 20.00, 'custom_salary' => 4500.00, 'staff_count' => 1], // Override L1
                2 => ['percentage' => 10.00, 'custom_salary' => '', 'staff_count' => 1],
                3 => ['percentage' => 0.00, 'custom_salary' => '', 'staff_count' => 1],
                4 => ['percentage' => 0.00, 'custom_salary' => '', 'staff_count' => 1],
                5 => ['percentage' => 0.00, 'custom_salary' => '', 'staff_count' => 1],
            ]
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
            'is_license_shared' => '1',
            'license_share_percentage' => 45.50,
            'allocations' => [
                1 => ['percentage' => 10.00, 'custom_salary' => '', 'staff_count' => 3],
                2 => ['percentage' => 5.00, 'custom_salary' => '', 'staff_count' => 2],
                3 => ['percentage' => 2.00, 'custom_salary' => '', 'staff_count' => 1],
                4 => ['percentage' => 5.00, 'custom_salary' => '', 'staff_count' => 1],
                5 => ['percentage' => 2.00, 'custom_salary' => '', 'staff_count' => 1],
            ]
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
            ]
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
        $responseMd->assertHeader('Content-Disposition', 'attachment; filename="' . strtolower(str_replace(' ', '_', $client->name)) . '_mssp_cost_proposal.md"');

        // 2. Export Word
        $responseDoc = $this->get(route('mssp.export.word', [$client->id, $scenario->id]));
        $responseDoc->assertStatus(200);
        $responseDoc->assertHeader('Content-Type', 'application/msword');
        $responseDoc->assertHeader('Content-Disposition', 'attachment; filename="' . strtolower(str_replace(' ', '_', $client->name)) . '_mssp_cost_proposal.doc"');

        // 3. Export Excel
        $responseXls = $this->get(route('mssp.export.excel', [$client->id, $scenario->id]));
        $responseXls->assertStatus(200);
        $responseXls->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $responseXls->assertHeader('Content-Disposition', 'attachment; filename="' . strtolower(str_replace(' ', '_', $client->name)) . '_mssp_cost_proposal.xlsx"');
    }

    public function test_mssp_costing_ask_ai_successful(): void
    {
        $client = Client::create(['name' => 'Acme AI Test client']);
        $scenario = Scenario::first();

        // Fake the AI agent response
        OfferAnalyst::fake(['Mock AI Analysis Output from Gemma']);

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

        // Fake a failure by making the agent throw an exception when prompted
        OfferAnalyst::fake(function() {
            throw new \Exception('Connection refused to local Ollama');
        });

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
            'ai_analysis' => 'Special AI Report Section Content'
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
            'allocations' => [
                1 => ['percentage' => 10.00, 'custom_salary' => 12400.00, 'staff_count' => 1], // 4000 * 3.10
                2 => ['percentage' => 5.00, 'custom_salary' => '', 'staff_count' => 1],
                3 => ['percentage' => 2.00, 'custom_salary' => '', 'staff_count' => 1],
                4 => ['percentage' => 5.00, 'custom_salary' => '', 'staff_count' => 1],
                5 => ['percentage' => 2.00, 'custom_salary' => '', 'staff_count' => 1],
            ]
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
}
