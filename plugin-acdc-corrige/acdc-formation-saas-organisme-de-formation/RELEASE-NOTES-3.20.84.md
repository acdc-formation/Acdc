# ACDC Formation SAAS — version 3.20.84

## Objet

**Hotfix CSS du portail formateur livré en 3.20.83.**

La page d'activation et de connexion `/extranet-formateur/` s'affichait sans encadrement (logo en pleine largeur, champs nus, pas de carte blanche centrée). Cause : le CSS `learner-portal.css` qui contient les classes `.acdc-portal-shell`, `.acdc-login-card`, `.acdc-login-branding`, `.acdc-form` était chargé conditionnellement uniquement si la page contenait un shortcode apprenant. Le shortcode formateur n'était pas dans la liste blanche.

## Modification

Une seule ligne dans `class-acdc-kernel-render-trait.php` : ajout du shortcode `acdc_trainer_portal_login` et de l'option `acdc_of_trainer_portal_page_id` à la condition d'enqueue des CSS portail.

Concrètement, sur la page `/extranet-formateur/`, sont maintenant chargés :

- `frontend.css` (variables CSS et reset de base)
- `acdc-ui-system.css` (système UI ACDC)
- `acdc-components.css` (composants partagés)
- `learner-portal.css` (cartes de connexion, formulaires login, dashboard)

Plus le JS `acdc-ui-kernel.js` pour cohérence comportementale.

## Effet visuel attendu

| Élément | Avant | Après |
|---|---|---|
| Logo | Pleine largeur, géant | Centré dans une carte blanche, taille raisonnable |
| Carte blanche | Absente | Présente avec ombre douce |
| Champs de mot de passe | Sans bordures | Bordures arrondies, fond blanc, hauteur cohérente |
| Bouton « Activer mon accès » | Texte simple | Bouton or doré pleine largeur, dégradé ACDC |
| Titre « Espace formateur » | Aligné à gauche, sous-titre absent | Centré sous le logo avec sous-titre |
| Mise en page globale | Fluide gauche | Carte centrée sur fond gris clair |

Le rendu sera **strictement identique** à la capture de l'apprenant que vous m'avez envoyée.

## À propos du titre WordPress « Extranet formateur »

Le gros titre « Extranet formateur » que vous voyez au-dessus de la carte sur votre capture 2 est le titre WordPress standard injecté par votre thème (Twenty Twenty-Five). Il apparaîtra aussi sur la page apprenant **quand vous êtes connecté en admin**. Votre capture 1 a probablement été prise en navigation privée, sans barre admin ni titre de page, ce qui explique pourquoi vous ne le voyiez pas.

C'est un comportement de thème, pas du plugin. Si vous souhaitez le masquer pour les deux portails (apprenant et formateur), c'est une intervention au niveau du thème (CSS du type `.page-id-XXX .wp-block-post-title { display:none; }`), à arbitrer dans un patch ultérieur si vous le voulez.

## Cohérence

Aucun autre changement. Aucune migration BD. Aucune modification fonctionnelle.

## Procédure de test

1. Purger LiteSpeed (cache plugin + objets).
2. Téléverser le ZIP, remplacer.
3. Purger à nouveau.
4. Visiter `/extranet-formateur/?view=activate&token=...` (utiliser le lien de votre dernier e-mail d'invitation, ou réinviter votre faux formateur depuis sa fiche admin).
5. **Vérifier le visuel** : carte blanche centrée, logo encadré et de taille raisonnable, champs avec bordures arrondies, bouton or doré « Activer mon accès » en pleine largeur. Identique à votre capture 1.
6. Tester ensuite la connexion classique sur `/extranet-formateur/` : même rendu, formulaire e-mail + mot de passe, lien « Mot de passe oublié » en bas.

## Si le rendu n'est toujours pas correct

Suspecter un cache navigateur. Forcer le rechargement avec `Ctrl + Shift + R` (Windows) ou `Cmd + Shift + R` (Mac). Si LiteSpeed agrège les CSS, vider également **Optimisation → Purger tout** côté plugin LiteSpeed.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump 3.20.83 → 3.20.84.
- `includes/kernel/class-acdc-kernel-render-trait.php` — ajout du shortcode `acdc_trainer_portal_login` et de l'option `acdc_of_trainer_portal_page_id` à la condition d'enqueue CSS (ligne 64).

## Suite

Hotfix terminé. Quand le visuel est OK chez vous, on enchaîne sur le 3.20.85 (ex-3.20.84) : **Bibliothèque personnelle** du formateur (CV, diplômes, attestations URSSAF, RC pro, dates d'expiration).
