# ACDC Formation SAAS — version 3.20.74

## Objet

Mise en place d'un **éditeur de texte enrichi centralisé**, réutilisable dans tout le plugin via une fonction PHP unique. Application immédiate aux quatre champs de la fiche Formation (Description, Objectifs, Prérequis, Public cible) avec le profil le plus complet (`full`).

Ce composant est conçu pour servir de fondation à toute la communication enrichie du plugin (e-mails marketing, modèles d'e-mails, contenus catalogue, scénarios automatiques, etc.). Aucune dépendance externe : moteur maison basé sur l'API DOM standard du navigateur, sans TinyMCE, sans Quill, sans bibliothèque tierce.

## Architecture posée

### Composant centralisé

Une seule fonction PHP : `render_acdc_rich_editor( $name, $value, $args )`, définie dans le kernel. Tout le plugin consomme cette fonction.

### Profils prédéfinis

Quatre profils paramétrables centralement :

| Profil | Boutons | Cas d'usage |
|---|---|---|
| **`minimal`** | Gras, italique, lien, listes | Champs courts (notes, commentaires) |
| **`standard`** | + souligné, barré, citation, code, indentation, undo/redo | Pages internes |
| **`full`** | Tout : sélecteur de paragraphe, taille de police au pixel, couleurs, alignements, plein écran, mode code, aide | Description, Objectifs, Prérequis, Public cible (3.20.74) — futurs modules de communication |
| **`mail`** | Identique à `full` sauf l'upload d'image (limites des clients e-mail) | Module Communication à venir |

Modifier un profil = modifier l'éditeur partout dans le plugin où ce profil est utilisé.

### Distribution

Trois nouveaux fichiers, tous dans le kernel :

- `assets/css/acdc-rich-editor.css` (482 lignes) — feuille de style centralisée
- `assets/js/acdc-rich-editor.js` (815 lignes) — moteur d'édition
- Fonction PHP `render_acdc_rich_editor()` dans `class-acdc-kernel-render-trait.php`

Les assets sont enregistrés dans `enqueue_front_assets()` à la suite des assets existants, en dépendance d'`acdc-of-ui-system`. Aucun chargement supplémentaire n'est imposé sur les pages WordPress qui n'utilisent pas l'éditeur — c'est conditionné aux pages portail.

## Fonctionnalités du profil `full`

### Format de paragraphe

Sélecteur déroulant : Paragraphe / Titre 2 / Titre 3 / Titre 4 / Préformaté.

### Mise en forme inline

Gras, Italique, Souligné, Barré.

### Couleurs

- **Couleur du texte** : palette de 24 teintes alignées sur l'identité ACDC.
- **Surlignage** : même palette pour le fond.

### Taille de police

- **Sélecteur prédéfini** : 10, 12, 14, 16, 18, 20, 24, 28, 32, 36, 48 px.
- **Champ libre** : saisie au pixel près, validée entre 6 et 200 px.

### Insertion

- **Lien** : modale propre (URL + texte affiché + ouvrir dans un nouvel onglet) — pas un `prompt()` natif. Validation des schémas autorisés (`http(s)`, `mailto`, `tel`, ancres internes). Auto-préfixe en `https://` si l'utilisateur oublie le protocole.
- **Image** : modale (URL + texte alternatif). Note : l'**upload de fichier sera ajouté dans une version ultérieure** ; pour l'instant, l'utilisateur indique l'URL d'une image hébergée.
- **Caractère spécial** : modale avec grille de ~80 caractères courants (lettres accentuées, symboles, flèches, ponctuation typographique, devises).
- **Ligne horizontale** (`<hr>`).

### Listes & alignement

Liste à puces, liste numérotée, alignement (gauche, centre, droite, justifié), augmenter/diminuer le retrait. Indentation gère les listes imbriquées (Tab/Shift+Tab dans une liste).

### Mise en forme bloc

Citation, code inline, bloc de code.

### Édition

Annuler / Refaire (pile d'historique, jusqu'à 50 niveaux), effacer le formatage.

### Vues

- **Visuel / Code** : bascule entre l'édition WYSIWYG et l'édition du HTML brut.
- **Plein écran** : éditeur fixé sur tout l'écran (Échap pour sortir).

### Aide

Modale listant tous les raccourcis clavier.

### Raccourcis clavier supportés

`Ctrl+B`, `Ctrl+I`, `Ctrl+U`, `Ctrl+K` (lien), `Ctrl+Z`, `Ctrl+Shift+Z`, `Tab`/`Shift+Tab` dans une liste, `Échap` pour sortir du plein écran.

### Collage propre

Au coller depuis Word, Google Docs, ou une page web, le contenu est nettoyé automatiquement : balises non sûres (`<script>`, `<style>`, `<iframe>`, `<form>`...) supprimées, attributs dangereux (`on*=`, `class`, `id`, `data-*` non ACDC) retirés. Seules les propriétés CSS utiles sont conservées (`font-size`, `color`, `background-color`, `text-align`, `font-weight`, `font-style`, `text-decoration`, `margin-left`).

## Sécurité

- **`wp_kses_post`** appliqué à tous les `_POST` des 4 champs au save (`handle_save_formation`).
- **`wp_kses_post`** appliqué à l'affichage dans la fiche (mode contenteditable et mode catalogue public).
- **Filtre `safe_style_css`** étendu pour autoriser explicitement `font-size`, `color`, `background-color`, `text-align`, `font-weight`, `font-style`, `text-decoration`, `margin-left`. Les autres propriétés CSS restent filtrées par défaut WordPress.
- **Schémas de liens** : seuls `http://`, `https://`, `mailto:`, `tel:`, ancres internes et chemins relatifs sont acceptés. Auto-préfixe en `https://` si rien n'est fourni.
- **Pas de `document.execCommand`** : API obsolète et imprévisible, remplacée par manipulation DOM contrôlée via Selection/Range.

## Cohérence visuelle ACDC

Toutes les valeurs visuelles de l'éditeur viennent du système global :

| Élément | Valeur |
|---|---|
| Couleur primaire (boutons actifs, focus, accents) | `#8b5b23` |
| Hauteur des boutons toolbar | 36 px (cohérent avec compactage toolbar) |
| Hauteur des champs/boutons de modale | 40 px |
| Rayon de bordure | 10 px (8 px sur les boutons toolbar, 6 px sur sous-éléments) |
| Bordure inactive | `#d9dfe8` |
| Police | Rubik (héritée du système) |
| Icônes SVG inline | currentColor + 18 px (taille adaptée au bouton 36 px) |

Aucune valeur en dur localement.

## Conversion du champ Prérequis

Le champ `prerequisites` était un `<input type="text">` (une seule ligne, texte brut). Il devient un éditeur riche multiligne, identique aux trois autres. Comportement attendu et validé.

## Adaptations transverses (5 endroits)

Pour préserver la cohérence d'affichage des données dans tout le plugin :

| Fichier | Ligne | Ancien | Nouveau |
|---|---|---|---|
| `dossiers-contracts/class-acdc-dossiers-contracts-core-trait.php` | 1593 | `$formation->objectives ?? ''` | `wp_strip_all_tags( (string) ( $formation->objectives ?? '' ) )` |
| `settings-catalog/class-acdc-settings-catalog-render-trait.php` | 807 | `wpautop( esc_html( $formation->description_text ) )` | `wp_kses_post( wpautop( (string) $formation->description_text ) )` |
| `settings-catalog/class-acdc-settings-catalog-render-trait.php` | 812 | `wpautop( esc_html( $formation->objectives ) )` | `wp_kses_post( wpautop( (string) $formation->objectives ) )` |
| `settings-catalog/class-acdc-settings-catalog-render-trait.php` | 817 | `wpautop( esc_html( $formation->catalog_audience ) )` | `wp_kses_post( wpautop( (string) $formation->catalog_audience ) )` |
| `settings-catalog/class-acdc-settings-catalog-render-trait.php` | 822 | `wpautop( esc_html( $formation->prerequisites ) )` | `wp_kses_post( wpautop( (string) $formation->prerequisites ) )` |

Ces adaptations garantissent :

1. **Catalogue public** affiche correctement la mise en forme (gras, listes, etc.) saisie via l'éditeur.
2. **Contrats / conventions** restent en texte brut (pas de balises HTML qui apparaîtraient dans un PDF), grâce au `wp_strip_all_tags` au préremplissage.
3. **Portail apprenant** (déjà strippé en amont, lignes 352 et 355) continue d'afficher du texte simple — comportement inchangé.

## Engagement de préservation

- **Aucune table modifiée**, aucune colonne ajoutée. Les données existantes (texte brut) restent compatibles.
- **Aucune migration nécessaire**. Les valeurs existantes (sans HTML) s'affichent normalement dans l'éditeur ; l'utilisateur peut les enrichir progressivement à mesure qu'il rééditera ses formations.
- **Aucune modification de la logique métier** (duplication de formation, save, validation, sessions, etc.).
- **Aucune autre page touchée** par les changements visibles. Les autres formulaires du plugin continuent d'utiliser les `<textarea>` standards.
- **Le système d'éditeur léger existant** (`acdc-mini-editor-toolbar` dans le CRM commercial) reste en place et fonctionnel. Il sera migré vers `acdc-rich-editor` dans une version future si besoin.

Toutes les corrections 3.20.57 → 3.20.73 sont conservées.

## Risques de régression — tableau d'analyse

| Scénario | Avant 3.20.74 | Après 3.20.74 |
|---|---|---|
| Création d'une formation | 4 textareas/input simples | 4 éditeurs riches profil `full` |
| Édition d'une formation existante (texte brut) | Affichage du texte | Affichage du texte dans l'éditeur, prêt à être enrichi |
| Édition d'une variante (1.1, 1.2…) | Code variante préservé | Code variante toujours préservé (logique 3.20.71 conservée) |
| Sauvegarde d'une formation avec mise en forme | (inexistant) | HTML léger filtré par `wp_kses_post` |
| Affichage en mode Voir | Champs désactivés | Toolbar masquée, contenu en lecture seule, fond grisé |
| Catalogue public | Texte échappé via esc_html (les balises auraient été visibles) | HTML rendu correctement via `wp_kses_post( wpautop( ... ) )` |
| Contrat / convention auto-rempli depuis la formation | `objectives_text` strippé au préremplissage | Identique + fallback objectifs aussi strippé |
| Portail apprenant (texte affiché) | Strippé (texte brut) | Strippé (texte brut) — identique |
| Recherche dans Formations (`title LIKE`, `code LIKE`, etc.) | Match sur texte brut | Match sur HTML — peut donner des faux positifs sur balises (ex. recherche "strong"). Dans la pratique, peu impactant. |

## Procédure de test

### Test 1 — Apparition de la toolbar

1. Purger LiteSpeed (DB + objets + plugin).
2. Installer 3.20.74. Purger à nouveau.
3. Aller sur Formations → Créer une formation.
4. Vérifier que les 4 champs (Description, Objectifs, Prérequis, Public cible) affichent une **toolbar de boutons** au-dessus de la zone de saisie.
5. Vérifier que la toolbar contient : sélecteur de paragraphe, B/I/U/S, couleur, surlignage, taille, lien, image, caractère spécial, hr, listes, alignements, indentation, citation, code, undo/redo, clear, vue/code, plein écran, aide.

### Test 2 — Mise en forme basique

1. Saisir du texte. Sélectionner un mot. Cliquer sur **Gras** → vérifier qu'il devient gras.
2. Idem avec italique, souligné, barré.
3. Sélectionner du texte. Cliquer sur la palette **Couleur** → choisir une teinte → vérifier que le texte change de couleur.
4. Idem pour le surlignage.

### Test 3 — Taille de police au pixel

1. Sélectionner un mot. Cliquer sur **Taille de police**.
2. Choisir une taille préset (ex. 24 px) → vérifier le rendu.
3. Resélectionner. Ouvrir Taille → taper `23` dans le champ libre → cliquer OK → vérifier que le texte est en 23 px.

### Test 4 — Sélecteur de paragraphe

1. Cliquer dans une ligne. Choisir **Titre 2** dans le sélecteur → vérifier le rendu en gros titre.
2. Choisir **Préformaté** → rendu en monospace.
3. Choisir **Paragraphe** → retour au texte normal.

### Test 5 — Lien

1. Sélectionner du texte.
2. Cliquer sur **Lien** (ou Ctrl+K).
3. Modale : taper l'URL `acdc-formation.com` → laisser **Ouvrir dans un nouvel onglet** coché → cliquer Insérer.
4. Vérifier que l'URL est auto-préfixée en `https://acdc-formation.com`.
5. Vérifier que le lien est cliquable (Ctrl+clic) et s'ouvre dans un nouvel onglet.

### Test 6 — Listes et indentation

1. Cliquer sur **Liste à puces** → un `<li>` apparaît.
2. Saisir 3 lignes.
3. Sur la 2ᵉ ligne, taper **Tab** → la ligne s'indente sous la 1ʳᵉ.
4. Taper **Shift+Tab** → désindente.

### Test 7 — Plein écran et mode code

1. Cliquer sur **Plein écran** → l'éditeur prend tout l'écran.
2. Taper Échap → retour à la normale.
3. Cliquer sur **Visuel / Code** → la zone passe en HTML brut sur fond sombre.
4. Modifier le HTML → rebascule en Visuel → vérifier que les modifications sont prises en compte.

### Test 8 — Sauvegarde et persistance

1. Saisir du contenu enrichi dans Description (gras, listes, lien).
2. Cliquer **Créer une formation** (ou Modifier).
3. Recharger la page d'édition.
4. Vérifier que la mise en forme est **préservée à l'identique**.

### Test 9 — Mode Voir

1. Aller sur la liste Formations → cliquer sur l'œil (Voir).
2. Vérifier que la toolbar est **masquée** sur les 4 éditeurs.
3. Vérifier que le contenu apparaît avec sa mise en forme mais **non éditable** (fond grisé).

### Test 10 — Catalogue public

1. Si la formation est marquée "Afficher sur le catalogue public", aller sur la page publique de cette formation.
2. Vérifier que la mise en forme (gras, listes, etc.) saisie dans l'éditeur s'affiche correctement, **et non comme du HTML brut**.

### Test 11 — Contrat (PDF / convention)

1. Créer un dossier de contrat à partir d'une formation dont les objectifs ont du HTML.
2. Vérifier que les balises HTML **ne sont pas visibles** dans le contrat (PDF ou texte) — les objectifs apparaissent en texte brut.

### Test 12 — Régression (formations existantes)

1. Ouvrir une formation antérieure à 3.20.74 (texte brut).
2. Vérifier qu'elle s'affiche normalement, sans balises visibles.
3. Enregistrer sans modification → relire → vérifier l'absence d'altération.

## Fichiers ajoutés

- `assets/css/acdc-rich-editor.css` — feuille de style du composant éditeur (482 lignes).
- `assets/js/acdc-rich-editor.js` — moteur JavaScript du composant éditeur (815 lignes).
- `RELEASE-NOTES-3.20.74.md` — ce document.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump `3.20.73` → `3.20.74`.
- `includes/class-acdc-plugin.php` — ajout du filtre `safe_style_css`.
- `includes/kernel/class-acdc-kernel-render-trait.php` — ajout fonction `render_acdc_rich_editor()`, fonction `extend_safe_style_css()`, enqueue des nouveaux assets, intégration aux 4 champs Formation.
- `includes/kernel/class-acdc-kernel-actions-trait.php` — bascule de `sanitize_textarea_field` / `sanitize_text_field` vers `wp_kses_post` pour les 4 champs Formation.
- `includes/dossiers-contracts/class-acdc-dossiers-contracts-core-trait.php` — strip HTML dans le fallback objectifs (ligne 1593).
- `includes/settings-catalog/class-acdc-settings-catalog-render-trait.php` — bascule de `esc_html` vers `wp_kses_post` pour les 4 affichages catalogue public.

**Aucun autre fichier modifié.**

## Si quelque chose ne va pas

Le retour à 3.20.73 est immédiat et sans risque. Aucune dépendance dure créée :

- Les colonnes `description_text`, `objectives`, `prerequisites`, `catalog_audience` n'ont pas changé en BD — elles restent en LONGTEXT/TEXT.
- Les données saisies en HTML léger restent **lisibles en texte** si on revient à 3.20.73 (les balises HTML apparaissent comme du texte, mais aucune perte d'information).
- Les filtres `safe_style_css` et `extend_safe_style_css` sont retirés en revenant à 3.20.73 — sans incidence.

## Suite logique — ce que cette fondation permet

Le composant `render_acdc_rich_editor` est désormais réutilisable partout. Quelques candidats naturels pour les patches suivants :

- **Module Communication** : profil `mail` pour les e-mails marketing, modèles d'e-mails, scénarios automatiques.
- **Recueil des besoins** : profil `standard` pour les zones de notes/commentaires.
- **Notes prospects et suivi commercial** : profil `minimal` pour les commentaires courts.
- **Conditions générales et mentions** : profil `full` pour les contenus légaux à éditer dans les réglages.
- **Programme de formation détaillé** : si vous voulez le saisir directement dans le plugin plutôt que via un fichier joint.
- **Fonctionnalité d'upload d'image** : à brancher dans le bouton Image existant (modale déjà prête, juste un endpoint AJAX à ajouter avec gestion permissions/nonce).

Aucune de ces évolutions n'est livrée dans 3.20.74. Elles attendent votre validation explicite.

## Note sur l'écosystème — pour information

Le moteur JS de l'éditeur est encapsulé dans une IIFE (`(function(){})()`) qui expose `window.ACDCRichEditor` avec deux méthodes : `init(node)` et `initAll()`. Cette interface permet, dans un patch futur, d'instancier un éditeur après le DOMContentLoaded (par exemple dans une modale qui se charge dynamiquement).

Le `data-acdc-rich-init="1"` empêche les doubles initialisations, ce qui rend l'auto-init idempotente.
