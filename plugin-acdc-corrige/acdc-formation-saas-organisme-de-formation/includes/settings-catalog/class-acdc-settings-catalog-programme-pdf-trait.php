<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Génération du programme de formation au format HTML visuel — SAAS OF
 *
 * Port exact du module ACDC_FM_Programme_PDF du plugin Manager (3.9.36).
 * Les données sont lues depuis la table BDD acdc_of_formations au lieu de get_post_meta().
 * Format programme_detail : JSON [{jour, moment, titre, contenus, competences, opo}, …]
 *
 * Accès : portal_page_url( ['fm_action'=>'programme_pdf','fm_formation_id'=>$id,'fm_pdf_nonce'=>$nonce] )
 * Intercepté via template_redirect (hook priorité 1) — jamais via admin-post.php (bloqué WAF).
 *
 * Compatible PHP 7.3+
 *
 * @since 3.21.52
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ACDC_Settings_Catalog_Programme_PDF_Trait {

	/* ─────────────────────────────────────────
	   CONSTANTES VISUELLES (identiques au Manager)
	───────────────────────────────────────── */
	private function get_prog_pdf_logo_url() {
		return trailingslashit( ACDC_OF_SAAS_URL ) . 'assets/images/logo-acdc.png';
	}
	private function get_prog_pdf_hero_url() {
		return trailingslashit( ACDC_OF_SAAS_URL ) . 'assets/images/hero-programme.jpg';
	}
	private function get_prog_pdf_contacts() {
		return array(
			array( 'nom' => 'David Contal',       'tel' => '06.78.26.91.10', 'email' => 'dcontal@acdc-formation.com' ),
			array( 'nom' => 'Ann-Cécile Joucher', 'tel' => '06.29.93.74.24', 'email' => 'acjoucher@acdc-formation.com' ),
		);
	}
	private function get_prog_pdf_adresse() { return '7 avenue Paul Cézanne — 83310 Cogolin — Golfe de St-Tropez'; }
	private function get_prog_pdf_site()    { return 'https://acdc-formation.com'; }
	private function get_prog_pdf_siret()   { return '405 109 901 00042'; }
	private function get_prog_pdf_nda()     { return '93 83 08347 83'; }

	/* ─────────────────────────────────────────
	   INTERCEPTION template_redirect
	───────────────────────────────────────── */
	public function handle_programme_pdf_request() {
		if ( ! isset( $_GET['fm_action'] ) || 'programme_pdf' !== sanitize_key( wp_unslash( $_GET['fm_action'] ) ) ) {
			return;
		}

		$formation_id = isset( $_GET['fm_formation_id'] ) ? absint( $_GET['fm_formation_id'] ) : 0;
		$nonce        = isset( $_GET['fm_pdf_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['fm_pdf_nonce'] ) ) : '';

		if ( ! $formation_id ) {
			status_header( 404 );
			wp_die( 'Formation introuvable.' );
		}

		if ( ! wp_verify_nonce( $nonce, 'acdc_of_programme_pdf_' . $formation_id ) ) {
			status_header( 403 );
			wp_die( 'Lien invalide ou expiré.' );
		}

		if ( ! is_user_logged_in() ) {
			// Accepter aussi les apprenants connectés via le portal apprenant (session custom)
			$lp_account = $this->learner_portal_get_current_account();
			if ( ! $lp_account ) {
				status_header( 403 );
				wp_die( 'Connexion requise.' );
			}
		}

		$formation = $this->get_formation( $formation_id );
		if ( ! $formation ) {
			status_header( 404 );
			wp_die( 'Formation introuvable.' );
		}

		$auto_pdf = isset( $_GET['fm_pdf_mode'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['fm_pdf_mode'] ) );

		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Content-Disposition: inline' );

		echo $this->build_programme_pdf_html( $formation, $auto_pdf ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/* ─────────────────────────────────────────
	   URL PUBLIQUE DU PROGRAMME PDF
	───────────────────────────────────────── */
	public function get_programme_pdf_url( $formation_id, $auto_pdf = false ) {
		$args = array(
			'fm_action'       => 'programme_pdf',
			'fm_formation_id' => (int) $formation_id,
			'fm_pdf_nonce'    => wp_create_nonce( 'acdc_of_programme_pdf_' . (int) $formation_id ),
		);
		if ( $auto_pdf ) {
			$args['fm_pdf_mode'] = '1';
		}
		return $this->portal_page_url( $args );
	}

	/* ─────────────────────────────────────────
	   RÉCUPÉRATION DES DONNÉES DEPUIS LA BDD SAAS
	───────────────────────────────────────── */
	private function get_prog_pdf_data( $formation ) {
		// Durée : le SAAS stocke HH:MM — reformater en "X jours / Yh" si possible
		$duree_raw   = isset( $formation->duration ) ? (string) $formation->duration : '';
		$jours_count = isset( $formation->jours_count ) ? (int) $formation->jours_count : 0;
		$duree = '';
		if ( $duree_raw ) {
			if ( preg_match( '/^(\\d{1,3}):(\\d{2})$/', $duree_raw, $m ) ) {
				// ACDC 3.25.113 — intégrer les minutes de la durée HH:MM (perdues auparavant).
				$total_h      = (int) $m[1];
				$mins         = isset( $m[2] ) ? (int) $m[2] : 0;
				$heures_label = $total_h . 'h' . ( $mins ? sprintf( '%02d', $mins ) : '' );
				if ( $jours_count > 0 && ( $total_h > 0 || $mins > 0 ) ) {
					$duree = $jours_count . ' jour' . ( $jours_count > 1 ? 's' : '' ) . ' / ' . $heures_label;
				} elseif ( $total_h > 0 || $mins > 0 ) {
					$duree = $heures_label;
				}
			} else {
				$duree = $duree_raw;
			}
		}

		// Modalités : lire toutes les formations liées (même manager_formation_id) pour avoir toutes les modalités
		$modalites = array();
		$tarifs_par_modalite = array();
		$manager_id = isset( $formation->manager_formation_id ) ? (int) $formation->manager_formation_id : 0;
		if ( $manager_id > 0 ) {
			global $wpdb;
			$all = $wpdb->get_results( $wpdb->prepare(
				"SELECT modality, price_ht, tarifs_detail FROM {$this->formation_table} WHERE manager_formation_id = %d AND is_active = 1",
				$manager_id
			) );
			foreach ( $all as $row ) {
				if ( $row->modality ) $modalites[] = $row->modality;
				// Tarifs par modalité depuis tarifs_detail (JSON)
				if ( ! empty( $row->tarifs_detail ) ) {
					$td = json_decode( $row->tarifs_detail, true );
					if ( is_array( $td ) ) {
						foreach ( $td as $k => $v ) {
							if ( $v && $v !== '' ) $tarifs_par_modalite[ $k ] = $v;
						}
					}
				} elseif ( $row->price_ht && $row->modality ) {
					$tarifs_par_modalite[ $row->modality ] = $row->price_ht;
				}
			}
			$modalites = array_values( array_unique( array_filter( $modalites ) ) );
		} else {
			$modality = isset( $formation->modality ) ? (string) $formation->modality : '';
			if ( $modality ) $modalites = array( $modality );
			$tarif_ht = isset( $formation->price_ht ) ? (string) $formation->price_ht : '';
			if ( $tarif_ht && $modality ) $tarifs_par_modalite[ $modality ] = $tarif_ht;
		}

		// Tarif fallback depuis price_ht si tarifs_detail vide
		if ( empty( $tarifs_par_modalite ) ) {
			$tarif_ht = isset( $formation->price_ht ) ? (string) $formation->price_ht : '';
			$modality = isset( $formation->modality ) ? (string) $formation->modality : '';
			if ( $tarif_ht ) $tarifs_par_modalite[ $modality ?: 'Tarif' ] = $tarif_ht;
		}

		// Effectif
		$effectif_min = isset( $formation->effectif_min ) ? (int) $formation->effectif_min : 0;
		$effectif_max = isset( $formation->effectif_max ) ? (int) $formation->effectif_max : 0;

		// CPF
		$cpf_eligible = isset( $formation->cpf_eligible ) ? (int) $formation->cpf_eligible : 0;
		$cpf_code     = isset( $formation->cpf_code )     ? (string) $formation->cpf_code     : '';

		// Financement
		$financement_raw = isset( $formation->financement ) ? (string) $formation->financement : '';
		$financement_arr = array();
		if ( $financement_raw ) {
			$decoded = json_decode( $financement_raw, true );
			if ( is_array( $decoded ) ) {
				$financement_arr = array_filter( array_map( 'sanitize_text_field', $decoded ) );
			} else {
				$financement_arr = array_filter( array_map( 'trim', explode( ',', $financement_raw ) ) );
			}
		}

		// Objectifs pédagogiques structurés (objectifs_detail = objectifs_blocs Manager)
		$objectifs_blocs = array();
		if ( ! empty( $formation->objectifs_detail ) ) {
			$decoded = json_decode( (string) $formation->objectifs_detail, true );
			if ( is_array( $decoded ) ) $objectifs_blocs = $decoded;
		}

		// Programme détaillé — format SAAS : [{jour, moment, titre, contenus, ateliers, competences, opo}]
		$programme_blocs = array();
		$prog_raw = isset( $formation->programme_detail ) ? (string) $formation->programme_detail : '';
		if ( $prog_raw ) {
			$prog_decoded = json_decode( $prog_raw, true );
			if ( is_array( $prog_decoded ) ) {
				$by_jour = array();
				foreach ( $prog_decoded as $bloc ) {
					$jour    = isset( $bloc['jour'] ) ? (int) $bloc['jour'] : 1;
					$moment  = isset( $bloc['moment'] ) ? (string) $bloc['moment'] : 'matin';
					$moment_label = ( 'apm' === $moment ) ? 'Après-midi' : 'Matin';
					$contenus_raw    = isset( $bloc['contenus'] )    ? (string) $bloc['contenus']    : '';
					$ateliers_raw    = isset( $bloc['ateliers'] )    ? (string) $bloc['ateliers']    : '';
					$competences_raw = isset( $bloc['competences'] ) ? (string) $bloc['competences'] : '';
					$opo_raw         = isset( $bloc['opo'] )         ? (string) $bloc['opo']         : '';
					$by_jour[ $jour ][] = array(
						'moment'      => $moment_label,
						'titre'       => isset( $bloc['titre'] ) ? (string) $bloc['titre'] : '',
						'contenus'    => array_values( array_filter( array_map( 'trim', explode( "\n", $contenus_raw ) ) ) ),
						'ateliers'    => array_values( array_filter( array_map( 'trim', explode( "\n", $ateliers_raw ) ) ) ),
						'competences' => array_values( array_filter( array_map( 'trim', explode( "\n", $competences_raw ) ) ) ),
						'objectifs'   => array_values( array_filter( array_map( 'trim', explode( "\n", $opo_raw ) ) ) ),
					);
				}
				ksort( $by_jour );
				foreach ( $by_jour as $jour_num => $demi_journees ) {
					$programme_blocs[] = array(
						'jour'          => 'Jour ' . $jour_num,
						'demi_journees' => $demi_journees,
					);
				}
			}
		}

		return array(
			'titre'             => isset( $formation->title ) ? (string) $formation->title : '',
			'date_maj'          => wp_date( 'd/m/Y' ),
			'pourquoi'          => isset( $formation->accroche ) ? (string) $formation->accroche : '',
			'objectif_general'  => isset( $formation->objectives ) ? (string) $formation->objectives : '',
			'objectifs_blocs'   => $objectifs_blocs,
			'programme_blocs'   => $programme_blocs,
			'public'            => isset( $formation->catalog_audience ) ? (string) $formation->catalog_audience : '',
			'prerequis'         => isset( $formation->prerequisites ) ? (string) $formation->prerequisites : '',
			'duree'             => $duree,
			'modalites'         => $modalites,
			'effectif_min'      => $effectif_min,
			'effectif_max'      => $effectif_max,
			'tarifs'            => $tarifs_par_modalite,
			'cpf'               => $cpf_eligible ? 'Éligible CPF' : 'Non',
			'cpf_code'          => $cpf_code,
			'financement'       => $financement_arr,
			'formateurs'        => isset( $formation->trainer_ref ) ? (string) $formation->trainer_ref : '',
			'accessibilite'     => isset( $formation->accessibilite ) ? (string) $formation->accessibilite : '',
			'referent_handicap' => isset( $formation->referent_handicap ) ? (string) $formation->referent_handicap : '',
			'sanction'          => isset( $formation->sanction ) ? (string) $formation->sanction : '',
			'eval_entree'       => isset( $formation->evaluation_entree ) ? (string) $formation->evaluation_entree : '',
			'eval_sortie'       => isset( $formation->evaluation_sortie ) ? (string) $formation->evaluation_sortie : '',
			'moyens_pedago'     => isset( $formation->moyens_pedago ) ? (string) $formation->moyens_pedago : '',
			'suivi'             => isset( $formation->suivi ) ? (string) $formation->suivi : '',
			'lieu_acces'        => isset( $formation->lieu_acces ) ? (string) $formation->lieu_acces : '',
			'ressources'        => isset( $formation->ressources ) ? (string) $formation->ressources : '',
			'demarches'         => isset( $formation->demarches ) ? (string) $formation->demarches : '',
			'annulation'        => isset( $formation->annulation ) ? (string) $formation->annulation : '',
		);
	}

		/* ─────────────────────────────────────────
	   CONSTRUCTION HTML COMPLET (identique au Manager)
	───────────────────────────────────────── */
	private function build_programme_pdf_html( $formation, $auto_pdf = false ) {
		$d            = $this->get_prog_pdf_data( $formation );
		$t            = esc_html( $d['titre'] );
		$logo_url     = $this->get_prog_pdf_logo_url();
		$hero_url     = $this->get_prog_pdf_hero_url();
		$pdf_filename = $this->get_prog_pdf_filename( $d['titre'] );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Programme — <?php echo $t; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,700&display=swap" rel="stylesheet">
<style>
:root {
	--marine:      #174a6d;
	--marine-dark: #1C2B4A;
	--or:          #c9a84c;
	--or-alt:      #d6a353;
	--cream:       #fdf6e7;
	--text:        #2d2d2d;
	--white:       #ffffff;
	--border:      #e8dfc0;
	--sidebar-w:   28px;
	--grad-h: linear-gradient(90deg,#6B3F1D 0%,#B7772E 17%,#E9C77C 34%,#D7A24B 50%,#F3E3BF 67%,#C28A3A 84%,#8A5A2B 100%);
	--grad-d: linear-gradient(135deg,#6b3f1d,#b7772e,#e9c77c,#d7a24b,#f3e3bf,#8a5a2b);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{-webkit-print-color-adjust:exact;print-color-adjust:exact}
body{font-family:'Rubik',sans-serif;font-size:14px;color:var(--text);background:#ddd;line-height:1.5}
.page{position:relative;width:210mm;height:297mm;background:var(--white);margin:10mm auto;display:flex;flex-direction:row;box-shadow:0 4px 28px rgba(0,0,0,.22);overflow:hidden}
.sidebar{width:var(--sidebar-w);flex-shrink:0;background:var(--grad-d);display:flex;align-items:center;justify-content:center}
.sidebar-label{writing-mode:vertical-rl;transform:rotate(180deg);font-size:14px;font-weight:900;letter-spacing:4px;text-transform:uppercase;color:var(--marine-dark);white-space:nowrap;user-select:none}
.page-inner{flex:1;display:flex;flex-direction:column;height:297mm;overflow:hidden}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;padding:10px 16px 8px 12px;border-bottom:1px solid var(--border);flex-shrink:0}
.header-left{flex:1}
.date-line{font-size:11px;color:#999;margin-bottom:3px}
.formation-lbl{font-size:13.5px;font-weight:700;color:var(--marine);text-decoration:underline;text-underline-offset:2px;margin-bottom:2px}
.titre-formation{font-size:21px;font-weight:800;color:var(--marine);line-height:1.2;max-width:330px}
.page-logo{width:76px;height:auto;object-fit:contain;flex-shrink:0;margin-left:10px}
.hero-img{width:100%;height:90px;object-fit:cover;display:block;flex-shrink:0}
.page-content{flex:1;padding:10px 16px 10px 12px;display:flex;flex-direction:column;gap:8px}
.section-title{font-size:18px;font-weight:800;color:var(--marine);position:relative;padding-bottom:5px;margin-bottom:8px}
.section-title::after{content:'';display:block;height:2.5px;width:100%;background:var(--grad-h);border-radius:2px;position:absolute;bottom:0;left:0}
.bloc-texte{background:var(--cream);border:1px solid var(--border);border-radius:5px;padding:9px 11px;font-size:13px;line-height:1.6;color:var(--text)}
.bloc-texte p{margin:0 0 6px}.bloc-texte p:last-child{margin-bottom:0}
.bloc-texte ul{padding-left:14px;margin:4px 0}.bloc-texte li{margin-bottom:3px}
.jours-grid{display:flex;flex-direction:column;gap:9px}
.jour-titre{font-size:14px;font-weight:700;color:var(--marine);padding-bottom:3px;border-bottom:1.5px solid var(--or);margin-bottom:6px}
.demi-journees{display:grid;grid-template-columns:1fr 1fr;gap:7px}
.demi-journee{background:var(--white);border:1px solid #e0d9c4;border-radius:5px;padding:5px 8px}
.dj-header{display:flex;align-items:center;gap:5px;margin-bottom:5px;flex-wrap:wrap}
.badge{display:inline-block;padding:2px 8px;border-radius:999px;background:var(--grad-h);color:var(--marine-dark);font-size:11px;font-weight:800;font-style:italic;white-space:nowrap;flex-shrink:0}
.dj-titre{font-size:13px;font-weight:700;color:var(--marine);line-height:1.3}
.dj-intro{font-size:11.5px;color:#888;font-style:italic;margin-bottom:4px}
.dj-list{list-style:none;padding:0;margin:0}
.dj-list li{font-size:12.5px;color:var(--text);padding:1.5px 0 1.5px 10px;position:relative;line-height:1.4}
.dj-list li::before{content:'▸';color:var(--or-alt);position:absolute;left:0;font-size:11px;top:3px}
.meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.meta-item{background:var(--white);border:1.5px solid var(--or);border-radius:5px;padding:5px 8px}
.meta-item.span2{grid-column:span 2}
.meta-label{font-size:11px;font-weight:700;color:var(--marine);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:3px}
.meta-value{font-size:13px;color:var(--text);font-weight:500;line-height:1.5}
.meta-value p{margin:0 0 4px}.meta-value p:last-child{margin-bottom:0}
.meta-value ul{padding-left:12px;margin:3px 0}.meta-value li{margin-bottom:2px}
.prog-section{display:flex;flex-direction:column;gap:10px}
.prog-dj-header{display:flex;align-items:center;gap:8px;margin-bottom:8px}
.prog-dj-titre{font-size:16px;font-weight:700;color:var(--marine)}
.prog-deux-cols{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.prog-col-head{font-size:11.5px;font-weight:700;color:var(--white);text-transform:uppercase;letter-spacing:0.4px;padding:4px 9px;background:var(--marine);border-radius:4px 4px 0 0}
.prog-col-body{background:var(--cream);border:1px solid var(--border);border-top:none;border-radius:0 0 4px 4px;padding:5px 8px;min-height:60px}
.prog-list{list-style:none;padding:0;margin:0}
.prog-list li{font-size:13px;color:var(--text);padding:2.5px 0 2.5px 11px;position:relative;line-height:1.4;border-bottom:1px dotted #e0d9c4}
.prog-list li:last-child{border-bottom:none}
.prog-list li::before{content:'▸';color:var(--or-alt);position:absolute;left:0;font-size:11px;top:4px}
.comp-head{font-size:11.5px;font-weight:700;color:var(--marine-dark);text-transform:uppercase;letter-spacing:0.4px;padding:4px 9px;background:var(--grad-h);border-radius:4px 4px 0 0;display:block;margin-top:8px}
.comp-body{background:var(--white);border:1px solid var(--border);border-top:none;border-radius:0 0 4px 4px;padding:6px 9px}
.comp-body ul{list-style:none;padding:0;margin:0}
.comp-body ul li{font-size:12.5px;color:var(--text);padding:2px 0 2px 12px;position:relative;line-height:1.4}
.comp-body ul li::before{content:'✦';color:var(--or-alt);position:absolute;left:0;font-size:10px;top:4px}
.equipe-grid{display:flex;flex-direction:column;gap:8px}
.equipe-item{background:var(--cream);border:1px solid var(--border);border-radius:5px;padding:6px 10px}
.equipe-item-title{font-size:13px;font-weight:700;color:var(--marine);margin-bottom:5px;padding-bottom:3px;border-bottom:1.5px solid var(--or)}
.equipe-item-body{font-size:13px;color:var(--text);line-height:1.55}
.equipe-item-body p{margin:0 0 4px}.equipe-item-body p:last-child{margin-bottom:0}
.equipe-item-body ul{padding-left:12px;margin:3px 0}
.eval-grid{display:flex;flex-direction:column;gap:7px}
.eval-item{background:var(--white);border-left:3px solid var(--or-alt);padding:5px 9px;border-radius:0 5px 5px 0}
.eval-title{font-size:12px;font-weight:700;color:var(--marine);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:3px}
.eval-body{font-size:13px;color:var(--text);line-height:1.5}
.eval-body p{margin:0 0 4px}.eval-body p:last-child{margin-bottom:0}
.eval-body ul{padding-left:12px;margin:3px 0}
.moyens-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px}
.moyen-item{background:var(--cream);border:1px solid var(--border);border-radius:5px;padding:5px 8px}
.moyen-item.span2{grid-column:span 2}
.moyen-title{font-size:11.5px;font-weight:700;color:var(--marine);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:3px}
.moyen-body{font-size:13px;color:var(--text);line-height:1.5}
.moyen-body p{margin:0 0 4px}.moyen-body p:last-child{margin-bottom:0}
.moyen-body ul{padding-left:12px;margin:3px 0}
.contact-bloc{background:var(--grad-h);border-radius:7px;padding:13px 16px;margin-top:auto}
.contact-title{font-size:17px;font-weight:800;color:var(--marine-dark);margin-bottom:2px}
.contact-sub{font-size:11.5px;color:var(--marine-dark);margin-bottom:9px}
.contact-persons{margin-bottom:9px}
.contact-person{font-size:13px;font-weight:700;color:var(--marine-dark);margin-bottom:2px}
.contact-footer-row{display:flex;justify-content:space-between;align-items:flex-end}
.contact-adresse{font-size:11.5px;color:var(--marine-dark);line-height:1.6}
.contact-logo img{width:65px;height:auto;object-fit:contain}
.page-footer{padding:4px 16px 5px 12px;display:flex;justify-content:flex-end;border-top:1px solid #f0e8d0;flex-shrink:0}
.page-num{font-size:11px;color:#bbb;font-weight:500}
@media print {
	html,body{background:#fff !important;margin:0 !important;padding:0 !important}
	#acdc-programme-content{margin:0 !important;padding:0 !important}
	.page{margin:0 !important;box-shadow:none;width:210mm;height:297mm;overflow:hidden;page-break-after:always;break-after:page}
}
@page{size:A4;margin:0}
</style>
</head>
<body class="<?php echo $auto_pdf ? 'is-pdf-auto' : ''; ?>">
<?php
		$html_head = ob_get_clean();
		echo $html_head; // phpcs:ignore

		// ── BARRE D'ACTIONS FLOTTANTE ──
		$titre_display = esc_html( $d['titre'] );

		echo '<div id="acdc-print-bar" style="'
			. 'position:fixed;top:0;left:0;right:0;z-index:9999;'
			. 'background:linear-gradient(90deg,#174a6d 0%,#1C2B4A 100%);'
			. 'padding:9px 24px;'
			. 'display:flex;align-items:center;justify-content:space-between;'
			. 'box-shadow:0 2px 12px rgba(0,0,0,.35);'
			. 'font-family:\'Rubik\',sans-serif;">';

		echo '<div style="color:#fff;font-size:14px;font-weight:600;">'
			. '<span style="color:#c9a84c;font-weight:800;">ACDC Formation</span>'
			. ' &nbsp;·&nbsp; Programme : ' . $titre_display
			. '</div>';

		echo '<div style="display:flex;gap:10px;">';

		// Bouton Imprimer
		echo '<button onclick="window.print()" style="'
			. 'background:#fff;color:#174a6d;border:2px solid #c9a84c;border-radius:5px;'
			. 'padding:8px 16px;font-family:\'Rubik\',sans-serif;'
			. 'font-size:13px;font-weight:700;cursor:pointer;'
			. 'display:flex;align-items:center;gap:7px;">'
			. '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'
			. '<polyline points="6 9 6 2 18 2 18 9"/>'
			. '<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>'
			. '<rect x="6" y="14" width="12" height="8"/>'
			. '</svg> Imprimer'
			. '</button>';

		// Bouton Télécharger PDF
		echo '<button type="button" id="acdc-download-pdf-btn" onclick="acdcGenerateProgrammePdf(event)" style="'
			. 'background:linear-gradient(90deg,#6B3F1D 0%,#B7772E 17%,#E9C77C 34%,#D7A24B 50%,#F3E3BF 67%,#C28A3A 84%,#8A5A2B 100%);'
			. 'color:#1C2B4A;border:none;border-radius:5px;'
			. 'padding:8px 16px;font-family:\'Rubik\',sans-serif;'
			. 'font-size:13px;font-weight:700;cursor:pointer;'
			. 'display:flex;align-items:center;gap:7px;">'
			. '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'
			. '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>'
			. '<polyline points="7 10 12 15 17 10"/>'
			. '<line x1="12" y1="15" x2="12" y2="3"/>'
			. '</svg> Télécharger en PDF'
			. '</button>';

		echo '</div></div>';
		echo '<div style="height:52px;" class="acdc-print-spacer"></div>';
		echo '<style>@media print{#acdc-print-bar,.acdc-print-spacer,#acdc-pdf-loader{display:none !important;}} body.is-pdf-auto #acdc-print-bar, body.is-pdf-auto .acdc-print-spacer{display:none !important;}</style>';

		if ( $auto_pdf ) {
			echo '<div id="acdc-pdf-loader" style="position:fixed;inset:0;z-index:10000;background:rgba(255,255,255,.94);display:flex;align-items:center;justify-content:center;font-family:Rubik,sans-serif;color:#174a6d;text-align:center;padding:24px;"><div><strong style="font-size:18px;">Préparation du PDF en cours…</strong><br><span style="font-size:13px;color:#555;">Le document va s\'ouvrir automatiquement dans le lecteur PDF du navigateur.</span></div></div>';
		}

		echo '<div id="acdc-programme-content">';
		$page_num = 1;

		// PAGE 1 — Pourquoi + Objectif général
		$this->prog_pdf_open_page( $d, $page_num, $logo_url, $hero_url );
		echo '<div class="page-content">';
		if ( $d['pourquoi'] ) {
			echo '<div>';
			echo '<div class="section-title">Pourquoi cette formation ?</div>';
			echo '<div class="bloc-texte">' . $this->prog_pdf_clean_html( $d['pourquoi'] ) . '</div>';
			echo '</div>';
		}
		if ( $d['objectif_general'] ) {
			echo '<div>';
			echo '<div class="section-title">Objectif de la formation</div>';
			echo '<div class="bloc-texte">' . $this->prog_pdf_clean_html( $d['objectif_general'] ) . '</div>';
			echo '</div>';
		}
		echo '</div>';
		$this->prog_pdf_close_page( $page_num );
		$page_num++;

		// PAGE 2 — Objectifs pédagogiques (uniquement si objectifs_blocs présents)
		if ( ! empty( $d['objectifs_blocs'] ) ) {
			$this->prog_pdf_open_page( $d, $page_num, $logo_url, $hero_url );
			echo '<div class="page-content">';
			echo '<div class="section-title">Objectifs pédagogiques</div>';
			echo '<div class="jours-grid">';
			foreach ( $d['objectifs_blocs'] as $day ) {
				if ( ! is_array( $day ) ) { continue; }
				$jour_label = esc_html( $day['jour'] ?? 'Jour' );
				echo '<div class="jour-block">';
				echo '<div class="jour-titre">' . $jour_label . '</div>';
				echo '<div class="demi-journees">';
				foreach ( (array) ( $day['demi_journees'] ?? array() ) as $half ) {
					if ( ! is_array( $half ) ) { continue; }
					$moment    = esc_html( $half['moment'] ?? '' );
					$titre     = esc_html( $half['titre'] ?? '' );
					$objectifs = array_filter( array_map( function( $x ) { return strip_tags( (string) $x ); }, (array) ( $half['objectifs'] ?? array() ) ) );
					echo '<div class="demi-journee">';
					echo '<div class="dj-header">';
					echo '<span class="badge">' . $moment . '</span>';
					if ( $titre ) echo '<span class="dj-titre">' . $titre . '</span>';
					echo '</div>';
					echo '<div class="dj-intro">À l\'issue de cette demi-journée, l\'apprenant sera capable de :</div>';
					if ( $objectifs ) {
						echo '<ul class="dj-list">';
						foreach ( $objectifs as $obj ) {
							echo '<li>' . esc_html( $obj ) . '</li>';
						}
						echo '</ul>';
					}
					echo '</div>';
				}
				echo '</div></div>';
			}
			echo '</div></div>';
			$this->prog_pdf_close_page( $page_num );
			$page_num++;
		}

		// PAGE — Informations pédagogiques
		$this->prog_pdf_open_page( $d, $page_num, $logo_url, $hero_url );
		echo '<div class="page-content">';
		echo '<div class="section-title">Informations pédagogiques</div>';
		echo '<div class="meta-grid">';
		if ( $d['public'] ) {
			echo '<div class="meta-item"><div class="meta-label">Public cible</div><div class="meta-value">' . $this->prog_pdf_clean_html( $d['public'] ) . '</div></div>';
		}
		if ( $d['prerequis'] ) {
			echo '<div class="meta-item"><div class="meta-label">Prérequis</div><div class="meta-value">' . $this->prog_pdf_clean_html( $d['prerequis'] ) . '</div></div>';
		}
		if ( $d['duree'] ) {
			echo '<div class="meta-item"><div class="meta-label">Durée</div><div class="meta-value">' . esc_html( $d['duree'] ) . '</div></div>';
		}
		if ( ! empty( $d['modalites'] ) ) {
			echo '<div class="meta-item"><div class="meta-label">Modalités pédagogiques</div><div class="meta-value">' . esc_html( implode( ', ', $d['modalites'] ) ) . '</div></div>';
		}
		if ( $d['effectif_min'] || $d['effectif_max'] ) {
			$eff = array();
			if ( $d['effectif_min'] ) $eff[] = 'Minimum : ' . (int) $d['effectif_min'];
			if ( $d['effectif_max'] ) $eff[] = 'Maximum : ' . (int) $d['effectif_max'];
			echo '<div class="meta-item"><div class="meta-label">Effectif</div><div class="meta-value">' . implode( '<br>', $eff ) . '</div></div>';
		}
		if ( ! empty( $d['tarifs'] ) ) {
			$tarifs_html = '';
			foreach ( $d['tarifs'] as $label => $val ) {
				if ( $val ) $tarifs_html .= esc_html( $label ) . ' : ' . esc_html( $val ) . ' € HT<br>';
			}
			if ( $tarifs_html ) {
				echo '<div class="meta-item"><div class="meta-label">Tarifs</div><div class="meta-value">' . rtrim( $tarifs_html, '<br>' ) . '</div></div>';
			}
		}
		if ( $d['cpf'] || $d['cpf_code'] ) {
			$cpf_val = '';
			if ( $d['cpf'] )      $cpf_val .= esc_html( $d['cpf'] ) . '<br>';
			if ( $d['cpf_code'] ) $cpf_val .= 'Code : ' . esc_html( $d['cpf_code'] );
			echo '<div class="meta-item"><div class="meta-label">Éligibilité CPF</div><div class="meta-value">' . $cpf_val . '</div></div>';
		}
		if ( ! empty( $d['financement'] ) ) {
			echo '<div class="meta-item span2"><div class="meta-label">Financements acceptés</div><div class="meta-value">' . esc_html( implode( ' — ', $d['financement'] ) ) . '</div></div>';
		}
		echo '</div></div>';
		$this->prog_pdf_close_page( $page_num );
		$page_num++;

		// PAGES PROGRAMME DÉTAILLÉ — 1 page par demi-journée
		if ( ! empty( $d['programme_blocs'] ) ) {
			foreach ( $d['programme_blocs'] as $day ) {
				$jour_label = esc_html( $day['jour'] ?? 'Jour' );
				foreach ( (array) ( $day['demi_journees'] ?? array() ) as $half ) {
					$moment      = esc_html( $half['moment'] ?? '' );
					$titre_dj    = esc_html( $half['titre'] ?? '' );
					$contenus    = array_filter( array_map( 'strip_tags', (array) ( $half['contenus'] ?? array() ) ) );
					$ateliers    = array_filter( array_map( 'strip_tags', (array) ( $half['ateliers'] ?? array() ) ) );
					$competences = array_filter( array_map( 'strip_tags', (array) ( $half['competences'] ?? array() ) ) );

					$this->prog_pdf_open_page( $d, $page_num, $logo_url, $hero_url );
					echo '<div class="page-content">';
					echo '<div class="section-title">Programme détaillé</div>';
					echo '<div class="prog-section">';

					echo '<div>';
					echo '<div class="prog-dj-header">';
					echo '<span class="badge">' . $moment . '</span>';
					echo '<div>';
					echo '<div style="font-size:13px;color:#888;font-weight:500;">' . $jour_label . '</div>';
					if ( $titre_dj ) echo '<div class="prog-dj-titre">' . $titre_dj . '</div>';
					echo '</div>';
					echo '</div>';
					echo '</div>';

					echo '<div class="prog-deux-cols">';

					// Colonne gauche : contenus
					echo '<div>';
					echo '<div class="prog-col-head">Contenus pédagogiques</div>';
					echo '<div class="prog-col-body">';
					if ( $contenus ) {
						echo '<ul class="prog-list">';
						foreach ( $contenus as $c ) echo '<li>' . esc_html( $c ) . '</li>';
						echo '</ul>';
					}
					echo '</div>';
					if ( $competences ) {
						echo '<span class="comp-head">Compétences développées</span>';
						echo '<div class="comp-body"><ul>';
						foreach ( $competences as $comp ) echo '<li>' . esc_html( $comp ) . '</li>';
						echo '</ul></div>';
					}
					echo '</div>';

					// Colonne droite : ateliers (ou objectifs OPO si ateliers absents)
					$right_items = ! empty( $ateliers ) ? $ateliers : array_filter( array_map( 'strip_tags', (array) ( $half['objectifs'] ?? array() ) ) );
					$right_head  = ! empty( $ateliers ) ? 'Ateliers pratiques' : 'Objectifs pédagogiques';
					echo '<div>';
					echo '<div class="prog-col-head">' . esc_html( $right_head ) . '</div>';
					echo '<div class="prog-col-body">';
					if ( $right_items ) {
						echo '<ul class="prog-list">';
						foreach ( $right_items as $a ) echo '<li>' . esc_html( $a ) . '</li>';
						echo '</ul>';
					}
					echo '</div>';
					echo '</div>';

					echo '</div>'; // prog-deux-cols
					echo '</div>'; // prog-section
					echo '</div>'; // page-content
					$this->prog_pdf_close_page( $page_num );
					$page_num++;
				}
			}
		}

		// PAGE — Équipe pédagogique & accessibilité
		$this->prog_pdf_open_page( $d, $page_num, $logo_url, $hero_url );
		echo '<div class="page-content">';
		echo '<div class="section-title">Équipe pédagogique et accessibilité</div>';
		echo '<div class="equipe-grid">';
		if ( $d['formateurs'] ) {
			echo '<div class="equipe-item"><div class="equipe-item-title">Formateur(s) référent(s)</div><div class="equipe-item-body">' . $this->prog_pdf_clean_html( $d['formateurs'] ) . '</div></div>';
		}
		if ( $d['accessibilite'] ) {
			echo '<div class="equipe-item"><div class="equipe-item-title">Accessibilité handicap</div><div class="equipe-item-body">' . $this->prog_pdf_clean_html( $d['accessibilite'] ) . '</div></div>';
		}
		if ( $d['referent_handicap'] ) {
			echo '<div class="equipe-item"><div class="equipe-item-title">Référent handicap</div><div class="equipe-item-body">' . esc_html( $d['referent_handicap'] ) . '</div></div>';
		}
		echo '</div></div>';
		$this->prog_pdf_close_page( $page_num );
		$page_num++;

		// PAGE — Évaluation & validation
		$this->prog_pdf_open_page( $d, $page_num, $logo_url, $hero_url );
		echo '<div class="page-content">';
		echo '<div class="section-title">Évaluation et validation</div>';
		echo '<div class="eval-grid">';
		if ( $d['sanction'] ) {
			echo '<div class="eval-item"><div class="eval-title">Validation de la formation</div><div class="eval-body">' . $this->prog_pdf_clean_html( $d['sanction'] ) . '</div></div>';
		}
		if ( $d['eval_entree'] ) {
			echo '<div class="eval-item"><div class="eval-title">Évaluation de positionnement</div><div class="eval-body">' . $this->prog_pdf_clean_html( $d['eval_entree'] ) . '</div></div>';
		}
		if ( $d['eval_sortie'] ) {
			echo '<div class="eval-item"><div class="eval-title">Évaluation des acquis</div><div class="eval-body">' . $this->prog_pdf_clean_html( $d['eval_sortie'] ) . '</div></div>';
		}
		echo '</div></div>';
		$this->prog_pdf_close_page( $page_num );
		$page_num++;

		// PAGE — Moyens & organisation
		$this->prog_pdf_open_page( $d, $page_num, $logo_url, $hero_url );
		echo '<div class="page-content">';
		echo '<div class="section-title">Moyens et organisation</div>';
		echo '<div class="moyens-grid">';
		if ( $d['moyens_pedago'] ) {
			echo '<div class="moyen-item span2"><div class="moyen-title">Moyens pédagogiques et techniques</div><div class="moyen-body">' . $this->prog_pdf_clean_html( $d['moyens_pedago'] ) . '</div></div>';
		}
		if ( $d['suivi'] ) {
			echo '<div class="moyen-item"><div class="moyen-title">Suivi de l\'exécution</div><div class="moyen-body">' . $this->prog_pdf_clean_html( $d['suivi'] ) . '</div></div>';
		}
		if ( $d['lieu_acces'] ) {
			echo '<div class="moyen-item"><div class="moyen-title">Lieu et délais d\'accès</div><div class="moyen-body">' . $this->prog_pdf_clean_html( $d['lieu_acces'] ) . '</div></div>';
		}
		if ( $d['ressources'] ) {
			echo '<div class="moyen-item span2"><div class="moyen-title">Ressources remises</div><div class="moyen-body">' . $this->prog_pdf_clean_html( $d['ressources'] ) . '</div></div>';
		}
		echo '</div></div>';
		$this->prog_pdf_close_page( $page_num );
		$page_num++;

		// PAGE — Informations administratives + Contact
		$this->prog_pdf_open_page( $d, $page_num, $logo_url, $hero_url );
		echo '<div class="page-content">';
		echo '<div class="section-title">Informations administratives</div>';
		echo '<div class="eval-grid">';
		if ( $d['demarches'] ) {
			echo '<div class="eval-item"><div class="eval-title">Démarches administratives</div><div class="eval-body">' . $this->prog_pdf_clean_html( $d['demarches'] ) . '</div></div>';
		}
		if ( $d['annulation'] ) {
			echo '<div class="eval-item"><div class="eval-title">Annulation / rétractation</div><div class="eval-body">' . $this->prog_pdf_clean_html( $d['annulation'] ) . '</div></div>';
		}
		echo '</div>';

		// Bloc contact
		echo '<div class="contact-bloc" style="margin-top:auto;">';
		echo '<div class="contact-title">NOUS CONTACTER</div>';
		echo '<div class="contact-sub">À votre disposition pour plus d\'informations :</div>';
		echo '<div class="contact-persons">';
		foreach ( $this->get_prog_pdf_contacts() as $c ) {
			echo '<div class="contact-person">' . esc_html( $c['nom'] ) . ' : ' . esc_html( $c['tel'] ) . ' — ' . esc_html( $c['email'] ) . '</div>';
		}
		echo '</div>';
		echo '<div class="contact-footer-row">';
		echo '<div class="contact-adresse">';
		echo '<strong>ACDC Formation</strong><br>';
		echo esc_html( $this->get_prog_pdf_adresse() ) . '<br>';
		echo '<a href="' . esc_url( $this->get_prog_pdf_site() ) . '" style="color:var(--marine-dark);font-weight:600;">' . esc_html( $this->get_prog_pdf_site() ) . '</a><br>';
		echo 'Siret : ' . esc_html( $this->get_prog_pdf_siret() ) . '<br>';
		echo 'NDA : ' . esc_html( $this->get_prog_pdf_nda() );
		echo '</div>';
		echo '<div class="contact-logo"><img src="' . esc_url( $logo_url ) . '" alt="ACDC Formation"></div>';
		echo '</div>';
		echo '</div>'; // contact-bloc

		echo '</div>'; // page-content
		$this->prog_pdf_close_page( $page_num );

		echo '</div>'; // #acdc-programme-content

		$this->prog_pdf_print_js( $auto_pdf, $pdf_filename );

		echo '</body></html>';
		return '';
	}

	/* ─────────────────────────────────────────
	   HELPER : NOM DU FICHIER PDF
	───────────────────────────────────────── */
	private function get_prog_pdf_filename( $title ) {
		$slug = sanitize_title( $title );
		if ( ! $slug ) {
			$slug = 'programme-formation';
		}
		return 'Programme-' . $slug . '.pdf';
	}

	/* ─────────────────────────────────────────
	   HELPER : OUVERTURE / FERMETURE DE PAGE
	───────────────────────────────────────── */
	private function prog_pdf_open_page( array $d, $page_num, $logo_url, $hero_url ) {
		$t    = esc_html( $d['titre'] );
		$date = esc_html( $d['date_maj'] );
		echo '<div class="page">';
		echo '<div class="sidebar"><span class="sidebar-label">PROGRAMME</span></div>';
		echo '<div class="page-inner">';
		echo '<div class="page-header">';
		echo '<div class="header-left">';
		echo '<div class="date-line">Date de dernière mise à jour : ' . $date . '</div>';
		echo '<div class="formation-lbl">FORMATION :</div>';
		echo '<div class="titre-formation">' . $t . '</div>';
		echo '</div>';
		echo '<img class="page-logo" src="' . esc_url( $logo_url ) . '" alt="Logo ACDC Formation">';
		echo '</div>';
		echo '<img class="hero-img" src="' . esc_url( $hero_url ) . '" alt="Image de formation">';
	}

	private function prog_pdf_close_page( $page_num ) {
		echo '<div class="page-footer"><span class="page-num">Page ' . (int) $page_num . '</span></div>';
		echo '</div>'; // page-inner
		echo '</div>'; // page
	}

	/* ─────────────────────────────────────────
	   HELPER : NETTOYAGE HTML
	───────────────────────────────────────── */
	private function prog_pdf_clean_html( $raw ) {
		if ( ! $raw ) return '';

		// Si le contenu contient déjà du HTML → nettoyer et retourner
		if ( preg_match( '/<[a-z][\s\S]*>/i', $raw ) ) {
			$allowed = array(
				'p'      => array(),
				'br'     => array(),
				'strong' => array(),
				'em'     => array(),
				'b'      => array(),
				'i'      => array(),
				'ul'     => array(),
				'ol'     => array(),
				'li'     => array(),
				'span'   => array(),
			);
			return wp_kses( $raw, $allowed );
		}

		// Texte brut (issu de html_to_text() Manager) → convertir en HTML structuré
		// Séparer par saut de ligne
		$lines  = preg_split( '/\r\n|\r|\n/', $raw );
		$output = '';
		$in_ul  = false;

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( $line === '' ) {
				if ( $in_ul ) {
					$output .= '</ul>';
					$in_ul = false;
				}
				continue;
			}

			// Ligne à puce : commence par • ou - suivi d'un espace
			if ( preg_match( '/^[•\-]\s+(.+)$/', $line, $m ) ) {
				if ( ! $in_ul ) {
					$output .= '<ul>';
					$in_ul = true;
				}
				$output .= '<li>' . esc_html( $m[1] ) . '</li>';
				continue;
			}

			// Fin d'une liste si on sort du mode puce
			if ( $in_ul ) {
				$output .= '</ul>';
				$in_ul = false;
			}

			// Ligne de la forme "Clé courte :" seule (titre de section)
			// ex: "Moyens pédagogiques" ou "Moyens techniques"
			if ( preg_match( '/^([^:]{1,60})\s*:\s*$/', $line, $m ) ) {
				$output .= '<p><strong>' . esc_html( rtrim( $m[1] ) ) . ' :</strong></p>';
				continue;
			}

			// Ligne de la forme "Clé courte : valeur"
			// ex: "Nom : David Contal" / "Qualification pédagogique : ..."
			// Clé = tout ce qui précède le premier ":" et fait ≤ 5 mots
			if ( preg_match( '/^([^:]{1,50}?)\s*:\s*(.+)$/', $line, $m ) ) {
				$key   = trim( $m[1] );
				$value = trim( $m[2] );
				$parts = preg_split( '/\s+/u', trim( $key ), -1, PREG_SPLIT_NO_EMPTY );
				$word_count = is_array( $parts ) ? count( $parts ) : 0;
				if ( $word_count <= 4 ) {
					$output .= '<p><strong>' . esc_html( $key ) . ' :</strong> ' . esc_html( $value ) . '</p>';
					continue;
				}
			}

			// Ligne normale
			$output .= '<p>' . esc_html( $line ) . '</p>';
		}

		if ( $in_ul ) {
			$output .= '</ul>';
		}

		return $output;
	}

	/* ─────────────────────────────────────────
	   HELPER : SCRIPT GÉNÉRATION PDF NAVIGATEUR
	───────────────────────────────────────── */
	private function prog_pdf_print_js( $auto_pdf, $pdf_filename ) {
		$auto        = $auto_pdf ? 'true' : 'false';
		$filename    = wp_json_encode( $pdf_filename );
		$vendor_base = trailingslashit( ACDC_OF_SAAS_URL ) . 'assets/js/vendor/';
		$html2canvas = wp_json_encode( $vendor_base . 'html2canvas.min.js' );
		$jspdf       = wp_json_encode( $vendor_base . 'jspdf.umd.min.js' );
		?>
<script>
(function () {
	'use strict';
	var ACDC_PDF_AUTO = <?php echo $auto; ?>;
	var ACDC_PDF_FILENAME = <?php echo $filename; ?>;
	var acdcPdfGenerationRunning = false;

	function acdcLoadScript(src) {
		return new Promise(function (resolve, reject) {
			var existing = document.querySelector('script[src="' + src + '"]');
			if (existing) {
				if (existing.dataset.loaded === '1') { resolve(); return; }
				existing.addEventListener('load', resolve, { once: true });
				existing.addEventListener('error', reject, { once: true });
				return;
			}
			var script = document.createElement('script');
			script.src = src;
			script.async = true;
			script.onload = function () { script.dataset.loaded = '1'; resolve(); };
			script.onerror = reject;
			document.head.appendChild(script);
		});
	}

	function acdcWaitForImages() {
		var images = Array.prototype.slice.call(document.images || []);
		return Promise.all(images.map(function (img) {
			if (img.complete) { return Promise.resolve(); }
			return new Promise(function (resolve) { img.onload = resolve; img.onerror = resolve; });
		}));
	}

	async function acdcWaitForAssets() {
		if (document.fonts && document.fonts.ready) {
			try { await document.fonts.ready; } catch (e) {}
		}
		await acdcWaitForImages();
	}

	function acdcSetButtonState(isLoading) {
		var button = document.getElementById('acdc-download-pdf-btn');
		if (!button) { return; }
		if (isLoading) {
			button.dataset.originalHtml = button.innerHTML;
			button.disabled = true;
			button.style.opacity = '.72';
			button.style.cursor = 'wait';
			button.innerHTML = 'Préparation du PDF…';
			return;
		}
		button.disabled = false;
		button.style.opacity = '';
		button.style.cursor = 'pointer';
		if (button.dataset.originalHtml) { button.innerHTML = button.dataset.originalHtml; }
	}

	function acdcShowLoader(message) {
		var loader = document.getElementById('acdc-pdf-loader');
		if (!loader) { return; }
		loader.style.display = 'flex';
		if (message) {
			loader.innerHTML = '<div><strong style="font-size:18px;">' + message + '</strong><br><span style="font-size:13px;color:#555;">Merci de patienter quelques secondes.</span></div>';
		}
	}

	async function acdcGenerateProgrammePdf(event) {
		if (event && event.preventDefault) { event.preventDefault(); }
		if (acdcPdfGenerationRunning) { return; }
		acdcPdfGenerationRunning = true;
		acdcSetButtonState(true);
		acdcShowLoader('Préparation du PDF en cours…');
		try {
			await acdcLoadScript(<?php echo $html2canvas; ?>);
			await acdcLoadScript(<?php echo $jspdf; ?>);
			await acdcWaitForAssets();
			if (!window.html2canvas || !window.jspdf || !window.jspdf.jsPDF) {
				throw new Error('Bibliothèques PDF indisponibles.');
			}
			var pages = Array.prototype.slice.call(document.querySelectorAll('#acdc-programme-content .page'));
			if (!pages.length) { throw new Error('Aucune page de programme trouvée.'); }
			var pdf = new window.jspdf.jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4', compress: true });
			if (pdf.setProperties) {
				pdf.setProperties({ title: ACDC_PDF_FILENAME.replace(/\.pdf$/i, ''), creator: 'ACDC Formation SAAS' });
			}
			for (var i = 0; i < pages.length; i++) {
				var page = pages[i];
				var canvas = await window.html2canvas(page, {
					scale: 2, useCORS: true, allowTaint: true, backgroundColor: '#ffffff',
					logging: false, windowWidth: page.scrollWidth, windowHeight: page.scrollHeight
				});
				var imageData = canvas.toDataURL('image/jpeg', 0.96);
				if (i > 0) { pdf.addPage('a4', 'portrait'); }
				pdf.addImage(imageData, 'JPEG', 0, 0, 210, 297);
			}
			var blobUrl = pdf.output('bloburl');
			var dlLink = document.createElement('a');
			dlLink.href = blobUrl;
			dlLink.download = ACDC_PDF_FILENAME;
			dlLink.style.display = 'none';
			document.body.appendChild(dlLink);
			dlLink.click();
			window.setTimeout(function () {
				document.body.removeChild(dlLink);
				window.URL.revokeObjectURL(blobUrl);
			}, 1000);
		} catch (error) {
			console.error('[ACDC Formation SAAS] Génération PDF impossible :', error);
			alert("Le PDF n'a pas pu être généré automatiquement. La fenêtre d'impression va s'ouvrir en solution de secours. Choisissez « Enregistrer au format PDF » si besoin.");
			window.print();
		} finally {
			acdcSetButtonState(false);
			acdcPdfGenerationRunning = false;
		}
	}

	window.acdcGenerateProgrammePdf = acdcGenerateProgrammePdf;

	if (ACDC_PDF_AUTO) {
		window.addEventListener('load', function () {
			window.setTimeout(function () { acdcGenerateProgrammePdf(); }, 350);
		});
	}
})();
</script>
		<?php
	}
}
