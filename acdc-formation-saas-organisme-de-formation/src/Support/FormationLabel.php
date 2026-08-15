<?php
/**
 * Le nom sous lequel une formation se présente à l'écran.
 *
 * Relevé en recette, sur la modale de création de quiz : « il y a aucun moyen
 * de différencier deux formations avec le même nom ». C'est exact, et ce n'est
 * pas un hasard : une même formation existe en présentiel ET en distanciel,
 * deux fiches distinctes, deux tarifs, deux programmes — et le même intitulé.
 * Un menu déroulant qui n'écrit que l'intitulé propose donc deux fois la même
 * ligne, et celui qui choisit tire à pile ou face.
 *
 * Ce n'est pas une gêne d'ergonomie. Rattacher un quiz, une séance, un dossier
 * ou une convention à la mauvaise fiche, c'est produire une preuve Qualiopi qui
 * désigne une modalité que l'apprenant n'a pas suivie.
 *
 * LE VRAI DÉFAUT ÉTAIT AILLEURS. Le plugin savait déjà écrire la modalité — il
 * le faisait à cinq endroits. Mais il existait QUATRE fonctions concurrentes
 * pour fabriquer ce libellé, chacune avec sa propre idée :
 *
 *   acdc_formation_labelled()        « 1.0 — Titre »            (pièces qui sortent)
 *   format_formation_option_label()  « 12 Titre »               (8 sélecteurs)
 *   format_qz_formation_label()      « CODE | Titre (Présentiel) » (5 sélecteurs)
 *   build_formation_option_label()   « 12 — Titre — Distanciel — 900 € » (2 sélecteurs)
 *
 * … et trois sélecteurs de plus qui n'écrivaient que le titre nu. Une même
 * question posée à sept endroits recevait sept réponses différentes. C'est la
 * famille de défauts la plus tenace du plugin : une règle recopiée au lieu
 * d'être appelée, qui diverge ensuite sans que personne ne s'en aperçoive.
 *
 * La règle vit désormais ici, seule, et les quatre fonctions l'appellent. Elle
 * ne dépend d'aucune base et d'aucun WordPress : on peut donc la vérifier.
 *
 * L'IDENTIFIANT TECHNIQUE N'Y FIGURE PAS, volontairement. Il a été proposé puis
 * écarté : la modalité suffit à distinguer les deux fiches, et un numéro de
 * ligne de base de données affiché à l'utilisateur est du bruit qu'il faudrait
 * ensuite expliquer. Le repère de famille (« 1.0 », « 1.1 ») reste réservé aux
 * pièces qui sortent de l'application, où il tient lieu de référence.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class FormationLabel {

	/**
	 * Le libellé complet d'une formation : « CODE | Intitulé (Modalité) ».
	 *
	 * Chaque segment ne s'écrit que s'il existe. Une fiche sans code ni
	 * modalité rend son seul intitulé — jamais une chaîne décorée de
	 * séparateurs vides, qui laisserait croire à une donnée manquante là où il
	 * n'y a rien à dire.
	 *
	 * @param string $title    Intitulé de la formation.
	 * @param string $modality Modalité (« Présentiel », « Distanciel », …).
	 * @param string $code     Référence saisie par l'organisme, souvent vide.
	 * @return string
	 */
	public static function compose( $title, $modality = '', $code = '' ) {
		$title    = self::clean( $title );
		$modality = self::modality( $modality );
		$code     = self::clean( $code );

		if ( '' === $title ) {
			/* Une fiche sans intitulé existe (import interrompu, brouillon).
			   Mieux vaut annoncer sa modalité que rendre une ligne vide, qui
			   se lirait comme une option absente. */
			return '' !== $modality ? '(' . $modality . ')' : '';
		}

		$out = $title;
		if ( '' !== $code && ! self::startsWith( $title, $code ) ) {
			$out = $code . ' | ' . $out;
		}
		if ( '' !== $modality && ! self::mentions( $title, $modality ) ) {
			$out .= ' (' . $modality . ')';
		}
		return $out;
	}

	/**
	 * Le même libellé, construit depuis une ligne de la table des formations.
	 *
	 * Les écrans manipulent des `stdClass` issus de $wpdb, dont les colonnes
	 * varient selon la requête : certaines ne ramènent que `title`. On lit donc
	 * ce qui est là, sans jamais supposer.
	 *
	 * L'ORDRE DES CLÉS EST LA SEULE CHOSE QUI COMPTE ICI. Une ligne de séance
	 * porte DEUX titres : le sien (`title`, « Groupe A — mars ») et celui de la
	 * formation jointe (`formation_title`). Lire `title` d'abord rendrait le nom
	 * de la séance dans une colonne « Formation » — une confusion silencieuse,
	 * plus trompeuse que la case vide qu'on voulait remplir. La colonne
	 * préfixée, quand elle existe, prime donc toujours.
	 *
	 * @param object|array|null $formation
	 * @return string
	 */
	public static function fromRow( $formation ) {
		if ( is_object( $formation ) ) {
			$formation = get_object_vars( $formation );
		}
		if ( ! is_array( $formation ) ) {
			return '';
		}
		return self::compose(
			self::pick( $formation, array( 'formation_title', 'title' ) ),
			self::pick( $formation, array( 'formation_modality', 'modality' ) ),
			self::pick( $formation, array( 'formation_code', 'code' ) )
		);
	}

	/**
	 * Le libellé d'une formation LUE PAR JOINTURE, depuis la ligne d'un autre
	 * objet — une séance, un dossier, une passation.
	 *
	 * Il ne lit QUE les colonnes préfixées `formation_*`, jamais `title` ni
	 * `modality` nus. C'est volontairement plus strict que fromRow() : la ligne
	 * d'une séance porte son propre `title`, et le moindre repli sur ce nom-là
	 * écrirait l'intitulé de la séance dans une colonne « Formation ». Une case
	 * vide se voit ; un nom plausible mais faux ne se voit pas.
	 *
	 * Rend une chaîne vide quand la jointure n'a rien ramené — à l'appelant de
	 * décider ce qu'il écrit alors.
	 *
	 * @param object|array|null $row
	 * @return string
	 */
	public static function fromJoinedRow( $row ) {
		if ( is_object( $row ) ) {
			$row = get_object_vars( $row );
		}
		if ( ! is_array( $row ) ) {
			return '';
		}
		$title = self::pick( $row, array( 'formation_title' ) );
		if ( '' === $title ) {
			return '';
		}
		return self::compose(
			$title,
			self::pick( $row, array( 'formation_modality' ) ),
			self::pick( $row, array( 'formation_code' ) )
		);
	}

	/**
	 * Le libellé précédé d'un repère déjà calculé par l'appelant.
	 *
	 * Deux écrans affichent depuis toujours un numéro devant l'intitulé (« 12 »
	 * pour une formation de base, « 3.1 » pour une variante). Le leur retirer
	 * en passant serait une perte silencieuse : on le remet devant, et la
	 * modalité vient s'ajouter derrière comme partout ailleurs.
	 *
	 * @param string $prefix    Repère déjà composé par l'appelant.
	 * @param string $title
	 * @param string $modality
	 * @param string $code
	 * @return string
	 */
	public static function prefixed( $prefix, $title, $modality = '', $code = '' ) {
		$prefix = self::clean( $prefix );
		$label  = self::compose( $title, $modality, $code );
		if ( '' === $prefix ) {
			return $label;
		}
		if ( '' === $label ) {
			return $prefix;
		}
		if ( self::startsWith( $label, $prefix ) ) {
			return $label;
		}
		return $prefix . ' ' . $label;
	}

	/**
	 * La modalité telle qu'on l'écrit : première lettre en capitale, le reste
	 * tel quel.
	 *
	 * Le champ est libre en base et arrive sous toutes les formes — « PRESENTIEL »
	 * crié par un import Excel, « présentiel » saisi à la main. Les deux
	 * désignent la même chose et doivent se lire pareil, sinon la liste donne
	 * l'illusion de deux modalités différentes — exactement la confusion qu'on
	 * cherche à lever.
	 *
	 * @param string $modality
	 * @return string
	 */
	public static function modality( $modality ) {
		$modality = self::clean( $modality );
		if ( '' === $modality ) {
			return '';
		}
		/* Un sigle reste un sigle : « FOAD », « AFEST » ne se recapitalisent
		   pas en « Foad ». On ne retouche que les mots écrits tout en capitales
		   de plus de cinq lettres, qui sont des cris d'import, pas des sigles. */
		if ( $modality === self::upper( $modality ) && self::length( $modality ) > 5 ) {
			$modality = self::upperFirst( self::lower( $modality ) );
		} else {
			$modality = self::upperFirst( $modality );
		}
		return $modality;
	}

	/* ── Outils ─────────────────────────────────────────────────────────── */

	private static function clean( $value ) {
		if ( is_scalar( $value ) ) {
			return trim( preg_replace( '/\s+/u', ' ', (string) $value ) );
		}
		return '';
	}

	/**
	 * @param array<string,mixed> $row
	 * @param string[]            $keys
	 */
	private static function pick( array $row, array $keys ) {
		foreach ( $keys as $key ) {
			if ( isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) {
				return (string) $row[ $key ];
			}
		}
		return '';
	}

	/** L'intitulé porte-t-il déjà la modalité ? On ne l'écrit pas deux fois. */
	private static function mentions( $title, $modality ) {
		if ( '' === $modality ) {
			return false;
		}
		return false !== strpos( self::lower( $title ), self::lower( $modality ) );
	}

	private static function startsWith( $haystack, $needle ) {
		if ( '' === $needle ) {
			return false;
		}
		return 0 === strpos( self::lower( $haystack ), self::lower( $needle ) );
	}

	private static function lower( $value ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	private static function upper( $value ) {
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $value, 'UTF-8' ) : strtoupper( $value );
	}

	private static function length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	private static function upperFirst( $value ) {
		if ( '' === $value ) {
			return '';
		}
		if ( function_exists( 'mb_substr' ) ) {
			return self::upper( mb_substr( $value, 0, 1, 'UTF-8' ) ) . mb_substr( $value, 1, null, 'UTF-8' );
		}
		return ucfirst( $value );
	}
}
