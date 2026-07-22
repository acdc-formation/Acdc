# ACDC SAAS OF — Version 3.20.36

## Objet

Correction de la sauvegarde automatique des largeurs de colonnes des tableaux en front office.

## Correctifs appliqués

- Stabilisation de l'identifiant de chaque tableau.
- Ajout d'une clé de sauvegarde basée sur la page, le contexte du tableau et ses en-têtes.
- Conservation d'une compatibilité avec l'ancienne clé de sauvegarde locale.
- Sauvegarde complète de toutes les largeurs du tableau après redimensionnement.
- Ajout d'un endpoint AJAX dédié à l'enregistrement global d'un tableau complet.
- Rechargement des largeurs sauvegardées en une seule fois pour éviter les recalculs colonne par colonne.
- Conservation du gel des colonnes pendant le redimensionnement afin d'éviter le déplacement des autres colonnes.

## Fichiers modifiés

- `assets/js/acdc-ui-kernel.js`
- `includes/class-acdc-plugin.php`
- `acdc-formation-saas-organisme-de-formation.php`

## Non-régression

- Slug du plugin conservé : `acdc-formation-saas-organisme-de-formation`.
- Sauvegarde globale existante conservée : option WordPress `acdc_of_global_column_widths`.
- Aucun changement volontaire sur le CRM.
- Aucune modification des tables métier.
