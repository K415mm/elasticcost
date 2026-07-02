<?php

$coldFiles = glob('testandcompare/traces/B-full-harness/*.json');
$warmFiles = glob('testandcompare-warm/traces/B-full-harness/*.json');
sort($coldFiles);
sort($warmFiles);

$cold = array_map(fn ($f) => json_decode(file_get_contents($f), true), $coldFiles);
$warm = array_map(fn ($f) => json_decode(file_get_contents($f), true), $warmFiles);

echo "══════════════════════════════════════════════════════════════\n";
echo " B-COLD vs B-WARM — POWER OF MEMORY & CACHE\n";
echo " Pre-loaded: 84 cognitive facts | 182 quantum nodes | 16 cache entries\n";
echo "══════════════════════════════════════════════════════════════\n\n";

$totalColdLat = 0;
$totalWarmLat = 0;
$cacheHits = 0;
$quantumInjected = 0;
$cognitiveUsed = 0;
$warmFaster = 0;
$coldFaster = 0;
$warmRicher = 0;
$coldRicher = 0;

printf("%-4s %-22s %9s %9s %8s %8s %8s  %s\n",
    '#', 'category', 'COLD(ms)', 'WARM(ms)', 'Δ speed', 'resp(C)', 'resp(W)', 'notes');
echo str_repeat('─', 100)."\n";

for ($i = 0; $i < count($cold); $i++) {
    $c = $cold[$i] ?? null;
    $w = $warm[$i] ?? null;
    if (! $c || ! $w) {
        continue;
    }

    $latC = $c['timing']['latency_ms'];
    $latW = $w['timing']['latency_ms'];
    $respC = strlen($c['response'] ?? '');
    $respW = strlen($w['response'] ?? '');
    $diff = $latC - $latW;
    $pct = $latC > 0 ? round($diff / $latC * 100) : 0;

    $totalColdLat += $latC;
    $totalWarmLat += $latW;

    if ($latW < $latC) {
        $warmFaster++;
    } else {
        $coldFaster++;
    }
    if ($respW > $respC) {
        $warmRicher++;
    } else {
        $coldRicher++;
    }

    // Detect cache hits (very fast responses)
    $notes = [];
    if ($latW < 500) {
        $notes[] = 'CACHE HIT';
        $cacheHits++;
    } elseif ($latW < $latC * 0.5) {
        $notes[] = 'faster';
    }

    printf("%-4s %-22s %9d %9d %+8d%% %8d %8d  %s\n",
        '#'.($i + 1),
        substr($c['category'] ?? '', 0, 22),
        $latC, $latW, $pct,
        $respC, $respW,
        implode(', ', $notes)
    );
}

echo str_repeat('─', 100)."\n\n";

$n = count($cold);
echo "══ SUMMARY ════════════════════════════════════════════════════\n";
printf("  Avg latency  COLD : %6.0fms\n", $totalColdLat / $n);
printf("  Avg latency  WARM : %6.0fms\n", $totalWarmLat / $n);
printf("  Speed improvement: %+.1f%%\n", ($totalColdLat - $totalWarmLat) / $totalColdLat * 100);
printf("  Cache hits        : %d/%d\n", $cacheHits, $n);
printf("  Warm faster       : %d/%d requests\n", $warmFaster, $n);
printf("  Warm richer resp  : %d/%d requests\n", $warmRicher, $n);
