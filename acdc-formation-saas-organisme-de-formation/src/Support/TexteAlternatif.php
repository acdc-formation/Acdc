<?php
/**
 * La version texte d'un e-mail HTML.
 *
 * POURQUOI CE FICHIER EXISTE. Le plugin envoyait ses e-mails en HTML PUR : un
 * seul en-tête « Content-Type: text/html », aucune version texte. C'est l'un des
 * signaux qu'un filtre anti-spam regarde — un message légitime en propose
 * presque toujours deux, et un message qui n'en propose qu'une ressemble à un
 * envoi fabriqué à la chaîne. L'agent de recette l'a relevé de son côté ; le
 * code le confirme d'une ligne.
 *
 * CE QUE CETTE VERSION DOIT ABSOLUMENT CONSERVER : LES LIENS. Une enquête de
 * satisfaction dont la version texte aurait perdu son adresse ne serait pas une
 * version dégradée, ce serait un message vide de sens. On écrit donc « Répondre
 * à l'enquête (https://…) » plutôt que « Répondre à l'enquête ».
 *
 * CE QU'ELLE DOIT ABSOLUMENT PERDRE : le contenu des balises <style>. Sans quoi
 * la version texte commence par trois cents caractères de règles CSS — ce qui
 * est pire que pas de version texte du tout, et se voit sur les téléphones qui
 * affichent l'aperçu.
 *
 * IL NE LIT NI LA BASE NI WORDPRESS : il reçoit du HTML, il rend du texte.
 * Tout est vérifiable sans site.
 *
 * @package ACDC
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
    exit;
}

final class TexteAlternatif {

    /**
     * Le texte équivalent d'un corps HTML.
     *
     * @param string $html Le corps du message.
     * @return string Texte brut, lignes normalisées, liens conservés.
     */
    public static function depuisHtml( $html ) {
        $t = (string) $html;
        if ( '' === trim( $t ) ) {
            return '';
        }

        /* 1. Ce qui n'est pas du contenu disparaît AVANT tout le reste : sinon
              les règles CSS et le code JavaScript se retrouvent dans le texte. */
        $t = preg_replace( '#<(style|script|head|title)\b[^>]*>.*?</\1>#is', ' ', $t );

        /* 2. Les liens, avant que les balises ne tombent. On n'écrit l'adresse
              que si elle apporte quelque chose : un lien dont le texte EST déjà
              l'adresse n'a pas besoin d'être doublé, et une ancre interne ou un
              mailto se lisent très bien seuls. */
        $t = preg_replace_callback(
            '#<a\b[^>]*href\s*=\s*["\']([^"\']*)["\'][^>]*>(.*?)</a>#is',
            static function ( $m ) {
                $url   = trim( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
                $texte = trim( preg_replace( '/\s+/', ' ', strip_tags( $m[2] ) ) );
                if ( '' === $url || '#' === $url[0] || 0 === stripos( $url, 'mailto:' ) ) {
                    return '' !== $texte ? $texte : $url;
                }
                if ( '' === $texte ) {
                    return $url;
                }
                if ( 0 === strcasecmp( $texte, $url ) ) {
                    return $url;
                }
                return $texte . ' (' . $url . ')';
            },
            $t
        );

        /* 3. Ce qui faisait un saut de ligne à l'écran en fait un dans le texte. */
        $t = preg_replace( '#<br\s*/?>#i', "\n", $t );
        /* « li » n'est PAS dans cette liste : sa fermeture ajouterait un saut de
           ligne en plus de celui que son ouverture pose déjà, et chaque puce
           d'une liste se retrouverait séparée par une ligne vide. */
        $t = preg_replace( '#</(p|div|tr|h1|h2|h3|h4|h5|h6|table|blockquote)\s*>#i', "\n", $t );
        $t = preg_replace( '#<li\b[^>]*>#i', "\n- ", $t );
        $t = preg_replace( '#<(td|th)\b[^>]*>#i', ' ', $t );
        $t = preg_replace( '#<hr\s*/?>#i', "\n----\n", $t );

        /* 4. Le reste des balises n'a plus rien à dire. */
        $t = strip_tags( $t );
        $t = html_entity_decode( $t, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        /* L'espace insécable est invisible mais n'est pas un espace : laissé
           tel quel, il colle les mots dans certains lecteurs texte. */
        $t = str_replace( array( "\xC2\xA0", "\xE2\x80\xAF" ), ' ', $t );

        /* 5. La mise au propre. On travaille ligne à ligne pour ne pas écraser
              la structure du message avec un unique preg_replace global. */
        $t = str_replace( array( "\r\n", "\r" ), "\n", $t );
        $lignes = array();
        foreach ( explode( "\n", $t ) as $ligne ) {
            $lignes[] = trim( preg_replace( '/[ \t]+/', ' ', $ligne ) );
        }
        $t = implode( "\n", $lignes );
        /* Trois lignes vides ou plus ne veulent pas dire trois fois plus de
           séparation : une ligne vide suffit à séparer deux paragraphes. */
        $t = preg_replace( "/\n{3,}/", "\n\n", $t );

        return trim( $t );
    }
}
