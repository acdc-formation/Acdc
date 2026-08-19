<?php
/**
 * ACDC 3.25.326 — Une enquête par formation, pas par journée.
 *
 * CE QUI EST ARRIVÉ. Formation de deux jours, 18 et 19 août, fin annoncée le 19
 * à 17 h. Les trois apprenants ont reçu l'enquête de satisfaction le 18 à 19 h —
 * le soir du PREMIER jour, la formation à peine commencée. Et ils l'auraient
 * reçue une seconde fois le 19 au soir.
 *
 * LA RACINE : UNE UNITÉ DE COMPTE FAUSSE. Toute la mécanique d'automatisation
 * des enquêtes prenait pour unité la SÉANCE — la liste des candidates rendait
 * une ligne par séance, la date de déclenchement se calculait sur la fin de
 * CETTE séance, et le garde-fou anti-doublon comparait le numéro de séance. Une
 * formation de deux jours fabriquait donc deux enquêtes, dont la première
 * partait avant la fin de la formation. Ce n'est pas un décalage d'horaire :
 * une enquête de satisfaction porte sur un PARCOURS, et l'apprenant n'en a
 * qu'un avis, à la fin.
 *
 * ET C'EST AUSSI LA PISTE DU COURRIER INDÉSIRABLE. David constate que les
 * convocations, les certificats et les convention arrivent en boîte de
 * réception, et que SEULES les enquêtes finissent en indésirables. Elles
 * partaient en double : deux messages au contenu identique, vers les trois
 * mêmes adresses, à quelques minutes d'intervalle. C'est exactement le motif
 * que cherche un filtre — il ne juge pas un message, il juge une répétition.
 * L'authentification n'y est pour rien : ces envois passent par la même porte
 * et le même « From: » que les autres.
 *
 * CE QUE CE BALAYAGE DÉFEND. L'unité de compte : le parcours. Ses six enquêtes
 * — intermédiaire, à chaud, à froid, formateur, entreprise, financeur — se
 * décident sur la séance qui le CLÔT, et sur une fenêtre qui va du début de la
 * première séance à la fin de la dernière.
 *
 * Éprouvé en sabotant : chaque règle échoue quand on défait la correction.
 */

$racine  = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation';
$fichier = $racine . '/includes/questionnaires/class-acdc-questionnaires-core-trait.php';

if ( ! is_readable( $fichier ) ) {
    fwrite( STDERR, "Fichier introuvable : $fichier\n" );
    exit( 1 );
}

/* Le code débarrassé de ses commentaires : ce fichier RACONTE le défaut qu'il
   corrige, et chercher « seance_id » dans le source brut trouverait d'abord la
   phrase qui l'explique. */
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

/* 1. Le résolveur de parcours existe et regroupe par formation ET commanditaire. */
$exiger(
    false !== strpos( $sans, 'function acdc_survey_seances_du_parcours(' ),
    'Le résolveur de parcours a disparu : chaque séance redevient une formation à elle seule, et une formation de deux jours enverra deux enquêtes.'
);
$exiger(
    (bool) preg_match( '/formation_id = %d.{0,200}COALESCE\(company_id, 0\) = %d/s', $sans ),
    'Le parcours ne se regroupe plus par formation ET commanditaire : deux clients suivant la même formation seraient réunis dans un seul parcours, ou séparés à tort.'
);
$exiger(
    (bool) preg_match( '/\$ecart_max\s*=\s*21\s*\*\s*DAY_IN_SECONDS/', $sans ),
    'Le découpage par contiguïté a disparu : la même formation vendue trois mois plus tard au même client serait rattachée au parcours précédent, et son enquête ne partirait jamais.'
);

/* 2. Les six enquêtes ne se déclenchent que sur la séance qui CLÔT le parcours. */
$exiger(
    substr_count( $sans, 'acdc_survey_est_fin_de_parcours(' ) >= 7,
    'Les six automatisations d’enquêtes ne vérifient plus toutes qu’elles sont sur la dernière séance du parcours : celles qui ne le font plus partiront une fois par journée de formation.'
);
foreach ( array( 'mid', 'hot', 'cold', 'trainer', 'company', 'funder' ) as $type ) {
    $exiger(
        (bool) preg_match( '/acdc_survey_est_fin_de_parcours\([^)]*\).{0,400}has_existing_' . $type . '_survey_automated_session/s', $sans ),
        'L’enquête « ' . $type . ' » ne contrôle plus la fin de parcours avant de se programmer.'
    );
}

/* 3. Le garde-fou anti-doublon porte sur TOUTES les séances du parcours.
      Sinon, ajouter une journée déplace la séance de clôture et fait naître
      une seconde enquête pour le même parcours. */
$exiger(
    false !== strpos( $sans, 'function acdc_survey_parcours_deja_programme(' ),
    'Le garde-fou anti-doublon par parcours a disparu.'
);
$exiger(
    (bool) preg_match( '/seance_id IN \(\{\$marques\}\)/', $sans ),
    'Le garde-fou anti-doublon est revenu à une seule séance : ajouter une journée à une formation ferait naître une seconde enquête pour le même parcours.'
);
$exiger(
    6 === substr_count( $sans, 'acdc_survey_parcours_deja_programme(' ) - 1,
    'Les six garde-fous anti-doublon ne délèguent plus tous au parcours.'
);

/* 4. La fenêtre de déclenchement est celle du parcours, pas d'une journée.
      C'est elle qui porte la date : la fin pour l'enquête à chaud, le milieu
      pour l'intermédiaire, la fin plus le délai pour celle à froid. */
$fenetre_debut = strpos( $sans, 'function get_mid_survey_session_window(' );
$fenetre = false !== $fenetre_debut ? substr( $sans, $fenetre_debut, 2200 ) : '';
$exiger(
    '' !== $fenetre && false !== strpos( $fenetre, 'acdc_survey_seances_du_parcours(' ),
    'La fenêtre de déclenchement est de nouveau bornée à une seule séance : l’enquête de fin repartira le soir du premier jour.'
);
$exiger(
    '' !== $fenetre && (bool) preg_match( '/if\s*\(\s*\$f\s*>\s*\$fin_parcours\s*\)\s*\{\s*\$fin_parcours\s*=\s*\$f;/s', $fenetre ),
    'La fin du parcours n’est plus calculée sur la DERNIÈRE séance.'
);

/* 4 bis. LE DOUBLON DÉJÀ EN FILE NE PART PAS.
      La correction empêche d'en FABRIQUER un second ; elle ne défait pas ceux
      qui existent déjà. Sans ce contrôle à l'envoi, l'enquête jumelle créée
      avant la mise à jour partirait quand même. */
$exiger(
    false !== strpos( $sans, 'function acdc_enquete_doublon_de_parcours(' ),
    'Le contrôle à l’envoi a disparu : une enquête jumelle déjà en file partirait, et c’est le second message identique qui fait basculer les précédents en indésirables.'
);
$exiger(
    (bool) preg_match( '/if\s*\(\s*\$this->acdc_enquete_doublon_de_parcours\(\s*\$session\s*\)\s*\)\s*\{\s*continue;/s', $sans ),
    'La boucle d’envoi n’écarte plus les doublons de parcours : la fonction existe mais n’est plus appelée.'
);
$exiger(
    (bool) preg_match( "/'status'\s*=>\s*'doublon_parcours'/", $sans ),
    'L’enquête écartée n’est plus marquée : elle resterait « planifiée » et repartirait au passage suivant.'
);

/* 5. L'e-mail d'enquête : un seul bonjour, un objet lisible.
      Rien de tout cela ne classe seul un message en indésirable — mais c'est la
      signature d'un envoi automatique mal tenu, et cela s'ajoute au reste. */
$exiger(
    ! preg_match( "/\\\$body\s*=\s*'<p>Bonjour '/", $sans ),
    'Le corps de l’e-mail d’enquête resalue le destinataire alors que le gabarit commun le fait déjà : il lit son prénom deux fois.'
);
$exiger(
    (bool) preg_match( '/mb_strlen\(\s*\$subject\s*\)\s*>\s*110/', $sans ),
    'L’objet de l’e-mail d’enquête n’est plus borné : il reprenait le libellé interne de l’envoi, près de deux cents caractères, tronqué par toutes les messageries.'
);

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Enquêtes : une par parcours, à la fin du parcours — %d/%d vertes.\n", $verifs, $verifs );
exit( 0 );
