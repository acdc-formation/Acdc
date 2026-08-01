# ACDC Formation SAAS — Release notes 3.21.01.3

**Date** : 27 avril 2026
**Slug** : `acdc-formation-saas-organisme-de-formation`
**Périmètre** : libellés des formations dans les sélecteurs du moteur Quizzes.

---

## En une ligne

Les sélecteurs « Formation rattachée » affichent maintenant la modalité (Présentiel / Distanciel / Hybride / E-learning) et le code de la formation. Fini les apparents doublons : on distingue d'un coup d'œil la variante visée.

---

## Diagnostic

Tu m'as remonté que le sélecteur affichait deux fois chaque titre. En réalité, la table `acdc_of_formations` contient une ligne par **variante** (présentiel, distanciel, hybride, e-learning) — c'est l'architecture choisie pour le plugin. Mes sélecteurs n'affichaient que `title`, ce qui rendait les variantes indiscernables.

Convention déjà établie ailleurs dans le plugin (modules facturation, contrats) :
```
{code} | {title} ({modality})
```

Exemple : `2 | Statuts du couple et successions (Présentiel)`

Je m'aligne sur cette convention au lieu d'inventer un format propre au moteur Quizzes.

---

## Ce qui change

**`get_qz_available_formations()`** récupère désormais aussi `code` et `modality`. Elle filtre les brouillons (`is_draft = 1`) et les formations désactivées (`is_active = 0`) pour ne pas polluer le sélecteur. Le tri est `title ASC, modality ASC` pour regrouper visuellement les variantes d'une même formation.

**`get_qz_formation_title()`** retourne maintenant le label complet (avec code et modalité), pour cohérence avec le sélecteur. C'est ce label qui apparaît sur les cartes de quiz dans la liste.

**Nouvelle méthode publique `format_qz_formation_label( $formation )`** dans le trait Core. Centralise la mise en forme du libellé. Réutilisable par les sous-versions futures qui auront besoin d'afficher une formation.

**Trois rendus mis à jour** dans le trait Render :
- Filtre « Formation » sur la liste des quiz.
- Sélecteur « Formation rattachée » dans la modale Créer.
- Sélecteur « Formation cible » dans la modale Dupliquer depuis.

Tous utilisent désormais `format_qz_formation_label()`.

**Aucun autre fichier modifié.** Le kernel, les autres traits, le JS et le CSS restent à l'identique de la 3.21.01.2.

---

## Recette en 2 étapes

**Étape 1 — Installation.**
Téléverse le ZIP `3.21.01.3` par-dessus la 3.21.01.2. Vide le cache LiteSpeed et fais Ctrl+Maj+R.

**Étape 2 — Vérification du sélecteur.**
Va sur `Quiz / Test / Évaluation` → `Quiz live` → `+ Créer`. Dans le sélecteur « Formation rattachée », vérifie que :
- Chaque formation est désormais préfixée par son code (si défini).
- Chaque formation indique sa modalité entre parenthèses : `(Présentiel)`, `(Distanciel)`, `(Hybride)`, `(E-learning)`.
- Les apparents doublons disparaissent : ce qui semblait être deux fois la même formation est en fait deux variantes.
- Les formations en brouillon ou désactivées n'apparaissent plus.

Vérifie aussi le sélecteur « Formation » dans le bandeau de filtres en haut de la liste, et les deux sélecteurs de la modale « Dupliquer depuis » : ils doivent tous afficher le même format cohérent.

---

## Rollback

Pour revenir en arrière : réinstaller le ZIP 3.21.01.2 par-dessus. Aucune migration BDD à défaire.

---

## À suivre

Toujours en cap pour la **3.21.02 — versioning et verrouillage**, qui apportera la validation de complétude au moment du verrouillage et la création automatique de nouvelles versions.
