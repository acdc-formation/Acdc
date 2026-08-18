# Prompt agent — recette des extranets et du back-office (v3)

À coller **dans l'onglet de l'agent**.

> **v3 — ce qui change.** Les deux versions précédentes demandaient à l'agent de
> re-constater les douze points relevés par David. C'était la mauvaise approche :
> c'est moi qui ai écrit ce code, donc c'est à moi de décrire **ce qui doit se
> passer**. L'agent n'a qu'à vérifier et noter les écarts.

---

## Préalable côté David

L'agent n'a pas la main sur l'onglet (`anchor tab group not established`).
**Rouvrez le panneau latéral depuis l'onglet cible** pour que le groupe s'ancre.

---

```
[MISSION — VÉRIFICATION. TU EXPLORES ET TU NOTES, TU NE CORRIGES RIEN.]

Ton rôle est simple : je te décris ci-dessous COMMENT L'APPLICATION EST CENSÉE
FONCTIONNER, écran par écran. Tu ouvres chaque écran, tu compares avec ce que tu
vois, et tu me notes tout écart.

Tu ne corriges rien. Tu ne modifies rien. Tu ne cherches pas la cause dans le
code — c'est mon travail. Tu constates, tu décris, tu me remontes.

Tout se fait DANS LE NAVIGATEUR : la couche de sécurité du site refuse ton
client HTTP, donc toute mesure faite hors navigateur est sans valeur.

════════════════════════════════════════════════════════════
LE DOSSIER QUI SERT DE FIL
════════════════════════════════════════════════════════════
  Formation ... « L'Intelligence Artificielle appliquée à votre métier —
                  Formation opérationnelle en 2 jours » (présentiel)
  Séances ..... 14 et 15/08/2026, 09:00–12:30 et 13:30–17:00
  Entreprise .. Skill Conseil — signataire Bérengère Valériano
  Financeur ... TEST — AFDAS, subrogation active, 1200 € pris en charge
                sur 1800 € HT
  Formateur ... David Contal — dcontal@acdc-formation.com
  Apprenants .. Ilona Rossa <info@davidcontal.com>
                Léandra Rossa <david@davidcontal.com>
                Bérengère Valériano <contact@davidcontal.com>
  Chaîne ...... Recueil n°2 → Devis n°1 → Convention n°1 → Séances

Ces adresses appartiennent toutes à David : tu peux déclencher des envois sans
risque. Vérifie quand même l'adresse avant chaque envoi.

════════════════════════════════════════════════════════════
LE PROCESSUS COMPLET — CE QUI DOIT S'ENCHAÎNER
════════════════════════════════════════════════════════════
Voici la chaîne telle que je l'ai codée. Chaque étape doit produire la suivante,
et chaque document produit doit se retrouver AU MOINS à deux endroits : dans le
back-office, et dans l'extranet de la personne concernée.

 1. RECUEIL DU BESOIN. Saisi côté organisme. Produit un PDF téléchargeable, et
    doit être envoyé aux apprenants pour qu'ils le complètent (« analyse du
    besoin »). Chaque apprenant doit le retrouver dans SON extranet.
 2. PROPOSITION COMMERCIALE / DEVIS. Généré depuis le recueil. Envoyable en
    signature électronique. Le formateur désigné dans la proposition doit être
    celui qui se retrouve ensuite sur la convention.
 3. CONVENTION (ou contrat). Générée depuis la proposition. Envoyée par e-mail
    au signataire avec, en pièces ou en liens : la convention, le règlement
    intérieur, ET le programme de formation. Signature électronique → la
    convention signée revient dans le dossier, et un certificat d'audit est
    produit.
 4. CONTRAT DE SOUS-TRAITANCE FORMATEUR. Même mécanique de signature, côté
    formateur, visible dans « Mes contrats » de son extranet.
 5. SÉANCES. Créées à partir des dates de la convention. Chaque séance a une
    demi-journée matin et une après-midi.
 6. CONVOCATIONS. Une par apprenant, avec ses nom et prénom, envoyée par e-mail
    et déposée dans son extranet.
 7. ÉMARGEMENT. Le formateur ouvre la feuille (lien ou QR code) ; les apprenants
    signent ; les signatures apparaissent sur la feuille et dans le dossier.
 8. DOCUMENTS DE SÉANCE. Le formateur dépose des documents pour les apprenants,
    avec une visibilité « Tout de suite » ou « À la fin de la formation », et un
    destinataire (toute la séance, ou une personne).
 9. ENQUÊTES. À chaud (apprenants), entreprise, financeur, formateur. Puis les
    relances. Chacune part par e-mail à son destinataire et remonte ses réponses
    côté organisme.
10. FACTURATION. Le devis se transforme en facture. Avec un financeur en
    subrogation et une prise en charge partielle, DEUX factures liées sont
    attendues : une au financeur pour 1200 €, une au client pour le reste à
    charge, calculé et jamais saisi.
11. ATTESTATIONS ET CERTIFICATS. Produits en fin de parcours, déposés dans
    l'extranet de chaque apprenant.

Si un maillon de cette chaîne ne produit pas le suivant, c'est une anomalie,
même si l'écran ne montre aucune erreur.

════════════════════════════════════════════════════════════
A. EXTRANET DU FORMATEUR — 6 ONGLETS
════════════════════════════════════════════════════════════
Connecte-toi et parcours-les tous. Voici l'attendu de chacun.

TABLEAU DE BORD
  Des cartes de synthèse, dont « Mes sessions à venir », cliquables et menant
  vers l'écran correspondant. Les compteurs doivent correspondre à la réalité du
  dossier ci-dessus.

MES SESSIONS
  La liste de ses séances. En ouvrant une séance, il doit trouver :
   — la feuille d'émargement, avec les signatures des apprenants une fois
     signée, et sa propre signature de formateur ;
   — la liste des apprenants de la séance ;
   — le bloc « Documents remis aux apprenants » : dépôt d'un fichier, avec un
     intitulé, un destinataire (toute la séance ou une personne), une visibilité
     (« Tout de suite » ou « À la fin de la formation »), et une case
     « Prévenir les apprenants par e-mail » ;
   — le « Bilan post-formation » : niveau du groupe, objectifs atteints,
     incidents, recommandations. Il doit s'enregistrer ET ressortir côté
     organisme. Vérifie les deux.
  ATTENDU CONNU : la case « Prévenir par e-mail » n'envoie rien si la visibilité
  est « À la fin de la formation ». C'est voulu côté envoi, mais l'interface ne
  le dit pas — je le corrige. Ne perds pas de temps dessus.

MA BIBLIOTHÈQUE
  Les ressources mises à disposition du formateur par l'organisme. Vérifie
  qu'elles s'ouvrent et se téléchargent réellement.

MES CONTRATS
  Ses contrats de sous-traitance : à signer, puis signés. Un contrat signé doit
  rester consultable, avec son certificat. VÉRIFIE CE QUI S'AFFICHE APRÈS UNE
  SIGNATURE : David a obtenu une page « 403 Forbidden » à ce moment-là. Refais
  une signature de test et note l'adresse exacte de la page d'arrivée et ce
  qu'elle affiche.

MES DISPONIBILITÉS
  Saisie de ses disponibilités. Vérifie qu'elles s'enregistrent et qu'elles sont
  visibles côté organisme.

MON PROFIL
  Ses informations. Vérifie qu'une modification s'enregistre et se reflète
  ailleurs (par exemple sur une convocation ou un contrat).

════════════════════════════════════════════════════════════
B. EXTRANET DES APPRENANTS — 8 ONGLETS, ET TROIS COMPTES
════════════════════════════════════════════════════════════
Fais-le TROIS FOIS, une par apprenant. Le point le plus important est la
CLOISON : chacun ne doit voir que ce qui le concerne.

TABLEAU DE BORD .... synthèse de sa formation.
MES FORMATIONS ..... la formation suivie ; en l'ouvrant, son détail.
MON PLANNING ....... les séances des 14 et 15/08, avec les horaires matin et
                     après-midi, et le lieu (512 Chemin des Negadoux, 83140
                     Six-Fours-Les-Plages).
MES DOCUMENTS ...... classés en catégories. Elles existent toutes dans le code,
                     et tu dois vérifier ce que chacune contient réellement :
                       · Programme de formation
                       · Convocations
                       · Règlements et livret d'accueil
                       · Analyses du besoin
                       · Résultats
                       · Certificats et attestations
                       · Conventions / contrats
                       · Documents de séance
                       · Documents partagés
                     Un document dont la visibilité est « À la fin de la
                     formation » doit apparaître VERROUILLÉ tant que la séance
                     n'est pas terminée, puis devenir consultable ensuite.
                     La séance étant terminée, tout doit être ouvert.
MA BIBLIOTHÈQUE .... les ressources partagées.
MES QUIZ ........... HORS PÉRIMÈTRE, voir plus bas.
MES SIGNATURES ..... ses demandes de signature, avec un état : En attente,
                     Ouvert, Signé, Refusé ou Expiré.
MON PROFIL ......... ses informations.

À VÉRIFIER EN PRIORITÉ DANS CES TROIS EXTRANETS :
  — Le document NOMINATIF déposé par le formateur pour Bérengère Valériano
    (« Recu_2026-08-06_122512.pdf ») ne doit apparaître QUE chez elle.
  — Les deux documents déposés pour toute la séance
    (« AttestationDeDroits_2026-07-02.pdf » et la convention) doivent apparaître
    chez les TROIS.
  — La convocation de chaque apprenant doit porter SON nom.
  — Le programme de formation doit être présent.

════════════════════════════════════════════════════════════
C. LE BACK-OFFICE — CHAQUE ONGLET ET SOUS-ONGLET
════════════════════════════════════════════════════════════
Parcours tout le menu de gauche. Voici les écrans qui existent, regroupés comme
dans le menu. Pour chacun : ouvre-le, comprends à quoi il sert, vérifie que les
données du dossier s'y retrouvent, et note ce qui cloche.

  Tableau de bord
  Commercial ......... Prospects · Suivi commercial · Calendrier prospects ·
                       Rdv préalables · Recueil des besoins · Analyses du besoin
  Workflow ........... Suivi des parcours · À faire · Journal ·
                       Émargements orphelins · Configuration
  CRM
  Config. pré-formation
  Répertoires ........ Apprenants · Formations · Groupes · Entreprises ·
                       Financeurs · Formateurs · Contacts
  Évaluation & Enquêtes  Enquêtes à chaud · à froid · à mi-parcours ·
                       entreprise · financeur · formateur · Résultats ·
                       Sessions de questionnaire · Réglages · Réclamations
  Actions de formation  Calendrier des séances · Séances en cours de validation ·
                       Séances validées · Dossiers de formation
  Inscription / Suivi   Inscription formation · Convention / contrat · Documents
  Portail apprenant .. pilotage des accès extranet
  Réglages ........... Réglages organisme · Configuration · Données & maintenance ·
                       UI / Design système · Variables CSS · Utilisateurs

Si tu trouves un écran que je n'ai pas listé, ouvre-le aussi et dis-le-moi.

DEUX ÉCRANS À REGARDER DE PRÈS :

  WORKFLOW → SUIVI DES PARCOURS. Chaque étape a un état. Plusieurs sont
  bloquées sur « En attente d'un préalable » avec l'observation « le moteur
  ordonne, le module exécute. L'envoi n'est pas confirmé par le module » : les
  quatre enquêtes à chaud du 17/08 17:00 et les trois analyses du besoin du
  18/08 01:00. Leur date est passée. Note l'état EXACT de chaque ligne, et
  regarde si le Journal garde une trace de tentative ou d'échec.

  INSCRIPTION / SUIVI → CONVENTION. En bas de la convention, note précisément
  quels boutons ou icônes sont proposés, et compare avec ce qui est proposé en
  bas des AUTRES écrans du même type (devis, proposition). David trouve qu'ici
  ce sont deux petites icônes là où ailleurs ce sont des boutons.

════════════════════════════════════════════════════════════
LES NOMS DE FICHIERS — À RELEVER SYSTÉMATIQUEMENT
════════════════════════════════════════════════════════════
Chaque fois que tu télécharges un document, NOTE LE NOM DU FICHIER OBTENU. C'est
un sujet à part entière : plusieurs ne portent pas le nom attendu. Fais-le pour
le recueil des besoins, le devis, la proposition, la convention, la convention
signée, les convocations, les attestations, les certificats et les factures.

Donne-moi la liste brute : « écran → nom du fichier obtenu ». Sans commentaire,
je m'occupe de dire ce qui devrait s'appeler comment.

════════════════════════════════════════════════════════════
HORS PÉRIMÈTRE — LES QUIZ
════════════════════════════════════════════════════════════
Les quiz ont 2 jours de validité. Les séances datant des 14 et 15 août, ils
avaient disparu au moment du test. Ce n'est PAS un défaut : ne perds pas de
temps dessus. Note seulement ce que l'interface affiche à leur place — un
message clair, ou rien du tout.

════════════════════════════════════════════════════════════
COMMENT NOTER UNE ANOMALIE
════════════════════════════════════════════════════════════
Pour chacune, quatre lignes :
  OÙ ......... l'écran, l'onglet, et l'adresse exacte
  ATTENDU .... ce que la description ci-dessus annonce
  OBSERVÉ .... ce que tu as réellement vu
  GRAVITÉ .... bloquant / gênant / cosmétique, selon toi

Un constat sans son adresse est inexploitable pour moi.

FOUILLE. Ouvre, clique, redescends, compare deux écrans entre eux. Si un écran
te paraît incohérent sans que tu saches dire pourquoi, dis-le quand même avec
tes mots — je préfère une intuition floue à un silence.

INTERDITS : aucune suppression, aucun réglage modifié, aucune sauvegarde ni
restauration, aucune signature à la place de quelqu'un, aucun mot de passe
saisi, aucun CAPTCHA franchi.

════════════════════════════════════════════════════════════
LE RAPPORT
════════════════════════════════════════════════════════════
Écris à Claude Code, préfixé [QA-DELIV]. Il peut être long — c'est voulu.

  1. Les anomalies, classées par gravité, au format ci-dessus.
  2. L'extranet formateur, onglet par onglet.
  3. Les trois extranets apprenants, et ce que chacun voit.
  4. Le back-office, onglet par onglet et sous-onglet par sous-onglet.
  5. La liste brute des noms de fichiers téléchargés.
  6. Ce qui FONCTIONNE — franchement, la liste. Ça m'évite de « corriger » ce
     qui va bien.
  7. Ce que tu n'as pas pu vérifier, et pourquoi.

Prends le temps qu'il faut. Je ne commence les corrections qu'après ton rapport.
```

---

## Note pour David

Ce prompt ne lui donne plus vos douze points : il lui donne **le fonctionnement
attendu**, et il n'a qu'à comparer. Vos douze points seront forcément retrouvés
s'ils sont réels — et il en trouvera d'autres, ce qui est le but.
