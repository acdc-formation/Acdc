<?php
/**
 * Une durée, écrite comme un humain la lit.
 *
 * POURQUOI CE FICHIER EXISTE. Une pastille d'émargement affichait
 * « Retard 138746min ». Le nombre était juste — 96 jours, une feuille signée
 * longtemps après la séance — mais personne ne peut lire ça. Une information
 * exacte qu'on ne peut pas lire ne renseigne pas : elle occupe la place de
 * celle qui renseignerait.
 *
 * Quatre écrans affichaient ce nombre brut, chacun avec sa propre concaténation
 * « . 'min' » ou « . ' min' ». Une règle recopiée quatre fois est une règle qui
 * finira par diverger ; celle-ci vit ici, et les quatre l'appellent.
 *
 * IL NE LIT NI LA BASE NI WORDPRESS : il reçoit un nombre de minutes, il rend
 * une phrase. Tout est vérifiable sans site.
 *
 * @package ACDC
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
    exit;
}

final class Duree {

    /**
     * Des minutes, en durée lisible.
     *
     * On ne descend jamais sous la minute et on ne monte jamais au-dessus du
     * jour : au-delà, « 3 mois » suppose de savoir de quels mois il s'agit, et
     * une pastille n'a pas à faire ce calcul-là.
     *
     * Les unités négligeables sont tues : « 2 h » et non « 2 h 00 », « 3 j » et
     * non « 3 j 0 h ». Un zéro affiché fait chercher ce qu'il signifie.
     *
     * @param int|float|string $minutes Nombre de minutes.
     * @return string Ex. « 45 min », « 2 h 15 », « 3 j 4 h », « 96 j ».
     */
    public static function lisible( $minutes ) {
        $minutes = (int) round( (float) $minutes );
        if ( $minutes <= 0 ) {
            return '0 min';
        }
        if ( $minutes < 60 ) {
            return $minutes . ' min';
        }
        if ( $minutes < 1440 ) {
            $heures  = intdiv( $minutes, 60 );
            $restant = $minutes % 60;
            return $restant > 0
                ? $heures . ' h ' . str_pad( (string) $restant, 2, '0', STR_PAD_LEFT )
                : $heures . ' h';
        }
        $jours   = intdiv( $minutes, 1440 );
        $heures  = intdiv( $minutes % 1440, 60 );
        return $heures > 0 ? $jours . ' j ' . $heures . ' h' : $jours . ' j';
    }

    /**
     * Le libellé complet d'une pastille de retard.
     *
     * Rendu vide quand il n'y a pas de retard : une pastille « Retard 0 min »
     * alarme pour rien.
     */
    public static function retard( $minutes ) {
        $minutes = (int) round( (float) $minutes );
        if ( $minutes <= 0 ) {
            return '';
        }
        return 'Retard ' . self::lisible( $minutes );
    }
}
