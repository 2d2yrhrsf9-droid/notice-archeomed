<?php
/**
 * Page de réglages du Formulaire des notices d’archéologie médiévale.
 *
 * Permet de saisir depuis l'administration les valeurs qui figuraient
 * auparavant en dur dans le code : clés Turnstile et adresse de la rédaction.
 *
 * Ordre de priorité pour chaque valeur :
 *   1. la constante définie dans wp-config.php, si elle existe ;
 *   2. la valeur enregistrée dans cette page de réglages ;
 *   3. la valeur par défaut inscrite dans le code.
 *
 * Ainsi, un site qui déclare NA_TURNSTILE_SECRET dans wp-config.php garde ce
 * fonctionnement, et les autres passent par l'interface sans toucher un fichier.
 *
 * @package Notice_Archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Settings {

	const OPTION_GROUP = 'notice_archeomed_settings';
	const OPTION_NAME  = 'notice_archeomed_options';
	const PAGE_SLUG    = 'notice-archeomed';
	// Assez pour une rédaction ; assez peu pour qu'une liste devenue illisible
	// se remarque avant d'être un annuaire.
	const MAX_DESTINATAIRES = 10;

	/**
	 * Valeurs par défaut, reprises de la version précédente du plugin.
	 */
	private static $defaults = array(
		// Conservé pour les installations d'avant la liste : il n'est plus
		// écrit, seulement relu. Voir destinataires().
		'dest_email'         => '',
		'turnstile_site'     => '0x4AAAAAADnqPBVc6ZpaeiEi',
		'turnstile_secret'   => '',
		// « differe » ou « immediat ». Le différé rend la main à l'auteur en
		// une fraction de seconde et laisse les courriels partir après ; c'est
		// ce qui permet à plusieurs personnes de déposer en même temps.
		'mode_envoi'         => 'differe',
		// « locale », « turnstile » ou « les_deux ». Locale par défaut : elle
		// ne dépend d'aucun service tiers, et un serveur qui ne peut pas
		// joindre Cloudflare — hébergement institutionnel derrière un proxy
		// filtrant — n'a alors rien à régler pour que le formulaire protège.
		'protection'         => 'locale',

		// — L'iconographie d'un numéro —
		// Le numéro en préparation ouvre le nom de chaque illustration, comme
		// la mise en page le fait : « AM55_2_Etiolles_2024_Fig_1.jpg ».
		'numero'             => '',
		'nom_modele'         => '{numero}_{rubrique}_{commune}_{lieu_dit}_{annee}_Fig_{n}',
		// La basse définition sert au lien posé dans le Word : elle doit se
		// voir à l'écran, pas s'imprimer. Le poids compte autant que la
		// largeur — cent figures liées dans un fascicule, et le dossier
		// devient intransportable.
		'br_largeur'         => 1000,
		'br_dpi'             => 96,
		'br_poids'           => 1024,   // en kilo-octets
		'br_tolerance'       => 15,     // en pour-cent, sous le plafond
		// La haute définition part à la mise en page, et elle ne se règle pas
		// d'une seule main : une photographie se satisfait de 300 dpi, un
		// dessin au trait en réclame 1200, et le convertir en JPEG lui ôte
		// justement ce qu'on lui demande. Deux familles, deux réglages.
		'hr_photo_jpeg'      => 1,
		'hr_photo_dpi'       => 300,
		'hr_photo_qualite'   => 92,
		'hr_trait_jpeg'      => 0,
		'hr_trait_dpi'       => 1200,
		'hr_trait_qualite'   => 95,
		// Les originaux, gardés à côté : rien de ce que l'auteur a envoyé ne
		// doit disparaître dans une conversion.
		'garder_originaux'   => 1,

		// — Les mises à jour —
		// Rien n'est renseigné par défaut : le dépôt se nomme à l'installation,
		// et tant qu'il ne l'est pas le mécanisme reste muet.
		// — L'acheminement du courriel —
		// « php » s'en remet à la fonction mail() du serveur ; « smtp » passe
		// par un relais. Sur un hébergement où mail() n'est pas configurée,
		// PHPMailer répond « Impossible d'instancier la fonction mail » et
		// aucune notice ne part jamais.
		'envoi_mode'         => 'php',
		'smtp_hote'          => '',
		'smtp_port'          => 587,
		'smtp_chiffrement'   => 'tls',
		'smtp_utilisateur'   => '',
		'smtp_motdepasse'    => '',
		'smtp_expediteur'    => '',
		'smtp_nom'           => '',
		// Le poids qu'un courriel peut atteindre, pièces jointes encodées
		// comprises, en méga-octets. Dix : c'est la limite de Postfix par
		// défaut, et celle qu'a montrée l'hébergement de la revue — une image
		// de 4 Mo passait, deux étaient refusées.
		'poids_courriel'     => 10,

		'maj_github'         => 0,
		'github_depot'       => '',
		'github_jeton'       => '',
	);

	/** Les jetons admis dans le modèle de nom, et ce qu'ils valent. */
	const JETONS_DE_NOM = array(
		'{numero}'   => 'le numéro en préparation — « AM55 »',
		'{rubrique}' => 'le rang de la rubrique — « 2 » pour la deuxième',
		'{commune}'  => 'la commune de la notice',
		'{lieu_dit}' => 'le lieu-dit — il distingue deux notices d’une même commune',
		'{annee}'    => 'l’année de l’opération',
		'{n}'        => 'le rang de la figure dans la notice',
	);

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Renvoie une option, en donnant la priorité à la constante wp-config.php.
	 */
	public static function get( $key ) {
		$constants = array(
			'turnstile_secret' => 'NA_TURNSTILE_SECRET',
			'turnstile_site'   => 'NA_TURNSTILE_SITE',
			'dest_email'       => 'NA_DEST_EMAIL',
			'mode_envoi'       => 'NA_MODE_ENVOI',
			'protection'       => 'NA_PROTECTION',
		);
		if ( isset( $constants[ $key ] ) && defined( $constants[ $key ] ) ) {
			$value = constant( $constants[ $key ] );
			if ( '' !== trim( (string) $value ) ) {
				return trim( (string) $value );
			}
		}
		$options = get_option( self::OPTION_NAME, array() );
		if ( isset( $options[ $key ] ) && '' !== trim( (string) $options[ $key ] ) ) {
			return trim( (string) $options[ $key ] );
		}
		return isset( self::$defaults[ $key ] ) ? self::$defaults[ $key ] : '';
	}

	/**
	 * Les destinataires, chacun avec ce qu'il reçoit.
	 *
	 * Une rédaction n'est pas une personne : le secrétariat veut chaque notice
	 * à mesure qu'elle arrive, la direction veut le récapitulatif du jour et
	 * pas quarante courriels. Un destinataire unique obligeait à faire suivre
	 * à la main, ou à tout envoyer à tout le monde.
	 *
	 * Rend une liste de tableaux { email, notices, recap }. Vide si rien n'est
	 * réglé : c'est alors qu'aucun courriel ne part, et le plugin le dit en
	 * rouge dans l'administration.
	 */
	public static function destinataires() {
		// La constante prime, comme pour les autres réglages. Elle accepte
		// plusieurs adresses séparées par des virgules ; elles reçoivent tout,
		// faute d'endroit où dire qui reçoit quoi.
		if ( defined( 'NA_DEST_EMAIL' ) && '' !== trim( (string) constant( 'NA_DEST_EMAIL' ) ) ) {
			$liste = array();
			foreach ( explode( ',', (string) constant( 'NA_DEST_EMAIL' ) ) as $brut ) {
				$mail = sanitize_email( trim( $brut ) );
				if ( is_email( $mail ) ) {
					$liste[] = array( 'email' => $mail, 'notices' => true, 'recap' => true );
				}
			}
			if ( ! empty( $liste ) ) {
				return $liste;
			}
		}

		$options = get_option( self::OPTION_NAME, array() );
		$brut    = ( isset( $options['destinataires'] ) && is_array( $options['destinataires'] ) )
			? $options['destinataires'] : array();
		$liste = array();
		foreach ( $brut as $item ) {
			if ( ! is_array( $item ) || empty( $item['email'] ) ) {
				continue;
			}
			$mail = sanitize_email( trim( (string) $item['email'] ) );
			if ( ! is_email( $mail ) ) {
				continue;
			}
			$liste[] = array(
				'email'   => $mail,
				'notices' => ! empty( $item['notices'] ),
				'recap'   => ! empty( $item['recap'] ),
			);
			if ( count( $liste ) >= self::MAX_DESTINATAIRES ) {
				break;
			}
		}
		if ( ! empty( $liste ) ) {
			return $liste;
		}

		// Reprise silencieuse de l'ancien réglage : une adresse unique, qui
		// recevait les notices comme les récapitulatifs. Une mise à jour ne
		// doit pas interrompre les envois le temps qu'on rouvre la page.
		$ancien = isset( $options['dest_email'] )
			? sanitize_email( trim( (string) $options['dest_email'] ) ) : '';
		if ( is_email( $ancien ) ) {
			return array( array( 'email' => $ancien, 'notices' => true, 'recap' => true ) );
		}
		return array();
	}

	/** Les adresses d'une liste déjà lue qui reçoivent telle sorte de courriel. */
	private static function destinataires_de_la_liste( $liste, $sorte ) {
		$out = array();
		foreach ( (array) $liste as $qui ) {
			if ( ! empty( $qui[ $sorte ] ) ) {
				$out[] = $qui['email'];
			}
		}
		return $out;
	}

	/** Les adresses qui reçoivent « notices » ou « recap ». */
	public static function destinataires_de( $sorte ) {
		$out = array();
		foreach ( self::destinataires() as $qui ) {
			if ( ! empty( $qui[ $sorte ] ) ) {
				$out[] = $qui['email'];
			}
		}
		return $out;
	}

	/** Vrai quand la liste est imposée par la constante wp-config.php. */
	public static function destinataires_verrouilles() {
		return defined( 'NA_DEST_EMAIL' )
			&& '' !== trim( (string) constant( 'NA_DEST_EMAIL' ) );
	}

	/**
	 * Indique si la valeur provient d'une constante, auquel cas le champ du
	 * formulaire est affiché en lecture seule pour éviter toute confusion.
	 */
	public static function is_locked( $key ) {
		$constants = array(
			'turnstile_secret' => 'NA_TURNSTILE_SECRET',
			'turnstile_site'   => 'NA_TURNSTILE_SITE',
			'dest_email'       => 'NA_DEST_EMAIL',
			'mode_envoi'       => 'NA_MODE_ENVOI',
			'protection'       => 'NA_PROTECTION',
		);
		return isset( $constants[ $key ] )
			&& defined( $constants[ $key ] )
			&& '' !== trim( (string) constant( $constants[ $key ] ) );
	}

	/**
	 * L'adresse de la page de réglages, en un seul endroit.
	 *
	 * Elle était écrite en toutes lettres à quatre endroits, dont trois
	 * bandeaux d'alerte. Déplacer le menu les aurait tous menés à une page
	 * inexistante, et rien ne l'aurait dit avant qu'on clique.
	 */
	public static function url() {
		return admin_url( 'edit.php?post_type=' . Notice_Archeomed_File::CPT
			. '&page=' . self::PAGE_SLUG );
	}

	/**
	 * Les réglages entrent dans le menu du plugin, et non sous « Réglages ».
	 *
	 * Ils vivaient sous les réglages généraux de WordPress : pour y aller il
	 * fallait passer par la liste des extensions, ou se souvenir d'une entrée
	 * noyée parmi vingt autres. Le plugin a déjà son menu dans la barre — la
	 * liste des notices reçues —, et c'est là qu'on les cherche.
	 */
	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=' . Notice_Archeomed_File::CPT,
			'Formulaire des notices d’archéologie médiévale',
			'Réglages',
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Nettoyage des valeurs soumises. Un champ « clé secrète » laissé vide ne
	 * doit pas effacer la clé déjà enregistrée : c'est le comportement attendu
	 * quand l'utilisateur enregistre la page sans retoucher ce champ.
	 */
	public function sanitize( $input ) {
		$current = get_option( self::OPTION_NAME, array() );
		$out     = is_array( $current ) ? $current : array();

		if ( isset( $input['destinataires'] ) && is_array( $input['destinataires'] ) ) {
			$liste   = array();
			$refuses = array();
			$vus     = array();
			foreach ( $input['destinataires'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$saisi = isset( $item['email'] ) ? trim( (string) $item['email'] ) : '';
				if ( '' === $saisi ) {
					continue;   // une ligne vidée est une ligne supprimée
				}
				$email = sanitize_email( $saisi );
				if ( ! is_email( $email ) ) {
					$refuses[] = $saisi;
					continue;
				}
				// La même adresse deux fois enverrait le même courriel deux
				// fois : on garde la première ligne, qui porte ses cases.
				$clef = strtolower( $email );
				if ( isset( $vus[ $clef ] ) ) {
					continue;
				}
				$vus[ $clef ] = true;
				$liste[] = array(
					'email'   => $email,
					'notices' => ! empty( $item['notices'] ) ? 1 : 0,
					'recap'   => ! empty( $item['recap'] ) ? 1 : 0,
				);
				if ( count( $liste ) >= self::MAX_DESTINATAIRES ) {
					break;
				}
			}
			$out['destinataires'] = $liste;
			// L'ancien champ unique a servi de reprise ; la liste enregistrée
			// le remplace, et le garder ferait deux vérités.
			$out['dest_email'] = '';

			if ( ! empty( $refuses ) ) {
				add_settings_error(
					self::OPTION_NAME,
					'destinataires',
					sprintf(
						/* translators: %s : les adresses refusées, séparées par des virgules. */
						'Adresse non valide, écartée de la liste : %s. Les autres ont bien été enregistrées.',
						esc_html( implode( ', ', $refuses ) )
					),
					'error'
				);
			}
			if ( empty( $liste ) ) {
				add_settings_error(
					self::OPTION_NAME,
					'destinataires_vide',
					'Aucun destinataire n\'est enregistré : les notices déposées seront conservées, mais aucune ne partira.',
					'warning'
				);
			} elseif ( empty( self::destinataires_de_la_liste( $liste, 'notices' ) ) ) {
				add_settings_error(
					self::OPTION_NAME,
					'destinataires_sans_notices',
					'Personne ne reçoit les notices : elles seront conservées sans être expédiées. Cochez « Notices » pour au moins un destinataire.',
					'warning'
				);
			}
		}

		if ( isset( $input['turnstile_site'] ) ) {
			$out['turnstile_site'] = sanitize_text_field( $input['turnstile_site'] );
		}

		// La clé secrète n'est remplacée que si un nouveau contenu est fourni.
		if ( isset( $input['turnstile_secret'] ) && '' !== trim( $input['turnstile_secret'] ) ) {
			$out['turnstile_secret'] = sanitize_text_field( $input['turnstile_secret'] );
		}

		// Case à cocher explicite pour effacer la clé enregistrée.
		if ( ! empty( $input['clear_secret'] ) ) {
			$out['turnstile_secret'] = '';
		}

		if ( isset( $input['mode_envoi'] ) ) {
			$out['mode_envoi'] = 'immediat' === $input['mode_envoi'] ? 'immediat' : 'differe';
		}

		if ( isset( $input['protection'] ) ) {
			$out['protection'] = in_array(
				$input['protection'], array( 'locale', 'turnstile', 'les_deux' ), true )
				? $input['protection'] : 'locale';
		}

		// — L'acheminement —
		if ( isset( $input['envoi_mode'] ) ) {
			$out['envoi_mode'] = ( 'smtp' === $input['envoi_mode'] ) ? 'smtp' : 'php';
		}
		if ( isset( $input['smtp_hote'] ) ) {
			$out['smtp_hote'] = trim( sanitize_text_field( $input['smtp_hote'] ) );
		}
		if ( isset( $input['poids_courriel'] ) ) {
			$out['poids_courriel'] = max( 1, min( 100, (int) $input['poids_courriel'] ) );
		}
		if ( isset( $input['smtp_port'] ) ) {
			$out['smtp_port'] = max( 1, min( 65535, (int) $input['smtp_port'] ) );
		}
		if ( isset( $input['smtp_chiffrement'] ) ) {
			$out['smtp_chiffrement'] = in_array( $input['smtp_chiffrement'],
				array( 'aucun', 'tls', 'ssl' ), true ) ? $input['smtp_chiffrement'] : 'tls';
		}
		if ( isset( $input['smtp_utilisateur'] ) ) {
			$out['smtp_utilisateur'] = trim( sanitize_text_field( $input['smtp_utilisateur'] ) );
		}
		// Comme la clé Turnstile : un champ laissé vide ne doit pas effacer
		// ce qui est enregistré, le mot de passe ne s'affichant jamais.
		if ( isset( $input['smtp_motdepasse'] ) && '' !== trim( $input['smtp_motdepasse'] ) ) {
			$out['smtp_motdepasse'] = (string) $input['smtp_motdepasse'];
		}
		if ( ! empty( $input['effacer_motdepasse'] ) ) {
			$out['smtp_motdepasse'] = '';
		}
		if ( isset( $input['smtp_expediteur'] ) ) {
			$adresse = sanitize_email( $input['smtp_expediteur'] );
			if ( '' === trim( $input['smtp_expediteur'] ) || is_email( $adresse ) ) {
				$out['smtp_expediteur'] = is_email( $adresse ) ? $adresse : '';
			} else {
				add_settings_error( self::OPTION_NAME, 'smtp_expediteur',
					'L’adresse d’expédition n’est pas valide. L’ancienne a été conservée.', 'error' );
			}
		}
		if ( isset( $input['smtp_nom'] ) ) {
			$out['smtp_nom'] = trim( sanitize_text_field( $input['smtp_nom'] ) );
		}

		// — Les mises à jour —
		if ( isset( $input['maj_presente'] ) ) {
			$out['maj_github'] = empty( $input['maj_github'] ) ? 0 : 1;
		}
		if ( isset( $input['github_depot'] ) ) {
			$depot = trim( sanitize_text_field( $input['github_depot'] ) );
			if ( 'compte/depot' === strtolower( $depot ) ) {
				// L'exemple du champ, recopié tel quel : il a la bonne forme,
				// passait donc la vérification, et GitHub répondait 404 sans
				// qu'on voie pourquoi.
				add_settings_error(
					self::OPTION_NAME, 'github_depot',
					'« compte/depot » est l’exemple du champ, non un dépôt : indiquez le vrai nom, par exemple « 2d2yrhrsf9-droid/notice-archeomed ». L’ancienne valeur a été conservée.',
					'error'
				);
			} elseif ( '' === $depot || preg_match( '#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $depot ) ) {
				$out['github_depot'] = $depot;
			} else {
				add_settings_error(
					self::OPTION_NAME, 'github_depot',
					'Le dépôt s’écrit « compte/depot », sans adresse complète ni barre finale. L’ancienne valeur a été conservée.',
					'error'
				);
			}
		}
		// Le jeton ne se remplace que si l'on en fournit un : enregistrer la
		// page sans y toucher ne doit pas l'effacer, le champ étant vide à
		// l'affichage comme tout secret.
		if ( isset( $input['github_jeton'] ) && '' !== trim( $input['github_jeton'] ) ) {
			$out['github_jeton'] = sanitize_text_field( $input['github_jeton'] );
		}
		if ( ! empty( $input['effacer_jeton'] ) ) {
			$out['github_jeton'] = '';
		}

		// — L'iconographie —
		if ( isset( $input['numero'] ) ) {
			// Le numéro ouvre des noms de fichiers : il suit la même règle
			// qu'eux, sans quoi il ferait entrer par la porte ce que le
			// nettoyage chasse par la fenêtre.
			$out['numero'] = Notice_Archeomed_Nommage::assainir( $input['numero'] );
		}
		if ( isset( $input['nom_modele'] ) ) {
			$modele = trim( sanitize_text_field( $input['nom_modele'] ) );
			// Un modèle sans « {n} » donnerait le même nom à trois
			// illustrations, dont deux s'écraseraient sans bruit.
			if ( '' === $modele || false === strpos( $modele, '{n}' ) ) {
				add_settings_error(
					self::OPTION_NAME, 'nom_modele',
					'Le modèle de nom doit contenir « {n} », le rang de la figure : sans lui, deux illustrations d’une même notice porteraient le même nom. L’ancien modèle a été conservé.',
					'error'
				);
			} else {
				$out['nom_modele'] = $modele;
				// Deux notices d'une même commune et d'une même année, cela
				// se voit dans chaque fascicule. Sans « {lieu_dit} » leurs
				// figures portent le même nom, et l'assemblage doit alors
				// numéroter d'office — ce qui marche, mais donne des noms
				// qu'on ne reconnaît plus.
				if ( false === strpos( $modele, '{lieu_dit}' ) ) {
					add_settings_error(
						self::OPTION_NAME, 'nom_modele_lieu',
						'Le modèle ne contient pas « {lieu_dit} » : deux notices d’une même commune et d’une même année donneraient des noms identiques. L’assemblage les distinguera par un suffixe, mais le lieu-dit se lit mieux.',
						'warning'
					);
				}
			}
		}
		// Des bornes, et non une confiance : un « 0 » en largeur donnerait une
		// image vide, un « 20000 » épuiserait la mémoire du serveur.
		foreach ( array(
			'br_largeur'       => array( 200, 4000 ),
			'br_dpi'           => array( 72, 300 ),
			'br_poids'         => array( 50, 20480 ),
			'br_tolerance'     => array( 0, 50 ),
			'hr_photo_dpi'     => array( 150, 1200 ),
			'hr_photo_qualite' => array( 40, 100 ),
			'hr_trait_dpi'     => array( 150, 2400 ),
			'hr_trait_qualite' => array( 40, 100 ),
		) as $clef => $bornes ) {
			if ( ! isset( $input[ $clef ] ) ) {
				continue;
			}
			$valeur = (int) $input[ $clef ];
			$out[ $clef ] = max( $bornes[0], min( $bornes[1], $valeur ) );
		}
		// Les cases à cocher ne s'envoient pas quand elles sont vides : leur
		// absence est la réponse « non », pourvu que le formulaire les ait
		// bien présentées — d'où le témoin caché.
		if ( isset( $input['icono_presente'] ) ) {
			$out['hr_photo_jpeg']    = empty( $input['hr_photo_jpeg'] ) ? 0 : 1;
			$out['hr_trait_jpeg']    = empty( $input['hr_trait_jpeg'] ) ? 0 : 1;
			$out['garder_originaux'] = empty( $input['garder_originaux'] ) ? 0 : 1;
		}

		return $out;
	}

	/**
	 * Le serveur joint-il GitHub ?
	 *
	 * La question n'est pas oiseuse : c'est le même proxy qui empêche de
	 * joindre Cloudflare, et qui rend Turnstile inutilisable. S'il filtre
	 * aussi GitHub, un mécanisme de mise à jour par les releases resterait
	 * muet — le site ne saurait jamais qu'une version existe.
	 *
	 * Deux hôtes, car deux étapes : l'API dit quelle version existe,
	 * « objects.githubusercontent.com » sert le fichier. Un proxy peut
	 * laisser passer l'une et bloquer l'autre, et la mise à jour
	 * s'arrêterait au milieu.
	 *
	 * Ce qui compte n'est pas le code HTTP mais le fait d'en recevoir un :
	 * un 403 ou un 404 prouve qu'on a traversé, une erreur de transport
	 * prouve le contraire.
	 */
	private function test_github() {
		$hotes = array(
			'api.github.com'               => 'https://api.github.com/',
			'objects.githubusercontent.com' => 'https://objects.githubusercontent.com/',
		);
		$lignes  = array();
		$joints  = 0;
		foreach ( $hotes as $nom => $url ) {
			$reponse = wp_remote_get( $url, array(
				'timeout'   => 10,
				'sslverify' => true,
				// GitHub refuse les requêtes sans agent, et répond alors 403
				// pour une raison qui n'a rien à voir avec le proxy.
				'headers'   => array( 'Accept' => 'application/vnd.github+json' ),
				'user-agent' => 'notice-archeomed',
			) );
			if ( is_wp_error( $reponse ) ) {
				$message = $reponse->get_error_message();
				$lignes[] = $nom . ' : injoignable — ' . $message;
				// L'erreur 56 derrière un CONNECT est la signature du proxy
				// filtrant, celle que Cloudflare renvoie déjà.
				if ( false !== stripos( $message, 'proxy' ) || false !== stripos( $message, 'error 56' ) ) {
					$lignes[] = '    → c’est le proxy de l’hébergement qui refuse la sortie, comme pour Cloudflare.';
				}
				continue;
			}
			++$joints;
			$lignes[] = $nom . ' : joint (code HTTP ' . (int) wp_remote_retrieve_response_code( $reponse ) . ').';
		}
		$total = count( $hotes );
		if ( $joints === $total ) {
			return array(
				'ok'      => true,
				'message' => 'GitHub est joignable depuis ce serveur. ' . implode( ' ', $lignes )
					. ' Une mise à jour servie par les releases est donc possible.',
			);
		}
		if ( 0 === $joints ) {
			return array(
				'ok'      => false,
				'message' => 'GitHub n’est pas joignable. ' . implode( ' ', $lignes )
					. ' Les mises à jour devront passer par le téléversement de l’archive, comme aujourd’hui.',
			);
		}
		return array(
			'ok'      => false,
			'message' => 'GitHub n’est joignable qu’à moitié. ' . implode( ' ', $lignes )
				. ' Le site saurait qu’une version existe mais ne pourrait pas la télécharger : il faut les deux.',
		);
	}

	/**
	 * Teste la clé secrète auprès de Cloudflare. On envoie volontairement un
	 * jeton invalide : si la clé est bonne, l'API répond « invalid-input-response » ;
	 * si la clé est mauvaise, elle répond « invalid-input-secret ». C'est donc
	 * un moyen de valider la clé sans avoir à résoudre un défi.
	 */
	private function test_secret( $secret ) {
		if ( '' === trim( $secret ) ) {
			return array( 'ok' => false, 'message' => 'Aucune clé secrète enregistrée.' );
		}
		$response = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret,
					'response' => 'test-invalide-verification-cle',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => 'Impossible de joindre Cloudflare : ' . $response->get_error_message(),
			);
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return array( 'ok' => false, 'message' => 'Réponse inattendue de Cloudflare.' );
		}
		$codes = isset( $body['error-codes'] ) ? (array) $body['error-codes'] : array();
		if ( in_array( 'invalid-input-secret', $codes, true ) || in_array( 'missing-input-secret', $codes, true ) ) {
			return array( 'ok' => false, 'message' => 'Cloudflare refuse cette clé secrète. Vérifiez qu\'elle correspond bien au site déclaré.' );
		}
		// Tout autre code signifie que la clé a été acceptée et que seul le
		// jeton de test a été rejeté, ce qui est le résultat attendu.
		return array( 'ok' => true, 'message' => 'La clé secrète est reconnue par Cloudflare.' );
	}

	/**
	 * À quoi ressemblera un nom, avec les réglages en cours.
	 *
	 * Un modèle à jetons ne se lit pas : on le comprend en voyant ce qu'il
	 * produit. L'exemple se recalcule à chaque affichage de la page.
	 */
	public static function exemple_de_nom() {
		return Notice_Archeomed_Nommage::construire(
			self::get( 'nom_modele' ),
			array(
				'numero'   => self::get( 'numero' ),
				'rubrique' => 2,
				'commune'  => 'Aix-en-Provence',
				'lieu_dit' => 'Rue des Chartreux',
				'annee'    => '2024',
				'n'        => 1,
			),
			'jpg'
		);
	}

	/**
	 * Ce dont le plugin a besoin, et ce que l'hébergement lui donne.
	 *
	 * On bâtissait au pari : personne ne savait si Imagick était là, ni quelle
	 * taille de fichier le serveur laissait passer. Chaque ligne dit donc
	 * trois choses — ce qu'il faut, ce qu'il y a, et ce qui manque — plutôt
	 * qu'une valeur brute qu'il faudrait aller comparer ailleurs.
	 *
	 * Rien n'est modifié ici : on regarde, on ne touche pas.
	 */
	public static function etat_du_serveur() {
		$lignes = array();

		$ligne = function ( $quoi, $requis, $constate, $suffit, $pourquoi )
			use ( &$lignes ) {
			$lignes[] = array(
				'quoi'     => $quoi,
				'requis'   => $requis,
				'constate' => $constate,
				'etat'     => $suffit ? 'ok' : 'manque',
				// Pour une extension, l'écart n'est pas un nombre : elle est
				// là ou elle n'y est pas.
				'ecart'    => $suffit ? '' : 'à installer',
				'pourquoi' => $pourquoi,
			);
		};

		// — Les extensions —
		$zip = class_exists( 'ZipArchive' );
		$ligne( 'ZipArchive', 'présente', $zip ? 'présente' : 'absente', $zip,
			'Sans elle, la notice part en RTF au lieu du DOCX, et aucun paquet de numéro ne peut être assemblé.' );

		$magick = extension_loaded( 'imagick' ) && class_exists( 'Imagick' );
		$ligne( 'Imagick', 'présente', $magick ? 'présente' : 'absente', $magick,
			'Seule à lire le TIFF, donc seule à fabriquer les basses définitions à partir des aperçus intégrés aux EPS.' );

		$gd = extension_loaded( 'gd' );
		$ligne( 'GD', 'présente', $gd ? 'présente' : 'absente', $gd,
			'Repli d\'Imagick pour le JPEG et le PNG. Elle ne lit pas le TIFF.' );

		$finfo = function_exists( 'finfo_open' );
		$ligne( 'finfo', 'présente', $finfo ? 'présente' : 'absente', $finfo,
			'Reconnaît le type réel d\'un fichier déposé, quelle que soit son extension.' );

		// — Les formats qu'Imagick veut bien traiter —
		if ( $magick ) {
			foreach ( array(
				'TIFF' => 'Les aperçus intégrés aux EPS sont des TIFF : c\'est par eux que passent les basses définitions.',
				'JPEG' => 'Le format des illustrations livrées à la mise en page.',
				'PNG'  => 'Fréquent pour les dessins au trait.',
			) as $format => $pourquoi ) {
				$su = (array) Imagick::queryFormats( $format );
				$ligne( 'Imagick : ' . $format, 'accepté',
					! empty( $su ) ? 'accepté' : 'refusé', ! empty( $su ), $pourquoi );
			}
			// PDF et EPS passent par Ghostscript, que la politique
			// d'ImageMagick désactive d'origine depuis « ImageTragick ». On le
			// signale sans le réclamer : le plugin sait s'en passer.
			$ps = (array) Imagick::queryFormats( 'PDF' );
			$lignes[] = array(
				'quoi'     => 'Imagick : PDF et EPS',
				'requis'   => 'facultatif',
				'constate' => ! empty( $ps ) ? 'accepté' : 'refusé',
				'etat'     => 'note',
				'ecart'    => '',
				'pourquoi' => 'Passe par Ghostscript, que la politique d\'ImageMagick désactive presque partout. Le plugin s\'en passe : il tire la basse définition de l\'aperçu intégré à l\'EPS, et garde l\'original en haute définition.',
			);
		}

		// — Ce que PHP laisse passer —
		$octets = function ( $valeur ) {
			return (int) wp_convert_hr_to_bytes( (string) $valeur );
		};
		foreach ( array(
			'upload_max_filesize' => array( 25 * MB_IN_BYTES,
				'Taille d\'un fichier déposé. En deçà, les grandes illustrations sont refusées avant même que le plugin les voie.' ),
			'post_max_size'       => array( 25 * MB_IN_BYTES,
				'Taille de tout l\'envoi. Elle doit dépasser celle d\'un fichier seul, sinon le dépôt entier est rejeté.' ),
			'memory_limit'        => array( 256 * MB_IN_BYTES,
				'Redimensionner une grande image demande de la tenir en mémoire décompressée.' ),
		) as $clef => $attendu ) {
			$brut = ini_get( $clef );
			$val  = $octets( $brut );
			// « -1 » vaut sans limite, et c'est le cas de memory_limit sur
			// certains hébergements : ce n'est pas un manque.
			$suffit = ( -1 === (int) $brut ) || ( $val >= $attendu[0] );
			$lignes[] = array(
				'quoi'     => $clef,
				'requis'   => size_format( $attendu[0] ),
				'constate' => ( -1 === (int) $brut ) ? 'sans limite' : size_format( $val ),
				'etat'     => $suffit ? 'ok' : 'manque',
				'ecart'    => $suffit ? '' : size_format( $attendu[0] - $val ) . ' de plus',
				'pourquoi' => $attendu[1],
			);
		}

		$duree  = (int) ini_get( 'max_execution_time' );
		$assez  = ( 0 === $duree ) || ( $duree >= 120 );
		$lignes[] = array(
			'quoi'     => 'max_execution_time',
			'requis'   => '120 s',
			'constate' => 0 === $duree ? 'sans limite' : $duree . ' s',
			'etat'     => $assez ? 'ok' : 'manque',
			'ecart'    => $assez ? '' : ( 120 - $duree ) . ' s de plus',
			'pourquoi' => 'Assembler un paquet redimensionne des dizaines d\'images à la suite.',
		);

		$fichiers = (int) ini_get( 'max_file_uploads' );
		$ligne( 'max_file_uploads', '3', $fichiers ? (string) $fichiers : 'inconnu',
			$fichiers >= 3,
			'Une notice porte jusqu\'à trois illustrations.' );

		// — Le planificateur —
		$cron_coupe = defined( 'DISABLE_WP_CRON' ) && constant( 'DISABLE_WP_CRON' );
		$lignes[] = array(
			'quoi'     => 'Planificateur WordPress',
			'requis'   => 'actif',
			'constate' => $cron_coupe ? 'désactivé (DISABLE_WP_CRON)' : 'actif',
			'etat'     => $cron_coupe ? 'note' : 'ok',
			'ecart'    => '',
			'pourquoi' => $cron_coupe
				? 'Coupé, il faut une tâche système qui appelle wp-cron.php, faute de quoi la file d\'attente ne part jamais.'
				: 'C\'est lui qui expédie les notices mises en file.',
		);

		return $lignes;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$test_result = null;
		if ( isset( $_POST['na_test_turnstile'] ) ) {
			check_admin_referer( 'na_test_turnstile' );
			$test_result = $this->test_secret( self::get( 'turnstile_secret' ) );
		}
		// Un lien plutôt qu'un bouton : l'état des mises à jour se lit dans le
		// tableau des réglages, donc à l'intérieur du formulaire principal.
		// Un formulaire dans un formulaire n'existe pas en HTML — le
		// navigateur en abandonne un, et le jeton de sécurité partait sans
		// son formulaire : « le lien que vous avez suivi a expiré ».
		if ( isset( $_GET['na_chercher_maj'] ) ) {
			check_admin_referer( 'na_chercher_maj' );
			global $notice_archeomed_maj;
			if ( $notice_archeomed_maj instanceof Notice_Archeomed_MiseAJour ) {
				$notice_archeomed_maj->oublier();
			}
		}
		$essais_successifs = null;
		if ( isset( $_POST['na_essais_successifs'] ) ) {
			check_admin_referer( 'na_test_envoi' );
			global $notice_archeomed_plugin;
			if ( $notice_archeomed_plugin instanceof Notice_Archeomed_Pactols ) {
				$essais_successifs = $notice_archeomed_plugin->essais_successifs();
			}
		}
		$essai_poids = null;
		if ( isset( $_POST['na_essai_poids'] ) ) {
			check_admin_referer( 'na_test_envoi' );
			$vers = isset( $_POST['na_essai_vers'] )
				? sanitize_email( wp_unslash( $_POST['na_essai_vers'] ) ) : '';
			$mo   = isset( $_POST['na_poids_essai'] ) ? (int) $_POST['na_poids_essai'] : 0;
			global $notice_archeomed_plugin;
			$essai_poids = ( $notice_archeomed_plugin instanceof Notice_Archeomed_Pactols )
				? $notice_archeomed_plugin->essayer_le_poids( $vers, $mo )
				: array( 'ok' => false, 'message' => 'Le plugin n’est pas chargé.' );
		}
		$test_envoi = null;
		if ( isset( $_POST['na_test_envoi'] ) ) {
			check_admin_referer( 'na_test_envoi' );
			$vers = isset( $_POST['na_essai_vers'] )
				? sanitize_email( wp_unslash( $_POST['na_essai_vers'] ) ) : '';
			// L'instance créée en fin de fichier principal.
			global $notice_archeomed_plugin;
			$test_envoi = ( $notice_archeomed_plugin instanceof Notice_Archeomed_Pactols )
				? $notice_archeomed_plugin->tester_l_envoi( $vers )
				: array( 'ok' => false, 'message' => 'Le plugin n’est pas chargé.' );
		}
		$test_github = null;
		if ( isset( $_POST['na_test_github'] ) ) {
			check_admin_referer( 'na_test_github' );
			$test_github = $this->test_github();
		}

		$secret_locked = self::is_locked( 'turnstile_secret' );
		$site_locked   = self::is_locked( 'turnstile_site' );
		$email_locked  = self::destinataires_verrouilles();
		$options       = get_option( self::OPTION_NAME, array() );
		$has_secret    = '' !== trim( self::get( 'turnstile_secret' ) );
		// Le formulaire n'est hors service que si Turnstile est bien ce sur
		// quoi il s'appuie. Avec la protection locale, il fonctionne sans clé.
		$turnstile_sert = in_array(
			self::get( 'protection' ), array( 'turnstile', 'les_deux' ), true );
		?>
		<div class="wrap">
			<h1>Formulaire des notices d’archéologie médiévale</h1>

			<?php if ( $turnstile_sert && ! $has_secret ) : ?>
				<div class="notice notice-error">
					<p><strong>Le formulaire est actuellement hors service.</strong> Sans clé secrète Turnstile, toutes les soumissions sont refusées. Renseignez la clé ci-dessous.</p>
				</div>
			<?php endif; ?>

			<?php if ( null !== $test_result ) : ?>
				<div class="notice notice-<?php echo $test_result['ok'] ? 'success' : 'error'; ?>">
					<p><?php echo esc_html( $test_result['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<?php settings_errors( self::OPTION_NAME ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<h2>Protection anti-robot</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Comment le formulaire se protège</th>
						<td>
							<?php $prot = self::get( 'protection' ); $prot_locked = self::is_locked( 'protection' ); ?>
							<label style="display:block;margin-bottom:6px">
								<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[protection]"
									value="locale" <?php checked( 'locale' === $prot ); ?>
									<?php disabled( $prot_locked ); ?>>
								Curseur à glisser dans le formulaire <strong>(ne dépend de rien)</strong>
							</label>
							<label style="display:block;margin-bottom:6px">
								<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[protection]"
									value="turnstile" <?php checked( 'turnstile' === $prot ); ?>
									<?php disabled( $prot_locked ); ?>>
								Cloudflare Turnstile seul
							</label>
							<label style="display:block">
								<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[protection]"
									value="les_deux" <?php checked( 'les_deux' === $prot ); ?>
									<?php disabled( $prot_locked ); ?>>
								Les deux
							</label>
							<p class="description">
								<?php if ( $prot_locked ) : ?>
									Valeur imposée par la constante <code>NA_PROTECTION</code>.
								<?php else : ?>
									Turnstile suppose que <strong>le serveur</strong> puisse joindre
									Cloudflare. Derrière un proxy filtrant — hébergement institutionnel —
									il ne le peut pas&nbsp;: la vérification échoue, le plugin laisse
									passer pour ne pas perdre de notice, et la protection n'en est plus
									une. Le curseur posé dans le formulaire se vérifie sur place et
									fonctionne partout. Le test ci-dessous dit si Cloudflare est
									joignable depuis ce serveur.
								<?php endif; ?>
							</p>
						</td>
					</tr>
				</table>

				<h2>Cloudflare Turnstile</h2>
				<p>Ces clés se créent gratuitement sur <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">le tableau de bord Cloudflare</a>, rubrique Turnstile. La clé de site est publique ; la clé secrète ne doit jamais être diffusée.</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="na_site">Clé de site</label></th>
						<td>
							<input type="text" id="na_site" class="regular-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[turnstile_site]"
								value="<?php echo esc_attr( $site_locked ? self::get( 'turnstile_site' ) : ( isset( $options['turnstile_site'] ) ? $options['turnstile_site'] : self::get( 'turnstile_site' ) ) ); ?>"
								<?php disabled( $site_locked ); ?>>
							<?php if ( $site_locked ) : ?>
								<p class="description">Valeur imposée par la constante <code>NA_TURNSTILE_SITE</code> définie dans <code>wp-config.php</code>.</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="na_secret">Clé secrète</label></th>
						<td>
							<?php if ( $secret_locked ) : ?>
								<input type="text" class="regular-text" value="(définie dans wp-config.php)" disabled>
								<p class="description">Valeur imposée par la constante <code>NA_TURNSTILE_SECRET</code>. Pour la modifier, éditez <code>wp-config.php</code>.</p>
							<?php else : ?>
								<input type="password" id="na_secret" class="regular-text" autocomplete="new-password"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[turnstile_secret]"
									value="" placeholder="<?php echo $has_secret ? '••••••••••••  (clé enregistrée)' : 'aucune clé enregistrée'; ?>">
								<p class="description">
									<?php if ( $has_secret ) : ?>
										Une clé est enregistrée. Laissez ce champ vide pour la conserver, ou saisissez-en une nouvelle pour la remplacer.
									<?php else : ?>
										Collez ici la clé secrète fournie par Cloudflare.
									<?php endif; ?>
								</p>
								<?php if ( $has_secret ) : ?>
									<p>
										<label>
											<input type="checkbox" value="1"
												name="<?php echo esc_attr( self::OPTION_NAME ); ?>[clear_secret]">
											Effacer la clé enregistrée
										</label>
									</p>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<h2>Qui reçoit quoi</h2>
				<?php $destinataires = self::destinataires(); ?>
				<?php if ( $email_locked ) : ?>
					<p class="description">
						Liste imposée par la constante <code>NA_DEST_EMAIL</code> de
						<code>wp-config.php</code> : les adresses qui y figurent reçoivent
						tout, notices comme récapitulatifs.
					</p>
					<ul style="margin-left:1.5em;list-style:disc">
						<?php foreach ( $destinataires as $qui ) : ?>
							<li><code><?php echo esc_html( $qui['email'] ); ?></code></li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="description" style="max-width:46em">
						Deux sortes de courriels partent d'ici : <strong>une notice à chaque
						dépôt</strong>, avec son document et ses illustrations, et
						<strong>un récapitulatif quotidien</strong> qui liste ce qui est
						arrivé dans la journée. Chacun choisit ce qu'il veut recevoir.
					</p>
					<table class="widefat striped" id="na-destinataires" style="max-width:48em;margin-top:10px">
						<thead>
							<tr>
								<th style="width:55%">Adresse</th>
								<th style="width:15%">Notices</th>
								<th style="width:20%">Récapitulatif</th>
								<th style="width:10%"></th>
							</tr>
						</thead>
						<tbody>
						<?php
						// Une ligne vide en plus, pour qu'on puisse ajouter sans
						// avoir à chercher le bouton du premier coup.
						$lignes = $destinataires;
						$lignes[] = array( 'email' => '', 'notices' => true, 'recap' => false );
						foreach ( $lignes as $rang => $qui ) :
							$nom = esc_attr( self::OPTION_NAME ) . '[destinataires][' . (int) $rang . ']';
						?>
							<tr class="na-destinataire">
								<td>
									<input type="email" class="regular-text" style="width:100%"
										name="<?php echo $nom; ?>[email]"
										value="<?php echo esc_attr( $qui['email'] ); ?>"
										placeholder="adresse@exemple.fr">
								</td>
								<td style="text-align:center">
									<input type="checkbox" value="1" name="<?php echo $nom; ?>[notices]"
										<?php checked( ! empty( $qui['notices'] ) ); ?>>
								</td>
								<td style="text-align:center">
									<input type="checkbox" value="1" name="<?php echo $nom; ?>[recap]"
										<?php checked( ! empty( $qui['recap'] ) ); ?>>
								</td>
								<td style="text-align:center">
									<button type="button" class="button-link na-oter"
										aria-label="Retirer ce destinataire" title="Retirer ce destinataire">✕</button>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p>
						<button type="button" class="button" id="na-ajouter-destinataire">+ Ajouter un destinataire</button>
						<span class="description" style="margin-left:8px">
							<?php echo (int) self::MAX_DESTINATAIRES; ?> au plus. Une adresse effacée est retirée à l'enregistrement.
						</span>
					</p>
					<?php
					$pour_notices = self::destinataires_de( 'notices' );
					$pour_recap   = self::destinataires_de( 'recap' );
					?>
					<p class="description">
						<?php if ( empty( $pour_notices ) ) : ?>
							<strong style="color:#b32d2e">Personne ne reçoit les notices</strong> :
							les dépôts sont conservés, mais aucun ne part.
						<?php else : ?>
							Notices : <code><?php echo esc_html( implode( ', ', $pour_notices ) ); ?></code>.
						<?php endif; ?>
						<br>
						<?php if ( empty( $pour_recap ) ) : ?>
							Récapitulatif : personne. Il ne sera pas envoyé.
						<?php else : ?>
							Récapitulatif : <code><?php echo esc_html( implode( ', ', $pour_recap ) ); ?></code>.
						<?php endif; ?>
					</p>
					<script>
					(function () {
						var table = document.getElementById('na-destinataires');
						var corps = table.querySelector('tbody');
						var bouton = document.getElementById('na-ajouter-destinataire');
						var maximum = <?php echo (int) self::MAX_DESTINATAIRES; ?>;
						var prefixe = <?php echo wp_json_encode( self::OPTION_NAME ); ?>;
						// Le rang de la prochaine ligne : on ne réutilise pas ceux des
						// lignes retirées, l'enregistrement renumérote de toute façon.
						var suivant = corps.querySelectorAll('tr.na-destinataire').length;
						function ajouter() {
							if (corps.querySelectorAll('tr.na-destinataire').length >= maximum) {
								bouton.disabled = true;
								return;
							}
							var nom = prefixe + '[destinataires][' + (suivant++) + ']';
							var tr = document.createElement('tr');
							tr.className = 'na-destinataire';
							tr.innerHTML =
								'<td><input type="email" class="regular-text" style="width:100%" placeholder="adresse@exemple.fr" name="' + nom + '[email]"></td>' +
								'<td style="text-align:center"><input type="checkbox" value="1" checked name="' + nom + '[notices]"></td>' +
								'<td style="text-align:center"><input type="checkbox" value="1" name="' + nom + '[recap]"></td>' +
								'<td style="text-align:center"><button type="button" class="button-link na-oter" aria-label="Retirer ce destinataire" title="Retirer ce destinataire">\u2715</button></td>';
							corps.appendChild(tr);
							tr.querySelector('input[type=email]').focus();
							bouton.disabled = corps.querySelectorAll('tr.na-destinataire').length >= maximum;
						}
						bouton.addEventListener('click', ajouter);
						corps.addEventListener('click', function (e) {
							var b = e.target.closest('.na-oter');
							if (!b) { return; }
							var lignes = corps.querySelectorAll('tr.na-destinataire');
							// La dernière ligne se vide plutôt que de disparaître : un
							// tableau sans aucune ligne n'offre plus où saisir.
							if (lignes.length <= 1) {
								b.closest('tr').querySelectorAll('input[type=email]').forEach(function (i) { i.value = ''; });
								return;
							}
							b.closest('tr').remove();
							bouton.disabled = false;
						});
					}());
					</script>
				<?php endif; ?>

				<h2>Rythme d'envoi</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Expédition des courriels</th>
						<td>
							<?php $mode = self::get( 'mode_envoi' ); $mode_locked = self::is_locked( 'mode_envoi' ); ?>
							<label style="display:block;margin-bottom:6px">
								<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[mode_envoi]"
									value="differe" <?php checked( 'immediat' !== $mode ); ?>
									<?php disabled( $mode_locked ); ?>>
								Différée <strong>(recommandé)</strong> — le formulaire rend la main aussitôt
							</label>
							<label style="display:block">
								<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[mode_envoi]"
									value="immediat" <?php checked( 'immediat' === $mode ); ?>
									<?php disabled( $mode_locked ); ?>>
								Immédiate — le formulaire attend que le courriel soit parti
							</label>
							<p class="description">
								<?php if ( $mode_locked ) : ?>
									Valeur imposée par la constante <code>NA_MODE_ENVOI</code>.
								<?php else : ?>
									En différé, la notice est inscrite puis expédiée par le planificateur de
									WordPress&nbsp;: plusieurs personnes peuvent déposer en même temps sans que
									le site ralentisse, et un courriel qui échoue est represté tout seul. Ne
									passer en immédiat que si le planificateur est désactivé sur cet
									hébergement et qu'aucune tâche système ne le remplace — la liste
									«&nbsp;Notices Archéomed&nbsp;» le dira en s'allongeant.
								<?php endif; ?>
							</p>
						</td>
					</tr>
				</table>

				<h2>Acheminement du courriel</h2>
				<p class="description" style="max-width:46em">
					Tout le plugin en dépend : une notice qui ne part pas reste en file, puis
					échoue. Si le serveur répond <em>« Impossible d’instancier la fonction
					mail »</em>, c’est que <code>mail()</code> n’est pas configurée sur cet
					hébergement — un relais SMTP contourne la question.
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Par quoi les courriels passent</th>
						<td>
							<?php $mode = self::get( 'envoi_mode' ); ?>
							<label style="display:block;margin-bottom:6px">
								<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[envoi_mode]"
									value="php" <?php checked( 'smtp' !== $mode ); ?>>
								La fonction <code>mail()</code> du serveur <strong>(par défaut)</strong>
							</label>
							<label style="display:block">
								<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[envoi_mode]"
									value="smtp" <?php checked( 'smtp' === $mode ); ?>>
								Un relais SMTP
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="na_smtp_hote">Relais SMTP</label></th>
						<td>
							<input type="text" id="na_smtp_hote" class="regular-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[smtp_hote]"
								value="<?php echo esc_attr( self::get( 'smtp_hote' ) ); ?>"
								placeholder="smtp.exemple.fr">
							&nbsp;port
							<input type="number" min="1" max="65535" style="width:6em"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[smtp_port]"
								value="<?php echo (int) self::get( 'smtp_port' ); ?>">
							&nbsp;
							<?php $chif = self::get( 'smtp_chiffrement' ); ?>
							<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[smtp_chiffrement]">
								<option value="tls" <?php selected( 'tls', $chif ); ?>>STARTTLS (587)</option>
								<option value="ssl" <?php selected( 'ssl', $chif ); ?>>SSL (465)</option>
								<option value="aucun" <?php selected( 'aucun', $chif ); ?>>aucun chiffrement (25)</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="na_smtp_user">Authentification</label></th>
						<td>
							<input type="text" id="na_smtp_user" class="regular-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[smtp_utilisateur]"
								value="<?php echo esc_attr( self::get( 'smtp_utilisateur' ) ); ?>"
								placeholder="identifiant — laissez vide si le relais n’en demande pas">
							<p>
								<input type="password" class="regular-text" autocomplete="new-password"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[smtp_motdepasse]"
									placeholder="<?php echo '' !== (string) self::get( 'smtp_motdepasse' ) ? 'enregistré — laissez vide pour le garder' : 'mot de passe'; ?>">
								<?php if ( '' !== (string) self::get( 'smtp_motdepasse' ) ) : ?>
									<label style="margin-left:8px"><input type="checkbox" value="1"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[effacer_motdepasse]"> effacer</label>
								<?php endif; ?>
							</p>
							<p class="description">Un relais institutionnel accepte souvent ses propres machines sans identifiant.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="na_smtp_from">Adresse d’expédition</label></th>
						<td>
							<input type="email" id="na_smtp_from" class="regular-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[smtp_expediteur]"
								value="<?php echo esc_attr( self::get( 'smtp_expediteur' ) ); ?>"
								placeholder="notices@exemple.fr">
							<input type="text" class="regular-text" style="max-width:16em"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[smtp_nom]"
								value="<?php echo esc_attr( self::get( 'smtp_nom' ) ); ?>"
								placeholder="nom affiché">
							<p class="description">
								Elle doit appartenir au domaine que le relais accepte, sans quoi il
								refusera le message pour usurpation.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="na-poids-courriel">Poids maximal d’un courriel</label></th>
						<td>
							<input type="number" id="na-poids-courriel" min="1" max="100" style="width:6em"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[poids_courriel]"
								value="<?php echo esc_attr( (int) self::get( 'poids_courriel' ) ); ?>"> Mo
							<p class="description">
								Au-delà, le serveur de courriel refuse le message entier. Les illustrations
								partent en version allégée, qui pèse peu ; ce plafond ne compte que pour
								un original joint faute de version allégée — au-delà, il reste sur le site
								et le courriel le nomme, avec le lien de la notice. Une pièce jointe
								grossit d’un tiers en voyageant. L’« essai de poids », plus bas, mesure
								ce que le serveur accepte.
							</p>
						</td>
					</tr>
				</table>

				<h2>Mises à jour</h2>
				<p class="description" style="max-width:46em">
					Le plugin ne vit pas dans le répertoire de WordPress : sans cela, chaque
					correction se téléverse à la main. Renseignez le dépôt, et les nouvelles
					versions paraîtront dans <strong>Extensions</strong> comme pour n’importe
					quelle autre. Testez d’abord la sortie vers GitHub, plus bas : derrière
					le proxy d’un hébergement institutionnel, le site peut ne pas y accéder.
				</p>
				<input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[maj_presente]" value="1">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Proposer les mises à jour</th>
						<td>
							<label>
								<input type="checkbox" value="1"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[maj_github]"
									<?php checked( (int) self::get( 'maj_github' ), 1 ); ?>>
								Interroger GitHub et annoncer les versions plus récentes
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="na_depot">Dépôt</label></th>
						<td>
							<input type="text" id="na_depot" class="regular-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[github_depot]"
								value="<?php echo esc_attr( self::get( 'github_depot' ) ); ?>"
								placeholder="compte/depot">
							<p class="description">
								Sous la forme <code>compte/depot</code>, sans adresse complète.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="na_jeton">Jeton d’accès</label></th>
						<td>
							<input type="password" id="na_jeton" class="regular-text" autocomplete="new-password"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[github_jeton]"
								placeholder="<?php echo '' !== trim( self::get( 'github_jeton' ) ) ? 'enregistré — laissez vide pour le garder' : 'inutile si le dépôt est public'; ?>">
							<?php if ( '' !== trim( self::get( 'github_jeton' ) ) ) : ?>
								<p><label><input type="checkbox" value="1"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[effacer_jeton]"> Effacer le jeton enregistré</label></p>
							<?php endif; ?>
							<p class="description">
								Nécessaire seulement si le dépôt est privé. Il n’est envoyé qu’aux
								hôtes de GitHub, jamais ailleurs.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">État</th>
						<td>
							<?php
							// L'instance créée au chargement du plugin, et non une
							// seconde : en fabriquer une ici enregistrerait ses
							// filtres une deuxième fois.
							global $notice_archeomed_maj;
							$release = ( $notice_archeomed_maj instanceof Notice_Archeomed_MiseAJour )
								? $notice_archeomed_maj->derniere_release() : array();
							$installee = ( $notice_archeomed_maj instanceof Notice_Archeomed_MiseAJour )
								? $notice_archeomed_maj->version() : '';
							?>
							<p>Version installée : <code><?php echo esc_html( $installee ); ?></code></p>
							<p style="margin:8px 0">
								<a class="button button-secondary" href="<?php
									echo esc_url( wp_nonce_url(
										add_query_arg( 'na_chercher_maj', '1', self::url() ),
										'na_chercher_maj' ) ); ?>">Chercher une mise à jour maintenant</a>
								<span class="description" style="margin-left:8px">
									GitHub n’est interrogé qu’une fois toutes les six heures, et
									WordPress garde sa propre réserve jusqu’à douze heures. Ce
									bouton vide les deux.
								</span>
							</p>
							<?php if ( ! empty( $release['echec'] ) ) : ?>
								<p style="color:#b32d2e"><strong><?php echo esc_html( $release['echec'] ); ?></strong></p>
							<?php elseif ( empty( $release['version'] ) ) : ?>
								<p class="description">Aucune version publiée n’a pu être lue — dépôt non renseigné, ou mécanisme éteint.</p>
							<?php else : ?>
								<p>Dernière version publiée : <code><?php echo esc_html( $release['version'] ); ?></code></p>
								<?php if ( empty( $release['propre'] ) ) : ?>
									<p style="color:#b32d2e"><strong>La release ne porte pas d’archive <code>notice-archeomed.zip</code>.</strong>
									Celle que GitHub fabrique seul s’ouvre sur le mauvais dossier et installerait le plugin
									à côté de lui-même : la mise à jour n’est donc pas proposée. Joignez à la release
									l’archive produite par <code>./empaqueter</code>.</p>
								<?php elseif ( version_compare( $release['version'], $installee, '>' ) ) : ?>
									<p style="color:#2f6b2f"><strong>Une mise à jour est disponible</strong> — elle paraît dans « Extensions ».</p>
								<?php else : ?>
									<p class="description">Le site est à jour.</p>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<h2>Iconographie</h2>
				<p class="description" style="max-width:46em">
					Ce qui gouverne le dossier <code>icono</code> d’un numéro : le nom
					des fichiers, et les deux définitions qu’on en tire — la basse,
					posée en lien dans le document Word, et la haute, qui part à la
					mise en page.
				</p>
				<input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[icono_presente]" value="1">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="na_numero">Numéro en préparation</label></th>
						<td>
							<input type="text" id="na_numero" class="regular-text" style="max-width:12em"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[numero]"
								value="<?php echo esc_attr( self::get( 'numero' ) ); ?>"
								placeholder="AM55">
							<p class="description">Il ouvre le nom de chaque illustration. Laissé vide, le nom commence au rang de la rubrique.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="na_nom_modele">Modèle de nom</label></th>
						<td>
							<input type="text" id="na_nom_modele" class="large-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[nom_modele]"
								value="<?php echo esc_attr( self::get( 'nom_modele' ) ); ?>">
							<p class="description">
								Jetons admis :
								<?php
								$jetons = array();
								foreach ( self::JETONS_DE_NOM as $jeton => $quoi ) {
									$jetons[] = '<code>' . esc_html( $jeton ) . '</code> ' . esc_html( $quoi );
								}
								echo wp_kses_post( implode( ' ; ', $jetons ) );
								?>.
								<br>
								Ce qui n’est pas un jeton est recopié tel quel, puis tout le nom
								passe à la règle : pas d’accent, pas d’espace, le souligné pour
								seul séparateur. <strong><code>{n}</code> est obligatoire</strong> :
								sans lui, deux illustrations d’une même notice porteraient le même nom.
								<br>
								Exemple : <code><?php echo esc_html( self::exemple_de_nom() ); ?></code>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Basse définition (<code>icono/br</code>)</th>
						<td>
							<label>Largeur maximale
								<input type="number" min="200" max="4000" step="10" style="width:7em"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[br_largeur]"
									value="<?php echo (int) self::get( 'br_largeur' ); ?>"> px</label>
							&nbsp;&nbsp;
							<label>Résolution
								<input type="number" min="72" max="300" step="1" style="width:6em"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[br_dpi]"
									value="<?php echo (int) self::get( 'br_dpi' ); ?>"> dpi</label>
							&nbsp;&nbsp;
							<label>Poids maximal
								<input type="number" min="50" max="20480" step="50" style="width:7em"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[br_poids]"
									value="<?php echo (int) self::get( 'br_poids' ); ?>"> Ko</label>
							&nbsp;&nbsp;
							<label>Tolérance
								<input type="number" min="0" max="50" step="1" style="width:5em"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[br_tolerance]"
									value="<?php echo (int) self::get( 'br_tolerance' ); ?>"> %</label>
							<p class="description">
								Elle n’est pas destinée à l’impression : elle sert à voir la figure
								à sa place dans le document. Le poids compte donc autant que la
								largeur — cent figures liées dans un fascicule, et le dossier
								devient intransportable.
								<br>
								<strong>La qualité ne se règle pas</strong> : le plugin part de la
								meilleure et ne la baisse que si le fichier dépasse le plafond,
								par paliers, en s’arrêtant au premier qui tient. La tolérance dit
								jusqu’où descendre sans insister :
								<?php
								$plafond = (int) self::get( 'br_poids' );
								$bas     = (int) round( $plafond * ( 100 - (int) self::get( 'br_tolerance' ) ) / 100 );
								printf( 'on s’arrête dès que le fichier tient entre %d et %d Ko.',
									(int) $bas, (int) $plafond );
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Haute définition (<code>icono/hr</code>)<br>
							<span class="description" style="font-weight:400">photographies</span></th>
						<td>
							<label>
								<input type="checkbox" value="1"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hr_photo_jpeg]"
									<?php checked( (int) self::get( 'hr_photo_jpeg' ), 1 ); ?>>
								Convertir en JPEG
							</label>
							&nbsp;&nbsp;
							<label>Résolution
								<input type="number" min="150" max="1200" step="1" style="width:6em"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hr_photo_dpi]"
									value="<?php echo (int) self::get( 'hr_photo_dpi' ); ?>"> dpi</label>
							&nbsp;&nbsp;
							<label>Qualité
								<input type="number" min="40" max="100" step="1" style="width:6em"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hr_photo_qualite]"
									value="<?php echo (int) self::get( 'hr_photo_qualite' ); ?>"> %</label>
							<p class="description">
								Ce qui arrive en JPEG, TIFF ou PNG. La revue demande
								10 × 15 cm à 300 dpi au minimum.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Haute définition (<code>icono/hr</code>)<br>
							<span class="description" style="font-weight:400">dessins au trait et vectoriels</span></th>
						<td>
							<label>
								<input type="checkbox" value="1"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hr_trait_jpeg]"
									<?php checked( (int) self::get( 'hr_trait_jpeg' ), 1 ); ?>>
								Convertir en JPEG
							</label>
							&nbsp;&nbsp;
							<label>Résolution
								<input type="number" min="150" max="2400" step="1" style="width:6em"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hr_trait_dpi]"
									value="<?php echo (int) self::get( 'hr_trait_dpi' ); ?>"> dpi</label>
							&nbsp;&nbsp;
							<label>Qualité
								<input type="number" min="40" max="100" step="1" style="width:6em"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hr_trait_qualite]"
									value="<?php echo (int) self::get( 'hr_trait_qualite' ); ?>"> %</label>
							<p class="description">
								Ce qui arrive en PDF, EPS ou AI — c’est le format déposé qui range
								l’illustration dans l’une ou l’autre famille. La revue demande
								1200 dpi pour un trait, et <strong>le convertir en JPEG lui ôte
								justement ce qu’on lui demande</strong> : décoché, l’original est
								recopié tel quel.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Originaux</th>
						<td>
							<label>
								<input type="checkbox" value="1"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[garder_originaux]"
									<?php checked( (int) self::get( 'garder_originaux' ), 1 ); ?>>
								Garder les fichiers d’origine dans <code>icono/originaux</code>
							</label>
							<p class="description">Rien de ce que l’auteur a envoyé ne disparaît alors dans une conversion.</p>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Enregistrer les réglages' ); ?>
			</form>

			<hr>

			<h2>Vérification</h2>
			<p>Ce test interroge Cloudflare pour savoir si la clé secrète enregistrée est reconnue. Il n'envoie aucune notice.</p>
			<form method="post">
				<?php wp_nonce_field( 'na_test_turnstile' ); ?>
				<?php submit_button( 'Tester la clé secrète', 'secondary', 'na_test_turnstile', false ); ?>
			</form>

			<hr>

			<h2>Feuille de style Métopes</h2>
			<p class="description" style="max-width:46em">
				<a href="https://www.metopes.fr" target="_blank" rel="noopener">Métopes</a> —
				chaîne d’édition XML créée par le Pôle document numérique et l’infrastructure
				Métopes de l’université de Caen Normandie.
			</p>
			<?php
			$messages = array(
				'posee'    => array( 'success', 'La nouvelle feuille de style est en service.' ),
				'retiree'  => array( 'success', 'La feuille déposée a été retirée : celle livrée avec le plugin reprend la main.' ),
				'vide'     => array( 'error', 'Aucun fichier n’a été reçu.' ),
				'format'   => array( 'error', 'Seuls les fichiers .docx et .rtf sont acceptés.' ),
				'invalide' => array( 'error', 'Ce document ne porte pas de feuille de styles : ce n’est pas un gabarit Métopes. Rien n’a été changé.' ),
				'ecriture' => array( 'error', 'Le fichier n’a pas pu être écrit dans le dossier des téléversements.' ),
			);
			$retour = isset( $_GET['na_feuille'] ) ? sanitize_key( wp_unslash( $_GET['na_feuille'] ) ) : '';
			if ( isset( $messages[ $retour ] ) ) {
				echo '<div class="notice notice-' . esc_attr( $messages[ $retour ][0] ) . '"><p>'
					. esc_html( $messages[ $retour ][1] ) . '</p></div>';
			}
			$feuille = Notice_Archeomed_Pactols::etat_de_la_feuille( 'docx' );
			?>
			<table class="widefat striped" style="max-width:46em">
				<tbody>
					<tr><td style="width:36%"><strong>En service</strong></td>
						<td><?php echo $feuille['deposee']
							? 'feuille déposée depuis cette page'
							: 'feuille livrée avec le plugin'; ?></td></tr>
					<?php if ( $feuille['presente'] ) : ?>
						<tr><td><strong>Date du document</strong></td>
							<td><?php echo '' !== $feuille['modifiee']
								? esc_html( mysql2date( 'j F Y', str_replace( array( 'T', 'Z' ), array( ' ', '' ), $feuille['modifiee'] ) ) )
								: '<span class="description">non renseignée dans le fichier</span>'; ?></td></tr>
						<tr><td><strong>Posée sur le serveur le</strong></td>
							<td><?php echo esc_html( date_i18n( 'j F Y à H:i', $feuille['posee'] ) ); ?></td></tr>
						<tr><td><strong>Styles déclarés</strong></td>
							<td><?php echo (int) $feuille['styles'] > 0
								? (int) $feuille['styles'] : '<span class="description">illisible sans ZipArchive</span>'; ?></td></tr>
						<tr><td><strong>Poids</strong></td>
							<td><?php echo esc_html( size_format( $feuille['poids'] ) ); ?></td></tr>
					<?php else : ?>
						<tr><td colspan="2" style="color:#b32d2e"><strong>Aucune feuille de style trouvée.</strong>
						Les notices partiront sans document mis en forme.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>

			<p class="description" style="max-width:46em;margin-top:10px">
				Quand Métopes fait évoluer sa feuille, déposez ici le nouveau gabarit :
				inutile d’aller dans le dossier du plugin. Les styles sont reconnus par
				leur nom, aucune modification du code n’est nécessaire.
				<br>
				<strong>La feuille déposée vit dans les téléversements, non dans le plugin</strong> —
				une mise à jour de l’extension remplace son dossier en entier et
				effacerait un gabarit qu’on y aurait posé.
			</p>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="na_feuille">
				<?php wp_nonce_field( 'na_feuille' ); ?>
				<p>
					<input type="file" name="na_feuille" accept=".docx,.rtf">
					<?php submit_button( 'Déposer cette feuille', 'secondary', '', false ); ?>
				</p>
				<?php if ( $feuille['deposee'] ) : ?>
					<p>
						<label><input type="checkbox" name="na_feuille_retirer" value="1">
						Retirer la feuille déposée et revenir à celle du plugin</label>
						<?php submit_button( 'Appliquer', 'secondary small', '', false ); ?>
					</p>
				<?php endif; ?>
			</form>

			<p class="description">
				Après tout changement de gabarit, passez <code>./verifier-les-styles</code> :
				un style disparu ne provoque aucune erreur, le paragraphe sort simplement
				en Normal et l’on ne s’en aperçoit qu’à la relecture.
			</p>

			<hr>

			<h2>Essai d’envoi</h2>
			<p style="max-width:46em">Ce bouton envoie un courriel et rapporte ce que le
			serveur a répondu — sans déposer de notice ni attendre le planificateur.
			Enregistrez d’abord les réglages ci-dessus.</p>
			<form method="post">
				<?php wp_nonce_field( 'na_test_envoi' ); ?>
				<p>
					<input type="email" name="na_essai_vers" class="regular-text"
						value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>"
						placeholder="adresse d’essai">
					<?php submit_button( 'Envoyer un courriel d’essai', 'secondary', 'na_test_envoi', false ); ?>
				</p>
				<p>
					<?php submit_button( 'Refaire l’envoi d’une notice, étape par étape', 'secondary', 'na_essais_successifs', false ); ?>
					<span class="description" style="margin-left:8px">
						Quand un essai simple part mais qu’une notice est refusée, celui-ci
						dit lequel des écarts en est la cause : plusieurs destinataires, un
						en-tête Reply-To, une pièce jointe.
					</span>
				</p>
				<p>
					<select name="na_poids_essai">
						<?php foreach ( Notice_Archeomed_Pactols::TAILLES_ESSAI as $mo ) : ?>
							<option value="<?php echo (int) $mo; ?>"><?php echo (int) $mo; ?> Mo</option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( 'Essai de poids', 'secondary', 'na_essai_poids', false ); ?>
					<span class="description" style="margin-left:8px">
						Un seul message, avec une pièce jointe de la taille choisie : il dit
						jusqu’où le serveur de courriel accepte, sans rien d’autre qui varie.
					</span>
				</p>
			</form>
			<?php if ( null !== $essai_poids ) : ?>
				<div class="notice notice-<?php echo $essai_poids['ok'] ? 'success' : 'error'; ?>">
					<p><?php echo esc_html( $essai_poids['message'] ); ?></p>
				</div>
			<?php endif; ?>
			<?php
			$poids_essayes = get_option( 'na_essais_de_poids', array() );
			if ( is_array( $poids_essayes ) && ! empty( $poids_essayes ) ) :
				$passe_max  = 0;
				$refus_min  = 0;
				foreach ( $poids_essayes as $mo => $e ) {
					if ( ! empty( $e['ok'] ) ) {
						$passe_max = max( $passe_max, (int) $mo );
					} elseif ( ! $refus_min || (int) $mo < $refus_min ) {
						$refus_min = (int) $mo;
					}
				}
				?>
				<table class="widefat striped" style="max-width:52em;margin-bottom:6px">
					<thead><tr><th>Pièce jointe</th><th>Réponse du serveur</th><th>Le</th></tr></thead>
					<tbody>
					<?php foreach ( $poids_essayes as $mo => $e ) : ?>
						<tr>
							<td><strong><?php echo (int) $mo; ?> Mo</strong></td>
							<td style="color:<?php echo ! empty( $e['ok'] ) ? '#2f6b2f' : '#b32d2e'; ?>">
								<?php echo ! empty( $e['ok'] ) ? 'accepté' : 'refusé'; ?>
							</td>
							<td><?php echo esc_html( mysql2date( 'j F Y à H:i', $e['quand'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description" style="max-width:52em">
					<?php
					if ( $passe_max && $refus_min && $passe_max < $refus_min ) {
						echo esc_html( sprintf( 'La limite du serveur se situe entre %d et %d Mo de pièces jointes. '
							. 'C’est le chiffre à donner à l’hébergeur ; et c’est d’après lui que se règle '
							. '« Poids maximal d’un courriel », plus haut — en comptant un tiers de plus pour l’encodage.',
							$passe_max, $refus_min ) );
					} elseif ( $passe_max && ! $refus_min ) {
						echo esc_html( sprintf( 'Le serveur accepte au moins %d Mo de pièces jointes. '
							. 'Si le message arrive bien, « Poids maximal d’un courriel » peut être relevé.', $passe_max ) );
					} elseif ( $refus_min && ! $passe_max ) {
						echo esc_html( sprintf( 'Le serveur refuse dès %d Mo de pièces jointes : essayez une taille plus petite pour borner la limite.', $refus_min ) );
					} else {
						echo esc_html( 'Les essais se contredisent — un poids plus lourd accepté après un plus léger refusé : '
							. 'la cause n’est peut-être pas le poids. Refaites-les à quelques minutes d’intervalle.' );
					}
					?>
				</p>
			<?php endif; ?>
			<?php if ( is_array( $essais_successifs ) ) : ?>
				<table class="widefat striped" style="max-width:52em;margin-bottom:12px">
					<tbody>
					<?php foreach ( $essais_successifs as $e ) : ?>
						<tr>
							<td style="width:34%"><strong><?php echo esc_html( $e['etape'] ); ?></strong></td>
							<td style="color:<?php echo $e['ok'] ? '#2f6b2f' : '#b32d2e'; ?>">
								<?php echo esc_html( $e['message'] ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description">La première ligne en rouge nomme ce qui fait échouer l’envoi.</p>
			<?php endif; ?>
			<?php if ( null !== $test_envoi ) : ?>
				<div class="notice notice-<?php echo $test_envoi['ok'] ? 'success' : 'error'; ?>">
					<p><?php echo esc_html( $test_envoi['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<hr>

			<h2>Sortie vers GitHub</h2>
			<p style="max-width:46em">Ce test dit si le serveur peut joindre GitHub — l’API
			qui annonce les versions, et l’hôte qui sert les fichiers. C’est ce dont
			dépendrait une mise à jour proposée dans « Extensions » plutôt que téléversée
			à la main. Le même proxy empêche déjà de joindre Cloudflare.</p>
			<form method="post">
				<?php wp_nonce_field( 'na_test_github' ); ?>
				<?php submit_button( 'Tester l’accès à GitHub', 'secondary', 'na_test_github', false ); ?>
			</form>
			<?php if ( null !== $test_github ) : ?>
				<div class="notice notice-<?php echo $test_github['ok'] ? 'success' : 'error'; ?>">
					<p><?php echo esc_html( $test_github['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<hr>

			<h2>Ce que l’hébergement offre</h2>
			<p>Ce tableau ne change rien : il regarde. Chaque ligne dit ce qu’il
			faut, ce qu’il y a, et ce qui manque — de quoi savoir sur quoi
			compter, et quoi demander à l’hébergeur.</p>
			<?php
			$etat = self::etat_du_serveur();
			$manques = array_values( array_filter( $etat, function ( $l ) {
				return 'manque' === $l['etat'];
			} ) );
			?>
			<table class="widefat striped" style="max-width:60em">
				<thead>
					<tr>
						<th style="width:20%">Ce qu’il faut</th>
						<th style="width:12%">Requis</th>
						<th style="width:14%">Constaté</th>
						<th style="width:14%">Écart</th>
						<th>Pourquoi</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $etat as $l ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $l['quoi'] ); ?></strong></td>
						<td><?php echo esc_html( $l['requis'] ); ?></td>
						<td<?php echo 'manque' === $l['etat'] ? ' style="color:#b32d2e;font-weight:600"' : ''; ?>>
							<?php echo esc_html( $l['constate'] ); ?>
						</td>
						<td<?php echo '' !== $l['ecart'] ? ' style="color:#b32d2e"' : ''; ?>>
							<?php echo '' !== $l['ecart'] ? esc_html( $l['ecart'] ) : '—'; ?>
						</td>
						<td class="description"><?php echo esc_html( $l['pourquoi'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( empty( $manques ) ) : ?>
				<p style="color:#2f6b2f"><strong>Rien ne manque.</strong></p>
			<?php else : ?>
				<p style="color:#b32d2e"><strong><?php echo (int) count( $manques ); ?>
				<?php echo 1 === count( $manques ) ? 'point manque' : 'points manquent'; ?>.</strong>
				Le reste du plugin fonctionne, mais ce qui en dépend restera hors d’atteinte.</p>
			<?php endif; ?>

			<h2>Utilisation</h2>
			<p>Insérez le code court <code>[notice_archeomed_pactols]</code> dans la page devant accueillir le formulaire.</p>
		</div>
		<?php
	}
}
