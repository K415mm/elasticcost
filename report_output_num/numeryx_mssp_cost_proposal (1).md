# MSSP & SOC Cost Proposal Report: Numeryx

This document details the estimated costs and client offer proposal for **Client "Numeryx"** under the **Scenario 2** scenario.

*   **Active Currency**: **EUR**
*   **Date**: 2026-07-01

---

## 1. SOC Analyst Staffing Allocations

| Operational Role | Dedication (%) | Staff Count | Monthly Salary | Calculated Client Cost |
| :--- | :---: | :---: | :---: | :---: |
| **Analyst Level 1 (L1)** | 10% | 3 | €2,500.00 | €750.00 |
| **Analyst Level 2 (L2)** | 5% | 2 | €4,000.00 | €400.00 |
| **Analyst Level 3 (L3)** | 2% | 1 | €5,500.00 | €110.00 |
| **SOC Engineer** | 5% | 1 | €5,500.00 | €275.00 |
| **SOC Manager** | 10% | 1 | €6,500.00 | €650.00 |
| **Total Staffing Cost** | | | | **€2,185.00** |


---

## 2. Option A: On-Premise Deployment Offer

### VM Hosting Infrastructure

| Node Type / Role | Instance Count | RAM / Node | Storage / Node | Total Monthly Cost |
| :--- | :---: | :---: | :---: | :---: |
| **hot-node-01** (Master / Data (Hot)) | x1 | 32 GB | 1.5 TB (Local NVMe SSD) | €233.02 |
| **hot-node-02** (Master / Data (Hot)) | x1 | 32 GB | 1.5 TB (Local NVMe SSD) | €233.02 |
| **hot-node-03** (Master / Data (Hot)) | x1 | 32 GB | 1.5 TB (Local NVMe SSD) | €233.02 |
| **master-tiebreaker** (Dedicated Master (Quorum)) | x1 | 4 GB | 20 GB (Local SSD) | €17.40 |
| **frozen-node-01** (Data (Frozen)) | x1 | 16 GB | 17 TB (SATA SSD (Snapshot Cache)) | €403.38 |
| **frozen-node-02** (Data (Frozen)) | x1 | 16 GB | 17 TB (SATA SSD (Snapshot Cache)) | €403.38 |
| **kibana-fleet-01** (Kibana / Fleet Server) | x1 | 16 GB | 50 GB (Local SSD) | €67.50 |
| **kibana-fleet-02** (Kibana / Fleet Server (HA)) | x1 | 16 GB | 50 GB (Local SSD) | €67.50 |
| **ml-node** (Dedicated Machine Learning) | x1 | 16 GB | 50 GB (Local SSD) | €67.50 |
| **Total Hosting Cost** | | | | **€1,725.73** |

### Software Licensing & Maintenance

*   **Elastic Search License Status**: Dedicated
*   **Monthly License Cost Equivalent**: **€3,220.00**
*   **Monthly Operational Maintenance**: **€1,000.00**

### Profit Markup & Commercial Benefits (On-Premise)

| Profit/Benefit Factor | Percentage (%) | Monthly Profit Amount |
| :--- | :---: | :---: |
| Assurance Benefit | 0% | €0.00 |
| Marketing Benefit | 0% | €0.00 |
| SOC Manager Profit | 0% | €0.00 |
| CEO Profit | 0% | €0.00 |
| Fixed Profit | 0% | €0.00 |
| **Total Profit Margin Markup** | **0%** | **€0.00** |

### Commercial Proposal Summary (On-Premise)

*   **Estimated Base Cost (MRC)**: **€8,130.73**
*   **Total Commercial Markup**: **+€0.00** (+0%)
*   **Final Client Offered MRC (Price)**: **€8,130.73**
*   **Upfront Setup Cost (One-Time)**: **€10,000.00**


---

## 3. Option B: Elastic Cloud Deployment Offer

### Elastic Cloud Node Sizing & Reference Pricing (Azure East US 2)

*   **Subscription Tier**: **Platinum**

| Node Sizing Item | Operational Role | Count | RAM / Node | Matched Instance SKU | Hourly Rate | Total Monthly Cost |
| :--- | :--- | :---: | :---: | :--- | :---: | :---: |
| **hot-node-01** | Master / Data (Hot) | x1 | 32 GB | `azure.es.master.fsv2` | $0.0462 /GB-hr | €992.89 |
| **hot-node-02** | Master / Data (Hot) | x1 | 32 GB | `azure.es.master.fsv2` | $0.0462 /GB-hr | €992.89 |
| **hot-node-03** | Master / Data (Hot) | x1 | 32 GB | `azure.es.master.fsv2` | $0.0462 /GB-hr | €992.89 |
| **master-tiebreaker** | Dedicated Master (Quorum) | x1 | 4 GB | `azure.es.master.fsv2` | $0.0462 /GB-hr | €124.11 |
| **frozen-node-01** | Data (Frozen) | x1 | 16 GB | `azure.es.datafrozen.edsv4` | $0.0625 /GB-hr | €671.60 |
| **frozen-node-02** | Data (Frozen) | x1 | 16 GB | `azure.es.datafrozen.edsv4` | $0.0625 /GB-hr | €671.60 |
| **kibana-fleet-01** | Kibana / Fleet Server | x1 | 16 GB | `azure.apm.e32sv3` | $0.0328 /GB-hr | €352.45 |
| **kibana-fleet-02** | Kibana / Fleet Server (HA) | x1 | 16 GB | `azure.apm.e32sv3` | $0.0328 /GB-hr | €352.45 |
| **ml-node** | Dedicated Machine Learning | x1 | 16 GB | `azure.es.ml.fsv2` | $0.0462 /GB-hr | €496.45 |
| **Total Subscription Cost (Reference)** | | | | | | **€5,647.35** |

*(Note: This cost is for estimation reference only and is NOT billed separately. All cloud subscription, hosting, staffing, and benefits are fully covered in the agent rates below.)*

### MDR Agent Package Coverage

| Agent Type | Mapped Devices | Monthly Unit Price | Total Monthly Cost |
| :--- | :---: | :---: | :---: |
| **Unified Security Monitoring & Correlation (SIEM)** | 210 | €23.00 | €4,830.00 |
| **Expert-Led 24/7 Monitoring & Response (MDR)** | 80 | €27.60 | €2,208.00 |
| **Advanced Endpoint Protection (EDR)** | 600 | €9.20 | €5,520.00 |
| **Total MDR Agent Package Cost** | | | **€12,558.00** |

### Commercial Proposal Summary (Elastic Cloud)

*   **Monthly Agent Package Cost**: **€12,558.00**
*   **Commercial Markup**: **$0.00** *(Agent rates include hosting and profits)*
*   **Final Client Offered MRC (Price)**: **€12,558.00**
*   **Upfront Setup Cost (One-Time)**: **€10,000.00**

