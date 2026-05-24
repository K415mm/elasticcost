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
        ]);

        $clientAsset->update($validated);

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
