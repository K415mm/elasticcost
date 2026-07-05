<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientAsset;
use App\Models\GlobalSetting;
use App\Models\Scenario;
use App\Services\CurrencyHelper;
use App\Services\SizingEngine;
use Illuminate\Support\Facades\Cache;

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
        $clientSummaries = Cache::remember('dashboard:client_summaries', now()->addMinutes(10), function () {
            $clients = Client::with('clientAssets')->get();
            $summaries = [];

            foreach ($clients as $client) {
                $defaultScenario = Scenario::find(2) ?? Scenario::first();
                $sizing = $defaultScenario ? $this->sizingEngine->calculate($client, $defaultScenario) : null;

                $summaries[] = [
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'client_description' => $client->description,
                    'device_count' => $client->clientAssets()->sum('device_count'),
                    'daily_raw_gb' => $sizing ? $sizing['totals']['daily_raw_gb'] : 0.0,
                    'cluster_ram_gb' => $sizing ? $sizing['licensing']['total_ram_gb'] : 0,
                    'required_erus' => $sizing ? $sizing['licensing']['required_erus'] : 0,
                    'default_scenario_id' => $defaultScenario?->id,
                    'default_scenario_name' => $defaultScenario?->name,
                ];
            }

            return $summaries;
        });

        $totalClients = Cache::remember('dashboard:total_clients', now()->addMinutes(10), fn () => Client::count());
        $totalDevices = Cache::remember('dashboard:total_devices', now()->addMinutes(10), fn () => ClientAsset::sum('device_count'));

        $totalDailyRawGb = collect($clientSummaries)->sum('daily_raw_gb');
        $totalClusterRamGb = collect($clientSummaries)->sum('cluster_ram_gb');
        $totalRequiredErus = collect($clientSummaries)->sum('required_erus');

        $scenarios = Cache::remember('dashboard:scenarios', now()->addMinutes(10), fn () => Scenario::all()->toArray());
        $scenarios = collect($scenarios)->map(fn ($s) => (object) $s);

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
