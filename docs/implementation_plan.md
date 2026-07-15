# Auto-Generate Sizing Diagrams from Sizing Details

## Goal

When viewing the Sizing Details page (`/clients/{client}/scenarios/{scenario}`), automatically
generate 4 Draw.io diagrams (one per major section) and persist them to the `diagrams` table linked
to the client + scenario. On every **save layout / recalculate**, the diagrams regenerate. On
**reset to defaults**, they regenerate too. On **scenario delete**, the diagrams are cascade-deleted.

---

## Diagrams to Generate

| # | Diagram Name (template) | Type | Content Description |
|---|---|---|---|
| 1 | `{Scenario} — Log Ingestion & Source Sizing` | `log_ingestion` | Bar-chart-style flow: each log source as a box with daily GB/day volume |
| 2 | `{Scenario} — Recommended Node Specs` | `node_specs` | Table-style diagram: one box per node type with RAM/disk/count |
| 3 | `{Scenario} — Cluster Topology (Editor)` | `cluster_topology` | Swimlane diagram: tier-grouped nodes (Hot/Warm/Cold/Frozen/Master) |
| 4 | `{Scenario} — Node Clustering Topology` | `node_clustering` | Visual card layout: each node as a labelled server shape |

---

## Proposed Changes

### New `type` values for Diagram

#### [MODIFY] [Diagram.php](file:///s:/elasticcost/app/Models/Diagram.php)
- No schema changes needed — `type` is a free-form string already. The 4 new type slugs
  (`log_ingestion`, `node_specs`, `cluster_topology`, `node_clustering`) are just new values.

---

### New Service — XML Generator

#### [NEW] `app/Services/SizingDiagramService.php`
A dedicated service that accepts `$client`, `$scenario`, and `$data` (from `SizingEngine::calculate`)
and returns an array of 4 keyed Draw.io XML strings, one per diagram. All XML is generated as
valid `.drawio` (`<mxfile>` format).

Key methods:
- `generateAll(Client, Scenario, array $data): array` — returns `['log_ingestion' => '<mxfile...', ...]`
- `generateLogIngestion(array $assets, string $scenarioName): string`
- `generateNodeSpecs(array $nodes, string $scenarioName): string`
- `generateClusterTopology(array $nodes, string $scenarioName): string`
- `generateNodeClustering(array $nodes, string $scenarioName): string`

---

### Controller — New `syncDiagrams` Action

#### [MODIFY] [SizingDashboardController.php](file:///s:/elasticcost/app/Http/Controllers/SizingDashboardController.php)
Add a new `POST` action `syncDiagrams(Client, Scenario)`:
- Calls `SizingEngine::calculate` to get sizing data
- Calls `SizingDiagramService::generateAll`
- For each of the 4 diagram types:
  - Uses `updateOrCreate(['client_id', 'scenario_id', 'type'])` to upsert the diagram
- Returns JSON `{ success: true, diagrams: [{id, name, type, url}, ...] }`

Also hook into existing `saveCustomNodes` and `resetCustomNodes` to call `syncDiagrams` automatically
after saving.

---

### Routes

#### [MODIFY] `routes/web.php`
Add inside the `sizing` middleware group:
```php
Route::post('clients/{client}/scenarios/{scenario}/sync-diagrams',
    [SizingDashboardController::class, 'syncDiagrams'])
    ->name('sizing.sync-diagrams');
```

---

### Frontend — Sync Button + Auto-Sync

#### [MODIFY] [sizing.blade.php](file:///s:/elasticcost/resources/views/dashboard/sizing.blade.php)
1. Add a **"📐 Sync Diagrams"** button in the top action bar (next to "Analyze Sizing").
2. Show a compact **"Sizing Diagrams"** panel at the bottom of the page listing the 4 generated
   diagrams with links to open them in the editor (if they exist). If not yet synced, show a prompt.
3. After `Save Layout & Recalculate` form submission, trigger an AJAX `syncDiagrams` call automatically
   and refresh the diagrams panel.

---

### Cascade Delete on Scenario

#### [MODIFY] [Diagram.php](file:///s:/elasticcost/app/Models/Diagram.php)
- No change needed — diagrams are linked by `client_id` + `scenario_id`. When we delete a scenario,
  the app should delete related diagrams. We'll add a `deleting` observer or use the Scenario model.

#### [MODIFY] `app/Models/Scenario.php`
Add `protected static function booted()` with a `static::deleting` hook that calls
`Diagram::where('scenario_id', $scenario->id)->delete()` before deleting a scenario.

---

## Verification Plan

### Automated Tests
- Add `test_sync_diagrams_creates_four_diagrams` in `tests/Feature/DiagramTest.php`
- Add `test_sync_diagrams_updates_existing_diagrams` — call twice, assert count stays at 4
- Add `test_scenario_delete_cascades_diagrams`

```bash
php artisan test --compact --filter="DiagramTest"
```

### Manual Verification
1. Open a sizing details page
2. Click "📐 Sync Diagrams" — confirm 4 diagrams appear in the panel
3. Edit the Cluster Topology Editor and save — confirm diagrams auto-update
4. Open each diagram link — confirm Draw.io editor shows the correct XML content
5. Delete a scenario — confirm its 4 diagrams are removed from the database
