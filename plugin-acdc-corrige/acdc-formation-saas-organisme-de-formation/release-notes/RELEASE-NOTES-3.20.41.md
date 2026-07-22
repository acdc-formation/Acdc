# Version 3.20.41

## Module concerné
Entreprises — page Créer / Modifier une entreprise.

## Correction réalisée
- Remplacement du champ texte « Forme juridique » par une liste déroulante.
- Ajout d’une liste étendue de formes juridiques françaises.
- Conservation de la valeur existante si une ancienne valeur n’est pas présente dans la liste.
- Aucun changement de structure de données : le champ `legal_form` reste conservé comme texte.
- Intervention limitée au formulaire Entreprises.

## Base de travail
Version 3.20.40.

## Anti-régression
- Slug conservé : `acdc-formation-saas-organisme-de-formation`.
- Aucun changement volontaire sur le CRM.
- Aucun champ supprimé.
