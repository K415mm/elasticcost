<?php

$modes = ['A1-direct-api', 'A2-loop-no-features', 'B-full-harness'];
$allTraces = [];

foreach ($modes as $mode) {
    $files = glob("testandcompare/traces/{$mode}/*.json");
    sort($files);
    foreach ($files as $f) {
        $allTraces[$mode][] = json_decode(file_get_contents($f), true);
    }
}

// Find failed request indices in B
$bFailed = [];
foreach ($allTraces['B-full-harness'] as $i => $t) {
    if (! $t['success']) {
        $bFailed[] = $i;
    }
}

echo 'B failed request indices (0-based): '.implode(', ', $bFailed)."\n";
echo 'Excluded requests: '.implode(', ', array_map(fn ($i) => '#'.($i + 1).' '.$allTraces['B-full-harness'][$i]['category'], $bFailed))."\n\n";

// Only keep indices where ALL three modes succeeded AND B succeeded
$validIndices = [];
for ($i = 0; $i < 20; $i++) {
    $a1Ok = $allTraces['A1-direct-api'][$i]['success'] ?? false;
    $a2Ok = $allTraces['A2-loop-no-features'][$i]['success'] ?? false;
    $bOk = $allTraces['B-full-harness'][$i]['success'] ?? false;
    if ($a1Ok && $a2Ok && $bOk) {
        $validIndices[] = $i;
    }
}

echo 'Valid requests for fair comparison: '.count($validIndices)."/20\n";
echo 'Indices: '.implode(', ', array_map(fn ($i) => '#'.($i + 1), $validIndices))."\n\n";

// ── Per-mode stats on valid only ─────────────────────────────────
foreach ($modes as $mode) {
    $traces = array_values(array_filter($allTraces[$mode], fn ($t, $i) => in_array($i, $validIndices), ARRAY_FILTER_USE_BOTH));
    $elastic = array_filter($traces, fn ($t) => $t['agent'] === 'ElasticCostAssistant');
    $rgsoc = array_filter($traces, fn ($t) => $t['agent'] === 'RgSocEngineer');

    $latAll = array_column(array_column($traces, 'timing'), 'latency_ms');
    $latE = array_column(array_column(array_values($elastic), 'timing'), 'latency_ms');
    $latR = array_column(array_column(array_values($rgsoc), 'timing'), 'latency_ms');

    $toolCalls = array_sum(array_map(fn ($t) => $t['tool_calls']['count'] ?? 0, $traces));
    $withTools = count(array_filter($traces, fn ($t) => ($t['tool_calls']['count'] ?? 0) > 0));
    $respLens = array_map(fn ($t) => strlen($t['response'] ?? ''), $traces);
    $n = count($traces);

    $tokensTotal = 0;
    foreach ($traces as $t) {
        $tokensTotal += ($t['tokens']['prompt'] ?? 0) + ($t['tokens']['completion'] ?? 0);
    }

    echo "══════════════════════════════════════════\n";
    echo " {$mode} (n={$n} valid)\n";
    echo "══════════════════════════════════════════\n";
    echo sprintf("  Avg latency (all)  : %6.0fms\n", array_sum($latAll) / $n);
    echo sprintf("  Avg latency (EC)   : %6.0fms\n", count($latE) ? array_sum($latE) / count($latE) : 0);
    echo sprintf("  Avg latency (RgSoc): %6.0fms\n", count($latR) ? array_sum($latR) / count($latR) : 0);
    echo sprintf("  Min / Max latency  : %dms / %dms\n", min($latAll), max($latAll));
    echo sprintf("  Tool calls total   : %d\n", $toolCalls);
    echo sprintf("  Requests with tools: %d/%d\n", $withTools, $n);
    echo sprintf("  Avg response length: %.0f chars\n", array_sum($respLens) / $n);
    echo sprintf("  Total tokens used  : %d\n\n", $tokensTotal);
}

// ── Per-request head-to-head on valid only ───────────────────────
echo "══════════════════════════════════════════\n";
echo " FAIR PER-REQUEST COMPARISON (success only)\n";
echo "══════════════════════════════════════════\n";
printf("%-4s %-22s %8s %8s %8s  %10s  %10s  %10s  %s\n",
    '#', 'category', 'A1(ms)', 'A2(ms)', 'B(ms)', 'resp(A1)', 'resp(A2)', 'resp(B)', 'tools(A2/B)');
echo str_repeat('─', 110)."\n";

$winsA1 = 0;
$winsA2 = 0;
$winsB = 0;
$latencyWinA1 = 0;
$latencyWinA2 = 0;
$latencyWinB = 0;
$qualityWinA1 = 0;
$qualityWinA2 = 0;
$qualityWinB = 0;

foreach ($validIndices as $i) {
    $a1 = $allTraces['A1-direct-api'][$i];
    $a2 = $allTraces['A2-loop-no-features'][$i];
    $b = $allTraces['B-full-harness'][$i];

    $latA1 = $a1['timing']['latency_ms'];
    $latA2 = $a2['timing']['latency_ms'];
    $latB = $b['timing']['latency_ms'];

    $respA1 = strlen($a1['response'] ?? '');
    $respA2 = strlen($a2['response'] ?? '');
    $respB = strlen($b['response'] ?? '');

    $toolsA2 = $a2['tool_calls']['count'] ?? 0;
    $toolsB = $b['tool_calls']['count'] ?? 0;

    // Latency winner (lower = better)
    $minLat = min($latA1, $latA2, $latB);
    if ($latA1 === $minLat) {
        $latencyWinA1++;
    } elseif ($latA2 === $minLat) {
        $latencyWinA2++;
    } else {
        $latencyWinB++;
    }

    // Quality score: tool calls (real DB data) weighted heavily + response richness
    $scoreA1 = ($respA1 / 300);
    $scoreA2 = ($toolsA2 * 5) + ($respA2 / 300);
    $scoreB = ($toolsB * 5) + ($respB / 300);

    // Quality winner
    $maxScore = max($scoreA1, $scoreA2, $scoreB);
    if ($scoreA1 === $maxScore) {
        $qualityWinA1++;
    } elseif ($scoreA2 === $maxScore) {
        $qualityWinA2++;
    } else {
        $qualityWinB++;
    }

    // Overall winner (balanced: quality + latency efficiency)
    $effA1 = $scoreA1 / ($latA1 / 1000);
    $effA2 = $scoreA2 / ($latA2 / 1000);
    $effB = $scoreB / ($latB / 1000);
    $maxEff = max($effA1, $effA2, $effB);
    $overallWinner = ($effA1 === $maxEff) ? 'A1' : (($effA2 === $maxEff) ? 'A2' : 'B');

    if ($overallWinner === 'A1') {
        $winsA1++;
    } elseif ($overallWinner === 'A2') {
        $winsA2++;
    } else {
        $winsB++;
    }

    printf("%-4s %-22s %8d %8d %8d  %10d  %10d  %10d  A2=%d B=%d => %s\n",
        '#'.($i + 1),
        substr($a1['category'] ?? 'unknown', 0, 22),
        $latA1, $latA2, $latB,
        $respA1, $respA2, $respB,
        $toolsA2, $toolsB,
        $overallWinner
    );
}

echo str_repeat('─', 110)."\n";
echo "\n── FINAL SCORES (on ".count($validIndices)." fair requests) ──────────────────\n\n";

echo "  LATENCY WINS (fastest response):\n";
echo "    A1: {$latencyWinA1}  A2: {$latencyWinA2}  B: {$latencyWinB}\n\n";

echo "  QUALITY WINS (tools used + response richness):\n";
echo "    A1: {$qualityWinA1}  A2: {$qualityWinA2}  B: {$qualityWinB}\n\n";

echo "  EFFICIENCY WINS (quality per second):\n";
echo "    A1: {$winsA1}  A2: {$winsA2}  B: {$winsB}\n\n";

// ── EC vs RgSoc breakdown ────────────────────────────────────────
echo "── BREAKDOWN BY AGENT ──────────────────────────────\n";
foreach (['ElasticCostAssistant', 'RgSocEngineer'] as $agent) {
    $agentIndices = array_filter($validIndices, fn ($i) => $allTraces['B-full-harness'][$i]['agent'] === $agent);
    $n = count($agentIndices);
    echo "\n  {$agent} ({$n} valid requests):\n";
    foreach (['A1-direct-api' => 'A1', 'A2-loop-no-features' => 'A2', 'B-full-harness' => 'B'] as $mode => $label) {
        $lats = array_map(fn ($i) => $allTraces[$mode][$i]['timing']['latency_ms'], $agentIndices);
        $lens = array_map(fn ($i) => strlen($allTraces[$mode][$i]['response'] ?? ''), $agentIndices);
        $tools = array_sum(array_map(fn ($i) => $allTraces[$mode][$i]['tool_calls']['count'] ?? 0, $agentIndices));
        echo sprintf("    %s: avg=%6.0fms  avg_resp=%5.0f chars  tools=%d\n",
            $label, array_sum($lats) / $n, array_sum($lens) / $n, $tools);
    }
}
