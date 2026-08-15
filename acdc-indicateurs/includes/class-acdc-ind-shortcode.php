<?php
defined( 'ABSPATH' ) || exit;

/**
 * ACDC Indicateurs — Shortcode [acdc_indicateurs_globaux]
 */
class ACDC_Ind_Shortcode {

    public static function init() {
        add_shortcode( 'acdc_indicateurs_globaux', array( __CLASS__, 'render' ) );
    }

    public static function render( $atts ) {
        $atts = shortcode_atts( array(
            'colonnes'  => '3',
            'style'     => 'cards',
            'couleur'   => '#D7A24B',
            'items'     => 'all',
        ), $atts, 'acdc_indicateurs_globaux' );

        $api  = new ACDC_Ind_Api();
        $data = $api->fetch_global();

        if ( ! $data ) {
            // Silencieux — aucun bloc si le SAAS est inaccessible
            return '';
        }

        $couleur  = sanitize_hex_color( $atts['couleur'] ) ?: '#D7A24B';
        $colonnes = max( 1, min( 4, (int) $atts['colonnes'] ) );
        $style    = in_array( $atts['style'], array( 'cards', 'inline' ), true ) ? $atts['style'] : 'cards';

        // Liste complète des indicateurs disponibles
        $all_items = array(
            /* ACDC 1.0.7 — LE CHIFFRE ANNONCÉ ÉTAIT RECOPIÉ À LA MAIN.
               Le SAAS ne remontait que l'activité de l'organisme, une petite
               part de ce qui a réellement été animé : il fallait donc saisir le
               vrai total dans les réglages, et le corriger à chaque nouvelle
               formation. Le SAAS publie désormais les deux — l'organisme seul,
               et tout compris, prestations extérieures incluses. On prend le
               second, en gardant l'ancienne clé en repli pour qu'un SAAS non
               mis à jour continue d'afficher quelque chose de juste. */
            'apprenants'      => array(
                'value' => self::premier_positif( array(
                    $data['total_apprenants_tous'] ?? 0,
                    $data['total_apprenants'] ?? 0,
                    get_option( 'acdc_ind_fallback_apprenants', 0 ),
                ) ),
                'label' => 'Apprenants formés',
                'suffix' => '',
            ),
            'satisfaction'    => array(
                'value' => isset( $data['taux_satisfaction_moyen'] ) ? (int) $data['taux_satisfaction_moyen'] : 0,
                'label' => 'Taux de satisfaction',
                'suffix' => '%',
            ),
            'reussite'        => array(
                'value' => isset( $data['taux_reussite_moyen'] ) ? (int) $data['taux_reussite_moyen'] : 0,
                'label' => 'Taux de réussite',
                'suffix' => '%',
            ),
            'recommandation'  => array(
                'value' => isset( $data['taux_recommandation_moyen'] ) ? (int) $data['taux_recommandation_moyen'] : 0,
                'label' => 'Taux de recommandation',
                'suffix' => '%',
            ),
            'formations'      => array(
                'value' => isset( $data['total_formations'] ) ? (int) $data['total_formations'] : 0,
                'label' => 'Formations actives',
                'suffix' => '',
            ),
            /* ACDC 1.0.7 — DEUX UNITÉS D'HEURES, ET LE LIBELLÉ NE DÉSIGNAIT PAS
               CELLE QU'ON AFFICHAIT. Une journée de 7 h devant douze personnes
               fait 7 heures DISPENSÉES et 84 heures SUIVIES. Le réglage de
               secours portait le second chiffre sous le libellé du premier :
               juste, mais sous un nom faux — et personne ne vient vérifier un
               chiffre qui a l'air renseigné. On affiche les heures suivies,
               sous leur nom. */
            'heures'          => array(
                'value' => self::premier_positif( array(
                    $data['total_heures_suivies_tous'] ?? 0,
                    $data['total_heures'] ?? 0,
                    get_option( 'acdc_ind_fallback_heures', 0 ),
                ) ),
                'label' => 'Heures de formation suivies',
                'suffix' => 'h',
                'format' => 'heures',
            ),
            /* Les heures réellement animées, pour qui veut les deux. */
            'heures_dispensees' => array(
                'value' => self::premier_positif( array(
                    $data['total_heures_dispensees_tous'] ?? 0,
                    $data['total_heures'] ?? 0,
                ) ),
                'label' => 'Heures de formation dispensées',
                'suffix' => 'h',
                'format' => 'heures',
            ),
        );

        // Filtrer selon l'attribut items
        if ( 'all' !== $atts['items'] ) {
            $requested = array_map( 'trim', explode( ',', $atts['items'] ) );
            $items_to_show = array();
            foreach ( $requested as $key ) {
                if ( isset( $all_items[ $key ] ) ) {
                    $items_to_show[ $key ] = $all_items[ $key ];
                }
            }
        } else {
            $items_to_show = $all_items;
        }

        if ( empty( $items_to_show ) ) {
            return '';
        }

        // Rendu CSS unique par page (une seule fois)
        static $css_printed = false;
        $css_block = '';
        if ( ! $css_printed ) {
            $css_block = self::get_css();
            $css_printed = true;
        }

        ob_start();
        echo $css_block;

        $wrapper_class = 'acdc-ind-wrap acdc-ind-' . $style;
        $cols_style    = 'grid-template-columns:repeat(' . $colonnes . ',minmax(0,1fr));';

        echo '<div class="' . esc_attr( $wrapper_class ) . '" style="' . esc_attr( $cols_style ) . '">';

        foreach ( $items_to_show as $item ) {
            // Format spécial pour les heures : entier + " heures" en toutes lettres
            if ( ! empty( $item['format'] ) && 'heures' === $item['format'] ) {
              $val_int = (int) round( (float) $item['value'] );
              $value_display = number_format( $val_int, 0, ',', ' ' ) . ' ' . ( $val_int > 1 ? 'heures' : 'heure' );
            } else {
              $value_display = is_float( $item['value'] ) ? number_format( $item['value'], 1, ',', ' ' ) : number_format( (int) $item['value'], 0, ',', ' ' );
              $value_display .= $item['suffix'];
            }
            echo '<div class="acdc-ind-card">';
            echo '<span class="acdc-ind-value" style="color:' . esc_attr( $couleur ) . ';">' . esc_html( $value_display ) . '</span>';
            echo '<span class="acdc-ind-label">' . esc_html( $item['label'] ) . '</span>';
            echo '</div>';
        }

        echo '</div>';

        return (string) ob_get_clean();
    }

    /**
     * La première valeur strictement positive de la liste.
     *
     * Chaque indicateur a une source principale, une source de repli, et — pour
     * les deux plus anciens — une valeur saisie à la main. Zéro ne veut pas
     * dire « pas de mesure » de façon fiable, mais afficher 0 apprenant sur une
     * page commerciale serait pire que d'afficher la valeur précédente : on
     * descend donc la liste jusqu'à trouver un chiffre.
     *
     * @param array $candidats Du plus souhaitable au dernier recours.
     * @return float
     */
    private static function premier_positif( $candidats ) {
        foreach ( (array) $candidats as $v ) {
            $n = (float) $v;
            if ( $n > 0 ) {
                return $n;
            }
        }
        return 0.0;
    }

    private static function get_css() {
        return '<style>
.acdc-ind-wrap{display:grid;gap:18px;margin:24px 0}
.acdc-ind-wrap.acdc-ind-inline{display:flex;flex-wrap:wrap;gap:14px;align-items:center}
.acdc-ind-card{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:22px 16px;background:#fff;border:1px solid #dce4ec;border-radius:14px;box-shadow:0 4px 14px rgba(28,44,64,.05);gap:8px}
.acdc-ind-value{font-family:Rubik,Arial,sans-serif;font-size:40px;font-weight:700;line-height:1;letter-spacing:-.02em;text-shadow:1px 1px 1px rgba(0,0,0,.97)}
.acdc-ind-label{font-size:13px;color:#0C2D52;font-weight:500;line-height:1.35;text-align:center}
.acdc-ind-wrap.acdc-ind-inline .acdc-ind-card{flex:1;min-width:120px}
@media(max-width:600px){.acdc-ind-wrap{grid-template-columns:1fr 1fr!important}}
</style>';
    }
}

ACDC_Ind_Shortcode::init();
