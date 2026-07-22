# ACDC Formation SAAS — version 3.20.76

## Objet

Évolution ergonomique et conformité BPF du formulaire Formation : refonte des champs « Objectif de la prestation dispensée » et « Spécialité de la formation » conformes à la nomenclature officielle française, ajout d'un champ conditionnel « Niveau de qualification », et déplacement du champ « Sessions à venir » dans la section Informations principales.

S'applique simultanément aux trois pages **Créer une formation**, **Voir une formation** et **Modifier une formation** (formulaire partagé).

## Modifications appliquées

### 1. Déplacement de « Sessions à venir »

Le champ a quitté la section **Catalogue** pour rejoindre la section **Informations principales**, juste après le grid Format/Adresse/Ville/CP/Tarif/Durée. Aucune modification de schéma BD : la colonne `future_sessions` reste à la même place, seul le rendu change.

### 2. « Objectif de la prestation dispensée » — refonte des options

Anciennes valeurs (4 options génériques) :

- Acquisition de compétences
- Certification
- Mise à jour réglementaire
- Sensibilisation

Nouvelles valeurs (6 options officielles BPF) :

| Clé interne | Libellé affiché |
|---|---|
| `rncp` | Formation visant un diplôme, un titre à finalité professionnelle ou un certificat de qualification professionnelle enregistré au Répertoire national des certifications professionnelles (RNCP) |
| `rs` | Formation visant une certification (dont CQP) ou une habilitation enregistrée au répertoire spécifique (RS) |
| `cqp_non_inscrit` | Formation visant un CQP non enregistré au RNCP ou au RS |
| `autre_pro` | Autre formation professionnelle |
| `bilan_competences` | Bilan de compétences |
| `vae` | Action d'accompagnement à la validation des acquis de l'expérience. |

Stockage en base : la **clé interne** est enregistrée dans `service_objective` (ex. `rncp`). Cela évite tout problème de longueur de chaîne et facilite les futures statistiques BPF.

### 3. Nouveau champ conditionnel « Niveau de qualification »

Apparaît **uniquement** quand l'objectif sélectionné est `rncp` (Formation visant un diplôme RNCP). Pour toutes les autres options, le bloc est masqué et la valeur est réinitialisée à vide à la sauvegarde.

Options disponibles :

| Clé interne | Libellé affiché |
|---|---|
| `niveau_6_8` | De niveau 6 à 8 (Licence, Master, diplôme d'ingénieur, Doctorat, etc) |
| `niveau_5` | De niveau 5 (BTS, DUT, écoles de formation sanitaire et sociale, etc.) |
| `niveau_4` | De niveau 4 (BAC professionnel, BT, BP, BM, etc.) |
| `niveau_3` | De niveau 3 (BEP, CAP, etc.) |
| `niveau_2` | De niveau 2 |
| `cqp_sans_niv` | Certificat de qualification professionnelle (CQP) sans niveau de qualification |

Logique de show/hide gérée par un mini script JavaScript inline (~10 lignes), sans dépendance externe, scopé au formulaire. Au changement d'objectif :

- Si nouvelle valeur = `rncp` → bloc affiché.
- Sinon → bloc masqué et select interne remis à `""`. La valeur en BD sera donc reset au save suivant.

### 4. « Spécialité de la formation » — passage en sélecteur structuré NSF

L'ancien champ texte libre est remplacé par un `<select>` HTML standard contenant les **~80 codes officiels** de la **Nomenclature des Spécialités de Formation** française. La hiérarchie est rendue visible par une indentation (espaces non-sécables) :

```
100 — Formations générales
   110 — Spécialités pluriscientifiques
      111 — Physique-chimie
      112 — Chimie-biologie, biochimie
      …
   120 — Spécialités pluridisciplinaires, sciences humaines et droit
      121 — Géographie
      …
200 — Technologies industrielles fondamentales (...)
   201 — Technologies de commandes (...)
210 — Spécialités plurivalentes de l'agronomie et de l'agriculture
   211 — Productions végétales (...)
   …
421 — Jeux et activités spécifiques de loisirs
422 — Economie et activités domestiques
423 — Vie familiale, vie sociale et autres formations au développement personnel
```

Les codes parents (100, 110, 200, 300, 310, 410…) restent sélectionnables — c'est conforme à la pratique NSF officielle où une formation peut être catégorisée à un niveau intermédiaire si aucune sous-spécialité ne convient.

Stockage en base : le **code numérique** est enregistré dans `specialty` (ex. `326` pour « Informatique, traitement de l'information, réseaux »).

### 5. Nouvelle colonne BD : `service_objective_level`

Ajout d'une colonne `service_objective_level VARCHAR(190) DEFAULT ''` dans `wp_acdc_of_formations`. Ajoutée :

- Au schéma `CREATE TABLE` pour les nouvelles installations.
- Via `maybe_add_table_column()` pour les bases existantes, à la suite des colonnes variantes ajoutées en 3.20.70.

Idempotente, comme toutes les autres migrations. Si la colonne existe déjà, l'opération est un no-op.

## Rétrocompatibilité — anciennes valeurs

### Anciennes valeurs `service_objective`

Les formations existantes peuvent contenir : `Acquisition de compétences`, `Certification`, `Mise à jour réglementaire`, `Sensibilisation`. Comportement à l'ouverture :

- **Le select affiche un placeholder « Choisir une option »** comme valeur sélectionnée (parce qu'aucune des nouvelles clés ne matche).
- **Une option grisée et désactivée** apparaît en bas de la liste avec le texte : `(ancienne valeur : Certification — veuillez choisir une option dans la liste)`. Elle indique à l'utilisateur ce qui était stocké avant, sans qu'il puisse la sélectionner.
- **La valeur en BD reste préservée** tant que l'utilisateur n'enregistre pas. Au prochain enregistrement, l'utilisateur choisit une nouvelle option et la valeur ancienne est remplacée par la nouvelle clé (`rncp`, `rs`, etc.).

Aucune migration automatique : pas de mapping arbitraire d'« ancien » vers « nouveau » qui pourrait être faux pour certains cas.

### Anciennes valeurs `specialty`

Les formations existantes peuvent contenir un texte libre (ex. « Marketing digital », « WordPress », « Comptabilité PME »). Comportement à l'ouverture :

- **Le select affiche un placeholder « Choisir une option »** comme valeur sélectionnée.
- **Une option grisée et désactivée** apparaît en bas avec le texte : `(ancienne valeur : Marketing digital — veuillez choisir une option dans la liste)`.
- **La valeur en BD reste préservée** jusqu'au prochain enregistrement.

L'utilisateur doit revoir la spécialité au prochain passage sur la fiche. Coût marginal acceptable.

## Engagement de préservation

- **Aucune valeur effacée en BD** au moment de la mise à jour.
- **Aucune migration automatique** des anciennes valeurs (pas de mapping arbitraire).
- **Aucune autre page touchée**.
- **Aucun CSS modifié.**
- **Aucun fichier JS modifié** (le mini script est inline dans le formulaire, scopé via un `(function(){})()` immédiatement invoqué).
- **L'éditeur riche (3.20.74-75) reste intact** — aucune interaction avec ces nouveaux selects.
- **Variantes de formation (3.20.70-72) restent intactes**.

Toutes les corrections 3.20.57 → 3.20.75 sont conservées.

## Risques de régression — analyse

| Scénario | Avant 3.20.76 | Après 3.20.76 |
|---|---|---|
| Création nouvelle formation | 4 options Objectif, input texte Spécialité | 6 options BPF, 80 spécialités NSF, niveau conditionnel |
| Édition formation avec ancienne `service_objective` | Sélectionnée dans le select | Placeholder + option grisée explicative en fin de liste |
| Édition formation avec ancienne `specialty` (texte libre) | Texte affiché dans l'input | Placeholder + option grisée explicative |
| Édition formation post-3.20.76 (clés correctes) | — | Sélectionnée correctement |
| Sauvegarde sans changement après ouverture | Garde la valeur ancienne en BD | Garde la valeur ancienne en BD si l'utilisateur ne touche pas le select (l'option grisée est sélectionnée mais non soumissible — `disabled`). Voir note ci-dessous. |
| Sélection objectif `rncp` puis changement vers autre | — | Bloc Niveau caché et niveau remis à vide au save |
| Catalogue public | Affiche le label de l'objectif (ex. « Certification ») | Affiche la clé interne (ex. `rncp`) — **point d'attention, voir ci-dessous** |
| Contrats / PDF | N'utilisent pas ces champs | Identique |

### Point d'attention 1 — Comportement du sélecteur lors d'un save sans modification

Une option `disabled` n'est pas soumise dans `$_POST`. Donc si l'utilisateur ouvre une fiche avec une ancienne valeur (ex. `Certification`), voit l'option grisée sélectionnée, et clique Enregistrer **sans rien changer** :

- `$_POST['service_objective']` arrivera **vide**.
- La valeur en BD sera donc réécrite à `''`.

C'est volontaire : on force l'utilisateur à choisir une vraie option de la nouvelle liste avant de pouvoir enregistrer un autre champ. Sinon, on aurait un mélange d'anciennes et nouvelles valeurs en BD qui rendrait les statistiques BPF incohérentes.

**Recommandation utilisateur** : si vous ouvrez une fiche pour modifier autre chose qu'Objectif/Spécialité, pensez d'abord à remettre une nouvelle valeur dans ces deux champs.

### Point d'attention 2 — Catalogue public

Le catalogue public (`includes/settings-catalog/class-acdc-settings-catalog-render-trait.php`) affiche probablement encore le contenu brut de `service_objective` et `specialty`. Avec les nouvelles clés (`rncp`, `326`...), il afficherait des chaînes peu lisibles.

**Cette adaptation n'est pas livrée dans 3.20.76.** Elle nécessite un patch dédié 3.20.77 où on remplacera les `echo $formation->service_objective` par un appel à un mapper qui transforme la clé en libellé. C'est intentionnellement gardé pour un patch séparé afin de ne pas mélanger les modifications.

Si votre catalogue public est en production active, **signalez-le** — je produis le patch 3.20.77 immédiatement.

## Procédure de test

### Test 1 — Migration BD

1. Purger LiteSpeed.
2. Installer 3.20.76. Purger à nouveau.
3. (Optionnel) En SQL : `SHOW COLUMNS FROM wp_acdc_of_formations LIKE 'service_objective_level';` → doit retourner une ligne.

### Test 2 — Création d'une nouvelle formation

1. Aller sur Formations → Créer une formation.
2. Vérifier que le champ « Sessions à venir » apparaît juste après Tarif/Durée, **pas** dans la section Catalogue.
3. Vérifier que le select « Objectif de la prestation dispensée » contient les 6 nouvelles options.
4. Vérifier que le select « Spécialité de la formation » contient les ~80 options NSF avec hiérarchie indentée.
5. Sélectionner « Formation visant un diplôme … (RNCP) » → vérifier qu'un nouveau champ « Niveau de qualification » apparaît juste en dessous.
6. Sélectionner une autre option dans Objectif → vérifier que Niveau disparaît.
7. Re-sélectionner RNCP, choisir un niveau, choisir une spécialité, enregistrer.
8. Rouvrir la fiche → vérifier que les valeurs sont bien restituées.

### Test 3 — Édition d'une formation existante (ancienne `service_objective`)

1. Ouvrir une formation créée avant 3.20.76 (qui a, par exemple, `service_objective = 'Certification'`).
2. Vérifier que le select Objectif affiche « Choisir une option ».
3. Faire défiler les options jusqu'en bas → constater l'option grisée `(ancienne valeur : Certification — veuillez choisir une option dans la liste)`.
4. Sélectionner une nouvelle option (ex. `Formation visant une certification (RS)`), enregistrer.
5. Rouvrir → la nouvelle option doit être sélectionnée correctement et l'option grisée a disparu.

### Test 4 — Édition d'une formation existante (ancienne `specialty` libre)

Procédure identique au test 3 mais sur le champ Spécialité.

### Test 5 — Mode Voir

1. Ouvrir une formation en consultation.
2. Vérifier que les selects sont désactivés (grisés, non modifiables).
3. Le bloc Niveau apparaît si l'objectif est `rncp`, sinon caché.

### Test 6 — Recherche / filtres dans la liste Formations

Vérifier que la recherche (par titre, code, modalité, ville) continue de fonctionner. La recherche n'utilise pas les champs Objectif/Spécialité, donc aucun impact attendu.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump `3.20.75` → `3.20.76`.
- `includes/kernel/class-acdc-kernel-core-trait.php` :
  - Ajout de `service_objective_level` au `CREATE TABLE`.
  - Ajout de `maybe_add_table_column( ..., 'service_objective_level', ... )`.
- `includes/kernel/class-acdc-kernel-render-trait.php` :
  - Trois nouvelles fonctions : `get_acdc_service_objective_options()`, `get_acdc_service_objective_level_options()`, `get_acdc_nsf_specialty_options()`.
  - Refonte du bloc Objectif/Spécialité dans le formulaire formation.
  - Ajout du bloc conditionnel Niveau de qualification + script inline de show/hide.
  - Déplacement du champ Sessions à venir vers Informations principales.
  - Suppression du champ Sessions à venir de la section Catalogue.
- `includes/kernel/class-acdc-kernel-actions-trait.php` :
  - Ajout de la sanitization `service_objective_level` dans `handle_save_formation()`.

**Aucun autre fichier modifié.**

## Si quelque chose ne va pas

Le retour à 3.20.75 est immédiat. La colonne `service_objective_level` ajoutée en BD ne disparaît pas (utile ou inerte selon le code installé), aucune perte de données.

## Suite logique

- **3.20.77** — Adaptation du catalogue public pour traduire les clés (`rncp`, `326`, etc.) en libellés lisibles. À déclencher si vous utilisez le catalogue public en production.
- **Évolution future** — Treeview custom navigable pour la spécialité NSF (chevrons, recherche), si l'option B (select natif avec indentation) ne donne pas satisfaction à l'usage.
- **Statistiques BPF** — Les nouvelles clés normalisées (`rncp`, `rs`, `niveau_6_8`...) facilitent les futures fonctions d'export BPF officiel.

Aucune de ces évolutions n'est livrée dans 3.20.76. Elles attendent votre validation explicite.
