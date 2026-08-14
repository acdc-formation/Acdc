# Vérifications automatiques

Trois de mes régressions récentes auraient été attrapées ici. Ces contrôles se
lancent sans WordPress, en quelques secondes.

    php tests/test-prix.php            # normalisation des montants (20 cas réels)
    php tests/test-nom-personne.php    # personne vs raison sociale (7 cas)
    php tests/test-deroule-seances.php # déroulé des séances sur la convention
    php tests/test-cascade-adresse.php # ordre des sources du préremplissage devis
    php tests/test-decoupe-adresse.php # découpage rue / CP / ville du lieu de formation
    php tests/test-lieu-convention.php # priorité des sources du lieu de la convention
    php tests/test-completude.php      # étapes et calcul de la barre de complétude
    php tests/test-client-proposition.php # d'où viennent raison sociale, SIRET et adresse
    php tests/test-images-pdf.php      # réduction des images embarquées dans le PDF
    php tests/test-programme-formation.php # lien et pièce jointe du programme de formation
    php tests/test-convocation.php     # dates, horaires et lieu annoncés par la convocation
    php tests/test-inscription-convention.php # la convention signée crée les dossiers
    php tests/test-cachet-proportions.php # le cachet et la signature ne sont jamais déformés
    php tests/test-protection-dossiers.php # aucune protection ne remonte à la racine des uploads
    php tests/test-secret-financeur.php # un champ mot de passe vide n'efface jamais
    php tests/test-facturation-opco.php # qui reçoit la facture, et pour quel montant
    php tests/test-jalons-prospect.php  # étapes franchies comptées par le suivi commercial
    php tests/test-rappel-contrat-formateur.php # rappel du contrat formateur après signature
    php tests/test-montant-mission.php # heures, taux et montant : le calcul dans les deux sens
    node tests/test-signature-trace.js # continuité du tracé des signatures
    php tests/scan-global-wpdb.php acdc-formation-saas-organisme-de-formation
    php tests/scan-envois-email.php  # e-mails qui contournent la porte commune
    php tests/scan-liens-proteges.php # liens directs vers un dossier interdit d’accès
    php tests/scan-tarif-jour.php    # tarif catalogue (un TOTAL) injecté dans « Tarif jour »
    php tests/scan-programme-formation.php # lecture directe de program_file_url
    php tests/scan-convocation-unique.php  # convocation composée hors du composeur commun
    php tests/scan-seances-en-dur.php # horaires de séance écrits en dur
    php tests/scan-charte-documents.php # documents qui dessinent leur propre en-tête
    php tests/scan-actions-publiques.php # actions publiques bloquées pour les visiteurs
    php tests/scan-pdf-sans-filet.php # fabrication de PDF sans rattrapage d'erreur
    php tests/scan-secrets-en-page.php # secret (clé API, mot de passe) imprimé dans le HTML
    php tests/scan-reste-a-charge.php # reste à charge saisi au lieu d'être calculé
    php tests/scan-parcours-prospect.php # devis sans prospect, pastilles et filtre divergents
    php tests/scan-accueil-analyse.php # l'analyse du besoin salue toujours le même destinataire
    php tests/scan-images-deformees.php # image plafonnée en largeur ET en hauteur

Le dernier balaie tout le plugin et signale les fonctions qui utilisent `$wpdb`
sans l'avoir importé — la faute qui a produit l'erreur fatale de la 3.25.236.
