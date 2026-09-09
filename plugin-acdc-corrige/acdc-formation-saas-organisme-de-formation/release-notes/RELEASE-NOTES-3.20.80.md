# ACDC Formation SAAS — version 3.20.80

## Objet

Correction d'un bug CSS dans le catalogue public introduit en 3.20.79 (et présent en réalité depuis l'origine de la fonctionnalité de recherche, mais resté invisible jusqu'aux nouveaux filtres modalité).

Les chips de modalité ne filtraient pas réellement les cards : visuellement la chip s'activait et le bouton de retour apparaissait, mais les cards restaient toutes affichées.

## Diagnostic

Le JavaScript de filtre applique `card.hidden = true` sur les cards à masquer. Le navigateur traduit normalement cet attribut en `display:none` via la règle implicite `[hidden] { display: none }`. **Mais cette règle est surchargée par une règle plus spécifique du catalogue** :

```css
.acdc-catalog-card{display:flex;flex-direction:column;min-height:100%}
```

Spécificité comparée :

| Sélecteur | Spécificité (a,b,c) |
|---|---|
| `[hidden]` (règle navigateur) | (0,1,0) |
| `.acdc-catalog-card` (règle plugin) | (0,1,0) |

Spécificité identique, mais en CSS la règle déclarée **en dernier** l'emporte. Donc `.acdc-catalog-card{display:flex}` gagne et l'attribut `hidden` ne masque rien. Les cards restent visibles malgré le filtre.

Ce piège affecte aussi la recherche textuelle existante (depuis l'origine du catalogue), mais le bug y était moins flagrant à repérer parce qu'on tapait du texte sans toujours observer si le filtre fonctionnait réellement. Avec les chips, le clic est explicite, donc le dysfonctionnement saute aux yeux.

## Correction

Une seule règle CSS ajoutée juste après `.acdc-catalog-card{display:flex...}` :

```css
.acdc-catalog-card[hidden]{display:none!important}
```

Le `!important` est ici **justifié et exceptionnel** : on doit explicitement neutraliser une règle de classe plus spécifique. Il n'introduit aucun effet de bord parce que la règle ne s'applique qu'aux cards portant l'attribut `[hidden]`, attribut qui n'est jamais posé manuellement dans le code — uniquement par le JS de filtre.

## Engagement de préservation

- **Une seule ligne CSS ajoutée.**
- **Aucune modification de logique JS, PHP, BD.**
- **Aucune autre page touchée.**
- **Toutes les corrections 3.20.57 → 3.20.79 conservées.**

## Bénéfice collatéral

La recherche textuelle existante (`acdc-catalog-search-input`) était également affectée par le même piège — elle masquait les cards dans le DOM via `[hidden]`, mais visuellement elles restaient affichées. Cette correction la fait fonctionner enfin correctement aussi.

## Risques de régression

Nuls. La règle ajoutée est ultra-ciblée :

- Sélecteur `.acdc-catalog-card[hidden]` : ne touche que les cards qui ont **explicitement** l'attribut `hidden`.
- Effet : `display:none`. C'est exactement le comportement attendu d'un attribut `hidden`.

Aucune card du catalogue ne porte l'attribut `hidden` au chargement initial — il n'est posé que par le JS au moment du filtre. Aucun autre composant du plugin n'est concerné par cette règle.

## Procédure de test

1. Purger LiteSpeed.
2. Installer 3.20.80. Purger à nouveau.
3. Aller sur `/catalogue/`.
4. **Test chips** : cliquer sur « Présentiel » → seules les cards en présentiel doivent s'afficher (les autres doivent réellement disparaître). La chip est colorée en or, le bouton « ✕ Afficher toutes les formations » apparaît.
5. **Test bouton retour** : cliquer sur le bouton de retour → toutes les cards réapparaissent.
6. **Test recherche** : taper « facebook » dans la barre de recherche → seules les cards correspondantes restent visibles.
7. **Test combinaison** : filtre Distanciel + recherche → intersection.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump `3.20.79` → `3.20.80`.
- `includes/settings-catalog/class-acdc-settings-catalog-render-trait.php` — ajout d'une ligne CSS `.acdc-catalog-card[hidden]{display:none!important}`.

**Aucun autre fichier modifié.**

## Remarque honnête

Cette régression aurait dû être détectée à la livraison de 3.20.79. J'ai testé la cohérence du HTML produit et la logique JS, mais je n'ai pas exercé le rendu réel dans un navigateur où ce conflit de spécificité CSS se serait manifesté. C'est exactement le type de bug que ma documentation interne signale comme « le rendu écran > la lecture de code » — leçon à garder.

## Si quelque chose ne va pas

Le retour à 3.20.79 est immédiat. La règle ajoutée n'a aucun impact persistant.
