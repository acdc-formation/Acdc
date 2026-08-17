<?php
/**
 * Les corrections de l'audit du 17 août : ce qui ne doit pas se rouvrir.
 *
 * L'audit a trouvé un risque CRITIQUE et cinq élevés. Le critique tenait à une
 * seule absence : le dossier des sauvegardes — qui contient la copie complète de
 * l'organisme, signatures manuscrites et pièces d'identité comprises — ne
 * recevait ni « .htaccess » ni « index.php », alors que le plugin sait les poser
 * pour les contrats formateur depuis la 3.25.148. Une seule porte oubliée
 * annulait quarante serrures.
 *
 * Ces contrôles gèlent les dix corrections. Chacun a été vérifié EN LE SABOTANT.
 */

$racine = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : 'acdc-formation-saas-organisme-de-formation';
if ( ! is_dir( $racine ) ) {
    fwrite( STDERR, "Répertoire introuvable : {$racine}\n" );
    exit( 2 );
}

$alertes = array();
$signale = function ( $fichier, $texte ) use ( &$alertes ) {
    $alertes[] = sprintf( '%s — %s', $fichier, $texte );
};
$lire = function ( $rel ) use ( $racine ) {
    $p = $racine . '/' . $rel;
    return is_file( $p ) ? (string) file_get_contents( $p ) : '';
};
/** Le corps d'une fonction, borné à la fonction suivante. */
$corps = function ( $src, $signature, $taille = 2600 ) {
    $d = strpos( $src, $signature );
    return false === $d ? '' : substr( $src, $d, $taille );
};

$noyau  = $lire( 'includes/kernel/class-acdc-kernel-core-trait.php' );
$emargC = $lire( 'includes/emargement/class-acdc-emarg-core.php' );
$emargP = $lire( 'includes/emargement/class-acdc-emarg-public.php' );
$quest  = $lire( 'includes/questionnaires/class-acdc-questionnaires-core-trait.php' );
$sig    = $lire( 'includes/signature/class-acdc-sig-core.php' );
$appr   = $lire( 'includes/learner-portal/actions/class-acdc-learner-portal-actions-trait.php' );
$form   = $lire( 'includes/trainer-portal/actions/class-acdc-trainer-portal-actions-trait.php' );
$drive  = $lire( 'includes/backup-drive/class-acdc-backup-drive-trait.php' );
$veille = $lire( 'includes/watch/class-acdc-watch-actions-trait.php' );

/* 1 — LE DOSSIER DES SAUVEGARDES EST VERROUILLÉ (risque critique) */
if ( false === strpos( $noyau, 'function acdc_verrouiller_dossier(' ) ) {
    $signale( 'kernel-core-trait.php', "la fonction de verrouillage a disparu : le dossier des sauvegardes redeviendrait un dossier public contenant toute la base." );
} else {
    $c = $corps( $noyau, 'function get_backup_base_directory(' );
    if ( false === strpos( $c, 'acdc_verrouiller_dossier(' ) ) {
        $signale( 'kernel-core-trait.php', "get_backup_base_directory() ne verrouille plus le dossier : la copie complète de l'organisme redevient lisible par son adresse." );
    }
    $c2 = $corps( $noyau, 'function get_backup_run_directory(' );
    if ( false === strpos( $c2, 'acdc_verrouiller_dossier(' ) ) {
        $signale( 'kernel-core-trait.php', "le dossier de CHAQUE passage n'est plus verrouillé : le .htaccess du parent ne protège pas les serveurs qui l'ignorent." );
    }
    if ( false === strpos( $c2, 'wp_hash(' ) ) {
        $signale( 'kernel-core-trait.php', "le nom du dossier de sauvegarde n'est plus imprévisible : les deux rendez-vous sont à heure FIXE, le nom du jour se devinerait d'avance." );
    }
}

/* 2 — LE FILET DES GESTES IRRÉVERSIBLES (risque élevé) */
$c = $corps( $noyau, 'function create_safety_backup_snapshot(' );
if ( false === strpos( $c, "empty( \$result['success'] )" ) || false === strpos( $c, 'return false;' ) ) {
    $signale( 'kernel-core-trait.php', "create_safety_backup_snapshot() ne rend plus faux en cas d'échec : ses quatre appelants testent « empty() », et un tableau qui dit « échec » n'est pas vide. Le filet des suppressions massives redeviendrait décoratif." );
}
if ( false === strpos( $noyau, 'acdc_restore_no_safety_backup' ) ) {
    $signale( 'kernel-core-trait.php', "la restauration ne vérifie plus sa sauvegarde de sécurité : elle vide chaque table sans annulation possible, et c'est le geste qu'on fait quand quelque chose est déjà cassé." );
}

/* 3 — LE DISQUE NE SE REMPLIT PLUS (risque élevé) */
if ( false === strpos( $noyau, 'function acdc_vider_dossier_brut(' ) ) {
    $signale( 'kernel-core-trait.php', "le contenu brut n'est plus effacé après la fabrication de l'archive : chaque passage occuperait deux fois le volume, et le disque se remplirait en quinze jours." );
}
if ( ! preg_match( "/get_option\(\s*'acdc_of_backup_retention_count',\s*(\d+)\s*\)/", $noyau, $m ) || (int) $m[1] > 10 ) {
    $signale( 'kernel-core-trait.php', "la conservation locale des sauvegardes est remontée au-dessus de 10 : ce sont des copies complètes, et elles évincent au passage les sauvegardes prises à la main." );
}
if ( false === strpos( $drive, 'unlink( $archive )' ) ) {
    $signale( 'backup-drive-trait.php', "l'archive n'est plus effacée du serveur après son dépôt chez Google : l'intérêt d'une sauvegarde hors site est justement qu'elle n'occupe plus la machine qu'elle protège." );
}
$c = $corps( $noyau, 'function backup_data_snapshot(', 40000 );
if ( false !== strpos( $c, 'get_results( "SELECT * FROM {$table}", ARRAY_A )' ) ) {
    $signale( 'kernel-core-trait.php', "une table est de nouveau chargée ENTIÈRE en mémoire : le jour où le journal dépasse la mémoire allouée, le processus est tué sans un mot et la sauvegarde n'a pas lieu." );
}

/* 4 — L'ÉMARGEMENT, PIÈCE MAÎTRESSE QUALIOPI (risque élevé) */
if ( false === strpos( $emargC, 'signature_token' ) ) {
    $signale( 'emarg-core.php', "le jeton de signature a disparu : le QR affiché en salle redonnerait aux apprenants l'adresse de l'écran du formateur, avec les boutons « Signer » de chacun et « Marquer absent »." );
}
if ( false === strpos( $emargP, "case 'signature':" ) ) {
    $signale( 'emarg-public.php', "la page de signature n'est plus routée : le QR mènerait de nouveau à la liste du formateur." );
}
if ( false !== strpos( $emargP, "get_public_url('liste', \$token) ); ?>);" ) ) {
    $signale( 'emarg-public.php', "le QR encode de nouveau l'adresse de la page LISTE — l'écran du formateur." );
}
$c = $corps( $emargC, 'function get_learner_by_sign_token(' );
if ( false === strpos( $c, 'expires_at' ) ) {
    $signale( 'emarg-core.php', "le lien personnel d'émargement n'expire plus : une signature apposée des mois après la séance s'inscrirait sur la feuille, avec l'horodatage du jour." );
}
if ( false === strpos( $emargC, 'function acdc_tracer_emargement(' ) ) {
    $signale( 'emarg-core.php', "l'émargement ne verse plus au journal commun : déclarer un apprenant absent ne laisserait aucune trace, et la contestation trois mois après serait sans réponse." );
}

/* 5 — LES RELANCES D'ENQUÊTES (risque élevé) */
$c = $corps( $quest, 'function process_scheduled_survey_reminders(', 3200 );
if ( '' === $c ) {
    $signale( 'questionnaires-core-trait.php', "le répartiteur de relances a disparu." );
} else {
    if ( false === strpos( $c, 'LIMIT %d' ) ) {
        $signale( 'questionnaires-core-trait.php', "les relances n'ont plus de plafond : elles repartiraient en rafale, et c'est le DOMAINE qui est déclassé — donc les convocations et les demandes de signature qui tombent en indésirables." );
    }
    if ( false === strpos( $c, '$__servis' ) ) {
        $signale( 'questionnaires-core-trait.php', "la règle « un seul message par destinataire et par passage » a disparu des relances." );
    }
}

/* 6 — UNE PAGE PUBLIQUE NE MIGRE PLUS LA BASE (risque modéré) */
$c = $corps( $sig, 'function maybe_upgrade(' );
if ( false === strpos( $c, 'acdc_migration_autorisee_ici' ) ) {
    $signale( 'sig-core.php', "la migration du module signature n'est plus gardée : chaque visiteur anonyme la relancerait, en parallèle. C'est mot pour mot la panne du 9 août." );
}
if ( false === strpos( $c, 'acdc_sig_migration_en_cours' ) ) {
    $signale( 'sig-core.php', "le verrou de migration a disparu : deux onglets ouverts la lanceraient deux fois." );
}
if ( false === strpos( $noyau, 'function acdc_migration_autorisee_ici(' ) ) {
    $signale( 'kernel-core-trait.php', "la porte publique vers la garde de migration a disparu : les classes autonomes recopieraient la règle, et elle divergerait." );
}

/* 7 — LES FORMULAIRES PUBLICS (risque modéré) */
foreach ( array(
    array( $appr, 'learner-portal-actions', 'connexion_apprenant' ),
    array( $form, 'trainer-portal-actions', 'connexion_formateur' ),
    array( $appr, 'learner-portal-actions', 'contact_expire_apprenant' ),
) as $t ) {
    if ( false === strpos( $t[0], "acdc_trop_de_tentatives( '" . $t[2] . "'" ) ) {
        $signale( $t[1] . '.php', "le garde-fou par réseau « " . $t[2] . " » a disparu : on pouvait maintenir un formateur dehors le jour de sa formation, ou saturer la boîte de l'exploitant." );
    }
}
if ( false === strpos( $appr, '$__mdp_juste' ) ) {
    $signale( 'learner-portal-actions.php', "le portail apprenant répond de nouveau des messages différents selon l'état du compte : on peut éprouver une liste d'adresses et savoir qui a été formé chez vous." );
}

/* 8 — LES DOCUMENTS NOMINATIFS (risque élevé) */
if ( false === strpos( $noyau, 'function acdc_dossier_documents(' ) ) {
    $signale( 'kernel-core-trait.php', "les dossiers de documents ne reçoivent plus d'index.php : un serveur mal réglé donnerait l'inventaire de vos devis, donc la liste de vos clients." );
}
foreach ( array(
    array( 'includes/documents-billing/class-acdc-documents-billing-core-trait.php', 'le devis' ),
    array( 'includes/documents-billing/class-acdc-documents-billing-actions-trait.php', 'la facture' ),
    array( 'includes/quizzes/class-acdc-quizzes-core-trait.php', 'le résultat de quiz' ),
) as $t ) {
    if ( false === strpos( $lire( $t[0] ), 'wp_generate_password( 20, false, false )' ) ) {
        $signale( basename( $t[0] ), "le nom de fichier de " . $t[1] . " n'est plus imprévisible : son adresse se devine à partir d'un numéro et d'une date." );
    }
}

/* 9 — LE VOLET RGPD (risque modéré) */
$csv = $lire( 'includes/kernel/class-acdc-export-csv-trait.php' );
foreach ( array( 'rgpd_anonymisation', 'rgpd_export' ) as $trace ) {
    if ( false === strpos( $csv, "'" . $trace . "'" ) ) {
        $signale( 'export-csv-trait.php', "« " . $trace . " » ne s'inscrit plus au journal : le RGPD n'impose pas seulement de répondre à une demande, il impose de pouvoir DÉMONTRER qu'on y a répondu." );
    }
}
$c = $corps( $noyau, 'function purge_system_logs(', 4000 );
if ( false === strpos( $c, 'learner_portal_log_table' ) || false === strpos( $c, 'trainer_portal_log_table' ) ) {
    $signale( 'kernel-core-trait.php', "la règle « l'adresse Internet est effacée au bout d'un an » ne s'applique plus qu'à un journal sur trois, alors que l'écran affiche la phrase inverse." );
}

/* 10 — LES CLÉS D'API NE SONT PLUS RÉÉCRITES (point écarté du top 10) */
if ( false === strpos( $veille, "if ( '' === trim( (string) \$val ) ) {" ) ) {
    $signale( 'watch-actions-trait.php', "un champ vide efface de nouveau une clé : puisque l'écran ne les réaffiche plus, un simple « Enregistrer » les effacerait toutes les cinq." );
}

/* VERDICT */
echo "\n";
if ( $alertes ) {
    foreach ( $alertes as $a ) { echo "ALERTE  {$a}\n"; }
    printf( "%d alerte(s)\n", count( $alertes ) );
    exit( 1 );
}
echo "Audit du 17 août : sauvegardes verrouillées, filets tendus, émargement tracé, formulaires bridés, RGPD démontrable.\n";
exit( 0 );
