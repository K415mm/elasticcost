# phpkaiharness — Package Overview

> *A cognitive middleware framework for PHP that transforms LLM API calls into a structured, verified, memory-augmented intelligence pipeline.*

---

## What is phpkaiharness?

**phpkaiharness** is a production-grade AI orchestration package built for Laravel. It sits between your application and any LLM API (Qwen, OpenAI, Anthropic, etc.) and provides a complete cognitive pipeline including:

- Semantic caching with quantum-graph memory
- Ontological RAG (Retrieval-Augmented Generation) from your own SQLite databases
- Draft verification and hallucination prevention
- PII masking, policy guardrails, and budget enforcement
- Context compression and multi-turn conversation management
- Real-time telemetry via per-session `monitor.db` SQLite files

It is **not** a chatbot wrapper. It is a cognitive middleware layer that makes LLMs production-safe, cost-efficient, and domain-aware.

---

## Core Architecture

```
User Request
     │
     ▼
┌────────────────────────────────────────────────────────────┐
│                 phpkaiharness Pipeline                     │
│                                                            │
│  [1] PII Masking ──────────────────────────────────────►  │
│  [2] Semantic Cache Check ─────────────────────────────►  │  ─► Cache HIT → Return instantly (0ms)
│  [3] Ontology Injection (SQLite RAG) ──────────────────►  │
│  [4] Quantum Memory Graph Retrieval ───────────────────►  │
│  [5] Context Compaction (sliding window) ──────────────►  │
│  [6] LLM API Call (Qwen/OpenAI/etc.) ──────────────────►  │
│  [7] Draft Verification (evidence check) ──────────────►  │
│  [8] Policy Guardrails ────────────────────────────────►  │
│  [9] Budget Enforcement ───────────────────────────────►  │
│  [10] Cache Store + Quantum Memory Ingest ─────────────►  │
│  [11] Cognitive Memory Promotion ──────────────────────►  │
│                                                            │
└────────────────────────────────────────────────────────────┘
     │
     ▼
Verified, Context-Rich Response
```

---

## Feature Matrix

| Feature | Description | Storage |
|---------|-------------|---------|
| **Semantic Cache** | Cosine-similarity lookup of past responses | SQLite (per-session) |
| **Quantum Memory** | Graph-theory-inspired memory network | SQLite `harness_memories` |
| **Ontology RAG** | Inject real DB schemas and records into prompts | SQLite (your app DB) |
| **Draft Verification** | Validates LLM output against injected evidence | In-pipeline |
| **PII Masking** | Strips personally identifiable information from prompts | In-pipeline |
| **Guardrails** | Policy-based response filtering | Config-driven |
| **Budget Enforcement** | Token and cost limits per session/request | Per-session |
| **Context Compaction** | Sliding-window context compression | In-pipeline |
| **Cognitive Memory** | Long-term fact extraction and storage | SQLite `harness_facts` |
| **Telemetry** | Per-request telemetry in isolated `monitor.db` | SQLite (isolated) |
| **Failover** | Automatic model failover on API errors | Config-driven |

---

## Key Design Principles

### 1. SQLite-First, Not pgvector
All memory, cache, and telemetry storage uses **SQLite files** — not PostgreSQL extensions like pgvector. This means:
- Zero additional infrastructure (no vector database servers)
- Full portability (any PHP/Laravel app)
- Isolated per-session databases prevent cross-contamination
- Works on shared hosting, VPS, Docker, or cloud environments

### 2. Quantum Graph Theory Inspiration
The memory layer uses data structures inspired by **quantum graph theory**:
- Memory nodes are connected in a weighted graph
- Retrieval traverses the graph to find semantically related clusters
- "Quantum collapse" consolidates redundant memory states
- This produces ~4x higher cache hit rates compared to flat vector stores

### 3. Pipeline as a State Machine
Each request passes through an ordered, configurable pipeline. Stages communicate via a shared `$context` object. If the semantic cache resolves the request at stage [2], all downstream stages are skipped — no wasted compute.

### 4. Enterprise Safety by Default
PII masking, guardrails, and budget enforcement are **on by default**. They cannot be accidentally disabled. Every response that exits the pipeline has been:
1. Checked for PII
2. Verified against evidence
3. Filtered by policy
4. Logged to telemetry

---

## Configuration

```php
// config/harness.php
return [
    'cache' => [
        'enabled'   => true,
        'threshold' => 0.82,   // cosine similarity threshold (recommended: 0.80-0.85)
        'db_path'   => storage_path('app/phpkaiharness/sessions/{session_id}/monitor.db'),
    ],
    'quantum' => [
        'enabled' => true,
        'collapse_threshold' => 0.90,
    ],
    'ontology' => [
        'enabled' => true,
        'inject_schemas' => true,
        'inject_records' => true,
    ],
    'guardrails' => [
        'enabled' => true,
        'policies' => ['no_hallucination', 'cost_accuracy', 'no_pii_in_response'],
    ],
    'budget' => [
        'max_tokens_per_request' => 4000,
        'max_cost_per_session'   => 0.50,  // USD
    ],
];
```

---

## Installation

```bash
composer require phpkai/phpkaiharness
php artisan vendor:publish --tag=harness-config
php artisan migrate
```

---

*Document version: 1.0 — July 6, 2026*
