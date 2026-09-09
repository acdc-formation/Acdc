# ACDC Formation SAAS — version 3.20.64

## Objet

Patch chirurgical d'un seul caractère qui complète la 3.20.63. Le décalage vertical de la colonne Actions persistait malgré la 3.20.63 parce qu'une autre règle CSS, injectée dynamiquement par le PHP, forçait `display: flex` sur les `<td>` d'actions, ce qui les sortait du modèle de tableau et empêchait `vertical-align: middle` de produire son effet.

## Anomalie corrigée

**Symptôme persistant** : malgré la 3.20.63 qui ajoutait correctement `vertical-align: middle !important` sur les cellules Actions, le décalage vertical restait visible sur Prospects, Apprenants, Entreprises, etc.

**Cause identifiée par inspection navigateur** : sur la `<td>` Actions, l'inspecteur révèle `display: flex` actif. Le coupable est dans `includes/kernel/class-acdc-kernel-core-trait.php`, fonction `get_dynamic_css()` ligne 5702, qui génère un CSS inline injecté dans le `<head>` :

```css
.acdc-actions-cell-icons,
.acdc-table td.acdc-actions-cell-icons,
.acdc-actions {
  display: flex !important;
  align-items: center !important;
  justify-content: flex-end !important;
  gap: var(--acdc-action-icon-gap) !important;
}
```

Le sélecteur `td.acdc-actions-cell-icons` cible directement la cellule de tableau et la transforme en boîte flex autonome. Or une `<td>` doit rester en `display: table-cell` pour participer correctement à la grille du tableau et synchroniser sa hauteur avec celle des cellules voisines. Quand le `display: flex` est forcé sur la `<td>`, elle devient une boîte indépendante : les boutons d'action sont bien centrés à l'intérieur de cette boîte, mais la boîte elle-même n'est plus alignée à la même ligne de base que les autres `<td>` de la ligne. Résultat visuel : le décalage que vous voyiez.

`vertical-align` n'a aucun effet sur un élément en `display: flex` — c'est `align-items` qui prend le relais, mais le problème est en amont : la `<td>` ne devrait pas être en flex.

**Correctif** : modification d'une seule ligne dans `class-acdc-kernel-core-trait.php` ligne 5702. Le sélecteur passe de :

```
.acdc-actions-cell-icons, .acdc-table td.acdc-actions-cell-icons, .acdc-actions
```

à :

```
.acdc-actions-cell-icons:not(td):not(th), .acdc-actions
```

L'effet `display: flex` continue de s'appliquer aux **wrappers internes** (`<div class="acdc-companies-actions-inline">`, `<div class="acdc-prospect-actions">`, etc.) qui contiennent les boutons. Mais il ne s'applique plus à la `<td>` parente, qui retombe en `display: table-cell` par défaut. Le `vertical-align: middle !important` posé en 3.20.63 prend alors effet et centre verticalement la cellule dans sa ligne.

Le filtre `:not(td):not(th)` est défensif : si à l'avenir la classe `.acdc-actions-cell-icons` est posée sur un `<div>` quelque part, elle bénéficiera toujours du flex. Mais elle ne s'applique pas aux cellules de tableau, ce qui résout le problème.

## Pourquoi cette règle écrasait celle de la 3.20.63

L'ordre de chargement CSS dans le plugin :

1. `frontend.css` (qui importe `tables.css` via `@import`).
2. `acdc-ui-system.css`.
3. `acdc-components.css` — c'est ici qu'est posée la règle 3.20.63 `vertical-align: middle !important`.
4. **Ensuite**, le PHP injecte un `<style>` inline dans le `<head>` via `get_dynamic_css()` — cette injection arrive **après** tous les fichiers CSS et a donc priorité à spécificité égale.

C'est ce CSS inline qui force `display: flex` sur la `<td>`, après que `acdc-components.css` ait posé son `vertical-align: middle`. Le `display: flex` neutralise l'effet de `vertical-align`. La 3.20.63 plaçait donc la bonne règle au bon endroit, mais une règle plus tardive l'invalidait silencieusement.

## Ce que la correction préserve

- Les boutons d'action restent **alignés horizontalement** entre eux (le wrapper interne `.acdc-companies-actions-inline`, `.acdc-prospect-actions`, etc., garde son flex).
- L'espacement entre les boutons reste piloté par `--acdc-action-icon-gap`.
- L'alignement à gauche, au centre ou à droite des boutons dans la cellule reste identique (les autres règles CSS qui pilotent ce comportement sont intactes).
- Toutes les autres règles `display: flex` qui s'appliquent aux wrappers internes sont conservées.

## Engagement de préservation

**Aucun JavaScript modifié. Aucune logique métier touchée. Aucune fonctionnalité retirée.**

Cette version :
- Modifie une seule ligne de PHP — le sélecteur d'une seule règle CSS dynamique.
- Conserve toutes les corrections des 3.20.57 → 3.20.63 (variables CSS, glyphes Prospects, exclusions AcdcActionHub, alignement vertical).
- Préserve les interactions Prospects (dropdown 6 entrées, modale RDV, édition inline).

## Compatibilité

- Aucun changement de slug.
- Aucun changement de structure de base de données.
- Aucune option modifiée.
- Si la modification ne produisait pas l'effet attendu (par exemple à cause d'un cache), le retour à la 3.20.63 est immédiat et transparent.

## Vérification recommandée

Sur staging, en partant d'une 3.20.63 fonctionnelle :

1. **Purger le cache LiteSpeed** depuis Réglages → LiteSpeed Cache → Toolbox → Purge All.
2. Installer la 3.20.64.
3. Recharger en mode privé (ou Ctrl+Shift+R).
4. Ouvrir la liste **Entreprises** ou **Prospects** : vérifier que sur les lignes avec un texte de formation sur deux lignes, les boutons d'action sont **centrés verticalement** au milieu de la ligne.
5. Vérifier que les boutons restent **alignés horizontalement** entre eux (œil, crayon, corbeille côte à côte).
6. Vérifier que le **gap** entre les boutons est correct.
7. Vérifier que toutes les fonctions métier marchent toujours :
   - Dropdown 6 entrées sur Prospects.
   - Modale RDV.
   - Édition inline statut/attribution.
   - Voir, Modifier, Supprimer sur toutes les pages.

## Pour confirmer techniquement la prise d'effet

Dans l'inspecteur navigateur, clic droit sur une `<td>` Actions → Inspecter → onglet Computed :
- Avant 3.20.64 : `display: flex`.
- Après 3.20.64 : `display: table-cell` (valeur par défaut des `<td>`).
- `vertical-align: middle` doit s'afficher comme effectif (et non barré).

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — version 3.20.64.
- `includes/kernel/class-acdc-kernel-core-trait.php` — une seule ligne, ligne 5702 : modification du sélecteur d'une règle CSS générée dynamiquement.

**Aucun autre fichier modifié.**

## Si quelque chose ne va pas

Le retour à la 3.20.63 est immédiat — un seul ZIP à réinstaller. La modification étant un simple ajustement de sélecteur dans une règle CSS, le risque de régression est très faible.
