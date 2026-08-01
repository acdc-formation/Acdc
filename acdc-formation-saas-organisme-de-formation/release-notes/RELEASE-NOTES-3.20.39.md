# ACDC SAAS OF — Version 3.20.39

## Base
Version 3.20.38 validée comme base de travail.

## Périmètre
Page front office : Créer une entreprise.

## Corrections
- Suppression de la liste des entreprises sous le formulaire de création.
- Ajout des champs manquants visibles dans le modèle fourni :
  - Code NAF ;
  - Code NAFA ;
  - Code APRN ;
  - complément d’adresse ;
  - prénom du signataire ;
  - nom du signataire ;
  - qualité du signataire ;
  - signature documents ;
  - destinataires en copie ;
  - téléphone signataire / entreprise ;
  - contacts annexes ;
  - alerte contact ;
  - commentaire.
- Conservation des champs déjà présents :
  - forme juridique ;
  - site web.
- Conservation de la logique en deux colonnes.
- Ajout des colonnes SQL nécessaires à la table des entreprises avec migration incrémentale.
- Conservation du slug historique du plugin.

## Hors périmètre
Aucune modification volontaire du CRM.
