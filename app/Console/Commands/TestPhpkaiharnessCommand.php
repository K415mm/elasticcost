<?php

namespace App\Console\Commands;

use App\Services\TestCompare\TestCompareReportGenerator;
use App\Services\TestCompare\TestRunner;
use Illuminate\Console\Command;

class TestPhpkaiharnessCommand extends Command
{
    protected $signature = 'test:phpkaiharness
                            {--run : Execute the full test suite (68 executions: 17 requests × 4 modes)}
                            {--report-only : Generate report from existing traces without running tests}
                            {--mode= : Run only a specific mode (A1-direct-api, A2-loop-no-features, B-full-harness, B-warm-harness)}
                            {--dir= : Output directory name (default: testandcompare)}';

    protected $description = 'Run phpkaiharness comparison tests (17 requests × 4 modes) and generate report';

    public function handle(): int
    {
        if ($this->option('report-only')) {
            return $this->generateReportOnly();
        }

        if (! $this->option('run')) {
            $this->info('Use --run to execute the test suite, or --report-only to generate a report from existing traces.');
            $this->info('');
            $this->info('Available options:');
            $this->info('  --run              Execute all 68 test executions (17 requests × 4 modes)');
            $this->info('  --report-only      Generate report from existing traces');
            $this->info('  --mode=B-full-harness  Run only a specific mode');
            $this->info('  --mode=B-warm-harness   Run only warm mode');

            return self::SUCCESS;
        }

        $this->info('Starting phpkaiharness comparison test suite...');
        $this->info('Execution order: A1 → A2 → B-cold → B-warm');
        $this->info('Mode A1:      Direct Qwen Cloud API (no harness) — runs first');
        $this->info('Mode A2:      AgentLoop with all features disabled — runs second');
        $this->info('Mode B-cold:  Full phpkaiharness (all features, cold cache) — runs third');
        $this->info('Mode B-warm:  Full phpkaiharness (warm cache) — runs fourth (no cache clear)');
        $this->info('');

        $dirName = $this->option('dir') ?: 'testandcompare';
        $outputDir = base_path($dirName);
        $runner = new TestRunner($outputDir);

        $filterMode = $this->option('mode');

        $result = $runner->runAll(function (string $mode, int $index, int $total, string $status) {
            $modeLabel = match ($mode) {
                'A1-direct-api' => 'A1 (Direct API)',
                'A2-loop-no-features' => 'A2 (Loop, no features)',
                'B-full-harness' => 'B-cold (Full Harness)',
                'B-warm-harness' => 'B-warm (Warm Cache)',
                default => $mode,
            };

            $num = $index + 1;

            match ($status) {
                'running' => $this->info("[{$modeLabel}] Request {$num}/{$total}..."),
                'done' => $this->line("  ✓ Completed ({$num}/{$total})"),
                'error' => $this->error("  ✗ Failed ({$num}/{$total})"),
                default => null,
            };
        }, $filterMode);

        $this->info('');
        $this->info('Test suite completed!');
        $this->info('');

        // Print summary table
        $this->table(
            ['Metric', 'A1 (Direct API)', 'A2 (Loop)', 'B-cold', 'B-warm'],
            $this->buildSummaryTable($result['summary'])
        );

        // Generate report
        $this->info('');
        $this->info('Generating comparison report...');

        $reportGenerator = new TestCompareReportGenerator(
            $result['traces'],
            $result['summary'],
            $outputDir
        );
        $reportGenerator->generate();

        $this->info("Report saved to: {$outputDir}/comparison-report.md");
        $this->info("Traces saved to: {$outputDir}/traces/");
        $this->info("Summary saved to: {$outputDir}/comparison-summary.json");

        // Write marker file so the status endpoint knows we're done
        file_put_contents(storage_path('logs/test-compare-run.marker'), 'DONE');

        return self::SUCCESS;
    }

    private function generateReportOnly(): int
    {
        $dirName = $this->option('dir') ?: 'testandcompare';
        $outputDir = base_path($dirName);
        $summaryFile = $outputDir.'/comparison-summary.json';

        if (! file_exists($summaryFile)) {
            $this->error('No test data found. Run --run first.');

            return self::FAILURE;
        }

        $summary = json_decode(file_get_contents($summaryFile), true);
        $traces = [];

        foreach (['A1-direct-api', 'A2-loop-no-features', 'B-full-harness', 'B-warm-harness'] as $mode) {
            $dir = $outputDir.'/traces/'.$mode;
            if (is_dir($dir)) {
                $files = glob($dir.'/request-*.json');
                foreach ($files as $file) {
                    $traces[$mode][] = json_decode(file_get_contents($file), true);
                }
                usort($traces[$mode], fn ($a, $b) => $a['request_index'] <=> $b['request_index']);
            }
        }

        $this->info('Generating report from existing traces...');

        $reportGenerator = new TestCompareReportGenerator($traces, $summary, $outputDir);
        $reportGenerator->generate();

        $this->info("Report saved to: {$outputDir}/comparison-report.md");

        return self::SUCCESS;
    }

    private function buildSummaryTable(array $summary): array
    {
        $rows = [];
        $metrics = [
            'avg_latency_ms' => 'Avg Latency (ms)',
            'min_latency_ms' => 'Min Latency (ms)',
            'max_latency_ms' => 'Max Latency (ms)',
            'avg_total_tokens' => 'Avg Total Tokens',
            'avg_tool_calls' => 'Avg Tool Calls',
            'avg_response_length' => 'Avg Response Length',
            'successful' => 'Successful Requests',
        ];

        foreach ($metrics as $key => $label) {
            $rows[] = [
                $label,
                $summary['A1-direct-api'][$key] ?? 'N/A',
                $summary['A2-loop-no-features'][$key] ?? 'N/A',
                $summary['B-full-harness'][$key] ?? 'N/A',
                $summary['B-warm-harness'][$key] ?? 'N/A',
            ];
        }

        return $rows;
    }
}
