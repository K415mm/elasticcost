@extends('layouts.app')

@section('title', 'Clients - Sizing Projects')

@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item"><a href="#">{{ __('messages.clients') }}</a></li>
    <li class="breadcrumb-item active">{{ __('messages.project_list') }}</li>
</ul>

<h1 class="page-header">
    {{ __('messages.client_sizing_projects') }} <small>{{ __('messages.client_sizing_projects_subtitle') }}</small>
</h1>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <strong>Success!</strong> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row">
    <!-- Clients List -->
    <div class="col-xl-8 col-lg-7">
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-grow-1">
                        <h5 class="card-title mb-1 text-theme">
                            <i class="bi bi-people-fill me-2"></i> {{ __('messages.active_client_profiles') }}
                        </h5>
                        <p class="text-muted small mb-0">{{ __('messages.active_client_profiles_subtitle') }}</p>
                    </div>
                </div>

                @if($clients->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-folder-x fs-1 my-3 d-block opacity-50"></i>
                        <p>{{ __('messages.no_client_projects') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-borderless table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="border-bottom text-muted">{{ __('messages.client_name') }}</th>
                                    <th class="border-bottom text-muted">{{ __('messages.log_sources') }}</th>
                                    <th class="border-bottom text-muted">{{ __('messages.created') }}</th>
                                    <th class="border-bottom text-muted text-end">{{ __('messages.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($clients as $c)
                                    <tr class="align-middle">
                                        <td>
                                            <a href="{{ route('clients.show', $c->id) }}" class="fw-bold text-decoration-none text-theme fs-16px">
                                                {{ $c->name }}
                                            </a>
                                            <div class="small text-muted mt-1">
                                                {{ Str::limit($c->description, 60) }}
                                            </div>
                                        </td>
                                        <td class="mono-cell text-white">
                                            <span class="badge bg-theme bg-opacity-15 text-theme border border-theme border-opacity-30">
                                                {{ $c->client_assets_count }} sources
                                            </span>
                                        </td>
                                        <td class="small text-muted">
                                            {{ $c->created_at->format('M d, Y') }}
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2 justify-content-end">
                                                <a href="{{ route('clients.show', $c->id) }}" class="btn btn-outline-theme btn-sm px-3">
                                                    {{ __('messages.open') }}
                                                </a>
                                                <form action="{{ route('clients.destroy', $c->id) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure you want to delete this client? This will remove all associated inventory and calculations.') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm px-2">
                                                        {{ __('messages.delete') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
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
    </div>

    <!-- Create Client Form -->
    <div class="col-xl-4 col-lg-5">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3 text-theme">
                    <i class="bi bi-plus-circle me-2"></i> {{ __('messages.initialize_project') }}
                </h5>
                <p class="text-muted small mb-4">{{ __('messages.initialize_project_subtitle') }}</p>
                
                <form action="{{ route('clients.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="name" class="form-label text-muted">{{ __('messages.client_project_name') }}</label>
                        <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Acme Corp" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="description" class="form-label text-muted">{{ __('messages.scope_description') }}</label>
                        <textarea id="description" name="description" class="form-control" rows="4" placeholder="{{ __('Describe infrastructure scope, compliance target, or tier overrides...') }}"></textarea>
                    </div>

                    <button type="submit" class="btn btn-outline-theme d-block w-100 py-2">
                        {{ __('messages.initialize_sizing_profile') }}
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
