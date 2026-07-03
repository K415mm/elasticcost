@extends('layouts.auth')

@section('title', 'Create Account — ElasticCost')

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <img src="/assets/css/images/logo-dark.png" alt="ElasticCost" class="auth-logo-dark mb-3">
            <img src="/assets/css/images/logo.png" alt="ElasticCost" class="auth-logo-light mb-3">
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-subtitle">Join the ElasticCost platform</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('register.post') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="name">Full Name</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-user"></i></span>
                    <input type="text" id="name" name="name" class="form-control" placeholder="John Doe" value="{{ old('name') }}" required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="email">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                    <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" value="{{ old('email') }}" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-lock"></i></span>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Min. 8 characters" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="password_confirmation">Confirm Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-lock"></i></span>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="Re-enter password" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label" for="role">Account Type</label>
                <select id="role" name="role" class="form-select">
                    <option value="client" {{ old('role') === 'client' ? 'selected' : '' }}>Client — View reports & sizing data</option>
                    <option value="manager" {{ old('role') === 'manager' ? 'selected' : '' }}>Manager — Manage clients & scenarios</option>
                    <option value="sales_manager" {{ old('role') === 'sales_manager' ? 'selected' : '' }}>Sales Manager — Sales pipeline & partners</option>
                    <option value="partner" {{ old('role') === 'partner' ? 'selected' : '' }}>Partner — Limited external access</option>
                    <option value="ceo" {{ old('role') === 'ceo' ? 'selected' : '' }}>CEO — Full access</option>
                </select>
            </div>

            <button type="submit" class="btn btn-theme w-100 btn-lg">
                <i class="fa fa-user-plus me-2"></i> Create Account
            </button>
        </form>

        <div class="auth-footer">
            <p class="text-muted mb-0">Already have an account? <a href="{{ route('login') }}" class="text-theme text-decoration-none">Sign in</a></p>
        </div>
    </div>
</div>
@endsection
