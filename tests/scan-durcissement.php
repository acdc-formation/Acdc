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

/* ── VERDICT ────────────────────────────────────────────────────────────── */

if ( $hits ) {
    foreach ( $hits as $h ) {
        echo 'ALERTE  ' . $h . "\n";
    }
    printf( "%d alerte(s)\n", count( $hits ) );
    exit( 1 );
}
echo "Durcissement : pièces jointes retrouvées, réinitialisations limitées, propositions non listables et non devinables, droits vérifiés, traces de suppression exactes, journal conservé, daté, situé et lisible.\n";
exit( 0 );
