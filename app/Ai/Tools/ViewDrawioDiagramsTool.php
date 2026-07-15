<?php

namespace App\Ai\Tools;

use App\Models\Client;
use App\Models\Diagram;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ViewDrawioDiagramsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'List all diagram templates and files for a client, or fetch the full XML code of a specific diagram to analyze or modify it.';
    }

    public function handle(Request $request): Stringable|string
    {
        $clientId = $request['client_id'] ?? null;
        $diagramId = $request['diagram_id'] ?? null;

        if (empty($clientId)) {
            return json_encode([
                'status' => 'error',
                'message' => 'Missing required argument: client_id must be provided.',
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
            if (! empty($diagramId)) {
                // Fetch specific diagram
                $diagram = Diagram::where('client_id', $clientId)->find($diagramId);
                if (! $diagram) {
                    return json_encode([
                        'status' => 'error',
                        'message' => "Diagram with ID {$diagramId} not found for this client.",
                    ]);
                }

                return json_encode([
                    'status' => 'success',
                    'data' => [
                        'id' => $diagram->id,
                        'name' => $diagram->name,
                        'type' => $diagram->type,
                        'content' => $diagram->content,
                        'created_at' => $diagram->created_at->toIso8601String(),
                        'updated_at' => $diagram->updated_at->toIso8601String(),
                    ],
                ]);
            }

            // List all diagrams
            $diagrams = Diagram::where('client_id', $clientId)
                ->select(['id', 'name', 'type', 'scenario_id', 'created_at', 'updated_at'])
                ->with('scenario:id,name')
                ->orderBy('updated_at', 'desc')
                ->get();

            return json_encode([
                'status' => 'success',
                'count' => $diagrams->count(),
                'data' => $diagrams->map(function ($diagram) use ($clientId) {
                    return [
                        'id' => $diagram->id,
                        'name' => $diagram->name,
                        'type' => $diagram->type,
                        'scenario_id' => $diagram->scenario_id,
                        'scenario_name' => $diagram->scenario?->name,
                        'view_url' => route('clients.diagrams.show', [$clientId, $diagram->id]),
                        'created_at' => $diagram->created_at->toIso8601String(),
                        'updated_at' => $diagram->updated_at->toIso8601String(),
                    ];
                }),
            ]);
        } catch (\Throwable $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Database error while reading diagrams: '.$e->getMessage(),
            ]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'client_id' => [
                    'type' => 'integer',
                    'description' => 'The ID of the client whose diagrams to retrieve.',
                ],
                'diagram_id' => [
                    'type' => 'integer',
                    'description' => 'Optional ID of a specific diagram to fetch the raw XML content for.',
                ],
            ],
            'required' => ['client_id'],
        ];
    }
}
