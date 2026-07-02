<?php

namespace App\Http\Controllers;

use App\Models\HarnessSession;
use Illuminate\Support\Facades\DB;

class HarnessAnalyticsController extends Controller
{
    /**
     * Display the diagnostics dashboard.
     */
    public function index()
    {
        // Calculate aggregations
        $totalSessions = HarnessSession::count();
        $avgDuration = HarnessSession::avg('total_duration_ms') ?? 0;

        $totalPromptTokens = DB::table('harness_details')->where('type', 'llm_call')->sum('tokens_prompt') ?? 0;
        $totalCompletionTokens = DB::table('harness_details')->where('type', 'llm_call')->sum('tokens_completion') ?? 0;
        $totalTokens = $totalPromptTokens + $totalCompletionTokens;

        // Routing method stats
        $methodStats = HarnessSession::select('method', DB::raw('count(*) as count'))
            ->groupBy('method')
            ->pluck('count', 'method')
            ->toArray();

        // Tool calls stats
        $totalToolRuns = DB::table('harness_details')->where('type', 'tool_call')->count();

        // Latest sessions list
        $sessions = HarnessSession::with('details')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Calculate phpkaiharness benefits/savings
        // Fast-path bypass saves 1 model run.
        $fastPathCount = $methodStats['fast-path-keyword'] ?? 0;
        $classifierCount = ($methodStats['router-classified-action'] ?? 0) + ($methodStats['router-classified-chat'] ?? 0);
        $totalSavedModelRuns = $fastPathCount; // Fast path directly executes without classifier model run.

        return view('admin.harness_analytics', compact(
            'totalSessions',
            'avgDuration',
            'totalTokens',
            'totalPromptTokens',
            'totalCompletionTokens',
            'methodStats',
            'totalToolRuns',
            'sessions',
            'totalSavedModelRuns'
        ));
    }

    /**
     * Get granular steps details of a harness session in JSON format.
     */
    public function showDetails(string $id)
    {
        $session = HarnessSession::with(['details' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'session' => $session,
        ]);
    }
}
