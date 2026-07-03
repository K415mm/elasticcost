@extends('layouts.auth')

@section('title', 'Sign In — ElasticCost')

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <img src="/assets/css/images/logo-dark.png" alt="ElasticCost" class="auth-logo-dark mb-3">
            <img src="/assets/css/images/logo.png" alt="ElasticCost" class="auth-logo-light mb-3">
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-subtitle">Sign in to your ElasticCost account</p>
        </div>

        @if (session('status'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="email">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                    <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" value="{{ old('email') }}" required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-lock"></i></span>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                <a href="#" class="text-theme text-decoration-none small">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn-theme w-100 btn-lg">
                <i class="fa fa-sign-in-alt me-2"></i> Sign In
            </button>
        </form>

        <div class="auth-footer">
            <p class="text-muted mb-0">Don't have an account? <a href="{{ route('register') }}" class="text-theme text-decoration-none">Create one</a></p>
        </div>
    </div>
</div>
@endsection
