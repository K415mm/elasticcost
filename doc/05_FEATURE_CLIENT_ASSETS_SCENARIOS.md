# Feature: Client & Asset Inventory Management

> **Module:** `ClientController` + `ClientAssetController` + `AssetTypeController`  
> **Routes:** `/clients`, `/settings/asset-types`

---

## 1. Purpose

Client management is the entry point for all sizing and costing work. Each client has an **inventory** of asset types (servers, firewalls, Active Directory, etc.) with device counts and agent coverage flags. Asset types define the EPS benchmarks used in sizing calculations.

---

## 2. Routes

### Client Routes
| Method | URI | Action | Named Route |
|---|---|---|---|
| GET | `/clients` | `ClientController@index` | `clients.index` |
| POST | `/clients` | `ClientController@store` | `clients.store` |
| GET | `/clients/{client}` | `ClientController@show` | `clients.show` |
| DELETE | `/clients/{client}` | `ClientController@destroy` | `clients.destroy` |

### Client Asset Routes
| Method | URI | Action | Named Route |
|---|---|---|---|
| POST | `/clients/{client}/assets` | `ClientAssetController@store` | `client-assets.store` |
| PUT | `/clients/{client}/assets-bulk` | `ClientAssetController@updateBulk` | `client-assets.update-bulk` |
| PUT | `/clients/{client}/assets/{asset}` | `ClientAssetController@update` | `client-assets.update` |
| DELETE | `/clients/{client}/assets/{asset}` | `ClientAssetController@destroy` | `client-assets.destroy` |

### Asset Type (Benchmark) Routes
| Method | URI | Action | Named Route |
|---|---|---|---|
| GET | `/settings/asset-types` | `AssetTypeController@index` | `asset-types.index` |
| POST | `/settings/asset-types` | `AssetTypeController@store` | `asset-types.store` |
| PUT | `/settings/asset-types/{type}` | `AssetTypeController@update` | `asset-types.update` |

---

## 3. Models

### `Client`
**File:** `app/Models/Client.php`

```php
fillable: ['name', 'description']

Relationships:
  clientAssets() → HasMany(ClientAsset)
```

### `ClientAsset`
**File:** `app/Models/ClientAsset.php`

```php
fillable: [
    'client_id', 'asset_type_id', 'device_count',
    'custom_min_eps', 'custom_avg_eps', 'custom_max_eps',
    'custom_avg_event_size_bytes', 'custom_max_monthly_gb',
    'runs_siem_agent', 'runs_mdr_agent', 'runs_edr_agent'
]

casts: [
    'runs_siem_agent' => 'boolean',
    'runs_mdr_agent'  => 'boolean',
    'runs_edr_agent'  => 'boolean',
]

Relationships:
  assetType()  → BelongsTo(AssetType)
  client()     → BelongsTo(Client)
```

Key behavior:
- Each client automatically gets one `ClientAsset` record per `AssetType` upon creation (via `CreateClientTool` or controller)
- Custom EPS/event size fields allow overriding the `AssetType` defaults per client

### `AssetType`
**File:** `app/Models/AssetType.php`

```php
fillable: [
    'name', 'description',
    'calibration_mode',         // 'eps_per_device' | 'monthly_gb_per_device' | 'monthly_gb_total'
    'avg_event_size_bytes',
    'min_eps_default', 'avg_eps_default', 'max_eps_default',
    'max_monthly_gb_default',
    'runs_siem_agent', 'runs_mdr_agent', 'runs_edr_agent'
]
```

**Calibration Modes:**
| Mode | Description |
|---|---|
| `eps_per_device` | Log volume = devices × EPS × seconds × event size |
| `monthly_gb_per_device` | Log volume = devices × monthly_gb / 30 days |
| `monthly_gb_total` | Log volume = total monthly_gb / 30 days (independent of device count) |

**Default Asset Types (seeded):**

| Asset | Avg Event Size | Default Calibration |
|---|---|---|
| Windows Workstations | ~500 bytes | eps_per_device |
| Windows Servers | ~600 bytes | eps_per_device |
| Linux Servers | ~400 bytes | eps_per_device |
| Network Firewalls | ~800 bytes | eps_per_device |
| Active Directory | ~700 bytes | eps_per_device |
| SIEM (Elastic) | n/a | monthly_gb_total |
| EDR Agents | ~300 bytes | eps_per_device |
| Network Switches | ~400 bytes | eps_per_device |

---

## 4. Bulk Asset Update Logic

The `updateBulk` endpoint accepts an array of assets for a client:
```json
{
  "assets": [
    { "id": 5, "device_count": 100, "runs_siem_agent": true },
    { "id": 6, "device_count": 50, "runs_mdr_agent": false }
  ]
}
```

This enables the inventory panel to submit all device counts at once (single form submit for entire client inventory).

---

## 5. Agent Coverage Flags

Each `ClientAsset` has 3 boolean flags that drive cost calculation in `MsspCostingEngine`:

| Flag | MSSP Cost Impact |
|---|---|
| `runs_siem_agent` | `device_count × siem_agent_monthly_cost_per_device` |
| `runs_mdr_agent` | `device_count × mdr_agent_monthly_cost_per_device` |
| `runs_edr_agent` | `device_count × edr_agent_monthly_cost_per_device` |

Default values inherited from `AssetType.runs_*_agent`, but overridable per `ClientAsset`.

---

## 6. AI-Driven Client Creation

The `RgSocEngineerMain` agent (via `CreateClientTool`) can create clients conversationally:

**Flow:**
1. User asks: "Create a new client called Acme"
2. Agent calls `GetSystemDetailsTool` → gets list of active asset types
3. Agent asks user for device counts for each type
4. Agent calls `CreateClientTool` with `name`, `description`, `device_counts` JSON map
5. Tool creates `Client` + one `ClientAsset` per `AssetType` with provided counts

---

# Feature: Scenario Template Management

> **Module:** `ScenarioController`  
> **Routes:** `/settings/scenarios`

---

## 1. Purpose

Scenarios define the data retention and workload parameters used in sizing calculations. They are reusable templates applied to any client.

---

## 2. Routes

| Method | URI | Action |
|---|---|---|
| GET | `/settings/scenarios` | `index` |
| GET | `/settings/scenarios/create` | `create` |
| POST | `/settings/scenarios` | `store` |
| GET | `/settings/scenarios/{scenario}/edit` | `edit` |
| PUT | `/settings/scenarios/{scenario}` | `update` |
| DELETE | `/settings/scenarios/{scenario}` | `destroy` |

---

## 3. Model: `Scenario`

**File:** `app/Models/Scenario.php`

```php
fillable: [
    'name', 'description', 'workload_profile',
    'retention_days',
    'hot_days', 'warm_days', 'cold_days', 'frozen_days',
    'hot_replicas'
]
```

| Field | Values | Description |
|---|---|---|
| `workload_profile` | `min`, `avg`, `max` | EPS selection strategy |
| `retention_days` | integer | Total retention (sum of all tiers) |
| `hot_days` | integer | Days in Hot tier (NVMe SSD, high EPS) |
| `warm_days` | integer | Days in Warm tier (SATA) |
| `cold_days` | integer | Days in Cold/snapshot tier |
| `frozen_days` | integer | Days in Frozen/object tier |
| `hot_replicas` | integer | Number of replicas on Hot tier (typically 1) |

---

## 4. Common Scenario Examples

| Scenario Name | Profile | Hot | Warm | Cold | Frozen | Total |
|---|---|---|---|---|---|---|
| SME Basic | min | 7 | 0 | 23 | 0 | 30 days |
| Enterprise Standard | avg | 14 | 30 | 46 | 0 | 90 days |
| Enterprise Compliance | max | 30 | 60 | 90 | 365 | 545 days |
| POC / Demo | min | 3 | 0 | 0 | 0 | 3 days |
