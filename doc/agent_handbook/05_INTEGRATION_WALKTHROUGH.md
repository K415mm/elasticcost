# System Integration Walkthrough

This document explains the technical bridge between the main Laravel application and the `phpkaiharness` package, detailing adapters, telemetry mapping, and bootstrapping.

---

## 🌉 1. Adapting Laravel Tools to phpkaiharness
Laravel AI SDK tools use standard contracts (`Laravel\Ai\Contracts\Tool`), but the `AgentLoop` in `phpkaiharness` requires `Phpkaiharness\Contracts\ToolInterface`.

To bridge this gap, we implemented the **[LaravelToolAdapter](file:///s:/elasticcost/app/Ai/Adapters/LaravelToolAdapter.php)**:
*   **Schema Mapping**: Dynamically reads the tool's JSON schema (arguments, types, descriptions) and adapts it.
*   **Execution Forwarding**: Wraps the Laravel tool's handler in a `Phpkaiharness` compatible request/response execution frame:
    ```php
    public function execute(array $arguments): string
    {
        $request = new \Laravel\Ai\Tools\Request($arguments);
        return (string) $this->laravelTool->handle($request);
    }
    ```

---

## 🛠️ 2. Laravel AI Client Bridge (`LaravelAiClient`)
To ensure `phpkaiharness` can use any LLM connection defined in the Laravel app (Ollama, LM Studio, Gemini, etc.), we created **[LaravelAiClient](file:///s:/elasticcost/packages/phpkaiharness/src/Llm/LaravelAiClient.php)**:

1.  **Connection Resolution**: Resolves the `AiManager` from the Laravel application container:
    ```php
    $aiManager = app(\Laravel\Ai\AiManager::class);
    $textProvider = $aiManager->textProvider($providerName);
    $textGateway = $textProvider->textGateway();
    ```
2.  **Message Transformation**: Converts the package's array-based conversation history into `UserMessage`, `AssistantMessage`, and `ToolResultMessage` objects required by the SDK.
3.  **Schema Transformation**: Wraps tool array schemas into `RawSchemaLaravelTool` structures.
4.  **Direct Generation**: Calls `generateText` directly on the gateway. This bypasses Laravel's automatic agent loop, allowing `phpkaiharness` to retain complete control over tool execution, caching, compaction, and telemetry recording.

---

## 📊 3. Dual-Telemetry Architecture
Our telemetry system routes analytics differently depending on how the agent is executed:

```
                  ┌──────────────────────┐
                  │   Agent Execution    │
                  └──────────┬───────────┘
                             │
              Is it run via CLI or Laravel?
                             │
              ┌──────────────┴──────────────┐
              ▼                             ▼
       [ CLI Runner ]               [ Laravel Application ]
              │                             │
    [ SqliteMonitorStore ]     [ LaravelAnalyticsCollector ]
              │                             │
     (Zero-dependency)             (Eloquent / ORM Layer)
              │                             │
   SQLite: ~/.phpkaiharness/...     PostgreSQL: database
```

*   **SQLite Store**: Writes tracing events to a local SQLite database when executing standalone scripts (no framework bootstrap or database dependencies required).
*   **Laravel Collector**: Connects to Laravel's database models (`HarnessSession` and `HarnessDetail`) when executing inside the Web App context.

---

## 🚀 4. CLI Bootstrapping Logic
When running the package command line (`bin/harness`) inside a standalone directory:
1.  **Composer Autoload**: Loads the package autoloader.
2.  **Root Discovery**: Checks common directories (and `LARAVEL_PATH` env variable) to locate the main Laravel project.
3.  **Application Bootstrap**: If found, it requires the main application's autoloader (making `App\` namespaces available) and bootstraps the console kernel. This initializes configurations, service bindings, database connections, and LLM providers.
