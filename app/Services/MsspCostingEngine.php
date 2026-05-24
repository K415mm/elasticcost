<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Scenario;
use App\Models\SocRole;
use App\Models\ClientScenarioMsspDetail;
use App\Models\ClientScenarioAnalystAllocation;

class MsspCostingEngine
{
    protected SizingEngine $sizingEngine;

    public function __construct(SizingEngine $sizingEngine)
    {
        $this->sizingEngine = $sizingEngine;
    }

    /**
     * Calculates the full MSSP / SOC costing model for a client scenario.
     */
    public function calculate(Client $client, Scenario $scenario): array
    {
        // 1. Get Elastic Stack Sizing and License costing
        $sizingData = $this->sizingEngine->calculate($client, $scenario);

        // 2. Fetch or create the MSSP costing details record
        $msspDetail = ClientScenarioMsspDetail::firstOrCreate(
            [
                'client_id' => $client->id,
                'scenario_id' => $scenario->id,
            ],
            [
                'one_time_setup_cost' => 5000.00,
                'monthly_maintenance_cost' => 1500.00,
                'ram_monthly_cost_per_gb' => 1.5000,
                'nvme_ssd_monthly_cost_per_gb' => 0.1500,
                'sata_ssd_monthly_cost_per_gb' => 0.0800,
                'local_ssd_monthly_cost_per_gb' => 0.1200,
            ]
        );

        // 3. Ensure default analyst allocations exist for all SOC roles
        $roles = SocRole::all();
        $defaultPercentages = [
            1 => 10.00, // L1 Analyst: 10%
            2 => 5.00,  // L2 Analyst: 5%
            3 => 2.00,  // L3 Analyst: 2%
            4 => 5.00,  // SOC Engineer: 5%
            5 => 2.00,  // SOC Manager: 2%
        ];

        foreach ($roles as $role) {
            ClientScenarioAnalystAllocation::firstOrCreate(
                [
                    'mssp_details_id' => $msspDetail->id,
                    'soc_role_id' => $role->id,
                ],
                [
                    'allocation_percentage' => $defaultPercentages[$role->id] ?? 0.00,
                    'custom_monthly_salary' => null,
                ]
            );
        }

        // 4. Calculate Staffing (Analyst) Cost
        $allocations = $msspDetail->analystAllocations()->with('socRole')->get();
        $analystDetails = [];
        $totalAnalystCost = 0.0;

        foreach ($allocations as $alloc) {
            $salary = (float) ($alloc->custom_monthly_salary ?? $alloc->socRole->default_monthly_salary);
            $percentage = (float) $alloc->allocation_percentage;
            $staffCount = (int) ($alloc->staff_count ?? 1);
            $cost = $salary * ($percentage / 100) * $staffCount;

            $analystDetails[] = [
                'id' => $alloc->id,
                'role_id' => $alloc->soc_role_id,
                'name' => $alloc->socRole->name,
                'description' => $alloc->socRole->description,
                'monthly_salary' => $salary,
                'allocation_percentage' => $percentage,
                'staff_count' => $staffCount,
                'client_cost' => round($cost, 2),
            ];

            $totalAnalystCost += $cost;
        }

        // 5. Calculate Infrastructure Node Hosting Costs
        $nodeDetails = [];
        $totalInfraCost = 0.0;

        foreach ($sizingData['nodes'] as $node) {
            $count = $node['count'];
            $ramGb = $node['ram_gb'];
            $storageGb = $node['storage_gb'];
            $storageType = $node['storage_type'];

            // RAM cost
            $ramUnitCost = (float) $msspDetail->ram_monthly_cost_per_gb;
            $ramCost = $ramGb * $ramUnitCost * $count;

            // Storage cost selection based on media type
            $storageUnitCost = (float) $msspDetail->local_ssd_monthly_cost_per_gb;
            if (stripos($storageType, 'NVMe') !== false) {
                $storageUnitCost = (float) $msspDetail->nvme_ssd_monthly_cost_per_gb;
            } elseif (stripos($storageType, 'SATA') !== false || stripos($storageType, 'HDD') !== false) {
                $storageUnitCost = (float) $msspDetail->sata_ssd_monthly_cost_per_gb;
            }

            $storageCost = $storageGb * $storageUnitCost * $count;
            $nodeTotal = $ramCost + $storageCost;

            $nodeDetails[] = [
                'name' => $node['name'],
                'role' => $node['role'],
                'count' => $count,
                'ram_gb' => $ramGb,
                'storage_gb' => $storageGb,
                'storage_type' => $storageType,
                'ram_monthly_cost' => round($ramCost, 2),
                'storage_monthly_cost' => round($storageCost, 2),
                'total_monthly_cost' => round($nodeTotal, 2),
            ];

            $totalInfraCost += $nodeTotal;
        }

        // 6. Calculate monthly equivalent license cost
        $annualLicense = (float) $sizingData['licensing']['annual_cost_usd'];
        $monthlyLicenseCost = $annualLicense / 12;
        if ($msspDetail->is_license_shared) {
            $sharePercentage = (float) ($msspDetail->license_share_percentage ?? 100.00);
            $monthlyLicenseCost = $monthlyLicenseCost * ($sharePercentage / 100);
        }

        // 7. Calculate aggregate totals
        $oneTimeCosts = (float) $msspDetail->one_time_setup_cost;
        $monthlyMaintenance = (float) $msspDetail->monthly_maintenance_cost;

        $totalMonthlyCost = $totalAnalystCost + $totalInfraCost + $monthlyLicenseCost + $monthlyMaintenance;

        // 8. Calculate profit margin markups based on percentages of Base Estimated MRC
        $assurancePct = (float) ($msspDetail->assurance_benefit_percentage ?? 0.00);
        $marketingPct = (float) ($msspDetail->marketing_benefit_percentage ?? 0.00);
        $socManagerPct = (float) ($msspDetail->soc_manager_benefit_percentage ?? 0.00);
        $ceoPct = (float) ($msspDetail->ceo_benefit_percentage ?? 0.00);
        $fixedPct = (float) ($msspDetail->fixed_profit_percentage ?? 0.00);

        $assuranceAmount = $totalMonthlyCost * ($assurancePct / 100);
        $marketingAmount = $totalMonthlyCost * ($marketingPct / 100);
        $socManagerAmount = $totalMonthlyCost * ($socManagerPct / 100);
        $ceoAmount = $totalMonthlyCost * ($ceoPct / 100);
        $fixedAmount = $totalMonthlyCost * ($fixedPct / 100);

        $totalProfitPct = $assurancePct + $marketingPct + $socManagerPct + $ceoPct + $fixedPct;
        $totalProfitAmount = $assuranceAmount + $marketingAmount + $socManagerAmount + $ceoAmount + $fixedAmount;

        $clientOfferedMrc = $totalMonthlyCost + $totalProfitAmount;

        return [
            'client' => $sizingData['client'],
            'scenario' => $sizingData['scenario'],
            'sizing_summary' => [
                'total_ram_gb' => $sizingData['licensing']['total_ram_gb'],
                'required_erus' => $sizingData['licensing']['required_erus'],
                'annual_license_usd' => $annualLicense,
                'monthly_license_usd' => round($monthlyLicenseCost, 2),
                'daily_raw_gb' => $sizingData['totals']['daily_raw_gb'],
            ],
            'rates' => [
                'ram_monthly_cost_per_gb' => (float) $msspDetail->ram_monthly_cost_per_gb,
                'nvme_ssd_monthly_cost_per_gb' => (float) $msspDetail->nvme_ssd_monthly_cost_per_gb,
                'sata_ssd_monthly_cost_per_gb' => (float) $msspDetail->sata_ssd_monthly_cost_per_gb,
                'local_ssd_monthly_cost_per_gb' => (float) $msspDetail->local_ssd_monthly_cost_per_gb,
            ],
            'infrastructure' => [
                'nodes' => $nodeDetails,
                'total_monthly_infra_cost' => round($totalInfraCost, 2),
            ],
            'analysts' => [
                'roles' => $analystDetails,
                'total_monthly_analyst_cost' => round($totalAnalystCost, 2),
            ],
            'onetime_setup_cost' => round($oneTimeCosts, 2),
            'monthly_maintenance_cost' => round($monthlyMaintenance, 2),
            'total_monthly_service_cost' => round($totalMonthlyCost, 2),
            
            // Profit margins
            'assurance_benefit_percentage' => $assurancePct,
            'assurance_benefit_amount' => round($assuranceAmount, 2),
            'marketing_benefit_percentage' => $marketingPct,
            'marketing_benefit_amount' => round($marketingAmount, 2),
            'soc_manager_benefit_percentage' => $socManagerPct,
            'soc_manager_benefit_amount' => round($socManagerAmount, 2),
            'ceo_benefit_percentage' => $ceoPct,
            'ceo_benefit_amount' => round($ceoAmount, 2),
            'fixed_profit_percentage' => $fixedPct,
            'fixed_profit_amount' => round($fixedAmount, 2),
            'total_profit_percentage' => $totalProfitPct,
            'total_profit_amount' => round($totalProfitAmount, 2),
            'client_offered_price_mrc' => round($clientOfferedMrc, 2),

            'raw_mssp_detail' => $msspDetail,
        ];
    }
}
