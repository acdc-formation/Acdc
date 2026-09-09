# Prompt agent — raccorder les sauvegardes au Google Drive

À coller **dans l'onglet de l'agent**. Mission bornée : créer l'autorisation
Google, la déposer dans le plugin, prouver qu'une sauvegarde arrive dans le
dossier « Sauvegarde SAAS ». Rien d'autre.

**L'adresse de retour à déclarer dans la console Google** — celle que David
demande, à recopier **caractère pour caractère**, sans espace ni barre finale :

```
https://acdcformation.com/wp-admin/admin.php?page=acdc-of-maintenance&acdc_gdrive=callback
```

Elle est également rappelée dans l'écran du plugin, juste au-dessus des deux
champs à remplir : si un jour l'adresse du site change, c'est l'écran qui fait
foi, pas ce document.

---

```
[MISSION — AUTORISER LE PLUGIN À DÉPOSER LES SAUVEGARDES DANS LE DRIVE]

Le plugin ACDC Formation SAAS sait fabriquer une sauvegarde complète (toutes les
tables, plus les fichiers de preuve : émargements, signatures, propositions). Il
sait aussi la déposer dans Google Drive, deux fois par jour, à 12h00 et 18h00.
Il lui manque une seule chose : l'autorisation de Google.

Ta mission tient en trois temps : créer cette autorisation dans la console
Google Cloud, la déposer dans le plugin, puis prouver par un envoi réel qu'un
fichier arrive bien dans le dossier « Sauvegarde SAAS ».

Le compte à utiliser est celui de David : davidcontal@gmail.com. Le dossier de
destination existe déjà, il ne faut PAS en créer un autre :
https://drive.google.com/drive/u/0/folders/13zSg3ZpgmjxRW_Zx5_eHgEfONSkGYPIM
Son identifiant est la partie qui suit /folders/ :
13zSg3ZpgmjxRW_Zx5_eHgEfONSkGYPIM

────────────────────────────────────────────────
LA RÈGLE QUI PRIME SUR TOUTES LES AUTRES
────────────────────────────────────────────────
Tu vas manipuler un identifiant client et un secret client. Ce sont des clés.

Tu ne les écris JAMAIS : ni dans ton rapport, ni dans un message, ni dans un
résumé, ni « juste les premiers caractères ». Tu les copies depuis la console
Google et tu les colles directement dans les champs du plugin, sans étape
intermédiaire — pas de bloc-notes, pas de fichier, pas de document partagé.

Si tu dois parler de l'un d'eux, tu écris exactement : « secret récupéré, collé
dans le plugin ». Rien de plus.

Si à un moment tu ne peux pas coller sans afficher, ARRÊTE-TOI et dis-le à
David : il le fera lui-même. Une clé recopiée quelque part est une clé à
révoquer.

────────────────────────────────────────────────
GARDE-FOUS
────────────────────────────────────────────────
- Tu ne touches à AUCUN autre réglage du plugin. En particulier : tu ne cliques
  ni sur « Suppression totale », ni sur « Importer et restaurer », ni sur
  « Remise à zéro sélective ». Ces boutons sont hors mission, sans exception.
- Tu ne décoches jamais « Mode recette ».
- Dans Google Drive, tu ne supprimes, ne déplaces et ne partages aucun fichier.
- Tu ne crées aucun compte, aucun utilisateur, aucune autre adresse e-mail.
- Si un écran te demande une carte bancaire ou l'activation d'une facturation :
  c'est que tu n'es pas au bon endroit. L'API Drive est gratuite pour cet usage.
  Arrête-toi et signale-le.
- Si tu hésites sur une action, NE LA FAIS PAS : décris-la et demande.

════════════════════════════════════════════════
TEMPS 1 — CRÉER L'AUTORISATION DANS LA CONSOLE GOOGLE
════════════════════════════════════════════════
Console : https://console.cloud.google.com/ — connecté en davidcontal@gmail.com.

L'interface de Google a beaucoup bougé ces dernières années ; les libellés
ci-dessous peuvent différer d'un mot. Fie-toi à ce que la page FAIT, pas au
chemin exact. Si un intitulé manque, cherche son équivalent avant de renoncer.

1.1 — LE PROJET
Crée un projet, ou réutilise celui qui existe déjà si tu en vois un nommé pour
ce site. Nom suggéré : « ACDC Sauvegardes ». Aucune organisation à rattacher.
Vérifie ensuite, en haut de page, que le projet SÉLECTIONNÉ est bien celui-là :
la moitié des échecs de cette procédure vient d'un réglage posé dans un projet
et d'une clé créée dans un autre.

1.2 — ACTIVER L'API GOOGLE DRIVE
« API et services » → « Bibliothèque » → chercher « Google Drive API » →
« Activer ». Attends la confirmation. Sans cette étape, la connexion réussira
et le dépôt échouera — un piège classique : l'erreur n'apparaît qu'au premier
envoi.

1.3 — L'ÉCRAN DE CONSENTEMENT
Selon la version de la console : « API et services » → « Écran de consentement
OAuth », ou bien « Google Auth Platform ».
- Type d'utilisateur : **Externe**. (« Interne » n'est proposé qu'aux comptes
  d'entreprise Google Workspace ; sur un compte Gmail il sera grisé.)
- Nom de l'application : « ACDC Sauvegardes ».
- Adresse d'assistance et adresse du développeur : davidcontal@gmail.com.
- Utilisateur test : ajoute davidcontal@gmail.com s'il t'est demandé une liste.

1.4 — LA PORTÉE (SCOPE)
Dans « Accès aux données » / « Champs d'application », ajoute UNIQUEMENT :
    https://www.googleapis.com/auth/drive.file
C'est la portée la plus étroite qui existe pour ce besoin : elle ne donne accès
QU'AUX fichiers que l'application a elle-même créés. Le plugin ne peut donc ni
lire ni toucher le reste du Drive de David. N'ajoute aucune autre portée, et
surtout pas « .../auth/drive » tout court, qui ouvrirait tout le Drive.

1.5 — PUBLIER L'APPLICATION  ← NE SAUTE PAS CETTE ÉTAPE
Sur l'écran de consentement, l'état de publication est au départ « Test ».
Fais-le passer en **« En production »** (bouton « PUBLIER L'APPLICATION »).

Pourquoi c'est indispensable : tant que l'application reste en « Test », Google
fait expirer l'autorisation au bout de SEPT JOURS. Les sauvegardes partiraient
une semaine, puis s'arrêteraient — sans que personne ne le remarque, puisque
tout aurait l'air d'avoir marché. C'est exactement le genre de panne muette qu'on
refuse ici.

Google peut afficher un avertissement sur la vérification : la portée
drive.file n'est pas une portée sensible, la publication n'exige donc aucun
examen préalable. Si un formulaire de vérification s'ouvre malgré tout, ne le
remplis pas : arrête-toi et signale-le à David.

1.6 — L'IDENTIFIANT CLIENT
« API et services » → « Identifiants » → « Créer des identifiants » →
« ID client OAuth ».
- Type d'application : **Application Web**. (Pas « Application de bureau », pas
  « Compte de service » : un compte de service n'a pas d'espace de stockage à
  lui et ne peut RIEN déposer dans le Drive personnel de David.)
- Nom : « ACDC Sauvegardes — site ».
- « URI de redirection autorisés » → « + AJOUTER UN URI » → colle exactement :

    https://acdcformation.com/wp-admin/admin.php?page=acdc-of-maintenance&acdc_gdrive=callback

  Vérifie caractère par caractère : https, pas de www, pas de barre oblique
  finale, le « & » bien présent. Google compare cette adresse à l'identique ;
  un seul caractère d'écart et la connexion sera refusée avec un message
  « redirect_uri_mismatch ».
- « Créer ». Google affiche alors l'identifiant client et le secret client.
  RELIS LA RÈGLE QUI PRIME : tu les copies, tu ne les écris nulle part. Garde
  cette fenêtre ouverte, tu en as besoin tout de suite.

════════════════════════════════════════════════
TEMPS 2 — DÉPOSER L'AUTORISATION DANS LE PLUGIN
════════════════════════════════════════════════
Va sur https://acdcformation.com/wp-admin/ →
menu « ACDC Formation SAAS » → « Données & maintenance ».
Descends jusqu'au bloc « Envoi des sauvegardes vers Google Drive ».

2.1 — Compare l'adresse affichée sous le titre (« Adresse de retour à déclarer
dans la console Google ») avec celle que tu viens de déclarer. Elles doivent
être identiques. Si elles diffèrent, c'est l'adresse de L'ÉCRAN qui a raison :
retourne la corriger dans la console.

2.2 — Remplis :
- « Identifiant client » : colle depuis la console.
- « Secret client » : colle depuis la console. Le champ est masqué, c'est normal.
- « Dossier Drive » : 13zSg3ZpgmjxRW_Zx5_eHgEfONSkGYPIM
- « Archives conservées » : 200.
  (Deux sauvegardes par jour : 200 représente un peu plus de trois mois
  d'historique. Au-delà, la plus ancienne est supprimée automatiquement.)
Clique « Enregistrer ».

Note : ces deux champs se rappellent leur contenu mais ne le réaffichent
jamais. Après enregistrement ils apparaissent VIDES, avec la mention
« enregistré — laisser vide pour conserver ». C'est voulu : un champ vide
conserve la valeur en place, on ne peut donc pas effacer un secret par
inadvertance.

2.3 — Clique « Connecter mon Drive ». Google demande l'accord de David :
accepte avec le compte davidcontal@gmail.com.
- Si l'écran « Google n'a pas validé cette application » apparaît :
  « Paramètres avancés » → « Accéder à ACDC Sauvegardes (non sécurisé) ».
  C'est l'application de David lui-même, ce n'est pas un tiers.
- Tu dois revenir sur l'écran « Données & maintenance », qui affiche désormais
  **« Drive connecté »** en vert.

Si le retour échoue, note le message EXACT affiché par Google ou par le plugin
et rapporte-le tel quel : c'est lui qui dit quoi corriger.

CE QUI S'EST DÉJÀ PRODUIT ICI, LE 16 AOÛT 2026, ET QUI PEUT RECOMMENCER.
Le retour de Google a d'abord été refusé par le serveur lui-même : une page
« 403 Forbidden » servie par LiteSpeed, donc par le pare-feu de l'hébergement,
pas par Google. Puis le plugin a affiché « jeton d'état invalide ».

La cause probable : Google ajoute à l'adresse de retour un paramètre
« scope=https://www.googleapis.com/auth/drive.file ». Un paramètre de requête
qui contient une URL complète est l'un des motifs les plus classiquement
bloqués par ModSecurity — la famille de règles anti-inclusion distante — et
ces règles sont plus sévères sur /wp-admin/ que sur le reste du site.

Depuis la 3.25.295, le plugin distingue trois pannes au lieu d'une. Recopie la
phrase EXACTE, elle désigne l'endroit à regarder :
  - « arrivé SANS son jeton d'état […] retiré en chemin » → c'est le pare-feu
    ou le cache de l'hébergement, pas un réglage ;
  - « a expiré » → plus de quinze minutes se sont écoulées, ou l'écran a été
    rechargé entre-temps : recommencer, sans passer par un onglet resté ouvert ;
  - « ne correspond pas » → le retour ne vient pas de la demande faite ici.

Dans le premier cas, le journal ModSecurity de N0C donne le numéro de la règle
qui a bloqué, avec l'horodatage et l'adresse IP. En LECTURE SEULE : tu relèves
le numéro et l'heure, tu ne modifies aucun réglage de sécurité.

════════════════════════════════════════════════
TEMPS 3 — PROUVER QUE ÇA MARCHE
════════════════════════════════════════════════
Un réglage qui a l'air juste ne prouve rien. Il faut un fichier.

3.1 — Clique « Sauvegarder et envoyer maintenant ». C'est long : la sauvegarde
complète est fabriquée puis téléversée. Laisse la page travailler, ne clique pas
deux fois.

3.2 — Attends le bandeau de résultat, et RECOPIE-LE mot pour mot dans ton
rapport, qu'il annonce une réussite ou un échec.

3.3 — Ouvre le dossier Drive et vérifie de tes yeux :
https://drive.google.com/drive/u/0/folders/13zSg3ZpgmjxRW_Zx5_eHgEfONSkGYPIM
- Un fichier .zip est-il présent ?
- Son nom a-t-il la forme « sauvegarde-du-jj-mm-aaaa-00h00mn.zip » ?
- Quelle taille fait-il ? (Une archive de quelques kilo-octets serait un signal
  d'alarme : la vraie pèse plusieurs méga-octets.)
- Son nom contient-il la mention « (INCOMPLETE) » ? Si oui, dis-le en premier
  dans ton rapport : le plugin marque ainsi une sauvegarde à laquelle il manque
  des tables.

3.4 — Reviens sur « Données & maintenance » et relève :
- la ligne « Dernier envoi réussi le … » ;
- le bloc juste en dessous, qui indique combien de tables et combien de fichiers
  contient la dernière sauvegarde, et si elle est complète.

────────────────────────────────────────────────
CE QUE TU RAPPORTES
────────────────────────────────────────────────
Préfixe [QA-VERIF]. Court, factuel, dans cet ordre :
1. API Drive activée : oui / non.
2. État de publication de l'application : « En production » / « Test ».
   (Si « Test », dis-le en tête : les sauvegardes s'arrêteront dans sept jours.)
3. Portée déclarée : laquelle, exactement.
4. Adresse de redirection : identique à celle de l'écran, oui / non.
5. Identifiants : « secret récupéré, collé dans le plugin » — et rien d'autre.
6. Écran du plugin : « Drive connecté » affiché, oui / non.
7. Envoi manuel : le bandeau, mot pour mot.
8. Dans le dossier Drive : nom du fichier, taille, mention « (INCOMPLETE) »
   présente ou non.
9. Nombre de tables et de fichiers annoncés par l'écran, et « complète : oui/non ».
10. Tout écran inattendu, message d'erreur ou hésitation — même résolue.

Si quelque chose échoue, n'essaie pas de contourner : rapporte l'écran exact.
```

---

## Ce qu'il restera à faire côté hébergeur

Les rendez-vous de 12h00 et 18h00 sont posés, mais WordPress ne déclenche ses
tâches qu'à la première visite qui suit l'heure prévue. Sur un site peu
fréquenté à midi, la sauvegarde peut donc partir avec du retard — voire pas du
tout un jour creux.

Pour un départ à l'heure exacte, il faut une tâche planifiée chez N0C
(PlanetHoster) qui appelle toutes les quinze minutes :

```
https://acdcformation.com/wp-cron.php?doing_wp_cron
```

C'est une manipulation dans le panneau d'hébergement, en écriture : elle relève
de David, pas de l'agent.
