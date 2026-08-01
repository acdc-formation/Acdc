# ACDC Formation SAAS — version 3.20.58

## Objet

Patch phase 2 « bascule en variables » : faire du back office UI le maître réel des icônes d'action sur tous les écrans de listes, en remplaçant les valeurs en dur (tailles, couleurs) des fichiers CSS par les variables produites par `get_dynamic_css()`. Cette version étend la phase 1 (limitée aux Prospects et au Suivi commercial) à toutes les pages du plugin reposant sur les classes standards d'actions.

## Périmètre étendu

La phase 1 (3.20.57) avait neutralisé trois écrasements ciblés (Prospects, Suivi commercial, moteur générique JS). La phase 2 généralise ce traitement aux fichiers CSS qui imposaient encore des valeurs en dur sur les boutons d'action de listes :

- `assets/css/acdc-components.css` — moteur `AcdcActionHub` (rendu unifié des actions de liste 3.20.56) : tailles `34px`, glyphes `20px`, couleurs `#8b5b23`, `#e8d8bd`, `#fffaf2`, `#f7ead5`, `#d6a353`, `#6f4418` au repos et au survol → bascule sur `--acdc-action-icon-frame-size`, `--acdc-action-icon-glyph-size`, `--acdc-action-icon-color`, `--acdc-action-icon-bg`, `--acdc-action-icon-border`, et leurs variantes hover.
- `assets/css/acdc-components.css` — toggles `.acdc-switch-slider` (trois occurrences) : couleurs `#d6a353` / `#d8dee7` → bascule sur `--acdc-toggle-active` / `--acdc-toggle-inactive`.
- `assets/css/acdc-ui-system.css` — toggles d'aperçu `.acdc-preview-toggle.is-on/is-off` → bascule sur les mêmes variables toggle.
- `assets/css/frontend.css` — `.acdc-prospect-patch-actions` (taille `18px`, couleur `#e9c77c`) → variables back office.
- `assets/css/tables.css` — neuf blocs traités : conteneurs `.acdc-row-action-icon`, `.acdc-row-view-link`, `.acdc-row-edit-link`, `.acdc-row-delete-link`, `.acdc-row-menu-toggle`, `.acdc-row-menu-button`, `.acdc-table-action-trigger` ; bloc d'industrialisation 3.18.52 ; `.acdc-learners-actions-cell`, `.acdc-learners-actions-inline`, `.acdc-groups-actions-inline`, `.acdc-companies-actions-inline` ; `.acdc-formations-actions`. Au total 57 substitutions sur ce seul fichier.
- `assets/css/tables.css` — icône Qualiopi `.acdc-qualiopi-icon` : taille basculée sur `--acdc-icon-size` (champ « Taille globale » du back office), couleurs sémantiques verte / rouge conservées intentionnellement.

## Variables CSS désormais maîtres après la phase 2

Sur tous les boutons d'action de listes, à travers toutes les pages :

- `--acdc-action-icon-frame-size` — taille du bouton (champ back office « Taille du bouton d'action »).
- `--acdc-action-icon-glyph-size` — taille du pictogramme (champ back office « Taille du pictogramme d'action »).
- `--acdc-action-icon-color` — couleur de l'icône au repos et au survol (champ back office « Couleur actions »).
- `--acdc-action-icon-bg` — fond du bouton (champ back office « Fond actions »).
- `--acdc-action-icon-border` — bordure (champ back office « Bordure actions »).
- `--acdc-action-icon-bg-hover` — fond au survol (champ back office « Fond actions au survol »).
- `--acdc-action-icon-gap` — espace entre boutons (champ back office « Espace entre actions »).
- `--acdc-action-icon-radius` — arrondi (champ back office « Arrondi boutons d'action »).
- `--acdc-toggle-active` / `--acdc-toggle-inactive` — couleurs des toggles (champs back office « Couleur toggle actif / inactif »).
- `--acdc-icon-size` — taille de l'icône Qualiopi et autres icônes globales (champ back office « Taille globale »).

## Pages où les réglages prennent effet après cette phase

- Prospects, Suivi commercial (déjà couverts en phase 1, intégrité conservée).
- Apprenants, Groupes, Entreprises, Formations, Sessions, Devis, Conventions, Inscriptions.
- Tous les écrans utilisant les classes d'actions standards `.acdc-row-action-icon`, `.acdc-row-view-link`, `.acdc-row-edit-link`, `.acdc-row-delete-link`, `.acdc-row-menu-toggle`, `.acdc-row-menu-button`, `.acdc-table-action-trigger`.
- Tous les écrans utilisant le moteur `AcdcActionHub` (3.20.56) qui pose la classe `.acdc-action-hub-btn`.
- Toggles dans tout le plugin (réglages, formulaires, modules de configuration).
- Aperçu live du système UI lui-même.

## Décisions de périmètre explicites (préservations volontaires)

Trois choix méritent d'être documentés car ils dérogent au principe « tout en variables » pour de bonnes raisons :

- **Variantes sémantiques `acdc-action-hub-btn--delete`, `--validate`, `--cancel`** : leurs couleurs (rouge `#9f2f25`, vert `#2f6f46`, brun `#8a4a17`) ont été **conservées en dur**. Elles relèvent de l'ergonomie universelle des SaaS (rouge = supprimer, vert = valider) et ne sont pas couvertes par les champs actuels du back office. Une phase ultérieure pourra ajouter trois nouveaux champs « Couleur Supprimer », « Couleur Valider », « Couleur Annuler » si besoin.
- **Icône Qualiopi `.acdc-qualiopi-icon-ok` / `-ko`** : couleurs verte `#1f9d55` et rouge `#b42318` conservées car porteuses de sens fonctionnel (conformité Qualiopi). Seule la taille bascule sur les variables.
- **Lien Prérequis `formation-prereq-trigger`** : couleur `#d6ad6b` conservée. Ce n'est pas une icône d'action mais un lien textuel cliquable, hors périmètre strict de la phase 2.

## Pages encore en attente de la phase 3

- Fonction PHP `render_inline_icon($icon, $size)` qui injecte encore `style="width:Xpx;height:Xpx"` inline avec des tailles `14`, `16`, `18`, `25`, `30`, `54` selon les contextes (200 appels). Ces styles inline sont actuellement **battus par les `!important` des CSS** sur les boutons d'action de listes, donc le système fonctionne — mais il reste fragile et incohérent pour les icônes décoratives hors actions de liste (sections, badges, hero illustrations).
- Quatre moteurs JavaScript concurrents (`acdcDecorateActionControl`, `acdcPatchGenericActionContainers`, `acdcCreateIconLink`, `AcdcActionHub.normalize`) — phase 4.

## Compatibilité

- Aucun changement de slug, aucun changement de structure de base de données, aucune option modifiée.
- Aucune modification fonctionnelle ni de classe CSS posée par le PHP ou le JavaScript.
- Le rendu visuel par défaut (sans modification du back office) reste **strictement identique** à la 3.20.57 grâce aux fallbacks `var(--acdc-action-icon-*, valeur d'origine)`. Tous les sélecteurs conservent comme valeur de secours la valeur exacte qui existait en dur avant la modification.

## Vérification recommandée

Sur staging, en partant de la 3.20.57 fonctionnelle :

1. Installer la 3.20.58.
2. Sans toucher au back office, ouvrir une liste représentative (Prospects, Apprenants, Entreprises, Formations) : le rendu doit être **identique** à la 3.20.57.
3. Aller dans Réglages → Système UI → Icônes.
4. Modifier « Taille du bouton d'action » de la valeur actuelle vers 36, enregistrer.
5. Ouvrir successivement les listes Prospects, Apprenants, Entreprises, Groupes, Formations, Sessions : tous les boutons d'action doivent passer à 36 px.
6. Modifier « Couleur actions » vers une couleur de test (rouge), enregistrer. Vérifier l'application sur les mêmes pages.
7. Modifier « Couleur toggle actif » vers une couleur de test, enregistrer. Vérifier sur un écran contenant un toggle.
8. Vérifier que les boutons sémantiques (Supprimer en rouge, Valider en vert) **conservent** leurs couleurs spécifiques — c'est volontaire.
9. Vérifier que les indicateurs Qualiopi (vert OK, rouge KO) **conservent** leurs couleurs — c'est volontaire.
10. Remettre les valeurs souhaitées dans le back office.

Si une page reste insensible aux changements du back office, c'est qu'elle dépend encore de `render_inline_icon` ou d'un moteur JS — ce sera la phase 3.
