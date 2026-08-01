# RELEASE NOTES — 3.25.108 (4 chantiers premium)

## 1. Factur-X — incorporation PDF/A-3
- `build_invoice_facturx_pdf()` : rend la facture en PDF/A-3 (mPDF, `PDFA`+`PDFAauto`) avec
  le XML `factur-x.xml` embarqué en pièce jointe + métadonnées XMP (`ACDC\Support\FacturXPdf`).
  Handler `acdc_download_invoice_facturx_pdf` (capacité + nonce) + bouton « Factur-X (PDF/A-3) ».
  Gardé par `class_exists(\Mpdf\Mpdf)` → message clair si la lib PDF est absente. 8 tests.

## 2. Pagination des listes (pilote : prospects)
- `ACDC\Support\Paginator` (pur, 13 tests) + `get_prospects_page()` (COUNT + `LIMIT/OFFSET`).
  La liste des prospects charge désormais une page bornée (25) avec navigation accessible,
  au lieu de toute la table. Filtres/recherche conservés côté SQL.

## 3. Capacités & rôles métier (fondation non régressive)
- `ACDC\Support\Capabilities` (pur, 6 tests) : capacité pivot `acdc_of_manage` + 7 capacités
  métier, attribuées aux rôles `administrator` et `acdc_portal_admin` (idempotent).
  `is_admin_manager()` accepte désormais `manage_options` OU la capacité pivot (compat totale).

## 4. Durcissement des sessions de portail (opt-in)
- `ACDC\Support\SessionFingerprint` (pur, 8 tests) : liaison session ↔ empreinte IP (/24) +
  User-Agent, activée par la constante `ACDC_PORTAL_STRICT_SESSION` (apprenant + formateur).
  Sessions legacy (sans empreinte) toujours acceptées. Anti-vol de cookie.

Suite PHPUnit totale : **82 tests / 195 assertions**, tout vert. Lint PHP 8.4 : 0 erreur.
