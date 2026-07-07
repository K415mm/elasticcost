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
                       Query Prompt Input
                                │
                                ▼
                  ┌──────────────────────────┐
                  │  Dirac Complexity Router │
                  └──────────────────────────┘
                                │
          ┌─────────────────────┼─────────────────────┐
          ▼                     ▼                     ▼
   [|Simple> Path]      [|Complicated> Path]    [|Complex> Path]
   Direct LLM Call       Ontological RAG RAG    Full Pipeline Loop
          │                     │                     │
          │                     │                     ▼
          │                     │         ┌───────────────────────┐
          │                     │         │  L1/L2 Semantic Cache │
          │                     │         └───────────────────────┘
          │                     │                     │
          │                     │          (Miss) ──┼── (Hit) ──► Verified Cache Response
          │                     │                     │
          │                     │                     ▼
          │                     │         ┌───────────────────────┐
          │                     │         │  Ontology Context RAG │
          │                     │         └───────────────────────┘
          │                     │                     │
          │                     │                     ▼
          │                     │         ┌───────────────────────┐
          │                     │         │   Multi-Turn Agent    │
          │                     │         │   (Qwen Cloud API)    │
          │                     │         └───────────────────────┘
          │                     │                     │
          │                     │                     ▼
          │                     │         ┌───────────────────────┐
          │                     │         │  Quantum Mem Ingest   │
          │                     │         └───────────────────────┘
          │                     │                     │
          ▼                     ▼                     ▼
                       Final Verified Output
```

---

## Feature Matrix

| Feature | Description | Storage |
|---------|-------------|---------|
| **Dirac Complexity Router** | Project prompt complexity $| \psi \rangle$ to trigger direct, RAG, or full loop | In-pipeline |
| **Semantic Cache & L1/L2 Loop** | Cosine + phase matching with L1 Redis, L2 SQLite & LLM validation | Redis + SQLite |
| **Dissipative Cache Decay** | Density matrices decay exponentially ($\rho(t) = e^{-\Gamma t}\rho(0)$) to prevent stale hits | Redis + SQLite |
| **Quantum Memory Harness** | Cosine + phase wave interference ($S_{\text{fused}} = \alpha S_{\text{cos}} + \beta \cos(\theta_q-\theta_m)$) and entanglement twin pairing | SQLite `agent_memory.sqlite` |
| **Ontology RAG Injector** | Hydrates prompt context from host model embeddings | SQLite (App DB) |
| **Cognitive Graph Memory** | Extracts triplets, manages coherence weights, and decays edges over time | SQLite `agent_memory.sqlite` |
| **Draft Verification** | Validates LLM output against injected database evidence | In-pipeline |
| **PII Masking** | Strips sensitive emails, IPs, cards, and keys from prompts | In-pipeline |
| **Guardrails** | Policy-based tool execution filtering | Config-driven |
| **Budget Enforcement** | Token limits per request / execution session | Per-session |
| **Telemetry Dashboard** | Real-time diagnostics HUD trace viewer and standalone web views | SQLite (isolated) |
| **Failover** | Ordered LLM failover stack (Qwen -> Ollama -> OpenRouter) | Config-driven |

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
