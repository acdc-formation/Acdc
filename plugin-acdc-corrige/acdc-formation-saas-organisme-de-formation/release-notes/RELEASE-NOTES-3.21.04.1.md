# 3.21.04.1 — Écrans de résultats détaillés (Qualiopi)

**Date :** 27 avril 2026
**Périmètre :** module Quizzes / Tests / Évaluations — phase 3.21.04 partie 1 sur 2.

---

## Ce que cette version apporte

Cette livraison ajoute **les écrans de consultation des résultats** pour tout quiz envoyé à des apprenants, à la fois côté wp-admin et côté portail formateur. C'est le maillon qui manquait entre l'envoi (livré en 3.21.03.1) et l'exploitation pédagogique : sans ces écrans, on envoyait un quiz mais on ne pouvait pas voir les retours.

Cinq vues sont fournies, en miroir entre admin et portail formateur :

1. **Liste des envois** — un tableau de tous les envois avec quiz, formation, date, nombre de participants et score moyen.
2. **Détail d'un envoi : participants** — qui a répondu, son statut, sa durée, son score.
3. **Détail d'un envoi : par question** — taux de réussite question par question, pour identifier celles qui posent problème pédagogiquement.
4. **Détail d'un envoi : par objectif pédagogique** — score moyen sur chaque compétence, comparé au seuil de réussite défini dans l'éditeur de quiz. **Critique pour Qualiopi indicateur 12** (atteinte des objectifs pédagogiques).
5. **Détail individuel d'un apprenant** — chaque question avec sa réponse, comparée à l'attendu (QCM, V/F, Puzzle, réponse libre).

## Correction manuelle des réponses libres

Pour les questions de type « réponse libre » (textarea), le formateur dispose d'un mini-formulaire sous chaque réponse :

- Validation binaire (✓ Validé / ✗ Non validé)
- Attribution de points entre 0 et le maximum de la question
- **Recalcul automatique du score total** du participant après chaque correction
- **Trace dans le journal Qualiopi** (`event_type = manual_grading`) avec l'identifiant du correcteur

Ce flux est accessible depuis le wp-admin pour les administrateurs et depuis le portail formateur pour les utilisateurs ayant la permission `dispatch_quiz`.

## Export CSV

Bouton **« ⬇ Exporter en CSV »** sur l'écran de détail d'un envoi. Le fichier généré contient :

- Une ligne par participant
- Colonnes : nom complet, e-mail, statut, dates (invité, démarré, terminé), durée en minutes, score brut, score en pourcentage
- Une colonne **par question** avec la réponse formatée (texte, choix sélectionnés, ordre puzzle) et un indicateur correct / faux / non noté

Format : CSV séparateur `;` avec BOM UTF-8 pour ouverture directe dans Excel et LibreOffice. Nom de fichier : `resultats-{slug-quiz}-{YYYY-MM-DD}.csv`.

## Architecture technique

**Méthodes Core ajoutées** (`class-acdc-quizzes-core-trait.php`) :

- `get_qz_dispatch_sessions( $args )` — listage avec filtre formateur et fallback à 2 niveaux
- `get_qz_dispatch_session( $id )` — fiche enrichie avec stats agrégées
- `can_qz_trainer_view_dispatch_session( $session_id, $trainer_id )` — contrôle d'accès avec fallback
- `get_qz_participants_for_dispatch_session( $session_id )`
- `get_qz_answers_by_participant( $participant_id )` — indexé par question_id
- `get_qz_results_by_question( $session_id )` — taux de réussite agrégés
- `get_qz_results_by_objective( $session_id )` — score moyen par objectif + indicateur is_passing
- `update_qz_manual_grading( $participant_id, $question_id, $is_correct, $score, $grader )` — avec recalcul + journal
- `recompute_qz_participant_score( $participant_id )` — agrégation post-correction
- `export_qz_session_results_csv( $session_id )` — CSV UTF-8 BOM
- `get_qz_answers_for_question_public( $question_id )` — wrapper public de la version privée existante

**Nouveaux traits Render** :

- `class-acdc-quizzes-render-results-trait.php` — 5 écrans côté wp-admin
- `class-acdc-trainer-portal-results-render-trait.php` — 5 écrans côté portail formateur, branchés via les hooks d'extensibilité posés en 3.21.03.2

**Nouveaux handlers admin-post** :

- `acdc_of_qz_grade_open_answer` — correction manuelle (perm `dispatch_quiz`)
- `acdc_of_qz_export_results` — téléchargement CSV (perm `dispatch_quiz`)

**Routing admin** : aiguillage `?view=results` (constante `ACDC_OF_QZ_VIEW_RESULTS = 'results'` qui était définie depuis 3.21.00 et enfin utilisée).

**Sidebar portail formateur** : nouvel onglet « Résultats » entre « Mes quiz » et « Ma bibliothèque ».

## Points de recette

**Côté wp-admin :**

1. Admin → Quizzes → Tests / Évaluations → bouton « 📊 Résultats »
2. Liste affiche les envois testés en 3.21.03.2 (le quiz d'évaluation envoyé)
3. Cliquer sur un envoi → onglets Participants / Par question / Par objectif
4. Cliquer sur « Détail » d'un participant → ses réponses question par question
5. Pour une réponse libre : utiliser le formulaire de correction → vérifier que le score se recalcule
6. Bouton « ⬇ Exporter en CSV » → fichier téléchargé, ouvre dans Excel avec accents corrects (BOM)

**Côté portail formateur :**

1. Connexion sur le portail formateur
2. Sidebar gauche → onglet « Résultats »
3. Liste des envois pour les formations qu'on anime (avec fallback automatique si trainer_id pas propagé)
4. Mêmes 5 écrans qu'en admin, scopés au formateur
5. Si on tente d'accéder à un envoi qu'on ne devrait pas voir → message d'accès refusé propre

**Cas spécifiques à tester :**

- Onglet « Par objectif » sur un quiz **sans objectifs définis** → message d'invitation à en définir, pas de crash
- Réponse libre **sans correction préalable** → badge « ⏳ À corriger » visible
- Export CSV d'un envoi **sans aucun participant complété** → fichier généré avec en-têtes seulement
- Quiz de type **Puzzle** : vérifier que l'ordre saisi par l'apprenant et l'ordre attendu sont bien comparés visuellement (vert / rouge selon position)

## Ce qui n'est PAS dans cette version

- **Anti-triche en mode synchrone** (live) : reporté en 3.21.04.2. Concerne uniquement les quiz live avec PIN, pas les envois async traités ici.
- **Notifications par e-mail au formateur** quand un apprenant termine un quiz : pas demandé pour l'instant.
- **Comparaison entre cohortes** (session A vs session B sur le même quiz) : possible plus tard si besoin.

## Compatibilité

- Aucune migration de base de données : utilise uniquement les tables existantes (`acdc_of_qz_sessions`, `acdc_of_qz_participants`, `acdc_of_qz_player_answers`, `acdc_of_qz_questions`, `acdc_of_qz_objectives`, `acdc_of_qz_logs`)
- Pas de modification du moteur de jeu : aucun risque sur les passations en cours
- PDF convention figée respectée : non touchée
