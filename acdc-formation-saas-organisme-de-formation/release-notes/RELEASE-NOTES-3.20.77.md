# ACDC Formation SAAS — version 3.20.77

## Objet

Refonte de la section **Bibliothèque** du formulaire Formation : affichage des documents existants avec nom et bouton « Voir », suppression individuelle via case à cocher, comportement add-then-merge au save (au lieu du replace global), et amélioration des libellés de ressources dans l'extranet apprenant (vrai nom du fichier au lieu d'un label générique).

S'applique aux trois pages **Créer / Voir / Modifier une formation**.

## Modifications appliquées

### 1. Documents partagés — affichage + suppression individuelle

Pour chaque document déjà présent en base, le formulaire affiche désormais :

- Le **nom du fichier** (extrait de l'URL : `mon-document.pdf`).
- Un **bouton « Voir »** qui ouvre le fichier dans un nouvel onglet.
- Une **case à cocher « Supprimer »** pour marquer le document à retirer au prochain enregistrement.

L'input file `<input type="file" multiple>` reste disponible en dessous pour ajouter de nouveaux documents.

### 2. Documents internes — même traitement

Strictement identique au point 1, sur le champ Documents internes (non partagés). Mêmes contrôles, même UX. Les apprenants n'y ont toujours pas accès, c'est un usage interne ACDC uniquement (cohérent avec le nom du champ).

### 3. Liens partagés — aperçu cliquable sous le textarea

Le textarea de saisie des liens (un par ligne) est conservé pour l'édition. **En dessous**, ajout d'une **liste d'aperçu** affichant chaque lien comme une ligne cliquable :

- Affichage du domaine + chemin court (ex. `youtube.com/watch?v=abc123`).
- Bouton **« Voir »** qui ouvre le lien dans un nouvel onglet.

L'utilisateur peut ainsi vérifier d'un coup d'œil que chaque lien est valide et accessible, sans devoir copier-coller dans une nouvelle fenêtre.

### 4. Comportement add + delete au save (changement de comportement important)

**Avant 3.20.77** : si l'utilisateur uploadait un nouveau fichier dans le champ Documents partagés, **tous les anciens étaient remplacés**. Si rien n'était uploadé, les anciens étaient conservés. Comportement de type `replace if any upload`.

**À partir de 3.20.77** : comportement add + delete sélectif :

1. On part de la liste existante en base.
2. On retire les documents dont la case « Supprimer » est cochée (`shared_docs_remove[]` dans `$_POST`).
3. On ajoute à la fin les nouveaux fichiers uploadés.
4. Déduplication finale (au cas où un fichier serait uploadé deux fois).

Une seule fonction utilitaire centralise cette logique : `acdc_merge_doc_lists()` dans `class-acdc-kernel-actions-trait.php`. Réutilisable pour d'autres modules futurs avec la même problématique multi-fichiers.

### 5. Extranet apprenant — vrai nom de fichier au lieu de « Document partagé 1 »

Avant 3.20.77, l'extranet apprenant affichait des labels génériques : `Document partagé 1`, `Document partagé 2`, `Ressource externe 1`. Difficile pour l'apprenant de savoir ce qu'il télécharge sans cliquer.

À partir de 3.20.77 :

- **Documents partagés** : le label devient le **nom réel du fichier** (`introduction-au-management.pdf`, `support-de-cours.docx`, etc.). Si pour une raison quelconque le nom ne peut pas être extrait, fallback sur l'ancien label générique.
- **Liens partagés** : le label devient le **domaine + chemin court** (`youtube.com/watch?v=abc123`, `acdc-formation.com/ressources/cours-1`). Fallback sur `Ressource externe N` si l'URL ne peut pas être parsée.

Modification appliquée à **deux endroits** dans `class-acdc-learner-portal-core-trait.php` (lignes 1026 et 1347), correspondant respectivement à la liste de ressources et au regroupement par catégorie.

## Engagement de préservation

- **Aucune modification du schéma BD.** Les colonnes `shared_docs`, `internal_docs`, `shared_links` conservent le format multiline d'URLs.
- **Aucune migration de données.** Les valeurs existantes restent intactes et fonctionnelles.
- **Aucune autre page touchée** dans le formulaire formation. Ergonomie 3.20.73-76 conservée.
- **Aucun CSS modifié** (utilisation des classes `acdc-button acdc-button-soft` existantes pour le bouton « Voir »).
- **Aucun JavaScript modifié** (logique purement PHP côté serveur).
- **Toutes les corrections 3.20.57 → 3.20.76 sont conservées.**

## Risques de régression — analyse

| Scénario | Avant 3.20.77 | Après 3.20.77 |
|---|---|---|
| Création formation, upload de 3 fichiers | 3 fichiers stockés | 3 fichiers stockés (identique) |
| Édition formation avec 5 docs, ajout d'1 nouveau | **Replace : on perd les 5 anciens, il ne reste que le nouveau** ❌ | **Add : on a 6 docs, les 5 anciens préservés** ✅ |
| Édition formation, retrait d'1 doc | Impossible sans tout re-uploader | Cocher « Supprimer » + Enregistrer |
| Édition formation, aucun changement | Anciens conservés | Anciens conservés (identique) |
| Édition formation, retrait d'1 doc + ajout d'1 nouveau | Replace global, perte des autres | Suppression du doc coché + ajout du nouveau, autres préservés |
| Affichage extranet apprenant — documents | « Document partagé 1, 2, 3... » | Vrais noms de fichiers |
| Affichage extranet apprenant — liens | « Ressource externe 1, 2, 3... » | Domaine + chemin court |

**Régression possible identifiée** : si un utilisateur a un workflow où il prévoit d'uploader pour effacer les anciens (peu probable mais possible), il sera surpris par le nouveau comportement. Ce changement est documenté dans le formulaire via le texte d'aide « Les nouveaux fichiers s'ajoutent à ceux déjà présents. Cochez "Supprimer" pour retirer un fichier au prochain enregistrement. »

## Procédure de test

### Test 1 — Affichage des documents existants

1. Purger LiteSpeed.
2. Installer 3.20.77. Purger à nouveau.
3. Ouvrir une formation existante qui a déjà des documents partagés (ou en uploader avant si aucune).
4. Vérifier qu'au-dessus de l'input file, la liste des documents apparaît avec : nom du fichier, bouton « Voir », case « Supprimer ».
5. Cliquer sur « Voir » → le fichier doit s'ouvrir dans un nouvel onglet.

### Test 2 — Suppression sélective

1. Sur une formation avec 3 documents partagés.
2. Cocher « Supprimer » sur le 2ᵉ document. Cliquer Enregistrer.
3. Rouvrir la fiche → vérifier qu'il ne reste plus que les documents 1 et 3.
4. Vérifier que le document supprimé n'apparaît plus dans l'extranet apprenant non plus (purge LiteSpeed côté front d'abord).

### Test 3 — Ajout sans perte des anciens

1. Sur une formation avec 2 documents partagés.
2. Uploader 1 nouveau document (sans rien cocher).
3. Cliquer Enregistrer. Rouvrir la fiche.
4. Vérifier que **3 documents** sont présents (les 2 anciens + le nouveau).

### Test 4 — Aperçu des liens cliquables

1. Sur une formation, dans le textarea Liens partagés, taper une ou plusieurs URLs (une par ligne).
2. Cliquer Enregistrer.
3. Rouvrir la fiche → vérifier que sous le textarea, chaque lien apparaît dans un cadre avec un bouton « Voir ».
4. Cliquer « Voir » → le lien s'ouvre dans un nouvel onglet.

### Test 5 — Documents internes

Procédure identique au test 1 et 2, sur le champ Documents internes (non partagés).

### Test 6 — Extranet apprenant

1. Connecter un compte apprenant inscrit à une formation qui a au moins un document partagé.
2. Aller dans la section ressources de l'extranet.
3. Vérifier que les documents apparaissent avec leur **vrai nom** (ex. `introduction-au-management.pdf`) et non plus `Document partagé 1`.
4. Idem pour les liens.

### Test 7 — Mode Voir

1. Ouvrir une formation en consultation (œil dans la liste).
2. Vérifier que les documents apparaissent avec leur nom et le bouton « Voir », **mais sans case à cocher** (lecture seule).
3. Vérifier qu'aucun input file n'apparaît.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump `3.20.76` → `3.20.77`.
- `includes/kernel/class-acdc-kernel-render-trait.php` :
  - Nouvelles fonctions utilitaires `render_acdc_existing_docs_list()` et `render_acdc_existing_links_preview()`.
  - Refonte complète de la section Bibliothèque dans le formulaire formation.
- `includes/kernel/class-acdc-kernel-actions-trait.php` :
  - Nouvelle fonction `acdc_merge_doc_lists()` (logique add + delete sélectif).
  - Refonte de la zone d'agrégation des documents au save (`shared_docs` et `internal_docs`).
- `includes/learner-portal/core/class-acdc-learner-portal-core-trait.php` :
  - Deux blocs adaptés (lignes ~1026 et ~1347) pour utiliser le vrai nom de fichier / chemin court de lien comme label de ressource.

**Aucun autre fichier modifié.**

## Si quelque chose ne va pas

Le retour à 3.20.76 est immédiat et sans risque. Le format de stockage en BD n'a pas changé : les colonnes restent en LONGTEXT avec URLs séparées par retour ligne. Ce sont uniquement la logique de save et le rendu qui évoluent.

## Suite logique

- **Ergonomie restante de la fiche formation** : section Paramètres, section Catalogue, section Brouillon — y a-t-il d'autres aspects à revoir ?
- **Application aux autres pages** : la fonction centralisée `render_acdc_existing_docs_list()` est réutilisable. Candidats potentiels : Apprenants (justificatifs), Entreprises (contrats), Formateurs (CV/diplômes), Documents internes ACDC.
- **Évolution future** : si à l'usage les liens partagés deviennent nombreux, on pourra remplacer le textarea par un répétiteur (un input par ligne avec libellé personnalisé + suppression individuelle), comme évoqué initialement (option B mise de côté).

Aucune de ces évolutions n'est livrée dans 3.20.77. Elles attendent votre validation explicite.
