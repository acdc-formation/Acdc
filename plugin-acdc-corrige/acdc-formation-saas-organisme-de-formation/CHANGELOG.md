# Changelog

Toutes les modifications notables de ce projet sont documentées ici.
Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).
L'historique détaillé antérieur est archivé dans [`release-notes/`](release-notes/).

## [3.25.109] — 2026-07-22

### Corrigé
- Compatibilité PHP : déclaration de `Requires PHP: 8.2` (en-tête) et `"php": ">=8.2"`
  (composer) — le plugin utilise des constantes de trait (PHP 8.2+). L'ancien `>=7.4`
  était erroné (fatal sur PHP < 8.2).
- `composer.json` : nom de paquet dev corrigé `szepeweb/` → `szepeviktor/phpstan-wordpress`
  (le paquet erroné faisait échouer `composer install` / la CI).
- Matrices CI alignées PHP 8.2/8.3 ; phpcs testVersion 8.2-.

## [3.25.108] — 2026-07-22

### Ajouté
- **Factur-X PDF/A-3** : `build_invoice_facturx_pdf()` (mPDF, XML embarqué + XMP), handler
  `acdc_download_invoice_facturx_pdf`, bouton dédié. `ACDC\Support\FacturXPdf` + 8 tests.
- **Pagination** : `ACDC\Support\Paginator` (13 tests) + `get_prospects_page()` ; liste des
  prospects paginée (LIMIT/OFFSET) avec navigation accessible.
- **Capacités métier** : `ACDC\Support\Capabilities` (6 tests), capacité pivot `acdc_of_manage`
  + 7 caps attribuées aux rôles. `is_admin_manager()` = `manage_options` OU pivot (non régressif).
- **Sessions portail** : `ACDC\Support\SessionFingerprint` (8 tests), liaison IP(/24)+UA
  opt-in via `ACDC_PORTAL_STRICT_SESSION` (apprenant + formateur).

Suite PHPUnit : 82 tests / 195 assertions.

## [3.25.107] — 2026-07-22

### Ajouté
- Facturation électronique : génération du XML **Factur-X** (profil MINIMUM, CII UN/CEFACT)
  d'une facture. `ACDC\Support\FacturX` (pur, 11 tests) + `get_invoice_facturx_xml()` +
  handler `acdc_download_invoice_facturx` (capacité + nonce) + bouton dans le détail facture.
  Suite : 47 tests / 98 assertions. (Incorporation PDF/A-3 : étape suivante.)

## [3.25.106] — 2026-07-22

### Sécurité
- En-têtes de sécurité HTTP sur les pages publiques de document (signature, émargement) :
  CSP `frame-ancestors 'self'` (anti-clickjacking), `X-Frame-Options`, `nosniff`,
  `Referrer-Policy: no-referrer` ; HSTS opt-in (`ACDC_ENABLE_HSTS`). Filtre
  `acdc_of_saas_public_security_headers`. `ACDC\Support\SecurityHeaders` + 5 tests
  (suite : 36 tests / 74 assertions).

## [3.25.105] — 2026-07-22

### Performance
- Fin du N+1 émargement dans les listes de séances (≈400 requêtes/page → 2). Méthodes de
  préchargement en lot `get_by_session_ids` / `get_learners_for_emarg_ids`, câblées sur les
  3 sites (séances validées, statistiques formateurs, dérivation des statuts).

### Ajouté
- `ACDC\Support\EmargeStatus` (dérivation pure des statuts présence/signature) + 9 tests.
  Suite PHPUnit : 31 tests / 62 assertions.

## [3.25.104] — 2026-07-22

### Ajouté
- Bibliothèque de logique pure testable `src/` (`ACDC\Support\Money`, `QuizScore`, `DocumentSeal`),
  autoloadée sans dépendance à `composer install`.
- Suite PHPUnit (`tests/`, `phpunit.xml.dist`) — 22 tests / 45 assertions, couvrant les
  scénarios des bugs H1 (facturation) et H2 (triche quiz).
- Scellement SHA-256 des signatures électroniques : empreinte du document source et du PDF
  signé calculée, stockée (`doc_sha256`, `signed_pdf_sha256`) et journalisée (valeur probante).

### Corrigé
- Anti-triche quiz renforcé : le bornage `min(client, serveur)` du temps de réponse était
  inefficace (`min(0, écoulé)=0`) ; le temps serveur est désormais autoritatif.
- Calcul HT/TVA/TTC devis et facture unifié via `Money` (source unique, verrouille le correctif H1).

## [3.25.103] — 2026-07-22

### Sécurité
- Durcissement de la détection d'IP du portail formateur : `X-Forwarded-For` / `X-Real-IP`
  ignorés sauf proxy de confiance déclaré (`ACDC_TRUSTED_PROXY`).
- Noms de fichiers de signatures d'émargement rendus non devinables (jeton aléatoire) —
  empêche l'énumération par URL (RGPD).

### Performance
- Suppression du `wp_cache_flush()` global déclenché à chaque écriture (n'effondre plus
  l'object cache partagé) ; les purges de cache-page ciblées sont conservées.
- Index ajoutés : `learners(status)`, `learners(updated_at)`, `learners(session_id,status)`,
  `emarg_learners(is_absent)` — suppression de full scans sur les filtres/crons fréquents.

### Accessibilité
- Déblocage du zoom (`maximum-scale=1` retiré) sur la page publique d'émargement (WCAG 1.4.4).

### Outillage / Qualité (DX)
- Ajout de `composer.json` (scripts lint/phpcs/phpcbf/phpstan/test) et des dépendances de dev.
- Configuration `phpcs.xml.dist` (WordPress Coding Standards + sécurité + PHPCompatibility).
- Configuration `phpstan.neon.dist` (analyse statique, stubs WordPress).
- Pipeline CI `.github/workflows/ci.yml` (lint + phpcs + phpstan + matrice PHP 7.4→8.3).
- Génération du modèle de traduction `languages/acdc-formation-saas.pot` (410 entrées).
- Ajout de `README.md`, `.gitignore` ; archivage des notes de version dans `release-notes/`.

## [3.25.102] — 2026-07

### Corrigé
- Facture : numérotation atomique (verrou nommé) anti-doublon.
- Réactivation de la vérification TLS (`sslverify`) sur tous les appels HTTPS.
- Filtres « statut signatures / présence » des séances rendus fonctionnels (état réel d'émargement).
- Rate-limiting sur le « rejoindre » public des questionnaires.

## [3.25.101] — 2026-07

### Corrigé (audit complet — ~45 correctifs)
- **HAUTE** : calcul HT/TVA de facture (frais exclus), score quiz live falsifiable,
  requêtes `GROUP BY` incompatibles MySQL 8, brute-force OTP signature, fatal `implode()`.
- **MOYENNE** : colonnes SQL inexistantes (marketing), tokens émargement sans expiration,
  nonce questionnaire non vérifié, `pdf_color_map_json` écrasé, upload SVG, injection formule
  CSV, avoir/logo facture, doublons d'apprenants, propriétés dynamiques PHP 8.2, `uninstall.php`.
- **BASSE** : path traversal, QR/token e-mail, `confirm()` JS, Zip-Slip, etc.

---

_Versions antérieures (3.20.x → 3.25.100) : voir [`release-notes/`](release-notes/)._

[3.25.103]: #32510302026-07-22
[3.25.102]: #325102-2026-07
[3.25.101]: #325101-2026-07
