<?php
/**
 * ACDC Learners / Contacts — ACDC_Learners_Contacts_Core_Trait.
 *
 * Extraction micro-incrémentale du sous-bloc métier :
 * apprenants + contacts liés.
 *
 * @since 3.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Learners_Contacts_Core_Trait {


  private function get_contacts() {
    global $wpdb;
    $sql = "SELECT c.*, e.name AS company_name
        FROM {$this->contact_table} c
        LEFT JOIN {$this->company_table} e ON e.id = c.company_id
        ORDER BY c.last_name ASC, c.first_name ASC";
    return $wpdb->get_results( $sql );
  }


  private function get_contact( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->contact_table} WHERE id = %d", $id ) );
  }


  private function get_learners( $search = '' ) {
    global $wpdb;
    $where = '';
    if ( '' !== trim( (string) $search ) ) {
      $like = '%' . $wpdb->esc_like( trim( (string) $search ) ) . '%';
      $where = $wpdb->prepare( "WHERE a.first_name LIKE %s OR a.usage_last_name LIKE %s OR a.email LIKE %s OR a.phone LIKE %s OR a.city LIKE %s", $like, $like, $like, $like, $like );
    }
    $sql = "SELECT a.*, e.name AS company_name, s.title AS session_title,
            p.first_name AS prospect_first_name, p.last_name AS prospect_last_name, p.company_name AS prospect_company_name
        FROM {$this->learner_table} a
        LEFT JOIN {$this->company_table} e ON e.id = a.company_id
        LEFT JOIN {$this->session_table} s ON s.id = a.session_id
        LEFT JOIN {$this->prospect_table} p ON p.id = a.prospect_id
        {$where}
        ORDER BY a.created_at DESC, a.id DESC";
    return $wpdb->get_results( $sql );
  }


  private function get_learner( $id ) {
    global $wpdb;
    return $wpdb->get_row(
      $wpdb->prepare(
        "SELECT a.*, e.name AS company_name, s.title AS session_title,
            p.first_name AS prospect_first_name, p.last_name AS prospect_last_name, p.company_name AS prospect_company_name
         FROM {$this->learner_table} a
         LEFT JOIN {$this->company_table} e ON e.id = a.company_id
         LEFT JOIN {$this->session_table} s ON s.id = a.session_id
         LEFT JOIN {$this->prospect_table} p ON p.id = a.prospect_id
         WHERE a.id = %d",
        $id
      )
    );
  }


  private function get_related_contacts( $company_id ) {
    global $wpdb;
    return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->contact_table} WHERE company_id = %d ORDER BY last_name ASC, first_name ASC", $company_id ) );
  }


  private function get_learner_gender_options() {
    return array(
      '' => 'Choisir une option',
      'Femme' => 'Femme',
      'Homme' => 'Homme',
      'Autre' => 'Autre',
      'Non précisé' => 'Non précisé',
    );
  }


  private function get_learner_yes_no_options() {
    return array(
      '' => 'Choisir une option',
      'Oui' => 'Oui',
      'Non' => 'Non',
    );
  }


  private function get_learner_socio_options() {
    return array(
      '' => 'Choisir une option',
      'Salarié' => 'Salarié',
      'Apprenti' => 'Apprenti',
      'Dirigeant' => 'Dirigeant',
      'Indépendant' => 'Indépendant',
      'Particulier' => 'Particulier',
      'Demandeur d’emploi' => 'Demandeur d’emploi',
      'Étudiant' => 'Étudiant',
      'Autre' => 'Autre',
    );
  }


  private function get_learner_education_options() {
    return array(
      '' => 'Choisir une option',
      'Sans diplôme' => 'Sans diplôme',
      'CAP / BEP' => 'CAP / BEP',
      'Baccalauréat' => 'Baccalauréat',
      'Bac +2' => 'Bac +2',
      'Bac +3/4' => 'Bac +3/4',
      'Bac +5 et plus' => 'Bac +5 et plus',
    );
  }

}
