<?php

namespace App\Console\Commands;

use App\Services\TestCompare\TestCompareReportGenerator;
use App\Services\TestCompare\TestRunner;
use Illuminate\Console\Command;

class TestPhpkaiharnessCommand extends Command
{
    protected $signature = 'test:phpkaiharness
                            {--run : Execute the full test suite (60 executions: 20 requests × 3 modes)}
                            {--report-only : Generate report from existing traces without running tests}
                            {--mode= : Run only a specific mode (A1-direct-api, A2-loop-no-features, B-full-harness)}
                            {--dir= : Output directory name (default: testandcompare)}';

    protected $description = 'Run phpkaiharness comparison tests (20 requests × 3 modes) and generate report';

    public function handle(): int
    {
        if ($this->option('report-only')) {
            return $this->generateReportOnly();
        }

        if (! $this->option('run')) {
            $this->info('Use --run to execute the test suite, or --report-only to generate a report from existing traces.');
            $this->info('');
            $this->info('Available options:');
            $this->info('  --run              Execute all 60 test executions (20 requests × 3 modes)');
            $this->info('  --report-only      Generate report from existing traces');
            $this->info('  --mode=B-full-harness  Run only a specific mode');

            return self::SUCCESS;
        }

        $this->info('Starting phpkaiharness comparison test suite...');
        $this->info('Mode B:  Full phpkaiharness (all features enabled) — runs first');
        $this->info('Mode A2: AgentLoop with all features disabled — runs second');
        $this->info('Mode A1: Direct Qwen Cloud API (no harness) — runs last');
        $this->info('');

        $dirName = $this->option('dir') ?: 'testandcompare';
        $outputDir = base_path($dirName);
        $runner = new TestRunner($outputDir);

        $filterMode = $this->option('mode');

        $result = $runner->runAll(function (string $mode, int $index, int $total, string $status) {
            $modeLabel = match ($mode) {
                'A1-direct-api' => 'A1 (Direct API)',
                'A2-loop-no-features' => 'A2 (Loop, no features)',
                'B-full-harness' => 'B (Full Harness)',
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
            ['Metric', 'B (Full Harness)', 'A2 (Loop, no features)', 'A1 (Direct API)'],
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

        foreach (['A1-direct-api', 'A2-loop-no-features', 'B-full-harness'] as $mode) {
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
                $summary['B-full-harness'][$key] ?? 'N/A',
                $summary['A2-loop-no-features'][$key] ?? 'N/A',
                $summary['A1-direct-api'][$key] ?? 'N/A',
            ];
        }

        return $rows;
    }
}
