# ACDC Formation SAAS — version 3.20.59

## Objet

Patch chirurgical : corriger deux anomalies visuelles signalées sur la page Prospects et ajouter au back office UI un nouveau champ de pilotage pour l'icône Suivi commercial.

## Anomalies corrigées

### 1. Menu trois points vertical au lieu d'horizontal (Prospects)

**Symptôme** : sur la liste des Prospects, le bouton menu trois points s'affichait en colonne (trois points empilés verticalement), alors que tous les autres écrans affichent une rangée horizontale.

**Cause identifiée** : le moteur JavaScript `AcdcActionHub.normalize` (admin.js, 3.20.56) détectait les boutons d'action des Prospects et les **réécrivait** avec sa propre version verticale du SVG menu. Le rendu PHP d'origine était pourtant horizontal — mais il était systématiquement remplacé par le moteur après affichage.

**Correctif** : ajout des sélecteurs `.acdc-prospect-actions`, `.acdc-prospect-action-menu`, `.acdc-prospect-action-dropdown` et `.acdc-prospect-patch-actions` à la liste d'exclusion d'`AcdcActionHub.isActionElement()` dans `assets/js/admin.js`. Le module Prospects dispose déjà de son propre CSS dédié et de son propre rendu PHP — l'exclusion préserve désormais l'intention d'origine sans interférence.

### 2. Suivi commercial affichait un œil au lieu d'un presse-papier

**Symptôme** : sur la liste des Prospects, le 3e bouton (Suivi commercial) affichait un œil identique à celui du bouton Voir, créant une ambiguïté visuelle.

**Cause identifiée** : double facteur :
- Le glyphe `'clipboard'` demandé par le rendu PHP n'était **pas défini** dans la fonction `get_nav_icon_svg()` du noyau, donc tombait sur le glyphe par défaut (cercle avec « + »).
- Le moteur `AcdcActionHub` détectait ensuite le mot `view` dans l'URL `?action=view&item_id=...` du lien Suivi commercial et imposait l'œil.

**Correctifs** :
- Ajout du glyphe `'clipboard'` dans `get_nav_icon_svg()` (`includes/kernel/class-acdc-kernel-core-trait.php`), avec aliases `'followup'` et `'task'`. SVG conforme à la charte graphique du noyau (stroke 1.9, currentColor, viewBox 24×24).
- Exclusion d'AcdcActionHub déjà traitée par le correctif n°1 ci-dessus, qui empêche le remplacement du clipboard par l'œil.

## Nouvelle fonctionnalité — Pilotage du Suivi commercial dans le back office

### Champ ajouté

Dans Réglages → Système UI → Icônes → « Choix des pictogrammes d'action », nouveau sélecteur :

- **Suivi commercial** — par défaut : Presse-papier.
- Apparaît dans l'ordre logique : entre « Supprimer » et « Dupliquer ».
- Propose les mêmes 31 pictogrammes que les autres champs (Œil, Crayon, Corbeille, Document, Calendrier, Graphique, etc.) plus le nouveau Presse-papier.

### Mécanisme technique

- Nouvelle option `action_icon_followup` ajoutée aux options par défaut de `class-acdc-settings-catalog-core-trait.php` (valeur par défaut `'clipboard'`).
- Mapping ajouté dans `map_configured_action_icon()` du noyau : `'clipboard' → action_icon_followup`, `'followup' → action_icon_followup`, `'task' → action_icon_followup`.
- L'appel PHP existant `render_inline_icon('clipboard', 18)` (déjà présent dans le rendu Prospects depuis longtemps) lit maintenant automatiquement la nouvelle option du back office.

### Bonus — Le menu trois points Prospects devient pilotable

En supprimant le SVG en dur du menu trois points dans le rendu PHP des Prospects, et en le remplaçant par un appel `render_inline_icon('more-horizontal', 18)`, ce menu **bénéficie maintenant** du champ « Menu 3 points » du back office. Avant ce patch, ce menu ignorait le réglage. Désormais il s'aligne sur tous les autres modules.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` : numéro de version (3.20.59).
- `assets/js/admin.js` : exclusion des Prospects de la détection `AcdcActionHub`.
- `includes/kernel/class-acdc-kernel-core-trait.php` : ajout du glyphe SVG `clipboard`.
- `includes/kernel/class-acdc-kernel-render-trait.php` : ajout du mapping `clipboard → action_icon_followup`, ajout du choix « Presse-papier » dans la liste, ajout du sélecteur « Suivi commercial » dans le formulaire.
- `includes/settings-catalog/class-acdc-settings-catalog-core-trait.php` : ajout de l'option `action_icon_followup` aux valeurs par défaut.
- `includes/crm-commercial/class-acdc-crm-commercial-render-trait.php` : remplacement du SVG en dur du menu 3 points Prospects par un appel `render_inline_icon('more-horizontal', 18)`.

**Aucun CSS modifié.** **Aucune option supprimée.** **Aucun renommage de classe.**

## Compatibilité

- Aucun changement de slug, aucun changement de structure de base de données.
- Les options existantes restent inchangées. La nouvelle option `action_icon_followup` est créée avec sa valeur par défaut au premier chargement de l'écran de réglages, sans aucune action utilisateur requise.
- Aucune régression visuelle attendue sur les autres modules (Apprenants, Entreprises, Sessions, Formations, etc.) : ils continuent d'utiliser `AcdcActionHub` comme avant.
- Les pages Prospects voient leur rendu **se rapprocher de l'intention d'origine du PHP**, ce qui était le but recherché.

## Points d'attention résiduels (à traiter en phase 4)

Deux scripts JavaScript du fichier CRM contiennent encore des SVG menu trois points en dur (lignes 484 et 1647 — fonctions `iconMarkup` et `icon`). Ils sont **horizontaux** (donc visuellement corrects) mais ne sont pas pilotés par le back office. Quand ils s'exécutent (footer du module Prospects et dropdown Suivi commercial), ils injectent leurs SVG sans passer par `render_inline_icon` ni par le mapping. Ce sera nettoyé lors du chantier de rationalisation des moteurs JS (phase 4).

## Vérification recommandée

Sur staging :

1. Installer la 3.20.59.
2. Aller sur Liste des prospects.
3. **Vérifier** : le menu trois points est désormais **horizontal** (3 points alignés en rangée).
4. **Vérifier** : la 3e icône (Suivi commercial) affiche un **presse-papier**, plus un œil.
5. Aller dans Réglages → Système UI → Icônes → bas de page.
6. **Vérifier** : un nouveau champ **« Suivi commercial »** apparaît entre « Supprimer » et « Dupliquer ».
7. Modifier ce champ vers un autre pictogramme (par exemple « Calendrier »), enregistrer.
8. Rouvrir la liste Prospects.
9. **Vérifier** : la 3e icône reflète le nouveau choix.
10. Restaurer « Presse-papier » et enregistrer.
11. Tester aussi de modifier « Menu 3 points » vers « 3 points horizontaux » et vérifier que le menu Prospects suit (avant la 3.20.59, il ne réagissait pas).
