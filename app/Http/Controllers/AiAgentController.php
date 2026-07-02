<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ElasticCostAssistant;
use App\Ai\Agents\MarketBuyingSimulatorAgent;
use App\Ai\Agents\OfferAnalyst;
use App\Ai\Agents\RgSocEngineer;
use App\Ai\Agents\SizingRegulator;
use App\Ai\Analytics\LaravelAnalyticsCollector;
use App\Ai\Middleware\InjectDocumentation;
use App\Models\Client;
use App\Models\GlobalSetting;
use App\Models\Scenario;
use App\Services\AiConfigHelper;
use App\Services\CurrencyHelper;
use App\Services\MsspCostingEngine;
use App\Services\SizingEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Prompts\AgentPrompt;
use Phpkaiharness\Core\AgentLoop;
use Phpkaiharness\Core\Prompt\DummyTextProvider;
use Phpkaiharness\Llm\LaravelAiClient;

class AiAgentController extends Controller
{
    protected SizingEngine $sizingEngine;

    protected MsspCostingEngine $costingEngine;

    public function __construct(SizingEngine $sizingEngine, MsspCostingEngine $costingEngine)
    {
        $this->sizingEngine = $sizingEngine;
        $this->costingEngine = $costingEngine;
    }

    /**
     * Display the AI Agents & Orchestration registry, config and sandbox.
     */
    public function index(Request $request): View
    {
        $clients = Client::all();
        $scenarios = Scenario::all();

        // Load configuration overrides
        $orchestrationMode = GlobalSetting::getValue('ai_orchestration_mode', 'router-executor');
        $delegationEnabled = (bool) GlobalSetting::getValue('ai_delegation_enabled', true);
        $maxDelegationHops = (int) GlobalSetting::getValue('ai_max_delegation_hops', 3);

        // Resolve active AI backend details
        $aiConfig = AiConfigHelper::configure();
        $providerName = is_object($aiConfig['provider']) ? $aiConfig['provider']->name : (string) $aiConfig['provider'];
        $activeModelInfo = "{$providerName} | {$aiConfig['model']}";

        // Get Multi-model configurations
        $multiConfig = AiConfigHelper::configureMultiModel();
        $lightProvider = is_object($multiConfig['light']['provider']) ? $multiConfig['light']['provider']->name : (string) $multiConfig['light']['provider'];
        $lightModelInfo = "{$lightProvider} | {$multiConfig['light']['model']}";

        // Programmatic description mapping for display
        $agents = [
            [
                'id' => 'ElasticCostAssistant',
                'name' => 'ElasticCost Assistant',
                'class' => ElasticCostAssistant::class,
                'role' => 'Primary Conversational Interface & Front-End Helper',
                'description' => 'Built directly into the sizing and costing panels, this agent serves as the entrypoint for users. It can translate requests, provide high-level summaries, and delegate complex tasks to domain specialist agents.',
                'backend' => $activeModelInfo,
                'capabilities' => [
                    'Sizing & topology sizing summaries',
                    'Interactive help for MSSP SOC costing rules',
                    'Multi-language localizations (EN/FR/AR)',
                    'Multi-currency conversions (USD/EUR/TND)',
                ],
                'tools' => ['RgSocEngineer', 'OfferAnalyst'],
                'middleware' => ['InjectDocumentation'],
            ],
            [
                'id' => 'RgSocEngineer',
                'name' => 'RG SOC Engineer',
                'class' => RgSocEngineer::class,
                'role' => 'System Operations & Database Action Executor',
                'description' => 'Acts as the technical administrator. Routes incoming tasks from the router, inspects active asset types, modifies global settings, configures client fleets, and modifies analyst allocations.',
                'backend' => $lightModelInfo.' (Light Router) / '.$activeModelInfo.' (Main)',
                'capabilities' => [
                    'Dynamic request routing and intent classification',
                    'Automated updates to global exchange rates & prices',
                    'Fleet security agent coverage allocation updates',
                    'Conversational client profile creation',
                ],
                'tools' => ['RgSocEngineerMain'],
                'middleware' => ['TelemetryMiddleware', 'PolicyGuardrailMiddleware'],
            ],
            [
                'id' => 'OfferAnalyst',
                'name' => 'Offer Analyst',
                'class' => OfferAnalyst::class,
                'role' => 'MSSP Commercial Architect & Financial Critique',
                'description' => 'A financial specialist agent. Performs sanity checks on profit markups, validates log ingestion data to avoid zero-ingested licenses, reviews VM sizing, and generates structured markdown critiquing proposals.',
                'backend' => $activeModelInfo,
                'capabilities' => [
                    'Critical sanity checks (Zero log ingestion flags)',
                    'Markup factor audits (Assurance, marketing, management)',
                    'Staffing & frontline analyst resource checking',
                    'Proposal health score grading (1-10)',
                ],
                'tools' => [],
                'middleware' => [],
            ],
            [
                'id' => 'SizingRegulator',
                'name' => 'Sizing Regulator',
                'class' => SizingRegulator::class,
                'role' => 'Elasticsearch Solutions Architect & Systems Sizing Analyst',
                'description' => 'An expert sizing agent. Audits physical topologies, verifies Hot/Warm/Cold/Frozen RAM-to-disk ratios, performs master node quorum checks, and enforces the JVM heap 50/50 memory rule.',
                'backend' => $activeModelInfo,
                'capabilities' => [
                    'Mathematical RAM-to-disk ratio verification',
                    'High Availability & Master Quorum checks',
                    'Data tier retention duration calculations',
                    'JVM heap limit caps and recommendations',
                ],
                'tools' => [],
                'middleware' => ['InjectDocumentation'],
            ],
            [
                'id' => 'MarketBuyingSimulatorAgent',
                'name' => 'Market Buying Simulator',
                'class' => MarketBuyingSimulatorAgent::class,
                'role' => 'Market Buying & Profit Optimization AI Agent',
                'description' => 'Simulates buyer purchasing behavior over 1, 3, 6, 12, 36 months for enterprise partners and direct clients. Evaluates wholesale vs retail margins, compares custom service packs vs per-agent purchases, and provides profit optimization recommendations.',
                'backend' => $activeModelInfo,
                'capabilities' => [
                    'Market purchasing persona simulation',
                    'Partner Wholesale vs Client Retail margin evaluation',
                    'Custom pack vs per-agent demand comparison',
                    '36-month cumulative profit optimization strategies',
                ],
                'tools' => [],
                'middleware' => [],
            ],
        ];

        return view('settings.agents', compact(
            'clients',
            'scenarios',
            'orchestrationMode',
            'delegationEnabled',
            'maxDelegationHops',
            'agents'
        ));
    }

    /**
     * Update global agent orchestration configurations.
     */
    public function updateConfig(Request $request): RedirectResponse
    {
        $request->validate([
            'ai_orchestration_mode' => 'required|in:router-executor,linear,autonomous',
            'ai_delegation_enabled' => 'nullable|string',
            'ai_max_delegation_hops' => 'required|integer|min:1|max:10',
        ]);

        GlobalSetting::updateOrCreate(
            ['key' => 'ai_orchestration_mode'],
            [
                'value' => $request->input('ai_orchestration_mode'),
                'description' => 'Predefined agent orchestration style',
            ]
        );

        GlobalSetting::updateOrCreate(
            ['key' => 'ai_delegation_enabled'],
            [
                'value' => $request->boolean('ai_delegation_enabled') ? '1' : '0',
                'description' => 'Allow agents to communicate and delegate tasks to other agents',
            ]
        );

        GlobalSetting::updateOrCreate(
            ['key' => 'ai_max_delegation_hops'],
            [
                'value' => (string) $request->input('ai_max_delegation_hops'),
                'description' => 'Maximum allowed delegation hops during agent conversation',
            ]
        );

        return redirect()->route('settings.agents')
            ->with('success', 'Agent orchestration settings updated successfully!');
    }

    /**
     * Run an agent analysis simulation in the sandbox.
     */
    public function runAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'scenario_id' => 'required|exists:scenarios,id',
            'agent' => 'required|in:SizingRegulator,OfferAnalyst',
        ]);

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        try {
            $client = Client::findOrFail($request->input('client_id'));
            $scenario = Scenario::findOrFail($request->input('scenario_id'));
            $agentType = $request->input('agent');

            if ($request->boolean('simulation')) {
                if ($agentType === 'SizingRegulator') {
                    $output = "### 📊 Elasticsearch Sizing Audit\n\n**Verdict:** Imbalanced\n**Health Score:** 6/10\n\n#### 💡 Sizing Regulator Recommendations:\n1. Sizing regulator recommends updating the USD to EUR rate setting `usd_to_eur_rate` to `0.95` to align regional hosting costs.\n2. The SIEM agent price `siem_agent_monthly_cost_per_device` should be set to `25` per device.\n3. Enable EDR security agent coverage on client assets.";

                    return response()->json([
                        'success' => true,
                        'agent' => 'Sizing Regulator',
                        'output' => $output,
                        'html' => Str::markdown($output),
                    ]);
                } else {
                    $output = "### 💰 SOC Cost Proposal Audit\n\n**Verdict:** Optimal\n**Health Score:** 7/10\n\n#### 💡 Offer Analyst Recommendations:\n1. The USD to EUR rate setting `usd_to_eur_rate` should be updated to `0.95` to match current market trends.\n2. Set the EDR agent price `edr_agent_monthly_cost_per_device` to `4.5` to align client margins.";

                    return response()->json([
                        'success' => true,
                        'agent' => 'Offer Analyst',
                        'output' => $output,
                        'html' => Str::markdown($output),
                    ]);
                }
            }

            $aiConfig = AiConfigHelper::configure();

            if ($agentType === 'SizingRegulator') {
                $data = $this->sizingEngine->calculate($client, $scenario);

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

                $provider = $aiConfig['provider'];
                $providerStr = $provider instanceof \BackedEnum ? $provider->value : (string) $provider;
                $model = $aiConfig['model'];

                $agent = new SizingRegulator;
                if ($agent::isFaked()) {
                    $response = $agent->prompt($promptContent, provider: $aiConfig['provider'], model: $aiConfig['model'], timeout: 120);
                    $analysisText = property_exists($response, 'structured')
                        ? ($response->structured['full_critique'] ?? $response->text)
                        : $response->text;

                    return response()->json([
                        'success' => true,
                        'agent' => 'Sizing Regulator',
                        'output' => $analysisText,
                        'html' => Str::markdown($analysisText),
                    ]);
                }

                // Run InjectDocumentation RAG middleware manually
                $ragMiddleware = new InjectDocumentation;
                $dummyProvider = new DummyTextProvider;
                $dummyPrompt = new AgentPrompt($agent, $promptContent, [], $dummyProvider, $model);
                $finalPrompt = $ragMiddleware->handle($dummyPrompt, fn ($p) => $p);
                $promptContent = $finalPrompt->prompt;

                // Call agent via phpkaiharness pipeline
                $schemaJson = '{
                    "verdict": "Adequate, Under-provisioned, or Imbalanced",
                    "health_score": 8,
                    "ratio_audit": [
                        "Tier audit details..."
                    ],
                    "ha_check": {
                        "master_eligible_count": 3,
                        "quorum_met": true,
                        "remarks": "Master quorum details..."
                    },
                    "recommendations": [
                        "Recommendation 1",
                        "Recommendation 2"
                    ],
                    "full_critique": "Detailed markdown audit critique..."
                }';

                $systemPrompt = (new SizingRegulator)->instructions()."\n\nYou MUST respond ONLY with a valid JSON object matching the following structure:\n".$schemaJson;
                $llmClient = new LaravelAiClient($providerStr, $model);

                $loop = new AgentLoop(
                    llmClient: $llmClient,
                    systemPrompt: $systemPrompt,
                    model: $model,
                    maxIterations: 1
                );
                $loop->setAgentName('SizingRegulator');

                $sessionId = 'sandbox_'.session()->getId();
                $analytics = new LaravelAnalyticsCollector;

                $history = [];
                $responseText = $loop->run(
                    userPrompt: $promptContent,
                    history: $history,
                    sessionId: $sessionId,
                    collector: $analytics
                );

                $decoded = json_decode($responseText, true);
                $analysisText = is_array($decoded)
                    ? ($decoded['full_critique'] ?? $responseText)
                    : $responseText;

                return response()->json([
                    'success' => true,
                    'agent' => 'Sizing Regulator',
                    'output' => $analysisText,
                    'html' => Str::markdown($analysisText),
                ]);
            } else {
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

                $promptContent = "Please analyze the following Cybersecurity MSSP and SOC proposal costing details:\n\n".
                          json_encode($pricingBreakdown, JSON_PRETTY_PRINT)."\n\n".
                          'Evaluate the sizing/topology vs ingestion, evaluate the staffing allocations, and critique the pricing margins and final offered price. Provide recommendations.';

                $provider = $aiConfig['provider'];
                $providerStr = $provider instanceof \BackedEnum ? $provider->value : (string) $provider;
                $model = $aiConfig['model'];

                $agent = new OfferAnalyst;
                if ($agent::isFaked()) {
                    $response = $agent->prompt($promptContent, provider: $aiConfig['provider'], model: $aiConfig['model'], timeout: 120);
                    $analysisText = property_exists($response, 'structured')
                        ? ($response->structured['full_critique'] ?? $response->text)
                        : $response->text;

                    return response()->json([
                        'success' => true,
                        'agent' => 'Offer Analyst',
                        'output' => $analysisText,
                        'html' => Str::markdown($analysisText),
                    ]);
                }

                $schemaJson = '{
                    "health_score": 8,
                    "margin_status": "Low, Optimal, or High",
                    "sanity_checks": [
                        "Sanity check status..."
                    ],
                    "staffing_status": "Over-allocated, Under-allocated, or Balanced",
                    "infrastructure_status": "Wasteful, Imbalanced, or Optimal",
                    "recommendations": [
                        "Recommendation 1",
                        "Recommendation 2"
                    ],
                    "full_critique": "Detailed markdown analysis..."
                }';

                $systemPrompt = (new OfferAnalyst)->instructions()."\n\nYou MUST respond ONLY with a valid JSON object matching the following structure:\n".$schemaJson;
                $llmClient = new LaravelAiClient($providerStr, $model);

                $loop = new AgentLoop(
                    llmClient: $llmClient,
                    systemPrompt: $systemPrompt,
                    model: $model,
                    maxIterations: 1
                );
                $loop->setAgentName('OfferAnalyst');

                $sessionId = 'sandbox_'.session()->getId();
                $analytics = new LaravelAnalyticsCollector;

                $history = [];
                $responseText = $loop->run(
                    userPrompt: $promptContent,
                    history: $history,
                    sessionId: $sessionId,
                    collector: $analytics
                );

                $decoded = json_decode($responseText, true);
                $analysisText = is_array($decoded)
                    ? ($decoded['full_critique'] ?? $responseText)
                    : $responseText;

                return response()->json([
                    'success' => true,
                    'agent' => 'Offer Analyst',
                    'output' => $analysisText,
                    'html' => Str::markdown($analysisText),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('AI sandbox analysis failed: '.$e->getMessage());
            $errorMsg = $e->getMessage();
            if (str_contains(strtolower($errorMsg), 'timed out') || str_contains(strtolower($errorMsg), 'curl error 28') || str_contains(strtolower($errorMsg), 'connection')) {
                $errorMsg .= ' (The connection to the AI server timed out or failed. Please check if your active AI provider/URL in System Settings is reachable, or switch to a different provider.)';
            }

            return response()->json([
                'success' => false,
                'message' => 'Analysis failed: '.$errorMsg,
            ], 500);
        }
    }

    /**
     * Forward the analyst output as context and run the SOC Engineer to execute action.
     */
    public function runOrchestratedAction(Request $request): JsonResponse
    {
        $request->validate([
            'context' => 'required|string',
            'instruction' => 'nullable|string',
        ]);

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        try {
            $contextText = $request->input('context');
            $customInstruction = $request->input('instruction');

            if ($request->boolean('simulation')) {
                $executedTools = [];
                $responseText = "I have successfully analyzed the forwarded critique and executed the following database setting updates:\n\n";

                if (str_contains($contextText, 'usd_to_eur_rate')) {
                    preg_match('/usd_to_eur_rate.*?(\d+(?:\.\d+)?)/', $contextText, $matches);
                    $rate = $matches[1] ?? '0.95';
                    GlobalSetting::updateOrCreate(
                        ['key' => 'usd_to_eur_rate'],
                        ['value' => $rate, 'description' => 'USD to EUR conversion rate']
                    );
                    $executedTools[] = [
                        'name' => 'UpdateGlobalSettingTool',
                        'arguments' => ['key' => 'usd_to_eur_rate', 'value' => $rate],
                        'status' => 'success',
                    ];
                    $responseText .= "* Updated global configuration `usd_to_eur_rate` to `{$rate}`.\n";
                }

                if (str_contains($contextText, 'siem_agent_monthly_cost_per_device')) {
                    preg_match('/siem_agent_monthly_cost_per_device.*?(\d+)/', $contextText, $matches);
                    $price = $matches[1] ?? '25';
                    GlobalSetting::updateOrCreate(
                        ['key' => 'siem_agent_monthly_cost_per_device'],
                        ['value' => $price, 'description' => 'SIEM agent monthly cost per device']
                    );
                    $executedTools[] = [
                        'name' => 'UpdateGlobalSettingTool',
                        'arguments' => ['key' => 'siem_agent_monthly_cost_per_device', 'value' => $price],
                        'status' => 'success',
                    ];
                    $responseText .= "* Updated global configuration `siem_agent_monthly_cost_per_device` to `{$price}`.\n";
                }

                if (str_contains($contextText, 'edr_agent_monthly_cost_per_device')) {
                    preg_match('/edr_agent_monthly_cost_per_device.*?(\d+(?:\.\d+)?)/', $contextText, $matches);
                    $price = $matches[1] ?? '4.5';
                    GlobalSetting::updateOrCreate(
                        ['key' => 'edr_agent_monthly_cost_per_device'],
                        ['value' => $price, 'description' => 'EDR agent monthly cost per device']
                    );
                    $executedTools[] = [
                        'name' => 'UpdateGlobalSettingTool',
                        'arguments' => ['key' => 'edr_agent_monthly_cost_per_device', 'value' => $price],
                        'status' => 'success',
                    ];
                    $responseText .= "* Updated global configuration `edr_agent_monthly_cost_per_device` to `{$price}`.\n";
                }

                $responseText .= "\nAll required system configurations have been successfully aligned with the analyst's recommendations.";

                return response()->json([
                    'success' => true,
                    'output' => $responseText,
                    'html' => Str::markdown($responseText),
                    'executed_tools' => $executedTools,
                ]);
            }

            $instructionText = $customInstruction
                ? $customInstruction
                : 'Review the recommendations in the context below and execute any system setting updates, pricing revisions, or asset calibrations mentioned.';

            $compiledPrompt = "TASK: {$instructionText}\n\nCONVERSATION CONTEXT:\n{$contextText}";

            // Run SOC Engineer
            $socEngineer = new RgSocEngineer;
            $socEngineer->phpSessionId = 'sandbox_'.session()->getId();

            // Set up active models
            $multiConfig = AiConfigHelper::configureMultiModel();
            $provider = $multiConfig['light']['provider'];
            $model = $multiConfig['light']['model'];

            // Run
            $response = $socEngineer->prompt($compiledPrompt, provider: $provider, model: $model);

            // Extract executed tool calls
            $executedTools = [];
            if (isset($response->toolCalls) && is_object($response->toolCalls)) {
                $executedTools = collect($response->toolCalls)->map(function ($toolCall) {
                    return [
                        'name' => $toolCall->name ?? 'Unknown Tool',
                        'arguments' => (array) ($toolCall->arguments ?? []),
                        'status' => 'success',
                    ];
                })->toArray();
            }

            return response()->json([
                'success' => true,
                'output' => $response->text,
                'html' => Str::markdown($response->text),
                'executed_tools' => $executedTools,
            ]);
        } catch (\Throwable $e) {
            Log::error('Agent orchestrated action failed: '.$e->getMessage());
            $errorMsg = $e->getMessage();
            if (str_contains(strtolower($errorMsg), 'timed out') || str_contains(strtolower($errorMsg), 'curl error 28') || str_contains(strtolower($errorMsg), 'connection')) {
                $errorMsg .= ' (The connection to the AI server timed out or failed. Please check if your active AI provider/URL in System Settings is reachable, or switch to a different provider.)';
            }

            return response()->json([
                'success' => false,
                'message' => 'Orchestration failed: '.$errorMsg,
            ], 500);
        }
    }
}
