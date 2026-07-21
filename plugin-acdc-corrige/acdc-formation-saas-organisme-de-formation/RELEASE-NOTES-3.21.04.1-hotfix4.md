# 3.21.04.1-hotfix4 — Refonte esthétique sectionnée style legacy

**Date :** 27 avril 2026
**Type :** refonte visuelle de la livraison hotfix3.

---

## Constat

Le rendu visuel des écrans Résultats livré en 3.21.04.1 et conservé en hotfix1/2/3 était fonctionnel mais nu : tableaux directement dans le flux de la page, pas de containers, pas d'identité visuelle propre. À côté du module legacy questionnaires qui a une esthétique sectionnée affirmée (sections beige crème, conteneurs blancs internes, séparateurs verticaux dorés, cadenas discret), le contraste était frappant.

## Ce qui change

**Refonte du langage visuel des sections résultats**, calé sur l'esthétique du module legacy questionnaires :

- **Sections** avec fond beige `#fbf8f7`, bordure `#f0e6dc`, coins arrondis 12px, padding généreux
- **Cadenas SVG décoratif** en haut à droite (signal « données protégées »)
- **Conteneur interne blanc** qui héberge le tableau, coins arrondis 10px
- **Header de tableau** avec fond doré clair `#fbf2e3`, texte en bleu marine `#0f2c52` UPPERCASE
- **Séparateurs verticaux dorés** entre colonnes (1px `#d6a353`, 50% de hauteur centrée)
- **Lignes alternées** au survol avec hover beige clair

Cinq écrans concernés :

1. **Liste des envois** (`?tab=qz_results_*`) : la section « Liste des envois » est désormais wrappée
2. **Détail envoi — onglet Participants** : section « Liste des participants »
3. **Détail envoi — onglet Par question** : section « Détail par question » + nouvelle sous-section
4. **Détail envoi — onglet Par objectif** : section « Atteinte par objectif pédagogique »
5. **Détail individuel d'un apprenant** : section « Détail des réponses »

## Bonus métier — « Questions les plus faibles »

Sur l'onglet « Par question », ajout d'une **sous-section « Questions les plus faibles »** qui affiche le top 3 des questions au taux de réussite le plus bas. Inspiration directe de la capture legacy. Valeur métier : permet de repérer immédiatement ce qui ne passe pas pour déclencher une action pédagogique (revoir un point en cours, ajuster le quiz, identifier un objectif mal formulé).

La sous-section ne s'affiche que s'il y a au moins une question scorée. Pas de pollution visuelle quand le quiz est uniquement constitué de réponses libres ou de sondages.

## Architecture technique

**Helper centralisé** : `qz_section_lock_svg()` produit le SVG du cadenas. Réutilisé dans toutes les sections. Si tu veux changer l'icône, c'est en un seul endroit.

**Classes CSS introduites** :
- `.acdc-qz-results-section` — wrapper section beige
- `.acdc-qz-results-section-lock` — cadenas en position absolue
- `.acdc-qz-results-section-header` — header avec h2 et subtitle
- `.acdc-qz-results-section-subtitle` — sous-titre gris
- `.acdc-qz-results-section-inner` — conteneur blanc interne

**Refonte de** `.acdc-qz-results-table` et `.acdc-qz-results-tabs` pour adopter le nouveau style. Les anciennes définitions en doublon dans le CSS ont été nettoyées — le test renforcé vérifie qu'il ne reste qu'une seule définition de `.acdc-qz-results-table thead th`.

## Compatibilité

- Aucune migration BDD
- Aucune modification des méthodes Core ni des handlers admin-post
- Aucune modification du portail formateur (qui pourra adopter le même style en cohérence si tu le souhaites — à voir en hotfix5 ou plus tard)
- Toutes les fonctionnalités préservées : correction manuelle des réponses libres, export CSV, KPI cards en haut, filtres « Statut », filtre « Finalité » sur l'onglet transverse

## Points de vigilance

- Le cache LiteSpeed agrège souvent le CSS — vide-le impérativement après installation, sinon tu verras peut-être un mélange ancien/nouveau style
- L'esthétique du portail formateur n'a **pas** été refondue dans ce hotfix. Côté formateur, les sections gardent le rendu plus minimal. Si tu veux que le formateur ait le même rendu, dis-le et on fait un hotfix5 dédié

## Recette

1. Vider le cache LiteSpeed
2. Aller sur l'extranet ACDC en mode admin → onglet « Résultats — Évaluations »
3. Vérifier le **rendu de la liste** : section beige avec cadenas, tableau dans conteneur blanc, séparateurs verticaux dorés entre colonnes
4. Cliquer sur « Voir → » d'un envoi
5. Onglet **Participants** : même esthétique de section
6. Onglet **Par question** : section principale + sous-section « Questions les plus faibles » (si questions scorées)
7. Onglet **Par objectif pédagogique** : section avec titre Qualiopi
8. Cliquer sur « Détail » d'un apprenant : section « Détail des réponses » avec ses questions
