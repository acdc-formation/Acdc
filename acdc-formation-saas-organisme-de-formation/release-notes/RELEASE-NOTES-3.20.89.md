# ACDC Formation SAAS — version 3.20.89

## Objet

**Onglet « Mon profil » côté portail formateur.**

Le formateur connecté à `/extranet-formateur/?view=profile` peut désormais consulter et modifier ses informations personnelles : photo, identité, coordonnées, bio, préférences de notifications et opt-in RGPD.

Comme arbitré, **les disponibilités hebdomadaires ne sont PAS encore éditables** depuis cet onglet — elles feront l'objet d'un patch dédié (3.20.90) qui apportera un calendrier annuel par demi-journées.

## Fonctionnalités livrées

### 1. Nouvel onglet « Mon profil » dans la nav du portail

Le portail authentifié a maintenant **3 onglets** :

```
Tableau de bord  │  Ma bibliothèque  │  Mon profil
```

Cohérence totale avec le système d'onglets posé en 3.20.87.

### 2. Structure de la page « Mon profil »

Quatre cartes empilées verticalement :

#### Carte 1 — Identité et photo
- Photo de profil ronde 96×96 px (avec placeholder à initiales si absente)
- Bouton de remplacement (input file)
- Case « Retirer ma photo actuelle » (visible uniquement si photo existante)
- Civilité (sélecteur)
- Date de naissance
- Prénom * (obligatoire)
- Nom * (obligatoire)

#### Carte 2 — Coordonnées et présentation
- Téléphone (format libre, placeholder « 06 XX XX XX XX »)
- Présentation / bio (textarea 6 lignes minimum, accepte HTML léger via `wp_kses_post`)

#### Carte 3 — Préférences et confidentialité
- **Notifications** :
  - Me rappeler de mes prochaines sessions
  - M'avertir au démarrage de chaque session
- **Informations visibles par les apprenants (RGPD)** :
  - Photo
  - Nom et prénom
  - Bio
  - Disponibilités

Chaque case est explicitée par un sous-texte en gris.

#### Carte 4 — Informations administratives (lecture seule)

Affiche en blocs gris non éditables :
- E-mail de connexion
- Rôle dans l'organisme
- Type de formateur
- Statut juridique
- SIRET
- Numéro NDA

Avec mention « Pour modifier ces informations, contactez l'administrateur ACDC. »

Note de bas de carte rappelant que les justificatifs (CV, URSSAF, etc.) se gèrent depuis l'onglet « Ma bibliothèque » et que les disponibilités arriveront prochainement.

### 3. Allowlist serveur stricte

La fonction `get_acdc_trainer_self_editable_fields()` retourne la liste des **12 champs** que le formateur peut modifier. Toute tentative d'envoyer un autre champ via le formulaire (par injection d'input cachée par exemple) est **silencieusement ignorée côté serveur**.

Champs autorisés à l'édition par le formateur :

```
gender, first_name, last_name, phone, birth_date, description_text,
session_reminder_enabled, session_start_enabled,
learner_info_photo, learner_info_name,
learner_info_description, learner_info_availability
```

Tous les autres champs (`email`, `role_name`, `siret`, `nda_number`, `legal_status`, `expertise_nsf_codes`, `permissions_*`, `comment_text`, `access_enabled`, etc.) sont **inaccessibles** via cette voie.

### 4. Upload de photo — sécurisé et cohérent avec l'admin

Réutilisation du mécanisme `wp_handle_upload()` standard de WordPress (le même que celui utilisé par votre fiche admin formateur). La photo est stockée dans `wp-content/uploads/YYYY/MM/` (zone publique de WordPress), accessible directement dans `<img src="...">`.

Validation triple :

1. **Taille** : 5 Mo maximum.
2. **Extension** : JPG, JPEG, PNG, WEBP uniquement.
3. **Type MIME** vérifié via `wp_check_filetype_and_ext()` qui inspecte le contenu (anti-MIME-spoofing — un `.exe` renommé en `.jpg` est refusé).

### 5. Retrait de la photo

Si une photo est déjà présente, une case à cocher « Retirer ma photo actuelle » apparaît. Cocher cette case et soumettre vide le champ `photo_url` en BD. (L'ancien fichier physique reste dans `wp-content/uploads/` — c'est WordPress qui gère ce dossier, pas mon plugin. Si vous voulez nettoyer les fichiers orphelins, c'est l'administration des médias WordPress qui s'en charge.)

### 6. Validation et gestion d'erreurs

| Cas | Comportement |
|---|---|
| Prénom vide | Refus + message « Le prénom est obligatoire. » |
| Nom vide | Refus + message « Le nom est obligatoire. » |
| Photo > 5 Mo | Refus + message « La photo dépasse 5 Mo. » |
| Format photo non autorisé | Refus + message clair |
| Photo dont le contenu ne correspond pas à l'extension | Refus (anti-MIME-spoofing) |
| Champ non autorisé envoyé en POST | **Silencieusement ignoré** (allowlist) |
| Aucune modification | Sauvegarde quand même (met à jour `updated_at`) — comportement standard WP |

### 7. Audit

Tous les enregistrements de profil sont tracés dans `wp_acdc_of_trainer_portal_logs` :

```
event_type   = 'profile_updated'
event_data   = { "fields": ["first_name", "phone", "description_text", ...] }
```

La liste des champs effectivement modifiés est journalisée — utile pour la traçabilité Qualiopi et le débogage.

## Cohérence visuelle

- Plein écran (cohérent avec 3.20.88).
- Cartes blanches avec bordure 1 px et rayon 10 px.
- Champs avec hauteur 40 px, bordure arrondie 8 px, focus bleu cohérent.
- Photo ronde 96×96 avec bordure douce.
- Placeholder à initiales sur fond `#f3e3bf` (palette ACDC) avec texte `#1E4777`.
- Cases à cocher avec sous-texte explicatif gris.
- Carte « Lecture seule » avec fond gris très clair pour bien distinguer.
- Bouton « Enregistrer mes modifications » en bas à droite (or doré, classe standard).

## Engagement de préservation

- **Aucune migration BD.** Tous les champs existaient déjà dans `wp_acdc_of_trainers`.
- **Compatibilité totale avec l'admin.** Le formulaire admin existant continue de gérer les mêmes champs sans changement. Si l'admin et le formateur modifient en même temps, la dernière écriture gagne (last-write-wins, comportement BD standard).
- **Aucune dépendance externe ajoutée.**

## Limites connues (à régler en 3.20.90)

### 🔴 Disponibilités non éditables côté formateur

Pour cette version, le formateur ne peut PAS modifier ses disponibilités depuis son portail. La carte « Informations administratives » mentionne « Vos disponibilités hebdomadaires seront éditables prochainement via un calendrier annuel dédié. »

Côté admin, le formulaire continue de fonctionner avec le widget hebdo actuel (inchangé).

Le 3.20.90 livrera :
- Migration du modèle BD `availability_json` vers `{ weekly: {...}, exceptions: {...} }` avec demi-journées.
- Calendrier annuel cliquable côté formateur (édition) et côté admin (consultation).

## Procédure de test

### Test 1 — Affichage de la page profil

1. Purger LiteSpeed (DB + objets + plugin).
2. Téléverser le ZIP, remplacer.
3. Purger encore, `Cmd + Shift + R`.
4. Se connecter à `/extranet-formateur/` avec votre compte test.
5. Vérifier la **nav d'onglets** : 3 entrées maintenant — « Tableau de bord » / « Ma bibliothèque » / **« Mon profil »**.
6. Cliquer sur « Mon profil » → URL devient `/extranet-formateur/?view=profile`.
7. Vérifier le rendu en plein écran avec les 4 cartes empilées.

### Test 2 — Photo de profil

1. Sur la carte « Identité et photo », vérifier l'affichage actuel :
   - Si pas de photo : placeholder à initiales (votre prénom + votre nom, en majuscules, sur fond beige).
   - Si photo existante : photo ronde 96×96.
2. **Upload** : choisir un JPG, soumettre → vérifier que la photo s'affiche après rechargement.
3. **Retrait** : cocher « Retirer ma photo actuelle », soumettre → retour au placeholder à initiales.
4. **Format invalide** : tenter un `.gif` ou un `.txt` renommé en `.jpg` → message d'erreur clair.
5. **Taille** : tenter une photo > 5 Mo → refus.

### Test 3 — Modification des champs simples

1. Modifier votre prénom (ex. ajouter un accent), votre téléphone, votre bio.
2. Cliquer « Enregistrer mes modifications ».
3. Vérifier le message vert « Votre profil a bien été mis à jour. »
4. Vérifier la persistance après rechargement.
5. **Cohérence admin** : aller sur la fiche admin de ce formateur → vérifier que les mêmes valeurs sont visibles côté admin.

### Test 4 — Validation

1. Effacer le prénom, soumettre → message rouge « Le prénom est obligatoire. »
2. Effacer le nom, soumettre → message rouge « Le nom est obligatoire. »

### Test 5 — Cases à cocher RGPD et notifications

1. Cocher / décocher diverses cases.
2. Soumettre, vérifier la persistance.
3. **Cohérence admin** : aller sur la fiche admin → vérifier que les checkboxes correspondantes côté admin reflètent les mêmes valeurs.

### Test 6 — Champs en lecture seule

1. Vérifier que la carte « Informations administratives » liste 6 champs en gris.
2. Vérifier que ces champs ne sont **jamais éditables** depuis ce portail.
3. **Test sécurité** : ouvrir l'inspecteur, ajouter manuellement `<input type="hidden" name="profile[email]" value="autre@email.com">` dans le formulaire, soumettre. Vérifier que **l'e-mail en BD n'a pas changé** (allowlist serveur).

### Test 7 — Audit

(Optionnel SQL)

```sql
SELECT created_at, event_type, event_data
FROM wp_acdc_of_trainer_portal_logs
WHERE event_type = 'profile_updated'
ORDER BY id DESC LIMIT 5;
```

Doit montrer la liste des champs modifiés à chaque enregistrement.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump 3.20.88 → 3.20.89.
- `includes/class-acdc-plugin.php` — branchement de l'action `acdc_trainer_update_own_profile`.
- `includes/kernel/class-acdc-kernel-core-trait.php` — 3 nouveaux helpers :
  - `get_acdc_trainer_self_editable_fields()` (allowlist)
  - `get_acdc_trainer_photo_allowed_mimes()`
  - `get_acdc_trainer_photo_max_size_bytes()`
- `includes/trainer-portal/actions/class-acdc-trainer-portal-actions-trait.php` — handler `handle_trainer_update_own_profile()`.
- `includes/trainer-portal/render/class-acdc-trainer-portal-render-trait.php` :
  - Routeur shortcode étendu pour la vue `profile`.
  - Onglet « Mon profil » ajouté à la nav.
  - Nouvelle fonction `render_trainer_portal_profile()`.
  - Texte du dashboard mis à jour pour mentionner les 2 onglets fonctionnels.

## Suite

Module Formateur — état d'avancement :

| Patch | Périmètre | Statut |
|---|---|---|
| 3.20.82 | Schéma BD | ✅ |
| 3.20.83 | Auth custom + e-mail invitation | ✅ |
| 3.20.84 | CSS portail | ✅ |
| 3.20.85 | Template blank | ✅ |
| 3.20.86 | Bibliothèque admin | ✅ |
| 3.20.87 | Bibliothèque portail + nav | ✅ |
| 3.20.88 | Plein écran | ✅ |
| **3.20.89** | **Mon profil (sauf calendrier)** | ✅ |
| 3.20.90 | **Calendrier annuel des disponibilités** (demi-journées, hebdo + exceptions) | À venir |
| 3.20.91 | Mes sessions | À venir |
| 3.20.92 | Alertes Qualiopi automatiques | À venir |

Validez le 3.20.89, et on attaque le **3.20.90 — Calendrier annuel** (le gros morceau).
