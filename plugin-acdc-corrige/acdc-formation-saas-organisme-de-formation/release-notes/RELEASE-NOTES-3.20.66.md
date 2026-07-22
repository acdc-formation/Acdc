# ACDC Formation SAAS — version 3.20.66

## Objet

Patch chirurgical en trois corrections coordonnées qui rétablissent **réellement** la stabilité des largeurs de colonnes (la 3.20.65 avait stabilisé les clés mais le bug de fond persistait côté PHP) et corrigent une erreur JavaScript silencieuse qui pouvait perturber d'autres scripts.

## Anomalie n°1 — Bug d'accumulation des largeurs (cause racine)

**Symptôme** : malgré les clés stables fournies par la 3.20.65, les largeurs continuaient de bouger entre les navigations. L'inspection console révélait des **tableaux de 98 entrées** alors qu'un tableau métier n'a au maximum qu'une vingtaine de colonnes.

**Cause identifiée** : dans `includes/class-acdc-plugin.php`, fonction `ajax_save_global_column_widths()` ligne 504, l'instruction :

```php
$widths[ $table_key ] = array_merge( $widths[ $table_key ], $clean_widths );
```

Le piège PHP classique : `array_merge()` sur des tableaux à clés numériques (même castées en string `"0"`, `"1"`...) **renumérote en cascade** au lieu de remplacer par clé. Concrètement :

- Tableau existant : `["0" => 98, "1" => 195, "2" => 185]`
- Nouvelles valeurs : `["0" => 100, "1" => 200, "2" => 190]`
- Résultat `array_merge()` : `[0 => 98, 1 => 195, 2 => 185, 3 => 100, 4 => 200, 5 => 190]`

À chaque sauvegarde, le tableau **s'allongeait** au lieu d'écraser les anciennes valeurs. Après 8 séances de redimensionnement sur un tableau de 12 colonnes, on arrivait à 96 entrées polluées. Au moment de la lecture côté JavaScript, le système prenait la première valeur trouvée pour chaque index, ce qui donnait des largeurs **apparemment aléatoires** : tantôt l'ancienne, tantôt la nouvelle, tantôt une valeur d'une colonne complètement différente.

C'est la cause exacte du « les largeurs ne restent pas stable » que vous m'aviez signalé après la 3.20.65.

**Correctif** : remplacement de `array_merge()` par une boucle explicite qui écrit clé par clé :

```php
foreach ( $clean_widths as $column_index => $column_width ) {
  $widths[ $table_key ][ (string) $column_index ] = $column_width;
}
```

Cette écriture garantit le remplacement par clé et la non-accumulation.

## Anomalie n°2 — Sauvegardes existantes polluées

**Symptôme** : même après la correction n°1, les sauvegardes déjà polluées dans la base (98 entrées pour Prospects par exemple) auraient continué à donner des résultats incohérents.

**Correctif** : ajout d'un mécanisme de nettoyage automatique dans `localize_ui_kernel_settings()` qui détecte et corrige les tableaux trop longs au moment de la lecture :

1. Si un tableau-clé contient plus de 30 entrées (impossible naturellement), le système suspecte une pollution.
2. Une heuristique cherche le motif répétitif (cycle de 5 à 24 colonnes qui se répète) — c'est la signature du bug `array_merge`.
3. Si un cycle est détecté, le système conserve **uniquement le dernier cycle complet**, qui correspond au réglage le plus récent.
4. À défaut de cycle détecté, repli sécuritaire : conservation des 24 dernières valeurs.
5. Le résultat propre est réécrit en base via `update_option`, ce qui purge durablement la pollution.

**Effet pour vous** : à la première visite d'une page après installation de la 3.20.66, vos sauvegardes existantes sont automatiquement nettoyées. Vous récupérez les **dernières valeurs réglées** (les plus pertinentes) et le tableau revient à sa taille normale de 5 à 24 entrées. Aucune action manuelle requise, aucune purge phpMyAdmin nécessaire.

## Anomalie n°3 — Erreur JavaScript `acdcKernel is not defined`

**Symptôme** : dans la console JavaScript, une erreur visible au chargement :

```
Uncaught ReferenceError: acdcKernel is not defined
   at acdcCloseAllProspectMenus (admin.js:201)
```

**Cause identifiée** : dans `assets/js/admin.js`, la variable `acdcKernel` est déclarée dans une IIFE qui se ferme ligne 40. Mais la fonction `acdcCloseAllProspectMenus` ligne 200 est dans une **autre IIFE** qui n'a pas accès à cette déclaration. Le test `if (acdcKernel && ...)` lance donc une `ReferenceError` parce que la variable n'existe pas du tout dans ce scope (ce qui est différent d'être à `null` ou `undefined`).

**Correctif** : remplacement de `if (acdcKernel && ...)` par `if (typeof acdcKernel !== 'undefined' && acdcKernel && ...)`. Le test `typeof` est sécurisé : il ne lance jamais d'erreur, même si la variable n'existe pas. Si elle n'est pas définie, le code retombe proprement sur la branche `else` qui contient déjà une implémentation de repli fonctionnelle.

Même correction appliquée ligne 173 dans `acdcPositionProspectMenu`, qui souffrait du même bug latent (probablement masqué par l'ordre d'exécution mais qui se déclenchait dans certains contextes).

**Effet pour vous** : plus d'erreur dans la console au chargement. Le code de fermeture des menus Prospects continue de fonctionner exactement comme avant — la branche de repli faisait déjà le travail correctement.

## Engagement de préservation

**Aucun CSS modifié, aucune logique métier touchée, aucune fonctionnalité retirée.**

Cette version :
- Modifie deux fichiers : `includes/class-acdc-plugin.php` (logique de sauvegarde et nettoyage) et `assets/js/admin.js` (sécurisation du test `acdcKernel`).
- Conserve toutes les corrections 3.20.57 → 3.20.65.
- Ne touche pas à la structure de l'option `acdc_of_global_column_widths` (mêmes clés, mêmes valeurs).
- Préserve les interactions Prospects (dropdown 6 entrées, modale RDV, édition inline).

## Compatibilité

- Aucun changement de slug.
- Aucun changement de structure de base de données.
- Aucune option supprimée.
- L'option `acdc_of_global_column_widths` est nettoyée automatiquement au premier accès, mais conserve le même format.

## Vérification recommandée

Sur staging, en partant d'une 3.20.65 fonctionnelle :

1. Purger le cache LiteSpeed.
2. Installer la 3.20.66.
3. Recharger en mode privé.
4. Aller sur la liste Prospects. **Première vérification** : ouvrir la console JavaScript et taper :
   ```javascript
   window.AcdcUiKernelSettings.columnWidths['v2:extranet-tableau-de-bord:cls-acdc-table-prospects']
   ```
   Le tableau retourné doit maintenant avoir **moins de 30 entrées** (probablement 12-15 selon le nombre de colonnes Prospects).
5. **Plus aucune erreur** `acdcKernel is not defined` dans la console.
6. Régler les largeurs Prospects à des valeurs très spécifiques (par exemple 400 px sur la première colonne).
7. Recharger immédiatement (F5) — la largeur reste.
8. Naviguer vers Apprenants, régler les largeurs là-bas aussi.
9. Revenir sur Prospects — **les largeurs sont strictement celles que vous aviez réglées**, sans dérive.
10. Refaire 5-6 séances de réglages successifs sur Prospects et vérifier dans la console que le tableau **ne grossit pas** au-delà du nombre de colonnes du tableau.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — version 3.20.66.
- `includes/class-acdc-plugin.php` — correction du bug `array_merge` (ligne 504) + ajout du nettoyage automatique des données polluées.
- `assets/js/admin.js` — sécurisation du test `acdcKernel` lignes 173 et 200.

**Aucun autre fichier modifié.**

## Si quelque chose ne va pas

Le retour à la 3.20.65 est immédiat. Le mécanisme de nettoyage automatique a déjà restauré vos sauvegardes à un état propre, donc même en cas de retour arrière, vous bénéficiez du nettoyage. Le risque est très faible étant donné la précision chirurgicale des trois changements.

## Prochaine étape (3.20.67) — Le cadenas de verrouillage des colonnes

Une fois la 3.20.66 validée, je livrerai le cadenas de verrouillage selon vos choix :
- **Position** : dans la barre de recherche/filtres au-dessus du tableau, à droite.
- **État par défaut** : ouvert (modifiable).
- **Portée** : par tableau métier.
- **Stockage** : côté serveur, nouvelle option `acdc_of_table_locks`.
