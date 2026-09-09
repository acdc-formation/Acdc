# RELEASE NOTES — 3.21.20

## Refonte UI pages enquêtes (6 types)

### Ce qui change

Les 6 pages enquêtes (intermédiaire, à chaud, à froid, formateurs, entreprises, financeurs)
ont été entièrement refondues avec une architecture 3 sous-onglets unifiée.

### Nouvelle architecture

**Section head** : titre + sous-titre Qualiopi + boutons (Depuis le modèle / + Nouvelle enquête / Paramètres)

**4 KPIs** (toujours visibles) : Envois · Réponses · Taux de retour · Score moyen

**Sous-onglet 1 — Mes enquêtes** (`srv_subtab=mes_enquetes`)
- Tableau liste avec menu 3 points (Voir résultats · Gérer envoi · Relancer · Dupliquer · Exporter · Archiver · Supprimer)
- Icônes Voir + Modifier + Supprimer inline (pattern certifié module Apprenants)

**Sous-onglet 2 — Envois & suivi** (`srv_subtab=envois`)
- Tableau des sessions avec badges statut acdc-sig-badge (Envoyée / Répondue / Expirée / Planifiée)
- Cadenas colonnes (acdc-table-lock-toggle)
- Actions : Voir résultats / Relancer

**Sous-onglet 3 — Résultats & preuves** (`srv_subtab=resultats`)
- Bandeau : scores → Statistiques indicateurs de performance
- Bandeau Qualiopi archive
- 3 KPIs synthèse (score moyen, taux participation, alertes)
- Lien vers résultats détaillés
- Export CSV + Export PDF Qualiopi

### Conformité design system
- Icônes : 30px frame · 16px glyphe · #d6a353 · bordure #f1dcc0 · fond #ffffff
- Cadenas ouvert : icône #C5A253 · fond #ffffff
- Cadenas fermé : fond #C5A253 · icône #ffffff
- Badges : classes acdc-sig-badge officielles (jamais inline)
- Navigation : srv_subtab (jamais tab brut)

### Fichier modifié
- `includes/questionnaires/class-acdc-questionnaires-render-trait.php`
  - 10 nouvelles fonctions privées partagées
  - 6 anciens render functions remplacés par wrappers 2 lignes
  - PHP 7.3 compatible · Lint OK · 0 collision traits

