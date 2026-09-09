<?php
/**
 * ACDC 3.25.84 — Import / Export Excel (.xlsx) des prospects.
 * Reutilise l'infrastructure XLSX existante du kernel : read_xlsx_rows(),
 * normalize_import_header_label(), validate_uploaded_file_array().
 * Le fichier exporte est re-importable sans creer de doublon (colonne ID prospect).
 * L'export CSV existant n'est pas touche.
 *
 * @package ACDC_Formation_SAAS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ACDC_Excel_Prospects_Trait {

	/* ====================================================================
	 *  Definition des colonnes (export, modele, mapping import)
	 * ==================================================================== */

	private function get_prospect_export_columns() {
		return array(
			array( 'key' => 'id',                  'header' => 'ID prospect',           'type' => 'int',      'import' => true,  'aliases' => array( 'id prospect', 'id', 'prospect id', 'identifiant prospect' ) ),
			array( 'key' => 'profile_type',        'header' => 'Type de profil',        'type' => 'text',     'import' => true,  'aliases' => array( 'type de profil', 'type', 'profil', 'profile type' ) ),
			array( 'key' => 'gender',              'header' => 'Genre',                 'type' => 'text',     'import' => true,  'aliases' => array( 'genre', 'civilite' ) ),
			array( 'key' => 'first_name',          'header' => 'Prenom',                'type' => 'text',     'import' => true,  'aliases' => array( 'prenom', 'first name' ) ),
			array( 'key' => 'last_name',           'header' => 'Nom',                   'type' => 'text',     'import' => true,  'aliases' => array( 'nom', 'last name' ) ),
			array( 'key' => 'company_name',        'header' => 'Entreprise',            'type' => 'text',     'import' => true,  'aliases' => array( 'entreprise', 'societe', 'raison sociale', 'company' ) ),
			array( 'key' => 'siret',               'header' => 'SIRET',                 'type' => 'text',     'import' => true,  'aliases' => array( 'siret' ) ),
			array( 'key' => 'naf_code',            'header' => 'Code NAF',              'type' => 'text',     'import' => true,  'aliases' => array( 'code naf', 'naf', 'code ape', 'ape' ) ),
			array( 'key' => 'email',               'header' => 'E-mail',                'type' => 'text',     'import' => true,  'aliases' => array( 'e mail', 'email', 'mail', 'courriel' ) ),
			array( 'key' => 'phone',               'header' => 'Telephone',             'type' => 'text',     'import' => true,  'aliases' => array( 'telephone', 'tel', 'phone' ) ),
			array( 'key' => 'address',             'header' => 'Adresse',               'type' => 'text',     'import' => true,  'aliases' => array( 'adresse', 'address' ) ),
			array( 'key' => 'postal_code',         'header' => 'Code postal',           'type' => 'text',     'import' => true,  'aliases' => array( 'code postal', 'cp', 'zip' ) ),
			array( 'key' => 'city',                'header' => 'Ville',                 'type' => 'text',     'import' => true,  'aliases' => array( 'ville', 'city' ) ),
			array( 'key' => 'signer_first_name',   'header' => 'Prenom signataire',     'type' => 'text',     'import' => true,  'aliases' => array( 'prenom signataire', 'prenom du signataire' ) ),
			array( 'key' => 'signer_last_name',    'header' => 'Nom signataire',        'type' => 'text',     'import' => true,  'aliases' => array( 'nom signataire', 'nom du signataire' ) ),
			array( 'key' => 'signer_quality',      'header' => 'Qualite signataire',    'type' => 'text',     'import' => true,  'aliases' => array( 'qualite signataire', 'fonction signataire', 'qualite du signataire' ) ),
			array( 'key' => 'signer_email',        'header' => 'E-mail signataire',     'type' => 'text',     'import' => true,  'aliases' => array( 'e mail signataire', 'email signataire', 'mail signataire' ) ),
			array( 'key' => 'signer_phone',        'header' => 'Telephone signataire',  'type' => 'text',     'import' => true,  'aliases' => array( 'telephone signataire', 'tel signataire' ) ),
			array( 'key' => 'company_phone',       'header' => 'Telephone entreprise',  'type' => 'text',     'import' => true,  'aliases' => array( 'telephone entreprise', 'tel entreprise' ) ),
			array( 'key' => 'company_email',       'header' => 'E-mail entreprise',     'type' => 'text',     'import' => true,  'aliases' => array( 'e mail entreprise', 'email entreprise', 'mail entreprise' ) ),
			array( 'key' => 'activity',            'header' => 'Activite',              'type' => 'text',     'import' => true,  'aliases' => array( 'activite', 'secteur', 'secteur d activite', 'domaine' ) ),
			array( 'key' => 'website',             'header' => 'Site web',              'type' => 'url',      'import' => true,  'aliases' => array( 'site web', 'site internet', 'url', 'website', 'site' ) ),
			array( 'key' => 'birth_date',          'header' => 'Date de naissance',     'type' => 'date',     'import' => true,  'aliases' => array( 'date de naissance', 'naissance', 'date naissance' ) ),
			array( 'key' => 'job_title',           'header' => 'Poste',                 'type' => 'text',     'import' => true,  'aliases' => array( 'poste', 'fonction', 'job title' ) ),
			array( 'key' => 'education_level',     'header' => 'Niveau d etude',        'type' => 'text',     'import' => true,  'aliases' => array( 'niveau d etude', 'niveau etude', 'niveau d etudes', 'niveau' ) ),
			array( 'key' => 'desired_training',    'header' => 'Formation souhaitee',   'type' => 'text',     'import' => true,  'aliases' => array( 'formation souhaitee', 'formation', 'formation demandee' ) ),
			array( 'key' => 'status',              'header' => 'Statut',                'type' => 'text',     'import' => true,  'aliases' => array( 'statut', 'status', 'etat' ) ),
			array( 'key' => 'source',              'header' => 'Source',                'type' => 'text',     'import' => true,  'aliases' => array( 'source', 'origine' ) ),
			array( 'key' => 'is_france_travail',   'header' => 'France Travail',        'type' => 'text',     'import' => true,  'aliases' => array( 'france travail', 'pole emploi', 'demandeur emploi' ) ),
			array( 'key' => 'accessibility_needs', 'header' => 'Besoins accessibilite', 'type' => 'text',     'import' => true,  'aliases' => array( 'besoins accessibilite', 'accessibilite', 'handicap' ) ),
			array( 'key' => 'comment_text',        'header' => 'Commentaire',           'type' => 'text',     'import' => true,  'aliases' => array( 'commentaire', 'commentaires', 'notes', 'comment' ) ),
			array( 'key' => 'assigned_to',         'header' => 'Attribue a',            'type' => 'text',     'import' => true,  'aliases' => array( 'attribue a', 'assigne a', 'responsable', 'commercial' ) ),
			array( 'key' => 'created_at',          'header' => 'Cree le',               'type' => 'datetime', 'import' => false, 'aliases' => array() ),
			array( 'key' => 'updated_at',          'header' => 'Modifie le',            'type' => 'datetime', 'import' => false, 'aliases' => array() ),
		);
	}

	/* ====================================================================
	 *  Writer XLSX generique (une feuille) — calque sur build_formations_xlsx
	 * ==================================================================== */

	private function acdc_build_xlsx_single_sheet( $sheet_name, $headers, $data_rows ) {
		if ( ! class_exists( 'ZipArchive' ) ) { return ''; }

		$clean = function( $s ) {
			return preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $s );
		};
		$xe = function( $s ) use ( $clean ) {
			$s = $clean( $s );
			$s = htmlspecialchars( $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
			$s = str_replace( "\n", '&#10;', $s );
			$s = str_replace( "\r", '', $s );
			return $s;
		};
		$col_letter = function( $n ) {
			$l = '';
			while ( $n > 0 ) { $n--; $l = chr( 65 + ( $n % 26 ) ) . $l; $n = intdiv( $n, 26 ); }
			return $l;
		};

		$all_rows = array();
		$all_rows[] = array_values( (array) $headers );
		foreach ( (array) $data_rows as $row ) {
			$all_rows[] = array_values( (array) $row );
		}

		$shared     = array();
		$shared_map = array();
		$add_str    = function( $val ) use ( &$shared, &$shared_map ) {
			$k = (string) $val;
			if ( ! isset( $shared_map[ $k ] ) ) {
				$shared_map[ $k ] = count( $shared );
				$shared[]         = $k;
			}
			return (int) $shared_map[ $k ];
		};

		$s1_data = '';
		foreach ( $all_rows as $_ri => $_rd ) {
			$_rn  = $_ri + 1;
			$_hdr = ( 0 === $_ri );
			$_rx  = '';
			foreach ( $_rd as $_ci => $_cv ) {
				$_ref = $col_letter( $_ci + 1 ) . $_rn;
				$_v   = (string) $_cv;
				$_nl  = ( false !== strpos( $_v, "\n" ) );
				$_si  = $add_str( $_v );
				$_s   = $_hdr ? 2 : ( $_nl ? 1 : 0 );
				$_rx .= '<c r="' . $_ref . '" t="s" s="' . $_s . '"><v>' . $_si . '</v></c>';
			}
			$s1_data .= '<row r="' . $_rn . '">' . $_rx . '</row>';
		}
		$xml_sheet1 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . '<sheetViews><sheetView workbookViewId="0">' . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>' . '</sheetView></sheetViews>' . '<sheetFormatPr defaultRowHeight="15"/>' . '<sheetData>' . $s1_data . '</sheetData>' . '</worksheet>';

		$ss_count = count( $shared );
		$ss_data  = '';
		foreach ( $shared as $_sv ) {
			$_sp   = ( '' !== $_sv && ( ' ' === $_sv[0] || ' ' === $_sv[ strlen( $_sv ) - 1 ] ) ) ? ' xml:space="preserve"' : '';
			$ss_data .= '<si><t' . $_sp . '>' . $xe( $_sv ) . '</t></si>';
		}
		$shared_str_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"' . ' count="' . $ss_count . '" uniqueCount="' . $ss_count . '">' . $ss_data . '</sst>';

		$styles_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . '<fonts count="2">' . '<font><sz val="10"/><name val="Calibri"/><charset val="1"/></font>' . '<font><b/><sz val="10"/><name val="Calibri"/><charset val="1"/></font>' . '</fonts>' . '<fills count="3">' . '<fill><patternFill patternType="none"/></fill>' . '<fill><patternFill patternType="gray125"/></fill>' . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9E1F2"/></patternFill></fill>' . '</fills>' . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' . '<cellXfs count="3">' . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment wrapText="1" vertical="top"/></xf>' . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0"><alignment wrapText="1"/></xf>' . '</cellXfs>' . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>' . '</styleSheet>';

		$ns_r   = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
		$ns_p   = 'http://schemas.openxmlformats.org/package/2006/relationships';
		$ct_xls = 'application/vnd.openxmlformats-officedocument.spreadsheetml';

		$safe_name = $xe( $sheet_name );
		if ( '' === $safe_name ) { $safe_name = 'Feuille1'; }
		$workbook_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="' . $ns_r . '">' . '<sheets>' . '<sheet name="' . $safe_name . '" sheetId="1" r:id="rId1"/>' . '</sheets></workbook>';

		$workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . '<Relationships xmlns="' . $ns_p . '">' . '<Relationship Id="rId1" Type="' . $ns_r . '/worksheet"     Target="worksheets/sheet1.xml"/>' . '<Relationship Id="rId2" Type="' . $ns_r . '/styles"        Target="styles.xml"/>' . '<Relationship Id="rId3" Type="' . $ns_r . '/sharedStrings" Target="sharedStrings.xml"/>' . '</Relationships>';

		$root_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . '<Relationships xmlns="' . $ns_p . '">' . '<Relationship Id="rId1" Type="' . $ns_r . '/officeDocument" Target="xl/workbook.xml"/>' . '</Relationships>';

		$content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' . '<Default Extension="xml"  ContentType="application/xml"/>' . '<Override PartName="/xl/workbook.xml"           ContentType="' . $ct_xls . '.sheet.main+xml"/>' . '<Override PartName="/xl/worksheets/sheet1.xml"  ContentType="' . $ct_xls . '.worksheet+xml"/>' . '<Override PartName="/xl/styles.xml"             ContentType="' . $ct_xls . '.styles+xml"/>' . '<Override PartName="/xl/sharedStrings.xml"      ContentType="' . $ct_xls . '.sharedStrings+xml"/>' . '</Types>';

		$tmp = tempnam( sys_get_temp_dir(), 'acdc_prospects_' ) . '.xlsx';
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) { return ''; }
		$zip->addFromString( '[Content_Types].xml',        $content_types );
		$zip->addFromString( '_rels/.rels',                $root_rels );
		$zip->addFromString( 'xl/workbook.xml',            $workbook_xml );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', $workbook_rels );
		$zip->addFromString( 'xl/worksheets/sheet1.xml',   $xml_sheet1 );
		$zip->addFromString( 'xl/styles.xml',              $styles_xml );
		$zip->addFromString( 'xl/sharedStrings.xml',       $shared_str_xml );
		$zip->close();
		$bytes = file_get_contents( $tmp );
		@unlink( $tmp );
		return false !== $bytes ? $bytes : '';
	}

	private function acdc_send_xlsx_download( $filename, $bytes ) {
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $bytes ) );
		echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binaire XLSX
		exit;
	}

	/* ====================================================================
	 *  Export — Prospects (.xlsx)
	 * ==================================================================== */

	public function handle_export_prospects_xlsx() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'Acces refuse.' ) );
		}
		check_admin_referer( 'acdc_export_prospects_xlsx' );
		global $wpdb;

		$columns = $this->get_prospect_export_columns();
		$rows    = $wpdb->get_results( "SELECT * FROM {$this->prospect_table} ORDER BY updated_at DESC, id DESC" );

		$headers = array();
		foreach ( $columns as $col ) {
			$headers[] = $col['header'];
		}

		$data_rows = array();
		foreach ( (array) $rows as $r ) {
			$line = array();
			foreach ( $columns as $col ) {
				$key   = $col['key'];
				$value = isset( $r->$key ) ? $r->$key : '';
				if ( 'date' === $col['type'] ) {
					$value = ( $value && '0000-00-00' !== $value ) ? mysql2date( 'd/m/Y', $value ) : '';
				} elseif ( 'datetime' === $col['type'] ) {
					$value = ( $value && '0000-00-00 00:00:00' !== $value ) ? mysql2date( 'd/m/Y H:i', $value ) : '';
				}
				$line[] = (string) $value;
			}
			$data_rows[] = $line;
		}

		$xlsx = $this->acdc_build_xlsx_single_sheet( 'prospects', $headers, $data_rows );
		if ( '' === $xlsx ) {
			$this->redirect_to_portal( 'prospects', 'Impossible de generer le fichier Excel. Verifiez que l extension ZipArchive est disponible sur le serveur.', 'error' );
		}
		$this->acdc_send_xlsx_download( 'prospects-' . date_i18n( 'Y-m-d-His' ) . '.xlsx', $xlsx );
	}

	/* ====================================================================
	 *  Modele d'import — Prospects (.xlsx, en-tetes + ligne d'exemple)
	 * ==================================================================== */

	public function handle_prospects_xlsx_template() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'Acces refuse.' ) );
		}
		check_admin_referer( 'acdc_prospects_xlsx_template' );

		$columns = $this->get_prospect_export_columns();
		$headers = array();
		foreach ( $columns as $col ) {
			$headers[] = $col['header'];
		}

		$example = array();
		foreach ( $columns as $col ) {
			switch ( $col['key'] ) {
				case 'id':                  $example[] = ''; break;
				case 'profile_type':        $example[] = 'Entreprise'; break;
				case 'company_name':        $example[] = 'ACME SARL'; break;
				case 'siret':               $example[] = '12345678900012'; break;
				case 'signer_first_name':   $example[] = 'Marie'; break;
				case 'signer_last_name':    $example[] = 'Durand'; break;
				case 'signer_quality':      $example[] = 'Gerante'; break;
				case 'email':               $example[] = 'contact@acme.fr'; break;
				case 'phone':               $example[] = '0490000000'; break;
				case 'city':                $example[] = 'Cogolin'; break;
				case 'postal_code':         $example[] = '83310'; break;
				case 'status':              $example[] = 'À traiter'; break;
				case 'desired_training':    $example[] = 'Initiation IA generative'; break;
				default:                    $example[] = ''; break;
			}
		}

		$xlsx = $this->acdc_build_xlsx_single_sheet( 'modele_import_prospects', $headers, array( $example ) );
		if ( '' === $xlsx ) {
			$this->redirect_to_portal( 'prospects', 'Impossible de generer le modele Excel. Verifiez que l extension ZipArchive est disponible sur le serveur.', 'error', array( 'action' => 'import' ) );
		}
		$this->acdc_send_xlsx_download( 'modele_import_prospects.xlsx', $xlsx );
	}

	/* ====================================================================
	 *  Import — Prospects (.xlsx)
	 * ==================================================================== */

	private function map_prospect_import_headers( $header_row ) {
		$columns = $this->get_prospect_export_columns();
		$alias_to_key = array();
		foreach ( $columns as $col ) {
			if ( empty( $col['import'] ) ) {
				continue;
			}
			foreach ( (array) $col['aliases'] as $alias ) {
				$alias_to_key[ $this->normalize_import_header_label( $alias ) ] = $col['key'];
			}
		}

		$mapping = array();
		foreach ( (array) $header_row as $index => $label ) {
			$norm = $this->normalize_import_header_label( $label );
			if ( '' === $norm ) {
				continue;
			}
			if ( isset( $alias_to_key[ $norm ] ) ) {
				$key = $alias_to_key[ $norm ];
				if ( ! isset( $mapping[ $key ] ) ) {
					$mapping[ $key ] = (int) $index;
				}
			}
		}
		return $mapping;
	}

	private function find_prospect_for_import( $row, $mapping ) {
		global $wpdb;

		if ( isset( $mapping['id'] ) ) {
			$raw_id = isset( $row[ $mapping['id'] ] ) ? absint( $row[ $mapping['id'] ] ) : 0;
			if ( $raw_id > 0 ) {
				$found = $this->get_prospect( $raw_id );
				if ( $found ) {
					return $found;
				}
			}
		}

		if ( isset( $mapping['email'] ) ) {
			$raw_email = isset( $row[ $mapping['email'] ] ) ? sanitize_email( trim( (string) $row[ $mapping['email'] ] ) ) : '';
			if ( '' !== $raw_email ) {
				$found = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->prospect_table} WHERE email = %s ORDER BY id ASC LIMIT 1", $raw_email ) );
				if ( $found ) {
					return $found;
				}
			}
		}

		if ( isset( $mapping['siret'] ) ) {
			$raw_siret = isset( $row[ $mapping['siret'] ] ) ? preg_replace( '/\D/', '', (string) $row[ $mapping['siret'] ] ) : '';
			if ( strlen( $raw_siret ) === 14 ) {
				$found = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->prospect_table} WHERE siret = %s ORDER BY id ASC LIMIT 1", $raw_siret ) );
				if ( $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	private function build_prospect_import_data_from_row( $row, $mapping, $existing, $options ) {
		$overwrite_empty = ! empty( $options['overwrite_empty'] );

		$cell = function( $key ) use ( $row, $mapping ) {
			if ( ! isset( $mapping[ $key ] ) ) {
				return null;
			}
			$idx = (int) $mapping[ $key ];
			return isset( $row[ $idx ] ) ? trim( (string) $row[ $idx ] ) : '';
		};

		$prev = function( $key, $default = '' ) use ( $existing ) {
			if ( $existing && isset( $existing->$key ) ) {
				return (string) $existing->$key;
			}
			return $default;
		};

		// Resolution du profil : valeur fournie sinon deduction.
		$profile = (string) $cell( 'profile_type' );
		$profile = '' !== $profile ? sanitize_text_field( $profile ) : '';
		if ( '' === $profile && $existing ) {
			$profile = (string) $prev( 'profile_type' );
		}
		if ( '' === $profile ) {
			$company_hint = (string) $cell( 'company_name' );
			$siret_hint   = (string) $cell( 'siret' );
			if ( '' !== $company_hint || '' !== $siret_hint ) {
				$profile = 'Entreprise';
			} else {
				$profile = 'Particulier';
			}
		}
		$is_company = $this->is_company_prospect_profile( $profile );

		// SIRET : validation 14 chiffres si renseigne (entreprise).
		if ( $is_company ) {
			$raw_siret_cell = $cell( 'siret' );
			if ( null !== $raw_siret_cell && '' !== $raw_siret_cell ) {
				$digits = preg_replace( '/\D/', '', (string) $raw_siret_cell );
				if ( strlen( $digits ) !== 14 ) {
					return new \WP_Error( 'acdc_import_bad_siret', 'SIRET invalide (14 chiffres attendus).' );
				}
			}
		}

		// Champs minimaux pour creer un prospect exploitable.
		if ( ! $existing ) {
			if ( $is_company ) {
				$company_now = (string) $cell( 'company_name' );
				if ( '' === $company_now ) {
					return new \WP_Error( 'acdc_import_missing_company', 'Entreprise manquante pour un prospect entreprise.' );
				}
			} else {
				$last_now  = (string) $cell( 'last_name' );
				$email_now = (string) $cell( 'email' );
				if ( '' === $last_now && '' === $email_now ) {
					return new \WP_Error( 'acdc_import_missing_identity', 'Nom ou e-mail requis pour un prospect.' );
				}
			}
		}

		// Champs texte simples geres par cette boucle.
		$text_keys = array(
			'gender', 'first_name', 'last_name', 'company_name', 'naf_code',
			'address', 'postal_code', 'city', 'phone',
			'signer_first_name', 'signer_last_name', 'signer_quality', 'signer_phone',
			'company_phone', 'job_title', 'education_level',
			'desired_training', 'source', 'is_france_travail',
			'accessibility_needs', 'comment_text', 'assigned_to', 'activity',
		);

		$data = array();
		$data['profile_type'] = $profile;

		foreach ( $text_keys as $key ) {
			$val = $cell( $key );
			if ( null === $val ) {
				// Colonne absente : on conserve l'existant (rien a ecrire).
				continue;
			}
			if ( '' === $val && ! $overwrite_empty && $existing ) {
				continue; // cellule vide en mode preserve : on garde l'existant.
			}
			$data[ $key ] = sanitize_text_field( $val );
		}

		// E-mails sanitizes specifiquement.
		foreach ( array( 'email', 'signer_email', 'company_email' ) as $email_key ) {
			$val = $cell( $email_key );
			if ( null === $val ) {
				continue;
			}
			if ( '' === $val && ! $overwrite_empty && $existing ) {
				continue;
			}
			$data[ $email_key ] = sanitize_email( $val );
		}

		// Site web : sanitize via esc_url_raw (jamais sanitize_text_field).
		$website_val = $cell( 'website' );
		if ( null !== $website_val ) {
			if ( '' === $website_val ) {
				if ( $overwrite_empty || ! $existing ) {
					$data['website'] = '';
				}
			} else {
				$data['website'] = esc_url_raw( $website_val );
			}
		}

		// SIRET : 14 chiffres normalises.
		$siret_val = $cell( 'siret' );
		if ( null !== $siret_val ) {
			if ( '' === $siret_val ) {
				if ( $overwrite_empty || ! $existing ) {
					$data['siret'] = '';
				}
			} else {
				$data['siret'] = preg_replace( '/\D/', '', (string) $siret_val );
			}
		}

		// Date de naissance : conversion FR -> Y-m-d.
		$birth_val = $cell( 'birth_date' );
		if ( null !== $birth_val ) {
			if ( '' === $birth_val ) {
				if ( $overwrite_empty || ! $existing ) {
					$data['birth_date'] = null;
				}
			} else {
				$data['birth_date'] = $this->normalize_import_date_to_mysql( $birth_val );
			}
		}

		// Statut : valeur par defaut si vide a la creation.
		$status_val = $cell( 'status' );
		if ( null !== $status_val ) {
			if ( '' === $status_val ) {
				if ( ! $existing ) {
					$data['status'] = 'À traiter';
				} elseif ( $overwrite_empty ) {
					$data['status'] = 'À traiter';
				}
			} else {
				$data['status'] = sanitize_text_field( $status_val );
			}
		} elseif ( ! $existing ) {
			$data['status'] = 'À traiter';
		}

		// Coherence profil : on neutralise les champs sans objet.
		if ( $this->is_individual_prospect_profile( $profile ) ) {
			$data['company_name'] = '';
			$data['siret'] = '';
			$data['naf_code'] = '';
			$data['signer_first_name'] = '';
			$data['signer_last_name'] = '';
			$data['signer_quality'] = '';
			$data['signer_email'] = '';
			$data['signer_phone'] = '';
			$data['company_phone'] = '';
		} elseif ( $is_company ) {
			$data['gender'] = '';
			$data['birth_date'] = null;
		}

		return $data;
	}

	private function normalize_import_date_to_mysql( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}
		if ( preg_match( '#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})$#', $value, $m ) ) {
			return sprintf( '%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1] );
		}
		if ( preg_match( '#^(\d{4})[/\-.](\d{1,2})[/\-.](\d{1,2})$#', $value, $m ) ) {
			return sprintf( '%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3] );
		}
		$ts = strtotime( $value );
		if ( false !== $ts ) {
			return wp_date( 'Y-m-d', $ts );
		}
		return null;
	}

	public function handle_import_prospects_xlsx() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'Acces refuse.' ) );
		}
		check_admin_referer( 'acdc_import_prospects_xlsx' );

		if ( empty( $_FILES['prospects_file'] ) || ! is_array( $_FILES['prospects_file'] ) ) {
			$this->redirect_to_portal( 'prospects', 'Aucun fichier Excel n a ete recu.', 'error', array( 'action' => 'import' ) );
		}

		$validated = $this->validate_uploaded_file_array( wp_unslash( $_FILES['prospects_file'] ), 'prospects_file' );
		if ( is_wp_error( $validated ) ) {
			$this->redirect_to_portal( 'prospects', $validated->get_error_message(), 'error', array( 'action' => 'import' ) );
		}

		$rows = $this->read_xlsx_rows( $validated['tmp_name'] );
		if ( is_wp_error( $rows ) ) {
			$this->redirect_to_portal( 'prospects', $rows->get_error_message(), 'error', array( 'action' => 'import' ) );
		}

		if ( empty( $rows ) || empty( $rows[0] ) ) {
			$this->redirect_to_portal( 'prospects', 'Le fichier Excel est vide ou ne contient pas d en-tetes exploitables.', 'error', array( 'action' => 'import' ) );
		}

		$mapping = $this->map_prospect_import_headers( $rows[0] );
		if ( ! isset( $mapping['email'] ) && ! isset( $mapping['last_name'] ) && ! isset( $mapping['company_name'] ) && ! isset( $mapping['id'] ) ) {
			$this->redirect_to_portal( 'prospects', 'Le fichier doit contenir au moins une colonne reconnue : E-mail, Nom, Entreprise ou ID prospect.', 'error', array( 'action' => 'import' ) );
		}

		$overwrite_empty = ! empty( $_POST['overwrite_empty'] );
		$import_options  = array( 'overwrite_empty' => $overwrite_empty );

		$this->ensure_storage_ready();
		$this->ensure_prospect_schema_integrity();

		global $wpdb;
		$now     = current_time( 'mysql' );
		$created = 0;
		$updated = 0;
		$errors  = array();

		foreach ( $rows as $row_index => $row ) {
			if ( 0 === $row_index ) {
				continue;
			}
			if ( '' === trim( implode( '', (array) $row ) ) ) {
				continue; // ligne totalement vide
			}

			$existing = $this->find_prospect_for_import( $row, $mapping );
			$data     = $this->build_prospect_import_data_from_row( $row, $mapping, $existing, $import_options );

			if ( is_wp_error( $data ) ) {
				$errors[] = 'Ligne ' . ( $row_index + 1 ) . ' : ' . $data->get_error_message();
				continue;
			}

			if ( $existing ) {
				$data['updated_at'] = $now;
				$result = $wpdb->update( $this->prospect_table, $data, array( 'id' => (int) $existing->id ) );
				if ( false === $result ) {
					$errors[] = 'Ligne ' . ( $row_index + 1 ) . ' : echec de mise a jour (ID ' . (int) $existing->id . ').';
					continue;
				}
				$updated++;
			} else {
				$data['created_at'] = $now;
				$data['updated_at'] = $now;
				$result = $wpdb->insert( $this->prospect_table, $data );
				if ( false === $result ) {
					$errors[] = 'Ligne ' . ( $row_index + 1 ) . ' : echec de creation.';
					continue;
				}
				$created++;
			}
		}

		$message = sprintf( 'Import termine : %1$d creation(s), %2$d mise(s) a jour, %3$d erreur(s).', (int) $created, (int) $updated, (int) count( $errors ) );
		if ( ! empty( $errors ) ) {
			$message .= ' ' . implode( ' | ', array_slice( $errors, 0, 5 ) );
			if ( count( $errors ) > 5 ) {
				$message .= ' | Autres erreurs : ' . ( count( $errors ) - 5 ) . '.';
			}
		}

		$this->redirect_to_portal( 'prospects', $message, empty( $errors ) ? 'success' : 'error', array( 'action' => 'import' ) );
	}
}
