@extends('layouts.app')
 
@section('title', "Edit Diagram: {$diagram->name}")
 
@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">{{ __('messages.clients') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('clients.show', $client->id) }}">{{ strtoupper($client->name) }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('clients.diagrams.index', $client->id) }}">DIAGRAMS</a></li>
    <li class="breadcrumb-item active">{{ strtoupper($diagram->name) }}</li>
</ul>
 
<div class="d-flex align-items-center mb-4">
    <div class="flex-grow-1">
        <h1 class="page-header mb-0">
            {{ $diagram->name }} 
            <small class="d-inline-block ms-2 badge bg-secondary bg-opacity-20 text-white border border-secondary border-opacity-30 fs-12px">
                {{ str_replace('_', ' ', strtoupper($diagram->type)) }}
            </small>
        </h1>
        <div class="small text-muted mt-1">
            Created by {{ $diagram->creator?->name ?? 'System' }} &bull; Last updated {{ $diagram->updated_at->diffForHumans() }}
        </div>
    </div>
    <div>
        <a href="{{ route('clients.diagrams.index', $client->id) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Diagrams
        </a>
    </div>
</div>
 
<!-- Embed draw.io iframe editor -->
<div class="card border border-secondary border-opacity-30">
    <div class="card-body p-0">
        @include('partials.diagram-embed', [
            'xml' => $diagram->content,
            'saveUrl' => route('clients.diagrams.update', [$client->id, $diagram->id]),
            'exitUrl' => route('clients.diagrams.index', $client->id)
        ])
    </div>
    <!-- card-arrow -->
    <div class="card-arrow">
        <div class="card-arrow-top-left"></div>
        <div class="card-arrow-top-right"></div>
        <div class="card-arrow-bottom-left"></div>
        <div class="card-arrow-bottom-right"></div>
    </div>
</div>
@endsection
