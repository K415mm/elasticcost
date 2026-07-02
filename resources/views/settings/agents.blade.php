@extends('layouts.app')

@section('title', __('messages.agent_registry') ?: 'AI Agent Registry')

@section('styles')
<style>
    .nav-tabs-v2 .nav-link {
        color: rgba(255, 255, 255, 0.5);
        border: none;
        border-bottom: 2px solid transparent;
        padding: 12px 20px;
        font-weight: 600;
        background: transparent;
        transition: all 0.2s ease-in-out;
    }
    .nav-tabs-v2 .nav-link:hover {
        color: #fff;
    }
    .nav-tabs-v2 .nav-link.active {
        color: var(--bs-theme);
        border-bottom-color: var(--bs-theme);
        background: transparent;
    }
    
    .agent-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .agent-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(var(--bs-theme-rgb), 0.15) !important;
    }

    .flow-diagram-container {
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 30px;
        position: relative;
        min-height: 400px;
    }
    
    .flow-node {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        position: relative;
        z-index: 2;
        transition: all 0.3s ease;
    }
    .flow-node:hover {
        border-color: var(--bs-theme);
        background: rgba(var(--bs-theme-rgb), 0.05);
    }
    
    .flow-node.active-orchestrator {
        border-color: var(--bs-theme);
        box-shadow: 0 0 15px rgba(var(--bs-theme-rgb), 0.2);
    }
    
    .flow-connector {
        position: absolute;
        z-index: 1;
        border: 1px dashed rgba(255, 255, 255, 0.25);
    }
    
    .flow-connector-vertical {
        width: 1px;
    }
    .flow-connector-horizontal {
        height: 1px;
    }

    .flow-arrow-down::after {
        content: "▼";
        position: absolute;
        bottom: -5px;
        left: -5px;
        font-size: 8px;
        color: rgba(255, 255, 255, 0.5);
    }
    
    /* Terminal Console Style */
    .console-panel {
        font-family: 'JetBrains Mono', monospace;
        background: #0d0e12;
        border: 1px solid #1f222e;
        border-radius: 6px;
        color: #a9b2c3;
        padding: 15px;
        min-height: 200px;
        max-height: 400px;
        overflow-y: auto;
    }
    .console-line {
        margin-bottom: 6px;
        line-height: 1.4;
    }
    .console-info { color: #3b82f6; }
    .console-success { color: #10b981; }
    .console-warning { color: #f59e0b; }
    .console-error { color: #ef4444; }
    .console-input { color: #a855f7; }

    .action-badge {
        font-size: 11px;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 4px;
        background-color: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: #fff;
    }
    .action-badge-primary {
        background-color: rgba(59, 130, 246, 0.15);
        border-color: rgba(59, 130, 246, 0.3);
        color: #93c5fd;
    }
    .action-badge-success {
        background-color: rgba(16, 185, 129, 0.15);
        border-color: rgba(16, 185, 129, 0.3);
        color: #a7f3d0;
    }
</style>
@endsection

@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">{{ __('messages.clients') }}</a></li>
    <li class="breadcrumb-item active">AI AGENT REGISTRY & ORCHESTRATION</li>
</ul>

<div class="d-flex align-items-center mb-4">
    <div class="flex-grow-1">
        <h1 class="page-header mb-0">
            AI Agent Registry & Orchestration
            <small class="d-block mt-1">Audit active intelligence profiles, configure cross-agent communication pathways, and execute automated actions.</small>
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

<div class="nav-tabs-container mb-4">
    <ul class="nav nav-tabs nav-tabs-v2">
        <li class="nav-item">
            <a href="#agents-registry" class="nav-link active" data-bs-toggle="tab">
                <i class="bi bi-robot me-1 text-theme"></i> Active Agent Registry
            </a>
        </li>
        <li class="nav-item">
            <a href="#orchestration-flow" class="nav-link" data-bs-toggle="tab">
                <i class="bi bi-diagram-3 me-1 text-theme"></i> Orchestration Flow & Config
            </a>
        </li>
        <li class="nav-item">
            <a href="#sandbox" class="nav-link" data-bs-toggle="tab">
                <i class="bi bi-play-circle me-1 text-theme"></i> Orchestration Sandbox
            </a>
        </li>
    </ul>
</div>

<div class="tab-content">
    
    <!-- TAB 1: ACTIVE AGENT REGISTRY -->
    <div class="tab-pane fade show active" id="agents-registry">
        <div class="row">
            @foreach($agents as $agent)
                <div class="col-xl-6 col-md-12 mb-4">
                    <div class="card h-100 agent-card">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <div>
                                    <h4 class="card-title text-theme mb-1 d-flex align-items-center">
                                        <i class="bi bi-cpu-fill me-2"></i> {{ $agent['name'] }}
                                    </h4>
                                    <span class="badge action-badge-primary mb-2">{{ $agent['role'] }}</span>
                                </div>
                                <span class="badge bg-secondary bg-opacity-25 border border-secondary border-opacity-30 text-white font-monospace small px-2 py-1">
                                    {{ class_basename($agent['class']) }}
                                </span>
                            </div>
                            
                            <p class="text-muted small mb-3 flex-grow-1" style="min-height: 60px;">
                                {{ $agent['description'] }}
                            </p>
                            
                            <div class="mb-3">
                                <label class="text-white small fw-bold d-block mb-1">Active Backend Infrastructure</label>
                                <div class="bg-black bg-opacity-30 border border-secondary border-opacity-20 p-2 rounded text-muted font-monospace small" style="font-size: 11px;">
                                    <i class="bi bi-hdd-network me-1 text-theme"></i> {{ $agent['backend'] }}
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="text-white small fw-bold d-block mb-1">Key Functions & Capabilities</label>
                                <ul class="list-unstyled mb-0 row g-2">
                                    @foreach($agent['capabilities'] as $cap)
                                        <li class="col-md-6 small text-muted d-flex align-items-center gap-2">
                                            <i class="bi bi-check-circle-fill text-theme small"></i>
                                            <span>{{ $cap }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            
                            <div class="border-top border-secondary border-opacity-30 pt-3 mt-3">
                                <div class="row">
                                    <div class="col-6">
                                        <label class="text-white small fw-bold d-block mb-1">Sub-Agents & Tools</label>
                                        <div class="d-flex flex-wrap gap-1">
                                            @forelse($agent['tools'] as $tool)
                                                <span class="badge bg-dark bg-opacity-50 border border-secondary border-opacity-30 text-white font-monospace text-xs" style="font-size: 10px;">
                                                    <i class="bi bi-link-45deg me-0.5"></i> {{ $tool }}
                                                </span>
                                            @empty
                                                <span class="text-muted small italic">None</span>
                                            @endforelse
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="text-white small fw-bold d-block mb-1">Active Middleware</label>
                                        <div class="d-flex flex-wrap gap-1">
                                            @forelse($agent['middleware'] as $mw)
                                                <span class="badge bg-dark bg-opacity-50 border border-secondary border-opacity-30 text-white font-monospace text-xs" style="font-size: 10px;">
                                                    <i class="bi bi-shield-check me-0.5"></i> {{ $mw }}
                                                </span>
                                            @empty
                                                <span class="text-muted small italic">None</span>
                                            @endforelse
                                        </div>
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
            @endforeach
        </div>
    </div>
    
    <!-- TAB 2: ORCHESTRATION FLOW & CONFIG -->
    <div class="tab-pane fade" id="orchestration-flow">
        <div class="row">
            <!-- Configuration Settings Form -->
            <div class="col-xl-5 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title text-theme mb-3">
                            <i class="bi bi-sliders2-vertical me-2"></i> Orchestration Parameters
                        </h5>
                        <p class="text-muted small mb-4">
                            Configure how agents interact, classify intents, and route processes. These settings are persisted globally in the system configurations.
                        </p>
                        
                        <form action="{{ route('settings.agents.config') }}" method="POST">
                            @csrf
                            <div class="mb-4">
                                <label class="form-label text-white small">Multi-Agent Orchestration Mode</label>
                                <select name="ai_orchestration_mode" class="form-select">
                                    <option value="router-executor" {{ $orchestrationMode === 'router-executor' ? 'selected' : '' }}>
                                        Router-Executor Pipeline (Classify first, then route)
                                    </option>
                                    <option value="linear" {{ $orchestrationMode === 'linear' ? 'selected' : '' }}>
                                        Linear Sequential Chain (Sequential execution flow)
                                    </option>
                                    <option value="autonomous" {{ $orchestrationMode === 'autonomous' ? 'selected' : '' }}>
                                        Autonomous Routing (Agent resolves next hop dynamically)
                                    </option>
                                </select>
                                <div class="small text-muted mt-1" style="font-size: 10px;">
                                    The active paradigm for managing multi-hop agent requests (e.g. routing chat messages).
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input type="checkbox" name="ai_delegation_enabled" id="aiDelegationEnabled" value="1" class="form-check-input" {{ $delegationEnabled ? 'checked' : '' }}>
                                    <label class="form-check-label text-white small" for="aiDelegationEnabled">Enable Dynamic Agent Delegation</label>
                                </div>
                                <div class="small text-muted mt-1" style="font-size: 10px;">
                                    Allow agents to instantiate other agents as dynamic tool actions during loops.
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label text-white small">Maximum Delegation Hops</label>
                                <input type="number" name="ai_max_delegation_hops" class="form-control" value="{{ $maxDelegationHops }}" min="1" max="10">
                                <div class="small text-muted mt-1" style="font-size: 10px;">
                                    Llimit the execution loop to prevent recursive agent-to-agent loops.
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-outline-theme w-100 py-2 mt-2">
                                <i class="bi bi-save me-1"></i> Save Orchestration Config
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
            
            <!-- Flow Diagram Visualizer -->
            <div class="col-xl-7 mb-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title text-theme mb-3">
                            <i class="bi bi-diagram-3 me-2"></i> Agent Communication Pathway
                        </h5>
                        <p class="text-muted small mb-4">
                            Predefined delegation architecture mapping showing how client requests flow to analyzers, routers, and database executors.
                        </p>
                        
                        <div class="flex-grow-1 d-flex align-items-center justify-content-center">
                            <div class="flow-diagram-container w-100" style="max-width: 600px;">
                                <!-- Row 1: Central Assistant -->
                                <div class="row justify-content-center mb-5">
                                    <div class="col-6">
                                        <div class="flow-node active-orchestrator">
                                            <div class="fw-bold text-theme"><i class="bi bi-chat-dots-fill me-1"></i> ElasticCost Assistant</div>
                                            <small class="text-muted font-monospace" style="font-size: 9px;">Central Chat Entrypoint</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Connectors from Assistant -->
                                <div class="flow-connector flow-connector-vertical" style="top: 75px; left: 50%; height: 40px;"></div>
                                <div class="flow-connector flow-connector-horizontal" style="top: 115px; left: 25%; width: 50%;"></div>
                                <div class="flow-connector flow-connector-vertical" style="top: 115px; left: 25%; height: 30px;"></div>
                                <div class="flow-connector flow-connector-vertical" style="top: 115px; left: 75%; height: 30px;"></div>
                                
                                <!-- Row 2: Analysts & Routers -->
                                <div class="row justify-content-between mb-5">
                                    <div class="col-5">
                                        <div class="flow-node">
                                            <div class="fw-bold text-info"><i class="bi bi-journal-text me-1"></i> Specialised Analysts</div>
                                            <small class="text-muted font-monospace" style="font-size: 9px;">Sizing & Proposal Critique</small>
                                            <div class="mt-1 d-flex justify-content-center gap-1">
                                                <span class="badge bg-secondary bg-opacity-20 text-white font-monospace" style="font-size: 8px;">SizingRegulator</span>
                                                <span class="badge bg-secondary bg-opacity-20 text-white font-monospace" style="font-size: 8px;">OfferAnalyst</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-5">
                                        <div class="flow-node">
                                            <div class="fw-bold text-warning"><i class="bi bi-shield-lock-fill me-1"></i> RG SOC Engineer</div>
                                            <small class="text-muted font-monospace" style="font-size: 9px;">Intent Classifier (Router)</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Connectors from SOC Router to Executor -->
                                <div class="flow-connector flow-connector-vertical" style="top: 195px; left: 75%; height: 50px;"></div>
                                
                                <!-- Row 3: Executor -->
                                <div class="row justify-content-end">
                                    <div class="col-5">
                                        <div class="flow-node">
                                            <div class="fw-bold text-success"><i class="bi bi-database-fill-gear me-1"></i> SOC Engineer Main</div>
                                            <small class="text-muted font-monospace" style="font-size: 9px;">Tool & Db Action Loop</small>
                                            <div class="mt-1">
                                                <span class="badge bg-dark bg-opacity-50 text-white font-monospace" style="font-size: 8px; border: 1px solid rgba(25,135,84,0.3);"><i class="bi bi-tools"></i> 7 Active Tools</span>
                                            </div>
                                        </div>
                                    </div>
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
        </div>
    </div>
    
    <!-- TAB 3: ORCHESTRATION SANDBOX -->
    <div class="tab-pane fade" id="sandbox">
        <div class="row">
            <!-- Sandbox Control Panel -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title text-theme mb-3">
                            <i class="bi bi-play-circle me-2"></i> Simulation Sandbox
                        </h5>
                        <p class="text-muted small mb-4">
                            Choose a client sizing scope, trigger a specialized critique analyst, and forward its output to the system executor.
                        </p>
                        
                        <div class="mb-3">
                            <label class="form-label text-white small">1. Select Target Client</label>
                            <select id="sandboxClientSelect" class="form-select">
                                <option value="">-- Choose Client Profile --</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-white small">2. Select Scenario Template</label>
                            <select id="sandboxScenarioSelect" class="form-select">
                                <option value="">-- Choose Scenario --</option>
                                @foreach($scenarios as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->workload_profile }})</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-white small">3. Select Analyst Agent</label>
                            <select id="sandboxAgentSelect" class="form-select">
                                <option value="SizingRegulator">Sizing Regulator (Elasticsearch Architecture)</option>
                                <option value="OfferAnalyst">Offer Analyst (SOC Pricing & Margins)</option>
                            </select>
                        </div>

                        <div class="mb-4 bg-dark bg-opacity-25 border border-secondary border-opacity-20 rounded p-2.5">
                            <div class="form-check form-switch d-flex align-items-center gap-2">
                                <input type="checkbox" id="sandboxSimulationToggle" value="1" class="form-check-input mt-0" checked style="cursor: pointer;">
                                <label class="form-check-label text-white small fw-bold mb-0" for="sandboxSimulationToggle" style="cursor: pointer;">
                                    Simulation Mode (Fake AI)
                                </label>
                            </div>
                            <div class="text-muted mt-1" style="font-size: 10px; line-height: 1.3;">
                                Bypasses LLM API calls and uses high-fidelity simulation. Enable this if your local environment is offline or times out.
                            </div>
                        </div>
                        
                        <button type="button" id="btnRunAnalysis" class="btn btn-theme w-100 py-2">
                            <i class="bi bi-lightning-charge-fill me-1"></i> Step 1: Run Agent Analysis
                        </button>
                    </div>
                    <div class="card-arrow">
                        <div class="card-arrow-top-left"></div>
                        <div class="card-arrow-top-right"></div>
                        <div class="card-arrow-bottom-left"></div>
                        <div class="card-arrow-bottom-right"></div>
                    </div>
                </div>
            </div>
            
            <!-- Analysis Terminal Output -->
            <div class="col-lg-8 mb-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column" style="min-height: 450px;">
                        <h5 class="card-title text-theme mb-3">
                            <i class="bi bi-terminal me-2"></i> Agent Output & Orchestrator Terminal
                        </h5>
                        
                        <!-- Terminal View -->
                        <div class="console-panel flex-grow-1 mb-3" id="sandboxConsole">
                            <div class="console-line text-muted">&gt; Ready. Choose settings and click "Run Agent Analysis" above to initiate.</div>
                        </div>
                        
                        <!-- Action Section (Initially hidden) -->
                        <div id="sandboxActionContainer" class="border-top border-secondary border-opacity-35 pt-3 mt-2" style="display: none;">
                            <div class="bg-black bg-opacity-25 border border-success border-opacity-25 p-3 rounded mb-3">
                                <h6 class="text-success mb-1 d-flex align-items-center">
                                    <i class="bi bi-shuffle me-2"></i> Context-To-Action Orchestration
                                </h6>
                                <p class="text-muted small mb-3">
                                    The analyst has generated recommendations. You can now pass this analysis output to the **RG SOC Engineer** as context, instructing it to programmatically align the system settings/inventory.
                                </p>
                                
                                <div class="mb-3">
                                    <label class="form-label text-white small">SOC Engineer Action Instruction</label>
                                    <input type="text" id="actionInstruction" class="form-control mono-cell" value="Review the critique context. Update any global pricing configuration, client fleet agent settings, or device asset counts mentioned.">
                                </div>
                                
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" id="btnRunOrchestrate" class="btn btn-outline-success px-4 py-2">
                                        <i class="bi bi-play-fill me-1"></i> Step 2: Forward Context & Execute Actions
                                    </button>
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
        </div>
    </div>
    
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var btnRunAnalysis = document.getElementById("btnRunAnalysis");
        var btnRunOrchestrate = document.getElementById("btnRunOrchestrate");
        var sandboxConsole = document.getElementById("sandboxConsole");
        var sandboxActionContainer = document.getElementById("sandboxActionContainer");
        
        var selectedClient = null;
        var selectedScenario = null;
        var selectedAgent = null;
        var lastAgentOutput = "";
        
        function appendConsole(text, type = 'info') {
            var cssClass = 'console-info';
            var prefix = '[INFO]';
            if (type === 'success') { cssClass = 'console-success'; prefix = '[SUCCESS]'; }
            if (type === 'warning') { cssClass = 'console-warning'; prefix = '[WARNING]'; }
            if (type === 'error') { cssClass = 'console-error'; prefix = '[ERROR]'; }
            if (type === 'input') { cssClass = 'console-input'; prefix = '&gt;'; }
            if (type === 'raw') { cssClass = ''; prefix = ''; }
            
            var line = document.createElement("div");
            line.className = "console-line " + cssClass;
            line.innerHTML = prefix + " " + text;
            sandboxConsole.appendChild(line);
            sandboxConsole.scrollTop = sandboxConsole.scrollHeight;
        }

        btnRunAnalysis.addEventListener("click", function() {
            var clientSelect = document.getElementById("sandboxClientSelect");
            var scenarioSelect = document.getElementById("sandboxScenarioSelect");
            var agentSelect = document.getElementById("sandboxAgentSelect");
            
            selectedClient = clientSelect.value;
            selectedScenario = scenarioSelect.value;
            selectedAgent = agentSelect.value;
            
            if (!selectedClient || !selectedScenario) {
                alert("Please select both a client and a scenario template first!");
                return;
            }
            
            // Lock UI
            btnRunAnalysis.disabled = true;
            sandboxActionContainer.style.display = "none";
            sandboxConsole.innerHTML = "";
            
            appendConsole("Initializing sandbox simulation environment...", "info");
            appendConsole(`Target Agent: ${selectedAgent}`, "info");
            appendConsole("Loading client asset matrix and scenario tier thresholds...", "info");
            
            var simulationToggle = document.getElementById("sandboxSimulationToggle");
            var params = {
                client_id: selectedClient,
                scenario_id: selectedScenario,
                agent: selectedAgent,
                simulation: simulationToggle.checked ? 1 : 0,
                _token: "{{ csrf_token() }}"
            };
            
            appendConsole("Executing agent pipeline prompt (calling active LLM)...", "input");
            
            fetch("{{ route('settings.agents.analyze') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify(params)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    lastAgentOutput = data.output;
                    appendConsole("Agent loop terminated successfully.", "success");
                    appendConsole("----------------------------------------------", "raw");
                    appendConsole("<strong>Critique Report Output:</strong>", "success");
                    
                    var outDiv = document.createElement("div");
                    outDiv.className = "p-3 bg-dark bg-opacity-20 border border-secondary border-opacity-20 rounded my-2 text-white font-monospace text-xs";
                    outDiv.style.maxHeight = "300px";
                    outDiv.style.overflowY = "auto";
                    outDiv.innerHTML = data.html;
                    sandboxConsole.appendChild(outDiv);
                    
                    sandboxActionContainer.style.display = "block";
                    sandboxConsole.scrollTop = sandboxConsole.scrollHeight;
                } else {
                    appendConsole(data.message || "An unknown error occurred during LLM prompt.", "error");
                }
            })
            .catch(function(err) {
                appendConsole(err.message || err, "error");
            })
            .finally(function() {
                btnRunAnalysis.disabled = false;
            });
        });

        btnRunOrchestrate.addEventListener("click", function() {
            var instructionInput = document.getElementById("actionInstruction");
            var instruction = instructionInput.value.trim();
            
            if (!lastAgentOutput) {
                alert("No agent context available to execute. Run the analysis first!");
                return;
            }
            
            btnRunOrchestrate.disabled = true;
            appendConsole("----------------------------------------------", "raw");
            appendConsole("Forwarding analyst critique as context to execution agent...", "info");
            appendConsole("Agent loaded: App\\Ai\\Agents\\RgSocEngineer (Orchestrator)", "info");
            appendConsole("Instruction: " + instruction, "input");
            appendConsole("Reviewing context. Executing policy guards...", "info");
            
            var simulationToggle = document.getElementById("sandboxSimulationToggle");
            var params = {
                context: lastAgentOutput,
                instruction: instruction,
                simulation: simulationToggle.checked ? 1 : 0,
                _token: "{{ csrf_token() }}"
            };
            
            fetch("{{ route('settings.agents.orchestrate') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify(params)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    appendConsole("RG SOC Engineer completed task successfully.", "success");
                    
                    // Display Agent Explanation
                    var explainDiv = document.createElement("div");
                    explainDiv.className = "p-3 bg-success bg-opacity-5 border border-success border-opacity-20 rounded my-2 text-white font-monospace text-xs";
                    explainDiv.innerHTML = "<strong>SOC Engineer explanation:</strong><br>" + data.html;
                    sandboxConsole.appendChild(explainDiv);
                    
                    // Display executed tools
                    if (data.executed_tools && data.executed_tools.length > 0) {
                        appendConsole("Executed system mutations:", "success");
                        data.executed_tools.forEach(function(tool) {
                            var toolArgs = JSON.stringify(tool.arguments);
                            appendConsole(`⚡ Tool: <span class="text-warning">${tool.name}</span> | Arguments: <code class="text-theme">${toolArgs}</code>`, "success");
                        });
                    } else {
                        appendConsole("No tool mutations were required based on the context.", "warning");
                    }
                    
                    sandboxConsole.scrollTop = sandboxConsole.scrollHeight;
                } else {
                    appendConsole(data.message || "An error occurred during orchestrated execution.", "error");
                }
            })
            .catch(function(err) {
                appendConsole(err.message || err, "error");
            })
            .finally(function() {
                btnRunOrchestrate.disabled = false;
            });
        });
    });
</script>
@endsection
