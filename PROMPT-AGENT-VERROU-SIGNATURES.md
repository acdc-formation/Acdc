# Prompt agent — pose du verrou sur `acdc-signatures/` (action autorisée)

À coller **dans l'onglet de l'agent**, N0C ouvert sur le gestionnaire de fichiers.

**Cette mission est la seule où l'agent écrit.** L'autorisation est nominative, limitée à
trois fichiers, et assortie d'un retour arrière immédiat en cas de 500.

**Pourquoi.** Le relevé du 17/08 a établi que `wp-content/uploads/acdc-signatures/` ne
porte aucune règle de refus, alors que ses noms de fichiers sont prévisibles
(`signature-<id>-<horodatage>.png`, `id-<id>-<horodatage>.jpg`, sous-dossiers numérotés
en séquence). Un horodatage Unix se balaie. Ce dossier contient des signatures
manuscrites et des pièces d'identité scannées.

**Pourquoi pas un `deny from all`.** Vérifié dans le code : le certificat
`certificat-*.pdf` du même dossier **est** servi par URL au signataire depuis son portail.
Tout bloquer lui couperait l'accès à son propre document signé. L'image de signature, elle,
n'est jamais servie par URL — elle est lue sur le disque pour fabriquer le PDF. La règle
ci-dessous bloque donc les images et laisse passer les PDF.

---

```
[MISSION D'INTERVENTION — POSE D'UN VERROU. ACTION AUTORISÉE, ET BORNÉE.]

Contrairement à toutes tes missions précédentes sur ce panneau, tu es ici
AUTORISÉ À ÉCRIRE — mais uniquement les trois fichiers listés ci-dessous, et
rien d'autre. Tout le reste du panneau reste en lecture seule stricte.

Rappel : le compte PlanetHoster est au nom de « Christian ». C'est normal.
L'hébergement contient acdcformation.com, et c'est ce site-là qui t'intéresse.
Ne recopie ce prénom, ni aucun nom de personne, dans ton rapport.

────────────────────────────────────────────────
CE QUI RESTE INTERDIT — la liste n'a pas changé
────────────────────────────────────────────────
- ne supprime, ne renomme, ne déplace, n'édite AUCUN autre fichier ;
- n'écris rien dans la base de données ;
- ne modifie aucune configuration PHP, aucun DNS, aucune tâche cron, aucun
  compte e-mail — même si l'écran te les présente à côté ;
- ne lance, ne restaure, ne supprime aucune sauvegarde ;
- ne clique sur aucun bouton « réparer », « optimiser », « nettoyer » ;
- n'OUVRE ni ne TÉLÉCHARGE aucun fichier de signature, de pièce d'identité ou
  de certificat. Tu manipules des chemins et des codes de réponse, jamais des
  contenus.

Ne recopie jamais un identifiant, un mot de passe, une clé, un jeton, ni le
contenu de wp-config.php. Masque par [MASQUÉ] toute donnée sensible citée.

────────────────────────────────────────────────
ÉTAPE 1 — CONSTAT AVANT (à faire AVANT toute écriture)
────────────────────────────────────────────────
Dans le gestionnaire de fichiers, place-toi dans :

    wp-content/uploads/acdc-signatures/

Note, SANS RIEN OUVRIR :
  a) le nom d'UN fichier image de signature (forme signature-….png) ;
  b) le nom d'UN fichier de certificat (forme certificat-….pdf) ;
  c) le nom d'UN fichier de pièce d'identité (forme id-….jpg ou .png) ;
  d) le sous-dossier dans lequel chacun se trouve.

Puis, en NAVIGATION PRIVÉE, relève le code de réponse HTTP de ces trois adresses
— le CODE seulement, ne sauvegarde ni n'affiche le contenu :

    https://acdcformation.com/wp-content/uploads/acdc-signatures/<sous-dossier>/<fichier a>
    https://acdcformation.com/wp-content/uploads/acdc-signatures/<sous-dossier>/<fichier b>
    https://acdcformation.com/wp-content/uploads/acdc-signatures/<sous-dossier>/<fichier c>

Écris les trois codes. C'est la mesure « avant », et elle sert à prouver que
l'intervention a servi à quelque chose.

Si un fichier s'ouvre au lieu de rendre un code, FERME-LE immédiatement sans le
lire ni le télécharger, et note simplement « accessible ».

────────────────────────────────────────────────
ÉTAPE 2 — ÉCRITURE AUTORISÉE : LE FICHIER .htaccess
────────────────────────────────────────────────
Crée, dans wp-content/uploads/acdc-signatures/ , un fichier nommé exactement :

    .htaccess

(c'est un fichier caché : pense à activer l'affichage des fichiers cachés)

S'il en existe déjà un, NE L'ÉCRASE PAS : recopie-moi son contenu actuel et
arrête-toi là. Le relevé disait qu'il n'y en a pas ; si la situation a changé,
je veux le savoir avant qu'on écrive par-dessus.

Contenu à saisir, exactement ceci, rien de plus, rien de moins :

# ACDC — Les images de ce dossier ne sont jamais servies par URL.
# Les PDF de certificat, si : ils sont remis au signataire depuis son portail.
<FilesMatch "\.(png|jpe?g|gif|webp|bmp|tiff?)$">
<IfModule mod_authz_core.c>
Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
Order allow,deny
Deny from all
</IfModule>
</FilesMatch>
<FilesMatch "^id-">
<IfModule mod_authz_core.c>
Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
Order allow,deny
Deny from all
</IfModule>
</FilesMatch>

La seconde règle vise les pièces d'identité par leur NOM et non par leur
extension : elle les couvre même si l'une d'elles est un PDF.

Attention à trois pièges du gestionnaire de fichiers :
  - le fichier doit s'appeler « .htaccess », pas « htaccess » ni « .htaccess.txt » ;
  - pas d'espace ni de ligne vide avant la première ligne ;
  - si l'éditeur propose un encodage, choisis UTF-8 sans BOM.

────────────────────────────────────────────────
ÉTAPE 3 — VÉRIFICATION IMMÉDIATE, ET RETOUR ARRIÈRE SI BESOIN
────────────────────────────────────────────────
Dès le fichier enregistré, en navigation privée, recharge :

    https://acdcformation.com/

SI LA PAGE D'ACCUEIL RENVOIE UNE ERREUR 500 : la syntaxe n'est pas acceptée par
ce serveur. SUPPRIME IMMÉDIATEMENT le fichier .htaccess que tu viens de créer,
vérifie que l'accueil revient, et rapporte-le. N'essaie pas de corriger la
syntaxe toi-même — c'est à moi de la revoir.

Si l'accueil répond normalement, relève à nouveau les trois codes HTTP de
l'étape 1, sur les MÊMES trois adresses.

RÉSULTAT ATTENDU :
  - fichier a (signature-….png)   → doit passer à 403
  - fichier c (id-….jpg)          → doit passer à 403
  - fichier b (certificat-….pdf)  → doit RESTER accessible (200)

Si le certificat passe lui aussi à 403, dis-le tout de suite : la règle est trop
large et je dois la corriger. Ne la modifie pas toi-même.

────────────────────────────────────────────────
ÉTAPE 4 — ÉCRITURE AUTORISÉE : LE FICHIER index.php
────────────────────────────────────────────────
Toujours dans wp-content/uploads/acdc-signatures/ , crée un fichier nommé :

    index.php

contenant exactement cette seule ligne :

<?php // Silence.

C'est le filet : si un jour le serveur ignore le .htaccess, ce fichier empêche
au moins l'inventaire du dossier.

Fais la MÊME chose — index.php seul, PAS de .htaccess — dans chacun de ces
dossiers, s'ils existent :

    wp-content/uploads/acdc-emargement/
    wp-content/uploads/acdc-invoices/
    wp-content/uploads/acdc-contracts/

Pour ceux-là, PAS de règle de refus : leurs fichiers sont légitimement ouverts
par URL (une facture envoyée à un client, une signature affichée sur l'écran du
formateur pendant la séance). Un refus casserait un usage réel. Leurs noms
portent déjà un jeton aléatoire de 20 caractères, ils ne se devinent pas.

Si l'un de ces dossiers contient déjà un index.php, laisse-le tel quel et
dis-le.

────────────────────────────────────────────────
ÉTAPE 5 — SUPPRESSION AUTORISÉE, SOUS CONDITION
────────────────────────────────────────────────
Le fichier wp-content/acdc-debug.log est servi publiquement en HTTP 200. Il ne
vient PAS du plugin ACDC — aucune ligne de son code ne l'écrit. C'est un résidu.

Avant de le supprimer, vérifie deux choses et dis-les-moi :
  1. sa date de dernière modification est bien en mai 2026 (donc il n'est plus
     alimenté) ;
  2. ses lignes sont bien uniquement des messages « PHP Deprecated » ou
     « PHP Notice », et rien qui ressemble à des données de personnes.

Si ces deux conditions sont vraies : supprime-le.
Si l'une des deux est fausse : NE LE SUPPRIME PAS, et rapporte ce que tu as vu.

────────────────────────────────────────────────
FORMAT DU RAPPORT
────────────────────────────────────────────────
Écris à Claude Code, préfixé [QA-DELIV], en cinq paragraphes numérotés comme les
étapes.

Le cœur du rapport tient en six nombres : les trois codes HTTP AVANT, et les
trois codes APRÈS. Donne-les côte à côte, c'est la preuve que l'intervention a
fonctionné — et la preuve qu'elle n'a pas cassé l'accès du signataire à son
certificat.

Ne recopie aucun nom de fichier en entier dans ton rapport : écris
« signature-….png », « id-….jpg », « certificat-….pdf ». Le sous-dossier, tu
peux le donner (c'est un numéro).

Si tu as dû faire un retour arrière à l'étape 3, dis-le en premier, avant tout
le reste.
```

---

## Rappel côté David

Ce que l'agent pose à la main, je le mets ensuite **dans le code** : le plugin doit
reposer ces verrous tout seul, y compris sur les dossiers créés avant que cette règle
existe. L'intervention manuelle ferme le trou aujourd'hui ; le code l'empêche de se
rouvrir demain, sur ce site comme sur un autre.

Les six codes HTTP du rapport me servent de recette : c'est exactement le contrôle que
j'écrirai en balayage automatique.
