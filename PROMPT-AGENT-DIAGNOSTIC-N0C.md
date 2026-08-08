# Prompt de diagnostic — panne du site, accès N0C

À coller **dans l'onglet de l'agent**. Mission courte et bornée : constater, pas réparer.

---

```
[MISSION DE DIAGNOSTIC — PANNE]

Le site https://acdcformation.com ne répond plus. Tu passes en mode DIAGNOSTIC :
tu ne testes plus de fonctionnalités, tu ne remplis plus la recette. Tu constates
et tu rapportes. David t'a ouvert le panneau d'hébergement N0C (PlanetHoster)
pour cela.

CE QUE L'ON SAIT DÉJÀ, mesuré depuis un autre réseau :
- Un fichier STATIQUE répondait en 0,9 s (200).
- Toute page servie par PHP ne renvoyait AUCUN octet, même après 40 secondes.
- Donc : serveur, réseau et TLS sains ; c'est la couche PHP qui se bloque.
La cause identifiée est un défaut du plugin en version 3.25.176 : deux reprises
de données tournaient à chaque requête, y compris publique, et se relançaient
sans fin. La 3.25.177 les corrige.

HYPOTHÈSE PRINCIPALE À VÉRIFIER, c'est le cœur de ta mission :
le dossier du plugin contient peut-être ENCORE la 3.25.176. Si le correctif n'a
pas été copié, remettre le plugin en service relance la panne immédiatement.

────────────────────────────────────────────────
GARDE-FOUS — N0C EST UN PANNEAU DE PRODUCTION
────────────────────────────────────────────────
Tu LIS. Tu ne modifies RIEN. Aucune exception.

INTERDIT, absolument :
- supprimer, renommer, déplacer, éditer ou téléverser le moindre fichier ;
- écrire dans la base de données, de quelque manière que ce soit (phpMyAdmin
  n'est ouvert qu'en LECTURE, et uniquement pour les cinq requêtes listées plus
  bas — voir « Priorité absolue ») ;
- modifier une configuration PHP, un DNS, un compte e-mail, une tâche cron ;
- lancer, restaurer ou supprimer une sauvegarde ;
- vider, purger ou faire tourner un journal ;
- redémarrer un service, sauf si Claude Code te le demande explicitement.

Ne recopie JAMAIS dans ton rapport : un identifiant, un mot de passe, une clé
d'API, un jeton, ni le contenu de wp-config.php. Si une donnée sensible apparaît
dans un journal que tu cites, remplace-la par [MASQUÉ].

Si tu hésites sur une action, NE LA FAIS PAS : décris-la et demande.

────────────────────────────────────────────────
PRIORITÉ ABSOLUE — LES DONNÉES SONT-ELLES ENCORE LÀ ?
────────────────────────────────────────────────
David constate que les écrans ne montrent plus aucun prospect, aucune formation,
aucun quiz. Il faut savoir, AVANT TOUTE AUTRE CHOSE, s'il s'agit d'un affichage
vide ou d'une perte réelle. Les deux se ressemblent à l'écran et n'ont rien à
voir en gravité.

Ce que l'analyse du code établit déjà : le plugin ne contient AUCUNE instruction
de suppression de table, sauf dans sa routine de désinstallation, laquelle sort
sans rien faire tant qu'une option réglée sur « conserver » — sa valeur par
défaut, posée à l'installation — n'a pas été changée. Une perte réelle est donc
très improbable. Il faut le prouver, pas l'espérer.

EXCEPTION AU GARDE-FOU HABITUEL — tu es autorisé à ouvrir phpMyAdmin, pour
LIRE, et uniquement pour cela :
  - onglet SQL, et tu exécutes EXACTEMENT ces requêtes, une par une :

      SELECT COUNT(*) FROM wp_acdc_of_prospects;
      SELECT COUNT(*) FROM wp_acdc_of_formations;
      SELECT COUNT(*) FROM wp_acdc_of_quizzes;
      SELECT COUNT(*) FROM wp_acdc_of_learners;
      SELECT COUNT(*) FROM wp_acdc_of_training_registrations;

  - si le préfixe des tables n'est pas « wp_ », relève le préfixe réel dans la
    liste des tables et adapte-le ;
  - si une table n'existe plus, la requête renverra une erreur : recopie cette
    erreur telle quelle, c'est une information capitale.

STRICTEMENT INTERDIT dans phpMyAdmin, sans aucune exception :
  UPDATE, INSERT, DELETE, DROP, TRUNCATE, ALTER, RENAME, l'onglet Opérations,
  l'import, l'export, la réparation de table, et toute modification de structure.
  Tu ne tapes QUE des SELECT COUNT(*). Rien d'autre. Si une requête que tu
  t'apprêtes à écrire ne commence pas par SELECT, ne l'écris pas.

Rapporte les cinq nombres. C'est le relevé le plus important de ta mission :
  - des nombres non nuls  → les données sont intactes, c'est un problème
    d'affichage ou de chargement du plugin, et rien n'est perdu ;
  - des tables vides ou absentes → il faut arrêter tout net et ne plus rien
    écrire sur ce site avant décision de David.

────────────────────────────────────────────────
CE QUE TU DOIS RAPPORTER ENSUITE — six points, dans cet ordre
────────────────────────────────────────────────

1. ÉTAT DU SITE, mesuré et non supposé.
   Ouvre dans deux onglets :
     a. https://acdcformation.com/wp-includes/js/jquery/jquery.min.js
        (fichier statique — ne passe PAS par PHP)
     b. https://acdcformation.com/
   Dis pour chacun : la page s'affiche-t-elle, en combien de temps environ, ou
   tourne-t-elle indéfiniment. C'est ce couple qui dit si la panne est toujours
   la même.

2. LA VERSION RÉELLEMENT SUR LE DISQUE — le point le plus important.
   Dans le gestionnaire de fichiers N0C, va dans :
     wp-content/plugins/
   Puis :
     - Note le NOM EXACT du dossier du plugin ACDC. Se termine-t-il par « -HS » ?
     - Entre dedans, ouvre en LECTURE le fichier
       acdc-formation-saas-organisme-de-formation.php
     - Lis la ligne 6, qui commence par « * Version: »
     - RECOPIE CE NUMÉRO TEL QUEL.
   3.25.176 = le correctif n'est pas en place, la panne va se reproduire.
   3.25.177 = le correctif est bien là, la cause est ailleurs.

3. LE FICHIER DE MAINTENANCE.
   À la racine du site — le dossier qui contient wp-config.php, wp-content et
   wp-includes — cherche un fichier nommé exactement :
     .maintenance
   C'est un fichier caché : pense à activer l'affichage des fichiers cachés.
   Dis simplement s'il existe, et sa date. NE LE SUPPRIME PAS.

4. LE JOURNAL D'ERREURS PHP — l'artefact le plus utile.
   Cherche, dans cet ordre de préférence :
     - la rubrique « Journaux » / « Logs » du panneau N0C ;
     - un fichier error_log à la racine du site ;
     - un fichier error_log dans wp-content/ ;
     - wp-content/debug.log.
   Recopie les 30 DERNIÈRES LIGNES, telles quelles, horodatage compris.
   Cherche en particulier : « Fatal error », « Allowed memory size »,
   « Maximum execution time », « Out of memory », « Too many connections ».
   Masque toute donnée sensible par [MASQUÉ].

5. LA CHARGE DU SERVEUR.
   Dans N0C, trouve l'écran d'utilisation des ressources (souvent « Statistiques »,
   « Ressources » ou « Utilisation »). Relève, sans rien modifier :
     - l'usage CPU, et s'il est plafonné ;
     - le nombre de processus / d'entrées en file d'attente ;
     - la mémoire utilisée ;
     - toute mention de limite atteinte ou de blocage récent.
   Si tu ne trouves pas cet écran, dis-le simplement — c'est une information.

6. L'ÉTAT DE L'EXTENSION.
   Essaie https://acdcformation.com/wp-admin/. Si la page de connexion s'affiche,
   dis-le et ARRÊTE-TOI là : ne te connecte pas, ne réactive rien. Si elle tourne
   indéfiniment, dis-le aussi. C'est une observation, pas une action.

────────────────────────────────────────────────
FORMAT DU RAPPORT
────────────────────────────────────────────────
Écris à Claude Code, préfixé [QA-VERIF], en six paragraphes numérotés comme
ci-dessus. Sois littéral : recopie les numéros de version et les lignes de
journal mot pour mot plutôt que de les résumer. Si tu n'as pas pu accéder à un
point, écris « non accessible » et pourquoi — un trou déclaré vaut mieux qu'une
supposition.

Si tu ne dois rapporter qu'une seule chose, ce sont les CINQ NOMBRES de la
priorité absolue. Le numéro de version lu sur le disque vient juste après.

Et une consigne qui prime sur toutes les autres : tant que l'état des données
n'est pas établi, tu n'exécutes AUCUNE action qui écrit quoi que ce soit, ni sur
le site, ni dans le panneau, ni dans la base. Pas de réactivation d'extension,
pas de restauration de sauvegarde, pas de réparation automatique proposée par un
outil. Si un écran te propose un bouton « réparer », « restaurer » ou
« optimiser », ne le touche pas et signale-le.
```

---

## Rappel côté David

L'agent ne fait que **lire**. C'est toi qui manipules les fichiers, dans cet ordre :

1. Dossier renommé en `...-HS` → le site revient.
2. Patch extrait **dans** ce dossier.
3. Ligne 6 du fichier principal vérifiée : `Version: 3.25.177`.
4. Dossier renommé sans le `-HS`, extension réactivée.
