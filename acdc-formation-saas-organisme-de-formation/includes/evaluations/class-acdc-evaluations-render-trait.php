<?php
/**
 * ACDC Évaluations des acquis — ACDC_Evaluations_Render_Trait.
 *
 * Extraction micro-incrémentale du sous-bloc métier :
 * évaluations des acquis.
 *
 * @since 3.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Evaluations_Render_Trait {

  private function render_front_evaluations_tab( $action, $item_id ) {
    $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
    $mode = isset( $_GET['mode'] ) ? sanitize_text_field( wp_unslash( $_GET['mode'] ) ) : '';
    $valid_modes = array_keys( $this->get_quiz_correction_options() );
    $has_valid_mode = in_array( $mode, $valid_modes, true );
    $evaluations = $this->get_evaluations( $search );
    $evaluation = $item_id ? $this->get_evaluation( $item_id ) : null;
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-evaluations' ) : $this->portal_page_url( array( 'tab' => 'evaluations' ) );
    $page_title = $this->acdc_get_action_page_title( $action, 'Évaluations des acquis', 'Créer une évaluation des acquis', 'Modifier une évaluation des acquis', 'Voir une évaluation des acquis' );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2><?php echo esc_html( $page_title ); ?></h2>
        <p>Créez, modifiez et consultez les évaluations des acquis rattachées à vos formations.</p>
      </div>
      <div class="acdc-inline-wrap">
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-questionnaire-sessions&source_type=evaluation' ) : $this->portal_page_url( array( 'tab' => 'questionnaire_sessions', 'source_type' => 'evaluation' ) ) ); ?>">Sessions de questionnaires</a>
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-questionnaire-results&source_type=evaluation' ) : $this->portal_page_url( array( 'tab' => 'questionnaire_results', 'source_type' => 'evaluation' ) ) ); ?>">Résultats des sessions</a>
        <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( add_query_arg( array( 'action' => 'new' ), $base_url ) ); ?>">Créer une évaluation des acquis</a>
      </div>
    </section>
    <?php if ( in_array( $action, array( 'new', 'edit', 'view' ), true ) ) : ?>
      <?php if ( 'new' === $action && ! $has_valid_mode ) : ?>
        <div class="acdc-panel" style="margin-bottom:18px;max-width:760px;">
          <div class="acdc-needs-section-title">Choisir le type d’évaluation</div>
          <form method="get" action="<?php echo esc_url( is_admin() ? admin_url( 'admin.php' ) : $this->portal_page_url() ); ?>">
            <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-evaluations"><?php endif; ?>
            <input type="hidden" name="tab" value="evaluations">
            <input type="hidden" name="action" value="new">
            <p><label><strong>Type d’évaluation des acquis *</strong></label><br>
              <select name="mode" required>
                <option value="">Choisir une option</option>
                <?php foreach ( $this->get_quiz_correction_options() as $option_value => $option_label ) : ?>
                  <option value="<?php echo esc_attr( $option_value ); ?>"><?php echo esc_html( $option_label ); ?></option>
                <?php endforeach; ?>
              </select>
            </p>
            <p class="acdc-actions-end-wrap">
              <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $base_url ); ?>">Annuler</a>
              <button type="submit" class="acdc-button acdc-button-primary">Créer une évaluation des acquis</button>
            </p>
          </form>
        </div>
      <?php else : ?>
        <?php $this->render_front_evaluation_form( $evaluation, 'view' === $action ); ?>
      <?php endif; ?>
    <?php endif; ?>
    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-evaluations"><?php endif; ?>
        <input type="hidden" name="tab" value="evaluations">
        <div class="acdc-inline-wrap acdc-inline-wrap-center">
          <input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="Rechercher" style="max-width:420px;">
          <button type="submit" class="acdc-button acdc-button-soft">Rechercher</button>
        </div>
      </form>
    </div>
    <div class="acdc-panel">
      <div class="acdc-table-wrap">
        <table class="acdc-table">
          <thead>
            <tr>
              <th>Intitulé</th>
              <th>Formation(s)</th>
              <th>Durée du questionnaire</th>
              <th>Modifié le</th>
              <th>Type de correction</th>
              <th>Sessions</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if ( ! empty( $evaluations ) ) : foreach ( $evaluations as $entry ) : $titles = $this->get_evaluation_formation_titles( $entry ); $session_count = $this->get_questionnaire_session_count_for_source( 'evaluation', (int) $entry->id ); ?>
            <tr>
              <td><?php echo esc_html( $entry->title ); ?></td>
              <td><?php echo esc_html( count( $titles ) . ' Formation(s)' ); ?></td>
              <td><?php echo esc_html( absint( $entry->duration_minutes ) ); ?> minutes</td>
              <td><?php echo esc_html( mysql2date( 'j F Y à H\hi', $entry->updated_at ) ); ?></td>
              <td><?php echo esc_html( $entry->correction_type ); ?></td>
              <td><strong><?php echo esc_html( $session_count ); ?></strong><br><small><a href="<?php echo esc_url( $this->get_questionnaire_sessions_url( 'evaluation', (int) $entry->id ) ); ?>">Voir les sessions</a></small></td>
              <td>
                <?php /* ACDC 3.20.104 — Conversion liens texte → icônes inline 25px. */ ?>
                <div class="acdc-groups-actions-inline">
                  <a class="acdc-row-action-icon acdc-row-view-link" href="<?php echo esc_url( add_query_arg( array( 'action' => 'view', 'item_id' => (int) $entry->id ), $base_url ) ); ?>" title="Voir" aria-label="Voir l'évaluation des acquis">
                    <?php echo $this->render_inline_icon( 'eye', 25 ); ?>
                  </a>
                  <a class="acdc-row-action-icon acdc-row-edit-link" href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'item_id' => (int) $entry->id ), $base_url ) ); ?>" title="Modifier" aria-label="Modifier l'évaluation des acquis">
                    <?php echo $this->render_inline_icon( 'edit', 25 ); ?>
                  </a>
                  <a class="acdc-row-action-icon" href="<?php echo esc_url( $this->get_questionnaire_new_session_url( 'evaluation', (int) $entry->id ) ); ?>" title="Créer une session" aria-label="Créer une session pour cette évaluation">
                    <?php echo $this->render_inline_icon( 'create-session', 25 ); ?>
                  </a>
                  <a class="acdc-row-action-icon acdc-row-delete-link" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_delete_evaluation&evaluation_id=' . (int) $entry->id . ( is_admin() ? '&page=acdc-of-evaluations' : '' ) ), 'acdc_delete_evaluation_' . (int) $entry->id ) ); ?>" title="Supprimer" aria-label="Supprimer l'évaluation des acquis" onclick="return confirm('Supprimer cette évaluation des acquis ?');">
                    <?php echo $this->render_inline_icon( 'trash', 25 ); ?>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; else : ?>
            <tr><td colspan="7">Aucune évaluation des acquis enregistrée.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if ( 'list' === $action ) : ?>
      <div class="acdc-modal-shell" id="acdc-create-evaluation-modal" hidden>
        <div class="acdc-modal-backdrop" data-acdc-close-modal></div>
        <div class="acdc-modal-dialog acdc-modal-dialog-medium">
          <div class="acdc-modal-header"><h4>Créer une évaluation des acquis</h4><button type="button" class="acdc-modal-close" data-acdc-close-modal>&times;</button></div>
          <div class="acdc-modal-body acdc-pad-24">
            <form method="get" action="<?php echo esc_url( is_admin() ? admin_url( 'admin.php' ) : $this->portal_page_url() ); ?>">
              <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-evaluations"><?php endif; ?>
              <input type="hidden" name="tab" value="evaluations">
              <input type="hidden" name="action" value="new">
              <p><label><strong>Type d’évaluation des acquis *</strong></label></p>
              <p>
                <select name="mode" required>
                  <option value="">Choisir une option</option>
                  <?php foreach ( $this->get_quiz_correction_options() as $option_value => $option_label ) : ?>
                    <option value="<?php echo esc_attr( $option_value ); ?>"><?php echo esc_html( $option_label ); ?></option>
                  <?php endforeach; ?>
                </select>
              </p>
              <p class="acdc-actions-end-wrap">
                <button type="button" class="acdc-button acdc-button-soft" data-acdc-close-modal>Annuler</button>
                <button type="submit" class="acdc-button acdc-button-primary">Créer une évaluation des acquis</button>
              </p>
            </form>
          </div>
        </div>
      </div>
      <script>
      document.addEventListener('DOMContentLoaded',function(){
      });
      </script>
    <?php endif; ?>
    <?php
  }

  private function render_front_evaluation_form( $evaluation = null, $read_only = false ) {
    $mode = 'Correction automatique';
    if ( $evaluation && ! empty( $evaluation->correction_type ) ) {
      $mode = $evaluation->correction_type;
    } elseif ( isset( $_GET['mode'] ) ) {
      $candidate = sanitize_text_field( wp_unslash( $_GET['mode'] ) );
      if ( array_key_exists( $candidate, $this->get_quiz_correction_options() ) ) {
        $mode = $candidate;
      }
    }
    $is_auto = 'Sans correction automatique' !== $mode;
    $formations = $this->get_formations( array( 'archived' => false ) );
    if ( empty( $formations ) ) {
      $formations = $this->get_formations();
    }
    $selected_formations = $evaluation ? $this->get_evaluation_formation_ids( $evaluation ) : array();
    $questions = array();
    if ( $evaluation && ! empty( $evaluation->question_blocks ) ) {
      $decoded = json_decode( $evaluation->question_blocks, true );
      if ( is_array( $decoded ) ) {
        $questions = $decoded;
      }
    }
    $scores = array();
    if ( $evaluation && ! empty( $evaluation->scoring_blocks ) ) {
      $decoded = json_decode( $evaluation->scoring_blocks, true );
      if ( is_array( $decoded ) ) {
        $scores = $decoded;
      }
    }
    $value = function( $key, $default = '' ) use ( $evaluation, $mode ) {
      if ( 'correction_type' === $key ) {
        return $evaluation && isset( $evaluation->$key ) && '' !== $evaluation->$key ? $evaluation->$key : $mode;
      }
      return $evaluation && isset( $evaluation->$key ) ? $evaluation->$key : $default;
    };
    ?>
    <form class="acdc-form acdc-evaluation-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'acdc_save_evaluation' ); ?>
      <input type="hidden" name="action" value="acdc_save_evaluation">
      <input type="hidden" name="evaluation_id" value="<?php echo $evaluation ? esc_attr( $evaluation->id ) : 0; ?>">
      <input type="hidden" name="evaluation[correction_type]" value="<?php echo esc_attr( $mode ); ?>">
      <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-evaluations"><?php endif; ?>
      <div class="acdc-panel acdc-mb-18">
        <div class="acdc-needs-section-title">Informations</div>
        <div class="acdc-grid-2cols">
          <p><label>Formation(s) *</label>
            <select name="evaluation[formation_ids][]" <?php echo $read_only ? 'disabled' : ''; ?> multiple size="6" required>
              <?php foreach ( $formations as $formation ) : ?>
                <option value="<?php echo esc_attr( $formation->id ); ?>" <?php selected( in_array( (int) $formation->id, $selected_formations, true ) ); ?>><?php echo esc_html( $this->format_formation_option_label( $formation ) ); ?></option>
              <?php endforeach; ?>
            </select>
          </p>
          <p><label>Durée du questionnaire *</label>
            <input type="number" min="1" name="evaluation[duration_minutes]" required value="<?php echo esc_attr( $value( 'duration_minutes', 5 ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Durée du questionnaire">
            <small class="acdc-help-inline">Le temps doit être déclaré en minutes</small>
          </p>
        </div>
      </div>
      <div class="acdc-panel acdc-mb-18">
        <div class="acdc-needs-section-title">Évaluation des acquis</div>
        <p><label>Intitulé *</label><input type="text" name="evaluation[title]" required value="<?php echo esc_attr( $value( 'title' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Intitulé"></p>
        <p><label>Description</label><textarea name="evaluation[description_text]" rows="5" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Description"><?php echo esc_textarea( $value( 'description_text' ) ); ?></textarea></p>
        <div class="acdc-needs-section-title" style="margin-top:24px;">Type de question</div>
        <?php if ( ! $read_only ) : ?><p><button type="button" class="acdc-button acdc-button-primary" id="acdc-add-evaluation-question">Ajouter une nouvelle question</button></p><?php endif; ?>
        <div id="acdc-evaluation-questions-wrap">
          <?php foreach ( $questions as $index => $q ) : ?>
            <div class="acdc-panel acdc-panel-block">
              <div class="acdc-grid-question-row">
                <input type="text" name="questions[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $q['label'] ?? '' ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Intitulé de la question">
                <select name="questions[<?php echo esc_attr( $index ); ?>][type]" <?php echo $read_only ? 'disabled' : ''; ?>>
                  <?php foreach ( $this->get_quiz_question_type_options() as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $q['type'] ?? '', $key ); ?>><?php echo esc_html( $label ); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <textarea name="questions[<?php echo esc_attr( $index ); ?>][options]" rows="3" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Options ou consignes"><?php echo esc_textarea( $q['options'] ?? '' ); ?></textarea>
              <?php if ( ! $read_only ) : ?><p><button type="button" class="acdc-button acdc-remove-evaluation-question acdc-button-soft">Supprimer la question</button></p><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if ( $is_auto ) : ?>
      <div class="acdc-panel acdc-mb-18">
        <div class="acdc-needs-section-title">Résultat évaluation des acquis</div>
        <?php if ( ! $read_only ) : ?><p><button type="button" class="acdc-button acdc-button-primary" id="acdc-add-evaluation-score">Ajouter un type de notation</button></p><?php endif; ?>
        <div id="acdc-evaluation-scoring-wrap">
          <?php foreach ( $scores as $index => $row ) : ?>
            <div class="acdc-panel acdc-panel-block">
              <div class="acdc-grid-score-row">
                <select name="scoring[<?php echo esc_attr( $index ); ?>][type]" <?php echo $read_only ? 'disabled' : ''; ?>>
                  <?php foreach ( $this->get_quiz_notation_type_options() as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $row['type'] ?? '', $key ); ?>><?php echo esc_html( $label ); ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="text" name="scoring[<?php echo esc_attr( $index ); ?>][threshold]" value="<?php echo esc_attr( $row['threshold'] ?? '' ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Seuil">
                <input type="text" name="scoring[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $row['label'] ?? '' ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Libellé affiché">
              </div>
              <?php if ( ! $read_only ) : ?><p><button type="button" class="acdc-button acdc-remove-evaluation-score acdc-button-soft">Supprimer ce type</button></p><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="acdc-muted-top-16">“Acquis/Non acquis” : vous devrez indiquer le nombre de bonnes réponses nécessaires pour réussir l’évaluation.</p>
        <p class="acdc-muted-top-6">“Niveaux” : vous pourrez créer plusieurs niveaux et définir le seuil à atteindre pour chaque niveau.</p>
      </div>
      <?php endif; ?>
      <p class="acdc-actions-end-wrap">
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-evaluations' ) : $this->portal_page_url( array( 'tab' => 'evaluations' ) ) ); ?>">Annuler</a>
        <?php if ( ! $read_only ) : ?>
          <button type="submit" name="save_and_add" value="1" class="acdc-button acdc-button-primary">Créer & ajouter un autre</button>
          <button type="submit" name="save_and_prepare_session" value="1" class="acdc-button acdc-button-soft"><?php echo $evaluation ? 'Modifier et préparer une session' : 'Créer et préparer une session'; ?></button>
          <button type="submit" class="acdc-button acdc-button-primary"><?php echo $evaluation ? 'Modifier l’évaluation' : 'Créer une évaluation des acquis'; ?></button>
        <?php else : ?>
          <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-evaluations&action=edit&item_id=' . (int) $evaluation->id ) : $this->portal_page_url( array( 'tab' => 'evaluations', 'action' => 'edit', 'item_id' => (int) $evaluation->id ) ) ); ?>">Modifier cette évaluation</a>
        <?php endif; ?>
      </p>
      <?php if ( $evaluation ) { $this->render_questionnaire_source_visibility_panel( 'evaluation', (int) $evaluation->id, 'Cette évaluation des acquis' ); } else { $this->render_questionnaire_source_pending_panel( 'cette évaluation des acquis' ); } ?>
      <?php if ( ! $read_only ) : ?>
      <script>
      document.addEventListener('DOMContentLoaded',function(){
        var qWrap=document.getElementById('acdc-evaluation-questions-wrap');
        var addQ=document.getElementById('acdc-add-evaluation-question');
        if(addQ&&qWrap){addQ.addEventListener('click',function(){var i=qWrap.querySelectorAll('.acdc-panel').length;var d=document.createElement('div');d.className='acdc-panel acdc-panel-block';d.innerHTML='<div class="acdc-grid-question-row"><input type="text" name="questions['+i+'][label]" placeholder="Intitulé de la question"><select name="questions['+i+'][type]"><?php foreach ( $this->get_quiz_question_type_options() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_js( $label ); ?></option><?php endforeach; ?></select></div><textarea name="questions['+i+'][options]" rows="3" placeholder="Options ou consignes"></textarea><p><button type="button" class="acdc-button acdc-remove-evaluation-question acdc-button-soft">Supprimer la question</button></p>';qWrap.appendChild(d);});qWrap.addEventListener('click',function(e){if(e.target.classList.contains('acdc-remove-evaluation-question')){e.preventDefault();e.target.closest('.acdc-panel').remove();}});}
        var sWrap=document.getElementById('acdc-evaluation-scoring-wrap');
        var addS=document.getElementById('acdc-add-evaluation-score');
        if(addS&&sWrap){addS.addEventListener('click',function(){var i=sWrap.querySelectorAll('.acdc-panel').length;var d=document.createElement('div');d.className='acdc-panel acdc-panel-block';d.innerHTML='<div class="acdc-grid-score-row"><select name="scoring['+i+'][type]"><?php foreach ( $this->get_quiz_notation_type_options() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_js( $label ); ?></option><?php endforeach; ?></select><input type="text" name="scoring['+i+'][threshold]" placeholder="Seuil"><input type="text" name="scoring['+i+'][label]" placeholder="Libellé affiché"></div><p><button type="button" class="acdc-button acdc-remove-evaluation-score acdc-button-soft">Supprimer ce type</button></p>';sWrap.appendChild(d);});sWrap.addEventListener('click',function(e){if(e.target.classList.contains('acdc-remove-evaluation-score')){e.preventDefault();e.target.closest('.acdc-panel').remove();}});}
      });
      </script>
      <?php endif; ?>
    </form>
    <?php
  }


  private function render_front_evaluation_results_tab() {
    $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
    $per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 25;
    if ( ! in_array( $per_page, array( 25, 50, 100 ), true ) ) {
      $per_page = 25;
    }
    $paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
    $base_tab = 'evaluation_results';
    $base_url = is_admin() ? $this->admin_tab_url( $base_tab ) : $this->portal_page_url( array( 'tab' => $base_tab ) );
    $rows = $this->get_evaluation_result_rows( $search );
    $total = count( $rows );
    $total_pages = max( 1, (int) ceil( max( 1, $total ) / $per_page ) );
    if ( $paged > $total_pages ) { $paged = $total_pages; }
    $offset = ( $paged - 1 ) * $per_page;
    $page_rows = array_slice( $rows, $offset, $per_page );
    ?>
    <section class="acdc-section-head"><div><h2>Résultat évaluations des acquis</h2></div></section>
    <div class="acdc-panel acdc-mb-18">
      <form class="acdc-search-bar" method="get" action="<?php echo esc_url( $base_url ); ?>">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
        <input type="hidden" name="tab" value="evaluation_results">
        <div class="acdc-search-row">
          <input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="Rechercher">
          <button type="button" class="acdc-filter-toggle acdc-filter-toggle-icons-only" data-acdc-filter-toggle aria-expanded="false" title="Filtres"><?php echo $this->render_inline_icon( 'filter', 18 ); ?> <?php echo $this->render_inline_icon( 'chevron-down', 16 ); ?></button>
        </div>
        <div class="acdc-sessions-filters-panel acdc-documents-filters-panel" data-acdc-filters-panel hidden>
          <div class="acdc-documents-filter-card">
            <label><span>Par page</span>
              <select name="per_page">
                <?php foreach ( array( 25, 50, 100 ) as $size ) : ?>
                  <option value="<?php echo esc_attr( $size ); ?>" <?php selected( $per_page, $size ); ?>><?php echo esc_html( $size ); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="acdc-inline-wrap" style="justify-content:flex-end;margin-top:14px;">
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $base_url ); ?>">Réinitialiser</a>
            <button type="submit" class="acdc-button acdc-button-primary">Appliquer</button>
          </div>
        </div>
      </form>
    </div>
    <div class="acdc-panel">
      <?php if ( empty( $page_rows ) ) : ?>
        <div class="acdc-empty-state" style="padding:72px 24px;text-align:center;"><div class="acdc-empty-state-icon" aria-hidden="true"><?php echo $this->render_inline_icon( 'evaluation_result', 54 ); ?></div><p style="margin:0;color:#1E4777;">Aucune donnée ne correspond aux critères demandés.</p></div>
      <?php else : ?>
        <div class="acdc-table-wrap">
          <table class="acdc-table acdc-table-evaluation-results-documents">
            <thead>
              <tr>
                <th><input type="checkbox" aria-label="Sélectionner"></th>
                <th>ID</th>
                <th>Apprenant</th>
                <th>Évaluation</th>
                <th>Type de correction</th>
                <th>Date ajout/passage</th>
                <th>Résultats</th>
                <th>Formation</th>
                <th>Dates de formation</th>
                <th>Durée (H)</th>
                <th>Format</th>
                <th>État dossier</th>
                <th><span class="screen-reader-text">Menu</span></th>
                <th><span class="screen-reader-text">Voir</span></th>
                <th><span class="screen-reader-text">Modifier</span></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ( $page_rows as $row ) : ?>
              <?php
              $entry = $row['registration'];
              $context = $row['context'];
              $document = $context['document'];
              $download_url = $this->get_evaluation_result_download_url( $entry, 'attachment' );
              $view_pdf_url = $this->get_evaluation_result_download_url( $entry, 'inline' );
              $view_modal_id = 'acdc-evaluation-result-view-' . (int) $entry->id;
              $edit_modal_id = 'acdc-evaluation-result-edit-' . (int) $entry->id;
              $export_modal_id = 'acdc-evaluation-result-export-' . (int) $entry->id;
              $default_filename = sanitize_file_name( 'details-qcm-evaluation-' . ( ! empty( $context['learner_name'] ) ? $context['learner_name'] : 'apprenant' ) . '-' . date_i18n( 'Ymd' ) );
              ?>
              <tr>
                <td><input type="checkbox" aria-label="Sélectionner ce résultat"></td>
                <td><?php echo esc_html( (int) $entry->id ); ?></td>
                <td><?php echo esc_html( $context['learner_name'] ); ?></td>
                <td><?php echo esc_html( $context['evaluation_source_label'] ); ?><small><?php echo esc_html( $context['evaluation_title'] ); ?></small></td>
                <td><?php echo esc_html( $context['correction_type'] ); ?></td>
                <td><?php echo esc_html( $this->format_pdf_date( $context['start_date'] ) ); ?></td>
                <td><?php echo esc_html( $context['result_label'] ); ?><?php if ( null !== $context['correct_answers'] && ! empty( $context['total_questions'] ) ) : ?><small>Nb de questions réussies / Nb de questions : <?php echo esc_html( (int) $context['correct_answers'] . ' / ' . (int) $context['total_questions'] ); ?></small><?php endif; ?></td>
                <td><a href="<?php echo esc_url( $view_pdf_url ); ?>" target="_blank" rel="noopener" class="acdc-program-link"><?php echo esc_html( $context['formation_title'] ); ?></a></td>
                <td><?php echo esc_html( 'Début : ' . $this->format_pdf_date( $context['start_date'] ) . "
Fin : " . $this->format_pdf_date( $context['end_date'] ) ); ?></td>
                <td><?php echo esc_html( $context['duration'] ); ?></td>
                <td><?php echo esc_html( $context['format'] ); ?></td>
                <td><?php echo esc_html( $row['state'] ); ?></td>
                <td>
                  <div class="acdc-row-menu" data-acdc-row-menu>
                    <button type="button" class="acdc-row-menu-toggle" data-acdc-row-menu-toggle aria-expanded="false" title="Actions"><?php echo $this->render_inline_icon( 'more-horizontal', 25 ); ?></button>
                    <div class="acdc-row-menu-dropdown" data-acdc-row-menu-dropdown hidden>
                      <a href="<?php echo esc_url( $download_url ); ?>">Résultat des évaluations des acquis</a>
                      <a href="#" data-acdc-modal-open="<?php echo esc_attr( $export_modal_id ); ?>">Exporter les détails des QCM</a>
                    </div>
                  </div>
                </td>
                <td><button type="button" class="acdc-row-view-link" data-acdc-modal-open="<?php echo esc_attr( $view_modal_id ); ?>" title="Voir" aria-label="Voir"><?php echo $this->render_inline_icon( 'view', 25 ); ?></button></td>
                <td><button type="button" class="acdc-row-edit-link" data-acdc-modal-open="<?php echo esc_attr( $edit_modal_id ); ?>" title="Modifier" aria-label="Modifier"><?php echo $this->render_inline_icon( 'edit-pencil', 25 ); ?></button></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ( $total_pages > 1 ) : ?>
          <div class="acdc-pagination-wrap"><div class="acdc-pagination"><?php for ( $page = 1; $page <= $total_pages; $page++ ) : ?><a class="<?php echo $page === $paged ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'q' => $search, 'per_page' => $per_page, 'paged' => $page ), $base_url ) ); ?>"><?php echo esc_html( $page ); ?></a><?php endfor; ?></div><div class="acdc-pagination-summary"><?php echo esc_html( sprintf( '%d-%d de %d', $total ? $offset + 1 : 0, min( $offset + $per_page, $total ), $total ) ); ?></div></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php foreach ( $page_rows as $row ) : ?>
      <?php
      $entry = $row['registration'];
      $context = $row['context'];
      $document = $context['document'];
      $file_name = $this->get_evaluation_result_display_file_name( $entry, $context, $document );
      $view_modal_id = 'acdc-evaluation-result-view-' . (int) $entry->id;
      $edit_modal_id = 'acdc-evaluation-result-edit-' . (int) $entry->id;
      $export_modal_id = 'acdc-evaluation-result-export-' . (int) $entry->id;
      $view_pdf_url = $this->get_evaluation_result_download_url( $entry, 'inline' );
      $default_filename = sanitize_file_name( 'details-qcm-evaluation-' . ( ! empty( $context['learner_name'] ) ? $context['learner_name'] : 'apprenant' ) . '-' . date_i18n( 'Ymd' ) );
      ?>
      <div class="acdc-modal-shell" id="<?php echo esc_attr( $view_modal_id ); ?>" hidden>
        <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
        <div class="acdc-modal-dialog acdc-modal-dialog-contract">
          <div class="acdc-modal-header"><h4>Voir le résultat des évaluations des acquis :</h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
          <div class="acdc-modal-body">
            <div class="acdc-contract-doc-topbar"><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $view_pdf_url ); ?>" target="_blank" rel="noopener">Voir le document</a></div>
            <div class="acdc-contract-details-grid">
              <div class="acdc-contract-detail-label">ID</div><div><?php echo esc_html( (int) $entry->id ); ?></div>
              <div class="acdc-contract-detail-label">Apprenant</div><div><?php echo esc_html( $context['learner_name'] ); ?></div>
              <div class="acdc-contract-detail-label">Évaluation</div><div><?php echo esc_html( $context['evaluation_source_label'] ); ?>
<?php echo esc_html( $context['evaluation_title'] ); ?></div>
              <div class="acdc-contract-detail-label">Type de correction</div><div><?php echo esc_html( $context['correction_type'] ); ?></div>
              <div class="acdc-contract-detail-label">Date Ajout/Passage</div><div><?php echo esc_html( $this->format_pdf_date( $context['start_date'] ) ); ?></div>
              <div class="acdc-contract-detail-label">Résultats</div><div><?php echo esc_html( $context['result_label'] ); ?><?php if ( null !== $context['correct_answers'] && ! empty( $context['total_questions'] ) ) : ?>
Nb de questions réussies / Nb de questions : <?php echo esc_html( (int) $context['correct_answers'] . ' / ' . (int) $context['total_questions'] ); ?><?php endif; ?></div>
              <div class="acdc-contract-detail-label">Adresse e-mail</div><div><?php echo esc_html( $context['email'] ); ?></div>
              <div class="acdc-contract-detail-label">Téléphone</div><div><?php echo esc_html( $context['phone'] ); ?></div>
              <div class="acdc-contract-detail-label">Formation</div><div><?php echo esc_html( $context['formation_title'] ); ?></div>
              <div class="acdc-contract-detail-label">Dates de formation</div><div><?php echo esc_html( 'Début : ' . $this->format_pdf_date( $context['start_date'] ) . "
Fin : " . $this->format_pdf_date( $context['end_date'] ) ); ?></div>
              <div class="acdc-contract-detail-label">Durée (h)</div><div><?php echo esc_html( $context['duration'] ); ?></div>
              <div class="acdc-contract-detail-label">Format</div><div><?php echo esc_html( $context['format'] ); ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="acdc-modal-shell" id="<?php echo esc_attr( $export_modal_id ); ?>" hidden>
        <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
        <div class="acdc-modal-dialog" style="max-width:1090px;">
          <div class="acdc-modal-header"><h4>Exporter les détails des QCM</h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
          <div class="acdc-modal-body">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
              <?php wp_nonce_field( 'acdc_export_evaluation_qcm_details_' . (int) $entry->id ); ?>
              <input type="hidden" name="action" value="acdc_export_evaluation_qcm_details">
              <input type="hidden" name="registration_id" value="<?php echo esc_attr( (int) $entry->id ); ?>">
              <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
              <input type="hidden" name="tab" value="evaluation_results">
              <div class="acdc-contract-grid">
                <div class="acdc-contract-label">Nom du fichier <span class="acdc-required">*</span></div>
                <div><input type="text" name="export_filename" value="<?php echo esc_attr( $default_filename ); ?>" placeholder="Nom du fichier" required></div>
              </div>
              <div class="acdc-contract-grid">
                <div class="acdc-contract-label">Type <span class="acdc-required">*</span></div>
                <div>
                  <select name="export_type" required>
                    <option value="excel">Excel</option>
                    <option value="csv">CSV</option>
                  </select>
                </div>
              </div>
              <p class="acdc-actions-end"><button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button><button type="submit" class="acdc-button acdc-button-primary">Exécuter l'action</button></p>
            </form>
          </div>
        </div>
      </div>
      <div class="acdc-modal-shell" id="<?php echo esc_attr( $edit_modal_id ); ?>" hidden>
        <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
        <div class="acdc-modal-dialog acdc-modal-dialog-contract">
          <div class="acdc-modal-header"><h4>Modifier le résultat des évaluations des acquis</h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
          <div class="acdc-modal-body">
            <div class="acdc-convocation-edit-topbar"><div>Apprenant : <?php echo esc_html( $context['learner_name'] ); ?></div><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->get_evaluation_result_download_url( $entry, 'attachment' ) ); ?>">Télécharger le document actuel</a></div>
            <div class="acdc-convocation-edit-warning">Attention, cela va écraser l’ancien document et le remplacer par le nouveau document.</div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
              <?php wp_nonce_field( 'acdc_update_evaluation_result_document_' . (int) $entry->id ); ?>
              <input type="hidden" name="action" value="acdc_update_evaluation_result_document">
              <input type="hidden" name="registration_id" value="<?php echo esc_attr( (int) $entry->id ); ?>">
              <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
              <input type="hidden" name="tab" value="evaluation_results">
              <div class="acdc-contract-grid">
                <div class="acdc-contract-label">Résultat des évaluations des acquis</div>
                <div>
                  <div class="acdc-contract-file-preview">
                    <div class="acdc-contract-file-preview-thumb"><?php echo $this->render_inline_icon( 'document', 30 ); ?></div>
                    <div class="acdc-contract-file-preview-name"><?php echo esc_html( $file_name ); ?></div>
                  </div>
                  <label class="acdc-upload-dropzone">
                    <span class="acdc-button acdc-button-primary">Choisir le fichier</span>
                    <span>Déposez le fichier ou cliquez pour choisir</span>
                    <input type="file" name="evaluation_result_document_file" accept="application/pdf" required>
                  </label>
                </div>
              </div>
              <p class="acdc-actions-end"><button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button><button type="submit" class="acdc-button acdc-button-primary">Modifier le résultat des évaluations des acquis</button></p>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <style>
      .acdc-table-evaluation-results-documents .acdc-row-view-link,.acdc-table-evaluation-results-documents .acdc-row-edit-link{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;color:#1E4777;text-decoration:none;border:none;background:transparent;cursor:pointer}
      .acdc-table-evaluation-results-documents .acdc-row-view-link:hover,.acdc-table-evaluation-results-documents .acdc-row-edit-link:hover{color:#0C2D52}
      .acdc-table-evaluation-results-documents .acdc-program-link{color:#C5A253;text-decoration:none;font-weight:500}
      .acdc-table-evaluation-results-documents .acdc-program-link:hover{text-decoration:underline}
      .acdc-table-evaluation-results-documents th:last-child,.acdc-table-evaluation-results-documents td:last-child,.acdc-table-evaluation-results-documents th:nth-last-child(2),.acdc-table-evaluation-results-documents td:nth-last-child(2),.acdc-table-evaluation-results-documents th:nth-last-child(3),.acdc-table-evaluation-results-documents td:nth-last-child(3){text-align:center}
      .acdc-table-evaluation-results-documents small{display:block;margin-top:4px;color:#1E4777}
      .acdc-convocation-edit-topbar{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:16px 18px;border-radius:10px;background:#fbf8f7;color:#1E4777;margin-bottom:0}.acdc-convocation-edit-warning{background:linear-gradient(90deg,#C9A409 0%,#E9C77C 100%);color:#0B0706;text-align:center;padding:10px 14px;border-radius:0 0 6px 6px;margin-bottom:18px}
    </style>
    <?php
  }


public function render_admin_evaluations_page() { $this->render_admin_portal_wrapper( 'evaluations' ); }
}
