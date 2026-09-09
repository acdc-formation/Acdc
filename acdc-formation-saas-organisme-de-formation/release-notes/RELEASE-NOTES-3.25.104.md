# RELEASE NOTES — 3.25.104 (Phase 1 : tests & valeur probante)

## Fondation de tests (non-régression)
- Extraction de la logique métier critique dans une bibliothèque pure et testable
  (`src/`, namespace `ACDC\Support`, autoloadée sans dépendance à `composer install`) :
  - `Money` : calcul HT/TVA/TTC (source unique devis + facture — verrouille le correctif H1).
  - `QuizScore` : scoring Kahoot + bornage anti-triche du temps de réponse.
  - `DocumentSeal` : empreintes SHA-256.
- Suite **PHPUnit** (`tests/`, `phpunit.xml.dist`) couvrant ces logiques, dont les
  scénarios exacts des bugs corrigés (H1 facturation, H2 triche quiz). 31 assertions vertes.

## Renforcement anti-triche quiz (corrige une insuffisance de la 3.25.101)
- Le bornage `min(client, serveur)` du temps de réponse était **inefficace** :
  `min(0, écoulé) = 0` laissait un tricheur (`response_ms=0`) au score maximum.
  Désormais le **temps serveur est autoritatif** dès qu'il est mesuré (non falsifiable).

## Valeur probante des signatures électroniques
- Scellement **SHA-256** : à la signature, empreinte du document source et du PDF signé
  calculée, stockée (`doc_sha256`, `signed_pdf_sha256`) et journalisée dans la piste d'audit.
  Toute modification ultérieure du document devient détectable.
