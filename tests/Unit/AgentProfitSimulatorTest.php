<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\ClientScenarioMsspDetail;
use App\Services\AgentProfitSimulatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentProfitSimulatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_profit_simulator_calculates_capacity_capping_correctly(): void
    {
        $service = new AgentProfitSimulatorService;

        $client = new Client;
        $msspDetail = new ClientScenarioMsspDetail([
            'edr_agent_monthly_cost_per_device' => 10.0,
            'mdr_agent_monthly_cost_per_device' => 30.0,
            'siem_agent_monthly_cost_per_device' => 15.0,
        ]);

        $customParams = [
            'edr_base_cost' => 10.0,
            'edr_partner_price' => 20.0,
            'edr_client_price' => 25.0,
            'edr_purchased_limit' => 100,
            'edr_monthly_growth' => 20,

            'mdr_base_cost' => 30.0,
            'mdr_partner_price' => 45.0,
            'mdr_client_price' => 60.0,
            'mdr_purchased_limit' => 50,
            'mdr_monthly_growth' => 10,

            'siem_base_cost' => 15.0,
            'siem_partner_price' => 25.0,
            'siem_client_price' => 35.0,
            'siem_purchased_limit' => 100,
            'siem_monthly_growth' => 15,
        ];

        $result = $service->calculate($client, $msspDetail, $customParams);

        $this->assertArrayHasKey('timeline', $result);
        $this->assertCount(36, $result['timeline']);

        // Month 1: Initial (0) + (1-1)*growth = 0
        $m1 = $result['timeline'][1];
        $this->assertEquals(0, $m1['total_deployed']);
        $this->assertFalse($m1['is_fully_sold_out']);

        // Month 6: EDR = min(0 + 5*20, 100) = 100 (SOLD OUT)
        // MDR = min(0 + 5*10, 50) = 50 (SOLD OUT)
        // SIEM = min(0 + 5*15, 100) = 75
        $m6 = $result['timeline'][6];
        $this->assertEquals(100, $m6['edr_deployed']);
        $this->assertEquals(50, $m6['mdr_deployed']);
        $this->assertEquals(75, $m6['siem_deployed']);

        // Month 12: All reached limit (EDR: 100, MDR: 50, SIEM: 100)
        $m12 = $result['timeline'][12];
        $this->assertEquals(100, $m12['edr_deployed']);
        $this->assertEquals(50, $m12['mdr_deployed']);
        $this->assertEquals(100, $m12['siem_deployed']);
        $this->assertTrue($m12['is_fully_sold_out']);

        // Check horizon summary keys
        $this->assertArrayHasKey(1, $result['horizons']);
        $this->assertArrayHasKey(3, $result['horizons']);
        $this->assertArrayHasKey(6, $result['horizons']);
        $this->assertArrayHasKey(12, $result['horizons']);
        $this->assertArrayHasKey(36, $result['horizons']);
    }
}
