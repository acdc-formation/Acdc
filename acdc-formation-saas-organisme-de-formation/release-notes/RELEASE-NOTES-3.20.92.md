# ACDC SAAS OF — Release notes 3.20.92

**Module Formateur — Onglet « Mes sessions » + lien id-based session/groupe ↔ formateur**

Version précédente : 3.20.91 (matrice de permissions UI admin, livrée et validée).
Date : avril 2026.
Slug : `acdc-formation-saas-organisme-de-formation` (inchangé).
Migration BD : **2 colonnes ajoutées + 2 index + backfill automatique idempotent**.

---

## Vue d'ensemble

Ce patch livre l'onglet « Mes sessions » dans le portail formateur, premier consommateur réel de la matrice de permissions posée en 3.20.91. Il pose en même temps la couche technique manquante : un lien id-based entre les groupes/sessions pédagogiques et la fiche formateur authentifiée.

Découverte au passage : la colonne `groups.trainer_name` (héritée d'une version antérieure du plugin) n'avait en pratique **aucune UI active** dans la version 3.20.91. Aucun formulaire ne permettait à un admin de désigner le formateur d'un groupe. Le 3.20.92 livre donc deux choses : la fonctionnalité métier nouvelle de désignation, et la tuyauterie technique qui va avec.

---

## Périmètre fonctionnel — ce qui change pour l'admin

### Fiche groupe (admin + portail)

Un nouveau champ `<select>` **Formateur** apparaît dans la fiche groupe. Il liste tous les formateurs enregistrés, triés alphabétiquement nom puis prénom. Une option vide « Aucun formateur désigné » permet de laisser le groupe sans formateur.

À l'enregistrement :
- `groups.trainer_id` est mis à jour (référence id-based).
- `groups.trainer_name` est **automatiquement** rempli avec « Prénom Nom » du formateur sélectionné, pour conserver la rétrocompatibilité avec les lectures existantes (merge tags PDF `{session_formateur}`, extranet apprenant qui lit cette colonne).

Si le formateur sélectionné a été supprimé entre-temps, `trainer_id` est silencieusement remis à NULL pour éviter une référence orpheline.

La conservation des données saisies en cas d'erreur formulaire (mécanisme `acdc_consume_form_state`) est désormais alignée sur les autres modules. Avant la 3.20.92, la fiche groupe ne disposait pas de ce mécanisme.

### Fiche session (admin + portail)

Un nouveau champ `<select>` **Formateur** apparaît également sur la fiche session, juste après le champ Entreprise. Il pilote `sessions.trainer_id`, qui est **indépendant** de `groups.trainer_id`. Cette double désignation est volontaire (arbitrage 2b) : elle couvre les sessions sans groupe et permet à l'admin de gérer en conscience.

Le portail formateur fait l'union des deux sources pour « Mes sessions » : un formateur voit une session dès qu'il est désigné soit directement sur la session, soit via un de ses groupes.

---

## Périmètre fonctionnel — ce qui change pour le formateur

### Nouvel onglet « Mes sessions »

Visible dans la nav du portail formateur, entre « Tableau de bord » et « Ma bibliothèque ». L'onglet est **toujours affiché** (arbitrage 1b). C'est la page elle-même qui contrôle la permission `view_own_sessions` :

- Si la permission est désactivée → message clair « Vous n'avez pas l'autorisation d'accéder à cette section ».
- Sinon → vue liste avec deux blocs distincts : **Sessions à venir** et **Sessions passées**.

### Vue liste

Cartes triées du plus proche au plus lointain pour les sessions à venir, du plus récent au plus ancien pour les passées. Chaque carte affiche : titre, formation, période, lieu, entreprise, nombre d'apprenants, statut (pill colorée), et un lien « Voir le détail ».

État vide explicite : « Aucune session à venir pour le moment » avec un message d'aide expliquant le rôle de l'administration.

### Vue détaillée

Bloc 1 — Informations session : formation, entreprise, période, lieu, format, lien distanciel cliquable, statut, notes.

Bloc 2 — Apprenants. La permission `view_session_learners` est requise pour voir la liste. Si désactivée, message clair sans leak d'information.

Si la permission `view_learner_personal_data` est en plus activée, deux colonnes supplémentaires apparaissent dans le tableau apprenants : e-mail et téléphone. Sinon, ces colonnes sont masquées et un message le précise.

Sécurité d'accès : la vue détaillée vérifie strictement que la session appartient au formateur connecté (via `sessions.trainer_id` OU `groups.trainer_id`). Une URL `?session_id=X` arbitraire renvoie un message « Session introuvable ou non autorisée » avec retour à la liste.

---

## Migration BD — automatique et idempotente

Au déploiement, `maybe_upgrade()` exécute dans cet ordre :

1. **Ajout des colonnes** via `maybe_add_table_column` (idempotent) :
   - `wp_acdc_of_groups.trainer_id BIGINT UNSIGNED NULL`
   - `wp_acdc_of_sessions.trainer_id BIGINT UNSIGNED NULL`
2. **Ajout des index** via `maybe_add_table_index` (idempotent) :
   - `KEY trainer_id (trainer_id)` sur les deux tables
3. **Backfill** via `backfill_groups_and_sessions_trainer_ids()` :
   - **Étape 1** — Pour chaque groupe avec `trainer_name` peuplé et `trainer_id` NULL : tentative de match nominatif normalisé (lowercase, sans accents, espaces compactés) avec `first_name + ' ' + last_name` des trainers existants. Match unique → écriture du `trainer_id`. Sinon → laissé NULL et comptabilisé.
   - **Étape 2** — Pour chaque session avec `trainer_id` NULL et un seul groupe associé avec `trainer_id` renseigné : copie du `trainer_id` du groupe vers la session.
4. **Flag** `acdc_of_saas_3_20_92_backfill_done = 1` stocké en option pour ne plus rejouer.
5. **Statistiques** stockées en option `acdc_of_saas_3_20_92_backfill_stats` (clés `groups_matched`, `groups_unresolved`, `sessions_propagated`).

**Si votre base ne contient aucun `trainer_name` peuplé**, le backfill est un noop sans aucun effet. Aucun risque.

**Si votre base contient des `trainer_name` peuplés**, le backfill fait du best-effort. Les cas non résolus (orthographe divergente, formateur supprimé, ambiguïté) restent à NULL et sont visibles dans le compteur. L'admin peut alors ouvrir la fiche groupe correspondante et sélectionner le formateur dans le nouveau `<select>` pour finaliser.

---

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump version `3.20.92`.
- `includes/kernel/class-acdc-kernel-core-trait.php` — 2 `maybe_add_table_column` + 2 `maybe_add_table_index` + appel `backfill_groups_and_sessions_trainer_ids()` depuis `maybe_upgrade()` + nouvelle fonction `backfill_groups_and_sessions_trainer_ids()` + helper privé `acdc_normalize_for_match()`.
- `includes/kernel/class-acdc-kernel-render-trait.php` — refonte `render_front_group_form()` : ajout `<select>` formateur, mécanisme `acdc_consume_form_state`, helpers `$value` / `$field_class` / `$invalid_note` cohérents avec les autres modules.
- `includes/kernel/class-acdc-kernel-actions-trait.php` — extension `handle_save_group()` : sanitisation `trainer_id`, vérification d'existence, auto-update `trainer_name`, conservation d'état en cas d'erreur.
- `includes/sessions/class-acdc-sessions-render-trait.php` — ajout `<select>` formateur dans `render_front_sessions_tab()`.
- `includes/sessions/class-acdc-sessions-actions-trait.php` — extension `handle_save_session()` : sanitisation et persistance `trainer_id`.
- `includes/trainer-portal/render/class-acdc-trainer-portal-render-trait.php` — ajout onglet `'sessions'` dans `$tabs` et `$internal_views`, nouveau `case 'sessions'` au dispatcher, 3 nouvelles fonctions privées : `render_trainer_portal_sessions()`, `render_trainer_portal_sessions_list()`, `render_trainer_portal_session_card()`, `render_trainer_portal_session_detail()`.

**Aucune modification** : autres modules (CRM, prospects, apprenants directs, formations, financeurs, utilisateurs, devis, factures, conventions, contrats, quiz, évaluations, enquêtes), helpers PDF, e-mails transactionnels, extranet apprenant, conformité Qualiopi, paramètres globaux.

---

## Procédure de test (à exécuter après installation)

> **Pré-requis cache** : après installation du ZIP, vider le cache LiteSpeed (admin WP → LiteSpeed Cache → Toolbox → Empty all caches) et recharger les pages admin avec Ctrl+Shift+R.

### A — Migration BD et backfill

1. Installer le ZIP via WordPress (Extensions → Ajouter → Téléverser → Activer).
2. Aller dans phpMyAdmin (ou équivalent) :
   - `wp_acdc_of_groups` doit avoir une colonne `trainer_id BIGINT UNSIGNED NULL`.
   - `wp_acdc_of_sessions` doit avoir une colonne `trainer_id BIGINT UNSIGNED NULL`.
   - Les index `trainer_id` doivent être présents sur les deux tables.
3. Aller dans `wp_options` et chercher `acdc_of_saas_3_20_92_backfill_done` — la valeur doit être `1`.
4. Chercher aussi `acdc_of_saas_3_20_92_backfill_stats` : tableau sérialisé contenant `groups_matched`, `groups_unresolved`, `sessions_propagated`.
5. **Si votre base avait des `groups.trainer_name` peuplés** : vérifier que les groupes pour lesquels le nom matchait clairement un formateur ont bien `trainer_id` rempli, et que les sessions associées ont aussi `trainer_id` rempli.

### B — UI admin fiche groupe

6. Aller dans Groupes → Modifier un groupe existant : un nouveau champ `<select>` **Formateur** est présent, avec les formateurs triés alphabétiquement.
7. Sélectionner un formateur, enregistrer : le groupe est mis à jour avec succès.
8. En BD : `groups.trainer_id` correspond à l'id du formateur choisi, `groups.trainer_name` contient « Prénom Nom » du formateur.
9. Repasser le `<select>` sur « Aucun formateur désigné », enregistrer : `trainer_id` revient à NULL, `trainer_name` est vidé.
10. Vider le champ Nom (obligatoire) et tenter d'enregistrer : message d'erreur « Le nom du groupe est obligatoire ». Au retour sur le formulaire, vérifier que le formateur sélectionné, le commentaire et tous les autres champs saisis sont **conservés**.

### C — UI admin fiche session

11. Aller dans Sessions → Modifier une session existante : nouveau `<select>` **Formateur** présent juste après le champ Entreprise.
12. Sélectionner un formateur, enregistrer.
13. En BD : `sessions.trainer_id` correspond à l'id du formateur choisi.
14. Vérifier que ce trainer_id est bien **indépendant** du `groups.trainer_id` du groupe associé (vous pouvez les définir différemment, c'est volontaire).

### D — Onglet « Mes sessions » du portail formateur

15. Préparer un scénario : créer ou ouvrir un formateur, l'inviter au portail (ou utiliser un compte existant), s'y connecter via `/extranet-formateur/`.
16. Vérifier que l'onglet « **Mes sessions** » est présent dans la nav, juste après « Tableau de bord ».
17. Cliquer dessus.

### E — Permission `view_own_sessions`

18. Côté admin, ouvrir la fiche du même formateur, passer en profil **Personnalisé**, **décocher** « Voir ses sessions » uniquement, enregistrer.
19. Recharger le portail formateur, onglet « Mes sessions » : la page affiche le message « Vous n'avez pas l'autorisation d'accéder à cette section. ». L'onglet reste visible (arbitrage 1b validé).
20. Réactiver la permission, recharger.

### F — Vue liste

21. Si le formateur n'a aucune session associée : les deux blocs « À venir » et « Passées » affichent le message d'état vide explicite.
22. Côté admin, rattacher le formateur à au moins un groupe ayant `session_id` ou directement à une session (via les `<select>` ajoutés dans cette version).
23. Recharger le portail : la session apparaît dans le bon bloc selon sa date (à venir ou passée).
24. Cliquer sur le titre ou « Voir le détail » → bascule sur la vue détaillée.

### G — Vue détaillée — informations session

25. Vérifier que les informations session s'affichent : formation, entreprise, période, lieu, format, lien distanciel (cliquable et ouvrant un nouvel onglet), statut, notes.

### H — Vue détaillée — apprenants et permissions

26. Si la session a des apprenants (table `wp_acdc_of_learners` avec `session_id` matchant) : ils s'affichent dans le tableau, triés par nom de famille.
27. Avec le profil par défaut **Simple** (qui n'a pas `view_learner_personal_data` par défaut) : seules les colonnes Nom, Prénom et Statut sont visibles. Un message le précise au-dessus du tableau.
28. Côté admin, basculer la permission `view_learner_personal_data` pour ce formateur (profil Personnalisé), recharger : les colonnes E-mail et Téléphone apparaissent.
29. Côté admin, désactiver `view_session_learners` (toujours en Personnalisé), recharger la vue détaillée : le bloc Apprenants affiche un message « Vous n'avez pas l'autorisation… » au lieu du tableau. Le bloc Informations session reste visible.

### I — Sécurité de l'accès direct

30. Tenter d'accéder à une session qui **n'est pas** rattachée au formateur connecté en construisant l'URL manuellement : `/extranet-formateur/?view=sessions&session_id=999` (avec un id dont le formateur n'est ni `sessions.trainer_id` ni présent dans `groups.trainer_id` correspondant). La page doit afficher « Session introuvable ou non autorisée » avec retour à la liste, sans leak d'information.

### J — Anti-régression

31. Vérifier que les autres onglets du portail formateur (Tableau de bord, Ma bibliothèque, Mes disponibilités, Mon profil) **fonctionnent exactement comme avant**.
32. Vérifier que la fiche formateur admin (édition, profil, calendrier disponibilités, matrice de permissions livrée en 3.20.91) **fonctionne sans changement**.
33. Vérifier que les merge tags PDF `{session_formateur}` continuent de produire le bon nom (ils lisent `groups.trainer_name` qui est désormais auto-rempli côté handler save).
34. Vérifier que la liste des sessions admin (avec son filtre de recherche par formateur en chaîne libre) fonctionne toujours — elle continue d'utiliser `groups.trainer_name`.

---

## Points de vigilance

- **Indépendance volontaire `groups.trainer_id` vs `sessions.trainer_id`** (arbitrage 2b validé). Le save d'un groupe ne touche pas `sessions.trainer_id`, et inversement. C'est l'admin qui pilote en conscience. Le portail formateur fait l'union des deux sources, donc un formateur voit la session dès qu'il est désigné via l'un ou l'autre canal.
- **Auto-update de `groups.trainer_name`** : quand un formateur est désigné via le `<select>`, son nom est sérialisé dans `trainer_name` au format « Prénom Nom ». Cela conserve la rétrocompatibilité avec les merge tags PDF et l'extranet apprenant qui lisent toujours cette colonne. Si le nom du formateur est ensuite modifié dans sa fiche, `trainer_name` n'est pas automatiquement re-synchronisé sur les groupes existants — il faudrait une passe manuelle ou un patch ultérieur dédié.
- **Backfill non-rejouable par défaut** : le flag `acdc_of_saas_3_20_92_backfill_done` empêche de rejouer le backfill. Si nécessaire (ex. après ajout massif de formateurs en BD), supprimer cette option puis bumper artificiellement la version pour relancer `maybe_upgrade()`. Procédure documentable dans un patch ultérieur si besoin.
- **Cache LiteSpeed** : après upgrade, vider le cache plugin et recharger en Ctrl+Shift+R. Les fichiers JS et CSS ne sont pas modifiés sur disque (les nouveaux styles sont inline dans les fonctions de rendu), donc le rafraîchissement passe surtout par la sortie de cache page.

---

## Prochaine étape logique

**3.20.93 — Alertes Qualiopi automatiques** : e-mails J-30 et J-7 sur expirations URSSAF, RC pro, certifications. Cron WP planifié, template e-mail ACDC officiel, page admin de configuration des seuils. C'est la dernière brique pour boucler le module Formateur côté conformité.
