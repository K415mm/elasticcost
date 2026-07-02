# MSSP & SOC Commercial Cost Proposal: Client "Numeryx"

This proposal outlines the commercial and staffing structures for the security operations center (SOC) onboarding of **Numeryx** under **Scenario 2** (Minimum Ingest, Multi-Tier, 1 Year Retention). 

This document offers two distinct deployment models: **Option A (Dedicated On-Premise Private Cloud)** and **Option B (Managed Elastic Cloud)**.

---

## 1. SOC Analyst Staffing & Dedicated Operations

Operational delivery is driven by a tiered security operations team. The following allocations represent the client-allocated pricing based on dedication metrics:

| Operational Role | FTE Allocation (%) | Active Staff | Blended Monthly Salary | Monthly Client Cost |
| :--- | :---: | :---: | :---: | :---: |
| **Analyst Level 1 (L1)** | 10% | 3 | €2,500.00 | €750.00 |
| **Analyst Level 2 (L2)** | 5% | 2 | €4,000.00 | €400.00 |
| **Analyst Level 3 (L3)** | 2% | 1 | €5,500.00 | €110.00 |
| **SOC Engineer** | 5% | 1 | €5,500.00 | €275.00 |
| **SOC Manager** | 10% | 1 | €6,500.00 | €650.00 |
| **TOTAL STAFFING** | **32% Blended** | **8** | — | **€2,185.00 / Month** |

---

## 2. Option A: On-Premise Private Cloud Deployment

Option A places the cluster on dedicated virtual machines in a Private Cloud environment. We present the initial standard proposal alongside the **Optimized OVHcloud Design** which integrates searchable snapshots to reduce infrastructure costs.

### 2.1 Cost Breakdown Comparison (EUR / Month)

| Cost Component | Standard Proposal (All Block Storage) | Optimized OVHcloud Proposal (S3 + NVMe Cache) | Difference |
| :--- | :---: | :---: | :---: |
| **VM Hosting Infrastructure** | €1,725.73 | **€1,353.30** | - €372.43 |
| **Elasticsearch License (Platinum)** | €3,220.00 | **€3,220.00** | €0.00 |
| **Operational Maintenance** | €1,000.00 | **€1,000.00** | €0.00 |
| **SOC Staffing Cost** | €2,185.00 | **€2,185.00** | €0.00 |
| **TOTAL MONTHLY MRC** | **€8,130.73** | **€7,758.30** | **- €372.43** |
| **ONE-TIME SETUP COST** | **€10,000.00** | **€10,000.00** | — |

> [!TIP]
> By adopting the **Optimized OVHcloud Proposal**, the client reduces their annual On-Premise expenditure from **€97,568.76** to **€93,099.60**, representing a net annual savings of **€4,469.16**.

---

## 3. Option B: Managed Elastic Cloud Deployment (SaaS Model)

Option B deploys the architecture on Elastic Cloud (Azure East US 2) under a Platinum subscription. Rather than paying individual infrastructure and licensing costs, the client is billed under a unified **MDR Agent Package** based on monitored device counts.

### 3.1 MDR Agent Package Sizing & Pricing

| Agent Type | Device Count | Monthly Unit Price | Total Monthly Cost |
| :--- | :---: | :---: | :---: |
| **Unified Security Monitoring & Correlation (SIEM)** | 210 | €23.00 | €4,830.00 |
| **Expert-Led 24/7 Monitoring & Response (MDR)** | 80 | €27.60 | €2,208.00 |
| **Advanced Endpoint Protection (EDR)** | 600 | €9.20 | €5,520.00 |
| **TOTAL MDR PACKAGE** | **890** | — | **€12,558.00 / Month** |

*Note: The Elastic Cloud subscription cost (Reference: €5,647.35/month) is fully absorbed within the MDR Agent Package. No additional hosting or licensing costs are billed.*

### 3.2 Commercial Summary (Elastic Cloud)

* **Monthly Recurring Cost (MRC)**: **€12,558.00 / month**
* **One-Time Setup Cost**: **€10,000.00**
* **Licensing Tier**: Platinum (absorbs all multi-tier compute & storage costs on Azure)

---

## 4. Option A vs. Option B Comparison

| Metric | Option A (Optimized On-Premise) | Option B (Managed Elastic Cloud) | Recommendation |
| :--- | :---: | :---: | :--- |
| **Monthly Cost (MRC)** | **€7,758.30** | **€12,558.00** | **Option A** is **38.2% cheaper** monthly. |
| **Management Overhead** | High (customer maintains OS, VMs, cluster health) | Low (SaaS model, zero infrastructure management) | **Option B** is preferred for teams with limited Ops staff. |
| **Resource Isolation** | 100% Dedicated Private Cloud VMs | Shared Cloud tenant (Azure EE2) | **Option A** is preferred for strict compliance constraints. |
| **Scalability** | Manual VM provisioning and storage attachment | Automatic scaling and multi-zone failover | **Option B** scales seamlessly on-demand. |
