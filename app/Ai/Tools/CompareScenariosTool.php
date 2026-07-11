<?php

namespace App\Ai\Tools;

use App\Models\Client;
use App\Models\Scenario;
use App\Services\CurrencyHelper;
use App\Services\MsspCostingEngine;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\App;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CompareScenariosTool implements Tool
{
    /**
     * Get the tool name.
     */
    public function name(): string
    {
        return 'compare_scenarios';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Compare all available MSSP/SOC costing scenarios for a given client, returning a cost and performance summary for each scenario so the user can pick the best option.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $clientId = $request['client_id'] ?? null;
        $scenarioIds = $request['scenario_ids'] ?? null;

        if (! $clientId) {
            return json_encode(['error' => 'client_id is required.'], JSON_PRETTY_PRINT);
        }

        return json_encode($this->compare($clientId, $scenarioIds), JSON_PRETTY_PRINT);
    }

    /**
     * Compare scenarios for a client and return an array summary.
     *
     * @param  array<int>|null  $scenarioIds
     * @return array<string, mixed>
     */
    public function compare(int $clientId, ?array $scenarioIds = null): array
    {
        $client = Client::find($clientId);

        if (! $client) {
            return ['error' => 'Client not found.'];
        }

        $scenarios = $scenarioIds ? Scenario::whereIn('id', $scenarioIds)->get() : Scenario::all();

        if ($scenarios->isEmpty()) {
            return ['error' => 'No scenarios found to compare.'];
        }

        $engine = App::make(MsspCostingEngine::class);
        $comparisons = [];

        foreach ($scenarios as $scenario) {
            $costData = $engine->calculate($client, $scenario);

            $onPremiseMrc = $costData['client_offered_price_mrc'] ?? 0.0;
            $cloudMrc = $costData['cloud_option']['client_offered_price_mrc'] ?? 0.0;
            $setupCost = $costData['onetime_setup_cost'] ?? 0.0;
            $dailyRawGb = $costData['sizing_summary']['daily_raw_gb'] ?? 0.0;
            $totalRamGb = $costData['sizing_summary']['total_ram_gb'] ?? 0.0;
            $monthlyLicense = $costData['sizing_summary']['monthly_license_usd'] ?? 0.0;
            $totalStorage = $costData['sizing_summary']['total_storage_footprint_gb'] ?? 0.0;

            $twelveMonthOnPremise = $setupCost + ($onPremiseMrc * 12);
            $twelveMonthCloud = $setupCost + ($cloudMrc * 12);

            $costPerGbDay = $dailyRawGb > 0 ? $onPremiseMrc / ($dailyRawGb * 30) : null;

            $comparisons[] = [
                'scenario_id' => $scenario->id,
                'name' => $scenario->name,
                'workload_profile' => $scenario->workload_profile,
                'on_premise_mrc' => CurrencyHelper::format($onPremiseMrc),
                'on_premise_mrc_value' => round($onPremiseMrc, 2),
                'cloud_mrc' => CurrencyHelper::format($cloudMrc),
                'cloud_mrc_value' => round($cloudMrc, 2),
                'setup_cost' => CurrencyHelper::format($setupCost),
                'setup_cost_value' => round($setupCost, 2),
                'monthly_license' => CurrencyHelper::format($monthlyLicense),
                'monthly_license_value' => round($monthlyLicense, 2),
                'twelve_month_tco_on_premise' => CurrencyHelper::format($twelveMonthOnPremise),
                'twelve_month_tco_on_premise_value' => round($twelveMonthOnPremise, 2),
                'twelve_month_tco_cloud' => CurrencyHelper::format($twelveMonthCloud),
                'twelve_month_tco_cloud_value' => round($twelveMonthCloud, 2),
                'sizing' => [
                    'daily_raw_gb' => round($dailyRawGb, 4),
                    'total_ram_gb' => round($totalRamGb, 2),
                    'total_storage_footprint_gb' => round($totalStorage, 2),
                    'cost_per_gb_day' => $costPerGbDay ? round($costPerGbDay, 4) : null,
                ],
            ];
        }

        usort($comparisons, function ($a, $b) {
            return ($a['twelve_month_tco_on_premise_value'] + $a['twelve_month_tco_cloud_value'])
                <=> ($b['twelve_month_tco_on_premise_value'] + $b['twelve_month_tco_cloud_value']);
        });

        return [
            'client_id' => $client->id,
            'client_name' => $client->name,
            'comparisons' => $comparisons,
            'best_overall' => $comparisons[0] ?? null,
        ];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->description('The client ID to compare scenarios for.')->required(),
            'scenario_ids' => $schema->array()->description('Optional list of scenario IDs to compare. If omitted, all scenarios are compared.')->items($schema->integer()),
        ];
    }
}
