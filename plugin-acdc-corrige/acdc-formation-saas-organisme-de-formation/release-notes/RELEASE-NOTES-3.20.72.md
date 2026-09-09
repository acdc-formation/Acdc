# ACDC Formation SAAS — version 3.20.72

## Objet

Tri groupé des formations dans la liste : chaque variante (`X.Y`) apparaît désormais immédiatement après sa formation mère (`X`), au lieu d'être éparpillée selon son ID auto-incrémenté.

## Contexte

Avant 3.20.72, la requête `get_formations()` ordonnait par `id DESC`. Comme les variantes reçoivent un ID auto-incrémenté supérieur à toutes les formations existantes (ex. 10, 11, 12…), elles remontaient en haut de la liste, séparées de leur formation mère.

Exemple d'affichage observé en 3.20.71 :

```
9.1, 2.1, 1.1, 9, 8, 7, 6, 5, 4, 3, 2, 1
```

Comportement attendu, demandé pour cohérence métier :

```
1, 1.1, 2, 2.1, 3, 4, 5, 6, 7, 8, 9, 9.1
```

La formation mère apparaît d'abord, immédiatement suivie de toutes ses variantes, le groupe suivant commence ensuite.

## Correction

Modification de l'unique `ORDER BY` dans `get_formations()` (`class-acdc-kernel-core-trait.php`) :

```sql
ORDER BY 
  (CASE WHEN base_formation_id > 0 THEN base_formation_id ELSE id END) ASC,
  variant_number ASC,
  id ASC
```

Lecture du tri :

1. **Premier critère** — calcul du « groupe » de chaque ligne :
   - Si `base_formation_id > 0` → c'est une variante, son groupe est l'ID de la mère.
   - Sinon → c'est une formation mère, son groupe est son propre ID.
2. **Deuxième critère** — au sein d'un groupe, la mère (`variant_number = 0`) apparaît avant ses variantes (`variant_number = 1, 2, 3…`).
3. **Troisième critère** — `id ASC` pour garantir un ordre stable et déterministe en cas d'égalité (très rare, mais évite tout aléa visuel à chaque rechargement de page).

## Périmètre transverse

`get_formations()` est utilisée à 16 endroits dans le plugin. Le nouveau tri s'applique partout :

- Liste principale Formations (page extranet) — bénéfice direct demandé.
- Sélecteurs `<select>` dans les modules :
  - Sessions
  - Dossiers / Contrats / Conventions
  - Évaluations
  - Questionnaires (intermédiaires, à chaud, à froid, formateurs, entreprises)
  - CRM commercial
  - Inscriptions

Ce comportement transverse est **souhaitable** : dans un sélecteur, l'utilisateur voit également les variantes regroupées avec leur formation mère, ce qui facilite la sélection.

## Conséquence à connaître

L'ordre passe de `id DESC` (les plus récentes en haut) à un ordre **groupé ASC** (formation 1 en haut, formation la plus récente en bas du tableau).

Pour une bibliothèque de formations stable (cas d'un OF avec catalogue défini), cet ordre groupé ASC est plus naturel à parcourir.

Si vous tenez à garder « les plus récentes en haut » dans un écran particulier (par exemple le sélecteur Sessions ou le CRM), je peux ajouter un paramètre optionnel `'order' => 'recent'` à `get_formations()` qui inverserait l'ordre des groupes uniquement pour les écrans concernés. À me demander si besoin.

## Engagement de préservation

- **Aucun rendu modifié.** La structure du tableau, les colonnes, les actions, le menu trois points : strictement identiques.
- **Aucun CSS modifié.**
- **Aucun JavaScript modifié.**
- **Aucune logique métier modifiée** — pas de duplication, pas de save, pas de validation.
- **Aucune autre requête modifiée.** Seule la clause `ORDER BY` de `get_formations()` est touchée.

## Risques de régression

Quasi nuls.

| Scénario | Avant 3.20.72 | Après 3.20.72 |
|---|---|---|
| Liste Formations (page extranet) | Ordre `id DESC`, variantes en haut | **Ordre groupé ASC, variantes sous leur mère ✅** |
| Sélecteur formation dans Sessions, CRM, etc. | Ordre `id DESC` | Ordre groupé ASC — plus lisible |
| Recherche par titre/code/modalité/ville | Filtre WHERE inchangé, ordre groupé sur les résultats filtrés | Idem, comportement plus cohérent |
| Limit (paginations / appels limités) | LIMIT après ORDER BY | Identique mécaniquement |

Le seul changement perceptible est l'ordre d'apparition des formations dans les listes et les sélecteurs.

## Procédure de test

1. Purger LiteSpeed.
2. Installer 3.20.72. Purger à nouveau.
3. **Test 1 — page Formations** :
   - Ouvrir la liste.
   - Vérifier l'ordre attendu : `1, 1.1, 2, 2.1, 3, 4, 5, 6, 7, 8, 9, 9.1`.
4. **Test 2 — création d'une session** :
   - Aller créer une nouvelle session, ouvrir le sélecteur Formation.
   - Vérifier que les variantes apparaissent juste après leur mère dans la liste déroulante.
5. **Test 3 — duplication post-installation** :
   - Dupliquer la formation 5.
   - Vérifier qu'elle apparaît immédiatement sous la formation 5 dans la liste, en `5.1`.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump de version.
- `includes/kernel/class-acdc-kernel-core-trait.php` — clause `ORDER BY` de `get_formations()`.

**Aucun autre fichier modifié.**

## Si quelque chose ne va pas

Le retour à 3.20.71 est immédiat et sans risque. Aucune dépendance dure n'est créée par ce patch — c'est une simple modification de la clause ORDER BY d'une requête SQL.

## Suite logique

Si le tri groupé répond à votre attente, on pourra continuer sur l'ergonomie de la liste Formations selon votre plan initial :

- Affichage visuel du lien parent-enfant (indentation légère, badge « variante » discret) pour rendre la hiérarchie immédiatement perceptible à l'œil.
- Filtre rapide pour masquer les variantes et n'afficher que les formations mères.
- Indication, sur la fiche mère, du nombre de variantes existantes.
- Décision sur le comportement d'archivage : archiver une mère doit-il proposer d'archiver aussi ses variantes ?

Aucune de ces évolutions n'est livrée dans 3.20.72. Elles attendent votre validation explicite.
