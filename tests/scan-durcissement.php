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

/* ── VERDICT ────────────────────────────────────────────────────────────── */

if ( $hits ) {
    foreach ( $hits as $h ) {
        echo 'ALERTE  ' . $h . "\n";
    }
    printf( "%d alerte(s)\n", count( $hits ) );
    exit( 1 );
}
echo "Durcissement : chemin des pièces jointes calculé, réinitialisations limitées, propositions non listables et non devinables, droits vérifiés sur les séances, traces de suppression exactes.\n";
exit( 0 );
