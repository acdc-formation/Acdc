# RELEASE NOTES — 3.21.09-hotfix3

**Date :** 04 mai 2026
**Base :** 3.21.09-hotfix2
**Type :** Évolution UI

---

## Évolution

### Colonne "Actions" unifiée dans Séances validées

**Fichier :** `class-acdc-sessions-render-trait.php`

Les 2 dernières colonnes (menu 3 points + icône œil) ont été fusionnées en une seule colonne **"Actions"**, alignée sur le pattern de la page Apprenants.

**Contenu de la colonne Actions :**
| Icône | Action | Classe CSS |
|-------|--------|-----------|
| ✉ Enveloppe (`convocation`) | Envoyer / renvoyer l'email d'émargement au formateur | `acdc-row-action-icon` (form POST) |
| 👁 Œil (`eye`) | Voir la séance | `acdc-row-action-icon acdc-row-view-link` |
| ✏ Crayon (`edit`) | Modifier la séance | `acdc-row-action-icon acdc-row-edit-link` |
| 🗑 Corbeille (`trash`) | Supprimer la séance | `acdc-row-action-icon acdc-row-delete-link` |

**Pattern CSS :** identique à la page Apprenants —
`acdc-actions-cell-icons` + `acdc-sessions-actions-inline` (flex, gap 4px).

**Nettoyage :**
- Suppression du dropdown 3 points (les 3 actions Voir/Modifier/Supprimer sont maintenant des icônes directes)
- Suppression du JS de gestion du dropdown
- Suppression des règles CSS `nth-last-child(2)` et `.acdc-row-menu-dropdown`
- Suppression de la règle `.acdc-row-menu-toggle,.acdc-row-view-link` (override local en conflit avec le système global)

---

## Aucune régression

- Hotfix2 (typed properties PHP 7.3 + colonnes dynamiques) : conservé
- Handler `acdc_emarg_send_trainer` : inchangé (réutilisé par le bouton icône ✉)
- Colonnes Signature formateur et Présence(s) apprenant(s) : inchangées
