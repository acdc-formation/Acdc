<?php
/**
 * Les demi-journées d'une séance, quand personne ne les a saisies.
 *
 * POURQUOI. La carte « Heures de formation dispensées » annonçait 16:00:00 pour
 * deux journées de formation qui en font 14. Le calcul n'était pas en cause : il
 * lit déjà le planning détaillé d'une séance et n'additionne que les créneaux
 * réels. Il ne retombe sur « début → fin » — la journée entière, PAUSE DÉJEUNER
 * COMPRISE — que si ce planning est vide.
 *
 * Or aucun formulaire n'envoie ce planning. Une séance née d'une convention le
 * reçoit ; une séance créée à la main naît sans, et TOUT ce qui compte des
 * heures retombe alors sur la journée pleine : la carte, le BPF, les statistiques
 * du formateur. Deux fois huit heures au lieu de quatre fois trois heures trente.
 *
 * CE QU'ON S'INTERDIT. Inventer des horaires. On ne fabrique pas une pause qui
 * n'a pas été déclarée : on COUPE la plage annoncée là où l'organisme a dit que
 * sa pause se situe, et seulement si la plage l'enjambe réellement. Une séance
 * de 9 h à 12 h reste une seule demi-journée ; une séance de 14 h à 17 h aussi.
 * Une séance de 9 h à 17 h en fait deux — et perd l'heure de midi, qui n'a pas
 * été dispensée.
 *
 * Les bornes restent celles de la SÉANCE : si elle commence à 8 h 30, le matin
 * commence à 8 h 30, pas à l'horaire par défaut. On ne corrige que la coupure.
 *
 * @package ACDC
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
    exit;
}

final class HalfDaySplit {

    /** La pause de midi, telle que l'organisme la déclare par défaut. */
    public const PAUSE_DEBUT_DEFAUT = '12:30';
    public const PAUSE_FIN_DEFAUT   = '13:30';

    /**
     * Découpe une plage en demi-journées.
     *
     * @param string $start_at   « 2026-05-11 09:00:00 ».
     * @param string $end_at     « 2026-05-11 17:00:00 ».
     * @param string $pause_debut « 12:30 ».
     * @param string $pause_fin   « 13:30 ».
     * @return array Liste de créneaux { start_date, start_at, end_at, half }.
     */
    public static function decouper( $start_at, $end_at, $pause_debut = self::PAUSE_DEBUT_DEFAUT, $pause_fin = self::PAUSE_FIN_DEFAUT ) {
        $debut = self::instant( $start_at );
        $fin   = self::instant( $end_at );
        if ( ! $debut || ! $fin || $fin <= $debut ) {
            return array();
        }

        $jour = gmdate( 'Y-m-d', $debut );

        /* Une séance à cheval sur deux jours n'est pas une journée de formation :
           on ne la découpe pas, on la rend telle quelle plutôt que d'inventer. */
        if ( gmdate( 'Y-m-d', $fin ) !== $jour ) {
            return array( self::creneau( $jour, $debut, $fin, 'full' ) );
        }

        $pause_d = self::instantSurJour( $jour, $pause_debut );
        $pause_f = self::instantSurJour( $jour, $pause_fin );
        if ( ! $pause_d || ! $pause_f || $pause_f <= $pause_d ) {
            return array( self::creneau( $jour, $debut, $fin, 'full' ) );
        }

        /* La plage n'enjambe pas la pause : une seule demi-journée, et l'on ne
           préjuge pas de laquelle — c'est l'heure qui le dit. */
        if ( $fin <= $pause_d ) {
            return array( self::creneau( $jour, $debut, $fin, 'am' ) );
        }
        if ( $debut >= $pause_f ) {
            return array( self::creneau( $jour, $debut, $fin, 'pm' ) );
        }

        /* Elle l'enjambe : deux demi-journées, bornées par la SÉANCE. */
        $creneaux = array();
        if ( $debut < $pause_d ) {
            $creneaux[] = self::creneau( $jour, $debut, $pause_d, 'am' );
        }
        if ( $fin > $pause_f ) {
            $creneaux[] = self::creneau( $jour, $pause_f, $fin, 'pm' );
        }
        /* Une plage entièrement contenue dans la pause ne se découpe pas. */
        return $creneaux ? $creneaux : array( self::creneau( $jour, $debut, $fin, 'full' ) );
    }

    /** Les minutes réellement dispensées — la pause n'en fait pas partie. */
    public static function minutes( array $creneaux ) {
        $total = 0;
        foreach ( $creneaux as $c ) {
            $d = self::instant( $c['start_at'] ?? '' );
            $f = self::instant( $c['end_at'] ?? '' );
            if ( $d && $f && $f > $d ) {
                $total += (int) round( ( $f - $d ) / 60 );
            }
        }
        return $total;
    }

    private static function creneau( $jour, $debut, $fin, $moitie ) {
        return array(
            'start_date' => $jour,
            'start_at'   => gmdate( 'Y-m-d H:i:s', $debut ),
            'end_at'     => gmdate( 'Y-m-d H:i:s', $fin ),
            'half'       => $moitie,
        );
    }

    /**
     * Un horodatage lu SANS fuseau.
     *
     * Ces chaînes sont des heures de calendrier — « la séance commence à 9 h » —
     * et non des instants absolus. Les lire dans un fuseau les décalerait, et
     * c'est le défaut qui a coûté une journée entière à ce projet le 16 août :
     * une heure écrite dans un repère, relue dans un autre.
     */
    private static function instant( $valeur ) {
        $valeur = trim( (string) $valeur );
        if ( '' === $valeur ) {
            return 0;
        }
        $ts = strtotime( $valeur . ' UTC' );
        return $ts ? (int) $ts : 0;
    }

    private static function instantSurJour( $jour, $heure ) {
        $heure = trim( (string) $heure );
        if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', $heure, $m ) ) {
            return 0;
        }
        return self::instant( $jour . ' ' . sprintf( '%02d:%02d:00', (int) $m[1], (int) $m[2] ) );
    }
}
