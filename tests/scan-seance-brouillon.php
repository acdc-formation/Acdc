<?php
/**
 * Un brouillon retient la convocation — sur TOUS les chemins, et sans faire
 * disparaître la séance.
 *
 * Depuis la 3.25.268, une séance née d'une convention sans formateur naît en
 * brouillon, et ce brouillon retient la convocation : elle annonce un lieu, des
 * horaires et un intervenant, et une convocation fausse est pire qu'une
 * convocation tardive.
 *
 * Deux façons de perdre cette règle, et ce balayage les guette toutes les deux.
 *
 * LA PREMIÈRE est celle qui revient le plus souvent dans ce plugin : une règle
 * recopiée au lieu d'être appelée. Trois codes expédient une convocation — le
 * moteur, le cron historique, le bouton du dossier. Une porte posée sur un seul
 * d'entre eux n'est pas une porte. Chaque chemin doit donc consulter
 * SessionDraftGate, ou écarter lui-même les brouillons dans sa requête.
 *
 * LA SECONDE est le piège inverse, et il est plus vicieux : écarter les
 * brouillons DANS LE MOTEUR. Le parcours perdrait d'un coup ses séances, ses
 * dates, son formateur et ses rappels d'émargement, et réclamerait « Rattacher
 * une séance au dossier » alors que les séances sont là. Une porte ne se ferme
 * pas en effaçant ce qu'elle garde : le moteur VOIT les brouillons, et retient
 * une seule étape.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$rii  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();
$vus  = 0;

foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }
    if ( false !== strpos( $path, '/src/Support/' ) ) { continue; }

    $src   = (string) file_get_contents( $path );
    $rel   = preg_replace( '#^.*/(includes|src)/#', '$1/', $path );
    $lines = explode( "\n", $src );

    /* ── PREMIER PASSAGE : les chemins d'envoi ────────────────────────────
       On découpe grossièrement par fonction — l'indentation de ce plugin est
       régulière — et l'on regarde, pour chaque fonction qui compose une
       convocation, si elle sait ce qu'est un brouillon. */
    $debut = 0;
    $nom   = '';
    $corps = array();
    $blocs = array();
    foreach ( $lines as $i => $line ) {
        if ( preg_match( '/^\s{2}(?:private|public|protected)\s+function\s+(\w+)/', $line, $m ) ) {
            if ( '' !== $nom ) { $blocs[] = array( $nom, $debut, implode( "\n", $corps ) ); }
            $nom   = $m[1];
            $debut = $i + 1;
            $corps = array();
        }
        $corps[] = $line;
    }
    if ( '' !== $nom ) { $blocs[] = array( $nom, $debut, implode( "\n", $corps ) ); }

    foreach ( $blocs as $bloc ) {
        list( $fonction, $ligne, $texte ) = $bloc;
        /* La fonction qui COMPOSE la convocation n'envoie rien : elle met en
           forme. C'est aux chemins d'envoi de décider s'il faut envoyer. */
        if ( 'acdc_convocation_email_parts' === $fonction ) { continue; }
        if ( false === strpos( $texte, 'acdc_convocation_email_parts(' ) ) { continue; }

        $vus++;
        /* Attention au piège des mots identiques : les DOSSIERS d'inscription
           portent eux aussi une colonne `is_draft`, et le chemin du moteur en
           écarte les brouillons. S'en contenter comme preuve reviendrait à
           accepter qu'une fonction filtre les dossiers et ne regarde jamais la
           séance — c'est-à-dire à valider précisément le défaut visé. On
           n'accepte donc un filtre maison que s'il porte sur LA SÉANCE. */
        $sait = false !== strpos( $texte, 'SessionDraftGate' );
        if ( ! $sait ) {
            $bloc_lignes = explode( "\n", $texte );
            foreach ( $bloc_lignes as $j => $bl ) {
                if ( ! preg_match( '/is_draft/', $bl ) ) { continue; }
                if ( false !== strpos( $bl, 'training_registration_table' ) ) { continue; }
                $autour = implode( "\n", array_slice( $bloc_lignes, max( 0, $j - 6 ), 12 ) );
                if ( false !== strpos( $autour, 'session_table' ) && false === strpos( $autour, 'training_registration_table' ) ) {
                    $sait = true;
                    break;
                }
            }
        }
        if ( ! $sait ) {
            $hits[] = sprintf(
                '%s:%d  %s() compose une convocation sans consulter SessionDraftGate — une séance en brouillon la laisserait partir, avec un formateur que personne n’a désigné.',
                $rel,
                $ligne,
                $fonction
            );
        }
    }

    /* ── SECOND PASSAGE : le moteur ne doit pas perdre les brouillons ───── */
    if ( false === strpos( $path, '/workflow/' ) ) { continue; }
    foreach ( $lines as $i => $line ) {
        $t = ltrim( $line );
        if ( 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '/*' ) ) { continue; }
        if ( ! preg_match( '/is_draft\s*=\s*0/', $line ) ) { continue; }
        /* Les DOSSIERS d'inscription ont eux aussi une colonne is_draft, et
           là c'est légitime : un dossier en brouillon n'inscrit personne. */
        if ( false !== strpos( $line, 'training_registration_table' ) ) { continue; }
        $voisinage = implode( "\n", array_slice( $lines, max( 0, $i - 6 ), 12 ) );
        if ( false === strpos( $voisinage, 'session_table' ) ) { continue; }
        $hits[] = sprintf(
            '%s:%d  le moteur écarte les séances en brouillon — le parcours perdrait ses dates, son formateur et ses rappels, et réclamerait une séance qui est pourtant là.  %s',
            $rel,
            $i + 1,
            trim( $line )
        );
    }
}

if ( $hits ) {
    echo "Séance en brouillon — anomalies :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
printf( "0 occurrence — %d chemin(s) de convocation, tous passent par la même porte.\n", $vus );
exit( 0 );
