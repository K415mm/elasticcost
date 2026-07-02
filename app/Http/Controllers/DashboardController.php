<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientAsset;
use App\Models\GlobalSetting;
use App\Models\Scenario;
use App\Services\CurrencyHelper;
use App\Services\SizingEngine;

class DashboardController extends Controller
{
    protected SizingEngine $sizingEngine;

    public function __construct(SizingEngine $sizingEngine)
    {
        $this->sizingEngine = $sizingEngine;
    }

    /**
     * Display the main system-wide landing dashboard.
     */
    public function index()
    {
        $clients = Client::with('clientAssets')->get();
        $scenarios = Scenario::all();

        $totalClients = $clients->count();
        $totalDevices = ClientAsset::sum('device_count');

        // Calculate overall system-wide Daily Ingested GB (under Average workload profile)
        $totalDailyRawGb = 0.0;
        $totalClusterRamGb = 0;
        $totalRequiredErus = 0;

        $clientSummaries = [];

        foreach ($clients as $client) {
            // Run sizing calculation using Scenario 2 (Average / standard Multi-Tier) as default for landing stats
            $defaultScenario = Scenario::find(2) ?? Scenario::first();
            $sizing = $defaultScenario ? $this->sizingEngine->calculate($client, $defaultScenario) : null;

            $clientDailyRaw = $sizing ? $sizing['totals']['daily_raw_gb'] : 0.0;
            $clientRam = $sizing ? $sizing['licensing']['total_ram_gb'] : 0;
            $clientErus = $sizing ? $sizing['licensing']['required_erus'] : 0;

            $totalDailyRawGb += $clientDailyRaw;
            $totalClusterRamGb += $clientRam;
            $totalRequiredErus += $clientErus;

            $clientSummaries[] = [
                'client' => $client,
                'device_count' => $client->clientAssets()->sum('device_count'),
                'daily_raw_gb' => $clientDailyRaw,
                'cluster_ram_gb' => $clientRam,
                'required_erus' => $clientErus,
                'default_scenario' => $defaultScenario,
            ];
        }

        // Active exchange rates
        $eurRate = CurrencyHelper::rate('EUR');
        $tndRate = CurrencyHelper::rate('TND');

        // Active AI provider
        $aiProviderKey = GlobalSetting::getValue('ai_provider', 'ollama');
        $aiProviderNames = [
            'ollama' => 'Ollama (Local)',
            'lmstudio' => 'LM Studio (Local)',
            'gemini' => 'Gemini Studio (Cloud)',
        ];
        $aiProviderName = $aiProviderNames[$aiProviderKey] ?? ucfirst($aiProviderKey);

        return view('dashboard.index', compact(
            'totalClients',
            'totalDevices',
            'totalDailyRawGb',
            'totalClusterRamGb',
            'totalRequiredErus',
            'clientSummaries',
            'scenarios',
            'eurRate',
            'tndRate',
            'aiProviderName'
        ));
    }
}
