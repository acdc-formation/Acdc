<?php
/**
 * ACDC 3.25.315 — Le verrou du dossier des signatures.
 *
 * Ce que ce contrôle défend, et pourquoi chaque règle existe :
 *
 *  1. Le dossier « acdc-signatures/ » reçoit les signatures manuscrites, dont le
 *     nom ne porte AUCUN jeton (« signature-<n>-<horodatage>.png ») dans un
 *     dossier numéroté en séquence. Sans refus de répertoire, elles sont
 *     devinables.
 *  2. Le certificat d'audit vit dans ce MÊME dossier et son adresse part au
 *     signataire. L'exception PDF n'est pas un confort : sans elle, le lien
 *     envoyé au client meurt.
 *  3. La pièce d'identité ne doit jamais repasser par cette exception.
 *  4. La règle se mesure, et l'état d'avant est rétabli quand la mesure n'est
 *     pas concluante — un verrou invérifiable ne reste pas posé en production.
 *  5. Le drapeau « fait » s'écrit APRÈS le travail et seulement sur un verdict
 *     concluant : c'est la faute de la 3.25.313 sur l'émargement, dont le
 *     numéro s'inscrivait avant la migration et interdisait tout rattrapage.
 *  6. Les appels HTTP ne doivent jamais partir sur une page publique — la faute
 *     de la 3.25.176, qui avait mis le site à genoux.
 *
 * DEUX PIÈGES QUE CE CONTRÔLE A DÛ APPRENDRE À ÉVITER, et qui l'avaient rendu
 * complaisant à sa première écriture :
 *
 *  — Chercher « Order allow,deny » dans le source trouve aussi le COMMENTAIRE
 *    que la règle écrit dans le .htaccess. Le contrôle passait alors que la
 *    directive avait été retirée. On décode donc la règle produite et on
 *    n'examine que ses lignes utiles, commentaires écartés.
 *  — Un « .*? » avec le drapeau « /s » traverse le fichier entier : la
 *    vérification du garde-fou « is_admin() » allait en trouver un autre, cent
 *    lignes plus loin, dans une fonction sans rapport. On borne donc chaque
 *    examen au corps de la fonction concernée.
 *
 * Les treize règles ont été éprouvées en les sabotant une par une : chacune
 * échoue quand on défait la correction.
 */

$racine = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation';
$noyau  = $racine . '/includes/kernel/class-acdc-kernel-core-trait.php';
$plugin = $racine . '/includes/class-acdc-plugin.php';

foreach ( array( $noyau, $plugin ) as $fichier ) {
    if ( ! is_readable( $fichier ) ) {
        fwrite( STDERR, "Fichier introuvable : $fichier\n" );
        exit( 1 );
    }
}

$src_noyau  = (string) file_get_contents( $noyau );
$src_plugin = (string) file_get_contents( $plugin );

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) {
        $echecs[] = $message;
    }
};

/**
 * Le corps d'une méthode, borné à la déclaration suivante. Sans cette borne,
 * toute recherche déborde sur le reste du fichier et trouve ce qu'elle veut.
 */
$extraire = function ( $src, $signature ) {
    $i = strpos( $src, $signature );
    if ( false === $i ) {
        return '';
    }
    $reste = substr( $src, $i + strlen( $signature ) );
    if ( preg_match( '/\n  (?:public|private|protected) function /', $reste, $m, PREG_OFFSET_CAPTURE ) ) {
        return $signature . substr( $reste, 0, $m[0][1] );
    }
    return $signature . $reste;
};

$corps   = $extraire( $src_noyau, 'private function acdc_verrouiller_signatures()' );
$entree  = $extraire( $src_noyau, 'public function acdc_verrouiller_signatures_en_admin()' );
$verdict = $extraire( $src_noyau, 'private function acdc_verrou_signatures_verdict(' );

$exiger( '' !== $corps, 'acdc_verrouiller_signatures() est absente du noyau.' );
$exiger( '' !== $entree, 'Le point d\'entrée public acdc_verrouiller_signatures_en_admin() est absent.' );
$exiger( '' !== $verdict, 'acdc_verrou_signatures_verdict() est absente du noyau.' );
if ( '' === $corps || '' === $entree || '' === $verdict ) {
    foreach ( $echecs as $e ) { fwrite( STDERR, "ÉCHEC — $e\n" ); }
    exit( 1 );
}

/* -------------------------------------------------------------------------
 * La règle produite, décodée — et non le source qui la fabrique.
 * ---------------------------------------------------------------------- */
$regle_txt = '';
if ( preg_match( '/\$regle\s*=(.*?);\n/s', $corps, $m ) ) {
    if ( preg_match_all( '/"((?:[^"\\\\]|\\\\.)*)"/', $m[1], $litteraux ) ) {
        foreach ( $litteraux[1] as $litteral ) {
            $regle_txt .= stripcslashes( $litteral );
        }
    }
}
$exiger( '' !== $regle_txt, 'La règle .htaccess est introuvable ou illisible dans la fonction.' );

/* Les lignes utiles : ni vides, ni commentaires. C'est ce que le serveur lit. */
$utiles = array();
foreach ( explode( "\n", $regle_txt ) as $ligne ) {
    $ligne = trim( $ligne );
    if ( '' === $ligne || '#' === substr( $ligne, 0, 1 ) ) {
        continue;
    }
    $utiles[] = $ligne;
}

$suivante = function ( $ouverture ) use ( $utiles ) {
    $i = array_search( $ouverture, $utiles, true );
    return ( false === $i || ! isset( $utiles[ $i + 1 ] ) ) ? '' : $utiles[ $i + 1 ];
};

/* 1. Le refus porte sur le répertoire. Un motif de fichier seul ne suffit pas :
      un serveur qui résout le fichier avant la règle renvoie 404 sans jamais la
      consulter. */
$exiger(
    in_array( 'Order allow,deny', $utiles, true ),
    'La directive « Order allow,deny » a disparu des lignes utiles de la règle : les signatures manuscrites, dont le nom ne porte aucun jeton, redeviennent devinables.'
);

/* 2. L'exception PDF, sans laquelle le certificat envoyé au signataire meurt. */
$exiger(
    in_array( '<FilesMatch "\.pdf$">', $utiles, true ) && 'Allow from all' === $suivante( '<FilesMatch "\.pdf$">' ),
    "L'exception PDF a disparu ou n'autorise plus rien : le certificat d'audit, dont l'adresse est envoyée au signataire, deviendrait injoignable."
);

/* 3. La pièce d'identité ne repasse pas par cette exception. */
$exiger(
    in_array( '<FilesMatch "^id-">', $utiles, true ) && 'Deny from all' === $suivante( '<FilesMatch "^id-">' ),
    "Le refus des pièces d'identité (« ^id- ») a disparu ou ne refuse plus rien : une pièce déposée en PDF passerait par l'exception."
);

/* 4. La mesure porte sur les DEUX extensions : une seule sonde ne distingue pas
      « refus appliqué » de « exception cassée ». */
$exiger(
    (bool) preg_match( '/\$code_png\s*=\s*\$sonde\(/', $corps ),
    'La sonde .png a disparu : plus rien ne vérifie que le refus de répertoire s\'applique.'
);
$exiger(
    (bool) preg_match( '/\$code_pdf\s*=\s*\$sonde\(/', $corps ),
    'La sonde .pdf a disparu : une exception cassée ne serait plus distinguée d\'un refus qui marche.'
);
$exiger(
    (bool) preg_match( '/403 === \$code_png && 404 === \$code_pdf/', $corps ),
    'Le seul verdict concluant (403 sur .png ET 404 sur .pdf) n\'est plus celui qui conserve la règle.'
);

/* 5. Le rétablissement : appelé APRÈS le verdict concluant — donc jamais sur
      lui — et AVANT tous les autres. */
$pos_actif   = strpos( $corps, "'actif'" );
$pos_restore = strpos( $corps, '$restore();' );
$pos_rejete  = strpos( $corps, "'rejete'" );
$exiger(
    false !== $pos_restore && false !== $pos_actif && $pos_actif < $pos_restore,
    "L'état d'avant n'est plus rétabli, ou il l'est aussi sur un verdict concluant : le verrou serait défait alors qu'il fonctionne."
);
$exiger(
    false !== $pos_rejete && $pos_restore < $pos_rejete,
    "Le rétablissement ne précède plus les verdicts non concluants : un verrou invérifiable resterait posé en production."
);

/* 6. La sonde vise un nom explicitement inexistant : aucune donnée réelle n'est
      lue ni transmise par la mesure. */
$exiger(
    false !== strpos( $corps, 'inexistant' ),
    'La sonde ne vise plus un nom explicitement inexistant : la mesure risquerait de lire une signature réelle.'
);

/* 7. Un dossier encore absent ne marque pas le travail comme fait : il naît à la
      première signature, et le passage suivant doit le prendre. */
$exiger(
    ! preg_match( '/is_dir\( \$dir \).{0,240}_fait/s', $corps ),
    'Un dossier encore absent marque le travail comme fait : le verrou ne serait jamais posé sur une installation neuve.'
);

/* 8. Le drapeau « fait » n'est jamais posé sur une mesure impossible, et il
      s'écrit APRÈS le verdict. */
$exiger(
    (bool) preg_match( "/in_array\(\s*\\\$verdict,\s*array\((.*?)\)/s", $verdict, $m_liste )
        && false !== strpos( $m_liste[1], "'actif'" )
        && false === strpos( $m_liste[1], "'indetermine'" ),
    "Le drapeau « fait » peut s'écrire sur un verdict « indetermine » : le dossier resterait ouvert pour toujours sur la foi d'un appel HTTP qui a échoué une fois."
);
$pos_v = strpos( $verdict, "_verdict', \$verdict" );
$pos_f = strpos( $verdict, "_fait', '1'" );
$exiger(
    false !== $pos_v && false !== $pos_f && $pos_v < $pos_f,
    'Le drapeau « fait » s\'écrit avant le verdict : c\'est l\'ordre fautif de la 3.25.313 sur l\'émargement.'
);

/* 9. Jamais sur une page publique. */
$exiger(
    (bool) preg_match( "/add_action\(\s*'admin_init',\s*array\(\s*\\\$this,\s*'acdc_verrouiller_signatures_en_admin'/", $src_plugin ),
    "Le verrou n'est plus accroché à « admin_init » : les appels HTTP de la mesure partiraient sur la visite d'un prospect (faute de la 3.25.176)."
);
$exiger(
    false !== strpos( $entree, 'is_admin()' ) && strpos( $entree, 'is_admin()' ) < strpos( $entree, 'acdc_verrouiller_signatures()' ),
    'Le garde-fou « is_admin() » a disparu du point d\'entrée public, ou il ne précède plus l\'appel.'
);

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Verrou des signatures : %d/%d vérifications vertes.\n", $verifs, $verifs );
exit( 0 );
