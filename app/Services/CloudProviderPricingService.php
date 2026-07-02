<?php

namespace App\Services;

class CloudProviderPricingService
{
    /**
     * Create a new class instance.
     */
    protected array $pricingData = [];

    protected array $elasticCloudPricing = [];

    public function __construct()
    {
        $path = public_path('assets/json/xpress_azure_prices.json');
        if (file_exists($path)) {
            $this->pricingData = json_decode(file_get_contents($path), true) ?? [];
        }

        $cloudPath = public_path('assets/json/elastic_cloud_prices.json');
        if (file_exists($cloudPath)) {
            $this->elasticCloudPricing = json_decode(file_get_contents($cloudPath), true) ?? [];
        }
    }

    /**
     * Match a node type to an Elastic Cloud SKU name and calculate hourly/monthly rate.
     */
    public function matchElasticCloudNode(string $nodeName, string $nodeRole, string $tier = 'platinum'): ?array
    {
        $regionData = $this->elasticCloudPricing['azure-eastus2'] ?? [];
        if (empty($regionData)) {
            return null;
        }

        $nodeRoleLower = strtolower($nodeRole);
        $nodeNameLower = strtolower($nodeName);

        $sku = 'azure.es.datahot.edsv4'; // default fallback

        if (str_contains($nodeRoleLower, 'master')) {
            $sku = 'azure.es.master.fsv2';
        } elseif (str_contains($nodeRoleLower, 'learning') || str_contains($nodeRoleLower, 'ml')) {
            $sku = 'azure.es.ml.fsv2';
        } elseif (str_contains($nodeRoleLower, 'warm')) {
            $sku = 'azure.es.datawarm.edsv4';
        } elseif (str_contains($nodeRoleLower, 'cold')) {
            $sku = 'azure.es.datacold.edsv4';
        } elseif (str_contains($nodeRoleLower, 'frozen')) {
            $sku = 'azure.es.datafrozen.edsv4';
        } elseif (str_contains($nodeRoleLower, 'kibana') || str_contains($nodeRoleLower, 'fleet') || str_contains($nodeRoleLower, 'server') || str_contains($nodeRoleLower, 'apm')) {
            $sku = 'azure.apm.e32sv3';
        }

        $rates = $regionData[$sku] ?? null;
        if (! $rates) {
            return null;
        }

        $tierKey = strtolower($tier);
        if (! isset($rates[$tierKey])) {
            $tierKey = 'platinum';
        }

        $hourlyRate = (float) ($rates[$tierKey] ?? 0.0);

        return [
            'sku' => $sku,
            'hourly_rate' => $hourlyRate,
            'tier' => $tierKey,
        ];
    }

    /**
     * Get available datacenters (sheet names).
     *
     * @return array<string>
     */
    public function getDatacenters(): array
    {
        return array_keys($this->pricingData);
    }

    /**
     * Match the cheapest VM in the specified datacenter that has RAM >= $requiredRam.
     * Elasticsearch runs on Linux, so we use 'price_linux_tnd'.
     *
     * @return array{name: string, vcpu: int, ram_gb: float, price_windows_tnd: float, price_linux_tnd: float}|null
     */
    public function matchVm(string $datacenter, float $requiredRam): ?array
    {
        if (! isset($this->pricingData[$datacenter]['vms'])) {
            return null;
        }

        $bestVm = null;
        $lowestPrice = null;

        foreach ($this->pricingData[$datacenter]['vms'] as $vm) {
            if ($vm['ram_gb'] >= $requiredRam) {
                $price = (float) $vm['price_linux_tnd'];
                if ($lowestPrice === null || $price < $lowestPrice) {
                    $lowestPrice = $price;
                    $bestVm = $vm;
                }
            }
        }

        return $bestVm;
    }

    /**
     * Match disk size(s) that satisfy $requiredStorage.
     *
     * @return array{description: string, total_size_gb: float, total_price_tnd: float, disks: array<array{name: string, size_gb: float, price_tnd: float, count: int}>}|null
     */
    public function matchDisk(string $datacenter, float $requiredStorage): ?array
    {
        if (! isset($this->pricingData[$datacenter]['disks']) || empty($this->pricingData[$datacenter]['disks'])) {
            return null;
        }

        $availableDisks = $this->pricingData[$datacenter]['disks'];

        $unitDisk = null;
        $profiles = [];

        foreach ($availableDisks as $disk) {
            if ($disk['name'] === 'Unit') {
                $unitDisk = $disk;
            } else {
                $profiles[] = $disk;
            }
        }

        usort($profiles, function (array $a, array $b): int {
            return $a['size_gb'] <=> $b['size_gb'];
        });

        $maxProfileSize = ! empty($profiles) ? end($profiles)['size_gb'] : 0.0;

        if ($requiredStorage <= $maxProfileSize) {
            foreach ($profiles as $profile) {
                if ($profile['size_gb'] >= $requiredStorage) {
                    return [
                        'description' => "{$profile['name']} ({$profile['size_gb']} GB) {$profile['type']}",
                        'total_size_gb' => (float) $profile['size_gb'],
                        'total_price_tnd' => (float) $profile['price_tnd'],
                        'disks' => [
                            ['name' => $profile['name'], 'size_gb' => (float) $profile['size_gb'], 'price_tnd' => (float) $profile['price_tnd'], 'count' => 1],
                        ],
                    ];
                }
            }
        }

        $p30 = end($profiles);
        $p30Size = (float) $p30['size_gb'];

        $p30Count = (int) floor($requiredStorage / $p30Size);
        $remainder = $requiredStorage - ($p30Count * $p30Size);

        $matchedDisks = [];
        $matchedDisks[] = [
            'name' => $p30['name'],
            'size_gb' => $p30Size,
            'price_tnd' => (float) $p30['price_tnd'],
            'count' => $p30Count,
        ];

        $totalPrice = (float) $p30['price_tnd'] * $p30Count;
        $totalSize = $p30Size * $p30Count;
        $descParts = ["{$p30Count}x {$p30['name']} (".($p30Size * $p30Count).' GB)'];

        if ($remainder > 0) {
            $foundRemainderProfile = false;
            foreach ($profiles as $profile) {
                if ($profile['size_gb'] >= $remainder) {
                    $matchedDisks[] = [
                        'name' => $profile['name'],
                        'size_gb' => (float) $profile['size_gb'],
                        'price_tnd' => (float) $profile['price_tnd'],
                        'count' => 1,
                    ];
                    $totalPrice += (float) $profile['price_tnd'];
                    $totalSize += (float) $profile['size_gb'];
                    $descParts[] = "1x {$profile['name']} ({$profile['size_gb']} GB)";
                    $foundRemainderProfile = true;
                    break;
                }
            }

            if (! $foundRemainderProfile && $unitDisk) {
                $unitPrice = (float) $unitDisk['price_tnd'];
                $remainderPrice = $remainder * $unitPrice;
                $matchedDisks[] = [
                    'name' => 'Custom Storage',
                    'size_gb' => $remainder,
                    'price_tnd' => $remainderPrice,
                    'count' => 1,
                ];
                $totalPrice += $remainderPrice;
                $totalSize += $remainder;
                $descParts[] = "{$remainder} GB Custom Storage";
            }
        }

        $type = ! empty($profiles) ? $profiles[0]['type'] : 'Disk';

        return [
            'description' => implode(' + ', $descParts)." {$type}",
            'total_size_gb' => $totalSize,
            'total_price_tnd' => $totalPrice,
            'disks' => $matchedDisks,
        ];
    }
}
