<?php
/**
 * Un portail à authentification propre ne parle pas à WordPress connecté.
 *
 * Relevé en production, sur l'iPhone de David : basculer une demi-journée de
 * disponibilité affichait « Erreur réseau. Réessayez. » — en navigation privée
 * comme en navigation normale, donc rien à voir avec un cache.
 *
 * La cause n'était pas dans le réseau. Les portails apprenant et formateur ont
 * leur PROPRE authentification, par cookie de session maison. Un formateur
 * connecté à son espace est, pour WordPress, un VISITEUR. Or
 * `admin_post_<action>` ne se déclenche que pour un utilisateur WordPress
 * connecté : sans le pendant `admin_post_nopriv_<action>`, admin-post.php ne
 * trouve aucun gestionnaire, ne répond rien, et le navigateur reçoit une page
 * vide qu'il n'arrive pas à lire comme du JSON. D'où le message d'erreur
 * générique — qui désigne le réseau alors que le serveur a répondu.
 *
 * Treize actions étaient dans ce cas : basculer une disponibilité, déposer ou
 * supprimer un document, télécharger son contrat, modifier son profil,
 * enregistrer un bilan de séance ou le cahier de texte, changer son mot de
 * passe, se déconnecter. Presque tout ce qu'un formateur ou un apprenant peut
 * FAIRE dans son espace.
 *
 * CE DÉFAUT EST INVISIBLE POUR QUI DÉVELOPPE : on est presque toujours connecté
 * à WordPress en même temps, et tout fonctionne. Il ne se manifeste que chez
 * l'utilisateur réel — c'est-à-dire trop tard.
 *
 * Ce balayage exige donc deux choses de chaque action de portail :
 *   1. qu'elle soit déclarée pour les visiteurs ;
 *   2. que son gestionnaire se garde LUI-MÊME. Déclarer sans garder
 *      ouvrirait une porte au lieu d'en réparer une.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$plugin = $root . '/includes/class-acdc-plugin.php';
if ( ! is_readable( $plugin ) ) {
    echo "class-acdc-plugin.php introuvable.\n";
    exit( 1 );
}

$src  = (string) file_get_contents( $plugin );
$hits = array();

preg_match_all( "/add_action\(\s*'admin_post_(acdc_(?:trainer|learner)_[a-z_0-9]+)'\s*,\s*array\(\s*\\\$this\s*,\s*'([a-z_0-9]+)'/i", $src, $m, PREG_SET_ORDER );
$actions = array();
foreach ( $m as $found ) {
    $actions[ $found[1] ] = $found[2];
}

preg_match_all( "/add_action\(\s*'admin_post_nopriv_(acdc_(?:trainer|learner)_[a-z_0-9]+)'/i", $src, $mn );
$nopriv = array_flip( $mn[1] );

/* Les gestionnaires vivent dans les traits des deux portails. */
$sources = '';
foreach ( array( '/includes/trainer-portal/', '/includes/learner-portal/' ) as $dossier ) {
    $chemin = $root . $dossier;
    if ( ! is_dir( $chemin ) ) { continue; }
    $rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $chemin ) );
    foreach ( $rii as $f ) {
        if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
        $sources .= (string) file_get_contents( $f->getPathname() );
    }
}

foreach ( $actions as $action => $handler ) {
    if ( ! isset( $nopriv[ $action ] ) ) {
        $hits[] = sprintf(
            '%s n’est déclarée que pour un utilisateur WordPress connecté : un formateur ou un apprenant de son propre espace est un VISITEUR pour WordPress. admin-post.php ne trouvera aucun gestionnaire, ne répondra rien, et l’écran affichera « Erreur réseau ».',
            $action
        );
        continue;
    }

    /* Déclarée pour les visiteurs : elle doit alors se garder elle-même.
       Le corps se lit jusqu'à la fonction suivante — une accolade fermante ne
       marque pas la fin d'une fonction, seulement celle d'un bloc, et s'arrêter
       à la première rendait le contrôle aveugle. */
    $debut = strpos( $sources, 'function ' . $handler . '(' );
    if ( false === $debut ) {
        $debut = strpos( $sources, 'function ' . $handler . ' (' );
    }
    if ( false === $debut ) {
        continue;
    }
    $suite = preg_match( '/\n\s{0,4}(?:public|private|protected)\s+function\s/', $sources, $mm, PREG_OFFSET_CAPTURE, $debut + 10 )
        ? $mm[0][1]
        : strlen( $sources );
    $bloc = substr( $sources, $debut, $suite - $debut );

    $a_jeton = preg_match( '/check_admin_referer|check_ajax_referer|wp_verify_nonce/', $bloc );
    if ( ! $a_jeton ) {
        $hits[] = sprintf(
            '%s est ouverte aux visiteurs mais %s() ne vérifie aucun jeton de sécurité : ouvrir sans garder n’est plus une réparation, c’est une porte.',
            $action,
            $handler
        );
        continue;
    }

    /* La session de portail ne peut pas être exigée des actions qui SERVENT à
       l'obtenir ou à la rompre : connexion, activation, mot de passe oublié,
       déconnexion, prise de contact d'un accès expiré. Les exiger reviendrait à
       demander d'être déjà entré pour pouvoir entrer. */
    if ( preg_match( '/_(login|logout|activate|activation|request_reset|reset_password|expired_contact)$/', $action ) ) {
        continue;
    }
    if ( ! preg_match( '/require_auth|get_current_account/', $bloc ) ) {
        $hits[] = sprintf(
            '%s est ouverte aux visiteurs mais %s() ne vérifie pas la session de portail : le jeton seul ne dit pas QUI agit.',
            $action,
            $handler
        );
    }
}

if ( $hits ) {
    echo "Actions de portail injouables ou mal gardées :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
printf( "0 occurrence — les %d actions de portail sont jouables par leurs utilisateurs, et gardées.\n", count( $actions ) );
exit( 0 );
