<?php
/**
 * Les sauvegardes partent chez Google Drive.
 *
 * POURQUOI. Une sauvegarde qui vit sur le serveur qu'elle protège ne protège
 * pas grand-chose : le disque qui la contient est celui qui peut tomber. Elle
 * doit sortir de la machine.
 *
 * POURQUOI PAS UN COMPTE DE SERVICE. C'est la voie qu'on choisit d'instinct, et
 * elle échoue ici : un compte de service n'a pas de quota de stockage dans un
 * Drive PERSONNEL, et le dossier visé en est un. L'envoi retournerait
 * « Service Accounts do not have storage quota ». On passe donc par une
 * autorisation OAuth accordée une fois par le propriétaire du Drive : les
 * archives lui appartiennent et comptent sur son espace.
 *
 * CE QUI EST CONSERVÉ, ET COMMENT. L'identifiant client, le secret client et le
 * jeton de rafraîchissement sont chiffrés au repos avec la même mécanique que
 * les clés d'IA (acdc_secret_encrypt) : la clé de chiffrement dérive d'un secret
 * de wp-config.php, donc absent d'un export de la base. Conséquence à connaître :
 * une base restaurée sur un AUTRE serveur ne saura pas les déchiffrer, et il
 * faudra reconnecter le Drive. C'est le prix d'un secret qui ne voyage pas avec
 * la sauvegarde.
 *
 * @package ACDC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ACDC_Backup_Drive_Trait {

	/** Le point de retour de Google, à déclarer à l'identique dans la console. */
	public function acdc_gdrive_redirect_uri() {
		return admin_url( 'admin.php?page=acdc-of-maintenance&acdc_gdrive=callback' );
	}

	/** Les réglages Drive, secrets déchiffrés. */
	private function acdc_gdrive_settings() {
		$o = get_option( 'acdc_of_gdrive', array() );
		if ( ! is_array( $o ) ) {
			$o = array();
		}
		$defauts = array(
			'client_id'     => '',
			'client_secret' => '',
			'refresh_token' => '',
			'folder_id'     => '',
			'conserver'     => 60,
			'actif'         => 0,
			'dernier_envoi' => '',
			'derniere_erreur' => '',
		);
		$o = array_merge( $defauts, $o );
		foreach ( array( 'client_id', 'client_secret', 'refresh_token' ) as $secret ) {
			$o[ $secret ] = $this->acdc_secret_decrypt( (string) $o[ $secret ] );
		}
		return $o;
	}

	/** Écrit les réglages, secrets chiffrés. */
	private function acdc_gdrive_save_settings( $valeurs ) {
		$actuel = $this->acdc_gdrive_settings();
		$fusion = array_merge( $actuel, is_array( $valeurs ) ? $valeurs : array() );
		foreach ( array( 'client_id', 'client_secret', 'refresh_token' ) as $secret ) {
			$fusion[ $secret ] = $this->acdc_secret_encrypt( (string) $fusion[ $secret ] );
		}
		update_option( 'acdc_of_gdrive', $fusion, false );
	}

	/** Le Drive est-il utilisable ? */
	private function acdc_gdrive_pret() {
		$o = $this->acdc_gdrive_settings();
		return ( '' !== $o['client_id'] && '' !== $o['client_secret'] && '' !== $o['refresh_token'] && '' !== $o['folder_id'] );
	}

	/* ── AUTORISATION ──────────────────────────────────────────────────── */

	/** L'adresse vers laquelle envoyer l'exploitant pour qu'il autorise l'accès. */
	private function acdc_gdrive_auth_url() {
		$o = $this->acdc_gdrive_settings();
		if ( '' === $o['client_id'] ) {
			return '';
		}
		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( array(
			'client_id'     => $o['client_id'],
			'redirect_uri'  => $this->acdc_gdrive_redirect_uri(),
			'response_type' => 'code',
			/* drive.file : l'application ne voit QUE les fichiers qu'elle a
			   elle-même déposés. Elle ne peut pas lire le reste du Drive — c'est
			   le périmètre le plus étroit qui permette de déposer une archive. */
			'scope'         => 'https://www.googleapis.com/auth/drive.file',
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => wp_create_nonce( 'acdc_gdrive_state' ),
		), '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Retour de Google : on échange le code contre un jeton de rafraîchissement.
	 *
	 * Branché sur l'affichage de l'écran de maintenance, pas sur un point
	 * d'entrée public : l'échange n'a de sens que pour un administrateur connecté
	 * qui vient de cliquer sur « Connecter mon Drive ».
	 */
	public function acdc_gdrive_maybe_handle_callback() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_GET['acdc_gdrive'] ) || 'callback' !== sanitize_key( wp_unslash( $_GET['acdc_gdrive'] ) ) ) {
			return;
		}
		$etat = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		if ( ! wp_verify_nonce( $etat, 'acdc_gdrive_state' ) ) {
			$this->acdc_gdrive_save_settings( array( 'derniere_erreur' => 'Retour Google non reconnu (jeton d’état invalide).' ) );
			return;
		}
		if ( isset( $_GET['error'] ) ) {
			$this->acdc_gdrive_save_settings( array( 'derniere_erreur' => 'Autorisation refusée : ' . sanitize_text_field( wp_unslash( $_GET['error'] ) ) ) );
			return;
		}
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( '' === $code ) {
			return;
		}
		$o = $this->acdc_gdrive_settings();
		$reponse = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'timeout' => 30,
			'body'    => array(
				'code'          => $code,
				'client_id'     => $o['client_id'],
				'client_secret' => $o['client_secret'],
				'redirect_uri'  => $this->acdc_gdrive_redirect_uri(),
				'grant_type'    => 'authorization_code',
			),
		) );
		$jeton = $this->acdc_gdrive_lire_reponse( $reponse );
		if ( is_wp_error( $jeton ) ) {
			$this->acdc_gdrive_save_settings( array( 'derniere_erreur' => $jeton->get_error_message() ) );
			return;
		}
		if ( empty( $jeton['refresh_token'] ) ) {
			/* Google ne renvoie le jeton de rafraîchissement qu'à la PREMIÈRE
			   autorisation, sauf si l'on demande explicitement un nouveau
			   consentement — ce que fait acdc_gdrive_auth_url(). S'il manque
			   quand même, le dire plutôt que d'enregistrer une connexion vide. */
			$this->acdc_gdrive_save_settings( array( 'derniere_erreur' => 'Google n’a pas renvoyé de jeton durable. Révoquez l’accès dans votre compte Google, puis reconnectez.' ) );
			return;
		}
		$this->acdc_gdrive_save_settings( array(
			'refresh_token'   => (string) $jeton['refresh_token'],
			'actif'           => 1,
			'derniere_erreur' => '',
		) );
		$this->log_action_event( 'gdrive_connect', 'settings', 0, 'success' );
	}

	/** Un jeton d'accès court, obtenu à partir du jeton durable. */
	private function acdc_gdrive_access_token() {
		$cache = get_transient( 'acdc_of_gdrive_access' );
		if ( is_string( $cache ) && '' !== $cache ) {
			return $cache;
		}
		$o = $this->acdc_gdrive_settings();
		if ( '' === $o['refresh_token'] ) {
			return new WP_Error( 'acdc_gdrive_no_token', 'Aucun Drive connecté.' );
		}
		$reponse = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'timeout' => 30,
			'body'    => array(
				'client_id'     => $o['client_id'],
				'client_secret' => $o['client_secret'],
				'refresh_token' => $o['refresh_token'],
				'grant_type'    => 'refresh_token',
			),
		) );
		$jeton = $this->acdc_gdrive_lire_reponse( $reponse );
		if ( is_wp_error( $jeton ) ) {
			return $jeton;
		}
		if ( empty( $jeton['access_token'] ) ) {
			return new WP_Error( 'acdc_gdrive_no_access', 'Google n’a pas renvoyé de jeton d’accès.' );
		}
		$duree = isset( $jeton['expires_in'] ) ? max( 60, (int) $jeton['expires_in'] - 60 ) : 3000;
		set_transient( 'acdc_of_gdrive_access', (string) $jeton['access_token'], $duree );
		return (string) $jeton['access_token'];
	}

	/**
	 * Lit une réponse HTTP de Google, ou dit précisément ce qui a manqué.
	 *
	 * Un envoi de sauvegarde qui échoue en silence est pire qu'un envoi absent :
	 * on croit ses données sorties de la machine.
	 */
	private function acdc_gdrive_lire_reponse( $reponse ) {
		if ( is_wp_error( $reponse ) ) {
			return new WP_Error( 'acdc_gdrive_http', 'Google injoignable : ' . $reponse->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $reponse );
		$corps = json_decode( (string) wp_remote_retrieve_body( $reponse ), true );
		if ( $code < 200 || $code > 299 ) {
			$detail = '';
			if ( is_array( $corps ) ) {
				$detail = (string) ( $corps['error_description'] ?? ( is_array( $corps['error'] ?? null ) ? ( $corps['error']['message'] ?? '' ) : ( $corps['error'] ?? '' ) ) );
			}
			return new WP_Error( 'acdc_gdrive_status', sprintf( 'Google a répondu %d%s', $code, '' !== $detail ? ' : ' . $detail : '' ) );
		}
		return is_array( $corps ) ? $corps : array();
	}

	/* ── ENVOI ─────────────────────────────────────────────────────────── */

	/**
	 * Dépose une archive dans le dossier Drive, par envoi reprenable.
	 *
	 * Une archive contenant les fichiers de preuve pèse lourd : un envoi en une
	 * seule requête dépasserait le temps d'exécution d'un hébergement mutualisé.
	 * On utilise donc l'envoi reprenable de Google, par tranches, et l'on
	 * s'arrête proprement si le temps manque plutôt que de mourir au milieu.
	 *
	 * @param string $chemin  Archive à envoyer.
	 * @param string $nom     Nom affiché dans Drive.
	 * @return true|WP_Error
	 */
	private function acdc_gdrive_envoyer( $chemin, $nom ) {
		if ( ! is_file( $chemin ) ) {
			return new WP_Error( 'acdc_gdrive_fichier', 'Archive introuvable : ' . basename( $chemin ) );
		}
		$o = $this->acdc_gdrive_settings();
		if ( '' === $o['folder_id'] ) {
			return new WP_Error( 'acdc_gdrive_dossier', 'Aucun dossier Drive indiqué.' );
		}
		$acces = $this->acdc_gdrive_access_token();
		if ( is_wp_error( $acces ) ) {
			return $acces;
		}

		$taille = (int) filesize( $chemin );
		$ouverture = wp_remote_post( 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&supportsAllDrives=true', array(
			'timeout' => 30,
			'headers' => array(
				'Authorization'           => 'Bearer ' . $acces,
				'Content-Type'            => 'application/json; charset=UTF-8',
				'X-Upload-Content-Type'   => 'application/zip',
				'X-Upload-Content-Length' => (string) $taille,
			),
			'body'    => wp_json_encode( array(
				'name'     => $nom,
				'parents'  => array( $o['folder_id'] ),
				'mimeType' => 'application/zip',
			) ),
		) );
		if ( is_wp_error( $ouverture ) ) {
			return new WP_Error( 'acdc_gdrive_http', 'Google injoignable : ' . $ouverture->get_error_message() );
		}
		$code_ouverture = (int) wp_remote_retrieve_response_code( $ouverture );
		if ( $code_ouverture < 200 || $code_ouverture > 299 ) {
			$lu = $this->acdc_gdrive_lire_reponse( $ouverture );
			return is_wp_error( $lu ) ? $lu : new WP_Error( 'acdc_gdrive_open', 'Ouverture de l’envoi refusée.' );
		}
		$session = (string) wp_remote_retrieve_header( $ouverture, 'location' );
		if ( '' === $session ) {
			return new WP_Error( 'acdc_gdrive_session', 'Google n’a pas ouvert de session d’envoi.' );
		}

		$tranche  = 8 * 1024 * 1024; /* 8 Mo : multiple de 256 Ko, exigé par Google */
		$position = 0;
		$debut    = time();
		$fh = fopen( $chemin, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return new WP_Error( 'acdc_gdrive_lecture', 'Archive illisible.' );
		}
		while ( $position < $taille ) {
			/* On garde une marge sur le temps d'exécution : mieux vaut un envoi
			   incomplet signalé qu'un processus tué au milieu. */
			if ( time() - $debut > 240 ) {
				fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				return new WP_Error( 'acdc_gdrive_temps', sprintf( 'Envoi interrompu faute de temps à %d %% — archive trop lourde pour cet hébergement.', (int) ( $position / max( 1, $taille ) * 100 ) ) );
			}
			$morceau = fread( $fh, $tranche ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false === $morceau || '' === $morceau ) {
				break;
			}
			$fin = $position + strlen( $morceau ) - 1;
			$envoi = wp_remote_request( $session, array(
				'method'  => 'PUT',
				'timeout' => 120,
				'headers' => array(
					'Content-Length' => (string) strlen( $morceau ),
					'Content-Range'  => sprintf( 'bytes %d-%d/%d', $position, $fin, $taille ),
				),
				'body'    => $morceau,
			) );
			if ( is_wp_error( $envoi ) ) {
				fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				return new WP_Error( 'acdc_gdrive_http', 'Envoi interrompu : ' . $envoi->get_error_message() );
			}
			$code = (int) wp_remote_retrieve_response_code( $envoi );
			if ( 308 === $code ) {
				$position = $fin + 1;
				continue;
			}
			if ( $code >= 200 && $code <= 299 ) {
				fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				return true;
			}
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$lu = $this->acdc_gdrive_lire_reponse( $envoi );
			return is_wp_error( $lu ) ? $lu : new WP_Error( 'acdc_gdrive_put', sprintf( 'Google a répondu %d pendant l’envoi.', $code ) );
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return true;
	}

	/* ── CONSERVATION CÔTÉ DRIVE ───────────────────────────────────────── */

	/**
	 * Ne garde que les N archives les plus récentes dans le dossier.
	 *
	 * On ne supprime QUE les fichiers déposés par cette application — la portée
	 * demandée à Google (drive.file) ne donne d'ailleurs accès à rien d'autre.
	 * Un dossier partagé ne doit jamais devenir un endroit où le plugin efface
	 * ce qu'il n'a pas écrit.
	 */
	private function acdc_gdrive_appliquer_conservation() {
		$o = $this->acdc_gdrive_settings();
		$garder = max( 1, (int) $o['conserver'] );
		$acces = $this->acdc_gdrive_access_token();
		if ( is_wp_error( $acces ) ) {
			return $acces;
		}
		$url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query( array(
			'q'        => sprintf( "'%s' in parents and trashed = false and mimeType = 'application/zip'", $o['folder_id'] ),
			'fields'   => 'files(id,name,createdTime)',
			'orderBy'  => 'createdTime desc',
			'pageSize' => 200,
		), '', '&', PHP_QUERY_RFC3986 );
		$liste = $this->acdc_gdrive_lire_reponse( wp_remote_get( $url, array(
			'timeout' => 30,
			'headers' => array( 'Authorization' => 'Bearer ' . $acces ),
		) ) );
		if ( is_wp_error( $liste ) ) {
			return $liste;
		}
		$fichiers = isset( $liste['files'] ) && is_array( $liste['files'] ) ? $liste['files'] : array();
		$supprimes = 0;
		foreach ( array_slice( $fichiers, $garder ) as $vieux ) {
			if ( empty( $vieux['id'] ) ) {
				continue;
			}
			$suppression = wp_remote_request( 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( (string) $vieux['id'] ), array(
				'method'  => 'DELETE',
				'timeout' => 30,
				'headers' => array( 'Authorization' => 'Bearer ' . $acces ),
			) );
			if ( ! is_wp_error( $suppression ) ) {
				$supprimes++;
			}
		}
		return $supprimes;
	}

	/* ── LE POINT D'ENTRÉE : SAUVEGARDER PUIS ENVOYER ──────────────────── */

	/**
	 * Crée une sauvegarde et la dépose sur Drive. Appelé par la tâche planifiée.
	 *
	 * @return array{ok:bool,message:string}
	 */
	public function acdc_gdrive_sauvegarder_et_envoyer() {
		$resultat = $this->create_manual_backup_snapshot( 'planifiee', array( 'origine' => 'cron' ) );
		if ( empty( $resultat['success'] ) || empty( $resultat['manifest'] ) ) {
			$message = 'La sauvegarde n’a pas pu être créée : rien n’a été envoyé.';
			$this->acdc_gdrive_save_settings( array( 'derniere_erreur' => $message ) );
			$this->log_action_event( 'gdrive_upload', 'settings', 0, 'error', array( 'message' => $message ) );
			return array( 'ok' => false, 'message' => $message );
		}
		if ( ! $this->acdc_gdrive_pret() ) {
			$message = 'Sauvegarde créée sur le serveur. Drive non connecté : elle n’est pas partie.';
			$this->acdc_gdrive_save_settings( array( 'derniere_erreur' => $message ) );
			return array( 'ok' => false, 'message' => $message );
		}

		/* Le manifeste porte le chemin de l'archive et son nom lisible. */
		$manifeste = $this->get_backup_absolute_path( (string) $resultat['manifest'] );
		$donnees   = ( '' !== $manifeste && is_file( $manifeste ) ) ? json_decode( (string) file_get_contents( $manifeste ), true ) : array();
		$archive   = '';
		if ( is_array( $donnees ) && ! empty( $donnees['archive'] ) ) {
			$archive = trailingslashit( dirname( $manifeste ) ) . basename( (string) $donnees['archive'] );
		}
		if ( '' === $archive || ! is_file( $archive ) ) {
			$message = 'Archive introuvable après la sauvegarde : rien n’a été envoyé.';
			$this->acdc_gdrive_save_settings( array( 'derniere_erreur' => $message ) );
			$this->log_action_event( 'gdrive_upload', 'settings', 0, 'error', array( 'message' => $message ) );
			return array( 'ok' => false, 'message' => $message );
		}
		$nom = is_array( $donnees ) && ! empty( $donnees['nom'] ) ? (string) $donnees['nom'] : $this->acdc_nom_sauvegarde();
		if ( ! empty( $donnees['complete'] ) ) {
			$nom .= '.zip';
		} else {
			/* Une archive incomplète part quand même — mieux vaut une copie
			   partielle hors du serveur que rien — mais elle le DIT dans son nom. */
			$nom .= ' (INCOMPLETE).zip';
		}

		$envoi = $this->acdc_gdrive_envoyer( $archive, $nom );
		if ( is_wp_error( $envoi ) ) {
			$message = $envoi->get_error_message();
			$this->acdc_gdrive_save_settings( array( 'derniere_erreur' => $message ) );
			$this->log_action_event( 'gdrive_upload', 'settings', 0, 'error', array( 'message' => $message ) );
			return array( 'ok' => false, 'message' => $message );
		}

		$purge = $this->acdc_gdrive_appliquer_conservation();
		$this->acdc_gdrive_save_settings( array(
			'dernier_envoi'   => current_time( 'mysql' ),
			'derniere_erreur' => '',
		) );
		$this->log_action_event( 'gdrive_upload', 'settings', 0, 'success', array(
			'archive'   => basename( $archive ),
			'supprimes' => is_wp_error( $purge ) ? 0 : (int) $purge,
		) );
		return array( 'ok' => true, 'message' => sprintf( '« %s » déposée sur Drive.', $nom ) );
	}

	/** La tâche planifiée, deux fois par jour. */
	public function cron_gdrive_backup() {
		try {
			$this->acdc_gdrive_sauvegarder_et_envoyer();
		} catch ( \Throwable $e ) {
			$this->acdc_gdrive_save_settings( array( 'derniere_erreur' => 'Interruption : ' . $e->getMessage() ) );
			$this->log_error( 'gdrive_cron', $e->getMessage() );
		}
	}
}
