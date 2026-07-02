# Development Workflow & Testing Cookbook

Welcome, Agent! This document is your cookbook for running, formatting, testing, and developing across this project.

---

## ⚙️ 1. Environment & Network Configuration

### WSL 2 & Windows Host Networking
The application runs across a hybrid Windows-WSL environment:
*   **Main Application**: Typically run on Windows or served in WSL, connecting to PostgreSQL (`127.0.0.1:5432`) and Redis (`127.0.0.1:6379`).
*   **LM Studio / LLM Server**: Runs on the Windows host on port `1234` or `11434`.
*   **Localhost Loopback**: In modern WSL 2 (with Mirrored Networking enabled), accessing `localhost:1234` inside WSL routes directly to the Windows host listener.
    *   *Verify reachability inside WSL:* `curl -I http://localhost:1234/v1/models`

---

## 🗄️ 2. Database & Queuing Setup

### Database Seeding
To reset and seed the main application database (PostgreSQL):
```bash
php artisan migrate:fresh --seed
```

### Starting Horizon (WSL)
Asynchronous agent jobs require Horizon to process queues:
```bash
# Start Horizon supervisor in WSL
php artisan horizon
```

---

## 🧪 3. Testing Cookbook

### Running Main Application Tests
Runs unit and feature tests across all sizing, costing, and agent integration layers:
```bash
# Run all tests (WSL or Windows Host)
php artisan test --compact
```

### Running phpkaiharness Package Tests
To test the standalone package features (including cache, compactor, and client mock adapters), run PHPUnit inside the WSL package directory:
```bash
# From /home/kais/phpkaiharness
./vendor/bin/phpunit -c phpunit.xml
```

---

## 🧹 4. Code Formatting & Pint

Always run the Laravel Pint formatter before committing any PHP files. Use the `--dirty` flag to only format modified files and avoid unrelated diff noise:
```bash
# Format modified files
vendor/bin/pint --dirty --format agent
```

---

## 🤖 5. CLI Execution Cookbook

### Testing Standalone Harness in WSL
Use the CLI harness to run agents directly in the WSL terminal. The CLI will automatically bootstrap your main application settings:
```bash
# Execute prompt with local LM Studio model
php /home/kais/phpkaiharness/bin/harness run "Check DNS records for google.com" --provider lmstudio --model gemma-3-1b-it-glm-4.7-flash-heretic-uncensored-thinking_gguf

# Verify captured sessions inside monitor DB
php /home/kais/phpkaiharness/bin/harness monitor:sessions --limit 10
```

---

## 📂 Next Steps
*   Read [05_INTEGRATION_WALKTHROUGH.md](file:///s:/elasticcost/doc/agent_handbook/05_INTEGRATION_WALKTHROUGH.md) to understand how the package connects with the main app.
