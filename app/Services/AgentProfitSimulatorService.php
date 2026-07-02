<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientScenarioMsspDetail;

class AgentProfitSimulatorService
{
    /**
     * Calculate the full 36-month Agent Profit & Revenue Simulation.
     */
    public function calculate(Client $client, ClientScenarioMsspDetail $msspDetail, array $customParams = []): array
    {
        // 1. Get saved settings or merge defaults
        $saved = $msspDetail->agent_profit_simulation_settings ?? [];
        $settings = array_merge([
            'edr_base_cost' => (float) ($msspDetail->edr_agent_monthly_cost_per_device ?? 10.0),
            'edr_partner_price' => (float) ($saved['edr_partner_price'] ?? 20.0),
            'edr_client_price' => (float) ($saved['edr_client_price'] ?? 25.0),
            'edr_purchased_limit' => (int) ($saved['edr_purchased_limit'] ?? 500),
            'edr_monthly_growth' => (int) ($saved['edr_monthly_growth'] ?? 20),

            'mdr_base_cost' => (float) ($msspDetail->mdr_agent_monthly_cost_per_device ?? 30.0),
            'mdr_partner_price' => (float) ($saved['mdr_partner_price'] ?? 45.0),
            'mdr_client_price' => (float) ($saved['mdr_client_price'] ?? 60.0),
            'mdr_purchased_limit' => (int) ($saved['mdr_purchased_limit'] ?? 300),
            'mdr_monthly_growth' => (int) ($saved['mdr_monthly_growth'] ?? 10),

            'siem_base_cost' => (float) ($msspDetail->siem_agent_monthly_cost_per_device ?? 15.0),
            'siem_partner_price' => (float) ($saved['siem_partner_price'] ?? 25.0),
            'siem_client_price' => (float) ($saved['siem_client_price'] ?? 35.0),
            'siem_purchased_limit' => (int) ($saved['siem_purchased_limit'] ?? 500),
            'siem_monthly_growth' => (int) ($saved['siem_monthly_growth'] ?? 15),
        ], $customParams);

        // 2. Determine initial baseline from Client Asset Inventory
        $clientAssets = $client->clientAssets()->get();
        $initialEdr = 0;
        $initialMdr = 0;
        $initialSiem = 0;

        foreach ($clientAssets as $asset) {
            $count = $asset->device_count;
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

        // 3. Compute 36-month timeline simulation
        $timeline = [];
        $cumulDirectProfit = 0.0;
        $cumulPartnerProfit = 0.0;
        $cumulDirectRevenue = 0.0;
        $cumulPartnerRevenue = 0.0;

        for ($month = 1; $month <= 36; $month++) {
            // Capped deployed counts
            $edrDeployed = min($initialEdr + ($month - 1) * $settings['edr_monthly_growth'], $settings['edr_purchased_limit']);
            $mdrDeployed = min($initialMdr + ($month - 1) * $settings['mdr_monthly_growth'], $settings['mdr_purchased_limit']);
            $siemDeployed = min($initialSiem + ($month - 1) * $settings['siem_monthly_growth'], $settings['siem_purchased_limit']);

            $totalDeployed = $edrDeployed + $mdrDeployed + $siemDeployed;

            $edrSoldOut = $edrDeployed >= $settings['edr_purchased_limit'];
            $mdrSoldOut = $mdrDeployed >= $settings['mdr_purchased_limit'];
            $siemSoldOut = $siemDeployed >= $settings['siem_purchased_limit'];

            $isFullySoldOut = $edrSoldOut && $mdrSoldOut && $siemSoldOut;

            // Monthly Financials
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

        // 4. Summarize key time horizons
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

        return [
            'settings' => $settings,
            'initial_inventory' => [
                'edr' => $initialEdr,
                'mdr' => $initialMdr,
                'siem' => $initialSiem,
                'total' => $initialEdr + $initialMdr + $initialSiem,
            ],
            'horizons' => $horizonSummary,
            'timeline' => $timeline,
        ];
    }
}
