@extends('layouts.app')

@section('title', __('messages.scenario_templates'))

@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item"><a href="#">{{ strtoupper(__('messages.settings')) }}</a></li>
    <li class="breadcrumb-item active">{{ strtoupper(__('messages.scenario_templates')) }}</li>
</ul>

<h1 class="page-header">
    {{ __('messages.scenario_templates') }} <small>{{ __('messages.scenarios_help_text') }}</small>
</h1>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <strong>{{ __('messages.success') }}!</strong> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <strong>Error!</strong> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row">
    <!-- List & Edit Scenarios -->
    <div class="col-xl-8 col-lg-7">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3 text-theme">
                    <i class="bi bi-clock-history me-2"></i> {{ __('messages.active_scenario_templates') }}
                </h5>
                <p class="text-muted small mb-4">{{ __('messages.scenarios_help_text') }}</p>

                @foreach($scenarios as $sc)
                    <div class="border border-secondary border-opacity-30 p-4 rounded mb-4 bg-black bg-opacity-15 position-relative">
                        <form action="{{ route('scenarios.update', $sc->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary border-opacity-20">
                                <div class="d-flex align-items-center gap-2">
                                    <input type="text" name="name" class="form-control form-control-sm fw-bold fs-16px text-white bg-transparent border-0 p-0" value="{{ $sc->name }}" style="width: 250px;" required>
                                    @if($sc->is_system_default)
                                        <span class="badge bg-secondary bg-opacity-20 text-muted border border-secondary border-opacity-30 small">{{ __('messages.default') }}</span>
                                    @endif
                                </div>
                                <span class="badge {{ $sc->workload_profile === 'min' ? 'badge-cold' : ($sc->workload_profile === 'avg' ? 'badge-warm' : 'badge-hot') }}">
                                    {{ $sc->workload_profile }} {{ __('messages.profile') }}
                                </span>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-muted small">{{ __('messages.description') }}</label>
                                <input type="text" name="description" class="form-control" value="{{ $sc->description }}" required>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6 col-sm-12">
                                    <label class="form-label text-muted small">{{ __('messages.total_retention_days') }}</label>
                                    <input type="number" name="retention_days" class="form-control mono-cell" value="{{ $sc->retention_days }}" required>
                                </div>
                                
                                <div class="col-md-6 col-sm-12">
                                    <label class="form-label text-muted small">{{ __('messages.workload_profile_capacity') }}</label>
                                    <select name="workload_profile" class="form-select">
                                        <option value="min" {{ $sc->workload_profile === 'min' ? 'selected' : '' }}>{{ __('messages.workload_profile_min_desc') }}</option>
                                        <option value="avg" {{ $sc->workload_profile === 'avg' ? 'selected' : '' }}>{{ __('messages.workload_profile_avg_desc') }}</option>
                                        <option value="max" {{ $sc->workload_profile === 'max' ? 'selected' : '' }}>{{ __('messages.workload_profile_max_desc') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="border border-secondary border-opacity-25 p-3 rounded bg-black bg-opacity-20 mb-3">
                                <h6 class="text-muted small mb-3">{{ __('messages.lifecycle_tiers_alloc') }}</h6>
                                
                                <div class="row g-2">
                                    <!-- Hot -->
                                    <div class="col-3 text-center border-end border-secondary border-opacity-20">
                                        <span class="badge badge-hot mb-2 d-inline-block">{{ __('messages.hot_days') }}</span>
                                        <input type="number" name="hot_days" class="form-control form-control-sm text-center mono-cell mb-1" value="{{ $sc->hot_days }}" placeholder="Days" title="Days in Hot" required>
                                        <input type="number" name="hot_replicas" class="form-control form-control-sm text-center mono-cell" value="{{ $sc->hot_replicas }}" placeholder="Replicas" title="Replicas in Hot" required>
                                    </div>
                                    
                                    <!-- Warm -->
                                    <div class="col-3 text-center border-end border-secondary border-opacity-20">
                                        <span class="badge badge-warm mb-2 d-inline-block">{{ __('messages.warm_days') }}</span>
                                        <input type="number" name="warm_days" class="form-control form-control-sm text-center mono-cell mb-1" value="{{ $sc->warm_days }}" placeholder="Days" title="Days in Warm" required>
                                        <input type="number" name="warm_replicas" class="form-control form-control-sm text-center mono-cell" value="{{ $sc->warm_replicas }}" placeholder="Replicas" title="Replicas in Warm" required>
                                    </div>

                                    <!-- Cold -->
                                    <div class="col-3 text-center border-end border-secondary border-opacity-20">
                                        <span class="badge badge-cold mb-2 d-inline-block">{{ __('messages.cold_days') }}</span>
                                        <input type="number" name="cold_days" class="form-control form-control-sm text-center mono-cell mb-1" value="{{ $sc->cold_days }}" placeholder="Days" title="Days in Cold" required>
                                        <input type="number" name="cold_replicas" class="form-control form-control-sm text-center mono-cell" value="{{ $sc->cold_replicas }}" placeholder="Replicas" title="Replicas in Cold" required>
                                    </div>

                                    <!-- Frozen -->
                                    <div class="col-3 text-center">
                                        <span class="badge badge-frozen mb-2 d-inline-block">{{ __('messages.frozen_days') }}</span>
                                        <input type="number" name="frozen_days" class="form-control form-control-sm text-center mono-cell mb-1" value="{{ $sc->frozen_days }}" placeholder="Days" title="Days in Frozen" required>
                                        <input type="number" name="frozen_replicas" class="form-control form-control-sm text-center mono-cell" value="{{ $sc->frozen_replicas }}" placeholder="Replicas" title="Replicas in Frozen" required>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                @if(!$sc->is_system_default)
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="if(confirm('Are you sure you want to delete this custom template?')) { document.getElementById('delete-form-{{ $sc->id }}').submit(); }">
                                        {{ __('messages.delete_template') }}
                                    </button>
                                @endif
                                <button type="submit" class="btn btn-outline-theme btn-sm px-4">
                                    {{ __('messages.save_changes') }}
                                </button>
                            </div>
                        </form>

                        @if(!$sc->is_system_default)
                            <form id="delete-form-{{ $sc->id }}" action="{{ route('scenarios.destroy', $sc->id) }}" method="POST" style="display:none;">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endif
                    </div>
                @endforeach
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

    <!-- Create Custom Scenario Form -->
    <div class="col-xl-4 col-lg-5">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3 text-theme">
                    <i class="bi bi-plus-circle me-2"></i> {{ __('messages.add_custom_scenario') }}
                </h5>
                <p class="text-muted small mb-4">{{ __('messages.create_custom_scenario_help') }}</p>
                
                <form action="{{ route('scenarios.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="name" class="form-label text-muted small">{{ __('messages.scenario_name') }}</label>
                        <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Custom Corporate Long" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="desc" class="form-label text-muted small">{{ __('messages.description') }}</label>
                        <input type="text" id="desc" name="description" class="form-control" placeholder="e.g. 180-day compliance retention" required>
                    </div>

                    <div class="mb-3">
                        <label for="profile" class="form-label text-muted small">{{ __('messages.workload_profile') }}</label>
                        <select id="profile" name="workload_profile" class="form-select">
                            <option value="min">Minimum Ingest</option>
                            <option value="avg" selected>Average Ingest</option>
                            <option value="max">Maximum Ingest</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="ret_days" class="form-label text-muted small">{{ __('messages.total_retention_days') }}</label>
                        <input type="number" id="ret_days" name="retention_days" class="form-control mono-cell" value="180" min="1" required>
                    </div>

                    <div class="border-top border-secondary border-opacity-30 pt-3 mb-4">
                        <h6 class="text-muted small mb-3">{{ __('messages.tier_sizing_allocation') }}</h6>
                        
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1" style="font-size: 11px;">{{ __('messages.hot_days') }}</label>
                                <input type="number" name="hot_days" class="form-control mono-cell form-control-sm" value="30" min="0" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1" style="font-size: 11px;">{{ __('messages.hot_replicas') }}</label>
                                <input type="number" name="hot_replicas" class="form-control mono-cell form-control-sm" value="1" min="0" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1" style="font-size: 11px;">{{ __('messages.warm_days') }}</label>
                                <input type="number" name="warm_days" class="form-control mono-cell form-control-sm" value="60" min="0" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1" style="font-size: 11px;">{{ __('messages.warm_replicas') }}</label>
                                <input type="number" name="warm_replicas" class="form-control mono-cell form-control-sm" value="1" min="0" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1" style="font-size: 11px;">{{ __('messages.cold_days') }}</label>
                                <input type="number" name="cold_days" class="form-control mono-cell form-control-sm" value="90" min="0" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1" style="font-size: 11px;">{{ __('messages.cold_replicas') }}</label>
                                <input type="number" name="cold_replicas" class="form-control mono-cell form-control-sm" value="0" min="0" required>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1" style="font-size: 11px;">{{ __('messages.frozen_days') }}</label>
                                <input type="number" name="frozen_days" class="form-control mono-cell form-control-sm" value="0" min="0" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1" style="font-size: 11px;">{{ __('messages.frozen_replicas') }}</label>
                                <input type="number" name="frozen_replicas" class="form-control mono-cell form-control-sm" value="0" min="0" required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-outline-theme d-block w-100 py-2">
                        {{ __('messages.create_scenario_template') }}
                    </button>
                </form>
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
</div>
@endsection
