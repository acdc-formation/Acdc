# MCP — Accès distant au site WordPress ACDC (transport HTTP)

Ce dépôt fournit une configuration MCP (`.mcp.json`, scope projet) pour piloter le
site **https://acdcformation.com**. La bibliothèque **WordPress/mcp-adapter est
intégrée DANS le plugin ACDC** (vendorisée, v0.5.0) : **aucun plugin séparé à
installer/activer**.

**Connexion HTTP directe (native), sans proxy.** Le plugin expose un vrai endpoint
MCP streamable-HTTP ; Claude Code s'y connecte en direct via un serveur `.mcp.json`
de `type: http`. Le proxy `@automattic/mcp-wordpress-remote` **n'est plus utilisé**
(il visait les endpoints du plugin Automattic, pas notre namespace REST custom).

Endpoint MCP exposé par le plugin :
**`https://acdcformation.com/wp-json/acdc-mcp/v1/mcp`**

Authentification par **mot de passe d'application WordPress** (HTTP Basic). La
valeur Basic est lue **uniquement** depuis la variable d'environnement
`${ACDC_MCP_BASIC}`, développée par Claude Code **à l'intérieur du header**
`Authorization` — **aucun secret n'est stocké dans le dépôt** :

```json
{
  "mcpServers": {
    "wordpress-acdc": {
      "type": "http",
      "url": "https://acdcformation.com/wp-json/acdc-mcp/v1/mcp",
      "headers": { "Authorization": "Basic ${ACDC_MCP_BASIC}" }
    }
  }
}
```

> La substitution `${VAR}` dans les `headers` est **officiellement supportée** par
> Claude Code (doc MCP : « Environment variables can be expanded in … `headers`:
> for HTTP server authentication »).

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

### 4. Stocker le mot de passe dans le Keychain macOS (jamais en clair dans un fichier)
Le secret vit **uniquement** dans le trousseau macOS. La variable `ACDC_MCP_BASIC`
(= Base64 de `identifiant:motdepasse`) est reconstruite au vol depuis le Keychain,
sans jamais écrire le mot de passe dans un fichier.

```bash
# a) Enregistrer le mot de passe d'application dans le Keychain (invite masquée : collez-le puis Entrée)
security add-generic-password -a mcp-bot -s acdc-mcp -U -w

# b) Exporter la valeur Basic pour la session courante (lue depuis le Keychain, espaces retirés)
export ACDC_MCP_BASIC="$(printf '%s:%s' mcp-bot "$(security find-generic-password -a mcp-bot -s acdc-mcp -w | tr -d ' ')" | base64)"
```

Pour la rendre permanente (aucune valeur secrète écrite — seulement une lecture du
Keychain), ajoutez la ligne `b)` à `~/.zshrc`.

### 5. Vérifier la connexion
```bash
# Le namespace doit apparaître (déjà OK sur le site) :
curl -s https://acdcformation.com/wp-json/ | grep -o 'acdc-mcp/[^"]*' | sort -u

# Depuis la racine du dépôt : approuver puis lister
claude          # approuver « wordpress-acdc » à la 1re utilisation (ou via /mcp)
claude mcp list # attendu : wordpress-acdc … ✔ Connected
```

`claude mcp list` doit afficher `wordpress-acdc: https://acdcformation.com/wp-json/acdc-mcp/v1/mcp (HTTP) - ✔ Connected`.
Le compte `mcp-bot` (rôle *ACDC MCP Lecture seule*) donne accès aux 8 abilities de
lecture ; les 3 écritures nécessitent le rôle `acdc_mcp_agent`.

### 6. Révoquer proprement (en cas de fuite)
```bash
# 1) WordPress : Utilisateurs → mcp-bot → Mots de passe d'application → Révoquer « claude »
# 2) Supprimer le secret du Keychain :
security delete-generic-password -a mcp-bot -s acdc-mcp
# 3) Purger la variable de la session (et retirer la ligne de ~/.zshrc si ajoutée) :
unset ACDC_MCP_BASIC
```

---

## Rappel sécurité
- **Aucun secret dans le dépôt** : `.mcp.json` ne contient que la référence
  `${ACDC_MCP_BASIC}`. Le mot de passe d'application ne vit que dans le Keychain.
- La révocation WordPress (étape 6.1) invalide immédiatement l'accès, même si la
  variable d'environnement subsiste.
- HTTPS obligatoire (Basic auth) — l'endpoint refuse déjà les requêtes non
  authentifiées (`401 rest_forbidden`).
