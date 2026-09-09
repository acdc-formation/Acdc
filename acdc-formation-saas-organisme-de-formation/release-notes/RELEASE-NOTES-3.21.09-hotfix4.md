# RELEASE NOTES — 3.21.09-hotfix4

**Date :** 04 mai 2026
**Base :** 3.21.09-hotfix3
**Type :** Amélioration UI transverse

---

## Évolution

### Système de tooltips CSS pur — transverse à tout le plugin

**Fichiers modifiés :**
- `assets/css/acdc-components.css` — règles CSS `[data-tooltip]`
- `assets/js/acdc-ui-system.js` — fonction `initTooltips()` + câblage

**Principe :**

Toutes les icônes d'action du plugin affichent désormais un tooltip
stylisé (fond marine `#0f2c52`, texte blanc, radius 6px, flèche) au
survol et au focus clavier.

**Activation automatique :**

Le JS lit l'attribut `title` existant sur chaque icône d'action et
le migre vers `data-tooltip` (supprimant le `title` natif pour éviter
le double tooltip). Cela couvre l'intégralité des pages sans toucher
aux templates un par un.

**Sélecteur couvert :**
- `.acdc-row-action-icon`
- `.acdc-row-view-link` / `.acdc-row-edit-link` / `.acdc-row-delete-link`
- `.acdc-action-icon` / `.acdc-action-icon-base`
- `.acdc-icon-link`
- `.acdc-table-action-trigger`

**MutationObserver :** `initTooltips()` est branché sur l'observer
existant → les éléments injectés dynamiquement (AJAX, modales) sont
couverts automatiquement.

**Usage futur :** poser `data-tooltip="Texte"` directement sur
n'importe quel élément pour activer le tooltip, sans JS supplémentaire.

---

## Aucune régression

- Hotfix3 (colonne Actions sessions) : conservé
- Hotfix2 (PHP 7.3 + colonnes dynamiques émargement) : conservé
- Aucun template modifié — migration 100 % côté JS
