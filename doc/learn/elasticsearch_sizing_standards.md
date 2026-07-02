# Elasticsearch Benchmarking & Sizing Standards

This guide outlines official Elasticsearch sizing principles, memory-to-disk ratios, overhead calculations, and architectural configurations for log and metrics observability workloads based on standard Elastic sizing benchmarks.

---

## 1. Storage Tiers & RAM-to-Disk Ratios

For optimal performance, cost-efficiency, and system reliability, Elasticsearch deployments utilize specific **RAM-to-Disk Storage Ratios** per data tier. These ratios represent the amount of raw or indexed data supported by a given volume of memory.

| Data Tier | Purpose | Recommended RAM-to-Disk Ratio | RAM Calculation Formula | Standard Sizing Guideline |
| :--- | :--- | :--- | :--- | :--- |
| **Hot Tier** | Active ingestion & fast, low-latency search | **`1:16` to `1:30`** | $\text{RAM} = \frac{\text{Disk}}{30}$ | 32 GB RAM supports up to ~960 GB of hot storage |
| **Warm Tier** | Occasional queries & read-only indexing | **`1:48` to `1:80`** | $\text{RAM} = \frac{\text{Disk}}{80}$ | 32 GB RAM supports up to ~2.56 TB of warm storage |
| **Cold Tier** | Rarely accessed historical data & compliance | **`1:100` to `1:160`** | $\text{RAM} = \frac{\text{Disk}}{100}$ | 16 GB RAM supports up to ~1.6 TB of cold storage |
| **Frozen Tier**| Long-term archive via searchable snapshots | **`1:1000` or higher** | $\text{RAM} = \frac{\text{Disk}}{160}$ | 16 GB RAM supports up to ~2.56 TB of frozen storage |

---

## 2. Cluster Storage Calculations

To size an Elasticsearch cluster's disk capacity, we must account for raw ingestion logs, retention windows, index metadata expansion, and replica overhead:

$$\text{Total Storage} = (\text{Daily Ingestion} \times \text{Retention in Days}) \times \text{Index Expansion Multiplier} \times (1 + \text{Replica Count})$$

*   **Daily Ingestion**: The raw volume of logs generated per day (in GB).
*   **Retention in Days**: The number of days the logs are kept on the active tier.
*   **Index Expansion Multiplier**: Typically **`1.15` to `1.25`** (15% to 25% overhead) representing mapping fields, term vectors, index metadata, and ECS formatting.
*   **Replica Count**: The count of secondary copies for High Availability (HA). Usually **`1`** for Hot/Warm tiers to guarantee failover. For Cold and Frozen tiers using Searchable Snapshots, replica count is typically **`0`**, as failover is backed by direct snapshot restoration from external storage.

---

## 3. Resolving the Scenario 2 Cluster Configuration

In Scenario 2, the client has a daily raw log ingestion footprint that results in **9.59 GB/day** of daily ingested data (including replica overhead) on the Hot Tier:

*   **Ingestion Rate**: `9.59 GB/day` (Total Daily Ingested).
*   **Hot Tier (30 Days)**:
    *   $\text{Storage Needed} = 9.59 \text{ GB/day} \times 30 \text{ days} = 287.7 \text{ GB}$ (Total Cluster Hot Storage).
    *   Split across **2 Hot Nodes**: $\frac{287.7 \text{ GB}}{2} = 143.85 \text{ GB/node}$.
    *   With **20% disk margin** and neat sizing: `200 GB SSD disk` per node.
    *   Applying Hot Ratio ($1:30$): $\text{RAM} = \frac{200 \text{ GB}}{30} = 6.67 \text{ GB}$, which maps to standard **8 GB RAM** profile per hot node.
*   **Cold Tier (335 Days)**:
    *   $\text{Storage Needed} = 9.59 \text{ GB/day} \times 335 \text{ days} = 3,212.65 \text{ GB}$ (Total Cluster Cold Storage = `3.21 TB`).
    *   Split across **2 Cold Nodes**: $\frac{3212.65 \text{ GB}}{2} = 1,606.3 \text{ GB/node}$ (or `1.6 TB` of cache storage per node).
    *   Applying Cold Ratio ($1:100$): $\text{RAM} = \frac{1600 \text{ GB}}{100} = 16 \text{ GB}$ RAM per cold node.
    *   *Correction*: Previous app iterations sized cold nodes with a $1:400$ ratio, incorrectly recommending only **2 GB RAM** and **900 GB disk** (which only totaled 1.8 TB storage instead of the required 3.21 TB). Sizing cold nodes to `16 GB RAM` and `1600 GB disk` satisfies the required storage footprint and operational RAM limits.

---

## 4. Elastic Sizing Benchmarks & Best Practices

According to the official Elastic sizing blogs, the key phases of scaling are:

1.  **Run Benchmarks**: Always simulate production traffic locally or in staging with representative documents before doing large-scale deployments.
2.  **Maintain Shard Sizes**: Keep primary shards between **30 GB and 50 GB**. Shards larger than 50 GB slow down cluster recoveries and search speeds, while shards smaller than 10 GB introduce too much cluster state metadata overhead.
3.  **JVM Heap Limit**: Never size node memory beyond **64 GB RAM** (which allows a max JVM heap of ~32 GB to stay within compressed ordinary object pointers).
4.  **Isolate Roles**: In enterprise setups (typically >1,000 EPS or >500 GB/day), keep Master-eligible nodes, Machine Learning nodes, and Kibana separate from Data nodes to protect search and ingest latency.
