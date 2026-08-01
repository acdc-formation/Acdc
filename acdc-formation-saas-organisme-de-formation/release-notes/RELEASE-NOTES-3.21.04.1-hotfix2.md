# 3.21.04.1-hotfix2 — Trois onglets Résultats par finalité + fix CSS

**Date :** 27 avril 2026
**Type :** correctif critique de la livraison hotfix1.

---

## Ce qui était cassé en hotfix1

Deux problèmes constatés à la recette :

1. **Le CSS de la page Résultats ne se chargeait pas.** La page apparaissait en mode texte brut : KPI cards empilés sans mise en forme, tableau sans bordures, filtres mal alignés. Cause racine : la fonction `is_qz_extranet_screen()` qui pilote l'enqueue du CSS ne reconnaissait pas le nouveau tab `qz_results` ajouté en hotfix1. Le tableau des tabs autorisés n'avait pas été mis à jour. Erreur de ma part — j'aurais dû y penser quand j'ai créé la constante.

2. **Architecture sidebar mal pensée.** Un onglet « Résultats » transverse mélange les sessions de quiz live, les tests de positionnement et les évaluations des acquis dans la même liste. Or ces trois finalités ont des objectifs métier distincts : un test de positionnement détecte un niveau initial, une évaluation des acquis valide une fin de formation (Qualiopi indicateur 12), un quiz live anime un présentiel. Les regrouper dans un même tableau n'aide pas la lecture.

## Ce qui change

**CSS** — `is_qz_extranet_screen()` reconnaît désormais les 4 tabs Résultats. La page s'affiche correctement avec ses KPI cards, ses filtres et son tableau stylé.

**Sidebar** — refonte du groupe « Quiz / Test / Évaluation » qui passe de 4 à 6 entrées :

```
Quiz / Test / Évaluation
├── Quiz live
├── Résultats — Quiz live              ← nouveau, dédié
├── Tests de positionnement
├── Résultats — Positionnement         ← nouveau, dédié
├── Évaluations des acquis
└── Résultats — Évaluations            ← nouveau, dédié
```

Chaque finalité a son onglet de gestion **et** son onglet Résultats dédié, l'un à la suite de l'autre. Plus de mélange entre types de questionnaires.

**Onglet transverse retiré** — le `qz_results` global ajouté en hotfix1 n'est plus exposé dans la sidebar. La constante reste pour compatibilité éventuelle d'URLs déjà en signets, mais elle n'apparaît plus dans la navigation. Trois onglets ciblés valent mieux qu'un onglet fourre-tout.

## Comportement détaillé des 3 nouveaux onglets

Chaque onglet Résultats spécifique :

- Affiche un **titre adapté** (« Résultats — Quiz live » / « Résultats — Tests de positionnement » / « Résultats — Évaluations des acquis »)
- Affiche un **sous-titre métier** différent selon la finalité (notamment la mention Qualiopi indicateur 12 pour les évaluations)
- **Masque le filtre « Finalité »** (puisqu'il est imposé par le tab — pas la peine de demander ce qu'on sait déjà)
- **Masque la colonne « Finalité »** dans le tableau (idem)
- **Filtre les KPIs sur la finalité** (le « score moyen global » d'un test de positionnement n'est pas le même que celui d'une évaluation)
- Garde le filtre **« Statut »** (utile pour distinguer envoyé / en cours / terminé)

## Architecture technique

**Nouvelles constantes** :
- `ACDC_OF_QZ_TAB_RESULTS_LIVE` = `'qz_results_live'`
- `ACDC_OF_QZ_TAB_RESULTS_POSITIONING` = `'qz_results_positioning'`
- `ACDC_OF_QZ_TAB_RESULTS_ASSESSMENT` = `'qz_results_assessment'`

**Routage** : `render_qz_unknown_tab()` reconnaît chacun des 3 tabs et appelle `render_qz_extranet_screen()` avec le purpose correspondant. La vue `results` est imposée automatiquement (pas besoin d'ajouter `?view=results` à la main dans la sidebar).

**Détection écran** : `is_qz_extranet_screen()` ajoute les 4 tabs Résultats à sa liste, ce qui déclenche l'enqueue du CSS et du JS sur ces pages.

## Compatibilité

- Aucune migration BDD
- Toutes les vues détaillées (par participant, par question, par objectif, correction manuelle, export CSV) sont **inchangées**
- Le portail formateur n'est pas modifié (il utilisait déjà son propre onglet Résultats unifié, qui reste pertinent côté formateur)

## Points de recette

1. Vider le cache LiteSpeed (réflexe systématique)
2. Aller sur l'extranet ACDC en mode admin
3. Sidebar gauche : vérifier que le groupe « Quiz / Test / Évaluation » contient bien **6 entrées** dans cet ordre :
   - Quiz live
   - Résultats — Quiz live
   - Tests de positionnement
   - Résultats — Positionnement
   - Évaluations des acquis
   - Résultats — Évaluations
4. Cliquer sur **« Résultats — Évaluations »** : la page doit s'afficher avec son CSS (KPI cards stylées, tableau bordé, filtres dans une carte)
5. Vérifier que **seuls les envois d'évaluation** apparaissent (pas les positionnements ni les live)
6. Vérifier que le **filtre « Finalité » est absent** (puisque imposé) mais que le **filtre « Statut » est présent**
7. Cliquer sur « Voir → » d'un envoi : le détail (3 onglets Participants / Par question / Par objectif) doit fonctionner comme avant
8. Reproduire pour « Résultats — Quiz live » et « Résultats — Positionnement »

## Note honnête sur la cause racine

L'oubli sur l'enqueue CSS est typiquement le genre de bug que le test renforcé aurait dû attraper. Mais mes tests vérifiaient l'existence des méthodes et des constantes, pas le branchement effectif des assets. J'ajoute mentalement à mes habitudes : pour tout nouvel onglet, vérifier explicitement que `is_qz_extranet_screen()` (et toute fonction similaire d'orchestration des assets) le reconnaît.
