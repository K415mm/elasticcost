# Feature: Elasticsearch Sizing Dashboard

> **Module:** `SizingDashboardController` + `SizingEngine`  
> **Route Prefix:** `/clients/{client}/scenarios/{scenario}`  
> **Views:** `resources/views/` (sizing)

---

## 1. Purpose

The Sizing Dashboard is the core technical module of ElasticCost. It takes a client's asset inventory (device counts + EPS benchmarks) and a scenario template (tier retention days, workload profile, replicas), then:

1. Calculates daily log volume (raw → indexed → ingested)
2. Computes storage per tier (Hot/Warm/Cold/Frozen)
3. Recommends a node topology with RAM/disk specifications
4. Calculates Elasticsearch licensing cost (ERU model)
5. Presents an AI-powered topology audit via `SizingRegulator`

---

## 2. Routes

| Method | URI | Controller Method | Named Route |
|---|---|---|---|
| GET | `/clients/{client}/scenarios/{scenario}` | `show()` | `sizing.show` |
| GET | `.../export/excel` | `exportExcel()` | `sizing.export.excel` |
| GET | `.../export/markdown` | `exportMarkdown()` | `sizing.export.markdown` |
| GET | `.../export/word` | `exportWord()` | `sizing.export.word` |
| POST | `.../sizing/analyze-ai` | `analyzeSizingAi()` | `sizing.analyze-ai` |

---

## 3. Controller: `SizingDashboardController`

**File:** `app/Http/Controllers/SizingDashboardController.php` (~52 KB)

### Methods

#### `show(Client $client, Scenario $scenario)`
- Calls `SizingEngine::calculate()` to compute all sizing data
- Loads `GlobalSetting` values for display
- Returns Blade view with full sizing data array

#### `exportExcel(Client $client, Scenario $scenario)`
- Uses `PhpSpreadsheet` to build a multi-sheet Excel report
- Sheets: Sizing Summary, Node Topology, Asset Breakdown, License Costs
- Streams download via `response()->streamDownload()`

#### `exportMarkdown(Client $client, Scenario $scenario)`
- Generates Markdown text with tables for all sizing sections
- Returns plain-text download

#### `exportWord(Client $client, Scenario $scenario)`
- Uses `PhpWord` to produce a formatted Word report
- Includes styled tables, headings, and Elastic logo

#### `analyzeSizingAi(Request $request, Client $client, Scenario $scenario)`
- Builds a JSON payload of the current sizing topology
- Calls `SizingRegulator::prompt($json)` (structured output)
- Returns JSON with: `verdict`, `health_score`, `ratio_audit`, `ha_check`, `recommendations`, `full_critique`
- Result stored/displayed in the UI as a collapsible AI report panel

---

## 4. Service: `SizingEngine`

**File:** `app/Services/SizingEngine.php` (534 lines)

### `calculate(Client $client, Scenario $scenario): array`

**Step 1: Global Settings**
```php
$eruCost        = GlobalSetting::getValue('eru_cost_usd', 14000);
$ramPerEru      = GlobalSetting::getValue('ram_per_eru_gb', 64);
$expansionFactor = GlobalSetting::getValue('index_expansion_factor', 1.25);
```

**Step 2: Asset Loop (EPS + Daily GB per asset)**
```
foreach clientAssets:
  EPS = resolved from scenario workload_profile:
    min → asset.custom_min_eps ?? assetType.min_eps_default
    avg → asset.custom_avg_eps ?? assetType.avg_eps_default
    max → depends on calibration_mode:
            eps_per_device     → custom_max_eps ?? max_eps_default
            monthly_gb_per_device → monthlyGb/30 (back-calculate EPS)
            monthly_gb_total      → monthlyGb/30 (back-calculate EPS)
  dailyRawGb = deviceCount × EPS × 86400 × eventSizeBytes / 10^9
  dailyIndexedGb = dailyRawGb × expansionFactor (1.25)
  dailyIngestedGb = dailyIndexedGb × (1 + hotReplicas)
```

**Step 3: Tier Storage**
```
hotStorage    = dailyIngestedGb × scenario.hot_days
warmStorage   = dailyIngestedGb × scenario.warm_days
coldStorage   = dailyIngestedGb × scenario.cold_days
frozenStorage = dailyIngestedGb × scenario.frozen_days
```

**Step 4: Node Recommendations**
Calls `recommendNodes()` — 3 profiles:

| Profile | Master | Hot | Warm | Cold | Frozen | Kibana | Logstash | ML |
|---|---|---|---|---|---|---|---|---|
| `min` | 1 tiebreaker | 2 combined | — | 2 HA (if cold_days>0) | — | 1-2 | — | 1 (if tiered) |
| `avg` | 3 dedicated | 2 dedicated | 2 (if warm_days>0) | 1 (if cold_days>0) | 1 (if frozen_days>0) | 1 | 1 | — |
| `max` | 3 dedicated | 2 dedicated | 2-3 (if warm_days>0) | 1 | 1 | 1 | 2 | — |

RAM per node = `getNearestVmProfile(hotDisk / ratioForTier, maxRamCap)`

RAM profiles: `[1, 2, 4, 8, 16, 32, 64, 96, 128, 192, 256, 384, 512]` GB

Storage disk sizes rounded via `roundToNeatDiskSize()`:
- ≤20 GB → 20 GB
- ≤50 GB → 50 GB
- ≤100 GB → 100 GB
- ≤1000 GB → round up to nearest 100 GB
- >1000 GB → round up to nearest 500 GB

**Step 5: ERU Licensing**
```
totalClusterRAM = sum(node.count × node.ram_gb)
requiredERUs    = ceil(totalClusterRAM / ramPerEru)
annualCostUSD   = requiredERUs × eruCost
```

---

## 5. Key Models Used

| Model | Role |
|---|---|
| `Client` | The client being sized |
| `ClientAsset` | Device counts per asset type |
| `AssetType` | EPS benchmarks, event size, calibration mode |
| `Scenario` | Tier days, workload profile, replica count |
| `GlobalSetting` | ERU cost, expansion factor, RAM per ERU |

---

## 6. AI Integration: SizingRegulator

**Trigger:** POST `/sizing/analyze-ai`

**Input JSON payload:**
```json
{
  "client": { "name": "..." },
  "scenario": { "name": "...", "workload_profile": "avg", ... },
  "nodes": [
    { "name": "hot-node-01", "role": "...", "ram_gb": 32, "storage_gb": 1000, ... }
  ],
  "totals": { "daily_raw_gb": 15.2, "hot_storage_gb": 456, ... }
}
```

**Structured Output:**
```json
{
  "verdict": "Adequate|Under-provisioned|Imbalanced",
  "health_score": 8,
  "ratio_audit": [...],
  "ha_check": { "master_eligible_count": 3, "quorum_met": true, "remarks": "..." },
  "recommendations": ["...", "..."],
  "full_critique": "# Markdown critique..."
}
```

---

## 7. RAM-to-Disk Ratios Reference

| Tier | Optimal Range | Storage Type |
|---|---|---|
| Hot | 1:16 – 1:30 | Local NVMe SSD |
| Warm | 1:48 – 1:80 | SATA SSD / HDD |
| Cold | 1:100 – 1:160 | SATA SSD (Snapshot Cache) |
| Frozen | 1:160 – 1:1000 | Object Storage / SATA |

JVM Heap = exactly 50% of node RAM, max 30-32 GB
