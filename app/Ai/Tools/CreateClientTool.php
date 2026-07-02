<?php

namespace App\Ai\Tools;

use App\Models\AssetType;
use App\Models\Client;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateClientTool implements Tool
{
    /**
     * Get the description of the tool.
     */
    public function description(): Stringable|string
    {
        return 'Creates a new client in the database and pre-populates/configures their asset inventory with device counts and custom agent coverages.';
    }

    /**
     * Handle the execution of the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $name = $request['name'] ?? null;
        $description = $request['description'] ?? null;

        if (empty($name)) {
            return 'Error: Client name is required.';
        }

        // Create the client
        $client = Client::create([
            'name' => $name,
            'description' => $description,
        ]);

        $deviceCounts = [];
        if (! empty($request['device_counts'])) {
            $deviceCounts = json_decode($request['device_counts'], true);
            if (! is_array($deviceCounts)) {
                $deviceCounts = [];
            }
        }

        $agentCoverages = [];
        if (! empty($request['agent_coverages'])) {
            $agentCoverages = json_decode($request['agent_coverages'], true);
            if (! is_array($agentCoverages)) {
                $agentCoverages = [];
            }
        }

        $assetTypes = AssetType::all();
        $createdAssets = [];

        foreach ($assetTypes as $type) {
            // Check if device count is provided for this asset type ID or name
            $count = 0;
            if (isset($deviceCounts[$type->id])) {
                $count = (int) $deviceCounts[$type->id];
            } elseif (isset($deviceCounts[$type->name])) {
                $count = (int) $deviceCounts[$type->name];
            }

            // Get custom or default agent coverages
            $coverage = $agentCoverages[$type->id] ?? $agentCoverages[$type->name] ?? [];
            $runsSiem = isset($coverage['runs_siem_agent']) ? (bool) $coverage['runs_siem_agent'] : (bool) $type->runs_siem_agent;
            $runsMdr = isset($coverage['runs_mdr_agent']) ? (bool) $coverage['runs_mdr_agent'] : (bool) $type->runs_mdr_agent;
            $runsEdr = isset($coverage['runs_edr_agent']) ? (bool) $coverage['runs_edr_agent'] : (bool) $type->runs_edr_agent;

            $asset = $client->clientAssets()->create([
                'asset_type_id' => $type->id,
                'device_count' => $count,
                'runs_siem_agent' => $runsSiem,
                'runs_mdr_agent' => $runsMdr,
                'runs_edr_agent' => $runsEdr,
            ]);

            $createdAssets[] = [
                'asset_id' => $asset->id,
                'asset_type_name' => $type->name,
                'device_count' => $count,
                'runs_siem' => $runsSiem,
                'runs_mdr' => $runsMdr,
                'runs_edr' => $runsEdr,
            ];
        }

        $summary = [
            'client_id' => $client->id,
            'client_name' => $client->name,
            'client_description' => $client->description,
            'inventory' => $createdAssets,
        ];

        return "Successfully created client '{$client->name}' with ID {$client->id} and pre-populated inventory: ".json_encode($summary, JSON_PRETTY_PRINT);
    }

    /**
     * Get the JSON schema for the tool.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The name of the new client to create.')->required(),
            'description' => $schema->string()->description('Optional description of the client.'),
            'device_counts' => $schema->string()->description('Optional JSON encoded string mapping asset_type_id (string/integer) to device_count (integer). E.g. "{\"1\":150,\"4\":200}"'),
            'agent_coverages' => $schema->string()->description('Optional JSON encoded string mapping asset_type_id (string/integer) to agent coverage boolean values. E.g. "{\"1\":{\"runs_siem_agent\":true,\"runs_mdr_agent\":false,\"runs_edr_agent\":false}}"'),
        ];
    }
}
