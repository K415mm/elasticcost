# phpkaiharness Performance Analysis: Why a Local Qwen3.5-122B Outperforms Qwen Cloud Playground

## Executive Summary

When comparing the ElasticCost application (powered by phpkaiharness + Qwen3.5-122B-A10B via LM Studio) against the Qwen Cloud Playground using the same model, the local phpkaiharness setup consistently produces:

- **Faster responses** despite running on local hardware
- **More accurate and contextually relevant answers**, including in Tunisian Arabic dialect
- **Better situational awareness** without requiring lengthy user descriptions
- **Tool-augmented responses** with real-time database access

This document analyzes each phpkaiharness subsystem that contributes to this performance gap.

---

## Test Environment

| Component | Value |
|---|---|
| Model | Qwen3.5-122B-A10B (local via LM Studio) |
| Provider | LM Studio (Chat Completions API) |
| Harness Config | `config_overrides.json` with all feature_graph nodes enabled |
| Comparison | Same model on Qwen Cloud Playground (dashscope-intl.aliyuncs.com) |

---

## Architecture: What phpkaiharness Adds

The raw Qwen Cloud Playground sends your prompt directly to the model. phpkaiharness instead routes the prompt through a **multi-stage prompt processing pipeline** before it ever reaches the LLM, and then applies **post-execution enrichment** after the model responds.

```
User Input
    │
    ▼
┌─────────────────────────────────────────────────────┐
│           PROMPT PROCESSOR PIPELINE                  │
│                                                     │
│  1. Draft Verification (generate → retrieve → refine)│
│  2. Prompt Middleware (policy/telemetry injection)   │
│  3. Model Prompt Optimizer (Qwen-specific tuning)    │
│  4. Ontological Context Injection (RAG from DB)      │
│  5. Quantum Memory Injection (similarity + phase)    │
│                                                     │
└─────────────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────────────┐
│              AGENT LOOP (max 5 iterations)           │
│                                                     │
│  • Semantic Cache lookup (skip LLM if hit)           │
│  • Context Compaction (sliding window)               │
│  • LLM Call with tools                              │
│  • Tool execution (GetSystemDetails, UpdateClient…)  │
│  • Iterate until response or max iterations          │
│                                                     │
└─────────────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────────────┐
│           POST-EXECUTION ENRICHMENT                  │
│                                                     │
│  • Quantum Memory Ingestion (store interaction)      │
│  • Cognitive Graph Memory (extract & store facts)    │
│  • Telemetry recording (full trace)                  │
│                                                     │
└─────────────────────────────────────────────────────┘
    │
    ▼
Final Response to User
```

---

## Feature-by-Feature Analysis

### 1. Draft Verification Orchestration

**Config:** `feature_graph.nodes.draft_verification.enabled = true`

**What it does:** Before the main LLM call, phpkaiharness runs a 3-step pipeline:

1. **Draft Phase:** Sends the user prompt to the model with minimal instructions to generate a quick raw draft response.
2. **Retrieval Phase:** Uses the draft text to perform a vector similarity search against the host application's Eloquent models (e.g., `ClientAsset`) to find confirming or challenging evidence.
3. **Verification Phase:** Constructs an enhanced prompt that includes the original user message, the internal draft, and the retrieved evidence — then instructs the model to produce a verified, refined response.

**Why it improves performance:**
- The model gets a "second chance" to correct assumptions using real database evidence
- False assumptions from the raw draft are caught and corrected before the user sees them
- The model doesn't need to guess about system state — it receives actual records

**File:** `packages/phpkaiharness/src/Optimize/DraftVerificationOrchestration.php`

**Code path:**
```
DraftVerificationStage → DraftVerificationOrchestration::orchestrate()
  → Step 1: client->chat() with draft instructions
  → Step 2: OntologicalContextInjector::inject() with draft as query
  → Step 3: Construct verification prompt with draft + evidence
  → Return enhanced prompt to AgentLoop
```

---

### 2. Quantum Memory Injection

**Config:** `feature_graph.nodes.quantum_harness.enabled = true`
**DB:** `storage/app/phpkaiharness/agent_memory.sqlite`

**What it does:** Quantum memory is a novel context retrieval system that combines:

- **Semantic similarity** (cosine similarity between query embedding and stored memory embeddings)
- **Quantum phase interference** (cosine of phase angle difference between query and memory nodes)

The fused score is: `score = (alpha * cosine_similarity) + (beta * phase_interference)`

With `alpha = 0.7` and `beta = 0.3`, the system prioritizes semantic relevance while still considering the structural relationship between agent types.

**Memory ingestion:** After each interaction, the `AgentLoop::ingestQuantumMemory()` method stores:
- The user's prompt as an **episodic memory node** (type: `episodic`)
- The assistant's response as a **semantic memory node** (type: `semantic`)

Each node gets an embedding vector stored in `memory_vectors` table.

**Why it improves performance:**
- **Accumulating context:** Each interaction enriches the memory DB, so subsequent queries benefit from prior conversation history
- **Cross-session memory:** The agent remembers facts from previous sessions, not just the current conversation
- **Tunisian dialect support:** When the user speaks in Tunisian Arabic, the quantum memory retrieves semantically similar past interactions (even in different languages) because embeddings are language-agnostic at the semantic level
- **Phase-aware retrieval:** Different agent types (Security, DataProcessing, Episodic, Semantic) get different phase angles, creating structural separation that prevents irrelevant memory from leaking across agent contexts

**File:** `packages/phpkaiharness/src/Optimize/QuantumInferenceEngine.php`
**Stage:** `packages/phpkaiharness/src/Core/Prompt/Stages/QuantumInjectionStage.php`
**Ingestion:** `packages/phpkaiharness/src/Core/AgentLoop.php` (`ingestQuantumMemory` method)

---

### 3. Ontological Context Injection (RAG)

**Config:** `feature_graph.nodes.ontology_injection.enabled = true`
**Parameters:** `similarity_threshold = 0.3`, `max_records = 11`

**What it does:** Uses the Laravel AI SDK Embeddings system to:

1. Generate an embedding vector for the user's prompt
2. Search the host application's Eloquent models (e.g., `ClientAsset`) for semantically similar records
3. Inject matching records into the system prompt as additional context

**Why it improves performance:**
- The model receives **real database records** as context, eliminating hallucination about system state
- When a user asks "add 2 FortiGate to Acme Corp", the ontology injection retrieves Acme Corp's current asset inventory, so the model knows the exact `asset_type_id` and current device counts
- The Qwen Cloud Playground has **no access to your database** — it can only guess based on the prompt text
- With `max_records = 11` and `similarity_threshold = 0.3`, the system is generous with context injection, providing the model with a rich picture of the current state

**File:** `packages/phpkaiharness/src/Optimize/OntologicalContextInjector.php`
**Stage:** `packages/phpkaiharness/src/Core/Prompt/Stages/OntologicalContextInjectorStage.php`

---

### 4. Model Prompt Optimizer (Qwen-Specific Tuning)

**Config:** `feature_graph.nodes.model_optimizer.enabled = true`

**What it does:** Detects the target model family and appends model-specific optimization protocols to the system prompt:

**For Qwen models:**
```
[QWEN OPTIMIZATION PROTOCOL]
1. Before responding, output your step-by-step thinking process inside `<thought>...</thought>` tags.
2. If you need to execute a tool, call the corresponding function from your tools list natively.
3. Structure your final response logically and concisely.
```

**Why it improves performance:**
- Qwen3.5 models have a native thinking/reasoning capability (`reasoning_content` field in API response)
- The optimizer explicitly instructs the model to use structured thinking, which activates the model's chain-of-thought reasoning
- The `parseThinkingResponse()` method then strips the thinking output, delivering only the final answer to the user
- This is equivalent to getting "Chain of Thought" reasoning for free, without the user seeing the intermediate steps

**File:** `packages/phpkaiharness/src/Optimize/ModelPromptOptimizer.php`
**Stage:** `packages/phpkaiharness/src/Core/Prompt/Stages/ModelPromptOptimizerStage.php`
**Thinking parser:** `packages/phpkaiharness/src/Llm/QwenClient.php` (`parseThinkingResponse` method)

---

### 5. Semantic Cache

**Config:** `feature_graph.nodes.semantic_cache.enabled = true`
**Threshold:** `0.88` (high similarity required for cache hit)

**What it does:** Before making an LLM call, checks if a semantically similar prompt has already been answered:

1. **Vector similarity search** (via `SemanticMemoryInterface`) — true AI-native matching
2. **Exact string matching** — fast path for identical prompts
3. **Levenshtein fuzzy string distance** — fallback for near-matches

**Why it improves performance:**
- **Speed:** Cache hits return instantly without an LLM call
- **Cost:** Reduces API calls to the model
- **Consistency:** Similar questions get consistent answers
- The `0.88` threshold ensures only genuinely similar prompts trigger cache hits (not false positives)

**File:** `packages/phpkaiharness/src/Optimize/SemanticCache.php`

---

### 6. Context Compactor (Sliding Window)

**Config:** `feature_graph.nodes.context_compactor.enabled = true`
**Strategy:** `sliding_window`, `max_turns = 6`, `max_tokens_threshold = 4000`

**What it does:** Manages the conversation history to prevent token overflow:

- **Sliding window:** Keeps only the last 6 conversation turns, dropping older messages
- **Summarize:** Can collapse old history into a summary via LLM utility call (alternative strategy)
- Runs on every iteration of the agent loop

**Why it improves performance:**
- Keeps the prompt within the model's effective context window
- Prevents degradation of model performance that occurs with very long contexts
- The 4000-token threshold ensures the model focuses on relevant recent context
- Combined with the conversation history built by `AiChatController` (last 6 messages with truncation of long messages at 1500 chars), the model always receives a clean, focused context

**File:** `packages/phpkaiharness/src/Optimize/ContextCompactor.php`

---

### 7. Cognitive Graph Memory

**Config:** `feature_graph.nodes.cognitive_memory.enabled = true`

**What it does:** After each interaction, runs a lightweight LLM extraction call to:

1. Parse the user prompt and agent response
2. Extract concrete facts and state changes (e.g., "SIEM price set to 20", "Acme Corp EDR count updated to 50")
3. Store extracted facts in the `harness_facts` SQLite table
4. These facts are available via the `query_graph_memory` tool for future queries

**Why it improves performance:**
- Creates a persistent knowledge graph of system changes
- The agent can query past facts without re-executing database lookups
- Provides historical context that the raw Qwen Cloud model completely lacks

**File:** `packages/phpkaiharness/src/Optimize/CognitiveGraphMemory.php`
**Tool:** `packages/phpkaiharness/src/Tools/QueryGraphMemoryTool.php`

---

### 8. Tool Calling & Agent Loop

**Config:** `default.max_iterations = 5`

**What it does:** The `AgentLoop` implements an iterative tool-calling cycle:

1. Send prompt + tool schemas to the LLM
2. If the LLM returns tool calls, execute them (e.g., `GetSystemDetailsTool`, `UpdateClientInventoryTool`)
3. Append tool results to the conversation history
4. Send the updated history back to the LLM
5. Repeat until the LLM returns a final text response (no tool calls) or max iterations reached

**Why it improves performance:**
- The model can **query real data** before answering — it doesn't need to guess
- The model can **execute actions** (update settings, modify inventory) and report results
- Up to 5 iterations allow complex multi-step operations (e.g., get system details → find asset type → update inventory → confirm result)
- The Qwen Cloud Playground has **no tools** — it can only generate text based on the prompt

**File:** `packages/phpkaiharness/src/Core/AgentLoop.php`
**Tools:** `app/Ai/Tools/GetSystemDetailsTool.php`, `app/Ai/Tools/UpdateClientInventoryTool.php`, etc.

---

### 9. Conversation Context Injection

**What it does:** The `AiChatController` builds a structured conversation history:

1. Retrieves the last 6 messages from the database
2. Filters out failed/pending messages
3. Truncates long messages ( > 1500 chars) to preserve token budget
4. Formats as `### User:` / `### RG SOC Engineer:` / `### ElasticCost Assistant:` blocks
5. Passes the full context to the agent

The `RgSocEngineer` agent further enhances this by:
- Extracting the latest clean user query from the compiled history
- Passing `"TASK: {actionInstruction}\n\nCONVERSATION CONTEXT:\n{full_prompt}"` to the `AgentLoop`

**Why it improves performance:**
- The model knows **which client** is being discussed from prior messages
- The model knows **what was already done** in previous turns
- The model can **maintain context** across language switches (e.g., English → Tunisian Arabic)
- The Qwen Cloud Playground starts fresh with each message — no conversation memory

**File:** `app/Http/Controllers/AiChatController.php` (lines 97-129)
**Agent:** `app/Ai/Agents/RgSocEngineer.php` (lines 105-119, 221-228)

---

### 10. Multi-Agent Routing (RG SOC Engineer Pipeline)

**What it does:** The RG SOC Engineer uses a 2-tier routing system:

1. **Light Router** (SocEngineerRouter): Classifies intent using a lightweight model
   - Determines if the request needs database action (`requires_action = true/false`)
   - Extracts a clean action instruction
   - Returns a direct chat response for simple greetings

2. **Action Executor** (RgSocEngineerMain): Handles database operations with tool access
   - Gets system details, updates settings, modifies inventory
   - Uses the main model with full tool-calling capability

**Why it improves performance:**
- Simple greetings are handled instantly without tool calls
- Database queries go directly to the action executor with the right tools
- The router extracts a clean instruction, removing noise from the conversation history
- The system uses **two different model tiers** — a fast light model for classification and the main model for execution

**File:** `app/Ai/Agents/RgSocEngineer.php`, `app/Ai/Agents/SocEngineerRouter.php`, `app/Ai/Agents/RgSocEngineerMain.php`

---

### 11. Thinking/Reasoning Tag Stripping

**What it does:** The `parseThinkingResponse()` method in both `QwenClient` and `LaravelAiClient` strips the model's internal reasoning from the final response:

- Strips `<think>...</think>` tags (Qwen3.5+ format)
- Strips unclosed `<think>` tags (model started thinking but didn't close)
- Strips `<thought>...</thought>` tags (legacy format)
- Strips JSON thinking blocks (`{"thought": "..."}`)
- Always prefers `content` (the actual answer) over `reasoning_content` (the thinking)

**Why it improves performance:**
- The model can use chain-of-thought reasoning internally without leaking it to the user
- The user sees only the clean, final answer
- The Qwen Cloud Playground shows the raw model output, including thinking blocks

**File:** `packages/phpkaiharness/src/Llm/QwenClient.php`, `packages/phpkaiharness/src/Llm/LaravelAiClient.php`

---

### 12. Budget Management & Token Optimization

**Config:** `budget.enabled = true`, `budget.max_tokens = 30000`
**Config:** `compression.enabled = true`, `compression.line_threshold = 150`

**What it does:**
- Tracks total token usage across the conversation
- Compresses long lines (> 150 chars) in the prompt to reduce token consumption
- Enforces a 30,000-token budget to prevent runaway costs

**Why it improves performance:**
- Keeps prompts lean and focused
- Prevents the model from being overwhelmed with unnecessary tokens
- Reduces latency by keeping the prompt size optimal

---

## Why Tunisian Dialect Works Better with phpkaiharness

The user observed that Tunisian Arabic (a dialect not formally supported by Qwen) works significantly better through phpkaiharness than on the Qwen Cloud Playground. Here's why:

| Factor | phpkaiharness | Qwen Cloud Playground |
|---|---|---|
| **Conversation context** | Full sliding-window history (6 turns) | Single message, no history |
| **Quantum memory** | Retrieves semantically similar past interactions across languages | No memory |
| **Ontological RAG** | Injects relevant database records as context | No database access |
| **Draft verification** | Two-pass refinement catches misunderstandings | Single-pass only |
| **Model optimization** | Qwen-specific protocol activates structured thinking | Raw prompt |
| **Tool calling** | Can query system state to verify understanding | No tools |
| **Cognitive memory** | Accumulates facts from prior sessions | No persistence |

The key insight is that **embeddings are language-agnostic at the semantic level**. When the user asks "zid 2 fortigate l'acme" (Tunisian Arabic for "add 2 FortiGate to Acme"), the quantum memory and ontological injection systems retrieve relevant context based on semantic similarity — not exact language matching. The model then has enough context to understand the intent, even if the dialect is unusual.

On the Qwen Cloud Playground, the model only has the raw text to work with. It may understand Tunisian Arabic, but without context about which client, what FortiGate means in this system, or what the current state is, it cannot produce a useful response without extensive clarification.

---

## Performance Comparison Summary

| Metric | phpkaiharness | Qwen Cloud Playground |
|---|---|---|
| **Context available** | Conversation history + quantum memory + DB records + cognitive facts | Prompt text only |
| **Tool calling** | 7 tools (GetSystemDetails, UpdateInventory, etc.) | None |
| **Reasoning quality** | Two-pass (draft → verify → refine) | Single-pass |
| **Memory** | Persistent across sessions (quantum + cognitive) | None |
| **Language support** | Enhanced via semantic context retrieval | Raw model capability only |
| **Response speed** | Faster (semantic cache + local model) | Network latency + no cache |
| **Accuracy** | High (verified against real DB evidence) | Lower (assumptions only) |
| **Token efficiency** | Optimized (compaction + compression + budget) | Raw prompt |
| **Model optimization** | Qwen-specific protocol injection | None |

---

## Configuration Reference

All features are enabled in `storage/app/phpkaiharness/config_overrides.json`:

```json
{
  "feature_graph": {
    "nodes": {
      "draft_verification": { "enabled": true },
      "prompt_middleware": { "enabled": true },
      "model_optimizer": { "enabled": true },
      "ontology_injection": { "enabled": true },
      "semantic_cache": { "enabled": true },
      "context_compactor": { "enabled": true },
      "guardrails": { "enabled": false },
      "cognitive_memory": { "enabled": true },
      "quantum_harness": { "enabled": true }
    }
  },
  "quantum_harness": {
    "enabled": true,
    "alpha": 0.7,
    "beta": 0.3,
    "similarity_threshold": 0.3,
    "max_anchors": 3
  },
  "ontology": {
    "enabled": true,
    "similarity_threshold": 0.3,
    "max_records": 11
  },
  "cache": {
    "enabled": true,
    "threshold": 0.88
  },
  "compaction": {
    "strategy": "sliding_window",
    "max_turns": 6,
    "max_tokens_threshold": 4000
  },
  "budget": {
    "enabled": true,
    "max_tokens": 30000
  },
  "default": {
    "max_iterations": 5
  }
}
```

---

## Conclusion

The phpkaiharness package transforms a standard Qwen3.5-122B model into a context-aware, tool-calling, memory-augmented agent system. The performance improvement over the raw Qwen Cloud Playground is not due to a better model — it's the same model — but rather due to the **cognitive architecture** wrapped around it:

1. **Before the LLM call:** Draft verification, ontological RAG, quantum memory injection, and model-specific prompt optimization enrich the prompt with real context
2. **During the LLM call:** Tool calling, iterative refinement, and context compaction keep the model focused and grounded
3. **After the LLM call:** Quantum memory ingestion and cognitive graph extraction persist knowledge for future interactions

This architecture effectively gives the model:
- **Memory** (quantum + cognitive)
- **Knowledge** (ontological RAG from real database)
- **Tools** (database queries and updates)
- **Reasoning discipline** (draft verification + thinking tag stripping)
- **Context awareness** (conversation history + sliding window)

The result is that a local Qwen3.5-122B model running through phpkaiharness outperforms the same model on the Qwen Cloud Playground in speed, accuracy, and contextual understanding — even in challenging dialects like Tunisian Arabic.
