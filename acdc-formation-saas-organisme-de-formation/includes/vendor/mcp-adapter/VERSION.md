# Bibliothèque vendorisée — WordPress/mcp-adapter

Code source intégré au plugin ACDC pour exposer nos abilities en MCP **sans
installer de plugin séparé**. Chargé via `autoload.php` (PSR-4 maison), sans
dépendance à Composer côté site.

## Versions épinglées

| Paquet | Version | Namespace PSR-4 | Source vendorisée |
|--------|---------|-----------------|-------------------|
| `wordpress/mcp-adapter` | **v0.5.0** | `WP\MCP\` | `mcp-adapter/includes/` |
| `wordpress/php-mcp-schema` | **v0.1.2** | `WP\McpSchema\` | `php-mcp-schema/src/` |

`php-mcp-schema` est la dépendance runtime de `mcp-adapter` (`require`), intégrée
elle aussi. Seul le code source runtime est vendorisé (les dossiers `tests/`,
`docs/`, `.github/`, `.git/` ont été retirés).

## Mise à jour

1. `composer require wordpress/mcp-adapter:<version>` dans un projet jetable.
2. Recopier `vendor/wordpress/mcp-adapter/includes` et
   `vendor/wordpress/php-mcp-schema/src` ici (idem `composer.json` + `LICENSE`).
3. Mettre à jour ce tableau et la constante `ACDC_MCP_ADAPTER_VERSION`
   dans `autoload.php`.

## Licence

`wordpress/mcp-adapter` et `wordpress/php-mcp-schema` sont distribués sous
licence GPL-2.0-or-later (voir les fichiers `LICENSE` de chaque sous-dossier),
compatible avec la licence du plugin.
