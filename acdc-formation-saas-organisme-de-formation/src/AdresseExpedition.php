<?php
/**
 * L'adresse qui EXPÉDIE — une seule décision, un seul endroit.
 *
 * POURQUOI CE FICHIER EXISTE. Le 17 août, tous les e-mails du plugin sont
 * partis en indésirables, d'un coup, y compris ceux qui arrivaient la veille.
 * L'authentification du domaine n'y était pour rien : SPF, DKIM et DMARC
 * étaient en place, 9,6/10 chez mail-tester, serveur hors listes noires.
 *
 * LA RACINE. En 3.25.290, un lot bien intentionné a fait lire la fiche identité
 * de l'organisme partout où une adresse était écrite en dur — dans les
 * conventions, les factures, et les e-mails. C'était juste pour tout, SAUF pour
 * une chose : l'en-tête « From: ». Le repli codé en dur qu'il a supprimé,
 * « contact@acdc-formation.com », n'était pas une négligence : c'est l'adresse
 * du DOMAINE SIGNÉ. Dès que le « From: » est passé sur l'adresse de contact de
 * la fiche, l'alignement DMARC est tombé, et tout a basculé.
 *
 * LA DISTINCTION QU'IL FAUT TENIR. Une adresse de CONTACT est une préférence :
 * elle s'affiche, elle change quand l'organisme change d'e-mail, elle vit dans
 * une fiche. Une adresse d'EXPÉDITION est une donnée d'INFRASTRUCTURE : elle
 * est liée aux enregistrements DNS du domaine, et la changer sans changer le
 * DNS ne dégrade pas un peu la délivrabilité — elle l'annule.
 *
 * Trois modules composaient leur « From: » chacun de son côté, avec trois
 * chaînes de repli différentes — et deux d'entre elles finissaient sur
 * « admin_email », qui est très souvent une adresse personnelle chez un
 * fournisseur grand public. Le pire cas possible pour un alignement de domaine.
 * Ce fichier est la porte unique : il n'y a plus qu'un endroit à relire.
 *
 * Il ne lit ni la base ni WordPress : il reçoit un tableau et rend une adresse.
 * Tout est donc vérifiable sans site.
 *
 * @package ACDC
 */

namespace ACDC;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
    exit;
}

final class AdresseExpedition {

    /**
     * Le domaine dont les enregistrements DNS sont posés et vérifiés.
     * Changer cette valeur SANS changer le DNS renvoie tout en indésirables.
     */
    public const DEFAUT = 'contact@acdc-formation.com';

    /**
     * L'adresse du « From: ».
     *
     * Un seul réglage peut la remplacer : « sender_email » des paramètres
     * marketing, qui est explicitement une adresse d'envoi. Tout le reste —
     * fiche identité, fiche marque, adresse d'administration du site — est une
     * adresse de CONTACT et n'a rien à faire dans un « From: ».
     *
     * @param array  $marketing Les paramètres marketing (acdc_of_marketing_settings).
     * @param string $defaut    Le repli, pour les tests et les autres domaines.
     * @return string
     */
    public static function resoudre( array $marketing, $defaut = self::DEFAUT ) {
        $configuree = isset( $marketing['sender_email'] ) ? trim( (string) $marketing['sender_email'] ) : '';
        if ( '' !== $configuree && self::valide( $configuree ) ) {
            return $configuree;
        }
        $defaut = trim( (string) $defaut );
        return self::valide( $defaut ) ? $defaut : '';
    }

    /**
     * Le « From: » est-il sur le domaine attendu ?
     *
     * Sert à SIGNALER une dérive, jamais à bloquer un envoi : un e-mail mal
     * aligné arrive quand même parfois, un e-mail non envoyé jamais.
     */
    public static function alignee( $adresse, $domaine_attendu ) {
        $domaine = self::domaine( $adresse );
        $attendu = strtolower( trim( (string) $domaine_attendu ) );
        if ( '' === $domaine || '' === $attendu ) {
            return false;
        }
        /* Un sous-domaine d'envoi (« mail.exemple.fr ») reste aligné avec
           « exemple.fr » : DMARC l'admet en relâché, qui est le réglage usuel. */
        return $domaine === $attendu || substr( $domaine, -strlen( '.' . $attendu ) ) === '.' . $attendu;
    }

    /** Le domaine d'une adresse, en minuscules, ou '' si elle n'en a pas. */
    public static function domaine( $adresse ) {
        $adresse = trim( (string) $adresse );
        $at      = strrpos( $adresse, '@' );
        if ( false === $at ) {
            return '';
        }
        return strtolower( substr( $adresse, $at + 1 ) );
    }

    /** Une validation minimale, sans dépendre de WordPress. */
    private static function valide( $adresse ) {
        return (bool) filter_var( $adresse, FILTER_VALIDATE_EMAIL );
    }
}
