# RELEASE NOTES — 3.25.107 (facturation électronique : Factur-X XML)

## Facture électronique 2026
- Génération du **XML Factur-X (profil MINIMUM, CII UN/CEFACT)** d'une facture, reconnu
  par l'administration fiscale française. Classe pure `ACDC\Support\FacturX` (construction
  via DOMDocument, échappement correct) — 11 tests PHPUnit.
- Mapping facture → Factur-X via `get_invoice_facturx_xml()` (montants HT/TVA/TTC issus de
  `Money`, vendeur depuis le branding SIRET/TVA, acheteur depuis le client).
- Handler de téléchargement `acdc_download_invoice_facturx` (capacité + nonce) et bouton
  « Factur-X (XML) » dans le détail de facture.
- Suite PHPUnit totale : 47 tests / 98 assertions.

## Étape suivante (hors périmètre de cette version)
Le XML produit est prêt à être **embarqué dans un PDF/A-3** (fichier « factur-x.xml » +
métadonnées XMP) pour obtenir la facture Factur-X complète. Cette incorporation nécessite
une bibliothèque PDF/A-3 et une validation en environnement réel — à réaliser ensuite.
