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
- ouvrir phpMyAdmin, ou toucher à une base de données de quelque manière ;
- modifier une configuration PHP, un DNS, un compte e-mail, une tâche cron ;
- lancer, restaurer ou supprimer une sauvegarde ;
- vider, purger ou faire tourner un journal ;
- redémarrer un service, sauf si Claude Code te le demande explicitement.

Ne recopie JAMAIS dans ton rapport : un identifiant, un mot de passe, une clé
d'API, un jeton, ni le contenu de wp-config.php. Si une donnée sensible apparaît
dans un journal que tu cites, remplace-la par [MASQUÉ].

Si tu hésites sur une action, NE LA FAIS PAS : décris-la et demande.

────────────────────────────────────────────────
CE QUE TU DOIS RAPPORTER — six points, dans cet ordre
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

Le point 2 est prioritaire. Si tu ne dois en rapporter qu'un seul, c'est
celui-là : le numéro de version lu sur le disque.
```

---

## Rappel côté David

L'agent ne fait que **lire**. C'est toi qui manipules les fichiers, dans cet ordre :

1. Dossier renommé en `...-HS` → le site revient.
2. Patch extrait **dans** ce dossier.
3. Ligne 6 du fichier principal vérifiée : `Version: 3.25.177`.
4. Dossier renommé sans le `-HS`, extension réactivée.
