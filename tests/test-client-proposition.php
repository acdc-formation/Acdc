<?php
/**
 * Le client d'une proposition commerciale : qui le renseigne, et dans quel ordre.
 *
 * Le défaut corrigé en 3.25.250 : le repli cherchait l'entreprise sur
 * « prospect.company_id », une colonne qui n'existe pas sur la table des
 * prospects. Le test était donc toujours faux, sans erreur ni trace, et la
 * proposition partait sans raison sociale, sans SIRET et sans adresse — c'est
 * ce que David a constaté sur le document généré.
 *
 * La règle retenue : ce qui est écrit sur le dossier le plus proche du client
 * gagne. Le recueil rattaché à une fiche entreprise fait foi ; à défaut on lit
 * le prospect lui-même ; la fiche entreprise retrouvée par SIRET (puis par
 * raison sociale exacte) ne fait que compléter ce qui manque encore.
 *
 * Aucun rapprochement approximatif : mettre la mauvaise société sur une
 * proposition, c'est adresser un devis au nom d'un tiers.
 */

/** Reproduit la cascade de build_proposal_from_need() + acdc_find_company_for_prospect(). */
function resoudre_client( array $ctx ) {
    $data = array(
        'client_company' => '', 'client_siret' => '', 'client_address' => '',
        'client_postal_code' => '', 'client_city' => '', 'client_website' => '',
        'source' => 'aucune',
    );
    $need     = $ctx['need'] ?? array();
    $prospect = $ctx['prospect'] ?? null;
    $annuaire = $ctx['entreprises'] ?? array();   // liste de fiches entreprise

    /* 1. L'entreprise rattachée au recueil. */
    if ( ! empty( $need['company_id'] ) ) {
        foreach ( $annuaire as $c ) {
            if ( (int) $c['id'] === (int) $need['company_id'] ) {
                $data['client_company']     = $c['name'];
                $data['client_siret']       = $c['siret'] ?? '';
                $data['client_address']     = $c['address'] ?? '';
                $data['client_postal_code'] = $c['postal_code'] ?? '';
                $data['client_city']        = $c['city'] ?? '';
                $data['client_website']     = $c['website'] ?? '';
                $data['source']             = 'recueil';
                return $data;
            }
        }
    }

    /* 2. Le prospect lui-même. */
    if ( '' === $data['client_company'] && $prospect ) {
        if ( ! empty( $prospect['company_name'] ) ) { $data['client_company']     = $prospect['company_name']; }
        if ( ! empty( $prospect['siret'] ) )        { $data['client_siret']       = $prospect['siret']; }
        if ( ! empty( $prospect['address'] ) )      { $data['client_address']     = $prospect['address']; }
        if ( ! empty( $prospect['postal_code'] ) )  { $data['client_postal_code'] = $prospect['postal_code']; }
        if ( ! empty( $prospect['city'] ) )         { $data['client_city']        = $prospect['city']; }
        if ( '' !== $data['client_company'] ) { $data['source'] = 'prospect'; }

        /* 3. La fiche entreprise retrouvée : SIRET d'abord, raison sociale ensuite. */
        $trouvee = null;
        if ( ! empty( $prospect['siret'] ) ) {
            foreach ( $annuaire as $c ) {
                if ( ! empty( $c['siret'] ) && $c['siret'] === $prospect['siret'] ) { $trouvee = $c; break; }
            }
        }
        if ( ! $trouvee && ! empty( $prospect['company_name'] ) ) {
            foreach ( $annuaire as $c ) {
                if ( $c['name'] === $prospect['company_name'] ) { $trouvee = $c; break; }
            }
        }
        if ( $trouvee ) {
            foreach ( array( 'company' => 'name', 'siret' => 'siret', 'address' => 'address',
                             'postal_code' => 'postal_code', 'city' => 'city', 'website' => 'website' ) as $k => $src ) {
                if ( '' === $data[ 'client_' . $k ] && ! empty( $trouvee[ $src ] ) ) {
                    $data[ 'client_' . $k ] = $trouvee[ $src ];
                }
            }
            if ( 'aucune' === $data['source'] ) { $data['source'] = 'entreprise'; }
        }
    }
    return $data;
}

$ko = 0;
$t  = function ( $label, $ctx, array $attendu ) use ( &$ko ) {
    $r = resoudre_client( $ctx );
    foreach ( $attendu as $k => $v ) {
        if ( (string) $r[ $k ] !== (string) $v ) {
            $ko++;
            printf( "ÉCHEC  %s : %s attendu « %s », obtenu « %s »\n", $label, $k, $v, $r[ $k ] );
        }
    }
};

$skill = array(
    'id' => 7, 'name' => 'SKILL CONSEILS', 'siret' => '82345678900019',
    'address' => '512 chemin des Négadoux', 'postal_code' => '83140', 'city' => 'Six-Fours-les-Plages',
    'website' => 'https://skill-conseils.fr',
);

/* Le cas nominal : le recueil porte l'entreprise. */
$t( 'recueil rattaché à une entreprise',
    array( 'need' => array( 'company_id' => 7 ), 'entreprises' => array( $skill ) ),
    array( 'client_company' => 'SKILL CONSEILS', 'client_siret' => '82345678900019', 'source' => 'recueil' ) );

/* LE DÉFAUT CORRIGÉ : pas de fiche entreprise du tout, seulement un prospect.
   L'ancien code ne remplissait rien ; la raison sociale, le SIRET et l'adresse
   sont sur le prospect et doivent suffire. */
$t( 'prospect seul, aucune fiche entreprise',
    array( 'need' => array(), 'prospect' => array(
        'company_name' => 'SKILL CONSEILS', 'siret' => '82345678900019',
        'address' => '512 chemin des Négadoux', 'postal_code' => '83140', 'city' => 'Six-Fours-les-Plages',
    ), 'entreprises' => array() ),
    array( 'client_company' => 'SKILL CONSEILS', 'client_siret' => '82345678900019',
           'client_address' => '512 chemin des Négadoux', 'client_postal_code' => '83140',
           'client_city' => 'Six-Fours-les-Plages', 'source' => 'prospect' ) );

/* La fiche entreprise complète ce qui manque — ici le site web — sans écraser. */
$t( 'la fiche entreprise complète le prospect',
    array( 'need' => array(), 'prospect' => array(
        'company_name' => 'SKILL CONSEILS', 'siret' => '82345678900019', 'address' => 'Adresse du prospect',
    ), 'entreprises' => array( $skill ) ),
    array( 'client_address' => 'Adresse du prospect', 'client_website' => 'https://skill-conseils.fr',
           'client_postal_code' => '83140' ) );

/* Le rapprochement se fait par SIRET même si la raison sociale a été retapée. */
$t( 'rapprochement par SIRET malgré un nom différent',
    array( 'need' => array(), 'prospect' => array(
        'company_name' => 'Skill Conseils SARL', 'siret' => '82345678900019',
    ), 'entreprises' => array( $skill ) ),
    array( 'client_company' => 'Skill Conseils SARL', 'client_city' => 'Six-Fours-les-Plages' ) );

/* Un nom approchant sans SIRET commun ne rapproche RIEN. */
$t( 'aucun rapprochement approximatif',
    array( 'need' => array(), 'prospect' => array( 'company_name' => 'SKILL CONSEIL' ),
           'entreprises' => array( $skill ) ),
    array( 'client_company' => 'SKILL CONSEIL', 'client_city' => '', 'client_siret' => '' ) );

/* Un particulier n'a pas de raison sociale : rien ne doit être inventé. */
$t( 'prospect particulier',
    array( 'need' => array(), 'prospect' => array( 'address' => '3 rue des Lilas', 'city' => 'Toulon' ),
           'entreprises' => array( $skill ) ),
    array( 'client_company' => '', 'client_siret' => '', 'client_city' => 'Toulon', 'source' => 'aucune' ) );

/* Le recueil rattaché l'emporte sur le prospect, même si les deux sont renseignés. */
$t( 'le recueil bat le prospect',
    array( 'need' => array( 'company_id' => 7 ),
           'prospect' => array( 'company_name' => 'AUTRE SOCIÉTÉ', 'siret' => '11111111100011' ),
           'entreprises' => array( $skill ) ),
    array( 'client_company' => 'SKILL CONSEILS', 'source' => 'recueil' ) );

printf( "7 cas, %d échec(s)\n", $ko );
exit( 0 === $ko ? 0 : 1 );
