@extends('layouts.app')

@section('title', 'Token Management — ElasticCost')

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
    .token-scope { font-size: 0.65rem; padding: 2px 6px; border-radius: 10px; margin: 1px; display: inline-block; background: rgba(var(--bs-theme-rgb), 0.15); color: var(--bs-theme); }
</style>
@endsection

@section('content')
<h1 class="page-header">Token Management <small>Passport OAuth2 token administration</small></h1>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card">
    <div class="card-header fw-bold small d-flex">
        <span class="flex-grow-1"><i class="bi bi-key-fill me-1"></i> Active OAuth Tokens</span>
        <form method="GET" action="{{ route('tokens.index') }}" class="d-flex gap-2">
            <select class="form-select form-select-sm" name="user_id" style="width: 200px;">
                <option value="">All Users</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @if(request('user_id') == $u->id) selected @endif>{{ $u->name }} ({{ $u->email }})</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-default"><i class="bi bi-funnel"></i></button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-borderless table-sm m-0 text-nowrap small">
                <thead>
                    <tr class="border-bottom">
                        <th class="ps-3" style="min-width: 200px;">User</th>
                        <th class="px-10px">Client</th>
                        <th class="px-10px">Scopes</th>
                        <th class="px-10px w-180px">Created</th>
                        <th class="px-10px w-180px">Expires</th>
                        <th class="px-10px w-120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tokens as $token)
                        <tr>
                            <td class="ps-3 border-0">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar bg-secondary text-white" style="width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:0.7rem;">
                                        {{ strtoupper(substr($token->user?->name ?? '?', 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold">{{ $token->user?->name ?? 'Unknown' }}</div>
                                        <div class="text-muted" style="font-size: 0.7rem;">{{ $token->user?->email ?? 'N/A' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-10px border-0">
                                <span class="font-monospace small">{{ $token->client?->name ?? 'N/A' }}</span>
                            </td>
                            <td class="px-10px border-0">
                                @if(!empty($token->scopes))
                                    @foreach($token->scopes as $scope)
                                        <span class="token-scope">{{ $scope }}</span>
                                    @endforeach
                                @else
                                    <span class="text-muted small">No scopes</span>
                                @endif
                            </td>
                            <td class="px-10px border-0">{{ $token->created_at?->format('M d, Y, h:iA') ?? 'N/A' }}</td>
                            <td class="px-10px border-0">
                                @if($token->expires_at && $token->expires_at->isPast())
                                    <span class="badge bg-danger">Expired</span>
                                @elseif($token->expires_at)
                                    {{ $token->expires_at->format('M d, Y, h:iA') }}
                                @else
                                    <span class="text-muted">Never</span>
                                @endif
                            </td>
                            <td class="px-10px border-0">
                                <form method="POST" action="{{ route('tokens.destroy', $token->id) }}" class="d-inline" onsubmit="return confirm('Revoke this token? The user will need to re-authenticate.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-default text-danger">
                                        <i class="bi bi-x-circle me-1"></i> Revoke
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-key fa-3x d-block mb-2 opacity-50"></i>
                                <div class="fw-bold">No active tokens</div>
                                <div class="small">All OAuth tokens have been revoked or expired.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($tokens->hasPages())
    <div class="card-footer">
        {{ $tokens->links('pagination::bootstrap-5')->links() }}
    </div>
    @endif
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
