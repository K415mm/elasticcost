<?php

namespace Tests\Feature;

use App\Ai\Tools\UpdateClientInventoryTool;
use App\Models\AssetType;
use App\Models\Client;
use App\Models\ClientAsset;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class UpdateClientInventoryToolTest extends TestCase
{
    use RefreshDatabase;

    protected $client;

    protected $assetType1;

    protected $assetType2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->client = Client::create([
            'name' => 'Test Client Inventory',
            'description' => 'A test client for inventory updates',
        ]);

        $this->assetType1 = AssetType::first();
        $this->assetType2 = AssetType::skip(1)->first() ?? AssetType::factory()->create();
    }

    public function test_updates_device_count_by_asset_type_id(): void
    {
        $asset1 = ClientAsset::create([
            'client_id' => $this->client->id,
            'asset_type_id' => $this->assetType1->id,
            'device_count' => 10,
        ]);

        $asset2 = ClientAsset::create([
            'client_id' => $this->client->id,
            'asset_type_id' => $this->assetType2->id,
            'device_count' => 20,
        ]);

        $tool = new UpdateClientInventoryTool;
        $response = $tool->handle(new Request([
            'client_id' => $this->client->id,
            'asset_type_id' => $this->assetType1->id,
            'device_count' => 150,
        ]));

        $this->assertStringContainsString('Successfully updated device_count to 150', $response);

        $asset1->refresh();
        $asset2->refresh();

        $this->assertEquals(150, $asset1->device_count);
        $this->assertEquals(20, $asset2->device_count);
    }

    public function test_updates_device_count_by_asset_id(): void
    {
        $asset = ClientAsset::create([
            'client_id' => $this->client->id,
            'asset_type_id' => $this->assetType1->id,
            'device_count' => 10,
        ]);

        $tool = new UpdateClientInventoryTool;
        $response = $tool->handle(new Request([
            'client_id' => $this->client->id,
            'asset_id' => $asset->id,
            'device_count' => 99,
        ]));

        $this->assertStringContainsString('Successfully updated device_count to 99', $response);

        $asset->refresh();
        $this->assertEquals(99, $asset->device_count);
    }

    public function test_error_missing_client_id(): void
    {
        $tool = new UpdateClientInventoryTool;
        $response = $tool->handle(new Request([
            'asset_type_id' => $this->assetType1->id,
            'device_count' => 10,
        ]));

        $this->assertStringContainsString('Error: client_id is required.', $response);
    }

    public function test_error_missing_identifiers(): void
    {
        $tool = new UpdateClientInventoryTool;
        $response = $tool->handle(new Request([
            'client_id' => $this->client->id,
            'device_count' => 10,
        ]));

        $this->assertStringContainsString('Error: Must provide either asset_id or asset_type_id to target client assets.', $response);
    }

    public function test_error_negative_device_count(): void
    {
        $tool = new UpdateClientInventoryTool;
        $response = $tool->handle(new Request([
            'client_id' => $this->client->id,
            'asset_id' => 1,
            'device_count' => -5,
        ]));

        $this->assertStringContainsString('Error: device_count must be a non-negative integer.', $response);
    }

    public function test_returns_correct_summary_format(): void
    {
        $asset = ClientAsset::create([
            'client_id' => $this->client->id,
            'asset_type_id' => $this->assetType1->id,
            'device_count' => 50,
        ]);

        $tool = new UpdateClientInventoryTool;
        $response = $tool->handle(new Request([
            'client_id' => $this->client->id,
            'asset_id' => $asset->id,
            'device_count' => 200,
        ]));

        $this->assertStringContainsString('Successfully updated device_count to 200 for 1 asset(s)', $response);
        $this->assertStringContainsString('Updated assets:', $response);
        $this->assertStringContainsString('"new_device_count":200', $response);
    }
}
