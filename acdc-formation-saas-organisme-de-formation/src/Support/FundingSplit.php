<?php
/**
 * Qui reçoit la facture, et pour quel montant, quand un OPCO prend en charge.
 *
 * Trois situations, une seule règle — et le reste à charge n'est JAMAIS saisi :
 *
 *   prise en charge vide ou 0  → une facture, au client, pour la totalité ;
 *   prise en charge = total    → une facture, au financeur, pour la totalité ;
 *   prise en charge < total    → deux factures liées : la part financeur telle
 *                                qu'elle a été accordée, et le RESTE, calculé.
 *
 * Une prise en charge supérieure au total est refusée : elle ne peut être
 * qu'une faute de frappe, et la laisser passer produirait une facture négative
 * ou un avoir déguisé.
 *
 * Les montants sont manipulés en CENTIMES. En euros flottants, 1200,00 − 799,99
 * peut donner 400,00999999999 : sur une facture, c'est un centime qui ne tombe
 * pas juste et une somme des deux factures qui ne fait pas le total.
 *
 * Sans financeur rattaché, il n'y a pas de prise en charge — quel que soit le
 * montant enregistré. Un montant sans destinataire ne désigne personne.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class FundingSplit {

	public const CLIENT_SEUL    = 'client_seul';
	public const FINANCEUR_SEUL = 'financeur_seul';
	public const DEUX_FACTURES  = 'deux_factures';

	/**
	 * Convertit un montant saisi en centimes entiers.
	 *
	 * Accepte ce que l'on tape vraiment : « 1 200,50 », « 1200.50 », « 1 200,50 € »,
	 * « », null. Une valeur vide ou illisible vaut zéro — pas « inconnu » : c'est
	 * l'appelant qui distingue les deux, par la présence d'un financeur.
	 *
	 * @param mixed $value Montant saisi.
	 * @return int Centimes (peut être négatif : le refus est décidé plus haut).
	 */
	public static function toCents( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (int) round( ( (float) $value ) * 100 );
		}
		$s = trim( (string) $value );
		if ( '' === $s ) {
			return 0;
		}
		/* On ne garde que ce qui fait un nombre. L'espace insécable des
		   milliers français comme le symbole € disparaissent ici. */
		$s = str_replace( array( "\xc2\xa0", "\xe2\x80\xaf", ' ', '€', 'EUR' ), '', $s );
		$s = str_replace( ',', '.', $s );
		/* Un séparateur de milliers en point : « 1.200.50 » → « 1200.50 ». */
		if ( substr_count( $s, '.' ) > 1 ) {
			$last = strrpos( $s, '.' );
			$s    = str_replace( '.', '', substr( $s, 0, $last ) ) . substr( $s, $last );
		}
		if ( ! preg_match( '/-?\d*\.?\d+/', $s, $m ) ) {
			return 0;
		}
		return (int) round( ( (float) $m[0] ) * 100 );
	}

	/** Centimes → euros, deux décimales, sans dérive flottante. */
	public static function toEuros( $cents ) {
		return round( ( (int) $cents ) / 100, 2 );
	}

	/**
	 * Décide la ou les factures à émettre.
	 *
	 * @param mixed $total_ht    Total HT de la prestation (tous frais compris).
	 * @param mixed $pec_amount  Montant pris en charge par le financeur.
	 * @param bool  $has_funder  Un financeur est-il réellement rattaché ?
	 * @return array{ok:bool,error:string,case:string,total_ht:float,funder_ht:float,client_ht:float,invoices:array}
	 */
	public static function plan( $total_ht, $pec_amount, $has_funder = false ) {
		$total_c = self::toCents( $total_ht );
		$pec_c   = $has_funder ? self::toCents( $pec_amount ) : 0;

		if ( $pec_c < 0 ) {
			return self::refus( 'montant_negatif', $total_c );
		}
		if ( $pec_c > $total_c ) {
			return self::refus( 'pec_superieure_au_total', $total_c );
		}

		/* Ni financeur, ni montant : le client paie tout. C'est le cas ordinaire. */
		if ( 0 === $pec_c ) {
			return self::resultat( self::CLIENT_SEUL, $total_c, 0, $total_c, array(
				self::facture( 'client', $total_c, 'totalite' ),
			) );
		}

		/* Prise en charge intégrale : une seule facture, au financeur, qui
		   nomme tout de même le bénéficiaire — le financeur ne paie pas pour
		   lui-même. */
		if ( $pec_c === $total_c ) {
			return self::resultat( self::FINANCEUR_SEUL, $total_c, $total_c, 0, array(
				self::facture( 'funder', $total_c, 'totalite' ),
			) );
		}

		/* Prise en charge partielle : le reste est une SOUSTRACTION, jamais une
		   saisie. Le financeur d'abord — c'est lui qui conditionne le reste. */
		$reste_c = $total_c - $pec_c;
		return self::resultat( self::DEUX_FACTURES, $total_c, $pec_c, $reste_c, array(
			self::facture( 'funder', $pec_c, 'part_financeur' ),
			self::facture( 'client', $reste_c, 'reste_a_charge' ),
		) );
	}

	private static function facture( $billed_to, $cents, $share ) {
		return array(
			'billed_to' => $billed_to,
			'amount_ht' => self::toEuros( $cents ),
			'share'     => $share,
		);
	}

	private static function resultat( $case, $total_c, $funder_c, $client_c, $invoices ) {
		return array(
			'ok'        => true,
			'error'     => '',
			'case'      => $case,
			'total_ht'  => self::toEuros( $total_c ),
			'funder_ht' => self::toEuros( $funder_c ),
			'client_ht' => self::toEuros( $client_c ),
			'invoices'  => $invoices,
		);
	}

	private static function refus( $error, $total_c ) {
		return array(
			'ok'        => false,
			'error'     => $error,
			'case'      => '',
			'total_ht'  => self::toEuros( $total_c ),
			'funder_ht' => 0.0,
			'client_ht' => 0.0,
			'invoices'  => array(),
		);
	}

	/** Le message à montrer quand la répartition est refusée. */
	public static function errorMessage( $error, $total_ht ) {
		$total = number_format( (float) $total_ht, 2, ',', ' ' );
		if ( 'pec_superieure_au_total' === $error ) {
			return 'Le montant pris en charge dépasse le total de la prestation (' . $total . ' € HT). Corrigez le montant : il ne peut pas y avoir de reste à charge négatif.';
		}
		if ( 'montant_negatif' === $error ) {
			return 'Le montant pris en charge ne peut pas être négatif.';
		}
		return 'Répartition impossible entre le financeur et le client.';
	}
}
