# Technical Guide: Creating Custom AI Agents in Laravel with `laravel/ai`

This guide explains how to manually design, build, configure, and invoke custom AI agents using the **`laravel/ai`** SDK. We will cover the core architecture of an agent class, annotating LLM providers and models, defining instruction layers, and dynamically injecting local files as skills or knowledge context.

---

## 1. Core Architecture of a Laravel Agent

In the `laravel/ai` SDK (version 0), an agent is a standard PHP class that implements the `Laravel\Ai\Contracts\Agent` contract and imports the `Laravel\Ai\Promptable` trait.

### Folder Structure
Agents are typically stored inside the `app/Ai/Agents/` directory:
```text
app/
└── Ai/
    └── Agents/
        ├── OfferAnalyst.php
        └── SizingRegulator.php
```

### Basic Agent Template
Here is a baseline structure for an agent:

```php
<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Ollama)]
#[Model('gemma4:e2b')]
class MyCustomAgent implements Agent
{
    use Promptable;

    /**
     * Define the system instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return "You are a helpful assistant specialized in...";
    }
}
```

---

## 2. Key API Components & Attributes

### 1. The `Agent` Contract
Enforces the implementation of the `instructions()` method. This method returns the system prompt (instructions) that dictates the agent's behavior, constraints, and output format.

### 2. The `Promptable` Trait
Imports runtime capabilities, enabling the developer to call the `.prompt()` method directly on an instance of the agent (e.g. `(new MyCustomAgent)->prompt($promptContent)`).

### 3. LLM Configuration Attributes
- `#[Provider(Lab::Ollama)]`: Configures the agent to communicate with the local Ollama backend interface.
- `#[Model('gemma4:e2b')]`: Sets the target model family (e.g., `gemma4:e2b`).

---

## 3. Dynamically Injecting Local Files as Skills / Knowledge

To add local files (like reference sheets, helper scripts, or data catalogs) as **dynamic skills/knowledge** to the agent, we can read them from the filesystem within the agent's `instructions()` method.

This makes the agent context-aware of your local codebase and docs at runtime.

### Example: Reading Reference Guides as Skills
```php
public function instructions(): Stringable|string
{
    $basePath = base_path();

    // 1. Load domain knowledge from markdown guides
    $sizingSkills = file_exists($basePath . '/doc/learn/elasticsearch_sizing_standards.md')
        ? file_get_contents($basePath . '/doc/learn/elasticsearch_sizing_standards.md')
        : 'No sizing standards loaded.';

    // 2. Load execution script context to let the agent understand diagnostic tests
    $diagnosticCode = file_exists($basePath . '/check_sizing.php')
        ? file_get_contents($basePath . '/check_sizing.php')
        : '';

    // 3. Compile the prompt
    return <<<INSTRUCTIONS
You are a Sizing Regulator Agent.

Here is the domain-specific sizing reference and code skills you should use to perform audits:

---
## SKILL 1: Elasticsearch Sizing Standards Reference
{$sizingSkills}

---
## SKILL 2: Sizing Diagnostic Script Reference
{$diagnosticCode}

---

Use the above reference data to evaluate and critique cluster sizing queries.
INSTRUCTIONS;
}
```

---

## 4. Invoking the Agent in Controllers

Once the agent class is created, you can trigger it from a Controller method by instantiating the class and calling `prompt()` with the dynamic user data.

```php
use App\Ai\Agents\SizingRegulator;
use Laravel\Ai\Enums\Lab;

public function analyzeSizing(Client $client, Scenario $scenario)
{
    // 1. Gather sizing data
    $sizingData = $this->sizingEngine->calculate($client, $scenario);

    // 2. Construct prompt
    $promptContent = "Analyze the following sizing metrics:\n" . json_encode($sizingData, JSON_PRETTY_PRINT);

    // 3. Invoke Agent
    $model = env('OLLAMA_MODEL', 'gemma4:e2b');
    $agent = new SizingRegulator();
    
    $response = $agent->prompt(
        $promptContent,
        provider: Lab::Ollama,
        model: $model,
        timeout: 120
    );

    // 4. Extract generated text
    $analysisMarkdown = $response->text;
    
    return response()->json([
        'success' => true,
        'analysis' => $analysisMarkdown,
    ]);
}
```

---

## 5. Caching and Displaying Results in the UI

1. **Database Persistence**: Add a nullable `text` column in the database (e.g., `ai_sizing_analysis`) to store the markdown analysis returned by the agent. This prevents redundant LLM calls when reloading the dashboard.
2. **Markdown Parsing**: Use standard Laravel string helpers (e.g. `Str::markdown($analysis)`) in Blade templates to render the generated Markdown response as clean, styled HTML.
