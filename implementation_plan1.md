# Dynamic DataTables Integration Plan (Frontend & Backend)

This plan outlines the integration of DataTables with full backend and frontend support (server-side processing for trace sessions, and client-side processing for isolated session directories) for all telemetry tables on the dashboard.

## Proposed Changes

### 1. Phpkaiharness Package Analytics

#### [MODIFY] [MonitorReport.php](file:///s:/elasticcost/packages/phpkaiharness/src/Monitor/MonitorReport.php)
- Add `getSessionsFiltered(int $limit, int $offset, ?string $search = null, ?string $sortColumn = null, string $sortDir = 'desc')` to support paginated, searchable, and sortable queries from the SQLite database.
- Add `getSessionCountFiltered(?string $search = null)` to retrieve the filtered total record count.

---

### 2. Controller & Routing

#### [MODIFY] [HarnessTelemetryController.php](file:///s:/elasticcost/packages/phpkaiharness/src/Http/Controllers/HarnessTelemetryController.php)
- Enhance the `sessions()` method and the `api(action = sessions)` block to support DataTables parameters:
  - Read input parameters: `draw`, `start` (offset), `length` (limit), `search['value']` (search term), and `order[0]` (sort details).
  - Map column indexes to DB column names:
    - 0 -> `id`
    - 1 -> `php_session_id`
    - 2 -> `method`
    - 3 -> `prompt`
    - 4 -> `total_duration_ms`
    - 5 -> `llm_calls`
    - 6 -> `tool_calls`
    - 7 -> `created_at`
  - If session isolation is **enabled**:
    - Fetch all sessions from the isolated directory DB files.
    - Perform filtering (matching search values on ID, method, prompt) in PHP.
    - Perform sorting (using the requested column name and direction) in PHP.
    - Perform pagination (slicing by `start` and `length`) in PHP.
  - If session isolation is **disabled**:
    - Perform paginated, filtered, and sorted SQL queries using the new `MonitorReport` helper methods.
  - Format the response into the standard DataTables JSON format:
    ```json
    {
      "draw": 1,
      "recordsTotal": 100,
      "recordsFiltered": 25,
      "data": [...]
    }
    ```

---

### 3. Frontend Dashboard View

#### [MODIFY] [dashboard.blade.php](file:///s:/elasticcost/packages/phpkaiharness/resources/views/dashboard.blade.php)
- **Styles & Scripts Inclusion**:
  - Include DataTables Bootstrap 5 CSS files in the `<head>` section:
    - `vendor/harness/plugins/datatables.net-bs5/css/dataTables.bootstrap5.min.css`
    - `vendor/harness/plugins/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css`
  - Include DataTables JS files at the bottom of the body:
    - `vendor/harness/plugins/datatables.net/js/dataTables.min.js`
    - `vendor/harness/plugins/datatables.net-bs5/js/dataTables.bootstrap5.min.js`
    - `vendor/harness/plugins/datatables.net-responsive/js/dataTables.responsive.min.js`
    - `vendor/harness/plugins/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js`
- **Recent Sessions Table (`sessionTable`)**:
  - Remove PHP server-side row-rendering loops. Leave the `<tbody>` tag empty.
  - Initialize `sessionTable` using `$('#sessionTable').DataTable({ ... })` in server-side processing mode (`serverSide: true`).
  - Wire up Custom Renderers for columns to preserve all visual aspects (e.g. status badges, duration in ms, child trace indicators, interaction index pill, clickable PHP session IDs).
  - Implement a `createdRow` callback to attach click handlers (calling `loadSessionTrace`), attributes (like `data-php-session-id`), styles, and hover cursors.
- **Isolated Sessions Table (`sessionsTable`)**:
  - Update `loadIsolatedSessions()` to destroy any existing DataTable instance before populating rows, and then initialize the table as a client-side DataTable with custom sorting.

---

## Verification Plan

### Automated Tests
- Run package tests:
  ```bash
  vendor/bin/phpunit --testdox
  ```
- Run host app tests:
  ```bash
  php artisan test --compact
  ```

### Manual Verification
- Load the Telemetry Dashboard at `http://127.0.0.1:8000/harness/dashboard`.
- Verify the **Recent Sessions** table is dynamically loaded via AJAX.
- Type in the search box to check database-level filtering.
- Click headers (ID, Method, Prompt, Duration, LLM, Tools, Created) to check server-side sorting.
- Paginate through the tables using the paging controls.
- Click on an isolated session folder to verify instant table row filtering.
