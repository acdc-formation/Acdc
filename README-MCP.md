# MCP — Accès distant au site WordPress ACDC (transport HTTP)

Ce dépôt fournit une configuration MCP (`.mcp.json`, scope projet) pour piloter le
site **https://acdcformation.com**. La bibliothèque **WordPress/mcp-adapter est
intégrée DANS le plugin ACDC** (vendorisée, v0.5.0) : **aucun plugin séparé à
installer/activer**. Le site étant distant, l'accès passe par le proxy officiel
`@automattic/mcp-wordpress-remote` (STDIO/wp-cli impossible à distance).

Endpoint MCP exposé par le plugin (transport REST) :
**`https://acdcformation.com/wp-json/acdc-mcp/v1/mcp`**

Le serveur déclaré côté Claude est `wordpress-acdc`. Il lit ses identifiants
**uniquement** depuis l'environnement local (`${WP_API_USERNAME}` /
`${WP_API_PASSWORD}`) : **aucun secret n'est stocké dans le dépôt.**

Le serveur MCP n'expose **que la liste blanche explicite de nos 11 abilities**
(8 lecture + 3 écritures sûres) — pas de découverte automatique des abilities du
site (le serveur « par défaut » de la bibliothèque est désactivé).

---

## Marche à suivre

### 1. Déployer le plugin ACDC (la bibliothèque MCP est incluse)
- Installer/mettre à jour le plugin **ACDC ≥ 3.25.146** (`Extensions → Ajouter →
  Téléverser`). La bibliothèque `mcp-adapter` est **embarquée** — rien d'autre à
  installer.
- Prérequis site (déjà vérifiés) : WordPress 7.0.2, **API Abilities présente**
  (namespace `wp-abilities/v1`), mots de passe d'application actifs.
- Le plugin **Automattic « WordPress MCP » n'est PAS utilisé** (il n'utilise pas
  l'API Abilities) : le laisser **inactif**.

### 2. Créer un utilisateur WordPress dédié à rôle restreint
- Ne **pas** utiliser le compte administrateur principal.
- Créer un utilisateur spécifique (ex. `mcp-bot`) et lui attribuer **l'un des
  rôles dédiés MCP** fournis par le plugin (voir « Rôles & capacités » ci-dessous).
- Ce cloisonnement limite l'impact en cas de fuite du mot de passe d'application :
  l'agent ne dispose QUE des capacités MCP, pas de l'administration WordPress.

#### Rôles & capacités (fournis automatiquement par le plugin)
Le plugin déclare deux capacités dédiées — `acdc_mcp_read` (lecture) et
`acdc_mcp_write` (écriture) — et **aucune ability n'utilise `manage_options`**.
Trois rôles peuvent porter ces capacités :

| Rôle | Capacités | Peut faire | À choisir si… |
|------|-----------|------------|----------------|
| **`acdc_mcp_readonly`** | `read` + `acdc_mcp_read` | Lot 1 seulement (lister/consulter formations, prospects, leads, devis, indicateurs) | L'agent doit **seulement lire** — le choix le plus sûr, recommandé par défaut. |
| **`acdc_mcp_agent`** | `read` + `acdc_mcp_read` + `acdc_mcp_write` | Lot 1 **et** Lot 2 (créer/modifier un devis, créer un prospect ; jamais d'e-mail) | L'agent doit aussi **écrire** (devis/prospects), en préproduction. |
| **`administrator`** | reçoit `acdc_mcp_read` + `acdc_mcp_write` | Tout | Compatibilité : l'admin garde l'accès sans configuration. |

Recommandation : pour un usage MCP en préproduction, créez `mcp-bot` avec
**`acdc_mcp_readonly`** par défaut, et ne passez à **`acdc_mcp_agent`** que
lorsque vous voulez autoriser les écritures sûres du Lot 2.

Provisionnement : les rôles/capacités sont créés à l'activation du plugin **et**
au boot via un contrôle de version (ils apparaissent donc après simple mise à
jour, sans réactivation). Ils sont **supprimés proprement à la désactivation**
(rôles retirés + capacités révoquées sur `administrator`).

### 3. Générer un mot de passe d'application pour cet utilisateur
- `Utilisateurs → (l'utilisateur dédié) → Mots de passe d'application`.
- Saisir un nom (ex. `mcp-remote`), générer, **copier la valeur affichée une seule fois**.
- Ce mot de passe d'application (et non le mot de passe de connexion) sert d'identifiant API.

### 4. Exporter les identifiants dans l'environnement local (jamais dans le dépôt)
Dans le shell depuis lequel vous lancez `claude` :

```bash
export WP_API_USERNAME="mcp-bot"
export WP_API_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"   # le mot de passe d'application
```

> Ne jamais committer ces valeurs, ne pas les coller dans `.mcp.json`.
> Astuce : placez-les dans un fichier non versionné hors dépôt (ex. `~/.acdc-mcp.env`)
> chargé manuellement (`source ~/.acdc-mcp.env`).

### 5. Vérifier que le namespace `acdc-mcp/v1` est bien exposé
Avant tout test, contrôler que le plugin publie son serveur MCP :

```bash
curl -s https://acdcformation.com/wp-json/ | grep -o 'acdc-mcp/[^"]*' | sort -u
```

Le namespace `acdc-mcp/v1` doit apparaître (il est **absent** tant que le plugin
ACDC ≥ 3.25.146 n'est pas actif, ou si l'API Abilities n'est pas chargée).
L'endpoint MCP est : `https://acdcformation.com/wp-json/acdc-mcp/v1/mcp`.

### 6. Lancer Claude et approuver le serveur
- Lancer `claude` à la racine du dépôt.
- Approuver le serveur `wordpress-acdc` (scope projet → approbation requise à la
  première utilisation), ou via `/mcp`.
- Vérifier ensuite avec `claude mcp list` que le statut passe à *connected*.

---

## Journalisation
Les logs du proxy sont écrits dans `./.mcp-logs/mcp-adapter.log`
(dossier **ignoré par Git**, voir `.gitignore`).

## Rappel sécurité
- Aucun identifiant en clair dans le dépôt : seules les références `${WP_API_USERNAME}`
  et `${WP_API_PASSWORD}` figurent dans `.mcp.json`.
- Révoquer le mot de passe d'application depuis WordPress en cas de doute
  (`Utilisateurs → Mots de passe d'application → Révoquer`).
