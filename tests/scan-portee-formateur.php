<?php
/**
 * Un formateur ne voit que ce qu'il anime, et jamais les coordonnées.
 *
 * Deux règles posées par David, et deux façons de les perdre.
 *
 * LA PORTÉE. « Il peut y avoir plusieurs formations du même nom lancées les
 * mêmes jours » — et dans l'extranet formateur, le filtre portait sur la
 * FORMATION, pas sur la séance : dès qu'un formateur avait animé une séance
 * d'une formation, il voyait tous les envois de cette formation, y compris ceux
 * du groupe d'un collègue, avec les résultats nominatifs des apprenants
 * correspondants. Pire, un repli affichait TOUT à qui n'avait rien — le
 * commentaire d'origine l'assumait : « pour un formateur unique, le fallback
 * affichera tous les quiz du SaaS ».
 *
 * LES COORDONNÉES. « Je ne veux pas que les e-mails ou téléphones des
 * apprenants soient visibles (RGPD et confidentiel). » La permission
 * `view_learner_personal_data` existait déjà — mais un seul écran la
 * consultait, pendant que deux autres affichaient l'adresse sans rien demander.
 * Et l'écran qui la respectait affichait, lui, la phrase « Coordonnées
 * masquées ». Une promesse fausse est pire qu'une absence de promesse : on
 * croit la donnée protégée, donc on ne vérifie plus.
 *
 * Ce balayage refuse le retour de l'une ou l'autre.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$rii  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();

foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }

    $src   = (string) file_get_contents( $path );
    $rel   = preg_replace( '#^.*/includes/#', '', $path );
    $lines = explode( "\n", $src );

    $portail_formateur = false !== strpos( $path, '/trainer-portal/' );

    foreach ( $lines as $i => $line ) {
        $t = ltrim( $line );
        if ( 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '/*' ) ) { continue; }

        /* ── Le repli qui montre tout ───────────────────────────────────── */
        if ( preg_match( "/'fallback_all_active'\s*=>\s*true/", $line ) ) {
            $hits[] = sprintf(
                '%s:%d  le repli « voir tout » est réactivé : un formateur sans envoi verrait ceux de tout l’organisme, apprenants nommés et scores compris.',
                $rel,
                $i + 1
            );
        }

        /* ── La portée par formation au lieu de la séance ───────────────── */
        if ( preg_match( '/\w+\.formation_id\s*=\s*(q|s)\.formation_id.*trainer_id\s*=\s*%d/i', $line ) ) {
            /* La branche de repli des envois SANS séance rattachée est
               légitime : elle est encadrée juste au-dessus par un
               `formation_session_id IS NULL` explicite. */
            $encadrement = implode( "\n", array_slice( $lines, max( 0, $i - 4 ), 6 ) );
            if ( false !== strpos( $encadrement, 'formation_session_id IS NULL' ) ) { continue; }
            /* La liste des quiz d'une formation est légitime — c'est le
               catalogue dans lequel le formateur choisit. Ce qui ne l'est pas,
               c'est de rattacher ainsi des ENVOIS, qui portent des résultats. */
            $voisinage = implode( "\n", array_slice( $lines, max( 0, $i - 12 ), 25 ) );
            if ( false !== strpos( $voisinage, 'dispatch' ) || false !== strpos( $voisinage, 'qz_sessions' ) ) {
                $hits[] = sprintf(
                    '%s:%d  les envois sont rattachés au formateur par la FORMATION : il verra les groupes de ses collègues.  %s',
                    $rel,
                    $i + 1,
                    trim( mb_substr( trim( $line ), 0, 100 ) )
                );
            }
        }

        /* ── L'adresse ou le téléphone d'un apprenant, sans permission ──── */
        if ( ! $portail_formateur ) { continue; }
        /* Deux formes : la lecture directe `$l->email`, et la variable locale
           qu'on lui a affectée deux lignes plus haut — c'est celle-là qui a
           servi pendant tout ce temps à afficher l'adresse dans la liste des
           destinataires. Le formateur, lui, se lit sur `$account` : il a le
           droit de voir sa propre adresse. */
        if ( ! preg_match( '/->(email|phone)\b/', $line )
            && ! preg_match( '/echo[^;]*\$(email|phone)\b/', $line ) ) { continue; }
        if ( preg_match( '/\$(account|trainer|tr)\b/', $line ) ) { continue; }   // le formateur lui-même
        if ( false === strpos( $line, 'echo' ) && false === strpos( $line, '?:' ) ) { continue; }

        /* La permission doit GARDER la ligne, pas seulement exister dans les
           parages. Une variable calculée dix lignes plus haut puis oubliée
           laisse passer exactement le défaut qu'on traque : on exige donc un
           `if` qui la teste, sur la ligne même ou juste au-dessus. */
        $garde = implode( "\n", array_slice( $lines, max( 0, $i - 3 ), 4 ) );
        if ( preg_match( '/if\s*\(.*(view_learner_personal_data|can_[a-z_]*personal)/', $garde ) ) {
            continue;
        }
        /* La ligne qui consulte elle-même la permission se garde toute seule :
           un ternaire vaut un `if`. */
        if ( preg_match( '/(view_learner_personal_data|can_[a-z_]*personal)/', $line ) ) {
            continue;
        }
        $hits[] = sprintf(
            '%s:%d  coordonnée d’apprenant affichée sans consulter view_learner_personal_data — l’écran des séances promet pourtant qu’elles sont masquées.  %s',
            $rel,
            $i + 1,
            trim( mb_substr( trim( $line ), 0, 100 ) )
        );
    }
}

if ( $hits ) {
    echo "Portée et confidentialité dans l’extranet formateur :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 occurrence — chaque formateur ne voit que ses séances, et jamais les coordonnées sans permission.\n";
exit( 0 );
