<?php

namespace App\Http\Controllers;

use App\Ai\Adapters\LaravelToolAdapter;
use App\Ai\Agents\ElasticCostAssistant;
use App\Ai\Analytics\LaravelAnalyticsCollector;
use App\Models\Client;
use App\Models\ClientScenarioAnalystAllocation;
use App\Models\GlobalSetting;
use App\Models\Scenario;
use App\Services\AgentProfitSimulatorService;
use App\Services\AiConfigHelper;
use App\Services\CurrencyHelper;
use App\Services\MsspCostingEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Phpkaiharness\Core\AgentLoop;
use Phpkaiharness\Core\Registry\ToolRegistry;
use Phpkaiharness\Llm\LaravelAiClient;
use Phpkaiharness\Session\SessionManager;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'cloud_datacenter' => 'nullable|string|in:Dataxion,TT',
            'one_time_setup_cost' => 'required|numeric|min:0',
            'monthly_maintenance_cost' => 'required|numeric|min:0',
            'ram_monthly_cost_per_gb' => 'required|numeric|min:0',
            'nvme_ssd_monthly_cost_per_gb' => 'required|numeric|min:0',
            'sata_ssd_monthly_cost_per_gb' => 'required|numeric|min:0',
            'local_ssd_monthly_cost_per_gb' => 'required|numeric|min:0',
            'elastic_cloud_monthly_cost_per_gb_ram' => 'required|numeric|min:0',
            'elastic_cloud_subscription_tier' => 'required|string|in:standard,gold,platinum,enterprise',
            'siem_agent_monthly_cost_per_device' => 'required|numeric|min:0',
            'mdr_agent_monthly_cost_per_device' => 'required|numeric|min:0',
            'edr_agent_monthly_cost_per_device' => 'required|numeric|min:0',
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
            'cloud_datacenter' => $request->input('cloud_datacenter') ?: null,
            'one_time_setup_cost' => CurrencyHelper::convertBack((float) $request->input('one_time_setup_cost')),
            'monthly_maintenance_cost' => CurrencyHelper::convertBack((float) $request->input('monthly_maintenance_cost')),
            'ram_monthly_cost_per_gb' => CurrencyHelper::convertBack((float) $request->input('ram_monthly_cost_per_gb')),
            'nvme_ssd_monthly_cost_per_gb' => CurrencyHelper::convertBack((float) $request->input('nvme_ssd_monthly_cost_per_gb')),
            'sata_ssd_monthly_cost_per_gb' => CurrencyHelper::convertBack((float) $request->input('sata_ssd_monthly_cost_per_gb')),
            'local_ssd_monthly_cost_per_gb' => CurrencyHelper::convertBack((float) $request->input('local_ssd_monthly_cost_per_gb')),
            'elastic_cloud_monthly_cost_per_gb_ram' => CurrencyHelper::convertBack((float) $request->input('elastic_cloud_monthly_cost_per_gb_ram')),
            'elastic_cloud_subscription_tier' => $request->input('elastic_cloud_subscription_tier', 'platinum'),
            'siem_agent_monthly_cost_per_device' => CurrencyHelper::convertBack((float) $request->input('siem_agent_monthly_cost_per_device')),
            'mdr_agent_monthly_cost_per_device' => CurrencyHelper::convertBack((float) $request->input('mdr_agent_monthly_cost_per_device')),
            'edr_agent_monthly_cost_per_device' => CurrencyHelper::convertBack((float) $request->input('edr_agent_monthly_cost_per_device')),
            'is_license_shared' => $request->has('is_license_shared'),
            'license_share_percentage' => $request->has('is_license_shared') ? (float) $request->input('license_share_percentage') : 100.00,
            'assurance_benefit_percentage' => (float) $request->input('assurance_benefit_percentage', 0.00),
            'marketing_benefit_percentage' => (float) $request->input('marketing_benefit_percentage', 0.00),
            'soc_manager_benefit_percentage' => (float) $request->input('soc_manager_benefit_percentage', 0.00),
            'ceo_benefit_percentage' => (float) $request->input('ceo_benefit_percentage', 0.00),
            'fixed_profit_percentage' => (float) $request->input('fixed_profit_percentage', 0.00),
        ]);

        if ($request->has('reset_defaults')) {
            $defaults = app(AgentProfitSimulatorService::class)->getScenarioDefaults($client, $msspDetail);
            $msspDetail->update([
                'agent_profit_simulation_settings' => $defaults,
            ]);
        } elseif ($request->has('agent_profit_simulation')) {
            $simulationInput = $request->input('agent_profit_simulation');
            if (is_array($simulationInput)) {
                $defaults = app(AgentProfitSimulatorService::class)->getScenarioDefaults($client, $msspDetail);
                $existing = $msspDetail->agent_profit_simulation_settings ?? [];
                $customPacks = $simulationInput['custom_packs'] ?? ($existing['custom_packs'] ?? ($defaults['custom_packs'] ?? []));

                // Clean up and convert monetary values back to USD
                if (is_array($customPacks)) {
                    foreach ($customPacks as &$pack) {
                        $pack['partner_price'] = CurrencyHelper::convertBack((float) ($pack['partner_price'] ?? 350));
                        $pack['client_price'] = CurrencyHelper::convertBack((float) ($pack['client_price'] ?? 450));
                        if (! empty($pack['extra_services']) && is_array($pack['extra_services'])) {
                            foreach ($pack['extra_services'] as &$svc) {
                                $svc['price'] = CurrencyHelper::convertBack((float) ($svc['price'] ?? 0));
                            }
                        }
                    }
                }

                $msspDetail->update([
                    'agent_profit_simulation_settings' => [
                        'mode' => $simulationInput['mode'] ?? ($existing['mode'] ?? 'agent'),
                        'hosting_mode' => $simulationInput['hosting_mode'] ?? ($existing['hosting_mode'] ?? 'none'),

                        'edr_partner_price' => isset($simulationInput['edr_partner_price']) ? CurrencyHelper::convertBack((float) $simulationInput['edr_partner_price']) : ($existing['edr_partner_price'] ?? $defaults['edr_partner_price']),
                        'edr_client_price' => isset($simulationInput['edr_client_price']) ? CurrencyHelper::convertBack((float) $simulationInput['edr_client_price']) : ($existing['edr_client_price'] ?? $defaults['edr_client_price']),
                        'edr_purchased_limit' => isset($simulationInput['edr_purchased_limit']) ? (int) $simulationInput['edr_purchased_limit'] : ($existing['edr_purchased_limit'] ?? $defaults['edr_purchased_limit']),
                        'edr_monthly_growth' => isset($simulationInput['edr_monthly_growth']) ? (int) $simulationInput['edr_monthly_growth'] : ($existing['edr_monthly_growth'] ?? $defaults['edr_monthly_growth']),

                        'mdr_partner_price' => isset($simulationInput['mdr_partner_price']) ? CurrencyHelper::convertBack((float) $simulationInput['mdr_partner_price']) : ($existing['mdr_partner_price'] ?? $defaults['mdr_partner_price']),
                        'mdr_client_price' => isset($simulationInput['mdr_client_price']) ? CurrencyHelper::convertBack((float) $simulationInput['mdr_client_price']) : ($existing['mdr_client_price'] ?? $defaults['mdr_client_price']),
                        'mdr_purchased_limit' => isset($simulationInput['mdr_purchased_limit']) ? (int) $simulationInput['mdr_purchased_limit'] : ($existing['mdr_purchased_limit'] ?? $defaults['mdr_purchased_limit']),
                        'mdr_monthly_growth' => isset($simulationInput['mdr_monthly_growth']) ? (int) $simulationInput['mdr_monthly_growth'] : ($existing['mdr_monthly_growth'] ?? $defaults['mdr_monthly_growth']),

                        'siem_partner_price' => isset($simulationInput['siem_partner_price']) ? CurrencyHelper::convertBack((float) $simulationInput['siem_partner_price']) : ($existing['siem_partner_price'] ?? $defaults['siem_partner_price']),
                        'siem_client_price' => isset($simulationInput['siem_client_price']) ? CurrencyHelper::convertBack((float) $simulationInput['siem_client_price']) : ($existing['siem_client_price'] ?? $defaults['siem_client_price']),
                        'siem_purchased_limit' => isset($simulationInput['siem_purchased_limit']) ? (int) $simulationInput['siem_purchased_limit'] : ($existing['siem_purchased_limit'] ?? $defaults['siem_purchased_limit']),
                        'siem_monthly_growth' => isset($simulationInput['siem_monthly_growth']) ? (int) $simulationInput['siem_monthly_growth'] : ($existing['siem_monthly_growth'] ?? $defaults['siem_monthly_growth']),

                        'custom_packs' => $customPacks,
                    ],
                ]);
            }
        }

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

        $activeTab = $request->input('tab');
        $routeParams = [$client->id, $scenario->id];
        if ($activeTab) {
            $routeParams['tab'] = $activeTab;
        }

        return redirect()
            ->route('mssp.show', $routeParams)
            ->with('success', 'MSSP Costing parameters and simulation updated successfully!');
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
        $markdown .= '*   **Date**: '.date('Y-m-d')."\n";
        if ($costData['raw_mssp_detail']->cloud_datacenter) {
            $markdown .= "*   **Cloud Datacenter**: **{$costData['raw_mssp_detail']->cloud_datacenter} (XpressAzure Partner)**\n";
        }
        $markdown .= "\n---\n\n";

        $markdown .= "## 1. SOC Analyst Staffing Allocations\n\n";
        $markdown .= "| Operational Role | Dedication (%) | Staff Count | Monthly Salary | Calculated Client Cost |\n";
        $markdown .= "| :--- | :---: | :---: | :---: | :---: |\n";
        foreach ($costData['analysts']['roles'] as $role) {
            $markdown .= "| **{$role['name']}** | {$role['allocation_percentage']}% | {$role['staff_count']} | ".CurrencyHelper::format($role['monthly_salary']).' | '.CurrencyHelper::format($role['client_cost'])." |\n";
        }
        $markdown .= '| **Total Staffing Cost** | | | | **'.CurrencyHelper::format($costData['analysts']['total_monthly_analyst_cost'])."** |\n\n";

        $markdown .= "\n---\n\n";

        $markdown .= "## 2. Option A: On-Premise Deployment Offer\n\n";
        $markdown .= "### VM Hosting Infrastructure\n\n";
        $markdown .= "| Node Type / Role | Instance Count | RAM / Node | Storage / Node | Total Monthly Cost |\n";
        $markdown .= "| :--- | :---: | :---: | :---: | :---: |\n";
        foreach ($costData['infrastructure']['nodes'] as $node) {
            $storage = $node['storage_gb'] >= 1000 ? ($node['storage_gb'] / 1000).' TB' : $node['storage_gb'].' GB';
            $specText = '';
            if ($node['cloud_datacenter']) {
                $specText = "<br><small>VM: <code>{$node['matched_vm_name']}</code> | Disk: <code>{$node['matched_disk_desc']}</code></small>";
            }
            $markdown .= "| **{$node['name']}** ({$node['role']}){$specText} | x{$node['count']} | {$node['ram_gb']} GB | {$storage} ({$node['storage_type']}) | ".CurrencyHelper::format($node['total_monthly_cost'])." |\n";
        }
        $markdown .= '| **Total Hosting Cost** | | | | **'.CurrencyHelper::format($costData['infrastructure']['total_monthly_infra_cost'])."** |\n\n";

        $markdown .= "### Software Licensing & Maintenance\n\n";
        $licenseStatus = $costData['raw_mssp_detail']->is_license_shared ? 'Shared ('.$costData['raw_mssp_detail']->license_share_percentage.'% allocated)' : 'Dedicated';
        $markdown .= "*   **Elastic Search License Status**: {$licenseStatus}\n";
        $markdown .= '*   **Monthly License Cost Equivalent**: **'.CurrencyHelper::format($costData['sizing_summary']['monthly_license_usd'])."**\n";
        $markdown .= '*   **Monthly Operational Maintenance**: **'.CurrencyHelper::format($costData['monthly_maintenance_cost'])."**\n\n";

        $markdown .= "### Profit Markup & Commercial Benefits (On-Premise)\n\n";
        $markdown .= "| Profit/Benefit Factor | Percentage (%) | Monthly Profit Amount |\n";
        $markdown .= "| :--- | :---: | :---: |\n";
        $markdown .= "| Assurance Benefit | {$costData['assurance_benefit_percentage']}% | ".CurrencyHelper::format($costData['assurance_benefit_amount'])." |\n";
        $markdown .= "| Marketing Benefit | {$costData['marketing_benefit_percentage']}% | ".CurrencyHelper::format($costData['marketing_benefit_amount'])." |\n";
        $markdown .= "| SOC Manager Profit | {$costData['soc_manager_benefit_percentage']}% | ".CurrencyHelper::format($costData['soc_manager_benefit_amount'])." |\n";
        $markdown .= "| CEO Profit | {$costData['ceo_benefit_percentage']}% | ".CurrencyHelper::format($costData['ceo_benefit_amount'])." |\n";
        $markdown .= "| Fixed Profit | {$costData['fixed_profit_percentage']}% | ".CurrencyHelper::format($costData['fixed_profit_amount'])." |\n";
        $markdown .= "| **Total Profit Margin Markup** | **{$costData['total_profit_percentage']}%** | **".CurrencyHelper::format($costData['total_profit_amount'])."** |\n\n";

        $markdown .= "### Commercial Proposal Summary (On-Premise)\n\n";
        $markdown .= '*   **Estimated Base Cost (MRC)**: **'.CurrencyHelper::format($costData['total_monthly_service_cost'])."**\n";
        $markdown .= '*   **Total Commercial Markup**: **+'.CurrencyHelper::format($costData['total_profit_amount'])."** (+{$costData['total_profit_percentage']}%)\n";
        $markdown .= '*   **Final Client Offered MRC (Price)**: **'.CurrencyHelper::format($costData['client_offered_price_mrc'])."**\n";
        $markdown .= '*   **Upfront Setup Cost (One-Time)**: **'.CurrencyHelper::format($costData['onetime_setup_cost'])."**\n\n";

        $markdown .= "\n---\n\n";

        $cloudData = $costData['cloud_option'];
        $markdown .= "## 3. Option B: Elastic Cloud Deployment Offer\n\n";
        $markdown .= "### Elastic Cloud Node Sizing & Reference Pricing (Azure East US 2)\n\n";
        $markdown .= '*   **Subscription Tier**: **'.ucfirst($cloudData['elastic_cloud_subscription_tier'])."**\n\n";
        $markdown .= "| Node Sizing Item | Operational Role | Count | RAM / Node | Matched Instance SKU | Hourly Rate | Total Monthly Cost |\n";
        $markdown .= "| :--- | :--- | :---: | :---: | :--- | :---: | :---: |\n";
        foreach ($cloudData['matched_nodes'] as $node) {
            $markdown .= "| **{$node['name']}** | {$node['role']} | x{$node['count']} | {$node['ram_gb']} GB | `{$node['sku']}` | $".number_format($node['hourly_rate'], 4).' /GB-hr | '.CurrencyHelper::format($node['monthly_cost'])." |\n";
        }
        $markdown .= '| **Total Subscription Cost (Reference)** | | | | | | **'.CurrencyHelper::format($cloudData['elastic_cloud_subscription_cost'])."** |\n\n";
        $markdown .= "*(Note: This cost is for estimation reference only and is NOT billed separately. All cloud subscription, hosting, staffing, and benefits are fully covered in the agent rates below.)*\n\n";

        $markdown .= "### MDR Agent Package Coverage\n\n";
        $markdown .= "| Agent Type | Mapped Devices | Monthly Unit Price | Total Monthly Cost |\n";
        $markdown .= "| :--- | :---: | :---: | :---: |\n";
        $markdown .= "| **Unified Security Monitoring & Correlation (SIEM)** | {$cloudData['total_siem_count']} | ".CurrencyHelper::format($cloudData['siem_agent_monthly_cost_per_device']).' | '.CurrencyHelper::format($cloudData['siem_monthly_cost'])." |\n";
        $markdown .= "| **Expert-Led 24/7 Monitoring & Response (MDR)** | {$cloudData['total_mdr_count']} | ".CurrencyHelper::format($cloudData['mdr_agent_monthly_cost_per_device']).' | '.CurrencyHelper::format($cloudData['mdr_monthly_cost'])." |\n";
        $markdown .= "| **Advanced Endpoint Protection (EDR)** | {$cloudData['total_edr_count']} | ".CurrencyHelper::format($cloudData['edr_agent_monthly_cost_per_device']).' | '.CurrencyHelper::format($cloudData['edr_monthly_cost'])." |\n";
        $markdown .= '| **Total MDR Agent Package Cost** | | | **'.CurrencyHelper::format($cloudData['total_agents_monthly_cost'])."** |\n\n";

        $markdown .= "### Commercial Proposal Summary (Elastic Cloud)\n\n";
        $markdown .= '*   **Monthly Agent Package Cost**: **'.CurrencyHelper::format($cloudData['total_agents_monthly_cost'])."**\n";
        $markdown .= "*   **Commercial Markup**: **$0.00** *(Agent rates include hosting and profits)*\n";
        $markdown .= '*   **Final Client Offered MRC (Price)**: **'.CurrencyHelper::format($cloudData['client_offered_price_mrc'])."**\n";
        $markdown .= '*   **Upfront Setup Cost (One-Time)**: **'.CurrencyHelper::format($costData['onetime_setup_cost'])."**\n\n";

        if (! empty($costData['raw_mssp_detail']->ai_analysis)) {
            $markdown .= "---\n\n";
            $markdown .= "## 4. AI Cost & Logic Analysis\n\n";
            $markdown .= $costData['raw_mssp_detail']->ai_analysis."\n\n";
        }

        $fileName = strtolower(str_replace(' ', '_', $client->name)).'_mssp_cost_proposal.md';

        return new StreamedResponse(function () use ($markdown) {
            echo $markdown;
        }, 200, [
            'Content-Type' => 'text/markdown',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Export the MSSP Cost Proposal to Word (.doc / HTML template).
     */
    public function exportWord(Client $client, Scenario $scenario)
    {
        $costData = $this->costingEngine->calculate($client, $scenario);
        $cloudData = $costData['cloud_option'];
        $curr = CurrencyHelper::active();
        $isRtl = app()->getLocale() === 'ar';
        $dir = $isRtl ? 'rtl' : 'ltr';

        $fileName = strtolower(str_replace(' ', '_', $client->name)).'_mssp_cost_proposal.doc';

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
        .highlight-box-cloud {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body dir="'.$dir.'">
    <div class="title-banner">
        <h1>MSSP & SOC Cost Proposal</h1>
        <p>Client: '.htmlspecialchars($client->name).' | Scenario: '.htmlspecialchars($scenario->name).' | Date: '.date('Y-m-d').' ('.$curr.')</p>
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
                <td><strong>'.htmlspecialchars($role['name']).'</strong></td>
                <td class="text-center">'.$role['allocation_percentage'].'%</td>
                <td class="text-center">'.$role['staff_count'].'</td>
                <td class="text-right">'.CurrencyHelper::format($role['monthly_salary']).'</td>
                <td class="text-right">'.CurrencyHelper::format($role['client_cost']).'</td>
            </tr>';
        }
        $html .= '<tr style="font-weight:bold; background-color:#e2e8f0;">
                <td colspan="4">Total Monthly Staffing Cost</td>
                <td class="text-right">'.CurrencyHelper::format($costData['analysts']['total_monthly_analyst_cost']).'</td>
            </tr>
        </tbody>
    </table>

    <h2>2. Option A: On-Premise Deployment Offer</h2>

    <h3>VM Hosting Infrastructure</h3>
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
            $storage = $node['storage_gb'] >= 1000 ? ($node['storage_gb'] / 1000).' TB' : $node['storage_gb'].' GB';
            $specText = '';
            if ($node['cloud_datacenter']) {
                $specText = "<br><span style='font-size:9pt; color:#64748b;'>VM: <strong>".htmlspecialchars($node['matched_vm_name']).'</strong> | Disk: <strong>'.htmlspecialchars($node['matched_disk_desc']).'</strong></span>';
            }
            $html .= '<tr>
                <td><strong>'.htmlspecialchars($node['name']).'</strong> ('.$node['role'].')'.$specText.'</td>
                <td class="text-center">x'.$node['count'].'</td>
                <td class="text-center">'.$node['ram_gb'].' GB</td>
                <td class="text-center">'.$storage.' ('.$node['storage_type'].')</td>
                <td class="text-right">'.CurrencyHelper::format($node['total_monthly_cost']).'</td>
            </tr>';
        }
        $html .= '<tr style="font-weight:bold; background-color:#e2e8f0;">
                <td colspan="4">Total Monthly Hosting Cost</td>
                <td class="text-right">'.CurrencyHelper::format($costData['infrastructure']['total_monthly_infra_cost']).'</td>
            </tr>
        </tbody>
    </table>

    <h3>Software Licensing & Maintenance</h3>
    <ul>
        <li><strong>Elastic Search License Status:</strong> '.($costData['raw_mssp_detail']->is_license_shared ? 'Shared ('.$costData['raw_mssp_detail']->license_share_percentage.'% allocated)' : 'Dedicated').'</li>
        <li><strong>Monthly License Cost Equivalent:</strong> '.CurrencyHelper::format($costData['sizing_summary']['monthly_license_usd']).'</li>
        <li><strong>Monthly Operational Maintenance:</strong> '.CurrencyHelper::format($costData['monthly_maintenance_cost']).'</li>
    </ul>

    <h3>Profit Markup & Commercial Benefits</h3>
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
                <td class="text-center">'.$costData['assurance_benefit_percentage'].'%</td>
                <td class="text-right">'.CurrencyHelper::format($costData['assurance_benefit_amount']).'</td>
            </tr>
            <tr>
                <td>Marketing Benefit</td>
                <td class="text-center">'.$costData['marketing_benefit_percentage'].'%</td>
                <td class="text-right">'.CurrencyHelper::format($costData['marketing_benefit_amount']).'</td>
            </tr>
            <tr>
                <td>SOC Manager Profit</td>
                <td class="text-center">'.$costData['soc_manager_benefit_percentage'].'%</td>
                <td class="text-right">'.CurrencyHelper::format($costData['soc_manager_benefit_amount']).'</td>
            </tr>
            <tr>
                <td>CEO Profit</td>
                <td class="text-center">'.$costData['ceo_benefit_percentage'].'%</td>
                <td class="text-right">'.CurrencyHelper::format($costData['ceo_benefit_amount']).'</td>
            </tr>
            <tr>
                <td>Fixed Profit</td>
                <td class="text-center">'.$costData['fixed_profit_percentage'].'%</td>
                <td class="text-right">'.CurrencyHelper::format($costData['fixed_profit_amount']).'</td>
            </tr>
            <tr style="font-weight:bold; background-color:#e2e8f0;">
                <td>Total Profit Margin Markup</td>
                <td class="text-center">'.$costData['total_profit_percentage'].'%</td>
                <td class="text-right">'.CurrencyHelper::format($costData['total_profit_amount']).'</td>
            </tr>
        </tbody>
    </table>

    <div class="highlight-box">
        <h3 style="margin-top:0; color:#0f766e;">Commercial Proposal Summary (On-Premise)</h3>
        <p style="margin:5px 0;"><strong>Estimated Base Cost (MRC):</strong> '.CurrencyHelper::format($costData['total_monthly_service_cost']).'</p>
        <p style="margin:5px 0;"><strong>Total Commercial Markup:</strong> +'.CurrencyHelper::format($costData['total_profit_amount']).' (+'.$costData['total_profit_percentage'].'%)</p>
        <p style="margin:5px 0; font-size:12pt; color:#111;"><strong>Final Client Offered MRC (Price):</strong> <span style="font-weight:bold; color:#0d9488;">'.CurrencyHelper::format($costData['client_offered_price_mrc']).' / month</span></p>
        <p style="margin:10px 0 0 0; font-size:10pt; border-top:1px solid #ddd; padding-top:5px;"><strong>Upfront Setup Cost (One-Time):</strong> '.CurrencyHelper::format($costData['onetime_setup_cost']).'</p>
    </div>

    <h2>3. Option B: Elastic Cloud Deployment Offer</h2>

    <h3>Elastic Cloud Node Sizing & Reference Pricing (Azure East US 2)</h3>
    <p><strong>Subscription Tier:</strong> '.ucfirst($cloudData['elastic_cloud_subscription_tier']).'</p>
    <table>
        <thead>
            <tr>
                <th>Node Sizing Item</th>
                <th>Role</th>
                <th class="text-center">Count</th>
                <th class="text-center">RAM / Node</th>
                <th>Matched Instance SKU</th>
                <th class="text-right">Hourly Rate</th>
                <th class="text-right">Total Monthly Cost</th>
            </tr>
        </thead>
        <tbody>';
        foreach ($cloudData['matched_nodes'] as $node) {
            $html .= '<tr>
                <td><strong>'.htmlspecialchars($node['name']).'</strong></td>
                <td>'.htmlspecialchars($node['role']).'</td>
                <td class="text-center">x'.$node['count'].'</td>
                <td class="text-center">'.$node['ram_gb'].' GB</td>
                <td><code>'.htmlspecialchars($node['sku']).'</code></td>
                <td class="text-right">$'.number_format($node['hourly_rate'], 4).' /GB-hr</td>
                <td class="text-right">'.CurrencyHelper::format($node['monthly_cost']).'</td>
            </tr>';
        }
        $html .= '<tr style="font-weight:bold; background-color:#e2e8f0;">
                <td colspan="6">Total Monthly Elastic Cloud Subscription Cost (Reference)</td>
                <td class="text-right">'.CurrencyHelper::format($cloudData['elastic_cloud_subscription_cost']).'</td>
            </tr>
        </tbody>
    </table>
    <p style="font-size:9pt; color:#64748b;"><em>(Note: This cost is for estimation reference only and is NOT billed separately. All cloud subscription, hosting, staffing, and benefits are fully covered in the agent rates below.)</em></p>

    <h3>MDR Agent Package Coverage</h3>
    <table>
        <thead>
            <tr>
                <th>Agent Type</th>
                <th class="text-center">Mapped Devices</th>
                <th class="text-right">Monthly Unit Price</th>
                <th class="text-right">Total Monthly Cost</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Unified Security Monitoring & Correlation (SIEM)</strong></td>
                <td class="text-center">'.$cloudData['total_siem_count'].'</td>
                <td class="text-right">'.CurrencyHelper::format($cloudData['siem_agent_monthly_cost_per_device']).'</td>
                <td class="text-right">'.CurrencyHelper::format($cloudData['siem_monthly_cost']).'</td>
            </tr>
            <tr>
                <td><strong>Expert-Led 24/7 Monitoring & Response (MDR)</strong></td>
                <td class="text-center">'.$cloudData['total_mdr_count'].'</td>
                <td class="text-right">'.CurrencyHelper::format($cloudData['mdr_agent_monthly_cost_per_device']).'</td>
                <td class="text-right">'.CurrencyHelper::format($cloudData['mdr_monthly_cost']).'</td>
            </tr>
            <tr>
                <td><strong>Advanced Endpoint Protection (EDR)</strong></td>
                <td class="text-center">'.$cloudData['total_edr_count'].'</td>
                <td class="text-right">'.CurrencyHelper::format($cloudData['edr_agent_monthly_cost_per_device']).'</td>
                <td class="text-right">'.CurrencyHelper::format($cloudData['edr_monthly_cost']).'</td>
            </tr>
            <tr style="font-weight:bold; background-color:#e2e8f0;">
                <td colspan="3">Total MDR Agent Package Cost</td>
                <td class="text-right">'.CurrencyHelper::format($cloudData['total_agents_monthly_cost']).'</td>
            </tr>
        </tbody>
    </table>

    <div class="highlight-box-cloud">
        <h3 style="margin-top:0; color:#1d4ed8;">Commercial Proposal Summary (Elastic Cloud)</h3>
        <p style="margin:5px 0;"><strong>Monthly Agent Package Cost:</strong> '.CurrencyHelper::format($cloudData['total_agents_monthly_cost']).'</p>
        <p style="margin:5px 0;"><strong>Commercial Markup:</strong> $0.00 (Agent rates include everything)</p>
        <p style="margin:5px 0; font-size:12pt; color:#111;"><strong>Final Client Offered MRC (Price):</strong> <span style="font-weight:bold; color:#2563eb;">'.CurrencyHelper::format($cloudData['client_offered_price_mrc']).' / month</span></p>
        <p style="margin:10px 0 0 0; font-size:10pt; border-top:1px solid #ddd; padding-top:5px;"><strong>Upfront Setup Cost (One-Time):</strong> '.CurrencyHelper::format($costData['onetime_setup_cost']).'</p>
    </div>';

        if (! empty($costData['raw_mssp_detail']->ai_analysis)) {
            $html .= '<h2>4. AI Cost & Logic Analysis</h2>';
            $html .= '<div style="background-color: #fafafa; border-left: 4px solid #475569; padding: 15px; margin: 20px 0;">';
            $html .= Str::markdown($costData['raw_mssp_detail']->ai_analysis);
            $html .= '</div>';
        }

        $html .= '</body>
</html>';

        return new StreamedResponse(function () use ($html) {
            echo $html;
        }, 200, [
            'Content-Type' => 'application/msword',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Export the MSSP Cost Proposal to Excel (XLSX).
     */
    public function exportExcel(Client $client, Scenario $scenario)
    {
        $costData = $this->costingEngine->calculate($client, $scenario);
        $curr = CurrencyHelper::active();

        $spreadsheet = new Spreadsheet;

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

        $dcValue = $costData['raw_mssp_detail']->cloud_datacenter ?: 'Generic Rates';
        $sheet->setCellValue('A7', 'Cloud Datacenter:');
        $sheet->setCellValue('B7', $dcValue);

        $sheet->getStyle('A3:A7')->getFont()->setBold(true);

        // 3. Analyst Table Section
        $sheet->setCellValue('A8', '1. SOC Analyst Staffing Allocations');
        $sheet->getStyle('A8')->applyFromArray($sectionHeaderStyle);
        $sheet->getRowDimension(8)->setRowHeight(25);

        $sheet->setCellValue('A9', 'Operational Role');
        $sheet->setCellValue('B9', 'Dedication Allocation (%)');
        $sheet->setCellValue('C9', 'Staff Count');
        $sheet->setCellValue('D9', 'Monthly Base Salary ('.$curr.')');
        $sheet->setCellValue('E9', 'Calculated Monthly Cost ('.$curr.')');
        $sheet->getStyle('A9:E9')->applyFromArray($tableHeaderStyle);
        $sheet->getRowDimension(9)->setRowHeight(25);

        $row = 10;
        foreach ($costData['analysts']['roles'] as $role) {
            $sheet->setCellValue('A'.$row, $role['name']);
            $sheet->setCellValue('B'.$row, $role['allocation_percentage'] / 100);
            $sheet->setCellValue('C'.$row, $role['staff_count']);
            $sheet->setCellValue('D'.$row, $role['monthly_salary']);
            $sheet->setCellValue('E'.$row, '=D'.$row.'*B'.$row.'*C'.$row);

            // Formatting
            $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
            $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
            $sheet->getStyle('D'.$row)->getNumberFormat()->setFormatCode($currencyFormat);
            $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode($currencyFormat);
            $row++;
        }

        // Total Staffing Row
        $sheet->setCellValue('A'.$row, 'Total Monthly Staffing Cost');
        $sheet->setCellValue('E'.$row, '=SUM(E10:E'.($row - 1).')');
        $sheet->getStyle('A'.$row.':E'.$row)->applyFromArray($totalRowStyle);
        $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode($currencyFormat);
        $totalStaffingCell = 'E'.$row;
        $row += 2;

        // 4. Infrastructure Table Section
        $sheet->setCellValue('A'.$row, '2. VM Hosting Infrastructure');
        $sheet->getStyle('A'.$row)->applyFromArray($sectionHeaderStyle);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;

        $sheet->setCellValue('A'.$row, 'Node Type / Role');
        $sheet->setCellValue('B'.$row, 'Instance Count');
        $sheet->setCellValue('C'.$row, 'RAM / Node (GB)');
        $sheet->setCellValue('D'.$row, 'Storage (GB) / Storage Type');
        $sheet->setCellValue('E'.$row, 'Total Monthly Hosting Cost ('.$curr.')');
        $sheet->getStyle('A'.$row.':E'.$row)->applyFromArray($tableHeaderStyle);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $infraHeaderRow = $row;
        $row++;

        foreach ($costData['infrastructure']['nodes'] as $node) {
            $nameText = $node['name'].' ('.$node['role'].')';
            if ($node['cloud_datacenter']) {
                $nameText .= ' [VM: '.$node['matched_vm_name'].' | Disk: '.$node['matched_disk_desc'].']';
            }
            $sheet->setCellValue('A'.$row, $nameText);
            $sheet->setCellValue('B'.$row, $node['count']);
            $sheet->setCellValue('C'.$row, $node['ram_gb']);
            $sheet->setCellValue('D'.$row, $node['storage_gb'].' GB ('.$node['storage_type'].')');
            $sheet->setCellValue('E'.$row, $node['total_monthly_cost']);

            $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
            $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
            $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode($currencyFormat);
            $row++;
        }

        // Total Infra Row
        $sheet->setCellValue('A'.$row, 'Total Monthly Hosting Cost');
        $sheet->setCellValue('E'.$row, '=SUM(E'.($infraHeaderRow + 1).':E'.($row - 1).')');
        $sheet->getStyle('A'.$row.':E'.$row)->applyFromArray($totalRowStyle);
        $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode($currencyFormat);
        $totalInfraCell = 'E'.$row;
        $row += 2;

        // 5. Software Licensing & Maintenance
        $sheet->setCellValue('A'.$row, '3. Software Licensing & Maintenance');
        $sheet->getStyle('A'.$row)->applyFromArray($sectionHeaderStyle);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;

        $sheet->setCellValue('A'.$row, 'Line Item');
        $sheet->setCellValue('B'.$row, 'Status / Configuration');
        $sheet->setCellValue('E'.$row, 'Monthly Equivalent Cost ('.$curr.')');
        $sheet->getStyle('A'.$row.':E'.$row)->applyFromArray($tableHeaderStyle);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $licensingHeaderRow = $row;
        $row++;

        $licenseStatus = $costData['raw_mssp_detail']->is_license_shared ? 'Shared ('.$costData['raw_mssp_detail']->license_share_percentage.'%)' : 'Dedicated';
        $sheet->setCellValue('A'.$row, 'Elastic Search License Equivalent');
        $sheet->setCellValue('B'.$row, $licenseStatus);
        $sheet->setCellValue('E'.$row, $costData['sizing_summary']['monthly_license_usd']);
        $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode($currencyFormat);
        $licenseCell = 'E'.$row;
        $row++;

        $sheet->setCellValue('A'.$row, 'Monthly Operational Maintenance');
        $sheet->setCellValue('B'.$row, 'Fixed Recurrer');
        $sheet->setCellValue('E'.$row, $costData['monthly_maintenance_cost']);
        $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode($currencyFormat);
        $maintenanceCell = 'E'.$row;
        $row += 2;

        // 6. Cost Base and Profit Markup Analysis
        $sheet->setCellValue('A'.$row, '4. Cost Base & Commercial Profit Markup');
        $sheet->getStyle('A'.$row)->applyFromArray($sectionHeaderStyle);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;

        $sheet->setCellValue('A'.$row, 'Profit / Benefit Margin Factor');
        $sheet->setCellValue('B'.$row, 'Markup Percentage (%)');
        $sheet->setCellValue('E'.$row, 'Monthly Markup Amount ('.$curr.')');
        $sheet->getStyle('A'.$row.':E'.$row)->applyFromArray($tableHeaderStyle);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $markupHeaderRow = $row;
        $row++;

        // Base Cost Row
        $sheet->setCellValue('A'.$row, 'Base Estimated Cost (Cost Base)');
        $sheet->setCellValue('E'.$row, '='.$totalStaffingCell.'+'.$totalInfraCell.'+'.$licenseCell.'+'.$maintenanceCell);
        $sheet->getStyle('A'.$row.':E'.$row)->getFont()->setBold(true);
        $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode($currencyFormat);
        $baseCostCell = 'E'.$row;
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
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $pct / 100);
            $sheet->setCellValue('E'.$row, '=B'.$row.'*'.$baseCostCell);

            $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
            $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode($currencyFormat);
            $row++;
        }
        $markupEndRow = $row - 1;

        // Total Profit Margin Row
        $sheet->setCellValue('A'.$row, 'Total Profit Margin Markup');
        $sheet->setCellValue('B'.$row, '=SUM(B'.$markupStartRow.':B'.$markupEndRow.')');
        $sheet->setCellValue('E'.$row, '=SUM(E'.$markupStartRow.':E'.$markupEndRow.')');
        $sheet->getStyle('A'.$row.':E'.$row)->applyFromArray($totalRowStyle);
        $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode($currencyFormat);
        $totalMarkupCell = 'E'.$row;
        $row += 2;

        // 7. Final Offered Price Banner Block
        $sheet->mergeCells('A'.$row.':C'.$row);
        $sheet->setCellValue('A'.$row, 'FINAL CLIENT OFFERED MONTHLY RECURRING PRICE (MRC)');
        $sheet->setCellValue('E'.$row, '='.$baseCostCell.'+'.$totalMarkupCell);

        $sheet->getStyle('A'.$row.':E'.$row)->getFont()->setBold(true)->setSize(11)->setColor(new Color('0F766E')); // Teal 700 text color
        $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode($currencyFormat);
        $row += 2;

        // 8. One-Time Setup Costs
        $sheet->setCellValue('A'.$row, '5. One-Time Setup & Onboarding Fees');
        $sheet->getStyle('A'.$row)->applyFromArray($sectionHeaderStyle);
        $row++;
        $sheet->setCellValue('A'.$row, 'Upfront Onboarding & Setup Cost');
        $sheet->setCellValue('E'.$row, $costData['onetime_setup_cost']);
        $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet->getStyle('A'.$row.':E'.$row)->getFont()->setBold(true);

        // Autofit columns for readability
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // --- SHEET 2: Elastic Cloud Proposal ---
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Elastic Cloud Proposal');

        // 1. Title for Sheet 2
        $sheet2->mergeCells('A1:E1');
        $sheet2->setCellValue('A1', 'CLIENT OFFER COST PROPOSAL - ELASTIC CLOUD DEPLOYMENT');
        $sheet2->getStyle('A1:E1')->applyFromArray($titleStyle);
        $sheet2->getRowDimension(1)->setRowHeight(40);

        // 2. Metadata Info
        $sheet2->setCellValue('A3', 'Client:');
        $sheet2->setCellValue('B3', $client->name);
        $sheet2->setCellValue('A4', 'Scenario Template:');
        $sheet2->setCellValue('B4', $scenario->name);
        $sheet2->setCellValue('A5', 'Date Generated:');
        $sheet2->setCellValue('B5', date('Y-m-d'));
        $sheet2->setCellValue('A6', 'Active Currency:');
        $sheet2->setCellValue('B6', $curr);
        $sheet2->getStyle('A3:A6')->getFont()->setBold(true);

        // 3. Analyst Table Section (Reused staffing details)
        $sheet2->setCellValue('A8', '1. SOC Analyst Staffing Allocations');
        $sheet2->getStyle('A8')->applyFromArray($sectionHeaderStyle);
        $sheet2->getRowDimension(8)->setRowHeight(25);

        $sheet2->setCellValue('A9', 'Operational Role');
        $sheet2->setCellValue('B9', 'Dedication Allocation (%)');
        $sheet2->setCellValue('C9', 'Staff Count');
        $sheet2->setCellValue('D9', 'Monthly Base Salary ('.$curr.')');
        $sheet2->setCellValue('E9', 'Calculated Monthly Cost ('.$curr.')');
        $sheet2->getStyle('A9:E9')->applyFromArray($tableHeaderStyle);
        $sheet2->getRowDimension(9)->setRowHeight(25);

        $row2 = 10;
        foreach ($costData['analysts']['roles'] as $role) {
            $sheet2->setCellValue('A'.$row2, $role['name']);
            $sheet2->setCellValue('B'.$row2, $role['allocation_percentage'] / 100);
            $sheet2->setCellValue('C'.$row2, $role['staff_count']);
            $sheet2->setCellValue('D'.$row2, $role['monthly_salary']);
            $sheet2->setCellValue('E'.$row2, '=D'.$row2.'*B'.$row2.'*C'.$row2);

            $sheet2->getStyle('B'.$row2)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
            $sheet2->getStyle('C'.$row2)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
            $sheet2->getStyle('D'.$row2)->getNumberFormat()->setFormatCode($currencyFormat);
            $sheet2->getStyle('E'.$row2)->getNumberFormat()->setFormatCode($currencyFormat);
            $row2++;
        }

        // Total Staffing Row
        $sheet2->setCellValue('A'.$row2, 'Total Monthly Staffing Cost');
        $sheet2->setCellValue('E'.$row2, '=SUM(E10:E'.($row2 - 1).')');
        $sheet2->getStyle('A'.$row2.':E'.$row2)->applyFromArray($totalRowStyle);
        $sheet2->getStyle('E'.$row2)->getNumberFormat()->setFormatCode($currencyFormat);
        $totalStaffingCell2 = 'E'.$row2;
        $row2 += 2;

        // 4. Elastic Cloud Subscription Section
        $sheet2->setCellValue('A'.$row2, '2. Elastic Cloud Subscription');
        $sheet2->getStyle('A'.$row2)->applyFromArray($sectionHeaderStyle);
        $sheet2->getRowDimension($row2)->setRowHeight(25);
        $row2++;

        $sheet2->setCellValue('A'.$row2, 'Node Sizing Item');
        $sheet2->setCellValue('B'.$row2, 'Operational Role');
        $sheet2->setCellValue('C'.$row2, 'Instance Count');
        $sheet2->setCellValue('D'.$row2, 'RAM / Node (GB)');
        $sheet2->setCellValue('E'.$row2, 'Matched Instance SKU');
        $sheet2->setCellValue('F'.$row2, 'Hourly Rate ($/GB-hr)');
        $sheet2->setCellValue('G'.$row2, 'Total Monthly Cost ('.$curr.')');
        $sheet2->getStyle('A'.$row2.':G'.$row2)->applyFromArray($tableHeaderStyle);
        $sheet2->getRowDimension($row2)->setRowHeight(25);
        $row2++;

        $cloudStartRow = $row2;
        foreach ($costData['cloud_option']['matched_nodes'] as $node) {
            $sheet2->setCellValue('A'.$row2, $node['name']);
            $sheet2->setCellValue('B'.$row2, $node['role']);
            $sheet2->setCellValue('C'.$row2, $node['count']);
            $sheet2->setCellValue('D'.$row2, $node['ram_gb']);
            $sheet2->setCellValue('E'.$row2, $node['sku']);
            $sheet2->setCellValue('F'.$row2, $node['hourly_rate']);
            // Monthly Cost formula: Count * RAM * Hourly Rate * 730
            $sheet2->setCellValue('G'.$row2, '=C'.$row2.'*D'.$row2.'*F'.$row2.'*730');

            $sheet2->getStyle('C'.$row2)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
            $sheet2->getStyle('D'.$row2)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
            $sheet2->getStyle('F'.$row2)->getNumberFormat()->setFormatCode('"$#,##0.0000"');
            $sheet2->getStyle('G'.$row2)->getNumberFormat()->setFormatCode($currencyFormat);
            $sheet2->getRowDimension($row2)->setRowHeight(20);
            $row2++;
        }
        $cloudEndRow = $row2 - 1;

        // Total Subscription Cost Row (Reference)
        $sheet2->setCellValue('A'.$row2, 'Total Monthly Elastic Cloud Subscription Cost (Reference)');
        $sheet2->setCellValue('G'.$row2, '=SUM(G'.$cloudStartRow.':G'.$cloudEndRow.')');
        $sheet2->getStyle('A'.$row2.':G'.$row2)->applyFromArray($totalRowStyle);
        $sheet2->getStyle('G'.$row2)->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet2->getRowDimension($row2)->setRowHeight(22);

        $totalCloudSubscriptionCell = 'G'.$row2;
        $row2 += 2;

        // 5. MDR Agent Package Coverage
        $sheet2->setCellValue('A'.$row2, '3. MDR Agent Package Coverage');
        $sheet2->getStyle('A'.$row2)->applyFromArray($sectionHeaderStyle);
        $sheet2->getRowDimension($row2)->setRowHeight(25);
        $row2++;

        $sheet2->setCellValue('A'.$row2, 'Agent Type');
        $sheet2->setCellValue('B'.$row2, 'Mapped Device Count');
        $sheet2->setCellValue('C'.$row2, 'Monthly Unit Cost ('.$curr.')');
        $sheet2->setCellValue('E'.$row2, 'Total Monthly Agent Cost ('.$curr.')');
        $sheet2->getStyle('A'.$row2.':E'.$row2)->applyFromArray($tableHeaderStyle);
        $sheet2->getRowDimension($row2)->setRowHeight(25);
        $agentHeaderRow = $row2;
        $row2++;

        $agentsList = [
            [
                'name' => 'Unified Security Monitoring & Correlation (SIEM)',
                'count' => $costData['cloud_option']['total_siem_count'],
                'unit' => $costData['cloud_option']['siem_agent_monthly_cost_per_device'],
            ],
            [
                'name' => 'Expert-Led 24/7 Monitoring & Response (MDR)',
                'count' => $costData['cloud_option']['total_mdr_count'],
                'unit' => $costData['cloud_option']['mdr_agent_monthly_cost_per_device'],
            ],
            [
                'name' => 'Advanced Endpoint Protection (EDR)',
                'count' => $costData['cloud_option']['total_edr_count'],
                'unit' => $costData['cloud_option']['edr_agent_monthly_cost_per_device'],
            ],
        ];

        foreach ($agentsList as $agent) {
            $sheet2->setCellValue('A'.$row2, $agent['name']);
            $sheet2->setCellValue('B'.$row2, $agent['count']);
            $sheet2->setCellValue('C'.$row2, $agent['unit']);
            $sheet2->setCellValue('E'.$row2, '=B'.$row2.'*C'.$row2);

            $sheet2->getStyle('B'.$row2)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
            $sheet2->getStyle('C'.$row2)->getNumberFormat()->setFormatCode($currencyFormat);
            $sheet2->getStyle('E'.$row2)->getNumberFormat()->setFormatCode($currencyFormat);
            $row2++;
        }

        // Total Agent Row
        $sheet2->setCellValue('A'.$row2, 'Total MDR Agent Package Cost');
        $sheet2->setCellValue('E'.$row2, '=SUM(E'.($agentHeaderRow + 1).':E'.($row2 - 1).')');
        $sheet2->getStyle('A'.$row2.':E'.$row2)->applyFromArray($totalRowStyle);
        $sheet2->getStyle('E'.$row2)->getNumberFormat()->setFormatCode($currencyFormat);
        $totalAgentPackageCell = 'E'.$row2;
        $row2 += 2;

        // 6. Software Maintenance (Elastic Cloud)
        $sheet2->setCellValue('A'.$row2, '4. Software Operational Maintenance');
        $sheet2->getStyle('A'.$row2)->applyFromArray($sectionHeaderStyle);
        $sheet2->getRowDimension($row2)->setRowHeight(25);
        $row2++;

        $sheet2->setCellValue('A'.$row2, 'Line Item');
        $sheet2->setCellValue('B'.$row2, 'Status / Configuration');
        $sheet2->setCellValue('E'.$row2, 'Monthly Equivalent Cost ('.$curr.')');
        $sheet2->getStyle('A'.$row2.':E'.$row2)->applyFromArray($tableHeaderStyle);
        $sheet2->getRowDimension($row2)->setRowHeight(25);
        $row2++;

        $sheet2->setCellValue('A'.$row2, 'Monthly Operational Maintenance');
        $sheet2->setCellValue('B'.$row2, 'Fixed Recurrer');
        $sheet2->setCellValue('E'.$row2, $costData['monthly_maintenance_cost']);
        $sheet2->getStyle('E'.$row2)->getNumberFormat()->setFormatCode($currencyFormat);
        $maintenanceCell2 = 'E'.$row2;
        $row2 += 2;

        // 7. Cost Base & Markup (Elastic Cloud)
        $sheet2->setCellValue('A'.$row2, '5. Cost Base & Commercial Profit Markup');
        $sheet2->getStyle('A'.$row2)->applyFromArray($sectionHeaderStyle);
        $sheet2->getRowDimension($row2)->setRowHeight(25);
        $row2++;

        $sheet2->setCellValue('A'.$row2, 'Profit / Benefit Margin Factor');
        $sheet2->setCellValue('B'.$row2, 'Markup Percentage (%)');
        $sheet2->setCellValue('E'.$row2, 'Monthly Markup Amount ('.$curr.')');
        $sheet2->getStyle('A'.$row2.':E'.$row2)->applyFromArray($tableHeaderStyle);
        $sheet2->getRowDimension($row2)->setRowHeight(25);
        $row2++;

        // Base Cost Row
        $sheet2->setCellValue('A'.$row2, 'Base Estimated Cost (MDR Agent Package Cost)');
        $sheet2->setCellValue('E'.$row2, '='.$totalAgentPackageCell);
        $sheet2->getStyle('A'.$row2.':E'.$row2)->getFont()->setBold(true);
        $sheet2->getStyle('E'.$row2)->getNumberFormat()->setFormatCode($currencyFormat);
        $baseCostCell2 = 'E'.$row2;
        $row2++;

        $markupStartRow2 = $row2;
        foreach ($markupFactors as $label => $pct) {
            $sheet2->setCellValue('A'.$row2, $label);
            $sheet2->setCellValue('B'.$row2, 0.0); // Markup is 0% since agent rates include everything
            $sheet2->setCellValue('E'.$row2, '=B'.$row2.'*'.$baseCostCell2);

            $sheet2->getStyle('B'.$row2)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
            $sheet2->getStyle('E'.$row2)->getNumberFormat()->setFormatCode($currencyFormat);
            $row2++;
        }
        $markupEndRow2 = $row2 - 1;

        // Total Profit Margin Row
        $sheet2->setCellValue('A'.$row2, 'Total Profit Margin Markup');
        $sheet2->setCellValue('B'.$row2, '=SUM(B'.$markupStartRow2.':B'.$markupEndRow2.')');
        $sheet2->setCellValue('E'.$row2, '=SUM(E'.$markupStartRow2.':E'.$markupEndRow2.')');
        $sheet2->getStyle('A'.$row2.':E'.$row2)->applyFromArray($totalRowStyle);
        $sheet2->getStyle('B'.$row2)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $sheet2->getStyle('E'.$row2)->getNumberFormat()->setFormatCode($currencyFormat);
        $totalMarkupCell2 = 'E'.$row2;
        $row2 += 2;

        // 8. Final Offered Price Banner Block
        $sheet2->mergeCells('A'.$row2.':C'.$row2);
        $sheet2->setCellValue('A'.$row2, 'FINAL CLIENT OFFERED MONTHLY RECURRING PRICE (MRC)');
        $sheet2->setCellValue('E'.$row2, '='.$baseCostCell2.'+'.$totalMarkupCell2);
        $sheet2->getStyle('A'.$row2.':E'.$row2)->getFont()->setBold(true)->setSize(11)->setColor(new Color('3B82F6')); // Blue 600 text color
        $sheet2->getStyle('E'.$row2)->getNumberFormat()->setFormatCode($currencyFormat);
        $row2 += 2;

        // 9. One-Time Setup Costs
        $sheet2->setCellValue('A'.$row2, '6. One-Time Setup & Onboarding Fees');
        $sheet2->getStyle('A'.$row2)->applyFromArray($sectionHeaderStyle);
        $row2++;
        $sheet2->setCellValue('A'.$row2, 'Upfront Onboarding & Setup Cost');
        $sheet2->setCellValue('E'.$row2, $costData['onetime_setup_cost']);
        $sheet2->getStyle('E'.$row2)->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet2->getStyle('A'.$row2.':E'.$row2)->getFont()->setBold(true);

        // Autofit columns for Sheet 2
        foreach (range('A', 'G') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = strtolower(str_replace(' ', '_', $client->name)).'_mssp_cost_proposal.xlsx';

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
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

            $analystsInfo = collect($costData['analysts']['roles'])->map(fn ($role) => [
                'role_name' => $role['name'],
                'allocation_percentage' => $role['allocation_percentage'].'%',
                'staff_count' => $role['staff_count'],
                'monthly_salary' => CurrencyHelper::format($role['monthly_salary']),
                'client_cost' => CurrencyHelper::format($role['client_cost']),
            ])->toArray();

            $nodesInfo = collect($costData['infrastructure']['nodes'])->map(fn ($node) => [
                'node_type' => $node['name'],
                'role' => $node['role'],
                'count' => $node['count'],
                'ram_gb' => $node['ram_gb'].' GB',
                'storage_gb' => $node['storage_gb'].' GB',
                'storage_type' => $node['storage_type'],
                'monthly_cost' => CurrencyHelper::format($node['total_monthly_cost']),
            ])->toArray();

            $pricingBreakdown = [
                'client_name' => $client->name,
                'scenario_name' => $scenario->name,
                'active_currency' => $curr,
                'sizing_summary' => [
                    'daily_raw_ingestion' => $costData['sizing_summary']['daily_raw_gb'].' GB',
                    'total_cluster_ram' => $costData['sizing_summary']['total_ram_gb'].' GB',
                    'monthly_license_cost' => CurrencyHelper::format($costData['sizing_summary']['monthly_license_usd']),
                    'license_sharing' => $costData['raw_mssp_detail']->is_license_shared
                        ? 'Shared ('.$costData['raw_mssp_detail']->license_share_percentage.'% allocated to client)'
                        : 'Dedicated (100% cost allocated to client)',
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
                    'assurance_benefit' => $costData['assurance_benefit_percentage'].'% ('.CurrencyHelper::format($costData['assurance_benefit_amount']).')',
                    'marketing_benefit' => $costData['marketing_benefit_percentage'].'% ('.CurrencyHelper::format($costData['marketing_benefit_amount']).')',
                    'soc_manager_profit' => $costData['soc_manager_benefit_percentage'].'% ('.CurrencyHelper::format($costData['soc_manager_benefit_amount']).')',
                    'ceo_profit' => $costData['ceo_benefit_percentage'].'% ('.CurrencyHelper::format($costData['ceo_benefit_amount']).')',
                    'fixed_profit' => $costData['fixed_profit_percentage'].'% ('.CurrencyHelper::format($costData['fixed_profit_amount']).')',
                    'total_profit_percentage' => $costData['total_profit_percentage'].'%',
                    'total_profit_amount' => CurrencyHelper::format($costData['total_profit_amount']),
                ],
                'commercial_summary' => [
                    'base_estimated_mrc' => CurrencyHelper::format($costData['total_monthly_service_cost']),
                    'total_commercial_markup' => '+'.CurrencyHelper::format($costData['total_profit_amount']),
                    'final_client_offered_mrc' => CurrencyHelper::format($costData['client_offered_price_mrc']),
                ],
            ];

            $promptContent = "You are reviewing the current MSSP/SOC proposal for client {$client->id} and scenario {$scenario->id}:\n\n".
                json_encode($pricingBreakdown, JSON_PRETTY_PRINT)."\n\n".
                'Evaluate the sizing/topology vs ingestion, evaluate the staffing allocations, and critique the pricing margins and final offered price. '.
                'Then compare all available scenarios for this client using the compare_scenarios tool, select the best scenario using the select_best_scenario tool, '.
                'and generate Markdown and Word export links for the current scenario using the export_mssp_proposal tool. '.
                'Present the recommendation as a concise summary with a cost table and clickable download links.';

            // Run ElasticCost Assistant with tool support
            $aiConfig = AiConfigHelper::configure();
            $agent = new ElasticCostAssistant;

            $sessionId = 'phpsess_'.session()->getId();
            $sessionManager = app(SessionManager::class);
            $sessionManager->activateSession($sessionId);
            $analytics = new LaravelAnalyticsCollector($sessionManager->resolveMonitorDbPath($sessionId));

            $provider = $aiConfig['provider'];
            $providerStr = $provider instanceof \BackedEnum ? $provider->value : (string) $provider;

            $llmClient = new LaravelAiClient($providerStr, $aiConfig['model']);

            $registry = new ToolRegistry;
            foreach ($agent->tools() as $laravelTool) {
                $registry->attach(new LaravelToolAdapter($laravelTool));
            }

            $loop = new AgentLoop(
                llmClient: $llmClient,
                registry: $registry,
                systemPrompt: $agent->instructions(),
                model: $aiConfig['model'],
                maxIterations: (int) config('harness.default.max_iterations', 10)
            );
            $loop->setAgentName('ElasticCostAssistant');

            $history = [];
            $analysisText = $loop->run(
                userPrompt: $promptContent,
                history: $history,
                sessionId: $sessionId,
                collector: $analytics
            );

            $analytics->endSession($sessionId, $analysisText, 0, 1);

            // Get provider display name
            $providerName = is_object($aiConfig['provider']) ? $aiConfig['provider']->name : (string) $aiConfig['provider'];

            // Build a debug footer so the user can verify the AI backend was used
            $debugInfo = "\n\n---\n> 🤖 **AI Debug Info** | Provider: `{$providerName}` | Model: `{$aiConfig['model']}` | Generated: `".now()->format('Y-m-d H:i:s').'` | Prompt tokens (est.): ~'.(int) (strlen($promptContent) / 4).' | Response tokens (est.): ~'.(int) (strlen($analysisText) / 4);
            $fullAnalysis = $analysisText.$debugInfo;

            // Save the analysis text (with debug footer) to the database
            $msspDetail = $costData['raw_mssp_detail'];
            $msspDetail->update([
                'ai_analysis' => $fullAnalysis,
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
            \Log::error('Laravel AI SDK generation error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('messages.ai_error').' Error details: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test connectivity to the local Ollama server and return diagnostic info.
     */
    public function ollamaPing(Request $request)
    {
        $providerKey = $request->input('provider', GlobalSetting::getValue('ai_provider', 'ollama'));
        $targetModel = $request->input('target_model');

        if ($providerKey === 'openrouter') {
            $apiKey = $request->input('api_key', GlobalSetting::getValue('openrouter_api_key', ''));
            $model = $targetModel ?? GlobalSetting::getValue('openrouter_model', 'meta-llama/llama-3-8b-instruct:free');
            $url = 'https://openrouter.ai/api/v1/models';

            $diagnostics = [
                'provider' => 'openrouter',
                'provider_name' => 'OpenRouter',
                'url' => $url,
                'target_model' => $model,
                'timestamp' => now()->toIso8601String(),
            ];

            try {
                $headers = [];
                if (! empty($apiKey)) {
                    $headers['Authorization'] = 'Bearer '.$apiKey;
                }
                $response = Http::withHeaders($headers)->timeout(8)->get($url);
                if ($response->successful()) {
                    $data = $response->json();
                    $models = collect($data['data'] ?? [])->pluck('id')->toArray();
                    $modelAvailable = collect($models)->contains($model);

                    $diagnostics['status'] = 'ok';
                    $diagnostics['available_models'] = $models;
                    $diagnostics['target_model_found'] = $modelAvailable;
                    $diagnostics['message'] = 'OpenRouter API is reachable ✅';
                    $diagnostics['model_status'] = $modelAvailable
                        ? "✅ Model `{$model}` is available"
                        : "⚠️ Model `{$model}` NOT found in OpenRouter. Available: ".implode(', ', array_slice($models, 0, 10)).'...';
                } else {
                    $diagnostics['status'] = 'warning';
                    $diagnostics['message'] = 'OpenRouter responded with HTTP '.$response->status();
                    $diagnostics['available_models'] = [];
                }
            } catch (\Throwable $e) {
                $diagnostics['status'] = 'error';
                $diagnostics['message'] = 'OpenRouter connection failed: '.$e->getMessage();
                $diagnostics['available_models'] = [];
            }

            return response()->json($diagnostics);
        }

        if ($providerKey === 'gemini') {
            $apiKey = $request->input('api_key', GlobalSetting::getValue('gemini_api_key', ''));
            $model = $targetModel ?? GlobalSetting::getValue('gemini_model', 'gemini-1.5-flash');
            $url = 'https://generativelanguage.googleapis.com/v1beta/models';

            $diagnostics = [
                'provider' => 'gemini',
                'provider_name' => 'Gemini',
                'url' => $url,
                'target_model' => $model,
                'timestamp' => now()->toIso8601String(),
            ];

            if (empty($apiKey)) {
                $diagnostics['status'] = 'error';
                $diagnostics['message'] = 'Gemini API Key is missing. Please enter your API key.';
                $diagnostics['available_models'] = [];

                return response()->json($diagnostics);
            }

            try {
                $response = Http::timeout(8)->get($url.'?key='.$apiKey);
                if ($response->successful()) {
                    $data = $response->json();
                    $models = collect($data['models'] ?? [])->pluck('name')->toArray();
                    $cleanModels = collect($models)->map(fn ($m) => str_replace('models/', '', $m))->toArray();
                    $modelAvailable = collect($cleanModels)->contains($model);

                    $diagnostics['status'] = 'ok';
                    $diagnostics['available_models'] = $cleanModels;
                    $diagnostics['target_model_found'] = $modelAvailable;
                    $diagnostics['message'] = 'Gemini API is reachable ✅';
                    $diagnostics['model_status'] = $modelAvailable
                        ? "✅ Model `{$model}` is available"
                        : "⚠️ Model `{$model}` NOT found in Gemini API. Available: ".implode(', ', $cleanModels);
                } else {
                    $diagnostics['status'] = 'warning';
                    $diagnostics['message'] = 'Gemini API responded with HTTP '.$response->status();
                    $diagnostics['available_models'] = [];
                }
            } catch (\Throwable $e) {
                $diagnostics['status'] = 'error';
                $diagnostics['message'] = 'Gemini exception: '.$e->getMessage();
                $diagnostics['available_models'] = [];
            }

            return response()->json($diagnostics);
        }

        if ($providerKey === 'lmstudio') {
            $url = $request->input('url', GlobalSetting::getValue('lmstudio_url', 'http://localhost:1234/v1'));
            $url = AiConfigHelper::resolveUrlForEnvironment($url);
            $model = $targetModel ?? GlobalSetting::getValue('lmstudio_model', 'qwen2.5-coder-7b-instruct');

            $url = rtrim($url, '/');

            $diagnostics = [
                'provider' => 'lmstudio',
                'provider_name' => 'LM Studio',
                'url' => $url,
                'target_model' => $model,
                'timestamp' => now()->toIso8601String(),
            ];

            try {
                $response = Http::timeout(8)->get($url.'/models');
                if ($response->successful()) {
                    $data = $response->json();
                    $models = collect($data['data'] ?? [])->pluck('id')->toArray();
                    $modelAvailable = collect($models)->contains($model);

                    $diagnostics['status'] = 'ok';
                    $diagnostics['available_models'] = $models;
                    $diagnostics['target_model_found'] = $modelAvailable;
                    $diagnostics['message'] = 'LM Studio server is reachable ✅';
                    $diagnostics['model_status'] = $modelAvailable
                        ? "✅ Model `{$model}` is available"
                        : "⚠️ Model `{$model}` NOT found in LM Studio. Available: ".implode(', ', $models);
                } else {
                    $diagnostics['status'] = 'warning';
                    $diagnostics['message'] = "LM Studio responded with HTTP {$response->status()}";
                    $diagnostics['available_models'] = [];
                }
            } catch (\Throwable $e) {
                $diagnostics['status'] = 'error';
                $diagnostics['message'] = 'LM Studio connection failed: '.$e->getMessage();
                $diagnostics['available_models'] = [];
            }

            return response()->json($diagnostics);
        }

        if ($providerKey === 'qwen') {
            $apiKey = $request->input('api_key', GlobalSetting::getValue('qwen_api_key', ''));
            $model = $targetModel ?? GlobalSetting::getValue('qwen_model', 'qwen-plus');
            $url = $request->input('url', GlobalSetting::getValue('qwen_url', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'));
            $url = rtrim($url, '/');

            $diagnostics = [
                'provider' => 'qwen',
                'provider_name' => 'Qwen Cloud',
                'url' => $url,
                'target_model' => $model,
                'timestamp' => now()->toIso8601String(),
            ];

            try {
                $headers = [];
                if (! empty($apiKey)) {
                    $headers['Authorization'] = 'Bearer '.$apiKey;
                }
                $response = Http::withHeaders($headers)->timeout(8)->get($url.'/models');
                if ($response->successful()) {
                    $data = $response->json();
                    $models = collect($data['data'] ?? [])->pluck('id')->toArray();
                    $modelAvailable = collect($models)->contains($model);

                    $diagnostics['status'] = 'ok';
                    $diagnostics['available_models'] = array_slice($models, 0, 20);
                    $diagnostics['total_models'] = count($models);
                    $diagnostics['target_model_found'] = $modelAvailable;
                    $diagnostics['message'] = 'Qwen Cloud API is reachable ✅';
                    $diagnostics['model_status'] = $modelAvailable
                        ? "✅ Model `{$model}` is available"
                        : "⚠️ Model `{$model}` NOT found in Qwen Cloud. Available: ".implode(', ', array_slice($models, 0, 10)).'...';
                } else {
                    $diagnostics['status'] = 'warning';
                    $diagnostics['message'] = 'Qwen Cloud responded with HTTP '.$response->status();
                    $diagnostics['available_models'] = [];
                }
            } catch (\Throwable $e) {
                $diagnostics['status'] = 'error';
                $diagnostics['message'] = 'Qwen Cloud connection failed: '.$e->getMessage();
                $diagnostics['available_models'] = [];
            }

            return response()->json($diagnostics);
        }

        // Default: Ollama
        $url = $request->input('url', GlobalSetting::getValue('ollama_url', 'http://localhost:11434'));
        $url = AiConfigHelper::resolveUrlForEnvironment($url);
        $model = $targetModel ?? GlobalSetting::getValue('ollama_model', 'gemma4:e2b');

        $url = rtrim($url, '/');

        $diagnostics = [
            'provider' => 'ollama',
            'provider_name' => 'Ollama',
            'url' => $url,
            'target_model' => $model,
            'timestamp' => now()->toIso8601String(),
        ];

        try {
            $response = Http::timeout(8)->get($url.'/api/tags');
            if ($response->successful()) {
                $data = $response->json();
                $models = collect($data['models'] ?? [])->pluck('name')->toArray();
                $modelAvailable = in_array($model, $models) || collect($models)->contains(fn ($m) => str_starts_with($m, explode(':', $model)[0]));

                $diagnostics['status'] = 'ok';
                $diagnostics['message'] = 'Ollama server is reachable ✅';
                $diagnostics['available_models'] = $models;
                $diagnostics['target_model_found'] = $modelAvailable;
                $diagnostics['model_status'] = $modelAvailable
                    ? "✅ Model `{$model}` is available"
                    : "⚠️ Model `{$model}` NOT found in Ollama. Available: ".implode(', ', $models);
            } else {
                $diagnostics['status'] = 'warning';
                $diagnostics['message'] = "Ollama responded with HTTP {$response->status()}";
                $diagnostics['available_models'] = [];
            }
        } catch (\Throwable $e) {
            $diagnostics['status'] = 'error';
            $diagnostics['message'] = 'Ollama connection failed: '.$e->getMessage();
            $diagnostics['available_models'] = [];
        }

        return response()->json($diagnostics);
    }

    /**
     * Reset the Agent & Pack Profit Simulation settings to live scenario defaults.
     */
    public function resetSimulation(Client $client, Scenario $scenario)
    {
        $costData = $this->costingEngine->calculate($client, $scenario);
        $msspDetail = $costData['raw_mssp_detail'];

        $defaults = app(AgentProfitSimulatorService::class)->getScenarioDefaults($client, $msspDetail);
        $msspDetail->update([
            'agent_profit_simulation_settings' => $defaults,
        ]);

        return redirect()->route('mssp.show', [$client->id, $scenario->id, 'tab' => 'simulator'])
            ->with('success', 'Simulation settings reset to live scenario inventory baseline & rate cards.');
    }
}
