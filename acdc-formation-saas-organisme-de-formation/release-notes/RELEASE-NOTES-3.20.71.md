# ACDC Formation SAAS — version 3.20.71

## Objet

Correction définitive du bug de duplication des formations : la valeur du code variante (`X.Y`) issue de la duplication était écrasée par chaîne vide au premier enregistrement de la fiche d'édition. Cette version restaure le comportement attendu et auto-répare les variantes déjà altérées.

## Rappel du contexte (3.20.70)

La 3.20.70 a ajouté les colonnes `code`, `base_formation_id`, `variant_number` à la table `wp_acdc_of_formations` via `maybe_add_table_column()`, après détection que `dbDelta()` ne les avait pas créées sur les bases existantes. Ces colonnes sont nécessaires au stockage des variantes.

Cette correction était nécessaire mais pas suffisante. Un second défaut, indépendant, restait actif et cassait également le comportement attendu.

## Diagnostic 3.20.71

Le scénario complet du bug observé :

1. **Duplication via menu trois points** — `handle_duplicate_formation()` crée bien une nouvelle ligne en base avec `code='1.1'`, `base_formation_id=1`, `variant_number=1`. Aucun problème ici.
2. **Redirection automatique** vers la fiche d'édition de la nouvelle formation (action `edit`).
3. **Modification éventuelle** de la modalité, du tarif ou de tout autre champ propre à la variante, puis **clic sur Enregistrer**.
4. **Exécution de `handle_save_formation()`** — c'est ici que la valeur `code='1.1'` était écrasée.

La cause précise se trouvait à la ligne du tableau `$data` :

```php
'code' => isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '',
```

Le formulaire d'édition d'une formation (template du plugin) ne contient **aucun champ `name="code"`** — c'est volontaire, le code variante n'est pas censé être éditable manuellement par l'utilisateur, il est posé automatiquement à la duplication. La conséquence imprévue : `$_POST['code']` est toujours absent, le fallback `''` est systématiquement appliqué, et `wpdb->update()` écrase la valeur correcte.

À l'affichage de la liste, le rendu retombe alors sur l'ID brut auto-incrémenté (`$entry->id`) au lieu d'afficher le code variante.

## Correction

Réécriture de la résolution de la valeur `code` dans `handle_save_formation()` selon une logique en cascade :

1. **Si `$_POST['code']` est présent** (cas hypothétique d'un formulaire futur qui exposerait le champ) → utiliser la valeur postée.
2. **Sinon, en édition d'une formation existante** :
   - Si `$existing->code` est non vide → préserver la valeur en base (cas nominal d'une variante déjà identifiée).
   - Sinon, si `$existing->base_formation_id` ET `$existing->variant_number` sont non nuls → reconstruire le code au format `base_formation_id.variant_number` (auto-réparation des variantes dont le code aurait été écrasé par une version antérieure).
   - Sinon → chaîne vide (formation mère sans variante).
3. **Sinon, en création** → chaîne vide (comportement identique à avant).

## Auto-réparation rétroactive

Les variantes dont le `code` a été perdu par un enregistrement effectué sous version antérieure (3.20.70 ou plus ancienne) seront **automatiquement réparées au prochain enregistrement de leur fiche**, à condition que `base_formation_id` et `variant_number` soient encore présents en base.

Procédure pour récupérer la formation 11 (ou toute variante orpheline) :

1. Installer 3.20.71 et purger LiteSpeed.
2. Aller sur la liste Formations.
3. Ouvrir la fiche d'édition de la formation orpheline (icône Modifier).
4. Cliquer simplement sur Enregistrer (sans rien changer si vous ne voulez rien modifier).
5. Recharger la liste : la colonne ID doit afficher `1.1` (ou le code variante reconstruit).

Si `base_formation_id=0` et `variant_number=0` chez vous (cas où la duplication initiale n'avait posé aucun marqueur, par exemple parce qu'elle a tourné avant 3.20.70), l'auto-réparation ne peut pas reconstruire le code. Deux options dans ce cas :

- **Reparamétrage SQL direct** — accès phpMyAdmin via le panneau N0C :
  ```sql
  UPDATE wp_acdc_of_formations
  SET code='1.1', base_formation_id=1, variant_number=1
  WHERE id=11;
  ```
- **Archiver puis redupliquer** la formation mère — la nouvelle variante portera un code variante propre.

## Diagnostic préalable recommandé

Avant ou après installation, exécuter dans phpMyAdmin :

```sql
SELECT id, code, base_formation_id, variant_number
FROM wp_acdc_of_formations
WHERE id = 11;
```

Trois cas de figure :

| Résultat | Conclusion |
|---|---|
| `code='', base_formation_id=1, variant_number=1` | La duplication a fonctionné, seul le SAVE a écrasé. **Auto-réparation au prochain Enregistrer.** |
| `code='', base_formation_id=0, variant_number=0` | La duplication n'avait pas posé les marqueurs. Reparamétrer en SQL ou archiver/redupliquer. |
| Erreur `Unknown column 'code'` | La 3.20.70 n'a pas tourné. Désactiver/réactiver le plugin pour forcer `install_or_update`. |

## Engagement de préservation

- **Aucun rendu modifié.** Le formulaire d'édition reste strictement identique.
- **Aucun CSS modifié.**
- **Aucun JavaScript modifié.**
- **Aucune modification de la logique de duplication elle-même** (`handle_duplicate_formation`).
- **Aucune autre logique de save modifiée** — seule la résolution de `code` a été extraite et durcie.

Modifications effectuées :

1. `acdc-formation-saas-organisme-de-formation.php` : version `3.20.70` → `3.20.71` (en-tête + constante).
2. `includes/kernel/class-acdc-kernel-actions-trait.php` : remplacement d'une ligne par un bloc de 17 lignes dans `handle_save_formation()` (résolution de la valeur `code`).

Toutes les corrections 3.20.57 → 3.20.70 sont conservées. Le patch BD de 3.20.70 reste en place et reste utile.

## Risques de régression

Nuls.

| Scénario | Avant 3.20.71 | Après 3.20.71 |
|---|---|---|
| Création nouvelle formation | `code=''` | `code=''` (identique) |
| Édition formation mère sans variante | `code=''` (déjà vide, écrasé par vide) | `code=''` (préservé tel quel) |
| Édition variante avec code valide en base | **`code` écrasé par vide ❌** | **`code` préservé ✅** |
| Édition variante avec code vide mais marqueurs présents | `code=''` (reste vide) | **`code` reconstruit `X.Y` ✅** |
| Édition variante sans aucun marqueur | `code=''` | `code=''` (rien à reconstruire, identique) |

## Procédure de test

1. Purger LiteSpeed (objects + DB + plugin cache).
2. Installer 3.20.71. Purger à nouveau.
3. Aller sur la liste Formations.
4. **Test auto-réparation** (si vous avez la formation 11 orpheline) :
   - Ouvrir la fiche de modification de la formation 11.
   - Cliquer sur Enregistrer sans rien changer.
   - Recharger la liste → la colonne ID doit afficher `1.1`.
5. **Test duplication propre** :
   - Sur une formation mère (ex. ID 6), menu trois points → Dupliquer.
   - Vérifier que la nouvelle ligne affiche `6.1` dans la colonne ID, **et pas un nouvel ID auto-incrémenté**.
   - La fiche d'édition s'ouvre. Modifier la modalité (ex. Présentiel → Distanciel). Enregistrer.
   - Recharger la liste → la colonne ID doit toujours afficher `6.1`.
6. **Test deuxième variante** :
   - Redupliquer la formation 6 → la nouvelle variante doit afficher `6.2`.
   - Modifier et enregistrer → toujours `6.2`.
7. **Test non-régression formation mère** :
   - Modifier une formation mère existante (ex. ID 1) sans la dupliquer.
   - Vérifier que la colonne ID continue d'afficher `1` (pas de code variante posé indésirablement).

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump de version.
- `includes/kernel/class-acdc-kernel-actions-trait.php` — résolution de `$code_value` dans `handle_save_formation()`.

**Aucun autre fichier modifié.**

## Si quelque chose ne va pas

Le retour à 3.20.70 est immédiat et sans risque. La 3.20.71 ne crée aucune dépendance dure : c'est une logique pure côté PHP, sans nouvelle table, sans nouveau hook, sans nouveau fichier.

## Suite logique

Une fois la duplication validée comme stable, on pourra discuter de l'ergonomie de la liste Formations :

- Affichage visuel du lien parent-enfant (indentation, badge "variante", regroupement).
- Filtre pour masquer les variantes et n'afficher que les formations mères.
- Indication sur la fiche mère du nombre de variantes existantes.
- Comportement de l'archivage : archiver la mère doit-il archiver les variantes ?

Aucune de ces évolutions n'est livrée dans 3.20.71. Elles attendent votre validation explicite.
