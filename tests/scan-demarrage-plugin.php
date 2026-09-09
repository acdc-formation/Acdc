<?php
/**
 * Une migration ne doit jamais pouvoir faire taire l'application.
 *
 * Relevé en production le 15 août 2026, après l'installation d'une version :
 * les pages de l'extranet affichaient `[acdc_of_portal tab="dashboard"]` en
 * toutes lettres au lieu du tableau de bord. Les guillemets y étaient déformés
 * en « » — la signature d'un raccourci que WordPress ne connaît pas et
 * qu'il traite comme du texte ordinaire.
 *
 * LA CAUSE ÉTAIT UN ORDRE. `maybe_upgrade` et `register_shortcodes` étaient
 * tous deux accrochés à `init` en priorité 10, et WordPress exécute les
 * égalités dans l'ordre d'inscription : la migration passait donc avant les
 * raccourcis. Tout ce qui retardait ou interrompait la migration — la recopie
 * complète de la base, deux écritures concurrentes sur les mêmes tables, un
 * dépassement du temps d'exécution — empêchait l'enregistrement des raccourcis.
 *
 * CE QUI A RENDU L'INCIDENT DURABLE, c'est le cache : la page rendue pendant
 * cette fenêtre a été mise en cache par le serveur et resservie longtemps après
 * que tout soit rentré dans l'ordre. Une seconde de désordre, figée. Réinstaller
 * le plugin — donc vider le cache — a suffi à tout remettre d'aplomb, ce qui
 * rendait la panne parfaitement irreproductible.
 *
 * AUCUNE ERREUR N'A ÉTÉ LEVÉE. Ni page blanche, ni message, ni e-mail
 * d'alerte de WordPress, ni ligne dans le journal du plugin. C'est la forme de
 * panne la plus coûteuse : l'écran ne dit pas qu'il est cassé, il affiche
 * simplement autre chose.
 *
 * Ce balayage tient trois promesses :
 *   1. les raccourcis se posent AVANT la migration ;
 *   2. les migrations lourdes vivent SOUS le verrou de mise à jour, et non sur
 *      le chemin de chaque affichage de page ;
 *   3. une mise à jour qui aboutit jette le cache derrière elle.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$hits = array();

/* ── 1. L'ORDRE DES DEUX CROCHETS ────────────────────────────────────────── */
$plugin = $root . '/includes/class-acdc-plugin.php';
if ( ! is_readable( $plugin ) ) {
    echo "class-acdc-plugin.php introuvable.\n";
    exit( 1 );
}
$src = (string) file_get_contents( $plugin );

$priorite = function ( $methode ) use ( $src ) {
    $motif = "/add_action\(\s*'init'\s*,\s*array\(\s*\\\$this\s*,\s*'" . preg_quote( $methode, '/' ) . "'\s*\)\s*(?:,\s*(\d+))?\s*\)/";
    if ( ! preg_match( $motif, $src, $m, PREG_OFFSET_CAPTURE ) ) {
        return null;
    }
    return array(
        'priorite' => isset( $m[1] ) ? (int) $m[1][0] : 10,
        'position' => (int) $m[0][1],
    );
};

$raccourcis = $priorite( 'register_shortcodes' );
$migration  = $priorite( 'maybe_upgrade' );

if ( null === $raccourcis ) {
    $hits[] = 'register_shortcodes n’est plus accroché à `init` : aucun raccourci du plugin ne sera reconnu, et les pages afficheront leur code entre crochets.';
} elseif ( null === $migration ) {
    $hits[] = 'maybe_upgrade n’est plus accroché à `init` : le schéma de base ne sera plus mis à jour.';
} else {
    /* À priorité égale, WordPress suit l'ordre d'inscription. */
    $raccourcis_avant = ( $raccourcis['priorite'] < $migration['priorite'] )
        || ( $raccourcis['priorite'] === $migration['priorite'] && $raccourcis['position'] < $migration['position'] );
    if ( ! $raccourcis_avant ) {
        $hits[] = sprintf(
            'La migration de base (priorité %d) passe avant l’enregistrement des raccourcis (priorité %d) : si elle traîne ou échoue, l’extranet affichera « [acdc_of_portal …] » en toutes lettres, sans le moindre message d’erreur.',
            $migration['priorite'],
            $raccourcis['priorite']
        );
    }
}

/* ── 2. LES MIGRATIONS LOURDES SOUS LE VERROU ────────────────────────────── */
$core = $root . '/includes/kernel/class-acdc-kernel-core-trait.php';
if ( is_readable( $core ) ) {
    $csrc  = (string) file_get_contents( $core );
    $debut = strpos( $csrc, 'public function maybe_upgrade()' );
    if ( false !== $debut ) {
        $fin  = preg_match( '/\n\s{0,4}(?:public|private|protected)\s+function\s/', $csrc, $m, PREG_OFFSET_CAPTURE, $debut + 30 )
            ? $m[0][1]
            : strlen( $csrc );
        $bloc = substr( $csrc, $debut, $fin - $debut );

        /* Le corps se coupe en deux : sous le verrou, et après. */
        $verrou = strpos( $bloc, "'acquired' === \$lock" );
        $apres  = false !== $verrou ? strpos( $bloc, '} finally {', $verrou ) : false;

        /* Ces deux-là écrivent en base sur toutes les formations et suppriment
           une table : elles n'ont rien à faire sur le chemin d'un affichage. */
        foreach ( array( 'backfill_qualiopi_toggles_default_state', 'retire_legacy_positioning_test_module' ) as $lourde ) {
            $pos = strpos( $bloc, $lourde . '(' );
            if ( false === $pos ) { continue; }
            if ( false === $verrou || false === $apres || $pos < $verrou || $pos > $apres ) {
                $hits[] = sprintf(
                    '%s() est appelée hors du verrou de mise à jour : elle sera réexaminée à chaque affichage de page, y compris pendant que la mise à jour recopie déjà la base.',
                    $lourde
                );
            }
        }

        /* 3. Le cache doit être jeté après une mise à jour aboutie. */
        if ( false === strpos( $bloc, 'litespeed_purge_all' ) && false === strpos( $bloc, 'wp_cache_flush' ) ) {
            $hits[] = 'La mise à jour ne vide plus le cache : une page rendue pendant la migration peut être resservie indéfiniment, longtemps après que tout soit rentré dans l’ordre. C’est ce qui a rendu l’incident du 15 août permanent — et irreproductible.';
        }
    }
}

if ( $hits ) {
    echo "Démarrage du plugin :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 occurrence — les raccourcis se posent avant la migration, les migrations lourdes restent sous le verrou, et le cache est jeté après coup.\n";
exit( 0 );
