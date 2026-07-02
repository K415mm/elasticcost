# Part 2: The Agentic AI (Antigravity) IDE Workflow

In Part 1, we saw the traditional Laravel development flow. In this guide, we explore how the same feature is added using an **Agentic AI IDE (like Antigravity)**. 

Unlike simple "autocomplete" or "chat-in-sidebar" tools, an **Agentic AI agent** can read files, write code, run commands, execute tests, and self-correct errors—all under your direct guidance and supervision. It acts as an elite, virtual pair programmer.

---

## 🧠 The Agentic Development Loop

When you work with Antigravity, the workflow shifts from *writing* code to *orchestrating and reviewing* code. The agent follows a strict **four-stage loop**:

```text
  [ User Prompt ] ---> [ Planning Mode ] ---> [ Execution Mode ] ---> [ Verification Mode ]
                           (Plans changes)        (Writes code & lints)      (Runs tests & docs)
```

Here is exactly how we implement our settings audit log feature in this environment.

---

## 1. 📋 Planning Mode (Research & Outlining)

In Antigravity, we start by describing our goal in plain English. We don't need to specify every file or migration syntax:

> **User Prompt**: "I want to create a settings audit log feature. Whenever a global setting value is updated, we should record the key, the old value, and the new value in a database table called audit_logs. Please add this feature and write tests."

### What the Agent Does
1.  **Reads the Rules (`AGENTS.md`)**: The agent first checks the project rules. It learns the tech stack (PHP 8.5, Laravel 13, Pint, PHPUnit) and styling preferences automatically.
2.  **Scans the Workspace**: The agent searches for setting models and controllers, instantly finding [app/Models/GlobalSetting.php](file:///s:/elasticcost/app/Models/GlobalSetting.php) and [app/Http/Controllers/SystemSettingsController.php](file:///s:/elasticcost/app/Http/Controllers/SystemSettingsController.php).
3.  **Generates an Implementation Plan**: The agent creates an `implementation_plan.md` artifact. This plan lists the exact files to be created and modified, along with the proposed testing strategy.
4.  **Awaiting Approval**: The agent stops and asks for your review. You have full control: you can approve the plan, request edits, or ask questions.

> [!IMPORTANT]
> By forcing a planning phase, the agent prevents "runaway code" and ensures both you and the AI align on the design before any files are changed.

---

## 2. 📝 Execution Mode (Writing Code & Formatting)

Once you click **Approve** on the implementation plan, the agent moves into execution. It creates a checklist file called `task.md` to track progress transparently.

### Step-by-Step Execution
1.  **Scaffolding**: The agent runs the Artisan generator:
    ```bash
    php artisan make:model AuditLog -m -f
    ```
    *(The IDE prompts you to approve terminal command executions, keeping you secure).*
2.  **Writing Database Schema**: The agent edits the migration file, writing the fields (`setting_key`, `old_value`, `new_value`). Because it reads your database config, it uses the correct data-types.
3.  **Writing Logic**: The agent writes the `GlobalSettingObserver` class and registers it in `AppServiceProvider`. Because the agent has read adjacent files, it uses the exact namespace formats, type declarations, and constructor promotions defined in your codebase.
4.  **Auto-Formatting**: Before finalizing any code changes, the agent automatically runs the styling fixer:
    ```bash
    vendor/bin/pint --dirty --format agent
    ```
    This guarantees that the written code matches your team's style guides with zero manual effort from you.

---

## 3. 🧪 Verification Mode (Testing & Self-Correction)

Once the code is written, the agent is responsible for proving that it works.

1.  **Writing Tests**: The agent writes `SystemSettingsAuditTest.php`, setting up the Arrange-Act-Assert blocks automatically.
2.  **Running Tests**: The agent executes the test command:
    ```bash
    php artisan test --compact --filter=SystemSettingsAuditTest
    ```
3.  **Self-Correction (The "Debug" Loop)**:
    If the test fails due to a missing class import or typo, the agent doesn't stop and ask you for help. Instead:
    *   It parses the PHPUnit exception trace.
    *   It identifies the missing import (e.g., forgotten `use App\Models\AuditLog;` in the observer).
    *   It modifies the file, runs Pint, and re-executes the test.
    *   It loops until the test passes successfully (Green).

---

## 4. 🏁 Handover & Walkthrough

Once all tests pass, the agent:
1.  Creates a `walkthrough.md` file summarizing the changes made, the tests executed, and the results.
2.  Gives you a concise summary of the finished task.

Your workspace is left clean, formatted, tested, and ready for you to review and push to your git repository.

---

## 📊 Summary: Traditional vs. Agentic Workflow

| Aspect | Traditional Laravel | Agentic AI (Antigravity) |
| :--- | :--- | :--- |
| **Code Discovery** | Developer manually searches files. | AI scans directory and locates controllers/models. |
| **Boilerplate** | Developer runs commands & types file setups. | AI runs commands & populates boilerplate. |
| **Code Style** | Manual review or pre-commit failures. | AI runs `pint` automatically on modified files. |
| **Testing** | Developer writes and runs tests manually. | AI writes, runs, and debugs tests until green. |
| **Planning** | Internal developer thoughts. | Structured `implementation_plan.md` artifact. |

In the next part, we will see how **Laravel Boost** takes this AI assistance to the next level by feeding the agent live, version-specific application data.

👉 **[Go to Part 3: Laravel Boost Workflow](file:///s:/elasticcost/doc/walkthroughs/03_laravel_boost.md)**
