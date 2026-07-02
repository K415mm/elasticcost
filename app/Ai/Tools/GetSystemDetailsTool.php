<?php

namespace App\Ai\Tools;

use App\Models\AssetType;
use App\Models\Client;
use App\Models\GlobalSetting;
use App\Models\Scenario;
use App\Models\SocRole;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetSystemDetailsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Exposes system settings, active rates, scenario IDs, active clients, SOC roles, and current agent costs to make the agent context-aware.';
    }

    public function handle(Request $request): Stringable|string
    {
        $settings = GlobalSetting::all()->pluck('value', 'key')->toArray();
        $clients = Client::all()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->toArray();
        $scenarios = Scenario::all()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->toArray();
        $roles = SocRole::all()->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'default_salary' => $r->default_monthly_salary])->toArray();
        $assetTypes = AssetType::all()->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'calibration_mode' => $t->calibration_mode, 'description' => $t->description])->toArray();

        return json_encode([
            'settings' => $settings,
            'clients' => $clients,
            'scenarios' => $scenarios,
            'soc_roles' => $roles,
            'asset_types' => $assetTypes,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return []; // No arguments required
    }
}
