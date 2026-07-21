# Rapport d'analyse — Plugin ACDC Formation SAAS v3.25.100

_Analyse : 120 fichiers PHP (~125 500 lignes), PHP 8.4. Lint syntaxique : 0 erreur.
Chaque bug listé a été confirmé par lecture du code (et testé quand pertinent)._

## Constat général
Code mature et globalement bien durci : requêtes préparées, nonces/capacités
présents sur la grande majorité des handlers, OTP signature solide (random_int,
random_bytes, hash_equals, rate-limit), pas d'eval/exec/unserialize, échappement
de sortie rigoureux. Les bugs ci-dessous sont réels mais ponctuels.

---
## 🔴 HAUTE

### H1. Facture : Total HT exclut les frais (HT + TVA ≠ TTC)
- `includes/documents-billing/class-acdc-documents-billing-core-trait.php:515`
- Chemin facture : `tarif_ht_number => $tarif_ht` (base seule), mais TVA (l.516) et
  TTC (l.518) calculés sur `$sous_total_ht` (= base + transport + repas + extra, l.472).
  Le chemin devis (l.227) utilise correctement `$sous_total_ht`. Asymétrie = bug.
- Impact : dès qu'il y a des frais, la facture est incohérente (HT sous-évalué),
  problème comptable/juridique.
- Fix : `'tarif_ht_number' => format($sous_total_ht)` (+ `tarif_ht_value`, `tarif_ht`).

### H2. Score quiz « live » entièrement contrôlé par le client
- `includes/quizzes/class-acdc-quizzes-actions-trait.php:2702,2737` + engine `:366`
- `response_ms` (temps de réponse servant au score Kahoot) vient du POST joueur
  (endpoint nopriv). Le serveur ignore `current_question_started_at`. → `response_ms=0`
  donne 1000 pts à chaque bonne réponse. Classement faussé.
- Fix : calculer l'écoulé côté serveur, borner `min(client_ms, server_elapsed_ms)`.

### H3. GROUP BY incompatible ONLY_FULL_GROUP_BY (MySQL 8 par défaut)
- `includes/sessions/class-acdc-sessions-core-trait.php:118` (get_validated_sessions)
- `includes/sessions/class-acdc-sessions-actions-trait.php:1168` (cron absences)
- Colonnes non agrégées (`g.name`, `l.first_name`, `s.start_date`, `f.title`) avec
  `GROUP BY s.id` / `GROUP BY el.learner_id` → erreur 1055 sur MySQL 5.7.5+/8.0.
- Impact : onglet « Séances validées » vide + cron alerte absences (Qualiopi) KO.
- Fix : `ANY_VALUE()`/`MAX()` sur les colonnes non agrégées, ou compléter le GROUP BY.

### H4. OTP signature sans limite de tentatives (brute-force 2FA)
- `includes/signature/class-acdc-sig-public.php:458` (handle_otp_verify) +
  `class-acdc-sig-core.php:285` (verify_otp)
- `check_rate_limit()` existe et est appliqué sur `handle_submit`, mais PAS sur la
  vérification OTP. Aucun compteur d'échec ; le nonce reste réutilisable. OTP 6 chiffres
  brute-forçable en 15 min pour qui détient le lien de signature. 2e facteur à valeur
  contractuelle (convention/contrat niveau « renforcé »).
- Fix : compteur d'échecs par request_id/IP (≤5), invalider l'OTP après N échecs.

### H5. Fatal PHP 8 : implode() sur une chaîne (fiche scénario marketing)
- `includes/marketing/class-acdc-marketing-render-trait.php:3508-3510`
- `marketing_contact_related_labels()` retourne une CHAÎNE (core:683), passée comme
  2e argument de `implode()` → `TypeError` fatal. La page `tab=marketing_scenarios&action=view`
  plante en écran blanc.
- Fix : `esc_html( $this->marketing_contact_related_labels( … ) )` sans `implode()`.

---
## 🟠 MOYENNE

### M1. Trois requêtes SQL avec colonnes inexistantes (libellé destinataire)
- `includes/marketing/class-acdc-marketing-core-trait.php:969,985,991`
- l.969 (prospects) : `company` → la table a `company_name`.
- l.985 (funders) : `contact_first_name, contact_last_name` → inexistants.
- l.991 (companies) : `company_name` → la table a `name`.
- → « Unknown column » : libellé vide / erreur DB pour prospect/financeur/entreprise.
- Fix : aligner les colonnes sur les schémas réels.

### M2. Tokens d'émargement qui n'expirent jamais
- `includes/emargement/class-acdc-emarg-core.php:154-176` (getters)
- `expires_at` (72 h) est écrit à la création mais JAMAIS lu (aucune vérification dans
  les getters ni ailleurs). Un lien/QR d'émargement reste valable indéfiniment →
  falsification possible des feuilles de présence (enjeu Qualiopi). Les pages affichent
  pourtant « Lien expiré » (jamais vrai).
- Fix : `AND expires_at > NOW() AND status NOT IN (...)` + purge cron.

### M3. XSS DOM via esc_attr() dans un handler JS inline (émargement)
- `includes/emargement/class-acdc-emarg-public.php:405`
- `onsubmit="return confirm('Marquer <?php echo esc_attr($lr->learner_name) ?> ...')"` :
  `esc_attr` n'échappe pas pour un contexte JS. Un nom avec apostrophe/`)` casse la chaîne
  et exécute du JS. Page publique (bearer token).
- Fix : `esc_js()` (ou data-attribut lu en JS).

### M4. Nonce généré mais jamais vérifié (réponse questionnaire)
- `includes/questionnaires/class-acdc-questionnaires-actions-trait.php:1180`
- `handle_submit_questionnaire_answer` (nopriv) n'appelle aucun `check_admin_referer`,
  alors que le formulaire émet un nonce et que la méthode sœur (survey) le vérifie.
- Fix : `check_admin_referer('acdc_front_secure_action')`.

### M5. Usurpation d'identité apprenant sur les « join » publics (Qualiopi)
- quiz : `class-acdc-quizzes-actions-trait.php:2634` ; questionnaires `:1159`
- L'`apprenant_id` est fourni en POST ; seule l'appartenance à la séance est vérifiée,
  jamais l'identité. Réponses/score attribués à un vrai apprenant avec `qualiopi_traceable=1`.
  La liste des noms est même exposée (ajax get_session_learners).
- Fix : preuve faible d'identité (jeton individuel par e-mail, etc.) avant traçabilité.

### M6. $tbl_q indéfini dans get_qz_live_state
- `includes/quizzes/class-acdc-quizzes-engine-trait.php:545,548`
- `$tbl_q` utilisé mais jamais défini dans cette fonction (seule affectation l.602, autre
  méthode) → `SHOW COLUMNS FROM  LIKE …` cassé, réponse attendue des questions open_text
  jamais révélée en live + bruit d'erreurs DB.
- Fix : `$tbl_q = $this->get_qz_table('questions');` en tête de bloc.

### M7. pdf_color_map_json écrasé à chaque sauvegarde
- `includes/settings-catalog/class-acdc-settings-catalog-actions-trait.php:323`
- `strpos('pdf_color_map_json','color_') === 4` → la clé JSON tombe dans la branche hex,
  `sanitize_hex_color(JSON)=null` → valeur remise à `''` à chaque enregistrement du branding.
- Fix : test d'égalité stricte (exclure `$css_keys` de la branche hex).

### M8. Upload SVG sans sanitisation (XSS stocké)
- `includes/settings-catalog/class-acdc-settings-catalog-actions-trait.php:219`
- `image/svg+xml` autorisé (logo/cachet/signature) sans nettoyage → SVG avec `<script>`
  exécuté à l'ouverture directe de l'URL uploads. (Limité aux admins → MOYENNE.)
- Fix : retirer `svg` ou sanitiser (svg-sanitize).

### M9. Injection de formule CSV (tous les exports CSV)
- `includes/kernel/class-acdc-export-csv-trait.php:45` (acdc_csv_row)
- Aucune neutralisation des préfixes `= + - @` ; valeurs issues de la BD (noms,
  commentaires…). Cellule `=cmd|…` exécutée par Excel/LibreOffice à l'ouverture.
  (L'export XLSX n'est pas affecté.)
- Fix : préfixer `'` toute cellule commençant par `= + - @` (tab/CR).

### M10. Import de formations : clé 'title' non définie → mauvaise colonne
- `includes/kernel/class-acdc-kernel-actions-trait.php:902,923`
- Fichier sans colonne « Intitulé » autorisé (l.902), mais `(int)$mapping['title']` lu
  sans condition (l.923) → warning PHP 8 + lecture de la colonne 0 (mauvaise donnée,
  rattachement erroné).
- Fix : `$title_index = isset($mapping['title']) ? (int)$mapping['title'] : -1;`

### M11. uninstall.php : purge de tables erronée et incomplète
- `uninstall.php:106-107` supprime `acdc_of_watch_sources`/`acdc_of_watch_articles`
  (qui ne sont PAS des tables) ; la vraie table `acdc_of_watch_items` et ~20 autres
  (complaints, trainer_*, invoices, quotes, emarg_*, learner/trainer_portal_*…) ne sont
  jamais supprimées → données personnelles orphelines après purge.
- Fix : dériver la liste des tables du registre de création.

### M12. Propriétés dynamiques non déclarées → Deprecated PHP 8.2+
- `includes/class-acdc-plugin.php:182,184` (`trainer_contract_table`,
  `trainer_evaluation_table`) jamais déclarées → `Deprecated: Creation of dynamic property`
  à chaque requête (fatal en PHP 9).
- Fix : déclarer les deux propriétés.

### M13. dbDelta() exécuté à chaque requête (performance)
- `includes/class-acdc-audit.php:52` → `install()` sur `init` sans garde de version ;
  `dbDelta()` tourne sur toutes les pages (front/admin/ajax/cron).
- Fix : garder par `get_option('acdc_of_audit_db_version')`.

### M14. Modèle de prompt IA personnalisable non fonctionnel
- `includes/agent-audit/class-acdc-agent-audit-core-trait.php:253-313`
- `custom_prompt_template` sauvegardé et affiché mais jamais utilisé (prompt codé en dur,
  pas de substitution des `{{...}}`).
- Fix : partir du template et `str_replace` des jetons.

### M15. Auto-clôture erronée des séances sans date de fin
- `includes/sessions/class-acdc-sessions-actions-trait.php:531`
- `COALESCE(end_date,'1970-01-01')` → séance sans date de fin toujours `< NOW()` →
  marquée « Terminée » → envoi attestations/évaluations à tort (données legacy/import).
- Fix : `AND (end_at IS NOT NULL OR end_date IS NOT NULL)`.

### M16. Filtres sessions non fonctionnels (formateur direct ; signatures/présence)
- `includes/sessions/class-acdc-sessions-core-trait.php:112` (formateur : ne teste que
  `g.trainer_name`, ignore `s.trainer_id`) et `:127-146` (labels signatures/présence
  codés en dur → filtres no-op).
- Fix : élargir la condition formateur ; calculer les vrais labels.

### M17. objectifs_blocs PDF non normalisé → TypeError possible
- `includes/settings-catalog/class-acdc-settings-catalog-programme-pdf-trait.php:471,478`
- JSON `objectifs_detail` utilisé sans normalisation ; structure inattendue →
  `strip_tags(array)` / accès offset sur string = fatal (PDF écran blanc).
- Fix : `is_array()` + cast `(string)` comme pour `programme_blocs`.

### M18. Variables indéfinies dans le document facture/avoir (logo + signature)
- `includes/documents-billing/class-acdc-documents-billing-core-trait.php:1084,1116`
- `$logo_data_uri` et `$sig_data_uri_t2` jamais définis dans `get_invoice_document_html`
  (seulement dans le devis, l.771-773) → logo jamais embarqué en base64 (dépend de l'URL
  distante) et image de signature jamais affichée sur factures/avoirs.
- Fix : initialiser les deux via `quote_img_to_data_uri()` en tête de la fonction.

### M19. Génération d'avoir cassée en mode facturation réelle
- `includes/documents-billing/class-acdc-documents-billing-actions-trait.php:265-275`
- `handle_download_credit_note_document` appelle TOUJOURS `get_mock_invoice_record()`
  (ignore `is_documents_billing_demo_enabled`). En mode réel → `reset(array())=false` →
  `get_invoice_document_html(false,...)` = cascade de warnings « array offset on bool » +
  `sanitize_file_name(null)`. Avoir illisible, jamais construit depuis la vraie facture.
- Fix : charger `get_invoice($id)` en mode réel comme le fait le handler facture.

### M20. Numérotation factures/devis : race condition + pas d'unicité
- `class-acdc-documents-billing-core-trait.php:422-432` et `:134-144`
- Numéro = `MAX(...)+1` sans verrou ni index `UNIQUE(number)` → deux créations
  concurrentes = même numéro de facture (doublon, séquence non garantie = non conforme).
- Fix : index UNIQUE + génération transactionnelle/compteur atomique avec ré-essai.

### M21. Doublons d'apprenants à chaque save d'une convention individuelle
- `includes/dossiers-contracts/class-acdc-dossiers-contracts-actions-trait.php:568-628`
- L'auto-création d'apprenant (Particulier/Salarié/Apprenant) n'est pas gardée par
  `$is_new_contract` → à chaque édition-sauvegarde, un nouvel apprenant est inséré sans
  contrôle d'existence, et `learner_ids` grossit.
- Fix : garder par `$is_new_contract` et/ou dédoublonner (SELECT email/nom) avant insert.

### M22. Variable $source_prospect indéfinie dans le formulaire financeur
- `includes/kernel/class-acdc-kernel-render-trait.php:8637`
- `$source_prospect` (défini uniquement dans `render_front_need_form`) utilisé dans
  `render_front_funder_form` → `Warning: Undefined variable` à chaque rendu + bloc de code
  mort (`$cancel_need_url` jamais utilisé). Copier-coller depuis le formulaire NAD.
- Fix : supprimer les lignes 8636-8641 (bloc mort).

---
## 🟡 BASSE (sélection — liste complète à intégrer)

- **B1.** Path traversal (authentifié admin) `get_backup_absolute_path` : filtre
  `str_replace('../')` contournable (`....//` → `../`). `kernel-core-trait.php:3358`. TESTÉ.
- **B2.** Fuite du token de signature vers `api.qrserver.com` (QR distant).
  `sig-email.php:123`. → générer le QR localement.
- **B3.** `confirm()` cassé par apostrophe non échappée → suppression sans confirmation :
  `marketing-render:3947`, `agent-audit-render:118`. → `esc_js()`.
- **B4.** `$prospect->id` au lieu de `$entry->id` (null property PHP 8 + lien cassé) :
  `crm-commercial-render-trait.php:356`.
- **B5.** `handle_nad_resend` : contrôle de capacité insuffisant (`is_user_logged_in`
  seul). `kernel-actions-trait.php:4630`.
- **B6.** `$wpdb->prepare()` sans placeholder (`_doing_it_wrong`).
  `kernel-core-trait.php:9759`.
- **B7.** ZIP restore sans protection Zip-Slip. `kernel-actions-trait.php:3987`.
- **B8.** `sslverify => false` (MITM) : watch AI/YouTube, CERFA `:74,139`, audit-zip `:162`.
- **B9.** Sessions portail non liées à IP/User-Agent (cookie volé réutilisable).
- **B10.** Images de signature/émargement servies en URL publique semi-prédictible (RGPD).
- **B11.** `late_minutes` géant si `start_at` invalide. `emarg-core.php:228`.
- **B12.** number field vidé → '0' au lieu du défaut ; insert_id dead condition ;
  `count(preg_split /u)` fatal UTF-8 invalide. `settings-catalog-actions/programme-pdf`.
- **B13.** Nettoyage cron incomplet (désactivation ≠ uninstall, ~15 crons orphelins).
- **B14.** Incohérences fuseau horaire (tokens audit gmdate vs current_time ; logs marketing).
- **B15.** Double échappement HTML titre PDF `analyse-besoin-apprenant.php:55`.
- **B16.** OTP resend sans rate-limit ; is_otp_verified état global (signature).
- **B17.** Lecture fichier local arbitraire export ZIP (admin) `audit-zip.php:214`.
- **B18.** `sig_data`/proposals : robustesse (tableau → TypeError ; arrondi tarif/jour).
- **B19.** `handle_save_invoice` crée une facture sans numéro (latent, POST forgé).
  `documents-billing-actions-trait.php:305-361`.
- **B20.** Suppression physique d'une facture émise (obligation légale = avoir).
  `documents-billing-actions-trait.php:370-381`.
- **B21.** Handler de signature convention orphelin + statut `'signée'` non reconnu par
  les badges (`completed`/`signed`). `dossiers-contracts-core-trait.php:802,835`.

---
## Modules vérifiés SANS bug critique/haut
- **compliance-quality** (BPF/Qualiopi/réclamations) : handlers protégés (nonce +
  `require_front_manager`/`current_user_can`), divisions gardées (`>0`), `number_format`
  sur floats. Aucun bug critique/haut détecté sur les zones échantillonnées.
- **kernel-core-trait** (~9900 l.) et **kernel-render-trait** (~15500 l.) : très défensifs ;
  seuls B6 (prepare sans placeholder) et M22 ($source_prospect) confirmés.
- **crm-commercial-render**, **proposals-render**, **marketing-render** : échappement
  rigoureux ; seuls B3/B4/H5 confirmés.

## Motifs systémiques (à corriger globalement)
1. **`confirm()` cassé par apostrophe** (≥2 occurrences) → utiliser `esc_js()` partout.
2. **`sslverify => false`** (≥3 occurrences) → réactiver la vérification TLS.
3. **Chemins « facture réelle » vs « démo/devis » désynchronisés** (HT/TVA, variables
   indéfinies, avoir) → factoriser le rendu facture/avoir/devis sur une source unique.
4. **Endpoints publics Qualiopi** (quiz/questionnaire/émargement) : identité non prouvée +
   expiration/rate-limit manquants → renforcer la traçabilité.
