# Vérifications automatiques

Trois de mes régressions récentes auraient été attrapées ici. Ces contrôles se
lancent sans WordPress, en quelques secondes.

    php tests/test-prix.php            # normalisation des montants (20 cas réels)
    php tests/test-nom-personne.php    # personne vs raison sociale (7 cas)
    php tests/test-deroule-seances.php # déroulé des séances sur la convention
    php tests/test-protection-dossiers.php # aucune protection ne remonte à la racine des uploads
    node tests/test-signature-trace.js # continuité du tracé des signatures
    php tests/scan-global-wpdb.php acdc-formation-saas-organisme-de-formation

Le dernier balaie tout le plugin et signale les fonctions qui utilisent `$wpdb`
sans l'avoir importé — la faute qui a produit l'erreur fatale de la 3.25.236.
