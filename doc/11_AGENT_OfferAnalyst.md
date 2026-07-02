# Agent: OfferAnalyst

> **File:** `app/Ai/Agents/OfferAnalyst.php`  
> **SDK Contracts:** `Agent`, `HasStructuredOutput`  
> **Laravel AI Trait:** `Promptable`

---

## 1. Purpose

The `OfferAnalyst` is the **MSSP SOC commercial proposal auditor**. It receives a complete JSON snapshot of a client's MSSP cost model and produces a structured, expert-level commercial and operational critique covering:
- Pricing sanity & margin sustainability
- Staffing vs. workload alignment
- Infrastructure efficiency
- Structural risks & recommendations
- Overall health score (1–10)

It is triggered from the MSSP Costing Dashboard via the "Ask AI" / "Analyze Offer" button.

---

## 2. Provider & Model

```php
#[Provider(Lab::Ollama)]
#[Model('gemma4:e2b')]
```

> Provider/model resolved at runtime via `AiConfigHelper::configure()` in `MsspCostingController::askAi()`.

---

## 3. Laravel AI Contracts

### `Agent`
Provides the expert MSSP financial analyst system prompt.

### `HasStructuredOutput`
Enforces typed JSON output validated against schema.

```php
public function schema(JsonSchema $schema): array
{
    return [
        'health_score'           => $schema->integer()
                                        ->description('Overall proposal health score from 1 to 10.')
                                        ->required(),
        'margin_status'          => $schema->string()
                                        ->description('Margin assessment: Low, Optimal, or High.')
                                        ->required(),
        'sanity_checks'          => $schema->array()
                                        ->description('Status of checks: ingestion, staffing, etc.')
                                        ->required(),
        'staffing_status'        => $schema->string()
                                        ->description('Over-allocated, Under-allocated, or Balanced.')
                                        ->required(),
        'infrastructure_status'  => $schema->string()
                                        ->description('Wasteful, Imbalanced, or Optimal.')
                                        ->required(),
        'recommendations'        => $schema->array()
                                        ->description('Specific numbered recommendations.')
                                        ->required(),
        'full_critique'          => $schema->string()
                                        ->description('Detailed Markdown analysis.')
                                        ->required(),
    ];
}
```

---

## 4. Expert System Prompt — Critical Sanity Checks

The agent is pre-configured as an **elite MSSP Commercial Architect and Financial Analyst**.

### Mandatory Sanity Checks (Always First)

| Check | Condition | Flag |
|---|---|---|
| **Ingestion Volume** | `daily_raw_ingestion` = "0 GB" for client with VMs | ⚠️ CRITICAL: Zero ingestion |
| **Staffing vs Ingestion** | Ingestion=0 but significant staffing costs | Disconnect flag |
| **Ghost License** | Non-zero Elastic license with 0 GB ingestion | Ghost cost flag |
| **Shared License Logic** | License share % vs VMs and ingestion | Proportionality check |

### Analysis Sections in `full_critique`

#### 1. 🔍 Executive Summary & Health Score (1–10)
- 3–5 sentence summary
- `**Health Score: X/10**` with justification
- Zero ingestion proposals max score: 4/10 unless labeled template

#### 2. 💰 Pricing & Margin Analysis
- Each profit factor as % of base cost
- Total markup >40% → high-risk flag
- Total markup <15% → unsustainability flag
- MRC competitiveness assessment

#### 3. 👥 Staffing & Resource Viability
- Each analyst role: allocation % vs ingestion volume
- Under/over allocation flags
- SOC Manager:Engineer:Analyst ratios

#### 4. 🏗️ Infrastructure Review
- VM count vs cluster workload
- Over-provisioned infra for small workloads
- Storage type efficiency (NVMe vs SATA split)

#### 5. ⚠️ Structural Risks & Recommendations
- Numbered, prioritized, actionable items
- Data quality, commercial, and operational risks

---

## 5. Input Payload

Built by `MsspCostingController::askAi()` from `MsspCostingEngine::calculate()` output:

```json
{
  "client": { "name": "Acme Corp" },
  "scenario": { "name": "Enterprise Standard", "workload_profile": "avg" },
  "sizing_summary": {
    "daily_raw_gb": 15.2,
    "total_ram_gb": 256,
    "required_erus": 4,
    "monthly_license_usd": 4666.67
  },
  "infrastructure": {
    "total_monthly_infra_cost": 3850.00,
    "nodes": [
      { "name": "hot-node-01", "ram_gb": 32, "storage_gb": 300, "total_monthly_cost": 960 }
    ]
  },
  "analysts": {
    "total_monthly_analyst_cost": 2100.00,
    "roles": [
      {
        "name": "L1 Analyst",
        "monthly_salary": 3000,
        "allocation_percentage": 10,
        "staff_count": 5,
        "client_cost": 1500
      }
    ]
  },
  "total_monthly_service_cost": 10616.67,
  "assurance_benefit_percentage": 5.0,
  "marketing_benefit_percentage": 3.0,
  "soc_manager_benefit_percentage": 2.0,
  "ceo_benefit_percentage": 2.0,
  "fixed_profit_percentage": 5.0,
  "total_profit_percentage": 17.0,
  "total_profit_amount": 1804.83,
  "client_offered_price_mrc": 12421.50
}
```

---

## 6. Output Example

```json
{
  "health_score": 7,
  "margin_status": "Optimal",
  "sanity_checks": [
    "✅ Ingestion check: 15.2 GB/day — normal SOC workload detected",
    "✅ Staffing alignment: Analyst costs proportional to ingestion",
    "✅ License cost: Active and proportional to infrastructure",
    "✅ Shared license: Not applicable (100% allocated)"
  ],
  "staffing_status": "Balanced",
  "infrastructure_status": "Optimal",
  "recommendations": [
    "1. Consider increasing assurance markup from 5% to 7% for better risk coverage.",
    "2. Hot node storage at 300 GB may be insufficient for peak ingestion. Validate against max scenario.",
    "3. Add a second cold node for HA on snapshot cache."
  ],
  "full_critique": "# 🔍 Executive Summary & Health Score\n\nThe proposal represents a well-structured MSSP offering for an active production SOC environment with 15.2 GB/day ingestion...\n\n**Health Score: 7/10** — Solid margin structure with minor infrastructure optimization opportunities.\n\n## 💰 Pricing & Margin Analysis\n..."
}
```

---

## 7. Execution Flow

```
MsspCostingController::askAi(Request, Client, Scenario)
  │
  ├── MsspCostingEngine::calculate(client, scenario)  ← full cost model
  ├── Build JSON payload (infra + analysts + margins + totals)
  ├── AiConfigHelper::configure()                      ← read provider/model
  └── OfferAnalyst::prompt($jsonPayload, provider, model)
        │
        ├── AI provider call with expert system prompt + JSON input
        │   (No RAG middleware — uses structured input as full context)
        │
        └── Structured JSON output (validated against schema)
  
  ← array{ health_score, margin_status, sanity_checks, ..., full_critique }
  ← JSON response to frontend (rendered as analysis panel)
  → stored in client_scenario_mssp_details.ai_analysis (JSON column)
```

---

## 8. Laravel Components Used

| Component | Class/Facade | Purpose |
|---|---|---|
| AI Contract | `Agent` | Core agent interface |
| AI Contract | `HasStructuredOutput` | Typed JSON response schema |
| AI Trait | `Promptable` | `prompt()` implementation |
| AI Schema | `JsonSchema` | Schema builder via `Illuminate\Contracts\JsonSchema\JsonSchema` |
| AI Attribute | `#[Provider(Lab::Ollama)]` | Default provider |
| AI Attribute | `#[Model('gemma4:e2b')]` | Default model |
| Service | `AiConfigHelper::configure()` | Runtime provider/model resolution |
| Service | `MsspCostingEngine` | Full cost data for JSON input |
| Eloquent | `ClientScenarioMsspDetail` | Store `ai_analysis` result |
| Controller | `MsspCostingController` | Caller + JSON payload builder |

---

## 9. Key Differences from SizingRegulator

| Aspect | OfferAnalyst | SizingRegulator |
|---|---|---|
| Domain | Commercial / Financial | Technical / Infrastructure |
| RAG | None (data self-contained in input) | Yes (InjectDocumentation) |
| Primary output | Margin, health, risks, staffing | Ratios, HA, topology |
| Triggered by | MSSP Dashboard → "Ask AI" | Sizing Dashboard → "Analyze with AI" |
| Input | Full cost model JSON | Node topology + storage JSON |
| Focus | Is this proposal profitable & viable? | Is this cluster properly sized? |
