# ACDC Formation SAAS — Release notes 3.21.01.1

**Date** : 27 avril 2026
**Slug** : `acdc-formation-saas-organisme-de-formation`
**Périmètre** : correctif d'aiguillage de la 3.21.01.

---

## Pourquoi cette sous-sous-version

La 3.21.01 a livré un éditeur de quiz fonctionnel, mais relié aux mauvaises pages — celles de wp-admin (`/wp-admin/admin.php?page=acdc-of-quiz`), alors que ton workflow réel passe par l'extranet (`/extranet/tableau-de-bord/?tab=quiz`). Résultat : l'éditeur ne s'affichait nulle part dans ton parcours quotidien.

La 3.21.01.1 corrige cet aiguillage en intégrant le nouveau moteur **directement dans la sidebar de l'extranet**, à côté du moteur legacy qui reste visible le temps de la phase de mise au point.

---

## Ce qui change pour l'utilisateur

Une nouvelle entrée parente apparaît dans la sidebar de l'extranet, juste **en-dessous** du groupe legacy « Quiz / Enquêtes / Éval. » :

**Quiz / Test / Évaluation**
- Quiz live
- Tests de positionnement
- Évaluations des acquis

Chaque sous-entrée ouvre l'écran de liste du nouveau moteur (cartes, boutons « + Créer » et « Dupliquer depuis… », filtres formation/statut). Toute la mécanique livrée en 3.21.01 (création, éditeur 3 zones, modale paramètres 4 onglets, modale objectifs, drag & drop, autosave AJAX, dupliquer, archiver, supprimer) est désormais accessible.

L'ancien moteur « Quiz / Enquêtes / Éval. » **reste pleinement opérationnel** à côté. Tu peux comparer les deux interfaces côte à côte pendant les sous-versions de mise au point. Une fois la 3.21.14 livrée, le legacy sera masqué (pas supprimé : juste retiré de la sidebar).

---

## Ce qui change techniquement

### Hooks ajoutés au kernel (2 lignes)

Fichier `includes/kernel/class-acdc-kernel-render-trait.php`. Aucune logique du kernel n'est modifiée, seulement deux points d'extension légitimes ajoutés :

1. **`apply_filters( 'acdc_portal_navigation_groups', $groups, $current_tab )`** dans la méthode `render_portal_navigation()`, juste après la définition du tableau de groupes. Permet aux modules d'ajouter, masquer ou réordonner les entrées de la sidebar.

2. **`apply_filters( 'acdc_portal_render_unknown_tab', false, $tab, $action, $item_id )`** dans le `default` du switch principal de `render_portal_shortcode()`. Permet aux modules de prendre la main sur le rendu d'un tab que le kernel ne connaît pas, plutôt que de retomber automatiquement sur le tableau de bord.

Ces deux points d'extension sont **strictement non-invasifs** : sans handler enregistré, le comportement du kernel est rigoureusement identique à la 3.21.00. Ils ouvrent simplement la voie aux modules métier pour s'y greffer proprement, sans surcharger de méthodes privées.

### Annulation des modifications kernel obsolètes de la 3.21.01

Les 2 changements de visibilité (`private` → `protected`) sur `render_admin_portal_wrapper()` et `render_admin_portal_content()` sont **annulés**. Le kernel retrouve son état 3.21.00 strict, plus 2 lignes de hooks. Les 9 lignes `insteadof` ajoutées dans `class-acdc-plugin.php` sont également retirées : elles n'ont plus aucun rôle, le wiring passe désormais par les hooks.

### Adaptation du module Quizzes

Le trait `Actions` enregistre maintenant deux nouveaux filtres :
- `add_filter( 'acdc_portal_navigation_groups', ... )` injecte l'entrée parente « Quiz / Test / Évaluation » dans la sidebar.
- `add_filter( 'acdc_portal_render_unknown_tab', ... )` rend le contenu des 3 tabs `qz_live`, `qz_positioning`, `qz_assessment`.

Le trait `Render` ne surcharge plus aucune méthode du kernel. Sa méthode publique `render_qz_extranet_screen( $purpose )` est appelée depuis le hook `acdc_portal_render_unknown_tab` et rend uniquement la zone `<main>` (le wrapper portal-shell, la sidebar et la topbar sont déjà rendus par le kernel).

Les URLs internes (boutons, redirections, formulaires) générées par `qz_admin_url()` pointent désormais vers `portal_page_url( ['tab' => 'qz_live', ...] )` au lieu de `admin_url( 'admin.php?page=acdc-of-quiz' )`. Le moteur reste 100 % côté extranet.

Les 5 traits du module Quizzes (Core, Actions, Engine, Render, Render Editor) totalisent **3 933 lignes**. Tout le code livré en 3.21.01 (handlers CRUD, endpoints AJAX, modales, drag & drop, autosave, duplication, archivage, suppression) est conservé tel quel.

### Assets

Les assets `assets/css/quizzes-admin.css` et `assets/js/quizzes-editor.js` sont chargés via `wp_enqueue_scripts` (front-office) et non plus via `admin_enqueue_scripts` (back-office). La détection se fait sur `?tab=qz_*` côté front.

---

## Scénario de recette en 8 étapes

Beaucoup plus court que le précédent puisque l'éditeur lui-même n'a pas changé.

**Étape 1 — Installation.**
Installe le ZIP `3.21.01.1` par-dessus la 3.21.01 (ou la 3.21.00 si la 3.21.01 a été désinstallée). Vide le cache LiteSpeed et rafraîchis le navigateur (Ctrl+Maj+R). Vérifie la version dans Extensions : doit être **3.21.01.1**.

**Étape 2 — Apparition de la nouvelle entrée sidebar.**
Va sur `/extranet/tableau-de-bord/`. Dans la sidebar de gauche, cherche le groupe « Quiz / Test / Évaluation ». Il doit apparaître **juste en-dessous** de l'ancien groupe « Quiz / Enquêtes / Éval. » legacy (qui reste visible). Vérifie que les 3 sous-entrées « Quiz live », « Tests de positionnement » et « Évaluations des acquis » sont présentes.

**Étape 3 — Ouverture du nouveau moteur.**
Clique sur « Quiz live ». Tu dois voir la nouvelle interface :
- Fil d'Ariane « Quiz live » en haut.
- Titre « Quiz live ».
- Boutons « + Créer » et « Dupliquer depuis… » à droite.
- Bandeau de filtres Formation / Statut.
- État vide « Aucun quiz pour le moment » (la nouvelle table `acdc_of_qz_*` est vide à ce stade).

**Étape 4 — Création d'un quiz test.**
Clique sur « + Créer ». La modale s'ouvre. Saisis le titre `TEST 3.21.01.1`, choisis une formation, valide. Tu dois être redirigé vers l'éditeur 3 zones avec un message vert « Quiz créé. »

**Étape 5 — Ajout d'une question.**
Clique sur « + Ajouter une question ». Une question vide doit apparaître. Tape un énoncé, modifie les 2 réponses pré-remplies, coche la bonne. Patiente 1 seconde — l'indicateur en haut affiche « Enregistrement… » puis « Enregistré ».

**Étape 6 — Modales paramètres et objectifs.**
Clique sur « ⚙ Paramètres ». La modale s'ouvre avec 4 onglets (Général / Scoring / Anti-triche / RGPD). Navigue, modifie un seuil, valide. Clique sur « Objectifs (0) ». Ajoute un objectif `Compétence test`, seuil 70, enregistre. Le bouton doit afficher « Objectifs (1) » au prochain rechargement.

**Étape 7 — Retour liste et menu ⋯.**
Clique sur « Fermer » en haut à droite. Tu reviens à la liste. Sur la carte du quiz, clique sur « ⋯ ». Le menu déroulant doit afficher Modifier / Dupliquer dans cette formation / Voir les résultats (grisé) / Lancer en live (grisé) / Archiver / Supprimer.

**Étape 8 — Tests de positionnement et Évaluations des acquis.**
Va sur `Tests de positionnement` puis `Évaluations des acquis` (dans le nouveau groupe). Vérifie que chacun affiche son propre titre et est filtré par finalité (les quiz créés en « live » ne doivent pas apparaître ici, et inversement). Crée un quiz test sur chacun, vérifie que la modale de création propose le bon mode de passation par défaut (asynchrone pour positionnement, choix asynchrone/synchrone pour évaluations).

---

## Points de vigilance

**Le legacy reste visible.** C'est volontaire pour permettre la comparaison côte à côte. Les anciennes pages legacy (`?tab=quiz`, `?tab=positioning_tests`, `?tab=evaluations`) continuent à fonctionner et à afficher l'ancien éditeur. Les nouvelles pages sont sur `?tab=qz_live`, `?tab=qz_positioning`, `?tab=qz_assessment`.

**Les deux moteurs travaillent sur des tables séparées.** Aucun risque de mélange : le legacy utilise `acdc_of_quizzes`, le nouveau utilise `acdc_of_qz_quizzes`. Si tu crées un quiz dans l'un, il n'apparaîtra pas dans l'autre — c'est le comportement attendu.

**Cache LiteSpeed.** Comme toujours, vide le cache après installation. La nouvelle entrée sidebar peut apparaître seulement après vidage.

---

## Rollback

Pour revenir en arrière, deux niveaux de retour :

1. **Retour code complet** : réinstaller le ZIP 3.21.00 par-dessus. Les nouvelles tables `acdc_of_qz_*` restent en BDD mais inertes. Les 2 hooks kernel disparaissent automatiquement.
2. **Désactivation du nouveau moteur uniquement** : commenter les deux `add_filter` dans `register_quizzes_module_hooks()` du fichier `includes/quizzes/class-acdc-quizzes-actions-trait.php`. Le module Quizzes reste actif (cron, BDD), mais sa nouvelle entrée disparaît de la sidebar et le legacy reprend son rôle exclusif.

---

## À suivre

Prochaine sous-version **3.21.02** : versioning et verrouillage. Une fois un quiz utilisé pour une session réelle, il bascule en lecture seule et toute modification ultérieure crée automatiquement une nouvelle version. Indispensable pour la traçabilité Qualiopi.
