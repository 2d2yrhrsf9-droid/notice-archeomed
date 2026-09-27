<?php
/**
 * Les mises à jour, proposées depuis un dépôt GitHub.
 *
 * Le plugin ne vit pas dans le répertoire de WordPress : sans cela, mettre à
 * jour veut dire fabriquer une archive, ouvrir l'administration, téléverser,
 * confirmer le remplacement. Cinq gestes à refaire à chaque correction, et
 * autant d'occasions d'installer la mauvaise archive.
 *
 * WordPress sait pourtant interroger n'importe quelle source, pourvu qu'on
 * lui réponde dans sa langue : un objet portant la version disponible et
 * l'adresse du paquet, glissé dans le transient des mises à jour. C'est tout
 * ce que fait cette classe — une centaine de lignes, aucune bibliothèque, et
 * l'entrée paraît dans « Extensions » comme pour n'importe quelle autre.
 *
 * Deux choses à savoir, qui ne se devinent pas.
 *
 * **WordPress compare des numéros de version, pas des commits.** Une
 * correction poussée sans monter le « Version: » de l'en-tête reste
 * invisible, et c'est heureux : on ne veut pas qu'un travail en cours
 * s'annonce comme une mise à jour sur le site de la revue.
 *
 * **L'archive de la release doit avoir la bonne racine.** Celle que GitHub
 * fabrique tout seul s'ouvre sur « depot-3.16/ » là où WordPress attend
 * « notice-archeomed/ » : le plugin s'installerait à côté de lui-même
 * au lieu de se mettre à jour. Il faut joindre à la release l'archive
 * produite par « ./empaqueter ». La classe cherche donc un fichier joint
 * avant de se rabattre sur l'archive automatique, et le dit quand elle n'en
 * trouve pas.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_MiseAJour {

	/** Le nom que doit porter l'archive jointe à la release. */
	const ARCHIVE = 'notice-archeomed.zip';

	/**
	 * Six heures entre deux interrogations.
	 *
	 * WordPress vérifie les mises à jour souvent, et à chaque affichage de
	 * certaines pages : sans cette réserve, le site appellerait GitHub des
	 * dizaines de fois par jour pour une réponse qui ne change pas.
	 */
	const DUREE_RESERVE = 21600;

	private $fichier;   // « notice-archeomed/notice-archeomed.php »
	private $dossier;   // « notice-archeomed »
	private $chemin;    // le chemin absolu, pour lire l'en-tête
	private $version = '';

	public function __construct( $fichier_principal ) {
		$this->chemin  = $fichier_principal;
		$this->fichier = plugin_basename( $fichier_principal );
		$this->dossier = dirname( $this->fichier );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'annoncer' ) );
		add_filter( 'plugins_api', array( $this, 'details' ), 10, 3 );
		add_filter( 'http_request_args', array( $this, 'porter_le_jeton' ), 10, 2 );
		// Une correction poussée doit pouvoir se voir sans attendre six
		// heures : vider la réserve à l'enregistrement des réglages suffit.
		add_action( 'update_option_' . Notice_Archeomed_Settings::OPTION_NAME,
			array( $this, 'oublier' ) );
	}

	public function oublier() {
		delete_transient( 'na_maj_release' );
	}

	/**
	 * La version installée, lue dans l'en-tête et non écrite en double.
	 *
	 * Lue au moment d'en avoir besoin : « get_plugin_data » réclame les
	 * fonctions d'administration, qu'il n'y a aucune raison de charger sur
	 * une page de la revue.
	 */
	public function version() {
		if ( '' !== $this->version ) {
			return $this->version;
		}
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$entete        = get_plugin_data( $this->chemin, false, false );
		$this->version = isset( $entete['Version'] ) ? $entete['Version'] : '0';
		return $this->version;
	}

	/** Le dépôt réglé, sous la forme « compte/depot », ou une chaîne vide. */
	private function depot() {
		$brut = trim( (string) Notice_Archeomed_Settings::get( 'github_depot' ) );
		return preg_match( '#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $brut ) ? $brut : '';
	}

	private function actif() {
		return (int) Notice_Archeomed_Settings::get( 'maj_github' ) && '' !== $this->depot();
	}

	/**
	 * Ajoute le jeton aux requêtes vers GitHub, et à elles seules.
	 *
	 * WordPress télécharge l'archive par ses propres moyens : sans ce filtre,
	 * un dépôt privé répondrait 404 au moment de la prendre, après avoir
	 * annoncé la mise à jour. Le jeton ne part que vers les hôtes de GitHub.
	 */
	public function porter_le_jeton( $args, $url ) {
		$jeton = trim( (string) Notice_Archeomed_Settings::get( 'github_jeton' ) );
		if ( '' === $jeton || ! $this->actif() ) {
			return $args;
		}
		$hote = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! in_array( $hote, array( 'api.github.com', 'objects.githubusercontent.com' ), true ) ) {
			return $args;
		}
		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = array();
		}
		$args['headers']['Authorization'] = 'Bearer ' . $jeton;
		return $args;
	}

	/**
	 * La dernière release publiée, ou un tableau vide.
	 */
	public function derniere_release() {
		if ( ! $this->actif() ) {
			return array();
		}
		$connue = get_transient( 'na_maj_release' );
		if ( is_array( $connue ) ) {
			return $connue;
		}
		$reponse = wp_remote_get(
			'https://api.github.com/repos/' . $this->depot() . '/releases/latest',
			array(
				'timeout'    => 12,
				'user-agent' => 'notice-archeomed',
				'headers'    => array( 'Accept' => 'application/vnd.github+json' ),
			)
		);
		if ( is_wp_error( $reponse ) || 200 !== (int) wp_remote_retrieve_response_code( $reponse ) ) {
			// Une panne de réseau ne se met pas en réserve pour six heures :
			// on retente au prochain passage, mais pas tout de suite.
			set_transient( 'na_maj_release', array(), 900 );
			return array();
		}
		$corps = json_decode( wp_remote_retrieve_body( $reponse ), true );
		if ( ! is_array( $corps ) || empty( $corps['tag_name'] ) ) {
			set_transient( 'na_maj_release', array(), 900 );
			return array();
		}

		// « v3.16 » comme « 3.16 » : c'est le nombre qui compte.
		$version = ltrim( (string) $corps['tag_name'], 'vV' );
		$paquet  = '';
		$propre  = false;
		foreach ( (array) ( isset( $corps['assets'] ) ? $corps['assets'] : array() ) as $joint ) {
			if ( isset( $joint['name'] ) && self::ARCHIVE === $joint['name']
				&& ! empty( $joint['browser_download_url'] ) ) {
				$paquet = (string) $joint['browser_download_url'];
				$propre = true;
				break;
			}
		}
		if ( '' === $paquet && ! empty( $corps['zipball_url'] ) ) {
			$paquet = (string) $corps['zipball_url'];
		}

		$release = array(
			'version' => $version,
			'paquet'  => $paquet,
			'propre'  => $propre,
			'notes'   => isset( $corps['body'] ) ? (string) $corps['body'] : '',
			'date'    => isset( $corps['published_at'] ) ? (string) $corps['published_at'] : '',
			'adresse' => isset( $corps['html_url'] ) ? (string) $corps['html_url'] : '',
		);
		set_transient( 'na_maj_release', $release, self::DUREE_RESERVE );
		return $release;
	}

	/**
	 * Glisse la mise à jour dans ce que WordPress s'apprête à retenir.
	 */
	public function annoncer( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		$release = $this->derniere_release();
		if ( empty( $release['version'] ) || empty( $release['paquet'] ) ) {
			return $transient;
		}
		// L'archive automatique de GitHub s'ouvre sur le mauvais dossier :
		// l'annoncer installerait le plugin à côté de lui-même. On se tait
		// plutôt que de casser une installation qui marche.
		if ( empty( $release['propre'] ) ) {
			return $transient;
		}
		if ( ! version_compare( $release['version'], $this->version(), '>' ) ) {
			return $transient;
		}
		$objet = (object) array(
			'slug'        => $this->dossier,
			'plugin'      => $this->fichier,
			'new_version' => $release['version'],
			'package'     => $release['paquet'],
			'url'         => $release['adresse'],
			'tested'      => get_bloginfo( 'version' ),
		);
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		$transient->response[ $this->fichier ] = $objet;
		return $transient;
	}

	/**
	 * Ce que montre « Afficher les détails » avant d'installer.
	 */
	public function details( $resultat, $action, $args ) {
		if ( 'plugin_information' !== $action
			|| ! isset( $args->slug ) || $args->slug !== $this->dossier ) {
			return $resultat;
		}
		$release = $this->derniere_release();
		if ( empty( $release['version'] ) ) {
			return $resultat;
		}
		return (object) array(
			'name'          => 'Formulaire des notices d’archéologie médiévale',
			'slug'          => $this->dossier,
			'version'       => $release['version'],
			'last_updated'  => $release['date'],
			'download_link' => $release['paquet'],
			'sections'      => array(
				'changelog' => '' !== $release['notes']
					? wpautop( esc_html( $release['notes'] ) )
					: '<p>Aucune note publiée avec cette version.</p>',
			),
		);
	}
}
