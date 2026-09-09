# Notes de version — 3.25.112 (4ᵉ vague)

Date : 2026-07-22
Thème : câblage du **taux d'occupation** (arbitrage produit) + réparation des actions d'émargement formateur.

## 3 corrections

### 1. Taux d'occupation des séances = présents / inscrits (arbitrage validé)
Le panneau « Taux d'occupation » des statistiques formateurs comptait chaque séance comme « Présent »
en dur (Absent/En attente toujours à 0) → toujours 100 %. Désormais agrégé à partir de l'émargement
réel de chaque séance :
- **Présent** = nombre d'apprenants ayant signé,
- **Absent** = nombre marqués absents,
- **En attente** = inscrits n'ayant ni signé ni été marqués absents.
Le pourcentage « Présent » correspond donc à présents / inscrits. Les compteurs bruts sont exposés
par `get_validated_sessions()` (`presence_signed_count` / `presence_absent_count` / `presence_total_count`).

### 2. Actions formateur depuis la page liste publique (émargement)
Sur la page liste (ouverte par lien/QR, formateur non connecté à WordPress), les boutons
« Marquer absent » et « ✉ Envoyer lien » pointaient vers des handlers `admin-post` exigeant
`manage_options` → `wp_die` (page blanche) pour le formateur. Ces deux actions sont désormais :
- routées via le flux public `template_redirect` (comme les signatures — contourne aussi le WAF
  LiteSpeed sur mobile),
- autorisées par le `list_token` de la séance (le détenteur du lien gère la présence de CETTE séance),
- sécurisées par nonce **et** vérification que l'apprenant ciblé appartient bien à la séance du token.

### 3. Calcul du retard d'émargement (fuseaux horaires)
`late_minutes` mélangeait `strtotime()` (fuseau serveur, souvent UTC) et `current_time('timestamp')`
(fuseau WordPress) → retard fantôme ou masqué de l'offset GMT (1-2 h). Recalculé en base homogène
UTC réel via `get_gmt_from_date($start_at, 'U')` comparé à `time()`.

## Reste à traiter (backlog, non bloquant)
- Déduplication de l'import CSV marketing par e-mail contre les fiches métier (prospects/apprenants…) :
  différée car elle interagit avec la fusion non destructive corrigée en 3.25.111 et nécessite ses
  propres tests.
- Numérotation devis/avoirs par `MAX+1` (réutilisation possible après suppression d'un devis) :
  robustesse à traiter si une séquence sans trou est exigée.
- Normalisation d'URL de veille (paramètres `utm_*`, slash final) pour une déduplication plus fine.
