# Feature: MSSP SOC Costing Dashboard

> **Module:** `MsspCostingController` + `MsspCostingEngine`  
> **Route Prefix:** `/clients/{client}/scenarios/{scenario}/mssp-cost`

---

## 1. Purpose

The MSSP Costing Dashboard is the commercial module of ElasticCost. It calculates the full monthly recurring cost (MRC) for delivering an Elasticsearch-based MSSP/SOC service to a client, covering:

- **Infrastructure hosting** (VM + storage costs, matched to Azure or unit pricing)
- **Elasticsearch licensing** (ERU-based annual license prorated monthly)
- **SOC staffing** (analyst salaries × allocation % × headcount)
- **Profit margin layers** (5 configurable margin factors)
- **Client MRC proposal** (base cost + all margins)
- **Cloud alternative** (Elastic Cloud managed + agent cost model)
- **AI offer analysis** (`OfferAnalyst` structured critique)

---

## 2. Routes

| Method | URI | Controller Method | Named Route |
|---|---|---|---|
| GET | `/clients/{client}/scenarios/{scenario}/mssp-cost` | `show()` | `mssp.show` |
| POST | same URI | `update()` | `mssp.update` |
| GET | `.../export/excel` | `exportExcel()` | `mssp.export.excel` |
| GET | `.../export/word` | `exportWord()` | `mssp.export.word` |
| GET | `.../export/markdown` | `exportMarkdown()` | `mssp.export.markdown` |
| POST | `.../ask-ai` | `askAi()` | `mssp.ask-ai` |
| GET | `/api/ollama-ping` | `ollamaPing()` | `ollama.ping` |

---

## 3. Controller: `MsspCostingController`

**File:** `app/Http/Controllers/MsspCostingController.php` (~72 KB — largest in project)

### Key Methods

#### `show(Client $client, Scenario $scenario)`
- Calls `MsspCostingEngine::calculate()` for full cost model
- Loads `CurrencyHelper::rates()` for display in USD/EUR/TND
- Returns Blade view with costing data, infrastructure cards, analyst panel

#### `update(Request $request, Client $client, Scenario $scenario)`
- Accepts form POST for:
  - Unit prices (RAM, NVMe, SATA, Local SSD per GB)
  - Profit margin percentages (5 factors)
  - One-time setup cost, monthly maintenance
  - License sharing flag + percentage
  - Cloud datacenter selection
  - Agent costs per device (SIEM/MDR/EDR)
  - Elastic Cloud subscription tier
- Updates `ClientScenarioMsspDetail`
- Recalculates and redirects

#### `askAi(Request $request, Client $client, Scenario $scenario)`
- Builds JSON payload of the full costing snapshot
- Calls `OfferAnalyst::prompt($json)` — structured output
- Returns JSON: `{ health_score, margin_status, sanity_checks, staffing_status, infrastructure_status, recommendations, full_critique }`

#### `exportExcel()` / `exportWord()` / `exportMarkdown()`
- Multi-tab Excel proposal (Infra nodes, Analysts, Cloud Option, Margins)
- Word formatted proposal document
- Markdown executive summary

#### `ollamaPing()`
- Tests connectivity to the configured Ollama URL
- Returns JSON `{ reachable: true/false, url: '...', model: '...' }`

---

## 4. Service: `MsspCostingEngine`

**File:** `app/Services/MsspCostingEngine.php` (374 lines)

**Constructor dependencies:**
```php
public function __construct(
    protected SizingEngine $sizingEngine,
    protected CloudProviderPricingService $pricingService
) {}
```

### `calculate(Client $client, Scenario $scenario): array`

**Step 1: Sizing data**
```php
$sizingData = $this->sizingEngine->calculate($client, $scenario);
```

**Step 2: MSSP detail record (firstOrCreate)**
Creates `ClientScenarioMsspDetail` with default unit prices if first access.

**Step 3: Default analyst allocations**
Auto-creates `ClientScenarioAnalystAllocation` for each `SocRole` with default %:

| SOC Role | Default % | Default Action |
|---|---|---|
| L1 Analyst (id=1) | 10% | Auto-created |
| L2 Analyst (id=2) | 5% | Auto-created |
| L3 Analyst (id=3) | 2% | Auto-created |
| SOC Engineer (id=4) | 5% | Auto-created |
| SOC Manager (id=5) | 2% | Auto-created |

**Step 4: Analyst Cost**
```
cost_per_role = salary × (allocation_percentage / 100) × staff_count
totalAnalystCost = sum of all roles
```

**Step 5: Infrastructure Hosting Cost**
For each node from `SizingEngine`:
- If `cloud_datacenter` is set → `CloudProviderPricingService::matchVm(datacenter, ram)` + `matchDisk(datacenter, storage)`
  - Uses Azure pricing (TND prices → converted to USD via `CurrencyHelper`)
- Otherwise → unit prices × RAM GB × count, unit prices × storage GB × count (NVMe/SATA/Local SSD)

**Step 6: License (monthly)**
```
monthlyLicense = annualLicense / 12
IF is_license_shared: monthlyLicense × (share_percentage / 100)
```

**Step 7: Base MRC**
```
totalMonthlyCost = analystCost + infraCost + monthlyLicense + monthlyMaintenance
```

**Step 8: Profit Margins**
```
assurance_amount   = totalMonthlyCost × (assurance_pct / 100)
marketing_amount   = totalMonthlyCost × (marketing_pct / 100)
soc_manager_amount = totalMonthlyCost × (soc_manager_pct / 100)
ceo_amount         = totalMonthlyCost × (ceo_pct / 100)
fixed_amount       = totalMonthlyCost × (fixed_pct / 100)
clientOfferedMRC   = totalMonthlyCost + sum_of_all_margins
```

**Step 9: Cloud Option (Elastic Cloud)**
```
For each node: match Elastic Cloud SKU → hourly_rate
  monthly_node_cost = count × ram_gb × hourly_rate × 730 hours
elasticCloudSubscriptionCost = sum of all nodes

SIEM/MDR/EDR agent counts from clientAssets.runs_*_agent flags
siem_cost = total_siem_count × siem_unit_cost
mdr_cost  = total_mdr_count  × mdr_unit_cost
edr_cost  = total_edr_count  × edr_unit_cost
cloudOfferedMRC = agentsCost (simplified)
```

---

## 5. Key Models Used

| Model | Role |
|---|---|
| `Client` | Client being costed |
| `ClientAsset` | Device counts + SIEM/MDR/EDR agent flags |
| `AssetType` | Default agent coverage flags |
| `ClientScenarioMsspDetail` | Per-client/scenario costing config & prices |
| `ClientScenarioAnalystAllocation` | Per-SOC-role allocation % and headcount |
| `SocRole` | Role definitions with default salaries |
| `GlobalSetting` | Agent prices, Elastic Cloud cost per GB RAM |

---

## 6. `ClientScenarioMsspDetail` Key Fields

| Column | Type | Purpose |
|---|---|---|
| `ram_monthly_cost_per_gb` | decimal | On-premise RAM cost |
| `nvme_ssd_monthly_cost_per_gb` | decimal | NVMe SSD cost |
| `sata_ssd_monthly_cost_per_gb` | decimal | SATA/HDD cost |
| `local_ssd_monthly_cost_per_gb` | decimal | Local SSD cost |
| `cloud_datacenter` | string | e.g. "DC_Xpress_Tunis_1" |
| `is_license_shared` | bool | Shared license flag |
| `license_share_percentage` | decimal | % of license allocated |
| `elastic_cloud_subscription_tier` | string | standard/gold/platinum |
| `elastic_cloud_monthly_cost_per_gb_ram` | decimal | Cloud RAM pricing |
| `siem_agent_monthly_cost_per_device` | decimal | SIEM agent fee |
| `mdr_agent_monthly_cost_per_device` | decimal | MDR agent fee |
| `edr_agent_monthly_cost_per_device` | decimal | EDR agent fee |
| `assurance_benefit_percentage` | decimal | Margin factor 1 |
| `marketing_benefit_percentage` | decimal | Margin factor 2 |
| `soc_manager_benefit_percentage` | decimal | Margin factor 3 |
| `ceo_benefit_percentage` | decimal | Margin factor 4 |
| `fixed_profit_percentage` | decimal | Margin factor 5 |
| `one_time_setup_cost` | decimal | One-time fee |
| `monthly_maintenance_cost` | decimal | Monthly maintenance |
| `ai_analysis` | json | Stored OfferAnalyst result |
| `ai_sizing_analysis` | json | Stored SizingRegulator result |

---

## 7. CloudProviderPricingService

**File:** `app/Services/CloudProviderPricingService.php`

Reads two static JSON files from `public/assets/json/`:
- `xpress_azure_prices.json` — Azure datacenter VM and disk pricing (TND)
- `elastic_cloud_prices.json` — Elastic Cloud SKU hourly rates (USD)

### `matchVm(datacenter, requiredRam)` → best-fit VM (cheapest with RAM ≥ required)
### `matchDisk(datacenter, requiredStorage)` → combination of disk profiles that satisfy storage
### `matchElasticCloudNode(nodeName, nodeRole, tier)` → Elastic Cloud SKU + hourly rate

Elastic Cloud SKU mapping:
| Node Role | SKU |
|---|---|
| `master` | `azure.es.master.fsv2` |
| `hot` | `azure.es.datahot.edsv4` |
| `warm` | `azure.es.datawarm.edsv4` |
| `cold` | `azure.es.datacold.edsv4` |
| `frozen` | `azure.es.datafrozen.edsv4` |
| `ml` | `azure.es.ml.fsv2` |
| `kibana/fleet` | `azure.apm.e32sv3` |

---

## 8. AI Integration: OfferAnalyst

**Trigger:** POST `/mssp-cost/ask-ai`

**Input:** JSON snapshot of full costing model (client, scenario, infrastructure, analysts, margins, totals)

**Key sanity checks performed:**
1. Zero ingestion with active infrastructure → CRITICAL FLAG
2. Staffing costs without log data → disconnect flag
3. Ghost license cost (non-zero license, zero ingestion)
4. Shared license % validation against workload

**Structured output schema:**
```json
{
  "health_score": 7,
  "margin_status": "Optimal",
  "sanity_checks": ["✅ Ingestion check: OK", "..."],
  "staffing_status": "Balanced",
  "infrastructure_status": "Optimal",
  "recommendations": ["1. ...", "2. ..."],
  "full_critique": "# Markdown full analysis..."
}
```
