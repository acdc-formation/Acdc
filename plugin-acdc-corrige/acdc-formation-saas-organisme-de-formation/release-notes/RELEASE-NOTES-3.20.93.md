# ACDC SAAS OF — Release notes 3.20.93

**Module Formateur — Alertes Qualiopi automatiques (URSSAF, RC pro, justificatifs Qualiopi)**

Version précédente : 3.20.92 (lien id-based session/groupe ↔ formateur + onglet « Mes sessions », livrée et validée).
Date : avril 2026.
Slug : `acdc-formation-saas-organisme-de-formation` (inchangé).
Migration BD : **aucune** — toutes les colonnes nécessaires sont déjà en place depuis la 3.20.82 et 3.20.86.

---

## Vue d'ensemble

Ce patch boucle le module Formateur côté conformité Qualiopi. Un cron WP quotidien scanne les dates d'expiration des trois sources canoniques :

- `wp_acdc_of_trainers.urssaf_attestation_expires_at` — attestation URSSAF
- `wp_acdc_of_trainers.rc_pro_expires_at` — assurance RC professionnelle
- `wp_acdc_of_trainer_documents.expires_at` (avec `is_qualiopi_proof = 1`) — justificatifs Qualiopi (CV, diplômes, certifications…)

Aux seuils **J-30, J-7 et J-0** (arbitrage 3b validé), deux e-mails séparés sont envoyés (arbitrage 1.4 validé) : un au formateur sur un ton personnel l'invitant à mettre à jour son document depuis son extranet, un à l'administration sur un ton synthétique avec les données d'identification.

Le périmètre est strict Qualiopi (arbitrage 2a validé) : les documents avec `expires_at` mais sans le flag `is_qualiopi_proof = 1` ne déclenchent pas d'alerte automatique.

---

## Architecture

### Cron quotidien

Hook : `acdc_of_qualiopi_alerts_cron`
Fréquence : `daily`
Offset : 1500s pour étaler par rapport aux autres crons existants.

Planté de manière idempotente via `maybe_schedule_runtime_hook` dans `maybe_repair_runtime_state`. Si le cron est désinstallé pour une raison quelconque, il est replanté au prochain chargement de page admin. Branché sur la méthode `process_qualiopi_expiration_alerts()`.

### Anti-doublon

Option WordPress `acdc_of_qualiopi_alerts_log`, tableau associatif :

```
{
  "t42_urssaf_2026-08-15_30":  "2026-04-26",
  "t42_urssaf_2026-08-15_7":   "2026-08-08",
  "t56_doc_103_2026-05-15_0":  "2026-05-15",
  ...
}
```

La clé combine `trainer_id`, type de document, date d'expiration et seuil. Si l'admin met à jour la date d'expiration, la clé change automatiquement et une nouvelle alerte sera envoyée au prochain seuil pertinent.

Purge automatique des entrées plus vieilles que 60 jours pour éviter que l'option ne grossisse indéfiniment.

### Deux e-mails séparés (arbitrage 1.4)

**Pour le formateur** — ton personnel, deuxième personne du pluriel, CTA vers le portail formateur (onglet Ma bibliothèque). Sujet contextuel : « Votre justificatif expire dans X jours » / « Votre justificatif expire dans 7 jours » / « Votre justificatif expire aujourd’hui ».

**Pour l'administration** — ton synthétique, troisième personne, données d'identification du formateur, action attendue selon l'urgence. Sujet préfixé : « Qualiopi — Expiration dans X jours : [type de document] ». Envoyé à `get_option('admin_email')`.

Les deux e-mails passent par `acdc_send_transactional_email()` qui applique le template ACDC officiel (header bleu `#1E4777`, bouton or `#D7A24B`, footer 06 78 26 91 10 · contact@acdc-formation.com).

### Bandeau visuel sur la fiche formateur (admin)

En tête de la fiche formateur en mode édition, un bandeau coloré apparaît si au moins une expiration tombe dans les 30 jours. Code couleur :

- **Rouge** (`#fdecec` / `#c62828`) — expiration aujourd'hui (J-0) ou dépassée
- **Orange** (`#fff3d6` / `#a06b00`) — expiration entre 1 et 7 jours
- **Jaune** (`#fffae6` / `#caa53a`) — expiration entre 8 et 30 jours

Le bandeau liste les éléments concernés avec leur date d'expiration et inclut un bouton **« Tester l'envoi »** qui déclenche immédiatement l'envoi des alertes pour ce formateur (en ignorant le verrou anti-doublon — utile pour valider les templates en prod).

### Bandeau dans le tableau de bord du portail formateur

Le formateur connecté voit le même type de bandeau coloré en tête de son tableau de bord, conditionné à la permission `view_qualiopi_status` (active par défaut sur les profils Simple et Autonome depuis la 3.20.91). Le bandeau renvoie vers son onglet Ma bibliothèque pour mettre à jour les documents concernés.

---

## Fichiers modifiés

- `acdc-formation-saas-organisme-de-formation.php` — bump version `3.20.93`.
- `includes/class-acdc-plugin.php` — `add_action( 'acdc_of_qualiopi_alerts_cron', ... )` + `add_action( 'admin_post_acdc_qualiopi_test_alert', ... )`.
- `includes/kernel/class-acdc-kernel-core-trait.php` — `maybe_schedule_runtime_hook` pour le nouveau cron + nouveau bloc complet (≈ 320 lignes) avec :
  - `get_acdc_qualiopi_alert_thresholds()`
  - `get_acdc_qualiopi_alert_log()` / `set_acdc_qualiopi_alert_log()`
  - `acdc_qualiopi_type_label()`
  - `get_acdc_qualiopi_upcoming_expirations()`
  - `process_qualiopi_expiration_alerts()` (handler du cron)
  - `acdc_qualiopi_send_alert_pair()`
  - `acdc_qualiopi_subject_for()`
  - `acdc_qualiopi_build_body_for_trainer()`
  - `acdc_qualiopi_build_body_for_admin()`
  - `acdc_qualiopi_run_test_for_trainer()` (test manuel)
- `includes/kernel/class-acdc-kernel-actions-trait.php` — nouveau handler `handle_qualiopi_test_alert()`.
- `includes/kernel/class-acdc-kernel-render-trait.php` — bandeau « Expirations Qualiopi à venir » + bouton « Tester l'envoi » sur la fiche formateur admin.
- `includes/trainer-portal/render/class-acdc-trainer-portal-render-trait.php` — bandeau « Statut Qualiopi » dans le tableau de bord du portail formateur (conditionné à `view_qualiopi_status`) + mise à jour du texte d'introduction du dashboard pour mentionner « Mes sessions » désormais disponible.

**Aucune modification** : schéma BD, autres modules (CRM, prospects, apprenants, formations, financeurs, devis, factures, conventions, contrats, quiz, évaluations, enquêtes), helpers PDF, extranet apprenant, paramètres globaux, fichiers JS/CSS, logique de permissions formateur (livrée en 3.20.91), liens groupes/sessions/formateurs (livrés en 3.20.92).

---

## Procédure de test (à exécuter après installation)

> **Pré-requis cache** : après installation, vider le cache LiteSpeed et recharger en Ctrl+Shift+R.
> **Pré-requis SMTP** : la procédure suppose qu'un envoi e-mail fonctionnel est configuré sur le site. En cas de doute, faire un test simple avec un autre plugin (WP Mail Logging par exemple) avant.

### A — Planification du cron

1. Activer la nouvelle version du plugin.
2. Vérifier que le cron est planifié : depuis WP-CLI ou via le plugin **WP Crontrol**, chercher le hook `acdc_of_qualiopi_alerts_cron`. Il doit apparaître avec une fréquence quotidienne.
3. Si vous utilisez WP Crontrol, vous pouvez le déclencher manuellement (« Exécuter maintenant ») pour valider le bon fonctionnement du cron.

### B — Test manuel via la fiche formateur

4. Préparer un formateur avec **au moins une date d'expiration** qui tombe à J-30, J-7 ou J-0 :
   - Soit en renseignant une date fictive sur `urssaf_attestation_expires_at` ou `rc_pro_expires_at` (ouvrir la fiche formateur, modifier les champs, enregistrer).
   - Soit en téléversant un document Qualiopi (`is_qualiopi_proof = 1`) avec une `expires_at` correspondante via la bibliothèque admin.
5. Recharger la fiche du formateur. Le bandeau « Expirations Qualiopi à venir » doit apparaître en tête de fiche, dans la couleur correspondant à l'urgence la plus critique.
6. Cliquer sur le bouton **« Tester l'envoi »** dans le bandeau. Confirmer la modale.
7. Une notice apparaît : « X alerte(s) test envoyée(s) à : email1, email2 ». Vérifier que :
   - L'e-mail formateur est bien arrivé sur la boîte du formateur.
   - L'e-mail admin est bien arrivé sur l'e-mail admin du site (`get_option('admin_email')`).

### C — Vérification du contenu des e-mails

8. **E-mail formateur** : sujet en deuxième personne (« Votre justificatif… »), corps personnel, encadré gris-bleu listant le document concerné et la date, bouton or ACDC « Mettre à jour mon justificatif » → cliquer doit ouvrir l'onglet Ma bibliothèque du portail formateur.
9. **E-mail admin** : sujet préfixé « Qualiopi — Expiration… : [document] », corps synthétique, table avec les champs Formateur / Justificatif / Date / Action attendue. Mention en bas : « Le formateur a reçu en parallèle une notification personnelle ».

### D — Anti-doublon

10. Cliquer **à nouveau** sur le bouton « Tester l'envoi » pour le même formateur sans rien changer côté dates.
11. Le test étant en mode manuel **ignore** le verrou anti-doublon et renverra des e-mails — c'est le comportement attendu pour cette fonctionnalité de test.
12. **Test du verrou en cron réel** : pour vérifier que le cron lui-même évite les doublons :
    - Déclencher manuellement `acdc_of_qualiopi_alerts_cron` via WP Crontrol → première exécution envoie les alertes pour les seuils détectés.
    - Aller dans `wp_options`, vérifier que l'option `acdc_of_qualiopi_alerts_log` contient des entrées du jour.
    - Re-déclencher le cron immédiatement → aucune alerte envoyée (les entrées existent déjà dans le log).

### E — Bandeau formateur (portail)

13. Connecter le formateur (avec une expiration dans 30 jours) à son extranet `/extranet-formateur/`.
14. Sur le tableau de bord, le bandeau « Statut Qualiopi » doit apparaître en tête, dans la couleur appropriée, avec la liste des documents concernés et un lien « Mettre à jour mes justificatifs → ».
15. Vérifier que le bandeau **disparaît** si toutes les expirations sont à plus de 30 jours.

### F — Permissions

16. Côté admin, ouvrir la fiche du même formateur. Passer le profil de permissions sur **Personnalisé** et **décocher** « Voir son statut Qualiopi », enregistrer.
17. Recharger le portail formateur, tableau de bord : le bandeau Qualiopi a **disparu** (la permission le conditionne).
18. Réactiver la permission, le bandeau revient.

### G — Code couleur progressif

19. Modifier les dates d'expiration pour tester chaque palier :
    - `J-25` (8 à 30 jours) → bandeau jaune
    - `J-5` (1 à 7 jours) → bandeau orange
    - `J-0` (aujourd'hui) ou date passée → bandeau rouge
20. Vérifier que la couleur du bandeau (côté admin et côté portail formateur) reflète bien le seuil le plus critique parmi les expirations détectées.

### H — Cas vide

21. Sur un formateur **sans aucune date d'expiration** dans les 30 jours : aucun bandeau visible, ni côté admin ni côté portail. Pas d'erreur, pas de message vide.
22. Cliquer sur « Tester l'envoi » dans la fiche d'un formateur sans expiration éligible (pas de seuil J-30/J-7/J-0) : message « Aucune date d'expiration aux seuils J-30, J-7 ou J-0 pour ce formateur. Test non envoyé ».

### I — Anti-régression

23. Vérifier que les autres fonctionnalités du module Formateur fonctionnent comme avant : édition fiche, calendrier disponibilités (3.20.90), matrice de permissions (3.20.91), onglet Mes sessions (3.20.92), bibliothèque, profil.
24. Vérifier que les autres modules (CRM, sessions, learners, formations, etc.) sont inchangés.

---

## Points de vigilance

- **Calcul des jours** : la fonction `get_acdc_qualiopi_upcoming_expirations()` utilise `strtotime( wp_date('Y-m-d') )` pour la date du jour, donc tient compte du fuseau horaire WordPress. Cela évite les bugs de seuil en début/fin de journée près de minuit UTC.
- **Comportement à J-0 et au-delà** : à J-0, l'alerte est envoyée. Au-delà (J+1, J+5…), aucune alerte n'est envoyée par le cron — le bandeau visuel persiste cependant sur la fiche formateur en rouge avec le libellé « dépassée de N jours », pour ne pas laisser une situation oubliée.
- **Seuils manqués** : si le cron rate une journée (panne hébergeur, désactivation), le seuil est manqué pour les expirations qui tombaient pile ce jour-là. C'est volontaire (simplicité). Pour rattrapage manuel, l'admin peut utiliser le bouton « Tester l'envoi » sur les fiches concernées. Une amélioration future pourrait consister à scanner sur des fenêtres `[seuil-2; seuil]` au lieu de `[seuil]` pile, mais ça compliquerait la logique anti-doublon.
- **Test manuel ignore le verrou** : le bouton « Tester l'envoi » n'écrit pas dans le log anti-doublon, donc un même seuil peut être renvoyé en boucle pour valider les templates. Le cron quotidien, lui, respecte le verrou. C'est le comportement attendu — ne pas confondre les deux.
- **Test sans seuil pertinent** : si un formateur a des expirations à 25 jours par exemple (entre les seuils 30 et 7), le bandeau est affiché mais le bouton « Tester l'envoi » répondra « Aucune date d'expiration aux seuils J-30, J-7 ou J-0 ». Comportement correct : on n'envoie qu'aux seuils canoniques.
- **Cache LiteSpeed** : après upgrade, vider le cache plugin et recharger. Les bandeaux étant générés au runtime côté serveur, ils n'apparaîtront pas sur des pages cachées avant invalidation.
- **Volume d'e-mails** : un formateur ayant 5 documents Qualiopi qui expirent tous le même jour générera 10 e-mails (5 × 2 destinataires). Pour des organismes de formation avec beaucoup de formateurs et beaucoup de justificatifs, c'est volontairement granulaire pour la traçabilité. Une consolidation en un seul e-mail récapitulatif par formateur est envisageable dans un patch futur si le besoin émerge.

---

## Module Formateur — boucle Qualiopi terminée

La 3.20.93 boucle l'objectif initial du module Formateur tel qu'il avait été cadré :

- 3.20.82 → schéma BD + permissions canoniques (helpers backend prêts)
- 3.20.83 → auth custom + page extranet
- 3.20.84/85 → hotfixes CSS et template
- 3.20.86/87 → bibliothèque admin et formateur
- 3.20.88 → mini-patch UI plein écran
- 3.20.89 → onglet Mon profil avec allowlist serveur
- 3.20.90 → calendrier annuel des disponibilités
- **3.20.91 → matrice de permissions UI admin**
- **3.20.92 → onglet Mes sessions + lien id-based session/groupe ↔ formateur**
- **3.20.93 → alertes Qualiopi automatiques (ce patch)**

À votre main pour la suite : améliorations transverses, sujets en suspens du résumé de session (champ `catalog_slug` orphelin sur le formulaire formation, libellés bruts dans le catalogue public, etc.), ou tout autre périmètre que vous voudrez aborder.
