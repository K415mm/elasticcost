# Part 1: The Traditional Laravel Workflow

In this guide, we will walk through the classic, manual process of adding a new feature to our Laravel application. We will use a real-world scenario: **Adding an Audit Log feature to track changes when system settings (like exchange rates or translation overrides) are modified.**

If you have worked with Laravel before, this process will feel very familiar. It is the solid foundation that every professional developer builds upon.

---

## 📋 The Scenario
Our product manager asks us to log every update made to the global settings. We need to record:
1. Which setting key was updated.
2. What the value was before the update (old value).
3. What the new value is.
4. When the change occurred.

---

## 🔍 Step 1: Planning & Discovering the Codebase

Before writing a single line of code, we must understand how settings are currently stored and modified.

### 1. Find the Database Schema
Since we don't have an AI to tell us, we have to look for the database structures ourselves. We search our `database/migrations/` folder or open a database GUI (like TablePlus or DBeaver) to inspect the table schema. We find that global settings are stored in `global_settings`:
*   `key` (string, primary key)
*   `value` (text)
*   `description` (text, nullable)

### 2. Search for the Active Controller & Model
We search our files (e.g., using `Ctrl+P` in VS Code) for "Setting" or "GlobalSetting" and find:
*   [app/Models/GlobalSetting.php](file:///s:/elasticcost/app/Models/GlobalSetting.php)
*   [app/Http/Controllers/SystemSettingsController.php](file:///s:/elasticcost/app/Http/Controllers/SystemSettingsController.php)

We open `SystemSettingsController.php` to see how settings are updated. We see an `update` method that loops over settings sent in a request and updates them in a loop.

---

## 🛠️ Step 2: Scaffolding the New Files

Now we need to create the files for our audit log feature: a Model, a Database Migration, and a Model Factory (for testing).

We run the classic Laravel Artisan generator command in our terminal:

```bash
php artisan make:model AuditLog -m -f
```

This command creates three files:
1.  **Model**: `app/Models/AuditLog.php`
2.  **Migration**: `database/migrations/xxxx_xx_xx_xxxxxx_create_audit_logs_table.php`
3.  **Factory**: `database/database/factories/AuditLogFactory.php`

---

## 🗄️ Step 3: Designing the Migration Schema

We open the newly created migration file and manually define the schema for our audit logs:

```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->string('setting_key');
    $table->text('old_value')->nullable();
    $table->text('new_value')->nullable();
    $table->timestamps();
});
```

We save the migration and execute it against our local database:

```bash
php artisan migrate
```

---

## 📚 Step 4: Searching Documentation & Designing the Logic

We need a clean way to hook into the `GlobalSetting` update event. In Laravel, the recommended way is using **Model Observers**.

### 1. Manual Documentation Search
Since we are doing this traditionally, we:
1. Open our web browser and go to `laravel.com/docs`.
2. Select the correct Laravel version.
3. Search for "Observers".
4. Copy the code template for generating and registering an observer.

### 2. Scaffold the Observer
We run the Artisan command to scaffold our observer:

```bash
php artisan make:observer GlobalSettingObserver --model=GlobalSetting
```

### 3. Implement the Observer Logic
We open `app/Observers/GlobalSettingObserver.php` and implement the `updated` event handler. We want to capture the old and new values:

```php
namespace App\Observers;

use App\Models\GlobalSetting;
use App\Models\AuditLog;

class GlobalSettingObserver
{
    public function updated(GlobalSetting $globalSetting): void
    {
        // Only log if the value actually changed
        if ($globalSetting->isDirty('value')) {
            AuditLog::create([
                'setting_key' => $globalSetting->key,
                'old_value' => $globalSetting->getOriginal('value'),
                'new_value' => $globalSetting->value,
            ]);
        }
    }
}
```

### 4. Register the Observer
We open `app/Providers/AppServiceProvider.php` and register the observer in the `boot()` method:

```php
use App\Models\GlobalSetting;
use App\Observers\GlobalSettingObserver;

public function boot(): void
{
    GlobalSetting::observe(GlobalSettingObserver::class);
}
```

---

## 🧪 Step 5: Writing and Running Tests

To verify that our observer works, we write an automated feature test.

### 1. Create the Test File
We scaffold a feature test class:

```bash
php artisan make:test SystemSettingsAuditTest
```

### 2. Write the Test Logic
We open `tests/Feature/SystemSettingsAuditTest.php` and write a test case to simulate a setting update and assert that an audit log is created:

```php
namespace Tests\Feature;

use App\Models\GlobalSetting;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingsAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_global_setting_creates_audit_log(): void
    {
        // 1. Arrange: Create a setting
        $setting = GlobalSetting::create([
            'key' => 'exchange_rate_usd_to_eur',
            'value' => '0.85',
            'description' => 'USD to EUR Conversion Rate',
        ]);

        // 2. Act: Update the setting value
        $setting->update(['value' => '0.92']);

        // 3. Assert: Verify the audit log was created with correct values
        $this->assertDatabaseHas('audit_logs', [
            'setting_key' => 'exchange_rate_usd_to_eur',
            'old_value' => '0.85',
            'new_value' => '0.92',
        ]);
    }
}
```

### 3. Run the Test
We execute our new test in the terminal:

```bash
php artisan test --filter=SystemSettingsAuditTest
```

If it passes (Green), we are happy! If it fails (Red), we must debug.

---

## 🐛 Step 6: Debugging Issues Manually

If the test fails, we don't have an AI to read the stack trace for us. We must debug manually:
1.  **Add Debug Helpers**: We place statements like `dd($setting)` or `Log::info(...)` in our observer or test to check runtime variables.
2.  **Run Tinker**: We open `php artisan tinker` to manually update database rows and see if they crash.
3.  **Read Logs**: We open the local laravel log file `storage/logs/laravel.log` and scroll to the bottom to parse the stack trace.

---

## 🎨 Step 7: Linting and Formatting Code

Before committing, we ensure our code matches the team's style conventions. We run **Laravel Pint** to format the files:

```bash
vendor/bin/pint --dirty
```

*(The `--dirty` flag ensures we only format the files we have modified in Git, saving time).*

---

## 🚀 Step 8: Deployment to Production

Once our feature is fully tested and formatted, we deploy it to the production environment. A typical manual deployment looks like this:

1.  **Commit and Push**:
    ```bash
    git add .
    git commit -m "feat: add audit logging for global settings"
    git push origin main
    ```
2.  **SSH into Server & Pull Code**:
    ```bash
    ssh production-server
    cd /var/www/elasticcost
    git pull origin main
    ```
3.  **Install Dependencies & Build Assets**:
    ```bash
    composer install --no-dev --optimize-autoloader
    npm install && npm run build
    ```
4.  **Run Database Migrations**:
    ```bash
    php artisan migrate --force
    ```
5.  **Clear & Cache Configs**:
    ```bash
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    ```
6.  **Restart Workers & Services**:
    ```bash
    php artisan queue:restart
    sudo systemctl restart php8.5-fpm
    ```

---

## 📝 Part 1 Summary

The traditional Laravel workflow is highly structured and requires manual attention at every step. You are responsible for:
*   Searching the web/documentation.
*   Checking database tables manually.
*   Generating boilerplate files.
*   Writing test scripts and parsing error logs.
*   Formatting your code manually.

Next, we will see how an Agentic AI IDE (like Antigravity) handles the exact same feature.

👉 **[Go to Part 2: Agentic AI (Antigravity) Workflow](file:///s:/elasticcost/doc/walkthroughs/02_agentic_ai_antigravity.md)**
