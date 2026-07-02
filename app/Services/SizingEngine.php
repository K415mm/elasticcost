<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientScenarioMsspDetail;
use App\Models\GlobalSetting;
use App\Models\Scenario;

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
        $dailyIngestedGb = $totalIndexedDailyGb * (1 + $scenario->hot_replicas);
        $hotStorage = $dailyIngestedGb * $scenario->hot_days;
        $warmStorage = $dailyIngestedGb * $scenario->warm_days;
        $coldStorage = $dailyIngestedGb * $scenario->cold_days;
        $frozenStorage = $dailyIngestedGb * $scenario->frozen_days;

        $totalStorageFootprint = $hotStorage + $warmStorage + $coldStorage + $frozenStorage;
        $totalRawStorageStored = $totalRawDailyGb * $scenario->retention_days;
        $totalIndexedStorageStored = $totalIndexedDailyGb * $scenario->retention_days;

        // 4. Infrastructure Node Recommendations
        $msspDetail = ClientScenarioMsspDetail::where([
            'client_id' => $client->id,
            'scenario_id' => $scenario->id,
        ])->first();

        if ($msspDetail && ! empty($msspDetail->custom_nodes)) {
            $nodes = [];
            foreach ($msspDetail->custom_nodes as $cn) {
                $ram = (float) $cn['ram_gb'];
                $nodes[] = [
                    'name' => $cn['name'],
                    'role' => $cn['role'],
                    'count' => (int) $cn['count'],
                    'ram_gb' => $ram,
                    'heap_gb' => $ram / 2,
                    'storage_gb' => (float) $cn['storage_gb'],
                    'storage_type' => $cn['storage_type'],
                ];
            }
        } else {
            $nodes = $this->recommendNodes($scenario, $hotStorage, $warmStorage, $coldStorage, $frozenStorage);
        }

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

        // 1. Hot Nodes
        if ($profile === 'min') {
            $hotNodes = $this->recommendTierNodes(
                tierName: 'hot',
                roleName: 'Master / Data (Hot)',
                tierStorage: $hotStorage,
                ratio: 30,
                startCount: 2,
                overheadFactor: 1.20,
                storageType: 'Local NVMe SSD',
                maxRamPerNode: 64.0,
                replicas: $scenario->hot_replicas
            );
        } elseif ($profile === 'avg') {
            $hotNodes = $this->recommendTierNodes(
                tierName: 'hot',
                roleName: 'Dedicated Data (Hot)',
                tierStorage: $hotStorage,
                ratio: 30,
                startCount: 2,
                overheadFactor: 1.10,
                storageType: 'Local NVMe SSD',
                maxRamPerNode: 64.0,
                replicas: $scenario->hot_replicas
            );
        } else { // max
            $hotNodes = $this->recommendTierNodes(
                tierName: 'hot',
                roleName: 'Dedicated Data (Hot)',
                tierStorage: $hotStorage,
                ratio: 30,
                startCount: 3,
                overheadFactor: 1.10,
                storageType: 'Local NVMe SSD',
                maxRamPerNode: 64.0,
                replicas: $scenario->hot_replicas
            );
        }
        $nodes = array_merge($nodes, $hotNodes);
        $hotRam = ! empty($hotNodes) ? $hotNodes[0]['ram_gb'] : 16.0;

        // 2. Master Nodes
        if ($profile === 'min') {
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
        } else {
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
        }

        // 3. Warm Nodes
        if ($scenario->warm_days > 0) {
            $warmNodes = $this->recommendTierNodes(
                tierName: 'warm',
                roleName: $profile === 'min' ? 'Data (Warm)' : 'Dedicated Data (Warm)',
                tierStorage: $warmStorage,
                ratio: 80,
                startCount: $profile === 'max' ? 3 : 2,
                overheadFactor: 1.10,
                storageType: 'SATA SSD / HDD',
                maxRamPerNode: 64.0,
                replicas: $scenario->warm_replicas
            );
            $nodes = array_merge($nodes, $warmNodes);
        }

        // 4. Cold Nodes
        if ($scenario->cold_days > 0) {
            $coldNodes = $this->recommendTierNodes(
                tierName: 'cold',
                roleName: $profile === 'min' ? 'Data (Cold)' : 'Dedicated Data (Cold)',
                tierStorage: $coldStorage,
                ratio: 100,
                startCount: $profile === 'max' ? 3 : ($profile === 'avg' ? 1 : 2),
                overheadFactor: 1.10,
                storageType: 'SATA SSD (Snapshot Cache)',
                maxRamPerNode: 64.0,
                replicas: $scenario->cold_replicas
            );
            if ($profile === 'min' && count($coldNodes) >= 2) {
                $coldNodes[1]['role'] = 'Data (Cold) (HA)';
            }
            $nodes = array_merge($nodes, $coldNodes);
        }

        // 5. Frozen Nodes
        if ($scenario->frozen_days > 0) {
            $frozenNodes = $this->recommendTierNodes(
                tierName: 'frozen',
                roleName: $profile === 'min' ? 'Data (Frozen)' : 'Dedicated Data (Frozen)',
                tierStorage: $frozenStorage,
                ratio: 160,
                startCount: $profile === 'max' ? 3 : 1,
                overheadFactor: $profile === 'max' ? 1.05 : 0.30,
                storageType: 'SATA SSD (Snapshot Cache)',
                maxRamPerNode: 64.0,
                replicas: $scenario->frozen_replicas
            );
            $nodes = array_merge($nodes, $frozenNodes);
        }

        // 6. Management Nodes (Kibana, Fleet, ML)
        $kibanaCount = ($profile === 'max' || $isTiered) ? 2 : 1;
        $kibanaRam = ($hotRam <= 4) ? 2 : (($hotRam <= 16) ? 4 : 16);
        for ($i = 1; $i <= $kibanaCount; $i++) {
            $nameSuffix = $kibanaCount > 1 ? "-0{$i}" : '';
            $roleText = 'Kibana / Fleet Server'.($i === 2 ? ' (HA)' : '');
            $nodes[] = [
                'name' => "kibana-fleet{$nameSuffix}",
                'role' => $roleText,
                'count' => 1,
                'ram_gb' => $kibanaRam,
                'heap_gb' => $kibanaRam / 2,
                'storage_gb' => $profile === 'min' ? 50 : 100,
                'storage_type' => 'Local SSD',
            ];
        }

        if ($isTiered) {
            $mlCount = $profile === 'max' ? 2 : 1;
            $mlRam = ($hotRam <= 4) ? 2 : (($hotRam <= 16) ? 4 : 16);
            for ($i = 1; $i <= $mlCount; $i++) {
                $nameSuffix = $mlCount > 1 ? "-0{$i}" : '';
                $roleText = 'Dedicated Machine Learning'.($i === 2 ? ' (HA)' : '');
                $nodes[] = [
                    'name' => "ml-node{$nameSuffix}",
                    'role' => $roleText,
                    'count' => 1,
                    'ram_gb' => $mlRam,
                    'heap_gb' => $mlRam / 2,
                    'storage_gb' => 50,
                    'storage_type' => 'Local SSD',
                ];
            }
        }

        // 7. Logstash Ingestion
        if ($profile === 'avg') {
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
     * Recommends nodes for a specific data tier by scaling out if RAM exceeds 64GB.
     */
    private function recommendTierNodes(
        string $tierName,
        string $roleName,
        float $tierStorage,
        float $ratio,
        int $startCount,
        float $overheadFactor,
        string $storageType,
        float $maxRamPerNode = 64.0,
        int $replicas = 0
    ): array {
        if ($tierStorage <= 0.0) {
            return [];
        }

        $minNodes = 1 + $replicas;
        $count = max($startCount, $minNodes);
        $maxNodesLimit = 3;

        while (true) {
            $diskPerNode = max(20.0, ($tierStorage / $count) * $overheadFactor);
            $roundedDisk = $this->roundToNeatDiskSize($diskPerNode);
            $ramNeeded = $roundedDisk / $ratio;

            // Find nearest VM profile. Capped at $maxRamPerNode (64.0)
            $ram = $this->getNearestVmProfile($ramNeeded, $maxRamPerNode);

            // Scale out by adding a node if RAM exceeds cap, but only up to 3 nodes
            if (($ramNeeded > $maxRamPerNode || $ram > $maxRamPerNode) && $count < $maxNodesLimit) {
                $count++;

                continue;
            }

            break;
        }

        $nodes = [];
        for ($i = 1; $i <= $count; $i++) {
            $nameSuffix = $count > 1 ? sprintf('-0%d', $i) : '';
            $nodes[] = [
                'name' => strtolower($tierName).'-node'.$nameSuffix,
                'role' => $roleName,
                'count' => 1,
                'ram_gb' => $ram,
                'heap_gb' => $ram / 2,
                'storage_gb' => $roundedDisk,
                'storage_type' => $storageType,
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
            if ($profile > $maxRam) {
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
        if ($gb <= 20) {
            return 20;
        }
        if ($gb <= 50) {
            return 50;
        }
        if ($gb <= 100) {
            return 100;
        }
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
