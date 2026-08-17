<?php
/**
 * Une colonne ajoutée sans migration, et quatre autres pièges de la 3.25.309.
 *
 * POURQUOI CE BALAYAGE EXISTE. J'ai ajouté une colonne « entity_label » à la
 * table des demandes de signature, dans le CREATE TABLE, et j'ai livré. Mes
 * 71 vérifications étaient vertes. Sur une installation NEUVE, tout marchait.
 *
 * Sur l'installation de David, la colonne n'aurait jamais été créée : le module
 * de signature ne relance dbDelta que si sa constante VERSION change, et je ne
 * l'avais pas touchée. Chaque demande de signature écrit cette colonne. Elles
 * auraient toutes échoué, en silence — plus une seule convention, plus un seul
 * devis, plus un seul contrat formateur signable.
 *
 * Aucun test unitaire ne pouvait voir ça : le défaut n'est pas dans une
 * fonction, il est dans l'écart entre deux fichiers. C'est exactement ce qu'un
 * balayage sait regarder.
 *
 * Les cinq règles ci-dessous ont chacune été vérifiées EN LA SABOTANT.
 */

$racine = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : 'acdc-formation-saas-organisme-de-formation';
if ( ! is_dir( $racine ) ) {
    fwrite( STDERR, "Répertoire introuvable : {$racine}\n" );
    exit( 2 );
}

$alertes = array();
$signale = function ( $fichier, $texte ) use ( &$alertes ) {
    $alertes[] = sprintf( '%s — %s', $fichier, $texte );
};

/* --------------------------------------------------------------------------
 * 1. AUCUN COMMENTAIRE À L'INTÉRIEUR D'UN « CREATE TABLE »
 *
 * dbDelta n'est pas un moteur SQL : il découpe la définition LIGNE PAR LIGNE et
 * prend chaque ligne pour une colonne. Un commentaire de huit lignes posé au
 * milieu devient huit colonnes à créer, et autant d'ALTER TABLE invalides
 * rejoués à chaque mise à jour. J'ai fait exactement ça.
 * ----------------------------------------------------------------------- */
$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $racine . '/includes' ) );
foreach ( $rii as $f ) {
    if ( ! $f->isFile() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $chemin = $f->getPathname();
    $court  = substr( $chemin, strlen( $racine ) + 1 );
    $src    = (string) file_get_contents( $chemin );
    $offset = 0;
    /* On ne cherche que les VRAIES définitions : celles qui ouvrent une chaîne
       SQL. Sans le guillemet, les mots « CREATE TABLE » cités dans un
       commentaire déclenchaient l'analyse d'un bloc de code quelconque — et le
       premier commentaire venu y ressemblait à une faute. Le sabotage l'a
       montré : un balayage qui crie sur du texte ordinaire finit ignoré. */
    while ( false !== ( $deb = strpos( $src, '"CREATE TABLE', $offset ) ) ) {
        $offset = $deb + 13;
        /* La définition va jusqu'à la parenthèse fermante suivie du point-virgule
           ou du guillemet de fin de chaîne. On se contente d'un bloc généreux :
           un commentaire de définition sera dedans. */
        $fin = strpos( $src, ') $charset_collate', $deb );
        if ( false === $fin ) { $fin = strpos( $src, ');', $deb ); }
        if ( false === $fin || $fin - $deb > 6000 ) { continue; }
        $bloc = substr( $src, $deb, $fin - $deb );
        if ( false !== strpos( $bloc, '/*' ) || false !== strpos( $bloc, '--' ) ) {
            $ligne = substr_count( substr( $src, 0, $deb ), "\n" ) + 1;
            $signale( $court . ':' . $ligne,
                "un commentaire est posé À L'INTÉRIEUR d'un CREATE TABLE. dbDelta lit ligne par ligne : chacune sera prise pour une colonne à créer. Le commentaire va au-dessus de la fonction." );
        }
    }
}

/* --------------------------------------------------------------------------
 * 2. LA TABLE DES SIGNATURES : SES COLONNES ET SA VERSION VONT ENSEMBLE
 *
 * L'empreinte ci-dessous est le NOMBRE de colonnes de la table des demandes.
 * Si vous ajoutez ou retirez une colonne, ce balayage échoue — et c'est le but :
 * il vous oblige à passer par la constante VERSION, sans laquelle la colonne ne
 * sera jamais créée sur les installations existantes.
 *
 * Pour le corriger : incrémentez self::VERSION dans class-acdc-sig-core.php,
 * PUIS mettez à jour les deux nombres ici.
 * ----------------------------------------------------------------------- */
$attendu_colonnes = 25;
$attendu_version  = '2.1.0';

$sig = $racine . '/includes/signature/class-acdc-sig-core.php';
$src = (string) file_get_contents( $sig );

if ( ! preg_match( "/const VERSION\s*=\s*'([^']+)'/", $src, $m ) ) {
    $signale( 'includes/signature/class-acdc-sig-core.php', "la constante VERSION a disparu : plus rien ne déclenche les migrations du module de signature." );
} else {
    $version = $m[1];
    $deb = strpos( $src, '$sql_requests = "CREATE TABLE' );
    if ( false === $deb ) {
        $signale( 'includes/signature/class-acdc-sig-core.php', "la définition de la table des demandes est introuvable." );
    } else {
        $fin  = strpos( $src, 'PRIMARY KEY', $deb );
        $bloc = substr( $src, $deb, $fin - $deb );
        /* Une colonne = une ligne « nom TYPE ». On compte les lignes qui
           déclarent un type SQL, ce qui ignore l'en-tête et les blancs. */
        $colonnes = preg_match_all( '/^\s*[a-z_]+\s+(BIGINT|INT|VARCHAR|CHAR|TEXT|LONGTEXT|DATETIME|DATE|TINYINT|SMALLINT|DECIMAL)/mi', $bloc );

        if ( $colonnes !== $attendu_colonnes ) {
            $signale( 'includes/signature/class-acdc-sig-core.php',
                sprintf(
                    "la table des demandes de signature compte %d colonnes, ce balayage en attendait %d. Si c'est voulu : incrémentez self::VERSION (actuellement « %s ») — SANS QUOI LA COLONNE NE SERA JAMAIS CRÉÉE sur les installations existantes, et chaque demande de signature échouera en silence — puis mettez à jour les nombres dans ce fichier.",
                    $colonnes, $attendu_colonnes, $version
                ) );
        }
        if ( $version !== $attendu_version ) {
            $signale( 'includes/signature/class-acdc-sig-core.php',
                sprintf( "VERSION vaut « %s », ce balayage attendait « %s ». Mettez à jour ce fichier pour enregistrer la migration.", $version, $attendu_version ) );
        }
    }
}

/* --------------------------------------------------------------------------
 * 3. UN TAUX HÉRITÉ NE SE FAIT PAS ÉCRASER PAR LE PROFIL
 *
 * Un devis ancien à 0 %, sans régime, converti en facture : sans ce garde-fou,
 * il tombait sur le régime du profil — 20 % — et le client recevait une facture
 * plus chère que le devis qu'il avait accepté.
 * ----------------------------------------------------------------------- */
$fact = $racine . '/includes/documents-billing/class-acdc-documents-billing-core-trait.php';
$src  = (string) file_get_contents( $fact );
$deb  = strpos( $src, 'function acdc_figer_regime_tva(' );
if ( false === $deb ) {
    $signale( 'includes/documents-billing/class-acdc-documents-billing-core-trait.php', "la porte de gel du régime de TVA a disparu." );
} else {
    $corps = substr( $src, $deb, 1600 );
    if ( ! preg_match( "/isset\(\s*\\\$data\['vat_rate'\]\s*\)\s*&&\s*''\s*!==\s*\(string\)\s*\\\$data\['vat_rate'\]/", $corps ) ) {
        $signale( 'includes/documents-billing/class-acdc-documents-billing-core-trait.php',
            "acdc_figer_regime_tva() ne préserve plus un taux transmis par l'appelant : un devis ancien à 0 % converti en facture repasserait à 20 %, et le client serait facturé plus cher que ce qu'il a signé." );
    }
}

/* --------------------------------------------------------------------------
 * 4. LE MOTEUR NE TOURNE PAS PENDANT UN ENREGISTREMENT
 *
 * « admin_init » se déclenche aussi sur admin-post.php, donc au début du
 * traitement de chaque formulaire. Le moindre caractère émis avant le
 * wp_redirect() final le fait échouer.
 * ----------------------------------------------------------------------- */
$wf  = $racine . '/includes/workflow/class-acdc-workflow-engine-trait.php';
$src = (string) file_get_contents( $wf );
$deb = strpos( $src, 'function acdc_wf_cron_en_admin(' );
if ( false === $deb ) {
    $signale( 'includes/workflow/class-acdc-workflow-engine-trait.php', "la relance du moteur depuis l'administration a disparu." );
} else {
    $corps = substr( $src, $deb, 2000 );
    /* On cherche la FORME DU CODE, pas le mot. Le sabotage a montré qu'un
       commentaire citant « admin-post.php » — et celui de cette fonction le
       cite — suffisait à satisfaire un simple strpos. Un contrôle qu'un
       commentaire peut contenter ne contrôle rien. */
    if ( ! preg_match( "/in_array\(\s*\\\$__script\s*,\s*array\(\s*'admin-post\\.php'/", $corps ) ) {
        $signale( 'includes/workflow/class-acdc-workflow-engine-trait.php',
            "le moteur ne s'écarte plus de admin-post.php : il tournerait AVANT le gestionnaire de chaque formulaire enregistré, et sa moindre sortie casserait la redirection finale." );
    }
}

/* --------------------------------------------------------------------------
 * 5. UN CERTIFICAT LISIBLE RESTE INDEVINABLE
 *
 * Rendre un nom de fichier lisible ne doit pas le rendre atteignable : ces PDF
 * portent le nom du signataire, son e-mail, son IP et l'image de sa signature,
 * et leur dossier n'est pas protégé par .htaccess.
 * ----------------------------------------------------------------------- */
$pdf = $racine . '/includes/signature/class-acdc-sig-pdf.php';
$src = (string) file_get_contents( $pdf );
$deb = strpos( $src, 'function build_audit_pdf_filename(' );
if ( false === $deb ) {
    $signale( 'includes/signature/class-acdc-sig-pdf.php', "la fabrique du nom de certificat a disparu." );
} else {
    $corps = substr( $src, $deb, 2600 );
    if ( false === strpos( $corps, 'wp_hash(' ) ) {
        $signale( 'includes/signature/class-acdc-sig-pdf.php',
            "le nom du certificat ne porte plus de condensat : son adresse redevient devinable à partir du nom de l'entreprise et de la date, et ce PDF contient le nom, l'e-mail, l'IP et la signature du signataire." );
    }
}

/* --------------------------------------------------------------------------
 * 6. L'ÉMARGEMENT : SES COLONNES ET SON NUMÉRO DE SCHÉMA VONT ENSEMBLE
 *
 * LA MÊME FAUTE, DEUX FOIS. La règle 2 ci-dessus surveille le module de
 * signature parce que j'y avais ajouté « entity_label » sans toucher à sa
 * VERSION. Quatre versions plus tard, j'ai ajouté « signature_token » à la
 * table d'émargement sans toucher à sa DB_VERSION — et cette page-là ne
 * surveillait que le module de signature. Le balayage voyait la faute qu'il
 * connaissait, pas la faute qu'il décrivait.
 *
 * Conséquence sur l'installation de David : maybe_install() sort immédiatement
 * quand le numéro stocké est identique, dbDelta n'aurait jamais tourné, la
 * colonne n'aurait jamais existé, et create_emarg_session() — qui l'écrit à
 * chaque appel — aurait échoué. Plus une seule feuille d'émargement ouvrable.
 *
 * Pour corriger une alerte d'ici : incrémentez ACDC_Emargement::DB_VERSION,
 * ajoutez le maybe_add_column() correspondant dans install(), PUIS mettez à
 * jour les trois nombres ci-dessous.
 * ----------------------------------------------------------------------- */
$emarg_attendu_sessions = 22;
$emarg_attendu_learners = 18;
$emarg_attendu_version  = '3.25.314';

$emarg_core = $racine . '/includes/emargement/class-acdc-emarg-core.php';
$emarg_head = $racine . '/includes/class-acdc-emargement.php';
$src_core   = (string) file_get_contents( $emarg_core );
$src_head   = (string) file_get_contents( $emarg_head );

$compte_colonnes = function ( $src, $ancre ) {
    $deb = strpos( $src, $ancre );
    if ( false === $deb ) { return null; }
    $fin = strpos( $src, 'PRIMARY KEY', $deb );
    if ( false === $fin ) { return null; }
    $bloc = substr( $src, $deb, $fin - $deb );
    return preg_match_all( '/^\s*[a-z_]+\s+(BIGINT|INT|VARCHAR|CHAR|TEXT|LONGTEXT|DATETIME|DATE|TINYINT|SMALLINT|DECIMAL)/mi', $bloc );
};

if ( ! preg_match( "/const DB_VERSION\s*=\s*'([^']+)'/", $src_head, $m ) ) {
    $signale( 'includes/class-acdc-emargement.php', "la constante DB_VERSION a disparu : plus rien ne déclenche les migrations du module d'émargement." );
} else {
    $emarg_version = $m[1];

    $n_sessions = $compte_colonnes( $src_core, '$sql_sessions = "CREATE TABLE' );
    $n_learners = $compte_colonnes( $src_core, '$sql_learners = "CREATE TABLE' );

    if ( null === $n_sessions || null === $n_learners ) {
        $signale( 'includes/emargement/class-acdc-emarg-core.php', "une des deux définitions de table d'émargement est introuvable." );
    } else {
        if ( $n_sessions !== $emarg_attendu_sessions ) {
            $signale( 'includes/emargement/class-acdc-emarg-core.php',
                sprintf(
                    "la table des feuilles d'émargement compte %d colonnes, ce balayage en attendait %d. Si c'est voulu : incrémentez ACDC_Emargement::DB_VERSION (actuellement « %s ») ET ajoutez le maybe_add_column() correspondant — SANS QUOI LA COLONNE NE SERA JAMAIS CRÉÉE sur les installations existantes, et plus aucune feuille ne pourra être ouverte — puis mettez à jour les nombres dans ce fichier.",
                    $n_sessions, $emarg_attendu_sessions, $emarg_version
                ) );
        }
        if ( $n_learners !== $emarg_attendu_learners ) {
            $signale( 'includes/emargement/class-acdc-emarg-core.php',
                sprintf(
                    "la table des apprenants émargés compte %d colonnes, ce balayage en attendait %d. Même règle : DB_VERSION (actuellement « %s ») et maybe_add_column() vont avec.",
                    $n_learners, $emarg_attendu_learners, $emarg_version
                ) );
        }
    }

    if ( $emarg_version !== $emarg_attendu_version ) {
        $signale( 'includes/class-acdc-emargement.php',
            sprintf( "DB_VERSION vaut « %s », ce balayage attendait « %s ». Mettez à jour ce fichier pour enregistrer la migration.", $emarg_version, $emarg_attendu_version ) );
    }
}

/* --------------------------------------------------------------------------
 * 7. UNE COLONNE AJOUTÉE APRÈS COUP NAÎT VIDE — ET UN JETON VIDE N'OUVRE RIEN
 *
 * Le corollaire de la règle 6, et il est de sécurité. « signature_token » est
 * NOT NULL DEFAULT '' : sur toutes les feuilles antérieures à la migration, il
 * vaut la chaîne vide. Sans refus explicite, l'adresse de signature SANS clé
 * ouvre la première feuille venue restée sans jeton — et livre à n'importe quel
 * visiteur la liste nominative d'une séance.
 * ----------------------------------------------------------------------- */
$deb = strpos( $src_core, 'function get_by_signature_token(' );
if ( false === $deb ) {
    $signale( 'includes/emargement/class-acdc-emarg-core.php', "la porte de la page de signature a disparu." );
} else {
    $corps = substr( $src_core, $deb, 900 );
    /* On cherche la FORME : un refus AVANT la requête. Le commentaire au-dessus
       de la fonction parle de jeton vide ; un simple strpos aurait été content
       de lui. Le sabotage l'a confirmé. */
    if ( ! preg_match( "/if\s*\(\s*''\s*===\s*\\\$token\s*\)\s*\{\s*return\s+null\s*;/", $corps ) ) {
        $signale( 'includes/emargement/class-acdc-emarg-core.php',
            "get_by_signature_token() ne refuse plus un jeton vide : l'adresse de signature sans clé ouvrirait une feuille antérieure à la migration, dont le jeton vaut la chaîne vide, et montrerait la liste nominative de la séance à un visiteur quelconque." );
    }
}

if ( false === strpos( $src_core, 'function jeton_signature(' ) ) {
    $signale( 'includes/emargement/class-acdc-emarg-core.php',
        "le filet de rattrapage jeton_signature() a disparu. La migration ne repasse jamais — son numéro de schéma est écrit AVANT le travail : une feuille restée sans jeton n'en recevrait plus jamais, et son QR mènerait à une adresse sans clé." );
}

/* --------------------------------------------------------------------------
 * VERDICT
 * ----------------------------------------------------------------------- */
echo "\n";
if ( $alertes ) {
    foreach ( $alertes as $a ) { echo "ALERTE  {$a}\n"; }
    printf( "%d alerte(s)\n", count( $alertes ) );
    exit( 1 );
}
echo "Schéma et migrations : aucune colonne sans migration, aucun commentaire dans un CREATE TABLE, aucun taux écrasé, aucun certificat devinable.\n";
exit( 0 );
