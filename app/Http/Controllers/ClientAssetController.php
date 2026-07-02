<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientAsset;
use Illuminate\Http\Request;

class ClientAssetController extends Controller
{
    /**
     * Store a newly created asset in the client's inventory.
     */
    public function store(Request $request, Client $client)
    {
        $validated = $request->validate([
            'asset_type_id' => 'required|exists:asset_types,id',
            'device_count' => 'required|integer|min:0',
        ]);

        // Avoid duplicates
        $exists = ClientAsset::where('client_id', $client->id)
            ->where('asset_type_id', $validated['asset_type_id'])
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', 'This asset type is already in the inventory.');
        }

        $client->clientAssets()->create($validated);

        return redirect()->route('clients.show', $client->id)
            ->with('success', 'Asset added to inventory.');
    }

    /**
     * Update the specified asset in the client's inventory.
     */
    public function update(Request $request, Client $client, ClientAsset $clientAsset)
    {
        $validated = $request->validate([
            'device_count' => 'required|integer|min:0',
            'custom_avg_event_size_bytes' => 'nullable|integer|min:1',
            'custom_min_eps' => 'nullable|numeric|min:0',
            'custom_avg_eps' => 'nullable|numeric|min:0',
            'custom_max_eps' => 'nullable|numeric|min:0',
            'custom_max_monthly_gb' => 'nullable|numeric|min:0',
            'runs_siem_agent' => 'nullable|boolean',
            'runs_mdr_agent' => 'nullable|boolean',
            'runs_edr_agent' => 'nullable|boolean',
        ]);

        $validated['runs_siem_agent'] = $request->has('runs_siem_agent');
        $validated['runs_mdr_agent'] = $request->has('runs_mdr_agent');
        $validated['runs_edr_agent'] = $request->has('runs_edr_agent');

        $clientAsset->update($validated);

        return redirect()->route('clients.show', $client->id)
            ->with('success', 'Inventory updated successfully.');
    }

    /**
     * Update all assets in the client's inventory at once.
     */
    public function updateBulk(Request $request, Client $client)
    {
        $request->validate([
            'assets' => 'required|array',
            'assets.*.device_count' => 'required|integer|min:0',
            'assets.*.custom_avg_event_size_bytes' => 'nullable|integer|min:1',
            'assets.*.custom_min_eps' => 'nullable|numeric|min:0',
            'assets.*.custom_avg_eps' => 'nullable|numeric|min:0',
            'assets.*.custom_max_eps' => 'nullable|numeric|min:0',
            'assets.*.custom_max_monthly_gb' => 'nullable|numeric|min:0',
        ]);

        $assetsInput = $request->input('assets', []);

        foreach ($assetsInput as $id => $data) {
            $clientAsset = ClientAsset::where('client_id', $client->id)->find($id);
            if ($clientAsset) {
                // Checkbox values are only submitted if checked
                $data['runs_siem_agent'] = isset($data['runs_siem_agent']);
                $data['runs_mdr_agent'] = isset($data['runs_mdr_agent']);
                $data['runs_edr_agent'] = isset($data['runs_edr_agent']);

                // Filter out empty custom overrides to set them as null
                $data['custom_avg_event_size_bytes'] = ! empty($data['custom_avg_event_size_bytes']) ? (int) $data['custom_avg_event_size_bytes'] : null;
                $data['custom_min_eps'] = (isset($data['custom_min_eps']) && $data['custom_min_eps'] !== '' && $data['custom_min_eps'] !== null) ? (float) $data['custom_min_eps'] : null;
                $data['custom_avg_eps'] = (isset($data['custom_avg_eps']) && $data['custom_avg_eps'] !== '' && $data['custom_avg_eps'] !== null) ? (float) $data['custom_avg_eps'] : null;
                $data['custom_max_eps'] = (isset($data['custom_max_eps']) && $data['custom_max_eps'] !== '' && $data['custom_max_eps'] !== null) ? (float) $data['custom_max_eps'] : null;
                $data['custom_max_monthly_gb'] = (isset($data['custom_max_monthly_gb']) && $data['custom_max_monthly_gb'] !== '' && $data['custom_max_monthly_gb'] !== null) ? (float) $data['custom_max_monthly_gb'] : null;

                $clientAsset->update($data);
            }
        }

        return redirect()->route('clients.show', $client->id)
            ->with('success', 'Inventory updated successfully.');
    }

    /**
     * Remove the specified asset from the client's inventory.
     */
    public function destroy(Client $client, ClientAsset $clientAsset)
    {
        $clientAsset->delete();

        return redirect()->route('clients.show', $client->id)
            ->with('success', 'Asset removed from inventory.');
    }
}
