# Agent: SizingRegulator

> **File:** `app/Ai/Agents/SizingRegulator.php`  
> **SDK Contracts:** `Agent`, `HasMiddleware`, `HasStructuredOutput`  
> **Laravel AI Trait:** `Promptable`

---

## 1. Purpose

The `SizingRegulator` is the **Elasticsearch cluster topology auditor**. It receives a full JSON description of a client's cluster sizing configuration and produces a structured, technical critique covering RAM:disk ratios, HA quorum, replication, and JVM heap allocation.

It is triggered from the Sizing Dashboard via the "Analyze with AI" button.

---

## 2. Provider & Model

```php
#[Provider(Lab::Ollama)]
#[Model('gemma4:e2b')]
```

> These are default attributes. The actual provider/model is resolved at runtime via `AiConfigHelper::configure()` in `SizingDashboardController::analyzeSizingAi()`.

---

## 3. Laravel AI Contracts

### `Agent`
Provides `instructions()` — the expert Elasticsearch architect system prompt.

### `HasMiddleware`
```php
public function middleware(): array
{
    return [new InjectDocumentation];
}
```
RAG injection from technical reference guides (ratio tables, architectural standards) is applied before the AI call, enriching the analysis with authoritative documentation.

### `HasStructuredOutput`
Forces the AI to return a typed JSON object rather than free text.

```php
public function schema(JsonSchema $schema): array
{
    return [
        'verdict'         => $schema->string()->description('Adequate|Under-provisioned|Imbalanced')->required(),
        'health_score'    => $schema->integer()->description('1-10')->required(),
        'ratio_audit'     => $schema->array()->description('Per-tier ratio audits')->required(),
        'ha_check'        => $schema->object([
                                'master_eligible_count' => $schema->integer()->required(),
                                'quorum_met'            => $schema->boolean()->required(),
                                'remarks'               => $schema->string()->required(),
                            ])->required(),
        'recommendations' => $schema->array()->description('Numbered recommendations')->required(),
        'full_critique'   => $schema->string()->description('Markdown critique')->required(),
    ];
}
```

---

## 4. Technical Audit Instructions (System Prompt)

### RAM-to-Disk Ratio Check (Critical)
The agent is given explicit formulas and threshold tables:

```
Formula: Ratio = disk_per_node_gb / ram_per_node_gb

Thresholds:
  Hot:    Optimal 1:16 – 1:30    (NVMe SSD)
  Warm:   Optimal 1:48 – 1:80   (SATA SSD/HDD)
  Cold:   Optimal 1:100 – 1:160 (SATA Snapshot Cache)
  Frozen: Optimal 1:160 – 1:1000 (Object Storage)
```

The agent is explicitly warned: *"Do not hallucinate or miscalculate. If a ratio falls within the optimal range, explicitly state that it is optimal."*

### HA / Master Quorum Check
```
Master-eligible nodes = any node with "Master" in role description
(including combined roles like "Master / Data (Hot)")

Minimum required: 3 master-eligible nodes for quorum
< 3 → flag as HA risk
```

### JVM Heap Rule
```
JVM Heap = exactly 50% of node physical RAM
Cap: 30-32 GB
```

### `full_critique` Structure (Markdown sections)
1. 📊 Topology Sizing Overview
2. 🔍 RAM-to-Disk Ratio Audit
3. 🛡️ High Availability & Redundancy Check
4. 💡 Actionable Enhancements & Recommendations

---

## 5. Input Payload

The agent receives a JSON payload built by `SizingDashboardController::analyzeSizingAi()`:

```json
{
  "client": { "id": 1, "name": "Acme Corp" },
  "scenario": {
    "name": "Enterprise Standard",
    "workload_profile": "avg",
    "hot_days": 14,
    "warm_days": 30,
    "cold_days": 46,
    "frozen_days": 0,
    "hot_replicas": 1
  },
  "totals": {
    "daily_raw_gb": 15.2,
    "daily_indexed_gb": 19.0,
    "hot_storage_gb": 532.0,
    "warm_storage_gb": 1140.0,
    "cold_storage_gb": 1748.0
  },
  "nodes": [
    {
      "name": "master-node-0[1-3]",
      "role": "Dedicated Master (Quorum)",
      "count": 3,
      "ram_gb": 4,
      "heap_gb": 2,
      "storage_gb": 50,
      "storage_type": "Local SSD"
    },
    {
      "name": "hot-node-01",
      "role": "Dedicated Data (Hot)",
      "count": 1,
      "ram_gb": 32,
      "heap_gb": 16,
      "storage_gb": 300,
      "storage_type": "Local NVMe SSD"
    }
  ]
}
```

---

## 6. Output Example

```json
{
  "verdict": "Adequate",
  "health_score": 8,
  "ratio_audit": [
    {
      "tier": "Hot",
      "ratio": "1:9.375",
      "status": "⚠️ Under the optimal 1:16-1:30 range — storage could be increased"
    },
    {
      "tier": "Warm",
      "ratio": "1:71.25",
      "status": "✅ Optimal (1:48-1:80)"
    }
  ],
  "ha_check": {
    "master_eligible_count": 3,
    "quorum_met": true,
    "remarks": "3 dedicated master nodes provide robust quorum. No split-brain risk."
  },
  "recommendations": [
    "1. Increase Hot node storage from 300 GB to 500 GB to meet the 1:16 minimum ratio.",
    "2. Consider adding a second Cold node for HA on snapshot cache."
  ],
  "full_critique": "# 📊 Topology Sizing Overview\n..."
}
```

---

## 7. Execution Flow

```
SizingDashboardController::analyzeSizingAi(Request, Client, Scenario)
  │
  ├── SizingEngine::calculate(client, scenario)  ← get full sizing data
  ├── Build JSON payload (nodes + totals + scenario)
  ├── AiConfigHelper::configure()                ← read provider/model
  └── SizingRegulator::prompt($jsonPayload, provider, model)
        │
        ├── InjectDocumentation::handle()          ← RAG injection
        │     (Elasticsearch sizing reference guides)
        │
        ├── AI provider call with system prompt + JSON input
        │
        └── Structured JSON output (validated against schema)
  
  ← array{ verdict, health_score, ratio_audit, ha_check, recommendations, full_critique }
  ← JSON response to frontend (rendered as collapsible panel)
  → stored in client_scenario_mssp_details.ai_sizing_analysis (JSON)
```

---

## 8. Laravel Components Used

| Component | Class/Facade | Purpose |
|---|---|---|
| AI Contract | `Agent` | Base agent interface |
| AI Contract | `HasMiddleware` | RAG middleware injection |
| AI Contract | `HasStructuredOutput` | Typed JSON output schema |
| AI Trait | `Promptable` | `prompt()` method implementation |
| AI Schema | `JsonSchema` | Schema builder for structured output |
| AI Attribute | `#[Provider(Lab::Ollama)]` | Default provider declaration |
| AI Attribute | `#[Model('gemma4:e2b')]` | Default model declaration |
| Service | `AiConfigHelper::configure()` | Runtime provider resolution |
| Middleware | `InjectDocumentation` | RAG context injection |
| Eloquent | `GlobalSetting` | RAG settings (threshold, max chunks) |
| Eloquent | `DocumentationChunk` | Vector search source |
| Laravel AI SDK | `Embeddings` | Query embedding generation |
| DB | `DB::connection()->getDriverName()` | SQLite vs pgvector routing |
| Controller | `SizingDashboardController` | Caller context + JSON builder |
| Service | `SizingEngine` | Provides sizing data for analysis |

---

## 9. RAG Configuration

| Key | Default |
|---|---|
| `ai_rag_enabled_SizingRegulator` | `true` |
| `ai_rag_threshold_SizingRegulator` | `0.30` |
| `ai_rag_max_chunks_SizingRegulator` | `3` |

Relevant reference documents to upload for best results:
- Elasticsearch sizing guides (RAM/disk ratios)
- Elastic hardware sizing reference
- Elasticsearch hardware recommendations (official docs)
