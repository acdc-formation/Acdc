# ACDC Formation SAAS — version 3.20.63

## Objet

Patch chirurgical d'une seule règle CSS pour corriger le décalage vertical des cellules d'actions dans tous les tableaux du plugin. Quand une ligne contient une colonne textuelle multi-lignes (formation longue, adresse, motif…), les boutons d'action apparaissaient « collés en haut » de la cellule au lieu d'être centrés verticalement par rapport au texte. Ce patch corrige ce désalignement sans toucher aux autres colonnes.

## Anomalie corrigée

**Symptôme** : sur les listes Prospects, Apprenants, Sessions, Entreprises, Formations, Groupes et tout tableau ACDC contenant une colonne textuelle multi-lignes, les boutons d'action de la dernière colonne apparaissaient désalignés vers le haut.

Concrètement, sur la liste Prospects, une ligne avec « Maîtriser l'hygiène alimentaire en restauration commerciale » (texte sur deux lignes) faisait monter la hauteur totale de la ligne à environ 50 px. Les boutons d'action, hauts d'environ 30 px, étaient collés en haut de cette cellule, ce qui créait une impression de désynchronisation visuelle entre le texte des autres colonnes (en haut) et les icônes d'action (qui paraissaient flotter au-dessus du milieu).

**Cause identifiée** : la règle générale des tableaux ACDC dans `assets/css/tables.css` ligne 20 impose `vertical-align: top` sur **toutes** les `<td>`. Cette règle est cohérente pour les colonnes textuelles (le texte commence en haut, ce qui est lisible quand on parcourt le tableau verticalement). Mais elle s'applique aussi à la colonne Actions, qui devrait elle être centrée verticalement.

**Correctif** : ajout d'une règle ciblée dans `assets/css/acdc-components.css` qui force `vertical-align: middle !important` uniquement sur les cellules d'actions, en couvrant les trois cas connus :

1. **Cellules avec classe d'actions explicite** (`.acdc-actions-cell-icons`, `.acdc-actions-cell`, `.column-actions`) — utilisées par Apprenants, Sessions, Entreprises, Groupes, Formations.
2. **Cellules décorées dynamiquement par AcdcActionHub** (`.acdc-action-hub-cell`) — posée au runtime par le JavaScript.
3. **Cellules Prospects** dont la `<td>` n'a aucune classe spécifique (le code historique fait juste `<td><div class="acdc-prospect-actions">...</div></td>`). Pour ces cas, le sélecteur `:has(> .acdc-prospect-actions)` cible la `<td>` parente.

Le sélecteur `:has()` est utilisé pour les cas Prospects et autres conteneurs internes. Il est supporté par tous les navigateurs courants en avril 2026 (Chrome 105+ depuis août 2022, Firefox 121+ depuis décembre 2023, Safari 15.4+ depuis mars 2022, Edge 105+).

## Engagement de préservation

**Aucune fonction métier touchée. Aucun fichier JavaScript modifié.**

Cette version :
- Ne désactive aucun script.
- Ne modifie aucune logique de rendu PHP.
- Ne touche pas aux interactions Prospects (dropdown 6 entrées, modale RDV, édition inline).
- N'altère pas l'alignement des autres colonnes (qui restent en `vertical-align: top`, conformément à la règle générale).

La correction est strictement additive : une règle CSS supplémentaire qui ne s'active que sur les cellules contenant des actions.

## Effet attendu

Sur tous les tableaux du plugin, les boutons d'action sont maintenant **centrés verticalement** dans leur cellule, alignés visuellement avec le texte des autres colonnes (qui reste en haut, comme avant). Le décalage perçu disparaît.

## Compatibilité

- Aucun changement de slug, aucun changement de structure de base de données, aucune option modifiée.
- Si pour une raison quelconque la règle ne s'appliquait pas (par exemple sur un navigateur très ancien qui ne supporte pas `:has()`, cas extrêmement rare en 2026), le comportement retombe sur celui de la 3.20.62 (les boutons restent en haut, comme avant). Aucune régression.
- Les sélecteurs `body .acdc-table td.acdc-actions-cell-icons` etc. n'utilisent pas `:has()` et fonctionnent partout.

## Vérification recommandée

Sur staging, en partant d'une 3.20.62 fonctionnelle :

1. Purger le cache LiteSpeed.
2. Installer la 3.20.63.
3. Recharger en mode privé (ou Ctrl+Shift+R).
4. Ouvrir la liste Prospects. Vérifier que les boutons d'action sont **centrés verticalement** sur les lignes où le titre de formation est long (sur deux lignes).
5. Faire de même sur Apprenants, Sessions, Entreprises, Groupes, Formations.
6. Vérifier que les **autres colonnes textuelles** restent bien alignées en haut (le texte commence au sommet de la cellule, comme avant).
7. Vérifier que toutes les fonctions métier continuent de marcher : ouverture des modales, dropdowns, édition inline, suppressions, etc.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — version 3.20.63.
- `assets/css/acdc-components.css` — ajout d'une règle CSS de 12 lignes (commentaire inclus).

**Aucun autre fichier modifié.**

## Si quelque chose ne va pas

Le retour à la 3.20.62 est immédiat — un seul ZIP à réinstaller. La correction étant une simple règle CSS supplémentaire, le risque de régression est très faible.
