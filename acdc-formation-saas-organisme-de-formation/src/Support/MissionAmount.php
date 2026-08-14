<?php
/**
 * Heures, taux horaire et montant d'une mission de sous-traitance.
 *
 * Trois nombres liés par une multiplication, dont on connaît rarement les
 * trois. Le calcul n'allait que dans un sens — heures × taux → montant — alors
 * qu'un forfait se négocie le plus souvent à l'envers : « 800 € pour deux
 * jours », et c'est le taux horaire qui se déduit. Le champ montant existait,
 * personne ne le lisait ; une mission saisie au forfait partait avec un taux à
 * zéro, et le contrat imprimait « 0,00 €/H ».
 *
 * LE MONTANT FAIT FOI QUAND IL EST SAISI. Ce n'est pas un détail d'arrondi :
 * 14 h à 57,14 €/H font 799,96 €, pas 800 €. C'est le montant qui est
 * contractuel — le taux horaire n'en est que la lecture ramenée à l'heure, et
 * c'est lui qu'on arrondit, jamais la somme due.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class MissionAmount {

	/**
	 * Complète le triplet à partir de ce qui a été saisi.
	 *
	 * @param mixed $hours  Heures dispensées.
	 * @param mixed $rate   Taux horaire HT.
	 * @param mixed $amount Montant HT.
	 * @return array{hours:float,rate:float,amount:float}
	 */
	public static function reconcile( $hours, $rate, $amount ) {
		$hours  = self::toFloat( $hours );
		$rate   = self::toFloat( $rate );
		$amount = self::toFloat( $amount );

		if ( $hours < 0 ) { $hours = 0.0; }
		if ( $rate < 0 ) { $rate = 0.0; }
		if ( $amount < 0 ) { $amount = 0.0; }

		/* Un forfait saisi commande : le taux se déduit. */
		if ( $amount > 0 && $hours > 0 ) {
			return array(
				'hours'  => round( $hours, 2 ),
				'rate'   => round( $amount / $hours, 2 ),
				'amount' => round( $amount, 2 ),
			);
		}

		/* Sinon, la multiplication ordinaire. */
		if ( $hours > 0 && $rate > 0 ) {
			return array(
				'hours'  => round( $hours, 2 ),
				'rate'   => round( $rate, 2 ),
				'amount' => round( $hours * $rate, 2 ),
			);
		}

		/* Un montant sans heures reste un montant : on ne l'efface pas sous
		   prétexte qu'on ne sait pas le ramener à l'heure. */
		return array(
			'hours'  => round( $hours, 2 ),
			'rate'   => round( $rate, 2 ),
			'amount' => round( $amount, 2 ),
		);
	}

	/** Lit « 1 200,50 », « 1200.50 », « 800 € », vide. */
	private static function toFloat( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (float) $value;
		}
		$s = trim( (string) $value );
		if ( '' === $s ) {
			return 0.0;
		}
		$s = str_replace( array( "\xc2\xa0", "\xe2\x80\xaf", ' ', '€', 'EUR' ), '', $s );
		$s = str_replace( ',', '.', $s );
		if ( substr_count( $s, '.' ) > 1 ) {
			$last = strrpos( $s, '.' );
			$s    = str_replace( '.', '', substr( $s, 0, $last ) ) . substr( $s, $last );
		}
		if ( ! preg_match( '/-?\d*\.?\d+/', $s, $m ) ) {
			return 0.0;
		}
		return (float) $m[0];
	}
}
