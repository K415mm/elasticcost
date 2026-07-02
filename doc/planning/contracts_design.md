# Contract Layer Architectural Design — phpkaiharness

This document details the architectural design and specifications for the enhanced Contract Layer of `phpkaiharness`.

---

## 1. Design Principles

- **Framework Agnostic**: None of the interfaces must depend directly on Laravel specific classes (such as `Illuminate\Support` or `Laravel\Ai` classes) unless they are optional adapter implementations.
- **SOLID Compliance**: Interfaces should have single responsibilities (e.g., separating history-keeping from semantic retrieval).
- **Extensibility**: Host applications can implement their own discovery, storage, or telemetry mechanisms by simply implementing these contracts.
- **PSR-14 Alignment**: Event classes are standard objects that can be dispatched by any compliant dispatcher.

---

## 2. New Contracts Specification

### 2.1. Agent Discovery Contract

#### `Phpkaiharness\Contracts\AgentDiscoveryInterface`
Allows the harness to query the host application for available agent configurations.

```php
namespace Phpkaiharness\Contracts;

interface AgentDiscoveryInterface
{
    /**
     * Discover all available agents in the host project.
     *
     * @return array<string, array{
     *     name: string,
     *     class: string,
     *     instructions: string,
     *     provider: string,
     *     model: string,
     *     tools: array<string>
     * }>
     */
    public function discover(): array;

    /**
     * Get the details of a specific agent by its name or class.
     *
     * @param string $agentName
     * @return array{
     *     name: string,
     *     class: string,
     *     instructions: string,
     *     provider: string,
     *     model: string,
     *     tools: array<string>
     * }|null
     */
    public function find(string $agentName): ?array;
}
```

---

### 2.2. Memory Contracts (Hybrid Architecture)

#### `Phpkaiharness\Contracts\MemoryInterface` (Short-Term/History)
Manages the conversation turn history, enabling saving, loading, pruning, and clearing of session messages.

```php
namespace Phpkaiharness\Contracts;

interface MemoryInterface
{
    /**
     * Retrieve the full conversation history for a given session.
     *
     * @param string $sessionId
     * @return array<array{role: string, content: string, tool_calls?: array, tool_call_id?: string, name?: string}>
     */
    public function getHistory(string $sessionId): array;

    /**
     * Append a message to the conversation history.
     *
     * @param string $sessionId
     * @param array{role: string, content: string, tool_calls?: array, tool_call_id?: string, name?: string} $message
     * @return void
     */
    public function appendMessage(string $sessionId, array $message): void;

    /**
     * Prune old history messages to fit context limits (sliding window helper).
     *
     * @param string $sessionId
     * @param int $keepTurns Number of recent turns to keep.
     * @return void
     */
    public function pruneHistory(string $sessionId, int $keepTurns): void;

    /**
     * Clear all conversation history for a given session.
     *
     * @param string $sessionId
     * @return void
     */
    public function clear(string $sessionId): void;
}
```

#### `Phpkaiharness\Contracts\SemanticMemoryInterface` (Long-Term/RAG)
Responsible for embedding-based context generation and semantic similarity search.

```php
namespace Phpkaiharness\Contracts;

interface SemanticMemoryInterface
{
    /**
     * Query semantic memories/documents relevant to a given prompt query.
     *
     * @param string $query The query prompt or text to match.
     * @param float $threshold Minimum similarity threshold (0.0 to 1.0).
     * @param int $limit Maximum number of document chunks to retrieve.
     * @return array<array{
     *     text: string,
     *     source: string,
     *     score: float
     * }>
     */
    public function search(string $query, float $threshold = 0.30, int $limit = 3): array;

    /**
     * Save a chunk of text with its corresponding embedding vector.
     *
     * @param string $text Raw text.
     * @param array<float> $embedding Mathematical vector representation.
     * @param string $source File source or identifier.
     * @return void
     */
    public function addMemory(string $text, array $embedding, string $source): void;
}
```

---

### 2.3. Event Contracts (PSR-14 Compatible)

All events will reside under `Phpkaiharness\Contracts\Event\`.

```
Phpkaiharness\Contracts\Event\
├── AgentEventInterface.php
├── AgentStartedInterface.php
├── AgentFinishedInterface.php
├── LlmCallStartedInterface.php
├── LlmCallFinishedInterface.php
├── ToolCallStartedInterface.php
└── ToolCallFinishedInterface.php
```

---

## 3. High-Level Event Flow

```mermaid
sequenceDiagram
    participant App as Host App Event Dispatcher
    participant Loop as AgentLoop
    participant LLM as LLM Client
    participant Tool as ToolRegistry / Tool

    Note over Loop: AgentLoop->run() starts
    Loop->>App: dispatch(AgentStartedInterface)

    rect rgb(240, 240, 255)
        Note over Loop: Loop Iteration Begins
        Loop->>App: dispatch(LlmCallStartedInterface)
        Loop->>LLM: chat()
        LLM-->>Loop: response payload
        Loop->>App: dispatch(LlmCallFinishedInterface)

        Note over Loop: Execute Tool Calls (if any)
        Loop->>App: dispatch(ToolCallStartedInterface)
        Loop->>Tool: execute()
        Tool-->>Loop: tool result string
        Loop->>App: dispatch(ToolCallFinishedInterface)
    end

    Note over Loop: Loop Ends / Returns
    Loop->>App: dispatch(AgentFinishedInterface)
```

---

## 4. Expected Benefits

- **Complete Decoupling**: Standalone CLI runs can use SQLite / in-memory structures, while Laravel runs can plug in Eloquent, pgvector, and native Redis/Horizon queues seamlessly without any package modification.
- **Hookability**: Developers can attach custom logging, auditing, token-budgeting, or UI-broadcasting listeners on standard PSR-14 events.
- **Enterprise Ready**: Brings OpenHarness level enterprise readiness to the PHP community.
