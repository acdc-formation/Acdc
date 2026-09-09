# Prompt agent — reprise du verrou `acdc-signatures/` (mesure concluante)

Suite du rapport [QA-DELIV] du 17/08. À coller **dans l'onglet de l'agent**.

**Pourquoi une reprise.** La règle posée était un `<FilesMatch>`, et l'agent a établi que
sur ce serveur elle n'est pas consultée pour un fichier absent : le 404 arrive avant.
La mesure ne pouvait donc rien prouver — le défaut était dans ma consigne, pas dans son
travail.

**Ce qui change.** On passe au refus **de répertoire**, celui dont `acdc-backups/` prouve
qu'il fonctionne ici (403 sur un fichier absent), avec deux exceptions explicites. Un
refus de répertoire est évalué **avant** la résolution du fichier : il devient mesurable
sans jamais appeler un fichier réel.

---

```
[MISSION DE REPRISE — REMPLACEMENT DU VERROU. ACTION AUTORISÉE, ET BORNÉE.]

Ton rapport précédent était juste sur les deux points que tu as soulevés. La
contradiction de l'étape 1 était dans ma consigne, et ton arbitrage — le
garde-fou d'abord — était le bon. Ton test de contrôle sur acdc-backups est ce
qui permet la mesure ci-dessous. Rien à refaire de ce côté.

Tu es AUTORISÉ À ÉCRIRE, uniquement le fichier nommé ci-dessous. Tout le reste
du panneau reste en lecture seule stricte, et la liste des interdits de la
mission précédente s'applique intégralement : aucun autre fichier, aucune base,
aucune configuration PHP, aucun cron, aucune sauvegarde, aucun bouton
« réparer ». Tu n'ouvres et ne télécharges toujours AUCUN fichier réel de
signature, de pièce d'identité ou de certificat — la mesure ci-dessous n'en a
pas besoin.

────────────────────────────────────────────────
ÉTAPE 1 — REMPLACER LE CONTENU DU .htaccess
────────────────────────────────────────────────
Le fichier wp-content/uploads/acdc-signatures/.htaccess existe (tu l'as créé,
506 octets). REMPLACE son contenu entier par celui-ci, exactement :

# ACDC — Refus par défaut, comme pour acdc-backups.
# La forme « FilesMatch » ne convenait pas : ce serveur résout le fichier
# avant d'évaluer la règle, et un motif ne protège donc pas de façon
# vérifiable. Le refus porte ici sur le répertoire.
Order allow,deny

# Exception : le certificat PDF est remis au signataire depuis son portail.
<FilesMatch "\.pdf$">
Allow from all
</FilesMatch>

# Mais jamais une pièce d'identité, même déposée en PDF.
# Sous « Order allow,deny », un refus l'emporte sur une autorisation.
<FilesMatch "^id-">
Deny from all
</FilesMatch>

Trois pièges du gestionnaire de fichiers, les mêmes que la dernière fois : le
nom reste « .htaccess », pas d'espace ni de ligne vide en tête, UTF-8 sans BOM.

────────────────────────────────────────────────
ÉTAPE 2 — LE SITE RÉPOND-IL ENCORE ?
────────────────────────────────────────────────
En navigation privée, recharge https://acdcformation.com/

SI LA PAGE RENVOIE 500 : la directive « Order » n'est pas acceptée. REMETS le
contenu précédent du fichier (celui de 506 octets, en FilesMatch), vérifie que
l'accueil revient, et rapporte-le. Ne cherche pas une autre syntaxe toi-même.

────────────────────────────────────────────────
ÉTAPE 3 — LA MESURE, ET ELLE EST CONCLUANTE CETTE FOIS
────────────────────────────────────────────────
Appelle ces DEUX adresses, en navigation privée. Les deux fichiers n'existent
pas, et c'est voulu : aucune donnée ne transite, dans aucun des cas.

    https://acdcformation.com/wp-content/uploads/acdc-signatures/1/verif-acdc.png
    https://acdcformation.com/wp-content/uploads/acdc-signatures/1/verif-acdc.pdf

Ce que les codes veulent dire, et c'est le cœur de la mission :

  .png → 403  ET  .pdf → 404   ✅ TOUT EST BON.
       Le refus de répertoire s'applique (403 avant même de chercher le
       fichier), et l'exception PDF est bien évaluée (elle laisse passer, donc
       le serveur cherche le fichier, ne le trouve pas, et dit 404).
       C'est exactement le résultat attendu.

  .png → 404  ET  .pdf → 404   ❌ La règle n'est pas appliquée du tout.
       Ne touche à rien, dis-le-moi.

  .png → 403  ET  .pdf → 403   ⚠ L'exception PDF ne fonctionne pas : le
       certificat du signataire serait bloqué. Ne touche à rien, dis-le-moi.

Pour comparaison, refais la même mesure sur le dossier témoin, dont on sait
qu'il refuse tout :

    https://acdcformation.com/wp-content/uploads/acdc-backups/verif-acdc.png

Attendu : 403. S'il rend autre chose, c'est le témoin qui a bougé et toute la
mesure est à relire.

────────────────────────────────────────────────
ÉTAPE 4 — DEUX RELEVÉS, SANS AUCUNE ÉCRITURE
────────────────────────────────────────────────
Tu as signalé que les fichiers anciens de acdc-emargement/ et acdc-invoices/
n'ont pas de jeton dans leur nom. Tu as raison, et je code une migration qui les
renommera. Pour la dimensionner, j'ai besoin de deux nombres :

  a) dans wp-content/uploads/acdc-emargement/ : combien de fichiers AU TOTAL, et
     combien portent la forme ANCIENNE (learner-<n>-<horodatage>.png ou
     trainer-<n>-<horodatage>.png, c'est-à-dire sans suite de 20 caractères
     avant l'extension) ;
  b) dans wp-content/uploads/acdc-invoices/ : combien de fichiers au total, et
     combien de forme ancienne (facture-<n>-<horodatage>.html).

Des NOMBRES seulement. N'ouvre aucun de ces fichiers, n'en recopie aucun nom en
entier.

────────────────────────────────────────────────
FORMAT DU RAPPORT
────────────────────────────────────────────────
Écris à Claude Code, préfixé [QA-DELIV], en quatre paragraphes numérotés.

Le cœur tient en trois codes : le .png, le .pdf, et le témoin acdc-backups.
Donne-les en premier, avant tout commentaire.

Si tu as dû faire un retour arrière à l'étape 2, dis-le avant tout le reste.

Et si un point de ma consigne te paraît se contredire lui-même, tranche comme la
dernière fois — garde-fou d'abord — et dis-le-moi. C'est ce qui a rendu ton
rapport précédent utile.
```

---

## Rappel côté David

Ce que l'agent mesure ici, je le transforme ensuite en balayage automatique : le plugin
posera ces verrous tout seul, y compris sur les dossiers créés avant que la règle existe,
et un contrôle vérifiera que la forme retenue est bien celle qui fonctionne sur ce serveur.

Les deux nombres de l'étape 4 dimensionnent la migration de renommage des fichiers
anciens — signatures manuscrites d'émargement et factures — dont les noms sont
aujourd'hui devinables.
