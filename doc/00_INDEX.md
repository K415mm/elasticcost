# ElasticCost — Documentation Index

> **Project:** ElasticCost — Elasticsearch Sizing & MSSP SOC Costing Calculator  
> **Stack:** Laravel 13 · PHP 8.3 · Laravel AI v0.7 · SQLite/PostgreSQL  
> **Generated:** 2026-06-15

---

## Documentation Files

### 1. Project Analysis
| File | Description |
|---|---|
| [01_PROJECT_ANALYSIS.md](./01_PROJECT_ANALYSIS.md) | Full project analysis: features, tech stack, DB schema, business logic |
| [02_APP_ARCHITECTURE_HLA.md](./02_APP_ARCHITECTURE_HLA.md) | High-level architecture diagrams, request flows, AI pipeline, data model |

---

### 2. Feature / Module Documentation
| File | Module | Description |
|---|---|---|
| [03_FEATURE_SIZING_DASHBOARD.md](./03_FEATURE_SIZING_DASHBOARD.md) | `SizingDashboardController` + `SizingEngine` | Elasticsearch cluster sizing, node recommendations, ERU licensing |
| [04_FEATURE_MSSP_SOC_COSTING.md](./04_FEATURE_MSSP_SOC_COSTING.md) | `MsspCostingController` + `MsspCostingEngine` | SOC staffing costs, infrastructure pricing, margin model, MRC proposal |
| [05_FEATURE_CLIENT_ASSETS_SCENARIOS.md](./05_FEATURE_CLIENT_ASSETS_SCENARIOS.md) | `ClientController` + `AssetTypeController` + `ScenarioController` | Client inventory management, asset types, scenario templates |
| [06_FEATURE_SETTINGS_AND_RAG.md](./06_FEATURE_SETTINGS_AND_RAG.md) | `SystemSettingsController` + `FileManagerController` + RAG pipeline | System config, AI settings, document ingestion, vector search |

---

### 3. AI & Agent Documentation
| File | Description |
|---|---|
| [07_AI_AND_AGENT_FEATURES.md](./07_AI_AND_AGENT_FEATURES.md) | AI features overview: all agents, tools, middleware, multi-model config, queue system |

---

### 4. Per-Agent Documentation
| File | Agent | Role |
|---|---|---|
| [08_AGENT_ElasticCostAssistant.md](./08_AGENT_ElasticCostAssistant.md) | `ElasticCostAssistant` | Conversational sizing/costing Q&A with RAG |
| [09_AGENT_RgSocEngineer_Pipeline.md](./09_AGENT_RgSocEngineer_Pipeline.md) | `RgSocEngineer` + `SocEngineerRouter` + `RgSocEngineerMain` + `SocEngineerChat` | Full 3-layer agentic routing pipeline for DB operations |
| [10_AGENT_SizingRegulator.md](./10_AGENT_SizingRegulator.md) | `SizingRegulator` | Cluster topology auditor (RAM:disk ratios, HA, JVM) |
| [11_AGENT_OfferAnalyst.md](./11_AGENT_OfferAnalyst.md) | `OfferAnalyst` | MSSP proposal commercial auditor (margins, staffing, risks) |
| [12_AGENT_TOOLS.md](./12_AGENT_TOOLS.md) | All 7 AI Tools | Tool-by-tool documentation with schemas, logic, and models accessed |

---

## Quick Reference: Key Files

| Component | File | Size |
|---|---|---|
| Sizing Engine | `app/Services/SizingEngine.php` | 534 lines |
| MSSP Engine | `app/Services/MsspCostingEngine.php` | 374 lines |
| MSSP Controller | `app/Http/Controllers/MsspCostingController.php` | ~72 KB |
| Sizing Controller | `app/Http/Controllers/SizingDashboardController.php` | ~52 KB |
| AI Chat Controller | `app/Http/Controllers/AiChatController.php` | 300 lines |
| AI Config Helper | `app/Services/AiConfigHelper.php` | 234 lines |
| RAG Middleware | `app/Ai/Middleware/InjectDocumentation.php` | 96 lines |
| Document Parser | `app/Services/DocumentParser.php` | 126 lines |
| Cloud Pricing Service | `app/Services/CloudProviderPricingService.php` | 219 lines |
| Web Routes | `routes/web.php` | 79 lines |

---

## Quick Reference: Agent System

```
ElasticCostAssistant (Chat + RAG)
├── InjectDocumentation middleware
├── tool: RgSocEngineer (SOC DB engineer)
└── tool: OfferAnalyst (MSSP offer critique)

RgSocEngineer (Multi-model Router — async)
├── Fast-path keyword check
├── SocEngineerRouter (classifier — light model)
└── RgSocEngineerMain (executor — main model)
    ├── GetSystemDetailsTool
    ├── UpdateGlobalSettingTool
    ├── GetClientInventoryTool
    ├── UpdateClientInventoryTool
    ├── ModifyClientAssetAgentsTool
    ├── UpdateAnalystAllocationTool
    └── CreateClientTool

SizingRegulator (Topology Auditor — sync)
└── InjectDocumentation middleware

OfferAnalyst (Commercial Auditor — sync)
└── (No middleware — input JSON is self-contained)
```

---

## Quick Reference: Database Tables

| Table | Purpose |
|---|---|
| `clients` | Client registry |
| `client_assets` | Device inventory per client |
| `asset_types` | EPS benchmark profiles |
| `scenarios` | Retention tier templates |
| `client_scenario_mssp_details` | MSSP cost config per client/scenario |
| `client_scenario_analyst_allocations` | SOC analyst % allocations |
| `soc_roles` | L1/L2/L3/Engineer/Manager definitions |
| `global_settings` | All runtime configuration |
| `agent_conversations` | Chat threads |
| `agent_conversation_messages` | Chat messages + AI meta |
| `documents` | Uploaded reference docs |
| `documentation_chunks` | Text chunks + embeddings for RAG |
| `translation_overrides` | Custom label translations |
| `jobs` | Laravel queue jobs |
