<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Scenario;
use App\Models\GlobalSetting;

class SizingEngine
{
    /**
     * Calculates the sizing and cost for a client under a specific scenario.
     */
    public function calculate(Client $client, Scenario $scenario): array
    {
        // 1. Fetch Global Settings (with sensible fallbacks)
        $eruCost = (float) GlobalSetting::getValue('eru_cost_usd', 14000);
        $ramPerEru = (float) GlobalSetting::getValue('ram_per_eru_gb', 64);
        $expansionFactor = (float) GlobalSetting::getValue('index_expansion_factor', 1.25);

        // 2. Load Client Assets (Inventory)
        $clientAssets = $client->clientAssets()->with('assetType')->get();

        $assetCalculations = [];
        $totalRawDailyGb = 0.0;
        $totalIndexedDailyGb = 0.0;

        foreach ($clientAssets as $asset) {
            $deviceCount = $asset->device_count;
            $eventSize = $asset->custom_avg_event_size_bytes ?? $asset->assetType->avg_event_size_bytes;
            $calibrationMode = $asset->assetType->calibration_mode;

            $eps = 0.0;
            $dailyRawGb = 0.0;

            // Resolve EPS and Daily Raw GB based on profile and calibration mode
            if ($scenario->workload_profile === 'min') {
                $eps = (float) ($asset->custom_min_eps ?? $asset->assetType->min_eps_default);
                // Daily Raw (GB) = (Devices * EPS * 86,400 * EventSize) / 10^9
                $dailyRawGb = ($deviceCount * $eps * 86400 * $eventSize) / 1000000000;
            } elseif ($scenario->workload_profile === 'avg') {
                $eps = (float) ($asset->custom_avg_eps ?? $asset->assetType->avg_eps_default);
                $dailyRawGb = ($deviceCount * $eps * 86400 * $eventSize) / 1000000000;
            } elseif ($scenario->workload_profile === 'max') {
                if ($calibrationMode === 'monthly_gb_per_device') {
                    $monthlyGb = (float) ($asset->custom_max_monthly_gb ?? $asset->assetType->max_monthly_gb_default);
                    $dailyRawGb = ($deviceCount * $monthlyGb) / 30;
                    // Back-calculate EPS: EPS = (Daily Raw GB * 10^9) / (Devices * 86,400 * EventSize)
                    $eps = $deviceCount > 0 ? ($dailyRawGb * 1000000000) / ($deviceCount * 86400 * $eventSize) : 0.0;
                } elseif ($calibrationMode === 'monthly_gb_total') {
                    $monthlyGb = (float) ($asset->custom_max_monthly_gb ?? $asset->assetType->max_monthly_gb_default);
                    $dailyRawGb = $monthlyGb / 30;
                    // Back-calculate EPS
                    $eps = $deviceCount > 0 ? ($dailyRawGb * 1000000000) / ($deviceCount * 86400 * $eventSize) : 0.0;
                } else { // eps_per_device
                    $eps = (float) ($asset->custom_max_eps ?? $asset->assetType->max_eps_default);
                    $dailyRawGb = ($deviceCount * $eps * 86400 * $eventSize) / 1000000000;
                }
            }

            $dailyIndexedGb = $dailyRawGb * $expansionFactor;
            
            // Replicated daily ingested (we assume Hot replicas apply to this ingest tier)
            $dailyIngestedGb = $dailyIndexedGb * (1 + $scenario->hot_replicas);

            $assetCalculations[] = [
                'id' => $asset->id,
                'name' => $asset->assetType->name,
                'device_count' => $deviceCount,
                'event_size_bytes' => $eventSize,
                'eps' => round($eps, 4),
                'total_eps' => round($eps * $deviceCount, 4),
                'daily_event_count' => round($deviceCount * $eps * 86400),
                'daily_raw_gb' => round($dailyRawGb, 4),
                'daily_indexed_gb' => round($dailyIndexedGb, 4),
                'daily_ingested_gb' => round($dailyIngestedGb, 4),
            ];

            $totalRawDailyGb += $dailyRawGb;
            $totalIndexedDailyGb += $dailyIndexedGb;
        }

        // 3. Storage Sizing Calculations by Tier
        $hotStorage = $totalIndexedDailyGb * (1 + $scenario->hot_replicas) * $scenario->hot_days;
        $warmStorage = $totalIndexedDailyGb * (1 + $scenario->warm_replicas) * $scenario->warm_days;
        $coldStorage = $totalIndexedDailyGb * (1 + $scenario->cold_replicas) * $scenario->cold_days;
        $frozenStorage = $totalIndexedDailyGb * (1 + $scenario->frozen_replicas) * $scenario->frozen_days;

        $totalStorageFootprint = $hotStorage + $warmStorage + $coldStorage + $frozenStorage;
        $totalRawStorageStored = $totalRawDailyGb * $scenario->retention_days;
        $totalIndexedStorageStored = $totalIndexedDailyGb * $scenario->retention_days;

        // 4. Infrastructure Node Recommendations
        $nodes = $this->recommendNodes($scenario, $hotStorage, $warmStorage, $coldStorage, $frozenStorage);

        // Calculate total RAM
        $totalClusterRam = 0;
        foreach ($nodes as $node) {
            $totalClusterRam += $node['count'] * $node['ram_gb'];
        }

        // 5. Licensing ERUs
        $requiredErus = (int) ceil($totalClusterRam / $ramPerEru);
        $annualCost = $requiredErus * $eruCost;

        return [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
            ],
            'scenario' => [
                'id' => $scenario->id,
                'name' => $scenario->name,
                'description' => $scenario->description,
                'workload_profile' => $scenario->workload_profile,
                'retention_days' => $scenario->retention_days,
                'hot_days' => $scenario->hot_days,
                'warm_days' => $scenario->warm_days,
                'cold_days' => $scenario->cold_days,
                'frozen_days' => $scenario->frozen_days,
            ],
            'assets' => $assetCalculations,
            'totals' => [
                'daily_raw_gb' => round($totalRawDailyGb, 2),
                'daily_indexed_gb' => round($totalIndexedDailyGb, 2),
                'daily_ingested_gb' => round($totalIndexedDailyGb * (1 + $scenario->hot_replicas), 2),
                'total_raw_storage_gb' => round($totalRawStorageStored, 2),
                'total_indexed_storage_gb' => round($totalIndexedStorageStored, 2),
                'hot_storage_gb' => round($hotStorage, 2),
                'warm_storage_gb' => round($warmStorage, 2),
                'cold_storage_gb' => round($coldStorage, 2),
                'frozen_storage_gb' => round($frozenStorage, 2),
                'total_storage_footprint_gb' => round($totalStorageFootprint, 2),
            ],
            'nodes' => $nodes,
            'licensing' => [
                'total_ram_gb' => $totalClusterRam,
                'required_erus' => $requiredErus,
                'annual_cost_usd' => $annualCost,
                'eru_cost_usd' => $eruCost,
                'ram_per_eru_gb' => $ramPerEru,
            ],
        ];
    }

    /**
     * Core logic to recommend nodes based on workload and storage required.
     */
    private function recommendNodes(Scenario $scenario, float $hotStorage, float $warmStorage, float $coldStorage, float $frozenStorage): array
    {
        $profile = $scenario->workload_profile;
        $isTiered = ($scenario->warm_days > 0 || $scenario->cold_days > 0 || $scenario->frozen_days > 0);
        $nodes = [];

        if ($profile === 'min') {
            // Hot Nodes
            $hotDisk = $this->roundToNeatDiskSize(max(20, ($hotStorage / 2) * 1.20));
            $hotRamNeeded = $hotDisk / 30;
            $hotRam = $this->getNearestVmProfile($hotRamNeeded, $isTiered ? 24 : 16);

            $nodes[] = [
                'name' => 'hot-node-01',
                'role' => 'Master / Data (Hot)',
                'count' => 1,
                'ram_gb' => $hotRam,
                'heap_gb' => $hotRam / 2,
                'storage_gb' => $hotDisk,
                'storage_type' => 'Local NVMe SSD',
            ];
            $nodes[] = [
                'name' => 'hot-node-02',
                'role' => 'Master / Data (Hot)',
                'count' => 1,
                'ram_gb' => $hotRam,
                'heap_gb' => $hotRam / 2,
                'storage_gb' => $hotDisk,
                'storage_type' => 'Local NVMe SSD',
            ];

            // Master Tiebreaker
            $masterRam = ($hotRam <= 4) ? 1 : (($hotRam <= 16) ? 2 : 4);
            $nodes[] = [
                'name' => 'master-tiebreaker',
                'role' => 'Dedicated Master (Quorum)',
                'count' => 1,
                'ram_gb' => $masterRam,
                'heap_gb' => $masterRam / 2,
                'storage_gb' => 20,
                'storage_type' => 'Local SSD',
            ];

            // Cold Nodes
            if ($scenario->cold_days > 0) {
                $coldDisk = $this->roundToNeatDiskSize(max(20, ($coldStorage / 2) * 1.10));
                $coldRamNeeded = $coldDisk / 400;
                $coldRam = $this->getNearestVmProfile($coldRamNeeded, 8);
                $nodes[] = [
                    'name' => 'cold-node-01',
                    'role' => 'Data (Cold)',
                    'count' => 1,
                    'ram_gb' => $coldRam,
                    'heap_gb' => $coldRam / 2,
                    'storage_gb' => $coldDisk,
                    'storage_type' => 'SATA SSD / HDD (Snapshot Cache)',
                ];
                $nodes[] = [
                    'name' => 'cold-node-02',
                    'role' => 'Data (Cold) (HA)',
                    'count' => 1,
                    'ram_gb' => $coldRam,
                    'heap_gb' => $coldRam / 2,
                    'storage_gb' => $coldDisk,
                    'storage_type' => 'SATA SSD / HDD (Snapshot Cache)',
                ];
            }

            // ML Node
            if ($isTiered) {
                $mlRam = ($hotRam <= 4) ? 2 : (($hotRam <= 16) ? 4 : 16);
                $nodes[] = [
                    'name' => 'ml-node-01',
                    'role' => 'Dedicated Machine Learning',
                    'count' => 1,
                    'ram_gb' => $mlRam,
                    'heap_gb' => $mlRam / 2,
                    'storage_gb' => 50,
                    'storage_type' => 'Local SSD',
                ];
            }

            // Kibana / Fleet Nodes
            $kibanaCount = $isTiered ? 2 : 1;
            $kibanaRam = ($hotRam <= 4) ? 2 : (($hotRam <= 16) ? 4 : 8);
            for ($i = 1; $i <= $kibanaCount; $i++) {
                $nameSuffix = $kibanaCount > 1 ? "-0{$i}" : "";
                $roleText = "Kibana / Fleet Server" . ($i === 2 ? " (HA)" : "");
                $nodes[] = [
                    'name' => "kibana-fleet{$nameSuffix}",
                    'role' => $roleText,
                    'count' => 1,
                    'ram_gb' => $kibanaRam,
                    'heap_gb' => $kibanaRam / 2,
                    'storage_gb' => 50,
                    'storage_type' => 'Local SSD',
                ];
            }

        } elseif ($profile === 'avg') {
            // Hot Nodes
            $hotDisk = $this->roundToNeatDiskSize(max(20, ($hotStorage / 2) * 1.10));
            $hotRamNeeded = $hotDisk / 30;
            $hotRam = $this->getNearestVmProfile($hotRamNeeded, 32);

            // Master Nodes
            $masterRam = ($hotRam <= 4) ? 2 : (($hotRam <= 16) ? 4 : 8);
            $nodes[] = [
                'name' => 'master-node-0[1-3]',
                'role' => 'Dedicated Master (Quorum)',
                'count' => 3,
                'ram_gb' => $masterRam,
                'heap_gb' => $masterRam / 2,
                'storage_gb' => 50,
                'storage_type' => 'Local SSD',
            ];

            $nodes[] = [
                'name' => 'hot-node-01',
                'role' => 'Dedicated Data (Hot)',
                'count' => 1,
                'ram_gb' => $hotRam,
                'heap_gb' => $hotRam / 2,
                'storage_gb' => $hotDisk,
                'storage_type' => 'Local NVMe SSD',
            ];
            $nodes[] = [
                'name' => 'hot-node-02',
                'role' => 'Dedicated Data (Hot)',
                'count' => 1,
                'ram_gb' => $hotRam,
                'heap_gb' => $hotRam / 2,
                'storage_gb' => $hotDisk,
                'storage_type' => 'Local NVMe SSD',
            ];

            // Warm Nodes
            if ($scenario->warm_days > 0) {
                $warmDisk = $this->roundToNeatDiskSize(max(20, ($warmStorage / 2) * 1.10));
                $warmRamNeeded = $warmDisk / 100;
                $warmRam = $this->getNearestVmProfile($warmRamNeeded, 32);
                $nodes[] = [
                    'name' => 'warm-node-01',
                    'role' => 'Dedicated Data (Warm)',
                    'count' => 1,
                    'ram_gb' => $warmRam,
                    'heap_gb' => $warmRam / 2,
                    'storage_gb' => $warmDisk,
                    'storage_type' => 'SATA SSD / HDD',
                ];
                $nodes[] = [
                    'name' => 'warm-node-02',
                    'role' => 'Dedicated Data (Warm)',
                    'count' => 1,
                    'ram_gb' => $warmRam,
                    'heap_gb' => $warmRam / 2,
                    'storage_gb' => $warmDisk,
                    'storage_type' => 'SATA SSD / HDD',
                ];
            }

            // Cold Nodes
            if ($scenario->cold_days > 0) {
                $coldDisk = $this->roundToNeatDiskSize(max(20, ($coldStorage / 1) * 1.10));
                $coldRamNeeded = $coldDisk / 400;
                $coldRam = $this->getNearestVmProfile($coldRamNeeded, 32);
                $nodes[] = [
                    'name' => 'cold-node-01',
                    'role' => 'Dedicated Data (Cold)',
                    'count' => 1,
                    'ram_gb' => $coldRam,
                    'heap_gb' => $coldRam / 2,
                    'storage_gb' => $coldDisk,
                    'storage_type' => 'SATA SSD (Snapshot Cache)',
                ];
            }

            // Frozen Nodes
            if ($scenario->frozen_days > 0) {
                $frozenDisk = $this->roundToNeatDiskSize(max(20, ($frozenStorage * 0.30)));
                $frozenRamNeeded = $frozenDisk / 1000;
                $frozenRam = $this->getNearestVmProfile($frozenRamNeeded, 32);
                $nodes[] = [
                    'name' => 'frozen-node-01',
                    'role' => 'Dedicated Data (Frozen)',
                    'count' => 1,
                    'ram_gb' => $frozenRam,
                    'heap_gb' => $frozenRam / 2,
                    'storage_gb' => $frozenDisk,
                    'storage_type' => 'SATA SSD (Snapshot Cache)',
                ];
            }

            // Kibana Node
            $kibanaRam = ($hotRam <= 4) ? 2 : (($hotRam <= 16) ? 4 : 16);
            $nodes[] = [
                'name' => 'kibana-fleet',
                'role' => 'Kibana / Fleet Server',
                'count' => 1,
                'ram_gb' => $kibanaRam,
                'heap_gb' => $kibanaRam / 2,
                'storage_gb' => 100,
                'storage_type' => 'Local SSD',
            ];

            // Logstash Node
            $logstashRam = ($hotRam <= 4) ? 2 : (($hotRam <= 16) ? 4 : 16);
            $nodes[] = [
                'name' => 'logstash-node',
                'role' => 'Logstash Ingestion',
                'count' => 1,
                'ram_gb' => $logstashRam,
                'heap_gb' => $logstashRam / 2,
                'storage_gb' => 50,
                'storage_type' => 'Local SSD',
            ];

        } elseif ($profile === 'max') {
            // Hot Nodes
            $hotDisk = $this->roundToNeatDiskSize(max(20, ($hotStorage / 2) * 1.10));
            $hotRamNeeded = $hotDisk / 30;
            $hotRam = $this->getNearestVmProfile($hotRamNeeded, 64);

            // Master Nodes
            $masterRam = ($hotRam <= 4) ? 2 : (($hotRam <= 16) ? 4 : 8);
            $nodes[] = [
                'name' => 'master-node-0[1-3]',
                'role' => 'Dedicated Master (Quorum)',
                'count' => 3,
                'ram_gb' => $masterRam,
                'heap_gb' => $masterRam / 2,
                'storage_gb' => 50,
                'storage_type' => 'Local SSD',
            ];

            $nodes[] = [
                'name' => 'hot-node-01',
                'role' => 'Dedicated Data (Hot)',
                'count' => 1,
                'ram_gb' => $hotRam,
                'heap_gb' => $hotRam / 2,
                'storage_gb' => $hotDisk,
                'storage_type' => 'Local NVMe SSD',
            ];
            $nodes[] = [
                'name' => 'hot-node-02',
                'role' => 'Dedicated Data (Hot)',
                'count' => 1,
                'ram_gb' => $hotRam,
                'heap_gb' => $hotRam / 2,
                'storage_gb' => $hotDisk,
                'storage_type' => 'Local NVMe SSD',
            ];

            // Warm Nodes
            if ($scenario->warm_days > 0) {
                $warmCount = ($warmStorage > 10000) ? 3 : 2;
                $warmDisk = $this->roundToNeatDiskSize(max(20, ($warmStorage / $warmCount) * 1.10));
                $warmRamNeeded = $warmDisk / 100;
                $warmRam = $this->getNearestVmProfile($warmRamNeeded, 64);

                for ($i = 1; $i <= $warmCount; $i++) {
                    $nodes[] = [
                        'name' => "warm-node-0{$i}",
                        'role' => 'Dedicated Data (Warm)',
                        'count' => 1,
                        'ram_gb' => $warmRam,
                        'heap_gb' => $warmRam / 2,
                        'storage_gb' => $warmDisk,
                        'storage_type' => 'SATA SSD / HDD',
                    ];
                }
            }

            // Cold Nodes
            if ($scenario->cold_days > 0) {
                $coldDisk = $this->roundToNeatDiskSize(max(20, ($coldStorage / 1) * 1.10));
                $coldRamNeeded = $coldDisk / 400;
                $coldRam = $this->getNearestVmProfile($coldRamNeeded, 32);
                $nodes[] = [
                    'name' => 'cold-node-01',
                    'role' => 'Dedicated Data (Cold)',
                    'count' => 1,
                    'ram_gb' => $coldRam,
                    'heap_gb' => $coldRam / 2,
                    'storage_gb' => $coldDisk,
                    'storage_type' => 'SATA SSD (Snapshot Cache)',
                ];
            }

            // Frozen Nodes
            if ($scenario->frozen_days > 0) {
                $frozenDisk = $this->roundToNeatDiskSize(max(20, ($frozenStorage * 1.05)));
                $frozenRamNeeded = $frozenDisk / 1000;
                $frozenRam = $this->getNearestVmProfile($frozenRamNeeded, 32);
                $nodes[] = [
                    'name' => 'frozen-node-01',
                    'role' => 'Dedicated Data (Frozen)',
                    'count' => 1,
                    'ram_gb' => $frozenRam,
                    'heap_gb' => $frozenRam / 2,
                    'storage_gb' => $frozenDisk,
                    'storage_type' => 'SATA SSD (Snapshot Cache)',
                ];
            }

            // Kibana Node
            $kibanaRam = ($hotRam <= 4) ? 2 : (($hotRam <= 16) ? 4 : 16);
            $nodes[] = [
                'name' => 'kibana-fleet',
                'role' => 'Kibana / Fleet Server',
                'count' => 1,
                'ram_gb' => $kibanaRam,
                'heap_gb' => $kibanaRam / 2,
                'storage_gb' => 100,
                'storage_type' => 'Local SSD',
            ];

            // Logstash Nodes
            $logstashRam = ($hotRam <= 4) ? 2 : (($hotRam <= 16) ? 4 : 16);
            $nodes[] = [
                'name' => 'logstash-node-0[1-2]',
                'role' => 'Logstash Ingestion',
                'count' => 2,
                'ram_gb' => $logstashRam,
                'heap_gb' => $logstashRam / 2,
                'storage_gb' => 50,
                'storage_type' => 'Local SSD',
            ];
        }

        return $nodes;
    }

    /**
     * Finds the nearest standard Elastic Cloud RAM profile.
     */
    private function getNearestVmProfile(float $neededRam, float $maxRam): float
    {
        $profiles = [1, 2, 4, 8, 16, 32, 64, 96, 128, 192, 256, 384, 512];
        $closest = 1.0;
        $minDiff = null;

        foreach ($profiles as $profile) {
            // Capping is only applied if the needed RAM is within the scenario's default maxRam limits.
            // If the needed RAM exceeds the default maxRam, we let it scale up to satisfy the RAM-to-disk ratio.
            if ($neededRam <= $maxRam && $profile > $maxRam) {
                break;
            }
            $diff = abs($neededRam - $profile);
            if ($minDiff === null || $diff < $minDiff) {
                $minDiff = $diff;
                $closest = (float) $profile;
            }
        }
        return $closest;
    }

    /**
     * Rounds a storage value in GB to a neat architectural size.
     */
    private function roundToNeatDiskSize(float $gb): float
    {
        if ($gb <= 20) return 20;
        if ($gb <= 50) return 50;
        if ($gb <= 100) return 100;
        if ($gb <= 500) {
            return ceil($gb / 100) * 100;
        }
        if ($gb <= 1000) {
            return ceil($gb / 100) * 100;
        }
        // Round up to nearest 0.5 TB (500 GB) for disks > 1 TB
        return ceil($gb / 500) * 500;
    }
}
