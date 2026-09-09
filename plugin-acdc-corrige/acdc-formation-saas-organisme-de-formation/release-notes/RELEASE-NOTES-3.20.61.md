# ACDC Formation SAAS — version 3.20.61

## Objet

Approche **non-destructive** pour rendre les Prospects pilotables par le back office UI : au lieu de désactiver les scripts JavaScript dédiés du module CRM (qui portent aussi des fonctions métier critiques), je les **modifie** pour qu'ils consomment la même configuration `ACDC_ACTION_HUB_CONFIG` que le moteur AcdcActionHub. Aucune fonction n'est désactivée, aucune logique n'est touchée.

## Engagement de préservation

Cette version **ne touche pas** aux fonctions métier suivantes :

- Le dropdown 6 entrées du menu trois points Prospects (Répliquer, Ajouter un rendez-vous, Recueil des besoins, Devis, Convention/contrat, Inscrire en formation).
- La modale rapide RDV.
- L'édition inline du statut et de l'attribution des prospects.
- Les écouteurs de clic, les fermetures sur scroll/resize, le `closeAll` global.
- Les fonctions `upgradeLegacyProspectActions`, `buildActionCell`, `buildMenuUrls`, `bindMenu`, `patchProspectsTables`, `patchRdvModal`.
- Le script `acdc-prospect-inline-updates-script` dans son intégralité.

Le périmètre de modification est strictement limité à **deux fonctions de rendu de SVG** et **trois blocs CSS d'apparence**.

## Modifications

### Modification A — Fonction `iconMarkup` du premier script Prospects

Cette fonction (ligne 482 du fichier CRM, dans le `<script>` du footer) générait des SVG en dur pour les types `more`, `view`, `edit`, `phone`, `trash` et retournait une chaîne vide pour `clipboard`. Elle est désormais étendue avec :

- Une table de correspondance `PROSPECT_TYPE_TO_HUB` qui mappe les types Prospects vers les types AcdcActionHub : `view→view`, `edit→edit`, `clipboard→followup`, `trash→delete`, `more→more`.
- Une fonction utilitaire `configuredSvg()` qui consulte `window.ACDC_ACTION_HUB_CONFIG` et retourne le SVG choisi par l'utilisateur, ou `null` si la config n'est pas disponible.
- En tête de `iconMarkup`, un appel `var fromConfig = configuredSvg(type)` ; si la config fournit un SVG, on l'utilise.
- Sinon, on retombe **intégralement** sur les SVG en dur d'origine. Aucun comportement n'est perdu.
- Ajout d'un cas `clipboard` dans le fallback en dur (pour le cas où la config est absente et que le bouton Suivi commercial est rendu) — il affichera un presse-papier au lieu d'une chaîne vide.

### Modification B — Fonction `icon` du second script Prospects

Cette fonction (ligne 1676, dans le `<script id="acdc-prospect-ui-patch-script">`) génère les SVG utilisés par `buildActionCell`. Même approche que la modification A :

- Même table `PROSPECT_TYPE_TO_HUB`.
- Même fonction `configuredSvg`.
- En tête de `icon`, consultation de la config avant tout fallback.
- Si la config retourne un SVG, on l'utilise. Sinon on tombe sur les SVG en dur historiques (qui contenaient déjà clipboard).

### Modification C — Réintroduction du cercle visuel sur les boutons Prospects

Les trois blocs CSS dédiés Prospects imposaient `border:none !important` et `background:transparent !important`, ce qui supprimait le cercle visuel présent sur les autres pages (où AcdcActionHub pose `border` et `border-radius` via `acdc-components.css`). Modification de ces trois blocs pour qu'ils consomment les variables back office :

- `border:none` devient `border:1px solid var(--acdc-action-icon-border, transparent)`.
- `background:transparent` devient `background:var(--acdc-action-icon-bg, transparent)`.
- Ajout de `border-radius:var(--acdc-action-icon-radius, 50%)`.
- Le hover ajoute désormais le fond et la bordure de survol via les variables `--acdc-action-icon-bg-hover` et `--acdc-action-icon-border-hover`.

Les valeurs par défaut des fallbacks (`transparent`, `50%`) sont choisies pour que **sans configuration back office active**, le rendu reste visuellement proche de l'ancien (pas de cercle visible si les variables ne sont pas définies, mais pas de cassure non plus).

## Effet attendu après installation

Sur la liste des Prospects, et seulement là (les autres pages étaient déjà gérées en 3.20.60) :

- **L'icône Suivi commercial** suit désormais votre choix dans Réglages → Système UI → Icônes → « Suivi commercial ». Si vous mettez « Calendrier », un calendrier s'affiche.
- **Le menu trois points** suit le choix « Menu 3 points » du back office.
- Les icônes Voir, Modifier, Supprimer suivent leurs réglages respectifs.
- Les boutons Prospects ont **le cercle** comme sur les autres pages, avec couleur de bordure et fond pilotés par le back office.
- Au survol, le fond et la bordure passent à la couleur configurée pour le hover.

## Compatibilité

- Aucun changement de slug, aucun changement de structure de base de données.
- Aucune option modifiée, aucune option supprimée.
- **Toutes les fonctions métier Prospects sont préservées** : dropdown 6 entrées, modale RDV, édition inline, fermetures de menu, etc.
- Si pour une raison quelconque la config `ACDC_ACTION_HUB_CONFIG` n'est pas disponible (cache JS périmé, plugin de minification, JS bloqué), les fonctions retombent sur leurs SVG en dur historiques — exactement le comportement de la 3.20.60.

## Vérification recommandée

Sur staging, en partant d'une 3.20.60 fonctionnelle :

1. Installer la 3.20.61.
2. **Sans toucher au back office**, ouvrir la liste des Prospects. Vérifier :
   - Les boutons d'action ont désormais un cercle (avec la bordure et le fond actuels du back office par défaut).
   - L'icône Suivi commercial est un presse-papier (et plus un œil ni rien).
   - Le menu trois points est horizontal.
3. **Tester le dropdown 6 entrées** sur le menu trois points : Répliquer, Ajouter un rendez-vous, Recueil des besoins, Devis, Convention/contrat, Inscrire en formation. Toutes les entrées doivent s'ouvrir correctement.
4. **Tester l'ajout de rendez-vous** : la modale RDV doit s'ouvrir, les champs doivent fonctionner, l'enregistrement doit marcher.
5. **Tester l'édition inline** du statut prospect et de l'attribution.
6. **Tester** Voir, Modifier, Supprimer (avec la confirmation Supprimer).
7. Aller dans Réglages → Système UI → Icônes → Choix des pictogrammes d'action.
8. Modifier « Suivi commercial » de « Presse-papier » vers « Calendrier ». Enregistrer.
9. Retourner sur la liste Prospects. Vérifier que la 3e icône affiche maintenant un calendrier.
10. Modifier « Menu 3 points » vers une autre forme. Vérifier sur Prospects.
11. Restaurer les valeurs souhaitées.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — version 3.20.61.
- `includes/crm-commercial/class-acdc-crm-commercial-render-trait.php` — extension de `iconMarkup` et `icon`, mise à jour de 3 blocs CSS.

**Aucun autre fichier modifié.** Aucun script désactivé. Aucun handler retiré.

## Si quelque chose ne va pas

Le retour à la 3.20.60 est immédiat — un seul ZIP à réinstaller. Le filet de sécurité dans `configuredSvg()` garantit que toute défaillance de la config fait retomber les fonctions sur leur comportement antérieur.

## Prochaine étape logique

Une fois la 3.20.61 validée, l'unification des icônes pilotables par le back office est **complète** sur tous les écrans du plugin. Les chantiers suivants pourront s'attaquer au nettoyage technique :

- audit des 200 appels `render_inline_icon()` côté PHP pour libérer leurs tailles ;
- désactivation progressive des moteurs JS résiduels (`acdcDecorateActionControl`, `acdcPatchGenericActionContainers`, `acdcCreateIconLink`) ;
- nettoyage final des CSS orphelins.

Mais ce sont des chantiers de **nettoyage technique**, pas des chantiers fonctionnels. L'objectif premier — back office maître réel des icônes — est atteint avec cette version.
