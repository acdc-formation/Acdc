# ACDC Formation SAAS — version 3.20.81

## Objet

Cohérence du couple **modalité ↔ lieu de formation**, à la fois côté formulaire admin (Créer / Voir / Modifier une formation) et côté catalogue public (liste + fiche détail).

Les champs Adresse, Ville et Code postal n'ont de sens que pour les formations physiquement situées quelque part (Présentiel, Hybride). Pour Distanciel et E-learning, ces champs créaient une confusion (adresse de l'organisme affichée comme « Lieu » sur la card alors que la formation est 100 % à distance).

## Modifications appliquées

### 1. Formulaire admin — masquage conditionnel

Dans le formulaire formation, lorsque l'utilisateur change le select **Format**, les champs Adresse, Ville et Code postal apparaissent ou disparaissent automatiquement :

| Format sélectionné | Champs Adresse / Ville / CP |
|---|---|
| Présentiel | **Visibles** |
| Hybride | **Visibles** |
| Distanciel | Masqués |
| E-learning | Masqués |

Comportement à l'ouverture d'une formation existante : la visibilité est calculée côté serveur d'après la modalité actuellement enregistrée, donc l'affichage est correct dès le premier rendu (pas de flash de contenu masqué).

Logique de show/hide : un mini script JS de ~10 lignes, scopé via IIFE, sans dépendance, branché sur l'événement `change` du select. Identique au pattern utilisé pour le champ « Niveau de qualification » conditionnel introduit en 3.20.76.

**Préservation des données** : si une formation a une adresse remplie puis est passée en Distanciel, les valeurs Adresse/Ville/CP **restent en base** (les champs sont juste masqués, pas vidés). Si l'utilisateur la repasse en Présentiel/Hybride, les valeurs réapparaissent telles qu'elles étaient. C'est le comportement attendu pour ne pas pénaliser les essais ou changements d'avis.

### 2. Catalogue public — affichage adapté à la modalité

Sur la **card** de la liste catalogue ET sur la **fiche détail**, la zone « Lieu » est désormais générée dynamiquement par une fonction utilitaire centralisée `render_catalog_location_lines()` :

| Modalité | Affichage |
|---|---|
| Présentiel | `Lieu : 7 avenue Paul Cézanne 83310 Cogolin` (inchangé) |
| Hybride | `Lieu : 7 avenue Paul Cézanne 83310 Cogolin` *+ note italique :* « Le lien de connexion pour la partie à distance vous sera communiqué la veille de la session. » |
| Distanciel | *(pas de "Lieu", note italique seule :)* « Le lien de connexion vous sera communiqué la veille de la session. » |
| E-learning | *(pas de "Lieu", note italique seule :)* « L'accès à la plateforme e-learning vous sera transmis dès votre inscription validée. » |

La ligne **Modalité** reste toujours visible dans le `<ul>` au-dessus, donc l'utilisateur voit immédiatement de quel type de formation il s'agit.

#### Justification des phrases proposées

J'ai retenu trois phrases courtes, professionnelles, et alignées avec les standards Qualiopi (information préalable au stagiaire) :

- **Hybride** : insiste sur la *partie* à distance, pour distinguer du Distanciel pur.
- **Distanciel** : annonce simple et rassurante, pas de jargon technique.
- **E-learning** : positionne l'accès comme automatique post-inscription, ce qui correspond à la logique pédagogique réelle d'un e-learning auto-piloté.

Vous pouvez ajuster les libellés directement dans la fonction `render_catalog_location_lines()` du fichier `includes/settings-catalog/class-acdc-settings-catalog-core-trait.php` si vous préférez une formulation différente. Trois constantes en haut de la fonction, faciles à modifier sans toucher la logique.

### 3. Style de la note explicative

Une seule règle CSS ajoutée pour différencier visuellement la note du reste de la liste :

```css
.acdc-catalog-location-note{font-style:italic;color:#5a6577;font-size:13px}
```

Discret, sobre, n'écrase pas la lecture principale. La note se distingue par l'italique et la couleur grise plus claire, sans crier.

## Centralisation — design pattern

La fonction `render_catalog_location_lines( $formation )` est utilisée à **deux endroits** dans le rendu public (card de la liste + fiche détail). Centraliser la logique garantit :

- **Cohérence** : impossible d'avoir une carte et une fiche détail qui divergeraient.
- **Maintenance** : modifier les phrases ou ajouter une nouvelle modalité (ex. « Mixte » futur) ne nécessite qu'un seul point de modification.
- **Conformité au principe ACDC** : système UI centralisé, pas de duplication.

C'est le même pattern que les fonctions `render_acdc_existing_docs_list()` et `render_acdc_existing_links_preview()` de la 3.20.77, et `acdc_merge_doc_lists()` de la même version. La fiche formation devient progressivement une orchestration de composants centralisés.

## Engagement de préservation

- **Aucune modification de schéma BD.**
- **Aucune migration de données.** Les adresses déjà saisies restent en BD, simplement masquées dans le formulaire et non affichées dans le catalogue pour les modalités à distance.
- **Aucune autre page touchée.**
- **Aucune dépendance externe.**
- **Toutes les corrections 3.20.57 → 3.20.80 conservées.**

## Risques de régression — analyse

| Scénario | Avant 3.20.81 | Après 3.20.81 |
|---|---|---|
| Formulaire — formation Présentiel | Adresse/Ville/CP affichés | Idem |
| Formulaire — formation Distanciel | Adresse/Ville/CP affichés (incohérent) | Masqués |
| Formulaire — passage Présentiel → Distanciel | Champs restent vides à remplir | Champs disparaissent |
| Formulaire — passage Distanciel → Présentiel | — | Champs réapparaissent avec les valeurs préservées |
| Catalogue card — Présentiel | "Lieu : ..." | Idem |
| Catalogue card — Distanciel | "Lieu : adresse de l'organisme" (faux !) | Note italique adaptée |
| Catalogue card — Hybride | "Lieu : adresse" seul | "Lieu : adresse" + note distance |
| Catalogue card — E-learning | "Lieu : adresse" (faux !) | Note italique adaptée |
| Fiche détail | Idem cards | Idem cards (cohérence) |
| Recherche textuelle | Indexait l'adresse dans `data-search` | **Inchangée** : l'adresse reste indexée (utile pour les utilisateurs qui cherchent par ville même sur des distancielles, et n'a pas d'impact visuel) |

**Aucune régression identifiée.**

## Procédure de test

### Test 1 — Formulaire admin, masquage conditionnel

1. Purger LiteSpeed.
2. Installer 3.20.81. Purger à nouveau.
3. Aller sur Formations → Modifier une formation existante.
4. Vérifier que pour une formation **Présentiel**, les champs Adresse/Ville/CP sont visibles dès l'ouverture.
5. Changer le select Format → **Distanciel** : vérifier que les 3 champs disparaissent.
6. Changer pour **E-learning** : idem masqués.
7. Changer pour **Hybride** : ils réapparaissent.
8. Changer pour **Présentiel** : ils restent affichés avec les valeurs précédentes.
9. Enregistrer en mode Distanciel : vérifier qu'aucune erreur n'est levée. Rouvrir la fiche → les valeurs Adresse/Ville/CP doivent toujours être en BD (juste masquées).

### Test 2 — Catalogue public, modalité Présentiel

1. Aller sur `/catalogue/`.
2. Repérer une carte de formation **Présentiel**.
3. Vérifier que la ligne « Lieu : 7 avenue Paul Cézanne 83310 Cogolin » s'affiche normalement.

### Test 3 — Catalogue public, modalité Hybride

1. Sur le catalogue, repérer (ou créer pour le test) une formation **Hybride**.
2. Vérifier que la card affiche :
   - Lieu : adresse complète
   - En dessous (italique gris) : « Le lien de connexion pour la partie à distance vous sera communiqué la veille de la session. »

### Test 4 — Catalogue public, modalité Distanciel

1. Repérer une formation **Distanciel**.
2. Vérifier que **aucune adresse n'est affichée**.
3. Vérifier la note italique : « Le lien de connexion vous sera communiqué la veille de la session. »

### Test 5 — Catalogue public, modalité E-learning

1. Repérer (ou créer) une formation **E-learning**.
2. Vérifier qu'aucune adresse n'apparaît.
3. Vérifier la note italique : « L'accès à la plateforme e-learning vous sera transmis dès votre inscription validée. »

### Test 6 — Cohérence card / fiche détail

1. Cliquer sur une card pour ouvrir la fiche détail.
2. Vérifier que le bloc Lieu/Modalité affiche les **mêmes** informations que sur la card (Lieu + note adaptée selon la modalité).

### Test 7 — Filtres modalité (3.20.79-80) toujours OK

1. Cliquer sur le chip « Distanciel » → seules les distancielles apparaissent.
2. Vérifier que sur ces cards filtrées, l'affichage Lieu/note est bien adapté à Distanciel.
3. Idem pour Hybride et E-learning.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump `3.20.80` → `3.20.81`.
- `includes/kernel/class-acdc-kernel-render-trait.php` :
  - Section Informations principales du formulaire formation : ajout de l'attribut `data-acdc-location-field` sur les `<p>` Adresse/Ville/CP.
  - Ajout d'un id `acdc-formation-modality` sur le select Format.
  - Calcul côté serveur de la visibilité initiale.
  - Mini script JS inline de show/hide.
- `includes/settings-catalog/class-acdc-settings-catalog-core-trait.php` :
  - Nouvelle fonction utilitaire `render_catalog_location_lines()`.
- `includes/settings-catalog/class-acdc-settings-catalog-render-trait.php` :
  - Card de la liste : `<li><strong>Lieu :</strong> ...</li>` remplacé par appel à la fonction centralisée.
  - Fiche détail : idem.
  - Ajout de la règle CSS `.acdc-catalog-location-note`.

**Aucun autre fichier modifié.**

## Si quelque chose ne va pas

Le retour à 3.20.80 est immédiat. Aucune donnée n'est touchée. Les phrases peuvent être ajustées par simple modification de trois variables en haut de `render_catalog_location_lines()`.

## Suite logique

- **Champ « Identifiant d'URL »** : toujours en suspens, à arbitrer (supprimer, brancher en pretty URL, ou laisser).
- **Catalogue public — clés `service_objective` brutes** : avec les nouvelles clés `rncp`, `rs`, `326` (NSF), il faudrait afficher les libellés humains plutôt que les clés. Patch dédié à venir si vous le confirmez.
- **Autres modules** : Apprenants, Entreprises, Sessions, ou autre selon votre priorité.

Aucune de ces évolutions n'est livrée dans 3.20.81.
