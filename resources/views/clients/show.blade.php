@extends('layouts.app')

@section('title', "Client: {$client->name}")

@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">{{ __('messages.clients') }}</a></li>
    <li class="breadcrumb-item active">{{ strtoupper($client->name) }}</li>
</ul>

<div class="d-flex align-items-center mb-4">
    <div class="flex-grow-1">
        <h1 class="page-header mb-0">
            {{ $client->name }} <small class="d-block mt-1">{{ $client->description ?? __('No description provided.') }}</small>
        </h1>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('clients.diagrams.index', $client->id) }}" class="btn btn-outline-theme">
            <i class="bi bi-diagram-3 me-1"></i> Architecture Diagrams
        </a>
        <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> {{ __('messages.back_to_clients') }}
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <strong>Success!</strong> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<!-- Section 1: Inventory & Assets Calibration -->
<div class="card mb-5">
    <div class="card-body">
        <h5 class="card-title mb-1 text-theme">
            <i class="bi bi-hdd-stack-fill me-2"></i> {{ __('messages.client_asset_inventory_calibration') }}
        </h5>
        <p class="text-muted small mb-4">
            {{ __('messages.client_asset_inventory_subtitle') }}
        </p>

        <form action="{{ route('client-assets.update-bulk', $client->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="table-responsive">
                <table class="table table-borderless table-hover mb-0 align-middle">
                    <thead>
                        <tr class="border-bottom">
                            <th class="text-muted" style="width: 25%;">{{ __('messages.log_source') }}</th>
                            <th class="text-muted" style="width: 15%;">{{ __('messages.device_count') }}</th>
                            <th class="text-muted" style="width: 20%;">{{ __('messages.default_calibration') }}</th>
                            <th class="text-muted" style="width: 25%;">{{ __('messages.custom_overrides') }}</th>
                            <th class="text-muted text-end" style="width: 15%;">{{ __('messages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($inventory as $item)
                            <tr>
                                <td>
                                    <strong class="text-theme fs-15px">{{ $item->assetType->name }}</strong>
                                    <div class="small text-muted mt-1">
                                        {{ $item->assetType->description }}
                                    </div>
                                    <div class="mt-2 d-flex gap-3 align-items-center">
                                        <div class="form-check form-check-inline mb-0">
                                            <input class="form-check-input border-secondary" type="checkbox" name="assets[{{ $item->id }}][runs_siem_agent]" id="siem_{{ $item->id }}" value="1" {{ $item->runs_siem_agent ? 'checked' : '' }}>
                                            <label class="form-check-label text-primary-light small fw-bold cursor-pointer" for="siem_{{ $item->id }}">SIEM</label>
                                        </div>
                                        <div class="form-check form-check-inline mb-0">
                                            <input class="form-check-input border-secondary" type="checkbox" name="assets[{{ $item->id }}][runs_mdr_agent]" id="mdr_{{ $item->id }}" value="1" {{ $item->runs_mdr_agent ? 'checked' : '' }}>
                                            <label class="form-check-label text-success-light small fw-bold cursor-pointer" for="mdr_{{ $item->id }}">MDR</label>
                                        </div>
                                        <div class="form-check form-check-inline mb-0">
                                            <input class="form-check-input border-secondary" type="checkbox" name="assets[{{ $item->id }}][runs_edr_agent]" id="edr_{{ $item->id }}" value="1" {{ $item->runs_edr_agent ? 'checked' : '' }}>
                                            <label class="form-check-label text-info-light small fw-bold cursor-pointer" for="edr_{{ $item->id }}">EDR</label>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm" style="max-width: 110px;">
                                        <input type="number" name="assets[{{ $item->id }}][device_count]" class="form-control mono-cell" value="{{ $item->device_count }}" min="0" required>
                                    </div>
                                </td>
                                <td class="small text-muted">
                                    <div class="mb-1">{{ __('messages.avg_event_label') }} <span class="mono-cell text-white">{{ $item->assetType->avg_event_size_bytes }} B</span></div>
                                    <div class="mb-1">{{ __('messages.mode_label') }} <span class="badge bg-secondary bg-opacity-20 text-white border border-secondary border-opacity-30">{{ str_replace('_', ' ', $item->assetType->calibration_mode) }}</span></div>
                                    @if($item->assetType->calibration_mode === 'eps_per_device')
                                        <div>{{ __('messages.peak_eps_label') }} <span class="mono-cell text-white">{{ $item->assetType->max_eps_default }}</span></div>
                                    @else
                                        <div>{{ __('messages.max_limit_label') }} <span class="mono-cell text-white">{{ $item->assetType->max_monthly_gb_default }} GB</span></div>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-2" style="max-width: 280px;">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-transparent text-muted border-secondary border-opacity-30" style="width: 90px; font-size: 11px;">{{ __('messages.event_size_label') }}</span>
                                            <input type="number" name="assets[{{ $item->id }}][custom_avg_event_size_bytes]" class="form-control mono-cell" value="{{ $item->custom_avg_event_size_bytes }}" placeholder="{{ $item->assetType->avg_event_size_bytes }} B">
                                        </div>
                                        @if($item->assetType->calibration_mode === 'eps_per_device')
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-transparent text-muted border-secondary border-opacity-30" style="width: 90px; font-size: 11px;">{{ __('messages.min_avg_eps_label') }}</span>
                                                <input type="text" name="assets[{{ $item->id }}][custom_min_eps]" class="form-control mono-cell" value="{{ $item->custom_min_eps }}" placeholder="Min: {{ $item->assetType->min_eps_default }}">
                                                <input type="text" name="assets[{{ $item->id }}][custom_avg_eps]" class="form-control mono-cell" value="{{ $item->custom_avg_eps }}" placeholder="Avg: {{ $item->assetType->avg_eps_default }}">
                                            </div>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-transparent text-muted border-secondary border-opacity-30" style="width: 90px; font-size: 11px;">{{ __('messages.max_eps_label_input') }}</span>
                                                <input type="text" name="assets[{{ $item->id }}][custom_max_eps]" class="form-control mono-cell" value="{{ $item->custom_max_eps }}" placeholder="Max: {{ $item->assetType->max_eps_default }}">
                                            </div>
                                        @else
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-transparent text-muted border-secondary border-opacity-30" style="width: 90px; font-size: 11px;">{{ __('messages.min_avg_eps_label') }}</span>
                                                <input type="text" name="assets[{{ $item->id }}][custom_min_eps]" class="form-control mono-cell" value="{{ $item->custom_min_eps }}" placeholder="Min: {{ $item->assetType->min_eps_default }}">
                                                <input type="text" name="assets[{{ $item->id }}][custom_avg_eps]" class="form-control mono-cell" value="{{ $item->custom_avg_eps }}" placeholder="Avg: {{ $item->assetType->avg_eps_default }}">
                                            </div>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-transparent text-muted border-secondary border-opacity-30" style="width: 90px; font-size: 11px;">{{ __('messages.max_volume_label') }}</span>
                                                <input type="text" name="assets[{{ $item->id }}][custom_max_monthly_gb]" class="form-control mono-cell" value="{{ $item->custom_max_monthly_gb }}" placeholder="Max: {{ $item->assetType->max_monthly_gb_default }} GB">
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="event.preventDefault(); if (confirm('Remove this asset type from client inventory?')) { document.getElementById('delete-form-{{ $item->id }}').submit(); }">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-outline-theme">
                    <i class="bi bi-check-circle me-1"></i> {{ __('messages.save_changes') }}
                </button>
            </div>
        </form>

        @foreach($inventory as $item)
            <form id="delete-form-{{ $item->id }}" action="{{ route('client-assets.destroy', [$client->id, $item->id]) }}" method="POST" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        @endforeach

        @if($availableAssetTypes->isNotEmpty())
            <div class="mt-4 pt-4 border-top border-secondary border-opacity-30">
                <h6 class="mb-3 text-theme">{{ __('messages.add_log_source_to_inventory') }}</h6>
                <form action="{{ route('client-assets.store', $client->id) }}" method="POST">
                    @csrf
                    <div class="row align-items-end g-3">
                        <div class="col-md-6 col-sm-12">
                            <label class="form-label text-muted small">{{ __('messages.asset_type') }}</label>
                            <select name="asset_type_id" class="form-select">
                                @foreach($availableAssetTypes as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }} (Mode: {{ str_replace('_', ' ', $t->calibration_mode) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label text-muted small">{{ __('messages.device_count') }}</label>
                            <input type="number" name="device_count" class="form-control" value="1" min="1" required>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <button type="submit" class="btn btn-outline-theme d-block w-100">
                                <i class="bi bi-plus-circle me-1"></i> {{ __('messages.add_log_source') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>
    <!-- card-arrow -->
    <div class="card-arrow">
        <div class="card-arrow-top-left"></div>
        <div class="card-arrow-top-right"></div>
        <div class="card-arrow-bottom-left"></div>
        <div class="card-arrow-bottom-right"></div>
    </div>
</div>

<!-- Section 2: Scenario Comparison Dashboard -->
<h4 class="mb-4 text-theme"><i class="bi bi-calculator-fill me-2"></i> {{ __('messages.elasticsearch_sizing_scenarios') }}</h4>

<div class="row">
    @foreach($scenarioComparisons as $comp)
        @php
            $sc = $comp['scenario'];
            $totals = $comp['totals'];
            $lic = $comp['licensing'];
        @endphp
        <div class="col-xl-4 col-lg-6 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 text-white fw-bold">{{ $sc->name }}</h5>
                            <span class="badge {{ $sc->workload_profile === 'min' ? 'badge-cold' : ($sc->workload_profile === 'avg' ? 'badge-warm' : 'badge-hot') }}">
                                {{ $sc->workload_profile }}
                            </span>
                        </div>
                        
                        <p class="text-muted small mb-4" style="height: 38px; overflow: hidden;">
                            {{ $sc->description }}
                        </p>
                        
                        <div class="border-top border-bottom border-secondary border-opacity-20 py-3 mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">{{ __('messages.daily_raw_volume') }}</span>
                                <strong class="mono-cell text-white">{{ $totals['daily_raw_gb'] }} GB/day</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">{{ __('messages.retention_period') }}</span>
                                <strong class="mono-cell text-white">{{ $sc->retention_days }} Days</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">{{ __('messages.total_cluster_ram') }}</span>
                                <strong class="mono-cell text-white">{{ $lic['total_ram_gb'] }} GB</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted small">{{ __('messages.required_licenses') }}</span>
                                <strong class="mono-cell text-theme">{{ $lic['required_erus'] }} ERUs</strong>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted small">{{ __('messages.subscription_cost') }}</span>
                            <div class="text-end">
                                <span class="fs-20px fw-bold text-success">{{ \App\Services\CurrencyHelper::format($lic['annual_cost_usd']) }}</span>
                                <span class="text-muted small">{{ __('messages.per_year') }}</span>
                            </div>
                        </div>
                        
                        <div class="row g-1">
                            <div class="col-3">
                                <a href="{{ route('sizing.show', [$client->id, $sc->id]) }}" class="btn btn-outline-theme btn-sm d-block w-100" title="View Sizing Specs">
                                    {{ __('messages.specs_btn') }}
                                </a>
                            </div>
                            <div class="col-3">
                                <a href="{{ route('sizing.export.excel', [$client->id, $sc->id]) }}" class="btn btn-outline-secondary btn-sm d-block w-100" title="Export Excel Model">
                                    XLSX
                                </a>
                            </div>
                            <div class="col-3">
                                <a href="{{ route('sizing.export.word', [$client->id, $sc->id]) }}" class="btn btn-outline-secondary btn-sm d-block w-100" title="Download Word Report">
                                    DOCX
                                </a>
                            </div>
                            <div class="col-3">
                                <a href="{{ route('sizing.export.markdown', [$client->id, $sc->id]) }}" class="btn btn-outline-secondary btn-sm d-block w-100" title="Download Markdown Report">
                                    MD
                                </a>
                            </div>
                        </div>
                        <div class="mt-2">
                            <a href="{{ route('mssp.show', [$client->id, $sc->id]) }}" class="btn btn-outline-success btn-sm d-block w-100">
                                <i class="bi bi-wallet2 me-1"></i> {{ __('messages.mssp_soc_proposal_btn') }}
                            </a>
                        </div>
                    </div>
                </div>
                <!-- card-arrow -->
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
@endsection
