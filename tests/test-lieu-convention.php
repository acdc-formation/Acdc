<?php
/**
 * Le lieu d'une convention. La règle qui compte : le lieu SAISI l'emporte
 * toujours. Dès que quelqu'un l'a écrit et relu, aucune déduction ne doit le
 * contredire — c'est la pièce qui engage sur le lieu.
 *
 * Les sources de secours viennent ensuite, dans l'ordre où elles font foi :
 * devis signé, proposition, séance, fiche formation, puis adresse du client.
 * Cette dernière est un pari, assumé par David, et tenable seulement parce que
 * le lieu est désormais visible et corrigeable avant signature.
 */
function resolve_location( array $sources ) {
    foreach ( array('saisi','devis','proposition','seance','formation','societe','prospect') as $key ) {
        if ( ! empty($sources[$key]) && '' !== trim((string)$sources[$key]['address']) ) {
            return array(
                'address'     => trim((string)$sources[$key]['address']),
                'postal_code' => trim((string)($sources[$key]['postal_code'] ?? '')),
                'city'        => trim((string)($sources[$key]['city'] ?? '')),
                'source'      => $key,
            );
        }
    }
    return array('address'=>'','postal_code'=>'','city'=>'','source'=>'aucune');
}

$A = function($a,$cp='',$v=''){ return array('address'=>$a,'postal_code'=>$cp,'city'=>$v); };
$ko = 0;
$t = function( $label, $sources, $attendu ) use ( &$ko ) {
    $r = resolve_location($sources);
    if ( $r['source'] !== $attendu ) {
        $ko++; printf("ÉCHEC  %s : source attendue « %s », obtenue « %s »\n", $label, $attendu, $r['source']);
    }
};

/* Le lieu saisi bat toutes les autres sources, même le devis signé. */
$t('le saisi bat le devis', array(
  'saisi' => $A('Salle des fêtes, Cogolin','83310','Cogolin'),
  'devis' => $A('512 Chem. des Négadoux','83140','Six-Fours'),
), 'saisi');

/* Sans saisie, le devis passe avant la proposition. */
$t('le devis avant la proposition', array(
  'devis'       => $A('512 Chem. des Négadoux','83140','Six-Fours'),
  'proposition' => $A('Ailleurs'),
), 'devis');

/* Puis la proposition avant la séance. */
$t('la proposition avant la séance', array(
  'proposition' => $A('512 Chem. des Négadoux'),
  'seance'      => $A('Ailleurs'),
), 'proposition');

/* Le cas de David : rien nulle part sauf la fiche prospect. */
$t('repli sur le prospect', array(
  'prospect' => $A('512 Chem. des Négadoux','83140','Six-Fours-les-Plages'),
), 'prospect');

/* La société passe avant le prospect quand elle a une adresse. */
$t('la société avant le prospect', array(
  'societe'  => $A('1 rue du Siège','83000','Toulon'),
  'prospect' => $A('512 Chem. des Négadoux','83140','Six-Fours'),
), 'societe');

/* Aucune source : le champ reste vide. On n'invente pas « À définir » en base ;
   c'est l'affichage qui le dira, et l'utilisateur peut le remplir. */
$t('aucune source', array(), 'aucune');

/* Une adresse composée uniquement d'espaces ne vaut pas une adresse. */
$t('adresse vide ignorée', array(
  'devis'    => $A('   '),
  'prospect' => $A('512 Chem. des Négadoux'),
), 'prospect');

printf("7 contrôles, %d échec(s)\n", $ko);
exit($ko === 0 ? 0 : 1);
