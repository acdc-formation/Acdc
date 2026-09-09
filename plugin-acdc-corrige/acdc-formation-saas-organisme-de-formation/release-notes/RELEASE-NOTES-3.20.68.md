# ACDC Formation SAAS — version 3.20.68

## Objet

Patch n°1 sur 3 de la grande campagne d'attribution d'identifiants uniques aux tableaux du plugin. Cette version traite **les 16 listes principales** que tu utilises au quotidien : Prospects, Suivi commercial, Apprenants, Entreprises, Groupes, Formateurs, Financeurs, Utilisateurs, Sessions, Programmes, Catalogue, Devis, Factures, Historique RDV, Pré-réunions.

Les patchs 3.20.69 et 3.20.70 traiteront ensuite les tableaux du Pilotage qualité/amélioration et les tableaux résiduels.

## Pourquoi ce chantier

Malgré la 3.20.65 qui rendait les clés stables et la 3.20.66 qui corrigeait l'accumulation `array_merge`, les largeurs de colonnes continuaient à se mélanger entre tableaux. La cause finale : plusieurs tableaux **partagent la même classe métier** (par exemple les 3 versions de Prospects partagent `acdc-table-prospects`), et **la grande majorité des tableaux n'a aucune classe métier propre** — ils utilisent juste la classe générique `acdc-table`.

Résultat : impossible pour le système de distinguer un tableau d'un autre quand on navigue entre eux. Régler les largeurs sur Prospects écrasait les largeurs du Suivi commercial. Régler le Pilotage écrasait les largeurs des autres tableaux Pilotage.

**Seule solution propre** : un identifiant unique stable par tableau, posé directement sur la balise HTML.

## Mécanisme mis en place

Un nouvel attribut `data-acdc-table-id="{identifiant-unique}"` est ajouté sur chaque balise `<table>`. Côté JavaScript, cet attribut devient la **source absolue** de la clé de sauvegarde, court-circuitant complètement les calculs précédents (URL, titre, classe métier, signature d'en-têtes, index dans le DOM).

La clé canonique passe en version 3 : `v3:tid-{identifiant}`. Exemple : `v3:tid-crm-prospects-list`.

Cette clé est :
- **Indépendante de l'URL** (filtres, paramètres GET, tab actif).
- **Indépendante du titre de page**.
- **Indépendante des en-têtes du tableau** (ajout/retrait de colonnes optionnelles).
- **Unique au tableau** (deux tableaux différents ne peuvent jamais partager la même clé).

Pour les tableaux qui n'ont pas encore d'identifiant unique (à venir en 3.20.69 et 3.20.70), le système retombe automatiquement sur l'ancienne logique (3.20.65). Aucune régression.

## Liste des 16 tableaux identifiés en 3.20.68

| Identifiant | Tableau | Fichier |
|---|---|---|
| `crm-prospects-list` | Liste Prospects principale | crm-commercial-render-trait.php |
| `crm-prospects-followup` | Suivi commercial | crm-commercial-render-trait.php |
| `crm-rdv-history` | Historique RDV (fiche prospect) | crm-commercial-render-trait.php |
| `learners-list` | Annuaire Apprenants | learners-contacts-render-trait.php |
| `companies-list` | Annuaire Entreprises | kernel-render-trait.php |
| `groups-list` | Annuaire Groupes | kernel-render-trait.php |
| `trainers-list` | Annuaire Formateurs | kernel-render-trait.php |
| `funders-list` | Annuaire Financeurs | kernel-render-trait.php |
| `users-list` | Annuaire Utilisateurs | auth-portal-render-trait.php |
| `sessions-validated-list` | Sessions validées | sessions-render-trait.php |
| `sessions-pending-list` | Sessions en attente | sessions-render-trait.php |
| `sessions-pre-meetings` | Réunions préparatoires | kernel-render-trait.php |
| `training-programs-list` | Programmes de formation | kernel-render-trait.php |
| `catalog-list` | Catalogue de formations | settings-catalog-render-trait.php |
| `quotes-list` | Liste des devis | documents-billing-render-trait.php |
| `invoices-list` | Liste des factures | documents-billing-render-trait.php |

## Convention de nommage

Format choisi pour faciliter la traçabilité dans le code : `{contexte-fonctionnel}-{nature}` en français court.

- `crm-*` pour le CRM commercial.
- `*-list` pour les listes principales.
- Les sous-vues d'un même module portent un suffixe explicite (`prospects-followup`, `prospects-list`, `rdv-history`).
- Les modules transverses (apprenants, entreprises, groupes…) gardent leur nom principal sans préfixe.

Si tu veux savoir où trouver un tableau dans le code, l'identifiant t'aiguille directement : `sessions-validated-list` → fichier `sessions/`, fonction de rendu de la liste validée.

## Migration des largeurs existantes

Le système conserve le **fallback legacy** : si une largeur a été sauvegardée sous l'ancienne clé (avant 3.20.68), elle reste lisible une dernière fois. Dès la première modification après mise à jour, la nouvelle clé `v3:tid-...` prend le relais et toutes les modifications futures se font sous cette clé propre et unique.

Aucune perte de données. Aucune action manuelle requise.

## Engagement de préservation

**Aucun comportement métier modifié. Aucune logique de rendu altérée.**

Les seules modifications sont :
- L'ajout d'un attribut HTML `data-acdc-table-id="..."` sur les 16 balises `<table>` ciblées.
- L'ajout dans le JavaScript d'une branche prioritaire qui lit cet attribut comme source absolue de clé.

Toutes les corrections 3.20.57 → 3.20.67 sont conservées. Toutes les fonctionnalités (cadenas, indicateur de largeur, logs de diagnostic) restent actives.

## Vérification recommandée

Sur staging, en partant d'une 3.20.67 fonctionnelle :

1. Purger le cache LiteSpeed.
2. Installer la 3.20.68.
3. Recharger en mode privé.
4. Aller sur Prospects. Régler la première colonne à 280 px précisément (l'indicateur de largeur permet de viser).
5. Console JavaScript : `window.AcdcUiKernelSettings.columnWidths` — chercher la nouvelle clé `v3:tid-crm-prospects-list`. Elle doit contenir 280 en première position.
6. Aller sur Sessions. Régler aussi quelques colonnes.
7. Revenir sur Prospects. **La première colonne doit être restée à 280 px.**
8. Aller sur Suivi commercial (depuis le menu CRM). Régler des colonnes.
9. Revenir sur Prospects. Toujours 280 px ? Oui — les deux tableaux ont maintenant des identifiants distincts (`crm-prospects-list` vs `crm-prospects-followup`).
10. Refaire la même chose sur tous les autres tableaux du périmètre 1 pour valider.

Si tout est stable sur le périmètre 1, je livre la 3.20.69 (Pilotage qualité/amélioration/évaluations) puis la 3.20.70 (résiduels).

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — version 3.20.68.
- `assets/js/acdc-ui-kernel.js` — branche prioritaire pour `data-acdc-table-id`.
- `includes/auth-portal/class-acdc-auth-portal-render-trait.php` — `users-list`.
- `includes/crm-commercial/class-acdc-crm-commercial-render-trait.php` — 3 identifiants (prospects-list, followup, rdv-history).
- `includes/documents-billing/class-acdc-documents-billing-render-trait.php` — quotes-list, invoices-list.
- `includes/kernel/class-acdc-kernel-render-trait.php` — companies, groups, trainers, funders, pre-meetings, programs.
- `includes/learners-contacts/class-acdc-learners-contacts-render-trait.php` — learners-list.
- `includes/sessions/class-acdc-sessions-render-trait.php` — sessions-validated, sessions-pending.
- `includes/settings-catalog/class-acdc-settings-catalog-render-trait.php` — catalog-list.

**Aucun autre fichier modifié.**

## Si quelque chose ne va pas

Le retour à la 3.20.67 est immédiat. Les identifiants posés ne créent aucune dépendance : si on retire la 3.20.68, le système retombe automatiquement sur les classes métier comme avant. Aucun risque de régression durable.

## Prochaines étapes

- **3.20.69** : tableaux du module Pilotage qualité/amélioration (BPF, articles veille, RSS, incidents, plan d'action, alertes…) et évaluations/questionnaires. Estimé entre 12 et 15 tableaux.
- **3.20.70** : tableaux résiduels (documents, dossiers, fiches détaillées, configuration, journaux d'événements…). Estimé entre 10 et 15 tableaux.

À l'issue des trois patchs, **chaque tableau du plugin** aura sa propre identité unique et stable, avec son cadenas indépendant et ses largeurs propres.
