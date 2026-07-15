<?php

namespace App\Ai\Tools;

use App\Models\Client;
use App\Models\Diagram;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateDrawioDiagramTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Create or update a draw.io architecture/network diagram (.drawio XML) for a client and optionally a scenario. Generates and stores the diagram inside the system.';
    }

    public function handle(Request $request): Stringable|string
    {
        $clientId = $request['client_id'] ?? null;
        $scenarioId = $request['scenario_id'] ?? null;
        $name = $request['diagram_name'] ?? null;
        $type = $request['diagram_type'] ?? 'custom';
        $xmlContent = $request['drawio_xml'] ?? '';

        if (empty($clientId) || empty($name) || empty($xmlContent)) {
            return json_encode([
                'status' => 'error',
                'message' => 'Missing required arguments: client_id, diagram_name, and drawio_xml must be provided.',
            ]);
        }

        $client = Client::find($clientId);
        if (! $client) {
            return json_encode([
                'status' => 'error',
                'message' => "Client with ID {$clientId} not found.",
            ]);
        }

        try {
            // Find existing diagram by client, scenario, and name to update it rather than duplicating
            $diagram = Diagram::where('client_id', $clientId)
                ->where('scenario_id', $scenarioId)
                ->where('name', $name)
                ->first();

            if ($diagram) {
                $diagram->update([
                    'type' => $type,
                    'content' => $xmlContent,
                ]);
                $message = 'Diagram updated successfully.';
            } else {
                $diagram = Diagram::create([
                    'client_id' => $clientId,
                    'scenario_id' => $scenarioId,
                    'name' => $name,
                    'type' => $type,
                    'content' => $xmlContent,
                    'created_by' => Auth::id(),
                ]);
                $message = 'Diagram created successfully.';
            }

            $viewUrl = route('clients.diagrams.show', [$clientId, $diagram->id]);

            return json_encode([
                'status' => 'success',
                'message' => $message,
                'data' => [
                    'id' => $diagram->id,
                    'name' => $diagram->name,
                    'type' => $diagram->type,
                    'view_url' => $viewUrl,
                ],
            ]);
        } catch (\Throwable $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Database error while saving diagram: '.$e->getMessage(),
            ]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->description('The ID of the client to associate this diagram with.')->required(),
            'scenario_id' => $schema->integer()->description('Optional ID of the scenario template to associate this diagram with.'),
            'diagram_name' => $schema->string()->description('Descriptive name for the diagram (e.g. AWS Multi-AZ Network Diagram).')->required(),
            'diagram_type' => $schema->string()->description('The category classification of the diagram.')->enum(['soc_architecture', 'deployment_topology', 'network_diagram', 'custom']),
            'drawio_xml' => $schema->string()->description('The complete, valid, raw .drawio XML code representing the diagram structures, cells, shapes, and connections.')->required(),
        ];
    }
}
