# ACDC Formation SAAS — version 3.20.78

## Objet

Refonte visuelle de la section **Bibliothèque** du formulaire Formation : séparation nette entre les ressources partagées avec les apprenants et les documents internes ACDC, par un système de **deux cartes encadrées avec accent couleur**, pour éviter toute confusion (et notamment qu'un document confidentiel ne soit uploadé par erreur dans la zone visible des apprenants). Inversion également de l'ordre input / liste : le sélecteur de fichiers apparaît désormais **au-dessus**, la liste des documents existants **en dessous**.

## Contexte

En 3.20.77, les trois zones (documents partagés, liens partagés, documents internes) avaient une présentation visuelle identique — même fond, même bordure, mêmes contrôles. Le seul différenciateur était le libellé textuel. Avec une fiche formation longue, le risque de confusion entre un fichier visible par les apprenants et un fichier réservé à l'usage interne ACDC était réel. Conséquences potentielles : RGPD, fuite d'informations confidentielles, documents sensibles partagés par erreur.

Cette version règle ce risque par une distinction visuelle forte.

## Modifications appliquées

### 1. Carte « Ressources partagées avec les apprenants » — accent vert

Encadré complet avec :

- **Bordure latérale gauche** de 4 px en vert sobre (`#1a7d3b`).
- **Bordure périphérique** légère (`#c8e6d2`).
- **Fond** très légèrement teinté (`#f4faf6`), suffisamment discret pour ne pas perturber la lecture.
- **Titre** : « Ressources partagées avec les apprenants » (couleur `#0f5223`).
- **Sous-titre explicite** : « Visibles dans l'extranet apprenant des participants inscrits à cette formation. »

Cette carte regroupe les deux contrôles partagés : Documents partagés + Liens partagés.

### 2. Carte « Documents internes ACDC » — accent rouge/ambre

Encadré complet avec :

- **Bordure latérale gauche** de 4 px en rouge sobre (`#c62828`).
- **Bordure périphérique** légère (`#f0c6c6`).
- **Fond** très légèrement teinté (`#fdf6f6`).
- **Titre** : « Documents internes ACDC » (couleur `#7a1a1a`).
- **Sous-titre explicite** : « Non visibles par les apprenants. Réservés à l'équipe pédagogique et administrative ACDC. »

Cette carte ne contient que le contrôle Documents internes.

### 3. Inversion de l'ordre input / liste

Demande utilisateur : le sélecteur de fichiers doit apparaître **avant** la liste des documents existants, pas après.

**Avant 3.20.78** : Liste des fichiers existants → input `<input type="file">` → texte d'aide.
**À partir de 3.20.78** : Input `<input type="file">` → texte d'aide → liste des fichiers existants.

Ce changement s'applique aux deux blocs Documents (partagés et internes). Le bloc Liens partagés conserve son ordre actuel (textarea de saisie en haut, aperçu cliquable en dessous), c'est cohérent avec la logique d'édition de texte.

### 4. Renommage cohérent des libellés

- « Documents internes (non partagés) » → « **Document(s) internes** » dans la carte (le « (non partagés) » devient redondant avec le titre de carte explicite).
- Le libellé « Document(s) partagé(s) » reste inchangé.

## Cohérence visuelle ACDC

Les couleurs ajoutées sont **subtiles** et restent dans l'esprit du cahier de style :

| Élément | Valeur | Justification |
|---|---|---|
| Vert principal | `#1a7d3b` | Vert sobre, déjà présent dans la palette de l'éditeur riche (3.20.74). Sémantique « ouvert / accessible / partage ». |
| Rouge principal | `#c62828` | Rouge atténué, déjà utilisé dans le label « Supprimer » de 3.20.77. Sémantique « réservé / fermé / attention ». |
| Bordure périphérique | Variantes très claires des couleurs ci-dessus | Discrétion maximale — les cartes ne crient pas. |
| Rayon de bordure | 10 px | Conforme au cahier de style ACDC. |
| Hauteur des champs / boutons | Inchangée | Pas de variation locale. |

**Aucun dégradé, aucune ombre, aucun effet visuel agressif.** Les cartes restent dans le ton sobre du reste du plugin.

## Engagement de préservation

- **Aucune modification de la logique métier.** La fonction `acdc_merge_doc_lists()` (3.20.77) est intacte.
- **Aucune modification du schéma BD.**
- **Aucune migration de données.** Tous les documents et liens existants sont préservés.
- **Aucun JavaScript modifié.**
- **Aucun fichier CSS centralisé modifié.** Les styles sont uniquement inline dans le formulaire formation, pour ne pas alourdir les feuilles de style globales avec des classes utilisées à un seul endroit. Si à terme ces cartes sont reproduites ailleurs, on extraira les styles dans un fichier CSS centralisé.
- **Aucune autre page touchée.**

Toutes les corrections 3.20.57 → 3.20.77 sont conservées.

## Risques de régression — analyse

| Scénario | Avant 3.20.78 | Après 3.20.78 |
|---|---|---|
| Affichage du formulaire formation | Trois zones uniformes empilées | Deux cartes distinctes (verte + rouge) |
| Risque de confusion partagé / interne | Réel | Quasi-nul |
| Logique upload, suppression, save | Identique (3.20.77) | Identique |
| Comportement extranet apprenant | Lit `shared_docs` et `shared_links` | Identique |
| Documents existants en BD | Préservés | Préservés |
| Mode Voir | Champs désactivés, listes affichées | Idem dans les nouvelles cartes |

Modification purement visuelle, aucun comportement métier ne change.

## Procédure de test

### Test 1 — Affichage des cartes

1. Purger LiteSpeed.
2. Installer 3.20.78. Purger à nouveau.
3. Aller sur Formations → Modifier une formation.
4. Vérifier que la section Bibliothèque affiche bien **deux cartes distinctes** :
   - Carte verte en haut : « Ressources partagées avec les apprenants ».
   - Carte rouge en bas : « Documents internes ACDC ».

### Test 2 — Inversion ordre input / liste

1. Sur une formation avec déjà des documents partagés.
2. Vérifier que dans la carte verte, l'input file (« Sélect. fichiers ») apparaît **au-dessus** de la liste des documents existants.
3. Idem dans la carte rouge pour les documents internes.

### Test 3 — Texte explicite anti-confusion

1. Vérifier le sous-titre de la carte verte : « Visibles dans l'extranet apprenant... ».
2. Vérifier le sous-titre de la carte rouge : « Non visibles par les apprenants. Réservés à l'équipe... ».

### Test 4 — Logique métier intacte

1. Uploader un fichier dans la carte verte → enregistrer → recharger → vérifier qu'il apparaît dans la liste de la carte verte uniquement.
2. Cocher Supprimer sur un document de la carte rouge → enregistrer → vérifier qu'il a disparu uniquement de la carte rouge.
3. Vérifier que les apprenants voient bien les fichiers de la carte verte dans leur extranet, et **jamais** ceux de la carte rouge.

### Test 5 — Mode Voir

1. Ouvrir une formation en consultation.
2. Vérifier que les deux cartes sont visibles avec leurs codes couleur, mais sans inputs file ni cases à cocher (lecture seule).

### Test 6 — Cohérence Créer / Voir / Modifier

1. Créer une nouvelle formation : les deux cartes doivent apparaître vides mais avec leurs encadrés et titres.
2. Modifier : avec les contenus existants.
3. Voir : en lecture seule.

## Choix d'implémentation — styles inline

Pour ce patch, les styles des cartes sont **inline** dans le PHP (attributs `style=`) plutôt que dans un fichier CSS dédié. Ce choix est délibéré pour cette livraison :

- Le composant n'est utilisé qu'à un seul endroit dans le plugin (formulaire formation).
- Pas d'ajout de fichier CSS supplémentaire à charger sur toutes les pages portail.
- Modification ciblée, lisible dans le contexte direct du formulaire.
- Si dans une version future ces cartes sont réutilisées ailleurs (ex. fiche apprenant pour les justificatifs partagés vs internes), les styles seront extraits dans un fichier CSS centralisé selon le principe de pilotage centralisé du système UI ACDC.

Cette approche reste cohérente avec votre règle absolue de système UI centralisé : on n'extrait dans le fichier global que ce qui est réellement transverse.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump `3.20.77` → `3.20.78`.
- `includes/kernel/class-acdc-kernel-render-trait.php` — refonte de la section Bibliothèque dans le formulaire formation : remplacement des trois zones plates par deux cartes encadrées + inversion de l'ordre input / liste.

**Aucun autre fichier modifié.**

## Si quelque chose ne va pas

Le retour à 3.20.77 est immédiat et sans risque. Modification purement visuelle, aucune dépendance dure créée.

## Suite logique

Si les cartes répondent à votre attente :

- **Section Paramètres de la formation** : neuf interrupteurs alignés verticalement, à possiblement regrouper par catégorie (suivi vs évaluation vs documents) si vous le souhaitez.
- **Section Catalogue** : champ Identifiant d'URL + Ordre + Image, à possiblement compléter avec d'autres méta-données SEO.
- **Section Brouillon** : un seul interrupteur, peut-être à fusionner avec le statut.
- **Application des cartes vert/rouge à d'autres modules** : Apprenants (justificatifs partagés vs internes), Entreprises (contrats partagés avec l'apprenant vs documents internes ACDC), etc.

Aucune de ces évolutions n'est livrée dans 3.20.78. Elles attendent votre validation explicite.
