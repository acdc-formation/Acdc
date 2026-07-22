# ACDC Formation SAAS — version 3.20.67

## Objet

Patch complet apportant trois améliorations coordonnées au système de redimensionnement des colonnes : indicateur de largeur en temps réel pendant le drag, cadenas de verrouillage par tableau métier, et logs de diagnostic activables. Ces trois apports permettent de tester précisément le comportement des largeurs et de figer les réglages quand on en est satisfait.

## Apport n°1 — Indicateur de largeur en temps réel

**Problème** : aucune valeur ne s'affichait pendant le redimensionnement d'une colonne, ce qui rendait impossible de viser une largeur précise et empêchait tout test reproductible.

**Solution** : pendant le drag de la poignée, une étiquette flottante apparaît à proximité du curseur et affiche la largeur courante en pixels (par exemple « 287 px »). Elle se met à jour en temps réel à chaque mouvement et disparaît au relâchement.

L'étiquette est :
- **Bleu foncé sur fond blanc**, contrastée pour être lisible quel que soit le fond du tableau.
- **Bornée entre 56 et 600 px** (conforme aux limites côté serveur).
- **Hors flux** (`position: fixed`, `pointer-events: none`) pour ne perturber aucune interaction.

**Effet** : vous pouvez maintenant régler une colonne à 250 px précisément, puis vérifier qu'elle reste à 250 px après navigation. Le diagnostic des dérives devient possible.

## Apport n°2 — Cadenas de verrouillage des largeurs par tableau métier

**Implémentation conforme à vos quatre choix** :
1. Position : dans la barre de recherche/filtres au-dessus du tableau, à droite (détection automatique de `.acdc-search-row` ou `.acdc-list-toolbar`).
2. État par défaut : ouvert (modifiable).
3. Portée : par tableau métier (chaque tableau a son propre verrou, identifié par sa clé canonique 3.20.65).
4. Stockage : nouvelle option WordPress `acdc_of_table_locks`, sauvegarde côté serveur via une fonction AJAX dédiée et nonce séparé.

**Fonctionnement** :
- Cadenas **ouvert** : icône en couleur primaire sur fond blanc, les poignées de redimensionnement sont visibles, les colonnes peuvent être ajustées.
- Cadenas **fermé** (clic) : icône blanche sur fond primaire, les poignées disparaissent, le `mousedown` sur l'emplacement des poignées ne fait plus rien. Aucune modification accidentelle possible.
- Au rechargement, l'état est restauré depuis le serveur.
- Si la barre de recherche n'est pas trouvée, repli automatique : un wrapper aligné à droite est inséré au-dessus du tableau.

**Tooltip dynamique** :
- Ouvert : « Largeurs de colonnes modifiables (cliquer pour verrouiller) »
- Fermé : « Largeurs de colonnes verrouillées (cliquer pour déverrouiller) »

## Apport n°3 — Logs de diagnostic activables

**Problème** : la dérive des largeurs entre tableaux après navigation (Prospects → Sessions → retour Prospects = largeurs Prospects altérées) reste à diagnostiquer. Sans visibilité sur ce que fait le code en arrière-plan, je ne peux pas trancher entre les hypothèses.

**Solution** : ajout d'un système de logs activables à la demande, totalement silencieux par défaut.

**Activation** : dans la console JavaScript du navigateur, taper :
```javascript
window.AcdcUiKernelDebug = true;
```

**Désactivation** :
```javascript
window.AcdcUiKernelDebug = false;
```

**Ce que les logs tracent** :
- À chaque initialisation de tableau : la clé canonique utilisée, les largeurs lues depuis la sauvegarde, le nombre de colonnes détectées.
- À chaque sauvegarde côté serveur : la clé du tableau, les valeurs envoyées, la pile d'appel pour identifier l'origine (drag manuel ou autre).

**Procédure de diagnostic** que vous pouvez exécuter avec ces logs activés :
1. Activer les logs.
2. Aller sur Prospects, régler une colonne à 250 px précisément (l'indicateur permet de viser).
3. Rechercher dans la console les lignes `[ACDC] saveServer` : il doit y en avoir **une seule**.
4. Naviguer sur Sessions. Rechercher de nouveaux logs `saveServer` : il **ne doit pas** y en avoir contenant la clé Prospects.
5. Régler une colonne sur Sessions. Vérifier qu'aucun `saveServer` Prospects n'apparaît.
6. Revenir sur Prospects. Vérifier que `[ACDC] initTable` montre bien 250 px sur la colonne réglée.

Si à une étape un log inattendu apparaît, c'est la cause exacte de la dérive — et je peux la corriger précisément en 3.20.68.

## Engagement de préservation

**Aucune fonction métier touchée. Aucune logique de sauvegarde existante modifiée** (la 3.20.66 a déjà corrigé la cause racine du bug `array_merge`).

Cette version :
- Ajoute du code (indicateur, cadenas, logs) sans toucher au code existant.
- Conserve toutes les corrections 3.20.57 → 3.20.66.
- Ne modifie pas le format de stockage des largeurs.
- Le verrou est purement défensif côté UI : il bloque le drag, mais n'empêche pas une modification programmatique via la console (utile si vous avez besoin de débloquer manuellement).

## Compatibilité

- Aucun changement de slug.
- Aucun changement de structure de données existantes.
- Une nouvelle option `acdc_of_table_locks` est créée à la première sauvegarde de verrou.
- Les utilisateurs non connectés ne voient pas le cadenas (aligné sur la logique existante de `canSaveGlobal`).

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — version 3.20.67.
- `assets/js/acdc-ui-kernel.js` — indicateur de largeur, système de verrouillage, logs.
- `includes/class-acdc-plugin.php` — fonction AJAX `ajax_save_table_lock`, ajout de `tableLocks` et `lockNonce` dans la localization.
- `assets/css/acdc-components.css` — styles du cadenas (bouton, état verrouillé, repli).

**Aucun autre fichier modifié.**

## Vérification recommandée

1. Purger le cache LiteSpeed.
2. Installer la 3.20.67.
3. Recharger en mode privé.
4. Aller sur la liste Prospects.
5. **Vérifier** : un cadenas ouvert apparaît à droite de la barre « Rechercher ».
6. Glisser une poignée de colonne. **Vérifier** : une étiquette « XXX px » suit le curseur en temps réel.
7. Cliquer sur le cadenas. **Vérifier** : il devient fermé (fond primaire), les poignées disparaissent.
8. Tenter de glisser à l'emplacement d'une poignée. **Vérifier** : rien ne se passe.
9. Re-cliquer sur le cadenas. **Vérifier** : il s'ouvre, les poignées réapparaissent, le redimensionnement remarche.
10. Recharger la page. **Vérifier** : l'état du cadenas est conservé (ouvert ou fermé selon votre dernier clic).
11. Activer les logs : `window.AcdcUiKernelDebug = true;`
12. Refaire votre test Prospects → Sessions → retour Prospects, en surveillant la console pour trancher la cause de la dérive.

## Prochaine étape (3.20.68 si nécessaire)

Si les logs activés révèlent la cause de la dérive des largeurs entre tableaux, je livrerai une 3.20.68 ciblée qui règle précisément ce point. Si en revanche aucune dérive n'apparaît dans vos tests avec l'indicateur précis et les logs, c'est probablement que le bug `array_merge` (corrigé en 3.20.66) était la cause unique et que tout est désormais stable.

Si tout fonctionne, on pourra retirer les logs en 3.20.68 ou les laisser dormants (ils sont silencieux par défaut, donc aucune raison de se presser).
