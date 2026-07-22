# Release Notes — 3.21.19

## Module Enquêtes — Refonte UX complète (Google Forms niveau)

### Page publique de réponse (ce que voient les répondants)

- **Template standalone** : la page de réponse aux enquêtes est désormais servie sans thème WordPress (plein écran, sans sidebar ni header WordPress), identique au modèle NAD
- **Hero header** avec image de fond par type d'enquête (6 images configurées), overlay gradient, logo organisme, badge type d'enquête, titre de la session, nom du destinataire
- **Barre de progression** intégrée au hero (affiche le nombre de questions)
- **Rendu des questions** entièrement refait :
  - `Étoiles` : 5 étoiles dorées cliquables avec effet hover
  - `Smileys` : 5 faces expressives (😞😕😐🙂😄) avec sélection visuelle
  - `NPS (0-10)` : boutons numérotés avec libellés min/max configurables
  - `Paragraphe` : textarea stylisée
  - `Réponse courte` : input text stylisé
  - `Cases à cocher / Choix unique` : options stylisées avec sélection visuelle
  - `Liste déroulante` : select stylisé
  - `Oui / Non` : 2 pill buttons marine/or
- **Écrans terminaux** stylisés : lien expiré, déjà répondu (avec confirmation), lien invalide, aucune question
- **Bouton de soumission** pleine largeur, gold gradient, responsive mobile

### Éditeur admin (interface de création/modification des enquêtes)

- **Éditeur partagé** unique pour les 6 types d'enquêtes (mid/hot/cold/trainer/company/funder) — suppression du code dupliqué
- **8 types de blocs** disponibles (contre 2 précédemment) :
  - Étoiles (1 à 5 ★)
  - Smileys (😞 à 😄)
  - Échelle NPS (0 à 10) avec min/max et libellés configurables
  - Paragraphe (texte long)
  - Réponse courte
  - Cases à cocher / Choix unique (avec sélection radio ou cases multiples)
  - Liste déroulante
  - Oui / Non
- **Interface dynamique** : ajout/suppression de blocs, changement de type avec affichage adaptatif des champs de configuration (réponses, type de sélection, bornes NPS), ajout de réponses à la volée

### Architecture technique

- `normalize_survey_question_blocks_for_questionnaire()` étendu pour les 5 nouveaux types
- `sanitize_survey_block_full()` — helper partagé pour la sanitisation des blocs (tous les 6 save handlers mis à jour)
- `get_survey_hero_image_url()`, `get_survey_type_display_label()`, `get_survey_block_types_map()` — nouveaux helpers
- `survey_maybe_override_template()` — filtre template_include pour les sessions survey uniquement (quiz/tests/évaluations non impactés)
- Template `includes/questionnaires/templates/standalone-survey-form.php` créé

### Compatibilité

- PHP 7.3 maintenu (pas de typed properties, pas de fn(), pas de ??=)
- Données existantes préservées (backward compat : notation → étoiles, champ_texte → paragraphe, cases_a_cocher → conservé)
- Aucun changement de structure BDD
- Aucun impact sur les modules hors enquêtes

### Images hero configurées

| Type | URL |
|------|-----|
| À mi-parcours | `.../Enquete-a-mi-parcours-mid.png` |
| À chaud | `.../Enquete-a-chaud-hot.png` |
| À froid | `.../Enquete-a-froid-cold.png` |
| Formateur | `.../Enquete-formateur-trainer.png` |
| Entreprise | `.../Enquete-entreprise-company.png` |
| Financeur | `.../Enquete-financeur-funder.png` |
