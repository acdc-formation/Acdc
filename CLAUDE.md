# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Ce qu'est ce dépôt

Un ERP Qualiopi pour organisme de formation, livré sous forme d'extension WordPress.
Le dépôt contient **quatre livrables distincts**, pas un seul :

| Chemin | Nature |
|---|---|
| `acdc-formation-saas-organisme-de-formation/` | l'extension principale (427 fichiers PHP) |
| `acdc-indicateurs/` | extension satellite, affiche les indicateurs publics via REST |
| `mu-plugins/acdc-preprod-mail-guard.php` | garde-fou e-mail de préproduction, **inerte tant que `ACDC_PREPROD` n'est pas défini** |
| `tests/`, `tools/` | balayages et bancs d'essai, hors WordPress |

La racine porte aussi les rapports d'audit (`AUDIT-*.md`) et les briefs d'agents de recette
(`PROMPT-AGENT-*.md`) : ce sont des documents de travail, pas du code.

## Commandes

Il n'y a **ni `composer.json`, ni `phpunit.xml`, ni CI GitHub** dans ce dépôt — les instructions
`composer install` / `composer test` du `README.md` de l'extension sont périmées. Tout se lance
avec le seul binaire `php`, sans WordPress, en quelques secondes.

```bash
# Toute la batterie. scan-global-wpdb.php est le seul à exiger un argument :
# il ressort ici en FAIL, c'est attendu, il se lance à part (voir plus bas).
for f in tests/*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL: $f"; done

# Un seul contrôle
php tests/scan-cachet-transparent.php
php tests/test-prix.php

# Le seul script qui EXIGE un argument (sinon erreur fatale, pas un échec de règle)
php tests/scan-global-wpdb.php acdc-formation-saas-organisme-de-formation

# Le seul contrôle Node
node tests/test-signature-trace.js

# Vérification de syntaxe sur toute l'extension (427 fichiers, 0 erreur attendue)
find acdc-formation-saas-organisme-de-formation -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"

# Bancs d'essai MCP (hors WordPress, bouchons du core)
php tools/mcp-registration-smoke.php
php tools/verify-mcp-abilities.php
```

`tests/README.md` liste chaque contrôle avec, en une phrase, ce qu'il défend. Il n'est pas
toujours à jour des tout derniers ajouts — se fier au contenu de `tests/`.

Les balayages ont besoin de `gd` et `zlib` (certains fabriquent un vrai PNG et lisent les octets
du PDF produit) ; sans eux ils sortent en erreur explicite plutôt que de passer en silence.

## Convention de test : la règle se prouve par sabotage

Les scripts de `tests/` ne sont pas des tests unitaires. Ce sont **des balayages qui lisent le
code source de l'extension** et refusent un motif, ou **des bancs qui appellent les fonctions
réelles** avec les fonctions WordPress bouchonnées. Deux exigences non négociables :

- **Une règle ne compte que si son sabotage la fait tomber.** Avant de livrer un balayage, muter
  la source qu'il surveille, vérifier qu'il échoue, restaurer. Un balayage qui passe sur du code
  cassé ne défend rien. Les en-têtes de fichier documentent les sabotages joués.
- **Ne jamais borner un corps de fonction au nombre de caractères** : borner à la déclaration
  `function` suivante. Sinon la fonction voisine satisfait la règle à la place de la bonne.
- Dépouiller les commentaires (`token_get_all()`) avant de chercher un motif : sinon le balayage
  reconnaît le commentaire qui explique le correctif, pas le correctif.
- Vérifier qu'une fonction est **appelée**, pas seulement qu'elle **existe** — une définition
  orpheline a déjà passé trois contrôles.

Sortie attendue : `OK — <nom> : N vérifications` et code 0, ou la liste des échecs sur `STDERR`
et code 1.

## Architecture

### Composition par traits

`includes/class-acdc-plugin.php` est une classe unique — `ACDC_Formation_SAAS_Plugin`, singleton —
dans laquelle sont composés **77 traits**. Chaque module vit dans
`includes/<module>/` et suit le découpage :

- `class-acdc-<module>-core-trait.php` — données, requêtes, calculs
- `class-acdc-<module>-actions-trait.php` — handlers de formulaire, `admin_post_*`, AJAX
- `class-acdc-<module>-render-trait.php` — rendu HTML

Conséquence pratique : **tout est `$this`**. Une méthode du module Quiz appelle directement une
méthode du kernel, sans injection. Les collisions se règlent par `insteadof` (voir la fin de la
liste `use`, où `ACDC_Agent_Audit_Render_Trait` et `ACDC_Kernel_Render_Trait` s'arbitrent).

Quelques modules sont déjà en **classes dédiées** (`class-acdc-signature.php`,
`class-acdc-emargement.php`, `includes/audit/`, `includes/mcp/`) — c'est la cible d'architecture,
pas encore la règle.

`src/` (namespace `ACDC\`, autoload PSR-4 maison, aucune dépendance à Composer en production)
contient la **logique pure et testable** extraite au fil des correctifs : `Money`, `HalfDaySplit`,
`FundingSplit`, `Indicators`, `AdresseExpedition`, `FiscalYear`… Quand un calcul se met à diverger
entre deux écrans, la réponse est de l'extraire ici.

### Points de passage obligés

Plusieurs correctifs coûteux ont abouti à une « porte unique ». Les contourner casse un balayage :

- **E-mails** → `acdc_send_transactional_email()` (kernel core trait). Elle applique le gabarit de
  marque, le mode recette (`acdc_wf_may_send_to`), l'archivage, et l'en-tête `From:`. Un
  `wp_mail()` direct échappe aux quatre. Un HTML déjà composé passe par `template_args['raw_html']`.
- **Adresse d'expédition** → `src/AdresseExpedition.php`. L'adresse de *contact* (préférence, dans
  la fiche organisme) et l'adresse d'*expédition* (infrastructure, alignée DMARC sur le domaine
  signé) sont deux choses différentes. Les confondre a mis tous les e-mails en indésirables.
- **PDF** → `_build_simple_pdf_string()` (kernel render trait), écrivain maison. Images en
  `/DCTDecode` uniquement ; la transparence voyage **à côté**, dans un `/SMask` DeviceGray déposé
  dans le registre `$acdc_pdf_masques` par `prepare_pdf_jpeg_image()`. Attention : l'alpha GD est
  inversé et sur 7 bits (0 = opaque), le PDF sur 8 bits (255 = opaque).
- **Migrations de schéma** → `install_or_update()` derrière `maybe_upgrade()`, protégé par un
  verrou en base et un compteur de reprises. Le travail lourd ne s'exécute **jamais** sur une page
  publique ni sur `admin-ajax.php`. Les raccourcis se posent **avant** la migration sur `init` —
  l'inverse a déjà affiché `[acdc_of_portal tab="dashboard"]` en toutes lettres en production.
- **Fichiers** → `wp-content/uploads/acdc-documents/`, `acdc-signatures/`, `acdc-emargement/`,
  `acdc-invoices/`, protégés par `.htaccess` et servis par un handler, jamais par lien direct
  (`scan-liens-proteges.php`).

### Écrans

Deux surfaces : l'administration WordPress (`?page=acdc-of…&tab=…`) et le front, monté par une
douzaine de shortcodes (`acdc_of_portal`, `acdc_of_learner_portal`, `acdc_trainer_portal_login`,
`acdc_qz_live_host`…). Les portails apprenant et formateur ont leur **authentification propre**
(tables `*_portal_account/token/session/log`), distincte des comptes WordPress.

## La famille de bugs qui revient

Le journal des versions raconte trente fois la même histoire : **un écran qui interroge une donnée
que personne n'écrit**, ou **du code qui cherche la donnée là où l'écran en écrit une autre**.
`enterprise_contact_email` contre `email` ; `registration_id` contre `learner_id` ;
`source_prospect_id` que seuls le devis et la convention portent tous les deux ; un *libellé* de
statut lu au lieu des pièces réellement présentes.

Avant de conclure qu'un écran est vide « parce que la donnée n'existe pas », vérifier **qui écrit
la colonne lue**. C'est presque toujours là. Les corollaires :

- Un état terminal posé sur un travail inachevé fige le dossier pour toujours : une correction qui
  ne prévoit pas de **passe de reprise** ne vaut que pour l'avenir et laisse les dossiers bloqués.
- Un repli **muet** rend une valeur fausse indiscernable d'une valeur juste.
- Un graphique peint en dur affirme quelque chose de faux à côté de chiffres justes.

## Versions et livraison

Le numéro vit à **deux endroits, dans le seul fichier d'amorçage** : l'en-tête `Version:` et
`define( 'ACDC_OF_SAAS_VERSION', … )`. Il faut l'incrémenter à chaque lot livré — c'est lui qui
déclenche `maybe_upgrade()` sur le site.

Un lot se termine par : entrée en tête de `CHANGELOG.md` (format Keep a Changelog, rédigé en
français, expliquant **la cause** et non la liste des symptômes), et un message de commit de la
forme `3.25.NNN — <ce que le lot rend vrai>`.

Branche de travail : `claude/plugin-modifications-workflows-qqthme`, base
`claude/setup-brand-analysis-UlGbJ`, PR #3 (brouillon). Le conteneur distant repart parfois d'un
clone où cette branche pointe sur la base : **vérifier `git log -1` et faire un
`git fetch origin <branche> && git merge --ff-only` avant de toucher au code**, sinon le travail
part d'un arbre vide.

## MCP

`.mcp.json` (scope projet) déclare le serveur `wordpress-acdc`, branché en HTTP direct sur
`https://acdcformation.com/wp-json/acdc-mcp/v1/mcp`. L'adaptateur `WordPress/mcp-adapter` est
**vendorisé dans l'extension** (`includes/vendor/mcp-adapter/`) : aucune extension tierce à
installer. L'authentification est un mot de passe d'application lu depuis `${ACDC_MCP_BASIC}` —
**aucun secret dans le dépôt**. Voir `README-MCP.md`.

## Recette sur le site réel

Les briefs `PROMPT-AGENT-*.md` fixent des limites que tout agent de recette doit tenir :
ne jamais s'authentifier à la place de quelqu'un, ne jamais résoudre un CAPTCHA, ne jamais choisir
un mot de passe pour un tiers, ne jamais ouvrir un fichier de signature ou de pièce d'identité
réel, aucune suppression en production. La couche de sécurité du site bloque les clients HTTP hors
navigateur : une mesure prise en dehors d'un navigateur ne vaut rien.
