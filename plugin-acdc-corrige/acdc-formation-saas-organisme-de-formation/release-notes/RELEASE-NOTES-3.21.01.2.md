# ACDC Formation SAAS — Release notes 3.21.01.2

**Date** : 27 avril 2026
**Slug** : `acdc-formation-saas-organisme-de-formation`
**Périmètre** : correctif sur la création de question dans l'éditeur de quiz.

---

## En une ligne

Le bouton « + Ajouter une question » provoquait l'erreur « Erreur lors de l'enregistrement de la question. ». La question est désormais créée correctement, vide, prête à être renseignée par l'utilisateur dans l'éditeur.

---

## Diagnostic

La méthode `upsert_qz_question()` du trait Core rejetait toute question dont le titre était vide (héritage d'une validation trop stricte écrite en 3.21.00). Or, l'UX prévue est exactement l'inverse : on crée une question vide, on l'édite ensuite, et l'autosave persiste les saisies au fil de l'eau.

Deuxième problème mineur identifié au passage : le JS de création envoyait deux réponses pré-remplies « Réponse 1 / Réponse 2 » que l'utilisateur devait effacer avant de saisir les vraies. Comportement non souhaité.

---

## Ce qui change

**Côté serveur** (`includes/quizzes/class-acdc-quizzes-core-trait.php`)

La validation « titre obligatoire à la création » est retirée de `upsert_qz_question()`. Une question peut désormais être insérée avec un titre vide. La validation finale (tous les champs requis avant lancement d'un quiz) sera ajoutée en 3.21.02 dans le mécanisme de verrouillage, là où elle a sa place.

**Côté navigateur** (`assets/js/quizzes-editor.js`)

La fonction `ajaxCreateQuestion` ne pré-remplit plus de réponses fictives : elle envoie un tableau de réponses vide, et l'utilisateur saisit ses propres propositions dans l'éditeur. Le serveur filtre déjà les réponses dont le texte est vide, donc rien n'est persisté tant que l'utilisateur n'a pas saisi quelque chose.

**Aucun autre fichier n'est modifié.** Les 5 traits du module Quizzes, le kernel, et tout le reste de la 3.21.01.1 restent à l'identique.

---

## Recette en 3 étapes

**Étape 1 — Installation.**
Téléverse le ZIP `3.21.01.2` par-dessus la 3.21.01.1. Vide le cache LiteSpeed et fais Ctrl+Maj+R. Vérifie que la version affichée dans Extensions est **3.21.01.2**.

**Étape 2 — Création d'une question.**
Va sur `Quiz / Test / Évaluation` → `Quiz live`. Ouvre un quiz existant (ou crée-en un nouveau). Dans l'éditeur, clique sur **« + Ajouter une question »**. Une question vide doit apparaître immédiatement dans la zone centrale, sans message d'erreur. La vignette de la question doit aussi apparaître dans le panneau gauche.

**Étape 3 — Saisie et autosave.**
Tape un énoncé (par exemple `Quelle est la couleur du ciel ?`). Patiente une seconde. L'indicateur en haut de l'éditeur doit afficher brièvement « Enregistrement… » puis « Enregistré ». Ajoute une réponse via le bouton « + Ajouter une proposition », tape `Bleu`, coche-la comme correcte. Patiente. Vérifie que l'autosave passe.

Si ces 3 étapes passent, le bug est corrigé.

---

## Rollback

Pour revenir en arrière : réinstaller le ZIP 3.21.01.1 par-dessus. Aucune modification de schéma BDD entre les deux versions, pas de migration à défaire.

---

## À suivre

La 3.21.02 (versioning et verrouillage) intègrera une **validation de complétude** au moment du verrouillage du quiz : un quiz ne peut basculer en `active` que si toutes ses questions ont un titre, des réponses cohérentes selon leur type, et qu'au moins une question est scorée (sauf pour les quiz de type sondage uniquement). Cette validation remplace celle qui était écrite trop tôt dans `upsert_qz_question()`.
