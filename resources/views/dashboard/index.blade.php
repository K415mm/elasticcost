@extends('layouts.app')

@section('title', __('messages.dashboard') ?: 'Dashboard')

@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item active">{{ strtoupper(__('messages.dashboard') ?: 'DASHBOARD') }}</li>
</ul>

<div class="d-flex align-items-center mb-4">
    <div class="flex-grow-1">
        <h1 class="page-header mb-0">
            {{ __('messages.dashboard') ?: 'Dashboard' }}
            <small class="d-block mt-1">Multi-Tier Elasticsearch Sizing & Costing Intelligence Platform</small>
        </h1>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('ai-chat.index') }}" class="btn btn-outline-theme">
            <i class="bi bi-chat-dots-fill me-1 text-theme"></i> {{ __('messages.ai_chat') ?: 'AI Chat Assistant' }}
        </a>
        <button type="button" class="btn btn-theme" data-bs-toggle="modal" data-bs-target="#modalInitProject">
            <i class="bi bi-plus-circle me-1"></i> {{ __('messages.initialize_project') ?: 'New Client Project' }}
        </button>
    </div>
</div>

<!-- Row 1: KPI Stats Grid -->
<div class="row">
    <!-- KPI 1: Active Clients -->
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex fw-bold small mb-2 text-inverse text-opacity-50">
                    <span class="flex-grow-1">CLIENT PROJECTS</span>
                    <i class="bi bi-people-fill text-theme fs-14px"></i>
                </div>
                <div class="row align-items-center">
                    <div class="col-12">
                        <h3 class="mb-1 text-white mono-cell">{{ number_format($totalClients) }}</h3>
                        <div class="small text-muted text-truncate">
                            <a href="{{ route('clients.index') }}" class="text-theme text-decoration-none">View Portfolio <i class="bi bi-arrow-right"></i></a>
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
    </div>

    <!-- KPI 2: Total Devices -->
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex fw-bold small mb-2 text-inverse text-opacity-50">
                    <span class="flex-grow-1">FLEET DEVICES</span>
                    <i class="bi bi-cpu-fill text-info fs-14px"></i>
                </div>
                <div class="row align-items-center">
                    <div class="col-12">
                        <h3 class="mb-1 text-white mono-cell">{{ number_format($totalDevices) }}</h3>
                        <div class="small text-muted text-truncate">
                            Total monitored assets
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
    </div>

    <!-- KPI 3: Daily Ingestion -->
    <div class="col-xl-3 col-md-4 col-sm-12 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex fw-bold small mb-2 text-inverse text-opacity-50">
                    <span class="flex-grow-1">DAILY RAW INGESTION</span>
                    <i class="bi bi-database-fill-down text-warning fs-14px"></i>
                </div>
                <div class="row align-items-center">
                    <div class="col-12">
                        <h3 class="mb-1 text-white mono-cell">{{ number_format($totalDailyRawGb, 2) }} <span class="fs-12px">GB/day</span></h3>
                        <div class="small text-muted text-truncate">
                            Overall raw volume (Avg profile)
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
    </div>

    <!-- KPI 4: Sized Cluster RAM -->
    <div class="col-xl-2 col-md-6 col-sm-6 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex fw-bold small mb-2 text-inverse text-opacity-50">
                    <span class="flex-grow-1">SIZED RAM TOTAL</span>
                    <i class="bi bi-memory text-danger fs-14px"></i>
                </div>
                <div class="row align-items-center">
                    <div class="col-12">
                        <h3 class="mb-1 text-white mono-cell">{{ number_format($totalClusterRamGb) }} <span class="fs-12px">GB</span></h3>
                        <div class="small text-muted text-truncate">
                            Total VM Memory footprint
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
    </div>

    <!-- KPI 5: Sized ERUs -->
    <div class="col-xl-3 col-md-6 col-sm-6 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex fw-bold small mb-2 text-inverse text-opacity-50">
                    <span class="flex-grow-1">LICENSE footprint</span>
                    <i class="bi bi-shield-lock-fill text-success fs-14px"></i>
                </div>
                <div class="row align-items-center">
                    <div class="col-12">
                        <h3 class="mb-1 text-success mono-cell">{{ number_format($totalRequiredErus) }} <span class="fs-12px text-white text-opacity-65">ERUs</span></h3>
                        <div class="small text-muted text-truncate">
                            Elastic Resource Units required
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
    </div>
</div>

<div class="row mt-2">
    <!-- Left Column: Client Portfolio Table & Scenarios -->
    <div class="col-xl-8 col-lg-12 mb-4">
        <!-- Client Portfolio Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <h5 class="card-title text-theme mb-0">
                        <i class="bi bi-journal-text me-2"></i> Client Sizing & Costing Portfolio
                    </h5>
                    <span class="badge badge-hot ms-2">Default Scenario: Scenario 2 (Average Workload)</span>
                </div>
                
                @if(count($clientSummaries) === 0)
                    <div class="text-center py-5">
                        <i class="bi bi-folder2-open display-4 opacity-30 text-muted"></i>
                        <p class="mt-3 mb-0">{{ __('messages.no_client_projects') ?: 'No client projects created yet.' }}</p>
                        <button type="button" class="btn btn-outline-theme btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#modalInitProject">
                            Create First Project
                        </button>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle mb-0">
                            <thead>
                                <tr class="bg-secondary bg-opacity-10 text-white">
                                    <th>Client / Project</th>
                                    <th class="text-center">Devices</th>
                                    <th class="text-end">Raw GB/day</th>
                                    <th class="text-center">Cluster RAM</th>
                                    <th class="text-center">Required ERUs</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($clientSummaries as $summary)
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-white">{{ $summary['client_name'] }}</div>
                                            <small class="text-muted text-truncate d-block" style="max-width: 250px;">
                                                {{ $summary['client_description'] ?: 'No scope description provided' }}
                                            </small>
                                        </td>
                                        <td class="text-center mono-cell">{{ number_format($summary['device_count']) }}</td>
                                        <td class="text-end mono-cell">{{ number_format($summary['daily_raw_gb'], 2) }}</td>
                                        <td class="text-center mono-cell">
                                            @if($summary['cluster_ram_gb'] > 0)
                                                <span class="badge bg-secondary bg-opacity-20 text-white px-2 py-1">{{ $summary['cluster_ram_gb'] }} GB</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($summary['required_erus'] > 0)
                                                <span class="badge bg-success bg-opacity-20 text-success fw-bold px-2 py-1">{{ $summary['required_erus'] }} ERUs</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <a href="{{ route('sizing.show', [$summary['client_id'], $summary['default_scenario_id'] ?? 2]) }}" 
                                                   class="btn btn-sm btn-outline-theme">
                                                    <i class="bi bi-calculator me-1"></i> Sizing
                                                </a>
                                                <a href="{{ route('mssp.show', [$summary['client_id'], $summary['default_scenario_id'] ?? 2]) }}" 
                                                   class="btn btn-sm btn-outline-info">
                                                    <i class="bi bi-wallet2 me-1"></i> Costing
                                                </a>
                                                <a href="{{ route('clients.index') }}" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
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

        <!-- Sizing Scenarios Card -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title text-theme mb-3">
                    <i class="bi bi-calendar3 me-2"></i> Standard Sizing Scenarios
                </h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle text-muted small mb-0">
                        <thead>
                            <tr class="text-white text-opacity-80">
                                <th>Scenario Template</th>
                                <th>Workload Profile</th>
                                <th class="text-center">Total Days</th>
                                <th class="text-center">Hot (Days/Replica)</th>
                                <th class="text-center">Warm (Days/Replica)</th>
                                <th class="text-center">Cold (Days/Replica)</th>
                                <th class="text-center">Frozen (Days/Replica)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($scenarios as $scen)
                                <tr>
                                    <td class="fw-bold text-white">{{ $scen->name }}</td>
                                    <td>{{ ucfirst($scen->workload_profile) }}</td>
                                    <td class="text-center text-white mono-cell">{{ $scen->days_retention }} days</td>
                                    <td class="text-center">
                                        {{ $scen->hot_days }}d / {{ $scen->hot_replicas }}r
                                    </td>
                                    <td class="text-center">
                                        @if($scen->warm_days > 0)
                                            {{ $scen->warm_days }}d / {{ $scen->warm_replicas }}r
                                        @else
                                            <span class="opacity-30">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($scen->cold_days > 0)
                                            {{ $scen->cold_days }}d / {{ $scen->cold_replicas }}r
                                        @else
                                            <span class="opacity-30">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($scen->frozen_days > 0)
                                            {{ $scen->frozen_days }}d / {{ $scen->frozen_replicas }}r
                                        @else
                                            <span class="opacity-30">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
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

    <!-- Right Column: AI Assistant Card & Exchange Rates -->
    <div class="col-xl-4 col-lg-12 mb-4">
        <!-- AI Chat Bot Card -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title text-theme mb-3">
                    <i class="bi bi-chat-square-quote me-2"></i> AI Cost & Sizing Analyst
                </h5>
                <p class="small text-muted mb-4">
                    Get real-time insights from our local LLM agent (`gemma4:e2b`). Review cluster limits, calculate disk-to-RAM configurations, or analyze staffing margin formulas dynamically.
                </p>

                <!-- Ping status widget -->
                <div class="bg-secondary bg-opacity-10 p-3 rounded mb-4 border border-secondary border-opacity-25">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="small fw-bold">{{ $aiProviderName }} Connection:</div>
                        <span id="ollama-status-badge" class="badge bg-secondary bg-opacity-25 text-white">Checking...</span>
                    </div>
                    <div id="ollama-details" class="small text-muted mt-2">Connecting to {{ $aiProviderName }} API endpoint...</div>
                </div>

                <div class="d-grid gap-2">
                    <a href="{{ route('ai-chat.index') }}" class="btn btn-outline-theme py-2 fs-13px fw-bold">
                        <i class="bi bi-chat-text-fill me-1"></i> Open AI Chat Assistant
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

        <!-- Active exchange rates -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title text-theme mb-3">
                    <i class="bi bi-currency-exchange me-2"></i> Global Rate Cards
                </h5>
                <p class="small text-muted mb-4">
                    Active rate matrices for dynamic currency conversion in proposals.
                </p>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle text-muted mb-0 small">
                        <tbody>
                            <tr>
                                <td>Base Currency</td>
                                <td class="text-end fw-bold text-white mono-cell">USD ($)</td>
                            </tr>
                            <tr>
                                <td>Euro Rate</td>
                                <td class="text-end fw-bold text-white mono-cell">1 USD = {{ number_format($eurRate, 4) }} EUR</td>
                            </tr>
                            <tr>
                                <td>Tunisian Dinar Rate</td>
                                <td class="text-end fw-bold text-white mono-cell">1 USD = {{ number_format($tndRate, 4) }} TND</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <a href="{{ route('settings.system') }}" class="btn btn-outline-secondary w-100 btn-sm">
                        <i class="bi bi-gear me-1"></i> Manage Rates & Settings
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
</div>

<!-- Modal: Initialize Client Project -->
<div class="modal fade" id="modalInitProject" tabindex="-1" aria-labelledby="modalInitProjectLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border border-secondary">
            <div class="modal-header bg-secondary bg-opacity-10 border-bottom border-secondary border-opacity-35">
                <h5 class="modal-title text-white" id="modalInitProjectLabel">
                    <i class="bi bi-folder-plus text-theme me-2"></i> {{ __('messages.initialize_project') ?: 'Initialize Client Project' }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('clients.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-white small">{{ __('messages.client_project_name') ?: 'Client / Project Name' }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g., Enterprise SOC Cluster" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label text-white small">{{ __('messages.scope_description') ?: 'Scope / Description' }}</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Optional scope notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-secondary bg-opacity-10 border-top border-secondary border-opacity-35">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('messages.close') ?: 'Close' }}</button>
                    <button type="submit" class="btn btn-theme">{{ __('messages.initialize_sizing_profile') ?: 'Initialize Sizing Profile' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function () {
        // Dynamic active AI provider health check
        var statusBadge = document.getElementById("ollama-status-badge");
        var statusDetails = document.getElementById("ollama-details");

        if (statusBadge && statusDetails) {
            fetch("{{ route('ollama.ping') }}")
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.status === 'ok') {
                        statusBadge.className = "badge bg-success bg-opacity-25 text-success";
                        statusBadge.innerText = "Online";
                        statusDetails.innerText = data.model_status || data.message || "AI server is online and active.";
                    } else {
                        statusBadge.className = "badge bg-warning bg-opacity-25 text-warning";
                        statusBadge.innerText = "Warning";
                        statusDetails.innerText = data.message || "AI server responded with warnings.";
                    }
                })
                .catch(function(err) {
                    statusBadge.className = "badge bg-danger bg-opacity-25 text-danger";
                    statusBadge.innerText = "Offline";
                    statusDetails.innerText = "Failed to connect to the active AI service backend. Verify your URL and connectivity.";
                });
        }
    });
</script>
@endsection
