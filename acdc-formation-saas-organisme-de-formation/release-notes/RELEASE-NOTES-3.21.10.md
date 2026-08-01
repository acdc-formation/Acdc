# RELEASE NOTES — Version 3.21.10

## Module : Analyse du besoin — Fondations modulaires

### Nouvelles tables BDD

- `acdc_of_need_blocks` — Bibliothèque des blocs (communs, profils, thématiques, personnalisés)
- `acdc_of_need_questions` — Questions associées à chaque bloc

### Colonnes ajoutées sur `acdc_of_need_analyses` (idempotentes via `maybe_add_table_column`)

`profil`, `thematique`, `statut`, `source_type`, `source_id`, `formation_id`, `repondant_nom`, `repondant_prenom`, `repondant_email`, `entreprise_id`, `apprenant_id`, `dossier_id`, `questions_snapshot`, `reponses`, `synthese`, `token_public`, `token_expire_at`, `sent_at`, `document_url_commanditaire`, `document_path_commanditaire`, `document_url_apprenant`, `document_path_apprenant`

### Seed système (14 blocs + ~149 questions, verrouillés)

| Bloc | Type | Questions |
|---|---|---|
| Démarrage | Commun | 4 |
| Bloc commun | Commun | 15 |
| Entreprise | Profil | 22 |
| Salarié | Profil | 11 |
| Apprenant | Profil | 11 |
| Particulier | Profil | 11 |
| Indépendant | Profil | 12 |
| Intelligence artificielle | Thématique | 9 |
| Automatisation & No-code | Thématique | 9 |
| Marketing digital & WordPress | Thématique | 9 |
| Management & leadership | Thématique | 8 |
| Management restauration | Thématique | 10 |
| Hygiène alimentaire | Thématique | 10 |
| Soft skills | Thématique | 8 |

### Bibliothèque des blocs (sous-onglet `nad_subtab=blocks`)

- Liste filtrée par type (tous / commun / profil / thématique / formation / personnalisé)
- Création et modification de blocs personnalisés
- Désactivation des blocs non système (blocs système = `verrouille=1`, non supprimables)
- Détail d'un bloc : liste de ses questions avec ordre, type, statut
- Ajout et modification de questions personnalisées
- Activation/désactivation individuelle de chaque question

### Formulaire d'analyse amélioré

- Champs `profil`, `thematique`, `statut` (Brouillon / Nouveau / À traiter / Traité / Archivé)
- Champs répondant : `repondant_nom`, `repondant_prenom`, `repondant_email`
- Colonnes liste upgradées : Profil, Thématique, Statut, Source

### Création depuis le modèle — modale améliorée

- Sélection profil + thématique + formation liée
- Génération du titre automatique (`Analyse du besoin — Profil — Thématique`)
- Compatibilité ascendante avec les modèles legacy (`analysis_type` conservé)

### Audit Qualiopi — Critère 1 / Indicateur 1

Deux nouveaux types de documents dans `get_document_types()` :
- `analyse_besoin_commanditaire` — Analyse du besoin — Commanditaire
- `analyse_besoin_apprenant` — Analyse du besoin — Apprenant

### Architecture retenue (Option B confirmée)

1 analyse par apprenant + 1 analyse commanditaire → chaque analyse génère son propre PDF au moment de la signature de la convention.

### Nouveaux hooks `admin-post.php`

- `acdc_save_need_block`
- `acdc_delete_need_block`
- `acdc_save_need_question`
- `acdc_toggle_need_question`

### Paramètres URL (anti-collision router global)

Tous les sous-onglets utilisent le préfixe `nad_` : `nad_subtab`, `nad_block_id`, `nad_question_id`. Jamais `tab`, `id` ou `view` bruts.

### Roadmap

- **3.21.11** — Bibliothèque blocs : liaison données (préremplissage depuis prospect/entreprise/apprenant/dossier)
- **3.21.12** — Page Voir/Modifier avancée + assemblage blocs avant envoi + synthèse
- **3.21.13** — Lien public sécurisé + 4 types d'e-mails + génération PDF + trigger signature convention
