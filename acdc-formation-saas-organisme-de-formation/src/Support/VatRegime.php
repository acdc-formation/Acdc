<?php
/**
 * Le régime de TVA de l'organisme, et la mention qu'il impose.
 *
 * POURQUOI CE FICHIER EXISTE. Le profil d'entreprise contenait DEUX réglages de
 * TVA — « vat_rate » à 20.00% et « vat_rate_default » à 0 — dont AUCUN n'était
 * lu nulle part. Le taux affiché sur les devis et les factures venait de « 20 »
 * écrit en dur, à huit endroits. Deux interrupteurs qui ne commandaient rien, et
 * une valeur codée qui donnait l'illusion qu'ils fonctionnaient.
 *
 * Tant qu'on facture à 20 %, cela ne se voit pas. Le jour où l'exonération est
 * accordée, l'exploitant change le réglage, les documents continuent d'afficher
 * 20 %, et rien ne le prévient. C'est précisément la situation de David : il
 * facturera d'abord avec TVA, puis demandera l'exonération au titre de la
 * formation professionnelle continue.
 *
 * DEUX EXONÉRATIONS QU'ON NE CONFOND PAS. Elles n'ont ni la même cause ni la
 * même mention, et écrire l'une pour l'autre est une erreur sur un document
 * comptable :
 *   — l'EXONÉRATION de l'article 261-4-4°a du CGI vise l'activité de formation
 *     professionnelle continue et suppose une attestation de l'administration ;
 *   — la FRANCHISE EN BASE de l'article 293 B dépend du chiffre d'affaires et
 *     n'a rien à voir avec l'activité.
 * Un simple « 0 % » ne dirait ni l'une ni l'autre, et laisserait une facture
 * sans la mention que la loi exige.
 *
 * CE FICHIER NE DÉCIDE RIEN DE FISCAL. Il énumère des régimes et rend leur
 * mention telle qu'elle s'écrit. La qualification du régime revient à
 * l'exploitant et à son comptable.
 *
 * IL NE LIT NI LA BASE NI WORDPRESS : il reçoit une clé, il rend un taux et une
 * phrase. Tout est vérifiable sans site.
 *
 * @package ACDC
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
    exit;
}

final class VatRegime {

    /** Le régime retenu quand rien n'a été choisi — celui d'aujourd'hui. */
    public const DEFAUT = 'tva_20';

    /**
     * Les régimes, dans l'ordre où ils s'affichent.
     *
     * « taux » est le pourcentage appliqué. « mention » est la phrase que la loi
     * impose sur le document ; vide quand le taux se suffit à lui-même.
     */
    public static function regimes() {
        return array(
            'tva_20' => array(
                'libelle' => 'TVA 20 % (taux normal)',
                'taux'    => 20.0,
                'mention' => '',
            ),
            'tva_10' => array(
                'libelle' => 'TVA 10 % (taux intermédiaire)',
                'taux'    => 10.0,
                'mention' => '',
            ),
            'tva_5_5' => array(
                'libelle' => 'TVA 5,5 % (taux réduit)',
                'taux'    => 5.5,
                'mention' => '',
            ),
            'tva_2_1' => array(
                'libelle' => 'TVA 2,1 % (taux particulier)',
                'taux'    => 2.1,
                'mention' => '',
            ),
            /* Prestation à un preneur assujetti d'un autre État membre : la TVA
               est due par le client, et la mention est obligatoire. */
            'autoliquidation' => array(
                'libelle' => 'Autoliquidation (preneur assujetti hors France)',
                'taux'    => 0.0,
                'mention' => 'Autoliquidation — article 283-2 du CGI',
            ),
            'exoneration_261' => array(
                'libelle' => 'Exonération — organisme de formation',
                'taux'    => 0.0,
                'mention' => 'Exonération de TVA — article 261-4-4°a du CGI',
            ),
            'franchise_293b' => array(
                'libelle' => 'Franchise en base',
                'taux'    => 0.0,
                'mention' => 'TVA non applicable, article 293 B du CGI',
            ),
        );
    }

    /** La liste pour un menu déroulant : clé => libellé. */
    public static function options() {
        $out = array();
        foreach ( self::regimes() as $cle => $r ) {
            $out[ $cle ] = $r['libelle'];
        }
        return $out;
    }

    /** Un régime existe-t-il ? */
    public static function existe( $cle ) {
        return array_key_exists( (string) $cle, self::regimes() );
    }

    /**
     * Le régime demandé, ou celui par défaut.
     *
     * On ne rend JAMAIS un tableau vide : un document qui ne saurait pas quel
     * taux appliquer afficherait un total faux sans le dire.
     */
    public static function get( $cle ) {
        $regimes = self::regimes();
        $cle     = (string) $cle;
        if ( ! isset( $regimes[ $cle ] ) ) {
            $cle = self::DEFAUT;
        }
        return array( 'cle' => $cle ) + $regimes[ $cle ];
    }

    /** Le taux, en pourcentage. */
    public static function taux( $cle ) {
        $r = self::get( $cle );
        return (float) $r['taux'];
    }

    /** La mention obligatoire, ou une chaîne vide. */
    public static function mention( $cle ) {
        $r = self::get( $cle );
        return (string) $r['mention'];
    }

    /** Ce régime fait-il apparaître une ligne de TVA sur le document ? */
    public static function avecTva( $cle ) {
        return self::taux( $cle ) > 0.0;
    }

    /**
     * Le régime qu'un taux seul désigne SANS AMBIGUÏTÉ, ou une chaîne vide.
     *
     * Sert à reprendre les documents antérieurs au régime : ils ne portent qu'un
     * nombre. 20, 10, 5,5 et 2,1 ne désignent qu'un régime chacun — la reprise
     * est certaine. ZÉRO N'EN DÉSIGNE AUCUN : autoliquidation, exonération de
     * l'article 261-4-4°a et franchise de l'article 293 B affichent toutes 0 %
     * et imposent trois mentions différentes. Deviner reviendrait à imprimer une
     * référence légale fausse sur une facture — on rend une chaîne vide, et le
     * document garde son absence de mention.
     */
    public static function parTaux( $taux ) {
        $taux = round( (float) str_replace( ',', '.', (string) $taux ), 2 );
        if ( $taux <= 0.0 ) {
            return '';
        }
        foreach ( self::regimes() as $cle => $r ) {
            if ( abs( (float) $r['taux'] - $taux ) < 0.001 && (float) $r['taux'] > 0.0 ) {
                return $cle;
            }
        }
        return '';
    }

    /**
     * Le régime figé sur un document déjà émis.
     *
     * LA RÈGLE QUI PROTÈGE LE PASSÉ. Le profil ne fournit le régime qu'aux
     * documents NOUVEAUX. Une facture émise à 20 % garde son taux et sa mention
     * le jour où l'organisme passe en exonération : sans quoi l'historique
     * comptable se réécrirait tout seul, et une facture réimprimée pour un
     * contrôle ne dirait plus ce qu'elle disait au client.
     *
     * @param string $fige   Le régime enregistré sur le document, s'il en a un.
     * @param string $profil Le régime courant de l'organisme.
     */
    public static function pourDocument( $fige, $profil ) {
        $fige = trim( (string) $fige );
        if ( '' !== $fige && self::existe( $fige ) ) {
            return self::get( $fige );
        }
        return self::get( $profil );
    }

    /**
     * Le montant de TVA et le total, à partir d'un HT.
     *
     * Arrondi au centime, une seule fois : arrondir deux fois — la TVA puis le
     * total — fait apparaître des écarts d'un centime sur les factures, que les
     * comptables retrouvent et qu'on ne sait plus expliquer.
     */
    public static function calculer( $ht, $cle ) {
        $ht    = (float) $ht;
        $taux  = self::taux( $cle );
        $tva   = round( $ht * $taux / 100, 2 );
        return array(
            'ht'      => round( $ht, 2 ),
            'taux'    => $taux,
            'tva'     => $tva,
            'ttc'     => round( $ht + $tva, 2 ),
            'mention' => self::mention( $cle ),
        );
    }
}
