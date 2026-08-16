<?php
/**
 * Les corrections issues de l'audit du 16 août ne doivent pas se défaire.
 *
 * Elles ont un point commun : AUCUNE NE SE VOIT À L'ÉCRAN. Une pièce jointe qui
 * manque, une trace d'audit qui décrit l'inverse de ce qui s'est passé, un lien
 * qui ne mène nulle part, un dossier qu'on peut parcourir, une demande qu'on
 * peut rejouer en boucle — rien de tout cela ne provoque d'erreur, rien ne
 * s'affiche en rouge. C'est pourquoi elles tiennent ici plutôt que dans la
 * mémoire de quelqu'un.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';
$hits = array();

$lire = function ( $rel ) use ( $root, &$hits ) {
    $p = $root . '/' . $rel;
    if ( ! is_readable( $p ) ) {
        $hits[] = $rel . ' est introuvable : le contrôle qui suit ne prouve rien.';
        return '';
    }
    return (string) file_get_contents( $p );
};

/* ── 1. LE CHEMIN D'UN FICHIER SE CALCULE DEPUIS SON ADRESSE ───────────── */

/* La fonction qui retrouve un document sur le disque à partir de son URL
   nettoyait une variable qu'elle n'avait jamais remplie : elle rendait le
   dossier des téléversements, jamais le fichier. Une convocation partait donc
   sans sa pièce jointe, sans que rien ne le signale. */
$noyau = $lire( 'includes/kernel/class-acdc-kernel-core-trait.php' );
if ( '' !== $noyau ) {
    if ( preg_match( '/function get_local_path_from_upload_url\(.*?\n  \}/s', $noyau, $m ) ) {
        $corps = $m[0];
        if ( ! preg_match( '/\$relative\s*=\s*substr\(\s*\$url/', $corps ) ) {
            $hits[] = 'get_local_path_from_upload_url() ne déduit plus le chemin de l’URL : les documents dont seule l’adresse est connue redeviennent introuvables, et les e-mails partent sans leur pièce jointe.';
        }
    } else {
        $hits[] = 'get_local_path_from_upload_url() est introuvable.';
    }
    /* ── 2. LE LIMITEUR DE SOLLICITATIONS EXISTE ───────────────────────── */
    if ( ! preg_match( '/function acdc_trop_de_tentatives\(/', $noyau ) ) {
        $hits[] = 'le limiteur acdc_trop_de_tentatives() a disparu : les demandes de réinitialisation redeviennent rejouables en boucle.';
    }
}

/* ── 3. LES DEUX RÉINITIALISATIONS SONT LIMITÉES ───────────────────────── */

$portails = array(
    'includes/learner-portal/actions/class-acdc-learner-portal-actions-trait.php' => 'handle_learner_portal_request_reset',
    'includes/trainer-portal/actions/class-acdc-trainer-portal-actions-trait.php' => 'handle_trainer_request_reset',
);
foreach ( $portails as $fichier => $fonction ) {
    $src = $lire( $fichier );
    if ( '' === $src ) {
        continue;
    }
    if ( preg_match( '/function ' . preg_quote( $fonction, '/' ) . '\(.*?\n  \}/s', $src, $m ) ) {
        if ( false === strpos( $m[0], 'acdc_trop_de_tentatives' ) ) {
            $hits[] = sprintf(
                '%s() ne limite plus les demandes : une adresse peut être sollicitée en boucle, et le domaine expéditeur finir classé en indésirable — ce jour-là ce ne sont plus les réinitialisations qui n’arrivent pas, ce sont les convocations.',
                $fonction
            );
        }
    } else {
        $hits[] = $fonction . '() est introuvable.';
    }
}

/* ── 4. LE DOSSIER DES PROPOSITIONS ─────────────────────────────────────── */

/* On n'y pose PAS de « deny from all » : le client ouvre sa proposition depuis
   le lien reçu par e-mail. On vérifie ce qui peut l'être sans casser l'usage —
   le dossier ne se parcourt pas, et le nom du fichier ne se devine pas. */
$prop = $lire( 'includes/proposals/class-acdc-proposals-render-trait.php' );
if ( '' !== $prop ) {
    if ( substr_count( $prop, "'index.php'" ) < 1 || false === strpos( $prop, 'Silence is golden' ) ) {
        $hits[] = 'le dossier des propositions n’est plus rendu impossible à parcourir : la liste des propositions commerciales redevient lisible depuis l’extérieur.';
    }
    if ( preg_match_all( "/\\\$filename\s*=\s*'proposition-'[^;]*;/", $prop, $m ) ) {
        foreach ( $m[0] as $ligne ) {
            if ( false === strpos( $ligne, '$__jeton' ) ) {
                $hits[] = 'un nom de fichier de proposition ne porte plus de jeton aléatoire : l’horodatage seul se devine à la seconde près, et une proposition porte le nom du client et le prix négocié.';
            }
        }
        if ( ! preg_match( "/wp_generate_password|random_bytes/", $prop ) ) {
            $hits[] = 'le jeton des noms de fichiers de propositions n’est plus tiré au hasard.';
        }
    } else {
        $hits[] = 'les noms de fichiers de propositions sont introuvables : le contrôle ne prouve rien.';
    }
}

/* ── 5. L'ACTION DES SÉANCES DE QUIZ VÉRIFIE LES DROITS ─────────────────── */

/* Ajouter le contrôle sans inscrire l'action dans la table des permissions
   l'aurait réservée aux seuls administrateurs — et cassé l'écran d'envoi pour
   les formateurs. Les deux vont ensemble ; on vérifie les deux. */
$quiz = $lire( 'includes/quizzes/class-acdc-quizzes-actions-trait.php' );
if ( '' !== $quiz ) {
    if ( preg_match( '/function ajax_acdc_of_qz_get_formation_sessions\(.*?\n    \}/s', $quiz, $m ) ) {
        if ( false === strpos( $m[0], 'qz_request_is_authorized' ) ) {
            $hits[] = 'ajax_acdc_of_qz_get_formation_sessions() ne vérifie plus les droits : tout compte connecté peut de nouveau lister les séances d’une formation.';
        }
    } else {
        $hits[] = 'ajax_acdc_of_qz_get_formation_sessions() est introuvable.';
    }
    if ( ! preg_match( "/'acdc_of_qz_get_formation_sessions'\s*=>\s*'dispatch_quiz'/", $quiz ) ) {
        $hits[] = 'l’action des séances n’est plus dans la table des permissions formateur : le contrôle de droits la réserve donc aux administrateurs, et l’écran d’envoi d’un quiz se casse pour les formateurs.';
    }
}

/* ── 6. UNE TRACE DE SUPPRESSION DIT « SUPPRESSION » ────────────────────── */

/* Les deux journaux d'audit d'une suppression annonçaient une « création » ou
   une « mise à jour » — en lisant au passage une variable inexistante. La trace
   décrivait l'inverse de ce qui venait de se passer, et c'est justement cette
   trace-là qu'on relit après coup. */
$quest = $lire( 'includes/questionnaires/class-acdc-questionnaires-core-trait.php' );
if ( '' !== $quest ) {
    foreach ( array(
        'handle_delete_quality_gap_record'    => 'Suppression d’un écart qualité',
        'handle_delete_quality_action_record' => 'Suppression d’une action qualité',
    ) as $fonction => $attendu ) {
        if ( preg_match( '/function ' . preg_quote( $fonction, '/' ) . '\(.*?\n  \}/s', $quest, $m ) ) {
            if ( false === strpos( $m[0], $attendu ) ) {
                $hits[] = sprintf(
                    '%s() n’écrit plus « %s » dans le journal d’audit : la trace d’une suppression décrit de nouveau autre chose que ce qui a eu lieu.',
                    $fonction,
                    $attendu
                );
            }
        }
    }
}

/* ── 7. LE JOURNAL DES ACTIONS ──────────────────────────────────────────── */

/* Il existait, il était alimenté, et personne ne pouvait l'ouvrir : aucun écran
   ne l'affichait. En parallèle une option gardait les 200 derniers événements —
   sans les montrer davantage — et effaçait le reste. Le plugin écrivait donc un
   journal que personne ne lisait, et en jetait la moitié. Plusieurs pièces le
   tiennent maintenant debout ; il suffit qu'une seule reparte pour que la
   traçabilité redevienne une croyance. */
if ( '' !== $noyau ) {
    if ( preg_match( '/function log_action_event\(.*?\n  \}/s', $noyau, $m ) ) {
        if ( false !== strpos( $m[0], "update_option( 'acdc_of_action_log'" ) ) {
            $hits[] = 'log_action_event() réécrit de nouveau l’option limitée aux 200 derniers événements : le journal recommence à s’effacer tout seul.';
        }
        if ( false === strpos( $m[0], 'insert_system_log' ) ) {
            $hits[] = 'log_action_event() n’écrit plus dans la table : plus rien n’est conservé.';
        }
    } else {
        $hits[] = 'log_action_event() est introuvable.';
    }
    if ( ! preg_match( '/ip_address VARCHAR/', $noyau ) || ! preg_match( '/user_agent VARCHAR/', $noyau ) ) {
        $hits[] = 'la table du journal ne porte plus l’origine des actions : on saura ce qui a été fait, jamais depuis où.';
    }
    if ( false === strpos( $noyau, "'ip_address'    => \$this->acdc_adresse_appelante()" ) ) {
        $hits[] = 'l’origine n’est plus enregistrée à l’insertion : les colonnes existent et restent vides.';
    }
    if ( ! preg_match( '/function purge_system_logs\(/', $noyau ) ) {
        $hits[] = 'la conservation du journal a disparu : il grossit sans fin et l’adresse d’origine de chaque action y reste indéfiniment.';
    } elseif ( preg_match( '/function purge_system_logs\(.*?\n\}/s', $noyau, $m ) ) {
        if ( false === strpos( $m[0], 'ip_address = NULL' ) ) {
            $hits[] = 'l’adresse d’origine n’est plus effacée au bout d’un an : une donnée personnelle serait conservée aussi longtemps que la preuve, ce qui est disproportionné.';
        }
        if ( false === strpos( $m[0], "yearsFor( 'audit' )" ) ) {
            $hits[] = 'la durée de conservation du journal ne vient plus de la politique déclarée du projet : une seconde règle s’installe à côté de la première.';
        }
    }
    if ( ! preg_match( '/function get_system_log_entries\(/', $noyau ) ) {
        $hits[] = 'la lecture du journal a disparu : la table redevient inconsultable.';
    }
}

/* On exige l'APPEL, pas la mention : un « method_exists( $this,
   'purge_system_logs' ) » resté seul suffirait à faire passer un contrôle qui se
   contenterait de chercher le nom. C'est le sabotage qui l'a montré. */
$actions = $lire( 'includes/kernel/class-acdc-kernel-actions-trait.php' );
if ( '' !== $actions && false === strpos( $actions, '$this->purge_system_logs()' ) ) {
    $hits[] = 'la conservation du journal n’est plus déclenchée par la tâche planifiée : elle existe et ne tourne jamais.';
}

$rendu = $lire( 'includes/kernel/class-acdc-kernel-render-trait.php' );
if ( '' !== $rendu ) {
    if ( ! preg_match( '/function render_admin_system_log_panel\(/', $rendu ) ) {
        $hits[] = 'l’écran du journal a disparu : le plugin écrit de nouveau une piste d’audit que personne ne peut ouvrir.';
    }
    if ( false === strpos( $rendu, '$this->render_admin_system_log_panel()' ) ) {
        $hits[] = 'l’écran du journal n’est plus appelé depuis « Données & maintenance » : il existe et ne s’affiche nulle part.';
    }
    /* Le journal affiche des valeurs venues de l'extérieur — un intitulé d'action
       peut contenir n'importe quoi. Chaque cellule doit être échappée. */
    if ( preg_match( '/function render_admin_system_log_panel\(.*?\n\}/s', $rendu, $m ) ) {
        if ( preg_match( "/echo '<td>' \. \\\$e->/", $m[0] ) || preg_match( "/echo '<td>' \. \(string\)/", $m[0] ) ) {
            $hits[] = 'une cellule du journal affiche une valeur sans l’échapper : c’est justement l’écran où atterrit ce que l’on n’a pas choisi.';
        }
    }
}

/* ── 8. LA SAUVEGARDE PREND TOUT, ET DIT CE QU'ELLE N'A PAS PRIS ────────── */

/* Deux listes de tables tenues à la main nommaient 40 tables ; le plugin en
   compte 56. Absentes de toutes les sauvegardes : les factures, les devis, le
   registre des réclamations, les contrats de sous-traitance, les quatre tables
   du portail formateur, ses documents, ses évaluations, son cahier de bord, les
   blocs de recueil, les activités de prospection, les thématiques, la veille.
   Et les FICHIERS n'y étaient pas non plus : la base ne garde que l'adresse
   d'une signature manuscrite, l'image vit dans les téléversements — une
   restauration rendait des émargements sans signature.
   Rien de tout cela ne se voyait : la sauvegarde s'annonçait « créée ». */
if ( '' !== $noyau ) {
    if ( preg_match( '/function get_plugin_table_map\(.*?\n  \}/s', $noyau, $m ) ) {
        if ( false === strpos( $m[0], 'SHOW TABLES LIKE' ) ) {
            $hits[] = 'la sauvegarde ne demande plus à la base quelles tables existent : elle retombe sur une liste tenue à la main, qui décrochera de nouveau sans que rien ne le signale.';
        }
        foreach ( array( 'companies', 'learners', 'system_logs' ) as $etiquette ) {
            if ( false === strpos( $m[0], "'" . $etiquette . "'" ) ) {
                $hits[] = sprintf( 'l’étiquette historique « %s » a disparu de la carte : les archives déjà produites ne seraient plus restaurables.', $etiquette );
            }
        }
    } else {
        $hits[] = 'get_plugin_table_map() est introuvable.';
    }
    if ( preg_match( '/function backup_data_snapshot\(.*?\n  \}/s', $noyau, $m ) ) {
        /* On exige le MÉCANISME, pas une mention : retirer la seule ligne
           d'initialisation laissait passer un contrôle qui cherchait
           « $manifest['fichiers'] ». C'est le sabotage qui l'a montré. */
        if ( false === strpos( $m[0], "glob( \$racine . 'acdc*', GLOB_ONLYDIR )" ) ) {
            $hits[] = 'la sauvegarde n’emporte plus les fichiers de preuve : une restauration rendrait des émargements dont la signature manuscrite n’existe plus.';
        }
        if ( false === strpos( $m[0], "'acdc-backups' === \$nom_dossier" ) ) {
            $hits[] = 'le dossier des sauvegardes n’est plus exclu de la copie : chaque archive contiendrait les précédentes, et grossirait sans fin.';
        }
        if ( false === strpos( $m[0], "\$manifest['tables_absentes']" ) || false === strpos( $m[0], "\$manifest['complete']" ) ) {
            $hits[] = 'la sauvegarde ne dit plus ce qu’elle n’a pas pris : elle peut de nouveau s’annoncer réussie en ayant laissé des données derrière elle.';
        }
    } else {
        $hits[] = 'backup_data_snapshot() est introuvable.';
    }
    if ( preg_match( '/function restore_backup_snapshot_from_manifest\(.*?\n  \}/s', $noyau, $m ) ) {
        if ( false === strpos( $m[0], "'fichiers de preuve'" ) ) {
            $hits[] = 'la restauration ne remet plus les fichiers de preuve : les lignes reviennent, les signatures non.';
        }
        if ( false === strpos( $m[0], "strpos( \$relatif_f, '..' )" ) ) {
            $hits[] = 'la restauration des fichiers ne refuse plus les chemins qui remontent : une archive préparée pourrait écrire hors des téléversements.';
        }
    }
    if ( ! preg_match( '/function acdc_nom_sauvegarde\(/', $noyau ) ) {
        $hits[] = 'le nom lisible des sauvegardes a disparu : les archives reprennent des noms techniques.';
    }
}

/* ── 9. L'ENVOI VERS LE DRIVE NE S'ÉLARGIT PAS TOUT SEUL ────────────────── */

/* Une archive qui sort de la machine emporte tout : les apprenants, les
   émargements signés, les factures. Trois choses la protègent, et chacune est
   du genre à se défaire lors d'une modification faite ailleurs — sans que rien
   ne se voie à l'écran, puisque l'envoi continuerait de réussir.

     — LA PORTÉE. « drive.file » ne donne accès QU'AUX fichiers déposés par
       l'application. Le remplacer par « auth/drive » tout court ouvrirait la
       totalité du Drive de l'exploitant, en lecture ET en suppression, pour un
       plugin qui n'a besoin que d'y écrire une archive.
     — LES SECRETS AU REPOS. Le secret client et le jeton de rafraîchissement
       sont chiffrés dans la base. Sans cela, un export de base — c'est-à-dire
       une sauvegarde — contiendrait les clés d'accès au Drive où elle part.
     — LE RETOUR DE GOOGLE. Il vaut acceptation : il exige donc à la fois le
       droit d'administration et le jeton d'état. */

$drive = $root . '/includes/backup-drive/class-acdc-backup-drive-trait.php';
if ( ! is_readable( $drive ) ) {
    $hits[] = 'l’envoi des sauvegardes vers le Drive a disparu : les archives ne quittent plus le serveur qu’elles protègent.';
} else {
    $src_drive = (string) file_get_contents( $drive );

    if ( preg_match( "#'scope'\s*=>\s*'([^']+)'#", $src_drive, $m ) ) {
        if ( 'https://www.googleapis.com/auth/drive.file' !== $m[1] ) {
            $hits[] = sprintf(
                'la portée demandée à Google est « %s » : le plugin réclame plus que le droit de déposer ses propres archives, et peut atteindre des fichiers qui ne sont pas les siens.',
                $m[1]
            );
        }
    } else {
        $hits[] = 'aucune portée n’est déclarée dans la demande d’autorisation Google : impossible de savoir ce que le plugin réclame.';
    }

    /* On exige le chiffrement là où il compte : à L'ÉCRITURE. Un déchiffrement
       à la lecture sans chiffrement à l'écriture laisserait les clés en clair
       dans la base sans que rien ne s'en aperçoive. */
    if ( preg_match( '/function acdc_gdrive_save_settings\(.*?\n\t\}/s', $src_drive, $m ) ) {
        if ( false === strpos( $m[0], 'acdc_secret_encrypt' ) ) {
            $hits[] = 'les réglages du Drive sont écrits sans chiffrement : le secret client et le jeton d’accès figureraient en clair dans la base — donc dans les sauvegardes elles-mêmes.';
        }
        foreach ( array( 'client_secret', 'refresh_token' ) as $secret ) {
            if ( false === strpos( $m[0], "'" . $secret . "'" ) ) {
                $hits[] = sprintf( 'le réglage « %s » n’est plus chiffré à l’écriture : une clé d’accès au Drive resterait lisible dans un export de base.', $secret );
            }
        }
    } else {
        $hits[] = 'acdc_gdrive_save_settings() est introuvable : on ne peut plus vérifier que les secrets du Drive sont chiffrés.';
    }

    if ( preg_match( '/function acdc_gdrive_maybe_handle_callback\(.*?\n\t\}/s', $src_drive, $m ) ) {
        if ( false === strpos( $m[0], "current_user_can( 'manage_options' )" ) ) {
            $hits[] = 'le retour de Google ne vérifie plus le droit d’administration : n’importe quel visiteur pourrait déclencher l’échange qui enregistre un jeton d’accès au Drive.';
        }
        if ( false === strpos( $m[0], "wp_verify_nonce( \$etat, 'acdc_gdrive_state' )" ) ) {
            $hits[] = 'le retour de Google ne vérifie plus le jeton d’état : un lien préparé ailleurs pourrait faire enregistrer au plugin un Drive qui n’est pas celui de l’exploitant.';
        }
    } else {
        $hits[] = 'le retour de Google est introuvable : la connexion du Drive ne peut plus aboutir.';
    }

    /* Ce qui efface doit être borné par le haut comme par le bas : « conserver
       0 » viderait le dossier à l'envoi suivant. */
    if ( preg_match( '/function acdc_gdrive_appliquer_conservation\(.*?\n\t\}/s', $src_drive, $m ) ) {
        if ( false === strpos( $m[0], "max( 1, (int) \$o['conserver'] )" ) ) {
            $hits[] = 'le nombre d’archives conservées n’est plus borné à 1 minimum : un réglage à zéro effacerait toutes les sauvegardes du dossier Drive.';
        }
        if ( false === strpos( $m[0], "in parents" ) ) {
            $hits[] = 'la purge des anciennes archives n’est plus limitée au dossier de destination : elle porterait sur ce que le plugin n’a pas déposé.';
        }
    } else {
        $hits[] = 'acdc_gdrive_appliquer_conservation() est introuvable : les archives s’accumuleraient sans fin sur le Drive.';
    }
}

/* ── VERDICT ────────────────────────────────────────────────────────────── */

if ( $hits ) {
    foreach ( $hits as $h ) {
        echo 'ALERTE  ' . $h . "\n";
    }
    printf( "%d alerte(s)\n", count( $hits ) );
    exit( 1 );
}
echo "Durcissement : pièces jointes retrouvées, réinitialisations limitées, propositions non listables, droits vérifiés, traces de suppression exactes, journal lisible, sauvegarde complète et qui dit ce qu’elle laisse, envoi Drive au périmètre le plus étroit et secrets chiffrés.\n";
exit( 0 );
