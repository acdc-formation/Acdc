# Prompt de reprise — après l'incident du 9 août

À coller **dans l'onglet de l'agent**. Reprise de la recette « Évaluation & Enquêtes ».

---

```
[REPRISE DE RECETTE — VERSION CIBLE 3.25.180]

Tu reprends la recette du module « Évaluation & Enquêtes ». Les règles de ton
briefing d'origine restent intégralement en vigueur : distribution fermée,
financeurs réels en consultation stricte, aucun envoi hors distribution, préfixe
TEST-QA, N0C en lecture seule, et tu écris à Claude Code préfixé [QA-VERIF].

────────────────────────────────────────────────
CE QUI S'EST PASSÉ PENDANT TON ARRÊT — lis-le, cela évite de faux rapports
────────────────────────────────────────────────
Un défaut que j'avais introduit en 3.25.176 a mis le site hors service : deux
reprises de données tournaient à chaque requête et se relançaient sans fin.
Ensuite, les tables métier se sont retrouvées vides — cause jamais élucidée.

David a restauré l'instantané du 9 août à 00h00. Tout est revenu, y compris ses
trois apprenantes et le financeur d'essai. La version en ligne est 3.25.180.

CE QUE CELA CHANGE POUR TOI :
- Les écrans vides que tu as constatés ne sont plus d'actualité. Ne les
  rapporte plus.
- Les données sont celles de minuit. Ta session live 130 et ses 5 passations
  devraient être intactes : le moteur de quiz vit dans des tables qui n'ont été
  ni purgées ni restaurées.
- Les comptes de portail apprenant et formateur ont pu perdre leur session.
  Si l'un ne répond plus, signale-le et poursuis avec les autres rôles.

🚨 INTERDICTION ABSOLUE, NOUVELLE ET SANS EXCEPTION
Dans Extranet > Paramètres > Réglages, le bloc « Protection des données »
contient « Suppression totale des entrées du plugin », « Créer une sauvegarde »
et « Importer et restaurer ». Tu peux LIRE cet écran. Tu ne cliques sur AUCUN
de ces trois boutons, ni sur aucune case à cocher de ce bloc, sous aucun
prétexte, même pour vérifier un correctif. Ces fonctions sont hors recette et
réservées à David. Si un test semble en avoir besoin, il ne se fait pas.

────────────────────────────────────────────────
PRIORITÉ 1 — L'INTÉGRITÉ APRÈS RESTAURATION
────────────────────────────────────────────────
Une restauration remet des lignes, mais les liens entre elles peuvent s'être
rompus. C'est le contrôle le plus utile que tu puisses faire cette nuit, et
personne ne l'a fait.

  a. Dossiers de formation : les inscriptions 8, 9 et 10 sont-elles revenues,
     avec leur apprenante nommée ET leur entreprise (Skill Conseil) ?
  b. Répertoires : 2 prospects, 2 entreprises, 3 apprenants, 12 financeurs
     (dont TEST-QA Financeur Essai), 20 formations. Les compteurs du tableau de
     bord disent-ils la même chose que les listes ?
  c. Chaque apprenante ouvre-t-elle encore sa fiche, avec son inscription
     rattachée ? Un apprenant restauré mais détaché de son dossier est un
     défaut à signaler immédiatement.
  d. Les 5 passations de quiz sont-elles toujours là, et pointent-elles encore
     vers des apprenants existants ?
  e. Les sessions d'enquête à chaud / à froid / intermédiaires ont-elles
     retrouvé leurs participants ?

────────────────────────────────────────────────
PRIORITÉ 2 — VÉRIFIER LES CORRECTIFS 3.25.171 À 3.25.180
────────────────────────────────────────────────
Tu les as tous signalés, aucun n'a été vérifié : la recette s'est arrêtée avant.
Verdicts : ✅ CORRIGÉ / ❌ TOUJOURS PRÉSENT / ⚠️ RÉGRESSION.

ÉCRANS DE RÉSULTATS, côté administration
  1. Colonne « Formateur » : remplie, et non plus à tiret, sur les quatre écrans.
  2. Colonne « Résultat » de Tous les résultats : plus de tiret systématique.
  3. Filtre « Type » de Tous les résultats : « Évaluation diagnostique » y figure
     et filtre réellement.
  4. Colonne « Apprenant » : nom complet quand l'apprenant est rattaché, et
     mention « ⚠ non rattaché à un apprenant » quand il ne l'est pas.
  5. Lien « Réinitialiser » : l'URL ne porte plus deux paramètres tab.
  6. La passation anonymisée du 5 août apparaît, sous « Apprenant anonymisé ».

PORTAIL FORMATEUR — c'est là qu'il y avait le plus d'écarts
  7. Score moyen d'un quiz live : en POINTS, plus de tiret. Doit dire la même
     valeur que l'administration.
  8. Colonne « Apprenant » de l'onglet Participants : plus jamais vide.
  9. « Lancé le » au lieu d'un tiret pour une passation en salle.
 10. Onglet « Par objectif » : le Seuil s'affiche avec son origine (seuil du
     quiz / seuil par défaut), et « Atteint ? » n'est plus « ✗ Non » sans seuil.
 11. « Par objectif » et « Par question » doivent maintenant donner des chiffres
     COMPATIBLES sur la même passation. C'est ton point 9 d'hier : tu avais
     mesuré 70,0 % contre 54,4 %. Refais le calcul.
 12. Colonne « Partielles » présente côté formateur aussi.

QUIZ EN SALLE — relance une session sur l'évaluation diagnostique
 13. Réponds VOLONTAIREMENT à moitié juste sur un QCM multiple : la carte doit
     dire « ◐ Réponse partiellement juste » avec les points réels, et non plus
     « ❌ Mauvaise réponse / +0 pt ».
 14. Laisse expirer le chronomètre puis tente de répondre : « ⏱ Temps écoulé »,
     réponse refusée, et non plus « Mauvaise réponse ».
 15. Le bouton « Réponse » ne saute plus le dévoilement quand le chronomètre
     atteint zéro.
 16. « Une seule réponse possible » a le même poids que l'alerte multiple, et
     la consigne multiple dit « cochez TOUTES les bonnes réponses ».
 17. Une question Puzzle porte une consigne, et son chronomètre laisse au moins
     15 secondes par élément.
 18. L'avatar propose une troisième option neutre.
 19. Après la session : la part de réussite partielle se retrouve-t-elle dans
     « Par question », « Par objectif » ET le score du participant ? C'était ton
     défaut chiffré d'hier.

PORTAIL APPRENANT
 20. « Ma formation » affiche l'entreprise (Skill Conseil), et non plus
     « Non renseignée ».
 21. « Lien distanciel » ne s'affiche plus sur une formation en présentiel.
 22. « Mes quiz » porte un bloc « MES RÉSULTATS » même sans passation.
 23. Les deux Rossa restent distinctes partout — nom complet, jamais le prénom
     seul.

ENQUÊTES
 24. Les sessions d'enquêtes entreprises et formateurs affichent-elles enfin un
     destinataire ? Sinon, la mention « ⚠ aucun destinataire » doit apparaître
     en rouge dans la liste.
 25. Les statuts sont accentués : « Expirée », « Planifiée », « Terminée ».

────────────────────────────────────────────────
PRIORITÉ 3 — DEUX ENQUÊTES EN LECTURE SEULE
────────────────────────────────────────────────
 26. LES FORMATIONS EN DOUBLE. C'est établi : 20 lignes pour 10 intitulés, par
     paires d'identifiants consécutifs, toutes créées à la même seconde le
     15 mai 2026. Une importation jouée deux fois. Ne supprime RIEN. Ce qu'il
     faut savoir avant que David tranche : pour chaque paire, laquelle des deux
     porte des séances, des inscriptions, des quiz ou des enquêtes ? Une
     suppression à l'aveugle orphelinerait ce qui pointe vers la mauvaise.
 27. LE FAUX COMPTEUR RGPD. Tu avais relevé « 0 prospects » au répertoire et
     « 2 » en rétention. Maintenant que les données sont revenues, les deux
     doivent dire 2. Si l'écart persiste dans un sens ou dans l'autre, c'est
     bien un compteur qui lit autre chose que la table.

────────────────────────────────────────────────
CE QUI EST BLOQUÉ, ET POURQUOI — n'y perds pas de temps
────────────────────────────────────────────────
ACTE 3 CHEMIN 3, passation asynchrone : le test de positionnement est
VERROUILLÉ et un quiz verrouillé ne peut plus être envoyé. Il faudrait en créer
une nouvelle version, ce que tu ne fais pas sans l'accord de David. Et le lien
de passation arrive dans SA boîte, il dort.

ACTE 4B, enquête financeur : demande de configurer un ciblage, donc d'écrire.
Tu ne le fais pas seul.

Ces deux actes attendent le réveil de David. Ne les force pas, ne les contourne
pas, et rappelle-les dans ton rapport comme trous de couverture déclarés.

────────────────────────────────────────────────
COMPTE-RENDU
────────────────────────────────────────────────
Un rapport par priorité, au fil de l'eau. Tableau : Point | Verdict |
Observation courte. En tête : toute RÉGRESSION, et toute rupture de lien
héritée de la restauration — ce sont les deux familles graves ce soir.

Commence par la PRIORITÉ 1 : si la restauration a laissé des liens rompus,
David doit le savoir avant de reprendre quoi que ce soit demain matin.
```
