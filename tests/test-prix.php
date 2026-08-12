<?php
function wp_strip_all_tags($s){return strip_tags((string)$s);}
function normalize_price_number( $value, $decimals = 2 ) {
    $value = is_scalar( $value ) ? (string) $value : '';
    $value = html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' );
    $value = str_replace( array( '€', ' ', "\xc2\xa0" ), '', $value );
    $value = preg_replace( '/[^0-9,.-]/u', '', $value );
    $negative = ( 0 === strpos( $value, '-' ) );
    $value    = str_replace( '-', '', $value );
    $last_comma = strrpos( $value, ',' );
    $last_dot   = strrpos( $value, '.' );
    if ( false === $last_comma && false === $last_dot ) {
      $number = is_numeric( $value ) ? (float) $value : 0.0;
      return number_format( $negative ? -$number : $number, (int) $decimals, ',', '' );
    }
    $sep_pos  = max( false === $last_comma ? -1 : $last_comma, false === $last_dot ? -1 : $last_dot );
    $fraction = substr( $value, $sep_pos + 1 );
    $only_one_kind = ( false === $last_comma || false === $last_dot );
    if ( $only_one_kind && 3 === strlen( $fraction ) && ctype_digit( $fraction ) && 1 === substr_count( $value, $value[ $sep_pos ] ) ) {
      $number = (float) preg_replace( '/[^0-9]/', '', $value );
      return number_format( $negative ? -$number : $number, (int) $decimals, ',', '' );
    }
    $integer = preg_replace( '/[^0-9]/', '', substr( $value, 0, $sep_pos ) );
    $number  = (float) ( ( '' === $integer ? '0' : $integer ) . '.' . preg_replace( '/[^0-9]/', '', $fraction ) );
    return number_format( $negative ? -$number : $number, (int) $decimals, ',', '' );
}
/* Cas réels : saisies d'écran, valeurs de base (DECIMAL), montants collés. */
$cas = array(
  array('1800',        '1800,00', 'entier simple'),
  array('1800.00',     '1800,00', 'décimal de la base MySQL'),
  array('1800,00',     '1800,00', 'saisie française'),
  array('1 800,00',    '1800,00', 'saisie avec espace'),
  array("1\xc2\xa0800,00", '1800,00', 'espace insécable'),
  array('1.800,00',    '1800,00', 'milliers à la française'),
  array('1,800.00',    '1800,00', 'milliers à l’anglaise'),
  array('1800.5',      '1800,50', 'un seul décimal'),
  array('1800,5',      '1800,50', 'un seul décimal, virgule'),
  array('1800 €',      '1800,00', 'avec le symbole'),
  array('1 800,00 € HT','1800,00','avec mention HT'),
  array('0',           '0,00',    'zéro'),
  array('',            '0,00',    'vide'),
  array('-250,50',     '-250,50', 'négatif'),
  array('12500.75',    '12500,75','montant à cinq chiffres depuis la base'),
  array('950.00',      '950,00',  'trois chiffres avant le point'),
  array('1.800',       '1800,00', 'milliers ambigu — lecture française'),
  array('0.5',         '0,50',    'moins d’un euro'),
  array('.5',          '0,50',    'sans partie entière'),
  array('2400',        '2400,00', 'tarif catalogue typique'),
);
$ko=0;
foreach($cas as $c){
  $got = normalize_price_number($c[0]);
  $ok  = ($got === $c[1]);
  if(!$ok){$ko++; printf("ÉCHEC  %-16s attendu %-10s obtenu %-10s  (%s)\n", '"'.$c[0].'"', $c[1], $got, $c[2]);}
}
printf("%d cas, %d échec(s)\n", count($cas), $ko);
exit($ko===0?0:1);
