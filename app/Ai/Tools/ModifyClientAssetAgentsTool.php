<?php

namespace App\Ai\Tools;

use App\Models\ClientAsset;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ModifyClientAssetAgentsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Modifies security agent configuration (runs_siem_agent, runs_mdr_agent, runs_edr_agent) on client assets in the database, directly changing their cost calculation.';
    }

    public function handle(Request $request): Stringable|string
    {
        $clientId = $request['client_id'] ?? null;
        $assetId = $request['asset_id'] ?? null;
        $assetTypeId = $request['asset_type_id'] ?? null;

        if (is_null($clientId)) {
            return 'Error: client_id is required.';
        }

        $query = ClientAsset::where('client_id', $clientId);

        if ($assetId) {
            $query->where('id', $assetId);
        } elseif ($assetTypeId) {
            $query->where('asset_type_id', $assetTypeId);
        } else {
            return 'Error: Must provide either asset_id or asset_type_id to target client assets.';
        }

        $assets = $query->get();

        if ($assets->isEmpty()) {
            return "No assets found matching client_id={$clientId} and the specified filters.";
        }

        $updates = [];
        if ($request->offsetExists('runs_siem_agent')) {
            $updates['runs_siem_agent'] = (bool) $request['runs_siem_agent'];
        }
        if ($request->offsetExists('runs_mdr_agent')) {
            $updates['runs_mdr_agent'] = (bool) $request['runs_mdr_agent'];
        }
        if ($request->offsetExists('runs_edr_agent')) {
            $updates['runs_edr_agent'] = (bool) $request['runs_edr_agent'];
        }

        if (empty($updates)) {
            return 'No agent updates (runs_siem_agent, runs_mdr_agent, runs_edr_agent) were specified in arguments.';
        }

        foreach ($assets as $asset) {
            $asset->update($updates);
        }

        return 'Successfully updated '.$assets->count()." assets for client ID {$clientId} with parameters: ".json_encode($updates);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->description('The ID of the target client.')->required(),
            'asset_id' => $schema->integer()->description('Optional ID of a specific asset to update.'),
            'asset_type_id' => $schema->integer()->description('Optional ID of an asset type to update all client assets of that type.'),
            'runs_siem_agent' => $schema->boolean()->description('Enable (true) or disable (false) SIEM agent.'),
            'runs_mdr_agent' => $schema->boolean()->description('Enable (true) or disable (false) MDR agent.'),
            'runs_edr_agent' => $schema->boolean()->description('Enable (true) or disable (false) EDR agent.'),
        ];
    }
}
