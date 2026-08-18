<?php
/**
 * ACDC 3.25.325 — Le podium à l'échelle de ceux qui montent dessus.
 *
 * CE QUE MESURE CE BALAYAGE. Sur l'écran de fin de quiz live, le podium
 * s'affichait comme un jouet sous des personnages grandeur nature. Mesuré au
 * navigateur, sur 1920 × 1080 : podium 243 px de large, personnages 507 px de
 * haut. La recette du 18 août le dit en une phrase — « les personnages ont la
 * bonne taille, mais pas le podium ».
 *
 * LA CAUSE, EN TROIS COUCHES.
 *   1. L'image portait 60 % de vide : le dessin n'occupait que 1021 × 581 d'un
 *      fichier de 1086 × 1448.
 *   2. Elle était servie en largeur (« 103% ») ET plafonnée en hauteur
 *      (« max-height: 32vh »). Avec object-fit: contain, le plafond de hauteur
 *      emportait la largeur : l'image se réduisait des deux côtés.
 *   3. Les personnages, eux, étaient dimensionnés en « vh ». Deux références
 *      différentes pour une même scène : aucun réglage ne pouvait être juste
 *      ailleurs que sur l'écran où il avait été fait à l'œil — d'où six jeux de
 *      coordonnées par point de rupture, en double homme/femme, plus un
 *      septième écrit en dur dans le JavaScript du formateur, qui écrasait tous
 *      les autres puisqu'un style en ligne gagne toujours.
 *
 * CE QUE LA CORRECTION POSE. Une SCÈNE au rapport exact du dessin recadré : un
 * pourcentage y désigne le même point de l'image à toutes les tailles. Les trois
 * places sont mesurées sur les pixels — dessus des marches à 86,7 %, 56,3 % et
 * 49,7 % de la hauteur ; centres à 15,7 %, 49,5 % et 85,2 % de la largeur.
 *
 * Vérifié en rendant la page dans un navigateur, à 1920, 1366, 1024 et 390 px :
 * les trois personnages posent les pieds sur leur marche à chaque taille.
 */

$racine = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation';

$lire = function ( $relatif ) use ( $racine ) {
    $chemin = $racine . '/' . $relatif;
    if ( ! is_readable( $chemin ) ) {
        fwrite( STDERR, "Fichier introuvable : $chemin\n" );
        exit( 1 );
    }
    return (string) file_get_contents( $chemin );
};

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) {
        $echecs[] = $message;
    }
};

/* --- 1. L'IMAGE EST RECADRÉE SUR SON DESSIN ---
   On lit l'en-tête du PNG : c'est une mesure, pas une déclaration. */
$png = $lire( 'assets/images/podium/podium-base.png' );
$largeur = 0;
$hauteur = 0;
if ( strlen( $png ) > 24 && "\x89PNG" === substr( $png, 0, 4 ) ) {
    $largeur = unpack( 'N', substr( $png, 16, 4 ) )[1];
    $hauteur = unpack( 'N', substr( $png, 20, 4 ) )[1];
}
$exiger(
    $largeur > 0 && $hauteur > 0,
    'L’image du podium n’est plus un PNG lisible.'
);
$exiger(
    $hauteur > 0 && $largeur / $hauteur > 1.4,
    'L’image du podium est redevenue plus haute que large (' . $largeur . '×' . $hauteur . ') : elle porte de nouveau des marges transparentes, et tout calage en pourcentage vise à côté du dessin.'
);

/* --- 2. LA SCÈNE PORTE LE RAPPORT DE L'IMAGE --- */
$css = $lire( 'assets/css/quizzes-live.css' );
$exiger(
    (bool) preg_match( '/\.acdc-qz-podium\s*\{[^}]*aspect-ratio:\s*' . $largeur . '\s*\/\s*' . $hauteur . '/s', $css ),
    'La scène du podium ne porte plus le rapport EXACT de l’image (' . $largeur . '/' . $hauteur . ') : les pourcentages de placement ne désignent plus les marches.'
);
$exiger(
    (bool) preg_match( '/\.acdc-qz-podium\s*\{[^}]*width:\s*min\(/s', $css ),
    'La scène n’est plus bornée à la fois en largeur et en hauteur : sur un écran bas, les têtes des personnages sortent de la zone visible.'
);

/* --- 3. LE PLAFOND DE HAUTEUR QUI ÉCRASAIT LA LARGEUR A DISPARU --- */
$exiger(
    ! preg_match( '/\.acdc-qz-podium-base[^{]*\{[^}]*max-height:\s*[\d.]+vh/s', $css ),
    'L’image du podium est de nouveau plafonnée en « vh » : avec object-fit: contain, ce plafond emporte la largeur et le podium redevient un jouet.'
);
$exiger(
    ! preg_match( '/\.acdc-qz-podium-block-\d[^{]*\{[^}]*bottom:\s*[\d.]+%[^}]*left:/s', $css )
        || 3 === preg_match_all( '/\.acdc-qz-podium-block-\d \{ bottom: [\d.]+%; left: [\d.]+%; right: auto; \}/', $css ),
    'Les positions des trois places ne sont plus posées une seule fois : les tableaux par point de rupture sont revenus.'
);
$exiger(
    ! preg_match( '/is-homme .acdc-qz-podium-avatar/', $css ),
    'Les tailles d’avatar par genre et par écran sont revenues : un personnage ne change pas de taille selon qu’il est homme ou femme.'
);

/* --- 4. LE JAVASCRIPT NE REPOSITIONNE PLUS RIEN ---
   Un style en ligne gagne sur la feuille de style : tant qu'il en pose, c'est
   lui qui décide, et corriger le CSS ne change rien à l'écran. */
$host = $lire( 'assets/js/quizzes-live-host.js' );
$exiger(
    false === strpos( $host, 'PODIUM_POS' ),
    'Le JavaScript du formateur repose un jeu de coordonnées en dur : il écrasera la feuille de style, et la correction du CSS restera sans effet à l’écran.'
);
$exiger(
    ! preg_match( '/block\.style\.(bottom|left|marginLeft|width)\s*=/', $host ),
    'Le JavaScript du formateur repositionne les blocs en style en ligne.'
);
$exiger(
    false === strpos( $host, 'padding-top:65.1vh' ),
    'Le podium du formateur est de nouveau écrasé dans le dernier tiers de l’écran : ses personnages n’ont plus la place de tenir debout.'
);
$joueur = $lire( 'assets/js/quizzes-live-player.js' );
$exiger(
    ! preg_match( '/block\.style\.width\s*=/', $joueur ),
    'Le JavaScript du joueur impose de nouveau « width » au bloc : le bloc se dimensionne alors sur l’image du personnage à sa taille naturelle, et le placement proportionnel n’a plus d’effet.'
);

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Podium : une scène, des proportions, plus de tableaux — %d/%d vertes.\n", $verifs, $verifs );
exit( 0 );
