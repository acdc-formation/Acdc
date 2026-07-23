# Changelog

Toutes les modifications notables de ce projet sont documentées ici.
Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).
L'historique détaillé antérieur est archivé dans [`release-notes/`](release-notes/).

## [3.25.126] — 2026-07-23

### Ajouté — Attestations vérifiables (référence + signature)
Nouvelle brique `ACDC\Support\CertificateCode` : génère une référence d'attestation unique dotée
d'une **clé de contrôle** (détection des fautes de frappe, façon IBAN) et d'une **signature** courte
(HMAC) permettant une **vérification d'authenticité par QR code** sur une page publique, sans
exposer de secret. Fonctionnalité différenciante (certificats infalsifiables, vérifiables par un
tiers). Couvert par PHPUnit (`tests/Support/CertificateCodeTest.php`). Le raccordement au PDF
d'attestation + page de vérification suivra.

## [3.25.125] — 2026-07-23

### Ajouté — Politique de rétention RGPD (base de purge automatique)
Nouvelle brique `ACDC\Support\Retention` : durées de conservation par catégorie (prospect 3 ans,
apprenant/évaluation 5 ans, comptable/audit 10 ans — surchargables), calcul de la **date de coupure**
et détection des enregistrements **à purger** (`isPurgeable`, `partition`). Fondation du ménage
automatique RGPD (art. 5-1-e). Gère les dates invalides et l'année bissextile. Couvert par PHPUnit
(`tests/Support/RetentionTest.php`). Le cron de purge s'appuiera sur cette brique dans un incrément
dédié.

## [3.25.124] — 2026-07-23

### Ajouté — Calcul unifié du chiffre d'affaires (statistiques)
Nouvelle brique `ACDC\Support\Revenue` : agrégation fiable et testée du CA à partir de lignes de
facturation homogènes, avec une règle unique et vérifiable (**factures + / avoirs −**). Fournit le
CA net HT/TVA/TTC (`summarize`), la ventilation par **taux de TVA** (`byVatRate`) et par **période**
mois/trimestre/année (`byPeriod`). Objectif : que tous les écrans de statistiques reposent sur le
même calcul, sans divergence. Couvert par PHPUnit (`tests/Support/RevenueTest.php`).

## [3.25.123] — 2026-07-23

### Modifié — NDA (numéro de déclaration d'activité) sur les factures
Le NDA de l'organisme (**93 83 08347 83**) est désormais renseigné par **défaut** dans le profil
(`activity_declaration_number`). Il apparaît donc automatiquement sur les factures internes
(mentions « NDA : … » déjà prévues dans les modèles) sans saisie préalable — le défaut était vide.
Mention légale obligatoire pour un organisme de formation.

## [3.25.122] — 2026-07-23

### Ajouté — Validation SIRET/SIREN + identité de l'organisme renseignée
- Nouvelle brique `ACDC\Support\Siret` : validation (clé de Luhn, exception La Poste), extraction
  SIREN/NIC et formatage homogène « 405 109 901 00042 » des numéros légaux. Couvert par PHPUnit
  (`tests/Support/SiretTest.php`).
- Le SIRET de l'organisme (**405 109 901 00042**, ACDC Formation) est désormais renseigné par
  **défaut** dans l'identité (`get_branding_defaults`) : il apparaît sur les factures internes et
  le XML Factur-X sans saisie manuelle préalable (le défaut était vide auparavant).

## [3.25.121] — 2026-07-23

### Ajouté — Horodatage scellé, prêt pour la qualification eIDAS
Nouvelle brique `ACDC\Support\Timestamp` : produit un **jeton d'horodatage** qui scelle
l'empreinte SHA-256 d'un document/écriture et **se scelle lui-même** (toute retouche de l'heure
casse le sceau → antidatage détectable). Fonctionne immédiatement avec l'horloge serveur
(horodatage *simple*, `qualified => false`).
- **Point de branchement officiel** : `Timestamp::create()` applique le filtre WordPress
  `acdc_timestamp_token`. Le jour où un tiers d'horodatage **qualifié** (RFC 3161 / eIDAS) est
  contractualisé, il suffit de le brancher sur ce filtre — aucun appelant n'a à changer. Repli
  automatique sur l'horodatage local tant qu'aucun fournisseur qualifié n'est branché.
- `verify()`, `isQualified()`, `levelLabel()` ; couvert par PHPUnit (`tests/Support/TimestampTest.php`).

## [3.25.120] — 2026-07-23

### Ajouté — Journal d'audit infalsifiable (valeur probante)
Nouvelle brique de logique pure `ACDC\Support\AuditTrail` : registre en **ajout-seul** dont chaque
écriture est liée à la précédente par une empreinte SHA-256 chaînée (WORM logique). Toute
modification, insertion ou suppression a posteriori d'une ligne **casse la chaîne** à partir de ce
point et devient détectable (`verifyChain()`, `firstTamperedIndex()`).
- Canonicalisation déterministe (clés triées récursivement, ordre des listes préservé) → empreinte
  reproductible quel que soit l'ordre d'insertion des données.
- Sans dépendance à WordPress, couvert par PHPUnit (`tests/Support/AuditTrailTest.php`).
- Fondation de la piste d'audit inviolable (chantier « Valeur juridique » de la feuille de route
  premium) — le raccordement au module `includes/audit/` suivra dans un incrément dédié.

## [3.25.119] — 2026-07-22

### Ajouté — Émargement PAR SÉANCE (multi-créneaux)
Une session à plusieurs créneaux (`schedule_json`) peut désormais avoir **une feuille d'émargement
signable par séance** (au lieu d'une signature globale unique). Implémentation **strictement additive
et rétro-compatible** :
- Schéma : colonnes `seance_index` (DEFAULT 0) + `seance_label`/`seance_start_at`/`seance_end_at`
  ajoutées de façon portable (MySQL/MariaDB) ; les feuilles existantes deviennent `seance_index = 0`.
- Les sessions **mono-séance** (la grande majorité) et les feuilles existantes conservent un
  comportement **identique** (UI, e-mail, PDF, audit) — le multi-séance n'est qu'une surcouche
  activée quand `schedule_json` contient plusieurs créneaux.
- PDF d'émargement : multi-pages « Séance k/N » quand plusieurs feuilles sont signées ; sortie
  strictement identique pour une seule feuille.
- UI portail : éclatement en sous-lignes/boutons par séance uniquement pour les sessions
  multi-créneaux.

> ⚠️ **À valider sur une session multi-séances de test avant usage en production.** Les sessions
> mono-séance ne sont pas affectées. Limitation connue : le tableau d'audit reflète la séance
> d'index 0 (le PDF consolidé par session couvre, lui, toutes les séances).

## [3.25.118] — 2026-07-22

### Ajouté (chantiers différés — aval facturation & BPF)
- **Relance d'impayés** : bouton/handler « Relancer » (e-mail + compteur `relance_count`/`last_relance_at`)
  sur les factures émises/envoyées/en retard, + **cron quotidien** qui passe automatiquement en
  `en_retard` les factures dont l'échéance (`due_date`) est dépassée (comparaison en heure WP).
- **Avoir persistant** : bouton/handler « Émettre un avoir » — numéro `AV-{année}-` **séquentiel sans
  trou** (verrou + compteur monotone), statut `avoir`, motif, garde anti double-avoir ; le
  téléchargement utilise le numéro persisté.
- **BPF — tableaux « Liste des actions / Liste des formateurs »** : désormais **peuplés depuis les
  vraies données** (conventions/contrats pour les actions ; formateurs des sessions sur la période),
  avec un **état vide explicite** au lieu d'un placeholder muet. Colonnes conformes aux en-têtes ; les
  cadres BPF existants (E/F1/C/D) sont inchangés.

_Différé (note de conception livrée, non implémenté pour ne pas risquer la preuve de présence) :
émargement PAR SÉANCE — nécessite une intervention coordonnée (core + PDF + e-mail + UI portail +
audit/ZIP) avec `seance_index` rétro-compatible ; à réaliser en un lot validé._

## [3.25.117] — 2026-07-22

### Corrigé / ajouté (suite audit workflows)
- **Statut d'inscription scopé à la session** : la clôture d'une session ne pousse plus « convocations
  envoyées » / « formation réalisée » aux inscrits des AUTRES sessions de la même formation
  (avancement limité aux apprenants réellement rattachés à la session via `learner_table.session_id`).
- **Facturation — encaissement** : nouveau bouton/handler « Marquer payée » (statut `payee` + `paid_at`),
  sécurisé (capability + nonce + garde mode démo), idempotent.
- **Facturation — envoi** : nouveau bouton/handler « Envoyer la facture » par e-mail (calqué sur l'envoi
  de devis : génération du HTML, e-mail transactionnel, statut `envoyee` + `sent_at`). Les deux modales
  de facture, jusque-là inertes, sont désormais fonctionnelles depuis la vue facture.

### Différé (nécessite conception/test en conditions réelles — signalé, non livré à l'aveugle)
- Relance automatique d'impayés (cron `en_retard`) et émission d'avoir persistant complet
  (workflow + numérotation `AV-` séquentielle).
- Émargement par séance (1 feuille par demi-journée) — changement de schéma à cadrer.
- Propagation d'un taux de TVA par proposition (aucun champ TVA n'existe aujourd'hui sur les
  propositions — l'exonération art. 293 B est une constante de gabarit).

## [3.25.116] — 2026-07-22

### Corrigé (6ᵉ vague — audit des workflows métier & tâches planifiées, ~18 correctifs)
Conformité / documents :
- **Attestations** : un apprenant marqué **absent** à l'émargement ne reçoit plus son certificat de
  réalisation ni son attestation de fin de formation (envois automatiques filtrés sur l'émargement).
- **Convocation** : l'envoi automatique persiste désormais l'URL du PDF sur le dossier → la
  convocation devient visible au portail apprenant (auparavant « bientôt disponible » en permanence).
- **Suppression de dossier** : le contrat/convention lié n'est plus laissé orphelin (le DELETE ciblait
  une colonne `registration_id` inexistante ; corrigé vers `id = autofill_contract_id`, 2 emplacements).
- **Émargement** : `mark_absent` réévalue la complétion de la feuille (statut `completed` atteint même
  quand la dernière action est un « marquer absent »).

Indicateurs / pilotage Qualiopi :
- **Actions Qualiopi** (amélioration continue) : les compteurs « à traiter / traitées » lisaient des
  clés inexistantes (`to_process`/`closed`) → toujours 0. Corrigé vers `a_traiter`/`traitee`.
- **Taux de réussite éval.** (dashboard) : KPI jamais alimenté (clé `success_rate` inexistante) →
  calcul réel du pass-rate (≥ 70 %).
- **Taux de bonnes réponses / progression** : double normalisation supprimée (`final_score` déjà en %
  n'est plus re-divisé par le nombre de questions → plus de plafonnement à 100 %).

CRM / facturation :
- **Conversion devis→facture** : garde anti double-conversion (un 2ᵉ clic redirige vers la facture
  existante au lieu de créer un doublon numéroté).
- **Entonnoir CRM** : le prospect avance à « Devis envoyé » à l'envoi du devis ; la fiche prospect
  affiche ses **vrais** devis en mode réel (au lieu des données de démonstration).
- **Lead REST** : `profile_type` normalisé en libellés canoniques (Particulier/Salarié/Indépendant/
  Entreprise) attendus par le CRM.

Tâches planifiées (WP-Cron) :
- 4 crons (NAD, indicateurs, sync formations, alertes absence) désormais aussi armés sur `init`
  (auparavant sur le hook `wp`, front-office uniquement → non auto-réparants).
- Purge des données et désinstallation déprogramment désormais **les 24** tâches cron (au lieu de 5/6)
  → plus de tâches orphelines s'exécutant contre des tables vidées.
- Récurrence `weekly` déclarée (auto-suffisance) ; crons convocation/positionnement comparés en
  dates calendaires homogènes (plus de décalage de fuseau d'un jour aux bornes J-X).

## [3.25.115] — 2026-07-22

### Corrigé (régression détectée par la revue finale)
- **Numérotation devis/factures** : la 3.25.114 réservait le numéro DEUX fois par document (aperçu +
  insertion sous verrou), ce qui sautait un numéro à chaque création. Séparation d'un aperçu en
  **lecture seule** (`preview_next_document_number`) et d'une **réservation persistée unique** sous
  verrou (`reserve_next_document_number`) → numérotation réellement continue, sans trou ni doublon.

## [3.25.114] — 2026-07-22

### Finalisation
- **Numérotation devis/factures sans trou** — un numéro n'est plus jamais réutilisé après suppression
  d'un document : compteur monotone persistant par année (`acdc_of_document_number_counters`) combiné
  au maximum présent en base. Répond à l'exigence de numérotation chronologique continue.
- **Désinstallation** — nettoyage des options ajoutées par les vagues de correction
  (`acdc_emarg_db_version`, `acdc_of_document_number_counters`, `acdc_of_watch_deleted_default_ids`,
  `acdc_of_watch_sources`, `acdc_of_questionnaire_settings`).

_Note : les contrôles d'animation « live » d'un quiz (terminer la session / exclure un participant /
afficher les résultats) restent disponibles côté serveur mais sans bouton dédié — à câbler lors d'une
session de test en conditions réelles._

## [3.25.113] — 2026-07-22

### Corrigé (5ᵉ vague — 2ᵉ passe d'audit approfondie, 19 correctifs)
Fatals / bloquants :
- **Émargement (MySQL)** : `ALTER TABLE … ADD COLUMN IF NOT EXISTS` (extension MariaDB) rejeté par MySQL → colonnes `trainer_ip`/`trainer_ua`/`learner_ua` jamais créées → l'enregistrement de signature échouait. Remplacé par un test `SHOW COLUMNS` + `ALTER` portable, colonnes ajoutées aux `CREATE TABLE`, et garde de version sur `init` (plus de migration à chaque requête).
- **Actions Qualiopi** : colonne `priority_level` écrite mais absente du schéma → action jamais créée + erreur SQL sur filtre. Migration ajoutée.
- **Quiz — cycle de vie** : 3 boutons morts (Activer / Repasser en brouillon / Verrouiller) — migration WAF incomplète, handlers `wp_ajax` manquants. Ajoutés.
- **Quiz — archivage** : écrivait dans la table legacy `acdc_of_quizzes` (sans colonne `status`) → archivage sans effet. Corrigé vers la table du module + `archived_at`.
- **Quiz — anti-triche live (hors-UTC)** : le temps serveur mélangeait heure murale WP et UTC → anti-triche contourné (offset +) ou tous les scores faux (offset −). Base de temps homogène.

Cohérence / données :
- **Convention (PDF juridique)** : délai de rétractation, clause de litiges et articles additionnels saisis par contrat étaient ignorés (valeurs globales affichées à la place). Priorité rétablie aux valeurs du contrat.
- **Avoir/dates/tokens** : uniformisation des comparaisons de temps en heure WP (fenêtre de rappel J-2, expiration tokens NAD et participant, badge « délai dépassé », chrono live).
- **Signature** : verrou atomique anti double-signature (`WHERE status <> 'signe'`) avant génération du PDF.
- **Quiz — moyenne** : les scores légitimes à 0 ne sont plus exclus (moyenne gonflée).
- **Programme PDF** : les minutes de la durée (`HH:MM`) ne sont plus perdues (« 7h30 » au lieu de « 7h »).

Sécurité / robustesse :
- **Questionnaire** : nonce vérifié sur `handle_join_questionnaire_session` (writer public).
- **Veille** : normalisation d'URL (retrait `utm_*`/`fbclid`/`gclid`, slash final, ancre) avant déduplication.
- **Marketing** : import CSV dédoublonné par e-mail contre les fiches métier (plus de doublons `import:`).
- **i18n** : 7 chaînes d'interface à l'échappement cassé (`\xc3\xa9` affiché littéralement) corrigées + 2 comparaisons de statut mortes nettoyées.

## [3.25.112] — 2026-07-22

### Corrigé (4ᵉ vague — taux d'occupation + émargement)
- **Taux d'occupation des séances** : calculé réellement en **présents / inscrits** (agrégé
  depuis l'émargement de chaque séance) au lieu d'afficher 100 % présents en dur.
- **Émargement — actions formateur** : « Marquer absent » et « Envoyer lien » depuis la page liste
  publique (accès par lien/QR) fonctionnent enfin pour le formateur non connecté — routées via le
  flux public et autorisées par le `list_token` (avec vérification d'appartenance de l'apprenant à
  la séance) au lieu d'exiger `manage_options` (qui provoquait un `wp_die`).
- **Émargement — calcul du retard** : `late_minutes` calculé en base de temps homogène (UTC réel via
  `get_gmt_from_date`) — supprime le retard fantôme/masqué dû au mélange de fuseaux serveur/WordPress.

## [3.25.111] — 2026-07-22

### Corrigé (3ᵉ vague d'audit — 14 bugs, dont 5 fatals)
- **Fatal** `wrap_pdf_text()` → `pdf_wrap_text()` : attestation de fin de formation (téléchargement + envoi auto) réparée.
- **Fatal** export « détails QCM » attestation de fin de formation : 3 méthodes inexistantes remplacées par un export CSV/Excel inline (aligné sur l'export positionnement).
- **Fatal** signature d'émargement apprenant : condition SQL `expires_at` (colonne absente de la table apprenants) supprimée — les apprenants peuvent de nouveau signer.
- **Fatal** tableau de bord apprenant : colonne SQL `q.duration_seconds` inexistante supprimée (crash `count(null)` sous PHP 8).
- **Fatal** téléchargement résultat d'évaluation : `render_simple_pdf_output()` → `render_simple_pdf()`.
- **Fatal** éditeur de quiz avec `quiz_id` invalide : `render_qz_inline_notice()` remplacé par une notice inline.
- **Avoir** : base HT alignée sur le sous-total avec frais (transport/repas/lignes annexes) — la TVA de l'avoir n'est plus surévaluée.
- **Évaluations** : le score final (`final_score`) est désormais calculé et persisté en **pourcentage 0-100** à la clôture de session (participants « terminé » + `responded_at`) — le tableau de performance des évaluations n'affiche plus tout en échec.
- **Indicateurs REST** : total d'heures calculé via `TIMESTAMPDIFF(start_at, end_at)` (la colonne `duration_minutes` n'existe pas sur les sessions) — ne renvoie plus 0.
- **Marketing** : l'édition d'identité (nom/type/e-mail/tél/société) d'un contact lié à une table métier n'est plus annulée au rechargement (fusion non destructive).
- **Veille** : une source par défaut supprimée n'est plus ré-injectée au rechargement (mémorisation des suppressions).
- **Quiz** : versionnage AJAX réparé (`create_qz_new_version` → `create_qz_quiz_new_version`).
- **Quiz async** : score brut (`total_score`) désormais persisté — le PDF de résultat et l'export CSV n'affichent plus « 0 pts ».

## [3.25.110] — 2026-07-22

### Corrigé (2ᵉ vague d'audit — ~13 bugs)
- **Fatals** : `normalize_marketing_input_list` (sauvegarde entités marketing) et
  `learner_portal_get_resource_source_url` (ouverture ressource) — méthodes inexistantes.
- Colonne SQL `passing_score` → `pass_threshold` (seuil de réussite quiz, 4 sites).
- BPF Cadre C (`c10`/`c_total`) et F1 (Apprentis/Particuliers) corrigés à l'écran.
- Moyenne session questionnaire (non-répondants exclus), import CSV marketing (fusion non
  destructive), refus signature via GET (nonce), apprenant absent bloqué à la signature,
  OTP revérifié côté serveur, double échappement PDF NAD, fuseau tokens auditeur, hash veille,
  classification BPF Salarié→C1, déplanification de 19 crons à la désactivation.

### Connu (décision de modèle requise)
- Échelle de `final_score` (dashboard réussite/progression) ; carte « Taux d'occupation » placeholder.

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
