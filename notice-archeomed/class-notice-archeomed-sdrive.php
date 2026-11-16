<?php
/**
 * L'accès du site à sDrive, le Nextcloud du CNRS.
 *
 * Les identifiants se déclaraient dans wp-config.php, que la revue ne peut
 * pas toucher : l'hébergement est celui de l'institution. Le site se relie
 * donc lui-même, comme le client de synchronisation de Nextcloud (« Login
 * Flow v2 ») : il demande une liaison, l'administrateur l'autorise dans son
 * navigateur, connecté par Janus, et sDrive rend au site un mot de passe
 * d'application qui n'appartient qu'à lui. Personne ne le tape ni ne le
 * lit ; il se révoque d'un bouton, ici ou dans sDrive.
 *
 * Ce mot de passe ouvre tout le compte qui l'a autorisé, pas seulement le
 * dossier des figures. Il est donc scellé dans la base avec les clés de
 * wp-config.php : une copie de la base seule ne le livre pas.
 *
 * Les constantes NA_SDRIVE_* gardent la priorité quand elles sont déclarées
 * toutes les quatre.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Sdrive {

	const ADRESSE = 'https://sdrive.cnrs.fr';
	const OPTION  = 'na_sdrive';
	// Nextcloud garde une demande de liaison vingt minutes.
	const ATTENTE = 20 * MINUTE_IN_SECONDS;

	/** Ce qu'on demande de chaque entrée d'un dossier : sa nature et sa taille. */
	const PROPRIETES = '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/><d:getcontentlength/></d:prop></d:propfind>';

	const CONSTANTES = array( 'NA_SDRIVE_WEBDAV', 'NA_SDRIVE_IDENTIFIANT', 'NA_SDRIVE_MOT_DE_PASSE', 'NA_SDRIVE_DOSSIER' );

	/** Vrai quand wp-config.php déclare les quatre constantes : elles priment. */
	public static function par_constantes() {
		foreach ( self::CONSTANTES as $c ) {
			if ( ! defined( $c ) || '' === trim( (string) constant( $c ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * De quoi parler à sDrive : adresse WebDAV du compte, identifiant, mot de
	 * passe et dossier. Null tant que rien n'est relié ni déclaré ; le dossier
	 * peut être vide, l'essai le dit alors.
	 */
	public static function acces() {
		if ( self::par_constantes() ) {
			return array(
				'webdav'       => (string) NA_SDRIVE_WEBDAV,
				'compte'       => (string) NA_SDRIVE_IDENTIFIANT,
				'mot_de_passe' => (string) NA_SDRIVE_MOT_DE_PASSE,
				'dossier'      => (string) NA_SDRIVE_DOSSIER,
			);
		}
		$lien = self::liaison();
		if ( empty( $lien['compte'] ) ) {
			return null;
		}
		$mot = self::desceller( isset( $lien['scelle'] ) ? $lien['scelle'] : '' );
		if ( '' === $mot ) {
			return null;
		}
		return array(
			'webdav'       => $lien['serveur'] . '/remote.php/dav/files/' . rawurlencode( $lien['compte'] ) . '/',
			'compte'       => $lien['compte'],
			'mot_de_passe' => $mot,
			'dossier'      => isset( $lien['dossier'] ) ? (string) $lien['dossier'] : '',
		);
	}

	/** La liaison enregistrée, mot de passe scellé compris ; vide sinon. */
	public static function liaison() {
		$lien = get_option( self::OPTION, array() );
		return is_array( $lien ) ? $lien : array();
	}

	/**
	 * Une liaison enregistrée dont le mot de passe ne se descelle plus : les
	 * clés de wp-config.php ont changé depuis. Il faut délier et relier.
	 */
	public static function liaison_illisible() {
		$lien = self::liaison();
		return ! empty( $lien['compte'] )
			&& '' === self::desceller( isset( $lien['scelle'] ) ? $lien['scelle'] : '' );
	}

	/** La demande de liaison en cours pour cet administrateur, ou null. */
	public static function demande_en_cours() {
		$demande = get_transient( self::cle_de_demande() );
		return ( is_array( $demande ) && ! empty( $demande['login'] ) ) ? $demande : null;
	}

	/**
	 * Première moitié de la liaison : sDrive ouvre une demande et donne
	 * l'adresse où l'administrateur l'autorise. Le jeton qui en relèvera la
	 * réponse reste sur le serveur, au nom de cet administrateur seul.
	 */
	public static function demander_la_liaison() {
		$reponse = wp_remote_post( self::ADRESSE . '/index.php/login/v2', array(
			'timeout'    => 15,
			'user-agent' => self::nom_du_site(),
		) );
		if ( is_wp_error( $reponse ) ) {
			return self::injoignable( $reponse );
		}
		$data     = json_decode( wp_remote_retrieve_body( $reponse ), true );
		$endpoint = isset( $data['poll']['endpoint'] ) ? (string) $data['poll']['endpoint'] : '';
		if ( 200 !== (int) wp_remote_retrieve_response_code( $reponse ) || empty( $data['poll']['token'] )
			|| empty( $data['login'] ) || ! self::chez_sdrive( $endpoint ) || ! self::chez_sdrive( $data['login'] ) ) {
			return array( 'ok' => false, 'message' => 'sDrive n’a pas ouvert de demande de liaison (code HTTP '
				. (int) wp_remote_retrieve_response_code( $reponse ) . ').' );
		}
		set_transient( self::cle_de_demande(), array(
			'token'    => (string) $data['poll']['token'],
			'endpoint' => $endpoint,
			'login'    => (string) $data['login'],
		), self::ATTENTE );
		return array( 'ok' => true, 'message' => 'Demande ouverte : autorisez le site dans sDrive, puis terminez la liaison.' );
	}

	/**
	 * Seconde moitié : sDrive rend le compte et son mot de passe
	 * d'application, une fois, si l'administrateur a autorisé. Une liaison
	 * plus ancienne est révoquée d'abord, pour ne pas laisser derrière soi un
	 * mot de passe que plus rien n'emploie.
	 */
	public static function finir_la_liaison() {
		$demande = self::demande_en_cours();
		if ( ! $demande ) {
			return array( 'ok' => false, 'message' => 'Aucune demande en cours, ou elle a expiré après vingt minutes : reliez de nouveau.' );
		}
		$reponse = wp_remote_post( $demande['endpoint'], array(
			'timeout'    => 15,
			'user-agent' => self::nom_du_site(),
			'body'       => array( 'token' => $demande['token'] ),
		) );
		if ( is_wp_error( $reponse ) ) {
			return self::injoignable( $reponse );
		}
		$code = (int) wp_remote_retrieve_response_code( $reponse );
		if ( 404 === $code ) {
			return array( 'ok' => false, 'message' => 'sDrive n’a pas encore reçu l’autorisation : ouvrez le lien, accordez l’accès, puis terminez de nouveau.' );
		}
		$data = json_decode( wp_remote_retrieve_body( $reponse ), true );
		if ( 200 !== $code || empty( $data['loginName'] ) || empty( $data['appPassword'] )
			|| empty( $data['server'] ) || ! self::chez_sdrive( $data['server'] ) ) {
			return array( 'ok' => false, 'message' => 'sDrive a répondu sans rendre d’accès (code HTTP ' . $code . ').' );
		}
		delete_transient( self::cle_de_demande() );
		$ancienne = self::liaison();
		if ( ! empty( $ancienne['compte'] ) ) {
			self::revoquer();
		}
		update_option( self::OPTION, array(
			'serveur' => untrailingslashit( (string) $data['server'] ),
			'compte'  => (string) $data['loginName'],
			'scelle'  => self::sceller( (string) $data['appPassword'] ),
			'dossier' => isset( $ancienne['dossier'] ) ? $ancienne['dossier'] : '',
			'le'      => time(),
		), false );
		return array( 'ok' => true, 'message' => 'Le site est relié au compte sDrive ' . $data['loginName'] . '.' );
	}

	/** Abandonne une demande de liaison restée sans réponse. */
	public static function oublier_la_demande() {
		delete_transient( self::cle_de_demande() );
		return array( 'ok' => true, 'message' => 'La demande de liaison est abandonnée.' );
	}

	/**
	 * Délie le site : le mot de passe d'application est révoqué chez sDrive,
	 * puis oublié ici. S'il ne se révoque pas, on l'oublie quand même, et
	 * l'on dit où le retirer à la main. Le dossier reste : il servira à la
	 * liaison suivante.
	 */
	public static function delier() {
		$revoque = self::revoquer();
		$lien    = self::liaison();
		update_option( self::OPTION, array( 'dossier' => isset( $lien['dossier'] ) ? $lien['dossier'] : '' ), false );
		if ( ! $revoque ) {
			return array( 'ok' => false, 'message' => 'Le site est délié, mais sDrive n’a pas confirmé la révocation : retirez l’appareil « '
				. self::nom_du_site() . ' » dans sDrive, Paramètres personnels, Sécurité.' );
		}
		return array( 'ok' => true, 'message' => 'Le site est délié, et son mot de passe d’application révoqué chez sDrive.' );
	}

	/** Le dossier où ranger, relatif à la racine du compte relié. */
	public static function enregistrer_le_dossier( $dossier ) {
		$propre = implode( '/', array_filter( array_map( 'trim', explode( '/', str_replace( '\\', '/', (string) $dossier ) ) ),
			function ( $segment ) {
				return '' !== $segment && '.' !== $segment && '..' !== $segment;
			} ) );
		$lien            = self::liaison();
		$lien['dossier'] = $propre;
		update_option( self::OPTION, $lien, false );
		return array( 'ok' => true, 'message' => '' === $propre
			? 'Le dossier est effacé : les figures iraient à la racine du compte, l’essai le refusera.'
			: 'Dossier enregistré : « ' . $propre . ' ».' );
	}

	/**
	 * Le site peut-il ranger ? Relié, et un dossier choisi : la racine du
	 * compte mêlerait les notices aux fichiers personnels de qui a relié.
	 */
	public static function pret() {
		$acces = self::acces();
		return null !== $acces && '' !== trim( $acces['dossier'], "/ \t" );
	}

	/**
	 * L'adresse d'un chemin pris sous le dossier choisi, segment par segment :
	 * espaces et accents doivent passer dans l'adresse.
	 */
	private static function adresse( $acces, $relatif ) {
		$segments = array_merge(
			array_filter( explode( '/', (string) $acces['dossier'] ), 'strlen' ),
			array_filter( explode( '/', (string) $relatif ), 'strlen' ) );
		return trailingslashit( $acces['webdav'] ) . implode( '/', array_map( 'rawurlencode', $segments ) );
	}

	/**
	 * Une requête WebDAV sous le dossier choisi. Rend le code HTTP — zéro
	 * quand rien n'a répondu — et la réponse, pour qui veut la lire.
	 */
	private static function requete( $methode, $relatif, $options = array() ) {
		if ( ! self::pret() ) {
			return array( 0, new WP_Error( 'na_sdrive', 'sDrive n’est pas relié, ou aucun dossier n’est choisi.' ) );
		}
		$acces   = self::acces();
		$entetes = isset( $options['headers'] ) ? (array) $options['headers'] : array();
		$options = array_merge( array( 'timeout' => 30 ), $options, array( 'method' => $methode ) );
		$options['headers'] = array( 'Authorization' => 'Basic ' . base64_encode( $acces['compte'] . ':' . $acces['mot_de_passe'] ) ) + $entetes;
		$reponse = wp_remote_request( self::adresse( $acces, $relatif ), $options );
		return array( is_wp_error( $reponse ) ? 0 : (int) wp_remote_retrieve_response_code( $reponse ), $reponse );
	}

	/**
	 * Le dossier choisi répond-il ? Une raison, vide s'il répond : un sDrive
	 * en panne ou une liaison révoquée ne sont la faute d'aucune notice.
	 */
	public static function joignable() {
		list( $code, $reponse ) = self::requete( 'PROPFIND', '', array( 'headers' => array( 'Depth' => '0' ) ) );
		return 207 === $code ? '' : self::pourquoi( $code, $reponse );
	}

	/** Ce qu'une requête refusée laisse dire : le code, ou l'erreur du transport. */
	private static function pourquoi( $code, $reponse ) {
		return is_wp_error( $reponse ) ? $reponse->get_error_message() : 'code HTTP ' . (int) $code;
	}

	/**
	 * Crée un dossier et ceux qui le précèdent. Un dossier qui existe déjà
	 * répond 405 : ce n'est pas un refus.
	 */
	public static function creer_le_dossier( $relatif ) {
		list( $code ) = self::requete( 'PROPFIND', $relatif, array( 'headers' => array( 'Depth' => '0' ) ) );
		if ( 207 === $code ) {
			return '';
		}
		$chemin = '';
		foreach ( array_filter( explode( '/', (string) $relatif ), 'strlen' ) as $segment ) {
			$chemin .= ( '' === $chemin ? '' : '/' ) . $segment;
			list( $code, $reponse ) = self::requete( 'MKCOL', $chemin );
			if ( ! in_array( $code, array( 201, 405 ), true ) ) {
				return 'le dossier « ' . $chemin . ' » ne se crée pas (' . self::pourquoi( $code, $reponse ) . ')';
			}
		}
		return '';
	}

	/**
	 * Dépose un fichier, puis relit sa taille chez sDrive : un dépôt n'est
	 * acquis que vérifié, et c'est sur cette vérification qu'on effacera la
	 * copie du serveur. Rend une raison, vide si tout va bien.
	 */
	public static function deposer( $local, $relatif ) {
		if ( ! is_readable( $local ) ) {
			return 'fichier introuvable sur le serveur';
		}
		$taille = (int) filesize( $local );
		$corps  = file_get_contents( $local );
		if ( false === $corps || strlen( $corps ) !== $taille ) {
			return 'fichier illisible sur le serveur';
		}
		list( $code, $reponse ) = self::requete( 'PUT', $relatif, array(
			'timeout' => 90,
			'body'    => $corps,
			'headers' => array( 'Content-Type' => 'application/octet-stream', 'OC-Checksum' => 'SHA1:' . sha1( $corps ) ),
		) );
		unset( $corps );
		if ( ! in_array( $code, array( 200, 201, 204 ), true ) ) {
			return 'dépôt refusé (' . self::pourquoi( $code, $reponse ) . ')';
		}
		$lue = self::taille( $relatif );
		return $lue === $taille ? '' : 'dépôt incomplet : ' . $lue . ' octets chez sDrive pour ' . $taille . ' ici';
	}

	/** Écrit un petit fichier de texte, celui des figures à déposer. */
	public static function ecrire( $texte, $relatif ) {
		list( $code, $reponse ) = self::requete( 'PUT', $relatif, array(
			'body'    => (string) $texte,
			'headers' => array( 'Content-Type' => 'text/plain; charset=utf-8' ),
		) );
		return in_array( $code, array( 200, 201, 204 ), true ) ? '' : 'écriture refusée (' . self::pourquoi( $code, $reponse ) . ')';
	}

	/** La taille d'un fichier chez sDrive ; -1 s'il n'y est pas. */
	public static function taille( $relatif ) {
		list( $code, $reponse ) = self::requete( 'PROPFIND', $relatif, array(
			'headers' => array( 'Depth' => '0', 'Content-Type' => 'application/xml; charset=utf-8' ),
			'body'    => '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:getcontentlength/></d:prop></d:propfind>',
		) );
		if ( 207 !== $code || ! preg_match( '#<(?:[a-z0-9]+:)?getcontentlength>\s*(\d+)\s*</#i', wp_remote_retrieve_body( $reponse ), $m ) ) {
			return -1;
		}
		return (int) $m[1];
	}

	/**
	 * Ce que contient un dossier : chaque entrée avec son nom, sa nature et
	 * sa taille. Null s'il ne se lit pas ; vide s'il n'existe pas.
	 */
	public static function lister( $relatif ) {
		list( $code, $reponse ) = self::requete( 'PROPFIND', untrailingslashit( (string) $relatif ) . '/', array(
			'headers' => array( 'Depth' => '1', 'Content-Type' => 'application/xml; charset=utf-8' ),
			'body'    => self::PROPRIETES,
		) );
		if ( 404 === $code ) {
			return array();
		}
		return 207 === $code ? self::entrees( wp_remote_retrieve_body( $reponse ) ) : null;
	}

	/**
	 * Les sous-dossiers d'un chemin pris depuis la racine du compte, et non
	 * du dossier choisi : c'est de quoi choisir ce dossier. Rend les noms,
	 * ou une raison si sDrive ne répond pas.
	 */
	public static function dossiers_du_compte( $chemin ) {
		$acces = self::acces();
		if ( null === $acces ) {
			return 'sDrive n’est pas relié.';
		}
		$acces['dossier'] = '';
		$reponse = wp_remote_request( self::adresse( $acces, (string) $chemin ), array(
			'method'  => 'PROPFIND',
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $acces['compte'] . ':' . $acces['mot_de_passe'] ),
				'Depth'         => '1',
				'Content-Type'  => 'application/xml; charset=utf-8',
			),
			'body'    => self::PROPRIETES,
		) );
		$code = is_wp_error( $reponse ) ? 0 : (int) wp_remote_retrieve_response_code( $reponse );
		if ( 207 !== $code ) {
			return 404 === $code ? 'Ce dossier n’existe pas sur sDrive.' : 'sDrive ne répond pas : ' . self::pourquoi( $code, $reponse ) . '.';
		}
		$noms = array();
		foreach ( self::entrees( wp_remote_retrieve_body( $reponse ) ) as $entree ) {
			if ( $entree['dossier'] ) {
				$noms[] = $entree['nom'];
			}
		}
		natcasesort( $noms );
		return array_values( $noms );
	}

	/**
	 * Les entrées d'une réponse PROPFIND, le dossier lu mis à part : nom,
	 * nature et taille de chacune.
	 */
	private static function entrees( $corps ) {
		$entrees = array();
		$blocs   = preg_split( '#<(?:[a-z0-9]+:)?response[\s>]#i', (string) $corps );
		array_shift( $blocs );
		// La première réponse est le dossier lui-même.
		array_shift( $blocs );
		foreach ( $blocs as $bloc ) {
			if ( ! preg_match( '#<(?:[a-z0-9]+:)?href>([^<]+)</#i', $bloc, $h ) ) {
				continue;
			}
			$nom = rawurldecode( basename( untrailingslashit( html_entity_decode( $h[1], ENT_QUOTES | ENT_XML1, 'UTF-8' ) ) ) );
			$taille = preg_match( '#<(?:[a-z0-9]+:)?getcontentlength>\s*(\d+)\s*</#i', $bloc, $t ) ? (int) $t[1] : 0;
			$entrees[] = array(
				'nom'     => $nom,
				'dossier' => (bool) preg_match( '#<(?:[a-z0-9]+:)?collection\s*/?>#i', $bloc ),
				'taille'  => $taille,
			);
		}
		return $entrees;
	}

	/**
	 * Rapatrie un fichier de sDrive dans un fichier du serveur, sans le
	 * tenir en mémoire. Rend une raison, vide si tout va bien.
	 */
	public static function telecharger( $relatif, $local ) {
		list( $code, $reponse ) = self::requete( 'GET', $relatif, array(
			'timeout'  => 90,
			'stream'   => true,
			'filename' => $local,
		) );
		if ( 200 !== $code ) {
			@unlink( $local );
			return 'lecture refusée (' . self::pourquoi( $code, $reponse ) . ')';
		}
		clearstatcache( true, $local );
		$annonce = (int) wp_remote_retrieve_header( $reponse, 'content-length' );
		if ( ! is_file( $local ) || ( $annonce > 0 && (int) filesize( $local ) !== $annonce ) ) {
			@unlink( $local );
			return 'lecture incomplète';
		}
		return '';
	}

	/**
	 * Efface chez sDrive : le fichier part dans la corbeille du compte, d'où
	 * il se restaure trente jours. Un fichier absent n'est pas un refus.
	 */
	public static function effacer( $relatif ) {
		list( $code, $reponse ) = self::requete( 'DELETE', $relatif );
		return in_array( $code, array( 204, 404 ), true ) ? '' : 'effacement refusé (' . self::pourquoi( $code, $reponse ) . ')';
	}

	/** Révoque chez sDrive le mot de passe de la liaison ; faux si rien n'est confirmé. */
	private static function revoquer() {
		$lien = self::liaison();
		$mot  = self::desceller( isset( $lien['scelle'] ) ? $lien['scelle'] : '' );
		if ( empty( $lien['compte'] ) || '' === $mot ) {
			return false;
		}
		$reponse = wp_remote_request( $lien['serveur'] . '/ocs/v2.php/core/apppassword', array(
			'method'  => 'DELETE',
			'timeout' => 15,
			'headers' => array(
				'Authorization'  => 'Basic ' . base64_encode( $lien['compte'] . ':' . $mot ),
				'OCS-APIRequest' => 'true',
			),
		) );
		return ! is_wp_error( $reponse ) && 200 === (int) wp_remote_retrieve_response_code( $reponse );
	}

	/**
	 * Scelle un secret avec les clés de wp-config.php. WordPress porte
	 * sodium depuis la 5.2, en extension ou en PHP pur : 24 octets de nonce,
	 * une clé de 32.
	 */
	private static function sceller( $clair ) {
		$nonce = random_bytes( 24 );
		return base64_encode( $nonce . sodium_crypto_secretbox( $clair, $nonce, self::cle() ) );
	}

	/** Le secret scellé, en clair ; vide s'il ne se descelle pas. */
	private static function desceller( $scelle ) {
		$brut = base64_decode( (string) $scelle, true );
		if ( false === $brut || strlen( $brut ) <= 24 ) {
			return '';
		}
		$clair = sodium_crypto_secretbox_open( substr( $brut, 24 ), substr( $brut, 0, 24 ), self::cle() );
		return false === $clair ? '' : $clair;
	}

	/** La clé du sceau, tirée des clés d'authentification du site. */
	private static function cle() {
		return sodium_crypto_generichash( wp_salt( 'auth' ) . '|' . self::OPTION, '', 32 );
	}

	/**
	 * Une adresse rendue par sDrive mène-t-elle bien chez sDrive ? Le site
	 * y enverra un jeton, puis le mot de passe : jamais ailleurs.
	 */
	private static function chez_sdrive( $url ) {
		return 'https' === wp_parse_url( (string) $url, PHP_URL_SCHEME )
			&& wp_parse_url( self::ADRESSE, PHP_URL_HOST ) === wp_parse_url( (string) $url, PHP_URL_HOST );
	}

	/** Le nom sous lequel le site paraît dans sDrive, parmi les appareils. */
	private static function nom_du_site() {
		return 'Notice Archeomed (' . wp_parse_url( home_url(), PHP_URL_HOST ) . ')';
	}

	/** La demande de liaison se garde par administrateur : deux ne se mêlent pas. */
	private static function cle_de_demande() {
		return 'na_sdrive_demande_' . get_current_user_id();
	}

	/** Le message d'une sortie refusée, proxy nommé quand c'est lui. */
	public static function injoignable( $erreur ) {
		$message = $erreur->get_error_message();
		$proxy   = false !== stripos( $message, 'proxy' ) || false !== stripos( $message, 'error 56' );
		return array(
			'ok'      => false,
			'message' => 'sDrive n’est pas joignable depuis ce serveur — ' . $message . '.'
				. ( $proxy ? ' C’est le proxy de l’hébergement qui refuse la sortie, comme pour Cloudflare : il faut demander l’ouverture de sdrive.cnrs.fr.' : '' ),
		);
	}
}
