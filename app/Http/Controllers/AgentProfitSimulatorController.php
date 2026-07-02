<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Scenario;
use App\Services\AgentProfitSimulatorService;
use App\Services\CurrencyHelper;
use App\Services\MsspCostingEngine;
use Illuminate\Http\Request;

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
            $client = Client::whereHas('scenarios')->first() ?? Client::first();
        }

        if (! $client) {
            return redirect()->route('clients.index')->with('error', 'Please create a client and scenario first.');
        }

        $scenarios = $client->scenarios()->orderBy('name')->get();

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
        $costData = $this->costingEngine->calculate($client, $scenario);
        $msspDetail = $costData['raw_mssp_detail'];

        $aiReport = $this->simulatorService->runMarketBuyingAiSimulation($client, $msspDetail);

        return response()->json([
            'status' => 'success',
            'analysis' => $aiReport,
        ]);
    }
}
