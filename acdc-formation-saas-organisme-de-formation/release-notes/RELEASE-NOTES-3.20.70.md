# ACDC Formation SAAS — version 3.20.70

## Objet

Correction du bug de duplication des formations : la duplication créait une formation indépendante avec un nouvel ID auto-incrémenté (par exemple ID 10) au lieu d'une variante portant un identifiant dérivé (1.1, 1.2, 1.3…).

## Diagnostic

La logique de duplication dans `handle_duplicate_formation()` est correcte depuis sa mise en place : elle calcule bien `code = base_id + . + variant_number` et l'envoie à `wpdb->insert()`. Le rendu de la liste affiche aussi correctement `code` si rempli, sinon `id`.

La vraie cause se trouve en base de données. Sur les installations existantes, la table `wp_acdc_of_formations` ne contenait pas les trois colonnes nécessaires :

- `code` (VARCHAR 100)
- `base_formation_id` (BIGINT UNSIGNED)
- `variant_number` (INT UNSIGNED)

Ces colonnes sont pourtant déclarées dans le `CREATE TABLE` du schéma cible, mais `dbDelta()` ne les avait pas ajoutées sur les bases existantes. C'est une limitation connue de `dbDelta` sur les ALTER de tables existantes : il échoue parfois silencieusement à propager les nouvelles colonnes selon l'ordre de déclaration ou les variations d'espaces.

Conséquence : `wpdb->insert()` envoyait bien le tableau avec `code='1.1'`, mais MySQL en mode non strict ignorait silencieusement les clés correspondant à des colonnes inexistantes. L'insertion réussissait pour les colonnes connues (titre, modalité, durée, tarif, etc.), l'auto-incrément donnait par exemple 10, et le code variante n'était jamais persisté.

Le rendu retombait alors sur `$entry->id` faute de `$entry->code`, et l'utilisateur voyait `10` au lieu de `1.1`.

## Correction

Ajout de trois appels `maybe_add_table_column()` immédiatement après `dbDelta( $sql_formations )` dans la routine d'installation/mise à jour, sur le même modèle que ce qui existe déjà pour la table `wp_acdc_of_companies` (onze colonnes complétées par cette même méthode).

Ces appels sont **idempotents** : si les colonnes existent déjà, ils ne font rien. Aucun risque sur les bases déjà à jour.

```php
$this->maybe_add_table_column( $this->formation_table, 'code', "VARCHAR(100) DEFAULT ''" );
$this->maybe_add_table_column( $this->formation_table, 'base_formation_id', "BIGINT UNSIGNED NOT NULL DEFAULT 0" );
$this->maybe_add_table_column( $this->formation_table, 'variant_number', "INT UNSIGNED NOT NULL DEFAULT 0" );
```

Le bump de version `3.20.69` → `3.20.70` redéclenche `maybe_upgrade()` au prochain `init`, ce qui exécute la routine d'ajout des colonnes manquantes.

## Engagement de préservation

- **Aucun PHP métier modifié.** La logique de duplication elle-même n'est pas touchée.
- **Aucun CSS modifié.**
- **Aucun JavaScript modifié.**
- **Aucun rendu modifié.** L'affichage du tableau Formations reste strictement identique.

Les seules modifications effectuées :

1. `acdc-formation-saas-organisme-de-formation.php` : version `3.20.69` → `3.20.70` (en-tête + constante `ACDC_OF_SAAS_VERSION`).
2. `includes/kernel/class-acdc-kernel-core-trait.php` : trois lignes ajoutées après `dbDelta( $sql_formations );`.

Toutes les corrections 3.20.57 → 3.20.69 sont conservées (identifiants de tableaux v3, fallback v2/legacy, sauvegardes de largeurs de colonnes, etc.).

## Procédure de test

1. Purger le cache LiteSpeed.
2. Installer 3.20.70 (la version remplace 3.20.69 sans toucher aux données).
3. Naviguer vers l'extranet — `init` s'exécute, `maybe_upgrade()` détecte le changement de version, les colonnes manquantes sont ajoutées par `ALTER TABLE`.
4. Optionnel : vérifier en base via phpMyAdmin que `SHOW COLUMNS FROM wp_acdc_of_formations LIKE 'code';` retourne désormais une ligne (idem pour `base_formation_id` et `variant_number`).
5. Aller sur la page Formations.
6. Cliquer sur le menu trois points d'une formation existante (ex. ID 1) → **Dupliquer**.
7. Vérifier que la nouvelle ligne dans le tableau affiche `1.1` dans la colonne ID, et non un nouvel ID auto-incrémenté.
8. Redupliquer la même formation → la nouvelle variante doit afficher `1.2`.
9. Si une formation `1.1`, `1.2`, `1.3` existe déjà, la prochaine duplication doit produire `1.4` automatiquement.

## Sort de la formation créée par erreur avant le patch

Si une formation avec un nouvel ID auto-incrémenté (par exemple 10) a été créée par une duplication antérieure au patch, elle existe en base comme une copie complète mais non rattachée. Trois options après installation :

- **Recommandée** — Archiver puis recréer : archiver la formation parasite via le menu trois points, puis redupliquer la formation mère → la nouvelle variante portera le code `1.1` correctement.
- **Conserver telle quelle** — la formation reste affichée avec son ID brut (10), traitée comme une formation indépendante.
- **Reparamétrer en base** — si à l'aise avec phpMyAdmin :
  ```sql
  UPDATE wp_acdc_of_formations SET code='1.1', base_formation_id=1, variant_number=1 WHERE id=10;
  ```
  La formation devient alors une variante rattachée à la formation 1, sans rien recréer.

## Risques de régression

Nuls.

- `maybe_add_table_column()` est idempotent — sur une base où les colonnes existent déjà, l'opération est un no-op.
- La logique de duplication n'est pas modifiée — elle fonctionnera dès que les colonnes seront présentes.
- Les formations existantes (mères, sans `code` rempli) garderont l'affichage de leur ID brut, comportement attendu.
- Aucune table autre que `wp_acdc_of_formations` n'est concernée.

## Note sur l'index `KEY code`

L'index `KEY code (code)` est déclaré dans le `CREATE TABLE` mais ne sera pas créé rétroactivement par `ALTER TABLE ADD COLUMN`. Sur les installations existantes, la colonne `code` existera sans index dédié. Ce n'est pas critique : la table formations contient peu de lignes en pratique et les recherches `LIKE 'X.%'` restent rapides. L'index sera créé sur les nouvelles installations à partir de 3.20.70 directement par `dbDelta()` au moment du `CREATE TABLE`.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump de version.
- `includes/kernel/class-acdc-kernel-core-trait.php` — trois lignes ajoutées après `dbDelta( $sql_formations )`.

**Aucun autre fichier modifié.**

## Si quelque chose ne va pas

Le retour à 3.20.69 est immédiat et sans risque : les colonnes ajoutées en 3.20.70 ne créent aucune dépendance dure. Si on désinstalle 3.20.70 et qu'on réinstalle 3.20.69, les colonnes restent présentes en base (utiles ou inertes selon le code installé), aucune perte de données.

## Suite logique

Si la duplication fonctionne comme prévu sur la formation ID 1 :

- Tester sur une formation déjà variante (ex. `1.1`) → la duplication doit calculer le `base_formation_id` correctement et produire `1.2`, `1.3`, etc., en partant de la mère.
- Vérifier que les formations marquées comme variantes (`base_formation_id != 0`) restent éditables comme une formation normale.
- Décider de la suite ergonomique éventuelle : affichage visuel du lien parent-enfant dans la liste, regroupement, etc. — uniquement si demandé.
