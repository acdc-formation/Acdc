<?php
/**
 * Le bilan pédagogique et financier des prestations extérieures.
 *
 * DEUX ENTITÉS, DEUX DÉCLARATIONS. L'organisme géré par l'application a son
 * numéro de déclaration d'activité et son BPF. Le formateur qui intervient pour
 * le compte d'AUTRES organismes a le sien, distinct. Ce sont deux personnes
 * morales et deux déclarations : mélanger leurs chiffres ferait déclarer à
 * l'une l'activité de l'autre.
 *
 * Ces prestations vivaient jusqu'ici dans le BPF de l'organisme — c'était juste
 * tant qu'il n'y avait qu'un seul numéro de déclaration. Ça cesse de l'être le
 * jour où une seconde structure est immatriculée, et c'est pourquoi leur prise
 * en compte devient un choix explicite plutôt qu'un fait acquis.
 *
 * CE QUE LE CERFA ATTEND, ET POURQUOI CHAQUE CHIFFRE VA LÀ OÙ IL VA :
 *
 *   Cadre C — les produits, par origine du financement. Une intervention payée
 *   par un autre organisme de formation est de la sous-traitance reçue : elle a
 *   sa propre ligne, distincte des ventes directes aux entreprises.
 *
 *   Cadre E — les personnes qui ont dispensé les heures. Un formateur
 *   indépendant est à lui seul le « personnel » de sa structure : une personne,
 *   et les heures qu'il a réellement animées.
 *
 *   Cadre F — les stagiaires et les heures-stagiaires. Ce ne sont pas les mêmes
 *   heures que le cadre E : une journée de sept heures devant douze personnes
 *   en fait sept au cadre E et quatre-vingt-quatre au cadre F. La confusion
 *   entre les deux est l'erreur la plus courante du BPF.
 *
 *   Cadre G — parmi ces stagiaires, ceux dont la formation a été confiée par un
 *   autre organisme. Ce cadre existe pour que l'administration puisse retirer
 *   les doubles comptes : le donneur d'ordre les déclare aussi. Ne pas le
 *   remplir revient à laisser croire que ces stagiaires n'ont été comptés
 *   qu'une fois.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class ExternalBpf {

	/**
	 * Les prestations d'un exercice.
	 *
	 * Une prestation appartient à l'exercice où elle COMMENCE. Une intervention
	 * d'octobre à janvier se déclare donc entièrement sur l'exercice d'octobre :
	 * la découper au prorata supposerait de savoir combien d'heures sont tombées
	 * de chaque côté, ce que la fiche ne dit pas. Mieux vaut une règle simple et
	 * annoncée qu'une répartition inventée.
	 *
	 * @param array<int,array<string,mixed>> $records
	 * @param string $debut AAAA-MM-JJ
	 * @param string $fin   AAAA-MM-JJ
	 * @return array<int,array<string,mixed>>
	 */
	public static function forPeriod( $records, $debut, $fin ) {
		$out = array();
		foreach ( (array) $records as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$ref = self::refDate( $r );
			if ( '' === $ref || $ref < $debut || $ref > $fin ) {
				continue;
			}
			$out[] = $r;
		}
		return $out;
	}

	/**
	 * Le cadre C : les produits, ventilés par origine du financement.
	 *
	 * Les clés reprennent la numérotation du Cerfa. Une origine non reconnue
	 * tombe en « autres produits » plutôt que d'être perdue : un total qui ne
	 * retombe pas sur ses pieds est un BPF refusé.
	 *
	 * @param array<int,array<string,mixed>> $records
	 * @return array{c1:float,c7:float,c9:float,c10:float,c11:float,total:float}
	 */
	public static function frameC( $records ) {
		$c = array( 'c1' => 0.0, 'c7' => 0.0, 'c9' => 0.0, 'c10' => 0.0, 'c11' => 0.0 );
		foreach ( (array) $records as $r ) {
			$montant = self::num( $r, 'ca_ht' );
			if ( 0.0 === $montant ) {
				continue;
			}
			switch ( self::slug( isset( $r['financement'] ) ? (string) $r['financement'] : '' ) ) {
				case 'organisme de formation':
					$c['c10'] += $montant;   // sous-traitance reçue d'un autre OF
					break;
				case 'entreprise':
					$c['c1'] += $montant;    // vente directe à une entreprise
					break;
				case 'opco':
					$c['c7'] += $montant;    // fonds mutualisés
					break;
				case 'cpf':
					$c['c9'] += $montant;    // personnes à leurs frais / CPF
					break;
				default:
					$c['c11'] += $montant;   // autres produits
			}
		}
		foreach ( $c as $k => $v ) {
			$c[ $k ] = round( $v, 2 );
		}
		$c['total'] = round( array_sum( $c ), 2 );
		return $c;
	}

	/**
	 * Le cadre E : les heures dispensées, et par combien de personnes.
	 *
	 * Une seule personne dès qu'il y a au moins une prestation — le formateur
	 * lui-même. Zéro prestation, zéro personne : déclarer un formateur qui n'a
	 * rien animé fausserait le ratio heures/personne que l'administration lit.
	 *
	 * @param array<int,array<string,mixed>> $records
	 * @return array{nb_internes:int,heures_internes:float,nb_externes:int,heures_externes:float}
	 */
	public static function frameE( $records ) {
		$heures = 0.0;
		$n      = 0;
		foreach ( (array) $records as $r ) {
			$heures += self::num( $r, 'heures_par_stagiaire' );
			$n++;
		}
		return array(
			'nb_internes'     => $n > 0 ? 1 : 0,
			'heures_internes' => round( $heures, 1 ),
			'nb_externes'     => 0,
			'heures_externes' => 0.0,
		);
	}

	/**
	 * Le cadre F : les stagiaires et les heures-stagiaires, par catégorie.
	 *
	 * Les catégories du formulaire de saisie ne portent pas les mêmes noms que
	 * celles du Cerfa : la correspondance se fait ici, une fois, plutôt que
	 * d'être refaite de tête à chaque déclaration. Une catégorie non reconnue
	 * va en « autres stagiaires », jamais à la poubelle.
	 *
	 * @param array<int,array<string,mixed>> $records
	 * @return array<string,int|float>
	 */
	public static function frameF( $records ) {
		$f = array(
			'salaries'     => 0,
			'apprentis'    => 0,
			'demandeurs'   => 0,
			'independants' => 0,
			'particuliers' => 0,
			'autres'       => 0,
			'total'        => 0,
			'heures'       => 0.0,
		);
		foreach ( (array) $records as $r ) {
			$nb = max( 0, (int) self::num( $r, 'nb_stagiaires' ) );
			if ( 0 === $nb ) {
				continue;
			}
			switch ( self::slug( isset( $r['type_public'] ) ? (string) $r['type_public'] : '' ) ) {
				case 'salarie':
					$f['salaries'] += $nb;
					break;
				case 'cfa':
					/* Un stagiaire de centre de formation d'apprentis est un
					   apprenti : c'est la case du Cerfa qui l'attend. */
					$f['apprentis'] += $nb;
					break;
				case 'demandeur emploi':
					$f['demandeurs'] += $nb;
					break;
				case 'independant':
					$f['independants'] += $nb;
					break;
				default:
					$f['autres'] += $nb;
			}
			$f['total']  += $nb;
			$f['heures'] += self::attendedHours( $r );
		}
		$f['heures'] = round( $f['heures'], 1 );
		return $f;
	}

	/**
	 * Le cadre G : ceux dont la formation a été confiée par un autre organisme.
	 *
	 * Sous-ensemble du cadre F, jamais un ajout. C'est ce que le donneur d'ordre
	 * déclare de son côté, et ce cadre permet de le retrancher.
	 *
	 * @param array<int,array<string,mixed>> $records
	 * @return array{nb_stagiaires:int,heures:float}
	 */
	public static function frameG( $records ) {
		$nb     = 0;
		$heures = 0.0;
		foreach ( (array) $records as $r ) {
			if ( 'organisme de formation' !== self::slug( isset( $r['financement'] ) ? (string) $r['financement'] : '' ) ) {
				continue;
			}
			$nb     += max( 0, (int) self::num( $r, 'nb_stagiaires' ) );
			$heures += self::attendedHours( $r );
		}
		return array( 'nb_stagiaires' => $nb, 'heures' => round( $heures, 1 ) );
	}

	/**
	 * Les heures-stagiaires d'une prestation : la durée par stagiaire multipliée
	 * par leur nombre.
	 *
	 * Le total saisi prime quand il existe : une session où tout le monde
	 * n'assiste pas à tout produit légitimement autre chose que le produit des
	 * deux facteurs, et recalculer effacerait ce constat.
	 *
	 * @param array<string,mixed> $r
	 * @return float
	 */
	public static function attendedHours( $r ) {
		$total = self::num( $r, 'heures_total' );
		if ( $total > 0 ) {
			return $total;
		}
		return self::num( $r, 'heures_par_stagiaire' ) * max( 0, (int) self::num( $r, 'nb_stagiaires' ) );
	}

	/* ── Outils ─────────────────────────────────────────────────────────── */

	/** La date qui rattache une prestation à un exercice. */
	private static function refDate( array $r ) {
		foreach ( array( 'start_date', 'end_date' ) as $cle ) {
			if ( ! empty( $r[ $cle ] ) && is_scalar( $r[ $cle ] ) ) {
				return substr( (string) $r[ $cle ], 0, 10 );
			}
		}
		return '';
	}

	/** Comparaison insensible à la casse et aux accents. */
	private static function slug( $valeur ) {
		$v = trim( (string) $valeur );
		if ( '' === $v ) {
			return '';
		}
		$v = function_exists( 'mb_strtolower' ) ? mb_strtolower( $v, 'UTF-8' ) : strtolower( $v );
		$v = strtr(
			$v,
			array(
				'à' => 'a', 'â' => 'a', 'ä' => 'a',
				'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
				'î' => 'i', 'ï' => 'i',
				'ô' => 'o', 'ö' => 'o',
				'ù' => 'u', 'û' => 'u', 'ü' => 'u',
				'ç' => 'c',
			)
		);
		return trim( preg_replace( '/\s+/', ' ', $v ) );
	}

	/** @param array<string,mixed> $row */
	private static function num( $row, $cle ) {
		if ( ! is_array( $row ) || ! isset( $row[ $cle ] ) || ! is_scalar( $row[ $cle ] ) ) {
			return 0.0;
		}
		return (float) str_replace( ',', '.', (string) $row[ $cle ] );
	}
}
