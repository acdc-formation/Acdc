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
			'connecte_le'   => '',
			'alerte_le'     => '',
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

	/**
	 * L'adresse vers laquelle envoyer l'exploitant pour qu'il autorise l'accès.
	 *
	 * ACDC 3.25.295 — LE JETON D'ÉTAT N'EST PLUS UN NONCE WORDPRESS.
	 *
	 * C'était le réflexe, et c'était le mauvais outil. Un nonce est lié à la
	 * session, au navigateur et à une fenêtre de douze heures. Or ce jeton-là
	 * part faire un aller-retour de plusieurs minutes par un écran de
	 * consentement Google, en traversant au passage un cache de page et le
	 * pare-feu d'un hébergement mutualisé. Il échoue donc pour une dizaine de
	 * raisons qui n'ont rien à voir avec une tentative d'attaque — et, en
	 * échouant, il disait toujours la même phrase : « jeton d'état invalide ».
	 * L'exploitant se retrouvait devant une panne sans cause lisible.
	 *
	 * Un jeton tiré au hasard et rangé côté serveur pour quinze minutes protège
	 * exactement contre la même chose — qu'un retour fabriqué ailleurs passe
	 * pour le nôtre — sans dépendre ni des cookies, ni du cache, ni de l'heure.
	 * Et il permet de distinguer les trois pannes, qui ne se corrigent pas au
	 * même endroit : rien n'est revenu, c'est trop tard, ce n'est pas le bon.
	 */
	private function acdc_gdrive_auth_url() {
		$o = $this->acdc_gdrive_settings();
		if ( '' === $o['client_id'] ) {
			return '';
		}
		$etat = wp_generate_password( 32, false );
		set_transient( 'acdc_of_gdrive_state', $etat, 15 * MINUTE_IN_SECONDS );
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
			'state'         => $etat,
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
		/* Trois pannes distinctes se cachaient derrière une seule phrase. Elles
		   ne se corrigent pas au même endroit : la première est chez
		   l'hébergeur, la deuxième dans l'ordre des gestes, la troisième est la
		   seule qui mérite qu'on s'inquiète. */
		$etat_recu = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$etat_pose = (string) get_transient( 'acdc_of_gdrive_state' );
		/* Usage unique : consommé dès qu'il est lu, quel que soit le verdict. */
		delete_transient( 'acdc_of_gdrive_state' );

		if ( '' === $etat_recu ) {
			$this->acdc_gdrive_save_settings( array( 'derniere_erreur' => 'Le retour de Google est arrivé SANS son jeton d’état : il a été retiré en chemin. Ce n’est pas un réglage à corriger — regardez le pare-feu (ModSecurity) et le cache de l’hébergement, qui filtrent les paramètres des adresses de /wp-admin/.' ) );
			return;
		}
		if ( '' === $etat_pose ) {
			$this->acdc_gdrive_save_settings( array( 'derniere_erreur' => 'Le jeton d’état a expiré : plus de quinze minutes se sont écoulées entre le clic sur « Connecter mon Drive » et le retour, ou cet écran a été rechargé entre-temps. Recommencez, sans passer par un onglet resté ouvert.' ) );
			return;
		}
		if ( ! hash_equals( $etat_pose, $etat_recu ) ) {
			$this->acdc_gdrive_save_settings( array( 'derniere_erreur' => 'Le jeton d’état renvoyé ne correspond pas à celui posé par cet écran : ce retour ne vient pas de la demande faite ici. Rien n’a été enregistré.' ) );
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
			/* Point de départ de la veille : sans lui, un Drive connecté et
			   jamais utilisé n'aurait aucune date à laquelle comparer, et le
			   silence passerait pour normal. */
			'connecte_le'     => current_time( 'mysql', true ),
			'alerte_le'       => '',
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
		/* ACDC 3.25.295 — L'ORDRE ÉTAIT INVERSÉ, ET IL COÛTAIT CHER.
		   Cette fonction fabriquait l'archive complète — 178 Mo, 451 fichiers de
		   preuve, 62 tables — PUIS constatait que le Drive n'était pas connecté.
		   Deux fois par jour, le serveur produisait donc 178 Mo pour rien.
		   Pire : c'était le seul chemin d'échec qui n'écrivait pas dans le
		   journal. Tous les autres y laissaient une trace ; celui qui allait se
		   produire tous les jours tant que le Drive n'était pas branché, non. La
		   panne la plus probable était la seule invisible. */
		if ( ! $this->acdc_gdrive_pret() ) {
			$message = 'Drive non connecté : aucune archive n’a été fabriquée et rien n’est parti. Rendez-vous dans « Données & maintenance » pour terminer la connexion.';
			$this->acdc_gdrive_save_settings( array( 'derniere_erreur' => $message ) );
			$this->log_action_event( 'gdrive_upload', 'settings', 0, 'error', array( 'message' => $message ) );
			return array( 'ok' => false, 'message' => $message );
		}
		$resultat = $this->create_manual_backup_snapshot( 'planifiee', array( 'origine' => 'cron' ) );
		if ( empty( $resultat['success'] ) || empty( $resultat['manifest'] ) ) {
			$message = 'La sauvegarde n’a pas pu être créée : rien n’a été envoyé.';
			$this->acdc_gdrive_save_settings( array( 'derniere_erreur' => $message ) );
			$this->log_action_event( 'gdrive_upload', 'settings', 0, 'error', array( 'message' => $message ) );
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

		/* ACDC 3.25.313 — L'ARCHIVE LOCALE PART UNE FOIS DÉPOSÉE.
		   Le dépôt réussi, elle restait sur le disque du serveur. C'est
		   exactement ce dont on voulait se passer : l'intérêt d'une sauvegarde
		   hors site est qu'elle n'occupe plus la machine qu'elle protège.
		   On n'efface qu'APRÈS un envoi confirmé — jamais avant, jamais sur
		   erreur : à ce moment-là, l'exemplaire local est le seul qui existe. */
		if ( ! empty( $archive ) && is_file( $archive ) ) {
			$__base = method_exists( $this, 'get_backup_base_directory' ) ? $this->get_backup_base_directory() : '';
			if ( '' !== $__base && 0 === strpos( $archive, trailingslashit( $__base ) ) ) {
				@unlink( $archive ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}

		$purge = $this->acdc_gdrive_appliquer_conservation();
		$this->acdc_gdrive_save_settings( array(
			/* ACDC 3.25.295 — Écrit en UTC, sans exception. Voir acdc_gdrive_veiller(). */
			'dernier_envoi'   => current_time( 'mysql', true ),
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
		$this->acdc_gdrive_veiller();
	}

	/* ── LES RENDEZ-VOUS ───────────────────────────────────────────────── */

	/** Les deux heures annoncées, telles que l'exploitant les lit. */
	public function acdc_gdrive_heures_rdv() {
		return array( 'acdc_of_gdrive_backup_midi' => 12, 'acdc_of_gdrive_backup_soir' => 18 );
	}

	/**
	 * Poser — et redresser — les rendez-vous de 12h00 et 18h00.
	 *
	 * ACDC 3.25.296 — ILS N'ÉTAIENT PAS À L'HEURE DITE.
	 *
	 * La 3.25.294 les calculait avec strtotime('today 12:00'). WordPress règle le
	 * fuseau de PHP sur UTC au démarrage : cette expression donnait donc midi
	 * UTC, c'est-à-dire QUATORZE HEURES à Paris l'été. L'écran annonçait 12h00 et
	 * 18h00, les sauvegardes seraient parties à 14h00 et 20h00, et rien n'aurait
	 * signalé l'écart — l'archive serait bien arrivée, deux heures plus tard,
	 * tous les jours. C'est la troisième forme prise aujourd'hui par le même
	 * défaut : une heure écrite dans un fuseau, relue dans un autre.
	 *
	 * wp_timezone() est l'horloge de l'exploitant. On calcule dedans, et on ne
	 * rend à WordPress qu'un instant vrai — ce qu'il attend.
	 *
	 * ET ON REDRESSE L'EXISTANT. Un simple « s'il n'est pas déjà posé » aurait
	 * laissé en place, sur les installations de la 3.25.294, un rendez-vous à
	 * 14h00 que personne n'aurait jamais vu bouger. On compare donc l'heure
	 * réelle du prochain déclenchement à l'heure voulue, et on la corrige.
	 */
	public function acdc_gdrive_planifier() {
		foreach ( $this->acdc_gdrive_heures_rdv() as $rdv => $heure ) {
			$actuel = wp_next_scheduled( $rdv );
			if ( $actuel && (int) wp_date( 'G', (int) $actuel ) !== (int) $heure ) {
				wp_unschedule_event( (int) $actuel, $rdv );
				$actuel = false;
			}
			if ( ! $actuel ) {
				wp_schedule_event( $this->acdc_gdrive_prochain_passage( $heure ), 'daily', $rdv );
			}
		}
	}

	/** Le prochain passage à cette heure-là, dans le fuseau du site. */
	private function acdc_gdrive_prochain_passage( $heure ) {
		try {
			$zone  = wp_timezone();
			$cible = ( new DateTimeImmutable( 'now', $zone ) )->setTime( (int) $heure, 0, 0 );
			if ( $cible->getTimestamp() <= time() ) {
				$cible = $cible->modify( '+1 day' );
			}
			return $cible->getTimestamp();
		} catch ( \Exception $e ) {
			return time() + HOUR_IN_SECONDS;
		}
	}

	/* ── LA VEILLE ─────────────────────────────────────────────────────── */

	/**
	 * L'alerte se déclenche sur l'ÂGE DU DERNIER SUCCÈS, pas sur l'erreur.
	 *
	 * C'est tout le sujet. Une alerte branchée sur « une erreur est survenue »
	 * ne verrait pas la panne la plus probable de cet hébergement : les tâches
	 * de WordPress ne partent qu'à la visite suivante, et sur un site peu
	 * fréquenté à midi elles peuvent ne pas partir du tout. Alors rien
	 * n'échoue. Il n'y a aucune erreur à signaler. L'écran affiche toujours
	 * « Dernier envoi réussi le… » avec une date qui vieillit doucement, dans
	 * une page que personne n'ouvre — et le jour où l'on en a besoin, la
	 * dernière copie des émargements signés a trois mois.
	 *
	 * Une date trop vieille, elle, couvre les deux pannes d'un seul contrôle :
	 * l'envoi qui échoue et l'envoi qui n'a jamais eu lieu.
	 *
	 * DEUX DÉCLENCHEURS, PARCE QU'UN SEUL SE SERAIT TU AVEC LE RESTE. La veille
	 * est appelée après chaque tâche planifiée, et aussi à l'ouverture de
	 * l'administration (une fois par heure au plus). Si le cron est mort, la
	 * simple visite d'un écran suffit à donner l'alerte. Reste un cas qu'aucun
	 * code ne peut couvrir depuis l'intérieur : personne ne visite le site ET
	 * le cron ne tourne plus. Là, seule une surveillance extérieure verrait
	 * quelque chose — c'est une limite, elle est écrite ici pour ne pas être
	 * confondue avec une garantie.
	 */
	/**
	 * L'instant vrai derrière une date de la veille — lue comme de l'UTC.
	 *
	 * ACDC 3.25.295 — DEUX HORLOGES, DEUX VÉRITÉS. La date était écrite avec
	 * current_time('mysql'), qui applique l'option « gmt_offset », et relue avec
	 * mysql2date(), qui applique wp_timezone() — laquelle se règle sur
	 * « timezone_string ». Ces deux réglages sont censés s'accorder ; ils
	 * peuvent diverger, et sur ce site ils divergent : la même sauvegarde
	 * s'affichait à 13h11 sur une ligne et à 15h10 deux lignes plus bas.
	 *
	 * Une veille qui déclenche sur un ÂGE ne peut pas reposer là-dessus : deux
	 * heures d'écart, et l'alerte part deux heures trop tôt ou deux heures trop
	 * tard — ou pas du tout. On écrit donc en UTC, on compare en UTC, et on ne
	 * convertit qu'au dernier moment, pour l'œil humain. Aucun réglage de site
	 * ne s'interpose plus entre l'écriture et la lecture.
	 */
	private function acdc_gdrive_instant( $date ) {
		$date = trim( (string) $date );
		if ( '' === $date ) {
			return 0;
		}
		$ts = strtotime( $date . ' UTC' );
		return $ts ? (int) $ts : 0;
	}

	public function acdc_gdrive_veiller() {
		$o = $this->acdc_gdrive_settings();

		/* Tant que le Drive n'est pas connecté, l'écran le dit en toutes lettres
		   et l'exploitant est en train de s'en occuper : un courrier quotidien
		   ne lui apprendrait rien et lui apprendrait à ne plus les lire. */
		if ( ! $this->acdc_gdrive_pret() ) {
			return;
		}

		$reference = '' !== $o['dernier_envoi'] ? $o['dernier_envoi'] : $o['connecte_le'];
		if ( '' === $reference ) {
			return;
		}
		$age     = time() - $this->acdc_gdrive_instant( $reference );
		$limite  = 36 * HOUR_IN_SECONDS; /* Deux rendez-vous manqués, pas un. */
		$destinataire = sanitize_email( (string) get_option( 'admin_email' ) );
		if ( '' === $destinataire ) {
			return;
		}

		/* Le retour à la normale se dit, sinon le silence resterait ambigu :
		   « je n'ai rien reçu » ne distingue pas « tout va bien » de « l'alerte
		   elle-même est en panne ». */
		if ( $age <= $limite ) {
			if ( '' !== $o['alerte_le'] ) {
				$this->acdc_gdrive_save_settings( array( 'alerte_le' => '' ) );
				$this->acdc_send_branded_email(
					$destinataire,
					'Sauvegardes ACDC : le dépôt sur Drive a repris',
					array(
						'intro_html' => '<p>Les sauvegardes repartent normalement vers Google Drive.</p>',
						'body_html'  => '<p>Dernier dépôt réussi : <strong>' . esc_html( get_date_from_gmt( $o['dernier_envoi'], 'd/m/Y à H:i' ) ) . '</strong>.</p>',
					),
					array( 'alerte_exploitant' => true, 'email_category' => 'exploitation' )
				);
			}
			return;
		}

		/* Une alerte par jour au plus : la panne dure, le rappel ne doit pas
		   devenir le bruit qui la fait ignorer. */
		if ( '' !== $o['alerte_le'] && ( time() - $this->acdc_gdrive_instant( $o['alerte_le'] ) ) < DAY_IN_SECONDS ) {
			return;
		}

		$heures = (int) floor( $age / HOUR_IN_SECONDS );
		$quoi   = '' !== $o['dernier_envoi']
			? 'Dernier dépôt réussi : <strong>' . esc_html( get_date_from_gmt( $o['dernier_envoi'], 'd/m/Y à H:i' ) ) . '</strong>.'
			: '<strong>Aucun dépôt n’a jamais abouti</strong> depuis la connexion du Drive.';
		$cause = '' !== $o['derniere_erreur']
			? '<p>Dernière erreur enregistrée : ' . esc_html( $o['derniere_erreur'] ) . '</p>'
			: '<p>Aucune erreur n’a été enregistrée : l’envoi n’a donc pas échoué, il n’a pas eu lieu. Les tâches de WordPress ne partent qu’à la première visite qui suit l’heure prévue — vérifiez la tâche planifiée de votre hébergeur.</p>';

		$this->acdc_send_branded_email(
			$destinataire,
			sprintf( 'Sauvegardes ACDC : rien n’est parti sur Drive depuis %d heures', $heures ),
			array(
				'intro_html' => '<p>Vos sauvegardes ne quittent plus le serveur.</p>',
				'body_html'  => '<p>' . $quoi . '</p>' . $cause
					. '<p>Tant que ce message revient, la seule copie de vos données — apprenants, conventions, émargements signés, factures — est sur le disque qu’elle est censée protéger.</p>',
			),
			array( 'alerte_exploitant' => true, 'email_category' => 'exploitation' )
		);
		$this->acdc_gdrive_save_settings( array( 'alerte_le' => current_time( 'mysql', true ) ) );
		$this->log_action_event( 'gdrive_veille', 'settings', 0, 'error', array( 'heures' => $heures ) );
	}

	/** La veille passe aussi par l'administration : un cron mort ne s'auto-signale pas. */
	public function acdc_gdrive_veiller_en_admin() {
		if ( ! current_user_can( 'manage_options' ) || get_transient( 'acdc_of_gdrive_veille' ) ) {
			return;
		}
		set_transient( 'acdc_of_gdrive_veille', 1, HOUR_IN_SECONDS );
		$this->acdc_gdrive_veiller();
	}
}
