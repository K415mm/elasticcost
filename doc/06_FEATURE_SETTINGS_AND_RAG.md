# Feature: System Settings & Configuration

> **Module:** `SystemSettingsController` + `GlobalSetting` model  
> **Routes:** `/settings/system`

---

## 1. Purpose

The System Settings module provides an admin UI to configure:
- AI provider selection and model configuration
- Global pricing parameters (ERU cost, agent prices, etc.)
- Translation label overrides (multilingual support: EN/FR/AR)
- Application-level feature flags

All settings are stored in the `global_settings` table as key-value pairs and read dynamically at runtime.

---

## 2. Routes

| Method | URI | Action | Named Route |
|---|---|---|---|
| GET | `/settings/system` | `index()` | `settings.system` |
| POST | `/settings/system` | `update()` | `settings.system.update` |
| POST | `/settings/system/translations` | `updateTranslation()` | `settings.system.translations.update` |
| POST | `/settings/system/ai` | `updateAi()` | `settings.system.ai.update` |

---

## 3. Model: `GlobalSetting`

**File:** `app/Models/GlobalSetting.php`

```php
fillable: ['key', 'value']

static getValue(string $key, mixed $default = null): mixed
static setValue(string $key, mixed $value): void
```

### All Registered Settings Keys

#### AI Provider Settings
| Key | Default | Description |
|---|---|---|
| `ai_provider` | `ollama` | Active provider: `ollama`, `lmstudio`, `gemini`, `openrouter` |
| `ollama_model` | `gemma4:e2b` | Main Ollama model |
| `ollama_light_model` | `gemma-3-1b-it-...` | Light routing model (Ollama) |
| `ollama_url` | `http://localhost:11434` | Ollama server URL |
| `lmstudio_model` | `qwen2.5-coder-7b-instruct` | Main LM Studio model |
| `lmstudio_light_model` | `gemma-3-1b-it-...` | Light model (LM Studio) |
| `lmstudio_url` | `http://localhost:1234/v1` | LM Studio API URL |
| `gemini_model` | `gemini-1.5-flash` | Gemini model |
| `gemini_light_model` | `gemini-1.5-flash` | Gemini light model |
| `gemini_api_key` | `` | Gemini API key |
| `openrouter_model` | `meta-llama/llama-3-8b-instruct:free` | OpenRouter model |
| `openrouter_light_model` | (same) | OpenRouter light model |
| `openrouter_api_key` | `` | OpenRouter API key |
| `ai_multi_agent_enabled` | `false` | Enable dual-model routing |

#### RAG Settings (per agent)
| Key | Default | Description |
|---|---|---|
| `ai_rag_enabled_ElasticCostAssistant` | `true` | RAG for chat assistant |
| `ai_rag_threshold_ElasticCostAssistant` | `0.30` | Min cosine similarity |
| `ai_rag_max_chunks_ElasticCostAssistant` | `3` | Max context chunks |
| `ai_rag_enabled_SizingRegulator` | `true` | RAG for sizing auditor |
| (same pattern for each agent) | | |

#### Sizing & Pricing Settings
| Key | Default | Description |
|---|---|---|
| `eru_cost_usd` | `14000` | Cost per ERU (Elastic Resource Unit) in USD |
| `ram_per_eru_gb` | `64` | GB of RAM per ERU |
| `index_expansion_factor` | `1.25` | Index metadata overhead multiplier |
| `siem_agent_monthly_cost_per_device` | `15.00` | SIEM agent monthly fee per device |
| `mdr_agent_monthly_cost_per_device` | `30.00` | MDR agent monthly fee per device |
| `edr_agent_monthly_cost_per_device` | `10.00` | EDR agent monthly fee per device |
| `elastic_cloud_monthly_cost_per_gb_ram` | `45.00` | Elastic Cloud RAM pricing |

---

## 4. `AiConfigHelper` — Runtime AI Configuration

**File:** `app/Services/AiConfigHelper.php` (234 lines)

This service is called at every AI request to:
1. Read `ai_provider` from `global_settings`
2. Configure the appropriate Laravel AI SDK provider (Ollama/LMStudio/Gemini/OpenRouter) at runtime via `config([...])`
3. Purge resolved provider instances: `app(AiManager::class)->forgetInstance(...)`
4. Return `['provider' => ..., 'model' => ...]` for use by agents

**WSL URL Resolution:**  
When running inside WSL, `resolveUrlForEnvironment()` auto-detects the Windows host IP by reading `/proc/net/route` and replaces `localhost` with the gateway IP if needed.

---

## 5. Translation System

**Model:** `TranslationOverride`  
**File:** `app/Models/TranslationOverride.php`

```php
fillable: ['key', 'locale', 'value']
```

**Service:** `CustomTranslator`  
**File:** `app/Services/CustomTranslator.php`

- Overrides standard Laravel `trans()` calls
- Checks `translation_overrides` table first, falls back to lang files
- Supports: `en`, `fr`, `ar`

**UI labels that can be overridden:** pricing terms, node role names, tier labels, report section headings.

---

# Feature: File Manager & RAG System

> **Module:** `FileManagerController` + `ProcessDocumentJob` + `DocumentParser` + `InjectDocumentation`  
> **Routes:** `/settings/files`

---

## 1. Purpose

The File Manager allows uploading technical reference documents (DOCX, MD, TXT) which are:
1. **Parsed** into plain text
2. **Chunked** into ~1200-char segments
3. **Embedded** into vector representations using the active AI provider
4. **Stored** in `documentation_chunks` with their vector
5. **Retrieved** at query time via cosine similarity search and **injected** into AI agent context (RAG)

This makes AI agents (ElasticCostAssistant, SizingRegulator) context-aware of custom technical guides.

---

## 2. Routes

| Method | URI | Action | Named Route |
|---|---|---|---|
| GET | `/settings/files` | `index()` | `settings.files` |
| POST | `/settings/files` | `store()` | `settings.files.store` |
| DELETE | `/settings/files/{document}` | `destroy()` | `settings.files.destroy` |
| POST | `/settings/files/agent-config` | `updateAgentConfig()` | `settings.files.agent-config` |
| GET | `/settings/files/{document}/chunks` | `showChunks()` | `settings.files.chunks` |

---

## 3. Document Lifecycle

```
Upload → validate → store file → Document::create(status='pending')
  → ProcessDocumentJob::dispatch(documentId)  ← async queue
  
[Queue Worker]:
  → DocumentParser::parse(filePath)
    → .docx: PhpWord IOFactory or ZipArchive direct XML extraction
    → others: file_get_contents()
  → chunkContent(text) → max 1200 chars, split on paragraph breaks
  → foreach chunk:
      → Embeddings::for([chunk])->generate(provider)
      → DocumentationChunk::create({ document_id, source_file, chunk_text, embedding })
  → Document::update(status='completed', chunk_count=N)
```

---

## 4. Models

### `Document`
**File:** `app/Models/Document.php`
```php
fillable: ['original_name', 'filename', 'mime_type', 'size', 'status', 'chunk_count', 'error_message']
status: 'pending' | 'processing' | 'completed' | 'failed'
```

### `DocumentationChunk`
**File:** `app/Models/DocumentationChunk.php`
```php
fillable: ['document_id', 'source_file', 'chunk_text', 'embedding']
casts: ['embedding' => 'array']

Methods:
  similarity(array $queryVector): float   // cosine similarity (SQLite fallback)
```

Vector storage:
- **SQLite**: stored as JSON array, compared in PHP via `similarity()` method
- **PostgreSQL**: `vector(1536)` column type, compared via pgvector `<=>` operator

---

## 5. RAG Injection Middleware

**File:** `app/Ai/Middleware/InjectDocumentation.php`

Implements the Laravel AI `middleware` contract. Applied to `ElasticCostAssistant` and `SizingRegulator`.

**Flow:**
```php
handle(AgentPrompt $prompt, Closure $next):
  1. Check ai_rag_enabled_{AgentName} → skip if disabled
  2. Read threshold (default 0.30) and maxChunks (default 3) from GlobalSetting
  3. Embeddings::for([$prompt->prompt])->generate($provider) → queryVector
  4. IF sqlite: in-memory cosine similarity scan on all chunks
     IF postgres: SELECT * with (1 - embedding <=> queryVector) >= threshold ORDER BY distance LIMIT N
  5. Append matching chunks to prompt:
     "\n## REFERENCED DOCUMENTATION SECTION\n... (Source: file, Score: 0.85) ---\n{chunk_text}\n"
  6. next($prompt)
```

---

## 6. `DocumentParser` Service

**File:** `app/Services/DocumentParser.php`

Supports:
- `.docx` → PhpWord `IOFactory::load()` → iterate sections/elements → `getText()`
  - Fallback: ZipArchive → extract `word/document.xml` → strip namespace prefixes → DOMDocument parse → `<w:t>` text extraction
- All other extensions → `file_get_contents()` (txt, md, json, csv, html)

---

## 7. Agent RAG Configuration per Agent

Via `/settings/files/agent-config` (POST), users can configure RAG per agent:
- Enable/disable RAG for each agent independently
- Set similarity threshold (0.0 – 1.0)
- Set max chunks injected into context (1–10)
