# RELEASE NOTES — 3.25.103 (Phase 0 : quick wins & socle qualité)

Première phase de la feuille de route « ultra premium » : correctifs sûrs à fort ROI
(sécurité, performance, accessibilité) + mise en place de l'outillage qualité.

## Sécurité / RGPD
- Détection d'IP du portail formateur durcie : `X-Forwarded-For`/`X-Real-IP` falsifiables
  ignorés sauf proxy de confiance déclaré (`ACDC_TRUSTED_PROXY`).
- Signatures d'émargement : noms de fichiers non devinables (jeton aléatoire) contre
  l'énumération par URL.

## Performance
- Suppression du `wp_cache_flush()` global à chaque écriture (n'effondre plus l'object cache).
- Index ajoutés : `learners(status)`, `learners(updated_at)`, `learners(session_id,status)`,
  `emarg_learners(is_absent)`.

## Accessibilité
- Zoom débloqué sur la page publique d'émargement (WCAG 1.4.4).

## Outillage / Qualité (DX)
- `composer.json`, `phpcs.xml.dist` (WPCS), `phpstan.neon.dist`, `.github/workflows/ci.yml`.
- `languages/acdc-formation-saas.pot` généré (410 entrées).
- `README.md`, `CHANGELOG.md`, `.gitignore` ; 85 notes de version archivées dans `release-notes/`.
