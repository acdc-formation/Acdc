<?php
function mb_strpos_c($h,$n){return mb_strpos($h,$n);}
function acdc_person_part_of_label( $label ) {
    $label = is_scalar( $label ) ? trim( (string) $label ) : '';
    if ( '' === $label ) { return ''; }
    if ( false === mb_strpos( $label, 'à l’attention de' ) && false === mb_strpos( $label, "à l'attention de" ) ) { return $label; }
    $split = preg_split( '/\s*—?\s*à l[’\']attention de\s*/u', $label, 2 );
    if ( is_array( $split ) && 2 === count( $split ) && '' !== trim( (string) $split[1] ) ) { return trim( (string) $split[1] ); }
    return $label;
}
$cas = array(
  array('Skill Conseil — à l’attention de Bérengère Valeriano', 'Bérengère Valeriano', 'libellé pollué, tiret cadratin'),
  array("Skill Conseil — à l'attention de Bérengère Valeriano", 'Bérengère Valeriano', 'apostrophe droite'),
  array('Bérengère Valeriano', 'Bérengère Valeriano', 'nom propre déjà correct'),
  array('Skill Conseil', 'Skill Conseil', 'raison sociale seule, sans personne'),
  array('', '', 'vide'),
  array('Skill Conseil — à l’attention de ', 'Skill Conseil — à l’attention de', 'personne absente : on ne perd pas la chaîne'),
  array('Jean-Pierre De La Tour', 'Jean-Pierre De La Tour', 'nom composé'),
);
$ko=0;
foreach($cas as $c){ $got=acdc_person_part_of_label($c[0]);
  if($got!==$c[1]){$ko++; printf("ÉCHEC  \"%s\" attendu \"%s\" obtenu \"%s\" (%s)\n",$c[0],$c[1],$got,$c[2]);} }
printf("%d cas, %d échec(s)\n",count($cas),$ko); exit($ko===0?0:1);
