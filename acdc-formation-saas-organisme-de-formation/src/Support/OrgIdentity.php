<?php
/**
 * L'identité de l'organisme, à un seul endroit.
 *
 * CE QUE CE FICHIER RÉPARE. « Je veux que lorsque je changerai le SIRET, le
 * NDA, le nom du dirigeant, tout soit changé partout. » Ce n'était pas le cas,
 * et de trois manières différentes.
 *
 *   1. DEUX FICHES POUR UNE SEULE IDENTITÉ. « acdc_of_branding » et
 *      « acdc_of_company_profile » portaient chacune un SIRET, un NDA, une
 *      raison sociale, une adresse. La seconde se pré-remplissait depuis la
 *      première à la toute première ouverture, puis les deux divergeaient sans
 *      que rien ne le signale.
 *
 *   2. DES ÉCRANS QUI LISAIENT UNE CLÉ INEXISTANTE. Le code demandait
 *      « siret », « nda », « nda_number », « company_name », « email »,
 *      « phone », « website », « zip » ; la fiche enregistre
 *      « siret_identification », « activity_declaration_number »,
 *      « enterprise », « enterprise_contact_email »… La condition échouait
 *      donc TOUJOURS et c'était la valeur de repli, écrite en dur, qui
 *      s'affichait. Cinquante-deux lectures dans sept fichiers. Un document
 *      qui affiche une identité sans l'avoir lue est plus dangereux qu'un
 *      document vide : il a l'air juste.
 *
 *   3. DES DOCUMENTS QUI NE LISAIENT RIEN DU TOUT. La facture, le programme de
 *      formation, le PDF de résultat de quiz, le recueil du besoin portaient
 *      le SIRET et le NDA en toutes lettres dans le code.
 *
 * LA RÈGLE DES CHAMPS VIDES. Un champ vidé ne réapparaît pas : ni valeur
 * historique de repli, ni étiquette orpheline. « Siret : » suivi de rien est
 * pire que rien — sur un document officiel, une mention tronquée se remarque,
 * une mention absente se corrige. C'est le choix explicite de l'exploitant :
 * un oubli de saisie doit se voir, pas se maquiller en ancienne identité.
 *
 * Ni base de données ni WordPress ici : deux tableaux entrent, une identité
 * normalisée sort. C'est ce qui rend la règle vérifiable.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class OrgIdentity {

	/**
	 * Les clés acceptées pour chaque champ, dans l'ordre de priorité.
	 *
	 * La fiche entreprise fait foi ; la fiche marque ne sert que de secours.
	 * Les noms hérités (« nda_number », « company_name »…) figurent ici pour
	 * qu'une fiche ancienne, ou recopiée d'un autre écran, reste lisible — pas
	 * pour encourager à les réécrire.
	 *
	 * @var array<string,string[]>
	 */
	const CHAMPS = array(
		'raison_sociale'  => array( 'enterprise', 'company_name' ),
		'forme_juridique' => array( 'legal_form' ),
		'siret'           => array( 'siret_identification', 'siret' ),
		'nda'             => array( 'activity_declaration_number', 'nda', 'nda_number' ),
		'naf'             => array( 'naf_code', 'naf' ),
		'tva'             => array( 'vat_number', 'tva' ),
		'adresse'         => array( 'address' ),
		'code_postal'     => array( 'postal_code', 'zip' ),
		'ville'           => array( 'city' ),
		'pays'            => array( 'country' ),
		'email'           => array( 'enterprise_contact_email', 'email' ),
		'telephone'       => array( 'enterprise_contact_phone', 'phone' ),
		'site'            => array( 'website_url', 'website' ),
		'signataire_prenom' => array( 'first_name' ),
		'signataire_nom'    => array( 'last_name' ),
		'signataire_role'   => array( 'signatory_role' ),
	);

	/**
	 * L'identité normalisée, à partir des deux fiches.
	 *
	 * @param array $fiche    La fiche entreprise — elle fait foi.
	 * @param array $marque   La fiche marque — secours, champ par champ.
	 * @return array<string,string>
	 */
	public static function fromOptions( $fiche, $marque = array() ) {
		$fiche  = is_array( $fiche ) ? $fiche : array();
		$marque = is_array( $marque ) ? $marque : array();

		$id = array();
		foreach ( self::CHAMPS as $canonique => $alias ) {
			$id[ $canonique ] = self::premierRempli( $alias, $fiche, $marque );
		}

		/* Le signataire s'écrit d'un seul tenant partout où il est signé. */
		$id['signataire'] = trim( $id['signataire_prenom'] . ' ' . $id['signataire_nom'] );

		return $id;
	}

	/* ── Mises en forme partagées ───────────────────────────────────────── */

	/**
	 * L'adresse sur une ligne : « 7 avenue X — 83310 Cogolin — France ».
	 *
	 * Les morceaux absents disparaissent avec leur séparateur : une adresse
	 * sans ville ne doit pas produire « 7 avenue X —  — France ».
	 *
	 * @param array  $id
	 * @param string $sep
	 * @return string
	 */
	public static function addressLine( $id, $sep = ' - ' ) {
		$ville = trim( self::v( $id, 'code_postal' ) . ' ' . self::v( $id, 'ville' ) );
		return self::joindre( array( self::v( $id, 'adresse' ), $ville, self::v( $id, 'pays' ) ), $sep );
	}

	/**
	 * L'adresse en plusieurs lignes, pour les blocs des PDF.
	 *
	 * @param array $id
	 * @return string[]
	 */
	public static function addressLines( $id ) {
		$ville = trim( self::v( $id, 'code_postal' ) . ' ' . self::v( $id, 'ville' ) );
		$pays  = self::v( $id, 'pays' );
		if ( '' !== $ville && '' !== $pays ) {
			$ville .= ' — ' . $pays;
			$pays   = '';
		}
		return array_values( array_filter( array( self::v( $id, 'adresse' ), $ville, $pays ), 'strlen' ) );
	}

	/**
	 * Les mentions légales : « Siret : … — NDA : … ».
	 *
	 * Une étiquette n'apparaît jamais sans sa valeur. C'est toute la règle des
	 * champs vides : mieux vaut une mention absente qu'une mention creuse.
	 *
	 * @param array  $id
	 * @param string $sep
	 * @return string
	 */
	public static function legalLine( $id, $sep = ' - ' ) {
		$bouts = array();
		if ( '' !== self::v( $id, 'siret' ) ) {
			$bouts[] = 'Siret : ' . self::v( $id, 'siret' );
		}
		if ( '' !== self::v( $id, 'nda' ) ) {
			$bouts[] = 'NDA : ' . self::v( $id, 'nda' );
		}
		return self::joindre( $bouts, $sep );
	}

	/**
	 * La ligne de pied de page : adresse puis mentions légales.
	 *
	 * @param array  $id
	 * @param string $sep
	 * @return string
	 */
	public static function footerLine( $id, $sep = ' - ' ) {
		return self::joindre( array( self::addressLine( $id, $sep ), self::legalLine( $id, $sep ) ), $sep );
	}

	/**
	 * La ligne de contact : « e-mail : … - Tél : … - site web : … ».
	 *
	 * @param array  $id
	 * @param string $sep
	 * @return string
	 */
	public static function contactLine( $id, $sep = ' - ' ) {
		$bouts = array();
		if ( '' !== self::v( $id, 'email' ) ) {
			$bouts[] = 'e-mail : ' . self::v( $id, 'email' );
		}
		if ( '' !== self::v( $id, 'telephone' ) ) {
			$bouts[] = 'Tél : ' . self::v( $id, 'telephone' );
		}
		if ( '' !== self::v( $id, 'site' ) ) {
			$bouts[] = 'site web : ' . self::siteAffiche( $id );
		}
		return self::joindre( $bouts, $sep );
	}

	/**
	 * Le bloc société d'un en-tête de document, ligne par ligne.
	 *
	 * @param array $id
	 * @return string[]
	 */
	public static function blockLines( $id ) {
		$lignes = array();
		if ( '' !== self::v( $id, 'raison_sociale' ) ) {
			$lignes[] = self::v( $id, 'raison_sociale' );
		}
		foreach ( self::addressLines( $id ) as $l ) {
			$lignes[] = $l;
		}
		if ( '' !== self::v( $id, 'siret' ) ) {
			$lignes[] = 'Siret : ' . self::v( $id, 'siret' );
		}
		if ( '' !== self::v( $id, 'nda' ) ) {
			$lignes[] = 'NDA : ' . self::v( $id, 'nda' );
		}
		return $lignes;
	}

	/**
	 * Le site sans son protocole, pour l'affichage.
	 *
	 * Un pied de page imprimé n'a que faire de « https:// » ; le lien
	 * cliquable, lui, garde l'adresse complète.
	 *
	 * @param array $id
	 * @return string
	 */
	public static function siteAffiche( $id ) {
		$site = self::v( $id, 'site' );
		$site = (string) preg_replace( '#^https?://#i', '', $site );
		return rtrim( $site, '/' );
	}

	/* ── Outils ─────────────────────────────────────────────────────────── */

	/**
	 * La première valeur non vide, fiche d'abord, marque ensuite.
	 *
	 * On épuise TOUS les alias de la fiche avant de regarder la marque : une
	 * fiche entreprise qui dit quelque chose, même sous un nom hérité, doit
	 * l'emporter sur la marque.
	 */
	private static function premierRempli( $alias, $fiche, $marque ) {
		foreach ( array( $fiche, $marque ) as $source ) {
			foreach ( $alias as $cle ) {
				if ( isset( $source[ $cle ] ) && ! is_array( $source[ $cle ] ) ) {
					$valeur = trim( (string) $source[ $cle ] );
					if ( '' !== $valeur ) {
						return $valeur;
					}
				}
			}
		}
		return '';
	}

	/** Un champ de l'identité, toujours une chaîne. */
	private static function v( $id, $cle ) {
		return isset( $id[ $cle ] ) ? trim( (string) $id[ $cle ] ) : '';
	}

	/** Joint en sautant les morceaux vides — pas de séparateur orphelin. */
	private static function joindre( $bouts, $sep ) {
		return implode( $sep, array_filter( array_map( 'trim', $bouts ), 'strlen' ) );
	}
}
