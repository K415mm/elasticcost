# Test Methodology — How We Ran the Benchmark

> **Run ID**: 20260706-172239  
> **Run Date**: July 6, 2026, 17:22:39 UTC  
> **Environment**: Docker container on Alibaba Cloud ECS (47.251.180.213)  
> **Framework**: Laravel 13 + Laravel Octane + phpkaiharness  

---

## Objective

Compare four execution modes of the same AI system to quantify:
1. The **overhead cost** of running the full phpkaiharness pipeline vs. a raw API call
2. The **quality improvement** delivered by the pipeline's cognitive features
3. The **cache warm-up benefit** of running the same queries through the pipeline twice
4. The **enterprise safety features** confirmed active via telemetry inspection

---

## Execution Modes Tested

| Mode | Label | Description |
|------|-------|-------------|
| **A1** | Direct API | Raw HTTP call to Qwen API. No pipeline. No context. No tools. |
| **A2** | Loop No Features | Basic agent loop with tool access. No pipeline stages, no semantic cache. |
| **B-Cold** | Full Harness (Cold) | Complete phpkaiharness pipeline. Semantic cache is empty (first run). |
| **B-Warm** | Full Harness (Warm) | Complete phpkaiharness pipeline. Cache is populated from B-Cold run. |

---

## Test Dataset

**20 carefully selected requests** designed to stress-test different aspects of the pipeline:

| # | Category | Language | Complexity |
|---|----------|----------|------------|
| 1-5 | Elasticsearch architecture design | English | High |
| 6-8 | Cloud cost estimation | English | High |
| 9-12 | Multi-tier infrastructure planning | English | Very High |
| 13-15 | Database optimization queries | Mixed | Medium |
| 16-18 | Tunisian Arabic dialect requests | Arabic | High |
| 19-20 | Compound multi-entity queries | English | Very High |

The dataset was chosen to include:
- **Technical depth**: requires real database schemas and cost data
- **Linguistic diversity**: Tunisian Arabic to test multilingual semantic cache
- **Semantic similarity**: several queries are rephrased versions of earlier ones (to test cache hit rate)
- **Enterprise realism**: queries are representative of real ElasticCost user sessions

---

## Execution Architecture

```
Browser / Test Runner
        │
        ▼ POST /test-compare/run
TestCompareController::run()
        │
        ▼ shell_exec (detached nohup)
php artisan test:phpkaiharness --run
        │
        ▼
TestRunner.php
        │
        ├── runDirectApi()          → A1 mode (20 requests, no pipeline)
        ├── runLoopNoFeatures()     → A2 mode (20 requests, loop + tools)
        ├── runFullHarness()        → B-Cold mode (20 requests, full pipeline, cold cache)
        └── runFullHarness(warm)    → B-Warm mode (20 requests, full pipeline, warm cache)
```

Each mode ran **sequentially** (not in parallel) to avoid resource contention. Total run time: approximately **35 minutes**.

---

## Data Collection

### HTTP Trace Layer
Each request saves a `request-{N}.json` trace file containing:
- Request prompt, raw response, success/failure
- Timing: `latency_ms`, `time_to_first_token_ms`
- Token counts: `prompt_tokens`, `completion_tokens`, `total_tokens`
- Pipeline stages executed and their durations
- Tool calls made and their results
- AI evaluation score (model-judged quality score 0-100)

### SQLite Telemetry Layer (phpkaiharness monitor.db)
Each phpkaiharness session writes to an isolated SQLite database at:
```
/var/www/storage/app/phpkaiharness/sessions/{session_id}/monitor.db
```

Tables captured:
- `harness_sessions` — session metadata, method, status, iterations
- `harness_details` — detailed pipeline events by type (cache, quantum, ontology, etc.)
- `harness_memories` — quantum memory nodes (where populated)
- `harness_facts` — cognitive memory facts (where extracted)

### Report Generation
After all modes complete, `TestCompareReportGenerator::generate()` reads both layers and produces:
- `comparison-summary.json` — machine-readable metrics
- `comparison-report.md` — human-readable report with expert evaluation

---

## Verification Steps

After the run, we executed **deep read-only inspection queries** against all 26 monitor.db files:
- Confirmed ontology injection fired on 100% of non-cache-hit sessions
- Confirmed quantum memory runs on 100% of non-cache-hit sessions
- Confirmed draft verification executed on every LLM response
- Counted actual semantic cache hit rate from `method='semantic-cache-hit'` in session records
- Cross-checked token counts from `harness_details.type='llm_call'`

This telemetry-level verification is what corrected the initial Feature Evaluation Matrix, which reported `0` for several features (those zeros came from HTTP trace parsing only, which could not see inside the pipeline).

---

## Environment Specifications

| Component | Value |
|-----------|-------|
| Server | Alibaba Cloud ECS |
| OS | Linux (Docker) |
| PHP | 8.5 (FPM via Octane) |
| Laravel | 13 |
| Web Server | Laravel Octane (FrankenPHP) |
| Database | SQLite (per-session monitor.db) |
| LLM Provider | Tongyi Qianwen (Alibaba Cloud) |
| Model | qwen-plus |
| Max Iterations | 50 per session |
| Cache Threshold | 0.60 cosine similarity (test run) |

---

*Document version: 1.0 — July 6, 2026*
