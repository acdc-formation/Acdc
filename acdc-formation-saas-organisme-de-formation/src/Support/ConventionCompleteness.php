<?php
/**
 * Ce qui manque à une convention pour être opposable.
 *
 * POURQUOI CE FICHIER EXISTE. Une convention a été validée sans financeur. Rien
 * ne l'a signalé — ni à la validation, ni au moment où le financeur aurait servi.
 * La chaîne entière s'est tue : convention sans financeur, puis enquête
 * financeur sans destinataire, puis statut « envoyée » écrit quand même. Un
 * champ oublié est devenu un dossier Qualiopi incomplet, sans un seul signal.
 *
 * DEUX NIVEAUX, ET C'EST VOULU. David dit « ils sont tous importants », et il a
 * raison sur le fond. Mais une alerte qui bloque sur un détail finit par être
 * contournée, et le jour où elle bloque sur l'essentiel, personne ne la lit
 * plus. On distingue donc :
 *
 *   — CE QUI BLOQUE : ce sans quoi le document n'est opposable ni à un OPCO ni
 *     à un auditeur. La validation refuse, et dit lesquels manquent.
 *   — CE QUI SIGNALE : tout le reste. Affiché, jamais bloquant.
 *
 * LE FINANCEUR NE BLOQUE QUE S'IL Y A FINANCEMENT EXTERNE. Une convention payée
 * directement par l'entreprise n'a pas de financeur, et bloquer là-dessus serait
 * absurde — c'est le genre de faux barrage qui apprend à passer outre.
 *
 * Ce fichier ne lit ni la base ni WordPress : il reçoit un tableau et rend deux
 * listes. Tout est donc vérifiable sans site.
 *
 * @package ACDC
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
    exit;
}

final class ConventionCompleteness {

    /** Ce sans quoi la convention n'est pas opposable. */
    public const BLOQUANTS = array(
        'formation_id'        => 'la formation',
        'company_id'          => 'le commanditaire',
        'start_date'          => 'la date de début',
        'end_date'            => 'la date de fin',
        'formation_address'   => 'l’adresse du lieu de formation',
        'formation_city'      => 'la ville du lieu de formation',
        'price_ht'            => 'le tarif',
        'vat_rate'            => 'le taux de TVA',
        'trainer_id'          => 'le formateur',
        'learner_ids'         => 'au moins un apprenant',
    );

    /** Ce qui manque sans empêcher : affiché, jamais bloquant. */
    public const SIGNALES = array(
        'objectives_text'      => 'les objectifs pédagogiques',
        'seances_schedule_json'=> 'le déroulé des séances',
        'accessibility_handicap' => 'l’accessibilité aux personnes en situation de handicap',
        'implementation_followup_evaluation' => 'les modalités de suivi et d’évaluation',
        'payment_terms'        => 'les conditions de règlement',
    );

    /**
     * @param array $convention Les champs de la convention, tels que saisis.
     * @return array{bloquants:string[],signales:string[],ok:bool}
     */
    public static function verifier( array $convention ) {
        $bloquants = array();
        $signales  = array();

        foreach ( self::BLOQUANTS as $cle => $libelle ) {
            if ( ! self::rempli( $convention, $cle ) ) {
                $bloquants[] = $libelle;
            }
        }

        /* Le financeur : exigé UNIQUEMENT si le dossier annonce un financement
           externe. C'est la seule règle conditionnelle, et elle évite le faux
           barrage sur une convention payée par l'entreprise elle-même. */
        if ( self::financementExterne( $convention ) && ! self::rempli( $convention, 'funder_id' ) ) {
            $bloquants[] = 'le financeur (le dossier annonce un financement externe)';
        }

        foreach ( self::SIGNALES as $cle => $libelle ) {
            if ( ! self::rempli( $convention, $cle ) ) {
                $signales[] = $libelle;
            }
        }

        return array(
            'bloquants' => $bloquants,
            'signales'  => $signales,
            'ok'        => empty( $bloquants ),
        );
    }

    /** La phrase de refus : elle nomme ce qui manque, sinon elle ne sert à rien. */
    public static function messageRefus( array $bloquants ) {
        if ( empty( $bloquants ) ) {
            return '';
        }
        return 'Convention non enregistrée — il manque : ' . self::enumerer( $bloquants ) . '.';
    }

    /** La phrase d'avertissement, qui n'empêche rien. */
    public static function messageSignale( array $signales ) {
        if ( empty( $signales ) ) {
            return '';
        }
        return 'Convention enregistrée. À compléter avant envoi : ' . self::enumerer( $signales ) . '.';
    }

    private static function enumerer( array $items ) {
        if ( 1 === count( $items ) ) {
            return $items[0];
        }
        $dernier = array_pop( $items );
        return implode( ', ', $items ) . ' et ' . $dernier;
    }

    /**
     * Un champ « rempli » : ni absent, ni vide, ni un zéro d'identifiant.
     *
     * « 0 » est vide pour un identifiant et plein pour un tarif. On distingue
     * donc les deux : sans quoi une formation gratuite serait déclarée
     * incomplète, et un formateur non choisi passerait pour choisi.
     */
    private static function rempli( array $convention, $cle ) {
        if ( ! array_key_exists( $cle, $convention ) ) {
            return false;
        }
        $valeur = $convention[ $cle ];
        if ( is_array( $valeur ) ) {
            return ! empty( array_filter( $valeur ) );
        }
        $valeur = trim( (string) $valeur );
        if ( '' === $valeur ) {
            return false;
        }
        if ( '_id' === substr( $cle, -3 ) ) {
            return 0 !== (int) $valeur;
        }
        /* « learner_ids » est une LISTE stockée en chaîne — « 7,8 ». Le test des
           identifiants ne s'y applique pas, mais une chaîne réduite à des zéros
           et des virgules ne désigne personne. */
        if ( '_ids' === substr( $cle, -4 ) ) {
            return '' !== trim( preg_replace( '/[0,\s]/', '', $valeur ) );
        }
        return true;
    }

    /**
     * Le dossier annonce-t-il un financement externe ?
     *
     * On lit l'intention déclarée, pas la présence du financeur : c'est
     * justement l'absence du financeur qu'on cherche à détecter.
     */
    private static function financementExterne( array $convention ) {
        $declare = isset( $convention['public_funding'] ) ? trim( (string) $convention['public_funding'] ) : '';
        if ( '' === $declare ) {
            return false;
        }
        $normalise = strtolower( $declare );
        foreach ( array( 'non', 'aucun', 'entreprise', 'direct', 'fonds propres', 'particulier' ) as $sans ) {
            if ( false !== strpos( $normalise, $sans ) ) {
                return false;
            }
        }
        return true;
    }
}
