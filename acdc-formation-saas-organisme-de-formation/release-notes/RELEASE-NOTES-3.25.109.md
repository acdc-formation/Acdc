# RELEASE NOTES — 3.25.109 (conformité PHP & CI)

## Correctif de compatibilité
- Déclaration correcte de la version PHP minimale : le plugin utilise des **constantes de
  trait** (PHP 8.2+). L'en-tête déclare désormais `Requires PHP: 8.2` (+ `Requires at least: 6.2`)
  et `composer.json` passe à `"php": ">=8.2"`. Auparavant `>=7.4` était annoncé à tort → un
  environnement PHP < 8.2 aurait subi un fatal « Traits cannot have constants ».

## Outillage
- `composer.json` : correction du nom de paquet de dev `szepeweb/phpstan-wordpress`
  → `szepeviktor/phpstan-wordpress` (le précédent n'existe pas → `composer install` échouait).
- Matrices CI (racine + plugin) alignées sur PHP 8.2 / 8.3. `phpcs.xml.dist` testVersion 8.2-.
