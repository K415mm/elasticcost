<?php

namespace App\Ai\Tools;

use App\Models\Client;
use App\Models\Scenario;
use App\Services\CurrencyHelper;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\App;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SelectBestScenarioTool implements Tool
{
    /**
     * Get the tool name.
     */
    public function name(): string
    {
        return 'select_best_scenario';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Select the best MSSP/SOC costing scenario for a client based on cost/performance. Returns the recommended scenario, cost breakdown, and direct links to view it or export it as DOCX, Markdown, or Excel.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $clientId = $request['client_id'] ?? null;
        $scenarioIds = $request['scenario_ids'] ?? null;
        $preference = $request['preference'] ?? 'overall';

        if (! $clientId) {
            return json_encode(['error' => 'client_id is required.'], JSON_PRETTY_PRINT);
        }

        $client = Client::find($clientId);

        if (! $client) {
            return json_encode(['error' => 'Client not found.'], JSON_PRETTY_PRINT);
        }

        $compareTool = App::make(CompareScenariosTool::class);
        $result = $compareTool->compare($clientId, $scenarioIds);

        if (isset($result['error'])) {
            return json_encode($result, JSON_PRETTY_PRINT);
        }

        $comparisons = $result['comparisons'];

        if (empty($comparisons)) {
            return json_encode(['error' => 'No scenario comparisons available.'], JSON_PRETTY_PRINT);
        }

        $best = match ($preference) {
            'on_premise' => $this->bestBy($comparisons, 'twelve_month_tco_on_premise_value'),
            'cloud' => $this->bestBy($comparisons, 'twelve_month_tco_cloud_value'),
            default => $this->bestOverall($comparisons),
        };

        $scenario = Scenario::find($best['scenario_id']);

        if (! $scenario) {
            return json_encode(['error' => 'Recommended scenario not found.'], JSON_PRETTY_PRINT);
        }

        return json_encode(
            [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'preference' => $preference,
                'recommended_scenario_id' => $best['scenario_id'],
                'recommended_scenario_name' => $best['name'],
                'workload_profile' => $best['workload_profile'],
                'on_premise_mrc' => $best['on_premise_mrc'],
                'cloud_mrc' => $best['cloud_mrc'],
                'setup_cost' => $best['setup_cost'],
                'twelve_month_tco_on_premise' => $best['twelve_month_tco_on_premise'],
                'twelve_month_tco_cloud' => $best['twelve_month_tco_cloud'],
                'sizing' => $best['sizing'],
                'summary' => $this->buildSummary($best, $preference),
                'view_url' => route('mssp.show', [$client->id, $scenario->id]),
                'export_urls' => [
                    'markdown' => route('mssp.export.markdown', [$client->id, $scenario->id]),
                    'word' => route('mssp.export.word', [$client->id, $scenario->id]),
                    'excel' => route('mssp.export.excel', [$client->id, $scenario->id]),
                ],
                'all_comparisons' => $comparisons,
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
            'client_id' => $schema->integer()->description('The client ID to select the best scenario for.')->required(),
            'scenario_ids' => $schema->array()->description('Optional list of scenario IDs to compare. If omitted, all scenarios are compared.')->items($schema->integer()),
            'preference' => $schema->string()->description('Which cost mode to prioritize: overall, on_premise, or cloud. Defaults to overall.')->enum(['overall', 'on_premise', 'cloud']),
        ];
    }

    /**
     * Pick the scenario with the lowest value in the given key.
     *
     * @param  array<int, array<string, mixed>>  $comparisons
     */
    private function bestBy(array $comparisons, string $key): array
    {
        return collect($comparisons)->sortBy($key)->first();
    }

    /**
     * Pick the best overall scenario based on the cheaper 12-month TCO of on-premise vs cloud.
     *
     * @param  array<int, array<string, mixed>>  $comparisons
     */
    private function bestOverall(array $comparisons): array
    {
        return collect($comparisons)->sortBy(function ($item) {
            return min($item['twelve_month_tco_on_premise_value'], $item['twelve_month_tco_cloud_value']);
        })->first();
    }

    /**
     * Build a human-friendly summary string.
     *
     * @param  array<string, mixed>  $best
     */
    private function buildSummary(array $best, string $preference): string
    {
        $mode = match ($preference) {
            'on_premise' => 'on-premise deployment',
            'cloud' => 'Elastic Cloud deployment',
            default => 'lowest 12-month TCO (on-premise or cloud)',
        };

        return sprintf(
            'Scenario "%s" is the best choice for %s. On-premise MRC is %s, Elastic Cloud MRC is %s, setup cost is %s, and the 12-month TCO starts at %s.',
            $best['name'],
            $mode,
            $best['on_premise_mrc'],
            $best['cloud_mrc'],
            $best['setup_cost'],
            CurrencyHelper::format(min($best['twelve_month_tco_on_premise_value'], $best['twelve_month_tco_cloud_value']))
        );
    }
}
