# ACDC SAAS OF — Version 3.20.35

## Objet

Correction ciblée du redimensionnement des colonnes dans le front office.

## Corrections

- Suppression du comportement d’agrandissement automatique au simple clic sur une poignée de colonne.
- Déclenchement du redimensionnement uniquement après un déplacement réel de la souris.
- Gel des largeurs réelles de toutes les colonnes avant modification.
- Modification limitée à la colonne ciblée, sans redistribution brutale des autres colonnes.
- Conservation de la sauvegarde serveur globale introduite en 3.20.34.
- Conservation du repli local navigateur si la sauvegarde serveur échoue.
- Zone de prise en main légèrement élargie pour rendre le réglage plus précis.

## Périmètre

- Fichier principal modifié : `assets/js/acdc-ui-kernel.js`.
- Ajustement serveur minimal : borne basse de largeur cohérente avec le nouveau comportement.
- Aucun changement volontaire sur le CRM validé.
- Slug conservé : `acdc-formation-saas-organisme-de-formation`.
