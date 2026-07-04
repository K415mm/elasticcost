@extends('layouts.app')

@section('title', 'User Management — ElasticCost')

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
    .file-manager { display: flex; flex-direction: column; height: 100%; }
    .file-manager-toolbar {
        display: flex; flex-wrap: wrap; gap: 4px;
        padding: 10px 12px; border-bottom: 1px solid var(--bs-border-color);
    }
    .file-manager-toolbar .btn { font-size: 0.8rem; }
    .file-manager-container { display: flex; flex: 1; overflow: hidden; }
    .file-manager-sidebar {
        width: 260px; border-right: 1px solid var(--bs-border-color);
        display: flex; flex-direction: column;
    }
    .file-manager-sidebar-content { flex: 1; overflow: auto; }
    .file-manager-sidebar-footer { border-top: 1px solid var(--bs-border-color); padding: 12px; }
    .file-manager-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
    .role-badge { font-size: 0.7rem; padding: 3px 8px; border-radius: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .user-avatar { width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; }
    .permission-pill { font-size: 0.65rem; padding: 2px 6px; border-radius: 10px; margin: 1px; display: inline-block; }
    @media (max-width: 768px) {
        .file-manager-sidebar { display: none; }
        .file-manager-sidebar.mobile-show { display: flex; position: absolute; z-index: 1000; background: var(--bs-body-bg); height: 100%; }
    }
</style>
@endsection

@section('content')
<!-- BEGIN page-header -->
<h1 class="page-header">User Management <small>Manage users, roles & permissions</small></h1>
<!-- END page-header -->

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card flex-1 m-0 d-flex flex-column overflow-hidden rounded-0">
    <div class="card-header fw-bold small d-flex">
        <span class="flex-grow-1">User Manager</span>
        <a href="#" data-toggle="card-expand" class="text-white text-opacity-50 text-decoration-none"><i class="fa fa-fw fa-expand"></i> EXPAND</a>
    </div>
    <div class="card-body p-0 flex-1 overflow-hidden">
        <div class="file-manager h-100" id="userManager">
            <!-- Toolbar -->
            <div class="file-manager-toolbar">
                <button type="button" class="btn border-0" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-plus-lg me-1"></i> New User
                </button>
                <button type="button" class="btn border-0" data-bs-toggle="modal" data-bs-target="#editUserModal" id="editUserBtn" disabled>
                    <i class="bi me-1 bi-pencil-square"></i> Edit
                </button>
                <button type="button" class="btn border-0" id="deleteUserBtn" disabled>
                    <i class="bi me-1 bi-trash"></i> Delete
                </button>
                <button type="button" class="btn border-0" disabled>
                    <i class="bi me-1 bi-key"></i> Reset Password
                </button>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <form method="GET" action="{{ route('users.index') }}" class="d-flex gap-2">
                        <input type="text" class="form-control form-control-sm" name="search" placeholder="Search users..." value="{{ request('search') }}" style="width: 180px;">
                        <select class="form-select form-select-sm" name="role" style="width: 140px;">
                            <option value="">All Roles</option>
                            @foreach($roles as $r)
                                <option value="{{ $r }}" @if(request('role') === $r) selected @endif>{{ ucfirst(str_replace('_', ' ', $r)) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-default"><i class="bi bi-search"></i></button>
                    </form>
                </div>
            </div>

            <div class="file-manager-container">
                <!-- Sidebar: Role tree -->
                <div class="file-manager-sidebar">
                    <div class="file-manager-sidebar-content">
                        <div data-scrollbar="true" data-height="100%" class="p-3">
                            <div class="small fw-bold text-muted mb-2">FILTER BY ROLE</div>
                            <div class="file-tree mb-3">
                                <div class="file-node {{ !request('role') ? 'selected' : '' }}">
                                    <a href="{{ route('users.index') }}" class="file-link">
                                        <span class="file-info">
                                            <span class="file-icon"><i class="bi bi-people-fill fa-lg text-theme"></i></span>
                                            <span class="file-text">All Users</span>
                                        </span>
                                        <span class="badge bg-theme text-theme-color ms-auto">{{ \App\Models\User::count() }}</span>
                                    </a>
                                </div>
                                @foreach($roles as $r)
                                <div class="file-node {{ request('role') === $r ? 'selected' : '' }}">
                                    <a href="{{ route('users.index', ['role' => $r]) }}" class="file-link">
                                        <span class="file-info">
                                            <span class="file-icon"><i class="bi bi-person-badge fa-lg text-{{ $roleColors[$r] }}"></i></span>
                                            <span class="file-text">{{ ucfirst(str_replace('_', ' ', $r)) }}</span>
                                        </span>
                                        <span class="badge bg-{{ $roleColors[$r] }} ms-auto">{{ \App\Models\User::where('role', $r)->count() }}</span>
                                    </a>
                                </div>
                                @endforeach
                            </div>

                            <div class="small fw-bold text-muted mb-2 mt-4">PERMISSIONS</div>
                            <div class="file-tree">
                                <div class="file-node">
                                    <a href="{{ route('roles.permissions') }}" class="file-link">
                                        <span class="file-info">
                                            <span class="file-icon"><i class="bi bi-shield-check fa-lg text-warning"></i></span>
                                            <span class="file-text">Permission Matrix</span>
                                        </span>
                                    </a>
                                </div>
                                <div class="file-node">
                                    <a href="{{ route('tokens.index') }}" class="file-link">
                                        <span class="file-info">
                                            <span class="file-icon"><i class="bi bi-key-fill fa-lg text-info"></i></span>
                                            <span class="file-text">Token Management</span>
                                        </span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="file-manager-sidebar-footer">
                        <div class="d-flex align-items-center">
                            <div class="mx-n1">
                                <i class="bi bi-shield-lock-fill fa-2x text-theme"></i>
                            </div>
                            <div class="flex-1 ps-3 small">
                                <div class="fw-bold small">RBAC System</div>
                                <div class="progress h-5px my-1">
                                    <div class="progress-bar progress-bar-striped bg-theme" style="width: {{ min(100, (\App\Models\User::count() / 50) * 100) }}%"></div>
                                </div>
                                <div class="fw-bold text-body text-opacity-50 small">
                                    <b class="text-body">{{ \App\Models\User::count() }}</b> users registered
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main content: User table -->
                <div class="file-manager-content d-flex flex-column">
                    <div class="mb-0 d-flex flex-wrap text-nowrap px-10px pt-10px pb-0 border-bottom">
                        <button type="button" class="btn btn-sm btn-default me-2 mb-10px px-2"><i class="fa fa-fw fa-home"></i></button>
                        <div class="btn-group me-2 mb-10px">
                            <button type="button" class="btn btn-sm btn-default" disabled><i class="fa me-1 fa-arrow-left"></i> Back</button>
                            <button type="button" class="btn btn-sm btn-default" disabled><i class="fa me-1 fa-arrow-right"></i> Forward</button>
                        </div>
                        <button type="button" class="btn btn-sm btn-default me-2 mb-10px px-2"><i class="fa fa-fw fa-arrows-rotate"></i></button>
                        <div class="ms-auto mb-10px small text-muted">
                            Showing {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} of {{ $users->total() }} users
                        </div>
                    </div>
                    <div class="flex-1 overflow-hidden">
                        <div data-scrollbar="true" data-skip-mobile="true" data-height="100%" class="p-0">
                            <table class="table table-striped table-borderless table-sm m-0 text-nowrap small">
                                <thead>
                                    <tr class="border-bottom">
                                        <th class="w-10px ps-10px"></th>
                                        <th class="px-10px">User</th>
                                        <th class="px-10px w-120px">Role</th>
                                        <th class="px-10px w-180px">Permissions</th>
                                        <th class="px-10px w-100px">Tokens</th>
                                        <th class="px-10px w-180px">Created</th>
                                        <th class="px-10px w-120px">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($users as $user)
                                    <tr class="user-row" data-user-id="{{ $user->id }}" data-user-name="{{ $user->name }}" data-user-email="{{ $user->email }}" data-user-role="{{ $user->role }}">
                                        <td class="ps-10px border-0 text-center">
                                            <input type="radio" name="selected_user" value="{{ $user->id }}" class="form-check-input user-select-radio">
                                        </td>
                                        <td class="px-10px border-0">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="user-avatar bg-{{ $roleColors[$user->role] ?? 'secondary' }} text-white">
                                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                                </div>
                                                <div>
                                                    <div class="fw-semibold">{{ $user->name }}</div>
                                                    <div class="small text-muted">{{ $user->email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-10px border-0">
                                            <span class="role-badge bg-{{ $roleColors[$user->role] ?? 'secondary' }} text-white">
                                                {{ ucfirst(str_replace('_', ' ', $user->role)) }}
                                            </span>
                                        </td>
                                        <td class="px-10px border-0">
                                            @php
                                                $allowedKeys = \App\Models\RolePermission::allowedKeysForRole($user->role);
                                                $totalPerms = \App\Models\Permission::count();
                                            @endphp
                                            <span class="text-muted">{{ $allowedKeys->count() }}/{{ $totalPerms }} modules</span>
                                            <div class="progress h-3px mt-1" style="width: 120px;">
                                                <div class="progress-bar bg-theme" style="width: {{ $totalPerms > 0 ? ($allowedKeys->count() / $totalPerms) * 100 : 0 }}%"></div>
                                            </div>
                                        </td>
                                        <td class="px-10px border-0">
                                            @if($user->tokens()->where('revoked', false)->count() > 0)
                                                <span class="badge bg-success">{{ $user->tokens()->where('revoked', false)->count() }} active</span>
                                            @else
                                                <span class="badge bg-secondary">No tokens</span>
                                            @endif
                                        </td>
                                        <td class="px-10px border-0">{{ $user->created_at->format('M d, Y, h:iA') }}</td>
                                        <td class="px-10px border-0">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-sm btn-default edit-user-btn" data-bs-toggle="modal" data-bs-target="#editUserModal" data-user-id="{{ $user->id }}" data-user-name="{{ $user->name }}" data-user-email="{{ $user->email }}" data-user-role="{{ $user->role }}">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" action="{{ route('users.destroy', $user) }}" class="d-inline" onsubmit="return confirm('Delete user {{ $user->name }}? This will revoke all their tokens.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-default text-danger" @if($user->id === auth()->id()) disabled @endif>
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fa-3x d-block mb-2 opacity-50"></i>
                                            <div class="fw-bold">No users found</div>
                                            <div class="small">Try adjusting your search or create a new user.</div>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination -->
                    @if($users->hasPages())
                    <div class="px-10px py-10px border-top d-flex justify-content-between align-items-center">
                        <div class="small text-muted">{{ $users->links('pagination::bootstrap-5')->links() }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="card-arrow">
        <div class="card-arrow-top-left"></div>
        <div class="card-arrow-top-right"></div>
        <div class="card-arrow-bottom-left"></div>
        <div class="card-arrow-bottom-right"></div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Create New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('users.store') }}" @precognition>
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Name</label>
                        <input type="text" class="form-control" name="name" required placeholder="Full name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" class="form-control" name="email" required placeholder="user@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Role</label>
                        <select class="form-select" name="role" required>
                            @foreach($roles as $r)
                                <option value="{{ $r }}">{{ ucfirst(str_replace('_', ' ', $r)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Password</label>
                        <input type="password" class="form-control" name="password" required placeholder="Minimum 8 characters">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Confirm Password</label>
                        <input type="password" class="form-control" name="password_confirmation" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-theme"><i class="bi bi-check-lg me-1"></i>Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="editUserForm" @precognition>
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Name</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Role</label>
                        <select class="form-select" name="role" id="edit_role" required>
                            @foreach($roles as $r)
                                <option value="{{ $r }}">{{ ucfirst(str_replace('_', ' ', $r)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Password <span class="text-muted small">(leave blank to keep current)</span></label>
                        <input type="password" class="form-control" name="password" placeholder="Only if changing">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Confirm Password</label>
                        <input type="password" class="form-control" name="password_confirmation" placeholder="Repeat new password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-theme"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Edit user modal — populate fields from data attributes
    document.addEventListener('DOMContentLoaded', function() {
        const editModal = document.getElementById('editUserModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function(event) {
                const btn = event.relatedTarget;
                if (!btn) return;
                const userId = btn.dataset.userId;
                const userName = btn.dataset.userName;
                const userEmail = btn.dataset.userEmail;
                const userRole = btn.dataset.userRole;

                document.getElementById('edit_user_id').value = userId;
                document.getElementById('edit_name').value = userName;
                document.getElementById('edit_email').value = userEmail;
                document.getElementById('edit_role').value = userRole;

                document.getElementById('editUserForm').action = '{{ url("users") }}/' + userId;
            });
        }

        // Radio selection enables toolbar buttons
        const radios = document.querySelectorAll('.user-select-radio');
        const editBtn = document.getElementById('editUserBtn');
        const deleteBtn = document.getElementById('deleteUserBtn');
        radios.forEach(function(radio) {
            radio.addEventListener('change', function() {
                if (editBtn) editBtn.disabled = false;
                if (deleteBtn) deleteBtn.disabled = false;
            });
        });
    });
</script>
@endsection
