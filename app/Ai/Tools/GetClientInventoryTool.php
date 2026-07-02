<?php

namespace App\Ai\Tools;

use App\Models\ClientAsset;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetClientInventoryTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Retrieves the complete asset inventory and agent mapping (runs_siem_agent, runs_mdr_agent, runs_edr_agent) for a specific client.';
    }

    public function handle(Request $request): Stringable|string
    {
        $clientId = $request['client_id'] ?? null;
        if (is_null($clientId)) {
            return 'Error: client_id is required.';
        }

        $assets = ClientAsset::with('assetType')
            ->where('client_id', $clientId)
            ->get()
            ->map(fn ($a) => [
                'asset_id' => $a->id,
                'asset_type_name' => $a->assetType->name ?? 'Unknown',
                'device_count' => $a->device_count,
                'runs_siem_agent' => $a->runs_siem_agent,
                'runs_mdr_agent' => $a->runs_mdr_agent,
                'runs_edr_agent' => $a->runs_edr_agent,
                'custom_max_monthly_gb' => $a->custom_max_monthly_gb,
            ])
            ->toArray();

        if (empty($assets)) {
            return "No assets/inventory found for client ID {$clientId}.";
        }

        return json_encode($assets, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->description('The client ID whose asset inventory is requested.')->required(),
        ];
    }
}
