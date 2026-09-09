# ACDC Formation SAAS — Organisme de formation

ERP complet pour organisme de formation (Qualiopi) sous forme de plugin WordPress :
CRM commercial, propositions/devis, marketing, sessions, quiz live, questionnaires
(satisfaction à chaud/à froid, NPS), dossiers & contrats, facturation (devis, factures,
avoirs), conformité qualité (BPF/Cerfa, indicateurs Qualiopi), émargement numérique,
signature électronique OTP, portails apprenant & formateur, veille réglementaire IA.

- **Version** : 3.25.103
- **PHP requis** : 8.2+
- **WordPress** : 6.2+
- **Licence** : GPL-2.0-or-later
- **Text domain** : `acdc-formation-saas`

## Installation

1. Copier le dossier dans `wp-content/plugins/`.
2. Activer « ACDC Formation SAAS Organisme de formation » depuis l'admin.
3. Les tables (`{$wpdb->prefix}acdc_of_*`) sont créées automatiquement (dbDelta).

## Développement

Le dépôt est outillé pour la qualité (voir `composer.json`).

```bash
composer install          # dépendances de dev (phpcs, phpstan, phpunit…)

composer lint             # vérifie la syntaxe PHP de tous les fichiers
composer phpcs            # style + sécurité (WordPress Coding Standards)
composer phpcbf           # corrige automatiquement le style corrigeable
composer phpstan          # analyse statique (niveau 4)
composer test             # tests PHPUnit
composer check            # lint + phpcs + phpstan (utilisé en CI)
```

### Internationalisation

Le fichier `languages/acdc-formation-saas.pot` est le modèle de traduction.
Le régénérer après ajout de chaînes :

```bash
wp i18n make-pot . languages/acdc-formation-saas.pot   # si WP-CLI disponible
```

## Architecture (résumé)

- Point d'entrée : `acdc-formation-saas-organisme-de-formation.php` (bootstrap + chargement des modules).
- Cœur : `includes/class-acdc-plugin.php` compose les traits de chaque module dans la classe principale.
- Modules : `includes/<module>/` (kernel, crm-commercial, marketing, proposals, sessions,
  quizzes, questionnaires, dossiers-contracts, documents-billing, compliance-quality,
  emargement, signature, learner-portal, trainer-portal, watch, agent-audit…).
- Chaque module suit le découpage `*-core-trait` (données), `*-actions-trait` (handlers),
  `*-render-trait` (rendu). Les modules Signature / Émargement / Audit sont en classes
  dédiées (cible d'architecture à généraliser).
- Assets : `assets/css`, `assets/js` (+ `assets/js/vendor` pour les libs tierces).

## Historique des versions

Voir [`CHANGELOG.md`](CHANGELOG.md). Les notes détaillées par version sont archivées
dans le dossier [`release-notes/`](release-notes/).
