---
title: "Elastic Stack Technical Foundation"
subtitle: "Fondation Technique Elastic Stack"
version: "2.0"
language: "EN / FR"
---

<div align="center">

# ⚙️ Elastic Stack Technical Foundation
## The Engine Behind RaiseGuard MDR 360°
## Le Moteur derrière RaiseGuard MDR 360°

> *"Built on the world's leading open-core search and analytics engine."*
> *« Construit sur le principal moteur de recherche et d'analytique open-core au monde. »*

</div>

---

## 🇬🇧 ENGLISH SECTION

### 1. Overview

RaiseGuard MDR 360° is powered by the **Elastic Stack** — the industry's leading open-core platform for security information management. The stack consists of four core components that work together to provide ingestion, storage, detection, and visualization:

```
┌──────────────────────────────────────────────────────────────────┐
│                      ELASTIC STACK                                │
├───────────────────┬───────────────────────────────────────────────┤
│   DATA SOURCES    │  Elastic Agent   Fleet Server   Logstash      │
│   (Collection)    │         ↓             ↓            ↓          │
├───────────────────┼───────────────────────────────────────────────┤
│   STORAGE &       │          Elasticsearch Cluster                 │
│   INDEXING        │  Hot Tier → Warm Tier → Cold Tier → Frozen    │
├───────────────────┼───────────────────────────────────────────────┤
│   VISUALIZATION   │    Kibana (Security Solution + Dashboards)     │
│   & DETECTION     │    SIEM Rules + ML Jobs + Case Management      │
└───────────────────┴───────────────────────────────────────────────┘
```

---

### 2. Core Components

#### 2.1 Elasticsearch — The Search & Storage Engine

Elasticsearch is a **distributed, RESTful search and analytics engine** built on Apache Lucene. In the context of RaiseGuard MDR 360°, it serves as:

- **Primary log store**: Stores all ingested events in structured JSON documents
- **Detection engine**: Runs SIEM detection rules as continuous queries
- **ILM controller**: Manages automatic data movement across storage tiers
- **ML platform**: Runs anomaly detection and unsupervised ML jobs

**Key Sizing Principle**:

```
Storage Required = Raw Volume × Expansion Factor × (1 + Replicas)

Where:
  Expansion Factor = 1.15 to 1.25 (ECS mapping, tokenizers, keyword structures)
  Replicas = 1 for Hot/Warm tiers (HA), 0 for Cold/Frozen (snapshot-based)
```

**Licensing Model**:

Elasticsearch self-managed licenses are measured in **ERUs (Elastic Resource Units)**:
- 1 ERU = 64 GB of RAM allocated to Elasticsearch, Kibana, Logstash, and Fleet nodes
- The ERU count is rounded up: `ERUs = ⌈Total RAM GB ÷ 64⌉`

#### 2.2 Elastic Agent & Fleet Server — The Collection Layer

**Elastic Agent** is the unified data collection framework that replaces multiple Beats agents:

| Capability | Description |
|---|---|
| **Endpoint Integration** | Collects OS events, security logs, performance metrics |
| **Elastic Defend** | Endpoint protection and behavioral monitoring |
| **Network Integrations** | Processes Syslog, SNMP, API feeds from network devices |
| **Cloud Integrations** | Connects to AWS/Azure/GCP log APIs |
| **Fleet-Managed** | Central policy deployment, upgrade, and monitoring from Kibana |

**Fleet Server** provides centralized management of all Elastic Agents:
- TLS-encrypted bidirectional communication
- Policy enforcement and remote configuration
- Agent health monitoring and automatic reconnection
- Remote response action dispatch (for EDR)

#### 2.3 Logstash — The Ingest Pipeline

**Logstash** is the data processing pipeline that sits between log sources and Elasticsearch:

```
Raw Log Event → [LOGSTASH PIPELINE]
    │
    ├── INPUT (receive from Beats, Syslog, Kafka...)
    ├── FILTER (parse, normalize, enrich, geoip, ECS mapping)
    └── OUTPUT (send to Elasticsearch)
         │
         └── Storage Optimization: Remove event.original field
             (saves up to 30% storage after normalization)
```

#### 2.4 Kibana — The Security Dashboard

**Kibana** provides the operational interface for the SOC team:

| Kibana Feature | SOC Use Case |
|---|---|
| **Security Solution** | SIEM alert management, case tracking, timeline investigation |
| **Dashboards** | Real-time threat visibility panels |
| **Fleet** | Elastic Agent management console |
| **Discover** | Ad-hoc log investigation and search |
| **Alerts** | Threshold, ML, and detection rule firing |
| **Cases** | Incident management and analyst collaboration |
| **ML** | Anomaly detection job configuration and results |

---

### 3. Storage Architecture Deep Dive

#### 3.1 Index Lifecycle Management (ILM)

ILM is the Elasticsearch feature that **automates data movement** across storage tiers based on configurable time policies:

```
Day 0                Day 7          Day 30           Day 90          Day 365
  │                    │               │                │               │
  ▼                    ▼               ▼                ▼               ▼
[INGEST]──────────► [HOT]──────────► [WARM]──────────► [COLD]────────► [FROZEN]
  │                NVMe SSD          SATA SSD          Object Store    Object Store
  │                + Replica         + Replica         Snapshot        Snapshot
  │                  (fast)           (cost-opt)       (searchable)    (archive)
  │
  └── Active queries, detection rules, dashboards run on HOT tier data
```

**Replication Strategy by Tier**:

| Tier | Replica Setting | Rationale |
|---|---|---|
| **Hot** | `number_of_replicas: 1` | HA protection: survive 1 node failure |
| **Warm** | `number_of_replicas: 1` | HA protection during active compliance window |
| **Cold** | `number_of_replicas: 0` | Searchable snapshot handles failover from object store |
| **Frozen** | `number_of_replicas: 0` | On-demand mount from object store; no local replica needed |

#### 3.2 Storage Sizing Constants

| Tier | RAM:Disk Ratio | Max Storage per 64 GB Node |
|---|---|---|
| **Hot** (NVMe SSD) | 1:30 | 1.9 TB |
| **Warm** (SATA SSD/HDD) | 1:80 | 5.1 TB |
| **Cold** (Object + Cache) | 1:100 | 6.4 TB |
| **Frozen** (Object + On-Demand) | 1:160 | 10.2 TB |

#### 3.3 Ingest Optimization — Raw Field Pruning

After events are successfully parsed into ECS-structured fields, the original raw log string is removed via an **ingest pipeline**:

```json
{
  "description": "Prune raw fields from parsed logs to optimize storage",
  "processors": [
    {
      "remove": {
        "field": "event.original",
        "ignore_missing": true
      }
    }
  ]
}
```

> **Note**: Raw field pruning is disabled for log sources under strict regulatory requirements where original log integrity must be preserved for legal evidence.

---

### 4. High-Availability Architecture

Production RaiseGuard deployments follow the **3-zone quorum architecture**:

```
                    PRODUCTION CLUSTER
    ┌───────────────────────────────────────────────────┐
    │                                                   │
    │  AZ-1 (Primary)   AZ-2 (Secondary)  AZ-3 (Tiebreaker)
    │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐
    │  │ Hot-Node-01 │  │ Hot-Node-02 │  │  Master-03  │
    │  │ (Primary)   │  │ (Replica)   │  │  (Quorum)   │
    │  └─────────────┘  └─────────────┘  └─────────────┘
    │         │                │                 │
    │         └────────────────┘─────────────────┘
    │                          │
    │              Shared Snapshot Store
    │            (Azure Blob / AWS S3)
    │                          │
    │  ┌───────────────────────┴──────────────────────┐
    │  │ Frozen-Node-01    Frozen-Node-02              │
    │  │ (On-Demand Cache) (On-Demand Cache)           │
    │  └───────────────────────────────────────────────┘
    │
    └───────────────────────────────────────────────────┘
```

**Quorum Constraint**: A minimum of **3 master-eligible nodes** distributed across 3 Availability Zones prevents split-brain scenarios.

---

### 5. Document Size Benchmarks

Based on typical enterprise security log patterns, post-compression event sizes are:

| Log Source Type | Avg. Compressed Size | Raw EPS Range |
|---|---|---|
| **Syslog / Basic Network (ICMP)** | 150 – 300 Bytes | 0.1 – 2 EPS/device |
| **Active Directory / Auth Logs** | 500 – 1,200 Bytes | 3 – 29 EPS/device |
| **Firewall / UTM Events** | 400 – 800 Bytes | 10 – 77 EPS/device |
| **Windows Server Event Logs** | 600 – 900 Bytes | 1 – 30 EPS/device |
| **Linux syslog / auditd** | 300 – 700 Bytes | 0.5 – 15 EPS/device |
| **Endpoint EDR (ECS process lineage)** | 1,000 – 1,500 Bytes | Variable |
| **XDR / Third-Party Alerts** | 800 – 2,000 Bytes | Variable |

---

## 🇫🇷 SECTION FRANÇAISE

### 1. Vue d'Ensemble

RaiseGuard MDR 360° est propulsé par l'**Elastic Stack** — la principale plateforme open-core de l'industrie pour la gestion des informations de sécurité. La pile se compose de quatre composants principaux qui travaillent ensemble pour fournir l'ingestion, le stockage, la détection et la visualisation.

---

### 2. Composants Principaux

#### 2.1 Elasticsearch — Le Moteur de Recherche & Stockage

Elasticsearch est un **moteur de recherche et d'analytique distribué et RESTful** construit sur Apache Lucene. Dans le contexte de RaiseGuard MDR 360°, il sert de :

- **Stockage principal des logs** : Stocke tous les événements ingérés en documents JSON structurés
- **Moteur de détection** : Exécute les règles de détection SIEM en requêtes continues
- **Contrôleur ILM** : Gère le déplacement automatique des données à travers les niveaux de stockage
- **Plateforme ML** : Exécute des tâches de détection d'anomalies et de ML non supervisé

**Modèle de Licence** :

Les licences Elasticsearch auto-gérées sont mesurées en **ERUs (Elastic Resource Units)** :
- 1 ERU = 64 Go de RAM allouée aux nœuds Elasticsearch, Kibana, Logstash et Fleet
- Le nombre d'ERU est arrondi au supérieur : `ERUs = ⌈RAM totale Go ÷ 64⌉`

#### 2.2 Elastic Agent & Fleet Server — La Couche de Collecte

**Elastic Agent** est le framework unifié de collecte de données qui remplace plusieurs agents Beats :

| Capacité | Description |
|---|---|
| **Intégration Endpoint** | Collecte événements OS, logs de sécurité, métriques de performance |
| **Elastic Defend** | Protection endpoint et surveillance comportementale |
| **Intégrations Réseau** | Traite Syslog, SNMP, flux API des équipements réseau |
| **Intégrations Cloud** | Se connecte aux APIs de logs AWS/Azure/GCP |
| **Géré par Fleet** | Déploiement central des politiques depuis Kibana |

---

### 3. Spécifications du Dimensionnement

#### 3.1 Constantes de Dimensionnement du Stockage

| Niveau | Ratio RAM:Disque | Stockage Max par Nœud 64 Go |
|---|---|---|
| **Hot** (NVMe SSD) | 1:30 | 1,9 To |
| **Warm** (SATA SSD/HDD) | 1:80 | 5,1 To |
| **Cold** (Object + Cache) | 1:100 | 6,4 To |
| **Frozen** (Object + À la Demande) | 1:160 | 10,2 To |

#### 3.2 Benchmarks de Taille des Documents

| Type de Source de Log | Taille Compressée Moy. | Plage EPS Bruts |
|---|---|---|
| **Syslog / Réseau Basique** | 150 – 300 octets | 0,1 – 2 EPS/appareil |
| **Active Directory / Logs Auth** | 500 – 1 200 octets | 3 – 29 EPS/appareil |
| **Événements Pare-feu / UTM** | 400 – 800 octets | 10 – 77 EPS/appareil |
| **Journaux Windows Server** | 600 – 900 octets | 1 – 30 EPS/appareil |
| **Linux syslog / auditd** | 300 – 700 octets | 0,5 – 15 EPS/appareil |
| **Endpoint EDR (lignage processus ECS)** | 1 000 – 1 500 octets | Variable |
| **XDR / Alertes Tiers** | 800 – 2 000 octets | Variable |

---

### 4. Architecture de Haute Disponibilité

Les déploiements RaiseGuard en production suivent l'**architecture quorum à 3 zones** :

- Minimum **3 nœuds master éligibles** répartis sur 3 Zones de Disponibilité
- Cette architecture évite les problèmes de split-brain
- Les nœuds Hot assurent l'écriture et la recherche lourde (minimum : 16-32 vCPU, 64 Go RAM, 2-6 To NVMe)
- Les nœuds Frozen servent de cache intelligent pour les snapshots (minimum : 8 vCPU, 64 Go RAM, 6-20+ To SSD)

---

*Document Version 2.0 | Elastic Stack Technical Foundation | © RaiseGuard — Confidential*
