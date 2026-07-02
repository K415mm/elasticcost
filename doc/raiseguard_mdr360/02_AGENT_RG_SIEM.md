---
title: "RG-SIEM Agent — Unified Security Monitoring & Correlation"
subtitle: "Agent de Surveillance Unifiée & Corrélation de Sécurité"
agent_code: "RG-SIEM"
version: "2.0"
language: "EN / FR"
---

<div align="center">

# 🔭 RG-SIEM Agent
## Unified Security Monitoring & Correlation
## Surveillance de Sécurité Unifiée & Corrélation

> *"See everything. Miss nothing."*
> *« Tout voir. Ne rien manquer. »*

</div>

---

## 🇬🇧 ENGLISH SECTION

### 1. Agent Overview

The **RG-SIEM Agent** is the foundational intelligence layer of the RaiseGuard MDR 360° platform. It acts as the central nervous system of your security program — collecting logs from every device in your environment, parsing them into a normalized security format (ECS — Elastic Common Schema), correlating events across data sources in real time, and surfacing actionable alerts to your SOC analysts.

This agent is **not** a simple log forwarder. It is a full-featured Security Information and Event Management (SIEM) capability delivered as a managed service, powered by the **Elastic Stack** (Logstash + Elasticsearch + Kibana) and the **Elastic Agent with Fleet Server**.

---

### 2. Core Capabilities

```
┌────────────────────────────────────────────────────────────────┐
│                    RG-SIEM Core Functions                       │
├──────────────────────────────────────────────────────────────── ┤
│  📥 COLLECT        Log ingestion from all sources               │
│  🔄 NORMALIZE      Map to Elastic Common Schema (ECS)           │
│  🔗 CORRELATE      Cross-source event detection                 │
│  🚨 ALERT          Real-time threshold & ML-based rules         │
│  📊 VISUALIZE      Kibana Security dashboards                   │
│  📋 COMPLY         Audit-ready log retention (ILM)              │
└────────────────────────────────────────────────────────────────┘
```

#### 2.1 Log Collection & Ingestion

The RG-SIEM Agent deploys **Elastic Agent** instances to each monitored device or network node. These agents:

- Collect system logs, application events, and security telemetry
- Ship data securely via **Fleet Server** over TLS-encrypted channels
- Support **agentless integrations** via Syslog, SNMP, API, and S3 for devices where agent installation is not possible (e.g., firewalls, network switches, cloud platforms)

#### 2.2 Event Normalization (ECS)

All collected events are parsed and enriched through **Logstash ingest pipelines** that:

- Map raw vendor log formats to the **Elastic Common Schema (ECS)**
- Enrich events with GeoIP data, threat intelligence feeds, and asset context
- Prune redundant raw fields to optimize storage costs (up to **30% reduction**)

#### 2.3 Detection Rules & Correlation

The RG-SIEM engine runs a continuous library of detection rules:

| Rule Category | Description | Example |
|---|---|---|
| **Threshold Rules** | Alert when event count exceeds a baseline | >10 failed logins in 5 min |
| **Sequence Rules** | Detect ordered chains of events | Recon → Lateral Move → Exfil |
| **ML Anomaly Detection** | Identify statistical deviations | Unusual login time or location |
| **Indicator Match** | Cross-reference known bad IPs/hashes/domains | IOC from threat intel feeds |
| **Event Correlation** | Multi-source timeline reconstruction | AD + Firewall + Server correlation |

#### 2.4 Compliance & Retention

The RG-SIEM Agent manages the full **Index Lifecycle Management (ILM)** pipeline, automatically moving data through storage tiers (Hot → Warm → Cold → Frozen) based on the chosen retention scenario. This ensures:

- **Regulatory compliance** (ISO 27001, SOC 2, NIS2, GDPR, PCI-DSS)
- **Cost optimization** through tiered storage (up to **46% disk savings**)
- **Full-year searchable archives** at Frozen tier pricing

---

### 3. Technical Specifications

| Parameter | Specification |
|---|---|
| **Deployment Model** | Elastic Agent (Fleet-managed) + Logstash |
| **Data Format** | Elastic Common Schema (ECS) v8.x |
| **Ingest Pipeline** | Logstash → Elasticsearch Hot Tier |
| **Supported Protocols** | Syslog (TCP/UDP), Beats, HTTP/S, SNMP, S3, API |
| **Detection Engine** | Kibana SIEM Detection Rules + ML Jobs |
| **Avg. Event Size** | 500 – 1,200 Bytes (post-normalization) |
| **Min EPS Support** | 0.1 EPS (network switches) to 30+ EPS (AD) |
| **Retention Support** | 7 days to 365 days via ILM tiers |
| **High Availability** | 3-node quorum, Primary + 1 Replica minimum |
| **Encryption** | TLS 1.3 in transit, AES-256 at rest |
| **Dashboard** | Kibana Security Solution with SIEM Dashboards |

---

### 4. Supported Asset Types

The RG-SIEM Agent is the **primary agent** for network and server infrastructure:

| Asset Category | Log Source Type | EPS Range | Integration Method |
|---|---|---|---|
| **Active Directory / Domain Controllers** | Identity & authentication events | 3 – 29 EPS/device | Elastic Agent (Winlogbeat) |
| **Windows Servers** | OS event logs, security, system | 1 – 30 EPS/device | Elastic Agent |
| **Linux Servers** | syslog, auth.log, auditd | 0.5 – 15 EPS/device | Elastic Agent |
| **Network Switches** | SNMP traps, syslog | 0.1 – 15 EPS/device | Syslog (agentless) |
| **FortiGate Firewalls** | Traffic, UTM, VPN logs | 10 – 77 EPS/device | Syslog / FortiGate Integration |
| **Cloud Platforms (AWS/Azure/GCP)** | CloudTrail, Activity Logs | Variable | API / S3 Integration |
| **Web Application Firewalls** | HTTP events, attack logs | Variable | Syslog / API |

---

### 5. Delivery & Onboarding Process

```
Week 1-2: Discovery & Design
    ├── Asset inventory review
    ├── Log source mapping
    └── Retention scenario selection

Week 3-4: Deployment
    ├── Fleet Server installation
    ├── Elastic Agent rollout to all sources
    └── Logstash pipeline configuration

Week 5: Tuning & Validation
    ├── Detection rule tuning (reduce false positives)
    ├── Dashboard customization
    └── Analyst handover & training

Week 6+: 24/7 Managed Operations
    ├── Continuous log ingestion
    ├── Alert monitoring & triage
    └── Monthly health & coverage reports
```

---

### 6. Why Choose RG-SIEM?

> ✅ **No SIEM expertise required on your side** — RaiseGuard manages everything from deployment to tuning.
>
> ✅ **Built on Elastic Stack** — industry-leading open-core technology with no vendor lock-in.
>
> ✅ **Scales with your growth** — from 10 devices to 10,000+ with the same agent model.
>
> ✅ **Compliance-ready from day one** — ILM retention meets ISO 27001, NIS2, and PCI-DSS requirements.
>
> ✅ **Transparent pricing** — pay per asset, not per GB ingested.

---

## 🇫🇷 SECTION FRANÇAISE

### 1. Présentation de l'Agent

L'**Agent RG-SIEM** est la couche d'intelligence fondamentale de la plateforme RaiseGuard MDR 360°. Il agit comme le système nerveux central de votre programme de sécurité — collectant les journaux de chaque appareil de votre environnement, les normalisant dans un format de sécurité standardisé (ECS — Elastic Common Schema), corrélant les événements de toutes les sources en temps réel, et remontant des alertes exploitables à vos analystes SOC.

Cet agent n'est **pas** un simple collecteur de logs. C'est une capacité SIEM complète (Security Information and Event Management) livrée en tant que service géré, propulsée par **l'Elastic Stack** (Logstash + Elasticsearch + Kibana) et l'**Elastic Agent avec Fleet Server**.

---

### 2. Capacités Principales

#### 2.1 Collecte et Ingestion des Logs

L'Agent RG-SIEM déploie des instances **Elastic Agent** sur chaque appareil ou nœud réseau surveillé. Ces agents :

- Collectent les journaux système, les événements applicatifs et la télémétrie de sécurité
- Transmettent les données de manière sécurisée via **Fleet Server** sur des canaux chiffrés TLS
- Supportent les **intégrations sans agent** via Syslog, SNMP, API et S3 pour les appareils où l'installation d'agent n'est pas possible (pare-feux, commutateurs réseau, plateformes cloud)

#### 2.2 Normalisation des Événements (ECS)

Tous les événements collectés sont parsés et enrichis via des **pipelines d'ingestion Logstash** qui :

- Mappent les formats de logs bruts des fournisseurs vers **l'Elastic Common Schema (ECS)**
- Enrichissent les événements avec des données GeoIP, des flux de renseignement sur les menaces et le contexte des actifs
- Élagent les champs bruts redondants pour optimiser les coûts de stockage (jusqu'à **30% de réduction**)

#### 2.3 Règles de Détection & Corrélation

Le moteur RG-SIEM exécute une bibliothèque continue de règles de détection :

| Catégorie de Règle | Description | Exemple |
|---|---|---|
| **Règles de Seuil** | Alerte quand le nombre d'événements dépasse une référence | >10 échecs de connexion en 5 min |
| **Règles de Séquence** | Détecte des chaînes ordonnées d'événements | Reconnaissance → Mouvement latéral → Exfiltration |
| **Détection Anomalies ML** | Identifie les déviations statistiques | Heure ou lieu de connexion inhabituel |
| **Correspondance Indicateur** | Croise avec les IPs/hash/domaines malveillants connus | IOC des flux de renseignement |
| **Corrélation d'Événements** | Reconstruction de timeline multi-sources | Corrélation AD + Pare-feu + Serveur |

#### 2.4 Conformité & Rétention

L'Agent RG-SIEM gère le pipeline complet de **Gestion du Cycle de Vie des Index (ILM)**, déplaçant automatiquement les données à travers les niveaux de stockage (Hot → Warm → Cold → Frozen) selon le scénario de rétention choisi. Cela garantit :

- **La conformité réglementaire** (ISO 27001, SOC 2, NIS2, RGPD, PCI-DSS)
- **L'optimisation des coûts** via le stockage hiérarchisé (jusqu'à **46% d'économies disque**)
- **Des archives annuelles consultables** au prix du niveau Frozen

---

### 3. Spécifications Techniques

| Paramètre | Spécification |
|---|---|
| **Modèle de Déploiement** | Elastic Agent (Fleet-managed) + Logstash |
| **Format de Données** | Elastic Common Schema (ECS) v8.x |
| **Pipeline d'Ingestion** | Logstash → Elasticsearch Hot Tier |
| **Protocoles Supportés** | Syslog (TCP/UDP), Beats, HTTP/S, SNMP, S3, API |
| **Moteur de Détection** | Règles SIEM Kibana + Jobs ML |
| **Taille Moyenne d'Événement** | 500 – 1 200 Octets (post-normalisation) |
| **Support EPS Min** | 0,1 EPS (commutateurs réseau) à 30+ EPS (AD) |
| **Support Rétention** | 7 jours à 365 jours via niveaux ILM |
| **Haute Disponibilité** | Quorum 3 nœuds, Primary + 1 Replica minimum |
| **Chiffrement** | TLS 1.3 en transit, AES-256 au repos |
| **Tableau de Bord** | Solution de Sécurité Kibana avec Tableaux SIEM |

---

### 4. Types d'Actifs Supportés

L'Agent RG-SIEM est l'**agent principal** pour l'infrastructure réseau et serveur :

| Catégorie d'Actif | Type de Source de Log | Plage EPS | Méthode d'Intégration |
|---|---|---|---|
| **Active Directory / Contrôleurs de Domaine** | Événements d'identité et d'authentification | 3 – 29 EPS/appareil | Elastic Agent (Winlogbeat) |
| **Serveurs Windows** | Journaux OS, sécurité, système | 1 – 30 EPS/appareil | Elastic Agent |
| **Serveurs Linux** | syslog, auth.log, auditd | 0,5 – 15 EPS/appareil | Elastic Agent |
| **Commutateurs Réseau** | Traps SNMP, syslog | 0,1 – 15 EPS/appareil | Syslog (sans agent) |
| **Pare-feux FortiGate** | Trafic, UTM, journaux VPN | 10 – 77 EPS/appareil | Syslog / Intégration FortiGate |
| **Plateformes Cloud** | CloudTrail, Journaux d'Activité | Variable | API / Intégration S3 |

---

### 5. Pourquoi Choisir RG-SIEM ?

> ✅ **Aucune expertise SIEM requise de votre côté** — RaiseGuard gère tout, du déploiement au réglage fin.
>
> ✅ **Construit sur Elastic Stack** — technologie open-core de référence industrielle sans dépendance fournisseur.
>
> ✅ **Évolue avec votre croissance** — de 10 à plus de 10 000 appareils avec le même modèle d'agent.
>
> ✅ **Prêt pour la conformité dès le premier jour** — la rétention ILM répond aux exigences ISO 27001, NIS2 et PCI-DSS.
>
> ✅ **Tarification transparente** — paiement par actif, non par Go ingéré.

---

*Document Version 2.0 | RG-SIEM Agent Specification | © RaiseGuard — Confidential*
