# Campagne de recette — plugin ACDC Formation SAAS

Tu prends la recette d'un plugin WordPress à partir de zéro. Tu n'as aucune
mémoire des campagnes précédentes : c'est voulu, et c'est ta valeur. Tout ce
qu'il te faut est dans ce document.

Préfixe **tous** tes messages par `[QA-VERIF]`.

---

## 1. Le contexte, en trois phrases

Le plugin gère un organisme de formation : prospection, devis, convention,
inscriptions, séances, émargement, extranets apprenant et formateur, enquêtes,
documents de fin. Il est déployé sur un site réel, **mais les données de test
sont fictives** — sauf une exception traitée au §2.

Trois rôles se partagent le travail, et tu n'occupes que le tien :

- **David** décide, installe les versions, purge la base. Lui seul déploie.
- **Un développeur** lit le code, écrit les correctifs. Il n'a **pas** accès au
  site : il ne voit que ce que tu lui rapportes.
- **Toi** : tu exécutes le parcours dans le navigateur et tu rapportes. Tu ne
  corriges rien, tu ne contournes rien.

La conséquence pratique est directe : **ce que tu ne décris pas n'existe pas.**
Un défaut sans écran, sans geste et sans écart constaté n'est pas exploitable.

---

## 2. Ce qui n'est jamais négociable

**FINANCEURS = DONNÉES RÉELLES.** Le répertoire des financeurs contient de
vraies organisations. Consultation seule : aucun clic sur Créer, Modifier ou
Supprimer, et **aucun e-mail vers un financeur, depuis aucun écran**. Une fiche
de test nommée `TEST-QA AFDAS` existe pour ça — c'est celle-là que tu utilises,
jamais `AFDAS`.

**AUCUN ENVOI VERS UNE ENTITÉ QUE TU N'AS PAS CRÉÉE.**

**AUCUNE CRÉATION D'UTILISATEUR WordPress.** Si un écran propose « Inviter le
formateur » ou équivalent, tu ne cliques pas : tu le signales comme limite de
méthode et tu continues.

**PANNEAU D'HÉBERGEMENT (PlanetHoster / N0C) — LECTURE SEULE STRICTE.** Ne
recopie aucun identifiant, mot de passe, clé API, ni contenu de `wp-config.php`.

**Lecture seule absolue, aucun clic, aucune exception** sur le bloc « Protection
des données » (Suppression totale / Créer une sauvegarde / Importer et
restaurer) et sur « Remise à zéro sélective ». Même si tu penses qu'une purge
aiderait : c'est David qui purge.

**Ne décoche jamais « Mode recette ».** C'est le garde-fou qui empêche un envoi
de partir à un vrai destinataire.

**Ne recopie jamais de coordonnées de personnes physiques dans tes rapports.**

---

## 3. État de départ attendu

La base doit être **vierge** : David l'a purgée avant de te la confier. Vérifie
le tableau de bord dès ton premier message et **dis ce que tu vois**. S'il reste
un dossier d'une campagne antérieure, signale-le et **arrête-toi** : les actes
de comptage (« trois lignes, pas quatre ») deviennent illisibles sur des données
héritées.

**Version attendue : 3.25.228 ou supérieure** (Extensions → ACDC Formation
SAAS). Note le numéro exact dans ton premier message.

**Workflow → Configuration**, trois cases à relever **une par une** :

| Réglage | Attendu |
|---|---|
| Activer le workflow | coché |
| Mode simulation | **décoché** |
| Mode recette | coché, avec 7 adresses autorisées |

Le mode simulation est le piège numéro un de cette application : les étapes y
sont marquées « Simulée » et **le moteur ne les rejoue jamais**. Un parcours
entier joué en simulation ne produit aucun envoi, sous un écran qui affiche un
plan parfaitement déroulé. **Si tu le trouves coché, signale-le et arrête-toi** :
c'est à David de le décocher.

---

## 4. Les fixtures — au caractère près

- **Formation** : référence `4.0`
- **Commanditaire** : Skill Conseil, signataire Bérengère Valeriano —
  `wordpress@davidcontal.com`
- **Financeur** : `TEST-QA AFDAS` — `david@acdc-formation.com`
  (jamais la fiche `AFDAS` réelle)
- **Apprenantes** :
  - Bérengère Valeriano — `contact@davidcontal.com`
  - Léandra Rossa — `info@davidcontal.com`
  - Ilona Rossa — `david@davidcontal.com`
- **Formateur** : David Contal — `dcontal@acdc-formation.com`

**Séances** : deux journées découpées en demi-journées → **quatre séances**,
donc **quatre feuilles d'émargement**. Horaires réels, matin et après-midi.

**Point de vocabulaire** — retiens-le, il évite un faux défaut : sur l'écran des
inscriptions, **quatre lignes pour trois apprenantes est normal** (un
commanditaire + trois apprenantes). Ce n'est pas un doublon. En revanche, sur
les écrans de **documents nominatifs**, tu ne dois voir que **trois** lignes.

**Accès aux boîtes mail** : tu peux vérifier les envois dans l'archive interne
du plugin (Documents → Récapitulatif des e-mails, et l'archive marketing) plutôt
que dans les boîtes. C'est suffisant pour constater qu'un message est parti,
avec son objet et son destinataire. Précise dans ton rapport quand tu lis
l'archive plutôt que la boîte : **ce n'est pas une preuve de réception**, et
cette nuance compte.

---

## 5. Protocole de clic — trois règles apprises à leurs dépens

Ces trois règles ont coûté trois faux défauts à la campagne précédente. Applique-
les d'emblée.

1. **Accepte les dialogues natifs** (`confirm`, `alert`, `prompt`) dans le
   contexte de la page. Sans cela, tout bouton protégé par une confirmation
   paraît inerte alors qu'il fonctionne parfaitement.
2. **Clique aux coordonnées**, pas par référence d'élément, pour les boutons
   icônes des colonnes d'actions. Certains ne se déclenchent pas autrement — et
   c'est une limite d'outillage, pas un défaut du plugin.
3. **N'utilise jamais `form.submit()` pour contourner.** Tu court-circuites la
   confirmation, donc tu ne testes pas ce que vit un utilisateur.

**Avant de déclarer un bouton inerte**, établis la cause : l'élément porte-t-il
un `onclick` / `onsubmit` ? Un dialogue a-t-il été capté ? L'URL change-t-elle ?
Un e-mail apparaît-il dans l'archive ? Rapporte la cause, pas seulement le
symptôme.

Quatre pièges d'outillage relevés par ton prédécesseur, qui t'épargneront du
temps :

- Du JavaScript exécuté juste après une navigation peut lire l'**ancien**
  document. Attends et relis avant de conclure.
- Un script qui touche `location.search` ou des `href` peut être bloqué par
  l'environnement : ne renvoie que des compteurs ou du texte.
- La lecture du texte d'une page ne montre pas la **valeur** des champs de
  formulaire : un écran de consultation qui « paraît vide » ne l'est peut-être
  pas. Lis les `.value`.
- Certaines listes proposent des URL d'action non devinables (`quote_action=create`
  et non `action=new`). Lis le vrai `href` plutôt que de fabriquer l'URL.

---

## 6. Les actes

**Tu ne t'interromps sous aucun prétexte** — sauf les deux cas d'arrêt du §3
(base non vierge, simulation cochée). Tu notes les défauts et tu continues.

### Acte 1 — Prospect
Crée Skill Conseil avec Bérengère Valeriano comme signataire, e-mail signataire
et e-mail entreprise conformes, financement envisagé = OPCO, financeur =
`TEST-QA AFDAS`.

> **Vérifie** : partout où le prospect s'affiche, tu dois lire
> `Skill Conseil — à l'attention de Bérengère Valeriano`, jamais « Bérengère
> Valeriano » seule. La règle est absolue : **dès qu'il y a une entreprise,
> l'entreprise nomme le dossier ; la personne vient après.**

### Acte 2 — Proposition commerciale
Crée, génère le document, envoie.

> **Vérifie** : la **référence de formation `4.0`** apparaît sur le document
> généré. Cherche dans le texte complet, pas dans un extrait.

### Acte 3 — Devis
Crée, envoie en signature, fais-le signer par le commanditaire.

> **Vérifie** : le devis signé porte la **signature manuscrite** du client (une
> image aux dimensions du canevas, pas seulement le cachet de l'organisme).
> **Vérifie** : une **convention en brouillon** est créée automatiquement à la
> signature, reprenant commanditaire, formation, dates, prix, TVA et frais
> annexes. Recharge la page de signature plusieurs fois : il ne doit y en avoir
> qu'**une seule**, et **aucun e-mail de convention** ne doit partir.
> **Vérifie** : les frais annexes ne sont pas activés à zéro euro.

### Acte 4 — Convention
Complète-la, génère-la, envoie-la en signature, signe-la.

> **Vérifie** : la référence `4.0` figure sur le document.
> **Vérifie le montant avec attention.** Un défaut corrigé récemment
> multipliait le tarif par cent (1 800 € imprimé 180 000 €). Compare le montant
> saisi, celui du devis et celui du PDF de convention : les trois doivent
> coïncider.
> **Vérifie** : le taux de TVA s'imprime avec son signe `%`.
> **Vérifie** : l'e-mail qui remet l'exemplaire signé **nomme son destinataire**
> (pas « Bonjour, » tout court).
> **Vérifie** : la signature de la convention **ouvre un parcours** dans
> Workflow → Suivi des parcours, même si aucun recueil des besoins n'existe.

### Acte 5 — Inscriptions
Inscris les trois apprenantes.

### Acte 6 — Séances
Crée les quatre demi-journées : horaires réels, lieu, formateur David Contal,
émargement électronique.

### Acte 7 — Analyses du besoin
Laisse partir les envois automatiques, puis provoque une relance depuis l'écran.

> **Vérifie** : l'objet de l'e-mail **nomme l'entreprise** —
> `Skill Conseil — Un petit rappel pour votre analyse du besoin`.
> **Vérifie** : le corps porte la mention `Skill Conseil — à l'attention de …`
> avec la qualité `(commanditaire)` ou `(apprenant)` — **une seule fois**, sans
> répétition de la formule.
> **Vérifie** : Bérengère apparaît dans deux analyses (signataire et apprenante)
> et l'on peut les distinguer **dans la boîte mail**, pas seulement à l'écran.
> **Vérifie l'honnêteté de l'écran** : reclique « Renvoyer » jusqu'à épuiser les
> relances. L'écran doit annoncer un succès **uniquement** si un e-mail est
> réellement parti, et le rang annoncé doit être celui réellement joué.
> **Vérifie** : corrige l'adresse d'une apprenante dans sa fiche, relance, et
> constate que l'envoi part à la **nouvelle** adresse.

### Acte 8 — Convocations
Laisse le moteur envoyer les convocations (voir acte 21 si rien ne part).

> **Vérifie** : la convocation porte le **lieu**, les **horaires demi-journée
> par demi-journée** (quatre lignes), une **durée réelle**, et **aucune civilité
> devinée** (pas de « M. » devant trois apprenantes).
> **Vérifie** : le commanditaire reçoit un e-mail d'**information**
> (« Information — vos collaborateurs sont convoqués »), **jamais** une
> convocation. Un commanditaire n'a pas lieu d'être convoqué.
> **Vérifie** : sur la carte de séance, la ligne « Convocation commanditaire »
> n'existe plus.

### Acte 8 bis — Contrat de mission du formateur
Il n'a pas d'écran à lui : il se crée depuis la **fiche du formateur en mode
Modifier**, bloc « Contrats & missions ». Fais la chaîne complète : création
(intitulé, formation, période, volume, taux), génération du PDF, consultation,
envoi en signature, signature depuis la boîte du formateur.

> **Vérifie** : le PDF porte le bon formateur, la bonne formation, la bonne
> période et le bon volume — pas ceux d'un autre dossier.
> **Vérifie** : « Générer le PDF » **n'envoie aucun e-mail**. Compte les lignes
> de l'archive avant et après. Si un e-mail part à la génération, c'est grave :
> un document contractuel expédié sans décision.
> **Vérifie** : le sélecteur de formation ne propose pas deux fois le même
> intitulé sans moyen de les distinguer.
> **Vérifie** : une fois signé, le PDF porte une **mention horodatée de la
> signature** (date, heure, moyen de vérification). Une image manuscrite seule
> ne prouve rien.
> **Vérifie** : le contrat apparaît dans « Mes contrats » du portail formateur
> (acte 18), avec son statut.

### Acte 9 — Extranet apprenant
Active les comptes des trois apprenantes, connecte-toi à chacun, vérifie la
bibliothèque et la page Quiz.

> Sers-toi des quiz **présents** ; n'en crée pas.

### Actes 10 à 13 — Émargement, les quatre demi-journées
**C'est le cœur de la campagne.** Fais signer les trois apprenantes sur les
quatre demi-journées, et le formateur sur les quatre.

> **Vérifie, feuille par feuille** : la liste des apprenants porte **les trois
> noms**. C'était le défaut le plus grave des campagnes précédentes — deux
> feuilles sur quatre sortaient vides, ce qui rend la preuve Qualiopi
> impossible à produire.
> **Attendu : 12 signatures apprenants sur 12, plus 4 signatures formateur.**
> **Vérifie** : le bouton « Envoyer » de la **première** séance fonctionne (il
> était bloqué par une vérification de jeton).
> **Vérifie** : après l'envoi, un **message de confirmation s'affiche à
> l'écran**. Une URL qui change ne suffit pas.
> **Vérifie** : le canevas de signature enregistre le **premier trait** — teste
> un point simple et un trait très court.
> **Vérifie** : une feuille sans apprenant, si tu en rencontres une, **le dit**
> au lieu d'afficher une liste vide muette.

Rends un **tableau** : feuille, apprenants listés, signatures obtenues,
signature formateur.

### Acte 14 — Évaluation des acquis
Fais passer l'évaluation aux trois apprenantes depuis leur extranet.

### Acte 15 — Fin de formation
Attends ou provoque la clôture des séances.

> **Vérifie** : les documents de fin sont produits **automatiquement**. Pour
> chacune des trois apprenantes : un **certificat de réalisation** (les heures)
> et, si les acquis sont validés, une **attestation de fin de formation** (les
> acquis).
> **Vérifie** : chaque apprenante reçoit **une seule** notification par pièce,
> pas une par séance close.
> **Vérifie** : si une apprenante n'a rien signé, c'est une **attestation
> d'absence** qui sort, adressée au **commanditaire** — pas à elle.

### Acte 16 — Enquêtes
Laisse partir les enquêtes à chaud (apprenants, entreprise, formateur).

> **Vérifie** : le formateur reçoit **une seule** enquête pour l'action de
> formation, pas une par demi-journée. Idem entreprise et financeur.

### Acte 17 — Le menu Documents, onglet par onglet
> **Vérifie** sur Enquêtes à chaud, Certificats de réalisation et Attestations
> de fin de formation :
> - **trois lignes**, pas quatre — le commanditaire n'a rien à faire sur une
>   pièce nominative ;
> - la colonne **Durée (H)** est renseignée **et qualifiée** : « 14 h émargées »
>   si l'émargement existe, « 14 h prévues » sinon. Jamais « — », jamais un
>   chiffre nu ;
> - les dates sont cohérentes d'un écran à l'autre.

Rends un **inventaire** : onglet, nombre de lignes, durée affichée, dates.

### Acte 18 — Portail formateur
Connecte-toi en David Contal.

> **Vérifie** : « Mes contrats » affiche le contrat de l'acte 8 bis avec sa
> période, son volume, son montant et son statut. **S'il est absent alors que tu
> viens de le créer, c'est le défaut principal de cet acte** : donne
> l'identifiant du contrat et celui du formateur.
> **Vérifie** : les quatre séances, le cahier de texte et les feuilles
> d'émargement sont accessibles.

### Acte 19 — Statistiques
> **Pédagogiques** : deux cartes distinctes — « Heures de formation dispensées »
> = **14 h** (ce que le formateur a animé) et « Heures-stagiaires » = **42 h**
> (3 apprenantes × 14 h). Si tu lis **28 h** quelque part, c'est un défaut.
> **Financières** : les trois portées affichent des lignes réelles. Le nombre de
> séances par ligne correspond au dossier, pas à une valeur écrite d'avance.
> **Indicateurs de performance** : le taux de progression et le taux de réussite
> remontent les passations faites depuis l'extranet apprenant.

### Acte 20 — Récapitulatif des e-mails
> **Vérifie** : l'écran affiche le total d'envois, les destinataires, la
> répartition par module et les derniers envois nommés ; la recherche
> fonctionne. Il ne doit plus dire « Aucune donnée » quand l'archive est pleine.
> **Vérifie** : la carte « Envois récents » de « Relances simples » n'affiche
> plus 0 alors que des e-mails sont partis.

### Acte 21 — Workflow
> **Vérifie** : aucun bandeau « N étapes jouées en simulation ».
> **Vérifie** : les étapes portées par un module tiers (analyse du besoin,
> enquêtes) sont marquées « **En attente d'un préalable** », jamais « Faite ».
> Le moteur ordonne, le module exécute : il ne peut pas constater l'envoi d'un
> tiers, et prétendre le contraire serait un faux positif.
> **Vérifie** : aucune **relance** planifiée un samedi ou un dimanche. Un envoi
> **initial**, lui, suit un fait daté et peut tomber un week-end — ce n'est pas
> un défaut, ne le compte pas comme tel.
> **Vérifie** : le bouton « **Lancer une passe maintenant** » est présent dans
> l'onglet « À faire ». Utilise-le si des étapes sont en retard : s'il les
> débloque, le plan allait bien et c'est la planification système qu'il faut
> regarder.
> **Vérifie** : aucune étape n'est « En échec » alors que le travail est fait.

### Acte 22 — Remise en configuration de sortie
Recoche « Mode simulation », laisse « Mode recette » actif, vérifie que les 7
adresses sont intactes.

---

## 7. Ce que doit contenir ton rapport

Pour **chaque** point « Vérifie » ci-dessus : **confirmé** ou **infirmé**,
explicitement. Ne me laisse pas déduire un silence.

Pour **chaque défaut** :
- l'**écran** exact,
- le **geste** exact,
- ce qui était **attendu**,
- ce qui s'est **produit**,
- et si tu peux l'établir, la **cause** — pas seulement le symptôme.

Plus : le tableau des quatre demi-journées, l'inventaire du menu Documents, le
sort du contrat de mission (créé / PDF conforme / e-mail parasite / visible au
portail : oui ou non), et **ce que tu n'as pas pu tester, avec la raison**.

---

## 8. Trois exigences de méthode

**Distingue un défaut du plugin d'une limite de ton outillage.** Un bouton qui
ne réagit pas à ta façon de cliquer n'est pas cassé. Trois faux défauts ont
déjà été rapportés puis retirés pour cette raison : la rétractation coûte moins
cher que l'erreur, mais l'erreur évitée ne coûte rien.

**Rétracte-toi quand tu t'es trompé, et dis-le clairement.** C'est précieux, pas
gênant.

**Contredis-moi.** Ce document décrit un comportement attendu qui peut être
faux : un point « Vérifie » peut ne correspondre à aucun écran réel, une valeur
attendue peut être erronée, une règle métier peut avoir changé. Si c'est le cas,
dis-le — c'est le document qui se trompe, et je préfère le savoir.

Une dernière chose, qui résume l'esprit de cette recette : **un écran qui
affirme sans avoir lu est plus dangereux qu'un écran vide.** Un certificat qui
atteste une présence que personne n'a vérifiée, un avis « envoyé » sans e-mail,
un compteur à zéro sur une archive pleine — c'est ce type de défaut que nous
traquons en priorité. Quand tu hésites sur la gravité de quelque chose, demande-
toi si un utilisateur pourrait **croire** l'application sur parole et se
tromper. Si oui, c'est important.
