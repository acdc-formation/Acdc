# Prompt agent — relevé des mesures d'hébergement (N0C / PlanetHoster)

À coller **dans l'onglet de l'agent**, une fois les onglets PlanetHoster et N0C ouverts.
Mission de **relevé** : l'agent lit des nombres et les recopie. Il ne répare rien.

**Pourquoi cette mission.** Trois audits de suite ont buté sur les mêmes inconnues :
mémoire PHP allouée, espace disque restant, exécution réelle du cron. Le plugin ne les
regarde pas — il suppose. Tant qu'elles sont supposées, la note de disponibilité ne peut
pas monter. Ce relevé les transforme en mesures, et je les câble ensuite dans l'écran
Veille avec alerte au seuil.

---

```
[MISSION DE RELEVÉ — MESURES D'HÉBERGEMENT]

Tu passes en mode RELEVÉ. Tu ne testes aucune fonctionnalité, tu ne remplis
aucune recette. Tu lis des nombres dans le panneau d'hébergement et tu les
recopies tels quels. David t'a ouvert deux onglets pour cela : l'espace client
PlanetHoster et le panneau N0C.

À SAVOIR AVANT DE COMMENCER, sinon tu vas croire à une erreur :
le compte PlanetHoster est au nom de « Christian ». C'est normal et attendu.
L'hébergement qu'il contient est bien celui de acdcformation.com — c'est ce
site-là, et lui seul, qui t'intéresse. Ne recopie PAS ce prénom, ni aucun nom de
personne, dans ton rapport : ils n'y ont pas leur place.

────────────────────────────────────────────────
GARDE-FOUS — PANNEAU DE PRODUCTION, LECTURE SEULE STRICTE
────────────────────────────────────────────────
Tu LIS. Tu ne modifies RIEN. Aucune exception.

INTERDIT, absolument :
- supprimer, renommer, déplacer, éditer ou téléverser le moindre fichier ;
- écrire dans la base de données, de quelque manière que ce soit ;
- modifier une configuration PHP, un DNS, un compte e-mail, une tâche cron ;
- lancer, restaurer ou supprimer une sauvegarde ;
- vider, purger ou faire tourner un journal ;
- redémarrer un service ;
- cliquer sur un bouton « réparer », « optimiser », « nettoyer » ou « corriger »,
  même si un écran le recommande. Si tu en vois un, signale-le et n'y touche pas.

Ne recopie JAMAIS dans ton rapport : un identifiant, un mot de passe, une clé
d'API, un jeton, le contenu de wp-config.php, ni le nom ou les coordonnées d'une
personne. Si une donnée sensible apparaît dans une capture ou un journal que tu
cites, remplace-la par [MASQUÉ]. Si tu détectes un secret quelque part, écris
seulement « secret détecté à vérifier », avec l'emplacement général du fichier.

Si tu hésites sur une action, NE LA FAIS PAS : décris-la et demande.

────────────────────────────────────────────────
1. LA MÉMOIRE PHP  — ce qui décide si une sauvegarde va au bout
────────────────────────────────────────────────
Dans N0C, cherche la sélection de version PHP et ses options (souvent
« PHP », « Sélecteur PHP », « Configuration PHP », ou une rubrique « Options »).

Relève ces cinq valeurs, telles qu'affichées :
  - la VERSION de PHP en service
  - memory_limit
  - max_execution_time
  - upload_max_filesize
  - post_max_size

Si un écran distingue la valeur « par défaut » de la valeur « appliquée »,
donne les deux et dis laquelle est active.

Pourquoi : la sauvegarde complète du plugin lit 62 tables et emporte les
fichiers de preuve. Sous 256 Mo, le processus peut être tué net, sans message,
et la sauvegarde n'a pas lieu — c'est le défaut le plus discret qui existe.

────────────────────────────────────────────────
2. L'ESPACE DISQUE — en octets ET en nombre de fichiers
────────────────────────────────────────────────
Cherche l'écran d'utilisation des ressources (« Statistiques », « Ressources »,
« Utilisation », ou le tableau de bord de l'hébergement).

Relève :
  - l'espace disque UTILISÉ et l'espace TOTAL alloué (avec l'unité)
  - le pourcentage affiché, s'il y en a un
  - le nombre de FICHIERS / d'inodes utilisés, et la limite s'il y en a une
  - la taille de la base de données, si elle est affichée à part

Le nombre de fichiers compte autant que les octets : sur un hébergement
mutualisé, on atteint souvent la limite d'inodes bien avant celle des gigaoctets,
et le symptôme est le même — plus rien ne s'écrit. Si cette information n'existe
nulle part, dis-le : c'est une information en soi.

────────────────────────────────────────────────
3. LE CRON — existe-t-il, et a-t-il tourné pour de vrai ?
────────────────────────────────────────────────
Cherche la rubrique des tâches planifiées (« Cron », « Tâches cron »,
« Planificateur »).

Relève, pour CHAQUE tâche listée :
  - sa fréquence exacte, telle qu'écrite (les cinq champs, si c'est du cron)
  - la commande, en masquant tout ce qui ressemble à une clé ou un jeton
  - la date et l'heure de la DERNIÈRE EXÉCUTION, si l'écran l'affiche
  - le résultat de cette dernière exécution (succès, erreur, code de sortie)

Si aucune tâche n'est listée, dis-le clairement — c'est une réponse, et une
réponse importante.

Si l'écran n'affiche pas de date de dernière exécution, cherche une trace
ailleurs : un journal de cron, un fichier de sortie, ou les e-mails de sortie de
cron dans la boîte du compte. Recopie les 5 dernières lignes trouvées.

Pourquoi : les sauvegardes automatiques, les relances d'enquêtes et les rappels
d'émargement dépendent d'un déclencheur périodique. S'il ne tourne pas, tout cela
ne se produit qu'au moment où quelqu'un ouvre une page d'administration — donc de
façon irrégulière, et jamais la nuit ni le week-end.

────────────────────────────────────────────────
4. LE DOSSIER DES SAUVEGARDES — la vérification qui tranche
────────────────────────────────────────────────
Ouvre une FENÊTRE DE NAVIGATION PRIVÉE, donc sans être connecté, et va à :

    https://acdcformation.com/wp-content/uploads/acdc-backups/

Dis EXACTEMENT ce que tu vois :
  - une erreur 403 (« interdit ») → c'est le bon résultat
  - une erreur 404 (« introuvable ») → bon résultat également
  - une page blanche → dis-le, précise si le code HTTP est visible
  - UNE LISTE DE FICHIERS OU DE DOSSIERS → dis-le immédiatement, en premier
    dans ton rapport, et N'OUVRE AUCUN de ces fichiers. Ne les télécharge pas.
    Ne recopie pas leurs noms complets : dis seulement combien il y en a et à
    quoi ressemble la forme d'un nom.

C'est le point le plus important de toute la mission. Ce dossier contient, s'il
existe, une copie complète de l'organisme : apprenants, conventions, factures,
signatures manuscrites. Je dois savoir s'il s'ouvre depuis Internet, et cette
question ne peut se trancher que de l'extérieur, ce que je ne peux pas faire.

Fais le même essai sur :

    https://acdcformation.com/wp-content/uploads/acdc-signatures/
    https://acdcformation.com/wp-content/uploads/acdc-documents/

Même consigne : tu regardes la réponse, tu n'ouvres rien.

────────────────────────────────────────────────
5. LES SAUVEGARDES DE L'HÉBERGEUR — celles qui ne dépendent pas de nous
────────────────────────────────────────────────
Dans N0C ou l'espace client, cherche la rubrique des sauvegardes de
l'hébergement (pas celles du plugin).

Relève, sans jamais en déclencher ni en restaurer une :
  - existent-elles, et sont-elles activées ?
  - à quelle fréquence sont-elles prises ?
  - combien de temps sont-elles conservées ?
  - quelle est la date de la plus récente ?
  - portent-elles les fichiers, la base de données, ou les deux ?

────────────────────────────────────────────────
6. LE JOURNAL D'ERREURS PHP — les 30 dernières lignes
────────────────────────────────────────────────
Cherche, dans cet ordre :
  - la rubrique « Journaux » / « Logs » du panneau N0C ;
  - un fichier error_log à la racine du site ;
  - un fichier error_log dans wp-content/ ;
  - wp-content/debug.log.

Recopie les 30 DERNIÈRES LIGNES, telles quelles, horodatage compris. Cherche en
particulier : « Fatal error », « Allowed memory size », « Maximum execution
time », « Out of memory », « Too many connections », « Disk quota exceeded ».
Masque toute donnée sensible par [MASQUÉ].

Si le fichier est très gros, dis sa taille et sa date de dernière modification —
un journal d'erreurs de plusieurs centaines de mégaoctets est lui-même un
problème d'espace disque.

────────────────────────────────────────────────
FORMAT DU RAPPORT
────────────────────────────────────────────────
Écris à Claude Code, préfixé [QA-DELIV], en six paragraphes numérotés comme
ci-dessus.

Sois LITTÉRAL. Recopie les nombres et les unités mot pour mot plutôt que de les
résumer ou de les convertir : « memory_limit = 256M » et non « la mémoire semble
correcte ». Un chiffre approximatif ne sert à rien ici — c'est justement pour
sortir des approximations que cette mission existe.

Si tu n'as pas pu accéder à un point, écris « non accessible » et pourquoi. Un
trou déclaré vaut mieux qu'une supposition : je préfère cinq mesures sûres et un
trou nommé, que six valeurs dont une inventée.

Si tu ne dois rapporter qu'une seule chose, c'est le POINT 4 — la réponse du
dossier des sauvegardes en navigation privée.

Et une consigne qui prime sur toutes les autres : à aucun moment de cette mission
tu n'exécutes une action qui écrit quoi que ce soit, ni sur le site, ni dans le
panneau, ni dans la base. Tu relèves, tu recopies, tu rapportes.
```

---

## Rappel côté David

L'agent ne fait que **lire**. Rien de ce qu'il relève ne modifie quoi que ce soit.

Ce que je fais de son rapport, dans l'ordre :

1. **Point 4** décide de la note de confidentialité. Si le dossier s'ouvre, je pose le
   verrou sur les dossiers **existants** le jour même — le correctif actuel ne couvre
   que ceux créés ou revus depuis la 3.25.313.
2. **Points 1, 2, 3** deviennent des seuils dans l'écran Veille : le plugin mesurera
   lui-même la mémoire, l'espace et l'âge du dernier passage de cron, et alertera avant
   la panne au lieu de la constater après.
3. **Point 5** dit si une seconde ligne de défense existe indépendamment du plugin.
4. **Point 6** dit si une panne s'est déjà produite sans que personne ne la voie.
