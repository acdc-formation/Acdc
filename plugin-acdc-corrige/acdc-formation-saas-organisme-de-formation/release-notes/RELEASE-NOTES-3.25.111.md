# Notes de version — 3.25.111 (3ᵉ vague d'audit)

Date : 2026-07-22
Cible : « plugin parfait » — passe exhaustive de correction de bugs (audit module par module, 6 axes en parallèle).

## Méthode
Audit complet des 24 modules `includes/` par 6 analyses parallèles indépendantes (kernel/sessions/CRM,
quiz/questionnaires/évaluations, facturation/dossiers/propositions, émargement/signature/conformité,
portails apprenant/formateur/auth, marketing/veille/audit/dispatcher). Chaque bug candidat a été
**vérifié personnellement** (atteignabilité par un hook/handler réel, croisement avec le schéma SQL)
avant correction. `php -l` (8.2/8.3/8.4) et PHPUnit (82 tests) verts.

## 14 bugs corrigés

### Fatals (5)
1. **Attestation de fin de formation** — `wrap_pdf_text()` (méthode inexistante) → `pdf_wrap_text()`.
   Cassait le téléchargement ET l'envoi automatique par e-mail.
2. **Export « détails QCM » (attestation fin de formation)** — 3 méthodes fantômes
   (`get_positioning_question_rows_for_registration`, `stream_csv_export`, `stream_excel_export`)
   remplacées par un export CSV/Excel inline calqué sur l'export positionnement fonctionnel.
3. **Signature d'émargement apprenant** — filtre SQL `expires_at` sur une colonne absente de la table
   apprenants → aucun apprenant ne pouvait signer. Condition supprimée.
4. **Tableau de bord apprenant** — `SELECT q.duration_seconds` (colonne inexistante sur la table quiz)
   → `count(null)` fatal sous PHP 8. Colonne retirée + retour gainé en `(array)`.
5. **Téléchargement résultat d'évaluation** — `render_simple_pdf_output()` → `render_simple_pdf()`.

### Hautes (2)
6. **Avoir (credit note)** — la TVA et le total HT étaient faux dès qu'il y avait des frais
   (transport/repas/lignes annexes) : la base HT prenait le tarif de base seul face à un TTC incluant
   les frais. Base HT alignée sur le sous-total avec frais (comme la facture d'origine).
7. **Éditeur de quiz** — `render_qz_inline_notice()` (inexistant) → notice inline. Fatal évité à
   l'ouverture d'un `quiz_id` supprimé/invalide.

### Moyennes (5)
8. **Score des évaluations en pourcentage 0-100** — les évaluations n'obtenaient jamais de `final_score`
   ni de statut « terminé » : le tableau de performance (seuils 70/50 %) affichait tout en échec.
   Ajout de la finalisation à la clôture de session : `final_score = (bonnes réponses / questions notées) × 100`,
   participant marqué « terminé » + `responded_at`. Les enquêtes de satisfaction (échelle 1-5) restent inchangées.
9. **Indicateurs REST — total d'heures** — `SUM(s.duration_minutes)` (colonne absente des sessions)
   renvoyait 0 → remplacé par `SUM(TIMESTAMPDIFF(MINUTE, s.start_at, s.end_at))`.
10. **Marketing — édition d'identité annulée** — pour un contact issu d'une table métier, les champs
    nom/type/e-mail/tél/société édités revenaient aux valeurs BDD au rechargement. Fusion rendue
    non destructive (on ne complète que les champs vides).
11. **Veille — suppression de source par défaut non persistée** — une source par défaut supprimée
    réapparaissait au rechargement. Mémorisation des ids supprimés (`acdc_of_watch_deleted_default_ids`).
12. **Quiz — versionnage AJAX** — `create_qz_new_version` (inexistant) → `create_qz_quiz_new_version`.
13. **Quiz async — « 0 pts »** — `total_score` (points bruts) n'était pas persisté pour les passations
    asynchrones → PDF de résultat et export CSV affichaient 0. Écriture de `SUM(score_earned)` ajoutée.

## Arbitrages produit appliqués (validés par le commanditaire)
- **Échelle du score de quiz/évaluation** : pourcentage 0-100 (bug #8).
- **Taux d'occupation** : présents / inscrits — *à intégrer* (voir note ci-dessous).

## Reste à traiter (non bloquant)
- « Taux d'occupation » : la définition retenue (présents / inscrits) est à câbler dans le module sessions
  (sera livré dans la vague suivante).
- Numérotation devis/avoirs par `MAX+1` (réutilisation possible après suppression) : amélioration de robustesse
  documentée, à traiter si séquence gapless requise.
