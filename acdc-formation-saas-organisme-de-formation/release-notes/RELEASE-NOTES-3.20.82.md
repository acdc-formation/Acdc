# ACDC Formation SAAS — version 3.20.82

## Objet

**Premier patch du module Portail Formateur** (1/5).

Ce patch pose les **fondations infrastructure** : extension du schéma BD, création des tables annexes, création du rôle WordPress dédié, et système d'invitation par e-mail. **Aucun portail front n'est livré dans cette version** — il vient en 3.20.83.

À l'issue de ce patch, vous pouvez déjà inviter un formateur par e-mail depuis sa fiche admin, lui envoyer un lien d'activation, et lui faire définir son mot de passe. Mais une fois connecté, il n'a encore rien à voir : son portail sera construit dans les patches suivants.

## Modifications appliquées

### 1. Extension de `wp_acdc_of_trainers`

Ajout de 8 colonnes via `maybe_add_table_column()` (idempotent, ne casse pas les bases existantes) :

| Colonne | Type | Usage |
|---|---|---|
| `user_id` | `BIGINT UNSIGNED DEFAULT 0` | Lien vers le compte WordPress du formateur |
| `permissions_json` | `LONGTEXT` | Matrice de permissions personnalisées (profil custom) |
| `permission_profile` | `VARCHAR(20) DEFAULT 'simple'` | Profil actif : `simple`, `autonome` ou `custom` |
| `legal_status` | `VARCHAR(50)` | Statut juridique : salarié / indépendant / interne |
| `urssaf_attestation_expires_at` | `DATE` | Date d'expiration de l'attestation URSSAF |
| `rc_pro_expires_at` | `DATE` | Date d'expiration de la RC pro |
| `expertise_nsf_codes` | `TEXT` | Codes NSF de spécialité (cohérent avec 3.20.76) |
| `invited_at` | `DATETIME` | Date d'envoi de l'invitation |

### 2. Nouvelle table `wp_acdc_of_trainer_documents`

Bibliothèque personnelle du formateur (CV, diplômes, attestations, certifications, RC pro, etc.) :

```sql
CREATE TABLE wp_acdc_of_trainer_documents (
  id, trainer_id, category, label, file_url, file_name,
  issued_at, expires_at, uploaded_by, uploader_user_id,
  is_qualiopi_proof, notes_admin, created_at, updated_at
);
```

- **`category`** : `cv` / `diploma` / `certification` / `urssaf_attestation` / `rc_pro` / `mandate` / `qualiopi_proof` / `other`.
- **`expires_at`** : essentiel pour les justificatifs renouvelables (URSSAF tous les 6 mois, RC pro annuelle).
- **`uploaded_by`** : `admin` ou `trainer` — traçabilité de qui a posé le document.
- **`is_qualiopi_proof`** : flag pour repérer rapidement les pièces qui comptent comme preuve d'audit.
- **`notes_admin`** : annotations privées (non visibles par le formateur).

### 3. Nouvelle table `wp_acdc_of_trainer_resources`

Ressources pédagogiques que le formateur partage avec ses apprenants :

```sql
CREATE TABLE wp_acdc_of_trainer_resources (
  id, trainer_id, formation_id, session_id, label,
  file_url, file_name, external_url, description_text,
  is_visible_to_learners, published_at, created_at, updated_at
);
```

- **`formation_id`** : la formation à laquelle la ressource est rattachée (obligatoire).
- **`session_id`** : optionnel, si la ressource est spécifique à une session précise.
- **`is_visible_to_learners`** : permet de préparer en brouillon avant publication.
- **`published_at`** : date de mise à disposition effective — utile pour la traçabilité Qualiopi.

### 4. Rôle WordPress `acdc_trainer`

Création du rôle au moment de l'`init` du plugin (idempotent) :

- Capabilities : `read` + `acdc_view_trainer_portal`.
- **Aucun accès à `/wp-admin/`** par construction (pas de `edit_posts`, pas de `manage_options`).
- Si le rôle préexiste, la capability custom est garantie même sur un compte créé via une intervention manuelle antérieure.

Cette capability est **prévue pour le portail front** (3.20.83) qui devra vérifier `current_user_can( 'acdc_view_trainer_portal' )` avant chaque rendu.

### 5. Système d'invitation par e-mail

Bandeau visuel ajouté en haut du formulaire de fiche formateur (mode édition uniquement) :

> **Accès au portail formateur**
> Aucun compte n'est encore associé à ce formateur.
> [ Inviter le formateur ]

Au clic sur « Inviter », le handler `handle_invite_trainer()` :

1. Vérifie la capability admin (`manage_options`) et la nonce.
2. Récupère l'e-mail de la fiche formateur (sécurité : `is_email`).
3. Cherche un compte WordPress existant avec ce mail. S'il existe : on le réutilise (pas de doublon). Sinon, création d'un compte via `wp_create_user()` avec :
   - Username `prenom.nom` (sans accents, suffixé par un nombre si déjà pris).
   - Mot de passe aléatoire de 24 caractères (l'utilisateur le redéfinira).
4. Ajout du rôle `acdc_trainer` au compte.
5. Mise à jour des informations d'identité (`first_name`, `last_name`, `display_name`).
6. Liaison `trainer.user_id` ↔ `user.ID` + traçage de la date d'invitation.
7. Génération d'un **lien de réinitialisation de mot de passe** via `get_password_reset_key()` — c'est le mécanisme natif WordPress, sécurisé, avec expiration automatique (24 h).
8. Envoi d'un e-mail clair en français avec le lien d'activation.

Si l'envoi échoue (serveur SMTP mal configuré, blacklist, etc.), le message d'erreur **fournit le lien d'activation à l'admin** pour qu'il puisse le transmettre manuellement par un autre canal. Pas de blocage silencieux.

Le bouton se transforme en **« Renvoyer l'invitation »** une fois le compte créé, avec affichage de la date du dernier envoi. Utile en cas d'oubli du lien initial ou d'expiration.

### 6. Système de permissions actif (préparation 3.20.86)

Trois nouvelles fonctions utilitaires posées dans le kernel, prêtes à être utilisées par le portail formateur dans les patches suivants :

- `get_acdc_trainer_permissions_definition()` — liste des **11 interrupteurs** prévus avec leurs valeurs par défaut pour les profils `simple` et `autonome`.
- `get_acdc_trainer_active_permissions( $trainer )` — résout les permissions effectives d'un formateur selon son profil actif et ses overrides éventuels en BD.
- `trainer_can( $trainer_id, $permission )` — vérification simple à appeler avant chaque action sensible côté portail.

#### Liste des 11 interrupteurs prévus

| Clé | Profil simple | Profil autonome |
|---|---|---|
| `view_dashboard` | ✅ | ✅ |
| `edit_own_profile` | ✅ | ✅ |
| `manage_own_documents` | ✅ | ✅ |
| `view_own_sessions` | ✅ | ✅ |
| `view_session_learners` | ✅ | ✅ |
| `view_learner_personal_data` | ❌ | ✅ |
| `manage_resources` | ✅ | ✅ |
| `mark_attendance` | ❌ | ✅ |
| `view_evaluations` | ❌ | ✅ |
| `send_messages_to_learners` | ❌ | ✅ |
| `view_qualiopi_status` | ✅ | ✅ |

L'**UI d'administration de ces interrupteurs** sera livrée en 3.20.86 (dernier patch de la série). Pour l'instant, vos formateurs invités auront automatiquement le profil `simple` (valeur par défaut de la colonne `permission_profile`).

## Cohérence visuelle

- Bandeau d'invitation : **utilise les variables CSS** `var(--acdc-primary)` et `var(--acdc-primary-soft)` avec fallbacks `#d6a353` et `#f3e3bf` (palette ACDC active, pas l'ancien `#8b5b23`).
- Boutons : classe `acdc-button acdc-button-primary` existante.
- Pas de couleur en dur dans la palette de marque.
- Bordure latérale gauche colorée (4 px), même pattern que les cartes vert/rouge de la 3.20.78 — cohérence visuelle entre les modules.

## Engagement de préservation

- **Aucune migration de données.** Les formateurs existants gardent toutes leurs informations.
- **Aucun comportement modifié** sur les pages existantes du back office.
- **Aucune dépendance externe ajoutée.**
- **Aucune feuille de style globale modifiée** — bandeau stylé inline (sera extrait en CSS centralisé dès qu'il sera utilisé ailleurs).
- **Le rôle `acdc_trainer` n'est pas supprimé à la désactivation** — pour ne pas perdre les liaisons `user_id` ↔ `trainer.user_id` existantes.

## Risques de régression — analyse

| Scénario | Avant 3.20.82 | Après 3.20.82 |
|---|---|---|
| Formulaire formateur en mode création | Inchangé | Bandeau invisible (visible uniquement en édition) |
| Formulaire formateur en mode édition | Inchangé | Bandeau d'invitation visible en haut |
| Formulaire formateur en mode lecture | Inchangé | Bandeau invisible (mode `is_view`) |
| Liste des formateurs | Inchangée | Inchangée |
| Bases existantes | — | Migration BD idempotente, sans blocage |
| Si `wp_mail` n'est pas configuré | — | Compte créé quand même, lien fourni à l'admin pour relai manuel |
| Si l'e-mail du formateur correspond à un user WP existant | — | Réutilisation du compte (rôle ajouté, pas de doublon) |
| Si le rôle `acdc_trainer` existe déjà | — | Aucune duplication, capability garantie |

**Aucune régression identifiée.**

## Points de vigilance opérationnelle

### Configuration SMTP

Pour que les e-mails d'invitation arrivent réellement, votre WordPress doit être configuré avec un SMTP fiable. Sur **PlanetHoster N0C** par défaut, `wp_mail` peut être bloqué ou aboutir en spam.

Recommandations :

- Utiliser un plugin SMTP dédié (WP Mail SMTP, FluentSMTP) avec un service comme Brevo, SendGrid ou OVH Mailpro.
- Tester l'envoi avec un faux formateur ayant votre propre adresse mail avant de basculer en production.
- Si l'envoi échoue, le lien d'activation reste affiché dans le message d'erreur côté admin pour transmission manuelle.

### Sécurité

- **Pas d'accès `wp-admin`** pour le rôle `acdc_trainer` (par construction, aucune capability d'édition n'a été accordée).
- Lien d'invitation expirant après 24 h (mécanisme natif WordPress).
- Username généré sans accent et suffixé en cas de collision — pas d'erreur silencieuse.

### RGPD

Le compte WordPress créé contient l'e-mail et le nom du formateur. Si un formateur quitte ACDC, il faudra :

- Soit supprimer son compte WP (la liaison `trainer.user_id` deviendra orpheline mais sans erreur).
- Soit dissocier son compte (mettre `user_id = 0` sur la fiche formateur).

Cette gestion sera proposée dans le 3.20.86 (panneau permissions + administration du compte).

## Procédure de test

### Test 1 — Migration BD

1. Purger LiteSpeed.
2. Installer 3.20.82. Purger à nouveau.
3. (Optionnel) En SQL :
   - `SHOW COLUMNS FROM wp_acdc_of_trainers LIKE 'user_id';` → 1 ligne.
   - `SHOW COLUMNS FROM wp_acdc_of_trainers LIKE 'permission_profile';` → 1 ligne.
   - `SHOW TABLES LIKE 'wp_acdc_of_trainer_documents';` → 1 ligne.
   - `SHOW TABLES LIKE 'wp_acdc_of_trainer_resources';` → 1 ligne.

### Test 2 — Rôle WordPress

1. Aller dans Utilisateurs → Tous les utilisateurs.
2. Vérifier que le rôle « Formateur ACDC » apparaît dans la liste des rôles disponibles (menu déroulant de filtre par rôle).

### Test 3 — Bandeau invitation

1. Aller sur Formateurs → Modifier un formateur existant qui a une adresse e-mail.
2. Vérifier que le bandeau d'invitation apparaît en haut, avec le bouton « Inviter le formateur ».
3. Vérifier qu'en mode Voir (lecture), le bandeau **n'apparaît pas**.
4. Vérifier qu'en mode Création (Nouveau formateur), le bandeau **n'apparaît pas non plus** (impossible d'inviter avant d'enregistrer).

### Test 4 — Invitation effective

**À tester de préférence avec votre propre adresse e-mail comme formateur fictif.**

1. Créer un formateur avec une adresse e-mail à laquelle vous avez accès.
2. Cliquer « Inviter le formateur ».
3. Vérifier le message de succès : « Invitation envoyée à xxx@yyy.com. ».
4. Vérifier la réception de l'e-mail (vérifier les spams si rien dans la boîte principale).
5. Cliquer sur le lien dans l'e-mail → page de définition de mot de passe WordPress.
6. Définir un mot de passe → connexion automatique.
7. **Important** : à ce stade, l'utilisateur connecté ne voit rien (portail pas encore construit). Il peut atterrir sur le tableau de bord WordPress par défaut. **C'est normal pour 3.20.82.** En 3.20.83, on créera la redirection automatique vers le portail formateur.

### Test 5 — Bouton renvoyer

1. Recharger la fiche du formateur invité.
2. Vérifier que le bandeau affiche maintenant « Compte actif. Invitation envoyée le … » et le bouton « Renvoyer l'invitation ».
3. Cliquer → nouveau lien d'activation envoyé.

### Test 6 — E-mail invalide

1. Sur un formateur sans adresse e-mail (ou avec un format invalide), tenter l'invitation.
2. Vérifier le message d'erreur explicite : « L'adresse e-mail du formateur est invalide ou manquante. Renseignez-la avant d'inviter. »

### Test 7 — Compte WP préexistant

1. Créer un formateur avec une adresse e-mail qui correspond à un utilisateur WordPress déjà existant (par exemple votre propre compte admin).
2. Inviter → vérifier que :
   - Aucune erreur n'est levée.
   - Le rôle `acdc_trainer` est ajouté au compte existant (le compte garde aussi ses autres rôles).
   - La fiche formateur est bien liée au compte existant.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump `3.20.81` → `3.20.82`.
- `includes/class-acdc-plugin.php` :
  - Déclaration de 2 nouvelles propriétés `$trainer_document_table`, `$trainer_resource_table`.
  - Initialisation des chemins de tables.
  - Branchement de l'action admin `acdc_invite_trainer`.
- `includes/kernel/class-acdc-kernel-core-trait.php` :
  - Migration BD : 8 colonnes ajoutées à `wp_acdc_of_trainers`.
  - Création des 2 tables `wp_acdc_of_trainer_documents` et `wp_acdc_of_trainer_resources`.
  - Nouvelle fonction `ensure_trainer_role()` branchée sur `maybe_upgrade()`.
  - 3 nouvelles fonctions de permissions actives : `get_acdc_trainer_permissions_definition()`, `get_acdc_trainer_active_permissions()`, `trainer_can()`.
- `includes/kernel/class-acdc-kernel-actions-trait.php` :
  - Nouvelle fonction `handle_invite_trainer()`.
  - 2 helpers privés : `redirect_back_to_trainer_edit()` et `acdc_strip_accents()`.
- `includes/kernel/class-acdc-kernel-render-trait.php` :
  - Bandeau d'invitation ajouté en haut de `render_front_trainer_form()` (mode édition uniquement).

**Aucun autre fichier modifié.**

## Si quelque chose ne va pas

Le retour à 3.20.81 est sûr. Les colonnes BD ajoutées et les 2 nouvelles tables resteront en BD (inertes), aucune perte de données. Le rôle WordPress `acdc_trainer` reste également présent (à supprimer manuellement si vous voulez vraiment l'effacer, via `Utilisateurs → Membres et rôles`).

## Suite logique — feuille de route module

| Patch | Périmètre | Statut |
|---|---|---|
| **3.20.82** | Socle BD + auth + invitation | ✅ Livré |
| **3.20.83** | Portail front formateur : connexion, dashboard minimal, déconnexion | À venir |
| **3.20.84** | Bibliothèque personnelle (CV, diplômes, attestations) côté formateur ET admin | À venir |
| **3.20.85** | Ressources pédagogiques + intégration extranet apprenant + traçabilité | À venir |
| **3.20.86** | UI d'administration des permissions granulaires (interrupteurs) | À venir |

Vous testez 3.20.82, vous validez ou vous remontez les ajustements, puis on enchaîne sur 3.20.83.
