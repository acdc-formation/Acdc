<?php
/**
 * ACDC 3.25.316 — Le contrôle de complétude reçoit-il ce qu'il réclame ?
 *
 * CE QUE CE BALAYAGE DÉFEND, ET POURQUOI IL N'EXISTAIT PAS.
 *
 * « test-convention-complete.php » vérifie la LOGIQUE du contrôleur : ce qui
 * bloque bloque, ce qui signale n'empêche rien. Il est juste, il est vert, et il
 * n'a rien vu — parce qu'il fabrique lui-même son tableau d'entrée. Il prouvait
 * que le contrôleur sait refuser une convention sans financeur ; il ne pouvait
 * pas prouver que le HANDLER lui donne le financeur.
 *
 * Or il ne le lui donnait pas. Le handler passait le POST BRUT, où cinq des
 * clés réclamées n'existent sous aucune forme :
 *
 *   — « company_id »  : la liste visible poste « source_prospect_id » ;
 *   — « start_date », « end_date » : dérivées des séances, jamais postées ;
 *   — « vat_rate »    : affiché seulement, il vient du profil de l'organisme ;
 *   — « funder_id »   : résolu par le plan de financement, calculé APRÈS.
 *
 * Résultat : entre la 3.25.302 et la 3.25.316, aucune convention ne pouvait être
 * enregistrée, et le refus nommait des manques qui étaient à l'écran.
 *
 * La leçon tient en une phrase : un test qui construit lui-même son entrée ne
 * dit rien de l'appelant. Ce balayage ferme cette porte — pour chaque champ que
 * le contrôleur exige, il vérifie qu'une source existe : soit le handler le
 * fournit explicitement, soit le formulaire le poste sous ce nom.
 *
 * Éprouvé en sabotant : retirer une clé du handler, ou remettre « $input » à la
 * place du tableau résolu, fait échouer ce balayage.
 */

define( 'ACDC_SUPPORT_TESTING', true );

$racine  = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation';
$support = $racine . '/src/Support/ConventionCompleteness.php';
$handler = $racine . '/includes/dossiers-contracts/class-acdc-dossiers-contracts-actions-trait.php';
$rendu   = $racine . '/includes/dossiers-contracts/class-acdc-dossiers-contracts-render-trait.php';

foreach ( array( $support, $handler, $rendu ) as $f ) {
    if ( ! is_readable( $f ) ) {
        fwrite( STDERR, "Fichier introuvable : $f\n" );
        exit( 1 );
    }
}

require_once $support;
use ACDC\Support\ConventionCompleteness as CC;

$src_handler = (string) file_get_contents( $handler );
$src_rendu   = (string) file_get_contents( $rendu );

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) {
        $echecs[] = $message;
    }
};

/* Le corps de handle_save_registration_contract(), borné à la fonction
   suivante : sans cette borne, toute recherche déborde et trouve ce qu'elle
   veut ailleurs dans un fichier de plusieurs milliers de lignes. */
$debut = strpos( $src_handler, 'public function handle_save_registration_contract()' );
$exiger( false !== $debut, 'handle_save_registration_contract() est introuvable.' );
if ( false === $debut ) {
    foreach ( $echecs as $e ) { fwrite( STDERR, "ÉCHEC — $e\n" ); }
    exit( 1 );
}
$reste = substr( $src_handler, $debut );
if ( preg_match( '/\n  (?:public|private|protected) function /', $reste, $m, PREG_OFFSET_CAPTURE, 10 ) ) {
    $corps = substr( $reste, 0, $m[0][1] );
} else {
    $corps = $reste;
}

/* LE CODE SANS SES COMMENTAIRES.
   Un balayage qui cherche « acdc_contract_funding_plan( » dans le source trouve
   aussi la phrase du commentaire qui EXPLIQUE la correction — et conclut que
   l'appel est là quand il ne l'est plus. On a déjà fait cette faute une fois
   dans « scan-verrou-signatures.php » ; on ne la refait pas ici. L'analyseur
   lexical de PHP tranche là où une expression régulière se trompe. */
$sans_commentaires = '';
foreach ( token_get_all( '<?php ' . $corps ) as $jeton ) {
    if ( is_array( $jeton ) ) {
        if ( T_COMMENT === $jeton[0] || T_DOC_COMMENT === $jeton[0] ) {
            continue;
        }
        $sans_commentaires .= $jeton[1];
        continue;
    }
    $sans_commentaires .= $jeton;
}
$corps = $sans_commentaires;

/* 1. Le contrôle ne doit PAS recevoir le POST brut. C'est la faute d'origine :
      « $input » contient ce que la page envoie, pas ce que le handler résout. */
$exiger(
    ! preg_match( '/ConventionCompleteness::verifier\(\s*\$input\s*\)/', $corps ),
    'Le contrôle de complétude reçoit à nouveau « $input », le POST brut : les champs dérivés (dates, TVA, financeur, commanditaire) y sont absents et la validation redeviendra impossible.'
);
$exiger(
    (bool) preg_match( '/ConventionCompleteness::verifier\(\s*\$controle\s*\)/', $corps ),
    'Le contrôle de complétude ne reçoit plus le tableau résolu « $controle ».'
);

/* 2. Le plan de financement doit être calculé AVANT le contrôle : c'est lui qui
      résout le financeur, et le contrôle bloque sur son absence. */
$pos_plan     = strpos( $corps, 'acdc_contract_funding_plan(' );
$pos_controle = strpos( $corps, 'ConventionCompleteness::verifier(' );
$exiger(
    false !== $pos_plan && false !== $pos_controle && $pos_plan < $pos_controle,
    'Le plan de financement est calculé après le contrôle de complétude : le financeur sera toujours vu comme absent, et toute convention à financement externe sera refusée.'
);

/* 3. LE CŒUR — chaque champ exigé doit avoir une source.
      Soit le handler le fournit explicitement dans « $controle », soit le
      formulaire le poste sous ce nom exact. */
$fournis = array();
if ( preg_match( '/\$controle\s*=\s*array_merge\((.*?)\n  \);/s', $corps, $m_ctrl ) ) {
    if ( preg_match_all( "/'([a-z_]+)'\s*=>/", $m_ctrl[1], $m_cles ) ) {
        $fournis = $m_cles[1];
    }
}
$exiger( ! empty( $fournis ), 'Le tableau « $controle » est introuvable ou vide dans le handler.' );

$exiges = array_keys( CC::BLOQUANTS );
$exiges[] = 'funder_id';       // exigé dès qu'un financement externe est annoncé
$exiges[] = 'public_funding';  // c'est lui qui déclenche cette exigence

/* On n'accepte PAS « le formulaire le poste quelque part » comme source. Le
   fichier de rendu contient plusieurs formulaires, et « company_id » y figure
   dans une section que l'écran de création n'utilise pas : cette souplesse
   laissait justement passer la faute d'origine. Le handler doit nommer chaque
   champ bloquant, sans exception. */
foreach ( $exiges as $cle ) {
    $exiger(
        in_array( $cle, $fournis, true ),
        sprintf(
            'Le champ « %s » est exigé par le contrôle mais le handler ne le fournit pas dans « $controle ». S’il n’est pas posté sous ce nom exact, la validation refusera un champ que personne ne peut remplir.',
            $cle
        )
    );
}

/* 4. Le commanditaire se lit sur les pistes de l'écran : la fiche
      entreprise/indépendant d'abord, le nom d'un particulier ensuite. */
$exiger(
    (bool) preg_match( '/\$commanditaire\s*=.*\$source_prospect_id/s', substr( $corps, 0, strpos( $corps, '$controle' ) ?: strlen( $corps ) ) )
        || false !== strpos( $corps, '(string) $source_prospect_id' ),
    'La résolution du commanditaire ne consulte plus la liste visible (« source_prospect_id ») : une convention créée depuis une proposition sera refusée.'
);
$exiger(
    (bool) preg_match( '/\$commanditaire\s*=\s*trim\(\s*\$commanditaire_first_name/', $corps ),
    'Le commanditaire particulier (nom saisi) n’est plus une piste de repli : une convention avec un particulier sera refusée faute de fiche entreprise.'
);

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Convention — l’appelant fournit ce que le contrôle exige : %d/%d vertes.\n", $verifs, $verifs );
exit( 0 );
