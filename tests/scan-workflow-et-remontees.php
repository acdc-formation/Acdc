<?php
/**
 * ACDC 3.25.323 — Quatre silences, une même forme.
 *
 * Chacun de ces défauts a la même signature : une information EXISTE dans la
 * base, et aucun écran ne va la chercher.
 *
 * 1. LE MOTEUR DE WORKFLOW. Sept étapes restaient « en attente d'un préalable »
 *    pendant que l'archive des e-mails montrait les sept envois aboutis.
 *    L'histoire est documentée dans le code : la 3.25.219 marquait ces étapes
 *    « Faite » — faux positif — et la 3.25.221 est revenue à « en attente » sur
 *    cette prémisse : « le moteur n'a pas les moyens de constater l'envoi d'un
 *    module tiers ». C'était vrai. Ça ne l'est plus : chaque e-mail laisse dans
 *    l'archive son module, son destinataire et son état.
 *    Le moteur CONSTATE donc, au lieu de deviner — et il ne confirme que sur
 *    preuve. Sans preuve, l'attente demeure : on n'a pas remplacé un
 *    aveuglement par une confiance.
 *
 * 2. L'ANALYSE DU BESOIN. « document_url_apprenant » n'était renseignée que par
 *    le TÉLÉCHARGEMENT du PDF depuis l'administration — un effet de bord d'une
 *    consultation. L'extranet de l'apprenant ne lit que cette colonne : il ne
 *    voyait donc rien tant que personne n'avait ouvert le PDF, c'est-à-dire
 *    jamais.
 *
 * 3. LE BILAN DU FORMATEUR. Les colonnes existaient sur la séance, remplies
 *    depuis l'extranet, et n'étaient affichées NULLE PART côté organisme. C'est
 *    l'indicateur 21 de Qualiopi : le recueil avait lieu, la preuve était
 *    invisible.
 *
 * 4. LA DURÉE. 09:00 → 17:00 donnait 8 h : la pause déjeuner comptée comme du
 *    temps de formation. La convention annonce 14 h pour deux jours, les
 *    statistiques affichaient 16 h. Deux chiffres pour la même réalité, et
 *    c'est le plus flatteur qui sortait — sur une base facturable.
 *
 * LA RÈGLE LA PLUS IMPORTANTE DE CE FICHIER est la reprise des étapes en
 * attente : sans elle, la correction du moteur ne vaudrait que pour l'avenir et
 * les sept étapes bloquées le resteraient à jamais. C'est la troisième fois
 * cette semaine qu'un état terminal est posé sur un travail inachevé.
 *
 * Éprouvé en sabotant : chaque règle échoue quand on défait la correction.
 */

$racine = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation';

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) {
        $echecs[] = $message;
    }
};

$code = function ( $relatif ) use ( $racine ) {
    $chemin = $racine . '/' . $relatif;
    if ( ! is_readable( $chemin ) ) {
        return '';
    }
    $sans = '';
    foreach ( token_get_all( (string) file_get_contents( $chemin ) ) as $jeton ) {
        if ( is_array( $jeton ) ) {
            if ( T_COMMENT === $jeton[0] || T_DOC_COMMENT === $jeton[0] ) {
                $sans .= str_repeat( "\n", substr_count( $jeton[1], "\n" ) );
                continue;
            }
            $sans .= $jeton[1];
            continue;
        }
        $sans .= $jeton;
    }
    return $sans;
};

/* ── 1. LE MOTEUR CONSTATE ─────────────────────────────────────────────── */
$wf = $code( 'includes/workflow/class-acdc-workflow-engine-trait.php' );
$exiger( '' !== $wf, 'Le moteur de workflow est introuvable.' );

$exiger(
    false !== strpos( $wf, 'acdc_wf_constater_envoi_delegue' ),
    'Le moteur ne consulte plus l’archive : les étapes déléguées resteront « en attente » alors que les envois ont eu lieu.'
);
/* On exige l'APPEL, pas la définition : une fonction orpheline ne reprend rien.
   C'est précisément ce que le premier sabotage a montré. */
$exiger(
    (bool) preg_match( '/\$this->acdc_wf_reprendre_etapes_en_attente\(/', $wf ),
    'LA RÈGLE CENTRALE — la reprise des étapes déjà « en attente » a disparu. La boucle d’exécution ne prend que les étapes « pending » : sans reprise, les étapes bloquées le restent à jamais, et la correction ne vaut que pour l’avenir.'
);
$exiger(
    (bool) preg_match( "/status = 'waiting'/", $wf ),
    'La reprise ne cible plus les étapes en attente.'
);
/* On ne confirme QUE sur preuve : le retour anticipé sans preuve est ce qui
   distingue un constat d'une confiance. */
$exiger(
    (bool) preg_match( '/if \( ! \$preuve \) \{\s*continue;/', $wf ),
    'La reprise clôt des étapes sans preuve d’envoi : c’est le faux positif de la 3.25.219 qui revient.'
);
$exiger(
    (bool) preg_match( "/'sent' !== \(string\) \( \\\$entree\['status'\] \?\? '' \)/", $wf ),
    'Un envoi seulement TENTÉ vaut désormais preuve d’envoi abouti.'
);
$exiger(
    (bool) preg_match( '/\$quand < \$depuis/', $wf ),
    'La fenêtre de temps a disparu : un envoi antérieur à la planification confirmerait une étape jamais exécutée.'
);
$exiger(
    false !== strpos( $wf, "'settled_by'  => 'archive'" ),
    'L’origine du constat n’est plus tracée : un auditeur ne peut plus distinguer une étape close par preuve d’une étape close à la main.'
);
$exiger(
    (bool) preg_match( "/status = 'waiting'.{0,200}s\.settled_by <> 'human'/s", $wf ),
    'La reprise écrase des étapes tranchées par un humain : un arbitrage manuel serait effacé par un constat automatique.'
);

/* ── 2. L'ANALYSE DU BESOIN ────────────────────────────────────────────── */
$nad = $code( 'includes/kernel/class-acdc-kernel-actions-trait.php' );
$exiger(
    false !== strpos( $nad, 'acdc_nad_apprenant_pdf_stocke' ),
    'Le PDF de l’apprenant n’est plus fabriqué hors téléchargement : son extranet restera vide.'
);
$exiger(
    (bool) preg_match( "/'statut'     => 'traite',.{0,900}acdc_nad_apprenant_pdf_stocke/s", $nad ),
    'Le PDF n’est plus fabriqué à la complétion : il redevient un effet de bord d’une consultation qui n’a peut-être jamais lieu.'
);
$exiger(
    (bool) preg_match( '/if \( ! empty\( \$nad->document_url_apprenant \) \) \{\s*return \(string\) \$nad->document_url_apprenant;/', $nad ),
    'La fabrication n’est plus idempotente : une pièce déjà consultée changerait sous les pieds de qui l’a téléchargée.'
);

/* ── 3. LE BILAN DU FORMATEUR ──────────────────────────────────────────── */
$ses = $code( 'includes/sessions/class-acdc-sessions-render-trait.php' );
$exiger(
    (bool) preg_match( '/\$this->acdc_rendre_bilan_post_formation\(/', $ses ),
    'Le bilan du formateur ne remonte plus à l’organisme : indicateur 21 de Qualiopi sans preuve visible.'
);
$exiger(
    false !== strpos( $ses, 'report_recommendations' ),
    'Les recommandations du formateur ne sont plus affichées.'
);

/* ── 4. LA DURÉE ───────────────────────────────────────────────────────── */
$dur = $code( 'includes/sessions/class-acdc-sessions-core-trait.php' );
$exiger(
    false !== strpos( $dur, 'acdc_session_minutes_demi_journees' ),
    'La durée redevient l’amplitude début → fin : la pause déjeuner est comptée comme du temps de formation, et 14 h deviennent 16 h.'
);
$exiger(
    (bool) preg_match( '/\$minutes_demi > 0/', $dur ),
    'Les demi-journées ne priment plus sur l’amplitude.'
);
$exiger(
    (bool) preg_match( '/seance_start_at IS NOT NULL AND seance_end_at IS NOT NULL/', $dur ),
    'Une demi-journée sans horaire entre dans le calcul et fausse le total.'
);

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Moteur, analyse, bilan et durée : l’information existante est enfin lue — %d/%d vertes.\n", $verifs, $verifs );
exit( 0 );
