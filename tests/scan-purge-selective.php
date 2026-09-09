<?php
/**
 * ACDC 3.25.322 — La remise à zéro se choisit ligne par ligne.
 *
 * C'est l'écran le plus destructeur du plugin, et il effaçait par blocs entiers.
 * Or « Formateurs et portail formateur » emporte d'un coup les fiches, les
 * contrats de mission signés, les bilans, la bibliothèque et les comptes de
 * portail : remettre à zéro les comptes d'un test coûtait les contrats.
 *
 * DEUX RÈGLES, ET LA SECONDE EST LA PLUS IMPORTANTE.
 *
 * 1. Chaque bloc expose ses sous-ensembles, et le SERVEUR n'efface que ceux
 *    qui lui parviennent cochés. Une case décochée à l'écran ne prouve rien :
 *    si le filtrage restait dans le navigateur, un bloc coché emporterait tout.
 *
 * 2. Les sous-ensembles sont la SOURCE UNIQUE des tables du bloc. Sans cette
 *    règle, les deux listes divergeraient au premier ajout de table : on
 *    cocherait une ligne en croyant tout tenir, pendant qu'une table oubliée
 *    partirait quand même — ou ne partirait jamais.
 *
 * Et le catalogue des formations rejoint les financeurs parmi les ensembles
 * protégés : vingt formations et leurs thématiques sont un travail de fond
 * réutilisé à chaque dossier, pas une donnée de dossier. Les effacer avec un
 * parcours de test, c'est perdre des semaines de saisie.
 *
 * Éprouvé en sabotant : chaque règle échoue quand on défait la correction.
 */

$racine  = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation';
$fichier = $racine . '/includes/kernel/class-acdc-kernel-purge-trait.php';

if ( ! is_readable( $fichier ) ) {
    fwrite( STDERR, "Fichier introuvable : $fichier\n" );
    exit( 1 );
}

$sans = '';
foreach ( token_get_all( (string) file_get_contents( $fichier ) ) as $jeton ) {
    if ( is_array( $jeton ) ) {
        if ( T_COMMENT === $jeton[0] || T_DOC_COMMENT === $jeton[0] ) {
            $sans .= str_repeat( "\n", substr_count( $jeton[1], "\n" ) );
            continue;
        }
        $sans .= $jeton[1];
        continue;
    }
    $sans .= $jeton;
}

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) {
        $echecs[] = $message;
    }
};

/* 1. LE FILTRAGE EST CÔTÉ SERVEUR. */
$exiger(
    (bool) preg_match( "/isset\( \\\$_POST\['acdc_purge_sous'\] \)\s*&&\s*is_array\( \\\$_POST\['acdc_purge_sous'\] \)/", $sans ),
    'Le serveur ne lit plus les sous-ensembles reçus : un bloc coché emporterait tout, quelles que soient les cases décochées à l’écran.'
);
$exiger(
    (bool) preg_match( '/\$groups\[ \$__cle_g \]\[.tables.\]\s*=\s*array_values\(\s*array_unique/', $sans ),
    'Les tables du bloc ne sont plus restreintes aux sous-ensembles choisis : la sélection fine n’a plus aucun effet sur ce qui est supprimé.'
);
$exiger(
    (bool) preg_match( '/if \( empty\( \$choisis \) \) \{\s*unset\( \$requested\[ \$__i \] \);/', $sans ),
    'Un bloc dont toutes les lignes sont décochées supprime de nouveau son contenu : la case du bloc l’emporte sur les choix de l’utilisateur.'
);
$exiger(
    false !== strpos( $sans, 'array_intersect( $choisis, array_keys(' ),
    'Les sous-ensembles reçus ne sont plus validés contre ceux qui existent : une clé forgée passerait.'
);

/* 2. LES SOUS-ENSEMBLES SONT LA SOURCE UNIQUE DES TABLES. */
$exiger(
    (bool) preg_match( '/\$orphelines = array_diff\( \$tables, \$depuis_sous \)/', $sans ),
    'Une table qu’aucun sous-ensemble ne réclame n’est plus rattachée : elle serait silencieusement épargnée, et l’écran mentirait sur ce qu’il efface.'
);
$exiger(
    (bool) preg_match( '/\$tables = \$depuis_sous;/', $sans ),
    'La liste des tables du bloc n’est plus reconstruite depuis ses sous-ensembles : les deux listes vont diverger.'
);

/* 3. LES ENSEMBLES PROTÉGÉS. */
$exiger(
    (bool) preg_match( "/'mot'\s*=>\s*'FORMATIONS'/", $sans ),
    'Le catalogue des formations n’est plus protégé : un « tout cocher » emporterait les vingt formations et leurs thématiques.'
);
$exiger(
    (bool) preg_match( "/'mot'\s*=>\s*'FINANCEURS'/", $sans ),
    'Les financeurs ne sont plus protégés par leur mot de confirmation.'
);
$exiger(
    (bool) preg_match( "/if \( empty\( \\\$__grp_s\['sensitive'\] \) \) \{ continue; \}/", $sans ),
    'La zone protégée ne boucle plus sur les ensembles sensibles : un nouvel ensemble protégé n’apparaîtrait nulle part et resterait effaçable.'
);
$exiger(
    ! preg_match( '/data-acdc-purge-box[^>]*value="funders"/', $sans ),
    'Un ensemble protégé a rejoint les cases de « Tout cocher ».'
);
/* Le bouton « tout cocher » ne doit pas nommer un seul ensemble protégé : ils
   sont deux, et il y en aura d'autres. */
$exiger(
    false === strpos( $sans, 'Tout cocher (sauf les financeurs)' ),
    'Le bouton annonce encore « sauf les financeurs » alors que le catalogue des formations est protégé lui aussi : l’utilisateur croirait le catalogue coché.'
);

/* 4. LE MESSAGE DE REFUS DIT LEQUEL. */
$exiger(
    false !== strpos( $sans, '$refuses' ),
    'Le refus ne nomme plus les ensembles protégés concernés : devant un catalogue refusé, le message envoie chercher du côté des financeurs.'
);

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Remise à zéro sélective : le choix descend jusqu’à la ligne — %d/%d vertes.\n", $verifs, $verifs );
exit( 0 );
