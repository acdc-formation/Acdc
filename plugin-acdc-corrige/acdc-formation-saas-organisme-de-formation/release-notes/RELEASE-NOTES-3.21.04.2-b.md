# Release Notes — 3.21.04.2-b

**Date** : 28 avril 2026  
**Périmètre** : Module Quiz Live — Raffinement UX (feedback, révélation, sons, transitions)

---

## Nouveautés

### 1. Feedback immédiat côté apprenant (Player)

Dès qu'un apprenant soumet sa réponse, l'écran bascule immédiatement en feedback — sans attendre que tous les participants aient répondu.

- ✅ **Bonne réponse** : carte verte, icône ✅, nombre de points gagnés en doré (+750 pts)
- ❌ **Mauvaise réponse** : carte rose, icône ❌, +0 pt
- ✏️ **Question ouverte** (texte libre) : card neutre, message "correction manuelle par le formateur"
- Score total mis à jour dans le sous-texte

### 2. Sons Web Audio

Sons synthesisés nativement via l'API Web Audio (aucun fichier audio ajouté au ZIP).

| Contexte | Son | Description |
|---|---|---|
| Bonne réponse (player) | `correct` | Mélodie ascendante do-mi-sol |
| Mauvaise réponse (player) | `wrong` | Son descendant sawtooth |
| Podium final (host + player) | `podium` | Fanfare do-mi-sol-do |
| Révélation des résultats (host) | `reveal` | Plong triangle descendant |

Les sons se déclenchent sans interaction préalable (API Audio Context créé à la volée).

### 3. Révélation enrichie côté host

L'écran de révélation affiche désormais — quand les données sont disponibles (i.e. `all_answered = true`) :

- Coloration **verte** pour la bonne réponse, **rouge atténuée** pour les mauvaises
- Badge **✓** sur la bonne réponse
- **Barre de progression animée** indiquant le % de participants ayant choisi chaque réponse
- Compteur : `N (X%)`

Les barres s'animent en `0% → X%` via une transition CSS de 0.7s (cubic-bezier).

### 4. Transitions fade-in entre états

Chaque basculement d'état (lobby → question → révélation → podium) déclenche une animation `fadein` de 0.3s (opacity + translateY léger). Applicable à host et player.

---

## Fichiers modifiés

| Fichier | Type | Nature de la modification |
|---|---|---|
| `includes/quizzes/class-acdc-quizzes-engine-trait.php` | PHP | `get_qz_live_state()` : ajout de `reveal_answers` (is_correct + count + percent) quand `all_answered` |
| `assets/js/quizzes-live-host.js` | JS | `renderReveal()` enrichi + son révélation + son podium |
| `assets/js/quizzes-live-player.js` | JS | `showFeedbackNow()` immédiat + sons correct/wrong/podium |
| `assets/css/quizzes-live.css` | CSS | Styles reveal (is-reveal-correct, barres), feedback enrichi, fade-in |
| `includes/quizzes/class-acdc-quizzes-render-trait.php` | PHP | Ajout de `<p id="acdc-qz-player-feedback-sub">` |

---

## Points techniques

- `reveal_answers` est injecté dans `current_question` uniquement quand `all_answered = true` → zéro impact sur les polls normaux (question encore ouverte)
- Comptage des votes : fetch PHP de `player_answers.answer_ids_json` + décodage en mémoire → pas de dépendance à `JSON_CONTAINS` (compatibilité MySQL 5.6+)
- Le son "reveal" côté host se déclenche une seule fois par question (protégé par `state.hasShownReveal`)
- `state.soundEnabled = true` → si besoin d'une option de désactivation côté player, prévu pour une version suivante

---

## Prochaine étape — 3.21.04.3 : Anti-triche dur

- Verrou `current_question_id` côté serveur (empêche la soumission sur une ancienne question)
- Blocage navigation arrière
- Détection tab-switch avec compteur dans le rapport
- Choix manuel / timer par quiz (champ `delivery_pace`)
