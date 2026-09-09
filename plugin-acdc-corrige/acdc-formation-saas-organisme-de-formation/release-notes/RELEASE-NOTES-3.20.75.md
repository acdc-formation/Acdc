# ACDC Formation SAAS — version 3.20.75

## Objet

Correction d'une erreur HTML introduite en 3.20.74 qui décalait visuellement les 4 éditeurs riches dans le formulaire de formation. Les labels apparaissaient en haut, mais leurs éditeurs descendaient sur des rangées suivantes au lieu de s'aligner avec les labels.

## Diagnostic

Dans le formulaire de formation, les 4 éditeurs riches étaient placés à l'intérieur de balises `<p>` :

```html
<p>
  <label>Description</label>
  <div class="acdc-rich-editor">...</div>
</p>
```

**En HTML, un `<p>` ne peut pas contenir un élément de type bloc (`<div>`).** La spécification HTML 5 force la fermeture automatique du `<p>` dès qu'un élément block-level apparaît. Le navigateur transforme silencieusement la structure en :

```html
<p><label>Description</label></p>
<div class="acdc-rich-editor">...</div>
<p></p>
```

Conséquence dans une grille `acdc-grid-2cols` :

- Le `<p>` ne contenant que le `<label>` occupe une cellule du grid.
- Le `<div>` éditeur sort du grid et redescend dans le flux normal.
- Tout se décale : labels collés en haut, éditeurs flottants en dessous.

C'est exactement ce qui apparaissait sur les captures de test : Description et Prérequis avec leurs labels en haut, leurs éditeurs poussés sous les éditeurs de droite (Objectifs et Public cible).

## Correction

Remplacement des `<p>` qui enveloppaient les 4 éditeurs riches par des `<div>` :

```html
<div>
  <label>Description</label>
  <div class="acdc-rich-editor">...</div>
</div>
```

Un `<div>` peut légitimement contenir un autre `<div>`. Le grid `acdc-grid-2cols` reçoit deux enfants block-level cohérents et les place côte à côte comme attendu.

## Étendue de la modification

Une seule zone modifiée : les 8 occurrences de `<p>` autour des éditeurs riches dans la section Informations principales du formulaire formation, dans `class-acdc-kernel-render-trait.php`.

Les autres `<p>` du formulaire (qui contiennent des `<input>`, `<select>`, ou `<textarea>` simples — éléments inline, donc valides dans un `<p>`) **ne sont pas touchés**. Aucun changement sur les autres champs.

## Engagement de préservation

- **Aucun CSS modifié.**
- **Aucun JavaScript modifié.**
- **Aucune logique métier modifiée.**
- **Aucune autre page touchée.**
- Le composant `render_acdc_rich_editor` lui-même n'est pas modifié : il continue à produire le même HTML interne.
- Toutes les corrections 3.20.57 → 3.20.74 sont conservées.

## Risques de régression

Nuls.

| Scénario | Avant 3.20.75 | Après 3.20.75 |
|---|---|---|
| Création d'une formation | Décalage visuel des éditeurs | Alignement correct |
| Modification d'une formation | Décalage visuel | Alignement correct |
| Consultation (Voir) | Décalage visuel | Alignement correct |
| Sauvegarde | Identique (le HTML produit côté serveur reste valide) | Identique |
| Catalogue public | Non concerné | Non concerné |

Le contenu HTML stocké en base, la sanitization, la logique de save sont **strictement identiques** à 3.20.74.

## Procédure de test

1. Purger LiteSpeed.
2. Installer 3.20.75.
3. Aller sur Formations → Créer une formation (ou ouvrir une formation en édition).
4. Vérifier que :
   - **Description** (à gauche) et **Objectifs** (à droite) sont alignés horizontalement, avec leurs labels au même niveau et leurs éditeurs au même niveau.
   - **Prérequis** (à gauche) et **Public cible** (à droite) sont alignés horizontalement, idem.
   - Aucun éditeur n'apparaît "flottant" sous d'autres éditeurs.
5. Saisir un peu de contenu, enregistrer, recharger → vérifier la persistance.

## Note technique pour la documentation interne

**Règle à intégrer dans le document `REGLE-EDITEUR-TEXTE-ENRICHI.md` :**

> Lors de l'intégration de `render_acdc_rich_editor()` dans un formulaire, **ne jamais l'envelopper dans un `<p>`**. Utiliser un `<div>`. Le composant produit du HTML block-level (toolbar, zone d'édition, textarea hidden, code area) qui est invalide à l'intérieur d'un paragraphe et provoque une fermeture automatique du `<p>` par le navigateur, avec les conséquences de mise en page que cela implique.
>
> Pattern correct :
> ```html
> <div><label>...</label><?php echo $this->render_acdc_rich_editor(...); ?></div>
> ```

Cette règle a été oubliée lors de la livraison 3.20.74 — d'où ce patch correctif.

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump `3.20.74` → `3.20.75`.
- `includes/kernel/class-acdc-kernel-render-trait.php` — remplacement de 8 balises `<p>` par `<div>` autour des éditeurs riches dans le formulaire formation.

**Aucun autre fichier modifié.**

## Si quelque chose ne va pas

Le retour à 3.20.74 est immédiat et sans risque. La modification est purement structurelle au niveau HTML et n'impacte aucune donnée stockée.
