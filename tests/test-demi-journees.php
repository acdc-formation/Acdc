<?php
/**
 * « Heures de formation dispensées : 16:00:00 » pour deux journées qui en font 14.
 *
 * Le calcul lisait déjà les demi-journées ; il ne retombait sur « début → fin »
 * que si le planning détaillé était vide. Or aucun formulaire ne l'envoie : une
 * séance créée à la main naissait sans, et l'heure du déjeuner était comptée
 * comme du temps de formation — sur la carte, sur le BPF, sur les statistiques
 * du formateur.
 *
 * Ces cas fixent la seule chose qu'on s'autorise : COUPER la plage annoncée là
 * où l'organisme situe sa pause, et seulement si elle l'enjambe. Jamais
 * inventer un horaire, jamais déborder des bornes de la séance.
 */
define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/HalfDaySplit.php';

use ACDC\Support\HalfDaySplit as H;

$echecs = array();
function verifie( $titre, $attendu, $obtenu ) {
    global $echecs;
    $ok = ( $attendu === $obtenu );
    printf( "  %-58s %s\n", $titre, $ok ? 'oui' : '>>> NON — ' . var_export( $obtenu, true ) );
    if ( ! $ok ) { $echecs[] = $titre; }
}

/* LE CAS DU 16 AOÛT — la journée de David, 09h00 → 17h00. */
$j = H::decouper( '2026-05-11 09:00:00', '2026-05-11 17:00:00' );
verifie( 'journée 9h-17h : deux demi-journées', 2, count( $j ) );
verifie( '  matin borné par la pause', '2026-05-11 12:30:00', $j[0]['end_at'] );
verifie( '  après-midi reprend après la pause', '2026-05-11 13:30:00', $j[1]['start_at'] );
verifie( '  la pause n’est pas du temps de formation', 420, H::minutes( $j ) );
verifie( '  soit 7 h, et non 8', 7.0, round( H::minutes( $j ) / 60, 1 ) );

/* Deux journées : 14 h, le chiffre des contrats de David. */
$deux = array_merge(
    H::decouper( '2026-05-11 09:00:00', '2026-05-11 17:00:00' ),
    H::decouper( '2026-05-12 09:00:00', '2026-05-12 17:00:00' )
);
verifie( 'deux journées : quatre demi-journées', 4, count( $deux ) );
verifie( 'deux journées : 14 h, pas 16', 840, H::minutes( $deux ) );

/* ON NE DÉCOUPE PAS CE QUI N'ENJAMBE PAS LA PAUSE. */
$matin = H::decouper( '2026-05-11 09:00:00', '2026-05-11 12:00:00' );
verifie( 'matinée seule : une demi-journée', 1, count( $matin ) );
verifie( '  et elle est reconnue comme le matin', 'am', $matin[0]['half'] );
verifie( '  ses 3 h sont intactes', 180, H::minutes( $matin ) );

$aprem = H::decouper( '2026-05-11 14:00:00', '2026-05-11 17:00:00' );
verifie( 'après-midi seul : une demi-journée', 1, count( $aprem ) );
verifie( '  reconnu comme l’après-midi', 'pm', $aprem[0]['half'] );

/* LES BORNES SONT CELLES DE LA SÉANCE, PAS DES HORAIRES PAR DÉFAUT. */
$tot = H::decouper( '2026-05-11 08:30:00', '2026-05-11 16:00:00' );
verifie( 'séance à 8h30 : le matin commence à 8h30', '2026-05-11 08:30:00', $tot[0]['start_at'] );
verifie( 'séance finissant à 16h : l’après-midi finit à 16h', '2026-05-11 16:00:00', $tot[1]['end_at'] );
verifie( '  total 6 h 30', 390, H::minutes( $tot ) );

/* UNE PAUSE PROPRE À L'ORGANISME. */
$autre = H::decouper( '2026-05-11 09:00:00', '2026-05-11 17:00:00', '12:00', '14:00' );
verifie( 'pause 12h-14h : 6 h dispensées', 360, H::minutes( $autre ) );

/* CE QU'ON REFUSE DE DÉCOUPER. */
verifie( 'plage à cheval sur deux jours : non découpée', 1, count( H::decouper( '2026-05-11 22:00:00', '2026-05-12 02:00:00' ) ) );
verifie( 'plage entièrement dans la pause : non découpée', 1, count( H::decouper( '2026-05-11 12:45:00', '2026-05-11 13:15:00' ) ) );
verifie( 'fin avant début : rien', 0, count( H::decouper( '2026-05-11 17:00:00', '2026-05-11 09:00:00' ) ) );
verifie( 'dates absentes : rien', 0, count( H::decouper( '', '' ) ) );
verifie( 'pause mal formée : plage rendue telle quelle', 1, count( H::decouper( '2026-05-11 09:00:00', '2026-05-11 17:00:00', 'midi', '14h' ) ) );

/* L'HEURE NE SE DÉCALE PAS — la leçon du 16 août. */
$ancien = date_default_timezone_get();
date_default_timezone_set( 'Europe/Paris' );
$paris = H::decouper( '2026-05-11 09:00:00', '2026-05-11 17:00:00' );
date_default_timezone_set( 'UTC' );
$utc = H::decouper( '2026-05-11 09:00:00', '2026-05-11 17:00:00' );
date_default_timezone_set( $ancien );
verifie( 'même découpe quel que soit le fuseau du serveur', $utc, $paris );

echo "\n";
if ( $echecs ) {
    printf( "%d cas en échec : %s\n", count( $echecs ), implode( ' | ', $echecs ) );
    exit( 1 );
}
echo "Demi-journées : la pause n’est jamais comptée, les bornes restent celles de la séance.\n";
exit( 0 );
