# ACDC Formation SAAS — version 3.20.88

## Objet

**Mini-patch UI : portail formateur en plein écran.**

Le portail authentifié (dashboard + bibliothèque) était contraint dans un conteneur de 1100 px maximum, centré, avec marges latérales sur grand écran. Vous l'avez signalé : ça gaspille l'espace disponible.

Désormais le portail occupe **toute la largeur de la fenêtre**, avec un padding latéral généreux pour la respiration.

## Modifications

### 1. Largeur du portail

| Élément | Avant | Après |
|---|---|---|
| Largeur du conteneur principal | `max-width: 1100px` centré | **100 % de la fenêtre** |
| Padding latéral desktop | 16 px | 32 px (respiration confortable) |
| Padding latéral mobile (< 880 px) | 16 px | 16 px (inchangé) |
| Padding intérieur du body | 22 px | 28 px |

### 2. Grille des cartes Bibliothèque

J'en ai profité pour adapter la grille des cartes maintenant qu'il y a beaucoup plus de place :

| Largeur d'écran | Avant | Après |
|---|---|---|
| > 1280 px (desktop large) | 2 colonnes | **3 colonnes** |
| 880 → 1280 px (laptop) | 2 colonnes | 2 colonnes |
| < 760 px (mobile) | 1 colonne | 1 colonne |

Sur un écran 1920 × 1080 standard, vous verrez désormais **les 8 catégories en 3 colonnes** (3 + 3 + 2), avec largement la place pour afficher les libellés longs des documents sans coupure.

## Cohérence

- Aucune modification fonctionnelle. Pure adaptation de mise en page.
- Le formulaire d'ajout, les badges, les boutons, les couleurs : strictement inchangés.
- La page de connexion (anonyme) reste centrée dans sa carte de 380 px — comme l'apprenant. Seul l'**état authentifié** passe en plein écran.

## Procédure de test

1. Purger LiteSpeed (DB + objets + plugin), déployer le ZIP, purger encore, `Cmd + Shift + R`.
2. Se connecter à `/extranet-formateur/` avec votre compte test.
3. Vérifier le **dashboard** : occupe toute la largeur, plus de marges blanches énormes à droite et à gauche.
4. Aller sur **Ma bibliothèque** : grille en 3 colonnes sur écran large (> 1280 px), 2 colonnes sur laptop standard, 1 colonne sur mobile.
5. Redimensionner la fenêtre du navigateur pour vérifier les breakpoints (1280 px et 760 px).

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump 3.20.87 → 3.20.88.
- `includes/trainer-portal/render/class-acdc-trainer-portal-render-trait.php` :
  - `.acdc-tportal-shell` : suppression de `max-width: 1100px; margin: 0 auto;` → `width:100%; max-width:none; margin:0;`. Padding 24 × 32 px desktop.
  - `.acdc-tdoc-grid` : 3 colonnes par défaut, 2 sous 1280 px, 1 sous 760 px.

## Suite

Quand le visuel plein écran est OK chez vous, on peut enchaîner sur le 3.20.89 — au choix :

1. **Mon profil** (édition par le formateur lui-même)
2. **Mes sessions** (liste sessions + apprenants)
3. **Alertes Qualiopi automatiques** (e-mails J-30 / J-7 expirations)

À votre choix.
