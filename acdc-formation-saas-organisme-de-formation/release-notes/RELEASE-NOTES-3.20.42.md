# ACDC Formation SAAS — Version 3.20.42

## Objet

Correction ciblée du champ **Forme juridique** dans le module Entreprises.

## Modifications

- Suppression de la nomenclature juridique longue.
- Conservation uniquement des formes juridiques courantes.
- Maintien du champ `legal_form` existant.
- Conservation de l’affichage de la valeur actuelle si une ancienne valeur enregistrée n’est pas dans la nouvelle liste.
- Intervention limitée aux pages **Créer une entreprise** et **Modifier une entreprise**.

## Non-régression

- Slug du plugin conservé.
- Aucun champ supprimé dans le formulaire Entreprises.
- Aucune modification du CRM.
