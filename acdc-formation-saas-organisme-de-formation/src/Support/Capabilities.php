<?php
/**
 * Modèle de capacités WordPress métier — logique pure et testable.
 *
 * Remplacement progressif et NON régressif du tout-`manage_options` : cette classe
 * définit le catalogue des capacités métier de l'ERP et leur attribution par rôle,
 * de façon déterministe (aucun accès WordPress, aucun état global).
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class Capabilities {

	/**
	 * Capacité pivot « accès ERP » : la porte d'entrée du back-office métier.
	 */
	const PRIMARY = 'acdc_of_manage';

	/**
	 * Catalogue complet des capacités métier.
	 *
	 * @return string[] Liste ordonnée, sans doublon, pivot en tête.
	 */
	public static function all() {
		return array(
			self::PRIMARY,
			'acdc_of_manage_learners',
			'acdc_of_view_signatures',
			'acdc_of_manage_crm',
			'acdc_of_manage_billing',
			'acdc_of_manage_settings',
			'acdc_of_manage_api_keys',
			'acdc_of_purge_data',
		);
	}

	/**
	 * Attribution des capacités par clé de rôle WordPress.
	 *
	 * Structure prête à accueillir des rôles plus fins (formateur, commercial…)
	 * sans toucher au reste du code.
	 *
	 * @return array<string,string[]> Clé de rôle => capacités à accorder.
	 */
	public static function map() {
		return array(
			'administrator'      => self::all(),
			'acdc_portal_admin'  => self::all(),
		);
	}
}
