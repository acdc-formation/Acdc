# Prompt agent — sortir les e-mails du dossier « Indésirables »

À coller **dans l'onglet de l'agent**. Mission bornée : établir pourquoi les
e-mails du plugin arrivent en spam, produire les enregistrements DNS exacts qui
corrigent le problème, et **prouver par un message réel** qu'ils passent.

---

## Une décision vous revient AVANT de coller ce prompt

Cette mission touche à la **zone DNS** du domaine. Votre règle permanente est
que le panneau N0C est en **lecture seule stricte** pour l'agent. Je ne la lève
pas de moi-même. Deux façons de procéder, choisissez :

**A — L'agent diagnostique, vous appliquez** *(conforme à votre règle, recommandé)*
Collez le prompt tel quel. L'agent établit les faits, vous rend les
enregistrements à créer, ligne par ligne, prêts à coller — et s'arrête là. Vous
les saisissez dans N0C, puis vous lui dites « c'est fait » : il reprend à
l'étape 3 pour vérifier.

**B — L'agent applique lui-même**
Ajoutez ce paragraphe à la fin de la section GARDE-FOUS du prompt :

> Exception à la lecture seule N0C, valable pour cette mission uniquement :
> tu es autorisé à créer et modifier des enregistrements DNS de type TXT et CNAME
> sur les domaines acdcformation.com et acdc-formation.com. Tu ne touches à
> AUCUN autre type d'enregistrement — en particulier ni A, ni AAAA, ni MX, ni NS :
> une erreur sur ceux-là met le site ou la réception du courrier hors service.
> Tu ne supprimes jamais un enregistrement existant sans avoir d'abord recopié
> son contenu dans ton rapport.

**Pourquoi ce garde-fou sur les MX** : ce sont eux qui font arriver le courrier
*entrant*. Une mission qui parle d'envoi n'a aucune raison d'y toucher, et une
faute à cet endroit ne se voit pas tout de suite — on s'en aperçoit quand un
client dit ne pas avoir eu de réponse.

---

## Ce que je sais déjà, et qui évite à l'agent de le redécouvrir

- Les enquêtes **sont bien parties** — c'est vérifié dans l'archive e-mail du
  plugin. Elles sont arrivées **dans les indésirables**. Le problème n'est donc
  pas l'envoi, c'est la réputation.
- Le plugin envoie par `wp_mail()`, à travers une porte commune unique, avec un
  en-tête `From:` construit depuis la fiche entreprise. Le relais est **Mailjet**,
  branché par l'extension **WP Mail SMTP**.
- Deux domaines coexistent : **acdcformation.com** (l'application) et
  **acdc-formation.com** (le site vitrine). L'agent doit établir lequel signe
  réellement les messages — ce n'est pas forcément celui qu'on croit.

---

```
[MISSION — LES E-MAILS DU PLUGIN ARRIVENT EN SPAM]

Les e-mails d'ACDC Formation partent bien, mais ils atterrissent dans le dossier
« Indésirables » de leurs destinataires. Ce sont des convocations, des feuilles
d'émargement, des enquêtes de satisfaction — des pièces qu'un apprenant qui ne
les voit pas ne remplit pas, et un dossier Qualiopi sans preuve est un dossier
incomplet.

Ta mission tient en trois temps : établir POURQUOI, produire les corrections
exactes, et prouver par un message réel qu'elles fonctionnent.

Le relais d'envoi est Mailjet, branché par l'extension WP Mail SMTP.
Les domaines concernés sont acdcformation.com et acdc-formation.com.

────────────────────────────────────────────────
LA RÈGLE QUI PRIME SUR TOUTES LES AUTRES
────────────────────────────────────────────────
Tu vas voir passer des clés d'API Mailjet et des identifiants d'hébergement.

Tu ne les écris JAMAIS : ni dans ton rapport, ni dans un message, ni dans un
résumé, ni « juste les premiers caractères ». Si tu dois parler de l'un d'eux,
tu écris exactement : « clé API Mailjet présente et active ». Rien de plus.

Les enregistrements DKIM contiennent une clé PUBLIQUE : celle-là se recopie sans
danger, c'est même son rôle. Ne confonds pas les deux. En cas de doute sur la
nature d'une valeur, tu ne la recopies pas et tu demandes.

────────────────────────────────────────────────
GARDE-FOUS
────────────────────────────────────────────────
- Tu n'envoies AUCUN e-mail de test à un apprenant, un formateur, une entreprise
  ou un financeur réel. Aucun. Tes messages de test vont vers des adresses que
  tu contrôles ou que David t'a explicitement données.
- Ton message de test ne contient AUCUNE donnée personnelle : pas de nom
  d'apprenant, pas d'adresse, pas de contenu de dossier. Un simple texte neutre.
  Tu vas l'envoyer vers des services d'analyse externes : tout ce qu'il contient
  leur est communiqué.
- Tu ne décoches JAMAIS « Mode recette » dans le plugin. Si tu penses en avoir
  besoin, tu t'arrêtes et tu demandes à David.
- Tu ne crées AUCUN utilisateur WordPress, aucun apprenant, aucune entreprise.
- Le panneau N0C est en LECTURE SEULE, sauf autorisation explicite ci-dessous.
- Dans Mailjet, tu ne supprimes aucun expéditeur ni aucun domaine déjà validé.
- Tu ne touches à aucun autre réglage du plugin.

════════════════════════════════════════════════
ÉTAPE 1 — ÉTABLIR LES FAITS, AVANT DE CORRIGER QUOI QUE CE SOIT
════════════════════════════════════════════════

N'applique aucune correction tant que cette étape n'est pas terminée et rendue.
Une correction posée sur un diagnostic supposé se contente de déplacer le
problème.

1.1 — QUELLE ADRESSE SIGNE LES MESSAGES ?
Dans le plugin, ouvre « Profil de l'entreprise » et relève l'adresse e-mail
d'expédition et le nom d'expéditeur. Relève aussi l'adresse de réponse si elle
diffère.

  → SI L'ADRESSE D'EXPÉDITION EST CHEZ UN FOURNISSEUR GRAND PUBLIC — gmail.com,
    orange.fr, outlook.com, free.fr — ARRÊTE-TOI ET DIS-LE IMMÉDIATEMENT.
    Aucun réglage DNS ne rattrapera cela : depuis 2024, Gmail et Yahoo rejettent
    ou classent en spam les messages envoyés « au nom de » une adresse Gmail par
    un serveur tiers. C'est la cause la plus fréquente, et la plus vite corrigée :
    il faut une adresse sur le domaine de l'organisme.

1.2 — QUE DIT UN MESSAGE RÉELLEMENT REÇU ?
C'est la seule source de vérité. Les réglages annoncés dans une interface ne
prouvent rien : c'est l'en-tête d'un message arrivé qui fait foi.

Envoie un message de test depuis le plugin vers une adresse que tu contrôles
(idéalement une Gmail, puisque c'est là que le classement est le plus sévère).
Ouvre l'en-tête complet du message reçu — dans Gmail : les trois points, puis
« Afficher l'original ». Relève :

  - le résultat SPF : pass, softfail, fail, ou none ?
  - le résultat DKIM : pass ou fail ? Et surtout : quel domaine dans « d= » ?
  - le résultat DMARC : pass ou fail ?
  - l'adresse exacte dans « From: » et celle dans « Return-Path: »

  → LE POINT QUI DÉCIDE DE TOUT, ET QU'ON RATE LE PLUS SOUVENT :
    L'ALIGNEMENT. Il ne suffit pas que SPF et DKIM disent « pass ». Il faut que
    le domaine qu'ils authentifient soit LE MÊME que celui du « From: ».
    Si le message part de contact@acdcformation.com mais que DKIM signe avec
    « d=mailjet.com », alors DKIM passe et DMARC échoue quand même — parce que
    Mailjet a prouvé son identité, pas celle d'ACDC. C'est le cas de figure
    typique d'un relais branché sans avoir validé le domaine chez lui.

1.3 — QUE DIT LA ZONE DNS AUJOURD'HUI ?
Interroge les enregistrements publics des deux domaines. Ça se fait sans aucun
accès au panneau d'hébergement — ce sont des données publiques :

  dig TXT acdcformation.com                    (SPF)
  dig TXT _dmarc.acdcformation.com             (DMARC)
  dig TXT mailjet._domainkey.acdcformation.com (DKIM Mailjet)

et les mêmes pour acdc-formation.com. Si tu n'as pas « dig », les services
MXToolbox ou dmarcian rendent la même chose dans un navigateur.

Relève pour chaque domaine : y a-t-il un SPF ? UN SEUL ? (deux enregistrements
SPF sur un même domaine invalident les deux, c'est une erreur classique et
silencieuse.) Mailjet y figure-t-il ? Y a-t-il un DMARC, et que dit sa politique ?

1.4 — QUE DIT MAILJET ?
Dans le compte Mailjet, ouvre la section des domaines et adresses expéditrices.
Le domaine d'envoi y est-il présent, et surtout : est-il VALIDÉ, avec SPF et
DKIM au vert de leur côté ? Un domaine ajouté mais non validé se comporte
exactement comme un domaine absent.

RENDS CETTE ÉTAPE À DAVID AVANT DE CONTINUER. Un tableau : ce qui est en place,
ce qui manque, et ta conclusion sur la cause. Puis attends son feu vert.

════════════════════════════════════════════════
ÉTAPE 2 — LES CORRECTIONS, ÉCRITES POUR ÊTRE COLLÉES
════════════════════════════════════════════════

Rends les enregistrements EXACTS à créer : le nom, le type, et la valeur
complète, dans un bloc de code, prêts à copier. Pas de description en prose du
genre « ajouter Mailjet au SPF » — la valeur entière, telle qu'elle doit être
saisie.

Trois choses, dans cet ordre. Ne saute aucune étape : chacune dépend de la
précédente.

2.1 — SPF : UN SEUL ENREGISTREMENT, QUI AUTORISE MAILJET
Il ne doit exister qu'UN enregistrement SPF par domaine. S'il en existe déjà un,
on le COMPLÈTE, on n'en ajoute pas un second. Il autorise à la fois le serveur
N0C (pour le courrier du site lui-même) et Mailjet.

Termine par « ~all » et non « -all » tant que la mise au point n'est pas finie :
« -all » demande un rejet ferme, et si un serveur légitime a été oublié, ses
messages disparaissent sans laisser de trace lisible.

2.2 — DKIM : LA SIGNATURE, AU NOM DU DOMAINE D'ACDC
C'est la correction qui compte le plus, parce que c'est elle qui règle
l'alignement. Récupère l'enregistrement DKIM que Mailjet fournit pour ce domaine
et fais-le poser dans la zone. Puis déclenche la validation côté Mailjet et
attends qu'elle passe au vert.

La clé publique est longue et se coupe facilement à la copie : vérifie qu'elle
est intégrale, du premier au dernier caractère.

2.3 — DMARC : EN OBSERVATION D'ABORD
Pose une politique « p=none » avec une adresse de rapport. « none » ne bloque
rien : elle demande seulement aux boîtes destinataires d'envoyer des rapports.
C'est exactement ce qu'on veut au début — on regarde une à deux semaines qui
envoie au nom du domaine, on vérifie que tout est aligné, ET SEULEMENT APRÈS on
durcit vers « p=quarantine ».

Durcir tout de suite est le geste qui coûte cher : si une source légitime a été
oubliée — un formulaire du site vitrine, une facturation externe, un outil de
prise de rendez-vous — ses messages cessent d'arriver, et personne ne fait le
lien avec un changement DNS d'il y a trois jours.

DIS EXPLICITEMENT À DAVID qu'il faudra revenir durcir la politique plus tard,
et à quelle date tu recommandes de le faire.

════════════════════════════════════════════════
ÉTAPE 3 — LA PREUVE
════════════════════════════════════════════════

Le DNS met de quelques minutes à quelques heures à se propager. N'annonce aucun
succès avant d'avoir vérifié.

3.1 — Renvoie un message de test depuis le plugin, vers la même adresse Gmail
qu'à l'étape 1.2. Ouvre l'en-tête complet et relève de nouveau les trois
verdicts. Il faut les trois « pass », ET « d= » portant le domaine d'ACDC, pas
celui de Mailjet.

3.2 — Fais passer un test à mail-tester.com : tu y récupères une adresse
jetable, tu envoies le message de test du plugin dessus, tu lis la note. Vise 9
ou 10 sur 10. Rappel : ce message ne contient aucune donnée personnelle.

3.3 — Vérifie où il ARRIVE. Boîte de réception, ou indésirables ? C'est la seule
question qui intéresse David. Un score de 10/10 dans un dossier spam ne vaut
rien, et cela arrive : la note mesure la conformité technique, pas la réputation
de l'expéditeur.

  → SI LE MESSAGE PASSE LES TROIS CONTRÔLES MAIS RESTE EN SPAM, dis-le
    franchement plutôt que de conclure au succès. Cela signifie que la
    réputation du domaine ou de l'IP d'envoi est en cause, et c'est un autre
    travail : montée en charge progressive, vérification des listes noires,
    parfois une IP dédiée. Ne le fais pas de ta propre initiative — décris-le et
    laisse David décider.

════════════════════════════════════════════════
CE QUE TU RENDS
════════════════════════════════════════════════

Un rapport court, préfixé [QA-DELIV], en quatre parties :

1. L'ÉTAT AVANT — les trois verdicts relevés à l'étape 1.2, le domaine « d= »
   observé, et ce que disait la zone DNS.
2. LA CAUSE — en une phrase. « DKIM signait avec mailjet.com au lieu
   d'acdcformation.com, donc DMARC échouait malgré un SPF valide » est une
   cause. « Problème de configuration e-mail » n'en est pas une.
3. CE QUI A ÉTÉ POSÉ — les enregistrements, en clair.
4. L'ÉTAT APRÈS — les trois verdicts à nouveau, la note mail-tester, et surtout :
   le message est-il arrivé en boîte de réception ? Réponds par oui ou par non.

Si tu n'as pas pu terminer, dis à quelle étape tu t'es arrêté et pourquoi. Un
rapport qui s'arrête honnêtement au milieu vaut mieux qu'un rapport qui conclut.

Une dernière chose : si à un moment tu constates que ce que tu vois contredit ce
que ce prompt annonce, c'est ce que tu vois qui a raison. Dis-le, et demande.
```

---

## Ce que je ferai de votre côté, quand l'agent aura rendu

Deux choses que le plugin peut apprendre de ce diagnostic, et que je câblerai si
le résultat le justifie :

- **Une alerte de délivrabilité.** Aujourd'hui, si les messages partent en spam,
  rien ne vous le dit — vous l'apprenez parce qu'un apprenant n'a pas répondu.
  Le plugin sait déjà surveiller l'âge de la dernière sauvegarde réussie et vous
  alerter ; il peut faire de même sur les enquêtes restées sans réponse au-delà
  d'un délai. Un silence anormal est une information.
- **L'expéditeur affiché.** Si l'agent conclut que l'adresse d'expédition est en
  cause, le profil d'entreprise doit refuser une adresse hors domaine, ou au
  moins le signaler à l'écran. Un réglage qui accepte une valeur qui ne peut pas
  fonctionner est un piège tendu à celui qui le remplit.
