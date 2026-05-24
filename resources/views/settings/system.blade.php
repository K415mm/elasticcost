@extends('layouts.app')

@section('title', __('messages.system_settings') ?: 'System Settings')

@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">{{ __('messages.clients') }}</a></li>
    <li class="breadcrumb-item active">{{ strtoupper(__('messages.system_settings') ?: 'SYSTEM SETTINGS') }}</li>
</ul>

<div class="d-flex align-items-center mb-4">
    <div class="flex-grow-1">
        <h1 class="page-header mb-0">
            {{ __('messages.system_settings') ?: 'System Settings' }}
            <small class="d-block mt-1">Configure global currency exchange rates and dynamic localization translations</small>
        </h1>
    </div>
    <div>
        <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> {{ __('messages.back_to_dashboard') ?: 'Back to Dashboard' }}
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <strong>{{ __('messages.success') }}!</strong> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row">
    <!-- 1. Exchange Rates Config Card -->
    <div class="col-xl-4 col-lg-5 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title text-theme mb-3">
                    <i class="bi bi-currency-exchange me-2"></i> {{ __('messages.exchange_rates') }}
                </h5>
                <p class="text-muted small mb-4">
                    {{ __('messages.usd_rate_info') ?: 'Exchange rates relative to USD ($1 USD = X target currency)' }}
                </p>

                <form action="{{ route('settings.system.update') }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label text-white small">USD to EUR Rate</label>
                        <div class="input-group">
                            <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">1 USD =</span>
                            <input type="number" step="0.0001" name="usd_to_eur_rate" class="form-control mono-cell" value="{{ $eurRate }}" required>
                            <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">EUR (€)</span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-white small">USD to TND Rate</label>
                        <div class="input-group">
                            <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">1 USD =</span>
                            <input type="number" step="0.0001" name="usd_to_tnd_rate" class="form-control mono-cell" value="{{ $tndRate }}" required>
                            <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30">TND (د.ت)</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-outline-theme w-100 py-2 mt-2">
                        <i class="bi bi-save me-1"></i> {{ __('messages.save_settings') ?: 'Save Settings' }}
                    </button>
                </form>
            </div>
            <div class="card-arrow">
                <div class="card-arrow-top-left"></div>
                <div class="card-arrow-top-right"></div>
                <div class="card-arrow-bottom-left"></div>
                <div class="card-arrow-bottom-right"></div>
            </div>
        </div>
    </div>

    <!-- 2. Charts / Analytics Card (HUD design style) -->
    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title text-theme mb-3">
                    <i class="bi bi-bar-chart-fill me-2"></i> Exchange Rate Conversion Visualizer
                </h5>
                <p class="text-muted small mb-4">
                    Visual ratio comparisons of 100 USD in target currencies using live configured conversion rates.
                </p>
                <div class="d-flex align-items-center justify-content-center" style="min-height: 200px;">
                    <div id="settingsExchangeChart" style="width: 100%;"></div>
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

<!-- 3. Translation Override Editor -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between mb-3 gap-2">
            <div>
                <h5 class="card-title text-theme mb-1">
                    <i class="bi bi-translate me-2"></i> {{ __('messages.translation_manager') ?: 'Translation Manager' }}
                </h5>
                <p class="text-muted small mb-0">
                    {{ __('messages.translation_manager_subtitle') ?: 'Manage and fix typos, misspellings, or customize terminology in real-time.' }}
                </p>
            </div>
            <div style="max-width: 250px;">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-secondary bg-opacity-20 border-secondary border-opacity-30"><i class="bi bi-search"></i></span>
                    <input type="text" id="translationSearch" class="form-control form-control-sm" placeholder="Filter keys...">
                </div>
            </div>
        </div>

        <form action="{{ route('settings.system.translations.update') }}" method="POST">
            @csrf
            <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
                <table class="table table-borderless table-hover align-middle mb-0" id="translationTable">
                    <thead>
                        <tr class="border-bottom text-muted small uppercase-tracking">
                            <th style="width: 25%;">{{ __('messages.key') ?: 'Key' }}</th>
                            <th style="width: 25%;">{{ __('messages.english') ?: 'English' }}</th>
                            <th style="width: 25%;">{{ __('messages.french') ?: 'French' }}</th>
                            <th style="width: 25%;">{{ __('messages.arabic') ?: 'Arabic' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($translationKeys as $subKey => $enDefault)
                            @php
                                $fullKey = 'messages.' . $subKey;
                                $enVal = isset($dbOverrides[$fullKey]) ? $dbOverrides[$fullKey]->where('locale', 'en')->first()?->value : '';
                                $frVal = isset($dbOverrides[$fullKey]) ? $dbOverrides[$fullKey]->where('locale', 'fr')->first()?->value : '';
                                $arVal = isset($dbOverrides[$fullKey]) ? $dbOverrides[$fullKey]->where('locale', 'ar')->first()?->value : '';
                                
                                $frDefault = $frDefaults[$subKey] ?? '';
                                $arDefault = $arDefaults[$subKey] ?? '';
                            @endphp
                            <tr class="translation-row" data-key="{{ strtolower($subKey) }}">
                                <td>
                                    <code class="text-theme fs-12px">{{ $subKey }}</code>
                                </td>
                                <td>
                                    <textarea name="translations[{{ $subKey }}][en]" class="form-control form-control-sm mono-cell mb-1" rows="1" placeholder="Use default text">{{ $enVal }}</textarea>
                                    <div class="small text-muted" style="font-size: 10px;">Default: <em>{{ $enDefault }}</em></div>
                                </td>
                                <td>
                                    <textarea name="translations[{{ $subKey }}][fr]" class="form-control form-control-sm mono-cell mb-1" rows="1" placeholder="Use default text">{{ $frVal }}</textarea>
                                    <div class="small text-muted" style="font-size: 10px;">Default: <em>{{ $frDefault }}</em></div>
                                </td>
                                <td>
                                    <textarea name="translations[{{ $subKey }}][ar]" class="form-control form-control-sm mono-cell mb-1 text-end" rows="1" placeholder="أدخل النص البديل" dir="rtl">{{ $arVal }}</textarea>
                                    <div class="small text-muted text-end" style="font-size: 10px;" dir="rtl">الافتراضي: <em>{{ $arDefault }}</em></div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 pt-3 border-top border-secondary border-opacity-30 d-flex justify-content-end">
                <button type="submit" class="btn btn-theme px-5 py-2">
                    <i class="bi bi-save me-2"></i> {{ __('messages.save') }}
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
@endsection

@section('scripts')
<script src="/assets/plugins/apexcharts/dist/apexcharts.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Live table filter
        var searchInput = document.getElementById("translationSearch");
        var rows = document.querySelectorAll(".translation-row");
        
        searchInput.addEventListener("keyup", function() {
            var filter = searchInput.value.toLowerCase();
            rows.forEach(function(row) {
                var key = row.getAttribute("data-key");
                if (key.indexOf(filter) > -1) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        });

        // Live Rate Comparison Bar Chart
        var options = {
            chart: {
                type: 'bar',
                height: 220,
                toolbar: { show: false }
            },
            colors: ['#0f766e', '#3b82f6', '#f97316'],
            series: [{
                name: 'Value equivalent of $100 USD',
                data: [100.0, {{ round(100 * $eurRate, 2) }}, {{ round(100 * $tndRate, 2) }}]
            }],
            xaxis: {
                categories: ['USD ($)', 'EUR (€)', 'TND (د.ت)'],
                labels: {
                    style: {
                        colors: '#fff'
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: '#fff'
                    }
                }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    distributed: true,
                    barHeight: '60%'
                }
            },
            legend: { show: false },
            dataLabels: {
                enabled: true,
                formatter: function (val, opt) {
                    var symbols = ['$', '€', ' TND'];
                    return val.toFixed(2) + symbols[opt.dataPointIndex];
                }
            }
        };

        var chart = new ApexCharts(document.querySelector("#settingsExchangeChart"), options);
        chart.render();
    });
</script>
@endsection
