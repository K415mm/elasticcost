# AI & Agent Features Overview

> **Module:** `app/Ai/` — Agents, Middleware, Tools  
> **SDK:** `laravel/ai` v0.7  
> **Date:** 2026-06-15

---

## 1. Overview

ElasticCost embeds a multi-agent AI system powered by `laravel/ai`. It exposes 4 user-facing AI features and runs 7 distinct agent classes, 1 middleware, and 7 tools.

---

## 2. User-Facing AI Features

| Feature | Trigger | Agent Used | Mode |
|---|---|---|---|
| **ElasticCost Assistant Chat** | AI Chat page → agent=ElasticCostAssistant | `ElasticCostAssistant` | Synchronous |
| **RG SOC Engineer Chat** | AI Chat page → agent=RgSocEngineer | `RgSocEngineer` pipeline | Async (queued) |
| **Sizing AI Analysis** | Sizing Dashboard → "Analyze with AI" button | `SizingRegulator` | Synchronous |
| **Offer AI Analysis** | MSSP Dashboard → "Ask AI" button | `OfferAnalyst` | Synchronous |

---

## 3. Agent Inventory

| Agent Class | Interface Contracts | Provider | Model |
|---|---|---|---|
| `ElasticCostAssistant` | `Agent, HasMiddleware, HasTools` | Configurable | Configurable |
| `RgSocEngineer` | `Agent, HasTools` | Configurable (Light) | Light Model |
| `SocEngineerRouter` | `Agent, HasStructuredOutput` | Configurable (Light) | Light Model |
| `RgSocEngineerMain` | `Agent, CanActAsTool, HasTools` | Configurable (Main) | Main Model |
| `SizingRegulator` | `Agent, HasMiddleware, HasStructuredOutput` | Ollama | gemma4:e2b |
| `OfferAnalyst` | `Agent, HasStructuredOutput` | Ollama | gemma4:e2b |
| `SocEngineerChat` | `Agent` | Ollama | gemma-3-1b-it-* |

---

## 4. AI Tool Inventory

| Tool Class | Purpose | Models Written/Read |
|---|---|---|
| `GetSystemDetailsTool` | Read all global settings, clients, scenarios, SOC roles, asset types | `GlobalSetting`, `Client`, `Scenario`, `SocRole`, `AssetType` |
| `UpdateGlobalSettingTool` | Write a single global setting key-value | `GlobalSetting` |
| `GetClientInventoryTool` | Read a client's asset inventory with device counts | `Client`, `ClientAsset`, `AssetType` |
| `UpdateClientInventoryTool` | Update device count or EPS for a specific client asset | `ClientAsset` |
| `ModifyClientAssetAgentsTool` | Toggle SIEM/MDR/EDR agent coverage on client assets | `ClientAsset` |
| `UpdateAnalystAllocationTool` | Update SOC role allocation % and staff count | `ClientScenarioAnalystAllocation` |
| `CreateClientTool` | Create new client + full asset inventory with device counts | `Client`, `ClientAsset` |

---

## 5. AI Middleware

### `InjectDocumentation`
**File:** `app/Ai/Middleware/InjectDocumentation.php`

Intercepts `AgentPrompt` before it reaches the AI provider and injects semantically relevant documentation chunks from the RAG system.

Applied to: `ElasticCostAssistant`, `SizingRegulator`

---

## 6. Multi-Model Architecture

When `ai_multi_agent_enabled = true` in `global_settings`, the system uses two separate models:

| Role | Model Size | Used For |
|---|---|---|
| **Light Model** | Small, fast | `SocEngineerRouter` classification, `RgSocEngineer` fast-path |
| **Main Model** | Large, capable | `RgSocEngineerMain` action execution, tool calls |

When disabled, both use the same model.

**Configuration service:** `AiConfigHelper::configureMultiModel()` → returns `['main' => [...], 'light' => [...]]`

---

## 7. Structured Output Agents

Three agents use `HasStructuredOutput` to return typed JSON responses:

### `SizingRegulator` Output Schema
```json
{
  "verdict": "string (Adequate|Under-provisioned|Imbalanced)",
  "health_score": "integer (1-10)",
  "ratio_audit": "array of tier audits",
  "ha_check": {
    "master_eligible_count": "integer",
    "quorum_met": "boolean",
    "remarks": "string"
  },
  "recommendations": "array of strings",
  "full_critique": "string (Markdown)"
}
```

### `OfferAnalyst` Output Schema
```json
{
  "health_score": "integer (1-10)",
  "margin_status": "string (Low|Optimal|High)",
  "sanity_checks": "array of check results",
  "staffing_status": "string (Over-allocated|Under-allocated|Balanced)",
  "infrastructure_status": "string (Wasteful|Imbalanced|Optimal)",
  "recommendations": "array of numbered strings",
  "full_critique": "string (Markdown)"
}
```

### `SocEngineerRouter` Output Schema
```json
{
  "requires_action": "boolean",
  "action_instruction": "string (clean task for executor)",
  "chat_response": "string (direct answer if no action needed)"
}
```

---

## 8. Agent Queuing & Job Status

**Async agents:** `RgSocEngineer` is always queued (never synchronous) because it may invoke multiple tool calls taking 10–120+ seconds.

**Queue flow:**
```
RgSocEngineer::queue(prompt, provider, model)
  ->then(function($response) { update message status='completed' })
  ->catch(function($e) { update message status='failed' })
```

**Job status polling:** `GET /api/agent-job-status/{jobId}`
- Returns: `{ status: 'pending'|'completed'|'failed', message: { content, html, meta } }`
- Frontend polls every 3 seconds using SweetAlert2 progress banner

**Auto-trigger queue worker (database driver):**
```php
// Windows:
pclose(popen("start /B cmd /c php artisan queue:work --once --tries=1", 'r'));
// Unix:
exec("php artisan queue:work --once --tries=1 > /dev/null 2>&1 &");
```

---

## 9. Conversation History Management

**Model:** `AgentConversation` (thread) + `AgentConversationMessage` (individual messages)

**Sliding window:** Last 6 messages fed into agent context (to preserve tokens)

**Long message truncation:**
```php
if (strlen($content) > 1500) {
    $content = substr($content, 0, 800) . "\n\n...[truncated]...\n\n" . substr($content, -400);
}
```

**Prompt construction:**
```
"Below is the history of the conversation so far, followed by the latest user question..."

### User:
{message}

### RG SOC Engineer:
{message}

### RG SOC Engineer:
```

---

## 10. AI Provider Configuration Runtime

The `AiConfigHelper::configure()` method reads `global_settings` and:
1. Determines active provider
2. Sets Laravel AI config at runtime via `config([...])` 
3. Purges cached provider instances
4. Returns `{ provider, model }`

Supported providers and their settings:

| Provider | Config Keys Set at Runtime |
|---|---|
| Ollama | `ai.providers.ollama.url`, `ai.default_for_embeddings` |
| LM Studio | `ai.providers.lmstudio.driver`, `.key`, `.url`, `.models.embeddings.default` |
| Gemini | `ai.providers.gemini.key` |
| OpenRouter | `ai.providers.openrouter.key` |

---

## 11. Fast-Path Keyword Detection (RgSocEngineer)

To avoid the latency of calling the `SocEngineerRouter` classifier for obvious action requests, `RgSocEngineer` performs a local keyword check:

**Action keywords:** `list, show, get, update, set, modify, change, enable, disable, check, action, tool, database, db, query, add, create, new, register`

**DB targets:** `client, device, asset, setting, price, salary, allocation, count, agent, siem, mdr, edr, active directory, status`

If **both** an action keyword AND a DB target are found → `forceAction = true` → skip router, go directly to `RgSocEngineerMain`.

---

## 12. Instant Greeting Bypass

For `RgSocEngineer`, simple greetings (`hi, hello, hey, greetings, yo, halo, ahoy`) bypass all AI calls and return a hardcoded response immediately:
> "Hello! I am the **RG SOC Engineer**. I can help you inspect system details, modify global settings, enable/disable security agent coverage on assets, update device counts, or manage analyst allocations..."

This eliminates a 10-30 second wait for trivial messages.
