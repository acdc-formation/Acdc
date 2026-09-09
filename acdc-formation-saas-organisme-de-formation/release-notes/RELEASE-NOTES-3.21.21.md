# RELEASE NOTES — 3.21.21

## Refonte du tableau de bord général

### Ce qui change

Le tableau de bord a été entièrement restructuré selon le principe
**"alertes actives en haut, toujours"**.

### Nouvelle architecture

**Zone 1 — Alertes actives**
- Seuls les items avec compteur > 0 remontent en haut (rouge/orange)
- Si aucune alerte : message vert "Tout est à jour"
- Alertes couvertes : tests de positionnement, évaluations des acquis,
  quiz, analyses du besoin, prospects en retard, toutes les enquêtes

**Zone 2 — KPIs répertoire** (6 cartes : Entreprises, Prospects, Apprenants, Formations, Sessions, Documents)

**Zone 3 — Grille 2×2**
- Workflow opérationnel : checklist complète avec point couleur (vert/orange/rouge)
- Indicateurs de performance : satisfaction, taux de retour, taux de réussite, réponses reçues
  — données réelles issues des enquêtes à chaud et des évaluations
- Prochaines sessions : 4 sessions à venir avec date, titre, nombre d'apprenants
- Dossiers récemment modifiés

**Zone 4 — Bande Qualiopi**
- Statut visuel par type (vert = conforme, orange = à traiter)
- Lien direct vers l'Audit Qualiopi

### Fichier modifié
- `includes/kernel/class-acdc-kernel-render-trait.php`
  - `render_front_dashboard_tab()` entièrement réécrite
  - `render_stat_card()` et `render_workflow_card()` supprimées (remplacées par la nouvelle logique)
  - PHP 7.3 compatible · Lint OK · 0 collision traits

