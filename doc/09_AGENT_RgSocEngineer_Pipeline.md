# Agent Pipeline: RgSocEngineer + SocEngineerRouter + RgSocEngineerMain

> **Files:**  
> - `app/Ai/Agents/RgSocEngineer.php`  
> - `app/Ai/Agents/SocEngineerRouter.php`  
> - `app/Ai/Agents/RgSocEngineerMain.php`  
> - `app/Ai/Agents/SocEngineerChat.php`

---

## 1. Overview — The 3-Layer Pipeline

The RG SOC Engineer is a **multi-agent routing pipeline** that intelligently separates:
- **Simple chat/greetings** → answered instantly or by a tiny model
- **DB queries and updates** → executed by a capable tool-calling agent

```
User Message
      │
      ▼
[Instant Greeting Check]  ← hardcoded, 0ms
      │
      ├── IS greeting → return hardcoded welcome
      │
      ▼
[RgSocEngineer — Light Router]
      │
      ├── Fast-path keyword check (action keyword + DB target)
      │   → forceAction = true → skip router
      │
      ├── OR: SocEngineerRouter::prompt()   [Light Model]
      │         → { requires_action, action_instruction, chat_response }
      │
      ├── requires_action = false → return chat_response
      │
      └── requires_action = true
               │
               ▼
        [RgSocEngineerMain — Action Executor]  [Main Model]
               │
               ├── GetSystemDetailsTool
               ├── UpdateGlobalSettingTool
               ├── GetClientInventoryTool
               ├── UpdateClientInventoryTool
               ├── ModifyClientAssetAgentsTool
               ├── UpdateAnalystAllocationTool
               └── CreateClientTool
               │
               ▼
        AgentResponse { text: "Formatted result" }
```

---

## 2. Agent: `RgSocEngineer` (Light Router)

**File:** `app/Ai/Agents/RgSocEngineer.php`  
**Contracts:** `Agent`, `HasTools`  
**Trait:** `Promptable`

### Role
Entry point and **orchestrator** of the pipeline. Overrides the default `prompt()` method with custom routing logic.

### Custom `prompt()` Override
```php
public function prompt(string $prompt, array $attachments = [], ...): AgentResponse
{
    $config = AiConfigHelper::configureMultiModel();
    $lightProvider = $config['light']['provider'];
    $lightModel    = $config['light']['model'];

    // Fast-path keyword detection
    $hasActionKeyword = str_contains($normalizedPrompt, $actionKeyword);
    $hasDbTarget      = str_contains($normalizedPrompt, $dbTarget);
    $forceAction      = $hasActionKeyword && $hasDbTarget;

    if ($forceAction) {
        $requiresAction = true;
    } else {
        // Classify with SocEngineerRouter (light model)
        $router = new SocEngineerRouter;
        $routerResponse = $router->prompt($prompt, [], $lightProvider, $lightModel);
        $requiresAction = $routerResponse['requires_action'];
        $chatResponse   = $routerResponse['chat_response'];
    }

    if ($requiresAction) {
        // Delegate to action executor (main model)
        $mainAgent = new RgSocEngineerMain;
        return $mainAgent->prompt($actionInstruction, [], $mainProvider, $mainModel);
    }

    return new AgentResponse(..., $chatResponse, ...);
}
```

### Tools
```php
public function tools(): iterable
{
    return [new RgSocEngineerMain]; // exposed as "execute_action" tool to the LLM
}
```

### Timeout
```php
public function timeout(): int { return 300; } // 5 minutes
```

### Dynamic Provider Resolution
```php
public function provider(): string|Lab
{
    return AiConfigHelper::configureMultiModel()['light']['provider'];
}
public function model(): string
{
    return AiConfigHelper::configureMultiModel()['light']['model'];
}
```

### Action Keywords (Fast-path Detection)
```php
$actionKeywords = ['list', 'show', 'get', 'update', 'set', 'modify', 'change',
                   'enable', 'disable', 'check', 'action', 'tool', 'database',
                   'db', 'query', 'add', 'create', 'new', 'register'];

$dbTargets = ['client', 'device', 'asset', 'setting', 'price', 'salary',
              'allocation', 'count', 'agent', 'siem', 'mdr', 'edr',
              'active directory', 'status'];
```

### Instructions (System Prompt)
```
You are the "RG SOC Engineer" (Light Router)...
1. If the request requires action (DB queries, settings, updates) → delegate to execute_action
2. If the request is a simple greeting or conversational → answer directly
3. If ambiguous → delegate to execute_action
```

---

## 3. Agent: `SocEngineerRouter` (Classifier)

**File:** `app/Ai/Agents/SocEngineerRouter.php`  
**Contracts:** `Agent`, `HasStructuredOutput`  
**Trait:** `Promptable`

### Role
**Intent classifier** — uses a lightweight AI model to determine if a user message requires database/tool access or can be answered conversationally.

### Dynamic Provider
```php
public function provider() / public function model()
{
    return AiConfigHelper::configureMultiModel()['light'][...];
}
```

### Structured Output Schema
```php
public function schema(JsonSchema $schema): array
{
    return [
        'requires_action' => $schema->boolean()
            ->description('True if DB access or updates needed...')
            ->required(),
        'action_instruction' => $schema->string()
            ->description('Consolidated clean instruction for the executor...')
            ->required(),
        'chat_response' => $schema->string()
            ->description('Direct helpful response if no action needed...')
            ->required(),
    ];
}
```

### Classification Rules (System Prompt)
**Set `requires_action = true` when user asks to:**
- List, show, find, retrieve, query, or inspect clients, settings, records, SOC roles, device counts
- Update, change, set, enable, disable, assign any parameters
- Create, add, register new clients
- Use database actions or tools

**Set `requires_action = false` only when:**
- Simple greeting/pleasantry (hello, hi, thank you)
- General query not needing DB (what can you do?, what is EDR?)

---

## 4. Agent: `RgSocEngineerMain` (Action Executor)

**File:** `app/Ai/Agents/RgSocEngineerMain.php`  
**Contracts:** `Agent`, `CanActAsTool`, `HasTools`  
**Trait:** `Promptable`

### Role
The **action executor** — has full access to all DB tools and executes multi-step queries, updates, and client creation workflows.

### `CanActAsTool` — Tool Name
```php
public function name(): string { return 'execute_action'; }
public function description(): string|Stringable
{
    return 'Delegates a task that requires database access, retrieving current system settings/details, listing clients, verifying status, or making updates.';
}
```

This allows `RgSocEngineer` to expose `RgSocEngineerMain` as a callable tool to the LLM.

### Dynamic Provider
```php
public function provider() { return AiConfigHelper::configureMultiModel()['main']['provider']; }
public function model()    { return AiConfigHelper::configureMultiModel()['main']['model']; }
```

### Full Tool Suite
```php
public function tools(): iterable
{
    return [
        new GetSystemDetailsTool,         // Read all settings + clients + scenarios
        new UpdateGlobalSettingTool,       // Write a setting key-value
        new ModifyClientAssetAgentsTool,   // Toggle SIEM/MDR/EDR flags
        new GetClientInventoryTool,        // Read client asset inventory
        new UpdateClientInventoryTool,     // Update device count
        new UpdateAnalystAllocationTool,   // Update analyst allocation %
        new CreateClientTool,              // Create a new client with inventory
    ];
}
```

### Operational Playbook (System Prompt)
1. **Always inspect state first** — use `GetSystemDetailsTool` or `GetClientInventoryTool` before updating
2. **Execute changes directly** — map task type to correct tool:
   - Setting update → `UpdateGlobalSettingTool`
   - Device count update → `GetClientInventoryTool` first → `UpdateClientInventoryTool`
   - Agent coverage → `ModifyClientAssetAgentsTool`
   - Analyst allocation → `UpdateAnalystAllocationTool`
3. **Conversational client creation** — collect name + description + device counts for all asset types before calling `CreateClientTool`; never call with missing data
4. **Format results** — clean Markdown tables, bullets, exact values reported

### Timeout
```php
public function timeout(): int { return 300; } // 5 minutes
```

---

## 5. Agent: `SocEngineerChat` (Fallback Chat)

**File:** `app/Ai/Agents/SocEngineerChat.php`  
**Contracts:** `Agent`  
**Trait:** `Promptable`

### Role
Ultra-lightweight agent for simple conversational replies. Used as a fallback in the pipeline when `requires_action = false` and no routing overhead is needed.

### Provider
```php
#[Provider(Lab::Ollama)]
#[Model('gemma-3-1b-it-glm-4.7-flash-heretic-uncensored-thinking_gguf')]
```
Uses the smallest available model for minimal latency.

### Instructions
```
You are the conversational assistant for the RG SOC Engineer.
Answer the user's question directly and concisely based on the conversation history.
Do not try to perform database updates or call tools.
```

> **Note:** In current implementation, `RgSocEngineerMain` returns chat responses directly from `SocEngineerRouter.chat_response` without creating a `SocEngineerChat` instance. `SocEngineerChat` exists as a standalone fallback option.

---

## 6. Laravel Components Used — Full Reference

| Component | Class / Facade | Used In | Purpose |
|---|---|---|---|
| AI Contracts | `Agent` | All agents | Core agent interface |
| AI Contracts | `HasTools` | RgSocEngineer, RgSocEngineerMain | Declares tool list |
| AI Contracts | `CanActAsTool` | RgSocEngineerMain | Exposes agent as a callable tool |
| AI Contracts | `HasStructuredOutput` | SocEngineerRouter | JSON schema output |
| AI Trait | `Promptable` | All agents | `prompt()`, `queue()`, `withModelFailover()` |
| AI Class | `AgentPrompt` | Router | Prompt wrapper |
| AI Class | `AgentResponse` | RgSocEngineer | Manual response construction |
| AI Class | `AiManager` | AiConfigHelper | Provider instance management |
| AI Enum | `Lab` | All providers | Ollama/Gemini/OpenRouter enum |
| AI Data | `Usage`, `Meta` | RgSocEngineer | Response metadata |
| Eloquent | `GlobalSetting` | AiConfigHelper | Read AI provider settings |
| Eloquent | `Client` | Tools | Client lookups |
| Eloquent | `ClientAsset` | Tools | Asset read/write |
| Eloquent | `AssetType` | Tools | Asset type info |
| Eloquent | `Scenario` | GetSystemDetailsTool | Scenario list |
| Eloquent | `SocRole` | GetSystemDetailsTool | SOC role list |
| Eloquent | `ClientScenarioAnalystAllocation` | UpdateAnalystAllocationTool | Allocation updates |
| Eloquent | `AgentConversationMessage` | AiChatController | Message persistence |
| Support | `Str::uuid7()` | RgSocEngineer | Response ID generation |
| Support | `Schema::hasTable()` | AiConfigHelper | Migration-safe guard |
| Queue | `->queue()` | AiChatController | Async job dispatch |
| DB | `DB::table('jobs')` | AiChatController | Capture job ID for polling |
| Config | `config([...])` | AiConfigHelper | Runtime provider setup |
| Log | `\Log::warning()` | AiConfigHelper | Fallback error logging |

---

## 7. Async Queue Integration

`RgSocEngineer` uses the Laravel AI SDK's `queue()` method:
```php
(new RgSocEngineer)->queue($prompt, provider: $lightProvider, model: $lightModel)
    ->then(function ($response) use ($messageId, $conversation) {
        AgentConversationMessage::find($messageId)->update([
            'content' => $response->text,
            'meta' => ['status' => 'completed', 'job_id' => ...]
        ]);
        $conversation->touch();
    })
    ->catch(function (\Throwable $e) use ($messageId) {
        AgentConversationMessage::find($messageId)->update([
            'content' => '⚠️ Agent encountered an error: ' . $e->getMessage(),
            'meta' => ['status' => 'failed', ...]
        ]);
    });
```

After dispatch, the job ID is captured from `DB::table('jobs')->orderByDesc('id')->first()` and stored in the message's `meta` for frontend polling.

---

## 8. Conversation History Format

The prompt passed to the agent includes conversation history:
```
Below is the history of the conversation so far, followed by the latest user question. Use this context to answer the user.

### User:
{previous user message}

### RG SOC Engineer:
{previous assistant message}

### User:
{latest user message}

### RG SOC Engineer:
```

The model is expected to continue from the last `### RG SOC Engineer:` line.
