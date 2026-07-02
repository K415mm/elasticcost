<?php

namespace App\Ai\Tools;

use App\Models\ClientAsset;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateClientInventoryTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Updates the device_count for client assets in the inventory database. Can target a specific asset by asset_id, or all assets of a given asset_type_id for a client.';
    }

    public function handle(Request $request): Stringable|string
    {
        $clientId = $request['client_id'] ?? null;
        $assetId = $request['asset_id'] ?? null;
        $assetTypeId = $request['asset_type_id'] ?? null;
        $deviceCount = $request['device_count'] ?? null;

        if (is_null($clientId)) {
            return 'Error: client_id is required.';
        }

        if (is_null($deviceCount) || ! is_numeric($deviceCount) || (int) $deviceCount < 0) {
            return 'Error: device_count must be a non-negative integer.';
        }

        $query = ClientAsset::where('client_id', $clientId);

        if ($assetId) {
            $query->where('id', $assetId);
        } elseif ($assetTypeId) {
            $query->where('asset_type_id', $assetTypeId);
        } else {
            return 'Error: Must provide either asset_id or asset_type_id to target client assets.';
        }

        $assets = $query->with('assetType')->get();

        if ($assets->isEmpty()) {
            return "No assets found matching client_id={$clientId} with the specified filters.";
        }

        foreach ($assets as $asset) {
            $asset->update(['device_count' => (int) $deviceCount]);
        }

        $summary = $assets->map(fn ($a) => [
            'asset_id' => $a->id,
            'asset_type' => $a->assetType->name ?? 'Unknown',
            'new_device_count' => (int) $deviceCount,
        ])->toArray();

        return 'Successfully updated device_count to '.(int) $deviceCount.' for '.$assets->count()." asset(s) on client ID {$clientId}. Updated assets: ".json_encode($summary);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->description('The ID of the target client.')->required(),
            'asset_id' => $schema->integer()->description('Optional ID of a specific asset to update.'),
            'asset_type_id' => $schema->integer()->description('Optional ID of an asset type — updates all client assets of that type.'),
            'device_count' => $schema->integer()->description('The new device count to set (must be >= 0).')->required(),
        ];
    }
}
