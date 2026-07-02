---
title: "Agent Selection Guide by Asset Type"
subtitle: "Guide de Sélection des Agents par Type d'Actif"
version: "2.0"
language: "EN / FR"
---

<div align="center">

# 🗺️ Agent Selection Guide
## Which RaiseGuard Agent for Which Asset?
## Quel Agent RaiseGuard pour Quel Actif ?

> *"Right protection for every asset. No gaps, no waste."*
> *« La bonne protection pour chaque actif. Aucun angle mort, aucun gaspillage. »*

</div>

---

## 🇬🇧 ENGLISH SECTION

### 1. Quick Reference Matrix

This matrix provides the definitive mapping between each asset type in your IT infrastructure and the recommended RaiseGuard agents. Some assets require multiple agents for complete coverage.

| Asset Type | 🔭 RG-SIEM | 🔍 RG-MDR | 🔒 RG-EDR | Coverage Level |
|---|:---:|:---:|:---:|---|
| **Active Directory / Domain Controllers** | ✅ | ❌ | ❌ | Identity correlation & auth monitoring |
| **FortiGate Firewalls** | ✅ | ✅ | ❌ | Network boundary + human threat analysis |
| **Network Switches** | ✅ | ✅ | ❌ | Traffic telemetry + lateral move detection |
| **Windows Servers** | ✅ | ❌ | ✅ | OS logs + deep endpoint behavioral defense |
| **Linux Servers** | ✅ | ❌ | ✅ | Syslog/auditd + endpoint behavioral defense |
| **Windows Workstations** | ❌ | ❌ | ✅ | Full endpoint protection (EDR only) |
| **Linux Workstations** | ❌ | ❌ | ✅ | Behavioral monitoring (EDR only) |
| **Third-Party EDR (SentinelOne, etc.)** | ❌ | ✅ | ❌ | XDR alert ingestion + expert analysis |
| **Cloud Platforms (AWS/Azure/GCP)** | ✅ | ✅ | ❌ | Cloud log collection + expert cloud IR |
| **Web Application Firewalls (WAF)** | ✅ | ✅ | ❌ | HTTP attack detection + expert response |
| **Email Security Gateways** | ✅ | ❌ | ❌ | Phishing & spam event correlation |
| **VPN Concentrators** | ✅ | ✅ | ❌ | VPN auth monitoring + expert analysis |

---

### 2. Per-Asset Deep Dive

#### 🖥️ Active Directory / Domain Controllers

**Agents: RG-SIEM only**

Active Directory is the **crown jewel** of any enterprise network. Compromising AD means full domain control. The RG-SIEM agent collects and correlates every authentication, privilege change, and group modification event.

```
Why RG-SIEM Only?
├── AD generates log data at the infrastructure level (not endpoint behavior)
├── All events are log-based: Event IDs 4624, 4625, 4768, 4720, 4732...
├── Correlation across multiple DCs requires SIEM aggregation
└── EDR on DCs creates performance risk (critical role separation)
```

**Key Detections from RG-SIEM on AD**:

| Detection | MITRE ATT&CK Technique | Description |
|---|---|---|
| Brute Force | T1110 | >10 failed logins in 5 min from same source |
| Pass-the-Hash | T1550.002 | NTLM authentication anomalies |
| Kerberoasting | T1558.003 | Unusual Kerberos TGS requests for service accounts |
| Golden Ticket | T1558.001 | TGT tickets with abnormal lifetimes |
| Admin Group Change | T1098 | New members added to Domain Admins / Enterprise Admins |
| Lateral Movement | T1021 | Unusual authentication flows between hosts |

**Recommended Sizing**: Active Directory generates **2.89 – 28.94 EPS** per domain controller, with an average event size of **1,200 bytes**.

---

#### 🔥 FortiGate Firewalls

**Agents: RG-SIEM + RG-MDR**

Firewalls are the **primary attack surface** for initial access. They see all inbound and outbound traffic, making them essential for perimeter threat detection. The combination of RG-SIEM (for log normalization and correlation) and RG-MDR (for expert human analysis of complex attack patterns) provides complete coverage.

```
Why RG-SIEM + RG-MDR?
├── SIEM: Normalizes FortiGate traffic logs, UTM events, VPN auth
├── SIEM: Correlates firewall events with other infrastructure logs
├── MDR: Human analysts investigate suspected exfiltration patterns
├── MDR: Security engineers optimize firewall rule sets
└── MDR: Proactive hunting for firewall-bypass techniques
```

**Key Detections**:

| Detection | Agent | Description |
|---|---|---|
| Port Scanning | RG-SIEM | Detect reconnaissance from external IPs |
| Geo-Blocked Access | RG-SIEM | Traffic from sanctioned or unexpected countries |
| Data Exfiltration | RG-SIEM + RG-MDR | Unusual outbound data volumes on non-standard ports |
| Firewall Rule Bypass | RG-MDR | Expert analysis of protocol evasion techniques |
| VPN Credential Stuffing | RG-SIEM | Multiple failed VPN auth from same source IP |
| C2 Communication | RG-SIEM + RG-MDR | Beaconing patterns to known C2 infrastructure |

**Recommended Sizing**: FortiGate generates **9.65 – 77.16 EPS** per firewall, with an average event size of **600 bytes**.

---

#### 🌐 Network Switches

**Agents: RG-SIEM + RG-MDR**

Network switches provide critical **east-west traffic visibility** — the movement of traffic inside your network between devices. Lateral movement is the most dangerous post-exploitation technique, and switch telemetry is key to detecting it.

```
Why RG-SIEM + RG-MDR?
├── SIEM: Collects SNMP traps, syslog from all switch infrastructure
├── SIEM: Correlates ARP tables, MAC address changes, VLAN events
├── MDR: Human analysts trace unusual east-west communication paths
└── MDR: Security engineers review switch ACLs and port security
```

**Key Detections**:

| Detection | Agent | Description |
|---|---|---|
| ARP Spoofing / MITM | RG-SIEM | Unexpected ARP replies or MAC changes |
| VLAN Hopping | RG-SIEM | 802.1q double tagging or trunking abuse |
| Lateral Movement | RG-SIEM + RG-MDR | Unusual traffic paths between internal segments |
| Rogue Device Connection | RG-SIEM | New MAC address appearing on monitored ports |
| Spanning Tree Manipulation | RG-SIEM | STP topology changes indicating attack |

**Recommended Sizing**: Network switches generate **0.1 – 15 EPS** per device, with an average event size of **250 bytes**.

---

#### 🖥️ Windows Servers

**Agents: RG-SIEM + RG-EDR**

Windows servers are the **primary target** for ransomware, credential theft, and data exfiltration. The combination of RG-SIEM (for centralized event log aggregation) and RG-EDR (for deep behavioral monitoring and active response) provides the most complete server protection available.

```
Why RG-SIEM + RG-EDR?
├── SIEM: Collects Windows Event Logs (Security, System, Application)
├── SIEM: Correlates server events with network and AD activity
├── EDR: Monitors all processes, files, network connections in real time
├── EDR: Detects fileless attacks, memory injection, and LotL techniques
├── EDR: Enables host isolation and process termination on detection
└── EDR: Ransomware rollback capability for business continuity
```

**Key Detections**:

| Detection | Agent | Description |
|---|---|---|
| Credential Dumping (LSASS) | RG-EDR | Process access to lsass.exe memory |
| PowerShell Attack | RG-SIEM + RG-EDR | Encoded/obfuscated PowerShell execution |
| Ransomware Encryption | RG-EDR | Mass file modifications with entropy change |
| Lateral Movement via WMI | RG-SIEM + RG-EDR | WMI remote execution correlation |
| Scheduled Task Persistence | RG-EDR | New scheduled tasks created via schtasks.exe |
| Pass-the-Hash | RG-SIEM + RG-EDR | NTLM auth with stolen credentials |

**Recommended Sizing**: Windows servers generate **1 – 30 EPS** per server, with an average event size of **800 bytes**.

---

#### 🐧 Linux Servers

**Agents: RG-SIEM + RG-EDR**

Linux servers underpin web services, databases, and CI/CD infrastructure. They are targeted for **web shell deployment, reverse shell execution, and cryptomining**. The RG-SIEM agent handles syslog collection, while RG-EDR provides auditd-level behavioral monitoring.

```
Why RG-SIEM + RG-EDR?
├── SIEM: Collects syslog, auth.log, kern.log, auditd events
├── SIEM: Detects SSH brute force, sudo abuse, cron changes
├── EDR: Monitors all process forks, exec calls, file writes
├── EDR: Detects reverse shells, privilege escalation, web shells
└── EDR: Container escape detection (Docker/Kubernetes environments)
```

**Key Detections**:

| Detection | Agent | Description |
|---|---|---|
| Web Shell | RG-SIEM + RG-EDR | Web server spawning shell process |
| SSH Brute Force | RG-SIEM | >10 failed auth to SSH in 60 seconds |
| Privilege Escalation (sudo) | RG-SIEM + RG-EDR | Unusual sudo usage or SUID binary abuse |
| Cron Persistence | RG-EDR | New cron jobs added by non-admin user |
| Reverse Shell | RG-EDR | Bash/netcat establishing outbound shell |
| Cryptominer | RG-EDR | High CPU process with known miner signatures |

**Recommended Sizing**: Linux servers generate **0.5 – 15 EPS** per server, with an average event size of **500 bytes**.

---

#### 💻 Workstations (Windows / Linux)

**Agent: RG-EDR only**

Workstations are the **primary phishing target**. Malicious email attachments, browser exploits, and USB-delivered malware typically enter organizations through user workstations. The RG-EDR agent provides full endpoint protection without the overhead of centralized SIEM log collection for endpoint events (which are handled via the EDR telemetry pipeline directly).

```
Why RG-EDR Only?
├── Workstation log volume at SIEM scale is cost-prohibitive
├── EDR captures all relevant endpoint events natively
├── SIEM receives only escalated alerts (not raw events) from EDR
└── EDR direct response (isolation) is faster than SIEM-escalation
```

---

#### 📦 Third-Party EDR Integrations

**Agent: RG-MDR only**

For clients that have invested in third-party EDR platforms (SentinelOne, Microsoft Defender for Endpoint, CrowdStrike Falcon, Trend Micro Vision One), the RG-MDR agent provides **expert analysis of the alerts generated by those platforms** rather than replacing them.

```
Integration Model:
Third-Party EDR Alerts → API → Logstash → Elasticsearch
                                               │
                                        RG-MDR Analyst
                                        (expert triage)
```

This approach **maximizes the value of existing security investments** while adding the human intelligence layer that most organizations lack internally.

---

### 3. Asset Type Summary Table

| Asset | SIEM Agent | MDR Agent | EDR Agent | Primary Risk Category |
|---|:---:|:---:|:---:|---|
| Active Directory | ✅ | ❌ | ❌ | Identity & Privilege Abuse |
| FortiGate Firewall | ✅ | ✅ | ❌ | Perimeter Breach & Exfiltration |
| Network Switch | ✅ | ✅ | ❌ | Lateral Movement & East-West Threats |
| Windows Server | ✅ | ❌ | ✅ | Ransomware & Credential Theft |
| Linux Server | ✅ | ❌ | ✅ | Web Shells & Privilege Escalation |
| Workstation (Win/Lin) | ❌ | ❌ | ✅ | Phishing & Malware Delivery |
| Third-Party EDR | ❌ | ✅ | ❌ | Alert Enrichment & Expert Response |
| Cloud Platform | ✅ | ✅ | ❌ | Misconfiguration & API Abuse |

---

## 🇫🇷 SECTION FRANÇAISE

### 1. Matrice de Référence Rapide

| Type d'Actif | 🔭 RG-SIEM | 🔍 RG-MDR | 🔒 RG-EDR | Niveau de Couverture |
|---|:---:|:---:|:---:|---|
| **Active Directory / Contrôleurs de Domaine** | ✅ | ❌ | ❌ | Corrélation identité & surveillance auth |
| **Pare-feux FortiGate** | ✅ | ✅ | ❌ | Frontière réseau + analyse humaine |
| **Commutateurs Réseau** | ✅ | ✅ | ❌ | Télémétrie trafic + détection mouvement lat. |
| **Serveurs Windows** | ✅ | ❌ | ✅ | Logs OS + défense comportementale endpoint |
| **Serveurs Linux** | ✅ | ❌ | ✅ | Syslog/auditd + défense comportementale |
| **Postes Windows** | ❌ | ❌ | ✅ | Protection endpoint complète (EDR seul) |
| **Postes Linux** | ❌ | ❌ | ✅ | Surveillance comportementale (EDR seul) |
| **EDR Tiers (SentinelOne, etc.)** | ❌ | ✅ | ❌ | Ingestion alertes XDR + analyse experte |
| **Plateformes Cloud** | ✅ | ✅ | ❌ | Collecte logs cloud + IR cloud experte |
| **WAF** | ✅ | ✅ | ❌ | Détection attaques HTTP + réponse experte |

---

### 2. Justification par Type d'Actif

#### 🖥️ Active Directory / Contrôleurs de Domaine — RG-SIEM uniquement

L'Active Directory est le **joyau de la couronne** de tout réseau d'entreprise. L'agent RG-SIEM collecte et corrèle chaque événement d'authentification, de changement de privilège et de modification de groupe.

**Détections Clés depuis RG-SIEM sur AD** :

| Détection | Technique MITRE ATT&CK | Description |
|---|---|---|
| Force Brute | T1110 | >10 échecs de connexion en 5 min depuis la même source |
| Pass-the-Hash | T1550.002 | Anomalies d'authentification NTLM |
| Kerberoasting | T1558.003 | Requêtes TGS Kerberos inhabituelles pour des comptes de service |
| Golden Ticket | T1558.001 | Tickets TGT avec des durées de vie anormales |
| Changement Groupe Admin | T1098 | Nouveaux membres ajoutés aux Admins Domaine |

---

#### 🔥 Pare-feux FortiGate — RG-SIEM + RG-MDR

Les pare-feux constituent la **surface d'attaque principale** pour l'accès initial. La combinaison RG-SIEM (normalisation des logs) et RG-MDR (analyse humaine des patterns d'attaque complexes) fournit une couverture complète.

#### 🌐 Commutateurs Réseau — RG-SIEM + RG-MDR

Les commutateurs réseau fournissent une visibilité **est-ouest** critique — le mouvement du trafic à l'intérieur de votre réseau entre les appareils. Le mouvement latéral est la technique post-exploitation la plus dangereuse.

#### 🖥️ Serveurs Windows — RG-SIEM + RG-EDR

Les serveurs Windows sont la **cible principale** des ransomwares, du vol de credentials et de l'exfiltration de données. La combinaison RG-SIEM et RG-EDR fournit la protection serveur la plus complète disponible.

#### 🐧 Serveurs Linux — RG-SIEM + RG-EDR

Les serveurs Linux sous-tendent les services web, les bases de données et l'infrastructure CI/CD. Ils sont ciblés pour le déploiement de web shells, l'exécution de reverse shells et le cryptominage.

---

### 3. Tableau Récapitulatif des Types d'Actifs

| Actif | Agent SIEM | Agent MDR | Agent EDR | Catégorie de Risque Principale |
|---|:---:|:---:|:---:|---|
| Active Directory | ✅ | ❌ | ❌ | Abus d'Identité & Privilèges |
| Pare-feu FortiGate | ✅ | ✅ | ❌ | Intrusion Périmètre & Exfiltration |
| Commutateur Réseau | ✅ | ✅ | ❌ | Mouvement Latéral & Menaces Est-Ouest |
| Serveur Windows | ✅ | ❌ | ✅ | Ransomware & Vol de Credentials |
| Serveur Linux | ✅ | ❌ | ✅ | Web Shells & Élévation de Privilèges |
| Poste (Win/Lin) | ❌ | ❌ | ✅ | Phishing & Livraison de Malware |
| EDR Tiers | ❌ | ✅ | ❌ | Enrichissement Alertes & Réponse Experte |
| Plateforme Cloud | ✅ | ✅ | ❌ | Mauvaise Config & Abus API |

---

*Document Version 2.0 | Agent Selection Guide | © RaiseGuard — Confidential*
