<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Diagram;
use App\Models\Scenario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DiagramController extends Controller
{
    /**
     * Display a listing of client diagrams.
     */
    public function index(Client $client, Request $request): View|JsonResponse
    {
        $query = $client->diagrams()->with(['scenario', 'creator']);

        if ($request->has('scenario_id')) {
            $query->where('scenario_id', $request->query('scenario_id'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }

        $diagrams = $query->orderBy('updated_at', 'desc')->get();

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $diagrams,
            ]);
        }

        $scenarios = Scenario::all();

        return view('clients.diagrams.index', compact('client', 'diagrams', 'scenarios'));
    }

    /**
     * Store a newly created diagram in storage.
     */
    public function store(Client $client, Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|in:soc_architecture,deployment_topology,network_diagram,custom',
            'scenario_id' => 'nullable|exists:scenarios,id',
            'content' => 'nullable|string',
            'thumbnail_svg' => 'nullable|string',
        ]);

        $diagram = $client->diagrams()->create([
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'custom',
            'scenario_id' => $validated['scenario_id'] ?? null,
            'content' => $validated['content'] ?? $this->getDefaultDrawioXml($validated['name']),
            'thumbnail_svg' => $validated['thumbnail_svg'] ?? null,
            'created_by' => Auth::id(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Diagram created successfully.',
                'data' => $diagram,
            ], 210); // 210 custom status or 201 Created
        }

        return redirect()->route('clients.diagrams.show', [$client->id, $diagram->id])
            ->with('success', 'Diagram created successfully.');
    }

    /**
     * Display the specified diagram for viewing/editing.
     */
    public function show(Client $client, Diagram $diagram, Request $request): View|JsonResponse
    {
        // Ensure the diagram belongs to the client
        if ($diagram->client_id !== $client->id) {
            abort(404);
        }

        $diagram->load(['scenario', 'creator']);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $diagram,
            ]);
        }

        return view('clients.diagrams.show', compact('client', 'diagram'));
    }

    /**
     * Update the specified diagram in storage.
     */
    public function update(Client $client, Diagram $diagram, Request $request): JsonResponse|RedirectResponse
    {
        if ($diagram->client_id !== $client->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'type' => 'nullable|string|in:soc_architecture,deployment_topology,network_diagram,custom',
            'scenario_id' => 'nullable|exists:scenarios,id',
            'content' => 'nullable|string',
            'thumbnail_svg' => 'nullable|string',
        ]);

        $diagram->update(array_filter([
            'name' => $validated['name'] ?? null,
            'type' => $validated['type'] ?? null,
            'scenario_id' => $validated['scenario_id'] ?? null,
            'content' => $validated['content'] ?? null,
            'thumbnail_svg' => $validated['thumbnail_svg'] ?? null,
        ], function ($val) {
            return ! is_null($val);
        }));

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Diagram updated successfully.',
                'data' => $diagram,
            ]);
        }

        return redirect()->route('clients.diagrams.show', [$client->id, $diagram->id])
            ->with('success', 'Diagram updated successfully.');
    }

    /**
     * Remove the specified diagram from storage.
     */
    public function destroy(Client $client, Diagram $diagram, Request $request): JsonResponse|RedirectResponse
    {
        if ($diagram->client_id !== $client->id) {
            abort(404);
        }

        $diagram->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Diagram deleted successfully.',
            ]);
        }

        return redirect()->route('clients.diagrams.index', $client->id)
            ->with('success', 'Diagram deleted successfully.');
    }

    /**
     * Generate default Draw.io XML structure.
     */
    private function getDefaultDrawioXml(string $name): string
    {
        $escapedName = htmlspecialchars($name, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return "<mxfile host=\"Embed\" modified=\"2026-07-15T00:00:00.000Z\" agent=\"ElasticCost App\" version=\"21.0.0\" type=\"embed\">
  <diagram id=\"default-id\" name=\"{$escapedName}\">
    <mxGraphModel dx=\"1000\" dy=\"1000\" grid=\"1\" gridSize=\"10\" guides=\"1\" tooltips=\"1\" connect=\"1\" arrows=\"1\" fold=\"1\" page=\"1\" pageScale=\"1\" pageWidth=\"827\" pageHeight=\"1169\" math=\"0\" shadow=\"0\">
      <root>
        <mxCell id=\"0\" />
        <mxCell id=\"1\" parent=\"0\" />
        <mxCell id=\"2\" value=\"{$escapedName}\" style=\"text;html=1;strokeColor=none;fillColor=none;align=center;verticalAlign=middle;whiteSpace=wrap;rounded=0;fontSize=18;fontStyle=1\" vertex=\"1\" parent=\"1\">
          <mxGeometry x=\"314\" y=\"40\" width=\"200\" height=\"30\" as=\"geometry\" />
        </mxCell>
      </root>
    </mxGraphModel>
  </diagram>
</mxfile>";
    }
}
