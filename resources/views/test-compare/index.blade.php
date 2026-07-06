@extends('layouts.app')

@section('title', 'phpkaiharness Test Compare Suite')

@section('styles')
<style>
/* ── Layout & Header ── */
.tc-header{background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%);border-radius:12px;padding:24px;margin-bottom:24px;}
.tc-header h1{color:#e94560;font-size:1.75rem;margin:0;}
.tc-header p{color:#a0a0b0;margin:8px 0 0;}
/* ── Cards ── */
.tc-card{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:16px;margin-bottom:16px;}
.tc-card h3{color:#e94560;font-size:1rem;margin:0 0 12px;}
.mode-card{background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:16px;margin-bottom:16px;transition:border-color .2s;}
.mode-card:hover{border-color:#e94560;}
.mode-card h3{color:#e94560;font-size:1.1rem;margin:0 0 8px;}
/* ── Metrics ── */
.metric-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);}
.metric-row:last-child{border-bottom:none;}
.metric-label{color:#a0a0b0;}
.metric-value{color:#fff;font-weight:600;}
.metric-value.good{color:#4ade80;}
.metric-value.bad{color:#f87171;}
.metric-value.warn{color:#fbbf24;}
/* ── Badges & Deltas ── */
.delta-badge{font-size:.75rem;padding:2px 6px;border-radius:4px;font-weight:600;}
.delta-up{background:rgba(248,113,113,.2);color:#f87171;}
.delta-down{background:rgba(74,222,128,.2);color:#4ade80;}
.delta-flat{background:rgba(160,160,176,.2);color:#a0a0b0;}
/* ── Insights ── */
.insight{border-radius:8px;padding:10px 14px;margin-bottom:6px;font-size:.875rem;}
.insight-pos{background:rgba(74,222,128,.08);border-left:3px solid #4ade80;}
.insight-neg{background:rgba(248,113,113,.08);border-left:3px solid #f87171;}
.insight-neu{background:rgba(96,165,250,.08);border-left:3px solid #60a5fa;}
/* ── AI Score ── */
.ai-score{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;font-weight:700;font-size:.9rem;}
.ai-score-high{background:rgba(74,222,128,.2);color:#4ade80;border:2px solid #4ade80;}
.ai-score-mid{background:rgba(251,191,36,.2);color:#fbbf24;border:2px solid #fbbf24;}
.ai-score-low{background:rgba(248,113,113,.2);color:#f87171;border:2px solid #f87171;}
.winner-crown{font-size:1rem;}
/* ── Progress ── */
.prog-wrap{background:rgba(255,255,255,.1);border-radius:8px;height:24px;overflow:hidden;margin:12px 0;}
.prog-fill{background:linear-gradient(90deg,#e94560,#0f3460);height:100%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.8rem;font-weight:600;transition:width .3s;}
/* ── Stage indicators ── */
.stage-ind{display:flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;font-size:.85rem;font-weight:600;border:2px solid transparent;transition:all .3s;}
.stage-pending{background:rgba(255,255,255,.05);color:#666;border-color:rgba(255,255,255,.1);}
.stage-running{background:rgba(233,69,96,.2);color:#e94560;border-color:#e94560;animation:pulse 1.5s infinite;}
.stage-done{background:rgba(74,222,128,.15);color:#4ade80;border-color:#4ade80;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.6}}
/* ── Run log ── */
#runLog{background:rgba(0,0,0,.4);border-radius:8px;padding:12px;max-height:300px;overflow-y:auto;font-family:monospace;font-size:.8rem;color:#4ade80;display:none;margin-top:16px;}
/* ── Tables ── */
.tc-table{font-size:.82rem;width:100%;}
.tc-table th{background:rgba(255,255,255,.05);padding:8px 6px;white-space:nowrap;}
.tc-table td{padding:7px 6px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;}
/* ── Expandable rows ── */
.expand-row{background:rgba(0,0,0,.25);font-size:.8rem;}
.response-preview{background:rgba(255,255,255,.03);border-radius:6px;padding:10px;font-size:.78rem;color:#d0d0e0;max-height:120px;overflow-y:auto;white-space:pre-wrap;word-break:break-word;border-left:3px solid rgba(255,255,255,.1);}
/* ── Freshness ── */
.freshness-ok{color:#4ade80;} .freshness-warn{color:#fbbf24;} .freshness-bad{color:#f87171;}
/* ── Dataset ── */
.agent-badge.elastic{background:#0f3460;color:#a0d0ff;} .agent-badge.soc{background:#4a1942;color:#ff9ecf;}
.agent-badge{font-size:.7rem;padding:2px 8px;border-radius:4px;}
.lang-badge{font-size:.7rem;padding:1px 6px;border-radius:3px;}
.lang-en{background:#1a472a;color:#4ade80;} .lang-fr{background:#1e3a5f;color:#60a5fa;} .lang-tn{background:#7c2d12;color:#fb923c;}
/* ── Run history ── */
.run-hist{display:flex;justify-content:space-between;align-items:center;padding:7px 12px;border-radius:6px;margin-bottom:4px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.05);}
/* ── Report ── */
.report-content{background:rgba(255,255,255,.03);border-radius:8px;padding:24px;max-height:600px;overflow-y:auto;}
.report-content h1{color:#e94560;font-size:1.5rem;}
.report-content h2{color:#e94560;font-size:1.2rem;margin-top:24px;}
.report-content table{width:100%;margin:12px 0;}
.report-content th{background:rgba(255,255,255,.05);padding:8px;}
.report-content td{padding:8px;border-bottom:1px solid rgba(255,255,255,.05);}
/* ── Buttons ── */
.run-btn{background:linear-gradient(135deg,#e94560,#0f3460);border:none;padding:12px 32px;border-radius:8px;font-weight:600;color:#fff;cursor:pointer;transition:transform .2s;}
.run-btn:hover{transform:translateY(-2px);}
.run-btn:disabled{opacity:.5;cursor:not-allowed;transform:none;}
</style>
@endsection

@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">PHPKAIHARNESS TEST COMPARE</li>
</ul>

{{-- ── Header ── --}}
<div class="tc-header">
    <h1>phpkaiharness Test Compare Suite</h1>
    <p>A1 (Direct API) vs A2 (Loop, no features) vs B-cold (Full Harness) vs B-warm (Warm Cache) — 20 requests × 4 modes = 80 executions</p>
    @if($runMeta ?? null)
    <p class="text-muted small mt-1">
        Run ID: <code>{{ $runMeta['run_id'] }}</code>
        @if($runMeta['run_start']) | Started: {{ $runMeta['run_start'] }} @endif
        @if($runMeta['run_end'])   | Ended: {{ $runMeta['run_end'] }} @endif
        @if($runMeta['in_progress'] ?? false) <span class="badge bg-warning text-dark ms-1">IN PROGRESS</span> @endif
    </p>
    @endif
</div>

{{-- ── Run History ── --}}
@if(!empty($availableRuns))
<div class="tc-card mb-3">
    <h3>📋 Run History</h3>
    @foreach($availableRuns as $run)
    <div class="run-hist">
        <span style="font-family:monospace;font-size:.8rem;color:#a0a0b0;">{{ $run['id'] }}</span>
        <span>{{ $run['date'] }}</span>
        <span style="font-size:.8rem;color:#666;">{{ $run['trace_count'] }} traces @if($run['has_summary'])<span class="text-success">✓ summary</span>@endif</span>
    </div>
    @endforeach
</div>
@endif

{{-- ── Trace Freshness ── --}}
@if($hasResults && !empty($traceFreshness))
<div class="tc-card mb-3">
    <h3>📋 Trace Integrity</h3>
    <div class="row">
        <div class="col-md-3">
            <span class="text-muted">Total Traces:</span>
            <span class="metric-value ms-2">{{ $traceFreshness['trace_count'] }}</span>
        </div>
        <div class="col-md-3">
            <span class="text-muted">Single Run:</span>
            @if($traceFreshness['is_single_run'])
            <span class="freshness-ok ms-2">✓ Yes</span>
            @else
            <span class="freshness-bad ms-2">✗ Mixed ({{ count($traceFreshness['unique_run_ids']) }} IDs)</span>
            @endif
        </div>
        <div class="col-md-3">
            <span class="text-muted">Oldest:</span>
            <span class="metric-value ms-2">{{ $traceFreshness['oldest_trace'] ?? 'N/A' }}</span>
        </div>
        <div class="col-md-3">
            <span class="text-muted">Newest:</span>
            <span class="metric-value ms-2">{{ $traceFreshness['newest_trace'] ?? 'N/A' }}</span>
        </div>
    </div>
    @if($traceFreshness['span_minutes'] > 60)
    <div class="insight insight-neg mt-2">⚠ Traces span {{ $traceFreshness['span_minutes'] }} min across multiple run IDs — run a fresh test.</div>
    @endif
</div>
@endif

{{-- ── Run Control ── --}}
<div class="mode-card">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3>Test Execution</h3>
            <p class="text-muted mb-0">80 executions (20 requests × 4 modes). Includes AI evaluation of every response.</p>
        </div>
        <div>
            <button id="runBtn" class="run-btn" onclick="runTests()">▶ Run Full Test Suite</button>
            <button class="btn btn-outline-success btn-sm ms-2" onclick="openSaveRunModal()">💾 Save This Run</button>
            <button class="btn btn-outline-danger btn-sm ms-2" onclick="purgeOldRuns()">🗑 Purge Old Runs</button>
            <a href="{{ route('test-compare.index') }}" class="btn btn-outline-secondary btn-sm ms-2">↻ Refresh</a>
        </div>
    </div>
    <div id="progressContainer" style="display:none;margin-top:16px;">
        <div class="prog-wrap"><div id="progressBar" class="prog-fill" style="width:0%;">0%</div></div>
        <div style="display:flex;gap:8px;margin:12px 0;flex-wrap:wrap;">
            <div id="stage-A1" class="stage-ind stage-pending"><span class="stage-icon">○</span> A1 <span class="stage-count">0/20</span></div>
            <div id="stage-A2" class="stage-ind stage-pending"><span class="stage-icon">○</span> A2 <span class="stage-count">0/20</span></div>
            <div id="stage-B-cold" class="stage-ind stage-pending"><span class="stage-icon">○</span> B-cold <span class="stage-count">0/20</span></div>
            <div id="stage-B-warm" class="stage-ind stage-pending"><span class="stage-icon">○</span> B-warm <span class="stage-count">0/20</span></div>
            <div id="stage-eval" class="stage-ind stage-pending"><span class="stage-icon">○</span> AI Eval <span class="stage-count"></span></div>
        </div>
        <div id="runLog"></div>
    </div>
</div>

@if($hasResults)

{{-- ── Mode Summary Cards ── --}}
<div class="row mt-4">
    @php
    $modeCards = [
        'A1-direct-api'       => ['label'=>'A1 — Direct API',       'badge'=>'Baseline',  'color'=>'secondary', 'border'=>''],
        'A2-loop-no-features' => ['label'=>'A2 — Loop (no features)','badge'=>'Overhead',  'color'=>'info',      'border'=>''],
        'B-full-harness'      => ['label'=>'B — Full Harness (Cold)','badge'=>'Cold Cache','color'=>'danger',    'border'=>'border-color:#e94560;'],
        'B-warm-harness'      => ['label'=>'B — Full Harness (Warm)','badge'=>'Warm Cache','color'=>'success',   'border'=>'border-color:#4ade80;'],
    ];
    $a1Avg = $summary['A1-direct-api']['avg_latency_ms'] ?? 1;
    @endphp
    @foreach($modeCards as $modeKey => $mc)
    <div class="col-md-3">
        <div class="mode-card" style="{{ $mc['border'] }}">
            <h3>{{ $mc['label'] }} <span class="badge bg-{{ $mc['color'] }}">{{ $mc['badge'] }}</span></h3>
            @if(isset($summary[$modeKey]))
            @php
                $s = $summary[$modeKey];
                $vsA1 = $analytics['latency_comparison'][$modeKey]['vs_a1'] ?? null;
                $aiScore = $s['avg_ai_score'] ?? null;
                $aiWins = $s['ai_win_count'] ?? 0;
            @endphp
            <div class="metric-row">
                <span class="metric-label">Avg Latency</span>
                <span class="metric-value">
                    {{ number_format($s['avg_latency_ms']) }}ms
                    @if($vsA1 !== null)
                    <span class="delta-badge {{ $vsA1 > 0 ? 'delta-up' : 'delta-down' }}">{{ $vsA1 > 0 ? '+' : '' }}{{ $vsA1 }}%</span>
                    @endif
                </span>
            </div>
            <div class="metric-row">
                <span class="metric-label">Min / Max</span>
                <span class="metric-value">{{ number_format($s['min_latency_ms']) }} / {{ number_format($s['max_latency_ms']) }}ms</span>
            </div>
            <div class="metric-row">
                <span class="metric-label">Avg Tokens</span>
                <span class="metric-value">{{ number_format($s['avg_total_tokens']) }}</span>
            </div>
            <div class="metric-row">
                <span class="metric-label">Tool Calls</span>
                <span class="metric-value">{{ $s['avg_tool_calls'] }}</span>
            </div>
            @if(isset($s['pipeline_stages_avg']) && $modeKey !== 'A1-direct-api' && $modeKey !== 'A2-loop-no-features')
            <div class="metric-row">
                <span class="metric-label">Pipeline Stages</span>
                <span class="metric-value good">{{ $s['pipeline_stages_avg'] }}</span>
            </div>
            @endif
            <div class="metric-row">
                <span class="metric-label">Success</span>
                <span class="metric-value {{ $s['failed'] > 0 ? 'bad' : 'good' }}">{{ $s['successful'] }}/{{ $s['total_requests'] }}</span>
            </div>
            @if($aiScore !== null)
            <div class="metric-row">
                <span class="metric-label">AI Score (avg)</span>
                <span class="metric-value {{ $aiScore >= 7 ? 'good' : ($aiScore >= 5 ? 'warn' : 'bad') }}">{{ $aiScore }}/10 <small class="text-muted">({{ $aiWins }} wins)</small></span>
            </div>
            @endif
            @else
            <p class="text-muted small">No data yet.</p>
            @endif
        </div>
    </div>
    @endforeach
</div>

{{-- ── AI Evaluation Summary ── --}}
@if(!empty($analytics['ai_evaluation_summary']))
<div class="row mt-2">
    <div class="col-md-12">
        <div class="tc-card">
            <h3>🤖 AI Evaluation Summary (LLM Judge — Accuracy · Completeness · Relevance · Quality)</h3>
            <div class="row g-3">
                @foreach(['A1-direct-api'=>'A1 Direct API','A2-loop-no-features'=>'A2 Loop','B-full-harness'=>'B-cold','B-warm-harness'=>'B-warm'] as $mk => $ml)
                @if(isset($analytics['ai_evaluation_summary'][$mk]))
                @php $ae = $analytics['ai_evaluation_summary'][$mk]; @endphp
                <div class="col-md-3">
                    <div style="background:rgba(255,255,255,.03);border-radius:8px;padding:14px;text-align:center;">
                        <div class="text-muted small mb-2">{{ $ml }}</div>
                        @php $sc = $ae['avg_score']; @endphp
                        <div class="ai-score {{ $sc >= 7 ? 'ai-score-high' : ($sc >= 5 ? 'ai-score-mid' : 'ai-score-low') }}" style="margin:0 auto 10px;">{{ $sc }}</div>
                        <div class="text-muted small">{{ $ae['min_score'] }}–{{ $ae['max_score'] }} range</div>
                        <div class="mt-1">
                            <span class="badge {{ $ae['win_pct'] >= 40 ? 'bg-success' : 'bg-secondary' }}">🏆 {{ $ae['win_count'] }} wins ({{ $ae['win_pct'] }}%)</span>
                        </div>
                    </div>
                </div>
                @endif
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Overhead Breakdown & Cache Impact ── --}}
@if(!empty($analytics['overhead_breakdown']))
<div class="row mt-2">
    <div class="col-md-6">
        <div class="tc-card">
            <h3>🔍 Overhead Breakdown</h3>
            @php $ob = $analytics['overhead_breakdown']; @endphp
            <div class="metric-row"><span class="metric-label">A1 Baseline</span><span class="metric-value">{{ number_format($ob['a1_baseline']) }}ms</span></div>
            <div class="metric-row"><span class="metric-label">A2 Loop Overhead</span><span class="metric-value {{ $ob['a2_loop_overhead_ms'] > 0 ? 'bad' : 'good' }}">{{ $ob['a2_loop_overhead_ms'] > 0 ? '+' : '' }}{{ number_format($ob['a2_loop_overhead_ms']) }}ms ({{ $ob['a2_loop_overhead_pct'] > 0 ? '+' : '' }}{{ $ob['a2_loop_overhead_pct'] }}%)</span></div>
            <div class="metric-row"><span class="metric-label">B-cold Harness</span><span class="metric-value bad">{{ $ob['b_cold_harness_overhead_ms'] > 0 ? '+' : '' }}{{ number_format($ob['b_cold_harness_overhead_ms']) }}ms ({{ $ob['b_cold_harness_overhead_pct'] > 0 ? '+' : '' }}{{ $ob['b_cold_harness_overhead_pct'] }}%)</span></div>
            <div class="metric-row"><span class="metric-label">B-warm vs B-cold</span><span class="metric-value {{ $ob['b_warm_vs_cold_ms'] < 0 ? 'good' : 'bad' }}">{{ $ob['b_warm_vs_cold_ms'] > 0 ? '+' : '' }}{{ number_format($ob['b_warm_vs_cold_ms']) }}ms ({{ $ob['b_warm_vs_cold_pct'] > 0 ? '+' : '' }}{{ $ob['b_warm_vs_cold_pct'] }}%)</span></div>
            <div class="metric-row"><span class="metric-label">Total A1 → B-cold</span><span class="metric-value">{{ $ob['total_overhead_a1_to_b_cold_ms'] > 0 ? '+' : '' }}{{ number_format($ob['total_overhead_a1_to_b_cold_ms']) }}ms ({{ $ob['total_overhead_a1_to_b_cold_pct'] > 0 ? '+' : '' }}{{ $ob['total_overhead_a1_to_b_cold_pct'] }}%)</span></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="tc-card">
            <h3>⚡ Cache Impact (B-cold vs B-warm)</h3>
            @if(!empty($analytics['cache_impact']))
            @php $ci = $analytics['cache_impact']; @endphp
            <div class="metric-row"><span class="metric-label">Cold Avg Latency</span><span class="metric-value">{{ number_format($ci['cold_avg_latency']) }}ms</span></div>
            <div class="metric-row"><span class="metric-label">Warm Avg Latency</span><span class="metric-value good">{{ number_format($ci['warm_avg_latency']) }}ms</span></div>
            <div class="metric-row"><span class="metric-label">Latency Saved</span><span class="metric-value {{ $ci['latency_saved_ms'] > 0 ? 'good' : 'bad' }}">{{ number_format($ci['latency_saved_ms']) }}ms ({{ $ci['latency_saved_pct'] }}%)</span></div>
            <div class="metric-row"><span class="metric-label">Cold / Warm Tokens</span><span class="metric-value">{{ $ci['cold_avg_tokens'] }} / {{ $ci['warm_avg_tokens'] }}</span></div>
            <div class="metric-row"><span class="metric-label">Token Delta</span><span class="metric-value">{{ $ci['token_delta'] > 0 ? '+' : '' }}{{ $ci['token_delta'] }}</span></div>
            <div class="metric-row"><span class="metric-label">Warm Cache Hits</span><span class="metric-value {{ ($analytics['features_matrix']['semantic_cache']['warm_hits'] ?? 0) > 0 ? 'good' : 'bad' }}">{{ $analytics['features_matrix']['semantic_cache']['warm_hits'] ?? 0 }}/20 ({{ $analytics['features_matrix']['semantic_cache']['warm_hit_pct'] ?? 0 }}%)</span></div>
            @else
            <p class="text-muted small">Run full test suite to generate warm data.</p>
            @endif
        </div>
    </div>
</div>

{{-- ── Key Insights ── --}}
<div class="tc-card mt-2">
    <h3>💡 Key Insights</h3>
    @php $ob = $analytics['overhead_breakdown']; @endphp
    @if($ob['a2_loop_overhead_ms'] > 0)
    <div class="insight insight-neg">AgentLoop overhead: +{{ number_format($ob['a2_loop_overhead_ms']) }}ms ({{ $ob['a2_loop_overhead_pct'] }}%) — the loop itself adds latency even without features.</div>
    @elseif($ob['a2_loop_overhead_ms'] < 0)
    <div class="insight insight-pos">AgentLoop is faster than direct API by {{ number_format(abs($ob['a2_loop_overhead_ms'])) }}ms — may batch more efficiently.</div>
    @endif
    @if($ob['b_cold_harness_overhead_ms'] > 0)
    <div class="insight insight-neu">Full harness adds +{{ number_format($ob['b_cold_harness_overhead_ms']) }}ms ({{ $ob['b_cold_harness_overhead_pct'] }}%) over A2 — cost of pipeline, RAG, cache, guardrails.</div>
    @endif
    @if(!empty($analytics['cache_impact']))
    @if($analytics['cache_impact']['latency_saved_ms'] > 0)
    <div class="insight insight-pos">Warm cache saves {{ number_format($analytics['cache_impact']['latency_saved_ms']) }}ms ({{ $analytics['cache_impact']['latency_saved_pct'] }}%) — semantic cache is effective.</div>
    @else
    <div class="insight insight-neg">Warm cache NOT faster than cold ({{ number_format($analytics['cache_impact']['latency_saved_ms']) }}ms delta) — cache may not be hitting.</div>
    @endif
    @endif
    @if(!empty($analytics['ai_evaluation_summary']))
    @php
        $bestMode = collect($analytics['ai_evaluation_summary'])->sortByDesc('avg_score')->keys()->first();
        $bestLabel = ['A1-direct-api'=>'A1 Direct API','A2-loop-no-features'=>'A2 Loop','B-full-harness'=>'B-cold','B-warm-harness'=>'B-warm'][$bestMode] ?? $bestMode;
        $bestScore = $analytics['ai_evaluation_summary'][$bestMode]['avg_score'] ?? 0;
    @endphp
    <div class="insight insight-pos">🏆 Best response quality: <strong>{{ $bestLabel }}</strong> with avg AI score {{ $bestScore }}/10 — AI judge ranked this mode's responses highest.</div>
    @endif
</div>
@endif

{{-- ── phpkaiharness Feature Matrix ── --}}
@if(!empty($analytics['features_matrix']))
<div class="tc-card mt-2" style="border-color:rgba(74,222,128,.25);">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 style="color:#4ade80;margin:0;">🔮 phpkaiharness Feature Evaluation Matrix</h3>
            <p class="text-muted small mb-0">Measured from live B-mode trace data (cold + warm runs)</p>
        </div>
        <span class="badge bg-success">7 FEATURES</span>
    </div>
    @php $fm = $analytics['features_matrix']; @endphp
    <div class="row g-3">
        <div class="col-md-4">
            <div class="mode-card p-3 h-100 mb-0">
                <div class="d-flex justify-content-between"><h5 style="color:#60a5fa;font-size:.95rem;margin:0;">⚡ Draft Verification</h5><span class="badge bg-primary">Active</span></div>
                <p class="text-muted small mb-2 mt-1">Speculative draft proposals verified by fast model passes</p>
                <div class="metric-row"><span class="metric-label">Proposals Generated</span><span class="metric-value">{{ $fm['draft_verification']['executed_count'] }}</span></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mode-card p-3 h-100 mb-0">
                <div class="d-flex justify-content-between"><h5 style="color:#c084fc;font-size:.95rem;margin:0;">📚 Ontology RAG</h5><span class="badge" style="background:#9333ea;color:#fff;">SQLite</span></div>
                <p class="text-muted small mb-2 mt-1">Semantic document chunk injection from SQLite</p>
                <div class="metric-row"><span class="metric-label">Requests w/ Injection</span><span class="metric-value">{{ $fm['ontology_rag']['executed_count'] }}</span></div>
                <div class="metric-row"><span class="metric-label">Total Chunks Injected</span><span class="metric-value">{{ $fm['ontology_rag']['total_chunks_injected'] }}</span></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mode-card p-3 h-100 mb-0">
                <div class="d-flex justify-content-between"><h5 style="color:#4ade80;font-size:.95rem;margin:0;">🎯 Semantic Cache</h5><span class="badge bg-success">Cross-Session</span></div>
                <p class="text-muted small mb-2 mt-1">Cross-session exact & fuzzy prompt matching</p>
                <div class="metric-row"><span class="metric-label">Cold Hits / Warm Hits</span><span class="metric-value">{{ $fm['semantic_cache']['cold_hits'] }} / {{ $fm['semantic_cache']['warm_hits'] }}</span></div>
                <div class="metric-row"><span class="metric-label">Warm Hit Rate</span><span class="metric-value {{ $fm['semantic_cache']['warm_hit_pct'] > 0 ? 'good' : 'bad' }}">{{ $fm['semantic_cache']['warm_hit_pct'] }}%</span></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mode-card p-3 h-100 mb-0">
                <div class="d-flex justify-content-between"><h5 style="color:#facc15;font-size:.95rem;margin:0;">⚛️ Quantum Memory</h5><span class="badge bg-warning text-dark">Superposition</span></div>
                <p class="text-muted small mb-2 mt-1">Multi-hop entanglement traversal & phase superposition</p>
                <div class="metric-row"><span class="metric-label">Total Nodes Retrieved</span><span class="metric-value">{{ $fm['quantum_memory']['total_nodes_retrieved'] }}</span></div>
                <div class="metric-row"><span class="metric-label">Avg per Request</span><span class="metric-value">{{ $fm['quantum_memory']['avg_nodes_per_request'] }}</span></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mode-card p-3 h-100 mb-0">
                <div class="d-flex justify-content-between"><h5 style="color:#fb923c;font-size:.95rem;margin:0;">🗜️ Context Compression</h5><span class="badge" style="background:#ea580c;color:#fff;">Middleware</span></div>
                <p class="text-muted small mb-2 mt-1">Prompt middleware noise stripping & token reduction</p>
                <div class="metric-row"><span class="metric-label">Requests Processed</span><span class="metric-value">{{ $fm['context_compression']['executed_count'] }}</span></div>
                <div class="metric-row"><span class="metric-label">Avg Prompt Tokens</span><span class="metric-value">{{ $fm['context_compression']['avg_prompt_tokens'] }}</span></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mode-card p-3 h-100 mb-0">
                <div class="d-flex justify-content-between"><h5 style="color:#38bdf8;font-size:.95rem;margin:0;">🔄 Context Compactor</h5><span class="badge bg-info">Sliding Window</span></div>
                <p class="text-muted small mb-2 mt-1">Sliding window compaction on multi-turn history</p>
                <div class="metric-row"><span class="metric-label">Total Iterations</span><span class="metric-value">{{ $fm['compaction']['compacted_turns'] }}</span></div>
                <div class="metric-row"><span class="metric-label">Avg Iterations/Req</span><span class="metric-value">{{ $fm['compaction']['avg_iterations'] }}</span></div>
            </div>
        </div>
        <div class="col-md-12">
            <div class="mode-card p-3 mb-0">
                <div class="d-flex justify-content-between"><h5 style="color:#ec4899;font-size:.95rem;margin:0;">🕸️ Cognitive Graph Memory</h5><span class="badge" style="background:#db2777;color:#fff;">QueryGraphMemoryTool</span></div>
                <p class="text-muted small mb-2 mt-1">Persistent entity relationships retrieved dynamically via harness_facts</p>
                <div class="metric-row"><span class="metric-label">Graph Memory Queries</span><span class="metric-value">{{ $fm['cognitive_graph_memory']['facts_queried'] }}</span></div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Efficiency Ratios ── --}}
@if(!empty($analytics['efficiency_ratios']))
<div class="tc-card mt-2">
    <h3>📈 Efficiency Ratios</h3>
    <table class="tc-table">
        <thead><tr><th>Mode</th><th>Tokens/ms</th><th>Chars/ms</th><th>ms/Token</th><th>Avg AI Score</th><th>AI Wins</th></tr></thead>
        <tbody>
        @foreach(['A1-direct-api'=>'A1 Direct API','A2-loop-no-features'=>'A2 Loop','B-full-harness'=>'B-cold','B-warm-harness'=>'B-warm'] as $mk => $ml)
        @if(isset($analytics['efficiency_ratios'][$mk]))
        @php
            $er = $analytics['efficiency_ratios'][$mk];
            $ae = $analytics['ai_evaluation_summary'][$mk] ?? null;
        @endphp
        <tr>
            <td><strong>{{ $ml }}</strong></td>
            <td>{{ $er['tokens_per_ms'] }}</td>
            <td>{{ $er['chars_per_ms'] }}</td>
            <td>{{ $er['ms_per_token'] }}</td>
            <td>@if($ae)<span class="{{ $ae['avg_score'] >= 7 ? 'text-success' : ($ae['avg_score'] >= 5 ? 'text-warning' : 'text-danger') }}">{{ $ae['avg_score'] }}/10</span>@else —@endif</td>
            <td>@if($ae)<span class="badge bg-secondary">{{ $ae['win_count'] }} ({{ $ae['win_pct'] }}%)</span>@else —@endif</td>
        </tr>
        @endif
        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ── Per-Request Comparison Table ── --}}
@if(!empty($analytics['per_request_deltas']))
<div class="mode-card mt-4">
    <h3>Per-Request Comparison — All 20 Requests</h3>
    <p class="text-muted small mb-3">Click any row to expand response previews and AI evaluation details.</p>
    <div style="overflow-x:auto;">
    <table class="tc-table">
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
                <th>Cache</th>
                <th>🏆 Winner</th>
                <th>A1 Score</th>
                <th>A2 Score</th>
                <th>B-cold Score</th>
                <th>B-warm Score</th>
                <th>OK</th>
            </tr>
        </thead>
        <tbody>
        @foreach($analytics['per_request_deltas'] as $row)
        @php
            $idx = $row['index'];
            $a1e = $row['a1_eval'] ?? null;
            $a2e = $row['a2_eval'] ?? null;
            $bce = $row['b_cold_eval'] ?? null;
            $bwe = $row['b_warm_eval'] ?? null;
            $winnerMode = $row['winner'] ?? null;
            $winnerLabels = ['A1-direct-api'=>'A1','A2-loop-no-features'=>'A2','B-full-harness'=>'B-cold','B-warm-harness'=>'B-warm'];
            $winnerLabel = $winnerLabels[$winnerMode] ?? '—';
        @endphp
        <tr style="cursor:pointer;" onclick="toggleExpand({{ $idx }})">
            <td>{{ $idx + 1 }}</td>
            <td>
                @if(str_contains($row['agent'],'Elastic'))<span class="agent-badge elastic">EC</span>
                @else<span class="agent-badge soc">SOC</span>@endif
            </td>
            <td><code style="font-size:.72rem;">{{ Str::limit($row['category'], 25) }}</code></td>
            <td>{{ number_format($row['a1_latency']) }}ms</td>
            <td>{{ number_format($row['a2_latency']) }}ms</td>
            <td>{{ number_format($row['b_cold_latency']) }}ms</td>
            <td>{{ $row['b_warm_latency'] > 0 ? number_format($row['b_warm_latency']).'ms' : '—' }}</td>
            <td>@if($row['a2_vs_a1_pct']!==null)<span class="delta-badge {{ $row['a2_vs_a1_pct']>0?'delta-up':'delta-down' }}">{{ $row['a2_vs_a1_pct']>0?'+':'' }}{{ $row['a2_vs_a1_pct'] }}%</span>@else—@endif</td>
            <td>@if($row['b_cold_vs_a1_pct']!==null)<span class="delta-badge {{ $row['b_cold_vs_a1_pct']>0?'delta-up':'delta-down' }}">{{ $row['b_cold_vs_a1_pct']>0?'+':'' }}{{ $row['b_cold_vs_a1_pct'] }}%</span>@else—@endif</td>
            <td>@if($row['b_warm_vs_b_cold_pct']!==null)<span class="delta-badge {{ $row['b_warm_vs_b_cold_pct']>0?'delta-up':'delta-down' }}">{{ $row['b_warm_vs_b_cold_pct']>0?'+':'' }}{{ $row['b_warm_vs_b_cold_pct'] }}%</span>@else—@endif</td>
            <td>{{ number_format($row['a1_tokens']) }}</td>
            <td>{{ number_format($row['b_cold_tokens']) }}</td>
            <td>{{ $row['b_warm_tokens'] > 0 ? number_format($row['b_warm_tokens']) : '—' }}</td>
            <td>
                @if($row['b_warm_cache_hit'])<span class="delta-badge delta-down">HIT ✓</span>
                @elseif($row['b_cold_cache_hit'])<span class="delta-badge delta-flat">COLD</span>
                @else<span class="text-muted">—</span>@endif
            </td>
            <td>
                @if($winnerMode)
                <span class="badge {{ $winnerMode==='B-warm-harness'?'bg-success':($winnerMode==='B-full-harness'?'bg-danger':'bg-secondary') }}">
                    🏆 {{ $winnerLabel }}
                </span>
                @else<span class="text-muted">—</span>@endif
            </td>
            <td>@if($a1e && $a1e['error']===null)<span class="{{ $a1e['score']>=7?'text-success':($a1e['score']>=5?'text-warning':'text-danger') }}">{{ $a1e['score'] }}</span>@else<span class="text-muted">—</span>@endif</td>
            <td>@if($a2e && $a2e['error']===null)<span class="{{ $a2e['score']>=7?'text-success':($a2e['score']>=5?'text-warning':'text-danger') }}">{{ $a2e['score'] }}</span>@else<span class="text-muted">—</span>@endif</td>
            <td>@if($bce && $bce['error']===null)<span class="{{ $bce['score']>=7?'text-success':($bce['score']>=5?'text-warning':'text-danger') }}">{{ $bce['score'] }}</span>@else<span class="text-muted">—</span>@endif</td>
            <td>@if($bwe && $bwe['error']===null)<span class="{{ $bwe['score']>=7?'text-success':($bwe['score']>=5?'text-warning':'text-danger') }}">{{ $bwe['score'] }}</span>@else<span class="text-muted">—</span>@endif</td>
            <td>
                @if($row['a1_success'] && $row['a2_success'] && $row['b_cold_success'])
                <span class="freshness-ok">✓</span>
                @else<span class="freshness-bad">✗</span>@endif
            </td>
        </tr>
        {{-- Expanded detail row --}}
        <tr id="expand-{{ $idx }}" class="expand-row" style="display:none;">
            <td colspan="20">
                <div class="p-3">
                    <div class="mb-2"><strong style="color:#a0a0b0;">Prompt:</strong> <span style="color:#d0d0e0;">{{ Str::limit($row['prompt'], 200) }}</span></div>
                    <div class="row g-2 mt-1">
                        {{-- A1 --}}
                        <div class="col-md-3">
                            <div style="font-size:.75rem;font-weight:600;color:#a0a0b0;margin-bottom:4px;">A1 Response @if($a1e && ($a1e['is_winner']??false)) 🏆 @endif</div>
                            <div class="response-preview">{{ $row['a1_response'] ?: '(empty)' }}</div>
                            @if($a1e && $a1e['error']===null)
                            <div style="font-size:.72rem;margin-top:6px;color:#888;">
                                Score: <strong class="{{ $a1e['score']>=7?'text-success':($a1e['score']>=5?'text-warning':'text-danger') }}">{{ $a1e['score'] }}/10</strong>
                                | Acc:{{ $a1e['accuracy'] }} Comp:{{ $a1e['completeness'] }} Rel:{{ $a1e['relevance'] }} Qual:{{ $a1e['quality'] }}
                            </div>
                            @if(!empty($a1e['verdict']))<div style="font-size:.72rem;color:#888;margin-top:3px;font-style:italic;">{{ $a1e['verdict'] }}</div>@endif
                            @endif
                        </div>
                        {{-- A2 (no response preview stored, show eval only) --}}
                        <div class="col-md-3">
                            <div style="font-size:.75rem;font-weight:600;color:#a0a0b0;margin-bottom:4px;">A2 Eval @if($a2e && ($a2e['is_winner']??false)) 🏆 @endif</div>
                            <div class="response-preview" style="color:#777;font-style:italic;">(response not stored in preview)</div>
                            @if($a2e && $a2e['error']===null)
                            <div style="font-size:.72rem;margin-top:6px;color:#888;">
                                Score: <strong class="{{ $a2e['score']>=7?'text-success':($a2e['score']>=5?'text-warning':'text-danger') }}">{{ $a2e['score'] }}/10</strong>
                                | Acc:{{ $a2e['accuracy'] }} Comp:{{ $a2e['completeness'] }} Rel:{{ $a2e['relevance'] }} Qual:{{ $a2e['quality'] }}
                            </div>
                            @if(!empty($a2e['verdict']))<div style="font-size:.72rem;color:#888;margin-top:3px;font-style:italic;">{{ $a2e['verdict'] }}</div>@endif
                            @endif
                        </div>
                        {{-- B-cold --}}
                        <div class="col-md-3">
                            <div style="font-size:.75rem;font-weight:600;color:#a0a0b0;margin-bottom:4px;">B-cold Response @if($bce && ($bce['is_winner']??false)) 🏆 @endif</div>
                            <div class="response-preview">{{ $row['b_cold_response'] ?: '(empty)' }}</div>
                            @if($bce && $bce['error']===null)
                            <div style="font-size:.72rem;margin-top:6px;color:#888;">
                                Score: <strong class="{{ $bce['score']>=7?'text-success':($bce['score']>=5?'text-warning':'text-danger') }}">{{ $bce['score'] }}/10</strong>
                                | Acc:{{ $bce['accuracy'] }} Comp:{{ $bce['completeness'] }} Rel:{{ $bce['relevance'] }} Qual:{{ $bce['quality'] }}
                            </div>
                            @if(!empty($bce['verdict']))<div style="font-size:.72rem;color:#888;margin-top:3px;font-style:italic;">{{ $bce['verdict'] }}</div>@endif
                            @if(!empty($bce['strengths']))<div style="font-size:.72rem;color:#4ade80;margin-top:3px;">✓ {{ $bce['strengths'] }}</div>@endif
                            @if(!empty($bce['weaknesses']) && $bce['weaknesses'] !== 'None')<div style="font-size:.72rem;color:#f87171;margin-top:2px;">✗ {{ $bce['weaknesses'] }}</div>@endif
                            @endif
                        </div>
                        {{-- B-warm --}}
                        <div class="col-md-3">
                            <div style="font-size:.75rem;font-weight:600;color:#a0a0b0;margin-bottom:4px;">B-warm Response @if($bwe && ($bwe['is_winner']??false)) 🏆 @endif</div>
                            <div class="response-preview">{{ $row['b_warm_response'] ?: '(empty)' }}</div>
                            @if($bwe && $bwe['error']===null)
                            <div style="font-size:.72rem;margin-top:6px;color:#888;">
                                Score: <strong class="{{ $bwe['score']>=7?'text-success':($bwe['score']>=5?'text-warning':'text-danger') }}">{{ $bwe['score'] }}/10</strong>
                                | Acc:{{ $bwe['accuracy'] }} Comp:{{ $bwe['completeness'] }} Rel:{{ $bwe['relevance'] }} Qual:{{ $bwe['quality'] }}
                            </div>
                            @if(!empty($bwe['verdict']))<div style="font-size:.72rem;color:#888;margin-top:3px;font-style:italic;">{{ $bwe['verdict'] }}</div>@endif
                            @if(!empty($bwe['strengths']))<div style="font-size:.72rem;color:#4ade80;margin-top:3px;">✓ {{ $bwe['strengths'] }}</div>@endif
                            @if(!empty($bwe['weaknesses']) && $bwe['weaknesses'] !== 'None')<div style="font-size:.72rem;color:#f87171;margin-top:2px;">✗ {{ $bwe['weaknesses'] }}</div>@endif
                            @endif
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
@endif

{{-- ── Test Dataset ── --}}
<div class="mode-card mt-4">
    <h3>Test Dataset ({{ count($dataset) }} requests)</h3>
    <table class="tc-table">
        <thead><tr><th>#</th><th>Agent</th><th>Category</th><th>Prompt</th><th>Lang</th><th>Tools?</th></tr></thead>
        <tbody>
        @foreach($dataset as $i => $req)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>
                @if($req['agent'] === 'ElasticCostAssistant')<span class="agent-badge elastic">ElasticCost</span>
                @else<span class="agent-badge soc">RG SOC</span>@endif
            </td>
            <td><code style="font-size:.72rem;">{{ $req['category'] }}</code></td>
            <td>{{ Str::limit($req['prompt'], 90) }}</td>
            <td>
                @if(str_contains($req['category'],'tunisian'))<span class="lang-badge lang-tn">TN</span>
                @elseif(str_contains($req['category'],'french'))<span class="lang-badge lang-fr">FR</span>
                @else<span class="lang-badge lang-en">EN</span>@endif
            </td>
            <td>{{ $req['expects_tools'] ? '✓' : '—' }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>

@endif {{-- hasResults --}}

{{-- ── Comparison Report ── --}}
@if($reportContent)
<div class="mode-card mt-4">
    <h3>Comparison Report (Markdown)</h3>
    <div class="report-content">{!! Str::markdown($reportContent) !!}</div>
</div>
@endif

{{-- ── Save Run Modal ── --}}
<div id="saveRunModal" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.6);align-items:center;justify-content:center;">
    <div style="background:#1e1e2e;border:1px solid #3b82f6;border-radius:12px;padding:32px;min-width:420px;max-width:520px;width:90%;">
        <h4 style="color:#f0f0f0;margin-bottom:16px;">💾 Save Current Test Run</h4>
        <p style="color:#aaa;font-size:.875rem;margin-bottom:20px;">Archive this run so you can compare it later against other models.</p>
        <div style="margin-bottom:14px;">
            <label style="color:#ccc;font-size:.85rem;display:block;margin-bottom:6px;">Label</label>
            <input type="text" id="saveRunLabel" placeholder="e.g. Qwen-Plus baseline July 2026" style="width:100%;padding:8px 12px;background:#2a2a3e;border:1px solid #444;border-radius:6px;color:#f0f0f0;font-size:.9rem;box-sizing:border-box;">
        </div>
        <div style="margin-bottom:14px;">
            <label style="color:#ccc;font-size:.85rem;display:block;margin-bottom:6px;">Model used</label>
            <input type="text" id="saveRunModel" value="qwen-plus" style="width:100%;padding:8px 12px;background:#2a2a3e;border:1px solid #444;border-radius:6px;color:#f0f0f0;font-size:.9rem;box-sizing:border-box;">
        </div>
        <div style="margin-bottom:20px;">
            <label style="color:#ccc;font-size:.85rem;display:block;margin-bottom:6px;">Notes (optional)</label>
            <textarea id="saveRunNotes" rows="2" placeholder="Any notes about this run..." style="width:100%;padding:8px 12px;background:#2a2a3e;border:1px solid #444;border-radius:6px;color:#f0f0f0;font-size:.9rem;resize:vertical;box-sizing:border-box;"></textarea>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button onclick="closeSaveRunModal()" style="padding:8px 18px;background:#333;color:#ccc;border:1px solid #555;border-radius:6px;cursor:pointer;">Cancel</button>
            <button onclick="doSaveRun()" style="padding:8px 20px;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:600;">💾 Save Run</button>
        </div>
    </div>
</div>

{{-- ── Saved Runs Archive Panel ── --}}
@if(!empty($savedRuns))
<div class="mode-card mt-4" id="savedRunsPanel">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
        <h3 style="margin:0;">📚 Saved Test Runs</h3>
        <span style="color:#aaa;font-size:.85rem;">{{ count($savedRuns) }} archived run{{ count($savedRuns) !== 1 ? 's' : '' }}</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;">
        @foreach($savedRuns as $sr)
        <div style="background:#1e1e2e;border:1px solid #333;border-radius:8px;padding:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
            <div style="flex:1;min-width:200px;">
                <div style="font-weight:600;color:#f0f0f0;margin-bottom:4px;">{{ $sr['label'] }}</div>
                <div style="font-size:.8rem;color:#aaa;">
                    🤖 {{ $sr['model'] }}
                    @if($sr['saved_at']) &nbsp;·&nbsp; 🕐 {{ \Illuminate\Support\Carbon::parse($sr['saved_at'])->format('M j, Y H:i') }} @endif
                    @if($sr['trace_count'] > 0) &nbsp;·&nbsp; 📊 {{ $sr['trace_count'] }} traces @endif
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-shrink:0;">
                @if($sr['has_report'])
                <button onclick="loadSavedRunReport('{{ $sr['slug'] }}')"
                    style="padding:6px 14px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.85rem;">📄 View Report</button>
                @endif
                <button onclick="deleteSavedRun('{{ $sr['slug'] }}', '{{ addslashes($sr['label']) }}')"
                    style="padding:6px 12px;background:#1e1e2e;border:1px solid #ef4444;color:#ef4444;border-radius:6px;cursor:pointer;font-size:.85rem;">🗑</button>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── Saved Run Report Viewer ── --}}
<div id="savedRunReportViewer" style="display:none;" class="mode-card mt-4">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
        <div>
            <h3 style="margin:0;" id="savedRunReportTitle">Saved Run Report</h3>
            <div style="color:#aaa;font-size:.85rem;margin-top:4px;" id="savedRunReportMeta"></div>
        </div>
        <button onclick="document.getElementById('savedRunReportViewer').style.display='none'"
            style="padding:6px 14px;background:#333;color:#ccc;border:1px solid #555;border-radius:6px;cursor:pointer;">✕ Close</button>
    </div>
    <div class="report-content" id="savedRunReportContent"></div>
</div>

@endsection

@section('scripts')
<script>
function toggleExpand(idx) {
    const row = document.getElementById('expand-' + idx);
    if (row) row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}

async function runTests() {
    const btn = document.getElementById('runBtn');
    const prog = document.getElementById('progressContainer');
    const bar  = document.getElementById('progressBar');
    const log  = document.getElementById('runLog');
    btn.disabled = true;
    btn.innerHTML = '⏳ Running...';
    prog.style.display = 'block';
    log.style.display  = 'block';
    log.innerHTML = '';

    const addLog = (msg) => { log.innerHTML += msg + '\n'; log.scrollTop = log.scrollHeight; };
    addLog('Starting test suite (background)...');

    try {
        const r = await fetch('{{ route("test-compare.run") }}', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
            body: JSON.stringify({})
        });
        if (r.status === 409) { const d = await r.json(); throw new Error(d.message || 'Already running'); }
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const d = await r.json();
        addLog('✓ Background PID: ' + d.pid);

        let lastLogLen = 0, polls = 0;
        const maxPolls = 150;
        const TOTAL = 80;
        const stageMap = {
            'A1-direct-api':'stage-A1',
            'A2-loop-no-features':'stage-A2',
            'B-full-harness':'stage-B-cold',
            'B-warm-harness':'stage-B-warm'
        };

        const iv = setInterval(async () => {
            polls++;
            if (polls > maxPolls) {
                clearInterval(iv);
                addLog('⚠ Timeout — reloading...');
                setTimeout(() => location.reload(), 3000);
                return;
            }
            try {
                const sr = await fetch('{{ route("test-compare.status") }}', {headers:{'Accept':'application/json'}});
                const s  = await sr.json();

                // Progress bar
                const done = (s.trace_counts['A1-direct-api']||0)+(s.trace_counts['A2-loop-no-features']||0)
                           + (s.trace_counts['B-full-harness']||0)+(s.trace_counts['B-warm-harness']||0);
                const pct = Math.min(Math.round(done/TOTAL*100), done >= TOTAL ? 98 : 99);
                bar.style.width = pct + '%';
                bar.innerText = pct + '% (' + done + '/' + TOTAL + ')';

                // Stage indicators
                Object.keys(stageMap).forEach(mode => {
                    const el = document.getElementById(stageMap[mode]);
                    if (!el) return;
                    const cnt = s.trace_counts[mode] || 0;
                    el.querySelector('.stage-count').textContent = cnt + '/20';
                    el.className = 'stage-ind ' + (cnt >= 20 ? 'stage-done' : (mode === s.current_stage ? 'stage-running' : (cnt > 0 ? 'stage-done' : 'stage-pending')));
                    el.querySelector('.stage-icon').textContent = cnt >= 20 ? '✓' : (mode === s.current_stage ? '◉' : '○');
                });

                // AI eval stage
                const evalEl = document.getElementById('stage-eval');
                if (evalEl) {
                    if (s.marker_done || done >= TOTAL) {
                        evalEl.className = 'stage-ind stage-done';
                        evalEl.querySelector('.stage-icon').textContent = '✓';
                    } else if (!s.running && done >= TOTAL) {
                        evalEl.className = 'stage-ind stage-running';
                        evalEl.querySelector('.stage-icon').textContent = '◉';
                    }
                }

                // Log delta
                if (s.log && s.log.length > lastLogLen) {
                    s.log.substring(lastLogLen).split('\n').filter(l=>l.trim()).forEach(l => addLog(l));
                    lastLogLen = s.log.length;
                }

                if (s.marker_done || done >= TOTAL) {
                    clearInterval(iv);
                    bar.style.width = '100%'; bar.innerText = '100%';
                    addLog('✓ Done! Reloading...');
                    setTimeout(() => location.reload(), 3000);
                } else if (!s.running && !s.marker_done && polls > 10 && done === 0) {
                    clearInterval(iv);
                    addLog('⚠ No traces after 50s — check logs. Reloading...');
                    setTimeout(() => location.reload(), 3000);
                }
            } catch(e) { /* keep polling */ }
        }, 5000);

    } catch(e) {
        addLog('✗ ' + e.message);
        btn.disabled = false;
        btn.innerHTML = '▶ Run Full Test Suite';
    }
}

function purgeOldRuns() {
    if (!confirm('Purge all old test runs and traces?')) return;
    fetch('{{ route("test-compare.purge") }}', {
        method:'POST',
        headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json','Accept':'application/json'}
    }).then(r=>r.json()).then(d => {
        if (d.success) { alert(d.message); location.reload(); }
        else alert('Purge failed: '+(d.message||'error'));
    }).catch(e=>alert('Error: '+e.message));
}

// ── Save Run ──────────────────────────────────────────────────────────────────
function openSaveRunModal() {
    document.getElementById('saveRunModal').style.display = 'flex';
    const d = new Date();
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    document.getElementById('saveRunLabel').value = 'Run ' + d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
}
function closeSaveRunModal() {
    document.getElementById('saveRunModal').style.display = 'none';
}

async function doSaveRun() {
    const label = document.getElementById('saveRunLabel').value.trim() || 'Unnamed Run';
    const model = document.getElementById('saveRunModel').value.trim() || 'qwen-plus';
    const notes = document.getElementById('saveRunNotes').value.trim();
    try {
        const resp = await fetch('{{ route("test-compare.save-run") }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json','Accept':'application/json'},
            body: JSON.stringify({label, model, notes}),
        });
        const data = await resp.json();
        closeSaveRunModal();
        if (data.success) { alert('✅ ' + data.message); location.reload(); }
        else alert('❌ ' + (data.message || 'Save failed'));
    } catch(e) { alert('Error: ' + e.message); }
}

// ── Load Saved Run Report ─────────────────────────────────────────────────────
async function loadSavedRunReport(slug) {
    const viewer  = document.getElementById('savedRunReportViewer');
    const content = document.getElementById('savedRunReportContent');
    const title   = document.getElementById('savedRunReportTitle');
    const meta    = document.getElementById('savedRunReportMeta');
    content.innerHTML = '<div style="color:#aaa;padding:24px;">Loading...</div>';
    viewer.style.display = 'block';
    viewer.scrollIntoView({behavior:'smooth'});
    try {
        const resp = await fetch('{{ url("test-compare/saved-runs") }}/' + encodeURIComponent(slug) + '/report', {
            headers:{'Accept':'application/json'}
        });
        const data = await resp.json();
        if (data.success) {
            title.textContent = '📄 ' + (data.meta?.label || slug);
            const savedAt = data.meta?.saved_at ? new Date(data.meta.saved_at).toLocaleString() : '';
            meta.innerHTML = '🤖 ' + (data.meta?.model || 'unknown') + (savedAt ? ' &nbsp;·&nbsp; 🕐 ' + savedAt : '');
            content.innerHTML = '<pre style="white-space:pre-wrap;word-break:break-word;font-family:inherit;font-size:.88rem;line-height:1.7;color:#ddd;">'
                + (data.report || 'No report found.').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                + '</pre>';
        } else {
            content.innerHTML = '<div style="color:#ef4444;">Failed to load: ' + (data.message||'unknown error') + '</div>';
        }
    } catch(e) { content.innerHTML = '<div style="color:#ef4444;">Error: ' + e.message + '</div>'; }
}

// ── Delete Saved Run ──────────────────────────────────────────────────────────
async function deleteSavedRun(slug, label) {
    if (!confirm('Delete saved run "' + label + '"? This cannot be undone.')) return;
    try {
        const resp = await fetch('{{ url("test-compare/saved-runs") }}/' + encodeURIComponent(slug), {
            method: 'DELETE',
            headers: {'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
        });
        const data = await resp.json();
        if (data.success) location.reload();
        else alert('Delete failed: ' + (data.message||'error'));
    } catch(e) { alert('Error: ' + e.message); }
}
</script>
@endsection
