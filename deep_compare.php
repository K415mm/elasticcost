<?php

$modes = ['A1-direct-api', 'A2-loop-no-features', 'B-full-harness'];
$allTraces = [];

foreach ($modes as $mode) {
    $files = glob("testandcompare/traces/{$mode}/*.json");
    foreach ($files as $f) {
        $allTraces[$mode][] = json_decode(file_get_contents($f), true);
    }
}

// ── Per-mode stats ──────────────────────────────────────────────
foreach ($modes as $mode) {
    $traces = $allTraces[$mode];
    $elastic = array_filter($traces, fn ($t) => $t['agent'] === 'ElasticCostAssistant');
    $rgsoc = array_filter($traces, fn ($t) => $t['agent'] === 'RgSocEngineer');

    $latAll = array_column(array_column($traces, 'timing'), 'latency_ms');
    $latE = array_column(array_column(array_values($elastic), 'timing'), 'latency_ms');
    $latR = array_column(array_column(array_values($rgsoc), 'timing'), 'latency_ms');

    $success = count(array_filter($traces, fn ($t) => $t['success']));
    $errors = count(array_filter($traces, fn ($t) => ! $t['success']));
    $toolCalls = array_sum(array_map(fn ($t) => $t['tool_calls']['count'] ?? 0, $traces));
    $tokensP = array_sum(array_map(fn ($t) => $t['tokens']['prompt'] ?? 0, $traces));
    $tokensC = array_sum(array_map(fn ($t) => $t['tokens']['completion'] ?? 0, $traces));
    $respLens = array_map(fn ($t) => strlen($t['response'] ?? ''), $traces);

    // Response quality proxy: avg response length
    $avgRespLen = array_sum($respLens) / count($respLens);

    // Tool effectiveness: requests that used tools
    $withTools = count(array_filter($traces, fn ($t) => ($t['tool_calls']['count'] ?? 0) > 0));

    echo "\n══════════════════════════════════════════\n";
    echo " {$mode}\n";
    echo "══════════════════════════════════════════\n";
    echo sprintf("  Success rate       : %d/20 (%.0f%%)\n", $success, $success / 20 * 100);
    echo sprintf("  Errors             : %d\n", $errors);
    echo sprintf("  Avg latency (all)  : %.0fms\n", array_sum($latAll) / count($latAll));
    echo sprintf("  Avg latency (EC)   : %.0fms\n", count($latE) ? array_sum($latE) / count($latE) : 0);
    echo sprintf("  Avg latency (RgSoc): %.0fms\n", count($latR) ? array_sum($latR) / count($latR) : 0);
    echo sprintf("  Min latency        : %dms\n", min($latAll));
    echo sprintf("  Max latency        : %dms\n", max($latAll));
    echo sprintf("  Tool calls total   : %d\n", $toolCalls);
    echo sprintf("  Requests with tools: %d/20\n", $withTools);
    echo sprintf("  Prompt tokens      : %d\n", $tokensP);
    echo sprintf("  Completion tokens  : %d\n", $tokensC);
    echo sprintf("  Total tokens       : %d\n", $tokensP + $tokensC);
    echo sprintf("  Avg response len   : %.0f chars\n", $avgRespLen);
}

// ── Per-request head-to-head ────────────────────────────────────
echo "\n══════════════════════════════════════════\n";
echo " PER-REQUEST: Winner Analysis\n";
echo "══════════════════════════════════════════\n";

$winsA1 = 0;
$winsA2 = 0;
$winsB = 0;
$draws = 0;

for ($i = 0; $i < 20; $i++) {
    $a1 = $allTraces['A1-direct-api'][$i] ?? null;
    $a2 = $allTraces['A2-loop-no-features'][$i] ?? null;
    $b = $allTraces['B-full-harness'][$i] ?? null;

    if (! $a1 || ! $a2 || ! $b) {
        continue;
    }

    $latA1 = $a1['timing']['latency_ms'];
    $latA2 = $a2['timing']['latency_ms'];
    $latB = $b['timing']['latency_ms'];

    $respA1 = strlen($a1['response'] ?? '');
    $respA2 = strlen($a2['response'] ?? '');
    $respB = strlen($b['response'] ?? '');

    $toolsB = $b['tool_calls']['count'] ?? 0;
    $toolsA2 = $a2['tool_calls']['count'] ?? 0;

    // Quality score: tools used + response richness + success bonus
    $scoreA1 = ($a1['success'] ? 10 : 0) + ($respA1 / 200);
    $scoreA2 = ($a2['success'] ? 10 : 0) + ($toolsA2 * 3) + ($respA2 / 200);
    $scoreB = ($b['success'] ? 10 : 0) + ($toolsB * 3) + ($respB / 200);

    $winner = 'DRAW';
    if ($scoreB > $scoreA1 && $scoreB > $scoreA2) {
        $winner = 'B';
        $winsB++;
    } elseif ($scoreA2 > $scoreA1 && $scoreA2 > $scoreB) {
        $winner = 'A2';
        $winsA2++;
    } elseif ($scoreA1 > $scoreA2 && $scoreA1 > $scoreB) {
        $winner = 'A1';
        $winsA1++;
    } else {
        $draws++;
    }

    echo sprintf(
        "#%02d %-20s  A1:%6dms  A2:%6dms  B:%6dms  tools(A2=%d,B=%d)  resp(A1=%d,A2=%d,B=%d)  => %s\n",
        $i + 1,
        substr($a1['category'] ?? '', 0, 20),
        $latA1, $latA2, $latB,
        $toolsA2, $toolsB,
        $respA1, $respA2, $respB,
        $winner
    );
}

echo "\n── Overall Winner Score ──────────────────\n";
echo "  A1 wins: {$winsA1}\n";
echo "  A2 wins: {$winsA2}\n";
echo "  B  wins: {$winsB}\n";
echo "  Draws  : {$draws}\n";

// ── Response quality: B vs A1 improvement ──────────────────────
echo "\n── Response Length (proxy for richness) ─\n";
for ($i = 0; $i < 20; $i++) {
    $a1 = $allTraces['A1-direct-api'][$i] ?? null;
    $b = $allTraces['B-full-harness'][$i] ?? null;
    if (! $a1 || ! $b) {
        continue;
    }
    $lenA1 = strlen($a1['response'] ?? '');
    $lenB = strlen($b['response'] ?? '');
    $diff = $lenB - $lenA1;
    $pct = $lenA1 > 0 ? round($diff / $lenA1 * 100) : 0;
    echo sprintf("#%02d  A1:%5d  B:%5d  diff:%+5d (%+d%%)\n", $i + 1, $lenA1, $lenB, $diff, $pct);
}
