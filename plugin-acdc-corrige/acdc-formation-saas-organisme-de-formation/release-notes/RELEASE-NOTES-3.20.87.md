# ACDC Formation SAAS — version 3.20.87

## Objet

**Bibliothèque personnelle du formateur — côté portail.**

Le formateur connecté à `/extranet-formateur/` peut désormais consulter, déposer et supprimer ses propres documents. Le portail gagne aussi sa première vraie navigation : un système d'onglets « Tableau de bord » / « Ma bibliothèque » qui accueillera les futures sections (sessions, ressources pédagogiques, profil, etc.).

C'est le miroir front du 3.20.86 — même grille visuelle, mêmes badges d'expiration, mêmes contrôles de sécurité.

## Fonctionnalités livrées

### 1. Navigation par onglets dans le portail

Quand le formateur est connecté, il voit désormais :

```
┌─────────────────────────────────────────────────────────┐
│  🧭 Espace formateur          [Se déconnecter]          │
│  Bienvenue, David                                       │
├─────────────────────────────────────────────────────────┤
│  Tableau de bord  │ Ma bibliothèque                     │
└─────────────────────────────────────────────────────────┘
│                                                         │
│  ... contenu de l'onglet actif ...                      │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

L'onglet actif est marqué d'un soulignage or champagne (`#d6a353`) cohérent avec la palette ACDC. URL : `?view=dashboard` ou `?view=library`.

### 2. Vue « Ma bibliothèque »

Mêmes principes que côté admin (3.20.86), mais avec **3 différences importantes** :

| Élément | Côté admin (3.20.86) | Côté formateur (3.20.87) |
|---|---|---|
| **Notes administratives** | Visibles + éditables | **Cachées** — privées par construction |
| **Flag « Preuve Qualiopi »** | Cochable au moment de l'upload | **Lecture seule** — affiché en badge si l'admin l'a posé |
| **Suppression d'un document déposé par l'admin** | Autorisée | **Interdite** — le bouton « Supprimer » n'apparaît pas |

Le formateur peut donc **uniquement** gérer ses propres uploads. Les pièces déposées par l'admin (par exemple une convention que vous avez mise en ligne pour lui) sont visibles et téléchargeables, mais **non supprimables** depuis son côté. Il voit la mention « déposé par l'administrateur » sous le document.

### 3. Formulaire d'ajout — champs allégés

Côté formateur, le formulaire est plus simple :

| Champ | Statut |
|---|---|
| Catégorie | Obligatoire |
| Libellé | Optionnel |
| Fichier | Obligatoire (PDF, JPG, PNG, DOC, DOCX, PPTX, max 100 Mo) |
| Date d'émission | Optionnel |
| Date d'expiration | Optionnel (recommandé pour URSSAF/RC pro) |

Pas de checkbox « Preuve Qualiopi », pas de champ « Notes administratives ». C'est l'admin qui posera ces métadonnées plus tard si pertinent.

### 4. Sécurité — auth custom + isolation par formateur

Les 3 nouveaux handlers vérifient systématiquement, avant toute action :

1. **Authentification** via `trainer_portal_require_auth()` (cookie + session valide).
2. **Validation de la nonce** spécifique à l'action et au document concerné.
3. **Isolation stricte** : `account.trainer_id === document.trainer_id`. Toute tentative de manipulation d'un doc appartenant à un autre formateur est :
   - **Bloquée** (`Accès refusé`).
   - **Tracée** dans `wp_acdc_of_trainer_portal_logs` avec l'event_type `document_delete_forbidden` ou `document_download_forbidden`.

Cas d'usage couvert : un formateur malveillant qui tenterait de modifier l'ID dans l'URL pour télécharger ou supprimer le document d'un collègue se prendrait un mur, et la tentative serait journalisée pour audit.

### 5. Audit complet

Tous les événements bibliothèque sont tracés côté front :

| Event type | Quand |
|---|---|
| `document_uploaded` | Le formateur dépose un nouveau doc |
| `document_deleted` | Le formateur supprime un de ses docs |
| `document_downloaded` | Le formateur télécharge un doc (le sien ou un déposé par l'admin) |
| `document_delete_forbidden` | Tentative de suppression d'un doc d'un autre formateur |
| `document_download_forbidden` | Tentative de téléchargement d'un doc d'un autre formateur |

Avec IP, user-agent, document_id, catégorie, nom de fichier — tout ce qu'il faut pour la conformité Qualiopi (audit d'accès) et RGPD (registre des traitements).

## Architecture technique

### 3 nouveaux handlers dans le trait actions trainer-portal

- `handle_trainer_upload_own_document()` — réplique de `handle_admin_upload_trainer_document()` mais avec `uploaded_by = 'trainer'`, sans notes_admin, sans flag Qualiopi, et avec `require_auth()` à la place de `manage_options`.
- `handle_trainer_delete_own_document()` — supprime fichier physique + ligne BD, après vérification d'isolation.
- `handle_trainer_download_own_document()` — readfile sécurisé avec validation `realpath()` et isolation.

### Layout commun pour les vues authentifiées

Nouvelle fonction `render_trainer_portal_authenticated_layout( $account, $body_html, $active_tab )` qui produit la coque commune (topbar + nav + body) pour toutes les vues. Le body est calculé en amont selon la vue active. Architecture extensible — ajouter une nouvelle vue (« Mes sessions », « Mon profil »…) en 3.20.88+ sera trivial.

### Réutilisation maximale du code 3.20.86

Tous les helpers du kernel restent partagés :

- `get_acdc_trainer_document_categories()`
- `get_acdc_trainer_document_allowed_mimes()`
- `get_acdc_trainer_document_max_size_bytes()`
- `ensure_trainer_documents_upload_dir()`
- `get_trainer_document_absolute_path()` (anti-path-traversal)
- `get_trainer_documents_grouped()`
- `describe_trainer_document_expiry()`

Une seule définition, deux côtés (admin + portail). Toute évolution future bénéficie aux deux.

### Stockage physique

Identique au 3.20.86 — les fichiers du formateur et ceux de l'admin se rangent dans le **même dossier** `wp-content/uploads/acdc-of/trainer-documents/{trainer_id}/`. La différence se fait uniquement en BD via la colonne `uploaded_by` (`admin` ou `trainer`). C'est intentionnel : on veut une seule bibliothèque par formateur, pas une bibliothèque admin séparée d'une bibliothèque formateur.

## Cohérence visuelle

- Même grille à 2 colonnes responsive que côté admin.
- Mêmes badges (vert / jaune / rouge / Qualiopi).
- Mêmes classes CSS `.acdc-tdoc-*` — un seul système, deux contextes.
- La navigation utilise un soulignage or champagne (`#d6a353`) — cohérent avec la palette ACDC.

## Engagement de préservation

- **Aucune modification BD.** La table `wp_acdc_of_trainer_documents` du 3.20.82 est utilisée telle quelle.
- **Aucune dépendance externe ajoutée.**
- **Aucun impact sur les autres modules** (apprenant, gestion, catalogue, etc.).
- **Le dashboard reste accessible** via l'onglet « Tableau de bord ». Son contenu a été légèrement reformulé pour mentionner la nouvelle section disponible.

## Risques de régression — analyse

| Scénario | Avant 3.20.87 | Après 3.20.87 |
|---|---|---|
| Formateur connecté, page racine | Dashboard direct | Dashboard via onglet (par défaut) |
| URL `?view=dashboard` directe | N'existait pas | Affiche dashboard |
| URL `?view=library` directe | N'existait pas | Affiche bibliothèque |
| URL `?view=xxx` inconnue | N'existait pas | Fallback → dashboard |
| Documents existants déposés via admin (3.20.86) | Visibles côté admin uniquement | Visibles côté formateur aussi (avec mention « déposé par l'administrateur ») et téléchargeables, **mais non supprimables** |
| Tentative de suppression croisée entre formateurs | — | Bloquée + journalisée |

**Aucune régression identifiée.**

## Procédure de test

### Test 1 — Navigation entre onglets

1. Purger LiteSpeed (DB + objets + plugin), déployer le ZIP, purger encore, forcer un reload navigateur.
2. Se connecter à `/extranet-formateur/` avec votre compte test (créé via 3.20.83).
3. Vérifier la **topbar** en haut : logo + « Espace formateur » + « Bienvenue, David » + bouton « Se déconnecter ».
4. Vérifier la **nav d'onglets** : deux entrées « Tableau de bord » (active par défaut) et « Ma bibliothèque ».
5. Cliquer sur « Ma bibliothèque » → URL devient `/extranet-formateur/?view=library`, l'onglet devient actif (souligné or), le contenu change.
6. Cliquer sur « Tableau de bord » → retour au dashboard.

### Test 2 — Visibilité des docs déposés par l'admin

**Pré-requis** : avoir au moins 1 document déposé via la fiche admin (test 3.20.86) pour ce formateur.

1. Aller sur l'onglet « Ma bibliothèque ».
2. Vérifier que le document admin apparaît dans sa carte de catégorie.
3. Vérifier la mention « **déposé par l'administrateur** » sous le document.
4. Vérifier que **seul** le bouton « Télécharger » est présent — **pas** de bouton « Supprimer ».
5. Cliquer Télécharger → le fichier se télécharge avec son nom d'origine.

### Test 3 — Upload par le formateur

1. Sur l'onglet « Ma bibliothèque », remplir le formulaire d'ajout :
   - Catégorie : **Diplômes**
   - Libellé : « Diplôme BTS Communication 2018 »
   - Fichier : un PDF de test
   - Date d'émission : laisser vide
   - Date d'expiration : laisser vide
2. Cliquer « Ajouter le document ».
3. Vérifier le message de succès vert « Document ajouté à votre bibliothèque ».
4. Vérifier que la carte « Diplômes » affiche maintenant **(1)** avec le doc déposé.
5. Vérifier que **les deux boutons** « Télécharger » et « Supprimer » sont présents (puisque c'est le formateur qui l'a déposé).

### Test 4 — Vérification croisée côté admin

1. Aller sur la fiche admin du formateur en question.
2. Faire défiler jusqu'à « Bibliothèque personnelle ».
3. Vérifier que le document que le formateur vient de déposer **apparaît bien** côté admin.
4. Vérifier que le côté admin a accès à des fonctions supplémentaires : possibilité d'éditer (en supprimant/réuploadant), notes admin privées, flag Qualiopi.

### Test 5 — Badges d'expiration côté formateur

1. Côté formateur, déposer un doc URSSAF avec **date d'expiration dans 30 jours**.
2. Vérifier le badge **jaune** « Expire le ... (30 j) ».
3. Côté admin, modifier l'enregistrement (supprimer + ré-uploader) avec **date dans 90 jours** + flag Qualiopi coché.
4. Côté formateur, recharger la page → badges **bleu Qualiopi** + **vert Expire le …**. Cohérence parfaite.

### Test 6 — Suppression côté formateur

1. Côté formateur, sur un de ses propres documents, cliquer « Supprimer ».
2. Confirmer la popup JS.
3. Vérifier la disparition + le compteur de la catégorie qui décrémente.
4. Vérifier côté admin que le document a aussi disparu.

### Test 7 — Tentative d'accès croisé (sécurité)

**Si vous avez deux formateurs avec compte actif** : essayez de manipuler une URL pour accéder au document d'un autre formateur.

1. Connecté en tant que Formateur A.
2. Récupérer un `document_id` qui appartient à Formateur B (par exemple via le SQL ou en se connectant temporairement avec B).
3. Forger une URL : `/wp-admin/admin-post.php?action=acdc_trainer_download_own_document&document_id=XX&_wpnonce=YY`
4. Vérifier le retour : « Accès refusé : vous ne pouvez télécharger que vos propres documents ».
5. Vérifier en BD : `SELECT event_type, ip_address, event_data FROM wp_acdc_of_trainer_portal_logs WHERE event_type LIKE 'document_%_forbidden' ORDER BY id DESC LIMIT 5;`

### Test 8 — Validation des formats / taille (rappel)

Identique au 3.20.86. Vérifier qu'un `.zip` est refusé, qu'un `.pdf > 100 Mo` est refusé.

## Limites connues à valider

- **Pas d'édition après upload côté formateur**. Comme côté admin, pour modifier date ou label : suppression + ré-upload. Ce sera ajouté plus tard si nécessaire.
- **Pas d'historique d'upload** visible par le formateur. Il voit son inventaire, pas la chronologie des dépôts.
- **Pas de limite de stockage par formateur**. Si un formateur déposait 50 fichiers de 100 Mo, ça consommerait 5 Go. À surveiller en pratique. Une option « quota par formateur » pourra être ajoutée si besoin.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump 3.20.86 → 3.20.87.
- `includes/class-acdc-plugin.php` — branchement de 3 nouvelles actions admin-post (`acdc_trainer_upload_own_document`, `acdc_trainer_delete_own_document`, `acdc_trainer_download_own_document`).
- `includes/trainer-portal/actions/class-acdc-trainer-portal-actions-trait.php` — 3 nouveaux handlers.
- `includes/trainer-portal/render/class-acdc-trainer-portal-render-trait.php` — refonte complète du rendu authentifié :
  - Système d'onglets (`render_trainer_portal_authenticated_layout`).
  - Nouvelle vue « Ma bibliothèque » (`render_trainer_portal_library`).
  - Le dashboard est maintenant un body séparé (`render_trainer_portal_dashboard_body`).
  - L'ancienne fonction `render_trainer_portal_dashboard()` est conservée pour compatibilité (elle wrap le body avec le layout).

## Suite

Module Formateur — état d'avancement :

| Patch | Périmètre | Statut |
|---|---|---|
| 3.20.82 | Schéma BD documents/resources | ✅ |
| 3.20.83 | Auth custom + page connexion + e-mail invitation | ✅ |
| 3.20.84 | CSS portail chargé sur la page formateur | ✅ |
| 3.20.85 | Template blank épuré sur la page formateur | ✅ |
| 3.20.86 | Bibliothèque côté admin | ✅ |
| **3.20.87** | **Bibliothèque côté portail formateur + nav onglets** | ✅ |
| 3.20.88 | « Mon profil » — fiche personnelle modifiable par le formateur | À venir |
| 3.20.89 | « Mes sessions » — sessions à venir / passées avec liste apprenants | À venir |
| 3.20.90 | « Mes ressources pédagogiques » — supports partagés avec apprenants | À venir |
| 3.20.91 | UI permissions granulaires côté admin | À venir |
| 3.20.92+ | Alertes Qualiopi automatiques sur expirations | À venir |

À ce stade, le **socle Bibliothèque est entièrement fonctionnel**, vu des deux côtés. Validez le 3.20.87 et choisissez la prochaine direction.
