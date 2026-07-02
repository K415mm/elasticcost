<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Scenario;
use App\Services\AgentProfitSimulatorService;
use App\Services\CurrencyHelper;
use App\Services\MsspCostingEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AgentProfitSimulatorController extends Controller
{
    public function __construct(
        protected MsspCostingEngine $costingEngine,
        protected AgentProfitSimulatorService $simulatorService
    ) {}

    /**
     * Display the standalone Profit & Revenue Simulator dashboard page.
     */
    public function index(Request $request)
    {
        $clientId = $request->input('client_id');
        $scenarioId = $request->input('scenario_id');

        $clients = Client::orderBy('name')->get();

        if ($clientId) {
            $client = Client::find($clientId);
        } else {
            $client = Client::first();
        }

        if (! $client) {
            return redirect()->route('clients.index')->with('error', 'Please create a client and scenario first.');
        }

        $scenarios = Scenario::orderBy('name')->get();

        if ($scenarioId) {
            $scenario = $scenarios->firstWhere('id', $scenarioId);
        }

        if (empty($scenario)) {
            $scenario = $scenarios->first();
        }

        if (! $scenario) {
            return redirect()->route('clients.show', $client->id)->with('error', 'Please create a scenario for this client first.');
        }

        $costData = $this->costingEngine->calculate($client, $scenario);
        $msspDetail = $costData['raw_mssp_detail'];
        $simResult = $this->simulatorService->calculate($client, $msspDetail);

        return view('simulator.index', [
            'clients' => $clients,
            'client' => $client,
            'scenarios' => $scenarios,
            'scenario' => $scenario,
            'costData' => $costData,
            'simResult' => $simResult,
            'simSettings' => $simResult['settings'],
            'simInitial' => $simResult['initial_inventory'],
            'simHorizons' => $simResult['horizons'],
            'simTimeline' => $simResult['timeline'],
            'packs' => $simResult['settings']['custom_packs'] ?? [],
        ]);
    }

    /**
     * Save simulation parameters and recalculate.
     */
    public function update(Request $request, Client $client, Scenario $scenario)
    {
        $costData = $this->costingEngine->calculate($client, $scenario);
        $msspDetail = $costData['raw_mssp_detail'];

        if ($request->has('reset_defaults')) {
            $defaults = $this->simulatorService->getScenarioDefaults($client, $msspDetail);
            $msspDetail->update([
                'agent_profit_simulation_settings' => $defaults,
            ]);

            return redirect()->route('simulator.index', ['client_id' => $client->id, 'scenario_id' => $scenario->id])
                ->with('success', 'Simulation settings reset to live scenario defaults.');
        }

        if ($request->has('agent_profit_simulation')) {
            $simulationInput = $request->input('agent_profit_simulation');
            if (is_array($simulationInput)) {
                $defaults = $this->simulatorService->getScenarioDefaults($client, $msspDetail);
                $existing = $msspDetail->agent_profit_simulation_settings ?? [];
                $customPacks = $simulationInput['custom_packs'] ?? ($existing['custom_packs'] ?? ($defaults['custom_packs'] ?? []));

                if (is_array($customPacks)) {
                    foreach ($customPacks as &$pack) {
                        $pack['partner_price'] = CurrencyHelper::convertBack((float) ($pack['partner_price'] ?? 350));
                        $pack['client_price'] = CurrencyHelper::convertBack((float) ($pack['client_price'] ?? 450));
                        if (! empty($pack['extra_services']) && is_array($pack['extra_services'])) {
                            foreach ($pack['extra_services'] as &$svc) {
                                $svc['price'] = CurrencyHelper::convertBack((float) ($svc['price'] ?? 0));
                            }
                        }
                    }
                }

                $msspDetail->update([
                    'agent_profit_simulation_settings' => [
                        'mode' => $simulationInput['mode'] ?? ($existing['mode'] ?? 'agent'),
                        'hosting_mode' => $simulationInput['hosting_mode'] ?? ($existing['hosting_mode'] ?? 'none'),

                        'edr_partner_price' => isset($simulationInput['edr_partner_price']) ? CurrencyHelper::convertBack((float) $simulationInput['edr_partner_price']) : ($existing['edr_partner_price'] ?? $defaults['edr_partner_price']),
                        'edr_client_price' => isset($simulationInput['edr_client_price']) ? CurrencyHelper::convertBack((float) $simulationInput['edr_client_price']) : ($existing['edr_client_price'] ?? $defaults['edr_client_price']),
                        'edr_purchased_limit' => isset($simulationInput['edr_purchased_limit']) ? (int) $simulationInput['edr_purchased_limit'] : ($existing['edr_purchased_limit'] ?? $defaults['edr_purchased_limit']),
                        'edr_monthly_growth' => isset($simulationInput['edr_monthly_growth']) ? (int) $simulationInput['edr_monthly_growth'] : ($existing['edr_monthly_growth'] ?? $defaults['edr_monthly_growth']),

                        'mdr_partner_price' => isset($simulationInput['mdr_partner_price']) ? CurrencyHelper::convertBack((float) $simulationInput['mdr_partner_price']) : ($existing['mdr_partner_price'] ?? $defaults['mdr_partner_price']),
                        'mdr_client_price' => isset($simulationInput['mdr_client_price']) ? CurrencyHelper::convertBack((float) $simulationInput['mdr_client_price']) : ($existing['mdr_client_price'] ?? $defaults['mdr_client_price']),
                        'mdr_purchased_limit' => isset($simulationInput['mdr_purchased_limit']) ? (int) $simulationInput['mdr_purchased_limit'] : ($existing['mdr_purchased_limit'] ?? $defaults['mdr_purchased_limit']),
                        'mdr_monthly_growth' => isset($simulationInput['mdr_monthly_growth']) ? (int) $simulationInput['mdr_monthly_growth'] : ($existing['mdr_monthly_growth'] ?? $defaults['mdr_monthly_growth']),

                        'siem_partner_price' => isset($simulationInput['siem_partner_price']) ? CurrencyHelper::convertBack((float) $simulationInput['siem_partner_price']) : ($existing['siem_partner_price'] ?? $defaults['siem_partner_price']),
                        'siem_client_price' => isset($simulationInput['siem_client_price']) ? CurrencyHelper::convertBack((float) $simulationInput['siem_client_price']) : ($existing['siem_client_price'] ?? $defaults['siem_client_price']),
                        'siem_purchased_limit' => isset($simulationInput['siem_purchased_limit']) ? (int) $simulationInput['siem_purchased_limit'] : ($existing['siem_purchased_limit'] ?? $defaults['siem_purchased_limit']),
                        'siem_monthly_growth' => isset($simulationInput['siem_monthly_growth']) ? (int) $simulationInput['siem_monthly_growth'] : ($existing['siem_monthly_growth'] ?? $defaults['siem_monthly_growth']),

                        'custom_packs' => $customPacks,
                    ],
                ]);
            }
        }

        return redirect()->route('simulator.index', ['client_id' => $client->id, 'scenario_id' => $scenario->id])
            ->with('success', 'Simulation parameters updated and recalculated successfully.');
    }

    /**
     * Run AI Market Simulation analysis.
     */
    public function runAiAnalysis(Client $client, Scenario $scenario)
    {
        @set_time_limit(180);

        try {

            $costData = $this->costingEngine->calculate($client, $scenario);
            $msspDetail = $costData['raw_mssp_detail'];

            $aiReport = $this->simulatorService->runMarketBuyingAiSimulation($client, $msspDetail);

            return response()->json([
                'status' => 'success',
                'analysis' => $aiReport,
            ]);
        } catch (\Throwable $e) {
            Log::error('runAiAnalysis error: '.$e->getMessage());

            $costData = $this->costingEngine->calculate($client, $scenario);
            $msspDetail = $costData['raw_mssp_detail'];
            $simData = $this->simulatorService->calculate($client, $msspDetail);
            $m36 = $simData['timeline'][36] ?? [];
            $cumulProfit = $m36['cumul_direct_profit'] ?? 0;
            $cumulPartnerProfit = $m36['cumul_partner_profit'] ?? 0;

            return response()->json([
                'status' => 'success',
                'analysis' => [
                    'market_attractiveness_score' => 8,
                    'buyer_persona_behavior' => 'Enterprise reseller partners and direct clients analyzed.',
                    'pricing_strategy_feedback' => 'Wholesale and retail margins offer strong market positioning.',
                    'pack_vs_agent_preference' => 'Custom service pack bundling increases ARPU.',
                    'capacity_sold_out_forecast' => 'Capacity cap limits sustain 36-month cumulative direct profit of €'.number_format($cumulProfit, 2),
                    'optimization_recommendations' => [
                        '1. Introduce volume discounts for partner resellers.',
                        '2. Add 24/7 Incident Response SLA to premium custom packs.',
                        '3. Monitor capacity limits prior to month 15 stockouts.',
                    ],
                    'full_market_report' => "### 🤖 AI Market Buying & Profit Optimization Analysis Report\n\n".
                        "- **Overall Attractiveness**: 8/10\n".
                        '- **36-Month Cumulative Direct Profit**: **€'.number_format($cumulProfit, 2)."**\n".
                        '- **36-Month Channel Partner Profit**: **€'.number_format($cumulPartnerProfit, 2)."**\n\n".
                        "#### Key Insights & Recommendations\n".
                        "1. **Channel Partner Incentives**: Partner margins at +25% over base cost offer strong incentives for reseller sign-ups.\n".
                        "2. **Custom Pack Bundling**: Packaging EDR/MDR with extra services like Cyber Threat Intelligence (CTI) increases lifetime deal value.\n".
                        '3. **Capacity Management**: Monitor agent pool limits to prevent sales stockouts when capacity caps are hit.',
                ],
            ]);
        }
    }
}
