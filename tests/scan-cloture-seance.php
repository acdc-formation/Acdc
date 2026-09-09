<?php
/**
 * ACDC 3.25.318 — Les pièces de fin de formation ne dépendent pas d'un statut.
 *
 * CE QUE CE BALAYAGE DÉFEND. La routine de clôture automatique ne sélectionnait
 * que les séances dont le statut n'était PAS encore « Terminée ». Elle les
 * marquait terminées, puis produisait dans la même passe :
 *
 *   — le certificat de réalisation,
 *   — l'attestation de fin de formation,
 *   — le passage des dossiers à « formation réalisée », qui arme lui-même
 *     l'enquête à froid.
 *
 * Une séance passée à « Terminée » par une AUTRE voie — clôture manuelle,
 * validation — sortait donc définitivement du champ, et rien de tout cela
 * n'avait lieu. Constaté le 18/08 : deux séances terminées, seize émargements
 * signés, et pourtant aucun certificat stocké, trois dossiers figés à
 * « Pré-inscrit », l'enquête à froid jamais armée.
 *
 * Le défaut était INVISIBLE : l'écran d'administration régénère le certificat à
 * la demande, avec un nom lisible, sans jamais le stocker. On téléchargeait donc
 * une pièce qui n'existait nulle part — et l'apprenant, qui ne lit que la
 * version stockée, ne voyait rien.
 *
 * C'est la faute que ce plugin répète : un drapeau posé AVANT le travail, et le
 * travail qui n'a jamais lieu. Même forme qu'en 3.25.313 sur l'émargement, où
 * le numéro de version s'inscrivait avant la migration.
 *
 * Éprouvé en sabotant : chaque règle échoue quand on défait la correction.
 */

$racine  = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation';
$fichier = $racine . '/includes/sessions/class-acdc-sessions-actions-trait.php';

if ( ! is_readable( $fichier ) ) {
    fwrite( STDERR, "Fichier introuvable : $fichier\n" );
    exit( 1 );
}

/* Le code débarrassé de ses commentaires : chercher un appel dans le source
   trouve aussi la phrase qui EXPLIQUE la correction. Faute déjà commise deux
   fois cette semaine, on ne la refait pas. */
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

$debut = strpos( $sans, 'public function process_of_session_auto_close()' );
if ( false === $debut ) {
    fwrite( STDERR, "ÉCHEC — process_of_session_auto_close() est introuvable.\n" );
    exit( 1 );
}
$reste = substr( $sans, $debut + 40 );
$fin   = preg_match( '/\n  (?:public|private|protected) function /', $reste, $m, PREG_OFFSET_CAPTURE )
    ? $m[0][1] : strlen( $reste );
$corps = substr( $sans, $debut, $fin + 40 );

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) {
        $echecs[] = $message;
    }
};

/* 1. La passe de rattrapage existe, et elle vise les séances DÉJÀ terminées. */
$exiger(
    (bool) preg_match( "/COALESCE\(status, ''\)\s*=\s*'Terminée'/u", $corps ),
    'La passe de rattrapage sur les séances déjà « Terminée » a disparu : une séance clôturée par une autre voie ne recevra jamais ses certificats, ses dossiers resteront « Pré-inscrit » et l’enquête à froid ne sera jamais armée.'
);

/* 2. Elle produit bien les pièces de fin — deux appels, pas un. */
/* Les deux passes n'appellent pas la même porte : la clôture passe par les
   enrobages historiques, le rattrapage appelle la routine directement. On exige
   les DEUX, sans quoi l'une des deux passes ne produit plus rien. */
$exiger(
    false !== strpos( $corps, '_auto_send_completion_certificate_for_session(' ),
    'La passe de clôture ne produit plus le certificat de réalisation.'
);
$exiger(
    false !== strpos( $corps, '_auto_send_end_training_certificate_for_session(' ),
    'La passe de clôture ne produit plus l’attestation de fin de formation.'
);
$exiger(
    false !== strpos( $corps, 'acdc_completion_dispatch_for_session(' ),
    'Le rattrapage ne produit plus les pièces de fin : une séance déjà terminée en resterait dépourvue.'
);

/* 3. Et elle fait avancer les dossiers, sans quoi le bas du cycle reste figé. */
$exiger(
    substr_count( $corps, "'formation_realisee'" ) >= 2,
    'Le rattrapage ne fait plus avancer les dossiers à « formation réalisée » : les statuts resteront figés et l’enquête à froid ne partira pas.'
);

/* 4. Le rattrapage est BORNÉ. Sans limite, chaque passage du cron reprendrait
      tout l'historique des séances du site. */
$exiger(
    (bool) preg_match( '/=\s*\'Terminée\'.{0,120}LIMIT\s+\d+/su', $corps ),
    'Le rattrapage n’est plus borné : chaque passage du cron reprendrait tout l’historique des séances.'
);

/* 5. La passe de clôture d'origine reste intacte : elle doit continuer à
      n'attraper que ce qui n'est PAS terminé, sinon on reclôture en boucle. */
$exiger(
    (bool) preg_match( "/NOT IN \('Terminée', 'Annulée', 'Brouillon'\)/u", $corps ),
    'La passe de clôture ne filtre plus les séances déjà terminées : elles seraient reclôturées à chaque passage.'
);

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Clôture de séance : les pièces de fin ne dépendent plus d’un statut — %d/%d vertes.\n", $verifs, $verifs );
exit( 0 );
