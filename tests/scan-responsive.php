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

/* ── CE QUI SE SAISIT AVEC UN DOIGT ───────────────────────────────────────
   Relevé sur iPad : « je ne peux pas régler la largeur des colonnes comme sur
   mon Mac ». La poignée n'écoutait que la souris — or Safari ne fabrique des
   événements de souris que pour une touche BRÈVE, jamais pour un glissement.
   Elle ne recevait donc strictement rien quand on la tirait au doigt.

   Deux conditions, et il faut les deux : les événements de POINTEUR, qui
   couvrent la souris, le doigt et le stylet d'un seul jeu ; et `touch-action`
   à `none`, sans quoi le navigateur interprète le glissement comme un
   défilement et la colonne ne bouge jamais. La seconde est invisible à la
   relecture : le code paraît juste, et rien ne se passe. */
$kernel_js = $root . '/assets/js/acdc-ui-kernel.js';
if ( is_readable( $kernel_js ) ) {
    $src_k = (string) file_get_contents( $kernel_js );
    $debut = strpos( $src_k, 'kernel.initAdminColumnResize' );
    if ( false !== $debut ) {
        $bloc = substr( $src_k, $debut );
        if ( false === strpos( $bloc, "addEventListener('pointerdown'" ) ) {
            $hits[] = 'Le redimensionnement des colonnes est revenu aux événements de souris : sur une tablette, la poignée ne recevra rien pendant le glissement, et la largeur restera celle du bureau.';
        }
        if ( false === strpos( $bloc, 'touchAction' ) ) {
            $hits[] = 'La poignée de colonne n’interdit plus le défilement pendant le glissement : le doigt fera défiler la page au lieu de tirer la colonne, sans qu’aucune erreur ne le signale.';
        }
        if ( false === strpos( $bloc, 'pointercancel' ) ) {
            $hits[] = 'Le glissement de colonne ne traite plus l’annulation du pointeur : un appel entrant pendant un redimensionnement laisserait la page en mode glissement, curseur figé et sélection bloquée.';
        }
    }
}

/* ── UN STYLE ÉCRIT DANS LE BALISAGE ANNULE LA FEUILLE ────────────────────
   `.acdc-grid-3cols` et `.acdc-grid-4cols` se replient déjà sur une colonne
   quand l'écran rétrécit — la règle existe depuis longtemps dans
   components.css. Elles ne se repliaient pourtant pas, et aucune relecture de
   feuille de style ne pouvait le révéler : un `style="grid-template-columns:…"`
   écrit à côté de la classe, dans le balisage, l'emporte sur toute feuille.
   La règle adaptative était là, simplement inatteignable. */
$hits_grilles = array();
$rii_php = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/includes' ) );
foreach ( $rii_php as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $src = (string) file_get_contents( $f->getPathname() );
    if ( preg_match_all( '/class="(acdc-grid-[34]cols[^"]*)"[^>]{0,200}?style="([^"]*grid-template-columns[^"]*)"/', $src, $m, PREG_SET_ORDER ) ) {
        foreach ( $m as $trouve ) {
            $hits_grilles[] = sprintf(
                '%s : une grille porte la classe %s — qui sait déjà se replier — ET un style direct qui l’en empêche. Sur un téléphone, quatre colonnes de 80 px.',
                preg_replace( '#^.*/includes/#', '', $f->getPathname() ),
                trim( explode( ' ', $trouve[1] )[0] )
            );
        }
    }
}
foreach ( array_unique( $hits_grilles ) as $h ) { $hits[] = $h; }

/* La reprise en main des styles directs — le seul cas où `!important` n'est pas
   un aveu, puisqu'il n'existe aucun autre moyen. */
if ( is_readable( $css ) ) {
    $src_r = (string) file_get_contents( $css );
    if ( false === strpos( $src_r, '[style*="display:flex"]' ) ) {
        $hits[] = 'Les rangées `display:flex` écrites dans le balisage ne sont plus forcées à s’enrouler : sur un écran étroit, leurs éléments se compriment jusqu’à l’illisible, et aucune feuille ne peut le corriger.';
    }
    if ( false === strpos( $src_r, 'acdc-grid-semaine' ) ) {
        $hits[] = 'Le calendrier n’est plus épargné par le repli des grilles : une semaine repliée sur deux colonnes n’est pas un calendrier lisible, c’est un calendrier détruit.';
    }
}

/* ── LES ÉCRANS QUE L'APPRENANT OUVRE SUR SON TÉLÉPHONE ───────────────────
   Ce sont les seuls que le développeur n'ouvre JAMAIS, et ceux qui produisent
   les mesures publiées : l'enquête de satisfaction et l'émargement. */

/* 1. Les cibles tactiles. Les étoiles de notation faisaient 23 × 26 px sur un
      téléphone et 29 × 32 sur une tablette. Un doigt vise mal en-dessous d'une
      quarantaine de pixels — et cette question-là produit le taux de
      satisfaction affiché sur le site commercial. Une étoile touchée à côté
      n'est pas une gêne d'ergonomie : c'est une mesure fausse, que rien ne
      distinguera jamais d'une vraie.
      Le critère est le DOIGT, pas la largeur : une tablette se touche autant
      qu'un téléphone. */
$surveys = $root . '/assets/css/surveys.css';
if ( ! is_readable( $surveys ) ) {
    $hits[] = 'assets/css/surveys.css a disparu : l’enquête que remplit l’apprenant n’a plus de mise en forme.';
} else {
    $src = (string) file_get_contents( $surveys );
    /* On cherche la RÈGLE, pas la mention : le commentaire qui l'explique
       contient les mêmes mots, et un contrôle qui se satisfait de sa propre
       documentation ne contrôle rien. */
    if ( ! preg_match( '/@media\s*\(\s*pointer\s*:\s*coarse\s*\)/', $src ) ) {
        $hits[] = 'L’enquête ne distingue plus les écrans tactiles : les étoiles de notation redeviennent des cibles de 26 px, sur l’écran même qui produit le taux de satisfaction publié.';
    }
    if ( false === strpos( $src, '100dvh' ) ) {
        $hits[] = 'L’enquête est revenue à `100vh` seul : sur iOS, le bas de la page reste hors de portée tant qu’on n’a pas fait défiler dans le vide.';
    }
}

/* 2. L'émargement. C'est le geste qui produit la preuve de présence : s'il
      échoue sur le téléphone d'un apprenant, il ne reste rien. */
$emarg = $root . '/includes/emargement/class-acdc-emarg-public.php';
if ( is_readable( $emarg ) ) {
    $src = (string) file_get_contents( $emarg );
    if ( false === strpos( $src, 'name="viewport"' ) ) {
        $hits[] = 'L’écran d’émargement n’annonce plus sa largeur : un téléphone le rendra à 980 px puis le réduira, et on signera dans un cadre de la taille d’un timbre.';
    }
    if ( false === strpos( $src, '100dvh' ) ) {
        $hits[] = 'L’écran d’émargement est revenu à `100vh` seul : sur iOS, le bouton de validation peut rester sous la barre du navigateur.';
    }
    if ( false === strpos( $src, 'safe-area-inset-bottom' ) ) {
        $hits[] = 'L’écran d’émargement ne tient plus compte de l’indicateur d’accueil de l’iPhone : le bouton de validation passe dessous, et c’est le seul geste de cet écran.';
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
