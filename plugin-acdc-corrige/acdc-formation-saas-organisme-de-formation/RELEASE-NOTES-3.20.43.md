# ACDC Formation SAAS — Version 3.20.43

## Module concerné
Entreprises / Contacts annexes.

## Corrections

- Remplissage automatique du champ **Signature documents** à partir du prénom, du nom et de la qualité du signataire.
- Sécurisation côté serveur : la valeur enregistrée est recalculée à l’enregistrement de l’entreprise.
- Suppression de la duplication de la qualité du signataire dans le tableau Entreprises.
- Affichage renforcé des contacts annexes dans la fiche entreprise.
- Après ajout d’un contact depuis une entreprise, redirection directe vers la fiche de l’entreprise concernée.
- Affichage du nom, de la fonction, de l’e-mail et du téléphone dans le bloc Contacts liés.

## Contraintes respectées

- Base incrémentale 3.20.42.
- Slug conservé : `acdc-formation-saas-organisme-de-formation`.
- Aucun changement volontaire sur le CRM.
- Intervention limitée aux pages Entreprises / Contacts liés.
