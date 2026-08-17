<?php
/**
 * Une convention validée sans financeur a rendu muette toute une chaîne.
 *
 * Le 16 août : financeur oublié sur la convention alors qu'il figurait au
 * recueil ET sur la proposition. Rien ne l'a signalé. Trois mois plus tard,
 * l'enquête financeur n'avait personne à qui s'adresser — et se déclarait
 * envoyée. Le dossier Qualiopi était incomplet, et le tableau de bord serein.
 *
 * Ces cas fixent ce qui bloque, ce qui signale, et la seule règle
 * conditionnelle : le financeur n'est exigé que si le dossier annonce un
 * financement externe. Bloquer une convention payée par l'entreprise
 * elle-même serait le genre de faux barrage qui apprend à passer outre.
 */
define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/ConventionCompleteness.php';

use ACDC\Support\ConventionCompleteness as CC;

$complete = array(
    'formation_id' => 12, 'commanditaire' => 5, 'start_date' => '2026-05-11',
    'end_date' => '2026-05-12', 'formation_address' => '512 Chemin des Negadoux',
    'formation_city' => 'Six-Fours', 'price_ht' => '2400',
    'vat_rate' => '20', 'trainer_id' => 3, 'learner_ids' => '7,8',
    'public_funding' => 'Non', 'objectives_text' => 'x',
    'seances_schedule_json' => '[]', 'accessibility_handicap' => 'oui',
    'implementation_followup_evaluation' => 'QCM', 'payment_terms' => '30j',
);

$echecs = array();
function verifie( $titre, $attendu, $obtenu ) {
    global $echecs;
    $ok = ( $attendu === $obtenu );
    printf( "  %-56s %s\n", $titre, $ok ? 'oui' : '>>> NON — ' . var_export( $obtenu, true ) );
    if ( ! $ok ) { $echecs[] = $titre; }
}

/* 1. Une convention complète passe. */
$r = CC::verifier( $complete );
verifie( 'convention complète : rien ne bloque', true, $r['ok'] );
verifie( 'convention complète : rien à signaler', 0, count( $r['signales'] ) );

/* 2. LE CAS DU 16 AOÛT — financement externe annoncé, financeur absent. */
$sans = $complete;
$sans['public_funding'] = 'OPCO';
unset( $sans['funder_id'] );
$r = CC::verifier( $sans );
verifie( 'OPCO sans financeur : bloque', false, $r['ok'] );
verifie( 'et le refus NOMME le financeur', true, (bool) preg_grep( '/financeur/', $r['bloquants'] ) );

/* 3. Le même dossier, financeur renseigné : il passe. */
$sans['funder_id'] = 9;
verifie( 'OPCO avec financeur : passe', true, CC::verifier( $sans )['ok'] );

/* 4. LE FAUX BARRAGE QU'ON REFUSE — payé par l'entreprise, pas de financeur. */
foreach ( array( 'Non', 'Entreprise', 'Fonds propres', '' ) as $mode ) {
    $direct = $complete;
    $direct['public_funding'] = $mode;
    unset( $direct['funder_id'] );
    verifie( sprintf( 'financement « %s » sans financeur : passe', $mode ?: '(vide)' ), true, CC::verifier( $direct )['ok'] );
}

/* 5. Un identifiant à zéro est vide ; un tarif à zéro ne l'est pas. */
$zero = $complete;
$zero['trainer_id'] = 0;
verifie( 'formateur à 0 : compté comme absent', true, (bool) preg_grep( '/formateur/', CC::verifier( $zero )['bloquants'] ) );
$gratuit = $complete;
$gratuit['price_ht'] = '0';
verifie( 'formation gratuite : le tarif reste rempli', true, CC::verifier( $gratuit )['ok'] );

/* 6. Aucun apprenant : un tableau vide n'est pas un apprenant. */
$vide = $complete;
$vide['learner_ids'] = '0';
verifie( 'liste d’apprenants vide : bloque', false, CC::verifier( $vide )['ok'] );

/* 7. Ce qui signale ne bloque jamais. */
$partiel = $complete;
unset( $partiel['accessibility_handicap'], $partiel['payment_terms'] );
$r = CC::verifier( $partiel );
verifie( 'accessibilité et règlement manquants : n’empêchent pas', true, $r['ok'] );
verifie( 'mais sont annoncés', 2, count( $r['signales'] ) );

/* 8. LE COMMANDITAIRE N'EST PAS TOUJOURS UNE ENTREPRISE.
      La clé s'appelait « company_id » et exigeait donc un identifiant
      d'entreprise. Une convention avec un particulier ne pouvait pas passer, et
      celle qui passait par la liste visible à l'écran non plus — cette liste
      poste « source_prospect_id ». La clé accepte désormais ce qui DÉSIGNE le
      commanditaire, identifiant ou nom. */
foreach ( array( '5', '  Bérengère Valeriano  ', 'Skill Conseil' ) as $qui ) {
    $c = $complete;
    $c['commanditaire'] = $qui;
    verifie( sprintf( 'commanditaire « %s » : accepté', trim( $qui ) ), true, CC::verifier( $c )['ok'] );
}
$sans_cmd = $complete;
$sans_cmd['commanditaire'] = '';
verifie( 'commanditaire vide : bloque', false, CC::verifier( $sans_cmd )['ok'] );
verifie(
    'et le refus le NOMME',
    true,
    (bool) preg_grep( '/commanditaire/', CC::verifier( $sans_cmd )['bloquants'] )
);
verifie(
    'la clé « company_id » n’est plus réclamée',
    false,
    array_key_exists( 'company_id', CC::BLOQUANTS )
);

/* 9. Une TVA à 0 % est une TVA renseignée — un organisme exonéré existe. */
$exonere = $complete;
$exonere['vat_rate'] = 0;
verifie( 'organisme exonéré (TVA 0) : le taux reste rempli', true, CC::verifier( $exonere )['ok'] );

/* 10. Les phrases nomment, sinon elles ne servent à rien. */
$msg = CC::messageRefus( array( 'le formateur', 'le tarif', 'le lieu' ) );
verifie( 'le refus énumère en français', true, false !== strpos( $msg, 'le formateur, le tarif et le lieu' ) );
verifie( 'un seul manque : pas de « et » orphelin', 'Convention non enregistrée — il manque : le tarif.', CC::messageRefus( array( 'le tarif' ) ) );
verifie( 'rien ne manque : aucune phrase', '', CC::messageRefus( array() ) );

echo "\n";
if ( $echecs ) {
    printf( "%d cas en échec : %s\n", count( $echecs ), implode( ' | ', $echecs ) );
    exit( 1 );
}
echo "Complétude de la convention : ce qui bloque bloque, ce qui signale n’empêche rien.\n";
exit( 0 );
