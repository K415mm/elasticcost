@extends('layouts.app')

@section('title', __('messages.ai_chat') ?: 'AI Chat Assistant')

@section('styles')
<style>
    .chat-container {
        height: calc(100vh - 180px);
        min-height: 550px;
    }
    
    .chat-history-list {
        overflow-y: auto;
    }
    
    .chat-message-list {
        overflow-y: auto;
    }
    
    .chat-bubble p:last-child {
        margin-bottom: 0;
    }
    
    .chat-bubble pre {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.15);
        padding: 10px;
        border-radius: 6px;
        margin: 10px 0;
        overflow-x: auto;
    }
    
    .chat-bubble code {
        font-family: 'JetBrains Mono', Courier, monospace;
        color: #fca5a5;
        font-size: 0.9em;
    }
    
    .chat-bubble table {
        width: 100%;
        margin: 10px 0;
        border-collapse: collapse;
        font-size: 0.9em;
    }
    
    .chat-bubble th, .chat-bubble td {
        padding: 6px 10px;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }
    
    .chat-bubble th {
        background-color: rgba(255, 255, 255, 0.05);
        font-weight: bold;
    }

    .chat-bubble-content a {
        display: inline-block;
        padding: .25rem .6rem;
        border: 1px solid rgba(60, 210, 165, 0.5);
        border-radius: .375rem;
        background: rgba(60, 210, 165, 0.1);
        color: #3cd2a5;
        text-decoration: none;
        font-size: 0.82rem;
        font-weight: 600;
        margin: .15rem .15rem .15rem 0;
        transition: background 0.2s, color 0.2s;
    }

    .chat-bubble-content a:hover {
        background: rgba(60, 210, 165, 0.25);
        color: #ffffff;
    }

    .hover-delete-btn {
        opacity: 0;
        transition: opacity 0.2s ease-in-out;
    }

    .conversation-item:hover .hover-delete-btn {
        opacity: 1;
    }

    /* Rotation Outer border style */
    .chat-logo-container {
        position: relative;
        width: 60px;
        height: 60px;
    }
</style>
@endsection

@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('messages.dashboard') ?: 'Dashboard' }}</a></li>
    <li class="breadcrumb-item active">{{ strtoupper(__('messages.ai_chat') ?: 'AI CHAT') }}</li>
</ul>

<div class="card chat-container overflow-hidden">
    <div class="d-flex h-100">
        <!-- Sidebar: Chat History List -->
        <div class="w-300px border-end border-secondary border-opacity-35 bg-secondary bg-opacity-5 d-flex flex-column h-100">
            <!-- New Chat Trigger -->
            <div class="p-3 border-bottom border-secondary border-opacity-25">
                <a href="{{ route('ai-chat.index') }}" class="btn btn-outline-theme w-100 rounded-3 py-2 fw-bold text-decoration-none d-flex align-items-center justify-content-center gap-1">
                    <i class="bi bi-plus-lg"></i> New Chat
                </a>
            </div>

            <!-- Scrollable List of past threads -->
            <div class="flex-1 chat-history-list p-2">
                <div class="small fw-bold text-inverse text-opacity-50 px-2 py-2">
                    <i class="bi bi-clock-history me-1 text-theme"></i> CONVERSATION HISTORY
                </div>
                
                @if(count($conversations) === 0)
                    <div class="text-center text-muted small py-5">
                        No previous chats.
                    </div>
                @else
                    <div class="d-flex flex-column gap-1">
                        @foreach($conversations as $conv)
                            <div class="conversation-item d-flex align-items-center justify-content-between rounded-2 px-2 py-1 {{ $activeConversation && $activeConversation->id === $conv->id ? 'bg-secondary bg-opacity-20 border-theme' : 'hover-bg-light hover-bg-opacity-5' }}">
                                <a href="{{ route('ai-chat.index', $conv->id) }}" class="flex-1 text-decoration-none text-white text-opacity-75 py-1 text-truncate" style="max-width: 220px;">
                                    <i class="bi bi-chat-left-text me-2 small text-theme"></i> {{ $conv->title }}
                                </a>
                                
                                <form action="{{ route('ai-chat.destroy', $conv->id) }}" method="POST" class="hover-delete-btn ms-2">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-link btn-sm text-danger p-0 border-0" onclick="return confirm('Delete this conversation thread?');" title="Delete chat thread">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            
            <div class="p-3 bg-secondary bg-opacity-10 border-top border-secondary border-opacity-25 small text-center text-muted">
                Target Backend: <code class="text-theme">gemma4:e2b</code>
            </div>
        </div>

        <!-- Chat Pane Area -->
        <div class="flex-grow-1 d-flex flex-column h-100 bg-black bg-opacity-15">
            <!-- Scrollable Messages Window -->
            <div class="flex-grow-1 chat-message-list p-3 p-lg-4" id="chatMessageArea">
                @if(!$activeConversation)
                    <!-- Suggestion / Greeting View -->
                    <div class="d-flex flex-column align-items-center justify-content-center h-100 text-center py-5">
                        <div class="chat-logo-container mb-4">
                            <img src="/assets/css/images/logo-dark.png" alt="Logo" class="brand-logo-img-dark position-absolute start-50 top-50 translate-middle" style="width: 40px; height: auto;">
                            <img src="/assets/css/images/logo.png" alt="Logo" class="brand-logo-img-light position-absolute start-50 top-50 translate-middle" style="width: 40px; height: auto;">
                            <div class="spinner-border text-theme position-absolute top-0 start-0 w-100 h-100" style="border-width: 2px;" role="status"></div>
                        </div>
                        <h2 class="text-white fw-bold mb-1">Hello, I'm your ElasticCost Analyst</h2>
                        <p class="text-muted mb-4" style="max-width: 500px;">
                            Ask me anything about Elasticsearch node sizing, replica parameters, RAM-to-disk calculations, or pricing formulas.
                        </p>

                        <div class="row g-3 w-100" style="max-width: 750px;">
                            <div class="col-md-4">
                                <div class="card cursor-pointer h-100 hover-bg-light hover-bg-opacity-5" onclick="selectSuggestion('Sizing help: Model an Elasticsearch cluster for 500 GB/day raw volume with 30 days retention')">
                                    <div class="card-body py-3 px-3">
                                        <h6 class="text-theme mb-1"><i class="bi bi-calculator me-1"></i> Sizing Help</h6>
                                        <small class="text-muted d-block">Model a cluster for 500 GB/day with 30 days retention.</small>
                                    </div>
                                    <div class="card-arrow">
                                        <div class="card-arrow-top-left"></div><div class="card-arrow-top-right"></div>
                                        <div class="card-arrow-bottom-left"></div><div class="card-arrow-bottom-right"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card cursor-pointer h-100 hover-bg-light hover-bg-opacity-5" onclick="selectSuggestion('Explain how licensing works in our app. What is an ERU and how is it calculated?')">
                                    <div class="card-body py-3 px-3">
                                        <h6 class="text-info mb-1"><i class="bi bi-shield-check me-1"></i> Explain ERUs</h6>
                                        <small class="text-muted d-block">What are Elastic Resource Units and how are they counted?</small>
                                    </div>
                                    <div class="card-arrow">
                                        <div class="card-arrow-top-left"></div><div class="card-arrow-top-right"></div>
                                        <div class="card-arrow-bottom-left"></div><div class="card-arrow-bottom-right"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card cursor-pointer h-100 hover-bg-light hover-bg-opacity-5" onclick="selectSuggestion('MSSP costing: How do staff salary overrides and Ceo benefit margin configurations work?')">
                                    <div class="card-body py-3 px-3">
                                        <h6 class="text-success mb-1"><i class="bi bi-wallet2 me-1"></i> Costing Logic</h6>
                                        <small class="text-muted d-block">Explain profit margins and staffing cost configurations.</small>
                                    </div>
                                    <div class="card-arrow">
                                        <div class="card-arrow-top-left"></div><div class="card-arrow-top-right"></div>
                                        <div class="card-arrow-bottom-left"></div><div class="card-arrow-bottom-right"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Thread Message List -->
                    <div id="messageBubbleContainer">
                        @foreach($activeConversation->messages as $msg)
                            @if($msg->role === 'user')
                                <!-- Human Bubble -->
                                <div class="d-flex justify-content-end align-items-start mb-4">
                                    <div class="rounded-3 px-3 py-2 bg-theme bg-opacity-15 text-white mw-75 border border-theme border-opacity-25 chat-bubble">
                                        <p class="mb-0">{!! nl2br(e($msg->content)) !!}</p>
                                    </div>
                                    <div class="ms-2">
                                        <div class="w-32px h-32px rounded-circle bg-secondary bg-opacity-35 text-white fw-bold d-flex align-items-center justify-content-center small" title="User">
                                            U
                                        </div>
                                    </div>
                                </div>
                            @else
                                <!-- AI Bubble -->
                                <div class="d-flex justify-content-start align-items-start mb-4" data-message-id="{{ $msg->id }}">
                                    <div class="me-2">
                                        @if(($msg->agent ?? 'ElasticCostAssistant') === 'RgSocEngineer')
                                            <div class="w-32px h-32px rounded-circle bg-warning bg-opacity-20 text-warning d-flex align-items-center justify-content-center small" title="RG SOC Engineer">
                                                <i class="bi bi-shield-fill-check fs-14px"></i>
                                            </div>
                                        @else
                                            <div class="w-32px h-32px rounded-circle bg-theme bg-opacity-20 text-theme d-flex align-items-center justify-content-center small" title="ElasticCost Assistant">
                                                <i class="bi bi-robot fs-14px"></i>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="rounded-3 px-3 py-2 bg-secondary bg-opacity-15 text-white mw-75 border border-secondary border-opacity-30 chat-bubble">
                                        <div class="small {{ ($msg->agent ?? 'ElasticCostAssistant') === 'RgSocEngineer' ? 'text-warning' : 'text-theme' }} fw-bold mb-1">
                                            {{ ($msg->agent ?? 'ElasticCostAssistant') === 'RgSocEngineer' ? 'RG SOC Engineer Agent' : 'ElasticCost Assistant' }}
                                            @if($msg->meta && ($msg->meta['status'] ?? '') === 'pending')
                                                <span class="pending-indicator ms-2 badge bg-warning text-dark fw-bold" style="font-size:0.7em;"><i class="bi bi-hourglass-split me-1"></i>Working...</span>
                                            @endif
                                        </div>
                                        <div class="chat-bubble-content">
                                            {!! Str::markdown($msg->content) !!}
                                        </div>
                                        @if(($msg->agent ?? 'ElasticCostAssistant') !== 'RgSocEngineer')
                                            <div class="mt-3 border-top border-secondary border-opacity-20 pt-2 d-flex flex-column gap-2">
                                                <div class="d-flex align-items-center gap-1 text-muted" style="font-size: 10px;">
                                                    <i class="bi bi-share text-theme"></i>
                                                    <span>Forward Context to SOC Engineer:</span>
                                                </div>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button type="button" class="btn btn-outline-warning py-0.5 px-2 font-monospace" style="font-size: 9px; line-height: 1.2;" onclick="forwardContext(this, 'validate')">
                                                        <i class="bi bi-shield-fill-check me-0.5"></i> Validate & Update Settings
                                                    </button>
                                                    <button type="button" class="btn btn-outline-success py-0.5 px-2 font-monospace" style="font-size: 9px; line-height: 1.2;" onclick="forwardContext(this, 'create_client')">
                                                        <i class="bi bi-person-plus-fill me-0.5"></i> Create Client
                                                    </button>
                                                    <button type="button" class="btn btn-outline-info py-0.5 px-2 font-monospace" style="font-size: 9px; line-height: 1.2;" onclick="forwardContext(this, 'modify_agents')">
                                                        <i class="bi bi-gear-fill me-0.5"></i> Modify Coverages
                                                    </button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Chat Send Input Form -->
            <div class="p-3 border-top border-secondary border-opacity-25 bg-black bg-opacity-20">
                <form id="chatForm" onsubmit="submitMessage(event)">
                    @csrf
                    <div class="d-flex align-items-center mb-2 gap-3">
                        <span class="text-muted small fw-bold"><i class="bi bi-cpu-fill me-1"></i> Active Agent:</span>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="radio" name="target_agent" id="agentAssistant" value="ElasticCostAssistant" checked>
                            <label class="form-check-label text-theme small cursor-pointer" for="agentAssistant">
                                <i class="bi bi-robot me-1"></i> ElasticCost Assistant
                            </label>
                        </div>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="radio" name="target_agent" id="agentEngineer" value="RgSocEngineer">
                            <label class="form-check-label text-warning small cursor-pointer" for="agentEngineer">
                                <i class="bi bi-shield-fill-check me-1"></i> RG SOC Engineer (Action Tools)
                            </label>
                        </div>
                    </div>
                    <div class="input-group">
                        <input type="text" id="userInput" class="form-control form-control-lg bg-secondary bg-opacity-10 border-secondary border-opacity-25 text-white rounded-3 shadow-none" placeholder="Type a message to the AI Analyst..." required autocomplete="off">
                        <button type="submit" id="sendBtn" class="btn btn-theme px-4 rounded-3 ms-2">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </form>
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
@endsection

@section('scripts')
<script>
    var currentConversationId = "{{ $activeConversation ? $activeConversation->id : '' }}";

    // ──────────────────────────────────────────────
    // Auto-scroll chat area to bottom
    // ──────────────────────────────────────────────
    function scrollToBottom() {
        var chatArea = document.getElementById("chatMessageArea");
        if (chatArea) { chatArea.scrollTop = chatArea.scrollHeight; }
    }

    document.addEventListener("DOMContentLoaded", function() {
        scrollToBottom();
        var userInput = document.getElementById("userInput");
        if (userInput) { userInput.focus(); }

        // Resume polling if there is a pending message in the loaded page
        var pendingBubble = document.querySelector('[data-message-id] .pending-indicator');
        if (pendingBubble) {
            var bubbleEl = pendingBubble.closest('[data-message-id]');
            var messageId = bubbleEl ? bubbleEl.getAttribute('data-message-id') : null;
            if (messageId) {
                startAgentPolling(messageId);
            }
        }
    });

    function selectSuggestion(text) {
        var input = document.getElementById("userInput");
        if (input) { input.value = text; input.focus(); }
    }

    // ──────────────────────────────────────────────
    // SweetAlert2 — Agent Status Banner Polling
    // ──────────────────────────────────────────────
    var agentPollInterval = null;

    function startAgentPolling(messageId) {
        // Show persistent top toast
        Swal.fire({
            toast: true,
            position: 'top',
            icon: 'info',
            title: '🛡️ RG SOC Engineer is working...',
            html: 'Checking for results every 5 seconds. The app remains fully responsive.',
            showConfirmButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            background: '#1a2035',
            color: '#fff',
            iconColor: '#f6c90e',
            customClass: { popup: 'border border-warning border-opacity-50 shadow-lg' },
            didOpen: function() {
                // Update countdown in subtitle
                var secondsLeft = 5;
                var countdownEl = Swal.getHtmlContainer();

                agentPollInterval = setInterval(function() {
                    secondsLeft -= 1;
                    if (countdownEl) {
                        countdownEl.innerHTML = 'Next check in <strong>' + secondsLeft + 's</strong>...';
                    }
                    if (secondsLeft <= 0) {
                        secondsLeft = 5;
                        checkAgentJobStatus(messageId);
                        if (countdownEl) {
                            countdownEl.innerHTML = 'Checking now...';
                        }
                    }
                }, 1000);
            }
        });
    }

    function stopAgentPolling() {
        if (agentPollInterval) {
            clearInterval(agentPollInterval);
            agentPollInterval = null;
        }
        Swal.close();
    }

    function checkAgentJobStatus(messageId) {
        fetch('/api/agent-job-status/' + messageId, {
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.status === 'completed') {
                stopAgentPolling();

                // Update the pending bubble in the chat
                if (data.message) {
                    updatePendingBubble(messageId, data.message);
                }

                Swal.fire({
                    toast: true,
                    position: 'top',
                    icon: 'success',
                    title: '✅ RG SOC Engineer completed!',
                    html: 'Action executed and result saved to the chat.',
                    timer: 6000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    background: '#0d2137',
                    color: '#fff',
                    iconColor: '#22c55e',
                });

            } else if (data.status === 'failed') {
                stopAgentPolling();

                if (data.message) {
                    updatePendingBubble(messageId, data.message);
                }

                Swal.fire({
                    position: 'top',
                    icon: 'error',
                    title: 'RG SOC Engineer failed',
                    text: 'The agent encountered an error. Check the chat for details.',
                    confirmButtonText: 'Dismiss',
                    background: '#1a0a0a',
                    color: '#fff',
                });
            }
            // If still 'pending', do nothing — interval will check again
        })
        .catch(function() {
            // Network error — keep polling silently
        });
    }

    function updatePendingBubble(messageId, message) {
        // Find and replace the pending bubble content
        var bubbleContainer = document.getElementById("messageBubbleContainer");
        if (!bubbleContainer) { return; }

        var pendingBubbles = bubbleContainer.querySelectorAll('[data-message-id="' + messageId + '"]');
        if (pendingBubbles.length > 0) {
            pendingBubbles.forEach(function(el) {
                var contentDiv = el.querySelector('.chat-bubble-content');
                if (contentDiv && message.html) {
                    contentDiv.innerHTML = message.html;
                }
                // Remove pending indicator
                var pendingEl = el.querySelector('.pending-indicator');
                if (pendingEl) { pendingEl.remove(); }
            });
        } else {
            // If no tracked bubble (new page load), append the message bubble
            var isEngMsg = message.agent === 'RgSocEngineer';
            var bubble = `
                <div class="d-flex justify-content-start align-items-start mb-4" data-message-id="${messageId}">
                    <div class="me-2">
                        <div class="w-32px h-32px rounded-circle bg-warning bg-opacity-20 text-warning d-flex align-items-center justify-content-center small" title="RG SOC Engineer">
                            <i class="bi bi-shield-fill-check fs-14px"></i>
                        </div>
                    </div>
                    <div class="rounded-3 px-3 py-2 bg-secondary bg-opacity-15 text-white mw-75 border border-secondary border-opacity-30 chat-bubble">
                        <div class="small text-warning fw-bold mb-1">RG SOC Engineer Agent</div>
                        <div class="chat-bubble-content">${message.html}</div>
                    </div>
                </div>
            `;
            bubbleContainer.insertAdjacentHTML("beforeend", bubble);
        }
        scrollToBottom();
    }

    // ──────────────────────────────────────────────
    // Submit user message
    // ──────────────────────────────────────────────
    function submitMessage(e) {
        if (e) e.preventDefault();

        var input = document.getElementById("userInput");
        var sendBtn = document.getElementById("sendBtn");
        var msgText = input ? input.value.trim() : "";

        if (!msgText) return;

        input.value = "";
        input.disabled = true;
        sendBtn.disabled = true;

        var messageArea = document.getElementById("chatMessageArea");
        var bubbleContainer = document.getElementById("messageBubbleContainer");

        if (!bubbleContainer) {
            messageArea.innerHTML = '<div id="messageBubbleContainer"></div>';
            bubbleContainer = document.getElementById("messageBubbleContainer");
        }

        // Render user message bubble
        var userHtml = `
            <div class="d-flex justify-content-end align-items-start mb-4">
                <div class="rounded-3 px-3 py-2 bg-theme bg-opacity-15 text-white mw-75 border border-theme border-opacity-25 chat-bubble">
                    <p class="mb-0">${escapeHtml(msgText).replace(/\n/g, '<br>')}</p>
                </div>
                <div class="ms-2">
                    <div class="w-32px h-32px rounded-circle bg-secondary bg-opacity-35 text-white fw-bold d-flex align-items-center justify-content-center small">U</div>
                </div>
            </div>
        `;
        bubbleContainer.insertAdjacentHTML("beforeend", userHtml);
        scrollToBottom();

        var agentRadio = document.querySelector('input[name="target_agent"]:checked');
        var selectedAgent = agentRadio ? agentRadio.value : "ElasticCostAssistant";
        var isEngineer = selectedAgent === 'RgSocEngineer';

        // Render AI placeholder bubble
        var aiLoaderId = "ai-loader-" + Date.now();
        var aiLoaderHtml = `
            <div class="d-flex justify-content-start align-items-start mb-4" id="${aiLoaderId}">
                <div class="me-2">
                    <div class="w-32px h-32px rounded-circle ${isEngineer ? 'bg-warning bg-opacity-20 text-warning' : 'bg-theme bg-opacity-20 text-theme'} d-flex align-items-center justify-content-center small">
                        <i class="bi ${isEngineer ? 'bi-shield-fill-check' : 'bi-robot'} fs-14px"></i>
                    </div>
                </div>
                <div class="rounded-3 px-3 py-2 bg-secondary bg-opacity-15 text-white mw-75 border border-secondary border-opacity-30">
                    <div class="d-flex align-items-center gap-2">
                        <div class="spinner-border ${isEngineer ? 'text-warning' : 'text-theme'} spinner-border-sm" role="status"></div>
                        <span class="${isEngineer ? 'text-warning' : 'text-theme'} fw-bold">${isEngineer ? 'Engineer dispatching task...' : 'Assistant is thinking...'}</span>
                    </div>
                </div>
            </div>
        `;
        bubbleContainer.insertAdjacentHTML("beforeend", aiLoaderHtml);
        scrollToBottom();

        var url = currentConversationId
            ? "{{ route('ai-chat.message', ':id') }}".replace(':id', currentConversationId)
            : "{{ route('ai-chat.message') }}";

        fetch(url, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({ message: msgText, agent: selectedAgent })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            var loader = document.getElementById(aiLoaderId);
            if (loader) { loader.remove(); }

            if (data.success) {
                // New conversation — update ID and URL
                if (!currentConversationId && data.conversation_id) {
                    currentConversationId = data.conversation_id;
                    window.history.pushState(null, '', '/ai-chat/' + currentConversationId);
                    window.location.reload();
                    return;
                }

                // Render all messages
                bubbleContainer.innerHTML = "";
                (data.messages || []).forEach(function(m) {
                    bubbleContainer.insertAdjacentHTML("beforeend", buildBubble(m));
                });
                scrollToBottom();

                // If queued — start SweetAlert2 polling
                if (data.queued && data.message_id) {
                    startAgentPolling(data.message_id);
                }

            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'AI Error',
                    text: data.message || 'Failed to query AI model.',
                    background: '#1a0a0a',
                    color: '#fff',
                });
            }

            input.disabled = false;
            sendBtn.disabled = false;
            input.focus();
        })
        .catch(function() {
            var loader = document.getElementById(aiLoaderId);
            if (loader) { loader.remove(); }

            Swal.fire({
                icon: 'error',
                title: 'Connection Failed',
                text: 'API connection failed. Verify your active AI provider/API key is configured and reachable.',
                background: '#1a0a0a',
                color: '#fff',
            });
            input.disabled = false;
            sendBtn.disabled = false;
            input.focus();
        });
    }

    // ──────────────────────────────────────────────
    // Build a chat message bubble from data
    // ──────────────────────────────────────────────
    function buildBubble(m) {
        if (m.role === 'user') {
            return `
                <div class="d-flex justify-content-end align-items-start mb-4">
                    <div class="rounded-3 px-3 py-2 bg-theme bg-opacity-15 text-white mw-75 border border-theme border-opacity-25 chat-bubble">
                        <p class="mb-0">${escapeHtml(m.content).replace(/\n/g, '<br>')}</p>
                    </div>
                    <div class="ms-2">
                        <div class="w-32px h-32px rounded-circle bg-secondary bg-opacity-35 text-white fw-bold d-flex align-items-center justify-content-center small">U</div>
                    </div>
                </div>
            `;
        }

        var isEngMsg = m.agent === 'RgSocEngineer';
        var isPending = m.meta && m.meta.status === 'pending';
        var messageId = m.id || '';

        var forwardControls = '';
        if (!isEngMsg) {
            forwardControls = `
                <div class="mt-3 border-top border-secondary border-opacity-20 pt-2 d-flex flex-column gap-2">
                    <div class="d-flex align-items-center gap-1 text-muted" style="font-size: 10px;">
                        <i class="bi bi-share text-theme"></i>
                        <span>Forward Context to SOC Engineer:</span>
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        <button type="button" class="btn btn-outline-warning py-0.5 px-2 font-monospace" style="font-size: 9px; line-height: 1.2;" onclick="forwardContext(this, 'validate')">
                            <i class="bi bi-shield-fill-check me-0.5"></i> Validate & Update Settings
                        </button>
                        <button type="button" class="btn btn-outline-success py-0.5 px-2 font-monospace" style="font-size: 9px; line-height: 1.2;" onclick="forwardContext(this, 'create_client')">
                            <i class="bi bi-person-plus-fill me-0.5"></i> Create Client
                        </button>
                        <button type="button" class="btn btn-outline-info py-0.5 px-2 font-monospace" style="font-size: 9px; line-height: 1.2;" onclick="forwardContext(this, 'modify_agents')">
                            <i class="bi bi-gear-fill me-0.5"></i> Modify Coverages
                        </button>
                    </div>
                </div>
            `;
        }

        return `
            <div class="d-flex justify-content-start align-items-start mb-4" data-message-id="${messageId}">
                <div class="me-2">
                    <div class="w-32px h-32px rounded-circle ${isEngMsg ? 'bg-warning bg-opacity-20 text-warning' : 'bg-theme bg-opacity-20 text-theme'} d-flex align-items-center justify-content-center small" title="${isEngMsg ? 'RG SOC Engineer' : 'ElasticCost Assistant'}">
                        <i class="bi ${isEngMsg ? 'bi-shield-fill-check' : 'bi-robot'} fs-14px"></i>
                    </div>
                </div>
                <div class="rounded-3 px-3 py-2 bg-secondary bg-opacity-15 text-white mw-75 border border-secondary border-opacity-30 chat-bubble">
                    <div class="small ${isEngMsg ? 'text-warning' : 'text-theme'} fw-bold mb-1">
                        ${isEngMsg ? 'RG SOC Engineer Agent' : 'ElasticCost Assistant'}
                        ${isPending ? '<span class="pending-indicator ms-2 badge bg-warning text-dark fw-bold" style="font-size:0.7em;"><i class="bi bi-hourglass-split me-1"></i>Working...</span>' : ''}
                    </div>
                    <div class="chat-bubble-content">${m.html || escapeHtml(m.content)}</div>
                    ${forwardControls}
                </div>
            </div>
        `;
    }

    // ──────────────────────────────────────────────
    // HTML escape helper
    // ──────────────────────────────────────────────
    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // ──────────────────────────────────────────────
    // Forward message context to SOC Engineer Agent
    // ──────────────────────────────────────────────
    function forwardContext(btn, action) {
        var bubbleEl = btn.closest('.chat-bubble');
        if (!bubbleEl) return;
        var contentDiv = bubbleEl.querySelector('.chat-bubble-content');
        if (!contentDiv) return;
        var messageText = contentDiv.innerText.trim();

        // Select the RG SOC Engineer radio button
        var radioEngineer = document.getElementById("agentEngineer");
        if (radioEngineer) {
            radioEngineer.checked = true;
        }

        // Generate the prefix command based on action type
        var promptPrefix = "";
        if (action === 'validate') {
            promptPrefix = "Review and validate the recommendations in the context below. Executing necessary setting updates:\n\n";
        } else if (action === 'create_client') {
            promptPrefix = "Extract the client details (name, description, device counts) from the context below and create a new client using the CreateClientTool:\n\n";
        } else if (action === 'modify_agents') {
            promptPrefix = "Modify the client asset coverages (SIEM, MDR, EDR) based on the recommendations in the context below:\n\n";
        }

        // Pre-fill user input with the command and the context
        var userInput = document.getElementById("userInput");
        if (userInput) {
            userInput.value = promptPrefix + "[CONTEXT FROM ASSISTANT]:\n" + messageText;
            userInput.focus();
        }

        // Scroll the input area into view smoothly
        userInput.scrollIntoView({ behavior: 'smooth' });
    }
</script>
@endsection

