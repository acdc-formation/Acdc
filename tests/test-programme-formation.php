<?php
/**
 * D'où vient le lien « Programme de formation », et quand doit-il disparaître.
 *
 * Le défaut corrigé en 3.25.251 : la colonne `program_file_url` contenait, sur
 * les formations importées, une adresse de l'ancien plugin Manager portant un
 * NONCE. Un nonce est un jeton daté de vingt-quatre heures, lié à
 * l'utilisateur qui l'a créé : rangé dans une donnée, il est mort le
 * lendemain. Le commanditaire recevait « Lien invalide », et comme cette
 * adresse ne désigne aucun fichier des uploads, la pièce jointe promise
 * n'était pas jointe non plus.
 *
 * Deux questions distinctes, et c'est là que le premier code se trompait :
 *   — « où consulter le programme ? »  → un fichier, sinon la page du portail ;
 *   — « que puis-je JOINDRE à un e-mail ? » → un fichier, et rien d'autre.
 * La page du portail exige une connexion : la proposer à un destinataire
 * d'e-mail, c'est l'envoyer sur un écran de refus.
 */

/** Une adresse héritée du Manager : route d'administration ET action du Manager. */
function url_manager_morte( $url ) {
    $url = trim( (string) $url );
    if ( '' === $url ) { return false; }
    $route = ( false !== strpos( $url, 'admin-ajax.php' ) || false !== strpos( $url, 'admin-post.php' ) );
    return $route && ( false !== strpos( $url, 'acdc_fm_' ) || false !== strpos( $url, 'acdc_pdf_nonce' ) );
}

/**
 * Reproduit acdc_formation_programme_file() : un fichier, ou rien.
 * $disque = liste des fichiers réellement présents (adresses).
 */
function programme_fichier( $stocke, array $disque, $base_uploads = 'https://site.fr/wp-content/uploads' ) {
    $none = array( 'path' => '', 'url' => '' );
    $url  = trim( (string) $stocke );
    if ( '' === $url || url_manager_morte( $url ) ) { return $none; }
    if ( 0 !== strpos( $url, $base_uploads ) ) {
        return array( 'path' => '', 'url' => $url );   // externe : lien oui, pièce jointe non
    }
    if ( ! in_array( $url, $disque, true ) ) { return $none; }   // promet un fichier absent
    return array( 'path' => str_replace( $base_uploads, '/var/www/uploads', $url ), 'url' => $url );
}

/** Reproduit acdc_formation_programme_url() : le fichier, sinon la page du portail. */
function programme_lien( $stocke, array $disque, $formation_id = 12 ) {
    $f = programme_fichier( $stocke, $disque );
    if ( '' !== $f['url'] ) { return $f['url']; }
    return $formation_id ? 'https://site.fr/portail?fm_action=programme_pdf&fm_formation_id=' . $formation_id : '';
}

$UP   = 'https://site.fr/wp-content/uploads';
$MORT = 'https://site.fr/wp-admin/admin-ajax.php?action=acdc_fm_download_programme_pdf&formation_id=31858&acdc_pdf_nonce=e6dd916976';
$VRAI = $UP . '/acdc-programmes/programme-12-a1b2c3d4e5f6a7b8c9d0.pdf';

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s :\n       attendu « %s »\n       obtenu  « %s »\n", $label, $attendu, $obtenu );
    }
};

/* --- Reconnaissance des adresses mortes --- */
$t( 'le lien exact reçu par David est reconnu mort', url_manager_morte( $MORT ) ? 'oui' : 'non', 'oui' );
$t( 'un admin-post du Manager aussi',
    url_manager_morte( 'https://site.fr/wp-admin/admin-post.php?action=acdc_fm_prog&acdc_pdf_nonce=x' ) ? 'oui' : 'non', 'oui' );
$t( 'un fichier des uploads n’est pas mort', url_manager_morte( $VRAI ) ? 'oui' : 'non', 'non' );
/* Le piège : les deux conditions sont exigées ensemble. Un fichier déposé dont
   le NOM contient « admin-ajax » reste un fichier parfaitement valide. */
$t( 'un fichier nommé admin-ajax.php.pdf reste valide',
    url_manager_morte( $UP . '/2026/05/admin-ajax.php.pdf' ) ? 'oui' : 'non', 'non' );
$t( 'une route d’admin sans action Manager n’est pas visée',
    url_manager_morte( 'https://site.fr/wp-admin/admin-ajax.php?action=autre_chose' ) ? 'oui' : 'non', 'non' );

/* --- Ce qu'on peut joindre à un e-mail --- */
$t( 'rien à joindre quand la colonne porte un lien Manager',
    programme_fichier( $MORT, array( $VRAI ) )['path'], '' );
$t( 'rien à joindre quand le fichier annoncé est absent du disque',
    programme_fichier( $VRAI, array() )['path'], '' );
$t( 'le fichier déposé est joignable',
    programme_fichier( $VRAI, array( $VRAI ) )['path'], '/var/www/uploads/acdc-programmes/programme-12-a1b2c3d4e5f6a7b8c9d0.pdf' );
$t( 'une adresse externe est un lien, pas une pièce jointe',
    programme_fichier( 'https://autre-site.fr/programme.pdf', array() )['path'], '' );
$t( 'une adresse externe reste un lien',
    programme_fichier( 'https://autre-site.fr/programme.pdf', array() )['url'], 'https://autre-site.fr/programme.pdf' );

/* --- Où consulter le programme --- */
$t( 'le fichier déposé fait foi', programme_lien( $VRAI, array( $VRAI ) ), $VRAI );
$t( 'à défaut, la page du portail',
    programme_lien( $MORT, array() ), 'https://site.fr/portail?fm_action=programme_pdf&fm_formation_id=12' );
$t( 'colonne vide : la page du portail aussi',
    programme_lien( '', array() ), 'https://site.fr/portail?fm_action=programme_pdf&fm_formation_id=12' );

printf( "13 contrôles, %d échec(s)\n", $ko );
exit( 0 === $ko ? 0 : 1 );
