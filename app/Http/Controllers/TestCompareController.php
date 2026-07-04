<?php

namespace App\Http\Controllers;

use App\Services\TestCompare\TestDataset;
use App\Services\TestCompare\TestRunner;
use Illuminate\Http\Request;

class TestCompareController extends Controller
{
    /**
     * Display the test comparison dashboard.
     */
    public function index()
    {
        $dataset = TestDataset::all();
        $outputDir = base_path('testandcompare');
        $warmOutputDir = base_path('testandcompare-warm');

        // Load cold results
        $hasResults = file_exists($outputDir.'/comparison-summary.json');
        $summary = $hasResults
            ? json_decode(file_get_contents($outputDir.'/comparison-summary.json'), true)
            : null;

        // Load warm results and merge into summary
        // Existing warm data may use 'B-full-harness' key — rename to 'B-warm-harness'
        $hasWarmResults = file_exists($warmOutputDir.'/comparison-summary.json');
        if ($hasWarmResults) {
            $warmSummary = json_decode(file_get_contents($warmOutputDir.'/comparison-summary.json'), true);
            if ($warmSummary) {
                // Rename B-full-harness to B-warm-harness if present (old format)
                if (isset($warmSummary['B-full-harness']) && ! isset($warmSummary['B-warm-harness'])) {
                    $warmSummary['B-warm-harness'] = $warmSummary['B-full-harness'];
                    unset($warmSummary['B-full-harness']);
                }
                $summary = array_merge($summary ?? [], $warmSummary);
                $hasResults = true;
            }
        }

        $traces = [];
        if ($hasResults) {
            // Load cold traces
            foreach (['A1-direct-api', 'A2-loop-no-features', 'B-full-harness'] as $mode) {
                $dir = $outputDir.'/traces/'.$mode;
                if (is_dir($dir)) {
                    $files = glob($dir.'/request-*.json');
                    foreach ($files as $file) {
                        $traces[$mode][] = json_decode(file_get_contents($file), true);
                    }
                    $modeTraces = $traces[$mode] ?? [];
                    usort($modeTraces, fn ($a, $b) => $a['request_index'] <=> $b['request_index']);
                    $traces[$mode] = $modeTraces;
                }
            }

            // Load warm traces — check both B-warm-harness (new) and B-full-harness (old format)
            $warmMode = 'B-warm-harness';
            $warmDir = $warmOutputDir.'/traces/'.$warmMode;
            if (! is_dir($warmDir)) {
                // Fallback: old format stored warm traces under B-full-harness
                $warmDir = $warmOutputDir.'/traces/B-full-harness';
            }
            if (is_dir($warmDir)) {
                $files = glob($warmDir.'/request-*.json');
                foreach ($files as $file) {
                    $traces[$warmMode][] = json_decode(file_get_contents($file), true);
                }
                $modeTraces = $traces[$warmMode] ?? [];
                usort($modeTraces, fn ($a, $b) => $a['request_index'] <=> $b['request_index']);
                $traces[$warmMode] = $modeTraces;
            }
        }

        $reportPath = $outputDir.'/comparison-report.md';
        $reportContent = file_exists($reportPath) ? file_get_contents($reportPath) : null;

        return view('test-compare.index', compact('dataset', 'summary', 'traces', 'hasResults', 'reportContent', 'hasWarmResults'));
    }

    /**
     * Run the test suite (AJAX endpoint).
     */
    public function run(Request $request)
    {
        $runner = new TestRunner;
        $outputDir = $runner->getOutputDir();

        // Run the test suite via Artisan in the background to avoid HTTP timeout
        $logFile = storage_path('logs/test-compare-run.log');
        $pidFile = storage_path('logs/test-compare-run.pid');

        // Check if a run is already in progress
        if (file_exists($pidFile)) {
            $pid = (int) file_get_contents($pidFile);
            if ($pid > 0 && @posix_kill($pid, 0)) {
                return response()->json([
                    'success' => false,
                    'message' => 'A test run is already in progress (PID: '.$pid.').',
                ], 409);
            }
        }

        $command = 'php '.base_path().'/artisan test:phpkaiharness --run > '.escapeshellarg($logFile).' 2>&1 & echo $!';
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

        $running = false;
        $pid = 0;
        if (file_exists($pidFile)) {
            $pid = (int) file_get_contents($pidFile);
            if ($pid > 0 && @posix_kill($pid, 0)) {
                $running = true;
            }
        }

        $log = '';
        if (file_exists($logFile)) {
            $log = file_get_contents($logFile);
            // Return last 3000 chars to keep response small
            if (strlen($log) > 3000) {
                $log = substr($log, -3000);
            }
        }

        // Check current trace counts
        $outputDir = base_path('testandcompare');
        $warmOutputDir = base_path('testandcompare-warm');
        $traceCounts = [];
        foreach (['A1-direct-api', 'A2-loop-no-features', 'B-full-harness'] as $mode) {
            $dir = $outputDir.'/traces/'.$mode;
            $traceCounts[$mode] = is_dir($dir) ? count(glob($dir.'/request-*.json')) : 0;
        }
        $warmDir = $warmOutputDir.'/traces/B-warm-harness';
        if (! is_dir($warmDir)) {
            $warmDir = $warmOutputDir.'/traces/B-full-harness';
        }
        $traceCounts['B-warm-harness'] = is_dir($warmDir) ? count(glob($warmDir.'/request-*.json')) : 0;

        // Clean up PID file if process finished
        if (! $running && file_exists($pidFile)) {
            unlink($pidFile);
        }

        return response()->json([
            'running' => $running,
            'pid' => $pid,
            'log' => $log,
            'trace_counts' => $traceCounts,
        ]);
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
        $outputDir = base_path('testandcompare');
        $warmOutputDir = base_path('testandcompare-warm');

        // Warm traces are stored in the warm output directory
        // Check both B-warm-harness (new) and B-full-harness (old format) for warm mode
        if ($mode === 'B-warm-harness') {
            $searchDir = $warmOutputDir;
            $files = glob($searchDir.'/traces/B-warm-harness/request-*.json');
            if (empty($files)) {
                $files = glob($searchDir.'/traces/B-full-harness/request-*.json');
            }
        } else {
            $searchDir = $outputDir;
            $files = glob($searchDir."/traces/{$mode}/request-*.json");
        }

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
