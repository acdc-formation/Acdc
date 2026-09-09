<?php
/**
 * ACDC Quiz — Préparation automatique des quiz d'une action de formation.
 *
 * Demandé en recette, et la formulation de David dit exactement le besoin :
 * « Comment faire en sorte que systématiquement, dès qu'une séance est créée,
 * les quiz qui correspondent à la formation soient automatiquement reliés aux
 * différents apprenants ? »
 *
 * Jusqu'ici, rattacher un quiz à une séance était un geste manuel, à refaire
 * pour chaque groupe. Ce qui s'oublie une fois par action de formation finit
 * par ne plus se faire du tout — et un quiz non rattaché ne remonte dans aucun
 * dossier : il ne vaut rien comme preuve Qualiopi.
 *
 * TROIS QUIZ PAR ACTION, PAS PAR JOURNÉE. Une formation de trois, quatre ou
 * sept jours existe chez nous en autant de séances — une par journée — mais
 * elle n'a qu'UN test de positionnement, UNE évaluation diagnostique le premier
 * jour et UNE évaluation des acquis le dernier. Préparer les trois sur chaque
 * journée produirait vingt et un quiz pour une seule action, dont dix-huit
 * parasites. Ils sont donc accrochés à la PREMIÈRE séance de l'action, là où le
 * formateur les retrouve du premier au dernier jour.
 *
 * LE QUIZ LIVE EST À PART. Il peut y en avoir plusieurs — des brise-glace, un
 * par demi-journée si le formateur le veut — et c'est lui qui les crée depuis
 * son extranet, quand il en a besoin. On n'en prépare donc aucun d'office :
 * décider à sa place combien de brise-glace il lui faut serait absurde.
 *
 * RIEN N'EST ENVOYÉ ICI. Les envois naissent à l'état de brouillon, sans code
 * PIN, sans destinataire figé. C'est le formateur qui déclenche, et la liste
 * des apprenants se résout à ce moment-là — un apprenant inscrit après la
 * création de la séance doit être du quiz, et il le sera.
 *
 * @since 3.25.271
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Quizzes_Preparation_Trait {

    /**
     * Les trois usages préparés d'office pour une action de formation.
     *
     * @return string[]
     */
    private function acdc_qz_structural_purposes() {
        return array(
            self::ACDC_OF_QZ_PURPOSE_POSITIONING,
            self::ACDC_OF_QZ_PURPOSE_DIAGNOSTIC,
            self::ACDC_OF_QZ_PURPOSE_ASSESSMENT,
        );
    }

    /**
     * L'interrupteur de la fiche formation qui commande chaque quiz structurel.
     *
     * ACDC 3.25.278 — IL N'Y EN AVAIT AUCUN. La fiche formation offre dix
     * interrupteurs « Automatismes Qualiopi » ; ils commandaient bien les
     * questionnaires et les documents, mais la préparation des quiz, écrite
     * plus tard, ne les consultait pas. Éteindre « Test de positionnement » ou
     * « Évaluation des acquis » ne changeait rien : le quiz était préparé et
     * rattaché aux apprenants quand même.
     *
     * Ce n'est pas seulement un réglage sans effet. L'écran AFFIRME que le
     * document sort du parcours ; on le croit, on ne vérifie plus, et le quiz
     * part malgré tout. Un écran qui affirme sans avoir lu est plus dangereux
     * qu'un écran vide.
     *
     * L'évaluation diagnostique, elle, n'avait même pas d'interrupteur : on
     * l'ajoute en même temps, sans quoi elle serait le seul quiz que rien ne
     * commande.
     *
     * @return array<string,string> usage du quiz => colonne de la formation
     */
    private function acdc_qz_purpose_toggles() {
        return array(
            self::ACDC_OF_QZ_PURPOSE_POSITIONING => 'positioning_test_enabled',
            self::ACDC_OF_QZ_PURPOSE_DIAGNOSTIC  => 'diagnostic_evaluation_enabled',
            self::ACDC_OF_QZ_PURPOSE_ASSESSMENT  => 'evaluation_enabled',
        );
    }

    /**
     * Cet usage est-il activé sur cette formation ?
     *
     * Une colonne absente — installation dont la mise à jour de schéma n'a pas
     * encore tourné — vaut l'état par défaut de la liste commune, jamais
     * « éteint ». Traiter l'inconnu comme un refus arrêterait des automatismes
     * que personne n'a demandé d'arrêter, et sans le dire.
     *
     * @param object|null $formation
     * @param string      $purpose
     * @return bool
     */
    private function acdc_qz_purpose_is_enabled( $formation, $purpose ) {
        $toggles = $this->acdc_qz_purpose_toggles();
        if ( ! isset( $toggles[ $purpose ] ) ) {
            return true;   // un usage sans interrupteur n'est pas commandé : il passe.
        }
        $colonne = $toggles[ $purpose ];
        if ( is_object( $formation ) && isset( $formation->$colonne ) ) {
            return 1 === (int) $formation->$colonne;
        }
        $defauts = $this->acdc_qualiopi_toggles();
        return isset( $defauts[ $colonne ] ) ? 1 === (int) $defauts[ $colonne ]['default'] : true;
    }

    /**
     * Prépare les quiz d'une action de formation.
     *
     * Idempotent : rejouable sans risque sur une action déjà préparée, ce dont
     * on a besoin — la convention rejoue sa création de séances à chaque passe
     * du moteur, et une séance modifiée repasse par ici.
     *
     * @param int[] $session_ids Les séances de l'action (toutes ses journées).
     * @return array{prepared:int,skipped:int,missing:string[]} Compte rendu.
     */
    private function acdc_prepare_action_quizzes( $session_ids ) {
        global $wpdb;

        $report = array( 'prepared' => 0, 'skipped' => 0, 'missing' => array() );

        $session_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $session_ids ) ) ) );
        if ( empty( $session_ids ) ) {
            return $report;
        }

        $ph       = implode( ',', array_fill( 0, count( $session_ids ), '%d' ) );
        $sessions = (array) $wpdb->get_results( $wpdb->prepare(
            "SELECT id, formation_id, start_date, start_at
               FROM {$this->session_table}
              WHERE id IN ({$ph})
                AND COALESCE(status,'') NOT IN ('Annulée','Annulee')
              ORDER BY COALESCE(start_date, DATE(start_at)) ASC, id ASC",
            $session_ids
        ) );
        if ( empty( $sessions ) ) {
            return $report;
        }

        /* La première journée porte les trois quiz de l'action. */
        $premiere     = $sessions[0];
        $formation_id = (int) $premiere->formation_id;
        if ( $formation_id <= 0 ) {
            return $report;
        }

        $tbl_quizzes  = $this->get_qz_table( 'quizzes' );
        $tbl_sessions = $this->get_qz_table( 'sessions' );
        if ( '' === $tbl_quizzes || '' === $tbl_sessions ) {
            return $report;
        }

        $toutes_les_journees = array();
        foreach ( $sessions as $s ) { $toutes_les_journees[] = (int) $s->id; }
        $ph_jours = implode( ',', array_fill( 0, count( $toutes_les_journees ), '%d' ) );
        $now      = current_time( 'mysql' );

        /* ACDC 3.25.278 — La fiche formation commande ses automatismes. On la
           lit une fois, avant la boucle : les trois usages s'y réfèrent. */
        $formation = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->formation_table} WHERE id = %d",
            $formation_id
        ) );

        foreach ( $this->acdc_qz_structural_purposes() as $purpose ) {
            /* Un usage désactivé sur la fiche n'est pas préparé. Il n'est pas
               non plus compté comme « manquant » : il ne manque pas, il a été
               décidé. Confondre les deux ferait remonter une alerte à chaque
               action pour un choix délibéré, et une alerte qu'on apprend à
               ignorer ne protège plus de rien. */
            if ( ! $this->acdc_qz_purpose_is_enabled( $formation, $purpose ) ) {
                continue;
            }
            /* Le quiz COURANT de la formation pour cet usage. La table porte
               déjà `is_current` : une version en vigueur, les précédentes
               archivées. On ne choisit pas, on lit ce qui a été décidé. */
            $quiz = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, title, delivery_mode
                   FROM {$tbl_quizzes}
                  WHERE formation_id = %d
                    AND quiz_purpose = %s
                    AND is_current = 1
                    AND status = %s
                  ORDER BY id DESC LIMIT 1",
                $formation_id,
                $purpose,
                self::ACDC_OF_QZ_STATUS_ACTIVE
            ) );

            if ( ! $quiz ) {
                /* Ce qui manque se dit par son nom, pas par un compteur : c'est
                   la seule forme d'alerte sur laquelle on peut agir. */
                $labels = $this->get_quiz_purpose_labels();
                $report['missing'][] = isset( $labels[ $purpose ] ) ? $labels[ $purpose ] : $purpose;
                continue;
            }

            /* Déjà préparé — ou déjà joué — sur l'une des journées de l'action :
               on ne double pas. Un quiz annulé ne compte pas : il doit pouvoir
               être repréparé. */
            $params   = array_merge( array( (int) $quiz->id ), $toutes_les_journees, array( self::ACDC_OF_QZ_DISPATCH_CANCELLED ) );
            $existant = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$tbl_sessions}
                  WHERE quiz_id = %d
                    AND formation_session_id IN ({$ph_jours})
                    AND status <> %s
                  LIMIT 1",
                $params
            ) );
            if ( $existant > 0 ) {
                $report['skipped']++;
                continue;
            }

            $inserted = $wpdb->insert( $tbl_sessions, array(
                'quiz_id'              => (int) $quiz->id,
                'formation_id'         => $formation_id,
                'formation_session_id' => (int) $premiere->id,
                'delivery_mode'        => (string) $quiz->delivery_mode,
                /* Brouillon : préparé, visible du formateur, envoyé par
                   personne. Le code PIN naîtra au déclenchement — deux groupes
                   de la même formation le même jour auront ainsi deux PIN
                   distincts, ce qui est la seule façon de ne pas les mélanger. */
                'status'               => self::ACDC_OF_QZ_DISPATCH_DRAFT,
                'created_at'           => $now,
                'updated_at'           => $now,
            ) );

            if ( $inserted ) {
                $report['prepared']++;
            }
        }

        return $report;
    }

    /**
     * Les usages qui manquent au catalogue d'une formation.
     *
     * Sert l'alerte : « on découvre le trou le matin même » est exactement ce
     * qu'il faut empêcher.
     *
     * @param int $formation_id Formation.
     * @return string[] Libellés des usages sans quiz courant.
     */
    private function acdc_qz_missing_purposes( $formation_id ) {
        global $wpdb;

        $formation_id = (int) $formation_id;
        $manquants    = array();
        if ( $formation_id <= 0 ) {
            return $manquants;
        }
        $tbl_quizzes = $this->get_qz_table( 'quizzes' );
        if ( '' === $tbl_quizzes ) {
            return $manquants;
        }

        /* ACDC 3.25.278 — On ne réclame pas un quiz que la fiche formation a
           désactivé : ce n'est pas un oubli, c'est une décision. Une alerte qui
           se déclenche sur un choix délibéré s'apprend à s'ignorer, et cesse
           alors de signaler les vrais oublis. */
        $formation = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->formation_table} WHERE id = %d",
            $formation_id
        ) );

        $labels = $this->get_quiz_purpose_labels();
        foreach ( $this->acdc_qz_structural_purposes() as $purpose ) {
            if ( ! $this->acdc_qz_purpose_is_enabled( $formation, $purpose ) ) {
                continue;
            }
            $existe = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$tbl_quizzes}
                  WHERE formation_id = %d AND quiz_purpose = %s AND is_current = 1 AND status = %s
                  LIMIT 1",
                $formation_id,
                $purpose,
                self::ACDC_OF_QZ_STATUS_ACTIVE
            ) );
            if ( ! $existe ) {
                $manquants[] = isset( $labels[ $purpose ] ) ? $labels[ $purpose ] : $purpose;
            }
        }

        return $manquants;
    }

    /**
     * La fenêtre pendant laquelle un quiz est visible chez le formateur.
     *
     * « Les quiz correspondant aux formations qu'il fait doivent être visibles
     * pendant la formation, mais disparaître quand la formation est terminée,
     * avec 1 ou 2 jours de battement. » Réglable, parce qu'un battement se
     * discute et qu'on ne rouvre pas le code pour changer un délai.
     *
     * @return array{avant:int,apres:int} Jours avant le début, jours après la fin.
     */
    private function acdc_qz_visibility_window() {
        /* Les deux valeurs vivent avec les autres délais du parcours, sur
           l'écran où l'on règle déjà la convocation et l'émargement : un réglage
           qui a son propre écran est un réglage qu'on ne retrouve pas. */
        $avant = method_exists( $this, 'acdc_wf_delay' )
            ? (int) $this->acdc_wf_delay( 'quiz_visible_days_before', 1 )
            : 1;
        $apres = method_exists( $this, 'acdc_wf_delay' )
            ? (int) $this->acdc_wf_delay( 'quiz_visible_days_after', 2 )
            : 2;

        return array(
            'avant' => max( 0, min( 60, $avant ) ),
            'apres' => max( 0, min( 60, $apres ) ),
        );
    }
}
