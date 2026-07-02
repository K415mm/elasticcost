# Agent: ElasticCostAssistant

> **File:** `app/Ai/Agents/ElasticCostAssistant.php`  
> **SDK Contracts:** `Agent`, `HasMiddleware`, `HasTools`  
> **Laravel AI Trait:** `Promptable`

---

## 1. Purpose

The `ElasticCostAssistant` is the primary **conversational AI** for the ElasticCost application. It answers questions about Elasticsearch sizing, MSSP SOC costing, and application features — enhanced with RAG context from uploaded reference documents and able to delegate to sub-agents.

---

## 2. Provider & Model

```php
#[Provider(Lab::Ollama)]
#[Model('gemma4:e2b')]
```

> These are **fallback** attributes. The actual provider and model are resolved at runtime via `AiConfigHelper::configure()` in `AiChatController::runSynchronous()`.

---

## 3. Laravel AI Contracts

### `Agent`
Core contract requiring `instructions(): string|Stringable` — defines the system prompt.

### `HasMiddleware`
Declares prompt middleware that intercepts the request before sending to the AI provider.

```php
public function middleware(): array
{
    return [
        new InjectDocumentation,
    ];
}
```

The `InjectDocumentation` middleware performs vector similarity search and appends relevant documentation chunks to the prompt.

### `HasTools`
Declares sub-agents or tools the agent can delegate to:

```php
public function tools(): iterable
{
    return [
        new RgSocEngineer,   // SOC database engineer sub-agent
        new OfferAnalyst,    // MSSP offer structured analysis sub-agent
    ];
}
```

> When the model decides to use a tool, it calls `RgSocEngineer` (which then runs its full routing pipeline) or `OfferAnalyst` (which performs a structured analysis).

---

## 4. System Prompt (Instructions)

The agent is configured as an elite AI chatbot with 3 knowledge domains:

1. **Elasticsearch Sizing** — RAM:disk ratios, index expansion, shards, JVM heap
2. **MSSP SOC Costing** — Staffing, hosting pricing, assurance markup, MRC
3. **Application Features** — Dashboard, exports, currency conversion, translations

**Conversational rules:**
- Respond in user's language (English, French, Arabic)
- Use Markdown formatting (bold, tables, lists, code)
- Ask for details before sizing a cluster
- Never output raw messy JSON

---

## 5. Execution Flow

```
AiChatController::runSynchronous()
  │
  ├── AiConfigHelper::configure()          ← reads global_settings
  ├── new ElasticCostAssistant
  └── $agent->prompt($prompt, provider, model, timeout=120)
        │
        ├── InjectDocumentation::handle()   ← RAG injection
        │     ├── Embeddings::for([prompt])->generate(provider)
        │     ├── similarity search in documentation_chunks
        │     └── append relevant chunks to prompt
        │
        ├── AI provider call (Ollama/Gemini/etc)
        │
        └── [IF model calls tools]:
              ├── new RgSocEngineer → route → action/chat
              └── new OfferAnalyst → structured MSSP analysis

  ← AgentResponse { text: "..." }
  → save to agent_conversation_messages
  ← JSON response to frontend
```

---

## 6. Laravel Components Used

| Component | Class/Facade | Purpose |
|---|---|---|
| Eloquent Model | `AgentConversation` | Fetch conversation thread |
| Eloquent Model | `AgentConversationMessage` | Save messages |
| Eloquent Model | `DocumentationChunk` | Vector similarity search |
| Eloquent Model | `GlobalSetting` | Read RAG config |
| Laravel AI SDK | `Embeddings` | Generate query embeddings |
| Laravel AI SDK | `Promptable` trait | `prompt()`, `withModelFailover()` |
| Laravel AI SDK | `AgentPrompt` | Prompt wrapper passed to middleware |
| DB Facade | `DB::connection()->getDriverName()` | SQLite vs pgvector routing |
| Log Facade | `\Log::warning()` | Middleware error logging |
| Service Container | `app(AiManager::class)->forgetInstance()` | Provider cache purge |

---

## 7. RAG Configuration

Controlled via `GlobalSetting`:
| Key | Default |
|---|---|
| `ai_rag_enabled_ElasticCostAssistant` | `true` |
| `ai_rag_threshold_ElasticCostAssistant` | `0.30` |
| `ai_rag_max_chunks_ElasticCostAssistant` | `3` |

---

## 8. Known Behaviors & Edge Cases

- **Timeout:** 120 seconds hard limit on synchronous call
- **Streaming:** Not implemented; full response returned at once
- **Token context:** Sliding window of last 6 messages prevents token overflow
- **Long message truncation:** Messages >1500 chars are truncated at 800 chars head + 400 chars tail
