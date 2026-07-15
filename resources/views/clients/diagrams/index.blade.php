@extends('layouts.app')
 
@section('title', "Diagrams: {$client->name}")
 
@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">{{ __('messages.clients') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('clients.show', $client->id) }}">{{ strtoupper($client->name) }}</a></li>
    <li class="breadcrumb-item active">DIAGRAMS</li>
</ul>
 
<div class="d-flex align-items-center mb-4">
    <div class="flex-grow-1">
        <h1 class="page-header mb-0">
            {{ $client->name }} <small class="d-block mt-1">Manage SOC architectures, AWS/Azure deployments, and network topologies.</small>
        </h1>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('clients.show', $client->id) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Client Details
        </a>
        <button type="button" class="btn btn-outline-theme" data-bs-toggle="modal" data-bs-target="#newDiagramModal">
            <i class="bi bi-plus-circle me-1"></i> New Diagram
        </button>
    </div>
</div>
 
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <strong>Success!</strong> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
 
<!-- Diagram List Section -->
<div class="card mb-5">
    <div class="card-body">
        <div class="d-flex align-items-center mb-4">
            <h5 class="card-title mb-0 text-theme">
                <i class="bi bi-diagram-3-fill me-2"></i> Client Diagrams List
            </h5>
            <div class="ms-auto d-flex align-items-center gap-2">
                <span class="badge bg-secondary bg-opacity-20 text-white border border-secondary border-opacity-30">
                    Total: {{ $diagrams->count() }}
                </span>
            </div>
        </div>
 
        @if($diagrams->isEmpty())
            <div class="text-center py-5">
                <div class="avatar avatar-lg bg-secondary bg-opacity-10 text-muted mb-3 mx-auto" style="width: 64px; height: 64px; display: flex; align-items: center; justify-content: center; rounded: 50%;">
                    <i class="bi bi-image-alt fs-2"></i>
                </div>
                <h5 class="text-white">No Diagrams Found</h5>
                <p class="text-muted small">No draw.io diagrams have been created for this client yet.</p>
                <div class="d-flex justify-content-center gap-2 mt-3">
                    <button type="button" class="btn btn-outline-theme btn-sm" data-bs-toggle="modal" data-bs-target="#newDiagramModal">
                        <i class="bi bi-plus-circle me-1"></i> Create Manually
                    </button>
                    <a href="{{ route('ai-chat.index') }}?prompt=Create+a+SOC+architecture+diagram+for+client+{{ urlencode($client->name) }}" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-robot me-1"></i> Generate with AI
                    </a>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-borderless table-hover mb-0 align-middle">
                    <thead>
                        <tr class="border-bottom">
                            <th class="text-muted" style="width: 5%;">#</th>
                            <th class="text-muted" style="width: 35%;">Diagram Name & Type</th>
                            <th class="text-muted" style="width: 25%;">Linked Scenario</th>
                            <th class="text-muted" style="width: 20%;">Last Modified</th>
                            <th class="text-muted text-end" style="width: 15%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($diagrams as $index => $diagram)
                            <tr>
                                <td class="text-muted">{{ $index + 1 }}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-3 p-2 bg-theme bg-opacity-15 text-theme rounded border border-theme border-opacity-30">
                                            @if($diagram->type === 'soc_architecture')
                                                <i class="bi bi-shield-fill-check fs-5"></i>
                                            @elseif($diagram->type === 'deployment_topology')
                                                <i class="bi bi-cloud-check-fill fs-5"></i>
                                            @elseif($diagram->type === 'network_diagram')
                                                <i class="bi bi-hdd-network-fill fs-5"></i>
                                            @else
                                                <i class="bi bi-palette-fill fs-5"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <a href="{{ route('clients.diagrams.show', [$client->id, $diagram->id]) }}" class="text-decoration-none">
                                                <strong class="text-white fs-15px">{{ $diagram->name }}</strong>
                                            </a>
                                            <div class="mt-1">
                                                <span class="badge bg-secondary bg-opacity-20 text-light border border-secondary border-opacity-30 small">
                                                    {{ str_replace('_', ' ', strtoupper($diagram->type)) }}
                                                </span>
                                                <span class="small text-muted ms-2">
                                                    by {{ $diagram->creator?->name ?? 'System' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($diagram->scenario)
                                        <span class="badge bg-info bg-opacity-15 text-info border border-info border-opacity-30">
                                            {{ $diagram->scenario->name }}
                                        </span>
                                    @else
                                        <span class="text-muted small">None (Global / General)</span>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    <div class="mb-1">{{ $diagram->updated_at->diffForHumans() }}</div>
                                    <div class="mono-cell" style="font-size: 11px;">{{ $diagram->updated_at->format('Y-m-d H:i') }}</div>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('clients.diagrams.show', [$client->id, $diagram->id]) }}" class="btn btn-outline-theme btn-sm" title="Edit in Draw.io">
                                            <i class="bi bi-pencil-square"></i> Open
                                        </a>
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="event.preventDefault(); if (confirm('Delete this diagram permanently?')) { document.getElementById('delete-diagram-{{ $diagram->id }}').submit(); }">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <form id="delete-diagram-{{ $diagram->id }}" action="{{ route('clients.diagrams.destroy', [$client->id, $diagram->id]) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
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
 
<!-- Modal: New Diagram -->
<div class="modal fade" id="newDiagramModal" tabindex="-1" aria-labelledby="newDiagramModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border border-secondary border-opacity-30 bg-dark">
            <div class="modal-header border-bottom border-secondary border-opacity-20">
                <h5 class="modal-title text-theme" id="newDiagramModalLabel">
                    <i class="bi bi-plus-circle me-2"></i> Create Draw.io Diagram
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('clients.diagrams.store', $client->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Diagram Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. AWS Deployment Architecture" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Diagram Type</label>
                        <select name="type" class="form-select">
                            <option value="soc_architecture">SOC Architecture</option>
                            <option value="deployment_topology">Deployment Topology</option>
                            <option value="network_diagram">Network Diagram</option>
                            <option value="custom" selected>Custom Diagram</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Associate with Scenario (Optional)</label>
                        <select name="scenario_id" class="form-select">
                            <option value="">None (General Diagram)</option>
                            @foreach($scenarios as $s)
                                <option value="{{ $s->id }}">{{ $s->name }} (Mode: {{ $s->workload_profile }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-20">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-outline-theme btn-sm">Create & Open</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
