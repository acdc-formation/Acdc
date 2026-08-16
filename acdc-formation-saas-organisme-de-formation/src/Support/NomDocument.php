<?php
/**
 * Le nom d'un fichier téléchargé, lisible par celui qui le classe.
 *
 * POURQUOI CE FICHIER EXISTE. Un dossier de formation produit une dizaine de
 * pièces téléchargeables. Elles s'appelaient :
 *
 *   certificat-12-1755374400.pdf
 *   certificat-13-1755374512.pdf
 *   certificat-14-1755374690.pdf
 *   contrat-formateur-4-5-a3f19c2b8e04d7615caa.pdf
 *   convocation-87-9d2e01ba4c73f8e5601a.pdf
 *   devis-12-1755374400.html
 *
 * Toutes exactes, toutes inexploitables. Dans le dossier « Téléchargements »
 * d'un exploitant qui prépare un contrôle Qualiopi, il faut les ouvrir une à
 * une pour savoir laquelle concerne quel client. Les longues suites sont soit
 * un horodatage Unix — une date qu'aucun humain ne lit — soit un jeton de
 * sécurité qui n'a rien à faire dans un nom destiné à être lu.
 *
 * LA RÈGLE, UNE SEULE, POUR TOUTES LES PIÈCES :
 *
 *   <nature>-<entité>-<jj-mm-aaaa>.<ext>
 *   convention-de-formation-acme-sarl-16-08-2026.pdf
 *
 * la nature de la pièce, POUR QUI elle est établie, et la date qui compte.
 *
 * L'ENTITÉ, PAS LE SIGNATAIRE. Une convention est signée par une personne au
 * nom d'une entreprise ; c'est l'entreprise qu'on cherche en classant. Quand il
 * n'y a pas d'entité — un émargement de stagiaire — on retombe sur la personne,
 * qui est alors bien la partie concernée. Jamais sur rien.
 *
 * LE JETON RESTE SUR LE DISQUE, PAS DANS LE NOM LU. Certains fichiers portent un
 * condensat qui les rend impossibles à deviner de l'extérieur : c'est une
 * protection, et elle ne se négocie pas. Elle vit dans le nom du fichier stocké ;
 * le nom proposé au téléchargement, lui, peut être lisible sans rien affaiblir —
 * ce sont deux noms différents pour le même octet.
 *
 * IL NE LIT NI LA BASE NI WORDPRESS : il reçoit trois textes, il rend un nom.
 * Tout est vérifiable sans site.
 *
 * @package ACDC
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
    exit;
}

final class NomDocument {

    /** Ce qu'on écrit quand on ne sait rien — plutôt qu'un nom vide. */
    public const SANS_NOM = 'document';

    /**
     * Le nom d'une pièce, extension comprise.
     *
     * @param string $nature    « Convention de formation », « Devis »…
     * @param string $entite    L'entreprise, ou à défaut la personne.
     * @param string $date      Déjà formatée, ex. « 16-08-2026 ». Facultative.
     * @param string $extension Sans le point.
     * @return string Ex. « convention-de-formation-acme-sarl-16-08-2026.pdf ».
     */
    public static function composer( $nature, $entite, $date = '', $extension = 'pdf' ) {
        $morceaux = array();
        foreach ( array( $nature, $entite, $date ) as $part ) {
            $slug = self::slug( $part );
            if ( '' !== $slug ) {
                $morceaux[] = $slug;
            }
        }
        $base = implode( '-', $morceaux );
        if ( '' === $base ) {
            $base = self::SANS_NOM;
        }
        /* Les systèmes de fichiers et les en-têtes HTTP n'aiment pas les noms
           interminables : on borne, sans jamais couper au milieu d'un mot si on
           peut l'éviter. */
        if ( strlen( $base ) > 120 ) {
            $base    = substr( $base, 0, 120 );
            $dernier = strrpos( $base, '-' );
            if ( false !== $dernier && $dernier > 40 ) {
                $base = substr( $base, 0, $dernier );
            }
            $base = rtrim( $base, '-' );
        }
        $ext = self::slug( $extension );
        return '' !== $ext ? $base . '.' . $ext : $base;
    }

    /**
     * Un texte quelconque en morceau de nom de fichier.
     *
     * Les accents sont repliés plutôt que supprimés : « Société » devient
     * « societe » et non « socit ». Un nom d'entreprise amputé de ses voyelles
     * ne se reconnaît plus, ce qui ruine l'objet même de l'exercice.
     */
    public static function slug( $texte ) {
        $texte = (string) $texte;
        if ( '' === trim( $texte ) ) {
            return '';
        }
        $texte = strtr(
            $texte,
            array(
                'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
                'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
                'Ç' => 'C', 'ç' => 'c',
                'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
                'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
                'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
                'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
                'Ñ' => 'N', 'ñ' => 'n',
                'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
                'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
                'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
                'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
                'Ý' => 'Y', 'ý' => 'y', 'ÿ' => 'y',
                'Æ' => 'AE', 'æ' => 'ae', 'Œ' => 'OE', 'œ' => 'oe',
                'ß' => 'ss', '°' => '', '’' => '-', "'" => '-',
            )
        );
        $texte = strtolower( $texte );
        $texte = preg_replace( '/[^a-z0-9]+/', '-', $texte );
        return trim( (string) $texte, '-' );
    }
}
