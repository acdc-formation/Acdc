<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Kernel_Modular_Render_Trait {

  protected function acdc_render_repeatable_simple_sections( $args = array() ) {
    $defaults = array(
      'container_id'       => 'acdc-repeatable-sections',
      'button_id'          => 'acdc-add-repeatable-section',
      'sections'           => array(),
      'input_prefix'       => 'items',
      'title_label'        => 'Titre de la section',
      'content_label'      => 'Contenu de la section',
      'title_required'     => false,
      'content_required'   => false,
      'add_button_label'   => 'Ajouter une section',
      'description'        => '',
      'min_items'          => 0,
      'panel_class'        => '',
      'button_class'       => 'acdc-button',
    );
    $args = wp_parse_args( $args, $defaults );

    $container_id = sanitize_html_class( $args['container_id'] );
    $button_id    = sanitize_html_class( $args['button_id'] );
    $sections     = is_array( $args['sections'] ) ? array_values( $args['sections'] ) : array();
    $prefix       = (string) $args['input_prefix'];
    $min_items    = max( 0, absint( $args['min_items'] ) );

    echo '<div id="' . esc_attr( $container_id ) . '" class="acdc-repeatable-sections ' . esc_attr( trim( (string) $args['panel_class'] ) ) . '"';
    echo ' data-acdc-repeatable-sections="1"';
    echo ' data-acdc-input-prefix="' . esc_attr( $prefix ) . '"';
    echo ' data-acdc-title-label="' . esc_attr( (string) $args['title_label'] ) . '"';
    echo ' data-acdc-content-label="' . esc_attr( (string) $args['content_label'] ) . '"';
    echo ' data-acdc-title-required="' . ( ! empty( $args['title_required'] ) ? '1' : '0' ) . '"';
    echo ' data-acdc-content-required="' . ( ! empty( $args['content_required'] ) ? '1' : '0' ) . '"';
    echo ' data-acdc-min-items="' . esc_attr( (string) $min_items ) . '"';
    echo '>';

    foreach ( $sections as $index => $section ) {
      $title   = isset( $section['title'] ) ? (string) $section['title'] : '';
      $content = isset( $section['content'] ) ? (string) $section['content'] : '';
      $this->acdc_render_repeatable_simple_section_card( $prefix, (int) $index, $title, $content, (string) $args['title_label'], (string) $args['content_label'], ! empty( $args['title_required'] ), ! empty( $args['content_required'] ) );
    }

    echo '</div>';
    echo '<p><button type="button" id="' . esc_attr( $button_id ) . '" class="' . esc_attr( trim( (string) $args['button_class'] ) ) . '" data-acdc-repeatable-add="' . esc_attr( $container_id ) . '">' . esc_html( (string) $args['add_button_label'] ) . '</button></p>';
    if ( '' !== (string) $args['description'] ) {
      echo '<p class="acdc-help">' . esc_html( (string) $args['description'] ) . '</p>';
    }
  }

  protected function acdc_render_repeatable_simple_section_card( $prefix, $index, $title, $content, $title_label = 'Titre de la section', $content_label = 'Contenu de la section', $title_required = false, $content_required = false ) {
    echo '<div class="acdc-contract-section-card" data-acdc-repeatable-card="1">';
    echo '<div class="acdc-contract-section-head">';
    echo '<span>#' . esc_html( (string) ( $index + 1 ) ) . ' Section</span>';
    echo '<button type="button" class="acdc-contract-remove-section" data-acdc-repeatable-remove="1">Supprimer</button>';
    echo '</div>';
    echo '<div class="acdc-contract-section-body">';
    echo '<div class="acdc-contract-grid">';
    echo '<div class="acdc-contract-label">' . esc_html( (string) $this->acdc_with_required_suffix( $title_label, $title_required ) ) . '</div>';
    echo '<div><input type="text" data-acdc-field-key="title" name="' . esc_attr( $prefix ) . '[' . (int) $index . '][title]" value="' . esc_attr( $title ) . '"' . ( $title_required ? ' required' : '' ) . '></div>';
    echo '</div>';
    echo '<div class="acdc-contract-grid">';
    echo '<div class="acdc-contract-label">' . esc_html( (string) $this->acdc_with_required_suffix( $content_label, $content_required ) ) . '</div>';
    echo '<div><textarea data-acdc-field-key="content" name="' . esc_attr( $prefix ) . '[' . (int) $index . '][content]" rows="4"' . ( $content_required ? ' required' : '' ) . '>' . esc_textarea( $content ) . '</textarea></div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
  }

  protected function acdc_with_required_suffix( $label, $required ) {
    if ( ! $required ) {
      return $label;
    }

    return $label . ' *';
  }
}
