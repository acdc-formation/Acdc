<?php
/**
 * Les corrections de la recette de bout en bout : ce qui ne doit pas revenir.
 *
 * Cinq défauts distincts partagent une famille : UN ÉCRAN QUI AFFIRME SANS
 * AVOIR LU, ou une règle recopiée au lieu d'être appelée.
 *
 *   1. « Retard 138746min » — un nombre exact que personne ne peut lire.
 *   2. Une fonction « nom d'affichage » qui rendait le nom de stockage.
 *   3. Un consentement RGPD écrit DEUX FOIS, en deux formulations différentes.
 *   4. Le périmètre d'un formateur calculé trois fois, deux fois incomplet.
 *   5. Un moteur d'automatisation qui ne tourne qu'au passage d'un visiteur.
 *
 * Chaque contrôle ci-dessous a été vérifié EN LE SABOTANT : on a remis le
 * défaut, et le contrôle a échoué. Un contrôle qu'on n'a pas vu échouer ne
 * prouve rien.
 */

$racine = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : 'acdc-formation-saas-organisme-de-formation';
if ( ! is_dir( $racine ) ) {
    fwrite( STDERR, "Répertoire introuvable : {$racine}\n" );
    exit( 2 );
}

$alertes = array();
$signale = function ( $fichier, $ligne, $texte ) use ( &$alertes ) {
    $alertes[] = sprintf( '%s:%d — %s', $fichier, $ligne, $texte );
};

/** Le code d'un fichier, commentaires retirés — un commentaire qui décrit le
 *  défaut n'est pas le défaut. */
function acdc_code_lignes( $chemin ) {
    $out = array();
    $jetons = @token_get_all( (string) file_get_contents( $chemin ) );
    if ( ! is_array( $jetons ) ) { return $out; }
    $ligne = 1;
    foreach ( $jetons as $jeton ) {
        if ( is_array( $jeton ) ) {
            $ligne = $jeton[2];
            if ( T_COMMENT === $jeton[0] || T_DOC_COMMENT === $jeton[0] ) {
                $ligne += substr_count( $jeton[1], "\n" );
                continue;
            }
            $texte = $jeton[1];
        } else {
            $texte = $jeton;
        }
        foreach ( explode( "\n", $texte ) as $k => $morceau ) {
            $n = $ligne + $k;
            if ( ! isset( $out[ $n ] ) ) { $out[ $n ] = ''; }
            $out[ $n ] .= $morceau;
        }
        $ligne += substr_count( $texte, "\n" );
    }
    return $out;
}

$tous_les_php = array();
$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $racine . '/includes' ) );
foreach ( $rii as $f ) {
    if ( $f->isFile() && 'php' === strtolower( $f->getExtension() ) ) {
        $tous_les_php[] = $f->getPathname();
    }
}

/* --------------------------------------------------------------------------
 * 1. AUCUNE DURÉE BRUTE À L'ÉCRAN
 * ----------------------------------------------------------------------- */
foreach ( $tous_les_php as $chemin ) {
    $court = substr( $chemin, strlen( $racine ) + 1 );
    foreach ( acdc_code_lignes( $chemin ) as $n => $ligne ) {
        if ( preg_match( "/late_minutes\s*\.\s*'\s*m/i", $ligne ) ) {
            $signale( $court, $n, "un nombre de minutes est collé à un texte : « Retard 138746min » est exact et illisible. Passer par ACDC\\Support\\Duree." );
        }
    }
}

/* --------------------------------------------------------------------------
 * 2. UN « NOM D'AFFICHAGE » NE REND PAS LE NOM DE STOCKAGE
 * ----------------------------------------------------------------------- */
$conv = $racine . '/includes/kernel/class-acdc-kernel-core-trait.php';
$src  = (string) file_get_contents( $conv );
$deb  = strpos( $src, 'function get_training_convocation_display_file_name(' );
if ( false === $deb ) {
    $signale( 'includes/kernel/class-acdc-kernel-core-trait.php', 0, "la fonction qui nomme la convocation téléchargée a disparu." );
} else {
    $corps = substr( $src, $deb, 1800 );
    if ( false !== strpos( $corps, 'return basename(' ) ) {
        $signale( 'includes/kernel/class-acdc-kernel-core-trait.php', 0,
            "le « nom d'affichage » de la convocation rend de nouveau le basename du fichier stocké — c'est-à-dire son jeton de sécurité, et le nom lisible juste en dessous redevient du code mort." );
    }
    if ( false === strpos( $corps, 'NomDocument::composer' ) ) {
        $signale( 'includes/kernel/class-acdc-kernel-core-trait.php', 0,
            "la convocation ne suit plus la règle commune de nommage." );
    }
}

/* --------------------------------------------------------------------------
 * 3. LE CONSENTEMENT N'EST ÉCRIT QU'UNE FOIS
 * ----------------------------------------------------------------------- */
$phrase = "J'accepte que mes réponses soient utilisées";
foreach ( $tous_les_php as $chemin ) {
    $court = substr( $chemin, strlen( $racine ) + 1 );
    if ( false !== strpos( $court, 'kernel-core-trait' ) ) {
        /* Le catalogue des questions est la SOURCE de cette phrase : sa place
           est là, et nulle part ailleurs. */
        continue;
    }
    foreach ( acdc_code_lignes( $chemin ) as $n => $ligne ) {
        if ( false !== strpos( $ligne, $phrase ) ) {
            $signale( $court, $n,
                "le texte du consentement est réécrit hors du catalogue des questions : la personne verrait deux formulations du même engagement, et l'exploitant n'en corrigerait qu'une." );
        }
    }
}

/* --------------------------------------------------------------------------
 * 4. LE PÉRIMÈTRE DU FORMATEUR : UNE SEULE RÈGLE
 * ----------------------------------------------------------------------- */
$portee = array(
    'includes/quizzes/class-acdc-quizzes-core-trait.php',
    'includes/trainer-portal/render/class-acdc-trainer-portal-quizzes-render-trait.php',
);
foreach ( $portee as $fichier ) {
    $chemin = $racine . '/' . $fichier;
    if ( ! is_file( $chemin ) ) {
        $signale( $fichier, 0, "fichier introuvable : la règle de périmètre n'a plus de gardien." );
        continue;
    }
    foreach ( acdc_code_lignes( $chemin ) as $n => $ligne ) {
        /* La forme fautive : le périmètre recopié à la main, sans sa seconde
           moitié — le rattachement du formateur par un groupe. */
        if ( preg_match( '/s\.formation_id\s*=\s*q\.formation_id\s+AND\s+s\.trainer_id\s*=\s*%d/i', $ligne ) ) {
            $signale( $fichier, $n,
                "le périmètre du formateur est recopié sans le rattachement par groupe : un formateur d'un groupe voit ses formations et aucun de leurs quiz." );
        }
    }
}
$src_qz = (string) file_get_contents( $racine . '/includes/quizzes/class-acdc-quizzes-core-trait.php' );
if ( false === strpos( $src_qz, 'function acdc_sql_seance_animee_par(' ) ) {
    $signale( 'includes/quizzes/class-acdc-quizzes-core-trait.php', 0,
        "la règle unique de périmètre du formateur a disparu : elle va être recopiée, et diverger." );
}

/* --------------------------------------------------------------------------
 * 5. LE MOTEUR NE DÉPEND PAS DU SEUL CRON
 * ----------------------------------------------------------------------- */
$src_plug = (string) file_get_contents( $racine . '/includes/class-acdc-plugin.php' );
if ( false === strpos( $src_plug, "add_action( 'admin_init', array( \$this, 'acdc_wf_cron_en_admin' ) )" ) ) {
    $signale( 'includes/class-acdc-plugin.php', 0,
        "le moteur n'est plus relancé depuis l'administration : sur un site peu visité, le cron de WordPress ne se déclenche pas, et un envoi programmé « dans deux heures » partira le lendemain." );
}
if ( false === strpos( $src_plug, "add_action( 'acdc_of_workflow_cron', array( \$this, 'acdc_wf_cron' ) )" ) ) {
    $signale( 'includes/class-acdc-plugin.php', 0,
        "le rendez-vous au quart d'heure a disparu : plus rien ne tourne la nuit ni le week-end." );
}
$src_wf = (string) file_get_contents( $racine . '/includes/workflow/class-acdc-workflow-engine-trait.php' );
if ( false === strpos( $src_wf, "set_transient( 'acdc_of_wf_dernier_passage'" ) ) {
    $signale( 'includes/workflow/class-acdc-workflow-engine-trait.php', 0,
        "le passage en administration n'est plus bridé : chaque clic relancerait le moteur." );
}

/* --------------------------------------------------------------------------
 * 6bis. LA RAFALE D'ENVOIS NE PEUT PAS REVENIR
 *
 * Le 16 août, DOUZE messages sont partis en UNE SECONDE vers trois adresses du
 * même domaine — six échéances d'enquête nées avec des dates vieilles de trois
 * mois, ramassées d'un coup par un répartiteur sans plafond. Authentification
 * parfaite, et tout en indésirables : un filtre ne juge pas un message, il juge
 * un motif.
 * ----------------------------------------------------------------------- */
$src_q = (string) file_get_contents( $racine . '/includes/questionnaires/class-acdc-questionnaires-core-trait.php' );
$deb_q = strpos( $src_q, 'function process_scheduled_survey_dispatches(' );
if ( false === $deb_q ) {
    $signale( 'includes/questionnaires/class-acdc-questionnaires-core-trait.php', 0,
        "le répartiteur d'enquêtes a disparu." );
} else {
    $corps_q = substr( $src_q, $deb_q, 4000 );
    if ( false === strpos( $corps_q, 'LIMIT %d' ) ) {
        $signale( 'includes/questionnaires/class-acdc-questionnaires-core-trait.php', 0,
            "le répartiteur d'enquêtes n'a plus de plafond : il reviderait toute la pile des retards d'un seul trait, comme le 16 août." );
    }
    if ( ! preg_match( '/array_intersect\(\s*\$__adresses\s*,\s*\$__servis\s*\)/', $corps_q ) ) {
        $signale( 'includes/questionnaires/class-acdc-questionnaires-core-trait.php', 0,
            "la règle « un seul message par destinataire et par passage » a disparu : c'est elle qui casse la rafale." );
    }
    if ( ! preg_match( '/acdc_enquete_trop_tardive\(\s*\$session/', $corps_q ) ) {
        $signale( 'includes/questionnaires/class-acdc-questionnaires-core-trait.php', 0,
            "une enquête trop en retard repartirait en aveugle : elle poserait une question à laquelle la personne ne peut plus répondre honnêtement." );
    }
}
if ( false === strpos( $src_q, 'function acdc_replanifier_enquetes_de_seance(' ) ) {
    $signale( 'includes/questionnaires/class-acdc-questionnaires-core-trait.php', 0,
        "les échéances d'enquête ne suivent plus les dates de la séance : déplacer une séance laisserait des rendez-vous accrochés à l'ancienne date." );
}
$src_s = (string) file_get_contents( $racine . '/includes/sessions/class-acdc-sessions-actions-trait.php' );
if ( false === strpos( $src_s, '$this->acdc_replanifier_enquetes_de_seance( $session_id )' ) ) {
    $signale( 'includes/sessions/class-acdc-sessions-actions-trait.php', 0,
        "l'enregistrement d'une séance n'appelle plus la replanification : la règle existerait sans que personne ne l'invoque." );
}

/* --------------------------------------------------------------------------
 * 6ter. AUCUN E-MAIL NE PART SANS SA VERSION TEXTE
 * ----------------------------------------------------------------------- */
$src_k = (string) file_get_contents( $racine . '/includes/kernel/class-acdc-kernel-core-trait.php' );
if ( false === strpos( $src_k, 'function acdc_poser_version_texte(' ) ) {
    $signale( 'includes/kernel/class-acdc-kernel-core-trait.php', 0,
        "la pose de la version texte a disparu : les e-mails repartiraient en HTML pur, l'un des signaux relevés par la recette du 16 août." );
}
if ( false === strpos( $src_plug, "add_action( 'phpmailer_init', array( \$this, 'acdc_poser_version_texte' ) )" ) ) {
    $signale( 'includes/class-acdc-plugin.php', 0,
        "la version texte n'est plus branchée : la fonction existerait sans que rien ne l'appelle." );
}

/* --------------------------------------------------------------------------
 * 6. LE PORTAIL APPRENANT NE PROMET QUE CE QUI EST PRÉVU
 * ----------------------------------------------------------------------- */
$src_ap = (string) file_get_contents( $racine . '/includes/learner-portal/core/class-acdc-learner-portal-core-trait.php' );
if ( false === strpos( $src_ap, 'function learner_portal_evaluations_prevues(' ) ) {
    $signale( 'includes/learner-portal/core/class-acdc-learner-portal-core-trait.php', 0,
        "plus rien ne vérifie qu'une évaluation est prévue : le portail réafficherait « Résultat du positionnement — Bientôt disponible » sur une formation qui n'en comporte pas." );
}
if ( false === strpos( $src_ap, 'function learner_portal_seances_du_parcours(' ) ) {
    $signale( 'includes/learner-portal/core/class-acdc-learner-portal-core-trait.php', 0,
        "« Mon planning » est revenu à la seule séance inscrite sur la ligne de l'apprenant : une formation de quatre séances n'en montrerait qu'une." );
}

/* --------------------------------------------------------------------------
 * VERDICT
 * ----------------------------------------------------------------------- */
echo "\n";
if ( $alertes ) {
    foreach ( $alertes as $a ) { echo "ALERTE  {$a}\n"; }
    printf( "%d alerte(s)\n", count( $alertes ) );
    exit( 1 );
}
echo "Recette des écrans : durées lisibles, noms lisibles, un seul consentement, un seul périmètre formateur, un moteur qui tourne.\n";
exit( 0 );
