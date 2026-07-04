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
    <p>Compare direct Qwen Cloud API calls vs AgentLoop (no features) vs Full phpkaiharness (cold & warm cache) across 17 test requests</p>
</div>

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
            <div class="metric-row"><span class="metric-label">Avg Latency</span><span class="metric-value">{{ $summary['A2-loop-no-features']['avg_latency_ms'] }}ms</span></div>
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
            <div class="metric-row"><span class="metric-label">Avg Latency</span><span class="metric-value">{{ $summary['B-full-harness']['avg_latency_ms'] }}ms</span></div>
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
            <div class="metric-row"><span class="metric-label">Avg Latency</span><span class="metric-value good">{{ $summary['B-warm-harness']['avg_latency_ms'] }}ms</span></div>
            <div class="metric-row"><span class="metric-label">Avg Tokens</span><span class="metric-value">{{ $summary['B-warm-harness']['avg_total_tokens'] }}</span></div>
            <div class="metric-row"><span class="metric-label">Tool Calls</span><span class="metric-value good">{{ $summary['B-warm-harness']['avg_tool_calls'] }}</span></div>
            <div class="metric-row"><span class="metric-label">Pipeline Stages</span><span class="metric-value good">{{ $summary['B-warm-harness']['pipeline_stages_avg'] }}</span></div>
            <div class="metric-row"><span class="metric-label">Success Rate</span><span class="metric-value">{{ $summary['B-warm-harness']['successful'] }}/{{ $summary['B-warm-harness']['total_requests'] }}</span></div>
            @elseif($hasWarmResults ?? false)
            <p class="text-muted small">Warm results loading...</p>
            @else
            <p class="text-muted small">No warm run yet. Run test suite to generate.</p>
            @endif
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

@if($hasResults && !empty($traces))
<div class="row mt-4">
    <div class="col-md-12">
        <div class="mode-card">
            <h3>Per-Request Comparison</h3>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Agent</th>
                        <th>Category</th>
                        <th>A1 Lat</th>
                        <th>A2 Lat</th>
                        <th>B-Cold Lat</th>
                        <th>B-Warm Lat</th>
                        <th>B-Cold Tok</th>
                        <th>B-Warm Tok</th>
                        <th>B-Cold Tools</th>
                        <th>B-Warm Tools</th>
                        <th>Δ Latency</th>
                    </tr>
                </thead>
                <tbody>
                    @for($i = 0; $i < max(count($traces['A1-direct-api'] ?? []), count($traces['B-full-harness'] ?? []), count($traces['B-warm-harness'] ?? [])); $i++)
                    @php
                        $a1 = $traces['A1-direct-api'][$i] ?? [];
                        $a2 = $traces['A2-loop-no-features'][$i] ?? [];
                        $bc = $traces['B-full-harness'][$i] ?? [];
                        $bw = $traces['B-warm-harness'][$i] ?? [];
                        $coldLat = $bc['timing']['latency_ms'] ?? 0;
                        $warmLat = $bw['timing']['latency_ms'] ?? 0;
                        $delta = $coldLat > 0 ? round(($warmLat - $coldLat) / $coldLat * 100) : 0;
                        $deltaClass = $delta < 0 ? 'good' : ($delta > 10 ? 'bad' : '');
                    @endphp
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $a1['agent'] ?? $bc['agent'] ?? 'N/A' }}</td>
                        <td><code>{{ $a1['category'] ?? $bc['category'] ?? 'N/A' }}</code></td>
                        <td>{{ $coldLat }}ms</td>
                        <td>{{ ($a2['timing']['latency_ms'] ?? 0) }}ms</td>
                        <td>{{ $coldLat }}ms</td>
                        <td>{{ $warmLat > 0 ? $warmLat.'ms' : '—' }}</td>
                        <td>{{ $bc['tokens']['total_tokens'] ?? 0 }}</td>
                        <td>{{ $bw['tokens']['total_tokens'] ?? '—' }}</td>
                        <td>{{ $bc['tool_calls']['count'] ?? 0 }}</td>
                        <td>{{ $bw['tool_calls']['count'] ?? '—' }}</td>
                        <td>@if($warmLat > 0)<span class="metric-value {{ $deltaClass }}">{{ $delta > 0 ? '+' : '' }}{{ $delta }}%</span>@else — @endif</td>
                    </tr>
                    @endfor
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

        log('Starting test suite...');

        try {
            // Simulate progress since the AJAX call is synchronous
            let progress = 0;
            const interval = setInterval(() => {
                progress = Math.min(progress + 1, 95);
                progressBar.style.width = progress + '%';
                progressBar.innerText = progress + '%';
            }, 500);

            log('Sending run request...');

            const response = await fetch('{{ route("test-compare.run") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({})
            });

            clearInterval(interval);

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const data = await response.json();

            progressBar.style.width = '100%';
            progressBar.innerText = '100%';
            log('✓ Test suite completed!');
            log('✓ Report generated');

            if (data.summary) {
                log('\nSummary:');
                Object.keys(data.summary).forEach(mode => {
                    const s = data.summary[mode];
                    log(`  ${mode}: ${s.successful}/${s.total_requests} successful, avg ${s.avg_latency_ms}ms, ${s.avg_total_tokens} tokens`);
                });
            }

            log('\nReloading page to show results...');
            setTimeout(() => location.reload(), 2000);

        } catch (error) {
            log('✗ Error: ' + error.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="icon-play"></i> Run Full Test Suite';
        }
    }
</script>
@endsection
