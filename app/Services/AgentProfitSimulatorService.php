<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientScenarioMsspDetail;

class AgentProfitSimulatorService
{
    /**
     * Calculate the full 36-month Agent & Pack Profit & Revenue Simulation.
     */
    public function calculate(Client $client, ClientScenarioMsspDetail $msspDetail, array $customParams = []): array
    {
        // 1. Get initial baseline from real Client Asset Inventory & Log Calibration
        $inventoryBaseline = $this->getClientInventoryBaseline($client);

        // 2. Get saved settings or merge with live scenario rate card defaults
        $saved = $msspDetail->agent_profit_simulation_settings ?? [];

        $settings = array_merge([
            'mode' => $saved['mode'] ?? 'agent', // 'agent' or 'pack'
            'hosting_mode' => $saved['hosting_mode'] ?? 'none', // 'none', 'onprem', 'cloud'

            'edr_base_cost' => (float) ($msspDetail->edr_agent_monthly_cost_per_device ?? 10.0),
            'edr_partner_price' => (float) ($saved['edr_partner_price'] ?? 20.0),
            'edr_client_price' => (float) ($saved['edr_client_price'] ?? 25.0),
            'edr_purchased_limit' => (int) ($saved['edr_purchased_limit'] ?? max($inventoryBaseline['edr'] * 3, 500)),
            'edr_monthly_growth' => (int) ($saved['edr_monthly_growth'] ?? 20),

            'mdr_base_cost' => (float) ($msspDetail->mdr_agent_monthly_cost_per_device ?? 30.0),
            'mdr_partner_price' => (float) ($saved['mdr_partner_price'] ?? 45.0),
            'mdr_client_price' => (float) ($saved['mdr_client_price'] ?? 60.0),
            'mdr_purchased_limit' => (int) ($saved['mdr_purchased_limit'] ?? max($inventoryBaseline['mdr'] * 3, 300)),
            'mdr_monthly_growth' => (int) ($saved['mdr_monthly_growth'] ?? 10),

            'siem_base_cost' => (float) ($msspDetail->siem_agent_monthly_cost_per_device ?? 15.0),
            'siem_partner_price' => (float) ($saved['siem_partner_price'] ?? 25.0),
            'siem_client_price' => (float) ($saved['siem_client_price'] ?? 35.0),
            'siem_purchased_limit' => (int) ($saved['siem_purchased_limit'] ?? max($inventoryBaseline['siem'] * 3, 500)),
            'siem_monthly_growth' => (int) ($saved['siem_monthly_growth'] ?? 15),

            'custom_packs' => $saved['custom_packs'] ?? $this->getDefaultPacks($msspDetail),
        ], $customParams);

        // 3. Perform Per-Agent Simulation
        $agentSimulation = $this->calculateAgentSimulation($inventoryBaseline, $settings);

        // 4. Perform Custom Pack Simulation
        $packSimulation = $this->calculatePackSimulation($settings);

        return [
            'mode' => $settings['mode'],
            'hosting_mode' => $settings['hosting_mode'],
            'settings' => $settings,
            'initial_inventory' => $inventoryBaseline,
            'agent_simulation' => $agentSimulation,
            'pack_simulation' => $packSimulation,
            // Main active output depending on mode
            'horizons' => $settings['mode'] === 'pack' ? $packSimulation['horizons'] : $agentSimulation['horizons'],
            'timeline' => $settings['mode'] === 'pack' ? $packSimulation['timeline'] : $agentSimulation['timeline'],
        ];
    }

    /**
     * Get live baseline counts from Client Asset Inventory & Log Calibration.
     */
    public function getClientInventoryBaseline(Client $client): array
    {
        $clientAssets = $client->clientAssets()->get();
        $initialEdr = 0;
        $initialMdr = 0;
        $initialSiem = 0;

        foreach ($clientAssets as $asset) {
            $count = (int) $asset->device_count;
            if ($asset->runs_edr_agent) {
                $initialEdr += $count;
            }
            if ($asset->runs_mdr_agent) {
                $initialMdr += $count;
            }
            if ($asset->runs_siem_agent) {
                $initialSiem += $count;
            }
        }

        return [
            'edr' => $initialEdr,
            'mdr' => $initialMdr,
            'siem' => $initialSiem,
            'total' => $initialEdr + $initialMdr + $initialSiem,
        ];
    }

    /**
     * Get scenario default parameters for Reset / Re-sync.
     */
    public function getScenarioDefaults(Client $client, ClientScenarioMsspDetail $msspDetail): array
    {
        $baseline = $this->getClientInventoryBaseline($client);

        return [
            'mode' => 'agent',
            'hosting_mode' => 'none',

            'edr_base_cost' => (float) ($msspDetail->edr_agent_monthly_cost_per_device ?? 10.0),
            'edr_partner_price' => 20.0,
            'edr_client_price' => 25.0,
            'edr_purchased_limit' => max($baseline['edr'] * 3, 500),
            'edr_monthly_growth' => 20,

            'mdr_base_cost' => (float) ($msspDetail->mdr_agent_monthly_cost_per_device ?? 30.0),
            'mdr_partner_price' => 45.0,
            'mdr_client_price' => 60.0,
            'mdr_purchased_limit' => max($baseline['mdr'] * 3, 300),
            'mdr_monthly_growth' => 10,

            'siem_base_cost' => (float) ($msspDetail->siem_agent_monthly_cost_per_device ?? 15.0),
            'siem_partner_price' => 25.0,
            'siem_client_price' => 35.0,
            'siem_purchased_limit' => max($baseline['siem'] * 3, 500),
            'siem_monthly_growth' => 15,

            'custom_packs' => $this->getDefaultPacks($msspDetail),
        ];
    }

    /**
     * Generate default sample pack.
     */
    public function getDefaultPacks(ClientScenarioMsspDetail $msspDetail): array
    {
        $edrBase = (float) ($msspDetail->edr_agent_monthly_cost_per_device ?? 10.0);
        $cost = (10 * $edrBase) + 150.0; // 10 EDR + $150 CTI service

        return [
            [
                'id' => 'pack_default_1',
                'name' => 'Basic EDR + CTI Protection Pack',
                'edr_count' => 10,
                'mdr_count' => 0,
                'siem_count' => 0,
                'extra_services' => [
                    ['name' => 'Cyber Threat Intelligence (CTI)', 'price' => 150.0],
                ],
                'calculated_base_cost' => $cost,
                'partner_price' => 350.0,
                'client_price' => 450.0,
                'purchased_limit' => 50,
                'monthly_growth' => 5,
                'initial_packs' => 1,
            ],
        ];
    }

    /**
     * Compute Per-Agent Simulation logic.
     */
    protected function calculateAgentSimulation(array $baseline, array $settings): array
    {
        $timeline = [];
        $cumulDirectProfit = 0.0;
        $cumulPartnerProfit = 0.0;
        $cumulDirectRevenue = 0.0;
        $cumulPartnerRevenue = 0.0;

        for ($month = 1; $month <= 36; $month++) {
            $edrDeployed = min($baseline['edr'] + ($month - 1) * $settings['edr_monthly_growth'], $settings['edr_purchased_limit']);
            $mdrDeployed = min($baseline['mdr'] + ($month - 1) * $settings['mdr_monthly_growth'], $settings['mdr_purchased_limit']);
            $siemDeployed = min($baseline['siem'] + ($month - 1) * $settings['siem_monthly_growth'], $settings['siem_purchased_limit']);

            $totalDeployed = $edrDeployed + $mdrDeployed + $siemDeployed;

            $edrSoldOut = $edrDeployed >= $settings['edr_purchased_limit'];
            $mdrSoldOut = $mdrDeployed >= $settings['mdr_purchased_limit'];
            $siemSoldOut = $siemDeployed >= $settings['siem_purchased_limit'];
            $isFullySoldOut = $edrSoldOut && $mdrSoldOut && $siemSoldOut;

            $monthlyCost = ($edrDeployed * $settings['edr_base_cost'])
                + ($mdrDeployed * $settings['mdr_base_cost'])
                + ($siemDeployed * $settings['siem_base_cost']);

            $directRevenue = ($edrDeployed * $settings['edr_client_price'])
                + ($mdrDeployed * $settings['mdr_client_price'])
                + ($siemDeployed * $settings['siem_client_price']);

            $directProfit = $directRevenue - $monthlyCost;

            $partnerRevenue = ($edrDeployed * $settings['edr_partner_price'])
                + ($mdrDeployed * $settings['mdr_partner_price'])
                + ($siemDeployed * $settings['siem_partner_price']);

            $partnerProfit = $partnerRevenue - $monthlyCost;
            $partnerMargin = $directRevenue - $partnerRevenue;

            $cumulDirectProfit += $directProfit;
            $cumulPartnerProfit += $partnerProfit;
            $cumulDirectRevenue += $directRevenue;
            $cumulPartnerRevenue += $partnerRevenue;

            $timeline[$month] = [
                'month' => $month,
                'edr_deployed' => $edrDeployed,
                'mdr_deployed' => $mdrDeployed,
                'siem_deployed' => $siemDeployed,
                'total_deployed' => $totalDeployed,
                'edr_sold_out' => $edrSoldOut,
                'mdr_sold_out' => $mdrSoldOut,
                'siem_sold_out' => $siemSoldOut,
                'is_fully_sold_out' => $isFullySoldOut,
                'monthly_cost' => round($monthlyCost, 2),
                'direct_revenue' => round($directRevenue, 2),
                'direct_profit' => round($directProfit, 2),
                'partner_revenue' => round($partnerRevenue, 2),
                'partner_profit' => round($partnerProfit, 2),
                'partner_margin' => round($partnerMargin, 2),
                'cumul_direct_profit' => round($cumulDirectProfit, 2),
                'cumul_partner_profit' => round($cumulPartnerProfit, 2),
                'cumul_direct_revenue' => round($cumulDirectRevenue, 2),
                'cumul_partner_revenue' => round($cumulPartnerRevenue, 2),
            ];
        }

        return [
            'horizons' => $this->summarizeHorizons($timeline),
            'timeline' => $timeline,
        ];
    }

    /**
     * Compute Custom Pack Builder Simulation logic.
     */
    protected function calculatePackSimulation(array $settings): array
    {
        $packs = $settings['custom_packs'] ?? [];
        $timeline = [];
        $cumulDirectProfit = 0.0;
        $cumulPartnerProfit = 0.0;
        $cumulDirectRevenue = 0.0;
        $cumulPartnerRevenue = 0.0;

        for ($month = 1; $month <= 36; $month++) {
            $totalPacksDeployed = 0;
            $monthlyCost = 0.0;
            $directRevenue = 0.0;
            $partnerRevenue = 0.0;
            $allPacksSoldOut = count($packs) > 0;

            foreach ($packs as $p) {
                $edrCount = (int) ($p['edr_count'] ?? 0);
                $mdrCount = (int) ($p['mdr_count'] ?? 0);
                $siemCount = (int) ($p['siem_count'] ?? 0);

                // Base cost per pack
                $extraServicesCost = 0.0;
                if (! empty($p['extra_services']) && is_array($p['extra_services'])) {
                    foreach ($p['extra_services'] as $svc) {
                        $extraServicesCost += (float) ($svc['price'] ?? 0);
                    }
                }

                $packBaseCost = ($edrCount * $settings['edr_base_cost'])
                    + ($mdrCount * $settings['mdr_base_cost'])
                    + ($siemCount * $settings['siem_base_cost'])
                    + $extraServicesCost;

                $initialPacks = (int) ($p['initial_packs'] ?? 1);
                $growth = (int) ($p['monthly_growth'] ?? 5);
                $limit = (int) ($p['purchased_limit'] ?? 50);

                $deployedPacks = min($initialPacks + ($month - 1) * $growth, $limit);
                $totalPacksDeployed += $deployedPacks;

                if ($deployedPacks < $limit) {
                    $allPacksSoldOut = false;
                }

                $monthlyCost += $deployedPacks * $packBaseCost;
                $directRevenue += $deployedPacks * (float) ($p['client_price'] ?? 450.0);
                $partnerRevenue += $deployedPacks * (float) ($p['partner_price'] ?? 350.0);
            }

            $directProfit = $directRevenue - $monthlyCost;
            $partnerProfit = $partnerRevenue - $monthlyCost;
            $partnerMargin = $directRevenue - $partnerRevenue;

            $cumulDirectProfit += $directProfit;
            $cumulPartnerProfit += $partnerProfit;
            $cumulDirectRevenue += $directRevenue;
            $cumulPartnerRevenue += $partnerRevenue;

            $timeline[$month] = [
                'month' => $month,
                'total_deployed' => $totalPacksDeployed,
                'is_fully_sold_out' => $allPacksSoldOut,
                'monthly_cost' => round($monthlyCost, 2),
                'direct_revenue' => round($directRevenue, 2),
                'direct_profit' => round($directProfit, 2),
                'partner_revenue' => round($partnerRevenue, 2),
                'partner_profit' => round($partnerProfit, 2),
                'partner_margin' => round($partnerMargin, 2),
                'cumul_direct_profit' => round($cumulDirectProfit, 2),
                'cumul_partner_profit' => round($cumulPartnerProfit, 2),
                'cumul_direct_revenue' => round($cumulDirectRevenue, 2),
                'cumul_partner_revenue' => round($cumulPartnerRevenue, 2),
            ];
        }

        return [
            'horizons' => $this->summarizeHorizons($timeline),
            'timeline' => $timeline,
        ];
    }

    /**
     * Helper to summarize key time horizons (1M, 3M, 6M, 12M, 36M).
     */
    protected function summarizeHorizons(array $timeline): array
    {
        $horizons = [1, 3, 6, 12, 36];
        $horizonSummary = [];

        foreach ($horizons as $h) {
            $horizonSummary[$h] = [
                'months' => $h,
                'label' => $h === 1 ? '1 Month' : ($h < 12 ? "{$h} Months" : ($h === 12 ? '1 Year' : '3 Years')),
                'deployed_at_end' => $timeline[$h]['total_deployed'],
                'is_sold_out' => $timeline[$h]['is_fully_sold_out'],
                'direct_revenue' => $timeline[$h]['cumul_direct_revenue'],
                'direct_profit' => $timeline[$h]['cumul_direct_profit'],
                'partner_revenue' => $timeline[$h]['cumul_partner_revenue'],
                'partner_profit' => $timeline[$h]['cumul_partner_profit'],
                'monthly_mrc_at_end' => $timeline[$h]['direct_revenue'],
                'monthly_profit_at_end' => $timeline[$h]['direct_profit'],
            ];
        }

        return $horizonSummary;
    }
}
