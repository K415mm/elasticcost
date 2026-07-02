<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientScenarioAnalystAllocation;
use App\Models\ClientScenarioMsspDetail;
use App\Models\GlobalSetting;
use App\Models\Scenario;
use App\Models\SocRole;

class MsspCostingEngine
{
    public function __construct(
        protected SizingEngine $sizingEngine,
        protected CloudProviderPricingService $pricingService
    ) {}

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
                'elastic_cloud_monthly_cost_per_gb_ram' => (float) GlobalSetting::getValue('elastic_cloud_monthly_cost_per_gb_ram', 45.0000),
                'elastic_cloud_subscription_tier' => 'platinum',
                'siem_agent_monthly_cost_per_device' => (float) GlobalSetting::getValue('siem_agent_monthly_cost_per_device', 15.0000),
                'mdr_agent_monthly_cost_per_device' => (float) GlobalSetting::getValue('mdr_agent_monthly_cost_per_device', 30.0000),
                'edr_agent_monthly_cost_per_device' => (float) GlobalSetting::getValue('edr_agent_monthly_cost_per_device', 10.0000),
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
        $datacenter = $msspDetail->cloud_datacenter;

        foreach ($sizingData['nodes'] as $node) {
            $count = $node['count'];
            $ramGb = $node['ram_gb'];
            $storageGb = $node['storage_gb'];
            $storageType = $node['storage_type'];

            $matchedVm = null;
            $matchedDisk = null;
            $ramCost = 0.0;
            $storageCost = 0.0;

            if ($datacenter) {
                // Find matching VM
                $matchedVm = $this->pricingService->matchVm($datacenter, $ramGb);
                if ($matchedVm) {
                    $tndPrice = (float) $matchedVm['price_linux_tnd'];
                    $usdPrice = $tndPrice / CurrencyHelper::rate('TND');
                    $ramCost = $usdPrice * $count;
                } else {
                    $ramUnitCost = (float) $msspDetail->ram_monthly_cost_per_gb;
                    $ramCost = $ramGb * $ramUnitCost * $count;
                }

                // Find matching Disk
                $matchedDisk = $this->pricingService->matchDisk($datacenter, $storageGb);
                if ($matchedDisk) {
                    $tndPrice = (float) $matchedDisk['total_price_tnd'];
                    $usdPrice = $tndPrice / CurrencyHelper::rate('TND');
                    $storageCost = $usdPrice * $count;
                } else {
                    $storageUnitCost = (float) $msspDetail->local_ssd_monthly_cost_per_gb;
                    if (stripos($storageType, 'NVMe') !== false) {
                        $storageUnitCost = (float) $msspDetail->nvme_ssd_monthly_cost_per_gb;
                    } elseif (stripos($storageType, 'SATA') !== false || stripos($storageType, 'HDD') !== false) {
                        $storageUnitCost = (float) $msspDetail->sata_ssd_monthly_cost_per_gb;
                    }
                    $storageCost = $storageGb * $storageUnitCost * $count;
                }
            } else {
                $ramUnitCost = (float) $msspDetail->ram_monthly_cost_per_gb;
                $ramCost = $ramGb * $ramUnitCost * $count;

                $storageUnitCost = (float) $msspDetail->local_ssd_monthly_cost_per_gb;
                if (stripos($storageType, 'NVMe') !== false) {
                    $storageUnitCost = (float) $msspDetail->nvme_ssd_monthly_cost_per_gb;
                } elseif (stripos($storageType, 'SATA') !== false || stripos($storageType, 'HDD') !== false) {
                    $storageUnitCost = (float) $msspDetail->sata_ssd_monthly_cost_per_gb;
                }
                $storageCost = $storageGb * $storageUnitCost * $count;
            }

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
                'matched_vm_name' => $matchedVm ? $matchedVm['name'] : null,
                'matched_vm_vcpu' => $matchedVm ? $matchedVm['vcpu'] : null,
                'matched_vm_ram' => $matchedVm ? $matchedVm['ram_gb'] : null,
                'matched_vm_price_tnd' => $matchedVm ? $matchedVm['price_linux_tnd'] : null,
                'matched_disk_desc' => $matchedDisk ? $matchedDisk['description'] : null,
                'matched_disk_price_tnd' => $matchedDisk ? $matchedDisk['total_price_tnd'] : null,
                'cloud_datacenter' => $datacenter,
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

        // 9. Calculate Elastic Cloud and MDR Agent Package costs
        $clientAssets = $client->clientAssets()->with('assetType')->get();
        $totalSiemCount = 0;
        $totalMdrCount = 0;
        $totalEdrCount = 0;
        $agentDetails = [];

        foreach ($clientAssets as $asset) {
            $deviceCount = $asset->device_count;
            $runsSiem = (bool) $asset->runs_siem_agent;
            $runsMdr = (bool) $asset->runs_mdr_agent;
            $runsEdr = (bool) $asset->runs_edr_agent;

            $siemCount = $runsSiem ? $deviceCount : 0;
            $mdrCount = $runsMdr ? $deviceCount : 0;
            $edrCount = $runsEdr ? $deviceCount : 0;

            $totalSiemCount += $siemCount;
            $totalMdrCount += $mdrCount;
            $totalEdrCount += $edrCount;

            $agentDetails[] = [
                'asset_id' => $asset->id,
                'name' => $asset->assetType->name,
                'device_count' => $deviceCount,
                'runs_siem' => $runsSiem,
                'runs_mdr' => $runsMdr,
                'runs_edr' => $runsEdr,
                'siem_count' => $siemCount,
                'mdr_count' => $mdrCount,
                'edr_count' => $edrCount,
            ];
        }

        $siemUnitCost = (float) ($msspDetail->siem_agent_monthly_cost_per_device ?? GlobalSetting::getValue('siem_agent_monthly_cost_per_device', 15.0000));
        $mdrUnitCost = (float) ($msspDetail->mdr_agent_monthly_cost_per_device ?? GlobalSetting::getValue('mdr_agent_monthly_cost_per_device', 30.0000));
        $edrUnitCost = (float) ($msspDetail->edr_agent_monthly_cost_per_device ?? GlobalSetting::getValue('edr_agent_monthly_cost_per_device', 10.0000));
        $cloudRamUnitCost = (float) ($msspDetail->elastic_cloud_monthly_cost_per_gb_ram ?? GlobalSetting::getValue('elastic_cloud_monthly_cost_per_gb_ram', 45.0000));

        $siemMonthlyCost = $totalSiemCount * $siemUnitCost;
        $mdrMonthlyCost = $totalMdrCount * $mdrUnitCost;
        $edrMonthlyCost = $totalEdrCount * $edrUnitCost;
        $totalAgentsMonthlyCost = $siemMonthlyCost + $mdrMonthlyCost + $edrMonthlyCost;

        $cloudTier = $msspDetail->elastic_cloud_subscription_tier ?? 'platinum';
        $elasticCloudSubscriptionCost = 0.0;
        $matchedCloudNodes = [];

        foreach ($sizingData['nodes'] as $node) {
            $count = $node['count'];
            $ramGb = $node['ram_gb'];

            // Match to Elastic Cloud SKU and rate
            $matchedNode = $this->pricingService->matchElasticCloudNode($node['name'], $node['role'], $cloudTier);

            $hourlyRate = 0.0;
            $sku = 'unknown';
            if ($matchedNode) {
                $sku = $matchedNode['sku'];
                $hourlyRate = $matchedNode['hourly_rate'];
            }

            // Monthly node cost = count * ram_gb * hourly_rate * 730 hours
            $monthlyNodeCost = $count * $ramGb * $hourlyRate * 730;
            $elasticCloudSubscriptionCost += $monthlyNodeCost;

            $matchedCloudNodes[] = [
                'name' => $node['name'],
                'role' => $node['role'],
                'count' => $count,
                'ram_gb' => $ramGb,
                'sku' => $sku,
                'hourly_rate' => $hourlyRate,
                'monthly_cost' => round($monthlyNodeCost, 2),
            ];
        }

        // Cloud Option Base Cost
        $cloudBaseMrc = $totalAgentsMonthlyCost;

        $cloudAssuranceAmount = 0.0;
        $cloudMarketingAmount = 0.0;
        $cloudSocManagerAmount = 0.0;
        $cloudCeoAmount = 0.0;
        $cloudFixedAmount = 0.0;

        $totalCloudProfitAmount = 0.0;
        $clientOfferedCloudMrc = $totalAgentsMonthlyCost;

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

            // Cloud Option details
            'cloud_option' => [
                'elastic_cloud_subscription_tier' => $cloudTier,
                'elastic_cloud_monthly_cost_per_gb_ram' => $cloudRamUnitCost,
                'elastic_cloud_subscription_cost' => round($elasticCloudSubscriptionCost, 2),
                'matched_nodes' => $matchedCloudNodes,
                'siem_agent_monthly_cost_per_device' => $siemUnitCost,
                'mdr_agent_monthly_cost_per_device' => $mdrUnitCost,
                'edr_agent_monthly_cost_per_device' => $edrUnitCost,

                'agents' => $agentDetails,
                'total_siem_count' => $totalSiemCount,
                'total_mdr_count' => $totalMdrCount,
                'total_edr_count' => $totalEdrCount,
                'siem_monthly_cost' => round($siemMonthlyCost, 2),
                'mdr_monthly_cost' => round($mdrMonthlyCost, 2),
                'edr_monthly_cost' => round($edrMonthlyCost, 2),
                'total_agents_monthly_cost' => round($totalAgentsMonthlyCost, 2),

                'total_monthly_service_cost' => round($cloudBaseMrc, 2),

                'assurance_benefit_amount' => round($cloudAssuranceAmount, 2),
                'marketing_benefit_amount' => round($cloudMarketingAmount, 2),
                'soc_manager_benefit_amount' => round($cloudSocManagerAmount, 2),
                'ceo_benefit_amount' => round($cloudCeoAmount, 2),
                'fixed_profit_amount' => round($cloudFixedAmount, 2),
                'total_profit_amount' => round($totalCloudProfitAmount, 2),
                'client_offered_price_mrc' => round($clientOfferedCloudMrc, 2),
            ],
        ];
    }
}
