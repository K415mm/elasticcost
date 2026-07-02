# AI Tools Documentation

> **Directory:** `app/Ai/Tools/`  
> **SDK Contract:** `Laravel\Ai\Contracts\Tool`  
> **Used By:** `RgSocEngineerMain` agent

---

## Overview

All 7 tools implement the `Tool` contract from `laravel/ai`:
```php
interface Tool {
    public function description(): string|Stringable;
    public function handle(Request $request): string|Stringable;
    public function schema(JsonSchema $schema): array;
}
```

Tools are **called by the LLM** (not PHP code directly) when the agent decides to use them. The `description()` and `schema()` methods are sent to the model so it knows when and how to call each tool.

---

## Tool 1: `GetSystemDetailsTool`

**File:** `app/Ai/Tools/GetSystemDetailsTool.php`

### Purpose
Exposes a complete snapshot of the current system state to the agent — making it context-aware before taking any action.

### Description (sent to LLM)
> "Exposes system settings, active rates, scenario IDs, active clients, SOC roles, and current agent costs to make the agent context-aware."

### Schema
No arguments required (`schema()` returns `[]`).

### Handle
```php
public function handle(Request $request): string
{
    $settings   = GlobalSetting::all()->pluck('value', 'key')->toArray();
    $clients    = Client::all()->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->toArray();
    $scenarios  = Scenario::all()->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->toArray();
    $roles      = SocRole::all()->map(fn($r) => ['id' => $r->id, 'name' => $r->name, 'default_salary' => $r->default_monthly_salary])->toArray();
    $assetTypes = AssetType::all()->map(fn($t) => ['id' => $t->id, 'name' => $t->name, ...])->toArray();

    return json_encode([
        'settings'    => $settings,
        'clients'     => $clients,
        'scenarios'   => $scenarios,
        'soc_roles'   => $roles,
        'asset_types' => $assetTypes,
    ], JSON_PRETTY_PRINT);
}
```

### Models Accessed
| Model | Operation |
|---|---|
| `GlobalSetting` | Read all key-value settings |
| `Client` | Read all client names/IDs |
| `Scenario` | Read all scenario names/IDs |
| `SocRole` | Read all SOC roles + default salaries |
| `AssetType` | Read all asset types + calibration modes |

---

## Tool 2: `UpdateGlobalSettingTool`

**File:** `app/Ai/Tools/UpdateGlobalSettingTool.php`

### Purpose
Writes or updates a single key-value pair in `global_settings`. Used to change pricing, AI config, or feature flags via natural language.

### Description (sent to LLM)
> "Updates or creates a global application setting by key-value pair. Use this to change pricing parameters, AI provider settings, or feature flags."

### Schema
```php
[
    'key'   => $schema->string()->description('The setting key to update.')->required(),
    'value' => $schema->string()->description('The new value for the setting.')->required(),
]
```

### Handle
```php
public function handle(Request $request): string
{
    $key   = $request['key'];
    $value = $request['value'];
    GlobalSetting::setValue($key, $value);
    return "Successfully updated setting '{$key}' to '{$value}'.";
}
```

### Models Accessed
| Model | Operation |
|---|---|
| `GlobalSetting` | `updateOrCreate(['key' => $key], ['value' => $value])` |

---

## Tool 3: `GetClientInventoryTool`

**File:** `app/Ai/Tools/GetClientInventoryTool.php`

### Purpose
Retrieves a specific client's complete asset inventory — device counts, agent flags, and asset type details — so the agent can make informed updates.

### Description (sent to LLM)
> "Retrieves the complete asset inventory for a specific client, including device counts, agent coverage flags, and asset type IDs."

### Schema
```php
[
    'client_id' => $schema->integer()->description('The ID of the client.')->required(),
]
```

### Handle
Returns JSON of client's assets with: `asset_id, asset_type_id, asset_type_name, device_count, runs_siem_agent, runs_mdr_agent, runs_edr_agent`, plus custom EPS overrides.

### Models Accessed
| Model | Operation |
|---|---|
| `Client` | Find by ID |
| `ClientAsset` | `->with('assetType')->get()` |

---

## Tool 4: `UpdateClientInventoryTool`

**File:** `app/Ai/Tools/UpdateClientInventoryTool.php`

### Purpose
Updates the device count (and optionally custom EPS values) for a specific asset in a client's inventory.

### Description (sent to LLM)
> "Updates the device count or EPS override values for a specific client asset in the inventory."

### Schema
```php
[
    'asset_id'      => $schema->integer()->description('The ClientAsset ID')->required(),
    'device_count'  => $schema->integer()->description('New device count')->required(),
    'custom_min_eps' => $schema->number()->description('Optional custom min EPS override'),
    'custom_avg_eps' => $schema->number()->description('Optional custom avg EPS override'),
    'custom_max_eps' => $schema->number()->description('Optional custom max EPS override'),
]
```

### Handle
```php
$asset->update([
    'device_count'   => $request['device_count'],
    'custom_min_eps' => $request['custom_min_eps'] ?? $asset->custom_min_eps,
    // ...
]);
return "Successfully updated asset ID {$asset->id}...";
```

### Models Accessed
| Model | Operation |
|---|---|
| `ClientAsset` | `find($id)` then `update()` |

---

## Tool 5: `ModifyClientAssetAgentsTool`

**File:** `app/Ai/Tools/ModifyClientAssetAgentsTool.php`

### Purpose
Toggles SIEM, MDR, and/or EDR agent coverage flags on one or more client assets. This directly affects the `cloud_option` agent cost calculation in `MsspCostingEngine`.

### Description (sent to LLM)
> "Enables or disables SIEM, MDR, or EDR agent coverage on one or more client assets."

### Schema
```php
[
    'asset_ids'        => $schema->string()->description('Comma-separated ClientAsset IDs')->required(),
    'runs_siem_agent'  => $schema->boolean()->description('Set SIEM agent coverage'),
    'runs_mdr_agent'   => $schema->boolean()->description('Set MDR agent coverage'),
    'runs_edr_agent'   => $schema->boolean()->description('Set EDR agent coverage'),
]
```

### Handle
```php
$ids = array_map('intval', explode(',', $request['asset_ids']));
foreach ($ids as $id) {
    $asset = ClientAsset::find($id);
    $asset->update([
        'runs_siem_agent' => $request['runs_siem_agent'] ?? $asset->runs_siem_agent,
        'runs_mdr_agent'  => $request['runs_mdr_agent']  ?? $asset->runs_mdr_agent,
        'runs_edr_agent'  => $request['runs_edr_agent']  ?? $asset->runs_edr_agent,
    ]);
}
return "Successfully updated agent coverage for asset IDs: ...";
```

### Models Accessed
| Model | Operation |
|---|---|
| `ClientAsset` | `find($id)` then `update()` for each ID |

---

## Tool 6: `UpdateAnalystAllocationTool`

**File:** `app/Ai/Tools/UpdateAnalystAllocationTool.php`

### Purpose
Updates the allocation percentage and/or staff count for a SOC analyst role on a specific MSSP cost record. Directly affects staffing cost in `MsspCostingEngine`.

### Description (sent to LLM)
> "Updates the allocation percentage and staff count for a SOC analyst role in an MSSP costing record."

### Schema
```php
[
    'allocation_id'           => $schema->integer()->description('ClientScenarioAnalystAllocation ID')->required(),
    'allocation_percentage'   => $schema->number()->description('New allocation %'),
    'staff_count'             => $schema->integer()->description('New staff count'),
    'custom_monthly_salary'   => $schema->number()->description('Override salary'),
]
```

### Handle
```php
$allocation->update([
    'allocation_percentage' => $request['allocation_percentage'] ?? $allocation->allocation_percentage,
    'staff_count'           => $request['staff_count'] ?? $allocation->staff_count,
    'custom_monthly_salary' => $request['custom_monthly_salary'] ?? null,
]);
```

### Models Accessed
| Model | Operation |
|---|---|
| `ClientScenarioAnalystAllocation` | `find($id)` then `update()` |

---

## Tool 7: `CreateClientTool`

**File:** `app/Ai/Tools/CreateClientTool.php`

### Purpose
Creates a new client in the database and pre-populates their complete asset inventory with device counts and agent coverage flags for all active asset types.

### Description (sent to LLM)
> "Creates a new client in the database and pre-populates/configures their asset inventory with device counts and custom agent coverages."

### Schema
```php
[
    'name'           => $schema->string()->description('Client name')->required(),
    'description'    => $schema->string()->description('Optional description'),
    'device_counts'  => $schema->string()->description('JSON map: asset_type_id => device_count (e.g., {"1":150,"4":200})'),
    'agent_coverages' => $schema->string()->description('JSON map: asset_type_id => {runs_siem_agent, runs_mdr_agent, runs_edr_agent}'),
]
```

### Handle Logic
```
1. Validate: name required
2. Client::create(['name' => ..., 'description' => ...])
3. Parse device_counts JSON (asset_type_id => count)
4. Parse agent_coverages JSON (asset_type_id => { runs_siem, runs_mdr, runs_edr })
5. AssetType::all() → foreach:
     - count = device_counts[$type->id] ?? 0
     - coverage = agent_coverages[$type->id] ?? defaults from AssetType
     - $client->clientAssets()->create([...])
6. Return JSON summary of created client + inventory
```

### Models Written
| Model | Operation |
|---|---|
| `Client` | `create(['name', 'description'])` |
| `ClientAsset` | `create(...)` for each `AssetType` (always creates for all types) |

### Models Read
| Model | Operation |
|---|---|
| `AssetType` | `all()` — to get full list of asset types + default coverage |

---

## Tool Interaction Summary

```
User: "Create a new client called 'Airbus' with 200 Windows servers and 50 firewalls"

RgSocEngineerMain:
  1. GetSystemDetailsTool()                    ← get asset_types list
  2. (asks user for missing device counts)
  3. CreateClientTool({
       name: "Airbus",
       device_counts: '{"2": 200, "4": 50, ...}'
     })
  ← "Successfully created client 'Airbus' with ID 12 and pre-populated inventory: {...}"

User: "Enable SIEM coverage for Airbus's Windows servers"

RgSocEngineerMain:
  1. GetClientInventoryTool({ client_id: 12 })  ← find asset_id for Windows servers
  2. ModifyClientAssetAgentsTool({
       asset_ids: "45",
       runs_siem_agent: true
     })
  ← "Successfully updated agent coverage for asset IDs: 45"
```
