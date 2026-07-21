# ACDC Formation SAAS — Release notes 3.21.04.2-a

**Date :** 28 avril 2026
**Version précédente :** 3.21.04.1-hotfix5
**Type de livraison :** Sous-version (moteur de jeu live — étape 1/2)
**Périmètre :** Module Quizzes — quiz live synchrone

---

## Ce que cette version apporte

C'est la **première moitié du moteur de jeu live**. Cette livraison se concentre sur le **moteur fonctionnel complet** : un formateur peut lancer un quiz en direct, partager un PIN à ses apprenants, ils rejoignent depuis leur téléphone, jouent ensemble, voient le podium animé en fin de partie. La seconde moitié (3.21.04.2-b) raffinera les détails UX. La couche anti-triche viendra en 3.21.04.3.

### Pour le formateur

Sur la fiche d'un quiz live actif, un nouveau bouton apparaît : **« ▶ Lancer en live »**. Un clic, la session se crée et il bascule sur la page de pilotage pleine page :

- À gauche : un **QR code** que les apprenants scannent (ou le PIN à 7 chiffres qu'ils saisissent manuellement) ;
- Au centre : le **nom du quiz** affiché en grand, prêt à être projeté ;
- À droite : la **liste des participants** qui rejoignent en temps réel (rafraîchissement toutes les 1,5 s) ;
- En bas : footer marine avec le PIN rappelé en doré, le compteur de participants et un bouton plein écran.

Une fois prêt, il clique sur **« Commencer »**. La première question s'affiche en grand format Kahoot-like : tuiles colorées (rose, bleu, orange, vert), timer compte à rebours, compteur d'apprenants ayant répondu. Quand tout le monde a répondu (ou que le timer expire), la révélation s'affiche automatiquement et il passe à la question suivante d'un clic.

À la fin, **podium animé** : le 3e apparaît à T+1,5 s, le 2e à T+3,5 s, le 1er à T+5,5 s, accompagné d'un feu d'artifice (canvas-confetti). Les illustrations homme/femme manga que tu m'as fournies sont posées sur les marches dorée, argent, bronze. Le classement complet (positions 4 à 10) apparaît juste en dessous.

### Pour l'apprenant

Il scanne le QR ou tape le PIN sur son téléphone. Une page d'accueil pleine page lui demande son **pseudo** et le laisse choisir son **avatar (homme ou femme)**. Il rejoint, attend le démarrage, puis joue : tuiles colorées, timer, soumission instantanée. Le verrou est **souple** — il peut corriger sa réponse tant que la question est ouverte (l'anti-triche dur viendra en 3.21.04.3).

À la fin, il voit le **même podium animé** que le formateur, plus une carte **« Ta position »** qui affiche son rang personnel (par exemple #7) et son score total — qu'il soit dans le top 3 ou non.

### Score Kahoot-like

Réponse correcte instantanée : 1000 points. La valeur décroît linéairement vers 500 points si la réponse arrive juste avant la fin du timer. Sans timer (mode manuel), c'est binaire : 1000 ou 0. Réponse fausse : 0 dans tous les cas.

### Retardataires

Un apprenant qui rejoint après le démarrage peut quand même participer aux questions restantes. Son score commence à 0 (les questions déjà passées comptent comme non répondues). C'est tracé en base.

---

## Décisions techniques

**Architecture des écrans.** Pleine page sans header de thème, via le filtre `template_include` existant qui sert un template autonome (`standalone-public-page.php`). Le filtre couvre maintenant trois pages : passation async, player live et **host live** (nouveau).

**Pages WordPress auto-créées.**

- `/acdc-quiz-live/` (existait déjà) — shortcode `[acdc_qz_live_player]` ;
- `/acdc-quiz-live-host/` (nouveau) — shortcode `[acdc_qz_live_host]`.

Les deux sont créées au boot via les fonctions `ensure_quiz_live_public_page()` et `ensure_quiz_live_host_page()` hookées sur `init` priorité 11.

**Polling.** 1,5 s côté host comme côté player. Le serveur retourne l'état complet de la session (statut, question courante, participants, scores) à chaque appel. Pas de WebSocket — sur l'hébergement N0C/LiteSpeed la latence reste imperceptible et la complexité reste basse.

**Persistance pseudo.** L'apprenant garde sa session via `localStorage` côté client + un `secure_token` côté serveur. S'il rafraîchit la page ou rouvre l'onglet, il reprend là où il était.

**QR code.** Généré côté JavaScript par la lib **qrcode-generator** v1.4.4 (Kazuhiko Arase, MIT) embarquée localement dans `assets/js/vendor/`. Pas de dépendance PHP, pas d'appel à un service externe.

**Feu d'artifice.** Lib **canvas-confetti** v1.9.3 (MIT) embarquée localement. Quatre tirs successifs aux origines variées (centre, gauche, droite, centre) avec la palette ACDC (marine, doré, crème).

**Mapping podium.** Les 6 illustrations PNG transparentes que tu as fournies sont placées dans `assets/images/podium/` avec un naming propre (`1ere-femme.png`, `1ere-homme.png`, etc.). Leurs URLs sont injectées dans `window.acdcQzPodiumAssets` via `wp_add_inline_script`, et le JS les pioche selon le rang et l'avatar choisi par l'apprenant.

**Scoring.** Helper `qz_calculate_kahoot_score($is_correct, $response_time_ms, $time_limit_seconds)` dans Engine. Réponse fausse → 0. Sans timer → 1000 (correct) ou 0. Avec timer → 1000 à la 0e ms, 500 à la fin, linéaire entre les deux.

**Verrou souple.** `ajax_acdc_of_qz_player_submit_answer` fait un UPSERT (UPDATE si la `player_answer` existe pour ce participant + cette question, INSERT sinon). Tant que la question est active côté serveur, le participant peut envoyer plusieurs fois — seule la dernière réponse compte. Le score total est recalculé à chaque soumission via `recompute_qz_participant_score()`.

**Révélation automatique.** Le helper `qz_check_all_answered($session_id)` compare le nombre de participants actifs au nombre de réponses uniques sur la question courante. Quand il retourne `true`, le client bascule en état révélation. Le serveur ne pousse rien — c'est le polling client qui détecte la transition.

---

## Architecture & fichiers

### Nouveaux fichiers

| Fichier | Rôle |
|---|---|
| `includes/quizzes/class-acdc-quizzes-render-live-trait.php` | Trait dédié au rendu des pages live (host + helpers) |
| `assets/js/quizzes-live-host.js` | Logic formateur : polling, state machine, podium animé, QR |
| `assets/js/quizzes-live-player.js` | Logic apprenant : join, polling, soumission, podium |
| `assets/css/quizzes-live.css` | CSS dédié aux deux écrans live |
| `assets/js/vendor/canvas-confetti.min.js` | Lib feu d'artifice (MIT, embarquée) |
| `assets/js/vendor/qrcode-generator.min.js` | Lib QR (MIT, embarquée) |
| `assets/images/podium/[1-3]eme-[femme/homme].png` | 6 illustrations podium |

### Fichiers modifiés

| Fichier | Changement |
|---|---|
| `includes/quizzes/class-acdc-quizzes-engine-trait.php` | +9 méthodes du moteur live (création, démarrage, avancement, scoring, leaderboard) |
| `includes/quizzes/class-acdc-quizzes-actions-trait.php` | +9 handlers AJAX live + handler `launch_live` + enqueue assets live + helper templates |
| `includes/quizzes/class-acdc-quizzes-core-trait.php` | +`ensure_quiz_live_host_page()` |
| `includes/quizzes/class-acdc-quizzes-render-trait.php` | Stub player remplacé par vrai écran live (5 états) |
| `includes/quizzes/class-acdc-quizzes-render-editor-trait.php` | +bouton « ▶ Lancer en live » sur la fiche d'un quiz live actif |
| `includes/class-acdc-plugin.php` | Composition du nouveau trait + hooks init |
| `includes/class-acdc-quizzes.php` | Manifest des traits |
| `acdc-formation-saas-organisme-de-formation.php` | Trait check + bump version |

---

## Schéma BDD

**Aucune migration.** Toutes les colonnes nécessaires existaient déjà :

- Table `sessions` : `current_question_id`, `current_question_started_at`, `pin_code`, `lobby_opened_at`, `delivery_mode`, `host_id` ;
- Table `participants` : `avatar`, `nickname`, `secure_token`, `total_score` ;
- Table `player_answers` : tout ce qui sert au scoring.

La version de schéma `acdc_of_qz_db_version` reste à `1.0.0`.

---

## Ce qui n'est PAS dans cette livraison

À préserver en tête pour la suite :

- **Anti-triche dur** (verrou serveur sur `current_question_id`, blocage retour arrière, détection tab switch). Reportée explicitement en 3.21.04.3.
- **Choix manuel/timer au lancement.** Pour l'instant, l'avancement se fait soit auto (tous ont répondu), soit manuel (clic du formateur). Le toggle « par timer uniquement » viendra avec l'anti-triche.
- **Révélation détaillée par apprenant.** L'apprenant voit « Réponse enregistrée » et son score actuel, pas le détail bonne/mauvaise réponse. Demande une requête supplémentaire — ajout en 3.21.04.2-b.
- **Sons et transitions raffinées.** Reportées en 3.21.04.2-b (raffinement UX).

---

## Ce qu'il faut tester en priorité

1. **Cycle complet sur un seul navigateur** : ouvrir l'URL formateur dans un onglet, l'URL apprenant dans un second, scanner le QR avec le téléphone. Vérifier que le PIN est bien à 7 chiffres et que la liste des participants se rafraîchit côté formateur.
2. **Démarrage** : clic « Commencer » → la première question apparaît côté formateur ET côté apprenant simultanément.
3. **Soumission** : un apprenant clique une réponse → le compteur « X/Y ont répondu » s'incrémente côté formateur.
4. **Révélation automatique** : tous les apprenants répondent → bascule auto en révélation.
5. **Avancement manuel** : clic « Question suivante → » → la question 2 apparaît partout.
6. **Retardataire** : ouvrir un 3e onglet apprenant après le démarrage avec un autre pseudo → vérifier qu'il rejoint bien la session en cours et reçoit la question courante.
7. **Fin de partie** : dernière question répondue → bascule auto sur le podium → animation séquentielle 3e/2e/1er + confetti.
8. **Vider le cache LiteSpeed** avant chaque test sinon CSS/JS obsolète persiste (réflexe N0C).

---

## Points d'attention pour le formateur

- **L'URL formateur s'ouvre dans un nouvel onglet** quand on clique « ▶ Lancer en live » (pour qu'il puisse garder son back-office ouvert).
- **Plein écran F11** ou bouton plein écran en bas à droite du footer recommandé en présentiel.
- **PIN unique de session** — chaque session live a son propre PIN à 7 chiffres, regénéré à chaque lancement. Pas de réutilisation possible.
- **Pseudo unique par session** — un apprenant ne peut pas prendre un pseudo déjà utilisé dans cette session live (le serveur retourne 409 et le client affiche « Ce pseudo est déjà pris »).

---

## Compatibilité

- WordPress 6.4+ (testé jusqu'à 6.7).
- PHP 7.4 → 8.3.
- Hébergement PlanetHoster N0C / LiteSpeed : vider le cache après déploiement.
- Astra/Elementor : la page bypass complètement le thème via `template_include`, donc pas de conflit.
