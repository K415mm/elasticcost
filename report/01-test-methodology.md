# 01 — Test Methodology

## Overview

The benchmark was designed to answer a single scientific question:

> When all phpkaiharness features are fully enabled and the system has prior session memory,
> how does it compare to a raw API call and a plain loop with no features?

All tests were run on the **same Laravel application**, the same infrastructure, the same model,
and the same 17-request dataset. The only variable is how much of the phpkaiharness pipeline
is active for each mode.

---

## The Four Modes

### A1 — Direct API

**What it is:** A raw HTTP POST directly to the Qwen Cloud `/chat/completions` endpoint.  
**No harness. No loop. No tools. No memory. No pipeline.**

```
User Prompt ──► HTTP POST ──► LLM ──► Response
```

- System prompt is injected verbatim from the agent definition
- No tool registration
- No context enrichment
- No caching
- No memory read or write
- One LLM call per request, always

**Purpose:** Establishes the absolute baseline — the fastest possible response with zero overhead,
and zero intelligence beyond the model's pre-trained knowledge.

---

### A2 — Agent Loop, No Features

**What it is:** The `AgentLoop` runs with tools registered, but **all phpkaiharness feature_graph nodes disabled**.

```
User Prompt ──► AgentLoop ──► LLM ──► [Tool Calls] ──► LLM ──► Response
```

- Tool registry active (DB query, update, create tools)
- Semantic cache: **OFF**
- Cognitive memory: **OFF**
- Quantum inference: **OFF**
- Draft verification: **OFF**
- Model optimizer: **OFF**
- Context compactor: **OFF**
- Ontology injection: **OFF**

**Purpose:** Measures the pure value of tool-use and agentic looping vs raw API,
without any phpkaiharness enhancements.

---

### B-Cold — Full Harness, No Prior Memory

**What it is:** The complete phpkaiharness pipeline runs with all features enabled,
but the SQLite databases are cleared before the run — **zero prior knowledge**.

```
User Prompt ──► SemanticCache (miss) ──► ModelOptimizer ──► DraftVerification
            ──► OntologyInjection ──► AgentLoop ──► QuantumInference
            ──► CognitiveMemory (write) ──► ContextCompactor ──► Response
```

- All feature_graph nodes: **ON**
- Semantic cache: populated from scratch
- Cognitive facts DB: empty at start
- Quantum nodes: empty at start

**Purpose:** Shows the full pipeline overhead on a cold start, and proves that
the harness builds memory correctly during execution.

---

### B-Warm — Full Harness, Pre-Loaded Memory

**What it is:** Identical to B-Cold but with **all prior session memory consolidated** into the
shared DBs before the run:

| Memory pre-loaded | Count |
|-------------------|-------|
| Cognitive facts | **84** |
| Quantum memory nodes | **182** |
| Quantum vectors | **182** |
| Semantic cache entries | **16** |
| Session histories | **41** |

```
User Prompt ──► SemanticCache (HIT → instant return) OR
             ──► QuantumRetrieval (182 nodes to match against)
             ──► CognitiveInjection (84 facts available)
             ──► AgentLoop ──► Response
```

**Purpose:** Demonstrates the compounding value of phpkaiharness after real usage —
the state every production deployment reaches after its first sessions.

---

## Dataset: 17 Requests

Originally 20 requests. Three were removed before any test run because they reliably
failed due to missing multilingual data (`db-query-french`, `db-update-multi`, `db-update-tunisian`).
All four modes ran on exactly the same 17-request dataset.

### ElasticCostAssistant (10 requests)

These are sizing and costing advisory questions — the agent answers from knowledge,
optionally augmented by RAG/memory. No tool calls required.

| # | Category | Sample Prompt |
|---|----------|--------------|
| 1 | sizing-basic | What is the RAM-to-disk ratio for Hot tier? |
| 2 | sizing-calculation | Size a cluster for 500 GB/day, 30-day retention |
| 3 | sizing-french | Expliquez les ratios RAM/disque (French) |
| 4 | costing-calculation | Calculate MSSP staffing cost for 50 clients |
| 5 | sizing-tunisian | Size cluster for 1 TB/day (Arabic/Tunisian) |
| 6 | sizing-replicas | What does having one replica mean? |
| 7 | costing-concept | Explain assurance markup in MSSP costing |
| 8 | costing-currency-french | Currency conversion explanation (French) |
| 9 | sizing-shards | Optimal shard size recommendation |
| 10 | costing-tunisian | SOC cost estimate (Tunisian Arabic) |

### RgSocEngineer (7 requests)

These are live DB interactions — the agent must call tools to read/write the application database.

| # | Category | Operation |
|---|----------|-----------|
| 11 | db-query-simple | List all clients and device counts |
| 12 | db-query-settings | Read all global SOC cost settings |
| 13 | db-update-simple | Add 2 FortiGate firewalls to Acme Corp |
| 14 | db-update-setting | Check/set SIEM agent cost to $25/device |
| 15 | db-query-tunisian | List clients in Tunisian Arabic |
| 16 | db-create-client | Create new client TechCorp Industries |
| 17 | db-query-comprehensive | Full system state overview |

---

## Fairness Rules

1. **Same model**: All 4 modes used `qwen3.5-omni-flash-2026-03-15` via Qwen MaaS
2. **Same infrastructure**: Single machine, same PHP server, same DB files
3. **Same dataset**: 17 requests, identical prompts, identical order
4. **Cold DB before B-Cold**: `clear_db.php` wiped all sessions, facts, quantum nodes
5. **Memory consolidation before B-Warm**: `consolidate_memory.php` merged all B-Cold session data
6. **No retries**: Each request ran once; timing includes full pipeline overhead

---

## Measurement Dimensions

| Dimension | How measured |
|-----------|-------------|
| **Latency** | Wall-clock ms from request start to final response write |
| **Tool calls** | Count of real tool executions recorded in session trace |
| **Response length** | Character count of final response (proxy for richness) |
| **Cache hit** | Boolean flag + latency under 500ms |
| **Success** | Non-error response with non-empty content |

---

*Next: [02 — phpkaiharness Components](./02-phpkaiharness-components.md)*
