@extends('layouts.app')

@section('title', __('messages.sizing_calculator') . ": {$client->name} - {$scenario->name}")

@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">{{ __('messages.clients') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('clients.show', $client->id) }}">{{ strtoupper($client->name) }}</a></li>
    <li class="breadcrumb-item active">{{ __('messages.sizing_calculator') }}</li>
</ul>

<div class="d-flex align-items-center mb-4">
    <div class="flex-grow-1">
        <h1 class="page-header mb-0">
            {{ __('messages.sizing_details') }} <small class="d-block mt-1">{{ $scenario->name }} ({{ $scenario->description }})</small>
        </h1>
    </div>
    <div class="d-flex gap-2">
        <button id="btn-test-ollama" type="button" class="btn btn-outline-warning" title="Test AI Connection">
            <i class="bi bi-wifi me-1"></i> Test AI Connection
        </button>
        <button id="btn-ask-ai" type="button" class="btn btn-outline-info">
            <i class="bi bi-cpu me-1"></i> Analyze Sizing
        </button>
        <button id="btn-sync-diagrams" type="button" class="btn btn-outline-primary">
            <i class="bi bi-diagram-3 me-1"></i> Sync Diagrams
        </button>
        <a href="{{ route('clients.show', $client->id) }}" class="btn btn-outline-secondary">
            <i class="bi bi-x-circle me-1"></i> {{ __('messages.close') }}
        </a>
        <a href="{{ route('sizing.export.excel', [$client->id, $scenario->id]) }}" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-excel me-1"></i> {{ __('messages.export_xlsx') }}
        </a>
        <a href="{{ route('sizing.export.word', [$client->id, $scenario->id]) }}" class="btn btn-outline-info">
            <i class="bi bi-file-earmark-word me-1"></i> {{ __('messages.download_docx') }}
        </a>
        <a href="{{ route('sizing.export.markdown', [$client->id, $scenario->id]) }}" class="btn btn-outline-theme">
            <i class="bi bi-file-earmark-text me-1"></i> {{ __('messages.download_md') }}
        </a>
        <a href="{{ route('mssp.show', [$client->id, $scenario->id]) }}" class="btn btn-primary">
            <i class="bi bi-wallet2 me-1"></i> {{ __('messages.mssp_soc_proposal_btn') }}
        </a>
    </div>
</div>

<!-- 1. Top Level Key Metrics -->
<div class="row mb-4">
    <!-- Daily Ingestion -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold mb-2 uppercase-tracking">
                    <span style="color: var(--tier-hot);">⚡</span> {{ __('messages.daily_ingestion_volume') }}
                </div>
                <div class="d-flex align-items-baseline mb-2">
                    <span class="fs-32px fw-bold text-white mono-cell">{{ $data['totals']['daily_raw_gb'] }}</span>
                    <span class="text-muted ms-2 fs-14px">GB/day</span>
                </div>
                <div class="small text-muted mt-2 border-top border-secondary border-opacity-20 pt-2">
                    Indexed: <strong class="text-white mono-cell">{{ $data['totals']['daily_indexed_gb'] }} GB</strong> | Ingested (+Replica): <strong class="text-white mono-cell">{{ $data['totals']['daily_ingested_gb'] }} GB</strong>
                </div>
            </div>
            <div class="card-arrow">
                <div class="card-arrow-top-left"></div>
                <div class="card-arrow-top-right"></div>
                <div class="card-arrow-bottom-left"></div>
                <div class="card-arrow-bottom-right"></div>
            </div>
        </div>
    </div>

    <!-- Storage Footprint -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold mb-2 uppercase-tracking">
                    <span style="color: var(--tier-cold);">💾</span> {{ __('messages.storage_footprint') }}
                </div>
                <div class="d-flex align-items-baseline mb-2">
                    <span class="fs-32px fw-bold text-white mono-cell">{{ number_format($data['totals']['total_storage_footprint_gb'] / ($data['totals']['total_storage_footprint_gb'] >= 1000 ? 1000 : 1), 2) }}</span>
                    <span class="text-muted ms-2 fs-14px">{{ $data['totals']['total_storage_footprint_gb'] >= 1000 ? 'TB' : 'GB' }}</span>
                </div>
                <div class="small text-muted mt-2 border-top border-secondary border-opacity-20 pt-2">
                    Raw stored: <strong class="text-white mono-cell">{{ number_format($data['totals']['total_raw_storage_gb'] / ($data['totals']['total_raw_storage_gb'] >= 1000 ? 1000 : 1), 2) }} {{ $data['totals']['total_raw_storage_gb'] >= 1000 ? 'TB' : 'GB' }}</strong> over <strong class="text-white">{{ $scenario->retention_days }} days</strong>
                </div>
            </div>
            <div class="card-arrow">
                <div class="card-arrow-top-left"></div>
                <div class="card-arrow-top-right"></div>
                <div class="card-arrow-bottom-left"></div>
                <div class="card-arrow-bottom-right"></div>
            </div>
        </div>
    </div>

    <!-- Licensing Subscription -->
    <div class="col-xl-4 col-md-12 mb-4">
        <div class="card h-100 border-theme border-opacity-30">
            <div class="card-body">
                <div class="text-muted small fw-bold mb-2 uppercase-tracking">
                    <span class="text-theme">🎟️</span> {{ __('messages.subscription_licensing') }}
                </div>
                <div class="d-flex align-items-baseline mb-2">
                    <span class="fs-32px fw-bold text-theme mono-cell">{{ $data['licensing']['required_erus'] }}</span>
                    <span class="text-muted ms-2 fs-14px">ERUs</span>
                </div>
                <div class="small text-muted mt-2 border-top border-secondary border-opacity-20 pt-2">
                    Cluster RAM: <strong class="text-white mono-cell">{{ $data['licensing']['total_ram_gb'] }} GB</strong> | Annual Cost: <strong class="text-success fs-13px">{{ \App\Services\CurrencyHelper::format($data['licensing']['annual_cost_usd']) }}</strong>
                </div>
            </div>
            <div class="card-arrow">
                <div class="card-arrow-top-left"></div>
                <div class="card-arrow-top-right"></div>
                <div class="card-arrow-bottom-left"></div>
                <div class="card-arrow-bottom-right"></div>
            </div>
        </div>
</div>

<div id="ollama-ping-toast-container" style="position: fixed; top: 80px; right: 20px; z-index: 9999;"></div>

<!-- AI Analysis Card -->
<div class="card mb-4 border-info border-opacity-40" id="ai-analysis-card" style="{{ empty($aiSizingAnalysis) ? 'display: none;' : '' }}">
    <div class="card-body py-4">
        <h5 class="card-title text-info mb-3">
            <i class="bi bi-cpu me-2"></i> AI Sizing Regulator Analysis
        </h5>
        <div id="ai-analysis-content" class="text-white text-opacity-80 leading-relaxed markdown-body">
            @if(!empty($aiSizingAnalysis))
                {!! \Illuminate\Support\Str::markdown($aiSizingAnalysis) !!}
            @endif
        </div>
    </div>
    <div class="card-arrow">
        <div class="card-arrow-top-left"></div>
        <div class="card-arrow-top-right"></div>
        <div class="card-arrow-bottom-left"></div>
        <div class="card-arrow-bottom-right"></div>
    </div>
</div>

@php
    $chartLabels = [];
    $chartSeries = [];
    foreach($data['assets'] as $asset) {
        $chartLabels[] = $asset['name'];
        $chartSeries[] = (float) $asset['daily_raw_gb'];
    }
@endphp

<!-- 2. Source Log Sizing Table -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3 text-theme">
            <i class="bi bi-table me-2"></i> {{ __('messages.log_ingestion_source_sizing_breakdown') }}
        </h5>
        
        <div class="row">
            <div class="col-xl-8 col-lg-7">
                <div class="table-responsive">
                    <table class="table table-borderless table-hover align-middle mb-0">
                        <thead>
                            <tr class="border-bottom text-muted small uppercase-tracking">
                                <th>{{ __('messages.log_source') }}</th>
                                <th>{{ __('messages.device_count') }}</th>
                                <th>{{ __('messages.avg_event_size') }}</th>
                                <th>{{ __('messages.eps_device') }}</th>
                                <th>{{ __('messages.total_eps') }}</th>
                                <th>{{ __('messages.daily_events') }}</th>
                                <th>{{ __('messages.daily_raw') }}</th>
                                <th>{{ __('messages.daily_indexed') }}</th>
                                <th>{{ __('messages.daily_ingested') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['assets'] as $asset)
                                <tr>
                                    <td><strong class="text-white">{{ $asset['name'] }}</strong></td>
                                    <td class="mono-cell">{{ $asset['device_count'] }}</td>
                                    <td class="mono-cell">{{ $asset['event_size_bytes'] >= 1000 ? ($asset['event_size_bytes']/1000) . ' KB' : $asset['event_size_bytes'] . ' B' }}</td>
                                    <td class="mono-cell text-muted">{{ $asset['eps'] }} EPS</td>
                                    <td class="mono-cell">{{ $asset['total_eps'] }} EPS</td>
                                    <td class="mono-cell text-muted">{{ number_format($asset['daily_event_count']) }}</td>
                                    <td class="mono-cell text-white fw-bold">{{ $asset['daily_raw_gb'] }} GB</td>
                                    <td class="mono-cell text-muted">{{ $asset['daily_indexed_gb'] }} GB</td>
                                    <td class="mono-cell text-theme fw-bold">{{ $asset['daily_ingested_gb'] }} GB</td>
                                </tr>
                            @endforeach
                            <tr class="border-top table-active font-weight-bold align-middle">
                                <td>{{ __('messages.total_calculations') }}</td>
                                <td class="mono-cell">-</td>
                                <td class="mono-cell">-</td>
                                <td class="mono-cell">-</td>
                                <td class="mono-cell">-</td>
                                <td class="mono-cell">-</td>
                                <td class="mono-cell text-white fw-bold">{{ $data['totals']['daily_raw_gb'] }} GB</td>
                                <td class="mono-cell text-muted">{{ $data['totals']['daily_indexed_gb'] }} GB</td>
                                <td class="mono-cell text-theme fw-bold">{{ $data['totals']['daily_ingested_gb'] }} GB</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-xl-4 col-lg-5">
                <div class="d-flex align-items-center justify-content-center h-100" style="min-height: 280px;">
                    <div id="ingestionDonutChart" style="width: 100%;"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-arrow">
        <div class="card-arrow-top-left"></div>
        <div class="card-arrow-top-right"></div>
        <div class="card-arrow-bottom-left"></div>
        <div class="card-arrow-bottom-right"></div>
    </div>
</div>

<!-- 3. Lifecycle Tier Breakdown & Cluster Architecture Grid -->
<div class="row">
    <!-- Storage Lifecycle Tiers -->
    <div class="col-xl-6 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title mb-3 text-theme">
                    <i class="bi bi-clock-history me-2"></i> {{ __('messages.data_lifecycle_tier_sizing') }}
                </h5>
                
                <div class="table-responsive">
                    <table class="table table-borderless table-hover align-middle mb-0">
                        <thead>
                            <tr class="border-bottom text-muted small uppercase-tracking">
                                <th>{{ __('messages.tier') }}</th>
                                <th>{{ __('messages.duration') }}</th>
                                <th>{{ __('messages.replication') }}</th>
                                <th class="text-end">{{ __('messages.storage_needed') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($scenario->hot_days > 0)
                                <tr>
                                    <td><span class="badge badge-hot">Hot SSD</span></td>
                                    <td class="mono-cell">{{ $scenario->hot_days }} Days</td>
                                    <td class="small text-muted">Primary + {{ $scenario->hot_replicas }} Replica</td>
                                    <td class="mono-cell text-end text-white fw-bold">{{ number_format($data['totals']['hot_storage_gb'], 2) }} GB</td>
                                </tr>
                            @endif
                            @if($scenario->warm_days > 0)
                                <tr>
                                    <td><span class="badge badge-warm">Warm Tier</span></td>
                                    <td class="mono-cell">{{ $scenario->warm_days }} Days</td>
                                    <td class="small text-muted">Primary + {{ $scenario->warm_replicas }} Replica</td>
                                    <td class="mono-cell text-end text-white fw-bold">{{ number_format($data['totals']['warm_storage_gb'], 2) }} GB</td>
                                </tr>
                            @endif
                            @if($scenario->cold_days > 0)
                                <tr>
                                    <td><span class="badge badge-cold">Cold Tier</span></td>
                                    <td class="mono-cell">{{ $scenario->cold_days }} Days</td>
                                    <td class="small text-muted">Mounted Snapshot (0% replica overhead)</td>
                                    <td class="mono-cell text-end text-white fw-bold">{{ number_format($data['totals']['cold_storage_gb'], 2) }} GB</td>
                                </tr>
                            @endif
                            @if($scenario->frozen_days > 0)
                                <tr>
                                    <td><span class="badge badge-frozen">Frozen Cache</span></td>
                                    <td class="mono-cell">{{ $scenario->frozen_days }} Days</td>
                                    <td class="small text-muted">On-Demand Cache (0% replica overhead)</td>
                                    <td class="mono-cell text-end text-white fw-bold">{{ number_format($data['totals']['frozen_storage_gb'], 2) }} GB</td>
                                </tr>
                            @endif
                            <tr class="border-top table-active font-weight-bold">
                                <td>{{ __('messages.retention_totals') }}</td>
                                <td class="mono-cell">{{ $scenario->retention_days }} Days</td>
                                <td class="small text-muted">-</td>
                                <td class="mono-cell text-end text-success fw-bold">{{ number_format($data['totals']['total_storage_footprint_gb'], 2) }} GB</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                @if($scenario->frozen_days > 0)
                    <div class="alert alert-secondary bg-transparent border-secondary border-opacity-30 mt-3 small mb-0">
                        <i class="bi bi-lightbulb-fill text-warning me-1"></i>
                        <strong>ILM Optimization Notice</strong>: Using searchable snapshot mounts on cold/frozen tiers removes replica requirements, saving up to **46%** in cluster-wide disk allocation.
                    </div>
                @endif
            </div>
            <div class="card-arrow">
                <div class="card-arrow-top-left"></div>
                <div class="card-arrow-top-right"></div>
                <div class="card-arrow-bottom-left"></div>
                <div class="card-arrow-bottom-right"></div>
            </div>
        </div>
    </div>

    <!-- Cluster Node Architecture Specs -->
    <div class="col-xl-6 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title mb-3 text-theme">
                    <i class="bi bi-hdd-fill me-2"></i> {{ __('messages.recommended_node_specs') }}
                </h5>

                <div class="table-responsive">
                    <table class="table table-borderless table-hover align-middle mb-0">
                        <thead>
                            <tr class="border-bottom text-muted small uppercase-tracking">
                                <th>{{ __('messages.node_type') }}</th>
                                <th>{{ __('messages.role') }}</th>
                                <th>{{ __('messages.ram_node') }}</th>
                                <th>{{ __('messages.disk_node') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['nodes'] as $node)
                                <tr>
                                    <td>
                                        <strong class="text-white">{{ $node['name'] }}</strong>
                                        @if($node['count'] > 1) <span class="small text-muted">(x{{ $node['count'] }})</span> @endif
                                    </td>
                                    <td class="small text-muted">{{ $node['role'] }}</td>
                                    <td class="mono-cell">{{ $node['ram_gb'] }} GB <span class="text-muted small">(Heap: {{ $node['heap_gb'] }}G)</span></td>
                                    <td class="mono-cell text-white">
                                        {{ $node['storage_gb'] >= 1000 ? ($node['storage_gb'] / 1000) . ' TB' : $node['storage_gb'] . ' GB' }}
                                        <div class="small text-muted mt-0.5" style="font-size: 11px;">{{ $node['storage_type'] }}</div>
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="border-top table-active font-weight-bold">
                                <td>{{ __('messages.total_vm_ram') }}</td>
                                <td class="small text-muted">-</td>
                                <td class="mono-cell text-theme fw-bold">{{ $data['licensing']['total_ram_gb'] }} GB</td>
                                <td class="mono-cell">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-arrow">
                <div class="card-arrow-top-left"></div>
                <div class="card-arrow-top-right"></div>
                <div class="card-arrow-bottom-left"></div>
                <div class="card-arrow-bottom-right"></div>
            </div>
        </div>
    </div>
</div>

<!-- Cluster Topology Editor -->
<div class="card mb-4 border-theme border-opacity-35">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title text-theme mb-0">
                <i class="bi bi-pencil-square me-2"></i> Cluster Topology Editor
            </h5>
            <div>
                @php
                    $isCustom = !empty($client->clientScenarioMsspDetails()->where('scenario_id', $scenario->id)->first()?->custom_nodes);
                @endphp
                @if($isCustom)
                    <span class="badge bg-warning bg-opacity-20 text-warning border border-warning border-opacity-30 me-2">Customized Layout</span>
                @else
                    <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-30 me-2">Auto-Recommended Layout</span>
                @endif
            </div>
        </div>
        <p class="text-muted small mb-4">
            Customize the cluster node architecture below. You can modify existing nodes, add new ones (e.g. dedicated masters, ML, Logstash), or delete rows. Saving changes will immediately update the total RAM, ERU licensing counts, and annual subscription costs.
        </p>

        <form action="{{ route('sizing.custom-nodes.save', [$client->id, $scenario->id]) }}" method="POST">
            @csrf
            <div class="table-responsive">
                <table class="table table-borderless table-hover align-middle mb-0" id="custom-nodes-table">
                    <thead>
                        <tr class="border-bottom text-muted small uppercase-tracking">
                            <th>Node Name</th>
                            <th>Role / Description</th>
                            <th style="width: 100px;">Node Count</th>
                            <th style="width: 110px;">RAM (GB)</th>
                            <th style="width: 130px;">Storage (GB)</th>
                            <th>Storage Type</th>
                            <th style="width: 80px;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="nodes-tbody">
                        @foreach($data['nodes'] as $index => $node)
                            <tr>
                                <td>
                                    <input type="text" name="nodes[{{ $index }}][name]" value="{{ $node['name'] }}" class="form-control form-control-sm bg-black bg-opacity-30 border-secondary border-opacity-30 text-white" required>
                                </td>
                                <td>
                                    <input type="text" name="nodes[{{ $index }}][role]" value="{{ $node['role'] }}" class="form-control form-control-sm bg-black bg-opacity-30 border-secondary border-opacity-30 text-white" required>
                                </td>
                                <td>
                                    <input type="number" name="nodes[{{ $index }}][count]" value="{{ $node['count'] }}" min="1" class="form-control form-control-sm bg-black bg-opacity-30 border-secondary border-opacity-30 text-white text-center" required>
                                </td>
                                <td>
                                    <input type="number" step="0.1" name="nodes[{{ $index }}][ram_gb]" value="{{ $node['ram_gb'] }}" min="0.1" class="form-control form-control-sm bg-black bg-opacity-30 border-secondary border-opacity-30 text-white text-center" required>
                                </td>
                                <td>
                                    <input type="number" step="0.1" name="nodes[{{ $index }}][storage_gb]" value="{{ $node['storage_gb'] }}" min="0.1" class="form-control form-control-sm bg-black bg-opacity-30 border-secondary border-opacity-30 text-white text-center" required>
                                </td>
                                <td>
                                    <input type="text" name="nodes[{{ $index }}][storage_type]" value="{{ $node['storage_type'] }}" class="form-control form-control-sm bg-black bg-opacity-30 border-secondary border-opacity-30 text-white" required>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-outline-danger btn-sm w-100 delete-node-row">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between mt-3 pt-3 border-top border-secondary border-opacity-25">
                <button type="button" class="btn btn-outline-info btn-sm" id="btn-add-node">
                    <i class="bi bi-plus-circle me-1"></i> Add Custom Node
                </button>
                <div class="d-flex gap-2">
                    @if($isCustom)
                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="document.getElementById('reset-topology-form').submit();">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset to Defaults
                        </button>
                    @endif
                    <button type="submit" class="btn btn-theme btn-sm px-4">
                        <i class="bi bi-save me-1"></i> Save Layout & Recalculate
                    </button>
                </div>
            </div>
        </form>
    </div>
    <div class="card-arrow">
        <div class="card-arrow-top-left"></div>
        <div class="card-arrow-top-right"></div>
        <div class="card-arrow-bottom-left"></div>
        <div class="card-arrow-bottom-right"></div>
    </div>
</div>

<form id="reset-topology-form" action="{{ route('sizing.custom-nodes.reset', [$client->id, $scenario->id]) }}" method="POST" style="display:none;">
    @csrf
</form>

<!-- 4. Visual Topology Representation -->
<div class="card mb-4 border-secondary border-opacity-20">
    <div class="card-body">
        <h5 class="card-title mb-4 text-theme">
            <i class="bi bi-diagram-3-fill me-2"></i> {{ __('messages.recommended_node_clustering_topology') }}
        </h5>
        
        <div class="row g-3 justify-content-center p-3 rounded bg-black bg-opacity-20">
            @foreach($data['nodes'] as $node)
                @php
                    $roleClass = 'border-secondary';
                    if (str_contains(strtolower($node['role']), 'hot')) $roleClass = 'border-danger';
                    elseif (str_contains(strtolower($node['role']), 'warm')) $roleClass = 'border-warning';
                    elseif (str_contains(strtolower($node['role']), 'cold')) $roleClass = 'border-primary';
                    elseif (str_contains(strtolower($node['role']), 'frozen')) $roleClass = 'border-info';
                    elseif (str_contains(strtolower($node['role']), 'master')) $roleClass = 'border-secondary border-opacity-70';
                @endphp
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
                    <div class="card border {{ $roleClass }} bg-transparent bg-opacity-10 h-100">
                        <div class="card-body d-flex flex-column justify-content-between py-3">
                            <div>
                                <span class="text-muted small uppercase-tracking" style="font-size: 10px;">{{ $node['role'] }}</span>
                                <h6 class="mb-3 text-white fw-bold mt-1">
                                    {{ $node['name'] }} 
                                    @if($node['count'] > 1) <span class="badge bg-secondary bg-opacity-30 text-white">x{{ $node['count'] }}</span> @endif
                                </h6>
                            </div>
                            <div class="border-top border-secondary border-opacity-20 pt-2 d-flex justify-content-between align-items-center fs-12px mono-cell">
                                <span class="text-theme fw-bold">{{ $node['ram_gb'] }} GB RAM</span>
                                <span class="text-muted">{{ $node['storage_gb'] >= 1000 ? ($node['storage_gb']/1000) . 'TB' : $node['storage_gb'] . 'GB' }}</span>
                            </div>
                        </div>
                        <div class="card-arrow">
                            <div class="card-arrow-top-left"></div>
                            <div class="card-arrow-top-right"></div>
                            <div class="card-arrow-bottom-left"></div>
                            <div class="card-arrow-bottom-right"></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="card-arrow">
        <div class="card-arrow-top-left"></div>
        <div class="card-arrow-top-right"></div>
        <div class="card-arrow-bottom-left"></div>
        <div class="card-arrow-bottom-right"></div>
    </div>
</div>

<!-- 5. Generated Sizing Diagrams -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3 text-theme">
            <i class="bi bi-diagram-3-fill me-2"></i> Scenario Architecture Diagrams (Draw.io)
        </h5>
        <p class="text-muted small mb-4">
            These diagrams are automatically generated based on the sizing parameters above. They update automatically when you save custom node layouts or reset defaults. You can open any diagram in the full editor to customize it further.
        </p>

        @php
            $scenarioDiagrams = $client->diagrams()->where('scenario_id', $scenario->id)->get();
        @endphp

        <div class="row g-3" id="sizing-diagrams-grid">
            @if($scenarioDiagrams->isEmpty())
                <div class="col-12 text-center py-4 text-muted">
                    <i class="bi bi-info-circle me-1"></i> No generated diagrams found. Click <strong>Sync Diagrams</strong> above to auto-generate them.
                </div>
            @else
                @foreach($scenarioDiagrams as $diag)
                    <div class="col-xl-3 col-lg-6 col-md-6">
                        <div class="card border-secondary border-opacity-25 bg-black bg-opacity-10 h-100">
                            <div class="card-body d-flex flex-column justify-content-between p-3">
                                <div>
                                    <span class="badge bg-theme bg-opacity-20 text-theme border border-theme border-opacity-30 mb-2">
                                        {{ strtoupper(str_replace('_', ' ', $diag->type)) }}
                                    </span>
                                    <h6 class="text-white fw-bold mb-1">{{ $diag->name }}</h6>
                                    <p class="text-muted small mb-3">Last updated: {{ $diag->updated_at->diffForHumans() }}</p>
                                </div>
                                <div class="d-flex gap-2 border-top border-secondary border-opacity-20 pt-2">
                                    <a href="{{ route('clients.diagrams.show', [$client->id, $diag->id]) }}" class="btn btn-outline-theme btn-xs flex-grow-1" target="_blank">
                                        <i class="bi bi-pencil-square me-1"></i> Open Editor
                                    </a>
                                </div>
                            </div>
                            <div class="card-arrow">
                                <div class="card-arrow-top-left"></div>
                                <div class="card-arrow-top-right"></div>
                                <div class="card-arrow-bottom-left"></div>
                                <div class="card-arrow-bottom-right"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
    <div class="card-arrow">
        <div class="card-arrow-top-left"></div>
        <div class="card-arrow-top-right"></div>
        <div class="card-arrow-bottom-left"></div>
        <div class="card-arrow-bottom-right"></div>
    </div>
</div>
@endsection

@section('scripts')
<script src="/assets/plugins/apexcharts/dist/apexcharts.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var options = {
            chart: {
                type: 'donut',
                height: 280,
                fontFamily: 'inherit',
                toolbar: { show: false }
            },
            stroke: {
                show: true,
                width: 2,
                colors: ['rgba(0,0,0,0.5)']
            },
            colors: ['#3cd2a5', '#0f766e', '#3b82f6', '#f97316', '#a855f7', '#ec4899', '#eab308', '#6366f1', '#14b8a6'],
            series: {!! json_encode($chartSeries) !!},
            labels: {!! json_encode($chartLabels) !!},
            legend: {
                show: true,
                position: 'bottom',
                labels: {
                    colors: '#fff'
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return val.toFixed(1) + "%"
                }
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function (val) {
                        return val + " GB/day"
                    }
                }
            }
        };

        var chart = new ApexCharts(document.querySelector("#ingestionDonutChart"), options);
        chart.render();

        // Ask AI Sizing Regulator AJAX Handler
        var btnAskAi = document.getElementById("btn-ask-ai");
        var aiCard = document.getElementById("ai-analysis-card");
        var aiContent = document.getElementById("ai-analysis-content");

        if (btnAskAi && aiCard && aiContent) {
            btnAskAi.addEventListener("click", function() {
                aiCard.style.display = "";
                aiContent.innerHTML = `
                    <div class="d-flex flex-column align-items-center justify-content-center py-5">
                        <div class="position-relative mb-4" style="width: 80px; height: 80px;">
                            <img src="/assets/css/images/logo-dark.png" alt="Logo" class="brand-logo-img-dark position-absolute start-50 top-50 translate-middle" style="width: 40px; height: auto; z-index: 10;">
                            <img src="/assets/css/images/logo.png" alt="Logo" class="brand-logo-img-light position-absolute start-50 top-50 translate-middle" style="width: 40px; height: auto; z-index: 10;">
                            <div class="spinner-border text-theme position-absolute top-0 start-0 w-100 h-100" style="border-width: 3px;" role="status"></div>
                        </div>
                        <div class="h5 text-theme text-center mt-2 mb-1" id="ai-loading-step">Initializing Sizing Regulator Agent...</div>
                        <div class="text-muted small text-center" id="ai-loading-desc">Setting up local Ollama connection to gemma4:e2b...</div>
                    </div>
                `;
                btnAskAi.disabled = true;

                var steps = [
                    { title: "Initializing Sizing Regulator Agent...", desc: "Setting up local Ollama connection to gemma4:e2b..." },
                    { title: "Loading Reference Documentation...", desc: "Reading doc/learn/elasticsearch_sizing_standards.md..." },
                    { title: "Reviewing Sizing Calculator Logic...", desc: "Comparing calculated Hot/Warm/Cold storage ratios..." },
                    { title: "Auditing Master Node Specs...", desc: "Checking if quorum master configurations match constraints..." },
                    { title: "Evaluating Cluster Topology...", desc: "Formulating Elasticsearch cluster sizing audit logs..." },
                    { title: "Formatting Proposal Suggestions...", desc: "Structuring final recommendations and pricing notes..." }
                ];
                var stepIndex = 0;
                var stepInterval = setInterval(function() {
                    stepIndex = (stepIndex + 1) % steps.length;
                    var stepTitleEl = document.getElementById("ai-loading-step");
                    var stepDescEl = document.getElementById("ai-loading-desc");
                    if (stepTitleEl && stepDescEl) {
                        stepTitleEl.innerText = steps[stepIndex].title;
                        stepDescEl.innerText = steps[stepIndex].desc;
                    }
                }, 2500);

                fetch("{{ route('sizing.analyze-ai', [$client->id, $scenario->id]) }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    }
                })
                .then(response => response.json())
                .then(data => {
                    clearInterval(stepInterval);
                    btnAskAi.disabled = false;
                    if (data.success) {
                        aiContent.innerHTML = data.html;
                    } else {
                        aiContent.innerHTML = `
                            <div class="alert alert-danger mb-0">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> ${data.message || "Error during AI generation."}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    clearInterval(stepInterval);
                    btnAskAi.disabled = false;
                    aiContent.innerHTML = `
                        <div class="alert alert-danger mb-0">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> AI generation failed: ${error.message}
                        </div>
                    `;
                });
            });
        }

        // Test AI Connection
        var btnTestOllama = document.getElementById("btn-test-ollama");
        var pingContainer = document.getElementById("ollama-ping-toast-container");

        if (btnTestOllama && pingContainer) {
            btnTestOllama.addEventListener("click", function () {
                btnTestOllama.disabled = true;
                btnTestOllama.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Testing...';

                fetch("{{ route('ollama.ping') }}", { method: 'GET' })
                .then(r => r.json())
                .then(data => {
                    btnTestOllama.disabled = false;
                    btnTestOllama.innerHTML = '<i class="bi bi-wifi me-1"></i> Test AI Connection';

                    var providerName = data.provider_name || 'AI';
                    var isOk = data.status === 'ok';
                    var badgeClass = isOk ? 'success' : (data.status === 'warning' ? 'warning' : 'danger');
                    var icon = isOk ? '✅' : '⚠️';

                    var modelsHtml = '';
                    if (data.available_models && data.available_models.length > 0) {
                        modelsHtml = '<div class="mt-2 small"><strong>Available models:</strong><br>' +
                            data.available_models.map(m => `<code class="me-1">${m}</code>`).join('') + '</div>';
                    }

                    var toastId = 'ping-toast-' + Date.now();
                    var toastHtml = `
                        <div id="${toastId}" class="toast show align-items-start border-${badgeClass} border-opacity-50 mb-2"
                            role="alert" style="min-width: 360px; background: #1a2433; color: #e0e8f0;">
                            <div class="toast-header border-${badgeClass} border-opacity-25" style="background: #0f1c2e; color: #e0e8f0;">
                                <strong class="me-auto">${icon} ${providerName} Status</strong>
                                <small class="text-muted">${data.timestamp ? data.timestamp.substring(11, 19) : 'now'}</small>
                                <button type="button" class="btn-close btn-close-white ms-2" onclick="document.getElementById('${toastId}').remove()"></button>
                            </div>
                            <div class="toast-body small">
                                <div><strong>URL:</strong> <code>${data.url || ''}</code></div>
                                <div><strong>Status:</strong> ${data.message}</div>
                                ${data.model_status ? '<div class="mt-1">' + data.model_status + '</div>' : ''}
                                ${modelsHtml}
                            </div>
                        </div>`;

                    pingContainer.insertAdjacentHTML('beforeend', toastHtml);

                    // Auto-remove after 12 seconds
                    setTimeout(() => {
                        var el = document.getElementById(toastId);
                        if (el) el.remove();
                    }, 12000);
                })
                .catch(err => {
                    btnTestOllama.disabled = false;
                    btnTestOllama.innerHTML = '<i class="bi bi-wifi me-1"></i> Test AI Connection';
                    pingContainer.insertAdjacentHTML('beforeend',
                        `<div class="toast show border-danger mb-2" style="min-width:300px;background:#1a2433;color:#e0e8f0;">
                            <div class="toast-body">⚠️ Could not reach Laravel backend: ${err.message}</div>
                        </div>`);
                });
            });
        }
        // Add Node Row
        var btnAddNode = document.getElementById("btn-add-node");
        var nodesTbody = document.getElementById("nodes-tbody");
        if (btnAddNode && nodesTbody) {
            var nodeIndex = {{ count($data['nodes']) }};
            btnAddNode.addEventListener("click", function() {
                var newRow = document.createElement("tr");
                newRow.innerHTML = `
                    <td>
                        <input type="text" name="nodes[${nodeIndex}][name]" value="custom-node" class="form-control form-control-sm bg-black bg-opacity-30 border-secondary border-opacity-30 text-white" required>
                    </td>
                    <td>
                        <input type="text" name="nodes[${nodeIndex}][role]" value="Custom Role" class="form-control form-control-sm bg-black bg-opacity-30 border-secondary border-opacity-30 text-white" required>
                    </td>
                    <td>
                        <input type="number" name="nodes[${nodeIndex}][count]" value="1" min="1" class="form-control form-control-sm bg-black bg-opacity-30 border-secondary border-opacity-30 text-white text-center" required>
                    </td>
                    <td>
                        <input type="number" step="0.1" name="nodes[${nodeIndex}][ram_gb]" value="16.0" min="0.1" class="form-control form-control-sm bg-black bg-opacity-30 border-secondary border-opacity-30 text-white text-center" required>
                    </td>
                    <td>
                        <input type="number" step="0.1" name="nodes[${nodeIndex}][storage_gb]" value="100.0" min="0.1" class="form-control form-control-sm bg-black bg-opacity-30 border-secondary border-opacity-30 text-white text-center" required>
                    </td>
                    <td>
                        <input type="text" name="nodes[${nodeIndex}][storage_type]" value="Local SSD" class="form-control form-control-sm bg-black bg-opacity-30 border-secondary border-opacity-30 text-white" required>
                    </td>
                    <td>
                        <button type="button" class="btn btn-outline-danger btn-sm w-100 delete-node-row">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                `;
                nodesTbody.appendChild(newRow);
                nodeIndex++;
            });
        }

        // Delete Node Row (using event delegation)
        if (nodesTbody) {
            nodesTbody.addEventListener("click", function(e) {
                if (e.target.classList.contains("delete-node-row") || e.target.closest(".delete-node-row")) {
                    var btn = e.target.classList.contains("delete-node-row") ? e.target : e.target.closest(".delete-node-row");
                    var tr = btn.closest("tr");
                    if (tr) {
                        tr.remove();
                    }
                }
            });
        }

        // Sync Sizing Diagrams Handler
        var btnSyncDiag = document.getElementById("btn-sync-diagrams");
        if (btnSyncDiag) {
            btnSyncDiag.addEventListener("click", function() {
                btnSyncDiag.disabled = true;
                btnSyncDiag.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Syncing...';

                fetch("{{ route('sizing.sync-diagrams', [$client->id, $scenario->id]) }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    }
                })
                .then(response => response.json())
                .then(data => {
                    btnSyncDiag.disabled = false;
                    btnSyncDiag.innerHTML = '<i class="bi bi-diagram-3 me-1"></i> Sync Diagrams';
                    
                    if (data.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: 'Sizing diagrams generated and synchronized successfully.',
                            icon: 'success',
                            confirmButtonColor: '#3cd2a5',
                            background: '#1a2433',
                            color: '#e0e8f0'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: data.message || 'Error generating sizing diagrams.',
                            icon: 'error',
                            confirmButtonColor: '#ff5b57',
                            background: '#1a2433',
                            color: '#e0e8f0'
                        });
                    }
                })
                .catch(err => {
                    btnSyncDiag.disabled = false;
                    btnSyncDiag.innerHTML = '<i class="bi bi-diagram-3 me-1"></i> Sync Diagrams';
                    Swal.fire({
                        title: 'Error!',
                        text: 'Network error or failed to sync: ' + err.message,
                        icon: 'error',
                        confirmButtonColor: '#ff5b57',
                        background: '#1a2433',
                        color: '#e0e8f0'
                    });
                });
            });
        }
    });
</script>
@endsection
