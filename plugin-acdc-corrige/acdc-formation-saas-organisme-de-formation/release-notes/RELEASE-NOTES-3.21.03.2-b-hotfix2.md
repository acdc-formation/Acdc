# RELEASE NOTES — 3.21.03.2-b (hotfix2)

**Date** : 27 avril 2026
**Type** : Hotfix mineur — bandeau de notice illisible après création
**Slug** : `acdc-formation-saas-organisme-de-formation/`

---

## Le bug corrigé

Après création d'un quiz depuis le portail formateur, le bandeau de confirmation affichait `Quizcrvouspouvezmaintenantajouterdesquestions` au lieu de « Quiz créé avec succès. Vous pouvez maintenant ajouter ses questions depuis l'administration. ».

### Cause

Côté handler admin-post, le notice est renvoyé sous forme de **libellé humain déjà traduit** (« Quiz créé avec succès… ») via `qz_redirect( ..., __('...') )`. Côté affichage portail formateur, j'avais utilisé `sanitize_key()` pour récupérer ce paramètre — ce qui supprime tout caractère non `a-z0-9_`. Résultat : espaces, accents, ponctuation, tout disparaît, et la chaîne se transforme en bouillie illisible.

### Correctif

1. Remplacement de `sanitize_key()` par `sanitize_text_field()` aux deux endroits où le notice est lu (liste « Mes quiz » et vue détail). `sanitize_text_field` préserve les espaces, la ponctuation et les accents tout en restant sûr (suppression des balises HTML).

2. Amélioration de la méthode `trainer_portal_quiz_notice_label()` : elle détecte maintenant si le code reçu est un libellé humain (présence d'un espace ou ponctuation) et le retourne tel quel, sinon elle applique le mapping slug → libellé. Cette logique en cascade gère proprement les deux cas de notice :
   - Handlers qui envoient déjà le texte traduit (la majorité — `__('Quiz mis à jour.')`).
   - Handlers qui envoient un slug court (ex. `feature_pending`).

---

## Tests effectués

- Lint PHP : pas d'erreur.
- Vérification que les deux occurrences `sanitize_key` ont bien été remplacées par `sanitize_text_field`.
- Logique de l'heuristique vérifiée mentalement sur 4 cas : `quiz_created` (slug → mappé), `Quiz créé avec succès.` (humain → tel quel), `feature_pending` (slug → mappé), chaîne vide → vide.

---

## Compatibilité

Compatible avec 3.21.03.2-b-hotfix1. Cumulatif. Aucune autre modification, juste ce fix.

---

## Procédure de mise à jour

1. Téléverser le ZIP, confirmer le remplacement.
2. Vider le cache LiteSpeed.
3. Vider le cache navigateur (Ctrl+Shift+R).

---

## Recette

1. Va sur le portail formateur → Mes quiz.
2. Clique « + Créer un quiz ».
3. Remplis et soumets.
4. Le bandeau de confirmation doit maintenant afficher proprement : « Quiz créé avec succès. Vous pouvez maintenant ajouter ses questions depuis l'administration. »
