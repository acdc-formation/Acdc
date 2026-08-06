# Recette — Config. pré-formation → Répertoires

**Date :** 5 août 2026 · **Version testée :** ACDC 3.25.147 · **Site :** acdcformation.com (préproduction)
**Couverture :** ~98 % des blocs A→I · **52 anomalies** · **6 bloquantes**
**E-mails :** 10 envois, tous vers le domaine du propriétaire · **aucun vers un financeur**
**État du site :** restitué à l'identique (3 apprenants, 0 groupe, 191 commanditaires, 11 financeurs,
7 thématiques, 20 formations, 2 formateurs, 3 utilisateurs) · zéro objet `TEST-QA` résiduel ·
aucun compte WordPress orphelin · aucune donnée préexistante modifiée ou supprimée.

---

## 🔴 Bloquants (6)

| # | Bug | Impact | Cause racine (confirmée au code) |
|---|-----|--------|----------------------------------|
| **F11** | PDF de contrats formateur en **accès public** | 🔒 **Exposition RGPD** : nom, e-mail, SIRET accessibles sans authentification, URL énumérable (`contrat-formateur-{trainer_id}-{contract_id}.pdf`), fichiers **survivant à la suppression** de la mission et du formateur | `handle_generate_trainer_contract_pdf()` fait `wp_mkdir_p()` **sans `.htaccess` ni `index.php`**, alors que le plugin protège correctement d'autres dossiers (`deny from all` sur les pièces d'identité de signature) |
| **G4** | Le bouton « Générer contrat PDF » **envoie un e-mail au formateur** | Envoi non consenti à un tiers ; aucun retour visible → l'utilisateur reclique → **double envoi constaté** | `wp_mail()` inconditionnel avant l'émission des en-têtes ; la réponse HTTP se perd (404) alors que le PDF est bien écrit sur disque |
| **A5** | Export CSV apprenants **vide** (en-tête seul) | Export inexploitable | La requête sélectionne `c.company_name`, **colonne inexistante** (la vraie est `name`) → erreur SQL silencieuse, `get_results()` renvoie un tableau vide. **Correctif : 1 ligne** |
| **A1** | Recherche Apprenants → page 404 `/extranet-2/` | Fonction inutilisable | Champ nommé `name="s"` = **variable réservée WordPress** (recherche) → `redirect_canonical()`. 6 emplacements concernés (dont 5 hors périmètre) |
| **E2/E5** | Création/suppression de thématique → écran « pas l'autorisation » | Utilisateur éjecté sur une impasse alors que l'écriture a réussi | Redirection vers `admin.php?page=acdc-of-thematiques`, page **jamais déclarée** ; `is_admin()` est toujours vrai sous `admin-post.php`, donc la branche front-office n'est jamais empruntée |
| **G5** | Bouton d'en-tête « Envoyer pour signature » cassé | Le bouton le plus visible échoue (« Données manquantes ») ; le bouton de ligne fonctionne | `contract_id` soumis vide |

---

## 🟠 Fonctionnels (24, principaux)

- **Attribution d'archivage e-mail neutralisée** — les en-têtes `X-ACDC-*` sont correctement **émis** mais **jamais lus** : WordPress transforme `$headers` en tableau **associatif** (`pluggable.php` l.307/378) avant `wp_mail_succeeded`, alors que `parse_email_archive_headers()` attend des lignes `"Nom: valeur"`. Conséquences : colonne **Source** toujours `plugin / wp_mail`, et **Destinataire** résolu par **adresse e-mail** au lieu de l'entité.
  *Preuve expérimentale (agent QA)* : 4 e-mails vers `contact@` → tous attribués à Bérengère Valeriano ; 6 vers `contact+testqa@` → tous correctement attribués à TEST-QA Formateur.
- `learner_id` et `company_id` sont des **paramètres morts** : transmis par les menus 3 points, jamais consommés par les formulaires de destination (analyse du besoin, devis, convention, inscription).
- Menu 3 points **Commanditaires** → 2 slugs inexistants : `tab=quotes&action=new` (aboutit à la liste) et `tab=contracts` (slug inexistant → tableau de bord). Le menu Apprenants utilise, lui, les bons slugs.
- Onglet Commanditaires : **191 lignes sans pagination ni champ de recherche** (les 7 autres onglets en ont un).
- Variantes Présentiel/Distanciel de formation **indistinguables dans les selects** (libellés strictement identiques) → risque de mauvais tarif.
- Validation **SIRET absente** côté Commanditaire (13 chiffres accepté), alors que le formulaire Prospect impose strictement 14 chiffres → incohérence interne.
- Duplication de formation : les **espaces insécables (U+00A0) deviennent la chaîne littérale `&nbsp;`** (double encodage à la copie).
- `item_id` inexistant → **formulaire vide** au lieu d'une erreur « introuvable » (confirmé sur formations et companies).
- Champ **« Méthode d'émargement » absent du formulaire de groupe** alors que la colonne existe en base, est affichée, filtrée et recherchée.
- Aucun **garde-fou « thématique utilisée »** avant suppression.
- **Unicité de l'e-mail formateur** contrôlée à l'invitation mais **pas à la création** → doublons possibles.
- Bloc « Accès au portail formateur » **présent uniquement sur la page Modifier**, absent de la page Voir.
- L'**exemplaire signé** du contrat ne part **qu'au formateur** ; l'OF ne reçoit aucune copie (incohérent avec la convention).
- **Archive des signatures** : colonne « Formation / Séance » à `—` et colonne « Document » **vide** → valeur probante inaccessible.
- Contrat et exemplaire signé **non classés** dans la bibliothèque du formateur (catégorie « Contrats formateurs » à 0).
- **Aucun historique d'e-mails** sur les fiches (apprenant, commanditaire).
- Libellé **« Commanditaire » trompeur** sur la fiche/liste apprenant (alimenté par `prospect_id`, alors que « Entreprise » = `company_id` pointe le vrai répertoire).
- **Aucun tri, aucune case à cocher, aucune action groupée** sur les 8 onglets.
- « Interrompre la formation du groupe » : action irréversible **sans confirmation ni motif**.
- Fonctions portail **apprenant** (activation / envoi d'accès / réinitialisation) reléguées dans un écran wp-admin séparé, sans pont depuis la fiche — **asymétrie** avec le formateur.
- Import de formation : round-trip basé sur l'**intitulé exact** (l'export n'a ni ID, ni statut, ni thématique) → un renommage crée un doublon ; formation importée créée directement en **VALIDÉE**.
- Duplication de formation : pas de suffixe « copie », statut **VALIDÉE** d'emblée → risque de publication involontaire.

## 🟡 Cosmétiques (22, principaux)

Bandeau de succès jamais rendu (la redirection utilise `_acdc_notice` — **1 seule occurrence** dans tout le plugin — au lieu de `notice`) · durée affichée « 14:00 » (lue comme une heure, pas une durée) · tarif « 1800 » brut sans séparateur ni symbole · code `IA` au lieu du libellé de thématique · SIRET non normalisés (avec/sans espaces) · « ARCHIV/E » coupé sur deux lignes · bouton « Créer & ajouter un autre » sur des formulaires d'**édition** (groupes, formateurs) · date non zero-paddée (`4/08/2026`) · colonne Photo de profil incohérente · emojis collées au texte · ordre non trié de l'export CSV formateurs · « Archiver » sans `confirm()`.

---

## ✅ Points sains confirmés

- **Dates et fuseau horaire parfaits** partout : aucun décalage ±2 h, aucune inversion jour/mois, y compris sur des cas pièges (05/08). Horodatages d'archive strictement égaux à l'heure réelle.
- **Aucun « Array »**, aucun ID brut affiché à la place d'un nom, sur les 8 onglets.
- **Statuts lisibles** partout (jamais de valeur technique) ; `Oui/Non` et non `1/0` dans les exports.
- **Accents et apostrophes impeccables** : interface, PDF, e-mails, export CSV **et** XLSX. Aucune entité HTML parasite, aucun mojibake. Seule exception : le `&nbsp;` de la duplication (copie, pas affichage).
- **Signature électronique de bout en bout excellente** : OTP accepté du premier coup, adresse masquée, aperçu du document, canevas manuscrit, bouton désactivé tant que les conditions ne sont pas réunies, horodatage exact, PDF signé généré séparément.
- **Export CSV formateurs exemplaire** (BOM UTF-8, 18 colonnes, accents, dates FR) — contraste qui **valide** le diagnostic de l'export apprenants vide.
- **Contrat PDF formateur** : mise en page sans défaut ; **le montant figure bien** en page 2 (Article 6 — Rémunération : `taux €/H × N H = montant € HT`).
- **Suppression en cascade** du compte WordPress à la suppression du formateur : aucun compte orphelin.
- **Onglet Financeurs** : aucun bouton d'envoi d'e-mail, ni en liste ni en fiche, ni menu 3 points, ni export, ni action groupée → **risque d'envoi involontaire vers un OPCO nul**. Les 11 financeurs sont strictement intacts.

---

## ⚠️ Angle mort unique — à exécuter par David

**H2 / H6 — création puis suppression d'un utilisateur.** L'agent QA a **refusé d'exécuter** ce test : le select « Rôle » ne propose que **« Administrateur »** et **« Administrateur principal »**, donc créer ce compte revient à créer un accès administratif. Décision assumée et documentée plutôt qu'exécutée.

À vérifier (2 minutes) : que le rôle choisi est bien celui attribué après enregistrement · qu'un e-mail d'accès part ou non (le formulaire n'en propose aucun — réserve H5) · que la suppression est propre.

---

## Traces volontairement laissées en place

1. **2 PDF orphelins** dans `/wp-content/uploads/acdc-of-contracts/5/` — conservés comme **preuve du bug F11** ; ils disparaîtront avec le correctif.
2. **1 entrée dans l'Archive des signatures** (TEST-QA Formateur, 05/08/2026 22:15) — **non supprimée volontairement** : une piste d'audit de signature électronique a valeur probante.
3. **8 e-mails de test** dans l'Archive des e-mails — sans gravité, ils documentent le bug d'attribution.

---

## Plan de correction proposé

- **LOT 1 — bloquants (6)** : priorité absolue à **F11** (exposition RGPD) et **G4** (envoi non consenti), puis A5, A1, E2/E5, G5.
- **LOT 2 — fonctionnels** : attribution X-ACDC, paramètres morts (`learner_id`/`company_id`), slugs cassés, recherche/pagination Commanditaires, validation SIRET, garde-fous.
- **LOT 3 — cosmétiques** : `_acdc_notice`, formats durée/tarif, libellés, notices.
