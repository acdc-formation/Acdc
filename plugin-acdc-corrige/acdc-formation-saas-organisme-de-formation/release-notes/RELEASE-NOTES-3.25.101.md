# RELEASE NOTES — 3.25.101 (correctifs bugs & sécurité)

Version issue d'un audit complet du code (120 fichiers PHP). 0 erreur de syntaxe.
Correctifs appliqués, par sévérité.

## 🔴 HAUTE
- **Facturation — Total HT erroné** : sur une facture comportant des frais de
  transport/repas/lignes extra, le Total HT n'incluait pas ces frais alors que la TVA
  et le TTC les incluaient (HT + TVA ≠ TTC). Le chemin facture utilise désormais
  `$sous_total_ht` comme le devis. (`documents-billing-core-trait.php`)
- **Quiz live — score falsifiable** : le temps de réponse (base du score) venait
  uniquement du client. Il est désormais borné par le temps écoulé calculé côté serveur.
  (`quizzes-actions-trait.php`)
- **Séances — requêtes MySQL 8** : les requêtes `GROUP BY` (séances validées + cron
  alertes absences) échouaient sous `ONLY_FULL_GROUP_BY` (défaut MySQL 8). Colonnes
  jointes désormais agrégées via `MAX()`. (`sessions-core-trait.php`, `sessions-actions-trait.php`)
- **Signature — brute-force OTP** : la vérification du code OTP (2ᵉ facteur) n'avait
  aucune limite de tentatives. Rate-limiting ajouté (vérification + renvoi).
  (`sig-public.php`)
- **Marketing — fatal PHP 8** : `implode()` appelé sur une chaîne faisait planter la
  fiche scénario. Corrigé. (`marketing-render-trait.php`)

## 🟠 MOYENNE
- **Marketing** : 3 requêtes SQL référençaient des colonnes inexistantes (`company`,
  `contact_first_name`…) → libellés destinataires vides. Colonnes alignées sur le schéma.
- **Émargement** : les liens/tokens n'expiraient jamais (`expires_at` jamais vérifié).
  Contrôle d'expiration ajouté dans les getters.
- **Questionnaires** : nonce non vérifié à la soumission de réponse → `check_admin_referer` ajouté.
- **Quiz live** : variable `$tbl_q` indéfinie (réponse attendue jamais révélée) → corrigée.
- **Réglages/branding** : `pdf_color_map_json` était détruit à chaque sauvegarde
  (bug `strpos 'color_'`) → exclusion des clés JSON de la branche hex.
- **Réglages** : upload SVG retiré des types autorisés (XSS stocké possible).
- **Exports CSV** : neutralisation de l'injection de formule (`= + - @`).
- **Import formations** : clé `title` non définie → mauvaise colonne lue. Corrigé.
- **Facturation** : variables `$logo_data_uri`/`$sig_data_uri_t2` indéfinies (logo/signature
  absents du document) → initialisées. Génération d'avoir cassée en mode réel → corrigée.
- **Dossiers/conventions** : doublons d'apprenants créés à chaque sauvegarde → auto-création
  restreinte aux nouvelles conventions + dédoublonnage par email/nom.
- **Séances** : auto-clôture erronée des séances sans date de fin (`1970-01-01`) → exclues.
  Filtre « formateur » élargi au formateur assigné directement.
- **PDF programme** : normalisation de `objectifs_blocs` (JSON) contre les TypeError.
- **Cycle de vie** : propriétés dynamiques déclarées (Deprecated PHP 8.2) ; `dbDelta`
  d'audit sous garde de version (plus à chaque requête) ; `uninstall.php` corrigé
  (tables réelles supprimées : `watch_items`, `complaints`, `invoices`, `quotes`, etc.).
- **Formulaire financeur** : variable `$source_prospect` indéfinie → bloc mort supprimé.

## 🟡 BASSE
- Path traversal (sauvegarde) : filtre `../` renforcé (rejet de tout `..` + contrôle `realpath`).
- Signature : QR code distant retiré de l'e-mail (ne divulgue plus le token à un tiers).
- Confirmations JS cassées par apostrophes → `esc_js()` / échappement (marketing, agent-audit, émargement).
- CRM : `$prospect->id` → `$entry->id` (lien « Recueil des besoins »).
- Restauration ZIP : protection anti Zip-Slip.
- `wpdb::prepare()` sans placeholder retiré ; robustesse divers champs numériques/UTF-8 ;
  contrôle de capacité renforcé sur `handle_nad_resend` ; blocage suppression facture émise ;
  statut de signature de convention aligné (`completed`).

## Points signalés NON auto-corrigés (choix produit à valider)
- **Numérotation factures** : ajouter un index `UNIQUE(number)` + génération transactionnelle
  (migration de schéma — à faire côté BDD).
- **Traçabilité Qualiopi** (quiz/questionnaire « join » publics) : preuve d'identité de
  l'apprenant à renforcer (évolution fonctionnelle).
- **Filtres signatures/présence** (séances) : labels statiques signalés par TODO (comportement
  inchangé pour ne rien casser).
- **`sslverify => false`** (CERFA/veille) : à réactiver selon l'environnement.
