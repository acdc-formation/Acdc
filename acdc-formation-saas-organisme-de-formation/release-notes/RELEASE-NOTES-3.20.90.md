# ACDC Formation SAAS — version 3.20.90

## Objet

**Calendrier annuel des disponibilités du formateur.**

Refonte complète du système de disponibilités. Le formateur configure son rythme habituel par cases à cocher (matin/après-midi pour chaque jour de la semaine), puis ajoute ou retire des exceptions ponctuelles directement sur un calendrier mensuel cliquable. L'admin consulte le résultat en lecture seule sur la fiche formateur.

C'est le plus gros patch UI du module Formateur depuis l'auth custom.

## Modèle de données

### Nouveau format `availability_json`

```json
{
  "weekly": {
    "lundi":    { "morning": true,  "afternoon": true  },
    "mardi":    { "morning": true,  "afternoon": true  },
    "mercredi": { "morning": true,  "afternoon": true  },
    "jeudi":    { "morning": true,  "afternoon": true  },
    "vendredi": { "morning": true,  "afternoon": true  },
    "samedi":   { "morning": false, "afternoon": false },
    "dimanche": { "morning": false, "afternoon": false }
  },
  "exceptions": {
    "2026-08-15": { "morning": false, "afternoon": false, "note": "" },
    "2026-12-24": { "morning": false, "afternoon": false, "note": "" },
    "2026-06-14": { "morning": true,  "afternoon": true,  "note": "" }
  }
}
```

### Migration — option C (rétrocompat à la lecture)

Aucune migration BD massive. Tout se fait à la volée :

- En **lecture**, `parse_trainer_availability()` accepte les deux formats. Si l'ancien format est détecté (`{"lundi":"Disponible",...}`), il est converti en mémoire selon cette table :

| Ancienne valeur | Matin | Après-midi |
|---|---|---|
| `Disponible` | ✅ | ✅ |
| `Fermé` | ❌ | ❌ |
| `Matin uniquement` | ✅ | ❌ |
| `Après-midi uniquement` | ❌ | ✅ |
| autre / vide | ❌ | ❌ |

- En **écriture**, on stocke toujours le nouveau format. Au fil des sauvegardes, les anciennes fiches passent naturellement au nouveau format.

- **Schéma par défaut** pour un nouveau formateur : Lun-Ven matin+après-midi disponibles, samedi/dimanche fermés.

### Auto-cleaning des exceptions

Quand le formateur clique sur un jour qui correspondait à son rythme habituel, on enregistre une exception. Mais si après bascule, l'état correspond **à nouveau** au rythme habituel, l'exception est **automatiquement supprimée** plutôt que stockée. La BD reste légère, et le calendrier reste lisible (les exceptions sont vraiment des exceptions).

## Côté formateur — édition complète

### Nouvel onglet « Mes disponibilités »

Le portail compte maintenant **4 onglets** :

```
Tableau de bord  │  Ma bibliothèque  │  Mes disponibilités  │  Mon profil
```

### Carte 1 — Mon rythme habituel

Grille de 7 cartes (une par jour de la semaine), chacune avec 2 cases à cocher (Matin / Après-midi). Bouton « Enregistrer mon rythme habituel ».

### Carte 2 — Calendrier des exceptions

Calendrier mensuel à 7 colonnes (Lun-Dim), avec navigation prev/next entre les mois. Chaque jour est divisé en 2 demi-cellules cliquables :
- **M** (matin) — verte si dispo, rouge si indispo
- **A** (après-midi) — idem

Au clic, l'état bascule via AJAX (sauvegarde immédiate, pas de bouton « Enregistrer »).

### Code couleur

| Couleur | Signification |
|---|---|
| 🟢 Vert clair (`#e7f4ec`) | Disponible — rythme habituel |
| 🔴 Rouge clair (`#fdecec`) | Indisponible — rythme habituel |
| 🟢 Vert foncé (`#1a7d3b`) | **Disponibilité exceptionnelle** — diffère du rythme habituel |
| 🔴 Rouge foncé (`#c62828`) | **Indisponibilité exceptionnelle** — diffère du rythme habituel |

Les exceptions ressortent immédiatement à l'œil (couleurs vives saturées) tandis que le rythme habituel reste en arrière-plan visuel apaisé.

### Aujourd'hui mis en évidence

Le numéro du jour courant est encadré en jaune (`#fff3d6` avec texte `#a06b00`).

## Côté admin — consultation

### Sur la fiche formateur

Le widget hebdo non interactif (qui affichait juste le texte avec un bouton « + » qui ne faisait rien) est **remplacé** par :

1. **Bandeau Rythme habituel** : 7 cartes compactes une ligne avec les pastilles M/A colorées en lecture seule.
2. **Calendrier mensuel** : version compacte du calendrier formateur, navigable entre mois, **non cliquable**.
3. **Légende** : rappel du code couleur.

Mention claire : « Le formateur gère ses disponibilités depuis son portail extranet (onglet Mes disponibilités). »

### Bug fix critique

L'ancien `handle_save_trainer` côté admin **écrasait** systématiquement `availability_json` avec `{"lundi":"Fermé",...,"dimanche":"Fermé"}` à chaque sauvegarde de fiche (formulaire admin). Ce bug existait depuis l'origine du module et explique pourquoi vos disponibilités étaient régulièrement réinitialisées.

**Correction** : la clé `availability_json` est désormais **complètement absente** du tableau `$data` côté admin. `$wpdb->update()` ne touche plus à cette colonne lors d'un save admin. Seul le formateur peut écrire ses disponibilités, via les 2 nouveaux handlers de son portail.

## Architecture technique

### Helpers core (kernel-core-trait)

- `get_acdc_weekday_keys()` — `['lundi', ..., 'dimanche']`
- `get_acdc_weekday_labels()` — `['lundi' => 'Lundi', ...]`
- `get_default_trainer_availability()` — schéma Lun-Ven dispo
- `parse_trainer_availability($json)` — parse rétrocompat → structure normalisée
- `serialize_trainer_availability($struct)` — sérialise vers JSON, avec auto-cleaning
- `date_to_weekday_key($date_str)` — `'2026-05-15'` → `'vendredi'`
- `get_trainer_day_availability($availability, $date_str)` — renvoie `{morning, afternoon, is_exception, note}`

### Handlers (trainer-portal-actions-trait)

- `handle_trainer_update_weekly_schedule()` — POST classique, met à jour `weekly[*]`.
- `handle_trainer_toggle_availability()` — endpoint AJAX, calcule l'état suivant, applique l'auto-cleaning, renvoie le nouveau JSON pour mise à jour DOM.

### Helper de rendu réutilisable (trainer-portal-render-trait)

`render_trainer_availability_calendar( $availability, $month_ts, $editable, $ajax_url, $nonce, $base_nav_url )`

Génère le HTML du calendrier mensuel, en mode édition (boutons cliquables avec dataset) ou lecture seule (spans simples). Utilisé deux fois :

- Côté formateur (édition, AJAX)
- Côté admin (lecture, version compacte)

### Sécurité du toggle AJAX

| Vérification | Effet |
|---|---|
| `check_ajax_referer` | Refuse si la nonce est invalide ou expirée |
| `trainer_portal_get_current_account` | Refuse si pas de session active |
| Validation regex date `^\d{4}-\d{2}-\d{2}$` | Refuse les formats incorrects |
| Validation `part` ∈ `{morning, afternoon}` | Refuse toute autre valeur |
| Le formateur ne peut écrire que **sa propre** ligne (`trainer_id` issu du compte connecté) | Isolation stricte |

## Cohérence visuelle

- Plein écran (cohérent avec 3.20.88).
- Cartes blanches arrondies (cohérent avec 3.20.89).
- Couleurs sémantiques : vert succès `#1a7d3b`, rouge danger `#c62828` — palette ACDC en dur.
- Pas d'utilisation de `--acdc-primary` pour les états vert/rouge (la palette de marque est l'or champagne, pas le rouge ou le vert).
- Police, espacements, rayons cohérents avec le reste du portail.

## Engagement de préservation

- **Aucune migration BD destructive.** Aucune colonne ajoutée, aucune transformation massive. La rétrocompatibilité option C garantit que les anciennes valeurs restent lisibles indéfiniment.
- **Aucune dépendance externe ajoutée.**
- **Le formulaire admin reste fonctionnel.** Tous les autres champs (identité, coordonnées, photo, rôle, NDA, SIRET, opt-in apprenants) continuent de se sauvegarder normalement. Seul `availability_json` n'est plus touché côté admin.

## Procédure de test

### Test 1 — Onglet Mes disponibilités existe

1. Purger LiteSpeed (DB + objets + plugin), déployer ZIP, purger encore, `Cmd + Shift + R`.
2. Se connecter à `/extranet-formateur/`.
3. Vérifier la nav : **4 onglets** maintenant (Tableau de bord / Ma bibliothèque / **Mes disponibilités** / Mon profil).
4. Cliquer sur « Mes disponibilités » → URL devient `?view=availability`.

### Test 2 — Rétrocompat ancien format

**Pré-requis** : votre `availability_json` actuel en BD contient peut-être l'ancien format `{"lundi":"Fermé",...}`.

1. Sur l'onglet « Mes disponibilités », vérifier l'affichage de la carte « Mon rythme habituel ».
2. Si l'ancien format était `Fermé` partout, vous verrez 7 cartes avec toutes les cases décochées.
3. **Cocher** quelques cases (ex. Lundi matin, Mardi matin+après-midi, Mercredi après-midi).
4. Cliquer « Enregistrer mon rythme habituel ».
5. Vérifier le message vert + recharger la page → les cases doivent rester cochées.
6. Inspecter la BD via SQL : la colonne `availability_json` doit maintenant être au nouveau format avec `weekly` + `exceptions: {}`.

### Test 3 — Calendrier interactif

1. Sur la carte « Calendrier des exceptions », vérifier le mois courant affiché en titre (ex. « Mai 2026 » avec capitale).
2. Vérifier la légende en bas.
3. **Cliquer** sur la cellule « M » d'un lundi → la cellule passe de vert clair à rouge foncé (exception négative). Pas de rechargement de page.
4. **Cliquer** sur la même cellule → retour à vert clair (l'exception est auto-cleanée car elle correspond à nouveau au rythme habituel).
5. **Cliquer** sur la cellule « M » d'un samedi (rouge clair par défaut) → passage en vert foncé (exception positive).
6. Recharger la page → l'état doit persister.

### Test 4 — Navigation entre mois

1. Cliquer sur la flèche « › » → mois suivant affiché.
2. Cliquer plusieurs fois pour aller en novembre 2026.
3. Cliquer « ‹ » pour revenir au mois courant.
4. Vérifier que le numéro du jour courant est en jaune (case « Aujourd'hui »).

### Test 5 — Cohérence côté admin

1. Toujours connecté en admin WordPress, ouvrir la fiche de votre formateur test.
2. Vérifier qu'à la place de l'ancien tableau hebdo, on voit :
   - Une grille de 7 cartes compactes avec les pastilles M/A colorées (rythme habituel)
   - Un calendrier mensuel en lecture seule (les cellules ne sont pas cliquables — pas de hover qui change le curseur)
   - La légende en dessous
3. Naviguer entre mois côté admin → fonctionne (URL admin avec `&month=YYYY-MM`).
4. Vérifier que les exceptions posées côté formateur (test 3) apparaissent en couleurs vives côté admin.

### Test 6 — Bug fix : save admin ne casse plus les dispos

1. **Étape critique pour valider le bug fix.** Ouvrir la fiche formateur côté admin, modifier un champ trivial (ex. téléphone, ou cocher/décocher une case « E-mail de rappel »).
2. Cliquer « Enregistrer ».
3. Recharger la page formateur (`/extranet-formateur/?view=availability`).
4. Vérifier que **le rythme habituel ET les exceptions sont préservés** (avant le 3.20.90, le save admin réinitialisait tout).
5. SQL de vérification : `SELECT availability_json FROM wp_acdc_of_trainers WHERE id = X;` → doit montrer le format `{"weekly":...,"exceptions":...}` intact.

### Test 7 — Sécurité du toggle AJAX

(Optionnel)

1. Ouvrir l'inspecteur réseau, intercepter une requête de toggle, modifier `_wpnonce` → la requête doit retourner 403.
2. Se déconnecter du portail formateur, retenter une requête de toggle avec l'ancienne nonce → 401 (session expirée).

### Test 8 — Audit

```sql
SELECT created_at, event_type
FROM wp_acdc_of_trainer_portal_logs
WHERE event_type LIKE 'availability_%'
ORDER BY id DESC LIMIT 10;
```

Doit montrer les `availability_weekly_updated` à chaque enregistrement du rythme habituel.

## Limites connues

- **Pas d'édition d'exception côté admin.** Comme convenu (lecture seule). Si vous devez forcer une dispo pour un formateur récalcitrant, c'est SQL ou intervention sur la fiche du formateur connecté.
- **Pas de note libre sur les exceptions côté UI.** Le champ `note` dans l'exception existe en BD mais n'est pas exposé dans l'UI pour cette version. À ajouter plus tard si demande utilisateur.
- **Pas de récurrence d'exception** (genre « Tous les vendredis matin de juin et juillet »). Chaque exception est ponctuelle.
- **Pas de vue annuelle 12 mois** sur une seule page. Navigation mois par mois uniquement (cohérent avec les usages standards type Calendly).
- **Pas de check de conflit avec sessions planifiées.** Si une session est déjà programmée le 15 août et que le formateur marque le 15 août en indisponibilité, aucune alerte. À traiter dans le 3.20.91 (Mes sessions).

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump 3.20.89 → 3.20.90.
- `includes/class-acdc-plugin.php` — branchement de 2 actions (`acdc_trainer_update_weekly_schedule`, `acdc_trainer_toggle_availability`).
- `includes/kernel/class-acdc-kernel-core-trait.php` — 7 nouveaux helpers (parsing rétrocompat, serialize, date utilities).
- `includes/kernel/class-acdc-kernel-actions-trait.php` — `handle_save_trainer` ne touche plus à `availability_json` (bug fix).
- `includes/kernel/class-acdc-kernel-render-trait.php` — remplacement du widget hebdo non interactif par le calendrier en lecture seule.
- `includes/trainer-portal/actions/class-acdc-trainer-portal-actions-trait.php` — 2 handlers + 1 helper privé `build_availability_cell_class()`.
- `includes/trainer-portal/render/class-acdc-trainer-portal-render-trait.php` — nouvelle vue `render_trainer_portal_availability()` + helper réutilisable `render_trainer_availability_calendar()` + 4e onglet dans la nav + texte dashboard mis à jour.

## Suite

Module Formateur — état d'avancement :

| Patch | Périmètre | Statut |
|---|---|---|
| 3.20.82 | Schéma BD | ✅ |
| 3.20.83 | Auth custom + e-mail invitation | ✅ |
| 3.20.84 | CSS portail | ✅ |
| 3.20.85 | Template blank | ✅ |
| 3.20.86 | Bibliothèque admin | ✅ |
| 3.20.87 | Bibliothèque portail + nav | ✅ |
| 3.20.88 | Plein écran | ✅ |
| 3.20.89 | Mon profil | ✅ |
| **3.20.90** | **Calendrier annuel disponibilités** | ✅ |
| 3.20.91 | Mes sessions | À venir |
| 3.20.92 | Alertes Qualiopi automatiques | À venir |

À ce stade, le portail formateur dispose de :
- Authentification sécurisée
- Bibliothèque personnelle complète (admin + formateur)
- Profil éditable
- Calendrier annuel de disponibilités

Validez le 3.20.90, et on enchaîne sur le **3.20.91 — Mes sessions**.
