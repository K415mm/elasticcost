@extends('layouts.app')

@section('title', 'Profit & Revenue Simulator: ' . $client->name . ' - ' . $scenario->name)

@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">{{ __('messages.clients') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('clients.show', $client->id) }}">{{ strtoupper($client->name) }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('mssp.show', [$client->id, $scenario->id]) }}">{{ __('messages.soc_cost_proposal') }}</a></li>
    <li class="breadcrumb-item active">Profit Simulator</li>
</ul>

<!-- Page Header with Client & Scenario Switcher -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
    <div>
        <h1 class="page-header mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-graph-up-arrow text-success"></i> Universal Profit & Revenue Simulator
            <span class="badge bg-theme bg-opacity-20 text-theme border border-theme border-opacity-40 px-2 py-1 fs-11px"><i class="bi bi-server me-1"></i> Bound to On-Premise Deployment Offer</span>
        </h1>
        <div class="text-muted small mt-1">
            Simulate monthly subscription profits over 36 months based on Agent Unit Pricing or Custom Service Packs aligned with platform capacity limits and On-Premise Deployment Offer rates.
        </div>
    </div>
    
    <!-- Client and Scenario Quick Switcher -->
    <div class="d-flex align-items-center gap-2 bg-dark bg-opacity-50 p-2 rounded border border-secondary border-opacity-30">
        <form action="{{ route('simulator.index') }}" method="GET" class="d-flex align-items-center gap-2 m-0">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-secondary bg-opacity-20 text-muted"><i class="bi bi-person me-1"></i> Client</span>
                <select name="client_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" {{ $c->id === $client->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-secondary bg-opacity-20 text-muted"><i class="bi bi-diagram-3 me-1"></i> Scenario</span>
                <select name="scenario_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($scenarios as $s)
                        <option value="{{ $s->id }}" {{ $s->id === $scenario->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        <a href="{{ route('mssp.show', [$client->id, $scenario->id]) }}" class="btn btn-outline-theme btn-sm ms-2">
            <i class="bi bi-file-earmark-text me-1"></i> Back to Proposal
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <strong>{{ __('messages.success') }}!</strong> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<!-- Main Form -->
<form action="{{ route('simulator.update', [$client->id, $scenario->id]) }}" method="POST" id="simulator-main-form">
    @csrf

    <!-- Header Banner & Live Inventory Calibration -->
    <div class="card mb-4 border-info border-opacity-30 bg-black bg-opacity-30 shadow-lg">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h6 class="text-info fw-bold mb-1 d-flex align-items-center gap-2">
                        <i class="bi bi-cpu fs-16px"></i> System Capacity & Agent Inventory Calibration
                        <span class="badge bg-info bg-opacity-15 text-info border border-info border-opacity-30 px-2 py-0 fs-10px">On-Premise Deployment Offer</span>
                    </h6>
                    <p class="text-muted small mb-0">
                        Agent Rate Cards are bound to the <strong>On-Premise Deployment Offer</strong> (Option A). System capacity calibrated from <strong>Client Asset Inventory</strong>: 
                        <span class="text-info fw-bold">{{ $simInitial['edr'] }} EDR</span>, 
                        <span class="text-success fw-bold">{{ $simInitial['mdr'] }} MDR</span>, 
                        <span class="text-primary fw-bold">{{ $simInitial['siem'] }} SIEM</span> = 
                        <strong>{{ $simInitial['total'] }} Platform Capacity Cap</strong>.
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="submit" form="simulator-main-form" class="btn btn-success btn-sm fw-bold shadow-sm d-inline-flex align-items-center gap-1">
                        <i class="bi bi-play-circle-fill"></i> Update & Recalculate Simulation
                    </button>
                    <button type="submit" form="simulator-main-form" name="reset_defaults" value="1" class="btn btn-outline-info btn-sm">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Defaults
                    </button>
                    <button type="button" id="btn-run-ai-market" class="btn btn-outline-warning btn-sm fw-bold">
                        <i class="bi bi-robot me-1"></i> Run AI Market Buying Advisor
                    </button>
                </div>
            </div>
        </div>
        <div class="card-arrow">
            <div class="card-arrow-top-left"></div><div class="card-arrow-top-right"></div><div class="card-arrow-bottom-left"></div><div class="card-arrow-bottom-right"></div>
        </div>
    </div>

    <!-- AI Market Buying Report Container (Hidden by default) -->
    <div id="ai-market-report-card" class="card mb-4 border-warning border-opacity-40 bg-dark bg-opacity-60" style="display: none;">
        <div class="card-header bg-warning bg-opacity-15 border-bottom border-warning border-opacity-30 py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title text-warning mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-robot fs-18px"></i> AI Market Buying & Profit Optimization Analysis (On-Premise Offer Aligned)
            </h5>
            <button type="button" class="btn-close btn-close-white" onclick="document.getElementById('ai-market-report-card').style.display='none'"></button>
        </div>
        <div class="card-body py-4">
            <div id="ai-market-loading" class="text-center py-4" style="display: none;">
                <div class="spinner-border text-warning mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
                <h6 class="text-white fw-bold">AI Market Agent is simulating enterprise buyer decisions...</h6>
                <p class="text-muted small">Evaluating reseller partner vs direct retail buying behavior for On-Premise Deployment Offer...</p>
            </div>
            <div id="ai-market-content"></div>
        </div>
        <div class="card-arrow">
            <div class="card-arrow-top-left"></div><div class="card-arrow-top-right"></div><div class="card-arrow-bottom-left"></div><div class="card-arrow-bottom-right"></div>
        </div>
    </div>

    <!-- Simulation Controls Panel -->
    <div class="card mb-4 border-secondary border-opacity-30 bg-dark bg-opacity-40">
        <div class="card-body py-3">
            <div class="row g-3 align-items-center">
                <div class="col-md-5">
                    <label class="form-label text-theme fw-bold small mb-1">Simulation Engine Mode</label>
                    <select class="form-select form-select-sm" name="agent_profit_simulation[mode]" id="sim_mode_select" onchange="toggleSimMode(this.value)">
                        <option value="agent" {{ $simSettings['mode'] === 'agent' ? 'selected' : '' }}>
                            📊 Mode 1: Per-Agent Unit Pricing (EDR / MDR / SIEM Individual Pricing)
                        </option>
                        <option value="pack" {{ $simSettings['mode'] === 'pack' ? 'selected' : '' }}>
                            📦 Mode 2: Custom Pack Builder & Extra Services (Bundled Packs + CTI / SLA)
                        </option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label text-theme fw-bold small mb-1">Deduct Infrastructure Hosting Cost (On-Premise Aligned)</label>
                    <select class="form-select form-select-sm" name="agent_profit_simulation[hosting_mode]" id="sim_hosting_mode_select">
                        <option value="none" {{ ($simSettings['hosting_mode'] ?? 'none') === 'none' ? 'selected' : '' }}>
                            🟢 None (Standalone Agent / Pack Gross Profit Margin)
                        </option>
                        <option value="onprem" {{ ($simSettings['hosting_mode'] ?? '') === 'onprem' ? 'selected' : '' }}>
                            🏢 Option A: On-Premise Deployment Offer (Deduct {{ \App\Services\CurrencyHelper::format($costData['total_monthly_service_cost']) }}/mo Hosting)
                        </option>
                        <option value="cloud" {{ ($simSettings['hosting_mode'] ?? '') === 'cloud' ? 'selected' : '' }}>
                            ☁️ Option B: Elastic Cloud (Deduct {{ \App\Services\CurrencyHelper::format($costData['cloud_option']['elastic_cloud_subscription_cost']) }}/mo Cloud Subscription)
                        </option>
                    </select>
                </div>
                <div class="col-md-2 text-end pt-3">
                    <button type="submit" class="btn btn-success btn-sm w-100 fw-bold">
                        <i class="bi bi-arrow-repeat me-1"></i> Recalculate
                    </button>
                </div>
            </div>
        </div>
        <div class="card-arrow">
            <div class="card-arrow-top-left"></div><div class="card-arrow-top-right"></div><div class="card-arrow-bottom-left"></div><div class="card-arrow-bottom-right"></div>
        </div>
    </div>

    <!-- MODE 1: Per-Agent Simulation Inputs -->
    <div id="sim_mode_agent_container" class="{{ $simSettings['mode'] === 'pack' ? 'd-none' : '' }}">
        <div class="card mb-4 border-success border-opacity-30 bg-black bg-opacity-25 shadow-lg">
            <div class="card-header bg-success bg-opacity-15 border-bottom border-success border-opacity-20 py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title text-success mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-graph-up-arrow fs-18px"></i> Per-Agent Selling Price & Profit Margin Simulator
                    </h5>
                    <div class="text-muted small mt-1">
                        Pricing formulas are based on <strong>Base Cost + Margin</strong>. Max limit defaults to system capacity cap.
                    </div>
                </div>
                <button type="submit" class="btn btn-success btn-sm fw-bold">
                    <i class="bi bi-play-fill me-1"></i> Apply & Run Simulation
                </button>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <!-- EDR Agent Package -->
                    @php
                        $edrPartnerMargin = round((($simSettings['edr_partner_price'] - $simSettings['edr_base_cost']) / max($simSettings['edr_base_cost'], 0.01)) * 100, 1);
                        $edrRetailMargin = round((($simSettings['edr_client_price'] - $simSettings['edr_base_cost']) / max($simSettings['edr_base_cost'], 0.01)) * 100, 1);
                    @endphp
                    <div class="col-md-4">
                        <div class="card bg-dark bg-opacity-40 border-secondary border-opacity-20 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold text-info"><i class="bi bi-shield-fill-check me-1"></i> EDR Agent Package</span>
                                    <span class="badge bg-info bg-opacity-25 text-info-light">Current: {{ $simInitial['edr'] }}</span>
                                </div>
                                <div class="small text-muted mb-3">Base Cost Price: <strong class="text-white">{{ \App\Services\CurrencyHelper::format($simSettings['edr_base_cost']) }}/mo</strong></div>

                                <div class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label class="form-label small text-muted mb-1">Partner Wholesale Price ({{ \App\Services\CurrencyHelper::symbol() }}/mo)</label>
                                        <span class="badge bg-success bg-opacity-20 text-success-light" style="font-size: 9px;">+{{ $edrPartnerMargin }}% margin</span>
                                    </div>
                                    <input type="number" step="0.01" class="form-control form-control-sm sim-input" name="agent_profit_simulation[edr_partner_price]" value="{{ round(\App\Services\CurrencyHelper::convert($simSettings['edr_partner_price']), 2) }}">
                                </div>
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label class="form-label small text-muted mb-1">Final Client Retail Price ({{ \App\Services\CurrencyHelper::symbol() }}/mo)</label>
                                        <span class="badge bg-info bg-opacity-20 text-info-light" style="font-size: 9px;">+{{ $edrRetailMargin }}% margin</span>
                                    </div>
                                    <input type="number" step="0.01" class="form-control form-control-sm sim-input" name="agent_profit_simulation[edr_client_price]" value="{{ round(\App\Services\CurrencyHelper::convert($simSettings['edr_client_price']), 2) }}">
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small text-muted mb-1">Max Purchased Limit</label>
                                        <input type="number" class="form-control form-control-sm sim-input" name="agent_profit_simulation[edr_purchased_limit]" value="{{ $simSettings['edr_purchased_limit'] }}">
                                        <span class="text-muted d-block mt-1" style="font-size: 9px;">Stop selling cap</span>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small text-muted mb-1">Monthly Growth (+/mo)</label>
                                        <input type="number" class="form-control form-control-sm sim-input" name="agent_profit_simulation[edr_monthly_growth]" value="{{ $simSettings['edr_monthly_growth'] }}">
                                        <span class="text-muted d-block mt-1" style="font-size: 9px;">Sales velocity</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MDR Agent Package -->
                    @php
                        $mdrPartnerMargin = round((($simSettings['mdr_partner_price'] - $simSettings['mdr_base_cost']) / max($simSettings['mdr_base_cost'], 0.01)) * 100, 1);
                        $mdrRetailMargin = round((($simSettings['mdr_client_price'] - $simSettings['mdr_base_cost']) / max($simSettings['mdr_base_cost'], 0.01)) * 100, 1);
                    @endphp
                    <div class="col-md-4">
                        <div class="card bg-dark bg-opacity-40 border-secondary border-opacity-20 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold text-success"><i class="bi bi-shield-lock-fill me-1"></i> MDR Agent Package</span>
                                    <span class="badge bg-success bg-opacity-25 text-success-light">Current: {{ $simInitial['mdr'] }}</span>
                                </div>
                                <div class="small text-muted mb-3">Base Cost Price: <strong class="text-white">{{ \App\Services\CurrencyHelper::format($simSettings['mdr_base_cost']) }}/mo</strong></div>

                                <div class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label class="form-label small text-muted mb-1">Partner Wholesale Price ({{ \App\Services\CurrencyHelper::symbol() }}/mo)</label>
                                        <span class="badge bg-success bg-opacity-20 text-success-light" style="font-size: 9px;">+{{ $mdrPartnerMargin }}% margin</span>
                                    </div>
                                    <input type="number" step="0.01" class="form-control form-control-sm sim-input" name="agent_profit_simulation[mdr_partner_price]" value="{{ round(\App\Services\CurrencyHelper::convert($simSettings['mdr_partner_price']), 2) }}">
                                </div>
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label class="form-label small text-muted mb-1">Final Client Retail Price ({{ \App\Services\CurrencyHelper::symbol() }}/mo)</label>
                                        <span class="badge bg-info bg-opacity-20 text-info-light" style="font-size: 9px;">+{{ $mdrRetailMargin }}% margin</span>
                                    </div>
                                    <input type="number" step="0.01" class="form-control form-control-sm sim-input" name="agent_profit_simulation[mdr_client_price]" value="{{ round(\App\Services\CurrencyHelper::convert($simSettings['mdr_client_price']), 2) }}">
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small text-muted mb-1">Max Purchased Limit</label>
                                        <input type="number" class="form-control form-control-sm sim-input" name="agent_profit_simulation[mdr_purchased_limit]" value="{{ $simSettings['mdr_purchased_limit'] }}">
                                        <span class="text-muted d-block mt-1" style="font-size: 9px;">Stop selling cap</span>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small text-muted mb-1">Monthly Growth (+/mo)</label>
                                        <input type="number" class="form-control form-control-sm sim-input" name="agent_profit_simulation[mdr_monthly_growth]" value="{{ $simSettings['mdr_monthly_growth'] }}">
                                        <span class="text-muted d-block mt-1" style="font-size: 9px;">Sales velocity</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SIEM Agent Package -->
                    @php
                        $siemPartnerMargin = round((($simSettings['siem_partner_price'] - $simSettings['siem_base_cost']) / max($simSettings['siem_base_cost'], 0.01)) * 100, 1);
                        $siemRetailMargin = round((($simSettings['siem_client_price'] - $simSettings['siem_base_cost']) / max($simSettings['siem_base_cost'], 0.01)) * 100, 1);
                    @endphp
                    <div class="col-md-4">
                        <div class="card bg-dark bg-opacity-40 border-secondary border-opacity-20 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold text-primary"><i class="bi bi-display me-1"></i> SIEM Agent Package</span>
                                    <span class="badge bg-primary bg-opacity-25 text-primary-light">Current: {{ $simInitial['siem'] }}</span>
                                </div>
                                <div class="small text-muted mb-3">Base Cost Price: <strong class="text-white">{{ \App\Services\CurrencyHelper::format($simSettings['siem_base_cost']) }}/mo</strong></div>

                                <div class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label class="form-label small text-muted mb-1">Partner Wholesale Price ({{ \App\Services\CurrencyHelper::symbol() }}/mo)</label>
                                        <span class="badge bg-success bg-opacity-20 text-success-light" style="font-size: 9px;">+{{ $siemPartnerMargin }}% margin</span>
                                    </div>
                                    <input type="number" step="0.01" class="form-control form-control-sm sim-input" name="agent_profit_simulation[siem_partner_price]" value="{{ round(\App\Services\CurrencyHelper::convert($simSettings['siem_partner_price']), 2) }}">
                                </div>
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label class="form-label small text-muted mb-1">Final Client Retail Price ({{ \App\Services\CurrencyHelper::symbol() }}/mo)</label>
                                        <span class="badge bg-info bg-opacity-20 text-info-light" style="font-size: 9px;">+{{ $siemRetailMargin }}% margin</span>
                                    </div>
                                    <input type="number" step="0.01" class="form-control form-control-sm sim-input" name="agent_profit_simulation[siem_client_price]" value="{{ round(\App\Services\CurrencyHelper::convert($simSettings['siem_client_price']), 2) }}">
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small text-muted mb-1">Max Purchased Limit</label>
                                        <input type="number" class="form-control form-control-sm sim-input" name="agent_profit_simulation[siem_purchased_limit]" value="{{ $simSettings['siem_purchased_limit'] }}">
                                        <span class="text-muted d-block mt-1" style="font-size: 9px;">Stop selling cap</span>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small text-muted mb-1">Monthly Growth (+/mo)</label>
                                        <input type="number" class="form-control form-control-sm sim-input" name="agent_profit_simulation[siem_monthly_growth]" value="{{ $simSettings['siem_monthly_growth'] }}">
                                        <span class="text-muted d-block mt-1" style="font-size: 9px;">Sales velocity</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-arrow">
                <div class="card-arrow-top-left"></div><div class="card-arrow-top-right"></div><div class="card-arrow-bottom-left"></div><div class="card-arrow-bottom-right"></div>
            </div>
        </div>
    </div>

    <!-- MODE 2: Custom Pack Builder & Extra Services -->
    <div id="sim_mode_pack_container" class="{{ $simSettings['mode'] === 'agent' ? 'd-none' : '' }}">
        <div class="card mb-4 border-theme border-opacity-30 bg-black bg-opacity-25 shadow-lg">
            <div class="card-header bg-theme bg-opacity-15 border-bottom border-theme border-opacity-20 py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title text-theme mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-box-seam fs-18px"></i> Custom Service Pack & Add-On Service Builder
                    </h5>
                    <div class="text-muted small mt-1">
                        Bundle agents (EDR, MDR, SIEM) with extra services (e.g. CTI, 24/7 VIP SLA) aligned with total system agent capacity limits.
                    </div>
                </div>
                <button type="button" class="btn btn-outline-theme btn-sm" onclick="addCustomPack()">
                    <i class="bi bi-plus-circle me-1"></i> Add New Custom Pack
                </button>
            </div>
            <div class="card-body">
                <div id="custom_packs_list">
                    @foreach($packs as $idx => $p)
                        <div class="card bg-dark bg-opacity-40 border-secondary border-opacity-20 mb-3 pack-card" id="pack_card_{{ $idx }}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <input type="text" class="form-control form-control-sm fw-bold text-theme w-50" name="agent_profit_simulation[custom_packs][{{ $idx }}][name]" value="{{ $p['name'] }}" placeholder="Pack Name (e.g. Basic EDR + CTI Pack)">
                                    <button type="button" class="btn btn-outline-danger btn-xs" onclick="removeCustomPack('{{ $idx }}')">
                                        <i class="bi bi-trash me-1"></i> Remove Pack
                                    </button>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted mb-1">Included EDR Agents</label>
                                        <input type="number" class="form-control form-control-sm" name="agent_profit_simulation[custom_packs][{{ $idx }}][edr_count]" value="{{ $p['edr_count'] ?? 10 }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted mb-1">Included MDR Agents</label>
                                        <input type="number" class="form-control form-control-sm" name="agent_profit_simulation[custom_packs][{{ $idx }}][mdr_count]" value="{{ $p['mdr_count'] ?? 0 }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted mb-1">Included SIEM Agents</label>
                                        <input type="number" class="form-control form-control-sm" name="agent_profit_simulation[custom_packs][{{ $idx }}][siem_count]" value="{{ $p['siem_count'] ?? 0 }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted mb-1">Initial Packs Deployed</label>
                                        <input type="number" class="form-control form-control-sm" name="agent_profit_simulation[custom_packs][{{ $idx }}][initial_packs]" value="{{ $p['initial_packs'] ?? 1 }}">
                                    </div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted mb-1">Partner Wholesale Price ({{ \App\Services\CurrencyHelper::symbol() }}/mo)</label>
                                        <input type="number" step="0.01" class="form-control form-control-sm" name="agent_profit_simulation[custom_packs][{{ $idx }}][partner_price]" value="{{ round(\App\Services\CurrencyHelper::convert($p['partner_price'] ?? 350), 2) }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted mb-1">Final Client Retail Price ({{ \App\Services\CurrencyHelper::symbol() }}/mo)</label>
                                        <input type="number" step="0.01" class="form-control form-control-sm" name="agent_profit_simulation[custom_packs][{{ $idx }}][client_price]" value="{{ round(\App\Services\CurrencyHelper::convert($p['client_price'] ?? 450), 2) }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted mb-1">Max Purchased Limit</label>
                                        <input type="number" class="form-control form-control-sm" name="agent_profit_simulation[custom_packs][{{ $idx }}][purchased_limit]" value="{{ $p['purchased_limit'] ?? 50 }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted mb-1">Monthly Pack Growth (+/mo)</label>
                                        <input type="number" class="form-control form-control-sm" name="agent_profit_simulation[custom_packs][{{ $idx }}][monthly_growth]" value="{{ $p['monthly_growth'] ?? 5 }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card-arrow">
                <div class="card-arrow-top-left"></div><div class="card-arrow-top-right"></div><div class="card-arrow-bottom-left"></div><div class="card-arrow-bottom-right"></div>
            </div>
        </div>
    </div>

    <!-- Projected Benefit & Revenue Summary Cards -->
    <div class="mb-3">
        <h6 class="text-white fw-bold mb-2"><i class="bi bi-clock-history text-info me-2"></i> Projected Benefit & Revenue Summary</h6>
        <div class="row g-3">
            @foreach($simHorizons as $hMonths => $hz)
                <div class="col">
                    <div class="card bg-dark bg-opacity-50 border-secondary border-opacity-30 h-100 text-center py-2 px-1 shadow-sm">
                        <div class="card-body p-2">
                            <span class="badge bg-secondary bg-opacity-30 text-white mb-2 px-2 py-1" style="font-size: 10px;">{{ $hz['label'] }}</span>
                            <div class="mono-cell text-success fs-16px fw-bold" id="hz_direct_profit_{{ $hMonths }}">
                                {{ \App\Services\CurrencyHelper::format($hz['direct_profit']) }}
                            </div>
                            <div class="text-muted" style="font-size: 11px;">Direct Net Profit</div>
                            <hr class="my-2 border-secondary border-opacity-20">
                            <div class="mono-cell text-info small fw-semibold" id="hz_partner_profit_{{ $hMonths }}">
                                {{ \App\Services\CurrencyHelper::format($hz['partner_profit']) }}
                            </div>
                            <div class="text-muted" style="font-size: 10px;">Partner Channel Profit</div>
                            <div class="mt-2" id="hz_status_badge_{{ $hMonths }}">
                                @if($hz['is_sold_out'])
                                    <span class="badge bg-danger bg-opacity-25 text-danger-light border border-danger border-opacity-40 py-1 px-2" style="font-size: 9px;">
                                        <i class="bi bi-exclamation-octagon-fill me-1"></i> SOLD OUT
                                    </span>
                                @else
                                    <span class="badge bg-success bg-opacity-25 text-success-light border border-success border-opacity-40 py-1 px-2" style="font-size: 9px;">
                                        {{ $hz['deployed_at_end'] }} Active Deployed
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Month-by-Month Cumulative Table (1 to 36 Months) -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="text-white fw-bold mb-0"><i class="bi bi-calendar3 text-success me-2"></i> Month-by-Month Projection Schedule (36 Months)</h6>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small me-2">Dual metric view: Packs Sold & Equivalent Agent Breakdown.</span>
            <button type="submit" form="simulator-main-form" class="btn btn-success btn-xs fw-bold px-3 py-1">
                <i class="bi bi-arrow-repeat me-1"></i> Update & Recalculate
            </button>
        </div>
    </div>

    <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
        <table class="table table-borderless table-hover align-middle mb-0" id="sim_schedule_table">
            <thead class="sticky-top bg-dark border-bottom border-secondary border-opacity-30">
                <tr class="text-muted small uppercase-tracking">
                    <th>Month</th>
                    <th>Status</th>
                    <th class="text-center">Active Deployed</th>
                    @if($simSettings['mode'] === 'pack')
                        <th class="text-center">Equivalent Agents (EDR/MDR/SIEM)</th>
                    @endif
                    <th>Monthly Cost</th>
                    <th>Partner Revenue</th>
                    <th>Direct Revenue</th>
                    <th>Partner Margin</th>
                    <th class="text-end">Direct Net Profit</th>
                    <th class="text-end">Cumul. Direct Profit</th>
                </tr>
            </thead>
            <tbody>
                @foreach($simTimeline as $m => $row)
                    <tr class="border-bottom border-secondary border-opacity-10 {{ $row['is_fully_sold_out'] ? 'table-danger bg-opacity-10' : '' }}">
                        <td class="fw-bold text-theme">Month {{ $row['month'] }}</td>
                        <td>
                            @if($row['is_fully_sold_out'])
                                <span class="badge bg-danger text-white border border-danger">
                                    <i class="bi bi-slash-circle me-1"></i> ALL SOLD OUT
                                </span>
                            @else
                                <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-30">
                                    ACTIVE GROWTH
                                </span>
                            @endif
                        </td>
                        <td class="text-center mono-cell fw-bold text-white">
                            @if($simSettings['mode'] === 'pack')
                                {{ $row['packs_sold'] ?? $row['total_deployed'] }} Packs
                            @else
                                {{ $row['total_deployed'] }} Agents
                            @endif
                        </td>
                        @if($simSettings['mode'] === 'pack')
                            <td class="text-center mono-cell text-info small">
                                <span class="text-info">{{ $row['edr_agents_sold'] ?? 0 }} EDR</span> /
                                <span class="text-success">{{ $row['mdr_agents_sold'] ?? 0 }} MDR</span> /
                                <span class="text-primary">{{ $row['siem_agents_sold'] ?? 0 }} SIEM</span>
                                (<strong>{{ $row['total_agents_sold'] ?? 0 }} Total</strong>)
                            </td>
                        @endif
                        <td class="mono-cell text-muted">{{ \App\Services\CurrencyHelper::format($row['monthly_cost']) }}</td>
                        <td class="mono-cell text-info-light">{{ \App\Services\CurrencyHelper::format($row['partner_revenue']) }}</td>
                        <td class="mono-cell text-white">{{ \App\Services\CurrencyHelper::format($row['direct_revenue']) }}</td>
                        <td class="mono-cell text-warning">{{ \App\Services\CurrencyHelper::format($row['partner_margin']) }}</td>
                        <td class="mono-cell text-end text-success fw-bold">{{ \App\Services\CurrencyHelper::format($row['direct_profit']) }}</td>
                        <td class="mono-cell text-end text-success fw-bold">{{ \App\Services\CurrencyHelper::format($row['cumul_direct_profit']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</form>

@endsection

@section('scripts')
<script>
    function toggleSimMode(mode) {
        var agentBox = document.getElementById('sim_mode_agent_container');
        var packBox = document.getElementById('sim_mode_pack_container');
        if (mode === 'pack') {
            if (agentBox) agentBox.classList.add('d-none');
            if (packBox) packBox.classList.remove('d-none');
        } else {
            if (agentBox) agentBox.classList.remove('d-none');
            if (packBox) packBox.classList.add('d-none');
        }
    }

    function renderMarkdownToHtml(md) {
        if (!md) return '';

        let lines = md.split('\n');
        let inTable = false;
        let tableHtml = '';
        let processedLines = [];

        for (let i = 0; i < lines.length; i++) {
            let line = lines[i].trim();

            if (line.startsWith('|') && line.endsWith('|')) {
                if (!inTable) {
                    inTable = true;
                    tableHtml = '<div class="table-responsive my-3"><table class="table table-sm table-dark table-striped table-bordered align-middle mb-0"><thead class="table-dark text-warning border-warning border-opacity-30"><tr>';
                    let cells = line.split('|').slice(1, -1);
                    cells.forEach(c => { tableHtml += `<th class="py-2 px-3 bg-dark bg-opacity-80 fw-bold">${c.trim()}</th>`; });
                    tableHtml += '</tr></thead><tbody>';
                } else if (line.includes('---')) {
                    continue;
                } else {
                    tableHtml += '<tr>';
                    let cells = line.split('|').slice(1, -1);
                    cells.forEach(c => {
                        let text = c.trim();
                        text = text.replace(/✅\s*Aligned/gi, '<span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-30"><i class="bi bi-check-circle-fill me-1"></i>Aligned</span>');
                        text = text.replace(/❌\s*Underpriced/gi, '<span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-30"><i class="bi bi-x-circle-fill me-1"></i>Underpriced</span>');
                        text = text.replace(/⚠️\s*Soft/gi, '<span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-30"><i class="bi bi-exclamation-triangle-fill me-1"></i>Soft</span>');
                        text = text.replace(/✅\s*Strong/gi, '<span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-30"><i class="bi bi-shield-check me-1"></i>Strong</span>');
                        text = text.replace(/🏆\s*Anchor Product/gi, '<span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-30"><i class="bi bi-trophy-fill me-1"></i>Anchor Product</span>');
                        tableHtml += `<td class="py-2 px-3 font-monospace fs-12px">${text}</td>`;
                    });
                    tableHtml += '</tr>';
                }
            } else {
                if (inTable) {
                    tableHtml += '</tbody></table></div>';
                    processedLines.push(tableHtml);
                    inTable = false;
                    tableHtml = '';
                }
                processedLines.push(line);
            }
        }
        if (inTable) {
            tableHtml += '</tbody></table></div>';
            processedLines.push(tableHtml);
        }

        let html = processedLines.join('\n');

        html = html.replace(/^# (.*$)/gim, '<h3 class="text-warning fw-bold border-bottom border-warning border-opacity-25 pb-2 mb-3 mt-4"><i class="bi bi-graph-up me-2"></i>$1</h3>');
        html = html.replace(/^## (.*$)/gim, '<h4 class="text-info fw-bold border-bottom border-secondary border-opacity-25 pb-2 mb-3 mt-4"><i class="bi bi-bookmark-fill me-2 fs-14px"></i>$1</h4>');
        html = html.replace(/^### (.*$)/gim, '<h5 class="text-white fw-bold mb-2 mt-3"><i class="bi bi-chevron-right text-warning me-1"></i>$1</h5>');

        html = html.replace(/\*\*(.*?)\*\*/g, '<strong class="text-white">$1</strong>');
        html = html.replace(/\*(.*?)\*/g, '<em class="text-muted">$1</em>');

        html = html.replace(/^\* (.*$)/gim, '<li class="ms-3 mb-1 text-light">$1</li>');
        html = html.replace(/^- (.*$)/gim, '<li class="ms-3 mb-1 text-light">$1</li>');

        html = html.replace(/\n\n/g, '<br><br>');

        return html;
    }

    document.getElementById('btn-run-ai-market')?.addEventListener('click', function() {
        var card = document.getElementById('ai-market-report-card');
        var loading = document.getElementById('ai-market-loading');
        var content = document.getElementById('ai-market-content');

        if (!card || !content) return;

        card.style.display = 'block';
        if (loading) loading.style.display = 'block';
        content.innerHTML = '';
        card.scrollIntoView({ behavior: 'smooth' });

        fetch("{{ route('simulator.ai-analysis', [$client->id, $scenario->id]) }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (loading) loading.style.display = 'none';

            if (data.status === 'success' && data.analysis) {
                var r = data.analysis;
                var score = r.market_attractiveness_score || 8;
                var scoreColor = score >= 8 ? 'success' : (score >= 5 ? 'warning' : 'danger');

                var html = `
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="card bg-black bg-opacity-40 border-${scoreColor} border-opacity-30 h-100 p-3">
                                <div class="text-muted small uppercase-tracking mb-1">Market Attractiveness Score</div>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="fs-36px fw-bold text-${scoreColor}">${score}<small class="fs-18px text-muted">/10</small></span>
                                    <div class="flex-grow-1">
                                        <div class="progress bg-secondary bg-opacity-30" style="height: 8px;">
                                            <div class="progress-bar bg-${scoreColor}" style="width: ${score * 10}%"></div>
                                        </div>
                                        <span class="badge bg-${scoreColor} bg-opacity-20 text-${scoreColor} border border-${scoreColor} border-opacity-30 mt-2">
                                            ${score >= 8 ? 'High Commercial Fit' : 'Moderate Commercial Fit'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="card bg-black bg-opacity-40 border-secondary border-opacity-30 h-100 p-3">
                                <div class="text-muted small uppercase-tracking mb-1">AI Provider & Execution Harness</div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="badge bg-theme text-black fw-bold"><i class="bi bi-robot me-1"></i> ${r.ai_provider_used || 'Ollama'}</span>
                                    <span class="badge bg-dark text-info border border-info border-opacity-30"><i class="bi bi-cpu me-1"></i> ${r.ai_model_used || 'gemma4:e2b'}</span>
                                    <span class="badge bg-dark text-success border border-success border-opacity-30"><i class="bi bi-diagram-3 me-1"></i> phpkaiharness Session</span>
                                </div>
                                <p class="text-white-50 small mb-0">Analysis compiled by autonomous Market Buying Agent evaluating partner wholesale margins (+25%) vs direct retail margins (+50%) on On-Premise Deployment Offer.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Structured Insights Grid -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="card bg-dark bg-opacity-50 border-info border-opacity-30 h-100">
                                <div class="card-body">
                                    <h6 class="text-info fw-bold mb-2"><i class="bi bi-people me-2"></i> Buyer Persona & Market Behavior</h6>
                                    <p class="text-white-50 small mb-0">${r.buyer_persona_behavior || 'Analysis of partner vs direct client purchasing behavior.'}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-dark bg-opacity-50 border-success border-opacity-30 h-100">
                                <div class="card-body">
                                    <h6 class="text-success fw-bold mb-2"><i class="bi bi-currency-dollar me-2"></i> Pricing Strategy & Margins</h6>
                                    <p class="text-white-50 small mb-0">${r.pricing_strategy_feedback || 'Assessment of partner wholesale vs client retail margins.'}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-dark bg-opacity-50 border-warning border-opacity-30 h-100">
                                <div class="card-body">
                                    <h6 class="text-warning fw-bold mb-2"><i class="bi bi-box-seam me-2"></i> Pack vs Standalone Unit Preference</h6>
                                    <p class="text-white-50 small mb-0">${r.pack_vs_agent_preference || 'Comparison of standalone unit agents vs custom service packs.'}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-dark bg-opacity-50 border-primary border-opacity-30 h-100">
                                <div class="card-body">
                                    <h6 class="text-primary fw-bold mb-2"><i class="bi bi-speedometer2 me-2"></i> Capacity & Stockout Risk Forecast</h6>
                                    <p class="text-white-50 small mb-0">${r.capacity_sold_out_forecast || 'Forecast of capacity limit caps.'}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                if (r.optimization_recommendations && Array.isArray(r.optimization_recommendations) && r.optimization_recommendations.length > 0) {
                    html += `
                        <div class="card bg-warning bg-opacity-10 border-warning border-opacity-30 mb-4">
                            <div class="card-body">
                                <h6 class="text-warning fw-bold mb-3"><i class="bi bi-lightbulb-fill me-2"></i> Actionable Profit Optimization Recommendations</h6>
                                <div class="row g-2">
                    `;
                    r.optimization_recommendations.forEach(function(rec, idx) {
                        html += `
                            <div class="col-12">
                                <div class="bg-black bg-opacity-40 border border-secondary border-opacity-20 rounded p-2 px-3 d-flex align-items-start gap-2">
                                    <span class="badge bg-warning text-black font-monospace fw-bold">${idx + 1}</span>
                                    <span class="text-white small">${rec}</span>
                                </div>
                            </div>
                        `;
                    });
                    html += `
                                </div>
                            </div>
                        </div>
                    `;
                }

                if (r.full_market_report) {
                    html += `
                        <div class="card bg-black bg-opacity-40 border-secondary border-opacity-30">
                            <div class="card-header bg-dark bg-opacity-50 border-bottom border-secondary border-opacity-20 py-2">
                                <span class="text-white-50 small fw-bold uppercase-tracking"><i class="bi bi-file-earmark-text me-2"></i> Comprehensive Market Analysis Report</span>
                            </div>
                            <div class="card-body py-3">
                                <div class="markdown-body text-white text-opacity-90">
                                    ${renderMarkdownToHtml(r.full_market_report)}
                                </div>
                            </div>
                        </div>
                    `;
                }

                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="alert alert-danger mb-0">Failed to generate AI Market Buying Report.</div>';
            }
        })
        .catch(err => {
            if (loading) loading.style.display = 'none';
            content.innerHTML = '<div class="alert alert-danger mb-0">Error communicating with AI Market Buying Agent: ' + err.message + '</div>';
        });
    });
</script>
@endsection

