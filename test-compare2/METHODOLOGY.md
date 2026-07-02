# phpkaiharness Comparison Test — Methodology & Architecture

## Overview

This document describes the testing methodology, file structure, execution flow, and data capture mechanisms used in the phpkaiharness comparison test suite. The goal is to measure the value of the phpkaiharness cognitive architecture by comparing three execution modes against the same set of prompts.

---

## Test Methodology

### Three Execution Modes

| Mode | Name | Description |
|---|---|---|
| **A1** | Direct API | Raw HTTP call to Qwen Cloud `/v1/chat/completions` endpoint. No harness, no AgentLoop, no tools, no pipeline. System prompt = agent instructions, user prompt = raw test prompt. |
| **A2** | Loop (no features) | `AgentLoop` with all `feature_graph` nodes disabled (cache, compaction, budget, compression, failover, guardrails, ontology, quantum, optimizer, draft verification). Tools are attached to the registry but the model decides whether to call them. |
| **B** | Full Harness | Real application flow with all phpkaiharness features enabled. RgSocEngineer uses `queue()` → Horizon job → DB polling. ElasticCostAssistant uses `prompt()` → `AgentLoop` directly. |

### Execution Order

Tests run in order: **B → A2 → A1**. This ensures the monitor DB captures B mode sessions first. Cache and quantum memory are cleared between modes to prevent cross-contamination.

### Test Dataset

**20 prompts** defined in `TestDataset::all()`, split across two agents:

- **ElasticCostAssistant (10 prompts)**: Elasticsearch sizing, costing, and best-practice questions. Languages: English, French, Tunisian Arabic. None expect tool calls.
- **RgSocEngineer (10 prompts)**: Database queries, updates, and client creation. Languages: English, French, Tunisian Arabic. All expect tool calls.

### Total Executions

20 prompts × 3 modes = **60 executions** per test run.

---

## File Structure

### Core Test Files

```
app/Services/TestCompare/
├── TestDataset.php              # 20 test prompts (10 ElasticCost + 10 RG SOC)
├── TestRunner.php               # Orchestrates execution across 3 modes
├── TestProbe.php                # Instrumentation probe — captures metrics per request
└── TestCompareReportGenerator.php  # Generates Markdown comparison report
```

### Command

```
app/Console/Commands/
└── TestPhpkaiharnessCommand.php  # Artisan command: php artisan test:phpkaiharness
```

### phpkaiharness Package (monitoring & execution)

```
packages/phpkaiharness/src/
├── Core/
│   └── AgentLoop.php            # Agent execution loop — calls LLM, executes tools, iterates
├── Llm/
│   └── LaravelAiClient.php      # LLM client — routes to QwenClient or Chat Completions API
├── Monitor/
│   └── SqliteMonitorStore.php   # SQLite analytics store (sessions, LLM calls, tool calls)
└── Http/Controllers/
    └── HarnessTelemetryController.php  # Telemetry dashboard (reads from SqliteMonitorStore)
```

### Agent Files (application layer)

```
app/Ai/Agents/
├── ElasticCostAssistant.php     # Knowledge-based ES sizing/costing agent (no tools)
├── RgSocEngineer.php            # Router agent — classifies intent, routes to RgSocEngineerMain or chat
└── RgSocEngineerMain.php        # Action executor — has 7 tools for DB operations
```

### Output Files

```
test-compare2/
├── comparison-report.md         # Human-readable Markdown report
├── comparison-summary.json      # Machine-readable aggregate metrics
└── traces/
    ├── B-full-harness/
    │   ├── request-01-elasticcostassistant.json
    │   ├── ...
    │   └── request-20-rgsocengineer.json
    ├── A2-loop-no-features/
    │   └── ...
    └── A1-direct-api/
        └── ...
```

---

## Execution Flow per Mode

### Mode A1 — Direct API

```
TestRunner::runDirectApi()
  │
  ├── Create TestProbe (starts timer)
  ├── Build HTTP payload:
  │     model: qwen3.5-27b
  │     messages: [system: agent.instructions(), user: prompt]
  │     max_tokens: 12000
  │     enable_thinking: true (for qwen3 models)
  │     temperature: 0.7, top_p: 0.8
  │
  ├── POST to Qwen Cloud API (/v1/chat/completions)
  ├── Parse response: choices[0].message.content
  ├── stripThinking() — remove <think>...</think> tags
  ├── probe.finish(response)
  └── return probe
```

**What is captured**: latency, response text, response length, estimated tokens (chars/4).
**What is NOT captured**: tool calls, pipeline stages, iterations, real token usage from API.

### Mode A2 — Loop (no features)

```
TestRunner::runLoopNoFeatures()
  │
  ├── Disable all feature_graph nodes via Config::set()
  ├── Disable: cache, compaction, budget, compression, failover
  │
  ├── executeViaAgentLoop()
  │     ├── Create agent instance (RgSocEngineerMain or ElasticCostAssistant)
  │     ├── Create LaravelAiClient (routes to QwenClient for qwen provider)
  │     ├── Create ToolRegistry — attach all agent tools via LaravelToolAdapter
  │     ├── Create AgentLoop(llmClient, registry, systemPrompt, model, maxIterations=5)
  │     ├── Attach event listeners (LlmCallStarted/Finished, ToolCallStarted/Finished)
  │     │
  │     ├── loop->run(prompt, history, sessionId, analytics)
  │     │     ├── Iteration 1: LLM call → response
  │     │     ├── If tool_calls in response: execute each tool, add results to history
  │     │     ├── Iteration 2: LLM call with tool results → response
  │     │     └── Repeat until no tool calls or maxIterations reached
  │     │
  │     ├── Extract tool calls from loop->getExecutedToolCalls() (fallback)
  │     ├── stripThinking()
  │     └── probe.finish(response)
  │
  └── Restore original config
```

**What is captured**: latency, response text, tool calls (via events + AgentLoop fallback), iterations, LLM calls, estimated tokens.
**What is NOT captured**: pipeline stages (features disabled), cache hits, quantum memory, context injection.

### Mode B — Full Harness

**For ElasticCostAssistant (synchronous):**

```
TestRunner::runFullHarness()
  │
  ├── Enable ALL feature_graph nodes via Config::set()
  │     draft_verification, prompt_middleware, model_optimizer, ontology_injection,
  │     semantic_cache, context_compactor, guardrails, cognitive_memory, quantum_harness
  │
  ├── Attach event listeners
  ├── Create ElasticCostAssistant agent
  │
  ├── executeViaAgentLoop(probe, data, withTools=true, skipListeners=true)
  │     ├── Same as A2 but with features enabled in config
  │     ├── AgentLoop may use semantic cache, context compactor, guardrails
  │     └── Tools attached but ElasticCostAssistant has no tools → 0 tool calls
  │
  └── return probe
```

**For RgSocEngineer (Horizon queue — real app flow):**

```
TestRunner::runFullHarness()
  │
  ├── Enable ALL feature_graph nodes
  ├── Create RgSocEngineer agent (router)
  │
  ├── Create AgentConversation + user message + pending assistant message in DB
  │
  ├── agent->queue(prompt, [], lightProvider, lightModel)
  │     ├── RgSocEngineer::prompt() runs INSIDE Horizon worker:
  │     │     ├── Keyword classification (fast-path) or LLM router classification
  │     │     ├── If requires_action:
  │     │     │     ├── Create RgSocEngineerMain
  │     │     │     ├── Create AgentLoop with tools registry
  │     │     │     ├── loop->run() — LLM call → tool execution → iterate
  │     │     │     ├── Get executedToolCalls from AgentLoop
  │     │     │     └── Return AgentResponse with toolCalls
  │     │     └── If chat only: return router response
  │     │
  │     ├── .then() callback: update DB message with response + tool_calls in meta
  │     └── .catch() callback: update DB message with error
  │
  ├── Poll DB message every 5s (up to 300s)
  │     ├── sleep(5)
  │     ├── Check message meta status: pending → completed → failed
  │     └── Record horizon_poll stage in probe
  │
  ├── On completion:
  │     ├── Extract tool calls from conversation message meta
  │     ├── stripThinking()
  │     ├── Adjust latency: subtract polling sleep time (pollCount × 5s)
  │     └── probe.finish(response)
  │
  └── return probe
```

**What is captured**: latency (adjusted for polling), response text, tool calls (from message meta + monitor DB enrichment), pipeline stages (horizon_dispatch, horizon_poll), iterations, LLM calls.
**Additional data in monitor DB**: session method (fast-path-keyword / executor-loop), total_duration_ms, iterations, all harness_details (tool_call, llm_call, guardrail, rate_limit, compaction, quantum_ingest, quantum, optimizer, ontology, draft_verification, cache).

---

## Data Capture Mechanisms

### TestProbe (per-request instrumentation)

Each test request gets a `TestProbe` instance that captures:

| Field | Source | Description |
|---|---|---|
| `startTime` / `endTime` / `latencyMs` | `microtime(true)` | Wall-clock duration |
| `model` / `provider` | `AiConfigHelper::configure()` | Resolved from DB settings |
| `originalSystemPrompt` | `agent->instructions()` | Agent's system prompt |
| `optimizedSystemPrompt` | Same (no optimization in test) | Post-pipeline system prompt |
| `toolCalls` | Event listeners + `AgentLoop::getExecutedToolCalls()` + message meta | Tool name, arguments, result preview |
| `pipelineStages` | Event listeners (`LlmCallStarted`, `ToolCallFinished`, etc.) | Stage name, status, detail |
| `contextInjected` | Not currently populated | Would show ontology/quantum context |
| `quantumMemoryNodes` | Not currently populated | Would show retrieved memory anchors |
| `cacheHit` / `cacheKey` | Not currently populated | Semantic cache status |
| `promptTokens` / `completionTokens` | `estimateTokens()` — chars ÷ 4 | Rough estimate (not real API usage) |
| `iterations` | `count(history)` or monitor DB | AgentLoop iterations |
| `llmCalls` | Event listener counter | LLM API calls made |
| `success` / `errors` | Exception handling | Whether request succeeded |

### Event Listeners (B and A2 modes)

`TestRunner::attachEventListeners()` registers Laravel event listeners:

| Event | Captures |
|---|---|
| `AgentStarted` | Pipeline stage: agent_started |
| `LlmCallStarted` | Pipeline stage: llm_call/started, increment llmCalls |
| `LlmCallFinished` | Pipeline stage: llm_call/finished with duration |
| `ToolCallStarted` | Pipeline stage: tool_call/started with tool name |
| `ToolCallFinished` | `probe->recordToolCall()` + pipeline stage |
| `AgentFinished` | Pipeline stage: agent_finished |

**Important**: For B mode RgSocEngineer, events fire **inside the Horizon worker process**, not in the TestRunner process. The TestRunner's event listeners do NOT capture them. Tool calls are instead extracted from the conversation message's `meta['tool_calls']` field, which is saved by the `then()` callback in the Horizon worker.

### SqliteMonitorStore (B mode only)

The phpkaiharness monitor SQLite database (`storage/app/phpkaiharness/monitor.db`) captures detailed execution data from within the Horizon worker:

**`harness_sessions` table**:
- `id`: Session ID (e.g., `int_6a405aa37b6ad2.95519388`)
- `prompt`: The user prompt
- `response`: Final response text
- `method`: Execution method (`fast-path-keyword` or `executor-loop`)
- `iterations`: Number of AgentLoop iterations
- `total_duration_ms`: Actual agent execution time (excluding TestRunner polling)

**`harness_details` table**:
- `type`: Detail type (`tool_call`, `llm_call`, `guardrail`, `rate_limit`, `compaction`, `quantum_ingest`, `quantum`, `optimizer`, `ontology`, `draft_verification`, `cache`)
- `name`: Tool name or model name
- `payload`: Input arguments (JSON)
- `response`: Output result (JSON)
- `duration_ms`: Execution time
- `tokens_prompt` / `tokens_completion`: Token usage from API

### Post-Test Enrichment

After the test run, traces are enriched from the monitor DB:

1. **Tool calls**: Matched by normalized prompt text → `harness_details` where `type='tool_call'`
2. **Latency adjustment**: B mode RgSocEngineer latency corrected using `harness_sessions.total_duration_ms` (actual agent time, not polling overhead)
3. **Pipeline stages**: Populated from `harness_details` for B mode traces
4. **Token counts**: Updated from `harness_details` where `type='llm_call'` (real API usage, not estimates)

---

## Key Configuration

### AI Provider

- **Provider**: Qwen Cloud (`qwen`)
- **Model**: `qwen3.5-27b`
- **API**: DashScope compatible mode (`/v1/chat/completions`)
- **max_tokens**: 12000 (increased from 4096 to accommodate Qwen3 thinking tokens)
- **enable_thinking**: `true` (required for qwen3 models to produce proper responses)
- **temperature**: 0.7, **top_p**: 0.8, **repetition_penalty**: 1.1

### phpkaiharness Features (B mode)

| Feature | Config Key | Purpose |
|---|---|---|
| Draft Verification | `harness.feature_graph.nodes.draft_verification` | Verify agent draft before returning |
| Prompt Middleware | `harness.feature_graph.nodes.prompt_middleware` | Telemetry + policy guardrails |
| Model Optimizer | `harness.feature_graph.nodes.model_optimizer` | Optimize prompt for model |
| Ontology Injection | `harness.feature_graph.nodes.ontology_injection` | Inject DB schema context |
| Semantic Cache | `harness.feature_graph.nodes.semantic_cache` | Cache similar prompts |
| Context Compactor | `harness.feature_graph.nodes.context_compactor` | Compact long conversations |
| Guardrails | `harness.feature_graph.nodes.guardrails` | Safety checks on tool calls |
| Cognitive Memory | `harness.feature_graph.nodes.cognitive_memory` | Memory-aware responses |
| Quantum Harness | `harness.feature_graph.nodes.quantum_harness` | Quantum memory retrieval |

### Infrastructure

- **Horizon**: Queue system running in Kali WSL (`php artisan horizon`)
- **Redis**: Queue backend (`127.0.0.1:6379` inside WSL)
- **SQLite**: All phpkaiharness data stores use SQLite (monitor, quantum memory, semantic cache)
- **Path normalization**: Windows paths (e.g., `S:\elasticcost\...`) are translated to WSL mount paths (`/mnt/s/elasticcost/...`) when running in Linux/WSL

---

## Scripts Used

### Test Execution

```bash
# Full test suite (all 3 modes, 60 executions)
php artisan test:phpkaiharness --run --dir=test-compare2

# Single mode only (loads existing traces for other modes)
php artisan test:phpkaiharness --run --mode=B-full-harness --dir=test-compare2

# Generate report from existing traces only
php artisan test:phpkaiharness --report-only --dir=test-compare2
```

### Pre-Run Cleanup

```bash
# Clear test output
Remove-Item 'test-compare2\*' -Recurse -Force

# Clear application DB (test data)
php clear_db.php

# Clear Redis (Horizon queues)
wsl -d kali-linux -- bash -lc "redis-cli flushall"

# Clear Laravel cache
php artisan optimize:clear

# Restart Horizon in WSL (picks up code changes)
wsl -d kali-linux -- bash -lc "cd /mnt/s/elasticcost && php artisan horizon:terminate; sleep 3; php artisan horizon:clear; php artisan optimize:clear; nohup php artisan horizon > /tmp/horizon.log 2>&1 &"
```

### Post-Test Enrichment

A PHP script was used to enrich existing traces from the monitor SQLite DB:
- Matched traces to monitor sessions by normalized prompt text
- Injected tool calls from `harness_details` (type=`tool_call`)
- Adjusted B mode latency using `harness_sessions.total_duration_ms`
- Regenerated `comparison-summary.json` and `comparison-report.md`

### Code Formatting

```bash
vendor\bin\pint --dirty --format agent
```

---

## Known Limitations

1. **Token counts are estimates**: A1 and A2 modes use `strlen(response) / 4` as a rough token estimate. Only B mode gets real token usage from the monitor DB (via `harness_details.tokens_prompt` / `tokens_completion`).

2. **Tool call capture for B mode RgSocEngineer**: Event listeners fire inside the Horizon worker, not in the TestRunner process. Tool calls are captured via:
   - `AgentLoop::getExecutedToolCalls()` → `AgentResponse::toolCalls` → message `meta['tool_calls']` → `extractToolCallsFromConversation()`
   - Post-test enrichment from monitor DB `harness_details` table

3. **Latency measurement for B mode RgSocEngineer**: The TestRunner polls DB every 5s, adding 15-40s of sleep time. This is subtracted post-hoc using `pollCount × 5s`, and further corrected using `monitor.total_duration_ms` during enrichment.

4. **ElasticCostAssistant in B mode**: Runs synchronously via `AgentLoop` (not queued). The harness features are enabled in config but `ElasticCostAssistant` has no tools, so tool calls are always 0. Pipeline stages are also minimal because the agent doesn't trigger the executor loop.

5. **A2 mode tool calls**: Tools are attached to the registry, but the model (qwen3.5-27b) did not make any tool calls in A2 mode across all 20 prompts. The harness's router/classifier (in B mode) is what triggers tool usage — the model alone doesn't decide to use tools.

6. **3 B mode failures**: Requests #15, #18, #19 (RgSocEngineer, French/Tunisian prompts) failed with `'messages' must contain the word 'json'` — a Qwen API error related to tool result formatting. A1 and A2 modes succeeded for the same prompts.

7. **Semantic cache**: 0 cache hits across all 20 B mode requests. The similarity threshold (0.88) is too strict for diverse test prompts. Cache would help in production with repeated/similar queries.

8. **Quantum memory & ontology**: Enabled but did not activate. The quantum memory DB was empty (no prior sessions to retrieve anchors from). Ontology injection requires DB schema context that wasn't triggered for these prompts.

---

## Summary Table

| Metric | B (Full Harness) | A2 (Loop, no features) | A1 (Direct API) |
|---|---|---|---|
| Avg Latency (ms) | 21,949 | 11,437 | 12,681 |
| Min Latency (ms) | 5,033 | 1,893 | 1,963 |
| Max Latency (ms) | 57,003 | 26,779 | 39,042 |
| Avg Total Tokens | 700 | 1,015 | 783 |
| Avg Tool Calls | 1.9 | 0 | 0 |
| Avg Response Length | 1,675 | 1,935 | 1,007 |
| Avg Pipeline Stages | 3 | 3 | 0 |
| Successful Requests | 17/20 | 20/20 | 20/20 |

---

*This methodology document was generated on 2026-06-28.*
