# 3.21.04.1-hotfix3 — Trois icônes de sidebar dédiées par finalité

**Date :** 27 avril 2026
**Type :** ajustement visuel de la sidebar.

---

## Le problème

À la recette de hotfix2, la sidebar montrait six entrées correctement organisées, mais visuellement confuses : quatre des six items utilisaient l'icône « barres graphique ». Pas par paresse — par mutualisation des SVG dans le kernel. Le name `quiz` était mappé sur le même SVG que `evaluations` (clipboard avec checkmark), et `positioning` partageait son SVG avec `results`, `evaluation_result`, `statistics`, `report` et `bpf` (les barres graphiques). Donc « Quiz live » et « Tests de positionnement » se retrouvaient avec des icônes qui ne reflétaient pas leur métier.

## Ce qui change

**Trois SVG dédiés**, en cohérence stroke style avec le reste de la sidebar :

- **`quiz`** — bulle de dialogue avec point d'interrogation. Auparavant : clipboard partagé avec `evaluations`.
- **`positioning`** — presse-papiers avec crayon (geste de test, prise de notes). Auparavant : barres graphique partagées avec les Résultats.
- **`evaluation_acquired`** — *nouveau name* — certificat avec items cochés et médaille. Utilisé pour l'onglet « Évaluations des acquis » qui auparavant utilisait `evaluation_result` (barres graphique).

## Pourquoi un nouveau name plutôt que de modifier `evaluations`

Le name `evaluations` est utilisé à deux endroits :
1. Le **label parent** du groupe « Quiz / Test / Évaluation » (le titre du regroupement dans la sidebar)
2. Anciennement aussi pour l'onglet « Évaluations des acquis »

Si je modifiais `evaluations`, je changeais aussi l'icône du label parent. Pour préserver l'existant et n'agir que là où c'est demandé, j'introduis `evaluation_acquired` comme nouveau nom dédié à l'onglet de gestion des évaluations.

## Mapping final dans la sidebar

| Item | Icône | SVG |
|---|---|---|
| Quiz live | `quiz` | bulle + ? |
| Résultats — Quiz live | `evaluation_result` | barres graphique |
| Tests de positionnement | `positioning` | clipboard + crayon |
| Résultats — Positionnement | `evaluation_result` | barres graphique |
| Évaluations des acquis | `evaluation_acquired` | certificat + médaille |
| Résultats — Évaluations | `evaluation_result` | barres graphique |

Six items, quatre icônes distinctes (les trois Résultats partagent justement le même visuel, ce qui est intentionnel et lisible).

## Compatibilité

- Aucune migration BDD
- Le name `evaluations` (label parent) garde son SVG d'origine — aucune régression sur le label du groupe
- Les autres consommateurs de `quiz` et `positioning` ailleurs dans le plugin verront leurs icônes changer, ce qui est l'effet recherché (voir notes de vigilance ci-dessous)

## Points de vigilance / régressions potentielles

- Le name `quiz` est aussi utilisé dans le menu legacy (groupe « Quiz / Enquêtes / Éval. ») pour son sous-onglet Quiz. Sa nouvelle icône sera la bulle au lieu du clipboard. C'est cohérent puisqu'il s'agit bien d'un quiz.
- Le name `positioning` peut être utilisé ailleurs dans le code legacy. Vérifier après installation que les autres usages ne sont pas dégradés.
- Si une icône remplace mal un usage existant, signale-le et on bascule vers un name dédié comme on a fait pour `evaluation_acquired`.

## Test renforcé

Le test confirme :
- `quiz` a un SVG distinct de `evaluations`
- `positioning` a un SVG distinct de `evaluation_result`
- `evaluation_acquired` existe et est unique
- Les 5 SVG concernés sont structurellement valides
- Le SVG quiz contient bien le point du `?`

## Recette

1. Vider le cache LiteSpeed
2. Sidebar gauche du groupe « Quiz / Test / Évaluation » : vérifier les nouvelles icônes
3. Dans le menu legacy « Quiz / Enquêtes / Éval. » : vérifier que le sous-onglet Quiz n'est pas visuellement cassé (le SVG a changé mais reste cohérent)
