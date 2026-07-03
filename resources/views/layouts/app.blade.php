<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if(app()->getLocale() === 'ar') dir="rtl" @endif data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Elasticsearch On-Premise Sizing & Cost Calculator')</title>
    <meta name="description" content="Professional Elastic Resource Unit (ERU) licensing cost calculator and node deployment recommendation engine for enterprise on-premise setups.">
    
    <!-- ================== BEGIN core-css ================== -->
    <link href="/assets/css/vendor.min.css" rel="stylesheet">
    <link href="/assets/css/app.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <!-- ================== END core-css ================== -->

    <style>
        :root {
            --tier-hot: #ef4444;
            --tier-warm: #f97316;
            --tier-cold: #3b82f6;
            --tier-frozen: #06b6d4;
        }
        .badge-hot { background-color: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-warm { background-color: rgba(249, 115, 22, 0.15); color: #fdba74; border: 1px solid rgba(249, 115, 22, 0.3); }
        .badge-cold { background-color: rgba(59, 130, 246, 0.15); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.3); }
        .badge-frozen { background-color: rgba(6, 182, 212, 0.15); color: #67e8f9; border: 1px solid rgba(6, 182, 212, 0.3); }
        .mono-cell { font-family: 'JetBrains Mono', Courier, monospace; }

        /* Cover rendering enhancements (Prevents blurry upscaling & sets crisp rendering) */
        html::after {
            background-size: cover !important;
            background-position: center !important;
            background-repeat: no-repeat !important;
            background-attachment: fixed !important;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }

        /* Brand logo scaling */
        .brand-logo-img-dark,
        .brand-logo-img-light {
            height: 28px;
            width: auto;
            max-height: 100%;
            object-fit: contain;
        }
        .brand-logo-img-dark {
            display: block;
        }
        .brand-logo-img-light {
            display: none;
        }
        [data-bs-theme="light"] .brand-logo-img-dark {
            display: none;
        }
        [data-bs-theme="light"] .brand-logo-img-light {
            display: block;
        }

        /* Light Theme UX & Accessibility Color Enhancements */
        [data-bs-theme="light"] {
            --bs-body-color: #1f2937;
        }
        [data-bs-theme="light"] .badge-hot {
            background-color: rgba(239, 68, 68, 0.1) !important;
            color: #b91c1c !important;
            border-color: rgba(239, 68, 68, 0.25) !important;
        }
        [data-bs-theme="light"] .badge-warm {
            background-color: rgba(249, 115, 22, 0.1) !important;
            color: #c2410c !important;
            border-color: rgba(249, 115, 22, 0.25) !important;
        }
        [data-bs-theme="light"] .badge-cold {
            background-color: rgba(59, 130, 246, 0.1) !important;
            color: #1d4ed8 !important;
            border-color: rgba(59, 130, 246, 0.25) !important;
        }
        [data-bs-theme="light"] .badge-frozen {
            background-color: rgba(6, 182, 212, 0.1) !important;
            color: #0e7490 !important;
            border-color: rgba(6, 182, 212, 0.25) !important;
        }
        [data-bs-theme="light"] th {
            color: #4b5563 !important;
            font-weight: 700 !important;
            border-bottom-color: rgba(0, 0, 0, 0.1) !important;
        }
        [data-bs-theme="light"] td {
            color: #1f2937 !important;
            border-bottom-color: rgba(0, 0, 0, 0.08) !important;
        }
        [data-bs-theme="light"] .text-muted {
            color: #4b5563 !important;
        }
        [data-bs-theme="light"] .page-header {
            border-bottom-color: rgba(0, 0, 0, 0.1) !important;
        }
        [data-bs-theme="light"] .page-header small {
            color: #4b5563 !important;
        }
        [data-bs-theme="light"] .card {
            background-color: rgba(255, 255, 255, 0.85) !important;
            border-color: rgba(0, 0, 0, 0.12) !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04) !important;
            backdrop-filter: blur(10px);
        }
        [data-bs-theme="light"] .card-arrow-top-left,
        [data-bs-theme="light"] .card-arrow-top-right,
        [data-bs-theme="light"] .card-arrow-bottom-left,
        [data-bs-theme="light"] .card-arrow-bottom-right {
            border-color: rgba(0, 0, 0, 0.22) !important;
        }
        [data-bs-theme="light"] .app-sidebar .menu .menu-header {
            color: #4b5563 !important;
            font-weight: 700;
        }
        [data-bs-theme="light"] .app-sidebar .menu .menu-item .menu-link {
            color: #374151 !important;
        }
        [data-bs-theme="light"] .app-sidebar .menu .menu-item.active > .menu-link {
            color: var(--bs-theme) !important;
            background-color: rgba(var(--bs-theme-rgb), 0.1) !important;
        }
    </style>
    @yield('styles')
</head>
<body>
    <!-- BEGIN #app -->
    <div id="app" class="app">
        <!-- BEGIN #header -->
        <div id="header" class="app-header">
            <!-- BEGIN desktop-toggler -->
            <div class="desktop-toggler">
                <button type="button" class="menu-toggler" data-toggle-class="app-sidebar-collapsed" data-dismiss-class="app-sidebar-toggled" data-toggle-target=".app">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </button>
            </div>
            <!-- BEGIN desktop-toggler -->
            
            <!-- BEGIN mobile-toggler -->
            <div class="mobile-toggler">
                <button type="button" class="menu-toggler" data-toggle-class="app-sidebar-mobile-toggled" data-toggle-target=".app">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </button>
            </div>
            <!-- END mobile-toggler -->
            
            <!-- BEGIN brand -->
            <div class="brand">
                <a href="{{ route('clients.index') }}" class="brand-logo text-decoration-none d-flex align-items-center">
                    <img src="/assets/css/images/logo-dark.png" alt="ElasticCost Logo" class="brand-logo-img-dark me-2">
                    <img src="/assets/css/images/logo.png" alt="ElasticCost Logo" class="brand-logo-img-light me-2">
                    <span class="brand-text">ElasticCost</span>
                </a>
            </div>
            <!-- END brand -->
            
            <!-- BEGIN menu -->
            <div class="menu">
                <!-- Currency Selector Dropdown -->
                <div class="menu-item dropdown">
                    <a href="#" class="menu-link" data-bs-toggle="dropdown">
                        <div class="menu-text d-flex align-items-center gap-1">
                            <i class="fa fa-money-bill-wave text-theme me-1"></i>
                            <span class="fw-bold">{{ session('currency', 'USD') }}</span>
                            <i class="fa fa-chevron-down small opacity-50 ms-1"></i>
                        </div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a href="?currency=USD" class="dropdown-item d-flex align-items-center justify-content-between {{ session('currency', 'USD') === 'USD' ? 'active' : '' }}">
                            <span>USD ($)</span>
                            @if(session('currency', 'USD') === 'USD') <i class="fa fa-check text-theme"></i> @endif
                        </a>
                        <a href="?currency=EUR" class="dropdown-item d-flex align-items-center justify-content-between {{ session('currency') === 'EUR' ? 'active' : '' }}">
                            <span>EUR (€)</span>
                            @if(session('currency') === 'EUR') <i class="fa fa-check text-theme"></i> @endif
                        </a>
                        <a href="?currency=TND" class="dropdown-item d-flex align-items-center justify-content-between {{ session('currency') === 'TND' ? 'active' : '' }}">
                            <span>TND (د.ت)</span>
                            @if(session('currency') === 'TND') <i class="fa fa-check text-theme"></i> @endif
                        </a>
                    </div>
                </div>

                <!-- Language Selector Dropdown -->
                <div class="menu-item dropdown">
                    <a href="#" class="menu-link" data-bs-toggle="dropdown">
                        <div class="menu-text d-flex align-items-center gap-1">
                            <i class="fa fa-globe text-theme me-1"></i>
                            <span class="fw-bold">{{ strtoupper(app()->getLocale()) }}</span>
                            <i class="fa fa-chevron-down small opacity-50 ms-1"></i>
                        </div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a href="?locale=en" class="dropdown-item d-flex align-items-center justify-content-between {{ app()->getLocale() === 'en' ? 'active' : '' }}">
                            <span>English</span>
                            @if(app()->getLocale() === 'en') <i class="fa fa-check text-theme"></i> @endif
                        </a>
                        <a href="?locale=fr" class="dropdown-item d-flex align-items-center justify-content-between {{ app()->getLocale() === 'fr' ? 'active' : '' }}">
                            <span>Français</span>
                            @if(app()->getLocale() === 'fr') <i class="fa fa-check text-theme"></i> @endif
                        </a>
                        <a href="?locale=ar" class="dropdown-item d-flex align-items-center justify-content-between {{ app()->getLocale() === 'ar' ? 'active' : '' }}">
                            <span>العربية (RTL)</span>
                            @if(app()->getLocale() === 'ar') <i class="fa fa-check text-theme"></i> @endif
                        </a>
                    </div>
                </div>

                <div class="menu-item dropdown dropdown-mobile-full">
                    <a href="#" class="menu-link disabled opacity-50">
                        <div class="menu-text d-sm-block d-none">{{ __('messages.enterprise_ingest_planner') }}</div>
                    </a>
                </div>

                @auth
                <!-- User Profile Dropdown -->
                <div class="menu-item dropdown">
                    <a href="#" class="menu-link" data-bs-toggle="dropdown">
                        <div class="menu-text d-flex align-items-center gap-2">
                            <span class="badge bg-theme text-theme-color fw-bold text-uppercase" style="font-size: 0.65rem;">{{ auth()->user()->role }}</span>
                            <span class="fw-bold d-none d-sm-inline">{{ auth()->user()->name }}</span>
                            <i class="fa fa-chevron-down small opacity-50"></i>
                        </div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <div class="dropdown-header py-2">
                            <div class="fw-bold">{{ auth()->user()->name }}</div>
                            <div class="small text-muted">{{ auth()->user()->email }}</div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item d-flex align-items-center text-danger">
                                <i class="fa fa-sign-out-alt me-2"></i> Logout
                            </button>
                        </form>
                    </div>
                </div>
                @endauth
            </div>
            <!-- END menu -->
        </div>
        <!-- END #header -->
        
        <!-- BEGIN #sidebar -->
        <div id="sidebar" class="app-sidebar">
            <!-- BEGIN scrollbar -->
            <div class="app-sidebar-content" data-scrollbar="true" data-height="100%">
                <!-- BEGIN menu -->
                <div class="menu">
                    <div class="menu-header">{{ __('messages.navigation') }}</div>
                    
                    @if(auth()->user()?->hasPermission('dashboard'))
                    <div class="menu-item {{ Request::is('dashboard') ? 'active' : '' }}">
                        <a href="{{ route('dashboard') }}" class="menu-link">
                            <span class="menu-icon"><i class="bi bi-house"></i></span>
                            <span class="menu-text">{{ __('messages.dashboard') }}</span>
                        </a>
                    </div>
                    @endif
                    
                    @if(auth()->user()?->hasPermission('clients'))
                    <div class="menu-item {{ Request::is('clients*') ? 'active' : '' }}">
                        <a href="{{ route('clients.index') }}" class="menu-link">
                            <span class="menu-icon"><i class="bi bi-people"></i></span>
                            <span class="menu-text">{{ __('messages.clients_sizing') }}</span>
                        </a>
                    </div>
                    @endif

                    @if(auth()->user()?->hasPermission('profit_simulator'))
                    <div class="menu-item {{ Request::is('simulator*') ? 'active' : '' }}">
                        <a href="{{ route('simulator.index') }}" class="menu-link">
                            <span class="menu-icon"><i class="bi bi-graph-up-arrow text-success"></i></span>
                            <span class="menu-text">Profit Simulator</span>
                        </a>
                    </div>
                    @endif

                    @if(auth()->user()?->hasPermission('ai_chat'))
                    <div class="menu-item {{ Request::is('ai-chat*') ? 'active' : '' }}">
                        <a href="{{ route('ai-chat.index') }}" class="menu-link">
                            <span class="menu-icon"><i class="bi bi-chat-dots"></i></span>
                            <span class="menu-text">{{ __('messages.ai_chat') }}</span>
                        </a>
                    </div>
                    @endif
                    
                    @if(auth()->user()?->hasPermission('asset_types'))
                    <div class="menu-item {{ Request::is('settings/asset-types*') ? 'active' : '' }}">
                        <a href="{{ route('asset-types.index') }}" class="menu-link">
                            <span class="menu-icon"><i class="bi bi-sliders"></i></span>
                            <span class="menu-text">{{ __('messages.ingest_benchmarks') }}</span>
                        </a>
                    </div>
                    @endif
                    
                    @if(auth()->user()?->hasPermission('scenarios'))
                    <div class="menu-item {{ Request::is('settings/scenarios*') ? 'active' : '' }}">
                        <a href="{{ route('scenarios.index') }}" class="menu-link">
                            <span class="menu-icon"><i class="bi bi-gear"></i></span>
                            <span class="menu-text">{{ __('messages.scenario_templates') }}</span>
                        </a>
                    </div>
                    @endif

                    @if(auth()->user()?->hasPermission('system_settings'))
                    <div class="menu-item {{ Request::is('settings/system*') ? 'active' : '' }}">
                        <a href="{{ route('settings.system') }}" class="menu-link">
                            <span class="menu-icon"><i class="bi bi-cpu"></i></span>
                            <span class="menu-text">{{ __('messages.system_settings') }}</span>
                        </a>
                    </div>
                    @endif

                    @if(auth()->user()?->hasPermission('ai_agents'))
                    <div class="menu-item {{ Request::is('settings/agents*') ? 'active' : '' }}">
                        <a href="{{ route('settings.agents') }}" class="menu-link">
                            <span class="menu-icon"><i class="bi bi-robot text-theme"></i></span>
                            <span class="menu-text">{{ __('messages.ai_agents') ?: 'AI Agents' }}</span>
                        </a>
                    </div>
                    @endif

                    @if(auth()->user()?->hasPermission('file_manager'))
                    <div class="menu-item {{ Request::is('settings/files*') ? 'active' : '' }}">
                        <a href="{{ route('settings.files') }}" class="menu-link">
                            <span class="menu-icon"><i class="bi bi-folder2-open"></i></span>
                            <span class="menu-text">File Manager (RAG)</span>
                        </a>
                    </div>
                    @endif

                    @if(auth()->user()?->hasPermission('harness_analytics'))
                    <div class="menu-item {{ Request::is('harness/dashboard*') ? 'active' : '' }}">
                        <a href="{{ route('harness.dashboard') }}" class="menu-link">
                            <span class="menu-icon"><i class="bi bi-activity"></i></span>
                            <span class="menu-text">Harness Analytics</span>
                        </a>
                    </div>
                    @endif

                    @if(auth()->user()?->hasPermission('test_compare'))
                    <div class="menu-item {{ Request::is('test-compare*') ? 'active' : '' }}">
                        <a href="{{ route('test-compare.index') }}" class="menu-link">
                            <span class="menu-icon"><i class="bi bi-bug"></i></span>
                            <span class="menu-text">Test Compare</span>
                        </a>
                    </div>
                    @endif

                    @if(auth()->user()?->hasPermission('user_management') || auth()->user()?->hasPermission('token_management'))
                    <div class="menu-divider"></div>
                    <div class="menu-header">Administration</div>
                    @endif

                    @if(auth()->user()?->hasPermission('user_management'))
                    <div class="menu-item {{ Request::is('users*') ? 'active' : '' }}">
                        <a href="{{ route('users.index') }}" class="menu-link">
                            <span class="menu-icon"><i class="bi bi-person-gear"></i></span>
                            <span class="menu-text">User Management</span>
                        </a>
                    </div>
                    <div class="menu-item {{ Request::is('roles*') ? 'active' : '' }}">
                        <a href="{{ route('roles.permissions') }}" class="menu-link">
                            <span class="menu-icon"><i class="bi bi-shield-check"></i></span>
                            <span class="menu-text">Permission Matrix</span>
                        </a>
                    </div>
                    @endif

                    @if(auth()->user()?->hasPermission('token_management'))
                    <div class="menu-item {{ Request::is('tokens*') ? 'active' : '' }}">
                        <a href="{{ route('tokens.index') }}" class="menu-link">
                            <span class="menu-icon"><i class="bi bi-key"></i></span>
                            <span class="menu-text">Token Management</span>
                        </a>
                    </div>
                    @endif
                </div>
                <!-- END menu -->
                
                <div class="p-3 px-4 mt-auto">
                    <div class="text-center text-muted small py-2">
                        v1.0.0 (PHP 8.5 & Laravel 13)
                    </div>
                </div>
            </div>
            <!-- END scrollbar -->
        </div>
        <!-- END #sidebar -->
            
        <!-- BEGIN mobile-sidebar-backdrop -->
        <button class="app-sidebar-mobile-backdrop" data-toggle-target=".app" data-toggle-class="app-sidebar-mobile-toggled"></button>
        <!-- END mobile-sidebar-backdrop -->
        
        <!-- BEGIN #content -->
        <div id="content" class="app-content">
            @yield('content')
        </div>
        <!-- END #content -->
        
        <!-- BEGIN btn-scroll-top -->
        <a href="#" data-toggle="scroll-to-top" class="btn-scroll-top fade"><i class="fa fa-arrow-up"></i></a>
        <!-- END btn-scroll-top -->
        
        <!-- BEGIN theme-panel -->
        <div class="app-theme-panel">
            <div class="app-theme-panel-container">
                <a href="javascript:;" data-toggle="theme-panel-expand" class="app-theme-toggle-btn"><i class="bi bi-sliders"></i></a>
                <div class="app-theme-panel-content">
                    <div class="small fw-bold text-inverse mb-1">Display Mode</div>
                    <div class="card mb-3">
                        <div class="card-body p-2">
                            <div class="row gx-2">
                                <div class="col-6">
                                    <a href="javascript:;" data-toggle="theme-mode-selector" data-theme-mode="dark" class="app-theme-mode-link active">
                                        <div class="img"><img src="/assets/img/mode/dark.jpg" class="object-fit-cover" height="76" width="76" alt="Dark Mode"></div>
                                        <div class="text">Dark</div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="javascript:;" data-toggle="theme-mode-selector" data-theme-mode="light" class="app-theme-mode-link">
                                        <div class="img"><img src="/assets/img/mode/light.jpg" class="object-fit-cover" height="76" width="76" alt="Light Mode"></div>
                                        <div class="text">Light</div>
                                    </a>
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
                    
                    <div class="small fw-bold text-inverse mb-1">Theme Color</div>
                    <div class="card mb-3">
                        <div class="card-body p-2">
                            <div class="app-theme-list">
                                <div class="app-theme-list-item"><a href="javascript:;" class="app-theme-list-link bg-pink" data-theme-class="theme-pink" data-toggle="theme-selector" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-container="body" data-bs-title="Pink">&nbsp;</a></div>
                                <div class="app-theme-list-item"><a href="javascript:;" class="app-theme-list-link bg-red" data-theme-class="theme-red" data-toggle="theme-selector" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-container="body" data-bs-title="Red">&nbsp;</a></div>
                                <div class="app-theme-list-item"><a href="javascript:;" class="app-theme-list-link bg-warning" data-theme-class="theme-warning" data-toggle="theme-selector" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-container="body" data-bs-title="Orange">&nbsp;</a></div>
                                <div class="app-theme-list-item"><a href="javascript:;" class="app-theme-list-link bg-yellow" data-theme-class="theme-yellow" data-toggle="theme-selector" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-container="body" data-bs-title="Yellow">&nbsp;</a></div>
                                <div class="app-theme-list-item"><a href="javascript:;" class="app-theme-list-link bg-lime" data-theme-class="theme-lime" data-toggle="theme-selector" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-container="body" data-bs-title="Lime">&nbsp;</a></div>
                                <div class="app-theme-list-item"><a href="javascript:;" class="app-theme-list-link bg-green" data-theme-class="theme-green" data-toggle="theme-selector" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-container="body" data-bs-title="Green">&nbsp;</a></div>
                                <div class="app-theme-list-item active"><a href="javascript:;" class="app-theme-list-link bg-teal" data-theme-class="" data-toggle="theme-selector" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-container="body" data-bs-title="Default">&nbsp;</a></div>
                                <div class="app-theme-list-item"><a href="javascript:;" class="app-theme-list-link bg-info" data-theme-class="theme-info" data-toggle="theme-selector" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-container="body" data-bs-title="Cyan">&nbsp;</a></div>
                                <div class="app-theme-list-item"><a href="javascript:;" class="app-theme-list-link bg-primary" data-theme-class="theme-primary" data-toggle="theme-selector" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-container="body" data-bs-title="Blue">&nbsp;</a></div>
                                <div class="app-theme-list-item"><a href="javascript:;" class="app-theme-list-link bg-purple" data-theme-class="theme-purple" data-toggle="theme-selector" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-container="body" data-bs-title="Purple">&nbsp;</a></div>
                                <div class="app-theme-list-item"><a href="javascript:;" class="app-theme-list-link bg-indigo" data-theme-class="theme-indigo" data-toggle="theme-selector" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-container="body" data-bs-title="Indigo">&nbsp;</a></div>
                                <div class="app-theme-list-item"><a href="javascript:;" class="app-theme-list-link bg-gray-100" data-theme-class="theme-gray-200" data-toggle="theme-selector" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-container="body" data-bs-title="Gray">&nbsp;</a></div>
                            </div>
                        </div>
                        <div class="card-arrow">
                            <div class="card-arrow-top-left"></div>
                            <div class="card-arrow-top-right"></div>
                            <div class="card-arrow-bottom-left"></div>
                            <div class="card-arrow-bottom-right"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END theme-panel -->
    </div>
    <!-- END #app -->
    
    <!-- ================== BEGIN core-js ================== -->
    <script src="/assets/js/vendor.min.js"></script>
    <script src="/assets/js/app.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- ================== END core-js ================== -->
    @yield('scripts')
</body>
</html>
