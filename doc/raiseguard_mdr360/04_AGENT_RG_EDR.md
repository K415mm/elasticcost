---
title: "RG-EDR Agent — Endpoint Detection & Response"
subtitle: "Agent de Détection et Réponse sur les Endpoints"
agent_code: "RG-EDR"
version: "2.0"
language: "EN / FR"
---

<div align="center">

# 🔒 RG-EDR Agent
## Endpoint Detection & Response (Powered by Elastic Defend)
## Détection et Réponse sur les Endpoints (via Elastic Defend)

> *"Stop threats at the endpoint — before they spread."*
> *« Arrêter les menaces à l'endpoint — avant qu'elles ne se propagent. »*

</div>

---

## 🇬🇧 ENGLISH SECTION

### 1. Agent Overview

The **RG-EDR Agent** is the endpoint protection layer of the RaiseGuard MDR 360° platform. It provides **deep behavioral visibility and active response capabilities** directly on every endpoint device — Windows servers, Linux servers, and workstations — by deploying **Elastic Defend** as its core engine.

Unlike traditional antivirus or basic EDR point solutions, the RG-EDR Agent operates through the same **Elastic Agent** framework used across the RaiseGuard platform, ensuring that endpoint telemetry feeds directly into the centralized SIEM pipeline alongside network and server logs. This creates a unified threat timeline that no siloed endpoint tool can achieve.

The RG-EDR Agent covers the endpoint lifecycle:
- **Before an attack**: Prevention rules block known malware signatures and behavioral patterns
- **During an attack**: Real-time process monitoring and behavioral analysis detect zero-day attacks
- **After an attack**: Memory forensics, process tree analysis, and automated isolation enable rapid response

---

### 2. Core Capabilities

```
┌────────────────────────────────────────────────────────────────┐
│                   RG-EDR Core Functions                         │
├─────────────────────────────────────────────────────────────────┤
│  🛡️  PREVENTION       Malware & exploit blocking (pre-execution)│
│  👁️  BEHAVIORAL        Process tree & memory analysis           │
│  🔴 DETECTION         Zero-day & fileless attack detection      │
│  📦 FORENSICS         Full event timeline reconstruction         │
│  🔇 ISOLATION         Automated or manual host quarantine       │
│  🔄 ROLLBACK          Ransomware rollback & file recovery       │
└────────────────────────────────────────────────────────────────┘
```

#### 2.1 Prevention Engine

The RG-EDR prevention layer operates at multiple levels to stop threats before they execute:

| Protection Layer | Technology | What It Stops |
|---|---|---|
| **Signature Detection** | Malware hash/signature database | Known malware families & variants |
| **Exploit Prevention** | Memory page guard + shellcode detection | Buffer overflows, heap spray, UAF |
| **Ransomware Prevention** | File entropy monitoring + shadow copy protection | Ransomware encryption attempts |
| **Malicious Behavior Rules** | YARA rules + process behavior heuristics | Credential dumping, process injection |
| **Script & Macro Control** | PowerShell, VBScript, macro policy enforcement | Living-off-the-land (LotL) attacks |

#### 2.2 Behavioral Detection & Analysis

Beyond prevention, the RG-EDR Agent performs **continuous behavioral monitoring**:

- **Process Lineage Tracking**: Records every process parent-child relationship to detect unusual spawn chains (e.g., Word spawning PowerShell)
- **Network Connection Monitoring**: Captures all TCP/UDP connections from each process, detecting C2 communication patterns
- **File System Monitoring**: Tracks file creation, modification, and deletion events — critical for detecting ransomware and data theft
- **Registry Monitoring (Windows)**: Watches for persistence mechanisms written to startup registry keys
- **Memory Scanning**: Performs periodic in-memory scanning to detect fileless malware and injected shellcode

#### 2.3 Forensic Capabilities

When an incident is detected, the RG-EDR Agent provides a **complete forensic timeline**:

```
ALERT TRIGGERED
    │
    ▼
Full Process Tree Reconstruction
    │
    ├── Parent Process (e.g., explorer.exe)
    ├── Malicious Process (e.g., powershell.exe)
    │   ├── Network connections made
    │   ├── Files created/modified/deleted
    │   ├── Registry keys written
    │   └── Child processes spawned
    │
    ▼
Memory Dump (if critical)
    │
    ▼
IOC Extraction → Shared to Threat Intel Feed
```

#### 2.4 Active Response Actions

The RG-EDR Agent supports **remote response actions** from the Kibana console or SOC analyst workstation:

| Response Action | Description | Requires Analyst Approval |
|---|---|---|
| **Host Isolation** | Cut all network connections except SOC tunnel | P1: Auto / P2+: Manual |
| **Process Kill** | Terminate a malicious running process | Manual |
| **File Quarantine** | Move suspicious file to encrypted quarantine | Manual |
| **Memory Dump** | Capture process memory for offline forensics | Manual |
| **Script Execution** | Run response scripts (remediation, cleanup) | L3 Analyst only |
| **Agent Reinstall** | Force reinstall endpoint agent remotely | SOC Engineer only |

---

### 3. Technical Specifications

| Parameter | Specification |
|---|---|
| **Endpoint Engine** | Elastic Defend (formerly Endgame) |
| **Supported OS** | Windows 10/11, Windows Server 2016+, RHEL/Ubuntu/CentOS Linux |
| **Agent Framework** | Elastic Agent (Fleet-managed) — unified with RG-SIEM agent |
| **Avg. EPS Output** | 1 – 30 EPS/device (Windows Servers), 0.5 – 15 EPS/device (Linux) |
| **Avg. Event Size** | 800 – 1,500 Bytes (ECS-mapped with process lineage) |
| **Prevention Modes** | Detect / Prevent (configurable per policy group) |
| **Malware Models** | On-device ML malware classification model |
| **Anti-Tamper** | Agent self-protection against process kill / uninstall |
| **Communication** | Fleet Server (TLS 1.3), bidirectional policy push & response |
| **Dashboard** | Kibana Endpoint Security + Security Solution |
| **Data Retention** | Follows ILM scenario — same Hot/Warm/Cold/Frozen pipeline |

---

### 4. Supported Asset Types

The RG-EDR Agent is the **primary agent for endpoint devices**:

| Asset Category | RG-EDR Role | Key Detections |
|---|---|---|
| **Windows Servers** | Deep process & file monitoring | Lateral movement, credential theft, ransomware |
| **Linux Servers** | auditd enhancement + behavioral analysis | Web shell detection, reverse shell, privilege escalation |
| **Windows Workstations** | Full endpoint protection + response | Phishing payload execution, USB threats, browser exploits |
| **Linux Workstations** | Behavioral monitoring + network analysis | Insider threat, shadow IT, unauthorized connections |

> **Note on Third-Party EDR**: If your organization already operates a third-party EDR platform (SentinelOne, Microsoft Defender for Endpoint, CrowdStrike Falcon), its alerts are ingested via the **RG-MDR Agent** pipeline rather than deploying Elastic Defend. The RG-EDR Agent is used when Elastic Defend is the primary endpoint protection platform.

---

### 5. Elastic Defend vs. Third-Party EDR

| Feature | Elastic Defend (RG-EDR) | Third-Party EDR via RG-MDR |
|---|---|---|
| Native Elastic Integration | ✅ Zero-copy, unified timeline | ⚠️ API-based alert forwarding only |
| Alert Fidelity | ✅ Full telemetry (process, file, network, memory) | ⚠️ Alert-level only (no raw telemetry) |
| Response Actions from Kibana | ✅ Full remote response | ❌ Must use third-party console |
| Cost | ✅ Included in Elastic license | ⚠️ Third-party license cost (client-side) |
| Deployment Complexity | ✅ Fleet-managed (same agent) | ⚠️ Separate deployment |
| Best For | Greenfield deployments | Existing EDR investments |

---

### 6. Why Choose RG-EDR?

> ✅ **Deepest endpoint visibility** — process trees, memory, network, file, and registry events in a single unified timeline.
>
> ✅ **Same agent as RG-SIEM** — Elastic Agent framework eliminates double-deployment complexity.
>
> ✅ **Active response capabilities** — isolate, kill, quarantine, and remediate directly from the SOC console.
>
> ✅ **Ransomware rollback** — unique capability to recover encrypted files without paying ransom.
>
> ✅ **On-device ML models** — works offline without needing cloud connectivity for malware detection.

---

## 🇫🇷 SECTION FRANÇAISE

### 1. Présentation de l'Agent

L'**Agent RG-EDR** est la couche de protection des endpoints de la plateforme RaiseGuard MDR 360°. Il fournit une **visibilité comportementale approfondie et des capacités de réponse active** directement sur chaque endpoint — serveurs Windows, serveurs Linux et postes de travail — en déployant **Elastic Defend** comme moteur principal.

Contrairement aux antivirus traditionnels ou aux solutions EDR basiques cloisonnées, l'Agent RG-EDR opère via le même framework **Elastic Agent** utilisé dans toute la plateforme RaiseGuard, garantissant que la télémétrie des endpoints alimente directement le pipeline SIEM centralisé aux côtés des logs réseau et serveur.

---

### 2. Capacités Principales

#### 2.1 Moteur de Prévention

| Couche de Protection | Technologie | Ce qu'elle arrête |
|---|---|---|
| **Détection par Signature** | Base de signatures malware | Familles de malware connues |
| **Prévention d'Exploit** | Memory page guard + détection shellcode | Buffer overflows, heap spray |
| **Prévention Ransomware** | Surveillance d'entropie de fichiers | Tentatives de chiffrement ransomware |
| **Règles de Comportement Malveillant** | Règles YARA + heuristiques comportementales | Dumping de credentials, injection de processus |
| **Contrôle Script & Macro** | Application des politiques PowerShell, VBScript | Attaques Living-off-the-Land (LotL) |

#### 2.2 Actions de Réponse Active

| Action de Réponse | Description | Approbation Analyste Requise |
|---|---|---|
| **Isolation d'Hôte** | Couper toutes les connexions réseau sauf le tunnel SOC | P1 : Auto / P2+ : Manuel |
| **Arrêt de Processus** | Terminer un processus malveillant en cours | Manuel |
| **Mise en Quarantaine de Fichier** | Déplacer un fichier suspect en quarantaine chiffrée | Manuel |
| **Dump Mémoire** | Capturer la mémoire du processus pour forensique hors ligne | Manuel |
| **Exécution de Script** | Lancer des scripts de réponse (remédiation, nettoyage) | Analyste L3 uniquement |

---

### 3. Spécifications Techniques

| Paramètre | Spécification |
|---|---|
| **Moteur Endpoint** | Elastic Defend (anciennement Endgame) |
| **OS Supportés** | Windows 10/11, Windows Server 2016+, RHEL/Ubuntu/CentOS Linux |
| **Framework Agent** | Elastic Agent (Fleet-managed) — unifié avec l'agent RG-SIEM |
| **EPS Moyen** | 1 – 30 EPS/appareil (Serveurs Windows), 0,5 – 15 EPS/appareil (Linux) |
| **Taille Moyenne d'Événement** | 800 – 1 500 octets (ECS avec lignage de processus) |
| **Modes de Prévention** | Détecter / Prévenir (configurable par groupe de politique) |
| **Modèles ML** | Modèle de classification malware ML sur l'appareil |
| **Anti-Sabotage** | Auto-protection de l'agent contre kill/désinstallation |
| **Communication** | Fleet Server (TLS 1.3), politique bidirectionnelle push & réponse |

---

### 4. Types d'Actifs Supportés

L'Agent RG-EDR est l'**agent principal pour les appareils endpoint** :

| Catégorie d'Actif | Rôle de RG-EDR | Détections Clés |
|---|---|---|
| **Serveurs Windows** | Surveillance approfondie processus & fichiers | Mouvement latéral, vol de credentials, ransomware |
| **Serveurs Linux** | Amélioration auditd + analyse comportementale | Détection web shell, reverse shell, élévation de privilèges |
| **Postes Windows** | Protection endpoint complète + réponse | Exécution payload phishing, menaces USB, exploits navigateur |
| **Postes Linux** | Surveillance comportementale + analyse réseau | Menace interne, Shadow IT, connexions non autorisées |

---

### 5. Pourquoi Choisir RG-EDR ?

> ✅ **Visibilité endpoint la plus profonde** — arbres de processus, mémoire, réseau, fichiers et registre dans une timeline unifiée.
>
> ✅ **Même agent que RG-SIEM** — le framework Elastic Agent élimine la complexité du double déploiement.
>
> ✅ **Capacités de réponse active** — isoler, tuer, mettre en quarantaine et remédier directement depuis la console SOC.
>
> ✅ **Rollback ransomware** — capacité unique de récupérer des fichiers chiffrés sans payer de rançon.
>
> ✅ **Modèles ML sur l'appareil** — fonctionne hors ligne sans connectivité cloud pour la détection de malware.

---

*Document Version 2.0 | RG-EDR Agent Specification | © RaiseGuard — Confidential*
