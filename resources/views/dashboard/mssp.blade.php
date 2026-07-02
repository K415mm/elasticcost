@extends('layouts.app')

@section('title', __('messages.soc_cost_proposal') . ": {$client->name} - {$scenario->name}")

@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">{{ __('messages.clients') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('clients.show', $client->id) }}">{{ strtoupper($client->name) }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('sizing.show', [$client->id, $scenario->id]) }}">{{ __('messages.sizing_calculator') }}</a></li>
    <li class="breadcrumb-item active">{{ __('messages.soc_cost_proposal') }}</li>
</ul>

<div class="d-flex align-items-center mb-4">
    <div class="flex-grow-1">
        <h1 class="page-header mb-0">
            {{ __('messages.mssp_soc_cost_proposal') }} <small class="d-block mt-1">{{ $scenario->name }} ({{ $scenario->description }})</small>
        </h1>
    </div>
    <div class="d-flex gap-2">
        <button id="btn-test-ollama" type="button" class="btn btn-outline-warning" title="Test AI Connection">
            <i class="fa fa-satellite-dish me-1"></i> Test AI Connection
        </button>
        <button id="btn-ask-ai" type="button" class="btn btn-outline-info">
            <i class="fa fa-robot me-1"></i> {{ __('messages.ask_ai') }}
        </button>
        <a href="{{ route('mssp.export.excel', [$client->id, $scenario->id]) }}" class="btn btn-outline-success">
            <i class="fa fa-file-excel me-1"></i> {{ __('messages.export_xlsx') }}
        </a>
        <a href="{{ route('mssp.export.word', [$client->id, $scenario->id]) }}" class="btn btn-outline-theme">
            <i class="fa fa-file-word me-1"></i> {{ __('messages.download_docx') }}
        </a>
        <a href="{{ route('mssp.export.markdown', [$client->id, $scenario->id]) }}" class="btn btn-outline-theme">
            <i class="fa fa-file-code me-1"></i> {{ __('messages.download_md') }}
        </a>
        <a href="{{ route('sizing.show', [$client->id, $scenario->id]) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> {{ __('messages.close') }}
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <strong>{{ __('messages.success') }}!</strong> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<!-- Option A vs Option B Side-by-Side Comparison Grid -->
<div class="row mb-4 g-3">
    <!-- Option A: On-Premise Deployment Offer -->
    <div class="col-lg-6">
        <div class="card h-100 border-theme border-opacity-40" style="background: linear-gradient(135deg, rgba(var(--bs-theme-rgb), 0.1), transparent);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-theme bg-opacity-25 text-theme-light border border-theme border-opacity-40 px-2 py-1 uppercase-tracking">Option A</span>
                    <h5 class="mb-0 text-white fw-bold"><i class="bi bi-server me-2"></i> On-Premise Deployment Offer</h5>
                </div>
                <div class="border-top border-secondary border-opacity-20 pt-3 mb-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Upfront Setup Fee:</span>
                        <strong class="mono-cell text-white">{{ \App\Services\CurrencyHelper::format($costData['onetime_setup_cost']) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Monthly Base Cost:</span>
                        <strong class="mono-cell text-white">{{ \App\Services\CurrencyHelper::format($costData['total_monthly_service_cost']) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Commercial Markup (+{{ $costData['total_profit_percentage'] }}%):</span>
                        <strong class="mono-cell text-success">+{{ \App\Services\CurrencyHelper::format($costData['total_profit_amount']) }}</strong>
                    </div>
                </div>
                <div class="border-top border-secondary border-opacity-20 pt-3 d-flex justify-content-between align-items-baseline">
                    <span class="text-theme fw-bold">Client Offered Price:</span>
                    <div class="text-end">
                        <span class="fs-28px fw-bold text-white mono-cell text-theme">{{ \App\Services\CurrencyHelper::format($costData['client_offered_price_mrc']) }}</span>
                        <span class="text-muted small">/mo</span>
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

    <!-- Option B: Elastic Cloud Deployment Offer -->
    <div class="col-lg-6">
        <div class="card h-100 border-info border-opacity-40" style="background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), transparent);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-info bg-opacity-25 text-info-light border border-info border-opacity-40 px-2 py-1 uppercase-tracking">Option B</span>
                    <h5 class="mb-0 text-white fw-bold"><i class="bi bi-cloud me-2"></i> Elastic Cloud Deployment Offer</h5>
                </div>
                <div class="border-top border-secondary border-opacity-20 pt-3 mb-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Upfront Setup Fee:</span>
                        <strong class="mono-cell text-white">{{ \App\Services\CurrencyHelper::format($costData['onetime_setup_cost']) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Monthly Agent Cost:</span>
                        <strong class="mono-cell text-white">{{ \App\Services\CurrencyHelper::format($costData['cloud_option']['total_agents_monthly_cost']) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Commercial Markup:</span>
                        <strong class="mono-cell text-success">$0.00 <span class="small text-muted" style="font-size: 10px;">(Agent rates include everything)</span></strong>
                    </div>
                </div>
                <div class="border-top border-secondary border-opacity-20 pt-3 d-flex justify-content-between align-items-baseline">
                    <span class="text-info-light fw-bold">Client Offered Price:</span>
                    <div class="text-end">
                        <span class="fs-28px fw-bold text-white mono-cell text-info-light">{{ \App\Services\CurrencyHelper::format($costData['cloud_option']['client_offered_price_mrc']) }}</span>
                        <span class="text-muted small">/mo</span>
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

<!-- 1. Cost Overview Metrics -->
<div class="row mb-4">
    <!-- One-Time Setup -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold mb-2 uppercase-tracking">
                    <span>🛠️</span> {{ __('messages.upfront_setup_cost') }}
                </div>
                <div class="d-flex align-items-baseline mb-2">
                    <span class="fs-24px fw-bold text-white mono-cell">{{ \App\Services\CurrencyHelper::format($costData['onetime_setup_cost']) }}</span>
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

    <!-- Monthly Staffing -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold mb-2 uppercase-tracking">
                    <span>👥</span> {{ __('messages.monthly_staffing_soc') }}
                </div>
                <div class="d-flex align-items-baseline mb-2">
                    <span class="fs-24px fw-bold text-white mono-cell">{{ \App\Services\CurrencyHelper::format($costData['analysts']['total_monthly_analyst_cost']) }}</span>
                    <span class="text-muted ms-2 fs-12px">/mo</span>
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

    <!-- Monthly Infrastructure -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold mb-2 uppercase-tracking">
                    <span>💾</span> {{ __('messages.monthly_hosting_vms') }}
                </div>
                <div class="d-flex align-items-baseline mb-2">
                    <span class="fs-24px fw-bold text-white mono-cell">{{ \App\Services\CurrencyHelper::format($costData['infrastructure']['total_monthly_infra_cost']) }}</span>
                    <span class="text-muted ms-2 fs-12px">/mo</span>
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

    <!-- Monthly License -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold mb-2 uppercase-tracking">
                    <span>🎟️</span> {{ __('messages.monthly_license_eru') }}
                </div>
                <div class="d-flex align-items-baseline mb-2">
                    <span class="fs-24px fw-bold text-white mono-cell">{{ \App\Services\CurrencyHelper::format($costData['sizing_summary']['monthly_license_usd']) }}</span>
                    <span class="text-muted ms-2 fs-12px">/mo</span>
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
    $costLabels = [
        __('messages.monthly_staffing_soc') ?: 'Staffing',
        __('messages.monthly_hosting_vms') ?: 'VM Hosting',
        __('messages.monthly_license_eru') ?: 'Licensing',
        __('messages.maintenance_fee_monthly') ?: 'Maintenance',
        __('messages.total_profit_margin') ?: 'Profit Margin'
    ];
    $costSeries = [
        (float) $costData['analysts']['total_monthly_analyst_cost'],
        (float) $costData['infrastructure']['total_monthly_infra_cost'],
        (float) $costData['sizing_summary']['monthly_license_usd'],
        (float) $costData['monthly_maintenance_cost'],
        (float) $costData['total_profit_amount']
    ];
@endphp

<!-- Total Cost Banner -->
<div class="card mb-4 border-theme border-opacity-40" style="background: linear-gradient(135deg, rgba(var(--bs-theme-rgb), 0.1), transparent);">
    <div class="card-body py-4">
        <div class="row align-items-center">
            <div class="col-xl-8 col-lg-7 border-end border-secondary border-opacity-20 pe-lg-4">
                <div class="row align-items-center">
                    <div class="col-md-8 col-sm-12">
                        <h4 class="text-theme fw-bold mb-1">{{ __('messages.total_monthly_service_fee') }}</h4>
                        <p class="text-muted mb-0 small">
                            This proposal covers SOC analyst staffing, licensed node compute, cloud hosting infrastructure, operational maintenance, and dynamic commercial profit markups.
                        </p>
                        <div class="mt-2 text-muted small">
                            <span>📈</span> {{ __('messages.total_profit_margin') }}: <strong class="text-success mono-cell">+{{ \App\Services\CurrencyHelper::format($costData['total_profit_amount']) }}</strong> (+{{ $costData['total_profit_percentage'] }}%)
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12 text-md-end text-sm-start mt-md-0 mt-3">
                        <div class="d-inline-block text-start">
                            <div class="text-muted small uppercase-tracking">{{ __('messages.client_offered_mrc') }}:</div>
                            <div class="d-flex align-items-baseline">
                                <span class="fs-32px fw-bold text-white mono-cell text-theme">{{ \App\Services\CurrencyHelper::format($costData['client_offered_price_mrc']) }}</span>
                            </div>
                            <div class="text-muted fs-11px mt-1">{{ __('messages.base_cost_mrc') }}: <span class="mono-cell">{{ \App\Services\CurrencyHelper::format($costData['total_monthly_service_cost']) }}</span></div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Info Items underneath -->
                <div class="row mt-4 pt-3 border-top border-secondary border-opacity-10 small text-muted">
                    <div class="col-md-3 col-sm-6 mb-2">
                        👥 {{ __('messages.monthly_staffing_soc') }}: <strong class="text-white mono-cell">{{ \App\Services\CurrencyHelper::format($costData['analysts']['total_monthly_analyst_cost']) }}</strong>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        💾 {{ __('messages.monthly_hosting_vms') }}: <strong class="text-white mono-cell">{{ \App\Services\CurrencyHelper::format($costData['infrastructure']['total_monthly_infra_cost']) }}</strong>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        🎟️ {{ __('messages.monthly_license_eru') }}: <strong class="text-white mono-cell">{{ \App\Services\CurrencyHelper::format($costData['sizing_summary']['monthly_license_usd']) }}</strong>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        🔧 {{ __('messages.maintenance_fee_monthly') }}: <strong class="text-white mono-cell">{{ \App\Services\CurrencyHelper::format($costData['monthly_maintenance_cost']) }}</strong>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-5 ps-lg-4 mt-4 mt-lg-0">
                <div class="d-flex align-items-center justify-content-center" style="min-height: 180px;">
                    <div id="costDonutChart" style="width: 100%;"></div>
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

<style>
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: .4; }
    }
    .animate-pulse {
        animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>

<!-- Ollama Ping Toast -->
<div id="ollama-ping-toast-container" style="position: fixed; top: 80px; right: 20px; z-index: 9999;"></div>

<!-- AI Analysis Card -->
<div class="card mb-4 border-info border-opacity-40" id="ai-analysis-card" style="{{ empty($costData['raw_mssp_detail']->ai_analysis) ? 'display: none;' : '' }}">
    <div class="card-body py-4">
        <h5 class="card-title text-info mb-3">
            <i class="fa fa-robot me-2"></i> {{ __('messages.ai_analysis') }}
        </h5>
        <div id="ai-analysis-content" class="text-white text-opacity-80 leading-relaxed markdown-body">
            @if(!empty($costData['raw_mssp_detail']->ai_analysis))
                {!! \Illuminate\Support\Str::markdown($costData['raw_mssp_detail']->ai_analysis) !!}
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

<form action="{{ route('mssp.update', [$client->id, $scenario->id]) }}" method="POST">
    @csrf
    <div class="row">
        <!-- Left Column: Staffing and Allocation Controls -->
        <div class="col-xl-8 col-lg-12 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title text-theme mb-3">
                        <i class="bi bi-people-fill me-2"></i> {{ __('messages.soc_staffing_dedication_allocations') }}
                    </h5>
                    
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead>
                                <tr class="border-bottom text-muted small uppercase-tracking">
                                    <th style="width: 30%;">{{ __('messages.operational_role') }}</th>
                                    <th style="width: 20%;">{{ __('messages.dedication_allocation') }}</th>
                                    <th style="width: 15%;">{{ __('messages.staff_count') }}</th>
                                    <th style="width: 20%;">{{ __('messages.monthly_salary_override') }} ({{ \App\Services\CurrencyHelper::symbol() }})</th>
                                    <th class="text-end" style="width: 15%;">{{ __('messages.calculated_cost') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($costData['analysts']['roles'] as $role)
                                    <tr>
                                        <td>
                                            <strong class="text-white">{{ $role['name'] }}</strong>
                                            <div class="small text-muted mt-1" style="font-size: 11px;">
                                                {{ $role['description'] }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm" style="max-width: 140px;">
                                                <input type="number" step="0.1" name="allocations[{{ $role['role_id'] }}][percentage]" class="form-control mono-cell" value="{{ $role['allocation_percentage'] }}" min="0" max="100" required>
                                                <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm" style="max-width: 100px;">
                                                <input type="number" name="allocations[{{ $role['role_id'] }}][staff_count]" class="form-control mono-cell" value="{{ $role['staff_count'] ?? 1 }}" min="1" required>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm" style="max-width: 160px;">
                                                <input type="number" step="{{ session('currency') === 'TND' ? '0.001' : '0.01' }}" name="allocations[{{ $role['role_id'] }}][custom_salary]" class="form-control mono-cell" value="{{ round(\App\Services\CurrencyHelper::convert($role['monthly_salary']), session('currency') === 'TND' ? 3 : 2) }}">
                                            </div>
                                        </td>
                                        <td class="text-end fs-15px fw-bold text-white mono-cell">
                                            {{ \App\Services\CurrencyHelper::format($role['client_cost']) }}
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

        <!-- Right Column: Settings & Rate Card Config -->
        <div class="col-xl-4 col-lg-12 mb-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h5 class="card-title text-theme mb-3">
                            <i class="bi bi-sliders me-2"></i> {{ __('messages.costing_parameters') }}
                        </h5>

                        <!-- Cloud Datacenter Partner -->
                        <div class="mb-4">
                            <label class="form-label text-muted small uppercase-tracking">Cloud Datacenter Partner</label>
                            <select name="cloud_datacenter" id="cloud_datacenter_select" class="form-select form-select-sm">
                                <option value="" {{ empty($costData['raw_mssp_detail']->cloud_datacenter) ? 'selected' : '' }}>None (Use Generic Rates)</option>
                                <option value="Dataxion" {{ $costData['raw_mssp_detail']->cloud_datacenter === 'Dataxion' ? 'selected' : '' }}>Dataxion (Cloud Partner)</option>
                                <option value="TT" {{ $costData['raw_mssp_detail']->cloud_datacenter === 'TT' ? 'selected' : '' }}>TT (Cloud Partner)</option>
                            </select>
                        </div>
                        <!-- Flat Fees -->
                        <div class="mb-4">
                            <label class="form-label text-muted small uppercase-tracking">{{ __('messages.flat_fees') }}</label>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small text-muted">{{ __('messages.setup_fee_one_time') }} ({{ \App\Services\CurrencyHelper::symbol() }})</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="{{ session('currency') === 'TND' ? '0.001' : '0.01' }}" name="one_time_setup_cost" class="form-control mono-cell" value="{{ round(\App\Services\CurrencyHelper::convert($costData['onetime_setup_cost']), session('currency') === 'TND' ? 3 : 2) }}" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-muted">{{ __('messages.maintenance_fee_monthly') }} ({{ \App\Services\CurrencyHelper::symbol() }})</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="{{ session('currency') === 'TND' ? '0.001' : '0.01' }}" name="monthly_maintenance_cost" class="form-control mono-cell" value="{{ round(\App\Services\CurrencyHelper::convert($costData['monthly_maintenance_cost']), session('currency') === 'TND' ? 3 : 2) }}" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- License Sharing -->
                        <div class="mb-4 border-top border-secondary border-opacity-20 pt-3">
                            <label class="form-label text-theme small uppercase-tracking">{{ __('messages.license_sharing') }}</label>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="is_license_shared" name="is_license_shared" value="1" {{ $costData['raw_mssp_detail']->is_license_shared ? 'checked' : '' }}>
                                <label class="form-check-label text-white small" for="is_license_shared">{{ __('messages.is_license_shared') }}</label>
                            </div>
                            <div class="mb-3" id="license_share_pct_container" style="{{ $costData['raw_mssp_detail']->is_license_shared ? '' : 'display: none;' }}">
                                <label class="form-label small text-muted">{{ __('messages.license_share_percentage') }}</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" name="license_share_percentage" class="form-control mono-cell" value="{{ $costData['raw_mssp_detail']->license_share_percentage }}" min="0.01" max="100.00" required>
                                    <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Profit Margins -->
                        <div class="mb-4 border-top border-secondary border-opacity-20 pt-3">
                            <label class="form-label text-theme small uppercase-tracking">{{ __('messages.profit_margins') }}</label>
                            
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small text-muted">{{ __('messages.assurance_benefit') }}</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" name="assurance_benefit_percentage" class="form-control mono-cell" value="{{ $costData['assurance_benefit_percentage'] }}" min="0" max="100" required>
                                        <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">%</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-muted">{{ __('messages.marketing_benefit') }}</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" name="marketing_benefit_percentage" class="form-control mono-cell" value="{{ $costData['marketing_benefit_percentage'] }}" min="0" max="100" required>
                                        <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">%</span>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small text-muted">{{ __('messages.soc_manager_benefit') }}</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" name="soc_manager_benefit_percentage" class="form-control mono-cell" value="{{ $costData['soc_manager_benefit_percentage'] }}" min="0" max="100" required>
                                        <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">%</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-muted">{{ __('messages.ceo_benefit') }}</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" name="ceo_benefit_percentage" class="form-control mono-cell" value="{{ $costData['ceo_benefit_percentage'] }}" min="0" max="100" required>
                                        <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">%</span>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-12">
                                    <label class="form-label small text-muted">{{ __('messages.fixed_profit') }}</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" name="fixed_profit_percentage" class="form-control mono-cell" value="{{ $costData['fixed_profit_percentage'] }}" min="0" max="100" required>
                                        <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">%</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Infrastructure Rate Card -->
                        <div class="border-top border-secondary border-opacity-20 pt-3" id="generic_rate_card_container" style="{{ !empty($costData['raw_mssp_detail']->cloud_datacenter) ? 'opacity: 0.6;' : '' }}">
                            <div class="d-flex align-items-center mb-2">
                                <label class="form-label text-theme small uppercase-tracking mb-0">{{ __('messages.vm_hosting_rate_card') }}</label>
                                <span id="generic_rates_ignored_badge" class="badge bg-warning text-dark ms-2" style="font-size: 9px; {{ empty($costData['raw_mssp_detail']->cloud_datacenter) ? 'display: none;' : '' }}">Ignored in Cloud mode</span>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small text-muted">{{ __('messages.ram_cost_per_gb') }} ({{ \App\Services\CurrencyHelper::symbol() }})</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.0001" name="ram_monthly_cost_per_gb" class="form-control mono-cell" value="{{ round(\App\Services\CurrencyHelper::convert($costData['rates']['ram_monthly_cost_per_gb']), 4) }}" required>
                                    <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">/GB</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">{{ __('messages.local_ssd_disk') }} ({{ \App\Services\CurrencyHelper::symbol() }})</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.0001" name="local_ssd_monthly_cost_per_gb" class="form-control mono-cell" value="{{ round(\App\Services\CurrencyHelper::convert($costData['rates']['local_ssd_monthly_cost_per_gb']), 4) }}" required>
                                    <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">/GB</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">{{ __('messages.nvme_ssd_disk') }} ({{ \App\Services\CurrencyHelper::symbol() }})</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.0001" name="nvme_ssd_monthly_cost_per_gb" class="form-control mono-cell" value="{{ round(\App\Services\CurrencyHelper::convert($costData['rates']['nvme_ssd_monthly_cost_per_gb']), 4) }}" required>
                                    <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">/GB</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">{{ __('messages.sata_ssd_hdd_disk') }} ({{ \App\Services\CurrencyHelper::symbol() }})</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.0001" name="sata_ssd_monthly_cost_per_gb" class="form-control mono-cell" value="{{ round(\App\Services\CurrencyHelper::convert($costData['rates']['sata_ssd_monthly_cost_per_gb']), 4) }}" required>
                                    <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">/GB</span>
                                </div>
                            </div>
                        </div>

                        <!-- Elastic Cloud & Agent Rate Card -->
                        <div class="border-top border-secondary border-opacity-20 pt-3 mb-3">
                            <label class="form-label text-theme small uppercase-tracking">Elastic Cloud & Agent Rate Card</label>
                            
                            <div class="mb-3">
                                <label class="form-label small text-muted">Elastic Cloud Subscription Tier</label>
                                <select name="elastic_cloud_subscription_tier" class="form-select form-select-sm">
                                    <option value="standard" {{ $costData['cloud_option']['elastic_cloud_subscription_tier'] === 'standard' ? 'selected' : '' }}>Standard</option>
                                    <option value="gold" {{ $costData['cloud_option']['elastic_cloud_subscription_tier'] === 'gold' ? 'selected' : '' }}>Gold</option>
                                    <option value="platinum" {{ $costData['cloud_option']['elastic_cloud_subscription_tier'] === 'platinum' ? 'selected' : '' }}>Platinum</option>
                                    <option value="enterprise" {{ $costData['cloud_option']['elastic_cloud_subscription_tier'] === 'enterprise' ? 'selected' : '' }}>Enterprise</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Elastic Cloud Flat Rate ({{ \App\Services\CurrencyHelper::symbol() }} - Reference Only)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" name="elastic_cloud_monthly_cost_per_gb_ram" class="form-control mono-cell" value="{{ round(\App\Services\CurrencyHelper::convert($costData['cloud_option']['elastic_cloud_monthly_cost_per_gb_ram']), 2) }}" required>
                                    <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">/GB RAM</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">SIEM Agent Monthly Unit Price ({{ \App\Services\CurrencyHelper::symbol() }})</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" name="siem_agent_monthly_cost_per_device" class="form-control mono-cell" value="{{ round(\App\Services\CurrencyHelper::convert($costData['cloud_option']['siem_agent_monthly_cost_per_device']), 2) }}" required>
                                    <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">/device</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">MDR Agent Monthly Unit Price ({{ \App\Services\CurrencyHelper::symbol() }})</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" name="mdr_agent_monthly_cost_per_device" class="form-control mono-cell" value="{{ round(\App\Services\CurrencyHelper::convert($costData['cloud_option']['mdr_agent_monthly_cost_per_device']), 2) }}" required>
                                    <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">/device</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">EDR Agent Monthly Unit Price ({{ \App\Services\CurrencyHelper::symbol() }})</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" name="edr_agent_monthly_cost_per_device" class="form-control mono-cell" value="{{ round(\App\Services\CurrencyHelper::convert($costData['cloud_option']['edr_agent_monthly_cost_per_device']), 2) }}" required>
                                    <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">/device</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 border-top border-secondary border-opacity-20 pt-3">
                        <button type="submit" class="btn btn-theme btn-lg w-100 d-block">
                            <i class="bi bi-save me-2"></i> {{ __('messages.update_cost_settings') }}
                        </button>
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
</form>

<!-- 2. Proposal Deployment Options Breakdown -->
<ul class="nav nav-tabs mb-3 border-secondary border-opacity-30" id="proposalTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active text-uppercase tracking-wider fw-bold" id="onprem-tab" data-bs-toggle="tab" data-bs-target="#onprem-pane" type="button" role="tab" aria-controls="onprem-pane" aria-selected="true" style="font-size: 11px;">
            <i class="bi bi-server me-1"></i> Option A: On-Premise Details
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link text-uppercase tracking-wider fw-bold" id="cloud-tab" data-bs-toggle="tab" data-bs-target="#cloud-pane" type="button" role="tab" aria-controls="cloud-pane" aria-selected="false" style="font-size: 11px;">
            <i class="bi bi-cloud me-1"></i> Option B: Elastic Cloud Details
        </button>
    </li>
</ul>

<div class="tab-content" id="proposalTabContent">
    <!-- Option A: On-Premise Details Pane -->
    <div class="tab-pane fade show active" id="onprem-pane" role="tabpanel" aria-labelledby="onprem-tab">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title text-theme mb-3">
                    <i class="bi bi-hdd-fill me-2"></i> {{ __('messages.infrastructure_hosting_cost_breakdown') }}
                    @if(!empty($costData['raw_mssp_detail']->cloud_datacenter))
                        <span class="badge bg-info text-dark ms-2"><i class="fa fa-cloud me-1"></i> Partner Cloud Pricing: {{ $costData['raw_mssp_detail']->cloud_datacenter }}</span>
                    @endif
                </h5>

                <div class="table-responsive">
                    <table class="table table-borderless table-hover align-middle mb-0">
                        <thead>
                            <tr class="border-bottom text-muted small uppercase-tracking">
                                <th>{{ __('messages.node_type') }} / {{ __('messages.role') }}</th>
                                <th>{{ __('messages.instance_count') }}</th>
                                <th>{{ __('messages.vm_ram') }}</th>
                                <th>{{ __('messages.vm_disk') }}</th>
                                <th>{{ __('messages.monthly_ram_cost') }}</th>
                                <th>{{ __('messages.monthly_disk_cost') }}</th>
                                <th class="text-end">{{ __('messages.total_hosting_node_type') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($costData['infrastructure']['nodes'] as $node)
                                <tr>
                                    <td>
                                        <strong class="text-white">{{ $node['name'] }}</strong>
                                        <div class="small text-muted mt-0.5" style="font-size: 11px;">{{ $node['role'] }}</div>
                                        @if(!empty($node['cloud_datacenter']))
                                            <div class="mt-1">
                                                <span class="badge bg-theme bg-opacity-20 text-theme border border-theme border-opacity-30" style="font-size: 10px;">
                                                    <i class="fa fa-server me-1"></i> VM: {{ $node['matched_vm_name'] }} ({{ $node['matched_vm_vcpu'] }} vCPU)
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="mono-cell">x{{ $node['count'] }}</td>
                                    <td class="mono-cell">{{ $node['ram_gb'] }} GB</td>
                                    <td class="mono-cell">
                                        {{ $node['storage_gb'] >= 1000 ? ($node['storage_gb']/1000) . ' TB' : $node['storage_gb'] . ' GB' }}
                                        <div class="text-muted small" style="font-size: 10px;">{{ $node['storage_type'] }}</div>
                                        @if(!empty($node['cloud_datacenter']))
                                            <div class="mt-1 text-info small" style="font-size: 10px;" title="{{ $node['matched_disk_desc'] }}">
                                                <i class="fa fa-hdd me-1"></i> {{ $node['matched_disk_desc'] }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="mono-cell text-muted" title="{{ $node['cloud_datacenter'] ? 'Price in TND: ' . number_format($node['matched_vm_price_tnd'], 2) . ' TND' : '' }}">
                                        {{ \App\Services\CurrencyHelper::format($node['ram_monthly_cost']) }}
                                    </td>
                                    <td class="mono-cell text-muted" title="{{ $node['cloud_datacenter'] ? 'Price in TND: ' . number_format($node['matched_disk_price_tnd'], 3) . ' TND' : '' }}">
                                        {{ \App\Services\CurrencyHelper::format($node['storage_monthly_cost']) }}
                                    </td>
                                    <td class="mono-cell text-end text-white fw-bold">
                                        {{ \App\Services\CurrencyHelper::format($node['total_monthly_cost']) }}
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="border-top table-active font-weight-bold">
                                <td>{{ __('messages.infrastructure_sizing_totals') }}</td>
                                <td class="mono-cell">-</td>
                                <td class="mono-cell">{{ $costData['sizing_summary']['total_ram_gb'] }} GB</td>
                                <td class="mono-cell">-</td>
                                <td class="mono-cell">-</td>
                                <td class="mono-cell">-</td>
                                <td class="mono-cell text-end text-success">
                                    {{ \App\Services\CurrencyHelper::format($costData['infrastructure']['total_monthly_infra_cost']) }}
                                </td>
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

    <!-- Option B: Elastic Cloud Details Pane -->
    <div class="tab-pane fade" id="cloud-pane" role="tabpanel" aria-labelledby="cloud-tab">
        <div class="card mb-4 bg-black bg-opacity-20 border-info border-opacity-25" style="background: linear-gradient(135deg, rgba(13, 148, 136, 0.05), rgba(59, 130, 246, 0.05));">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="fs-30px text-info">
                        <i class="bi bi-info-circle-fill"></i>
                    </div>
                    <div>
                        <h6 class="text-white fw-bold mb-1">Elastic Cloud Online Calculator Reference</h6>
                        <p class="text-muted small mb-2">
                            To check configurations and verify hosting needs for this client inventory, use the official online calculator at <a href="https://cloud.elastic.co/pricing" target="_blank" rel="noopener" class="text-info-light text-decoration-none">cloud.elastic.co/pricing</a>. Benchmark subscription rates are configured in the settings sidebar.
                        </p>
                        <a href="https://cloud.elastic.co/pricing" target="_blank" rel="noopener" class="btn btn-outline-info btn-xs py-1 px-2">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Open Official Calculator
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-arrow">
                <div class="card-arrow-top-left"></div><div class="card-arrow-top-right"></div><div class="card-arrow-bottom-left"></div><div class="card-arrow-bottom-right"></div>
            </div>
        </div>

        <div class="row mb-4 g-3">
            <div class="col-md-6 col-sm-12">
                <div class="card h-100 bg-black bg-opacity-20 border-secondary border-opacity-20">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div>
                            <h6 class="text-theme mb-3"><i class="fa fa-cloud me-2"></i> Elastic Cloud Cluster Sizing</h6>
                            <div class="d-flex justify-content-between mb-2 pb-2 border-bottom border-secondary border-opacity-10">
                                <span class="text-muted small">Required Cluster RAM:</span>
                                <strong class="mono-cell text-white">{{ $costData['sizing_summary']['total_ram_gb'] }} GB</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2 pb-2 border-bottom border-secondary border-opacity-10">
                                <span class="text-muted small">Subscription Tier:</span>
                                <strong class="mono-cell text-white">{{ ucfirst($costData['cloud_option']['elastic_cloud_subscription_tier']) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between pt-1 border-bottom border-secondary border-opacity-10 pb-2 mb-2">
                                <span class="text-theme fw-bold">Elastic Cloud Subscription Cost:</span>
                                <strong class="mono-cell text-success fs-16px">{{ \App\Services\CurrencyHelper::format($costData['cloud_option']['elastic_cloud_subscription_cost']) }} /mo</strong>
                            </div>
                        </div>
                        <div class="text-muted mt-2" style="font-size: 11px; line-height: 1.4;">
                            <i class="bi bi-shield-fill-check text-info me-1"></i> <strong>Reference Benchmark:</strong> Node-by-node matched from Azure East US 2 pricing. Billed hosting is <strong>NOT</strong> added separately under Option B (covered in agent rates).
                        </div>
                    </div>
                    <div class="card-arrow">
                        <div class="card-arrow-top-left"></div><div class="card-arrow-top-right"></div><div class="card-arrow-bottom-left"></div><div class="card-arrow-bottom-right"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-sm-12">
                <div class="card h-100 bg-black bg-opacity-20 border-secondary border-opacity-20">
                    <div class="card-body">
                        <h6 class="text-theme mb-3"><i class="fa fa-shield-alt me-2"></i> MDR Agent Package Totals</h6>
                        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom border-secondary border-opacity-10">
                            <span class="text-muted small">Unified SIEM Agents:</span>
                            <strong class="mono-cell text-white">{{ $costData['cloud_option']['total_siem_count'] }} devices</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom border-secondary border-opacity-10">
                            <span class="text-muted small">Expert-Led MDR Agents:</span>
                            <strong class="mono-cell text-white">{{ $costData['cloud_option']['total_mdr_count'] }} devices</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom border-secondary border-opacity-10">
                            <span class="text-muted small">Advanced EDR Agents:</span>
                            <strong class="mono-cell text-white">{{ $costData['cloud_option']['total_edr_count'] }} devices</strong>
                        </div>
                        <div class="d-flex justify-content-between pt-1">
                            <span class="text-theme fw-bold">MDR Agent Package Cost:</span>
                            <strong class="mono-cell text-success fs-16px">{{ \App\Services\CurrencyHelper::format($costData['cloud_option']['total_agents_monthly_cost']) }} /mo</strong>
                        </div>
                    </div>
                    <div class="card-arrow">
                        <div class="card-arrow-top-left"></div><div class="card-arrow-top-right"></div><div class="card-arrow-bottom-left"></div><div class="card-arrow-bottom-right"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title text-theme mb-3"><i class="fa fa-cloud me-2"></i> Elastic Cloud Node Sizing & Pricing (Azure East US 2)</h5>
                <div class="table-responsive">
                    <table class="table table-borderless table-hover align-middle mb-0">
                        <thead>
                            <tr class="border-bottom text-muted small uppercase-tracking">
                                <th>Node Sizing Item</th>
                                <th>Role</th>
                                <th>Count</th>
                                <th>RAM / Node</th>
                                <th>Matched Instance SKU</th>
                                <th>Hourly Rate ({{ ucfirst($costData['cloud_option']['elastic_cloud_subscription_tier']) }})</th>
                                <th class="text-end">Total Monthly Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($costData['cloud_option']['matched_nodes'] as $node)
                                <tr>
                                    <td><strong class="text-white">{{ $node['name'] }}</strong></td>
                                    <td class="small text-muted">{{ $node['role'] }}</td>
                                    <td class="mono-cell">x{{ $node['count'] }}</td>
                                    <td class="mono-cell">{{ $node['ram_gb'] }} GB</td>
                                    <td class="mono-cell"><code class="text-info-light" style="font-size: 11px;">{{ $node['sku'] }}</code></td>
                                    <td class="mono-cell">${{ number_format($node['hourly_rate'], 4) }} /GB-hr</td>
                                    <td class="mono-cell text-end text-white fw-bold">{{ \App\Services\CurrencyHelper::format($node['monthly_cost']) }}</td>
                                </tr>
                            @endforeach
                            <tr class="border-top table-active font-weight-bold">
                                <td colspan="6">Total Monthly Elastic Cloud Subscription Cost (Reference Only)</td>
                                <td class="mono-cell text-end text-success">{{ \App\Services\CurrencyHelper::format($costData['cloud_option']['elastic_cloud_subscription_cost']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-arrow">
                <div class="card-arrow-top-left"></div><div class="card-arrow-top-right"></div><div class="card-arrow-bottom-left"></div><div class="card-arrow-bottom-right"></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title text-theme mb-3"><i class="bi bi-shield-fill-check me-2"></i> MDR Agent Package Cost Breakdown</h5>
                <div class="table-responsive">
                    <table class="table table-borderless table-hover align-middle mb-0">
                        <thead>
                            <tr class="border-bottom text-muted small uppercase-tracking">
                                <th>Agent Type</th>
                                <th>Description</th>
                                <th>Mapped Devices</th>
                                <th>Monthly Unit Cost</th>
                                <th class="text-end">Total Monthly Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong class="text-white">SIEM Agent</strong></td>
                                <td class="small text-muted">Unified Security Monitoring & Correlation</td>
                                <td class="mono-cell">{{ $costData['cloud_option']['total_siem_count'] }}</td>
                                <td class="mono-cell">{{ \App\Services\CurrencyHelper::format($costData['cloud_option']['siem_agent_monthly_cost_per_device']) }}</td>
                                <td class="mono-cell text-end text-white fw-bold">{{ \App\Services\CurrencyHelper::format($costData['cloud_option']['siem_monthly_cost']) }}</td>
                            </tr>
                            <tr>
                                <td><strong class="text-white">MDR Agent</strong></td>
                                <td class="small text-muted">Expert-Led 24/7 Monitoring & Response</td>
                                <td class="mono-cell">{{ $costData['cloud_option']['total_mdr_count'] }}</td>
                                <td class="mono-cell">{{ \App\Services\CurrencyHelper::format($costData['cloud_option']['mdr_agent_monthly_cost_per_device']) }}</td>
                                <td class="mono-cell text-end text-white fw-bold">{{ \App\Services\CurrencyHelper::format($costData['cloud_option']['mdr_monthly_cost']) }}</td>
                            </tr>
                            <tr>
                                <td><strong class="text-white">EDR Agent</strong></td>
                                <td class="small text-muted">Advanced Endpoint Protection</td>
                                <td class="mono-cell">{{ $costData['cloud_option']['total_edr_count'] }}</td>
                                <td class="mono-cell">{{ \App\Services\CurrencyHelper::format($costData['cloud_option']['edr_agent_monthly_cost_per_device']) }}</td>
                                <td class="mono-cell text-end text-white fw-bold">{{ \App\Services\CurrencyHelper::format($costData['cloud_option']['edr_monthly_cost']) }}</td>
                            </tr>
                            <tr class="border-top table-active font-weight-bold">
                                <td colspan="4">Total Monthly Agent Package Cost</td>
                                <td class="mono-cell text-end text-success">{{ \App\Services\CurrencyHelper::format($costData['cloud_option']['total_agents_monthly_cost']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-arrow">
                <div class="card-arrow-top-left"></div><div class="card-arrow-top-right"></div><div class="card-arrow-bottom-left"></div><div class="card-arrow-bottom-right"></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title text-theme mb-3"><i class="bi bi-hdd-stack-fill me-2"></i> Client Asset Inventory Agent Mappings</h5>
                <div class="table-responsive">
                    <table class="table table-borderless table-hover align-middle mb-0">
                        <thead>
                            <tr class="border-bottom text-muted small uppercase-tracking">
                                <th>Log Source</th>
                                <th>Device Count</th>
                                <th class="text-center">SIEM Agent</th>
                                <th class="text-center">MDR Agent</th>
                                <th class="text-center">EDR Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($costData['cloud_option']['agents'] as $agent)
                                <tr>
                                    <td><strong class="text-white">{{ $agent['name'] }}</strong></td>
                                    <td class="mono-cell">{{ $agent['device_count'] }}</td>
                                    <td class="text-center">
                                        @if($agent['runs_siem'])
                                            <span class="badge bg-primary bg-opacity-25 text-primary-light border border-primary border-opacity-40">Active ({{ $agent['siem_count'] }})</span>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($agent['runs_mdr'])
                                            <span class="badge bg-success bg-opacity-25 text-success-light border border-success border-opacity-40">Active ({{ $agent['mdr_count'] }})</span>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($agent['runs_edr'])
                                            <span class="badge bg-info bg-opacity-25 text-info-light border border-info border-opacity-40">Active ({{ $agent['edr_count'] }})</span>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-arrow">
                <div class="card-arrow-top-left"></div><div class="card-arrow-top-right"></div><div class="card-arrow-bottom-left"></div><div class="card-arrow-bottom-right"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="/assets/plugins/apexcharts/dist/apexcharts.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Cloud Datacenter toggle generic rate card opacity
        var cloudDcSelect = document.getElementById("cloud_datacenter_select");
        var genericRateCard = document.getElementById("generic_rate_card_container");
        var genericBadge = document.getElementById("generic_rates_ignored_badge");
        if (cloudDcSelect && genericRateCard && genericBadge) {
            cloudDcSelect.addEventListener("change", function() {
                if (cloudDcSelect.value) {
                    genericRateCard.style.opacity = "0.6";
                    genericBadge.style.display = "";
                } else {
                    genericRateCard.style.opacity = "";
                    genericBadge.style.display = "none";
                }
            });
        }

        // License Sharing Toggle
        var isLicenseShared = document.getElementById("is_license_shared");
        var licenseContainer = document.getElementById("license_share_pct_container");
        if (isLicenseShared && licenseContainer) {
            isLicenseShared.addEventListener("change", function() {
                if (isLicenseShared.checked) {
                    licenseContainer.style.display = "";
                } else {
                    licenseContainer.style.display = "none";
                }
            });
        }

        var options = {
            chart: {
                type: 'donut',
                height: 220,
                fontFamily: 'inherit',
                toolbar: { show: false }
            },
            stroke: {
                show: true,
                width: 2,
                colors: ['rgba(0,0,0,0.5)']
            },
            colors: ['#3cd2a5', '#3b82f6', '#f97316', '#a855f7', '#ec4899'],
            series: {!! json_encode($costSeries) !!},
            labels: {!! json_encode($costLabels) !!},
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
                        var activeCurrency = "{{ \App\Services\CurrencyHelper::active() }}";
                        if (activeCurrency === 'TND') {
                            return val.toLocaleString(undefined, {minimumFractionDigits: 3, maximumFractionDigits: 3}) + " TND";
                        } else if (activeCurrency === 'EUR') {
                            return "€" + val.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        } else {
                            return "$" + val.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }
                }
            }
        };

        var chart = new ApexCharts(document.querySelector("#costDonutChart"), options);
        chart.render();

        // Ask AI Ajax Handler
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
                        <div class="h5 text-theme text-center mt-2 mb-1" id="ai-loading-step">Initializing SOC Offer Analyst Agent...</div>
                        <div class="text-muted small text-center" id="ai-loading-desc">Setting up local Ollama connection to gemma4:e2b...</div>
                    </div>
                `;
                btnAskAi.disabled = true;

                var steps = [
                    { title: "Initializing SOC Offer Analyst Agent...", desc: "Setting up local Ollama connection to gemma4:e2b..." },
                    { title: "Reviewing Staffing & Dedication Allocations...", desc: "Auditing tier 1, 2, 3 analyst counts and salary rates..." },
                    { title: "Evaluating VM Hosting Rate Cards...", desc: "Auditing SSD, NVMe, and SATA storage cost rules..." },
                    { title: "Checking ERU License cost allocations...", desc: "Auditing client percentage shares and Elastic license costs..." },
                    { title: "Auditing Upfront and MRC Pricing Margins...", desc: "Comparing base estimate MRC vs offered client MRC ratios..." },
                    { title: "Formulating Proposal Suggestions...", desc: "Structuring final recommendations and markup analysis..." }
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

                fetch("{{ route('mssp.ask-ai', [$client->id, $scenario->id]) }}", {
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
                                <i class="fa fa-exclamation-triangle me-2"></i> ${data.message || "{{ __('messages.ai_error') }}"}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    clearInterval(stepInterval);
                    btnAskAi.disabled = false;
                    aiContent.innerHTML = `
                        <div class="alert alert-danger mb-0">
                            <i class="fa fa-exclamation-triangle me-2"></i> {{ __('messages.ai_error') }} (Error: ${error.message})
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
                    btnTestOllama.innerHTML = '<i class="fa fa-satellite-dish me-1"></i> Test AI Connection';

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
                    btnTestOllama.innerHTML = '<i class="fa fa-satellite-dish me-1"></i> Test AI Connection';
                    pingContainer.insertAdjacentHTML('beforeend',
                        `<div class="toast show border-danger mb-2" style="min-width:300px;background:#1a2433;color:#e0e8f0;">
                            <div class="toast-body">⚠️ Could not reach Laravel backend: ${err.message}</div>
                        </div>`);
                });
            });
        }
    });
</script>
@endsection
