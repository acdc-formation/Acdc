# RELEASE NOTES — 3.21.22

## Nouveau module : Propositions Commerciales

### Déclencheur
Bouton "Créer une proposition commerciale" sur la fiche Recueil des besoins (NAD).
Pré-remplissage automatique depuis les données CRM (entreprise, contact, besoin).

### Flux 3 étapes (modale)

**Étape 1 — Formation & organisation**
- Sélection de la formation depuis le catalogue existant
- Nb de jours, heures/jour, tarif journalier (défaut catalogue, éditable)
- Nb d'apprenants, financement, dates prévues, lieu
- Sélection formateurs (défaut = David, modifiable)
- Calcul automatique du total

**Étape 2 — À propos du client**
- Données pré-remplies depuis CRM (raison sociale, SIRET, adresse, activité)
- Bouton "Générer via IA" → appel GPT-4o-mini → texte de présentation (~200 mots)
- Zone texte éditable (résultat IA ou saisie manuelle)

**Étape 3 — Récapitulatif & génération**
- Résumé de la proposition
- Génération PDF + enregistrement CRM en un clic

### PDF généré — charte ACDC / style Canva
- Page 1 : Couverture (bande dorée, titre formation, destinataire)
- Page 2 : À propos du client
- Page 3 : Projet & organisation (tableau durée/effectifs/financement)
- Page 4 : Proposition financière (tableau avec total, mentions légales)
- Page 5 : Contact ACDC
- Couleurs officielles : #C5A253 (or), #0f2c52 (marine)

### Réglages IA
Réglages → Intégrations IA → Clé API OpenAI
Stockée dans wp_options (acdc_of_integrations), jamais en clair.

### Nouvelle table BDD
`wp_acdc_of_proposals` — créée automatiquement à l'activation/upgrade.

### Fichiers créés/modifiés
- `includes/proposals/class-acdc-proposals-core-trait.php` (nouveau)
- `includes/proposals/class-acdc-proposals-actions-trait.php` (nouveau)
- `includes/proposals/class-acdc-proposals-render-trait.php` (nouveau)
- `includes/class-acdc-plugin.php` — use + register_proposal_hooks
- `includes/kernel/class-acdc-kernel-core-trait.php` — maybe_create_proposal_table
- `includes/kernel/class-acdc-kernel-render-trait.php` — bouton + liste sur fiche NAD
- `includes/settings-catalog/class-acdc-settings-catalog-render-trait.php` — onglet Intégrations IA
- `acdc-formation-saas-organisme-de-formation.php` — require_once + version bump

