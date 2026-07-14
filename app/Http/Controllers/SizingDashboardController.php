<?php

namespace App\Http\Controllers;

use App\Ai\Agents\SizingRegulator;
use App\Ai\Middleware\InjectDocumentation;
use App\Models\Client;
use App\Models\ClientScenarioMsspDetail;
use App\Models\Scenario;
use App\Services\AiConfigHelper;
use App\Services\CurrencyHelper;
use App\Services\SizingEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Ai\Prompts\AgentPrompt;
use Phpkaiharness\Core\AgentLoop;
use Phpkaiharness\Core\Prompt\DummyTextProvider;
use Phpkaiharness\Llm\LaravelAiClient;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SizingDashboardController extends Controller
{
    protected SizingEngine $sizingEngine;

    public function __construct(SizingEngine $sizingEngine)
    {
        $this->sizingEngine = $sizingEngine;
    }

    /**
     * Display the detailed sizing dashboard for a specific client and scenario.
     */
    public function show(Client $client, Scenario $scenario)
    {
        // Calculate sizing metrics
        $data = $this->sizingEngine->calculate($client, $scenario);

        // Load all scenarios for the selector dropdown/sidebar
        $scenarios = Scenario::all();

        // Get the cached sizing analysis (if any) from mssp detail
        $msspDetail = ClientScenarioMsspDetail::where([
            'client_id' => $client->id,
            'scenario_id' => $scenario->id,
        ])->first();

        $aiSizingAnalysis = $msspDetail?->ai_sizing_analysis;

        return view('dashboard.sizing', compact('client', 'scenario', 'data', 'scenarios', 'aiSizingAnalysis'));
    }

    /**
     * Call local Ollama AI model to analyze the Elasticsearch sizing configuration and return the result.
     */
    public function analyzeSizingAi(Client $client, Scenario $scenario)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        try {
            $data = $this->sizingEngine->calculate($client, $scenario);

            // Format the sizing details for the agent
            $sizingBreakdown = [
                'client_name' => $client->name,
                'scenario_name' => $scenario->name,
                'workload_profile' => $scenario->workload_profile,
                'retention_days' => $scenario->retention_days,
                'ilm_retention_tiers' => [
                    'hot' => [
                        'days' => $scenario->hot_days,
                        'replicas' => $scenario->hot_replicas,
                        'storage_gb' => $data['totals']['hot_storage_gb'] ?? 0,
                    ],
                    'warm' => [
                        'days' => $scenario->warm_days,
                        'replicas' => $scenario->warm_replicas,
                        'storage_gb' => $data['totals']['warm_storage_gb'] ?? 0,
                    ],
                    'cold' => [
                        'days' => $scenario->cold_days,
                        'replicas' => $scenario->cold_replicas,
                        'storage_gb' => $data['totals']['cold_storage_gb'] ?? 0,
                    ],
                    'frozen' => [
                        'days' => $scenario->frozen_days,
                        'replicas' => $scenario->frozen_replicas,
                        'storage_gb' => $data['totals']['frozen_storage_gb'] ?? 0,
                    ],
                ],
                'totals' => [
                    'daily_raw_gb' => $data['totals']['daily_raw_gb'],
                    'daily_indexed_gb' => $data['totals']['daily_indexed_gb'],
                    'total_storage_footprint_gb' => $data['totals']['total_storage_footprint_gb'],
                    'total_ram_gb' => $data['licensing']['total_ram_gb'],
                    'required_erus' => $data['licensing']['required_erus'],
                    'annual_license_cost_usd' => $data['licensing']['annual_cost_usd'],
                ],
                'nodes' => collect($data['nodes'])->map(fn ($node) => [
                    'name' => $node['name'],
                    'role' => $node['role'],
                    'count' => $node['count'],
                    'ram_gb' => $node['ram_gb'].' GB',
                    'storage_gb' => $node['storage_gb'].' GB',
                    'storage_type' => $node['storage_type'],
                ])->toArray(),
            ];

            $promptContent = "Please analyze the following Elasticsearch sizing details and topology configuration:\n\n".
                      json_encode($sizingBreakdown, JSON_PRETTY_PRINT)."\n\n".
                      'Evaluate the sizing/topology, RAM-to-disk ratios, storage tiers retention plan, and node specs. Offer enhancements and recommendations.';

            $aiConfig = AiConfigHelper::configure();
            $provider = $aiConfig['provider'];
            $providerStr = $provider instanceof \BackedEnum ? $provider->value : (string) $provider;
            $model = $aiConfig['model'];

            $agent = new SizingRegulator;
            if ($agent::isFaked()) {
                $response = $agent->prompt($promptContent, provider: $aiConfig['provider'], model: $aiConfig['model'], timeout: 120);
                $analysisText = property_exists($response, 'structured')
                    ? ($response->structured['full_critique'] ?? $response->text)
                    : $response->text;
            } else {
                // Run InjectDocumentation RAG middleware manually
                $ragMiddleware = new InjectDocumentation;
                $dummyProvider = new DummyTextProvider;
                $dummyPrompt = new AgentPrompt($agent, $promptContent, [], $dummyProvider, $model);
                $finalPrompt = $ragMiddleware->handle($dummyPrompt, fn ($p) => $p);
                $promptContent = $finalPrompt->prompt;

                // Call agent via phpkaiharness AgentLoop to avoid SDK structured output timeout
                $schemaJson = '{'.
                    '"verdict": "Adequate, Under-provisioned, or Imbalanced",'.
                    '"health_score": 8,'.
                    '"ratio_audit": ["Tier audit details..."],'.
                    '"ha_check": {"master_eligible_count": 3, "quorum_met": true, "remarks": "Master quorum details..."},'.
                    '"recommendations": ["Recommendation 1", "Recommendation 2"],'.
                    '"full_critique": "Detailed markdown audit critique..."'.
                '}';

                $systemPrompt = $agent->instructions()."\n\nYou MUST respond ONLY with a valid JSON object matching the following structure:\n".$schemaJson;
                $llmClient = new LaravelAiClient($providerStr, $model);

                $loop = new AgentLoop(
                    llmClient: $llmClient,
                    systemPrompt: $systemPrompt,
                    model: $model,
                    maxIterations: 1
                );
                $loop->setAgentName('SizingRegulator');

                $history = [];
                $responseText = $loop->run(
                    userPrompt: $promptContent,
                    history: $history,
                    sessionId: 'sandbox_'.session()->getId()
                );

                $decoded = json_decode($responseText, true);
                $analysisText = is_array($decoded)
                    ? ($decoded['full_critique'] ?? $responseText)
                    : $responseText;
            }

            // Get provider display name
            $providerName = is_object($aiConfig['provider']) ? $aiConfig['provider']->name : (string) $aiConfig['provider'];

            // Build a debug footer so the user can verify the AI backend was used
            $debugInfo = "\n\n---\n> 🤖 **AI Debug Info** | Provider: `{$providerName}` | Model: `{$aiConfig['model']}` | Generated: `".now()->format('Y-m-d H:i:s').'` | Prompt tokens (est.): ~'.(int) (strlen($promptContent) / 4).' | Response tokens (est.): ~'.(int) (strlen($analysisText) / 4);
            $fullAnalysis = $analysisText.$debugInfo;

            // Save the analysis text to the database
            $msspDetail = ClientScenarioMsspDetail::firstOrCreate([
                'client_id' => $client->id,
                'scenario_id' => $scenario->id,
            ]);

            $msspDetail->update([
                'ai_sizing_analysis' => $fullAnalysis,
            ]);

            return response()->json([
                'success' => true,
                'analysis' => $fullAnalysis,
                'html' => Str::markdown($fullAnalysis),
                'debug' => [
                    'provider' => $providerName,
                    'model' => $aiConfig['model'],
                    'prompt_length' => strlen($promptContent),
                    'response_length' => strlen($analysisText),
                    'generated_at' => now()->toIso8601String(),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Laravel AI SDK sizing regulator error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error generating sizing analysis. Details: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export the sizing scenario to Markdown report.
     */
    public function exportMarkdown(Client $client, Scenario $scenario)
    {
        $data = $this->sizingEngine->calculate($client, $scenario);

        $markdown = "# Elasticsearch Sizing & Cost Report: {$scenario->name} - {$scenario->description}\n\n";
        $markdown .= "This report details the architectural footprint and licensing cost for **Client \"{$client->name}\"** under **{$scenario->name}**.\n\n";
        $markdown .= "---\n\n";

        $markdown .= "## 1. Workload & Ingest Parameters\n\n";
        $markdown .= '*   **Ingestion Profile**: **'.ucfirst($scenario->workload_profile)." Workload**\n";
        $markdown .= "*   **Daily Raw Log Volume**: **`{$data['totals']['daily_raw_gb']} GB/day`**\n";
        $markdown .= "*   **Daily Indexed Volume (+25% Expansion)**: **`{$data['totals']['daily_indexed_gb']} GB/day`**\n";
        $markdown .= "*   **Retention Period**: **`{$scenario->retention_days} Days`** (1 Year)\n";
        $markdown .= "*   **ILM Data Lifecycle Tiers**:\n";
        if ($scenario->hot_days > 0) {
            $markdown .= "    *   **Hot Tier**: **{$scenario->hot_days} Days** (Primary + {$scenario->hot_replicas} Replica). Daily: {$data['totals']['daily_ingested_gb']} GB/day.\n";
        }
        if ($scenario->warm_days > 0) {
            $markdown .= "    *   **Warm Tier**: **{$scenario->warm_days} Days** (Primary + {$scenario->warm_replicas} Replica).\n";
        }
        if ($scenario->cold_days > 0) {
            $markdown .= "    *   **Cold Tier**: **{$scenario->cold_days} Days** (Searchable Snapshots, 0% Replica overhead).\n";
        }
        if ($scenario->frozen_days > 0) {
            $markdown .= "    *   **Frozen Tier**: **{$scenario->frozen_days} Days** (Searchable Snapshots, 0% Replica overhead).\n";
        }
        $markdown .= "\n---\n\n";

        $markdown .= "## 2. Storage Sizing Calculations\n\n";
        $markdown .= '*   **Total Raw Data Stored**: '.$data['totals']['daily_raw_gb'].' GB/day * '.$scenario->retention_days.' days = **'.$data['totals']['total_raw_storage_gb']." GB**\n";
        $markdown .= '*   **Total Indexed Data (Active)**: '.$data['totals']['daily_indexed_gb'].' GB/day * '.$scenario->retention_days.' days = **'.$data['totals']['total_indexed_storage_gb']." GB**\n";
        $markdown .= "*   **Tier Storage Breakdown (Cluster Physical Footprint)**:\n";
        if ($scenario->hot_days > 0) {
            $markdown .= "    *   **Hot Tier (NVMe SSD)**: **{$data['totals']['hot_storage_gb']} GB**\n";
        }
        if ($scenario->warm_days > 0) {
            $markdown .= "    *   **Warm Tier (SATA SSD / HDD)**: **{$data['totals']['warm_storage_gb']} GB**\n";
        }
        if ($scenario->cold_days > 0) {
            $markdown .= "    *   **Cold Tier (Object Store + Local Cache)**: **{$data['totals']['cold_storage_gb']} GB**\n";
        }
        if ($scenario->frozen_days > 0) {
            $markdown .= "    *   **Frozen Tier (Object Store Cache)**: **{$data['totals']['frozen_storage_gb']} GB**\n";
        }
        $markdown .= '*   **Total Cluster Storage Required**: **'.$data['totals']['total_storage_footprint_gb']." GB**\n\n";

        $markdown .= "---\n\n";

        $markdown .= "## 3. Recommended Cluster Architecture (On-Premises VMs)\n\n";
        $markdown .= "| Node Name | Node Role | Count | RAM / Node | JVM Heap | Storage / Node | Storage Type |\n";
        $markdown .= "| :--- | :--- | :---: | :---: | :---: | :---: | :--- |\n";
        foreach ($data['nodes'] as $node) {
            $gbVal = $node['storage_gb'] >= 1000 ? ($node['storage_gb'] / 1000).' TB' : $node['storage_gb'].' GB';
            $markdown .= "| **{$node['name']}** | {$node['role']} | {$node['count']} | {$node['ram_gb']} GB | {$node['heap_gb']} GB | {$gbVal} | {$node['storage_type']} |\n";
        }
        $markdown .= "\n### System Capacities:\n";
        $markdown .= "*   **Total Cluster Memory Footprint**: **`{$data['licensing']['total_ram_gb']} GB RAM`**\n\n";

        $markdown .= "---\n\n";

        $markdown .= "## 4. Elastic Resource Unit (ERU) Licensing Cost\n\n";
        $markdown .= '$$\\text{Required ERUs} = \\left\\lceil \\frac{'.$data['licensing']['total_ram_gb'].'\\text{ GB (Total RAM)}}{64\\text{ GB (1 ERU)}} \\right\\rceil = \\mathbf{'.$data['licensing']['required_erus']."\\text{ ERUs}}$$\n\n";
        $markdown .= "> [!NOTE]\n";
        $markdown .= "> **Licensing Verdict**: This configuration requires **`{$data['licensing']['required_erus']} ERU`** subscription licenses. ";
        $markdown .= 'Annual projected license cost is **`'.CurrencyHelper::format($data['licensing']['annual_cost_usd']).'`** based on '.CurrencyHelper::format($data['licensing']['eru_cost_usd'])."/ERU assumptions.\n\n";

        // Add Mermaid Diagram
        $markdown .= "### Cluster Topology Diagram\n\n";
        $markdown .= "```mermaid\ngraph TD\n";
        $markdown .= "    subgraph Cluster [Elasticsearch Cluster]\n";
        foreach ($data['nodes'] as $index => $node) {
            $markdown .= "        N{$index}[\"{$node['name']} ({$node['role']})<br>{$node['ram_gb']}GB RAM | {$node['storage_gb']}GB\"]\n";
        }
        $markdown .= "    end\n```\n";

        $fileName = strtolower(str_replace(' ', '_', $client->name)).'_scenario_'.$scenario->id.'_report.md';

        return new StreamedResponse(function () use ($markdown) {
            echo $markdown;
        }, 200, [
            'Content-Type' => 'text/markdown',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Export the sizing scenario workbook to Excel using live formulas and modern design.
     */
    public function exportExcel(Client $client, Scenario $scenario)
    {
        $spreadsheet = new Spreadsheet;

        // Set default font to Segoe UI and size to 10
        $spreadsheet->getDefaultStyle()->getFont()->setName('Segoe UI');
        $spreadsheet->getDefaultStyle()->getFont()->setSize(10);

        // Remove default sheet
        $spreadsheet->removeSheetByIndex(0);

        // Calculate all scenarios first to build Sheet 3 dynamically
        $scenarios = Scenario::orderBy('id')->get();
        $scenarioRowMapping = [];

        // Pre-create sheets at correct indices:
        $sheet1 = $spreadsheet->createSheet(0);
        $sheet1->setTitle('1. Dashboard & Costs');

        $sheet2 = $spreadsheet->createSheet(1);
        $sheet2->setTitle('2. Ingestion & Storage Sizing');

        $sheet3 = $spreadsheet->createSheet(2);
        $sheet3->setTitle('3. Infrastructure Details');

        // Styles Definition
        $fontFamily = 'Segoe UI';

        $titleStyle = [
            'font' => [
                'name' => $fontFamily,
                'size' => 14,
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F172A'], // Slate 900
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        $sectionHeaderStyle = [
            'font' => [
                'name' => $fontFamily,
                'size' => 11,
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F766E'], // Deep Teal (Teal 700)
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
            ],
        ];

        $tableHeaderStyle = [
            'font' => [
                'name' => $fontFamily,
                'size' => 10,
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'], // Slate 800
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '475569'],
                ],
            ],
        ];

        $borderThin = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0'], // Slate 200
                ],
            ],
        ];

        $totalRowStyle = [
            'font' => [
                'name' => $fontFamily,
                'size' => 10,
                'bold' => true,
            ],
            'borders' => [
                'top' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '94A3B8'],
                ],
                'bottom' => [
                    'borderStyle' => Border::BORDER_DOUBLE,
                    'color' => ['rgb' => '0F172A'],
                ],
            ],
        ];

        $zebraFill = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F8FAFC'], // Slate 50
            ],
        ];

        // 3. Populate Sheet 3 first (so we can map rows for Sheet 1 formulas)
        $sheet3->mergeCells('A1:F2');
        $sheet3->setCellValue('A1', 'Scenario Infrastructure Sizing Details');
        $sheet3->getStyle('A1:F2')->applyFromArray($titleStyle);
        $sheet3->getRowDimension(1)->setRowHeight(25);
        $sheet3->getRowDimension(2)->setRowHeight(25);

        $currentRow = 4;
        foreach ($scenarios as $sc) {
            $data = $this->sizingEngine->calculate($client, $sc);

            $sheet3->mergeCells("A{$currentRow}:F{$currentRow}");
            $sheet3->setCellValue("A{$currentRow}", "Scenario {$sc->id}: {$sc->description}");
            $sheet3->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray($sectionHeaderStyle);
            $sheet3->getRowDimension($currentRow)->setRowHeight(26);

            $headerRow = $currentRow + 1;
            $sheet3->setCellValue("A{$headerRow}", 'Node Name');
            $sheet3->setCellValue("B{$headerRow}", 'Role');
            $sheet3->setCellValue("C{$headerRow}", 'Count');
            $sheet3->setCellValue("D{$headerRow}", 'RAM per Node (GB)');
            $sheet3->setCellValue("E{$headerRow}", 'JVM Heap (GB)');
            $sheet3->setCellValue("F{$headerRow}", 'Total Node RAM (GB)');

            $sheet3->getStyle("A{$headerRow}:F{$headerRow}")->applyFromArray($tableHeaderStyle);
            $sheet3->getStyle("A{$headerRow}:B{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet3->getStyle("C{$headerRow}:F{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet3->getRowDimension($headerRow)->setRowHeight(22);

            $startNodeRow = $headerRow + 1;
            foreach ($data['nodes'] as $nIdx => $node) {
                $nodeRow = $startNodeRow + $nIdx;
                $sheet3->setCellValue("A{$nodeRow}", $node['name']);
                $sheet3->setCellValue("B{$nodeRow}", $node['role']);
                $sheet3->setCellValue("C{$nodeRow}", $node['count']);
                $sheet3->setCellValue("D{$nodeRow}", $node['ram_gb']);
                $sheet3->setCellValue("E{$nodeRow}", "=D{$nodeRow}/2");
                $sheet3->setCellValue("F{$nodeRow}", "=C{$nodeRow}*D{$nodeRow}");

                $sheet3->getRowDimension($nodeRow)->setRowHeight(20);

                // Alignments
                $sheet3->getStyle("A{$nodeRow}:B{$nodeRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet3->getStyle("C{$nodeRow}:F{$nodeRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Formats
                $sheet3->getStyle("C{$nodeRow}:F{$nodeRow}")->getNumberFormat()->setFormatCode('#,##0');

                // Zebra Striping
                if ($nIdx % 2 === 1) {
                    $sheet3->getStyle("A{$nodeRow}:F{$nodeRow}")->applyFromArray($zebraFill);
                }
            }

            $endNodeRow = $startNodeRow + count($data['nodes']) - 1;
            $sheet3->getStyle("A{$startNodeRow}:F{$endNodeRow}")->applyFromArray($borderThin);

            $totalRamRow = $endNodeRow + 1;
            $eruRow = $totalRamRow + 1;

            $sheet3->setCellValue("A{$totalRamRow}", 'Total RAM (GB)');
            $sheet3->setCellValue("D{$totalRamRow}", "=SUM(F{$startNodeRow}:F{$endNodeRow})");
            $sheet3->setCellValue("F{$totalRamRow}", "=D{$totalRamRow}");

            $sheet3->getRowDimension($totalRamRow)->setRowHeight(20);
            $sheet3->getStyle("A{$totalRamRow}:F{$totalRamRow}")->applyFromArray($totalRowStyle);
            $sheet3->getStyle("D{$totalRamRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet3->getStyle("F{$totalRamRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet3->getStyle("D{$totalRamRow}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet3->getStyle("F{$totalRamRow}")->getNumberFormat()->setFormatCode('#,##0');

            $sheet3->setCellValue("A{$eruRow}", 'Required ERUs');
            $sheet3->setCellValue("D{$eruRow}", "=CEILING(D{$totalRamRow}/'1. Dashboard & Costs'!\$B\$14, 1)");

            $sheet3->getRowDimension($eruRow)->setRowHeight(20);
            $sheet3->getStyle("A{$eruRow}:F{$eruRow}")->applyFromArray([
                'font' => ['bold' => true],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '0F172A'],
                    ],
                ],
            ]);
            $sheet3->getStyle("D{$eruRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet3->getStyle("D{$eruRow}")->getNumberFormat()->setFormatCode('#,##0');

            $scenarioRowMapping[$sc->id] = [
                'ram_cell' => "D{$totalRamRow}",
                'eru_cell' => "D{$eruRow}",
            ];

            // Space between scenarios
            $currentRow = $eruRow + 3;
        }

        // 1. Populate Sheet 1: Dashboard & Costs
        $sheet1->mergeCells('A1:H2');
        $sheet1->setCellValue('A1', "Client '".strtoupper($client->name)."' Elasticsearch Sizing & Cost Dashboard");
        $sheet1->getStyle('A1:H2')->applyFromArray($titleStyle);
        $sheet1->getRowDimension(1)->setRowHeight(25);
        $sheet1->getRowDimension(2)->setRowHeight(25);

        // General assumptions section
        $sheet1->mergeCells('A5:D5');
        $sheet1->setCellValue('A5', 'General Inputs & Assumptions');
        $sheet1->getStyle('A5:D5')->applyFromArray($sectionHeaderStyle);
        $sheet1->getRowDimension(5)->setRowHeight(26);

        $sheet1->setCellValue('A6', 'Parameter');
        $sheet1->setCellValue('B6', 'Value');
        $sheet1->setCellValue('C6', 'Unit');
        $sheet1->setCellValue('D6', 'Notes');
        $sheet1->getStyle('A6:D6')->applyFromArray($tableHeaderStyle);
        $sheet1->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet1->getStyle('B6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet1->getStyle('C6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle('D6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet1->getRowDimension(6)->setRowHeight(22);

        // Dynamic counts of devices based on client asset inventory
        $assets = $client->clientAssets()->with('assetType')->get();

        $adCount = $assets->where('asset_type_id', 1)->first()?->device_count ?? 2;
        $fwCount = $assets->where('asset_type_id', 2)->first()?->device_count ?? 2;
        $swCount = $assets->where('asset_type_id', 6)->first()?->device_count ?? 10;
        $winCount = $assets->where('asset_type_id', 4)->first()?->device_count ?? 20;
        $linCount = $assets->where('asset_type_id', 5)->first()?->device_count ?? 10;
        $epCount = $assets->where('asset_type_id', 3)->first()?->device_count ?? 150;

        $assumptions = [
            ['Active Directory Servers', $adCount, 'DC', 'From client asset inventory'],
            ['FortiGate Firewalls', $fwCount, 'Unit', 'From client asset inventory'],
            ['Core Network Switches', $swCount, 'Unit', 'From client asset inventory'],
            ['Windows Servers', $winCount, 'Server', 'From client asset inventory'],
            ['Linux Servers', $linCount, 'Server', 'From client asset inventory'],
            ['EDR / XDR Integration', $epCount, 'Endpoint', 'From client asset inventory'],
            ['Annual subscription cost per ERU', CurrencyHelper::convert(14000), CurrencyHelper::active(), 'Commercial list price'],
            ['Elastic Resource Unit (ERU) Limit', 64, 'GB', 'RAM per single subscription unit'],
            ['Elastic Index Expansion Factor', 1.25, 'Multiplier', 'Metadata & index overhead factor'],
        ];

        foreach ($assumptions as $i => $row) {
            $rowNum = 7 + $i;
            $sheet1->setCellValue("A{$rowNum}", $row[0]);
            $sheet1->setCellValue("B{$rowNum}", $row[1]);
            $sheet1->setCellValue("C{$rowNum}", $row[2]);
            $sheet1->setCellValue("D{$rowNum}", $row[3]);

            $sheet1->getRowDimension($rowNum)->setRowHeight(20);
            $sheet1->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet1->getStyle("B{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet1->getStyle("C{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getStyle("D{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            // Format value
            if ($row[0] === 'Annual subscription cost per ERU') {
                $sheet1->getStyle("B{$rowNum}")->getNumberFormat()->setFormatCode(CurrencyHelper::excelFormatCode(false));
            } elseif ($row[0] === 'Elastic Index Expansion Factor') {
                $sheet1->getStyle("B{$rowNum}")->getNumberFormat()->setFormatCode('0.00');
            } else {
                $sheet1->getStyle("B{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');
            }

            // Zebra
            if ($i % 2 === 1) {
                $sheet1->getStyle("A{$rowNum}:D{$rowNum}")->applyFromArray($zebraFill);
            }
        }
        $sheet1->getStyle('A7:D15')->applyFromArray($borderThin);

        // Scenario Cost section
        $sheet1->mergeCells('A18:H18');
        $sheet1->setCellValue('A18', 'Scenario Sizing & Cost Comparison');
        $sheet1->getStyle('A18:H18')->applyFromArray($sectionHeaderStyle);
        $sheet1->getRowDimension(18)->setRowHeight(26);

        $sheet1->setCellValue('A19', 'Scenario #');
        $sheet1->setCellValue('B19', 'Scenario Description');
        $sheet1->setCellValue('C19', 'Workload Profile');
        $sheet1->setCellValue('D19', 'Retention (Days)');
        $sheet1->setCellValue('E19', 'Tier Architecture');
        $sheet1->setCellValue('F19', 'Total RAM (GB)');
        $sheet1->setCellValue('G19', 'Required ERUs');
        $sheet1->setCellValue('H19', 'Annual Subscription Cost');
        $sheet1->getStyle('A19:H19')->applyFromArray($tableHeaderStyle);

        $sheet1->getStyle('A19')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle('B19:C19')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet1->getStyle('D19')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet1->getStyle('E19')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet1->getStyle('F19:H19')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet1->getRowDimension(19)->setRowHeight(22);

        foreach ($scenarios as $idx => $sc) {
            $rowNum = 20 + $idx;
            $sheet1->setCellValue("A{$rowNum}", "Scenario {$sc->id}");
            $sheet1->setCellValue("B{$rowNum}", $sc->description);

            $profileText = ($sc->workload_profile === 'min') ? 'Minimum' : (($sc->workload_profile === 'avg') ? 'Average' : 'Maximum');
            $sheet1->setCellValue("C{$rowNum}", $profileText);
            $sheet1->setCellValue("D{$rowNum}", $sc->retention_days);

            $tiers = [];
            if ($sc->hot_days > 0) {
                $tiers[] = 'Hot';
            }
            if ($sc->warm_days > 0) {
                $tiers[] = 'Warm';
            }
            if ($sc->cold_days > 0) {
                $tiers[] = 'Cold';
            }
            if ($sc->frozen_days > 0) {
                $tiers[] = 'Frozen';
            }
            $architecture = implode(' + ', $tiers);
            $sheet1->setCellValue("E{$rowNum}", $architecture);

            $ramCell = $scenarioRowMapping[$sc->id]['ram_cell'];
            $eruCell = $scenarioRowMapping[$sc->id]['eru_cell'];

            $sheet1->setCellValue("F{$rowNum}", "='3. Infrastructure Details'!{$ramCell}");
            $sheet1->setCellValue("G{$rowNum}", "='3. Infrastructure Details'!{$eruCell}");
            $sheet1->setCellValue("H{$rowNum}", "=G{$rowNum}*'1. Dashboard & Costs'!\$B\$13");

            $sheet1->getRowDimension($rowNum)->setRowHeight(20);

            // Alignments
            $sheet1->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getStyle("B{$rowNum}:C{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet1->getStyle("D{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet1->getStyle("E{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet1->getStyle("F{$rowNum}:H{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Formats
            $sheet1->getStyle("D{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet1->getStyle("F{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet1->getStyle("G{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet1->getStyle("H{$rowNum}")->getNumberFormat()->setFormatCode(CurrencyHelper::excelFormatCode(false));

            // Zebra
            if ($idx % 2 === 1) {
                $sheet1->getStyle("A{$rowNum}:H{$rowNum}")->applyFromArray($zebraFill);
            }
        }
        $endCostRow = 20 + count($scenarios) - 1;
        $sheet1->getStyle("A20:H{$endCostRow}")->applyFromArray($borderThin);

        // 2. Populate Sheet 2: Ingestion & Storage Sizing
        $sheet2->mergeCells('A1:I2');
        $sheet2->setCellValue('A1', 'Log Ingestion & Storage Volume Sizing');
        $sheet2->getStyle('A1:I2')->applyFromArray($titleStyle);
        $sheet2->getRowDimension(1)->setRowHeight(25);
        $sheet2->getRowDimension(2)->setRowHeight(25);

        // Section: Logging Calibration
        $sheet2->mergeCells('A5:D5');
        $sheet2->setCellValue('A5', 'Logging Calibration Parameters');
        $sheet2->getStyle('A5:D5')->applyFromArray($sectionHeaderStyle);
        $sheet2->getRowDimension(5)->setRowHeight(26);

        $sheet2->setCellValue('A6', 'Parameter / Metric');
        $sheet2->setCellValue('B6', 'Value');
        $sheet2->setCellValue('C6', 'Unit');
        $sheet2->setCellValue('D6', 'Description');
        $sheet2->getStyle('A6:D6')->applyFromArray($tableHeaderStyle);
        $sheet2->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet2->getStyle('B6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet2->getStyle('C6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet2->getStyle('D6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet2->getRowDimension(6)->setRowHeight(22);

        $calibrationParams = [
            ['AD DC Max Raw Volume (per Server)', 90, 'GB/month', 'Active Directory log budget'],
            ['Firewall Max Raw Volume (per Firewall)', 120, 'GB/month', 'Firewall traffic log budget'],
            ['Endpoint Security Max Raw Volume', 30, 'GB/month', 'EDR/XDR agent log budget'],
            ['Windows Server Max EPS (per Server)', 30, 'EPS', 'Events Per Second peak rate'],
            ['Windows Server Event Size', 800, 'Bytes', 'Average size of single event'],
            ['Linux Server Max EPS (per Server)', 15, 'EPS', 'Events Per Second peak rate'],
            ['Linux Server Event Size', 500, 'Bytes', 'Average size of single event'],
            ['Core Switch Max EPS (per Switch)', 15, 'EPS', 'Events Per Second peak rate'],
            ['Core Switch Event Size', 250, 'Bytes', 'Average size of single event'],
        ];

        foreach ($calibrationParams as $i => $row) {
            $rowNum = 7 + $i;
            $sheet2->setCellValue("A{$rowNum}", $row[0]);
            $sheet2->setCellValue("B{$rowNum}", $row[1]);
            $sheet2->setCellValue("C{$rowNum}", $row[2]);
            $sheet2->setCellValue("D{$rowNum}", $row[3]);

            $sheet2->getRowDimension($rowNum)->setRowHeight(20);
            $sheet2->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet2->getStyle("B{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet2->getStyle("C{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle("D{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            $sheet2->getStyle("B{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');

            if ($i % 2 === 1) {
                $sheet2->getStyle("A{$rowNum}:D{$rowNum}")->applyFromArray($zebraFill);
            }
        }
        $sheet2->getStyle('A7:D15')->applyFromArray($borderThin);

        // Section: Daily Raw Ingest Profiles
        $sheet2->mergeCells('A18:D18');
        $sheet2->setCellValue('A18', 'Daily Raw Ingest Profiles (GB/day)');
        $sheet2->getStyle('A18:D18')->applyFromArray($sectionHeaderStyle);
        $sheet2->getRowDimension(18)->setRowHeight(26);

        $sheet2->setCellValue('A19', 'Log Source');
        $sheet2->setCellValue('B19', 'Min Daily Raw (GB)');
        $sheet2->setCellValue('C19', 'Avg Daily Raw (GB)');
        $sheet2->setCellValue('D19', 'Max Daily Raw (GB)');
        $sheet2->getStyle('A19:D19')->applyFromArray($tableHeaderStyle);
        $sheet2->getStyle('A19')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet2->getStyle('B19:D19')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet2->getRowDimension(19)->setRowHeight(22);

        $sources = [
            ['Active Directory', 0.60, 2.67, "=('1. Dashboard & Costs'!B7*B7)/30"],
            ['FortiGate Firewalls', 1.00, 3.33, "=('1. Dashboard & Costs'!B8*B8)/30"],
            ['EDR / XDR Integration', 0.10, 0.40, '=B9/30'],
            ['Windows Servers', 1.38, 11.06, "=('1. Dashboard & Costs'!B10*B10*B11*86400)/1000000000"],
            ['Linux Servers', 0.22, 1.30, "=('1. Dashboard & Costs'!B11*B12*B13*86400)/1000000000"],
            ['Network Switches', 0.02, 0.43, "=('1. Dashboard & Costs'!B9*B14*B15*86400)/1000000000"],
        ];

        foreach ($sources as $i => $row) {
            $rowNum = 20 + $i;
            $sheet2->setCellValue("A{$rowNum}", $row[0]);
            $sheet2->setCellValue("B{$rowNum}", $row[1]);
            $sheet2->setCellValue("C{$rowNum}", $row[2]);
            $sheet2->setCellValue("D{$rowNum}", $row[3]);

            $sheet2->getRowDimension($rowNum)->setRowHeight(20);
            $sheet2->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet2->getStyle("B{$rowNum}:D{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet2->getStyle("B{$rowNum}:D{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');

            if ($i % 2 === 1) {
                $sheet2->getStyle("A{$rowNum}:D{$rowNum}")->applyFromArray($zebraFill);
            }
        }
        $sheet2->getStyle('A20:D25')->applyFromArray($borderThin);

        // Row 26: Total Daily Raw
        $sheet2->setCellValue('A26', 'Total Daily Raw');
        $sheet2->setCellValue('B26', '=SUM(B20:B25)');
        $sheet2->setCellValue('C26', '=SUM(C20:C25)');
        $sheet2->setCellValue('D26', '=SUM(D20:D25)');
        $sheet2->getRowDimension(26)->setRowHeight(20);
        $sheet2->getStyle('A26:D26')->applyFromArray($totalRowStyle);
        $sheet2->getStyle('B26:D26')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet2->getStyle('B26:D26')->getNumberFormat()->setFormatCode('#,##0.00');

        // Section: Storage by Scenario
        $sheet2->mergeCells('A29:I29');
        $sheet2->setCellValue('A29', 'Physical Storage Sizing by Scenario (GB)');
        $sheet2->getStyle('A29:I29')->applyFromArray($sectionHeaderStyle);
        $sheet2->getRowDimension(29)->setRowHeight(26);

        $sheet2->setCellValue('A30', 'Scenario #');
        $sheet2->setCellValue('B30', 'Raw Daily (GB)');
        $sheet2->setCellValue('C30', 'Indexed Daily (GB)');
        $sheet2->setCellValue('D30', 'Retention (Days)');
        $sheet2->setCellValue('E30', 'Hot SSD (GB)');
        $sheet2->setCellValue('F30', 'Warm HDD (GB)');
        $sheet2->setCellValue('G30', 'Cold S3/MinIO (GB)');
        $sheet2->setCellValue('H30', 'Frozen Cache (GB)');
        $sheet2->setCellValue('I30', 'Total Storage Footprint');
        $sheet2->getStyle('A30:I30')->applyFromArray($tableHeaderStyle);

        $sheet2->getStyle('A30')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet2->getStyle('B30:I30')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet2->getRowDimension(30)->setRowHeight(22);

        foreach ($scenarios as $idx => $sc) {
            $rowNum = 31 + $idx;
            $sheet2->setCellValue("A{$rowNum}", "Scenario {$sc->id}");

            $rawCol = ($sc->workload_profile === 'min') ? 'B' : (($sc->workload_profile === 'avg') ? 'C' : 'D');
            $sheet2->setCellValue("B{$rowNum}", "={$rawCol}26");
            $sheet2->setCellValue("C{$rowNum}", "=B{$rowNum}*'1. Dashboard & Costs'!\$B\$15");
            $sheet2->setCellValue("D{$rowNum}", $sc->retention_days);

            if ($sc->hot_days > 0) {
                $sheet2->setCellValue("E{$rowNum}", "=C{$rowNum}*".($sc->hot_replicas + 1)."*{$sc->hot_days}");
            } else {
                $sheet2->setCellValue("E{$rowNum}", 0);
            }

            if ($sc->warm_days > 0) {
                $sheet2->setCellValue("F{$rowNum}", "=C{$rowNum}*".($sc->warm_replicas + 1)."*{$sc->warm_days}");
            } else {
                $sheet2->setCellValue("F{$rowNum}", 0);
            }

            if ($sc->cold_days > 0) {
                $sheet2->setCellValue("G{$rowNum}", "=C{$rowNum}*".($sc->cold_replicas + 1)."*{$sc->cold_days}");
            } else {
                $sheet2->setCellValue("G{$rowNum}", 0);
            }

            if ($sc->frozen_days > 0) {
                $sheet2->setCellValue("H{$rowNum}", "=C{$rowNum}*".($sc->frozen_replicas + 1)."*{$sc->frozen_days}");
            } else {
                $sheet2->setCellValue("H{$rowNum}", 0);
            }

            $sheet2->setCellValue("I{$rowNum}", "=SUM(E{$rowNum}:H{$rowNum})");

            $sheet2->getRowDimension($rowNum)->setRowHeight(20);

            // Alignments & Formats
            $sheet2->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle("B{$rowNum}:C{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet2->getStyle("D{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet2->getStyle("E{$rowNum}:I{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet2->getStyle("B{$rowNum}:C{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet2->getStyle("D{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet2->getStyle("E{$rowNum}:I{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');

            if ($idx % 2 === 1) {
                $sheet2->getStyle("A{$rowNum}:I{$rowNum}")->applyFromArray($zebraFill);
            }
        }
        $endStorageRow = 31 + count($scenarios) - 1;
        $sheet2->getStyle("A31:I{$endStorageRow}")->applyFromArray($borderThin);

        // Autofit columns & enable gridlines
        foreach ($spreadsheet->getAllSheets() as $sh) {
            $sh->setShowGridlines(true);
            foreach (range('A', 'I') as $col) {
                $sh->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = strtolower(str_replace(' ', '_', $client->name)).'_sizing_model.xlsx';

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Export the sizing scenario to Word (.doc) formatted report.
     */
    public function exportWord(Client $client, Scenario $scenario)
    {
        $data = $this->sizingEngine->calculate($client, $scenario);

        $fileName = strtolower(str_replace(' ', '_', $client->name)).'_scenario_'.$scenario->id.'_report.doc';

        // Build HTML template
        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="utf-8">
    <title>ElasticCost Sizing & Cost Report</title>
    <!--[if gte mso 9]>
    <xml>
        <w:WordDocument>
            <w:View>Print</w:View>
            <w:Zoom>100</w:Zoom>
            <w:DoNotOptimizeForBrowser/>
        </w:WordDocument>
    </xml>
    <![endif]-->
    <style>
        @page {
            size: A4;
            margin: 1in;
        }
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            color: #1e293b;
            line-height: 1.5;
            font-size: 11pt;
        }
        .title-banner {
            background-color: #0f172a;
            color: #ffffff;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 6px solid #0d9488;
        }
        .title-banner h1 {
            font-size: 22pt;
            font-weight: bold;
            margin: 0;
            color: #ffffff;
            border: none;
            padding: 0;
        }
        .title-banner p {
            margin: 5px 0 0 0;
            color: #94a3b8;
            font-size: 11pt;
        }
        h2 {
            font-size: 14pt;
            color: #0f766e;
            margin-top: 25px;
            margin-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 4px;
        }
        h3 {
            font-size: 12pt;
            color: #1e293b;
            margin-top: 15px;
            margin-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 10pt;
        }
        th {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 8px;
            border: 1px solid #cbd5e1;
        }
        td {
            padding: 8px;
            border: 1px solid #e2e8f0;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-weight: bold;
            font-size: 8pt;
            color: #ffffff;
            border-radius: 3px;
        }
        .badge-hot { background-color: #ef4444; }
        .badge-warm { background-color: #f97316; }
        .badge-cold { background-color: #3b82f6; }
        .badge-frozen { background-color: #6366f1; }
        
        .alert {
            background-color: #f0fdfa;
            border-left: 4px solid #0f766e;
            padding: 12px;
            margin: 15px 0;
            font-style: italic;
        }
        .mono {
            font-family: Consolas, Monaco, monospace;
            background-color: #f1f5f9;
            padding: 1px 4px;
            font-size: 9.5pt;
        }
    </style>
</head>
<body>

    <div class="title-banner">
        <h1>ElasticSearch Sizing & Cost Report</h1>
        <p>Client: '.htmlspecialchars($client->name).' &bull; Scenario: '.htmlspecialchars($scenario->name).' &bull; Profile: '.ucfirst($scenario->workload_profile).'</p>
    </div>

    <h2>1. Workload & Ingest Parameters</h2>
    <p>This report details the architectural footprint and licensing cost for <strong>Client "'.htmlspecialchars($client->name).'"</strong> under the <strong>'.htmlspecialchars($scenario->name).'</strong> sizing scenario.</p>
    <ul>
        <li><strong>Ingestion Profile:</strong> '.ucfirst($scenario->workload_profile).' Workload</li>
        <li><strong>Daily Raw Log Volume:</strong> <span class="mono">'.number_format($data['totals']['daily_raw_gb'], 2).' GB/day</span></li>
        <li><strong>Daily Indexed Volume (+25% Expansion):</strong> <span class="mono">'.number_format($data['totals']['daily_indexed_gb'], 2).' GB/day</span></li>
        <li><strong>Retention Period:</strong> <span class="mono">'.number_format($scenario->retention_days).' Days</span></li>
    </ul>

    <h3>ILM Data Lifecycle Tiers</h3>
    <table>
        <thead>
            <tr>
                <th>Data Lifecycle Tier</th>
                <th class="text-center">Retention Period</th>
                <th class="text-center">Replicas</th>
                <th>Daily Indexed Volume</th>
            </tr>
        </thead>
        <tbody>';

        if ($scenario->hot_days > 0) {
            $html .= '
            <tr>
                <td><span class="badge badge-hot">HOT</span> primary ingest, high search rate</td>
                <td class="text-center">'.$scenario->hot_days.' Days</td>
                <td class="text-center">'.$scenario->hot_replicas.'</td>
                <td>'.number_format($data['totals']['daily_ingested_gb'], 2).' GB/day (incl. replicas)</td>
            </tr>';
        }
        if ($scenario->warm_days > 0) {
            $html .= '
            <tr>
                <td><span class="badge badge-warm">WARM</span> active search, lower performance storage</td>
                <td class="text-center">'.$scenario->warm_days.' Days</td>
                <td class="text-center">'.$scenario->warm_replicas.'</td>
                <td>'.number_format($data['totals']['daily_indexed_gb'] * ($scenario->warm_replicas + 1), 2).' GB/day</td>
            </tr>';
        }
        if ($scenario->cold_days > 0) {
            $html .= '
            <tr>
                <td><span class="badge badge-cold">COLD</span> read-only search, object storage cache</td>
                <td class="text-center">'.$scenario->cold_days.' Days</td>
                <td class="text-center">'.$scenario->cold_replicas.' (0% replica overhead)</td>
                <td>'.number_format($data['totals']['daily_indexed_gb'], 2).' GB/day</td>
            </tr>';
        }
        if ($scenario->frozen_days > 0) {
            $html .= '
            <tr>
                <td><span class="badge badge-frozen">FROZEN</span> archive search, searchable snapshots only</td>
                <td class="text-center">'.$scenario->frozen_days.' Days</td>
                <td class="text-center">'.$scenario->frozen_replicas.' (0% replica overhead)</td>
                <td>'.number_format($data['totals']['daily_indexed_gb'], 2).' GB/day</td>
            </tr>';
        }

        $html .= '
        </tbody>
    </table>

    <h2>2. Storage Sizing Calculations</h2>
    <ul>
        <li><strong>Total Raw Data Stored:</strong> '.number_format($data['totals']['daily_raw_gb'], 2).' GB/day * '.$scenario->retention_days.' days = <strong>'.number_format($data['totals']['total_raw_storage_gb'], 2).' GB</strong></li>
        <li><strong>Total Indexed Data (Active):</strong> '.number_format($data['totals']['daily_indexed_gb'], 2).' GB/day * '.$scenario->retention_days.' days = <strong>'.number_format($data['totals']['total_indexed_storage_gb'], 2).' GB</strong></li>
        <li><strong>Total Cluster Storage Required:</strong> <strong>'.number_format($data['totals']['total_storage_footprint_gb'], 2).' GB</strong> physical footprint</li>
    </ul>

    <h3>Tier Storage Breakdown</h3>
    <table>
        <thead>
            <tr>
                <th>Tier</th>
                <th>Storage Type</th>
                <th class="text-right">Physical Space Required</th>
            </tr>
        </thead>
        <tbody>';
        if ($scenario->hot_days > 0) {
            $html .= '<tr><td><span class="badge badge-hot">HOT</span></td><td>NVMe SSD (Primary + Replica)</td><td class="text-right"><strong>'.number_format($data['totals']['hot_storage_gb'], 2).' GB</strong></td></tr>';
        }
        if ($scenario->warm_days > 0) {
            $html .= '<tr><td><span class="badge badge-warm">WARM</span></td><td>SATA SSD / HDD</td><td class="text-right"><strong>'.number_format($data['totals']['warm_storage_gb'], 2).' GB</strong></td></tr>';
        }
        if ($scenario->cold_days > 0) {
            $html .= '<tr><td><span class="badge badge-cold">COLD</span></td><td>Object Store + Local Cache</td><td class="text-right"><strong>'.number_format($data['totals']['cold_storage_gb'], 2).' GB</strong></td></tr>';
        }
        if ($scenario->frozen_days > 0) {
            $html .= '<tr><td><span class="badge badge-frozen">FROZEN</span></td><td>Object Store Cache</td><td class="text-right"><strong>'.number_format($data['totals']['frozen_storage_gb'], 2).' GB</strong></td></tr>';
        }
        $html .= '
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="2">Total Physical Storage Footprint</td>
                <td class="text-right">'.number_format($data['totals']['total_storage_footprint_gb'], 2).' GB</td>
            </tr>
        </tbody>
    </table>

    <h2>3. Recommended Cluster Architecture (On-Premises VMs)</h2>
    <p>The following deployment cluster topology is recommended to satisfy the storage and RAM-to-disk ratio requirements:</p>
    <table>
        <thead>
            <tr>
                <th>Node Name</th>
                <th>Node Role</th>
                <th class="text-center">Count</th>
                <th class="text-right">RAM / Node</th>
                <th class="text-right">JVM Heap</th>
                <th class="text-right">Storage / Node</th>
                <th>Storage Type</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($data['nodes'] as $node) {
            $gbVal = $node['storage_gb'] >= 1000 ? ($node['storage_gb'] / 1000).' TB' : $node['storage_gb'].' GB';
            $html .= '
            <tr>
                <td><strong>'.htmlspecialchars($node['name']).'</strong></td>
                <td>'.htmlspecialchars($node['role']).'</td>
                <td class="text-center">'.$node['count'].'</td>
                <td class="text-right">'.$node['ram_gb'].' GB</td>
                <td class="text-right">'.$node['heap_gb'].' GB</td>
                <td class="text-right">'.$gbVal.'</td>
                <td>'.htmlspecialchars($node['storage_type']).'</td>
            </tr>';
        }

        $html .= '
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="3">Total Cluster Memory Footprint</td>
                <td class="text-right" colspan="4">'.number_format($data['licensing']['total_ram_gb']).' GB RAM</td>
            </tr>
        </tbody>
    </table>

    <h2>4. Elastic Resource Unit (ERU) Licensing Cost</h2>
    <p>Required ERUs are calculated using the total memory footprint divided by the standard ERU size:</p>
    <div style="text-align: center; margin: 15px 0; font-size: 12pt; font-weight: bold; color: #0f766e;">
        Required ERUs = Ceiling( '.$data['licensing']['total_ram_gb'].' GB RAM / 64 GB ) = '.$data['licensing']['required_erus'].' ERUs
    </div>

    <div class="alert">
        <strong>Licensing Verdict:</strong> This configuration requires <strong>'.$data['licensing']['required_erus'].' ERU</strong> subscription licenses. 
        Annual projected license cost is <strong>'.CurrencyHelper::format($data['licensing']['annual_cost_usd']).'</strong> based on an assumed '.CurrencyHelper::format($data['licensing']['eru_cost_usd']).'/ERU commercial list price.
    </div>

</body>
</html>';

        return new StreamedResponse(function () use ($html) {
            echo $html;
        }, 200, [
            'Content-Type' => 'application/msword',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Save custom nodes layout for the client and scenario.
     */
    public function saveCustomNodes(Request $request, Client $client, Scenario $scenario)
    {
        $validated = $request->validate([
            'nodes' => 'required|array',
            'nodes.*.name' => 'required|string|max:255',
            'nodes.*.role' => 'required|string|max:255',
            'nodes.*.count' => 'required|integer|min:1',
            'nodes.*.ram_gb' => 'required|numeric|min:0.1',
            'nodes.*.storage_gb' => 'required|numeric|min:0.1',
            'nodes.*.storage_type' => 'required|string|max:255',
        ]);

        $msspDetail = ClientScenarioMsspDetail::firstOrCreate([
            'client_id' => $client->id,
            'scenario_id' => $scenario->id,
        ]);

        $msspDetail->update([
            'custom_nodes' => $validated['nodes'],
        ]);

        return redirect()->back()->with('success', 'Custom node topology saved successfully.');
    }

    /**
     * Reset custom nodes to the default engine recommendations.
     */
    public function resetCustomNodes(Client $client, Scenario $scenario)
    {
        $msspDetail = ClientScenarioMsspDetail::where([
            'client_id' => $client->id,
            'scenario_id' => $scenario->id,
        ])->first();

        if ($msspDetail) {
            $msspDetail->update([
                'custom_nodes' => null,
            ]);
        }

        return redirect()->back()->with('success', 'Node topology reset to auto-recommendations.');
    }
}
