# ACDC Formation SAAS — version 3.20.60

## Objet

**Étape 1 du plan « AcdcActionHub source de vérité unique »** : rendre le moteur JavaScript AcdcActionHub pilotable par le back office UI. Cette version pose les fondations sans rien désactiver — elle ajoute des capacités au moteur tout en préservant intégralement son comportement antérieur en cas de défaillance de la nouvelle plomberie.

## Vision long terme

Le plugin contient actuellement plusieurs systèmes de rendu d'icônes qui se concurrencent. AcdcActionHub est le plus récent et le mieux conçu, mais il avait jusqu'ici ses propres SVG codés en dur, indépendants des choix du back office. Le plan en plusieurs étapes consiste à :

1. **Étape 1 (cette version)** — Rendre AcdcActionHub pilotable par le back office.
2. **Étape 2 (à venir)** — Réintégrer les Prospects dans AcdcActionHub en supprimant leurs scripts dédiés.
3. **Étape 3 (à venir)** — Désactiver les autres moteurs JS résiduels (`acdcDecorateActionControl`, `acdcPatchGenericActionContainers`, `acdcCreateIconLink`, `acdcInjectGenericIconActionStyles`, `setIconStyles`).
4. **Étape 4 (à venir)** — Nettoyage CSS final, suppression des blocs dédiés Prospects, audit `render_inline_icon`.

À l'issue de ce plan, le plugin disposera d'un système d'icônes unifié, piloté en un seul point, robuste pour les évolutions futures.

## Modifications de cette version (étape 1)

### 1. Nouvelle méthode PHP `get_action_hub_config()`

Ajoutée dans `includes/kernel/class-acdc-kernel-core-trait.php` juste après `get_nav_icon_svg()`. Cette méthode :

- lit les options du back office (`action_icon_view`, `action_icon_edit`, `action_icon_delete`, `action_icon_more`, `action_icon_followup`, `action_icon_archive`, `action_icon_duplicate`) ;
- construit un mapping type-interne → nom-de-glyphe choisi par l'utilisateur ;
- attache une bibliothèque complète des SVG disponibles (40 glyphes), tirée de `get_nav_icon_svg()` pour rester cohérente avec le rendu PHP.

### 2. Injection de la config en JavaScript

Aux deux endroits où `admin.js` est enqueued (front et admin), ajout d'un `wp_localize_script( 'acdc-of-admin', 'ACDC_ACTION_HUB_CONFIG', $this->get_action_hub_config() )`. L'objet `window.ACDC_ACTION_HUB_CONFIG` est désormais disponible dans le contexte JavaScript.

### 3. Nouvelle fonction JavaScript `resolveSvg(type)`

Ajoutée dans `assets/js/admin.js` juste avant `iconize()`. Logique de résolution :

- si `window.ACDC_ACTION_HUB_CONFIG` est présent et contient le type demandé, utilise le SVG de l'utilisateur ;
- sinon, retombe sur le SVG en dur de la table `ICONS` (ancien comportement).

Cette résolution garantit que toute défaillance de la nouvelle plomberie (config absente, type inconnu, glyphe invalide) ne casse rien : le moteur continue de fonctionner exactement comme avant.

La fonction `iconize()` appelle désormais `resolveSvg(type)` au lieu de lire directement `ICONS[type]`.

### 4. Réordonnancement de `actionType()` pour corriger l'anomalie Suivi commercial

L'ancienne version testait le mot « view » avant les tests sémantiques précis (followup, need, quote, etc.). Conséquence : un bouton « Suivi commercial » dont l'URL contient `?action=view` était classé en `view` et affichait l'œil au lieu du presse-papier.

La nouvelle version :

- exécute d'abord `delete` et `edit` (mots clairs) ;
- puis les **tests sémantiques précis** sur le label uniquement (`followup`, `need`, `quote`, `contract`, `signup`, `calendar`) ;
- ensuite seulement le test `view` qui est plus générique ;
- enfin une seconde passe avec des filets de sécurité sur le haystack complet (label + URL + classe) pour les cas où le label seul ne suffit pas.

Le test `followup` reconnaît maintenant les variantes : `relancer`, `relance`, `follow`, `suivi commercial`, `suivi-commercial`, et le mot `suivi` isolé.

## Effet attendu après installation

Sur les écrans déjà gérés par AcdcActionHub (Apprenants, Entreprises, Sessions, Formations, Groupes, etc.), les choix du back office UI deviennent **réellement effectifs** :

- changer « Voir = Document » dans le back office → tous les boutons Voir affichent un document ;
- changer « Suivi commercial = Calendrier » → les boutons de suivi commercial affichent un calendrier ;
- les valeurs par défaut sont identiques à celles affichées avant cette mise à jour, donc **aucune régression visuelle** sans modification du back office.

## Limites connues de cette étape

Cette version traite uniquement les écrans **déjà gérés par AcdcActionHub**. Ne sont pas encore affectés :

- **Page Prospects** : reste exclue (correctifs 3.20.59 conservés). Ses scripts dédiés tournent encore avec leurs SVG en dur. Sera traitée à l'**étape 2**.
- **Pages encore décorées par des moteurs concurrents** (`acdcDecorateActionControl` etc.) : seront traitées aux **étapes 3 et 4**.

## Compatibilité

- Aucun changement de slug, aucun changement de structure de base de données.
- Aucune option modifiée, aucune option supprimée.
- Le comportement par défaut sans réglage utilisateur est identique à la 3.20.59 : les valeurs par défaut de la config (`'eye'`, `'edit-pencil'`, `'trash-bin'`, etc.) reproduisent fidèlement les SVG de la table `ICONS`.
- En cas d'échec d'injection de la config (cache JS périmé, plugin de minification qui casserait `wp_localize_script`), le moteur retombe sur l'ancien comportement sans faille.

## Vérification recommandée

Sur staging, en partant de la 3.20.59 fonctionnelle :

1. Installer la 3.20.60.
2. **Sans toucher au back office**, ouvrir une liste représentative (Apprenants, Sessions, Entreprises). Vérifier que le rendu visuel est **identique** à la 3.20.59. Cette étape valide la non-régression.
3. Ouvrir Réglages → Système UI → Icônes → Choix des pictogrammes d'action.
4. Modifier « Suivi commercial » de « Presse-papier » vers « Calendrier ». Enregistrer.
5. Sur Apprenants ou tout écran AcdcActionHub avec un bouton de suivi commercial, vérifier que l'icône a effectivement changé pour un calendrier.
6. Modifier « Voir » vers « Document ». Vérifier le changement sur les boutons Voir.
7. Modifier « Modifier » vers un autre glyphe. Vérifier.
8. Restaurer les valeurs souhaitées.
9. **Important pour cette étape** : la page Prospects ne réagira **pas** à ces changements (rappel : elle est exclue d'AcdcActionHub depuis la 3.20.59). Ce sera traité à l'étape 2.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — numéro de version (3.20.60).
- `includes/kernel/class-acdc-kernel-core-trait.php` — nouvelle méthode `get_action_hub_config()`.
- `includes/kernel/class-acdc-kernel-render-trait.php` — appel `wp_localize_script` aux deux enqueues d'`admin.js`.
- `assets/js/admin.js` — fonction `resolveSvg()`, modification de `iconize()`, réordonnancement de `actionType()`.

**Aucun CSS modifié.** **Aucune logique métier touchée.** **Aucun fichier supprimé.**

## Prochaine étape

Une fois cette version validée sur staging, **étape 2** : intégration des Prospects dans AcdcActionHub. Cela impliquera de supprimer les exclusions ajoutées en 3.20.59 et de désactiver les deux scripts JavaScript dédiés du module CRM (lignes 480-651 et 1642-1894 du fichier de rendu). C'est l'opération la plus délicate du plan car elle touche à un module fonctionnel actif. Elle sera donc accompagnée d'une vérification très précise sur staging avant tout déploiement en production.
