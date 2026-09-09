<?php
/**
 * Le préremplissage d'un devis interroge trois sources dans l'ordre où elles
 * font autorité : la proposition (pièce commerciale la plus récente), puis la
 * société, puis le prospect. Il s'arrête à la première qui répond.
 *
 * Le défaut corrigé en 3.25.243 n'était pas une donnée manquante : c'était
 * l'absence de deuxième regard. La proposition ne portait ni code postal, ni
 * ville, ni SIRET, et le préremplissage renonçait — alors que la fiche prospect
 * les connaissait.
 */
$first_filled = static function ( ...$values ) {
    foreach ( $values as $value ) {
        $value = is_scalar($value) ? trim((string)$value) : '';
        if ( '' !== $value ) { return $value; }
    }
    return '';
};

$ko = 0;
$check = function( $label, $got, $expected ) use ( &$ko ) {
    if ( $got !== $expected ) { $ko++; printf("ÉCHEC  %s : attendu \"%s\", obtenu \"%s\"\n", $label, $expected, $got); }
};

/* Le cas réel de David : proposition muette, prospect renseigné. */
$check('CP repris du prospect',    $first_filled('', '', '83140'), '83140');
$check('ville reprise du prospect',$first_filled('', '', 'Six-Fours-les-Plages'), 'Six-Fours-les-Plages');

/* La proposition prime quand elle sait. */
$check('la proposition prime',     $first_filled('83000', '83140', '83999'), '83000');

/* La société vient avant le prospect. */
$check('la société avant le prospect', $first_filled('', '83140', '83999'), '83140');

/* Aucune source : le champ reste vide, il n'invente rien. */
$check('rien nulle part',          $first_filled('', '', ''), '');

/* Les espaces seuls ne comptent pas pour une valeur. */
$check('espaces ignorés',          $first_filled('   ', '83140', ''), '83140');

/* Une valeur non scalaire (désérialisation ancienne) ne casse pas la cascade. */
$check('valeur non scalaire',      $first_filled(array('x'), '83140'), '83140');

/* Le complément n'a qu'une source : la société. Sans elle, il reste vide. */
$check('complément sans société',  $first_filled(''), '');

printf("8 contrôles, %d échec(s)\n", $ko);
exit($ko === 0 ? 0 : 1);
