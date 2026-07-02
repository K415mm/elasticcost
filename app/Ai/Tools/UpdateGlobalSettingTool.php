<?php

namespace App\Ai\Tools;

use App\Models\GlobalSetting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateGlobalSettingTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Updates a key-value pair in global settings. E.g. setting exchange rates or agent costing rates.';
    }

    public function handle(Request $request): Stringable|string
    {
        $key = $request['key'] ?? null;
        $value = $request['value'] ?? null;
        $description = $request['description'] ?? ucfirst(str_replace('_', ' ', (string) $key)).' setting';

        if (is_null($key) || is_null($value)) {
            return 'Error: key and value parameters are required.';
        }

        $setting = GlobalSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
                'description' => $description,
            ]
        );

        return "Successfully updated setting '{$key}' to '{$value}'.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->description('The settings key to update. E.g. usd_to_eur_rate, siem_agent_monthly_cost_per_device.')->required(),
            'value' => $schema->string()->description('The new string/numeric value for this setting.')->required(),
            'description' => $schema->string()->description('Optional brief description of what this setting represents.'),
        ];
    }
}
