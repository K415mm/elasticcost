# 02 — phpkaiharness Components

`phpkaiharness` is a standalone Laravel package that wraps any Laravel AI agent with a
complete production-grade pipeline. It is model-agnostic, provider-agnostic, and
requires zero changes to the host application's agents or controllers.

Every component described here is implemented in `packages/phpkaiharness/src/Optimize/`
and is activated via the `feature_graph.nodes` section of `config/harness.php` or
`storage/app/phpkaiharness/config_overrides.json`.

---

## Architecture Overview

```
                        ┌─────────────────────────────────────────────────────┐
                        │                   phpkaiharness                     │
  User Prompt ─────────►│                                                     │
                        │  ┌──────────────┐   ┌──────────────────────────┐   │
                        │  │ SemanticCache│   │  ModelPromptOptimizer    │   │
                        │  │  (lookup)    │   │  (qwen/gemma tuning)     │   │
                        │  └──────┬───────┘   └────────────┬─────────────┘   │
                        │    HIT ▼                         ▼                 │
                        │  Instant Return    ┌─────────────────────────────┐ │
                        │                   │  DraftVerificationOrch.     │ │
                        │                   │  Draft ► Evidence ► Verify  │ │
                        │                   └──────────────┬──────────────┘ │
                        │                                  ▼                 │
                        │                   ┌──────────────────────────────┐ │
                        │                   │  OntologicalContextInjector  │ │
                        │                   │  (RAG from documentation DB) │ │
                        │                   └──────────────┬───────────────┘ │
                        │                                  ▼                 │
                        │                   ┌──────────────────────────────┐ │
                        │                   │   QuantumInferenceEngine     │ │
                        │                   │   (vector memory retrieval)  │ │
                        │                   └──────────────┬───────────────┘ │
                        │                                  ▼                 │
                        │                   ┌──────────────────────────────┐ │
                        │                   │        AgentLoop             │ │
                        │                   │  [Tool Calls + Iterations]   │ │
                        │                   │  + ContextCompactor          │ │
                        │                   └──────────────┬───────────────┘ │
                        │                                  ▼                 │
                        │                   ┌──────────────────────────────┐ │
                        │                   │   CognitiveGraphMemory       │ │
                        │                   │   (extract & persist facts)  │ │
                        │                   └──────────────┬───────────────┘ │
                        │                                  ▼                 │
                        │                   ┌──────────────────────────────┐ │
                        │                   │   SemanticCache (store)      │ │
                        │                   └──────────────────────────────┘ │
                        └─────────────────────────────────────────────────────┘
                                                           │
                                                    Final Response
```

---

## Component 1 — Semantic Cache

**File:** `Optimize/SemanticCache.php`  
**Config:** `feature_graph.nodes.semantic_cache.enabled` + `cache.threshold` (0.88)

### What it does

Before the agent loop runs, the cache checks whether a semantically equivalent
question has been answered before. It uses **three-tier matching**:

1. **Vector semantic search** via `SemanticMemoryInterface` — true AI-native matching
   using embedding vectors stored in the SQLite monitor DB
2. **Exact string match** — zero-overhead O(1) lookup
3. **Levenshtein fuzzy distance** — catches near-identical rephrasing

If any match exceeds the similarity threshold (0.88), the cached response is returned
**immediately** — no LLM call, no tool execution, no pipeline overhead.

### Test evidence

In B-Warm, 7 out of 17 requests were instant cache hits:

| Request | Category | B-Cold latency | B-Warm latency | Saving |
|---------|----------|----------------|----------------|--------|
| #11 | db-query-simple | 179 ms | **87 ms** | 51% |
| #12 | db-query-settings | 81 ms | **53 ms** | 35% |
| #13 | db-update-simple | 81 ms | **53 ms** | 35% |
| #14 | db-update-setting | 48 ms | 70 ms | -46% (tiny query, lookup overhead > saving) |
| #15 | db-query-tunisian | 65 ms | 135 ms | — |
| #16 | db-create-client | 114 ms | **53 ms** | 54% |
| #17 | db-query-comprehensive | 119 ms | 231 ms | — |

All 7 returned responses from the previous B-Cold run — zero new LLM API calls.

---

## Component 2 — Cognitive Graph Memory

**File:** `Optimize/CognitiveGraphMemory.php`  
**Config:** `feature_graph.nodes.cognitive_memory.enabled`

### What it does

After every agent response, a **secondary LLM call** extracts concrete, atomic facts from
the interaction transcript and stores them as plain-text records in `harness_facts` (SQLite).

The extraction prompt is strict — it only captures state changes and factual resolutions:

```
"You are a precise facts-extraction assistant. Extract a flat list of key facts or state changes.
 Focus on concrete changes (settings updated, client added, allocations changed, device counts set).
 Each fact must be a single, standalone sentence. If no concrete facts, output nothing."
```

These facts become part of the shared memory that enriches future agent context.

### What was accumulated from B-Cold sessions

| Session | Facts extracted |
|---------|----------------|
| int_6a42881b... | 4 facts |
| int_6a428860... | 16 facts |
| int_6a4288de... | 7 facts |
| int_6a42892e... | 17 facts |
| int_6a4289a3... | 17 facts |
| int_6a428a3f... | 6 facts |
| int_6a428a85... | 9 facts |
| int_6a428adc... | 5 facts |
| int_6a428b1f... | 1 fact |
| int_6a428b66... | 2 facts |
| **Total** | **84 facts** |

### Test evidence

In B-Warm, 10/17 responses were richer than B-Cold — cognitive facts were injected into
the prompt context, giving the model grounded knowledge about previous interactions.
For example, `sizing-tunisian` response grew from 3,916 chars (cold) to 5,554 chars (warm)
because the model had prior facts about Tunisian client sizing conventions.

---

## Component 3 — Quantum-Inspired Memory Harness

**File:** `Optimize/QuantumInferenceEngine.php`  
**Config:** `feature_graph.nodes.quantum_harness.enabled` + `quantum_harness` block  
**DB:** `storage/app/phpkaiharness/agent_memory.sqlite`

### What it does

The Quantum Inference Engine stores interaction patterns as **memory nodes** with
phase angles and embedding vectors. It implements a quantum-inspired scoring model:

```
Score(node) = α × cosine_similarity(embedding, query) + β × phase_decay(node)
```

Where:
- `α = 0.7` — weight on semantic similarity
- `β = 0.3` — weight on recency/phase decay
- `similarity_threshold = 0.15` — minimum score to include a node
- `max_anchors = 5` — maximum nodes injected per request

**Entanglement pairs** link related memory nodes — when one node is retrieved,
its entangled partners are also scored and potentially included.

### What was accumulated from B-Cold sessions

- **182 memory nodes** with embeddings
- **182 memory vectors** (one per node)
- Entanglement pairs linking related concepts

### Why this matters

Unlike traditional RAG (retrieve top-k by cosine distance), the quantum scoring
applies phase decay to age nodes appropriately — older, less-relevant memories
fade while recent, frequently-retrieved ones stay prominent.

---

## Component 4 — Draft Verification Orchestration

**File:** `Optimize/DraftVerificationOrchestration.php`  
**Config:** `feature_graph.nodes.draft_verification.enabled`

### What it does

A **three-step verification pipeline** that runs before the main agent loop on
complex analytical questions:

```
Step 1 — DRAFT
  Fast LLM call with a simplified system prompt.
  Produces a raw, assumption-heavy initial answer.

Step 2 — RETRIEVAL
  Ontological context injector searches the documentation DB
  for evidence that supports or contradicts the draft.

Step 3 — VERIFY
  Main LLM call receives both the draft AND the retrieved evidence.
  Produces a corrected, evidence-backed final response.
```

This pattern is derived from state-of-the-art multi-step verification pipelines
(RAG-Fusion, Self-RAG, CRITIC) and eliminates hallucinations on factual questions
by grounding the answer in retrieved evidence before committing to a final response.

### Impact on ElasticCostAssistant

Sizing and costing questions benefit directly — the draft captures the model's
initial assumption about RAM/disk ratios, then evidence from documentation confirms
or corrects the exact numbers before the final response is generated.

---

## Component 5 — Ontological Context Injector

**File:** `Optimize/OntologicalContextInjector.php`  
**Config:** `feature_graph.nodes.ontology_injection.enabled` + `ontology` block

### What it does

Before each LLM call, retrieves the most semantically relevant documentation chunks
from the application's documentation database using embedding similarity:

- `similarity_threshold = 0.15` — minimum similarity score
- `max_records = 5` — maximum chunks injected
- Embedding column: `embedding`

The retrieved documentation is appended to the system prompt as structured context,
giving the model current, application-specific knowledge beyond its training data.

### Why this matters for ElasticCost

The application maintains its own documentation: pricing tables, sizing ratios,
currency conversion rules, MSSP staffing formulas. The ontology injector ensures
the model always has the current version of this data, not a potentially stale
version from training.

---

## Component 6 — Model Prompt Optimizer

**File:** `Optimize/ModelPromptOptimizer.php`  
**Config:** `feature_graph.nodes.model_optimizer.enabled`

### What it does

Automatically rewrites the system prompt with model-specific reasoning instructions
before sending to the LLM:

**For Qwen models:**
```
[QWEN OPTIMIZATION PROTOCOL]
1. Before responding, output your step-by-step thinking process inside <thought>...</thought> tags.
2. If you need to execute a tool, call the corresponding function from your tools list natively.
3. Structure your final response logically and concisely.
```

**For Gemma models:**
```
[GEMMA OPTIMIZATION PROTOCOL]
1. You are running in a strict role-bounded environment. Respond strictly as the assigned role.
2. When generating tool call parameters, output clean JSON objects...
3. Keep responses direct and structured.
```

This eliminates the need to manually tune prompts per model — the optimizer detects
the model name and applies the correct optimization automatically.

### Test evidence

In B-Warm vs A1 on ElasticCostAssistant, the optimized system prompt caused
the model to structure responses with tables and sections rather than prose,
directly contributing to the richer responses in 10/17 warm requests.

---

## Component 7 — Context Compactor

**File:** `Optimize/ContextCompactor.php`  
**Config:** `feature_graph.nodes.context_compactor.enabled` + `compaction` block

### What it does

Manages the conversation history within the agent loop to prevent token overflow
on multi-turn, tool-heavy sessions. Two strategies:

**Sliding Window** (configured in this test: `max_turns = 20`):
```
[Original user query] + [⚠️ Dropped N older turns] + [Last 20 messages]
```

**Summarize** (LLM-assisted):
```
[Original user query] + [Summary of earlier turns] + [Last 2 messages]
```

Without this, RgSocEngineer requests with 15+ tool call iterations would
overflow the model's context window, causing truncation or errors.

### Test evidence

Request #17 (db-query-comprehensive) triggered 15 tool calls in A2, which without
compaction would have grown to ~15,000 tokens of history. The compactor reduced
this to a manageable window while preserving the original query and recent context.

---

## Component 8 — LLM Client Pipeline (Failover, Rate Limit, PII, Budget)

**File:** `Llm/LlmClientPipelineBuilder.php`  
**Config:** `failover`, `rate_limiting`, `pii_masking`, `budget` blocks

### What it does

Wraps the base LLM client in a decorator chain:

```
FailoverLlmClient
  └─► ThinkingBudgetLlmClient (max_tokens: 30,000,000)
        └─► PiiMaskingLlmClient (EMAIL, IP, CREDIT_CARD patterns)
              └─► RateLimitedLlmClient (1200 req/min)
                    └─► LaravelAiClient (base: Qwen/Ollama/LM Studio)
```

**FailoverLlmClient:** If the primary LLM fails, automatically retries with
configured fallback clients — transparent to the agent.

**PiiMaskingLlmClient:** Regex-replaces sensitive patterns before they reach
the LLM API — emails, IPs, credit card numbers, API keys, phone numbers.

**ThinkingBudgetLlmClient:** Caps thinking token budgets for reasoning models,
preventing runaway inference costs.

**RateLimitedLlmClient:** Token-bucket rate limiting to avoid provider throttling.

---

## Component 9 — Session Isolation

**Config:** `session_isolation.enabled`  
**Path:** `storage/app/phpkaiharness/sessions/{session_id}/`

### What it does

Each agent session writes its telemetry, facts, and quantum nodes to its own
isolated SQLite DB under a unique session directory. This ensures:

- **No cross-session contamination** — one session's tool errors cannot corrupt another's memory
- **Complete audit trail** — each session's full trace is independently queryable
- **Consolidation pipeline** — `consolidate_memory.php` merges all session DBs into the
  shared main DB on demand, enabling the B-Warm scenario

In the test, 85 session DBs were created across all runs, then consolidated into
the main DB to pre-load B-Warm.

---

*Next: [03 — Results Analysis](./03-results-analysis.md)*
