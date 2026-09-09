# ACDC Formation SAAS — version 3.20.57

## Objet

Patch phase 1 « neutralisation » : rendre le back office UI maître réel des icônes d'action sur les pages Prospects et CRM, en supprimant les écrasements bloquants identifiés. Aucune valeur visuelle n'est définie en dur dans cette nouvelle version sur les sélecteurs touchés ; tout est désormais piloté par les variables CSS produites par `get_dynamic_css()`.

## Périmètre

Trois modifications strictement chirurgicales, dans cet ordre :

1. Suppression du bloc `:root` parasite dans `assets/css/acdc-components.css` (lignes 262-273 de la 3.20.56) qui imposait `--acdc-action-icon-box-size: 44px`, `--acdc-action-icon-glyph-size: 25px`, `--acdc-action-icon-color: #d6a353`, etc. Ces variables sont désormais fournies exclusivement par le back office.
2. Réécriture des trois blocs `<style>` du fichier `class-acdc-crm-commercial-render-trait.php` qui imprimaient en dur `width:18px`, `height:18px` et `color:#e9c77c` sur les classes `.acdc-prospect-icon-link`, `.acdc-prospect-action-trigger` et `.acdc-followup-actions .acdc-action-icon`. Remplacement par `var(--acdc-action-icon-frame-size)`, `var(--acdc-action-icon-glyph-size)`, `var(--acdc-action-icon-color)`. Substitution de `fill='#e9c77c'` par `fill='currentColor'` dans le SVG injecté en JavaScript pour que la couleur soit héritée du CSS du parent.
3. Réécriture de la fonction `acdcInjectGenericIconActionStyles()` dans `assets/js/admin.js` pour qu'elle injecte les variables CSS du back office au lieu des valeurs en dur `#E2B54B`, `#C99A2D` et `18px`.

## Variables CSS désormais maîtres pour les icônes d'action concernées

- `--acdc-action-icon-frame-size` : taille du bouton d'action (back office : « Taille du bouton d'action »).
- `--acdc-action-icon-glyph-size` : taille du pictogramme à l'intérieur (back office : « Taille du pictogramme d'action »).
- `--acdc-action-icon-color` : couleur de l'icône (back office : « Couleur actions »).
- `--acdc-action-icon-gap` : espace entre boutons (back office : « Espace entre actions »).
- Fallbacks introduits dans les sélecteurs hover de `acdc-components.css` : `--acdc-action-icon-bg-hover`, `--acdc-action-icon-border-hover`, `--acdc-action-icon-shadow-hover` pointent désormais sur les variables sœurs déjà fournies par le back office. Aucune valeur en dur introduite.

## Pages où les réglages du back office reprennent la main

- Liste des prospects (admin et front office) : icônes Voir, Modifier, Suivi commercial, Supprimer, menu Actions complémentaires.
- Suivi commercial (followup) : icônes des actions de relance et menu trois points.
- Tout container de table générique transformé par `acdcPatchGenericActionContainers` (icônes inline injectées par le moteur).
- Tout sélecteur consommant `var(--acdc-action-icon-*)` dans `acdc-components.css` (formations, learners, groupes, companies, actions de hub).

## Pages encore sous écrasement (à traiter en phase 2)

- Toggle des switches (`acdc-components.css` ligne 215) — couleur `#d6a353` en dur, hors périmètre phase 1.
- Hover du moteur 3.20.56 `.acdc-action-hub-btn` (`acdc-components.css` lignes 475-477) — couleur `#d6a353`, fond `#f7ead5`, texte `#6f4418` en dur. Sera la priorité numéro 1 de la phase 2.
- Fichiers CSS orphelins (jamais chargés par WordPress) : `tables.css`, `components.css` historique, `forms.css`, `layout.css`, `base.css`, `surveys.css`, `calendar.css`, `modal.css`. Leurs valeurs en dur (notamment `#8b5b23`, `25px`, `34px`) restent inertes mais devront être nettoyées en phase 2.
- Fonction PHP `render_inline_icon()` qui injecte encore `style="width:Xpx;height:Xpx"` inline avec des tailles `14`, `16`, `18`, `25`, `30`, `54` selon les appels — phase 3.
- Quatre moteurs JavaScript concurrents (`acdcDecorateActionControl`, `acdcPatchGenericActionContainers`, `acdcCreateIconLink`, `AcdcActionHub.normalize`) — phase 4.

## Compatibilité

- Aucun changement de slug, aucun changement de structure de base de données, aucune modification d'option enregistrée.
- Aucune modification fonctionnelle : les classes posées sur les éléments DOM sont identiques à la 3.20.56.
- Aucune régression attendue sur les pages où le rendu visuel était déjà piloté par les variables CSS (aperçu du système UI).

## Vérification recommandée

Sur staging, ouvrir successivement :

- Réglages → Système UI → Icônes : modifier « Taille du bouton d'action » de 24 vers 36, enregistrer.
- Liste des prospects (admin) : les boutons d'action doivent passer à 36 px.
- Suivi commercial : les boutons d'action de la table de suivi doivent passer à 36 px.
- Modifier « Couleur actions » dans le back office vers une couleur de test bien visible (par exemple rouge), enregistrer.
- Vérifier que les icônes des prospects et du suivi changent de couleur en conséquence.
- Modifier de nouveau « Couleur actions » vers la couleur de production souhaitée et enregistrer.

Si une page hors phase 1 ne répond pas (par exemple Apprenants, Entreprises, Sessions), c'est attendu : ces pages sont gérées par le moteur `AcdcActionHub` et le hover en dur, qui seront traités en phase 2.
