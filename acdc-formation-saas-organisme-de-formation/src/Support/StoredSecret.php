<?php
/**
 * Un secret qu'on doit pouvoir relire : que faire d'un champ laissé vide ?
 *
 * Un mot de passe d'espace financeur n'est pas un mot de passe de connexion :
 * il faut pouvoir le RELIRE pour aller se connecter chez l'OPCO. Il est donc
 * chiffré, pas haché. Et comme le formulaire ne le réaffiche jamais — l'écrire
 * dans la page reviendrait à le publier dans le code source, le masque ne
 * serait qu'un décor —, le champ arrive vide à chaque enregistrement.
 *
 * D'où la règle, et la raison d'être de cette classe : un champ vide ne
 * signifie JAMAIS « efface ». Sinon la première modification d'une autre
 * donnée de la fiche (le téléphone de l'interlocuteur, par exemple) effacerait
 * silencieusement le mot de passe. Seule une case cochée efface.
 *
 * @package ACDC
 */

namespace ACDC\Support;

final class StoredSecret {

	/** Rien à faire : ce qui est enregistré reste enregistré. */
	public const KEEP = 'keep';

	/** Effacement demandé explicitement. */
	public const CLEAR = 'clear';

	/** Nouvelle valeur soumise : à chiffrer puis enregistrer. */
	public const SET = 'set';

	/**
	 * @param string $submitted       Ce que le formulaire a envoyé (souvent '').
	 * @param bool   $clear_requested La case « effacer » est-elle cochée ?
	 * @return string KEEP, CLEAR ou SET.
	 */
	public static function decide( $submitted, $clear_requested = false ) {
		/* La case cochée l'emporte : l'utilisateur demande un état final sans
		   secret, quoi qu'il ait pu taper au-dessus. */
		if ( $clear_requested ) {
			return self::CLEAR;
		}
		if ( '' === trim( (string) $submitted ) ) {
			return self::KEEP;
		}
		return self::SET;
	}
}
