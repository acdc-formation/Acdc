# ACDC Formation SAAS — version 3.20.65

## Objet

Patch chirurgical qui résout définitivement la perte des largeurs de colonnes lors de la navigation entre tableaux. Trois corrections coordonnées dans `acdc-ui-kernel.js` qui rendent l'identifiant de chaque tableau métier stable, indépendant de l'URL, des paramètres GET et des variations contextuelles.

## Anomalie corrigée

**Symptôme** : quand vous régliez les largeurs de colonnes d'un tableau (Prospects, Apprenants, Entreprises, etc.), puis que vous naviguiez vers un autre tableau et reveniez, les réglages avaient bougé ou disparu. Le système semblait sauvegarder les largeurs, mais ne les retrouvait plus systématiquement au retour.

**Cause identifiée par inspection navigateur** : l'inspection de `window.AcdcUiKernelSettings.columnWidths` a révélé un nombre anormalement élevé de clés sauvegardées, dont beaucoup étaient des **variantes du même tableau** :

```
v2:extranet-tableau-de-bord-tab-companies:auto-zone-siret-...
v2:extranet-tableau-de-bord-tab-companies-action-view-item-id-2:auto-brasserie-...
v2:extranet-tableau-de-bord-tab-companies-action-view-item-id-4:auto-david-...
v2:extranet-tableau-de-bord-tab-formations:auto-zone-id-statut-parcours-qualiopi-...
v2:extranet-tableau-de-bord-tab-formations:auto-zone-id-statut-qualiopi-intitule-...
```

Trois sources d'instabilité étaient combinées dans le calcul de l'identifiant :

1. **`window.location.search` faisait partie de la clé de page**. Donc dès qu'un paramètre GET changeait (ouverture d'une fiche, application d'un filtre, retour depuis une page enfant), la clé du tableau changeait aussi, alors qu'il s'agissait du même tableau métier.

2. **La signature d'en-têtes incluait les 14 premières colonnes**. Donc si une colonne optionnelle apparaissait/disparaissait selon le contexte (filtre actif, permission différente, mode édition vs lecture), la signature complète changeait et la clé devenait introuvable.

3. **Les tables métier n'avaient pas d'identifiant prioritaire**. Le système retombait toujours sur un mode `auto-` qui combine zone + signature + index, et ces trois éléments peuvent tous varier avec le contexte.

**Effet cumulé** : pour un même tableau métier comme « Entreprises », il pouvait y avoir 5 à 10 entrées différentes dans la sauvegarde, chacune correspondant à un contexte de navigation particulier. Vous régliez les largeurs sur une de ces entrées, puis le système en lisait une autre au retour, donnant l'impression que les réglages avaient bougé.

## Corrections appliquées

### Correction 1 — `getPageKey()` ne dépend plus de `window.location.search`

Avant :
```javascript
return sanitizeKeyPart(path + '|' + search + '|' + titleText, 'page');
```

Après :
```javascript
return sanitizeKeyPart(path + '|' + titleText, 'page');
```

Effet : un paramètre `?action=view&item_id=5` n'a plus d'impact sur la clé. La liste et toute fiche ouverte depuis la liste partagent désormais la même clé de page.

### Correction 2 — `getTableHeaderSignature()` limitée à 3 en-têtes au lieu de 14

Trois en-têtes suffisent largement à distinguer deux tableaux différents qui coexisteraient dans une même page. Cette limitation rend la signature **tolérante** aux variations mineures de structure (ajout d'une colonne optionnelle au milieu ou en fin de tableau, par exemple).

### Correction 3 — Détection automatique de la classe métier

Toutes les tables métier du plugin suivent l'un de deux patterns de nommage stables :
- `acdc-{nom}-table` (exemples : `acdc-companies-table`, `acdc-learners-table`, `acdc-groups-table`)
- `acdc-table-{nom}` (exemples : `acdc-table-prospects`, `acdc-table-needs-documents`, `acdc-table-attendance-sheets`)

Une nouvelle fonction `getBusinessTableClass()` détecte automatiquement cette classe métier, et `getTableKeys()` l'utilise comme **source de clé prioritaire** avant de retomber sur le mode auto. Concrètement, l'ordre de priorité devient :

1. Attribut `data-acdc-table-key` explicite (si présent dans un wrapper).
2. Attribut `id` de la `<table>` (si présent).
3. **[Nouveau]** Classe métier détectée (`acdc-companies-table`, `acdc-table-prospects`, etc.).
4. Mode `auto-` en dernier recours (fallback historique).

Pour chaque tableau métier, la clé devient ainsi quasi-immutable : elle ne dépend plus que du chemin, du titre, et de la classe métier. Trois éléments qui changent rarement.

## Effet attendu

Vous pouvez désormais régler les largeurs des colonnes sur Prospects, naviguer vers Apprenants, régler aussi les largeurs là-bas, ouvrir une fiche détaillée, appliquer un filtre, revenir sur Prospects... Les largeurs réglées sur chaque tableau **restent strictement liées à ce tableau**, indépendamment du contexte de navigation.

## Migration des données existantes

Le mécanisme de `legacyKey` déjà en place permet une transition douce. Quand une nouvelle clé canonique est calculée (avec les corrections 3.20.65), si elle ne trouve pas de largeur sauvegardée, le système consulte aussi la clé legacy. Ainsi :

- **Vos réglages déjà sauvegardés restent disponibles** au moins jusqu'à la première modification.
- **À la première modification de largeur après mise à jour**, la nouvelle clé stable est utilisée et toutes les modifications futures se feront sous cette nouvelle clé propre.
- Progressivement, les anciennes entrées polluées resteront dans la base mais ne seront plus consultées en priorité.

Si vous souhaitez purger complètement les anciennes entrées polluées, vous pouvez le faire manuellement via une requête WP-CLI ou via phpMyAdmin sur la table `wp_options`, en supprimant l'entrée `acdc_of_global_column_widths` puis en recommençant à zéro vos réglages. Mais ce n'est **pas nécessaire** : le système fonctionnera correctement avec les anciennes entrées présentes.

## Engagement de préservation

**Aucun PHP modifié, aucun CSS modifié, aucune logique métier touchée.**

Cette version :
- Modifie un seul fichier : `assets/js/acdc-ui-kernel.js`.
- Conserve toutes les fonctions existantes du noyau UI (mesure, application, sauvegarde, redimensionnement par poignée).
- Conserve la compatibilité avec le système legacy (les anciennes clés restent lisibles).
- Préserve toutes les corrections 3.20.57 → 3.20.64 (icônes, alignement, etc.).

## Compatibilité

- Aucun changement de slug.
- Aucun changement de structure de base de données.
- Aucune option modifiée (l'option `acdc_of_global_column_widths` continue de stocker les largeurs sous le même format).
- Le format des clés évolue, mais le code lit l'ancien et le nouveau.

## Vérification recommandée

Sur staging, en partant d'une 3.20.64 fonctionnelle :

1. Purger le cache LiteSpeed.
2. Installer la 3.20.65.
3. Recharger en mode privé (ou Ctrl+Shift+R).
4. Aller sur la liste **Prospects**. Régler les largeurs de plusieurs colonnes (les rendre très différentes du défaut pour bien voir l'effet).
5. Aller sur la liste **Apprenants**. Régler aussi les largeurs ici.
6. Aller sur la liste **Entreprises**. Régler aussi les largeurs.
7. Ouvrir une fiche détaillée d'une entreprise (`?action=view&item_id=X`).
8. Revenir à la liste **Entreprises**. Vérifier que les largeurs sont **identiques à ce que vous aviez réglé**.
9. Naviguer vers **Prospects** puis **Apprenants** puis **Prospects** à nouveau. Vérifier à chaque retour que les largeurs sont **stables**.
10. Ouvrir la console JavaScript (F12 → Console) et taper :
    ```
    window.AcdcUiKernelSettings.columnWidths
    ```
    Examiner les nouvelles clés. Elles devraient maintenant utiliser le format `cls-acdc-{nom}-table` (ou `cls-acdc-table-{nom}`) au lieu du format `auto-...`.

## Pour confirmer techniquement la prise d'effet

Dans la console JavaScript, sur la liste Entreprises :

```javascript
const table = document.querySelector('.acdc-companies-table');
window.AcdcUiKernel.getTableKeys(table, 0);
```

Vous devriez voir une clé du type :
```
["v2:extranet-tableau-de-bord-acdc-formation:cls-acdc-companies-table", "page-table-0"]
```

Le premier élément (clé canonique) doit contenir `cls-acdc-companies-table`. Si c'est le cas, la correction est active.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — version 3.20.65.
- `assets/js/acdc-ui-kernel.js` — trois fonctions modifiées (`getPageKey`, `getTableHeaderSignature`, `getTableKeys`), une nouvelle fonction ajoutée (`getBusinessTableClass`).

**Aucun autre fichier modifié.**

## Si quelque chose ne va pas

Le retour à la 3.20.64 est immédiat — un seul ZIP à réinstaller. Les sauvegardes existantes ne sont pas perdues : elles restent dans l'option `acdc_of_global_column_widths` et seront relues normalement par la 3.20.64.
