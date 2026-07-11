<?php

namespace App\Ai\Tools;

use App\Models\Client;
use App\Models\Scenario;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ExportMsspProposalTool implements Tool
{
    /**
     * Get the tool name.
     */
    public function name(): string
    {
        return 'export_mssp_proposal';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Generate a download URL for the MSSP/SOC cost proposal in the requested format (markdown, word, or excel). This lets the agent provide the export button as a tool.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $clientId = $request['client_id'] ?? null;
        $scenarioId = $request['scenario_id'] ?? null;
        $format = $request['format'] ?? 'markdown';

        if (! $clientId || ! $scenarioId) {
            return json_encode(['error' => 'client_id and scenario_id are required.'], JSON_PRETTY_PRINT);
        }

        $client = Client::find($clientId);
        $scenario = Scenario::find($scenarioId);

        if (! $client || ! $scenario) {
            return json_encode(['error' => 'Client or scenario not found.'], JSON_PRETTY_PRINT);
        }

        $routeName = $this->routeName($format);

        if (! $routeName) {
            return json_encode(['error' => 'Invalid format. Use markdown, word, or excel.'], JSON_PRETTY_PRINT);
        }

        return json_encode(
            [
                'client_id' => $client->id,
                'scenario_id' => $scenario->id,
                'format' => $format,
                'filename' => $this->filename($client, $scenario, $format),
                'download_url' => route($routeName, [$client->id, $scenario->id]),
            ],
            JSON_PRETTY_PRINT
        );
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->description('The client ID for the proposal.')->required(),
            'scenario_id' => $schema->integer()->description('The scenario ID for the proposal.')->required(),
            'format' => $schema->string()->description('Export format: markdown, word, or excel. Defaults to markdown.')->enum(['markdown', 'word', 'excel'])->default('markdown'),
        ];
    }

    /**
     * Resolve the export route name for the requested format.
     */
    private function routeName(string $format): ?string
    {
        return match ($format) {
            'markdown' => 'mssp.export.markdown',
            'word', 'docx' => 'mssp.export.word',
            'excel', 'xlsx' => 'mssp.export.excel',
            default => null,
        };
    }

    /**
     * Build a human-readable filename for the export.
     */
    private function filename(Client $client, Scenario $scenario, string $format): string
    {
        $extension = match ($format) {
            'word', 'docx' => 'docx',
            'excel', 'xlsx' => 'xlsx',
            default => 'md',
        };

        return sprintf('mssp-proposal-%s-%s.%s', str($client->name)->slug(), str($scenario->name)->slug(), $extension);
    }
}
