# ElasticCost — Full Project Analysis

> **Document Type:** Comprehensive Project Analysis  
> **Version:** 1.0  
> **Date:** 2026-06-15  
> **Stack:** Laravel 13 · PHP 8.3 · Laravel AI v0.7 · SQLite/PostgreSQL · Vite · Tailwind

---

## 1. Project Overview

**ElasticCost** is a specialized, AI-augmented **Elasticsearch Sizing & MSSP SOC Costing Calculator** built for security vendors, MSSPs (Managed Security Service Providers), and systems architects. It enables pre-sales engineers to size Elasticsearch clusters and model the full cost of a managed SOC offering — covering infrastructure, licensing, staffing, and commercial margin — then export professional proposals (Excel, Word, Markdown).

### Primary Users
| Persona | Role |
|---|---|
| Systems Architect | Designs and validates Elasticsearch cluster topologies |
| SOC Manager | Models staffing allocation and analyst cost |
| Financial Analyst / Commercial Architect | Configures margins, reviews MRC proposals |
| MSSP Pre-Sales Engineer | Generates sizing + costing proposals for clients |

---

## 2. Core Feature Summary

| Feature | Module | Description |
|---|---|---|
| Elasticsearch Sizing Dashboard | `SizingDashboardController` + `SizingEngine` | Calculates node counts, RAM/disk per tier (Hot/Warm/Cold/Frozen), ERU licensing cost |
| MSSP SOC Costing Dashboard | `MsspCostingController` + `MsspCostingEngine` | Staffing cost, infrastructure hosting, elastic license, margin/MRC calculation |
| Client Inventory Management | `ClientController`, `ClientAssetController` | Client CRUD + per-asset-type device count tracking |
| Agent & Benchmark Management | `AssetTypeController` | SIEM/MDR/EDR agent type definitions, EPS benchmarks |
| Scenario Template Management | `ScenarioController` | Reusable retention/tier scenarios (min/avg/max workload) |
| System Settings | `SystemSettingsController` | AI provider config, global pricing knobs, translations |
| File Manager & RAG Pipeline | `FileManagerController` + `ProcessDocumentJob` | Upload docs (DOCX/MD/TXT), chunk & embed for RAG |
| AI Chat (ElasticCost Assistant) | `AiChatController` + `ElasticCostAssistant` | Conversational sizing/costing Q&A, RAG-backed |
| AI Chat (RG SOC Engineer) | `AiChatController` + `RgSocEngineer` pipeline | Agentic multi-model router with tool use — DB queries, updates, client creation |
| AI Sizing Analyst | `SizingDashboardController::analyzeSizingAi` + `SizingRegulator` | Structured critique of cluster topology (JSON schema output) |
| AI Offer Analyst | `MsspCostingController::askAi` + `OfferAnalyst` | Structured critique of MSSP cost proposal (health score, risks) |
| Cloud Provider Pricing | `CloudProviderPricingService` | Matches Azure VM/disk SKUs and Elastic Cloud node prices |
| Currency Conversion | `CurrencyHelper` | USD/EUR/TND conversion in all cost outputs |
| Multi-format Export | `SizingDashboardController`, `MsspCostingController` | Excel (PhpSpreadsheet), Word (PhpWord), Markdown export |

---

## 3. Technology Stack

### Backend
| Technology | Version | Purpose |
|---|---|---|
| PHP | 8.3 | Primary backend language |
| Laravel Framework | 13.x | MVC Web Framework |
| Laravel AI | 0.7.x | AI agent SDK (tool use, embeddings, structured output) |
| Laravel Horizon | 5.x | Queue management / Redis (optional) |
| PhpSpreadsheet | 5.7 | Excel export generation |
| PhpWord | 1.4 | Word (.docx) export & document parsing |
| Predis | 3.5 | Redis client (optional cache/queue driver) |

### Frontend
| Technology | Purpose |
|---|---|
| Vite | Asset bundling |
| Tailwind CSS | Utility-first styling |
| Alpine.js | Lightweight reactivity (tabs, modals, toggles) |
| SweetAlert2 | Toast/banner notifications |
| Chart.js / Markdown rendering | Data visualization and report display |

### Database
| Option | Status |
|---|---|
| SQLite | Default / Development |
| PostgreSQL + pgvector | Production (enables native vector similarity search for RAG) |

### AI Providers
| Provider | Usage |
|---|---|
| Ollama (local) | Default — `gemma4:e2b`, `gemma-3-1b-it` for light routing |
| LM Studio (local) | Alternative local provider (OpenAI-compatible API) |
| Google Gemini | Cloud provider option |
| OpenRouter | Multi-model cloud gateway |

---

## 4. Project Directory Structure

```
elasticcost/
├── app/
│   ├── Ai/
│   │   ├── Agents/          # 7 AI agent classes
│   │   ├── Middleware/       # RAG middleware (InjectDocumentation)
│   │   └── Tools/           # 7 tool classes for agent tool-use
│   ├── Http/
│   │   ├── Controllers/     # 12 HTTP controllers
│   │   └── Client/          # HTTP client helpers (if any)
│   ├── Jobs/
│   │   └── ProcessDocumentJob.php    # Async RAG document embedding
│   ├── Models/              # 14 Eloquent models
│   ├── Providers/           # Service providers
│   └── Services/            # 7 core service classes
├── database/
│   ├── migrations/          # 19 migration files
│   └── seeders/
├── resources/
│   ├── views/               # Blade templates
│   ├── css/                 # Stylesheet source
│   └── js/                  # Frontend JS source
├── routes/
│   └── web.php              # 79-line route file (no API versioning)
├── public/
│   └── assets/json/         # xpress_azure_prices.json, elastic_cloud_prices.json
└── storage/
    └── app/private/documents/   # Uploaded documents for RAG
```

---

## 5. Database Schema Overview

| Table | Purpose |
|---|---|
| `users` | Auth (Laravel default) |
| `clients` | Client registry |
| `client_assets` | Per-client asset inventory (device counts + agent flags) |
| `asset_types` | Benchmark asset types (SIEM/EDR/MDR, EPS profiles) |
| `scenarios` | Retention/tier configuration templates |
| `client_scenario_mssp_details` | MSSP costing configuration per client/scenario |
| `client_scenario_analyst_allocations` | Analyst cost allocation (SOC role % per scenario) |
| `soc_roles` | SOC role definitions (L1, L2, L3, Engineer, Manager) |
| `global_settings` | Key-value store for all runtime config (AI provider, prices) |
| `agent_conversations` | AI chat conversation threads |
| `agent_conversation_messages` | Individual messages with role, content, meta (status/job_id) |
| `documents` | Document metadata for RAG (status, chunk_count) |
| `documentation_chunks` | Chunked text + embedding vectors for RAG similarity search |
| `translation_overrides` | Custom label/translation strings |
| `jobs` | Laravel queue jobs table |
| `cache` | Laravel cache table |

---

## 6. Key Business Logic

### Sizing Calculation Flow
```
Client Assets → EPS Profile (min/avg/max) → Daily Raw GB
→ Index Expansion (1.25x) → Daily Indexed GB
→ Tier Retention (Hot/Warm/Cold/Frozen days) → Storage per Tier
→ Node Recommendation (RAM/disk profiles, NearestVmProfile)
→ Total Cluster RAM → ERU count → Annual License Cost (USD)
```

### MSSP Cost Calculation Flow
```
SizingEngine.calculate() → Cluster topology + License cost
→ Hosting cost per node (RAM + Storage unit pricing or matched Azure VM)
→ Analyst allocation costs (salary × % × staff_count)
→ Base Monthly Cost = Analyst + Infra + License/12 + Maintenance
→ Profit margin layers (Assurance, Marketing, SOC Mgr, CEO, Fixed %)
→ Client Offered MRC = Base + Total Profit
→ Cloud Option MRC = Agent costs (SIEM/MDR/EDR per device) + Cloud subscription
```

### RAG Pipeline Flow
```
Document Upload → DocumentParser (DOCX/TXT/MD) → Text Extraction
→ ProcessDocumentJob (async) → Chunking (1200 char max)
→ Embeddings::for(chunk)->generate(provider) → Vector stored in documentation_chunks
[At query time]:
User prompt → InjectDocumentation middleware → Embedding of query
→ Cosine similarity search (pgvector: <=>) or in-memory (SQLite)
→ Top-K chunks injected into agent context
```

---

## 7. AI System Overview

The application embeds **5 distinct AI agents** and **7 AI tools**, orchestrated via the `laravel/ai` SDK:

| Agent | Type | Model | Role |
|---|---|---|---|
| `ElasticCostAssistant` | Chat + RAG + sub-agent tools | Ollama gemma4:e2b | Sizing/costing Q&A with RAG |
| `RgSocEngineer` | Multi-model router (Light) | Configurable | Routes: chat vs action |
| `SocEngineerRouter` | Classifier (structured output) | Light model | Intent classification |
| `RgSocEngineerMain` | Action executor (CanActAsTool) | Main model | DB queries + updates |
| `OfferAnalyst` | Structured output analyzer | Ollama gemma4:e2b | MSSP proposal critique |
| `SizingRegulator` | Structured output analyzer | Ollama gemma4:e2b | Cluster topology audit |
| `SocEngineerChat` | Simple chat responder | Ultra-light model | Greeting/chat replies |

---

## 8. Export Capabilities

| Format | Module | Contents |
|---|---|---|
| Excel (.xlsx) | `SizingDashboardController::exportExcel` | Full sizing breakdown, node topology, licensing |
| Excel (.xlsx) | `MsspCostingController::exportExcel` | Full MSSP proposal (infra + staffing + margins + MRC) |
| Word (.docx) | Both controllers | Formatted proposal report |
| Markdown (.md) | Both controllers | Plain-text exportable report |

---

## 9. Configuration System

All runtime configuration is stored in the `global_settings` table and managed through the System Settings UI. This covers:

- **AI Provider & Models**: `ai_provider`, `ollama_model`, `ollama_url`, `gemini_model`, `openrouter_model`, etc.
- **Multi-Agent Mode**: `ai_multi_agent_enabled`, `*_light_model` per provider
- **RAG Settings**: `ai_rag_enabled_{AgentName}`, `ai_rag_threshold_{AgentName}`, `ai_rag_max_chunks_{AgentName}`
- **Pricing Knobs**: `eru_cost_usd`, `ram_per_eru_gb`, `siem_agent_monthly_cost_per_device`, etc.
- **Currency**: exchange rates
- **Translation Overrides**: per-label custom translations

---

## 10. Testing

- **Framework**: PHPUnit 12 + Pest Laravel
- **Test database**: SQLite in-memory
- **Current coverage**: Unit/Feature test stubs present; core engines are test-covered
- **Run command**: `php artisan test --compact`
