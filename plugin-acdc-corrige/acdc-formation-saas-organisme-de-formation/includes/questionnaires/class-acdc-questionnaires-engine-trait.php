<?php
/**
 * ACDC Questionnaires / Enquêtes — moteur commun des enquêtes.
 *
 * Prépare la base commune des 6 modules enquêtes sur le socle
 * questionnaires déjà présent dans le plugin.
 *
 * @since 3.18.72
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Questionnaires_Engine_Trait {

  private function get_survey_engine_registry() {
    return array(
      'mid' => array(
        'label' => 'Enquêtes intermédiaires',
        'source_type' => 'mid_survey',
        'recipient_type' => 'learner',
        'moment' => 'Pendant la formation',
        'mandatory' => false,
        'default_trigger' => 'À mi-parcours ou après X % des heures prévues',
        'subtypes' => array(),
      ),
      'hot' => array(
        'label' => 'Enquêtes à chaud',
        'source_type' => 'hot_survey',
        'recipient_type' => 'learner',
        'moment' => 'Fin de formation',
        'mandatory' => true,
        'default_trigger' => 'Quand la formation est marquée terminée',
        'subtypes' => array(),
      ),
      'cold' => array(
        'label' => 'Enquêtes à froid',
        'source_type' => 'cold_survey',
        'recipient_type' => 'learner',
        'moment' => '30 jours après la fin réelle',
        'mandatory' => true,
        'default_trigger' => 'J+30 après la date de fin réelle',
        'subtypes' => array(),
      ),
      'trainer' => array(
        'label' => 'Enquêtes formateurs',
        'source_type' => 'trainer_survey',
        'recipient_type' => 'trainer',
        'moment' => 'Fin de formation + campagne annuelle',
        'mandatory' => true,
        'default_trigger' => 'Fin de formation ou campagne annuelle planifiée',
        'subtypes' => array( 'trainer_end', 'trainer_annual' ),
      ),
      'company' => array(
        'label' => 'Enquêtes entreprises',
        'source_type' => 'company_survey',
        'recipient_type' => 'company_signatory',
        'moment' => 'Fin de formation',
        'mandatory' => true,
        'default_trigger' => 'Quand la formation est marquée terminée',
        'subtypes' => array(),
      ),
      'funder' => array(
        'label' => 'Enquêtes financeurs',
        'source_type' => 'funder_survey',
        'recipient_type' => 'funder_contact',
        'moment' => 'Fin de formation + campagne annuelle',
        'mandatory' => true,
        'default_trigger' => 'Fin de prestation si financeur lié ou campagne annuelle',
        'subtypes' => array( 'funder_end', 'funder_annual' ),
      ),
    );
  }

  private function get_survey_engine_type_config( $survey_type ) {
    $registry = $this->get_survey_engine_registry();
    $survey_type = sanitize_key( (string) $survey_type );
    return isset( $registry[ $survey_type ] ) ? $registry[ $survey_type ] : array();
  }

  private function get_survey_engine_tables_map() {
    return array(
      'dispatches'  => $this->questionnaire_session_table,
      'recipients'  => $this->questionnaire_participant_table,
      'answers'     => $this->questionnaire_answer_table,
      'actions'     => $this->questionnaire_action_table,
      'models'      => $this->questionnaire_model_table,
      'logs'        => $this->questionnaire_log_table,
    );
  }

  private function get_survey_engine_model_statuses() {
    return array(
      'brouillon' => 'Brouillon',
      'actif'     => 'Actif',
      'archive'   => 'Archivé',
    );
  }

  private function get_survey_engine_dispatch_statuses() {
    return array(
      'brouillon'             => 'Brouillon',
      'planifiee'             => 'Planifiée',
      'envoyee'               => 'Envoyée',
      'ouverte'               => 'Ouverte',
      'commencee'             => 'Commencée',
      'partiellement_repondue'=> 'Partiellement répondue',
      'repondue'              => 'Répondue',
      'expiree'               => 'Expirée',
      'annulee'               => 'Annulée',
    );
  }

  private function get_survey_engine_participant_statuses() {
    return array(
      'envoye'      => 'Envoyé',
      'ouvert'      => 'Ouvert',
      'commence'    => 'Commencé',
      'partiel'     => 'Partiel',
      'repondu'     => 'Répondu',
      'expire'      => 'Expiré',
      'annule'      => 'Annulé',
    );
  }

  private function get_survey_engine_default_settings() {
    $registry = $this->get_survey_engine_registry();
    $defaults = array(
      'version' => '1.0.0',
      'security' => array(
        'unique_link' => 1,
        'single_final_submission' => 1,
        'log_datetime' => 1,
        'log_ip' => 0,
      ),
      'defaults' => array(
        'deadline_enabled' => 1,
        'deadline_days' => 15,
        'reminder_days' => array( 5, 10, 15 ),
        'auto_stop_reminders_after_response' => 1,
        'allow_admin_reopen' => 1,
        'create_improvement_action' => 1,
      ),
      'types' => array(),
    );

    foreach ( $registry as $key => $config ) {
      $defaults['types'][ $key ] = array(
        'source_type'      => $config['source_type'],
        'recipient_type'   => $config['recipient_type'],
        'mandatory'        => ! empty( $config['mandatory'] ) ? 1 : 0,
        'default_trigger'  => $config['default_trigger'],
        'deadline_days'    => 'cold' === $key ? 15 : 15,
        'reminder_days'    => array( 5, 10, 15 ),
        'subtypes'         => isset( $config['subtypes'] ) ? array_values( (array) $config['subtypes'] ) : array(),
      );
    }

    $defaults['types']['mid']['trigger_rule'] = 'midpoint_or_progress';
    $defaults['types']['hot']['trigger_rule'] = 'formation_completed';
    $defaults['types']['cold']['trigger_rule'] = 'days_after_completion';
    $defaults['types']['cold']['trigger_delay_days'] = 90;
    $defaults['types']['trainer']['trigger_delay_days'] = 1;
    $defaults['types']['company']['trigger_delay_days'] = 30;
    $defaults['types']['trainer']['trigger_rule'] = 'completion_or_annual_campaign';
    $defaults['types']['company']['trigger_rule'] = 'formation_completed';
    $defaults['types']['funder']['trigger_rule'] = 'completion_or_annual_campaign';

    return $defaults;
  }

  public function maybe_seed_survey_engine_settings() {
    $current = get_option( 'acdc_of_survey_engine_settings', array() );
    $defaults = $this->get_survey_engine_default_settings();

    if ( ! is_array( $current ) || empty( $current ) ) {
      add_option( 'acdc_of_survey_engine_settings', $defaults, '', false );
    } else {
      $merged = wp_parse_args( $current, $defaults );
      if ( empty( $merged['types'] ) || ! is_array( $merged['types'] ) ) {
        $merged['types'] = $defaults['types'];
      } else {
        foreach ( $defaults['types'] as $type_key => $type_defaults ) {
          $merged['types'][ $type_key ] = isset( $merged['types'][ $type_key ] ) && is_array( $merged['types'][ $type_key ] )
            ? wp_parse_args( $merged['types'][ $type_key ], $type_defaults )
            : $type_defaults;
        }
      }
      update_option( 'acdc_of_survey_engine_settings', $merged, false );
    }

    add_option( 'acdc_of_survey_engine_registry_version', '1.0.0' );
    update_option( 'acdc_of_survey_engine_registry_version', '1.0.0', false );
  }

  private function get_survey_engine_settings() {
    $this->maybe_seed_survey_engine_settings();
    $settings = get_option( 'acdc_of_survey_engine_settings', array() );
    return is_array( $settings ) ? $settings : $this->get_survey_engine_default_settings();
  }


  private function get_survey_engine_type_key_from_source_type( $source_type ) {
    $source_type = sanitize_key( (string) $source_type );
    foreach ( $this->get_survey_engine_registry() as $type_key => $config ) {
      if ( isset( $config['source_type'] ) && $source_type === (string) $config['source_type'] ) {
        return (string) $type_key;
      }
    }
    return '';
  }

  private function get_survey_engine_type_settings( $survey_type ) {
    $settings = $this->get_survey_engine_settings();
    $defaults = $this->get_survey_engine_default_settings();
    $survey_type = sanitize_key( (string) $survey_type );
    if ( empty( $defaults['types'][ $survey_type ] ) ) {
      return array();
    }
    $current = isset( $settings['types'][ $survey_type ] ) && is_array( $settings['types'][ $survey_type ] )
      ? $settings['types'][ $survey_type ]
      : array();
    return wp_parse_args( $current, $defaults['types'][ $survey_type ] );
  }

  private function update_survey_engine_type_settings( $survey_type, $new_settings ) {
    $survey_type = sanitize_key( (string) $survey_type );
    $settings = $this->get_survey_engine_settings();
    $defaults = $this->get_survey_engine_default_settings();
    if ( empty( $defaults['types'][ $survey_type ] ) ) {
      return false;
    }

    $clean = wp_parse_args( is_array( $new_settings ) ? $new_settings : array(), $defaults['types'][ $survey_type ] );
    $clean['mandatory'] = empty( $clean['mandatory'] ) ? 0 : 1;
    $clean['deadline_days'] = max( 1, absint( $clean['deadline_days'] ) );
    $clean['trigger_rule'] = sanitize_key( (string) $clean['trigger_rule'] );
    $clean['trigger_delay_days'] = max( 0, absint( isset( $clean['trigger_delay_days'] ) ? $clean['trigger_delay_days'] : 0 ) );
    $reminders = isset( $clean['reminder_days'] ) ? $clean['reminder_days'] : array();
    if ( is_string( $reminders ) ) {
      $reminders = preg_split( '/\s*,\s*/', $reminders );
    }
    $reminders = array_values( array_filter( array_map( 'absint', (array) $reminders ) ) );
    $clean['reminder_days'] = ! empty( $reminders ) ? $reminders : array( 5, 10, 15 );
    $clean['default_trigger'] = sanitize_text_field( (string) $clean['default_trigger'] );
    $clean['recipient_type'] = sanitize_key( (string) $clean['recipient_type'] );
    $clean['source_type'] = sanitize_key( (string) $clean['source_type'] );
    $clean['subtypes'] = array_values( array_filter( array_map( 'sanitize_key', (array) ( $clean['subtypes'] ?? array() ) ) ) );

    if ( ! isset( $settings['types'] ) || ! is_array( $settings['types'] ) ) {
      $settings['types'] = array();
    }
    $settings['types'][ $survey_type ] = $clean;
    update_option( 'acdc_of_survey_engine_settings', $settings, false );
    return true;
  }

  private function get_survey_engine_overview_counts() {
    global $wpdb;

    $counts = array();
    foreach ( $this->get_survey_engine_registry() as $type => $config ) {
      $counts[ $type ] = array(
        'sessions'      => 0,
        'participants'  => 0,
        'responses'     => 0,
        'open_actions'  => 0,
      );
    }

    if ( empty( $this->questionnaire_session_table ) || empty( $this->questionnaire_participant_table ) || empty( $this->questionnaire_action_table ) ) {
      return $counts;
    }

    $registry = $this->get_survey_engine_registry();
    $map = array();
    foreach ( $registry as $type => $config ) {
      $map[ $config['source_type'] ] = $type;
    }

    $session_rows = $wpdb->get_results( "SELECT source_type, COUNT(*) AS total FROM {$this->questionnaire_session_table} WHERE is_survey_session = 1 GROUP BY source_type" );
    foreach ( (array) $session_rows as $row ) {
      $source_type = isset( $row->source_type ) ? (string) $row->source_type : '';
      if ( isset( $map[ $source_type ] ) ) {
        $counts[ $map[ $source_type ] ]['sessions'] = (int) $row->total;
      }
    }

    $participant_rows = $wpdb->get_results( "SELECT s.source_type, COUNT(*) AS total, SUM(CASE WHEN p.responded_at IS NOT NULL OR p.participant_status IN ('repondu','termine') THEN 1 ELSE 0 END) AS responded_total FROM {$this->questionnaire_participant_table} p INNER JOIN {$this->questionnaire_session_table} s ON s.id = p.session_id WHERE s.is_survey_session = 1 GROUP BY s.source_type" );
    foreach ( (array) $participant_rows as $row ) {
      $source_type = isset( $row->source_type ) ? (string) $row->source_type : '';
      if ( isset( $map[ $source_type ] ) ) {
        $counts[ $map[ $source_type ] ]['participants'] = (int) $row->total;
        $counts[ $map[ $source_type ] ]['responses'] = (int) $row->responded_total;
      }
    }

    $action_rows = $wpdb->get_results( "SELECT s.source_type, COUNT(*) AS total FROM {$this->questionnaire_action_table} a INNER JOIN {$this->questionnaire_session_table} s ON s.id = a.questionnaire_session_id WHERE s.is_survey_session = 1 AND a.action_status NOT IN ('traitee','annulee') GROUP BY s.source_type" );
    foreach ( (array) $action_rows as $row ) {
      $source_type = isset( $row->source_type ) ? (string) $row->source_type : '';
      if ( isset( $map[ $source_type ] ) ) {
        $counts[ $map[ $source_type ] ]['open_actions'] = (int) $row->total;
      }
    }

    return $counts;
  }

  public function render_questionnaire_engine_foundation_panel() {
    $registry = $this->get_survey_engine_registry();
    $settings = $this->get_survey_engine_settings();
    $counts   = $this->get_survey_engine_overview_counts();
    $tables   = $this->get_survey_engine_tables_map();
    ?>
    <div class="acdc-panel acdc-mb-18">
      <div class="acdc-panel-body">
        <h3 style="margin-top:0;">Moteur commun des enquêtes</h3>
        <p>Socle mutualisé préparé pour les 6 modules enquêtes : types, statuts, déclencheurs par défaut, tables communes, destinataires, réponses et actions d'amélioration.</p>
        <div class="acdc-grid-2cols" style="margin-bottom:16px;">
          <div>
            <h4 style="margin:0 0 8px;">Tables mutualisées</h4>
            <ul style="margin:0;padding-left:18px;">
              <?php foreach ( $tables as $table_key => $table_name ) : ?>
                <li><strong><?php echo esc_html( ucfirst( $table_key ) ); ?> :</strong> <code><?php echo esc_html( $table_name ); ?></code></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div>
            <h4 style="margin:0 0 8px;">Statuts communs</h4>
            <p style="margin:0 0 6px;"><strong>Envois :</strong> <?php echo esc_html( implode( ' • ', array_values( $this->get_survey_engine_dispatch_statuses() ) ) ); ?></p>
            <p style="margin:0;"><strong>Réponses :</strong> <?php echo esc_html( implode( ' • ', array_values( $this->get_survey_engine_participant_statuses() ) ) ); ?></p>
          </div>
        </div>
        <div class="acdc-table-wrap">
          <table class="acdc-table">
            <thead>
              <tr>
                <th>Module</th>
                <th>Source type</th>
                <th>Destinataire</th>
                <th>Déclencheur par défaut</th>
                <th>Obligatoire</th>
                <th>Envois</th>
                <th>Réponses</th>
                <th>Actions ouvertes</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $registry as $type => $config ) : $row = isset( $counts[ $type ] ) ? $counts[ $type ] : array(); ?>
                <tr>
                  <td><strong><?php echo esc_html( $config['label'] ); ?></strong><?php if ( ! empty( $config['subtypes'] ) ) : ?><br><small><?php echo esc_html( implode( ' / ', $config['subtypes'] ) ); ?></small><?php endif; ?></td>
                  <td><code><?php echo esc_html( $config['source_type'] ); ?></code></td>
                  <td><?php echo esc_html( $config['recipient_type'] ); ?></td>
                  <td><?php echo esc_html( $config['default_trigger'] ); ?></td>
                  <td><?php echo ! empty( $config['mandatory'] ) ? 'Oui' : 'Non'; ?></td>
                  <td><?php echo esc_html( (string) ( $row['sessions'] ?? 0 ) ); ?></td>
                  <td><?php echo esc_html( (string) ( $row['responses'] ?? 0 ) ); ?> / <?php echo esc_html( (string) ( $row['participants'] ?? 0 ) ); ?></td>
                  <td><?php echo esc_html( (string) ( $row['open_actions'] ?? 0 ) ); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="acdc-help" style="margin-top:12px;">Réglages communs actifs : lien unique <?php echo ! empty( $settings['security']['unique_link'] ) ? 'oui' : 'non'; ?>, soumission finale unique <?php echo ! empty( $settings['security']['single_final_submission'] ) ? 'oui' : 'non'; ?>, relances par défaut <?php echo esc_html( implode( ', ', (array) $settings['defaults']['reminder_days'] ) ); ?> jours.</p>
      </div>
    </div>
    <?php
  }
}
