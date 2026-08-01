# RELEASE NOTES — 3.25.110 (2ᵉ vague d'audit : ~13 bugs corrigés)

## CRITIQUE / HAUTE — fatals & logique cœur
- **Fatal** : `handle_marketing_save_entity` appelait `normalize_marketing_input_list()`
  (méthode inexistante) → toute sauvegarde de liste/étiquette/segment/scénario/webhook
  plantait. Corrigé (`normalize_marketing_csv_array`).
- **Fatal** : `handle_learner_portal_open_resource` appelait `learner_portal_get_resource_source_url()`
  (méthode inexistante) → ouverture d'une ressource externe cassée. Méthode créée (avec
  contrôle d'appartenance par e-mail).
- **Colonne SQL inexistante `passing_score`** (la table a `pass_threshold`) : sur 4 sites
  (génération PDF résultats + 3 requêtes du portail apprenant). Le seuil de réussite était
  figé à 70 % / les requêtes portail échouaient. Corrigé.
- **BPF Cadre C** : `c10` (sous-traitance) et `c_total` absents des données persistées →
  affichés à 0 à l'écran (incohérent avec le PDF CERFA). Corrigé.

## MOYENNE
- Score moyen de session questionnaire dilué par les non-répondants (comptés 0) — cumul
  désormais restreint aux statuts `repondu`/`termine` (3 fonctions).
- Import CSV marketing : un réimport écrasait listes/étiquettes/score ajoutés manuellement —
  désormais fusion des seuls champs d'identité non vides.
- BPF Cadre F1 : lignes « Apprentis » et « Particuliers » manquantes à l'écran — ajoutées.
- Refus de signature via lien GET non protégé (pré-chargeur/antivirus) — nonce requis.

## MINEUR / BASSE
- Apprenant marqué « absent » pouvait quand même signer l'émargement — bloqué.
- Signature renforcée : revérification serveur de l'OTP dans `handle_submit` (défense en profondeur).
- Double échappement du nom apprenant dans le titre du PDF NAD — corrigé.
- Tokens auditeur : comparaison d'expiration en UTC (cohérente avec le stockage) — corrigé.
- Veille : hash de dédoublonnage normalisé (URL échappée) — plus de doublons.
- Classification BPF : `Salarié` versé en C1 (entreprises) au lieu de C9.
- Crons non déplanifiés à la désactivation : 19 crons réellement planifiés désormais nettoyés.
- Code mort retiré (émargement).

## Signalés — décision de modèle requise (NON corrigés à l'aveugle)
- **Échelle de `final_score`** (dashboard) : interprété tantôt en points, tantôt en % (seuil ≥70).
  `final_score` = moyenne de scores numériques (surveys), jamais écrit pour les quiz. Les deux
  tableaux de bord ne peuvent être justes simultanément → à trancher (que doit mesurer l'indicateur ?).
- **Carte « Taux d'occupation des séances »** : compte chaque séance comme « Présent » (placeholder) —
  métrique voulue à définir (remplissage vs présence réelle).

PHPUnit : 82 tests / 195 assertions. Lint PHP 8.2/8.3 : 0 erreur.
