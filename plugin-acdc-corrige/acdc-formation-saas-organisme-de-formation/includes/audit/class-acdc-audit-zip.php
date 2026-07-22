<?php
/**
 * ACDC Audit Qualiopi — Export ZIP
 *
 * Génère une archive ZIP organisée Formation → Séance/Apprenant → Documents.
 * Chaque document est téléchargé (URL statique) ou généré à la volée (admin-post).
 *
 * @package ACDC_Formation_SAAS
 * @since   3.21.09-hotfix27
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ACDC_Audit_Zip {

    /** @var ACDC_Audit_Core */
    private $core;

    /** Fichiers manquants ou en erreur */
    private $errors = array();

    public function __construct( ACDC_Audit_Core $core ) {
        $this->core = $core;
    }

    /* -----------------------------------------------------------------------
     * Point d'entrée — génère et sert le ZIP
     * -------------------------------------------------------------------- */
    public function export( $filters = array() ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            wp_die( 'L\'extension PHP ZipArchive est requise pour générer l\'export ZIP. Contactez votre hébergeur.' );
        }

        @set_time_limit( 300 );

        $docs     = $this->core->get_documents( $filters );
        $zip_path = $this->build_zip( $docs );

        if ( ! $zip_path || ! file_exists( $zip_path ) ) {
            wp_die( 'Impossible de générer l\'archive ZIP. Vérifiez les permissions du dossier temporaire.' );
        }

        $filename = 'Qualiopi-Export-' . gmdate( 'Y-m-d' ) . '.zip';
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $zip_path ) );
        header( 'Cache-Control: no-cache, no-store' );
        readfile( $zip_path );
        @unlink( $zip_path );
        exit;
    }

    /* -----------------------------------------------------------------------
     * Construction de l'archive
     * -------------------------------------------------------------------- */
    private function build_zip( $docs ) {
        $tmp  = wp_tempnam( 'acdc-audit-zip-' ) . '.zip';
        $zip  = new ZipArchive();
        $res  = $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE );
        if ( true !== $res ) { return false; }

        $root = 'Export-Qualiopi-' . gmdate( 'Y-m-d' ) . '/';
        $seq  = 1;

        foreach ( $docs as $fid => $formation ) {
            $f_slug  = $this->zname( sprintf( '%02d - %s', $seq++, $formation['title'] ) );
            $f_dir   = $root . $f_slug . '/';

            /* Documents de formation (conventions) */
            if ( ! empty( $formation['docs_formation'] ) ) {
                $sub = $f_dir . '00 - Documents de formation/';
                foreach ( $formation['docs_formation'] as $doc ) {
                    $this->add_doc_to_zip( $zip, $doc, $sub );
                }
            }

            /* Documents par séance */
            $sess_seq = 1;
            foreach ( $formation['sessions'] as $sid => $session ) {
                $s_label = sprintf( '%02d - Séance %s%s',
                    $sess_seq++,
                    $session['date_label'],
                    $session['title'] ? ' - ' . $session['title'] : ''
                );
                $s_dir = $f_dir . $this->zname( $s_label ) . '/';
                foreach ( $session['docs'] as $doc ) {
                    $this->add_doc_to_zip( $zip, $doc, $s_dir );
                }
            }

            /* Documents par apprenant */
            if ( ! empty( $formation['docs_learners'] ) ) {
                // Grouper par apprenant
                $by_learner = array();
                foreach ( $formation['docs_learners'] as $doc ) {
                    $key = $doc['learner_name'] ?: 'Inconnu';
                    $by_learner[ $key ][] = $doc;
                }
                foreach ( $by_learner as $learner_name => $ldocs ) {
                    $l_dir = $f_dir . $this->zname( 'Apprenant - ' . $learner_name ) . '/';
                    foreach ( $ldocs as $doc ) {
                        $this->add_doc_to_zip( $zip, $doc, $l_dir );
                    }
                }
            }
        }

        /* Rapport des erreurs si besoin */
        if ( ! empty( $this->errors ) ) {
            $report = "Documents non inclus dans l'export (fichiers manquants ou inaccessibles) :\n\n";
            foreach ( $this->errors as $e ) {
                $report .= '- ' . $e . "\n";
            }
            $zip->addFromString( $root . '_RAPPORT_ERREURS.txt', $report );
        }

        $zip->close();
        return $tmp;
    }

    /* -----------------------------------------------------------------------
     * Ajouter un document dans le ZIP
     * -------------------------------------------------------------------- */
    private function add_doc_to_zip( ZipArchive $zip, array $doc, $dir ) {
        $content = $this->fetch_doc_content( $doc );
        if ( ! $content ) {
            $this->errors[] = ( $doc['label'] ?? 'Document inconnu' ) . ' [URL: ' . ( $doc['url'] ?? '—' ) . ']';
            return;
        }

        $ext      = $this->guess_extension( $doc );
        $basename = $this->zname( $doc['label'] ?? 'document' ) . '.' . $ext;
        // Déduplication si même nom dans le même dossier
        $path = $dir . $basename;
        $i    = 1;
        while ( $zip->locateName( $path ) !== false ) {
            $path = $dir . $this->zname( $doc['label'] ?? 'document' ) . '-' . ( ++$i ) . '.' . $ext;
        }

        $zip->addFromString( $path, $content );
    }

    /* -----------------------------------------------------------------------
     * Récupération du contenu d'un document
     * -------------------------------------------------------------------- */
    private function fetch_doc_content( array $doc ) {
        /* Émargement PDF généré à la volée — NE PAS passer par HTTP */
        if ( ! empty( $doc['is_admin_url'] ) ) {
            return $this->fetch_admin_url_content( $doc );
        }

        $url = $doc['url_download'] ?? $doc['url'] ?? '';
        if ( ! $url ) { return false; }

        /* Essai 1 — chemin local (plus rapide, sans HTTP) */
        $local = $this->url_to_local_path( $url );
        if ( $local && file_exists( $local ) ) {
            $content = @file_get_contents( $local );
            if ( $content !== false && strlen( $content ) > 0 ) { return $content; }
        }

        /* Essai 2 — HTTP via wp_remote_get */
        $resp = wp_remote_get( $url, array( 'timeout' => 30, 'sslverify' => true ) );
        if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
            return false;
        }
        $content = wp_remote_retrieve_body( $resp );
        return ( strlen( $content ) > 0 ) ? $content : false;
    }

    private function fetch_admin_url_content( array $doc ) {
        $url = $doc['url_download'] ?? $doc['url'] ?? '';
        if ( ! $url ) { return false; }

        // Extraire les paramètres de l'URL admin-post
        $parsed = parse_url( $url );
        parse_str( $parsed['query'] ?? '', $params );
        $action = $params['action'] ?? '';

        /* Feuille d'émargement */
        if ( 'acdc_emarg_download_pdf' === $action && ! empty( $params['session_id'] ) ) {
            return $this->get_emarg_pdf_content( absint( $params['session_id'] ) );
        }

        /* Certificat de signature émargement formateur */
        if ( 'acdc_emarg_download_cert_trainer' === $action && ! empty( $params['emarg_id'] ) ) {
            return $this->get_emarg_cert_content( 'trainer', absint( $params['emarg_id'] ) );
        }

        /* Certificat de signature émargement apprenant */
        if ( 'acdc_emarg_download_cert_learner' === $action && ! empty( $params['emarg_id'] ) ) {
            return $this->get_emarg_cert_content( 'learner', absint( $params['emarg_id'] ) );
        }

        return false;
    }

    private function get_emarg_pdf_content( $session_id ) {
        if ( ! class_exists( 'ACDC_Emargement' ) ) { return false; }
        $emarg = ACDC_Emargement::get_instance();
        if ( ! $emarg || ! isset( $emarg->pdf ) ) { return false; }
        // Accès « toutes séances » par session : get_pdf_content() agrège désormais
        // toutes les feuilles signées de la session en un PDF multi-pages (une page-set
        // par séance). Pour une session mono-séance, la sortie reste identique.
        return $emarg->pdf->get_pdf_content( $session_id );
    }

    private function get_emarg_cert_content( $type, $id ) {
        if ( ! class_exists( 'ACDC_Emargement' ) ) { return false; }
        $emarg = ACDC_Emargement::get_instance();
        if ( ! $emarg || ! isset( $emarg->pdf ) ) { return false; }
        return $emarg->pdf->get_cert_content( $type, $id );
    }

    /* -----------------------------------------------------------------------
     * Helpers
     * -------------------------------------------------------------------- */
    private function url_to_local_path( $url ) {
        $upload = wp_upload_dir();
        $base_url = rtrim( $upload['baseurl'], '/' );
        $base_dir = rtrim( $upload['basedir'], '/' );
        $url      = strtok( $url, '?' ); // Supprimer les query strings
        if ( strpos( $url, $base_url ) === 0 ) {
            return $base_dir . substr( $url, strlen( $base_url ) );
        }
        // Fallback : remplacer site_url par ABSPATH
        $site_url = rtrim( site_url(), '/' );
        if ( strpos( $url, $site_url ) === 0 ) {
            return rtrim( ABSPATH, '/' ) . substr( $url, strlen( $site_url ) );
        }
        return false;
    }

    private function guess_extension( array $doc ) {
        $url = $doc['url_download'] ?? $doc['url'] ?? '';
        $ext = strtolower( pathinfo( strtok( $url, '?' ), PATHINFO_EXTENSION ) );
        if ( in_array( $ext, array( 'pdf', 'png', 'jpg', 'jpeg', 'docx', 'xlsx' ), true ) ) {
            return $ext;
        }
        // Pour les admin-post (PDFs générés), on sait que c'est du PDF
        if ( ! empty( $doc['is_admin_url'] ) ) { return 'pdf'; }
        return 'pdf';
    }

    /**
     * Nettoie un nom pour le système de fichiers ZIP.
     * Supprime les caractères interdits, limite à 80 chars.
     */
    private function zname( $name ) {
        $name = (string) $name;
        // Translittération des accents
        $map = array( 'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','à'=>'a','â'=>'a','ù'=>'u','û'=>'u',
                      'ô'=>'o','î'=>'i','ï'=>'i','ç'=>'c','É'=>'E','È'=>'E','Ê'=>'E','À'=>'A',
                      'Â'=>'A','Ô'=>'O','Î'=>'I','Ç'=>'C','Ù'=>'U','Û'=>'U' );
        $name = strtr( $name, $map );
        // Supprimer caractères interdits dans les noms de fichiers
        $name = preg_replace( '/[\\\\\/\:\*\?\"\<\>\|\x00-\x1f]/', '-', $name );
        $name = preg_replace( '/\s+/', ' ', $name );
        $name = trim( $name, ' -.' );
        return mb_substr( $name, 0, 80 );
    }
}
