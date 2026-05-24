@extends('layouts.app')

@section('title', __('messages.ingest_benchmarks'))

@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item"><a href="#">{{ strtoupper(__('messages.settings')) }}</a></li>
    <li class="breadcrumb-item active">{{ strtoupper(__('messages.ingest_benchmarks')) }}</li>
</ul>

<h1 class="page-header">
    {{ __('messages.ingest_benchmarks') }} <small>{{ __('messages.system_default_benchmarks_help') }}</small>
</h1>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <strong>{{ __('messages.success') }}!</strong> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row">
    <!-- List & Edit Asset Types -->
    <div class="col-xl-8 col-lg-7">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3 text-theme">
                    <i class="bi bi-sliders me-2"></i> {{ __('messages.system_default_benchmarks') }}
                </h5>
                <p class="text-muted small mb-4">{{ __('messages.system_default_benchmarks_help') }}</p>

                @foreach($assetTypes as $type)
                    <div class="border border-secondary border-opacity-30 p-4 rounded mb-3 bg-black bg-opacity-15">
                        <form action="{{ route('asset-types.update', $type->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary border-opacity-20">
                                <input type="text" name="name" class="form-control form-control-sm fw-bold fs-16px text-white bg-transparent border-0 p-0" value="{{ $type->name }}" style="width: 300px;" required>
                                <span class="badge bg-secondary bg-opacity-20 text-white border border-secondary border-opacity-30">
                                    {{ str_replace('_', ' ', $type->calibration_mode) }}
                                </span>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4 col-sm-6 mb-2">
                                    <label class="form-label text-muted small mb-1">{{ __('messages.avg_event_size_bytes') }}</label>
                                    <input type="number" name="avg_event_size_bytes" class="form-control mono-cell" value="{{ $type->avg_event_size_bytes }}" required>
                                </div>
                                
                                <div class="col-md-4 col-sm-6 mb-2">
                                    <label class="form-label text-muted small mb-1">{{ __('messages.calibration_mode') }}</label>
                                    <select name="calibration_mode" class="form-select">
                                        <option value="eps_per_device" {{ $type->calibration_mode === 'eps_per_device' ? 'selected' : '' }}>{{ __('messages.eps_per_device') }}</option>
                                        <option value="monthly_gb_per_device" {{ $type->calibration_mode === 'monthly_gb_per_device' ? 'selected' : '' }}>{{ __('messages.monthly_gb_per_device') }}</option>
                                        <option value="monthly_gb_total" {{ $type->calibration_mode === 'monthly_gb_total' ? 'selected' : '' }}>{{ __('messages.monthly_gb_total') }}</option>
                                    </select>
                                </div>

                                <div class="col-md-4 col-sm-12 mb-2">
                                    <label class="form-label text-muted small mb-1">{{ __('messages.min_profile_eps') }}</label>
                                    <input type="text" name="min_eps_default" class="form-control mono-cell" value="{{ $type->min_eps_default }}" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4 col-sm-6 mb-2">
                                    <label class="form-label text-muted small mb-1">{{ __('messages.avg_profile_eps') }}</label>
                                    <input type="text" name="avg_eps_default" class="form-control mono-cell" value="{{ $type->avg_eps_default }}" required>
                                </div>

                                <div class="col-md-4 col-sm-6 mb-2">
                                    <label class="form-label text-muted small mb-1">{{ __('messages.max_profile_eps') }}</label>
                                    <input type="text" name="max_eps_default" class="form-control mono-cell" value="{{ $type->max_eps_default }}" placeholder="N/A">
                                </div>

                                <div class="col-md-4 col-sm-12 mb-2">
                                    <label class="form-label text-muted small mb-1">{{ __('messages.max_monthly_gb') }}</label>
                                    <input type="text" name="max_monthly_gb_default" class="form-control mono-cell" value="{{ $type->max_monthly_gb_default }}" placeholder="N/A">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-muted small mb-1">{{ __('messages.desc_log_scope') }}</label>
                                <input type="text" name="description" class="form-control" value="{{ $type->description }}">
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-outline-theme btn-sm px-4">
                                    {{ __('messages.save_changes') }}
                                </button>
                            </div>
                        </form>
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

    <!-- Create Asset Type Form -->
    <div class="col-xl-4 col-lg-5">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3 text-theme">
                    <i class="bi bi-plus-circle me-2"></i> {{ __('messages.add_log_source_type') }}
                </h5>
                <p class="text-muted small mb-4">{{ __('messages.create_log_ref_help') }}</p>
                
                <form action="{{ route('asset-types.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="new_name" class="form-label text-muted small">{{ __('messages.source_name') }}</label>
                        <input type="text" id="new_name" name="name" class="form-control" placeholder="e.g. Cisco Firepower" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_size" class="form-label text-muted small">{{ __('messages.avg_event_size_bytes') }}</label>
                        <input type="number" id="new_size" name="avg_event_size_bytes" class="form-control mono-cell" placeholder="e.g. 500" required>
                    </div>

                    <div class="mb-3">
                        <label for="new_mode" class="form-label text-muted small">{{ __('messages.calibration_mode') }}</label>
                        <select id="new_mode" name="calibration_mode" class="form-select">
                            <option value="eps_per_device">{{ __('messages.eps_per_device') }}</option>
                            <option value="monthly_gb_per_device">{{ __('messages.monthly_gb_per_device') }}</option>
                            <option value="monthly_gb_total">{{ __('messages.monthly_gb_total') }}</option>
                        </select>
                    </div>
                    
                    <div class="border-top border-secondary border-opacity-30 pt-3 mb-4">
                        <h6 class="text-muted small mb-3">{{ __('messages.calibration_ingest_defaults') }}</h6>
                        
                        <div class="mb-2">
                            <label for="new_min" class="form-label text-muted small mb-1" style="font-size: 11px;">{{ __('messages.min_profile_eps') }}</label>
                            <input type="text" id="new_min" name="min_eps_default" class="form-control mono-cell form-control-sm" placeholder="e.g. 1.0" required>
                        </div>

                        <div class="mb-2">
                            <label for="new_avg" class="form-label text-muted small mb-1" style="font-size: 11px;">{{ __('messages.avg_profile_eps') }}</label>
                            <input type="text" id="new_avg" name="avg_eps_default" class="form-control mono-cell form-control-sm" placeholder="e.g. 5.0" required>
                        </div>

                        <div class="mb-2">
                            <label for="new_max_eps" class="form-label text-muted small mb-1" style="font-size: 11px;">{{ __('messages.max_profile_eps') }}</label>
                            <input type="text" id="new_max_eps" name="max_eps_default" class="form-control mono-cell form-control-sm" placeholder="e.g. 20.0">
                        </div>

                        <div>
                            <label for="new_max_gb" class="form-label text-muted small mb-1" style="font-size: 11px;">{{ __('messages.max_monthly_limit_opt') }}</label>
                            <input type="text" id="new_max_gb" name="max_monthly_gb_default" class="form-control mono-cell form-control-sm" placeholder="e.g. 100">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="new_description" class="form-label text-muted small">{{ __('messages.description') }}</label>
                        <input type="text" id="new_description" name="description" class="form-control" placeholder="e.g. Core network boundary events">
                    </div>

                    <button type="submit" class="btn btn-outline-theme d-block w-100 py-2">
                        {{ __('messages.create_log_source_type') }}
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
