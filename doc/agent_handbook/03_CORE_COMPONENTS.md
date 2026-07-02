# Core Components & Calculations

This document details the core classes, calculations, and structural components of the project.

---

## 🧮 1. Mathematical Formulas & Calculations

### Elasticsearch Sizing calculations
*   **Raw Daily Volume (GB)**: User-defined ingestion throughput.
*   **Index Expansion Factor**: Accounts for metadata, mapping overhead, and primary/replica structures (Default: `1.25`).
*   **RAM Sizing**: Memory allocation is calculated using Elastic Resource Units (ERU).
    *   1 ERU = `64GB RAM`.
*   **Storage Scale-Up**: Cold/Frozen nodes scale storage allocations based on long-term retention policies.

### MSSP SOC Costing margins
*   **Staff Monthly Cost**: Cumulative salaries of assigned SOC roles (e.g. L1 Analyst, L2 Analyst, Threat Hunter).
*   **Staff Count Multiplier**: Multiplies staff costs based on the client scale.
*   **License Monthly Cost**: Device count multiplied by the selected agent tier (SIEM: `$15`, MDR: `$30`, EDR: `$10`).
*   **License Cost Sharing**: Split between client and provider based on percentages:
    $$\text{Provider License Cost} = \text{Total License Cost} \times \text{Sharing Percentage}$$
*   **Margin & Price Calculation**:
    $$\text{Total Cost} = \text{Provider License Cost} + (\text{Staff Monthly Cost} \times \text{Staff Multiplier})$$
    $$\text{Target Monthly Price} = \frac{\text{Total Cost}}{1 - \text{Profit Margin}}$$

---

## 🤖 2. Active AI Agents (`app/Ai/Agents`)

*   **[ElasticCostAssistant](file:///s:/elasticcost/app/Ai/Agents/ElasticCostAssistant.php)**: General Q&A assistant. Leverages parsed document chunks (RAG) to provide contextual answers about sizing policies.
*   **[RgSocEngineer](file:///s:/elasticcost/app/Ai/Agents/RgSocEngineer.php)**: Primary SOC router. Classifies user input into either conversational chat or action execution paths.
*   **[RgSocEngineerMain](file:///s:/elasticcost/app/Ai/Agents/RgSocEngineerMain.php)**: The main action agent. Holds the actual business tools for modifying client parameters, updating system costs, and allocating analysts.
*   **[SizingRegulator](file:///s:/elasticcost/app/Ai/Agents/SizingRegulator.php)**: Recommends cluster sizing configurations based on VM specifications.
*   **[OfferAnalyst](file:///s:/elasticcost/app/Ai/Agents/OfferAnalyst.php)**: Reviews cost metrics and creates pricing optimization reports.

---

## 📦 3. Package Structure (`packages/phpkaiharness/src`)

The standalone package isolates execution logic to remain decoupled from the framework:

```
src/
├── Contracts/
│   ├── AnalyticsCollectorInterface.php  (Interface for telemetry tracking)
│   └── LlmClientInterface.php           (Interface for LLM chat gateways)
├── Core/
│   ├── AgentLoop.php                    (Core orchestration loop)
│   └── Registry/
│       └── ToolRegistry.php             (Attaches and registers tools)
├── Llm/
│   ├── LaravelAiClient.php              (Adapts laravel/ai SDK)
│   ├── LmStudioClient.php               (Standalone OpenAI-compatible client)
│   └── OllamaClient.php                 (Standalone Ollama client)
├── Monitor/
│   ├── SqliteMonitorStore.php           (SQLite database writer)
│   └── MonitorReport.php                (Aggregates telemetry stats)
├── Optimize/
│   ├── ContextCompactor.php             (Prunes window turns)
│   ├── Guardrails.php                   (Blocks injection parameters)
│   └── SemanticCache.php                (Saves LLM responses in SQLite)
└── Tools/
    └── WslCommandTool.php               (WSL security diagnostic command runner)
```

---

## 📂 Next Steps
*   Read [04_DEVELOPMENT_WORKFLOW.md](file:///s:/elasticcost/doc/agent_handbook/04_DEVELOPMENT_WORKFLOW.md) for how to run and test these components.
*   Read [05_INTEGRATION_WALKTHROUGH.md](file:///s:/elasticcost/doc/agent_handbook/05_INTEGRATION_WALKTHROUGH.md) to see how the agents integrate with the package.
