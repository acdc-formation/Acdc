# RELEASE NOTES — 3.25.106 (Phase 1 : en-têtes de sécurité HTTP)

## Sécurité
- Durcissement HTTP des pages publiques de document (signature `?sig=`, émargement
  `?acdc_emarg=`), qui exposent des documents sans authentification :
  - `Content-Security-Policy` (dont `frame-ancestors 'self'` — anti-clickjacking, et
    `object-src 'none'`, `form-action 'self'`, `base-uri 'self'`).
  - `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`.
  - `Referrer-Policy: no-referrer` (évite la fuite de l'URL/token du document).
  - `Strict-Transport-Security` en opt-in via la constante `ACDC_ENABLE_HSTS` (HTTPS uniquement).
- En-têtes ajustables via le filtre `acdc_of_saas_public_security_headers`.
- Logique construite dans `ACDC\Support\SecurityHeaders` (pur, 5 tests PHPUnit).
  Suite totale : 36 tests / 74 assertions.

Note : la CSP conserve `'unsafe-inline'` (styles/scripts en ligne des pages publiques) ;
elle pourra être resserrée une fois ces éléments externalisés (item UX du rapport premium).
