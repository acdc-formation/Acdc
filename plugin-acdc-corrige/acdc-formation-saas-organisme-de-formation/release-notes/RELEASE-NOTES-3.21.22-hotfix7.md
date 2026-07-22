# RELEASE NOTES — 3.21.22-hotfix7
## Module : Propositions Commerciales

### Corrections et évolutions

#### 1. Fix doublon formations dans le select (Point 4)
- **Cause** : `get_formations()` retourne les formations ET leurs variantes (`base_formation_id > 0`)
- **Correction** : filtre `base_formation_id = 0` appliqué partout où le select est rendu (modale 3 étapes + formulaire complet)

#### 2. Images thématiques automatiques (Point 3)
- Nouveau helper `get_proposal_thematique_images($code)` → mappe le code thématique vers 2 URLs (couverture + programme)
- Thématiques couvertes : `intelligence_artificielle`, `management_leadership`, `management_restauration`, `hygiene_alimentaire`, `marketing_digital`, `soft_skills`, `automatisation_no_code`
- Le champ `data-thematique` est transmis sur chaque `<option>` du select formations
- Le template HTML injecte automatiquement les bonnes images selon la thématique de la formation choisie

#### 3. Intégration images dans le template HTML (Points 1+2)
- Images statiques : `avant-pendant-apres-propal.png`, `image-a-propos-propal.png`, `Nous-propal.png`, `Nous-formateurs-propal.png`, `Objectif-propal.png`, `Objectifs-Methodes-propal.png`, `Ressources-complementaires-propal.png`, `Contact-propal.png`
- Images dynamiques par thématique : couverture page 1 + bandeau pages programme
- Fallback CSS dégradé conservé si image absente

#### 4. Onglet "Propositions commerciales" dans la sidebar CRM (Point 5)
- Nouveau tab `proposals` injecté dans le groupe CRM via `acdc_portal_navigation_groups`
- Rendu délégué via `acdc_portal_render_unknown_tab`
- Le bouton "Créer une proposition commerciale" reste fonctionnel sur Recueil des besoins
- Vues disponibles : liste globale, formulaire création, formulaire modification, détail

#### 5. Formulaire complet éditable (Point 6)
- Toutes les sections sont éditables : formation & organisation, client, projet & besoins, objectifs, méthodes, programme J1/J2/J3, ressources complémentaires
- Nouvelles colonnes BDD ajoutées via `maybe_add_table_column` (migration non destructive) :
  - `thematique`, `about_project`, `custom_objectives`, `custom_methods`, `custom_prerequisites`, `program_j1`, `program_j2`, `program_j3`, `extra_resources`
- Textes personnalisés pris en priorité sur les données de la fiche formation
- Bouton "Enregistrer & générer le document" : sauvegarde + ouverture du document en une action

### Fichiers modifiés
- `includes/proposals/class-acdc-proposals-core-trait.php`
- `includes/proposals/class-acdc-proposals-actions-trait.php`
- `includes/proposals/class-acdc-proposals-render-trait.php`
- `includes/proposals/templates/proposal-html.php`
- `acdc-formation-saas-organisme-de-formation.php` (version → 3.21.22-hotfix7)

### Anti-régression
- Aucun fichier hors module propositions modifié
- Modale 3 étapes (Recueil des besoins) intacte — seul le fix doublon appliqué
- Colonnes BDD ajoutées via `maybe_add_table_column` → migration non destructive, données existantes préservées
- 0 collision de méthodes entre traits (vérifié)
- 0 erreur PHP lint (vérifié)
- 0 typed property PHP 7.3 ni arrow function (vérifié)
