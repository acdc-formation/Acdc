<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'acdc_of_get_variables_registry' ) ) {
    function acdc_of_get_variables_registry() {
        $registry = array(
            'apprenant' => array(
                'label' => 'Apprenant',
                'variables' => array(
                    'apprenant_prenom' => array('label' => 'Prénom', 'field' => 'first_name', 'object' => 'apprenant'),
                    'apprenant_nom' => array('label' => 'Nom d\'usage', 'field' => 'usage_last_name', 'object' => 'apprenant', 'fallback_fields' => array('last_name')),
                    'apprenant_nom_naissance' => array('label' => 'Nom de naissance', 'field' => 'last_name', 'object' => 'apprenant'),
                    'apprenant_nom_complet' => array('label' => 'Nom complet', 'object' => 'apprenant', 'callback' => 'acdc_of_variable_apprenant_nom_complet'),
                    'apprenant_email' => array('label' => 'E-mail', 'field' => 'email', 'object' => 'apprenant'),
                    'apprenant_telephone' => array('label' => 'Téléphone', 'field' => 'phone', 'object' => 'apprenant'),
                    'apprenant_categorie_socioprofessionnelle' => array('label' => 'Catégorie socioprofessionnelle', 'field' => 'socio_category', 'object' => 'apprenant'),
                    'apprenant_entreprise' => array('label' => 'Entreprise', 'field' => 'company_name', 'object' => 'apprenant'),
                    'apprenant_fonction' => array('label' => 'Fonction', 'field' => 'job_title', 'object' => 'apprenant'),
                ),
            ),
            'prospect' => array(
                'label' => 'Prospect',
                'variables' => array(
                    'prospect_entreprise' => array('label' => 'Entreprise', 'field' => 'company_name', 'object' => 'prospect'),
                    'prospect_siret' => array('label' => 'SIRET', 'field' => 'siret', 'object' => 'prospect'),
                    'prospect_code_naf' => array('label' => 'Code NAF', 'field' => 'naf_code', 'object' => 'prospect'),
                    'prospect_adresse' => array('label' => 'Adresse', 'field' => 'address', 'object' => 'prospect'),
                    'prospect_code_postal' => array('label' => 'Code postal', 'field' => 'postal_code', 'object' => 'prospect'),
                    'prospect_ville' => array('label' => 'Ville', 'field' => 'city', 'object' => 'prospect'),
                    'prospect_signataire_prenom' => array('label' => 'Prénom du signataire', 'field' => 'signer_first_name', 'object' => 'prospect'),
                    'prospect_signataire_nom' => array('label' => 'Nom du signataire', 'field' => 'signer_last_name', 'object' => 'prospect'),
                    'prospect_signataire_nom_complet' => array('label' => 'Nom complet du signataire', 'object' => 'prospect', 'callback' => 'acdc_of_variable_prospect_signataire_nom_complet'),
                    'prospect_signataire_fonction' => array('label' => 'Qualité du signataire', 'field' => 'signer_role', 'object' => 'prospect'),
                    'prospect_signataire_email' => array('label' => 'E-mail du signataire', 'field' => 'signer_email', 'object' => 'prospect'),
                    'prospect_telephone_entreprise' => array('label' => 'Téléphone de l\'entreprise', 'field' => 'company_phone', 'object' => 'prospect'),
                    'prospect_signataire_telephone' => array('label' => 'Téléphone du signataire', 'field' => 'signer_phone', 'object' => 'prospect'),
                    'prospect_formation_souhaitee' => array('label' => 'Formation souhaitée', 'field' => 'desired_training', 'object' => 'prospect'),
                    'prospect_commentaire' => array('label' => 'Commentaire', 'field' => 'comment_text', 'object' => 'prospect', 'fallback_fields' => array('comment')),
                    'prospect_source' => array('label' => 'Source', 'field' => 'source', 'object' => 'prospect'),
                    'prospect_statut' => array('label' => 'Statut', 'field' => 'status', 'object' => 'prospect'),
                    'prospect_url' => array('label' => 'Lien de consultation', 'field' => 'view_url', 'object' => 'prospect'),
                    'prospect_edit_url' => array('label' => 'Lien de modification', 'field' => 'edit_url', 'object' => 'prospect'),
                ),
            ),
            'entreprise' => array(
                'label' => 'Entreprise',
                'variables' => array(
                    'entreprise_nom' => array('label' => 'Nom', 'field' => 'name', 'object' => 'entreprise', 'fallback_fields' => array('company_name')),
                    'entreprise_siret' => array('label' => 'SIRET', 'field' => 'siret', 'object' => 'entreprise'),
                    'entreprise_code_naf' => array('label' => 'Code NAF', 'field' => 'naf_code', 'object' => 'entreprise'),
                    'entreprise_adresse' => array('label' => 'Adresse', 'field' => 'address', 'object' => 'entreprise'),
                    'entreprise_ville' => array('label' => 'Ville', 'field' => 'city', 'object' => 'entreprise'),
                    'entreprise_code_postal' => array('label' => 'Code postal', 'field' => 'postal_code', 'object' => 'entreprise'),
                    'entreprise_telephone' => array('label' => 'Téléphone', 'field' => 'phone', 'object' => 'entreprise'),
                    'entreprise_email' => array('label' => 'E-mail', 'field' => 'email', 'object' => 'entreprise'),
                ),
            ),
            'formation' => array(
                'label' => 'Formation',
                'variables' => array(
                    'formation_intitule' => array('label' => 'Intitulé', 'field' => 'title', 'object' => 'formation'),
                    'formation_duree' => array('label' => 'Durée', 'field' => 'duration', 'object' => 'formation'),
                    'formation_prix' => array('label' => 'Prix', 'field' => 'price', 'object' => 'formation', 'fallback_fields' => array('price_ht')),
                    'formation_objectif' => array('label' => 'Objectif', 'field' => 'objective', 'object' => 'formation', 'fallback_fields' => array('objectives')),
                    'formation_programme' => array('label' => 'Programme', 'field' => 'program', 'object' => 'formation', 'fallback_fields' => array('program_file_url')),
                ),
            ),
            'session' => array(
                'label' => 'Session',
                'variables' => array(
                    'session_date_debut' => array('label' => 'Date de début', 'field' => 'start_date', 'object' => 'session'),
                    'session_date_fin' => array('label' => 'Date de fin', 'field' => 'end_date', 'object' => 'session'),
                    'session_lieu' => array('label' => 'Lieu', 'field' => 'location', 'object' => 'session'),
                    'session_formateur' => array('label' => 'Formateur', 'field' => 'trainer_name', 'object' => 'session'),
                    'session_format' => array('label' => 'Format', 'field' => 'format', 'object' => 'session', 'fallback_fields' => array('modality')),
                ),
            ),
            'convention_contrat' => array(
                'label' => 'Convention / Contrat',
                'variables' => array(
                    'commanditaire_nom' => array('label' => 'Nom du commanditaire', 'field' => 'commanditaire_nom', 'object' => 'convention_contrat'),
                    'commanditaire_adresse' => array('label' => 'Adresse du commanditaire', 'field' => 'commanditaire_adresse', 'object' => 'convention_contrat'),
                    'commanditaire_code_postal' => array('label' => 'Code postal du commanditaire', 'field' => 'commanditaire_code_postal', 'object' => 'convention_contrat'),
                    'commanditaire_ville' => array('label' => 'Ville du commanditaire', 'field' => 'commanditaire_ville', 'object' => 'convention_contrat'),
                    'commanditaire_identification' => array('label' => 'SIRET / identification du commanditaire', 'field' => 'commanditaire_identification', 'object' => 'convention_contrat'),
                    'commanditaire_representant' => array('label' => 'Représentant du commanditaire', 'field' => 'commanditaire_representant', 'object' => 'convention_contrat'),
                    'commanditaire_qualite' => array('label' => 'Qualité du commanditaire', 'field' => 'commanditaire_qualite', 'object' => 'convention_contrat'),
                    'apprenants_count' => array('label' => 'Nombre d’apprenants', 'field' => 'apprenants_count', 'object' => 'convention_contrat'),
                    'apprenants_list' => array('label' => 'Liste des apprenants', 'field' => 'apprenants_list', 'object' => 'convention_contrat'),
                    'contract_vat_rate' => array('label' => 'Taux de TVA', 'field' => 'contract_vat_rate', 'object' => 'convention_contrat'),
                    'contract_price_ht' => array('label' => 'Prix HT', 'field' => 'contract_price_ht', 'object' => 'convention_contrat'),
                    'contract_price_ttc' => array('label' => 'Prix TTC', 'field' => 'contract_price_ttc', 'object' => 'convention_contrat'),
                    'contract_total_general' => array('label' => 'Total général', 'field' => 'contract_total_general', 'object' => 'convention_contrat'),
                    'contract_deposit_amount_ht' => array('label' => 'Acompte HT', 'field' => 'contract_deposit_amount_ht', 'object' => 'convention_contrat'),
                    'contract_transport_fees_amount_ht' => array('label' => 'Frais de transport HT', 'field' => 'contract_transport_fees_amount_ht', 'object' => 'convention_contrat'),
                    'contract_meal_fees_amount_ht' => array('label' => 'Frais de restauration / hébergement HT', 'field' => 'contract_meal_fees_amount_ht', 'object' => 'convention_contrat'),
                    'contract_signed_city_date' => array('label' => 'Ville et date de signature', 'field' => 'contract_signed_city_date', 'object' => 'convention_contrat'),
                ),
            ),
            'formateur' => array(
                'label' => 'Formateur',
                'variables' => array(
                    'formateur_prenom' => array('label' => 'Prénom', 'field' => 'first_name', 'object' => 'formateur'),
                    'formateur_nom' => array('label' => 'Nom', 'field' => 'last_name', 'object' => 'formateur'),
                    'formateur_email' => array('label' => 'E-mail', 'field' => 'email', 'object' => 'formateur'),
                    'formateur_telephone' => array('label' => 'Téléphone', 'field' => 'phone', 'object' => 'formateur'),
                ),
            ),
            'financeur' => array(
                'label' => 'Financeur',
                'variables' => array(
                    'financeur_nom' => array('label' => 'Nom', 'field' => 'name', 'object' => 'financeur'),
                    'financeur_contact' => array('label' => 'Contact', 'field' => 'contact_name', 'object' => 'financeur'),
                    'financeur_email' => array('label' => 'E-mail', 'field' => 'email', 'object' => 'financeur'),
                ),
            ),
        );
        return apply_filters( 'acdc_of_variables_registry', $registry );
    }
}

if ( ! function_exists( 'acdc_of_get_flat_variables_registry' ) ) {
    function acdc_of_get_flat_variables_registry() {
        $flat = array();
        foreach ( acdc_of_get_variables_registry() as $object_key => $group ) {
            if ( empty( $group['variables'] ) || ! is_array( $group['variables'] ) ) {
                continue;
            }
            foreach ( $group['variables'] as $variable => $definition ) {
                if ( ! is_array( $definition ) ) {
                    $definition = array();
                }
                $definition['object_key'] = $object_key;
                $flat[ $variable ] = $definition;
            }
        }
        return $flat;
    }
}

if ( ! function_exists( 'acdc_of_variable_read_context_value' ) ) {
    function acdc_of_variable_read_context_value( $context, $object, $field ) {
        if ( ! is_array( $context ) || '' === (string) $field ) {
            return null;
        }
        if ( isset( $context[ $field ] ) && '' !== (string) $context[ $field ] ) {
            return $context[ $field ];
        }
        if ( $object && isset( $context[ $object ] ) && is_array( $context[ $object ] ) && isset( $context[ $object ][ $field ] ) && '' !== (string) $context[ $object ][ $field ] ) {
            return $context[ $object ][ $field ];
        }
        return null;
    }
}

if ( ! function_exists( 'acdc_of_variable_apprenant_nom_complet' ) ) {
    function acdc_of_variable_apprenant_nom_complet( $context ) {
        $first = (string) acdc_of_variable_read_context_value( $context, 'apprenant', 'first_name' );
        $last  = (string) acdc_of_variable_read_context_value( $context, 'apprenant', 'usage_last_name' );
        if ( '' === trim( $last ) ) {
            $last = (string) acdc_of_variable_read_context_value( $context, 'apprenant', 'last_name' );
        }
        return trim( $first . ' ' . $last );
    }
}

if ( ! function_exists( 'acdc_of_variable_prospect_signataire_nom_complet' ) ) {
    function acdc_of_variable_prospect_signataire_nom_complet( $context ) {
        $first = (string) acdc_of_variable_read_context_value( $context, 'prospect', 'signer_first_name' );
        $last  = (string) acdc_of_variable_read_context_value( $context, 'prospect', 'signer_last_name' );
        return trim( $first . ' ' . $last );
    }
}

if ( ! function_exists( 'acdc_of_resolve_variable_value' ) ) {
    function acdc_of_resolve_variable_value( $variable, $context = array() ) {
        $registry = acdc_of_get_flat_variables_registry();
        if ( empty( $registry[ $variable ] ) ) {
            return null;
        }
        $definition = $registry[ $variable ];
        if ( ! empty( $definition['callback'] ) && is_callable( $definition['callback'] ) ) {
            $value = call_user_func( $definition['callback'], $context, $definition, $variable );
            return is_scalar( $value ) ? (string) $value : null;
        }
        $object = ! empty( $definition['object'] ) ? (string) $definition['object'] : '';
        $field  = ! empty( $definition['field'] ) ? (string) $definition['field'] : '';
        $value  = acdc_of_variable_read_context_value( $context, $object, $field );
        if ( null !== $value && '' !== (string) $value ) {
            return is_scalar( $value ) ? (string) $value : null;
        }
        if ( ! empty( $definition['fallback_fields'] ) && is_array( $definition['fallback_fields'] ) ) {
            foreach ( $definition['fallback_fields'] as $fallback_field ) {
                $value = acdc_of_variable_read_context_value( $context, $object, (string) $fallback_field );
                if ( null !== $value && '' !== (string) $value ) {
                    return is_scalar( $value ) ? (string) $value : null;
                }
            }
        }
        return null;
    }
}

if ( ! function_exists( 'acdc_replace_variables' ) ) {
    function acdc_replace_variables( $text, $context = array() ) {
        $text = (string) $text;
        if ( '' === $text || false === strpos( $text, '{{' ) ) {
            return $text;
        }
        return preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            function ( $matches ) use ( $context ) {
                $variable = isset( $matches[1] ) ? sanitize_key( $matches[1] ) : '';
                if ( '' === $variable ) {
                    return $matches[0];
                }
                $value = acdc_of_resolve_variable_value( $variable, $context );
                if ( null === $value || '' === $value ) {
                    return $matches[0];
                }
                return wp_check_invalid_utf8( (string) $value );
            },
            $text
        );
    }
}
