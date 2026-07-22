# ACDC Formation SAAS — version 3.20.69

## Objet

Correction du bug introduit en 3.20.68 : les sauvegardes existantes (clé `v2:...:cls-...`) étaient ignorées au profit de la nouvelle clé `v3:tid-...`, ce qui faisait disparaître toutes les largeurs précédemment réglées.

## Diagnostic confirmé par inspection

Le diagnostic console (extension Claude) a démontré que :
- Avant 3.20.68 : sauvegardes sous `v2:extranet-tableau-de-bord:cls-acdc-table-prospects` ✅ fonctionnelles.
- 3.20.68 : passage à `v3:tid-crm-prospects-list` sans inclure la clé v2 dans les fallbacks → toutes les sauvegardes existantes deviennent invisibles.
- Le système se rabattait alors sur localStorage (valeurs anciennes), d'où l'impression que « les largeurs ne tiennent pas ».

**La 3.20.66 fonctionnait parfaitement.** Le bug `array_merge` était bien la cause racine, le système de stockage est sain.

## Correction

Quand `data-acdc-table-id` est présent, la clé canonique reste `v3:tid-...`, mais les **clés de fallback** suivantes sont consultées en lecture :

1. **v3** (canonique, neuves sauvegardes après 3.20.68).
2. **v2 cls-** (sauvegardes 3.20.65 à 3.20.67 sous classe métier).
3. **legacy page-table-N** (sauvegardes antérieures à 3.20.65).

Les nouvelles sauvegardes vont sous v3. Les anciennes restent lisibles. Migration douce, aucune perte.

## Engagement

- Aucun PHP modifié.
- Aucun CSS modifié.
- Une seule fonction JS modifiée (`getTableKeys`), branche v3 uniquement.
- Toutes les corrections 3.20.57 → 3.20.68 conservées.
- Les 16 identifiants posés en 3.20.68 restent en place.

## Test

1. Purger LiteSpeed.
2. Installer 3.20.69.
3. Aller sur Prospects. Les largeurs réglées avant 3.20.68 doivent **réapparaître**.
4. Régler une nouvelle largeur, recharger, naviguer ailleurs, revenir : la largeur tient.
5. Console : `window.AcdcUiKernelSettings.columnWidths` — tu verras les anciennes clés v2 cohabiter avec les nouvelles v3, sans interférence.

## Suite

Si la 3.20.69 fonctionne comme prévu, on n'a **plus besoin** des chantiers 3.20.69 (Pilotage) et 3.20.70 (résiduels) prévus initialement avec identifiants partout. Les classes métier et le mode auto suffisent pour les autres tableaux, à condition qu'ils n'aient pas de doublons. On verra au cas par cas si des problèmes apparaissent.
