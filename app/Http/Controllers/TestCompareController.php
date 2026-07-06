<?php

namespace App\Http\Controllers;

use App\Services\TestCompare\TestDataset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class TestCompareController extends Controller
{
    /**
     * Display the test comparison dashboard.
     */
    public function index()
    {
        $dataset = TestDataset::all();
        $baseDir = base_path('testandcompare');

        // Load from 'latest' symlink (points to runs/{run_id})
        $latestDir = is_link($baseDir.'/latest') ? $baseDir.'/latest' : $baseDir;
        $hasSummary = file_exists($latestDir.'/comparison-summary.json');
        $summary = $hasSummary
            ? json_decode(file_get_contents($latestDir.'/comparison-summary.json'), true)
            : null;

        // Extract run metadata
        $runMeta = $summary['_meta'] ?? null;
        unset($summary['_meta']);

        // Load all traces from latest run directory — even if summary isn't written yet
        $traces = [];
        $traceFreshness = [];
        $allModes = ['A1-direct-api', 'A2-loop-no-features', 'B-full-harness', 'B-warm-harness'];
        $hasTraces = false;

        foreach ($allModes as $mode) {
            $dir = $latestDir.'/traces/'.$mode;
            if (is_dir($dir)) {
                $files = glob($dir.'/request-*.json');
                foreach ($files as $file) {
                    $data = json_decode(file_get_contents($file), true);
                    if (is_array($data)) {
                        $traces[$mode][] = $data;
                        $hasTraces = true;
                    }
                }
                if (isset($traces[$mode])) {
                    usort($traces[$mode], fn ($a, $b) => ($a['request_index'] ?? 0) <=> ($b['request_index'] ?? 0));
                }
            }
        }

        // If we have traces but no summary (run in progress), compute partial summary
        if ($hasTraces && ! $summary) {
            $summary = $this->computePartialSummary($traces);
        }

        $hasResults = $hasSummary || $hasTraces;

        if ($hasTraces) {
            // Check trace freshness — are all traces from the same run?
            $runIds = [];
            foreach ($traces as $modeTraces) {
                foreach ($modeTraces as $t) {
                    if (isset($t['run_id'])) {
                        $runIds[$t['run_id']] = true;
                    }
                }
            }
            $traceFreshness['unique_run_ids'] = array_keys($runIds);
            $traceFreshness['is_single_run'] = count($runIds) <= 1;
            $traceFreshness['trace_count'] = array_sum(array_map('count', $traces));

            // If no summary meta, build partial meta from traces
            if (! $runMeta && $hasTraces) {
                $runMeta = [
                    'run_id' => array_key_first($runIds) ?? null,
                    'run_start' => null,
                    'run_end' => null,
                    'total_modes' => 4,
                    'total_executions' => 68,
                    'modes_run' => array_keys($traces),
                    'in_progress' => true,
                ];
            }

            // Check file modification times for staleness
            $newestMtime = 0;
            $oldestMtime = PHP_INT_MAX;
            foreach ($allModes as $mode) {
                $dir = $latestDir.'/traces/'.$mode;
                if (is_dir($dir)) {
                    $files = glob($dir.'/request-*.json');
                    foreach ($files as $file) {
                        $mtime = filemtime($file);
                        $newestMtime = max($newestMtime, $mtime);
                        $oldestMtime = min($oldestMtime, $mtime);
                    }
                }
            }
            $traceFreshness['newest_trace'] = $newestMtime > 0 ? date('Y-m-d H:i:s', $newestMtime) : null;
            $traceFreshness['oldest_trace'] = $oldestMtime < PHP_INT_MAX ? date('Y-m-d H:i:s', $oldestMtime) : null;
            $traceFreshness['span_minutes'] = $newestMtime > 0 && $oldestMtime < PHP_INT_MAX
                ? round(($newestMtime - $oldestMtime) / 60, 1)
                : 0;
        }

        // List available runs for history
        $runsDir = $baseDir.'/runs';
        $availableRuns = [];
        if (is_dir($runsDir)) {
            $runDirs = glob($runsDir.'/*', GLOB_ONLYDIR);
            rsort($runDirs);
            foreach (array_slice($runDirs, 0, 10) as $runDir) {
                $runId = basename($runDir);
                $runSummaryFile = $runDir.'/comparison-summary.json';
                $availableRuns[] = [
                    'id' => $runId,
                    'date' => date('Y-m-d H:i', strtotime(substr($runId, 0, 8).' '.substr($runId, 9, 2).':'.substr($runId, 11, 2).':'.substr($runId, 13, 2))),
                    'has_summary' => file_exists($runSummaryFile),
                    'trace_count' => is_dir($runDir.'/traces') ? count(glob($runDir.'/traces/*/request-*.json')) : 0,
                ];
            }
        }

        // Compute cross-mode analytics
        $analytics = $this->computeAnalytics($summary, $traces);

        $reportPath = $latestDir.'/comparison-report.md';
        $reportContent = file_exists($reportPath) ? file_get_contents($reportPath) : null;

        return view('test-compare.index', compact(
            'dataset',
            'summary',
            'traces',
            'hasResults',
            'reportContent',
            'runMeta',
            'traceFreshness',
            'analytics',
            'availableRuns',
        ));
    }

    /**
     * Compute a partial summary from traces when the run is still in progress
     * and no comparison-summary.json has been written yet.
     */
    private function computePartialSummary(array $traces): array
    {
        $summary = [];

        foreach ($traces as $mode => $modeTraces) {
            if (empty($modeTraces)) {
                continue;
            }

            $latencies = array_map(fn ($t) => $t['timing']['latency_ms'] ?? 0, $modeTraces);
            $totalTokens = array_map(fn ($t) => $t['tokens']['total_tokens'] ?? 0, $modeTraces);
            $toolCallCounts = array_map(fn ($t) => $t['tool_calls']['count'] ?? 0, $modeTraces);
            $responseLengths = array_map(fn ($t) => $t['response_length'] ?? 0, $modeTraces);
            $successCount = count(array_filter($modeTraces, fn ($t) => $t['success'] ?? false));
            $count = max(count($modeTraces), 1);

            $summary[$mode] = [
                'total_requests' => count($modeTraces),
                'successful' => $successCount,
                'failed' => count($modeTraces) - $successCount,
                'avg_latency_ms' => (int) round(array_sum($latencies) / $count),
                'min_latency_ms' => empty($latencies) ? 0 : min($latencies),
                'max_latency_ms' => empty($latencies) ? 0 : max($latencies),
                'avg_total_tokens' => (int) round(array_sum($totalTokens) / $count),
                'avg_tool_calls' => round(array_sum($toolCallCounts) / $count, 2),
                'avg_response_length' => (int) round(array_sum($responseLengths) / $count),
                'pipeline_stages_avg' => (int) round(array_sum(array_map(fn ($t) => count($t['pipeline_stages'] ?? []), $modeTraces)) / $count),
            ];
        }

        return $summary;
    }

    /**
     * Compute cross-mode analytics for the dashboard.
     */
    private function computeAnalytics(?array $summary, array $traces): array
    {
        if (! $summary) {
            return [];
        }

        $modes = ['A1-direct-api', 'A2-loop-no-features', 'B-full-harness', 'B-warm-harness'];
        $result = [
            'latency_comparison' => [],
            'token_comparison' => [],
            'overhead_breakdown' => [],
            'cache_impact' => [],
            'efficiency_ratios' => [],
            'per_request_deltas' => [],
            'features_matrix' => [],
        ];

        // Latency comparison
        foreach ($modes as $mode) {
            if (isset($summary[$mode])) {
                $result['latency_comparison'][$mode] = [
                    'avg' => $summary[$mode]['avg_latency_ms'],
                    'min' => $summary[$mode]['min_latency_ms'],
                    'max' => $summary[$mode]['max_latency_ms'],
                    'vs_a1' => isset($summary['A1-direct-api'])
                        ? round($summary[$mode]['avg_latency_ms'] / max($summary['A1-direct-api']['avg_latency_ms'], 1) * 100) - 100
                        : null,
                ];
            }
        }

        // Token comparison
        foreach ($modes as $mode) {
            if (isset($summary[$mode])) {
                $result['token_comparison'][$mode] = [
                    'avg_total' => $summary[$mode]['avg_total_tokens'],
                    'vs_a1' => isset($summary['A1-direct-api'])
                        ? round($summary[$mode]['avg_total_tokens'] / max($summary['A1-direct-api']['avg_total_tokens'], 1) * 100) - 100
                        : null,
                ];
            }
        }

        // Overhead breakdown: A1 → A2 (loop overhead), A2 → B-cold (harness overhead)
        $a1Lat = $summary['A1-direct-api']['avg_latency_ms'] ?? 0;
        $a2Lat = $summary['A2-loop-no-features']['avg_latency_ms'] ?? 0;
        $bcLat = $summary['B-full-harness']['avg_latency_ms'] ?? 0;
        $bwLat = $summary['B-warm-harness']['avg_latency_ms'] ?? 0;

        $result['overhead_breakdown'] = [
            'a1_baseline' => $a1Lat,
            'a2_loop_overhead_ms' => $a2Lat - $a1Lat,
            'a2_loop_overhead_pct' => $a1Lat > 0 ? round(($a2Lat - $a1Lat) / $a1Lat * 100) : 0,
            'b_cold_harness_overhead_ms' => $bcLat - $a2Lat,
            'b_cold_harness_overhead_pct' => $a2Lat > 0 ? round(($bcLat - $a2Lat) / $a2Lat * 100) : 0,
            'b_warm_vs_cold_ms' => $bwLat - $bcLat,
            'b_warm_vs_cold_pct' => $bcLat > 0 ? round(($bwLat - $bcLat) / $bcLat * 100) : 0,
            'total_overhead_a1_to_b_cold_ms' => $bcLat - $a1Lat,
            'total_overhead_a1_to_b_cold_pct' => $a1Lat > 0 ? round(($bcLat - $a1Lat) / $a1Lat * 100) : 0,
        ];

        // Cache impact: B-cold vs B-warm
        if (isset($summary['B-full-harness']) && isset($summary['B-warm-harness'])) {
            $result['cache_impact'] = [
                'cold_avg_latency' => $bcLat,
                'warm_avg_latency' => $bwLat,
                'latency_saved_ms' => $bcLat - $bwLat,
                'latency_saved_pct' => $bcLat > 0 ? round(($bcLat - $bwLat) / $bcLat * 100) : 0,
                'cold_avg_tokens' => $summary['B-full-harness']['avg_total_tokens'],
                'warm_avg_tokens' => $summary['B-warm-harness']['avg_total_tokens'],
                'token_delta' => $summary['B-warm-harness']['avg_total_tokens'] - $summary['B-full-harness']['avg_total_tokens'],
                'cold_tool_calls' => $summary['B-full-harness']['avg_tool_calls'],
                'warm_tool_calls' => $summary['B-warm-harness']['avg_tool_calls'],
            ];
        }

        // Efficiency ratios
        foreach ($modes as $mode) {
            if (isset($summary[$mode]) && $summary[$mode]['avg_latency_ms'] > 0) {
                $result['efficiency_ratios'][$mode] = [
                    'tokens_per_ms' => round($summary[$mode]['avg_total_tokens'] / $summary[$mode]['avg_latency_ms'], 3),
                    'chars_per_ms' => round($summary[$mode]['avg_response_length'] / $summary[$mode]['avg_latency_ms'], 3),
                    'ms_per_token' => round($summary[$mode]['avg_latency_ms'] / max($summary[$mode]['avg_total_tokens'], 1), 1),
                ];
            }
        }

        // Per-request deltas (A1 vs A2 vs B-cold vs B-warm)
        $maxRequests = max(
            count($traces['A1-direct-api'] ?? []),
            count($traces['A2-loop-no-features'] ?? []),
            count($traces['B-full-harness'] ?? []),
            count($traces['B-warm-harness'] ?? []),
        );

        for ($i = 0; $i < $maxRequests; $i++) {
            $a1 = $traces['A1-direct-api'][$i] ?? null;
            $a2 = $traces['A2-loop-no-features'][$i] ?? null;
            $bc = $traces['B-full-harness'][$i] ?? null;
            $bw = $traces['B-warm-harness'][$i] ?? null;

            $a1Lat = $a1['timing']['latency_ms'] ?? 0;
            $a2Lat = $a2['timing']['latency_ms'] ?? 0;
            $bcLat = $bc['timing']['latency_ms'] ?? 0;
            $bwLat = $bw['timing']['latency_ms'] ?? 0;

            $result['per_request_deltas'][] = [
                'index' => $i,
                'agent' => $a1['agent'] ?? $bc['agent'] ?? 'N/A',
                'category' => $a1['category'] ?? $bc['category'] ?? 'N/A',
                'a1_latency' => $a1Lat,
                'a2_latency' => $a2Lat,
                'b_cold_latency' => $bcLat,
                'b_warm_latency' => $bwLat,
                'a2_vs_a1_pct' => $a1Lat > 0 ? round(($a2Lat - $a1Lat) / $a1Lat * 100) : null,
                'b_cold_vs_a1_pct' => $a1Lat > 0 ? round(($bcLat - $a1Lat) / $a1Lat * 100) : null,
                'b_warm_vs_b_cold_pct' => $bcLat > 0 ? round(($bwLat - $bcLat) / $bcLat * 100) : null,
                'a1_tokens' => $a1['tokens']['total_tokens'] ?? 0,
                'a2_tokens' => $a2['tokens']['total_tokens'] ?? 0,
                'b_cold_tokens' => $bc['tokens']['total_tokens'] ?? 0,
                'b_warm_tokens' => $bw['tokens']['total_tokens'] ?? 0,
                'a1_tools' => $a1['tool_calls']['count'] ?? 0,
                'a2_tools' => $a2['tool_calls']['count'] ?? 0,
                'b_cold_tools' => $bc['tool_calls']['count'] ?? 0,
                'b_warm_tools' => $bw['tool_calls']['count'] ?? 0,
                'b_cold_stages' => count($bc['pipeline_stages'] ?? []),
                'b_warm_stages' => count($bw['pipeline_stages'] ?? []),
                'b_cold_cache_hit' => $bc['cache']['hit'] ?? false,
                'b_warm_cache_hit' => $bw['cache']['hit'] ?? false,
                'a1_success' => $a1['success'] ?? false,
                'a2_success' => $a2['success'] ?? false,
                'b_cold_success' => $bc['success'] ?? false,
                'b_warm_success' => $bw['success'] ?? false,
            ];
        }

        // Feature Matrix evaluation breakdown
        $bcTraces = $traces['B-full-harness'] ?? [];
        $bwTraces = $traces['B-warm-harness'] ?? [];

        $draftCount = count(array_filter($bcTraces, fn ($t) => ! empty($t['features_eval']['draft_verification']['draft_generated']) || ! empty($t['draft_verification']['draft'])));
        $ragCount = count(array_filter($bcTraces, fn ($t) => ! empty($t['features_eval']['ontology_rag']['injected']) || ! empty($t['context_injected'])));
        $cacheHitCold = count(array_filter($bcTraces, fn ($t) => ($t['cache']['hit'] ?? $t['features_eval']['semantic_cache']['hit'] ?? false) === true));
        $cacheHitWarm = count(array_filter($bwTraces, fn ($t) => ($t['cache']['hit'] ?? $t['features_eval']['semantic_cache']['hit'] ?? false) === true));
        $quantumNodesTotal = array_sum(array_map(fn ($t) => $t['quantum_memory']['nodes_retrieved'] ?? $t['features_eval']['quantum_memory']['nodes_retrieved'] ?? count($t['quantum_memory']['nodes'] ?? []), $bcTraces));
        $compressedCount = count(array_filter($bcTraces, fn ($t) => ($t['features_eval']['context_compression']['enabled'] ?? false) === true));
        $compactionCount = count(array_filter($bcTraces, fn ($t) => ($t['iterations'] ?? 1) > 1));
        $graphMemoryTools = array_sum(array_map(fn ($t) => count(array_filter($t['tool_calls']['calls'] ?? [], fn ($c) => ($c['name'] ?? '') === 'query_graph_memory')), $bcTraces));

        $result['features_matrix'] = [
            'draft_verification' => [
                'name' => 'Draft Verification',
                'description' => 'Speculative proposal generation verified by fast model passes',
                'executed_count' => $draftCount,
                'status' => 'ACTIVE',
            ],
            'ontology_rag' => [
                'name' => 'Ontology RAG (pgvector)',
                'description' => 'Semantic document chunk injection from PostgreSQL pgvector',
                'executed_count' => $ragCount,
                'status' => 'ACTIVE',
            ],
            'semantic_cache' => [
                'name' => 'Semantic Cache',
                'description' => 'Cross-session exact & fuzzy prompt matching to skip LLM calls',
                'cold_hits' => $cacheHitCold,
                'warm_hits' => $cacheHitWarm,
                'warm_hit_pct' => count($bwTraces) > 0 ? round(($cacheHitWarm / count($bwTraces)) * 100, 1) : 0,
                'status' => 'ACTIVE',
            ],
            'quantum_memory' => [
                'name' => 'Quantum Memory Superposition',
                'description' => 'Multi-hop entanglement traversal & phase-angle superposition retrieval',
                'total_nodes_retrieved' => $quantumNodesTotal,
                'status' => 'ACTIVE',
            ],
            'context_compression' => [
                'name' => 'Context Compression',
                'description' => 'Prompt middleware noise stripping & token reduction',
                'executed_count' => $compressedCount,
                'status' => 'ACTIVE',
            ],
            'compaction' => [
                'name' => 'Context Compactor',
                'description' => 'Sliding window compaction on multi-turn conversation histories',
                'compacted_turns' => $compactionCount,
                'status' => 'ACTIVE',
            ],
            'cognitive_graph_memory' => [
                'name' => 'Cognitive Graph Memory',
                'description' => 'Persistent facts & entity relationships queried via QueryGraphMemoryTool',
                'facts_queried' => $graphMemoryTools,
                'status' => 'ACTIVE',
            ],
        ];

        return $result;
    }

    /**
     * Purge all old runs and traces.
     */
    public function purge()
    {
        try {
            $baseDir = base_path('testandcompare');
            $runsDir = $baseDir.'/runs';

            if (is_dir($runsDir)) {
                File::cleanDirectory($runsDir);
            }

            if (is_link($baseDir.'/latest') || is_file($baseDir.'/latest')) {
                @unlink($baseDir.'/latest');
            } elseif (is_dir($baseDir.'/latest')) {
                File::deleteDirectory($baseDir.'/latest');
            }

            if (is_dir($baseDir.'/traces')) {
                File::deleteDirectory($baseDir.'/traces');
            }

            $filesToClean = [
                storage_path('logs/test-compare-run.pid'),
                storage_path('logs/test-compare-run.log'),
                storage_path('logs/test-compare-run.marker'),
                storage_path('logs/test-compare-run.id'),
            ];
            foreach ($filesToClean as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'All old test runs and traces have been purged successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purge failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run the test suite (AJAX endpoint).
     */
    public function run(Request $request)
    {
        $logFile = storage_path('logs/test-compare-run.log');
        $pidFile = storage_path('logs/test-compare-run.pid');
        $markerFile = storage_path('logs/test-compare-run.marker');

        // Check if a run is already in progress
        if (file_exists($pidFile)) {
            $pid = (int) file_get_contents($pidFile);
            if ($pid > 0 && $this->isProcessRunning($pid)) {
                return response()->json([
                    'success' => false,
                    'message' => 'A test run is already in progress (PID: '.$pid.').',
                ], 409);
            }
        }

        // Clean up old marker
        if (file_exists($markerFile)) {
            unlink($markerFile);
        }

        // Use nohup + disown to fully detach from Octane's process tree
        // The artisan command writes 'DONE' to the marker file when complete
        $artisan = base_path().'/artisan';
        $command = sprintf(
            'nohup php %s test:phpkaiharness --run > %s 2>&1 < /dev/null & echo $!',
            escapeshellarg($artisan),
            escapeshellarg($logFile)
        );
        $pid = (int) trim(shell_exec($command));
        if ($pid > 0) {
            file_put_contents($pidFile, $pid);
        }

        return response()->json([
            'success' => true,
            'message' => 'Test suite started in background.',
            'pid' => $pid,
            'log_file' => $logFile,
        ]);
    }

    /**
     * Check the status of a running test suite (AJAX endpoint).
     */
    public function status()
    {
        $pidFile = storage_path('logs/test-compare-run.pid');
        $logFile = storage_path('logs/test-compare-run.log');
        $markerFile = storage_path('logs/test-compare-run.marker');
        $runIdFile = storage_path('logs/test-compare-run.id');

        $running = false;
        $pid = 0;
        if (file_exists($pidFile)) {
            $pid = (int) file_get_contents($pidFile);
            if ($pid > 0 && $this->isProcessRunning($pid)) {
                $running = true;
            }
        }

        // Also check marker file — the artisan command writes 'DONE' when finished
        $markerDone = file_exists($markerFile) && trim(file_get_contents($markerFile)) === 'DONE';
        if ($markerDone) {
            $running = false;
        }

        $log = '';
        if (file_exists($logFile)) {
            $log = file_get_contents($logFile);
            // Return last 3000 chars to keep response small
            if (strlen($log) > 3000) {
                $log = substr($log, -3000);
            }
        }

        // Count traces from the CURRENT run's directory only (not old runs)
        $baseDir = base_path('testandcompare');
        $runId = file_exists($runIdFile) ? trim(file_get_contents($runIdFile)) : null;
        $runDir = $runId ? $baseDir.'/runs/'.$runId : null;

        $totalReqs = count(TestDataset::all());
        $totalExecutions = $totalReqs * 4;

        $traceCounts = [];
        $currentStage = null;
        foreach (['A1-direct-api', 'A2-loop-no-features', 'B-full-harness', 'B-warm-harness'] as $mode) {
            $dir = $runDir ? $runDir.'/traces/'.$mode : null;
            $traceCounts[$mode] = ($dir && is_dir($dir)) ? count(glob($dir.'/request-*.json')) : 0;

            // Detect current stage: first mode with traces but not yet complete
            if ($currentStage === null && $traceCounts[$mode] > 0 && $traceCounts[$mode] < $totalReqs) {
                $currentStage = $mode;
            }
        }
        // If all modes have 0 traces, we're just starting
        if ($currentStage === null && array_sum($traceCounts) === 0) {
            $currentStage = 'starting';
        }
        // If all modes are complete
        if ($currentStage === null && array_sum($traceCounts) >= $totalExecutions) {
            $currentStage = 'completed';
        }
        // If some modes are complete but next hasn't started
        if ($currentStage === null && array_sum($traceCounts) > 0 && array_sum($traceCounts) < $totalExecutions) {
            $modeOrder = ['A1-direct-api', 'A2-loop-no-features', 'B-full-harness', 'B-warm-harness'];
            foreach ($modeOrder as $mode) {
                if ($traceCounts[$mode] < $totalReqs) {
                    $currentStage = $mode;
                    break;
                }
            }
        }

        // Clean up PID file if process finished
        if (! $running && file_exists($pidFile)) {
            unlink($pidFile);
        }

        return response()->json([
            'running' => $running,
            'pid' => $pid,
            'log' => $log,
            'trace_counts' => $traceCounts,
            'marker_done' => $markerDone,
            'run_id' => $runId,
            'current_stage' => $currentStage,
        ]);
    }

    /**
     * Check if a process is running (works without posix_kill).
     */
    private function isProcessRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        // Try /proc filesystem first (Linux)
        if (file_exists("/proc/{$pid}")) {
            return true;
        }

        // Fallback: posix_kill with signal 0
        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        // Last resort: ps command
        $result = shell_exec("ps -p {$pid} -o pid= 2>/dev/null");

        return ! empty(trim($result ?? ''));
    }

    /**
     * Get the test dataset (AJAX endpoint).
     */
    public function dataset()
    {
        return response()->json([
            'dataset' => TestDataset::all(),
        ]);
    }

    /**
     * View a specific trace.
     */
    public function trace(string $mode, int $index)
    {
        $baseDir = base_path('testandcompare');
        $latestDir = is_link($baseDir.'/latest') ? $baseDir.'/latest' : $baseDir;
        $dir = $latestDir.'/traces/'.$mode;
        $files = is_dir($dir) ? glob($dir.'/request-*.json') : [];

        if (empty($files)) {
            abort(404, 'Trace not found');
        }

        // Sort by index
        usort($files, function ($a, $b) {
            preg_match('/request-(\d+)/', $a, $ma);
            preg_match('/request-(\d+)/', $b, $mb);

            return (int) $ma[1] <=> (int) $mb[1];
        });

        $file = $files[$index] ?? null;
        if (! $file) {
            abort(404, 'Trace not found');
        }

        $trace = json_decode(file_get_contents($file), true);

        return response()->json($trace);
    }
}
