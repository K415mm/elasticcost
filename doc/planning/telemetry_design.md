# Telemetry & Configuration Layer Design — phpkaiharness

This document details the architectural design and specifications for packaging the Telemetry Dashboard, REST APIs, and a comprehensive publishable configuration system directly inside the `phpkaiharness` package.

---

## 1. Architectural Concepts

To make `phpkaiharness` fully plug-and-play across any PHP/Laravel application, we move the Telemetry Dashboard, diagnostics endpoints, and configuration out of the host project and package them internally:
1. **Comprehensive Configuration (`config/harness.php`)**: A central configuration file defining default LLM models, providers, failover sequences, PII masking regex rules, rate limits, caches, and guardrails.
2. **Packaged Web Dashboard Route (`/harness/dashboard`)**: A self-contained, beautifully styled telemetry controller and Blade view registered directly inside the package Service Provider.
3. **Packaged REST APIs**: Exposes endpoints (`/harness/api/stats`, `/harness/api/sessions`, `/harness/api/sessions/{id}`) to query execution metrics, traces, and tool results programmatically.

---

## 2. Low-Level Specifications

### 2.1. Centralized Configuration (`packages/phpkaiharness/config/harness.php`)
A comprehensive and publishable configuration file:

```php
return [
    'default' => [
        'provider' => env('PHPKAIHARNESS_PROVIDER', 'ollama'),
        'model' => env('PHPKAIHARNESS_MODEL', 'llama3.2'),
        'max_iterations' => env('PHPKAIHARNESS_MAX_ITERATIONS', 10),
    ],

    'cache' => [
        'enabled' => env('PHPKAIHARNESS_CACHE_ENABLED', true),
        'threshold' => env('PHPKAIHARNESS_CACHE_THRESHOLD', 0.88),
        'db_path' => env('PHPKAIHARNESS_DB'),
    ],

    'pii_masking' => [
        'enabled' => env('PHPKAIHARNESS_PII_ENABLED', true),
        'patterns' => [
            'EMAIL' => '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
            'IP' => '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
            'CREDIT_CARD' => '/\b(?:\d[ \-]*?){13,16}\b/',
            'API_KEY' => '/\b[A-Za-z0-9_\-]{32,64}\b/',
        ],
    ],

    'rate_limits' => [
        'enabled' => env('PHPKAIHARNESS_RATE_LIMIT_ENABLED', true),
        'requests_per_minute' => env('PHPKAIHARNESS_RPM', 60),
        'cooldown_ms' => env('PHPKAIHARNESS_COOLDOWN_MS', 0),
    ],

    'guardrails' => [
        'enabled' => env('PHPKAIHARNESS_GUARDRAILS_ENABLED', true),
        'high_risk_tools' => ['wsl_command', 'delete_*', 'execute_*'],
        'authorized_scopes' => ['admin', 'sizing', 'analytics'],
        'tool_scope_map' => [
            'wsl_command' => ['admin'],
        ],
    ],
];
```

### 2.2. Telemetry Controller (`Phpkaiharness\Http\Controllers\HarnessTelemetryController`)
Reads stats from `MonitorReport` and handles both HTML/Blade rendering and REST API queries:

```php
namespace Phpkaiharness\Http\Controllers;

use Illuminate\Routing\Controller;
use Phpkaiharness\Monitor\MonitorReport;
use Phpkaiharness\Monitor\SqliteMonitorStore;

class HarnessTelemetryController extends Controller
{
    protected MonitorReport $report;

    public function __construct()
    {
        $dbPath = config('harness.cache.db_path') ?: SqliteMonitorStore::defaultDbPath();
        $this->report = new MonitorReport($dbPath);
    }

    public function dashboard(); // Renders blade
    public function stats();     // Returns JSON stats
    public function sessions();  // Returns JSON paginated sessions
    public function show(string $id); // Returns session details
}
```

### 2.3. Service Provider Registration (`Phpkaiharness\PhpkaiharnessServiceProvider`)
Registers and boots package configurations, views, and routes:

```php
namespace Phpkaiharness;

use Illuminate\Support\ServiceProvider;

class PhpkaiharnessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/harness.php', 'harness');
    }

    public function boot(): void
    {
        // Publish Config
        $this->publishes([
            __DIR__.'/../config/harness.php' => config_path('harness.php'),
        ], 'harness-config');

        // Load Views
        $this->loadViewsFrom(__DIR__.'/resources/views', 'harness');

        // Load Routes
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
    }
}
```

---

## 3. High-Level Telemetry Flow

```
                      Browser: /harness/dashboard
                                  │
                                  ▼
                     HarnessTelemetryController
                                  │
          ┌───────────────────────┴───────────────────────┐
          ▼                                               ▼
    MonitorReport                                  Render view('harness::dashboard')
          │                                               │
          ▼ (SQLite Query)                                ▼
    harness_sessions & details                     Chart.js + Session Log UI
```

---

## 4. Verification & Testing

- Create routing tests confirming `/harness/dashboard` is responsive and returns HTML.
- Create REST API tests asserting `/harness/api/stats` and `/harness/api/sessions` return correct JSON payloads.
- Verify config publishing works cleanly using `artisan vendor:publish`.
