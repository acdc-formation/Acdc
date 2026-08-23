<?php
/**
 * ACDC 3.25.329 — Le certificat de réalisation reprend sa forme normalisée.
 *
 * CE QUE DIT LA RECETTE. « Les 2 sont présentées sous forme de diplôme.
 * L'attestation (non remise au financeur) reste sous la forme de diplôme.
 * Alors que le certificat doit plus être sous format A4 et rédigé. »
 *
 * POURQUOI CE N'EST PAS UNE QUESTION DE GOÛT. Les deux pièces n'ont pas le
 * même destinataire. L'attestation de fin de formation est la pièce de
 * l'APPRENANT : il la garde, la montre, parfois l'affiche — le cadre du
 * diplôme lui va. Le certificat de réalisation est la pièce du FINANCEUR :
 * il se transmet, se classe, se contrôle, et son modèle est publié — A4
 * portrait, texte rédigé à la première personne, mentions dans un ordre
 * imposé, cases à cocher pour la nature de l'action. Les deux avaient la
 * même allure parce qu'ils partageaient un gabarit, pas parce qu'ils
 * s'adressaient au même lecteur.
 *
 * CE QUE MESURE CE BALAYAGE. Il construit RÉELLEMENT le document — les deux
 * traits composés, le constructeur appelé — puis lit la page produite :
 * son format, ses mentions, ses cases. Lire le code source aurait suffi à
 * constater que les chaînes existent ; il fallait constater qu'elles
 * ARRIVENT sur la page, et qu'aucune ne se pose sur une autre.
 *
 * LA MOITIÉ À NE PAS EMPORTER. L'attestation doit RESTER un diplôme. Une
 * correction qui normaliserait les deux documents d'un coup ferait perdre à
 * l'apprenant la seule pièce qu'il ait à montrer. Le balayage vérifie donc
 * aussi que le cadre du diplôme est intact, à l'italienne.
 */

$racine = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation';

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) { $echecs[] = $message; }
};

/* --- Le banc : bouchons WordPress minimaux, code du plugin intact -------- */
$base = sys_get_temp_dir() . '/acdc-cert-' . getmypid();
@mkdir( $base, 0777, true );
$GLOBALS['acdc_base_cert'] = $base;
if ( ! function_exists( 'wp_get_upload_dir' ) ) {
    function wp_get_upload_dir() { return array( 'baseurl' => 'https://exemple.test/wp-content/uploads', 'basedir' => $GLOBALS['acdc_base_cert'] ); }
    function home_url( $p = '' ) { return 'https://exemple.test' . $p; }
    function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
    function wp_parse_url( $u, $c = -1 ) { return ( -1 === $c ) ? parse_url( $u ) : parse_url( $u, $c ); }
    function get_option( $k, $d = false ) { return $d; }
    function get_theme_mod( $k, $d = false ) { return $d; }
    function get_site_icon_url( $s = 512 ) { return ''; }
    function date_i18n( $f, $t = null ) { return date( $f, ( null === $t ) ? time() : $t ); }
    function esc_url( $u ) { return $u; }
    function absint( $n ) { return abs( (int) $n ); }
    function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
}
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', $base . '/' ); }

require_once $racine . '/includes/kernel/class-acdc-kernel-core-trait.php';
require_once $racine . '/includes/kernel/class-acdc-kernel-render-trait.php';
require_once $racine . '/includes/kernel/class-acdc-kernel-actions-trait.php';

class ACDC_Banc_Certificat {
    use ACDC_Kernel_Core_Trait;
    use ACDC_Kernel_Render_Trait;
    use ACDC_Kernel_Actions_Trait;
    /* Deux dépendances de fiche, bouchonnées : elles lisent des options
       WordPress et ne décident rien de la mise en page mesurée ici. */
    public function get_company_profile_options() { return array( 'first_name' => 'Ann-Cécile', 'last_name' => 'Joucher', 'signatory_role' => 'P.D.G' ); }
    public function acdc_org_identity() { return array( 'raison_sociale' => 'ACDC-Formation', 'ville' => 'Cogolin' ); }
}

$banc = new ACDC_Banc_Certificat();
$appeler = function ( $methode, $args ) use ( $banc ) {
    $r = new ReflectionMethod( 'ACDC_Banc_Certificat', $methode );
    $r->setAccessible( true );
    return $r->invokeArgs( $banc, $args );
};

$inscription = (object) array( 'id' => 3, 'company_label' => 'Skill Conseil' );
$contexte = array(
    'signatory_name'  => 'Ann-Cécile Joucher',
    'signatory_role'  => 'P.D.G',
    'issuer_name'     => 'ACDC-Formation',
    'issuer_city'     => 'Cogolin',
    'learner_name'    => 'Ilona Rossa',
    'formation_title' => 'L’Intelligence Artificielle appliquée à votre métier — Formation opérationnelle en 2 jours',
    'duration'        => '14 h',
    'hours_label'     => '14 h émargées',
    'start_date'      => '2026-08-18',
    'end_date'        => '2026-08-19',
    'company'         => (object) array( 'name' => 'Skill Conseil' ),
);
$pages = $appeler( 'build_completion_certificate_pdf_pages', array( $inscription, $contexte ) );

$page  = ( is_array( $pages ) && isset( $pages[0] ) && is_array( $pages[0] ) ) ? $pages[0] : array();
$meta  = array( 'width' => 0, 'height' => 0 );
$textes = array();
$rects  = array();
foreach ( $page as $e ) {
    if ( isset( $e['type'] ) && 'page_meta' === $e['type'] ) { $meta = $e; }
    if ( isset( $e['text'] ) ) { $textes[] = $e; }
    if ( isset( $e['type'] ) && 'rect' === $e['type'] ) { $rects[] = $e; }
}
$plat = implode( "\n", wp_list_pluck_simple( $textes, 'text' ) );

/* --- 1 et 2. LE FORMAT : A4 PORTRAIT, PAS UN DIPLÔME À L'ITALIENNE ------ */
$exiger( 1 === count( $pages ), '1. Le certificat ne fait pas exactement une page.' );
$exiger( 595 === (int) $meta['width'] && 842 === (int) $meta['height'], '2. Le certificat n\'est pas en A4 portrait (' . (int) $meta['width'] . 'x' . (int) $meta['height'] . ') : il est resté au format du diplôme.' );

/* --- 3 à 9. LES MENTIONS DU MODÈLE, RÉDIGÉES ---------------------------- */
$contient = function ( $aiguille ) use ( $plat ) { return false !== mb_strpos( $plat, $aiguille ); };
$exiger( $contient( 'CERTIFICAT DE RÉALISATION' ), '3. Le titre du modèle est absent.' );
$exiger( $contient( 'Je soussigné(e) Ann-Cécile Joucher' ), '4. La formule « Je soussigné(e) … » est absente : le document n\'est pas rédigé.' );
$exiger( $contient( 'atteste que' ), '5. La mention « atteste que » est absente.' );
$exiger( $contient( 'Mme/M. Ilona Rossa' ), '6. Le bénéficiaire n\'est pas nommé selon le modèle (« Mme/M. … »).' );
$exiger( $contient( 'Skill Conseil' ), '7. L\'employeur du bénéficiaire n\'apparaît pas (« salarié(e) de l\'entreprise … »).' );
$exiger( $contient( '18/08/2026' ) && $contient( '19/08/2026' ), '8. La période réalisée n\'est pas portée.' );
$exiger( $contient( '14 h' ), '9. La durée réalisée n\'est pas portée.' );

/* --- 10 à 12. LA NATURE DE L'ACTION : QUATRE CASES, UNE SEULE COCHÉE ----
   Les caractères de cases à cocher n'existent pas dans l'encodage de ce
   moteur PDF : ils sortiraient en points d'interrogation. Les cases sont
   donc DESSINÉES, et c'est ce dessin qu'on compte. */
$exiger( $contient( 'action de formation' ) && $contient( 'bilan de compétences' ) && $contient( 'action de VAE' ) && $contient( 'action de formation par apprentissage' ), '10. Les quatre natures d\'action du modèle ne sont pas toutes présentes.' );
$cases = 0;
$cochees = array();
foreach ( $rects as $r ) {
    $w = (float) ( $r['width'] ?? 0 );
    $h = (float) ( $r['height'] ?? 0 );
    if ( 9.0 === $w && 9.0 === $h && ! empty( $r['stroke_color'] ) ) { $cases++; }
    if ( 5.0 === $w && 5.0 === $h && ! empty( $r['fill_color'] ) ) { $cochees[] = (float) $r['y']; }
}
$exiger( 4 === $cases, '11. Le document ne dessine pas quatre cases à cocher (' . $cases . ').' );
$exiger( 1 === count( $cochees ), '12. Le nombre de cases cochées n\'est pas exactement une (' . count( $cochees ) . ').' );

/* Compter les coches ne suffit pas : une seule case cochée, mais celle de
   « action de VAE », produirait un certificat faux que rien ne signalerait.
   On rapproche donc la coche de la LIGNE qu'elle désigne, par sa hauteur. */
$y_action = null;
foreach ( $textes as $t ) {
    if ( 'action de formation' === (string) $t['text'] ) { $y_action = (float) $t['y']; }
}
$bien_placee = ( null !== $y_action && 1 === count( $cochees ) && abs( $cochees[0] - $y_action ) < 6 );
$exiger( $bien_placee, '12 bis. La case cochée n\'est pas celle de « action de formation » : le certificat déclarerait une autre nature d\'action.' );

/* --- 13. LES RENVOIS DU MODÈLE ------------------------------------------ */
$exiger( $contient( 'transition professionnelle' ) && $contient( 'à distance' ), '13. Les deux renvois de bas de page du modèle sont absents.' );

/* --- 14. AUCUNE MENTION NE SE POSE SUR LE CADRE DE SIGNATURE ------------
   Mesure, pas relecture : on cherche le cadre, puis on vérifie qu'aucune
   ligne du corps ne tombe dans sa bande verticale du côté gauche. */
$cadre = null;
foreach ( $rects as $r ) {
    if ( (float) ( $r['width'] ?? 0 ) > 200 && (float) ( $r['height'] ?? 0 ) > 100 && ! empty( $r['stroke_color'] ) ) { $cadre = $r; }
}
$chevauche = false;
if ( $cadre ) {
    $haut = (float) $cadre['y'] + (float) $cadre['height'];
    foreach ( $textes as $t ) {
        $ty = (float) $t['y'];
        $tx = (float) $t['x'];
        if ( $tx < (float) $cadre['x'] && $ty <= $haut && $ty >= (float) $cadre['y'] && (float) ( $t['size'] ?? 10 ) >= 9 ) {
            /* Le lieu et la date sont VOLONTAIREMENT à gauche du cadre, à sa
               hauteur : c'est la disposition du modèle. On ne compte que ce
               qui vient du corps. */
            if ( false === mb_strpos( (string) $t['text'], 'Fait à' ) && false === mb_strpos( (string) $t['text'], 'Le : ' ) ) {
                $chevauche = true;
            }
        }
    }
}
$exiger( $cadre && ! $chevauche, '14. Une mention du corps tombe dans la bande du cadre de signature.' );

/* --- 15 et 16. LA MOITIÉ QU'ON N'EMPORTE PAS : LE DIPLÔME RESTE --------- */
$cadre_diplome = $appeler( 'acdc_diploma_frame_elements', array( array( 'title' => 'ATTESTATION DE FIN DE FORMATION' ) ) );
$meta_dip = array( 'width' => 0, 'height' => 0 );
foreach ( (array) $cadre_diplome as $e ) {
    if ( isset( $e['type'] ) && 'page_meta' === $e['type'] ) { $meta_dip = $e; }
}
$exiger( 842 === (int) $meta_dip['width'] && 595 === (int) $meta_dip['height'], '15. Le cadre du diplôme n\'est plus à l\'italienne : l\'attestation de l\'apprenant a été emportée par la correction.' );

$source_attestation = (string) file_get_contents( $racine . '/includes/kernel/class-acdc-kernel-core-trait.php' );
$bloc = '';
$d = strpos( $source_attestation, 'private function build_end_training_certificate_pdf_pages' );
if ( false !== $d ) { $bloc = substr( $source_attestation, $d, 4000 ); }
$exiger( '' !== $bloc && false !== strpos( $bloc, 'acdc_diploma_frame_elements' ), '16. L\'attestation de fin de formation n\'utilise plus le cadre du diplôme.' );

/* --- 17 à 20. LA COPIE DÉJÀ ARCHIVÉE ------------------------------------
   Le certificat est STOCKÉ par la routine de clôture, et le téléchargement
   sert le fichier stocké sans le regarder. Sans ce contrôle, tous les
   dossiers déjà clôturés auraient continué de rendre l'ancien diplôme :
   la correction aurait été livrée sans rien changer à ce qu'on télécharge.
   On MESURE la détection sur deux vrais PDF, l'un à l'italienne, l'autre à
   la française — pas sur la lecture du code. */
$actions = (string) file_get_contents( $racine . '/includes/kernel/class-acdc-kernel-actions-trait.php' );
$exiger( false !== strpos( $actions, '$this->acdc_certificat_archive_est_perime( $document[\'path\'] )' ), '17. Le téléchargement ne vérifie pas le format de la copie archivée.' );
$exiger( false !== strpos( $actions, '@file_put_contents( $document[\'path\'], $pdf );' ), '18. La copie périmée n\'est pas réécrite à la même adresse : le lien de l\'extranet apprenant continuerait de pointer l\'ancien document.' );

$paysage = $appeler( '_build_simple_pdf_string', array( array( array(
    array( 'type' => 'page_meta', 'width' => 842, 'height' => 595 ),
    array( 'text' => 'ancien certificat', 'x' => 60, 'y' => 400, 'size' => 12, 'font' => 'Helvetica' ),
) ) ) );
$portrait = $appeler( '_build_simple_pdf_string', array( $pages ) );
$f_paysage  = $base . '/ancien.pdf';
$f_portrait = $base . '/nouveau.pdf';
file_put_contents( $f_paysage, $paysage );
file_put_contents( $f_portrait, $portrait );

$exiger( true === $appeler( 'acdc_certificat_archive_est_perime', array( $f_paysage ) ), '19. Une copie archivée à l\'italienne n\'est pas reconnue comme périmée : l\'ancien document continuerait d\'être servi.' );
$exiger( false === $appeler( 'acdc_certificat_archive_est_perime', array( $f_portrait ) ), '20. Une copie déjà au bon format est déclarée périmée : elle serait réécrite à chaque téléchargement.' );

@unlink( $f_paysage );
@unlink( $f_portrait );
@rmdir( $base );

if ( $echecs ) {
    fwrite( STDERR, "ÉCHEC — scan-certificat-normalise (" . count( $echecs ) . "/$verifs)\n" );
    foreach ( $echecs as $e ) { fwrite( STDERR, "  - $e\n" ); }
    exit( 1 );
}
fwrite( STDOUT, "OK — scan-certificat-normalise : $verifs vérifications\n" );
exit( 0 );

function wp_list_pluck_simple( $rows, $field ) {
    $out = array();
    foreach ( (array) $rows as $r ) { $out[] = isset( $r[ $field ] ) ? (string) $r[ $field ] : ''; }
    return $out;
}
