<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\ClientAsset;
use App\Models\Scenario;
use App\Services\SizingEngine;
use Database\Seeders\SizingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SizingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed the default parameters and scenarios
        $this->seed(SizingSeeder::class);
    }

    public function test_sizing_engine_calculates_all_six_scenarios_correctly(): void
    {
        // 1. Create a Client
        $client = Client::create([
            'name' => 'Client ttt',
            'description' => 'Test Sizing Client',
        ]);

        // 2. Add client asset inventory matching "client_ttt_sizing_report.md"
        // - Active Directory: 2
        // - FortiGate Firewalls: 2
        // - Network Switches: 10
        // - Windows Servers: 20
        // - Linux Servers: 10
        // - EDR / XDR Integration: 150
        $assets = [
            1 => 2,   // Active Directory
            2 => 2,   // FortiGate Firewalls
            3 => 150, // EDR / XDR Integration
            4 => 20,  // Windows Servers
            5 => 10,  // Linux Servers
            6 => 10,  // Network Switches
        ];

        foreach ($assets as $typeId => $count) {
            ClientAsset::create([
                'client_id' => $client->id,
                'asset_type_id' => $typeId,
                'device_count' => $count,
            ]);
        }

        $engine = new SizingEngine;

        // SCENARIO 1: Minimum Ingest, Hot-Only (Short Retention)
        $scenario1 = Scenario::find(1);
        $res1 = $engine->calculate($client, $scenario1);
        $this->assertEqualsWithDelta(3.32, $res1['totals']['daily_raw_gb'], 0.2);
        $this->assertEqualsWithDelta(4.15, $res1['totals']['daily_indexed_gb'], 0.2);
        $this->assertEqualsWithDelta(8.30, $res1['totals']['daily_ingested_gb'], 0.2);
        $this->assertEqualsWithDelta(58.09, $res1['totals']['total_storage_footprint_gb'], 0.2);
        $this->assertEquals(7, $res1['licensing']['total_ram_gb']);
        $this->assertEquals(1, $res1['licensing']['required_erus']);

        // SCENARIO 2: Minimum Ingest, Multi-Tier (Long Retention)
        $scenario2 = Scenario::find(2);
        $res2 = $engine->calculate($client, $scenario2);
        $this->assertEqualsWithDelta(3.32, $res2['totals']['daily_raw_gb'], 0.2);
        $this->assertEqualsWithDelta(248.97, $res2['totals']['hot_storage_gb'], 0.5);
        $this->assertEqualsWithDelta(2780.14, $res2['totals']['cold_storage_gb'], 1.0);
        $this->assertEqualsWithDelta(3029.11, $res2['totals']['total_storage_footprint_gb'], 2.0);
        $this->assertEquals(62, $res2['licensing']['total_ram_gb']);
        $this->assertEquals(1, $res2['licensing']['required_erus']);

        // SCENARIO 3: Average Ingest, Hot-Only (Standard Retention)
        $scenario3 = Scenario::find(3);
        $res3 = $engine->calculate($client, $scenario3);
        $this->assertEqualsWithDelta(19.19, $res3['totals']['daily_raw_gb'], 0.2);
        $this->assertEqualsWithDelta(23.99, $res3['totals']['daily_indexed_gb'], 0.2);
        $this->assertEqualsWithDelta(1439.25, $res3['totals']['total_storage_footprint_gb'], 2.0);
        $this->assertEquals(120, $res3['licensing']['total_ram_gb']);
        $this->assertEquals(2, $res3['licensing']['required_erus']);

        // SCENARIO 4: Average Ingest, Multi-Tier (Long Retention)
        $scenario4 = Scenario::find(4);
        $res4 = $engine->calculate($client, $scenario4);
        $this->assertEqualsWithDelta(19.19, $res4['totals']['daily_raw_gb'], 0.2);
        $this->assertEqualsWithDelta(335.82, $res4['totals']['hot_storage_gb'], 1.0);
        $this->assertEqualsWithDelta(1103.42, $res4['totals']['warm_storage_gb'], 2.0);
        $this->assertEqualsWithDelta(2878.49, $res4['totals']['cold_storage_gb'], 2.0);
        $this->assertEqualsWithDelta(13193.10, $res4['totals']['frozen_storage_gb'], 5.0);
        $this->assertEqualsWithDelta(17510.84, $res4['totals']['total_storage_footprint_gb'], 5.0);
        $this->assertEquals(116, $res4['licensing']['total_ram_gb']);
        $this->assertEquals(2, $res4['licensing']['required_erus']);

        // SCENARIO 5: Maximum Ingest, Hot + Warm (Medium Retention)
        $scenario5 = Scenario::find(5);
        $res5 = $engine->calculate($client, $scenario5);
        $this->assertEqualsWithDelta(66.19, $res5['totals']['daily_raw_gb'], 0.2);
        $this->assertEqualsWithDelta(82.74, $res5['totals']['daily_indexed_gb'], 0.2);
        $this->assertEqualsWithDelta(2316.72, $res5['totals']['hot_storage_gb'], 5.0);
        $this->assertEqualsWithDelta(12576.48, $res5['totals']['warm_storage_gb'], 10.0);
        $this->assertEqualsWithDelta(14893.20, $res5['totals']['total_storage_footprint_gb'], 10.0);
        $this->assertEquals(392, $res5['licensing']['total_ram_gb']);
        $this->assertEquals(7, $res5['licensing']['required_erus']);

        // SCENARIO 6: Maximum Ingest, Multi-Tier (Long Retention)
        $scenario6 = Scenario::find(6);
        $res6 = $engine->calculate($client, $scenario6);
        $this->assertEqualsWithDelta(66.19, $res6['totals']['daily_raw_gb'], 0.2);
        $this->assertEqualsWithDelta(2316.72, $res6['totals']['hot_storage_gb'], 5.0);
        $this->assertEqualsWithDelta(2647.68, $res6['totals']['warm_storage_gb'], 5.0);
        $this->assertEqualsWithDelta(9928.80, $res6['totals']['cold_storage_gb'], 5.0);
        $this->assertEqualsWithDelta(45507.00, $res6['totals']['frozen_storage_gb'], 10.0);
        $this->assertEqualsWithDelta(60400.20, $res6['totals']['total_storage_footprint_gb'], 15.0);
        $this->assertEquals(584, $res6['licensing']['total_ram_gb']);
        $this->assertEquals(10, $res6['licensing']['required_erus']);
    }

    public function test_sizing_engine_scales_up_for_large_storage_volumes(): void
    {
        // 1. Create a Client
        $client = Client::create([
            'name' => 'High Volume Client',
            'description' => 'Test Sizing Client with High Volume',
        ]);

        // 2. Add inventory containing 300 Active Directory servers and the standard default seed items
        $assets = [
            1 => 300, // 300 Active Directory (about 90 GB/day raw on min EPS default)
            2 => 2,
            3 => 150,
            4 => 20,
            5 => 10,
            6 => 10,
        ];

        foreach ($assets as $typeId => $count) {
            ClientAsset::create([
                'client_id' => $client->id,
                'asset_type_id' => $typeId,
                'device_count' => $count,
            ]);
        }

        $engine = new SizingEngine;

        // Run Scenario 2 (Minimum Ingest, Multi-Tier: 30 Hot days, 1 Hot replica)
        $scenario2 = Scenario::find(2);
        $res = $engine->calculate($client, $scenario2);

        // Raw daily is around 92.7 GB/day
        $this->assertEqualsWithDelta(92.72, $res['totals']['daily_raw_gb'], 0.5);

        // Sizing per node:
        // Daily indexed = 92.72 * 1.25 = 115.9 GB/day.
        // Hot storage = 115.9 * 2 * 30 = 6,954 GB.
        // Per node = 3,477 GB.
        // With 20% headroom = 4,172 GB.
        // Neat disk size = 4,500 GB.
        // Needed RAM = 4500 / 30 = 150 GB RAM per node.
        // Nearest VM profile is 128 GB.

        $hotNode = collect($res['nodes'])->firstWhere('name', 'hot-node-01');
        $this->assertNotNull($hotNode);
        // It must scale up to 128 GB RAM (bypassing the default 24 GB cap)
        $this->assertEquals(128.0, $hotNode['ram_gb']);
        $this->assertEquals(4500.0, $hotNode['storage_gb']);
    }
}
