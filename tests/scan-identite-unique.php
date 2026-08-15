<?php
/**
 * L'identité de l'organisme ne se recopie pas : elle s'appelle.
 *
 * « Je veux que lorsque je changerai le SIRET, le NDA, le nom du dirigeant,
 * tout soit changé partout. » Trois choses l'en empêchaient, et ce balayage
 * garde les trois fermées.
 *
 *   1. UNE VALEUR D'IDENTITÉ ÉCRITE EN DUR. Le SIRET, le NDA, l'adresse et les
 *      coordonnées figuraient dans une quinzaine de documents — facture,
 *      programme de formation, PDF de résultat de quiz, recueil du besoin — qui
 *      n'ouvraient aucun réglage. Aucun ne changerait jamais.
 *
 *   2. UNE CLÉ QUI N'EXISTE PAS. Le code demandait « siret », « nda »,
 *      « nda_number », « company_name », « email », « phone », « website »,
 *      « zip » ; la fiche enregistre « siret_identification »,
 *      « activity_declaration_number », « enterprise »,
 *      « enterprise_contact_email »… La condition échouait donc TOUJOURS et
 *      c'était la valeur de repli qui s'affichait. C'est la forme la plus
 *      dangereuse : le code a l'air de lire les réglages, le document a l'air
 *      juste, et rien ne signale que le réglage ne sert à rien.
 *
 *   3. UNE VALEUR PAR DÉFAUT QUI RESSUSCITE. Le NDA était aussi la valeur par
 *      défaut de son propre champ : le vider n'avait aucun effet, il revenait
 *      au chargement suivant. Impossible d'abandonner une ancienne identité.
 *
 * Ce balayage lit les fichiers, pas la base : il ne sait pas ce qui s'affiche.
 * Il sait seulement qu'une identité recopiée finit toujours par diverger de
 * l'originale — et c'est suffisant pour l'interdire.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';
$hits = array();

/* ── LES CLÉS QUE LA FICHE ENREGISTRE VRAIMENT ────────────────────────── */

$fichier_fiche = $root . '/includes/settings-catalog/class-acdc-settings-catalog-core-trait.php';
if ( ! is_readable( $fichier_fiche ) ) {
    echo "La fiche entreprise est introuvable : le balayage ne prouve rien.\n";
    exit( 1 );
}
$src_fiche = (string) file_get_contents( $fichier_fiche );
$vraies    = array();
if ( preg_match( '/function get_company_profile_defaults.*?return array\((.*?)\n    \);/s', $src_fiche, $m ) ) {
    if ( preg_match_all( "/'([a-z0-9_]+)'\s*=>/", $m[1], $k ) ) {
        $vraies = array_flip( $k[1] );
    }
}
if ( count( $vraies ) < 20 ) {
    $hits[] = sprintf( 'seules %d clés de la fiche entreprise ont été relues : la comparaison qui suit ne prouve rien.', count( $vraies ) );
}

/* ── 1. AUCUNE IDENTITÉ ÉCRITE EN DUR ─────────────────────────────────── */

/* Les motifs sont des FORMES, pas des valeurs : un SIRET est un SIRET quel que
   soit le numéro, et un balayage qui ne connaîtrait que l'ancien laisserait
   passer le nouveau. Le NDA d'un organisme de formation s'écrit
   « 93 83 08347 83 » : deux chiffres de région, deux de département, cinq de
   rang, deux de contrôle. */
$motifs = array(
    '/\b\d{3}\s?\d{3}\s?\d{3}\s?\d{5}\b/'                => 'un SIRET',
    '/\b\d{2}\s\d{2}\s\d{5}\s\d{2}\b/'                   => 'un numéro de déclaration d’activité',
    '/\bFR\d{2}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{3}\b/' => 'un IBAN',
    '/\b0[1-9](?:[ .]\d{2}){4}\b/'                       => 'un numéro de téléphone',
    '/[\w.+-]+@(?:acdc|acdcformation)[\w.-]*\.\w+/i'      => 'une adresse e-mail de l’organisme',
);

/* Un numéro de remplissage n'est l'identité de personne : « 06 00 00 00 00 »
   dans un formulaire montre la forme attendue, il ne se retrouve sur aucun
   document. On les reconnaît à leur régularité. */
$remplissages = array( '/^0\d(?:00){4}$/', '/^0\d12345678$/', '/^0123456789$/' );

/* Là où l'identité a le droit de figurer, et seulement là. */
$tolerés = array(
    /* La migration, datée, qui a fait passer l'identité du code à la fiche. */
    'includes/kernel/class-acdc-kernel-core-trait.php' => 'backfill_org_identity_from_code',
    /* Le formateur de SIRET, dont les exemples sont la documentation. */
    'src/Support/Siret.php' => null,
    /* Les jeux de démonstration affichés quand la base est vide. */
    'includes/documents-billing/class-acdc-documents-billing-core-trait.php' => 'demo',
    /* Les contacts nommés du programme : choix explicite de l'exploitant,
       à reprendre plus tard. */
    'includes/settings-catalog/class-acdc-settings-catalog-programme-pdf-trait.php' => 'get_prog_pdf_contacts',
);

/* Ces fonctions ne décrivent pas l'organisme : elles décrivent des tiers — les
   financeurs et leurs numéros, un modèle d'import, un jeu de démonstration.
   Y interdire un numéro de téléphone n'aurait aucun sens. */
$hors_sujet = array(
    'get_default_funder_dataset',
    'maybe_import_psh_default_partners',
    'handle_prospects_xlsx_template',
    'get_mock_invoices_data',
);

$fichiers = array();
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/includes' ) );
foreach ( $it as $f ) {
    if ( 'php' === $f->getExtension() ) {
        $fichiers[] = $f->getPathname();
    }
}
$fichiers[] = $root . '/src/Support/OrgIdentity.php';

foreach ( $fichiers as $chemin ) {
    $relatif = str_replace( $root . '/', '', $chemin );
    $lignes  = file( $chemin );
    $fonction = '';
    foreach ( $lignes as $i => $ligne ) {
        if ( preg_match( '/function\s+([a-z0-9_]+)\s*\(/i', $ligne, $mf ) ) {
            $fonction = $mf[1];
        }
        /* Un commentaire qui cite une valeur explique un défaut passé ; il ne
           l'affiche à personne. */
        $nu = trim( $ligne );
        if ( '' === $nu || 0 === strpos( $nu, '*' ) || 0 === strpos( $nu, '//' ) || 0 === strpos( $nu, '/*' ) ) {
            continue;
        }
        if ( in_array( $fonction, $hors_sujet, true ) ) {
            continue;
        }
        foreach ( $motifs as $motif => $quoi ) {
            if ( ! preg_match( $motif, $ligne, $trouve ) ) {
                continue;
            }
            if ( 'un numéro de téléphone' === $quoi ) {
                $chiffres = preg_replace( '/\D/', '', $trouve[0] );
                foreach ( $remplissages as $r ) {
                    if ( preg_match( $r, $chiffres ) ) {
                        continue 2;
                    }
                }
            }
            if ( array_key_exists( $relatif, $tolerés ) ) {
                $zone = $tolerés[ $relatif ];
                if ( null === $zone || false !== strpos( $fonction, $zone ) || false !== strpos( strtolower( $ligne ), $zone ) ) {
                    continue 2;
                }
            }
            $hits[] = sprintf(
                '%s:%d — %s est écrit en dur dans %s() : ce document ne suivra pas un changement de SIRET, de NDA ou de coordonnées.',
                $relatif,
                $i + 1,
                $quoi,
                '' !== $fonction ? $fonction : '?'
            );
            continue 2;
        }
    }
}

/* ── 2. AUCUNE LECTURE D'UNE CLÉ QUI N'EXISTE PAS ─────────────────────── */

foreach ( $fichiers as $chemin ) {
    $relatif = str_replace( $root . '/', '', $chemin );
    $lignes  = file( $chemin );
    $suivies = array();
    foreach ( $lignes as $i => $ligne ) {
        /* Toute réaffectation invalide la variable : on ne suit que ce qui
           vient RÉELLEMENT de la fiche entreprise. Une première version de ce
           contrôle suivait le nom de la variable d'un bout à l'autre du
           fichier et accusait quinze lectures innocentes. */
        if ( preg_match_all( '/\$([a-z_0-9]+)\s*=[^=]/', $ligne, $aa ) ) {
            foreach ( $aa[1] as $v ) {
                $suivies[ $v ] = (bool) preg_match(
                    '/\$' . preg_quote( $v, '/' ) . "\s*=\s*(get_option\(\s*'acdc_of_company_profile'|\\\$this->get_company_profile_options\()/",
                    $ligne
                );
            }
        }
        foreach ( $suivies as $v => $est_fiche ) {
            if ( ! $est_fiche ) {
                continue;
            }
            if ( preg_match_all( '/\$' . preg_quote( $v, '/' ) . "\[\s*'([a-z0-9_]+)'\s*\]/", $ligne, $kk ) ) {
                foreach ( array_unique( $kk[1] ) as $cle ) {
                    if ( ! isset( $vraies[ $cle ] ) ) {
                        $hits[] = sprintf(
                            '%s:%d — la fiche entreprise est interrogée sur « %s », une clé qu’elle n’enregistre pas : la condition échoue toujours et c’est la valeur de repli qui s’affiche.',
                            $relatif,
                            $i + 1,
                            $cle
                        );
                    }
                }
            }
        }
    }
}

/* ── 3. AUCUNE VALEUR PAR DÉFAUT QUI RESSUSCITE ───────────────────────── */

$champs_identite = array(
    'siret_identification',
    'activity_declaration_number',
    'enterprise',
    'address',
    'postal_code',
    'city',
    'enterprise_contact_email',
    'enterprise_contact_phone',
);
foreach ( $champs_identite as $champ ) {
    if ( preg_match( "/'" . preg_quote( $champ, '/' ) . "'\s*=>\s*'([^']+)'/", $src_fiche, $m ) ) {
        $hits[] = sprintf(
            'la fiche entreprise propose « %s » par défaut pour le champ « %s » : vider ce champ n’aurait aucun effet, la valeur reviendrait au chargement suivant.',
            $m[1],
            $champ
        );
    }
}

/* ── 4. LA SOURCE UNIQUE EXISTE ET EST APPELÉE ────────────────────────── */

$noyau = $root . '/includes/kernel/class-acdc-kernel-core-trait.php';
$src_noyau = is_readable( $noyau ) ? (string) file_get_contents( $noyau ) : '';
if ( ! preg_match( '/function acdc_org_identity\(/', $src_noyau ) ) {
    $hits[] = 'la source unique acdc_org_identity() a disparu : chaque écran devra de nouveau arbitrer seul entre les deux fiches.';
}
if ( ! is_readable( $root . '/src/Support/OrgIdentity.php' ) ) {
    $hits[] = 'src/Support/OrgIdentity.php a disparu : la règle des alias et des champs vides n’est plus vérifiable.';
}
$appels = 0;
foreach ( $fichiers as $chemin ) {
    $appels += substr_count( (string) file_get_contents( $chemin ), 'acdc_org_identity()' );
}
if ( $appels < 25 ) {
    $hits[] = sprintf(
        'la source unique n’est appelée que %d fois : les documents ont recommencé à lire les réglages chacun de leur côté.',
        $appels
    );
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
    "Identité unique : aucune valeur en dur, aucune clé fantôme, aucune valeur par défaut qui ressuscite, %d appels à la source unique.\n",
    $appels
);
exit( 0 );
