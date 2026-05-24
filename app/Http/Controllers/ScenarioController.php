<?php

namespace App\Http\Controllers;

use App\Models\Scenario;
use Illuminate\Http\Request;

class ScenarioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $scenarios = Scenario::all();
        return view('scenarios.index', compact('scenarios'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'workload_profile' => 'required|string|in:min,avg,max',
            'retention_days' => 'required|integer|min:1',
            'hot_days' => 'required|integer|min:0',
            'warm_days' => 'required|integer|min:0',
            'cold_days' => 'required|integer|min:0',
            'frozen_days' => 'required|integer|min:0',
            'hot_replicas' => 'required|integer|min:0',
            'warm_replicas' => 'required|integer|min:0',
            'cold_replicas' => 'required|integer|min:0',
            'frozen_replicas' => 'required|integer|min:0',
        ]);

        // Validate that sum of tier days equals retention days
        $sumDays = $validated['hot_days'] + $validated['warm_days'] + $validated['cold_days'] + $validated['frozen_days'];
        if ($sumDays !== (int) $validated['retention_days']) {
            return redirect()->back()
                ->withInput()
                ->with('error', "The sum of tier days ({$sumDays}) must equal the total retention days ({$validated['retention_days']}).");
        }

        $validated['is_system_default'] = false;
        Scenario::create($validated);

        return redirect()->route('scenarios.index')
            ->with('success', 'Custom scenario template created.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Scenario $scenario)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'workload_profile' => 'required|string|in:min,avg,max',
            'retention_days' => 'required|integer|min:1',
            'hot_days' => 'required|integer|min:0',
            'warm_days' => 'required|integer|min:0',
            'cold_days' => 'required|integer|min:0',
            'frozen_days' => 'required|integer|min:0',
            'hot_replicas' => 'required|integer|min:0',
            'warm_replicas' => 'required|integer|min:0',
            'cold_replicas' => 'required|integer|min:0',
            'frozen_replicas' => 'required|integer|min:0',
        ]);

        // Validate sum of tier days
        $sumDays = $validated['hot_days'] + $validated['warm_days'] + $validated['cold_days'] + $validated['frozen_days'];
        if ($sumDays !== (int) $validated['retention_days']) {
            return redirect()->back()
                ->withInput()
                ->with('error', "The sum of tier days ({$sumDays}) must equal the total retention days ({$validated['retention_days']}).");
        }

        $scenario->update($validated);

        return redirect()->route('scenarios.index')
            ->with('success', 'Scenario template updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Scenario $scenario)
    {
        if ($scenario->is_system_default) {
            return redirect()->route('scenarios.index')
                ->with('error', 'Cannot delete system default scenarios.');
        }

        $scenario->delete();

        return redirect()->route('scenarios.index')
            ->with('success', 'Custom scenario template deleted.');
    }
}
