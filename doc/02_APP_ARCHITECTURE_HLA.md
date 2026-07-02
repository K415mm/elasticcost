# ElasticCost — High-Level Architecture (HLA)

> **Document Type:** Architecture Overview  
> **Version:** 1.0  
> **Date:** 2026-06-15

---

## 1. System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              USER BROWSER                                       │
│  (Systems Architect / SOC Manager / Financial Analyst / Pre-Sales Engineer)     │
└──────────────────────────────────┬──────────────────────────────────────────────┘
                                   │ HTTPS / HTTP
                                   ▼
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        LARAVEL 13 APPLICATION                                   │
│                                                                                 │
│  ┌───────────────────┐    ┌──────────────────────────────────────────────────┐  │
│  │    WEB ROUTES     │    │           HTTP CONTROLLERS                       │  │
│  │   (routes/web.php)│───▶│  DashboardController                            │  │
│  │                   │    │  ClientController / ClientAssetController        │  │
│  │  79 route entries │    │  SizingDashboardController  (52 KB)              │  │
│  │  No auth guards   │    │  MsspCostingController      (72 KB)              │  │
│  └───────────────────┘    │  AiChatController                                │  │
│                           │  SystemSettingsController                        │  │
│                           │  FileManagerController                           │  │
│                           │  AssetTypeController / ScenarioController        │  │
│                           │  AgentJobStatusController                        │  │
│                           └───────────────────┬──────────────────────────────┘  │
│                                               │                                 │
│          ┌────────────────────────────────────┼───────────────────────┐         │
│          ▼                                    ▼                       ▼         │
│  ┌───────────────┐                ┌─────────────────────┐  ┌─────────────────┐ │
│  │   SERVICES    │                │   AI LAYER          │  │  QUEUE / JOBS   │ │
│  │               │                │                     │  │                 │ │
│  │ SizingEngine  │                │ ElasticCostAssistant│  │ProcessDocument  │ │
│  │ MsspCosting   │                │ RgSocEngineer       │  │   Job           │ │
│  │  Engine       │                │  ├ SocEngineerRouter│  │                 │ │
│  │ CloudProvider │                │  └ RgSocEngineerMain│  │ (Laravel Queue) │ │
│  │  PricingService│               │ SizingRegulator     │  │ database/redis  │ │
│  │ AiConfigHelper│                │ OfferAnalyst        │  └────────┬────────┘ │
│  │ DocumentParser│                │ SocEngineerChat     │           │           │
│  │ CurrencyHelper│                └─────────────────────┘           │           │
│  └───────┬───────┘                         │  │                     │           │
│          │                                 │  │AI Tools             │           │
│          │                       ┌─────────┘  └──────────────┐      │           │
│          ▼                       ▼                           ▼      │           │
│  ┌───────────────┐      ┌─────────────────────────────────────────┐ │           │
│  │  ELOQUENT     │      │              AI TOOLS                   │ │           │
│  │   MODELS      │      │  GetSystemDetailsTool                   │ │           │
│  │               │      │  UpdateGlobalSettingTool                │ │           │
│  │ Client        │◀─────│  GetClientInventoryTool                 │ │           │
│  │ ClientAsset   │      │  UpdateClientInventoryTool              │ │           │
│  │ AssetType     │      │  UpdateAnalystAllocationTool            │ │           │
│  │ Scenario      │      │  ModifyClientAssetAgentsTool            │ │           │
│  │ GlobalSetting │      │  CreateClientTool                       │ │           │
│  │ SocRole       │      └─────────────────────────────────────────┘ │           │
│  │ ClientScenario│                                                   │           │
│  │  MsspDetail   │◀──────────────────────────────────────────────────┘           │
│  │ AgentConvers. │                                                               │
│  │  + Messages   │                                                               │
│  │ Document      │                                                               │
│  │ Documentation │                                                               │
│  │   Chunk       │                                                               │
│  └───────┬───────┘                                                               │
│          │                                                                       │
└──────────┼───────────────────────────────────────────────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         DATABASE LAYER                                   │
│                                                                         │
│  SQLite (dev/test)     ──OR──    PostgreSQL + pgvector (production)     │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  Tables: clients · client_assets · asset_types · scenarios       │  │
│  │          client_scenario_mssp_details                            │  │
│  │          client_scenario_analyst_allocations · soc_roles         │  │
│  │          global_settings · agent_conversations                   │  │
│  │          agent_conversation_messages · documents                 │  │
│  │          documentation_chunks (vector<1536>) · jobs · cache      │  │
│  └──────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                      AI PROVIDER LAYER                                  │
│                                                                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌────────────┐  │
│  │ Ollama       │  │  LM Studio   │  │  Google      │  │ OpenRouter │  │
│  │ (local)      │  │  (local)     │  │  Gemini      │  │  (cloud)   │  │
│  │ gemma4:e2b   │  │  Qwen / etc  │  │  (cloud)     │  │  LLaMA etc │  │
│  │ gemma-3-1b   │  │              │  │              │  │            │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  └────────────┘  │
│         ▲ Configurable at runtime via global_settings table             │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Request / Response Flow

### Standard Page Request
```
Browser GET /clients/{client}/scenarios/{scenario}
  → web.php router
    → SizingDashboardController::show()
      → SizingEngine::calculate(client, scenario)
        → ClientAsset::with(assetType)
        → GlobalSetting::getValue(...)
        → recommendNodes(...)
        → return $data
      → view('sizing.show', $data)
    ← Blade HTML rendered ← returned to browser
```

### AI Chat (Synchronous — ElasticCost Assistant)
```
Browser POST /ai-chat/message/{id}
  → AiChatController::storeMessage()
    → Build sliding-window prompt (last 6 messages)
    → AiConfigHelper::configure()   ← reads global_settings
    → ElasticCostAssistant::prompt(...)
      → InjectDocumentation middleware
        → Embeddings::for([query])->generate(provider)  ← embed query
        → DocumentationChunk similarity search (cosine)
        → Append relevant chunks to prompt
      → AI provider call (Ollama/Gemini/etc)
      → Tool call: RgSocEngineer (sub-agent) or OfferAnalyst
    → Save assistant message to DB
    ← JSON response with messages[]
```

### AI Chat (Async — RG SOC Engineer)
```
Browser POST /ai-chat/message/{id}?agent=RgSocEngineer
  → AiChatController::storeMessage()
    → Save user message (DB)
    → Create pending placeholder message (DB)
    → RgSocEngineer::queue(...) ← dispatches to queue
      → triggerBackgroundQueueWorker() (auto-starts worker on Windows)
    ← JSON { queued: true, job_id: X }

[Browser polls GET /api/agent-job-status/{jobId}]
  → AgentJobStatusController::show()
    ← { status: 'pending'|'completed'|'failed', message: {...} }

[Queue Worker processes job]:
  → RgSocEngineer::prompt()
    → Fast-path keyword check (forceAction)
    → SocEngineerRouter::prompt() → { requires_action, action_instruction, chat_response }
    → IF requires_action: RgSocEngineerMain::prompt()
      → Tool calls: GetSystemDetailsTool, UpdateGlobalSettingTool, etc.
    → ELSE: return chat_response directly
  → Update placeholder message with result (DB)
```

### Document RAG Ingestion
```
Browser POST /settings/files
  → FileManagerController::store()
    → Validate file (max 20MB, docx/pdf/md/txt)
    → Store file: storage/app/private/documents/{uuid}.ext
    → Document::create(...)
    → ProcessDocumentJob::dispatch(document_id)  ← async
    ← redirect with success flash

[Queue Worker]:
  → ProcessDocumentJob::handle()
    → DocumentParser::parse(filePath)    ← docx or plain text
    → chunkContent(text)                 ← max 1200 chars per chunk
    → foreach chunk:
        → Embeddings::for([chunk])->generate(provider)
        → DocumentationChunk::create({ embedding: vector })
    → Document::update(status: 'completed', chunk_count: N)
```

---

## 3. AI Agent Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    AI AGENT HIERARCHY                                   │
│                                                                         │
│   ElasticCostAssistant ──────────────────────────────────────────────   │
│   implements: Agent, HasMiddleware, HasTools                            │
│   middleware: [InjectDocumentation]   ← RAG injection                  │
│   tools: [RgSocEngineer, OfferAnalyst]  ← sub-agent delegation         │
│                                                                         │
│   RgSocEngineer (Light Router) ──────────────────────────────────────   │
│   implements: Agent, HasTools                                           │
│   → Fast-path keyword check (forceAction)                               │
│   → OR delegates to SocEngineerRouter (structured output classifier)   │
│     ├── requires_action=false → return chat_response directly           │
│     └── requires_action=true  → delegate to RgSocEngineerMain          │
│                                                                         │
│   SocEngineerRouter (Classifier) ────────────────────────────────────   │
│   implements: Agent, HasStructuredOutput                                │
│   schema: { requires_action, action_instruction, chat_response }        │
│   uses: Light Model (fast, cheap)                                       │
│                                                                         │
│   RgSocEngineerMain (Action Executor) ───────────────────────────────   │
│   implements: Agent, CanActAsTool, HasTools                             │
│   tool_name: "execute_action"                                           │
│   uses: Main Model (capable, slower)                                    │
│   tools: [GetSystemDetailsTool, UpdateGlobalSettingTool,                │
│           GetClientInventoryTool, UpdateClientInventoryTool,            │
│           ModifyClientAssetAgentsTool, UpdateAnalystAllocationTool,     │
│           CreateClientTool]                                             │
│                                                                         │
│   SizingRegulator (Analyst) ─────────────────────────────────────────   │
│   implements: Agent, HasMiddleware, HasStructuredOutput                 │
│   middleware: [InjectDocumentation]                                     │
│   schema: { verdict, health_score, ratio_audit, ha_check,              │
│             recommendations, full_critique }                            │
│                                                                         │
│   OfferAnalyst (Analyst) ────────────────────────────────────────────   │
│   implements: Agent, HasStructuredOutput                                │
│   schema: { health_score, margin_status, sanity_checks,                │
│             staffing_status, infrastructure_status,                    │
│             recommendations, full_critique }                            │
│                                                                         │
│   SocEngineerChat (Fallback Chat) ───────────────────────────────────   │
│   implements: Agent                                                     │
│   Ultra-light model — direct conversational replies only                │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 4. Multi-Model Configuration Architecture

```
AiConfigHelper::configureMultiModel()
  │
  ├── Reads global_settings:
  │     ai_provider      → ollama | lmstudio | gemini | openrouter
  │     *_model          → main model name
  │     *_light_model    → light/router model name
  │     ai_multi_agent_enabled → bool
  │
  ├── IF ai_multi_agent_enabled=false
  │     → main = light = same model
  │
  └── IF ai_multi_agent_enabled=true
        → main = heavy model (e.g. gemma4:e2b, qwen2.5-coder-7b)
        → light = small model (e.g. gemma-3-1b, gemini-1.5-flash)

Used by: RgSocEngineer, SocEngineerRouter, RgSocEngineerMain
```

---

## 5. Data Tier Architecture

```
Client → ClientAsset (n per client) → AssetType (benchmark profile)
            │
            ├── device_count
            ├── runs_siem_agent (bool)
            ├── runs_mdr_agent (bool)
            ├── runs_edr_agent (bool)
            └── custom_*_eps, custom_avg_event_size_bytes (overrides)

Client + Scenario → ClientScenarioMsspDetail (1 per pair)
                       │
                       ├── Hardware unit prices (RAM/NVMe/SATA/Local per GB)
                       ├── Agent pricing (SIEM/MDR/EDR per device per month)
                       ├── Cloud datacenter selection
                       ├── License sharing (is_shared, share_percentage)
                       ├── Elastic Cloud subscription tier
                       └── Profit margin percentages

ClientScenarioMsspDetail → ClientScenarioAnalystAllocation (1 per SOC role)
                               │
                               ├── soc_role_id → SocRole
                               ├── allocation_percentage
                               ├── staff_count
                               └── custom_monthly_salary
```

---

## 6. Export Architecture

```
                    SizingDashboardController
                    MsspCostingController
                          │
            ┌─────────────┼─────────────┐
            ▼             ▼             ▼
         Excel          Word         Markdown
    (PhpSpreadsheet) (PhpWord)   (direct string)
         │               │
         ▼               ▼
    response()->        response()->
    streamDownload()    streamDownload()
```

---

## 7. Queue Architecture

```
composer run dev → 4 concurrent processes:
  1. php artisan serve            ← HTTP server
  2. php artisan queue:listen     ← Queue worker (ProcessDocumentJob + AI jobs)
  3. php artisan pail             ← Log tailing
  4. npm run dev                  ← Vite hot reload

Database queue driver (default):
  jobs table → polled by worker
  AI queued responses → AgentConversationMessage.meta.job_id

Optional Redis/Horizon:
  If queue.default=redis → Laravel Horizon used
  triggerBackgroundQueueWorker() skipped (Horizon manages workers)
```

---

## 8. Security Considerations

| Aspect | Current State |
|---|---|
| Authentication | Not implemented (no auth middleware on routes) |
| Authorization | No role-based guards |
| Input validation | Present on key controllers (Request::validate) |
| SQL injection | Protected by Eloquent ORM |
| File upload | Validated (extension whitelist, size limit) |
| AI tool safety | Tools interact only with own DB; no shell execution |
| Env secrets | AI API keys stored in `global_settings` table (runtime) |

> ⚠️ **Note:** The application is intended for internal/intranet deployment. Add Laravel auth middleware before any internet-facing deployment.
