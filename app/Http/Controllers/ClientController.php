<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Scenario;
use App\Models\AssetType;
use App\Services\SizingEngine;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    protected SizingEngine $sizingEngine;

    public function __construct(SizingEngine $sizingEngine)
    {
        $this->sizingEngine = $sizingEngine;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $clients = Client::withCount('clientAssets')->get();
        return view('clients.index', compact('clients'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $client = Client::create($validated);

        // Pre-populate inventory with default assets (count = 0) so the user can easily fill in counts
        $assetTypes = AssetType::all();
        foreach ($assetTypes as $type) {
            $client->clientAssets()->create([
                'asset_type_id' => $type->id,
                'device_count' => 0,
            ]);
        }

        return redirect()->route('clients.show', $client->id)
            ->with('success', 'Client created successfully. Please configure device counts.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Client $client)
    {
        // Load client assets with types
        $inventory = $client->clientAssets()->with('assetType')->get();
        
        // Find asset types that are NOT currently in the client's inventory (if any)
        $existingAssetTypeIds = $inventory->pluck('asset_type_id')->toArray();
        $availableAssetTypes = AssetType::whereNotIn('id', $existingAssetTypeIds)->get();

        // Load all scenarios
        $scenarios = Scenario::all();
        $scenarioComparisons = [];

        foreach ($scenarios as $scenario) {
            $result = $this->sizingEngine->calculate($client, $scenario);
            $scenarioComparisons[] = [
                'scenario' => $scenario,
                'totals' => $result['totals'],
                'licensing' => $result['licensing']
            ];
        }

        return view('clients.show', compact('client', 'inventory', 'availableAssetTypes', 'scenarioComparisons'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client)
    {
        $client->delete();
        return redirect()->route('clients.index')
            ->with('success', 'Client deleted successfully.');
    }
}
