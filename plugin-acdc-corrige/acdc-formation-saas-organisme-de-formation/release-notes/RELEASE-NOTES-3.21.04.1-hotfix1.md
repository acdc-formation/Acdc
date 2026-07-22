# 3.21.04.1-hotfix1 — Onglet Résultats au niveau principal + page dashboard

**Date :** 27 avril 2026
**Type :** ajustement UX de la livraison 3.21.04.1.

---

## Ce qui change par rapport à 3.21.04.1

À la recette de 3.21.04.1, le bouton « 📊 Résultats » noyé dans la barre d'actions de chaque liste de quiz s'est avéré peu accessible. Cette mise à jour le promeut au rang d'**onglet principal** dans la sidebar de l'extranet, et refond la page d'arrivée en véritable tableau de bord.

## Ce qui est livré

**Sidebar extranet** : nouvel onglet **« Résultats »** ajouté dans le groupe « Quiz / Test / Évaluation », juste après « Évaluations des acquis ». Quatre onglets désormais, là où il y en avait trois.

**Page dashboard transverse** (URL : `?tab=qz_results`) qui affiche en un seul écran tous les envois quel que soit leur type :

- En-tête avec titre, sous-titre explicatif
- Filtres : **Finalité** (live / positionnement / évaluation / toutes) et **Statut** (envoyé / en cours / terminé / tous)
- **6 KPI cards** : Envois, Participants cumulés, Participants ayant terminé, Taux de réponse global, Réponses enregistrées, Score moyen global
- Tableau récapitulatif des envois avec colonne « Finalité » (badge coloré par type), formation, date d'envoi, ratio participants, score moyen, bouton « Voir → »

**Suppression du bouton redondant** : le bouton « 📊 Résultats » qui figurait dans la barre d'actions de chaque liste de quiz a été retiré (devenu inutile maintenant qu'il y a un onglet dédié).

## Architecture

- Nouvelle constante `ACDC_OF_QZ_TAB_RESULTS = 'qz_results'`
- Nouvelle méthode Core : `get_qz_results_dashboard_kpis( $args )` qui agrège les KPIs en une requête optimisée, avec support du filtre `purpose` et fallback formateur
- Routage : quand le tab courant est `qz_results`, on force la vue `view=results` même si la query string ne le contient pas — donc l'URL `?tab=qz_results` arrive directement sur le dashboard
- Comportement préservé : depuis chaque tab spécifique (Quiz live / Tests de positionnement / Évaluations), la vue Résultats reste accessible mais cette fois on ne voit que les envois du type concerné

## Compatibilité

- Aucune migration BDD : utilise les mêmes méthodes Core que 3.21.04.1
- Toutes les vues détaillées (par participant, par question, par objectif, correction manuelle, export CSV) sont **inchangées** : on ne touche que la liste d'arrivée
- Portail formateur : pas modifié ici (il avait déjà son onglet « Résultats » dédié, livré en 3.21.04.1)

## Points de recette

1. Vider le cache LiteSpeed (réflexe systématique)
2. Aller sur l'extranet ACDC en mode admin
3. Sidebar gauche : vérifier que **« Résultats »** apparaît bien dans le groupe « Quiz / Test / Évaluation »
4. Cliquer dessus : la page dashboard doit s'afficher avec les 6 KPI cards et le tableau
5. Tester le filtre **Finalité** : sélectionner « Quiz live » → seuls les envois live s'affichent ; les KPIs se recalculent en conséquence
6. Tester le filtre **Statut** : sélectionner « Terminé » → seuls les envois clôturés s'affichent
7. Cliquer « Voir → » sur un envoi : le détail (3 onglets Participants / Par question / Par objectif) doit s'ouvrir comme avant
8. Vérifier que les boutons d'export CSV et la correction manuelle des réponses libres fonctionnent toujours
