# ACDC Formation SAAS — version 3.20.62

## Objet

Patch chirurgical en deux corrections, basé sur l'analyse précise de l'inspecteur du navigateur (DOM + CSS calculé) qui a permis d'identifier les causes exactes des deux anomalies persistantes.

## Anomalie 1 — Le réglage « Arrondi boutons d'action » du back office était sans effet

**Symptôme** : sur toutes les pages avec colonne d'actions (Apprenants, Sessions, Entreprises, Formations…), les icônes Voir, Modifier, Supprimer apparaissaient comme des cercles parfaits indépendamment de la valeur réglée dans le back office. Seul le menu trois points (sur certaines pages) obéissait à l'arrondi configuré.

**Cause identifiée par inspection navigateur** : ligne 336 de `assets/css/acdc-components.css`, une règle à très large portée (qui matche `.acdc-row-view-link`, `.acdc-row-edit-link`, `.acdc-row-delete-link` et tous leurs équivalents sur les pages d'actions) imposait `border-radius: 14px !important` avec une **valeur en dur**, alors que la même règle utilisait des variables CSS pour la largeur, la hauteur, la bordure, le fond et la couleur. Sur un bouton de 28-30 px, un `border-radius` de 14 px produit un cercle parfait. Cette ligne incohérente datait d'avant nos travaux et écrasait silencieusement votre réglage.

**Correctif** : `border-radius: 14px !important` devient `border-radius: var(--acdc-action-icon-radius, 14px) !important`. Le fallback à 14 px préserve le comportement par défaut si la variable n'est pas fournie. Quand elle l'est (toujours le cas via le back office), elle prend le dessus.

**Effet attendu** : le réglage Arrondi du back office devient effectif sur toutes les icônes d'actions de listes de toutes les pages.

## Anomalie 2 — L'icône Suivi commercial (Prospects) restait un œil

**Symptôme** : sur la liste Prospects, le bouton Suivi commercial affichait un œil au lieu d'un presse-papier, malgré nos correctifs précédents (3.20.59, 3.20.60, 3.20.61).

**Cause identifiée par inspection navigateur** : le HTML rendu par le PHP Prospects était bien correct — il contenait initialement notre presse-papier. Mais l'attribut `data-acdc-iconized="1"` présent sur le lien révélait qu'**un autre moteur JavaScript décorait le lien après le rendu PHP**, écrasant le SVG presse-papier par un œil.

Ce moteur — composé des fonctions `acdcDecorateActionControl()` et `acdcDecorateMenuTrigger()` dans `admin.js` — était **distinct** du moteur AcdcActionHub que nous avions exclu en 3.20.59. Il opérait via une boucle `acdcIconizeTextActions()` (DOMContentLoaded) qui :

1. Inspectait l'URL et la classe du lien Suivi commercial.
2. Détectait le mot `view` dans `?action=view&...` et concluait `type='view'`.
3. Ajoutait la classe `acdc-row-view-link` au lien Suivi commercial.
4. Écrasait son contenu HTML avec un SVG œil.

Notre exclusion AcdcActionHub de la 3.20.59 n'avait aucun effet sur ce moteur indépendant.

**Correctif** : ajout d'une exclusion explicite Prospects en tête de `acdcDecorateActionControl` et `acdcDecorateMenuTrigger`. Si le nœud est dans `.acdc-prospect-actions`, `.acdc-prospect-action-menu`, `.acdc-prospect-action-dropdown` ou `.acdc-prospect-patch-actions`, ces fonctions sortent silencieusement sans toucher au contenu. Le rendu PHP du module Prospects (qui consulte `ACDC_ACTION_HUB_CONFIG` depuis la 3.20.61) a alors le dernier mot.

**Effet attendu** :
- Le bouton Suivi commercial affiche le glyphe choisi dans Réglages → Système UI → Icônes → « Suivi commercial » (par défaut : presse-papier).
- Si ce réglage est modifié (par exemple « Calendrier »), l'icône suit immédiatement.
- Le menu trois points et les autres boutons Prospects continuent de fonctionner exactement comme avant, avec leurs interactions métier intactes.

## Engagement de préservation

**Aucune fonction métier touchée.** Cette version :
- Ne désactive aucun script.
- Ne modifie aucun handler, aucune fonction `bindMenu`, aucune logique de modale RDV, aucune édition inline.
- Ne touche pas au dropdown 6 entrées du menu Prospects (Répliquer, Ajouter un rendez-vous, Recueil des besoins, Devis, Convention/contrat, Inscrire en formation).

Les deux corrections sont :
1. Le remplacement d'**une valeur littérale** par une référence à variable CSS dans un fichier de style.
2. L'ajout de **deux conditions de sortie anticipée** en tête de deux fonctions JS, sans modifier leur logique pour les autres pages.

## Compatibilité

- Aucun changement de slug, aucun changement de structure de base de données, aucune option modifiée.
- Si pour une raison quelconque l'exclusion ne s'applique pas (par exemple un sélecteur Prospects modifié dans le futur), le comportement retombe sur celui de la 3.20.61.
- Les autres modules (Apprenants, Sessions, Entreprises, Formations, Groupes…) restent décorés par `acdcDecorateActionControl` et `acdcDecorateMenuTrigger` sans changement.

## Vérification recommandée

Sur staging, en partant de la 3.20.61 fonctionnelle :

1. **Purger le cache LiteSpeed** avant tout (sinon le navigateur peut servir l'ancien `admin.js` et l'ancien CSS).
2. Installer la 3.20.62.
3. Forcer un rechargement complet (Ctrl+Shift+R sur Chrome, ou ouverture en navigation privée).
4. Aller dans Réglages → Système UI → Icônes.
5. Modifier « Arrondi boutons d'action » de la valeur actuelle vers 0 (carré). Enregistrer.
6. Ouvrir une liste **non-Prospects** (Apprenants, Sessions, Entreprises). **Vérifier** : tous les boutons d'action sont devenus des carrés (œil, crayon, corbeille, menu trois points).
7. Remettre l'arrondi à 24 (rond complet). Vérifier que tout devient rond.
8. Restaurer la valeur souhaitée.
9. Aller dans la liste Prospects. **Vérifier** : la 3e icône (Suivi commercial) affiche un presse-papier (et non un œil).
10. Dans Réglages, modifier « Suivi commercial » → « Calendrier ». Enregistrer.
11. Retourner sur Prospects. **Vérifier** : la 3e icône affiche maintenant un calendrier.
12. **Tester impérativement** que tout fonctionne encore sur Prospects :
    - Le menu trois points (clic, dropdown 6 entrées).
    - L'ajout de rendez-vous (modale RDV).
    - L'édition inline statut/attribution.
    - Voir, Modifier, Supprimer.
13. Restaurer les valeurs souhaitées.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — version 3.20.62.
- `assets/css/acdc-components.css` — une ligne (border-radius en variable).
- `assets/js/admin.js` — deux conditions de sortie anticipée dans `acdcDecorateActionControl` et `acdcDecorateMenuTrigger`.

**Aucun autre fichier modifié.**

## Si quelque chose ne va pas

Le retour à la 3.20.61 est immédiat — un seul ZIP à réinstaller. Les modifications de cette version sont strictement additives et la moindre régression est très improbable étant donné la précision chirurgicale des deux changements.

## Limite résiduelle (à traiter plus tard si besoin)

L'inspection a révélé que la fonction PHP `get_nav_icon_svg()` ne définit pas explicitement les SVG pour 18 noms de glyphes proposés dans le sélecteur back office (`book`, `building`, `chart`, `check`, `document`, `download`, `finance`, `folder`, `mail`, `plus`, `quality`, `school`, `search`, `send`, `signature`, `upload`, `user`, `warning`). Tous ces noms tombent actuellement sur le SVG par défaut (un cercle avec un « + » dedans).

Concrètement, si vous choisissez « Voir = Document » dans le back office, l'icône Voir affichera ce SVG par défaut au lieu d'un vrai document. Ce point n'a pas été révélé par les tests précédents parce que les options par défaut (`view='eye'`, `edit='edit-pencil'`, `delete='trash-bin'`, `more='more-horizontal'`, `followup='clipboard'`) pointent toutes vers des SVG correctement définis.

Cette limite peut être traitée dans un patch ultérieur si vous souhaitez utiliser ces autres glyphes. Elle ne bloque pas l'usage actuel du plugin.
