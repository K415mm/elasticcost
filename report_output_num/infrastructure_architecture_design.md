# Infrastructure & Architecture Design: OVHcloud Deployment

This document outlines the distributed cluster architecture and hosting optimization design for the **Numeryx** security operations data platform. It details the transition from an all-block storage model to a hybrid searchable-snapshot model hosted on OVHcloud Public Cloud.

---

## 1. Cluster Node Topology & Virtual Machine Mapping

The physical node layout consists of a 9-node cluster, providing dedicated roles to isolate ingestion, queries, machine learning workload, and control-plane metadata.

| Node Name | Role | Specs Target | Mapped OVHcloud Instance | CPU / RAM | Disk & Storage Attachments | Monthly Cost (Excl. VAT) |
| :--- | :--- | :--- | :--- | :---: | :--- | :---: |
| **hot-node-01** | Master / Data Hot | 32 GB RAM | **b3-32** (General Purpose) | 8 vCPUs / 32 GB | 200 GB local NVMe + 1,300 GB Block NVMe | €201.71 |
| **hot-node-02** | Master / Data Hot | 32 GB RAM | **b3-32** (General Purpose) | 8 vCPUs / 32 GB | 200 GB local NVMe + 1,300 GB Block NVMe | €201.71 |
| **hot-node-03** | Master / Data Hot | 32 GB RAM | **b3-32** (General Purpose) | 8 vCPUs / 32 GB | 200 GB local NVMe + 1,300 GB Block NVMe | €201.71 |
| **master-tiebreaker** | Quorum Leader | 4 GB RAM | **b2-7** (General Purpose) | 2 vCPUs / 7 GB | 50 GB local SSD | €25.17 |
| **frozen-node-01** | Data (Frozen) | 16 GB RAM | **b3-16** (General Purpose) | 4 vCPUs / 16 GB | 100 GB local NVMe + 500 GB Block NVMe (Cache) | €90.50* |
| **frozen-node-02** | Data (Frozen) | 16 GB RAM | **b3-16** (General Purpose) | 4 vCPUs / 16 GB | 100 GB local NVMe + 500 GB Block NVMe (Cache) | €90.50* |
| **kibana-fleet-01** | Kibana / HA Fleet | 16 GB RAM | **b3-16** (General Purpose) | 4 vCPUs / 16 GB | 100 GB local NVMe | €56.00 |
| **kibana-fleet-02** | Kibana / HA Fleet | 16 GB RAM | **b3-16** (General Purpose) | 4 vCPUs / 16 GB | 100 GB local NVMe | €56.00 |
| **ml-node** | Machine Learning | 16 GB RAM | **b3-16** (General Purpose) | 4 vCPUs / 16 GB | 100 GB local NVMe | €56.00 |
| **TOTAL** | | **180 GB RAM** | | **38 vCPUs / 183 GB**| | **€979.30** |

*\*Note: The Frozen Tier compute cost is €90.50 per node. This excludes the raw Object Storage bucket cost (€0.011/GB/month), which is calculated separately based on the stored data volume (~34 TB total = €374.00).*

---

## 2. Re-Engineered Storage Design: Searchable Snapshots

The initial storage proposal mapped cold/frozen volumes directly to Block Storage attached to instances. For 34 TB of data, this resulted in €2,444.20/month just for the frozen tier. 

### 2.1 Hybrid Storage Architecture

The optimized design utilizes **Elasticsearch Searchable Snapshots (Shared Cache Mode)** to decouple compute resources from storage capacity:

1. **Object Storage Backend**: The primary 34 TB corpus is stored in durable **OVH High Performance Object Storage (S3 API)** at €0.011/GB/month.
2. **Local NVMe SSD Cache**: Compute instances (`frozen-node-01` and `frozen-node-02`) are attached with **500 GB Block Storage NVMe** volumes. Combined with their 100 GB internal drives, they expose a **600 GB local cache** to store frequently accessed data blocks.
3. **Cache-to-Data Ratio**: A 1.2 TB total cluster cache for 34 TB of data yields a **~3.5% caching ratio**, which is optimal for historical and compliance search patterns.

---

## 3. Network Throughput & Bandwidth SLA Validation

Because frozen data blocks are fetched from the S3 API on-demand, network bandwidth dictates query latency.

* **Compute VM Bandwidth**: The OVH `b3-16` instance has a public and private network cap of **1 Gbit/s** (approx. **125 MB/s** raw throughput).
* **Historical Query SLA**: Running a large query that has to fetch an uncached **20 GB data block** from the S3 bucket will take:
  
  $$\text{Fetch Time} = \frac{20 \text{ GB} \times 1024 \text{ MB/GB}}{125 \text{ MB/s}} = 163.84 \text{ seconds} \approx \mathbf{2 \text{ minutes and 44 seconds}}$$

This fits within standard SLAs for compliance and security investigations.

---

## 4. Key Cluster Configuration Parameters

The following settings must be injected into the `elasticsearch.yml` configuration of the data nodes to control caching and limit network congestion during snapshot restoration:

```yaml
# 1. Throttle snapshot and recovery network throughput to prevent cluster split-brain/heartbeat issues
indices.recovery.max_bytes_per_sec: 80mb

# 2. Define the path where the local NVMe cache is mounted
path.shared_data: /mnt/nvme-cache/

# 3. Restrict cache usage (leaves 50 GB free space on the 600 GB volume for OS temporary files)
x-pack.searchable.snapshot.shared_cache.size: 550gb
```

---

## 5. Cost Comparison & Savings (EUR / Month)

| Tier / Component | Initial Plan (All Block Storage) | Optimized Plan (Searchable Snapshots) | Monthly Savings | % Change |
| :--- | :---: | :---: | :---: | :---: |
| **Hot Tier** | €605.13 | €605.13 | €0.00 | 0.0% |
| **Quorum / Tiebreaker** | €25.17 | €25.17 | €0.00 | 0.0% |
| **Kibana & Fleet Server** | €112.00 | €112.00 | €0.00 | 0.0% |
| **Machine Learning Node** | €56.00 | €56.00 | €0.00 | 0.0% |
| **Frozen Tier (Block vs. S3+Cache)**| €2,444.20 | **€555.00** | **- €1,889.20** | **- 77.3%** |
| **TOTAL** | **€3,298.50** | **€1,353.30** | **- €1,945.20** | **- 59.0%** |

> [!TIP]
> **Annual Cost Reduction**: Saves **€23,342.40 / year** on hosting costs while maintaining identical Hot tier ingestion and search performance.
