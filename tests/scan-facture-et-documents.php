<?php
/**
 * ACDC 3.25.321 — La facture subrogée, et les documents de la seconde journée.
 *
 * DEUX DÉFAUTS, UNE MÊME FAMILLE : du code qui cherche une donnée là où l'écran
 * en écrit une autre.
 *
 * 1. LA FACTURE. Un dossier avec 1 200 € pris en charge sur 1 800 € doit
 *    produire DEUX factures liées. Il en produisait UNE, au client, pour la
 *    totalité. La logique des deux factures existait et était juste : c'est la
 *    CONVENTION qui n'était pas retrouvée. La recherche s'appuyait sur
 *    « quote_id » et « company_id », alors que l'écran de création poste
 *    « source_prospect_id » — la seule colonne que portent à la fois le devis et
 *    la convention. Même racine que la 3.25.316.
 *    Et le repli était muet : « client seul » est aussi le cas ordinaire, si
 *    bien qu'une facture fausse ressemblait trait pour trait à une facture
 *    juste. Une erreur de facturation silencieuse est pire qu'un refus.
 *
 * 2. LES DOCUMENTS DE SÉANCE. Une formation de deux jours tient en deux
 *    séances, et « apprenants.session_id » n'en porte qu'une : l'inscription
 *    rattache à la PREMIÈRE. Trois documents déposés sur la seconde journée
 *    n'apparaissaient chez aucun des trois apprenants. C'est le défaut corrigé
 *    en 3.25.211 pour le portail formateur, resté intact côté apprenant.
 *
 * LA RÈGLE QU'ON PROTÈGE SURTOUT ICI : l'élargissement passe par
 * « acdc_session_learners() », donc une séance de la même formation animée pour
 * une AUTRE entreprise ne contient pas cet apprenant et n'entre pas. Remplacer
 * ce filtre par un simple « même formation » ouvrirait les documents d'un client
 * à ceux d'un autre. C'est la vérification la plus importante de ce fichier.
 *
 * Éprouvé en sabotant : chaque règle échoue quand on défait la correction.
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

$code = function ( $relatif ) use ( $racine ) {
    $chemin = $racine . '/' . $relatif;
    if ( ! is_readable( $chemin ) ) {
        return '';
    }
    $sans = '';
    foreach ( token_get_all( (string) file_get_contents( $chemin ) ) as $jeton ) {
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
    return $sans;
};

/* ── 1. LA FACTURE ─────────────────────────────────────────────────────── */
$fact = $code( 'includes/documents-billing/class-acdc-documents-billing-core-trait.php' );
$exiger( '' !== $fact, 'Le fichier de facturation est introuvable.' );

$exiger(
    (bool) preg_match( '/WHERE source_prospect_id = %d ORDER BY id DESC LIMIT 1/', $fact ),
    'La convention n’est plus retrouvée par « source_prospect_id » : c’est le seul lien que l’écran remplit, et sans lui une prise en charge partielle produit une facture unique au client pour la totalité.'
);
$exiger(
    (bool) preg_match( '/quote_id = %d/', $fact ),
    'La recherche par « quote_id » a disparu : le lien direct devis → convention n’est plus consulté.'
);
$exiger(
    false !== strpos( $fact, 'invoice_client_seul_malgre_financement' ),
    'Une facture au client seul sur un dossier à financement externe repasse en silence : c’est exactement l’erreur de facturation qu’on ne voit pas.'
);
/* Les deux factures liées doivent rester liées : sans le lien, on ne sait plus
   qu'elles se complètent, et le reste à charge devient un second total. */
/* Le lien va dans les DEUX sens : la facture client désigne celle du financeur,
   et le financeur est mis à jour pour désigner celle du client. Un lien
   unilatéral laisse la seconde facture orpheline dans les écrans qui partent de
   l'autre bout. */
$exiger(
    (bool) preg_match( '/\$facture_client\[.sibling_invoice_id.\]\s*=\s*\$id_financeur/', $fact ),
    'La facture du client ne désigne plus celle du financeur : le reste à charge se lit alors comme une facture indépendante.'
);
$exiger(
    (bool) preg_match( "/'sibling_invoice_id'\s*=>\s*\\\$id_client/", $fact ),
    'La facture du financeur ne désigne plus celle du client : le lien est unilatéral et la seconde facture devient orpheline.'
);

$exiger(
    (bool) preg_match( "/FundingSplit::FINANCEUR_SEUL/", $fact ) && (bool) preg_match( "/FundingSplit::CLIENT_SEUL/", $fact ),
    'Un des trois cas de financement a disparu de la décision.'
);

/* ── 2. LES DOCUMENTS DE SÉANCE ────────────────────────────────────────── */
$docs = $code( 'includes/sessions/class-acdc-session-documents-trait.php' );
$exiger( '' !== $docs, 'Le fichier des documents de séance est introuvable.' );

$exiger(
    (bool) preg_match( '/WHERE formation_id = %d AND COALESCE\(is_draft, 0\) = 0/', $docs ),
    'Les autres séances de la même formation ne sont plus consultées : un document déposé sur la seconde journée reste invisible chez l’apprenant.'
);
/* LA CLOISON — la vérification la plus importante du fichier. */
$exiger(
    (bool) preg_match( '/acdc_session_learners\(\s*\$autre\s*\)/', $docs ),
    'L’élargissement ne passe plus par la liste réelle des apprenants de la séance : une séance de la même formation animée pour une AUTRE entreprise entrerait, et ses documents seraient exposés à des apprenants qui n’y ont pas droit.'
);
$exiger(
    (bool) preg_match( '/\(int\)\s*\$__ap->id\s*===\s*\$learner_id/', $docs ),
    'La comparaison qui vérifie que l’apprenant appartient bien à la séance a disparu : la cloison ne tient plus.'
);
$exiger(
    (bool) preg_match( '/\$formation_id > 0 && \$learner_id > 0 && method_exists/', $docs ),
    'L’élargissement n’est plus conditionné à un apprenant identifié : sans identité, il ramènerait toutes les séances de la formation.'
);

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Facture subrogée et documents de séance — %d/%d vertes.\n", $verifs, $verifs );
exit( 0 );
