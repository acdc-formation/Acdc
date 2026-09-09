<?php
/**
 * Trait ACDC_Mcp_Abilities_Trait
 *
 * Expose une sélection d'actions métier du plugin en tant qu'« abilities »
 * WordPress (API wp-abilities/v1), afin de les rendre pilotables via le
 * plugin mcp-adapter (namespace mcp/).
 *
 * CADRAGE DE SÉCURITÉ (site de préproduction, financeurs RÉELS) :
 *   - AUCUNE ability n'envoie d'e-mail.
 *   - AUCUNE ability de suppression.
 *   - AUCUNE ability n'écrit sur les données financeurs, ni n'expose leurs
 *     coordonnées (aucune ability de lecture financeur n'est déclarée).
 *   - Chaque ability a un permission_callback réel adossé à une capacité DÉDIÉE
 *     (acdc_mcp_read / acdc_mcp_write — jamais manage_options), un input_schema
 *     et un output_schema explicites, une category (obligatoire, pré-enregistrée)
 *     et meta.public = true (exposition REST/MCP).
 *   - Rôles dédiés : acdc_mcp_agent (lecture+écriture), acdc_mcp_readonly
 *     (lecture). L'administrateur reçoit aussi les deux capacités.
 *
 * Deux lots :
 *   - Lot 1 : lecture seule (formations, prospects, leads, devis, indicateurs).
 *   - Lot 2 : écritures sûres et réversibles SANS e-mail (créer/modifier un
 *     devis, créer un prospect).
 *
 * @since 3.25.144
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ACDC_Mcp_Abilities_Trait {

	/** Garde d'idempotence : empêche un double enregistrement si les deux hooks se déclenchent. */
	private $acdc_mcp_abilities_registered = false;

	/**
	 * Enregistre toutes les abilities ACDC.
	 * Branché sur le hook du core wp_abilities_api_init (armé tôt dans
	 * class-acdc-mcp.php), avec garde d'idempotence.
	 */
	public function register_mcp_abilities() {
		// Idempotence : une seule passe même si le hook se déclenche plusieurs fois.
		if ( $this->acdc_mcp_abilities_registered ) {
			return;
		}
		// Disponibilité de l'API avant tout appel.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return; // API Abilities absente.
		}
		$this->acdc_mcp_abilities_registered   = true;
		$this->acdc_mcp_registration_failures = array();

		/* ---------------------- LOT 1 — LECTURE SEULE ---------------------- */

		$this->acdc_register_mcp_ability(
			'acdc-of/list-formations',
			'Lister les formations du catalogue',
			'Retourne les formations du catalogue (lecture seule, aucun e-mail).',
			array(
				'type'       => 'object',
				'properties' => array(
					'search'     => array( 'type' => 'string', 'description' => 'Recherche titre/code/modalité/ville.' ),
					'thematique' => array( 'type' => 'string', 'description' => 'Filtre par thématique exacte.' ),
					'archived'   => array( 'type' => 'boolean', 'description' => 'true = archivées, false = actives, absent = toutes.' ),
					'limit'      => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 100 ),
				),
				'additionalProperties' => false,
			),
			array(
				'type'       => 'object',
				'properties' => array(
					'formations' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
					'count'      => array( 'type' => 'integer' ),
				),
			),
			array( $this, 'mcp_list_formations' ),
			array( $this, 'mcp_can_read' )
		);

		$this->acdc_register_mcp_ability(
			'acdc-of/get-formation',
			'Consulter une formation',
			'Retourne une formation par son identifiant (lecture seule, aucun e-mail).',
			array(
				'type'       => 'object',
				'properties' => array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
				'required'   => array( 'id' ),
				'additionalProperties' => false,
			),
			array(
				'type'       => 'object',
				'properties' => array( 'formation' => array( 'type' => array( 'object', 'null' ) ) ),
			),
			array( $this, 'mcp_get_formation' ),
			array( $this, 'mcp_can_read' )
		);

		$this->acdc_register_mcp_ability(
			'acdc-of/list-prospects',
			'Lister les prospects',
			'Retourne les prospects du CRM (lecture seule, aucun e-mail). N\'expose aucune coordonnée financeur.',
			array(
				'type'       => 'object',
				'properties' => array(
					'search' => array( 'type' => 'string', 'description' => 'Recherche nom/société/e-mail.' ),
					'status' => array( 'type' => 'string', 'description' => 'Filtre par statut exact (ex. « À traiter »).' ),
					'source' => array( 'type' => 'string', 'description' => 'Filtre par source exacte.' ),
					'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 100 ),
				),
				'additionalProperties' => false,
			),
			array(
				'type'       => 'object',
				'properties' => array(
					'prospects' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
					'count'     => array( 'type' => 'integer' ),
				),
			),
			array( $this, 'mcp_list_prospects' ),
			array( $this, 'mcp_can_read' )
		);

		$this->acdc_register_mcp_ability(
			'acdc-of/get-prospect',
			'Consulter un prospect',
			'Retourne un prospect par son identifiant (lecture seule, aucun e-mail).',
			array(
				'type'       => 'object',
				'properties' => array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
				'required'   => array( 'id' ),
				'additionalProperties' => false,
			),
			array(
				'type'       => 'object',
				'properties' => array( 'prospect' => array( 'type' => array( 'object', 'null' ) ) ),
			),
			array( $this, 'mcp_get_prospect' ),
			array( $this, 'mcp_can_read' )
		);

		$this->acdc_register_mcp_ability(
			'acdc-of/list-leads',
			'Lister les leads',
			'Retourne les prospects issus d\'une capture entrante (champ « source » renseigné). Lecture seule, aucun e-mail.',
			array(
				'type'       => 'object',
				'properties' => array(
					'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 100 ),
				),
				'additionalProperties' => false,
			),
			array(
				'type'       => 'object',
				'properties' => array(
					'leads' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
					'count' => array( 'type' => 'integer' ),
				),
			),
			array( $this, 'mcp_list_leads' ),
			array( $this, 'mcp_can_read' )
		);

		$this->acdc_register_mcp_ability(
			'acdc-of/list-quotes',
			'Lister les devis',
			'Retourne les devis (lecture seule, aucun e-mail).',
			array(
				'type'       => 'object',
				'properties' => array(
					'scope'  => array( 'type' => 'string', 'enum' => array( 'action', 'ancillary' ) ),
					'status' => array( 'type' => 'string', 'description' => 'Filtre par statut (brouillon, envoye, a_signer, signe, refuse, expire).' ),
					'search' => array( 'type' => 'string' ),
					'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 100 ),
				),
				'additionalProperties' => false,
			),
			array(
				'type'       => 'object',
				'properties' => array(
					'quotes' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
					'count'  => array( 'type' => 'integer' ),
				),
			),
			array( $this, 'mcp_list_quotes' ),
			array( $this, 'mcp_can_read' )
		);

		$this->acdc_register_mcp_ability(
			'acdc-of/get-quote',
			'Consulter un devis',
			'Retourne un devis par son identifiant (lecture seule, aucun e-mail).',
			array(
				'type'       => 'object',
				'properties' => array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
				'required'   => array( 'id' ),
				'additionalProperties' => false,
			),
			array(
				'type'       => 'object',
				'properties' => array( 'quote' => array( 'type' => array( 'object', 'null' ) ) ),
			),
			array( $this, 'mcp_get_quote' ),
			array( $this, 'mcp_can_read' )
		);

		$this->acdc_register_mcp_ability(
			'acdc-of/get-indicators',
			'Consulter les indicateurs globaux',
			'Retourne les indicateurs Qualiopi/activité agrégés (lecture seule, aucun e-mail).',
			array( 'type' => 'object', 'properties' => array(), 'additionalProperties' => false ),
			array( 'type' => 'object', 'properties' => array( 'indicators' => array( 'type' => 'object' ) ) ),
			array( $this, 'mcp_get_indicators' ),
			array( $this, 'mcp_can_read' )
		);

		/* ------------- LOT 2 — ÉCRITURES SÛRES, SANS E-MAIL --------------- */

		$this->acdc_register_mcp_ability(
			'acdc-of/create-quote',
			'Créer un devis (brouillon, sans envoi)',
			'Crée un devis. AUCUN e-mail n\'est envoyé (ni au client, ni au financeur). Réversible : le devis peut être modifié ensuite.',
			$this->mcp_quote_write_schema( true ),
			array(
				'type'       => 'object',
				'properties' => array(
					'id'     => array( 'type' => 'integer' ),
					'number' => array( 'type' => 'string' ),
					'quote'  => array( 'type' => 'object' ),
				),
			),
			array( $this, 'mcp_create_quote' ),
			array( $this, 'mcp_can_write' )
		);

		$this->acdc_register_mcp_ability(
			'acdc-of/update-quote',
			'Modifier un devis (sans envoi)',
			'Met à jour les champs fournis d\'un devis existant. AUCUN e-mail n\'est envoyé.',
			$this->mcp_quote_write_schema( false ),
			array(
				'type'       => 'object',
				'properties' => array(
					'id'    => array( 'type' => 'integer' ),
					'quote' => array( 'type' => 'object' ),
				),
			),
			array( $this, 'mcp_update_quote' ),
			array( $this, 'mcp_can_write' )
		);

		$this->acdc_register_mcp_ability(
			'acdc-of/create-prospect',
			'Créer un prospect (sans envoi)',
			'Crée un prospect dans le CRM. AUCUN e-mail n\'est envoyé. Aucune donnée financeur en écriture.',
			array(
				'type'       => 'object',
				'properties' => array(
					'profile_type'         => array( 'type' => 'string', 'description' => 'Ex. « Particulier », « Salarié », « Entreprise », « Indépendant ».' ),
					'gender'               => array( 'type' => 'string' ),
					'first_name'           => array( 'type' => 'string' ),
					'last_name'            => array( 'type' => 'string' ),
					'email'                => array( 'type' => 'string', 'format' => 'email' ),
					'phone'                => array( 'type' => 'string' ),
					'company_name'         => array( 'type' => 'string' ),
					'siret'                => array( 'type' => 'string', 'description' => '14 chiffres pour un profil entreprise.' ),
					'signer_first_name'    => array( 'type' => 'string' ),
					'signer_last_name'     => array( 'type' => 'string' ),
					'signer_email'         => array( 'type' => 'string', 'format' => 'email' ),
					'address'              => array( 'type' => 'string' ),
					'postal_code'          => array( 'type' => 'string' ),
					'city'                 => array( 'type' => 'string' ),
					'desired_training'     => array( 'type' => 'string' ),
					'desired_thematique'   => array( 'type' => 'string' ),
					'desired_formation_id' => array( 'type' => 'integer' ),
					'status'               => array( 'type' => 'string', 'default' => 'À traiter' ),
					'source'               => array( 'type' => 'string', 'default' => 'MCP' ),
				),
				'required'             => array( 'profile_type' ),
				'additionalProperties' => false,
			),
			array(
				'type'       => 'object',
				'properties' => array(
					'id'       => array( 'type' => 'integer' ),
					'prospect' => array( 'type' => 'object' ),
				),
			),
			array( $this, 'mcp_create_prospect' ),
			array( $this, 'mcp_can_write' )
		);
	}

	/* ============================ HELPERS ============================== */

	/**
	 * Slug de la catégorie d'abilities ACDC (contrat core : une catégorie
	 * enregistrée est OBLIGATOIRE pour chaque ability).
	 */
	const MCP_CATEGORY = 'acdc-of';

	/**
	 * Enregistre une ability conformément au contrat réel du core WordPress 6.9/7.0 :
	 * label, description, category (obligatoire, catégorie pré-enregistrée),
	 * execute_callback, permission_callback, input/output_schema, meta.public.
	 * Trace tout échec (retour null) quand WP_DEBUG est actif.
	 */
	private function acdc_register_mcp_ability( $name, $label, $description, $input_schema, $output_schema, $execute_cb, $permission_cb ) {
		$ability = wp_register_ability(
			$name,
			array(
				'label'               => $label,
				'description'         => $description,
				'category'            => self::MCP_CATEGORY,
				'input_schema'        => $input_schema,
				'output_schema'       => $output_schema,
				'execute_callback'    => $execute_cb,
				'permission_callback' => $permission_cb,
				'meta'                => array(
					// Exposition publique (REST / MCP). Contrat core : meta.public (bool).
					'public'       => true,
					'show_in_rest' => true,
				),
			)
		);
		if ( null === $ability ) {
			$this->acdc_mcp_registration_failures[] = (string) $name;
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'ACDC MCP: échec d\'enregistrement de l\'ability « ' . $name . ' » (voir _doing_it_wrong / contrat Abilities API).' );
			}
		}
		return $ability;
	}

	/** Noms d'abilities dont l'enregistrement a échoué (diagnostic). */
	private $acdc_mcp_registration_failures = array();

	/**
	 * Enregistre la catégorie d'abilities ACDC.
	 * DOIT être branché sur wp_abilities_api_categories_init (avant wp_abilities_api_init).
	 */
	public function register_mcp_ability_categories() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		$cat = wp_register_ability_category(
			self::MCP_CATEGORY,
			array(
				'label'       => 'ACDC Formation',
				'description' => 'Actions CRM ACDC exposées via MCP (lecture + écritures sûres, sans e-mail).',
			)
		);
		if ( null === $cat && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'ACDC MCP: échec d\'enregistrement de la catégorie « ' . self::MCP_CATEGORY .' ».' );
		}
	}

	/** Liste blanche canonique des 11 abilities (source unique, réutilisée par le diagnostic). */
	public function acdc_mcp_expected_ability_names() {
		return array(
			'acdc-of/list-formations',
			'acdc-of/get-formation',
			'acdc-of/list-prospects',
			'acdc-of/get-prospect',
			'acdc-of/list-leads',
			'acdc-of/list-quotes',
			'acdc-of/get-quote',
			'acdc-of/get-indicators',
			'acdc-of/create-quote',
			'acdc-of/update-quote',
			'acdc-of/create-prospect',
		);
	}

	/** Permission LECTURE — capacité dédiée (jamais « true » en dur, jamais manage_options). */
	public function mcp_can_read( $input = array() ) {
		return current_user_can( 'acdc_mcp_read' );
	}

	/** Permission ÉCRITURE — capacité dédiée (jamais « true » en dur, jamais manage_options). */
	public function mcp_can_write( $input = array() ) {
		return current_user_can( 'acdc_mcp_write' );
	}

	/* ==================== CAPACITÉS & RÔLES DÉDIÉS ==================== */

	/** Version du schéma de rôles/capacités MCP. Incrémenter force une re-provision au boot. */
	private function acdc_mcp_roles_version() {
		return '1';
	}

	/**
	 * Provisionne (idempotent) les capacités et rôles dédiés MCP :
	 *   - capacités : acdc_mcp_read (lecture), acdc_mcp_write (écriture) ;
	 *   - rôle acdc_mcp_agent    : read + acdc_mcp_read + acdc_mcp_write ;
	 *   - rôle acdc_mcp_readonly : read + acdc_mcp_read ;
	 *   - administrator          : reçoit acdc_mcp_read + acdc_mcp_write (compat. usage actuel).
	 * Rejouable sans effet de bord (add_role est ignoré si le rôle existe ; on force les caps).
	 */
	public function acdc_mcp_setup_roles() {
		if ( ! function_exists( 'add_role' ) || ! function_exists( 'get_role' ) ) {
			return;
		}

		// Rôle agent (lecture + écriture).
		add_role( 'acdc_mcp_agent', 'ACDC MCP Agent', array() );
		$agent = get_role( 'acdc_mcp_agent' );
		if ( $agent ) {
			foreach ( array( 'read', 'acdc_mcp_read', 'acdc_mcp_write' ) as $cap ) {
				$agent->add_cap( $cap );
			}
		}

		// Rôle lecture seule.
		add_role( 'acdc_mcp_readonly', 'ACDC MCP Lecture seule', array() );
		$readonly = get_role( 'acdc_mcp_readonly' );
		if ( $readonly ) {
			foreach ( array( 'read', 'acdc_mcp_read' ) as $cap ) {
				$readonly->add_cap( $cap );
			}
			// Garantit que la variante « lecture seule » ne détient jamais l'écriture.
			$readonly->remove_cap( 'acdc_mcp_write' );
		}

		// Administrateur : conserve l'accès (compatibilité avec l'usage actuel).
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'acdc_mcp_read' );
			$admin->add_cap( 'acdc_mcp_write' );
		}

		update_option( 'acdc_mcp_roles_version', $this->acdc_mcp_roles_version(), false );
	}

	/**
	 * Provisionne les rôles au boot uniquement si nécessaire (check de version),
	 * pour que le rôle apparaisse même sans réactivation du plugin.
	 */
	public function acdc_mcp_maybe_setup_roles() {
		if ( get_option( 'acdc_mcp_roles_version' ) !== $this->acdc_mcp_roles_version() ) {
			$this->acdc_mcp_setup_roles();
		}
	}

	/**
	 * Suppression propre des rôles/capacités MCP (désactivation du plugin).
	 * Retire les rôles dédiés et révoque les capacités accordées à l'administrateur.
	 */
	public function acdc_mcp_remove_roles() {
		if ( function_exists( 'remove_role' ) ) {
			remove_role( 'acdc_mcp_agent' );
			remove_role( 'acdc_mcp_readonly' );
		}
		if ( function_exists( 'get_role' ) ) {
			$admin = get_role( 'administrator' );
			if ( $admin ) {
				$admin->remove_cap( 'acdc_mcp_read' );
				$admin->remove_cap( 'acdc_mcp_write' );
			}
		}
		if ( function_exists( 'delete_option' ) ) {
			delete_option( 'acdc_mcp_roles_version' );
		}
	}

	/** Schéma d'écriture partagé des devis. $creating : true = création (id interdit), false = maj (id requis). */
	private function mcp_quote_write_schema( $creating ) {
		$props = array(
			'scope'              => array( 'type' => 'string', 'enum' => array( 'action', 'ancillary' ), 'default' => 'action' ),
			'commanditaire_type' => array( 'type' => 'string', 'default' => 'Entreprise' ),
			'source_prospect_id' => array( 'type' => 'integer', 'description' => 'Rattachement à un prospect existant.' ),
			'apprenant_name'     => array( 'type' => 'string' ),
			'apprenant_email'    => array( 'type' => 'string', 'format' => 'email', 'description' => 'Stocké sur le devis ; AUCUN e-mail n\'est envoyé.' ),
			'client_company'     => array( 'type' => 'string' ),
			'client_siret'       => array( 'type' => 'string' ),
			'client_address'     => array( 'type' => 'string' ),
			'client_postal_code' => array( 'type' => 'string' ),
			'client_city'        => array( 'type' => 'string' ),
			'formation_id'       => array( 'type' => 'integer' ),
			'formation_title'    => array( 'type' => 'string' ),
			'start_date'         => array( 'type' => 'string', 'description' => 'Date de début (AAAA-MM-JJ).' ),
			'end_date'           => array( 'type' => 'string', 'description' => 'Date de fin (AAAA-MM-JJ).' ),
			'duration_label'     => array( 'type' => 'string' ),
			'trained_headcount'  => array( 'type' => 'string' ),
			'quantity'           => array( 'type' => 'string', 'default' => '1,00' ),
			'tarif_ht'           => array( 'type' => 'string', 'description' => 'Montant HT (ex. « 1800 » ou « 1800,00 »).' ),
			/* ACDC 3.25.309 — « vat_rate » retiré de l'interface MCP : le taux est
			   celui du régime de l'organisme, figé à la création du document.
			   Continuer à l'annoncer aurait laissé croire qu'on peut le fixer par
			   appel, alors que save_quote() l'écrase. */
			'designation'        => array( 'type' => 'string' ),
			'validity_days'      => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 365 ),
			'status'             => array( 'type' => 'string', 'description' => 'brouillon, envoye, a_signer, signe, refuse, expire.' ),
		);
		if ( ! $creating ) {
			$props = array_merge( array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ), $props );
			$required = array( 'id' );
		} else {
			$required = array();
		}
		return array(
			'type'                 => 'object',
			'properties'           => $props,
			'required'             => $required,
			'additionalProperties' => false,
		);
	}

	/* ---------------------- MAPPERS (sortie curée) -------------------- */

	private function mcp_map_formation( $f ) {
		if ( ! $f ) { return null; }
		return array(
			'id'                => isset( $f->id ) ? (int) $f->id : 0,
			'code'              => isset( $f->code ) ? (string) $f->code : '',
			'title'             => isset( $f->title ) ? (string) $f->title : '',
			'thematique'        => isset( $f->thematique ) ? (string) $f->thematique : '',
			'modality'          => isset( $f->modality ) ? (string) $f->modality : '',
			'city'              => isset( $f->city ) ? (string) $f->city : '',
			'duration'          => isset( $f->duration ) ? (string) $f->duration : '',
			'price_ht'          => isset( $f->price_ht ) ? (float) $f->price_ht : 0.0,
			'status'            => isset( $f->status ) ? (string) $f->status : '',
			'is_active'         => isset( $f->is_active ) ? (int) $f->is_active : 0,
			'taux_satisfaction' => isset( $f->taux_satisfaction ) ? (float) $f->taux_satisfaction : 0.0,
		);
	}

	private function mcp_map_prospect( $p ) {
		if ( ! $p ) { return null; }
		return array(
			'id'                   => isset( $p->id ) ? (int) $p->id : 0,
			'profile_type'         => isset( $p->profile_type ) ? (string) $p->profile_type : '',
			'first_name'           => isset( $p->first_name ) ? (string) $p->first_name : '',
			'last_name'            => isset( $p->last_name ) ? (string) $p->last_name : '',
			'company_name'         => isset( $p->company_name ) ? (string) $p->company_name : '',
			'siret'                => isset( $p->siret ) ? (string) $p->siret : '',
			'email'                => isset( $p->email ) ? (string) $p->email : '',
			'phone'                => isset( $p->phone ) ? (string) $p->phone : '',
			'city'                 => isset( $p->city ) ? (string) $p->city : '',
			'postal_code'          => isset( $p->postal_code ) ? (string) $p->postal_code : '',
			'status'               => isset( $p->status ) ? (string) $p->status : '',
			'source'               => isset( $p->source ) ? (string) $p->source : '',
			'desired_training'     => isset( $p->desired_training ) ? (string) $p->desired_training : '',
			'desired_formation_id' => isset( $p->desired_formation_id ) ? (int) $p->desired_formation_id : 0,
			// Référence financeur (identifiant uniquement — aucune coordonnée financeur exposée).
			'funder_id'            => isset( $p->funder_id ) ? (int) $p->funder_id : 0,
			'planned_funding'      => isset( $p->planned_funding ) ? (string) $p->planned_funding : '',
			'created_at'           => isset( $p->created_at ) ? (string) $p->created_at : '',
		);
	}

	private function mcp_map_quote( $row ) {
		if ( ! is_array( $row ) || empty( $row ) ) { return null; }
		$pick = array(
			'id', 'number', 'scope', 'status', 'status_label', 'commanditaire_type',
			'apprenant', 'apprenant_email', 'client_company', 'client_siret',
			'formation_full', 'formation_title', 'start_date', 'end_date',
			'quantity', 'tarif_ht', 'vat_rate', 'tarif_ttc',
			'emission_date', 'expiration_date', 'validity_days',
			'signature_status', 'source_prospect_id',
		);
		$out = array();
		foreach ( $pick as $k ) {
			if ( array_key_exists( $k, $row ) ) { $out[ $k ] = $row[ $k ]; }
		}
		return $out;
	}

	/* ------------------------ LOT 1 — CALLBACKS ---------------------- */

	public function mcp_list_formations( $input ) {
		$args = array(
			'search'     => isset( $input['search'] ) ? (string) $input['search'] : '',
			'thematique' => isset( $input['thematique'] ) ? (string) $input['thematique'] : '',
			'limit'      => isset( $input['limit'] ) ? (int) $input['limit'] : 100,
		);
		if ( array_key_exists( 'archived', $input ) ) {
			$args['archived'] = (bool) $input['archived'];
		}
		$rows = (array) $this->get_formations( $args );
		$out  = array_values( array_filter( array_map( array( $this, 'mcp_map_formation' ), $rows ) ) );
		return array( 'formations' => $out, 'count' => count( $out ) );
	}

	public function mcp_get_formation( $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( ! $id ) { return new WP_Error( 'acdc_mcp_invalid', 'Identifiant manquant.' ); }
		return array( 'formation' => $this->mcp_map_formation( $this->get_formation( $id ) ) );
	}

	public function mcp_list_prospects( $input ) {
		$limit  = isset( $input['limit'] ) ? max( 1, min( 200, (int) $input['limit'] ) ) : 100;
		$search = isset( $input['search'] ) ? mb_strtolower( trim( (string) $input['search'] ) ) : '';
		$status = isset( $input['status'] ) ? (string) $input['status'] : '';
		$source = isset( $input['source'] ) ? (string) $input['source'] : '';
		$rows   = (array) $this->get_prospects();
		$out    = array();
		foreach ( $rows as $p ) {
			if ( '' !== $status && (string) ( $p->status ?? '' ) !== $status ) { continue; }
			if ( '' !== $source && (string) ( $p->source ?? '' ) !== $source ) { continue; }
			if ( '' !== $search ) {
				$hay = mb_strtolower( trim( ( $p->first_name ?? '' ) . ' ' . ( $p->last_name ?? '' ) . ' ' . ( $p->company_name ?? '' ) . ' ' . ( $p->email ?? '' ) ) );
				if ( false === strpos( $hay, $search ) ) { continue; }
			}
			$out[] = $this->mcp_map_prospect( $p );
			if ( count( $out ) >= $limit ) { break; }
		}
		return array( 'prospects' => $out, 'count' => count( $out ) );
	}

	public function mcp_get_prospect( $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( ! $id ) { return new WP_Error( 'acdc_mcp_invalid', 'Identifiant manquant.' ); }
		return array( 'prospect' => $this->mcp_map_prospect( $this->get_prospect( $id ) ) );
	}

	public function mcp_list_leads( $input ) {
		$limit = isset( $input['limit'] ) ? max( 1, min( 200, (int) $input['limit'] ) ) : 100;
		$rows  = (array) $this->get_prospects();
		$out   = array();
		foreach ( $rows as $p ) {
			// Un lead = un prospect issu d'une capture entrante (champ source renseigné).
			if ( '' === trim( (string) ( $p->source ?? '' ) ) ) { continue; }
			$out[] = $this->mcp_map_prospect( $p );
			if ( count( $out ) >= $limit ) { break; }
		}
		return array( 'leads' => $out, 'count' => count( $out ) );
	}

	public function mcp_list_quotes( $input ) {
		$args = array(
			'scope'  => isset( $input['scope'] ) ? (string) $input['scope'] : '',
			'status' => isset( $input['status'] ) ? (string) $input['status'] : '',
			'search' => isset( $input['search'] ) ? (string) $input['search'] : '',
			'limit'  => isset( $input['limit'] ) ? (int) $input['limit'] : 100,
		);
		$rows = (array) $this->get_quotes( $args );
		$out  = array();
		foreach ( $rows as $q ) {
			$out[] = $this->mcp_map_quote( $this->build_quote_row_from_record( $q ) );
		}
		$out = array_values( array_filter( $out ) );
		return array( 'quotes' => $out, 'count' => count( $out ) );
	}

	public function mcp_get_quote( $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( ! $id ) { return new WP_Error( 'acdc_mcp_invalid', 'Identifiant manquant.' ); }
		$q = $this->get_quote( $id );
		if ( ! $q ) { return array( 'quote' => null ); }
		return array( 'quote' => $this->mcp_map_quote( $this->build_quote_row_from_record( $q ) ) );
	}

	public function mcp_get_indicators( $input ) {
		$data = array();
		if ( method_exists( $this, 'rest_get_indicators_global' ) && class_exists( '\WP_REST_Request' ) ) {
			$resp = $this->rest_get_indicators_global( new \WP_REST_Request( 'GET', '/acdc-of/v1/indicators' ) );
			if ( $resp instanceof \WP_REST_Response ) {
				$body = $resp->get_data();
				if ( is_array( $body ) && isset( $body['data'] ) && is_array( $body['data'] ) ) {
					$data = $body['data'];
				}
			}
		}
		return array( 'indicators' => $data );
	}

	/* ------------------------ LOT 2 — CALLBACKS ---------------------- */

	/** Construit les données d'un devis à partir d'un input MCP validé (aucun e-mail). */
	private function mcp_build_quote_data( array $input ) {
		$data = array();
		$copy_text = array(
			'commanditaire_type', 'apprenant_name', 'client_company', 'client_siret',
			'client_address', 'client_postal_code', 'client_city',
			'formation_title', 'duration_label', 'trained_headcount', 'designation', 'quantity',
		);
		foreach ( $copy_text as $k ) {
			if ( isset( $input[ $k ] ) ) { $data[ $k ] = sanitize_text_field( (string) $input[ $k ] ); }
		}
		if ( isset( $input['designation'] ) ) { $data['designation'] = sanitize_textarea_field( (string) $input['designation'] ); }
		if ( isset( $input['apprenant_email'] ) ) { $data['apprenant_email'] = sanitize_email( (string) $input['apprenant_email'] ); }
		if ( isset( $input['scope'] ) && in_array( $input['scope'], array( 'action', 'ancillary' ), true ) ) { $data['scope'] = (string) $input['scope']; }
		if ( isset( $input['source_prospect_id'] ) ) { $data['source_prospect_id'] = absint( $input['source_prospect_id'] ) ?: null; }
		if ( isset( $input['formation_id'] ) ) { $data['formation_id'] = absint( $input['formation_id'] ) ?: null; }
		if ( isset( $input['start_date'] ) && '' !== (string) $input['start_date'] ) { $data['start_date'] = sanitize_text_field( (string) $input['start_date'] ); }
		if ( isset( $input['end_date'] ) && '' !== (string) $input['end_date'] ) { $data['end_date'] = sanitize_text_field( (string) $input['end_date'] ); }
		if ( isset( $input['tarif_ht'] ) ) { $data['tarif_ht'] = round( (float) str_replace( ',', '.', preg_replace( '/[^0-9,.]/', '', (string) $input['tarif_ht'] ) ), 2 ); }
		if ( isset( $input['validity_days'] ) ) { $data['validity_days'] = absint( $input['validity_days'] ); }
		if ( isset( $input['status'] ) && array_key_exists( (string) $input['status'], $this->get_quote_status_labels() ) ) {
			$data['status'] = (string) $input['status'];
		}
		return $data;
	}

	public function mcp_create_quote( $input ) {
		if ( ! current_user_can( 'acdc_mcp_write' ) ) { return new WP_Error( 'acdc_mcp_forbidden', 'Accès refusé.' ); }
		$data = $this->mcp_build_quote_data( is_array( $input ) ? $input : array() );
		if ( empty( $data['scope'] ) ) { $data['scope'] = 'action'; }
		if ( empty( $data['status'] ) ) { $data['status'] = 'brouillon'; }
		// Déclenche la réservation atomique d'un vrai numéro DE-AAAA-N dans save_quote().
		$data['number'] = 'DE-' . (int) wp_date( 'Y' ) . '-0';
		$new_id = (int) $this->save_quote( $data );
		if ( ! $new_id ) { return new WP_Error( 'acdc_mcp_save_failed', 'La création du devis a échoué.' ); }
		$row = $this->build_quote_row_from_record( $this->get_quote( $new_id ) );
		return array(
			'id'     => $new_id,
			'number' => isset( $row['number'] ) ? (string) $row['number'] : '',
			'quote'  => $this->mcp_map_quote( $row ),
		);
	}

	public function mcp_update_quote( $input ) {
		if ( ! current_user_can( 'acdc_mcp_write' ) ) { return new WP_Error( 'acdc_mcp_forbidden', 'Accès refusé.' ); }
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( ! $id || ! $this->get_quote( $id ) ) { return new WP_Error( 'acdc_mcp_not_found', 'Devis introuvable.' ); }
		$data = $this->mcp_build_quote_data( is_array( $input ) ? $input : array() );
		if ( empty( $data ) ) { return new WP_Error( 'acdc_mcp_noop', 'Aucun champ à mettre à jour.' ); }
		$data['id'] = $id;
		$this->save_quote( $data ); // update pur en BDD, aucun e-mail.
		return array( 'id' => $id, 'quote' => $this->mcp_map_quote( $this->build_quote_row_from_record( $this->get_quote( $id ) ) ) );
	}

	public function mcp_create_prospect( $input ) {
		if ( ! current_user_can( 'acdc_mcp_write' ) ) { return new WP_Error( 'acdc_mcp_forbidden', 'Accès refusé.' ); }
		$input = is_array( $input ) ? $input : array();

		$profile_type = sanitize_text_field( (string) ( $input['profile_type'] ?? '' ) );
		if ( '' === $profile_type ) { return new WP_Error( 'acdc_mcp_invalid', 'Le profil (profile_type) est obligatoire.' ); }

		// Validation minimale alignée sur le formulaire (sans dupliquer sa logique de redirection).
		$is_individual = method_exists( $this, 'is_individual_prospect_profile' ) ? $this->is_individual_prospect_profile( $profile_type ) : false;
		$siret_raw     = preg_replace( '/\D/', '', (string) ( $input['siret'] ?? '' ) );
		if ( $is_individual ) {
			foreach ( array( 'first_name', 'last_name', 'email' ) as $req ) {
				if ( '' === trim( (string) ( $input[ $req ] ?? '' ) ) ) {
					return new WP_Error( 'acdc_mcp_invalid', 'Champ obligatoire manquant pour un particulier : ' . $req );
				}
			}
		} else {
			if ( '' === trim( (string) ( $input['company_name'] ?? '' ) ) || '' === trim( (string) ( $input['signer_first_name'] ?? '' ) ) || '' === trim( (string) ( $input['signer_last_name'] ?? '' ) ) ) {
				return new WP_Error( 'acdc_mcp_invalid', 'Entreprise/indépendant : company_name, signer_first_name et signer_last_name sont obligatoires.' );
			}
			if ( '' !== $siret_raw && 14 !== strlen( $siret_raw ) ) {
				return new WP_Error( 'acdc_mcp_invalid', 'Le SIRET doit contenir exactement 14 chiffres.' );
			}
		}

		$data = array(
			'profile_type'         => $profile_type,
			'gender'               => sanitize_text_field( (string) ( $input['gender'] ?? '' ) ),
			'first_name'           => sanitize_text_field( (string) ( $input['first_name'] ?? '' ) ),
			'last_name'            => sanitize_text_field( (string) ( $input['last_name'] ?? '' ) ),
			'email'                => sanitize_email( (string) ( $input['email'] ?? '' ) ),
			'phone'                => sanitize_text_field( (string) ( $input['phone'] ?? '' ) ),
			'company_name'         => sanitize_text_field( (string) ( $input['company_name'] ?? '' ) ),
			'siret'                => sanitize_text_field( (string) ( $input['siret'] ?? '' ) ),
			'signer_first_name'    => sanitize_text_field( (string) ( $input['signer_first_name'] ?? '' ) ),
			'signer_last_name'     => sanitize_text_field( (string) ( $input['signer_last_name'] ?? '' ) ),
			'signer_email'         => sanitize_email( (string) ( $input['signer_email'] ?? '' ) ),
			'address'              => sanitize_text_field( (string) ( $input['address'] ?? '' ) ),
			'postal_code'          => sanitize_text_field( (string) ( $input['postal_code'] ?? '' ) ),
			'city'                 => sanitize_text_field( (string) ( $input['city'] ?? '' ) ),
			'desired_training'     => sanitize_text_field( (string) ( $input['desired_training'] ?? '' ) ),
			'desired_thematique'   => sanitize_text_field( (string) ( $input['desired_thematique'] ?? '' ) ),
			'desired_formation_id' => absint( $input['desired_formation_id'] ?? 0 ) ?: null,
			'status'               => sanitize_text_field( (string) ( $input['status'] ?? 'À traiter' ) ) ?: 'À traiter',
			'source'               => sanitize_text_field( (string) ( $input['source'] ?? 'MCP' ) ) ?: 'MCP',
		);

		// Synchronise le titre de formation souhaitée si une formation catalogue est liée.
		if ( ! empty( $data['desired_formation_id'] ) && method_exists( $this, 'get_formation' ) ) {
			$lf = $this->get_formation( (int) $data['desired_formation_id'] );
			if ( $lf && ! empty( $lf->title ) ) { $data['desired_training'] = (string) $lf->title; }
		}

		$persist = $this->persist_prospect_record( $data, 0 );
		if ( empty( $persist['ok'] ) || empty( $persist['id'] ) ) {
			return new WP_Error( 'acdc_mcp_save_failed', 'La création du prospect a échoué.' );
		}
		return array(
			'id'       => (int) $persist['id'],
			'prospect' => $this->mcp_map_prospect( $this->get_prospect( (int) $persist['id'] ) ),
		);
	}

	/* ==================== DIAGNOSTIC ADMIN (Réglages → ACDC MCP) ==================== */

	/** Enregistre la page de diagnostic sous Réglages. Branché sur admin_menu. */
	public function register_mcp_admin_page() {
		if ( ! function_exists( 'add_options_page' ) ) {
			return;
		}
		add_options_page(
			'ACDC MCP',
			'ACDC MCP',
			'manage_options',
			'acdc-mcp',
			array( $this, 'render_mcp_admin_page' )
		);
	}

	/** Construit l'état de diagnostic (aucune donnée métier, aucun secret). */
	public function acdc_mcp_diagnostics() {
		$expected = $this->acdc_mcp_expected_ability_names();

		$abilities_api = function_exists( 'wp_register_ability' );
		$lib_present   = class_exists( '\WP\MCP\Core\McpAdapter' );

		// État de chaque ability attendue.
		$rows = array();
		foreach ( $expected as $name ) {
			$registered = function_exists( 'wp_has_ability' ) ? (bool) wp_has_ability( $name ) : false;
			$reason     = '';
			if ( ! $registered ) {
				if ( ! $abilities_api ) {
					$reason = 'API Abilities absente';
				} elseif ( in_array( $name, (array) $this->acdc_mcp_registration_failures, true ) ) {
					$reason = 'échec d\'enregistrement (contrat Abilities — voir logs si WP_DEBUG)';
				} else {
					$reason = 'hook wp_abilities_api_init non déclenché ou ability non armée';
				}
			}
			$rows[] = array( 'name' => $name, 'registered' => $registered, 'reason' => $reason );
		}

		// Liste blanche transmise au serveur MCP.
		$whitelist = ( class_exists( 'ACDC_Mcp_Server' ) && defined( 'ACDC_Mcp_Server::ABILITIES' ) )
			? (array) constant( 'ACDC_Mcp_Server::ABILITIES' )
			: $expected;

		// Endpoint MCP.
		$endpoint = '';
		if ( class_exists( 'ACDC_Mcp_Server' ) && function_exists( 'rest_url' ) ) {
			$endpoint = rest_url( ACDC_Mcp_Server::ROUTE_NAMESPACE . '/' . ACDC_Mcp_Server::ROUTE );
		}

		// Rôles/capacités + comptes porteurs.
		$roles_info = array();
		foreach ( array( 'acdc_mcp_agent', 'acdc_mcp_readonly', 'administrator' ) as $role_slug ) {
			$role = function_exists( 'get_role' ) ? get_role( $role_slug ) : null;
			if ( ! $role ) {
				continue;
			}
			$caps = array();
			foreach ( array( 'acdc_mcp_read', 'acdc_mcp_write' ) as $cap ) {
				if ( ! empty( $role->capabilities[ $cap ] ) ) {
					$caps[] = $cap;
				}
			}
			$users = array();
			if ( function_exists( 'get_users' ) ) {
				$found = get_users( array( 'role' => $role_slug, 'number' => 50, 'fields' => array( 'user_login' ) ) );
				foreach ( (array) $found as $u ) {
					$users[] = (string) $u->user_login; // identifiant de connexion, jamais de secret.
				}
			}
			$roles_info[] = array( 'role' => $role_slug, 'caps' => $caps, 'users' => $users );
		}

		return array(
			'plugin_version'  => defined( 'ACDC_OF_SAAS_VERSION' ) ? (string) ACDC_OF_SAAS_VERSION : '',
			'abilities_api'   => $abilities_api,
			'lib_present'     => $lib_present,
			'lib_version'     => defined( 'ACDC_MCP_ADAPTER_VERSION' ) ? (string) ACDC_MCP_ADAPTER_VERSION : '',
			'endpoint'        => (string) $endpoint,
			'whitelist'       => array_values( $whitelist ),
			'abilities'       => $rows,
			'registered_count'=> count( array_filter( $rows, function ( $r ) { return $r['registered']; } ) ),
			'roles'           => $roles_info,
		);
	}

	/** Rendu de la page de diagnostic (admins uniquement). */
	public function render_mcp_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'Accès refusé.' ) );
		}
		$d   = $this->acdc_mcp_diagnostics();
		$yes = '<span style="color:#1a7f37;font-weight:600;">Oui</span>';
		$no  = '<span style="color:#b32d2e;font-weight:600;">Non</span>';
		echo '<div class="wrap"><h1>ACDC MCP — Diagnostic</h1>';
		echo '<p>Aucune donnée métier ni secret sur cette page.</p>';

		echo '<table class="widefat striped" style="max-width:820px;margin-bottom:20px;"><tbody>';
		printf( '<tr><td><strong>Version du plugin</strong></td><td>%s</td></tr>', esc_html( $d['plugin_version'] ) );
		printf( '<tr><td><strong>API Abilities présente</strong></td><td>%s</td></tr>', $d['abilities_api'] ? $yes : $no ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		printf( '<tr><td><strong>Bibliothèque mcp-adapter</strong></td><td>%s %s</td></tr>', $d['lib_present'] ? $yes : $no, esc_html( $d['lib_version'] ? 'v' . $d['lib_version'] : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		printf( '<tr><td><strong>Endpoint MCP</strong></td><td><code>%s</code></td></tr>', esc_html( $d['endpoint'] ) );
		printf( '<tr><td><strong>Abilities enregistrées</strong></td><td>%d / %d</td></tr>', (int) $d['registered_count'], count( $d['abilities'] ) );
		echo '</tbody></table>';

		echo '<h2>Abilities (liste blanche)</h2>';
		echo '<table class="widefat striped" style="max-width:820px;margin-bottom:20px;"><thead><tr><th>Ability</th><th>État</th><th>Motif si manquante</th></tr></thead><tbody>';
		foreach ( $d['abilities'] as $r ) {
			printf(
				'<tr><td><code>%s</code></td><td>%s</td><td>%s</td></tr>',
				esc_html( $r['name'] ),
				$r['registered'] ? '<span style="color:#1a7f37;font-weight:600;">ENREGISTRÉE</span>' : '<span style="color:#b32d2e;font-weight:600;">MANQUANTE</span>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html( $r['reason'] )
			);
		}
		echo '</tbody></table>';

		echo '<h2>Liste blanche transmise au serveur MCP</h2><ul style="list-style:disc;margin-left:20px;">';
		foreach ( $d['whitelist'] as $w ) {
			printf( '<li><code>%s</code></li>', esc_html( $w ) );
		}
		echo '</ul>';

		echo '<h2>Rôles &amp; capacités</h2>';
		echo '<table class="widefat striped" style="max-width:820px;"><thead><tr><th>Rôle</th><th>Capacités MCP</th><th>Comptes porteurs</th></tr></thead><tbody>';
		foreach ( $d['roles'] as $ri ) {
			printf(
				'<tr><td><code>%s</code></td><td>%s</td><td>%s</td></tr>',
				esc_html( $ri['role'] ),
				esc_html( $ri['caps'] ? implode( ', ', $ri['caps'] ) : '—' ),
				esc_html( $ri['users'] ? implode( ', ', $ri['users'] ) : '—' )
			);
		}
		echo '</tbody></table>';
		echo '</div>';
	}
}
