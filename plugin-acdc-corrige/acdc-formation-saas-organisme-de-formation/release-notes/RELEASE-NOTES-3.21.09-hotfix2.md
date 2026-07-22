# RELEASE NOTES — 3.21.09-hotfix2

**Date :** 04 mai 2026
**Base :** 3.21.09-hotfix1
**Type :** Correctif

---

## Corrections

### 1. PHP 7.3 — Typed properties supprimées (FATAL corrigé)

**Fichiers :** `class-acdc-emarg-email.php`, `class-acdc-emarg-public.php`

Les propriétés typées (`private ACDC_Emarg_Core $core`) sont interdites en PHP 7.3 et causent une erreur fatale silencieuse au chargement du module.

- `class-acdc-emarg-email.php` : `private ACDC_Emarg_Core $core` → `/** @var ACDC_Emarg_Core */ private $core`
- `class-acdc-emarg-public.php` : idem pour `$core` et `$email`

### 2. Sessions validées — Colonnes émargement dynamiques

**Fichier :** `class-acdc-sessions-render-trait.php`

Les colonnes "Signature formateur" et "Présence(s) apprenant(s)" affichaient des valeurs statiques hardcodées (`'SIGNÉE'` / `'Voir détails'`).

Remplacé par le rendu dynamique identique à `kernel-render-trait.php` :

**Colonne "Signature formateur" :**
- Pas de session émargement → bouton `✉ Envoyer` (form admin-post)
- Status `pending` → badge bleu "En attente"
- Status `signe` → badge vert "Signé" + heure + miniature PNG

**Colonne "Présence(s) apprenant(s)" :**
- Par apprenant : badge coloré Présent / Retard Xmin / Absent / En attente
- Si signé : heure de signature + miniature PNG

---

## Aucune régression

- Hotfix1 (pièce d'identité `/id/`, signature manuscrite convention) : conservé
- Toutes les tables BDD émargement : inchangées
- Bootstrap et handlers admin-post : inchangés
- Pages publiques (formateur/liste/apprenant) : inchangées
- Kernel-render colonnes : inchangé
