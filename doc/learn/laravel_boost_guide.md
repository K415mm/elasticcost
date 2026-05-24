# Laravel Boost & Manual AI Workflow Guide

This guide explains how **Laravel Boost** supercharges AI coding agents (such as Cursor, Claude Code, and Antigravity) and teaches developers how to **manually** perform the same context-gathering and verification steps when coding.

---

## 🚀 How Laravel Boost Works

Laravel Boost acts as a development bridge between your IDE/AI agent and your local Laravel application using three pillars:

1.  **Model Context Protocol (MCP) Server**: Exposes internal app functions (schema, config, logs, database queries) as structured tools that AI agents can call.
2.  **AI Guidelines (`AGENTS.md`)**: Automatically aggregates instructions from your environment (Laravel version, PHP version, testing tools, styling rules) so the AI knows how to write clean, idiomatic code for *this specific project*.
3.  **Agent Skills**: Loads contextual skills on-demand, preventing the LLM context window from becoming bloated with irrelevant guides.

---

## 🛠️ Manual Workflow: Replicating Agent Automation

AI agents automate context gathering, code generation, and verification. Below is a tutorial on how you, as a developer, can manually perform these exact steps to achieve high-quality results.

---

### 1. Database Schema Inspection
*   **What the AI does**: Calls `database-schema` to retrieve JSON metadata of tables.
*   **How you do it manually**:
    - Inspect specific table schemas using Artisan:
      ```bash
      php artisan db:table client_scenario_mssp_details
      ```
      *(This lists columns, data types, indexes, and foreign keys directly in the CLI)*
    - Or view database migrations inside the `database/migrations/` directory to understand the history and field constraints.

---

### 2. Sandbox Code Testing
*   **What the AI does**: Runs isolated code snippets via Tinker commands.
*   **How you do it manually**:
    - Use Artisan Tinker in interactive mode to test Eloquent relationships:
      ```bash
      php artisan tinker
      ```
    - Or execute a one-liner statement directly from your terminal:
      ```bash
      php artisan tinker --execute 'echo App\Models\Client::first()->name;'
      ```
      *Note: Always wrap the execute code in single quotes (`'`) to prevent your terminal shell from expanding PHP variables (like `$client`).*

---

### 3. Route Inspection
*   **What the AI does**: Evaluates route parameters to write matching controller redirects and API calls.
*   **How you do it manually**:
    - List application routes, filtering by name or HTTP method to reduce noise:
      ```bash
      php artisan route:list --path=mssp-cost
      ```
      Or filter by method:
      ```bash
      php artisan route:list --method=POST --except-vendor
      ```

---

### 4. Dynamic Translations & Custom Translator Testing
*   **What the AI does**: Inspects translation keys and overrides values in the `translation_overrides` database table.
*   **How you do it manually**:
    - Verify that translation overrides resolve correctly in different locales using Tinker:
      ```bash
      php artisan tinker --execute 'App::setLocale("fr"); echo __("messages.close");'
      ```
    - Check for active translation keys in the base localization files located under `lang/en/messages.php`, `lang/fr/messages.php`, and `lang/ar/messages.php`.

---

### 5. Code Formatting (Style Enforcement)
*   **What the AI does**: Automatically runs Pint on dirty files before finalizing.
*   **How you do it manually**:
    - Ensure your code conforms to the project's formatting standard before committing:
      ```bash
      vendor/bin/pint --dirty --format agent
      ```
      *(The `--dirty` option targets only files changed in your working copy, speeding up execution).*

---

### 6. Focused Test Execution
*   **What the AI does**: Finds and runs only the tests affected by current changes.
*   **How you do it manually**:
    - Run the entire test suite in compact mode for cleaner console output:
      ```bash
      php artisan test --compact
      ```
    - Run tests inside a single feature test class:
      ```bash
      php artisan test --compact tests/Feature/SystemSettingsTest.php
      ```
    - Filter and run a single test method:
      ```bash
      php artisan test --compact --filter=test_system_settings_update_exchange_rates
      ```

---

### 7. Formulating Guidelines for your IDE (Cursor / Windsurf)
*   **What the AI does**: Ingests guidelines from `AGENTS.md` automatically.
*   **How you do it manually**:
    - Copy rules from `AGENTS.md` and append them to your IDE configuration files:
      - For **Cursor**: Place rules inside `.cursorrules` in your project root.
      - For **Windsurf**: Place rules inside `.windsurfrules` in your project root.
      - For **Claude Code**: Let Claude read `CLAUDE.md` or `AGENTS.md` directly.
    - Explicitly state version constants (PHP 8.5, Laravel 13, PHPUnit 12) inside these rules to keep the LLM from suggesting deprecated features.
