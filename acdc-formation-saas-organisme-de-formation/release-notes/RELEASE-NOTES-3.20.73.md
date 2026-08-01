# ACDC Formation SAAS — version 3.20.73

## Objet

Ergonomie du formulaire de formation : suppression du bouton « Afficher le guide d'utilisation » sur cette page, regroupement de Description et Objectifs sur une même ligne, déplacement de Public cible depuis la section Catalogue vers Informations principales et regroupement avec Prérequis.

Ces modifications s'appliquent automatiquement aux trois pages **Créer une formation**, **Voir une formation** et **Modifier une formation** (formulaire partagé, comportement piloté par un flag `$is_view` qui désactive les champs en consultation).

## Modifications appliquées

### 1. Suppression du bouton « Afficher le guide d'utilisation »

Le bouton n'apparaît plus dans le titre « Informations principales » du formulaire de formation. Il a été supprimé uniquement sur cette page : la fonction `get_guide_button_html()` reste en place et ses deux autres utilisations dans le plugin (sur d'autres écrans) ne sont pas touchées.

### 2. Description + Objectifs sur la même ligne

Les deux textareas `description_text` et `objectives` étaient affichés en pleine largeur l'un sous l'autre. Ils sont désormais regroupés dans un `<div class="acdc-grid-2cols">`, à 50/50 sur la même ligne. Le rendu mobile reste géré par la classe utilitaire existante (qui retombe en 1 colonne sur petit écran).

### 3. Prérequis + Public cible sur la même ligne

- Le champ `prerequisites` a été extrait du grid principal qui contenait précédemment 7 champs (Prérequis, Format, Adresse, Ville, Code postal, Tarif, Durée).
- Le champ `catalog_audience` (Public cible) a été retiré de la section **Catalogue** et déplacé vers la section **Informations principales**.
- Les deux sont désormais regroupés dans un nouveau `<div class="acdc-grid-2cols">` immédiatement après Description/Objectifs.

### 4. Réorganisation propre du grid des champs administratifs

Le grid principal qui contient désormais Format, Adresse, Ville, Code postal, Tarif, Durée passe de **7 champs (4 lignes, dernière demi-vide)** à **6 champs (3 lignes pleines)**. Aucune demi-case orpheline.

### 5. Section Catalogue allégée

La section Catalogue ne contient plus le champ Public cible. Elle conserve :

- Toggle « Afficher sur le catalogue public »
- Identifiant d'URL / Ordre d'affichage (grid 2 cols)
- Image du catalogue
- Sessions à venir

## Engagement de préservation

- **Aucun champ supprimé en base.** La colonne `catalog_audience` reste présente dans `wp_acdc_of_formations`. Les valeurs déjà saisies sont préservées et restent éditables, simplement à un nouvel emplacement dans le formulaire.
- **Aucune logique métier modifiée.** Pas de modification de `handle_save_formation()`, pas de modification de `handle_duplicate_formation()`, pas de modification du tri.
- **Aucun CSS modifié.** Réutilisation des classes existantes `acdc-grid-2cols` et `acdc-formation-title-row`.
- **Aucun JavaScript modifié.**
- **Aucune autre page touchée.** Les autres formulaires (Apprenants, Entreprises, Sessions, Prospects, etc.) ne sont pas concernés.
- **Page Catalogue public en front non impactée.** Le champ `catalog_audience` reste lu et affiché par les templates publics si ces derniers l'utilisaient.

Toutes les corrections 3.20.57 → 3.20.72 sont conservées.

## Impact attendu sur les trois pages

| Page | Comportement |
|---|---|
| **Créer une formation** | Bouton guide supprimé. Description/Objectifs et Prérequis/Public cible groupés deux par deux. |
| **Modifier une formation** | Idem. Si une variante (ex. 1.1) est ouverte, son code variante est préservé (logique 3.20.71 conservée). |
| **Voir une formation** | Idem, en lecture seule (`disabled($is_view)` actif sur tous les champs). |

## Risques de régression

Quasi nuls.

| Scénario | Avant 3.20.73 | Après 3.20.73 |
|---|---|---|
| Création d'une nouvelle formation | Description/Objectifs en pleine largeur | Sur 2 colonnes |
| Édition d'une formation existante avec valeur Public cible déjà saisie | Champ visible dans la section Catalogue | Champ visible dans Informations principales, valeur préservée |
| Édition d'une formation sans valeur Public cible | Champ vide dans Catalogue | Champ vide dans Informations principales |
| Sauvegarde d'une formation avec Public cible rempli | `catalog_audience` enregistré en base | `catalog_audience` enregistré en base (logique de save inchangée) |
| Affichage du formulaire en mode Voir | Champs désactivés, bouton guide visible | Champs désactivés, bouton guide masqué |
| Catalogue public front | `catalog_audience` lu en base | `catalog_audience` lu en base (identique) |

Les valeurs `catalog_audience` saisies avant la 3.20.73 restent **disponibles, lisibles et modifiables**. Aucune migration de données n'est nécessaire.

## Procédure de test

1. Purger LiteSpeed (DB + objets + plugin).
2. Installer 3.20.73. Purger à nouveau.
3. **Test création** :
   - Aller sur Formations → Créer une formation.
   - Vérifier que le bouton « Afficher le guide d'utilisation » n'apparaît plus.
   - Vérifier que Description et Objectifs sont sur la même ligne (50/50).
   - Vérifier que Prérequis et Public cible sont sur la même ligne (50/50), juste en dessous.
   - Vérifier que Format, Adresse, Ville, Code postal, Tarif, Durée occupent 3 lignes pleines (pas de demi-case orpheline).
   - Descendre jusqu'à la section Catalogue : vérifier l'absence du champ Public cible.
4. **Test édition** :
   - Ouvrir une formation existante en modification.
   - Si elle avait une valeur Public cible saisie : la valeur doit apparaître dans le nouvel emplacement (Informations principales).
   - Modifier un autre champ, enregistrer. Rouvrir : la valeur Public cible doit toujours être présente.
5. **Test consultation (Voir)** :
   - Ouvrir une formation en mode Voir.
   - Mêmes regroupements visuels qu'en création/édition. Champs désactivés. Pas de bouton guide.
6. **Test catalogue public** (si utilisé) :
   - Vérifier que la page publique de la formation continue d'afficher Public cible si le template le mentionne.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump de version `3.20.72` → `3.20.73`.
- `includes/kernel/class-acdc-kernel-render-trait.php` — réorganisation du formulaire de formation (4 modifications structurelles dans la section Informations principales et la section Catalogue).

**Aucun autre fichier modifié.**

## Si quelque chose ne va pas

Le retour à 3.20.72 est immédiat et sans risque. Les modifications 3.20.73 sont purement présentationnelles côté formulaire — aucune dépendance dure créée. Toutes les valeurs en base restent intactes.

## Suite logique

Une fois cette ergonomie validée, on pourra continuer sur la page Formations selon votre plan :

- Affichage visuel du lien parent-enfant des variantes dans la liste (indentation, badge).
- Filtre pour masquer les variantes.
- Réorganisation éventuelle d'autres sections (Bibliothèque, Paramètres, Catalogue) si elles présentent des incohérences ergonomiques similaires.
- Autre page à traiter (Apprenants, Entreprises, Groupes, etc.).

Aucune de ces évolutions n'est livrée dans 3.20.73. Elles attendent votre validation explicite.
