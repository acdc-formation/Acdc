<?php
/**
 * Une suppression laisse une trace, ou elle n'a jamais eu lieu.
 *
 * C'est la pièce qui manquait à la traçabilité. Sur 54 gestionnaires de
 * suppression, QUATRE écrivaient au journal ; huit écrivaient dans un journal de
 * module que l'écran ne montrait pas ; les autres ne laissaient rien. Un
 * apprenant, une facture, une convention, un émargement pouvaient disparaître
 * sans qu'aucun écran ne puisse dire qui, ni quand.
 *
 * DEUX RÈGLES, APPRISES EN SE TROMPANT.
 *
 *   1. LA TRACE SE POSE SUR LA SUPPRESSION, PAS SUR LA REDIRECTION. Une première
 *      version posait la trace devant chaque sortie de succès. Or une page qui
 *      annonce « Interaction supprimée » ne prouve pas qu'une ligne l'ait été :
 *      dans handle_delete_prospect_activity, ce message s'affiche même quand
 *      rien n'a été supprimé. La trace aurait recopié ce mensonge dans le
 *      journal — exactement ce que la section 6 de scan-durcissement interdit.
 *      L'ancrage est donc le $wpdb->delete() lui-même, ou le retrait effectif de
 *      la fiche : à l'intérieur de la condition qui le garde, et avec son
 *      résultat réel.
 *
 *   2. UNE DÉLÉGATION COMPTE. Un gestionnaire qui appelle une fonction interne
 *      elle-même tracée est tracé. Ignorer cela accuserait à tort les six
 *      gestionnaires d'enquête, dont la trace est posée une fois pour toutes
 *      dans la fonction qui retire réellement la fiche.
 *
 * Le plafond ci-dessous est une DETTE DÉCLARÉE, pas une tolérance : il ne peut
 * que descendre. Toute suppression ajoutée sans trace fait échouer ce balayage.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';
$plafond = 0;

/* Tous les journaux du plugin — chacun verse au journal commun depuis la
   3.25.299/301, y compris celui du module signature, qui est une classe
   autonome et passe par la porte publique acdc_journaliser(). */
$journaux = '(log_action_event|insert_system_log|log_qz_event|learner_portal_log_event'
    . '|trainer_portal_log_event|log_questionnaire_event|add_marketing_log'
    . '|append_quality_audit_log_event|->log_event\()';

/* Corps de chaque fonction du plugin : de sa déclaration à la suivante. Découper
   sur l'accolade fermante serait plus juste en théorie et faux en pratique,
   l'indentation n'étant pas uniforme dans ce dépôt. */
$corps = array();
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/includes' ) );
foreach ( $it as $f ) {
    if ( 'php' !== $f->getExtension() ) {
        continue;
    }
    $lignes = file( $f->getPathname() );
    $debuts = array();
    foreach ( $lignes as $i => $l ) {
        if ( preg_match( '/\bfunction\s+\w+\s*\(/', $l ) ) {
            $debuts[] = $i;
        }
    }
    foreach ( $lignes as $i => $l ) {
        if ( ! preg_match( '/\bfunction\s+(\w+)\s*\(/', $l, $m ) ) {
            continue;
        }
        $fin = count( $lignes );
        foreach ( $debuts as $d ) {
            if ( $d > $i ) { $fin = $d; break; }
        }
        $corps[ $m[1] ] = array(
            implode( '', array_slice( $lignes, $i, $fin - $i ) ),
            str_replace( $root . '/includes/', '', $f->getPathname() ),
        );
    }
}
if ( count( $corps ) < 500 ) {
    echo "ALERTE  seules " . count( $corps ) . " fonctions ont été relues : le balayage ne prouve rien.\n";
    exit( 1 );
}

$tracee = function ( $c ) use ( $journaux ) {
    return (bool) preg_match( '/' . $journaux . '/', $c );
};

$muets = array();
foreach ( $corps as $nom => $info ) {
    if ( ! preg_match( '/^(handle|ajax)_[a-z0-9_]*(delete|remove|supprim|purge)/i', $nom ) ) {
        continue;
    }
    if ( $tracee( $info[0] ) ) {
        continue;
    }
    /* Délégation : une seule profondeur, et seulement vers une fonction dont le
       nom dit qu'elle supprime — sinon n'importe quel appel utilitaire tracé
       ferait passer un gestionnaire muet pour tracé. */
    $delegue = false;
    if ( preg_match_all( '/\$this->([a-z_0-9]+)\(/', $info[0], $appels ) ) {
        foreach ( array_unique( $appels[1] ) as $appel ) {
            if ( ! preg_match( '/delete|remove|purge|supprim/i', $appel ) ) {
                continue;
            }
            if ( isset( $corps[ $appel ] ) && $tracee( $corps[ $appel ][0] ) ) {
                $delegue = true;
                break;
            }
        }
    }
    if ( ! $delegue ) {
        $muets[] = $nom . ' (' . $info[1] . ')';
    }
}
sort( $muets );

if ( count( $muets ) > $plafond ) {
    printf(
        "ALERTE  %d suppressions ne laissent aucune trace, soit %d de plus que la dette déclarée.\n",
        count( $muets ),
        count( $muets ) - $plafond
    );
    foreach ( $muets as $m ) {
        echo '        ' . $m . "\n";
    }
    echo "        Une suppression sans trace ne peut être ni constatée, ni datée, ni attribuée.\n";
    exit( 1 );
}
if ( count( $muets ) < $plafond ) {
    printf(
        "La dette a baissé : %d suppressions muettes au lieu de %d. Abaissez \$plafond à %d pour la verrouiller.\n",
        count( $muets ),
        $plafond,
        count( $muets )
    );
    exit( 1 );
}
echo "Toute suppression laisse une trace : aucune n’échappe au journal.\n";
exit( 0 );
