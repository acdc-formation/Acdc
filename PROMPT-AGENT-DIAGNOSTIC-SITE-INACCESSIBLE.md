# [URGENCE] Site inaccessible — diagnostic hébergement et fichiers

`acdcformation.com` ne répond plus : **ERR_TIMED_OUT**. Ta mission est de
trouver pourquoi, et si possible de remettre le site en ligne.

Préfixe tous tes messages par `[QA-VERIF]`.

**Ce n'est plus une campagne de recette.** Oublie les actes, les fixtures, les
parcours. Tu fais du diagnostic système.

---

## Ce que dit déjà le symptôme

**ERR_TIMED_OUT n'est pas une erreur 500.** La distinction est capitale et elle
oriente tout le diagnostic :

- une **erreur 500** signifie que PHP a planté — le serveur répond, mais mal ;
- un **timeout** signifie que rien ne répond du tout — le serveur reçoit la
  requête et ne rend jamais la main.

Un timeout désigne donc, par ordre de probabilité : un processus qui tourne en
boucle et consomme tout, une limite de ressources atteinte sur le compte
d'hébergement, un disque plein, ou une base de données qui ne répond plus.

---

## Ce que tu dois autoriser toi-même à faire, et ce que tu ne peux pas

David t'autorise **explicitement**, pour cette intervention uniquement :

- **lire** tous les journaux et écrans de supervision du panneau N0C ;
- **lire et lister** les fichiers par FTP ou par le gestionnaire de fichiers ;
- **renommer** le dossier du plugin ACDC pour le désactiver (voir §4). C'est une
  action réversible en dix secondes, et c'est le test décisif.

Restent interdits, sans exception :

- **supprimer** quoi que ce soit — aucun fichier, aucune table, aucune ligne ;
- **modifier** `wp-config.php`, `.htaccess` ou tout fichier du cœur WordPress ;
- **recopier** un identifiant, un mot de passe, une clé API ou le contenu de
  `wp-config.php` dans ton rapport. Tu peux dire « la constante X est définie »,
  jamais sa valeur ;
- **toucher à la base de données** autrement qu'en lecture.

Si une action te paraît nécessaire au-delà de ça, **demande-la** au lieu de la
faire.

---

## 1. D'abord, écarte ce qui ne vient pas du site

Avant d'accuser le serveur, vérifie que le problème n'est pas chez toi. Une de
tes captures montrait ton propre navigateur en mode hors ligne.

- Charge n'importe quel autre site public. S'il ne répond pas non plus, c'est ta
  connexion : dis-le et arrête là.
- Charge le panneau N0C. S'il répond, ta connexion va bien et le problème est
  bien du côté du site.

Puis **délimite la panne** :

- `acdcformation.com` répond-il ? Et `acdcformation.com/wp-admin/` ?
- Le site répond-il en **HTTP** comme en **HTTPS** ?
- Y a-t-il d'autres domaines ou sous-domaines sur le même compte
  d'hébergement ? S'ils répondent, la panne est propre à ce site ; s'ils sont
  tous morts, c'est le compte ou le serveur.

Cette distinction change tout le reste du diagnostic : dis-la clairement.

---

## 2. Le panneau N0C — ce qu'il faut regarder, dans cet ordre

**a. L'état du compte.** Est-il actif, suspendu, en dépassement ? Un compte
suspendu pour dépassement de ressources produit exactement ce symptôme.

**b. Les ressources.** Cherche les graphiques ou compteurs de CPU, de mémoire,
d'« entry processes » ou de processus simultanés, et d'entrées/sorties. Regarde
les **dernières heures**, pas la moyenne du mois.

> Ce que tu cherches : un mur. Une consommation qui monte d'un coup et reste au
> plafond. Note **l'heure exacte** où ça commence — c'est la donnée la plus
> précieuse de tout ce diagnostic, parce qu'elle se compare à l'heure d'une
> installation de plugin.

**c. L'espace disque et le quota.** Un disque plein produit des timeouts, parce
que MySQL ne peut plus écrire ses fichiers temporaires. Regarde aussi le quota
d'inodes (nombre de fichiers) s'il est affiché.

**d. La base de données.** Le service MySQL tourne-t-il ? La base est-elle
accessible depuis phpMyAdmin ? Sa taille est-elle anormale ?

**e. Les journaux d'erreur.** Cherche un « Error log », « Logs PHP » ou
équivalent. Relève les **dernières lignes**, avec leur horodatage. Cherche en
particulier : `Maximum execution time exceeded`, `Allowed memory size
exhausted`, `Too many connections`, `disk full`.

**f. La version de PHP** et les limites configurées (`max_execution_time`,
`memory_limit`). Ne les modifie pas ; relève-les.

---

## 3. Les fichiers — par FTP ou gestionnaire de fichiers

Va à la racine WordPress du site.

**a. Cherche les fichiers de journal.** Ils s'appellent en général `error_log`,
`php_errorlog` ou `debug.log`, et se trouvent à la racine, dans `wp-content/`,
ou dans le dossier du plugin. Note leur **taille** et leur **date de dernière
modification** : un `error_log` de plusieurs dizaines de mégaoctets écrit il y a
cinq minutes est un aveu.

> Lis les **dernières lignes**, pas le fichier entier. Cherche un message qui se
> répète en boucle : c'est la signature d'un processus qui recommence sans fin.

**b. Regarde le dossier du plugin**
`wp-content/plugins/acdc-formation-saas-organisme-de-formation/`.

- Quelle version porte-t-il ? Ouvre le fichier
  `acdc-formation-saas-organisme-de-formation.php` et lis l'en-tête `Version:`.
- **À quelle heure a-t-il été modifié pour la dernière fois ?** Compare cette
  heure à celle du mur de ressources du §2b. Si les deux coïncident,
  l'installation du plugin est la cause, et il faut le dire sans détour.
- Le dossier semble-t-il **complet** ? Une extraction interrompue laisse un
  plugin à moitié installé, qui plante à chaque chargement.

**c. Regarde s'il existe des dossiers de plugin en double** — par exemple un
`...-formation-saas-organisme-de-formation-2` ou un dossier portant l'ancien
nom. Deux copies du même plugin actives en même temps, c'est la panne garantie.

**d. Relève la taille du dossier `wp-content/uploads/`** et en particulier
`uploads/acdc-certificates/` et `uploads/acdc-of-contracts/`. Un dossier qui
aurait explosé signalerait une génération de documents en boucle.

---

## 4. Le test décisif : le plugin est-il la cause ?

C'est le geste le plus utile de toute l'intervention, et il est réversible.

**Renomme le dossier du plugin** :

```
wp-content/plugins/acdc-formation-saas-organisme-de-formation
→ wp-content/plugins/acdc-formation-saas-organisme-de-formation-DESACTIVE
```

WordPress ne trouve plus le plugin et le désactive de lui-même. Aucune donnée
n'est perdue : les tables et les fichiers restent intacts, et remettre le nom
d'origine réactive tout.

Puis **recharge le site** et dis ce qui se passe :

- **Le site revient** → le plugin est la cause. **Laisse-le désactivé** et
  signale-le immédiatement : c'est l'information la plus importante que tu
  puisses rapporter, et David doit décider de la suite.
- **Le site reste mort** → le plugin n'est pas en cause. **Remets aussitôt le
  nom d'origine** et continue le diagnostic côté hébergement.

Ne fais ce test qu'après avoir relevé les éléments des §2 et §3 : une fois le
plugin désactivé, les traces de la panne cessent de s'écrire.

---

## 5. Une piste précise à vérifier en priorité

Le développeur a identifié dans son propre code un mécanisme capable de produire
exactement ce symptôme, et il l'a corrigé dans la version **3.25.230**. Voici
comment le reconnaître, pour confirmer ou écarter cette hypothèse.

Le module d'émargement lançait, **à chaque chargement de page**, une réparation
des feuilles d'émargement vides — jusqu'à deux cents feuilles, plusieurs
requêtes chacune. Le numéro de schéma qui devait arrêter cette réparation
n'était écrit qu'**après** son achèvement. Si la réparation dépassait le temps
d'exécution PHP, elle mourait avant d'écrire ce numéro, et la requête suivante
recommençait tout. Une boucle qui s'auto-entretient, sur chaque page, y compris
publiques.

**Ce que tu dois chercher pour le confirmer :**

- dans le journal d'erreur : des `Maximum execution time exceeded` répétés,
  mentionnant un fichier du dossier `includes/emargement/` ;
- dans la base (lecture seule, via phpMyAdmin) : la table `options`, ligne
  `acdc_emarg_db_version`. **Si elle est absente ou porte une valeur ancienne
  alors que le plugin installé est en 3.25.223 ou plus, l'hypothèse est
  confirmée** : la réparation n'a jamais réussi à s'achever ;
- dans la table `..._acdc_of_emarg_sessions` : combien de feuilles existent, et
  combien n'ont aucune ligne dans `..._acdc_of_emarg_learners`. Un nombre élevé
  explique la lenteur.

Si l'hypothèse se confirme, la version 3.25.230 la corrige : la réparation ne
tourne plus que dans l'administration, par lots de dix, et le numéro de schéma
s'écrit **avant** le travail.

---

## 6. Ton rapport

Donne-moi, dans cet ordre :

1. **La panne est-elle propre au site, au compte, ou au serveur ?**
2. **L'heure exacte** où les ressources ont décroché, si tu as pu l'établir.
3. **La version du plugin installée** et **l'heure de dernière modification** de
   son dossier.
4. **Les dernières lignes du journal d'erreur**, avec horodatage. Si un message
   se répète, dis-le et donne-le une fois.
5. **Le résultat du test du §4** : site revenu ou non après désactivation.
6. **L'état de la piste du §5** : confirmée, écartée, ou non vérifiable.
7. **Ce que tu n'as pas pu regarder, et pourquoi.**

Et rappelle-toi la règle de ce projet : **ne recopie aucun identifiant, aucun
mot de passe, aucune clé.** Décris, ne divulgue pas.

Si tu dois choisir entre remettre le site en ligne et poursuivre le diagnostic,
**remets le site en ligne** : un site qui répond permet de diagnostiquer
tranquillement, l'inverse n'est pas vrai.
