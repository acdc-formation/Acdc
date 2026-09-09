<?php
/**
 * Combien d'apprenants sur une séance ? Une seule réponse.
 *
 * Un apprenant peut être rattaché à une séance de trois façons : par le lien
 * direct apprenant.session_id, par un groupe, ou par la CONVENTION qui couvre
 * cette séance. Le dernier chemin est le plus courant depuis que la convention
 * crée les séances — et c'est celui qui laisse session_id vide.
 *
 * Le tableau de bord comptait avec sa propre sous-requête :
 *
 *     (SELECT COUNT(*) FROM apprenants l WHERE l.session_id = s.id)
 *
 * Un seul des trois chemins. La fiche de la séance affichait « Apprenants (3) »
 * pendant que « Prochaines sessions » annonçait « 0 apprenant(s) », pour la
 * même séance, sur le même écran d'accueil.
 *
 * La réponse vit dans acdc_session_learners(), qui réunit les trois. Ce
 * balayage refuse qu'on la réécrive ailleurs.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$rii       = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits      = array();
$resolveur = false;

foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }

    $src   = (string) file_get_contents( $path );
    $rel   = preg_replace( '#^.*/includes/#', '', $path );
    $lines = explode( "\n", $src );

    if ( false !== strpos( $src, 'function acdc_session_learners' ) ) {
        $resolveur = true;
        /* Le résolveur a le droit — et le devoir — d'interroger session_id. */
        continue;
    }

    foreach ( $lines as $i => $line ) {
        $t = ltrim( $line );
        if ( 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '/*' ) ) { continue; }
        /* Un comptage d'apprenants adossé au seul session_id. */
        if ( preg_match( '/COUNT\(\s*\*\s*\)\s+FROM\s+\{\$this->learner_table\}[^)]*session_id/i', $line ) ) {
            $hits[] = sprintf( '%s:%d  comptage des apprenants sur le seul session_id — les séances nées d’une convention afficheront zéro.  %s', $rel, $i + 1, trim( $line ) );
        }

        /* ACDC 3.25.270 — LA MÊME FAUTE, ÉCRITE AUTREMENT.
           Le balayage ne guettait qu'une forme : COUNT(*) sur la table des
           apprenants. Le bilan pédagogique et financier, lui, l'écrivait en
           JOINTURE — « INNER JOIN séances ON s.id = l.session_id » — et il est
           donc passé au travers pendant tout ce temps, en déclarant zéro
           stagiaire à la DREETS. Une règle qui ne reconnaît qu'une orthographe
           ne protège que d'une faute d'orthographe.
           Les exports CSV sont laissés de côté : ils reproduisent volontairement
           l'ancien rattachement, colonne par colonne, pour les fichiers déjà
           livrés à des tiers. */
        if ( false !== strpos( $path, 'export-csv' ) ) { continue; }
        if ( preg_match( '/JOIN\s+\{\$this->session_table\}\s+s\s+ON\s+s\.id\s*=\s*l\.session_id/i', $line )
          || preg_match( '/JOIN\s+\{\$this->learner_table\}\s+l\s+ON\s+l\.session_id\s*=\s*s\.id/i', $line ) ) {
            /* Une requête a le droit de commencer par le lien direct SI elle
               consulte ensuite le résolveur — c'est ce que fait la liste des
               séances validées depuis la 3.25.212 : elle compte d'abord vite,
               puis corrige. Ce qui est interdit, c'est de s'en tenir là. */
            $bloc = implode( "\n", array_slice( $lines, max( 0, $i - 40 ), 90 ) );
            if ( false !== strpos( $bloc, 'acdc_session_learner' ) ) { continue; }
            $hits[] = sprintf(
                '%s:%d  apprenants joints aux séances par `learner.session_id` — la colonne que la convention ne renseigne jamais, puisque c’est elle qui crée les séances.  %s',
                $rel,
                $i + 1,
                trim( mb_substr( trim( $line ), 0, 110 ) )
            );
        }
    }
}

if ( ! $resolveur ) {
    $hits[] = 'acdc_session_learners() a disparu : le rattachement des apprenants à une séance redevient une réécriture, écran par écran.';
}

if ( $hits ) {
    echo "Comptage des apprenants d’une séance — anomalies :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 occurrence — les apprenants d’une séance se comptent au même endroit.\n";
exit( 0 );
