# Notes de version — 3.25.113 (5ᵉ vague — 2ᵉ passe d'audit approfondie)

Date : 2026-07-22
Méthode : seconde passe d'audit sur 6 angles neufs (schéma/migrations, sécurité XSS/CSRF/SQLi,
câblage REST/AJAX/JS, modules peu couverts, cohérence dates/argent/états, i18n/robustesse PHP 8),
puis correction déléguée à des agents ciblés par fichier. Chaque trouvaille vérifiée atteignable
avant correction. `php -l` (8.2/8.3/8.4) + PHPUnit (82 tests / 195 assertions) : verts.

## 19 correctifs

### Fatals / bloquants
1. **Émargement sur MySQL** — `ALTER TABLE … ADD COLUMN IF NOT EXISTS` est une extension MariaDB,
   rejetée par MySQL (l'hébergement de production tourne sur MySQL). Conséquences : colonnes
   `trainer_ip`/`trainer_ua`/`learner_ua` jamais créées → l'`UPDATE` de signature échouait
   (« Unknown column »), et `install()` sur `init` sans garde de version rejouait l'ALTER (erreur
   SQL loggée) à chaque requête. Corrigé : helper portable `SHOW COLUMNS` + `ALTER ADD COLUMN`,
   colonnes déclarées dans les `CREATE TABLE`, et garde de version (`acdc_emarg_db_version`).
2. **Actions Qualiopi** — colonne `priority_level` écrite (INSERT) mais absente de
   `acdc_of_questionnaire_actions` → action correctif/amélioration jamais créée + erreur SQL au
   filtrage par priorité. Migration `maybe_add_table_column` ajoutée.
3. **Quiz — cycle de vie** — les boutons « Activer », « Repasser en brouillon », « Verrouiller »
   postaient sur admin-ajax.php mais seuls des handlers `admin_post_` existaient (migration WAF
   3.25.78 incomplète) → boutons morts. Handlers `wp_ajax` ajoutés (wrappers JSON réutilisant la
   logique métier core).
4. **Quiz — archivage** — écrivait `status='archived'` dans la table legacy `acdc_of_quizzes`
   (sans colonne `status`) au lieu de `acdc_of_qz_quizzes` → archivage sans effet. Corrigé + `archived_at`.
5. **Quiz — anti-triche live hors-UTC** — `server_elapsed_ms = time() − strtotime(started_at)`
   mélangeait UTC et heure murale WP. Sur offset positif l'anti-triche était contourné (elapsed=0 →
   retombée sur le temps client), sur offset négatif tous les scores devenaient « lents ». Base de
   temps homogène (`current_time('timestamp')`).

### Cohérence des données
6. **Convention (PDF juridiquement contraignant)** — délai de rétractation, clause de litiges et
   articles additionnels saisis PAR contrat étaient ignorés au profit des réglages globaux. Priorité
   rétablie aux valeurs du contrat (motif « contrat sinon défaut » aligné sur les autres articles).
7. **Uniformisation des fuseaux** — comparaisons de temps homogénéisées en heure WP : fenêtre de
   rappel J-2 des quiz (remplacement de `NOW()`/`DATE_ADD(NOW())` par des bornes PHP), expiration des
   tokens NAD (2 chemins) et participant, badge « délai dépassé » des dossiers, chrono live.
8. **Signature électronique** — verrou atomique anti double-signature : `UPDATE … WHERE id=%d AND
   status <> 'signe'` avant génération du PDF ; en cas de course, seule la 1ʳᵉ soumission scelle.
9. **Quiz — moyenne de session** — `array_filter` sans callback excluait les scores légitimes à 0,
   gonflant la moyenne. Filtre corrigé (ne retire que null/'').
10. **Programme PDF** — durée `HH:MM` : les minutes étaient perdues (« 07:30 » → « 7h »). Corrigé
    (« 7h30 »).

### Sécurité / robustesse
11. **Questionnaire** — nonce désormais vérifié sur `handle_join_questionnaire_session` (writer
    public ; le formulaire émettait déjà le nonce).
12. **Veille** — normalisation d'URL (retrait `utm_*`/`fbclid`/`gclid`/`mc_*`, slash final, ancre,
    tri des paramètres) avant hachage → déduplication cohérente.
13. **Marketing** — import CSV dédoublonné par e-mail contre les fiches métier
    (prospects/apprenants/entreprises/financeurs/formateurs/contacts) et le store existant : un e-mail
    déjà connu enrichit la fiche au lieu de créer un doublon `import:`.
14. **i18n** — 7 chaînes d'interface à l'échappement cassé (`\xc3\xa9` affiché littéralement, dans les
    messages de signature de devis et la confirmation de suppression de réclamation) corrigées, +
    2 comparaisons de statut mortes nettoyées (littéraux `trait\xc3\xa9`/`r\xc3\xa9alis`).

## Constat d'audit (positif)
La 2ᵉ passe sécurité n'a trouvé aucune vulnérabilité critique/haute atteignable : XSS échappés,
nonces/capabilities présents (délégués à des helpers), requêtes préparées, flux publics tokenisés,
anti-brute-force, injection de formule CSV/XLSX neutralisée. Les chemins monétaires convergent tous
vers `Money::invoiceTotals()` (pas de divergence HT/TVA/TTC résiduelle).

## Backlog restant (non bloquant)
- Numérotation devis/avoirs par `MAX+1` (réutilisation possible après suppression) — à traiter si une
  séquence sans trou est exigée (décision comptable).
- `acdc_emarg_db_version` à ajouter à la liste des options nettoyées dans `uninstall.php` (cohérence).
- Contrôles host quiz live (`host_show_results`/`kick`/`end_session`) enregistrés mais sans bouton :
  à câbler ou retirer (feature incomplète, pas de bug utilisateur).
