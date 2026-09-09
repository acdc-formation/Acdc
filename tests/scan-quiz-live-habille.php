<?php
/**
 * Une classe écrite dans le HTML mais absente de la feuille ne se voit pas.
 *
 * C'est le défaut le plus discret de tout le plugin, et le quiz live en portait
 * quatre exemplaires. Le gabarit de l'écran apprenant écrit
 * `<main class="acdc-qz-live-player-main">` ; la feuille ne connaissait que
 * `acdc-qz-live-host-main`, son jumeau côté formateur. Résultat : le <main> de
 * l'apprenant restait un bloc ordinaire, et TOUS les `flex: 1 ; min-height: 0`
 * écrits en dessous — sur les états, sur la grille des réponses — ne
 * s'appliquaient à rien. Ils étaient là, ils se lisaient dans la feuille, et ils
 * ne faisaient rien. Une question à six réponses en choix multiple poussait
 * ainsi le bouton « Valider mes réponses » hors de l'écran d'un téléphone.
 *
 * Même famille pour l'en-tête : le gabarit écrit `acdc-qz-live-player-brand`,
 * `-meta`, `-nickname`, `-score` ; la feuille définissait `-brand-text` et
 * `acdc-qz-player-header-score`, deux noms que rien n'écrit jamais. L'en-tête
 * que l'apprenant a sous les yeux pendant toute la partie était donc sans
 * aucune mise en forme.
 *
 * Et une variante arithmétique : le script construit les lettres de A à F, la
 * feuille ne colorait que cinq pastilles. La sixième réponse s'affichait sans
 * fond — elle avait l'air désactivée.
 *
 * Ces trois-là se ressemblent : personne ne les voit en lisant le code, parce
 * que le code a l'air complet des deux côtés. Il faut confronter les deux
 * fichiers. C'est ce que fait ce balayage.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';
$hits = array();

$css_file = $root . '/assets/css/quizzes-live.css';
if ( ! is_readable( $css_file ) ) {
    echo "assets/css/quizzes-live.css a disparu : l'écran de quiz live n'a plus aucune mise en forme.\n";
    exit( 1 );
}
$css = (string) file_get_contents( $css_file );
/* Les commentaires expliquent souvent la règle qu'on cherche : les retirer
   évite qu'un balayage se rassure tout seul en lisant sa propre justification. */
$css_net = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

/* Le gabarit de l'écran formateur ne vit PAS dans le même fichier que celui de
   l'apprenant. Une première version de ce balayage ne lisait que le second et
   se déclarait verte : elle affirmait sans avoir lu la moitié du sujet. */
$sources = array(
    $root . '/includes/quizzes/class-acdc-quizzes-render-trait.php',
    $root . '/includes/quizzes/class-acdc-quizzes-render-live-trait.php',
    $root . '/assets/js/quizzes-live-player.js',
    $root . '/assets/js/quizzes-live-host.js',
);

/* ── 1. TOUTE CLASSE DES ÉCRANS LIVE DOIT AVOIR UNE RÈGLE ──────────────── */

$emises = array();
foreach ( $sources as $f ) {
    if ( ! is_readable( $f ) ) {
        $hits[] = basename( $f ) . ' est introuvable : impossible de vérifier que ses classes sont habillées.';
        continue;
    }
    $src = (string) file_get_contents( $f );
    if ( preg_match_all( '/class="([^"]*)"/', $src, $m ) ) {
        foreach ( $m[1] as $liste ) {
            foreach ( preg_split( '/\s+/', $liste ) as $c ) {
                /* On écarte les noms construits par concaténation (« tile-' + i »)
                   et ceux qui contiennent du PHP : ce ne sont pas des classes
                   littérales, on ne peut rien affirmer à leur sujet. */
                if ( preg_match( '/^acdc-qz-(live|player|host)-[A-Za-z0-9_-]+$/', $c ) ) {
                    $emises[ $c ] = true;
                }
            }
        }
    }
}
if ( count( $emises ) < 30 ) {
    $hits[] = sprintf(
        'seulement %d classes live relevées dans les gabarits : la lecture a échoué, le reste du balayage ne prouve rien.',
        count( $emises )
    );
}
foreach ( array_keys( $emises ) as $classe ) {
    if ( ! preg_match( '/\.' . preg_quote( $classe, '/' ) . '(?![A-Za-z0-9_-])/', $css_net ) ) {
        $hits[] = sprintf(
            'la classe « %s » est écrite dans le gabarit et n’a aucune règle : elle s’affiche sans mise en forme, sans que rien ne le signale.',
            $classe
        );
    }
}

/* ── 2. LE <main> DE L'APPRENANT PORTE LE MÊME CONTRAT QUE CELUI DU FORMATEUR ── */

$contrat = function ( $selecteur ) use ( $css_net ) {
    if ( ! preg_match( '/\\' . '.' . preg_quote( $selecteur, '/' ) . '\s*\{([^}]*)\}/', $css_net, $m ) ) {
        return null;
    }
    return $m[1];
};
foreach ( array( 'acdc-qz-live-host-main', 'acdc-qz-live-player-main' ) as $main ) {
    $regle = $contrat( $main );
    if ( null === $regle ) {
        $hits[] = sprintf( '« %s » n’a pas de règle : son contenu ne pourra ni se comprimer ni défiler.', $main );
        continue;
    }
    if ( false === strpos( $regle, 'flex: 1' ) || false === strpos( $regle, 'min-height: 0' ) ) {
        $hits[] = sprintf(
            '« %s » n’a plus « flex: 1 » et « min-height: 0 » : sans les deux, la hauteur des états en dessous n’est plus contrainte et tout ce qui dépasse est coupé.',
            $main
        );
    }
}

/* ── 3. LA ZONE DES RÉPONSES PEUT TOUJOURS SE COMPRIMER ET DÉFILER ─────── */

$grille = $contrat( 'acdc-qz-player-question-answers' );
if ( null === $grille ) {
    $hits[] = 'la grille des réponses de l’apprenant n’a plus de règle.';
} else {
    if ( false === strpos( $grille, 'min-height: 0' ) || false === strpos( $grille, 'overflow-y' ) ) {
        $hits[] = 'la grille des réponses n’a plus « min-height: 0 » et « overflow-y » : au-delà de 1024 px l’écran est verrouillé, et les réponses qui dépassent redeviennent inatteignables.';
    }
    if ( false === strpos( $grille, 'align-content: start' ) ) {
        $hits[] = 'la grille des réponses n’a plus « align-content: start » : elle occupe toute la hauteur, donc quatre réponses s’étirent en pavés de 330 px sur un écran d’ordinateur.';
    }
}

/* La grille du formateur porte le même risque, en pire : au-dessus de 1024 px
   responsive.css ne déverrouille pas l'écran, donc ce qui dépasse est coupé
   sans recours. Un vidéoprojecteur en 1280×720 est au-dessus de ce seuil. */
$grille_hote = $contrat( 'acdc-qz-host-question-answers' );
if ( null === $grille_hote ) {
    $hits[] = 'la grille des réponses du formateur n’a plus de règle.';
} elseif ( false === strpos( $grille_hote, 'min-height: 0' ) || false === strpos( $grille_hote, 'overflow-y' ) ) {
    $hits[] = 'la grille des réponses du formateur n’a plus « min-height: 0 » et « overflow-y » : sur un vidéoprojecteur en 1280×720, une question à six réponses coupe la sixième tuile ET le bouton « Réponse », sans défilement possible.';
}

/* ── 4. AUTANT DE COULEURS QUE DE LETTRES ─────────────────────────────── */

$lettres = 0;
$js = $root . '/assets/js/quizzes-live-player.js';
if ( is_readable( $js ) && preg_match( "/letters\s*=\s*\[([^\]]*)\]/", (string) file_get_contents( $js ), $m ) ) {
    $lettres = count( array_filter( array_map( 'trim', explode( ',', $m[1] ) ) ) );
}
if ( $lettres < 2 ) {
    $hits[] = 'impossible de relire la liste des lettres de réponse : la comparaison avec les couleurs ne prouve rien.';
} else {
    for ( $i = 0; $i < $lettres; $i++ ) {
        if ( ! preg_match( '/\.acdc-qz-host-answer-tile-' . $i . '\s*\{[^}]*background/', $css_net ) ) {
            $hits[] = sprintf(
                'le script propose %d réponses (jusqu’à la lettre %s) mais la pastille n° %d n’a pas de couleur de fond : cette réponse-là s’affiche sans fond, comme désactivée.',
                $lettres,
                chr( 65 + $lettres - 1 ),
                $i
            );
        }
    }
}

/* ── 5. LE TÉLÉPHONE EN PAYSAGE EST UNE CONTRAINTE DE HAUTEUR ──────────── */

if ( ! preg_match( '/@media[^{]*\(\s*max-height\s*:/', $css_net ) ) {
    $hits[] = 'aucune règle indexée sur la HAUTEUR dans quizzes-live.css : un téléphone tenu en paysage est large, il reçoit donc le traitement vertical d’un grand écran et la dernière réponse tombe sous l’écran.';
}
if ( ! preg_match( '/\.acdc-qz-player-multi-footer\s*\{[^}]*position:\s*sticky/', $css_net ) ) {
    $hits[] = 'le bouton « Valider mes réponses » n’est plus collé au bas de l’écran : sur une question à six réponses il repasse sous la ligne de flottaison, et rien n’indique à l’apprenant qu’il existe.';
}

/* ── VERDICT ──────────────────────────────────────────────────────────── */

if ( $hits ) {
    foreach ( $hits as $h ) {
        echo 'ALERTE  ' . $h . "\n";
    }
    printf( "%d alerte(s)\n", count( $hits ) );
    exit( 1 );
}
printf(
    "Quiz live habillé : %d classes du gabarit ont toutes une règle, les deux <main> portent le même contrat, %d pastilles pour %d lettres, la hauteur est prise en compte.\n",
    count( $emises ),
    $lettres,
    $lettres
);
exit( 0 );
