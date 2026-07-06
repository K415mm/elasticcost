<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientAsset;
use App\Models\Scenario;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SizingExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->ceo()->create();
        $this->actingAs($user);
    }

    public function test_sizing_export_excel_returns_success_headers(): void
    {
        $client = Client::create(['name' => 'Acme Corp']);

        // Add basic assets
        ClientAsset::create([
            'client_id' => $client->id,
            'asset_type_id' => 1,
            'device_count' => 2,
        ]);

        $scenario = Scenario::find(2);

        $response = $this->get(route('sizing.export.excel', [$client->id, $scenario->id]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertHeader('Content-Disposition', 'attachment; filename="acme_corp_sizing_model.xlsx"');
    }

    public function test_sizing_export_markdown_returns_success_headers(): void
    {
        $client = Client::create(['name' => 'Acme Corp']);

        ClientAsset::create([
            'client_id' => $client->id,
            'asset_type_id' => 1,
            'device_count' => 2,
        ]);

        $scenario = Scenario::find(2);

        $response = $this->get(route('sizing.export.markdown', [$client->id, $scenario->id]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="acme_corp_scenario_2_report.md"');
    }

    public function test_sizing_export_word_returns_success_headers(): void
    {
        $client = Client::create(['name' => 'Acme Corp']);

        ClientAsset::create([
            'client_id' => $client->id,
            'asset_type_id' => 1,
            'device_count' => 2,
        ]);

        $scenario = Scenario::find(2);

        $response = $this->get(route('sizing.export.word', [$client->id, $scenario->id]));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/msword', $response->headers->get('Content-Type'));
        $response->assertHeader('Content-Disposition', 'attachment; filename="acme_corp_scenario_2_report.doc"');
    }

    public function test_sizing_export_excel_with_tnd_currency(): void
    {
        $client = Client::create(['name' => 'Acme Corp']);
        $scenario = Scenario::find(2);

        $response = $this->withSession(['currency' => 'TND'])
            ->get(route('sizing.export.excel', [$client->id, $scenario->id]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_sizing_export_markdown_with_tnd_currency(): void
    {
        $client = Client::create(['name' => 'Acme Corp']);
        $scenario = Scenario::find(2);

        $response = $this->withSession(['currency' => 'TND'])
            ->get(route('sizing.export.markdown', [$client->id, $scenario->id]));

        $response->assertStatus(200);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('TND', $content);
    }
}
