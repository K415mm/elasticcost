@extends('layouts.app')

@section('title', 'phpkaiharness Test Compare Suite')

@section('styles')
<style>
    .test-compare-header {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
    }
    .test-compare-header h1 {
        color: #e94560;
        font-size: 1.75rem;
        margin: 0;
    }
    .test-compare-header p {
        color: #a0a0b0;
        margin: 8px 0 0 0;
    }
    .analytics-card {
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 16px;
    }
    .analytics-card h3 {
        color: #e94560;
        font-size: 1rem;
        margin: 0 0 12px 0;
    }
    .insight-banner {
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 8px;
        font-size: 0.9rem;
    }
    .insight-positive { background: rgba(74,222,128,0.1); border-left: 3px solid #4ade80; }
    .insight-negative { background: rgba(248,113,113,0.1); border-left: 3px solid #f87171; }
    .insight-neutral  { background: rgba(96,165,250,0.1); border-left: 3px solid #60a5fa; }
    .delta-badge {
        font-size: 0.75rem;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 600;
    }
    .delta-up   { background: rgba(248,113,113,0.2); color: #f87171; }
    .delta-down { background: rgba(74,222,128,0.2); color: #4ade80; }
    .delta-flat { background: rgba(160,160,176,0.2); color: #a0a0b0; }
    .freshness-ok { color: #4ade80; }
    .freshness-warn { color: #fbbf24; }
    .freshness-bad { color: #f87171; }
    .mode-card {
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 16px;
        transition: border-color 0.2s;
    }
    .mode-card:hover {
        border-color: #e94560;
    }
    .mode-card h3 {
        color: #e94560;
        font-size: 1.1rem;
        margin: 0 0 8px 0;
    }
    .mode-card .badge {
        font-size: 0.75rem;
    }
    .metric-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .metric-row:last-child { border-bottom: none; }
    .metric-label { color: #a0a0b0; }
    .metric-value { color: #fff; font-weight: 600; }
    .metric-value.good { color: #4ade80; }
    .metric-value.bad { color: #f87171; }
    .progress-bar-container {
        background: rgba(255,255,255,0.1);
        border-radius: 8px;
        height: 24px;
        overflow: hidden;
        margin: 12px 0;
    }
    .progress-bar-fill {
        background: linear-gradient(90deg, #e94560, #0f3460);
        height: 100%;
        transition: width 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 0.8rem;
        font-weight: 600;
    }
    .dataset-table {
        font-size: 0.85rem;
    }
    .dataset-table .agent-badge {
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 4px;
    }
    .agent-badge.elastic { background: #0f3460; color: #a0d0ff; }
    .agent-badge.soc { background: #4a1942; color: #ff9ecf; }
    .lang-badge { font-size: 0.7rem; padding: 1px 6px; border-radius: 3px; }
    .lang-en { background: #1a472a; color: #4ade80; }
    .lang-fr { background: #1e3a5f; color: #60a5fa; }
    .lang-tn { background: #7c2d12; color: #fb923c; }
    .run-btn {
        background: linear-gradient(135deg, #e94560, #0f3460);
        border: none;
        padding: 12px 32px;
        border-radius: 8px;
        font-weight: 600;
        color: #fff;
        cursor: pointer;
        transition: transform 0.2s;
    }
    .run-btn:hover { transform: translateY(-2px); }
    .run-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
    .trace-detail {
        background: rgba(0,0,0,0.3);
        border-radius: 8px;
        padding: 16px;
        margin-top: 12px;
        font-family: monospace;
        font-size: 0.8rem;
        max-height: 400px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-all;
    }
    .report-content {
        background: rgba(255,255,255,0.03);
        border-radius: 8px;
        padding: 24px;
        max-height: 600px;
        overflow-y: auto;
    }
    .report-content h1 { color: #e94560; font-size: 1.5rem; }
    .report-content h2 { color: #e94560; font-size: 1.2rem; margin-top: 24px; }
    .report-content table { width: 100%; margin: 12px 0; }
    .report-content th { background: rgba(255,255,255,0.05); padding: 8px; }
    .report-content td { padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .report-content code { background: rgba(233,69,96,0.15); padding: 2px 6px; border-radius: 3px; }
    #runLog {
        background: rgba(0,0,0,0.4);
        border-radius: 8px;
        padding: 12px;
        max-height: 300px;
        overflow-y: auto;
        font-family: monospace;
        font-size: 0.8rem;
        color: #4ade80;
        display: none;
        margin-top: 16px;
    }
</style>
@endsection

@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">PHPKAIHARNESS TEST COMPARE</li>
</ul>

<div class="test-compare-header">
    <h1>phpkaiharness Test Compare Suite</h1>
    <p>A1 (Direct API) vs A2 (Loop, no features) vs B-cold (Full Harness, cold cache) vs B-warm (Full Harness, warm cache) — 17 requests × 4 modes = 68 executions</p>
    @if($runMeta ?? null)
    <p class="text-muted small mt-1">Run ID: <code>{{ $runMeta['run_id'] }}</code> | Started: {{ $runMeta['run_start'] }} | Ended: {{ $runMeta['run_end'] }}</p>
    @endif
</div>

@if($hasResults && !empty($traceFreshness))
<div class="row mb-3">
    <div class="col-md-12">
        <div class="analytics-card">
            <h3>📋 Trace Freshness & Integrity</h3>
            <div class="row">
                <div class="col-md-3">
                    <span class="text-muted">Total Traces:</span>
                    <span class="metric-value">{{ $traceFreshness['trace_count'] }}</span>
                </div>
                <div class="col-md-3">
                    <span class="text-muted">Single Run:</span>
                    @if($traceFreshness['is_single_run'])
                    <span class="freshness-ok">✓ Yes ({{ count($traceFreshness['unique_run_ids']) }} run ID)</span>
                    @else
                    <span class="freshness-bad">✗ No ({{ count($traceFreshness['unique_run_ids']) }} run IDs)</span>
                    @endif
                </div>
                <div class="col-md-3">
                    <span class="text-muted">Oldest:</span>
                    <span class="metric-value">{{ $traceFreshness['oldest_trace'] ?? 'N/A' }}</span>
                </div>
                <div class="col-md-3">
                    <span class="text-muted">Newest:</span>
                    <span class="metric-value">{{ $traceFreshness['newest_trace'] ?? 'N/A' }}</span>
                </div>
            </div>
            @if(!$traceFreshness['is_single_run'] && $traceFreshness['span_minutes'] > 30)
            <div class="insight-banner insight-negative mt-2">
                ⚠ Traces span {{ $traceFreshness['span_minutes'] }} minutes across multiple run IDs — data may be stale or mixed. Run a fresh test for accurate comparison.
            </div>
            @elseif($traceFreshness['span_minutes'] > 0)
            <div class="insight-banner insight-neutral mt-2">
                ℹ Traces span {{ $traceFreshness['span_minutes'] }} min — @if($traceFreshness['span_minutes'] < 60) consistent with a single run @else may include old data @endif
            </div>
            @endif
        </div>
    </div>
</div>
@endif

<div class="row">
    <div class="col-md-12">
        <div class="mode-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3>Test Execution</h3>
                    <p class="text-muted mb-0">Runs 68 executions (17 requests × 4 modes). This may take several minutes.</p>
                </div>
                <div>
                    <button id="runBtn" class="run-btn" onclick="runTests()">
                        <i class="icon-play"></i> Run Full Test Suite
                    </button>
                    <a href="{{ route('test-compare.index') }}" class="btn btn-outline-secondary btn-sm ms-2">Refresh Results</a>
                </div>
            </div>
            <div id="progressContainer" style="display:none; margin-top:16px;">
                <div class="progress-bar-container">
                    <div id="progressBar" class="progress-bar-fill" style="width:0%;">0%</div>
                </div>
                <div id="runLog"></div>
            </div>
        </div>
    </div>
</div>

@if($hasResults)
<div class="row mt-4">
    <div class="col-md-3">
        <div class="mode-card">
            <h3>A1 — Direct API <span class="badge bg-secondary">Baseline</span></h3>
            <p class="text-muted small">Raw Qwen Cloud API, no harness</p>
            @if(isset($summary['A1-direct-api']))
            <div class="metric-row"><span class="metric-label">Avg Latency</span><span class="metric-value">{{ $summary['A1-direct-api']['avg_latency_ms'] }}ms</span></div>
            <div class="metric-row"><span class="metric-label">Min / Max</span><span class="metric-value">{{ $summary['A1-direct-api']['min_latency_ms'] }} / {{ $summary['A1-direct-api']['max_latency_ms'] }}ms</span></div>
            <div class="metric-row"><span class="metric-label">Avg Tokens</span><span class="metric-value">{{ $summary['A1-direct-api']['avg_total_tokens'] }}</span></div>
            <div class="metric-row"><span class="metric-label">Tool Calls</span><span class="metric-value">0</span></div>
            <div class="metric-row"><span class="metric-label">Success Rate</span><span class="metric-value">{{ $summary['A1-direct-api']['successful'] }}/{{ $summary['A1-direct-api']['total_requests'] }}</span></div>
            @endif
        </div>
    </div>
    <div class="col-md-3">
        <div class="mode-card">
            <h3>A2 — Loop (no features) <span class="badge bg-info">Overhead</span></h3>
            <p class="text-muted small">AgentLoop with all features disabled</p>
            @if(isset($summary['A2-loop-no-features']))
            @php $a2VsA1 = $analytics['latency_comparison']['A2-loop-no-features']['vs_a1'] ?? null; @endphp
            <div class="metric-row"><span class="metric-label">Avg Latency</span><span class="metric-value">{{ $summary['A2-loop-no-features']['avg_latency_ms'] }}ms @if($a2VsA1 !== null)<span class="delta-badge {{ $a2VsA1 > 0 ? 'delta-up' : 'delta-down' }}">{{ $a2VsA1 > 0 ? '+' : '' }}{{ $a2VsA1 }}% vs A1</span>@endif</span></div>
            <div class="metric-row"><span class="metric-label">Min / Max</span><span class="metric-value">{{ $summary['A2-loop-no-features']['min_latency_ms'] }} / {{ $summary['A2-loop-no-features']['max_latency_ms'] }}ms</span></div>
            <div class="metric-row"><span class="metric-label">Avg Tokens</span><span class="metric-value">{{ $summary['A2-loop-no-features']['avg_total_tokens'] }}</span></div>
            <div class="metric-row"><span class="metric-label">Tool Calls</span><span class="metric-value">{{ $summary['A2-loop-no-features']['avg_tool_calls'] }}</span></div>
            <div class="metric-row"><span class="metric-label">Success Rate</span><span class="metric-value">{{ $summary['A2-loop-no-features']['successful'] }}/{{ $summary['A2-loop-no-features']['total_requests'] }}</span></div>
            @endif
        </div>
    </div>
    <div class="col-md-3">
        <div class="mode-card" style="border-color: #e94560;">
            <h3>B — Full Harness (Cold) <span class="badge bg-danger">Cold Cache</span></h3>
            <p class="text-muted small">All features enabled, first run</p>
            @if(isset($summary['B-full-harness']))
            @php $bcVsA1 = $analytics['latency_comparison']['B-full-harness']['vs_a1'] ?? null; @endphp
            <div class="metric-row"><span class="metric-label">Avg Latency</span><span class="metric-value">{{ $summary['B-full-harness']['avg_latency_ms'] }}ms @if($bcVsA1 !== null)<span class="delta-badge {{ $bcVsA1 > 0 ? 'delta-up' : 'delta-down' }}">{{ $bcVsA1 > 0 ? '+' : '' }}{{ $bcVsA1 }}% vs A1</span>@endif</span></div>
            <div class="metric-row"><span class="metric-label">Min / Max</span><span class="metric-value">{{ $summary['B-full-harness']['min_latency_ms'] }} / {{ $summary['B-full-harness']['max_latency_ms'] }}ms</span></div>
            <div class="metric-row"><span class="metric-label">Avg Tokens</span><span class="metric-value">{{ $summary['B-full-harness']['avg_total_tokens'] }}</span></div>
            <div class="metric-row"><span class="metric-label">Tool Calls</span><span class="metric-value good">{{ $summary['B-full-harness']['avg_tool_calls'] }}</span></div>
            <div class="metric-row"><span class="metric-label">Pipeline Stages</span><span class="metric-value good">{{ $summary['B-full-harness']['pipeline_stages_avg'] }}</span></div>
            <div class="metric-row"><span class="metric-label">Success Rate</span><span class="metric-value">{{ $summary['B-full-harness']['successful'] }}/{{ $summary['B-full-harness']['total_requests'] }}</span></div>
            @endif
        </div>
    </div>
    <div class="col-md-3">
        <div class="mode-card" style="border-color: #4ade80;">
            <h3>B — Full Harness (Warm) <span class="badge bg-success">Warm Cache</span></h3>
            <p class="text-muted small">Same as B-cold, but cache pre-warmed</p>
            @if(isset($summary['B-warm-harness']))
            @php $bwVsA1 = $analytics['latency_comparison']['B-warm-harness']['vs_a1'] ?? null; @endphp
            <div class="metric-row"><span class="metric-label">Avg Latency</span><span class="metric-value good">{{ $summary['B-warm-harness']['avg_latency_ms'] }}ms @if($bwVsA1 !== null)<span class="delta-badge {{ $bwVsA1 > 0 ? 'delta-up' : 'delta-down' }}">{{ $bwVsA1 > 0 ? '+' : '' }}{{ $bwVsA1 }}% vs A1</span>@endif</span></div>
            <div class="metric-row"><span class="metric-label">Min / Max</span><span class="metric-value">{{ $summary['B-warm-harness']['min_latency_ms'] }} / {{ $summary['B-warm-harness']['max_latency_ms'] }}ms</span></div>
            <div class="metric-row"><span class="metric-label">Avg Tokens</span><span class="metric-value">{{ $summary['B-warm-harness']['avg_total_tokens'] }}</span></div>
            <div class="metric-row"><span class="metric-label">Tool Calls</span><span class="metric-value good">{{ $summary['B-warm-harness']['avg_tool_calls'] }}</span></div>
            <div class="metric-row"><span class="metric-label">Pipeline Stages</span><span class="metric-value good">{{ $summary['B-warm-harness']['pipeline_stages_avg'] }}</span></div>
            <div class="metric-row"><span class="metric-label">Success Rate</span><span class="metric-value">{{ $summary['B-warm-harness']['successful'] }}/{{ $summary['B-warm-harness']['total_requests'] }}</span></div>
            @else
            <p class="text-muted small">No warm run yet. Run test suite to generate.</p>
            @endif
        </div>
    </div>
</div>
@endif

@if($hasResults && !empty($analytics['overhead_breakdown']))
<div class="row mt-2">
    <div class="col-md-6">
        <div class="analytics-card">
            <h3>🔍 Overhead Breakdown (A1 → A2 → B-cold → B-warm)</h3>
            @php $ob = $analytics['overhead_breakdown']; @endphp
            <div class="metric-row"><span class="metric-label">A1 Baseline Latency</span><span class="metric-value">{{ $ob['a1_baseline'] }}ms</span></div>
            <div class="metric-row"><span class="metric-label">A2 Loop Overhead</span><span class="metric-value">{{ $ob['a2_loop_overhead_ms'] > 0 ? '+' : '' }}{{ $ob['a2_loop_overhead_ms'] }}ms ({{ $ob['a2_loop_overhead_pct'] > 0 ? '+' : '' }}{{ $ob['a2_loop_overhead_pct'] }}%)</span></div>
            <div class="metric-row"><span class="metric-label">B-cold Harness Overhead</span><span class="metric-value">{{ $ob['b_cold_harness_overhead_ms'] > 0 ? '+' : '' }}{{ $ob['b_cold_harness_overhead_ms'] }}ms ({{ $ob['b_cold_harness_overhead_pct'] > 0 ? '+' : '' }}{{ $ob['b_cold_harness_overhead_pct'] }}%)</span></div>
            <div class="metric-row"><span class="metric-label">B-warm vs B-cold</span><span class="metric-value {{ $ob['b_warm_vs_cold_ms'] < 0 ? 'good' : '' }}">{{ $ob['b_warm_vs_cold_ms'] > 0 ? '+' : '' }}{{ $ob['b_warm_vs_cold_ms'] }}ms ({{ $ob['b_warm_vs_cold_pct'] > 0 ? '+' : '' }}{{ $ob['b_warm_vs_cold_pct'] }}%)</span></div>
            <div class="metric-row"><span class="metric-label">Total A1 → B-cold</span><span class="metric-value">{{ $ob['total_overhead_a1_to_b_cold_ms'] > 0 ? '+' : '' }}{{ $ob['total_overhead_a1_to_b_cold_ms'] }}ms ({{ $ob['total_overhead_a1_to_b_cold_pct'] > 0 ? '+' : '' }}{{ $ob['total_overhead_a1_to_b_cold_pct'] }}%)</span></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="analytics-card">
            <h3>⚡ Cache Impact (B-cold vs B-warm)</h3>
            @if(!empty($analytics['cache_impact']))
            @php $ci = $analytics['cache_impact']; @endphp
            <div class="metric-row"><span class="metric-label">Cold Avg Latency</span><span class="metric-value">{{ $ci['cold_avg_latency'] }}ms</span></div>
            <div class="metric-row"><span class="metric-label">Warm Avg Latency</span><span class="metric-value good">{{ $ci['warm_avg_latency'] }}ms</span></div>
            <div class="metric-row"><span class="metric-label">Latency Saved</span><span class="metric-value {{ $ci['latency_saved_ms'] > 0 ? 'good' : 'bad' }}">{{ $ci['latency_saved_ms'] }}ms ({{ $ci['latency_saved_pct'] }}%)</span></div>
            <div class="metric-row"><span class="metric-label">Cold Avg Tokens</span><span class="metric-value">{{ $ci['cold_avg_tokens'] }}</span></div>
            <div class="metric-row"><span class="metric-label">Warm Avg Tokens</span><span class="metric-value">{{ $ci['warm_avg_tokens'] }}</span></div>
            <div class="metric-row"><span class="metric-label">Token Delta</span><span class="metric-value">{{ $ci['token_delta'] > 0 ? '+' : '' }}{{ $ci['token_delta'] }}</span></div>
            @else
            <p class="text-muted small">No warm data available — run full test suite to generate.</p>
            @endif
        </div>
    </div>
</div>

<div class="row mt-2">
    <div class="col-md-12">
        <div class="analytics-card">
            <h3>📊 Key Insights</h3>
            @php $ob = $analytics['overhead_breakdown']; @endphp
            @if($ob['a2_loop_overhead_ms'] > 0)
            <div class="insight-banner insight-negative">AgentLoop overhead: +{{ $ob['a2_loop_overhead_ms'] }}ms ({{ $ob['a2_loop_overhead_pct'] }}%) — the loop itself adds latency even without features.</div>
            @elseif($ob['a2_loop_overhead_ms'] < 0)
            <div class="insight-banner insight-positive">AgentLoop is faster than direct API by {{ abs($ob['a2_loop_overhead_ms']) }}ms — the loop may batch requests more efficiently.</div>
            @endif
            @if($ob['b_cold_harness_overhead_ms'] > 0)
            <div class="insight-banner insight-neutral">Full harness adds +{{ $ob['b_cold_harness_overhead_ms'] }}ms ({{ $ob['b_cold_harness_overhead_pct'] }}%) over A2 — this is the cost of pipeline stages, RAG, cache, and guardrails.</div>
            @endif
            @if(!empty($analytics['cache_impact']) && $analytics['cache_impact']['latency_saved_ms'] > 0)
            <div class="insight-banner insight-positive">Warm cache saves {{ $analytics['cache_impact']['latency_saved_ms'] }}ms ({{ $analytics['cache_impact']['latency_saved_pct'] }}%) over cold cache — semantic cache is effective.</div>
            @elseif(!empty($analytics['cache_impact']) && $analytics['cache_impact']['latency_saved_ms'] <= 0)
            <div class="insight-banner insight-negative">Warm cache is NOT faster than cold ({{ $analytics['cache_impact']['latency_saved_ms'] }}ms) — cache may not be hitting or is adding overhead.</div>
            @endif
            @if(isset($summary['B-full-harness']) && $summary['B-full-harness']['failed'] > 0)
            <div class="insight-banner insight-negative">B-cold has {{ $summary['B-full-harness']['failed'] }} failed requests — investigate errors in trace details.</div>
            @endif
            @if(isset($summary['B-warm-harness']) && $summary['B-warm-harness']['failed'] > 0)
            <div class="insight-banner insight-negative">B-warm has {{ $summary['B-warm-harness']['failed'] }} failed requests — warm cache may not resolve all issues.</div>
            @endif
        </div>
    </div>
</div>

<div class="row mt-2">
    <div class="col-md-12">
        <div class="analytics-card">
            <h3>📈 Efficiency Ratios</h3>
            <table class="table table-sm">
                <thead>
                    <tr><th>Mode</th><th>Tokens/ms</th><th>Chars/ms</th><th>ms/Token</th></tr>
                </thead>
                <tbody>
                    @foreach(['A1-direct-api' => 'A1 (Direct API)', 'A2-loop-no-features' => 'A2 (Loop)', 'B-full-harness' => 'B-cold', 'B-warm-harness' => 'B-warm'] as $mode => $label)
                    @if(isset($analytics['efficiency_ratios'][$mode]))
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ $analytics['efficiency_ratios'][$mode]['tokens_per_ms'] }}</td>
                        <td>{{ $analytics['efficiency_ratios'][$mode]['chars_per_ms'] }}</td>
                        <td>{{ $analytics['efficiency_ratios'][$mode]['ms_per_token'] }}</td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="row mt-4">
    <div class="col-md-12">
        <div class="mode-card">
            <h3>Test Dataset ({{ count($dataset) }} requests)</h3>
            <table class="table table-sm dataset-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Agent</th>
                        <th>Category</th>
                        <th>Prompt</th>
                        <th>Lang</th>
                        <th>Tools?</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dataset as $i => $req)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            @if($req['agent'] === 'ElasticCostAssistant')
                            <span class="agent-badge elastic">ElasticCost</span>
                            @else
                            <span class="agent-badge soc">RG SOC</span>
                            @endif
                        </td>
                        <td><code>{{ $req['category'] }}</code></td>
                        <td>{{ Str::limit($req['prompt'], 80) }}</td>
                        <td>
                            @if(str_contains($req['category'], 'tunisian'))
                            <span class="lang-badge lang-tn">TN</span>
                            @elseif(str_contains($req['category'], 'french'))
                            <span class="lang-badge lang-fr">FR</span>
                            @else
                            <span class="lang-badge lang-en">EN</span>
                            @endif
                        </td>
                        <td>{{ $req['expects_tools'] ? 'Yes' : 'No' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($hasResults && !empty($analytics['per_request_deltas']))
<div class="row mt-4">
    <div class="col-md-12">
        <div class="mode-card">
            <h3>Per-Request Comparison (A1 vs A2 vs B-cold vs B-warm)</h3>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Agent</th>
                        <th>Category</th>
                        <th>A1 Lat</th>
                        <th>A2 Lat</th>
                        <th>B-cold Lat</th>
                        <th>B-warm Lat</th>
                        <th>A2 vs A1</th>
                        <th>B-cold vs A1</th>
                        <th>B-warm vs B-cold</th>
                        <th>A1 Tok</th>
                        <th>B-cold Tok</th>
                        <th>B-warm Tok</th>
                        <th>B-cold Tools</th>
                        <th>B-warm Tools</th>
                        <th>Cache</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($analytics['per_request_deltas'] as $row)
                    <tr>
                        <td>{{ $row['index'] + 1 }}</td>
                        <td>{{ $row['agent'] }}</td>
                        <td><code>{{ $row['category'] }}</code></td>
                        <td>{{ $row['a1_latency'] }}ms</td>
                        <td>{{ $row['a2_latency'] }}ms</td>
                        <td>{{ $row['b_cold_latency'] }}ms</td>
                        <td>{{ $row['b_warm_latency'] > 0 ? $row['b_warm_latency'].'ms' : '—' }}</td>
                        <td>@if($row['a2_vs_a1_pct'] !== null)<span class="delta-badge {{ $row['a2_vs_a1_pct'] > 0 ? 'delta-up' : 'delta-down' }}">{{ $row['a2_vs_a1_pct'] > 0 ? '+' : '' }}{{ $row['a2_vs_a1_pct'] }}%</span>@else — @endif</td>
                        <td>@if($row['b_cold_vs_a1_pct'] !== null)<span class="delta-badge {{ $row['b_cold_vs_a1_pct'] > 0 ? 'delta-up' : 'delta-down' }}">{{ $row['b_cold_vs_a1_pct'] > 0 ? '+' : '' }}{{ $row['b_cold_vs_a1_pct'] }}%</span>@else — @endif</td>
                        <td>@if($row['b_warm_vs_b_cold_pct'] !== null)<span class="delta-badge {{ $row['b_warm_vs_b_cold_pct'] > 0 ? 'delta-up' : 'delta-down' }}">{{ $row['b_warm_vs_b_cold_pct'] > 0 ? '+' : '' }}{{ $row['b_warm_vs_b_cold_pct'] }}%</span>@else — @endif</td>
                        <td>{{ $row['a1_tokens'] }}</td>
                        <td>{{ $row['b_cold_tokens'] }}</td>
                        <td>{{ $row['b_warm_tokens'] > 0 ? $row['b_warm_tokens'] : '—' }}</td>
                        <td>{{ $row['b_cold_tools'] }}</td>
                        <td>{{ $row['b_warm_tools'] > 0 ? $row['b_warm_tools'] : '—' }}</td>
                        <td>
                            @if($row['b_warm_cache_hit']) <span class="delta-badge delta-down">HIT</span>
                            @elseif($row['b_cold_cache_hit']) <span class="delta-badge delta-flat">COLD HIT</span>
                            @else <span class="text-muted">—</span> @endif
                        </td>
                        <td>
                            @if($row['a1_success'] && $row['a2_success'] && $row['b_cold_success'] && ($row['b_warm_success'] || $row['b_warm_latency'] === 0))
                            <span class="freshness-ok">✓</span>
                            @else
                            <span class="freshness-bad">✗</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@if($reportContent)
<div class="row mt-4">
    <div class="col-md-12">
        <div class="mode-card">
            <h3>Comparison Report</h3>
            <div class="report-content">
                {!! Str::markdown($reportContent) !!}
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@section('scripts')
<script>
    async function runTests() {
        const btn = document.getElementById('runBtn');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const runLog = document.getElementById('runLog');

        btn.disabled = true;
        btn.innerHTML = '<i class="icon-spinner icon-spin"></i> Running...';
        progressContainer.style.display = 'block';
        runLog.style.display = 'block';
        runLog.innerHTML = '';

        const log = (msg) => {
            runLog.innerHTML += msg + '\n';
            runLog.scrollTop = runLog.scrollHeight;
        };

        log('Starting test suite (background mode)...');

        try {
            log('Dispatching background test run...');
            const response = await fetch('{{ route("test-compare.run") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({})
            });

            if (response.status === 409) {
                const data = await response.json();
                throw new Error(data.message || 'A run is already in progress');
            }

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const data = await response.json();
            log('✓ Background process started (PID: ' + data.pid + ')');
            log('Polling for progress...');

            // Poll status every 5 seconds
            let lastLogLen = 0;
            let pollCount = 0;
            const maxPolls = 120; // 10 minutes max
            const pollInterval = setInterval(async () => {
                pollCount++;
                if (pollCount > maxPolls) {
                    clearInterval(pollInterval);
                    log('⚠ Polling timeout — check server logs. Reloading...');
                    setTimeout(() => location.reload(), 3000);
                    return;
                }
                try {
                    const statusResp = await fetch('{{ route("test-compare.status") }}', {
                        headers: { 'Accept': 'application/json' }
                    });
                    const status = await statusResp.json();

                    // Update progress bar based on trace counts
                    const total = 68; // 17 requests × 4 modes
                    const done = (status.trace_counts['A1-direct-api'] || 0)
                               + (status.trace_counts['A2-loop-no-features'] || 0)
                               + (status.trace_counts['B-full-harness'] || 0)
                               + (status.trace_counts['B-warm-harness'] || 0);
                    const pct = Math.min(Math.round((done / total) * 100), 99);
                    progressBar.style.width = pct + '%';
                    progressBar.innerText = pct + '% (' + done + '/' + total + ')';

                    // Show per-mode progress
                    const modes = ['A1-direct-api', 'A2-loop-no-features', 'B-full-harness', 'B-warm-harness'];
                    const modeLabels = {'A1-direct-api': 'A1', 'A2-loop-no-features': 'A2', 'B-full-harness': 'B-cold', 'B-warm-harness': 'B-warm'};
                    let progressStr = modes.map(m => modeLabels[m] + ':' + (status.trace_counts[m] || 0) + '/17').join(' | ');
                    log('Progress: ' + progressStr);

                    // Append new log lines
                    if (status.log && status.log.length > lastLogLen) {
                        const newLog = status.log.substring(lastLogLen);
                        lastLogLen = status.log.length;
                        const lines = newLog.split('\n').filter(l => l.trim());
                        lines.forEach(line => log(line));
                    }

                    // Check completion: process not running OR marker says DONE
                    if (!status.running || status.marker_done) {
                        clearInterval(pollInterval);
                        if (done < total && !status.marker_done) {
                            log('⚠ Process terminated early — only ' + done + '/68 traces generated.');
                            log('Check server logs for errors. Reloading to show partial results...');
                        } else {
                            progressBar.style.width = '100%';
                            progressBar.innerText = '100%';
                            log('✓ Test suite completed! (' + done + '/68 traces)');
                            log('Reloading page to show results...');
                        }
                        setTimeout(() => location.reload(), 3000);
                    }
                } catch (e) {
                    // Keep polling even if one request fails
                }
            }, 5000);

        } catch (error) {
            log('✗ Error: ' + error.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="icon-play"></i> Run Full Test Suite';
        }
    }
</script>
@endsection
