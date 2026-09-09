<?php
/**
 * ACDC Marketing — rendus et écrans
 *
 * Extraction incrémentale du module marketing.
 * Version : 3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Marketing_Render_Trait {


  private function render_marketing_header( $title, $description = '', $actions = array() ) {
    echo '<div class="acdc-panel acdc-marketing-segments-form-panel" style="margin-bottom:18px">';
    echo '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:18px;flex-wrap:wrap">';
    echo '<div><h1 style="margin:0 0 8px 0">' . esc_html( $title ) . '</h1>';
    if ( $description ) {
      echo '<p style="margin:0;color:#1E4777;max-width:900px">' . esc_html( $description ) . '</p>';
    }
    echo '</div>';
    if ( ! empty( $actions ) ) {
      echo '<div style="display:flex;gap:10px;flex-wrap:wrap">';
      foreach ( $actions as $action ) {
        $class = ! empty( $action['primary'] ) ? 'acdc-button-primary' : 'acdc-button-secondary';
        echo '<a class="acdc-button ' . esc_attr( $class ) . '" href="' . esc_url( $action['url'] ) . '">' . esc_html( $action['label'] ) . '</a>';
      }
      echo '</div>';
    }
    echo '</div>';
    echo '</div>';
  }


  private function render_marketing_cards( $cards ) {
    echo '<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:18px">';
    foreach ( $cards as $card ) {
      echo '<div class="acdc-panel">';
      echo '<div style="font-size:12px;text-transform:uppercase;color:#1E4777;font-weight:700;margin-bottom:6px">' . esc_html( $card['label'] ) . '</div>';
      echo '<div style="font-size:28px;font-weight:700;color:#0C2D52">' . esc_html( (string) $card['value'] ) . '</div>';
      if ( ! empty( $card['help'] ) ) {
        echo '<div style="margin-top:6px;color:#1E4777;font-size:12px">' . esc_html( $card['help'] ) . '</div>';
      }
      echo '</div>';
    }
    echo '</div>';
  }


  public function render_marketing_public_shortcode( $atts = array() ) {
    $form_token = isset( $_GET['form'] ) ? sanitize_text_field( wp_unslash( $_GET['form'] ) ) : '';
    $forms = $this->get_marketing_store( 'forms', array() );
    $form = null;
    foreach ( $forms as $item ) {
      if ( isset( $item['public_token'] ) && $item['public_token'] === $form_token ) {
        $form = $item;
        break;
      }
    }
    ob_start();
    echo '<div class="acdc-portal-shell"><div class="acdc-panel" style="max-width:920px;margin:24px auto">';
    if ( ! $form ) {
      echo '<h1>Formulaire indisponible</h1><p>Le formulaire demandé est introuvable ou n’est pas encore publié.</p>';
      echo '</div></div>';
      return (string) ob_get_clean();
    }
    echo '<h1>' . esc_html( ! empty( $form['name'] ) ? $form['name'] : 'Formulaire marketing' ) . '</h1>';
    if ( ! empty( $form['intro_text'] ) ) {
      echo '<p style="color:#1E4777">' . esc_html( $form['intro_text'] ) . '</p>';
    }
    if ( isset( $_GET['submitted'] ) ) {
      echo '<div class="notice notice-success" style="padding:12px 16px;margin:16px 0"><p>' . esc_html( ! empty( $form['success_message'] ) ? $form['success_message'] : 'Votre demande a bien été enregistrée.' ) . '</p></div>';
    }
    echo '<form class="acdc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    wp_nonce_field( 'acdc_marketing_public_submit' );
    echo '<input type="hidden" name="action" value="acdc_marketing_public_submit">';
    echo '<input type="hidden" name="marketing_form_token" value="' . esc_attr( $form_token ) . '">';
    echo '<div class="acdc-marketing-segments-form-grid">';
    echo '<p><label>Prénom</label><input type="text" name="marketing_public[first_name]" required></p>';
    echo '<p><label>Nom</label><input type="text" name="marketing_public[last_name]" required></p>';
    echo '<p><label>E-mail</label><input type="email" name="marketing_public[email]" required></p>';
    echo '<p><label>Téléphone</label><input type="text" name="marketing_public[phone]"></p>';
    echo '<p><label>Entreprise / organisme</label><input type="text" name="marketing_public[company]"></p>';
    echo '<p><label>Fonction</label><input type="text" name="marketing_public[position]"></p>';
    echo '</div>';
    echo '<p><label>Votre message</label><textarea name="marketing_public[message]" rows="5"></textarea></p>';
    echo '<p class="acdc-checkbox-line"><label><input type="checkbox" name="marketing_public[consent]" value="1" required> J’accepte d’être recontacté dans le cadre de ma demande.</label></p>';
    echo '<p><button type="submit" class="acdc-button acdc-button-primary">Envoyer</button></p>';
    echo '</form></div></div>';
    return (string) ob_get_clean();
  }


  private function render_front_marketing_dashboard_tab() {
    $contacts = $this->get_marketing_contacts_index();
    $campaigns = $this->get_marketing_store( 'campaigns', array() );
    $scenarios = $this->get_marketing_store( 'scenarios', array() );
    $queue = $this->get_marketing_store( 'queue', array() );
    $logs = $this->get_marketing_store( 'logs', array() );
    $notifications = $this->get_marketing_store( 'notifications', array() );
    $enabled_contacts = array_filter( $contacts, function( $item ) { return ! empty( $item['enabled'] ); } );
    $active_campaigns = array_filter( $campaigns, function( $item ) { return isset( $item['status'] ) && in_array( $item['status'], array( 'active', 'planned' ), true ); } );
    $active_scenarios = array_filter( $scenarios, function( $item ) { return isset( $item['status'] ) && in_array( $item['status'], array( 'active', 'planned' ), true ); } );
    $this->render_marketing_header( 'Tableau de bord marketing', 'Pilotez ici votre sous-module Communication marketing, dans le respect du cadrage validé et de la charte du plugin.', array(
      array( 'label' => 'Créer une campagne', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_campaigns', 'action' => 'new' ) ), 'primary' => true ),
      array( 'label' => 'Créer un formulaire', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_forms', 'action' => 'new' ) ) ),
    ) );
    $this->render_marketing_cards( array(
      array( 'label' => 'Contacts marketing', 'value' => count( $enabled_contacts ), 'help' => 'Activés manuellement sur les fiches existantes.' ),
      array( 'label' => 'Campagnes actives', 'value' => count( $active_campaigns ), 'help' => 'Brouillons, planifiées ou en cours.' ),
      array( 'label' => 'Scénarios actifs', 'value' => count( $active_scenarios ), 'help' => 'Déclencheurs et automatisations.' ),
      array( 'label' => 'Envois en file', 'value' => count( $queue ), 'help' => 'Attente, pause ou reprise.' ),
    ) );
    echo '<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:18px">';
    echo '<div class="acdc-panel"><h2 style="margin-top:0">Activité récente</h2><table class="acdc-table"><thead><tr><th>Date</th><th>Type</th><th>Message</th></tr></thead><tbody>';
    foreach ( array_slice( $logs, 0, 8 ) as $log ) {
      echo '<tr><td>' . esc_html( isset( $log['date'] ) ? $log['date'] : '' ) . '</td><td>' . esc_html( isset( $log['type'] ) ? ucfirst( $log['type'] ) : '' ) . '</td><td>' . esc_html( isset( $log['message'] ) ? $log['message'] : '' ) . '</td></tr>';
    }
    if ( empty( $logs ) ) { echo '<tr><td colspan="3">Aucune activité marketing enregistrée pour le moment.</td></tr>'; }
    echo '</tbody></table></div>';
    echo '<div style="display:grid;gap:18px">';
    echo '<div class="acdc-panel"><h2 style="margin-top:0">À traiter</h2><ul style="margin:0;padding-left:18px;color:#1E4777">';
    echo '<li>Vérifier la configuration expéditeur et WP Mail SMTP.</li>';
    echo '<li>Activer les contacts marketing utiles avant le premier envoi.</li>';
    echo '<li>Contrôler la file d’attente, les désinscriptions et les rebonds.</li>';
    echo '</ul></div>';
    echo '<div class="acdc-panel"><h2 style="margin-top:0">Notifications internes</h2><table class="acdc-table"><thead><tr><th>Date</th><th>Titre</th><th>Niveau</th></tr></thead><tbody>';
    foreach ( array_slice( $notifications, 0, 6 ) as $item ) {
      echo '<tr><td>' . esc_html( isset( $item['created_at'] ) ? $item['created_at'] : '' ) . '</td><td>' . esc_html( isset( $item['title'] ) ? $item['title'] : '' ) . '</td><td>' . esc_html( isset( $item['level'] ) ? ucfirst( $item['level'] ) : '' ) . '</td></tr>';
    }
    if ( empty( $notifications ) ) { echo '<tr><td colspan="3">Aucune notification interne.</td></tr>'; }
    echo '</tbody></table></div>';
    echo '</div></div>';
  }



  private function render_front_marketing_contacts_tab( $action = 'list' ) {
    $contacts = $this->get_marketing_contacts_index();
    $type_labels = $this->marketing_type_labels();
    $source_labels = $this->marketing_source_labels();
    $status_labels = $this->marketing_category_statuses();
    $categories = $this->marketing_categories();
    $lists_map = $this->marketing_entity_name_map( 'lists' );
    $tags_map = $this->marketing_entity_name_map( 'tags' );
    uasort( $contacts, function( $a, $b ) { return strcmp( (string) $a['name'], (string) $b['name'] ); } );
    $current_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : $action;
    $current_key = isset( $_GET['source_key'] ) ? sanitize_text_field( wp_unslash( $_GET['source_key'] ) ) : '';
    $search = isset( $_GET['marketing_search'] ) ? sanitize_text_field( wp_unslash( $_GET['marketing_search'] ) ) : '';
    $filter_type = isset( $_GET['marketing_type_filter'] ) ? sanitize_key( wp_unslash( $_GET['marketing_type_filter'] ) ) : '';
    $filter_enabled = isset( $_GET['marketing_enabled_filter'] ) ? sanitize_key( wp_unslash( $_GET['marketing_enabled_filter'] ) ) : '';
    $filter_status = isset( $_GET['marketing_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['marketing_status_filter'] ) ) : '';

    $enabled_count = 0;
    $inactive_count = 0;
    foreach ( $contacts as $contact ) {
      if ( ! empty( $contact['enabled'] ) ) {
        $enabled_count++;
      } else {
        $inactive_count++;
      }
    }

    $this->render_marketing_header( 'Contacts marketing', 'Base marketing liée aux fiches existantes du plugin, avec activation manuelle, statuts par catégorie, listes, étiquettes et score simple.', array(
      array( 'label' => 'Voir les imports', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_imports' ) ), 'primary' => true ),
      array( 'label' => 'Créer une liste', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_lists', 'action' => 'new' ) ) ),
      array( 'label' => 'Créer une étiquette', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_tags', 'action' => 'new' ) ) ),
    ) );
    $this->render_marketing_cards( array(
      array( 'label' => 'Contacts détectés', 'value' => count( $contacts ), 'help' => 'Fiches réutilisées depuis le plugin et contacts publics/importés.' ),
      array( 'label' => 'Contacts activés', 'value' => $enabled_count, 'help' => 'Peuvent entrer dans les campagnes et scénarios selon leurs statuts.' ),
      array( 'label' => 'Contacts inactifs', 'value' => $inactive_count, 'help' => 'Disponibles, mais non activés pour le marketing.' ),
      array( 'label' => 'Listes / étiquettes', 'value' => count( $lists_map ) . ' / ' . count( $tags_map ), 'help' => 'Référentiels utilisables sur les fiches contacts.' ),
    ) );

    if ( in_array( $current_action, array( 'view', 'edit' ), true ) && isset( $contacts[ $current_key ] ) ) {
      $contact = $contacts[ $current_key ];
      $statuses = isset( $contact['statuses'] ) && is_array( $contact['statuses'] ) ? $contact['statuses'] : $this->marketing_default_contact_statuses( isset( $contact['type'] ) ? $contact['type'] : 'prospect' );
      $is_view = 'view' === $current_action;
      echo '<div class="acdc-panel" style="margin-bottom:18px">';
      echo '<div style="display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap;align-items:flex-start">';
      echo '<div>';
      echo '<h2 style="margin:0 0 8px 0">' . esc_html( $is_view ? 'Fiche contact marketing' : 'Modifier le contact marketing' ) . '</h2>';
      echo '<p style="margin:0;color:#1E4777">Source : ' . esc_html( isset( $source_labels[ $contact['source_type'] ] ) ? $source_labels[ $contact['source_type'] ] : $contact['source_type'] ) . ' · Clé : ' . esc_html( $contact['source_key'] ) . '</p>';
      echo '</div>';
      echo '<div style="display:flex;gap:10px;flex-wrap:wrap">';
      if ( $is_view ) {
        echo '<a class="acdc-button acdc-button-primary" href="' . esc_url( $this->portal_page_url( array( 'tab' => 'marketing_contacts', 'action' => 'edit', 'source_key' => $contact['source_key'] ) ) ) . '">Modifier</a>';
      }
      echo '<a class="acdc-button acdc-button-secondary" href="' . esc_url( $this->portal_page_url( array( 'tab' => 'marketing_contacts' ) ) ) . '">Retour à la liste</a>';
      echo '</div></div></div>';

      if ( $is_view ) {
        echo '<div style="display:grid;grid-template-columns:1.2fr 1fr;gap:18px">';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Identité et qualification</h3>';
        echo '<table class="acdc-table"><tbody>';
        echo '<tr><th>Nom</th><td>' . esc_html( $contact['name'] ) . '</td></tr>';
        echo '<tr><th>Type</th><td>' . esc_html( isset( $type_labels[ $contact['type'] ] ) ? $type_labels[ $contact['type'] ] : $contact['type'] ) . '</td></tr>';
        echo '<tr><th>E-mail</th><td>' . esc_html( $contact['email'] ) . '</td></tr>';
        echo '<tr><th>Téléphone</th><td>' . esc_html( isset( $contact['phone'] ) ? $contact['phone'] : '' ) . '</td></tr>';
        echo '<tr><th>Entreprise / organisme</th><td>' . esc_html( isset( $contact['company'] ) ? $contact['company'] : '' ) . '</td></tr>';
        echo '<tr><th>Activation</th><td>' . ( ! empty( $contact['enabled'] ) ? 'Activé' : 'Inactif' ) . '</td></tr>';
        echo '<tr><th>Liste principale</th><td>' . esc_html( isset( $lists_map[ isset( $contact['primary_list_id'] ) ? $contact['primary_list_id'] : '' ] ) ? $lists_map[ $contact['primary_list_id'] ] : '' ) . '</td></tr>';
        echo '<tr><th>Score</th><td>' . esc_html( (string) ( isset( $contact['score'] ) ? $contact['score'] : 0 ) ) . '</td></tr>';
        echo '<tr><th>Score valeur</th><td>' . esc_html( (string) ( isset( $contact['score_value'] ) ? $contact['score_value'] : 0 ) ) . '</td></tr>';
        echo '<tr><th>Dernier engagement</th><td>' . esc_html( isset( $contact['last_engagement'] ) ? $contact['last_engagement'] : '' ) . '</td></tr>';
        echo '<tr><th>Listes</th><td>' . esc_html( $this->marketing_contact_related_labels( isset( $contact['lists'] ) ? $contact['lists'] : array(), 'lists' ) ) . '</td></tr>';
        echo '<tr><th>Étiquettes</th><td>' . esc_html( $this->marketing_contact_related_labels( isset( $contact['tags'] ) ? $contact['tags'] : array(), 'tags' ) ) . '</td></tr>';
        echo '<tr><th>Note interne</th><td>' . esc_html( isset( $contact['notes'] ) ? $contact['notes'] : '' ) . '</td></tr>';
        echo '</tbody></table></div>';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Statuts par catégorie</h3><table class="acdc-table"><thead><tr><th>Catégorie</th><th>Statut</th></tr></thead><tbody>';
        foreach ( $categories as $cat_key => $cat_label ) {
          $status_key = isset( $statuses[ $cat_key ] ) ? $statuses[ $cat_key ] : 'not_requested';
          echo '<tr><td>' . esc_html( $cat_label ) . '</td><td>' . esc_html( isset( $status_labels[ $status_key ] ) ? $status_labels[ $status_key ] : $status_key ) . '</td></tr>';
        }
        echo '</tbody></table></div></div>';
      } else {
        echo '<style>.acdc-marketing-segment-choice-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:10px}.acdc-marketing-segment-choice{display:block;margin:0;cursor:pointer}.acdc-marketing-segment-choice input{position:absolute;opacity:0;pointer-events:none}.acdc-marketing-segment-choice-card{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border:1px solid #D8E0EF;border-radius:12px;background:#fff;min-height:74px;transition:border-color .15s ease,box-shadow .15s ease,background-color .15s ease}.acdc-marketing-segment-choice:hover .acdc-marketing-segment-choice-card{border-color:#C5A253;box-shadow:0 4px 14px rgba(11,7,6,.08)}.acdc-marketing-segment-choice input:checked + .acdc-marketing-segment-choice-card{border-color:#C5A253;background:#FBF6E8;box-shadow:0 4px 14px rgba(197,162,83,.18)}.acdc-marketing-segment-choice-check{width:18px;height:18px;border:2px solid #C5A253;border-radius:6px;flex:0 0 18px;margin-top:2px;background:#fff}.acdc-marketing-segment-choice input:checked + .acdc-marketing-segment-choice-card .acdc-marketing-segment-choice-check{background:#C5A253;box-shadow:inset 0 0 0 3px #fff}.acdc-marketing-segment-choice-title{font-weight:700;color:#17325C;line-height:1.25}.acdc-marketing-segment-choice-meta{margin-top:4px;font-size:12px;line-height:1.35;color:#5F749A}.acdc-marketing-segment-section{margin-top:18px;padding-top:14px;border-top:1px solid #E2E8F3}.acdc-marketing-segment-section h3{margin:0 0 6px 0;font-size:15px;color:#17325C}.acdc-marketing-segment-section p.acdc-marketing-segment-help{margin:0 0 10px 0;font-size:12px;color:#5F749A}</style>';
        echo '<div class="acdc-panel" style="margin-bottom:18px"><form class="acdc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'acdc_marketing_save_contact' );
        echo '<input type="hidden" name="action" value="acdc_marketing_save_contact">';
        echo '<input type="hidden" name="marketing_contact[source_key]" value="' . esc_attr( $contact['source_key'] ) . '">';
        echo '<div class="acdc-marketing-segments-form-grid">';
        echo '<p><label>Nom complet</label><input type="text" name="marketing_contact[name]" value="' . esc_attr( $contact['name'] ) . '" required></p>';
        echo '<p><label>Type</label><select name="marketing_contact[type]">';
        foreach ( $type_labels as $type_key => $type_label ) {
          echo '<option value="' . esc_attr( $type_key ) . '" ' . selected( $contact['type'], $type_key, false ) . '>' . esc_html( $type_label ) . '</option>';
        }
        echo '</select></p>';
        echo '<p><label>E-mail</label><input type="email" name="marketing_contact[email]" value="' . esc_attr( $contact['email'] ) . '"></p>';
        echo '<p><label>Téléphone</label><input type="text" name="marketing_contact[phone]" value="' . esc_attr( isset( $contact['phone'] ) ? $contact['phone'] : '' ) . '"></p>';
        echo '<p><label>Entreprise / organisme</label><input type="text" name="marketing_contact[company]" value="' . esc_attr( isset( $contact['company'] ) ? $contact['company'] : '' ) . '"></p>';
        echo '<p><label>Activation marketing</label><select name="marketing_contact[enabled]"><option value="1" ' . selected( ! empty( $contact['enabled'] ) ? '1' : '0', '1', false ) . '>Activé</option><option value="0" ' . selected( ! empty( $contact['enabled'] ) ? '1' : '0', '0', false ) . '>Inactif</option></select></p>';
        echo '<p><label>Liste principale</label><select name="marketing_contact[primary_list_id]"><option value="">Sélectionner</option>';
        foreach ( $lists_map as $list_id => $list_name ) {
          echo '<option value="' . esc_attr( $list_id ) . '" ' . selected( isset( $contact['primary_list_id'] ) ? $contact['primary_list_id'] : '', $list_id, false ) . '>' . esc_html( $list_name ) . '</option>';
        }
        echo '</select></p>';
        echo '<style>.acdc-checkbox-list{align-content:start;}.acdc-checkbox-list label{display:flex !important;align-items:center;gap:8px;margin:0 !important;padding:6px 8px;border-radius:8px;cursor:pointer;font-weight:500;line-height:1.3;}.acdc-checkbox-list label:hover{background:#faf2e2;}.acdc-checkbox-list input[type="checkbox"]{width:16px !important;height:16px !important;min-height:0 !important;max-width:16px !important;min-width:0 !important;padding:0 !important;margin:0 !important;border-radius:4px !important;flex:0 0 auto !important;accent-color:#8b5b23;}.acdc-checkbox-list label span{flex:1 1 auto;text-align:left;}</style>';
        echo '<div><label>Listes liées</label><div class="acdc-checkbox-list" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;border:1px solid var(--acdc-border);border-radius:10px;padding:10px;max-height:180px;overflow:auto">';
        if ( empty( $lists_map ) ) {
          echo '<p style="margin:0;color:#1E4777">Aucune liste créée pour le moment.</p>';
        } else {
          foreach ( $lists_map as $list_id => $list_name ) {
            $checked = ! empty( $contact['lists'] ) && is_array( $contact['lists'] ) && in_array( (string) $list_id, array_map( 'strval', $contact['lists'] ), true );
            echo '<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="marketing_contact[lists][]" value="' . esc_attr( $list_id ) . '" ' . checked( $checked, true, false ) . '> <span>' . esc_html( $list_name ) . '</span></label>';
          }
        }
        echo '</div></div>';
        echo '<div><label>Étiquettes liées</label><div class="acdc-checkbox-list" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;border:1px solid var(--acdc-border);border-radius:10px;padding:10px;max-height:180px;overflow:auto">';
        if ( empty( $tags_map ) ) {
          echo '<p style="margin:0;color:#1E4777">Aucune étiquette créée pour le moment.</p>';
        } else {
          foreach ( $tags_map as $tag_id => $tag_name ) {
            $checked = ! empty( $contact['tags'] ) && is_array( $contact['tags'] ) && in_array( (string) $tag_id, array_map( 'strval', $contact['tags'] ), true );
            echo '<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="marketing_contact[tags][]" value="' . esc_attr( $tag_id ) . '" ' . checked( $checked, true, false ) . '> <span>' . esc_html( $tag_name ) . '</span></label>';
          }
        }
        echo '</div></div>';
        echo '<p><label>Score marketing</label><input type="number" min="0" max="999" name="marketing_contact[score]" value="' . esc_attr( (string) ( isset( $contact['score'] ) ? $contact['score'] : 0 ) ) . '"></p>';
        echo '<p><label>Dernier engagement</label><input type="datetime-local" name="marketing_contact[last_engagement]" value="' . esc_attr( ! empty( $contact['last_engagement'] ) ? str_replace( ' ', 'T', substr( $contact['last_engagement'], 0, 16 ) ) : '' ) . '"></p>';
        echo '</div>';
        echo '<h3>Statuts par catégorie</h3>';
        echo '<div class="acdc-marketing-segments-form-grid">';
        foreach ( $categories as $cat_key => $cat_label ) {
          echo '<p><label>' . esc_html( $cat_label ) . '</label><select name="marketing_contact[statuses][' . esc_attr( $cat_key ) . ']">';
          foreach ( $status_labels as $status_key => $status_label ) {
            echo '<option value="' . esc_attr( $status_key ) . '" ' . selected( isset( $statuses[ $cat_key ] ) ? $statuses[ $cat_key ] : 'not_requested', $status_key, false ) . '>' . esc_html( $status_label ) . '</option>';
          }
          echo '</select></p>';
        }
        echo '</div>';
        echo '<p><label>Note interne</label><textarea name="marketing_contact[notes]" rows="4">' . esc_textarea( isset( $contact['notes'] ) ? $contact['notes'] : '' ) . '</textarea></p>';
        echo '<p><button type="submit" class="acdc-button acdc-button-primary">Enregistrer le contact marketing</button></p>';
        echo '</form></div>';
      }
    }

    echo '<div class="acdc-panel" style="margin-bottom:18px"><form class="acdc-form" method="get">';
    echo '<input type="hidden" name="tab" value="marketing_contacts">';
    echo '<div style="display:grid;grid-template-columns:2fr repeat(3,minmax(0,1fr));gap:16px;align-items:end">';
    echo '<p><label>Recherche</label><input type="text" name="marketing_search" value="' . esc_attr( $search ) . '" placeholder="Nom, e-mail, téléphone, entreprise"></p>';
    echo '<p><label>Type</label><select name="marketing_type_filter"><option value="">Tous</option>';
    foreach ( $type_labels as $type_key => $type_label ) {
      echo '<option value="' . esc_attr( $type_key ) . '" ' . selected( $filter_type, $type_key, false ) . '>' . esc_html( $type_label ) . '</option>';
    }
    echo '</select></p>';
    echo '<p><label>Activation</label><select name="marketing_enabled_filter"><option value="">Tous</option><option value="enabled" ' . selected( $filter_enabled, 'enabled', false ) . '>Activés</option><option value="disabled" ' . selected( $filter_enabled, 'disabled', false ) . '>Inactifs</option></select></p>';
    echo '<p><label>Statut marketing</label><select name="marketing_status_filter"><option value="">Tous</option>';
    foreach ( $status_labels as $status_key => $status_label ) {
      echo '<option value="' . esc_attr( $status_key ) . '" ' . selected( $filter_status, $status_key, false ) . '>' . esc_html( $status_label ) . '</option>';
    }
    echo '</select></p>';
    echo '</div>';
    echo '<p><button type="submit" class="acdc-button acdc-button-primary">Filtrer</button> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $this->portal_page_url( array( 'tab' => 'marketing_contacts' ) ) ) . '">Réinitialiser</a></p>';
    echo '</form></div>';

    $filtered = array();
    foreach ( $contacts as $contact ) {
      $matches = true;
      if ( '' !== $search ) {
        $haystack = strtolower( implode( ' ', array( isset( $contact['name'] ) ? $contact['name'] : '', isset( $contact['email'] ) ? $contact['email'] : '', isset( $contact['phone'] ) ? $contact['phone'] : '', isset( $contact['company'] ) ? $contact['company'] : '' ) ) );
        if ( false === strpos( $haystack, strtolower( $search ) ) ) {
          $matches = false;
        }
      }
      if ( $matches && '' !== $filter_type && ( ! isset( $contact['type'] ) || $contact['type'] !== $filter_type ) ) {
        $matches = false;
      }
      if ( $matches && 'enabled' === $filter_enabled && empty( $contact['enabled'] ) ) {
        $matches = false;
      }
      if ( $matches && 'disabled' === $filter_enabled && ! empty( $contact['enabled'] ) ) {
        $matches = false;
      }
      $marketing_status = isset( $contact['statuses']['marketing'] ) ? $contact['statuses']['marketing'] : 'not_requested';
      if ( $matches && '' !== $filter_status && $marketing_status !== $filter_status ) {
        $matches = false;
      }
      if ( $matches ) {
        $filtered[] = $contact;
      }
    }

    echo '<div class="acdc-panel"><table class="acdc-table"><thead><tr><th>Contact</th><th>Source</th><th>Type</th><th>E-mail</th><th>Activation</th><th>Statut marketing</th><th>Listes / étiquettes</th><th>Score</th><th>Dernier engagement</th><th>Actions</th></tr></thead><tbody>';
    foreach ( $filtered as $contact ) {
      $statuses = isset( $contact['statuses'] ) && is_array( $contact['statuses'] ) ? $contact['statuses'] : array();
      $status = isset( $statuses['marketing'] ) ? $statuses['marketing'] : 'not_requested';
      $toggle_url = wp_nonce_url( add_query_arg( array( 'action' => 'acdc_marketing_toggle_contact', 'source_key' => rawurlencode( $contact['source_key'] ) ), admin_url( 'admin-post.php' ) ), 'acdc_marketing_toggle_contact' );
      $view_url = $this->portal_page_url( array( 'tab' => 'marketing_contacts', 'action' => 'view', 'source_key' => $contact['source_key'] ) );
      $edit_url = $this->portal_page_url( array( 'tab' => 'marketing_contacts', 'action' => 'edit', 'source_key' => $contact['source_key'] ) );
      echo '<tr>';
      echo '<td><strong>' . esc_html( $contact['name'] ) . '</strong><br><small>' . esc_html( isset( $contact['company'] ) ? $contact['company'] : '' ) . '</small></td>';
      echo '<td>' . esc_html( isset( $source_labels[ $contact['source_type'] ] ) ? $source_labels[ $contact['source_type'] ] : $contact['source_type'] ) . '</td>';
      echo '<td>' . esc_html( isset( $type_labels[ $contact['type'] ] ) ? $type_labels[ $contact['type'] ] : ucfirst( $contact['type'] ) ) . '</td>';
      echo '<td>' . esc_html( $contact['email'] ) . '<br><small>' . esc_html( isset( $contact['phone'] ) ? $contact['phone'] : '' ) . '</small></td>';
      echo '<td>' . ( ! empty( $contact['enabled'] ) ? 'Activé' : 'Inactif' ) . '</td>';
      echo '<td>' . esc_html( isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status ) . '</td>';
      echo '<td><small>' . esc_html( $this->marketing_contact_related_labels( isset( $contact['lists'] ) ? $contact['lists'] : array(), 'lists' ) ) . '</small><br><small>' . esc_html( $this->marketing_contact_related_labels( isset( $contact['tags'] ) ? $contact['tags'] : array(), 'tags' ) ) . '</small></td>';
      echo '<td>' . esc_html( (string) ( isset( $contact['score'] ) ? $contact['score'] : 0 ) ) . '</td>';
      echo '<td>' . esc_html( isset( $contact['last_engagement'] ) ? $contact['last_engagement'] : '' ) . '</td>';
      echo '<td><a class="acdc-button acdc-button-secondary" href="' . esc_url( $view_url ) . '">Voir</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $edit_url ) . '">Modifier</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $toggle_url ) . '">' . ( ! empty( $contact['enabled'] ) ? 'Désactiver' : 'Activer' ) . '</a></td>';
      echo '</tr>';
    }
    if ( empty( $filtered ) ) {
      echo '<tr><td colspan="10">Aucun contact ne correspond aux filtres actuels.</td></tr>';
    }
    echo '</tbody></table></div>';
  }


  private function render_front_marketing_imports_tab() {

    $imports = $this->get_marketing_store( 'imports', array() );
    $contacts = $this->get_marketing_contacts_index();
    $enabled_contacts = array_filter( $contacts, function( $item ) { return ! empty( $item['enabled'] ); } );
    $import_contacts = array_filter( $contacts, function( $item ) { return isset( $item['source_type'] ) && 'import' === $item['source_type']; } );
    $template_url = ACDC_OF_SAAS_URL . 'assets/templates/modele-import-contacts-marketing.csv';

    $processed_total = 0;
    $created_total = 0;
    $updated_total = 0;
    foreach ( $imports as $item ) {
      $processed_total += isset( $item['count'] ) ? (int) $item['count'] : 0;
      $created_total += isset( $item['created_count'] ) ? (int) $item['created_count'] : ( isset( $item['count'] ) ? (int) $item['count'] : 0 );
      $updated_total += isset( $item['updated_count'] ) ? (int) $item['updated_count'] : 0;
    }

    $this->render_marketing_header( 'Imports', 'Importez vos contacts marketing en CSV, sans casser les fiches existantes du plugin et sans rompre la logique validée du module.', array(
      array( 'label' => 'Voir les contacts marketing', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_contacts' ) ), 'primary' => true ),
      array( 'label' => 'Télécharger le modèle CSV', 'url' => esc_url( $template_url ) ),
    ) );
    $this->render_marketing_cards( array(
      array( 'label' => 'Imports enregistrés', 'value' => count( $imports ), 'help' => 'Historique court conservé dans le module.' ),
      array( 'label' => 'Lignes traitées', 'value' => $processed_total, 'help' => 'Total cumulé des imports de contacts.' ),
      array( 'label' => 'Créations', 'value' => $created_total, 'help' => 'Nouveaux contacts marketing issus d’un import.' ),
      array( 'label' => 'Contacts importés actifs', 'value' => count( $import_contacts ) . ' / ' . count( $enabled_contacts ), 'help' => 'Part des contacts importés dans la base marketing active.' ),
    ) );

    echo '<div style="display:grid;grid-template-columns:1.15fr 0.85fr;gap:18px;align-items:start">';

    echo '<div style="display:grid;gap:18px">';
    echo '<div class="acdc-panel" style="margin-bottom:0"><form class="acdc-form" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    wp_nonce_field( 'acdc_marketing_import_contacts' );
    echo '<input type="hidden" name="action" value="acdc_marketing_import_contacts">';
    echo '<div class="acdc-marketing-segments-form-panel" style="box-shadow:none;border:none;padding:0;margin:0">';
    echo '<h2 style="margin:0 0 6px">Importer un fichier CSV</h2>';
    echo '<p style="margin:0 0 14px;color:#1E4777">Formats acceptés : <strong>.csv</strong>. Délimiteurs reconnus : virgule ou point-virgule. Les lignes sans e-mail valide sont ignorées.</p>';
    echo '<div class="acdc-marketing-segments-form-grid">';
    echo '<p><label>Fichier CSV</label><input type="file" name="marketing_import_file" accept=".csv,text/csv,text/plain" required></p>';
    echo '<p><label>Règle appliquée</label><input type="text" value="Activation marketing + affectation à Prospects + étiquettes import CSV / donnée à nettoyer" readonly></p>';
    echo '</div>';
    echo '<p class="acdc-checkbox-line"><label><input type="checkbox" checked disabled> Le module conserve les fiches existantes et met à jour le contact si l’e-mail est déjà connu.</label></p>';
    echo '<p><button type="submit" class="acdc-button acdc-button-primary">Importer les contacts</button></p>';
    echo '</div>';
    echo '</form></div>';

    echo '<div class="acdc-panel" style="margin-bottom:0">';
    echo '<h2 style="margin-top:0">Structure attendue</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Colonne</th><th>Obligatoire</th><th>Exemple</th></tr></thead><tbody>';
    $columns = array(
      array( 'email', 'Oui', 'contact@entreprise.fr' ),
      array( 'first_name', 'Non', 'David' ),
      array( 'last_name', 'Non', 'Contal' ),
      array( 'phone', 'Non', '06 00 00 00 00' ),
      array( 'company', 'Non', 'ACDC Formation' ),
      array( 'type', 'Non', 'prospect / client / apprenant / ancien_apprenant / entreprise / financeur / formateur / independant' ),
    );
    foreach ( $columns as $column ) {
      echo '<tr><td><strong>' . esc_html( $column[0] ) . '</strong></td><td>' . esc_html( $column[1] ) . '</td><td>' . esc_html( $column[2] ) . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<p style="margin:12px 0 0;color:#1E4777">Alias tolérés automatiquement : <strong>prénom / nom / téléphone / courriel / société / organisme</strong>.</p>';
    echo '</div>';
    echo '</div>';

    echo '<div style="display:grid;gap:18px">';
    echo '<div class="acdc-panel" style="margin-bottom:0"><h2 style="margin-top:0">Règles métier appliquées</h2><ul style="margin:0;padding-left:18px;color:#1E4777">';
    echo '<li>Base marketing liée aux fiches existantes, sans doublon inutile.</li>';
    echo '<li>Activation marketing immédiate du contact importé.</li>';
    echo '<li>Liste principale affectée : <strong>Prospects</strong>.</li>';
    echo '<li>Étiquettes automatiques : <strong>Source : import CSV</strong> et <strong>Donnée à nettoyer</strong>.</li>';
    echo '<li>Statut marketing initial : <strong>non demandé</strong>.</li>';
    echo '</ul></div>';

    echo '<div class="acdc-panel" style="margin-bottom:0"><h2 style="margin-top:0">Dernier import</h2>';
    if ( ! empty( $imports ) ) {
      $last = reset( $imports );
      echo '<p style="margin:0 0 8px"><strong>Fichier :</strong> ' . esc_html( isset( $last['name'] ) ? $last['name'] : '' ) . '</p>';
      echo '<p style="margin:0 0 8px"><strong>Date :</strong> ' . esc_html( isset( $last['created_at'] ) ? $last['created_at'] : '' ) . '</p>';
      echo '<p style="margin:0 0 8px"><strong>Lignes traitées :</strong> ' . esc_html( (string) ( isset( $last['count'] ) ? $last['count'] : 0 ) ) . '</p>';
      echo '<p style="margin:0 0 8px"><strong>Créations :</strong> ' . esc_html( (string) ( isset( $last['created_count'] ) ? $last['created_count'] : ( isset( $last['count'] ) ? $last['count'] : 0 ) ) ) . '</p>';
      echo '<p style="margin:0 0 8px"><strong>Mises à jour :</strong> ' . esc_html( (string) ( isset( $last['updated_count'] ) ? $last['updated_count'] : 0 ) ) . '</p>';
      echo '<p style="margin:0"><strong>Lignes ignorées :</strong> ' . esc_html( (string) ( isset( $last['skipped_count'] ) ? $last['skipped_count'] : 0 ) ) . '</p>';
    } else {
      echo '<p style="margin:0;color:#1E4777">Aucun import n’a encore été lancé dans ce module.</p>';
    }
    echo '</div>';
    echo '</div>';

    echo '</div>';

    echo '<div class="acdc-panel" style="margin-top:18px"><h2 style="margin-top:0">Historique des imports</h2><table class="acdc-table"><thead><tr><th>Date</th><th>Fichier</th><th>Traité</th><th>Créations</th><th>Mises à jour</th><th>Ignorées</th><th>Contexte</th></tr></thead><tbody>';
    foreach ( $imports as $item ) {
      echo '<tr><td>' . esc_html( isset( $item['created_at'] ) ? $item['created_at'] : '' ) . '</td><td>' . esc_html( isset( $item['name'] ) ? $item['name'] : '' ) . '</td><td>' . esc_html( (string) ( isset( $item['count'] ) ? $item['count'] : 0 ) ) . '</td><td>' . esc_html( (string) ( isset( $item['created_count'] ) ? $item['created_count'] : ( isset( $item['count'] ) ? $item['count'] : 0 ) ) ) . '</td><td>' . esc_html( (string) ( isset( $item['updated_count'] ) ? $item['updated_count'] : 0 ) ) . '</td><td>' . esc_html( (string) ( isset( $item['skipped_count'] ) ? $item['skipped_count'] : 0 ) ) . '</td><td>' . esc_html( isset( $item['context'] ) ? $item['context'] : '' ) . '</td></tr>';
    }
    if ( empty( $imports ) ) { echo '<tr><td colspan="7">Aucun import enregistré.</td></tr>'; }
    echo '</tbody></table></div>';
  }


  private function render_front_marketing_statistics_tab() {
    $contacts = $this->get_marketing_contacts_index();
    $logs = $this->get_marketing_store( 'logs', array() );
    $queue = $this->get_marketing_store( 'queue', array() );
    $unsubs = $this->get_marketing_store( 'unsubscribes', array() );
    $campaigns = $this->get_marketing_store( 'campaigns', array() );
    $scenarios = $this->get_marketing_store( 'scenarios', array() );
    $forms = $this->get_marketing_store( 'forms', array() );
    $imports = $this->get_marketing_store( 'imports', array() );
    $lists = $this->get_marketing_store( 'lists', array() );
    $segments = $this->get_marketing_store( 'segments', array() );
    $tags = $this->get_marketing_store( 'tags', array() );
    $settings = $this->get_marketing_store( 'settings', array() );
    $status_labels = $this->marketing_category_statuses();
    $type_labels = $this->marketing_type_labels();

    $enabled_contacts = array_filter( $contacts, function( $item ) {
      return ! empty( $item['enabled'] );
    } );
    $click_engaged_contacts = array_filter( $contacts, function( $item ) {
      return ! empty( $item['last_email_click_at'] );
    } );
    $inactive_contacts = array_filter( $contacts, function( $item ) {
      return empty( $item['last_engagement'] ) && empty( $item['last_email_click_at'] ) && empty( $item['last_email_sent_at'] );
    } );

    $contacts_by_type = array();
    foreach ( $type_labels as $key => $label ) {
      $contacts_by_type[ $key ] = 0;
    }

    $consent_counts = array(
      'marketing' => array_fill_keys( array_keys( $status_labels ), 0 ),
      'commercial' => array_fill_keys( array_keys( $status_labels ), 0 ),
      'followup' => array_fill_keys( array_keys( $status_labels ), 0 ),
      'administrative' => array_fill_keys( array_keys( $status_labels ), 0 ),
      'financeur' => array_fill_keys( array_keys( $status_labels ), 0 ),
      'internal' => array_fill_keys( array_keys( $status_labels ), 0 ),
    );

    $score_total = 0;
    foreach ( $contacts as $contact ) {
      $type = isset( $contact['type'] ) ? (string) $contact['type'] : 'contact';
      if ( ! isset( $contacts_by_type[ $type ] ) ) {
        $contacts_by_type[ $type ] = 0;
      }
      $contacts_by_type[ $type ]++;
      $score_total += isset( $contact['score_value'] ) ? (int) $contact['score_value'] : ( isset( $contact['score'] ) ? (int) $contact['score'] : 0 );
      $statuses = isset( $contact['statuses'] ) && is_array( $contact['statuses'] ) ? $contact['statuses'] : array();
      foreach ( $consent_counts as $category => $counts ) {
        $value = isset( $statuses[ $category ] ) ? (string) $statuses[ $category ] : 'not_requested';
        if ( ! isset( $consent_counts[ $category ][ $value ] ) ) {
          $consent_counts[ $category ][ $value ] = 0;
        }
        $consent_counts[ $category ][ $value ]++;
      }
    }

    $campaign_status_counts = array( 'draft' => 0, 'planned' => 0, 'active' => 0, 'archived' => 0 );
    foreach ( $campaigns as $campaign ) {
      $status = isset( $campaign['status'] ) ? (string) $campaign['status'] : 'draft';
      if ( ! isset( $campaign_status_counts[ $status ] ) ) {
        $campaign_status_counts[ $status ] = 0;
      }
      $campaign_status_counts[ $status ]++;
    }

    $scenario_status_counts = array( 'draft' => 0, 'active' => 0, 'planned' => 0, 'archived' => 0 );
    foreach ( $scenarios as $scenario ) {
      $status = isset( $scenario['status'] ) ? (string) $scenario['status'] : 'draft';
      if ( ! isset( $scenario_status_counts[ $status ] ) ) {
        $scenario_status_counts[ $status ] = 0;
      }
      $scenario_status_counts[ $status ]++;
    }

    $queue_status_counts = array();
    foreach ( $queue as $item ) {
      $status = isset( $item['status'] ) ? (string) $item['status'] : 'waiting';
      if ( ! isset( $queue_status_counts[ $status ] ) ) {
        $queue_status_counts[ $status ] = 0;
      }
      $queue_status_counts[ $status ]++;
    }

    $log_type_counts = array();
    foreach ( $logs as $log ) {
      $type = isset( $log['type'] ) ? (string) $log['type'] : 'info';
      if ( ! isset( $log_type_counts[ $type ] ) ) {
        $log_type_counts[ $type ] = 0;
      }
      $log_type_counts[ $type ]++;
    }

    $unsubscribe_category_counts = array();
    foreach ( $unsubs as $item ) {
      $category = isset( $item['category'] ) ? (string) $item['category'] : 'non_precise';
      if ( ! isset( $unsubscribe_category_counts[ $category ] ) ) {
        $unsubscribe_category_counts[ $category ] = 0;
      }
      $unsubscribe_category_counts[ $category ]++;
    }

    $import_contacts = array_filter( $contacts, function( $item ) {
      return isset( $item['source_type'] ) && 'import' === $item['source_type'];
    } );

    $processed_total = 0;
    $created_total = 0;
    $updated_total = 0;
    $skipped_total = 0;
    foreach ( $imports as $item ) {
      $processed_total += isset( $item['count'] ) ? (int) $item['count'] : 0;
      $created_total += isset( $item['created_count'] ) ? (int) $item['created_count'] : 0;
      $updated_total += isset( $item['updated_count'] ) ? (int) $item['updated_count'] : 0;
      $skipped_total += isset( $item['skipped_count'] ) ? (int) $item['skipped_count'] : 0;
    }

    arsort( $contacts_by_type );
    arsort( $log_type_counts );
    arsort( $unsubscribe_category_counts );

    $average_score = ! empty( $contacts ) ? round( $score_total / count( $contacts ), 1 ) : 0;
    $marketing_authorized = isset( $consent_counts['marketing']['allowed'] ) ? (int) $consent_counts['marketing']['allowed'] : 0;
    $marketing_refused = isset( $consent_counts['marketing']['refused'] ) ? (int) $consent_counts['marketing']['refused'] : 0;
    $marketing_pending = isset( $consent_counts['marketing']['pending'] ) ? (int) $consent_counts['marketing']['pending'] : 0;

    $this->render_marketing_header( 'Statistiques', 'Tableau de bord consolidé du module marketing : base de contacts, consentements, campagnes, scénarios, imports, file d’attente et journaux techniques.', array(
      array( 'label' => 'Voir les campagnes', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_campaigns' ) ), 'primary' => true ),
      array( 'label' => 'Voir les journaux', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_logs' ) ) ),
    ) );

    $this->render_marketing_cards( array(
      array( 'label' => 'Contacts marketing actifs', 'value' => count( $enabled_contacts ), 'help' => 'Contacts activés dans la base marketing.' ),
      array( 'label' => 'Campagnes', 'value' => count( $campaigns ), 'help' => 'Campagnes simples, planifiées ou archivées.' ),
      array( 'label' => 'Scénarios', 'value' => count( $scenarios ), 'help' => 'Scénarios automatiques stockés dans le module.' ),
      array( 'label' => 'État SMTP', 'value' => $this->get_marketing_smtp_status_label(), 'help' => 'Lecture de l’état de WP Mail SMTP.' ),
    ) );

    echo '<div style="display:grid;grid-template-columns:1.15fr 0.85fr;gap:18px;align-items:start">';

    echo '<div style="display:grid;gap:18px">';

    echo '<div class="acdc-panel" style="margin-bottom:0">';
    echo '<h2 style="margin-top:0">Vue d’ensemble</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Indicateur</th><th>Valeur</th><th>Lecture</th></tr></thead><tbody>';
    echo '<tr><td><strong>Base totale</strong></td><td>' . esc_html( (string) count( $contacts ) ) . '</td><td>Nombre total de fiches marketing actives ou non.</td></tr>';
    echo '<tr><td><strong>Contacts activés</strong></td><td>' . esc_html( (string) count( $enabled_contacts ) ) . '</td><td>Fiches activées manuellement pour le marketing.</td></tr>';
    echo '<tr><td><strong>Contacts avec clic suivi</strong></td><td>' . esc_html( (string) count( $click_engaged_contacts ) ) . '</td><td>Mesure minimale d’engagement, sans pixel d’ouverture.</td></tr>';
    echo '<tr><td><strong>Contacts sans engagement</strong></td><td>' . esc_html( (string) count( $inactive_contacts ) ) . '</td><td>Fiches sans trace récente d’envoi ou de clic.</td></tr>';
    echo '<tr><td><strong>Score moyen</strong></td><td>' . esc_html( number_format_i18n( $average_score, 1 ) ) . '</td><td>Score simple consolidé sur la base marketing.</td></tr>';
    echo '<tr><td><strong>File d’attente</strong></td><td>' . esc_html( (string) count( $queue ) ) . '</td><td>Éléments planifiés, en pause ou à reprendre.</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:0">';
    echo '<h2 style="margin-top:0">Répartition des contacts</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Type</th><th>Volume</th><th>Part</th></tr></thead><tbody>';
    foreach ( $contacts_by_type as $type => $count ) {
      if ( $count <= 0 ) {
        continue;
      }
      $ratio = count( $contacts ) > 0 ? round( ( $count / count( $contacts ) ) * 100, 1 ) : 0;
      echo '<tr><td>' . esc_html( isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : ucfirst( str_replace( '_', ' ', $type ) ) ) . '</td><td>' . esc_html( (string) $count ) . '</td><td>' . esc_html( number_format_i18n( $ratio, 1 ) ) . ' %</td></tr>';
    }
    if ( empty( $contacts ) ) {
      echo '<tr><td colspan="3">Aucun contact marketing enregistré.</td></tr>';
    }
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:0">';
    echo '<h2 style="margin-top:0">Pilotage campagnes et scénarios</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Bloc</th><th>Brouillon</th><th>Planifié</th><th>Actif</th><th>Archivé</th></tr></thead><tbody>';
    echo '<tr><td><strong>Campagnes</strong></td><td>' . esc_html( (string) ( isset( $campaign_status_counts['draft'] ) ? $campaign_status_counts['draft'] : 0 ) ) . '</td><td>' . esc_html( (string) ( isset( $campaign_status_counts['planned'] ) ? $campaign_status_counts['planned'] : 0 ) ) . '</td><td>' . esc_html( (string) ( isset( $campaign_status_counts['active'] ) ? $campaign_status_counts['active'] : 0 ) ) . '</td><td>' . esc_html( (string) ( isset( $campaign_status_counts['archived'] ) ? $campaign_status_counts['archived'] : 0 ) ) . '</td></tr>';
    echo '<tr><td><strong>Scénarios automatiques</strong></td><td>' . esc_html( (string) ( isset( $scenario_status_counts['draft'] ) ? $scenario_status_counts['draft'] : 0 ) ) . '</td><td>' . esc_html( (string) ( isset( $scenario_status_counts['planned'] ) ? $scenario_status_counts['planned'] : 0 ) ) . '</td><td>' . esc_html( (string) ( isset( $scenario_status_counts['active'] ) ? $scenario_status_counts['active'] : 0 ) ) . '</td><td>' . esc_html( (string) ( isset( $scenario_status_counts['archived'] ) ? $scenario_status_counts['archived'] : 0 ) ) . '</td></tr>';
    echo '</tbody></table>';
    echo '<p style="margin:12px 0 0;color:#1E4777">Formulaires : <strong>' . esc_html( (string) count( $forms ) ) . '</strong> · Listes : <strong>' . esc_html( (string) count( $lists ) ) . '</strong> · Segments : <strong>' . esc_html( (string) count( $segments ) ) . '</strong> · Étiquettes : <strong>' . esc_html( (string) count( $tags ) ) . '</strong>.</p>';
    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:0">';
    echo '<h2 style="margin-top:0">Imports et activité de base</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Indicateur</th><th>Valeur</th><th>Détail</th></tr></thead><tbody>';
    echo '<tr><td><strong>Imports enregistrés</strong></td><td>' . esc_html( (string) count( $imports ) ) . '</td><td>Historique du bloc Imports.</td></tr>';
    echo '<tr><td><strong>Lignes traitées</strong></td><td>' . esc_html( (string) $processed_total ) . '</td><td>Total cumulé des fichiers importés.</td></tr>';
    echo '<tr><td><strong>Créations</strong></td><td>' . esc_html( (string) $created_total ) . '</td><td>Nouveaux contacts créés depuis un import.</td></tr>';
    echo '<tr><td><strong>Mises à jour</strong></td><td>' . esc_html( (string) $updated_total ) . '</td><td>Fiches existantes enrichies via un import.</td></tr>';
    echo '<tr><td><strong>Lignes ignorées</strong></td><td>' . esc_html( (string) $skipped_total ) . '</td><td>E-mails invalides, doublons incomplets ou lignes rejetées.</td></tr>';
    echo '<tr><td><strong>Contacts issus d’un import</strong></td><td>' . esc_html( (string) count( $import_contacts ) ) . '</td><td>Source détectée : import.</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    echo '</div>';

    echo '<div style="display:grid;gap:18px">';

    echo '<div class="acdc-panel" style="margin-bottom:0">';
    echo '<h2 style="margin-top:0">Consentement marketing</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Statut</th><th>Volume</th></tr></thead><tbody>';
    echo '<tr><td><strong>Autorisé</strong></td><td>' . esc_html( (string) $marketing_authorized ) . '</td></tr>';
    echo '<tr><td><strong>En attente</strong></td><td>' . esc_html( (string) $marketing_pending ) . '</td></tr>';
    echo '<tr><td><strong>Refusé</strong></td><td>' . esc_html( (string) $marketing_refused ) . '</td></tr>';
    echo '<tr><td><strong>Non demandé</strong></td><td>' . esc_html( (string) ( isset( $consent_counts['marketing']['not_requested'] ) ? $consent_counts['marketing']['not_requested'] : 0 ) ) . '</td></tr>';
    echo '<tr><td><strong>Non applicable</strong></td><td>' . esc_html( (string) ( isset( $consent_counts['marketing']['not_applicable'] ) ? $consent_counts['marketing']['not_applicable'] : 0 ) ) . '</td></tr>';
    echo '</tbody></table>';
    echo '<p style="margin:12px 0 0;color:#1E4777">Mode de désinscription : <strong>' . esc_html( isset( $settings['unsubscribe_mode'] ) && 'category' === $settings['unsubscribe_mode'] ? 'par catégorie' : 'à vérifier' ) . '</strong>.</p>';
    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:0">';
    echo '<h2 style="margin-top:0">File d’attente</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Statut</th><th>Volume</th></tr></thead><tbody>';
    if ( ! empty( $queue_status_counts ) ) {
      foreach ( $queue_status_counts as $status => $count ) {
        echo '<tr><td>' . esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ) . '</td><td>' . esc_html( (string) $count ) . '</td></tr>';
      }
    } else {
      echo '<tr><td colspan="2">Aucun élément en file d’attente.</td></tr>';
    }
    echo '</tbody></table>';
    echo '<p style="margin:12px 0 0;color:#1E4777">Limites actuelles : <strong>' . esc_html( (string) ( isset( $settings['limit_per_minute'] ) ? $settings['limit_per_minute'] : 30 ) ) . '/minute</strong> et <strong>' . esc_html( (string) ( isset( $settings['limit_per_hour'] ) ? $settings['limit_per_hour'] : 300 ) ) . '/heure</strong>.</p>';
    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:0">';
    echo '<h2 style="margin-top:0">Désinscriptions</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Catégorie</th><th>Volume</th></tr></thead><tbody>';
    if ( ! empty( $unsubscribe_category_counts ) ) {
      foreach ( $unsubscribe_category_counts as $category => $count ) {
        echo '<tr><td>' . esc_html( ucfirst( str_replace( '_', ' ', $category ) ) ) . '</td><td>' . esc_html( (string) $count ) . '</td></tr>';
      }
    } else {
      echo '<tr><td colspan="2">Aucune désinscription enregistrée.</td></tr>';
    }
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:0">';
    echo '<h2 style="margin-top:0">Journaux d’activité</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Type</th><th>Volume</th></tr></thead><tbody>';
    if ( ! empty( $log_type_counts ) ) {
      foreach ( array_slice( $log_type_counts, 0, 6, true ) as $type => $count ) {
        echo '<tr><td>' . esc_html( ucfirst( str_replace( '_', ' ', $type ) ) ) . '</td><td>' . esc_html( (string) $count ) . '</td></tr>';
      }
    } else {
      echo '<tr><td colspan="2">Aucun journal enregistré.</td></tr>';
    }
    echo '</tbody></table>';
    echo '<p style="margin:12px 0 0;color:#1E4777">Total des journaux : <strong>' . esc_html( (string) count( $logs ) ) . '</strong>.</p>';
    echo '</div>';

    echo '</div>';

    echo '</div>';

    echo '<div class="acdc-panel" style="margin-top:18px">';
    echo '<h2 style="margin-top:0">Derniers événements du module</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Date</th><th>Type</th><th>Message</th><th>Contexte</th></tr></thead><tbody>';
    foreach ( array_slice( $logs, 0, 10 ) as $log ) {
      echo '<tr><td>' . esc_html( isset( $log['date'] ) ? $log['date'] : '' ) . '</td><td>' . esc_html( isset( $log['type'] ) ? ucfirst( $log['type'] ) : '' ) . '</td><td>' . esc_html( isset( $log['message'] ) ? $log['message'] : '' ) . '</td><td><small>' . esc_html( wp_json_encode( isset( $log['context'] ) ? $log['context'] : array(), JSON_UNESCAPED_UNICODE ) ) . '</small></td></tr>';
    }
    if ( empty( $logs ) ) {
      echo '<tr><td colspan="4">Aucun événement récent enregistré.</td></tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
  }


  private function render_front_marketing_logs_tab() {
    $logs = $this->get_marketing_store( 'logs', array() );
    $search = isset( $_GET['marketing_log_search'] ) ? sanitize_text_field( wp_unslash( $_GET['marketing_log_search'] ) ) : '';
    $type_filter = isset( $_GET['marketing_log_type'] ) ? sanitize_key( wp_unslash( $_GET['marketing_log_type'] ) ) : '';

    $counts_by_type = array(
      'success' => 0,
      'info'    => 0,
      'warning' => 0,
      'error'   => 0,
    );
    $imports_count = 0;
    $entity_counts = array();
    $recent_count = 0;
    $last_event_date = '';

    foreach ( $logs as $log ) {
      $type = isset( $log['type'] ) ? sanitize_key( $log['type'] ) : 'info';
      if ( isset( $counts_by_type[ $type ] ) ) {
        $counts_by_type[ $type ]++;
      }
      $context = isset( $log['context'] ) && is_array( $log['context'] ) ? $log['context'] : array();
      if ( ! empty( $context['entity'] ) ) {
        $entity = sanitize_key( $context['entity'] );
        if ( ! isset( $entity_counts[ $entity ] ) ) {
          $entity_counts[ $entity ] = 0;
        }
        $entity_counts[ $entity ]++;
      }
      if ( false !== stripos( isset( $log['message'] ) ? (string) $log['message'] : '', 'import' ) ) {
        $imports_count++;
      }
      if ( empty( $last_event_date ) && ! empty( $log['date'] ) ) {
        $last_event_date = (string) $log['date'];
      }
      if ( ! empty( $log['date'] ) ) {
        $timestamp = strtotime( (string) $log['date'] );
        if ( false !== $timestamp && $timestamp >= strtotime( '-7 days' ) ) {
          $recent_count++;
        }
      }
    }

    $filtered_logs = array();
    foreach ( $logs as $log ) {
      $haystack = strtolower(
        trim(
          (string) ( isset( $log['message'] ) ? $log['message'] : '' ) . ' ' .
          wp_json_encode( isset( $log['context'] ) ? $log['context'] : array(), JSON_UNESCAPED_UNICODE )
        )
      );
      if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) {
        continue;
      }
      if ( '' !== $type_filter && ( ! isset( $log['type'] ) || sanitize_key( $log['type'] ) !== $type_filter ) ) {
        continue;
      }
      $filtered_logs[] = $log;
    }

    arsort( $entity_counts );

    $this->render_marketing_header( 'Journaux d’envoi', 'Vue consolidée des événements du module marketing : imports, modifications, campagnes, scénarios, notifications et événements techniques.' );

    $this->render_marketing_cards( array(
      array( 'label' => 'Journaux enregistrés', 'value' => count( $logs ), 'help' => 'Historique global conservé dans le module.' ),
      array( 'label' => 'Derniers 7 jours', 'value' => $recent_count, 'help' => 'Événements datés récents.' ),
      array( 'label' => 'Alertes', 'value' => (int) $counts_by_type['warning'] + (int) $counts_by_type['error'], 'help' => 'Somme des avertissements et erreurs.' ),
      array( 'label' => 'Imports détectés', 'value' => $imports_count, 'help' => 'Journaux liés aux imports CSV du module.' ),
    ) );

    echo '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-bottom:18px">';

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">Répartition par type</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Type</th><th>Volume</th><th>Lecture métier</th></tr></thead><tbody>';
    echo '<tr><td>Succès</td><td>' . esc_html( (string) $counts_by_type['success'] ) . '</td><td>Actions traitées correctement.</td></tr>';
    echo '<tr><td>Information</td><td>' . esc_html( (string) $counts_by_type['info'] ) . '</td><td>Traçabilité standard du module.</td></tr>';
    echo '<tr><td>Avertissement</td><td>' . esc_html( (string) $counts_by_type['warning'] ) . '</td><td>Points à contrôler sans blocage immédiat.</td></tr>';
    echo '<tr><td>Erreur</td><td>' . esc_html( (string) $counts_by_type['error'] ) . '</td><td>Événements techniques ou métier à corriger.</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">Lecture rapide</h2>';
    echo '<table class="acdc-table"><tbody>';
    echo '<tr><th>Dernier événement</th><td>' . esc_html( $last_event_date ? $last_event_date : 'Aucun événement enregistré' ) . '</td></tr>';
    echo '<tr><th>Capacité de conservation</th><td>500 journaux maximum conservés automatiquement.</td></tr>';
    echo '<tr><th>Filtrage disponible</th><td>Recherche texte et tri par type de journal.</td></tr>';
    echo '<tr><th>Utilité de l’écran</th><td>Contrôle, audit interne, vérification des imports et des actions marketing.</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    echo '</div>';

    echo '<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:18px;margin-bottom:18px">';

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">Filtres</h2>';
    echo '<form method="get" class="acdc-filters" style="display:grid;grid-template-columns:2fr 1fr auto;gap:12px;align-items:end">';
    echo '<input type="hidden" name="page" value="acdc-formation-saas">';
    echo '<input type="hidden" name="tab" value="marketing_logs">';
    echo '<p style="margin:0"><label>Recherche</label><input type="text" name="marketing_log_search" value="' . esc_attr( $search ) . '" placeholder="Message, contexte, identifiant"></p>';
    echo '<p style="margin:0"><label>Type</label><select name="marketing_log_type"><option value="">Tous</option><option value="success" ' . selected( $type_filter, 'success', false ) . '>Succès</option><option value="info" ' . selected( $type_filter, 'info', false ) . '>Information</option><option value="warning" ' . selected( $type_filter, 'warning', false ) . '>Avertissement</option><option value="error" ' . selected( $type_filter, 'error', false ) . '>Erreur</option></select></p>';
    echo '<p style="margin:0"><button type="submit" class="acdc-button acdc-button-secondary">Filtrer</button></p>';
    echo '</form>';
    echo '</div>';

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">Objets les plus présents</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Objet</th><th>Volume</th></tr></thead><tbody>';
    $printed_entities = 0;
    foreach ( array_slice( $entity_counts, 0, 8, true ) as $entity => $count ) {
      echo '<tr><td>' . esc_html( ucfirst( str_replace( '_', ' ', (string) $entity ) ) ) . '</td><td>' . esc_html( (string) $count ) . '</td></tr>';
      $printed_entities++;
    }
    if ( 0 === $printed_entities ) {
      echo '<tr><td colspan="2">Aucun objet métier détecté dans les contextes enregistrés.</td></tr>';
    }
    echo '</tbody></table>';
    echo '</div>';

    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:18px">';
    echo '<h2 style="margin-top:0">Historique détaillé</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Date</th><th>Type</th><th>Message</th><th>Contexte</th></tr></thead><tbody>';
    $printed = 0;
    foreach ( $filtered_logs as $log ) {
      $context = isset( $log['context'] ) && is_array( $log['context'] ) ? $log['context'] : array();
      $context_parts = array();
      foreach ( $context as $key => $value ) {
        if ( is_array( $value ) ) {
          $value = implode( ', ', array_map( 'sanitize_text_field', $value ) );
        }
        $context_parts[] = sanitize_text_field( (string) $key ) . ' : ' . sanitize_text_field( (string) $value );
      }
      echo '<tr>';
      echo '<td>' . esc_html( isset( $log['date'] ) ? $log['date'] : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $log['type'] ) ? ucfirst( (string) $log['type'] ) : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $log['message'] ) ? $log['message'] : '' ) . '</td>';
      echo '<td><small>' . esc_html( ! empty( $context_parts ) ? implode( ' | ', $context_parts ) : '—' ) . '</small></td>';
      echo '</tr>';
      $printed++;
    }
    if ( 0 === $printed ) {
      echo '<tr><td colspan="4">Aucun journal ne correspond aux filtres actuels.</td></tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
  }


  private function render_front_marketing_notifications_tab() {
    $notifications = $this->get_marketing_store( 'notifications', array() );
    $search = isset( $_GET['marketing_notification_search'] ) ? sanitize_text_field( wp_unslash( $_GET['marketing_notification_search'] ) ) : '';
    $level_filter = isset( $_GET['marketing_notification_level'] ) ? sanitize_key( wp_unslash( $_GET['marketing_notification_level'] ) ) : '';

    $counts_by_level = array(
      'info'    => 0,
      'success' => 0,
      'warning' => 0,
      'error'   => 0,
    );
    $recent_count = 0;
    $last_notification_date = '';
    $last_notification_title = '';

    foreach ( $notifications as $item ) {
      $level = isset( $item['level'] ) ? sanitize_key( $item['level'] ) : 'info';
      if ( isset( $counts_by_level[ $level ] ) ) {
        $counts_by_level[ $level ]++;
      }
      if ( empty( $last_notification_date ) && ! empty( $item['created_at'] ) ) {
        $last_notification_date = (string) $item['created_at'];
        $last_notification_title = isset( $item['title'] ) ? (string) $item['title'] : '';
      }
      if ( ! empty( $item['created_at'] ) ) {
        $timestamp = strtotime( (string) $item['created_at'] );
        if ( false !== $timestamp && $timestamp >= strtotime( '-7 days' ) ) {
          $recent_count++;
        }
      }
    }

    $filtered_notifications = array();
    foreach ( $notifications as $item ) {
      $haystack = strtolower( trim( (string) ( isset( $item['title'] ) ? $item['title'] : '' ) . ' ' . (string) ( isset( $item['message'] ) ? $item['message'] : '' ) ) );
      if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) {
        continue;
      }
      if ( '' !== $level_filter && ( ! isset( $item['level'] ) || sanitize_key( $item['level'] ) !== $level_filter ) ) {
        continue;
      }
      $filtered_notifications[] = $item;
    }

    $this->render_marketing_header( 'Notifications internes', 'Vue de supervision interne pour repérer rapidement les informations, confirmations, alertes et erreurs remontées par le module marketing.' );

    $this->render_marketing_cards( array(
      array( 'label' => 'Notifications enregistrées', 'value' => count( $notifications ), 'help' => 'Historique interne conservé dans le module.' ),
      array( 'label' => 'Derniers 7 jours', 'value' => $recent_count, 'help' => 'Notifications datées récentes.' ),
      array( 'label' => 'Alertes', 'value' => (int) $counts_by_level['warning'] + (int) $counts_by_level['error'], 'help' => 'Somme des avertissements et erreurs.' ),
      array( 'label' => 'Dernier niveau critique', 'value' => $counts_by_level['error'] > 0 ? 'Erreur' : ( $counts_by_level['warning'] > 0 ? 'Alerte' : 'Aucun' ), 'help' => 'Lecture rapide du niveau d’attention actuel.' ),
    ) );

    echo '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-bottom:18px">';

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">Répartition par niveau</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Niveau</th><th>Volume</th><th>Lecture métier</th></tr></thead><tbody>';
    echo '<tr><td>Information</td><td>' . esc_html( (string) $counts_by_level['info'] ) . '</td><td>Traçabilité normale du module.</td></tr>';
    echo '<tr><td>Succès</td><td>' . esc_html( (string) $counts_by_level['success'] ) . '</td><td>Actions confirmées comme traitées.</td></tr>';
    echo '<tr><td>Avertissement</td><td>' . esc_html( (string) $counts_by_level['warning'] ) . '</td><td>Point à surveiller sans blocage total.</td></tr>';
    echo '<tr><td>Erreur</td><td>' . esc_html( (string) $counts_by_level['error'] ) . '</td><td>Événement nécessitant une vérification prioritaire.</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">Lecture rapide</h2>';
    echo '<table class="acdc-table"><tbody>';
    echo '<tr><th>Dernière notification</th><td>' . esc_html( $last_notification_date ? $last_notification_date : 'Aucune notification enregistrée' ) . '</td></tr>';
    echo '<tr><th>Dernier titre</th><td>' . esc_html( $last_notification_title ? $last_notification_title : '—' ) . '</td></tr>';
    echo '<tr><th>Capacité de conservation</th><td>100 notifications maximum conservées automatiquement.</td></tr>';
    echo '<tr><th>Utilité de l’écran</th><td>Contrôle des alertes internes, suivi des événements métier et repérage des anomalies du module.</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    echo '</div>';

    echo '<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:18px;margin-bottom:18px">';

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">Filtres</h2>';
    echo '<form method="get" class="acdc-filters" style="display:grid;grid-template-columns:2fr 1fr auto;gap:12px;align-items:end">';
    echo '<input type="hidden" name="page" value="acdc-formation-saas">';
    echo '<input type="hidden" name="tab" value="marketing_notifications">';
    echo '<p style="margin:0"><label>Recherche</label><input type="text" name="marketing_notification_search" value="' . esc_attr( $search ) . '" placeholder="Titre ou message"></p>';
    echo '<p style="margin:0"><label>Niveau</label><select name="marketing_notification_level"><option value="">Tous</option><option value="info" ' . selected( $level_filter, 'info', false ) . '>Information</option><option value="success" ' . selected( $level_filter, 'success', false ) . '>Succès</option><option value="warning" ' . selected( $level_filter, 'warning', false ) . '>Avertissement</option><option value="error" ' . selected( $level_filter, 'error', false ) . '>Erreur</option></select></p>';
    echo '<p style="margin:0"><button type="submit" class="acdc-button acdc-button-secondary">Filtrer</button></p>';
    echo '</form>';
    echo '</div>';

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">Règles de lecture</h2>';
    echo '<table class="acdc-table"><tbody>';
    echo '<tr><th>Information</th><td>Événement courant sans action urgente.</td></tr>';
    echo '<tr><th>Succès</th><td>Action confirmée par le module.</td></tr>';
    echo '<tr><th>Avertissement</th><td>Vérification conseillée avant poursuite.</td></tr>';
    echo '<tr><th>Erreur</th><td>Point bloquant ou à corriger rapidement.</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:18px">';
    echo '<h2 style="margin-top:0">Historique détaillé</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Date</th><th>Titre</th><th>Niveau</th><th>Message</th></tr></thead><tbody>';
    $printed = 0;
    foreach ( $filtered_notifications as $item ) {
      $level = isset( $item['level'] ) ? sanitize_key( $item['level'] ) : 'info';
      $level_label = 'Information';
      if ( 'success' === $level ) {
        $level_label = 'Succès';
      } elseif ( 'warning' === $level ) {
        $level_label = 'Avertissement';
      } elseif ( 'error' === $level ) {
        $level_label = 'Erreur';
      }
      echo '<tr>';
      echo '<td>' . esc_html( isset( $item['created_at'] ) ? $item['created_at'] : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['title'] ) ? $item['title'] : '' ) . '</td>';
      echo '<td>' . esc_html( $level_label ) . '</td>';
      echo '<td>' . esc_html( isset( $item['message'] ) ? $item['message'] : '' ) . '</td>';
      echo '</tr>';
      $printed++;
    }
    if ( 0 === $printed ) {
      echo '<tr><td colspan="4">Aucune notification ne correspond aux filtres actuels.</td></tr>';
    }
    echo '</tbody></table></div>';
  }


  private function render_front_marketing_unsubscribes_tab() {
    $items = $this->get_marketing_store( 'unsubscribes', array() );
    $search = isset( $_GET['marketing_unsubscribe_search'] ) ? sanitize_text_field( wp_unslash( $_GET['marketing_unsubscribe_search'] ) ) : '';
    $category_filter = isset( $_GET['marketing_unsubscribe_category'] ) ? sanitize_key( wp_unslash( $_GET['marketing_unsubscribe_category'] ) ) : '';
    $reason_filter = isset( $_GET['marketing_unsubscribe_reason'] ) ? sanitize_text_field( wp_unslash( $_GET['marketing_unsubscribe_reason'] ) ) : '';

    $category_counts = array();
    $reason_counts = array();
    $blacklist_count = 0;
    $with_ip_count = 0;
    $re_subscribe_count = 0;
    $recent_count = 0;
    $last_date = '';
    $last_email = '';

    foreach ( $items as $item ) {
      $category = isset( $item['category'] ) && '' !== (string) $item['category'] ? (string) $item['category'] : 'non_precise';
      if ( ! isset( $category_counts[ $category ] ) ) {
        $category_counts[ $category ] = 0;
      }
      $category_counts[ $category ]++;

      $reason = isset( $item['reason'] ) && '' !== (string) $item['reason'] ? (string) $item['reason'] : 'Non précisé';
      if ( ! isset( $reason_counts[ $reason ] ) ) {
        $reason_counts[ $reason ] = 0;
      }
      $reason_counts[ $reason ]++;

      if ( ! empty( $item['blacklisted'] ) || ! empty( $item['is_blacklisted'] ) || ( isset( $item['status'] ) && 'blacklisted' === (string) $item['status'] ) ) {
        $blacklist_count++;
      }
      if ( ! empty( $item['ip'] ) ) {
        $with_ip_count++;
      }
      if ( ! empty( $item['resubscribed_at'] ) || ! empty( $item['re_subscribed_at'] ) ) {
        $re_subscribe_count++;
      }
      if ( empty( $last_date ) && ! empty( $item['created_at'] ) ) {
        $last_date = (string) $item['created_at'];
        $last_email = isset( $item['email'] ) ? (string) $item['email'] : '';
      }
      if ( ! empty( $item['created_at'] ) ) {
        $timestamp = strtotime( (string) $item['created_at'] );
        if ( false !== $timestamp && $timestamp >= strtotime( '-30 days' ) ) {
          $recent_count++;
        }
      }
    }

    arsort( $category_counts );
    arsort( $reason_counts );

    $filtered_items = array();
    foreach ( $items as $item ) {
      $email = isset( $item['email'] ) ? (string) $item['email'] : '';
      $category = isset( $item['category'] ) ? sanitize_key( (string) $item['category'] ) : '';
      $reason = isset( $item['reason'] ) ? (string) $item['reason'] : '';
      $comment = isset( $item['comment'] ) ? (string) $item['comment'] : '';
      $haystack = strtolower( trim( $email . ' ' . $category . ' ' . $reason . ' ' . $comment ) );
      if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) {
        continue;
      }
      if ( '' !== $category_filter && $category !== $category_filter ) {
        continue;
      }
      if ( '' !== $reason_filter && $reason !== $reason_filter ) {
        continue;
      }
      $filtered_items[] = $item;
    }

    $this->render_marketing_header( 'Désinscriptions / liste noire', 'Conservez ici les traces utiles à la conformité, aux preuves de consentement, aux exclusions globales et aux réactivations autorisées.' );

    $this->render_marketing_cards( array(
      array( 'label' => 'Désinscriptions enregistrées', 'value' => count( $items ), 'help' => 'Historique global conservé dans le module marketing.' ),
      array( 'label' => 'Derniers 30 jours', 'value' => $recent_count, 'help' => 'Événements récents utiles au pilotage conformité.' ),
      array( 'label' => 'Liste noire', 'value' => $blacklist_count, 'help' => 'Adresses exclues globalement des envois concernés.' ),
      array( 'label' => 'Réinscriptions', 'value' => $re_subscribe_count, 'help' => 'Cas réautorisés après désinscription initiale.' ),
    ) );

    echo '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-bottom:18px">';

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">Répartition par catégorie</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Catégorie</th><th>Volume</th><th>Lecture métier</th></tr></thead><tbody>';
    $printed_categories = 0;
    foreach ( array_slice( $category_counts, 0, 8, true ) as $category => $count ) {
      echo '<tr><td>' . esc_html( $category ) . '</td><td>' . esc_html( (string) $count ) . '</td><td>Désinscriptions rattachées à cette famille d’e-mails.</td></tr>';
      $printed_categories++;
    }
    if ( 0 === $printed_categories ) {
      echo '<tr><td colspan="3">Aucune catégorie enregistrée pour le moment.</td></tr>';
    }
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">Lecture rapide</h2>';
    echo '<table class="acdc-table"><tbody>';
    echo '<tr><th>Dernière désinscription</th><td>' . esc_html( $last_date ? $last_date : 'Aucune désinscription enregistrée' ) . '</td></tr>';
    echo '<tr><th>Dernier e-mail concerné</th><td>' . esc_html( $last_email ? $last_email : '—' ) . '</td></tr>';
    echo '<tr><th>Adresses avec IP conservée</th><td>' . esc_html( (string) $with_ip_count ) . '</td></tr>';
    echo '<tr><th>Règle du module</th><td>Une désinscription marketing ne bloque pas automatiquement les e-mails de suivi, administratifs ou financeurs.</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    echo '</div>';

    echo '<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:18px;margin-bottom:18px">';

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">Filtres</h2>';
    echo '<form method="get" class="acdc-filters" style="display:grid;grid-template-columns:1.5fr 1fr 1fr auto;gap:12px;align-items:end">';
    echo '<input type="hidden" name="page" value="acdc-formation-saas">';
    echo '<input type="hidden" name="tab" value="marketing_unsubscribes">';
    echo '<p style="margin:0"><label>Recherche</label><input type="text" name="marketing_unsubscribe_search" value="' . esc_attr( $search ) . '" placeholder="E-mail, motif ou commentaire"></p>';
    echo '<p style="margin:0"><label>Catégorie</label><select name="marketing_unsubscribe_category"><option value="">Toutes</option>';
    foreach ( $category_counts as $category => $count ) {
      echo '<option value="' . esc_attr( sanitize_key( $category ) ) . '" ' . selected( $category_filter, sanitize_key( $category ), false ) . '>' . esc_html( $category ) . '</option>';
    }
    echo '</select></p>';
    echo '<p style="margin:0"><label>Motif</label><select name="marketing_unsubscribe_reason"><option value="">Tous</option>';
    foreach ( array_slice( $reason_counts, 0, 12, true ) as $reason => $count ) {
      echo '<option value="' . esc_attr( $reason ) . '" ' . selected( $reason_filter, $reason, false ) . '>' . esc_html( $reason ) . '</option>';
    }
    echo '</select></p>';
    echo '<p style="margin:0"><button type="submit" class="acdc-button acdc-button-secondary">Filtrer</button></p>';
    echo '</form>';
    echo '</div>';

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">Motifs les plus présents</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Motif</th><th>Volume</th></tr></thead><tbody>';
    $printed_reasons = 0;
    foreach ( array_slice( $reason_counts, 0, 8, true ) as $reason => $count ) {
      echo '<tr><td>' . esc_html( $reason ) . '</td><td>' . esc_html( (string) $count ) . '</td></tr>';
      $printed_reasons++;
    }
    if ( 0 === $printed_reasons ) {
      echo '<tr><td colspan="2">Aucun motif n’a encore été enregistré.</td></tr>';
    }
    echo '</tbody></table>';
    echo '</div>';

    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:18px">';
    echo '<h2 style="margin-top:0">Historique détaillé</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Date</th><th>E-mail</th><th>Catégorie</th><th>Motif</th><th>Liste noire</th><th>Réinscription</th><th>Contexte</th></tr></thead><tbody>';
    $printed = 0;
    foreach ( $filtered_items as $item ) {
      $context_parts = array();
      if ( ! empty( $item['ip'] ) ) {
        $context_parts[] = 'IP : ' . sanitize_text_field( (string) $item['ip'] );
      }
      if ( ! empty( $item['comment'] ) ) {
        $context_parts[] = 'Commentaire : ' . sanitize_text_field( (string) $item['comment'] );
      }
      if ( ! empty( $item['source'] ) ) {
        $context_parts[] = 'Source : ' . sanitize_text_field( (string) $item['source'] );
      }
      if ( ! empty( $item['page_url'] ) ) {
        $context_parts[] = 'Page : ' . sanitize_text_field( (string) $item['page_url'] );
      }
      if ( ! empty( $item['user_agent'] ) ) {
        $context_parts[] = 'Navigateur : ' . sanitize_text_field( (string) $item['user_agent'] );
      }
      $is_blacklisted = ! empty( $item['blacklisted'] ) || ! empty( $item['is_blacklisted'] ) || ( isset( $item['status'] ) && 'blacklisted' === (string) $item['status'] );
      $resub_date = '';
      if ( ! empty( $item['resubscribed_at'] ) ) {
        $resub_date = (string) $item['resubscribed_at'];
      } elseif ( ! empty( $item['re_subscribed_at'] ) ) {
        $resub_date = (string) $item['re_subscribed_at'];
      }
      echo '<tr>';
      echo '<td>' . esc_html( isset( $item['created_at'] ) ? $item['created_at'] : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['email'] ) ? $item['email'] : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['category'] ) ? $item['category'] : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['reason'] ) ? $item['reason'] : '' ) . '</td>';
      echo '<td>' . esc_html( $is_blacklisted ? 'Oui' : 'Non' ) . '</td>';
      echo '<td>' . esc_html( $resub_date ? $resub_date : '—' ) . '</td>';
      echo '<td><small>' . esc_html( ! empty( $context_parts ) ? implode( ' | ', $context_parts ) : '—' ) . '</small></td>';
      echo '</tr>';
      $printed++;
    }
    if ( 0 === $printed ) {
      echo '<tr><td colspan="7">Aucune désinscription ne correspond aux filtres actuels.</td></tr>';
    }
    echo '</tbody></table></div>';
  }


  private function render_front_marketing_queue_tab() {
    $items = $this->get_marketing_store( 'queue', array() );
    $settings = $this->get_marketing_store( 'settings', array() );
    $search = isset( $_GET['marketing_queue_search'] ) ? sanitize_text_field( wp_unslash( $_GET['marketing_queue_search'] ) ) : '';
    $status_filter = isset( $_GET['marketing_queue_status'] ) ? sanitize_key( wp_unslash( $_GET['marketing_queue_status'] ) ) : '';
    $kind_filter = isset( $_GET['marketing_queue_kind'] ) ? sanitize_key( wp_unslash( $_GET['marketing_queue_kind'] ) ) : '';

    $status_counts = array();
    $kind_counts = array();
    $planned_count = 0;
    $paused_count = 0;
    $ready_count = 0;
    $recent_count = 0;
    $last_date = '';
    $last_name = '';
    $last_type = '';

    foreach ( $items as $item ) {
      $status = isset( $item['status'] ) && '' !== (string) $item['status'] ? sanitize_key( (string) $item['status'] ) : 'pending';
      $kind = isset( $item['kind'] ) && '' !== (string) $item['kind'] ? sanitize_key( (string) $item['kind'] ) : 'campaign';

      if ( ! isset( $status_counts[ $status ] ) ) {
        $status_counts[ $status ] = 0;
      }
      $status_counts[ $status ]++;

      if ( ! isset( $kind_counts[ $kind ] ) ) {
        $kind_counts[ $kind ] = 0;
      }
      $kind_counts[ $kind ]++;

      if ( in_array( $status, array( 'planned', 'pending' ), true ) ) {
        $planned_count++;
      }
      if ( 'paused' === $status ) {
        $paused_count++;
      }
      if ( in_array( $status, array( 'ready', 'retry', 'queued' ), true ) ) {
        $ready_count++;
      }

      if ( empty( $last_date ) && ! empty( $item['created_at'] ) ) {
        $last_date = (string) $item['created_at'];
        $last_name = isset( $item['name'] ) ? (string) $item['name'] : '';
        $last_type = isset( $item['kind'] ) ? (string) $item['kind'] : '';
      }

      if ( ! empty( $item['created_at'] ) ) {
        $timestamp = strtotime( (string) $item['created_at'] );
        if ( false !== $timestamp && $timestamp >= strtotime( '-7 days' ) ) {
          $recent_count++;
        }
      }
    }

    arsort( $status_counts );
    arsort( $kind_counts );

    $filtered_items = array();
    foreach ( $items as $item ) {
      $name = isset( $item['name'] ) ? (string) $item['name'] : '';
      $kind = isset( $item['kind'] ) ? sanitize_key( (string) $item['kind'] ) : '';
      $status = isset( $item['status'] ) ? sanitize_key( (string) $item['status'] ) : '';
      $context = isset( $item['context'] ) ? (string) $item['context'] : '';
      $planned_at = isset( $item['planned_at'] ) ? (string) $item['planned_at'] : '';
      $haystack = strtolower( trim( $name . ' ' . $kind . ' ' . $status . ' ' . $context . ' ' . $planned_at ) );
      if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) {
        continue;
      }
      if ( '' !== $status_filter && $status !== $status_filter ) {
        continue;
      }
      if ( '' !== $kind_filter && $kind !== $kind_filter ) {
        continue;
      }
      $filtered_items[] = $item;
    }

    $queue_threshold = isset( $settings['queue_threshold'] ) ? absint( $settings['queue_threshold'] ) : 20;
    $limit_per_minute = isset( $settings['limit_per_minute'] ) ? absint( $settings['limit_per_minute'] ) : 30;
    $limit_per_hour = isset( $settings['limit_per_hour'] ) ? absint( $settings['limit_per_hour'] ) : 300;
    $allowed_start = isset( $settings['allowed_hours_start'] ) ? (string) $settings['allowed_hours_start'] : '08:00';
    $allowed_end = isset( $settings['allowed_hours_end'] ) ? (string) $settings['allowed_hours_end'] : '19:00';

    $this->render_marketing_header( 'File d’attente d’envoi', 'Pilotez ici les campagnes et envois planifiés, mis en pause, relancés ou prêts à repartir selon les règles du module.' );

    $this->render_marketing_cards( array(
      array( 'label' => 'Éléments en file', 'value' => count( $items ), 'help' => 'Campagnes ou tâches d’envoi stockées avant exécution.' ),
      array( 'label' => 'Derniers 7 jours', 'value' => $recent_count, 'help' => 'Entrées récentes visibles pour le pilotage quotidien.' ),
      array( 'label' => 'En pause', 'value' => $paused_count, 'help' => 'Éléments nécessitant une reprise manuelle.' ),
      array( 'label' => 'Prêts / à relancer', 'value' => $ready_count, 'help' => 'Éléments pouvant repartir sans reconfiguration lourde.' ),
    ) );

    echo '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-bottom:18px">';

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">Répartition par statut</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Statut</th><th>Volume</th><th>Lecture métier</th></tr></thead><tbody>';
    $printed_statuses = 0;
    foreach ( array_slice( $status_counts, 0, 8, true ) as $status => $count ) {
      $label = ucfirst( str_replace( '_', ' ', (string) $status ) );
      $help = 'Élément suivi dans la file d’envoi.';
      if ( 'planned' === $status ) {
        $help = 'Envoi programmé à une date et heure définies.';
      } elseif ( 'pending' === $status ) {
        $help = 'Élément en attente de départ ou de traitement.';
      } elseif ( 'paused' === $status ) {
        $help = 'Mise en pause volontaire ou technique.';
      } elseif ( in_array( $status, array( 'retry', 'ready' ), true ) ) {
        $help = 'Élément prêt à être repris.';
      }
      echo '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( (string) $count ) . '</td><td>' . esc_html( $help ) . '</td></tr>';
      $printed_statuses++;
    }
    if ( 0 === $printed_statuses ) {
      echo '<tr><td colspan="3">Aucun élément n’est actuellement présent dans la file.</td></tr>';
    }
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">Lecture rapide</h2>';
    echo '<table class="acdc-table"><tbody>';
    echo '<tr><th>Dernière entrée</th><td>' . esc_html( $last_date ? $last_date : 'Aucune entrée enregistrée' ) . '</td></tr>';
    echo '<tr><th>Dernier nom concerné</th><td>' . esc_html( $last_name ? $last_name : '—' ) . '</td></tr>';
    echo '<tr><th>Dernier type</th><td>' . esc_html( $last_type ? $last_type : '—' ) . '</td></tr>';
    echo '<tr><th>Règle module</th><td>Au-delà du seuil immédiat, les envois basculent automatiquement dans la file d’attente.</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    echo '</div>';

    echo '<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:18px;margin-bottom:18px">';

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">Filtres</h2>';
    echo '<form method="get" class="acdc-filters" style="display:grid;grid-template-columns:1.5fr 1fr 1fr auto;gap:12px;align-items:end">';
    echo '<input type="hidden" name="page" value="acdc-formation-saas">';
    echo '<input type="hidden" name="tab" value="marketing_queue">';
    echo '<p style="margin:0"><label>Recherche</label><input type="text" name="marketing_queue_search" value="' . esc_attr( $search ) . '" placeholder="Nom, type, statut ou contexte"></p>';
    echo '<p style="margin:0"><label>Statut</label><select name="marketing_queue_status"><option value="">Tous</option>';
    foreach ( $status_counts as $status => $count ) {
      $label = ucfirst( str_replace( '_', ' ', (string) $status ) );
      echo '<option value="' . esc_attr( sanitize_key( $status ) ) . '" ' . selected( $status_filter, sanitize_key( $status ), false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select></p>';
    echo '<p style="margin:0"><label>Type</label><select name="marketing_queue_kind"><option value="">Tous</option>';
    foreach ( $kind_counts as $kind => $count ) {
      $label = ucfirst( str_replace( '_', ' ', (string) $kind ) );
      echo '<option value="' . esc_attr( sanitize_key( $kind ) ) . '" ' . selected( $kind_filter, sanitize_key( $kind ), false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select></p>';
    echo '<p style="margin:0"><button type="submit" class="acdc-button acdc-button-secondary">Filtrer</button></p>';
    echo '</form>';
    echo '</div>';

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">Cadre d’envoi</h2>';
    echo '<table class="acdc-table"><tbody>';
    echo '<tr><th>Seuil immédiat</th><td>' . esc_html( (string) $queue_threshold ) . ' e-mails</td></tr>';
    echo '<tr><th>Limite / minute</th><td>' . esc_html( (string) $limit_per_minute ) . '</td></tr>';
    echo '<tr><th>Limite / heure</th><td>' . esc_html( (string) $limit_per_hour ) . '</td></tr>';
    echo '<tr><th>Plage autorisée</th><td>' . esc_html( $allowed_start . ' → ' . $allowed_end ) . '</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:18px">';
    echo '<h2 style="margin-top:0">Historique détaillé</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Date</th><th>Nom</th><th>Type</th><th>Statut</th><th>Planifié</th><th>Contexte</th></tr></thead><tbody>';
    $printed = 0;
    foreach ( $filtered_items as $item ) {
      $context_parts = array();
      if ( ! empty( $item['context'] ) ) {
        $context_parts[] = sanitize_text_field( (string) $item['context'] );
      }
      if ( ! empty( $item['attempts'] ) ) {
        $context_parts[] = 'Tentatives : ' . absint( $item['attempts'] );
      }
      if ( ! empty( $item['target_count'] ) ) {
        $context_parts[] = 'Cibles : ' . absint( $item['target_count'] );
      }
      if ( ! empty( $item['last_error'] ) ) {
        $context_parts[] = 'Dernière erreur technique consignée côté journal.';
      }
      echo '<tr>';
      echo '<td>' . esc_html( isset( $item['created_at'] ) ? $item['created_at'] : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['name'] ) ? $item['name'] : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['kind'] ) ? ucfirst( str_replace( '_', ' ', (string) $item['kind'] ) ) : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['status'] ) ? ucfirst( str_replace( '_', ' ', (string) $item['status'] ) ) : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['planned_at'] ) && '' !== (string) $item['planned_at'] ? (string) $item['planned_at'] : '—' ) . '</td>';
      echo '<td><small>' . esc_html( ! empty( $context_parts ) ? implode( ' | ', $context_parts ) : '—' ) . '</small></td>';
      echo '</tr>';
      $printed++;
    }
    if ( 0 === $printed ) {
      echo '<tr><td colspan="6">Aucun élément de file d’attente ne correspond aux filtres actuels.</td></tr>';
    }
    echo '</tbody></table></div>';
  }



  private function render_front_marketing_fields_tab() {
    $entity = 'fields';
    $tab = 'marketing_fields';
    $items = $this->get_marketing_store( $entity, array() );
    $contacts = $this->get_marketing_contacts_index();
    $forms = $this->get_marketing_store( 'forms', array() );
    $templates = $this->get_marketing_store( 'templates', array() );
    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
    $item_id = isset( $_GET['item_id'] ) ? sanitize_text_field( wp_unslash( $_GET['item_id'] ) ) : '';
    $search = isset( $_GET['marketing_search'] ) ? sanitize_text_field( wp_unslash( $_GET['marketing_search'] ) ) : '';
    $family_filter = isset( $_GET['marketing_field_family'] ) ? sanitize_key( wp_unslash( $_GET['marketing_field_family'] ) ) : '';
    $type_filter = isset( $_GET['marketing_field_type'] ) ? sanitize_key( wp_unslash( $_GET['marketing_field_type'] ) ) : '';

    $family_labels = array(
      'identity' => 'Identité',
      'contact' => 'Coordonnées',
      'company' => 'Entreprise / organisme',
      'commercial' => 'Commercial',
      'formation' => 'Formation',
      'consent' => 'Consentement / conformité',
      'marketing' => 'Suivi marketing',
      'custom' => 'Métier personnalisé',
    );
    $type_labels = array(
      'text' => 'Texte',
      'email' => 'E-mail',
      'phone' => 'Téléphone',
      'select' => 'Liste',
      'textarea' => 'Texte long',
    );

    $total_fields = count( $items );
    $active_fields = 0;
    $required_fields = 0;
    $system_fields = 0;
    $select_fields = 0;
    $used_in_forms = 0;
    $used_in_templates = 0;
    $family_counts = array();
    $type_counts = array();

    foreach ( $items as $item ) {
      $status = isset( $item['status'] ) ? sanitize_key( (string) $item['status'] ) : 'draft';
      $field_type = isset( $item['field_type'] ) ? sanitize_key( (string) $item['field_type'] ) : 'text';
      $family = isset( $item['family'] ) ? sanitize_key( (string) $item['family'] ) : 'custom';
      $family_counts[ $family ] = isset( $family_counts[ $family ] ) ? $family_counts[ $family ] + 1 : 1;
      $type_counts[ $field_type ] = isset( $type_counts[ $field_type ] ) ? $type_counts[ $field_type ] + 1 : 1;
      if ( 'active' === $status ) {
        $active_fields++;
      }
      if ( ! empty( $item['is_required'] ) ) {
        $required_fields++;
      }
      if ( ! empty( $item['is_system'] ) ) {
        $system_fields++;
      }
      if ( 'select' === $field_type ) {
        $select_fields++;
      }
      if ( ! empty( $item['usage_forms'] ) ) {
        $used_in_forms++;
      }
      if ( ! empty( $item['usage_templates'] ) ) {
        $used_in_templates++;
      }
    }

    $filtered = array();
    foreach ( $items as $item ) {
      $name = isset( $item['name'] ) ? (string) $item['name'] : '';
      $description = isset( $item['description'] ) ? (string) $item['description'] : '';
      $code = isset( $item['code'] ) ? (string) $item['code'] : '';
      $family = isset( $item['family'] ) ? sanitize_key( (string) $item['family'] ) : 'custom';
      $field_type = isset( $item['field_type'] ) ? sanitize_key( (string) $item['field_type'] ) : 'text';
      if ( $search ) {
        $haystack = strtolower( remove_accents( $name . ' ' . $description . ' ' . $code ) );
        $needle = strtolower( remove_accents( $search ) );
        if ( false === strpos( $haystack, $needle ) ) {
          continue;
        }
      }
      if ( $family_filter && $family !== $family_filter ) {
        continue;
      }
      if ( $type_filter && $field_type !== $type_filter ) {
        continue;
      }
      $filtered[] = $item;
    }

    usort( $filtered, function( $a, $b ) {
      $a_order = isset( $a['sort_order'] ) ? (int) $a['sort_order'] : 100;
      $b_order = isset( $b['sort_order'] ) ? (int) $b['sort_order'] : 100;
      if ( $a_order === $b_order ) {
        return strcmp( isset( $a['name'] ) ? (string) $a['name'] : '', isset( $b['name'] ) ? (string) $b['name'] : '' );
      }
      return $a_order <=> $b_order;
    } );

    $this->render_marketing_header( 'Champs personnalisés', 'Définissez les champs complémentaires réutilisables dans les contacts marketing, les formulaires publics et les modèles d’e-mails, sans casser la structure existante du plugin.', array(
      array( 'label' => 'Créer un champ', 'url' => $this->portal_page_url( array( 'tab' => $tab, 'action' => 'new' ) ), 'primary' => true ),
      array( 'label' => 'Voir les formulaires', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_forms' ) ) ),
    ) );
    $this->render_marketing_cards( array(
      array( 'label' => 'Champs enregistrés', 'value' => $total_fields, 'help' => 'Bibliothèque globale du module marketing.' ),
      array( 'label' => 'Champs actifs', 'value' => $active_fields, 'help' => 'Champs disponibles à l’usage métier.' ),
      array( 'label' => 'Champs obligatoires', 'value' => $required_fields, 'help' => 'À utiliser avec parcimonie, uniquement si nécessaire.' ),
      array( 'label' => 'Champs système', 'value' => $system_fields, 'help' => 'Éléments à préserver pour la cohérence du module.' ),
    ) );

    echo '<div style="display:grid;grid-template-columns:1.1fr 0.9fr;gap:18px;align-items:start">';
    echo '<div style="display:grid;gap:18px">';

    if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
      $item = array(
        'status' => 'active',
        'field_type' => 'text',
        'family' => 'custom',
        'usage' => 'contact',
        'code' => '',
        'description' => '',
        'options' => '',
        'sort_order' => 100,
      );
      foreach ( $items as $candidate ) {
        if ( isset( $candidate['id'] ) && $candidate['id'] === $item_id ) {
          $item = array_merge( $item, $candidate );
          break;
        }
      }
      echo '<div class="acdc-panel" style="margin-bottom:0"><form class="acdc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
      wp_nonce_field( 'acdc_marketing_save_entity' );
      echo '<input type="hidden" name="action" value="acdc_marketing_save_entity">';
      echo '<input type="hidden" name="marketing_entity" value="fields">';
      echo '<input type="hidden" name="marketing_item[id]" value="' . esc_attr( isset( $item['id'] ) ? $item['id'] : '' ) . '">';
      echo '<div class="acdc-marketing-segments-form-panel" style="box-shadow:none;border:none;padding:0;margin:0">';
      echo '<h2 style="margin:0 0 6px">' . esc_html( 'edit' === $action ? 'Modifier le champ' : 'Créer un champ personnalisé' ) . '</h2>';
      echo '<p style="margin:0 0 14px;color:#1E4777">Créez un champ réutilisable dans les fiches marketing, les formulaires publics ou les modèles. La structure reste alignée sur les écrans validés du module.</p>';
      echo '<div class="acdc-marketing-segments-form-grid">';
      echo '<p><label>Nom du champ</label><input type="text" name="marketing_item[name]" value="' . esc_attr( isset( $item['name'] ) ? $item['name'] : '' ) . '" required></p>';
      echo '<p><label>Code interne</label><input type="text" name="marketing_item[code]" value="' . esc_attr( isset( $item['code'] ) ? $item['code'] : '' ) . '" placeholder="exemple : source_rencontre"></p>';
      echo '<p><label>Statut</label><select name="marketing_item[status]">';
      foreach ( array( 'draft' => 'Brouillon', 'active' => 'Actif', 'archived' => 'Archivé' ) as $status_key => $status_label ) {
        echo '<option value="' . esc_attr( $status_key ) . '" ' . selected( isset( $item['status'] ) ? $item['status'] : 'active', $status_key, false ) . '>' . esc_html( $status_label ) . '</option>';
      }
      echo '</select></p>';
      echo '<p><label>Famille</label><select name="marketing_item[family]">';
      foreach ( $family_labels as $family_key => $family_label ) {
        echo '<option value="' . esc_attr( $family_key ) . '" ' . selected( isset( $item['family'] ) ? $item['family'] : 'custom', $family_key, false ) . '>' . esc_html( $family_label ) . '</option>';
      }
      echo '</select></p>';
      echo '<p><label>Type de champ</label><select name="marketing_item[field_type]">';
      foreach ( $type_labels as $type_key => $type_label ) {
        echo '<option value="' . esc_attr( $type_key ) . '" ' . selected( isset( $item['field_type'] ) ? $item['field_type'] : 'text', $type_key, false ) . '>' . esc_html( $type_label ) . '</option>';
      }
      echo '</select></p>';
      echo '<p><label>Usage principal</label><select name="marketing_item[usage]">';
      foreach ( array( 'contact' => 'Fiche contact', 'form' => 'Formulaire public', 'template' => 'Modèle d’e-mail', 'mixed' => 'Usage mixte' ) as $usage_key => $usage_label ) {
        echo '<option value="' . esc_attr( $usage_key ) . '" ' . selected( isset( $item['usage'] ) ? $item['usage'] : 'contact', $usage_key, false ) . '>' . esc_html( $usage_label ) . '</option>';
      }
      echo '</select></p>';
      echo '<p><label>Ordre d’affichage</label><input type="number" name="marketing_item[sort_order]" value="' . esc_attr( isset( $item['sort_order'] ) ? (int) $item['sort_order'] : 100 ) . '"></p>';
      echo '</div>';
      echo '<p><label>Description métier</label><textarea name="marketing_item[description]" rows="4">' . esc_textarea( isset( $item['description'] ) ? $item['description'] : '' ) . '</textarea></p>';
      echo '<p><label>Options</label><textarea name="marketing_item[options]" rows="4" placeholder="Une valeur par ligne ou séparées par des virgules. Utile pour les listes déroulantes.">' . esc_textarea( isset( $item['options'] ) ? $item['options'] : '' ) . '</textarea></p>';
      echo '<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:12px 0 16px">';
      echo '<p class="acdc-checkbox-line"><label><input type="checkbox" name="marketing_item[is_required]" value="1" ' . checked( ! empty( $item['is_required'] ), true, false ) . '> Champ obligatoire</label></p>';
      echo '<p class="acdc-checkbox-line"><label><input type="checkbox" name="marketing_item[is_system]" value="1" ' . checked( ! empty( $item['is_system'] ), true, false ) . '> Champ système</label></p>';
      echo '<p class="acdc-checkbox-line"><label><input type="checkbox" name="marketing_item[usage_forms]" value="1" ' . checked( ! empty( $item['usage_forms'] ), true, false ) . '> Utilisable dans les formulaires</label></p>';
      echo '<p class="acdc-checkbox-line"><label><input type="checkbox" name="marketing_item[usage_templates]" value="1" ' . checked( ! empty( $item['usage_templates'] ), true, false ) . '> Utilisable dans les modèles</label></p>';
      echo '</div>';
      echo '<p><button type="submit" class="acdc-button acdc-button-primary">Enregistrer le champ</button></p>';
      echo '</div>';
      echo '</form></div>';
    }

    echo '<div class="acdc-panel" style="margin-bottom:0">';
    echo '<h2 style="margin-top:0">Filtres</h2>';
    echo '<form method="get" class="acdc-form" style="display:grid;grid-template-columns:1.2fr 1fr 1fr auto;gap:12px;align-items:end">';
    echo '<input type="hidden" name="page_id" value="' . esc_attr( get_queried_object_id() ) . '">';
    echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
    echo '<p style="margin:0"><label>Recherche</label><input type="text" name="marketing_search" value="' . esc_attr( $search ) . '" placeholder="Nom, code, description"></p>';
    echo '<p style="margin:0"><label>Famille</label><select name="marketing_field_family"><option value="">Toutes</option>';
    foreach ( $family_labels as $family_key => $family_label ) {
      echo '<option value="' . esc_attr( $family_key ) . '" ' . selected( $family_filter, $family_key, false ) . '>' . esc_html( $family_label ) . '</option>';
    }
    echo '</select></p>';
    echo '<p style="margin:0"><label>Type</label><select name="marketing_field_type"><option value="">Tous</option>';
    foreach ( $type_labels as $type_key => $type_label ) {
      echo '<option value="' . esc_attr( $type_key ) . '" ' . selected( $type_filter, $type_key, false ) . '>' . esc_html( $type_label ) . '</option>';
    }
    echo '</select></p>';
    echo '<p style="margin:0"><button type="submit" class="acdc-button acdc-button-secondary">Filtrer</button></p>';
    echo '</form>';
    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:0">';
    echo '<h2 style="margin-top:0">Bibliothèque des champs</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Champ</th><th>Famille</th><th>Type</th><th>Usage</th><th>Statut</th><th>Ordre</th><th>Actions</th></tr></thead><tbody>';
    $printed = 0;
    foreach ( $filtered as $item ) {
      $edit_url = $this->portal_page_url( array( 'tab' => $tab, 'action' => 'edit', 'item_id' => isset( $item['id'] ) ? $item['id'] : '' ) );
      $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'acdc_marketing_delete_entity', 'marketing_entity' => $entity, 'item_id' => isset( $item['id'] ) ? $item['id'] : '' ), admin_url( 'admin-post.php' ) ), 'acdc_marketing_delete_entity' );
      $usage_flags = array();
      if ( ! empty( $item['usage_forms'] ) ) { $usage_flags[] = 'Formulaire'; }
      if ( ! empty( $item['usage_templates'] ) ) { $usage_flags[] = 'Modèle'; }
      if ( empty( $usage_flags ) ) { $usage_flags[] = isset( $item['usage'] ) ? ucfirst( (string) $item['usage'] ) : 'Contact'; }
      echo '<tr>';
      echo '<td><strong>' . esc_html( isset( $item['name'] ) ? $item['name'] : '' ) . '</strong><br><small>' . esc_html( isset( $item['code'] ) ? $item['code'] : '' ) . '</small>';
      if ( ! empty( $item['description'] ) ) {
        echo '<br><small>' . esc_html( $item['description'] ) . '</small>';
      }
      if ( ! empty( $item['is_required'] ) ) {
        echo '<br><small>Obligatoire</small>';
      }
      if ( ! empty( $item['is_system'] ) ) {
        echo '<br><small>Champ système</small>';
      }
      echo '</td>';
      echo '<td>' . esc_html( isset( $family_labels[ isset( $item['family'] ) ? $item['family'] : 'custom' ] ) ? $family_labels[ $item['family'] ] : 'Métier personnalisé' ) . '</td>';
      echo '<td>' . esc_html( isset( $type_labels[ isset( $item['field_type'] ) ? $item['field_type'] : 'text' ] ) ? $type_labels[ $item['field_type'] ] : 'Texte' ) . '</td>';
      echo '<td>' . esc_html( implode( ' / ', $usage_flags ) ) . '</td>';
      echo '<td>' . esc_html( isset( $item['status'] ) ? ucfirst( (string) $item['status'] ) : '' ) . '</td>';
      echo '<td>' . esc_html( (string) ( isset( $item['sort_order'] ) ? (int) $item['sort_order'] : 100 ) ) . '</td>';
      echo '<td><a class="acdc-button acdc-button-secondary" href="' . esc_url( $edit_url ) . '">Modifier</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'Confirmer la suppression ?\')">Supprimer</a></td>';
      echo '</tr>';
      $printed++;
    }
    if ( 0 === $printed ) {
      echo '<tr><td colspan="7">Aucun champ personnalisé ne correspond aux filtres actuels.</td></tr>';
    }
    echo '</tbody></table></div>';
    echo '</div>';

    echo '<div style="display:grid;gap:18px">';
    echo '<div class="acdc-panel" style="margin-bottom:0"><h2 style="margin-top:0">Lecture rapide</h2><table class="acdc-table"><tbody>';
    echo '<tr><th>Contacts marketing</th><td>' . esc_html( count( $contacts ) ) . '</td></tr>';
    echo '<tr><th>Formulaires marketing</th><td>' . esc_html( count( $forms ) ) . '</td></tr>';
    echo '<tr><th>Modèles d’e-mails</th><td>' . esc_html( count( $templates ) ) . '</td></tr>';
    echo '<tr><th>Champs à options</th><td>' . esc_html( $select_fields ) . '</td></tr>';
    echo '<tr><th>Usage formulaires</th><td>' . esc_html( $used_in_forms ) . '</td></tr>';
    echo '<tr><th>Usage modèles</th><td>' . esc_html( $used_in_templates ) . '</td></tr>';
    echo '</tbody></table></div>';

    echo '<div class="acdc-panel" style="margin-bottom:0"><h2 style="margin-top:0">Répartition par famille</h2><table class="acdc-table"><thead><tr><th>Famille</th><th>Volume</th></tr></thead><tbody>';
    foreach ( $family_labels as $family_key => $family_label ) {
      echo '<tr><td>' . esc_html( $family_label ) . '</td><td>' . esc_html( (string) ( isset( $family_counts[ $family_key ] ) ? $family_counts[ $family_key ] : 0 ) ) . '</td></tr>';
    }
    echo '</tbody></table></div>';

    echo '<div class="acdc-panel" style="margin-bottom:0"><h2 style="margin-top:0">Répartition par type</h2><table class="acdc-table"><thead><tr><th>Type</th><th>Volume</th></tr></thead><tbody>';
    foreach ( $type_labels as $type_key => $type_label ) {
      echo '<tr><td>' . esc_html( $type_label ) . '</td><td>' . esc_html( (string) ( isset( $type_counts[ $type_key ] ) ? $type_counts[ $type_key ] : 0 ) ) . '</td></tr>';
    }
    echo '</tbody></table></div>';

    echo '<div class="acdc-panel" style="margin-bottom:0"><h2 style="margin-top:0">Règles métier</h2><ul style="margin:0;padding-left:18px;color:#1E4777">';
    echo '<li>Les champs système doivent rester limités et documentés.</li>';
    echo '<li>Un champ obligatoire doit répondre à un vrai besoin métier ou réglementaire.</li>';
    echo '<li>Les listes déroulantes doivent contenir des valeurs simples et stables.</li>';
    echo '<li>Le code interne du champ doit rester court, lisible et sans accent.</li>';
    echo '<li>Le même champ doit pouvoir être réutilisé sans doublon dans plusieurs écrans.</li>';
    echo '</ul></div>';
    echo '</div>';
    echo '</div>';
  }



  private function render_front_marketing_webhooks_tab() {
    $entity = 'webhooks';
    $tab = 'marketing_webhooks';
    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
    $item_id = isset( $_GET['item_id'] ) ? sanitize_text_field( wp_unslash( $_GET['item_id'] ) ) : '';
    $items = $this->get_marketing_store( $entity, array() );
    $search = isset( $_GET['marketing_webhook_search'] ) ? sanitize_text_field( wp_unslash( $_GET['marketing_webhook_search'] ) ) : '';
    $direction_filter = isset( $_GET['marketing_webhook_direction'] ) ? sanitize_key( wp_unslash( $_GET['marketing_webhook_direction'] ) ) : '';
    $status_filter = isset( $_GET['marketing_webhook_status'] ) ? sanitize_key( wp_unslash( $_GET['marketing_webhook_status'] ) ) : '';

    $direction_counts = array(
      'outgoing' => 0,
      'incoming' => 0,
      'both'     => 0,
    );
    $status_counts = array(
      'active'   => 0,
      'draft'    => 0,
      'planned'  => 0,
      'archived' => 0,
    );
    $events_count = 0;
    $active_count = 0;
    $recent_count = 0;
    $last_update = '';
    $last_name = '';
    $categories_used = array();

    foreach ( $items as $item ) {
      $direction = isset( $item['direction'] ) ? sanitize_key( $item['direction'] ) : 'outgoing';
      if ( isset( $direction_counts[ $direction ] ) ) {
        $direction_counts[ $direction ]++;
      }

      $status = isset( $item['status'] ) ? sanitize_key( $item['status'] ) : 'draft';
      if ( isset( $status_counts[ $status ] ) ) {
        $status_counts[ $status ]++;
      }
      if ( 'active' === $status ) {
        $active_count++;
      }

      $events = isset( $item['events'] ) && is_array( $item['events'] ) ? $item['events'] : array();
      $events_count += count( $events );

      if ( ! empty( $item['category'] ) ) {
        $category = sanitize_key( $item['category'] );
        if ( ! isset( $categories_used[ $category ] ) ) {
          $categories_used[ $category ] = 0;
        }
        $categories_used[ $category ]++;
      }

      if ( empty( $last_update ) && ! empty( $item['updated_at'] ) ) {
        $last_update = (string) $item['updated_at'];
        $last_name = isset( $item['name'] ) ? (string) $item['name'] : '';
      }

      if ( ! empty( $item['updated_at'] ) ) {
        $timestamp = strtotime( (string) $item['updated_at'] );
        if ( false !== $timestamp && $timestamp >= strtotime( '-7 days' ) ) {
          $recent_count++;
        }
      }
    }

    $filtered = array();
    foreach ( $items as $item ) {
      $haystack = strtolower(
        trim(
          (string) ( isset( $item['name'] ) ? $item['name'] : '' ) . ' ' .
          (string) ( isset( $item['url'] ) ? $item['url'] : '' ) . ' ' .
          (string) ( isset( $item['description'] ) ? $item['description'] : '' ) . ' ' .
          wp_json_encode( isset( $item['events'] ) ? $item['events'] : array(), JSON_UNESCAPED_UNICODE )
        )
      );
      if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) {
        continue;
      }
      if ( '' !== $direction_filter && ( ! isset( $item['direction'] ) || sanitize_key( $item['direction'] ) !== $direction_filter ) ) {
        continue;
      }
      if ( '' !== $status_filter && ( ! isset( $item['status'] ) || sanitize_key( $item['status'] ) !== $status_filter ) ) {
        continue;
      }
      $filtered[] = $item;
    }

    $this->render_marketing_header( 'Webhooks / automatisations externes', 'Points de connexion du module marketing avec les outils externes, les notifications techniques et les flux automatisés.', array(
      array( 'label' => 'Créer un webhook', 'url' => $this->portal_page_url( array( 'tab' => $tab, 'action' => 'new' ) ), 'primary' => true ),
    ) );

    $this->render_marketing_cards( array(
      array( 'label' => 'Webhooks enregistrés', 'value' => count( $items ), 'help' => 'Total des points de connexion configurés.' ),
      array( 'label' => 'Webhooks actifs', 'value' => $active_count, 'help' => 'Connecteurs immédiatement exploitables.' ),
      array( 'label' => 'Événements suivis', 'value' => $events_count, 'help' => 'Somme des événements déclarés dans les webhooks.' ),
      array( 'label' => 'Mises à jour récentes', 'value' => $recent_count, 'help' => 'Webhooks modifiés dans les 7 derniers jours.' ),
    ) );

    echo '<div style="display:grid;grid-template-columns:1.15fr 1fr;gap:18px;margin-bottom:18px">';

    echo '<div style="display:grid;gap:18px">';
    echo '<div class="acdc-panel" style="margin-bottom:0">';
    echo '<h2 style="margin-top:0">Vue d’ensemble</h2>';
    echo '<table class="acdc-table"><tbody>';
    echo '<tr><th>Dernière mise à jour</th><td>' . esc_html( $last_update ? $last_update : 'Aucune mise à jour enregistrée' ) . '</td></tr>';
    echo '<tr><th>Dernier webhook modifié</th><td>' . esc_html( $last_name ? $last_name : '—' ) . '</td></tr>';
    echo '<tr><th>Usage de l’écran</th><td>Connexion avec Make, n8n, Zapier, services internes ou notifications applicatives.</td></tr>';
    echo '<tr><th>Niveau attendu</th><td>Configuration simple, lisible et traçable, sans logique technique inutilement complexe.</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:0">';
    echo '<h2 style="margin-top:0">Répartition par direction</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Direction</th><th>Volume</th><th>Lecture</th></tr></thead><tbody>';
    echo '<tr><td>Émission</td><td>' . esc_html( (string) $direction_counts['outgoing'] ) . '</td><td>Envoi d’événements depuis le plugin vers un outil externe.</td></tr>';
    echo '<tr><td>Réception</td><td>' . esc_html( (string) $direction_counts['incoming'] ) . '</td><td>Réception d’un appel externe vers le plugin.</td></tr>';
    echo '<tr><td>Les deux</td><td>' . esc_html( (string) $direction_counts['both'] ) . '</td><td>Connecteur bidirectionnel ou réversible.</td></tr>';
    echo '</tbody></table>';
    echo '</div>';
    echo '</div>';

    echo '<div style="display:grid;gap:18px">';
    echo '<div class="acdc-panel" style="margin-bottom:0">';
    echo '<h2 style="margin-top:0">Lecture rapide</h2>';
    echo '<table class="acdc-table"><tbody>';
    echo '<tr><th>Statuts gérés</th><td>Brouillon, actif, planifié, archivé.</td></tr>';
    echo '<tr><th>Conservation du secret</th><td>Le secret reste stocké dans le module et n’est affiché que dans l’écran d’édition.</td></tr>';
    echo '<tr><th>Suivi recommandé</th><td>Journal d’envoi, supervision technique et notifications internes.</td></tr>';
    echo '<tr><th>Règle de prudence</th><td>Un webhook actif doit toujours avoir une URL valide et des événements clairement définis.</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:0">';
    echo '<h2 style="margin-top:0">Répartition par statut</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Statut</th><th>Volume</th></tr></thead><tbody>';
    echo '<tr><td>Actif</td><td>' . esc_html( (string) $status_counts['active'] ) . '</td></tr>';
    echo '<tr><td>Brouillon</td><td>' . esc_html( (string) $status_counts['draft'] ) . '</td></tr>';
    echo '<tr><td>Planifié</td><td>' . esc_html( (string) $status_counts['planned'] ) . '</td></tr>';
    echo '<tr><td>Archivé</td><td>' . esc_html( (string) $status_counts['archived'] ) . '</td></tr>';
    echo '</tbody></table>';
    echo '</div>';
    echo '</div>';

    echo '</div>';

    echo '<div style="display:grid;grid-template-columns:1.25fr 1fr;gap:18px;margin-bottom:18px">';

    echo '<div class="acdc-panel" style="margin-bottom:0">';
    echo '<h2 style="margin-top:0">Filtres</h2>';
    echo '<form method="get" class="acdc-form" style="display:grid;grid-template-columns:1.3fr 1fr 1fr auto;gap:12px;align-items:end">';
    echo '<input type="hidden" name="page_id" value="' . esc_attr( get_queried_object_id() ) . '">';
    echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
    echo '<p style="margin:0"><label>Recherche</label><input type="text" name="marketing_webhook_search" value="' . esc_attr( $search ) . '" placeholder="Nom, URL, événement"></p>';
    echo '<p style="margin:0"><label>Direction</label><select name="marketing_webhook_direction"><option value="">Toutes</option><option value="outgoing" ' . selected( $direction_filter, 'outgoing', false ) . '>Émission</option><option value="incoming" ' . selected( $direction_filter, 'incoming', false ) . '>Réception</option><option value="both" ' . selected( $direction_filter, 'both', false ) . '>Les deux</option></select></p>';
    echo '<p style="margin:0"><label>Statut</label><select name="marketing_webhook_status"><option value="">Tous</option><option value="active" ' . selected( $status_filter, 'active', false ) . '>Actif</option><option value="draft" ' . selected( $status_filter, 'draft', false ) . '>Brouillon</option><option value="planned" ' . selected( $status_filter, 'planned', false ) . '>Planifié</option><option value="archived" ' . selected( $status_filter, 'archived', false ) . '>Archivé</option></select></p>';
    echo '<p style="margin:0"><button type="submit" class="acdc-button acdc-button-secondary">Filtrer</button></p>';
    echo '</form>';
    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:0">';
    echo '<h2 style="margin-top:0">Catégories utilisées</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Catégorie</th><th>Volume</th></tr></thead><tbody>';
    $printed_categories = 0;
    foreach ( $this->marketing_categories() as $category_key => $category_label ) {
      if ( empty( $categories_used[ $category_key ] ) ) {
        continue;
      }
      echo '<tr><td>' . esc_html( $category_label ) . '</td><td>' . esc_html( (string) $categories_used[ $category_key ] ) . '</td></tr>';
      $printed_categories++;
    }
    if ( 0 === $printed_categories ) {
      echo '<tr><td colspan="2">Aucune catégorie d’e-mail n’est encore associée à un webhook.</td></tr>';
    }
    echo '</tbody></table>';
    echo '</div>';

    echo '</div>';

    if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
      $item = array();
      foreach ( $items as $candidate ) {
        if ( isset( $candidate['id'] ) && $candidate['id'] === $item_id ) {
          $item = $candidate;
          break;
        }
      }
      echo '<div class="acdc-panel" style="margin-bottom:18px">';
      echo '<h2 style="margin-top:0">' . esc_html( 'edit' === $action ? 'Modifier le webhook' : 'Créer un webhook' ) . '</h2>';
      echo '<form class="acdc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
      wp_nonce_field( 'acdc_marketing_save_entity' );
      echo '<input type="hidden" name="action" value="acdc_marketing_save_entity">';
      echo '<input type="hidden" name="marketing_entity" value="webhooks">';
      echo '<input type="hidden" name="marketing_item[id]" value="' . esc_attr( isset( $item['id'] ) ? $item['id'] : '' ) . '">';
      echo '<div class="acdc-marketing-segments-form-grid">';
      echo '<p><label>Nom</label><input type="text" name="marketing_item[name]" value="' . esc_attr( isset( $item['name'] ) ? $item['name'] : '' ) . '" required></p>';
      echo '<p><label>Statut</label><select name="marketing_item[status]"><option value="draft" ' . selected( isset( $item['status'] ) ? $item['status'] : 'draft', 'draft', false ) . '>Brouillon</option><option value="active" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'active', false ) . '>Actif</option><option value="planned" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'planned', false ) . '>Planifié</option><option value="archived" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'archived', false ) . '>Archivé</option></select></p>';
      echo '<p><label>Catégorie d’e-mail</label><select name="marketing_item[category]">';
      foreach ( $this->marketing_categories() as $key => $label ) {
        echo '<option value="' . esc_attr( $key ) . '" ' . selected( isset( $item['category'] ) ? $item['category'] : 'marketing', $key, false ) . '>' . esc_html( $label ) . '</option>';
      }
      echo '</select></p>';
      echo '<p><label>Direction</label><select name="marketing_item[direction]"><option value="outgoing" ' . selected( isset( $item['direction'] ) ? $item['direction'] : 'outgoing', 'outgoing', false ) . '>Émission</option><option value="incoming" ' . selected( isset( $item['direction'] ) ? $item['direction'] : '', 'incoming', false ) . '>Réception</option><option value="both" ' . selected( isset( $item['direction'] ) ? $item['direction'] : '', 'both', false ) . '>Les deux</option></select></p>';
      echo '<p><label>URL</label><input type="url" name="marketing_item[url]" value="' . esc_attr( isset( $item['url'] ) ? $item['url'] : '' ) . '" placeholder="https://"></p>';
      echo '<p><label>Secret</label><input type="text" name="marketing_item[secret]" value="' . esc_attr( isset( $item['secret'] ) ? $item['secret'] : '' ) . '"></p>';
      echo '<p><label>Événements (CSV)</label><input type="text" name="marketing_item[events]" value="' . esc_attr( isset( $item['events'] ) && is_array( $item['events'] ) ? implode( ',', $item['events'] ) : '' ) . '" placeholder="contact.created,campaign.sent"></p>';
      echo '</div>';
      echo '<p><label>Description</label><textarea name="marketing_item[description]" rows="4">' . esc_textarea( isset( $item['description'] ) ? $item['description'] : '' ) . '</textarea></p>';
      echo '<p><button type="submit" class="acdc-button acdc-button-primary">Enregistrer le webhook</button></p>';
      echo '</form>';
      echo '</div>';
    }

    echo '<div class="acdc-panel" style="margin-bottom:18px">';
    echo '<h2 style="margin-top:0">Bibliothèque des webhooks</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Webhook</th><th>Direction</th><th>Catégorie</th><th>Événements</th><th>Statut</th><th>Mise à jour</th><th>Actions</th></tr></thead><tbody>';
    $printed = 0;
    foreach ( $filtered as $item ) {
      $edit_url = $this->portal_page_url( array( 'tab' => $tab, 'action' => 'edit', 'item_id' => isset( $item['id'] ) ? $item['id'] : '' ) );
      $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'acdc_marketing_delete_entity', 'marketing_entity' => 'webhooks', 'item_id' => isset( $item['id'] ) ? $item['id'] : '' ), admin_url( 'admin-post.php' ) ), 'acdc_marketing_delete_entity' );
      $events = isset( $item['events'] ) && is_array( $item['events'] ) ? array_map( 'sanitize_text_field', $item['events'] ) : array();
      $direction = isset( $item['direction'] ) ? sanitize_key( $item['direction'] ) : 'outgoing';
      $direction_label = 'outgoing' === $direction ? 'Émission' : ( 'incoming' === $direction ? 'Réception' : 'Les deux' );
      $category = isset( $item['category'] ) ? sanitize_key( $item['category'] ) : '';
      echo '<tr>';
      echo '<td><strong>' . esc_html( isset( $item['name'] ) ? $item['name'] : '' ) . '</strong>';
      if ( ! empty( $item['url'] ) ) {
        echo '<br><small>' . esc_html( $item['url'] ) . '</small>';
      }
      if ( ! empty( $item['description'] ) ) {
        echo '<br><small>' . esc_html( $item['description'] ) . '</small>';
      }
      echo '</td>';
      echo '<td>' . esc_html( $direction_label ) . '</td>';
      echo '<td>' . esc_html( isset( $this->marketing_categories()[ $category ] ) ? $this->marketing_categories()[ $category ] : '—' ) . '</td>';
      echo '<td><small>' . esc_html( ! empty( $events ) ? implode( ', ', $events ) : 'Aucun événement déclaré' ) . '</small></td>';
      echo '<td>' . esc_html( isset( $item['status'] ) ? ucfirst( (string) $item['status'] ) : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['updated_at'] ) ? $item['updated_at'] : '' ) . '</td>';
      echo '<td><a class="acdc-button acdc-button-secondary" href="' . esc_url( $edit_url ) . '">Modifier</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'Confirmer la suppression ?\')">Supprimer</a></td>';
      echo '</tr>';
      $printed++;
    }
    if ( 0 === $printed ) {
      echo '<tr><td colspan="7">Aucun webhook ne correspond aux filtres actuels.</td></tr>';
    }
    echo '</tbody></table>';
    echo '</div>';

    echo '<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px">';
    echo '<div class="acdc-panel" style="margin-bottom:0"><h2 style="margin-top:0">Règles métier</h2><ul style="margin:0;padding-left:18px;color:#1E4777">';
    echo '<li>Un webhook actif doit toujours être testé avant usage réel.</li>';
    echo '<li>Le nom doit rester lisible et relié à un usage métier clair.</li>';
    echo '<li>Les événements doivent être listés de façon simple et stable.</li>';
    echo '</ul></div>';

    echo '<div class="acdc-panel" style="margin-bottom:0"><h2 style="margin-top:0">Usages recommandés</h2><ul style="margin:0;padding-left:18px;color:#1E4777">';
    echo '<li>Déclencher un scénario depuis un outil externe.</li>';
    echo '<li>Notifier un envoi ou une inscription dans Make ou n8n.</li>';
    echo '<li>Propager un événement de désinscription ou de clic.</li>';
    echo '</ul></div>';

    echo '<div class="acdc-panel" style="margin-bottom:0"><h2 style="margin-top:0">Précautions</h2><ul style="margin:0;padding-left:18px;color:#1E4777">';
    echo '<li>Ne pas multiplier les webhooks redondants pour le même flux.</li>';
    echo '<li>Documenter la finalité du secret partagé.</li>';
    echo '<li>Archiver les connecteurs obsolètes au lieu de les laisser actifs.</li>';
    echo '</ul></div>';
    echo '</div>';
  }


  private function render_front_marketing_settings_tab() {
    /* ACDC 3.25.290 — Cet écran proposait un expéditeur écrit en dur, différent
       du reste du plugin : la fiche entreprise décide, ici comme ailleurs. */
    $__id_mk = $this->acdc_org_identity();
    $settings_defaults = array(
      'sender_name' => $__id_mk['raison_sociale'],
      'sender_email' => $__id_mk['email'],
      'reply_to' => $__id_mk['email'],
      'queue_threshold' => 20,
      'limit_per_minute' => 30,
      'limit_per_hour' => 300,
      'retry_enabled' => 1,
      'retry_max' => 3,
      'manual_pause' => 1,
      'allowed_hours_start' => '08:00',
      'allowed_hours_end' => '19:00',
      'click_tracking_enabled' => 1,
      'unsubscribe_mode' => 'category',
      'smtp_mode' => 'wp_mail_smtp_status_only',
      'soft_bounce_limit' => 3,
      'hard_bounce_blacklist' => 1,
      'internal_notification_email' => $__id_mk['email'],
      'scheduler_enabled' => 1,
      'scheduler_retry_lag' => 15,
      'retention_mode' => 'archive_only',
      'retention_logs_days' => 0,
      'public_form_mode' => 'plugin_pages',
    );
    $settings = wp_parse_args( $this->get_marketing_store( 'settings', array() ), $settings_defaults );
    $smtp_status = $this->get_marketing_smtp_status_label();
    $queue = $this->get_marketing_store( 'queue', array() );
    $logs = $this->get_marketing_store( 'logs', array() );
    $notifications = $this->get_marketing_store( 'notifications', array() );
    $forms = $this->get_marketing_store( 'forms', array() );

    $this->render_marketing_header( 'Réglages généraux', 'Paramètres globaux du module marketing : expéditeur unique, cadre d’envoi, rebonds, désinscriptions, conservation, tâches automatiques et pages publiques.' );
    $this->render_marketing_cards( array(
      array( 'label' => 'État SMTP', 'value' => $smtp_status, 'help' => 'Lecture seule depuis WP Mail SMTP.' ),
      array( 'label' => 'Expéditeur global', 'value' => (string) $settings['sender_name'], 'help' => (string) $settings['sender_email'] ),
      array( 'label' => 'Cadre horaire', 'value' => (string) $settings['allowed_hours_start'] . ' → ' . (string) $settings['allowed_hours_end'], 'help' => 'Plage d’envoi autorisée du module.' ),
      array( 'label' => 'Files / journaux', 'value' => count( $queue ) . ' / ' . count( $logs ), 'help' => count( $notifications ) . ' notification(s) interne(s) enregistrée(s).' ),
    ) );

    echo '<div style="display:grid;grid-template-columns:1.15fr .85fr;gap:18px;align-items:start">';

    echo '<div>';
    echo '<div class="acdc-panel" style="margin-bottom:18px">';
    echo '<h2 style="margin-top:0">Lecture rapide</h2>';
    echo '<table class="acdc-table"><tbody>';
    echo '<tr><th>Mode SMTP</th><td>' . esc_html( $smtp_status ) . '</td></tr>';
    echo '<tr><th>Mode de désinscription</th><td>' . esc_html( 'category' === $settings['unsubscribe_mode'] ? 'Par catégorie' : 'Global' ) . '</td></tr>';
    echo '<tr><th>Rebonds temporaires max.</th><td>' . esc_html( (string) $settings['soft_bounce_limit'] ) . '</td></tr>';
    echo '<tr><th>Liste noire après rebond dur</th><td>' . ( ! empty( $settings['hard_bounce_blacklist'] ) ? 'Oui' : 'Non' ) . '</td></tr>';
    echo '<tr><th>Suivi des clics</th><td>' . ( ! empty( $settings['click_tracking_enabled'] ) ? 'Activé' : 'Désactivé' ) . '</td></tr>';
    echo '<tr><th>Tâches automatiques</th><td>' . ( ! empty( $settings['scheduler_enabled'] ) ? 'Activées' : 'Désactivées' ) . '</td></tr>';
    echo '<tr><th>Pages publiques de formulaires</th><td>' . esc_html( 'plugin_pages' === $settings['public_form_mode'] ? 'Pages autonomes du plugin' : ( 'mixed' === $settings['public_form_mode'] ? 'Mode mixte' : 'Pages externes' ) ) . '</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:18px">';
    echo '<h2 style="margin-top:0">Répartition des réglages</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Bloc</th><th>Décision actuelle</th></tr></thead><tbody>';
    echo '<tr><td>Expéditeur</td><td>Unique sur tout le module</td></tr>';
    echo '<tr><td>Cadre d’envoi</td><td>' . esc_html( (string) $settings['queue_threshold'] ) . ' immédiat / ' . esc_html( (string) $settings['limit_per_minute'] ) . ' min / ' . esc_html( (string) $settings['limit_per_hour'] ) . ' heure</td></tr>';
    echo '<tr><td>Désinscriptions</td><td>' . esc_html( 'category' === $settings['unsubscribe_mode'] ? 'Par catégorie d’e-mail' : 'Globales' ) . '</td></tr>';
    echo '<tr><td>Conservation</td><td>' . esc_html( 'archive_only' === $settings['retention_mode'] ? 'Archive longue par défaut' : ( 'anonymize' === $settings['retention_mode'] ? 'Anonymisation encadrée' : 'Suppression manuelle encadrée' ) ) . '</td></tr>';
    echo '<tr><td>Automatisation</td><td>' . ( ! empty( $settings['scheduler_enabled'] ) ? 'Automatique + relance manuelle' : 'Manuelle' ) . '</td></tr>';
    echo '<tr><td>Formulaires</td><td>' . count( $forms ) . ' formulaire(s) natif(s) détecté(s)</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="acdc-panel">';
    echo '<form class="acdc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    wp_nonce_field( 'acdc_marketing_save_settings' );
    echo '<input type="hidden" name="action" value="acdc_marketing_save_settings">';

    echo '<div class="acdc-marketing-segment-section" style="margin-top:0;padding-top:0;border-top:0">';
    echo '<h3>Expéditeur global</h3>';
    echo '<p class="acdc-marketing-segment-help">Expéditeur unique imposé sur tout le module marketing, conformément au cadrage validé.</p>';
    echo '<div class="acdc-marketing-segments-form-grid">';
    echo '<p><label>Nom d’expéditeur</label><input type="text" name="marketing_settings[sender_name]" value="' . esc_attr( $settings['sender_name'] ) . '" required></p>';
    echo '<p><label>Adresse expéditrice</label><input type="email" name="marketing_settings[sender_email]" value="' . esc_attr( $settings['sender_email'] ) . '" required></p>';
    echo '<p><label>Adresse de réponse</label><input type="email" name="marketing_settings[reply_to]" value="' . esc_attr( $settings['reply_to'] ) . '"></p>';
    echo '<p><label>Adresse interne de supervision</label><input type="email" name="marketing_settings[internal_notification_email]" value="' . esc_attr( isset( $settings['internal_notification_email'] ) ? $settings['internal_notification_email'] : '' ) . '"><span class="acdc-help">Adresse utilisée pour les notifications techniques et internes du module.</span></p>';
    echo '</div></div>';

    echo '<div class="acdc-marketing-segment-section">';
    echo '<h3>Cadre d’envoi</h3>';
    echo '<p class="acdc-marketing-segment-help">Réglages prudents pour limiter la charge serveur et préserver la délivrabilité via SMTP.</p>';
    echo '<div class="acdc-marketing-segments-form-grid">';
    echo '<p><label>Seuil d’envoi immédiat</label><input type="number" min="1" name="marketing_settings[queue_threshold]" value="' . esc_attr( (string) $settings['queue_threshold'] ) . '"></p>';
    echo '<p><label>Limite par minute</label><input type="number" min="1" name="marketing_settings[limit_per_minute]" value="' . esc_attr( (string) $settings['limit_per_minute'] ) . '"></p>';
    echo '<p><label>Limite par heure</label><input type="number" min="1" name="marketing_settings[limit_per_hour]" value="' . esc_attr( (string) $settings['limit_per_hour'] ) . '"></p>';
    echo '<p><label>Début plage autorisée</label><input type="time" name="marketing_settings[allowed_hours_start]" value="' . esc_attr( $settings['allowed_hours_start'] ) . '"></p>';
    echo '<p><label>Fin plage autorisée</label><input type="time" name="marketing_settings[allowed_hours_end]" value="' . esc_attr( $settings['allowed_hours_end'] ) . '"></p>';
    echo '<p><label>Tentatives max.</label><input type="number" min="1" name="marketing_settings[retry_max]" value="' . esc_attr( (string) $settings['retry_max'] ) . '"></p>';
    echo '</div>';
    echo '<p class="acdc-checkbox-line"><label><input type="checkbox" name="marketing_settings[retry_enabled]" value="1" ' . checked( ! empty( $settings['retry_enabled'] ), true, false ) . '> Reprise automatique en cas d’échec</label></p>';
    echo '<p class="acdc-checkbox-line"><label><input type="checkbox" name="marketing_settings[manual_pause]" value="1" ' . checked( ! empty( $settings['manual_pause'] ), true, false ) . '> Mise en pause et reprise manuelle</label></p>';
    echo '</div>';

    echo '<div class="acdc-marketing-segment-section">';
    echo '<h3>Rebonds, désinscriptions et suivi</h3>';
    echo '<p class="acdc-marketing-segment-help">Réglages globaux des rebonds, du suivi des clics et de la logique de désinscription.</p>';
    echo '<div class="acdc-marketing-segments-form-grid">';
    echo '<p><label>Mode de désinscription</label><select name="marketing_settings[unsubscribe_mode]"><option value="category" ' . selected( $settings['unsubscribe_mode'], 'category', false ) . '>Par catégorie</option><option value="global" ' . selected( $settings['unsubscribe_mode'], 'global', false ) . '>Globale</option></select></p>';
    echo '<p><label>Rebonds temporaires avant blocage</label><input type="number" min="1" name="marketing_settings[soft_bounce_limit]" value="' . esc_attr( (string) $settings['soft_bounce_limit'] ) . '"></p>';
    echo '<p><label>Mode de conservation</label><select name="marketing_settings[retention_mode]"><option value="archive_only" ' . selected( $settings['retention_mode'], 'archive_only', false ) . '>Archive longue</option><option value="manual_delete" ' . selected( $settings['retention_mode'], 'manual_delete', false ) . '>Suppression manuelle encadrée</option><option value="anonymize" ' . selected( $settings['retention_mode'], 'anonymize', false ) . '>Anonymisation encadrée</option></select></p>';
    echo '<p><label>Durée de conservation des journaux (jours)</label><input type="number" min="0" name="marketing_settings[retention_logs_days]" value="' . esc_attr( (string) $settings['retention_logs_days'] ) . '"><span class="acdc-help">0 = pas de purge automatique.</span></p>';
    echo '</div>';
    echo '<p class="acdc-checkbox-line"><label><input type="checkbox" name="marketing_settings[hard_bounce_blacklist]" value="1" ' . checked( ! empty( $settings['hard_bounce_blacklist'] ), true, false ) . '> Ajouter automatiquement en liste noire après un rebond dur</label></p>';
    echo '<p class="acdc-checkbox-line"><label><input type="checkbox" name="marketing_settings[click_tracking_enabled]" value="1" ' . checked( ! empty( $settings['click_tracking_enabled'] ), true, false ) . '> Suivi des clics activé</label></p>';
    echo '</div>';

    echo '<div class="acdc-marketing-segment-section">';
    echo '<h3>Tâches automatiques et pages publiques</h3>';
    echo '<p class="acdc-marketing-segment-help">Réglages simples de supervision du moteur interne et des pages de formulaires générées par le plugin.</p>';
    echo '<div class="acdc-marketing-segments-form-grid">';
    echo '<p><label>Relance technique (minutes)</label><input type="number" min="1" name="marketing_settings[scheduler_retry_lag]" value="' . esc_attr( (string) $settings['scheduler_retry_lag'] ) . '"></p>';
    echo '<p><label>Mode des pages publiques</label><select name="marketing_settings[public_form_mode]"><option value="plugin_pages" ' . selected( $settings['public_form_mode'], 'plugin_pages', false ) . '>Pages autonomes du plugin</option><option value="mixed" ' . selected( $settings['public_form_mode'], 'mixed', false ) . '>Mode mixte</option><option value="external_pages" ' . selected( $settings['public_form_mode'], 'external_pages', false ) . '>Pages externes</option></select></p>';
    echo '<p><label>WP Mail SMTP</label><input type="text" value="' . esc_attr( $smtp_status ) . '" readonly></p>';
    echo '</div>';
    echo '<p class="acdc-checkbox-line"><label><input type="checkbox" name="marketing_settings[scheduler_enabled]" value="1" ' . checked( ! empty( $settings['scheduler_enabled'] ), true, false ) . '> Tâches automatiques internes activées</label></p>';
    echo '</div>';

    echo '<p><button type="submit" class="acdc-button acdc-button-primary">Enregistrer les réglages</button></p>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    echo '<div>';
    echo '<div class="acdc-panel" style="margin-bottom:18px">';
    echo '<h2 style="margin-top:0">Règles métier</h2>';
    echo '<ul style="margin:0;padding-left:18px;line-height:1.65">';
    echo '<li>Un seul expéditeur est utilisé sur tout le module.</li>';
    echo '<li>La désinscription marketing ne doit pas bloquer les e-mails opérationnels non concernés.</li>';
    echo '<li>Les traces sensibles et journaux utiles restent archivés tant qu’aucune purge encadrée n’est définie.</li>';
    echo '<li>Le module lit l’état de WP Mail SMTP mais ne gère pas sa configuration.</li>';
    echo '</ul>';
    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:18px">';
    echo '<h2 style="margin-top:0">Usages recommandés</h2>';
    echo '<table class="acdc-table"><tbody>';
    echo '<tr><th>Cadence</th><td>Conserver des volumes prudents et privilégier la file d’attente au-delà des petits envois.</td></tr>';
    echo '<tr><th>Rebonds</th><td>Bloquer vite les adresses dégradées pour éviter de polluer les campagnes futures.</td></tr>';
    echo '<tr><th>Consentement</th><td>Conserver une lecture par catégorie pour les publics mixtes du plugin.</td></tr>';
    echo '<tr><th>Supervision</th><td>Contrôler régulièrement les journaux d’envoi, la file d’attente et les notifications internes.</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">Précautions</h2>';
    echo '<ul style="margin:0;padding-left:18px;line-height:1.65">';
    echo '<li>Ne pas augmenter brutalement les limites d’envoi sans vérifier le comportement SMTP réel.</li>';
    echo '<li>Ne pas activer une purge automatique tant que les besoins de preuve et d’archivage ne sont pas validés.</li>';
    echo '<li>Conserver une adresse interne correcte pour recevoir les alertes importantes du module.</li>';
    echo '</ul>';
    echo '</div>';
    echo '</div>';

    echo '</div>';
  }


  private function render_front_marketing_supervision_tab() {
    $queue = $this->get_marketing_store( 'queue', array() );
    $logs = $this->get_marketing_store( 'logs', array() );
    $notifications = $this->get_marketing_store( 'notifications', array() );
    $scenarios = $this->get_marketing_store( 'scenarios', array() );
    $campaigns = $this->get_marketing_store( 'campaigns', array() );
    $settings = $this->get_marketing_store( 'settings', array() );

    $search = isset( $_GET['marketing_search'] ) ? sanitize_text_field( wp_unslash( $_GET['marketing_search'] ) ) : '';
    $type_filter = isset( $_GET['marketing_type_filter'] ) ? sanitize_key( wp_unslash( $_GET['marketing_type_filter'] ) ) : '';

    $queued = 0;
    $paused = 0;
    $failed = 0;
    $processed = 0;
    foreach ( $queue as $item ) {
      $status = isset( $item['status'] ) ? sanitize_key( (string) $item['status'] ) : 'queued';
      if ( in_array( $status, array( 'queued', 'pending', 'waiting' ), true ) ) {
        $queued++;
      } elseif ( in_array( $status, array( 'paused', 'hold' ), true ) ) {
        $paused++;
      } elseif ( in_array( $status, array( 'error', 'failed' ), true ) ) {
        $failed++;
      } elseif ( in_array( $status, array( 'sent', 'done', 'processed' ), true ) ) {
        $processed++;
      }
    }

    $recent_logs = 0;
    $error_logs = 0;
    $warning_logs = 0;
    $task_types = array();
    foreach ( $logs as $log ) {
      $date = isset( $log['date'] ) ? (string) $log['date'] : '';
      if ( '' !== $date && false !== strpos( $date, gmdate( 'Y-m-d' ) ) ) {
        $recent_logs++;
      }
      $type = isset( $log['type'] ) ? sanitize_key( (string) $log['type'] ) : 'info';
      if ( ! isset( $task_types[ $type ] ) ) {
        $task_types[ $type ] = 0;
      }
      $task_types[ $type ]++;
      if ( in_array( $type, array( 'error', 'failed' ), true ) ) {
        $error_logs++;
      } elseif ( in_array( $type, array( 'warning', 'alert' ), true ) ) {
        $warning_logs++;
      }
    }

    $active_scenarios = 0;
    foreach ( $scenarios as $scenario ) {
      if ( isset( $scenario['status'] ) && 'active' === $scenario['status'] ) {
        $active_scenarios++;
      }
    }

    $active_campaigns = 0;
    foreach ( $campaigns as $campaign ) {
      if ( isset( $campaign['status'] ) && in_array( $campaign['status'], array( 'active', 'scheduled', 'running' ), true ) ) {
        $active_campaigns++;
      }
    }

    $health = 'Stable';
    $health_help = 'Aucun blocage critique détecté dans le module.';
    if ( $failed > 0 || $error_logs > 0 ) {
      $health = 'À surveiller';
      $health_help = 'Des erreurs ou éléments bloqués demandent un contrôle rapide.';
    }
    if ( $failed > 3 || $error_logs > 5 ) {
      $health = 'Alerte';
      $health_help = 'Le module présente plusieurs anomalies techniques simultanées.';
    }

    arsort( $task_types );

    $this->render_marketing_header( 'Supervision technique', 'Vue de santé du module : tâches automatiques, erreurs, file d’attente, notifications et événements techniques à surveiller.', array(
      array( 'label' => 'Voir la file d’attente', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_queue' ) ), 'primary' => true ),
      array( 'label' => 'Voir les journaux', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_logs' ) ) ),
    ) );
    $this->render_marketing_cards( array(
      array( 'label' => 'État SMTP', 'value' => $this->get_marketing_smtp_status_label(), 'help' => 'Lecture seule depuis WP Mail SMTP.' ),
      array( 'label' => 'Santé globale', 'value' => $health, 'help' => $health_help ),
      array( 'label' => 'Éléments en file', 'value' => count( $queue ), 'help' => 'Tous statuts confondus : en attente, en pause, en erreur ou déjà traités.' ),
      array( 'label' => 'Événements techniques', 'value' => count( $logs ), 'help' => 'Historique agrégé du moteur marketing.' ),
    ) );

    echo '<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px;margin-bottom:18px">';
    echo '<div class="acdc-panel"><h3 style="margin-top:0">Vue d’ensemble</h3><table class="acdc-table"><tbody>';
    echo '<tr><th>Tâches récentes</th><td>' . esc_html( (string) $recent_logs ) . '</td></tr>';
    echo '<tr><th>Erreurs techniques</th><td>' . esc_html( (string) $error_logs ) . '</td></tr>';
    echo '<tr><th>Avertissements</th><td>' . esc_html( (string) $warning_logs ) . '</td></tr>';
    echo '<tr><th>Notifications internes</th><td>' . esc_html( (string) count( $notifications ) ) . '</td></tr>';
    echo '</tbody></table></div>';

    echo '<div class="acdc-panel"><h3 style="margin-top:0">File d’attente</h3><table class="acdc-table"><tbody>';
    echo '<tr><th>En attente</th><td>' . esc_html( (string) $queued ) . '</td></tr>';
    echo '<tr><th>En pause</th><td>' . esc_html( (string) $paused ) . '</td></tr>';
    echo '<tr><th>En erreur</th><td>' . esc_html( (string) $failed ) . '</td></tr>';
    echo '<tr><th>Traités</th><td>' . esc_html( (string) $processed ) . '</td></tr>';
    echo '</tbody></table></div>';

    echo '<div class="acdc-panel"><h3 style="margin-top:0">Automatisation</h3><table class="acdc-table"><tbody>';
    echo '<tr><th>Scénarios actifs</th><td>' . esc_html( (string) $active_scenarios ) . '</td></tr>';
    echo '<tr><th>Campagnes actives</th><td>' . esc_html( (string) $active_campaigns ) . '</td></tr>';
    echo '<tr><th>Tâches auto</th><td>' . ( ! empty( $settings['automation_enabled'] ) ? 'Activées' : 'Désactivées' ) . '</td></tr>';
    echo '<tr><th>Suivi des clics</th><td>' . ( ! empty( $settings['click_tracking_enabled'] ) ? 'Activé' : 'Désactivé' ) . '</td></tr>';
    echo '</tbody></table></div>';

    echo '<div class="acdc-panel"><h3 style="margin-top:0">Cadre technique</h3><table class="acdc-table"><tbody>';
    echo '<tr><th>Seuil immédiat</th><td>' . esc_html( isset( $settings['instant_send_threshold'] ) ? (string) $settings['instant_send_threshold'] : '20' ) . '</td></tr>';
    echo '<tr><th>Limite / minute</th><td>' . esc_html( isset( $settings['send_limit_per_minute'] ) ? (string) $settings['send_limit_per_minute'] : '30' ) . '</td></tr>';
    echo '<tr><th>Limite / heure</th><td>' . esc_html( isset( $settings['send_limit_per_hour'] ) ? (string) $settings['send_limit_per_hour'] : '300' ) . '</td></tr>';
    echo '<tr><th>Fenêtre autorisée</th><td>' . esc_html( ( isset( $settings['send_window_start'] ) ? $settings['send_window_start'] : '08:00' ) . ' → ' . ( isset( $settings['send_window_end'] ) ? $settings['send_window_end'] : '19:00' ) ) . '</td></tr>';
    echo '</tbody></table></div>';
    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:18px"><h3 style="margin-top:0">Lecture rapide</h3><table class="acdc-table"><tbody>';
    echo '<tr><th>Ce que surveille cette page</th><td>Exécutions récentes, erreurs, file d’attente, automatisations, état SMTP et paramètres techniques globaux.</td></tr>';
    echo '<tr><th>Quand agir</th><td>Dès qu’une erreur persiste, qu’une file grossit anormalement, qu’un scénario se bloque ou qu’une campagne reste en pause trop longtemps.</td></tr>';
    echo '<tr><th>Où corriger</th><td>File d’attente d’envoi, Journaux d’envoi, Notifications internes et Réglages généraux.</td></tr>';
    echo '<tr><th>Bonne pratique</th><td>Vérifier régulièrement l’état SMTP, la plage horaire autorisée et les limites d’envoi avant toute campagne importante.</td></tr>';
    echo '</tbody></table></div>';

    echo '<div style="display:grid;grid-template-columns:1.2fr 0.8fr;gap:18px;margin-bottom:18px">';
    echo '<div class="acdc-panel"><form method="get" class="acdc-filters" style="display:grid;grid-template-columns:2fr 1fr auto;gap:12px;align-items:end">';
    echo '<input type="hidden" name="page" value="acdc-formation-saas">';
    echo '<input type="hidden" name="tab" value="marketing_supervision">';
    echo '<p style="margin:0"><label>Recherche</label><input type="text" name="marketing_search" value="' . esc_attr( $search ) . '" placeholder="Message, contexte ou date"></p>';
    echo '<p style="margin:0"><label>Type</label><select name="marketing_type_filter"><option value="">Tous</option><option value="info" ' . selected( $type_filter, 'info', false ) . '>Information</option><option value="warning" ' . selected( $type_filter, 'warning', false ) . '>Avertissement</option><option value="error" ' . selected( $type_filter, 'error', false ) . '>Erreur</option><option value="import" ' . selected( $type_filter, 'import', false ) . '>Import</option><option value="campaign" ' . selected( $type_filter, 'campaign', false ) . '>Campagne</option><option value="scenario" ' . selected( $type_filter, 'scenario', false ) . '>Scénario</option></select></p>';
    echo '<p style="margin:0"><button type="submit" class="acdc-button acdc-button-secondary">Filtrer</button></p>';
    echo '</form></div>';

    echo '<div class="acdc-panel"><h3 style="margin-top:0">Répartition par type</h3><table class="acdc-table"><thead><tr><th>Type</th><th>Volume</th></tr></thead><tbody>';
    $printed_types = 0;
    foreach ( array_slice( $task_types, 0, 8, true ) as $type => $count ) {
      echo '<tr><td>' . esc_html( ucfirst( $type ) ) . '</td><td>' . esc_html( (string) $count ) . '</td></tr>';
      $printed_types++;
    }
    if ( 0 === $printed_types ) {
      echo '<tr><td colspan="2">Aucun type technique détecté pour le moment.</td></tr>';
    }
    echo '</tbody></table></div>';
    echo '</div>';

    echo '<div class="acdc-panel" style="margin-bottom:18px"><h3 style="margin-top:0">Règles métier</h3><table class="acdc-table"><tbody>';
    echo '<tr><th>File d’attente</th><td>Une file qui grossit sans baisse progressive signale souvent un blocage SMTP, une plage horaire trop restrictive ou des erreurs répétées.</td></tr>';
    echo '<tr><th>Erreurs</th><td>Une erreur isolée est acceptable. Des erreurs répétées sur le même type doivent conduire à un contrôle du paramétrage ou des données sources.</td></tr>';
    echo '<tr><th>Scénarios</th><td>Un scénario actif sans événements récents doit être contrôlé dans les scénarios automatiques et dans les journaux d’envoi.</td></tr>';
    echo '<tr><th>Notifications</th><td>Les notifications internes doivent compléter la supervision, pas la remplacer. Les sources principales restent les journaux et la file.</td></tr>';
    echo '</tbody></table></div>';

    echo '<div class="acdc-panel"><h2 style="margin-top:0">Historique détaillé</h2><table class="acdc-table"><thead><tr><th>Date</th><th>Type</th><th>Message</th><th>Contexte</th></tr></thead><tbody>';
    $printed = 0;
    foreach ( $logs as $log ) {
      $date = isset( $log['date'] ) ? (string) $log['date'] : '';
      $type = isset( $log['type'] ) ? sanitize_key( (string) $log['type'] ) : 'info';
      $message = isset( $log['message'] ) ? (string) $log['message'] : '';
      $context = isset( $log['context'] ) ? (string) $log['context'] : '';
      $haystack = strtolower( trim( $date . ' ' . $type . ' ' . $message . ' ' . $context ) );
      if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) {
        continue;
      }
      if ( '' !== $type_filter && $type_filter !== $type ) {
        continue;
      }
      echo '<tr><td>' . esc_html( $date ) . '</td><td>' . esc_html( ucfirst( $type ) ) . '</td><td>' . esc_html( $message ) . '</td><td>' . esc_html( $context ) . '</td></tr>';
      $printed++;
    }
    if ( 0 === $printed ) {
      echo '<tr><td colspan="4">Aucun événement technique ne correspond aux critères actuels.</td></tr>';
    }
    echo '</tbody></table></div>';
  }



  private function render_front_marketing_lists_tab() {
    $entity = 'lists';
    $tab = 'marketing_lists';
    $items = $this->get_marketing_store( $entity, array() );
    $contacts = $this->get_marketing_contacts_index();
    $campaigns = $this->get_marketing_store( 'campaigns', array() );
    $forms = $this->get_marketing_store( 'forms', array() );
    $scenarios = $this->get_marketing_store( 'scenarios', array() );
    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
    $item_id = isset( $_GET['item_id'] ) ? sanitize_text_field( wp_unslash( $_GET['item_id'] ) ) : '';
    $search = isset( $_GET['marketing_search'] ) ? sanitize_text_field( wp_unslash( $_GET['marketing_search'] ) ) : '';
    $status_filter = isset( $_GET['marketing_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['marketing_status_filter'] ) ) : '';

    $counts = array();
    foreach ( $items as $item ) {
      if ( empty( $item['id'] ) ) {
        continue;
      }
      $counts[ $item['id'] ] = 0;
    }
    foreach ( $contacts as $contact ) {
      if ( empty( $contact['lists'] ) || ! is_array( $contact['lists'] ) ) {
        continue;
      }
      foreach ( $contact['lists'] as $list_id ) {
        if ( isset( $counts[ $list_id ] ) ) {
          $counts[ $list_id ]++;
        }
      }
    }

    $active_lists = 0;
    $assigned_contacts = 0;
    foreach ( $items as $item ) {
      if ( isset( $item['status'] ) && 'active' === $item['status'] ) {
        $active_lists++;
      }
      if ( ! empty( $item['id'] ) && ! empty( $counts[ $item['id'] ] ) ) {
        $assigned_contacts += (int) $counts[ $item['id'] ];
      }
    }

    $this->render_marketing_header( 'Listes', 'Gérez vos listes marketing comme des contenants métier exploitables dans les contacts, formulaires, campagnes et scénarios.', array(
      array( 'label' => 'Créer une liste', 'url' => $this->portal_page_url( array( 'tab' => $tab, 'action' => 'new' ) ), 'primary' => true ),
      array( 'label' => 'Voir les contacts', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_contacts' ) ) ),
    ) );
    $this->render_marketing_cards( array(
      array( 'label' => 'Listes créées', 'value' => count( $items ), 'help' => 'Référentiel global du module marketing.' ),
      array( 'label' => 'Listes actives', 'value' => $active_lists, 'help' => 'Disponibles immédiatement dans les campagnes et formulaires.' ),
      array( 'label' => 'Contacts affectés', 'value' => $assigned_contacts, 'help' => 'Total des rattachements contact → liste.' ),
      array( 'label' => 'Usages détectés', 'value' => count( $campaigns ) . ' campagnes / ' . count( $forms ) . ' formulaires', 'help' => 'Repérage rapide des dépendances métier.' ),
    ) );

    if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
      $item = array();
      foreach ( $items as $candidate ) {
        if ( isset( $candidate['id'] ) && $candidate['id'] === $item_id ) {
          $item = $candidate;
          break;
        }
      }
      echo '<div class="acdc-panel" style="margin-bottom:18px"><form class="acdc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
      wp_nonce_field( 'acdc_marketing_save_entity' );
      echo '<input type="hidden" name="action" value="acdc_marketing_save_entity">';
      echo '<input type="hidden" name="marketing_entity" value="lists">';
      echo '<input type="hidden" name="marketing_item[id]" value="' . esc_attr( isset( $item['id'] ) ? $item['id'] : '' ) . '">';
      echo '<div class="acdc-marketing-segments-form-grid">';
      echo '<p><label>Nom de la liste</label><input type="text" name="marketing_item[name]" value="' . esc_attr( isset( $item['name'] ) ? $item['name'] : '' ) . '" required></p>';
      echo '<p><label>Code technique</label><input type="text" name="marketing_item[code]" value="' . esc_attr( isset( $item['code'] ) ? $item['code'] : '' ) . '" placeholder="prospects"></p>';
      echo '<p><label>Statut</label><select name="marketing_item[status]"><option value="draft" ' . selected( isset( $item['status'] ) ? $item['status'] : 'draft', 'draft', false ) . '>Brouillon</option><option value="active" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'active', false ) . '>Active</option><option value="archived" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'archived', false ) . '>Archivée</option></select></p>';
      echo '<p><label>Usage principal</label><select name="marketing_item[category]"><option value="marketing" ' . selected( isset( $item['category'] ) ? $item['category'] : 'marketing', 'marketing', false ) . '>Marketing / prospection</option><option value="commercial" ' . selected( isset( $item['category'] ) ? $item['category'] : '', 'commercial', false ) . '>Commercial / devis</option><option value="formation" ' . selected( isset( $item['category'] ) ? $item['category'] : '', 'formation', false ) . '>Suivi de formation</option><option value="administratif" ' . selected( isset( $item['category'] ) ? $item['category'] : '', 'administratif', false ) . '>Administratif</option></select></p>';
      echo '<p><label>Source d’entrée recommandée</label><input type="text" name="marketing_item[trigger_type]" value="' . esc_attr( isset( $item['trigger_type'] ) ? $item['trigger_type'] : '' ) . '" placeholder="Formulaire, import, activation manuelle..."></p>';
      echo '<p><label>Ordre d’affichage</label><input type="number" name="marketing_item[sort_order]" value="' . esc_attr( isset( $item['sort_order'] ) ? $item['sort_order'] : 100 ) . '"></p>';
      echo '</div>';
      echo '<p><label>Description / règle métier</label><textarea name="marketing_item[description]" rows="4">' . esc_textarea( isset( $item['description'] ) ? $item['description'] : '' ) . '</textarea></p>';
      echo '<p><label>Règles d’usage</label><textarea name="marketing_item[rules]" rows="5">' . esc_textarea( isset( $item['rules'] ) ? $item['rules'] : '' ) . '</textarea></p>';
      echo '<p><button type="submit" class="acdc-button acdc-button-primary">Enregistrer la liste</button> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab ) ) ) . '">Retour à la liste</a></p>';
      echo '</form></div>';
    }

    if ( 'view' === $action && ! empty( $item_id ) ) {
      $item = array();
      foreach ( $items as $candidate ) {
        if ( isset( $candidate['id'] ) && $candidate['id'] === $item_id ) {
          $item = $candidate;
          break;
        }
      }
      if ( ! empty( $item ) ) {
        $linked_contacts = array();
        foreach ( $contacts as $contact ) {
          if ( ! empty( $contact['lists'] ) && is_array( $contact['lists'] ) && in_array( $item_id, array_map( 'strval', $contact['lists'] ), true ) ) {
            $linked_contacts[] = $contact;
          }
        }
        $linked_campaigns = array();
        foreach ( $campaigns as $campaign ) {
          if ( ! empty( $campaign['list_ids'] ) && is_array( $campaign['list_ids'] ) && in_array( $item_id, array_map( 'strval', $campaign['list_ids'] ), true ) ) {
            $linked_campaigns[] = $campaign;
          }
        }
        $linked_forms = array();
        foreach ( $forms as $form ) {
          if ( ! empty( $form['list_ids'] ) && is_array( $form['list_ids'] ) && in_array( $item_id, array_map( 'strval', $form['list_ids'] ), true ) ) {
            $linked_forms[] = $form;
          }
        }
        $linked_scenarios = array();
        foreach ( $scenarios as $scenario ) {
          if ( ! empty( $scenario['list_ids'] ) && is_array( $scenario['list_ids'] ) && in_array( $item_id, array_map( 'strval', $scenario['list_ids'] ), true ) ) {
            $linked_scenarios[] = $scenario;
          }
        }

        echo '<div class="acdc-panel" style="margin-bottom:18px">';
        echo '<div style="display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap;align-items:flex-start">';
        echo '<div><h2 style="margin:0 0 8px 0">Fiche liste</h2><p style="margin:0;color:#1E4777">Une liste = un regroupement métier exploitable dans les contacts, formulaires, campagnes et scénarios.</p></div>';
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap">';
        echo '<a class="acdc-button acdc-button-primary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab, 'action' => 'edit', 'item_id' => $item_id ) ) ) . '">Modifier</a>';
        echo '<a class="acdc-button acdc-button-secondary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab ) ) ) . '">Retour à la liste</a>';
        echo '</div></div></div>';

        echo '<div style="display:grid;grid-template-columns:1.1fr 0.9fr;gap:18px;margin-bottom:18px">';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Informations générales</h3><table class="acdc-table"><tbody>';
        echo '<tr><th>Nom</th><td>' . esc_html( isset( $item['name'] ) ? $item['name'] : '' ) . '</td></tr>';
        echo '<tr><th>Code technique</th><td>' . esc_html( isset( $item['code'] ) ? $item['code'] : '' ) . '</td></tr>';
        echo '<tr><th>Statut</th><td>' . esc_html( isset( $item['status'] ) ? $item['status'] : '' ) . '</td></tr>';
        echo '<tr><th>Type</th><td>' . ( ! empty( $item['is_system'] ) ? 'Système' : 'Utilisateur' ) . '</td></tr>';
        echo '<tr><th>Usage principal</th><td>' . esc_html( isset( $item['category'] ) ? $item['category'] : '' ) . '</td></tr>';
        echo '<tr><th>Source d’entrée</th><td>' . esc_html( isset( $item['trigger_type'] ) ? $item['trigger_type'] : '' ) . '</td></tr>';
        echo '<tr><th>Description</th><td>' . esc_html( isset( $item['description'] ) ? $item['description'] : '' ) . '</td></tr>';
        echo '<tr><th>Règles d’usage</th><td>' . esc_html( isset( $item['rules'] ) ? $item['rules'] : '' ) . '</td></tr>';
        echo '<tr><th>Mise à jour</th><td>' . esc_html( isset( $item['updated_at'] ) ? $item['updated_at'] : '' ) . '</td></tr>';
        echo '</tbody></table></div>';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Dépendances métier</h3><table class="acdc-table"><tbody>';
        echo '<tr><th>Contacts affectés</th><td>' . esc_html( (string) count( $linked_contacts ) ) . '</td></tr>';
        echo '<tr><th>Campagnes liées</th><td>' . esc_html( (string) count( $linked_campaigns ) ) . '</td></tr>';
        echo '<tr><th>Formulaires liés</th><td>' . esc_html( (string) count( $linked_forms ) ) . '</td></tr>';
        echo '<tr><th>Scénarios liés</th><td>' . esc_html( (string) count( $linked_scenarios ) ) . '</td></tr>';
        echo '</tbody></table></div>';
        echo '</div>';

        echo '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px">';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Contacts de la liste</h3><table class="acdc-table"><thead><tr><th>Nom</th><th>Type</th><th>E-mail</th></tr></thead><tbody>';
        foreach ( array_slice( $linked_contacts, 0, 20 ) as $contact ) {
          echo '<tr><td>' . esc_html( isset( $contact['name'] ) ? $contact['name'] : '' ) . '</td><td>' . esc_html( isset( $contact['type'] ) ? $contact['type'] : '' ) . '</td><td>' . esc_html( isset( $contact['email'] ) ? $contact['email'] : '' ) . '</td></tr>';
        }
        if ( empty( $linked_contacts ) ) {
          echo '<tr><td colspan="3">Aucun contact lié à cette liste.</td></tr>';
        }
        echo '</tbody></table></div>';

        echo '<div class="acdc-panel"><h3 style="margin-top:0">Usages détectés</h3><table class="acdc-table"><thead><tr><th>Type</th><th>Nom</th><th>Statut</th></tr></thead><tbody>';
        $usage_rows = array();
        foreach ( $linked_campaigns as $campaign ) {
          $usage_rows[] = array( 'type' => 'Campagne', 'name' => isset( $campaign['name'] ) ? $campaign['name'] : '', 'status' => isset( $campaign['status'] ) ? $campaign['status'] : '' );
        }
        foreach ( $linked_forms as $form ) {
          $usage_rows[] = array( 'type' => 'Formulaire', 'name' => isset( $form['name'] ) ? $form['name'] : '', 'status' => isset( $form['status'] ) ? $form['status'] : '' );
        }
        foreach ( $linked_scenarios as $scenario ) {
          $usage_rows[] = array( 'type' => 'Scénario', 'name' => isset( $scenario['name'] ) ? $scenario['name'] : '', 'status' => isset( $scenario['status'] ) ? $scenario['status'] : '' );
        }
        foreach ( array_slice( $usage_rows, 0, 20 ) as $row ) {
          echo '<tr><td>' . esc_html( $row['type'] ) . '</td><td>' . esc_html( $row['name'] ) . '</td><td>' . esc_html( $row['status'] ) . '</td></tr>';
        }
        if ( empty( $usage_rows ) ) {
          echo '<tr><td colspan="3">Aucun usage détecté pour cette liste.</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '</div>';
      }
    }

    echo '<div class="acdc-panel" style="margin-bottom:18px"><form method="get" class="acdc-filters" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;align-items:end">';
    echo '<input type="hidden" name="page" value="acdc-formation-saas">';
    echo '<input type="hidden" name="tab" value="marketing_lists">';
    echo '<p style="margin:0"><label>Recherche</label><input type="text" name="marketing_search" value="' . esc_attr( $search ) . '" placeholder="Nom ou description"></p>';
    echo '<p style="margin:0"><label>Statut</label><select name="marketing_status_filter"><option value="">Tous</option><option value="draft" ' . selected( $status_filter, 'draft', false ) . '>Brouillon</option><option value="active" ' . selected( $status_filter, 'active', false ) . '>Actif</option><option value="archived" ' . selected( $status_filter, 'archived', false ) . '>Archivé</option></select></p>';
    echo '<p style="margin:0"><label>Tri</label><select disabled><option>Nom croissant</option></select></p>';
    echo '<p style="margin:0"><button type="submit" class="acdc-button acdc-button-secondary">Filtrer</button></p>';
    echo '</form></div>';

    echo '<div class="acdc-panel"><table class="acdc-table"><thead><tr><th>Nom</th><th>Statut</th><th>Usage</th><th>Contacts</th><th>Formulaires / campagnes</th><th>Mise à jour</th><th>Actions</th></tr></thead><tbody>';
    $printed = 0;
    foreach ( $items as $item ) {
      $haystack = strtolower( trim( (string) ( isset( $item['name'] ) ? $item['name'] : '' ) . ' ' . ( isset( $item['description'] ) ? $item['description'] : '' ) ) );
      if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) {
        continue;
      }
      if ( '' !== $status_filter && ( ! isset( $item['status'] ) || $item['status'] !== $status_filter ) ) {
        continue;
      }
      $id = isset( $item['id'] ) ? $item['id'] : '';
      $edit_url = $this->portal_page_url( array( 'tab' => $tab, 'action' => 'edit', 'item_id' => $id ) );
      $view_url = $this->portal_page_url( array( 'tab' => $tab, 'action' => 'view', 'item_id' => $id ) );
      $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'acdc_marketing_delete_entity', 'marketing_entity' => 'lists', 'item_id' => $id ), admin_url( 'admin-post.php' ) ), 'acdc_marketing_delete_entity' );
      $related_forms = 0;
      foreach ( $forms as $form ) {
        if ( ! empty( $form['list_ids'] ) && is_array( $form['list_ids'] ) && in_array( $id, array_map( 'strval', $form['list_ids'] ), true ) ) {
          $related_forms++;
        }
      }
      $related_campaigns = 0;
      foreach ( $campaigns as $campaign ) {
        if ( ! empty( $campaign['list_ids'] ) && is_array( $campaign['list_ids'] ) && in_array( $id, array_map( 'strval', $campaign['list_ids'] ), true ) ) {
          $related_campaigns++;
        }
      }
      echo '<tr>';
      echo '<td><strong>' . esc_html( isset( $item['name'] ) ? $item['name'] : '' ) . '</strong><br><small>' . esc_html( isset( $item['description'] ) ? $item['description'] : '' ) . '</small></td>';
      echo '<td>' . esc_html( isset( $item['status'] ) ? $item['status'] : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['category'] ) ? $item['category'] : '' ) . '</td>';
      echo '<td>' . esc_html( (string) ( isset( $counts[ $id ] ) ? $counts[ $id ] : 0 ) ) . '</td>';
      echo '<td>' . esc_html( $related_forms . ' / ' . $related_campaigns ) . '</td>';
      echo '<td>' . esc_html( isset( $item['updated_at'] ) ? $item['updated_at'] : '' ) . '</td>';
      echo '<td><a class="acdc-button acdc-button-secondary" href="' . esc_url( $view_url ) . '">Voir</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $edit_url ) . '">Modifier</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'Confirmer la suppression ?\')">Supprimer</a></td>';
      echo '</tr>';
      $printed++;
    }
    if ( 0 === $printed ) {
      echo '<tr><td colspan="7">Aucune liste ne correspond aux critères actuels.</td></tr>';
    }
    echo '</tbody></table></div>';
  }



  private function render_front_marketing_segments_tab() {
    $entity = 'segments';
    $tab = 'marketing_segments';
    $items = $this->get_marketing_store( $entity, array() );
    $contacts = $this->get_marketing_contacts_index();
    $lists = $this->get_marketing_store( 'lists', array() );
    $tags = $this->get_marketing_store( 'tags', array() );
    $campaigns = $this->get_marketing_store( 'campaigns', array() );
    $scenarios = $this->get_marketing_store( 'scenarios', array() );
    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
    $item_id = isset( $_GET['item_id'] ) ? sanitize_text_field( wp_unslash( $_GET['item_id'] ) ) : '';
    $search = isset( $_GET['marketing_search'] ) ? sanitize_text_field( wp_unslash( $_GET['marketing_search'] ) ) : '';
    $status_filter = isset( $_GET['marketing_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['marketing_status_filter'] ) ) : '';

    $evaluate_contact = function( $segment, $contact ) {
      if ( ! empty( $segment['contact_type'] ) && ( empty( $contact['type'] ) || $segment['contact_type'] !== $contact['type'] ) ) {
        return false;
      }
      if ( ! empty( $segment['enabled_only'] ) && empty( $contact['enabled'] ) ) {
        return false;
      }
      if ( ! empty( $segment['status_filter'] ) ) {
        $statuses = isset( $contact['statuses'] ) && is_array( $contact['statuses'] ) ? $contact['statuses'] : array();
        if ( ! in_array( $segment['status_filter'], $statuses, true ) ) {
          return false;
        }
      }
      if ( ! empty( $segment['list_ids'] ) && is_array( $segment['list_ids'] ) ) {
        $contact_lists = isset( $contact['lists'] ) && is_array( $contact['lists'] ) ? array_map( 'strval', $contact['lists'] ) : array();
        $required_lists = array_map( 'strval', $segment['list_ids'] );
        if ( 0 === count( array_intersect( $required_lists, $contact_lists ) ) ) {
          return false;
        }
      }
      if ( ! empty( $segment['tag_ids'] ) && is_array( $segment['tag_ids'] ) ) {
        $contact_tags = isset( $contact['tags'] ) && is_array( $contact['tags'] ) ? array_map( 'strval', $contact['tags'] ) : array();
        $required_tags = array_map( 'strval', $segment['tag_ids'] );
        if ( 0 === count( array_intersect( $required_tags, $contact_tags ) ) ) {
          return false;
        }
      }
      if ( ! empty( $segment['engagement_filter'] ) ) {
        $score = isset( $contact['score'] ) ? (int) $contact['score'] : 0;
        if ( 'hot' === $segment['engagement_filter'] && $score < 60 ) {
          return false;
        }
        if ( 'warm' === $segment['engagement_filter'] && ( $score < 25 || $score >= 60 ) ) {
          return false;
        }
        if ( 'cold' === $segment['engagement_filter'] && $score >= 25 ) {
          return false;
        }
      }
      return true;
    };

    $active_segments = 0;
    $dynamic_segments = 0;
    $population_cumulated = 0;
    foreach ( $items as &$item ) {
      $population = 0;
      foreach ( $contacts as $contact ) {
        if ( $evaluate_contact( $item, $contact ) ) {
          $population++;
        }
      }
      $item['population_count'] = $population;
      $population_cumulated += $population;
      if ( isset( $item['status'] ) && 'active' === $item['status'] ) {
        $active_segments++;
      }
      if ( isset( $item['segment_mode'] ) && 'dynamic' === $item['segment_mode'] ) {
        $dynamic_segments++;
      }
    }
    unset( $item );

    $this->render_marketing_header( 'Segments', 'Créez des segments métier pour cibler vos campagnes et vos scénarios à partir des contacts, listes, étiquettes et niveaux d’engagement.', array(
      array( 'label' => 'Créer un segment', 'url' => $this->portal_page_url( array( 'tab' => $tab, 'action' => 'new' ) ), 'primary' => true ),
      array( 'label' => 'Voir les contacts', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_contacts' ) ) ),
    ) );
    $this->render_marketing_cards( array(
      array( 'label' => 'Segments créés', 'value' => count( $items ), 'help' => 'Référentiel global du ciblage marketing.' ),
      array( 'label' => 'Segments actifs', 'value' => $active_segments, 'help' => 'Disponibles immédiatement dans les campagnes et scénarios.' ),
      array( 'label' => 'Segments dynamiques', 'value' => $dynamic_segments, 'help' => 'Population recalculée à partir des critères métier.' ),
      array( 'label' => 'Population cumulée', 'value' => $population_cumulated, 'help' => 'Total des correspondances calculées sur tous les segments.' ),
    ) );

    if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
      $item = array();
      foreach ( $items as $candidate ) {
        if ( isset( $candidate['id'] ) && $candidate['id'] === $item_id ) {
          $item = $candidate;
          break;
        }
      }
      echo '<div class="acdc-panel" style="margin-bottom:18px"><form class="acdc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
      wp_nonce_field( 'acdc_marketing_save_entity' );
      echo '<input type="hidden" name="action" value="acdc_marketing_save_entity">';
      echo '<input type="hidden" name="marketing_entity" value="segments">';
      echo '<input type="hidden" name="marketing_item[id]" value="' . esc_attr( isset( $item['id'] ) ? $item['id'] : '' ) . '">';
      echo '<style>.acdc-marketing-segment-choice-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:10px}.acdc-marketing-segment-choice{display:block;margin:0;cursor:pointer}.acdc-marketing-segment-choice input{position:absolute;opacity:0;pointer-events:none}.acdc-marketing-segment-choice-card{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border:1px solid #D8E0EF;border-radius:12px;background:#fff;min-height:74px;transition:border-color .15s ease,box-shadow .15s ease,background-color .15s ease}.acdc-marketing-segment-choice:hover .acdc-marketing-segment-choice-card{border-color:#C5A253;box-shadow:0 4px 14px rgba(11,7,6,.08)}.acdc-marketing-segment-choice input:checked + .acdc-marketing-segment-choice-card{border-color:#C5A253;background:#FBF6E8;box-shadow:0 4px 14px rgba(197,162,83,.18)}.acdc-marketing-segment-choice-check{width:18px;height:18px;border:2px solid #C5A253;border-radius:6px;flex:0 0 18px;margin-top:2px;background:#fff}.acdc-marketing-segment-choice input:checked + .acdc-marketing-segment-choice-card .acdc-marketing-segment-choice-check{background:#C5A253;box-shadow:inset 0 0 0 3px #fff}.acdc-marketing-segment-choice-title{font-weight:700;color:#17325C;line-height:1.25}.acdc-marketing-segment-choice-meta{margin-top:4px;font-size:12px;line-height:1.35;color:#5F749A}.acdc-marketing-segment-toggle{margin-top:10px}.acdc-marketing-segment-toggle .acdc-marketing-segment-choice-card{min-height:auto;align-items:center}.acdc-marketing-segment-section{margin-top:18px;padding-top:14px;border-top:1px solid #E2E8F3}.acdc-marketing-segment-section h3{margin:0 0 6px 0;font-size:15px;color:#17325C}.acdc-marketing-segment-section p.acdc-marketing-segment-help{margin:0 0 10px 0;font-size:12px;color:#5F749A}</style>';
      echo '<div class="acdc-marketing-segments-form-grid">';
      echo '<p><label>Nom du segment</label><input type="text" name="marketing_item[name]" value="' . esc_attr( isset( $item['name'] ) ? $item['name'] : '' ) . '" required></p>';
      echo '<p><label>Code technique</label><input type="text" name="marketing_item[code]" value="' . esc_attr( isset( $item['code'] ) ? $item['code'] : '' ) . '" placeholder="prospects-chauds-a-relancer"></p>';
      echo '<p><label>Statut</label><select name="marketing_item[status]"><option value="draft" ' . selected( isset( $item['status'] ) ? $item['status'] : 'draft', 'draft', false ) . '>Brouillon</option><option value="active" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'active', false ) . '>Actif</option><option value="archived" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'archived', false ) . '>Archivé</option></select></p>';
      echo '<p><label>Mode de segment</label><select name="marketing_item[segment_mode]"><option value="dynamic" ' . selected( isset( $item['segment_mode'] ) ? $item['segment_mode'] : 'dynamic', 'dynamic', false ) . '>Dynamique</option><option value="static" ' . selected( isset( $item['segment_mode'] ) ? $item['segment_mode'] : '', 'static', false ) . '>Figé</option></select></p>';
      echo '<p><label>Type de contact</label><select name="marketing_item[contact_type]"><option value="">Tous les types</option>';
      foreach ( $this->marketing_type_labels() as $type_key => $type_label ) {
        echo '<option value="' . esc_attr( $type_key ) . '" ' . selected( isset( $item['contact_type'] ) ? $item['contact_type'] : '', $type_key, false ) . '>' . esc_html( $type_label ) . '</option>';
      }
      echo '</select></p>';
      echo '<p><label>Niveau d’engagement</label><select name="marketing_item[engagement_filter]"><option value="">Tous</option><option value="hot" ' . selected( isset( $item['engagement_filter'] ) ? $item['engagement_filter'] : '', 'hot', false ) . '>Chaud</option><option value="warm" ' . selected( isset( $item['engagement_filter'] ) ? $item['engagement_filter'] : '', 'warm', false ) . '>Tiède</option><option value="cold" ' . selected( isset( $item['engagement_filter'] ) ? $item['engagement_filter'] : '', 'cold', false ) . '>Froid</option></select></p>';
      echo '<p><label>Statut marketing</label><select name="marketing_item[status_filter]"><option value="">Tous</option><option value="authorized" ' . selected( isset( $item['status_filter'] ) ? $item['status_filter'] : '', 'authorized', false ) . '>Autorisé</option><option value="pending" ' . selected( isset( $item['status_filter'] ) ? $item['status_filter'] : '', 'pending', false ) . '>En attente</option><option value="refused" ' . selected( isset( $item['status_filter'] ) ? $item['status_filter'] : '', 'refused', false ) . '>Refusé</option><option value="not_requested" ' . selected( isset( $item['status_filter'] ) ? $item['status_filter'] : '', 'not_requested', false ) . '>Non demandé</option></select></p>';
      echo '</div>';
      echo '<div class="acdc-marketing-segment-toggle">';
      echo '<label class="acdc-marketing-segment-choice">';
      echo '<input type="checkbox" name="marketing_item[enabled_only]" value="1" ' . checked( ! empty( $item['enabled_only'] ), true, false ) . '>';
      echo '<span class="acdc-marketing-segment-choice-card"><span class="acdc-marketing-segment-choice-check"></span><span><span class="acdc-marketing-segment-choice-title">Contacts marketing activés uniquement</span><span class="acdc-marketing-segment-choice-meta">Ne retient que les fiches déjà activées pour la communication marketing.</span></span></span>';
      echo '</label>';
      echo '</div>';
      echo '<div class="acdc-marketing-segment-section"><h3>Listes liées</h3><p class="acdc-marketing-segment-help">Choisissez une ou plusieurs listes métier à inclure dans le ciblage.</p><div class="acdc-marketing-segment-choice-grid">';
      $current_lists = isset( $item['list_ids'] ) && is_array( $item['list_ids'] ) ? array_map( 'strval', $item['list_ids'] ) : array();
      foreach ( $lists as $list ) {
        if ( empty( $list['id'] ) ) { continue; }
        $list_name = isset( $list['name'] ) ? $list['name'] : $list['id'];
        $list_meta = array();
        if ( ! empty( $list['usage'] ) ) { $list_meta[] = $list['usage']; }
        if ( ! empty( $list['source_hint'] ) ) { $list_meta[] = $list['source_hint']; }
        echo '<label class="acdc-marketing-segment-choice">';
        echo '<input type="checkbox" name="marketing_item[list_ids][]" value="' . esc_attr( $list['id'] ) . '" ' . checked( in_array( (string) $list['id'], $current_lists, true ), true, false ) . '>';
        echo '<span class="acdc-marketing-segment-choice-card"><span class="acdc-marketing-segment-choice-check"></span><span><span class="acdc-marketing-segment-choice-title">' . esc_html( $list_name ) . '</span>';
        if ( ! empty( $list_meta ) ) {
          echo '<span class="acdc-marketing-segment-choice-meta">' . esc_html( implode( ' • ', array_filter( $list_meta ) ) ) . '</span>';
        }
        echo '</span></span></label>';
      }
      if ( empty( $lists ) ) { echo '<span style="color:#1E4777">Aucune liste disponible.</span>'; }
      echo '</div></div>';
      echo '<div class="acdc-marketing-segment-section"><h3>Étiquettes liées</h3><p class="acdc-marketing-segment-help">Utilisez les étiquettes pour qualifier plus finement votre cible.</p><div class="acdc-marketing-segment-choice-grid">';
      $current_tags = isset( $item['tag_ids'] ) && is_array( $item['tag_ids'] ) ? array_map( 'strval', $item['tag_ids'] ) : array();
      foreach ( $tags as $tag ) {
        if ( empty( $tag['id'] ) ) { continue; }
        $tag_name = isset( $tag['name'] ) ? $tag['name'] : $tag['id'];
        $tag_title = $tag_name;
        $tag_meta = '';
        if ( false !== strpos( $tag_name, ':' ) ) {
          $parts = explode( ':', $tag_name, 2 );
          $tag_title = trim( $parts[0] ) . ' : ' . trim( $parts[1] );
        }
        if ( ! empty( $tag['usage'] ) ) { $tag_meta = $tag['usage']; }
        echo '<label class="acdc-marketing-segment-choice">';
        echo '<input type="checkbox" name="marketing_item[tag_ids][]" value="' . esc_attr( $tag['id'] ) . '" ' . checked( in_array( (string) $tag['id'], $current_tags, true ), true, false ) . '>';
        echo '<span class="acdc-marketing-segment-choice-card"><span class="acdc-marketing-segment-choice-check"></span><span><span class="acdc-marketing-segment-choice-title">' . esc_html( $tag_title ) . '</span>';
        if ( ! empty( $tag_meta ) ) {
          echo '<span class="acdc-marketing-segment-choice-meta">' . esc_html( $tag_meta ) . '</span>';
        }
        echo '</span></span></label>';
      }
      if ( empty( $tags ) ) { echo '<span style="color:#1E4777">Aucune étiquette disponible.</span>'; }
      echo '</div></div>';
      echo '<p><label>Règles métier</label><textarea name="marketing_item[rules]" rows="5">' . esc_textarea( isset( $item['rules'] ) ? $item['rules'] : '' ) . '</textarea></p>';
      echo '<p><label>Description</label><textarea name="marketing_item[description]" rows="4">' . esc_textarea( isset( $item['description'] ) ? $item['description'] : '' ) . '</textarea></p>';
      echo '<p><button type="submit" class="acdc-button acdc-button-primary">Enregistrer le segment</button> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab ) ) ) . '">Retour à la liste</a></p>';
      echo '</form></div>';
    }

    if ( 'view' === $action && ! empty( $item_id ) ) {
      $item = array();
      foreach ( $items as $candidate ) {
        if ( isset( $candidate['id'] ) && $candidate['id'] === $item_id ) { $item = $candidate; break; }
      }
      if ( ! empty( $item ) ) {
        $matched_contacts = array();
        foreach ( $contacts as $contact ) {
          if ( $evaluate_contact( $item, $contact ) ) { $matched_contacts[] = $contact; }
        }
        $linked_campaigns = array();
        foreach ( $campaigns as $campaign ) {
          if ( ! empty( $campaign['segment_ids'] ) && is_array( $campaign['segment_ids'] ) && in_array( $item_id, array_map( 'strval', $campaign['segment_ids'] ), true ) ) { $linked_campaigns[] = $campaign; }
        }
        $linked_scenarios = array();
        foreach ( $scenarios as $scenario ) {
          if ( ! empty( $scenario['segment_ids'] ) && is_array( $scenario['segment_ids'] ) && in_array( $item_id, array_map( 'strval', $scenario['segment_ids'] ), true ) ) { $linked_scenarios[] = $scenario; }
        }
        echo '<div class="acdc-panel" style="margin-bottom:18px">';
        echo '<div style="display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap;align-items:flex-start">';
        echo '<div><h2 style="margin:0 0 8px 0">Fiche segment</h2><p style="margin:0;color:#1E4777">Un segment = une règle de ciblage exploitable dans les campagnes et scénarios.</p></div>';
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap">';
        echo '<a class="acdc-button acdc-button-primary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab, 'action' => 'edit', 'item_id' => $item_id ) ) ) . '">Modifier</a>';
        echo '<a class="acdc-button acdc-button-secondary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab ) ) ) . '">Retour à la liste</a>';
        echo '</div></div></div>';
        echo '<div style="display:grid;grid-template-columns:1.1fr 0.9fr;gap:18px;margin-bottom:18px">';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Informations générales</h3><table class="acdc-table"><tbody>';
        echo '<tr><th>Nom</th><td>' . esc_html( isset( $item['name'] ) ? $item['name'] : '' ) . '</td></tr>';
        echo '<tr><th>Code technique</th><td>' . esc_html( isset( $item['code'] ) ? $item['code'] : '' ) . '</td></tr>';
        echo '<tr><th>Statut</th><td>' . esc_html( isset( $item['status'] ) ? $item['status'] : '' ) . '</td></tr>';
        echo '<tr><th>Type</th><td>' . ( ! empty( $item['is_system'] ) ? 'Système' : 'Utilisateur' ) . '</td></tr>';
        echo '<tr><th>Mode</th><td>' . esc_html( isset( $item['segment_mode'] ) ? $item['segment_mode'] : '' ) . '</td></tr>';
        echo '<tr><th>Type de contact</th><td>' . esc_html( isset( $item['contact_type'] ) ? $item['contact_type'] : '' ) . '</td></tr>';
        echo '<tr><th>Statut marketing</th><td>' . esc_html( isset( $item['status_filter'] ) ? $item['status_filter'] : '' ) . '</td></tr>';
        echo '<tr><th>Engagement</th><td>' . esc_html( isset( $item['engagement_filter'] ) ? $item['engagement_filter'] : '' ) . '</td></tr>';
        echo '<tr><th>Description</th><td>' . esc_html( isset( $item['description'] ) ? $item['description'] : '' ) . '</td></tr>';
        echo '<tr><th>Règles</th><td>' . esc_html( isset( $item['rules'] ) ? $item['rules'] : '' ) . '</td></tr>';
        echo '</tbody></table></div>';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Population estimée</h3>';
        echo '<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:12px">';
        echo '<div class="acdc-kpi-card"><strong>Contacts</strong><div style="font-size:28px;font-weight:700">' . esc_html( (string) count( $matched_contacts ) ) . '</div></div>';
        echo '<div class="acdc-kpi-card"><strong>Campagnes</strong><div style="font-size:28px;font-weight:700">' . esc_html( (string) count( $linked_campaigns ) ) . '</div></div>';
        echo '<div class="acdc-kpi-card"><strong>Scénarios</strong><div style="font-size:28px;font-weight:700">' . esc_html( (string) count( $linked_scenarios ) ) . '</div></div>';
        echo '</div><p style="margin:0;color:#1E4777">Aperçu recalculé localement à partir des critères métier du segment.</p></div>';
        echo '</div>';
        echo '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px">';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Échantillon de contacts</h3><table class="acdc-table"><thead><tr><th>Nom</th><th>Type</th><th>E-mail</th></tr></thead><tbody>';
        foreach ( array_slice( $matched_contacts, 0, 20 ) as $contact ) {
          echo '<tr><td>' . esc_html( isset( $contact['name'] ) ? $contact['name'] : '' ) . '</td><td>' . esc_html( isset( $contact['type'] ) ? $contact['type'] : '' ) . '</td><td>' . esc_html( isset( $contact['email'] ) ? $contact['email'] : '' ) . '</td></tr>';
        }
        if ( empty( $matched_contacts ) ) { echo '<tr><td colspan="3">Aucun contact ne correspond actuellement à ce segment.</td></tr>'; }
        echo '</tbody></table></div>';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Usages détectés</h3><table class="acdc-table"><thead><tr><th>Type</th><th>Nom</th><th>Statut</th></tr></thead><tbody>';
        $usage_rows = array();
        foreach ( $linked_campaigns as $campaign ) { $usage_rows[] = array( 'type' => 'Campagne', 'name' => isset( $campaign['name'] ) ? $campaign['name'] : '', 'status' => isset( $campaign['status'] ) ? $campaign['status'] : '' ); }
        foreach ( $linked_scenarios as $scenario ) { $usage_rows[] = array( 'type' => 'Scénario', 'name' => isset( $scenario['name'] ) ? $scenario['name'] : '', 'status' => isset( $scenario['status'] ) ? $scenario['status'] : '' ); }
        foreach ( array_slice( $usage_rows, 0, 20 ) as $row ) {
          echo '<tr><td>' . esc_html( $row['type'] ) . '</td><td>' . esc_html( $row['name'] ) . '</td><td>' . esc_html( $row['status'] ) . '</td></tr>';
        }
        if ( empty( $usage_rows ) ) { echo '<tr><td colspan="3">Aucun usage détecté pour ce segment.</td></tr>'; }
        echo '</tbody></table></div>';
        echo '</div>';
      }
    }

    echo '<div class="acdc-panel" style="margin-bottom:18px"><form method="get" class="acdc-filters" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;align-items:end">';
    echo '<input type="hidden" name="page" value="acdc-formation-saas">';
    echo '<input type="hidden" name="tab" value="marketing_segments">';
    echo '<p style="margin:0"><label>Recherche</label><input type="text" name="marketing_search" value="' . esc_attr( $search ) . '" placeholder="Nom ou description"></p>';
    echo '<p style="margin:0"><label>Statut</label><select name="marketing_status_filter"><option value="">Tous</option><option value="draft" ' . selected( $status_filter, 'draft', false ) . '>Brouillon</option><option value="active" ' . selected( $status_filter, 'active', false ) . '>Actif</option><option value="archived" ' . selected( $status_filter, 'archived', false ) . '>Archivé</option></select></p>';
    echo '<p style="margin:0"><label>Tri</label><select disabled><option>Nom croissant</option></select></p>';
    echo '<p style="margin:0"><button type="submit" class="acdc-button acdc-button-secondary">Filtrer</button></p>';
    echo '</form></div>';

    echo '<div class="acdc-panel"><table class="acdc-table"><thead><tr><th>Nom</th><th>Statut</th><th>Mode</th><th>Type</th><th>Population</th><th>Campagnes / scénarios</th><th>Mise à jour</th><th>Actions</th></tr></thead><tbody>';
    $printed = 0;
    foreach ( $items as $item ) {
      $haystack = strtolower( trim( (string) ( isset( $item['name'] ) ? $item['name'] : '' ) . ' ' . ( isset( $item['description'] ) ? $item['description'] : '' ) ) );
      if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) { continue; }
      if ( '' !== $status_filter && ( ! isset( $item['status'] ) || $item['status'] !== $status_filter ) ) { continue; }
      $id = isset( $item['id'] ) ? $item['id'] : '';
      $edit_url = $this->portal_page_url( array( 'tab' => $tab, 'action' => 'edit', 'item_id' => $id ) );
      $view_url = $this->portal_page_url( array( 'tab' => $tab, 'action' => 'view', 'item_id' => $id ) );
      $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'acdc_marketing_delete_entity', 'marketing_entity' => 'segments', 'item_id' => $id ), admin_url( 'admin-post.php' ) ), 'acdc_marketing_delete_entity' );
      $related_campaigns = 0;
      foreach ( $campaigns as $campaign ) { if ( ! empty( $campaign['segment_ids'] ) && is_array( $campaign['segment_ids'] ) && in_array( $id, array_map( 'strval', $campaign['segment_ids'] ), true ) ) { $related_campaigns++; } }
      $related_scenarios = 0;
      foreach ( $scenarios as $scenario ) { if ( ! empty( $scenario['segment_ids'] ) && is_array( $scenario['segment_ids'] ) && in_array( $id, array_map( 'strval', $scenario['segment_ids'] ), true ) ) { $related_scenarios++; } }
      echo '<tr>';
      echo '<td><strong>' . esc_html( isset( $item['name'] ) ? $item['name'] : '' ) . '</strong><br><small>' . esc_html( isset( $item['description'] ) ? $item['description'] : '' ) . '</small></td>';
      echo '<td>' . esc_html( isset( $item['status'] ) ? $item['status'] : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['segment_mode'] ) ? $item['segment_mode'] : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['contact_type'] ) ? $item['contact_type'] : 'tous' ) . '</td>';
      echo '<td>' . esc_html( (string) ( isset( $item['population_count'] ) ? $item['population_count'] : 0 ) ) . '</td>';
      echo '<td>' . esc_html( $related_campaigns . ' / ' . $related_scenarios ) . '</td>';
      echo '<td>' . esc_html( isset( $item['updated_at'] ) ? $item['updated_at'] : '' ) . '</td>';
      echo '<td><a class="acdc-button acdc-button-secondary" href="' . esc_url( $view_url ) . '">Voir</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $edit_url ) . '">Modifier</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'Confirmer la suppression ?\')">Supprimer</a></td>';
      echo '</tr>';
      $printed++;
    }
    if ( 0 === $printed ) { echo '<tr><td colspan="8">Aucun segment ne correspond aux critères actuels.</td></tr>'; }
    echo '</tbody></table></div>';
  }


  private function render_front_marketing_tags_tab() {
    $entity = 'tags';
    $tab = 'marketing_tags';
    $items = $this->get_marketing_store( $entity, array() );
    $contacts = $this->get_marketing_contacts_index();
    $campaigns = $this->get_marketing_store( 'campaigns', array() );
    $forms = $this->get_marketing_store( 'forms', array() );
    $scenarios = $this->get_marketing_store( 'scenarios', array() );
    $lists = $this->get_marketing_store( 'lists', array() );
    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
    $item_id = isset( $_GET['item_id'] ) ? sanitize_text_field( wp_unslash( $_GET['item_id'] ) ) : '';
    $search = isset( $_GET['marketing_search'] ) ? sanitize_text_field( wp_unslash( $_GET['marketing_search'] ) ) : '';
    $status_filter = isset( $_GET['marketing_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['marketing_status_filter'] ) ) : '';

    $counts = array();
    foreach ( $items as $item ) {
      if ( empty( $item['id'] ) ) {
        continue;
      }
      $counts[ $item['id'] ] = 0;
    }
    foreach ( $contacts as $contact ) {
      if ( empty( $contact['tags'] ) || ! is_array( $contact['tags'] ) ) {
        continue;
      }
      foreach ( $contact['tags'] as $tag_id ) {
        if ( isset( $counts[ $tag_id ] ) ) {
          $counts[ $tag_id ]++;
        }
      }
    }

    $active_tags = 0;
    $assigned_contacts = 0;
    foreach ( $items as $item ) {
      if ( isset( $item['status'] ) && 'active' === $item['status'] ) {
        $active_tags++;
      }
      if ( ! empty( $item['id'] ) && ! empty( $counts[ $item['id'] ] ) ) {
        $assigned_contacts += (int) $counts[ $item['id'] ];
      }
    }

    $this->render_marketing_header( 'Étiquettes', 'Organisez vos contacts et vos campagnes avec des étiquettes simples, réutilisables et pleinement reliées aux autres blocs métier du module.', array(
      array( 'label' => 'Créer une étiquette', 'url' => $this->portal_page_url( array( 'tab' => $tab, 'action' => 'new' ) ), 'primary' => true ),
      array( 'label' => 'Voir les contacts', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_contacts' ) ) ),
    ) );
    $this->render_marketing_cards( array(
      array( 'label' => 'Étiquettes créées', 'value' => count( $items ), 'help' => 'Référentiel de qualification marketing du module.' ),
      array( 'label' => 'Étiquettes actives', 'value' => $active_tags, 'help' => 'Disponibles immédiatement dans les fiches, formulaires, campagnes et scénarios.' ),
      array( 'label' => 'Contacts étiquetés', 'value' => $assigned_contacts, 'help' => 'Total des rattachements contact → étiquette.' ),
      array( 'label' => 'Usages détectés', 'value' => count( $forms ) . ' formulaires / ' . count( $campaigns ) . ' campagnes', 'help' => 'Repérage rapide des dépendances métier.' ),
    ) );

    if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
      $item = array();
      foreach ( $items as $candidate ) {
        if ( isset( $candidate['id'] ) && $candidate['id'] === $item_id ) {
          $item = $candidate;
          break;
        }
      }
      echo '<div class="acdc-panel" style="margin-bottom:18px"><form class="acdc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
      wp_nonce_field( 'acdc_marketing_save_entity' );
      echo '<input type="hidden" name="action" value="acdc_marketing_save_entity">';
      echo '<input type="hidden" name="marketing_entity" value="tags">';
      echo '<input type="hidden" name="marketing_item[id]" value="' . esc_attr( isset( $item['id'] ) ? $item['id'] : '' ) . '">';
      echo '<div class="acdc-marketing-segments-form-grid">';
      echo '<p><label>Nom de l’étiquette</label><input type="text" name="marketing_item[name]" value="' . esc_attr( isset( $item['name'] ) ? $item['name'] : '' ) . '" required></p>';
      echo '<p><label>Code technique</label><input type="text" name="marketing_item[code]" value="' . esc_attr( isset( $item['code'] ) ? $item['code'] : '' ) . '" placeholder="source-prospection-terrain"></p>';
      echo '<p><label>Statut</label><select name="marketing_item[status]"><option value="draft" ' . selected( isset( $item['status'] ) ? $item['status'] : 'draft', 'draft', false ) . '>Brouillon</option><option value="active" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'active', false ) . '>Active</option><option value="archived" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'archived', false ) . '>Archivée</option></select></p>';
      echo '<p><label>Famille</label><input type="text" name="marketing_item[family]" value="' . esc_attr( isset( $item['family'] ) ? $item['family'] : '' ) . '" placeholder="source"></p>';
      echo '<p><label>Usage principal</label><select name="marketing_item[category]"><option value="marketing" ' . selected( isset( $item['category'] ) ? $item['category'] : 'marketing', 'marketing', false ) . '>Marketing / prospection</option><option value="commercial" ' . selected( isset( $item['category'] ) ? $item['category'] : '', 'commercial', false ) . '>Commercial / devis</option><option value="formation" ' . selected( isset( $item['category'] ) ? $item['category'] : '', 'formation', false ) . '>Suivi de formation</option><option value="administratif" ' . selected( isset( $item['category'] ) ? $item['category'] : '', 'administratif', false ) . '>Administratif</option></select></p>';
      echo '<p><label>Couleur</label><input type="text" name="marketing_item[color]" value="' . esc_attr( isset( $item['color'] ) ? $item['color'] : '' ) . '" placeholder="#C5A253"></p>';
      echo '<p><label>Icône</label><input type="text" name="marketing_item[icon]" value="' . esc_attr( isset( $item['icon'] ) ? $item['icon'] : '' ) . '" placeholder="location"></p>';
      echo '<p><label>Ordre d’affichage</label><input type="number" name="marketing_item[sort_order]" value="' . esc_attr( isset( $item['sort_order'] ) ? $item['sort_order'] : 100 ) . '"></p>';
      echo '</div>';
      echo '<p><label>Description / usage métier</label><textarea name="marketing_item[description]" rows="4">' . esc_textarea( isset( $item['description'] ) ? $item['description'] : '' ) . '</textarea></p>';
      echo '<p><label>Règles d’usage</label><textarea name="marketing_item[rules]" rows="5">' . esc_textarea( isset( $item['rules'] ) ? $item['rules'] : '' ) . '</textarea></p>';
      echo '<p><button type="submit" class="acdc-button acdc-button-primary">Enregistrer l’étiquette</button> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab ) ) ) . '">Retour à la liste</a></p>';
      echo '</form></div>';
    }

    if ( 'view' === $action && ! empty( $item_id ) ) {
      $item = array();
      foreach ( $items as $candidate ) {
        if ( isset( $candidate['id'] ) && $candidate['id'] === $item_id ) {
          $item = $candidate;
          break;
        }
      }
      if ( ! empty( $item ) ) {
        $linked_contacts = array();
        foreach ( $contacts as $contact ) {
          if ( ! empty( $contact['tags'] ) && is_array( $contact['tags'] ) && in_array( $item_id, array_map( 'strval', $contact['tags'] ), true ) ) {
            $linked_contacts[] = $contact;
          }
        }
        $linked_campaigns = array();
        foreach ( $campaigns as $campaign ) {
          if ( ! empty( $campaign['tag_ids'] ) && is_array( $campaign['tag_ids'] ) && in_array( $item_id, array_map( 'strval', $campaign['tag_ids'] ), true ) ) {
            $linked_campaigns[] = $campaign;
          }
        }
        $linked_forms = array();
        foreach ( $forms as $form ) {
          if ( ! empty( $form['tag_ids'] ) && is_array( $form['tag_ids'] ) && in_array( $item_id, array_map( 'strval', $form['tag_ids'] ), true ) ) {
            $linked_forms[] = $form;
          }
        }
        $linked_scenarios = array();
        foreach ( $scenarios as $scenario ) {
          if ( ! empty( $scenario['tag_ids'] ) && is_array( $scenario['tag_ids'] ) && in_array( $item_id, array_map( 'strval', $scenario['tag_ids'] ), true ) ) {
            $linked_scenarios[] = $scenario;
          }
        }
        $linked_lists = array();
        foreach ( $lists as $list ) {
          if ( ! empty( $list['rules'] ) && false !== strpos( strtolower( (string) $list['rules'] ), strtolower( (string) ( isset( $item['name'] ) ? $item['name'] : '' ) ) ) ) {
            $linked_lists[] = $list;
          }
        }

        echo '<div class="acdc-panel" style="margin-bottom:18px">';
        echo '<div style="display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap;align-items:flex-start">';
        echo '<div><h2 style="margin:0 0 8px 0">Fiche étiquette</h2><p style="margin:0;color:#1E4777">Une étiquette = un marqueur métier simple et réutilisable pour qualifier les contacts et déclencher des actions ciblées.</p></div>';
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap">';
        echo '<a class="acdc-button acdc-button-primary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab, 'action' => 'edit', 'item_id' => $item_id ) ) ) . '">Modifier</a>';
        echo '<a class="acdc-button acdc-button-secondary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab ) ) ) . '">Retour à la liste</a>';
        echo '</div></div></div>';

        echo '<div style="display:grid;grid-template-columns:1.1fr 0.9fr;gap:18px;margin-bottom:18px">';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Informations générales</h3><table class="acdc-table"><tbody>';
        echo '<tr><th>Nom</th><td>' . esc_html( isset( $item['name'] ) ? $item['name'] : '' ) . '</td></tr>';
        echo '<tr><th>Code technique</th><td>' . esc_html( isset( $item['code'] ) ? $item['code'] : '' ) . '</td></tr>';
        echo '<tr><th>Statut</th><td>' . esc_html( isset( $item['status'] ) ? $item['status'] : '' ) . '</td></tr>';
        echo '<tr><th>Type</th><td>' . ( ! empty( $item['is_system'] ) ? 'Système' : 'Utilisateur' ) . '</td></tr>';
        echo '<tr><th>Usage principal</th><td>' . esc_html( isset( $item['category'] ) ? $item['category'] : '' ) . '</td></tr>';
        echo '<tr><th>Repère visuel</th><td>' . esc_html( isset( $item['subject'] ) ? $item['subject'] : '' ) . '</td></tr>';
        echo '<tr><th>Description</th><td>' . esc_html( isset( $item['description'] ) ? $item['description'] : '' ) . '</td></tr>';
        echo '<tr><th>Règles d’usage</th><td>' . esc_html( isset( $item['rules'] ) ? $item['rules'] : '' ) . '</td></tr>';
        echo '<tr><th>Mise à jour</th><td>' . esc_html( isset( $item['updated_at'] ) ? $item['updated_at'] : '' ) . '</td></tr>';
        echo '</tbody></table></div>';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Dépendances métier</h3><table class="acdc-table"><tbody>';
        echo '<tr><th>Contacts affectés</th><td>' . esc_html( (string) count( $linked_contacts ) ) . '</td></tr>';
        echo '<tr><th>Campagnes liées</th><td>' . esc_html( (string) count( $linked_campaigns ) ) . '</td></tr>';
        echo '<tr><th>Formulaires liés</th><td>' . esc_html( (string) count( $linked_forms ) ) . '</td></tr>';
        echo '<tr><th>Scénarios liés</th><td>' . esc_html( (string) count( $linked_scenarios ) ) . '</td></tr>';
        echo '<tr><th>Listes mentionnées</th><td>' . esc_html( (string) count( $linked_lists ) ) . '</td></tr>';
        echo '</tbody></table></div>';
        echo '</div>';

        echo '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px">';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Contacts étiquetés</h3><table class="acdc-table"><thead><tr><th>Nom</th><th>Type</th><th>E-mail</th></tr></thead><tbody>';
        foreach ( array_slice( $linked_contacts, 0, 20 ) as $contact ) {
          echo '<tr><td>' . esc_html( isset( $contact['name'] ) ? $contact['name'] : '' ) . '</td><td>' . esc_html( isset( $contact['type'] ) ? $contact['type'] : '' ) . '</td><td>' . esc_html( isset( $contact['email'] ) ? $contact['email'] : '' ) . '</td></tr>';
        }
        if ( empty( $linked_contacts ) ) {
          echo '<tr><td colspan="3">Aucun contact lié à cette étiquette.</td></tr>';
        }
        echo '</tbody></table></div>';

        echo '<div class="acdc-panel"><h3 style="margin-top:0">Usages détectés</h3><table class="acdc-table"><thead><tr><th>Type</th><th>Nom</th><th>Statut</th></tr></thead><tbody>';
        $usage_rows = array();
        foreach ( $linked_campaigns as $campaign ) {
          $usage_rows[] = array( 'type' => 'Campagne', 'name' => isset( $campaign['name'] ) ? $campaign['name'] : '', 'status' => isset( $campaign['status'] ) ? $campaign['status'] : '' );
        }
        foreach ( $linked_forms as $form ) {
          $usage_rows[] = array( 'type' => 'Formulaire', 'name' => isset( $form['name'] ) ? $form['name'] : '', 'status' => isset( $form['status'] ) ? $form['status'] : '' );
        }
        foreach ( $linked_scenarios as $scenario ) {
          $usage_rows[] = array( 'type' => 'Scénario', 'name' => isset( $scenario['name'] ) ? $scenario['name'] : '', 'status' => isset( $scenario['status'] ) ? $scenario['status'] : '' );
        }
        foreach ( $linked_lists as $list ) {
          $usage_rows[] = array( 'type' => 'Liste mentionnée', 'name' => isset( $list['name'] ) ? $list['name'] : '', 'status' => isset( $list['status'] ) ? $list['status'] : '' );
        }
        foreach ( array_slice( $usage_rows, 0, 20 ) as $row ) {
          echo '<tr><td>' . esc_html( $row['type'] ) . '</td><td>' . esc_html( $row['name'] ) . '</td><td>' . esc_html( $row['status'] ) . '</td></tr>';
        }
        if ( empty( $usage_rows ) ) {
          echo '<tr><td colspan="3">Aucun usage détecté pour cette étiquette.</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '</div>';
      }
    }

    echo '<div class="acdc-panel" style="margin-bottom:18px"><form method="get" class="acdc-filters" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;align-items:end">';
    echo '<input type="hidden" name="page" value="acdc-formation-saas">';
    echo '<input type="hidden" name="tab" value="marketing_tags">';
    echo '<p style="margin:0"><label>Recherche</label><input type="text" name="marketing_search" value="' . esc_attr( $search ) . '" placeholder="Nom ou description"></p>';
    echo '<p style="margin:0"><label>Statut</label><select name="marketing_status_filter"><option value="">Tous</option><option value="draft" ' . selected( $status_filter, 'draft', false ) . '>Brouillon</option><option value="active" ' . selected( $status_filter, 'active', false ) . '>Active</option><option value="archived" ' . selected( $status_filter, 'archived', false ) . '>Archivée</option></select></p>';
    echo '<p style="margin:0"><label>Tri</label><select disabled><option>Nom croissant</option></select></p>';
    echo '<p style="margin:0"><button type="submit" class="acdc-button acdc-button-secondary">Filtrer</button></p>';
    echo '</form></div>';

    echo '<div class="acdc-panel"><table class="acdc-table"><thead><tr><th>Nom</th><th>Statut</th><th>Usage</th><th>Contacts</th><th>Formulaires / campagnes</th><th>Mise à jour</th><th>Actions</th></tr></thead><tbody>';
    $printed = 0;
    foreach ( $items as $item ) {
      $haystack = strtolower( trim( (string) ( isset( $item['name'] ) ? $item['name'] : '' ) . ' ' . ( isset( $item['description'] ) ? $item['description'] : '' ) ) );
      if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) {
        continue;
      }
      if ( '' !== $status_filter && ( ! isset( $item['status'] ) || $item['status'] !== $status_filter ) ) {
        continue;
      }
      $id = isset( $item['id'] ) ? $item['id'] : '';
      $edit_url = $this->portal_page_url( array( 'tab' => $tab, 'action' => 'edit', 'item_id' => $id ) );
      $view_url = $this->portal_page_url( array( 'tab' => $tab, 'action' => 'view', 'item_id' => $id ) );
      $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'acdc_marketing_delete_entity', 'marketing_entity' => 'tags', 'item_id' => $id ), admin_url( 'admin-post.php' ) ), 'acdc_marketing_delete_entity' );
      $related_forms = 0;
      foreach ( $forms as $form ) {
        if ( ! empty( $form['tag_ids'] ) && is_array( $form['tag_ids'] ) && in_array( $id, array_map( 'strval', $form['tag_ids'] ), true ) ) {
          $related_forms++;
        }
      }
      $related_campaigns = 0;
      foreach ( $campaigns as $campaign ) {
        if ( ! empty( $campaign['tag_ids'] ) && is_array( $campaign['tag_ids'] ) && in_array( $id, array_map( 'strval', $campaign['tag_ids'] ), true ) ) {
          $related_campaigns++;
        }
      }
      echo '<tr>';
      echo '<td><strong>' . esc_html( isset( $item['name'] ) ? $item['name'] : '' ) . '</strong><br><small>' . esc_html( isset( $item['description'] ) ? $item['description'] : '' ) . '</small></td>';
      echo '<td>' . esc_html( isset( $item['status'] ) ? $item['status'] : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['category'] ) ? $item['category'] : '' ) . '</td>';
      echo '<td>' . esc_html( (string) ( isset( $counts[ $id ] ) ? $counts[ $id ] : 0 ) ) . '</td>';
      echo '<td>' . esc_html( $related_forms . ' / ' . $related_campaigns ) . '</td>';
      echo '<td>' . esc_html( isset( $item['updated_at'] ) ? $item['updated_at'] : '' ) . '</td>';
      echo '<td><a class="acdc-button acdc-button-secondary" href="' . esc_url( $view_url ) . '">Voir</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $edit_url ) . '">Modifier</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'Confirmer la suppression ?\')">Supprimer</a></td>';
      echo '</tr>';
      $printed++;
    }
    if ( 0 === $printed ) {
      echo '<tr><td colspan="7">Aucune étiquette ne correspond aux critères actuels.</td></tr>';
    }
    echo '</tbody></table></div>';
  }


  private function render_front_marketing_campaigns_tab() {
    $entity = 'campaigns';
    $tab = 'marketing_campaigns';
    $items = $this->get_marketing_store( $entity, array() );
    $lists = $this->get_marketing_store( 'lists', array() );
    $tags = $this->get_marketing_store( 'tags', array() );
    $segments = $this->get_marketing_store( 'segments', array() );
    $contacts = $this->get_marketing_contacts_index();
    $templates = $this->get_marketing_store( 'templates', array() );
    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
    $item_id = isset( $_GET['item_id'] ) ? sanitize_text_field( wp_unslash( $_GET['item_id'] ) ) : '';
    $search = isset( $_GET['marketing_search'] ) ? sanitize_text_field( wp_unslash( $_GET['marketing_search'] ) ) : '';
    $status_filter = isset( $_GET['marketing_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['marketing_status_filter'] ) ) : '';

    $segment_match = function( $segment, $contact ) {
      if ( ! empty( $segment['contact_type'] ) && ( empty( $contact['type'] ) || $segment['contact_type'] !== $contact['type'] ) ) {
        return false;
      }
      if ( ! empty( $segment['enabled_only'] ) && empty( $contact['enabled'] ) ) {
        return false;
      }
      if ( ! empty( $segment['status_filter'] ) ) {
        $statuses = isset( $contact['statuses'] ) && is_array( $contact['statuses'] ) ? $contact['statuses'] : array();
        if ( ! in_array( $segment['status_filter'], $statuses, true ) ) {
          return false;
        }
      }
      if ( ! empty( $segment['list_ids'] ) && is_array( $segment['list_ids'] ) ) {
        $contact_lists = isset( $contact['lists'] ) && is_array( $contact['lists'] ) ? array_map( 'strval', $contact['lists'] ) : array();
        if ( 0 === count( array_intersect( array_map( 'strval', $segment['list_ids'] ), $contact_lists ) ) ) {
          return false;
        }
      }
      if ( ! empty( $segment['tag_ids'] ) && is_array( $segment['tag_ids'] ) ) {
        $contact_tags = isset( $contact['tags'] ) && is_array( $contact['tags'] ) ? array_map( 'strval', $contact['tags'] ) : array();
        if ( 0 === count( array_intersect( array_map( 'strval', $segment['tag_ids'] ), $contact_tags ) ) ) {
          return false;
        }
      }
      return true;
    };

    $estimate_campaign_population = function( $campaign ) use ( $contacts, $segment_match, $segments ) {
      $matched = array();
      $segment_ids = ! empty( $campaign['segment_ids'] ) && is_array( $campaign['segment_ids'] ) ? array_map( 'strval', $campaign['segment_ids'] ) : array();
      $list_ids = ! empty( $campaign['list_ids'] ) && is_array( $campaign['list_ids'] ) ? array_map( 'strval', $campaign['list_ids'] ) : array();
      $tag_ids = ! empty( $campaign['tag_ids'] ) && is_array( $campaign['tag_ids'] ) ? array_map( 'strval', $campaign['tag_ids'] ) : array();
      foreach ( $contacts as $contact ) {
        if ( ! empty( $campaign['contact_type'] ) && ( empty( $contact['type'] ) || $campaign['contact_type'] !== $contact['type'] ) ) {
          continue;
        }
        if ( ! empty( $campaign['enabled_only'] ) && empty( $contact['enabled'] ) ) {
          continue;
        }
        $keep = empty( $segment_ids ) && empty( $list_ids ) && empty( $tag_ids );
        if ( ! $keep && ! empty( $list_ids ) ) {
          $contact_lists = isset( $contact['lists'] ) && is_array( $contact['lists'] ) ? array_map( 'strval', $contact['lists'] ) : array();
          $keep = 0 < count( array_intersect( $list_ids, $contact_lists ) );
        }
        if ( ! $keep && ! empty( $tag_ids ) ) {
          $contact_tags = isset( $contact['tags'] ) && is_array( $contact['tags'] ) ? array_map( 'strval', $contact['tags'] ) : array();
          $keep = 0 < count( array_intersect( $tag_ids, $contact_tags ) );
        }
        if ( ! $keep && ! empty( $segment_ids ) ) {
          foreach ( $segments as $segment ) {
            if ( empty( $segment['id'] ) || ! in_array( (string) $segment['id'], $segment_ids, true ) ) { continue; }
            if ( $segment_match( $segment, $contact ) ) { $keep = true; break; }
          }
        }
        if ( $keep && ! empty( $contact['email'] ) ) {
          $matched[ $contact['source_key'] ] = $contact;
        }
      }
      return array_values( $matched );
    };

    $planned = 0; $active = 0; $population_cumulated = 0;
    foreach ( $items as &$item ) {
      $matched = $estimate_campaign_population( $item );
      $item['population_count'] = count( $matched );
      $population_cumulated += $item['population_count'];
      if ( isset( $item['status'] ) && 'planned' === $item['status'] ) { $planned++; }
      if ( isset( $item['status'] ) && in_array( $item['status'], array( 'active', 'planned' ), true ) ) { $active++; }
    }
    unset( $item );

    $this->render_marketing_header( 'Campagnes', 'Préparez vos campagnes métier avec ciblage, contenu, planification, file d’attente et contrôle des destinataires.', array(
      array( 'label' => 'Créer une campagne', 'url' => $this->portal_page_url( array( 'tab' => $tab, 'action' => 'new' ) ), 'primary' => true ),
      array( 'label' => 'Voir les contacts', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_contacts' ) ) ),
    ) );
    $this->render_marketing_cards( array(
      array( 'label' => 'Campagnes créées', 'value' => count( $items ), 'help' => 'Référentiel global des campagnes du module.' ),
      array( 'label' => 'Campagnes actives', 'value' => $active, 'help' => 'Brouillons finalisés, planifiées ou en cours.' ),
      array( 'label' => 'Planifiées', 'value' => $planned, 'help' => 'Campagnes alimentant la file d’attente d’envoi.' ),
      array( 'label' => 'Population cumulée', 'value' => $population_cumulated, 'help' => 'Volume théorique de contacts couverts par vos ciblages.' ),
    ) );

    if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
      $item = array();
      foreach ( $items as $candidate ) { if ( isset( $candidate['id'] ) && $candidate['id'] === $item_id ) { $item = $candidate; break; } }
      $matched_contacts = $estimate_campaign_population( $item );
      echo '<div class="acdc-panel" style="margin-bottom:18px"><form class="acdc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
      wp_nonce_field( 'acdc_marketing_save_entity' );
      echo '<input type="hidden" name="action" value="acdc_marketing_save_entity">';
      echo '<input type="hidden" name="marketing_entity" value="campaigns">';
      echo '<input type="hidden" name="marketing_item[id]" value="' . esc_attr( isset( $item['id'] ) ? $item['id'] : '' ) . '">';
      echo '<div class="acdc-marketing-segments-form-grid">';
      echo '<p><label>Nom de la campagne</label><input type="text" name="marketing_item[name]" value="' . esc_attr( isset( $item['name'] ) ? $item['name'] : '' ) . '" required></p>';
      echo '<p><label>Statut</label><select name="marketing_item[status]"><option value="draft" ' . selected( isset( $item['status'] ) ? $item['status'] : 'draft', 'draft', false ) . '>Brouillon</option><option value="planned" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'planned', false ) . '>Planifiée</option><option value="active" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'active', false ) . '>Active</option><option value="archived" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'archived', false ) . '>Archivée</option></select></p>';
      echo '<p><label>Type de contact</label><select name="marketing_item[contact_type]"><option value="">Tous les types</option>';
      foreach ( $this->marketing_type_labels() as $type_key => $type_label ) {
        echo '<option value="' . esc_attr( $type_key ) . '" ' . selected( isset( $item['contact_type'] ) ? $item['contact_type'] : '', $type_key, false ) . '>' . esc_html( $type_label ) . '</option>';
      }
      echo '</select></p>';
      echo '<p><label>Catégorie d’e-mail</label><select name="marketing_item[category]"><option value="marketing" ' . selected( isset( $item['category'] ) ? $item['category'] : 'marketing', 'marketing', false ) . '>Marketing / prospection</option><option value="commercial" ' . selected( isset( $item['category'] ) ? $item['category'] : '', 'commercial', false ) . '>Informations commerciales liées à un devis ou une offre</option><option value="formation" ' . selected( isset( $item['category'] ) ? $item['category'] : '', 'formation', false ) . '>Suivi de formation / accompagnement</option><option value="administratif" ' . selected( isset( $item['category'] ) ? $item['category'] : '', 'administratif', false ) . '>Informations administratives</option><option value="financeur" ' . selected( isset( $item['category'] ) ? $item['category'] : '', 'financeur', false ) . '>Relances financeurs</option><option value="internal" ' . selected( isset( $item['category'] ) ? $item['category'] : '', 'internal', false ) . '>Notifications internes</option></select></p>';
      echo '<p><label>Objet</label><input type="text" name="marketing_item[subject]" value="' . esc_attr( isset( $item['subject'] ) ? $item['subject'] : '' ) . '"></p>';
      echo '<p><label>Modèle d’e-mail</label><select name="marketing_item[trigger_type]"><option value="">Aucun modèle lié</option>';
      foreach ( $templates as $template ) { if ( empty( $template['id'] ) ) { continue; } echo '<option value="' . esc_attr( $template['id'] ) . '" ' . selected( isset( $item['trigger_type'] ) ? $item['trigger_type'] : '', $template['id'], false ) . '>' . esc_html( isset( $template['name'] ) ? $template['name'] : $template['id'] ) . '</option>'; }
      echo '</select></p>';
      echo '<p><label>Planification</label><input type="datetime-local" name="marketing_item[planned_at]" value="' . esc_attr( isset( $item['planned_at'] ) ? str_replace( ' ', 'T', substr( $item['planned_at'], 0, 16 ) ) : '' ) . '"></p>';
      echo '<style>.acdc-marketing-campaign-choice-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:10px}.acdc-marketing-campaign-choice{display:block;margin:0;cursor:pointer}.acdc-marketing-campaign-choice input{position:absolute;opacity:0;pointer-events:none}.acdc-marketing-campaign-choice-card{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border:1px solid #D8E0EF;border-radius:12px;background:#fff;min-height:74px;transition:border-color .15s ease,box-shadow .15s ease,background-color .15s ease}.acdc-marketing-campaign-choice:hover .acdc-marketing-campaign-choice-card{border-color:#C5A253;box-shadow:0 4px 14px rgba(11,7,6,.08)}.acdc-marketing-campaign-choice input:checked + .acdc-marketing-campaign-choice-card{border-color:#C5A253;background:#FBF6E8;box-shadow:0 4px 14px rgba(197,162,83,.18)}.acdc-marketing-campaign-choice-check{width:18px;height:18px;border:2px solid #C5A253;border-radius:6px;flex:0 0 18px;margin-top:2px;background:#fff}.acdc-marketing-campaign-choice input:checked + .acdc-marketing-campaign-choice-card .acdc-marketing-campaign-choice-check{background:#C5A253;box-shadow:inset 0 0 0 3px #fff}.acdc-marketing-campaign-choice-title{font-weight:700;color:#17325C;line-height:1.25}.acdc-marketing-campaign-choice-meta{margin-top:4px;font-size:12px;line-height:1.35;color:#5F749A}.acdc-marketing-campaign-toggle{margin-top:10px}.acdc-marketing-campaign-toggle .acdc-marketing-campaign-choice-card{min-height:auto;align-items:center}.acdc-marketing-campaign-section{margin-top:18px;padding-top:14px;border-top:1px solid #E2E8F3}.acdc-marketing-campaign-section h3{margin:0 0 6px 0;font-size:15px;color:#17325C}.acdc-marketing-campaign-section p.acdc-marketing-campaign-help{margin:0 0 10px 0;font-size:12px;color:#5F749A}</style>';
      echo '<div class="acdc-marketing-campaign-toggle">';
      echo '<label class="acdc-marketing-campaign-choice">';
      echo '<input type="checkbox" name="marketing_item[enabled_only]" value="1" ' . checked( ! empty( $item['enabled_only'] ), true, false ) . '>';
      echo '<span class="acdc-marketing-campaign-choice-card"><span class="acdc-marketing-campaign-choice-check"></span><span><span class="acdc-marketing-campaign-choice-title">Contacts marketing activés uniquement</span><span class="acdc-marketing-campaign-choice-meta">Ne retient que les fiches déjà activées pour la communication marketing.</span></span></span>';
      echo '</label>';
      echo '</div>';
      echo '</div>';
      echo '<div class="acdc-marketing-campaign-section"><h3>Listes ciblées</h3><p class="acdc-marketing-campaign-help">Choisissez une ou plusieurs listes métier à inclure dans le ciblage.</p><div class="acdc-marketing-campaign-choice-grid">';
      $current_lists = isset( $item['list_ids'] ) && is_array( $item['list_ids'] ) ? array_map( 'strval', $item['list_ids'] ) : array();
      foreach ( $lists as $list ) {
        if ( empty( $list['id'] ) ) { continue; }
        $list_name = isset( $list['name'] ) ? $list['name'] : $list['id'];
        $list_meta = array();
        if ( ! empty( $list['usage'] ) ) { $list_meta[] = $list['usage']; }
        if ( ! empty( $list['source_hint'] ) ) { $list_meta[] = $list['source_hint']; }
        echo '<label class="acdc-marketing-campaign-choice">';
        echo '<input type="checkbox" name="marketing_item[list_ids][]" value="' . esc_attr( $list['id'] ) . '" ' . checked( in_array( (string) $list['id'], $current_lists, true ), true, false ) . '>';
        echo '<span class="acdc-marketing-campaign-choice-card"><span class="acdc-marketing-campaign-choice-check"></span><span><span class="acdc-marketing-campaign-choice-title">' . esc_html( $list_name ) . '</span>';
        if ( ! empty( $list_meta ) ) { echo '<span class="acdc-marketing-campaign-choice-meta">' . esc_html( implode( ' • ', array_filter( $list_meta ) ) ) . '</span>'; }
        echo '</span></span></label>';
      }
      if ( empty( $lists ) ) { echo '<span style="color:#1E4777">Aucune liste disponible.</span>'; }
      echo '</div></div>';
      echo '<div class="acdc-marketing-campaign-section"><h3>Étiquettes ciblées</h3><p class="acdc-marketing-campaign-help">Utilisez les étiquettes pour qualifier plus finement votre cible.</p><div class="acdc-marketing-campaign-choice-grid">';
      $current_tags = isset( $item['tag_ids'] ) && is_array( $item['tag_ids'] ) ? array_map( 'strval', $item['tag_ids'] ) : array();
      foreach ( $tags as $tag ) {
        if ( empty( $tag['id'] ) ) { continue; }
        $tag_name = isset( $tag['name'] ) ? $tag['name'] : $tag['id'];
        $tag_title = $tag_name;
        $tag_meta = '';
        if ( false !== strpos( $tag_name, ':' ) ) { $parts = explode( ':', $tag_name, 2 ); $tag_title = trim( $parts[0] ) . ' : ' . trim( $parts[1] ); }
        if ( ! empty( $tag['usage'] ) ) { $tag_meta = $tag['usage']; }
        echo '<label class="acdc-marketing-campaign-choice">';
        echo '<input type="checkbox" name="marketing_item[tag_ids][]" value="' . esc_attr( $tag['id'] ) . '" ' . checked( in_array( (string) $tag['id'], $current_tags, true ), true, false ) . '>';
        echo '<span class="acdc-marketing-campaign-choice-card"><span class="acdc-marketing-campaign-choice-check"></span><span><span class="acdc-marketing-campaign-choice-title">' . esc_html( $tag_title ) . '</span>';
        if ( ! empty( $tag_meta ) ) { echo '<span class="acdc-marketing-campaign-choice-meta">' . esc_html( $tag_meta ) . '</span>'; }
        echo '</span></span></label>';
      }
      if ( empty( $tags ) ) { echo '<span style="color:#1E4777">Aucune étiquette disponible.</span>'; }
      echo '</div></div>';
      echo '<div class="acdc-marketing-campaign-section"><h3>Segments ciblés</h3><p class="acdc-marketing-campaign-help">Ajoutez des segments existants pour croiser votre ciblage avec les listes et étiquettes.</p><div class="acdc-marketing-campaign-choice-grid">';
      $current_segments = isset( $item['segment_ids'] ) && is_array( $item['segment_ids'] ) ? array_map( 'strval', $item['segment_ids'] ) : array();
      foreach ( $segments as $segment ) {
        if ( empty( $segment['id'] ) ) { continue; }
        $segment_name = isset( $segment['name'] ) ? $segment['name'] : $segment['id'];
        $segment_meta = array();
        if ( ! empty( $segment['segment_mode'] ) ) { $segment_meta[] = 'dynamic' === $segment['segment_mode'] ? 'Segment dynamique' : 'Segment figé'; }
        if ( isset( $segment['population'] ) && '' !== $segment['population'] ) { $segment_meta[] = 'Population : ' . (string) $segment['population']; }
        echo '<label class="acdc-marketing-campaign-choice">';
        echo '<input type="checkbox" name="marketing_item[segment_ids][]" value="' . esc_attr( $segment['id'] ) . '" ' . checked( in_array( (string) $segment['id'], $current_segments, true ), true, false ) . '>';
        echo '<span class="acdc-marketing-campaign-choice-card"><span class="acdc-marketing-campaign-choice-check"></span><span><span class="acdc-marketing-campaign-choice-title">' . esc_html( $segment_name ) . '</span>';
        if ( ! empty( $segment_meta ) ) { echo '<span class="acdc-marketing-campaign-choice-meta">' . esc_html( implode( ' • ', array_filter( $segment_meta ) ) ) . '</span>'; }
        echo '</span></span></label>';
      }
      if ( empty( $segments ) ) { echo '<span style="color:#1E4777">Aucun segment disponible.</span>'; }
      echo '</div></div>';
      echo '<p><label>Contenu</label><textarea name="marketing_item[content]" rows="8">' . esc_textarea( isset( $item['content'] ) ? wp_strip_all_tags( $item['content'] ) : '' ) . '</textarea></p>';
      echo '<p><label>Note métier</label><textarea name="marketing_item[rules]" rows="4">' . esc_textarea( isset( $item['rules'] ) ? $item['rules'] : '' ) . '</textarea></p>';
      echo '<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:0 0 18px 0">';
      echo '<div class="acdc-kpi-card"><strong>Destinataires estimés</strong><div style="font-size:28px;font-weight:700">' . esc_html( (string) count( $matched_contacts ) ) . '</div><small>Aperçu calculé à partir des sélections.</small></div>';
      echo '<div class="acdc-kpi-card"><strong>Modèle relié</strong><div style="font-size:28px;font-weight:700">' . esc_html( isset( $item['trigger_type'] ) && $item['trigger_type'] ? '1' : '0' ) . '</div><small>Le modèle reste optionnel en version actuelle.</small></div>';
      echo '<div class="acdc-kpi-card"><strong>File d’attente</strong><div style="font-size:28px;font-weight:700">' . esc_html( isset( $item['status'] ) && 'planned' === $item['status'] ? 'Planifiée' : 'Brouillon' ) . '</div><small>Planifiée = entrée automatique dans la file d’attente.</small></div>';
      echo '</div>';
      echo '<p><button type="submit" class="acdc-button acdc-button-primary">Enregistrer la campagne</button> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab ) ) ) . '">Retour à la liste</a></p>';
      echo '</form></div>';
    }

    if ( 'view' === $action && ! empty( $item_id ) ) {
      $item = array(); foreach ( $items as $candidate ) { if ( isset( $candidate['id'] ) && $candidate['id'] === $item_id ) { $item = $candidate; break; } }
      if ( ! empty( $item ) ) {
        $matched_contacts = $estimate_campaign_population( $item );
        echo '<div class="acdc-panel" style="margin-bottom:18px"><div style="display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap;align-items:flex-start"><div><h2 style="margin:0 0 8px 0">Fiche campagne</h2><p style="margin:0;color:#1E4777">Vue d’ensemble de la campagne, de son ciblage et de son contenu.</p></div><div style="display:flex;gap:10px;flex-wrap:wrap"><a class="acdc-button acdc-button-primary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab, 'action' => 'edit', 'item_id' => $item_id ) ) ) . '">Modifier</a><a class="acdc-button acdc-button-secondary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab ) ) ) . '">Retour à la liste</a></div></div></div>';
        echo '<div style="display:grid;grid-template-columns:1.1fr 0.9fr;gap:18px;margin-bottom:18px">';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Informations générales</h3><table class="acdc-table"><tbody>';
        echo '<tr><th>Nom</th><td>' . esc_html( isset( $item['name'] ) ? $item['name'] : '' ) . '</td></tr>';
        echo '<tr><th>Code technique</th><td>' . esc_html( isset( $item['code'] ) ? $item['code'] : '' ) . '</td></tr>';
        echo '<tr><th>Statut</th><td>' . esc_html( isset( $item['status'] ) ? $item['status'] : '' ) . '</td></tr>';
        echo '<tr><th>Type</th><td>' . ( ! empty( $item['is_system'] ) ? 'Système' : 'Utilisateur' ) . '</td></tr>';
        echo '<tr><th>Catégorie</th><td>' . esc_html( isset( $item['category'] ) ? $item['category'] : '' ) . '</td></tr>';
        echo '<tr><th>Type de contact</th><td>' . esc_html( isset( $item['contact_type'] ) ? $item['contact_type'] : 'tous' ) . '</td></tr>';
        echo '<tr><th>Objet</th><td>' . esc_html( isset( $item['subject'] ) ? $item['subject'] : '' ) . '</td></tr>';
        echo '<tr><th>Planification</th><td>' . esc_html( isset( $item['planned_at'] ) ? $item['planned_at'] : '' ) . '</td></tr>';
        echo '<tr><th>Note métier</th><td>' . esc_html( isset( $item['rules'] ) ? $item['rules'] : '' ) . '</td></tr>';
        echo '</tbody></table></div>';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Ciblage estimé</h3><div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:12px"><div class="acdc-kpi-card"><strong>Destinataires</strong><div style="font-size:28px;font-weight:700">' . esc_html( (string) count( $matched_contacts ) ) . '</div></div><div class="acdc-kpi-card"><strong>Listes</strong><div style="font-size:28px;font-weight:700">' . esc_html( (string) count( isset( $item['list_ids'] ) && is_array( $item['list_ids'] ) ? $item['list_ids'] : array() ) ) . '</div></div><div class="acdc-kpi-card"><strong>Segments</strong><div style="font-size:28px;font-weight:700">' . esc_html( (string) count( isset( $item['segment_ids'] ) && is_array( $item['segment_ids'] ) ? $item['segment_ids'] : array() ) ) . '</div></div></div><p style="margin:0;color:#1E4777">Estimation locale à partir des listes, étiquettes, segments et du type de contact.</p></div>';
        echo '</div>';
        echo '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px">';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Échantillon de destinataires</h3><table class="acdc-table"><thead><tr><th>Nom</th><th>Type</th><th>E-mail</th></tr></thead><tbody>';
        foreach ( array_slice( $matched_contacts, 0, 20 ) as $contact ) { echo '<tr><td>' . esc_html( isset( $contact['name'] ) ? $contact['name'] : '' ) . '</td><td>' . esc_html( isset( $contact['type'] ) ? $contact['type'] : '' ) . '</td><td>' . esc_html( isset( $contact['email'] ) ? $contact['email'] : '' ) . '</td></tr>'; }
        if ( empty( $matched_contacts ) ) { echo '<tr><td colspan="3">Aucun destinataire estimé pour cette campagne.</td></tr>'; }
        echo '</tbody></table></div>';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Contenu</h3><div style="white-space:pre-wrap;color:#1E4777">' . esc_html( isset( $item['content'] ) ? wp_strip_all_tags( $item['content'] ) : '' ) . '</div></div>';
        echo '</div>';
      }
    }

    echo '<div class="acdc-panel" style="margin-bottom:18px"><form method="get" class="acdc-filters" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;align-items:end">';
    echo '<input type="hidden" name="page" value="acdc-formation-saas">';
    echo '<input type="hidden" name="tab" value="marketing_campaigns">';
    echo '<p style="margin:0"><label>Recherche</label><input type="text" name="marketing_search" value="' . esc_attr( $search ) . '" placeholder="Nom ou objet"></p>';
    echo '<p style="margin:0"><label>Statut</label><select name="marketing_status_filter"><option value="">Tous</option><option value="draft" ' . selected( $status_filter, 'draft', false ) . '>Brouillon</option><option value="planned" ' . selected( $status_filter, 'planned', false ) . '>Planifiée</option><option value="active" ' . selected( $status_filter, 'active', false ) . '>Active</option><option value="archived" ' . selected( $status_filter, 'archived', false ) . '>Archivée</option></select></p>';
      echo '<p style="margin:0"><label>Tri</label><select disabled><option>Nom croissant</option></select></p>';
      echo '<p style="margin:0"><button type="submit" class="acdc-button acdc-button-secondary">Filtrer</button></p>';
      echo '</form></div>';

      echo '<div class="acdc-panel"><table class="acdc-table"><thead><tr><th>Nom</th><th>Statut</th><th>Catégorie</th><th>Destinataires</th><th>Planification</th><th>Mise à jour</th><th>Actions</th></tr></thead><tbody>';
      $printed = 0;
      foreach ( $items as $item ) {
        $haystack = strtolower( trim( (string) ( isset( $item['name'] ) ? $item['name'] : '' ) . ' ' . ( isset( $item['subject'] ) ? $item['subject'] : '' ) ) );
        if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) { continue; }
        if ( '' !== $status_filter && ( ! isset( $item['status'] ) || $item['status'] !== $status_filter ) ) { continue; }
        $id = isset( $item['id'] ) ? $item['id'] : '';
        $edit_url = $this->portal_page_url( array( 'tab' => $tab, 'action' => 'edit', 'item_id' => $id ) );
        $view_url = $this->portal_page_url( array( 'tab' => $tab, 'action' => 'view', 'item_id' => $id ) );
        $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'acdc_marketing_delete_entity', 'marketing_entity' => 'campaigns', 'item_id' => $id ), admin_url( 'admin-post.php' ) ), 'acdc_marketing_delete_entity' );
        echo '<tr>';
        echo '<td><strong>' . esc_html( isset( $item['name'] ) ? $item['name'] : '' ) . '</strong><br><small>' . esc_html( isset( $item['subject'] ) ? $item['subject'] : '' ) . '</small></td>';
        echo '<td>' . esc_html( isset( $item['status'] ) ? $item['status'] : '' ) . '</td>';
        echo '<td>' . esc_html( isset( $item['category'] ) ? $item['category'] : '' ) . '</td>';
        echo '<td>' . esc_html( (string) ( isset( $item['population_count'] ) ? $item['population_count'] : 0 ) ) . '</td>';
        echo '<td>' . esc_html( isset( $item['planned_at'] ) ? $item['planned_at'] : '' ) . '</td>';
        echo '<td>' . esc_html( isset( $item['updated_at'] ) ? $item['updated_at'] : '' ) . '</td>';
        echo '<td><a class="acdc-button acdc-button-secondary" href="' . esc_url( $view_url ) . '">Voir</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $edit_url ) . '">Modifier</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'Confirmer la suppression ?\')">Supprimer</a></td>';
        echo '</tr>';
        $printed++;
      }
      if ( 0 === $printed ) { echo '<tr><td colspan="7">Aucune campagne ne correspond aux critères actuels.</td></tr>'; }
      echo '</tbody></table></div>';
  }



  private function render_front_marketing_scenarios_tab() {
    $entity = 'scenarios';
    $tab = 'marketing_scenarios';
    $items = $this->get_marketing_store( $entity, array() );
    $lists = $this->get_marketing_store( 'lists', array() );
    $tags = $this->get_marketing_store( 'tags', array() );
    $segments = $this->get_marketing_store( 'segments', array() );
    $contacts = $this->get_marketing_contacts_index();
    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
    $item_id = isset( $_GET['item_id'] ) ? sanitize_text_field( wp_unslash( $_GET['item_id'] ) ) : '';
    $search = isset( $_GET['marketing_search'] ) ? sanitize_text_field( wp_unslash( $_GET['marketing_search'] ) ) : '';
    $status_filter = isset( $_GET['marketing_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['marketing_status_filter'] ) ) : '';

    $segment_match = function( $segment, $contact ) {
      if ( ! empty( $segment['contact_type'] ) && ( empty( $contact['type'] ) || $segment['contact_type'] !== $contact['type'] ) ) {
        return false;
      }
      if ( ! empty( $segment['enabled_only'] ) && empty( $contact['enabled'] ) ) {
        return false;
      }
      if ( ! empty( $segment['status_filter'] ) ) {
        $statuses = isset( $contact['statuses'] ) && is_array( $contact['statuses'] ) ? $contact['statuses'] : array();
        if ( ! in_array( $segment['status_filter'], $statuses, true ) ) {
          return false;
        }
      }
      if ( ! empty( $segment['list_ids'] ) && is_array( $segment['list_ids'] ) ) {
        $contact_lists = isset( $contact['lists'] ) && is_array( $contact['lists'] ) ? array_map( 'strval', $contact['lists'] ) : array();
        if ( 0 === count( array_intersect( array_map( 'strval', $segment['list_ids'] ), $contact_lists ) ) ) {
          return false;
        }
      }
      if ( ! empty( $segment['tag_ids'] ) && is_array( $segment['tag_ids'] ) ) {
        $contact_tags = isset( $contact['tags'] ) && is_array( $contact['tags'] ) ? array_map( 'strval', $contact['tags'] ) : array();
        if ( 0 === count( array_intersect( array_map( 'strval', $segment['tag_ids'] ), $contact_tags ) ) ) {
          return false;
        }
      }
      return true;
    };

    $estimate_scenario_population = function( $scenario ) use ( $contacts, $segment_match, $segments ) {
      $matched = array();
      $segment_ids = ! empty( $scenario['segment_ids'] ) && is_array( $scenario['segment_ids'] ) ? array_map( 'strval', $scenario['segment_ids'] ) : array();
      $list_ids = ! empty( $scenario['list_ids'] ) && is_array( $scenario['list_ids'] ) ? array_map( 'strval', $scenario['list_ids'] ) : array();
      $tag_ids = ! empty( $scenario['tag_ids'] ) && is_array( $scenario['tag_ids'] ) ? array_map( 'strval', $scenario['tag_ids'] ) : array();
      foreach ( $contacts as $contact ) {
        if ( ! empty( $scenario['contact_type'] ) && ( empty( $contact['type'] ) || $scenario['contact_type'] !== $contact['type'] ) ) {
          continue;
        }
        if ( ! empty( $scenario['enabled_only'] ) && empty( $contact['enabled'] ) ) {
          continue;
        }
        $keep = empty( $segment_ids ) && empty( $list_ids ) && empty( $tag_ids );
        if ( ! $keep && ! empty( $list_ids ) ) {
          $contact_lists = isset( $contact['lists'] ) && is_array( $contact['lists'] ) ? array_map( 'strval', $contact['lists'] ) : array();
          $keep = 0 < count( array_intersect( $list_ids, $contact_lists ) );
        }
        if ( ! $keep && ! empty( $tag_ids ) ) {
          $contact_tags = isset( $contact['tags'] ) && is_array( $contact['tags'] ) ? array_map( 'strval', $contact['tags'] ) : array();
          $keep = 0 < count( array_intersect( $tag_ids, $contact_tags ) );
        }
        if ( ! $keep && ! empty( $segment_ids ) ) {
          foreach ( $segments as $segment ) {
            if ( empty( $segment['id'] ) || ! in_array( (string) $segment['id'], $segment_ids, true ) ) {
              continue;
            }
            if ( $segment_match( $segment, $contact ) ) {
              $keep = true;
              break;
            }
          }
        }
        if ( $keep && ! empty( $contact['email'] ) ) {
          $matched[ $contact['source_key'] ] = $contact;
        }
      }
      return array_values( $matched );
    };

    $active = 0;
    $draft = 0;
    $population_cumulated = 0;
    foreach ( $items as &$item ) {
      $matched = $estimate_scenario_population( $item );
      $item['population_count'] = count( $matched );
      $population_cumulated += $item['population_count'];
      if ( isset( $item['status'] ) && 'active' === $item['status'] ) {
        $active++;
      }
      if ( isset( $item['status'] ) && 'draft' === $item['status'] ) {
        $draft++;
      }
    }
    unset( $item );

    $this->render_marketing_header( 'Scénarios automatiques', 'Structurez vos déclencheurs, délais, conditions et actions automatiques sans changer le rendu validé du module marketing.', array(
      array( 'label' => 'Créer un scénario', 'url' => $this->portal_page_url( array( 'tab' => $tab, 'action' => 'new' ) ), 'primary' => true ),
      array( 'label' => 'Voir les contacts', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_contacts' ) ) ),
    ) );
    $this->render_marketing_cards( array(
      array( 'label' => 'Scénarios créés', 'value' => count( $items ), 'help' => 'Référentiel global des scénarios automatiques.' ),
      array( 'label' => 'Scénarios actifs', 'value' => $active, 'help' => 'Scénarios immédiatement mobilisables.' ),
      array( 'label' => 'Brouillons', 'value' => $draft, 'help' => 'Scénarios encore en paramétrage.' ),
      array( 'label' => 'Population cumulée', 'value' => $population_cumulated, 'help' => 'Volume théorique de contacts couverts par vos ciblages.' ),
    ) );

    if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
      $item = array();
      foreach ( $items as $candidate ) {
        if ( isset( $candidate['id'] ) && $candidate['id'] === $item_id ) {
          $item = $candidate;
          break;
        }
      }
      $matched_contacts = $estimate_scenario_population( $item );
      echo '<div class="acdc-panel" style="margin-bottom:18px"><form class="acdc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
      wp_nonce_field( 'acdc_marketing_save_entity' );
      echo '<input type="hidden" name="action" value="acdc_marketing_save_entity">';
      echo '<input type="hidden" name="marketing_entity" value="scenarios">';
      echo '<input type="hidden" name="marketing_item[id]" value="' . esc_attr( isset( $item['id'] ) ? $item['id'] : '' ) . '">';
      echo '<div class="acdc-marketing-segments-form-grid">';
      echo '<p><label>Nom du scénario</label><input type="text" name="marketing_item[name]" value="' . esc_attr( isset( $item['name'] ) ? $item['name'] : '' ) . '" required></p>';
      echo '<p><label>Statut</label><select name="marketing_item[status]"><option value="draft" ' . selected( isset( $item['status'] ) ? $item['status'] : 'draft', 'draft', false ) . '>Brouillon</option><option value="active" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'active', false ) . '>Actif</option><option value="planned" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'planned', false ) . '>Planifié</option><option value="archived" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'archived', false ) . '>Archivé</option></select></p>';
      echo '<p><label>Type de contact</label><select name="marketing_item[contact_type]"><option value="">Tous les types</option>';
      foreach ( $this->marketing_type_labels() as $type_key => $type_label ) {
        echo '<option value="' . esc_attr( $type_key ) . '" ' . selected( isset( $item['contact_type'] ) ? $item['contact_type'] : '', $type_key, false ) . '>' . esc_html( $type_label ) . '</option>';
      }
      echo '</select></p>';
      echo '<p><label>Catégorie d’e-mail</label><select name="marketing_item[category]"><option value="marketing" ' . selected( isset( $item['category'] ) ? $item['category'] : 'marketing', 'marketing', false ) . '>Marketing / prospection</option><option value="commercial" ' . selected( isset( $item['category'] ) ? $item['category'] : '', 'commercial', false ) . '>Informations commerciales liées à un devis ou une offre</option><option value="formation" ' . selected( isset( $item['category'] ) ? $item['category'] : '', 'formation', false ) . '>Suivi de formation / accompagnement</option><option value="administratif" ' . selected( isset( $item['category'] ) ? $item['category'] : '', 'administratif', false ) . '>Informations administratives</option><option value="financeur" ' . selected( isset( $item['category'] ) ? $item['category'] : '', 'financeur', false ) . '>Relances financeurs</option><option value="internal" ' . selected( isset( $item['category'] ) ? $item['category'] : '', 'internal', false ) . '>Notifications internes</option></select></p>';
      echo '<p><label>Déclencheur</label><input type="text" name="marketing_item[trigger_type]" value="' . esc_attr( isset( $item['trigger_type'] ) ? $item['trigger_type'] : '' ) . '" placeholder="inscription_formulaire, délai, fin_formation..."></p>';
      echo '<p><label>Objectif</label><input type="text" name="marketing_item[goal]" value="' . esc_attr( isset( $item['goal'] ) ? $item['goal'] : '' ) . '" placeholder="Réactivation, relance devis, suivi..." ></p>';
      echo '<p><label>Ordre d’affichage</label><input type="number" name="marketing_item[sort_order]" value="' . esc_attr( isset( $item['sort_order'] ) ? $item['sort_order'] : 100 ) . '"></p>';
      echo '<style>.acdc-marketing-scenario-choice-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:10px}.acdc-marketing-scenario-choice{display:block;margin:0;cursor:pointer}.acdc-marketing-scenario-choice input{position:absolute;opacity:0;pointer-events:none}.acdc-marketing-scenario-choice-card{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border:1px solid #D8E0EF;border-radius:12px;background:#fff;min-height:74px;transition:border-color .15s ease,box-shadow .15s ease,background-color .15s ease}.acdc-marketing-scenario-choice:hover .acdc-marketing-scenario-choice-card{border-color:#C5A253;box-shadow:0 4px 14px rgba(11,7,6,.08)}.acdc-marketing-scenario-choice input:checked + .acdc-marketing-scenario-choice-card{border-color:#C5A253;background:#FBF6E8;box-shadow:0 4px 14px rgba(197,162,83,.18)}.acdc-marketing-scenario-choice-check{width:18px;height:18px;border:2px solid #C5A253;border-radius:6px;flex:0 0 18px;margin-top:2px;background:#fff}.acdc-marketing-scenario-choice input:checked + .acdc-marketing-scenario-choice-card .acdc-marketing-scenario-choice-check{background:#C5A253;box-shadow:inset 0 0 0 3px #fff}.acdc-marketing-scenario-choice-title{font-weight:700;color:#17325C;line-height:1.25}.acdc-marketing-scenario-choice-meta{margin-top:4px;font-size:12px;line-height:1.35;color:#5F749A}.acdc-marketing-scenario-toggle{margin-top:10px}.acdc-marketing-scenario-toggle .acdc-marketing-scenario-choice-card{min-height:auto;align-items:center}.acdc-marketing-scenario-section{margin-top:18px;padding-top:14px;border-top:1px solid #E2E8F3}.acdc-marketing-scenario-section h3{margin:0 0 6px 0;font-size:15px;color:#17325C}.acdc-marketing-scenario-section p.acdc-marketing-scenario-help{margin:0 0 10px 0;font-size:12px;color:#5F749A}</style>';
      echo '<div class="acdc-marketing-scenario-toggle">';
      echo '<label class="acdc-marketing-scenario-choice">';
      echo '<input type="checkbox" name="marketing_item[enabled_only]" value="1" ' . checked( ! empty( $item['enabled_only'] ), true, false ) . '>';
      echo '<span class="acdc-marketing-scenario-choice-card"><span class="acdc-marketing-scenario-choice-check"></span><span><span class="acdc-marketing-scenario-choice-title">Contacts marketing activés uniquement</span><span class="acdc-marketing-scenario-choice-meta">Ne retient que les fiches déjà activées pour la communication marketing.</span></span></span>';
      echo '</label>';
      echo '</div>';
      echo '</div>';

      echo '<div class="acdc-marketing-scenario-section"><h3>Listes ciblées</h3><p class="acdc-marketing-scenario-help">Choisissez une ou plusieurs listes métier à inclure dans le déclenchement.</p><div class="acdc-marketing-scenario-choice-grid">';
      $current_lists = isset( $item['list_ids'] ) && is_array( $item['list_ids'] ) ? array_map( 'strval', $item['list_ids'] ) : array();
      foreach ( $lists as $list ) {
        if ( empty( $list['id'] ) ) { continue; }
        $list_name = isset( $list['name'] ) ? $list['name'] : $list['id'];
        $list_meta = array();
        if ( ! empty( $list['description'] ) ) { $list_meta[] = $list['description']; }
        echo '<label class="acdc-marketing-scenario-choice">';
        echo '<input type="checkbox" name="marketing_item[list_ids][]" value="' . esc_attr( $list['id'] ) . '" ' . checked( in_array( (string) $list['id'], $current_lists, true ), true, false ) . '>';
        echo '<span class="acdc-marketing-scenario-choice-card"><span class="acdc-marketing-scenario-choice-check"></span><span><span class="acdc-marketing-scenario-choice-title">' . esc_html( $list_name ) . '</span>';
        if ( ! empty( $list_meta ) ) { echo '<span class="acdc-marketing-scenario-choice-meta">' . esc_html( implode( ' • ', array_filter( $list_meta ) ) ) . '</span>'; }
        echo '</span></span></label>';
      }
      if ( empty( $lists ) ) { echo '<span style="color:#1E4777">Aucune liste disponible.</span>'; }
      echo '</div></div>';

      echo '<div class="acdc-marketing-scenario-section"><h3>Étiquettes ciblées</h3><p class="acdc-marketing-scenario-help">Affinez le déclenchement avec les étiquettes métier déjà validées.</p><div class="acdc-marketing-scenario-choice-grid">';
      $current_tags = isset( $item['tag_ids'] ) && is_array( $item['tag_ids'] ) ? array_map( 'strval', $item['tag_ids'] ) : array();
      foreach ( $tags as $tag ) {
        if ( empty( $tag['id'] ) ) { continue; }
        $tag_title = isset( $tag['name'] ) ? $tag['name'] : $tag['id'];
        $tag_meta = ! empty( $tag['description'] ) ? $tag['description'] : '';
        echo '<label class="acdc-marketing-scenario-choice">';
        echo '<input type="checkbox" name="marketing_item[tag_ids][]" value="' . esc_attr( $tag['id'] ) . '" ' . checked( in_array( (string) $tag['id'], $current_tags, true ), true, false ) . '>';
        echo '<span class="acdc-marketing-scenario-choice-card"><span class="acdc-marketing-scenario-choice-check"></span><span><span class="acdc-marketing-scenario-choice-title">' . esc_html( $tag_title ) . '</span>';
        if ( ! empty( $tag_meta ) ) { echo '<span class="acdc-marketing-scenario-choice-meta">' . esc_html( $tag_meta ) . '</span>'; }
        echo '</span></span></label>';
      }
      if ( empty( $tags ) ) { echo '<span style="color:#1E4777">Aucune étiquette disponible.</span>'; }
      echo '</div></div>';

      echo '<div class="acdc-marketing-scenario-section"><h3>Segments ciblés</h3><p class="acdc-marketing-scenario-help">Réutilisez les segments existants sans changer l’ergonomie validée du module.</p><div class="acdc-marketing-scenario-choice-grid">';
      $current_segments = isset( $item['segment_ids'] ) && is_array( $item['segment_ids'] ) ? array_map( 'strval', $item['segment_ids'] ) : array();
      foreach ( $segments as $segment ) {
        if ( empty( $segment['id'] ) ) { continue; }
        $segment_name = isset( $segment['name'] ) ? $segment['name'] : $segment['id'];
        $segment_meta = array();
        if ( ! empty( $segment['description'] ) ) { $segment_meta[] = $segment['description']; }
        if ( isset( $segment['population_count'] ) ) { $segment_meta[] = 'Population : ' . (string) $segment['population_count']; }
        echo '<label class="acdc-marketing-scenario-choice">';
        echo '<input type="checkbox" name="marketing_item[segment_ids][]" value="' . esc_attr( $segment['id'] ) . '" ' . checked( in_array( (string) $segment['id'], $current_segments, true ), true, false ) . '>';
        echo '<span class="acdc-marketing-scenario-choice-card"><span class="acdc-marketing-scenario-choice-check"></span><span><span class="acdc-marketing-scenario-choice-title">' . esc_html( $segment_name ) . '</span>';
        if ( ! empty( $segment_meta ) ) { echo '<span class="acdc-marketing-scenario-choice-meta">' . esc_html( implode( ' • ', array_filter( $segment_meta ) ) ) . '</span>'; }
        echo '</span></span></label>';
      }
      if ( empty( $segments ) ) { echo '<span style="color:#1E4777">Aucun segment disponible.</span>'; }
      echo '</div></div>';

      echo '<p><label>Logique / étapes du scénario</label><textarea name="marketing_item[rules]" rows="8">' . esc_textarea( isset( $item['rules'] ) ? $item['rules'] : '' ) . '</textarea></p>';
      echo '<p><label>Description métier</label><textarea name="marketing_item[description]" rows="4">' . esc_textarea( isset( $item['description'] ) ? $item['description'] : '' ) . '</textarea></p>';
      echo '<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:0 0 18px 0">';
      echo '<div class="acdc-kpi-card"><strong>Destinataires estimés</strong><div style="font-size:28px;font-weight:700">' . esc_html( (string) count( $matched_contacts ) ) . '</div><small>Aperçu calculé à partir des sélections.</small></div>';
      echo '<div class="acdc-kpi-card"><strong>Déclencheur</strong><div style="font-size:28px;font-weight:700">' . esc_html( ! empty( $item['trigger_type'] ) ? 'Oui' : 'Non' ) . '</div><small>Un déclencheur clair évite les scénarios ambigus.</small></div>';
      echo '<div class="acdc-kpi-card"><strong>Objectif</strong><div style="font-size:28px;font-weight:700">' . esc_html( ! empty( $item['goal'] ) ? 'Oui' : 'Non' ) . '</div><small>Chaque scénario doit porter un objectif explicite.</small></div>';
      echo '</div>';
      echo '<p><button type="submit" class="acdc-button acdc-button-primary">Enregistrer le scénario</button> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab ) ) ) . '">Retour à la liste</a></p>';
      echo '</form></div>';
    }

    if ( 'view' === $action && ! empty( $item_id ) ) {
      $item = array();
      foreach ( $items as $candidate ) {
        if ( isset( $candidate['id'] ) && $candidate['id'] === $item_id ) {
          $item = $candidate;
          break;
        }
      }
      if ( ! empty( $item ) ) {
        $matched_contacts = $estimate_scenario_population( $item );
        echo '<div class="acdc-panel" style="margin-bottom:18px"><div style="display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap;align-items:flex-start"><div><h2 style="margin:0 0 8px 0">Fiche scénario</h2><p style="margin:0;color:#1E4777">Vue d’ensemble du scénario, de son ciblage et de sa logique métier.</p></div><div style="display:flex;gap:10px;flex-wrap:wrap"><a class="acdc-button acdc-button-primary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab, 'action' => 'edit', 'item_id' => $item_id ) ) ) . '">Modifier</a><a class="acdc-button acdc-button-secondary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab ) ) ) . '">Retour à la liste</a></div></div></div>';
        echo '<div style="display:grid;grid-template-columns:1.1fr 0.9fr;gap:18px;margin-bottom:18px">';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Informations générales</h3><table class="acdc-table"><tbody>';
        echo '<tr><th>Nom</th><td>' . esc_html( isset( $item['name'] ) ? $item['name'] : '' ) . '</td></tr>';
        echo '<tr><th>Statut</th><td>' . esc_html( isset( $item['status'] ) ? $item['status'] : '' ) . '</td></tr>';
        echo '<tr><th>Catégorie</th><td>' . esc_html( isset( $item['category'] ) ? $item['category'] : '' ) . '</td></tr>';
        echo '<tr><th>Type de contact</th><td>' . esc_html( isset( $item['contact_type'] ) ? $item['contact_type'] : 'tous' ) . '</td></tr>';
        echo '<tr><th>Déclencheur</th><td>' . esc_html( isset( $item['trigger_type'] ) ? $item['trigger_type'] : '' ) . '</td></tr>';
        echo '<tr><th>Objectif</th><td>' . esc_html( isset( $item['goal'] ) ? $item['goal'] : '' ) . '</td></tr>';
        echo '<tr><th>Description</th><td>' . esc_html( isset( $item['description'] ) ? $item['description'] : '' ) . '</td></tr>';
        echo '<tr><th>Logique / étapes</th><td>' . esc_html( isset( $item['rules'] ) ? $item['rules'] : '' ) . '</td></tr>';
        echo '<tr><th>Mise à jour</th><td>' . esc_html( isset( $item['updated_at'] ) ? $item['updated_at'] : '' ) . '</td></tr>';
        echo '</tbody></table></div>';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Ciblage estimé</h3><div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:12px"><div class="acdc-kpi-card"><strong>Destinataires</strong><div style="font-size:28px;font-weight:700">' . esc_html( (string) count( $matched_contacts ) ) . '</div></div><div class="acdc-kpi-card"><strong>Listes</strong><div style="font-size:28px;font-weight:700">' . esc_html( (string) count( isset( $item['list_ids'] ) && is_array( $item['list_ids'] ) ? $item['list_ids'] : array() ) ) . '</div></div><div class="acdc-kpi-card"><strong>Segments</strong><div style="font-size:28px;font-weight:700">' . esc_html( (string) count( isset( $item['segment_ids'] ) && is_array( $item['segment_ids'] ) ? $item['segment_ids'] : array() ) ) . '</div></div></div><p style="margin:0;color:#1E4777">Estimation locale à partir des listes, étiquettes, segments et du type de contact.</p></div>';
        echo '</div>';
        echo '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px">';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Échantillon de population</h3><table class="acdc-table"><thead><tr><th>Nom</th><th>Type</th><th>E-mail</th></tr></thead><tbody>';
        foreach ( array_slice( $matched_contacts, 0, 20 ) as $contact ) {
          echo '<tr><td>' . esc_html( isset( $contact['name'] ) ? $contact['name'] : '' ) . '</td><td>' . esc_html( isset( $contact['type'] ) ? $contact['type'] : '' ) . '</td><td>' . esc_html( isset( $contact['email'] ) ? $contact['email'] : '' ) . '</td></tr>';
        }
        if ( empty( $matched_contacts ) ) {
          echo '<tr><td colspan="3">Aucun destinataire estimé pour ce scénario.</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="acdc-panel"><h3 style="margin-top:0">Ciblage retenu</h3><table class="acdc-table"><tbody>';
        echo '<tr><th>Listes</th><td>' . esc_html( $this->marketing_contact_related_labels( isset( $item['list_ids'] ) ? $item['list_ids'] : array(), 'lists' ) ) . '</td></tr>';
        echo '<tr><th>Étiquettes</th><td>' . esc_html( $this->marketing_contact_related_labels( isset( $item['tag_ids'] ) ? $item['tag_ids'] : array(), 'tags' ) ) . '</td></tr>';
        echo '<tr><th>Segments</th><td>' . esc_html( $this->marketing_contact_related_labels( isset( $item['segment_ids'] ) ? $item['segment_ids'] : array(), 'segments' ) ) . '</td></tr>';
        echo '</tbody></table></div>';
        echo '</div>';
      }
    }

    echo '<div class="acdc-panel" style="margin-bottom:18px"><form method="get" class="acdc-filters" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;align-items:end">';
    echo '<input type="hidden" name="page" value="acdc-formation-saas">';
    echo '<input type="hidden" name="tab" value="marketing_scenarios">';
    echo '<p style="margin:0"><label>Recherche</label><input type="text" name="marketing_search" value="' . esc_attr( $search ) . '" placeholder="Nom ou description"></p>';
    echo '<p style="margin:0"><label>Statut</label><select name="marketing_status_filter"><option value="">Tous</option><option value="draft" ' . selected( $status_filter, 'draft', false ) . '>Brouillon</option><option value="active" ' . selected( $status_filter, 'active', false ) . '>Actif</option><option value="planned" ' . selected( $status_filter, 'planned', false ) . '>Planifié</option><option value="archived" ' . selected( $status_filter, 'archived', false ) . '>Archivé</option></select></p>';
    echo '<p style="margin:0"><label>Tri</label><select disabled><option>Nom croissant</option></select></p>';
    echo '<p style="margin:0"><button type="submit" class="acdc-button acdc-button-secondary">Filtrer</button></p>';
    echo '</form></div>';

    echo '<div class="acdc-panel"><table class="acdc-table"><thead><tr><th>Nom</th><th>Statut</th><th>Déclencheur</th><th>Objectif</th><th>Population</th><th>Mise à jour</th><th>Actions</th></tr></thead><tbody>';
    $printed = 0;
    foreach ( $items as $item ) {
      $haystack = strtolower( trim( (string) ( isset( $item['name'] ) ? $item['name'] : '' ) . ' ' . ( isset( $item['description'] ) ? $item['description'] : '' ) ) );
      if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) {
        continue;
      }
      if ( '' !== $status_filter && ( ! isset( $item['status'] ) || $item['status'] !== $status_filter ) ) {
        continue;
      }
      $id = isset( $item['id'] ) ? $item['id'] : '';
      $edit_url = $this->portal_page_url( array( 'tab' => $tab, 'action' => 'edit', 'item_id' => $id ) );
      $view_url = $this->portal_page_url( array( 'tab' => $tab, 'action' => 'view', 'item_id' => $id ) );
      $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'acdc_marketing_delete_entity', 'marketing_entity' => 'scenarios', 'item_id' => $id ), admin_url( 'admin-post.php' ) ), 'acdc_marketing_delete_entity' );
      $population = isset( $item['population_count'] ) ? (int) $item['population_count'] : count( $estimate_scenario_population( $item ) );
      echo '<tr>';
      echo '<td><strong>' . esc_html( isset( $item['name'] ) ? $item['name'] : '' ) . '</strong><br><small>' . esc_html( isset( $item['description'] ) ? $item['description'] : '' ) . '</small></td>';
      echo '<td>' . esc_html( isset( $item['status'] ) ? $item['status'] : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['trigger_type'] ) ? $item['trigger_type'] : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['goal'] ) ? $item['goal'] : '' ) . '</td>';
      echo '<td>' . esc_html( (string) $population ) . '</td>';
      echo '<td>' . esc_html( isset( $item['updated_at'] ) ? $item['updated_at'] : '' ) . '</td>';
      echo '<td><a class="acdc-button acdc-button-secondary" href="' . esc_url( $view_url ) . '">Voir</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $edit_url ) . '">Modifier</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'Confirmer la suppression ?\')">Supprimer</a></td>';
      echo '</tr>';
      $printed++;
    }
    if ( 0 === $printed ) {
      echo '<tr><td colspan="7">Aucun scénario ne correspond aux critères actuels.</td></tr>';
    }
    echo '</tbody></table></div>';
  }




  private function render_front_marketing_forms_tab() {
    $entity = 'forms';
    $tab = 'marketing_forms';
    $items = $this->get_marketing_store( $entity, array() );
    $lists = $this->get_marketing_store( 'lists', array() );
    $tags = $this->get_marketing_store( 'tags', array() );
    $scenarios = $this->get_marketing_store( 'scenarios', array() );
    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
    $item_id = isset( $_GET['item_id'] ) ? sanitize_text_field( wp_unslash( $_GET['item_id'] ) ) : '';
    $search = isset( $_GET['marketing_search'] ) ? sanitize_text_field( wp_unslash( $_GET['marketing_search'] ) ) : '';
    $status_filter = isset( $_GET['marketing_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['marketing_status_filter'] ) ) : '';

    $active = 0; $public = 0; $mapped_lists = 0; $mapped_tags = 0;
    foreach ( $items as $item ) {
      if ( isset( $item['status'] ) && 'active' === $item['status'] ) { $active++; }
      if ( ! empty( $item['public_token'] ) ) { $public++; }
      $mapped_lists += ! empty( $item['list_ids'] ) && is_array( $item['list_ids'] ) ? count( $item['list_ids'] ) : 0;
      $mapped_tags += ! empty( $item['tag_ids'] ) && is_array( $item['tag_ids'] ) ? count( $item['tag_ids'] ) : 0;
    }

    $this->render_marketing_header( 'Formulaires', 'Créez vos formulaires natifs et leurs pages publiques autonomes sans changer le rendu validé du module marketing.', array(
      array( 'label' => 'Créer un formulaire', 'url' => $this->portal_page_url( array( 'tab' => $tab, 'action' => 'new' ) ), 'primary' => true ),
      array( 'label' => 'Voir les contacts', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_contacts' ) ) ),
    ) );
    $this->render_marketing_cards( array(
      array( 'label' => 'Formulaires créés', 'value' => count( $items ), 'help' => 'Référentiel global des formulaires natifs du module.' ),
      array( 'label' => 'Formulaires actifs', 'value' => $active, 'help' => 'Disponibles immédiatement sur leurs pages publiques.' ),
      array( 'label' => 'Pages publiques', 'value' => $public, 'help' => 'Formulaires disposant d’un lien public autonome.' ),
      array( 'label' => 'Affectations marketing', 'value' => $mapped_lists . ' listes / ' . $mapped_tags . ' étiquettes', 'help' => 'Rattachements automatiques à l’entrée dans le module.' ),
    ) );

    if ( in_array( $action, array( 'new', 'edit', 'view' ), true ) ) {
      $item = array();
      foreach ( $items as $candidate ) {
        if ( isset( $candidate['id'] ) && $candidate['id'] === $item_id ) {
          $item = $candidate;
          break;
        }
      }
      $public_url = ! empty( $item['public_token'] ) ? add_query_arg( array( 'form' => rawurlencode( $item['public_token'] ) ), $this->get_marketing_public_base_url() ) : '';

      if ( 'view' === $action && ! empty( $item ) ) {
        echo '<div class="acdc-panel" style="margin-bottom:18px">';
        echo '<div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap"><div><h2 style="margin:0 0 8px 0">Fiche formulaire</h2><p style="margin:0;color:#1E4777">Lecture rapide du formulaire, de sa page publique et de ses affectations marketing.</p></div><div><a class="acdc-button acdc-button-primary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab, 'action' => 'edit', 'item_id' => $item['id'] ) ) ) . '">Modifier</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab ) ) ) . '">Retour à la liste</a></div></div>';
        echo '<div class="acdc-marketing-segments-form-grid" style="margin-top:16px">';
        echo '<p><label>Nom</label><input type="text" value="' . esc_attr( isset( $item['name'] ) ? $item['name'] : '' ) . '" disabled></p>';
        echo '<p><label>Statut</label><input type="text" value="' . esc_attr( isset( $item['status'] ) ? $item['status'] : '' ) . '" disabled></p>';
        echo '<p><label>Slug</label><input type="text" value="' . esc_attr( isset( $item['slug'] ) ? $item['slug'] : '' ) . '" disabled></p>';
        echo '<p><label>Catégorie d’e-mail</label><input type="text" value="' . esc_attr( isset( $item['category'] ) && isset( $this->marketing_categories()[ $item['category'] ] ) ? $this->marketing_categories()[ $item['category'] ] : '' ) . '" disabled></p>';
        echo '</div>';
        echo '<p><label>Description</label><textarea rows="4" disabled>' . esc_textarea( isset( $item['description'] ) ? $item['description'] : '' ) . '</textarea></p>';
        echo '<p><label>Introduction</label><textarea rows="4" disabled>' . esc_textarea( isset( $item['intro_text'] ) ? $item['intro_text'] : '' ) . '</textarea></p>';
        echo '<p><label>Message de confirmation</label><textarea rows="3" disabled>' . esc_textarea( isset( $item['success_message'] ) ? $item['success_message'] : '' ) . '</textarea></p>';
        echo '<p><label>Contenu / structure</label><textarea rows="8" disabled>' . esc_textarea( isset( $item['content'] ) ? wp_strip_all_tags( $item['content'] ) : '' ) . '</textarea></p>';
        echo '<div class="acdc-marketing-segments-form-grid">';
        echo '<p><label>Listes liées</label><input type="text" value="' . esc_attr( $this->marketing_related_names_from_items( isset( $item['list_ids'] ) ? $item['list_ids'] : array(), $lists ) ) . '" disabled></p>';
        echo '<p><label>Étiquettes liées</label><input type="text" value="' . esc_attr( $this->marketing_related_names_from_items( isset( $item['tag_ids'] ) ? $item['tag_ids'] : array(), $tags ) ) . '" disabled></p>';
        echo '<p><label>Scénario de départ</label><input type="text" value="' . esc_attr( $this->marketing_related_name_by_id( isset( $item['trigger_scenario_id'] ) ? $item['trigger_scenario_id'] : '', $scenarios ) ) . '" disabled></p>';
        echo '<p><label>Page publique</label><input type="text" value="' . esc_attr( $public_url ) . '" disabled></p>';
        echo '</div>';
        if ( $public_url ) { echo '<p><a class="acdc-button acdc-button-secondary" href="' . esc_url( $public_url ) . '" target="_blank" rel="noopener">Ouvrir la page publique</a></p>'; }
        echo '</div>';
      } else {
        echo '<div class="acdc-panel" style="margin-bottom:18px"><form class="acdc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'acdc_marketing_save_entity' );
        echo '<input type="hidden" name="action" value="acdc_marketing_save_entity">';
        echo '<input type="hidden" name="marketing_entity" value="forms">';
        echo '<input type="hidden" name="marketing_item[id]" value="' . esc_attr( isset( $item['id'] ) ? $item['id'] : '' ) . '">';
        echo '<div class="acdc-marketing-segments-form-grid">';
        echo '<p><label>Nom du formulaire</label><input type="text" name="marketing_item[name]" value="' . esc_attr( isset( $item['name'] ) ? $item['name'] : '' ) . '" required></p>';
        echo '<p><label>Statut</label><select name="marketing_item[status]"><option value="draft" ' . selected( isset( $item['status'] ) ? $item['status'] : 'draft', 'draft', false ) . '>Brouillon</option><option value="active" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'active', false ) . '>Actif</option><option value="planned" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'planned', false ) . '>Planifié</option><option value="archived" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'archived', false ) . '>Archivé</option></select></p>';
        echo '<p><label>Catégorie d’e-mail</label><select name="marketing_item[category]">';
        foreach ( $this->marketing_categories() as $key => $label ) {
          echo '<option value="' . esc_attr( $key ) . '" ' . selected( isset( $item['category'] ) ? $item['category'] : 'marketing', $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></p>';
        echo '<p><label>Slug public</label><input type="text" name="marketing_item[slug]" value="' . esc_attr( isset( $item['slug'] ) ? $item['slug'] : '' ) . '" placeholder="inscription-newsletter"></p>';
        echo '<p><label>Titre public</label><input type="text" name="marketing_item[public_title]" value="' . esc_attr( isset( $item['public_title'] ) ? $item['public_title'] : '' ) . '" placeholder="Titre affiché sur la page"></p>';
        echo '<p><label>Scénario de départ</label><select name="marketing_item[trigger_scenario_id]"><option value="">Aucun scénario automatique</option>';
        foreach ( $scenarios as $scenario ) {
          echo '<option value="' . esc_attr( isset( $scenario['id'] ) ? $scenario['id'] : '' ) . '" ' . selected( isset( $item['trigger_scenario_id'] ) ? $item['trigger_scenario_id'] : '', isset( $scenario['id'] ) ? $scenario['id'] : '', false ) . '>' . esc_html( isset( $scenario['name'] ) ? $scenario['name'] : '' ) . '</option>';
        }
        echo '</select></p>';
        echo '<p><label>Redirection après validation</label><input type="url" name="marketing_item[redirect_url]" value="' . esc_attr( isset( $item['redirect_url'] ) ? $item['redirect_url'] : '' ) . '" placeholder="https://..."></p>';
        echo '<p><label>Visuel / logo</label><input type="url" name="marketing_item[image_url]" value="' . esc_attr( isset( $item['image_url'] ) ? $item['image_url'] : '' ) . '" placeholder="URL d’un visuel"></p>';
        echo '</div>';
        echo '<style>.acdc-marketing-segment-choice-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:10px}.acdc-marketing-segment-choice{display:block;margin:0;cursor:pointer}.acdc-marketing-segment-choice input{position:absolute;opacity:0;pointer-events:none}.acdc-marketing-segment-choice-card{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border:1px solid #D8E0EF;border-radius:12px;background:#fff;min-height:74px;transition:border-color .15s ease,box-shadow .15s ease,background-color .15s ease}.acdc-marketing-segment-choice:hover .acdc-marketing-segment-choice-card{border-color:#C5A253;box-shadow:0 4px 14px rgba(11,7,6,.08)}.acdc-marketing-segment-choice input:checked + .acdc-marketing-segment-choice-card{border-color:#C5A253;background:#FBF6E8;box-shadow:0 4px 14px rgba(197,162,83,.18)}.acdc-marketing-segment-choice-check{width:18px;height:18px;border:2px solid #C5A253;border-radius:6px;flex:0 0 18px;margin-top:2px;background:#fff}.acdc-marketing-segment-choice input:checked + .acdc-marketing-segment-choice-card .acdc-marketing-segment-choice-check{background:#C5A253;box-shadow:inset 0 0 0 3px #fff}.acdc-marketing-segment-section{margin-top:18px;padding-top:14px;border-top:1px solid #E2E8F3}.acdc-marketing-segment-section h3{margin:0 0 6px 0;font-size:15px;color:#17325C}.acdc-marketing-segment-section p.acdc-marketing-segment-help{margin:0 0 10px 0;font-size:12px;color:#5F749A}.acdc-marketing-segment-choice-title{font-weight:700;color:#17325C;line-height:1.25}.acdc-marketing-segment-choice-meta{margin-top:4px;font-size:12px;line-height:1.35;color:#5F749A}</style>';

        echo '<div class="acdc-marketing-segment-section"><h3>Affectations marketing</h3><p class="acdc-marketing-segment-help">Choisissez les listes et étiquettes automatiquement appliquées à l’entrée dans ce formulaire.</p></div>';
        echo '<div class="acdc-marketing-segment-section"><h3>Listes liées</h3><p class="acdc-marketing-segment-help">Choisissez une ou plusieurs listes métier à rattacher automatiquement.</p><div class="acdc-marketing-segment-choice-grid">';
        $current_lists = ! empty( $item['list_ids'] ) && is_array( $item['list_ids'] ) ? array_map( 'strval', $item['list_ids'] ) : array();
        foreach ( $lists as $list ) {
          if ( empty( $list['id'] ) ) { continue; }
          $id = (string) $list['id'];
          $list_name = isset( $list['name'] ) ? $list['name'] : $id;
          $list_meta = array();
          if ( ! empty( $list['usage'] ) ) { $list_meta[] = $list['usage']; }
          if ( ! empty( $list['source_hint'] ) ) { $list_meta[] = $list['source_hint']; }
          echo '<label class="acdc-marketing-segment-choice">';
          echo '<input type="checkbox" name="marketing_item[list_ids][]" value="' . esc_attr( $id ) . '" ' . checked( in_array( $id, $current_lists, true ), true, false ) . '>';
          echo '<span class="acdc-marketing-segment-choice-card"><span class="acdc-marketing-segment-choice-check"></span><span><span class="acdc-marketing-segment-choice-title">' . esc_html( $list_name ) . '</span>';
          if ( ! empty( $list_meta ) ) {
            echo '<span class="acdc-marketing-segment-choice-meta">' . esc_html( implode( ' • ', array_filter( $list_meta ) ) ) . '</span>';
          }
          echo '</span></span></label>';
        }
        if ( empty( $lists ) ) { echo '<span style="color:#1E4777">Aucune liste disponible.</span>'; }
        echo '</div></div>';
        echo '<div class="acdc-marketing-segment-section"><h3>Étiquettes liées</h3><p class="acdc-marketing-segment-help">Utilisez les étiquettes pour qualifier automatiquement les contacts issus du formulaire.</p><div class="acdc-marketing-segment-choice-grid">';
        $current_tags = ! empty( $item['tag_ids'] ) && is_array( $item['tag_ids'] ) ? array_map( 'strval', $item['tag_ids'] ) : array();
        foreach ( $tags as $tag ) {
          if ( empty( $tag['id'] ) ) { continue; }
          $id = (string) $tag['id'];
          $tag_name = isset( $tag['name'] ) ? $tag['name'] : $id;
          $tag_title = $tag_name;
          $tag_meta = '';
          if ( false !== strpos( $tag_name, ':' ) ) {
            $parts = explode( ':', $tag_name, 2 );
            $tag_title = trim( $parts[0] ) . ' : ' . trim( $parts[1] );
          }
          if ( ! empty( $tag['usage'] ) ) { $tag_meta = $tag['usage']; }
          echo '<label class="acdc-marketing-segment-choice">';
          echo '<input type="checkbox" name="marketing_item[tag_ids][]" value="' . esc_attr( $id ) . '" ' . checked( in_array( $id, $current_tags, true ), true, false ) . '>';
          echo '<span class="acdc-marketing-segment-choice-card"><span class="acdc-marketing-segment-choice-check"></span><span><span class="acdc-marketing-segment-choice-title">' . esc_html( $tag_title ) . '</span>';
          if ( ! empty( $tag_meta ) ) {
            echo '<span class="acdc-marketing-segment-choice-meta">' . esc_html( $tag_meta ) . '</span>';
          }
          echo '</span></span></label>';
        }
        if ( empty( $tags ) ) { echo '<span style="color:#1E4777">Aucune étiquette disponible.</span>'; }
        echo '</div></div>';

        echo '<p style="margin-top:16px"><label>Description / cadre d’usage</label><textarea name="marketing_item[description]" rows="4">' . esc_textarea( isset( $item['description'] ) ? $item['description'] : '' ) . '</textarea></p>';
        echo '<p><label>Texte d’introduction</label><textarea name="marketing_item[intro_text]" rows="4">' . esc_textarea( isset( $item['intro_text'] ) ? $item['intro_text'] : '' ) . '</textarea></p>';
        echo '<p><label>Structure / contenu du formulaire</label><textarea name="marketing_item[content]" rows="8">' . esc_textarea( isset( $item['content'] ) ? $item['content'] : '' ) . '</textarea></p>';
        echo '<p><label>Message de confirmation</label><textarea name="marketing_item[success_message]" rows="3">' . esc_textarea( isset( $item['success_message'] ) ? $item['success_message'] : '' ) . '</textarea></p>';
        echo '<p><button type="submit" class="acdc-button acdc-button-primary">Enregistrer le formulaire</button> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $this->portal_page_url( array( 'tab' => $tab ) ) ) . '">Retour à la liste</a></p>';
        echo '</form></div>';
      }
    }

    echo '<div class="acdc-panel" style="margin-bottom:18px"><form class="acdc-form" method="get">';
    echo '<input type="hidden" name="tab" value="marketing_forms">';
    echo '<div style="display:grid;grid-template-columns:2fr repeat(2,minmax(0,1fr));gap:16px;align-items:end">';
    echo '<p><label>Recherche</label><input type="text" name="marketing_search" value="' . esc_attr( $search ) . '" placeholder="Nom, slug, description"></p>';
    echo '<p><label>Statut</label><select name="marketing_status_filter"><option value="">Tous</option><option value="draft" ' . selected( $status_filter, 'draft', false ) . '>Brouillon</option><option value="active" ' . selected( $status_filter, 'active', false ) . '>Actif</option><option value="planned" ' . selected( $status_filter, 'planned', false ) . '>Planifié</option><option value="archived" ' . selected( $status_filter, 'archived', false ) . '>Archivé</option></select></p>';
    echo '<p><button type="submit" class="acdc-button acdc-button-primary">Filtrer</button></p>';
    echo '</div></form></div>';

    $filtered = array();
    foreach ( $items as $item ) {
      $ok = true;
      if ( '' !== $search ) {
        $haystack = strtolower( implode( ' ', array( isset( $item['name'] ) ? $item['name'] : '', isset( $item['slug'] ) ? $item['slug'] : '', isset( $item['description'] ) ? $item['description'] : '' ) ) );
        if ( false === strpos( $haystack, strtolower( $search ) ) ) { $ok = false; }
      }
      if ( $ok && '' !== $status_filter && ( ! isset( $item['status'] ) || $item['status'] !== $status_filter ) ) { $ok = false; }
      if ( $ok ) { $filtered[] = $item; }
    }

    echo '<div class="acdc-panel"><table class="acdc-table"><thead><tr><th>Nom</th><th>Statut</th><th>Catégorie</th><th>Page publique</th><th>Listes / étiquettes</th><th>Mise à jour</th><th>Actions</th></tr></thead><tbody>';
    foreach ( $filtered as $item ) {
      $view_url = $this->portal_page_url( array( 'tab' => $tab, 'action' => 'view', 'item_id' => isset( $item['id'] ) ? $item['id'] : '' ) );
      $edit_url = $this->portal_page_url( array( 'tab' => $tab, 'action' => 'edit', 'item_id' => isset( $item['id'] ) ? $item['id'] : '' ) );
      $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'acdc_marketing_delete_entity', 'marketing_entity' => 'forms', 'item_id' => isset( $item['id'] ) ? $item['id'] : '' ), admin_url( 'admin-post.php' ) ), 'acdc_marketing_delete_entity' );
      $public_url = ! empty( $item['public_token'] ) ? add_query_arg( array( 'form' => rawurlencode( $item['public_token'] ) ), $this->get_marketing_public_base_url() ) : '';
      echo '<tr>';
      echo '<td><strong>' . esc_html( isset( $item['name'] ) ? $item['name'] : '' ) . '</strong><br><small>' . esc_html( isset( $item['slug'] ) ? $item['slug'] : '' ) . '</small></td>';
      echo '<td>' . esc_html( isset( $item['status'] ) ? $item['status'] : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['category'] ) && isset( $this->marketing_categories()[ $item['category'] ] ) ? $this->marketing_categories()[ $item['category'] ] : '' ) . '</td>';
      echo '<td>' . ( $public_url ? '<a href="' . esc_url( $public_url ) . '" target="_blank" rel="noopener">Ouvrir</a>' : '—' ) . '</td>';
      echo '<td><small>' . esc_html( $this->marketing_related_names_from_items( isset( $item['list_ids'] ) ? $item['list_ids'] : array(), $lists ) ) . '</small><br><small>' . esc_html( $this->marketing_related_names_from_items( isset( $item['tag_ids'] ) ? $item['tag_ids'] : array(), $tags ) ) . '</small></td>';
      echo '<td>' . esc_html( isset( $item['updated_at'] ) ? $item['updated_at'] : '' ) . '</td>';
      echo '<td><a class="acdc-button acdc-button-secondary" href="' . esc_url( $view_url ) . '">Voir</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $edit_url ) . '">Modifier</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'Confirmer la suppression ?\')">Supprimer</a></td>';
      echo '</tr>';
    }
    if ( empty( $filtered ) ) { echo '<tr><td colspan="7">Aucun formulaire enregistré.</td></tr>'; }
    echo '</tbody></table></div>';
  }


    private function render_front_marketing_templates_tab() {
    $this->render_front_marketing_generic_tab(
      'templates',
      'Modèles d’e-mails',
      'Gérez vos modèles d’e-mails dans un écran stable du module marketing, sans modifier le shell global.'
    );
  }



  private function render_front_marketing_generic_tab( $entity, $title, $description ) {
    $labels = $this->marketing_entity_labels();
    $tab = 'marketing_' . $entity;
    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
    $item_id = isset( $_GET['item_id'] ) ? sanitize_text_field( wp_unslash( $_GET['item_id'] ) ) : '';
    $items = $this->get_marketing_store( $entity, array() );
    $this->render_marketing_header( $title, $description, array(
      array( 'label' => 'Créer', 'url' => $this->portal_page_url( array( 'tab' => $tab, 'action' => 'new' ) ), 'primary' => true ),
    ) );
    if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
      $item = array();
      foreach ( $items as $candidate ) {
        if ( isset( $candidate['id'] ) && $candidate['id'] === $item_id ) {
          $item = $candidate;
          break;
        }
      }
      echo '<div class="acdc-panel" style="margin-bottom:18px"><form class="acdc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
      wp_nonce_field( 'acdc_marketing_save_entity' );
      echo '<input type="hidden" name="action" value="acdc_marketing_save_entity">';
      echo '<input type="hidden" name="marketing_entity" value="' . esc_attr( $entity ) . '">';
      echo '<input type="hidden" name="marketing_item[id]" value="' . esc_attr( isset( $item['id'] ) ? $item['id'] : '' ) . '">';
      echo '<div class="acdc-marketing-segments-form-grid">';
      echo '<p><label>Nom</label><input type="text" name="marketing_item[name]" value="' . esc_attr( isset( $item['name'] ) ? $item['name'] : '' ) . '" required></p>';
      echo '<p><label>Statut</label><select name="marketing_item[status]"><option value="draft" ' . selected( isset( $item['status'] ) ? $item['status'] : 'draft', 'draft', false ) . '>Brouillon</option><option value="active" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'active', false ) . '>Actif</option><option value="planned" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'planned', false ) . '>Planifié</option><option value="archived" ' . selected( isset( $item['status'] ) ? $item['status'] : '', 'archived', false ) . '>Archivé</option></select></p>';
      if ( in_array( $entity, array( 'campaigns', 'templates', 'scenarios', 'forms' ), true ) ) {
        echo '<p><label>Catégorie d’e-mail</label><select name="marketing_item[category]">';
        foreach ( $this->marketing_categories() as $key => $label ) {
          echo '<option value="' . esc_attr( $key ) . '" ' . selected( isset( $item['category'] ) ? $item['category'] : 'marketing', $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></p>';
      }
      if ( in_array( $entity, array( 'campaigns', 'templates' ), true ) ) {
        echo '<p><label>Objet</label><input type="text" name="marketing_item[subject]" value="' . esc_attr( isset( $item['subject'] ) ? $item['subject'] : '' ) . '"></p>';
      }
      if ( 'campaigns' === $entity ) {
        echo '<p><label>Date / heure planifiée</label><input type="datetime-local" name="marketing_item[planned_at]" value="' . esc_attr( isset( $item['planned_at'] ) ? str_replace( ' ', 'T', substr( $item['planned_at'], 0, 16 ) ) : '' ) . '"></p>';
      }
      if ( 'webhooks' === $entity ) {
        echo '<p><label>Direction</label><select name="marketing_item[direction]"><option value="outgoing" ' . selected( isset( $item['direction'] ) ? $item['direction'] : 'outgoing', 'outgoing', false ) . '>Émission</option><option value="incoming" ' . selected( isset( $item['direction'] ) ? $item['direction'] : '', 'incoming', false ) . '>Réception</option><option value="both" ' . selected( isset( $item['direction'] ) ? $item['direction'] : '', 'both', false ) . '>Les deux</option></select></p>';
        echo '<p><label>URL</label><input type="url" name="marketing_item[url]" value="' . esc_attr( isset( $item['url'] ) ? $item['url'] : '' ) . '"></p>';
        echo '<p><label>Secret</label><input type="text" name="marketing_item[secret]" value="' . esc_attr( isset( $item['secret'] ) ? $item['secret'] : '' ) . '"></p>';
        echo '<p><label>Événements (CSV)</label><input type="text" name="marketing_item[events]" value="' . esc_attr( isset( $item['events'] ) && is_array( $item['events'] ) ? implode( ',', $item['events'] ) : '' ) . '"></p>';
      }
      if ( 'scenarios' === $entity ) {
        echo '<p><label>Déclencheur</label><input type="text" name="marketing_item[trigger_type]" value="' . esc_attr( isset( $item['trigger_type'] ) ? $item['trigger_type'] : '' ) . '"></p>';
        echo '<p><label>Objectif</label><input type="text" name="marketing_item[goal]" value="' . esc_attr( isset( $item['goal'] ) ? $item['goal'] : '' ) . '"></p>';
      }
      if ( 'forms' === $entity ) {
        echo '<p><label>Slug</label><input type="text" name="marketing_item[slug]" value="' . esc_attr( isset( $item['slug'] ) ? $item['slug'] : '' ) . '"></p>';
        echo '<p><label>Listes liées (CSV)</label><input type="text" name="marketing_item[list_ids]" value="' . esc_attr( isset( $item['list_ids'] ) && is_array( $item['list_ids'] ) ? implode( ',', $item['list_ids'] ) : '' ) . '"></p>';
        echo '<p><label>Étiquettes liées (CSV)</label><input type="text" name="marketing_item[tag_ids]" value="' . esc_attr( isset( $item['tag_ids'] ) && is_array( $item['tag_ids'] ) ? implode( ',', $item['tag_ids'] ) : '' ) . '"></p>';
      }
      if ( 'fields' === $entity ) {
        echo '<p><label>Type de champ</label><select name="marketing_item[field_type]"><option value="text">Texte</option><option value="email">E-mail</option><option value="phone">Téléphone</option><option value="select">Liste</option><option value="textarea">Texte long</option></select></p>';
        echo '<p><label>Options (une ligne ou CSV)</label><input type="text" name="marketing_item[options]" value="' . esc_attr( isset( $item['options'] ) ? $item['options'] : '' ) . '"></p>';
      }
      echo '</div>';
      echo '<p><label>Description</label><textarea name="marketing_item[description]" rows="4">' . esc_textarea( isset( $item['description'] ) ? $item['description'] : '' ) . '</textarea></p>';
      if ( in_array( $entity, array( 'segments', 'scenarios' ), true ) ) {
        echo '<p><label>Règles / étapes</label><textarea name="marketing_item[rules]" rows="6">' . esc_textarea( isset( $item['rules'] ) ? $item['rules'] : '' ) . '</textarea></p>';
      }
      if ( in_array( $entity, array( 'templates', 'campaigns', 'forms' ), true ) ) {
        echo '<p><label>Contenu / introduction</label><textarea name="marketing_item[content]" rows="8">' . esc_textarea( isset( $item['content'] ) ? $item['content'] : '' ) . '</textarea></p>';
      }
      if ( 'forms' === $entity ) {
        echo '<p><label>Texte d’introduction</label><textarea name="marketing_item[intro_text]" rows="4">' . esc_textarea( isset( $item['intro_text'] ) ? $item['intro_text'] : '' ) . '</textarea></p>';
        echo '<p><label>Message de confirmation</label><textarea name="marketing_item[success_message]" rows="3">' . esc_textarea( isset( $item['success_message'] ) ? $item['success_message'] : '' ) . '</textarea></p>';
      }
      echo '<p><button type="submit" class="acdc-button acdc-button-primary">Enregistrer</button></p>';
      echo '</form></div>';
    }
    echo '<div class="acdc-panel"><table class="acdc-table"><thead><tr><th>Nom</th><th>Statut</th><th>Catégorie</th><th>Mise à jour</th><th>Actions</th></tr></thead><tbody>';
    foreach ( $items as $item ) {
      $edit_url = $this->portal_page_url( array( 'tab' => $tab, 'action' => 'edit', 'item_id' => isset( $item['id'] ) ? $item['id'] : '' ) );
      $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'acdc_marketing_delete_entity', 'marketing_entity' => $entity, 'item_id' => isset( $item['id'] ) ? $item['id'] : '' ), admin_url( 'admin-post.php' ) ), 'acdc_marketing_delete_entity' );
      echo '<tr>';
      echo '<td><strong>' . esc_html( isset( $item['name'] ) ? $item['name'] : '' ) . '</strong>';
      if ( 'forms' === $entity && ! empty( $item['public_token'] ) ) {
        echo '<br><small><a href="' . esc_url( add_query_arg( array( 'form' => rawurlencode( $item['public_token'] ) ), $this->get_marketing_public_base_url() ) ) . '" target="_blank" rel="noopener">Voir la page publique</a></small>';
      }
      echo '</td>';
      echo '<td>' . esc_html( isset( $item['status'] ) ? $item['status'] : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['category'] ) && isset( $this->marketing_categories()[ $item['category'] ] ) ? $this->marketing_categories()[ $item['category'] ] : '' ) . '</td>';
      echo '<td>' . esc_html( isset( $item['updated_at'] ) ? $item['updated_at'] : '' ) . '</td>';
      echo '<td><a class="acdc-button acdc-button-secondary" href="' . esc_url( $edit_url ) . '">Modifier</a> <a class="acdc-button acdc-button-secondary" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'Confirmer la suppression ?\')">Supprimer</a></td>';
      echo '</tr>';
    }
    if ( empty( $items ) ) { echo '<tr><td colspan="5">Aucun élément enregistré.</td></tr>'; }
    echo '</tbody></table></div>';
  }


  public function render_front_marketing_email_archive_tab() {
    $archive = $this->get_marketing_email_archive();
    $search  = isset( $_GET['archive_search'] ) ? sanitize_text_field( wp_unslash( $_GET['archive_search'] ) ) : '';
    $action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
    $archive_id = isset( $_GET['archive_id'] ) ? sanitize_text_field( wp_unslash( $_GET['archive_id'] ) ) : '';
    $sub_tab = isset( $_GET['archive_sub'] ) ? sanitize_key( wp_unslash( $_GET['archive_sub'] ) ) : 'destinataires';
    if ( ! in_array( $sub_tab, array( 'destinataires', 'acdc' ), true ) ) {
      $sub_tab = 'destinataires';
    }

    if ( '' !== $search ) {
      $archive = array_values( array_filter( $archive, function( $entry ) use ( $search ) {
        $haystack = strtolower( implode( ' ', array(
          isset( $entry['subject'] ) ? (string) $entry['subject'] : '',
          implode( ' ', isset( $entry['to'] ) && is_array( $entry['to'] ) ? $entry['to'] : array() ),
          isset( $entry['source_module'] ) ? (string) $entry['source_module'] : '',
          isset( $entry['source_action'] ) ? (string) $entry['source_action'] : '',
        ) ) );
        return false !== strpos( $haystack, strtolower( $search ) );
      } ) );
    }

    /* ACDC 3.25.290 — Sert à distinguer, dans l'archive des envois, ce qui est
       parti vers l'organisme lui-même : l'adresse vient donc de la fiche. */
    $acdc_email   = $this->acdc_org_identity()['email'];
    $archive_dest = array();
    $archive_acdc = array();
    foreach ( $archive as $entry ) {
      $to_list = isset( $entry['to'] ) && is_array( $entry['to'] ) ? $entry['to'] : array();
      if ( in_array( $acdc_email, $to_list, true ) ) {
        $archive_acdc[] = $entry;
      } else {
        $archive_dest[] = $entry;
      }
    }

    $this->render_marketing_header( 'Archive des e-mails', 'Archive centrale transversale de tous les e-mails envoyés par le plugin via wp_mail, avec consultation et renvoi depuis un point unique.' );

    echo '<div class="acdc-panel acdc-mb-18">';
    echo '<form method="get" class="acdc-filters">';
    echo '<input type="hidden" name="tab" value="marketing_email_archive">';
    echo '<input type="hidden" name="archive_sub" value="' . esc_attr( $sub_tab ) . '">' ;
    echo '<label><span>Recherche</span><input type="search" name="archive_search" value="' . esc_attr( $search ) . '" placeholder="Objet, destinataire, module"></label>';
    echo '<div class="acdc-actions-inline"><button type="submit" class="acdc-button acdc-button-secondary">Filtrer</button>' .
      '<a class="acdc-button acdc-button-soft" href="' . esc_url( $this->portal_page_url( array( 'tab' => 'marketing_email_archive', 'archive_sub' => $sub_tab ) ) ) . '">Réinitialiser</a></div>';
    echo '</form></div>';

    if ( 'view' === $action && $archive_id ) {
      $entry = $this->get_marketing_archive_entry( $archive_id );
      if ( $entry ) {
        echo '<div class="acdc-panel acdc-mb-18">';
        echo '<div class="acdc-inline-between"><div><h2 style="margin-top:0">Visualisation de l\'e-mail</h2><p>Consultez ici le contenu archivé exact et relancez l\'envoi si nécessaire.</p></div>';
        echo '<div class="acdc-inline-wrap">' .
          '<a class="acdc-button acdc-button-soft" href="' . esc_url( $this->portal_page_url( array( 'tab' => 'marketing_email_archive', 'archive_sub' => $sub_tab ) ) ) . '">Retour à l\'archive</a>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-flex;">';
        wp_nonce_field( 'acdc_marketing_resend_archived_email_' . $archive_id );
        echo '<input type="hidden" name="action" value="acdc_marketing_resend_archived_email">';
        echo '<input type="hidden" name="archive_id" value="' . esc_attr( $archive_id ) . '">' ;
        echo '<button type="submit" class="acdc-button acdc-button-primary">Renvoyer l\'e-mail</button>';
        echo '</form></div></div>';
        echo '<div class="acdc-contract-grid">';
        echo '<div class="acdc-contract-label">Date</div><div>' . esc_html( ! empty( $entry['sent_at'] ) ? mysql2date( 'd/m/Y H:i', $entry['sent_at'] ) : '—' ) . '</div>';
        echo '<div class="acdc-contract-label">Destinataire</div><div>' . esc_html( $this->get_archive_recipient_label( $entry ) ) . '</div>';
        echo '<div class="acdc-contract-label">E-mail destinataire</div><div>' . esc_html( ! empty( $entry['to'] ) ? implode( ', ', (array) $entry['to'] ) : '—' ) . '</div>';
        echo '<div class="acdc-contract-label">Objet</div><div>' . esc_html( ! empty( $entry['subject'] ) ? $entry['subject'] : '—' ) . '</div>';
        echo '<div class="acdc-contract-label">Source</div><div>' . esc_html( trim( ( ! empty( $entry['source_module'] ) ? $entry['source_module'] : 'plugin' ) . ' / ' . ( ! empty( $entry['source_action'] ) ? $entry['source_action'] : 'wp_mail' ), ' /' ) ) . '</div>';
        echo '<div class="acdc-contract-label">Statut</div><div>' . esc_html( $this->get_archive_email_status_label( $entry ) ) . '</div>';
        echo '</div>';
        echo '<div class="acdc-panel" style="margin-top:18px;"><h3 style="margin-top:0">Contenu archivé</h3><div style="background:#fff;border:1px solid #dce4ec;border-radius:10px;padding:18px;overflow:auto;">' . wp_kses_post( ! empty( $entry['body'] ) ? $entry['body'] : '<p>Aucun contenu disponible.</p>' ) . '</div></div>';
        echo '</div>';
      }
    }

    $url_dest = $this->portal_page_url( array( 'tab' => 'marketing_email_archive', 'archive_sub' => 'destinataires' ) );
    $url_acdc = $this->portal_page_url( array( 'tab' => 'marketing_email_archive', 'archive_sub' => 'acdc' ) );
    echo '<div class="acdc-subtabs" style="display:flex;gap:8px;margin-bottom:18px;">';
    echo '<a href="' . esc_url( $url_dest ) . '" class="acdc-button ' . ( 'destinataires' === $sub_tab ? 'acdc-button-primary' : 'acdc-button-soft' ) . '">Destinataires <span style="font-size:12px;opacity:.75;">(' . count( $archive_dest ) . ')</span></a>';
    echo '<a href="' . esc_url( $url_acdc ) . '" class="acdc-button ' . ( 'acdc' === $sub_tab ? 'acdc-button-primary' : 'acdc-button-soft' ) . '">ACDC <span style="font-size:12px;opacity:.75;">(' . count( $archive_acdc ) . ')</span></a>';
    echo '</div>';

    $current_archive = ( 'acdc' === $sub_tab ) ? $archive_acdc : $archive_dest;

    echo '<div class="acdc-panel">';
    echo '<h2 style="margin-top:0">' . ( 'acdc' === $sub_tab ? 'E-mails internes ACDC' : 'E-mails destinataires' ) . '</h2>';
    echo '<table class="acdc-table"><thead><tr><th>Date</th><th>Destinataire</th><th>Objet</th><th>E-mail</th><th>Source</th><th>Statut</th><th>Action</th></tr></thead><tbody>';
    if ( ! empty( $current_archive ) ) {
      foreach ( $current_archive as $entry ) {
        $view_url = $this->portal_page_url( array( 'tab' => 'marketing_email_archive', 'archive_sub' => $sub_tab, 'action' => 'view', 'archive_id' => isset( $entry['id'] ) ? $entry['id'] : '' ) );
        $recipient_label = $this->get_archive_recipient_label( $entry );
        $email_dest      = ! empty( $entry['to'] ) ? ( is_array( $entry['to'] ) ? $entry['to'][0] : (string) $entry['to'] ) : '—';
        $source_label    = trim( ( ! empty( $entry['source_module'] ) ? $entry['source_module'] : 'plugin' ) . ' / ' . ( ! empty( $entry['source_action'] ) ? $entry['source_action'] : 'wp_mail' ), ' /' );
        $status_label    = $this->get_archive_email_status_label( $entry );
        $del_archive_url = wp_nonce_url(
          $this->portal_page_url( array( 'trf_action' => 'delete_archive_email', 'archive_id' => esc_attr( isset( $entry['id'] ) ? $entry['id'] : '' ), 'archive_sub' => $sub_tab ) ),
          'acdc_delete_archive_email_' . ( isset( $entry['id'] ) ? $entry['id'] : '' )
        );
        echo '<tr>';
        echo '<td style="white-space:nowrap">' . esc_html( ! empty( $entry['sent_at'] ) ? mysql2date( 'd/m/Y H:i', $entry['sent_at'] ) : '—' ) . '</td>';
        echo '<td>' . esc_html( $recipient_label ) . '</td>';
        echo '<td>' . esc_html( ! empty( $entry['subject'] ) ? $entry['subject'] : '—' ) . '</td>';
        echo '<td style="font-size:13px">' . esc_html( $email_dest ) . '</td>';
        echo '<td style="font-size:12px;color:#6b7280">' . esc_html( $source_label ) . '</td>';
        echo '<td>' . esc_html( $status_label ) . '</td>';
        echo '<td class="acdc-actions-cell-icons"><div class="acdc-groups-actions-inline">'
          . '<a class="acdc-row-action-icon acdc-row-view-link" data-acdc-iconized="1" href="' . esc_url( $view_url ) . '" title="Visualiser" aria-label="Visualiser">' . $this->render_inline_icon( 'view', 25 ) . '<span class="acdc-action-hub-sr screen-reader-text">Visualiser</span></a>'
          . '<a class="acdc-row-action-icon acdc-row-delete-link" data-acdc-iconized="1" href="' . esc_url( $del_archive_url ) . '" title="Supprimer" aria-label="Supprimer" onclick="return confirm(\'Supprimer cet e-mail de l\\\'archive ?\')">' . $this->render_inline_icon( 'trash', 25 ) . '<span class="acdc-action-hub-sr screen-reader-text">Supprimer</span></a>'
          . '</div></td>';
        echo '</tr>';
      }
    } else {
      echo '<tr><td colspan="7">Aucun e-mail archivé pour le moment.</td></tr>';
    }
    echo '</tbody></table></div>';
  }


  /* ====================================================================
   *  ACDC 3.24.32 — Signatures e-mail (expéditeurs nommés)
   * ==================================================================== */

  private function render_front_marketing_signatures_tab() {
    $signatures = $this->get_marketing_store( 'signatures', array() );
    $action  = isset( $_GET['action'] )  ? sanitize_key( wp_unslash( $_GET['action'] ) )  : 'list';
    $sig_id  = isset( $_GET['sig_id'] )  ? sanitize_key( wp_unslash( $_GET['sig_id'] ) )  : '';
    $notice  = isset( $_GET['acdc_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['acdc_notice'] ) ) : '';

    $current = array();
    if ( in_array( $action, array( 'edit', 'view' ), true ) && $sig_id ) {
      foreach ( $signatures as $s ) {
        if ( isset( $s['id'] ) && $s['id'] === $sig_id ) { $current = $s; break; }
      }
    }

    $base_url = $this->portal_page_url( array( 'tab' => 'marketing_signatures' ) );

    $this->render_marketing_header(
      'Signatures e-mail',
      'Gérez les signatures personnalisées de chaque expéditeur. Chaque signature est injectée automatiquement dans les e-mails envoyés depuis le module.',
      array(
        array( 'label' => 'Créer une signature', 'url' => $this->portal_page_url( array( 'tab' => 'marketing_signatures', 'action' => 'new' ) ), 'primary' => true ),
      )
    );

    if ( $notice ) {
      $msg = 'sig_saved' === $notice ? 'Signature enregistrée.' : ( 'sig_deleted' === $notice ? 'Signature supprimée.' : '' );
      if ( $msg ) { echo '<div class="acdc-notice acdc-notice-success" style="margin-bottom:16px;">' . esc_html( $msg ) . '</div>'; }
    }

    // ---- Formulaire new / edit ----
    if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
      $v = function( $key, $default = '' ) use ( $current ) {
        return isset( $current[ $key ] ) ? (string) $current[ $key ] : $default;
      };
      $is_edit = 'edit' === $action && ! empty( $current );
      ?>
      <div class="acdc-panel acdc-needs-section" style="margin-bottom:18px;">
        <div class="acdc-needs-section-title"><?php echo esc_html( $is_edit ? 'Modifier une signature' : 'Créer une signature' ); ?></div>
        <form class="acdc-form acdc-needs-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <?php wp_nonce_field( 'acdc_marketing_save_signature' ); ?>
          <input type="hidden" name="action" value="acdc_marketing_save_signature">
          <input type="hidden" name="sig_id" value="<?php echo esc_attr( $v( 'id' ) ); ?>">
          <div class="acdc-grid-2cols">
            <p>
              <label>Identifiant unique *</label>
              <input type="text" name="sig[id]" value="<?php echo esc_attr( $v( 'id' ) ); ?>" required placeholder="ex : david" pattern="[a-z0-9_]+" title="Minuscules, chiffres et tirets bas uniquement" <?php echo $is_edit ? 'readonly' : ''; ?>>
              <span class="description">Minuscules sans espaces. Non modifiable après création.</span>
            </p>
            <p>
              <label>Nom affiché *</label>
              <input type="text" name="sig[display_name]" value="<?php echo esc_attr( $v( 'display_name' ) ); ?>" required placeholder="ex : Prénom Nom">
            </p>
            <p>
              <label>Titre / fonction</label>
              <input type="text" name="sig[job_title]" value="<?php echo esc_attr( $v( 'job_title' ) ); ?>" placeholder="ex : Formateur professionnel d'adultes certifié">
            </p>
            <p>
              <label>Adresse e-mail *</label>
              <input type="email" name="sig[email]" value="<?php echo esc_attr( $v( 'email' ) ); ?>" required placeholder="ex : prenom@votre-organisme.fr">
            </p>
            <p>
              <label>Téléphone</label>
              <input type="text" name="sig[phone]" value="<?php echo esc_attr( $v( 'phone' ) ); ?>" placeholder="ex : 06 00 00 00 00">
            </p>
            <p>
              <label>Nom de l'organisme</label>
              <input type="text" name="sig[company_name]" value="<?php echo esc_attr( $v( 'company_name', 'ACDC Formation' ) ); ?>">
            </p>
            <p>
              <label>Site web de l'organisme</label>
              <input type="url" name="sig[company_url]" value="<?php echo esc_attr( $v( 'company_url', 'https://acdc-formation.com' ) ); ?>">
            </p>
            <p>
              <label>Adresse postale</label>
              <input type="text" name="sig[address]" value="<?php echo esc_attr( $v( 'address', \ACDC\Support\OrgIdentity::addressLine( $this->acdc_org_identity(), ' - ' ) ) ); ?>">
            </p>
            <p>
              <label>URL de la photo (médiathèque WP)</label>
              <input type="url" name="sig[photo_url]" value="<?php echo esc_attr( $v( 'photo_url' ) ); ?>" placeholder="https://…/photo.jpg">
            </p>
            <p>
              <label>URL du logo organisme</label>
              <input type="url" name="sig[logo_url]" value="<?php echo esc_attr( $v( 'logo_url' ) ); ?>" placeholder="https://…/logo.png">
            </p>
            <p>
              <label>LinkedIn</label>
              <input type="url" name="sig[linkedin_url]" value="<?php echo esc_attr( $v( 'linkedin_url' ) ); ?>" placeholder="https://linkedin.com/in/…">
            </p>
            <p>
              <label>Facebook</label>
              <input type="url" name="sig[facebook_url]" value="<?php echo esc_attr( $v( 'facebook_url' ) ); ?>" placeholder="https://facebook.com/…">
            </p>
            <p>
              <label>Instagram</label>
              <input type="url" name="sig[instagram_url]" value="<?php echo esc_attr( $v( 'instagram_url' ) ); ?>" placeholder="https://instagram.com/…">
            </p>
            <p style="display:flex;align-items:center;gap:10px;grid-column:span 2;">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;">
                <input type="checkbox" name="sig[is_default]" value="1" <?php checked( ! empty( $current['is_default'] ) ); ?>>
                Signature par défaut
              </label>
              <span class="description" style="margin:0;">Utilisée si aucun expéditeur n'est précisé.</span>
            </p>
          </div>
          <?php if ( ! empty( $v( 'display_name' ) ) ) : ?>
          <div style="margin:18px 24px 0;padding:18px;background:var(--acdc-bg);border-radius:10px;border:1px solid var(--acdc-border);">
            <p style="margin:0 0 12px;font-size:12px;font-weight:700;text-transform:uppercase;color:var(--acdc-text-muted);letter-spacing:.05em;">Aperçu de la signature</p>
            <?php echo $this->render_marketing_signature_html( $current ); ?>
          </div>
          <?php endif; ?>
          <p class="acdc-actions-end-wrap" style="padding:18px 24px 0;">
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $base_url ); ?>">Annuler</a>
            <button type="submit" class="acdc-button acdc-button-primary"><?php echo $is_edit ? 'Modifier la signature' : 'Créer la signature'; ?></button>
          </p>
        </form>
      </div>
      <?php
      return;
    }

    // ---- Liste des signatures ----
    if ( empty( $signatures ) ) {
      echo '<div class="acdc-panel"><div class="acdc-empty-state" style="padding:72px 24px;text-align:center;">';
      echo '<p style="color:var(--acdc-text-muted);">Aucune signature créée. Commencez par créer la signature de David, puis celle d\'Ann-Cécile.</p></div></div>';
      return;
    }
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(460px,1fr));gap:18px;">';
    foreach ( $signatures as $sig ) {
      $id = isset( $sig['id'] ) ? $sig['id'] : '';
      $edit_url   = $this->portal_page_url( array( 'tab' => 'marketing_signatures', 'action' => 'edit', 'sig_id' => $id ) );
      $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_marketing_delete_signature&sig_id=' . rawurlencode( $id ) ), 'acdc_marketing_delete_signature_' . $id );
      echo '<div class="acdc-panel" style="position:relative;">';
      if ( ! empty( $sig['is_default'] ) ) {
        echo '<span style="position:absolute;top:14px;right:14px;background:#d6a353;color:#fff;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;letter-spacing:.04em;">Par défaut</span>';
      }
      echo $this->render_marketing_signature_html( $sig );
      echo '<div style="display:flex;gap:10px;margin-top:18px;padding-top:14px;border-top:1px solid var(--acdc-border);">';
      echo '<a class="acdc-button acdc-button-primary" href="' . esc_url( $edit_url ) . '">Modifier</a>';
      echo '<a class="acdc-button acdc-button-danger" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'Supprimer cette signature ?\')">Supprimer</a>';
      echo '</div>';
      echo '</div>';
    }
    echo '</div>';
  }

  /**
   * ACDC 3.24.32 — Génère le HTML tabulaire d'une signature e-mail.
   * Compatible e-mail (table layout, styles inline).
   * Utilisable en aperçu dans l'interface et en injection dans les mails.
   *
   * @param array $sig Données de la signature.
   * @return string HTML prêt à l'emploi.
   */
  public function render_marketing_signature_html( $sig ) {
    if ( empty( $sig ) ) { return ''; }
    $name      = isset( $sig['display_name'] )  ? esc_html( $sig['display_name'] )  : '';
    $title     = isset( $sig['job_title'] )      ? esc_html( $sig['job_title'] )      : '';
    $email     = isset( $sig['email'] )          ? $sig['email']                      : '';
    $phone     = isset( $sig['phone'] )          ? esc_html( $sig['phone'] )          : '';
    $company   = isset( $sig['company_name'] )   ? esc_html( $sig['company_name'] )   : '';
    $co_url    = isset( $sig['company_url'] )    ? esc_url( $sig['company_url'] )     : '';
    $address   = isset( $sig['address'] )        ? esc_html( $sig['address'] )        : '';
    $photo     = isset( $sig['photo_url'] )      ? esc_url( $sig['photo_url'] )       : '';
    $logo      = isset( $sig['logo_url'] )       ? esc_url( $sig['logo_url'] )        : '';
    $linkedin  = isset( $sig['linkedin_url'] )   ? esc_url( $sig['linkedin_url'] )    : '';
    $facebook  = isset( $sig['facebook_url'] )   ? esc_url( $sig['facebook_url'] )    : '';
    $instagram = isset( $sig['instagram_url'] )  ? esc_url( $sig['instagram_url'] )   : '';

    $out  = '<table cellpadding="0" cellspacing="0" border="0" style="font-family:Arial,sans-serif;font-size:14px;color:#222;max-width:520px;">';
    $out .= '<tr>';
    if ( $photo ) {
      $out .= '<td style="vertical-align:top;padding-right:18px;">';
      $out .= '<img src="' . $photo . '" alt="' . $name . '" width="90" height="90" style="width:90px;height:90px;object-fit:cover;border-radius:6px;display:block;">';
      $out .= '</td>';
    }
    $out .= '<td style="vertical-align:top;">';
    $out .= '<p style="margin:0 0 2px;font-size:15px;font-weight:700;color:#0f2c52;">' . $name . '</p>';
    if ( $title )   { $out .= '<p style="margin:0 0 6px;font-size:13px;color:#4b5d76;">' . $title . '</p>'; }
    if ( $company ) {
      $co_label = '<strong style="color:#d6a353;font-size:14px;">' . $company . '</strong>';
      $out .= '<p style="margin:0 0 8px;font-size:14px;">' . $co_label . '</p>';
    }
    $out .= '<div style="border-top:1px solid #ddd;margin:8px 0;"></div>';
    if ( $address ) { $out .= '<p style="margin:0 0 4px;font-size:12px;color:#555;">' . $address . '</p>'; }
    if ( $phone )   { $out .= '<p style="margin:0 0 4px;font-size:12px;"><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '" style="color:#0f2c52;text-decoration:none;">' . $phone . '</a></p>'; }
    if ( $email )   { $out .= '<p style="margin:0 0 8px;font-size:12px;"><a href="mailto:' . esc_attr( $email ) . '" style="color:#0f2c52;text-decoration:none;">' . esc_html( $email ) . '</a></p>'; }
    // Icônes réseaux sociaux (SVG inline — pas de dépendance externe)
    $icons = array();
    if ( $linkedin )  { $icons[] = '<a href="' . $linkedin  . '" style="display:inline-block;width:28px;height:28px;border-radius:50%;background:#0077b5;text-align:center;line-height:28px;text-decoration:none;color:#fff;font-size:14px;font-weight:900;margin-right:4px;" title="LinkedIn">in</a>'; }
    if ( $facebook )  { $icons[] = '<a href="' . $facebook  . '" style="display:inline-block;width:28px;height:28px;border-radius:50%;background:#1877f2;text-align:center;line-height:28px;text-decoration:none;color:#fff;font-size:14px;font-weight:900;margin-right:4px;" title="Facebook">f</a>'; }
    if ( $instagram ) { $icons[] = '<a href="' . $instagram . '" style="display:inline-block;width:28px;height:28px;border-radius:50%;background:radial-gradient(circle at 30% 107%,#fdf497 0%,#fdf497 5%,#fd5949 45%,#d6249f 60%,#285AEB 90%);text-align:center;line-height:28px;text-decoration:none;color:#fff;font-size:13px;font-weight:900;margin-right:4px;" title="Instagram">&#9711;</a>'; }
    if ( ! empty( $icons ) ) { $out .= '<p style="margin:6px 0 0;">' . implode( '', $icons ) . '</p>'; }
    $out .= '</td>';
    if ( $logo ) {
      $out .= '</tr><tr><td colspan="2" style="padding-top:14px;">';
      $out .= '<img src="' . $logo . '" alt="' . $company . '" height="48" style="height:48px;width:auto;display:block;">';
      $out .= '</td>';
    }
    $out .= '</tr></table>';
    return $out;
  }

}
