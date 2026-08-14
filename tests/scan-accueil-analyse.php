<?php
/**
 * L'analyse du besoin salue toujours le même destinataire.
 *
 * Pour un commanditaire entreprise, c'est l'ENTITÉ qui est destinataire du
 * questionnaire, pas la personne qui tient le clavier. La règle existe depuis
 * la 3.21.29 ; sa source a été corrigée en 3.25.260 — mais à UN SEUL endroit,
 * l'en-tête du formulaire. Les deux écrans de remerciement lisaient encore le
 * prénom du répondant.
 *
 * Le même document affichait donc « Bonjour Skill Conseil 👋 » en haut et
 * « Merci Valeriano ! » à la fin : deux réponses à la même question. C'est la
 * panne la plus fréquente de ce plugin, et elle revient chaque fois qu'une
 * règle est RECOPIÉE au lieu d'être APPELÉE.
 *
 * Ce balayage vérifie qu'aucun écran de l'analyse du besoin ne salue quelqu'un
 * en lisant directement le prénom du répondant.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$rii      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits     = array();
$fonction = false;

foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }

    $src   = (string) file_get_contents( $path );
    $rel   = preg_replace( '#^.*/includes/#', '', $path );
    $lines = explode( "\n", $src );

    if ( false !== strpos( $src, 'function nad_greeting_name' ) ) {
        $fonction = true;
    }
    /* La fonction commune a le droit — et le devoir — de lire le prénom. */
    if ( false !== strpos( $src, 'function nad_greeting_name' )
      && false === strpos( $src, 'nad-success' ) ) {
        continue;
    }

    foreach ( $lines as $i => $line ) {
        $t = ltrim( $line );
        if ( 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '/*' ) ) { continue; }
        /* Une salutation qui imprime le prénom du répondant. */
        if ( ! preg_match( '/(Bonjour|Merci)/', $line ) ) { continue; }
        if ( ! preg_match( '/repondant_prenom|\$prenom\b/', $line ) ) { continue; }
        $hits[] = sprintf( '%s:%d  salutation construite sur le prénom du répondant.  %s', $rel, $i + 1, trim( $line ) );
    }
}

if ( ! $fonction ) {
    $hits[] = 'nad_greeting_name() a disparu : la règle du destinataire redevient une recopie, écran par écran.';
}

if ( $hits ) {
    echo "Accueil de l’analyse du besoin — anomalies :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 occurrence — les écrans de l’analyse du besoin saluent tous le même destinataire.\n";
exit( 0 );
