<?php
/**
 * Un e-mail se lit d'abord sur un téléphone.
 *
 * Relevé sur l'iPhone de David, captures à l'appui : le titre passait sur deux
 * lignes, le sous-titre sur quatre, le récapitulatif à deux colonnes coupait
 * chaque valeur, et son code de vérification se scindait — « 7 6 9 7 » puis
 * « 6 0 ». Un code qu'on doit recopier ne doit jamais passer à la ligne.
 *
 * Le gabarit mesurait 860 px avec 56 px de marge de chaque côté, un titre à
 * 34 px, un corps à 19 px, et n'avait ni en-tête, ni balise viewport, ni la
 * moindre règle d'adaptation.
 *
 * Trois pièces tiennent maintenant la lisibilité mobile, et elles sont
 * fragiles pour une raison précise : LE CORPS DES MESSAGES EST ÉCRIT AILLEURS.
 * Une trentaine d'endroits du plugin composent leurs propres paragraphes, avec
 * leurs propres tailles calibrées pour un grand écran. Ce n'est pas un défaut à
 * corriger un par un — le prochain paragraphe écrit ailleurs recommencerait —
 * c'est une raison de garder le plafond au niveau du gabarit commun.
 *
 * Si l'une de ces trois pièces disparaît, tous ces paragraphes reprennent leur
 * taille d'origine et le téléphone redevient illisible, sans que rien ne le
 * signale : l'e-mail part quand même.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$gabarit = $root . '/includes/kernel/class-acdc-kernel-core-trait.php';
$otp     = $root . '/includes/signature/class-acdc-sig-email.php';
$hits    = array();

if ( ! is_readable( $gabarit ) ) {
    $hits[] = 'Le gabarit commun des e-mails est introuvable.';
} else {
    $src = (string) file_get_contents( $gabarit );
    if ( ! preg_match( '/private function acdc_build_transactional_email_html.*?\n  \}/s', $src, $m ) ) {
        $hits[] = 'acdc_build_transactional_email_html() a disparu : chaque envoi va de nouveau composer son propre habillage.';
    } else {
        $tpl = $m[0];

        if ( false === strpos( $tpl, 'name="viewport"' ) ) {
            $hits[] = 'Le gabarit n’a plus de balise viewport : iOS remet le message à l’échelle, et tout redevient énorme ou minuscule selon l’élément le plus large.';
        }
        if ( false === strpos( $tpl, 'max-width:599px' ) ) {
            $hits[] = 'Le plafond de taille pour petit écran a disparu : les paragraphes composés ailleurs (18, 19 px) reprennent leur taille de bureau sur un téléphone.';
        }
        if ( false === strpos( $tpl, 'acdc-mail-body' ) ) {
            $hits[] = 'La zone de contenu n’est plus nommée : plus rien ne peut la plafonner.';
        }
        /* Le récapitulatif empilé : deux colonnes sur 250 px de large, c'est
           un libellé de 95 px face à une valeur qui se coupe en cinq lignes. */
        if ( preg_match( '/<td[^>]*width:\s*\d+%/', $tpl ) ) {
            $hits[] = 'Le récapitulatif est redevenu une table à colonne de largeur fixe : sur un téléphone, la valeur se coupe et le libellé flotte à côté d’un bloc de cinq lignes.';
        }
        /* Les tailles écrites en ligne sont celles du MOBILE : la règle
           d'adaptation les agrandit ensuite. Une taille de bureau posée en
           ligne survivrait à une messagerie qui supprime les feuilles de
           style — et c'est exactement le cas d'iOS avec certains réglages. */
        if ( preg_match_all( '/style="[^"]*font-size:\s*(\d+)px/', $tpl, $tailles ) ) {
            foreach ( $tailles[1] as $taille ) {
                if ( (int) $taille > 24 ) {
                    $hits[] = sprintf(
                        'Le gabarit pose %d px en ligne : sur un écran de 375 px, une taille pareille ne tient pas, et elle survit à une messagerie qui supprime les feuilles de style.',
                        (int) $taille
                    );
                }
            }
        }
    }
}

if ( ! is_readable( $otp ) ) {
    $hits[] = 'Le message du code de vérification est introuvable.';
} else {
    $src = (string) file_get_contents( $otp );
    if ( false === strpos( $src, 'white-space:nowrap' ) ) {
        $hits[] = 'Le code de vérification peut de nouveau se couper en deux : c’est la seule information du message qu’on lit caractère par caractère.';
    }
    if ( preg_match( '/letter-spacing:\s*(\d+)px[^"]*"/', $src, $m ) && (int) $m[1] > 6 ) {
        $hits[] = sprintf( 'Interlettrage du code à %d px : six chiffres réclament alors plus que la largeur utile d’un iPhone.', (int) $m[1] );
    }
}

if ( $hits ) {
    echo "Lisibilité des e-mails sur téléphone :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 occurrence — le gabarit reste lisible sur un écran de 375 px.\n";
exit( 0 );
