<?php

namespace Tests\Feature;

use App\Models\AssetType;
use App\Models\Client;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAssetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Test bulk update of client assets works as expected.
     */
    public function test_can_bulk_update_client_assets(): void
    {
        $client = Client::create([
            'name' => 'Acme Bulk Client',
            'description' => 'A test client for bulk inventory changes',
        ]);

        $assetTypes = AssetType::all();
        $assets = [];
        foreach ($assetTypes as $type) {
            $assets[] = $client->clientAssets()->create([
                'asset_type_id' => $type->id,
                'device_count' => 10,
                'runs_siem_agent' => false,
                'runs_mdr_agent' => false,
                'runs_edr_agent' => false,
            ]);
        }

        $this->assertCount(6, $assets);

        // Prep the payload: Update Active Directory (type 1) and Windows Servers (type 4)
        $adAsset = $assets[0]; // Active Directory (ID: 1)
        $winAsset = $assets[3]; // Windows Servers (ID: 4)

        $payload = [
            'assets' => [
                $adAsset->id => [
                    'device_count' => 150,
                    'runs_siem_agent' => '1',
                    'runs_mdr_agent' => '1',
                    // custom overrides
                    'custom_avg_event_size_bytes' => 1100,
                    'custom_min_eps' => '3.50',
                    'custom_avg_eps' => '15.00',
                    'custom_max_eps' => '35.00',
                ],
                $winAsset->id => [
                    'device_count' => 200,
                    'runs_edr_agent' => '1',
                    // custom overrides
                    'custom_avg_event_size_bytes' => '', // should become null
                    'custom_min_eps' => '',
                    'custom_avg_eps' => '',
                    'custom_max_eps' => '',
                ],
            ],
        ];

        $response = $this->put(route('client-assets.update-bulk', $client->id), $payload);

        $response->assertRedirect(route('clients.show', $client->id));
        $response->assertSessionHas('success', 'Inventory updated successfully.');

        // Verify database updates
        $adAsset->refresh();
        $this->assertEquals(150, $adAsset->device_count);
        $this->assertTrue($adAsset->runs_siem_agent);
        $this->assertTrue($adAsset->runs_mdr_agent);
        $this->assertFalse($adAsset->runs_edr_agent);
        $this->assertEquals(1100, $adAsset->custom_avg_event_size_bytes);
        $this->assertEquals(3.50, (float) $adAsset->custom_min_eps);
        $this->assertEquals(15.00, (float) $adAsset->custom_avg_eps);
        $this->assertEquals(35.00, (float) $adAsset->custom_max_eps);

        $winAsset->refresh();
        $this->assertEquals(200, $winAsset->device_count);
        $this->assertFalse($winAsset->runs_siem_agent);
        $this->assertFalse($winAsset->runs_mdr_agent);
        $this->assertTrue($winAsset->runs_edr_agent);
        $this->assertNull($winAsset->custom_avg_event_size_bytes);
        $this->assertNull($winAsset->custom_min_eps);
        $this->assertNull($winAsset->custom_avg_eps);
        $this->assertNull($winAsset->custom_max_eps);
    }
}
