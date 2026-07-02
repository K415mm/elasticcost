# Technical Sizing Report: Client "Numeryx" (Scenario 2)

This document provides the formal technical sizing specifications and data lifecycle modeling for the onboarding of **Numeryx** under **Scenario 2** (Minimum Ingest with Long-Term Retention). This sizing model is designed to support security operations ingestion and compliance storage requirements.

---

## 1. Data Ingestion & Growth Metrics

The cluster sizing is based on baseline ingestion volumes with an expected indexing metadata expansion factor.

| Parameter | Metric | Details |
| :--- | :--- | :--- |
| **Ingestion Workload Profile** | Minimum (Min) | Baseline security operations profile |
| **Daily Raw Log Volume** | **49.14 GB/day** | Ingested volume before indexing |
| **Indexing Expansion Factor** | **1.25x (25% expansion)** | Overhead for indexing, mapping, and metadata |
| **Daily Indexed Log Volume** | **61.43 GB/day** | Final storage written to disk daily |
| **Retention Period** | **365 Days (1 Year)** | Regulatory/operational retention requirement |

---

## 2. Data Lifecycle & Index Lifecycle Management (ILM)

To balance query performance and hosting costs, a multi-tier storage strategy is deployed using Elasticsearch Index Lifecycle Management (ILM).

```
[ Ingestion ] ──> [ Hot Tier ] (30 Days, 1 Replica) ──> [ Frozen Tier ] (335 Days, Searchable Snapshots)
```

### 2.1 Hot Tier (30 Days)
* **Purpose**: High-speed indexing, ingestion, and active querying of recent security events.
* **Duration**: Days 1 to 30.
* **Replication**: 1 Primary + 1 Replica (100% redundancy) to ensure high availability.
* **Daily Ingest Footprint (Raw)**: 49.14 GB/day × 30 days = **1,474.20 GB**
* **Daily Ingest Footprint (Indexed)**: 61.43 GB/day × 30 days = **1,842.88 GB**
* **Total Physical Hot Storage (with Replica)**: 1,842.88 GB × 2 = **3,685.76 GB (3.69 TB)**

### 2.2 Frozen Tier (335 Days)
* **Purpose**: Long-term archive and compliance storage supporting search-on-demand via Searchable Snapshots.
* **Duration**: Days 31 to 365 (335 days total).
* **Replication**: 0% Replica overhead. High availability is guaranteed by the underlying Object Storage durability.
* **Total Raw Storage**: 49.14 GB/day × 335 days = **16,461.90 GB**
* **Total Indexed Storage**: 61.43 GB/day × 335 days = **20,578.83 GB**
* **Total Physical Storage (Object Store Cache)**: **41,157.64 GB (41.16 TB)**

---

## 3. Storage Footprint Summary

| Storage Tier | Active Retention | Replicas | Storage Type | Physical Storage Footprint |
| :--- | :---: | :---: | :--- | :---: |
| **Hot Tier** | 30 Days | 1 | High-Performance NVMe SSD | **3,685.76 GB** |
| **Frozen Tier** | 335 Days | 0 | S3 Object Store / Local Cache | **41,157.64 GB** |
| **Total Footprint** | **365 Days** | — | **Hybrid Storage** | **44,843.40 GB (44.84 TB)** |

> [!NOTE]
> **Total Cumulative Raw Data**: 17,937.36 GB (17.94 TB)  
> **Total Cumulative Indexed Data**: 22,421.70 GB (22.42 TB)

---

## 4. Elastic Resource Unit (ERU) Licensing Model

Licensing for the Elasticsearch cluster is calculated using the Elastic Resource Unit (ERU) subscription model, which is based on the total RAM capacity across data nodes and operational nodes in the cluster.

### 4.1 Memory Allocation Inventory

* **3 × Hot Data/Master Nodes**: 32 GB RAM each = **96 GB RAM** (operational)
* **1 × Master Tiebreaker Node**: **4 GB RAM** (quorum)
* **2 × Frozen Compute Nodes**: 16 GB RAM each = **32 GB RAM**
* **2 × Kibana/Fleet Servers**: 16 GB RAM each = **32 GB RAM**
* **1 × Machine Learning Node**: **16 GB RAM** (anomaly detection)
* **Total Cluster Memory**: **180 GB RAM**

### 4.2 ERU Calculation

An Elastic Resource Unit represents 64 GB of RAM capacity:

$$\text{Required ERUs} = \left\lceil \frac{180 \text{ GB}}{64 \text{ GB}} \right\rceil = \mathbf{3 \text{ ERUs}}$$

### 4.3 Annual Subscription Pricing

* **Estimated Subscription Tier**: Platinum
* **Unit Cost per ERU**: **€12,880.00 / year**
* **Total Projected Licensing Cost**: 3 ERUs × €12,880.00 = **€38,640.00 / year** (equivalent to **€3,220.00 / month**)
