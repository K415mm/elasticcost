@extends('layouts.app')

@section('title', 'Document File Manager & RAG Ingestion')

@section('styles')
<style>
    .file-manager-sidebar {
        border-right: 1px solid rgba(255, 255, 255, 0.1);
        width: 250px;
        min-width: 250px;
    }
    .file-manager-content {
        min-height: 400px;
    }
    .agent-card {
        transition: all 0.2s ease-in-out;
    }
    .agent-card:hover {
        border-color: rgba(var(--bs-theme-rgb), 0.5) !important;
        box-shadow: 0 4px 15px rgba(var(--bs-theme-rgb), 0.1);
    }
    .mono-cell {
        font-family: 'JetBrains Mono', Courier, monospace;
    }
    .chunk-item {
        border-left: 3px solid var(--bs-theme);
        background: rgba(255, 255, 255, 0.02);
    }
    [data-bs-theme="light"] .chunk-item {
        background: rgba(0, 0, 0, 0.02);
    }
</style>
@endsection

@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('messages.dashboard') ?: 'Dashboard' }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('settings.system') }}">{{ __('messages.system_settings') ?: 'System Settings' }}</a></li>
    <li class="breadcrumb-item active">FILE MANAGER & RAG</li>
</ul>

<div class="d-flex align-items-center mb-4">
    <div class="flex-grow-1">
        <h1 class="page-header mb-0">
            File Manager & Semantic RAG
            <small class="d-block mt-1">Upload context documents, calibrate pgvector semantic chunk boundaries, and configure per-agent RAG retrieval thresholds.</small>
        </h1>
    </div>
    <div>
        <a href="{{ route('settings.system') }}" class="btn btn-outline-secondary">
            <i class="bi bi-gear me-1"></i> System Settings
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <strong>Success!</strong> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <strong>Error!</strong> Please check the inputs:
        <ul class="mb-0 mt-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row">
    <!-- Left Column: File Manager -->
    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="card h-100 m-0 d-flex flex-column overflow-hidden">
            <div class="card-header fw-bold small d-flex align-items-center justify-content-between">
                <span>DOCUMENT ARCHIVE & VECTOR INGESTION</span>
                <button type="button" class="btn btn-outline-theme btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="bi bi-upload me-1"></i> Upload File
                </button>
            </div>
            
            <div class="card-body p-0 flex-grow-1 overflow-hidden d-flex flex-column" style="min-height: 550px;">
                <div class="file-manager h-100 d-flex flex-row flex-grow-1">
                    
                    <!-- File Manager Sidebar -->
                    <div class="file-manager-sidebar d-flex flex-column p-3 justify-content-between">
                        <div>
                            <input type="text" id="fileSearchInput" class="form-control form-control-sm mb-3" placeholder="Search archive..." />
                            
                            <div class="list-group list-group-flush small">
                                <a href="#" class="list-group-item list-group-item-action border-0 px-2 py-1.5 active filter-link" data-filter="all">
                                    <i class="fa fa-folder-open me-2 text-theme"></i> All Files 
                                    <span class="badge bg-secondary bg-opacity-20 text-inverse rounded-pill float-end">{{ count($documents) }}</span>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action border-0 px-2 py-1.5 filter-link" data-filter="docx">
                                    <i class="far fa-file-word me-2 text-primary"></i> Word Docs (.docx)
                                    <span class="badge bg-secondary bg-opacity-20 text-inverse rounded-pill float-end">
                                        {{ $documents->filter(fn($d) => str_ends_with(strtolower($d->original_name), '.docx'))->count() }}
                                    </span>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action border-0 px-2 py-1.5 filter-link" data-filter="md">
                                    <i class="far fa-file-code me-2 text-info"></i> Markdown (.md)
                                    <span class="badge bg-secondary bg-opacity-20 text-inverse rounded-pill float-end">
                                        {{ $documents->filter(fn($d) => str_ends_with(strtolower($d->original_name), '.md'))->count() }}
                                    </span>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action border-0 px-2 py-1.5 filter-link" data-filter="txt">
                                    <i class="fa fa-file-text me-2 text-muted"></i> Plain Text (.txt)
                                    <span class="badge bg-secondary bg-opacity-20 text-inverse rounded-pill float-end">
                                        {{ $documents->filter(fn($d) => str_ends_with(strtolower($d->original_name), '.txt'))->count() }}
                                    </span>
                                </a>
                            </div>
                        </div>

                        <!-- SSD Space indicator in sidebar footer -->
                        <div class="border-top pt-3 mt-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1 ps-1 small">
                                    <div class="fw-bold small mb-1">RAG Storage Space:</div>
                                    <div class="progress h-5px my-1">
                                        <div class="progress-bar progress-bar-striped bg-theme" style="width: {{ $usedPercentage }}%"></div>
                                    </div>
                                    <div class="fw-bold text-body text-opacity-50 small">
                                        <span class="text-white">{{ $freeSpaceGb }}GB</span> free of {{ $totalDiskGb }}GB
                                    </div>
                                    <div class="small text-muted mt-2">
                                        <i class="bi bi-cpu me-1"></i> Chunks: <strong>{{ $totalChunks }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- File Manager Main Content Grid -->
                    <div class="file-manager-content flex-grow-1 d-flex flex-column overflow-hidden">
                        <div class="border-bottom p-2 d-flex align-items-center justify-content-between">
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-default px-2" onclick="window.location.reload();">
                                    <i class="fa fa-sync me-1"></i> Refresh Explorer
                                </button>
                            </div>
                            <div class="small text-muted px-2">
                                Total Ingested Size: <strong>{{ $totalBytes >= 1024 * 1024 ? number_format($totalBytes / (1024 * 1024), 2) . ' MB' : number_format($totalBytes / 1024, 1) . ' KB' }}</strong>
                            </div>
                        </div>
                        
                        <div class="table-responsive flex-grow-1 overflow-auto">
                            <table class="table table-striped table-borderless table-sm m-0 text-nowrap small" id="documentsTable">
                                <thead>
                                    <tr class="border-bottom text-muted">
                                        <th class="ps-3 w-50px">Mime</th>
                                        <th>File Name</th>
                                        <th>Size</th>
                                        <th>Chunks</th>
                                        <th>Status</th>
                                        <th>Uploaded At</th>
                                        <th class="pe-3 text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($documents as $doc)
                                        @php
                                            $ext = strtolower(pathinfo($doc->original_name, PATHINFO_EXTENSION));
                                            $sizeStr = $doc->size >= 1024 * 1024 
                                                ? number_format($doc->size / (1024 * 1024), 2) . ' MB' 
                                                : number_format($doc->size / 1024, 1) . ' KB';
                                        @endphp
                                        <tr class="align-middle doc-row" data-ext="{{ $ext }}" data-name="{{ strtolower($doc->original_name) }}">
                                            <td class="ps-3 text-center">
                                                @if($ext === 'docx')
                                                    <i class="far fa-file-word text-primary fa-lg" title="Microsoft Word"></i>
                                                @elseif($ext === 'md')
                                                    <i class="far fa-file-code text-info fa-lg" title="Markdown"></i>
                                                @elseif($ext === 'txt')
                                                    <i class="fa fa-file-text text-muted fa-lg" title="Plain Text"></i>
                                                @else
                                                    <i class="far fa-file-alt text-warning fa-lg" title="Plain Text"></i>
                                                @endif
                                            </td>
                                            <td class="fw-bold">
                                                {{ $doc->original_name }}
                                            </td>
                                            <td class="mono-cell text-muted">{{ $sizeStr }}</td>
                                            <td class="mono-cell">
                                                @if($doc->status === 'completed')
                                                    <span class="badge bg-secondary bg-opacity-20 text-white border border-secondary border-opacity-30">{{ $doc->chunk_count }}</span>
                                                @elseif($doc->status === 'failed')
                                                    <span class="text-danger">-</span>
                                                @else
                                                    <span class="text-muted"><i class="fa fa-circle-notch fa-spin"></i></span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($doc->status === 'pending')
                                                    <span class="badge bg-warning bg-opacity-15 text-warning border border-warning border-opacity-20">
                                                        <i class="fa fa-clock fa-spin me-1"></i> Pending
                                                    </span>
                                                @elseif($doc->status === 'processing')
                                                    <span class="badge bg-info bg-opacity-15 text-info border border-info border-opacity-20">
                                                        <i class="fa fa-spinner fa-spin me-1"></i> Chunking...
                                                    </span>
                                                @elseif($doc->status === 'completed')
                                                    <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-20">
                                                        <i class="fa fa-check-circle me-1"></i> Vectorized
                                                    </span>
                                                @elseif($doc->status === 'failed')
                                                    <span class="badge bg-danger bg-opacity-15 text-danger border border-danger border-opacity-20 cursor-pointer" 
                                                          data-bs-toggle="popover" 
                                                          data-bs-trigger="hover focus" 
                                                          title="Ingestion Failed" 
                                                          data-bs-content="{{ $doc->error_message ?: 'Unknown parser error.' }}">
                                                        <i class="fa fa-exclamation-triangle me-1"></i> Error
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-muted">{{ $doc->created_at->format('M d, Y h:i A') }}</td>
                                            <td class="pe-3 text-end">
                                                <div class="d-inline-flex gap-1">
                                                    @if($doc->status === 'completed')
                                                        <button type="button" class="btn btn-xs btn-outline-info btn-inspect" data-id="{{ $doc->id }}" data-name="{{ $doc->original_name }}">
                                                            <i class="fa fa-eye me-1"></i> Inspect
                                                        </button>
                                                    @endif
                                                    <form action="{{ route('settings.files.destroy', $doc->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this document and all its vector chunks?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-xs btn-outline-danger">
                                                            <i class="fa fa-trash me-1"></i> Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                <i class="bi bi-folder2-open d-block fs-2 mb-2"></i>
                                                No context files uploaded yet. Add plain text, markdown or docx references to inject them into Agent contexts.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
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
    </div>

    <!-- Right Column: Agent RAG Configurations -->
    <div class="col-xl-4 col-lg-5 mb-4">
        <!-- RAG Embedding Model Card -->
        <div class="card mb-4 border-theme border-opacity-30 bg-dark bg-opacity-40">
            <div class="card-body">
                <h6 class="text-theme mb-3 small fw-bold"><i class="bi bi-database-gear me-2"></i> RAG EMBEDDING CONFIGURATION</h6>
                <p class="text-muted small mb-3">Decouple the vector embedding generation provider and model from the main conversation LLM provider.</p>

                <form id="embeddingSettingsForm">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label text-muted small">Embedding Provider</label>
                        <select name="rag_embedding_provider" class="form-select form-select-sm" id="embProviderSelect" style="background-color: #1a2035; color: #fff; border-color: rgba(255,255,255,0.15);">
                            <option value="ollama" {{ $embeddingProvider === 'ollama' ? 'selected' : '' }}>Ollama (Local)</option>
                            <option value="lmstudio" {{ $embeddingProvider === 'lmstudio' ? 'selected' : '' }}>LM Studio (Local)</option>
                            <option value="gemini" {{ $embeddingProvider === 'gemini' ? 'selected' : '' }}>Gemini API</option>
                            <option value="openrouter" {{ $embeddingProvider === 'openrouter' ? 'selected' : '' }}>OpenRouter API</option>
                            <option value="qwen" {{ $embeddingProvider === 'qwen' ? 'selected' : '' }}>Qwen API</option>
                        </select>
                    </div>

                    <!-- Provider connection settings (hidden, used by scan) -->
                    <input type="hidden" id="emb_ollama_url" value="{{ $providerSettings['ollama_url'] ?? '' }}">
                    <input type="hidden" id="emb_lmstudio_url" value="{{ $providerSettings['lmstudio_url'] ?? '' }}">
                    <input type="hidden" id="emb_gemini_api_key" value="{{ $providerSettings['gemini_api_key'] ?? '' }}">
                    <input type="hidden" id="emb_openrouter_api_key" value="{{ $providerSettings['openrouter_api_key'] ?? '' }}">
                    <input type="hidden" id="emb_qwen_url" value="{{ $providerSettings['qwen_url'] ?? '' }}">
                    <input type="hidden" id="emb_qwen_api_key" value="{{ $providerSettings['qwen_api_key'] ?? '' }}">

                    <div class="mb-3">
                        <label class="form-label text-muted small">Embedding Model</label>
                        <div class="input-group">
                            <select name="rag_embedding_model" class="form-select form-select-sm mono-cell" id="embModelSelect" style="background-color: #1a2035; color: #fff; border-color: rgba(255,255,255,0.15);">
                                <option value="{{ $embeddingModel }}" selected>{{ $embeddingModel }}</option>
                            </select>
                            <button type="button" class="btn btn-outline-theme btn-sm" id="btnEmbScanModels">
                                <i class="bi bi-arrow-repeat me-1"></i> Scan
                            </button>
                        </div>
                        <div id="emb-scan-status" class="mt-1 small"></div>
                        <div class="form-text text-muted" style="font-size: 10px;" id="modelHelpText">
                            Ollama: <code>nomic-embed-text</code>, <code>mxbai-embed-large</code><br>
                            LM Studio: <code>text-embedding-embeddinggemma-300m</code>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-outline-theme btn-sm w-100 py-1.5" id="btnSaveEmbedding">
                        <i class="bi bi-save me-1"></i> Save Embedding Settings
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

        <h5 class="text-theme mb-3 small fw-bold"><i class="bi bi-sliders me-2"></i> AGENT RAG CONFIGURATION</h5>
        <p class="text-muted small mb-3">Enable and calibrate similarity thresholds on a per-agent basis. If RAG is enabled, the agent will query Postgres via pgvector for matching chunks before processing queries.</p>

        @foreach($agentConfigs as $key => $config)
            <div class="card mb-3 agent-card border-secondary border-opacity-20 bg-dark bg-opacity-40">
                <div class="card-body">
                    <form class="agent-config-form" data-key="{{ $key }}">
                        @csrf
                        <input type="hidden" name="agent_key" value="{{ $key }}">
                        
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="text-white mb-0">
                                <i class="bi bi-robot text-theme me-2"></i> {{ $config['name'] }}
                            </h6>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input agent-toggle" type="checkbox" name="enabled" value="1" id="rag_enabled_{{ $key }}" {{ $config['enabled'] ? 'checked' : '' }}>
                                <label class="form-check-label text-theme small fw-bold" for="rag_enabled_{{ $key }}">RAG INJECT</label>
                            </div>
                        </div>

                        <div class="agent-settings-group" id="settings_group_{{ $key }}" style="{{ $config['enabled'] ? '' : 'opacity: 0.5; pointer-events: none;' }}">
                            <div class="mb-3">
                                <label class="form-label text-muted small d-flex justify-content-between">
                                    <span>Similarity Threshold</span>
                                    <span class="fw-bold text-white mono-cell" id="threshold_val_{{ $key }}">{{ number_format($config['threshold'], 2) }}</span>
                                </label>
                                <input type="range" class="form-range threshold-slider" name="threshold" min="0" max="1" step="0.05" value="{{ $config['threshold'] }}" data-target="threshold_val_{{ $key }}">
                                <span class="d-block small text-muted" style="font-size: 10px;">Lower threshold retrieves broader matches; higher retrieves exact matches.</span>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-muted small d-flex justify-content-between">
                                    <span>Max Chunks (Prompt Budget)</span>
                                    <span class="fw-bold text-white mono-cell" id="max_chunks_val_{{ $key }}">{{ $config['max_chunks'] }} chunks</span>
                                </label>
                                <input type="range" class="form-range max-chunks-slider" name="max_chunks" min="1" max="10" step="1" value="{{ $config['max_chunks'] }}" data-target="max_chunks_val_{{ $key }}">
                                <span class="d-block small text-muted" style="font-size: 10px;">Max number of chunks to inject into the agent prompt context.</span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-outline-theme btn-sm w-100 mt-2 py-1.5 btn-update-config">
                            <i class="bi bi-save me-1"></i> Update Configuration
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
        @endforeach
    </div>
</div>

<!-- ================== MODAL: UPLOAD FILE ================== -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">Upload Context Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('settings.files.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small mb-3">Choose a local reference document. The system will slice it into overlapping paragraphs and compute vector embeddings via the configured embeddings model.</p>
                    
                    <div class="mb-4">
                        <label for="documentFile" class="form-label text-white small">Choose Document File</label>
                        <input class="form-control" type="file" id="documentFile" name="document" required>
                        <div class="form-text text-muted" style="font-size: 10px;">Supported extensions: <strong>.txt, .md, .docx, .csv, .json, .html</strong> (Max: 10 MB).</div>
                    </div>
                    
                    <div class="p-3 border border-secondary border-opacity-20 rounded bg-dark bg-opacity-20 mb-2">
                        <div class="d-flex align-items-center text-info small">
                            <i class="fa fa-info-circle me-2 fs-5"></i>
                            <div>Once uploaded, a background queue worker splits the document and stores vectors in Postgres using <strong>pgvector</strong>.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-outline-theme btn-sm"><i class="bi bi-upload me-1"></i> Start Ingestion</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================== MODAL: CHUNKS INSPECTOR ================== -->
<div class="modal fade" id="inspectModal" tabindex="-1" aria-labelledby="inspectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="inspectModalLabel">Vector Chunk Inspector: <span id="inspectDocName" class="text-theme"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-dark bg-opacity-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="small text-muted">
                        Total Vector Segments: <strong id="inspectChunksCount" class="text-white"></strong>
                    </div>
                    <div class="small text-muted">
                        Active Embeddings Model: <span class="badge bg-secondary" id="activeEmbeddingModelBadge">{{ $embeddingProvider }}/{{ $embeddingModel }}</span>
                    </div>
                </div>
                
                <div id="inspectorLoading" class="text-center py-5">
                    <div class="spinner-border text-theme" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="text-muted mt-2 small">Retrieving vector records from PostgreSQL...</div>
                </div>

                <div id="inspectorChunksContainer" class="d-none">
                    <!-- Dynamic Chunks injected here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close Inspector</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Init Bootstrap tooltips / popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });

        // 1. Live updating range sliders labels
        document.querySelectorAll('.threshold-slider, .max-chunks-slider').forEach(function(slider) {
            slider.addEventListener('input', function() {
                const targetId = this.getAttribute('data-target');
                const targetEl = document.getElementById(targetId);
                if (targetEl) {
                    if (this.classList.contains('threshold-slider')) {
                        targetEl.textContent = parseFloat(this.value).toFixed(2);
                    } else {
                        targetEl.textContent = this.value + ' chunks';
                    }
                }
            });
        });

        // 2. Disable sliders UI if agent is disabled
        document.querySelectorAll('.agent-toggle').forEach(function(toggle) {
            toggle.addEventListener('change', function() {
                const key = this.id.replace('rag_enabled_', '');
                const settingsGroup = document.getElementById('settings_group_' + key);
                if (settingsGroup) {
                    if (this.checked) {
                        settingsGroup.style.opacity = '1';
                        settingsGroup.style.pointerEvents = 'auto';
                    } else {
                        settingsGroup.style.opacity = '0.5';
                        settingsGroup.style.pointerEvents = 'none';
                    }
                }
            });
        });

        // 3. AJAX Agent Config submission
        document.querySelectorAll('.agent-config-form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const agentKey = this.getAttribute('data-key');
                const btn = this.querySelector('.btn-update-config');
                const originalHtml = btn.innerHTML;
                
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Saving...';

                // Read values
                const enabled = this.querySelector('.agent-toggle').checked ? '1' : '0';
                const threshold = this.querySelector('[name="threshold"]').value;
                const maxChunks = this.querySelector('[name="max_chunks"]').value;

                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('agent_key', agentKey);
                formData.append('threshold', threshold);
                formData.append('max_chunks', maxChunks);
                if (this.querySelector('.agent-toggle').checked) {
                    formData.append('enabled', '1');
                }

                fetch('{{ route("settings.files.agent-config") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    
                    if (data.success) {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: data.message || 'Config saved!',
                            showConfirmButton: false,
                            timer: 2500,
                            background: '#1a2035',
                            color: '#fff',
                            iconColor: '#00acac'
                        });
                    } else {
                        throw new Error('Config failed to save');
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    Swal.fire({
                        icon: 'error',
                        title: 'Configuration Update Failed',
                        text: err.message || 'Check logs for details.',
                        background: '#1a2035',
                        color: '#fff'
                    });
                });
            });
        });

        // 4. Ingest/Chunk Inspector Modal
        const inspectModal = new bootstrap.Modal(document.getElementById('inspectModal'));
        document.querySelectorAll('.btn-inspect').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const docId = this.getAttribute('data-id');
                const docName = this.getAttribute('data-name');
                
                document.getElementById('inspectDocName').textContent = docName;
                document.getElementById('inspectorLoading').classList.remove('d-none');
                document.getElementById('inspectorChunksContainer').classList.add('d-none');
                document.getElementById('inspectorChunksContainer').innerHTML = '';
                document.getElementById('inspectChunksCount').textContent = '...';
                
                inspectModal.show();
                
                // Fetch segments
                fetch(`/settings/files/${docId}/chunks`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('inspectorLoading').classList.add('d-none');
                    document.getElementById('inspectorChunksContainer').classList.remove('d-none');
                    document.getElementById('inspectChunksCount').textContent = data.chunks.length;
                    
                    if (data.chunks.length === 0) {
                        document.getElementById('inspectorChunksContainer').innerHTML = `
                            <div class="text-center py-4 text-muted small">
                                <i class="bi bi-layers d-block fs-3 mb-2"></i>
                                This document is empty or has not generated any chunk vectors yet.
                            </div>
                        `;
                        return;
                    }

                    data.chunks.forEach(function(chunk, idx) {
                        const wordCount = chunk.chunk_text.split(/\s+/).filter(w => w.length > 0).length;
                        const charCount = chunk.chunk_text.length;
                        const tokenCountEst = Math.round(charCount / 4);

                        const item = document.createElement('div');
                        item.className = 'chunk-item p-3 mb-3 border-start rounded';
                        item.innerHTML = `
                            <div class="d-flex justify-content-between mb-2 small text-muted border-bottom pb-1">
                                <div>
                                    <span class="badge bg-theme me-2">SEGMENT #${idx + 1}</span>
                                    <span class="mono-cell text-white text-opacity-50" style="font-size: 10px;">ID: ${chunk.id}</span>
                                </div>
                                <div>
                                    <span class="me-2"><i class="fa fa-font me-1"></i> ${charCount} chars</span>
                                    <span><i class="fa fa-calculator me-1"></i> ~${tokenCountEst} tokens</span>
                                </div>
                            </div>
                            <div class="chunk-body text-white text-opacity-90 small font-monospace" style="white-space: pre-wrap; font-size: 11px;">
                                ${escapeHtml(chunk.chunk_text)}
                            </div>
                        `;
                        document.getElementById('inspectorChunksContainer').appendChild(item);
                    });
                })
                .catch(err => {
                    document.getElementById('inspectorLoading').classList.add('d-none');
                    document.getElementById('inspectorChunksContainer').classList.remove('d-none');
                    document.getElementById('inspectorChunksContainer').innerHTML = `
                        <div class="alert alert-danger mb-0">
                            <strong>Inspection Failed:</strong> Unable to retrieve document vector chunks.
                        </div>
                    `;
                });
            });
        });

        function escapeHtml(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // AJAX Embedding Config submission
        const embeddingForm = document.getElementById('embeddingSettingsForm');
        if (embeddingForm) {
            embeddingForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const btn = document.getElementById('btnSaveEmbedding');
                const originalHtml = btn.innerHTML;
                
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Saving...';

                const provider = document.getElementById('embProviderSelect').value;
                const model = document.getElementById('embModelSelect').value;

                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('rag_embedding_provider', provider);
                formData.append('rag_embedding_model', model);

                fetch('{{ route("settings.files.embedding-config") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    
                    if (data.success) {
                        const dynamicBadge = document.getElementById('activeEmbeddingModelBadge');
                        if (dynamicBadge) {
                            dynamicBadge.textContent = provider + '/' + model;
                        }
                        
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: data.message || 'Embedding settings saved!',
                            showConfirmButton: false,
                            timer: 2500,
                            background: '#1a2035',
                            color: '#fff',
                            iconColor: '#00acac'
                        });
                    } else {
                        throw new Error('Embedding config failed to save');
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    Swal.fire({
                        icon: 'error',
                        title: 'Embedding Update Failed',
                        text: err.message || 'Check logs for details.',
                        background: '#1a2035',
                        color: '#fff'
                    });
                });
            });
        }

        // Scan models for embedding provider
        function scanEmbModels() {
            var provider = document.getElementById('embProviderSelect').value;
            var selectEl = document.getElementById('embModelSelect');
            var statusDiv = document.getElementById('emb-scan-status');
            var btn = document.getElementById('btnEmbScanModels');

            if (!statusDiv || !selectEl) return;

            var params = new URLSearchParams();
            params.append('provider', provider);

            if (provider === 'ollama') {
                var urlEl = document.getElementById('emb_ollama_url');
                if (urlEl) params.append('url', urlEl.value.trim());
            } else if (provider === 'lmstudio') {
                var urlEl = document.getElementById('emb_lmstudio_url');
                if (urlEl) params.append('url', urlEl.value.trim());
            } else if (provider === 'gemini') {
                var keyEl = document.getElementById('emb_gemini_api_key');
                if (keyEl) params.append('api_key', keyEl.value.trim());
            } else if (provider === 'openrouter') {
                var keyEl = document.getElementById('emb_openrouter_api_key');
                if (keyEl) params.append('api_key', keyEl.value.trim());
            } else if (provider === 'qwen') {
                var keyEl = document.getElementById('emb_qwen_api_key');
                var urlEl = document.getElementById('emb_qwen_url');
                if (keyEl) params.append('api_key', keyEl.value.trim());
                if (urlEl) params.append('url', urlEl.value.trim());
            }

            if (selectEl) {
                params.append('target_model', selectEl.value);
            }

            statusDiv.innerHTML = '<span class="text-warning small"><i class="spinner-border spinner-border-sm me-1"></i> Scanning models...</span>';
            if (btn) {
                btn.disabled = true;
                var origText = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            }

            fetch("{{ route('ollama.ping') }}?" + params.toString())
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.status === 'ok') {
                        statusDiv.innerHTML = '<span class="text-success small"><i class="bi bi-check-circle-fill me-1"></i> Connected! Found ' + data.available_models.length + ' models.</span>';

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
                    } else {
                        statusDiv.innerHTML = '<span class="text-danger small"><i class="bi bi-exclamation-triangle-fill me-1"></i> ' + (data.message || 'Connection failed') + '</span>';
                    }
                })
                .catch(function(err) {
                    statusDiv.innerHTML = '<span class="text-danger small"><i class="bi bi-x-circle-fill me-1"></i> Error: ' + (err.message || err) + '</span>';
                })
                .finally(function() {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = origText;
                    }
                });
        }

        var btnEmbScan = document.getElementById('btnEmbScanModels');
        if (btnEmbScan) {
            btnEmbScan.addEventListener('click', scanEmbModels);
        }

        // Dynamically suggest models based on provider dropdown change
        const embProviderSelect = document.getElementById('embProviderSelect');
        const embModelSelect = document.getElementById('embModelSelect');
        const modelHelpText = document.getElementById('modelHelpText');
        
        if (embProviderSelect && embModelSelect && modelHelpText) {
            embProviderSelect.addEventListener('change', function() {
                const provider = this.value;
                let defaultModel = 'nomic-embed-text';
                let helpHtml = '';

                if (provider === 'ollama') {
                    defaultModel = 'nomic-embed-text';
                    helpHtml = 'Ollama: <code>nomic-embed-text</code>, <code>mxbai-embed-large</code>';
                } else if (provider === 'lmstudio') {
                    defaultModel = 'text-embedding-embeddinggemma-300m';
                    helpHtml = 'LM Studio: <code>text-embedding-embeddinggemma-300m</code>';
                } else if (provider === 'gemini') {
                    defaultModel = 'text-embedding-004';
                    helpHtml = 'Gemini API: <code>text-embedding-004</code>';
                } else if (provider === 'openrouter') {
                    defaultModel = 'openai/text-embedding-3-small';
                    helpHtml = 'OpenRouter API: <code>openai/text-embedding-3-small</code>';
                } else if (provider === 'qwen') {
                    defaultModel = 'text-embedding-v3';
                    helpHtml = 'Qwen API: <code>text-embedding-v3</code>';
                }

                // Reset dropdown to default model and update help
                embModelSelect.innerHTML = '<option value="' + defaultModel + '" selected>' + defaultModel + '</option>';
                modelHelpText.innerHTML = helpHtml;

                // Auto-scan on provider change
                scanEmbModels();
            });

            // Auto-scan on page load
            scanEmbModels();
        }

        // 5. Sidebar File Filters
        document.querySelectorAll('.filter-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Set active class
                document.querySelectorAll('.filter-link').forEach(l => l.classList.remove('active'));
                this.classList.add('active');

                const filter = this.getAttribute('data-filter');
                document.querySelectorAll('.doc-row').forEach(row => {
                    const rowExt = row.getAttribute('data-ext');
                    if (filter === 'all' || rowExt === filter) {
                        row.classList.remove('d-none');
                    } else {
                        row.classList.add('d-none');
                    }
                });
            });
        });

        // 6. Inline Table Search
        const searchInput = document.getElementById('fileSearchInput');
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const activeFilter = document.querySelector('.filter-link.active').getAttribute('data-filter');

            document.querySelectorAll('.doc-row').forEach(row => {
                const name = row.getAttribute('data-name');
                const rowExt = row.getAttribute('data-ext');
                
                const matchesSearch = name.includes(query);
                const matchesFilter = activeFilter === 'all' || rowExt === activeFilter;

                if (matchesSearch && matchesFilter) {
                    row.classList.remove('d-none');
                } else {
                    row.classList.add('d-none');
                }
            });
        });
    });
</script>
@endsection
