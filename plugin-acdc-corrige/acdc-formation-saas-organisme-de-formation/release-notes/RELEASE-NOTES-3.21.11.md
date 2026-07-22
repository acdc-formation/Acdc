# RELEASE NOTES — Version 3.21.11

## Module : Analyse du besoin — Préremplissage dynamique (Option 3B)

### Moteur de préremplissage

Nouvelle méthode `get_nad_prefill_values($analysis)` dans `class-acdc-kernel-core-trait.php`.
Relit les fiches sources liées à chaque affichage — les valeurs sont toujours à jour (Option 3B).
Les saisies manuelles de l'utilisateur sont stockées séparément dans la colonne `reponses`.

Getters sources ajoutés :
- `nad_get_source_data($type, $id)` — prospect ou apprenant
- `nad_get_entreprise_data($id)` — entreprise
- `nad_get_formation_data($id)` — formation
- `nad_get_dossier_data($id)` — convention de formation (registration_contract)
- `nad_resolve_prefill($path, $map)` — helper de résolution de chemin

### Panneau "Sources liées" dans le formulaire (Option 2C)

Panneau visible dans le formulaire Créer/Modifier une analyse :
- Sélecteur Prospect source
- Sélecteur Apprenant lié
- Sélecteur Entreprise commanditaire
- Sélecteur Formation concernée
- Sélecteur Dossier / Convention

Encart "Données actuelles des fiches liées" : affiché si au moins une source est renseignée,
montre les valeurs courantes (répondant, email, téléphone, entreprise, formation, dates, financeur).

### Modale "Créer depuis le modèle" (Option 2C)

Trois nouveaux sélecteurs dans la modale de création :
- Prospect source
- Apprenant
- Entreprise commanditaire

### Handlers mis à jour

- `handle_save_need_analysis()` — sauvegarde `source_type`, `source_id`, `apprenant_id`,
  `entreprise_id`, `formation_id`, `dossier_id`
- `handle_create_need_analysis_from_model()` — idem depuis les champs de la modale

### Architecture retenue (mémo)

- Préremplissage = lecture dynamique (jamais stocké dans `reponses`)
- `reponses` JSON = uniquement les overrides manuels de l'utilisateur
- `source.xxx` → prospect ou apprenant selon source_type/source_id
- `entreprise.xxx` → company via entreprise_id
- `formation.xxx` → formation via formation_id
- `dossier.xxx` → registration_contract via dossier_id

### Roadmap

- **3.21.12** — Page Voir/Modifier avancée — assemblage des blocs bibliothèque
  dans le formulaire d'analyse + synthèse + questions ponctuelles
- **3.21.13** — Lien public sécurisé + 4 types d'emails + PDF + trigger convention signée
