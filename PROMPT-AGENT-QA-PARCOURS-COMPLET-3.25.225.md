# [QA-VERIF] Parcours complet — version 3.25.225

Base vierge. Toutes les données créées lors des campagnes précédentes ont été
supprimées par David. Tu repars de zéro, sur un plugin qui a reçu trois séries
de corrections (3.25.223, .224, .225).

Préfixe **tous** tes messages par `[QA-VERIF]`.

---

## 0. Ce qui n'est pas négociable

**FINANCEURS = DONNÉES RÉELLES.** Consultation seule. Aucun clic sur Créer /
Modifier / Supprimer. N'envoie **jamais** d'e-mail à un financeur, depuis aucun
écran. Ne recopie jamais de coordonnées de personnes physiques dans tes
rapports.

**AUCUN ENVOI VERS UNE ENTITÉ QUE TU N'AS PAS CRÉÉE.**

**AUCUNE CRÉATION D'UTILISATEUR WordPress.** Ce test est réservé à David.

**PANNEAU D'HÉBERGEMENT N0C (PlanetHoster) — LECTURE SEULE STRICTE.** Ne
recopie AUCUN identifiant, mot de passe, clé API ni contenu de wp-config.php.

**Lecture seule absolue, aucun clic, aucune exception**, sur le bloc
« Protection des données » (Suppression totale / Créer une sauvegarde /
Importer et restaurer) et sur « Remise à zéro sélective ».

**Ne décoche jamais « Mode recette ».** « Mode simulation » : voir §1.

---

## 1. Configuration de départ — à vérifier AVANT de commencer

Onglet Workflow → Configuration :

- Moteur **actif**.
- **Mode simulation : DÉCOCHÉ.** C'est la nouveauté la plus importante de cette
  campagne : en simulation, les étapes sont marquées « Simulée » et le moteur
  ne les rejoue pas. Un parcours entier joué en simulation ne produit aucun
  envoi. Si tu le trouves coché, signale-le et **arrête-toi** : c'est à David
  de le décocher.
- **Mode recette : COCHÉ**, avec les 7 adresses autorisées.

Note l'état exact des trois cases dans ton premier message.

---

## 2. Fixtures — à respecter au caractère près

- **Formation** : `4.0`
- **Prospect / commanditaire** : Skill Conseil — signataire Bérengère Valeriano
  — `wordpress@davidcontal.com`
- **OPCO** : AFDAS — `david@acdc-formation.com`
- **Apprenantes** :
  - Bérengère Valeriano — `contact@davidcontal.com`
  - Léandra Rossa — `info@davidcontal.com`
  - Ilona Rossa — `david@davidcontal.com`
- **Formateur** : David Contal — `dcontal@acdc-formation.com`
- Mot de passe des boîtes mail : `MariuS10/LouiS12`

**Séances** : deux journées, découpées en demi-journées → **quatre séances**,
donc **quatre feuilles d'émargement**. Crée les séances **AVANT** d'inscrire
les apprenantes, ou après : les deux ordres doivent maintenant fonctionner (§4).

**Rappel de vocabulaire** : quatre lignes pour trois apprenantes est normal sur
l'écran des inscriptions — un commanditaire (Skill Conseil) et trois
apprenantes. Ce n'est pas un doublon. En revanche, sur les écrans de
**documents**, tu ne dois plus voir que **trois** lignes (§7).

---

## 3. Protocole de clic — leçons de la campagne précédente

Tu as toi-même établi ces trois règles ; applique-les d'emblée :

1. **Dialogues natifs acceptés** dans le contexte de la page. Sans cela, tout
   bouton portant `confirm()` paraît inerte alors qu'il fonctionne.
2. **Clic aux coordonnées**, pas par référence d'élément, pour les boutons
   icônes des colonnes d'actions.
3. Avant de déclarer un bouton inerte, **vérifie la cause** : regarde s'il
   porte un `onclick`/`onsubmit`, et si un dialogue a été capté. Rapporte la
   cause, pas seulement le symptôme.

---

## 4. Les actes, dans l'ordre

Tu ne t'interromps sous aucun prétexte. Tu notes les défauts et tu continues.

**Acte 1 — Prospect.** Crée Skill Conseil avec Bérengère Valeriano comme
signataire.
> **À vérifier (3.25.225)** : partout où le prospect s'affiche, tu dois lire
> `Skill Conseil — à l'attention de Bérengère Valeriano`, jamais « Bérengère
> Valeriano » seule.

**Acte 2 — Proposition commerciale.** Crée, génère, envoie.
> **À vérifier** : la référence de formation apparaît sur le document.

**Acte 3 — Devis.** Crée, envoie, fais-le signer par le commanditaire depuis sa
boîte mail.
> **À vérifier (3.25.210)** : le devis signé que le commanditaire récupère
> **porte sa signature manuscrite**.
> **À vérifier (3.25.225 — NOUVEAU)** : une **convention en brouillon** est
> créée automatiquement à la signature du devis, reprenant le commanditaire, la
> formation, les dates, le prix, la TVA et les frais annexes. Elle ne doit être
> envoyée à personne. Vérifie qu'il n'y en a qu'**une seule** même si tu
> recharges la page de signature.

**Acte 4 — Convention.** Complète la convention, génère-la, envoie-la en
signature, signe-la.
> **À vérifier** : la référence de formation y figure.

**Acte 5 — Inscriptions.** Inscris les trois apprenantes.

**Acte 6 — Séances.** Crée les quatre demi-journées, avec horaires réels
(matin et après-midi), lieu, et le formateur David Contal.

**Acte 7 — Analyses du besoin.** Laisse partir les envois automatiques, puis
provoque une relance depuis l'écran.
> **À vérifier (3.25.224)** : l'objet de l'e-mail **nomme l'entreprise** —
> `Skill Conseil — Un petit rappel pour votre analyse du besoin` — et le corps
> porte la mention `Skill Conseil — à l'attention de … (commanditaire)` ou
> `(apprenant)`. Deux analyses portant la même personne dans deux qualités
> différentes doivent être distinguables dans la boîte mail.
> **À vérifier (3.25.212)** : corrige l'adresse d'une apprenante dans sa fiche,
> puis relance : l'envoi doit partir à la **nouvelle** adresse.

**Acte 8 — Convocations.** Laisse partir les convocations apprenants.
> **À vérifier (3.25.224)** : la convocation porte le **lieu** (cascade séance →
> formation → adresse du commanditaire), les **horaires demi-journée par
> demi-journée**, une **durée** réelle, et **aucune civilité devinée** (plus de
> « M. » devant trois apprenantes).
> **À vérifier (3.25.225)** : sur la carte de séance, la ligne « Convocation
> commanditaire » **n'existe plus**. Le commanditaire reçoit un e-mail
> d'**information** (« Information — vos collaborateurs sont convoqués »), pas
> une convocation.

**Acte 9 — Extranet apprenant.** Active les comptes des trois apprenantes,
connecte-toi à chacun, vérifie la bibliothèque et les quiz.
> Sers-toi des quiz **présents** ; n'en crée pas.

**Acte 10 à 13 — Émargement, les quatre demi-journées.** C'est le cœur de la
campagne.
> **À vérifier (3.25.223 — LA correction majeure)** : sur **chacune des quatre**
> feuilles, la liste des apprenants porte **les trois noms**. C'est le défaut
> que tu as classé numéro un la fois précédente : deux feuilles sur quatre
> étaient vides. Attendu : **12 signatures apprenants sur 12**, plus 4
> signatures formateur.
> **À vérifier (3.25.223)** : le bouton « Envoyer » de la **première** séance
> fonctionne. Il ne pouvait pas fonctionner avant : le formulaire émettait un
> jeton suffixé `_0` que le gestionnaire refusait.
> **À vérifier (3.25.224)** : après l'envoi, un **message de confirmation
> s'affiche à l'écran** (« La feuille d'émargement a été envoyée à … »). L'URL
> seule ne suffit pas.
> **À vérifier (3.25.223)** : le canevas de signature enregistre le **premier
> trait** — teste un point simple et un trait très court.
> **À vérifier** : une feuille sans apprenant, si tu en rencontres une,
> **le dit à l'écran** au lieu d'afficher une liste vide muette.

Fais signer les trois apprenantes sur les quatre demi-journées, et le
formateur sur les quatre.

**Acte 14 — Quiz et évaluation des acquis.** Fais passer l'évaluation aux trois
apprenantes depuis leur extranet.

**Acte 15 — Fin de formation.** Attends ou provoque la clôture des séances.
> **À vérifier (3.25.225 — NOUVEAU)** : les documents de fin sont **produits
> automatiquement**. Tu dois trouver, pour **chacune des trois apprenantes** :
> un **certificat de réalisation** (les heures) et, si les acquis sont validés,
> une **attestation de fin de formation** (les acquis). Chaque apprenante reçoit
> **une seule** notification par pièce, pas quatre.
> Si une apprenante n'avait rien signé, c'est une **attestation d'absence** qui
> doit sortir, adressée au **commanditaire** — pas à elle.

**Acte 16 — Enquêtes.** Laisse partir les enquêtes à chaud (apprenants,
entreprise, formateur).

**Acte 17 — Le menu Documents, onglet par onglet.**
> **À vérifier (3.25.224/225)** : sur les onglets Enquêtes à chaud, Certificats
> de réalisation et Attestations de fin de formation :
> - **trois lignes**, pas quatre — la ligne du commanditaire n'a rien à faire
>   sur une pièce nominative ;
> - la colonne **Durée (H)** est renseignée, avec le mot qui va avec : « 14 h
>   émargées » si l'émargement existe, « 14 h prévues » sinon — jamais « — » ;
> - les **dates de formation** sont des dates, sans heure contradictoire d'un
>   écran à l'autre.

**Acte 18 — Portail formateur.** Connecte-toi en David Contal.
> **À vérifier (3.25.225)** : « Mes contrats », s'il est vide, **explique
> pourquoi** et liste les missions planifiées — sans les faire passer pour des
> contrats.

**Acte 19 — Statistiques.**
> **À vérifier (3.25.225)** :
> - **Pédagogiques** : deux cartes distinctes — « Heures de formation
>   dispensées » = **14 h**, et « Heures-stagiaires » = **42 h**. Si tu lis 28 h
>   quelque part, c'est un défaut.
> - **Financières** : les trois portées (potentiel, actions, annexes) affichent
>   des lignes réelles, plus « Aucune donnée ne correspond aux critères
>   demandés ». Le nombre de séances par ligne doit correspondre au dossier, pas
>   à « 4 séances » écrit d'avance.
> - **Indicateurs de performance** : le taux de progression et le taux de
>   réussite remontent les passations faites depuis l'extranet apprenant.

**Acte 20 — Récapitulatif des e-mails.**
> **À vérifier (3.25.224)** : l'onglet « Récapitulatif des e-mails et relances »
> affiche le total d'envois, les destinataires, la répartition par module et les
> derniers envois nommés. La recherche fonctionne. L'écran ne doit plus dire
> « Aucune donnée » quand l'archive est pleine.
> **À vérifier** : la carte « Envois récents » de « Relances simples » n'affiche
> plus 0 alors que des e-mails sont partis.

**Acte 21 — Workflow.**
> **À vérifier (3.25.223)** : aucun bandeau « N étapes jouées en simulation ».
> S'il apparaît, c'est que la simulation a tourné : signale-le.
> **À vérifier (3.25.221)** : les étapes portées par un module tiers (analyse du
> besoin, enquêtes) sont marquées « **En attente d'un préalable** », jamais
> « Faite » — le moteur ne peut pas constater l'envoi d'un module tiers.
> **À vérifier (3.25.207)** : aucune relance planifiée un samedi ou un dimanche.

**Acte 22 — Remise en configuration de sortie.** Recoche « Mode simulation »,
laisse « Mode recette » actif, vérifie que les 7 adresses sont intactes.

---

## 5. Ce que j'attends de ton rapport

- Un **tableau des quatre demi-journées** : feuille, apprenants listés,
  signatures obtenues, signature formateur.
- Un **inventaire du menu Documents** : onglet par onglet, nombre de lignes,
  durée affichée, dates.
- Pour chaque défaut : **l'écran, le geste exact, ce qui était attendu, ce qui
  s'est produit**, et — si tu peux l'établir — **la cause**, pas seulement le
  symptôme.
- Pour chaque point « À vérifier » ci-dessus : **confirmé** ou **infirmé**,
  explicitement. Ne me laisse pas déduire un silence.
- Ce que tu **n'as pas pu** tester, et pourquoi.

Si tu constates qu'un de mes points « À vérifier » ne correspond à aucun écran
réel, dis-le : c'est moi qui me serai trompé, et je préfère le savoir.
