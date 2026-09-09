# Prompt de recette — le moteur de workflow (3.25.185)

À coller **dans l'onglet de l'agent**. Recette d'un module neuf, qui planifie mais n'envoie pas encore.

---

```
[RECETTE — MODULE WORKFLOW, VERSION 3.25.185]

Tu recettes un module NEUF : « Workflow », premier onglet de la section
Commercial dans l'extranet. Il orchestre le parcours complet d'un dossier, du
recueil des besoins jusqu'aux enquêtes de fin de formation.

Les règles de ton briefing d'origine restent intégralement en vigueur :
financeurs réels en consultation stricte, aucun envoi vers une entité que tu
n'as pas créée, aucune création d'utilisateur, préfixe TEST-QA sur tout ce que
tu crées, N0C en lecture seule, et le bloc « Protection des données » de
Paramètres > Réglages ne se clique JAMAIS, sous aucun prétexte.

Tu écris à Claude Code, préfixé [QA-VERIF].

════════════════════════════════════════════════
CE QU'IL FAUT COMPRENDRE AVANT DE COMMENCER
════════════════════════════════════════════════
Cette version POSE LE MOTEUR. Elle ne branche AUCUN envoi.

Le moteur lit l'état réel d'un dossier — proposition, devis, convention, séance,
apprenants — et en déduit la liste des étapes du parcours avec leur DATE PRÉVUE.
Il journalise ce qu'il aurait envoyé. Il n'envoie rien.

Tu ne recettes donc PAS des e-mails. Tu recettes UN PLAN : les bonnes étapes,
aux bonnes dates, dans le bon ordre, qui apparaissent et disparaissent quand
elles le doivent.

🚨 INTERDICTION ABSOLUE, PROPRE À CETTE RECETTE
Dans Workflow > Configuration, tu ne décoches JAMAIS « Mode simulation ».
Ce n'est pas une précaution : dans cette version, aucun traitement n'est branché.
Décocher la simulation ferait passer chaque étape en échec, salirait le journal
et te ferait rapporter vingt faux défauts. Si tu la décoches par accident,
recoche-la immédiatement et signale-le dans ton rapport.

Tu ne décoches pas non plus « Mode recette » : c'est lui qui empêche un envoi
d'atteindre un vrai financeur le jour où les envois seront branchés.

UN OUTIL UTILE : le moteur tourne par cron toutes les quinze minutes, et un cron
WordPress ne se déclenche qu'au passage d'un visiteur. Pour forcer un passage
immédiat, il te suffit de REVENIR SUR WORKFLOW > CONFIGURATION ET DE CLIQUER
« ENREGISTRER » : l'enregistrement déclenche un tour complet du moteur. Sers-t'en
chaque fois que tu attends qu'un plan se mette à jour.

════════════════════════════════════════════════
ACTE 0 — LE PLUGIN S'EST-IL INSTALLÉ NORMALEMENT
════════════════════════════════════════════════
Les deux dernières mises à jour ont été très longues à s'installer ; la 3.25.184
corrige cette cause. Avant toute chose, en trois observations courtes :

 0.1 La page d'accueil publique répond-elle en moins de 5 secondes ?
 0.2 L'extranet s'ouvre-t-il normalement, et en combien de temps environ ?
 0.3 Une bannière rouge « migration de base incomplète » apparaît-elle dans
     l'administration ? Si oui, RECOPIE-LA et ARRÊTE TOUT : ne clique pas sur
     « Relancer la migration », c'est une décision de David.

════════════════════════════════════════════════
ACTE 1 — MISE EN SERVICE
════════════════════════════════════════════════
Va dans Workflow > Configuration.

 1.1 Décris ce que tu vois EN ARRIVANT, avant toute modification : quel bandeau
     s'affiche en haut (arrêté / simulation / recette / actif) et quels
     interrupteurs sont cochés. À la première installation le workflow doit être
     À L'ARRÊT. S'il est déjà actif sans que personne ne l'ait activé, c'est un
     défaut grave : signale-le et arrête-toi.

 1.2 Coche « Activer le workflow ». LAISSE « Mode simulation » COCHÉ. LAISSE
     « Mode recette » COCHÉ.

 1.3 Dans « Adresses autorisées », colle exactement ces huit adresses, une par
     ligne :
        contact@davidcontal.com
        info@davidcontal.com
        david@davidcontal.com
        contact@acdc-formation.com
        dcontal@icloud.com
        dcontal@hotmail.com
        contaldavid@gmail.com
        davidcontal@gmail.com

 1.4 Enregistre. Puis :
     - le bandeau annonce-t-il bien le MODE SIMULATION ?
     - reviens sur l'écran : les huit adresses sont-elles toutes revenues, dans
       le même ordre, sans caractère perdu ?
     - les délais affichés correspondent-ils au schéma : devis 10 jours,
       convention 3 jours, convocation 17 h, émargement 30 minutes, enquête à
       chaud 0 heure puis relances 24/48/72, entreprise/financeur/formateur
       24 heures puis relances 3/5/7, à froid 90 jours puis 3/5/7 ?

════════════════════════════════════════════════
ACTE 2 — LES DOSSIERS QUI EXISTENT DÉJÀ
════════════════════════════════════════════════
Le moteur doit rattraper les recueils des besoins déjà en base.

 2.1 Va dans Workflow > Suivi des parcours. Combien de parcours apparaissent ?
     Compare avec le nombre de recueils dans CRM > Recueil des besoins. Le compte
     doit correspondre. S'il manque des dossiers, retourne enregistrer la
     configuration pour forcer un tour de moteur, puis recompte — le rattrapage
     se fait par lots.

 2.2 Pour chacun, relève : le dossier, la phase, la prochaine action et sa date.
     Une prochaine action vide sur un dossier actif est une anomalie.

 2.3 Ouvre le « Détail » de l'un d'eux. La liste des étapes est-elle lisible ?
     Chaque ligne porte-t-elle une phase, un libellé, un état ?

════════════════════════════════════════════════
ACTE 3 — UN PARCOURS COMPLET, DU DÉBUT À LA FIN
════════════════════════════════════════════════
C'est le cœur de la recette. Tu vas fabriquer un dossier de A à Z et vérifier
qu'à CHAQUE étape le plan se met à jour tout seul.

Utilise l'entreprise Skil Conseil, le formateur David Contal, et les trois
apprenantes déjà en base. Préfixe TEST-QA tout ce que tu crées. Pour toute
adresse e-mail qu'un formulaire te demande, prends UNIQUEMENT une adresse de la
liste de l'acte 1.3.

 3.1 Crée un prospect « TEST-QA Workflow ». Va voir le suivi des parcours :
     AUCUN parcours ne doit s'être ouvert. Un prospect n'engage rien.

 3.2 Crée un recueil des besoins rattaché à ce prospect. Retourne au suivi :
     un parcours doit être apparu IMMÉDIATEMENT, sans attendre le cron.
     → Sa prochaine action doit être « Créer la proposition commerciale ».
     → Cette même ligne doit apparaître dans l'onglet « À faire ».

 3.3 Crée la proposition commerciale depuis ce recueil. Force un tour de moteur.
     → La tâche « Créer la proposition » doit avoir DISPARU de « À faire ».
     → Elle doit apparaître dans le Journal, à l'état « Faite », avec la mention
       du numéro de proposition.
     → La nouvelle prochaine action doit être « Créer le devis ».

 3.4 Crée le devis depuis cette proposition. Force un tour.
     → « Créer le devis » se referme, le journal l'enregistre.

 3.5 Envoie le devis en signature électronique. Force un tour. Ouvre le détail
     du parcours.
     → Une étape « Relancer la signature du devis » doit être PLANIFIÉE.
     → Sa date doit être la date d'envoi + 10 jours. Calcule-la et compare.
     → Si ce dixième jour tombe un samedi ou un dimanche, la date affichée doit
       être le LUNDI suivant. Vérifie ce point : c'est une règle explicite.

 3.6 Crée la convention/contrat, en y nommant les trois apprenantes, et envoie-la
     en signature. Force un tour.
     → « Créer la convention » se referme.
     → « Relancer la signature de la convention » apparaît à +3 jours.

 3.7 Fais signer la convention (ou marque-la signée si l'écran le permet sans
     envoyer d'e-mail à un tiers). Force un tour. C'est le moment le plus
     important de la recette : TOUT le reste du parcours doit se déployer d'un
     coup. Ouvre le détail et vérifie la présence de :
       - « Inscrire les apprenants nommés dans la convention »
       - « Ouvrir l'extranet apprenant » — UNE LIGNE PAR APPRENANTE, avec son
         nom complet en destinataire
       - « Envoyer l'analyse des besoins » — également une ligne par apprenante
       - « Déposer le dossier de formation dans l'extranet formateur »
       - « Envoyer la convocation »
       - les rappels d'émargement matin et après-midi
       - les cinq enquêtes et leurs relances
     → Les deux Rossa doivent être DISTINCTES, avec leur nom complet. Un prénom
       seul, ou une seule ligne pour deux personnes, est un défaut.
     → Les relances de la convention et du devis doivent être passées à
       « Sans objet » : la convention est signée, il n'y a plus rien à relancer.

════════════════════════════════════════════════
ACTE 4 — L'ARITHMÉTIQUE DES DATES
════════════════════════════════════════════════
C'est le point le plus susceptible d'être faux, et le seul que personne ne verra
avant que des e-mails ne partent au mauvais moment. Prends le détail du parcours
de l'acte 3, note la date de fin de la formation, et VÉRIFIE CHAQUE CALCUL.

Les relances sont CUMULATIVES : chaque délai part de la relance précédente, pas
de l'envoi initial.

 4.1 Convocation : la veille du PREMIER jour de formation, à 17 h 00. Une seule,
     même si la formation dure trois jours.
 4.2 Émargement : 30 minutes avant chaque demi-journée, matin ET après-midi,
     CHAQUE jour de formation. Compte les lignes : trois jours = six rappels.
 4.3 Enquête à chaud : à la fin de la formation. Puis relances à H+24, H+72
     et H+144 (soit 24, puis 48 de plus, puis 72 de plus). Le cumul est voulu.
 4.4 Enquête entreprise, financeur et formateur : 24 heures après la fin. Puis
     relances à J+3, J+8 et J+15 après leur envoi.
 4.5 Enquête à froid : 90 jours après la fin, puis J+3, J+8, J+15.
 4.6 Toute relance tombant un samedi ou un dimanche doit être décalée au lundi.
     En revanche un ENVOI initial ne se décale pas : s'il tombe un dimanche, il
     reste dimanche. Trouve au moins un cas de chaque et vérifie-le.

Rapporte tes calculs dans un tableau : Étape | Date attendue | Date affichée |
Verdict. C'est le livrable le plus utile de la nuit.

════════════════════════════════════════════════
ACTE 5 — LA BRANCHE FINANCEUR
════════════════════════════════════════════════
 5.1 Sur le dossier de l'acte 3, qui n'a pas de financeur : les cinq étapes de la
     branche financeur (l'enquête et ses trois relances) doivent être à
     « Sans objet », avec la mention « Aucun financeur rattaché au dossier ».
     Elles ne doivent PAS être planifiées.

 5.2 Rattache TEST-QA Financeur Essai au dossier — et LUI SEUL. Les onze autres
     financeurs sont réels : tu ne les ouvres qu'en lecture, tu n'en modifies
     aucun, tu ne leur envoies rien. Force un tour de moteur.
     → L'enquête financeur et ses relances doivent maintenant être PLANIFIÉES,
       aux dates de l'acte 4.4.

════════════════════════════════════════════════
ACTE 6 — LES DÉLAIS SONT-ILS VRAIMENT RÉGLABLES
════════════════════════════════════════════════
 6.1 Dans la configuration, passe la relance du devis de 10 à 4 jours.
     Enregistre. Reviens sur un parcours dont le devis est envoyé et non signé.
     → La date de la relance a-t-elle bougé de 10 à 4 jours après l'envoi ?
     Une étape DÉJÀ JOUÉE, elle, ne doit pas bouger : c'est le passé.

 6.2 Vide complètement le champ « Enquête entreprise — relances ». Enregistre.
     → Les relances entreprise doivent disparaître du plan. Une case vide veut
       dire « ne relance pas », ce n'est pas une erreur de saisie.

 6.3 REMETS ENSUITE LES VALEURS D'ORIGINE : 10 jours pour le devis, 3, 5, 7 pour
     les relances entreprise. Ne laisse pas la configuration dans un état de
     test.

════════════════════════════════════════════════
ACTE 7 — LES GARDE-FOUS ET LES ÉCRANS
════════════════════════════════════════════════
 7.1 Le Journal : chaque ligne dit-elle QUAND, quel dossier, quelle étape, quel
     destinataire, et quoi ? En simulation, la mention doit être explicite sur
     le fait que rien n'a été envoyé. Si une ligne laisse croire qu'un e-mail est
     parti, c'est un défaut grave — signale-le en tête de rapport.

 7.2 Sur une tâche de « À faire », clique « Sans objet ».
     → La tâche quitte la liste, réapparaît au journal avec la mention
       « Écartée manuellement », et NE REVIENT PAS au tour de moteur suivant.
       Vérifie ce dernier point en forçant un tour.

 7.3 L'alerte : une convention envoyée et jamais signée doit finir par produire
     une tâche marquée d'un signe d'alerte. Tu ne pourras pas attendre le délai
     réel ; contente-toi de vérifier que la ligne « Convention non signée : créer
     un rendez-vous » existe bien dans le détail du parcours, planifiée à une
     date future. C'est le trou du schéma d'origine, il ne doit plus être muet.

 7.4 Repasse le workflow À L'ARRÊT, puis réactive-le. Rien ne doit être perdu :
     ni les parcours, ni le journal, ni les réglages.

════════════════════════════════════════════════
CE QUE TU NE PEUX PAS TESTER — dis-le, ne l'invente pas
════════════════════════════════════════════════
Aucun envoi n'est branché dans cette version. Tu ne verras donc jamais arriver :
une relance de signature, une analyse des besoins, une convocation, un rappel
d'émargement, une invitation d'extranet, une enquête. Ce n'est pas un défaut,
c'est le lot suivant. Ne le rapporte pas comme un manque, et ne cherche pas à le
provoquer en décochant la simulation.

Tu ne peux pas non plus vérifier qu'une relance s'arrête à la réception d'une
réponse : là encore, le branchement viendra après.

════════════════════════════════════════════════
COMPTE-RENDU
════════════════════════════════════════════════
Un rapport par acte, au fil de l'eau, préfixé [QA-VERIF].
Tableau : Point | Verdict (✅ / ❌ / ⚠️) | Observation courte.

En tête de rapport, et seulement là, trois familles :
  - toute ligne du journal qui laisserait croire à un envoi réel ;
  - toute date fausse de l'acte 4 ;
  - tout parcours actif sans prochaine action.

Si tu ne dois rapporter qu'une seule chose, c'est le TABLEAU DES DATES de
l'acte 4. C'est lui qui décide si l'on peut ouvrir le robinet.
```

---

## Rappel côté David

- Le zip **3.25.185** doit être installé et le cache purgé avant de lancer l'agent.
- L'agent **ne doit pas** décocher « Mode simulation » : rien n'est branché, tout passerait en échec.
- Les huit adresses servent à remplir la liste blanche du mode recette et à alimenter les fiches TEST-QA — pas à recevoir quoi que ce soit dans cette version.
