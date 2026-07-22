# ACDC SAAS OF — Version 3.20.34

## Objet

Sauvegarde automatique globale des largeurs de colonnes réglées dans le front office.

## Modifications

- Ajout d'une sauvegarde serveur WordPress des largeurs de colonnes via option globale `acdc_of_global_column_widths`.
- Chargement automatique des largeurs serveur dans `acdc-ui-kernel.js`.
- Conservation du repli local `localStorage` si la sauvegarde serveur n'est pas disponible.
- Enregistrement AJAX sécurisé par nonce et réservé aux administrateurs WordPress.
- Application des largeurs enregistrées à tous les utilisateurs qui chargent les tableaux concernés.
- Conservation du slug historique du plugin.

## Périmètre

Intervention limitée au noyau UI des tableaux et au point d'enregistrement serveur nécessaire.

## Non-régression

- Aucun changement volontaire sur le CRM.
- Aucun changement volontaire sur les menus, formulaires, documents ou modules métier.
- Conservation du comportement de redimensionnement existant.
