# ACDC SAAS OF — Release notes 3.20.91

**Module Formateur — UI permissions granulaires côté admin**

Version précédente : 3.20.90 (calendrier annuel des disponibilités, livré et validé).
Date : avril 2026.
Slug : `acdc-formation-saas-organisme-de-formation` (inchangé).
Migration BD : **aucune** — toutes les colonnes nécessaires ont été posées en 3.20.82.

---

## Ce que livre ce patch

La fiche formateur en back-office expose désormais une **matrice de permissions éditable** qui pilote ce que voit chaque formateur dans son extranet `/extranet-formateur/`.

Trois préréglages :

- **Simple** — accès essentiels (tableau de bord, profil, justificatifs, sessions, statut Qualiopi).
- **Autonome** — accès élargis (les essentiels + données personnelles apprenants, émargement, évaluations, messagerie).
- **Personnalisé** — la matrice fine devient éditable, l'admin coche/décoche permission par permission.

Les 11 permissions du set canonique (déjà définies en 3.20.82 dans `get_acdc_trainer_permissions_definition()`) sont :

`view_dashboard`, `edit_own_profile`, `manage_own_documents`, `view_own_sessions`,
`view_session_learners`, `view_learner_personal_data`, `manage_resources`,
`mark_attendance`, `view_evaluations`, `send_messages_to_learners`, `view_qualiopi_status`.

Comportement UI :

- Sélection d'un préset → cases mises à jour selon les defaults du profil, désactivées.
- Bascule sur **Personnalisé** → cases éditables.
- Modification manuelle d'une case alors qu'un préset est sélectionné → bascule automatique sur **Personnalisé** (l'état que l'utilisateur vient de modifier est conservé).
- En mode « Voir un formateur » : tout est en lecture seule.

Comportement persistance :

- Profil **Simple** ou **Autonome** → `permission_profile` stocké, `permissions_json` **vidé** (les defaults s'appliquent au runtime via `get_acdc_trainer_active_permissions()`).
- Profil **Personnalisé** → `permission_profile = 'custom'` + `permissions_json` sérialisé avec **uniquement les clés du set canonique** (allowlist serveur stricte, toute clé extérieure est silencieusement ignorée).

Conservation des données saisies en cas d'erreur formulaire : si un champ obligatoire manque (prénom, nom, e-mail) et que la page se recharge, le profil sélectionné et les cases cochées sont restaurés.

---

## Ce qui est retiré

L'ancienne matrice statique « Menu : Tableau de bord / CRM / Répertoire / ... » qui s'affichait en lecture seule sur la fiche formateur est **remplacée** par le nouveau panneau.

Justification : cette matrice décrivait des accès au **wp-admin WordPress**. Or depuis la 3.20.83, les formateurs ne se connectent plus jamais au wp-admin — ils passent exclusivement par leur portail extranet avec auth custom. La matrice était devenue un vestige sans branchement effectif (utilisée à un seul endroit pour un rendu purement informatif, ne pilotant aucun garde-fou).

Les helpers `get_trainer_access_matrix()` et `render_access_state_icon()` sont **conservés en place** dans le code (méthodes privées de `ACDC_Kernel_Core_Trait` / `ACDC_Kernel_Render_Trait`) pour ne rien casser au cas où un appel extérieur les utiliserait. Ils ne sont simplement plus appelés par le rendu du plugin.

---

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump version `3.20.91` (header + `ACDC_OF_SAAS_VERSION`).
- `includes/kernel/class-acdc-kernel-render-trait.php` — refonte du bloc autour de l'ancienne matrice statique dans `render_front_trainer_form()`. Nouveau panneau `acdc-trainer-permissions-panel` avec sélecteur de profil + tableau des 11 permissions + script JS inline pour la synchro presets ↔ checkboxes.
- `includes/kernel/class-acdc-kernel-actions-trait.php` — extension de `handle_save_trainer()` : sanitisation et persistance de `permission_profile` (whitelist `simple|autonome|custom`) et `permissions_json` (sérialisation conditionnelle, allowlist par les clés du set canonique).

**Aucune modification** : BD, helpers core (`get_acdc_trainer_permissions_definition`, `get_acdc_trainer_active_permissions`, `trainer_can`), portail formateur, autres modules.

---

## Aucune migration BD

Les colonnes `permissions_json` (LONGTEXT) et `permission_profile` (VARCHAR(20) DEFAULT 'simple') sont posées depuis la 3.20.82. Les formateurs existants ont :

- `permission_profile = 'simple'` (default colonne)
- `permissions_json = ''` (vide)

Le helper `get_acdc_trainer_active_permissions()` retourne déjà les defaults dans ce cas — **aucun écran formateur ne change**. La rétrocompatibilité est totale.

---

## Procédure de test (à exécuter après installation)

> **Pré-requis cache** : après installation du ZIP, vider le cache LiteSpeed (admin WP → LiteSpeed Cache → Toolbox → Empty all caches) et recharger la page admin avec Ctrl+Shift+R.

### 1. Affichage initial sur formateur existant

- Ouvrir un formateur en édition : un panneau **« Permissions du portail formateur »** apparaît à la place de l'ancien tableau « Menu : ... ».
- Le sélecteur de profil est positionné sur **Simple** (défaut BD).
- Les 11 cases reflètent les defaults du profil simple, **désactivées**.

### 2. Bascule sur Autonome

- Cocher **Autonome**.
- Les cases qui ont `defaults.autonome = true` se cochent automatiquement (ex. `view_learner_personal_data`, `mark_attendance`, `view_evaluations`, `send_messages_to_learners`).
- Les cases restent désactivées.

### 3. Bascule sur Personnalisé

- Cocher **Personnalisé**.
- Les cases deviennent éditables (curseur pointer, attribut `disabled` retiré).
- L'état de départ correspond exactement à ce qui était affiché avant la bascule.

### 4. Bascule auto vers Personnalisé

- Re-cocher **Simple** (les cases reviennent aux defaults simple, désactivées).
- Cocher manuellement une case (ex. `mark_attendance`).
- Vérifier que le sélecteur bascule **automatiquement** sur Personnalisé.
- Vérifier que la case que vous venez de modifier conserve son état (et n'est pas remise aux defaults).

### 5. Persistance — profil Personnalisé

- Avec un état de cases custom, cliquer **Enregistrer**.
- Recharger la fiche : le profil est bien sur Personnalisé, les cases reflètent ce qui a été enregistré.
- Vérifier en BD via phpMyAdmin :
  - `wp_acdc_of_trainers.permission_profile = 'custom'`
  - `wp_acdc_of_trainers.permissions_json` contient un objet JSON avec les 11 clés et booléens.

### 6. Persistance — profil préréglé

- Repasser sur **Simple**, enregistrer.
- Vérifier en BD :
  - `wp_acdc_of_trainers.permission_profile = 'simple'`
  - `wp_acdc_of_trainers.permissions_json = ''` (vide — c'est volontaire, on repart des defaults au runtime).

### 7. Conservation données en cas d'erreur formulaire

- Sur Personnalisé avec quelques cases cochées spécifiquement, **vider le champ Prénom**.
- Cliquer Enregistrer → message d'erreur « Prénom, nom et e-mail sont obligatoires ».
- Au retour sur le formulaire, vérifier que :
  - Le profil **Personnalisé** est toujours sélectionné.
  - Les cases cochées avant la soumission sont **toutes** restaurées à l'identique.

### 8. Mode Voir (lecture seule)

- Ouvrir un formateur en mode « Voir » (URL avec `&action=view`).
- Le panneau s'affiche, sélecteur et cases **tous désactivés**, curseur `not-allowed` sur les cases.
- Aucun script ne s'active (le bloc `<script>` est conditionnel à `! $is_view`).

### 9. Création d'un nouveau formateur

- Créer un nouveau formateur (URL `&action=new`).
- Profil par défaut **Simple**, cases reflètent les defaults simple.
- Renseigner les champs obligatoires + cocher Personnalisé + cocher quelques cases.
- Enregistrer → vérifier en BD que les valeurs sont bien sauvegardées.

### 10. Anti-régression — formateurs intouchés

- Vérifier que les autres champs de la fiche formateur (photo, genre, prénom, nom, e-mail, téléphone, rôle, type, SIRET, description, disponibilités, alertes e-mail, infos apprenants, etc.) **fonctionnent exactement comme avant** : édition, sauvegarde, restauration en cas d'erreur.
- Vérifier que le bandeau d'invitation au portail (3.20.83) est toujours présent et fonctionnel.
- Vérifier que le calendrier des disponibilités (3.20.90) s'affiche toujours correctement en lecture seule.

---

## Points de vigilance

- **Le portail formateur n'est pas encore branché à `trainer_can()`**. Ce patch livre uniquement l'UI admin et la persistance. Le branchement effectif des permissions sur les onglets et actions du portail viendra avec les patches 3.20.92 (Mes sessions) et au-delà. Aucun risque de régression sur le portail actuel : les formateurs continuent à voir exactement la même chose qu'en 3.20.90.
- **Couplage `role_name` ↔ `permission_profile`** : volontairement découplés. Le rôle métier (« Formateur simple » / « Formateur autonome ») reste un libellé descriptif. Le profil de permissions est piloté indépendamment par l'admin via la matrice. Si l'admin change le rôle métier, les permissions ne sont **pas** automatiquement modifiées (et inversement). C'est le bon comportement : éviter qu'une bascule du rôle écrase silencieusement des permissions custom.
- **Cache navigateur / LiteSpeed** : après upgrade, vider le cache plugin et recharger en Ctrl+Shift+R. Les fichiers JS inline sont versionnés via `ACDC_OF_SAAS_VERSION`, donc les éléments enqueue (admin.js etc.) seront rafraîchis. Mais le HTML inline du panneau n'est lui rafraîchi qu'en sortie de cache page.

---

## Prochaine étape logique

**3.20.92 — Module « Mes sessions » côté formateur** : liste des sessions à venir et passées, apprenants associés. Ce module sera le **premier consommateur** des nouvelles permissions : il appellera `trainer_can($trainer_id, 'view_own_sessions')` et `trainer_can($trainer_id, 'view_session_learners')` pour conditionner l'affichage. La matrice livrée ici aura donc immédiatement un effet visible côté portail.
