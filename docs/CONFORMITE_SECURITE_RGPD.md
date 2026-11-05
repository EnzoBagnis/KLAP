# Dossier Technique : Sécurité, Conformité RGPD & Autonomie Métier

> **Projet :** KLAP — Carnet de bord numérique  
> **Structure cliente :** KLAP Maison pour la danse / Kelemenis & cie (Marseille)  
> **Référente fonctionnelle :** Mariette Travard (Responsable des publics & Administratrice applicative)  
> **Auteur :** Lucas Lepape (Développeur & Référent Architecture Backend)  
> **Cadre :** SAÉ BUT 3 Informatique — IUT d'Aix-Marseille Université  

---

## 1. Cadre de Conformité RGPD & Protection des Données Personnelles

### 1.1 Anonymisation à la source & Protection des publics mineurs
Les actions artistiques de KLAP s'adressent majoritairement à des publics scolaires mineurs. Afin d'éviter la collecte de données à caractère personnel (DCP) soumises à consentement parental obligatoire (Art. 8 du RGPD), le système applique le principe de **minimisation des données** (Art. 5.1.c) :

* **Absence d'identifiants nominatifs** : Aucun nom, prénom ou date de naissance d'élève n'est collecté ni stocké en base de données.
* **Agrégation statistique** : Les fiches enregistrent exclusivement des métriques globales par atelier : effectif total (`attendees_count`), ratio garçons/filles (`attendees_male`, `attendees_female`) et niveau de classe (`class_or_age`).
* **Évaluation pédagogique collective** : Les indicateurs d'engagement, d'expression et d'autonomie (notés de 1 à 5) qualifient la dynamique globale de la cohorte, garantissant une anonymisation irréversible.

### 1.2 Gestion du cycle de vie des comptes professionnels
Pour les intervenants (artistes, médiateurs culturels, salariés KLAP) :
* **Données strictement professionnelles** : Identité, adresse e-mail professionnelle, téléphone et structure de rattachement.
* **Désactivation sans rupture d'intégrité** : Au terme d'une collaboration, le compte bascule à l'état `suspended`. Cette mesure révoque immédiatement les droits d'accès tout en préservant l'intégrité référentielle des séances et des bilans historiques de subventions.

---

## 2. Politique de Sécurité & Contrôle d'Accès (RBAC)

Le système implémente un contrôle d'accès basé sur les rôles (*Role-Based Access Control*) :

* **Administrateur (`admin`)** : Contrôle global de l'application (gestion des utilisateurs, attribution des rôles, validation des déverrouillages, suppression encadrée de projets).
* **Médiateur (`mediator`)** : Gestion courante des projets, planification des ateliers et génération des exports de synthèse.
* **Artiste (`artist`)** : Saisie et modification des carnets de bord rattachés à ses séances.

### Mesures de protection techniques :
* **Chiffrement des authentifications** : Mots de passe hachés via l'algorithme `Bcrypt` avec facteur de coût standardisé.
* **Protection contre les attaques par force brute** : Limitation du débit (*Rate Limiting*) sur le point d'entrée `/api/login` (blocage temporaire après 5 échecs consécutifs).
* **Jetons révocables (Laravel Sanctum)** : Authentification par jetons Bearer chiffrés en base de données et invalidés à la déconnexion.
* **Sécurisation des suppressions critiques** : La suppression d'un projet exige la confirmation textuelle exacte de son intitulé dans une transaction SQL atomique.

---

## 3. Autonomie Opérationnelle & Administration Métier

L'application garantit une indépendance totale de l'administratrice vis-à-vis de tout support technique :

* **Onboarding autonome** : Invitation de nouveaux collaborateurs par e-mail avec génération d'un jeton d'activation sécurisé à usage unique.
* **Gestion dynamique des habilitations** : Élévation ou réajustement des privilèges utilisateurs directement depuis l'interface d'administration.
* **Extensibilité via champs dynamiques (`custom_fields`)** : Intégration de nouvelles questions dans les carnets de bord sans modification de code source ni intervention sur la base de données.
* **Gestion des référentiels** : Administration libre des lieux partenaires, des structures scolaires et des financeurs institutionnels.

---

## 4. Intégrité des Données & Règles de Gestion Métier

### 4.1 Procédure de verrouillage temporel (Règle J+10)
* **Verrouillage automatique** : 10 jours après la date d'une séance, la fiche passe automatiquement en lecture seule (`locked`) pour sécuriser les données avant consolidation.
* **Protocole de déverrouillage motivé** : L'artiste peut soumettre une demande motivée (`unlock_reason`). Après validation par l'administration, une fenêtre de modification temporaire de 24 heures est accordée, avec traçabilité complète de l'opération.

### 4.2 Certification des métriques de subventionnement (Calcul DRAC PACA)
Pour répondre aux exigences des financeurs publics, la base de données intègre des vues SQL garantissant le décompte exact des bénéficiaires uniques :
$$\text{Jauge globale du projet} = \sum_{\text{classes}} \max(\text{participants réels par séance})$$
Cette formule écarte les doublons de fréquentation d'une même classe sur un cycle d'ateliers.

### 4.3 Politique d'exportation bureautique
* **Formats éditables retenus** : Génération native en OpenXML Word (`.docx`) et tableur Excel (`.csv` avec encodage UTF-8 BOM).
* **Exclusion du format PDF** : Rejet systématique (code HTTP 400) des demandes d'export PDF, conformément aux besoins fonctionnels de révision éditoriale de la cliente.

---

## 5. Matrice Récapitulative des Engagements Techniques et Légaux

| Domaine | Disposition Technique Implémentée | Conformité / Bénéfice Métier |
| :--- | :--- | :--- |
| **RGPD & Publics mineurs** | Traitement exclusif de métriques agrégées anonymisées | Respect strict de l'Art. 5 du RGPD (Minimisation) |
| **Sécurité d'accès** | Tokens Sanctum, Bcrypt, Rate Limiting (5 essais) | Standards OWASP contre les intrusions |
| **Autonomie cliente** | Administration des rôles, invitations et champs dynamiques | Zéro dépendance vis-à-vis d'un prestataire technique |
| **Intégrité budgétaire** | Calcul des effectifs certifié par vues SQL MariaDB | Fiabilité juridique des bilans transmis à la DRAC |
