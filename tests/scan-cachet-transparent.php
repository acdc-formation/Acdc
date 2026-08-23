<?php
/**
 * ACDC 3.25.329 — Le cachet posé sur un rectangle blanc.
 *
 * CE QUE MONTRE LA RECETTE. Sur le certificat de réalisation et sur
 * l'attestation de fin de formation, la signature et le cachet arrivaient
 * dans un carré blanc franc, qui mordait sur le nom du signataire et sur le
 * pied de page. Les fichiers, eux, sont des PNG à fond TRANSPARENT — David
 * le précise : « pour information les signatures sont en PNG fond
 * transparent ».
 *
 * LA CAUSE. Le moteur PDF maison n'écrivait que du DCTDecode — du JPEG,
 * c'est-à-dire une image sans canal alpha. La préparation d'image le savait
 * et faisait ce qu'il fallait pour ne pas produire un fichier illisible :
 * elle APLATISSAIT le PNG sur un fond blanc avant d'encoder. Le carré blanc
 * n'est donc pas un accident d'affichage, c'est du blanc réellement peint
 * dans le document.
 *
 * CE QUE POSE LA CORRECTION. Dans un PDF, la transparence ne voyage pas
 * dans l'image : elle voyage À CÔTÉ, dans un /SMask — une seconde image en
 * niveaux de gris, de mêmes dimensions, où 255 est opaque et 0 transparent.
 * La préparation extrait ce canal, le moteur l'attache.
 *
 * CE QUE MESURE CE BALAYAGE. Pas le texte du code : le RÉSULTAT. Le
 * balayage compose réellement les deux traits, fabrique un PNG transparent,
 * le fait passer par la préparation, construit un vrai PDF, puis décomprime
 * le masque et LIT ses octets. Un fond transparent doit y valoir 0, un
 * pixel d'encre 255. C'est la seule façon de prouver qu'on n'a pas écrit
 * le masque à l'envers — auquel cas le document sortirait cachet effacé et
 * fond peint, et aucune relecture ne le verrait.
 *
 * LA MOITIÉ QU'ON AURAIT PU OUBLIER. Une vingtaine d'endroits fabriquent un
 * élément « image » à la main, champ par champ. Le masque ne transite donc
 * pas par la valeur de retour : il est déposé dans un registre indexé par la
 * clé de l'image, que le moteur consulte seul. Le balayage le vérifie en
 * construisant l'élément SANS recopier le canal alpha — exactement comme le
 * font les vingt appelants existants.
 */

$racine = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation';

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) {
        $echecs[] = $message;
    }
};

if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'gzuncompress' ) ) {
    fwrite( STDERR, "GD ou zlib absent : ce balayage ne peut pas mesurer.\n" );
    exit( 1 );
}

/* --- Le banc : les deux traits réellement composés ------------------------
   On ne réimplémente rien. Les fonctions mesurées sont celles du plugin ;
   seules les fonctions WordPress qu'elles traversent sont bouchonnées. */
$base = sys_get_temp_dir() . '/acdc-cachet-' . getmypid();
@mkdir( $base, 0777, true );

if ( ! function_exists( 'wp_get_upload_dir' ) ) {
    $GLOBALS['acdc_base_test'] = $base;
    function wp_get_upload_dir() {
        return array( 'baseurl' => 'https://exemple.test/wp-content/uploads', 'basedir' => $GLOBALS['acdc_base_test'] );
    }
    function home_url( $p = '' ) { return 'https://exemple.test' . $p; }
    function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
    function wp_parse_url( $u, $c = -1 ) { return ( -1 === $c ) ? parse_url( $u ) : parse_url( $u, $c ); }
}
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', $base . '/' ); }

require_once $racine . '/includes/kernel/class-acdc-kernel-core-trait.php';
require_once $racine . '/includes/kernel/class-acdc-kernel-render-trait.php';

class ACDC_Banc_Cachet { use ACDC_Kernel_Core_Trait; use ACDC_Kernel_Render_Trait; }

$banc = new ACDC_Banc_Cachet();
$appeler = function ( $methode, $args ) use ( $banc ) {
    $r = new ReflectionMethod( 'ACDC_Banc_Cachet', $methode );
    $r->setAccessible( true );
    return $r->invokeArgs( $banc, $args );
};

/* --- Le cachet d'essai : fond transparent, encre opaque ------------------- */
$L = 120;
$H = 60;
$src = imagecreatetruecolor( $L, $H );
imagealphablending( $src, false );
imagesavealpha( $src, true );
imagefilledrectangle( $src, 0, 0, $L - 1, $H - 1, imagecolorallocatealpha( $src, 255, 255, 255, 127 ) );
imagefilledrectangle( $src, 40, 20, 79, 39, imagecolorallocatealpha( $src, 12, 45, 82, 0 ) );
imagepng( $src, $base . '/cachet.png' );
imagedestroy( $src );

$prepare = $appeler( 'prepare_pdf_jpeg_image', array( 'https://exemple.test/wp-content/uploads/cachet.png', 170, 128 ) );

/* --- 1 à 4. LE CANAL ALPHA EST EXTRAIT, ET DANS LE BON SENS -------------- */
$exiger( is_array( $prepare ), '1. La préparation d\'image a échoué sur un PNG transparent.' );
$exiger( is_array( $prepare ) && ! empty( $prepare['alpha_data'] ), '2. Aucun canal alpha extrait : le cachet repartirait sur fond blanc.' );

$masque = ( is_array( $prepare ) && ! empty( $prepare['alpha_data'] ) ) ? @gzuncompress( $prepare['alpha_data'] ) : '';
$exiger( '' !== $masque, '3. Le canal alpha ne se décomprime pas : le PDF serait illisible.' );
$exiger( strlen( $masque ) === $L * $H, '4. Le masque ne fait pas largeur x hauteur octets (' . strlen( $masque ) . ' pour ' . ( $L * $H ) . ').' );

/* --- 5 et 6. LA MESURE QUI COMPTE : LE SENS DU MASQUE --------------------
   Un masque à l'envers passe toutes les vérifications de structure et
   produit un document où le cachet a disparu et le fond est peint. */
$lire_alpha = function ( $x, $y ) use ( $masque, $L ) {
    $i = ( $y * $L ) + $x;
    return isset( $masque[ $i ] ) ? ord( $masque[ $i ] ) : -1;
};
$exiger( 0 === $lire_alpha( 5, 5 ), '5. Le fond transparent ne vaut pas 0 dans le masque (' . $lire_alpha( 5, 5 ) . ') : le rectangle blanc reviendrait.' );
$exiger( 255 === $lire_alpha( 60, 30 ), '6. L\'encre opaque ne vaut pas 255 dans le masque (' . $lire_alpha( 60, 30 ) . ') : le cachet serait effacé.' );

/* --- 7 à 10. LE MOTEUR ATTACHE LE MASQUE, SANS QU'ON LE LUI PASSE --------
   L'élément est construit ici EXACTEMENT comme le font les appelants
   existants : clé, données, dimensions. Pas de canal alpha recopié. */
$page = array( array(
    array( 'type' => 'page_meta', 'width' => 842, 'height' => 595 ),
    array(
        'type'           => 'image',
        'image_key'      => $prepare['key'],
        'image_data'     => $prepare['data'],
        'image_width'    => $prepare['width'],
        'image_height'   => $prepare['height'],
        'display_width'  => $prepare['display_width'],
        'display_height' => $prepare['display_height'],
        'x'              => 100,
        'y'              => 100,
    ),
) );
$pdf = $appeler( '_build_simple_pdf_string', array( $page ) );

$exiger( is_string( $pdf ) && 0 === strpos( $pdf, '%PDF-' ), '7. Le moteur n\'a pas produit de PDF.' );
$exiger( is_string( $pdf ) && false !== strpos( $pdf, '/SMask ' ), '8. Le PDF ne référence aucun /SMask : le registre de masques n\'a pas été consulté.' );
$exiger( is_string( $pdf ) && false !== strpos( $pdf, '/ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode' ), '9. Le masque n\'est pas écrit en gris comprimé : un lecteur PDF le refuserait.' );

/* Le flux du masque, relu DANS le document produit. */
$dans_le_pdf = false;
if ( is_string( $pdf ) && preg_match_all( '#/Filter /FlateDecode /Length (\d+) >>\s*stream\s*(.*?)\s*endstream#s', $pdf, $m, PREG_SET_ORDER ) ) {
    foreach ( $m as $bloc ) {
        $clair = @gzuncompress( $bloc[2] );
        if ( is_string( $clair ) && strlen( $clair ) === $L * $H && 0 === ord( $clair[0] ) ) {
            $dans_le_pdf = true;
        }
    }
}
$exiger( $dans_le_pdf, '10. Le masque relu dans le PDF produit ne correspond pas au cachet préparé.' );

/* --- 11 et 12. LA NON-RÉGRESSION : UNE IMAGE OPAQUE NE CHANGE PAS -------
   C'est la moitié qu'une correction emportée oublie. Un logo JPEG doit
   traverser exactement le chemin d'avant : aucun masque, aucun /SMask. */
$logo = imagecreatetruecolor( 80, 40 );
imagefilledrectangle( $logo, 0, 0, 79, 39, imagecolorallocate( $logo, 200, 30, 30 ) );
imagejpeg( $logo, $base . '/logo.jpg', 90 );
imagedestroy( $logo );

$prep_logo = $appeler( 'prepare_pdf_jpeg_image', array( 'https://exemple.test/wp-content/uploads/logo.jpg', 170, 128 ) );
$exiger( is_array( $prep_logo ) && empty( $prep_logo['alpha_data'] ), '11. Une image opaque produit un canal alpha : le chemin d\'avant a changé.' );

$page_logo = array( array(
    array( 'type' => 'image', 'image_key' => $prep_logo['key'], 'image_data' => $prep_logo['data'], 'image_width' => $prep_logo['width'], 'image_height' => $prep_logo['height'], 'display_width' => 80, 'display_height' => 40, 'x' => 10, 'y' => 10 ),
) );
$pdf_logo = $appeler( '_build_simple_pdf_string', array( $page_logo ) );
$exiger( is_string( $pdf_logo ) && false === strpos( $pdf_logo, '/SMask' ), '12. Un /SMask est écrit pour une image opaque.' );

@unlink( $base . '/cachet.png' );
@unlink( $base . '/logo.jpg' );
@rmdir( $base );

if ( $echecs ) {
    fwrite( STDERR, "ÉCHEC — scan-cachet-transparent (" . count( $echecs ) . "/$verifs)\n" );
    foreach ( $echecs as $e ) { fwrite( STDERR, "  - $e\n" ); }
    exit( 1 );
}
fwrite( STDOUT, "OK — scan-cachet-transparent : $verifs vérifications\n" );
exit( 0 );
