<?php
/**
 * Rétention RGPD — durées de conservation et détection des données à purger.
 *
 * Le RGPD (art. 5-1-e « limitation de la conservation ») interdit de garder des données
 * personnelles au-delà de la durée nécessaire. Cette brique calcule, pour une catégorie de
 * données et une date de référence, la **date de coupure** au-delà de laquelle un enregistrement
 * doit être purgé/anonymisé, et partitionne une liste en « à conserver » / « à purger ».
 *
 * Les durées par défaut reflètent les usages d'un organisme de formation (à ajuster selon la
 * politique de conservation propre — surchargées via le filtre `acdc_retention_policy`). Logique
 * pure, sans dépendance à WordPress, testable isolément.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class Retention {

	/**
	 * Durées de conservation par défaut (en années), par catégorie.
	 *
	 * Repères usuels : prospection commerciale 3 ans après dernier contact (reco CNIL) ;
	 * pièces liées à la formation / Qualiopi 5 ans ; pièces comptables (factures, BPF) 10 ans.
	 * Ces valeurs sont un point de départ prudent, pas un conseil juridique.
	 *
	 * @return array<string,int>
	 */
	public static function policyDefaults() {
		return array(
			'prospect'    => 3,   // CRM / prospection non convertie
			'learner'     => 5,   // dossiers apprenants / conventions / émargements
			'evaluation'  => 5,   // questionnaires, évaluations
			'accounting'  => 10,  // factures, avoirs, BPF (obligation comptable)
			'audit'       => 10,  // piste d'audit / journaux
		);
	}

	/**
	 * Nombre d'années de conservation pour une catégorie (repli sur la plus longue si inconnue).
	 *
	 * @param string $category
	 * @param array<string,int>|null $policy Politique personnalisée (sinon défauts).
	 * @return int
	 */
	public static function yearsFor( $category, $policy = null ) {
		$policy = is_array( $policy ) ? array_merge( self::policyDefaults(), $policy ) : self::policyDefaults();
		$category = (string) $category;
		if ( isset( $policy[ $category ] ) && (int) $policy[ $category ] > 0 ) {
			return (int) $policy[ $category ];
		}
		// Catégorie inconnue : on retient la durée la plus longue (prudence : on ne purge pas trop tôt).
		return max( array_map( 'intval', $policy ) );
	}

	/**
	 * Date de coupure (YYYY-MM-DD) : tout enregistrement à cette date ou avant est purgeable.
	 *
	 * @param int    $years    Durée de conservation en années.
	 * @param string $ref_date Date de référence YYYY-MM-DD (par défaut : aujourd'hui, UTC).
	 * @return string Chaîne vide si les paramètres sont invalides.
	 */
	public static function cutoffDate( $years, $ref_date = null ) {
		$years = (int) $years;
		if ( $years < 0 ) {
			return '';
		}
		$ref = self::parseDate( ( null === $ref_date ) ? gmdate( 'Y-m-d' ) : (string) $ref_date );
		if ( null === $ref ) {
			return '';
		}
		return $ref->modify( '-' . $years . ' years' )->format( 'Y-m-d' );
	}

	/**
	 * Vrai si un enregistrement daté $record_date doit être purgé pour une catégorie donnée.
	 *
	 * @param string $record_date YYYY-MM-DD (date de dernière activité / création).
	 * @param string $category
	 * @param string $ref_date    Date de référence (par défaut : aujourd'hui).
	 * @param array<string,int>|null $policy
	 * @return bool
	 */
	public static function isPurgeable( $record_date, $category, $ref_date = null, $policy = null ) {
		$rec = self::parseDate( (string) $record_date );
		if ( null === $rec ) {
			return false;
		}
		$cutoff = self::cutoffDate( self::yearsFor( $category, $policy ), $ref_date );
		if ( '' === $cutoff ) {
			return false;
		}
		// Purgeable si la date de l'enregistrement est <= coupure (hors de la fenêtre de conservation).
		return $rec->format( 'Y-m-d' ) <= $cutoff;
	}

	/**
	 * Partitionne une liste d'enregistrements en « à conserver » / « à purger ».
	 *
	 * Chaque enregistrement doit exposer sa date via $date_key (défaut 'date').
	 *
	 * @param array  $records
	 * @param string $category
	 * @param string $ref_date
	 * @param array<string,int>|null $policy
	 * @param string $date_key
	 * @return array{keep:array,purge:array}
	 */
	public static function partition( array $records, $category, $ref_date = null, $policy = null, $date_key = 'date' ) {
		$keep = array();
		$purge = array();
		foreach ( $records as $rec ) {
			$date = '';
			if ( is_array( $rec ) && isset( $rec[ $date_key ] ) ) {
				$date = (string) $rec[ $date_key ];
			} elseif ( is_string( $rec ) ) {
				$date = $rec;
			}
			if ( '' !== $date && self::isPurgeable( $date, $category, $ref_date, $policy ) ) {
				$purge[] = $rec;
			} else {
				$keep[] = $rec;
			}
		}
		return array(
			'keep'  => $keep,
			'purge' => $purge,
		);
	}

	/**
	 * Parse une date YYYY-MM-DD à minuit (heure UTC), ou null si invalide.
	 *
	 * @param string $date
	 * @return \DateTimeImmutable|null
	 */
	private static function parseDate( $date ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
			return null;
		}
		$d = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new \DateTimeZone( 'UTC' ) );
		if ( false === $d ) {
			return null;
		}
		// Rejette les dates « débordantes » (ex. 2026-02-31 que PHP recale au 03-03).
		if ( $d->format( 'Y-m-d' ) !== $date ) {
			return null;
		}
		return $d;
	}
}
