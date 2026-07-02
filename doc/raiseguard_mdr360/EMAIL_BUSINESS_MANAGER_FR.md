---
type: Email Template / Modèle d'Email
language: Français
destination: Directeur Commercial / Business Manager
objet: "Proposition Commerciale — RaiseGuard MDR 360° | [Nom Client]"
pièces_jointes:
  - inventaire_client_[nom_client].xlsx
  - documentation_raiseguard_mdr360.pdf (optionnel)
---

# ✉️ MODÈLE D'EMAIL — PROPOSITION COMMERCIALE MDR 360°

---

**De :** [Votre Prénom & Nom] — RaiseGuard SOC Team
**À :** [Prénom Nom Business Manager]
**Cc :** [Chef de Projet / Commercial Senior] *(optionnel)*
**Objet :** Proposition Commerciale — RaiseGuard MDR 360° | [Nom du Client] | Offre 2026
**Pièces jointes :** 📎 `inventaire_client_[nom_client].xlsx`

---

## CORPS DU MESSAGE

---

Bonjour [Prénom],

J'espère que vous allez bien. Faisant suite à notre analyse de l'inventaire informatique du client **[Nom du Client]**, je vous transmets ci-dessous la synthèse des **trois propositions tarifaires MDR 360°** que nous avons élaborées, accompagnée du fichier Excel détaillé en pièce jointe.

---

### 🛡️ Rappel rapide — Les 3 agents RaiseGuard MDR 360°

Avant de présenter les offres, voici une description en une ligne de chacun de nos agents :

| Agent | Description synthétique |
|---|---|
| 🔭 **RG-SIEM** | Collecte centralisée des journaux, corrélation temps réel et détection automatisée des menaces sur l'ensemble de l'infrastructure réseau et serveurs. |
| 🔍 **RG-MDR** | Surveillance humaine 24h/24 et 7j/7 par nos analystes SOC dédiés, avec chasse proactive aux menaces, triage des alertes et réponse aux incidents sous SLA contractuel. |
| 🔒 **RG-EDR** | Protection comportementale avancée sur chaque endpoint (serveurs Windows/Linux et postes de travail), avec capacité d'isolation, de remédiation et de rollback ransomware en temps réel. |

> ⚠️ **Note importante** : Les tarifs présentés ci-dessous couvrent **uniquement le coût mensuel des agents MDR 360°** (licences agents + infrastructure Elastic Stack + personnel SOC dédié). D'autres services additionnels (consulting, audits, formations, intégrations spécifiques, etc.) peuvent être ajoutés à cette base tarifaire selon vos besoins commerciaux.

---

### 💼 Les Trois Propositions Tarifaires

#### ➤ Offre 3 — Calibration Optimisée SIEM/MDR sur Serveurs *(Recommandée — Plus compétitive)*

> Répartition intelligente des serveurs entre agents SIEM et MDR selon leur criticité, sans déploiement EDR endpoint.

| Agent | Prix Unitaire / mois | Nombre d'unités | Coût Mensuel |
|---|---:|---:|---:|
| 🔍 RG-MDR | 380 TND | 13 | **4 940 TND** |
| 🔭 RG-SIEM | 270 TND | 11 | **2 970 TND** |
| 🔒 RG-EDR | 80 TND | 0 | 0 TND |
| | | **TOTAL MENSUEL** | **7 910 TND/mois** |

**Coût annuel estimé** : ~94 920 TND/an *(hors frais d'intégration initiale)*

---

#### ➤ Offre 2 — MDR 360° avec Intégration EDR & Commutateurs Core

> Couverture MDR renforcée sur les commutateurs cœur de réseau et intégration des alertes EDR tierces existantes du client, sans déploiement d'agents Elastic Defend sur les endpoints.

| Agent | Prix Unitaire / mois | Nombre d'unités | Coût Mensuel |
|---|---:|---:|---:|
| 🔍 RG-MDR | 380 TND | 18 | **6 840 TND** |
| 🔭 RG-SIEM | 270 TND | 6 | **1 620 TND** |
| 🔒 RG-EDR | 80 TND | 0 | 0 TND |
| | | **TOTAL MENSUEL** | **8 460 TND/mois** |

**Coût annuel estimé** : ~101 520 TND/an *(hors frais d'intégration initiale)*

---

#### ➤ Offre 1 — MDR 360° Couverture Totale (Tous Actifs dans Elastic)

> Couverture maximale : tous les actifs du client sont intégrés dans la plateforme Elastic, y compris le déploiement complet de l'agent EDR (Elastic Defend) sur 200 endpoints. Visibilité 360° totale, de la périphérie réseau à chaque poste utilisateur.

| Agent | Prix Unitaire / mois | Nombre d'unités | Coût Mensuel |
|---|---:|---:|---:|
| 🔍 RG-MDR | 380 TND | 16 | **6 080 TND** |
| 🔭 RG-SIEM | 270 TND | 15 | **4 050 TND** |
| 🔒 RG-EDR | 80 TND | 200 | **16 000 TND** |
| | | **TOTAL MENSUEL** | **26 130 TND/mois** |

**Coût annuel estimé** : ~313 560 TND/an *(hors frais d'intégration initiale)*

---

### 📊 Tableau Comparatif Résumé

| | Offre 3 *(Recommandée)* | Offre 2 | Offre 1 *(Totale)* |
|---|:---:|:---:|:---:|
| **Agent RG-SIEM** | 11 unités | 6 unités | 15 unités |
| **Agent RG-MDR** | 13 unités | 18 unités | 16 unités |
| **Agent RG-EDR** | — | — | 200 endpoints |
| **Couverture Endpoints** | ❌ | ❌ | ✅ Complète |
| **Couverture Réseau** | ✅ | ✅ Renforcée | ✅ |
| **TOTAL MENSUEL** | **7 910 TND** | **8 460 TND** | **26 130 TND** |
| **Coût Annuel** | ~94 920 TND | ~101 520 TND | ~313 560 TND |

---

### 💰 Frais d'Intégration Initiale (One-Time)

> **Onboarding & Mise en Production** : **10 000 TND** (frais uniques, non récurrents)

Ce montant couvre le déploiement initial de la plateforme Elastic Stack, l'installation et la configuration des agents, la création des pipelines d'ingestion, le paramétrage des règles de détection, et la formation de transfert de connaissances vers l'équipe du client.

---

### 📎 Pièce Jointe

Le fichier Excel **`inventaire_client_[nom_client].xlsx`** joint à cet email contient :
- L'inventaire complet des actifs du client par catégorie
- Le détail du calcul par agent et par actif
- Les paramètres de calibration utilisés (EPS, volume mensuel, taille des événements)
- Le récapitulatif des coûts par offre

---

Je reste disponible pour tout complément d'information ou pour ajuster les paramètres de l'une ou l'autre des offres selon vos retours commerciaux.

Bien cordialement,

**[Votre Prénom & Nom]**
SOC Engineer — RaiseGuard
📧 [votre.email@raiseguard.com]
📞 [+216 XX XXX XXX]

---
*Ce document est confidentiel et destiné exclusivement à son destinataire.*
*Les tarifs indiqués sont valables jusqu'au [Date de validité — ex: 31/12/2026].*
