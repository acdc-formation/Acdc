# ACDC Formation SAAS — version 3.20.85

## Objet

**Hotfix : la page `/extranet-formateur/` utilise désormais le template "blank" épuré** (sans header WordPress, sans titre de page injecté par le thème, sans footer thème, sans menu).

C'était la dernière brique manquante après le 3.20.84 (CSS portail).

## Diagnostic

Sur la page apprenant, vous aviez constaté un rendu **complètement épuré** : juste la carte au centre, fond gris clair, rien d'autre. Sur la page formateur, vous voyiez tout le décorum du thème Twenty Twenty-Five (menu en haut, titre « Extranet formateur » en gros, footer avec les liens « Blog », « À propos », « Évènements », etc.).

Le plugin contient un système de **template blank** (`templates/acdc-blank-template.php`) qui prend le contrôle du rendu de la page et supprime le header et le footer du thème. Ce template est appliqué via le filtre `template_include` à toutes les pages dont l'ID figure dans la liste blanche `is_acdc_front_page()` (la connexion gestion, le portail, le catalogue, la page apprenant, etc.).

L'option `acdc_of_trainer_portal_page_id` (créée par le 3.20.83) **n'avait pas été ajoutée** à cette liste blanche. Conséquence : la page formateur passait au template par défaut du thème, avec tout le décorum.

## Modification

Une seule ligne dans `class-acdc-kernel-core-trait.php` : ajout de `'acdc_of_trainer_portal_page_id'` au tableau `$keys` de la fonction `is_acdc_front_page()`.

## Effet

| Élément | Avant 3.20.85 | Après 3.20.85 |
|---|---|---|
| Menu WordPress en haut de page | Visible | **Caché** |
| Titre « Extranet formateur » en gros (h1 du thème) | Visible | **Caché** |
| Footer thème (Blog, À propos, Boutique, Compositions, Thèmes, etc.) | Visible | **Caché** |
| Mention « Twenty Twenty-Five » et « Conçu avec WordPress » | Visible | **Caché** |
| Carte centrée avec logo et formulaire | OK | OK (inchangé) |

Le rendu sera désormais **strictement identique** à la page apprenant que vous m'avez envoyée en capture 4.

La barre admin WordPress en haut (« Bonjour, David Contal », « Modifier le site », etc.) restera visible **uniquement quand vous êtes vous-même connecté en admin** — c'est un comportement WordPress, pas du plugin. Vos formateurs (qui ne sont pas connectés à WordPress puisque l'auth est 100 % custom) ne la verront jamais.

## Cohérence

Aucune autre modification. Aucune migration BD. Aucun changement fonctionnel. Pure correction d'oubli.

## Procédure de test

1. Purger LiteSpeed (DB + objets + plugin).
2. Téléverser le ZIP, remplacer.
3. Purger à nouveau.
4. **Forcer le rechargement** : `Cmd + Shift + R` (Mac) ou `Ctrl + Shift + R` (Windows).
5. **En navigation privée** (donc sans barre admin) — visiter `/extranet-formateur/?view=activate&token=...` ou simplement `/extranet-formateur/`.
6. **Vérifier** : page totalement épurée, juste la carte centrée sur fond gris, comme sur votre capture 4 (apprenant).

Si vous testez en mode admin (capture 1/2/3 du précédent test), la **barre admin WordPress noire en haut** restera visible — c'est attendu et normal. Tout le reste du décorum (titre h1, menu, footer) doit avoir disparu.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump 3.20.84 → 3.20.85.
- `includes/kernel/class-acdc-kernel-core-trait.php` — ajout de `acdc_of_trainer_portal_page_id` au tableau `$keys` de `is_acdc_front_page()` (ligne 1568).

## Suite

Quand le visuel est OK chez vous, on enchaîne sur le **3.20.86** : **Bibliothèque personnelle du formateur** — première vraie zone de contenu du portail. Côté formateur (visible dans son extranet) et côté admin (visible sur sa fiche formateur) : CV, diplômes, attestations URSSAF (avec date d'expiration), RC pro (avec date d'expiration), certifications, mandats, autres pièces.

Avec gestion des dates d'expiration en perspective d'un futur module d'alertes Qualiopi.
