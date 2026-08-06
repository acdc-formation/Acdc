<?php
/**
 * ACDC 3.21.69 — Export CSV des répertoires principaux.
 * Apprenants, Prospects, Formateurs, Séances.
 * Séparateur point-virgule, encodage UTF-8 BOM (compatible Excel).
 *
 * @package ACDC_Formation_SAAS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ACDC_Export_CSV_Trait {

	/* ====================================================================
	 *  Helper interne — envoi du fichier CSV
	 * ==================================================================== */

	/**
	 * Initialise les en-têtes HTTP et retourne un handle fopen php://output.
	 *
	 * @param string $filename Nom du fichier (sans chemin).
	 * @return resource
	 */
	private function acdc_csv_open( $filename ) {
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		$out = fopen( 'php://output', 'w' );
		// BOM UTF-8 pour Excel
		fwrite( $out, "\xEF\xBB\xBF" );
		return $out;
	}

	/**
	 * Écrit une ligne CSV en nettoyant les retours à la ligne dans les cellules.
	 *
	 * @param resource $handle
	 * @param array    $row
	 */
	/**
	 * ACDC 3.25.152 — Formatage sûr d'une date pour les exports.
	 *
	 * Les gardes existantes testaient uniquement la chaîne vide. Or MySQL stocke une
	 * date non renseignée sous la forme « 0000-00-00 », qui est TRUTHY en PHP : elle
	 * franchissait donc la garde et mysql2date() la rendait « 30/11/-0001 ».
	 *
	 * @param string $value  Date brute issue de la base.
	 * @param string $format Format de sortie.
	 * @return string Date formatée, ou chaîne vide si la date n'est pas renseignée.
	 */
	private function acdc_csv_date( $value, $format = 'd/m/Y' ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		// Dates « zéro » MySQL sous toutes leurs formes.
		if ( 0 === strpos( $value, '0000-00-00' ) ) {
			return '';
		}
		$ts = strtotime( $value );
		if ( false === $ts || $ts <= 0 ) {
			return '';
		}
		return mysql2date( $format, $value );
	}

	private function acdc_csv_row( $handle, $row ) {
		$clean = array_map( function( $v ) {
			$v = str_replace( array( "\r\n", "\r", "\n" ), ' ', (string) $v );
			if ( $v !== '' && in_array( $v[0], array( '=', '+', '-', '@' ), true ) ) { $v = "'" . $v; }
			return $v;
		}, $row );
		fputcsv( $handle, $clean, ';' );
	}

	/* ====================================================================
	 *  Export — Apprenants
	 * ==================================================================== */

	public function handle_export_learners_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'Accès refusé.' ) );
		}
		check_admin_referer( 'acdc_export_learners_csv' );
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT l.*,
			        s.title AS session_title,
			        s.start_date, s.end_date, s.status AS session_status,
			        f.title AS formation_title,
			        c.name AS company_name
			 FROM {$this->learner_table} l
			 LEFT JOIN {$this->session_table} s ON s.id = l.session_id
			 LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id
			 LEFT JOIN {$this->company_table} c ON c.id = l.company_id
			 ORDER BY l.last_name ASC, l.first_name ASC"
		);

		$out = $this->acdc_csv_open( 'apprenants-' . date_i18n( 'Y-m-d' ) . '.csv' );
		$this->acdc_csv_row( $out, array(
			'ID', 'Prénom', 'Nom', 'Nom d\'usage', 'Genre', 'E-mail', 'Téléphone',
			'Date de naissance', 'Ville', 'Code postal',
			'Entreprise', 'Poste', 'Niveau d\'étude', 'Catégorie socio-pro',
			'Statut', 'Financement',
			'Formation', 'Session', 'Date début', 'Date fin', 'Statut session',
			'Besoins accessibilité', 'Créé le', 'Modifié le',
		) );

		foreach ( (array) $rows as $r ) {
			$this->acdc_csv_row( $out, array(
				$r->id,
				$r->first_name,
				$r->last_name,
				$r->usage_last_name,
				$r->gender,
				$r->email,
				$r->phone,
				$this->acdc_csv_date( $r->birth_date ),
				$r->city,
				$r->postal_code,
				isset( $r->company_name ) ? $r->company_name : '',
				$r->job_title,
				$r->education_level,
				$r->socio_category,
				$r->status,
				$r->funding,
				isset( $r->formation_title ) ? $r->formation_title : '',
				isset( $r->session_title ) ? $r->session_title : '',
				$this->acdc_csv_date( $r->start_date ?? '' ),
				$this->acdc_csv_date( $r->end_date ?? '' ),
				isset( $r->session_status ) ? $r->session_status : '',
				$r->accessibility_needs,
				$this->acdc_csv_date( $r->created_at, 'd/m/Y H:i' ),
				$this->acdc_csv_date( $r->updated_at, 'd/m/Y H:i' ),
			) );
		}

		fclose( $out );
		exit;
	}

	/* ====================================================================
	 *  Export — Prospects
	 * ==================================================================== */

	public function handle_export_prospects_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'Accès refusé.' ) );
		}
		check_admin_referer( 'acdc_export_prospects_csv' );
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT * FROM {$this->prospect_table} ORDER BY updated_at DESC, id DESC"
		);

		$out = $this->acdc_csv_open( 'prospects-' . date_i18n( 'Y-m-d' ) . '.csv' );
		$this->acdc_csv_row( $out, array(
			'ID', 'Type', 'Genre', 'Prénom', 'Nom',
			'Entreprise', 'SIRET', 'Code NAF',
			'E-mail', 'Téléphone',
			'Adresse', 'Code postal', 'Ville',
			'Prénom signataire', 'Nom signataire', 'Qualité signataire',
			'E-mail signataire', 'Téléphone signataire',
			'Formation souhaitée', 'Statut', 'Source',
			'Date RDV', 'Dernier suivi', 'Attribué à',
			'France Travail', 'Accessibilité',
			'Commentaire', 'Créé le', 'Modifié le',
		) );

		foreach ( (array) $rows as $r ) {
			$this->acdc_csv_row( $out, array(
				$r->id,
				$r->profile_type,
				$r->gender,
				$r->first_name,
				$r->last_name,
				$r->company_name,
				isset( $r->siret ) ? $r->siret : '',
				isset( $r->naf_code ) ? $r->naf_code : '',
				$r->email,
				$r->phone,
				$r->address,
				$r->postal_code,
				$r->city,
				isset( $r->signer_first_name ) ? $r->signer_first_name : '',
				isset( $r->signer_last_name ) ? $r->signer_last_name : '',
				isset( $r->signer_quality ) ? $r->signer_quality : '',
				isset( $r->signer_email ) ? $r->signer_email : '',
				isset( $r->signer_phone ) ? $r->signer_phone : '',
				$r->desired_training,
				$r->status,
				$r->source,
				$this->acdc_csv_date( $r->rdv_at, 'd/m/Y H:i' ),
				$r->last_followup,
				$r->assigned_to,
				$r->is_france_travail,
				$r->accessibility_needs,
				$r->comment_text,
				$this->acdc_csv_date( $r->created_at, 'd/m/Y H:i' ),
				$this->acdc_csv_date( $r->updated_at, 'd/m/Y H:i' ),
			) );
		}

		fclose( $out );
		exit;
	}

	/* ====================================================================
	 *  Export — Formateurs
	 * ==================================================================== */

	public function handle_export_trainers_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'Accès refusé.' ) );
		}
		check_admin_referer( 'acdc_export_trainers_csv' );
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT * FROM {$this->trainer_table} ORDER BY last_name ASC, first_name ASC"
		);

		$out = $this->acdc_csv_open( 'formateurs-' . date_i18n( 'Y-m-d' ) . '.csv' );
		$this->acdc_csv_row( $out, array(
			'ID', 'Genre', 'Prénom', 'Nom', 'E-mail', 'Téléphone',
			'Rôle', 'Type', 'N° DA / NDA', 'SIRET',
			'Date de naissance',
			'Formateur principal', 'Accès portail',
			'Rappels séance', 'E-mails début séance',
			'Commentaire', 'Créé le', 'Modifié le',
		) );

		foreach ( (array) $rows as $r ) {
			$this->acdc_csv_row( $out, array(
				$r->id,
				$r->gender,
				$r->first_name,
				$r->last_name,
				$r->email,
				$r->phone,
				$r->role_name,
				$r->trainer_type,
				$r->nda_number,
				isset( $r->siret ) ? $r->siret : '',
				$this->acdc_csv_date( $r->birth_date ),
				! empty( $r->is_self_trainer ) ? 'Oui' : 'Non',
				! empty( $r->access_enabled ) ? 'Oui' : 'Non',
				! empty( $r->session_reminder_enabled ) ? 'Oui' : 'Non',
				! empty( $r->session_start_enabled ) ? 'Oui' : 'Non',
				$r->comment_text,
				$this->acdc_csv_date( $r->created_at, 'd/m/Y H:i' ),
				$this->acdc_csv_date( $r->updated_at, 'd/m/Y H:i' ),
			) );
		}

		fclose( $out );
		exit;
	}

	/* ====================================================================
	 *  Export — Séances
	 * ==================================================================== */

	public function handle_export_sessions_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'Accès refusé.' ) );
		}
		check_admin_referer( 'acdc_export_sessions_csv' );
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT s.*,
			        f.title AS formation_title,
			        t.first_name AS trainer_first, t.last_name AS trainer_last,
			        ( SELECT COUNT(*) FROM {$this->learner_table} l WHERE l.session_id = s.id ) AS nb_apprenants
			 FROM {$this->session_table} s
			 LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id
			 LEFT JOIN {$this->trainer_table} t ON t.id = s.trainer_id
			 WHERE s.is_draft = 0
			 ORDER BY COALESCE(s.start_date, DATE(s.start_at)) DESC, s.id DESC"
		);

		$out = $this->acdc_csv_open( 'seances-' . date_i18n( 'Y-m-d' ) . '.csv' );
		$this->acdc_csv_row( $out, array(
			'ID', 'Titre', 'Formation',
			'Type', 'Format', 'Mode de présence',
			'Date début', 'Date fin',
			'Lieu / lien distanciel',
			'Statut', 'Nb max apprenants', 'Nb apprenants inscrits',
			'Formateur',
			'Notes', 'Créé le', 'Modifié le',
		) );

		foreach ( (array) $rows as $r ) {
			$start = $this->acdc_csv_date( $r->start_date ?? '' ) ?: $this->acdc_csv_date( $r->start_at ?? '', 'd/m/Y H:i' );
			$end   = $this->acdc_csv_date( $r->end_date ?? '' ) ?: $this->acdc_csv_date( $r->end_at ?? '', 'd/m/Y H:i' );
			$lieu  = ! empty( $r->location ) ? $r->location : ( ! empty( $r->remote_link ) ? $r->remote_link : '' );
			$formateur = trim( ( $r->trainer_first ?? '' ) . ' ' . ( $r->trainer_last ?? '' ) );

			$this->acdc_csv_row( $out, array(
				$r->id,
				$r->title,
				isset( $r->formation_title ) ? $r->formation_title : '',
				$r->session_type,
				isset( $r->session_format ) ? $r->session_format : '',
				isset( $r->attendance_method ) ? $r->attendance_method : '',
				$start,
				$end,
				$lieu,
				$r->status,
				$r->max_learners,
				$r->nb_apprenants,
				$formateur,
				$r->notes,
				$this->acdc_csv_date( $r->created_at, 'd/m/Y H:i' ),
				$this->acdc_csv_date( $r->updated_at, 'd/m/Y H:i' ),
			) );
		}

		fclose( $out );
		exit;
	}

	/* ====================================================================
	 *  ACDC 3.24.30 — RGPD : Export données personnelles d'un apprenant
	 * ==================================================================== */

	/**
	 * Génère un CSV de l'ensemble des données personnelles d'un apprenant
	 * (droit d'accès RGPD art. 15 RGPD).
	 * Action : acdc_rgpd_export_learner
	 */
	public function handle_acdc_rgpd_export_learner() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'Accès refusé.' ) );
		}
		$learner_id = isset( $_GET['learner_id'] ) ? absint( $_GET['learner_id'] ) : 0;
		check_admin_referer( 'acdc_rgpd_export_learner_' . $learner_id );
		if ( ! $learner_id ) {
			wp_die( esc_html( 'Identifiant apprenant manquant.' ) );
		}
		global $wpdb;

		$learner = $wpdb->get_row( $wpdb->prepare(
			"SELECT l.*, c.company_name, s.title AS session_title, f.title AS formation_title
			 FROM {$this->learner_table} l
			 LEFT JOIN {$this->company_table} c ON c.id = l.company_id
			 LEFT JOIN {$this->session_table} s ON s.id = l.session_id
			 LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id
			 WHERE l.id = %d",
			$learner_id
		) );
		if ( ! $learner ) {
			wp_die( esc_html( 'Apprenant introuvable.' ) );
		}

		$nom = sanitize_file_name( $learner->last_name . '-' . $learner->first_name );
		$out = $this->acdc_csv_open( 'rgpd-apprenant-' . $nom . '-' . date_i18n( 'Y-m-d' ) . '.csv' );

		// Bloc 1 — Identité
		$this->acdc_csv_row( $out, array( '=== IDENTITÉ ===' ) );
		$this->acdc_csv_row( $out, array( 'Champ', 'Valeur' ) );
		$fields_identity = array(
			'ID'                 => $learner->id,
			'Genre'              => $learner->gender,
			'Prénom'             => $learner->first_name,
			'Nom d\'usage'       => $learner->usage_last_name,
			'Nom de naissance'   => $learner->birth_last_name,
			'E-mail'             => $learner->email,
			'Téléphone'          => $learner->phone,
			'Date de naissance'  => $this->acdc_csv_date( $learner->birth_date ),
			'Lieu de naissance'  => $learner->birth_place,
			'Adresse'            => $learner->address,
			'Complément adresse' => $learner->address_extra,
			'Code postal'        => $learner->postal_code,
			'Ville'              => $learner->city,
			'Poste'              => $learner->job_title,
			'Catégorie socio-pro'=> $learner->socio_category,
			'Niveau d\'étude'    => $learner->education_level,
			'France Travail'     => $learner->is_france_travail,
			'Financement'        => $learner->funding,
			'Statut'             => $learner->status,
			'Besoins accessibilité' => $learner->accessibility_needs,
			'Entreprise liée'    => isset( $learner->company_name ) ? $learner->company_name : '',
			'Session'            => isset( $learner->session_title ) ? $learner->session_title : '',
			'Formation'          => isset( $learner->formation_title ) ? $learner->formation_title : '',
			'Commentaire'        => $learner->comment_text,
			'Notes'              => $learner->notes,
			'Créé le'            => $this->acdc_csv_date( $learner->created_at, 'd/m/Y H:i' ),
			'Modifié le'         => $this->acdc_csv_date( $learner->updated_at, 'd/m/Y H:i' ),
		);
		foreach ( $fields_identity as $label => $val ) {
			$this->acdc_csv_row( $out, array( $label, (string) $val ) );
		}

		// Bloc 2 — Participations quiz
		$tbl_p = $wpdb->prefix . 'acdc_of_qz_participants';
		$tbl_s = $wpdb->prefix . 'acdc_of_qz_sessions';
		$tbl_q = $wpdb->prefix . 'acdc_of_qz_quizzes';
		$participations = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.id, p.nickname, p.email AS p_email, p.full_name, p.status,
			        p.total_score, p.total_score_percentage, p.is_passed,
			        p.invited_at, p.completed_at, p.is_anonymized,
			        q.title AS quiz_title
			 FROM {$tbl_p} p
			 LEFT JOIN {$tbl_s} qs ON qs.id = p.session_id
			 LEFT JOIN {$tbl_q} q  ON q.id  = qs.quiz_id
			 WHERE p.learner_id = %d
			 ORDER BY p.id DESC",
			$learner_id
		) );
		if ( ! empty( $participations ) ) {
			fputcsv( $out, array(), ';' ); // ligne vide
			$this->acdc_csv_row( $out, array( '=== PARTICIPATIONS QUIZ ===' ) );
			$this->acdc_csv_row( $out, array( 'ID', 'Quiz', 'Pseudo', 'E-mail quiz', 'Nom complet', 'Statut', 'Score', 'Score %', 'Réussi', 'Invité le', 'Complété le', 'Anonymisé' ) );
			foreach ( $participations as $p ) {
				$this->acdc_csv_row( $out, array(
					$p->id,
					$p->quiz_title,
					$p->nickname,
					$p->p_email,
					$p->full_name,
					$p->status,
					$p->total_score,
					$p->total_score_percentage,
					$p->is_passed ? 'Oui' : 'Non',
					$this->acdc_csv_date( $p->invited_at, 'd/m/Y H:i' ),
					$this->acdc_csv_date( $p->completed_at, 'd/m/Y H:i' ),
					$p->is_anonymized ? 'Oui' : 'Non',
				) );
			}
		}

		fclose( $out );
		exit;
	}

	/* ====================================================================
	 *  ACDC 3.24.30 — RGPD : Anonymisation manuelle d'un apprenant
	 * ==================================================================== */

	/**
	 * Anonymise les données personnelles d'un apprenant
	 * (droit à l'effacement RGPD art. 17).
	 * Action : acdc_rgpd_anonymize_learner
	 */
	public function handle_acdc_rgpd_anonymize_learner() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'Accès refusé.' ) );
		}
		$learner_id = isset( $_POST['learner_id'] ) ? absint( $_POST['learner_id'] ) : 0;
		check_admin_referer( 'acdc_rgpd_anonymize_learner_' . $learner_id );
		if ( ! $learner_id ) {
			wp_die( esc_html( 'Identifiant apprenant manquant.' ) );
		}
		global $wpdb;

		// Ajouter les colonnes si absentes (maybe_add_table_column)
		$this->maybe_add_table_column( $this->learner_table, 'is_anonymized',  'TINYINT(1) NOT NULL DEFAULT 0' );
		$this->maybe_add_table_column( $this->learner_table, 'anonymized_at',  'DATETIME DEFAULT NULL' );

		$token = 'ANONYME-' . strtoupper( substr( md5( (string) $learner_id . 'acdc_rgpd' ), 0, 8 ) );
		$now   = current_time( 'mysql' );

		$wpdb->update(
			$this->learner_table,
			array(
				'first_name'         => $token,
				'last_name'          => $token,
				'usage_last_name'    => $token,
				'birth_last_name'    => $token,
				'email'              => $token . '@anonyme.local',
				'phone'              => '',
				'birth_date'         => null,
				'birth_place'        => '',
				'address'            => '',
				'address_extra'      => '',
				'postal_code'        => '',
				'city'               => '',
				'job_title'          => '',
				'accessibility_needs'=> '',
				'comment_text'       => '',
				'notes'              => '',
				'is_anonymized'      => 1,
				'anonymized_at'      => $now,
				'updated_at'         => $now,
			),
			array( 'id' => $learner_id ),
			null,
			array( '%d' )
		);

		// Anonymiser aussi les participations quiz liées
		$tbl_p = $wpdb->prefix . 'acdc_of_qz_participants';
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$tbl_p} SET full_name = %s, email = %s, nickname = %s, is_anonymized = 1, anonymized_at = %s WHERE learner_id = %d AND is_anonymized = 0",
			$token,
			$token . '@anonyme.local',
			$token,
			$now,
			$learner_id
		) );

		$referer = wp_get_referer() ?: admin_url( 'admin.php?page=acdc-of-learners' );
		wp_safe_redirect( add_query_arg( 'acdc_notice', 'rgpd_anonymized', $referer ) );
		exit;
	}
}
