@extends('layouts.app')

@section('title', 'Permission Matrix — ElasticCost')

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
    .perm-toggle { width: 42px; height: 22px; }
    .perm-toggle .form-check-input { width: 42px; height: 22px; cursor: pointer; }
    .perm-category-header { background: rgba(var(--bs-theme-rgb), 0.08); }
    .role-header { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; }
    .module-icon { width: 28px; text-align: center; }
</style>
@endsection

@section('content')
<h1 class="page-header">Permission Matrix <small>Control page & feature access per role</small></h1>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card">
    <div class="card-header fw-bold small d-flex">
        <span class="flex-grow-1"><i class="bi bi-shield-check me-1"></i> Role × Module Permission Matrix</span>
        <span class="text-muted small">Toggle checkboxes to grant or revoke access</span>
    </div>
    <div class="card-body p-0">
        <form method="POST" action="{{ route('roles.permissions.update') }}">
            @csrf
            @method('PUT')
            <div class="table-responsive">
                <table class="table table-borderless table-sm m-0 align-middle">
                    <thead>
                        <tr class="border-bottom">
                            <th class="ps-3" style="min-width: 260px;">Module</th>
                            @foreach($roles as $role)
                                <th class="text-center role-header">
                                    <span class="badge bg-{{ $roleColors[$role] }} text-white">{{ ucfirst(str_replace('_', ' ', $role)) }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $currentCategory = '';
                        @endphp
                        @foreach($permissions as $permission)
                            @if($currentCategory !== $permission->category)
                                @php $currentCategory = $permission->category; @endphp
                                <tr class="perm-category-header">
                                    <td colspan="{{ count($roles) + 1 }}" class="ps-3 py-2 fw-bold small text-theme">
                                        <i class="bi bi-folder-fill me-1"></i> {{ $currentCategory }}
                                    </td>
                                </tr>
                            @endif
                            <tr class="border-bottom">
                                <td class="ps-3 py-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="module-icon"><i class="bi {{ $permission->icon ?? 'bi-app' }} fa-lg text-theme"></i></span>
                                        <div>
                                            <div class="fw-semibold small">{{ $permission->label }}</div>
                                            <div class="text-muted" style="font-size: 0.7rem;">{{ $permission->description }}</div>
                                        </div>
                                    </div>
                                </td>
                                @foreach($roles as $role)
                                    <td class="text-center py-2">
                                        <div class="form-check form-switch d-inline-block perm-toggle">
                                            <input class="form-check-input" type="checkbox"
                                                name="permissions[{{ $permission->key }}][{{ $role }}]"
                                                @if($matrix[$permission->key][$role] ?? false) checked @endif
                                                id="perm_{{ $permission->key }}_{{ $role }}">
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <div class="small text-muted">
                    <i class="bi bi-info-circle me-1"></i> Changes take effect immediately for new sessions. Existing users may need to re-login.
                </div>
                <button type="submit" class="btn btn-theme">
                    <i class="bi bi-check-lg me-1"></i> Save Permission Matrix
                </button>
            </div>
        </form>
    </div>
    <div class="card-arrow">
        <div class="card-arrow-top-left"></div>
        <div class="card-arrow-top-right"></div>
        <div class="card-arrow-bottom-left"></div>
        <div class="card-arrow-bottom-right"></div>
    </div>
</div>

<div class="mt-3">
    <a href="{{ route('users.index') }}" class="btn btn-default"><i class="bi bi-arrow-left me-1"></i> Back to User Management</a>
</div>
@endsection
