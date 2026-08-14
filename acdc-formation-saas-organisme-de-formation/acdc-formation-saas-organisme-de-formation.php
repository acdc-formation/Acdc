<?php
/**
 * Plugin Name: ACDC Formation SAAS Organisme de formation
 * Plugin URI: https://acdc-formation.com/
 * Description: Espace de gestion frontal sécurisé pour organisme de formation, réécrit sur base (dernière version du plugin : 3.20.105) avec module UI/Design système : réglage avancé des icônes d’action, taille, couleurs, espacements et choix des pictogrammes.
 * Version: 3.25.266
 * Requires at least: 6.2
 * Requires PHP: 8.2
 * Author: ACDC Formation
 * Text Domain: acdc-formation-saas
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ACDC_OF_SAAS_VERSION', '3.25.266' );
define( 'ACDC_OF_SAAS_FILE', __FILE__ );
define( 'ACDC_OF_SAAS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACDC_OF_SAAS_URL', plugin_dir_url( __FILE__ ) );

/* Bibliothèque de logique pure et testable (namespace ACDC\, couverte par PHPUnit).
   Autoload Composer si présent, sinon autoloader PSR-4 minimal (aucune dépendance
   à `composer install` en production). */
if ( file_exists( ACDC_OF_SAAS_DIR . 'vendor/autoload.php' ) ) {
	require_once ACDC_OF_SAAS_DIR . 'vendor/autoload.php';
}
spl_autoload_register(
	function ( $class ) {
		if ( 0 !== strpos( $class, 'ACDC\\' ) ) {
			return;
		}
		$file = ACDC_OF_SAAS_DIR . 'src/' . str_replace( '\\', '/', substr( $class, 5 ) ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/* C08 (audit 3.25.90) — Chargement effectif des traductions. Le text domain et 238
   appels __() existaient déjà mais aucune traduction n'était chargée. Accroché sur
   init (recommandation WordPress 6.7+). Sans effet visible en usage français. */
add_action( 'init', 'acdc_of_saas_load_textdomain' );
function acdc_of_saas_load_textdomain() {
    load_plugin_textdomain( 'acdc-formation-saas', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

if ( is_admin() ) {
    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }
    if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
        define( 'DONOTCACHEOBJECT', true );
    }
    if ( ! defined( 'DONOTCACHEDB' ) ) {
        define( 'DONOTCACHEDB', true );
    }
}

if ( ! function_exists( 'acdc_of_saas_purge_all_caches' ) ) {
    function acdc_of_saas_purge_all_caches() {
        static $done = false;
        if ( $done ) {
            return;
        }
        $done = true;

        /* Perf : NE PAS vider tout l'object cache (wp_cache_flush = FLUSHALL Redis/Memcached
           partagé) à chaque écriture — effondrait le taux de hit de tout le site. Le plugin
           gère ses propres transients au fil de l'eau ; seules les caches de page HTML publiques
           sont purgées ci-dessous. */

        if ( function_exists( 'do_action' ) ) {
            do_action( 'litespeed_purge_all' );
            do_action( 'rocket_clean_domain' );
            do_action( 'wpfc_clear_all_cache' );
            do_action( 'w3tc_flush_all' );
            do_action( 'autoptimize_action_cachepurged' );
        }
    }
}

if ( ! function_exists( 'acdc_of_saas_maybe_disable_cache_headers' ) ) {
    function acdc_of_saas_maybe_disable_cache_headers() {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

        if ( ( is_admin() && 0 === strpos( $page, 'acdc-of' ) ) || '' !== $tab ) {
            nocache_headers();
        }
    }
}
add_action( 'send_headers', 'acdc_of_saas_maybe_disable_cache_headers', 1 );

if ( ! function_exists( 'acdc_of_saas_send_security_headers' ) ) {
    /**
     * Durcissement HTTP des pages publiques de document (signature ?sig=, émargement
     * ?acdc_emarg=) : anti-clickjacking, anti MIME-sniffing, pas de fuite de référent.
     * HSTS opt-in via la constante ACDC_ENABLE_HSTS (HTTPS uniquement).
     */
    function acdc_of_saas_send_security_headers() {
        if ( headers_sent() ) {
            return;
        }
        if ( ! isset( $_GET['sig'] ) && ! isset( $_GET['acdc_emarg'] ) ) {
            return;
        }
        if ( ! class_exists( '\\ACDC\\Support\\SecurityHeaders' ) ) {
            return;
        }
        $enable_hsts = defined( 'ACDC_ENABLE_HSTS' ) && ACDC_ENABLE_HSTS;
        $headers     = \ACDC\Support\SecurityHeaders::forPublicDocument( is_ssl(), $enable_hsts );
        /** Permet d'ajuster/désactiver les en-têtes de sécurité des pages publiques. */
        $headers = apply_filters( 'acdc_of_saas_public_security_headers', $headers );
        foreach ( (array) $headers as $name => $value ) {
            if ( '' !== (string) $value ) {
                header( $name . ': ' . $value );
            }
        }
    }
}
add_action( 'send_headers', 'acdc_of_saas_send_security_headers', 1 );

if ( ! function_exists( 'acdc_of_saas_maybe_purge_after_plugin_request' ) ) {
    function acdc_of_saas_maybe_purge_after_plugin_request() {
        $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
        if ( '' === $action || 0 !== strpos( $action, 'acdc_' ) ) {
            return;
        }

        $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
        if ( in_array( $method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
            acdc_of_saas_purge_all_caches();
        }
    }
}
add_action( 'shutdown', 'acdc_of_saas_maybe_purge_after_plugin_request', 1 );


if ( ! function_exists( 'acdc_of_saas_store_boot_error' ) ) {
    function acdc_of_saas_store_boot_error( $message ) {
        if ( function_exists( 'update_option' ) ) {
            update_option( 'acdc_of_saas_boot_error', wp_strip_all_tags( (string) $message ), false );
        }
        if ( function_exists( 'error_log' ) ) {
            error_log( '[ACDC SAAS OF] ' . $message );
        }
    }
}

if ( ! function_exists( 'acdc_of_saas_render_boot_notice' ) ) {
    function acdc_of_saas_render_boot_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $message = get_option( 'acdc_of_saas_boot_error' );
        if ( ! $message ) {
            return;
        }
        echo '<div class="notice notice-error"><p><strong>ACDC SAAS OF</strong> — erreur de chargement : ' . esc_html( $message ) . '</p></div>';
    }
}
add_action( 'admin_notices', 'acdc_of_saas_render_boot_notice' );

if ( function_exists( 'delete_option' ) ) {
    delete_option( 'acdc_of_saas_boot_error' );
}

if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/variables-registry.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/variables-registry.php';
} else {
    acdc_of_saas_store_boot_error( 'Module variables introuvable : includes/variables-registry.php.' );
    return;
}


if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-auth-portal.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-auth-portal.php';
    if ( ! trait_exists( 'ACDC_Auth_Portal_Core_Trait' ) || ! trait_exists( 'ACDC_Auth_Portal_Actions_Trait' ) || ! trait_exists( 'ACDC_Auth_Portal_Render_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Module auth portail partiellement chargé : traits auth portail indisponibles.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Module auth portail introuvable : includes/class-acdc-auth-portal.php.' );
    return;
}


if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-marketing.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-marketing.php';
    if ( ! trait_exists( 'ACDC_Marketing_Core_Trait' ) || ! trait_exists( 'ACDC_Marketing_Actions_Trait' ) || ! trait_exists( 'ACDC_Marketing_Render_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Module marketing partiellement chargé : traits marketing indisponibles.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Module marketing introuvable : includes/class-acdc-marketing.php.' );
    return;
}

if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-questionnaires.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-questionnaires.php';
    if ( ! trait_exists( 'ACDC_Questionnaires_Core_Trait' ) || ! trait_exists( 'ACDC_Questionnaires_Actions_Trait' ) || ! trait_exists( 'ACDC_Questionnaires_Render_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Module questionnaires partiellement chargé : traits questionnaires indisponibles.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Module questionnaires introuvable : includes/class-acdc-questionnaires.php.' );
    return;
}

if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-settings-catalog.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-settings-catalog.php';
    if ( ! trait_exists( 'ACDC_Settings_Catalog_Core_Trait' ) || ! trait_exists( 'ACDC_Settings_Catalog_Actions_Trait' ) || ! trait_exists( 'ACDC_Settings_Catalog_Render_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Module réglages/catalogue partiellement chargé : traits réglages/catalogue indisponibles.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Module réglages/catalogue introuvable : includes/class-acdc-settings-catalog.php.' );
    return;
}

if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-compliance-quality.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-compliance-quality.php';
    if ( ! trait_exists( 'ACDC_Compliance_Quality_Core_Trait' ) || ! trait_exists( 'ACDC_Compliance_Quality_Actions_Trait' ) || ! trait_exists( 'ACDC_Compliance_Quality_Render_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Module conformité/qualité partiellement chargé : traits conformité/qualité indisponibles.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Module conformité/qualité introuvable : includes/class-acdc-compliance-quality.php.' );
    return;
}


if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-learners-contacts.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-learners-contacts.php';
    if ( ! trait_exists( 'ACDC_Learners_Contacts_Core_Trait' ) || ! trait_exists( 'ACDC_Learners_Contacts_Actions_Trait' ) || ! trait_exists( 'ACDC_Learners_Contacts_Render_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Module apprenants/contacts partiellement chargé : traits apprenants/contacts indisponibles.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Module apprenants/contacts introuvable : includes/class-acdc-learners-contacts.php.' );
    return;
}


if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-evaluations.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-evaluations.php';
    if ( ! trait_exists( 'ACDC_Evaluations_Core_Trait' ) || ! trait_exists( 'ACDC_Evaluations_Actions_Trait' ) || ! trait_exists( 'ACDC_Evaluations_Render_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Module évaluations des acquis partiellement chargé : traits évaluations indisponibles.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Module évaluations des acquis introuvable : includes/class-acdc-evaluations.php.' );
    return;
}


/* ACDC 3.21.00 — Socle commun aux campagnes asynchrones (token + email + relance). */
if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-async-dispatcher.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-async-dispatcher.php';
    if ( ! trait_exists( 'ACDC_Async_Dispatcher_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Socle async dispatcher partiellement chargé : trait indisponible.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Socle async dispatcher introuvable : includes/class-acdc-async-dispatcher.php.' );
    return;
}


/* ACDC 3.21.00 — Module Quizzes (Quiz live + Tests de positionnement + Évaluations des acquis, moteur unifié). */
if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-quizzes.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-quizzes.php';
    if ( ! trait_exists( 'ACDC_Quizzes_Core_Trait' ) || ! trait_exists( 'ACDC_Quizzes_Actions_Trait' ) || ! trait_exists( 'ACDC_Quizzes_Engine_Trait' ) || ! trait_exists( 'ACDC_Quizzes_Render_Trait' ) || ! trait_exists( 'ACDC_Quizzes_Render_Live_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Module quizzes partiellement chargé : traits quizzes indisponibles.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Module quizzes introuvable : includes/class-acdc-quizzes.php.' );
    return;
}



if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-crm-commercial.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-crm-commercial.php';
    if ( ! trait_exists( 'ACDC_Crm_Commercial_Core_Trait' ) || ! trait_exists( 'ACDC_Crm_Commercial_Actions_Trait' ) || ! trait_exists( 'ACDC_Crm_Commercial_Render_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Module CRM commercial partiellement chargé : traits CRM commercial indisponibles.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Module CRM commercial introuvable : includes/class-acdc-crm-commercial.php.' );
    return;
}


if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-sessions.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-sessions.php';
    if ( ! trait_exists( 'ACDC_Sessions_Core_Trait' ) || ! trait_exists( 'ACDC_Sessions_Actions_Trait' ) || ! trait_exists( 'ACDC_Sessions_Render_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Module séances partiellement chargé : traits séances indisponibles.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Module séances introuvable : includes/class-acdc-sessions.php.' );
    return;
}


if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-dossiers-contracts.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-dossiers-contracts.php';
    if ( ! trait_exists( 'ACDC_Dossiers_Contracts_Core_Trait' ) || ! trait_exists( 'ACDC_Dossiers_Contracts_Actions_Trait' ) || ! trait_exists( 'ACDC_Dossiers_Contracts_Render_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Module dossiers / conventions-contrats partiellement chargé : traits dossiers / conventions-contrats indisponibles.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Module dossiers / conventions-contrats introuvable : includes/class-acdc-dossiers-contracts.php.' );
    return;
}


if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-documents-billing.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-documents-billing.php';
    if ( ! trait_exists( 'ACDC_Documents_Billing_Core_Trait' ) || ! trait_exists( 'ACDC_Documents_Billing_Actions_Trait' ) || ! trait_exists( 'ACDC_Documents_Billing_Render_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Module documents / devis / factures partiellement chargé : traits documents / devis / factures indisponibles.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Module documents / devis / factures introuvable : includes/class-acdc-documents-billing.php.' );
    return;
}

if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-agent-audit.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-agent-audit.php';
    if ( ! trait_exists( 'ACDC_Agent_Audit_Core_Trait' ) || ! trait_exists( 'ACDC_Agent_Audit_Actions_Trait' ) || ! trait_exists( 'ACDC_Agent_Audit_Render_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Module exploration contrôlée partiellement chargé : traits exploration contrôlée indisponibles.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Module exploration contrôlée introuvable : includes/class-acdc-agent-audit.php.' );
    return;
}



require_once ACDC_OF_SAAS_DIR . 'includes/proposals/class-acdc-proposals-core-trait.php';
require_once ACDC_OF_SAAS_DIR . 'includes/proposals/class-acdc-proposals-actions-trait.php';
require_once ACDC_OF_SAAS_DIR . 'includes/proposals/class-acdc-proposals-render-trait.php';

/* ACDC 3.25.185 — Module workflow : orchestration du parcours, du recueil des
   besoins jusqu'aux enquêtes de fin de formation. */
if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-workflow.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-workflow.php';
    if ( ! trait_exists( 'ACDC_Workflow_Core_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Module workflow incomplet : includes/class-acdc-workflow.php.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Module workflow introuvable : includes/class-acdc-workflow.php.' );
    return;
}

/* ACDC 3.25.144 — Module MCP : abilities WordPress (pilotage via mcp-adapter). */
require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-mcp.php';

/* ACDC 3.23.11 — Module veille automatisée IA (V1→V6). */
require_once ACDC_OF_SAAS_DIR . 'includes/watch/class-acdc-watch-core-trait.php';
require_once ACDC_OF_SAAS_DIR . 'includes/watch/class-acdc-watch-ai-trait.php';
require_once ACDC_OF_SAAS_DIR . 'includes/watch/class-acdc-watch-actions-trait.php';
require_once ACDC_OF_SAAS_DIR . 'includes/watch/class-acdc-watch-render-trait.php';

if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-kernel.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-kernel.php';
    if ( ! trait_exists( 'ACDC_Kernel_Core_Trait' ) || ! trait_exists( 'ACDC_Kernel_Actions_Trait' ) || ! trait_exists( 'ACDC_Kernel_Render_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Module noyau final partiellement chargé : traits noyau final indisponibles.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Module noyau final introuvable : includes/class-acdc-kernel.php.' );
    return;
}

if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-learner-portal.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-learner-portal.php';
    if ( ! trait_exists( 'ACDC_Learner_Portal_Core_Trait' ) || ! trait_exists( 'ACDC_Learner_Portal_Actions_Trait' ) || ! trait_exists( 'ACDC_Learner_Portal_Render_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Module extranet apprenant partiellement chargé : traits apprenant indisponibles.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Module extranet apprenant introuvable : includes/class-acdc-learner-portal.php.' );
    return;
}

/* ACDC 3.20.83 — Module portail formateur (auth custom). */
if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-trainer-portal.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-trainer-portal.php';
    if ( ! trait_exists( 'ACDC_Trainer_Portal_Core_Trait' ) || ! trait_exists( 'ACDC_Trainer_Portal_Actions_Trait' ) || ! trait_exists( 'ACDC_Trainer_Portal_Render_Trait' ) ) {
        acdc_of_saas_store_boot_error( 'Module portail formateur partiellement chargé : traits formateur indisponibles.' );
        return;
    }
} else {
    acdc_of_saas_store_boot_error( 'Module portail formateur introuvable : includes/class-acdc-trainer-portal.php.' );
    return;
}

if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-plugin.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-plugin.php';
} else {
    acdc_of_saas_store_boot_error( 'Le fichier principal includes/class-acdc-plugin.php est introuvable.' );
    return;
}

if ( ! class_exists( 'ACDC_Formation_SAAS_Plugin' ) ) {
    acdc_of_saas_store_boot_error( 'La classe principale ACDC_Formation_SAAS_Plugin est introuvable.' );
    return;
}

/**
 * ACDC 3.25.246 — Envoi d'un e-mail au gabarit de marque, depuis n'importe où.
 *
 * Les modules autonomes — signature, émargement — ne composent pas la classe
 * principale et ne peuvent donc pas atteindre ses méthodes. Cette fonction leur
 * donne la même porte que le reste du plugin : même mise en forme, et surtout
 * même contrôle du mode recette.
 *
 * Si la classe principale n'est pas là, on N'ENVOIE PAS et on le journalise.
 * Retomber sur wp_mail() rétablirait exactement ce que l'on vient de fermer :
 * un envoi qui échappe à la liste des destinataires autorisés.
 *
 * @param string $to             Destinataire.
 * @param string $subject        Objet.
 * @param array  $template_args  greeting_name, intro_html, body_html, summary_title, summary_rows, footer_notice.
 * @param array  $header_args    En-têtes d'attribution (module, action, catégorie).
 * @param array  $attachments    Chemins des pièces jointes.
 * @return bool
 */
function acdc_of_send_branded_email( $to, $subject, $template_args = array(), $header_args = array(), $attachments = array() ) {
    if ( ! class_exists( 'ACDC_Formation_SAAS_Plugin' ) || ! method_exists( 'ACDC_Formation_SAAS_Plugin', 'get_instance' ) ) {
        error_log( '[ACDC] E-mail non envoyé (classe principale absente) : ' . wp_strip_all_tags( (string) $subject ) );
        return false;
    }
    $plugin = ACDC_Formation_SAAS_Plugin::get_instance();
    if ( ! $plugin || ! method_exists( $plugin, 'acdc_send_branded_email' ) ) {
        error_log( '[ACDC] E-mail non envoyé (porte d’envoi absente) : ' . wp_strip_all_tags( (string) $subject ) );
        return false;
    }
    return $plugin->acdc_send_branded_email( $to, $subject, $template_args, $header_args, $attachments );
}

// --- Module Signature électronique (Phase 1) ---
if ( file_exists( ACDC_OF_SAAS_DIR . 'includes/class-acdc-signature.php' ) ) {
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-signature.php';
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-emargement.php';
    ACDC_Emargement::get_instance();

    // Module Audit Qualiopi
    require_once ACDC_OF_SAAS_DIR . 'includes/class-acdc-audit.php';
    if ( ! class_exists( 'ACDC_Signature' ) ) {
        acdc_of_saas_store_boot_error( 'Module signature partiellement chargé : classe ACDC_Signature indisponible.' );
    }
} else {
    acdc_of_saas_store_boot_error( 'Module signature introuvable : includes/class-acdc-signature.php.' );
}

if ( ! function_exists( 'acdc_of_saas_safe_activate' ) ) {
    function acdc_of_saas_safe_activate() {
        delete_option( 'acdc_of_saas_boot_error' );
        try {
            ACDC_Formation_SAAS_Plugin::activate();
            if ( class_exists( 'ACDC_Signature' ) ) {
                ACDC_Signature::install();
            }
        } catch ( Throwable $e ) {
            acdc_of_saas_store_boot_error( $e->getMessage() . ' dans ' . $e->getFile() . ':' . $e->getLine() );
            deactivate_plugins( plugin_basename( __FILE__ ) );
            wp_die( esc_html( 'Activation interrompue : ' . $e->getMessage() ) );
        }
    }
}

if ( ! function_exists( 'acdc_of_saas_safe_deactivate' ) ) {
    function acdc_of_saas_safe_deactivate() {
        try {
            ACDC_Formation_SAAS_Plugin::deactivate();
            if ( class_exists( 'ACDC_Signature' ) ) {
                ACDC_Signature::deactivate();
            }
            if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
                wp_clear_scheduled_hook( 'acdc_sig_cron_relances' );
                wp_clear_scheduled_hook( 'acdc_of_surveys_cron_dispatches' );
                wp_clear_scheduled_hook( 'acdc_of_surveys_cron_reminders' );
                wp_clear_scheduled_hook( 'acdc_of_surveys_cron_expirations' );
                wp_clear_scheduled_hook( 'acdc_of_surveys_cron_action_followups' );
                wp_clear_scheduled_hook( 'acdc_of_learner_portal_cron_maintenance' );
                wp_clear_scheduled_hook( 'acdc_nad_cron_send_and_relance' );
                wp_clear_scheduled_hook( 'acdc_of_cron_push_indicators' );
                wp_clear_scheduled_hook( 'acdc_of_cron_sync_formations' );
                wp_clear_scheduled_hook( 'acdc_of_absence_alert_cron' );
                wp_clear_scheduled_hook( 'acdc_of_invoices_overdue_cron' ); // ACDC 3.25.118
                wp_clear_scheduled_hook( 'acdc_of_session_close_cron' );
                wp_clear_scheduled_hook( 'acdc_of_convocation_cron' );
                wp_clear_scheduled_hook( 'acdc_of_positioning_test_cron' );
                wp_clear_scheduled_hook( 'acdc_of_qualiopi_alerts_cron' );
                wp_clear_scheduled_hook( 'acdc_of_trainer_portal_cron_maintenance' );
                wp_clear_scheduled_hook( 'acdc_of_qz_cron_dispatches' );
                wp_clear_scheduled_hook( 'acdc_of_qz_cron_reminders' );
                wp_clear_scheduled_hook( 'acdc_of_qz_cron_expirations' );
                wp_clear_scheduled_hook( 'acdc_of_qz_cron_close_inactive_sessions' );
                wp_clear_scheduled_hook( 'acdc_of_qz_cron_rgpd_purge' );
                wp_clear_scheduled_hook( 'acdc_of_watch_collect_cron' );
                wp_clear_scheduled_hook( 'acdc_of_watch_analyze_cron' );
                wp_clear_scheduled_hook( 'acdc_of_watch_digest_cron' );
                wp_clear_scheduled_hook( 'acdc_of_watch_reminder_cron' );
            }
            if ( function_exists( 'delete_transient' ) ) {
                delete_transient( 'acdc_of_saas_runtime_notice' );
            }
        } catch ( Throwable $e ) {
            acdc_of_saas_store_boot_error( $e->getMessage() . ' dans ' . $e->getFile() . ':' . $e->getLine() );
        }
    }
}

register_activation_hook( __FILE__, 'acdc_of_saas_safe_activate' );
register_deactivation_hook( __FILE__, 'acdc_of_saas_safe_deactivate' );

try {
    delete_option( 'acdc_of_saas_boot_error' );
    ACDC_Formation_SAAS_Plugin::get_instance();
    if ( class_exists( 'ACDC_Signature' ) ) {
        ACDC_Signature::get_instance();
    }
} catch ( Throwable $e ) {
    acdc_of_saas_store_boot_error( $e->getMessage() . ' dans ' . $e->getFile() . ':' . $e->getLine() );
}
