---
title: "Log Retention — 6 Architecture Scenarios"
subtitle: "Rétention des Logs — 6 Scénarios d'Architecture"
version: "2.0"
language: "EN / FR"
---

<div align="center">

# 📦 Log Retention Architecture
## 6 Scenarios — From Lean to Enterprise-Grade
## 6 Scénarios — Du Minimal à l'Entreprise

> *"Store smart. Search fast. Comply always."*
> *« Stocker intelligemment. Rechercher rapidement. Toujours être conforme. »*

</div>

---

## 🇬🇧 ENGLISH SECTION

### Overview

RaiseGuard MDR 360° uses a **4-Tier Index Lifecycle Management (ILM)** architecture powered by Elasticsearch to manage the cost and performance of log retention. Data moves automatically through storage tiers based on its age and access frequency:

```
LOG INGESTION
     │
     ▼
┌──────────────────────────────────────────────────────────────┐
│  HOT TIER  │  WARM TIER  │  COLD TIER  │  FROZEN TIER        │
│  NVMe SSD  │  SATA SSD   │  Object     │  Object Store        │
│            │  / HDD      │  Store +    │  + On-Demand         │
│  (Active   │  (Read-Only │  Local Cache│  Cache Only          │
│  Queries)  │  Index)     │  (Searchable│  (Compliance         │
│            │             │  Snapshots) │  Archive)            │
├────────────┼─────────────┼─────────────┼──────────────────────┤
│ RAM:Disk   │  1:80       │  1:100      │  1:160               │
│ Ratio 1:30 │             │             │                      │
├────────────┼─────────────┼─────────────┼──────────────────────┤
│ Replica: 1 │  Replica: 1 │  Replica: 0 │  Replica: 0          │
│ (HA)       │  (HA)       │  (Snapshots)│  (Snapshots)         │
└────────────┴─────────────┴─────────────┴──────────────────────┘
```

**Why tiered storage matters:**
- Hot tier stores the most recent, frequently searched data on fast NVMe SSDs
- Warm and Cold tiers use progressively cheaper storage for older data
- Frozen tier uses object storage (S3/Azure Blob) at near-zero cost for long-term archives
- Moving data through tiers can save **up to 46% in physical disk capacity** compared to flat Hot-only storage

---

### Storage Tier Specifications

| Tier | Storage Media | Replication | RAM:Disk Ratio | Max Storage / 64 GB Node | Best For |
|---|---|---|---|---|---|
| **Hot** | Local NVMe SSD | Primary + 1 HA Replica | 1:30 | 1.9 TB | Active queries, detection rules, dashboards |
| **Warm** | SATA SSD / HDD | Primary + 1 HA Replica | 1:80 | 5.1 TB | Recent compliance queries, forensic investigation |
| **Cold** | Object Store + Local Cache | Searchable Snapshot (0% replica) | 1:100 | 6.4 TB | Infrequent queries, audit access |
| **Frozen** | Object Store + On-Demand Cache | Partial Snapshot (0% replica) | 1:160 | 10.2 TB | Long-term compliance archives, annual audits |

---

### Workload Profiles

Each scenario uses one of three **ingest workload profiles** based on the client's asset activity level:

| Profile | Daily Raw Volume | Typical Client | Description |
|---|---|---|---|
| **Minimum** | ~3.32 GB/day | Small offices, off-hours | Baseline activity, weekends, light logging |
| **Average** | ~19.19 GB/day | Standard enterprise | Normal weekday corporate activity |
| **Maximum** | ~66.19 GB/day | High-activity enterprise | Peak hours, burst events, heavy log sources |

---

### 📋 Scenario 1 — Minimum Ingest, Hot-Only (7-Day Retention)

**Profile**: Entry-level | **Retention**: 7 days | **Tiers Used**: Hot only

```
Architecture:
┌─────────────────────────────────────────────────┐
│  HOT TIER ONLY — 7 Days on NVMe SSD             │
│                                                  │
│  hot-node-01    [Master/Data] 16 GB RAM 100 GB  │
│  hot-node-02    [Master/Data] 16 GB RAM 100 GB  │
│  master-tiebreaker [Master]   4 GB RAM  20 GB   │
│  kibana-fleet   [Kibana]      8 GB RAM  50 GB   │
└─────────────────────────────────────────────────┘
```

| Metric | Value |
|---|---|
| **Daily Raw Volume** | 3.32 GB/day |
| **Daily Indexed (×1.25)** | 4.15 GB/day |
| **Total Cluster Storage** | 58.10 GB (7 days × Primary + 1 Replica) |
| **Total Cluster RAM** | 44 GB |
| **ERU Licenses Required** | **1 ERU** |
| **Storage Saved vs. Flat** | N/A (baseline) |

**When to Use This Scenario:**
- Small organizations with minimal regulatory compliance requirements
- Development or staging environments
- Proof of concept deployments
- Organizations where a 7-day investigation window is sufficient

**Limitations:**
- No compliance archive — logs older than 7 days are permanently deleted
- Not suitable for ISO 27001, NIS2, or PCI-DSS which require 90-365 day retention

---

### 📋 Scenario 2 — Minimum Ingest, Multi-Tier (365-Day Retention)

**Profile**: Entry-level | **Retention**: 365 days | **Tiers Used**: Hot + Cold

```
Architecture:
┌──────────────────────────────────────────────────────────────┐
│  HOT (30 days) + COLD (Days 31-365)                          │
│                                                              │
│  hot-node-01    [Master/Data Hot]  24 GB RAM   300 GB NVMe  │
│  hot-node-02    [Master/Data Hot]  24 GB RAM   300 GB NVMe  │
│  master-tiebreaker [Master]         4 GB RAM    20 GB       │
│  cold-node-01   [Data Cold]         8 GB RAM  1.5 TB HDD    │
│  cold-node-02   [Data Cold HA]      8 GB RAM  1.5 TB HDD    │
│  ml-node-01     [Machine Learning] 16 GB RAM   50 GB        │
│  kibana-fleet-01 [Kibana]           8 GB RAM   50 GB        │
│  kibana-fleet-02 [Kibana HA]        8 GB RAM   50 GB        │
└──────────────────────────────────────────────────────────────┘
```

| Metric | Value |
|---|---|
| **Daily Raw Volume** | 3.32 GB/day |
| **Hot Tier Storage** | 249 GB (30 days × Primary + Replica) |
| **Cold Tier Storage** | 1,390 GB (335 days × Searchable Snapshots) |
| **Total Cluster Storage** | **1.64 TB** |
| **Total Cluster RAM** | 100 GB |
| **ERU Licenses Required** | **2 ERUs** |

**When to Use This Scenario:**
- Small organizations with **annual compliance requirements** (ISO 27001, NIS2)
- Organizations that need ML anomaly detection capability (dedicated ML node)
- Environments requiring Kibana high-availability
- Security-mature small enterprises that want full-year audit trails

---

### 📋 Scenario 3 — Average Ingest, Hot-Only (30-Day Retention)

**Profile**: Standard enterprise | **Retention**: 30 days | **Tiers Used**: Hot only

```
Architecture:
┌──────────────────────────────────────────────────────────────┐
│  HOT TIER ONLY — 30 Days on NVMe SSD                        │
│                                                              │
│  master-node-01 [Dedicated Master] 8 GB RAM    50 GB       │
│  master-node-02 [Dedicated Master] 8 GB RAM    50 GB       │
│  master-node-03 [Dedicated Master] 8 GB RAM    50 GB       │
│  hot-node-01    [Dedicated Hot]   32 GB RAM   1.0 TB NVMe  │
│  hot-node-02    [Dedicated Hot]   32 GB RAM   1.0 TB NVMe  │
│  kibana-fleet   [Kibana]          16 GB RAM   100 GB       │
│  logstash-node  [Logstash]        16 GB RAM    50 GB       │
└──────────────────────────────────────────────────────────────┘
```

| Metric | Value |
|---|---|
| **Daily Raw Volume** | 19.19 GB/day |
| **Total Cluster Storage** | **1.44 TB** (30 days × Primary + Replica) |
| **Total Cluster RAM** | 120 GB |
| **ERU Licenses Required** | **2 ERUs** |
| **RAM:Disk Ratio (Hot)** | 1:22.5 (safe, max is 1:30) |

**When to Use This Scenario:**
- Standard mid-size enterprises with a 30-day investigation window
- Organizations with low compliance pressure but high operational security needs
- Initial SIEM deployment before deciding on longer retention

---

### 📋 Scenario 4 — Average Ingest, Full Multi-Tier (365-Day Retention)

**Profile**: Standard enterprise | **Retention**: 365 days | **Tiers Used**: Hot + Warm + Cold + Frozen

```
Architecture:
┌────────────────────────────────────────────────────────────────────┐
│  4-TIER LIFECYCLE: Hot(7d) → Warm(23d) → Cold(60d) → Frozen(275d) │
│                                                                     │
│  master-node-01/02/03  [Dedicated Master] 3×8GB   3×50GB SSD      │
│  hot-node-01           [Dedicated Hot]   32GB    400GB NVMe       │
│  hot-node-02           [Dedicated Hot]   32GB    400GB NVMe       │
│  warm-node-01          [Dedicated Warm]  32GB    1.2TB SATA       │
│  warm-node-02          [Dedicated Warm]  32GB    1.2TB SATA       │
│  cold-node-01          [Dedicated Cold]  32GB    1.5TB SATA       │
│  frozen-node-01        [Dedicated Frozen]32GB    2.0TB SATA       │
│  kibana-fleet          [Kibana]          16GB    100GB             │
│  logstash-node         [Logstash]        16GB     50GB             │
└────────────────────────────────────────────────────────────────────┘
```

| Metric | Value |
|---|---|
| **Daily Raw Volume** | 19.19 GB/day |
| **Annual Raw Data** | 7.00 TB |
| **Hot Tier Storage** | 335.86 GB |
| **Warm Tier Storage** | 1,103.54 GB |
| **Cold Tier Storage** | 1,439.40 GB |
| **Frozen Tier Storage** | 6,597.25 GB |
| **Total Cluster Storage** | **9.48 TB** |
| **Flat Hot Storage (equivalent)** | 17.51 TB |
| **💾 Disk Savings vs. Flat** | **~46% saved** |
| **Total Cluster RAM** | 248 GB |
| **ERU Licenses Required** | **4 ERUs** |

**When to Use This Scenario:**
- Enterprises with **mandatory annual compliance** (ISO 27001, NIS2, PCI-DSS, GDPR)
- Organizations under regulatory audit requirements
- Mid-size enterprises that want the best balance of cost and compliance coverage
- **This is the recommended production scenario for most enterprise clients**

---

### 📋 Scenario 5 — Maximum Ingest, Hot + Warm (90-Day Retention)

**Profile**: High-volume enterprise | **Retention**: 90 days | **Tiers Used**: Hot + Warm

```
Architecture:
┌────────────────────────────────────────────────────────────────────┐
│  2-TIER LIFECYCLE: Hot(14d) → Warm(76d)                            │
│                                                                     │
│  master-node-01/02/03  [Dedicated Master]  3×8GB    3×50GB        │
│  hot-node-01           [Dedicated Hot]    64GB    1.5TB NVMe      │
│  hot-node-02           [Dedicated Hot]    64GB    1.5TB NVMe      │
│  warm-node-01          [Dedicated Warm]   64GB    4.5TB SATA      │
│  warm-node-02          [Dedicated Warm]   64GB    4.5TB SATA      │
│  warm-node-03          [Dedicated Warm]   64GB    4.5TB SATA      │
│  kibana-fleet          [Kibana]           16GB    100GB            │
│  logstash-node-01/02   [Logstash × 2]   2×16GB   2×50GB          │
└────────────────────────────────────────────────────────────────────┘
```

| Metric | Value |
|---|---|
| **Daily Raw Volume** | 66.19 GB/day |
| **Daily Indexed** | 82.74 GB/day |
| **Hot Tier Storage** | 2,316.72 GB (14 days) |
| **Warm Tier Storage** | 12,576.48 GB (76 days) |
| **Total Cluster Storage** | **14.89 TB** |
| **Total Cluster RAM** | 392 GB |
| **RAM:Disk Ratio (Hot)** | 1:18.1 ✅ | 
| **RAM:Disk Ratio (Warm)** | 1:65.5 ✅ |
| **ERU Licenses Required** | **7 ERUs** |

**When to Use This Scenario:**
- High-volume environments with large device counts and verbose log sources
- Organizations with a **90-day compliance window** (quarterly audit)
- Enterprises transitioning from lower retention and expecting to grow
- High-frequency trading firms, telcos, large hospitals

---

### 📋 Scenario 6 — Maximum Ingest, Full Multi-Tier (365-Day Retention)

**Profile**: High-volume enterprise | **Retention**: 365 days | **Tiers Used**: Hot + Warm + Cold + Frozen

```
Architecture:
┌────────────────────────────────────────────────────────────────────┐
│  4-TIER LIFECYCLE: Hot(7d) → Warm(23d) → Cold(60d) → Frozen(275d) │
│                                                                     │
│  master-node-01/02/03  [Dedicated Master]  3×8GB    3×50GB        │
│  hot-node-01           [Dedicated Hot]    64GB    1.0TB NVMe      │
│  hot-node-02           [Dedicated Hot]    64GB    1.0TB NVMe      │
│  warm-node-01          [Dedicated Warm]   64GB    2.5TB SATA      │
│  warm-node-02          [Dedicated Warm]   64GB    2.5TB SATA      │
│  cold-node-01          [Dedicated Cold]   32GB    5.5TB SATA      │
│  frozen-node-01        [Dedicated Frozen] 32GB   24.0TB SATA      │
│  kibana-fleet          [Kibana]           16GB    100GB            │
│  logstash-node-01/02   [Logstash × 2]   2×16GB   2×50GB          │
└────────────────────────────────────────────────────────────────────┘
```

| Metric | Value |
|---|---|
| **Daily Raw Volume** | 66.19 GB/day |
| **Annual Raw Data** | **24.16 TB** |
| **Hot Tier Storage** | 1,158.36 GB |
| **Warm Tier Storage** | 3,806.04 GB |
| **Cold Tier Storage** | 4,964.40 GB |
| **Frozen Tier Storage** | 22,753.50 GB |
| **Total Cluster Storage** | **32.68 TB** |
| **Flat Hot Storage (equivalent)** | **60.40 TB** |
| **💾 Disk Savings vs. Flat** | **~46% saved** |
| **Total Cluster RAM** | 392 GB |
| **ERU Licenses Required** | **7 ERUs** (same as Scenario 5!) |

> **Key Insight**: Scenario 6 uses the **exact same RAM and ERU licensing as Scenario 5** (90-day). Extending retention from 90 to 365 days only requires adding low-cost Warm and Object storage — it does **not** increase licensing costs.

**When to Use This Scenario:**
- Large enterprises requiring **annual regulatory compliance** at maximum scale
- Government agencies, financial institutions, critical infrastructure operators
- Organizations under strict audit obligations (PCI-DSS Level 1, ISO 27001, HIPAA)
- Environments with very large device inventories (firewalls, servers, switches at scale)

---

### 📊 Scenario Comparison Dashboard

| Scenario | Workload | Tiers | Retention | Storage | RAM | ERUs | Best For |
|---|---|---|---|---|---|---|---|
| **1** | Minimum | Hot | 7 days | 58 GB | 44 GB | 1 | POC / Dev |
| **2** | Minimum | Hot+Cold | 365 days | 1.64 TB | 100 GB | 2 | Small + Compliant |
| **3** | Average | Hot | 30 days | 1.44 TB | 120 GB | 2 | Standard Ops |
| **4** | Average | Hot+Warm+Cold+Frozen | 365 days | 9.48 TB | 248 GB | 4 | **Enterprise Standard** |
| **5** | Maximum | Hot+Warm | 90 days | 14.89 TB | 392 GB | 7 | High-Volume Quarterly |
| **6** | Maximum | Hot+Warm+Cold+Frozen | 365 days | 32.68 TB | 392 GB | 7 | **Enterprise Compliance** |

```
ERU Cost Comparison:

  1 ERU ██ (Scenario 1)
  2 ERUs ████ (Scenarios 2, 3)
  4 ERUs ████████ (Scenario 4)
  7 ERUs ██████████████ (Scenarios 5 & 6)

  🔑 Key: 1 ERU = 64 GB RAM → Elastic Self-Managed License
```

---

### 🔑 ILM Architecture Principle

```
Storage Cost Optimization Through ILM:

Scenario 6 — Without ILM (flat Hot):
████████████████████████████████████████████████████████████ 60.40 TB

Scenario 6 — With ILM (4 tiers):
███████████████████████████████████ 32.68 TB  ← 46% savings!

The archived 275 days in Frozen tier cost ~$5-15/TB/month
vs. NVMe SSD Hot storage at $100-300/TB/month
```

---

## 🇫🇷 SECTION FRANÇAISE

### Vue d'Ensemble

RaiseGuard MDR 360° utilise une architecture **ILM (Index Lifecycle Management) à 4 niveaux** propulsée par Elasticsearch pour gérer le coût et les performances de la rétention des logs. Les données se déplacent automatiquement à travers les niveaux de stockage en fonction de leur âge et de leur fréquence d'accès.

### Spécifications des Niveaux de Stockage

| Niveau | Support de Stockage | Réplication | Ratio RAM:Disque | Stockage Max / Nœud 64 GB |
|---|---|---|---|---|
| **Hot** | NVMe SSD local | Primary + 1 Replica HA | 1:30 | 1,9 To |
| **Warm** | SATA SSD / HDD | Primary + 1 Replica HA | 1:80 | 5,1 To |
| **Cold** | Object Store + Cache local | Snapshot Consultable (0% replica) | 1:100 | 6,4 To |
| **Frozen** | Object Store + Cache à la Demande | Snapshot Partiel (0% replica) | 1:160 | 10,2 To |

### Profils de Charge de Travail

| Profil | Volume Journalier Brut | Client Type | Description |
|---|---|---|---|
| **Minimum** | ~3,32 Go/jour | Petits bureaux, hors-heures | Activité de base, week-ends, journalisation légère |
| **Moyen** | ~19,19 Go/jour | Entreprise standard | Activité corporate normale en semaine |
| **Maximum** | ~66,19 Go/jour | Entreprise haute activité | Heures de pointe, événements burst, sources de logs lourdes |

---

### 📋 Scénario 1 — Ingestion Minimale, Hot-Only (7 jours)

| Métrique | Valeur |
|---|---|
| **Volume Journalier Brut** | 3,32 Go/jour |
| **Stockage Total Cluster** | 58,10 Go |
| **RAM Totale Cluster** | 44 Go |
| **Licences ERU Requises** | **1 ERU** |

**Quand Utiliser ce Scénario :** Petites organisations avec des exigences minimales de conformité réglementaire, environnements de développement ou de test, déploiements preuve de concept.

---

### 📋 Scénario 2 — Ingestion Minimale, Multi-Niveaux (365 jours)

| Métrique | Valeur |
|---|---|
| **Stockage Niveau Hot** | 249 Go (30 jours) |
| **Stockage Niveau Cold** | 1 390 Go (335 jours) |
| **Stockage Total Cluster** | **1,64 To** |
| **RAM Totale** | 100 Go |
| **Licences ERU** | **2 ERUs** |

**Quand Utiliser ce Scénario :** Petites organisations avec des exigences de conformité annuelle (ISO 27001, NIS2), organisations nécessitant une détection ML.

---

### 📋 Scénario 3 — Ingestion Moyenne, Hot-Only (30 jours)

| Métrique | Valeur |
|---|---|
| **Volume Journalier Brut** | 19,19 Go/jour |
| **Stockage Total Cluster** | **1,44 To** |
| **RAM Totale** | 120 Go |
| **Licences ERU** | **2 ERUs** |

---

### 📋 Scénario 4 — Ingestion Moyenne, Multi-Niveaux Complet (365 jours) ⭐ Recommandé

| Métrique | Valeur |
|---|---|
| **Données Brutes Annuelles** | 7,00 To |
| **Stockage Niveau Hot** | 335,86 Go |
| **Stockage Niveau Warm** | 1 103,54 Go |
| **Stockage Niveau Cold** | 1 439,40 Go |
| **Stockage Niveau Frozen** | 6 597,25 Go |
| **Stockage Total Cluster** | **9,48 To** |
| **Stockage Flat Hot (équivalent)** | 17,51 To |
| **💾 Économies Disque** | **~46% économisés** |
| **RAM Totale** | 248 Go |
| **Licences ERU** | **4 ERUs** |

---

### 📋 Scénario 5 — Ingestion Maximum, Hot+Warm (90 jours)

| Métrique | Valeur |
|---|---|
| **Volume Journalier Brut** | 66,19 Go/jour |
| **Stockage Niveau Hot** | 2 316,72 Go |
| **Stockage Niveau Warm** | 12 576,48 Go |
| **Stockage Total Cluster** | **14,89 To** |
| **RAM Totale** | 392 Go |
| **Licences ERU** | **7 ERUs** |

---

### 📋 Scénario 6 — Ingestion Maximum, Multi-Niveaux Complet (365 jours)

| Métrique | Valeur |
|---|---|
| **Données Brutes Annuelles** | **24,16 To** |
| **Stockage Total Cluster** | **32,68 To** |
| **Stockage Flat Hot (équivalent)** | **60,40 To** |
| **💾 Économies Disque** | **~46% économisés** |
| **RAM Totale** | 392 Go (identique au Scénario 5!) |
| **Licences ERU** | **7 ERUs** (identique au Scénario 5!) |

> **Insight Clé** : Le Scénario 6 utilise la **même RAM et les mêmes licences ERU que le Scénario 5** (90 jours). L'extension de la rétention de 90 à 365 jours ne nécessite que l'ajout de stockage Warm et Object à faible coût — cela **n'augmente pas** les coûts de licence.

---

### 📊 Tableau de Comparaison des Scénarios

| Scénario | Charge | Niveaux | Rétention | Stockage | RAM | ERUs | Idéal Pour |
|---|---|---|---|---|---|---|---|
| **1** | Minimum | Hot | 7 jours | 58 Go | 44 Go | 1 | POC / Dev |
| **2** | Minimum | Hot+Cold | 365 jours | 1,64 To | 100 Go | 2 | Petite + Conforme |
| **3** | Moyen | Hot | 30 jours | 1,44 To | 120 Go | 2 | Ops Standard |
| **4** | Moyen | 4 Niveaux | 365 jours | 9,48 To | 248 Go | 4 | **Standard Entreprise** |
| **5** | Maximum | Hot+Warm | 90 jours | 14,89 To | 392 Go | 7 | Volume Élevé Trim. |
| **6** | Maximum | 4 Niveaux | 365 jours | 32,68 To | 392 Go | 7 | **Conformité Entreprise** |

---

*Document Version 2.0 | Log Retention Scenarios | © RaiseGuard — Confidential*
