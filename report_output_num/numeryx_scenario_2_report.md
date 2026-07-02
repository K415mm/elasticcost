# Elasticsearch Sizing & Cost Report: Scenario 2 - Minimum Ingest, Multi-Tier (Long Retention)

This report details the architectural footprint and licensing cost for **Client "Numeryx"** under **Scenario 2**.

---

## 1. Workload & Ingest Parameters

*   **Ingestion Profile**: **Min Workload**
*   **Daily Raw Log Volume**: **`49.14 GB/day`**
*   **Daily Indexed Volume (+25% Expansion)**: **`61.43 GB/day`**
*   **Retention Period**: **`365 Days`** (1 Year)
*   **ILM Data Lifecycle Tiers**:
    *   **Hot Tier**: **30 Days** (Primary + 1 Replica). Daily: 122.86 GB/day.
    *   **Frozen Tier**: **335 Days** (Searchable Snapshots, 0% Replica overhead).

---

## 2. Storage Sizing Calculations

*   **Total Raw Data Stored**: 49.14 GB/day * 365 days = **17937.36 GB**
*   **Total Indexed Data (Active)**: 61.43 GB/day * 365 days = **22421.7 GB**
*   **Tier Storage Breakdown (Cluster Physical Footprint)**:
    *   **Hot Tier (NVMe SSD)**: **3685.76 GB**
    *   **Frozen Tier (Object Store Cache)**: **41157.64 GB**
*   **Total Cluster Storage Required**: **44843.4 GB**

---

## 3. Recommended Cluster Architecture (On-Premises VMs)

| Node Name | Node Role | Count | RAM / Node | JVM Heap | Storage / Node | Storage Type |
| :--- | :--- | :---: | :---: | :---: | :---: | :--- |
| **hot-node-01** | Master / Data (Hot) | 1 | 32 GB | 16 GB | 1.5 TB | Local NVMe SSD |
| **hot-node-02** | Master / Data (Hot) | 1 | 32 GB | 16 GB | 1.5 TB | Local NVMe SSD |
| **hot-node-03** | Master / Data (Hot) | 1 | 32 GB | 16 GB | 1.5 TB | Local NVMe SSD |
| **master-tiebreaker** | Dedicated Master (Quorum) | 1 | 4 GB | 2 GB | 20 GB | Local SSD |
| **frozen-node-01** | Data (Frozen) | 1 | 16 GB | 8 GB | 17 TB | SATA SSD (Snapshot Cache) |
| **frozen-node-02** | Data (Frozen) | 1 | 16 GB | 8 GB | 17 TB | SATA SSD (Snapshot Cache) |
| **kibana-fleet-01** | Kibana / Fleet Server | 1 | 16 GB | 8 GB | 50 GB | Local SSD |
| **kibana-fleet-02** | Kibana / Fleet Server (HA) | 1 | 16 GB | 8 GB | 50 GB | Local SSD |
| **ml-node** | Dedicated Machine Learning | 1 | 16 GB | 8 GB | 50 GB | Local SSD |

### System Capacities:
*   **Total Cluster Memory Footprint**: **`180 GB RAM`**

---

## 4. Elastic Resource Unit (ERU) Licensing Cost

$$\text{Required ERUs} = \left\lceil \frac{180\text{ GB (Total RAM)}}{64\text{ GB (1 ERU)}} \right\rceil = \mathbf{3\text{ ERUs}}$$

> [!NOTE]
> **Licensing Verdict**: This configuration requires **`3 ERU`** subscription licenses. Annual projected license cost is **`€38,640.00`** based on €12,880.00/ERU assumptions.

### Cluster Topology Diagram

```mermaid
graph TD
    subgraph Cluster [Elasticsearch Cluster]
        N0["hot-node-01 (Master / Data (Hot))<br>32GB RAM | 1500GB"]
        N1["hot-node-02 (Master / Data (Hot))<br>32GB RAM | 1500GB"]
        N2["hot-node-03 (Master / Data (Hot))<br>32GB RAM | 1500GB"]
        N3["master-tiebreaker (Dedicated Master (Quorum))<br>4GB RAM | 20GB"]
        N4["frozen-node-01 (Data (Frozen))<br>16GB RAM | 17000GB"]
        N5["frozen-node-02 (Data (Frozen))<br>16GB RAM | 17000GB"]
        N6["kibana-fleet-01 (Kibana / Fleet Server)<br>16GB RAM | 50GB"]
        N7["kibana-fleet-02 (Kibana / Fleet Server (HA))<br>16GB RAM | 50GB"]
        N8["ml-node (Dedicated Machine Learning)<br>16GB RAM | 50GB"]
    end
```
