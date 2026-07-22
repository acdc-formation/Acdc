# Rapport d'améliorations — Vers un plugin ACDC Formation « ULTRA PREMIUM »

_Plugin ACDC Formation SAAS Organisme de formation — v3.25.102_
_Analyse exhaustive : 120 fichiers PHP (~125 600 lignes), 13 JS, 18 CSS, ~61 tables, sur 6 dimensions._

---

## 0. Résumé exécutif

Le plugin est **fonctionnellement remarquable** — un ERP d'organisme de formation complet (CRM, propositions, marketing, sessions, quiz live, questionnaires, dossiers/contrats, facturation, conformité Qualiopi/BPF, émargement, signature électronique, portails apprenant/formateur, veille IA multi-modèles). Sur le plan **produit**, il rivalise déjà avec Digiforma/Dendreo, avec deux atouts rares : la **veille réglementaire IA** et le **quiz live temps réel**.

Le passage à « ultra premium » ne concerne donc pas les fonctionnalités, mais les **fondations** : architecture, performance à l'échelle, valeur probante juridique, accessibilité réglementaire, et outillage qualité. L'historique (85 notes de version, 12 hotfix hors-semver, régressions à répétition) confirme empiriquement l'absence de filet de sécurité technique.

### Scorecard de maturité

| Dimension | Niveau actuel | Cible premium | Constat clé |
|-----------|:---:|:---:|-------------|
| **Fonctionnalités / Produit** | ★★★★☆ | ★★★★★ | Très riche, proche des leaders ; manquent e-invoicing 2026, LMS/SCORM, multi-tenant |
| **Sécurité & RGPD** | ★★★☆☆ | ★★★★★ | Bonne hygiène anti-bug ; lacunes structurelles (permissions binaires, valeur probante, chiffrement) |
| **UX / UI / Accessibilité** | ★★★☆☆ | ★★★★★ | Base design-system rare + inspecteur ; a11y réglementaire à risque (zoom, contrastes) |
| **Architecture / Code** | ★★☆☆☆ | ★★★★★ | God Object de 68 traits ; méthode de 1 618 lignes ; 0 namespace |
| **Performance / Scalabilité** | ★★☆☆☆ | ★★★★★ | Purge cache globale par écriture, N+1, listes non paginées |
| **Qualité / Outillage / DX** | ★☆☆☆☆ | ★★★★★ | Zéro test, CI, Composer, phpstan, .pot — tout est manuel |

### Les 10 chantiers structurants (transverses, par ordre de valeur)

1. **Socle qualité** : Composer + PSR-4 + PHPStan + PHPCS(WPCS) + PHPUnit + CI GitHub Actions. _Cause racine des régressions._
2. **Purge cache ciblée** (retirer le `wp_cache_flush()` global à chaque écriture). _Quick win P0 effort S._
3. **Pagination généralisée + suppression du N+1 émargement.** _Scalabilité 10k+ apprenants._
4. **Scellement SHA-256 + horodatage qualifié + audit inviolable** des signatures. _Valeur probante juridique._
5. **Modèle de permissions métier** (capacités WordPress dédiées, rôles gradués) au lieu de `manage_options` partout.
6. **Accessibilité réglementaire RGAA** (zoom émargement, contrastes, focus-trap). _Obligation légale OF._
7. **Facturation électronique 2026** (Factur-X + Chorus Pro/PDP + FEC + numérotation inaltérable).
8. **Découpage des méga-traits + couche vue/templates + repository.** _Maintenabilité._
9. **Chiffrement des données sensibles + purge de rétention RGPD automatique.**
10. **Fondation SaaS** (multi-tenant, marque blanche, plans/feature-flags) si l'objectif est la commercialisation.

### Quick wins immédiats (effort S, ROI élevé)

- Retirer la purge globale de cache à chaque écriture (perf) — `acdc-...-formation.php:75-88`
- Ajouter les index manquants (`status`, `is_absent`, composites) — schémas `dbDelta`
- Lazy-load jsPDF/html2canvas (560 Ko) — `enqueue_front_assets`
- `.htaccess deny` sur `uploads/acdc-emargement/` (fuite RGPD signatures)
- Ne plus faire confiance à `X-Forwarded-For` — `trainer-portal-core-trait.php:47`
- Débloquer le zoom (`maximum-scale=1`) sur la page d'émargement — a11y RGAA
- Générer le `.pot` (`wp i18n make-pot`) — i18n exploitable
- Ajouter `composer.json` + `phpcs.xml` + PHPStan niveau 3 — filet immédiat
- Sortir les 85 `RELEASE-NOTES-*.md` de la racine → `CHANGELOG.md` unique

---

## 1. Architecture & qualité de code — ★★☆☆☆

Métriques : **68 traits** fusionnés dans **1 classe** (`ACDC_Formation_SAAS_Plugin`), **~2 200 méthodes**, **371 hooks** dans le constructeur, **2 253 accès `$wpdb` directs**, **8 322 `echo`**, **0 namespace**, plus gros fichier **1,15 Mo / 15 504 lignes**, plus grosse méthode **1 618 lignes** (`render_front_training_files_tab`), **61 CREATE TABLE** + **36 migrations runtime ad hoc**, **129 options**.
> Atout : les modules **Signature / Émargement / Audit** sont déjà en vraies classes à responsabilité unique → la cible propre existe dans le code.

| # | Amélioration | Prio | Effort |
|---|--------------|:---:|:---:|
| A1 | Éclater le God Object en modules-services autonomes (injection de dépendances) | P0 | XL |
| A2 | Autoloader PSR-4 + Composer (remplacer ~30 `require_once` manuels, chargement paresseux) | P0 | L |
| A3 | Adopter les namespaces PHP (0 aujourd'hui) | P0 | L |
| A4 | Découper fichiers/méthodes monstrueux (méthode de 1 618 l., fichiers > 3 400 l.) | P1 | XL |
| A5 | Séparer logique/présentation : moteur de templates (8 322 `echo`, dossier `templates/` quasi vide) | P1 | XL |
| A6 | Couche Repository/DTO au lieu de `$wpdb` partout (y compris dans les traits « render ») | P1 | XL |
| A7 | Système de migrations de schéma centralisé (vs 36 `ALTER TABLE` runtime épars) | P1 | L |
| A8 | Enregistrement des hooks conditionné au contexte (admin/front/REST/cron) | P2 | M |
| A9 | Conteneur d'injection (remplacer les singletons `get_instance()`) | P2 | M |
| A10 | Centraliser/documenter les 129 options ; gérer les dépendances front (jspdf, html2canvas…) | P2 | M |
| A11 | Uniformiser conventions (`ACDC_*` vs `Acdc_*`), sortir CSS/JS inline, en-têtes `Requires PHP` | P3 | S-M |

---

## 2. Performance & scalabilité — ★★☆☆☆

Constat : **878 `$wpdb`**, **261 `SELECT *`**, chargement **eager** de tous les modules à chaque requête, **aucun** usage d'object cache applicatif, quiz live en **polling**.

| # | Amélioration | Prio | Effort |
|---|--------------|:---:|:---:|
| P1 | **Purge de TOUS les caches à chaque écriture** (`wp_cache_flush` + Litespeed/Rocket/W3TC) → invalidation ciblée | **P0** | S |
| P2 | **N+1 émargement** : ~2 requêtes/ligne de séance (≈400 req/page) → préchargement `WHERE session_id IN(...)` | **P0** | M |
| P3 | **Listes non paginées** : `SELECT *` de tables entières (prospects, entreprises…) → `LIMIT/OFFSET` + `COUNT` | **P0** | L |
| P4 | **Crons synchrones sans batch** (relances questionnaires, absences) → lots + file `async-dispatcher` (déjà présent) | **P0** | L |
| P5 | Index manquants (`learner.status`, `emarg_learners.is_absent`, composites `(session_id,status)`) | P1 | S |
| P6 | `SELECT *` sur tables `LONGTEXT` (listes) → colonnes ciblées | P1 | M |
| P7 | Object cache inexploité (0 `wp_cache_*`) + désactivé en admin → cacher les référentiels peu volatils | P1 | M |
| P8 | Bundle front monolithique (login apprenant tire `admin.js` 68 Ko + éditeur riche) → chargement par contexte | P1 | M |
| P9 | jsPDF (364 Ko) + html2canvas (199 Ko) chargés globalement → lazy-load au clic « Exporter » | P1 | S |
| P10 | mPDF régénéré à chaque téléchargement → cache document (si source inchangée) | P1 | M |
| P11 | Exports CSV/Excel full-memory → streaming `php://output` paginé | P1 | M |
| P12 | Chargement inconditionnel des modules (1,15 Mo de render sur une requête front) → lazy par contexte | P1 | L |
| P13 | `IN(...)` non borné, `nocache_headers()` trop large, maintenance sur `init` chaque requête, agrégats recalculés | P2-P3 | S-M |

---

## 3. Sécurité, RGPD & valeur probante — ★★★☆☆

Bonne hygiène anti-bug (nonces, `prepare`, sanitisation, chiffrement des clés watch). Les faiblesses restantes sont **structurelles**, à fort enjeu juridique.

| # | Amélioration | Prio | Effort |
|---|--------------|:---:|:---:|
| S1 | **Scellement SHA-256 du document signé** (aucun hash aujourd'hui) → intégrité prouvable | **P0** | M |
| S2 | Capacités WordPress métier (`acdc_manage_learners`, `_view_signatures`…) vs `manage_options` (201 occ.) | P1 | XL |
| S3 | Rôles distincts formateur/commercial/qualité/admin (moindre privilège, RGPD art. 32) | P1 | L |
| S4 | Lier la session portail à IP/User-Agent (anti-vol de cookie ; capturé mais jamais comparé) | P1 | M |
| S5 | Ne pas faire confiance à `X-Forwarded-For` (falsifiable → contourne throttling/logs probants) | P1 | S |
| S6 | 2FA sur portails admin/formateur/apprenant (brique OTP réutilisable depuis le module signature) | P1 | L |
| S7 | Chiffrement au repos des données sensibles (`accessibility_needs` = donnée de santé art. 9, naissance, adresse) | P1 | L |
| S8 | Purge automatique de rétention RGPD (aucun cron ; conservation illimitée = violation art. 5-1-e) | P1 | L |
| S9 | Horodatage qualifié RFC 3161/eIDAS (aujourd'hui `current_time()` = horloge modifiable) | P1 | L |
| S10 | Piste d'audit inviolable (chaînage de hash / WORM ; table mutable aujourd'hui) | P1 | M |
| S11 | Émargement public : ajouter nonce + rate-limit (aujourd'hui token URL seul) | P1 | M |
| S12 | `.htaccess deny` sur `uploads/acdc-emargement/` (signatures PNG énumérables par URL) | P1 | S |
| S13 | En-têtes de sécurité HTTP (CSP, X-Frame-Options, HSTS…) — 0 aujourd'hui ; pages de signature en iframe | P1 | M |
| S14 | Outils RGPD natifs WP (exporters/erasers), portabilité self-service apprenant, registre consentement | P2 | M-L |
| S15 | Crypto secrets : AES-CBC sans HMAC + fallback en clair → AEAD (sodium/GCM), étendre le périmètre chiffré | P2 | M |
| S16 | Politique MDP (≥12 + HIBP), anti-DoS login (throttle IP+compte, CAPTCHA), rate-limit global, alerting | P2 | M |
| S17 | Archivage à valeur probante (NF Z42-013), clarifier le niveau eIDAS (« simple + preuve », pas « avancée ») | P2 | L-XL |
| S18 | `uninstall.php` : compléter (tables portail/signature/émargement) + supprimer les fichiers uploadés | P3 | S |

---

## 4. UX / UI / Accessibilité — ★★★☆☆

Atout rare : couche de **design tokens** + **inspecteur de design system en back-office** (`.acdc-inspectable`). Mais présentation **accrétée** (double couche de tokens, ~489 `!important` cumulés, pas de mode sombre), et surtout **émargement/signature = pages autonomes hors design system** (3 polices, dorés/bleus différents).

| # | Amélioration | Prio | Effort |
|---|--------------|:---:|:---:|
| U1 | **Débloquer le zoom** (`maximum-scale=1`) sur l'émargement — échec WCAG 1.4.4, obligation RGAA | **P0** | S |
| U2 | **Contrastes** : doré `#C5A253` en texte ≈ 2,3:1 (< 4,5:1) → `--acdc-primary-ink` foncé | **P0** | M |
| U3 | **Focus-trap** dans les modales (`Tab` s'échappe ; échec WCAG 2.4.3) | **P0** | M |
| U4 | **Ré-habiller émargement + signature au design system** (cohérence de marque sur les moments légaux) | **P0** | L |
| U5 | Échelle de tokens complète (espacements/typo/ombres en dur aujourd'hui) | P1 | M |
| U6 | Mode sombre (`prefers-color-scheme` / `data-theme`) — fondations tokens déjà présentes | P1 | L |
| U7 | Skip link, `.sr-only` front, `aria-label` sur boutons-icônes, `prefers-reduced-motion` | P1 | S-M |
| U8 | Navigation portail repliable sur mobile (8 items empilés au-dessus du contenu) | P1 | M |
| U9 | Live quiz : polling 1500 ms → SSE/`EventSource` ou back-off adaptatif | P1 | L |
| U10 | États de chargement / squelettes / `aria-busy` (absents) | P1 | M |
| U11 | Unifier les 2 couches de tokens, dédupliquer `components.css`/`acdc-components.css`, corriger `--radius-pill` | P2 | L |
| U12 | Signature tactile : Pointer Events + lissage + « annuler dernier trait » | P2 | M |
| U13 | Build front + suppression des `onclick` inline (freinent la CSP) | P2 | M-L |
| U14 | Empty states composantisés, filtres/tri/recherche de tableaux homogènes, densité togglable | P2 | S-M |
| U15 | Micro-interactions cohérentes, simplifier le gradient bouton 7-stops, `alert/confirm` → in-context, focus global | P3 | S-M |

---

## 5. Fonctionnalités & produit — ★★★★☆

L'existant couvre déjà ~13 indicateurs Qualiopi, BPF Cerfa, quiz live, questionnaires à chaud/froid/NPS, signature OTP, veille IA, API REST minimale. Les écarts sont réglementaires (2026), intégrations et modèle SaaS.

**Vague 1 — Rattrapage réglementaire 2026 (non négociable)**
- Facture électronique **Factur-X** (PDF/A-3 + XML) [MH·L] · Connecteur **PDP / Chorus Pro** [MH·L] · Export **FEC** comptable [MH·M] · Numérotation inaltérable `UNIQUE(number)` [MH·S] · Renfort preuve d'identité apprenant [MH·S] · **Stripe** (paiement en ligne) [MH·M]

**Vague 2 — Fondation SaaS premium**
- **Multi-tenant** (isolation par organisme) [MH·XL] · Plans & **feature-flags** [MH·M] · **Facturation SaaS récurrente** (Stripe Billing) [MH·L] · **Marque blanche** (branding par client) [DIFF·M] · Onboarding self-service [DIFF·M] · Back-office super-admin multi-OF [MH·M]

**Vague 3 — Différenciation LMS & IA**
- Player **SCORM 1.2 / xAPI + LRS** [MH·XL] · Parcours e-learning/blended [DIFF·L] · Assiduité distancielle auto [DIFF·M] · **Visio native** Zoom/Teams/Meet [DIFF·L] · **Générateur IA** de programmes/quiz Qualiopi-ready [DIFF·M] · Assistant Qualiopi **32/32** audit-ready [DIFF·M] · Certificats vérifiables (Open Badges/QR) [DIFF·M] · Moteur de relances multi-canal [MH·M]

**Vague 4 — Pilotage & écosystème**
- **API REST publique** complète + OAuth + OpenAPI [DIFF·L] · Webhooks/bus d'événements + app **Zapier/Make** [DIFF·M] · Sync calendrier **2 sens** Google/Outlook [DIFF·M] · Connecteur compta **Pennylane/Sage** [MH·L] · Prévisionnel CA & carnet de commandes [MH·M] · Baromètre satisfaction consolidé [DIFF·S] · **PWA mobile** apprenant/formateur + push [DIFF·L] · e-mailing transactionnel (Brevo/Mailjet) [MH·S]

> **Signature produit à défendre** : capitaliser sur les 2 atouts rares (veille IA multi-modèles + quiz live) que les concurrents n'ont pas, pendant que les vagues 1-2 comblent les manques structurels.

---

## 6. Qualité, outillage, tests, i18n & distribution — ★☆☆☆☆

**Aucun** outillage : pas de `composer.json`, `package.json`, `phpunit`, `phpcs`, `phpstan`, `.github/` (CI), `README`, `LICENSE`, `.gitignore`, `.pot`. i18n **branchée mais vide** (~2 300 chaînes FR en dur). **85** `RELEASE-NOTES-*.md` à la racine ; **12** versions non-semver (`3.21.04.1-hotfix5`…).

| # | Amélioration | Prio | Effort |
|---|--------------|:---:|:---:|
| Q1 | Suite de tests PHPUnit (cibler d'abord la logique métier : HT/TVA, scoring, tokens, GROUP BY) | P0 | XL |
| Q2 | Composer + autoload + outils dev (`require-dev`) — prérequis testabilité | P0 | M |
| Q3 | Générer/livrer le `.pot` (`wp i18n make-pot`) ; puis enrober les chaînes en dur | P0/P2 | S/L |
| Q4 | PHP_CodeSniffer + WordPress Coding Standards (détecte escaping/nonces manquants) | P1 | S-M |
| Q5 | PHPStan + `phpstan-wordpress` (capture $tbl_q/$logo_data_uri/implode… avant runtime) | P1 | S-M |
| Q6 | CI/CD GitHub Actions (phpcs + phpstan + phpunit + `wp plugin check` + matrice PHP) | P1 | M |
| Q7 | Versioning sémantique strict + `CHANGELOG.md` consolidé (archiver les 85 notes) | P1 | M |
| Q8 | Updater self-hosted (`plugin-update-checker` → GitHub Releases) — MàJ sécurité clients | P2 | M |
| Q9 | Build assets (minification/bundling ; sortir 33 `<style>` + 23 `<script>` inline) | P2 | M-L |
| Q10 | README dev + `readme.txt` WP + `LICENSE` (GPLv2+, bloquant juridique) + doc des hooks | P2 | M |
| Q11 | `wp plugin check` en CI, formatage auto + pre-commit + `.editorconfig` | P3 | S |

---

## 7. Feuille de route consolidée (transverse)

### Phase 0 — Quick wins & filet de sécurité (≈ 2-4 semaines)
Purge cache ciblée (P1) · index manquants (P5) · lazy-load jsPDF (P9) · `.htaccess` émargement (S12) · X-Forwarded-For (S5) · zoom émargement (U1) · `.pot` (Q3) · **Composer + PHPCS + PHPStan** (Q2/Q4/Q5) · sortir les 85 notes (Q7).
_But : stopper l'hémorragie de régressions et les risques immédiats (perf, RGPD, a11y), à coût minimal._

### Phase 1 — Socle technique & conformité (≈ 1-2 trimestres)
CI GitHub Actions + tests PHPUnit sur la logique critique (Q6/Q1) · pagination + N+1 émargement (P2/P3) · crons asynchrones (P4) · **scellement SHA-256 + audit inviolable** (S1/S10) · capacités & rôles métier (S2/S3) · en-têtes HTTP + nonce émargement (S13/S11) · **accessibilité RGAA** (U1-U4/U7) · **Factur-X + numérotation inaltérable + FEC** (Vague 1 produit).

### Phase 2 — Refonte structurante & montée en gamme (≈ 2-3 trimestres)
Découpage God Object → modules-services + repository + templates (A1-A6) · namespaces/PSR-4 (A3/A2) · chiffrement données sensibles + purge rétention RGPD (S7/S8) · horodatage qualifié eIDAS (S9) · mode sombre + design system unifié + live quiz temps réel (U6/U11/U9) · **Stripe + connecteurs compta/visio + API publique** (Vagues 2-4 produit).

### Phase 3 — Plateforme SaaS premium (selon ambition commerciale)
Multi-tenant + marque blanche + plans/feature-flags + facturation SaaS (Vague 2) · LMS SCORM/xAPI + parcours e-learning + PWA (Vague 3) · pilotage décisionnel + écosystème d'intégrations (Vague 4).

---

## 8. Annexe — métriques clés relevées

| Indicateur | Valeur |
|-----------|--------|
| Fichiers PHP / lignes | 120 / ~125 600 |
| Traits dans la classe principale | 68 (~2 200 méthodes) |
| Hooks enregistrés au boot | 371 |
| Accès `$wpdb` directs | ~2 253 |
| Instructions `echo` | 8 322 |
| Namespaces PHP | 0 |
| Plus gros fichier / méthode | 1,15 Mo (15 504 l.) / 1 618 l. |
| Tables SQL / migrations runtime | ~61 / ~36 |
| Options `acdc_of_*` | 129 |
| `SELECT *` / requêtes sans `LIMIT` | 261 / ~48 |
| Object cache applicatif (`wp_cache_*`) | 0 |
| `!important` cumulés (CSS) | ~489 |
| Chaînes FR en dur (hors i18n) | ~2 300 |
| Fichiers `RELEASE-NOTES-*.md` | 85 |
| Tests / CI / Composer / .pot | 0 / 0 / 0 / 0 |

_Fin du rapport. Chaque item est traçable à un fichier:ligne dans l'analyse détaillée par dimension._
