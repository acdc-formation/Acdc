<?php
/**
 * ACDC Documents / devis / factures — ACDC_Documents_Billing_Render_Trait.
 *
 * Extraction micro-incrémentale du sous-bloc métier :
 * documents / devis / factures.
 *
 * @since 3.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Documents_Billing_Render_Trait {

  private function render_front_quotes_tab() {
    $scope = $this->get_quotes_scope();
    $action = $this->get_quotes_action();
    $quote_id = isset( $_GET['quote_id'] ) ? absint( $_GET['quote_id'] ) : 1;

    if ( '' === $scope ) {
      $this->render_front_quotes_hub();
      return;
    }

    if ( 'create' === $action ) {
      $this->render_front_quote_form( $scope, null, false );
      return;
    }

    if ( 'view' === $action ) {
      $this->render_front_quote_view( $scope, $quote_id );
      return;
    }

    if ( 'edit' === $action ) {
      $quote_real = $this->get_quote( $quote_id );
      $this->render_front_quote_form( $scope, $quote_real ? $this->build_quote_row_from_record( $quote_real ) : null, true );
      return;
    }

    if ( 'preview' === $action ) {
      $this->render_front_quote_preview( $scope, $quote_id );
      return;
    }

    $this->render_front_quotes_list( $scope );
  }

  private function render_front_quotes_hub() {
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'quotes' ) );
    ?>
    <section class="acdc-section-head" style="align-items:flex-start;"><div><h2>Devis</h2></div></section>
    <div class="acdc-grid-2cols" style="gap:18px;">
      <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'quotes', 'scope' => 'action' ), $base_url ) ); ?>" class="acdc-panel" style="min-height:98px;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;text-decoration:none;">
        <h3 style="margin:0 0 10px;color:#C5A253;font-size:18px;">Actions de formation</h3>
        <p style="margin:0;color:#1E4777;">Devis</p>
      </a>
      <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'quotes', 'scope' => 'ancillary' ), $base_url ) ); ?>" class="acdc-panel" style="min-height:98px;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;text-decoration:none;">
        <h3 style="margin:0 0 10px;color:#C5A253;font-size:18px;">Prestations annexes</h3>
        <p style="margin:0;color:#1E4777;">Devis</p>
      </a>
    </div>
    <?php
  }

  private function render_front_quotes_list( $scope ) {
    $base_url    = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'quotes' ) );
    $back_url    = add_query_arg( array( 'tab' => 'quotes' ), $base_url );
    $scope_label = 'ancillary' === $scope ? 'Prestations annexes' : 'Actions de formation';
    $create_url  = add_query_arg( array( 'tab' => 'quotes', 'scope' => $scope, 'quote_action' => 'create' ), $base_url );
    $rows_raw    = $this->get_quotes( array( 'scope' => $scope ) );
    $rows        = array();
    foreach ( $rows_raw as $q ) {
      $rows[] = $this->build_quote_row_from_record( $q );
    }
    ?>
    <section class="acdc-section-head">
      <div><h2>Devis : <?php echo esc_html( $scope_label ); ?></h2></div>
      <a href="<?php echo esc_url( $create_url ); ?>" class="acdc-button acdc-button-primary">+ Créer un devis</a>
    </section>
    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
        <input type="hidden" name="tab" value="quotes">
        <input type="hidden" name="scope" value="<?php echo esc_attr( $scope ); ?>">
        <div class="acdc-inline-wrap acdc-inline-wrap-center">
          <input type="search" name="q" value="" placeholder="Rechercher" style="max-width:420px;">
          <button type="submit" class="acdc-button acdc-button-soft">Rechercher</button>
        </div>
      </form>
    </div>
    <div class="acdc-panel">
      <div class="acdc-table-wrap">
        <table class="acdc-table acdc-quotes-table" data-acdc-table-id="quotes-list">
          <thead>
            <tr>
              <th>NUMÉRO</th>
              <th>COMMANDITAIRE</th>
              <th>STATUT</th>
              <th>DERNIÈRE RELANCE</th>
              <th>FORMATION</th>
              <th>DATE D’ÉMISSION</th>
              <th>DATE D’EXPIRATION</th>
              <th>QUANTITÉ</th>
              <th>TARIF HT</th>
              <th>MONTANT TVA</th>
              <th>TARIF TTC</th>
              <th>ACTIONS</th>
            </tr>
          </thead>
          <tbody>
          <?php if ( ! empty( $rows ) ) : ?>
            <?php foreach ( $rows as $row ) :
              $view_url     = add_query_arg( array( 'tab' => 'quotes', 'scope' => $scope, 'quote_action' => 'view',      'quote_id' => (int) $row['id'] ), $base_url );
              $edit_url     = add_query_arg( array( 'tab' => 'quotes', 'scope' => $scope, 'quote_action' => 'edit',      'quote_id' => (int) $row['id'] ), $base_url );
              $dup_url      = add_query_arg( array( 'tab' => 'quotes', 'scope' => $scope, 'quote_action' => 'create',    'duplicate_id' => (int) $row['id'] ), $base_url );
              $download_url = $this->secure_admin_post_url( 'acdc_download_quote_document', array( 'quote_id' => (int) $row['id'] ), 'acdc_download_quote_document_' . (int) $row['id'] );
              $delete_url   = wp_nonce_url(
                admin_url( 'admin-post.php?action=acdc_delete_quote&quote_id=' . (int) $row['id'] ),
                'acdc_delete_quote_' . (int) $row['id']
              );
              $menu_items_top = array(
                'Dupliquer'            => $dup_url,
                'Télécharger' => $download_url,
                'Envoyer en signature' => $this->secure_admin_post_url( 'acdc_send_quote_for_signature', array( 'quote_id' => (int) $row['id'] ), 'acdc_send_quote_for_signature_' . (int) $row['id'], 'POST' ),
                'Importer un devis signé' => '#acdc-import-signed-quote-modal',
                'Relancer'             => '#acdc-relance-quote-modal',
                'Supprimer'            => 'confirm:' . $delete_url,
              );
              $status_links = array();
              foreach ( $this->get_quote_status_labels() as $sk => $slbl ) {
                $status_links[ $slbl ] = wp_nonce_url(
                  admin_url( 'admin-post.php?action=acdc_set_quote_status&quote_id=' . (int) $row['id'] . '&status=' . rawurlencode( $sk ) ),
                  'acdc_set_quote_status_' . (int) $row['id'] . '_' . $sk
                );
              }
            ?>
            <tr>
              <td><?php echo esc_html( $row['number'] ); ?></td>
              <td>
                <?php echo esc_html( $row['commanditaire_type'] ?: '—' ); ?><br>
                <strong style="color:#C5A253;"><?php echo esc_html( $row['commanditaire_name'] ?: '—' ); ?></strong>
              </td>
              <td><span class="acdc-status-badge"><?php echo esc_html( $row['status'] ?? 'Brouillon' ); ?></span></td>
              <td><?php echo esc_html( $row['relance_date'] ?: 'Aucune relance' ); ?><?php if ( ! empty( $row['relance_count'] ) ) : ?><br><small>(<?php echo esc_html( (string) $row['relance_count'] ); ?> relance)</small><?php endif; ?></td>
              <td><span style="color:#C5A253;font-weight:600;"><?php echo esc_html( $row['formation'] ?: '—' ); ?></span></td>
              <td><?php echo esc_html( $row['emission_date'] ?: '—' ); ?></td>
              <td><?php echo esc_html( $row['expiration_date'] ?: '—' ); ?></td>
              <td><?php echo esc_html( $row['quantity'] ?: '—' ); ?></td>
              <td><?php echo esc_html( $row['tarif_ht'] ?: '—' ); ?></td>
              <td><?php echo esc_html( $row['montant_tva'] ?: '—' ); ?></td>
              <td><?php echo esc_html( $row['tarif_ttc'] ?: '—' ); ?></td>
              <td class="acdc-actions-cell-icons">
                <div class="acdc-groups-actions-inline" data-acdc-prospect-actions>
                  <div class="acdc-prospect-action-menu">
                    <button type="button"
                            class="acdc-row-action-icon acdc-prospect-action-trigger"
                            data-acdc-prospect-menu-toggle
                            data-acdc-iconized="1"
                            aria-haspopup="true"
                            aria-expanded="false"
                            aria-label="Plus d'actions"
                            title="Plus d'actions">
                      <?php echo $this->render_inline_icon( 'more-horizontal', 25 ); ?>
                      <span class="acdc-action-hub-sr screen-reader-text">Plus d'actions</span>
                    </button>
                    <div class="acdc-prospect-action-dropdown" data-acdc-prospect-menu hidden>
                      <?php foreach ( $menu_items_top as $label => $url ) : ?>
                        <?php if ( '#' === substr( $url, 0, 1 ) ) : ?>
                          <a href="#" data-acdc-modal-open="<?php echo esc_attr( ltrim( $url, '#' ) ); ?>" class="acdc-prospect-action-item"><?php echo esc_html( $label ); ?></a>
                        <?php elseif ( 'confirm:' === substr( $url, 0, 8 ) ) : ?>
                          <a href="<?php echo esc_url( substr( $url, 8 ) ); ?>" class="acdc-prospect-action-item" style="color:#c0392b;" onclick="return confirm('Supprimer ce devis ? Cette action est irréversible.');">&#x1F5D1; <?php echo esc_html( $label ); ?></a>
                        <?php else : ?>
                          <a href="<?php echo esc_url( $url ); ?>" class="acdc-prospect-action-item"><?php echo esc_html( $label ); ?></a>
                        <?php endif; ?>
                      <?php endforeach; ?>
                      <div style="border-top:1px solid #e5eaf2;margin:4px 0;padding-top:4px;">
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#8896ab;padding:4px 12px 2px;">Statut</div>
                        <?php foreach ( $status_links as $slbl => $surl ) : ?>
                          <a href="<?php echo esc_url( $surl ); ?>" class="acdc-prospect-action-item" style="padding:7px 12px;font-size:13px;"><?php echo esc_html( $slbl ); ?></a>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                  <a href="<?php echo esc_url( $view_url ); ?>"
                     class="acdc-row-action-icon acdc-row-view-link"
                     data-acdc-iconized="1"
                     title="Voir le devis"
                     aria-label="Voir">
                    <?php echo $this->render_inline_icon( 'view', 25 ); ?>
                    <span class="acdc-action-hub-sr screen-reader-text">Voir</span>
                  </a>
                  <a href="<?php echo esc_url( $edit_url ); ?>"
                     class="acdc-row-action-icon acdc-row-edit-link"
                     data-acdc-iconized="1"
                     title="Modifier le devis"
                     aria-label="Modifier">
                    <?php echo $this->render_inline_icon( 'edit-pencil', 25 ); ?>
                    <span class="acdc-action-hub-sr screen-reader-text">Modifier</span>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr><td colspan="12">Aucun devis enregistré.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php $this->render_quote_import_signed_modal(); ?>
    <?php $this->render_quote_relance_modal(); ?>
    <?php $this->render_quote_status_modal(); ?>
    <style>
      .acdc-quotes-table th{font-size:11px}
      .acdc-quotes-table td{vertical-align:middle}
    </style>
    <?php
  }

  private function render_quote_import_signed_modal() {
    ?>
    <div class="acdc-modal-shell" id="acdc-import-signed-quote-modal" hidden>
      <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
      <div class="acdc-modal-dialog acdc-modal-dialog-large">
        <div class="acdc-modal-header"><h4>IMPORTER UN DEVIS SIGNÉ</h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
        <div class="acdc-modal-body">
          <div class="acdc-pad-24">
            <p class="acdc-modal-title-strong">ESPACE UPLOAD DOCUMENT <span class="acdc-required">*</span></p>
            <div class="acdc-upload-box"><button type="button" class="acdc-button acdc-button-primary">Choisir le fichier</button><div>Déposez le fichier ou cliquez pour choisir</div></div>
            <p style="border-top:1px solid #e8edf4;margin:26px 0 0;padding-top:24px;color:#6d7c95;font-style:italic;">Ajoutez un document au format PDF, Word ou une image</p>
          </div>
          <div class="acdc-actions-end" style="padding:22px 24px;"><button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button><button type="button" class="acdc-button acdc-button-primary">Exécuter l'action</button></div>
        </div>
      </div>
    </div>
    <?php
  }

  private function render_quote_relance_modal() {
    $logo = 'https://acdc-formation.com/wp-content/uploads/2025/06/cropped-cropped-Logo-ACDC-500x500-1.png';
    ?>
    <div class="acdc-modal-shell" id="acdc-relance-quote-modal" hidden>
      <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
      <div class="acdc-modal-dialog acdc-modal-dialog-email">
        <div class="acdc-modal-header"><h4>RELANCER</h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
        <div class="acdc-modal-body acdc-pad-24">
          <p>Êtes-vous sûr·e de vouloir exécuter cette action ?</p>
          <p style="margin:22px 0 10px;color:#1E4777;">Aperçu de l'e-mail</p>
          <div class="acdc-editor-toolbar">
            <span class="acdc-editor-tool">B</span><span class="acdc-editor-tool"><em>I</em></span><span class="acdc-editor-tool"><u>U</u></span><span class="acdc-editor-tool">S</span><span class="acdc-editor-tool">A</span><span class="acdc-editor-tool">🙂</span><span class="acdc-editor-tool">🖼</span><span class="acdc-editor-tool">≡</span><span class="acdc-editor-tool">▦</span><span class="acdc-editor-tool">🔗</span>
          </div>
          <div class="acdc-email-preview">
            <div class="acdc-email-preview-inner">
              <div class="acdc-email-hero">
                <img src="<?php echo $logo; ?>" alt="ACDC Formation" style="max-width:130px;height:auto;display:block;margin:0 auto 12px;">
                <div style="font-size:44px;font-weight:800;color:#203b74;">ACDC Formation</div>
                <div style="font-size:19px;letter-spacing:.04em;color:#516c9b;">AZUR COMPÉTENCES DÉVELOPPEMENT &amp; CONSEIL</div>
              </div>
              <div style="padding:34px 42px 12px;font-size:18px;line-height:1.65;">
                <p>Bonjour <strong>Chloé</strong>,</p>
                <p>Sauf erreur de notre part, vous n'avez toujours pas signé le devis numéro <strong>DE-2026-1</strong>.</p>
                <p>Nous nous permettons donc de vous le renvoyer pour que vous puissiez nous le retourner signé.</p>
              </div>
              <div class="acdc-email-box">
                <p style="margin:0 0 20px;text-align:center;">Nous restons à votre entière disposition au besoin. 🙂</p>
                <p style="margin:0;text-align:center;font-weight:700;">ACDC FORMATION<br>06 78 26 91 10<br>contact@acdc-formation.com<br>acdcformation.com</p>
              </div>
              <div style="padding:0 42px 34px;font-size:18px;line-height:1.65;">
                <p>Cordialement,<br><strong>L’équipe ACDC Formation</strong></p>
              </div>
              <div style="border-top:1px solid #dfe6f0;padding:22px 24px 30px;text-align:center;color:#64779d;font-size:14px;line-height:1.75;">06 78 26 91 10 · contact@acdc-formation.com · acdcformation.com<br>7 avenue Paul Cézanne — 83310 Cogolin<br>Ce message a été envoyé dans le cadre du suivi de votre devis.</div>
            </div>
          </div>
          <p class="acdc-actions-end"><button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button><button type="button" class="acdc-button acdc-button-primary">Exécuter l'action</button></p>
        </div>
      </div>
    </div>
    <?php
  }

  private function render_quote_status_modal() {
    ?>
    <div class="acdc-modal-shell" id="acdc-status-quote-modal" hidden>
      <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
      <div class="acdc-modal-dialog acdc-modal-dialog-mediumwide">
        <div class="acdc-modal-header"><h4>MODIFIER LE STATUT</h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
        <div class="acdc-modal-body acdc-pad-24">
          <div class="acdc-modal-form-grid">
            <label>Nouveau statut <span class="acdc-required">*</span></label>
            <div>
              <select name="new_status" id="acdc-status-quote-select" required>
                <option value="">— Choisir un statut —</option>
                <?php foreach ( $this->get_quote_status_labels() as $k => $lbl ) : ?>
                  <option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $lbl ); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <label>Détails</label>
            <div><textarea rows="5" placeholder="Détails"></textarea></div>
          </div>
          <p class="acdc-actions-end"><button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button><button type="button" class="acdc-button acdc-button-primary">Exécuter l'action</button></p>
        </div>
      </div>
    </div>
    <?php
  }

  private function render_front_quote_view( $scope, $quote_id ) {
    $q_real = $this->get_quote( $quote_id );
    $row = $q_real ? $this->build_quote_row_from_record( $q_real ) : array();
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'quotes' ) );
    $back_url = add_query_arg( array( 'tab' => 'quotes', 'scope' => $scope ), $base_url );
    $edit_url = add_query_arg( array( 'tab' => 'quotes', 'scope' => $scope, 'quote_action' => 'edit', 'quote_id' => (int) $row['id'] ), $base_url );
    $download_url = $this->secure_admin_post_url( 'acdc_download_quote_document', array( 'quote_id' => (int) $row['id'] ), 'acdc_download_quote_document_' . (int) $row['id'] );
    $preview_url = add_query_arg( array( 'tab' => 'quotes', 'scope' => $scope, 'quote_action' => 'preview', 'quote_id' => (int) $row['id'] ), $base_url );
    ?>
    <div class="acdc-mb-18"><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $back_url ); ?>" style="min-width:44px;padding:10px 14px;">←</a></div>
    <?php if ( ! $this->is_documents_billing_demo_enabled() ) : ?>
    <div style="margin-bottom:16px;">
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
        <?php wp_nonce_field( 'acdc_convert_quote_to_invoice_' . (int) $row['id'] ); ?>
        <input type="hidden" name="action" value="acdc_convert_quote_to_invoice">
        <input type="hidden" name="quote_id" value="<?php echo esc_attr( (int) $row['id'] ); ?>">
        <button type="submit" class="acdc-button acdc-button-primary" onclick="return confirm('Créer une facture depuis ce devis ?');">🧾 Convertir en facture</button>
      </form>
    </div>
    <?php endif; ?>
    <div style="display:flex;justify-content:flex-end;gap:10px;margin-bottom:14px;">
      <a href="<?php echo esc_url( $edit_url ); ?>" class="acdc-button acdc-button-soft">Modifier</a>
      <a href="<?php echo esc_url( $back_url ); ?>" class="acdc-button acdc-button-soft">← Retour</a>
    </div>
    <div class="acdc-panel acdc-profile-section"><h3>Commanditaire</h3><div class="acdc-details-grid"><div>Apprenant</div><div style="color:#C5A253;font-weight:700;"><?php echo esc_html( $row['apprenant'] ); ?></div><div>Adresse</div><div><?php echo esc_html( $row['address'] ); ?></div><div>Complément d'adresse</div><div><?php echo esc_html( $row['address_complement'] ); ?></div><div>Code postal</div><div><?php echo esc_html( $row['postal_code'] ); ?></div><div>Ville</div><div><?php echo esc_html( $row['city'] ); ?></div></div></div>
    <div class="acdc-panel acdc-profile-section"><h3>Formation</h3><div class="acdc-details-grid"><div>Formation</div><div style="color:#C5A253;font-weight:700;"><?php echo esc_html( $row['formation_full'] ); ?></div><div>Format</div><div><?php echo esc_html( $row['format'] ); ?></div><div>Adresse</div><div><?php echo esc_html( $row['formation_address'] ); ?></div><div>Code postal</div><div><?php echo esc_html( $row['formation_postal_code'] ); ?></div><div>Ville</div><div><?php echo esc_html( $row['formation_city'] ); ?></div><div>Durée</div><div><?php echo esc_html( $row['duration'] ); ?></div><div>Date de début</div><div><?php echo esc_html( $row['start_date'] ); ?></div><div>Date de fin</div><div><?php echo esc_html( $row['end_date'] ); ?></div><div>Effectif formé</div><div><?php echo esc_html( $row['trained_headcount'] ); ?></div></div></div>
    <div class="acdc-panel acdc-profile-section"><h3>Devis</h3><div class="acdc-details-grid"><div>Télécharger</div><div><a href="<?php echo esc_url( $download_url ); ?>" class="acdc-file-chip">📄 Devis <?php echo esc_html( $row['number'] ); ?>.html</a></div><div>Statut</div><div><span class="acdc-status-badge"><?php echo esc_html( $row['status'] ); ?></span></div><div>Préfixe</div><div><?php echo esc_html( $row['prefix'] ); ?></div><div>Numéro</div><div><?php echo esc_html( $row['num'] ); ?></div><div>Date d'émission</div><div><?php echo esc_html( $row['emission_date'] ); ?></div><div>Date d'expiration</div><div><?php echo esc_html( $row['expiration_date'] ); ?></div><div>Désignation</div><div><a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener">Afficher le contenu</a></div><div>Quantité</div><div><?php echo esc_html( $row['quantity'] ); ?></div><div>Tarif de l'action de formation (€ HT)</div><div><?php echo esc_html( $row['tarif_ht'] ); ?></div><div>Taux de TVA (%)</div><div>20.00%</div><div>Frais de transport</div><div>⛔</div><div>Frais de restauration et / ou hébergement</div><div>⛔</div><div>Lignes supplémentaires</div><div>—</div><div>Tarif de l'action de formation (€ TTC)</div><div><?php echo esc_html( $row['tarif_ttc'] ); ?></div><div>Adresse d'émission</div><div><a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener">Afficher le contenu</a></div><div>Méthodes de paiement</div><div><a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener">Afficher le contenu</a></div><div>Commentaire (n'apparaît pas sur le devis)</div><div>—</div><div>Pièce jointe</div><div>—</div><div>Programme de formation</div><div>⛔</div><div>CGV</div><div>⛔</div></div></div>
    <div class="acdc-panel acdc-profile-section"><h3>Voir un devis : <?php echo esc_html( $row['number'] ); ?></h3><div class="acdc-details-grid"><div>Créé le</div><div><?php echo esc_html( $row['emission_date'] ); ?></div><div>Modifié le</div><div><?php echo esc_html( $row['emission_date'] ); ?></div></div></div>
    <?php
    // ACDC 3.24.98 — Bloc signature devis
    $q_real_sig = $this->get_quote( $quote_id );
    $sig_request_id_v = ( $q_real_sig && ! empty( $q_real_sig->signature_request_id ) ) ? (int) $q_real_sig->signature_request_id : 0;
    $sig_status_v     = ( $q_real_sig && ! empty( $q_real_sig->signature_status ) ) ? (string) $q_real_sig->signature_status : '';
    $send_sig_url     = $this->secure_admin_post_url( 'acdc_send_quote_for_signature', array( 'quote_id' => (int) $row['id'] ), 'acdc_send_quote_for_signature_' . (int) $row['id'] );
    ?>
    <div class="acdc-panel acdc-profile-section">
      <h3>Signature électronique</h3>
      <div class="acdc-details-grid">
        <div>Statut</div>
        <div>
          <?php if ( 'signée' === $sig_status_v ) : ?>
            <span class="acdc-status-badge" style="background:#e6f4ea;color:#276a2e;">✅ Signé</span>
          <?php elseif ( 'envoyée' === $sig_status_v ) : ?>
            <span class="acdc-status-badge" style="background:#fff8e1;color:#856404;">⏳ En attente de signature</span>
          <?php else : ?>
            <span class="acdc-status-badge" style="background:#f1f3f5;color:#555;">Non envoyé</span>
          <?php endif; ?>
        </div>
        <?php if ( $sig_request_id_v ) : ?>
        <div>Référence demande</div><div>#<?php echo esc_html( $sig_request_id_v ); ?></div>
        <?php endif; ?>
        <div>Action</div>
        <div>
          <?php if ( 'signée' !== $sig_status_v ) : ?>
            <?php if ( '' === $sig_status_v ) : ?>
              <a href="<?php echo esc_url( $send_sig_url ); ?>" class="acdc-button acdc-button-primary" onclick="return confirm('Envoyer ce devis en signature électronique à <?php echo esc_attr( $row['apprenant_email'] ?? '' ); ?> ?');">✉️ Envoyer en signature</a>
            <?php else : ?>
              <a href="<?php echo esc_url( $send_sig_url ); ?>" class="acdc-button acdc-button-soft" onclick="return confirm('Une demande est déjà active. Renvoyer quand même ?');">🔁 Renvoyer</a>
            <?php endif; ?>
          <?php else : ?>
            <span style="color:#276a2e;font-weight:600;">Document signé — preuve dans Archives signatures</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <style>.acdc-details-grid{display:grid;grid-template-columns:430px 1fr;gap:18px 26px}.acdc-file-chip{display:inline-flex;align-items:center;gap:10px;padding:12px 14px;border-radius:10px;background:#8ea2c0;color:#0B0706;text-decoration:none;min-width:100%;max-width:820px}.acdc-file-chip:hover{opacity:.92;color:#0B0706}@media(max-width:900px){.acdc-details-grid{grid-template-columns:1fr}}</style>
    <?php
  }

  private function render_front_quote_form( $scope, $row = null, $is_edit = false ) {
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'quotes' ) );
    $back_url = add_query_arg( array( 'tab' => 'quotes', 'scope' => $scope ), $base_url );
    if ( ! $row ) {
      $branding_q  = $this->get_branding_options();
      $profile_q   = $this->get_company_profile_options();
      $row = array(
        'id' => 0, 'scope' => $scope, 'number' => $this->get_quote_next_number( $scope ),
        'commanditaire_type' => '', 'apprenant' => '', 'client_company' => '',
        'formation_full' => '', 'formation' => '', 'formation_title' => '',
        'start_date' => '', 'end_date' => '', 'trained_headcount' => '',
        'emission_date' => wp_date( 'd/m/Y' ), 'expiration_date' => wp_date( 'd/m/Y', strtotime( '+' . max( 1, (int) ( $this->get_billing_settings_options()['quotes']['validity_days'] ?? 30 ) ) . ' days' ) ),
        'tarif_ht_value' => '', 'tarif_ttc_value' => '0', 'vat_rate' => '20,00',
        'quantity' => '1,00', 'designation' => "Dates de l'action de formation : à définir",
        'format' => 'Présentiel', 'validity_days' => '30',
        'vat_rate' => ( isset( $profile_q['vat_rate_default'] ) && '20' === (string) $profile_q['vat_rate_default'] ) ? '20,00' : '0,00',
        'iban' => ! empty( $profile_q['bank_iban'] ) ? (string) $profile_q['bank_iban'] : 'FR76 3000 4023 7500 0101 1397 203',
        'bic'  => ! empty( $profile_q['bank_bic'] )  ? (string) $profile_q['bank_bic']  : 'BNPAFRPPXXX',
        'payment_methods' => "Règlement par virement bancaire à l'édition de la facture.",
        'address' => '', 'address_complement' => '', 'postal_code' => '', 'city' => '',
        'formation_address' => '', 'formation_postal_code' => '', 'formation_city' => '',
        'duration' => '', 'objectives' => '', 'location' => '',
        'transport_fees_enabled' => 0, 'transport_fees_ht' => '0,00',
        'meal_fees_enabled' => 0, 'meal_fees_ht' => '0,00',
        'html_url' => '', 'status' => 'brouillon',
        'source_prospect_id' => isset( $_GET['prospect_id'] ) ? absint( wp_unslash( $_GET['prospect_id'] ) ) : 0,
        'proposal_id' => isset( $_GET['proposal_id'] ) ? absint( wp_unslash( $_GET['proposal_id'] ) ) : 0,
      );

      /* Préremplir depuis la proposition si proposal_id est passé en GET */
      $prefill_proposal_id = $row['proposal_id'];
      if ( $prefill_proposal_id && method_exists( $this, 'get_proposal' ) ) {
        $prefill_proposal = $this->get_proposal( $prefill_proposal_id );
        if ( $prefill_proposal ) {
          $is_company_type = ! empty( $prefill_proposal->client_siret ) || ! empty( $prefill_proposal->client_company );
          $row['commanditaire_type']  = $is_company_type ? 'Entreprise' : 'Particulier';
          $row['apprenant']           = (string) $prefill_proposal->client_name;
          $row['apprenant_email']     = (string) ( $prefill_proposal->client_email ?? '' );
          $row['client_company']      = (string) $prefill_proposal->client_company;
          $row['address']             = (string) ( $prefill_proposal->client_address ?? '' );
          $row['postal_code']         = (string) ( $prefill_proposal->client_postal_code ?? '' );
          $row['city']                = (string) ( $prefill_proposal->client_city ?? '' );
          $row['formation_title']     = (string) $prefill_proposal->formation_title;
          $row['formation_full']      = (string) $prefill_proposal->formation_title;
          $row['formation']           = (string) $prefill_proposal->formation_title;
          $row['duration']            = (int) $prefill_proposal->formation_days * (int) $prefill_proposal->formation_hours_per_day . 'h00';
          $row['trained_headcount']   = (string) $prefill_proposal->formation_learners_count;
          /* Lieu de formation depuis la proposition */
          $row['formation_address']      = (string) ( $prefill_proposal->formation_location ?? '' );
          $row['formation_postal_code']  = ! empty( $prefill_proposal->client_postal_code ) ? (string) $prefill_proposal->client_postal_code : '';
          $row['formation_city']         = ! empty( $prefill_proposal->client_city )        ? (string) $prefill_proposal->client_city        : '';
          /* Dates séances : parser formation_dates (CSV de YYYY-MM-DD) */
          $raw_fdates = ! empty( $prefill_proposal->formation_dates ) ? (string) $prefill_proposal->formation_dates : '';
          $fdates_arr = array();
          if ( $raw_fdates ) {
            foreach ( array_filter( array_map( 'trim', explode( ',', $raw_fdates ) ) ) as $_fd ) {
              if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_fd ) ) { $fdates_arr[] = $_fd; }
            }
            sort( $fdates_arr );
          }
          $row['start_date'] = ! empty( $fdates_arr ) ? wp_date( 'd/m/Y', strtotime( $fdates_arr[0] ) ) : '';
          $row['end_date']   = count( $fdates_arr ) > 1 ? wp_date( 'd/m/Y', strtotime( end( $fdates_arr ) ) ) : $row['start_date'];
          $row['tarif_ht_value']      = $prefill_proposal->formation_total > 0
            ? number_format( (float) $prefill_proposal->formation_total, 2, ',', '' )
            : '';
          $row['objectives']          = ! empty( $prefill_proposal->custom_objectives )
            ? wp_strip_all_tags( (string) $prefill_proposal->custom_objectives )
            : '';
          $row['designation']         = 'Dates : à définir'
            . "\nDurée : " . $row['duration']
            . "\nEffectif : " . $row['trained_headcount'];
          if ( ! empty( $prefill_proposal->formation_funding ) ) {
            $row['payment_methods'] = sanitize_text_field( (string) $prefill_proposal->formation_funding )
              . "\n\nRèglement par virement bancaire à l'édition de la facture.";
          }
        }
      }
    }
    $page_title = $is_edit ? 'Modifier un devis' : 'Créer un devis';
    $page_desc  = $is_edit ? 'Modifiez les informations du devis puis enregistrez.' : 'Remplissez les informations ci-dessous pour générer un nouveau devis.';
    ?>
    <section class="acdc-section-head">
      <div><h2><?php echo esc_html( $page_title ); ?></h2><p><?php echo esc_html( $page_desc ); ?></p></div>
      <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $back_url ); ?>">&#8592; Retour aux devis</a>
    </section>
    <form class="acdc-quote-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'acdc_save_quote' ); ?>
      <input type="hidden" name="action" value="acdc_save_quote">
      <input type="hidden" name="quote_id" value="<?php echo esc_attr( (int) ( $row['id'] ?? 0 ) ); ?>">
      <input type="hidden" name="quote[scope]" value="<?php echo esc_attr( $scope ); ?>">
      <input type="hidden" name="quote[source_prospect_id]" value="<?php echo esc_attr( (int) ( $row['source_prospect_id'] ?? 0 ) ); ?>">
      <input type="hidden" name="quote[proposal_id]" value="<?php echo esc_attr( (int) ( $row['proposal_id'] ?? 0 ) ); ?>">
      <?php $this->render_quote_form_sections( $row, $is_edit ); ?>
      <?php if ( $is_edit ) : ?>
        <div class="acdc-panel acdc-profile-section"><h3>Aperçu du devis</h3><div class="acdc-details-grid"><div>Visualiser :</div><div><?php if ( ! empty( $row['html_url'] ) ) : ?><a href="<?php echo esc_url( $row['html_url'] ); ?>" target="_blank" rel="noopener" class="acdc-button acdc-button-primary">Ouvrir le devis</a><?php endif; ?></div></div></div>
        <div class="acdc-form-actions"><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $back_url ); ?>" data-acdc-no-iconize="1">Annuler</a><button type="submit" class="acdc-button acdc-button-primary" data-acdc-no-iconize="1">Enregistrer les modifications</button></div>
      <?php else : ?>
        <div class="acdc-form-actions"><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $back_url ); ?>" data-acdc-no-iconize="1">Annuler</a><button type="submit" class="acdc-button acdc-button-primary" data-acdc-no-iconize="1">Créer le devis</button></div>
      <?php endif; ?>
    </form>
    <?php
  }

  private function render_quote_form_sections( $row, $is_edit = false ) {
    ?>
    <div class="acdc-panel acdc-profile-section"><h3>Commanditaire</h3><div class="acdc-contract-grid">
      <div class="acdc-contract-label">Type <span class="acdc-required">*</span></div><div>
        <select name="quote[commanditaire_type]" required>
          <option value="">Choisir une option</option>
          <option value="Particulier" <?php selected( $row['commanditaire_type'] ?? '', 'Particulier' ); ?>>Particulier</option>
          <option value="Entreprise" <?php selected( $row['commanditaire_type'] ?? '', 'Entreprise' ); ?>>Entreprise</option>
        </select></div>
      <div class="acdc-contract-label">Nom Signataire <span class="acdc-required">*</span></div><div><input type="text" name="quote[apprenant_name]" value="<?php echo esc_attr( $row['apprenant'] ?? '' ); ?>" required></div>
      <div class="acdc-contract-label">Email Signataire <span class="acdc-required">*</span></div><div><input type="email" name="quote[apprenant_email]" value="<?php echo esc_attr( $row['apprenant_email'] ?? '' ); ?>" required></div>
      <div class="acdc-contract-label">Entreprise / société</div><div><input type="text" name="quote[client_company]" value="<?php echo esc_attr( $row['client_company'] ?? '' ); ?>"></div>
      <div class="acdc-contract-label">Adresse</div><div><input type="text" name="quote[client_address]" value="<?php echo esc_attr( $row['address'] ?? '' ); ?>"></div>
      <div class="acdc-contract-label">Complément d'adresse</div><div><input type="text" name="quote[client_address_complement]" value="<?php echo esc_attr( $row['address_complement'] ?? '' ); ?>"></div>
      <div class="acdc-contract-label">Code postal</div><div><input type="text" name="quote[client_postal_code]" value="<?php echo esc_attr( $row['postal_code'] ?? '' ); ?>"></div>
      <div class="acdc-contract-label">Ville</div><div><input type="text" name="quote[client_city]" value="<?php echo esc_attr( $row['city'] ?? '' ); ?>"></div>
    </div></div>
    <div class="acdc-panel acdc-profile-section"><h3>Formation</h3><div class="acdc-contract-grid">
      <div class="acdc-contract-label">Intitulé formation <span class="acdc-required">*</span></div><div><input type="text" name="quote[formation_title]" value="<?php echo esc_attr( $row['formation_full'] ?? $row['formation'] ?? '' ); ?>" required></div>
      <div class="acdc-contract-label">Format</div><div>
        <select name="quote[format]">
          <?php foreach ( array( 'Présentiel', 'Distanciel', 'Hybride' ) as $fmt ) : ?>
            <option value="<?php echo esc_attr( $fmt ); ?>" <?php selected( $row['format'] ?? 'Présentiel', $fmt ); ?>><?php echo esc_html( $fmt ); ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="acdc-contract-label">Date de début</div><div><input type="text" name="quote[start_date]" value="<?php echo esc_attr( $row['start_date'] ?? '' ); ?>" placeholder="jj/mm/aaaa"></div>
      <div class="acdc-contract-label">Date de fin</div><div><input type="text" name="quote[end_date]" value="<?php echo esc_attr( $row['end_date'] ?? '' ); ?>" placeholder="jj/mm/aaaa"></div>
      <div class="acdc-contract-label">Effectif formé</div><div><input type="text" name="quote[trained_headcount]" value="<?php echo esc_attr( $row['trained_headcount'] ?? '' ); ?>"></div>
      <div class="acdc-contract-label">Durée</div><div><input type="text" name="quote[duration_label]" value="<?php echo esc_attr( $row['duration'] ?? '' ); ?>" placeholder="ex: 14h00"></div>
      <div class="acdc-contract-label">Lieu (adresse)</div><div><input type="text" name="quote[formation_address]" value="<?php echo esc_attr( $row['formation_address'] ?? '' ); ?>"></div>
      <div class="acdc-contract-label">Lieu (code postal)</div><div><input type="text" name="quote[formation_postal_code]" value="<?php echo esc_attr( $row['formation_postal_code'] ?? '' ); ?>"></div>
      <div class="acdc-contract-label">Lieu (ville)</div><div><input type="text" name="quote[formation_city]" value="<?php echo esc_attr( $row['formation_city'] ?? '' ); ?>"></div>
      <div class="acdc-contract-label">Objectifs pédagogiques</div><div><textarea name="quote[objectives]" rows="4"><?php echo esc_textarea( $row['objectives'] ?? '' ); ?></textarea></div>
    </div></div>
    <div class="acdc-panel acdc-profile-section"><h3>Devis</h3><div class="acdc-contract-grid">
      <div class="acdc-contract-label">Numéro</div><div><input type="text" name="quote[number]" value="<?php echo esc_attr( $row['number'] ?? '' ); ?>"></div>
      <div class="acdc-contract-label">Date d'émission <span class="acdc-required">*</span></div><div><input type="text" name="quote[emission_date]" value="<?php echo esc_attr( $row['emission_date'] ?? '' ); ?>" placeholder="jj/mm/aaaa" required></div>
      <div class="acdc-contract-label">Date d'expiration <span class="acdc-required">*</span></div><div><input type="text" name="quote[expiration_date]" value="<?php echo esc_attr( $row['expiration_date'] ?? '' ); ?>" placeholder="jj/mm/aaaa" required></div>
      <div class="acdc-contract-label">Désignation</div><div><textarea name="quote[designation]" rows="5"><?php echo esc_textarea( $row['designation'] ?? '' ); ?></textarea></div>
      <div class="acdc-contract-label">Quantité</div><div><input type="text" name="quote[quantity]" value="<?php echo esc_attr( $row['quantity'] ?? '1,00' ); ?>"></div>
      <div class="acdc-contract-label">Tarif HT (€) <span class="acdc-required">*</span></div><div><input type="text" name="quote[tarif_ht]" value="<?php echo esc_attr( $row['tarif_ht_value'] ?? '' ); ?>" placeholder="ex: 900,00" required></div>
      <div class="acdc-contract-label">Taux de TVA (%)</div><div><input type="text" name="quote[vat_rate]" value="<?php echo esc_attr( $row['vat_rate'] ?? '20,00' ); ?>"></div>
      <div class="acdc-contract-label">Frais de transport</div><div><label class="acdc-switch"><input type="checkbox" name="quote[transport_fees_enabled]" value="1" <?php checked( ! empty( $row['transport_fees_enabled'] ) ); ?>><span class="acdc-switch-slider"></span></label></div>
      <div class="acdc-contract-label">Montant transport HT (€)</div><div><input type="text" name="quote[transport_fees_ht]" value="<?php echo esc_attr( $row['transport_fees_ht'] ?? '0,00' ); ?>"></div>
      <div class="acdc-contract-label">Frais restauration / hébergement</div><div><label class="acdc-switch"><input type="checkbox" name="quote[meal_fees_enabled]" value="1" <?php checked( ! empty( $row['meal_fees_enabled'] ) ); ?>><span class="acdc-switch-slider"></span></label></div>
      <div class="acdc-contract-label">Montant restauration HT (€)</div><div><input type="text" name="quote[meal_fees_ht]" value="<?php echo esc_attr( $row['meal_fees_ht'] ?? '0,00' ); ?>"></div>
      <div class="acdc-contract-label">Validité (jours)</div><div><input type="number" name="quote[validity_days]" value="<?php echo esc_attr( $row['validity_days'] ?? '30' ); ?>" min="1"></div>
      <div class="acdc-contract-label">Méthodes de paiement</div><div><textarea name="quote[payment_methods]" rows="4"><?php echo esc_textarea( $row['payment_methods'] ?? '' ); ?></textarea></div>
      <div class="acdc-contract-label">IBAN</div><div><input type="text" name="quote[iban]" value="<?php echo esc_attr( $row['iban'] ?? '' ); ?>" placeholder="FR76 XXXX..."></div>
      <div class="acdc-contract-label">BIC</div><div><input type="text" name="quote[bic]" value="<?php echo esc_attr( $row['bic'] ?? '' ); ?>"></div>
      <div class="acdc-contract-label">Statut</div><div>
        <select name="quote[status]">
          <?php foreach ( $this->get_quote_status_labels() as $k => $lbl ) : ?>
            <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $row['status'] ?? 'brouillon', $k ); ?>><?php echo esc_html( $lbl ); ?></option>
          <?php endforeach; ?>
        </select></div>
    </div></div>
    <?php
  }
  private function render_front_quote_preview( $scope, $quote_id ) {
    $q_prev = $this->get_quote( $quote_id );
    $row = $q_prev ? $this->build_quote_row_from_record( $q_prev ) : array();
    $back_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=quotes&scope=' . rawurlencode( $scope ) . '&quote_action=view&quote_id=' . (int) $row['id'] ) : $this->portal_page_url( array( 'tab' => 'quotes', 'scope' => $scope, 'quote_action' => 'view', 'quote_id' => (int) $row['id'] ) );
    echo '<div class="acdc-mb-18"><a class="acdc-button acdc-button-soft" href="' . esc_url( $back_url ) . '" style="min-width:44px;padding:10px 14px;">←</a></div>';
    echo '<div class="acdc-panel" style="padding:0;overflow:hidden;">' . $this->get_quote_document_html( $row ) . '</div>';
  }

  private function render_front_invoices_credit_notes_tab() {
    $scope = $this->get_invoices_scope();
    $action = $this->get_invoices_action();
    $invoice_id = isset( $_GET['invoice_id'] ) ? absint( $_GET['invoice_id'] ) : 1;
    if ( '' === $scope ) {
      $this->render_front_invoices_hub();
      return;
    }
    if ( 'view' === $action ) {
      $this->render_front_invoice_view( $scope, $invoice_id );
      return;
    }
    if ( 'create_credit_note' === $action ) {
      $this->render_front_credit_note_form( $scope, $invoice_id );
      return;
    }
    if ( 'preview_invoice' === $action ) {
      $this->render_front_invoice_preview( $scope, $invoice_id, 'invoice' );
      return;
    }
    if ( 'preview_credit_note' === $action ) {
      $this->render_front_invoice_preview( $scope, $invoice_id, 'credit_note' );
      return;
    }
    $this->render_front_invoices_list( $scope );
  }

  private function render_front_invoices_hub() {
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'invoices_credit_notes' ) );
    ?>
    <section class="acdc-section-head" style="align-items:flex-start;"><div><h2>Factures &amp; Avoirs</h2></div></section>
    <div class="acdc-grid-2cols" style="gap:18px;">
      <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'invoices_credit_notes', 'scope' => 'action' ), $base_url ) ); ?>" class="acdc-panel" style="min-height:98px;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;text-decoration:none;">
        <h3 style="margin:0 0 10px;color:#C5A253;font-size:18px;">Actions de formation</h3>
        <p style="margin:0;color:#1E4777;">Factures &amp; Avoirs</p>
      </a>
      <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'invoices_credit_notes', 'scope' => 'ancillary' ), $base_url ) ); ?>" class="acdc-panel" style="min-height:98px;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;text-decoration:none;">
        <h3 style="margin:0 0 10px;color:#C5A253;font-size:18px;">Prestations annexes</h3>
        <p style="margin:0;color:#1E4777;">Factures &amp; Avoirs</p>
      </a>
    </div>
    <?php
  }

  private function render_front_invoices_list( $scope ) {
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'invoices_credit_notes' ) );
    $back_url = add_query_arg( array( 'tab' => 'invoices_credit_notes' ), $base_url );
    $scope_label = 'ancillary' === $scope ? 'Prestations annexes' : 'Actions de formation';
    $is_demo = $this->is_documents_billing_demo_enabled();
    /* Mode réel : lecture BDD réelle. Mode démo : données fictives. */
    if ( $is_demo ) {
      $rows = $this->get_mock_invoices_data( $scope );
    } else {
      $rows_raw = $this->get_invoices( array( 'scope' => $scope ) );
      $rows = array();
      foreach ( $rows_raw as $inv ) {
        $rows[] = $this->build_invoice_row_from_record( $inv );
      }
    }
    $settings_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=billing_settings' ) : $this->portal_page_url( array( 'tab' => 'billing_settings' ) );
    ?>
    <?php if ( $is_demo ) : ?>
    <div class="acdc-alert" style="background:#fff8e1;border-left:4px solid #d6a353;padding:14px 18px;border-radius:8px;margin-bottom:18px;display:flex;align-items:center;gap:12px;">
      <?php echo $this->render_inline_icon( 'alert-triangle', 20 ); ?>
      <span><strong>Module en mode démonstration.</strong> Les données affichées sont fictives. <a href="<?php echo esc_url( $settings_url ); ?>">Activez la facturation réelle dans les Réglages</a> pour créer de vraies factures.</span>
    </div>
    <?php endif; ?>
    <div class="acdc-mb-18"><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $back_url ); ?>" style="min-width:44px;padding:10px 14px;">←</a></div>
    <section class="acdc-section-head"><div><h2>Factures &amp; Avoirs : <?php echo esc_html( $scope_label ); ?></h2></div></section>
    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
        <input type="hidden" name="tab" value="invoices_credit_notes"><input type="hidden" name="scope" value="<?php echo esc_attr( $scope ); ?>">
        <div class="acdc-inline-wrap" style="justify-content:space-between;align-items:center;gap:16px;">
          <input type="search" name="q" value="" placeholder="Rechercher" style="max-width:420px;">
          <?php if ( ! $is_demo ) : ?>
          <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'quotes' ), $base_url ) ); ?>" class="acdc-button acdc-button-soft" style="font-size:13px;">Convertir un devis →</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
    <div class="acdc-panel acdc-table-panel">
      <div class="acdc-table-wrap">
        <table class="acdc-table acdc-quotes-table" data-acdc-table-id="invoices-list"><thead><tr><th>NUMÉRO</th><th>TYPE</th><th>COMMANDITAIRE</th><th>FINANCEUR</th><th>STATUT</th><th>DERNIÈRE RELANCE</th><th>FORMATION</th><th>APPRENANT(S)</th><th>DATE D'ÉMISSION</th><th>DATE D'ÉCHÉANCE</th><th>TARIF HT</th><th>MONTANT TVA</th><th>TARIF TTC</th><th></th><th></th></tr></thead><tbody>
        <?php foreach ( $rows as $row ) :
          $view_url = add_query_arg( array( 'tab' => 'invoices_credit_notes', 'scope' => $scope, 'invoice_action' => 'view', 'invoice_id' => (int) $row['id'] ), $base_url );
          $download_url = $this->secure_admin_post_url( 'acdc_download_invoice_document', array( 'invoice_id' => (int) $row['id'], 'scope' => $scope ), 'acdc_download_invoice_document_' . (int) $row['id'] );
          $delete_url = $is_demo ? '#' : wp_nonce_url( admin_url( 'admin-post.php?action=acdc_delete_invoice&invoice_id=' . (int) $row['id'] ), 'acdc_delete_invoice_' . (int) $row['id'] );
        ?>
          <tr>
            <td><?php echo esc_html( $row['number'] ); ?></td>
            <td><?php echo esc_html( $row['type'] ?? 'Facture' ); ?></td>
            <td><?php echo esc_html( $row['commanditaire_type'] ); ?><br><strong style="color:#C5A253;"><?php echo esc_html( $row['commanditaire_name'] ); ?></strong></td>
            <td><?php echo esc_html( $row['financeur'] ); ?></td>
            <td><span class="acdc-status-badge acdc-status-badge-<?php echo esc_attr( $row['status_key'] ); ?>"><?php echo esc_html( $row['status_label'] ?? $row['status'] ); ?></span></td>
            <td><?php echo esc_html( $row['relance_date'] ); ?></td>
            <td><span style="color:#C5A253;font-weight:600;"><?php echo esc_html( $row['formation'] ); ?></span></td>
            <td><span style="color:#C5A253;font-weight:600;"><?php echo esc_html( $row['apprenant'] ); ?></span></td>
            <td><?php echo esc_html( $row['emission_date'] ); ?></td>
            <td><?php echo esc_html( $row['due_date'] ); ?></td>
            <td><?php echo esc_html( $row['tarif_ht'] ); ?></td>
            <td><?php echo esc_html( $row['montant_tva'] ); ?></td>
            <td><?php echo esc_html( $row['tarif_ttc'] ); ?></td>
            <td class="acdc-row-actions-menu-cell">
              <button type="button" class="acdc-row-menu-button" data-acdc-row-menu-toggle aria-label="Actions"><?php echo $this->render_inline_icon( 'more-horizontal', 25 ); ?></button>
              <div class="acdc-row-menu">
                <a href="<?php echo esc_url( $download_url ); ?>">Télécharger la facture</a>
                <?php if ( ! $is_demo ) : ?>
                <a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('Supprimer cette facture ?');">Supprimer</a>
                <?php endif; ?>
              </div>
            </td>
            <td><a class="acdc-row-view-link" href="<?php echo esc_url( $view_url ); ?>" title="Voir"><?php echo $this->render_inline_icon( 'view', 25 ); ?></a></td>
          </tr>
        <?php endforeach; ?>
        <?php if ( empty( $rows ) && ! $is_demo ) : ?>
          <tr><td colspan="15" style="text-align:center;padding:28px;color:#6b7280;">Aucune facture. <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'quotes' ), $base_url ) ); ?>">Convertissez un devis en facture →</a></td></tr>
        <?php endif; ?>
        </tbody></table>
      </div>
      <div class="acdc-quotes-footer"><div>«</div><div>‹</div><div class="acdc-page-current">1</div><div>›</div><div>»</div><div class="acdc-page-meta"><?php echo count( $rows ); ?> facture(s)</div></div>
    </div>
    <?php $this->render_invoice_email_modal(); ?>
    <?php $this->render_invoice_status_modal(); ?>
    <style>
      .acdc-filter-toggle-icons-only{padding:0;border:none;background:transparent;color:#1E4777;display:inline-flex;align-items:center;gap:8px;cursor:pointer}.acdc-filter-toggle-icons-only:hover{color:#0C2D52}
      .acdc-quotes-table th{font-size:11px}.acdc-quotes-table td{vertical-align:top}.acdc-status-badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:11px;font-weight:700}.acdc-status-badge-late{background:#f8e79c;color:#b68000}.acdc-status-badge-paid{background:#d6f7d6;color:#3ca64d}
      .acdc-row-actions-menu-cell{position:relative}.acdc-row-menu-button{padding:0;border:none;background:transparent;color:#1E4777;cursor:pointer}.acdc-row-menu{position:absolute;right:18px;top:26px;z-index:50;min-width:230px;background:#fff;border:1px solid #dce4ec;border-radius:10px;box-shadow:0 18px 42px rgba(12,45,82,.14);padding:8px;display:none}.acdc-row-menu a{display:block;padding:10px 12px;color:#5b6880;text-decoration:none;border-radius:10px}.acdc-row-menu a:hover{background:#F6F8FB;color:#0C2D52}.acdc-row-actions-menu-cell.is-open .acdc-row-menu{display:block}
      .acdc-quotes-footer{display:flex;align-items:center;gap:10px;padding:14px 10px 2px;color:#1E4777}.acdc-page-current{width:28px;height:28px;border-radius:999px;background:#E9C77C;color:#0B0706;display:flex;align-items:center;justify-content:center}.acdc-page-meta{margin-left:auto}
      .acdc-row-view-link{display:inline-flex;color:#1E4777;text-decoration:none}.acdc-row-view-link:hover{color:#0C2D52}
      .acdc-modal-dialog-email{width:min(1180px,94vw)}
    </style>
    <script>
      document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('[data-acdc-row-menu-toggle]').forEach(function(btn){ btn.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); var cell=btn.closest('.acdc-row-actions-menu-cell'); document.querySelectorAll('.acdc-row-actions-menu-cell.is-open').forEach(function(other){ if(other!==cell){other.classList.remove('is-open');} }); if(cell){ cell.classList.toggle('is-open'); } }); });
        document.addEventListener('click', function(){ document.querySelectorAll('.acdc-row-actions-menu-cell.is-open').forEach(function(cell){ cell.classList.remove('is-open'); }); });
      });
    </script>
    <?php
  }

  private function render_invoice_email_modal() {
    $logo = 'https://acdc-formation.com/wp-content/uploads/2025/06/cropped-cropped-Logo-ACDC-500x500-1.png';
    ?>
    <div class="acdc-modal-shell" id="acdc-send-invoice-email-modal" hidden>
      <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
      <div class="acdc-modal-dialog acdc-modal-dialog-email">
        <div class="acdc-modal-header"><h4>ENVOYER LA FACTURE PAR E-MAIL</h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
        <div class="acdc-modal-body acdc-pad-24">
          <div style="border:1px solid #dce4ec;overflow:hidden;background:#fff;">
            <div style="max-width:760px;margin:0 auto;background:#fff;color:#20375f;">
              <div style="padding:30px 20px 14px;text-align:center;background:#f7f9fd;">
                <img src="<?php echo esc_url( $logo ); ?>" alt="ACDC Formation" style="max-width:130px;height:auto;display:block;margin:0 auto 12px;">
                <div style="font-size:44px;font-weight:800;color:#203b74;">ACDC Formation</div>
                <div style="font-size:19px;letter-spacing:.04em;color:#516c9b;">AZUR COMPÉTENCES DÉVELOPPEMENT &amp; CONSEIL</div>
              </div>
              <div style="padding:34px 42px 12px;font-size:18px;line-height:1.65;">
                <p>Bonjour <strong>David Contal</strong>,</p>
                <p>Veuillez trouver ci-joint votre facture. Nous vous remercions pour votre confiance.</p>
                <p>Si vous avez des questions, vous pouvez nous contacter directement à <strong>contact@acdc-formation.com</strong> ou au <strong>06 78 26 91 10</strong>.</p>
                <p>Cordialement,<br><strong>L’équipe ACDC Formation</strong></p>
              </div>
              <div style="border-top:1px solid #dfe6f0;padding:22px 24px 30px;text-align:center;color:#64779d;font-size:14px;line-height:1.75;">06 78 26 91 10 · contact@acdc-formation.com · acdcformation.com<br>7 avenue Paul Cézanne — 83310 Cogolin<br>Ce message a été envoyé dans le cadre de votre facture.</div>
            </div>
          </div>
          <p class="acdc-actions-end"><button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button><button type="button" class="acdc-button acdc-button-primary">Exécuter l'action</button></p>
        </div>
      </div>
    </div>
    <?php
  }

  private function render_invoice_status_modal() {
    ?>
    <div class="acdc-modal-shell" id="acdc-status-invoice-modal" hidden>
      <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
      <div class="acdc-modal-dialog" style="width:min(1080px,92vw)">
        <div class="acdc-modal-header"><h4>MODIFIER LE STATUT</h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
        <div class="acdc-modal-body acdc-pad-24">
          <div class="acdc-modal-form-grid">
            <label>Nouveau statut <span class="acdc-required">*</span></label>
            <div><select><option>Choisir une option</option><option>Non payée</option><option>Payée</option></select></div>
            <label>Détails</label>
            <div><textarea rows="5" placeholder="Détails"></textarea></div>
          </div>
          <p class="acdc-actions-end"><button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button><button type="button" class="acdc-button acdc-button-primary">Exécuter l'action</button></p>
        </div>
      </div>
    </div>
    <?php
  }

  private function render_front_invoice_view( $scope, $invoice_id ) {
    $is_demo = $this->is_documents_billing_demo_enabled();
    if ( $is_demo ) {
      $row = $this->get_mock_invoice_record( $invoice_id, $scope );
    } else {
      $inv_real = $this->get_invoice( $invoice_id );
      $row = $inv_real ? $this->build_invoice_row_from_record( $inv_real ) : array();
    }
    if ( empty( $row ) ) { echo '<div class="acdc-alert acdc-alert-error">Facture introuvable.</div>'; return; }
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'invoices_credit_notes' ) );
    $back_url = add_query_arg( array( 'tab' => 'invoices_credit_notes', 'scope' => $scope ), $base_url );
    $download_url = $this->secure_admin_post_url( 'acdc_download_invoice_document', array( 'invoice_id' => (int) $row['id'], 'scope' => $scope ), 'acdc_download_invoice_document_' . (int) $row['id'] );
    $facturx_url = $is_demo ? '' : $this->secure_admin_post_url( 'acdc_download_invoice_facturx', array( 'invoice_id' => (int) $row['id'] ), 'acdc_download_invoice_facturx_' . (int) $row['id'] );
    $preview_invoice_url = add_query_arg( array( 'tab' => 'invoices_credit_notes', 'scope' => $scope, 'invoice_action' => 'preview_invoice', 'invoice_id' => (int) $row['id'] ), $base_url );
    $delete_url = $is_demo ? '#' : wp_nonce_url( admin_url( 'admin-post.php?action=acdc_delete_invoice&invoice_id=' . (int) $row['id'] ), 'acdc_delete_invoice_' . (int) $row['id'] );
    ?>
    <div class="acdc-mb-18"><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $back_url ); ?>" style="min-width:44px;padding:10px 14px;">←</a></div>
    <div class="acdc-panel acdc-profile-section"><h3>Client</h3><div class="acdc-details-grid">
      <div>Commanditaire</div><div style="color:#C5A253;font-weight:700;"><?php echo esc_html( $row['commanditaire_name'] ); ?></div>
      <div>Apprenant</div><div><?php echo esc_html( $row['apprenant'] ); ?></div>
      <?php if ( ! empty( $row['client_siren'] ) ) : ?><div>SIREN client</div><div><?php echo esc_html( $row['client_siren'] ); ?></div><?php endif; ?>
      <div>Adresse livraison</div><div><?php echo esc_html( trim( ( $row['delivery_address'] ?? '' ) . ' ' . ( $row['delivery_postal_code'] ?? '' ) . ' ' . ( $row['delivery_city'] ?? '' ) ) ); ?></div>
      <div>Financeur</div><div><?php echo esc_html( $row['financeur'] ); ?></div>
    </div></div>
    <div class="acdc-panel acdc-profile-section"><h3>Formation</h3><div class="acdc-details-grid">
      <div>Formation</div><div><strong style="color:#C5A253;"><?php echo esc_html( $row['formation_full'] ); ?></strong></div>
      <div>Durée</div><div><?php echo esc_html( $row['duration'] ); ?></div>
      <div>Dates</div><div><?php echo esc_html( $row['start_date'] ); ?> – <?php echo esc_html( $row['end_date'] ); ?></div>
    </div></div>
    <div class="acdc-panel acdc-profile-section"><h3>Facture <?php echo esc_html( $row['number'] ); ?></h3><div class="acdc-details-grid">
      <div>Télécharger</div><div><a href="<?php echo esc_url( $download_url ); ?>" class="acdc-file-chip">📄 Facture <?php echo esc_html( $row['number'] ); ?>.html</a></div>
      <?php if ( $facturx_url ) : ?><div>Facture électronique</div><div><a href="<?php echo esc_url( $facturx_url ); ?>" class="acdc-file-chip" title="XML Factur-X (profil MINIMUM) à embarquer dans le PDF/A-3">🧾 Factur-X (XML)</a></div><?php endif; ?>
      <div>Statut</div><div><span class="acdc-status-badge acdc-status-badge-<?php echo esc_attr( $row['status_key'] ); ?>"><?php echo esc_html( $row['status_label'] ?? $row['status'] ); ?></span></div>
      <div>Date d'émission</div><div><?php echo esc_html( $row['emission_date'] ); ?></div>
      <div>Date d'échéance</div><div><?php echo esc_html( $row['due_date'] ); ?></div>
      <div>Nature de l'opération</div><div><?php echo esc_html( $row['nature_operation'] ?? '' ); ?></div>
      <div>Option TVA sur débits</div><div><?php echo ! empty( $row['vat_option_debit'] ) ? 'Oui' : 'Non'; ?></div>
      <div>Tarif HT</div><div><?php echo esc_html( $row['tarif_ht'] ); ?></div>
      <div>TVA</div><div><?php echo esc_html( $row['montant_tva'] ); ?></div>
      <div>Tarif TTC</div><div><strong><?php echo esc_html( $row['tarif_ttc'] ); ?></strong></div>
      <div>Devis d'origine</div><div><?php echo ! empty( $row['quote_id'] ) ? esc_html( '#' . $row['quote_id'] ) : '—'; ?></div>
    </div></div>
    <?php if ( ! $is_demo ) : ?>
    <div class="acdc-form-actions" style="margin-top:14px;">
      <a href="<?php echo esc_url( $delete_url ); ?>" class="acdc-button acdc-button-soft" onclick="return confirm('Supprimer cette facture ?');" style="color:#c0392b;">Supprimer</a>
      <a href="<?php echo esc_url( $preview_invoice_url ); ?>" target="_blank" class="acdc-button acdc-button-primary" rel="noopener">Prévisualiser</a>
    </div>
    <?php endif; ?>
    <style>.acdc-details-grid{display:grid;grid-template-columns:430px 1fr;gap:18px 26px}.acdc-file-chip{display:inline-flex;align-items:center;gap:10px;padding:12px 14px;border-radius:10px;background:#8ea2c0;color:#0B0706;text-decoration:none;min-width:100%;max-width:820px}.acdc-file-chip:hover{opacity:.92;color:#0B0706}@media(max-width:900px){.acdc-details-grid{grid-template-columns:1fr}}</style>
    <?php
  }

  private function render_front_credit_note_form( $scope, $invoice_id ) {
    $row = $this->get_mock_invoice_record( $invoice_id, $scope );
    $credit = $row['credit_note'];
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'invoices_credit_notes' ) );
    $back_url = add_query_arg( array( 'tab' => 'invoices_credit_notes', 'scope' => $scope ), $base_url );
    $preview_credit_note_url = add_query_arg( array( 'tab' => 'invoices_credit_notes', 'scope' => $scope, 'invoice_action' => 'preview_credit_note', 'invoice_id' => (int) $row['id'] ), $base_url );
    ?>
    <div class="acdc-mb-18"><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $back_url ); ?>" style="min-width:44px;padding:10px 14px;">←</a></div>
    <div class="acdc-panel acdc-contract-section"><h3>Destinataire</h3><div class="acdc-contract-grid"><div class="acdc-contract-label">Action de formation <span class="acdc-required">*</span></div><div><select><option><?php echo esc_html( $row['apprenant'] ); ?></option></select></div><div class="acdc-contract-label">Détails</div><div><div class="acdc-notice-inline" style="padding:16px;border-top:10px solid #C5A253;border-radius:0;">N° : <?php echo esc_html( $row['number'] ); ?><br>Tarif HT : <?php echo esc_html( $row['tarif_ht'] ); ?><br>TVA : <?php echo esc_html( $row['vat_rate'] ); ?>%<br>Tarif TTC : <?php echo esc_html( $row['tarif_ttc'] ); ?><p style="text-align:center;margin-top:16px;"><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->secure_admin_post_url( 'acdc_download_invoice_document', array( 'invoice_id' => (int) $row['id'], 'scope' => $scope ), 'acdc_download_invoice_document_' . (int) $row['id'] ) ); ?>">Télécharger la facture</a></p></div></div><div class="acdc-contract-label">Financeur <span class="acdc-required">*</span></div><div><select><option>—</option></select></div><div class="acdc-contract-label">Adresse <span class="acdc-required">*</span></div><div><input type="text" value="<?php echo esc_attr( $row['address'] ); ?>"></div><div class="acdc-contract-label">Code postal <span class="acdc-required">*</span></div><div><input type="text" value="<?php echo esc_attr( $row['postal_code'] ); ?>"></div><div class="acdc-contract-label">Ville <span class="acdc-required">*</span></div><div><input type="text" value="<?php echo esc_attr( $row['city'] ); ?>"></div></div></div>
    <div class="acdc-panel acdc-contract-section"><h3>Formation</h3><div class="acdc-contract-grid"><div class="acdc-contract-label">Format <span class="acdc-required">*</span></div><div><select><option><?php echo esc_html( $row['format'] ); ?></option></select></div><div class="acdc-contract-label">Adresse</div><div><input type="text" value="<?php echo esc_attr( $row['formation_address'] ); ?>"></div><div class="acdc-contract-label">Code postal</div><div><input type="text" value="<?php echo esc_attr( $row['formation_postal_code'] ); ?>"></div><div class="acdc-contract-label">Ville</div><div><input type="text" value="<?php echo esc_attr( $row['formation_city'] ); ?>"></div><div class="acdc-contract-label">Durée <span class="acdc-required">*</span></div><div><input type="text" value="<?php echo esc_attr( $row['duration'] ); ?>"></div><div class="acdc-contract-label">Date de début <span class="acdc-required">*</span></div><div><input type="text" value="<?php echo esc_attr( $row['start_date'] ); ?>"></div><div class="acdc-contract-label">Date de fin <span class="acdc-required">*</span></div><div><input type="text" value="<?php echo esc_attr( $row['end_date'] ); ?>"></div><div class="acdc-contract-label">Effectif formé</div><div><input type="text" value="<?php echo esc_attr( $row['trained_headcount'] ); ?>"></div></div></div>
    <div class="acdc-panel acdc-contract-section"><h3>Avoir</h3><div class="acdc-contract-grid"><div class="acdc-contract-label">Préfixe <span class="acdc-required">*</span></div><div><input type="text" value="<?php echo esc_attr( $credit['prefix'] ); ?>"></div><div class="acdc-contract-label">Numéro <span class="acdc-required">*</span></div><div><input type="text" value="<?php echo esc_attr( $credit['num'] ); ?>"></div><div class="acdc-contract-label">Date d'émission <span class="acdc-required">*</span></div><div><input type="text" placeholder="jj/mm/aaaa"></div><div class="acdc-contract-label">Date d'échéance <span class="acdc-required">*</span></div><div><input type="text" placeholder="jj/mm/aaaa"></div><div class="acdc-contract-label">Désignation</div><div><textarea rows="5"><?php echo esc_textarea( $credit['designation'] ); ?></textarea></div><div class="acdc-contract-label">Quantité <span class="acdc-required">*</span></div><div><input type="text" value="<?php echo esc_attr( $credit['quantity'] ); ?>"></div><div class="acdc-contract-label">Tarif HT (€) <span class="acdc-required">*</span></div><div><input type="text" value="<?php echo esc_attr( $credit['tarif_ht_value'] ); ?>"></div><div class="acdc-contract-label">Taux de TVA (%) <span class="acdc-required">*</span></div><div><input type="text" value="<?php echo esc_attr( $credit['vat_rate'] ); ?>"></div><div class="acdc-contract-label">Modes de financement à créditer pour cet avoir</div><div><div class="acdc-panel" style="margin:0;padding:0 0 14px;"><div style="background:#C5A253;color:#0B0706;padding:8px 14px;border-radius:10px 10px 0 0;">#1 &nbsp; Mode de financement</div><div style="padding:14px;"><p style="margin:0 0 10px;">Mode de financement <span class="acdc-required">*</span></p><select><option>Apprenant financé par un gestionnaire de fonds de la formation professionnelle</option></select><p style="margin:14px 0 10px;">Détail <span class="acdc-required">*</span></p><select><option>Plan de développement des compétences ou d'autres dispositifs</option></select><p style="margin:14px 0 10px;">Tarif (€ HT) <span class="acdc-required">*</span></p><input type="text" value="588"><p style="margin:14px 0 10px;">Créditer ce mode de financement</p><div class="acdc-switch"></div><p class="acdc-drawer-help">Permet la génération automatique de votre BPF</p></div></div><p class="acdc-drawer-help">Le détail des modes de financement n'apparaîtra pas sur l'avoir, mais sera utile à la génération automatique de votre Bilan Pédagogique et Financier.</p></div><div class="acdc-contract-label">Tarif TTC (€)</div><div><input type="text" value="<?php echo esc_attr( $credit['tarif_ttc_value'] ); ?>"></div><div class="acdc-contract-label">Adresse d'émission <span class="acdc-required">*</span></div><div><textarea rows="4"><?php echo esc_textarea( $credit['billing_address'] ); ?></textarea></div><div class="acdc-contract-label">Méthodes de paiement</div><div><textarea rows="4"><?php echo esc_textarea( $credit['payment_methods'] ); ?></textarea></div><div class="acdc-contract-label">Commentaire</div><div><textarea rows="4" placeholder="Commentaire"></textarea></div></div></div>
    <div class="acdc-panel acdc-contract-section"><h3>Modifier un avoir : <?php echo esc_html( $credit['number'] ); ?></h3><div class="acdc-contract-grid"><div class="acdc-contract-label">Aperçu :</div><div><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $preview_credit_note_url ); ?>" target="_blank" rel="noopener">Visualiser le document</a></div></div></div>
    <p class="acdc-actions-end"><button type="button" class="acdc-button acdc-button-soft">Annuler</button><button type="button" class="acdc-button acdc-button-primary">Créer &amp; ajouter un autre</button><button type="button" class="acdc-button acdc-button-primary">Créer un avoir</button></p>
    <?php
  }

  private function render_front_invoice_preview( $scope, $invoice_id, $document_kind = 'invoice' ) {
    $row = $this->get_mock_invoice_record( $invoice_id, $scope );
    $back_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=invoices_credit_notes&scope=' . rawurlencode( $scope ) . '&invoice_action=view&invoice_id=' . (int) $row['id'] ) : $this->portal_page_url( array( 'tab' => 'invoices_credit_notes', 'scope' => $scope, 'invoice_action' => 'view', 'invoice_id' => (int) $row['id'] ) );
    echo '<div class="acdc-mb-18"><a class="acdc-button acdc-button-soft" href="' . esc_url( $back_url ) . '" style="min-width:44px;padding:10px 14px;">←</a></div>';
    echo '<div class="acdc-panel" style="padding:0;overflow:hidden;">' . $this->get_invoice_document_html( $row, $document_kind ) . '</div>';
  }

  private function render_front_documents_tab( $action ) {
    $documents = $this->get_documents();
    $companies = $this->get_companies();
    $contacts = $this->get_contacts();
    $prefill_company_id = isset( $_GET['company_id'] ) ? absint( wp_unslash( $_GET['company_id'] ) ) : 0;
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Documents</h2>
        <p>Pièces administratives et documents métier.</p>
      </div>
      <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->portal_page_url( array( 'tab' => 'documents', 'action' => 'new' ) ) ); ?>">Ajouter un document</a>
    </section>
    <?php if ( 'new' === $action ) : ?>
      <div class="acdc-panel acdc-form-panel">
        <h3>Créer un document</h3>
        <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
          <?php wp_nonce_field( 'acdc_save_document' ); ?>
          <input type="hidden" name="action" value="acdc_save_document">
          <div class="acdc-grid-2cols">
            <p><label>Titre</label><input type="text" name="title" required></p>
            <p>
              <label>Type de document</label>
              <select name="document_type">
                <option value="Programme">Programme</option>
                <option value="Convention">Convention</option>
                <option value="Convocation">Convocation</option>
                <option value="Devis">Devis</option>
                <option value="Facture">Facture</option>
                <option value="Attestation">Attestation</option>
                <option value="Autre">Autre</option>
              </select>
            </p>
            <p>
              <label>Entreprise</label>
              <select name="company_id">
                <option value="0">— Aucune —</option>
                <?php foreach ( $companies as $company ) : ?>
                  <option value="<?php echo esc_attr( $company->id ); ?>" <?php selected( $prefill_company_id, $company->id ); ?>><?php echo esc_html( $company->name ); ?></option>
                <?php endforeach; ?>
              </select>
            </p>
            <p>
              <label>Contact</label>
              <select name="contact_id">
                <option value="0">— Aucun —</option>
                <?php foreach ( $contacts as $contact ) : ?>
                  <option value="<?php echo esc_attr( $contact->id ); ?>"><?php echo esc_html( trim( $contact->first_name . ' ' . $contact->last_name ) ); ?></option>
                <?php endforeach; ?>
              </select>
            </p>
          </div>
          <p><label>Fichier</label><input type="file" name="document_file" required></p>
          <p><button type="submit" class="acdc-button acdc-button-primary">Téléverser</button></p>
        </form>
      </div>
    <?php endif; ?>
    <div class="acdc-panel">
      <div class="acdc-table-wrap">
        <table class="acdc-table">
          <thead>
            <tr>
              <th>Titre</th>
              <th>Type</th>
              <th>Entreprise</th>
              <th>Contact</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if ( ! empty( $documents ) ) : ?>
            <?php foreach ( $documents as $document ) : ?>
              <tr>
                <td><?php echo esc_html( $document->title ); ?></td>
                <td><?php echo esc_html( $document->document_type ); ?></td>
                <td><?php echo esc_html( $document->company_name ); ?></td>
                <td><?php echo esc_html( trim( $document->contact_name ) ); ?></td>
                <td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $document->created_at ) ); ?></td>
                <td>
                  <?php /* ACDC 3.20.104 — Conversion liens texte → icônes inline 25px (action « Ouvrir » utilise le pictogramme lien externe). */ ?>
                  <div class="acdc-groups-actions-inline">
                    <a class="acdc-row-action-icon" href="<?php echo esc_url( $document->file_url ); ?>" target="_blank" rel="noopener noreferrer" title="Ouvrir" aria-label="Ouvrir le document dans un nouvel onglet">
                      <?php echo $this->render_inline_icon( 'external-link', 25 ); ?>
                    </a>
                    <a class="acdc-row-action-icon acdc-row-delete-link" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_delete_document&document_id=' . $document->id ), 'acdc_delete_document_' . $document->id ) ); ?>" title="Supprimer" aria-label="Supprimer le document" onclick="return confirm('Supprimer ce document ?');">
                      <?php echo $this->render_inline_icon( 'trash', 25 ); ?>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr><td colspan="6">Aucun document enregistré.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
  }

public function render_admin_documents_page() { $this->render_admin_portal_wrapper( 'documents' ); }
}
