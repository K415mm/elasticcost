<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if(app()->getLocale() === 'ar') dir="rtl" @endif data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ElasticCost')</title>
    <link href="/assets/css/vendor.min.css" rel="stylesheet">
    <link href="/assets/css/app.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            padding: 1rem;
        }
        .auth-container {
            width: 100%;
            max-width: 440px;
        }
        .auth-card {
            background-color: rgba(30, 41, 59, 0.85);
            border: 1px solid rgba(148, 163, 184, 0.15);
            border-radius: 16px;
            padding: 2.5rem;
            backdrop-filter: blur(20px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        }
        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .auth-logo-dark {
            height: 40px;
            display: block;
            margin: 0 auto;
        }
        .auth-logo-light {
            height: 40px;
            display: none;
            margin: 0 auto;
        }
        [data-bs-theme="light"] .auth-logo-dark { display: none; }
        [data-bs-theme="light"] .auth-logo-light { display: block; }
        .auth-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .auth-subtitle {
            color: #94a3b8;
            font-size: 0.875rem;
            margin-bottom: 0;
        }
        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(148, 163, 184, 0.1);
        }
        .form-label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: #cbd5e1;
        }
        .input-group-text {
            background-color: rgba(15, 23, 42, 0.6);
            border-color: rgba(148, 163, 184, 0.2);
            color: #64748b;
        }
        .form-control, .form-select {
            background-color: rgba(15, 23, 42, 0.6);
            border-color: rgba(148, 163, 184, 0.2);
            color: #e2e8f0;
        }
        .form-control:focus, .form-select:focus {
            background-color: rgba(15, 23, 42, 0.8);
            border-color: var(--bs-theme);
            box-shadow: 0 0 0 0.2rem rgba(var(--bs-theme-rgb), 0.15);
            color: #e2e8f0;
        }
        .form-control::placeholder { color: #64748b; }
        .btn-theme {
            font-weight: 600;
            border-radius: 10px;
        }
        [data-bs-theme="light"] body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #f8fafc 100%);
        }
        [data-bs-theme="light"] .auth-card {
            background-color: rgba(255, 255, 255, 0.9);
            border-color: rgba(0, 0, 0, 0.1);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
        }
        [data-bs-theme="light"] .auth-title { color: #1e293b; }
        [data-bs-theme="light"] .auth-subtitle { color: #64748b; }
        [data-bs-theme="light"] .form-label { color: #475569; }
        [data-bs-theme="light"] .input-group-text {
            background-color: rgba(241, 245, 249, 0.8);
            border-color: rgba(0, 0, 0, 0.1);
            color: #94a3b8;
        }
        [data-bs-theme="light"] .form-control, [data-bs-theme="light"] .form-select {
            background-color: rgba(241, 245, 249, 0.8);
            border-color: rgba(0, 0, 0, 0.1);
            color: #1e293b;
        }
        [data-bs-theme="light"] .form-control::placeholder { color: #94a3b8; }
    </style>
</head>
<body>
    @yield('content')
    <script src="/assets/js/vendor.min.js"></script>
    <script src="/assets/js/app.min.js"></script>
</body>
</html>
