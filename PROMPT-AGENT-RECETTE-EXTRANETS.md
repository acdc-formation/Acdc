# Prompt agent — recette complète des extranets et du back-office (v2)

À coller **dans l'onglet de l'agent**. Mission d'exploration et de rapport.
**Aucune correction n'est demandée à l'agent** : je les ferai après son rapport.

> **v2 — ce qui change.** Le « point zéro » de la v1 est ANNULÉ : mon hypothèse
> sur le `.htaccess` était fausse, je l'ai vérifiée dans le code et je l'explique
> ci-dessous. Le fichier reste en place. Le 403 après signature a une autre
> cause, à chercher dans un vrai navigateur.

---

## Préalable côté David — l'agent est aveugle sans ça

L'agent n'a pas la main sur l'onglet : ses appels retournent
`anchor tab group not established`. **Rouvrez le panneau latéral depuis l'onglet
cible** (l'extranet formateur), pour que le groupe s'ancre. Sans ça, il ne peut
rien parcourir.

Son client HTTP, lui, est refusé par la couche de sécurité du site : il obtient
403 sur presque tout, y compris sur le favicon que votre navigateur affiche sans
problème. **Toute mesure faite hors navigateur est donc sans valeur** — il l'a
constaté lui-même et a eu raison d'annuler ses propres résultats.

---

```
[MISSION — RECETTE COMPLÈTE. EXPLORATION ET RAPPORT, PAS DE CORRECTION.]

Tu as accès au back-office ACDC (https://acdcformation.com/) et aux deux
extranets — celui du formateur et ceux des apprenants. David t'ouvre tous les
onglets. Ta mission : TOUT parcourir, comprendre le processus, et me remonter un
rapport détaillé de ce qui fonctionne comme de ce qui ne fonctionne pas.

Tu ne corriges RIEN. Tu observes, tu vérifies, tu décris.

════════════════════════════════════════════════════════════
D'ABORD : DEUX CHOSES SUR TON RAPPORT PRÉCÉDENT
════════════════════════════════════════════════════════════
1. Tu as bien fait d'annuler tes propres mesures. Un 403 sur le favicon du site
   prouvait que la couche de sécurité refusait TON AGENT, pas le fichier. Si tu
   m'avais écrit « confirmé », David supprimait un fichier sur un artefact.

2. MON HYPOTHÈSE ÉTAIT FAUSSE, et le point zéro est annulé. Je l'ai vérifiée
   dans le code, pas en te la faisant tester. Voici le contenu exact du
   .htaccess — je te le donne, c'est moi qui te l'avais dicté :

   # ACDC — Les images de ce dossier ne sont jamais servies par URL.
   # Les PDF de certificat, si : ils sont remis au signataire depuis son portail.
   <FilesMatch "\.(png|jpe?g|gif|webp|bmp|tiff?)$">
   … Require all denied / Deny from all …
   </FilesMatch>
   <FilesMatch "^id-">
   … Require all denied / Deny from all …
   </FilesMatch>

   Il refuse les IMAGES et les fichiers dont le nom commence par « id- ».
   Il ne touche PAS aux PDF. Le certificat « certificat-3-…pdf » n'est donc
   bloqué par aucune des deux règles. La chronologie l'exclut aussi : le contrat
   formateur a été signé le 16/08, ce fichier a été écrit le 17/08 à 13h18.

   J'ai aussi vérifié qu'il ne casse aucun affichage : les signatures montrées à
   l'écran viennent de « acdc-emargement/ », les cachets de « uploads/2026/04/ ».
   LE FICHIER RESTE EN PLACE. Ne le supprime pas.

   Cette mission est donc entièrement en LECTURE. Aucune écriture autorisée.

════════════════════════════════════════════════════════════
LE DOSSIER À SUIVRE
════════════════════════════════════════════════════════════
Un parcours complet a été joué de bout en bout. C'est ton fil conducteur :

  Formation ...... « L'Intelligence Artificielle appliquée à votre métier —
                     Formation opérationnelle en 2 jours » (présentiel)
  Séances ........ 14/08/2026 et 15/08/2026, 09:00–12:30 et 13:30–17:00
  Entreprise ..... Skill Conseil (signataire : Bérengère Valériano)
  Financeur ...... TEST — AFDAS, subrogation active, 1200 € pris en charge
                   sur 1800 € HT — donc DEUX factures liées attendues
  Formateur ...... David Contal — dcontal@acdc-formation.com
  Apprenants ..... Ilona Rossa <info@davidcontal.com>
                   Léandra Rossa <david@davidcontal.com>
                   Bérengère Valériano <contact@davidcontal.com>
  Chaîne ......... Recueil n°2 → Devis n°1 → Convention n°1 → Séances

TOUTES ces adresses appartiennent à David. Tu peux déclencher des envois sans
risque pour un tiers — mais VÉRIFIE l'adresse avant chaque envoi, et n'envoie
jamais vers une adresse absente de cette liste.

════════════════════════════════════════════════════════════
TOUT SE FAIT DANS LE NAVIGATEUR
════════════════════════════════════════════════════════════
La couche de sécurité du site refuse ton client HTTP : tu obtiens 403 sur
presque tout, favicon compris. N'utilise donc PAS la récupération web pour
mesurer quoi que ce soit sur ce site — ni codes HTTP, ni contenu de fichier.
Tout passe par l'onglet, comme le ferait David.

Ton observation « une URL inexistante renvoie 403 au lieu du 404 WordPress »
vient probablement du même filtre. Retente-la DANS LE NAVIGATEUR : si elle se
confirme là, c'est un vrai point à signaler ; sinon, c'était un artefact.

════════════════════════════════════════════════════════════
LE 403 APRÈS SIGNATURE — À REPRODUIRE, PAS À DÉDUIRE
════════════════════════════════════════════════════════════
Après avoir signé le contrat formateur, David a obtenu une page « 403 Forbidden »
de LiteSpeed. Ce que je sais du code : après une signature réussie, le plugin
redirige vers une PAGE WordPress, « …?sig=<jeton>&signed=1 », et non vers un
fichier. Le document et le certificat ont bien été produits — donc l'envoi a
abouti et c'est l'affichage d'après qui échoue.

Reproduis-le dans le navigateur : fais signer un document de test et observe la
page d'arrivée. Note l'adresse exacte, ce qui s'affiche, et si le document reste
accessible ensuite depuis le portail. Si le 403 ne se reproduit pas, dis-le —
c'est une information aussi.

Piste à garder en tête : une couche de sécurité (WAF, LiteSpeed, extension) peut
refuser une requête à cause de la taille ou de la forme de ce qui est envoyé —
la signature manuscrite part en base64 et c'est un gros bloc de données.

════════════════════════════════════════════════════════════
CE QUE TU DOIS PARCOURIR
════════════════════════════════════════════════════════════
A. LE BACK-OFFICE — chaque onglet ET chaque sous-onglet du menu de gauche :
   Tableau de bord · Commercial · Workflow · CRM · Config. pré-formation ·
   Répertoires · Évaluation & Enquêtes (Avant / Pendant / Suivi global / Après /
   Enquêtes par public / Qualité & conformité) · Actions de formation ·
   Inscription / Suivi · et tout ce que tu trouveras en dessous.

   Pour CHACUN : ouvre-le, comprends à quoi il sert, vérifie que les données du
   dossier ci-dessus s'y affichent correctement, note ce qui cloche.

B. L'EXTRANET DU FORMATEUR — ses onglets sont : Tableau de bord, Mes sessions,
   Mes quiz, Résultats, Ma bibliothèque, Mes contrats, Mes disponibilités,
   Mon profil. Parcours-les tous, y compris le bilan post-formation en bas de
   « Mes sessions » (niveau du groupe, objectifs atteints, incidents,
   recommandations) : vérifie qu'il s'enregistre et qu'il ressort quelque part
   côté organisme.

C. LES TROIS EXTRANETS APPRENANTS — un par un, les trois. Chacun a reçu une
   ouverture d'extranet le 18/08 à 00:48. Vérifie ce que chaque apprenant voit :
   documents, convocation, programme, émargement, enquêtes, attestation.

════════════════════════════════════════════════════════════
LES DOUZE POINTS RELEVÉS PAR DAVID — À VÉRIFIER UN PAR UN
════════════════════════════════════════════════════════════
Confirme ou infirme chacun, en disant OÙ tu l'as constaté.

 1. Le recueil des besoins se télécharge sous « recueil-des-besoins-2 ».
    Attendu : « recueil-des-besoins — Nom de l'entreprise ».
 2. Le devis se télécharge sous « Modèle de devis - ACDC-Formation ».
    Attendu : « Devis — Nom de l'entreprise — N° du devis — Date du devis ».
 3. Le formateur choisi dans la PROPOSITION COMMERCIALE ne se reporte pas
    automatiquement dans la convention.
 4. En bas de la convention : deux petites icônes là où David veut les mêmes
    boutons qu'ailleurs. Décris ce qui est affiché aujourd'hui, où, et à quoi
    ressemblent les boutons de référence sur les autres écrans.
 5. Le lien du PROGRAMME DE FORMATION est absent de l'e-mail de convention, qui
    ne propose que « Convention / contrat de formation » et « Règlement
    intérieur ». Cherche si le programme est proposé ailleurs (extranet
    apprenant, convocation) ou nulle part.
 6. La convention se télécharge sous « Convention-LIntelligence-…-14082026 ».
    Attendu : le nom de l'entreprise EN TÊTE du nom de fichier.
 7. Idem pour la convention SIGNÉE.
 8. Le 403 après signature — voir la section dédiée plus haut.
 9. Les convocations se téléchargent sous « convocation-3-28d02c12b32ad4a251be ».
    Attendu : « convocation-Nom et Prénom de l'apprenant-<jeton> ».
10. Dépôt de document par le formateur dans l'extranet des apprenants.
    LA MOITIÉ « E-MAIL » EST DÉJÀ EXPLIQUÉE, ne la cherche pas : j'ai vérifié le
    code. L'avis par e-mail ne part QUE si la visibilité du document est « Tout
    de suite ». Les trois documents déposés sont en « À la fin de la formation »,
    donc aucun e-mail ne pouvait partir — et la case reste cochable sans aucun
    message. C'est un défaut d'interface, pas d'envoi ; je le corrigerai.
    CE QUI RESTE À VÉRIFIER, ET C'EST LÀ QUE J'AI BESOIN DE TOI : les documents
    déposés sont-ils VISIBLES dans les extranets des apprenants ? Le portail
    formateur affiche « Séance terminée : les apprenants ont accès à l'ensemble
    de leurs documents », et trois documents y figurent (un reçu nominatif pour
    Bérengère Valériano, une attestation de droits et la convention, ces deux
    dernières pour toute la séance). Ouvre les trois extranets apprenants et
    dis-moi qui voit quoi. Vérifie en particulier que le document NOMINATIF
    n'apparaît QUE chez sa destinataire.
11. Ce qui a été convenu dans la CONVENTION sur le règlement et le financeur
    n'est pas repris quand on transforme le devis en facture. Rappel :
    subrogation active, 1200 € pris en charge sur 1800 € HT, référence
    « Accord 52458-975/FX ». Deux factures liées sont attendues — une au
    financeur, une au client pour le reste à charge. Vérifie ce qui est produit.
12. Les enquêtes FINANCEUR et ENTREPRISE ne sont pas reçues.

════════════════════════════════════════════════════════════
UNE PISTE QUE JE TE DONNE, À CREUSER
════════════════════════════════════════════════════════════
Dans Workflow → Suivi des parcours, plusieurs étapes sont « En attente d'un
préalable » avec cette observation :

  « En attente du module Enquêtes : le moteur ordonne, le module exécute.
    L'envoi n'est pas confirmé par le module. »

C'est le cas des quatre enquêtes à chaud (apprenants, entreprise, financeur,
formateur) prévues le 17/08 à 17:00, ET des trois analyses du besoin prévues le
18/08 à 01:00. Toutes en attente, alors que leur date est passée.

Cela recoupe les points 12 et 10. Comprends ce que veut dire cet état : le
moteur a-t-il vraiment demandé l'envoi ? le module a-t-il essayé ? y a-t-il une
trace d'échec (Journal, file d'attente, journal d'erreurs) ? Le module attend-il
un préalable qui n'arrivera jamais ? Regarde aussi les onglets « Journal »,
« À faire » et « Émargements orphelins ».

Ne te contente pas de constater l'état : cherche POURQUOI.

════════════════════════════════════════════════════════════
NE PERDS PAS DE TEMPS SUR LES QUIZ
════════════════════════════════════════════════════════════
Les quiz ont une validité de 2 jours. Les séances étant datées des 14 et 15
août, ils avaient disparu de l'extranet formateur au moment du test. Ce n'est
pas un défaut : IGNORE-LES. Signale seulement ce que l'interface affiche à leur
place — un message clair, ou rien.

════════════════════════════════════════════════════════════
COMMENT TRAVAILLER
════════════════════════════════════════════════════════════
— FOUILLE. Ouvre, clique, redescends, compare deux écrans entre eux. Cherche à
  comprendre le PROCESSUS : ce qui déclenche quoi, ce qui attend quoi.
— Pour chaque anomalie : OÙ (écran, onglet, adresse), ce que tu ATTENDAIS, ce
  que tu as OBSERVÉ, ce que tu en déduis. Un constat sans son adresse est
  inexploitable.
— Note aussi CE QUI MARCHE. Ça m'évitera de « corriger » ce qui va bien.
— Si un écran te paraît incohérent sans que tu saches dire pourquoi, dis-le
  quand même, avec tes mots.
— Si une de mes consignes se contredit ou te paraît fausse, tranche toi-même,
  garde-fou d'abord, et dis-le-moi. Tu as eu raison trois fois sur trois.

INTERDITS : aucune écriture, aucune suppression, aucun réglage modifié, aucune
sauvegarde ni restauration, aucune signature à la place de quelqu'un, aucun mot
de passe saisi, aucun CAPTCHA franchi.

════════════════════════════════════════════════════════════
LE RAPPORT
════════════════════════════════════════════════════════════
Écris à Claude Code, préfixé [QA-DELIV]. Il peut être long — c'est voulu.

  1. Les douze points, dans l'ordre, chacun tranché : CONFIRMÉ / INFIRMÉ /
     PARTIEL, avec ce que tu as vu.
  2. Le back-office, onglet par onglet et sous-onglet par sous-onglet.
  3. L'extranet formateur.
  4. Les trois extranets apprenants.
  5. Ce que tu as compris du blocage « En attente d'un préalable ».
  6. Le 403 après signature : reproduit ou non, et dans quelles conditions.
  7. Ce qui fonctionne bien — la liste, franchement.
  8. Ce que tu n'as pas pu vérifier, et pourquoi.

Prends le temps qu'il faut. Un rapport complet vaut mieux qu'un rapport rapide :
je ne commence les corrections qu'après l'avoir lu.
```

---

## Notes pour David

**Le point zéro de la v1 est annulé, et je m'étais trompé.** J'avais écrit que le
403 venait « très probablement » de ma règle. Vérification faite dans le code :
elle refuse les images et les fichiers en `id-`, pas les PDF — votre certificat
n'est concerné par aucune des deux. Et le contrat formateur a été signé le 16,
la règle écrite le 17. **Ne supprimez pas ce fichier**, il protège les signatures
manuscrites sans rien casser.

Le 403 après signature reste donc entier, et il faut le reproduire dans un vrai
navigateur — le code montre que la redirection va vers une page WordPress, pas
vers un fichier, ce qui oriente plutôt vers une couche de sécurité du serveur.
