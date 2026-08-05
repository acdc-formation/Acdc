# MCP — Accès distant au site WordPress ACDC (transport HTTP)

Ce dépôt fournit une configuration MCP (`.mcp.json`, scope projet) pour piloter le
site **https://acdcformation.com** via le plugin **WordPress `mcp-adapter`**, en
utilisant le proxy officiel `@automattic/mcp-wordpress-remote` (le site étant
distant, le transport STDIO/wp-cli n'est pas possible).

Le serveur déclaré est `wordpress-acdc`. Il lit ses identifiants **uniquement**
depuis l'environnement local (`${WP_API_USERNAME}` / `${WP_API_PASSWORD}`) :
**aucun secret n'est stocké dans le dépôt.**

---

## Marche à suivre

### 1. Installer et activer le plugin `mcp-adapter` sur le site
- Récupérer l'archive : `https://github.com/WordPress/mcp-adapter/releases/latest/download/mcp-adapter.zip`
- L'installer via `Extensions → Ajouter → Téléverser`, puis **l'activer**.
- Prérequis déjà vérifiés sur le site : WordPress 7.0.2, namespace `wp-abilities/v1`
  présent, mots de passe d'application actifs.

### 2. Créer un utilisateur WordPress dédié à rôle restreint
- Ne **pas** utiliser le compte administrateur principal.
- Créer un utilisateur spécifique (ex. `mcp-bot`) avec le rôle le plus restreint
  possible couvrant strictement les capacités nécessaires aux abilities exposées.
- Ce cloisonnement limite l'impact en cas de fuite du mot de passe d'application.

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

### 5. Vérifier que le namespace `mcp/` est bien exposé
Avant tout test, contrôler que l'adaptateur est actif et publie ses routes :

```bash
curl -s https://acdcformation.com/wp-json/ | grep -o 'mcp/[^"]*' | sort -u
```

Le namespace `mcp/` doit apparaître (il est **absent** tant que le plugin
`mcp-adapter` n'est pas activé). L'URL configurée pour le serveur est :
`https://acdcformation.com/wp-json/mcp/mcp-adapter-default-server`.

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
