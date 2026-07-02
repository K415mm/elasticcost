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

<!-- 2.5 AI Provider Settings Card -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title text-theme mb-3">
            <i class="bi bi-cpu-fill me-2"></i> AI Provider Configuration
        </h5>
        <p class="text-muted small mb-4">
            Select and configure the AI provider and model utilized across sizing audits, cost proposal feedback, and the chat analyst assistant.
        </p>

        <form action="{{ route('settings.system.ai.update') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md-3 mb-4">
                    <label class="form-label text-white small">AI Provider Backend</label>
                    <select name="ai_provider" id="aiProviderSelect" class="form-select">
                        <option value="ollama" {{ $aiProvider === 'ollama' ? 'selected' : '' }}>Ollama (Local / On-Premise)</option>
                        <option value="lmstudio" {{ $aiProvider === 'lmstudio' ? 'selected' : '' }}>LM Studio (Local OpenAI API Compatible)</option>
                        <option value="gemini" {{ $aiProvider === 'gemini' ? 'selected' : '' }}>Gemini Studio (Cloud REST API)</option>
                        <option value="openrouter" {{ $aiProvider === 'openrouter' ? 'selected' : '' }}>OpenRouter (Cloud Hub API)</option>
                        <option value="qwen" {{ $aiProvider === 'qwen' ? 'selected' : '' }}>Qwen Cloud (Alibaba / DashScope API)</option>
                    </select>
                </div>

                <div class="col-md-3 mb-4 d-flex flex-column justify-content-end pb-1">
                    <div class="form-check form-switch mb-2">
                        <input type="checkbox" name="ai_multi_agent_enabled" id="aiMultiAgentEnabled" value="1" class="form-check-input" {{ $aiMultiAgentEnabled ? 'checked' : '' }}>
                        <label class="form-check-label text-white small" for="aiMultiAgentEnabled">Multi-Agent Architecture</label>
                    </div>
                </div>

                <!-- Ollama Settings Section -->
                <div class="col-md-12 ai-provider-settings-group" id="settings-ollama">
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <label class="form-label text-white small">Ollama API URL</label>
                            <div class="input-group">
                                <input type="url" id="ollama_url" name="ollama_url" class="form-control mono-cell" value="{{ $ollamaUrl }}" placeholder="http://localhost:11434">
                                <button type="button" class="btn btn-outline-theme btn-scan-models" data-provider="ollama">
                                    <i class="bi bi-arrow-repeat me-1"></i> Scan
                                </button>
                            </div>
                            <div class="small text-muted mt-1" style="font-size: 10px;">Default local port is 11434. Ensure Ollama service is running.</div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label text-white small">Target Main Model Name</label>
                            <select id="ollama_model" name="ollama_model" class="form-select mono-cell">
                                <option value="{{ $ollamaModel }}" selected>{{ $ollamaModel }}</option>
                            </select>
                            <div class="small text-muted mt-1" style="font-size: 10px;">Main execution model for complex tasks (e.g. gemma4:e2b).</div>
                            <div id="ollama-scan-status" class="mt-1 small"></div>
                        </div>
                        <div class="col-md-4 mb-4 ollama-light-model-container" style="display: {{ $aiMultiAgentEnabled ? 'block' : 'none' }};">
                            <label class="form-label text-white small">Target Light Model Name (Router)</label>
                            <select id="ollama_light_model" name="ollama_light_model" class="form-select mono-cell">
                                <option value="{{ $ollamaLightModel }}" selected>{{ $ollamaLightModel }}</option>
                            </select>
                            <div class="small text-muted mt-1" style="font-size: 10px;">Lightweight model for quick classification (e.g. gemma-3-1b).</div>
                        </div>
                    </div>
                </div>

                <!-- LM Studio Settings Section -->
                <div class="col-md-12 ai-provider-settings-group" id="settings-lmstudio" style="display: none;">
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <label class="form-label text-white small">LM Studio Server Endpoint</label>
                            <div class="input-group">
                                <input type="url" id="lmstudio_url" name="lmstudio_url" class="form-control mono-cell" value="{{ $lmstudioUrl }}" placeholder="http://localhost:1234/v1">
                                <button type="button" class="btn btn-outline-theme btn-scan-models" data-provider="lmstudio">
                                    <i class="bi bi-arrow-repeat me-1"></i> Scan
                                </button>
                            </div>
                            <div class="small text-muted mt-1" style="font-size: 10px;">Default LM Studio port is 1234. Include /v1 suffix.</div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label text-white small">LM Studio Model Key</label>
                            <select id="lmstudio_model" name="lmstudio_model" class="form-select mono-cell">
                                <option value="{{ $lmstudioModel }}" selected>{{ $lmstudioModel }}</option>
                            </select>
                            <div class="small text-muted mt-1" style="font-size: 10px;">Main execution model loaded in LM Studio.</div>
                            <div id="lmstudio-scan-status" class="mt-1 small"></div>
                        </div>
                        <div class="col-md-4 mb-4 lmstudio-light-model-container" style="display: {{ $aiMultiAgentEnabled ? 'block' : 'none' }};">
                            <label class="form-label text-white small">LM Studio Light Model Key (Router)</label>
                            <select id="lmstudio_light_model" name="lmstudio_light_model" class="form-select mono-cell">
                                <option value="{{ $lmstudioLightModel }}" selected>{{ $lmstudioLightModel }}</option>
                            </select>
                            <div class="small text-muted mt-1" style="font-size: 10px;">Lightweight model for quick classification.</div>
                        </div>
                    </div>
                </div>

                <!-- Gemini Settings Section -->
                <div class="col-md-12 ai-provider-settings-group" id="settings-gemini" style="display: none;">
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <label class="form-label text-white small">Gemini Model Identifier</label>
                            <select id="gemini_model" name="gemini_model" class="form-select mono-cell">
                                <option value="{{ $geminiModel }}" selected>{{ $geminiModel }}</option>
                            </select>
                            <div class="small text-muted mt-1" style="font-size: 10px;">Supported models include gemini-1.5-pro, gemini-2.5-flash, etc.</div>
                            <div id="gemini-scan-status" class="mt-1 small"></div>
                        </div>
                        <div class="col-md-4 mb-4 gemini-light-model-container" style="display: {{ $aiMultiAgentEnabled ? 'block' : 'none' }};">
                            <label class="form-label text-white small">Gemini Light Model Identifier (Router)</label>
                            <select id="gemini_light_model" name="gemini_light_model" class="form-select mono-cell">
                                <option value="{{ $geminiLightModel }}" selected>{{ $geminiLightModel }}</option>
                            </select>
                            <div class="small text-muted mt-1" style="font-size: 10px;">Lightweight model for quick classification (e.g. gemini-1.5-flash).</div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label text-white small">Gemini Studio API Key</label>
                            <div class="input-group">
                                <input type="password" id="gemini_api_key" name="gemini_api_key" class="form-control mono-cell" value="{{ $geminiApiKey }}" placeholder="AIzaSy...">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('gemini_api_key')">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button type="button" class="btn btn-outline-theme btn-scan-models" data-provider="gemini">
                                    <i class="bi bi-arrow-repeat me-1"></i> Scan
                                </button>
                            </div>
                            <div class="small text-muted mt-1" style="font-size: 10px;">Your private developer API key from Google AI Studio. Stored securely.</div>
                        </div>
                    </div>
                </div>

                <!-- OpenRouter Settings Section -->
                <div class="col-md-12 ai-provider-settings-group" id="settings-openrouter" style="display: none;">
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <label class="form-label text-white small">OpenRouter Model Identifier</label>
                            <select id="openrouter_model" name="openrouter_model" class="form-select mono-cell">
                                <option value="{{ $openrouterModel }}" selected>{{ $openrouterModel }}</option>
                            </select>
                            <div class="small text-muted mt-1" style="font-size: 10px;">E.g. meta-llama/llama-3-8b-instruct:free, google/gemini-2.5-flash, etc.</div>
                            <div id="openrouter-scan-status" class="mt-1 small"></div>
                        </div>
                        <div class="col-md-4 mb-4 openrouter-light-model-container" style="display: {{ $aiMultiAgentEnabled ? 'block' : 'none' }};">
                            <label class="form-label text-white small">OpenRouter Light Model Identifier (Router)</label>
                            <select id="openrouter_light_model" name="openrouter_light_model" class="form-select mono-cell">
                                <option value="{{ $openrouterLightModel }}" selected>{{ $openrouterLightModel }}</option>
                            </select>
                            <div class="small text-muted mt-1" style="font-size: 10px;">Lightweight model for quick classification.</div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label text-white small">OpenRouter API Key</label>
                            <div class="input-group">
                                <input type="password" id="openrouter_api_key" name="openrouter_api_key" class="form-control mono-cell" value="{{ $openrouterApiKey }}" placeholder="sk-or-v1-...">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('openrouter_api_key')">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button type="button" class="btn btn-outline-theme btn-scan-models" data-provider="openrouter">
                                    <i class="bi bi-arrow-repeat me-1"></i> Scan
                                </button>
                            </div>
                            <div class="small text-muted mt-1" style="font-size: 10px;">Your OpenRouter API Key. Stored securely.</div>
                        </div>
                    </div>
                </div>

                <!-- Qwen Settings Section -->
                <div class="col-md-12 ai-provider-settings-group" id="settings-qwen" style="display: none;">
                    <div class="row">
                        <div class="col-md-3 mb-4">
                            <label class="form-label text-white small">Qwen API Endpoint</label>
                            <input type="url" id="qwen_url" name="qwen_url" class="form-control mono-cell" value="{{ $qwenUrl }}" placeholder="https://dashscope-intl.aliyuncs.com/compatible-mode/v1">
                            <div class="small text-muted mt-1" style="font-size: 10px;">Qwen Cloud compatible base URL.</div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <label class="form-label text-white small">Qwen Model Key</label>
                            <select id="qwen_model" name="qwen_model" class="form-select mono-cell">
                                <option value="{{ $qwenModel }}" selected>{{ $qwenModel }}</option>
                            </select>
                            <div class="small text-muted mt-1" style="font-size: 10px;">Main Qwen model (e.g. qwen-plus).</div>
                            <div id="qwen-scan-status" class="mt-1 small"></div>
                        </div>
                        <div class="col-md-3 mb-4 qwen-light-model-container" style="display: {{ $aiMultiAgentEnabled ? 'block' : 'none' }};">
                            <label class="form-label text-white small">Qwen Light Model Key (Router)</label>
                            <select id="qwen_light_model" name="qwen_light_model" class="form-select mono-cell">
                                <option value="{{ $qwenLightModel }}" selected>{{ $qwenLightModel }}</option>
                            </select>
                            <div class="small text-muted mt-1" style="font-size: 10px;">Router model (e.g. qwen-turbo).</div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <label class="form-label text-white small">Qwen API Key</label>
                            <div class="input-group">
                                <input type="password" id="qwen_api_key" name="qwen_api_key" class="form-control mono-cell" value="{{ $qwenApiKey }}" placeholder="sk-...">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('qwen_api_key')">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button type="button" class="btn btn-outline-theme btn-scan-models" data-provider="qwen">
                                    <i class="bi bi-arrow-repeat me-1"></i> Scan
                                </button>
                            </div>
                            <div class="small text-muted mt-1" style="font-size: 10px;">Alibaba DashScope / Qwen API key.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-top border-secondary border-opacity-30 pt-3 mt-2 d-flex justify-content-end">
                <button type="submit" class="btn btn-outline-theme px-5 py-2">
                    <i class="bi bi-save me-2"></i> Save AI Settings
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
    function togglePasswordVisibility(id) {
        var input = document.getElementById(id);
        if (input) {
            if (input.type === "password") {
                input.type = "text";
            } else {
                input.type = "password";
            }
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        // AI Settings visibility toggler
        var aiProviderSelect = document.getElementById("aiProviderSelect");
        var settingsGroups = document.querySelectorAll(".ai-provider-settings-group");
        var multiAgentCheckbox = document.getElementById("aiMultiAgentEnabled");

        function toggleAiSettings() {
            if (!aiProviderSelect) return;
            var selected = aiProviderSelect.value;
            settingsGroups.forEach(function(group) {
                if (group.id === "settings-" + selected) {
                    group.style.setProperty("display", "block", "important");
                } else {
                    group.style.setProperty("display", "none", "important");
                }
            });
        }

        function toggleMultiAgentFields() {
            var isChecked = multiAgentCheckbox ? multiAgentCheckbox.checked : false;
            document.querySelectorAll(".ollama-light-model-container, .lmstudio-light-model-container, .gemini-light-model-container, .openrouter-light-model-container, .qwen-light-model-container").forEach(function(el) {
                el.style.setProperty("display", isChecked ? "block" : "none", "important");
            });
        }

        if (multiAgentCheckbox) {
            multiAgentCheckbox.addEventListener("change", toggleMultiAgentFields);
        }

        function scanModels(provider) {
            var urlInput = document.getElementById(provider + "_url");
            var keyInput = document.getElementById("gemini_api_key");
            var selectElement = document.getElementById(provider + "_model");
            var lightSelectElement = document.getElementById(provider + "_light_model");
            var statusDiv = document.getElementById(provider + "-scan-status");
            var button = document.querySelector(`.btn-scan-models[data-provider="${provider}"]`);

            if (!statusDiv) return;

            var params = new URLSearchParams();
            params.append('provider', provider);
            
            if (urlInput) {
                params.append('url', urlInput.value.trim());
            }
            if (provider === 'gemini' && keyInput) {
                params.append('api_key', keyInput.value.trim());
            }
            if (provider === 'openrouter') {
                var openrouterKey = document.getElementById("openrouter_api_key");
                if (openrouterKey) {
                    params.append('api_key', openrouterKey.value.trim());
                }
            }
            if (provider === 'qwen') {
                var qwenKey = document.getElementById("qwen_api_key");
                if (qwenKey) {
                    params.append('api_key', qwenKey.value.trim());
                }
            }
            if (selectElement) {
                params.append('target_model', selectElement.value);
            }

            statusDiv.innerHTML = '<span class="text-warning small"><i class="spinner-border spinner-border-sm me-1"></i> Testing connection and scanning models...</span>';
            if (button) {
                button.disabled = true;
                var origText = button.innerHTML;
                button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            }

            fetch("{{ route('ollama.ping') }}?" + params.toString())
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.status === 'ok') {
                        statusDiv.innerHTML = `<span class="text-success small"><i class="bi bi-check-circle-fill me-1"></i> Connected! Found ${data.available_models.length} models.</span>`;
                        
                        [selectElement, lightSelectElement].forEach(function(selectEl) {
                            if (!selectEl) return;
                            var currentValue = selectEl.value;
                            selectEl.innerHTML = '';
                            
                            data.available_models.forEach(function(model) {
                                var opt = document.createElement('option');
                                opt.value = model;
                                opt.textContent = model;
                                if (model === currentValue) {
                                    opt.selected = true;
                                }
                                selectEl.appendChild(opt);
                            });
                            
                            if (currentValue && !data.available_models.includes(currentValue)) {
                                var customOpt = document.createElement('option');
                                customOpt.value = currentValue;
                                customOpt.textContent = currentValue + " (saved)";
                                customOpt.selected = true;
                                selectEl.insertBefore(customOpt, selectEl.firstChild);
                            }
                        });
                    } else {
                        statusDiv.innerHTML = `<span class="text-danger small"><i class="bi bi-exclamation-triangle-fill me-1"></i> ${data.message || 'Connection failed'}</span>`;
                    }
                })
                .catch(function(err) {
                    statusDiv.innerHTML = `<span class="text-danger small"><i class="bi bi-x-circle-fill me-1"></i> Error: ${err.message || err}</span>`;
                })
                .finally(function() {
                    if (button) {
                        button.disabled = false;
                        button.innerHTML = origText;
                    }
                });
        }

        document.querySelectorAll('.btn-scan-models').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var provider = btn.getAttribute('data-provider');
                scanModels(provider);
            });
        });

        if (aiProviderSelect) {
            aiProviderSelect.addEventListener("change", function() {
                toggleAiSettings();
                scanModels(aiProviderSelect.value);
            });
            toggleAiSettings(); // Run initial state toggle
            toggleMultiAgentFields(); // Run initial multi-agent check
            scanModels(aiProviderSelect.value);
        }

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
