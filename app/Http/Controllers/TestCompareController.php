<?php

namespace App\Http\Controllers;

use App\Services\TestCompare\TestCompareReportGenerator;
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
        $hasWarmResults = file_exists($warmOutputDir.'/comparison-summary.json');
        if ($hasWarmResults) {
            $warmSummary = json_decode(file_get_contents($warmOutputDir.'/comparison-summary.json'), true);
            if ($warmSummary) {
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

            // Load warm traces
            $warmMode = 'B-warm-harness';
            $warmDir = $warmOutputDir.'/traces/'.$warmMode;
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

        $result = $runner->runAll();

        $reportGenerator = new TestCompareReportGenerator(
            $result['traces'],
            $result['summary'],
            $outputDir
        );
        $reportGenerator->generate();

        return response()->json([
            'success' => true,
            'summary' => $result['summary'],
            'output_dir' => $outputDir,
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
        $searchDir = $mode === 'B-warm-harness' ? $warmOutputDir : $outputDir;
        $files = glob($searchDir."/traces/{$mode}/request-*.json");

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
