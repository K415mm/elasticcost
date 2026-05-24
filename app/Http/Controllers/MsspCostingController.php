<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Scenario;
use App\Models\ClientScenarioAnalystAllocation;
use App\Services\MsspCostingEngine;
use App\Services\CurrencyHelper;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Ai\Agents\OfferAnalyst;
use Laravel\Ai\Enums\Lab;

class MsspCostingController extends Controller
{
    protected MsspCostingEngine $costingEngine;

    public function __construct(MsspCostingEngine $costingEngine)
    {
        $this->costingEngine = $costingEngine;
    }

    /**
     * Display the MSSP Cost Proposal dashboard.
     */
    public function show(Client $client, Scenario $scenario)
    {
        // Calculate all costs
        $costData = $this->costingEngine->calculate($client, $scenario);

        // Fetch all scenarios for comparison / selector sidebar
        $scenarios = Scenario::all();

        return view('dashboard.mssp', compact('client', 'scenario', 'costData', 'scenarios'));
    }

    /**
     * Update the MSSP costing settings and analyst allocations.
     */
    public function update(Request $request, Client $client, Scenario $scenario)
    {
        $request->validate([
            'one_time_setup_cost' => 'required|numeric|min:0',
            'monthly_maintenance_cost' => 'required|numeric|min:0',
            'ram_monthly_cost_per_gb' => 'required|numeric|min:0',
            'nvme_ssd_monthly_cost_per_gb' => 'required|numeric|min:0',
            'sata_ssd_monthly_cost_per_gb' => 'required|numeric|min:0',
            'local_ssd_monthly_cost_per_gb' => 'required|numeric|min:0',
            'is_license_shared' => 'nullable|boolean',
            'license_share_percentage' => 'required_if:is_license_shared,1|nullable|numeric|between:0.01,100',
            'assurance_benefit_percentage' => 'nullable|numeric|between:0,100',
            'marketing_benefit_percentage' => 'nullable|numeric|between:0,100',
            'soc_manager_benefit_percentage' => 'nullable|numeric|between:0,100',
            'ceo_benefit_percentage' => 'nullable|numeric|between:0,100',
            'fixed_profit_percentage' => 'nullable|numeric|between:0,100',
            'allocations' => 'required|array',
            'allocations.*.percentage' => 'required|numeric|between:0,100',
            'allocations.*.custom_salary' => 'nullable|numeric|min:0',
            'allocations.*.staff_count' => 'required|integer|min:1',
        ]);

        // Get the core cost details record
        $costData = $this->costingEngine->calculate($client, $scenario);
        $msspDetail = $costData['raw_mssp_detail'];

        // Update top-level costing values (Convert back to USD)
        $msspDetail->update([
            'one_time_setup_cost' => CurrencyHelper::convertBack((float) $request->input('one_time_setup_cost')),
            'monthly_maintenance_cost' => CurrencyHelper::convertBack((float) $request->input('monthly_maintenance_cost')),
            'ram_monthly_cost_per_gb' => CurrencyHelper::convertBack((float) $request->input('ram_monthly_cost_per_gb')),
            'nvme_ssd_monthly_cost_per_gb' => CurrencyHelper::convertBack((float) $request->input('nvme_ssd_monthly_cost_per_gb')),
            'sata_ssd_monthly_cost_per_gb' => CurrencyHelper::convertBack((float) $request->input('sata_ssd_monthly_cost_per_gb')),
            'local_ssd_monthly_cost_per_gb' => CurrencyHelper::convertBack((float) $request->input('local_ssd_monthly_cost_per_gb')),
            'is_license_shared' => $request->has('is_license_shared'),
            'license_share_percentage' => $request->has('is_license_shared') ? (float) $request->input('license_share_percentage') : 100.00,
            'assurance_benefit_percentage' => (float) $request->input('assurance_benefit_percentage', 0.00),
            'marketing_benefit_percentage' => (float) $request->input('marketing_benefit_percentage', 0.00),
            'soc_manager_benefit_percentage' => (float) $request->input('soc_manager_benefit_percentage', 0.00),
            'ceo_benefit_percentage' => (float) $request->input('ceo_benefit_percentage', 0.00),
            'fixed_profit_percentage' => (float) $request->input('fixed_profit_percentage', 0.00),
        ]);

        // Update allocations
        foreach ($request->input('allocations') as $roleId => $data) {
            $customSalaryUsd = $data['custom_salary'] ? CurrencyHelper::convertBack((float) $data['custom_salary']) : null;

            ClientScenarioAnalystAllocation::updateOrCreate(
                [
                    'mssp_details_id' => $msspDetail->id,
                    'soc_role_id' => $roleId,
                ],
                [
                    'allocation_percentage' => $data['percentage'],
                    'custom_monthly_salary' => $customSalaryUsd,
                    'staff_count' => (int) ($data['staff_count'] ?? 1),
                ]
            );
        }

        return redirect()
            ->route('mssp.show', [$client->id, $scenario->id])
            ->with('success', 'MSSP Costing parameters and SOC analyst allocations updated successfully!');
    }

    /**
     * Export the MSSP Cost Proposal to Markdown.
     */
    public function exportMarkdown(Client $client, Scenario $scenario)
    {
        $costData = $this->costingEngine->calculate($client, $scenario);
        $curr = CurrencyHelper::active();
        $rate = CurrencyHelper::rate();

        $markdown = "# MSSP & SOC Cost Proposal Report: {$client->name}\n\n";
        $markdown .= "This document details the estimated costs and client offer proposal for **Client \"{$client->name}\"** under the **{$scenario->name}** scenario.\n\n";
        $markdown .= "*   **Active Currency**: **{$curr}**\n";
        $markdown .= "*   **Date**: " . date('Y-m-d') . "\n\n";
        $markdown .= "---\n\n";

        $markdown .= "## 1. SOC Analyst Staffing Allocations\n\n";
        $markdown .= "| Operational Role | Dedication (%) | Staff Count | Monthly Salary | Calculated Client Cost |\n";
        $markdown .= "| :--- | :---: | :---: | :---: | :---: |\n";
        foreach ($costData['analysts']['roles'] as $role) {
            $markdown .= "| **{$role['name']}** | {$role['allocation_percentage']}% | {$role['staff_count']} | " . CurrencyHelper::format($role['monthly_salary']) . " | " . CurrencyHelper::format($role['client_cost']) . " |\n";
        }
        $markdown .= "| **Total Staffing Cost** | | | | **" . CurrencyHelper::format($costData['analysts']['total_monthly_analyst_cost']) . "** |\n\n";

        $markdown .= "---\n\n";

        $markdown .= "## 2. VM Hosting Infrastructure\n\n";
        $markdown .= "| Node Type / Role | Instance Count | RAM / Node | Storage / Node | Total Monthly Cost |\n";
        $markdown .= "| :--- | :---: | :---: | :---: | :---: |\n";
        foreach ($costData['infrastructure']['nodes'] as $node) {
            $storage = $node['storage_gb'] >= 1000 ? ($node['storage_gb']/1000) . " TB" : $node['storage_gb'] . " GB";
            $markdown .= "| **{$node['name']}** ({$node['role']}) | x{$node['count']} | {$node['ram_gb']} GB | {$storage} ({$node['storage_type']}) | " . CurrencyHelper::format($node['total_monthly_cost']) . " |\n";
        }
        $markdown .= "| **Total Hosting Cost** | | | | **" . CurrencyHelper::format($costData['infrastructure']['total_monthly_infra_cost']) . "** |\n\n";

        $markdown .= "---\n\n";

        $markdown .= "## 3. Software Licensing & Maintenance\n\n";
        $licenseStatus = $costData['raw_mssp_detail']->is_license_shared ? "Shared (" . $costData['raw_mssp_detail']->license_share_percentage . "% allocated)" : "Dedicated";
        $markdown .= "*   **Elastic Search License Status**: {$licenseStatus}\n";
        $markdown .= "*   **Monthly License Cost Equivalent**: **" . CurrencyHelper::format($costData['sizing_summary']['monthly_license_usd']) . "**\n";
        $markdown .= "*   **Monthly Operational Maintenance**: **" . CurrencyHelper::format($costData['monthly_maintenance_cost']) . "**\n\n";

        $markdown .= "---\n\n";

        $markdown .= "## 4. Profit Markup & Commercial Benefits\n\n";
        $markdown .= "| Profit/Benefit Factor | Percentage (%) | Monthly Profit Amount |\n";
        $markdown .= "| :--- | :---: | :---: |\n";
        $markdown .= "| Assurance Benefit | {$costData['assurance_benefit_percentage']}% | " . CurrencyHelper::format($costData['assurance_benefit_amount']) . " |\n";
        $markdown .= "| Marketing Benefit | {$costData['marketing_benefit_percentage']}% | " . CurrencyHelper::format($costData['marketing_benefit_amount']) . " |\n";
        $markdown .= "| SOC Manager Profit | {$costData['soc_manager_benefit_percentage']}% | " . CurrencyHelper::format($costData['soc_manager_benefit_amount']) . " |\n";
        $markdown .= "| CEO Profit | {$costData['ceo_benefit_percentage']}% | " . CurrencyHelper::format($costData['ceo_benefit_amount']) . " |\n";
        $markdown .= "| Fixed Profit | {$costData['fixed_profit_percentage']}% | " . CurrencyHelper::format($costData['fixed_profit_amount']) . " |\n";
        $markdown .= "| **Total Profit Margin Markup** | **{$costData['total_profit_percentage']}%** | **" . CurrencyHelper::format($costData['total_profit_amount']) . "** |\n\n";

        $markdown .= "---\n\n";

        $markdown .= "## 5. Commercial Proposal Summary\n\n";
        $markdown .= "*   **Estimated Base Cost (MRC)**: **" . CurrencyHelper::format($costData['total_monthly_service_cost']) . "**\n";
        $markdown .= "*   **Total Commercial Markup**: **+" . CurrencyHelper::format($costData['total_profit_amount']) . "** (+{$costData['total_profit_percentage']}%)\n";
        $markdown .= "*   **Final Client Offered MRC (Price)**: **" . CurrencyHelper::format($costData['client_offered_price_mrc']) . "**\n";
        $markdown .= "*   **Upfront Setup Cost (One-Time)**: **" . CurrencyHelper::format($costData['onetime_setup_cost']) . "**\n\n";

        if (!empty($costData['raw_mssp_detail']->ai_analysis)) {
            $markdown .= "---\n\n";
            $markdown .= "## 6. AI Cost & Logic Analysis\n\n";
            $markdown .= $costData['raw_mssp_detail']->ai_analysis . "\n\n";
        }

        $fileName = strtolower(str_replace(' ', '_', $client->name)) . "_mssp_cost_proposal.md";

        return new StreamedResponse(function () use ($markdown) {
            echo $markdown;
        }, 200, [
            'Content-Type' => 'text/markdown',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Export the MSSP Cost Proposal to Word (.doc / HTML template).
     */
    public function exportWord(Client $client, Scenario $scenario)
    {
        $costData = $this->costingEngine->calculate($client, $scenario);
        $curr = CurrencyHelper::active();
        $isRtl = app()->getLocale() === 'ar';
        $dir = $isRtl ? 'rtl' : 'ltr';

        $fileName = strtolower(str_replace(' ', '_', $client->name)) . "_mssp_cost_proposal.doc";

        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="utf-8">
    <title>MSSP Cost Proposal Report</title>
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
        .highlight-box {
            background-color: #f0fdfa;
            border-left: 4px solid #0d9488;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body dir="' . $dir . '">
    <div class="title-banner">
        <h1>MSSP & SOC Cost Proposal</h1>
        <p>Client: ' . htmlspecialchars($client->name) . ' | Scenario: ' . htmlspecialchars($scenario->name) . ' | Date: ' . date('Y-m-d') . ' (' . $curr . ')</p>
    </div>

    <h2>1. SOC Analyst Staffing Allocations</h2>
    <table>
        <thead>
            <tr>
                <th>Operational Role</th>
                <th class="text-center">Dedication (%)</th>
                <th class="text-center">Staff Count</th>
                <th class="text-right">Monthly Salary</th>
                <th class="text-right">Calculated Client Cost</th>
            </tr>
        </thead>
        <tbody>';
        foreach ($costData['analysts']['roles'] as $role) {
            $html .= '<tr>
                <td><strong>' . htmlspecialchars($role['name']) . '</strong></td>
                <td class="text-center">' . $role['allocation_percentage'] . '%</td>
                <td class="text-center">' . $role['staff_count'] . '</td>
                <td class="text-right">' . CurrencyHelper::format($role['monthly_salary']) . '</td>
                <td class="text-right">' . CurrencyHelper::format($role['client_cost']) . '</td>
            </tr>';
        }
        $html .= '<tr style="font-weight:bold; background-color:#e2e8f0;">
                <td colspan="4">Total Monthly Staffing Cost</td>
                <td class="text-right">' . CurrencyHelper::format($costData['analysts']['total_monthly_analyst_cost']) . '</td>
            </tr>
        </tbody>
    </table>

    <h2>2. VM Hosting Infrastructure</h2>
    <table>
        <thead>
            <tr>
                <th>Node Type / Role</th>
                <th class="text-center">Instance Count</th>
                <th class="text-center">RAM / Node</th>
                <th class="text-center">Storage / Node</th>
                <th class="text-right">Total Monthly Cost</th>
            </tr>
        </thead>
        <tbody>';
        foreach ($costData['infrastructure']['nodes'] as $node) {
            $storage = $node['storage_gb'] >= 1000 ? ($node['storage_gb']/1000) . " TB" : $node['storage_gb'] . " GB";
            $html .= '<tr>
                <td><strong>' . htmlspecialchars($node['name']) . '</strong> (' . $node['role'] . ')</td>
                <td class="text-center">x' . $node['count'] . '</td>
                <td class="text-center">' . $node['ram_gb'] . ' GB</td>
                <td class="text-center">' . $storage . ' (' . $node['storage_type'] . ')</td>
                <td class="text-right">' . CurrencyHelper::format($node['total_monthly_cost']) . '</td>
            </tr>';
        }
        $html .= '<tr style="font-weight:bold; background-color:#e2e8f0;">
                <td colspan="4">Total Monthly Hosting Cost</td>
                <td class="text-right">' . CurrencyHelper::format($costData['infrastructure']['total_monthly_infra_cost']) . '</td>
            </tr>
        </tbody>
    </table>

    <h2>3. Software Licensing & Maintenance</h2>
    <ul>
        <li><strong>Elastic Search License Status:</strong> ' . ($costData['raw_mssp_detail']->is_license_shared ? "Shared (" . $costData['raw_mssp_detail']->license_share_percentage . "% allocated)" : "Dedicated") . '</li>
        <li><strong>Monthly License Cost Equivalent:</strong> ' . CurrencyHelper::format($costData['sizing_summary']['monthly_license_usd']) . '</li>
        <li><strong>Monthly Operational Maintenance:</strong> ' . CurrencyHelper::format($costData['monthly_maintenance_cost']) . '</li>
    </ul>

    <h2>4. Profit Markup & Commercial Benefits</h2>
    <table>
        <thead>
            <tr>
                <th>Profit/Benefit Factor</th>
                <th class="text-center">Percentage (%)</th>
                <th class="text-right">Monthly Profit Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Assurance Benefit</td>
                <td class="text-center">' . $costData['assurance_benefit_percentage'] . '%</td>
                <td class="text-right">' . CurrencyHelper::format($costData['assurance_benefit_amount']) . '</td>
            </tr>
            <tr>
                <td>Marketing Benefit</td>
                <td class="text-center">' . $costData['marketing_benefit_percentage'] . '%</td>
                <td class="text-right">' . CurrencyHelper::format($costData['marketing_benefit_amount']) . '</td>
            </tr>
            <tr>
                <td>SOC Manager Profit</td>
                <td class="text-center">' . $costData['soc_manager_benefit_percentage'] . '%</td>
                <td class="text-right">' . CurrencyHelper::format($costData['soc_manager_benefit_amount']) . '</td>
            </tr>
            <tr>
                <td>CEO Profit</td>
                <td class="text-center">' . $costData['ceo_benefit_percentage'] . '%</td>
                <td class="text-right">' . CurrencyHelper::format($costData['ceo_benefit_amount']) . '</td>
            </tr>
            <tr>
                <td>Fixed Profit</td>
                <td class="text-center">' . $costData['fixed_profit_percentage'] . '%</td>
                <td class="text-right">' . CurrencyHelper::format($costData['fixed_profit_amount']) . '</td>
            </tr>
            <tr style="font-weight:bold; background-color:#e2e8f0;">
                <td>Total Profit Margin Markup</td>
                <td class="text-center">' . $costData['total_profit_percentage'] . '%</td>
                <td class="text-right">' . CurrencyHelper::format($costData['total_profit_amount']) . '</td>
            </tr>
        </tbody>
    </table>

    <div class="highlight-box">
        <h3 style="margin-top:0; color:#0f766e;">Commercial Proposal Summary</h3>
        <p style="margin:5px 0;"><strong>Estimated Base Cost (MRC):</strong> ' . CurrencyHelper::format($costData['total_monthly_service_cost']) . '</p>
        <p style="margin:5px 0;"><strong>Total Commercial Markup:</strong> +' . CurrencyHelper::format($costData['total_profit_amount']) . ' (+' . $costData['total_profit_percentage'] . '%)</p>
        <p style="margin:5px 0; font-size:13pt; color:#111;"><strong>Final Client Offered MRC (Price):</strong> <span style="font-weight:bold; color:#0d9488;">' . CurrencyHelper::format($costData['client_offered_price_mrc']) . ' / month</span></p>
        <p style="margin:10px 0 0 0; font-size:11pt; border-top:1px solid #ddd; padding-top:5px;"><strong>Upfront Setup Cost (One-Time):</strong> ' . CurrencyHelper::format($costData['onetime_setup_cost']) . '</p>
    </div>';

        if (!empty($costData['raw_mssp_detail']->ai_analysis)) {
            $html .= '<h2>6. AI Cost & Logic Analysis</h2>';
            $html .= '<div style="background-color: #fafafa; border-left: 4px solid #475569; padding: 15px; margin: 20px 0;">';
            $html .= \Illuminate\Support\Str::markdown($costData['raw_mssp_detail']->ai_analysis);
            $html .= '</div>';
        }

        $html .= '</body>
</html>';

        return new StreamedResponse(function () use ($html) {
            echo $html;
        }, 200, [
            'Content-Type' => 'application/msword',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Export the MSSP Cost Proposal to Excel (XLSX).
     */
    public function exportExcel(Client $client, Scenario $scenario)
    {
        $costData = $this->costingEngine->calculate($client, $scenario);
        $curr = CurrencyHelper::active();

        $spreadsheet = new Spreadsheet();
        
        // Set default font to Segoe UI and size to 10
        $spreadsheet->getDefaultStyle()->getFont()->setName('Segoe UI');
        $spreadsheet->getDefaultStyle()->getFont()->setSize(10);
        
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Commercial Proposal');

        // Page setup styling variables
        $titleStyle = [
            'font' => [
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
                'size' => 11,
                'bold' => true,
                'color' => ['rgb' => '0F766E'], // Teal 700
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        $tableHeaderStyle = [
            'font' => [
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
        ];

        $totalRowStyle = [
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F1F5F9'], // Slate 100
            ],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '94A3B8']],
            ],
        ];

        $currencyFormat = CurrencyHelper::excelFormatCode(true);

        // 1. Title
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', 'CLIENT OFFER COST PROPOSAL - MSSP & SOC SERVICES');
        $sheet->getStyle('A1:E1')->applyFromArray($titleStyle);
        $sheet->getRowDimension(1)->setRowHeight(40);

        // 2. Metadata Info
        $sheet->setCellValue('A3', 'Client:');
        $sheet->setCellValue('B3', $client->name);
        $sheet->setCellValue('A4', 'Scenario Template:');
        $sheet->setCellValue('B4', $scenario->name);
        $sheet->setCellValue('A5', 'Date Generated:');
        $sheet->setCellValue('B5', date('Y-m-d'));
        $sheet->setCellValue('A6', 'Active Currency:');
        $sheet->setCellValue('B6', $curr);
        $sheet->getStyle('A3:A6')->getFont()->setBold(true);

        // 3. Analyst Table Section
        $sheet->setCellValue('A8', '1. SOC Analyst Staffing Allocations');
        $sheet->getStyle('A8')->applyFromArray($sectionHeaderStyle);
        $sheet->getRowDimension(8)->setRowHeight(25);

        $sheet->setCellValue('A9', 'Operational Role');
        $sheet->setCellValue('B9', 'Dedication Allocation (%)');
        $sheet->setCellValue('C9', 'Staff Count');
        $sheet->setCellValue('D9', 'Monthly Base Salary (' . $curr . ')');
        $sheet->setCellValue('E9', 'Calculated Monthly Cost (' . $curr . ')');
        $sheet->getStyle('A9:E9')->applyFromArray($tableHeaderStyle);
        $sheet->getRowDimension(9)->setRowHeight(25);

        $row = 10;
        foreach ($costData['analysts']['roles'] as $role) {
            $sheet->setCellValue('A' . $row, $role['name']);
            $sheet->setCellValue('B' . $row, $role['allocation_percentage'] / 100);
            $sheet->setCellValue('C' . $row, $role['staff_count']);
            $sheet->setCellValue('D' . $row, $role['monthly_salary']);
            $sheet->setCellValue('E' . $row, '=D' . $row . '*B' . $row . '*C' . $row);
            
            // Formatting
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
            $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
            $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
            $row++;
        }

        // Total Staffing Row
        $sheet->setCellValue('A' . $row, 'Total Monthly Staffing Cost');
        $sheet->setCellValue('E' . $row, '=SUM(E10:E' . ($row - 1) . ')');
        $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($totalRowStyle);
        $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $totalStaffingCell = 'E' . $row;
        $row += 2;

        // 4. Infrastructure Table Section
        $sheet->setCellValue('A' . $row, '2. VM Hosting Infrastructure');
        $sheet->getStyle('A' . $row)->applyFromArray($sectionHeaderStyle);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;

        $sheet->setCellValue('A' . $row, 'Node Type / Role');
        $sheet->setCellValue('B' . $row, 'Instance Count');
        $sheet->setCellValue('C' . $row, 'RAM / Node (GB)');
        $sheet->setCellValue('D' . $row, 'Storage (GB) / Storage Type');
        $sheet->setCellValue('E' . $row, 'Total Monthly Hosting Cost (' . $curr . ')');
        $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($tableHeaderStyle);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $infraHeaderRow = $row;
        $row++;

        foreach ($costData['infrastructure']['nodes'] as $node) {
            $sheet->setCellValue('A' . $row, $node['name'] . ' (' . $node['role'] . ')');
            $sheet->setCellValue('B' . $row, $node['count']);
            $sheet->setCellValue('C' . $row, $node['ram_gb']);
            $sheet->setCellValue('D' . $row, $node['storage_gb'] . ' GB (' . $node['storage_type'] . ')');
            $sheet->setCellValue('E' . $row, $node['total_monthly_cost']);
            
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
            $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
            $row++;
        }

        // Total Infra Row
        $sheet->setCellValue('A' . $row, 'Total Monthly Hosting Cost');
        $sheet->setCellValue('E' . $row, '=SUM(E' . ($infraHeaderRow + 1) . ':E' . ($row - 1) . ')');
        $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($totalRowStyle);
        $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $totalInfraCell = 'E' . $row;
        $row += 2;

        // 5. Software Licensing & Maintenance
        $sheet->setCellValue('A' . $row, '3. Software Licensing & Maintenance');
        $sheet->getStyle('A' . $row)->applyFromArray($sectionHeaderStyle);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;

        $sheet->setCellValue('A' . $row, 'Line Item');
        $sheet->setCellValue('B' . $row, 'Status / Configuration');
        $sheet->setCellValue('E' . $row, 'Monthly Equivalent Cost (' . $curr . ')');
        $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($tableHeaderStyle);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $licensingHeaderRow = $row;
        $row++;

        $licenseStatus = $costData['raw_mssp_detail']->is_license_shared ? "Shared (" . $costData['raw_mssp_detail']->license_share_percentage . "%)" : "Dedicated";
        $sheet->setCellValue('A' . $row, 'Elastic Search License Equivalent');
        $sheet->setCellValue('B' . $row, $licenseStatus);
        $sheet->setCellValue('E' . $row, $costData['sizing_summary']['monthly_license_usd']);
        $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $licenseCell = 'E' . $row;
        $row++;

        $sheet->setCellValue('A' . $row, 'Monthly Operational Maintenance');
        $sheet->setCellValue('B' . $row, 'Fixed Recurrer');
        $sheet->setCellValue('E' . $row, $costData['monthly_maintenance_cost']);
        $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $maintenanceCell = 'E' . $row;
        $row += 2;

        // 6. Cost Base and Profit Markup Analysis
        $sheet->setCellValue('A' . $row, '4. Cost Base & Commercial Profit Markup');
        $sheet->getStyle('A' . $row)->applyFromArray($sectionHeaderStyle);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;

        $sheet->setCellValue('A' . $row, 'Profit / Benefit Margin Factor');
        $sheet->setCellValue('B' . $row, 'Markup Percentage (%)');
        $sheet->setCellValue('E' . $row, 'Monthly Markup Amount (' . $curr . ')');
        $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($tableHeaderStyle);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $markupHeaderRow = $row;
        $row++;

        // Base Cost Row
        $sheet->setCellValue('A' . $row, 'Base Estimated Cost (Cost Base)');
        $sheet->setCellValue('E' . $row, '=' . $totalStaffingCell . '+' . $totalInfraCell . '+' . $licenseCell . '+' . $maintenanceCell);
        $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
        $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $baseCostCell = 'E' . $row;
        $row++;

        // Markup Factors
        $markupFactors = [
            'Assurance Benefit' => $costData['assurance_benefit_percentage'],
            'Marketing Benefit Proposal' => $costData['marketing_benefit_percentage'],
            'SOC Manager Profit Proposal' => $costData['soc_manager_benefit_percentage'],
            'CEO Profit Proposal' => $costData['ceo_benefit_percentage'],
            'Fixed Profit' => $costData['fixed_profit_percentage'],
        ];

        $markupStartRow = $row;
        foreach ($markupFactors as $label => $pct) {
            $sheet->setCellValue('A' . $row, $label);
            $sheet->setCellValue('B' . $row, $pct / 100);
            $sheet->setCellValue('E' . $row, '=B' . $row . '*' . $baseCostCell);
            
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
            $row++;
        }
        $markupEndRow = $row - 1;

        // Total Profit Margin Row
        $sheet->setCellValue('A' . $row, 'Total Profit Margin Markup');
        $sheet->setCellValue('B' . $row, '=SUM(B' . $markupStartRow . ':B' . $markupEndRow . ')');
        $sheet->setCellValue('E' . $row, '=SUM(E' . $markupStartRow . ':E' . $markupEndRow . ')');
        $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($totalRowStyle);
        $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $totalMarkupCell = 'E' . $row;
        $row += 2;

        // 7. Final Offered Price Banner Block
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->setCellValue('A' . $row, 'FINAL CLIENT OFFERED MONTHLY RECURRING PRICE (MRC)');
        $sheet->setCellValue('E' . $row, '=' . $baseCostCell . '+' . $totalMarkupCell);
        
        $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true)->setSize(11)->setColor(new Color('0F766E')); // Teal 700 text color
        $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $row += 2;

        // 8. One-Time Setup Costs
        $sheet->setCellValue('A' . $row, '5. One-Time Setup & Onboarding Fees');
        $sheet->getStyle('A' . $row)->applyFromArray($sectionHeaderStyle);
        $row++;
        $sheet->setCellValue('A' . $row, 'Upfront Onboarding & Setup Cost');
        $sheet->setCellValue('E' . $row, $costData['onetime_setup_cost']);
        $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);

        // Autofit columns for readability
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = strtolower(str_replace(' ', '_', $client->name)) . "_mssp_cost_proposal.xlsx";

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Call local Ollama AI model to analyze the MSSP/SOC proposal logic and return the result.
     */
    public function askAi(Client $client, Scenario $scenario)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        try {
            $costData = $this->costingEngine->calculate($client, $scenario);
            $curr = CurrencyHelper::active();

            $analystsInfo = collect($costData['analysts']['roles'])->map(fn($role) => [
                'role_name' => $role['name'],
                'allocation_percentage' => $role['allocation_percentage'] . '%',
                'staff_count' => $role['staff_count'],
                'monthly_salary' => CurrencyHelper::format($role['monthly_salary']),
                'client_cost' => CurrencyHelper::format($role['client_cost']),
            ])->toArray();

            $nodesInfo = collect($costData['infrastructure']['nodes'])->map(fn($node) => [
                'node_type' => $node['name'],
                'role' => $node['role'],
                'count' => $node['count'],
                'ram_gb' => $node['ram_gb'] . ' GB',
                'storage_gb' => $node['storage_gb'] . ' GB',
                'storage_type' => $node['storage_type'],
                'monthly_cost' => CurrencyHelper::format($node['total_monthly_cost']),
            ])->toArray();

            $pricingBreakdown = [
                'client_name' => $client->name,
                'scenario_name' => $scenario->name,
                'active_currency' => $curr,
                'sizing_summary' => [
                    'daily_raw_ingestion' => $costData['sizing_summary']['daily_raw_gb'] . ' GB',
                    'total_cluster_ram' => $costData['sizing_summary']['total_ram_gb'] . ' GB',
                    'monthly_license_cost' => CurrencyHelper::format($costData['sizing_summary']['monthly_license_usd']),
                    'license_sharing' => $costData['raw_mssp_detail']->is_license_shared 
                        ? "Shared (" . $costData['raw_mssp_detail']->license_share_percentage . "% allocated to client)" 
                        : "Dedicated (100% cost allocated to client)",
                ],
                'analyst_staffing' => [
                    'roles' => $analystsInfo,
                    'total_monthly_analyst_cost' => CurrencyHelper::format($costData['analysts']['total_monthly_analyst_cost']),
                ],
                'vm_hosting_infrastructure' => [
                    'nodes' => $nodesInfo,
                    'total_monthly_hosting_cost' => CurrencyHelper::format($costData['infrastructure']['total_monthly_infra_cost']),
                ],
                'software_and_maintenance' => [
                    'monthly_maintenance_cost' => CurrencyHelper::format($costData['monthly_maintenance_cost']),
                    'one_time_setup_cost' => CurrencyHelper::format($costData['onetime_setup_cost']),
                ],
                'profit_markup_factors' => [
                    'assurance_benefit' => $costData['assurance_benefit_percentage'] . '% (' . CurrencyHelper::format($costData['assurance_benefit_amount']) . ')',
                    'marketing_benefit' => $costData['marketing_benefit_percentage'] . '% (' . CurrencyHelper::format($costData['marketing_benefit_amount']) . ')',
                    'soc_manager_profit' => $costData['soc_manager_benefit_percentage'] . '% (' . CurrencyHelper::format($costData['soc_manager_benefit_amount']) . ')',
                    'ceo_profit' => $costData['ceo_benefit_percentage'] . '% (' . CurrencyHelper::format($costData['ceo_benefit_amount']) . ')',
                    'fixed_profit' => $costData['fixed_profit_percentage'] . '% (' . CurrencyHelper::format($costData['fixed_profit_amount']) . ')',
                    'total_profit_percentage' => $costData['total_profit_percentage'] . '%',
                    'total_profit_amount' => CurrencyHelper::format($costData['total_profit_amount']),
                ],
                'commercial_summary' => [
                    'base_estimated_mrc' => CurrencyHelper::format($costData['total_monthly_service_cost']),
                    'total_commercial_markup' => '+' . CurrencyHelper::format($costData['total_profit_amount']),
                    'final_client_offered_mrc' => CurrencyHelper::format($costData['client_offered_price_mrc']),
                ],
            ];

            $promptContent = "Please analyze the following Cybersecurity MSSP and SOC proposal costing details:\n\n" . 
                      json_encode($pricingBreakdown, JSON_PRETTY_PRINT) . "\n\n" . 
                      "Evaluate the sizing/topology vs ingestion, evaluate the staffing allocations, and critique the pricing margins and final offered price. Provide recommendations.";

            // Run agent using Laravel AI SDK
            $model = env('OLLAMA_MODEL', 'gemma4:e2b');
            $response = (new OfferAnalyst)->prompt($promptContent, provider: Lab::Ollama, model: $model, timeout: 120);

            $analysisText = $response->text;

            // Build a debug footer so the user can verify the Ollama backend was used
            $debugInfo = "\n\n---\n> 🤖 **AI Debug Info** | Provider: `Ollama` | Model: `{$model}` | Generated: `" . now()->format('Y-m-d H:i:s') . "` | Prompt tokens (est.): ~" . (int)(strlen($promptContent) / 4) . " | Response tokens (est.): ~" . (int)(strlen($analysisText) / 4);
            $fullAnalysis = $analysisText . $debugInfo;

            // Save the analysis text (with debug footer) to the database
            $msspDetail = $costData['raw_mssp_detail'];
            $msspDetail->update([
                'ai_analysis' => $fullAnalysis
            ]);

            return response()->json([
                'success' => true,
                'analysis' => $fullAnalysis,
                'html' => \Illuminate\Support\Str::markdown($fullAnalysis),
                'debug' => [
                    'provider' => 'Ollama',
                    'model'    => $model,
                    'prompt_length' => strlen($promptContent),
                    'response_length' => strlen($analysisText),
                    'generated_at' => now()->toIso8601String(),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Laravel AI SDK generation error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('messages.ai_error') . ' Error details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test connectivity to the local Ollama server and return diagnostic info.
     */
    public function ollamaPing()
    {
        $ollamaUrl = config('ai.providers.ollama.url', 'http://localhost:11434');
        $model     = env('OLLAMA_MODEL', 'gemma4:e2b');

        $diagnostics = [
            'ollama_url'  => $ollamaUrl,
            'model_target' => $model,
            'timestamp'   => now()->toIso8601String(),
        ];

        // Try hitting Ollama /api/tags to list available models
        try {
            $ch = curl_init($ollamaUrl . '/api/tags');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            $body   = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error  = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $diagnostics['status']  = 'error';
                $diagnostics['message'] = "cURL error: {$error}";
                $diagnostics['models']  = [];
            } elseif ($status === 200) {
                $data   = json_decode($body, true);
                $models = collect($data['models'] ?? [])->pluck('name')->toArray();
                $modelAvailable = in_array($model, $models) || collect($models)->contains(fn($m) => str_starts_with($m, explode(':', $model)[0]));

                $diagnostics['status']          = 'ok';
                $diagnostics['http_code']        = $status;
                $diagnostics['message']          = 'Ollama server is reachable ✅';
                $diagnostics['available_models'] = $models;
                $diagnostics['target_model_found'] = $modelAvailable;
                $diagnostics['model_status']     = $modelAvailable
                    ? "✅ Model `{$model}` is available"
                    : "⚠️ Model `{$model}` NOT found in Ollama. Available: " . implode(', ', $models);
            } else {
                $diagnostics['status']   = 'warning';
                $diagnostics['http_code'] = $status;
                $diagnostics['message']  = "Ollama responded with HTTP {$status}";
            }
        } catch (\Throwable $e) {
            $diagnostics['status']  = 'error';
            $diagnostics['message'] = 'Exception: ' . $e->getMessage();
        }

        return response()->json($diagnostics);
    }
}
