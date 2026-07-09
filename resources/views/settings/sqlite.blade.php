@extends('layouts.app')

@section('title', 'SQLite Databases Monitor')

@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">Clients</a></li>
    <li class="breadcrumb-item"><a href="{{ route('settings.system') }}">Settings</a></li>
    <li class="breadcrumb-item active">SQLITE MONITOR</li>
</ul>

<div class="d-flex align-items-center mb-4">
    <div class="flex-grow-1">
        <h1 class="page-header mb-0">
            SQLite Databases Monitor
            <small class="d-block mt-1">Scan, browse, and run SQL queries against active phpkaiharness SQLite databases</small>
        </h1>
    </div>
</div>

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <strong>Error!</strong> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <h5 class="card-title text-theme mb-3">
            <i class="bi bi-database me-2"></i> Active SQLite Files
        </h5>
        
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="border-secondary border-opacity-30">Database Name</th>
                        <th class="border-secondary border-opacity-30">Size</th>
                        <th class="border-secondary border-opacity-30">Last Modified</th>
                        <th class="border-secondary border-opacity-30 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($databases as $db)
                        <tr>
                            <td class="border-secondary border-opacity-20 fw-bold">
                                <i class="bi bi-file-earmark-code text-muted me-2"></i>{{ $db['name'] }}
                            </td>
                            <td class="border-secondary border-opacity-20 mono-cell">{{ $db['size'] }}</td>
                            <td class="border-secondary border-opacity-20 text-muted">{{ $db['modified'] }}</td>
                            <td class="border-secondary border-opacity-20 text-end">
                                <a href="{{ route('sqlite.explore', ['db' => $db['name']]) }}" class="btn btn-sm btn-outline-theme">
                                    <i class="bi bi-search me-1"></i> Explore & Query
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted border-secondary border-opacity-20">
                                <i class="bi bi-exclamation-triangle d-block mb-2 fs-3 text-warning"></i>
                                No active SQLite databases found in the storage folder.
                            </td>
                        </tr>
                    @endforelse
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
