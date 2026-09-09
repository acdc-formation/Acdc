# RELEASE NOTES — 3.25.102 (durcissements complémentaires)

Fait suite à la 3.25.101 (audit complet). Traite les 4 points laissés en attente,
de façon NON régressive.

## Correctifs
- **Numérotation factures/devis — anti-doublon (race condition)** : la génération du
  numéro (`FA-AAAA-n` / `DE-AAAA-n`) est désormais sérialisée par un verrou nommé MySQL
  (`GET_LOCK`) et le numéro est réattribué juste avant l'insertion. Deux créations
  concurrentes ne peuvent plus obtenir le même numéro. Aucune migration de schéma requise ;
  les brouillons sans numéro ne sont pas affectés.
  (`documents-billing-core-trait.php` — `save_invoice`, `save_quote`)
- **Vérification TLS réactivée** : tous les appels HTTPS sortants (`wp_remote_get`)
  passent de `sslverify => false` à `true` (veille IA/YouTube, CERFA, export ZIP, PDF).
  Protège contre l'interception (MITM) et la fuite de clés API.
  (11 emplacements : `watch-*`, `cerfa_bpf_generator.php`, `audit-zip.php`,
  `kernel-actions-trait.php`, `documents-billing-core-trait.php`, `proposals-render-trait.php`)
- **Filtres « Statut signatures » et « Statut présence » (séances) rendus opérationnels** :
  les libellés, auparavant codés en dur (« Voir détails »/« Validée »), sont désormais
  calculés à partir de l'état réel d'émargement (Complète / Partielle / En attente /
  Absences / Non générée). Les listes déroulantes de filtre exposent ces vraies valeurs.
  (`sessions-core-trait.php`, `sessions-render-trait.php`)
- **Anti-abus sur le « rejoindre » public des questionnaires** : rate-limiting par IP
  (100 / 10 min) ajouté à `handle_join_questionnaire_session`, comme sur le quiz live.
  Limite la création massive automatisée de participants.
  (`questionnaires-actions-trait.php`)

## Reste en évolution (choix produit)
- **Preuve d'identité forte de l'apprenant** sur les « join » publics (quiz/questionnaire) :
  au-delà du rate-limiting ajouté, une vérification individuelle (jeton nominatif envoyé
  par e-mail) nécessite une évolution du parcours front — à cadrer côté produit.
- **Index `UNIQUE(number)` en base** : le verrou applicatif ci-dessus élimine la course en
  pratique ; un index unique dédié (avec migration des numéros vides en NULL et
  dédoublonnage préalable) reste recommandé à terme.
