# ACDC Formation SAAS — Release notes 3.21.02

**Date** : 27 avril 2026
**Slug** : `acdc-formation-saas-organisme-de-formation`
**Périmètre** : versioning des quiz, verrouillage pour traçabilité Qualiopi, validation à l'activation.

---

## En une phrase

Tes quiz ont maintenant un cycle de vie complet : tu les construis en brouillon, tu les actives quand ils sont prêts, ils se verrouillent une fois utilisés en session réelle (ou manuellement), et toute évolution ultérieure passe par une nouvelle version chaînée à la précédente — ce qui satisfait l'exigence Qualiopi de traçabilité.

---

## Architecture — deux mécanismes distincts

J'ai retenu l'option où **le statut métier et le verrouillage Qualiopi sont deux choses séparées**, parce que ça correspond à la réalité de tes besoins.

**Le statut métier**, c'est ton cycle d'auteur : `brouillon` → `actif` → `archivé`. Tu peux passer librement de l'un à l'autre tant que le quiz n'a pas servi.

**Le verrouillage**, c'est la traçabilité Qualiopi. Une fois un quiz utilisé en session réelle, il bascule en `is_locked = 1` et plus aucune modification n'est possible. Pour le faire évoluer, tu crées une nouvelle version. L'ancienne reste consultable pour les audits.

Concrètement, un quiz peut être :

- `brouillon` non verrouillé : tu construis, tu modifies librement.
- `actif` non verrouillé : publié, prêt à servir, encore modifiable.
- `actif` verrouillé : a servi en session, figé pour Qualiopi.
- `archivé` non verrouillé : retiré du circuit, jamais utilisé.
- `archivé` verrouillé : retiré du circuit après usage, restera consultable.

---

## Ce qui apparaît dans l'interface

**Dans la modale Paramètres → onglet Général**, un nouvel encadré « État du quiz » qui change selon le statut :

- Si `brouillon` : un bouton bleu « ✓ Activer ce quiz ». Cliquer déclenche la validation structurelle. Si tout est OK, bascule en `actif` avec confirmation. Si quelque chose manque, l'activation est bloquée et la liste des erreurs s'affiche.
- Si `actif` : deux boutons doux côte à côte. « Repasser en brouillon » pour revenir en arrière (tant que pas verrouillé), et « 🔒 Verrouiller manuellement » pour figer une version pour archive sans attendre une session réelle.
- Si `actif` et verrouillé : l'encadré disparaît (plus rien à faire ici).
- Si `archivé` : un simple message d'information.

**Dans la toolbar de l'éditeur**, j'ai ajouté à côté du titre :

- Un badge `v2`, `v3`... si le quiz est une version de rang supérieur à 1.
- Une pastille de statut colorée (jaune brouillon, vert actif, gris archivé) si non verrouillé.
- Un drapeau « 🔒 Verrouillé » si verrouillé.
- Un bouton « + Nouvelle version » qui n'apparaît que sur les quiz verrouillés.

**Dans la liste des quiz**, j'ai ajouté un nouveau filtre « Versions » avec deux options :

- « Versions courantes » (par défaut) : tu ne vois que la version la plus récente de chaque lignée. Plus les quiz isolés (qui n'ont pas de chaîne de versions). C'est ton mode de travail normal.
- « Toutes les versions » : tu vois aussi les anciennes versions verrouillées. Utile pour les audits.

Sur les cartes elles-mêmes, le badge de version apparaît à côté du titre, les anciennes versions sont rendues plus discrètes visuellement (légère opacité), et les quiz verrouillés ont un bord gauche orangé pour les repérer immédiatement.

**Dans le menu ⋯ d'une carte**, l'entrée « Modifier » devient « Consulter (lecture seule) » sur les quiz verrouillés. Et une nouvelle entrée « Créer une nouvelle version » apparaît sur les quiz verrouillés ou sur la version courante d'une lignée.

**Sous la toolbar de l'éditeur**, quand un quiz est verrouillé, un bandeau ambré explique clairement : « 🔒 Ce quiz est verrouillé. Verrouillé le 27 avril 2026 à 14h32. Il a été utilisé pour des sessions réelles et ne peut plus être modifié, pour préserver la traçabilité Qualiopi. Pour le faire évoluer, créez une nouvelle version. »

---

## Ce qui se passe à l'activation

La validation est en deux temps : **les erreurs bloquent**, **les avertissements alertent**.

**Erreurs bloquantes** (l'activation est refusée tant qu'elles ne sont pas corrigées) :
- Le titre du quiz est vide.
- La formation rattachée a été supprimée depuis la création du quiz.
- Le quiz ne contient aucune question.
- Une question scorée n'a pas d'énoncé.
- Une question scorée n'a aucune bonne réponse cochée (sauf pour les sondages et questions ouvertes, qui sont par nature non scorés).

**Avertissements non bloquants** (signalés mais l'activation passe quand même) :
- La description du quiz est vide.
- Aucun objectif pédagogique n'est rattaché.

Si tu cliques sur « Activer ce quiz » et que la validation est OK, tu retournes à l'éditeur avec un message vert « Quiz activé. Il peut maintenant être lancé en session. ». Si des avertissements existent, ils sont mentionnés à la suite : « (Avertissements : La description est vide • Aucun objectif pédagogique...) ».

Si la validation échoue, tu restes dans l'éditeur avec un message rouge listant chaque erreur séparée par des points : « Activation impossible : Question 2 : aucune bonne réponse cochée • Question 4 : l'énoncé est vide ».

---

## Ce qui se passe à la création d'une nouvelle version

Quand tu cliques « Créer une nouvelle version » (depuis la toolbar de l'éditeur d'un quiz verrouillé, ou depuis le menu ⋯ d'une carte), une confirmation s'affiche : « Créer une nouvelle version de ce quiz ? La version actuelle restera consultable en lecture seule, et vous éditerez la nouvelle version. ».

Si tu valides, voici ce qui se passe :

Le système duplique intégralement le quiz source (titre, description, paramètres, toutes les questions avec leurs réponses, tous les objectifs). Le nouveau quiz reçoit `version_number = N+1` (où N est le numéro du quiz source). Son champ `replaces_quiz_id` pointe vers le quiz source. Réciproquement, le quiz source reçoit un `replaced_by_quiz_id` qui pointe vers le nouveau. Le nouveau quiz est marqué `is_current = 1`, l'ancien `is_current = 0`. Le nouveau repart en `brouillon` et `is_locked = 0`.

Tu es automatiquement redirigé vers l'éditeur de la nouvelle version, prêt à modifier.

---

## Algorithmie du chaînage

Pour les curieux : la chaîne de versions n'est pas stockée comme une liste, mais déduite de deux pointeurs (`replaces_quiz_id` qui remonte, `replaced_by_quiz_id` qui descend). C'est la même approche qu'un linked-list bidirectionnel.

Quand le système doit reconstruire la chaîne complète (par exemple pour la futur écran d'historique en 3.21.10), il part de n'importe quel point, remonte tant que `replaces_quiz_id > 0` jusqu'à trouver la racine, puis redescend en suivant `replaced_by_quiz_id` jusqu'à la pointe. Une garde de sécurité limite à 50 itérations dans chaque sens pour éviter les boucles en cas d'incohérence BDD.

J'ai testé l'algorithme sur 5 cas (entrée par la racine, par le milieu, par la pointe, quiz isolé, récupération de la version courante) avant le packaging — tout passe.

---

## Ce qui change dans le code

Aucune migration BDD n'est nécessaire. Les colonnes `version_number`, `is_current`, `replaces_quiz_id`, `replaced_by_quiz_id`, `is_locked`, `locked_at`, `locked_reason`, `activated_at`, `archived_at` étaient déjà créées en 3.21.00. Bonne anticipation.

Côté code, j'ai modifié 7 fichiers exactement :

- Le header du plugin pour passer en 3.21.02.
- Le trait Core (+ environ 380 lignes) : 6 nouvelles méthodes publiques pour la validation, l'activation, la dépublication, le verrouillage, la création d'une nouvelle version, et la reconstruction de la chaîne de versions.
- Le trait Actions (+ environ 130 lignes) : 4 nouveaux handlers admin-post pour `activate_quiz`, `unpublish_quiz`, `lock_quiz`, et la réimplémentation de `create_new_version` (qui était un stub jusque-là).
- Le trait Render principal : ajout du filtre Versions dans le bandeau, badges sur les cartes, action « Nouvelle version » dans le menu ⋯, libellé Modifier/Consulter selon verrouillage.
- Le trait Render Editor : badge version + pastille de statut + drapeau verrouillage dans la toolbar, bandeau ambré sous la toolbar quand verrouillé, encadré « État du quiz » dans la modale Paramètres.
- Le JS de l'éditeur (+ environ 60 lignes) : 4 nouveaux handlers pour les 4 nouveaux boutons.
- Le CSS (+ environ 100 lignes) : badge version, pastilles statut, bandeau verrouillage, encadré lifecycle.

---

## Recette en 7 étapes

**Étape 1 — Installation.** Téléverse le ZIP par-dessus la 3.21.01.3. Vide le cache LiteSpeed et fais Ctrl+Maj+R. Vérifie que la version dans Extensions est **3.21.02**.

**Étape 2 — Quiz brouillon → actif.** Va sur `Quiz / Test / Évaluation` → `Quiz live`. Crée un nouveau quiz `TEST 3.21.02` avec une formation. Ajoute une question, mets-lui un énoncé et 2 propositions dont une cochée correcte. Ouvre la modale Paramètres → onglet Général. En bas tu vois l'encadré « État du quiz » avec le bouton bleu « ✓ Activer ce quiz ». Clique. Tu reviens à l'éditeur avec un message vert : « Quiz activé. ». Dans la toolbar, la pastille jaune « Brouillon » est devenue verte « Actif ».

**Étape 3 — Validation bloquante.** Dans la modale Paramètres → onglet Général, repasse le quiz en brouillon. Retourne dans l'éditeur, supprime la coche « bonne réponse » de la question. Ouvre Paramètres et clique « Activer ». Cette fois tu dois voir un message rouge listant l'erreur : « Activation impossible : Question 1 : aucune bonne réponse n'est cochée. ». Recoche la bonne réponse, le quiz redevient activable.

**Étape 4 — Verrouillage manuel.** Le quiz étant actif, ouvre la modale Paramètres → onglet Général. À côté de « Repasser en brouillon » tu as maintenant « 🔒 Verrouiller manuellement ». Clique. Confirme la modale d'avertissement (« action irréversible »). Tu reviens à l'éditeur avec un message vert. Le quiz est désormais verrouillé : un bandeau ambré apparaît sous la toolbar, le drapeau « 🔒 Verrouillé » remplace la pastille de statut, tous les champs deviennent en lecture seule, l'encadré « État du quiz » disparaît, et un nouveau bouton « + Nouvelle version » apparaît dans la toolbar.

**Étape 5 — Tentative de modification d'un quiz verrouillé.** Essaie de modifier le titre d'une question. Tous les champs sont disabled, les boutons « Ajouter une question », « Ajouter une proposition » sont grisés. Tu es bien empêché de modifier. Bon.

**Étape 6 — Création d'une nouvelle version.** Clique sur « + Nouvelle version » dans la toolbar. Confirme. Tu es redirigé vers l'éditeur de la nouvelle version. Vérifie : titre identique, badge `v2` à côté du titre, statut « Brouillon » à nouveau, plus de bandeau verrouillage, tous les champs éditables. Toutes les questions et réponses sont là, copiées de la v1.

**Étape 7 — Filtre Versions courantes / toutes.** Retourne à la liste des quiz (« Fermer » en haut à droite). Le filtre par défaut « Versions courantes » te montre uniquement la `v2` (la `v1` verrouillée est masquée). Bascule sur « Toutes les versions » : tu vois maintenant `v1` et `v2`. La `v1` apparaît avec une opacité réduite, un badge `v1`, un bord orangé qui indique le verrouillage.

Si ces 7 étapes passent, le cycle de vie complet est validé.

---

## Limites de cette version

**Le verrouillage automatique au premier usage n'est pas encore actif** parce que les modules « Lancement live » (3.21.05) et « Envoi async » (3.21.03) n'existent pas encore. La méthode `lock_qz_quiz_on_first_use()` est en place et sera appelée par ces modules quand ils arriveront. Pour l'instant, seul le verrouillage manuel via le bouton « 🔒 Verrouiller manuellement » est utilisable. C'est précisément pour pouvoir tester le mécanisme dès maintenant que j'ai ajouté ce bouton.

**Les quiz dupliqués vers une autre formation repartent à v1**, comme convenu. Si tu prends `Test Excel v3` (formation Bureautique) et que tu utilises « Dupliquer dans cette formation » sur un quiz d'une autre formation, la copie est traitée comme une lignée indépendante. C'est la décision validée pour ne pas avoir de « v7 » sur des formations qui n'ont jamais eu v1 à v6.

**Les anciennes versions ne se voient pas encore via un onglet « Historique »** dans l'éditeur. C'est prévu pour la 3.21.10 (Pilotage et exports), où l'on aura l'écran de comparaison des versions et l'export.

---

## Rollback

Pour revenir en arrière : réinstaller le ZIP 3.21.01.3 par-dessus. Aucune migration BDD à défaire (les colonnes existent déjà depuis 3.21.00, simplement non utilisées). Les éventuels quiz que tu aurais activés ou verrouillés conservent leurs valeurs en BDD, mais l'interface 3.21.01.3 ne les affichera plus.

---

## À suivre

Prochaine sous-version **3.21.03** : envoi asynchrone par e-mail. Pour les tests de positionnement et les évaluations à passer à distance. Le module enverra un mail avec un lien magique signé, l'apprenant clique, passe le quiz, et le formateur reçoit les résultats. C'est aussi la 3.21.03 qui déclenchera pour la première fois `lock_qz_quiz_on_first_use()` automatiquement, dès le premier envoi réel.
