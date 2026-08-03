<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC 3.21.22 — Module Propositions Commerciales
 * Rendu UI (modale 3 étapes) + génération PDF charte ACDC
 */
trait Acdc_Proposals_Render_Trait {

  /* ---------------------------------------------------------------
   * Bouton déclencheur — injecté sur la page Recueil des besoins
   * --------------------------------------------------------------- */
  /* -----------------------------------------------------------------------
   * Helper — Formatage des dates de séances
   * Stockage : "2026-05-13,2026-05-17,2026-06-10,2026-06-20" (ISO, virgules)
   * mode 'inline' → "13/05/2026, 17/05/2026, 10/06/2026, 20/06/2026"
   * mode 'list'   → "Séance 1 : 13/05/2026\nSéance 2 : 17/05/2026\n..."
   * Fallback si valeur texte libre (legacy) → retour brut
   * -------------------------------------------------------------------- */
  private function acdc_format_seances_list( $raw, $mode = 'list' ) {
    if ( empty( $raw ) ) {
      return '';
    }
    $parts = array_values( array_filter( array_map( 'trim', explode( ',', (string) $raw ) ) ) );
    if ( empty( $parts ) ) {
      return $raw;
    }
    // Vérifier que toutes les parties sont des dates ISO YYYY-MM-DD
    foreach ( $parts as $d ) {
      if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
        return $raw; // Texte libre legacy : retour brut
      }
    }
    $formatted = array();
    foreach ( $parts as $i => $d ) {
      $ts = strtotime( $d );
      if ( ! $ts ) {
        continue;
      }
      if ( 'inline' === $mode ) {
        $formatted[] = date_i18n( 'd/m/Y', $ts );
      } else {
        $formatted[] = 'Séance ' . ( $i + 1 ) . ' : ' . date_i18n( 'd/m/Y', $ts );
      }
    }
    return 'inline' === $mode
      ? implode( ', ', $formatted )
      : implode( "\n", $formatted );
  }

  private function render_proposal_trigger_button( $need_id ) {
    if ( ! $need_id ) {
      return;
    }
    /* hotfix11 — Bases + variantes, triées par groupe (get_formations ORDER BY composite) */
    $formations = $this->get_formations( array( 'archived' => false ) );
    $trainers   = $this->get_trainers_for_proposal();
    $need       = $this->get_need( $need_id );
    $prefill    = $this->build_proposal_from_need( $need_id );
    $nonce      = wp_create_nonce( 'acdc_proposal_nonce' );
    $ajax_url   = admin_url( 'admin-ajax.php' );
    ?>
    <button type="button"
            class="acdc-button acdc-button-soft"
            id="acdc-proposal-trigger"
            data-acdc-no-iconize="1"
            data-need-id="<?php echo esc_attr( $need_id ); ?>"
            data-nonce="<?php echo esc_attr( $nonce ); ?>"
            data-ajax="<?php echo esc_url( $ajax_url ); ?>"
            onclick="acdcOpenProposalModal(<?php echo esc_attr( $need_id ); ?>)">
      <?php echo $this->render_inline_icon( 'file-text', 20 ); ?>
      Cr&#233;er une proposition commerciale
    </button>

    <?php $this->render_proposal_modal( $need_id, $prefill, $formations, $trainers, $nonce, $ajax_url ); ?>
    <?php
  }

  /* ---------------------------------------------------------------
   * Modale 3 étapes
   * --------------------------------------------------------------- */
  private function render_proposal_modal( $need_id, $prefill, $formations, $trainers, $nonce, $ajax_url ) {
    $company_profile = get_option( 'acdc_of_company_profile', array() );
    $acdc_name    = ! empty( $company_profile['company_name'] ) ? $company_profile['company_name'] : 'ACDC Formation';
    $acdc_address = ! empty( $company_profile['address'] ) ? $company_profile['address'] : '7 avenue Paul C&#233;zanne, 83310 Cogolin';
    $acdc_nda     = ! empty( $company_profile['nda_number'] ) ? $company_profile['nda_number'] : '93 83 08347 83';
    $acdc_siret   = ! empty( $company_profile['siret'] ) ? $company_profile['siret'] : '';
    ?>
    <div id="acdc-proposal-modal" class="acdc-modal-shell" hidden style="z-index:100001">
      <div class="acdc-modal-backdrop" id="acdc-proposal-backdrop"></div>
      <div class="acdc-modal-dialog" style="max-width:720px;max-height:90vh;overflow-y:auto;border-radius:12px;">

        <div class="acdc-modal-header" style="position:sticky;top:0;background:#fff;z-index:2;border-bottom:1px solid #f0e6dc;padding:16px 24px;">
          <div style="display:flex;align-items:center;gap:12px;">
            <h4 style="color:#0f2c52;font-size:16px;font-weight:600;margin:0;">Cr&#233;er une proposition commerciale</h4>
            <div id="acdc-prop-steps" style="display:flex;gap:6px;margin-left:auto;">
              <?php for ( $i = 1; $i <= 3; $i++ ) : ?>
                <span id="acdc-prop-step-<?php echo $i; ?>"
                      style="width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;background:<?php echo 1 === $i ? '#d6a353' : '#f0e6dc'; ?>;color:<?php echo 1 === $i ? '#fff' : '#8a6d2a'; ?>">
                  <?php echo $i; ?>
                </span>
              <?php endfor; ?>
            </div>
          </div>
          <button type="button" class="acdc-modal-close" onclick="acdcCloseProposalModal()" style="color:#4b5d76;">&times;</button>
        </div>

        <div class="acdc-modal-body" style="padding:24px;">

          <?php /* ---- Étape 1 : Formation & organisation ---- */ ?>
          <div id="acdc-prop-panel-1">
            <div style="font-size:11px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.08em;margin-bottom:14px;">
              &#201;tape 1 &#8212; Formation &amp; organisation
            </div>
            <input type="hidden" id="acdc-prop-need-id" value="<?php echo esc_attr( $need_id ); ?>">
            <input type="hidden" id="acdc-prop-proposal-id" value="0">

            <p style="margin-bottom:12px;">
              <label style="font-size:13px;font-weight:500;color:#0f2c52;display:block;margin-bottom:5px;">Formation du catalogue *</label>
              <select id="acdc-prop-formation-id" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
                <option value="">&#8212; S&#233;lectionner une formation &#8212;</option>
                <?php foreach ( $formations as $f ) : ?>
                  <option value="<?php echo esc_attr( $f->id ); ?>"
                          data-title="<?php echo esc_attr( $f->title ); ?>"
                          data-duration="<?php echo esc_attr( $f->duration ); ?>"
                          data-price="<?php echo esc_attr( $f->price_ht ?: '' ); ?>"
                          data-objectives="<?php echo esc_attr( $f->objectives ?: '' ); ?>"
                          data-program="<?php echo esc_attr( $f->program ?: '' ); ?>"
                          data-thematique="<?php echo esc_attr( $f->thematique ?: '' ); ?>">
                    <?php
                      /* hotfix10 — [ID] Titre — Modalité — Prix */
                      echo esc_html( $this->build_formation_option_label( $f ) );
                    ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </p>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
              <p style="margin:0">
                <label style="font-size:12px;font-weight:500;color:#0f2c52;display:block;margin-bottom:4px;">Nombre de jours *</label>
                <input type="number" id="acdc-prop-days" min="1" value="<?php echo esc_attr( $prefill['formation_days'] ?? 1 ); ?>" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;" oninput="acdcPropCalcTotal()">
              </p>
              <p style="margin:0">
                <label style="font-size:12px;font-weight:500;color:#0f2c52;display:block;margin-bottom:4px;">Heures/jour</label>
                <input type="number" id="acdc-prop-hours" min="1" max="10" value="7" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
              </p>
              <p style="margin:0">
                <label style="font-size:12px;font-weight:500;color:#0f2c52;display:block;margin-bottom:4px;">Tarif jour (&#8364; HT) *</label>
                <input type="number" id="acdc-prop-price" min="0" step="10" value="900" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;" oninput="acdcPropCalcTotal()">
              </p>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
              <p style="margin:0">
                <label style="font-size:12px;font-weight:500;color:#0f2c52;display:block;margin-bottom:4px;">Nombre d&#8217;apprenants</label>
                <input type="number" id="acdc-prop-learners" min="1" value="<?php echo esc_attr( $prefill['formation_learners_count'] ?? 1 ); ?>" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
              </p>
              <p style="margin:0">
                <label style="font-size:12px;font-weight:500;color:#0f2c52;display:block;margin-bottom:4px;">Financement</label>
                <?php
                $prefill_funding = (string) ( $prefill['formation_funding'] ?? '' );
                /* Détecter si c'est un OPCO nommé (ex : "OPCO — ATLAS") */
                $is_opco_named = ( 'OPCO' === $prefill_funding || 0 === strpos( $prefill_funding, 'OPCO' ) );
                $funding_select_val = $is_opco_named ? 'OPCO' : $prefill_funding;
                ?>
                <select id="acdc-prop-funding-select" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
                  <?php foreach ( $this->get_need_funding_options() as $fk => $fl ) : ?>
                    <option value="<?php echo esc_attr( $fk ); ?>" <?php selected( $funding_select_val, $fk ); ?>><?php echo esc_html( $fl ); ?></option>
                  <?php endforeach; ?>
                </select>
                <!-- Champ caché qui stocke la valeur finale transmise au JS -->
                <input type="hidden" id="acdc-prop-funding" value="<?php echo esc_attr( $prefill_funding ); ?>">
                <div id="acdc-prop-opco-row" style="margin-top:6px;<?php echo $is_opco_named ? '' : 'display:none;'; ?>">
                  <label style="font-size:11px;color:#4b5d76;display:block;margin-bottom:3px;">OPCO / Financeur</label>
                  <select id="acdc-prop-funder-select" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
                    <option value="">— Sélectionner un OPCO —</option>
                    <?php foreach ( $this->get_funders() as $funder ) : ?>
                      <option value="OPCO — <?php echo esc_attr( $funder->name ); ?>"
                        <?php selected( $prefill_funding, 'OPCO — ' . $funder->name ); ?>>
                        <?php echo esc_html( $funder->name ); ?><?php if ( $funder->sector ) : ?> (<?php echo esc_html( $funder->sector ); ?>)<?php endif; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <script>
                (function(){
                  var sel = document.getElementById('acdc-prop-funding-select');
                  var opcoRow = document.getElementById('acdc-prop-opco-row');
                  var hidden = document.getElementById('acdc-prop-funding');
                  var opcoSel = document.getElementById('acdc-prop-funder-select');
                  if (!sel) return;
                  function sync(){
                    var v = sel.value;
                    opcoRow.style.display = (v === 'OPCO') ? '' : 'none';
                    if (v === 'OPCO') {
                      hidden.value = opcoSel.value || 'OPCO';
                    } else {
                      hidden.value = v;
                    }
                  }
                  sel.addEventListener('change', sync);
                  if (opcoSel) {
                    opcoSel.addEventListener('change', sync);
                  }
                  sync();
                })();
                </script>
              </p>
            </div>

            <p style="margin-bottom:12px;">
              <label style="font-size:12px;font-weight:500;color:#0f2c52;display:block;margin-bottom:6px;">Dates des séances</label>
              <input type="hidden" id="acdc-prop-dates" value="">
              <span id="acdc-modal-seances-chips" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;min-height:0;"></span>
              <span style="display:flex;gap:6px;align-items:center;">
                <input type="date" id="acdc-modal-seance-picker" style="height:36px;border-radius:8px;border:1px solid #dfe5ee;padding:0 10px;font-size:13px;color:#0f2c52;">
                <button type="button" id="acdc-modal-seance-add"
                        style="height:36px;padding:0 14px;background:#0f2c52;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">
                  + Ajouter
                </button>
              </span>
            </p>
            <script>
            (function(){
              var dates = [];
              var chipsEl = document.getElementById('acdc-modal-seances-chips');
              var hiddenEl = document.getElementById('acdc-prop-dates');
              var picker  = document.getElementById('acdc-modal-seance-picker');
              var addBtn  = document.getElementById('acdc-modal-seance-add');
              if(!chipsEl||!hiddenEl||!picker||!addBtn){return;}
              var months=['jan.','fév.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];
              function fmtDate(iso){var p=iso.split('-');if(p.length!==3){return iso;}return p[2]+' '+months[parseInt(p[1],10)-1]+' '+p[0];}
              function render(){
                chipsEl.innerHTML='';
                dates.forEach(function(d,i){
                  var chip=document.createElement('span');
                  chip.style.cssText='display:inline-flex;align-items:center;gap:5px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:20px;padding:3px 10px 3px 12px;font-size:12px;font-weight:600;color:#1e3a8a;white-space:nowrap;';
                  chip.innerHTML='<span>Séance '+(i+1)+' — '+fmtDate(d)+'</span>';
                  var rm=document.createElement('button');
                  rm.type='button';rm.innerHTML='×';
                  rm.setAttribute('aria-label','Supprimer');
                  rm.style.cssText='background:none;border:none;font-size:15px;line-height:1;cursor:pointer;color:#6366f1;padding:0 0 1px;';
                  rm.addEventListener('click',function(){dates.splice(i,1);render();});
                  chip.appendChild(rm);chipsEl.appendChild(chip);
                });
                hiddenEl.value=dates.join(',');
              }
              addBtn.addEventListener('click',function(){
                var v=picker.value;if(!v){return;}
                if(dates.indexOf(v)===-1){dates.push(v);dates.sort();render();}
                picker.value='';picker.focus();
              });
              picker.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();addBtn.click();}});
              render();
            })();
            </script>

            <p style="margin-bottom:12px;">
              <label style="font-size:12px;font-weight:500;color:#0f2c52;display:block;margin-bottom:4px;">Lieu de formation</label>
              <input type="text" id="acdc-prop-location" value="<?php echo esc_attr( $prefill['client_address'] ?? '' ); ?>" placeholder="Dans vos locaux, adresse…" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
            </p>

            <?php /* Formateurs */ ?>
            <p style="margin-bottom:4px;">
              <label style="font-size:12px;font-weight:500;color:#0f2c52;display:block;margin-bottom:4px;">Formateurs</label>
              <div id="acdc-prop-trainers" style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php foreach ( $trainers as $trainer ) :
                  $checked = (int) $trainer->id === get_current_user_id() || ! empty( $trainer->is_self_trainer ) ? 'checked' : '';
                ?>
                  <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#0f2c52;cursor:pointer;background:#fbf8f7;border:1px solid #f0e6dc;border-radius:8px;padding:6px 10px;">
                    <input type="checkbox" name="acdc_prop_trainer[]" value="<?php echo esc_attr( $trainer->id ); ?>" <?php echo $checked; ?>>
                    <?php echo esc_html( trim( $trainer->first_name . ' ' . $trainer->last_name ) ); ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </p>

            <?php /* Total calculé */ ?>
            <div style="background:linear-gradient(135deg,#fef6e4 0%,#fbf8f7 100%);border:1px solid #f0e6dc;border-radius:10px;padding:12px 16px;margin-top:14px;display:flex;align-items:center;justify-content:space-between;">
              <span style="font-size:13px;color:#4b5d76;">Total de la proposition</span>
              <strong id="acdc-prop-total" style="font-size:20px;font-weight:700;color:#0f2c52;">2&#160;700&#160;&#8364;</strong>
            </div>
          </div>

          <?php /* ---- Étape 2 : À propos du client ---- */ ?>
          <div id="acdc-prop-panel-2" hidden>
            <div style="font-size:11px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.08em;margin-bottom:14px;">
              &#201;tape 2 &#8212; &#192; propos du client
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
              <p style="margin:0">
                <label style="font-size:12px;font-weight:500;color:#0f2c52;display:block;margin-bottom:4px;">Nom du destinataire</label>
                <input type="text" id="acdc-prop-client-name" value="<?php echo esc_attr( $prefill['client_name'] ?? '' ); ?>" placeholder="Prénom NOM" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
              </p>
              <p style="margin:0">
                <label style="font-size:12px;font-weight:500;color:#0f2c52;display:block;margin-bottom:4px;">Titre / Fonction</label>
                <input type="text" id="acdc-prop-client-title" value="<?php echo esc_attr( $prefill['client_title'] ?? '' ); ?>" placeholder="Directeur &amp; CEO" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
              </p>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
              <p style="margin:0">
                <label style="font-size:12px;font-weight:500;color:#0f2c52;display:block;margin-bottom:4px;">Raison sociale</label>
                <input type="text" id="acdc-prop-client-company" value="<?php echo esc_attr( $prefill['client_company'] ?? '' ); ?>" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
              </p>
              <p style="margin:0">
                <label style="font-size:12px;font-weight:500;color:#0f2c52;display:block;margin-bottom:4px;">SIRET</label>
                <input type="text" id="acdc-prop-client-siret" value="<?php echo esc_attr( $prefill['client_siret'] ?? '' ); ?>" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
              </p>
            </div>

            <div style="display:grid;grid-template-columns:120px 1fr;gap:10px;margin-bottom:12px;">
              <p style="margin:0">
                <label style="font-size:12px;font-weight:500;color:#0f2c52;display:block;margin-bottom:4px;">Code postal</label>
                <input type="text" id="acdc-prop-client-postal-code" value="<?php echo esc_attr( $prefill['client_postal_code'] ?? '' ); ?>" placeholder="83310" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
              </p>
              <p style="margin:0">
                <label style="font-size:12px;font-weight:500;color:#0f2c52;display:block;margin-bottom:4px;">Ville</label>
                <input type="text" id="acdc-prop-client-city" value="<?php echo esc_attr( $prefill['client_city'] ?? '' ); ?>" placeholder="Cogolin" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
              </p>
            </div>

            <p style="margin-bottom:12px;">
              <label style="font-size:12px;font-weight:500;color:#0f2c52;display:block;margin-bottom:4px;">Site web (pour la g&#233;n&#233;ration IA)</label>
              <input type="url" id="acdc-prop-client-website" value="<?php echo esc_attr( $prefill['client_website'] ?? '' ); ?>" placeholder="https://…" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
            </p>

            <p style="margin-bottom:12px;">
              <label style="font-size:12px;font-weight:500;color:#0f2c52;display:block;margin-bottom:4px;">Activit&#233;</label>
              <input type="text" id="acdc-prop-client-activity" value="<?php echo esc_attr( $prefill['client_activity'] ?? '' ); ?>" placeholder="formation continue d&#8217;adultes…" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
            </p>

            <p style="margin-bottom:8px;">
              <label style="font-size:12px;font-weight:500;color:#0f2c52;display:block;margin-bottom:4px;">
                &#192; propos de l&#8217;entreprise (texte du PDF)
              </label>
              <textarea id="acdc-prop-client-about" rows="6" style="width:100%;border-radius:10px;border:1px solid #dfe5ee;padding:10px 12px;font-size:13px;line-height:1.55;resize:vertical;" placeholder="Description de l&#8217;entreprise cliente…"><?php echo esc_textarea( $prefill['client_about_text'] ?? '' ); ?></textarea>
            </p>
            <div style="display:flex;align-items:center;gap:10px;">
              <button type="button" id="acdc-prop-ai-btn"
                      class="acdc-button acdc-button-soft"
                      style="height:36px;font-size:12px;"
                      onclick="acdcPropGenerateAbout()">
                &#9889; G&#233;n&#233;rer via IA
              </button>
              <span id="acdc-prop-ai-status" style="font-size:12px;color:#4b5d76;"></span>
            </div>
          </div>

          <?php /* ---- Étape 3 : Récap & génération ---- */ ?>
          <div id="acdc-prop-panel-3" hidden>
            <div style="font-size:11px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.08em;margin-bottom:14px;">
              &#201;tape 3 &#8212; R&#233;capitulatif &amp; g&#233;n&#233;ration
            </div>
            <div id="acdc-prop-recap" style="background:#fbf8f7;border:1px solid #f0e6dc;border-radius:12px;padding:16px 20px;font-size:13px;color:#0f2c52;line-height:1.7;margin-bottom:16px;">
              Chargement du r&#233;capitulatif…
            </div>
            <div id="acdc-prop-pdf-result" style="display:none;background:#fef6e4;border:1px solid #d6a353;border-radius:10px;padding:12px 16px;margin-bottom:14px;font-size:13px;color:#8a6d2a;">
            </div>
          </div>

        </div><!-- /.acdc-modal-body -->

        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 24px;border-top:1px solid #f0e6dc;background:#fff;position:sticky;bottom:0;">
          <button type="button" id="acdc-prop-btn-prev" class="acdc-button acdc-button-soft" style="display:none;height:36px;font-size:13px;" onclick="acdcPropPrevStep()">
            &#8592; Pr&#233;c&#233;dent
          </button>
          <div style="display:flex;gap:8px;margin-left:auto;">
            <button type="button" id="acdc-prop-btn-save" class="acdc-button acdc-button-soft" style="height:36px;font-size:13px;" onclick="acdcPropSaveDraft()">
              Enregistrer le brouillon
            </button>
            <button type="button" id="acdc-prop-btn-next" class="acdc-button acdc-button-primary" style="height:36px;font-size:13px;" onclick="acdcPropNextStep()">
              Suivant &#8594;
            </button>
          </div>
        </div>

      </div><!-- /.acdc-modal-dialog -->
    </div>

    <script>
    var acdcPropCurrentStep = 1;
    var acdcPropNonce = '<?php echo esc_js( $nonce ); ?>';
    var acdcPropAjax  = '<?php echo esc_url( $ajax_url ); ?>';

    function acdcOpenProposalModal() {
      document.getElementById('acdc-proposal-modal').hidden = false;
    }
    function acdcCloseProposalModal() {
      document.getElementById('acdc-proposal-modal').hidden = true;
    }
    function acdcPropCalcTotal() {
      var days  = parseFloat(document.getElementById('acdc-prop-days').value) || 0;
      var price = parseFloat(document.getElementById('acdc-prop-price').value) || 0;
      var total = days * price;
      document.getElementById('acdc-prop-total').textContent = total.toLocaleString('fr-FR') + '\u00a0\u20ac';
    }
    document.getElementById('acdc-prop-formation-id').addEventListener('change', function() {
      var opt = this.options[this.selectedIndex];
      if (!opt.value) return;
      var title = opt.getAttribute('data-title') || '';
      var dur   = opt.getAttribute('data-duration') || '';
      var price = opt.getAttribute('data-price') || '900';
      document.getElementById('acdc-prop-price').value = price || 900;
      acdcPropCalcTotal();
    });
    function acdcPropNextStep() {
      if (acdcPropCurrentStep === 3) {
        acdcPropGeneratePdf();
        return;
      }
      if (acdcPropCurrentStep === 2) {
        acdcPropBuildRecap();
      }
      acdcPropCurrentStep++;
      acdcPropUpdateUI();
    }
    function acdcPropPrevStep() {
      if (acdcPropCurrentStep <= 1) return;
      acdcPropCurrentStep--;
      acdcPropUpdateUI();
    }
    function acdcPropUpdateUI() {
      for (var i = 1; i <= 3; i++) {
        document.getElementById('acdc-prop-panel-' + i).hidden = (i !== acdcPropCurrentStep);
        var dot = document.getElementById('acdc-prop-step-' + i);
        dot.style.background = (i === acdcPropCurrentStep) ? '#d6a353' : (i < acdcPropCurrentStep ? '#35b37e' : '#f0e6dc');
        dot.style.color = (i <= acdcPropCurrentStep) ? '#fff' : '#8a6d2a';
      }
      document.getElementById('acdc-prop-btn-prev').style.display = acdcPropCurrentStep > 1 ? '' : 'none';
      var nextBtn = document.getElementById('acdc-prop-btn-next');
      nextBtn.textContent = acdcPropCurrentStep === 3 ? 'G\u00e9n\u00e9rer le PDF' : 'Suivant \u2192';
    }
    function acdcPropBuildRecap() {
      var fOpt  = document.getElementById('acdc-prop-formation-id');
      var fTitle = fOpt.options[fOpt.selectedIndex] ? fOpt.options[fOpt.selectedIndex].text : '—';
      var days  = document.getElementById('acdc-prop-days').value || '—';
      var price = document.getElementById('acdc-prop-price').value || '—';
      var total = (parseFloat(days) || 0) * (parseFloat(price) || 0);
      var client = document.getElementById('acdc-prop-client-company').value || '—';
      var contact = document.getElementById('acdc-prop-client-name').value || '—';
      var dates = document.getElementById('acdc-prop-dates').value || 'Non renseign\u00e9es';
      document.getElementById('acdc-prop-recap').innerHTML =
        '<strong>Formation :</strong> ' + fTitle + '<br>' +
        '<strong>Dur\u00e9e :</strong> ' + days + ' jour(s)<br>' +
        '<strong>Tarif :</strong> ' + parseFloat(price).toLocaleString('fr-FR') + '\u00a0\u20ac/jour<br>' +
        '<strong>Total :</strong> ' + total.toLocaleString('fr-FR') + '\u00a0\u20ac net de TVA<br>' +
        '<strong>Client :</strong> ' + client + ' — ' + contact + '<br>' +
        '<strong>Dates :</strong> ' + dates;
    }
    function acdcPropGenerateAbout() {
      var btn = document.getElementById('acdc-prop-ai-btn');
      var status = document.getElementById('acdc-prop-ai-status');
      btn.disabled = true;
      status.textContent = 'Génération en cours…';
      var fd = new FormData();
      fd.append('action', 'acdc_proposal_generate_about');
      fd.append('nonce', acdcPropNonce);
      fd.append('client_company', document.getElementById('acdc-prop-client-company').value);
      fd.append('client_website', document.getElementById('acdc-prop-client-website').value);
      fd.append('client_activity', document.getElementById('acdc-prop-client-activity').value);
      fd.append('need_id', document.getElementById('acdc-prop-need-id').value || 0);
      fetch(acdcPropAjax, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          btn.disabled = false;
          if (data.success) {
            var aboutText = data.data.about || data.data.text || '';
            document.getElementById('acdc-prop-client-about').value = aboutText;
            status.textContent = '✓ Texte généré — vous pouvez le modifier.';
            status.style.color = '#35b37e';
          } else {
            status.textContent = 'Erreur : ' + (data.data ? data.data.message : 'inconnue');
            status.style.color = '#e06d6d';
          }
        })
        .catch(function() { btn.disabled = false; status.textContent = 'Erreur réseau.'; });
    }
    function acdcPropSaveDraft() {
      var fd = new FormData();
      fd.append('action', 'acdc_proposal_save_draft');
      fd.append('nonce', acdcPropNonce);
      var data = acdcPropCollectData();
      for (var k in data) { fd.append('proposal[' + k + ']', data[k]); }
      fetch(acdcPropAjax, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
          if (res.success) {
            document.getElementById('acdc-prop-proposal-id').value = res.data.id;
            alert('Brouillon enregistr\u00e9.');
          } else {
            alert('Erreur : ' + (res.data ? res.data.message : 'inconnue'));
          }
        });
    }
    function acdcPropGeneratePdf() {
      acdcPropSaveDraftThen(function(proposalId) {
        var fd = new FormData();
        fd.append('action', 'acdc_proposal_generate_pdf');
        fd.append('nonce', acdcPropNonce);
        fd.append('proposal_id', proposalId);
        document.getElementById('acdc-prop-btn-next').disabled = true;
        document.getElementById('acdc-prop-btn-next').textContent = 'G\u00e9n\u00e9ration\u2026';
        fetch(acdcPropAjax, { method: 'POST', body: fd })
          .then(function(r) { return r.json(); })
          .then(function(res) {
            document.getElementById('acdc-prop-btn-next').disabled = false;
            document.getElementById('acdc-prop-btn-next').textContent = 'G\u00e9n\u00e9rer le PDF';
            var div = document.getElementById('acdc-prop-pdf-result');
            div.style.display = '';
            if (res.success) {
              var label = res.data.type === 'html'
                ? 'Ouvrir la proposition (imprimer → PDF)'
                : 'Télécharger le PDF';
              div.innerHTML = '✓ Proposition générée — <a href="' + res.data.pdf_url + '" target="_blank" style="color:#8b5b23;font-weight:700;">' + label + '</a>';
            } else {
              div.innerHTML = 'Erreur : ' + (res.data ? res.data.message : 'inconnue');
              div.style.background = '#fff0f0';
              div.style.borderColor = '#f5c6c6';
              div.style.color = '#c2410c';
            }
          });
      });
    }
    function acdcPropSaveDraftThen(cb) {
      var fd = new FormData();
      fd.append('action', 'acdc_proposal_save_draft');
      fd.append('nonce', acdcPropNonce);
      var data = acdcPropCollectData();
      for (var k in data) { fd.append('proposal[' + k + ']', data[k]); }
      fetch(acdcPropAjax, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
          if (res.success) {
            document.getElementById('acdc-prop-proposal-id').value = res.data.id;
            cb(res.data.id);
          } else {
            alert('Erreur sauvegarde : ' + (res.data ? res.data.message : 'inconnue'));
          }
        });
    }
    function acdcPropCollectData() {
      var fOpt = document.getElementById('acdc-prop-formation-id');
      var formationId = fOpt.value || 0;
      var fTitle = formationId ? (fOpt.options[fOpt.selectedIndex].getAttribute('data-title') || '') : '';
      var fDur   = formationId ? (fOpt.options[fOpt.selectedIndex].getAttribute('data-duration') || '') : '';
      var days   = parseInt(document.getElementById('acdc-prop-days').value) || 1;
      var hours  = parseInt(document.getElementById('acdc-prop-hours').value) || 7;
      var price  = parseFloat(document.getElementById('acdc-prop-price').value) || 0;
      var selectedTrainers = [];
      document.querySelectorAll('#acdc-prop-trainers input[type=checkbox]:checked').forEach(function(cb) {
        selectedTrainers.push(cb.value);
      });
      return {
        id:                        document.getElementById('acdc-prop-proposal-id').value || 0,
        need_id:                   document.getElementById('acdc-prop-need-id').value || 0,
        formation_id:              formationId,
        formation_title:           fTitle,
        formation_duration:        fDur,
        formation_days:            days,
        formation_hours_per_day:   hours,
        formation_price_per_day:   price,
        formation_total:           days * price,
        formation_learners_count:  document.getElementById('acdc-prop-learners').value || 1,
        formation_funding:         document.getElementById('acdc-prop-funding').value || '',
        formation_dates:           document.getElementById('acdc-prop-dates').value || '',
        formation_location:        document.getElementById('acdc-prop-location').value || '',
        trainer_ids:               selectedTrainers.join(','),
        client_name:               document.getElementById('acdc-prop-client-name').value || '',
        client_title:              document.getElementById('acdc-prop-client-title').value || '',
        client_company:            document.getElementById('acdc-prop-client-company').value || '',
        client_siret:              document.getElementById('acdc-prop-client-siret').value || '',
        client_postal_code:        document.getElementById('acdc-prop-client-postal-code') ? document.getElementById('acdc-prop-client-postal-code').value || '' : '',
        client_city:               document.getElementById('acdc-prop-client-city') ? document.getElementById('acdc-prop-client-city').value || '' : '',
        client_website:            document.getElementById('acdc-prop-client-website').value || '',
        client_activity:           document.getElementById('acdc-prop-client-activity').value || '',
        client_about_text:         document.getElementById('acdc-prop-client-about').value || '',
        status:                    'brouillon',
      };
    }
    </script>
    <?php
  }

  /* ---------------------------------------------------------------
   * Helper : formateurs pour le sélecteur
   * --------------------------------------------------------------- */
  private function get_trainers_for_proposal() {
    global $wpdb;
    /* Tous les formateurs sans restriction — la sélection est explicite par l'utilisateur */
    return (array) $wpdb->get_results(
      "SELECT id, first_name, last_name, is_self_trainer, description_text, photo_url FROM {$this->trainer_table} ORDER BY is_self_trainer DESC, last_name ASC, first_name ASC"
    );
  }

  /* ---------------------------------------------------------------
   * Génération PDF — structure charte ACDC / style Canva
   * --------------------------------------------------------------- */

  /* ---------------------------------------------------------------
   * Helper : charge une image distante → [data, w, h] ou null
   * --------------------------------------------------------------- */
  private function proposal_fetch_image( $url ) {
    if ( empty( $url ) ) {
      return null;
    }
    // Essayer d'abord en local (plus rapide)
    $upload_dir = wp_upload_dir();
    $local_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
    if ( file_exists( $local_path ) ) {
      $data = @file_get_contents( $local_path );
    } else {
      $resp = wp_remote_get( $url, array( 'timeout' => 10, 'sslverify' => true ) );
      if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
        return null;
      }
      $data = wp_remote_retrieve_body( $resp );
    }
    if ( empty( $data ) ) {
      return null;
    }
    // Vérifier que c'est bien un JPEG (le moteur PDF natif ne supporte que DCTDecode = JPEG)
    if ( 0 !== strpos( $data, "\xFF\xD8" ) ) {
      // Convertir via GD si disponible
      if ( function_exists( 'imagecreatefromstring' ) && function_exists( 'imagejpeg' ) ) {
        $im = @imagecreatefromstring( $data );
        if ( false === $im ) { return null; }
        ob_start();
        imagejpeg( $im, null, 85 );
        $data = ob_get_clean();
        imagedestroy( $im );
        if ( empty( $data ) ) { return null; }
      } else {
        return null;
      }
    }
    $size = @getimagesizefromstring( $data );
    if ( ! $size || $size[0] < 1 || $size[1] < 1 ) {
      return null;
    }
    return array( 'data' => $data, 'w' => (int) $size[0], 'h' => (int) $size[1] );
  }

  /* ---------------------------------------------------------------
   * Pages PDF enrichies avec images catalogue + contenu formation
   * --------------------------------------------------------------- */
  private function proposal_pdf_page_cover_v2( $proposal, $formation ) {
    $w = 595; $h = 842;
    $title    = $proposal->formation_title ?: 'Formation professionnelle';
    $client   = $proposal->client_name ?: '';
    $ctitle   = $proposal->client_title ?: '';
    $company  = $proposal->client_company ?: '';
    $date     = wp_date( 'j F Y' );
    $lines    = array( array( 'type' => 'page_meta', 'width' => $w, 'height' => $h ) );

    /* Image catalogue en haut de page */
    $img_url = ! empty( $formation->catalog_image_url ) ? (string) $formation->catalog_image_url : '';
    $img = $img_url ? $this->proposal_fetch_image( $img_url ) : null;
    if ( $img ) {
      /* Image pleine largeur en haut : 595 x 240 pts */
      $disp_h = round( 595 * $img['h'] / max( 1, $img['w'] ) );
      $disp_h = min( $disp_h, 240 );
      $lines[] = array(
        'type'          => 'image', 'image_key'     => 'cover_img',
        'image_data'    => $img['data'], 'image_width'   => $img['w'], 'image_height'  => $img['h'],
        'display_width' => 595, 'display_height' => $disp_h,
        'x' => 0, 'y' => $h - $disp_h,
      );
      $img_bottom = $h - $disp_h;
    } else {
      $img_bottom = $h - 120;
      $lines[] = array( 'type' => 'rect', 'x' => 0, 'y' => $h - 120, 'width' => 595, 'height' => 120, 'fill_color' => '#C5A253' );
    }

    /* Bande titre sur l'image */
    $lines[] = array( 'type' => 'rect', 'x' => 0, 'y' => $img_bottom, 'width' => 595, 'height' => 50, 'fill_color' => '#C5A253' );
    $lines[] = array( 'type' => 'text', 'x' => 28, 'y' => $img_bottom + 32, 'text' => $this->proposal_pdf_clean( $title ), 'font' => 'bold', 'size' => 16, 'color' => '#ffffff' );

    /* Zone blanche milieu */
    $lines[] = array( 'type' => 'rect', 'x' => 28, 'y' => $img_bottom - 190, 'width' => 539, 'height' => 180, 'fill_color' => '#fbf8f7' );
    $lines[] = array( 'type' => 'rect', 'x' => 28, 'y' => $img_bottom - 190, 'width' => 539, 'height' => 180, 'stroke_color' => '#f0e6dc' );

    /* Sous-titre PROPOSITION COMMERCIALE */
    $lines[] = array( 'type' => 'rect', 'x' => 28, 'y' => $img_bottom - 230, 'width' => 539, 'height' => 32, 'fill_color' => '#C5A253' );
    $lines[] = array( 'type' => 'text', 'x' => 297, 'y' => $img_bottom - 209, 'text' => 'PROPOSITION COMMERCIALE', 'font' => 'bold', 'size' => 13, 'color' => '#ffffff', 'align' => 'center' );

    /* Nom société cliente */
    $lines[] = array( 'type' => 'text', 'x' => 297, 'y' => $img_bottom - 130, 'text' => $this->proposal_pdf_clean( $company ), 'font' => 'bold', 'size' => 15, 'color' => '#0f2c52', 'align' => 'center' );
    $lines[] = array( 'type' => 'text', 'x' => 297, 'y' => $img_bottom - 152, 'text' => "A l'attention de :", 'font' => 'regular', 'size' => 11, 'color' => '#4b5d76', 'align' => 'center' );
    $lines[] = array( 'type' => 'text', 'x' => 297, 'y' => $img_bottom - 168, 'text' => $this->proposal_pdf_clean( $client ), 'font' => 'bold', 'size' => 12, 'color' => '#0f2c52', 'align' => 'center' );
    $lines[] = array( 'type' => 'text', 'x' => 297, 'y' => $img_bottom - 183, 'text' => $this->proposal_pdf_clean( $ctitle ), 'font' => 'regular', 'size' => 11, 'color' => '#4b5d76', 'align' => 'center' );

    /* Bande dorée bas */
    $lines[] = array( 'type' => 'rect', 'x' => 0, 'y' => 0, 'width' => 595, 'height' => 75, 'fill_color' => '#C5A253' );
    $lines[] = array( 'type' => 'text', 'x' => 28, 'y' => 55, 'text' => 'Date : ' . $date, 'font' => 'regular', 'size' => 10, 'color' => '#ffffff' );
    $lines[] = array( 'type' => 'text', 'x' => 28, 'y' => 38, 'text' => 'ACDC Formation  |  dcontal@acdc-formation.com  |  06 78 26 91 10', 'font' => 'regular', 'size' => 10, 'color' => '#ffffff' );
    return $lines;
  }

  private function proposal_pdf_page_objectives_program( $proposal, $formation ) {
    $w = 595; $h = 842;
    $lines = array( array( 'type' => 'page_meta', 'width' => $w, 'height' => $h ) );
    /* En-tête doré */
    $lines[] = array( 'type' => 'rect', 'x' => 0, 'y' => $h - 55, 'width' => $w, 'height' => 55, 'fill_color' => '#C5A253' );
    $lines[] = array( 'type' => 'text', 'x' => 28, 'y' => $h - 28, 'text' => 'OBJECTIFS & PROGRAMME', 'font' => 'bold', 'size' => 13, 'color' => '#ffffff' );

    /* Objectif général */
    $y = $h - 80;
    $lines[] = array( 'type' => 'text', 'x' => 28, 'y' => $y, 'text' => 'Objectif de la formation', 'font' => 'bold', 'size' => 12, 'color' => '#C5A253' );
    $y -= 18;
    $obj_text = ! empty( $formation->objectives ) ? strip_tags( (string) $formation->objectives ) : 'Former les participants a l\'usage professionnel de l\'IA generative.';
    $obj_lines = $this->proposal_pdf_wrap_text( $this->proposal_pdf_clean( $obj_text ), 80 );
    foreach ( array_slice( $obj_lines, 0, 5 ) as $ol ) {
      $lines[] = array( 'type' => 'text', 'x' => 28, 'y' => $y, 'text' => $ol, 'font' => 'regular', 'size' => 10, 'color' => '#4b5d76' );
      $y -= 15;
    }

    /* Séparateur */
    $y -= 8;
    $lines[] = array( 'type' => 'rect', 'x' => 28, 'y' => $y, 'width' => 539, 'height' => 1, 'fill_color' => '#f0e6dc' );
    $y -= 18;

    /* Programme */
    $lines[] = array( 'type' => 'text', 'x' => 28, 'y' => $y, 'text' => 'Programme', 'font' => 'bold', 'size' => 12, 'color' => '#C5A253' );
    $y -= 16;
    $prog_text = ! empty( $formation->program ) ? strip_tags( (string) $formation->program ) : '';
    $prog_lines = $this->proposal_pdf_wrap_text( $this->proposal_pdf_clean( $prog_text ), 80 );
    foreach ( array_slice( $prog_lines, 0, 32 ) as $pl ) {
      if ( $y < 50 ) { break; }
      $lines[] = array( 'type' => 'text', 'x' => 28, 'y' => $y, 'text' => $pl, 'font' => 'regular', 'size' => 10, 'color' => '#4b5d76' );
      $y -= 15;
    }
    $lines[] = array( 'type' => 'text', 'x' => $w - 28, 'y' => 20, 'text' => '3', 'font' => 'regular', 'size' => 10, 'color' => '#C5A253', 'align' => 'right' );
    return $lines;
  }

  private function proposal_pdf_page_thematique( $proposal, $formation ) {
    $w = 595; $h = 842;
    $lines = array( array( 'type' => 'page_meta', 'width' => $w, 'height' => $h ) );

    /* Chercher l'image de la thématique */
    $thematique_img = null;
    if ( ! empty( $formation->specialty ) ) {
      global $wpdb;
      $th = $wpdb->get_row( $wpdb->prepare(
        "SELECT image_hero_url, label FROM {$this->thematique_table} WHERE code = %s OR label = %s LIMIT 1",
        (string) $formation->specialty, (string) $formation->specialty
      ) );
      if ( $th && ! empty( $th->image_hero_url ) ) {
        $thematique_img = $this->proposal_fetch_image( (string) $th->image_hero_url );
      }
    }

    /* Image thématique ou fallback image catalogue */
    $img = $thematique_img;
    if ( ! $img && ! empty( $formation->catalog_image_url ) ) {
      $img = $this->proposal_fetch_image( (string) $formation->catalog_image_url );
    }

    if ( $img ) {
      $disp_h = min( 200, round( 595 * $img['h'] / max( 1, $img['w'] ) ) );
      $lines[] = array(
        'type'          => 'image', 'image_key'     => 'th_img',
        'image_data'    => $img['data'], 'image_width'   => $img['w'], 'image_height'  => $img['h'],
        'display_width' => 595, 'display_height' => $disp_h,
        'x' => 0, 'y' => $h - $disp_h,
      );
      $img_bottom = $h - $disp_h;
    } else {
      $img_bottom = $h - 80;
    }

    $lines[] = array( 'type' => 'rect', 'x' => 0, 'y' => $img_bottom, 'width' => $w, 'height' => 50, 'fill_color' => '#C5A253' );
    $lines[] = array( 'type' => 'text', 'x' => 28, 'y' => $img_bottom + 30, 'text' => 'A PROPOS DE VOUS', 'font' => 'bold', 'size' => 14, 'color' => '#ffffff' );

    $y = $img_bottom - 30;
    $co = $proposal->client_company ?: '';
    $lines[] = array( 'type' => 'text', 'x' => 297, 'y' => $y, 'text' => $this->proposal_pdf_clean( $co ), 'font' => 'bold', 'size' => 15, 'color' => '#0f2c52', 'align' => 'center' );
    $y -= 20;

    $about = $proposal->client_about_text ?: 'Description non renseignee.';
    $about_lines = $this->proposal_pdf_wrap_text( $this->proposal_pdf_clean( $about ), 78 );
    foreach ( array_slice( $about_lines, 0, 20 ) as $al ) {
      if ( $y < 120 ) { break; }
      $lines[] = array( 'type' => 'text', 'x' => 28, 'y' => $y, 'text' => $al, 'font' => 'regular', 'size' => 10, 'color' => '#4b5d76' );
      $y -= 15;
    }
    $y -= 10;
    if ( $proposal->client_siret ) {
      $lines[] = array( 'type' => 'text', 'x' => 28, 'y' => $y, 'text' => 'SIRET : ' . $this->proposal_pdf_clean( (string) $proposal->client_siret ), 'font' => 'regular', 'size' => 10, 'color' => '#4b5d76' );
      $y -= 14;
    }
    if ( $proposal->client_address ) {
      $lines[] = array( 'type' => 'text', 'x' => 28, 'y' => $y, 'text' => 'Siege : ' . $this->proposal_pdf_clean( (string) $proposal->client_address ), 'font' => 'regular', 'size' => 10, 'color' => '#4b5d76' );
    }
    $lines[] = array( 'type' => 'text', 'x' => $w - 28, 'y' => 20, 'text' => '2', 'font' => 'regular', 'size' => 10, 'color' => '#C5A253', 'align' => 'right' );
    return $lines;
  }



  private function generate_proposal_html( $proposal ) {
    $upload_dir = wp_upload_dir();
    $dir        = $upload_dir['basedir'] . '/acdc-proposals/';
    $url_base   = $upload_dir['baseurl']  . '/acdc-proposals/';
    if ( ! file_exists( $dir ) ) {
      wp_mkdir_p( $dir );
    }
    /* Supprimer l'ancien fichier HTML avant d'en créer un nouveau */
    if ( ! empty( $proposal->pdf_url ) ) {
      $old_url  = (string) $proposal->pdf_url;
      $old_path = str_replace( trailingslashit( $url_base ), trailingslashit( $dir ), $old_url );
      if ( 0 === strpos( $old_path, $dir ) && file_exists( $old_path ) ) {
        @unlink( $old_path );
      }
    }

    $filename = 'proposition-' . (int) $proposal->id . '-' . time() . '.html';
    $filepath = $dir . $filename;
    $fileurl  = $url_base . $filename;

    $company_profile = get_option( 'acdc_of_company_profile', array() );

    /* Formation */
    $formation = ! empty( $proposal->formation_id ) ? $this->get_formation( (int) $proposal->formation_id ) : null;
    if ( ! $formation ) {
      $formation = (object) array(
        'catalog_image_url' => '', 'specialty' => '', 'objectives' => '',
        'program' => '', 'prerequisites' => '', 'modality' => '',
        'description_text' => '',
      );
    }

    /* Formateurs sélectionnés */
    $trainer_ids_raw = ! empty( $proposal->trainer_ids ) ? (string) $proposal->trainer_ids : '';
    $trainer_ids     = array_filter( array_map( 'absint', explode( ',', $trainer_ids_raw ) ) );
    $trainers        = array();
    if ( ! empty( $trainer_ids ) ) {
      global $wpdb;
      $placeholders = implode( ',', array_fill( 0, count( $trainer_ids ), '%d' ) );
      $trainers = (array) $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM {$this->trainer_table} WHERE id IN ($placeholders) ORDER BY FIELD(id, $placeholders)", ...$trainer_ids, ...$trainer_ids )
      );
    }

    /* Bios surchargeables par proposition */
    $trainer_bios = array();
    if ( ! empty( $proposal->trainer_bios_json ) ) {
      $decoded = json_decode( (string) $proposal->trainer_bios_json, true );
      if ( is_array( $decoded ) ) {
        $trainer_bios = $decoded;
      }
    }

    /* CGV depuis les réglages ou texte par défaut */
    $cgv_stored = get_option( 'acdc_of_proposal_cgv', '' );
    if ( $cgv_stored ) {
      $cgv_text = wp_kses_post( $cgv_stored );
    } else {
      $cgv_text = '<p><strong>ACDC FORMATION</strong> &mdash; Organisme de formation professionnelle d&eacute;clar&eacute; sous le n&deg;&nbsp;' . esc_html( ! empty( $company_profile['nda_number'] ) ? $company_profile['nda_number'] : '93830834783' ) . ' aupr&egrave;s de la DREETS PACA.</p>'
        . '<p><strong>Article 1 &ndash; Objet</strong><br />Les pr&eacute;sentes Conditions G&eacute;n&eacute;rales de Vente (CGV) s&rsquo;appliquent &agrave; l&rsquo;ensemble des prestations de formation professionnelle dispens&eacute;es par ACDC FORMATION. Toute inscription implique l&rsquo;adh&eacute;sion sans r&eacute;serve aux pr&eacute;sentes CGV.</p>'
        . '<p><strong>Article 6 &ndash; Annulation</strong><br />Plus de 15 jours avant&nbsp;: aucun frais. Entre 15 et 7 jours&nbsp;: 50&nbsp;% du montant. Moins de 7 jours&nbsp;: facturation int&eacute;grale.</p>'
        . '<p><strong>Derni&egrave;re mise &agrave; jour&nbsp;:</strong> 18/02/2026.</p>';
    }

    /* Coordonnées ACDC */
    $logo_url         = get_option( 'acdc_of_logo_url', '' );
    if ( ! $logo_url ) {
      $logo_url = 'https://acdcformation.com/wp-content/uploads/2026/03/Logo-ACDC.png';
    }
    $logo_favicon_url = 'https://acdcformation.com/wp-content/uploads/2026/05/Favicon.png';
    $acdc = array(
      'name'         => ! empty( $company_profile['company_name'] ) ? $company_profile['company_name'] : 'ACDC Formation',
      'address'      => ! empty( $company_profile['address'] )      ? $company_profile['address']      : '7 avenue Paul C&eacute;zanne',
      'city'         => ! empty( $company_profile['city'] )         ? $company_profile['city']         : '83310 Cogolin',
      'siret'        => ! empty( $company_profile['siret'] )        ? $company_profile['siret']        : '',
      'nda'          => ! empty( $company_profile['nda_number'] )   ? $company_profile['nda_number']   : '93 83 08347 83',
      'email'        => ! empty( $company_profile['email'] )        ? $company_profile['email']        : 'dcontal@acdc-formation.com',
      'phone'        => ! empty( $company_profile['phone'] )        ? $company_profile['phone']        : '06 78 26 91 10',
      'website'      => ! empty( $company_profile['website'] )      ? $company_profile['website']      : 'https://acdc-formation.com',
      'contact_name' => ! empty( $company_profile['contact_name'] ) ? $company_profile['contact_name'] : 'David Contal',
      'logo_url'         => $logo_url,
      'logo_favicon_url' => $logo_favicon_url,
    );

    $date_prop   = wp_date( 'j F Y' );
    $total_hours = (int) $proposal->formation_days * (int) $proposal->formation_hours_per_day;
    $p           = $proposal;

    /* Capturer le template HTML */
    $client_mode = true;
    ob_start();
    include ACDC_OF_SAAS_DIR . 'includes/proposals/templates/proposal-html.php';
    $html = ob_get_clean();

    $written = @file_put_contents( $filepath, $html );
    if ( false === $written ) {
      return '';
    }
    return $fileurl;
  }

  private function generate_proposal_pdf( $proposal ) {
    $upload_dir = wp_upload_dir();
    $dir        = $upload_dir['basedir'] . '/acdc-proposals/';
    $url_base   = $upload_dir['baseurl']  . '/acdc-proposals/';
    if ( ! file_exists( $dir ) ) {
      wp_mkdir_p( $dir );
    }
    $filename = 'proposition-' . (int) $proposal->id . '-' . time() . '.pdf';
    $filepath = $dir . $filename;
    $fileurl  = $url_base . $filename;

    /* Chargement mPDF — même chemin que le kernel */
    $autoload = dirname( dirname( dirname( dirname( plugin_dir_path( __FILE__ ) ) ) ) ) . '/acdc-libs/vendor/autoload.php';
    if ( ! file_exists( $autoload ) ) {
      return '';
    }
    require_once $autoload;
    if ( ! class_exists( '\Mpdf\Mpdf' ) ) {
      return '';
    }

    /* Contexte variables — identique à generate_proposal_html() */
    $p               = $proposal;
    $company_profile = get_option( 'acdc_of_company_profile', array() );
    $formation       = ! empty( $p->formation_id ) ? $this->get_formation( (int) $p->formation_id ) : null;
    if ( ! $formation ) {
      $formation = (object) array(
        'catalog_image_url' => '', 'specialty' => '', 'objectives' => '',
        'program' => '', 'prerequisites' => '', 'modality' => '',
        'description_text' => '', 'ressources' => '', 'moyens_pedago' => '',
        'catalog_audience' => '', 'thematique' => '', 'program_file_url' => '',
        'evaluation_entree' => '', 'evaluation_sortie' => '',
      );
    }
    $trainer_ids_raw = ! empty( $p->trainer_ids ) ? (string) $p->trainer_ids : '';
    $trainer_ids     = array_filter( array_map( 'absint', explode( ',', $trainer_ids_raw ) ) );
    $trainers        = array();
    if ( ! empty( $trainer_ids ) ) {
      global $wpdb;
      $placeholders = implode( ',', array_fill( 0, count( $trainer_ids ), '%d' ) );
      $trainers = (array) $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM {$this->trainer_table} WHERE id IN ($placeholders) ORDER BY FIELD(id, $placeholders)", ...$trainer_ids, ...$trainer_ids )
      );
    }
    $trainer_bios = array();
    if ( ! empty( $p->trainer_bios_json ) ) {
      $decoded = json_decode( (string) $p->trainer_bios_json, true );
      if ( is_array( $decoded ) ) {
        $trainer_bios = $decoded;
      }
    }
    $logo_url         = get_option( 'acdc_of_logo_url', '' ) ?: 'https://acdcformation.com/wp-content/uploads/2026/03/Logo-ACDC.png';
    $logo_favicon_url = 'https://acdcformation.com/wp-content/uploads/2026/05/Favicon.png';
    $acdc = array(
      'name'             => ! empty( $company_profile['company_name'] ) ? $company_profile['company_name'] : 'ACDC Formation',
      'address'          => ! empty( $company_profile['address'] )      ? $company_profile['address']      : '7 avenue Paul Cézanne',
      'city'             => ! empty( $company_profile['city'] )         ? $company_profile['city']         : '83310 Cogolin',
      'siret'            => ! empty( $company_profile['siret'] )        ? $company_profile['siret']        : '',
      'nda'              => ! empty( $company_profile['nda_number'] )   ? $company_profile['nda_number']   : '93 83 08347 83',
      'email'            => ! empty( $company_profile['email'] )        ? $company_profile['email']        : 'dcontal@acdc-formation.com',
      'phone'            => ! empty( $company_profile['phone'] )        ? $company_profile['phone']        : '06 78 26 91 10',
      'website'          => ! empty( $company_profile['website'] )      ? $company_profile['website']      : 'https://acdc-formation.com',
      'contact_name'     => ! empty( $company_profile['contact_name'] ) ? $company_profile['contact_name'] : 'David Contal',
      'logo_url'         => $logo_url,
      'logo_favicon_url' => $logo_favicon_url,
    );
    $date_prop   = wp_date( 'j F Y' );
    $total_hours = (int) $p->formation_days * (int) $p->formation_hours_per_day;
    $prog_days   = max( 1, min( 10, (int) $p->formation_days ) );

    /* Générer le HTML via proposal-mpdf.php */
    ob_start();
    include ACDC_OF_SAAS_DIR . 'includes/proposals/templates/proposal-mpdf.php';
    $html = ob_get_clean();

    /* Instancier mPDF — marges zéro, format A4 */
    $mpdf = new Mpdf\Mpdf( array(
      'format'        => 'A4',
      'margin_top'    => 0,
      'margin_bottom' => 0,
      'margin_left'   => 0,
      'margin_right'  => 0,
      'dpi'           => 72,
      'img_dpi'       => 96,
      'tempDir'       => sys_get_temp_dir(),
    ) );
    $mpdf->SetTitle( 'Proposition — ' . ( $p->formation_title ?: 'Formation' ) );
    $mpdf->WriteHTML( $html );
    $mpdf->Output( $filepath, 'F' );

    if ( ! file_exists( $filepath ) ) {
      return '';
    }
    return $fileurl;
  }

  /**
   * Construit le PDF en memoire (identique a render_simple_pdf mais retourne string).
   * Copie du moteur kernel sans headers ni exit — specifique aux propositions.
   */
  private function proposal_build_pdf_string( $pages ) {
    $objects = array();

    $add_object = static function ( $content ) use ( &$objects ) {
      $objects[] = $content;
      return count( $objects );
    };

    $font_regular_id = $add_object( '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>' );
    $font_bold_id    = $add_object( '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>' );

    /* --- Pré-scan images --- */
    $image_map = array();
    foreach ( $pages as $page_lines ) {
      foreach ( $page_lines as $line ) {
        if ( isset( $line['type'] ) && 'image' === $line['type']
          && ! empty( $line['image_key'] ) && ! isset( $image_map[ $line['image_key'] ] )
          && ! empty( $line['image_data'] ) ) {
          $img_obj = '<< /Type /XObject /Subtype /Image'
            . ' /Width ' . (int) $line['image_width']
            . ' /Height ' . (int) $line['image_height']
            . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode'
            . ' /Length ' . strlen( $line['image_data'] ) . " >>
stream
"
            . $line['image_data'] . "
endstream";
          $image_map[ $line['image_key'] ] = array(
            'name'      => 'Im' . ( count( $image_map ) + 1 ),
            'object_id' => $add_object( $img_obj ),
          );
        }
      }
    }

    $content_ids = array();
    $page_resource_xobjects = array();
    $page_sizes = array();

    foreach ( $pages as $page_lines ) {
      $stream     = '';
      $used_images = array();
      $page_width  = 595;
      $page_height = 842;

      foreach ( $page_lines as $line ) {
        if ( ! isset( $line['type'] ) ) {
          continue;
        }
        if ( 'page_meta' === $line['type'] ) {
          if ( ! empty( $line['width'] ) )  { $page_width  = (float) $line['width']; }
          if ( ! empty( $line['height'] ) ) { $page_height = (float) $line['height']; }
          continue;
        }
        if ( 'image' === $line['type'] ) {
          if ( empty( $line['image_key'] ) || empty( $image_map[ $line['image_key'] ] ) ) {
            continue;
          }
          $img_name = $image_map[ $line['image_key'] ]['name'];
          $used_images[ $img_name ] = $image_map[ $line['image_key'] ]['object_id'];
          $stream .= sprintf( "q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q
",
            (float) $line['display_width'], (float) $line['display_height'],
            (float) $line['x'], (float) $line['y'], $img_name );
          continue;
        }
        if ( 'rect' === $line['type'] ) {
          $fill   = isset( $line['fill_color'] )   ? $this->proposal_hex_to_rgb( $line['fill_color'] )   : null;
          $stroke = isset( $line['stroke_color'] ) ? $this->proposal_hex_to_rgb( $line['stroke_color'] ) : null;
          $stream .= "q
";
          if ( $fill )   { $stream .= sprintf( "%.4f %.4f %.4f rg
", $fill[0],   $fill[1],   $fill[2] ); }
          if ( $stroke ) { $stream .= sprintf( "%.4f %.4f %.4f RG
", $stroke[0], $stroke[1], $stroke[2] ); }
          $stream .= sprintf( "%.2f w
", isset( $line['line_width'] ) ? (float) $line['line_width'] : 1 );
          $stream .= sprintf( "%.2f %.2f %.2f %.2f re
", (float) $line['x'], (float) $line['y'], (float) $line['width'], (float) $line['height'] );
          $stream .= ( $fill && $stroke ) ? "B
" : ( $fill ? "f
" : "S
" );
          $stream .= "Q
";
          continue;
        }
        if ( 'text' === $line['type'] ) {
          $font_id   = ( isset( $line['font'] ) && 'bold' === $line['font'] ) ? $font_bold_id : $font_regular_id;
          $color     = isset( $line['color'] ) ? $this->proposal_hex_to_rgb( $line['color'] ) : array( 0, 0, 0 );
          $raw_text  = isset( $line['text'] ) ? (string) $line['text'] : '';
          $font_name = ( $font_id === $font_bold_id ) ? 'Helvetica-Bold' : 'Helvetica';
          $size      = isset( $line['size'] ) ? (float) $line['size'] : 11;
          $x         = (float) $line['x'];
          $y         = (float) $line['y'];
          /* Alignement centre / droite */
          if ( ! empty( $line['align'] ) && 'center' === $line['align'] ) {
            $char_w = $size * 0.5;
            $x = $x - ( strlen( $raw_text ) * $char_w / 2 );
          } elseif ( ! empty( $line['align'] ) && 'right' === $line['align'] ) {
            $char_w = $size * 0.5;
            $x = $x - ( strlen( $raw_text ) * $char_w );
          }
          $stream .= "BT
";
          $stream .= sprintf( "%.4f %.4f %.4f rg
", $color[0], $color[1], $color[2] );
          $stream .= sprintf( "/F%d %.2f Tf
", $font_id, $size );
          $stream .= sprintf( "1 0 0 1 %.2f %.2f Tm
", $x, $y );
          $stream .= '(' . $this->proposal_pdf_encode( $raw_text ) . ") Tj
";
          $stream .= "ET
";
          continue;
        }
      }

      $content_ids[]            = $add_object( "<< /Length " . strlen( $stream ) . " >>
stream
" . $stream . "
endstream" );
      $page_resource_xobjects[] = $used_images;
      $page_sizes[]             = array( 'width' => $page_width, 'height' => $page_height );
    }

    $page_ids = array();
    foreach ( $content_ids as $index => $content_id ) {
      $page_width  = ! empty( $page_sizes[ $index ]['width'] )  ? (float) $page_sizes[ $index ]['width']  : 595;
      $page_height = ! empty( $page_sizes[ $index ]['height'] ) ? (float) $page_sizes[ $index ]['height'] : 842;
      $page_ids[]  = $add_object(
        "<< /Type /Page /Parent PAGES_ROOT /MediaBox [0 0 {$page_width} {$page_height}]"
        . " /Resources << /Font << /F{$font_regular_id} {$font_regular_id} 0 R /F{$font_bold_id} {$font_bold_id} 0 R >> >>"
        . " /Contents {$content_id} 0 R >>"
      );
    }

    $kids = array();
    foreach ( $page_ids as $pid ) { $kids[] = $pid . ' 0 R'; }
    $pages_root_id = $add_object( '<< /Type /Pages /Count ' . count( $page_ids ) . ' /Kids [ ' . implode( ' ', $kids ) . ' ] >>' );

    foreach ( $page_ids as $index => $pid ) {
      $objects[ $pid - 1 ] = str_replace( 'PAGES_ROOT', $pages_root_id . ' 0 R', $objects[ $pid - 1 ] );
    }

    $catalog_id = $add_object( "<< /Type /Catalog /Pages {$pages_root_id} 0 R >>" );

    $pdf      = "%PDF-1.4
";
    $offsets  = array( 0 );
    foreach ( $objects as $i => $object ) {
      $offsets[] = strlen( $pdf );
      $pdf      .= ( $i + 1 ) . " 0 obj
" . $object . "
endobj
";
    }
    $xref_pos = strlen( $pdf );
    $pdf .= "xref
0 " . ( count( $objects ) + 1 ) . "
";
    $pdf .= "0000000000 65535 f 
";
    for ( $i = 1; $i <= count( $objects ); $i++ ) {
      $pdf .= sprintf( "%010d 00000 n 
", $offsets[ $i ] );
    }
    $pdf .= "trailer
<< /Size " . ( count( $objects ) + 1 ) . " /Root {$catalog_id} 0 R >>
startxref
{$xref_pos}
%%EOF";
    return $pdf;
  }

  private function proposal_hex_to_rgb( $hex ) {
    $hex = ltrim( (string) $hex, '#' );
    if ( strlen( $hex ) === 3 ) {
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if ( strlen( $hex ) !== 6 ) { return array( 0, 0, 0 ); }
    return array(
      hexdec( substr( $hex, 0, 2 ) ) / 255,
      hexdec( substr( $hex, 2, 2 ) ) / 255,
      hexdec( substr( $hex, 4, 2 ) ) / 255,
    );
  }

  private function proposal_pdf_encode( $text ) {
    $out = '';
    for ( $i = 0; $i < strlen( $text ); $i++ ) {
      $c = $text[ $i ];
      if ( '(' === $c || ')' === $c || '\\' === $c ) {
        $out .= '\\' . $c;
      } elseif ( ord( $c ) > 127 ) {
        $out .= '\\' . sprintf( '%03o', ord( $c ) );
      } else {
        $out .= $c;
      }
    }
    return $out;
  }

  /* ---- Pages PDF ---- */

  private function proposal_pdf_page_cover( $proposal ) {
    $title    = $proposal->formation_title ?: 'Formation professionnelle';
    $client   = $proposal->client_name ?: '';
    $ctitle   = $proposal->client_title ?: '';
    $company  = $proposal->client_company ?: '';
    $date     = wp_date( 'j F Y' );
    $w = 595; $h = 842;
    $lines = array(
      array( 'type' => 'page_meta', 'width' => $w, 'height' => $h ),
      // Bande dorée haut
      array( 'type' => 'rect', 'x' => 0, 'y' => $h - 120, 'width' => $w * 0.45, 'height' => 120, 'fill_color' => '#C5A253' ),
      // Titre formation dans bande
      array( 'type' => 'text', 'x' => 28, 'y' => $h - 90, 'text' => $this->proposal_pdf_clean( $title ), 'font' => 'bold', 'size' => 18, 'color' => '#ffffff' ),
      // Sous-bande "PROPOSITION COMMERCIALE"
      array( 'type' => 'rect', 'x' => 0, 'y' => $h - 280, 'width' => $w, 'height' => 40, 'fill_color' => '#C5A253' ),
      array( 'type' => 'text', 'x' => $w / 2, 'y' => $h - 265, 'text' => 'PROPOSITION COMMERCIALE', 'font' => 'bold', 'size' => 14, 'color' => '#ffffff', 'align' => 'center' ),
      // Nom client
      array( 'type' => 'text', 'x' => $w / 2, 'y' => $h - 350, 'text' => $company ?: 'A votre attention', 'font' => 'bold', 'size' => 16, 'color' => '#0f2c52', 'align' => 'center' ),
      array( 'type' => 'text', 'x' => $w / 2, 'y' => $h - 372, 'text' => 'A l\'attention de :', 'font' => 'regular', 'size' => 12, 'color' => '#4b5d76', 'align' => 'center' ),
      array( 'type' => 'text', 'x' => $w / 2, 'y' => $h - 393, 'text' => $this->proposal_pdf_clean( $client ), 'font' => 'bold', 'size' => 13, 'color' => '#0f2c52', 'align' => 'center' ),
      array( 'type' => 'text', 'x' => $w / 2, 'y' => $h - 410, 'text' => $this->proposal_pdf_clean( $ctitle ), 'font' => 'regular', 'size' => 12, 'color' => '#4b5d76', 'align' => 'center' ),
      // Bande gold bas
      array( 'type' => 'rect', 'x' => 0, 'y' => 0, 'width' => $w, 'height' => 80, 'fill_color' => '#C5A253' ),
      array( 'type' => 'text', 'x' => 28, 'y' => 62, 'text' => 'Date : ' . $date, 'font' => 'regular', 'size' => 11, 'color' => '#ffffff' ),
      array( 'type' => 'text', 'x' => 28, 'y' => 47, 'text' => 'ACDC Formation  |  dcontal@acdc-formation.com  |  06 78 26 91 10', 'font' => 'regular', 'size' => 10, 'color' => '#ffffff' ),
    );
    return $lines;
  }

  private function proposal_pdf_page_about( $proposal, $acdc_name ) {
    $w = 595; $h = 842;
    $about = $proposal->client_about_text ?: 'Description non renseignee.';
    $company = $proposal->client_company ?: '';
    $siret   = $proposal->client_siret ?: '';
    $addr    = $proposal->client_address ?: '';
    $lines = array(
      array( 'type' => 'page_meta', 'width' => $w, 'height' => $h ),
      // En-tête section
      array( 'type' => 'rect', 'x' => 0, 'y' => $h - 60, 'width' => $w, 'height' => 60, 'fill_color' => '#C5A253' ),
      array( 'type' => 'text', 'x' => 28, 'y' => $h - 28, 'text' => 'A PROPOS DE VOUS', 'font' => 'bold', 'size' => 14, 'color' => '#ffffff' ),
      // Sous-titre
      array( 'type' => 'text', 'x' => $w / 2, 'y' => $h - 90, 'text' => $this->proposal_pdf_clean( $company ), 'font' => 'bold', 'size' => 16, 'color' => '#0f2c52', 'align' => 'center' ),
    );
    // Texte about
    $about_lines = $this->proposal_pdf_wrap_text( $this->proposal_pdf_clean( $about ), 60 );
    $y = $h - 130;
    foreach ( $about_lines as $tl ) {
      $lines[] = array( 'type' => 'text', 'x' => 42, 'y' => $y, 'text' => $tl, 'font' => 'regular', 'size' => 11, 'color' => '#4b5d76' );
      $y -= 18;
    }
    // Infos admin
    $y -= 20;
    $lines[] = array( 'type' => 'text', 'x' => 42, 'y' => $y, 'text' => 'Informations administratives :', 'font' => 'bold', 'size' => 11, 'color' => '#0f2c52' );
    $y -= 18;
    if ( $siret ) {
      $lines[] = array( 'type' => 'text', 'x' => 42, 'y' => $y, 'text' => 'SIRET : ' . $siret, 'font' => 'regular', 'size' => 11, 'color' => '#4b5d76' );
      $y -= 18;
    }
    if ( $addr ) {
      $lines[] = array( 'type' => 'text', 'x' => 42, 'y' => $y, 'text' => 'Siege : ' . $this->proposal_pdf_clean( $addr ), 'font' => 'regular', 'size' => 11, 'color' => '#4b5d76' );
    }
    // Footer
    $lines[] = array( 'type' => 'text', 'x' => $w - 28, 'y' => 20, 'text' => '2', 'font' => 'regular', 'size' => 10, 'color' => '#C5A253', 'align' => 'right' );
    return $lines;
  }

  private function proposal_pdf_page_project( $proposal ) {
    $w = 595; $h = 842;
    $days    = (int) $proposal->formation_days;
    $hours   = (int) $proposal->formation_hours_per_day;
    $total_h = $days * $hours;
    $learners = (int) $proposal->formation_learners_count;
    $funding  = ! empty( $proposal->formation_funding ) ? (string) $proposal->formation_funding : 'Autofinancement / non renseigné';
    $dates    = $this->acdc_format_seances_list( $proposal->formation_dates ?: '', 'inline' ) ?: 'A confirmer';
    $location = $proposal->formation_location ?: '';
    $lines = array(
      array( 'type' => 'page_meta', 'width' => $w, 'height' => $h ),
      array( 'type' => 'rect', 'x' => 0, 'y' => $h - 60, 'width' => $w, 'height' => 60, 'fill_color' => '#C5A253' ),
      array( 'type' => 'text', 'x' => 28, 'y' => $h - 28, 'text' => 'VOTRE PROJET & VOS BESOINS', 'font' => 'bold', 'size' => 14, 'color' => '#ffffff' ),
      // Tableau organisation
      array( 'type' => 'rect', 'x' => 42, 'y' => $h - 180, 'width' => $w - 84, 'height' => 100, 'fill_color' => '#fbf2e3' ),
      array( 'type' => 'text', 'x' => 56, 'y' => $h - 110, 'text' => 'Duree', 'font' => 'bold', 'size' => 11, 'color' => '#0f2c52' ),
      array( 'type' => 'text', 'x' => 200, 'y' => $h - 110, 'text' => $days . ' jour(s) de ' . $hours . 'h (soit ' . $total_h . 'h)', 'font' => 'regular', 'size' => 11, 'color' => '#4b5d76' ),
      array( 'type' => 'text', 'x' => 56, 'y' => $h - 132, 'text' => 'Effectifs', 'font' => 'bold', 'size' => 11, 'color' => '#0f2c52' ),
      array( 'type' => 'text', 'x' => 200, 'y' => $h - 132, 'text' => 'Groupe : ' . $learners . ' apprenant(s)', 'font' => 'regular', 'size' => 11, 'color' => '#4b5d76' ),
      array( 'type' => 'text', 'x' => 56, 'y' => $h - 154, 'text' => 'Financement', 'font' => 'bold', 'size' => 11, 'color' => '#0f2c52' ),
      array( 'type' => 'text', 'x' => 200, 'y' => $h - 154, 'text' => $this->proposal_pdf_clean( $funding ), 'font' => 'regular', 'size' => 11, 'color' => '#4b5d76' ),
    );
    // Dates
    $y = $h - 210;
    $lines[] = array( 'type' => 'text', 'x' => 42, 'y' => $y, 'text' => 'Dates prevues :', 'font' => 'bold', 'size' => 11, 'color' => '#0f2c52' );
    $lines[] = array( 'type' => 'text', 'x' => 180, 'y' => $y, 'text' => $this->proposal_pdf_clean( $dates ), 'font' => 'regular', 'size' => 11, 'color' => '#4b5d76' );
    if ( $location ) {
      $y -= 20;
      $lines[] = array( 'type' => 'text', 'x' => 42, 'y' => $y, 'text' => 'Lieu :', 'font' => 'bold', 'size' => 11, 'color' => '#0f2c52' );
      $lines[] = array( 'type' => 'text', 'x' => 180, 'y' => $y, 'text' => $this->proposal_pdf_clean( $location ), 'font' => 'regular', 'size' => 11, 'color' => '#4b5d76' );
    }
    $lines[] = array( 'type' => 'text', 'x' => $w - 28, 'y' => 20, 'text' => '3', 'font' => 'regular', 'size' => 10, 'color' => '#C5A253', 'align' => 'right' );
    return $lines;
  }

  private function proposal_pdf_page_financial( $proposal, $acdc_name, $acdc_nda ) {
    $w = 595; $h = 842;
    $days      = (int) $proposal->formation_days;
    $price_day = number_format( (float) $proposal->formation_price_per_day, 2, ',', ' ' );
    $total     = number_format( (float) $proposal->formation_total, 2, ',', ' ' );
    $ftitle    = $proposal->formation_title ?: 'Formation';
    $dates     = $this->acdc_format_seances_list( $proposal->formation_dates ?: '', 'inline' ) ?: 'A confirmer';
    $location  = $proposal->formation_location ?: '';
    $date_prop = wp_date( 'j F Y' );
    $lines = array(
      array( 'type' => 'page_meta', 'width' => $w, 'height' => $h ),
      array( 'type' => 'rect', 'x' => 0, 'y' => $h - 60, 'width' => $w, 'height' => 60, 'fill_color' => '#C5A253' ),
      array( 'type' => 'text', 'x' => 28, 'y' => $h - 28, 'text' => 'PROPOSITION FINANCIERE', 'font' => 'bold', 'size' => 14, 'color' => '#ffffff' ),
      array( 'type' => 'text', 'x' => 42, 'y' => $h - 90, 'text' => 'Date de la proposition : ' . $date_prop, 'font' => 'bold', 'size' => 12, 'color' => '#0f2c52' ),
      // En-têtes tableau
      array( 'type' => 'rect', 'x' => 42, 'y' => $h - 170, 'width' => $w - 84, 'height' => 28, 'fill_color' => '#C5A253' ),
      array( 'type' => 'text', 'x' => 56, 'y' => $h - 153, 'text' => 'Designation', 'font' => 'bold', 'size' => 10, 'color' => '#ffffff' ),
      array( 'type' => 'text', 'x' => 340, 'y' => $h - 153, 'text' => 'Prix/jour', 'font' => 'bold', 'size' => 10, 'color' => '#ffffff' ),
      array( 'type' => 'text', 'x' => 420, 'y' => $h - 153, 'text' => 'Qte', 'font' => 'bold', 'size' => 10, 'color' => '#ffffff' ),
      array( 'type' => 'text', 'x' => $w - 56, 'y' => $h - 153, 'text' => 'Total HT', 'font' => 'bold', 'size' => 10, 'color' => '#ffffff', 'align' => 'right' ),
      // Ligne formation
      array( 'type' => 'rect', 'x' => 42, 'y' => $h - 200, 'width' => $w - 84, 'height' => 30, 'fill_color' => '#fbf8f7' ),
      array( 'type' => 'text', 'x' => 56, 'y' => $h - 181, 'text' => $this->proposal_pdf_clean( $ftitle ), 'font' => 'regular', 'size' => 10, 'color' => '#0f2c52' ),
      array( 'type' => 'text', 'x' => 340, 'y' => $h - 181, 'text' => $price_day . ' EUR', 'font' => 'bold', 'size' => 10, 'color' => '#0f2c52' ),
      array( 'type' => 'text', 'x' => 420, 'y' => $h - 181, 'text' => (string) $days, 'font' => 'regular', 'size' => 10, 'color' => '#0f2c52' ),
      array( 'type' => 'text', 'x' => $w - 56, 'y' => $h - 181, 'text' => $total . ' EUR', 'font' => 'bold', 'size' => 10, 'color' => '#0f2c52', 'align' => 'right' ),
      // Ligne offert
      array( 'type' => 'text', 'x' => 56, 'y' => $h - 211, 'text' => 'Ressources complementaires', 'font' => 'regular', 'size' => 10, 'color' => '#4b5d76' ),
      array( 'type' => 'text', 'x' => 340, 'y' => $h - 211, 'text' => 'Offertes', 'font' => 'bold', 'size' => 10, 'color' => '#35b37e' ),
      array( 'type' => 'text', 'x' => 56, 'y' => $h - 231, 'text' => 'Frais de deplacement et hebergement', 'font' => 'regular', 'size' => 10, 'color' => '#4b5d76' ),
      array( 'type' => 'text', 'x' => 340, 'y' => $h - 231, 'text' => 'Offerts', 'font' => 'bold', 'size' => 10, 'color' => '#35b37e' ),
      // Total
      array( 'type' => 'rect', 'x' => 42, 'y' => $h - 272, 'width' => $w - 84, 'height' => 28, 'fill_color' => '#fbf2e3' ),
      array( 'type' => 'text', 'x' => 56, 'y' => $h - 254, 'text' => 'TOTAL DE LA PROPOSITION', 'font' => 'bold', 'size' => 11, 'color' => '#0f2c52' ),
      array( 'type' => 'text', 'x' => $w - 56, 'y' => $h - 254, 'text' => $total . ' EUR net de TVA', 'font' => 'bold', 'size' => 11, 'color' => '#C5A253', 'align' => 'right' ),
      // Mentions
      array( 'type' => 'text', 'x' => 42, 'y' => $h - 295, 'text' => 'TVA non applicable - article 293 B du CGI', 'font' => 'regular', 'size' => 9, 'color' => '#4b5d76' ),
      array( 'type' => 'text', 'x' => 42, 'y' => $h - 310, 'text' => 'Declaration sous le n deg ' . $acdc_nda . ' aupres du prefet de region PACA.', 'font' => 'regular', 'size' => 9, 'color' => '#4b5d76' ),
      array( 'type' => 'text', 'x' => $w / 2, 'y' => $h - 335, 'text' => 'PROPOSITION VALABLE 3 MOIS', 'font' => 'bold', 'size' => 11, 'color' => '#C5A253', 'align' => 'center' ),
    );
    $lines[] = array( 'type' => 'text', 'x' => $w - 28, 'y' => 20, 'text' => '4', 'font' => 'regular', 'size' => 10, 'color' => '#C5A253', 'align' => 'right' );
    return $lines;
  }

  private function proposal_pdf_page_contact( $proposal, $acdc_name, $acdc_address, $acdc_email, $acdc_phone, $acdc_siret, $acdc_nda ) {
    $w = 595; $h = 842;
    $lines = array(
      array( 'type' => 'page_meta', 'width' => $w, 'height' => $h ),
      array( 'type' => 'rect', 'x' => 0, 'y' => $h - 60, 'width' => $w, 'height' => 60, 'fill_color' => '#C5A253' ),
      array( 'type' => 'text', 'x' => 28, 'y' => $h - 28, 'text' => 'NOUS CONTACTER', 'font' => 'bold', 'size' => 14, 'color' => '#ffffff' ),
      array( 'type' => 'text', 'x' => 42, 'y' => $h - 100, 'text' => 'A votre disposition pour plus d\'informations :', 'font' => 'bold', 'size' => 13, 'color' => '#C5A253' ),
      array( 'type' => 'text', 'x' => 42, 'y' => $h - 130, 'text' => 'David Contal', 'font' => 'bold', 'size' => 13, 'color' => '#0f2c52' ),
      array( 'type' => 'text', 'x' => 42, 'y' => $h - 152, 'text' => 'Email : ' . $acdc_email, 'font' => 'regular', 'size' => 11, 'color' => '#4b5d76' ),
      array( 'type' => 'text', 'x' => 42, 'y' => $h - 170, 'text' => 'Tel : ' . $acdc_phone, 'font' => 'regular', 'size' => 11, 'color' => '#4b5d76' ),
      array( 'type' => 'text', 'x' => 42, 'y' => $h - 188, 'text' => 'https://acdc-formation.com', 'font' => 'regular', 'size' => 11, 'color' => '#C5A253' ),
      array( 'type' => 'text', 'x' => 42, 'y' => $h - 220, 'text' => $acdc_name, 'font' => 'bold', 'size' => 12, 'color' => '#0f2c52' ),
      array( 'type' => 'text', 'x' => 42, 'y' => $h - 240, 'text' => $this->proposal_pdf_clean( $acdc_address ), 'font' => 'regular', 'size' => 11, 'color' => '#4b5d76' ),
      array( 'type' => 'text', 'x' => 42, 'y' => $h - 258, 'text' => 'SIRET : ' . $acdc_siret, 'font' => 'regular', 'size' => 10, 'color' => '#4b5d76' ),
      array( 'type' => 'text', 'x' => 42, 'y' => $h - 272, 'text' => 'Declaration n deg ' . $acdc_nda . ' aupres du prefet PACA.', 'font' => 'regular', 'size' => 9, 'color' => '#4b5d76' ),
    );
    $lines[] = array( 'type' => 'text', 'x' => $w - 28, 'y' => 20, 'text' => '5', 'font' => 'regular', 'size' => 10, 'color' => '#C5A253', 'align' => 'right' );
    return $lines;
  }

  /* ---- Helpers PDF ---- */
  private function proposal_pdf_clean( $str ) {
    return iconv( 'UTF-8', 'windows-1252//TRANSLIT//IGNORE', html_entity_decode( (string) $str, ENT_QUOTES, 'UTF-8' ) );
  }
  private function proposal_pdf_wrap_text( $text, $max_chars = 80 ) {
    $words  = explode( ' ', $text );
    $lines  = array();
    $current = '';
    foreach ( $words as $word ) {
      if ( strlen( $current . ' ' . $word ) > $max_chars && '' !== $current ) {
        $lines[]  = trim( $current );
        $current  = $word;
      } else {
        $current = ltrim( $current . ' ' . $word );
      }
    }
    if ( '' !== $current ) {
      $lines[] = trim( $current );
    }
    return $lines;
  }

  /* ---------------------------------------------------------------
   * Liste des propositions sur la fiche NAD
   * --------------------------------------------------------------- */
  private function render_proposals_list_for_need( $need_id ) {
    $proposals = $this->get_proposals( array( 'need_id' => $need_id ) );
    if ( empty( $proposals ) ) {
      return;
    }
    $statuses = $this->get_proposal_status_labels();
    $nonce    = wp_create_nonce( 'acdc_proposal_nonce' );
    $ajax_url = admin_url( 'admin-ajax.php' );
    ?>
    <div class="acdc-panel acdc-mb-18" style="margin-top:18px;">
      <div class="acdc-panel-heading" style="padding:12px 16px 8px;border-bottom:1px solid #f0e6dc;">
        <h3 style="font-size:13px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;margin:0;">
          Propositions commerciales li&#233;es
        </h3>
      </div>
      <div class="acdc-table-wrap" style="overflow:hidden">
        <table class="acdc-table" style="font-size:13px;">
          <thead>
            <tr>
              <th>Formation</th>
              <th>Total</th>
              <th>Statut</th>
              <th>Cr&#233;&#233; le</th>
              <th><span class="screen-reader-text">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $proposals as $p ) :
              $status_label = isset( $statuses[ $p->status ] ) ? $statuses[ $p->status ] : ucfirst( $p->status );
              $del_url = wp_nonce_url(
                is_admin()
                  ? admin_url( 'admin-post.php?action=acdc_delete_proposal&proposal_id=' . $p->id )
                  : $this->portal_page_url( array( 'trf_action' => 'delete_proposal', 'proposal_id' => (int) $p->id ) ),
                'acdc_delete_proposal_' . $p->id
              );
              $has_pdf = ! empty( $p->pdf_url );
              $prop_json = esc_attr( wp_json_encode( array(
                'id'                       => (int) $p->id,
                'formation_title'          => (string) $p->formation_title,
                'formation_days'           => (int) $p->formation_days,
                'formation_hours_per_day'  => (int) $p->formation_hours_per_day,
                'formation_price_per_day'  => (float) $p->formation_price_per_day,
                'formation_total'          => (float) $p->formation_total,
                'formation_learners_count' => (int) $p->formation_learners_count,
                'formation_funding'        => (string) $p->formation_funding,
                'formation_dates'          => $this->acdc_format_seances_list( (string) $p->formation_dates, 'list' ),
                'formation_location'       => (string) $p->formation_location,
                'client_name'              => (string) $p->client_name,
                'client_title'             => (string) $p->client_title,
                'client_company'           => (string) $p->client_company,
                'client_siret'             => (string) $p->client_siret,
                'client_website'           => (string) $p->client_website,
                'client_activity'          => (string) $p->client_activity,
                'client_about_text'        => (string) $p->client_about_text,
                'client_email'             => (string) $p->client_email,
              ) ) );
            ?>
            <tr>
              <td>
                <strong><?php echo esc_html( $p->formation_title ?: '—' ); ?></strong>
                <div style="font-size:11px;color:#4b5d76;margin-top:2px;">
                  <?php echo esc_html( $p->formation_days ); ?> j &#183;
                  <?php echo number_format( (float) $p->formation_price_per_day, 0, ',', ' ' ); ?>&#160;&#8364;/j
                </div>
              </td>
              <td><strong><?php echo $p->formation_total ? number_format( (float) $p->formation_total, 0, ',', '&#160;' ) . '&#160;&#8364;' : '—'; ?></strong></td>
              <td><span class="acdc-prop-badge acdc-prop-badge--<?php echo esc_attr( $p->status ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
              <td style="font-size:12px;color:#4b5d76;"><?php echo mysql2date( 'd/m/Y', $p->created_at ); ?></td>
              <td class="acdc-actions-cell-icons">
                <div class="acdc-groups-actions-inline">

                  <?php if ( $has_pdf ) : ?>
                    <?php /* PDF prêt : télécharger */ ?>
                    <a class="acdc-row-action-icon"
                       href="<?php echo esc_url( $p->pdf_url ); ?>"
                       target="_blank"
                       title="T&#233;l&#233;charger le PDF"
                       data-acdc-iconized="1">
                      <?php echo $this->render_inline_icon( 'download', 25 ); ?>
                      <span class="acdc-action-hub-sr screen-reader-text">T&#233;l&#233;charger</span>
                    </a>
                  <?php else : ?>
                    <?php /* Brouillon sans PDF : régénérer */ ?>
                    <a class="acdc-row-action-icon"
                       href="#"
                       title="G&#233;n&#233;rer le PDF"
                       data-acdc-iconized="1"
                       onclick="acdcPropRegenPdf(<?php echo (int) $p->id; ?>, this, '<?php echo esc_js( $nonce ); ?>', '<?php echo esc_url( $ajax_url ); ?>'); return false;">
                      <?php echo $this->render_inline_icon( 'file-text', 25 ); ?>
                      <span class="acdc-action-hub-sr screen-reader-text">G&#233;n&#233;rer le PDF</span>
                    </a>
                  <?php endif; ?>

                  <?php /* Modifier : pleine page (formulaire complet) */ ?>
                  <?php $edit_url_need = is_admin()
                    ? $this->admin_tab_url( 'proposals', array( 'action' => 'edit', 'item_id' => (int) $p->id ) )
                    : $this->portal_page_url( array( 'tab' => 'proposals', 'action' => 'edit', 'item_id' => (int) $p->id ) ); ?>
                  <a class="acdc-row-action-icon"
                     href="<?php echo esc_url( $edit_url_need ); ?>"
                     title="Modifier"
                     data-acdc-iconized="1">
                    <?php echo $this->render_inline_icon( 'edit', 25 ); ?>
                    <span class="acdc-action-hub-sr screen-reader-text">Modifier</span>
                  </a>

                  <?php /* Supprimer */ ?>
                  <a class="acdc-row-action-icon acdc-row-delete-link"
                     href="<?php echo esc_url( $del_url ); ?>"
                     title="Supprimer"
                     onclick="return confirm('Supprimer cette proposition ?');"
                     data-acdc-iconized="1">
                    <?php echo $this->render_inline_icon( 'trash', 25 ); ?>
                    <span class="acdc-action-hub-sr screen-reader-text">Supprimer</span>
                  </a>

                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <script>
    <?php if ( ! isset( $GLOBALS['_acdc_prop_regen_pdf_printed'] ) ) : $GLOBALS['_acdc_prop_regen_pdf_printed'] = true; ?>

    <?php endif; ?>
    </script>
    <?php
  }

  /* ---------------------------------------------------------------
   * Rendu principal de l'onglet "Propositions commerciales"
   * hotfix7 — onglet autonome dans la sidebar CRM
   * --------------------------------------------------------------- */
  private function render_proposals_tab( $tab, $action, $item_id ) {
    $nonce    = wp_create_nonce( 'acdc_proposal_nonce' );
    $ajax_url = admin_url( 'admin-ajax.php' );

    if ( 'edit' === $action || 'new' === $action ) {
      $proposal = null;
      if ( 'edit' === $action && $item_id ) {
        $proposal = $this->get_proposal( (int) $item_id );
      }
      $this->render_proposal_full_form( $proposal, $nonce, $ajax_url );
      return;
    }

    if ( 'view' === $action && $item_id ) {
      $proposal = $this->get_proposal( (int) $item_id );
      if ( $proposal ) {
        $this->render_proposal_detail( $proposal, $nonce, $ajax_url );
        return;
      }
    }

    /* Défaut : liste globale */
    $this->render_proposals_global_list( $nonce, $ajax_url );
  }

  /* ---------------------------------------------------------------
   * Liste globale des propositions
   * --------------------------------------------------------------- */
  private function render_proposals_global_list( $nonce, $ajax_url ) {
    $proposals = $this->get_proposals_all();
    $statuses  = $this->get_proposal_status_labels();
    $new_url   = $this->portal_page_url( array( 'tab' => 'proposals', 'action' => 'new' ) );

    /* Couleurs badges statut */
    $prop_status_colors = array(
      'brouillon' => array( '#f0f4ff', '#1e4777' ),
      'envoyee'   => array( '#fff7ed', '#c2610c' ),
      'acceptee'  => array( '#ecfdf5', '#059669' ),
      'refusee'   => array( '#fef2f2', '#dc2626' ),
      'expiree'   => array( '#f3f4f6', '#6b7280' ),
    );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Propositions commerciales</h2>
        <p>Centralisation de toutes vos propositions &#8212; brouillons et envoy&#233;es.</p>
      </div>
      <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $new_url ); ?>">+ Cr&#233;er une proposition</a>
    </section>

    <!-- Barre de filtres -->
    <div class="acdc-panel acdc-mb-18">
      <div class="acdc-proposals-filters-row">
        <input type="search" id="acdc-proposals-search" placeholder="Rechercher (formation, client, contact, email…)">
        <select id="acdc-proposals-filter-status">
          <option value="">Tous les statuts</option>
          <?php foreach ( $statuses as $k => $lbl ) : ?>
            <option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( html_entity_decode( $lbl ) ); ?></option>
          <?php endforeach; ?>
        </select>
        <span id="acdc-proposals-count" style="font-size:12px;color:#4b5d76;"></span>
      </div>
    </div>

    <div class="acdc-panel">
      <div class="acdc-table-wrap">
        <?php if ( empty( $proposals ) ) : ?>
          <div style="padding:44px 20px;text-align:center;color:#1E4777;">
            Aucune proposition commerciale pour le moment.
            <br><br>
            <a href="<?php echo esc_url( $new_url ); ?>" class="acdc-button acdc-button-primary">Cr&#233;er la premi&#232;re proposition</a>
          </div>
        <?php else : ?>
        <table class="acdc-table" id="acdc-proposals-list-table">
          <thead>
            <tr>
              <th>Client</th>
              <th>Formation</th>
              <th>Total HT</th>
              <th>Statut</th>
              <th>Cr&#233;&#233; le</th>
              <th>Dernier envoi</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $proposals as $p ) :
              $status_label = isset( $statuses[ $p->status ] ) ? html_entity_decode( $statuses[ $p->status ] ) : ucfirst( $p->status );
              $s_colors     = isset( $prop_status_colors[ $p->status ] ) ? $prop_status_colors[ $p->status ] : array( '#f3f4f6', '#6b7280' );
              $view_url     = $this->portal_page_url( array( 'tab' => 'proposals', 'action' => 'view', 'item_id' => $p->id ) );
              $edit_url     = $this->portal_page_url( array( 'tab' => 'proposals', 'action' => 'edit', 'item_id' => $p->id ) );
              $del_url      = wp_nonce_url(
                is_admin()
                  ? admin_url( 'admin-post.php?action=acdc_delete_proposal&proposal_id=' . $p->id . '&redirect=' . rawurlencode( $this->portal_page_url( array( 'tab' => 'proposals' ) ) ) )
                  : $this->portal_page_url( array( 'trf_action' => 'delete_proposal', 'proposal_id' => (int) $p->id ) ),
                'acdc_delete_proposal_' . $p->id
              );
              $has_doc    = ! empty( $p->pdf_url );
              $search_key = strtolower( $p->formation_title . ' ' . $p->client_company . ' ' . $p->client_name . ' ' . $p->client_email );
            ?>
            <tr
              data-prop-search="<?php echo esc_attr( $search_key ); ?>"
              data-prop-status="<?php echo esc_attr( $p->status ); ?>"
            >
              <td>
                <strong><?php echo esc_html( $p->client_company ?: '—' ); ?></strong>
                <?php if ( $p->client_name ) : ?>
                  <div style="font-size:11px;color:#4b5d76;"><?php echo esc_html( $p->client_name ); ?><?php if ( $p->client_title ) echo ' &middot; ' . esc_html( $p->client_title ); ?></div>
                <?php endif; ?>
              </td>
              <td>
                <strong><?php echo esc_html( $p->formation_title ?: '—' ); ?></strong>
                <div style="font-size:11px;color:#4b5d76;margin-top:2px;">
                  <?php echo esc_html( $p->formation_days ); ?> j
                  <?php if ( $p->formation_price_per_day ) : ?>&middot; <?php echo number_format( (float) $p->formation_price_per_day, 0, ',', '&#160;' ); ?>&#160;&#8364;/j<?php endif; ?>
                </div>
              </td>
              <td><strong><?php echo $p->formation_total ? number_format( (float) $p->formation_total, 0, ',', '&#160;' ) . '&#160;&#8364;' : '—'; ?></strong></td>
              <td>
                <span class="acdc-need-badge" style="background:<?php echo esc_attr( $s_colors[0] ); ?>;color:<?php echo esc_attr( $s_colors[1] ); ?>;">
                  <?php echo esc_html( $status_label ); ?>
                </span>
              </td>
              <td style="font-size:12px;color:#4b5d76;"><?php echo mysql2date( 'd/m/Y', $p->created_at ); ?></td>
              <td style="font-size:12px;color:#4b5d76;">
                <?php echo ! empty( $p->last_sent_at ) ? mysql2date( 'd/m/Y', $p->last_sent_at ) : '—'; ?>
              </td>
              <td class="acdc-actions-cell-icons">
                <div class="acdc-groups-actions-inline" data-acdc-prospect-actions>
                  <!-- Menu 3 points -->
                  <div class="acdc-prospect-action-menu">
                    <button type="button"
                            class="acdc-row-action-icon acdc-prospect-action-trigger"
                            data-acdc-prospect-menu-toggle
                            data-acdc-iconized="1"
                            aria-haspopup="true"
                            aria-expanded="false"
                            aria-label="Plus d'actions"
                            title="Plus d'actions">
                      <?php echo $this->render_inline_icon( 'more-horizontal', 18 ); ?>
                      <span class="acdc-action-hub-sr screen-reader-text">Actions</span>
                    </button>
                    <div class="acdc-prospect-action-dropdown" data-acdc-prospect-menu hidden>
                      <a class="acdc-prospect-action-item" href="<?php
                        $contract_args = array( 'tab' => 'registration_contract', 'action' => 'new', 'proposal_id' => (int) $p->id );
                        // source_prospect_id : natif (colonne native ou JOIN via get_proposals_all), sinon fallback need_id
                        $resolved_pid = ! empty( $p->source_prospect_id ) ? (int) $p->source_prospect_id : 0;
                        if ( ! $resolved_pid && ! empty( $p->need_id ) ) {
                          global $wpdb;
                          $resolved_pid = (int) $wpdb->get_var( $wpdb->prepare(
                            "SELECT source_prospect_id FROM {$wpdb->prefix}acdc_of_needs WHERE id = %d LIMIT 1",
                            (int) $p->need_id
                          ) );
                        }
                        if ( $resolved_pid ) {
                          $contract_args['prospect_id'] = $resolved_pid;
                        }
                        echo esc_url( $this->portal_page_url( $contract_args ) );
                      ?>">Convention / contrat</a>
                      <a class="acdc-prospect-action-item" href="<?php
                        echo esc_url( $this->portal_page_url( array(
                          'tab'         => 'create_session',
                          'proposal_id' => (int) $p->id,
                        ) ) );
                      ?>">Créer les séances</a>
                      <a class="acdc-prospect-action-item" href="<?php echo esc_url( $this->portal_page_url( array( 'tab' => 'quotes', 'scope' => 'action', 'quote_action' => 'create', 'proposal_id' => (int) $p->id ) ) ); ?>">Devis</a>
                    </div>
                  </div>
                  <!-- Icônes directes -->
                  <a href="<?php echo esc_url( $view_url ); ?>" class="acdc-row-action-icon acdc-row-view-link" data-acdc-iconized="1" title="Voir" aria-label="Voir cette proposition">
                    <?php echo $this->render_inline_icon( 'view', 18 ); ?>
                    <span class="acdc-action-hub-sr screen-reader-text">Voir</span>
                  </a>
                  <a href="<?php echo esc_url( $edit_url ); ?>" class="acdc-row-action-icon acdc-row-edit-link" data-acdc-iconized="1" title="Modifier" aria-label="Modifier cette proposition">
                    <?php echo $this->render_inline_icon( 'edit-pencil', 18 ); ?>
                    <span class="acdc-action-hub-sr screen-reader-text">Modifier</span>
                  </a>
                  <a href="<?php echo esc_url( $del_url ); ?>" class="acdc-row-action-icon acdc-row-delete-link" data-acdc-iconized="1" title="Supprimer" aria-label="Supprimer cette proposition"
                     onclick="return confirm('Supprimer cette proposition ?');">
                    <?php echo $this->render_inline_icon( 'trash', 18 ); ?>
                    <span class="acdc-action-hub-sr screen-reader-text">Supprimer</span>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
    <div style="text-align:center;margin-top:14px;">
      <button type="button" id="acdc-proposals-load-more" class="acdc-button acdc-button-soft" style="display:none;">Afficher 50&#160;de plus</button>
    </div>

    <style>
      .acdc-proposals-filters-row{display:flex;flex-wrap:wrap;gap:12px;align-items:center;padding:2px 0;}
      .acdc-proposals-filters-row input,.acdc-proposals-filters-row select{height:40px;border:1px solid var(--acdc-border);border-radius:10px;padding:0 12px;font-size:14px;color:#1E4777;background:#fff;min-width:160px;}
      .acdc-proposals-filters-row input{min-width:260px;}
      .acdc-prop-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;}
      .acdc-prop-badge--brouillon{background:#f0f4ff;color:#1e4777;}
      .acdc-prop-badge--envoyee{background:#fff7ed;color:#c2610c;}
      .acdc-prop-badge--acceptee{background:#ecfdf5;color:#059669;}
      .acdc-prop-badge--refusee{background:#fef2f2;color:#dc2626;}
      .acdc-prop-badge--expiree{background:#f3f4f6;color:#6b7280;}
      .acdc-prospect-action-menu{position:relative;display:inline-flex;align-items:center;}
      .acdc-prospect-action-trigger{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid var(--acdc-border);border-radius:8px;background:#fff;color:#1E4777;cursor:pointer;padding:0;}
      .acdc-prospect-action-trigger:hover{background:#f0f4ff;}
      .acdc-prospect-action-dropdown{position:fixed;top:0;left:0;min-width:240px;background:#fff;border:1px solid #dce4ec;border-radius:10px;box-shadow:0 10px 30px rgba(28,44,64,.12);padding:10px 0;z-index:99999;}
      .acdc-prospect-action-item{display:block;padding:12px 22px;color:#1E4777 !important;text-decoration:none !important;font-size:15px;line-height:1.35;white-space:nowrap;}
      .acdc-prospect-action-item:hover{background:#F6F8FB;}
    </style>

    <script>

    (function(){
      var searchEl = document.getElementById('acdc-proposals-search');
      var filterSt = document.getElementById('acdc-proposals-filter-status');
      var table    = document.getElementById('acdc-proposals-list-table');
      var PAGE_SIZE = 50;
      var visibleCount = 0;
      var btnMore = document.getElementById('acdc-proposals-load-more');
      var countEl = document.getElementById('acdc-proposals-count');
      var currentMatched = [];
      function applyFilters(){
        if(!table) return;
        var q  = searchEl ? searchEl.value.toLowerCase() : '';
        var st = filterSt ? filterSt.value : '';
        var rows = table.querySelectorAll('tbody tr[data-prop-status]');
        currentMatched = [];
        rows.forEach(function(row){ row.style.display='none'; });
        rows.forEach(function(row){
          var ok = (!q || (row.dataset.propSearch||'').indexOf(q)>-1)
            && (!st || row.dataset.propStatus===st);
          if(ok) currentMatched.push(row);
        });
        visibleCount = Math.min(PAGE_SIZE, currentMatched.length);
        for(var i=0;i<currentMatched.length;i++){
          currentMatched[i].style.display = i < visibleCount ? '' : 'none';
        }
        if(btnMore) btnMore.style.display = currentMatched.length > visibleCount ? '' : 'none';
        if(countEl) countEl.textContent = currentMatched.length + ' proposition' + (currentMatched.length>1?'s':'');
      }
      if(btnMore){
        btnMore.addEventListener('click', function(){
          var newCount = Math.min(visibleCount + PAGE_SIZE, currentMatched.length);
          for(var i=visibleCount;i<newCount;i++) currentMatched[i].style.display='';
          visibleCount = newCount;
          if(visibleCount >= currentMatched.length) btnMore.style.display='none';
        });
      }
      if(searchEl) searchEl.addEventListener('input', applyFilters);
      if(filterSt) filterSt.addEventListener('change', applyFilters);
      applyFilters();
    })();
    </script>
    <?php
  }

  /* ---------------------------------------------------------------
   * Vue détail d'une proposition (avec bouton modifier + générer)
   * --------------------------------------------------------------- */
  private function render_proposal_detail( $p, $nonce, $ajax_url ) {
    $statuses     = $this->get_proposal_status_labels();
    $status_label = isset( $statuses[ $p->status ] ) ? $statuses[ $p->status ] : ucfirst( (string) $p->status );
    $edit_url     = $this->portal_page_url( array( 'tab' => 'proposals', 'action' => 'edit', 'item_id' => $p->id ) );
    $list_url     = $this->portal_page_url( array( 'tab' => 'proposals' ) );

    /* URL Renvoyer — noncée, admin-post */
    $resend_url = wp_nonce_url(
      add_query_arg( array( 'action' => 'acdc_proposal_resend_email', 'proposal_id' => (int) $p->id ), admin_url( 'admin-post.php' ) ),
      'acdc_proposal_resend_email_' . (int) $p->id
    );

    /* Feedback resend depuis la redirection */
    $resend_status = isset( $_GET['resend'] ) ? sanitize_key( $_GET['resend'] ) : '';
    $resend_msg    = isset( $_GET['msg'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['msg'] ) ) ) : '';
    ?>
    <?php if ( 'ok' === $resend_status ) : ?>
    <div style="background:#ecfdf5;border:1px solid #6ee7b7;border-radius:10px;padding:12px 18px;margin-bottom:18px;font-size:13px;color:#065f46;display:flex;align-items:center;gap:8px;">
      <?php echo $this->render_inline_icon( 'check-circle', 18 ); ?>
      Proposition envoy&#233;e avec succ&#232;s &#224; <strong><?php echo esc_html( $p->client_email ); ?></strong>.
    </div>
    <?php elseif ( 'error' === $resend_status ) : ?>
    <div style="background:#fff0f0;border:1px solid #fca5a5;border-radius:10px;padding:12px 18px;margin-bottom:18px;font-size:13px;color:#991b1b;display:flex;align-items:center;gap:8px;">
      <?php echo $this->render_inline_icon( 'alert-circle', 18 ); ?>
      Erreur lors de l&#8217;envoi<?php echo $resend_msg ? ' : ' . esc_html( $resend_msg ) : '.'; ?>
    </div>
    <?php endif; ?>

    <div class="acdc-screen-wrap">
      <div class="acdc-screen-hero">
        <div>
          <div class="acdc-screen-eyebrow">Proposition #<?php echo (int) $p->id; ?></div>
          <h1 class="acdc-screen-title"><?php echo esc_html( $p->formation_title ?: 'Proposition sans titre' ); ?></h1>
          <p class="acdc-screen-subtitle"><?php echo esc_html( $p->client_company ); ?><?php if ( $p->client_name ) echo ' &mdash; ' . esc_html( $p->client_name ); ?></p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <a href="<?php echo esc_url( $list_url ); ?>" class="acdc-button acdc-button-soft">&#8592; Retour</a>
          <a href="<?php echo esc_url( $edit_url ); ?>" class="acdc-button acdc-button-soft"><?php echo $this->render_inline_icon( 'edit', 18 ); ?> Modifier</a>
          <a href="<?php echo esc_url( $this->get_proposal_pdf_url( $p->id ) ); ?>" target="_blank" class="acdc-button acdc-button-soft"><?php echo $this->render_inline_icon( 'file-text', 18 ); ?> Ouvrir la proposition</a>
          <?php if ( ! empty( $p->client_email ) ) : ?>
            <a href="<?php echo esc_url( $resend_url ); ?>"
               class="acdc-button acdc-button-primary"
               onclick="return confirm('Envoyer la proposition par email \u00e0 <?php echo esc_js( $p->client_email ); ?> ?');">
              <?php echo $this->render_inline_icon( 'send', 18 ); ?> Renvoyer
            </a>
          <?php else : ?>
            <span class="acdc-button acdc-button-soft" style="opacity:.45;cursor:not-allowed;pointer-events:none;">
              <?php echo $this->render_inline_icon( 'send', 18 ); ?> Renvoyer
            </span>
          <?php endif; ?>
          <?php if ( empty( $p->client_email ) ) : ?>
            <span style="font-size:12px;color:#c2410c;display:inline-flex;align-items:center;gap:5px;margin-left:4px;">
              <?php echo $this->render_inline_icon( 'alert-circle', 14 ); ?>
              Aucun email &mdash; <a href="<?php echo esc_url( $edit_url ); ?>" style="color:#c2410c;text-decoration:underline;">Modifier</a>
            </span>
          <?php endif; ?>
          <?php if ( ! empty( $p->pdf_url ) ) : ?>
            <a href="<?php echo esc_url( $p->pdf_url ); ?>" target="_blank" class="acdc-button acdc-button-soft" style="font-size:12px;"><?php echo $this->render_inline_icon( 'eye', 18 ); ?> Aperçu HTML</a>
          <?php endif; ?>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:24px;">
        <div class="acdc-panel">
          <div class="acdc-panel-heading"><h3>Formation &amp; organisation</h3></div>
          <div style="padding:16px;font-size:13px;line-height:1.7;color:#0f2c52;">
            <p><strong>Formation :</strong> <?php echo esc_html( $p->formation_title ); ?></p>
            <p><strong>Dur&#233;e :</strong> <?php echo (int) $p->formation_days; ?> j &times; <?php echo (int) $p->formation_hours_per_day; ?>h = <?php echo (int) $p->formation_days * (int) $p->formation_hours_per_day; ?>h</p>
            <p><strong>Apprenants :</strong> <?php echo (int) $p->formation_learners_count; ?></p>
            <?php if ( $p->formation_funding ) : ?><p><strong>Financement :</strong> <?php echo esc_html( $p->formation_funding ); ?></p><?php endif; ?>
            <?php if ( $p->formation_dates ) : ?><p><strong>Dates :</strong> <?php echo esc_html( $this->acdc_format_seances_list( (string) $p->formation_dates, 'inline' ) ); ?></p><?php endif; ?>
            <?php if ( $p->formation_location ) : ?><p><strong>Lieu :</strong> <?php echo esc_html( $p->formation_location ); ?></p><?php endif; ?>
            <p><strong>Total :</strong> <span style="color:#d6a353;font-weight:700;"><?php echo number_format( (float) $p->formation_total, 0, ',', '&#160;' ); ?>&#160;&#8364; net de TVA</span></p>
            <p><strong>Statut :</strong> <span class="acdc-prop-badge acdc-prop-badge--<?php echo esc_attr( $p->status ); ?>"><?php echo esc_html( $status_label ); ?></span></p>
          </div>
        </div>
        <div class="acdc-panel">
          <div class="acdc-panel-heading"><h3>Client</h3></div>
          <div style="padding:16px;font-size:13px;line-height:1.7;color:#0f2c52;">
            <p><strong>Entreprise :</strong> <?php echo esc_html( $p->client_company ); ?></p>
            <?php if ( $p->client_name ) : ?><p><strong>Contact :</strong> <?php echo esc_html( $p->client_name ); ?><?php if ( $p->client_title ) echo ' — ' . esc_html( $p->client_title ); ?></p><?php endif; ?>
            <?php if ( ! empty( $p->client_email ) ) : ?><p><strong>Email :</strong> <a href="mailto:<?php echo esc_attr( $p->client_email ); ?>" style="color:#0f2c52;"><?php echo esc_html( $p->client_email ); ?></a></p><?php endif; ?>
            <?php if ( $p->client_siret ) : ?><p><strong>SIRET :</strong> <?php echo esc_html( $p->client_siret ); ?></p><?php endif; ?>
            <?php if ( $p->client_address ) : ?><p><strong>Adresse :</strong> <?php echo esc_html( $p->client_address ); ?></p><?php endif; ?>
            <?php if ( $p->client_activity ) : ?><p><strong>Activit&#233; :</strong> <?php echo esc_html( $p->client_activity ); ?></p><?php endif; ?>
            <?php if ( $p->client_website ) : ?><p><strong>Site :</strong> <a href="<?php echo esc_url( $p->client_website ); ?>" target="_blank"><?php echo esc_html( $p->client_website ); ?></a></p><?php endif; ?>
          </div>
        </div>
      </div>

      <?php if ( ! empty( $p->client_about_text ) ) : ?>
      <div class="acdc-panel acdc-mt-20">
        <div class="acdc-panel-heading"><h3>&#192; propos du client</h3></div>
        <div style="padding:16px;font-size:13px;color:#0f2c52;line-height:1.6;"><?php echo nl2br( esc_html( $p->client_about_text ) ); ?></div>
      </div>
      <?php endif; ?>

      <?php if ( ! empty( $p->custom_objectives ) ) : ?>
      <div class="acdc-panel acdc-mt-20">
        <div class="acdc-panel-heading"><h3>Objectifs p&#233;dagogiques</h3></div>
        <div style="padding:16px;font-size:13px;color:#0f2c52;line-height:1.6;"><?php echo nl2br( esc_html( $p->custom_objectives ) ); ?></div>
      </div>
      <?php endif; ?>

      <?php if ( ! empty( $p->custom_methods ) ) : ?>
      <div class="acdc-panel acdc-mt-20">
        <div class="acdc-panel-heading"><h3>M&#233;thodes p&#233;dagogiques</h3></div>
        <div style="padding:16px;font-size:13px;color:#0f2c52;line-height:1.6;"><?php echo nl2br( esc_html( $p->custom_methods ) ); ?></div>
      </div>
      <?php endif; ?>

      <?php
      $program_days_view = array();
      for ( $pj = 1; $pj <= 10; $pj++ ) {
        $pjcol = 'program_j' . $pj;
        if ( ! empty( $p->$pjcol ) ) {
          $program_days_view[ $pj ] = (string) $p->$pjcol;
        }
      }
      ?>
      <?php if ( ! empty( $program_days_view ) ) : ?>
      <div class="acdc-panel acdc-mt-20">
        <div class="acdc-panel-heading"><h3>Programme</h3></div>
        <div style="padding:16px;">
          <?php foreach ( $program_days_view as $pjnum => $pjcontent ) : ?>
          <div style="margin-bottom:14px;">
            <div style="font-size:11px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Journ&#233;e <?php echo (int) $pjnum; ?></div>
            <div style="font-size:13px;color:#0f2c52;line-height:1.6;"><?php echo nl2br( esc_html( $pjcontent ) ); ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php
      $view_trainer_ids = ! empty( $p->trainer_ids ) ? array_filter( array_map( 'absint', explode( ',', $p->trainer_ids ) ) ) : array();
      $view_bios_map    = array();
      if ( ! empty( $p->trainer_bios_json ) ) {
        $decoded_bios = json_decode( $p->trainer_bios_json, true );
        if ( is_array( $decoded_bios ) ) {
          $view_bios_map = $decoded_bios;
        }
      }
      $view_trainers = array();
      if ( ! empty( $view_trainer_ids ) ) {
        foreach ( $this->get_trainers_for_proposal() as $vtr ) {
          if ( in_array( (int) $vtr->id, $view_trainer_ids, true ) ) {
            $view_trainers[] = $vtr;
          }
        }
      }
      ?>
      <?php if ( ! empty( $view_trainers ) ) : ?>
      <div class="acdc-panel acdc-mt-20">
        <div class="acdc-panel-heading"><h3>Formateurs</h3></div>
        <div style="padding:16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;">
          <?php foreach ( $view_trainers as $vtr ) : ?>
          <?php
            $vtrbio = ! empty( $view_bios_map[ (string) $vtr->id ] ) ? (string) $view_bios_map[ (string) $vtr->id ] : (string) ( isset( $vtr->description_text ) ? $vtr->description_text : '' );
          ?>
          <div style="border:1px solid #f0e6dc;border-radius:10px;padding:14px;">
            <div style="font-size:13px;font-weight:700;color:#0f2c52;"><?php echo esc_html( trim( $vtr->first_name . ' ' . $vtr->last_name ) ); ?></div>
            <?php if ( $vtrbio ) : ?>
            <div style="font-size:12px;color:#4b5d76;line-height:1.5;margin-top:6px;"><?php echo nl2br( esc_html( $vtrbio ) ); ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ( ! empty( $p->approach_before ) || ! empty( $p->approach_during ) || ! empty( $p->approach_after ) ) : ?>
      <div class="acdc-panel acdc-mt-20">
        <div class="acdc-panel-heading"><h3>Approche p&#233;dagogique</h3></div>
        <div style="padding:16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;font-size:13px;color:#0f2c52;line-height:1.6;">
          <?php if ( ! empty( $p->approach_before ) ) : ?>
          <div><div style="font-size:11px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Avant</div><?php echo nl2br( esc_html( $p->approach_before ) ); ?></div>
          <?php endif; ?>
          <?php if ( ! empty( $p->approach_during ) ) : ?>
          <div><div style="font-size:11px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Pendant</div><?php echo nl2br( esc_html( $p->approach_during ) ); ?></div>
          <?php endif; ?>
          <?php if ( ! empty( $p->approach_after ) ) : ?>
          <div><div style="font-size:11px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Apr&#232;s</div><?php echo nl2br( esc_html( $p->approach_after ) ); ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <script>
    function acdcPropRegenPdf(proposalId, el, nonce, ajaxUrl) {
      el.disabled = true;
      el.style.opacity = '0.6';
      el.style.pointerEvents = 'none';
      var fd = new FormData();
      fd.append('action', 'acdc_proposal_generate_pdf');
      fd.append('nonce', nonce);
      fd.append('proposal_id', proposalId);
      fetch(ajaxUrl, {method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
          el.disabled = false;
          el.style.opacity = '';
          el.style.pointerEvents = '';
          if (res.success) {
            location.reload();
          } else {
            alert('Erreur : ' + (res.data ? res.data.message : 'inconnue'));
          }
        })
        .catch(function(){ el.disabled=false; el.style.opacity=''; el.style.pointerEvents=''; alert('Erreur réseau.'); });
    }
    </script>
    <?php
  }

  /* ---------------------------------------------------------------
   * Formulaire complet création / modification
   * hotfix7 — tous les champs éditables
   * --------------------------------------------------------------- */
  private function render_proposal_full_form( $p, $nonce, $ajax_url ) {
    $is_edit   = ! empty( $p ) && ! empty( $p->id );
    $title_page = $is_edit ? 'Modifier la proposition' : 'Cr&#233;er une proposition';
    $list_url  = $this->portal_page_url( array( 'tab' => 'proposals' ) );

    /* hotfix11 — Bases + variantes, triées par groupe */
    $formations = $this->get_formations( array( 'archived' => false ) );

    $trainers = $this->get_trainers_for_proposal();
    $statuses = $this->get_proposal_status_labels();

    /* Valeurs du formulaire */
    $v = array(
      'id'                       => $is_edit ? (int) $p->id : 0,
      'need_id'                  => $is_edit && ! empty( $p->need_id ) ? (int) $p->need_id : 0,
      'source_prospect_id'       => $is_edit && ! empty( $p->source_prospect_id )
                                      ? (int) $p->source_prospect_id
                                      : ( isset( $_GET['prospect_id'] ) ? absint( wp_unslash( $_GET['prospect_id'] ) ) : 0 ),
      'title'                    => $is_edit ? (string) $p->title : '',
      'formation_id'             => $is_edit ? (int) $p->formation_id : 0,
      'formation_title'          => $is_edit ? (string) $p->formation_title : '',
      'thematique'               => $is_edit ? (string) $p->thematique : '',
      'formation_days'           => $is_edit ? (int) $p->formation_days : 1,
      'formation_hours_per_day'  => $is_edit ? (int) $p->formation_hours_per_day : 7,
      'formation_price_per_day'  => $is_edit ? (float) $p->formation_price_per_day : 900,
      'formation_total'          => $is_edit ? (float) $p->formation_total : 0,
      'formation_learners_count' => $is_edit ? (int) $p->formation_learners_count : 1,
      'formation_funding'        => $is_edit ? (string) $p->formation_funding : '',
      'formation_dates'          => $is_edit ? (string) $p->formation_dates : '',
      'formation_location'       => $is_edit ? (string) $p->formation_location : '',
      'formation_public'         => $is_edit && ! empty( $p->formation_public )         ? (string) $p->formation_public         : '',
      'proposal_deadline'        => $is_edit && ! empty( $p->proposal_deadline )        ? (string) $p->proposal_deadline        : 'Le plus tôt possible',
      'formation_discount'       => $is_edit && ! empty( $p->formation_discount )       ? (string) $p->formation_discount       : '—',
      'extra_resources_label'    => $is_edit && ! empty( $p->extra_resources_label )    ? (string) $p->extra_resources_label    : 'Offertes',
      'travel_costs_label'       => $is_edit && ! empty( $p->travel_costs_label )       ? (string) $p->travel_costs_label       : 'Offerts',
      'proposal_validity_months' => $is_edit && isset( $p->proposal_validity_months )   ? (int) $p->proposal_validity_months    : 3,
      'trainer_ids'              => $is_edit ? (string) $p->trainer_ids : '',
      'trainer_bios_json'        => $is_edit && ! empty( $p->trainer_bios_json ) ? (string) $p->trainer_bios_json : '{}',
      'client_name'              => $is_edit ? (string) $p->client_name : '',
      'client_title'             => $is_edit ? (string) $p->client_title : '',
      'client_email'             => $is_edit && ! empty( $p->client_email ) ? (string) $p->client_email : '',
      'client_company'           => $is_edit ? (string) $p->client_company : '',
      'client_siret'             => $is_edit ? (string) $p->client_siret : '',
      'client_address'           => $is_edit ? (string) $p->client_address : '',
      'client_activity'          => $is_edit ? (string) $p->client_activity : '',
      'client_website'           => $is_edit ? (string) $p->client_website : '',
      'client_about_text'        => $is_edit ? (string) $p->client_about_text : '',
      'about_project'            => $is_edit ? (string) $p->about_project : '',
      'custom_objectives'        => $is_edit ? (string) $p->custom_objectives : '',
      'custom_methods'           => $is_edit ? (string) $p->custom_methods : '',
      'custom_evaluation'        => $is_edit && ! empty( $p->custom_evaluation ) ? (string) $p->custom_evaluation : '',
      'custom_prerequisites'     => $is_edit ? (string) $p->custom_prerequisites : '',
      'program_j1'               => $is_edit ? (string) $p->program_j1 : '',
      'program_j2'               => $is_edit ? (string) $p->program_j2 : '',
      'program_j3'               => $is_edit ? (string) $p->program_j3 : '',
      'program_j4'               => $is_edit && ! empty( $p->program_j4  ) ? (string) $p->program_j4  : '',
      'program_j5'               => $is_edit && ! empty( $p->program_j5  ) ? (string) $p->program_j5  : '',
      'program_j6'               => $is_edit && ! empty( $p->program_j6  ) ? (string) $p->program_j6  : '',
      'program_j7'               => $is_edit && ! empty( $p->program_j7  ) ? (string) $p->program_j7  : '',
      'program_j8'               => $is_edit && ! empty( $p->program_j8  ) ? (string) $p->program_j8  : '',
      'program_j9'               => $is_edit && ! empty( $p->program_j9  ) ? (string) $p->program_j9  : '',
      'program_j10'              => $is_edit && ! empty( $p->program_j10 ) ? (string) $p->program_j10 : '',
      'extra_resources'          => $is_edit ? (string) $p->extra_resources : '',
      'about_acdc_text'          => $is_edit ? (string) $p->about_acdc_text   : '',
      'approach_before'          => $is_edit ? (string) $p->approach_before   : '',
      'approach_during'          => $is_edit ? (string) $p->approach_during   : '',
      'approach_after'           => $is_edit ? (string) $p->approach_after    : '',
      'access_resources'         => $is_edit ? (string) $p->access_resources  : '',
      'access_conditions'        => $is_edit ? (string) $p->access_conditions : '',
      'status'                   => $is_edit ? (string) $p->status : 'brouillon',
    );
    /* --- Nouveau depuis un prospect (menu Suivi commercial → Proposition commerciale) ---
       Le formulaire capte prospect_id mais ne se préremplissait que via un recueil lié.
       Lancé sans recueil (need_id=0), il restait entièrement vide. On préremplit ici depuis
       le prospect et on rattache automatiquement son recueil des besoins le plus récent
       (lie need_id → réutilise les fallbacks recueil ci-dessous et relie la proposition). */
    if ( ! $is_edit && ! empty( $v['source_prospect_id'] ) ) {
      $pp = $this->get_prospect( (int) $v['source_prospect_id'] );
      if ( $pp ) {
        if ( empty( $v['need_id'] ) && ! empty( $this->need_table ) ) {
          global $wpdb;
          $latest_need_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->need_table} WHERE source_prospect_id = %d ORDER BY id DESC LIMIT 1",
            (int) $v['source_prospect_id']
          ) );
          if ( $latest_need_id ) {
            $v['need_id'] = $latest_need_id;
          }
        }
        $is_company_pp = ! $this->is_individual_prospect_profile( (string) ( $pp->profile_type ?? '' ) );
        $pp_signer = trim( ( (string) ( $pp->signer_first_name ?? '' ) ) . ' ' . ( (string) ( $pp->signer_last_name ?? '' ) ) );
        if ( '' === $pp_signer ) {
          $pp_signer = trim( ( (string) ( $pp->first_name ?? '' ) ) . ' ' . ( (string) ( $pp->last_name ?? '' ) ) );
        }
        if ( '' === (string) $v['client_name'] && '' !== $pp_signer ) { $v['client_name'] = $pp_signer; }
        if ( '' === (string) $v['client_title'] && ! empty( $pp->signer_quality ) ) { $v['client_title'] = (string) $pp->signer_quality; }
        if ( empty( $v['client_email'] ) ) {
          $pp_email = $this->get_prospect_primary_email( $pp );
          if ( $pp_email ) { $v['client_email'] = $pp_email; }
        }
        if ( '' === (string) $v['client_company'] && $is_company_pp && ! empty( $pp->company_name ) ) { $v['client_company'] = (string) $pp->company_name; }
        if ( '' === (string) $v['client_siret'] && ! empty( $pp->siret ) ) { $v['client_siret'] = (string) $pp->siret; }
        if ( '' === (string) $v['client_address'] && ! empty( $pp->address ) ) { $v['client_address'] = (string) $pp->address; }
        if ( empty( $v['client_postal_code'] ) && ! empty( $pp->postal_code ) ) { $v['client_postal_code'] = (string) $pp->postal_code; }
        if ( empty( $v['client_city'] ) && ! empty( $pp->city ) ) { $v['client_city'] = (string) $pp->city; }
        $pp_fid = isset( $pp->desired_formation_id ) ? (int) $pp->desired_formation_id : 0;
        if ( empty( $v['formation_id'] ) && $pp_fid ) {
          $pp_formation = $this->get_formation( $pp_fid );
          if ( $pp_formation ) {
            $v['formation_id'] = $pp_fid;
            if ( '' === (string) $v['formation_title'] && ! empty( $pp_formation->title ) ) { $v['formation_title'] = (string) $pp_formation->title; }
            if ( '' === (string) $v['title'] && ! empty( $pp_formation->title ) ) { $v['title'] = (string) $pp_formation->title; }
          }
        }
        if ( '' === (string) $v['thematique'] && ! empty( $pp->desired_thematique ) ) { $v['thematique'] = (string) $pp->desired_thematique; }
        if ( '' === (string) $v['title'] && ! empty( $pp->desired_training ) ) { $v['title'] = (string) $pp->desired_training; }
        if ( '' === (string) $v['formation_title'] && ! empty( $pp->desired_training ) ) { $v['formation_title'] = (string) $pp->desired_training; }
      }
    }

    /* --- Fallback depuis le recueil des besoins lié --- */
    $v_need = ! empty( $v['need_id'] ) ? $this->get_need( (int) $v['need_id'] ) : null;
    if ( $v_need ) {
      if ( empty( $v['formation_public'] ) && ! empty( $v_need->target_audience ) ) {
        $v['formation_public'] = strip_tags( (string) $v_need->target_audience );
      }
      if ( empty( $v['client_email'] ) && ! empty( $v_need->source_prospect_id ) ) {
        $prospect_fb = $this->get_prospect( (int) $v_need->source_prospect_id );
        if ( $prospect_fb ) {
          $email_fb = $this->get_prospect_primary_email( $prospect_fb );
          if ( $email_fb ) { $v['client_email'] = $email_fb; }
        }
      }
      if ( empty( $v['client_address'] ) && ! empty( $v_need->company_id ) ) {
        $company_fb = $this->get_company( (int) $v_need->company_id );
        if ( $company_fb ) {
          if ( empty( $v['client_address'] )     && ! empty( $company_fb->address ) )     { $v['client_address']     = (string) $company_fb->address; }
          if ( empty( $v['client_postal_code'] ) && ! empty( $company_fb->postal_code ) ) { $v['client_postal_code'] = (string) $company_fb->postal_code; }
          if ( empty( $v['client_city'] )        && ! empty( $company_fb->city ) )        { $v['client_city']        = (string) $company_fb->city; }
        }
      }
      if ( empty( $v['about_project'] ) ) {
        $parts = array();
        if ( ! empty( $v_need->context_text ) )   { $parts[] = trim( strip_tags( (string) $v_need->context_text ) ); }
        if ( ! empty( $v_need->expressed_need ) )  { $parts[] = trim( strip_tags( (string) $v_need->expressed_need ) ); }
        if ( $parts ) { $v['about_project'] = implode( "\n\n", array_filter( $parts ) ); }
      }
    }
    /* --- Fallback moyens pédagogiques depuis la formation du catalogue --- */
    if ( empty( $v['custom_methods'] ) && ! empty( $v['formation_id'] ) ) {
      $formation_fb = $this->get_formation( (int) $v['formation_id'] );
      if ( $formation_fb && ! empty( $formation_fb->moyens_pedago ) ) {
        $v['custom_methods'] = (string) $formation_fb->moyens_pedago;
      }
    }

    $trainer_ids_arr = array_filter( array_map( 'intval', explode( ',', $v['trainer_ids'] ) ) );

    /* Helper champ texte */
    $field_s = function( $id, $label, $key, $placeholder = '', $required = false ) use ( $v ) {
      $req = $required ? ' *' : '';
      echo '<p style="margin-bottom:14px;">';
      echo '<label style="font-size:12px;font-weight:600;color:#0f2c52;display:block;margin-bottom:5px;">' . esc_html( $label . $req ) . '</label>';
      echo '<input type="text" name="proposal[' . esc_attr( $key ) . ']" value="' . esc_attr( $v[ $key ] ) . '" placeholder="' . esc_attr( $placeholder ) . '" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">';
      echo '</p>';
    };
    $field_ta = function( $id, $label, $key, $rows = 4, $placeholder = '' ) use ( $v ) {
      echo '<p style="margin-bottom:14px;">';
      echo '<label style="font-size:12px;font-weight:600;color:#0f2c52;display:block;margin-bottom:5px;">' . esc_html( $label ) . '</label>';
      echo '<textarea name="proposal[' . esc_attr( $key ) . ']" rows="' . (int) $rows . '" placeholder="' . esc_attr( $placeholder ) . '" style="width:100%;border-radius:10px;border:1px solid #dfe5ee;padding:10px 12px;font-size:13px;line-height:1.55;resize:vertical;">' . esc_textarea( $v[ $key ] ) . '</textarea>';
      echo '</p>';
    };
    ?>
    <div class="acdc-screen-wrap">
      <div class="acdc-screen-hero">
        <div>
          <div class="acdc-screen-eyebrow">Propositions commerciales</div>
          <h1 class="acdc-screen-title"><?php echo $title_page; ?></h1>
          <p class="acdc-screen-subtitle">Remplissez tous les champs pour g&#233;n&#233;rer un document complet et personnalis&#233;.</p>
        </div>
        <a href="<?php echo esc_url( $list_url ); ?>" class="acdc-button acdc-button-soft">&#8592; Retour &#224; la liste</a>
      </div>

      <form id="acdc-proposal-full-form" style="margin-top:24px;" onsubmit="return false;">
        <input type="hidden" name="proposal[id]" value="<?php echo esc_attr( $v['id'] ); ?>">
        <input type="hidden" name="proposal[status]" value="<?php echo esc_attr( $v['status'] ); ?>">
        <input type="hidden" id="acdc-full-need-id" name="proposal[need_id]" value="<?php echo esc_attr( $v['need_id'] ); ?>">
        <input type="hidden" name="proposal[source_prospect_id]" value="<?php echo esc_attr( $v['source_prospect_id'] ); ?>">

        <?php /* === SECTION 1 : Formation & organisation === */ ?>
        <div class="acdc-panel acdc-mb-18">
          <div class="acdc-panel-heading" style="padding:14px 20px;border-bottom:1px solid #f0e6dc;">
            <h3 style="margin:0;font-size:13px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;">1 — Formation &amp; organisation</h3>
          </div>
          <div style="padding:20px;">

            <?php $field_s( '', 'Titre de la proposition', 'title', 'ex. Proposition IA &#8212; SKILL CONSEILS' ); ?>

            <p style="margin-bottom:14px;">
              <label style="font-size:12px;font-weight:600;color:#0f2c52;display:block;margin-bottom:5px;">Formation du catalogue *</label>
              <select id="acdc-full-formation-id" name="proposal[formation_id]" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
                <option value="">&#8212; S&#233;lectionner une formation &#8212;</option>
                <?php foreach ( $formations as $f ) :
                  $selected = ( (int) $v['formation_id'] === (int) $f->id ) ? 'selected' : '';
                ?>
                  <option value="<?php echo esc_attr( $f->id ); ?>"
                          data-title="<?php echo esc_attr( $f->title ); ?>"
                          data-duration="<?php echo esc_attr( $f->duration ); ?>"
                          data-price="<?php echo esc_attr( $f->price_ht ?: '' ); ?>"
                          data-total-price="<?php echo esc_attr( $f->price_ht ?: '' ); ?>"
                          data-duration-days="<?php
                            $dur_raw = (string) ( $f->duration ?? '' );
                            $dur_days = 0;
                            if ( preg_match( '/^(\\d+):(\\d{2})$/', $dur_raw, $m ) ) {
                              $total_h = (int) $m[1] + (int) $m[2] / 60;
                              $dur_days = $total_h > 0 ? max( 1, round( $total_h / 7 ) ) : 1;
                            } elseif ( preg_match( '/(\\d+)\\s*j/i', $dur_raw, $m ) ) {
                              $dur_days = (int) $m[1];
                            } elseif ( preg_match( '/^(\\d+)$/', $dur_raw, $m ) ) {
                              $dur_days = (int) $m[1];
                            }
                            echo esc_attr( $dur_days > 0 ? $dur_days : '' );
                          ?>"
                          data-thematique="<?php echo esc_attr( $f->thematique ?: '' ); ?>"
                          <?php echo $selected; ?>>
                    <?php
                      /* hotfix10 — [ID] Titre — Modalité — Prix */
                      echo esc_html( $this->build_formation_option_label( $f ) );
                    ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </p>
            <input type="hidden" name="proposal[formation_title]" id="acdc-full-formation-title" value="<?php echo esc_attr( $v['formation_title'] ); ?>">
            <input type="hidden" name="proposal[thematique]" id="acdc-full-thematique" value="<?php echo esc_attr( $v['thematique'] ); ?>">

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px;">
              <p style="margin:0;">
                <label style="font-size:12px;font-weight:600;color:#0f2c52;display:block;margin-bottom:5px;">Nombre de jours *</label>
                <input type="number" name="proposal[formation_days]" id="acdc-full-days" min="1" max="10" value="<?php echo esc_attr( $v['formation_days'] ); ?>" oninput="acdcFullCalcTotal();acdcUpdateProgramDays();" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
              </p>
              <p style="margin:0;">
                <label style="font-size:12px;font-weight:600;color:#0f2c52;display:block;margin-bottom:5px;">Heures/jour</label>
                <input type="number" name="proposal[formation_hours_per_day]" min="1" max="10" value="<?php echo esc_attr( $v['formation_hours_per_day'] ); ?>" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
              </p>
              <p style="margin:0;">
                <label style="font-size:12px;font-weight:600;color:#0f2c52;display:block;margin-bottom:5px;">Tarif jour (&#8364; HT) *</label>
                <input type="number" name="proposal[formation_price_per_day]" id="acdc-full-price" min="0" step="10" value="<?php echo esc_attr( $v['formation_price_per_day'] ); ?>" oninput="acdcFullCalcTotal()" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
              </p>
            </div>
            <input type="hidden" name="proposal[formation_total]" id="acdc-full-total-hidden" value="<?php echo esc_attr( $v['formation_total'] ); ?>">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
              <p style="margin:0;">
                <label style="font-size:12px;font-weight:600;color:#0f2c52;display:block;margin-bottom:5px;">Nombre d&#8217;apprenants</label>
                <input type="number" name="proposal[formation_learners_count]" min="1" value="<?php echo esc_attr( $v['formation_learners_count'] ); ?>" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
              </p>
              <p style="margin:0;">
                <label style="font-size:12px;font-weight:600;color:#0f2c52;display:block;margin-bottom:5px;">Financement</label>
                <?php
                $edit_funding = (string) $v['formation_funding'];
                $edit_is_opco = ( 'OPCO' === $edit_funding || 0 === strpos( $edit_funding, 'OPCO' ) );
                $edit_funding_sel = $edit_is_opco ? 'OPCO' : $edit_funding;
                ?>
                <select id="acdc-edit-funding-select" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;margin-bottom:0;">
                  <?php foreach ( $this->get_need_funding_options() as $fk => $fl ) : ?>
                    <option value="<?php echo esc_attr( $fk ); ?>" <?php selected( $edit_funding_sel, $fk ); ?>><?php echo esc_html( $fl ); ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" id="acdc-edit-funding-hidden" name="proposal[formation_funding]" value="<?php echo esc_attr( $edit_funding ); ?>">
                <div id="acdc-edit-opco-row" style="margin-top:6px;<?php echo $edit_is_opco ? '' : 'display:none;'; ?>">
                  <label style="font-size:11px;color:#4b5d76;display:block;margin-bottom:3px;">OPCO / Financeur</label>
                  <select id="acdc-edit-funder-select" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
                    <option value="">— Sélectionner un OPCO —</option>
                    <?php foreach ( $this->get_funders() as $funder ) : ?>
                      <option value="OPCO — <?php echo esc_attr( $funder->name ); ?>"
                        <?php selected( $edit_funding, 'OPCO — ' . $funder->name ); ?>>
                        <?php echo esc_html( $funder->name ); ?><?php if ( $funder->sector ) : ?> (<?php echo esc_html( $funder->sector ); ?>)<?php endif; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <script>
                (function(){
                  var sel = document.getElementById('acdc-edit-funding-select');
                  var opcoRow = document.getElementById('acdc-edit-opco-row');
                  var hidden = document.getElementById('acdc-edit-funding-hidden');
                  var opcoSel = document.getElementById('acdc-edit-funder-select');
                  if (!sel) return;
                  function sync(){
                    var v = sel.value;
                    opcoRow.style.display = (v === 'OPCO') ? '' : 'none';
                    hidden.value = (v === 'OPCO') ? (opcoSel.value || 'OPCO') : v;
                  }
                  sel.addEventListener('change', sync);
                  if (opcoSel) { opcoSel.addEventListener('change', sync); }
                  sync();
                })();
                </script>
              </p>
            </div>

            <?php
            // ── Widget multi-dates séances ───────────────────────────────
            $raw_dates = $v['formation_dates'] ?? '';
            $seance_dates = array();
            if ( $raw_dates ) {
              foreach ( array_filter( array_map( 'trim', explode( ',', $raw_dates ) ) ) as $d ) {
                if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
                  $seance_dates[] = $d;
                }
              }
            }
            $seance_dates_json = wp_json_encode( $seance_dates );
            ?>
            <p style="margin-bottom:14px;">
              <label style="font-size:12px;font-weight:600;color:#0f2c52;display:block;margin-bottom:6px;">Dates des séances</label>
              <input type="hidden" name="proposal[formation_dates]" id="acdc-prop-dates-hidden" value="<?php echo esc_attr( implode( ',', $seance_dates ) ); ?>">

              <span id="acdc-seances-chips" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;min-height:0;"></span>

              <span style="display:flex;gap:6px;align-items:center;">
                <input type="date" id="acdc-seance-date-picker"
                       style="height:36px;border-radius:8px;border:1px solid #dfe5ee;padding:0 10px;font-size:13px;color:#0f2c52;">
                <button type="button" id="acdc-seance-add-btn"
                        style="height:36px;padding:0 14px;background:#0f2c52;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">
                  + Ajouter
                </button>
              </span>
              <?php if ( $raw_dates && empty( $seance_dates ) ) : ?>
                <span style="display:block;margin-top:6px;font-size:12px;color:#4b5d76;">
                  Valeur actuelle (texte libre) : <em><?php echo esc_html( $raw_dates ); ?></em>
                  — Ajoutez des dates pour la remplacer.
                </span>
              <?php endif; ?>
            </p>
            <script>
            (function(){
              var initialDates = <?php echo $seance_dates_json; ?>;
              var dates = initialDates.slice();
              var chipsEl  = document.getElementById('acdc-seances-chips');
              var hiddenEl = document.getElementById('acdc-prop-dates-hidden');
              var picker   = document.getElementById('acdc-seance-date-picker');
              var addBtn   = document.getElementById('acdc-seance-add-btn');
              if(!chipsEl || !hiddenEl || !picker || !addBtn){return;}

              var months = ['jan.','fév.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];
              function fmtDate(iso){
                var p = iso.split('-');
                if(p.length!==3){return iso;}
                return p[2]+' '+months[parseInt(p[1],10)-1]+' '+p[0];
              }

              function render(){
                chipsEl.innerHTML = '';
                dates.forEach(function(d, i){
                  var chip = document.createElement('span');
                  chip.style.cssText = 'display:inline-flex;align-items:center;gap:5px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:20px;padding:3px 10px 3px 12px;font-size:12px;font-weight:600;color:#1e3a8a;white-space:nowrap;';
                  chip.innerHTML = '<span>Séance '+(i+1)+' — '+fmtDate(d)+'</span>';
                  var rm = document.createElement('button');
                  rm.type='button';
                  rm.innerHTML='×';
                  rm.setAttribute('aria-label','Supprimer');
                  rm.style.cssText='background:none;border:none;font-size:15px;line-height:1;cursor:pointer;color:#6366f1;padding:0 0 1px;';
                  rm.addEventListener('click',function(){dates.splice(i,1);render();});
                  chip.appendChild(rm);
                  chipsEl.appendChild(chip);
                });
                hiddenEl.value = dates.join(',');
              }

              addBtn.addEventListener('click',function(){
                var v = picker.value;
                if(!v){return;}
                if(dates.indexOf(v)===-1){
                  dates.push(v);
                  dates.sort();
                  render();
                }
                picker.value='';
                picker.focus();
              });
              picker.addEventListener('keydown',function(e){
                if(e.key==='Enter'){e.preventDefault();addBtn.click();}
              });

              render();
            })();
            </script>
            <?php $field_s( '', 'Lieu de formation', 'formation_location', 'Dans vos locaux, adresse…' ); ?>
            <?php $field_s( '', 'Public cible', 'formation_public', 'ex. Collaborateurs et membres de la direction' ); ?>
            <?php $field_s( '', 'Date limite de confirmation', 'proposal_deadline', 'ex. Le plus tôt possible, 15 juin 2026…' ); ?>

            <p style="font-size:12px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.05em;padding:8px 0 4px;border-top:1px solid #f0e6dc;margin-top:12px;">Tableau financier (page 13)</p>
            <?php $field_s( '', 'Remise', 'formation_discount', 'ex. — ou 10%' ); ?>
            <?php $field_s( '', 'Libellé ressources complémentaires', 'extra_resources_label', 'ex. Offertes' ); ?>
            <?php $field_s( '', 'Libellé frais de déplacement', 'travel_costs_label', 'ex. Offerts' ); ?>
            <p style="margin-bottom:14px;">
              <label style="font-size:12px;font-weight:600;color:#0f2c52;display:block;margin-bottom:5px;">Validit&#233; de la proposition (mois)</label>
              <input type="number" name="proposal[proposal_validity_months]" min="1" max="24" value="<?php echo esc_attr( $v['proposal_validity_months'] ); ?>" style="width:100px;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
            </p>

            <div style="background:linear-gradient(135deg,#fef6e4 0%,#fbf8f7 100%);border:1px solid #f0e6dc;border-radius:10px;padding:12px 16px;margin-top:16px;display:flex;align-items:center;justify-content:space-between;">
              <span style="font-size:13px;color:#4b5d76;">Total de la proposition</span>
              <strong id="acdc-full-total-display" style="font-size:22px;font-weight:700;color:#0f2c52;"><?php echo number_format( $v['formation_total'], 0, ',', '&#160;' ); ?>&#160;&#8364;</strong>
            </div>

            <p style="margin-bottom:14px;margin-top:16px;">
              <label style="font-size:12px;font-weight:600;color:#0f2c52;display:block;margin-bottom:5px;">Statut</label>
              <select name="proposal[status]" style="height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;min-width:200px;">
                <?php foreach ( $this->get_proposal_status_labels() as $k => $lbl ) :
                  $sel = $v['status'] === $k ? 'selected' : '';
                ?>
                  <option value="<?php echo esc_attr( $k ); ?>" <?php echo $sel; ?>><?php echo esc_html( html_entity_decode( $lbl ) ); ?></option>
                <?php endforeach; ?>
              </select>
            </p>
          </div>
        </div>

        <?php /* === SECTION 2 : Client === */ ?>
        <div class="acdc-panel acdc-mb-18">
          <div class="acdc-panel-heading" style="padding:14px 20px;border-bottom:1px solid #f0e6dc;">
            <h3 style="margin:0;font-size:13px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;">2 — &#192; propos du client</h3>
          </div>
          <div style="padding:20px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
              <?php $field_s( '', 'Nom du destinataire', 'client_name', 'Pr&#233;nom NOM' ); ?>
              <?php $field_s( '', 'Titre / Fonction', 'client_title', 'Directeur &amp; CEO' ); ?>
              <?php $field_s( '', 'Raison sociale', 'client_company' ); ?>
              <?php $field_s( '', 'SIRET', 'client_siret' ); ?>
              <?php $field_s( '', 'Activit&#233;', 'client_activity', 'formation continue d&#8217;adultes…' ); ?>
              <?php $field_s( '', 'Site web (IA)', 'client_website', 'https://…' ); ?>
            </div>
            <p style="margin-bottom:14px;">
              <label style="font-size:12px;font-weight:600;color:#0f2c52;display:block;margin-bottom:5px;">Email du signataire <span style="color:#c99d4a;font-weight:400;font-size:11px;">(utilis&#233; pour l&#8217;envoi de la proposition)</span></label>
              <input type="email" name="proposal[client_email]" value="<?php echo esc_attr( $v['client_email'] ); ?>" placeholder="prenom.nom@entreprise.com" style="width:100%;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
            </p>
            <?php $field_ta( '', 'Adresse du si&#232;ge', 'client_address', 2, '512 chemin des N&#233;gadoux, 83140 Six-Fours…' ); ?>
            <?php $field_ta( '', '&#192; propos de l&#8217;entreprise (texte du document)', 'client_about_text', 6, 'Description de l\'entreprise cliente…' ); ?>
            <div style="display:flex;align-items:center;gap:10px;margin-top:-6px;">
              <button type="button" id="acdc-full-ai-btn" class="acdc-button acdc-button-soft" style="height:36px;font-size:12px;"
                      onclick="acdcFullGenerateAbout()">&#9889; G&#233;n&#233;rer via IA</button>
              <span id="acdc-full-ai-status" style="font-size:12px;color:#4b5d76;"></span>
            </div>
          </div>
        </div>

        <?php /* === SECTION 3 : Projet & besoins === */ ?>
        <div class="acdc-panel acdc-mb-18">
          <div class="acdc-panel-heading" style="padding:14px 20px;border-bottom:1px solid #f0e6dc;">
            <h3 style="margin:0;font-size:13px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;">3 — Votre projet &amp; vos besoins</h3>
          </div>
          <div style="padding:20px;">
            <p style="font-size:12px;color:#4b5d76;margin-bottom:14px;">Texte libre qui para&#238;t en page 3 du document. Laissez vide pour utiliser le texte par d&#233;faut.</p>
            <?php $field_ta( '', 'Texte personnalis&#233; (page Projet &amp; besoins)', 'about_project', 8, 'Suite &#224; nos &#233;changes, vous souhaitez mettre en place…' ); ?>
          </div>
        </div>

        <?php /* === SECTION 4 : Objectifs & méthodes === */ ?>
        <div class="acdc-panel acdc-mb-18">
          <div class="acdc-panel-heading" style="padding:14px 20px;border-bottom:1px solid #f0e6dc;">
            <h3 style="margin:0;font-size:13px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;">4 — Objectifs &amp; m&#233;thodes</h3>
          </div>
          <div style="padding:20px;">
            <p style="font-size:12px;color:#4b5d76;margin-bottom:14px;">Laissez vide pour utiliser les objectifs de la fiche formation.</p>
            <p style="margin-bottom:5px;">
              <label style="font-size:12px;font-weight:600;color:#0f2c52;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                <span>Objectifs p&#233;dagogiques personnalis&#233;s</span>
                <span style="display:flex;align-items:center;gap:8px;">
                  <button type="button" id="acdc-full-bloom-btn" class="acdc-button acdc-button-soft" style="height:30px;font-size:12px;padding:0 12px;"
                          onclick="acdcFullGenerateObjectives(document.getElementById('acdc-full-formation-id').value)">
                    &#9889; G&#233;n&#233;rer via IA (Bloom)
                  </button>
                  <span id="acdc-full-bloom-status" style="font-size:12px;color:#4b5d76;"></span>
                </span>
              </label>
              <textarea name="proposal[custom_objectives]" rows="6"
                        placeholder="Un objectif par ligne…"
                        style="width:100%;border-radius:10px;border:1px solid #dfe5ee;padding:10px 12px;font-size:13px;line-height:1.55;resize:vertical;margin-top:5px;"
              ><?php echo esc_textarea( $v['custom_objectives'] ); ?></textarea>
            </p>
            <?php $field_ta( '', 'M&#233;thodes p&#233;dagogiques personnalis&#233;es', 'custom_methods', 5, 'M&#233;thodes actives, cas pratiques…' ); ?>
            <?php $field_ta( '', 'Modalit&#233;s d\'&#233;valuation personnalis&#233;es', 'custom_evaluation', 4, 'Évaluation diagnostique (J1), exercices corrigés, cas final (J3)…' ); ?>
            <?php $field_s( '', 'Pr&#233;-requis personnalis&#233;s', 'custom_prerequisites', 'Compte ChatGPT, ordinateur…' ); ?>
          </div>
        </div>

        <?php /* === SECTION 5 : Programme détaillé (1 page par jour, jusqu'à 10 jours) === */ ?>
        <div class="acdc-panel acdc-mb-18">
          <div class="acdc-panel-heading" style="padding:14px 20px;border-bottom:1px solid #f0e6dc;">
            <h3 style="margin:0;font-size:13px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;">5 — Programme d&#233;taill&#233;</h3>
          </div>
          <div style="padding:20px;">
            <p style="font-size:12px;color:#4b5d76;margin-bottom:14px;">
              Le nombre de journées affich&#233;es correspond au champ <strong>Nombre de jours</strong> (Section 1).
              Laissez un champ vide pour utiliser le programme de la fiche formation.
              <strong>⚡ G&#233;n&#233;rer via IA</strong> r&#233;sume le programme en respectant le format de la page.
            </p>
            <?php for ( $__j = 1; $__j <= 10; $__j++ ) :
              $__key  = 'program_j' . $__j;
              $__show = $__j <= max( 1, (int) $v['formation_days'] );
            ?>
            <div id="acdc-prog-day-<?php echo $__j; ?>" style="<?php echo $__show ? '' : 'display:none;'; ?>border-top:1px solid #f0e6dc;padding-top:14px;margin-top:14px;">
              <p style="margin-bottom:6px;">
                <label style="font-size:12px;font-weight:600;color:#0f2c52;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                  <span>Programme Journ&#233;e <?php echo $__j; ?></span>
                  <span style="display:flex;align-items:center;gap:8px;">
                    <button type="button"
                            class="acdc-button acdc-button-soft acdc-prog-gen-btn"
                            data-day="<?php echo $__j; ?>"
                            style="height:30px;font-size:12px;padding:0 12px;">
                      &#9889; G&#233;n&#233;rer J<?php echo $__j; ?> via IA
                    </button>
                    <span class="acdc-prog-gen-status" data-day="<?php echo $__j; ?>" style="font-size:12px;color:#4b5d76;"></span>
                  </span>
                </label>
                <textarea name="proposal[<?php echo $__key; ?>]"
                          id="acdc-prog-ta-<?php echo $__j; ?>"
                          rows="8"
                          placeholder="Jour <?php echo $__j; ?> — Matinée (3h30)&#10;[Titre]&#10;• contenu 1&#10;• contenu 2&#10;Compétences développées : ...&#10;&#10;Après-midi (3h30)&#10;[Titre]&#10;• contenu"
                          style="width:100%;border-radius:10px;border:1px solid #dfe5ee;padding:10px 12px;font-size:13px;line-height:1.55;resize:vertical;margin-top:5px;font-family:monospace;"
                ><?php echo esc_textarea( $v[ $__key ] ); ?></textarea>
              </p>
            </div>
            <?php endfor; ?>
          </div>
        </div>

        <?php /* === SECTION 6 : Ressources === */ ?>
        <div class="acdc-panel acdc-mb-18">
          <div class="acdc-panel-heading" style="padding:14px 20px;border-bottom:1px solid #f0e6dc;">
            <h3 style="margin:0;font-size:13px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;">6 — Ressources compl&#233;mentaires</h3>
          </div>
          <div style="padding:20px;">
            <?php $field_ta( '', 'Ressources offertes (support, acc&#232;s, outils…)', 'extra_resources', 4, 'Support p&#233;dagogique num&#233;rique, acc&#232;s aux outils…' ); ?>
          </div>
        </div>

        <?php /* === SECTION 7 : Formateurs === */ ?>
        <div class="acdc-panel acdc-mb-18">
          <div class="acdc-panel-heading" style="padding:14px 20px;border-bottom:1px solid #f0e6dc;">
            <h3 style="margin:0;font-size:13px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;">7 &mdash; Formateurs</h3>
          </div>
          <div style="padding:20px;">
            <p style="font-size:12px;color:#4b5d76;margin-bottom:16px;">S&#233;lectionnez les formateurs qui interviendront sur cette formation. La bio affich&#233;e est celle de leur fiche &mdash; vous pouvez la modifier pour cette proposition uniquement.</p>
            <input type="hidden" id="acdc-full-trainer-bios-json" name="proposal[trainer_bios_json]" value="<?php echo esc_attr( $v['trainer_bios_json'] ); ?>">
            <?php
              $saved_bios = array();
              if ( ! empty( $v['trainer_bios_json'] ) ) {
                $decoded = json_decode( $v['trainer_bios_json'], true );
                if ( is_array( $decoded ) ) { $saved_bios = $decoded; }
              }
            ?>
            <div id="acdc-trainers-list" style="display:flex;flex-direction:column;gap:16px;">
              <?php foreach ( $trainers as $trainer ) :
                $tr_id      = (int) $trainer->id;
                $tr_name    = esc_html( trim( $trainer->first_name . ' ' . $trainer->last_name ) );
                $tr_photo   = ! empty( $trainer->photo_url ) ? esc_url( $trainer->photo_url ) : '';
                $tr_checked = in_array( $tr_id, $trainer_ids_arr, true ) ? 'checked' : '';
                $tr_default_bio = ! empty( $trainer->description_text ) ? (string) $trainer->description_text : '';
                $tr_saved_bio   = isset( $saved_bios[ $tr_id ] ) ? (string) $saved_bios[ $tr_id ] : '';
                $tr_bio         = $tr_saved_bio !== '' ? $tr_saved_bio : $tr_default_bio;
                $bio_display    = $tr_checked ? '' : 'display:none;';
              ?>
              <div class="acdc-trainer-row" data-trainer-id="<?php echo $tr_id; ?>" data-default-bio="<?php echo esc_attr( $tr_default_bio ); ?>">
                <label style="display:flex;align-items:center;gap:12px;cursor:pointer;padding:10px 12px;background:#fbf8f7;border:1px solid #f0e6dc;border-radius:10px;">
                  <?php if ( $tr_photo ) : ?>
                    <img src="<?php echo $tr_photo; ?>" alt="" style="width:48px;height:48px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                  <?php else : ?>
                    <div style="width:48px;height:48px;border-radius:50%;background:#f0e6dc;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:18px;color:#8a6d2a;font-weight:700;">
                      <?php echo esc_html( mb_strtoupper( mb_substr( $trainer->first_name, 0, 1 ) ) ); ?>
                    </div>
                  <?php endif; ?>
                  <input type="checkbox" name="proposal_trainers[]"
                         value="<?php echo $tr_id; ?>"
                         <?php echo $tr_checked; ?>
                         onchange="acdcToggleTrainerBio(this)"
                         style="width:18px;height:18px;accent-color:#8b5b23;flex-shrink:0;">
                  <span style="font-size:13px;font-weight:600;color:#0f2c52;"><?php echo $tr_name; ?></span>
                </label>
                <div class="acdc-trainer-bio-wrap" style="<?php echo $bio_display; ?>margin-top:8px;padding:0 12px;">
                  <label style="font-size:11px;font-weight:600;color:#8a6d2a;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.05em;">Bio pour cette proposition</label>
                  <textarea
                    id="acdc-trainer-bio-<?php echo $tr_id; ?>"
                    rows="5"
                    style="width:100%;border-radius:10px;border:1px solid #dfe5ee;padding:10px 12px;font-size:13px;line-height:1.55;resize:vertical;"
                    placeholder="Bio du formateur pour cette proposition…"
                  ><?php echo esc_textarea( $tr_bio ); ?></textarea>
                </div>
              </div>
              <?php endforeach; ?>
              <?php if ( empty( $trainers ) ) :
                $trainers_url = $this->portal_page_url( array( 'tab' => 'trainers' ) );
              ?>
                <p style="font-size:13px;color:#4b5d76;">
                  Aucun formateur enregistr&#233;.
                  <a href="<?php echo esc_url( $trainers_url ); ?>" style="color:#8b5b23;font-weight:600;">Cr&#233;er des formateurs &rarr;</a>
                </p>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <?php /* === SECTION 8 : Page 4 — À propos d'ACDC Formation === */ ?>
        <div class="acdc-panel acdc-mb-18">
          <div class="acdc-panel-heading" style="padding:14px 20px;border-bottom:1px solid #f0e6dc;">
            <h3 style="margin:0;font-size:13px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;">8 &mdash; Page 4&nbsp;: &#192; propos d&apos;ACDC Formation</h3>
          </div>
          <div style="padding:20px;">
            <p style="font-size:12px;color:#4b5d76;margin-bottom:10px;">Texte affich&#233; page 4 du document. <strong>Laissez vide</strong> pour utiliser automatiquement le texte des R&#233;glages. Saisissez quelque chose ici uniquement pour surcharger cette proposition sp&#233;cifiquement.</p>
            <p style="font-size:11px;color:#8a6d2a;background:#fef6e4;border:1px solid #f0e6dc;border-radius:8px;padding:8px 10px;margin-bottom:12px;">
              &#8505; Valeur actuelle des R&#233;glages&nbsp;: <?php $g = get_option('acdc_of_proposal_about_acdc','(texte par défaut intégré)'); echo esc_html( mb_substr(strip_tags($g), 0, 120) . (mb_strlen(strip_tags($g)) > 120 ? '…' : '') ); ?>
            </p>
            <?php $field_ta( '', 'Description ACDC Formation — surcharge pour cette proposition (optionnel)', 'about_acdc_text', 8, 'Laissez vide pour utiliser les Réglages…' ); ?>
          </div>
        </div>

        <?php /* === SECTION 9 : Pages 6 & 12 — Approche Avant/Pendant/Après === */ ?>
        <div class="acdc-panel acdc-mb-18">
          <div class="acdc-panel-heading" style="padding:14px 20px;border-bottom:1px solid #f0e6dc;">
            <h3 style="margin:0;font-size:13px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;">9 &mdash; Pages 6 &amp; 12&nbsp;: Approche Avant / Pendant / Apr&egrave;s</h3>
          </div>
          <div style="padding:20px;">
            <p style="font-size:12px;color:#4b5d76;margin-bottom:8px;">Un &#233;l&#233;ment par ligne. <strong>Laissez vide</strong> pour utiliser les valeurs des R&#233;glages.</p>
            <?php $field_ta( '', '&#9658; Avant la formation', 'approach_before', 4, 'Entretien préparatoire…' ); ?>
            <?php $field_ta( '', '&#9658; Pendant la formation', 'approach_during', 5, 'Formateurs expérimentés…' ); ?>
            <?php $field_ta( '', '&#9658; Apr&#232;s la formation', 'approach_after', 4, 'Bibliothèque de prompts…' ); ?>
          </div>
        </div>

        <?php /* === SECTION 10 : Page 14 — Modalités et délais d'accès === */ ?>
        <div class="acdc-panel acdc-mb-18">
          <div class="acdc-panel-heading" style="padding:14px 20px;border-bottom:1px solid #f0e6dc;">
            <h3 style="margin:0;font-size:13px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;">10 &mdash; Page 14&nbsp;: Modalit&#233;s et d&#233;lais d&apos;acc&#232;s</h3>
          </div>
          <div style="padding:20px;">
            <p style="font-size:12px;color:#4b5d76;margin-bottom:8px;">Un &#233;l&#233;ment par ligne. <strong>Laissez vide</strong> pour utiliser les valeurs des R&#233;glages.</p>
            <?php $field_ta( '', 'Ressources &#224; pr&#233;voir', 'access_resources', 4, 'Une salle équipée…' ); ?>
            <?php $field_ta( '', 'Modalit&#233;s et d&#233;lais d&apos;acc&#232;s', 'access_conditions', 5, 'Délai indicatif 30 jours…' ); ?>
          </div>
        </div>

        <!-- Barre d'actions -->
        <div style="display:flex;align-items:center;justify-content:flex-end;gap:12px;padding:16px 0 32px;">
          <a href="<?php echo esc_url( $list_url ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
          <button type="button" id="acdc-full-save-btn" class="acdc-button acdc-button-soft" onclick="acdcFullSave('brouillon')">Enregistrer le brouillon</button>
          <button type="button" id="acdc-full-save-gen-btn" class="acdc-button acdc-button-primary" onclick="acdcFullSaveAndGenerate()">
            <?php echo $this->render_inline_icon( 'file-text', 18 ); ?>
            Enregistrer &amp; g&#233;n&#233;rer le document
          </button>
        </div>

      </form>
    </div>

    <script>
    var _acdcFullNonce  = '<?php echo esc_js( $nonce ); ?>';
    var _acdcFullAjax   = '<?php echo esc_url( $ajax_url ); ?>';

    function acdcFullCalcTotal() {
      var days  = parseFloat(document.getElementById('acdc-full-days').value) || 0;
      var price = parseFloat(document.getElementById('acdc-full-price').value) || 0;
      var total = days * price;
      document.getElementById('acdc-full-total-hidden').value = total;
      document.getElementById('acdc-full-total-display').textContent = total.toLocaleString('fr-FR') + '\u00a0\u20ac';
    }

    function acdcFullGenerateObjectives(formationId) {
      if (!formationId) return;
      var ta  = document.querySelector('[name="proposal[custom_objectives]"]');
      var btn = document.getElementById('acdc-full-bloom-btn');
      var st  = document.getElementById('acdc-full-bloom-status');
      if (!ta) return;
      var prevVal = ta.value;
      ta.value = 'Génération des objectifs pédagogiques (Bloom) en cours…';
      ta.style.color = '#8a6d2a';
      ta.style.fontStyle = 'italic';
      ta.disabled = true;
      if (btn) { btn.disabled = true; }
      if (st)  { st.textContent = 'En cours…'; st.style.color = '#8a6d2a'; }
      var fd = new FormData();
      fd.append('action', 'acdc_proposal_generate_objectives');
      fd.append('nonce', _acdcFullNonce);
      fd.append('formation_id', formationId);
      fetch(_acdcFullAjax, {method:'POST', body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
          ta.disabled = false;
          ta.style.color = '';
          ta.style.fontStyle = '';
          if (btn) { btn.disabled = false; }
          if (res.success) {
            ta.value = res.data.objectives;
            ta.style.borderColor = '#35b37e';
            if (st)  { st.textContent = '✓ Objectifs générés — vous pouvez les modifier.'; st.style.color = '#35b37e'; }
            setTimeout(function(){ ta.style.borderColor = ''; }, 3000);
          } else {
            ta.value = prevVal;
            if (st)  { st.textContent = 'Erreur : ' + (res.data ? res.data.message : 'inconnue'); st.style.color = '#e06d6d'; }
          }
        })
        .catch(function(){
          ta.disabled = false;
          ta.style.color = '';
          ta.style.fontStyle = '';
          ta.value = prevVal;
          if (btn) { btn.disabled = false; }
          if (st)  { st.textContent = 'Erreur réseau.'; st.style.color = '#e06d6d'; }
        });
    }

        /* Programme — afficher/masquer jours selon formation_days */
    function acdcUpdateProgramDays() {
      var days = parseInt(document.getElementById('acdc-full-days').value) || 1;
      for (var j = 1; j <= 10; j++) {
        var el = document.getElementById('acdc-prog-day-' + j);
        if (el) el.style.display = (j <= days) ? '' : 'none';
      }
    }

    /* Programme — générer un jour via IA */
    function acdcProgGenerateDay(day, formationId) {
      if (!formationId) { alert('Sélectionnez une formation d’abord.'); return; }
      var btn = document.querySelector('.acdc-prog-gen-btn[data-day="' + day + '"]');
      var st  = document.querySelector('.acdc-prog-gen-status[data-day="' + day + '"]');
      var ta  = document.getElementById('acdc-prog-ta-' + day);
      if (!ta) return;
      var prevVal = ta.value;
      if (btn) btn.disabled = true;
      if (st)  { st.textContent = 'Génération J' + day + '…'; st.style.color = '#8a6d2a'; }
      ta.value = 'Génération en cours…';
      ta.style.color = '#8a6d2a';
      ta.style.fontStyle = 'italic';
      ta.disabled = true;
      var fd = new FormData();
      fd.append('action', 'acdc_proposal_generate_program_day');
      fd.append('nonce', _acdcFullNonce);
      fd.append('formation_id', formationId);
      fd.append('day', day);
      fetch(_acdcFullAjax, {method:'POST', body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
          ta.disabled = false;
          ta.style.color = '';
          ta.style.fontStyle = '';
          if (btn) btn.disabled = false;
          if (res.success) {
            ta.value = res.data.program;
            if (st) { st.textContent = '✓ J' + day + ' généré'; st.style.color = '#35b37e'; }
          } else {
            ta.value = prevVal;
            if (st) { st.textContent = 'Erreur : ' + (res.data ? res.data.message : 'inconnue'); st.style.color = '#e06d6d'; }
          }
        })
        .catch(function(){
          ta.disabled = false;
          ta.style.color = '';
          ta.style.fontStyle = '';
          ta.value = prevVal;
          if (btn) btn.disabled = false;
          if (st) { st.textContent = 'Erreur réseau'; st.style.color = '#e06d6d'; }
        });
    }

    /* Boutons IA programme — délégation d'événements */
    document.addEventListener('click', function(e) {
      var btn = e.target.closest('.acdc-prog-gen-btn');
      if (!btn) return;
      var day = parseInt(btn.getAttribute('data-day'));
      var fid = document.getElementById('acdc-full-formation-id').value;
      acdcProgGenerateDay(day, fid);
    });

    function acdcToggleTrainerBio(cb) {
      var row  = cb.closest('.acdc-trainer-row');
      var wrap = row ? row.querySelector('.acdc-trainer-bio-wrap') : null;
      if (!wrap) return;
      if (cb.checked) {
        wrap.style.display = '';
        /* Pré-remplir avec la bio par défaut si le textarea est vide */
        var ta = wrap.querySelector('textarea');
        if (ta && !ta.value.trim()) {
          ta.value = row.getAttribute('data-default-bio') || '';
        }
      } else {
        wrap.style.display = 'none';
      }
    }

    document.getElementById('acdc-full-formation-id').addEventListener('change', function() {
      var opt = this.options[this.selectedIndex];
      if (!opt.value) return;
      document.getElementById('acdc-full-formation-title').value = opt.getAttribute('data-title') || '';
      document.getElementById('acdc-full-thematique').value = opt.getAttribute('data-thematique') || '';
      var price = opt.getAttribute('data-price');
      if (price) { document.getElementById('acdc-full-price').value = price; }
      acdcFullCalcTotal();
      acdcUpdateProgramDays();
      acdcFullGenerateObjectives(opt.value);
      /* Auto-génération programme — une journée toutes les 1.2s pour éviter le throttle */
      var days = parseInt(document.getElementById('acdc-full-days').value) || 1;
      for (var j = 1; j <= days; j++) {
        var ta = document.getElementById('acdc-prog-ta-' + j);
        if (ta && !ta.value.trim()) {
          (function(day){ setTimeout(function(){ acdcProgGenerateDay(day, opt.value); }, (day-1)*1200); })(j);
        }
      }
    });

    function acdcFullCollect() {
      var form = document.getElementById('acdc-proposal-full-form');
      var data = {};
      form.querySelectorAll('[name^="proposal["]').forEach(function(el) {
        var key = el.name.replace('proposal[', '').replace(']', '');
        data[key] = el.value;
      });
      /* Formateurs : reconstruire trainer_ids + bios surchargeables */
      var checked = [];
      form.querySelectorAll('[name="proposal_trainers[]"]:checked').forEach(function(cb){ checked.push(cb.value); });
      data['trainer_ids'] = checked.join(',');
      /* Collecter les bios modifiées pour chaque formateur coché */
      var bios = {};
      form.querySelectorAll('[name="proposal_trainers[]"]:checked').forEach(function(cb) {
        var tid = cb.value;
        var ta = document.getElementById('acdc-trainer-bio-' + tid);
        if (ta) { bios[tid] = ta.value; }
      });
      data['trainer_bios_json'] = JSON.stringify(bios);
      return data;
    }

    function acdcFullSave(statusOverride) {
      var data = acdcFullCollect();
      if (statusOverride) { data['status'] = statusOverride; }
      var fd = new FormData();
      fd.append('action', 'acdc_proposal_save_full');
      fd.append('nonce', _acdcFullNonce);
      for (var k in data) { fd.append('proposal[' + k + ']', data[k]); }
      var btn = document.getElementById('acdc-full-save-btn');
      btn.disabled = true;
      return fetch(_acdcFullAjax, {method:'POST', body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
          btn.disabled = false;
          if (res.success) {
            alert('Brouillon enregistr\u00e9.');
            if (res.data.redirect) { window.location.href = res.data.redirect; }
          } else {
            alert('Erreur : ' + (res.data ? res.data.message : 'inconnue'));
          }
          return res;
        })
        .catch(function(){btn.disabled=false;alert('Erreur r\u00e9seau.');});
    }

    function acdcFullSaveAndGenerate() {
      var btn = document.getElementById('acdc-full-save-gen-btn');
      btn.disabled = true;
      var data = acdcFullCollect();
      data['status'] = 'envoyee';
      var fd = new FormData();
      fd.append('action', 'acdc_proposal_save_full');
      fd.append('nonce', _acdcFullNonce);
      for (var k in data) { fd.append('proposal[' + k + ']', data[k]); }
      fetch(_acdcFullAjax, {method:'POST', body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
          if (!res.success) {
            btn.disabled = false;
            alert('Erreur sauvegarde : ' + (res.data ? res.data.message : 'inconnue'));
            return;
          }
          var proposalId = res.data.id;
          var fd2 = new FormData();
          fd2.append('action', 'acdc_proposal_generate_pdf');
          fd2.append('nonce', _acdcFullNonce);
          fd2.append('proposal_id', proposalId);
          return fetch(_acdcFullAjax, {method:'POST', body:fd2})
            .then(function(r2){return r2.json();})
            .then(function(res2){
              btn.disabled = false;
              if (res2.success) {
                window.open(res2.data.pdf_url, '_blank');
                window.location.href = res.data.redirect || window.location.href;
              } else {
                alert('Sauvegard\u00e9 mais erreur g\u00e9n\u00e9ration : ' + (res2.data ? res2.data.message : 'inconnue'));
              }
            });
        })
        .catch(function(){btn.disabled=false;alert('Erreur r\u00e9seau.');});
    }

    function acdcFullGenerateAbout() {
      var btn = document.getElementById('acdc-full-ai-btn');
      var status = document.getElementById('acdc-full-ai-status');
      btn.disabled = true;
      status.textContent = 'Génération en cours…';
      var company  = document.querySelector('[name="proposal[client_company]"]').value;
      var website  = document.querySelector('[name="proposal[client_website]"]').value;
      var activity = document.querySelector('[name="proposal[client_activity]"]').value;
      var needId   = document.getElementById('acdc-full-need-id') ? document.getElementById('acdc-full-need-id').value : 0;
      var fd = new FormData();
      fd.append('action', 'acdc_proposal_generate_about');
      fd.append('nonce', _acdcFullNonce);
      fd.append('client_company', company);
      fd.append('client_website', website);
      fd.append('client_activity', activity);
      fd.append('need_id', needId || 0);
      fetch(_acdcFullAjax, {method:'POST', body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
          btn.disabled = false;
          if (res.success) {
            var aboutText   = res.data.about   || res.data.text || '';
            var projectText = res.data.project || '';
            document.querySelector('[name="proposal[client_about_text]"]').value = aboutText;
            if (projectText) {
              document.querySelector('[name="proposal[about_project]"]').value = projectText;
            }
            var msg = '✓ Texte généré';
            if (projectText) { msg += ' — « À propos » et « Projet » remplis.'; }
            else              { msg += ' — vous pouvez le modifier.'; }
            status.textContent = msg;
            status.style.color = '#35b37e';
          } else {
            status.textContent = 'Erreur : ' + (res.data ? res.data.message : 'inconnue');
            status.style.color = '#e06d6d';
          }
        })
        .catch(function(){btn.disabled=false;status.textContent='Erreur réseau.';});
    }

    /* Calcul initial au chargement */
    acdcFullCalcTotal();
    acdcUpdateProgramDays();
    /* Auto-génération objectifs Bloom si formation sélectionnée et objectifs vides */
    (function() {
      var sel = document.getElementById('acdc-full-formation-id');
      var ta  = document.querySelector('[name="proposal[custom_objectives]"]');
      if (sel && sel.value && ta && !ta.value.trim()) {
        acdcFullGenerateObjectives(sel.value);
      }
    })();
    /* Auto-génération programme au chargement si journées vides */
    (function() {
      var sel  = document.getElementById('acdc-full-formation-id');
      if (!sel || !sel.value) return;
      var days = parseInt(document.getElementById('acdc-full-days').value) || 1;
      for (var j = 1; j <= days; j++) {
        var ta = document.getElementById('acdc-prog-ta-' + j);
        if (ta && !ta.value.trim()) {
          (function(day){ setTimeout(function(){ acdcProgGenerateDay(day, sel.value); }, (day-1)*1200 + 800); })(j);
        }
      }
    })();
    /* ACDC 3.25.67 — Prix global mémorisé + recalcul tarif/jour + fix fermeture backdrop */
    var acdcFullTotalPriceGlobal = 0;
    (function() {
      var sel = document.getElementById('acdc-full-formation-id');
      if ( sel && sel.value ) {
        var opt = sel.options[sel.selectedIndex];
        acdcFullTotalPriceGlobal = parseFloat( opt.getAttribute('data-total-price') || opt.getAttribute('data-price') || '0' );
      }
      var daysEl = document.getElementById('acdc-full-days');
      if ( daysEl ) {
        daysEl.addEventListener('input', function() {
          if ( acdcFullTotalPriceGlobal > 0 ) {
            var d = parseFloat(this.value) || 1;
            document.getElementById('acdc-full-price').value = Math.round( acdcFullTotalPriceGlobal / d );
          }
          acdcFullCalcTotal();
          acdcUpdateProgramDays();
        });
      }
      var formSel2 = document.getElementById('acdc-full-formation-id');
      if ( formSel2 ) {
        formSel2.addEventListener('change', function() {
          var o = this.options[this.selectedIndex];
          acdcFullTotalPriceGlobal = parseFloat( o.getAttribute('data-total-price') || o.getAttribute('data-price') || '0' );
          var durDays = parseInt( o.getAttribute('data-duration-days') || '0' );
          var dF = document.getElementById('acdc-full-days');
          if ( durDays > 0 && dF ) { dF.value = durDays; }
          if ( acdcFullTotalPriceGlobal > 0 ) {
            var d = parseFloat( dF ? dF.value : 1 ) || 1;
            document.getElementById('acdc-full-price').value = Math.round( acdcFullTotalPriceGlobal / d );
          }
        });
      }
      var backdrop = document.getElementById('acdc-proposal-backdrop');
      if ( backdrop ) {
        var mdOnBackdrop = false;
        backdrop.addEventListener('mousedown', function(e) { mdOnBackdrop = (e.target === backdrop); });
        backdrop.addEventListener('mouseup', function(e) {
          if ( mdOnBackdrop && e.target === backdrop ) { acdcCloseProposalModal(); }
          mdOnBackdrop = false;
        });
      }
    })();
    </script>
    <?php
  }


  /* ---------------------------------------------------------------
   * PDF mPDF — Token sécurisé + URL de téléchargement
   * --------------------------------------------------------------- */
  private function build_proposal_pdf_token( $proposal_id ) {
    $proposal = $this->get_proposal( (int) $proposal_id );
    $seed     = 'proposal_pdf|' . (int) $proposal_id . '|' . ( $proposal && ! empty( $proposal->created_at ) ? (string) $proposal->created_at : '' );
    return hash_hmac( 'sha256', $seed, wp_salt( 'auth' ) );
  }

  public function get_proposal_pdf_url( $proposal_id, $disposition = 'inline' ) {
    return add_query_arg( array(
      'action'      => 'acdc_download_proposal_pdf',
      'proposal_id' => (int) $proposal_id,
      'token'       => $this->build_proposal_pdf_token( $proposal_id ),
      'dl'          => 'attachment' === $disposition ? '1' : '0',
    ), admin_url( 'admin-post.php' ) );
  }

  /* ---------------------------------------------------------------
   * Handler public — Télécharger/afficher la proposition en PDF
   * --------------------------------------------------------------- */
  public function handle_download_proposal_pdf() {
    $proposal_id = isset( $_GET['proposal_id'] ) ? absint( $_GET['proposal_id'] ) : 0;
    $token       = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
    if ( ! $proposal_id || '' === $token ) {
      wp_die( 'Lien invalide.' );
    }
    if ( ! hash_equals( $this->build_proposal_pdf_token( $proposal_id ), $token ) ) {
      wp_die( 'Lien de téléchargement invalide.' );
    }
    $proposal = $this->get_proposal( $proposal_id );
    if ( ! $proposal ) {
      wp_die( 'Proposition introuvable.' );
    }
    /* Servir directement le HTML viewer (client_mode) — rendu Chrome identique au viewer */
    $html = $this->build_proposal_client_html( $proposal );
    header( 'Content-Type: text/html; charset=UTF-8' );
    header( 'Cache-Control: no-cache, no-store, must-revalidate' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    echo $html;
    exit;
  }

  /* ---------------------------------------------------------------
   * Construire le HTML mPDF de la proposition
   * --------------------------------------------------------------- */
  private function build_proposal_client_html( $proposal ) {
    $p        = $proposal;
    $formation= ! empty( $p->formation_id ) ? $this->get_formation( (int) $p->formation_id ) : null;
    if ( ! $formation ) {
      $formation = (object) array(
        'catalog_image_url'=>'','specialty'=>'','objectives'=>'','program'=>'',
        'prerequisites'=>'','modality'=>'','description_text'=>'','ressources'=>'',
        'moyens_pedago'=>'','catalog_audience'=>'','thematique'=>'',
      );
    }
    $trainer_ids_raw = ! empty( $p->trainer_ids ) ? (string) $p->trainer_ids : '';
    $trainer_ids     = array_filter( array_map( 'absint', explode( ',', $trainer_ids_raw ) ) );
    $trainers        = array();
    if ( ! empty( $trainer_ids ) ) {
      global $wpdb;
      $placeholders = implode( ',', array_fill( 0, count( $trainer_ids ), '%d' ) );
      $trainers = (array) $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM {$this->trainer_table} WHERE id IN ($placeholders) ORDER BY FIELD(id, $placeholders)", ...$trainer_ids, ...$trainer_ids )
      );
    }
    $trainer_bios = array();
    if ( ! empty( $p->trainer_bios_json ) ) {
      $decoded = json_decode( (string) $p->trainer_bios_json, true );
      if ( is_array( $decoded ) ) $trainer_bios = $decoded;
    }
    $logo_url         = get_option( 'acdc_of_logo_url', '' ) ?: 'https://acdcformation.com/wp-content/uploads/2026/03/Logo-ACDC.png';
    $logo_favicon_url = 'https://acdcformation.com/wp-content/uploads/2026/05/Favicon.png';
    $company_profile  = get_option( 'acdc_of_company_profile', array() );
    $acdc = array(
      'name'         => ! empty( $company_profile['company_name'] ) ? $company_profile['company_name'] : 'ACDC Formation',
      'address'      => ! empty( $company_profile['address'] )      ? $company_profile['address']      : '7 avenue Paul Cézanne',
      'city'         => ! empty( $company_profile['city'] )         ? $company_profile['city']         : '83310 Cogolin',
      'siret'        => ! empty( $company_profile['siret'] )        ? $company_profile['siret']        : '',
      'nda'          => ! empty( $company_profile['nda_number'] )   ? $company_profile['nda_number']   : '93 83 08347 83',
      'email'        => ! empty( $company_profile['email'] )        ? $company_profile['email']        : 'dcontal@acdc-formation.com',
      'phone'        => ! empty( $company_profile['phone'] )        ? $company_profile['phone']        : '06 78 26 91 10',
      'website'      => ! empty( $company_profile['website'] )      ? $company_profile['website']      : 'https://acdc-formation.com',
      'contact_name' => ! empty( $company_profile['contact_name'] ) ? $company_profile['contact_name'] : 'David Contal',
      'logo_url'     => $logo_url,
      'logo_favicon_url' => $logo_favicon_url,
    );
    $date_prop   = wp_date( 'j F Y' );
    $total_hours = (int) $p->formation_days * (int) $p->formation_hours_per_day;
    $prog_days   = max( 1, min( 10, (int) $p->formation_days ) );
    ob_start();
    $client_mode = true;
    include ACDC_OF_SAAS_DIR . 'includes/proposals/templates/proposal-html.php';
    return ob_get_clean();
  }
}
