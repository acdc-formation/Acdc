<?php
/**
 * Le déroulé saisi sur la convention finit sur une pièce contractuelle et dans
 * les feuilles d'émargement. Trois garde-fous sont testés ici : on ne garde que
 * les journées retenues, un horaire illisible retombe sur l'horaire type, et
 * une fin antérieure au début est refusée (elle donnerait une demi-journée de
 * durée négative, et fausserait l'assiduité).
 */
$FALLBACK = array('format'=>'Présentiel','am_start'=>'09:00','am_end'=>'12:30','pm_start'=>'13:30','pm_end'=>'17:00');

function sanitize_schedule( $raw, $dates, $fallback ) {
    $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
    if ( ! is_array($decoded) ) { $decoded = array(); }
    $time = static function( $value, $default ) {
        $value = is_scalar($value) ? trim((string)$value) : '';
        return preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value) ? $value : $default;
    };
    $out = array();
    foreach ( (array)$dates as $date ) {
        $date = trim((string)$date);
        if ( ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ) { continue; }
        $day = isset($decoded[$date]) && is_array($decoded[$date]) ? $decoded[$date] : array();
        $format = isset($day['format']) && 'Distanciel' === (string)$day['format'] ? 'Distanciel' : 'Présentiel';
        $am_s = $time($day['am_start'] ?? '', $fallback['am_start']);
        $am_e = $time($day['am_end']   ?? '', $fallback['am_end']);
        $pm_s = $time($day['pm_start'] ?? '', $fallback['pm_start']);
        $pm_e = $time($day['pm_end']   ?? '', $fallback['pm_end']);
        if ( $am_e <= $am_s ) { $am_s = $fallback['am_start']; $am_e = $fallback['am_end']; }
        if ( $pm_e <= $pm_s ) { $pm_s = $fallback['pm_start']; $pm_e = $fallback['pm_end']; }
        $out[$date] = array('format'=>$format,'am_start'=>$am_s,'am_end'=>$am_e,'pm_start'=>$pm_s,'pm_end'=>$pm_e);
    }
    return $out;
}

$ko = 0;
$check = function( $label, $got, $expected ) use ( &$ko ) {
    if ( $got !== $expected ) {
        $ko++;
        echo "ÉCHEC  $label\n       attendu " . json_encode($expected, JSON_UNESCAPED_UNICODE)
           . "\n       obtenu  " . json_encode($got, JSON_UNESCAPED_UNICODE) . "\n";
    }
};

/* 1. Une journée retirée ne laisse pas son horaire derrière elle. */
$r = sanitize_schedule(
  json_encode(array(
    '2026-08-13' => array('format'=>'Distanciel','am_start'=>'10:00','am_end'=>'12:00','pm_start'=>'14:00','pm_end'=>'16:00'),
    '2026-08-14' => array('format'=>'Présentiel','am_start'=>'09:00','am_end'=>'12:30','pm_start'=>'13:30','pm_end'=>'17:00'),
  )),
  array('2026-08-13'), $FALLBACK );
$check('journée retirée', array_keys($r), array('2026-08-13'));
$check('format conservé', $r['2026-08-13']['format'], 'Distanciel');
$check('horaires conservés', $r['2026-08-13']['am_start'], '10:00');

/* 2. Un horaire illisible retombe sur l'horaire type. */
$r = sanitize_schedule(
  json_encode(array('2026-08-13'=>array('am_start'=>'nawak','am_end'=>'12:30','pm_start'=>'13:30','pm_end'=>'17:00'))),
  array('2026-08-13'), $FALLBACK );
$check('horaire illisible', $r['2026-08-13']['am_start'], '09:00');

/* 3. Une fin avant le début est refusée. */
$r = sanitize_schedule(
  json_encode(array('2026-08-13'=>array('am_start'=>'12:00','am_end'=>'09:00','pm_start'=>'13:30','pm_end'=>'17:00'))),
  array('2026-08-13'), $FALLBACK );
$check('fin avant début', array($r['2026-08-13']['am_start'],$r['2026-08-13']['am_end']), array('09:00','12:30'));

/* 4. Une convention sans déroulé enregistré prend les horaires type — l'ancien comportement. */
$r = sanitize_schedule('', array('2026-08-13'), $FALLBACK);
$check('aucun déroulé', $r['2026-08-13'], $FALLBACK);

/* 5. Une date invalide est écartée. */
$r = sanitize_schedule('{}', array('pas-une-date','2026-08-13'), $FALLBACK);
$check('date invalide écartée', array_keys($r), array('2026-08-13'));

/* 6. Les horaires type modifiés dans les paramètres sont bien ceux appliqués. */
$autre = array('format'=>'Présentiel','am_start'=>'08:30','am_end'=>'12:00','pm_start'=>'13:00','pm_end'=>'16:30');
$r = sanitize_schedule('{}', array('2026-08-13'), $autre);
$check('horaires type réglés', $r['2026-08-13'], $autre);

printf("6 contrôles, %d échec(s)\n", $ko);
exit($ko === 0 ? 0 : 1);
