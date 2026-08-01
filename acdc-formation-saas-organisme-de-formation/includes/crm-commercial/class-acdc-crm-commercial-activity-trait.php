<?php
/**
 * ACDC 3.25.86 — Journal d'activite commerciale des prospects.
 * Tracabilite exhaustive des interactions : appels, presentiels, visio, SMS, notes
 * (saisie manuelle) + e-mails (individuels et campagnes) remontes automatiquement
 * depuis l'archive marketing existante, fusionnes dans une timeline chronologique.
 *
 * Lot 1 : table acdc_of_prospect_activities + saisie manuelle + journal.
 * Lot 2 : fusion des e-mails archives au rendu (aucune modif du pipeline d'envoi).
 *
 * @package ACDC_Formation_SAAS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ACDC_Crm_Commercial_Activity_Trait {

	/* ====================================================================
	 *  Meta des types d'interaction
	 * ==================================================================== */

	private function get_prospect_activity_types_meta() {
		return array(
			'call'    => array( 'label' => 'Appel téléphonique',    'channel' => 'Téléphone',       'bg' => '#e1f5ee', 'fg' => '#0f6e56', 'manual' => true ),
			'meeting' => array( 'label' => 'Rendez-vous présentiel', 'channel' => 'Présentiel',     'bg' => '#faeeda', 'fg' => '#854f0b', 'manual' => true ),
			'video'   => array( 'label' => 'Visioconférence',        'channel' => 'Visioconférence', 'bg' => '#eeedfe', 'fg' => '#3c3489', 'manual' => true ),
			'sms'     => array( 'label' => 'SMS',                    'channel' => 'SMS',             'bg' => '#e1f5ee', 'fg' => '#0f6e56', 'manual' => true ),
			'note'    => array( 'label' => 'Note',                   'channel' => '',                'bg' => '#f1efe8', 'fg' => '#5f5e5a', 'manual' => true ),
			'rdv'     => array( 'label' => 'Rendez-vous planifié',    'channel' => 'Rendez-vous',     'bg' => '#fef0e7', 'fg' => '#9a3412', 'manual' => false ),
			'email'   => array( 'label' => 'E-mail',                 'channel' => 'E-mail',          'bg' => '#e6f1fb', 'fg' => '#185fa5', 'manual' => false ),
		);
	}

	private function get_prospect_activity_manual_keys() {
		return array( 'call', 'meeting', 'video', 'sms', 'note' );
	}

	private function get_prospect_activity_directions() {
		return array( '' => '—', 'out' => 'Sortant', 'in' => 'Entrant' );
	}

	/* ====================================================================
	 *  Insertion centrale (manuelle ET, plus tard, automatique)
	 * ==================================================================== */

	private function log_prospect_activity( $args ) {
		global $wpdb;
		$args = (array) $args;
		if ( empty( $args['prospect_id'] ) ) {
			return 0;
		}
		$this->ensure_storage_ready();
		$now  = current_time( 'mysql' );
		$type = isset( $args['activity_type'] ) ? sanitize_key( $args['activity_type'] ) : 'note';
		$meta = $this->get_prospect_activity_types_meta();
		if ( ! isset( $meta[ $type ] ) ) {
			$type = 'note';
		}
		$channel = isset( $args['channel'] ) && '' !== $args['channel'] ? sanitize_text_field( $args['channel'] ) : (string) $meta[ $type ]['channel'];
		$occurred_at = isset( $args['occurred_at'] ) && $args['occurred_at'] ? $args['occurred_at'] : $now;
		$data = array(
			'prospect_id'   => (int) $args['prospect_id'],
			'activity_type' => $type,
			'direction'     => isset( $args['direction'] ) ? sanitize_key( $args['direction'] ) : '',
			'channel'       => $channel,
			'subject'       => isset( $args['subject'] ) ? sanitize_text_field( $args['subject'] ) : '',
			'summary'       => isset( $args['summary'] ) ? sanitize_textarea_field( $args['summary'] ) : '',
			'outcome'       => isset( $args['outcome'] ) ? sanitize_text_field( $args['outcome'] ) : '',
			'ref_type'      => isset( $args['ref_type'] ) ? sanitize_key( $args['ref_type'] ) : '',
			'ref_id'        => isset( $args['ref_id'] ) ? sanitize_text_field( (string) $args['ref_id'] ) : '',
			'occurred_at'   => $occurred_at,
			'created_by'    => isset( $args['created_by'] ) ? sanitize_text_field( $args['created_by'] ) : '',
			'created_at'    => $now,
			'updated_at'    => $now,
		);
		$wpdb->insert( $this->prospect_activity_table, $data );
		return (int) $wpdb->insert_id;
	}

	private function get_prospect_manual_activities( $prospect_id ) {
		global $wpdb;
		$prospect_id = (int) $prospect_id;
		if ( ! $prospect_id ) {
			return array();
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->prospect_activity_table} WHERE prospect_id = %d ORDER BY occurred_at DESC, id DESC", $prospect_id ) );
	}

	private function get_prospect_activity_counts_map() {
		global $wpdb;
		$map   = array();
		$table = $this->prospect_activity_table;
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return $map;
		}
		$rows = $wpdb->get_results( "SELECT prospect_id, COUNT(*) AS cnt, MAX(occurred_at) AS last_at FROM {$table} GROUP BY prospect_id" );
		if ( $rows ) {
			foreach ( $rows as $r ) {
				$map[ (int) $r->prospect_id ] = array( 'count' => (int) $r->cnt, 'last_at' => (string) $r->last_at );
			}
		}
		return $map;
	}

	/* ====================================================================
	 *  Timeline unifiee (manuel + e-mails archives)
	 * ==================================================================== */

	private function build_prospect_activity_timeline( $prospect ) {
		$items = array();

		foreach ( $this->get_prospect_manual_activities( $prospect->id ) as $row ) {
			$items[] = array(
				'kind'       => 'manual',
				'id'         => (int) $row->id,
				'type'       => (string) $row->activity_type,
				'direction'  => (string) $row->direction,
				'channel'    => (string) $row->channel,
				'subject'    => (string) $row->subject,
				'summary'    => (string) $row->summary,
				'outcome'    => (string) $row->outcome,
				'when'       => (string) $row->occurred_at,
				'created_by' => (string) $row->created_by,
				'view_url'   => '',
			);
		}

		if ( method_exists( $this, 'get_marketing_archive_for_prospect' ) ) {
			foreach ( (array) $this->get_marketing_archive_for_prospect( $prospect ) as $entry ) {
				$to_list = ( ! empty( $entry['to'] ) && is_array( $entry['to'] ) ) ? implode( ', ', $entry['to'] ) : '';
				$status  = ! empty( $entry['status'] ) ? (string) $entry['status'] : '';
				$summary = 'Destinataire : ' . ( '' !== $to_list ? $to_list : '—' ) . ( '' !== $status ? ' — ' . $status : '' );
				$items[] = array(
					'kind'       => 'email',
					'id'         => isset( $entry['id'] ) ? (string) $entry['id'] : '',
					'type'       => 'email',
					'direction'  => 'out',
					'channel'    => 'E-mail',
					'subject'    => ! empty( $entry['subject'] ) ? (string) $entry['subject'] : 'E-mail',
					'summary'    => $summary,
					'outcome'    => '',
					'when'       => ! empty( $entry['sent_at'] ) ? (string) $entry['sent_at'] : '',
					'created_by' => '',
					'view_url'   => ( ! empty( $entry['id'] ) && method_exists( $this, 'get_archive_view_url_for_entry' ) ) ? $this->get_archive_view_url_for_entry( $entry['id'] ) : '',
				);
			}
		}

		if ( method_exists( $this, 'get_prospect_rdv_rows' ) ) {
			foreach ( (array) $this->get_prospect_rdv_rows( $prospect->id ) as $rdv ) {
				$is_cancelled = method_exists( $this, 'is_prospect_rdv_cancelled' ) ? $this->is_prospect_rdv_cancelled( $rdv ) : false;
				$rdv_comments = method_exists( $this, 'get_prospect_rdv_comments_text' ) ? $this->get_prospect_rdv_comments_text( $rdv ) : '';
				$rdv_post     = method_exists( $this, 'get_prospect_rdv_post_comment_text' ) ? $this->get_prospect_rdv_post_comment_text( $rdv ) : '';
				$summary_parts = array();
				if ( '' !== (string) $rdv_comments ) { $summary_parts[] = (string) $rdv_comments; }
				if ( '' !== (string) $rdv_post ) { $summary_parts[] = 'Suivi : ' . (string) $rdv_post; }
				$items[] = array(
					'kind'       => 'rdv',
					'id'         => (int) $rdv->id,
					'type'       => 'rdv',
					'direction'  => '',
					'channel'    => 'Rendez-vous',
					'subject'    => $is_cancelled ? 'Rendez-vous (annulé)' : 'Rendez-vous planifié',
					'summary'    => implode( ' — ', $summary_parts ),
					'outcome'    => $is_cancelled ? 'Annulé' : '',
					'when'       => ! empty( $rdv->rdv_at ) ? (string) $rdv->rdv_at : '',
					'created_by' => '',
					'view_url'   => '',
				);
			}
		}

		usort( $items, static function( $a, $b ) {
			$ta = ! empty( $a['when'] ) ? strtotime( $a['when'] ) : 0;
			$tb = ! empty( $b['when'] ) ? strtotime( $b['when'] ) : 0;
			if ( $ta === $tb ) {
				return 0;
			}
			return ( $tb < $ta ) ? -1 : 1;
		} );

		return $items;
	}

	/* ====================================================================
	 *  Rendu du journal (panneau + saisie rapide + timeline)
	 * ==================================================================== */

	private function render_front_prospect_activity_journal( $prospect ) {
		if ( ! $prospect || empty( $prospect->id ) ) {
			return;
		}
		$meta       = $this->get_prospect_activity_types_meta();
		$directions = $this->get_prospect_activity_directions();
		$timeline   = $this->build_prospect_activity_timeline( $prospect );
		$now_local  = current_time( 'Y-m-d\TH:i' );
		$post_url   = admin_url( 'admin-post.php' );
		$email_modal_id = 'acdc-direct-email-modal-prospect-' . (int) $prospect->id;
		$journal_to_email = method_exists( $this, 'get_prospect_primary_email' ) ? (string) $this->get_prospect_primary_email( $prospect ) : (string) $prospect->email;
		if ( '' === $journal_to_email ) {
			$journal_to_email = (string) $prospect->email;
		}
		$journal_to_name = trim( (string) $prospect->first_name . ' ' . (string) $prospect->last_name );
		if ( '' === $journal_to_name && ! empty( $prospect->company_name ) ) {
			$journal_to_name = (string) $prospect->company_name;
		}
		?>
		<div class="acdc-panel acdc-mb-18" id="acdc-prospect-activity-journal">
			<div class="acdc-journal-head">
				<h3 style="margin:0;">Journal d'activité</h3>
				<span class="acdc-journal-count"><?php echo (int) count( $timeline ); ?> interaction<?php echo count( $timeline ) > 1 ? 's' : ''; ?></span>
			</div>
			<p class="acdc-field-help">Tracez tout ce que vous faites avec ce prospect : appels, rendez-vous présentiels, visioconférences, SMS, notes. Les e-mails envoyés (individuels ou via une campagne) remontent automatiquement ici.</p>

			<div class="acdc-journal-quick">
				<button type="button" class="acdc-button acdc-button-soft" data-acdc-journal-toggle="1">+ Noter une interaction</button>
				<?php if ( '' !== $journal_to_email ) : ?><button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-open="<?php echo esc_attr( $email_modal_id ); ?>">&#x2709; Envoyer un e-mail</button><?php endif; ?>
			</div>

			<form class="acdc-form acdc-journal-form" method="post" action="<?php echo esc_url( $post_url ); ?>" data-acdc-journal-form="1" style="display:none;">
				<?php wp_nonce_field( 'acdc_log_prospect_activity' ); ?>
				<input type="hidden" name="action" value="acdc_log_prospect_activity">
				<input type="hidden" name="prospect_id" value="<?php echo (int) $prospect->id; ?>">
				<?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-prospect-followup"><?php else : ?><input type="hidden" name="return_tab" value="prospect_followup"><?php endif; ?>
				<div class="acdc-journal-form-grid">
					<p><label>Type d'interaction</label>
						<select name="activity_type">
							<?php foreach ( $this->get_prospect_activity_manual_keys() as $key ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $meta[ $key ]['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p><label>Sens</label>
						<select name="direction">
							<?php foreach ( $directions as $dk => $dl ) : ?>
								<option value="<?php echo esc_attr( $dk ); ?>"<?php echo ( 'out' === $dk ) ? ' selected' : ''; ?>><?php echo esc_html( $dl ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p><label>Date et heure</label><input type="datetime-local" name="occurred_at" value="<?php echo esc_attr( $now_local ); ?>"></p>
					<p><label>Résultat (facultatif)</label><input type="text" name="outcome" placeholder="Abouti, répondeur, à rappeler…"></p>
				</div>
				<p><label>Objet (facultatif)</label><input type="text" name="subject" placeholder="Ex : présentation du catalogue"></p>
				<p><label>Compte-rendu — ce qui a été dit</label><textarea name="summary" rows="3" placeholder="Notez ici le contenu de l'échange…"></textarea></p>
				<div class="acdc-inline-actions">
					<button type="button" class="acdc-button acdc-button-link" data-acdc-journal-cancel="1">Annuler</button>
					<button type="submit" class="acdc-button acdc-button-primary">Enregistrer l'interaction</button>
				</div>
			</form>

			<?php if ( ! empty( $timeline ) ) :
				$present_types = array();
				foreach ( $timeline as $ti ) { $present_types[ $ti['type'] ] = true; }
				$filter_chips = array(
					'all'     => 'Tout',
					'call'    => 'Appels',
					'email'   => 'E-mails',
					'rdv'     => 'Rendez-vous',
					'meeting' => 'Présentiel',
					'video'   => 'Visio',
					'sms'     => 'SMS',
					'note'    => 'Notes',
				);
				?>
				<div class="acdc-journal-filters" data-acdc-journal-filters="1">
					<?php foreach ( $filter_chips as $fk => $fl ) :
						if ( 'all' !== $fk && empty( $present_types[ $fk ] ) ) { continue; }
						?>
						<button type="button" class="acdc-journal-chip<?php echo ( 'all' === $fk ) ? ' is-active' : ''; ?>" data-acdc-journal-filter="<?php echo esc_attr( $fk ); ?>"><?php echo esc_html( $fl ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="acdc-journal-timeline">
				<?php if ( empty( $timeline ) ) : ?>
					<p class="acdc-field-help">Aucune interaction enregistrée pour le moment.</p>
				<?php else : foreach ( $timeline as $item ) :
					$type_key = isset( $meta[ $item['type'] ] ) ? $item['type'] : 'note';
					$tmeta    = $meta[ $type_key ];
					$dir_lbl  = isset( $directions[ $item['direction'] ] ) ? $directions[ $item['direction'] ] : '';
					$when_lbl = ! empty( $item['when'] ) ? mysql2date( 'd/m/Y H\hi', $item['when'] ) : '—';
					?>
					<div class="acdc-journal-item" data-acdc-journal-type="<?php echo esc_attr( $type_key ); ?>">
						<div class="acdc-journal-item-head">
							<span class="acdc-journal-badge" style="background:<?php echo esc_attr( $tmeta['bg'] ); ?>;color:<?php echo esc_attr( $tmeta['fg'] ); ?>;"><?php echo esc_html( $tmeta['label'] ); ?></span>
							<?php if ( 'manual' === $item['kind'] && '' !== $dir_lbl && '—' !== $dir_lbl ) : ?><span class="acdc-journal-dir"><?php echo esc_html( $dir_lbl ); ?></span><?php endif; ?>
							<?php if ( '' !== (string) $item['outcome'] ) : ?><span class="acdc-journal-outcome"><?php echo esc_html( $item['outcome'] ); ?></span><?php endif; ?>
							<?php if ( 'email' === $item['kind'] ) : ?><span class="acdc-journal-auto">Auto</span><?php endif; ?>
							<span class="acdc-journal-date"><?php echo esc_html( $when_lbl ); ?></span>
						</div>
						<?php if ( '' !== (string) $item['subject'] && 'E-mail' !== $item['subject'] ) : ?><div class="acdc-journal-subject"><?php echo esc_html( $item['subject'] ); ?></div><?php endif; ?>
						<?php if ( '' !== (string) $item['summary'] ) : ?><div class="acdc-journal-summary"><?php echo nl2br( esc_html( $item['summary'] ) ); ?></div><?php endif; ?>
						<div class="acdc-journal-item-foot">
							<?php if ( '' !== (string) $item['view_url'] ) : ?><a class="acdc-journal-link" href="<?php echo esc_url( $item['view_url'] ); ?>">Visualiser l'e-mail</a><?php endif; ?>
							<?php if ( 'manual' === $item['kind'] ) : ?>
								<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="acdc-journal-delete-form" onsubmit="return confirm('Supprimer cette interaction du journal ?');">
									<?php wp_nonce_field( 'acdc_delete_prospect_activity_' . (int) $item['id'] ); ?>
									<input type="hidden" name="action" value="acdc_delete_prospect_activity">
									<input type="hidden" name="prospect_id" value="<?php echo (int) $prospect->id; ?>">
									<input type="hidden" name="activity_id" value="<?php echo (int) $item['id']; ?>">
									<?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-prospect-followup"><?php else : ?><input type="hidden" name="return_tab" value="prospect_followup"><?php endif; ?>
									<button type="submit" class="acdc-journal-delete">Supprimer</button>
								</form>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; endif; ?>
			</div>
		</div>
		<style>
			#acdc-prospect-activity-journal .acdc-journal-head{display:flex;align-items:center;gap:10px;}
			#acdc-prospect-activity-journal .acdc-journal-count{font-size:12px;color:#8b5b23;background:#faf2e2;border:0.5px solid #e9d4a6;padding:3px 9px;border-radius:999px;}
			#acdc-prospect-activity-journal .acdc-journal-quick{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 14px;}
			#acdc-prospect-activity-journal .acdc-journal-form{background:#fbf7ee;border:0.5px solid #e9d4a6;border-radius:10px;padding:14px;margin-bottom:18px;}
			#acdc-prospect-activity-journal .acdc-journal-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;}
			#acdc-prospect-activity-journal .acdc-journal-timeline{display:flex;flex-direction:column;gap:14px;}
			#acdc-prospect-activity-journal .acdc-journal-filters{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px;}
			#acdc-prospect-activity-journal .acdc-journal-chip{font-size:12px;color:#5f5e5a;background:#f1efe8;border:0.5px solid #e2ddd0;padding:4px 11px;border-radius:999px;cursor:pointer;}
			#acdc-prospect-activity-journal .acdc-journal-chip.is-active{background:#8b5b23;color:#fff;border-color:#8b5b23;}
			#acdc-prospect-activity-journal .acdc-journal-item{border-left:3px solid #e9d4a6;padding:2px 0 2px 14px;}
			#acdc-prospect-activity-journal .acdc-journal-item-head{display:flex;align-items:center;flex-wrap:wrap;gap:8px;}
			#acdc-prospect-activity-journal .acdc-journal-badge{font-size:11px;font-weight:600;padding:2px 9px;border-radius:999px;}
			#acdc-prospect-activity-journal .acdc-journal-dir,#acdc-prospect-activity-journal .acdc-journal-outcome{font-size:11px;color:#5f5e5a;background:#f1efe8;padding:2px 8px;border-radius:999px;}
			#acdc-prospect-activity-journal .acdc-journal-auto{font-size:11px;color:#185fa5;background:#e6f1fb;padding:2px 8px;border-radius:999px;}
			#acdc-prospect-activity-journal .acdc-journal-date{margin-left:auto;font-size:12px;color:#8a8a8a;}
			#acdc-prospect-activity-journal .acdc-journal-subject{font-size:14px;font-weight:500;color:#0f2c52;margin-top:5px;}
			#acdc-prospect-activity-journal .acdc-journal-summary{font-size:13px;color:#475569;line-height:1.5;margin-top:4px;}
			#acdc-prospect-activity-journal .acdc-journal-item-foot{display:flex;align-items:center;gap:14px;margin-top:6px;}
			#acdc-prospect-activity-journal .acdc-journal-link{font-size:12px;color:#185fa5;}
			#acdc-prospect-activity-journal .acdc-journal-delete-form{display:inline-flex;}
			#acdc-prospect-activity-journal .acdc-journal-delete{background:none;border:none;color:#b91c1c;font-size:12px;cursor:pointer;padding:0;text-decoration:underline;}
		</style>
		<script>
		(function(){
			var root=document.getElementById('acdc-prospect-activity-journal');
			if(!root){return;}
			var form=root.querySelector('[data-acdc-journal-form]');
			var toggle=root.querySelector('[data-acdc-journal-toggle]');
			var cancel=root.querySelector('[data-acdc-journal-cancel]');
			if(toggle&&form){toggle.addEventListener('click',function(){form.style.display=(form.style.display==='none'||!form.style.display)?'block':'none';});}
			if(cancel&&form){cancel.addEventListener('click',function(){form.style.display='none';});}
			var filters=root.querySelector('[data-acdc-journal-filters]');
			if(filters){
				filters.addEventListener('click',function(e){
					var chip=e.target.closest('[data-acdc-journal-filter]');
					if(!chip){return;}
					var type=chip.getAttribute('data-acdc-journal-filter');
					var chips=filters.querySelectorAll('[data-acdc-journal-filter]');
					for(var i=0;i<chips.length;i++){chips[i].classList.remove('is-active');}
					chip.classList.add('is-active');
					var items=root.querySelectorAll('.acdc-journal-item');
					for(var j=0;j<items.length;j++){
						var it=items[j];
						it.style.display=(type==='all'||it.getAttribute('data-acdc-journal-type')===type)?'':'none';
					}
				});
			}
		})();
		</script>
		<?php
		if ( '' !== $journal_to_email && method_exists( $this, 'render_direct_email_modal' ) ) {
			$this->render_direct_email_modal( $prospect, 'prospect', $journal_to_email, $journal_to_name, 'prospect_followup' );
		}
	}

	/* ====================================================================
	 *  Handlers (admin_post)
	 * ==================================================================== */

	public function handle_log_prospect_activity() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'Accès refusé.' ) );
		}
		check_admin_referer( 'acdc_log_prospect_activity' );

		$prospect_id = isset( $_POST['prospect_id'] ) ? absint( $_POST['prospect_id'] ) : 0;
		$prospect    = $prospect_id ? $this->get_prospect( $prospect_id ) : null;
		$back_args   = array( 'tab' => 'prospect_followup', 'action' => 'view', 'item_id' => $prospect_id );
		if ( ! $prospect ) {
			$this->redirect_to_portal( 'prospect_followup', 'Prospect introuvable.', 'error' );
		}

		$type = isset( $_POST['activity_type'] ) ? sanitize_key( wp_unslash( $_POST['activity_type'] ) ) : 'note';
		if ( ! in_array( $type, $this->get_prospect_activity_manual_keys(), true ) ) {
			$type = 'note';
		}
		$occurred_raw = isset( $_POST['occurred_at'] ) ? (string) wp_unslash( $_POST['occurred_at'] ) : '';
		$occurred_at  = '' !== $occurred_raw ? $this->sanitize_datetime_input( $occurred_raw ) : current_time( 'mysql' );
		if ( empty( $occurred_at ) ) {
			$occurred_at = current_time( 'mysql' );
		}
		$summary = isset( $_POST['summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['summary'] ) ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		if ( 'note' !== $type && '' === trim( $summary ) && '' === trim( $subject ) ) {
			$this->redirect_to_portal( 'prospect_followup', 'Veuillez renseigner au moins un objet ou un compte-rendu.', 'error', $back_args );
		}

		$current_user = wp_get_current_user();
		$created_by   = ( $current_user && ! empty( $current_user->display_name ) ) ? $current_user->display_name : '';

		$this->log_prospect_activity( array(
			'prospect_id'   => $prospect_id,
			'activity_type' => $type,
			'direction'     => isset( $_POST['direction'] ) ? sanitize_key( wp_unslash( $_POST['direction'] ) ) : '',
			'subject'       => $subject,
			'summary'       => $summary,
			'outcome'       => isset( $_POST['outcome'] ) ? sanitize_text_field( wp_unslash( $_POST['outcome'] ) ) : '',
			'occurred_at'   => $occurred_at,
			'created_by'    => $created_by,
		) );

		$this->redirect_to_portal( 'prospect_followup', 'Interaction enregistrée dans le journal.', 'success', $back_args );
	}

	public function handle_delete_prospect_activity() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'Accès refusé.' ) );
		}
		$activity_id = isset( $_POST['activity_id'] ) ? absint( $_POST['activity_id'] ) : 0;
		check_admin_referer( 'acdc_delete_prospect_activity_' . $activity_id );

		$prospect_id = isset( $_POST['prospect_id'] ) ? absint( $_POST['prospect_id'] ) : 0;
		$back_args   = array( 'tab' => 'prospect_followup', 'action' => 'view', 'item_id' => $prospect_id );

		if ( $activity_id && $prospect_id ) {
			global $wpdb;
			$wpdb->delete( $this->prospect_activity_table, array( 'id' => $activity_id, 'prospect_id' => $prospect_id ) );
		}

		$this->redirect_to_portal( 'prospect_followup', 'Interaction supprimée du journal.', 'success', $back_args );
	}
}
