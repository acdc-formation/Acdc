<?php
/**
 * Le lieu de formation arrive de la proposition en une seule ligne, alors que le
 * devis porte trois colonnes et recompose l'adresse à partir des trois. On
 * découpe donc sur le motif du courrier français : cinq chiffres suivis de la
 * ville, en fin de ligne.
 *
 * La règle la plus importante n'est pas de découper : c'est de NE PAS découper
 * quand le motif n'est pas là. Une adresse entière au mauvais endroit se relit ;
 * une adresse coupée au mauvais endroit se perd.
 */
function split_french_address( $raw ) {
    $raw = is_scalar($raw) ? trim(preg_replace('/\s+/u',' ',(string)$raw)) : '';
    $out = array('address'=>$raw,'postal_code'=>'','city'=>'');
    if ( '' === $raw ) { return $out; }
    if ( ! preg_match('/^(.*)[,\s]\s*(\d{5})\s+(.+)$/u', $raw, $m) ) { return $out; }
    $street = trim(rtrim(trim((string)$m[1]), ','));
    $city   = trim((string)$m[3]);
    if ( '' === $street || '' === $city ) { return $out; }
    return array('address'=>$street,'postal_code'=>(string)$m[2],'city'=>$city);
}

$ko = 0;
$t = function( $label, $raw, $a, $cp, $v ) use ( &$ko ) {
    $r = split_french_address($raw);
    $exp = array('address'=>$a,'postal_code'=>$cp,'city'=>$v);
    if ( $r !== $exp ) {
        $ko++;
        printf("ÉCHEC  %s\n       « %s »\n       attendu %s\n       obtenu  %s\n",
          $label, $raw, json_encode($exp,JSON_UNESCAPED_UNICODE), json_encode($r,JSON_UNESCAPED_UNICODE));
    }
};

/* Le cas de David. */
$t('cas réel', '512 Chem. des Négadoux 83140 Six-Fours-les-Plages',
   '512 Chem. des Négadoux', '83140', 'Six-Fours-les-Plages');

/* Avec virgule, l'autre écriture courante. */
$t('avec virgule', '7 avenue Paul Cézanne, 83310 Cogolin',
   '7 avenue Paul Cézanne', '83310', 'Cogolin');

/* Ville composée, tirets et espaces. */
$t('ville composée', '1 rue du Port 13600 La Ciotat',
   '1 rue du Port', '13600', 'La Ciotat');

/* CP commençant par zéro. */
$t('CP avec zéro', '3 rue Neuve 01000 Bourg-en-Bresse',
   '3 rue Neuve', '01000', 'Bourg-en-Bresse');

/* ─── Ce qu'il ne faut PAS découper ─────────────────────────────── */

/* Pas de code postal : on ne touche à rien. */
$t('sans code postal', 'Dans les locaux de l’entreprise',
   'Dans les locaux de l’entreprise', '', '');

/* Adresse étrangère : le motif ne correspond pas, on laisse. */
$t('adresse étrangère', 'Rue de la Loi 200, 1049 Bruxelles',
   'Rue de la Loi 200, 1049 Bruxelles', '', '');

/* Uniquement « CP Ville » : rien à séparer, on garde la ligne entière. */
$t('CP et ville seuls', '83140 Six-Fours-les-Plages',
   '83140 Six-Fours-les-Plages', '', '');

/* Un numéro à cinq chiffres au milieu ne doit pas être pris pour un CP
   s'il n'est pas suivi d'une ville en fin de ligne — ici il l'est, donc on
   découpe : c'est le comportement attendu, la ville est bien la fin. */
$t('nombre puis ville', 'Bâtiment 12345 83140 Toulon',
   'Bâtiment 12345', '83140', 'Toulon');

/* Vide. */
$t('vide', '', '', '', '');

/* Espaces multiples normalisés. */
$t('espaces multiples', '512   Chem.  des Négadoux   83140   Six-Fours',
   '512 Chem. des Négadoux', '83140', 'Six-Fours');

printf("10 contrôles, %d échec(s)\n", $ko);
exit($ko === 0 ? 0 : 1);
