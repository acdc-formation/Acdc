# ACDC Formation SAAS — version 3.20.83

## Objet

**Pivot architectural majeur du module Portail Formateur.**

Le 3.20.82 utilisait WordPress (rôle `acdc_trainer`, `wp_create_user`, lien d'activation via `wp-login.php`). C'était fonctionnel, mais incohérent avec l'extranet apprenant qui utilise une **auth 100 % custom**, indépendante de WordPress, avec ses propres tables, ses propres cookies et son propre template e-mail.

Cette 3.20.83 **abandonne entièrement la voie WordPress** et reconstruit le portail formateur sur le même modèle que l'extranet apprenant : visuel identique, e-mail dans le template ACDC officiel, auth custom maîtrisée de bout en bout.

## Modifications appliquées

### 1. Annulation propre de la partie WordPress du 3.20.82

À l'init du plugin, la fonction `cleanup_legacy_wp_trainer_role()` s'exécute une fois et nettoie automatiquement :

- **Suppression du rôle WordPress `acdc_trainer`** créé en 3.20.82.
- **Suppression des comptes WP mono-rôle** créés via l'ancienne `handle_invite_trainer()` (sécurité : on ne touche que les comptes ayant *uniquement* le rôle `acdc_trainer`, jamais les admins ou comptes multi-rôles).
- **Pour les comptes multi-rôles** : retrait silencieux du rôle `acdc_trainer`, le compte WP reste intact.
- **Dissociation** : `trainer.user_id` repasse à 0 sur toutes les fiches concernées, `invited_at` est remis à NULL.

Idempotent : si le rôle n'existe plus (déjà nettoyé ou jamais créé), la fonction est un no-op.

### 2. Quatre nouvelles tables (auth custom)

Symétriques aux quatre tables apprenant :

| Table | Rôle |
|---|---|
| `wp_acdc_of_trainer_portal_accounts` | Compte avec MDP haché, statut, compteur d'échecs, blocage temporaire |
| `wp_acdc_of_trainer_portal_tokens` | Tokens d'activation et de réinitialisation, hashés en BD |
| `wp_acdc_of_trainer_portal_sessions` | Sessions de connexion, indexées par hash du cookie |
| `wp_acdc_of_trainer_portal_logs` | Audit complet : logins réussis/échoués, blocages, demandes reset, activations |

Schéma identique à l'apprenant (mêmes types, mêmes index, même `wp_hash_password` / `sha256`).

### 3. Nouveau module `includes/trainer-portal/`

Architecture trait-based, calquée sur `includes/learner-portal/` :

```
includes/trainer-portal/
├── core/    class-acdc-trainer-portal-core-trait.php   (helpers : tokens, sessions, cookies, logs, e-mails)
├── actions/ class-acdc-trainer-portal-actions-trait.php (handlers : login, logout, request_reset, reset_password, activate)
└── render/  class-acdc-trainer-portal-render-trait.php  (shortcode + dashboard minimal)
```

Loader : `includes/class-acdc-trainer-portal.php` — branché à côté du loader apprenant dans le bootstrap.

### 4. Page `/extranet-formateur/` créée automatiquement

À l'install (ou à l'activation), la fonction `ensure_trainer_portal_page()` :

- Vérifie qu'une page `extranet-formateur` existe.
- Si non, crée la page avec le shortcode `[acdc_trainer_portal_login]`.
- Si oui, la réutilise (pas de doublon).
- Mémorise l'ID dans l'option `acdc_of_trainer_portal_page_id`.

Le shortcode affiche :

- **Si déconnecté** : la page de connexion (réplique pixel-perfect de l'extranet apprenant, juste « Espace formateur » à la place d'« Espace apprenant »).
- **Si connecté** : un dashboard minimal de bienvenue avec photo logo, nom du formateur, et bouton de déconnexion. Squelette qui sera enrichi en 3.20.84-86.

### 5. Cinq vues du shortcode de login

Toutes routées via le paramètre `?view=...` :

| Vue | URL exemple | Usage |
|---|---|---|
| `login` (par défaut) | `/extranet-formateur/` | Formulaire e-mail + mot de passe |
| `forgot` | `/extranet-formateur/?view=forgot` | Demande de réinitialisation |
| `reset` | `/extranet-formateur/?view=reset&token=...` | Choix d'un nouveau MDP après clic dans l'e-mail de reset |
| `activate` | `/extranet-formateur/?view=activate&token=...` | Première activation du compte invité |
| `dashboard` | `/extranet-formateur/` une fois connecté | Page d'accueil post-login |

### 6. Cinq handlers admin-post

Branchés en `nopriv` ET `priv` selon les besoins :

| Action | Handler |
|---|---|
| `acdc_trainer_login` | `handle_trainer_login()` |
| `acdc_trainer_logout` | `handle_trainer_logout()` |
| `acdc_trainer_request_reset` | `handle_trainer_request_reset()` |
| `acdc_trainer_reset_password` | `handle_trainer_reset_password()` |
| `acdc_trainer_activate` | `handle_trainer_activate()` |

### 7. `handle_invite_trainer()` entièrement refait

Plus de création de compte WordPress. Désormais :

1. Vérification capability admin + nonce.
2. Validation de l'e-mail de la fiche formateur.
3. Vérification : aucun **autre** formateur ne possède déjà ce compte (anti-collision).
4. Création (ou récupération) d'un compte dans `trainer_portal_accounts` avec :
   - `status = 'never_activated'`
   - `must_change_password = 1`
   - Hash placeholder (sera réécrit au moment de l'activation par le formateur).
5. Mise à jour de `trainer.invited_at`.
6. Génération d'un token d'activation (24 caractères, hashé en BD via SHA-256), valable **7 jours**.
7. **Envoi de l'e-mail via `acdc_send_transactional_email()`** — le système central qui produit le template ACDC complet (logo, en-tête bleu, bouton or, footer coordonnées). Identique à l'e-mail de recueil des besoins.
8. Trace dans les logs : événement `invitation_sent`.

### 8. E-mails dans le template ACDC officiel

Tous les e-mails du portail formateur (activation, reset) passent par `acdc_send_transactional_email()` avec :

- `greeting_name` = `Formateur`
- `source_module` = `trainer-portal`
- `email_audience` = `formateur`
- `footer_notice` = mention RGPD adaptée

Le rendu visuel est **strictement identique** à votre e-mail de recueil des besoins (capture d'écran fournie) :

- Logo ACDC en haut
- Titre « ACDC Formation » en bleu nuit
- Sous-titre « Azur Compétences Développement & Conseil »
- Corps de message
- Bouton or doré (`#D7A24B`) pour l'action principale
- Footer avec coordonnées et mention RGPD

### 9. Sécurité — symétrique à l'apprenant

| Mécanisme | Détail |
|---|---|
| Hashing MDP | `wp_hash_password()` / `wp_check_password()` |
| Tokens en BD | Stockés hashés en SHA-256 (jamais en clair) |
| Cookies de session | `httpOnly`, `samesite=Lax`, `secure` si HTTPS, durée 7 jours |
| Lockout | 5 échecs consécutifs = blocage 15 minutes |
| Anti-énumération | La page « Mot de passe oublié » répond identiquement que le compte existe ou non |
| Tokens consommés | Marqués `consumed_at` après usage, plus jamais réutilisables |
| Tokens révoqués | Création d'un nouveau token révoque automatiquement les précédents du même type |
| Audit | Tous les événements (login OK/KO, blocage, reset, activation) tracés dans `trainer_portal_logs` |

## Cohérence visuelle

- **Page de connexion** : copie pixel-perfect de l'extranet apprenant. Mêmes classes CSS (`acdc-portal-shell`, `acdc-login-card`, `acdc-form`, `acdc-button-primary acdc-button-block`, `acdc-login-secondary-link`). Aucune duplication de CSS.
- **E-mails** : passent par le template ACDC central, donc parfaite cohérence avec le reste de la communication.
- **Bandeau d'invitation** côté admin : utilise les variables CSS palette ACDC officielles.

## Engagement de préservation

- **Aucune perte de données.** Les fiches formateur conservent tout (les colonnes du 3.20.82 restent en place, simplement `user_id` n'est plus utilisé).
- **Module apprenant intact** — aucune modification.
- **Toutes les autres pages du back office** — comportement inchangé.
- **Aucune dépendance externe ajoutée.**
- **Le nettoyage des comptes WP du 3.20.82** est strictement borné aux comptes mono-rôle `acdc_trainer` (zéro risque de toucher un admin).

## Risques de régression — analyse

| Scénario | Avant 3.20.83 | Après 3.20.83 |
|---|---|---|
| Pas d'invitation testée en 3.20.82 | — | Aucun changement visible, tout est neuf |
| Invitations testées en 3.20.82 (comptes WP créés) | Comptes WP `acdc_trainer` actifs | Comptes WP supprimés automatiquement (mono-rôle), fiches dissociées (`user_id = 0`, `invited_at = NULL`). À ré-inviter via le nouveau bouton. |
| Si l'admin avait par erreur attribué d'autres rôles à ces comptes | — | Le rôle `acdc_trainer` est retiré, le compte WP reste avec ses autres rôles |
| Page `/extranet-formateur/` préexistante | — | Réutilisation de la page existante, pas de doublon |
| Migration BD | — | 4 nouvelles tables, opération idempotente |
| Connexion à `/wp-admin/` | — | Aucun impact (ancien rôle supprimé, plus aucune capability custom) |
| Module apprenant | OK | OK, totalement inchangé |

**Aucune régression identifiée.**

## Points de vigilance opérationnelle

### SMTP

L'envoi des e-mails passe par `acdc_send_transactional_email()` qui appelle `wp_mail()` en interne. **Le template ACDC est cohérent**, mais la délivrabilité reste tributaire de votre configuration SMTP. Sur **PlanetHoster N0C**, prévoir un plugin SMTP fiable (WP Mail SMTP avec Brevo / SendGrid / OVH Mailpro).

Si un envoi échoue, le compte est créé quand même et le message d'erreur le signale à l'admin. La ré-invitation regénère un nouveau token et tente un nouvel envoi (le bouton du bandeau devient « Renvoyer l'invitation »).

### Sessions et cookies

Le cookie `acdc_trainer_portal_session` est totalement **distinct** du cookie apprenant (`acdc_learner_portal_session`). Un même navigateur peut donc être connecté simultanément aux deux espaces sans interférence.

### RGPD

Toutes les actions sensibles sont tracées dans `trainer_portal_logs` (login, échecs, blocage, reset, activation, déconnexion) avec IP et user-agent. Cette traçabilité sera utile pour la conformité Qualiopi (audit d'accès) et RGPD (registre des traitements).

## Procédure de test

### Test 1 — Migration BD et nettoyage du legacy

1. Purger LiteSpeed (DB + objets + plugin).
2. Téléverser le ZIP, remplacer.
3. Purger à nouveau.
4. Aller dans **Utilisateurs → Tous les utilisateurs** : vérifier que le rôle « Formateur ACDC » a **disparu** du filtre par rôle (s'il y était en 3.20.82, il a été nettoyé).
5. (Optionnel SQL) :
   - `SHOW TABLES LIKE 'wp_acdc_of_trainer_portal_%';` → 4 lignes.
   - `SELECT * FROM wp_acdc_of_trainer_portal_accounts;` → 0 ligne (aucun compte avant invitation).

### Test 2 — Page d'accueil portail formateur

1. Visiter `/extranet-formateur/` en navigation privée (pour ne pas être connecté).
2. Vérifier le visuel : copie pixel-perfect de l'apprenant, titre « Espace formateur ».
3. Cliquer sur « Mot de passe oublié » → la vue change, formulaire de demande de réinitialisation s'affiche.
4. Saisir un e-mail bidon → message « Si un compte existe pour cet e-mail, un lien… » (anti-énumération OK).

### Test 3 — Invitation effective

**À tester avec votre propre adresse e-mail comme formateur fictif.**

1. Aller sur **Formateurs → Modifier** un formateur (avec votre adresse e-mail).
2. Vérifier le bandeau or champagne en haut : « Aucun compte n'est encore associé à ce formateur ».
3. Cliquer **« Inviter le formateur »**.
4. Vérifier le message de succès : « Invitation envoyée à xxx@yyy.com ».
5. Recharger la fiche : le bandeau dit maintenant « Compte actif. Invitation envoyée le … » + bouton « Renvoyer l'invitation ».

### Test 4 — Réception de l'e-mail (template ACDC)

1. Vérifier la réception de l'e-mail (boîte principale + spams).
2. Visuel attendu :
   - Logo ACDC en haut
   - « ACDC Formation » en bleu
   - « AZUR COMPÉTENCES DÉVELOPPEMENT & CONSEIL » sous-titre
   - « Bonjour [Prénom] [Nom] »
   - Identifiant de connexion + bouton or « Activer mon accès formateur »
   - Footer : 06 78 26 91 10 · contact@acdc-formation.com · 7 avenue Paul Cézanne — 83310 Cogolin

### Test 5 — Activation et première connexion

1. Cliquer sur le bouton « Activer mon accès formateur ».
2. Vérifier l'arrivée sur `/extranet-formateur/?view=activate&token=...`.
3. Définir un mot de passe (au moins 8 caractères, deux saisies identiques).
4. Cliquer « Activer mon accès » → connexion automatique.
5. **Vérifier que vous arrivez sur le dashboard formateur** : « Bienvenue [Prénom] » + message « Votre espace formateur est en cours de construction… » + bouton « Se déconnecter » en haut à droite.

### Test 6 — Déconnexion / reconnexion

1. Cliquer « Se déconnecter ».
2. Retour sur la page de connexion avec « Vous êtes déconnecté ».
3. Saisir e-mail + mot de passe → reconnexion.
4. Tester un mauvais mot de passe **5 fois de suite** → message « Trop de tentatives infructueuses. Réessayez dans quelques minutes. »
5. (Patience 15 min ou intervention SQL `UPDATE wp_acdc_of_trainer_portal_accounts SET blocked_until = NULL WHERE id = X` pour reprendre.)

### Test 7 — Mot de passe oublié

1. Sur `/extranet-formateur/?view=forgot`, saisir votre e-mail.
2. Vérifier l'arrivée d'un nouvel e-mail au template ACDC avec « Réinitialiser mon mot de passe ».
3. Cliquer le bouton, définir un nouveau MDP, vérifier la connexion.

### Test 8 — Audit logs

(Optionnel SQL)

```sql
SELECT created_at, event_type, account_id, ip_address FROM wp_acdc_of_trainer_portal_logs ORDER BY id DESC LIMIT 20;
```

Vous devriez voir : `invitation_sent`, `account_activated`, `login_success`, `login_failed`, `logout_manual`, etc.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump 3.20.82 → 3.20.83 + ajout du require de `class-acdc-trainer-portal.php`.
- `includes/class-acdc-plugin.php` :
  - 4 nouvelles propriétés de tables `trainer_portal_*`.
  - 3 traits `ACDC_Trainer_Portal_*` ajoutés.
  - 9 actions admin-post ajoutées.
  - Shortcode `acdc_trainer_portal_login` enregistré.
- `includes/kernel/class-acdc-kernel-core-trait.php` :
  - Remplacement de `ensure_trainer_role()` par `cleanup_legacy_wp_trainer_role()`.
  - 4 nouvelles tables BD (`trainer_portal_accounts`, `_tokens`, `_sessions`, `_logs`).
  - Appel de `ensure_trainer_portal_page()` dans `ensure_default_pages()`.
- `includes/kernel/class-acdc-kernel-actions-trait.php` :
  - Refonte complète de `handle_invite_trainer()` (auth custom à la place de WP).
- `includes/kernel/class-acdc-kernel-render-trait.php` :
  - Bandeau d'invitation : indicateur basé sur `invited_at` au lieu de `user_id`.

## Fichiers ajoutés

- `includes/class-acdc-trainer-portal.php` — loader des 3 traits.
- `includes/trainer-portal/core/class-acdc-trainer-portal-core-trait.php` — helpers + e-mails.
- `includes/trainer-portal/actions/class-acdc-trainer-portal-actions-trait.php` — handlers POST.
- `includes/trainer-portal/render/class-acdc-trainer-portal-render-trait.php` — shortcode + dashboard.

## Si quelque chose ne va pas

Le retour à 3.20.81 (avant le module formateur) est sûr. Les 4 tables et les colonnes ajoutées au formateur restent en BD (inertes), aucune perte. Pour un nettoyage manuel total :

```sql
DROP TABLE IF EXISTS wp_acdc_of_trainer_portal_accounts;
DROP TABLE IF EXISTS wp_acdc_of_trainer_portal_tokens;
DROP TABLE IF EXISTS wp_acdc_of_trainer_portal_sessions;
DROP TABLE IF EXISTS wp_acdc_of_trainer_portal_logs;
DROP TABLE IF EXISTS wp_acdc_of_trainer_documents;
DROP TABLE IF EXISTS wp_acdc_of_trainer_resources;
```

(À ne faire qu'en dernier recours et après sauvegarde.)

## Suite logique — feuille de route module

| Patch | Périmètre | Statut |
|---|---|---|
| 3.20.82 | Schéma BD documents/resources | ✅ Livré (partie WP nettoyée par 3.20.83) |
| **3.20.83** | **Auth custom + page connexion + e-mail invitation refait + dashboard vide** | ✅ Livré |
| 3.20.84 | Bibliothèque personnelle (CV, diplômes, attestations) — vue formateur ET admin | À venir |
| 3.20.85 | Ressources pédagogiques + intégration extranet apprenant | À venir |
| 3.20.86 | UI permissions granulaires (interrupteurs simple/autonome/custom) | À venir |

Vous testez 3.20.83, vous validez ou vous remontez les ajustements visuels, puis on enchaîne sur 3.20.84 (la première vraie zone de contenu : la bibliothèque personnelle du formateur).
