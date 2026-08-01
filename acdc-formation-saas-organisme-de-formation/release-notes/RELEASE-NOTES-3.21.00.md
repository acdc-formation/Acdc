# ACDC Formation SAAS — Notes de version 3.21.00

**Date :** 2026-04-27
**Type :** Saut de version mineure (ouverture du chantier 3.21)
**Base de référence :** 3.20.105 (gelée comme dernière version stable de la série 3.20)
**Slug :** `acdc-formation-saas-organisme-de-formation` (inchangé)

---

## Résumé exécutif

La 3.21.00 est la **première sous-version d'un chantier majeur** : la mise en place d'un moteur unifié de **quiz, tests de positionnement et évaluations des acquis**, avec mode **live synchrone** style Kahoot et **traçabilité Qualiopi**.

Cette première sous-version pose **uniquement les fondations** — schéma BDD, structure de fichiers, intégration dans la fiche formation. Aucune fonctionnalité métier finale n'est encore activée. Les boutons « + Créer » sont visibles mais désactivés avec un tooltip « Disponible à partir de la version 3.21.01 ».

L'objectif est de **valider l'ossature technique sans risque de régression** avant de greffer les fonctionnalités dans les sous-versions suivantes (3.21.01 → 3.21.13).

---

## Changements appliqués

### Nouveaux fichiers (7)

```
includes/quizzes/class-acdc-quizzes-core-trait.php
includes/quizzes/class-acdc-quizzes-actions-trait.php
includes/quizzes/class-acdc-quizzes-engine-trait.php
includes/quizzes/class-acdc-quizzes-render-trait.php
includes/async-dispatcher/class-acdc-async-dispatcher-trait.php
includes/class-acdc-quizzes.php          (façade qui charge les 4 traits)
includes/class-acdc-async-dispatcher.php (façade qui charge le trait socle)
```

### Fichiers modifiés (2)

- `acdc-formation-saas-organisme-de-formation.php`
  - Bump version : `3.20.105` → `3.21.00`
  - Bump constante `ACDC_OF_SAAS_VERSION`
  - Ajout de deux blocs `require_once` pour charger les nouvelles façades (juste après le module `evaluations`).

- `includes/class-acdc-plugin.php`
  - Ajout de 5 nouveaux `use` de traits dans la classe principale (`ACDC_Async_Dispatcher_Trait` + 4 traits du module quizzes).
  - Ajout de 6 lignes en fin de constructeur `__construct()` pour initialiser le module : `init_quizzes_module_tables()`, `maybe_run_qz_db_upgrade()` sur `init` priorité 9, `ensure_quiz_async_public_page()` et `ensure_quiz_live_public_page()` sur `init` priorité 11, `ensure_quiz_cron_events()` sur `init` priorité 12, et `register_quizzes_module_hooks()` directement dans le constructeur pour les hooks d'enregistrement précoce.

**Aucun autre fichier existant n'est modifié.** Risque de régression sur le legacy : nul.

### Nouvelles tables BDD (8)

Toutes préfixées `{$wpdb->prefix}acdc_of_qz_`, créées via `dbDelta()` :

| Table | Rôle |
|---|---|
| `acdc_of_qz_quizzes` | Bibliothèque des quiz (par formation, avec versioning) |
| `acdc_of_qz_questions` | Questions appartenant à un quiz |
| `acdc_of_qz_answers` | Propositions de réponse pour les questions à choix |
| `acdc_of_qz_objectives` | Objectifs pédagogiques d'un quiz |
| `acdc_of_qz_sessions` | Sessions de passage (live ou async) |
| `acdc_of_qz_participants` | Participants à une session |
| `acdc_of_qz_player_answers` | Réponses individuelles des participants |
| `acdc_of_qz_logs` | Journal d'événements append-only pour audit Qualiopi |

Ces tables sont **strictement parallèles** aux embryons existants (`acdc_of_quizzes`, `acdc_of_positioning_tests`, `acdc_of_evaluations`) qui restent intacts. Aucun conflit possible : préfixe distinct (`acdc_of_qz_*` vs `acdc_of_*`).

### Nouvelles pages publiques WordPress (2)

Créées automatiquement à l'init si absentes (idempotent) :

- `/acdc-quiz-public/` (slug `acdc-quiz-public`) — page d'accueil du shortcode `[acdc_qz_async_public]` (passation tokenisée).
- `/acdc-quiz-live/` (slug `acdc-quiz-live`) — page d'accueil du shortcode `[acdc_qz_live_player]` (mode live côté apprenant).

En 3.21.00, ces pages affichent uniquement un message « Bientôt disponible ».

### Nouvelles options WordPress (2)

- `acdc_of_qz_db_version` : version SQL appliquée du module (valeur initiale `1.0.0`).
- `acdc_of_qz_async_public_page_id` : ID de la page publique async.
- `acdc_of_qz_live_public_page_id` : ID de la page publique live.

### Nouveaux événements cron (5)

Programmés à l'init s'ils ne le sont pas déjà :

- `acdc_of_qz_cron_dispatches` (toutes les heures, +5 min)
- `acdc_of_qz_cron_reminders` (toutes les heures, +10 min)
- `acdc_of_qz_cron_expirations` (toutes les heures, +15 min)
- `acdc_of_qz_cron_close_inactive_sessions` (toutes les heures, +10 min)
- `acdc_of_qz_cron_rgpd_purge` (quotidien, +24 h)

Les handlers sont des **stubs inactifs** en 3.21.00 — pas d'effet de bord.

### Nouvelle page admin

- `admin.php?page=acdc-of-qz-pilotage` — Page « Pilotage Quiz », sous-menu de `acdc-of-dashboard` (suit la convention du plugin : `add_submenu_page()` + `remove_submenu_page()` pour rester cohérent avec la navigation custom du portal-shell). Accessible par URL directe.

En 3.21.00, affiche un compteur minimal de quiz actifs par finalité.

---

## Stratégie de coexistence avec l'embryon legacy

Le plugin 3.20.x contenait déjà des tables et du code embryonnaires :

- `acdc_of_quizzes`
- `acdc_of_positioning_tests`
- `acdc_of_evaluations`
- méthodes : `handle_save_quiz()`, `handle_delete_quiz()`, `handle_send_quiz()`, `get_quizzes()`, `render_admin_quiz_page()`, etc.
- pages admin : `acdc-of-quiz`, `acdc-of-positioning-tests`, `acdc-of-evaluations`

**Ces éléments sont intégralement préservés.** La 3.21.00 ne touche à rien : tables intactes, code intact, URLs accessibles.

Côté UI, ces écrans étaient déjà retirés de la sidebar admin par `remove_submenu_page()` dans `register_admin_menu()` (kernel) — masquage doux déjà en place sans intervention nécessaire.

**Aucune migration n'est planifiée à ce stade**, sur confirmation utilisateur que l'embryon n'a jamais été utilisé en production. Une migration sera étudiée en 3.23+ si besoin.

---

## Scénario de recette (à exécuter après installation)

### 1. Installation

1. Sauvegarde préalable de la base de données (réflexe systématique).
2. Désactivation du plugin 3.20.105 dans WP Admin.
3. Suppression du dossier `wp-content/plugins/acdc-formation-saas-organisme-de-formation/` (les données BDD restent).
4. Téléversement du ZIP 3.21.00 via WP Admin > Extensions > Ajouter > Téléverser.
5. Activation du plugin.

> Alternative : remplacement direct du dossier via FTP/SFTP. Le slug est inchangé (`acdc-formation-saas-organisme-de-formation`), donc WordPress remplace proprement les fichiers.

### 2. Vérification d'absence de régression (priorité 1)

- [ ] La version affichée dans WP Admin > Extensions est bien `3.21.00`.
- [ ] Aucune erreur PHP fatale au chargement (page blanche, écran d'erreur).
- [ ] Le `wp-content/debug.log` ne mentionne pas de `Fatal error` ou `Warning` lié au module quizzes.
- [ ] Les pages admin existantes du plugin sont toujours accessibles : Centre d'administration, Formations, Sessions, Apprenants, Documents, etc.
- [ ] Une fiche formation ouverte en édition fonctionne normalement, ses onglets existants (Informations, Programme, Sessions...) répondent.

### 3. Vérification BDD (priorité 1)

Via phpMyAdmin ou la console MySQL :

- [ ] Les 8 nouvelles tables existent :
  ```sql
  SHOW TABLES LIKE 'wp_acdc_of_qz_%';
  ```
  Doit lister : `wp_acdc_of_qz_quizzes`, `wp_acdc_of_qz_questions`, `wp_acdc_of_qz_answers`, `wp_acdc_of_qz_objectives`, `wp_acdc_of_qz_sessions`, `wp_acdc_of_qz_participants`, `wp_acdc_of_qz_player_answers`, `wp_acdc_of_qz_logs`.
  *(Le préfixe peut différer selon la config WP — adapter si besoin.)*

- [ ] Les colonnes principales sont là :
  ```sql
  DESCRIBE wp_acdc_of_qz_quizzes;
  ```
  Doit notamment montrer `formation_id`, `quiz_purpose`, `delivery_mode`, `version_number`, `is_current`, `is_locked`.

- [ ] L'option de version SQL est posée :
  ```sql
  SELECT * FROM wp_options WHERE option_name = 'acdc_of_qz_db_version';
  ```
  Valeur attendue : `1.0.0`.

- [ ] Les tables legacy sont toujours présentes et inchangées :
  ```sql
  SHOW TABLES LIKE 'wp_acdc_of_quizzes';
  SHOW TABLES LIKE 'wp_acdc_of_positioning_tests';
  SHOW TABLES LIKE 'wp_acdc_of_evaluations';
  ```

### 4. Vérification des pages publiques (priorité 2)

- [ ] La page `/acdc-quiz-public/` affiche le titre « Réponse à un quiz » et un message d'attente.
- [ ] La page `/acdc-quiz-live/` affiche le titre « Quiz en direct » et un message d'attente.

Ces deux URL doivent répondre **sans erreur 404**.

### 5. Vérification de la page Pilotage Quiz (priorité 2)

- [ ] L'URL `wp-admin/admin.php?page=acdc-of-qz-pilotage` répond et affiche un tableau « Quiz actifs par finalité » avec 0 partout (table vide, comportement attendu).
- [ ] La page n'apparaît PAS dans la sidebar admin (comportement voulu, cohérent avec le reste du plugin).

### 6. Vérification de l'intégration dans la fiche formation (priorité 3)

> **Note importante** : en 3.21.00, l'onglet « Quiz » sur la fiche formation **n'est pas encore greffé visuellement**. Les méthodes de rendu sont en place (`render_qz_formation_tab()`) mais l'intégration dans le système d'onglets existant de la fiche formation sera réalisée en 3.21.01 (en même temps que l'éditeur).

Cette étape est volontairement reportée pour ne pas mélanger deux changements à risque dans la même sous-version.

### 7. Vérification des événements cron (priorité 3)

Via l'extension WP-CLI (ou le plugin WP Crontrol) :

- [ ] `acdc_of_qz_cron_dispatches` est planifié.
- [ ] `acdc_of_qz_cron_reminders` est planifié.
- [ ] `acdc_of_qz_cron_expirations` est planifié.
- [ ] `acdc_of_qz_cron_close_inactive_sessions` est planifié.
- [ ] `acdc_of_qz_cron_rgpd_purge` est planifié.

Si aucun n'est listé, c'est probablement parce que la page n'a pas encore été chargée depuis l'activation. Naviguer une fois sur le tableau de bord WP Admin déclenche `init` et provoque la planification.

### 8. Vérification du journal d'audit (priorité 3)

Tous les handlers admin-post du module sont des **stubs sécurisés** qui journalisent leur appel. Pour valider :

- [ ] Construire une URL d'invocation manuelle (pour test) :
  ```
  wp-admin/admin-post.php?action=acdc_of_qz_save_quiz
  ```
- [ ] L'appel renvoie une erreur d'accès si non connecté, ou redirige avec un avis si connecté en admin.
- [ ] Une nouvelle ligne apparaît dans `wp_acdc_of_qz_logs` avec `event_type = 'stub_called'` et `event_label = 'save_quiz'`.

---

## Points de vigilance

### Hébergement N0C / LiteSpeed

- Aucun changement d'architecture qui pourrait déclencher des 502.
- Pas de configuration TLS modifiée.
- Pas de nouvelle entrée massive d'événements cron susceptible de saturer (5 hooks au total, espacés).

### Cache navigateur / WordPress

Comme à chaque déploiement : **vider tous les caches** après installation pour éviter de fausses régressions visuelles. Au minimum :

- Cache LiteSpeed (purge totale).
- Cache navigateur (Ctrl+F5 sur le back-office).
- Vérifier qu'aucun plugin de cache supplémentaire ne sert d'anciens assets.

### Compatibilité PHP

Code testé syntaxiquement avec PHP 8.3. Aucune fonctionnalité spécifique requise au-delà de PHP 7.4 (compatible avec la base supportée par WordPress).

---

## Limites connues de la 3.21.00

Conformément au phasage validé, **rien d'autre n'est livré** dans cette sous-version. Notamment :

- ❌ Pas d'éditeur de quiz (3.21.01).
- ❌ Pas de versioning fonctionnel ni de bouton « Activer cette version » (3.21.02).
- ❌ Pas d'envoi async ni de page de passation (3.21.03).
- ❌ Pas d'anti-triche ni d'écrans de résultats (3.21.04).
- ❌ Pas de mode live host (3.21.05).
- ❌ Pas de mode live player (3.21.06).
- ❌ Pas de scoring complet (3.21.07).
- ❌ Pas d'attestations PDF (3.21.08).
- ❌ Pas d'onglet « Mes quiz » sur le portail apprenant (3.21.09).
- ❌ Pas d'exports ni de tableau de pilotage détaillé (3.21.10).
- ❌ Pas de mode papier de secours (3.21.11).
- ❌ Pas d'anonymisation RGPD ni de purge cron active (3.21.12).
- ❌ Pas d'audit a11y / i18n complet (3.21.13).

C'est volontaire. Chaque sous-version est livrée séparément avec son propre scénario de recette, pour valider à chaque palier.

---

## Référence — feuille de route 3.21

| Version | Thème | Statut |
|---|---|---|
| **3.21.00** | Socle BDD + scaffolding admin | ✅ Cette livraison |
| 3.21.01 | Éditeur de quiz CRUD | À venir |
| 3.21.02 | Versioning et verrouillage | À venir |
| 3.21.03 | Mode async — envoi et passation | À venir |
| 3.21.04 | Anti-triche async + écrans résultats | À venir |
| 3.21.05 | Mode live — host (formateur) | À venir |
| 3.21.06 | Mode live — player (apprenant) | À venir |
| 3.21.07 | Scoring complet et leaderboard | À venir |
| 3.21.08 | Attestations PDF Qualiopi | À venir |
| 3.21.09 | Portail apprenant — onglet Mes quiz | À venir |
| 3.21.10 | Page Pilotage Qualiopi + exports | À venir |
| 3.21.11 | Mode papier de secours | À venir |
| 3.21.12 | RGPD — anonymisation + purge | À venir |
| 3.21.13 | Peaufinage UI, accessibilité, i18n | À venir |
| 3.21.14 | Correctifs post-recette | À venir |

---

## En cas de problème

1. **Erreur 500 / page blanche après activation** : vérifier `wp-content/debug.log`. Si erreur PHP fatale liée aux nouveaux traits, désactiver le plugin via FTP en renommant le dossier, vérifier l'intégrité du téléversement.

2. **Tables non créées** : vérifier que `dbDelta()` a bien été appelé. Forcer un rechargement en visitant le tableau de bord admin (déclenche le hook `init`). Si toujours absent, vérifier les permissions de l'utilisateur MySQL (besoin de `CREATE TABLE`).

3. **Pages publiques non créées** : vérifier l'option `acdc_of_qz_async_public_page_id` en BDD. Si vide ou pointant vers un ID inexistant, supprimer l'option et recharger une page admin pour relancer la création idempotente.

4. **Régression sur l'existant** : ne devrait pas se produire (aucun fichier existant modifié hors plugin principal et class-acdc-plugin.php). En cas de doute, comparer les fichiers existants avec la 3.20.105 — ils doivent être strictement identiques sauf pour les 2 fichiers documentés ci-dessus.

---

**Prochaine étape** : validation de cette 3.21.00 par recette, puis ouverture de la 3.21.01 (éditeur de quiz CRUD).
