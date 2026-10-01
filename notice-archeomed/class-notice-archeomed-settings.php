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
		'{numero}'   => 'le numéro en préparation — « AM55 »',
		'{rubrique}' => 'le rang de la rubrique — « 2 » pour la deuxième',
		'{commune}'  => 'la commune de la notice',
		'{lieu_dit}' => 'le lieu-dit — il distingue deux notices d’une même commune',
		'{annee}'    => 'l’année de l’opération',
		'{n}'        => 'le rang de la figure dans la notice',
	);

	/** La dernière valeur rendue par sanitize(), que add_option repasse. */
	private static $dernier_nettoye = null;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_na_essai', array( $this, 'faire_un_essai' ) );
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
	 *
	 * Avec l'onglet voulu, s'il y en a un : un bandeau qui dit « renseignez
	 * la clé » doit mener à la clé, non au premier onglet de la page.
	 */
	public static function url( $onglet = '' ) {
		$url = admin_url( 'edit.php?post_type=' . Notice_Archeomed_File::CPT
			. '&page=' . self::PAGE_SLUG );
		return ( '' !== $onglet && array_key_exists( $onglet, self::onglets() ) )
			? add_query_arg( 'tab', $onglet, $url ) : $url;
	}

	/**
	 * Les onglets de la page, dans l'ordre où l'on règle une installation.
	 *
	 * Une seule page de sept sections et quatre formulaires : on enregistrait
	 * les réglages en croyant lancer un essai, ou l'inverse, et le bouton
	 * « Enregistrer » se trouvait trois écrans plus bas que le champ touché.
	 */
	public static function onglets() {
		return array(
			'destinataires' => __( 'Destinataires', 'notice-archeomed' ),
			'numero'        => __( 'Numéro et iconographie', 'notice-archeomed' ),
			'normes'        => __( 'Normes éditoriales', 'notice-archeomed' ),
			'styles'        => __( 'Styles Métopes', 'notice-archeomed' ),
			'formulaire'    => __( 'Formulaire', 'notice-archeomed' ),
			'accessibilite' => __( 'Accessibilité', 'notice-archeomed' ),
			'courriel'      => __( 'Courriel', 'notice-archeomed' ),
			'feuille'       => __( 'Feuille de styles', 'notice-archeomed' ),
			'maj'           => __( 'Mises à jour', 'notice-archeomed' ),
			// « Diagnostic » se lisait « rien à régler ici », et c'est là que
			// vivait le rythme d'envoi. Il est passé à l'onglet Courriel ; ne
			// reste que le constat de l'hébergement. La clé ne change pas : les
			// liens déjà donnés mènent toujours au même endroit.
			'diagnostic'    => __( 'Hébergement', 'notice-archeomed' ),
		);
	}

	/** L'onglet demandé dans l'adresse, ou le premier. */
	private static function onglet_demande() {
		$onglet = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		return array_key_exists( $onglet, self::onglets() ) ? $onglet : 'destinataires';
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
			__( 'Réglages de la Chronique', 'notice-archeomed' ),
			__( 'Réglages', 'notice-archeomed' ),
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
		// Tant que l'option est absente ou vide, WordPress passe par
		// add_option, qui renettoie la valeur déjà nettoyée : sans les
		// témoins d'onglet (« styles_presents », « normes_presentes »…), ce
		// second passage repartait de l'option vide, et la page disait
		// « Réglages enregistrés » sans rien garder.
		if ( null !== self::$dernier_nettoye && $input === self::$dernier_nettoye ) {
			return $input;
		}
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
						'Adresse non valide, écartée de la liste : %s. Les autres ont bien été enregistrées.',
						esc_html( implode( ', ', $refuses ) )
					),
					'error'
				);
			}
			if ( empty( $liste ) ) {
				add_settings_error(
					self::OPTION_NAME,
					'destinataires_vide',
					'Aucun destinataire n’est enregistré : les notices déposées seront conservées, mais aucune ne partira.',
					'warning'
				);
			} elseif ( empty( self::destinataires_de_la_liste( $liste, 'notices' ) ) ) {
				add_settings_error(
					self::OPTION_NAME,
					'destinataires_sans_notices',
					'Personne ne reçoit les notices : elles seront conservées sans être expédiées. Cochez « Chaque notice » pour au moins un destinataire.',
					'warning'
				);
			}
		}

		// Les normes éditoriales : un onglet à lui, qui peut aussi rétablir
		// d'un geste les choix de la revue.
		if ( ! empty( $input['normes_retablir'] ) ) {
			unset( $out[ Notice_Archeomed_Normes::CLE ] );
			Notice_Archeomed_Normes::oublier();
		} elseif ( ! empty( $input['normes_presentes'] ) ) {
			$out[ Notice_Archeomed_Normes::CLE ] = Notice_Archeomed_Normes::nettoyer(
				isset( $input[ Notice_Archeomed_Normes::CLE ] ) && is_array( $input[ Notice_Archeomed_Normes::CLE ] )
					? $input[ Notice_Archeomed_Normes::CLE ] : array() );
			Notice_Archeomed_Normes::oublier();
		}

		// La correspondance des styles Métopes : un style refusé — absent de
		// la feuille, ou du mauvais type — garde la valeur d'avant et le dit.
		if ( ! empty( $input['styles_retablir'] ) ) {
			unset( $out[ Notice_Archeomed_Styles::CLE ] );
			Notice_Archeomed_Styles::oublier();
		} elseif ( ! empty( $input['styles_presents'] ) ) {
			list( $styles, $refuses ) = Notice_Archeomed_Styles::nettoyer(
				isset( $input[ Notice_Archeomed_Styles::CLE ] ) && is_array( $input[ Notice_Archeomed_Styles::CLE ] )
					? $input[ Notice_Archeomed_Styles::CLE ] : array(),
				isset( $out[ Notice_Archeomed_Styles::CLE ] ) ? $out[ Notice_Archeomed_Styles::CLE ] : array() );
			$out[ Notice_Archeomed_Styles::CLE ] = $styles;
			Notice_Archeomed_Styles::oublier();
			if ( ! empty( $refuses ) ) {
				add_settings_error( self::OPTION_NAME, 'styles_refuses',
					'Style absent de la feuille ou du mauvais type, choix non retenu pour : ' . implode( ', ', $refuses ) . '.', 'error' );
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
					'« compte/depot » est l’exemple du champ, non un dépôt : indiquez le vrai nom, par exemple « 2d2yrhrsf9-droid/notice-archeomed ». L’ancienne valeur a été conservée.',
					'error'
				);
			} elseif ( '' === $depot || preg_match( '#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $depot ) ) {
				$out['github_depot'] = $depot;
			} else {
				add_settings_error(
					self::OPTION_NAME, 'github_depot',
					'Le dépôt s’écrit « compte/depot », sans adresse complète ni barre finale. L’ancienne valeur a été conservée.',
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
					'Le modèle de nom doit contenir « {n} », le rang de la figure : sans lui, deux illustrations d’une même notice porteraient le même nom. L’ancien modèle a été conservé.',
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
						'Le modèle ne contient pas « {lieu_dit} » : deux notices d’une même commune et d’une même année donneraient des noms identiques. L’assemblage les distinguera par un suffixe, mais le lieu-dit se lit mieux.',
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

		self::$dernier_nettoye = $out;
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
				$lignes[] = $nom . ' : injoignable — ' . $message;
				// L'erreur 56 derrière un CONNECT est la signature du proxy
				// filtrant, celle que Cloudflare renvoie déjà.
				if ( false !== stripos( $message, 'proxy' ) || false !== stripos( $message, 'error 56' ) ) {
					$lignes[] = '    → c’est le proxy de l’hébergement qui refuse la sortie, comme pour Cloudflare.';
				}
				continue;
			}
			++$joints;
			$lignes[] = $nom . ' : joint (code HTTP ' . (int) wp_remote_retrieve_response_code( $reponse ) . ').';
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
				. ' Le site saurait qu’une version existe mais ne pourrait pas la télécharger : il faut les deux.',
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
				'message' => 'Impossible de joindre Cloudflare : ' . $response->get_error_message(),
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
				'TIFF' => 'Les aperçus intégrés aux EPS sont des TIFF : c\'est par eux que passent les basses définitions.',
				'JPEG' => 'Le format des illustrations livrées à la mise en page.',
				'PNG'  => 'Fréquent pour les dessins au trait.',
			) as $format => $pourquoi ) {
				$su = (array) Imagick::queryFormats( $format );
				$ligne( 'Imagick : ' . $format, 'accepté',
					! empty( $su ) ? 'accepté' : 'refusé', ! empty( $su ), $pourquoi );
			}
			// PDF et EPS passent par Ghostscript, que la politique
			// d'ImageMagick désactive d'origine depuis « ImageTragick ». On le
			// signale sans le réclamer : le plugin sait s'en passer.
			$ps = (array) Imagick::queryFormats( 'PDF' );
			$lignes[] = array(
				'quoi'     => 'Imagick : PDF et EPS',
				'requis'   => 'facultatif',
				'constate' => ! empty( $ps ) ? 'accepté' : 'refusé',
				'etat'     => 'note',
				'ecart'    => '',
				'pourquoi' => 'Passe par Ghostscript, que la politique d\'ImageMagick désactive presque partout. Le plugin s\'en passe : il tire la basse définition de l\'aperçu intégré à l\'EPS, et garde l\'original en haute définition.',
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

	/**
	 * Les essais de la page, faits hors de la page, puis renvoi vers elle.
	 *
	 * Ils se faisaient pendant l'affichage, sur le POST même : recharger la
	 * page — le premier geste de qui attend — renvoyait l'essai, et l'essai
	 * de poids repartait avec ses vingt méga-octets. Ici l'essai se fait une
	 * fois, son résultat se garde quelques minutes pour l'utilisateur, et la
	 * page qu'on recharge n'est plus qu'une lecture.
	 */
	public function faire_un_essai() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		check_admin_referer( 'na_essai' );
		$quoi    = isset( $_REQUEST['na_quoi'] ) ? sanitize_key( wp_unslash( $_REQUEST['na_quoi'] ) ) : '';
		$onglets = array(
			'turnstile' => 'formulaire',
			'envoi'     => 'courriel',
			'etapes'    => 'courriel',
			'poids'     => 'courriel',
			'github'    => 'maj',
			'maj'       => 'maj',
			'pactols'   => 'diagnostic',
		);
		if ( ! isset( $onglets[ $quoi ] ) ) {
			wp_die( esc_html__( 'Essai inconnu.', 'notice-archeomed' ) );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 );
		}
		// L'instance créée en fin de fichier principal.
		global $notice_archeomed_plugin, $notice_archeomed_maj;
		$plugin = ( $notice_archeomed_plugin instanceof Notice_Archeomed_Pactols )
			? $notice_archeomed_plugin : null;
		$absent = array( 'ok' => false, 'message' => 'Le plugin n’est pas chargé.' );
		$vers   = isset( $_POST['na_essai_vers'] )
			? sanitize_email( wp_unslash( $_POST['na_essai_vers'] ) ) : '';
		if ( 'turnstile' === $quoi ) {
			$resultat = $this->test_secret( self::get( 'turnstile_secret' ) );
		} elseif ( 'envoi' === $quoi ) {
			$resultat = $plugin ? $plugin->tester_l_envoi( $vers ) : $absent;
		} elseif ( 'etapes' === $quoi ) {
			$resultat = $plugin ? $plugin->essais_successifs() : array();
		} elseif ( 'poids' === $quoi ) {
			$mo       = isset( $_POST['na_poids_essai'] ) ? (int) $_POST['na_poids_essai'] : 0;
			$resultat = $plugin ? $plugin->essayer_le_poids( $vers, $mo ) : $absent;
		} elseif ( 'github' === $quoi ) {
			$resultat = $this->test_github();
		} elseif ( 'pactols' === $quoi ) {
			$resultat = $this->test_pactols();
		} else {
			if ( $notice_archeomed_maj instanceof Notice_Archeomed_MiseAJour ) {
				$notice_archeomed_maj->oublier();
			}
			$resultat = array( 'ok' => true,
				'message' => 'Les réserves sont vidées : l’état ci-dessous vient d’être relu sur GitHub.' );
		}
		set_transient( 'na_essai_' . get_current_user_id(),
			array( 'quoi' => $quoi, 'resultat' => $resultat ), 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'na_essai', $quoi, self::url( $onglets[ $quoi ] ) ) );
		exit;
	}

	/** Le résultat d'essai qui attend d'être lu, une seule fois. */
	private function essai_a_montrer() {
		if ( ! isset( $_GET['na_essai'] ) ) {
			return array( 'quoi' => '', 'resultat' => null );
		}
		$cle   = 'na_essai_' . get_current_user_id();
		$essai = get_transient( $cle );
		delete_transient( $cle );
		return ( is_array( $essai ) && isset( $essai['quoi'] ) )
			? $essai : array( 'quoi' => '', 'resultat' => null );
	}

	/** Un résultat d'essai, dans le corps de la page, en mot et en couleur. */
	private static function resultat( $resultat ) {
		if ( ! is_array( $resultat ) || ! isset( $resultat['ok'] ) ) {
			return;
		}
		echo '<div class="notice inline notice-' . ( $resultat['ok'] ? 'success' : 'error' ) . '"><p>'
			. Notice_Archeomed_File::verdict( $resultat['ok'] ) . ' '
			. esc_html( $resultat['message'] ) . '</p></div>';
	}

	/** Le début d'un formulaire de réglages : chaque onglet a le sien. */
	private static function ouvrir_les_reglages() {
		echo '<form method="post" action="options.php">';
		// Le jeton porte l'adresse de la page, onglet compris : c'est elle
		// que WordPress rejoint après l'enregistrement.
		settings_fields( self::OPTION_GROUP );
	}

	/** Le bouton principal de l'onglet, et la fin de son formulaire. */
	private static function fermer_les_reglages() {
		submit_button( __( 'Enregistrer', 'notice-archeomed' ) );
		echo '</form>';
	}

	/** Le formulaire d'un essai, adressé au gestionnaire des essais. */
	private static function ouvrir_un_essai() {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="na_essai">';
		wp_nonce_field( 'na_essai' );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$onglet = self::onglet_demande();
		$essai  = $this->essai_a_montrer();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Réglages de la Chronique', 'notice-archeomed' ); ?></h1>

			<?php
			// Tous les messages, et non ceux du seul réglage de l'extension :
			// « Réglages enregistrés » est rangé par WordPress sous
			// « general », et le filtre le faisait disparaître. On ne savait
			// plus si le clic avait servi. Aucun autre appel sur cette page,
			// qui n'est pas sous « Réglages » : rien ne s'affiche deux fois.
			settings_errors();

			// L'envoi immédiat met les auteurs en attente devant le formulaire,
			// et deux dépôts simultanés peuvent alors se gêner : cela se dit
			// sur chaque onglet tant qu'il dure, et non au seul endroit où il
			// se règle.
			if ( 'immediat' === self::get( 'mode_envoi' ) ) {
				echo '<div class="notice inline notice-warning"><p><strong>'
					. esc_html__( 'L’envoi immédiat est activé.', 'notice-archeomed' ) . '</strong> '
					. esc_html__( 'Chaque auteur attend que le courriel soit parti avant de voir sa confirmation, et des dépôts simultanés peuvent ralentir le site. Revenez à l’envoi différé dès que possible : onglet « Courriel ».', 'notice-archeomed' )
					. '</p></div>';
			}
			?>

			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Parties des réglages', 'notice-archeomed' ); ?>">
				<?php foreach ( self::onglets() as $cle => $libelle ) : ?>
					<a href="<?php echo esc_url( self::url( $cle ) ); ?>"
						class="nav-tab<?php echo $cle === $onglet ? ' nav-tab-active' : ''; ?>"
						<?php echo $cle === $onglet ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $libelle ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php
			$methode = 'onglet_' . $onglet;
			$this->$methode( $essai );
			?>
		</div>
		<?php
	}

	/** Qui reçoit chaque notice, et qui le récapitulatif du jour. */
	private function onglet_destinataires( $essai ) {
		$destinataires = self::destinataires();
		?>
		<h2>Qui reçoit quoi</h2>
		<?php if ( self::destinataires_verrouilles() ) : ?>
			<p class="description">
				Liste imposée par la constante <code>NA_DEST_EMAIL</code> de
				<code>wp-config.php</code> : les adresses qui y figurent reçoivent
				tout, notices comme récapitulatifs.
			</p>
			<ul style="margin-left:1.5em;list-style:disc">
				<?php foreach ( $destinataires as $qui ) : ?>
					<li><code><?php echo esc_html( $qui['email'] ); ?></code></li>
				<?php endforeach; ?>
			</ul>
			<?php
			return;
		endif;
		self::ouvrir_les_reglages();
		?>
			<p class="description" style="max-width:46em">
				Deux sortes de courriels partent d’ici : <strong>chaque notice</strong>,
				à mesure qu’elle est déposée, avec son document et ses illustrations,
				et <strong>un récapitulatif du jour</strong>, qui liste ce qui est
				arrivé depuis la veille. Chacun choisit ce qu’il veut recevoir.
			</p>
			<table class="widefat striped" id="na-destinataires" style="max-width:48em;margin-top:10px">
				<thead>
					<tr>
						<th scope="col" style="width:55%">Adresse</th>
						<th scope="col" style="width:15%">Chaque notice</th>
						<th scope="col" style="width:20%">Récapitulatif du jour</th>
						<th scope="col" style="width:10%"><span class="screen-reader-text">Retirer</span></th>
					</tr>
				</thead>
				<tbody>
				<?php
				// Une ligne vide en plus, pour qu'on puisse ajouter sans avoir à
				// chercher le bouton du premier coup.
				$lignes   = $destinataires;
				$lignes[] = array( 'email' => '', 'notices' => true, 'recap' => false );
				foreach ( $lignes as $rang => $qui ) :
					$nom = esc_attr( self::OPTION_NAME ) . '[destinataires][' . (int) $rang . ']';
					$ici = 'na-dest-' . (int) $rang;
					?>
					<tr class="na-destinataire">
						<td>
							<label class="screen-reader-text" for="<?php echo esc_attr( $ici ); ?>">Adresse du destinataire <?php echo (int) $rang + 1; ?></label>
							<input type="email" class="regular-text" style="width:100%" id="<?php echo esc_attr( $ici ); ?>"
								name="<?php echo $nom; ?>[email]"
								value="<?php echo esc_attr( $qui['email'] ); ?>"
								placeholder="adresse@exemple.fr">
						</td>
						<td style="text-align:center">
							<label><input type="checkbox" value="1" name="<?php echo $nom; ?>[notices]"
								<?php checked( ! empty( $qui['notices'] ) ); ?>><span class="screen-reader-text">Reçoit chaque notice</span></label>
						</td>
						<td style="text-align:center">
							<label><input type="checkbox" value="1" name="<?php echo $nom; ?>[recap]"
								<?php checked( ! empty( $qui['recap'] ) ); ?>><span class="screen-reader-text">Reçoit le récapitulatif du jour</span></label>
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
					<?php echo (int) self::MAX_DESTINATAIRES; ?> au plus. Une adresse effacée est retirée à l’enregistrement.
				</span>
			</p>
			<?php
			$pour_notices = self::destinataires_de( 'notices' );
			$pour_recap   = self::destinataires_de( 'recap' );
			?>
			<p class="description">
				<?php if ( empty( $pour_notices ) ) : ?>
					<strong class="na-etat na-etat--echec"><span class="dashicons dashicons-warning" aria-hidden="true"></span> Personne ne reçoit les notices</strong> :
					les dépôts sont conservés, mais aucun ne part.
				<?php else : ?>
					Chaque notice : <code><?php echo esc_html( implode( ', ', $pour_notices ) ); ?></code>.
				<?php endif; ?>
				<br>
				<?php if ( empty( $pour_recap ) ) : ?>
					Récapitulatif du jour : personne. Il ne sera pas envoyé.
				<?php else : ?>
					Récapitulatif du jour : <code><?php echo esc_html( implode( ', ', $pour_recap ) ); ?></code>.
				<?php endif; ?>
			</p>
		<?php self::fermer_les_reglages(); ?>
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
				var rang = suivant++;
				var nom = prefixe + '[destinataires][' + rang + ']';
				var ici = 'na-dest-' + rang;
				var tr = document.createElement('tr');
				tr.className = 'na-destinataire';
				tr.innerHTML =
					'<td><label class="screen-reader-text" for="' + ici + '">Adresse du destinataire ' + (rang + 1) + '</label>' +
					'<input type="email" class="regular-text" style="width:100%" placeholder="adresse@exemple.fr" id="' + ici + '" name="' + nom + '[email]"></td>' +
					'<td style="text-align:center"><label><input type="checkbox" value="1" checked name="' + nom + '[notices]"><span class="screen-reader-text">Reçoit chaque notice</span></label></td>' +
					'<td style="text-align:center"><label><input type="checkbox" value="1" name="' + nom + '[recap]"><span class="screen-reader-text">Reçoit le récapitulatif du jour</span></label></td>' +
					'<td style="text-align:center"><button type="button" class="button-link na-oter" aria-label="Retirer ce destinataire" title="Retirer ce destinataire">✕</button></td>';
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
		<?php
	}

	/**
	 * Les normes éditoriales : ce que la revue a décidé pour ses notices,
	 * chaque décision avec ses variantes et un exemple. Le choix de la revue
	 * est coché d'avance et marqué comme tel ; une autre revue coche les
	 * siens.
	 */
	private function onglet_normes( $essai ) {
		unset( $essai );
		$nom = self::OPTION_NAME . '[' . Notice_Archeomed_Normes::CLE . ']';
		self::ouvrir_les_reglages();
		?>
		<input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[normes_presentes]" value="1">
		<p class="description" style="max-width:46em">
			Ce que la revue a décidé pour ses notices, et que le formulaire
			applique sans qu’on ait à le refaire à la main. Chaque choix de la
			revue est coché d’avance et marqué «&nbsp;choix de la revue&nbsp;».
			Les documents produits après l’enregistrement suivent les nouvelles
			normes — un fascicule ou un dossier refabriqué aussi. L’année d’une
			opération sur plusieurs années, elle, s’écrit au dépôt.
		</p>
		<?php
		foreach ( Notice_Archeomed_Normes::catalogue() as $groupe ) {
			echo '<h2>' . esc_html( $groupe['titre'] ) . '</h2>';
			if ( ! empty( $groupe['aide'] ) ) {
				echo '<p class="description" style="max-width:46em">' . esc_html( $groupe['aide'] ) . '</p>';
			}
			echo '<table class="form-table" role="presentation">';
			foreach ( $groupe['normes'] as $cle => $norme ) {
				$valeur = Notice_Archeomed_Normes::valeur( $cle );
				$id     = 'na_norme_' . $cle;
				echo '<tr><th scope="row">';
				if ( isset( $norme['min'] ) ) {
					echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $norme['libelle'] ) . '</label>';
				} else {
					echo esc_html( $norme['libelle'] );
				}
				echo '</th><td>';
				if ( isset( $norme['choix'] ) ) {
					echo '<fieldset><legend class="screen-reader-text">' . esc_html( $norme['libelle'] ) . '</legend>';
					foreach ( $norme['choix'] as $choix => $texte ) {
						echo '<label style="display:block;margin:0 0 6px">'
							. '<input type="radio" name="' . esc_attr( $nom . '[' . $cle . ']' ) . '" value="' . esc_attr( $choix ) . '"'
							. checked( $valeur, $choix, false ) . '> '
							. esc_html( $texte[0] )
							. ( '' !== $texte[1] ? ' <span style="margin-left:.4em;padding:1px 8px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;font-family:Georgia,serif">'
								. wp_kses( $texte[1], array( 'em' => array(), 'sup' => array(), 'span' => array( 'style' => array() ) ) ) . '</span>' : '' )
							. ( $norme['defaut'] === $choix ? ' <span class="description">— choix de la revue</span>' : '' )
							. '</label>';
					}
					echo '</fieldset>';
				} elseif ( isset( $norme['cases'] ) ) {
					echo '<fieldset><legend class="screen-reader-text">' . esc_html( $norme['libelle'] ) . '</legend>';
					foreach ( $norme['cases'] as $case => $texte ) {
						echo '<label style="display:block;margin:0 0 6px">'
							. '<input type="checkbox" name="' . esc_attr( $nom . '[' . $cle . '][]' ) . '" value="' . esc_attr( $case ) . '"'
							. checked( in_array( $case, (array) $valeur, true ), true, false ) . '> '
							. esc_html( $texte ) . '</label>';
					}
					echo '</fieldset>';
				} else {
					echo '<input type="number" id="' . esc_attr( $id ) . '" class="small-text" name="' . esc_attr( $nom . '[' . $cle . ']' ) . '"'
						. ' min="' . (int) $norme['min'] . '" max="' . (int) $norme['max'] . '" value="' . esc_attr( $valeur ) . '"> '
						. esc_html( $norme['unite'] )
						. ( (int) $norme['defaut'] !== (int) $valeur
							? ' <span class="description">— la revue : ' . esc_html( $norme['defaut'] . ' ' . $norme['unite'] ) . '</span>' : '' );
				}
				if ( ! empty( $norme['aide'] ) ) {
					echo '<p class="description">' . esc_html( $norme['aide'] ) . '</p>';
				}
				echo '</td></tr>';
			}
			echo '</table>';
		}
		submit_button( __( 'Enregistrer', 'notice-archeomed' ), 'primary', 'submit', false );
		echo ' ';
		// Rétablir est un geste secondaire, et il se confirme : il efface les
		// choix de la page entière.
		echo '<button type="submit" class="button" name="' . esc_attr( self::OPTION_NAME ) . '[normes_retablir]" value="1"'
			. ' onclick="return confirm(\'Rétablir toutes les normes de la revue ? Les choix faits ici seront oubliés.\');">'
			. esc_html__( 'Rétablir les normes de la revue', 'notice-archeomed' ) . '</button>';
		echo '</form>';
	}

	/**
	 * La correspondance des blocs avec les styles Métopes : un menu par
	 * bloc, qui ne propose que les styles du bon type que la feuille
	 * installée porte. La correspondance d'origine est marquée ; un style
	 * enregistré qui aurait disparu de la feuille le dit.
	 */
	private function onglet_styles( $essai ) {
		unset( $essai );
		$nom     = self::OPTION_NAME . '[' . Notice_Archeomed_Styles::CLE . ']';
		$feuille = Notice_Archeomed_Styles::styles_de_la_feuille();
		self::ouvrir_les_reglages();
		?>
		<input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[styles_presents]" value="1">
		<p class="description" style="max-width:46em">
			Le style Métopes que reçoit chaque bloc de la notice dans le document
			Word. Chaque menu ne propose que les styles de la feuille installée,
			et du bon type — style de paragraphe ou style de caractère. La
			correspondance d’origine est marquée «&nbsp;d’origine&nbsp;». Les
			documents produits après l’enregistrement suivent la nouvelle
			correspondance, comme les blocs d’index Pactols, qui nomment le
			style du paragraphe où ils se collent.
		</p>
		<?php
		if ( empty( $feuille['paragraphe'] ) ) {
			echo '<div class="notice notice-warning inline"><p>'
				. esc_html__( 'La feuille de styles n’a pas pu être lue : les correspondances ne peuvent pas se choisir. Voyez l’onglet « Feuille de styles ».', 'notice-archeomed' )
				. '</p></div>';
		}
		foreach ( Notice_Archeomed_Styles::catalogue() as $groupe ) {
			echo '<h2>' . esc_html( $groupe['titre'] ) . '</h2>';
			echo '<table class="form-table" role="presentation">';
			foreach ( $groupe['blocs'] as $cle => $bloc ) {
				$actuel = Notice_Archeomed_Styles::de( $cle );
				$id     = 'na_style_' . $cle;
				$liste  = $feuille[ $bloc['type'] ];
				echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $bloc['libelle'] ) . '</label></th><td>';
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $nom . '[' . $cle . ']' ) . '" style="max-width:26em">';
				$vide = Notice_Archeomed_Styles::libelle_du_vide( $bloc );
				if ( '' !== $vide ) {
					echo '<option value=""' . selected( $actuel, '', false ) . '>'
						. esc_html( '— ' . $vide . ' —' . ( '' === $bloc['defaut'] ? ' (d’origine)' : '' ) ) . '</option>';
				}
				if ( '' !== $actuel && ! in_array( $actuel, $liste, true ) ) {
					echo '<option value="' . esc_attr( $actuel ) . '" selected>' . esc_html( $actuel . ' — absent de la feuille' ) . '</option>';
				}
				foreach ( $liste as $style ) {
					echo '<option value="' . esc_attr( $style ) . '"' . selected( $actuel, $style, false ) . '>'
						. esc_html( $style . ( $style === $bloc['defaut'] ? ' (d’origine)' : '' ) ) . '</option>';
				}
				echo '</select> <span class="description">'
					. esc_html( 'caractere' === $bloc['type'] ? 'style de caractère' : 'style de paragraphe' ) . '</span>';
				if ( '' !== $actuel && ! empty( $liste ) && ! in_array( $actuel, $liste, true ) ) {
					echo '<p class="description" style="color:#b32d2e">' . Notice_Archeomed_File::verdict( false ) . ' '
						. esc_html__( 'Ce style n’est plus dans la feuille installée : le bloc sort en Normal. Choisissez-en un autre.', 'notice-archeomed' ) . '</p>';
				} elseif ( $actuel !== $bloc['defaut'] ) {
					echo '<p class="description">' . esc_html( 'D’origine : ' . ( '' === $bloc['defaut'] ? $vide : $bloc['defaut'] ) ) . '</p>';
				}
				echo '</td></tr>';
			}
			echo '</table>';
		}
		submit_button( __( 'Enregistrer', 'notice-archeomed' ), 'primary', 'submit', false );
		echo ' ';
		echo '<button type="submit" class="button" name="' . esc_attr( self::OPTION_NAME ) . '[styles_retablir]" value="1"'
			. ' onclick="return confirm(\'Rétablir toutes les correspondances d’origine ? Les choix faits ici seront oubliés.\');">'
			. esc_html__( 'Rétablir les correspondances d’origine', 'notice-archeomed' ) . '</button>';
		echo '</form>';
	}

	/**
	 * Le contrôle automatique de l'accessibilité du formulaire, et la trace
	 * de l'audit RGAA : la classe qui les tient les affiche.
	 */
	private function onglet_accessibilite( $essai ) {
		unset( $essai );
		Notice_Archeomed_Accessibilite::onglet();
	}

	/** Le numéro en préparation et ce qu'on fait des illustrations. */
	private function onglet_numero( $essai ) {
		self::ouvrir_les_reglages();
		?>
		<h2>Iconographie du numéro</h2>
		<p class="description" style="max-width:46em">
			Ce qui gouverne le dossier <code>icono</code> d’un numéro : le nom
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
						Jetons admis :
						<?php
						$jetons = array();
						foreach ( self::JETONS_DE_NOM as $jeton => $quoi ) {
							$jetons[] = '<code>' . esc_html( $jeton ) . '</code> ' . esc_html( $quoi );
						}
						echo wp_kses_post( implode( ' ; ', $jetons ) );
						?>.
						<br>
						Ce qui n’est pas un jeton est recopié tel quel, puis tout le nom
						passe à la règle : pas d’accent, pas d’espace, le souligné pour
						seul séparateur. <strong><code>{n}</code> est obligatoire</strong> :
						sans lui, deux illustrations d’une même notice porteraient le même nom.
						<br>
						Exemple : <code><?php echo esc_html( self::exemple_de_nom() ); ?></code>
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
						Elle n’est pas destinée à l’impression : elle sert à voir la figure
						à sa place dans le document. Le poids compte donc autant que la
						largeur — cent figures liées dans un fascicule, et le dossier
						devient intransportable.
						<br>
						<strong>La qualité ne se règle pas</strong> : le plugin part de la
						meilleure et ne la baisse que si le fichier dépasse le plafond,
						par paliers, en s’arrêtant au premier qui tient. La tolérance dit
						jusqu’où descendre sans insister :
						<?php
						$plafond = (int) self::get( 'br_poids' );
						$bas     = (int) round( $plafond * ( 100 - (int) self::get( 'br_tolerance' ) ) / 100 );
						echo esc_html( sprintf( 'on s’arrête dès que le fichier tient entre %d et %d Ko.',
							(int) $bas, (int) $plafond ) );
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
						10 × 15 cm à 300 dpi au minimum.
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
						1200 dpi pour un trait, et <strong>le convertir en JPEG lui ôte
						justement ce qu’on lui demande</strong> : décoché, l’original est
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
		<?php
		self::fermer_les_reglages();
	}

	/** La protection du formulaire, et où il paraît. */
	private function onglet_formulaire( $essai ) {
		$prot          = self::get( 'protection' );
		$prot_locked   = self::is_locked( 'protection' );
		$secret_locked = self::is_locked( 'turnstile_secret' );
		$site_locked   = self::is_locked( 'turnstile_site' );
		$options       = get_option( self::OPTION_NAME, array() );
		$has_secret    = '' !== trim( self::get( 'turnstile_secret' ) );
		// Les clés Turnstile ne concernent que qui a choisi Turnstile : elles
		// se cachent sinon, sans quitter le formulaire, pour que
		// l'enregistrement les garde telles quelles.
		$turnstile_sert = in_array( $prot, array( 'turnstile', 'les_deux' ), true );
		?>
		<h2>Où paraît le formulaire</h2>
		<p>
			<label for="na-code-court">Code court à coller dans la page qui doit accueillir le formulaire :</label><br>
			<input type="text" id="na-code-court" class="regular-text code" readonly
				value="[notice_archeomed_pactols]" onfocus="this.select()" onclick="this.select()">
		</p>

		<?php self::ouvrir_les_reglages(); ?>
		<h2>Protection anti-robot</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Comment le formulaire se protège</th>
				<td>
					<fieldset>
						<legend class="screen-reader-text">Comment le formulaire se protège</legend>
						<label style="display:block;margin-bottom:6px">
							<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[protection]"
								value="locale" <?php checked( 'locale' === $prot ); ?>
								<?php disabled( $prot_locked ); ?>>
							Pièce de puzzle à glisser — fonctionne partout
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
					</fieldset>
					<p class="description">
						<?php if ( $prot_locked ) : ?>
							Valeur imposée par la constante <code>NA_PROTECTION</code>.
						<?php else : ?>
							Turnstile suppose que <strong>le serveur</strong> puisse joindre
							Cloudflare. Derrière un proxy filtrant — hébergement institutionnel —
							il ne le peut pas : la vérification échoue, le plugin laisse
							passer pour ne pas perdre de notice, et la protection n’en est plus
							une. La pièce de puzzle se vérifie sur place et fonctionne partout.
						<?php endif; ?>
					</p>
				</td>
			</tr>
		</table>

		<div id="na-turnstile"<?php echo $turnstile_sert ? '' : ' hidden'; ?>>
			<h2>Cloudflare Turnstile</h2>
			<p>Ces clés se créent gratuitement sur <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">le tableau de bord Cloudflare</a>, rubrique Turnstile. La clé de site est publique ; la clé secrète ne doit jamais être diffusée.</p>
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
							<input type="text" id="na_secret" class="regular-text" value="(définie dans wp-config.php)" disabled>
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
		</div>
		<?php self::fermer_les_reglages(); ?>

		<div id="na-turnstile-essai"<?php echo $turnstile_sert ? '' : ' hidden'; ?>>
			<h2>Vérifier la clé secrète</h2>
			<p>Cet essai demande à Cloudflare si la clé secrète <strong>enregistrée</strong> est reconnue, et dit du même coup si le serveur peut le joindre. Il n’envoie aucune notice.</p>
			<?php self::ouvrir_un_essai(); ?>
				<button type="submit" class="button" name="na_quoi" value="turnstile">Tester la clé secrète</button>
			</form>
			<?php
			if ( 'turnstile' === $essai['quoi'] ) {
				self::resultat( $essai['resultat'] );
			}
			?>
		</div>
		<script>
		(function () {
			var radios = document.querySelectorAll('input[name="<?php echo esc_js( self::OPTION_NAME ); ?>[protection]"]');
			var blocs = [document.getElementById('na-turnstile'), document.getElementById('na-turnstile-essai')];
			radios.forEach(function (r) {
				r.addEventListener('change', function () {
					var sert = r.checked && r.value !== 'locale';
					if (!r.checked) { return; }
					blocs.forEach(function (b) { if (b) { b.hidden = !sert; } });
				});
			});
		}());
		</script>
		<?php
	}

	/** Par où partent les courriels, et les essais qui l'éprouvent. */
	private function onglet_courriel( $essai ) {
		$mode        = self::get( 'envoi_mode' );
		$rythme      = self::get( 'mode_envoi' );
		$mode_locked = self::is_locked( 'mode_envoi' );
		// Le rythme d'envoi vivait sous « Diagnostic », qui se lit « rien à
		// régler ici » : c'est pourtant lui qui garantit que deux dépôts
		// simultanés ne se gênent pas. Il ouvre l'onglet, avec son propre
		// bouton.
		self::ouvrir_les_reglages();
		?>
		<h2>Quand les courriels partent</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Expédition des courriels</th>
				<td>
					<fieldset>
						<legend class="screen-reader-text">Expédition des courriels</legend>
						<label style="display:block;margin-bottom:6px">
							<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[mode_envoi]"
								value="differe" <?php checked( 'immediat' !== $rythme ); ?>
								<?php disabled( $mode_locked ); ?>>
							Différée <strong>(recommandé)</strong> — le formulaire rend la main aussitôt
						</label>
						<label style="display:block">
							<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[mode_envoi]"
								value="immediat" <?php checked( 'immediat' === $rythme ); ?>
								<?php disabled( $mode_locked ); ?>>
							Immédiate — le formulaire attend que le courriel soit parti
						</label>
					</fieldset>
					<p class="description">
						<?php if ( $mode_locked ) : ?>
							Valeur imposée par la constante <code>NA_MODE_ENVOI</code>.
						<?php else : ?>
							En différé, la notice est inscrite puis expédiée par le planificateur de
							WordPress (WP-Cron) : plusieurs personnes peuvent déposer en même temps
							sans que le site ralentisse, et un courriel qui échoue est retenté de
							lui-même. Ne passer en immédiat que si le planificateur est désactivé sur cet
							hébergement (<code>DISABLE_WP_CRON</code>) et qu’aucune tâche système ne
							le remplace — la liste « Chronique ▸ Notices reçues » le dira en
							s’allongeant. Dans les deux cas, la notice est d’abord inscrite dans
							cette liste, avec ses fichiers : un courriel refusé ne la perd pas, elle
							y attend qu’on la relance.
						<?php endif; ?>
					</p>
				</td>
			</tr>
		</table>
		<?php self::fermer_les_reglages(); ?>

		<hr>

		<?php self::ouvrir_les_reglages(); ?>
		<h2>Acheminement du courriel</h2>
		<p class="description" style="max-width:46em">
			Tout le plugin en dépend : une notice qui ne part pas reste en file, puis
			échoue. Si le serveur répond <em>« Impossible d’instancier la fonction
			mail »</em>, c’est que <code>mail()</code> n’est pas configurée sur cet
			hébergement — un relais SMTP contourne la question.
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Par quoi les courriels passent</th>
				<td>
					<fieldset>
						<legend class="screen-reader-text">Par quoi les courriels passent</legend>
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
					</fieldset>
				</td>
			</tr>
			<tr data-si="smtp">
				<th scope="row"><label for="na_smtp_hote">Relais SMTP</label></th>
				<td>
					<input type="text" id="na_smtp_hote" class="regular-text"
						name="<?php echo esc_attr( self::OPTION_NAME ); ?>[smtp_hote]"
						value="<?php echo esc_attr( self::get( 'smtp_hote' ) ); ?>"
						placeholder="smtp.exemple.fr">
					<label for="na_smtp_port" style="margin-left:6px">port</label>
					<input type="number" id="na_smtp_port" min="1" max="65535" style="width:6em"
						name="<?php echo esc_attr( self::OPTION_NAME ); ?>[smtp_port]"
						value="<?php echo (int) self::get( 'smtp_port' ); ?>">
					<?php $chif = self::get( 'smtp_chiffrement' ); ?>
					<label for="na_smtp_chiffrement" style="margin-left:6px">chiffrement</label>
					<select id="na_smtp_chiffrement" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[smtp_chiffrement]">
						<option value="tls" <?php selected( 'tls', $chif ); ?>>STARTTLS (587)</option>
						<option value="ssl" <?php selected( 'ssl', $chif ); ?>>SSL (465)</option>
						<option value="aucun" <?php selected( 'aucun', $chif ); ?>>aucun chiffrement (25)</option>
					</select>
				</td>
			</tr>
			<tr data-si="smtp">
				<th scope="row"><label for="na_smtp_user">Identifiant</label></th>
				<td>
					<input type="text" id="na_smtp_user" class="regular-text"
						name="<?php echo esc_attr( self::OPTION_NAME ); ?>[smtp_utilisateur]"
						value="<?php echo esc_attr( self::get( 'smtp_utilisateur' ) ); ?>"
						placeholder="laissez vide si le relais n’en demande pas">
					<p class="description">Un relais institutionnel accepte souvent ses propres machines sans identifiant.</p>
				</td>
			</tr>
			<tr data-si="smtp">
				<th scope="row"><label for="na_smtp_mdp">Mot de passe</label></th>
				<td>
					<input type="password" id="na_smtp_mdp" class="regular-text" autocomplete="new-password"
						name="<?php echo esc_attr( self::OPTION_NAME ); ?>[smtp_motdepasse]"
						placeholder="<?php echo '' !== (string) self::get( 'smtp_motdepasse' ) ? 'enregistré — laissez vide pour le garder' : 'aucun'; ?>">
					<?php if ( '' !== (string) self::get( 'smtp_motdepasse' ) ) : ?>
						<label style="margin-left:8px"><input type="checkbox" value="1"
							name="<?php echo esc_attr( self::OPTION_NAME ); ?>[effacer_motdepasse]"> Effacer le mot de passe enregistré</label>
					<?php endif; ?>
				</td>
			</tr>
			<tr data-si="smtp">
				<th scope="row"><label for="na_smtp_from">Adresse d’expédition</label></th>
				<td>
					<input type="email" id="na_smtp_from" class="regular-text"
						name="<?php echo esc_attr( self::OPTION_NAME ); ?>[smtp_expediteur]"
						value="<?php echo esc_attr( self::get( 'smtp_expediteur' ) ); ?>"
						placeholder="notices@exemple.fr">
					<p class="description">
						Elle doit appartenir au domaine que le relais accepte, sans quoi il
						refusera le message pour usurpation.
					</p>
				</td>
			</tr>
			<tr data-si="smtp">
				<th scope="row"><label for="na_smtp_nom">Nom affiché</label></th>
				<td>
					<input type="text" id="na_smtp_nom" class="regular-text"
						name="<?php echo esc_attr( self::OPTION_NAME ); ?>[smtp_nom]"
						value="<?php echo esc_attr( self::get( 'smtp_nom' ) ); ?>"
						placeholder="Archéologie médiévale">
					<p class="description">Le nom que le destinataire voit à côté de l’adresse d’expédition.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="na-poids-courriel">Poids maximal d’un courriel</label></th>
				<td>
					<input type="number" id="na-poids-courriel" min="1" max="100" style="width:6em"
						name="<?php echo esc_attr( self::OPTION_NAME ); ?>[poids_courriel]"
						value="<?php echo esc_attr( (int) self::get( 'poids_courriel' ) ); ?>"> Mo
					<p class="description">
						Au-delà, le serveur de courriel refuse le message entier. Le plafond vaut
						pour tout ce que le courriel porte : le document, puis chaque figure dans
						son ordre — en version allégée, qui pèse peu, ou en original faute d’en
						avoir une. Une figure qui ne tient plus reste sur le site, et le courriel
						la nomme, avec le lien de la notice. Une pièce jointe grossit d’un tiers en
						voyageant. L’« essai de poids », plus bas, mesure ce que le serveur accepte.
					</p>
				</td>
			</tr>
		</table>
		<?php self::fermer_les_reglages(); ?>
		<script>
		// Les champs du relais restaient offerts quand « mail() » était coché :
		// on les remplissait pour rien, en croyant qu'ils servaient. Ils se
		// cachent tant que le relais n'est pas choisi ; sans script, tout reste
		// visible.
		(function () {
			var radios = document.querySelectorAll('input[name="<?php echo esc_js( self::OPTION_NAME ); ?>[envoi_mode]"]');
			var lignes = document.querySelectorAll('tr[data-si="smtp"]');
			function maj() {
				var smtp = false;
				radios.forEach(function (r) { if (r.checked && r.value === 'smtp') { smtp = true; } });
				lignes.forEach(function (l) { l.hidden = !smtp; });
			}
			radios.forEach(function (r) { r.addEventListener('change', maj); });
			maj();
		}());
		</script>

		<hr>

		<h2>Essais d’envoi</h2>
		<p style="max-width:46em">Chacun envoie un courriel et rapporte ce que le
		serveur a répondu — sans déposer de notice ni attendre le planificateur.
		<strong>Enregistrez d’abord les réglages ci-dessus</strong> : les essais
		se font avec ceux qui sont enregistrés.</p>
		<?php self::ouvrir_un_essai(); ?>
			<p>
				<label for="na-essai-vers">Adresse d’essai</label><br>
				<input type="email" id="na-essai-vers" name="na_essai_vers" class="regular-text"
					value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
			</p>
			<p>
				<button type="submit" class="button" name="na_quoi" value="envoi">Envoyer un courriel d’essai</button>
			</p>
			<p>
				<label for="na-poids-essai">Pièce jointe de</label>
				<select id="na-poids-essai" name="na_poids_essai">
					<?php foreach ( Notice_Archeomed_Pactols::TAILLES_ESSAI as $mo ) : ?>
						<option value="<?php echo (int) $mo; ?>"><?php echo (int) $mo; ?> Mo</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button" name="na_quoi" value="poids">Essai de poids</button>
				<span class="description" style="margin-left:8px">
					Un seul message, avec une pièce jointe de la taille choisie : il dit
					jusqu’où le serveur de courriel accepte, sans rien d’autre qui varie.
				</span>
			</p>
			<p>
				<button type="submit" class="button" name="na_quoi" value="etapes">Refaire l’envoi d’une notice, étape par étape</button>
				<span class="description" style="margin-left:8px">
					Vers les destinataires des notices. Quand un essai simple part mais
					qu’une notice est refusée, celui-ci dit lequel des écarts en est la
					cause : plusieurs destinataires, un en-tête Reply-To, une pièce jointe.
				</span>
			</p>
		</form>
		<?php
		if ( in_array( $essai['quoi'], array( 'envoi', 'poids' ), true ) ) {
			self::resultat( $essai['resultat'] );
		}
		if ( 'etapes' === $essai['quoi'] && is_array( $essai['resultat'] ) ) :
			?>
			<table class="widefat striped" style="max-width:52em;margin-bottom:12px">
				<tbody>
				<?php foreach ( $essai['resultat'] as $e ) : ?>
					<tr>
						<th scope="row" style="width:34%"><?php echo esc_html( $e['etape'] ); ?></th>
						<td><?php echo Notice_Archeomed_File::verdict( ! empty( $e['ok'] ) ); ?>
							<?php echo esc_html( $e['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">La première ligne marquée « échec » nomme ce qui fait échouer l’envoi.</p>
			<?php
		endif;

		$poids_essayes = get_option( 'na_essais_de_poids', array() );
		if ( ! is_array( $poids_essayes ) || empty( $poids_essayes ) ) {
			return;
		}
		$passe_max = 0;
		$refus_min = 0;
		foreach ( $poids_essayes as $mo => $e ) {
			if ( ! empty( $e['ok'] ) ) {
				$passe_max = max( $passe_max, (int) $mo );
			} elseif ( ! $refus_min || (int) $mo < $refus_min ) {
				$refus_min = (int) $mo;
			}
		}
		?>
		<h3>Essais de poids déjà faits</h3>
		<table class="widefat striped" style="max-width:52em;margin-bottom:6px">
			<thead><tr><th scope="col">Pièce jointe</th><th scope="col">Réponse du serveur</th><th scope="col">Le</th></tr></thead>
			<tbody>
			<?php foreach ( $poids_essayes as $mo => $e ) : ?>
				<tr>
					<td><strong><?php echo (int) $mo; ?> Mo</strong></td>
					<td><?php echo ! empty( $e['ok'] )
						? '<span class="na-etat na-etat--ok"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> accepté</span>'
						: '<span class="na-etat na-etat--echec"><span class="dashicons dashicons-warning" aria-hidden="true"></span> refusé</span>'; ?></td>
					<td><?php echo esc_html( Notice_Archeomed_File::date_lisible( $e['quand'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description" style="max-width:52em">
			<?php
			if ( $passe_max && $refus_min && $passe_max < $refus_min ) {
				echo esc_html( sprintf( 'La limite du serveur se situe entre %d et %d Mo de pièces jointes. '
					. 'C’est le chiffre à donner à l’hébergeur ; et c’est d’après lui que se règle '
					. '« Poids maximal d’un courriel », plus haut — en comptant un tiers de plus pour l’encodage.',
					$passe_max, $refus_min ) );
			} elseif ( $passe_max && ! $refus_min ) {
				echo esc_html( sprintf( 'Le serveur accepte au moins %d Mo de pièces jointes. '
					. 'Si le message arrive bien, « Poids maximal d’un courriel » peut être relevé.', $passe_max ) );
			} elseif ( $refus_min && ! $passe_max ) {
				echo esc_html( sprintf( 'Le serveur refuse dès %d Mo de pièces jointes : essayez une taille plus petite pour borner la limite.', $refus_min ) );
			} else {
				echo esc_html( 'Les essais se contredisent — un poids plus lourd accepté après un plus léger refusé : '
					. 'la cause n’est peut-être pas le poids. Refaites-les à quelques minutes d’intervalle.' );
			}
			?>
		</p>
		<?php
	}

	/** La feuille de styles Métopes en service, et comment la remplacer. */
	private function onglet_feuille( $essai ) {
		$messages = array(
			'posee'    => array( 'success', 'La nouvelle feuille de styles est en service.' ),
			'retiree'  => array( 'success', 'La feuille déposée a été retirée : celle livrée avec le plugin reprend la main.' ),
			'vide'     => array( 'error', 'Aucun fichier n’a été reçu.' ),
			'format'   => array( 'error', 'Seuls les fichiers .docx et .rtf sont acceptés.' ),
			'invalide' => array( 'error', 'Ce document ne porte pas de feuille de styles : ce n’est pas un gabarit Métopes. Rien n’a été changé.' ),
			'ecriture' => array( 'error', 'Le fichier n’a pas pu être écrit dans le dossier des téléversements.' ),
		);
		$retour = isset( $_GET['na_feuille'] ) ? sanitize_key( wp_unslash( $_GET['na_feuille'] ) ) : '';
		$feuille = Notice_Archeomed_Pactols::etat_de_la_feuille( 'docx' );
		?>
		<h2>Feuille de styles Métopes</h2>
		<?php
		if ( isset( $messages[ $retour ] ) ) {
			echo '<div class="notice inline notice-' . esc_attr( $messages[ $retour ][0] ) . '"><p>'
				. esc_html( $messages[ $retour ][1] ) . '</p></div>';
		}
		?>
		<p class="description" style="max-width:46em">
			<a href="https://www.metopes.fr" target="_blank" rel="noopener">Métopes</a> —
			chaîne d’édition XML créée par le Pôle document numérique et l’infrastructure
			Métopes de l’université de Caen Normandie. Sa feuille de styles donne leur
			forme aux notices et aux fascicules.
		</p>
		<table class="widefat striped" style="max-width:46em">
			<tbody>
				<tr><th scope="row" style="width:36%">En service</th>
					<td><?php echo $feuille['deposee']
						? 'la feuille déposée depuis cette page'
						: 'la feuille livrée avec le plugin'; ?></td></tr>
				<?php if ( $feuille['presente'] ) : ?>
					<tr><th scope="row">Date du document</th>
						<td><?php echo '' !== $feuille['modifiee']
							? esc_html( mysql2date( 'j F Y', str_replace( array( 'T', 'Z' ), array( ' ', '' ), $feuille['modifiee'] ) ) )
							: '<span class="description">non renseignée dans le fichier</span>'; ?></td></tr>
					<tr><th scope="row">Posée sur le serveur le</th>
						<td><?php echo esc_html( Notice_Archeomed_File::date_lisible( wp_date( 'Y-m-d H:i:s', (int) $feuille['posee'] ) ) ); ?></td></tr>
					<tr><th scope="row">Styles déclarés</th>
						<td><?php echo (int) $feuille['styles'] > 0
							? (int) $feuille['styles'] : '<span class="description">illisibles sur cet hébergement (il y manque ZipArchive)</span>'; ?></td></tr>
					<tr><th scope="row">Poids</th>
						<td><?php echo esc_html( size_format( $feuille['poids'] ) ); ?></td></tr>
				<?php else : ?>
					<tr><td colspan="2"><span class="na-etat na-etat--echec"><span class="dashicons dashicons-warning" aria-hidden="true"></span> Aucune feuille de styles trouvée.</span>
					Les notices partiront sans document mis en forme.</td></tr>
				<?php endif; ?>
			</tbody>
		</table>

		<h3>Remplacer la feuille</h3>
		<p class="description" style="max-width:46em">
			Quand Métopes fait évoluer sa feuille, déposez ici le nouveau gabarit
			(<code>.docx</code>) : inutile d’aller dans le dossier du plugin. Les
			styles sont reconnus par leur nom, aucune modification du code n’est
			nécessaire. La feuille déposée vit dans les téléversements du site : une
			mise à jour de l’extension ne l’efface pas.
		</p>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="na_feuille">
			<?php wp_nonce_field( 'na_feuille' ); ?>
			<p>
				<label for="na-feuille-fichier">Nouveau gabarit</label><br>
				<input type="file" id="na-feuille-fichier" name="na_feuille" accept=".docx,.rtf">
				<?php submit_button( 'Déposer cette feuille', 'primary', '', false ); ?>
			</p>
			<?php if ( $feuille['deposee'] ) : ?>
				<p>
					<label><input type="checkbox" name="na_feuille_retirer" value="1">
					Retirer la feuille déposée et revenir à celle du plugin</label>
					<?php submit_button( 'Appliquer', 'secondary small', '', false ); ?>
				</p>
			<?php endif; ?>
		</form>

		<div class="notice inline notice-info" style="max-width:46em"><p>
			<strong>Après chaque nouveau gabarit, vérifiez un fascicule.</strong>
			Téléchargez celui d’une rubrique depuis la liste des notices, ouvrez-le
			dans Word, et regardez si les titres, les auteurs et les légendes ont
			gardé leur mise en forme. Si Métopes a renommé un style, rien ne le
			signale : le paragraphe sort simplement en « Normal ». Dans ce cas,
			revenez à la feuille du plugin (case ci-dessus) et prévenez la personne
			qui assure la maintenance.
		</p></div>
		<details style="max-width:46em;margin-top:10px">
			<summary>Pour la maintenance</summary>
			<p>
				Depuis le dépôt du plugin, passez <code>./verifier-les-styles</code>
				après tout changement de <code>modele-metopes.docx</code> : il liste
				les styles qu’emploie le code et que le gabarit ne déclare plus.
			</p>
		</details>
		<?php
	}

	/** D'où viennent les nouvelles versions, et si le site peut les joindre. */
	private function onglet_maj( $essai ) {
		// L'instance créée au chargement du plugin, et non une seconde : en
		// fabriquer une ici enregistrerait ses filtres une deuxième fois.
		global $notice_archeomed_maj;
		self::ouvrir_les_reglages();
		?>
		<h2>Mises à jour</h2>
		<p class="description" style="max-width:46em">
			Le plugin ne vit pas dans le répertoire de WordPress : sans cela, chaque
			correction se téléverse à la main. Renseignez le dépôt, et les nouvelles
			versions paraîtront dans <strong>Extensions</strong> comme pour n’importe
			quelle autre. Testez d’abord la sortie vers GitHub, plus bas : derrière
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
		</table>
		<?php self::fermer_les_reglages(); ?>

		<hr>

		<h2>État</h2>
		<?php
		$release   = ( $notice_archeomed_maj instanceof Notice_Archeomed_MiseAJour )
			? $notice_archeomed_maj->derniere_release() : array();
		$installee = ( $notice_archeomed_maj instanceof Notice_Archeomed_MiseAJour )
			? $notice_archeomed_maj->version() : '';
		if ( 'maj' === $essai['quoi'] ) {
			self::resultat( $essai['resultat'] );
		}
		?>
		<p>Version installée : <code><?php echo esc_html( $installee ); ?></code></p>
		<?php if ( ! empty( $release['echec'] ) ) : ?>
			<p><span class="na-etat na-etat--echec"><span class="dashicons dashicons-warning" aria-hidden="true"></span> <?php echo esc_html( $release['echec'] ); ?></span></p>
		<?php elseif ( empty( $release['version'] ) ) : ?>
			<p class="description">Aucune version publiée n’a pu être lue — dépôt non renseigné, ou mécanisme éteint.</p>
		<?php else : ?>
			<p>Dernière version publiée : <code><?php echo esc_html( $release['version'] ); ?></code></p>
			<?php if ( empty( $release['propre'] ) ) : ?>
				<p><span class="na-etat na-etat--echec"><span class="dashicons dashicons-warning" aria-hidden="true"></span> La release ne porte pas d’archive <code>notice-archeomed.zip</code>.</span>
				Celle que GitHub fabrique seul s’ouvre sur le mauvais dossier et installerait le plugin
				à côté de lui-même : la mise à jour n’est donc pas proposée. Joignez à la release
				l’archive produite par <code>./empaqueter</code>.</p>
			<?php elseif ( version_compare( $release['version'], $installee, '>' ) ) : ?>
				<p><span class="na-etat na-etat--ok"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> Une mise à jour est disponible</span> — elle paraît dans « Extensions ».</p>
			<?php else : ?>
				<p class="description">Le site est à jour.</p>
			<?php endif; ?>
		<?php endif; ?>
		<p style="margin:8px 0">
			<a class="button" href="<?php
				echo esc_url( wp_nonce_url(
					add_query_arg( array( 'action' => 'na_essai', 'na_quoi' => 'maj' ), admin_url( 'admin-post.php' ) ),
					'na_essai' ) ); ?>">Chercher une mise à jour maintenant</a>
			<span class="description" style="margin-left:8px">
				GitHub n’est interrogé qu’une fois toutes les six heures, et
				WordPress garde sa propre réserve jusqu’à douze heures. Ce
				bouton vide les deux.
			</span>
		</p>

		<h2>Sortie vers GitHub</h2>
		<p style="max-width:46em">Cet essai dit si le serveur peut joindre GitHub — l’API
		qui annonce les versions, et l’hôte qui sert les fichiers. C’est ce dont
		dépend une mise à jour proposée dans « Extensions » plutôt que téléversée
		à la main. Le même proxy empêche déjà de joindre Cloudflare.</p>
		<?php self::ouvrir_un_essai(); ?>
			<button type="submit" class="button" name="na_quoi" value="github">Tester l’accès à GitHub</button>
		</form>
		<?php
		if ( 'github' === $essai['quoi'] ) {
			self::resultat( $essai['resultat'] );
		}
	}

	/** Ce que l'hébergement offre : un constat, pour dépanner. */
	private function onglet_diagnostic( $essai ) {
		?>
		<h2>Ce que l’hébergement offre</h2>
		<p>Ce tableau ne change rien : il regarde. Chaque ligne dit ce qu’il
		faut, ce qu’il y a, et ce qui manque — de quoi savoir sur quoi
		compter, et quoi demander à l’hébergeur.</p>
		<?php
		$etat    = self::etat_du_serveur();
		$manques = array_values( array_filter( $etat, function ( $l ) {
			return 'manque' === $l['etat'];
		} ) );
		$mots    = array(
			'ok'     => array( 'ok', 'dashicons-yes-alt', 'suffit' ),
			'manque' => array( 'echec', 'dashicons-warning', 'manque' ),
			'note'   => array( 'attente', 'dashicons-info', 'à noter' ),
		);
		?>
		<table class="widefat striped" style="max-width:66em">
			<thead>
				<tr>
					<th scope="col" style="width:18%">Ce qu’il faut</th>
					<th scope="col" style="width:11%">Requis</th>
					<th scope="col" style="width:13%">Constaté</th>
					<th scope="col" style="width:11%">Verdict</th>
					<th scope="col" style="width:12%">Écart</th>
					<th scope="col">Pourquoi</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $etat as $l ) : ?>
				<?php $mot = isset( $mots[ $l['etat'] ] ) ? $mots[ $l['etat'] ] : $mots['note']; ?>
				<tr>
					<th scope="row"><?php echo esc_html( $l['quoi'] ); ?></th>
					<td><?php echo esc_html( $l['requis'] ); ?></td>
					<td><?php echo esc_html( $l['constate'] ); ?></td>
					<td><span class="na-etat na-etat--<?php echo esc_attr( $mot[0] ); ?>"><span class="dashicons <?php echo esc_attr( $mot[1] ); ?>" aria-hidden="true"></span> <?php echo esc_html( $mot[2] ); ?></span></td>
					<td><?php echo '' !== $l['ecart'] ? esc_html( $l['ecart'] ) : '—'; ?></td>
					<td class="description"><?php echo esc_html( $l['pourquoi'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( empty( $manques ) ) : ?>
			<p><span class="na-etat na-etat--ok"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> Rien ne manque.</span></p>
		<?php else : ?>
			<p><span class="na-etat na-etat--echec"><span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<?php echo (int) count( $manques ); ?>
			<?php echo 1 === count( $manques ) ? 'point manque.' : 'points manquent.'; ?></span>
			Le reste du plugin fonctionne, mais ce qui en dépend restera hors d’atteinte.</p>
		<?php endif; ?>
		<p class="description" style="max-width:46em">
			L’envoi d’une notice précise se diagnostique depuis sa fiche, dans
			l’encart « Dépannage ».
		</p>

		<h2>Sortie vers Pactols</h2>
		<p style="max-width:46em">Cet essai dit si le serveur peut joindre le
		thésaurus. Le navigateur de l’auteur l’interroge lui-même pour proposer
		les termes&nbsp;; mais c’est le serveur qui lit ensuite leur forme préférée et
		leur chaîne, d’où viennent les blocs d’index. Le proxy qui refuse
		Cloudflare peut le refuser aussi.</p>
		<?php self::ouvrir_un_essai(); ?>
			<button type="submit" class="button" name="na_quoi" value="pactols">Tester l’accès à Pactols</button>
		</form>
		<?php
		if ( 'pactols' === $essai['quoi'] ) {
			self::resultat( $essai['resultat'] );
		}
	}

	/**
	 * Le serveur joint-il Pactols ?
	 *
	 * La résolution des termes ne se fait que là, dans une tâche : si le
	 * proxy de l'hébergement refuse la sortie, aucun terme n'est jamais lu —
	 * ni forme préférée, ni bloc d'index, ni terme retiré —, chaque notice
	 * épuise ses essais, et le Word dit seulement « pas encore lu ». On
	 * demande un concept connu, par la route même qu'emploie la résolution.
	 */
	private function test_pactols() {
		$reponse = wp_remote_get( Notice_Archeomed_Thesaurus::BASE . 'concept/ark:/26678/pcrtRFSvuXH6BD', array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/json' ),
		) );
		if ( is_wp_error( $reponse ) ) {
			$message = $reponse->get_error_message();
			$proxy   = false !== stripos( $message, 'proxy' ) || false !== stripos( $message, 'error 56' );
			return array(
				'ok'      => false,
				'message' => 'Pactols n’est pas joignable depuis ce serveur — ' . $message . '.'
					. ( $proxy ? ' C’est le proxy de l’hébergement qui refuse la sortie, comme pour Cloudflare : il faut demander l’ouverture de pactols.frantiq.fr.' : '' )
					. ' Les termes resteront « non lus » : ni forme préférée, ni bloc d’index.',
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $reponse );
		$data = json_decode( wp_remote_retrieve_body( $reponse ), true );
		if ( 200 !== $code || ! is_array( $data ) || empty( $data ) ) {
			return array(
				'ok'      => false,
				'message' => 'Pactols répond, mais pas ce qu’on attend (code HTTP ' . $code . ') : '
					. 'le serveur le joint, l’API peut être en panne ou avoir changé.',
			);
		}
		return array(
			'ok'      => true,
			'message' => 'Pactols est joignable depuis ce serveur (code HTTP 200) : les termes des notices seront lus.',
		);
	}
}
