<?php
/**
 * L'application doit rester lisible ailleurs que sur le 27" où elle est née.
 *
 * « Tout le plugin a été développé sur mon Mac 27". Quand je le visualise sur
 * mon iPhone, mon iPad ou mon laptop, visuellement c'est compliqué. »
 *
 * Ce défaut-là a une propriété qu'aucun autre n'a dans ce plugin : CELUI QUI
 * DÉVELOPPE NE LE VOIT JAMAIS. Une requête fausse finit par se remarquer, un
 * e-mail mal formé finit par revenir ; une mise en page cassée sur un écran
 * qu'on n'ouvre pas peut durer des années. C'est exactement ce qui s'est passé.
 *
 * D'où ce balayage, qui ne juge pas du rendu — il ne saurait pas — mais garde
 * les pièces sans lesquelles il n'y a plus d'adaptation du tout :
 *
 *   — la feuille et le script existent, et sont RÉELLEMENT chargés, côté
 *     extranet comme côté administration. Un fichier présent mais jamais
 *     déclaré ne se voit pas non plus ;
 *   — les trois seuils correspondent aux trois appareils de référence ;
 *   — la correction structurelle est là : sans elle, le menu et le contenu
 *     restent côte à côte sur une tablette, et il ne reste rien pour le
 *     contenu ;
 *   — et surtout : AUCUNE RÈGLE DE COLONNE GÉNÉRIQUE. C'est la faute qui a
 *     déformé tous les tableaux du plugin pendant des mois — une largeur écrite
 *     pour le répertoire des apprenants, posée sur `.acdc-table`, qui plafonnait
 *     la quatrième colonne de TOUS les tableaux à 74 px.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$css  = $root . '/assets/css/responsive.css';
$js   = $root . '/assets/js/acdc-responsive.js';
$hits = array();

if ( ! is_readable( $css ) ) {
    $hits[] = 'assets/css/responsive.css a disparu : plus aucune adaptation aux petits écrans.';
} else {
    $src = (string) file_get_contents( $css );
    foreach ( array(
        'max-width: 1024px' => 'le seuil tablette (iPad en portrait, 768 px)',
        'max-width: 640px'  => 'le seuil mobile (iPhone SE / 13 mini, 375 px)',
        'max-width: 1440px' => 'le seuil laptop 14" (1280 px)',
    ) as $seuil => $quoi ) {
        if ( false === strpos( $src, $seuil ) ) {
            $hits[] = 'Seuil manquant : ' . $quoi . '.';
        }
    }
    if ( ! preg_match( '/\.acdc-portal-layout\s*\{\s*display:\s*block/', $src ) ) {
        $hits[] = 'La correction structurelle a disparu : sur une tablette, le menu et le contenu redeviennent côte à côte, le menu prend toute la largeur et il ne reste rien pour le contenu.';
    }
    if ( false === strpos( $src, 'acdc-table-cards' ) ) {
        $hits[] = 'Les tableaux en fiches ont disparu : sur un téléphone, douze colonnes se lisent au doigt, en perdant les en-têtes en route.';
    }
    if ( false === strpos( $src, 'acdc-nav-toggle' ) ) {
        $hits[] = 'Le bouton du menu a disparu : sur un téléphone, la barre latérale occupe tout l’écran.';
    }
    /* Deux blocages mesurés sur l'iPhone de David, tous deux dus à `100vh`. */
    if ( false === strpos( $src, '100dvh' ) ) {
        $hits[] = 'L’unité dvh a disparu : `100vh` vaut sur iOS la hauteur écran BARRE D’ADRESSE COMPRISE. Le tiroir redevient plus haut que la surface visible, ne se déclare pas débordé, et ses dernières entrées deviennent inaccessibles.';
    }
    if ( false === strpos( $src, 'acdc-qz-live-player' ) ) {
        $hits[] = 'Le déverrouillage des écrans de quiz a disparu : ils sont posés en height:100vh AVEC overflow:hidden, et sur un téléphone tout ce qui dépasse — la question, les réponses, le bouton de validation — redevient purement inatteignable.';
    }
}

if ( ! is_readable( $js ) ) {
    $hits[] = 'assets/js/acdc-responsive.js a disparu : plus de tiroir de menu, et les fiches n’auraient plus de libellés.';
} else {
    $src = (string) file_get_contents( $js );
    if ( false === strpos( $src, 'data-acdc-label' ) ) {
        $hits[] = 'Les libellés de colonne ne sont plus recopiés dans les cellules : les fiches afficheraient des valeurs sans dire ce qu’elles désignent.';
    }
    if ( false === strpos( $src, 'acdc-js-ready' ) ) {
        $hits[] = 'La classe acdc-js-ready n’est plus posée : le tiroir ne s’ouvrira jamais, et rien ne le signalera.';
    }
}

/* Chargée, pas seulement écrite — et des deux côtés. */
$render = $root . '/includes/kernel/class-acdc-kernel-render-trait.php';
if ( is_readable( $render ) ) {
    $src = (string) file_get_contents( $render );
    if ( substr_count( $src, "assets/css/responsive.css" ) < 2 ) {
        $hits[] = 'La feuille d’adaptation n’est plus déclarée dans les deux contextes (extranet ET administration) : les mêmes écrans s’ouvrent des deux côtés.';
    }
    if ( substr_count( $src, "assets/js/acdc-responsive.js" ) < 2 ) {
        $hits[] = 'Le script d’adaptation n’est plus déclaré dans les deux contextes.';
    }
}

/* La faute d'origine : une règle écrite pour un tableau, appliquée à tous. */
$tables = $root . '/assets/css/tables.css';
if ( is_readable( $tables ) ) {
    $src   = (string) file_get_contents( $tables );
    $lines = explode( "\n", $src );
    foreach ( $lines as $i => $line ) {
        if ( ! preg_match( '/^\s*\.acdc-table\s+(th|td):nth-child/', $line ) ) {
            continue;
        }
        $bloc = implode( "\n", array_slice( $lines, $i, 8 ) );
        if ( preg_match( '/(max-)?width:\s*\d+px/', $bloc ) ) {
            $hits[] = sprintf(
                'tables.css:%d  largeur de colonne posée sur `.acdc-table` : elle s’appliquera à TOUS les tableaux du plugin, y compris ceux dont cette colonne porte un long libellé — c’est ainsi que « Méthode d’émargement » débordait par-dessus « Format ». À réserver à un tableau nommé.',
                $i + 1
            );
        }
    }
}

if ( $hits ) {
    echo "Adaptation aux écrans :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 occurrence — la couche d’adaptation est en place et chargée des deux côtés.\n";
exit( 0 );
