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
        <button id="btn-test-ollama" type="button" class="btn btn-outline-warning" title="Test Ollama Server Connectivity">
            <i class="fa fa-satellite-dish me-1"></i> Test Ollama
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
                        <div class="border-top border-secondary border-opacity-20 pt-3">
                            <label class="form-label text-theme small uppercase-tracking">{{ __('messages.vm_hosting_rate_card') }}</label>
                            
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

<!-- 2. Infrastructure Hosting Cost Details Table -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title text-theme mb-3">
            <i class="bi bi-hdd-fill me-2"></i> {{ __('messages.infrastructure_hosting_cost_breakdown') }}
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
                            </td>
                            <td class="mono-cell">x{{ $node['count'] }}</td>
                            <td class="mono-cell">{{ $node['ram_gb'] }} GB</td>
                            <td class="mono-cell">
                                {{ $node['storage_gb'] >= 1000 ? ($node['storage_gb']/1000) . ' TB' : $node['storage_gb'] . ' GB' }}
                                <div class="text-muted small" style="font-size: 10px;">{{ $node['storage_type'] }}</div>
                            </td>
                            <td class="mono-cell text-muted">{{ \App\Services\CurrencyHelper::format($node['ram_monthly_cost']) }}</td>
                            <td class="mono-cell text-muted">{{ \App\Services\CurrencyHelper::format($node['storage_monthly_cost']) }}</td>
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
@endsection

@section('scripts')
<script src="/assets/plugins/apexcharts/dist/apexcharts.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
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
                    <div class="d-flex align-items-center gap-3 py-3">
                        <div class="spinner-border text-info" role="status" style="width: 2rem; height: 2rem;"></div>
                        <span class="text-info fw-bold animate-pulse">{{ __('messages.ai_thinking') }}</span>
                    </div>
                `;
                btnAskAi.disabled = true;

                fetch("{{ route('mssp.ask-ai', [$client->id, $scenario->id]) }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    }
                })
                .then(response => response.json())
                .then(data => {
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
                    btnAskAi.disabled = false;
                    aiContent.innerHTML = `
                        <div class="alert alert-danger mb-0">
                            <i class="fa fa-exclamation-triangle me-2"></i> {{ __('messages.ai_error') }} (Error: ${error.message})
                        </div>
                    `;
                });
            });
        }

        // Test Ollama Connection
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
                    btnTestOllama.innerHTML = '<i class="fa fa-satellite-dish me-1"></i> Test Ollama';

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
                                <strong class="me-auto">${icon} Ollama Status</strong>
                                <small class="text-muted">${data.timestamp ? data.timestamp.substring(11, 19) : 'now'}</small>
                                <button type="button" class="btn-close btn-close-white ms-2" onclick="document.getElementById('${toastId}').remove()"></button>
                            </div>
                            <div class="toast-body small">
                                <div><strong>URL:</strong> <code>${data.ollama_url}</code></div>
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
                    btnTestOllama.innerHTML = '<i class="fa fa-satellite-dish me-1"></i> Test Ollama';
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
