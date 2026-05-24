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
    });
</script>
@endsection
