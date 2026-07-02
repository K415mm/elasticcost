<?php

namespace App\Http\Controllers;

use App\Models\AssetType;
use Illuminate\Http\Request;

class AssetTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $assetTypes = AssetType::all();

        return view('asset_types.index', compact('assetTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:asset_types,name',
            'avg_event_size_bytes' => 'required|integer|min:1',
            'calibration_mode' => 'required|string|in:eps_per_device,monthly_gb_per_device,monthly_gb_total',
            'runs_siem_agent' => 'nullable|boolean',
            'runs_mdr_agent' => 'nullable|boolean',
            'runs_edr_agent' => 'nullable|boolean',
            'min_eps_default' => 'required|numeric|min:0',
            'avg_eps_default' => 'required|numeric|min:0',
            'max_eps_default' => 'nullable|numeric|min:0',
            'max_monthly_gb_default' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $validated['runs_siem_agent'] = $request->has('runs_siem_agent');
        $validated['runs_mdr_agent'] = $request->has('runs_mdr_agent');
        $validated['runs_edr_agent'] = $request->has('runs_edr_agent');

        AssetType::create($validated);

        return redirect()->route('asset-types.index')
            ->with('success', 'New asset type created.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AssetType $assetType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:asset_types,name,'.$assetType->id,
            'avg_event_size_bytes' => 'required|integer|min:1',
            'calibration_mode' => 'required|string|in:eps_per_device,monthly_gb_per_device,monthly_gb_total',
            'runs_siem_agent' => 'nullable|boolean',
            'runs_mdr_agent' => 'nullable|boolean',
            'runs_edr_agent' => 'nullable|boolean',
            'min_eps_default' => 'required|numeric|min:0',
            'avg_eps_default' => 'required|numeric|min:0',
            'max_eps_default' => 'nullable|numeric|min:0',
            'max_monthly_gb_default' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $validated['runs_siem_agent'] = $request->has('runs_siem_agent');
        $validated['runs_mdr_agent'] = $request->has('runs_mdr_agent');
        $validated['runs_edr_agent'] = $request->has('runs_edr_agent');

        $assetType->update($validated);

        return redirect()->route('asset-types.index')
            ->with('success', 'Asset type updated successfully.');
    }
}
