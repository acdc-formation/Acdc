<?php
/**
 * Douze messages en une seconde, et pas une version texte.
 *
 * Le 16 août, quatre enquêtes sont parties vers trois adresses au même instant.
 * Authentification parfaite — SPF, DKIM et DMARC au vert, DKIM aligné sur le bon
 * domaine, 9,6/10 chez mail-tester, serveur hors listes noires — et pourtant
 * tout en indésirables. Deux causes, relevées séparément par la recette e-mail
 * et par la lecture du code : la rafale, et le fait que ces messages étaient en
 * HTML PUR, sans version texte.
 *
 * Ces cas fixent ce que la version texte doit ABSOLUMENT conserver — les
 * adresses des liens, sans quoi une enquête devient un message sans objet — et
 * ce qu'elle doit ABSOLUMENT perdre : les règles CSS, qui sinon ouvrent le
 * message par trois cents caractères illisibles, visibles dans l'aperçu des
 * téléphones.
 */
define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/TexteAlternatif.php';

use ACDC\Support\TexteAlternatif as T;

$echecs = array();
function verifie( $titre, $attendu, $obtenu ) {
    global $echecs;
    $ok = ( $attendu === $obtenu );
    printf( "  %-62s %s\n", $titre, $ok ? 'oui' : '>>> NON — ' . var_export( $obtenu, true ) );
    if ( ! $ok ) { $echecs[] = $titre; }
}
function contient( $titre, $aiguille, $meule ) {
    verifie( $titre, true, false !== strpos( $meule, $aiguille ) );
}
function absent( $titre, $aiguille, $meule ) {
    verifie( $titre, false, false !== strpos( $meule, $aiguille ) );
}

/* --- LE GABARIT RÉEL : en-tête stylé, corps, lien d'enquête, pied --- */
$mail = '<html><head><title>ACDC</title><style>.acdc-mail-foot{padding:18px;color:#6a7488}</style></head>'
    . '<body><div class="acdc-mail"><h1>Votre avis compte</h1>'
    . '<p>Bonjour Marie,</p>'
    . '<p>Votre formation s&rsquo;est termin&eacute;e. Merci d&rsquo;y consacrer 3&nbsp;minutes&nbsp;:</p>'
    . '<a href="https://acdcformation.com/enquete/?t=abc123">R&eacute;pondre &agrave; l&rsquo;enqu&ecirc;te</a>'
    . '<ul><li>Anonyme</li><li>3 minutes</li></ul>'
    . '<div class="acdc-mail-foot">04 94 00 00 00 &middot; <a href="mailto:contact@acdc-formation.com">contact@acdc-formation.com</a></div>'
    . '</div></body></html>';
$texte = T::depuisHtml( $mail );

contient( 'LE LIEN DE L’ENQUÊTE SURVIT, avec son adresse',
    'Répondre à l’enquête (https://acdcformation.com/enquete/?t=abc123)', $texte );
absent( 'les règles CSS ont disparu', 'padding:18px', $texte );
absent( '  et le nom de la classe avec', 'acdc-mail-foot', $texte );
absent( 'le titre de la page n’est pas du contenu', '<title>', $texte );
absent( 'aucune balise ne subsiste', '<', $texte );
contient( 'le texte du message est là', 'Bonjour Marie,', $texte );
contient( 'les apostrophes typographiques sont décodées', 's’est terminée', $texte );
contient( 'les puces sont lisibles', "- Anonyme\n- 3 minutes", $texte );

/* --- LES LIENS : CE QU'ON DOUBLE, ET CE QU'ON NE DOUBLE PAS --- */
verifie( 'un mailto se lit seul, sans son adresse répétée',
    'Écrivez-nous', T::depuisHtml( '<a href="mailto:contact@acdc-formation.com">Écrivez-nous</a>' ) );
verifie( 'une ancre interne ne pollue pas le texte',
    'Haut de page', T::depuisHtml( '<a href="#top">Haut de page</a>' ) );
verifie( 'un lien dont le texte EST l’adresse n’est pas écrit deux fois',
    'https://acdcformation.com', T::depuisHtml( '<a href="https://acdcformation.com">https://acdcformation.com</a>' ) );
verifie( 'un lien sans texte rend quand même son adresse',
    'https://acdcformation.com/x', T::depuisHtml( '<a href="https://acdcformation.com/x"></a>' ) );
verifie( 'un lien dont le libellé porte du gras reste propre',
    'Cliquer ici (https://a.fr/b)', T::depuisHtml( '<a href="https://a.fr/b"><strong>Cliquer</strong> ici</a>' ) );

/* --- LA MISE EN FORME --- */
verifie( 'un saut de ligne HTML devient un saut de ligne',
    "Ligne un\nLigne deux", T::depuisHtml( 'Ligne un<br>Ligne deux' ) );
verifie( 'deux paragraphes restent deux paragraphes',
    "Un\n\nDeux", T::depuisHtml( '<p>Un</p><p></p><p>Deux</p>' ) );
verifie( 'jamais plus d’une ligne vide de séparation',
    "Un\n\nDeux", T::depuisHtml( '<p>Un</p><br><br><br><br><p>Deux</p>' ) );
verifie( 'l’espace insécable redevient un espace',
    '3 minutes', T::depuisHtml( '3&nbsp;minutes' ) );
verifie( 'les espaces multiples sont resserrés',
    'a b', T::depuisHtml( 'a      b' ) );

/* --- CE QU'ON REFUSE DE RENDRE --- */
verifie( 'un corps vide ne rend rien', '', T::depuisHtml( '' ) );
verifie( 'du blanc seul ne rend rien', '', T::depuisHtml( "   \n\n  " ) );
verifie( 'un message qui n’est QUE du style ne rend rien',
    '', T::depuisHtml( '<style>.x{color:red}</style>' ) );
verifie( 'un script n’est jamais recopié', '', T::depuisHtml( '<script>alert(1)</script>' ) );

echo "\n";
if ( $echecs ) {
    printf( "%d cas en échec : %s\n", count( $echecs ), implode( ' | ', $echecs ) );
    exit( 1 );
}
echo "Version texte : les liens survivent, le style disparaît, rien n’est jamais vide par accident.\n";
exit( 0 );
