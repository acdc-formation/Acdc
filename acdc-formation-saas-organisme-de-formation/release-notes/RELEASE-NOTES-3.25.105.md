# RELEASE NOTES — 3.25.105 (Phase 1 : performance — fin du N+1 émargement)

## Performance / scalabilité
- Élimination du problème N+1 sur l'émargement dans les listes de séances : les fiches
  d'émargement et leurs apprenants étaient rechargés par ~2 requêtes PAR LIGNE (≈400
  requêtes pour une page de 200 séances). Deux méthodes de préchargement en lot ajoutées
  (`get_by_session_ids`, `get_learners_for_emarg_ids`) et câblées sur les 3 sites concernés
  (liste « Séances validées », statistiques formateurs, dérivation des statuts). Passage de
  O(2N) à O(2) requêtes par page.

## Testabilité
- Logique de dérivation des statuts présence/signature extraite dans `ACDC\Support\EmargeStatus`
  (pur, testé) — 9 tests PHPUnit supplémentaires. Suite totale : 31 tests / 62 assertions.
