<?php
/**
 * Ce qu'annonce une convocation : les dates, les horaires, le lieu.
 *
 * Trois codes écrivaient la convocation, et ils avaient divergé. Celui qui en
 * envoie le plus — le moteur, la veille à 17 h — ne lisait que la colonne
 * `location` de la séance. Elle est vide la plupart du temps : l'apprenant
 * recevait « Lieu / format : — » alors que la convention porte l'adresse. Il
 * n'annonçait aucun horaire, et donnait la seule date de début pour une
 * formation de deux jours.
 *
 * Les règles retenues, testées ici :
 *   — le lieu se lit sur la séance, puis la CONVENTION, puis la fiche
 *     formation ; « Distanciel » seulement si aucune adresse n'existe nulle
 *     part — une formation en salle avec un lien de secours reste en salle ;
 *   — les horaires viennent du déroulé de la séance, puis de celui de la
 *     convention ; jamais inventés ;
 *   — rien nulle part se dit « À préciser », pas « — » : un tiret ne dit rien
 *     à quelqu'un qui doit se déplacer.
 */

function lieu_convocation( $seance, $convention = null, $formation = null ) {
    $compose = function ( $adresse, $cp = '', $ville = '' ) {
        $adresse = trim( (string) $adresse );
        if ( '' === $adresse ) { return ''; }
        $suite = trim( trim( (string) $cp ) . ' ' . trim( (string) $ville ) );
        if ( '' !== $suite && false === stripos( $adresse, $suite ) ) {
            return $adresse . ', ' . $suite;
        }
        return $adresse;
    };
    foreach ( array(
        array( $seance,     'location',          'postal_code',            'city' ),
        array( $convention, 'formation_address', 'formation_postal_code',  'formation_city' ),
        array( $formation,  'address',           'postal_code',            'city' ),
    ) as $source ) {
        list( $row, $k_addr, $k_cp, $k_city ) = $source;
        if ( ! $row ) { continue; }
        $label = $compose( $row[ $k_addr ] ?? '', $row[ $k_cp ] ?? '', $row[ $k_city ] ?? '' );
        if ( '' !== $label ) { return $label; }
    }
    $distanciel = ! empty( $seance['remote_link'] ) || ( $convention && ! empty( $convention['remote_link'] ) );
    return $distanciel ? 'Distanciel' : 'À préciser';
}

function horaires_convocation( $creneaux_seance, $convention_json = null ) {
    if ( ! empty( $creneaux_seance ) ) {
        return implode( ' et ', $creneaux_seance );
    }
    if ( is_array( $convention_json ) ) {
        foreach ( $convention_json as $jour ) {
            $am = ( ! empty( $jour['am_start'] ) && ! empty( $jour['am_end'] ) )
                ? str_replace( ':', 'h', $jour['am_start'] ) . '–' . str_replace( ':', 'h', $jour['am_end'] ) : '';
            $pm = ( ! empty( $jour['pm_start'] ) && ! empty( $jour['pm_end'] ) )
                ? str_replace( ':', 'h', $jour['pm_start'] ) . '–' . str_replace( ':', 'h', $jour['pm_end'] ) : '';
            $parts = array_filter( array( $am, $pm ) );
            if ( $parts ) { return implode( ' et ', $parts ); }
        }
    }
    return 'À préciser';
}

/** Reproduit acdc_convocation_dates_label() sur des libellés déjà formatés. */
function dates_convocation( $debut, $fin = '' ) {
    if ( '' === $debut && '' === $fin ) { return 'À préciser'; }
    if ( '' === $fin || $fin === $debut ) { return $debut ?: $fin; }
    if ( '' === $debut ) { return $fin; }
    return 'Du ' . lcfirst( $debut ) . ' au ' . lcfirst( $fin );
}

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s :\n       attendu « %s »\n       obtenu  « %s »\n", $label, $attendu, $obtenu );
    }
};

$convention = array(
    'formation_address'     => '512 chemin des Négadoux',
    'formation_postal_code' => '83140',
    'formation_city'        => 'Six-Fours-les-Plages',
);

/* --- Le lieu --- */
$t( 'la séance passe avant la convention',
    lieu_convocation( array( 'location' => 'Salle des fêtes, Cogolin' ), $convention ),
    'Salle des fêtes, Cogolin' );

/* LE CAS DE DAVID : séance sans lieu, convention renseignée. Avant, « — ». */
$t( 'séance vide : la convention prend le relais',
    lieu_convocation( array(), $convention ),
    '512 chemin des Négadoux, 83140 Six-Fours-les-Plages' );

$t( 'puis la fiche formation',
    lieu_convocation( array(), null, array( 'address' => '7 avenue Paul Cézanne', 'postal_code' => '83310', 'city' => 'Cogolin' ) ),
    '7 avenue Paul Cézanne, 83310 Cogolin' );

$t( 'le code postal déjà présent n’est pas répété',
    lieu_convocation( array(), array( 'formation_address' => '512 chemin des Négadoux, 83140 Six-Fours', 'formation_postal_code' => '83140', 'formation_city' => 'Six-Fours' ) ),
    '512 chemin des Négadoux, 83140 Six-Fours' );

/* Une salle ET un lien de visio de secours : c'est une formation en salle. */
$t( 'une adresse l’emporte sur le lien de visio',
    lieu_convocation( array( 'location' => 'Salle 2', 'remote_link' => 'https://teams…' ), $convention ),
    'Salle 2' );

$t( 'distanciel quand il n’y a aucune adresse',
    lieu_convocation( array( 'remote_link' => 'https://teams…' ) ),
    'Distanciel' );

$t( 'rien nulle part : « À préciser », pas un tiret',
    lieu_convocation( array() ),
    'À préciser' );

/* --- Les horaires --- */
$t( 'le déroulé de la séance fait foi',
    horaires_convocation( array( '09h00–12h30', '13h30–17h00' ) ),
    '09h00–12h30 et 13h30–17h00' );

$t( 'à défaut, le déroulé de la convention',
    horaires_convocation( array(), array( '2026-08-14' => array( 'am_start' => '09:00', 'am_end' => '12:30', 'pm_start' => '13:30', 'pm_end' => '17:00' ) ) ),
    '09h00–12h30 et 13h30–17h00' );

$t( 'une matinée seule reste une matinée',
    horaires_convocation( array(), array( '2026-08-14' => array( 'am_start' => '09:00', 'am_end' => '12:30' ) ) ),
    '09h00–12h30' );

$t( 'aucun horaire n’est inventé',
    horaires_convocation( array(), array() ),
    'À préciser' );

/* --- Les dates --- */
$t( 'deux jours : du … au …',
    dates_convocation( 'Vendredi 14 août 2026', 'Samedi 15 août 2026' ),
    'Du vendredi 14 août 2026 au samedi 15 août 2026' );

$t( 'une seule journée ne se dédouble pas',
    dates_convocation( 'Vendredi 14 août 2026', 'Vendredi 14 août 2026' ),
    'Vendredi 14 août 2026' );

$t( 'sans date de fin, la date de début suffit',
    dates_convocation( 'Vendredi 14 août 2026' ),
    'Vendredi 14 août 2026' );

$t( 'sans aucune date : « À préciser »',
    dates_convocation( '', '' ),
    'À préciser' );

printf( "15 contrôles, %d échec(s)\n", $ko );
exit( 0 === $ko ? 0 : 1 );
