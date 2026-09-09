<?php
/**
 * Calculs monétaires purs (HT / TVA / TTC) — logique métier extraite et testable.
 *
 * Source unique de vérité pour le calcul des totaux de devis et factures.
 * Centraliser ici empêche la réapparition du bug d'asymétrie devis/facture
 * (Total HT excluant les frais alors que le TTC les incluait — corrigé en 3.25.101).
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class Money {

	/**
	 * Calcule le sous-total HT (tarif + frais annexes), la TVA et le TTC.
	 *
	 * Le sous-total HT inclut TOUJOURS les frais de transport, de repas et les lignes
	 * supplémentaires. La TVA est calculée sur ce sous-total, arrondie à 2 décimales.
	 *
	 * @param float $tarif_ht   Tarif de l'action de formation (HT).
	 * @param float $transport  Frais de transport HT (0 si désactivés).
	 * @param float $meal       Frais de repas/hébergement HT (0 si désactivés).
	 * @param float $extra      Total HT des lignes supplémentaires.
	 * @param float $vat_rate   Taux de TVA en pourcentage (ex. 20.0).
	 * @return array{ht:float,tva:float,ttc:float} Montants arrondis à 2 décimales.
	 */
	public static function invoiceTotals( $tarif_ht, $transport, $meal, $extra, $vat_rate ) {
		$sous_total_ht = round( (float) $tarif_ht + (float) $transport + (float) $meal + (float) $extra, 2 );
		$tva_amount    = round( $sous_total_ht * (float) $vat_rate / 100, 2 );
		$total_ttc     = round( $sous_total_ht + $tva_amount, 2 );

		return array(
			'ht'  => $sous_total_ht,
			'tva' => $tva_amount,
			'ttc' => $total_ttc,
		);
	}

	/**
	 * Somme sécurisée des lignes supplémentaires (chacune {total_ht}).
	 *
	 * @param array $lines Tableau de lignes, chaque ligne peut porter 'total_ht'.
	 * @return float
	 */
	public static function extraLinesTotal( $lines ) {
		$total = 0.0;
		if ( is_array( $lines ) ) {
			foreach ( $lines as $line ) {
				if ( is_array( $line ) && isset( $line['total_ht'] ) ) {
					$total += (float) $line['total_ht'];
				}
			}
		}
		return round( $total, 2 );
	}
}
