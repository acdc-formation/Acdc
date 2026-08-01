# ACDC Formation SAAS — version 3.20.86

## Objet

**Bibliothèque personnelle du formateur — côté admin.**

Première vraie zone de contenu du module Formateur après les trois patches d'infrastructure (3.20.83 auth, 3.20.84 CSS, 3.20.85 template). Vous pouvez désormais déposer, consulter, télécharger et supprimer les justificatifs administratifs et pédagogiques de chaque formateur (CV, diplômes, certifications, attestations URSSAF, RC pro, mandats, preuves Qualiopi, etc.) directement depuis sa fiche admin.

Le côté front (le formateur lui-même qui consulte ou dépose ses propres docs depuis son extranet) sera livré au **3.20.87**.

## Fonctionnalités livrées

### 1. Section « Bibliothèque personnelle » sur la fiche admin formateur

Visible en bas de la fiche en mode **Édition** et en mode **Lecture** (pas en création — il faut d'abord enregistrer le formateur pour qu'il ait un ID).

Structure :

- **En-tête de section** avec icône et description rappelant les formats acceptés et la limite de taille.
- **Formulaire d'ajout** (en mode édition uniquement) : tous les champs en un seul bloc.
- **Grille de 8 cartes** — une par catégorie, sur 2 colonnes (1 colonne sur mobile).

### 2. Catégories supportées (8)

| Clé | Libellé |
|---|---|
| `cv` | CV |
| `diploma` | Diplômes |
| `certification` | Certifications |
| `urssaf_attestation` | Attestation URSSAF (vigilance) |
| `rc_pro` | Assurance RC Pro |
| `mandate` | Mandats et conventions |
| `qualiopi_proof` | Preuves Qualiopi |
| `other` | Autres justificatifs |

### 3. Formulaire d'ajout — champs

| Champ | Statut | Détail |
|---|---|---|
| Catégorie | Obligatoire | Liste déroulante des 8 catégories |
| Libellé | Optionnel | Texte libre. Si vide, libellé = nom de la catégorie |
| Fichier | Obligatoire | PDF, JPG, PNG, DOC, DOCX, PPTX. **100 Mo maximum** |
| Date d'émission | Optionnel | Date du document |
| Date d'expiration | Optionnel (recommandée pour URSSAF/RC pro) | Active les badges de vigilance |
| Preuve Qualiopi | Optionnel | Cochez pour repérer les pièces qui comptent dans l'audit |
| Notes administratives | Optionnel | **Privées** — ne seront pas visibles par le formateur côté front |

### 4. Affichage des cartes

Chaque carte affiche :

- **Libellé** de la catégorie + compteur du nombre de documents
- Soit **« Aucun document »** en italique gris
- Soit la **liste des documents** avec, pour chacun :
  - Libellé du document
  - **Badges visuels** :
    - 🟦 *Qualiopi* (bleu) si flag activé
    - 🟢 *Expire le DD/MM/AAAA* (vert) si expiration > 60 jours
    - 🟡 *Expire le DD/MM/AAAA (X j)* (jaune) si expiration ≤ 60 jours — **vigilance**
    - 🔴 *Expiré le DD/MM/AAAA* (rouge) si date dépassée
  - Nom du fichier d'origine + date de dépôt (en gris fin)
  - Notes admin (si renseignées, en italique avec icône 📝)
  - Boutons **Télécharger** et **Supprimer**

### 5. Téléchargement sécurisé

Le fichier physique n'est **jamais accessible directement** par URL. Le téléchargement se fait toujours via un handler `acdc_download_trainer_document` qui :

1. Vérifie la capability admin (`manage_options`).
2. Vérifie la nonce associée au document précis.
3. Reconstruit le chemin absolu via `realpath()` puis vérifie qu'il est bien sous le dossier autorisé (anti-path-traversal).
4. Sert le fichier avec les bons en-têtes (`Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`).

### 6. Suppression — hard delete

Comme arbitré : pas de soft delete. Le clic sur « Supprimer » :

1. Demande confirmation JavaScript (`confirm()`).
2. Supprime le fichier physique du serveur (`unlink` après validation du chemin).
3. Supprime la ligne en BD (`DELETE FROM`).

Le tout via un handler avec capability + nonce, donc impossible à déclencher accidentellement par un lien malveillant.

## Stockage des fichiers

### Architecture choisie

```
wp-content/uploads/acdc-of/trainer-documents/
├── .htaccess              ← bloque l'accès direct (Deny from all)
├── index.php              ← empêche le listing
├── 12/                    ← un sous-dossier par formateur (id=12)
│   ├── cv_1761500000_cv-david.pdf
│   ├── urssaf_attestation_1761500100_attestation-q4.pdf
│   └── rc_pro_1761500200_assurance-mma-2026.pdf
├── 27/
│   └── diploma_1761500300_master-management.pdf
└── ...
```

### Création automatique

À la première utilisation, la fonction `ensure_trainer_documents_upload_dir()` crée le dossier racine et y dépose un `.htaccess` avec `Deny from all`. Le sous-dossier `{trainer_id}/` est créé à la volée lors du premier upload pour un formateur donné.

### Nommage des fichiers

Pattern : `{categorie}_{timestamp}_{slug-du-nom}.{ext}`

Exemples :
- `cv_1761500000_cv-david-contal.pdf`
- `urssaf_attestation_1761500100_attestation-vigilance-q4.pdf`
- `rc_pro_1761500200_assurance-mma-2026.pdf`

Le **nom d'origine** du fichier est conservé en BD (champ `file_name`) et utilisé au moment du téléchargement (le formateur récupère le fichier avec son nom lisible, pas le nom hashé interne).

### Validation à l'upload

Triple validation :

1. **Extension du fichier** doit être dans la liste autorisée.
2. **Type MIME** vérifié via `wp_check_filetype_and_ext()` qui inspecte le contenu, pas juste l'extension (anti-MIME-spoofing).
3. **Taille** : 100 Mo max côté plugin. Si la config serveur est plus restrictive, message d'erreur clair invitant à augmenter `upload_max_filesize` et `post_max_size`.

## Cohérence visuelle

- Variables CSS ACDC respectées : `var(--acdc-text)`, `var(--acdc-border)`, `var(--acdc-primary)`.
- Couleurs sémantiques en dur (vert succès `#1a7d3b`, rouge danger `#c62828`, jaune attention) — comme arbitré dans la session précédente sur la Bibliothèque côté formation.
- Cartes blanches avec bordure 1 px, rayon 10 px (conformes au cahier de style).
- Boutons « Télécharger » / « Supprimer » : style cohérent avec les autres listes du plugin (hauteur 30 px, bordure légère, rayon 6 px).
- Grille responsive : 2 colonnes desktop / 1 colonne mobile (breakpoint 880 px).

## Engagement de préservation

- **Aucune modification** des autres pages du back office.
- **Aucune migration BD** — la table `wp_acdc_of_trainer_documents` existe depuis le 3.20.82, on l'utilise désormais.
- **Aucune dépendance externe ajoutée.**
- **CSS scopé** à la section : tous les sélecteurs commencent par `.acdc-tdoc-` pour éviter toute interférence avec d'autres modules.

## Points de vigilance opérationnelle

### Configuration PHP du serveur

Pour pouvoir uploader 100 Mo, votre `php.ini` (ou `.user.ini`, ou `wp-config.php`) doit avoir :

```ini
upload_max_filesize = 100M
post_max_size       = 110M     # un peu plus que upload_max_filesize
max_execution_time  = 300      # pour les uploads lents
memory_limit        = 256M     # confortable
```

Sur **PlanetHoster N0C**, ces valeurs se règlent depuis le panneau « N0C → PHP → Configuration ». Si l'upload échoue avec un message « dépasse la taille maximale autorisée », c'est ici qu'il faut intervenir.

### Sauvegardes

Les fichiers physiques sont dans `wp-content/uploads/acdc-of/trainer-documents/`. **Vérifiez que ce dossier est inclus dans vos sauvegardes**. Sinon, en cas de restauration de la BD seule, vous récupérerez les références mais pas les fichiers.

### Sécurité du `.htaccess`

Le blocage par `.htaccess` fonctionne sur Apache et **LiteSpeed** (votre cas chez PlanetHoster). Sur Nginx pur, le `.htaccess` est ignoré — il faudrait alors une règle de bloc côté nginx.conf. Pour vous : OK direct.

Pour vérifier que la protection fonctionne : essayez d'accéder à un fichier en URL directe, par exemple `https://acdcformation.com/wp-content/uploads/acdc-of/trainer-documents/12/cv_xxx.pdf`. Vous devez recevoir une **403 Forbidden**. Si le fichier se télécharge, alertez-moi (config serveur à durcir).

## Limites connues du 3.20.86

- **Pas d'édition après upload.** Pour modifier la date d'expiration, le label, les notes ou le flag Qualiopi : il faut supprimer puis ré-uploader. Une UI d'édition inline pourra être ajoutée en 3.20.87 si besoin.
- **Pas encore de vue formateur.** Les documents sont uniquement administrables par l'admin. Le formateur connecté à son extranet ne les voit pas (sera traité au 3.20.87).
- **Pas d'alerte automatique** sur les expirations proches. Le suivi est visuel (badges jaunes/rouges sur la fiche). Une notification e-mail à J-30 / J-7 fera l'objet d'un patch ultérieur dans le cadre du module Qualiopi.

## Procédure de test

### Test 1 — Préparer le dossier d'upload

1. Purger LiteSpeed (DB + objets + plugin).
2. Téléverser le ZIP, remplacer.
3. Purger à nouveau, forcer un reload navigateur.
4. **Vérifier la création du dossier** par FTP / SSH / gestionnaire de fichiers : `wp-content/uploads/acdc-of/trainer-documents/` doit exister, avec un `.htaccess` et un `index.php` à l'intérieur.

> Le dossier sera créé au moment où vous chargez la page d'édition d'un formateur (premier appel à `ensure_trainer_documents_upload_dir()`). Si vous ne le voyez pas tout de suite, ouvrez d'abord une fiche formateur en édition.

### Test 2 — Affichage en mode lecture/édition

1. Aller sur **Formateurs → Modifier** un formateur existant.
2. Faire défiler la page : la section **« 📚 Bibliothèque personnelle »** doit apparaître en bas, sous le formulaire principal.
3. Vérifier le formulaire d'ajout (catégorie, libellé, fichier, dates, Qualiopi, notes).
4. Vérifier la grille de 8 cartes vides avec « Aucun document » dans chacune.
5. Aller sur **Formateurs → Voir** un formateur (mode lecture) : la section apparaît mais le formulaire d'ajout est masqué et les boutons « Supprimer » disparaissent. Seuls les boutons « Télécharger » restent.
6. Aller sur **Formateurs → Nouveau** : la section **n'apparaît pas** (le formateur n'existe pas encore).

### Test 3 — Upload d'un document

1. Sur la fiche d'un formateur, choisir la catégorie **CV**.
2. Joindre un fichier PDF.
3. Laisser libellé vide.
4. Cliquer « Ajouter le document ».
5. Vérifier le message vert de succès : « Document ajouté à la bibliothèque ».
6. Vérifier que la carte CV affiche maintenant **(1)** avec le document listé.
7. Vérifier que le libellé affiché reprend le nom de la catégorie (« CV ») puisque vous avez laissé vide.

### Test 4 — Upload avec date d'expiration

1. Sur la même fiche, ajouter un document **Attestation URSSAF** avec :
   - Libellé : « Attestation de vigilance Q4 2025 »
   - Fichier : un PDF
   - Date d'expiration : **dans 30 jours**
   - Cocher **Preuve Qualiopi**
   - Notes : « Reçue par mail le 15/01/2026 »
2. Vérifier la carte URSSAF :
   - Libellé personnalisé visible
   - Badge **Qualiopi** (bleu)
   - Badge **Expire le ... (30 j)** (jaune ⚠ — moins de 60 jours)
   - Notes affichées en italique avec 📝
3. Modifier la date d'expiration en **dans 90 jours** (en supprimant et recréant) → badge devient **vert**.
4. Modifier en **hier** → badge devient **rouge**.

### Test 5 — Téléchargement sécurisé

1. Sur la carte CV, cliquer **Télécharger**.
2. Vérifier que le fichier est servi avec son nom d'origine (ex. `mon-cv.pdf`).
3. Tester l'**accès direct** : copier l'URL d'un fichier (ex. `https://acdcformation.com/wp-content/uploads/acdc-of/trainer-documents/12/cv_xxxxx.pdf`) dans un nouvel onglet en navigation privée. **Vous devez recevoir une 403 Forbidden**.
4. Tester avec un utilisateur non connecté : visiter directement l'URL `admin-post.php?action=acdc_download_trainer_document&document_id=1` → vous devez recevoir « Accès refusé ».

### Test 6 — Suppression

1. Sur n'importe quel document, cliquer **Supprimer**.
2. Confirmer la popup JS.
3. Vérifier la disparition du document de la carte.
4. (Optionnel) Vérifier par FTP que le fichier physique a bien été supprimé du dossier.

### Test 7 — Validation des formats

1. Tenter d'uploader un fichier non autorisé (ex. `.zip`, `.exe`, `.txt`).
2. Vérifier le message d'erreur clair : « Format de fichier non autorisé. Formats acceptés : PDF, JPG, PNG, DOC, DOCX, PPTX. »
3. Tenter d'uploader un fichier renommé (ex. un `.exe` renommé en `.pdf`).
4. Vérifier le message d'erreur : « Le contenu du fichier ne correspond pas à son extension. Upload refusé. » (validation MIME)

### Test 8 — Validation de la taille

1. Tenter d'uploader un fichier > 100 Mo.
2. Vérifier le message d'erreur : « Le fichier dépasse 100 Mo » ou message serveur si la limite PHP est plus basse.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump 3.20.85 → 3.20.86.
- `includes/class-acdc-plugin.php` — branchement de 3 nouvelles actions admin-post (`upload`, `delete`, `download`).
- `includes/kernel/class-acdc-kernel-core-trait.php` — 6 nouvelles fonctions helper :
  - `get_acdc_trainer_document_categories()`
  - `get_acdc_trainer_document_allowed_mimes()`
  - `get_acdc_trainer_document_max_size_bytes()`
  - `ensure_trainer_documents_upload_dir()`
  - `get_trainer_document_absolute_path()`
  - `get_trainer_documents_grouped()`
  - `describe_trainer_document_expiry()`
- `includes/kernel/class-acdc-kernel-actions-trait.php` — 3 nouveaux handlers :
  - `handle_admin_upload_trainer_document()`
  - `handle_admin_delete_trainer_document()`
  - `handle_admin_download_trainer_document()`
- `includes/kernel/class-acdc-kernel-render-trait.php` — section Bibliothèque dans `render_front_trainer_form()` + nouvelle fonction `render_admin_trainer_library_section()`.

## Suite

Module Formateur — état d'avancement :

| Patch | Périmètre | Statut |
|---|---|---|
| 3.20.82 | Schéma BD documents/resources | ✅ |
| 3.20.83 | Auth custom + page connexion + e-mail invitation | ✅ |
| 3.20.84 | CSS portail chargé sur la page formateur | ✅ |
| 3.20.85 | Template blank épuré sur la page formateur | ✅ |
| **3.20.86** | **Bibliothèque personnelle — côté admin** | ✅ |
| 3.20.87 | Bibliothèque côté portail formateur (vue + dépôt par le formateur lui-même) | À venir |
| 3.20.88+ | Ressources pédagogiques + UI permissions granulaires + alertes Qualiopi | À venir |

Validez le 3.20.86, et on enchaîne sur le 3.20.87 quand vous voulez.
