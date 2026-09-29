<?php
/**
 * Plugin Name: Formulaire des notices d’archéologie médiévale
 * Description: Formulaire de soumission de notice d'opération archéologique pour la Chronique d'Archéologie médiévale. Le courriel adressé à la rédaction est accompagné d'un fichier DOCX stylé Métopes. Shortcode : [notice_archeomed_pactols]
 * Version: 3.43
 * Author: Rédaction d'Archéologie médiévale
 * Requires at least: 5.6
 * Requires PHP: 7.2
 * Text Domain: notice-archeomed
 *
 * Le document produit suit la feuille de styles Métopes (chaîne d'édition XML créée par le Pôle document numérique et l'infrastructure Métopes de l'université de Caen Normandie, https://www.metopes.fr).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-nommage.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-paquet.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-maj.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-rtf.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-docx.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-file.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-thesaurus.php';

new Notice_Archeomed_Settings();

// La file d'attente vit indépendamment du formulaire : elle doit tourner sur
// les requêtes d'administration et les passages du planificateur, où aucun
// formulaire n'est affiché.
// « global » : sous WP-CLI, qui charge WordPress dans une fonction, la
// variable devenait locale ; le plugin ne la retrouvait pas et bâtissait une
// seconde file, dont les crochets s'ajoutaient aux premiers — un récapitulatif
// dû pendant ce passage partait alors deux fois.
global $notice_archeomed_file;
$notice_archeomed_file = new Notice_Archeomed_File();
add_filter( 'cron_schedules', array( 'Notice_Archeomed_File', 'ajouter_intervalle' ) );
register_activation_hook( __FILE__, array( 'Notice_Archeomed_File', 'activer' ) );
register_deactivation_hook( __FILE__, array( 'Notice_Archeomed_File', 'desactiver' ) );
register_deactivation_hook( __FILE__, array( 'Notice_Archeomed_Pactols', 'desactiver' ) );
register_activation_hook( __FILE__, array( 'Notice_Archeomed_Pactols', 'activer' ) );

/**
 * Le guetteur de mises à jour, créé une fois et gardé sous la main.
 *
 * La page de réglages s'en sert pour dire quelle version est publiée : en
 * fabriquer une seconde y enregistrerait ses filtres une deuxième fois, et
 * WordPress verrait la mise à jour annoncée en double.
 */
global $notice_archeomed_maj;
$notice_archeomed_maj = new Notice_Archeomed_MiseAJour( __FILE__ );

/**
 * Lien « Réglages » directement depuis la liste des extensions.
 */
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		$url  = Notice_Archeomed_Settings::url();
		$lien = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Réglages', 'notice-archeomed' ) . '</a>';
		array_unshift( $links, $lien );
		return $links;
	}
);

/**
 * Contrôle à l'activation : sans gabarit, aucun document ne se fabrique.
 *
 * C'est « modele-metopes.docx » qui porte la feuille de styles de référence,
 * et c'est lui que le plugin emploie : le document joint à chaque notice est
 * un DOCX. Le contrôle portait sur le RTF, qui n'est qu'un repli — on
 * refusait donc l'activation pour l'absence du second tout en laissant
 * disparaître le premier sans rien dire.
 */
register_activation_hook(
	__FILE__,
	function () {
		$modele = plugin_dir_path( __FILE__ ) . 'modele-metopes.docx';
		if ( ! file_exists( $modele ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die(
				esc_html__( 'Le fichier modele-metopes.docx est absent du dossier du plugin. Ce gabarit porte la feuille de styles Métopes de référence : sans lui, aucun document ne peut être mis en forme.', 'notice-archeomed' ),
				esc_html__( 'Activation impossible', 'notice-archeomed' ),
				array( 'back_link' => true )
			);
		}
	}
);

/**
 * Rappel dans l'administration si le modèle disparaît après l'activation
 * (écrasement lors d'une mise à jour, synchronisation partielle…).
 */
add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Le gabarit de référence d'abord : c'est lui qui met les notices en
		// forme. Le RTF ensuite, et son absence ne se signale pas de la même
		// façon — elle ne se paie que sur un hébergement dépourvu de
		// ZipArchive, où le DOCX ne peut pas se fabriquer.
		if ( ! file_exists( plugin_dir_path( __FILE__ ) . 'modele-metopes.docx' ) ) {
			echo '<div class="notice notice-error"><p><strong>Formulaire des notices d’archéologie médiévale :</strong> '
				. esc_html__( 'le gabarit modele-metopes.docx est introuvable. Il porte la feuille de styles Métopes de référence : les notices partiront sans document mis en forme.', 'notice-archeomed' )
				. '</p></div>';
		}
		if ( ! file_exists( plugin_dir_path( __FILE__ ) . 'modele-metopes.rtf' ) && ! class_exists( 'ZipArchive' ) ) {
			echo '<div class="notice notice-warning"><p><strong>Formulaire des notices d’archéologie médiévale :</strong> '
				. esc_html__( 'cet hébergement n’a pas l’extension ZipArchive, le document doit donc se fabriquer en RTF — et le gabarit modele-metopes.rtf est introuvable.', 'notice-archeomed' )
				. '</p></div>';
		}
		// Sans clé secrète, Turnstile rejette toutes les soumissions. On ne le
		// signale que si Turnstile est effectivement la protection retenue :
		// un bandeau rouge permanent sur une installation qui se protège
		// autrement finirait par ne plus être lu du tout.
		// Sans adresse de destination, les notices s'accumulent en file sans
		// que rien ne parte. C'est le premier réglage d'une installation, et
		// celui qu'on oublie : il se signale donc en rouge et sur toutes les
		// pages, tant qu'il manque.
		if ( empty( Notice_Archeomed_Settings::destinataires_de( 'notices' ) ) ) {
			$lien = Notice_Archeomed_Settings::url();
			echo '<div class="notice notice-error"><p><strong>Formulaire des notices d’archéologie médiévale :</strong> '
				. esc_html__( 'l\'adresse de la rédaction n\'est pas renseignée. Les notices déposées sont conservées, mais aucune ne peut être expédiée tant qu\'elle manque.', 'notice-archeomed' )
				. ' <a href="' . esc_url( $lien ) . '">' . esc_html__( 'Renseigner l\'adresse', 'notice-archeomed' ) . '</a></p></div>';
		}
		$protection = Notice_Archeomed_Settings::get( 'protection' );
		$turnstile_sert = in_array( $protection, array( 'turnstile', 'les_deux' ), true );
		if ( $turnstile_sert && '' === trim( Notice_Archeomed_Settings::get( 'turnstile_secret' ) ) ) {
			$lien = Notice_Archeomed_Settings::url();
			echo '<div class="notice notice-error"><p><strong>Formulaire des notices d’archéologie médiévale :</strong> '
				. esc_html__( 'la clé secrète Turnstile n\'est pas renseignée. Tant qu\'elle est absente, le formulaire refuse toutes les soumissions.', 'notice-archeomed' )
				. ' <a href="' . esc_url( $lien ) . '">' . esc_html__( 'Renseigner la clé', 'notice-archeomed' ) . '</a></p></div>';
		}
	}
);
class Notice_Archeomed_Pactols {
	// Valeurs par défaut. Elles sont surchargées par la page de réglages
	// (Réglages > Notice Archéomed) ou par une constante de wp-config.php.
	// Modèle RTF portant la feuille de styles Métopes, livré avec le plugin.
	const RTF_TEMPLATE        = 'modele-metopes.rtf';
	const DOCX_TEMPLATE       = 'modele-metopes.docx';
	const MAX_TOTAL_FILESIZE  = 20971520;
	// Les plafonds, et pourquoi ils sont si hauts.
	//
	// Une adresse IP ne désigne pas une personne. L'Inrap, une DRAC, une
	// université, un laboratoire : leurs archéologues sortent tous par la même
	// adresse publique. Compter serré par IP, c'est bloquer un institut entier
	// dès le sixième dépôt — et sur une campagne où l'on sollicite plusieurs
	// milliers de personnes, c'est perdre les notices de ceux qui répondent
	// justement en groupe, le même jour, après la même réunion.
	//
	// La vraie barrière contre les robots est Turnstile. Ces compteurs ne sont
	// qu'un garde-fou contre le martèlement : on les règle assez haut pour
	// qu'aucun humain ne les rencontre jamais.
	const MAX_ATTEMPTS_PER_HOUR = 300;   // par adresse IP, tentatives comprises
	const MAX_SENDS_PER_IP      = 150;   // par adresse IP, envois aboutis
	const MAX_SENDS_PER_EMAIL   = 25;    // par personne : là, une IP vaut bien une personne
	const MAX_LOOKUPS_PER_HOUR  = 400;
	const MAX_FILES           = 3;
	// Une opération porte parfois sur plusieurs communes : « Beneuvre,
	// Bure-les-Temple, Duesme, Vanvey (Côte-d'Or), Mont Aigu ». Cinq lignes
	// couvrent ce qu'on voit passer, et chacune garde son identifiant Pactols.
	const MAX_LIEUX           = 5;
	// La gestion administrative d'une opération se partage : en recherche
	// programmée, une université et un service de collectivité la portent
	// ensemble. Trois lignes suffisent à ce qu'on voit passer.
	const MAX_ORGANISMES      = 3;
	// Les bornes de l'encoche, en centièmes de la largeur utile : assez loin
	// des bords pour que la pièce reste entière et la cible non devinable.
	const PUZZLE_MIN          = 30;
	const PUZZLE_MAX          = 88;
	// L'écart admis entre la pièce et son encoche. Assez large pour qu'on y
	// arrive au doigt et à la flèche du clavier, assez étroit pour qu'il
	// faille viser.
	const PUZZLE_TOLERANCE    = 4;
	const ALLOWED_EXT         = array( 'jpg', 'jpeg', 'tiff', 'tif', 'pdf' );
	// La clé de site est publique : elle figure dans le code HTML de la page.
	const TURNSTILE_SITE_KEY   = '0x4AAAAAADnqPBVc6ZpaeiEi';
	// La clé secrète NE DOIT PAS figurer ici. Elle se déclare dans wp-config.php :
	//     define( 'NA_TURNSTILE_SECRET', 'votre_cle_secrete' );
	// Laissée vide, la vérification Turnstile échoue et les envois sont bloqués.
	const TURNSTILE_SECRET_KEY = '';
	const TURNSTILE_FAIL_OPEN_ON_NETWORK_ERROR = true;
	const QUILL_JS_SRI  = 'sha384-utBUCeG4SYaCm4m7GQZYr8Hy8Fpy3V4KGjBZaf4WTKOcwhCYpt/0PfeEe3HNlwx8';
	const QUILL_CSS_SRI = 'sha384-ecIckRi4QlKYya/FQUbBUjS4qp65jF/J87Guw5uzTbO1C1Jfa/6kYmd6dXUF6D7i';
	const PACTOLS_API_BASE          = 'https://pactols.frantiq.fr/api/';
	const PACTOLS_LANG              = 'fr';
	const PACTOLS_PERIOD_THESO_ID   = 'TH_1';
	const PACTOLS_SUBJECT_THESO_ID  = 'TH_1';
	const PACTOLS_PLACE_THESO_ID    = 'th17'; // API Pactols_Lieux (th17 en minuscules)
	const PACTOLS_FIELD_COUNT       = 10;
	private $turnstile_error = 'turnstile';
	/**
	 * Ce que le serveur de courriel a répondu la dernière fois qu'il a refusé.
	 *
	 * « wp_mail » rend faux et se tait : la raison ne vit que dans un crochet
	 * qu'il faut écouter. Sans elle, une notice en échec ne dit rien de plus
	 * que « cinq tentatives sans succès », et l'on cherche dans les journaux
	 * du serveur ce que le plugin pouvait retenir.
	 */
	private $derniere_erreur_mail = '';
	private $rubriques = array(
		'I. Constructions et habitats civils',
		'II. Constructions et habitats ecclésiastiques',
		'III. Constructions et habitats fortifiés',
		'IV. Sépultures et nécropoles',
		'V. Installations artisanales',
		'VI. Archéologie subaquatique',
		'VII. Diverses chroniques',
	);
	/**
	 * Les trois familles d'opérations, dans l'ordre où la Chronique les range.
	 *
	 * Ce n'est pas un renseignement de plus à demander à l'auteur : il se
	 * déduit de la nature qu'il a cochée. Une notice qui en coche plusieurs
	 * prend la première famille rencontrée dans cet ordre — une fouille
	 * accompagnée d'une prospection reste une fouille.
	 */
	private $familles = array(
		'Opérations de terrain' => array(
			'Archéologie du bâti',
			'Fouille préventive',
			'Fouille programmée',
			'Opération de diagnostic',
			'Surveillance de travaux',
		),
		'Prospections' => array(
			'Prospection archéologique',
			'Prospection géophysique',
			// Le formulaire ne propose plus « Prospection électrique », mais
			// des notices l'ont cochée : elle reste ici pour qu'elles gardent
			// leur famille dans le fascicule plutôt que de tomber en « Non
			// classé ».
			'Prospection électrique',
			'Prospection inventaire',
			'Prospection pédestre',
			'Prospection subaquatique',
			'Prospection thématique',
		),
		'Projets collectifs de recherche' => array(
			'Projet collectif de recherche',
		),
	);

	private $natures = array(
		'Archéologie du bâti'           => 'https://ark.frantiq.fr/ark:/26678/pcrtaodMT8j83O',
		'Fouille préventive'            => 'https://ark.frantiq.fr/ark:/26678/pcrtcJxzOpgs7T',
		'Fouille programmée'            => 'https://ark.frantiq.fr/ark:/26678/crtSrWQs2w2KV',
		'Opération de diagnostic'       => 'https://ark.frantiq.fr/ark:/26678/pcrtWWQS75V5Bc',
		'Projet collectif de recherche' => 'https://ark.frantiq.fr/ark:/26678/crtqI2kNablQH',
		'Prospection archéologique'     => 'https://ark.frantiq.fr/ark:/26678/pcrtM6WKp5XFlj',
		'Prospection géophysique'       => 'https://ark.frantiq.fr/ark:/26678/pcrtD900pLBG6t',
		'Prospection inventaire'        => 'https://ark.frantiq.fr/ark:/26678/crtBhWSZf1tw8',
		'Prospection pédestre'          => 'https://ark.frantiq.fr/ark:/26678/crtVqcSVS0rm7',
		'Prospection subaquatique'      => 'https://ark.frantiq.fr/ark:/26678/pcrt17S8atFoMi',
		'Prospection thématique'        => 'https://ark.frantiq.fr/ark:/26678/crtcYIBmBlBPH',
		'Surveillance de travaux'       => 'https://ark.frantiq.fr/ark:/26678/crtZ49Dtn1aMT',
	);
	public function __construct() {
		add_shortcode( 'notice_archeomed_pactols', array( $this, 'render_form' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'init', array( $this, 'handle_submission' ) );
		add_action( 'wp_ajax_na_pactols_search', array( $this, 'ajax_pactols_search' ) );
		add_action( 'wp_ajax_nopriv_na_pactols_search', array( $this, 'ajax_pactols_search' ) );
		// L'expédition ne dépend pas de l'affichage du formulaire : elle a
		// lieu sur des requêtes où aucune page de la revue n'est rendue.
		$this->brancher_la_file();
		add_action( 'wp_mail_failed', array( $this, 'retenir_l_erreur_mail' ) );
		add_action( 'phpmailer_init', array( $this, 'acheminer_par_smtp' ) );
		add_action( 'admin_post_na_feuille', array( $this, 'deposer_la_feuille' ) );
		add_action( 'admin_post_na_document', array( $this, 'telecharger_le_document' ) );
		add_action( 'admin_post_na_fascicule', array( $this, 'telecharger_le_fascicule' ) );
		add_action( 'admin_post_na_paquet', array( $this, 'telecharger_le_paquet' ) );
		add_action( 'admin_post_na_illustration', array( $this, 'telecharger_une_illustration' ) );
		add_action( 'before_delete_post', array( $this, 'effacer_les_illustrations' ) );
	}
	/**
	 * Clé secrète Turnstile : constante wp-config.php, puis page de réglages,
	 * puis valeur du code (vide par défaut).
	 */
	private function turnstile_secret() {
		return trim( Notice_Archeomed_Settings::get( 'turnstile_secret' ) );
	}
	/**
	 * Clé de site Turnstile, selon la même priorité.
	 */
	private function turnstile_site_key() {
		$key = trim( Notice_Archeomed_Settings::get( 'turnstile_site' ) );
		return '' !== $key ? $key : trim( self::TURNSTILE_SITE_KEY );
	}
	private function turnstile_site_key_is_set() {
		return '' !== $this->turnstile_site_key();
	}
	/**
	 * L'envoi différé est le mode normal. On garde de quoi revenir à l'envoi
	 * immédiat : si le planificateur de WordPress est désactivé sur
	 * l'hébergement et qu'aucune tâche système ne le remplace, mieux vaut un
	 * formulaire lent qu'un formulaire qui n'envoie rien.
	 */
	private function envoi_differe() {
		return 'immediat' !== Notice_Archeomed_Settings::get( 'mode_envoi' );
	}

	/**
	 * La protection retenue : « locale », « turnstile » ou « les_deux ».
	 *
	 * Turnstile suppose que le serveur puisse joindre Cloudflare. Sur un
	 * hébergement institutionnel qui sort par un proxy filtrant, il ne le peut
	 * pas : la vérification échoue, le plugin laisse passer pour ne pas perdre
	 * de notice, et la protection n'en est plus une. La vérification posée dans
	 * le formulaire, elle, se fait sur place et ne dépend de personne.
	 */
	private function protection() {
		$mode = Notice_Archeomed_Settings::get( 'protection' );
		return in_array( $mode, array( 'locale', 'turnstile', 'les_deux' ), true )
			? $mode : 'locale';
	}

	private function protection_utilise_turnstile() {
		return in_array( $this->protection(), array( 'turnstile', 'les_deux' ), true );
	}

	private function protection_utilise_la_question() {
		return in_array( $this->protection(), array( 'locale', 'les_deux' ), true );
	}

	/**
	 * Le sceau d'un défi : de quoi le vérifier sans rien retenir entre
	 * l'affichage de la page et l'envoi du formulaire. Une page mise en cache
	 * garde donc son défi, et il reste valable.
	 *
	 * L'heure entre dans le sceau, et c'est ce qui a changé. Le sceau ne
	 * portait que la réponse : un couple (réponse, sceau) récolté une fois
	 * valait indéfiniment, sur toutes les pages, pour tout le monde. Il vaut
	 * maintenant l'heure en cours et la précédente — deux heures au pire, le
	 * temps qu'un formulaire ouvert à midi moins deux parte à midi passé.
	 */
	private function sceau_du_defi( $cible, $heure ) {
		return hash_hmac( 'sha256', (int) $cible . '|' . (int) $heure,
			wp_salt( 'nonce' ) );
	}

	/** L'heure en cours, au sens du sceau : un nombre qui change toutes les heures. */
	private function heure_du_defi() {
		return (int) floor( time() / HOUR_IN_SECONDS );
	}

	/**
	 * Le défi posé dans le formulaire : une pièce à glisser dans son encoche.
	 *
	 * C'était une addition, puis un curseur à mener au bout. L'addition
	 * demandait de lire, de calculer et d'écrire à qui venait de rédiger sept
	 * cents mots. Le curseur, lui, s'appuyait sur un « input type=range » : un
	 * thème qui remet l'apparence des champs à zéro lui ôte sa pastille, et il
	 * ne restait qu'à cliquer la piste de proche en proche. La pièce se dessine
	 * donc entièrement, et se déplace sur des événements pointeur, sur quoi
	 * aucune feuille de style n'a prise.
	 *
	 * Elle ne protège ni mieux ni moins bien que ce qu'elle remplace : la
	 * position de l'encoche est dans la page, comme la réponse l'était. La
	 * protection véritable reste ailleurs — Turnstile quand le proxy le laisse
	 * passer, le champ-piège, le délai minimal et les plafonds horaires.
	 */
	private function defi_du_puzzle() {
		// La position de l'encoche, en centièmes de la largeur utile. Tirée au
		// sort plutôt que constante : le corps d'un envoi capté sur une page ne
		// se rejoue pas tel quel sur une autre. Les bornes laissent la pièce
		// visible aux deux extrémités.
		$cible = wp_rand( self::PUZZLE_MIN, self::PUZZLE_MAX );
		return array(
			'cible'  => $cible,
			'preuve' => $this->sceau_du_defi( $cible, $this->heure_du_defi() ),
		);
	}

	private function verifier_le_puzzle() {
		$donnee = isset( $_POST['na_curseur'] )
			? sanitize_text_field( wp_unslash( $_POST['na_curseur'] ) ) : '';
		$preuve = isset( $_POST['na_preuve'] )
			? sanitize_text_field( wp_unslash( $_POST['na_preuve'] ) ) : '';
		if ( '' === $preuve || ! ctype_xdigit( $preuve )
			|| ! preg_match( '/^\d{1,3}$/', $donnee ) ) {
			return false;
		}
		$cible = (int) $donnee;
		if ( $cible < self::PUZZLE_MIN || $cible > self::PUZZLE_MAX ) {
			return false;
		}
		// L'heure en cours, puis celle d'avant : un formulaire ouvert à la
		// charnière ne se fait pas refuser pour une minute.
		$maintenant = $this->heure_du_defi();
		foreach ( array( $maintenant, $maintenant - 1 ) as $heure ) {
			if ( hash_equals( $this->sceau_du_defi( $cible, $heure ), $preuve ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Le passage obligé, quelle que soit la protection choisie.
	 */
	private function verifier_la_protection() {
		if ( $this->protection_utilise_la_question() && ! $this->verifier_le_puzzle() ) {
			error_log( 'Notice Archeomed: vérification par le puzzle non validée.' );
			return false;
		}
		if ( $this->protection_utilise_turnstile() && ! $this->verify_turnstile() ) {
			return false;
		}
		return true;
	}

	/**
	 * La famille d'une notice, et son rang dans l'ordre du volume.
	 */
	public function famille_de( $d ) {
		$cochees = array_map( 'trim', explode( ',', (string) ( isset( $d['nature'] ) ? $d['nature'] : '' ) ) );
		$rang = 0;
		foreach ( $this->familles as $nom => $natures ) {
			++$rang;
			foreach ( $cochees as $cochee ) {
				if ( in_array( $cochee, $natures, true ) ) {
					return array( 'nom' => $nom, 'rang' => $rang );
				}
			}
		}
		// Une nature qu'on ne sait pas ranger ne disparaît pas : elle passe en
		// queue, où elle se remarque et se corrige à la main.
		return array( 'nom' => __( 'Non classé', 'notice-archeomed' ), 'rang' => 9 );
	}

	/**
	 * La clef qui met les notices dans l'ordre du volume : rubrique
	 * principale, puis famille d'opération, puis commune par ordre
	 * alphabétique. Un seul champ triable plutôt que trois, parce qu'une liste
	 * d'administration ne sait pas trier sur trois colonnes à la fois.
	 */
	public function classement_de( $d ) {
		$rubrique = isset( $d['rubrique_principale'] ) ? $d['rubrique_principale'] : '';
		$rang_rubrique = array_search( $rubrique, $this->rubriques, true );
		$rang_rubrique = ( false === $rang_rubrique ) ? 99 : $rang_rubrique + 1;
		$famille = $this->famille_de( $d );
		$commune = remove_accents( (string) ( isset( $d['commune'] ) ? $d['commune'] : '' ) );
		$commune = strtolower( trim( preg_replace( '/[^a-zA-Z0-9]+/', ' ', $commune ) ) );
		return sprintf( '%02d|%d|%s', $rang_rubrique, $famille['rang'], $commune );
	}

	/**
	 * Refabrique le document d'une notice, et le rend au navigateur.
	 *
	 * Le fichier n'est pas conservé : il n'a pas à l'être. Un document stylé
	 * est une fonction de la saisie, et la saisie, elle, est gardée. Le
	 * refabriquer coûte quelques millisecondes, ne peut pas se perdre dans une
	 * purge de répertoire temporaire, et suit d'office une évolution de la
	 * feuille de styles Métopes — ce qu'un fichier archivé ne ferait pas.
	 */
	public function telecharger_le_document() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		check_admin_referer( 'na_document_' . $id );
		$d = $this->saisie_de( $id, true );
		if ( null === $d ) {
			wp_die( esc_html__( 'Cette notice ne porte pas de saisie exploitable.',
				'notice-archeomed' ) );
		}
		$erreur = '';
		$chemin = $this->build_rtf_file( $d, $erreur,
			class_exists( 'ZipArchive' ) ? 'docx' : 'rtf' );
		if ( '' === $chemin ) {
			wp_die( esc_html( sprintf(
				/* translators: %s : message d'erreur. */
				__( 'Le document n’a pas pu être fabriqué : %s', 'notice-archeomed' ),
				$erreur ) ) );
		}
		$this->rendre_le_fichier( $chemin, basename( $chemin ) );
	}

	/**
	 * Le paquet d'une rubrique : le document, les illustrations, l'arborescence.
	 *
	 * Le document est celui du fascicule, fabriqué par le même chemin : une
	 * seule façon d'assembler une rubrique, et non deux qui divergeraient.
	 */
	public function telecharger_le_paquet() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		check_admin_referer( 'na_paquet' );
		$rubrique = isset( $_GET['rubrique'] )
			? sanitize_text_field( wp_unslash( $_GET['rubrique'] ) ) : '';
		if ( ! in_array( $rubrique, $this->rubriques, true ) ) {
			wp_die( esc_html__( 'Rubrique inconnue.', 'notice-archeomed' ) );
		}
		$ids = $this->file()->notices_de_la_rubrique( $rubrique );
		if ( empty( $ids ) ) {
			wp_die( esc_html__( 'Aucune notice dans cette rubrique.', 'notice-archeomed' ) );
		}
		// Redimensionner cent images prend plus que les trente secondes
		// d'usage. On demande du temps ; si l'hébergement refuse, l'encart de
		// diagnostic l'aura déjà dit.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 );
		}

		// L'ordre compte. Les illustrations se posent d'abord : c'est en
		// mesurant les basses définitions produites qu'on sait à quelle taille
		// le document doit appeler chaque figure. L'inverse obligerait à
		// deviner les proportions, ou à écrire le document deux fois.
		$erreur  = '';
		$notices = array();
		foreach ( $ids as $id ) {
			$d = $this->saisie_de( $id, false );
			if ( null === $d ) {
				continue;
			}
			$notices[] = array(
				'id'            => $id,
				'd'             => $d,
				// Tels qu'enregistrés, sans écarter ceux qui manquent : c'est au
				// paquet de dire qu'un fichier reçu n'est plus là.
				'illustrations' => array_values( array_filter(
					(array) get_post_meta( $id, '_na_illustrations', true ), 'is_string' ) ),
			);
		}

		$paquet = new Notice_Archeomed_Paquet();
		$plan   = $paquet->preparer( $rubrique, $notices, $erreur );
		if ( '' !== $erreur ) {
			$paquet->nettoyer();
			wp_die( esc_html( $erreur ) );
		}

		// Chaque figure reçoit le chemin et la mesure de sa basse définition.
		$donnees = array();
		foreach ( $notices as $rang_notice => $notice ) {
			$d = $notice['d'];
			if ( ! empty( $d['illustrations'] ) ) {
				// Le rang de la légende désigne le fichier de même rang : c'est
				// la convention de tout le plugin depuis que les illustrations
				// ont chacune leur ligne, et c'est l'ordre du dépôt qui la
				// tient. « preparer » signale dans le lisez-moi le jour où les
				// deux comptes ne coïncident pas.
				//
				// La saisie arrive sans figure (« saisie_de » les ôte) : une
				// illustration que le dossier n'a pas su réduire reste donc
				// sans image, comme le lisez-moi l'annonce.
				foreach ( $d['illustrations'] as $i => $item ) {
					$n = (int) $item['rang'];
					if ( ! empty( $plan[ $rang_notice ][ $n ] ) ) {
						$d['illustrations'][ $i ]['figure'] = $plan[ $rang_notice ][ $n ];
					}
				}
			}
			$donnees[] = $d;
		}

		// Les blocs d'index, un fichier par notice sous « XML/indexation ».
		// C'est le seul endroit du dossier qui parle à la chaîne plutôt qu'à
		// un lecteur : ce qu'il contient se colle, il ne se relit pas.
		$termes_manquants   = 0;
		$notices_en_attente = 0;
		$prochaine_lecture  = 0;
		$plus_proche        = 0;
		foreach ( $notices as $notice ) {
			if ( empty( $notice['id'] ) ) {
				continue;
			}
			$manquants = 0;
			$prochaine = 0;
			$xml       = $this->indexation_de( $notice['id'], $notice['d'], $manquants, $prochaine );
			if ( $manquants > 0 ) {
				$termes_manquants += $manquants;
				++$notices_en_attente;
				// La plus tardive : c'est après elle que le dossier sera complet.
				$prochaine_lecture = max( $prochaine_lecture, $prochaine );
				// La plus proche : c'est pour elle que la tâche doit passer.
				$plus_proche = $plus_proche ? min( $plus_proche, $prochaine ) : $prochaine;
			}
			if ( '' === $xml ) {
				continue;
			}
			$paquet->poser_une_indexation(
				Notice_Archeomed_Nommage::assainir(
					trim( $notice['d']['commune'] . '-' . $notice['d']['lieu_dit'] ) ),
				$xml );
		}

		// Le lisez-moi dit quand Pactols sera réinterrogé, et non « dans
		// quelques minutes » : après un échec, la reprise attend que le
		// thésaurus oublie cet échec, et la promesse d'avant ne se tenait pas.
		// « Vers » parce que le planificateur de WordPress ne passe qu'à la
		// visite suivante du site.
		if ( $plus_proche > 0 ) {
			self::assurer_la_tache( $plus_proche );
		}
		if ( $termes_manquants > 0 ) {
			$quand = ( $prochaine_lecture <= time() + 2 * MINUTE_IN_SECONDS )
				? 'dans les minutes qui viennent'
				: 'd\'ici ' . wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
					$prochaine_lecture );
			$paquet->noter( sprintf(
				'XML/indexation : %d terme(s) du thésaurus, dans %d notice(s), ne sont pas'
				. ' encore résolus, et leur bloc d\'index manque. Pactols sera réinterrogé %s ;'
				. ' retéléchargez le dossier ensuite.',
				$termes_manquants, $notices_en_attente, $quand ) );
		}

		$document = $this->fabriquer_le_fascicule( $rubrique, $donnees, $erreur );
		if ( '' === $document ) {
			$paquet->nettoyer();
			wp_die( esc_html( $erreur ) );
		}

		$archive = $paquet->emballer( $document,
			$this->page_de_relecture( $rubrique, $donnees ), $erreur );
		@unlink( $document );
		$paquet->nettoyer();
		if ( '' === $archive ) {
			wp_die( esc_html( '' !== $erreur ? $erreur : "Le paquet n'a pas pu être assemblé." ) );
		}
		$this->rendre_le_fichier( $archive, basename( $archive ) );
	}

	/**
	 * Le fascicule d'une rubrique : toutes ses notices dans un seul document,
	 * rangées comme le volume les rangera.
	 *
	 * C'est la pièce qu'on relit. Quarante notices dans quarante courriels ne
	 * se relisent pas : on les ouvre une à une, on perd le fil, et l'on ne voit
	 * ni les doublons ni les communes qui se suivent mal.
	 */
	public function telecharger_le_fascicule() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		check_admin_referer( 'na_fascicule' );
		$rubrique = isset( $_GET['rubrique'] )
			? sanitize_text_field( wp_unslash( $_GET['rubrique'] ) ) : '';
		if ( ! in_array( $rubrique, $this->rubriques, true ) ) {
			wp_die( esc_html__( 'Rubrique inconnue.', 'notice-archeomed' ) );
		}
		$ids = $this->file()->notices_de_la_rubrique( $rubrique );
		if ( empty( $ids ) ) {
			wp_die( esc_html__( 'Aucune notice dans cette rubrique.', 'notice-archeomed' ) );
		}

		$erreur = '';
		$chemin = $this->fabriquer_le_fascicule(
			$rubrique, $this->donnees_des_notices( $ids ), $erreur );
		if ( '' === $chemin ) {
			wp_die( esc_html( $erreur ) );
		}
		$this->rendre_le_fichier( $chemin, basename( $chemin ) );
	}

	/**
	 * La rubrique à lire dans un navigateur, figures comprises.
	 *
	 * Un document Word aux images liées montre des cadres vides tant qu'il
	 * n'est pas ouvert depuis le dossier du paquet — et même alors, Word
	 * refuse parfois de résoudre un chemin relatif. Fabriquer un PDF côté
	 * serveur demanderait LibreOffice, qu'un hébergement mutualisé n'a pas.
	 *
	 * Une page HTML, elle, ne dépend de rien : on la double-clique, elle
	 * s'ouvre, les figures sont là. Qui veut un PDF l'imprime depuis son
	 * navigateur — la feuille de style est faite pour ça.
	 *
	 * Elle se bâtit ici, et non dans la classe du paquet, pour tenir ses
	 * métadonnées des mêmes fonctions que le document : deux mises en forme
	 * parallèles finiraient par ne plus dire la même chose.
	 */
	private function page_de_relecture( $rubrique, $donnees ) {
		$h = '';
		foreach ( (array) $donnees as $d ) {
			if ( ! is_array( $d ) || empty( $d ) ) {
				continue;
			}
			$departement = $this->sans_parentheses( $d['departement'] );
			$titre = esc_html( $this->lieux_en_ligne( $d ) . ' (' . $departement . ')' );
			if ( '' !== $d['lieu_dit'] ) {
				$titre .= '. <em>' . esc_html( $d['lieu_dit'] ) . '</em>';
			}
			$h .= '<article><h2>' . $titre . '</h2>';

			// Les mêmes termes qu'au document, portant les mêmes ARK : la page
			// sert aussi à vérifier les liens d'un clic, ce qu'un Word aux
			// liens externes ne permet pas commodément.
			$lier = function ( $items, $capitale = false ) {
				$out = array();
				foreach ( (array) $items as $item ) {
					$label = isset( $item['label'] ) ? trim( (string) $item['label'] ) : '';
					if ( '' === $label ) {
						continue;
					}
					$label = $capitale ? $this->capitale_initiale( $label )
						: $this->bas_de_casse( $label );
					$out[] = ! empty( $item['ark'] )
						? '<a href="' . esc_url( $item['ark'] ) . '">' . esc_html( $label ) . '</a>'
						: esc_html( $label );
				}
				return $out;
			};
			$meta = array();
			$natures = $lier( isset( $d['nature_items'] ) ? $d['nature_items'] : array() );
			if ( ! empty( $natures ) ) {
				$meta[] = esc_html( "Nature de l'opération : " ) . implode( ', ', $natures );
			}
			$periodes = $lier(
				isset( $d['pactols_periods_items'] ) ? $d['pactols_periods_items'] : array(), true );
			if ( ! empty( $periodes ) ) {
				$meta[] = esc_html( 'Période historique : ' ) . implode( ', ', $periodes );
			}
			$meta[] = esc_html( "Année de l'opération : " . $d['annee'] );
			if ( '' !== $d['num_autorisation'] ) {
				$meta[] = esc_html( "Numéro d'autorisation : " . $d['num_autorisation'] );
			}
			if ( ! empty( $d['id_patriarche'] ) ) {
				$meta[] = esc_html( 'Identifiant Patriarche : ' . $d['id_patriarche'] );
			}
			if ( ! empty( $d['rapport_lien'] ) ) {
				$meta[] = esc_html( 'Rapport final : ' ) . '<a href="'
					. esc_url( $d['rapport_lien'] ) . '">'
					. esc_html( $d['rapport_lien'] ) . '</a>';
			}
			$organismes = $this->organismes_de( $d );
			if ( ! empty( $organismes ) ) {
				$meta[] = esc_html( $this->libelle_organisme( count( $organismes ) )
					. ' : ' . implode( ', ', $organismes ) );
			}
			$sujets = $lier( isset( $d['pactols_subjects_items'] ) ? $d['pactols_subjects_items'] : array() );
			if ( ! empty( $sujets ) ) {
				$meta[] = esc_html( 'Mots-clés : ' ) . implode( ', ', $sujets );
			}
			$h .= '<p class="meta">' . implode( '<br>', $meta ) . '</p>';

			// Le texte a déjà traversé « clean_richtext » au dépôt : il ne
			// porte que huit balises. On le repasse au même tamis plutôt que
			// de s'en remettre à ce qui est en base.
			$h .= '<div class="texte">' . $this->clean_richtext( $d['texte_notice'] ) . '</div>';

			foreach ( (array) ( isset( $d['illustrations'] ) ? $d['illustrations'] : array() ) as $item ) {
				$h .= '<figure>';
				if ( ! empty( $item['figure']['fichier'] ) ) {
					$h .= '<img src="' . esc_attr( $item['figure']['fichier'] ) . '" alt="'
						. esc_attr( $item['titre'] ) . '">';
				} else {
					$h .= '<p class="absente">Pas de basse définition pour cette figure.</p>';
				}
				$h .= '<figcaption><strong>Fig. ' . (int) $item['rang'] . '</strong>';
				if ( '' !== $item['titre'] ) {
					$h .= ' — ' . esc_html( $item['titre'] );
				}
				if ( '' !== $item['legende'] ) {
					$h .= '<br>' . esc_html( $item['legende'] );
				}
				if ( '' !== $item['credits'] ) {
					$h .= '<br><em>' . esc_html( $item['credits'] ) . '</em>';
				}
				$h .= '</figcaption></figure>';
			}
			$h .= '</article>';
		}

		return '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
			. '<title>' . esc_html( $rubrique ) . '</title><style>'
			// Le fond s'écrit : sans lui, un navigateur en thème sombre pose
			// un canevas noir sous un texte gris foncé, et la page devient
			// illisible sans que personne ait rien fait de travers.
			. 'body{font-family:Georgia,serif;max-width:46em;margin:2em auto;padding:0 1.2em;'
			. 'color:#222;background:#fff;line-height:1.55}'
			. 'h1{font-size:1.5em;border-bottom:2px solid #8a6d3b;padding-bottom:.3em}'
			. 'article{margin:2.5em 0;padding-bottom:1.5em;border-bottom:1px solid #ddd}'
			. 'h2{font-size:1.2em;margin-bottom:.2em}'
			. '.meta{font-family:Arial,sans-serif;font-size:.82em;color:#555;margin:.2em 0 1em}'
			. '.meta a{color:#555;text-decoration:none;border-bottom:1px dotted #aaa}'
			. '.meta a:hover{color:#8a6d3b;border-bottom-color:#8a6d3b}'
			. '.texte p{text-align:justify}'
			. 'figure{margin:1.5em 0;padding:0}'
			. 'figure img{max-width:100%;height:auto;border:1px solid #ddd}'
			. 'figcaption{font-family:Arial,sans-serif;font-size:.82em;color:#444;margin-top:.4em}'
			. '.absente{font-family:Arial,sans-serif;font-size:.82em;color:#b32d2e;'
			. 'background:#f8e6e6;border:1px solid #b32d2e;padding:.6em .8em}'
			. '.note{font-family:Arial,sans-serif;font-size:.8em;color:#666;font-style:italic}'
			// Imprimée, la page devient le PDF qu'on ne peut pas fabriquer sur
			// le serveur : on évite qu'une figure se coupe entre deux pages.
			. '@media print{body{max-width:none;margin:0}article,figure{break-inside:avoid}'
			. '.note{display:none}}'
			. '</style></head><body>'
			. '<h1>' . esc_html( $rubrique ) . '</h1>'
			. '<p class="note">Page de relecture du paquet. Pour un PDF : imprimez depuis le navigateur.</p>'
			. $h . '</body></html>';
	}

	/** La saisie de chaque notice d'une liste, dans l'ordre du classement. */
	private function donnees_des_notices( $ids ) {
		$donnees = array();
		foreach ( (array) $ids as $id ) {
			$d = $this->saisie_de( $id, true );
			if ( null !== $d ) {
				$donnees[] = $d;
			}
		}
		return $donnees;
	}

	/**
	 * Une ligne de renvoi, à la place alphabétique de la notice qu'elle
	 * désigne.
	 *
	 * « Thue et Mue (Calvados). Rue de Reviers — Voir dans la rubrique
	 * Constructions et habitats ecclésiastiques. » Le lieu porte son ARK comme
	 * ailleurs, de sorte qu'un renvoi s'indexe aussi bien qu'une notice.
	 */
	private function poser_un_renvoi( $doc, $d ) {
		$departement = $this->sans_parentheses( $d['departement'] );
		$lieux       = $this->lieux_de( $d );
		$morceaux    = array();
		foreach ( $lieux as $lieu ) {
			$morceaux[] = ( '' !== $lieu['ark'] )
				? $doc->hyperlink( $lieu['ark'], $lieu['nom'] )
				: $doc->plain( $lieu['nom'] );
		}
		$contenu = ! empty( $morceaux )
			? implode( $doc->plain( ', ' ), $morceaux )
			: $doc->plain( $this->lieux_en_ligne( $d ) );
		if ( '' !== trim( $departement ) ) {
			$contenu .= $doc->plain( ' (' . $departement . ')' );
		}
		if ( '' !== $d['lieu_dit'] ) {
			$contenu .= $doc->plain( '. ' )
				. $this->run_xml( $doc, array( 'text' => $d['lieu_dit'], 'i' => true ) );
		}
		$contenu .= $doc->plain( ' — Voir dans la rubrique ' )
			. $this->run_xml( $doc, array(
				'text' => $this->rubrique_sans_numero( $d['rubrique_principale'] ),
				'i'    => true ) )
			. $doc->plain( '.' );
		$doc->add_raw_paragraph( 'Normal', $contenu );
	}

	/**
	 * Le document d'une rubrique, assemblé une fois pour deux usages.
	 *
	 * Le fascicule le sert seul ; le paquet le range dans « style ». Deux
	 * chemins qui l'assembleraient chacun de leur côté finiraient par ne plus
	 * produire la même chose, et l'écart ne se verrait qu'à la relecture.
	 *
	 * Rend le chemin du fichier, ou une chaîne vide en renseignant l'erreur.
	 */
	private function fabriquer_le_fascicule( $rubrique, $donnees, &$erreur ) {
		$erreur = '';
		$format = class_exists( 'ZipArchive' ) ? 'docx' : 'rtf';
		$doc    = $this->ouvrir_un_document( $format, $erreur );
		if ( null === $doc ) {
			return '';
		}
		// La rubrique une fois en tête, puis une sous-rubrique par groupe :
		// « IV. – Sépultures et nécropoles », « IV.1 – Opérations de terrain »,
		// et sous elle les notices, communes par ordre alphabétique. Les
		// notices arrivent déjà dans cet ordre, le classement les y a mises.
		$doc->add_paragraph( 'Title', array( array(
			'text' => $this->titre_de_rubrique( $rubrique ) ) ) );
		// Les notices de la rubrique, et les renvois que d'autres lui font :
		// les uns et les autres se rangent ensemble, par sous-rubrique puis
		// par commune. Un renvoi glissé à sa place alphabétique se voit ; posé
		// en bloc à la fin, il s'oublie.
		$entrees = array();
		foreach ( (array) $donnees as $d ) {
			if ( ! is_array( $d ) || empty( $d ) ) {
				continue;
			}
			$entrees[] = array( 'quoi' => 'notice', 'd' => $d, 'clef' => $this->classement_de( $d ) );
		}
		foreach ( $this->file()->renvois_vers( $rubrique ) as $d ) {
			// Le renvoi se classe dans la sous-rubrique de la notice qui le
			// porte : c'est la même opération, vue d'une autre rubrique.
			$entrees[] = array( 'quoi' => 'renvoi', 'd' => $d, 'clef' => $this->classement_de( $d ) );
		}
		usort( $entrees, function ( $a, $b ) {
			// La clef porte le rang de rubrique en tête ; ici toutes les
			// entrées ne sont pas de la même, et c'est la sous-rubrique puis
			// la commune qui comptent.
			$sa = substr( $a['clef'], strpos( $a['clef'], '|' ) + 1 );
			$sb = substr( $b['clef'], strpos( $b['clef'], '|' ) + 1 );
			return strcmp( $sa, $sb );
		} );

		$famille_en_cours = null;
		foreach ( $entrees as $entree ) {
			$famille = $this->titre_de_famille( $entree['d'] );
			if ( $famille !== $famille_en_cours ) {
				$doc->add_paragraph( 'TEI_Titre 1+rubrique',
					array( array( 'text' => $famille ) ) );
				$famille_en_cours = $famille;
			}
			if ( 'renvoi' === $entree['quoi'] ) {
				$this->poser_un_renvoi( $doc, $entree['d'] );
				continue;
			}
			$this->remplir_le_document( $doc, $entree['d'], false );
		}

		$tmp_dir = trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp';
		$slug    = sanitize_file_name( remove_accents( $this->rubrique_sans_numero( $rubrique ) ) );
		$nom     = 'fascicule-' . $slug . '-' . gmdate( 'Y-m-d' )
			. ( 'docx' === $format ? '.docx' : '.rtf' );
		$chemin  = $doc->write_to( $tmp_dir, 'notice-archeomed-' . $nom );
		if ( '' === $chemin ) {
			$erreur = $doc->get_error();
			return '';
		}
		return $chemin;
	}

	/**
	 * Sert un fichier fabriqué à l'instant, puis l'efface : il se refait.
	 */
	private function rendre_le_fichier( $chemin, $nom ) {
		$extension = strtolower( (string) pathinfo( $chemin, PATHINFO_EXTENSION ) );
		$types     = array(
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'rtf'  => 'application/rtf',
			'zip'  => 'application/zip',
		);
		$type = isset( $types[ $extension ] ) ? $types[ $extension ] : 'application/octet-stream';
		nocache_headers();
		header( 'Content-Type: ' . $type );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $nom ) . '"' );
		header( 'Content-Length: ' . filesize( $chemin ) );
		readfile( $chemin );
		@unlink( $chemin );
		exit;
	}


	/**
	 * Ce qu'on avait saisi, quand la soumission n'a pas abouti.
	 *
	 * Une notice se rédige en une heure. La perdre parce qu'on a oublié le
	 * la vérification anti-robot — ou parce que la page avait expiré pendant
	 * qu'on écrivait — est le défaut le plus coûteux qu'un formulaire puisse avoir,
	 * et celui qui décourage pour de bon.
	 *
	 * La saisie est donc mise de côté avant le renvoi, et le formulaire se
	 * remplit de lui-même au retour. Les fichiers font exception : aucun
	 * navigateur ne permet de regarnir un champ de fichier, et il faut les
	 * redéposer. Ce qu'on avait écrit à leur sujet, en revanche, revient.
	 */
	private $reprise = null;

	/** Le jeton par lequel on est arrivé, s'il y en a un. */
	private function jeton_de_reprise() {
		return isset( $_GET['notice_reprise'] )
			? sanitize_key( wp_unslash( $_GET['notice_reprise'] ) ) : '';
	}

	private function reprise() {
		if ( null !== $this->reprise ) {
			return $this->reprise;
		}
		$this->reprise = array();
		$jeton = $this->jeton_de_reprise();
		if ( '' !== $jeton ) {
			$garde = get_transient( 'na_reprise_' . $jeton );
			if ( is_array( $garde ) ) {
				$this->reprise = $garde;
			}
		}
		return $this->reprise;
	}

	/** La valeur d'un champ à la reprise, prête à être posée dans un attribut. */
	private function repris( $champ ) {
		$garde = $this->reprise();
		return isset( $garde[ $champ ] ) ? (string) $garde[ $champ ] : '';
	}

	/** La valeur d'un champ répétable à la reprise, sous forme de liste. */
	private function repris_liste( $champ ) {
		$garde = $this->reprise();
		if ( ! isset( $garde[ $champ ] ) ) {
			return array();
		}
		// Le champ était unique avant d'être répétable : une reprise d'alors
		// porte une chaîne là où l'on attend un tableau.
		return is_array( $garde[ $champ ] )
			? array_values( $garde[ $champ ] ) : array( (string) $garde[ $champ ] );
	}

	private function repris_coche( $champ, $valeur ) {
		$garde = $this->reprise();
		$liste = isset( $garde[ $champ ] ) ? (array) $garde[ $champ ] : array();
		return in_array( $valeur, $liste, true ) ? ' checked' : '';
	}

	/**
	 * La référence du dépôt que celui-ci corrige, ou une chaîne vide.
	 *
	 * Elle ne se lit pas dans le formulaire : un champ caché se forge, et l'on
	 * ferait dire à la rédaction d'effacer n'importe quelle notice. Le
	 * formulaire ne renvoie que le jeton par lequel il a été rouvert, et c'est
	 * la réserve — écrite par nous — qui dit ce qu'il remplace.
	 */
	private function reference_corrigee() {
		$jeton = isset( $_POST['reprise_jeton'] )
			? sanitize_key( wp_unslash( $_POST['reprise_jeton'] ) ) : '';
		if ( '' === $jeton ) {
			return '';
		}
		$garde = get_transient( 'na_reprise_' . $jeton );
		if ( ! is_array( $garde ) || empty( $garde['remplace'] ) ) {
			return '';
		}
		$ref = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $garde['remplace'] ) );
		return 6 === strlen( $ref ) ? $ref : '';
	}

	/**
	 * Met la saisie de côté et rend le jeton qui permettra de la reprendre.
	 *
	 * Deux usages, et deux durées. Après un refus, la saisie attend une heure
	 * que la personne recommence. Après un dépôt réussi, elle attend un mois :
	 * c'est le lien de correction, et l'on ne relit son propre texte qu'une
	 * fois la copie reçue. « $remplace » porte alors la référence du dépôt que
	 * la correction viendra remplacer.
	 */
	private function garder_la_saisie( $duree = HOUR_IN_SECONDS, $remplace = null ) {
		$garde = array();
		$simples = array(
			'rubrique_principale', 'renvoi_1', 'renvoi_2',
			'resp_prenom', 'resp_nom', 'resp_email', 'resp_inst',
			'coresp_prenom', 'coresp_nom', 'coresp_email', 'coresp_inst',
			'coauteur_prenom', 'coauteur_nom', 'coauteur_email', 'coauteur_inst',
			'departement', 'lieu_dit', 'annee',
			'num_autorisation', 'id_patriarche', 'rapport_lien', 'commentaires',
			'texte_notice', 'pactols_periods', 'pactols_subjects', 'pactols_places',
		);
		foreach ( $simples as $champ ) {
			if ( ! isset( $_POST[ $champ ] ) || ! is_string( $_POST[ $champ ] ) ) {
				continue;
			}
			// La saisie est remise telle quelle dans le formulaire : elle
			// repassera par les mêmes contrôles au renvoi suivant. Le texte de
			// l'éditeur fait exception — il arrive encodé, et c'est encodé
			// qu'il ressortait sous les yeux de l'auteur.
			$valeur = wp_unslash( $_POST[ $champ ] );
			$garde[ $champ ] = ( 'texte_notice' === $champ )
				? $this->texte_notice_pose( $valeur )
				: $this->limit_string( $valeur, 200000 );
		}
		if ( isset( $_POST['nature'] ) && is_array( $_POST['nature'] ) ) {
			$garde['nature'] = array_map( 'sanitize_text_field',
				array_map( 'strval', wp_unslash( $_POST['nature'] ) ) );
		}
		foreach ( array( 'commune', 'commune_ark', 'organisme',
			'illus_titre', 'illus_legende', 'illus_credits' ) as $champ ) {
			if ( isset( $_POST[ $champ ] ) && is_array( $_POST[ $champ ] ) ) {
				$garde[ $champ ] = array_map( 'sanitize_textarea_field',
					array_map( 'strval', wp_unslash( $_POST[ $champ ] ) ) );
			}
		}
		if ( empty( $garde ) ) {
			return '';
		}
		// Une correction qui échoue reste une correction : la référence
		// remplacée suit la saisie de reprise en reprise.
		$remplace = ( null === $remplace ) ? $this->reference_corrigee() : $remplace;
		if ( '' !== $remplace ) {
			$garde['remplace'] = $remplace;
		}
		$jeton = strtolower( wp_generate_password( 12, false, false ) );
		set_transient( 'na_reprise_' . $jeton, $garde, $duree );
		return $jeton;
	}

	/** Efface la réserve du lien de correction qui vient d'être utilisé. */
	private function oublier_le_jeton_repris() {
		$jeton = isset( $_POST['reprise_jeton'] )
			? sanitize_key( wp_unslash( $_POST['reprise_jeton'] ) ) : '';
		if ( '' !== $jeton ) {
			delete_transient( 'na_reprise_' . $jeton );
		}
	}

	/**
	 * L'adresse de la page qui porte le formulaire, sans nos paramètres.
	 */
	private function url_du_formulaire() {
		$referer = wp_get_referer();
		if ( ! $referer ) {
			$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		}
		if ( ! $referer ) {
			$referer = home_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' );
		}
		return remove_query_arg(
			array( 'notice_envoyee', 'notice_erreur', 'notice_champ', 'notice_ref',
				'notice_reprise' ),
			$referer );
	}

	/**
	 * Le lien qui rouvre le formulaire rempli, pour corriger ce qu'on vient
	 * de déposer.
	 *
	 * C'est en recevant sa copie qu'on voit ce qu'on n'avait pas vu en
	 * saisissant. Sans ce lien, il fallait tout retaper, ou écrire à la
	 * rédaction — et la rédaction se retrouvait à corriger à la main.
	 */
	private function lien_de_correction( $reference ) {
		$jeton = $this->garder_la_saisie( MONTH_IN_SECONDS, $reference );
		if ( '' === $jeton ) {
			return '';
		}
		return add_query_arg( 'notice_reprise', $jeton, $this->url_du_formulaire() );
	}

	/** Les adresses qui reçoivent chaque notice à mesure qu'elle arrive. */
	private function destinataires_des_notices() {
		return Notice_Archeomed_Settings::destinataires_de( 'notices' );
	}

	/**
	 * L'adresse à laquelle un auteur répond, et celle qu'on lui donne à
	 * écrire quand un envoi échoue.
	 *
	 * La première de la liste : il faut bien en choisir une, et c'est celle
	 * qu'on a placée en tête. À défaut de destinataire des notices, le premier
	 * de la liste quel qu'il soit — mieux vaut une adresse que rien.
	 */
	private function adresse_de_contact() {
		$notices = $this->destinataires_des_notices();
		if ( ! empty( $notices ) ) {
			return $notices[0];
		}
		$tous = Notice_Archeomed_Settings::destinataires();
		return ! empty( $tous ) ? $tous[0]['email'] : '';
	}
	/**
	 * L'éditeur et le contrôle anti-robot ne servent que sur la page du
	 * formulaire.
	 *
	 * Ils étaient chargés partout : deux requêtes à un CDN sur chaque page de
	 * la revue, y compris pour qui vient lire un article. Sur un site
	 * beaucoup consulté, c'est autant de dépendance à un tiers pour rien —
	 * et un ralentissement payé par tout le monde.
	 */
	private function page_porte_le_formulaire() {
		if ( is_admin() ) {
			return false;
		}
		$objet = get_post();
		if ( ! $objet instanceof WP_Post ) {
			return false;
		}
		return has_shortcode( $objet->post_content, 'notice_archeomed_pactols' );
	}

	public function enqueue_assets() {
		if ( ! $this->page_porte_le_formulaire() ) {
			return;
		}
		wp_enqueue_style( 'quill-snow', 'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css', array(), null );
		wp_enqueue_script( 'quill', 'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js', array(), null, true );
		add_filter( 'style_loader_tag', array( $this, 'add_sri_style' ), 10, 4 );
		add_filter( 'script_loader_tag', array( $this, 'add_sri_script' ), 10, 3 );
		if ( $this->protection_utilise_turnstile() && $this->turnstile_site_key_is_set() ) {
			wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
		}
	}
	public function add_sri_style( $tag, $handle, $href, $media ) {
		if ( 'quill-snow' === $handle ) {
			$tag = str_replace( ' />', ' integrity="' . esc_attr( self::QUILL_CSS_SRI ) . '" crossorigin="anonymous" />', $tag );
		}
		return $tag;
	}
	public function add_sri_script( $tag, $handle, $src ) {
		if ( 'quill' === $handle ) {
			$tag = str_replace( ' src=', ' integrity="' . esc_attr( self::QUILL_JS_SRI ) . '" crossorigin="anonymous" src=', $tag );
		}
		return $tag;
	}
	private function options_html( $options, $selected = '' ) {
		$html = '<option value="">- Sélectionner -</option>';
		foreach ( $options as $opt ) {
			$sel   = ( $opt === $selected ) ? ' selected' : '';
			$html .= '<option value="' . esc_attr( $opt ) . '"' . $sel . '>' . esc_html( $opt ) . '</option>';
		}
		return $html;
	}
	private function checkboxes_html( $options, $name ) {
		// $options peut être une liste simple ou un tableau associatif (libellé => ARK).
		$labels = $this->is_assoc( $options ) ? array_keys( $options ) : $options;
		$html = '<div class="na-checkboxes">';
		foreach ( $labels as $opt ) {
			$html .= '<label class="na-check"><input type="checkbox" name="'
				. esc_attr( $name ) . '[]" value="' . esc_attr( $opt ) . '"'
				. $this->repris_coche( $name, $opt ) . '> '
				. esc_html( $opt ) . '</label>';
		}
		$html .= '</div>';
		return $html;
	}
	private function pactols_column_html( $title, $type, $help = '' ) {
		$html  = '<div class="na-pactols-col">';
		$html .= '<h4>' . esc_html( $title ) . '</h4>';
		if ( '' !== $help ) {
			$html .= '<p class="na-help">' . esc_html( $help ) . '</p>';
		}
		for ( $i = 1; $i <= self::PACTOLS_FIELD_COUNT; $i++ ) {
			$field_id = 'na-pactols-' . $type . '-' . $i;
			$html .= '<div class="na-pactols-field" data-pactols-field>';
			$html .= '<label for="' . esc_attr( $field_id ) . '">' . esc_html( $i ) . '</label>';
			$html .= '<input type="text" id="' . esc_attr( $field_id ) . '" class="na-pactols-input" data-pactols-type="' . esc_attr( $type ) . '" autocomplete="off">';
			$html .= '<input type="hidden" class="na-pactols-hidden" data-pactols-hidden="' . esc_attr( $type ) . '">';
			$html .= '<ul class="na-pactols-suggestions" style="display:none;"></ul>';
			$html .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}
	/**
	 * Le relais vers le thésaurus Pactols : ouvert, mais ni sans mémoire ni
	 * sans limite.
	 *
	 * Il l'était : accessible sans être connecté, sans jeton, sans cache, une
	 * requête sortante de huit secondes par frappe au-delà de trois
	 * caractères. N'importe qui pouvait faire marteler frantiq.fr par le site
	 * de la revue et saturer ses processus PHP au passage.
	 *
	 * Un thésaurus ne bouge guère : le même terme cherché deux fois dans la
	 * journée n'a aucune raison de ressortir deux fois. Le cache protège donc
	 * autant qu'il accélère — la seconde frappe répond sans réseau.
	 */
	public function ajax_pactols_search() {
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		$q    = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$q    = $this->limit_string( trim( $q ), 80 );
		if ( strlen( $q ) < 3 || ! in_array( $type, array( 'period', 'subject', 'place' ), true ) ) {
			wp_send_json_success( array() );
		}

		// Une recherche déjà faite ressort de la réserve, sans réseau.
		// « mbstring » n'est pas garantie sur tous les hébergements : sans
		// elle, on se contente de la casse ASCII plutôt que d'échouer.
		$clef  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $q ) : strtolower( $q );
		$cache = 'na_pactols_' . md5( $type . '|' . $clef );
		$connu = get_transient( $cache );
		if ( is_array( $connu ) ) {
			wp_send_json_success( $connu );
		}

		// Au-delà, on cesse d'interroger Pactols pour cette connexion. La
		// borne est large : elle laisse taper tout un formulaire sans la voir.
		if ( ! $this->check_lookup_limit() ) {
			error_log( 'Notice Archeomed: Pactols lookup limit reached.' );
			wp_send_json_success( array() );
		}
		$theso = self::PACTOLS_SUBJECT_THESO_ID;
		if ( 'period' === $type ) {
			$theso = self::PACTOLS_PERIOD_THESO_ID;
		} elseif ( 'place' === $type ) {
			$theso = self::PACTOLS_PLACE_THESO_ID;
		}
		// API Opentheso : le terme cherché fait partie du chemin, theso et lang en paramètres.
		$url = self::PACTOLS_API_BASE . 'autocomplete/' . rawurlencode( $q )
			. '?lang=' . rawurlencode( self::PACTOLS_LANG )
			. '&theso=' . rawurlencode( $theso );
		// Périodes : restreindre à la branche P2-Entités temporelles (groupe g124).
		if ( 'period' === $type ) {
			$url .= '&group=g124';
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			error_log( 'Notice Archeomed: Pactols request failed: ' . $response->get_error_message() );
			// Une panne de réseau ne se met pas en réserve : la frappe
			// suivante doit pouvoir réessayer.
			wp_send_json_success( array() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			error_log( 'Notice Archeomed: Pactols HTTP status ' . $code . ' for URL ' . $url );
			wp_send_json_success( array() );
		}
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			error_log( 'Notice Archeomed: Pactols invalid JSON.' );
			wp_send_json_success( array() );
		}
		// Format autocomplete : tableau plat d'objets { identifier, uri, label }.
		$out  = array();
		$seen = array();
		foreach ( $data as $item ) {
			if ( ! is_array( $item ) || empty( $item['label'] ) || empty( $item['uri'] ) ) {
				continue;
			}
			$uri = (string) $item['uri'];
			// Dédoublonnage par URI (l'API renvoie le même concept pour prefLabel + altLabels).
			if ( isset( $seen[ $uri ] ) ) {
				continue;
			}
			$seen[ $uri ] = true;
			$out[] = array(
				'label'     => trim( wp_strip_all_tags( (string) $item['label'] ) ),
				'ark'       => trim( $uri ),
				'idConcept' => isset( $item['identifier'] ) ? trim( (string) $item['identifier'] ) : '',
				'fullpath'  => '',
			);
		}
		$out = array_slice( $out, 0, 20 );
		set_transient( $cache, $out, DAY_IN_SECONDS );
		wp_send_json_success( $out );
	}

	/**
	 * Ce qu'on dit à l'auteur quand la soumission n'aboutit pas.
	 *
	 * Tous les refus renvoyaient le même écran : nonce, quota, Turnstile,
	 * champ manquant, fichier refusé, panne de courriel. La raison n'était
	 * que dans le journal PHP, que l'auteur ne lit pas — il ne pouvait donc
	 * rien corriger, et recommençait à l'identique.
	 */
	private function message_derreur( $raison ) {
		$messages = array(
			'securite'     => 'La page avait expiré. Rechargez-la et redéposez votre notice — le texte saisi est conservé par le navigateur si vous revenez en arrière.',
			'quota'        => 'Trop de tentatives depuis cette connexion. Attendez une heure, ou écrivez directement à la rédaction.',
			'envois'       => 'Le nombre de notices envoyées depuis cette adresse a atteint la limite horaire. Attendez une heure, ou écrivez directement à la rédaction.',
			'verification' => 'La vérification anti-robot n\'a pas abouti. Glissez la pièce dans son encoche, puis renvoyez le formulaire.',
			'champs'       => 'Un renseignement obligatoire manque ou n\'est pas valide.',
			'texte'        => 'Le texte de la notice est vide, ou dépasse la longueur admise.',
			'fichiers'     => 'Un fichier joint a été refusé : trois au plus, vingt méga-octets en tout, et seulement des .jpg, .tif ou .pdf.',
			'envoi'        => 'Le courriel n\'a pas pu partir, mais votre notice n\'est pas perdue : elle est conservée sur le serveur, fichier stylé compris.',
		);
		$mot = isset( $messages[ $raison ] ) ? $messages[ $raison ] : 'Une erreur est survenue lors de l\'envoi.';
		$redaction = $this->adresse_de_contact();
		return '' !== $redaction
			? $mot . ' Si le problème persiste, écrivez à ' . esc_html( $redaction ) . '.'
			: $mot . ' Si le problème persiste, écrivez à la rédaction de la revue.';
	}

	/**
	 * Le champ qui manque, dit en français plutôt qu'en nom de variable.
	 */
	private function libelle_du_champ( $champ ) {
		$libelles = array(
			'rubrique_principale' => 'la rubrique principale',
			'commune'             => 'le lieu de l’opération',
			'departement'         => 'la précision entre parenthèses',
			'lieu_dit'            => 'le lieu-dit ou le complément de titre',
			'annee'               => 'l\'année de l\'opération',
			'organisme'           => 'l\'organisme porteur de l\'opération',
			'resp_prenom'         => 'le prénom du responsable',
			'resp_nom'            => 'le nom du responsable',
			'resp_inst'           => 'l\'institution du responsable',
			'resp_email'          => 'l\'adresse électronique du responsable',
			'nature'              => 'la nature de l\'opération',
		);
		return isset( $libelles[ $champ ] ) ? $libelles[ $champ ] : '';
	}

	public function render_form() {
		ob_start();
		if ( isset( $_GET['notice_envoyee'] ) ) {
			if ( '1' === $_GET['notice_envoyee'] ) {
				// « Reçue » et non « transmise » : en envoi différé, le
				// courriel part quelques secondes plus tard. Annoncer une
				// copie déjà envoyée, c'est promettre ce qu'on ne tient pas
				// encore — et faire écrire à la rédaction ceux qui regardent
				// leur boîte dans la minute.
				echo '<div class="na-message na-success">Votre notice a bien été reçue. Vous allez en recevoir une copie par courriel, dans quelques instants. Merci.</div>';
			} else {
				$raison = isset( $_GET['notice_erreur'] )
					? sanitize_key( wp_unslash( $_GET['notice_erreur'] ) ) : '';
				$champ  = isset( $_GET['notice_champ'] )
					? $this->libelle_du_champ( sanitize_key( wp_unslash( $_GET['notice_champ'] ) ) ) : '';
				$mot = $this->message_derreur( $raison );
				if ( ! empty( $this->reprise() ) ) {
					$mot .= ' <strong>Votre saisie est conservée : le formulaire est '
						. 'rempli comme vous l’aviez laissé.</strong>';
					if ( ! empty( $this->reprise()['illus_titre'] ) ) {
						$mot .= ' Les fichiers, eux, sont à redéposer — aucun '
							. 'navigateur ne permet de les remettre en place. Ce que '
							. 'vous aviez écrit à leur sujet revient dès que vous les '
							. 'aurez rechoisis.';
					}
				}
				$ref = isset( $_GET['notice_ref'] )
					? sanitize_key( wp_unslash( $_GET['notice_ref'] ) ) : '';
				if ( 'envoi' === $raison && '' !== $ref ) {
					$mot .= ' Citez la référence <strong>' . esc_html( strtoupper( $ref ) )
						. '</strong> dans votre message : elle permet de la retrouver.';
				}
				if ( 'champs' === $raison && '' !== $champ ) {
					$mot = str_replace(
						'Un renseignement obligatoire manque ou n\'est pas valide.',
						'Il manque ' . esc_html( $champ ) . '.',
						$mot
					);
				}
				echo '<div class="na-message na-error">' . wp_kses_post( $mot ) . '</div>';
			}
		}
		// Arrivée par le lien de correction : il faut le dire avant qu'on se
		// demande pourquoi le formulaire est déjà rempli, et prévenir que le
		// renvoi produit un second dépôt.
		$reprise = $this->reprise();
		if ( ! empty( $reprise['remplace'] ) ) {
			echo '<div class="na-message na-avis"><strong>Vous corrigez la notice déposée '
				. 'sous la référence ' . esc_html( $reprise['remplace'] ) . '.</strong> '
				. 'Le formulaire est rempli de votre saisie ; les illustrations sont à '
				. 'redéposer, aucun navigateur ne permettant de les remettre en place. '
				. 'En le renvoyant, vous produisez un nouveau dépôt : la rédaction sera '
				. 'avertie qu\'il remplace le précédent, et supprimera celui-ci.</div>';
		}
		?>
		<style>
			.na-form { max-width: 980px; }
			.na-form label { display: block; font-weight: 600; margin: 18px 0 4px; }
			.na-form .na-req { color: #b00; }
			.na-form input[type=text],
			.na-form input[type=email],
			.na-form select,
			.na-form textarea { width: 100%; padding: 8px; box-sizing: border-box; font-size: 15px; }
			.na-form .na-help { font-weight: 400; font-size: 13px; color: #555; margin: 2px 0 0; }
			.na-form .na-intro { background: #f4f4f0; border-left: 3px solid #8a6d3b; padding: 12px 16px; font-size: 14px; margin-bottom: 8px; }
			.na-form .na-intro.na-spaced { margin-top: 28px; }
			.na-form #na-editor { height: 260px; background: #fff; }
			/* Le thème de la revue donne aux paragraphes une couleur qui,
			   dans l'éditeur, rendait le texte saisi gris pâle sur blanc.
			   On la reprend : ce qu'on écrit se lit en noir. */
			.na-form #na-editor .ql-editor,
			.na-form #na-editor .ql-editor p { color: #111; }
			.na-form #na-editor .ql-editor.ql-blank::before { color: #888; }
			.na-form .na-wordcount { font-size: 13px; color: #555; margin-top: 4px; }
			.na-form .na-wordcount.na-out { color: #b00; font-weight: 600; }
			.na-form .na-submit { margin-top: 24px; padding: 12px 28px; font-size: 16px; background: #8a6d3b; color: #fff; border: 0; cursor: pointer; }
			.na-form .na-submit:hover { background: #6f5730; }
			.na-message { padding: 14px 18px; margin-bottom: 20px; border-radius: 3px; }
			.na-success { background: #e6f4e6; border: 1px solid #4a8a4a; }
			.na-error { background: #f8e6e6; border: 1px solid #b00; }
			.na-avis { background: #fdf6e3; border: 1px solid #8a6d3b; }
			.na-form .na-half { display: flex; gap: 16px; }
			.na-form .na-half > div { flex: 1; }
			.na-form .na-checkboxes { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 16px; margin-top: 4px; }
			.na-form .na-check { font-weight: 400; margin: 0; display: flex; align-items: center; gap: 6px; }
			.na-form .na-check input { width: auto; }
			.na-form .na-autocomplete,
			.na-form .na-pactols-field { position: relative; }
			.na-form .na-lieu { position: relative; margin-bottom: 6px; }
			.na-form .na-lieu input[type=text] { margin: 0; }
			.na-form input.na-organisme { margin-bottom: 6px; }
			/* La pièce et son encoche. Tout est dessiné : aucun champ natif ne
			   sert ici, et un thème qui remet l'apparence des champs à zéro n'a
			   donc rien à casser. */
			.na-form .na-puzzle-scene { position: relative; margin-top: 10px; max-width: 420px;
				border-radius: 6px; overflow: hidden; line-height: 0; }
			.na-form .na-puzzle-scene canvas.na-puzzle-fond { width: 100%; height: auto; display: block; }
			.na-form .na-puzzle-scene canvas.na-puzzle-piece { position: absolute; top: 50%;
				transform: translateY(-50%); height: auto;
				filter: drop-shadow(0 2px 4px rgba(0,0,0,.5)); pointer-events: none; }
			.na-form .na-puzzle-piste { position: relative; max-width: 420px; height: 34px;
				margin-top: 8px; background: #ececec; border: 1px solid #cfcfcf; border-radius: 17px; }
			.na-form .na-puzzle-poignee { position: absolute; top: 50%; left: 0; width: 46px;
				height: 30px; transform: translate(0, -50%); background: #fff;
				border: 1px solid #8a6d3b; border-radius: 15px; cursor: grab;
				touch-action: none; display: flex; align-items: center; justify-content: center;
				box-shadow: 0 1px 3px rgba(0,0,0,.25); box-sizing: border-box; }
			.na-form .na-puzzle-poignee::after { content: '⋮⋮'; color: #8a6d3b;
				font-size: 13px; letter-spacing: -2px; }
			.na-form .na-prise .na-puzzle-poignee { cursor: grabbing; }
			.na-form .na-rate .na-puzzle-scene { animation: na-secousse .3s; }
			@keyframes na-secousse { 25% { transform: translateX(-6px); } 75% { transform: translateX(6px); } }
			.na-form .na-puzzle-etat { display: block; font-size: 14px; color: #555; margin-top: 6px; }
			.na-form .na-fait .na-puzzle-etat { color: #2f6b2f; font-weight: 600; }
			.na-form .na-puzzle-rejouer { background: none; border: 0; padding: 0; margin-top: 2px;
				color: #2271b1; text-decoration: underline; cursor: pointer; font-size: 13px; }
			.na-form .na-question.na-fait { border-color: #4a8a4a; }
			/* L'envoi d'une notice prend plusieurs secondes — le document se
			   fabrique, les illustrations montent. Sans rien à l'écran, on
			   croit que le bouton n'a pas pris et l'on clique encore. */
			.na-form .na-envoi { display: none; margin-top: 14px; }
			.na-form .na-envoi.na-visible { display: block; }
			.na-form .na-envoi-piste { height: 6px; background: #ececec;
				border-radius: 3px; overflow: hidden; }
			.na-form .na-envoi-barre { height: 100%; width: 35%; background: #8a6d3b;
				border-radius: 3px; animation: na-va-et-vient 1.4s ease-in-out infinite; }
			@keyframes na-va-et-vient { 0% { margin-left: -35%; } 100% { margin-left: 100%; } }
			.na-form .na-envoi-mot { font-size: 14px; color: #555; margin-top: 8px; }
			.na-form .na-submit[disabled] { opacity: .6; cursor: progress; }
			.na-form .na-suggestions,
			.na-form .na-pactols-suggestions { position: absolute; z-index: 50; left: 0; right: 0; background: #fff; border: 1px solid #ccc; border-top: 0; max-height: 380px; overflow-y: auto; margin: 0; padding: 0; list-style: none; }
			.na-form .na-suggestions li,
			.na-form .na-pactols-suggestions li { padding: 7px 10px; cursor: pointer; font-size: 14px; }
			.na-form .na-suggestions li:hover,
			.na-form .na-suggestions li.na-active,
			.na-form .na-pactols-suggestions li:hover,
			.na-form .na-pactols-suggestions li.na-active { background: #f0ece2; }
			.na-form .na-filelist { font-size: 13px; color: #333; margin-top: 6px; padding-left: 18px; }
			.na-form .na-filelist li { margin: 2px 0; }
			.na-form .na-fileremove { color: #b00; cursor: pointer; margin-left: 8px; font-size: 12px; }
			/* Une illustration et ce qu'on en dit tiennent ensemble : le titre,
			   la légende et les crédits appartiennent à ce fichier-là et à
			   aucun autre. Un champ libre commun obligeait la rédaction à
			   deviner quelle ligne se rapportait à quelle image. */
			.na-form .na-filelist { list-style: none; padding-left: 0; }
			.na-form .na-illus { border: 1px solid #d8d8d8; border-radius: 8px;
				padding: 10px 12px; margin: 8px 0; background: #fafafa; }
			.na-form .na-illus-nom { font-weight: 600; font-size: 13px; }
			.na-form .na-illus-champs { display: grid; gap: 8px; margin-top: 8px; }
			.na-form .na-illus-champs label { font-size: 12px; color: #555;
				margin: 0; display: block; }
			.na-form .na-illus-champs input,
			.na-form .na-illus-champs textarea { width: 100%; font: inherit;
				font-size: 13px; padding: 5px 7px; box-sizing: border-box; }
			.na-form .na-illus-total { font-size: 12px; color: #555; margin-top: 6px; }
			.na-form .na-question { margin-top: 20px; padding: 12px 14px;
				border: 1px solid #d8d8d8; border-radius: 8px; background: #fafafa; }
			.na-form .na-question label { font-weight: 600; margin: 0; }
			.na-form .na-question input[type=text] { width: 140px; margin-top: 4px; }
			.na-form .na-js-error { background: #f8e6e6; border: 1px solid #b00; padding: 10px; margin: 10px 0; display: none; }
			.na-form .na-pactols-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-top: 8px; }
			.na-form .na-pactols-col { border: 1px solid #ddd; padding: 12px; background: #fafafa; }
			.na-form .na-pactols-col h4 { margin: 0 0 8px; font-size: 16px; }
			.na-form .na-pactols-field { margin-top: 8px; }
			.na-form .na-pactols-field label { display: inline-block; width: 24px; margin: 0 4px 0 0; font-weight: 600; color: #555; }
			.na-form .na-pactols-field input[type=text] { width: calc(100% - 34px); }
			.na-form .na-pactols-selected { border-color: #4a8a4a !important; background: #f6fff6; }
			.na-form .na-pactols-avis { margin: .35em 0 0; font-size: .9em; color: #8a5a00;
				background: #fff8e8; border-left: 3px solid #d79b2a; padding: .4em .6em; }
			.na-form .na-pactols-remplacer { margin-left: .4em; font-size: .95em;
				background: #fff; border: 1px solid #d79b2a; border-radius: 3px;
				padding: .15em .5em; cursor: pointer; color: #8a5a00; }
			.na-form .na-pactols-warning { color: #b00; font-size: 13px; display: none; margin-top: 8px; }
			@media (max-width: 800px) {
				.na-form .na-half,
				.na-form .na-pactols-grid { display: block; }
				.na-form .na-pactols-col { margin-top: 12px; }
			}
		</style>
		<form class="na-form" method="post" enctype="multipart/form-data" id="na-form">
			<?php wp_nonce_field( 'notice_archeomed_submit', 'notice_archeomed_nonce' ); ?>
			<div aria-hidden="true" style="position:absolute!important;left:-9999px!important;top:-9999px!important;height:0;overflow:hidden;">
				<label>Ne pas remplir ce champ
					<input type="text" name="na_website" tabindex="-1" autocomplete="off" value="">
				</label>
			</div>
			<input type="hidden" name="na_ts" value="<?php echo esc_attr( time() ); ?>">
			<input type="hidden" name="reprise_jeton" value="<?php echo esc_attr( $this->jeton_de_reprise() ); ?>">
			<div class="na-js-error" id="na-js-error"></div>
			<label>Rubrique principale <span class="na-req">*</span></label>
			<select name="rubrique_principale" required><?php echo $this->options_html( $this->rubriques, $this->repris( 'rubrique_principale' ) ); ?></select>
			<label>Renvoi à une autre rubrique</label>
			<select name="renvoi_1"><?php echo $this->options_html( $this->rubriques, $this->repris( 'renvoi_1' ) ); ?></select>
			<label>Renvoi à une autre rubrique</label>
			<select name="renvoi_2"><?php echo $this->options_html( $this->rubriques, $this->repris( 'renvoi_2' ) ); ?></select>
			<div class="na-half">
				<div>
					<label>Prénom du responsable d'opération <span class="na-req">*</span></label>
					<input type="text" name="resp_prenom" required value="<?php echo esc_attr( $this->repris( 'resp_prenom' ) ); ?>">
				</div>
				<div>
					<label>Nom du responsable d'opération <span class="na-req">*</span></label>
					<input type="text" name="resp_nom" required value="<?php echo esc_attr( $this->repris( 'resp_nom' ) ); ?>">
				</div>
			</div>
			<label>Adresse électronique du responsable <span class="na-req">*</span></label>
			<input type="email" name="resp_email" required value="<?php echo esc_attr( $this->repris( 'resp_email' ) ); ?>">
			<label>Institution de rattachement du responsable d'opération <span class="na-req">*</span></label>
			<input type="text" name="resp_inst" required value="<?php echo esc_attr( $this->repris( 'resp_inst' ) ); ?>">
			<div class="na-half">
				<div>
					<label>Prénom du co-responsable de l'opération</label>
					<input type="text" name="coresp_prenom" value="<?php echo esc_attr( $this->repris( 'coresp_prenom' ) ); ?>">
				</div>
				<div>
					<label>Nom du co-responsable de l'opération</label>
					<input type="text" name="coresp_nom" value="<?php echo esc_attr( $this->repris( 'coresp_nom' ) ); ?>">
				</div>
			</div>
			<label>Adresse électronique du co-responsable</label>
			<input type="email" name="coresp_email" value="<?php echo esc_attr( $this->repris( 'coresp_email' ) ); ?>">
			<label>Institution de rattachement du co-responsable de l'opération</label>
			<input type="text" name="coresp_inst" value="<?php echo esc_attr( $this->repris( 'coresp_inst' ) ); ?>">
			<div class="na-half">
				<div>
					<label>Prénom du co-auteur (notice rédigée avec)</label>
					<input type="text" name="coauteur_prenom" value="<?php echo esc_attr( $this->repris( 'coauteur_prenom' ) ); ?>">
				</div>
				<div>
					<label>Nom du co-auteur</label>
					<input type="text" name="coauteur_nom" value="<?php echo esc_attr( $this->repris( 'coauteur_nom' ) ); ?>">
				</div>
			</div>
			<label>Adresse électronique du co-auteur</label>
			<input type="email" name="coauteur_email" value="<?php echo esc_attr( $this->repris( 'coauteur_email' ) ); ?>">
			<label>Institution de rattachement du co-auteur</label>
			<input type="text" name="coauteur_inst" value="<?php echo esc_attr( $this->repris( 'coauteur_inst' ) ); ?>">
			<div class="na-half">
				<div class="na-autocomplete">
					<label>Lieu(x) de l’opération <span class="na-req">*</span></label>
					<p class="na-help">Une commune, un territoire, une région : ce que
					Pactols connaît. Plusieurs lignes quand l’opération a porté sur
					plusieurs lieux — ils s’écriront dans l’ordre, séparés par des
					virgules, et chacun garde son identifiant.</p>
					<?php
					$lieux_repris = $this->repris_liste( 'commune' );
					$arks_repris  = $this->repris_liste( 'commune_ark' );
					for ( $rang = 0; $rang < self::MAX_LIEUX; $rang++ ) :
						$valeur = isset( $lieux_repris[ $rang ] ) ? $lieux_repris[ $rang ] : '';
						$ark    = isset( $arks_repris[ $rang ] ) ? $arks_repris[ $rang ] : '';
					?>
					<div class="na-lieu">
						<input type="text" name="commune[]" class="na-commune"
							autocomplete="off"<?php echo 0 === $rang ? ' required' : ''; ?>
							placeholder="<?php echo 0 === $rang ? 'Commune ou territoire' : 'Lieu suivant (facultatif)'; ?>"
							value="<?php echo esc_attr( $valeur ); ?>">
						<input type="hidden" name="commune_ark[]" class="na-commune-ark"
							value="<?php echo esc_attr( $ark ); ?>">
						<ul class="na-suggestions" style="display:none;"></ul>
					</div>
					<?php endfor; ?>
				</div>
				<div>
					<label>Précision entre parenthèses <span class="na-req">*</span></label>
					<p class="na-help">Le département le plus souvent, mais ce peut être
					une région ou un ensemble : « Côte-d’Or », « Champagne, Alsace,
					Lorraine ». Rempli tout seul quand le premier lieu est une commune.
					<strong>Ce qui s’écrit ici n’est pas indexé</strong> : si la
					parenthèse nomme des territoires — Champagne, Alsace, Lorraine —,
					ajoutez-les plus bas dans « Lieux autres que celui de la notice »
					pour qu’ils reçoivent leur identifiant Pactols.</p>
					<input type="text" name="departement" id="na-departement" required value="<?php echo esc_attr( $this->repris( 'departement' ) ); ?>">
				</div>
			</div>
			<label>Lieu-dit, adresse ou complément de titre <span class="na-req">*</span></label>
			<p class="na-help">Ce qui suit la parenthèse dans le titre de la notice :
			un lieu-dit, une adresse, ou le titre d’un projet collectif.</p>
			<input type="text" name="lieu_dit" required value="<?php echo esc_attr( $this->repris( 'lieu_dit' ) ); ?>">
			<label>Nature de l'opération <span class="na-req">*</span></label>
			<?php echo $this->checkboxes_html( $this->natures, 'nature' ); ?>
			<label>Année de l'opération <span class="na-req">*</span></label>
			<input type="text" name="annee" required value="<?php echo esc_attr( $this->repris( 'annee' ) ); ?>">
			<label>Numéro d'autorisation</label>
			<input type="text" name="num_autorisation" value="<?php echo esc_attr( $this->repris( 'num_autorisation' ) ); ?>">
			<label>Identifiant Patriarche</label>
			<p class="na-help">Le numéro que porte l’opération dans la base Patriarche
			du ministère de la Culture, s’il vous est connu. Il ne se confond pas avec
			le numéro d’autorisation, qui est celui de l’arrêté.</p>
			<input type="text" name="id_patriarche" value="<?php echo esc_attr( $this->repris( 'id_patriarche' ) ); ?>">
			<label>Rapport final (lien)</label>
			<p class="na-help">L’adresse à laquelle le rapport final d’opération se
			consulte — Dolia, HAL, le site du service régional. Laisser vide s’il
			n’est pas déposé.</p>
			<input type="url" name="rapport_lien" placeholder="https://" value="<?php echo esc_attr( $this->repris( 'rapport_lien' ) ); ?>">
			<label>Organisme porteur de l'opération <span class="na-req">*</span></label>
			<p class="na-help">L’organisme qui gère administrativement l’opération :
			l’Inrap, un service archéologique de collectivité, une université, le
			CNRS, un opérateur privé. Plusieurs lignes quand cette gestion est
			partagée entre plusieurs organismes ; les autres partenaires
			scientifiques se citent dans le texte de la notice.</p>
			<?php
			$organismes_repris = $this->repris_liste( 'organisme' );
			for ( $rang = 0; $rang < self::MAX_ORGANISMES; $rang++ ) :
				$valeur = isset( $organismes_repris[ $rang ] ) ? $organismes_repris[ $rang ] : '';
			?>
			<input type="text" name="organisme[]" class="na-organisme"<?php echo 0 === $rang ? ' required' : ''; ?>
				placeholder="<?php echo 0 === $rang ? 'Organisme porteur' : 'Organisme suivant (facultatif)'; ?>"
				value="<?php echo esc_attr( $valeur ); ?>">
			<?php endfor; ?>
			<label>Texte de la notice <span class="na-req">*</span></label>
			<p class="na-help">Entre 300 et 700 mots (recommandation).</p>
			<div id="na-editor"></div>
			<div class="na-wordcount" id="na-wordcount">0 mot</div>
			<input type="hidden" name="texte_notice" id="na-texte-notice" value="<?php echo esc_attr( $this->repris( 'texte_notice' ) ); ?>">
			<label>Mots-clés Pactols</label>
			<p class="na-help">Saisir au moins 3 caractères, puis sélectionner un concept dans la liste. Le libellé et l'ARK seront transmis à la rédaction.</p>
			<div class="na-pactols-warning" id="na-pactols-warning">Certains mots-clés ont été saisis sans sélection Pactols. Merci de choisir une proposition dans la liste afin de transmettre l'ARK.</div>
			<div class="na-pactols-grid" id="na-pactols-grid">
				<?php
				echo $this->pactols_column_html( 'Périodes historiques', 'period', '' );
				echo $this->pactols_column_html( 'Sujets', 'subject', '' );
				// La commune de la notice est déjà demandée plus haut : ce qu'on
				// attend ici, ce sont les autres lieux — la région, le pays, un
				// ensemble géographique. Sans le dire, on reçoit la commune deux fois.
				echo $this->pactols_column_html(
					'Lieux autres que celui de la notice', 'place', '' );
				?>
			</div>
			<input type="hidden" name="pactols_periods" id="pactols_periods" value="<?php echo esc_attr( $this->repris( 'pactols_periods' ) ); ?>">
			<input type="hidden" name="pactols_subjects" id="pactols_subjects" value="<?php echo esc_attr( $this->repris( 'pactols_subjects' ) ); ?>">
			<input type="hidden" name="pactols_places" id="pactols_places" value="<?php echo esc_attr( $this->repris( 'pactols_places' ) ); ?>">
			<label>Commentaires éventuels</label>
			<textarea name="commentaires" rows="3"><?php echo esc_textarea( $this->repris( 'commentaires' ) ); ?></textarea>
			<div class="na-intro na-spaced">
				Normes des illustrations : photographies 10 x 15 cm minimum à 300 dpi, dessins au trait 1200 dpi, fichiers PDF acceptés si nécessaire. Les fichiers sources lourds doivent être transmis par un autre moyen.
			</div>
			<label>Illustrations</label>
			<p class="na-help">Formats acceptés : jpg, jpeg, tiff, tif, pdf. 3 fichiers maximum, 20 Mo maximum au total.</p>
			<input type="file" name="illustrations[]" id="na-illustrations" multiple accept=".jpg,.jpeg,.tiff,.tif,.pdf">
			<ul class="na-filelist" id="na-filelist"></ul>
			<p class="na-help">Chaque fichier déposé reçoit ci-dessous sa ligne : titre, légende, crédits. Joindre les autorisations pour les documents dont vous ne détenez pas les droits.</p>
			<div class="na-intro na-spaced">
				Les informations transmises via ce formulaire sont utilisées uniquement pour l'instruction éditoriale des notices destinées à la Chronique d'<em>Archéologie médiévale</em>. Elles sont adressées à la rédaction de la revue et ne sont pas utilisées à d'autres fins. Elles sont conservées pendant la durée nécessaire au traitement éditorial de la notice. Pour toute demande relative à ces données, vous pouvez contacter la rédaction à l'adresse indiquée sur le site.
			</div>
			<?php if ( $this->protection_utilise_la_question() ) : $defi = $this->defi_du_puzzle(); ?>
				<div class="na-question" id="na-puzzle"
					data-cible="<?php echo esc_attr( $defi['cible'] ); ?>"
					data-tolerance="<?php echo esc_attr( self::PUZZLE_TOLERANCE ); ?>">
					<label for="na-puzzle-poignee">Faites glisser la pièce jusque dans son encoche</label>
					<p class="na-help">Une vérification pour distinguer un lecteur d’un
					robot. Au clavier : atteignez le curseur par la tabulation, déplacez-le
					avec les flèches — la touche Majuscule enfoncée pour aller plus vite —,
					puis validez par Entrée.</p>
					<div class="na-puzzle-scene">
						<canvas class="na-puzzle-fond" id="na-puzzle-fond" width="320" height="150"
							role="img" aria-label="Image de vérification portant une encoche à combler"></canvas>
						<canvas class="na-puzzle-piece" id="na-puzzle-piece" width="59" height="48"
							aria-hidden="true"></canvas>
					</div>
					<div class="na-puzzle-piste">
						<div class="na-puzzle-poignee" id="na-puzzle-poignee" tabindex="0"
							role="slider" aria-label="Position de la pièce"
							aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"
							aria-describedby="na-puzzle-etat"></div>
					</div>
					<span class="na-puzzle-etat" id="na-puzzle-etat" role="status">À faire glisser</span>
					<button type="button" class="na-puzzle-rejouer" id="na-puzzle-rejouer">Recommencer</button>
					<input type="hidden" name="na_curseur" id="na-curseur-valeur" value="">
					<input type="hidden" name="na_preuve" value="<?php echo esc_attr( $defi['preuve'] ); ?>">
				</div>
			<?php endif; ?>
			<?php if ( $this->protection_utilise_turnstile() && $this->turnstile_site_key_is_set() ) : ?>
				<div class="na-turnstile" style="margin-top:20px;">
					<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $this->turnstile_site_key() ); ?>" data-theme="light" data-language="fr"></div>
				</div>
			<?php endif; ?>
			<button type="submit" name="notice_archeomed_envoi" value="1" class="na-submit">Envoyer la notice</button>
			<div class="na-envoi" id="na-envoi" role="status" aria-live="polite">
				<div class="na-envoi-piste"><div class="na-envoi-barre"></div></div>
				<p class="na-envoi-mot">Envoi en cours — le document se fabrique et les
				illustrations montent. Cela peut prendre quelques instants ; ne fermez pas
				cette page et ne cliquez pas une seconde fois.</p>
			</div>
		</form>
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var jsErrorBox = document.getElementById('na-js-error');
			function showJsError(msg) {
				jsErrorBox.textContent = msg;
				jsErrorBox.style.display = 'block';
			}
			var quill = null;
			if (typeof Quill === 'undefined') {
				showJsError('Erreur technique : l\u2019éditeur de texte ne s\u2019est pas chargé.');
			} else {
				quill = new Quill('#na-editor', {
					theme: 'snow',
					modules: {
						toolbar: [
							['bold', 'italic'],
							[{ 'script': 'super' }, { 'script': 'sub' }],
							['clean']
						]
					}
				});
				// Le texte revient dans l'éditeur après un renvoi manqué : il
				// était gardé dans le champ caché, dont Quill ne sait rien.
				var texteGarde = document.getElementById('na-texte-notice').value;
				if (texteGarde) {
					quill.clipboard.dangerouslyPasteHTML(texteGarde);
				}
			}
			var wc = document.getElementById('na-wordcount');
			function countWords() {
				if (!quill) {
					wc.textContent = 'éditeur indisponible';
					return 0;
				}
				var text = quill.getText().trim();
				var n = text.length ? text.split(/\s+/).length : 0;
				wc.textContent = n + (n > 1 ? ' mots' : ' mot');
				wc.classList.toggle('na-out', n > 0 && (n < 300 || n > 700));
				return n;
			}
			if (quill) {
				quill.on('text-change', countWords);
			}
			countWords();
			var deptInput = document.getElementById('na-departement');
			var timer = null;

			// Extrait "26678/pcrtd1Ms3ERUXz" depuis une URI ARK complète.
			function arkPathFromUri(uri) {
				var m = String(uri).match(/ark:\/(.+)$/);
				return m ? m[1] : '';
			}
			// À partir de l'ARK d'une commune, renvoie une promesse du nom de département
			// (chaîne vide si introuvable). Le département est l'avant-dernier élément du
			// chemin (le dernier étant la commune). On nettoie le préfixe "Département du/de...".
			function getDepartmentFromArk(arkUri) {
				var arkPath = arkPathFromUri(arkUri);
				if (!arkPath) { return Promise.resolve(''); }
				var url = 'https://pactols.frantiq.fr/api/searchwidgetbyark?q='
					+ encodeURIComponent(arkPath) + '&lang=fr';
				return fetch(url, { headers: { 'Accept': 'application/json' } })
					.then(function (r) { return r.ok ? r.json() : null; })
					.then(function (data) {
						if (!Array.isArray(data) || !data.length) { return ''; }
						var path = Array.isArray(data[0]) ? data[0] : data;
						if (path.length < 2) { return ''; }
						var deptLabel = (path[path.length - 2] && path[path.length - 2].label)
							? path[path.length - 2].label : '';
						if (deptLabel.indexOf('épartement') === -1) {
							for (var i = path.length - 2; i >= 0; i--) {
								if (path[i].label && path[i].label.indexOf('épartement') !== -1) {
									deptLabel = path[i].label;
									break;
								}
							}
						}
						return deptLabel.replace(/^D[ée]partement\s+(du|de la|de l['’]|des|de)\s+/i, '').trim();
					})
					.catch(function () { return ''; });
			}
			// Chaque ligne de lieu se complète pour son propre compte : une
			// opération porte parfois sur quatre communes, et il n'y a pas de
			// raison que la deuxième soit moins bien servie que la première.
			// Seule la première renseigne la parenthèse — c'est d'elle qu'on
			// tire le département, et les suivantes sont du même.
			function armerLeLieu(ligne, rang) {
				var champ = ligne.querySelector('.na-commune');
				var ark = ligne.querySelector('.na-commune-ark');
				var boite = ligne.querySelector('.na-suggestions');
				if (!champ || !ark || !boite) { return; }
				var actif = -1;

				function vider() {
					boite.innerHTML = '';
					boite.style.display = 'none';
					actif = -1;
				}
				ligne.viderLesSuggestions = vider;

				champ.addEventListener('input', function () {
					var q = champ.value.trim();
					ark.value = '';
					if (timer) { clearTimeout(timer); }
					if (q.length < 3) { vider(); return; }
					timer = setTimeout(function () {
						var url = 'https://pactols.frantiq.fr/api/autocomplete/'
							+ encodeURIComponent(q) + '?lang=fr&theso=th17';
						fetch(url, { headers: { 'Accept': 'application/json' } })
							.then(function (r) { return r.ok ? r.json() : []; })
							.then(function (data) {
								boite.innerHTML = '';
								if (!Array.isArray(data) || data.length === 0) { vider(); return; }
								var vus = {};
								data.forEach(function (c) {
									if (!c || !c.label || !c.uri || vus[c.uri]) { return; }
									vus[c.uri] = true;
									var li = document.createElement('li');
									var etiquette = document.createElement('span');
									etiquette.textContent = c.label;
									li.appendChild(etiquette);
									// Le département entre parenthèses distingue les
									// communes homonymes, qui sont légion.
									getDepartmentFromArk(c.uri).then(function (dept) {
										if (dept) { etiquette.textContent = c.label + ' (' + dept + ')'; }
									});
									li.addEventListener('click', function () {
										champ.value = c.label;
										ark.value = c.uri;
										vider();
										if (0 === rang) {
											deptInput.value = '';
											getDepartmentFromArk(c.uri).then(function (dept) {
												if (dept) { deptInput.value = dept; }
											});
										}
									});
									boite.appendChild(li);
								});
								boite.style.display = 'block';
							}).catch(function () { vider(); });
					}, 250);
				});

				champ.addEventListener('keydown', function (e) {
					var items = boite.querySelectorAll('li');
					if (boite.style.display === 'none' || items.length === 0) { return; }
					if (e.key === 'ArrowDown') {
						e.preventDefault();
						actif = Math.min(actif + 1, items.length - 1);
					} else if (e.key === 'ArrowUp') {
						e.preventDefault();
						actif = Math.max(actif - 1, 0);
					} else if (e.key === 'Enter' && actif >= 0) {
						e.preventDefault();
						items[actif].click();
						return;
					} else {
						return;
					}
					items.forEach(function (it, i) { it.classList.toggle('na-active', i === actif); });
				});
			}

			var lignesDeLieu = [].slice.call(document.querySelectorAll('.na-lieu'));
			lignesDeLieu.forEach(armerLeLieu);

			document.addEventListener('click', function (e) {
				lignesDeLieu.forEach(function (ligne) {
					if (!ligne.contains(e.target) && ligne.viderLesSuggestions) {
						ligne.viderLesSuggestions();
					}
				});
				document.querySelectorAll('.na-pactols-suggestions').forEach(function (box) {
					if (!box.contains(e.target) && !box.parentNode.contains(e.target)) {
						box.innerHTML = '';
						box.style.display = 'none';
					}
				});
			});
			function pactolsSearch(type, q) {
				// Appel direct à l'API Pactols depuis le navigateur (comme geo.api.gouv.fr).
				// URL sans /opentheso/ : ce préfixe provoquait une redirection 404 sans
				// en-têtes CORS, d'où l'erreur "No Access-Control-Allow-Origin" trompeuse.
				var theso = (type === 'place') ? 'th17' : 'TH_1';
				var url = 'https://pactols.frantiq.fr/api/autocomplete/' + encodeURIComponent(q)
					+ '?lang=fr&theso=' + encodeURIComponent(theso);
				// Périodes : restreindre à la branche P2-Entités temporelles (groupe g124),
				// pour ne pas proposer tout le thésaurus Sujets.
				if (type === 'period') {
					url += '&group=g124';
				}
				return fetch(url, { headers: { 'Accept': 'application/json' } })
					.then(function (r) {
						if (!r.ok) { return []; }
						return r.json();
					})
					.then(function (data) {
						if (!Array.isArray(data)) { return []; }
						var seen = {};
						var out = [];
						data.forEach(function (item) {
							if (!item || !item.label || !item.uri) { return; }
							if (seen[item.uri]) { return; }
							seen[item.uri] = true;
							out.push({
								label: item.label,
								ark: item.uri,
								idConcept: item.identifier || '',
								fullpath: ''
							});
						});
						return out.slice(0, 20);
					})
					.catch(function () { return []; });
			}
			// Un terme retiré du thésaurus s'indexe dans le vide : la chaîne
			// refuse de l'enrichir, et le mot-clé reste nu sans que personne
			// l'ait vu passer. L'autocomplétion les propose comme les autres —
			// elle ne rend qu'une étiquette et un identifiant. On va donc le
			// demander, une fois, au moment où quelqu'un choisit.
			//
			// La vérification ne bloque rien : le terme est déjà posé quand
			// elle part, et si Pactols ne répond pas, le dépôt suit son cours.
			function verifierLeConcept(field, item, type) {
				var zone = field.querySelector('[data-pactols-avis]');
				if (zone) { zone.remove(); }
				if (!item.idConcept) { return; }
				var theso = (type === 'place') ? 'th17' : 'TH_1';
				var url = 'https://pactols.frantiq.fr/openapi/v1/concept/'
					+ encodeURIComponent(theso) + '/' + encodeURIComponent(item.idConcept);
				fetch(url, { headers: { 'Accept': 'application/json' } })
					.then(function (r) { return r.ok ? r.json() : null; })
					.then(function (data) {
						if (!data || !data[item.ark]) { return; }
						var n = data[item.ark];
						var dep = n['http://www.w3.org/2002/07/owl#deprecated'];
						if (!dep || !dep.length) { return; }
						var rep = n['http://purl.org/dc/terms/isReplacedBy'];
						poserUnAvis(field, item, type, rep && rep.length ? rep[0].value : '');
					})
					.catch(function () {});
			}
			// L'avis nomme le remplaçant et propose de le prendre : signaler un
			// terme mort sans dire par quoi le remplacer laisse le travail
			// entier à qui dépose.
			function poserUnAvis(field, item, type, remplacant) {
				var zone = document.createElement('p');
				zone.setAttribute('data-pactols-avis', '1');
				zone.className = 'na-pactols-avis';
				zone.textContent = '« ' + item.label + ' » a été retiré du thésaurus Pactols.';
				field.appendChild(zone);
				if (!remplacant) { return; }
				var theso = (type === 'place') ? 'th17' : 'TH_1';
				var seg = remplacant.split('/').pop();
				fetch('https://pactols.frantiq.fr/openapi/v1/concept/'
						+ encodeURIComponent(theso) + '/' + encodeURIComponent(seg),
					{ headers: { 'Accept': 'application/json' } })
					.then(function (r) { return r.ok ? r.json() : null; })
					.then(function (data) {
						if (!data || !data[remplacant]) { return; }
						var labels = data[remplacant]['http://www.w3.org/2004/02/skos/core#prefLabel'] || [];
						var fr = '';
						var id = data[remplacant]['http://purl.org/dc/terms/identifier'];
						labels.forEach(function (l) { if (l.lang === 'fr') { fr = l.value; } });
						if (!fr) { return; }
						zone.textContent = '« ' + item.label + ' » a été retiré du thésaurus Pactols. Il est remplacé par « ' + fr + ' ». ';
						var b = document.createElement('button');
						b.type = 'button';
						b.className = 'na-pactols-remplacer';
						b.textContent = 'Prendre « ' + fr + ' »';
						b.addEventListener('click', function () {
							var neuf = { label: fr, ark: remplacant,
								idConcept: (id && id.length) ? id[0].value : '', fullpath: '' };
							var input = field.querySelector('.na-pactols-input');
							var hidden = field.querySelector('.na-pactols-hidden');
							input.value = fr;
							input.classList.add('na-pactols-selected');
							hidden.value = JSON.stringify(neuf);
							zone.remove();
						});
						zone.appendChild(b);
					})
					.catch(function () {});
			}
			function renderPactolsSuggestions(field, items, type) {
				var box = field.querySelector('.na-pactols-suggestions');
				var input = field.querySelector('.na-pactols-input');
				var hidden = field.querySelector('.na-pactols-hidden');
				box.innerHTML = '';
				if (!items.length) {
					box.style.display = 'none';
					return;
				}
				items.forEach(function (item) {
					var li = document.createElement('li');
					var labelSpan = document.createElement('span');
					labelSpan.textContent = item.label;
					li.appendChild(labelSpan);
					// Pour les lieux, on complète avec le département entre parenthèses
					// dès que disponible (utile pour distinguer les communes homonymes).
					if (type === 'place' && item.ark) {
						getDepartmentFromArk(item.ark).then(function (dept) {
							if (dept) { labelSpan.textContent = item.label + ' (' + dept + ')'; }
						});
					}
					li.addEventListener('click', function () {
						input.value = item.label;
						input.classList.add('na-pactols-selected');
						hidden.value = JSON.stringify(item);
						box.innerHTML = '';
						box.style.display = 'none';
						verifierLeConcept(field, item, type);
					});
					box.appendChild(li);
				});
				box.style.display = 'block';
			}
			document.querySelectorAll('.na-pactols-field').forEach(function (field) {
				var input = field.querySelector('.na-pactols-input');
				var hidden = field.querySelector('.na-pactols-hidden');
				var pTimer = null;
				input.addEventListener('input', function () {
					var q = input.value.trim();
					input.classList.remove('na-pactols-selected');
					hidden.value = '';
					if (pTimer) { clearTimeout(pTimer); }
					if (q.length < 3) {
						renderPactolsSuggestions(field, [], input.getAttribute('data-pactols-type'));
						return;
					}
					pTimer = setTimeout(function () {
						var t = input.getAttribute('data-pactols-type');
						pactolsSearch(t, q).then(function (items) {
							renderPactolsSuggestions(field, items, t);
						});
					}, 300);
				});
			});
			// Les mots-clés déjà choisis, quand la page revient d'un renvoi
			// manqué : le champ caché les porte, il reste à regarnir les cases
			// visibles pour qu'on les retrouve tels qu'on les avait posés.
			function reprendrePactols(type) {
				var garde = document.getElementById('pactols_' + type);
				if (!garde || !garde.value) { return; }
				var items = [];
				try { items = JSON.parse(garde.value); } catch (e) { return; }
				if (!Array.isArray(items) || items.length === 0) { return; }
				var cle = (type === 'periods') ? 'period'
					: (type === 'subjects') ? 'subject' : 'place';
				var caches = document.querySelectorAll(
					'.na-pactols-hidden[data-pactols-hidden="' + cle + '"]');
				items.forEach(function (item, i) {
					if (i >= caches.length || !item || !item.label) { return; }
					caches[i].value = JSON.stringify(item);
					var champ = caches[i].parentNode.querySelector('.na-pactols-input');
					if (champ) { champ.value = item.label; }
				});
			}
			['periods', 'subjects', 'places'].forEach(reprendrePactols);

			function collectPactols(type) {
				var values = [];
				document.querySelectorAll('.na-pactols-hidden[data-pactols-hidden="' + type + '"]').forEach(function (hidden) {
					if (!hidden.value) { return; }
					try {
						var item = JSON.parse(hidden.value);
						if (item && item.label) {
							values.push(item);
						}
					} catch (e) {}
				});
				return values;
			}
			function hasUnselectedPactolsInput() {
				var bad = false;
				document.querySelectorAll('.na-pactols-field').forEach(function (field) {
					var input = field.querySelector('.na-pactols-input');
					var hidden = field.querySelector('.na-pactols-hidden');
					if (input.value.trim() !== '' && hidden.value.trim() === '') {
						bad = true;
					}
				});
				return bad;
			}
			var fileInput = document.getElementById('na-illustrations');
			var fileList = document.getElementById('na-filelist');
			var selected = [];
			var MAX_FILES = <?php echo (int) self::MAX_FILES; ?>;
			var MAX_TOTAL_SIZE = <?php echo (int) self::MAX_TOTAL_FILESIZE; ?>;
			var ALLOWED = <?php echo wp_json_encode( self::ALLOWED_EXT ); ?>;
			function extOf(name) {
				var parts = name.toLowerCase().split('.');
				return parts.length > 1 ? parts.pop() : '';
			}
			function totalSelectedSize() {
				return selected.reduce(function (sum, f) { return sum + f.size; }, 0);
			}
			function formatMo(bytes) {
				return Math.round(bytes / 1024 / 1024 * 10) / 10;
			}
			function refreshFileInput() {
				var dt = new DataTransfer();
				selected.forEach(function (f) { dt.items.add(f); });
				fileInput.files = dt.files;
				renderFileList();
			}
			// Ce que l'auteur a écrit sur chaque illustration, gardé à part
			// des fichiers eux-mêmes : la liste se redessine à chaque retrait,
			// et retaper trois légendes parce qu'on a enlevé une image serait
			// le genre de détail qui fait abandonner un formulaire.
			var illusMeta = <?php
				$garde_illus = array();
				$titres = $this->reprise();
				$combien = isset( $titres['illus_titre'] ) ? count( (array) $titres['illus_titre'] ) : 0;
				for ( $i = 0; $i < $combien; $i++ ) {
					$garde_illus[] = array(
						'titre'   => isset( $titres['illus_titre'][ $i ] ) ? $titres['illus_titre'][ $i ] : '',
						'legende' => isset( $titres['illus_legende'][ $i ] ) ? $titres['illus_legende'][ $i ] : '',
						'credits' => isset( $titres['illus_credits'][ $i ] ) ? $titres['illus_credits'][ $i ] : '',
					);
				}
				echo wp_json_encode( $garde_illus );
			?>;

			function champIllustration(idx, clef, libelle, lignes) {
				var enveloppe = document.createElement('label');
				enveloppe.textContent = libelle;
				var champ = document.createElement(lignes ? 'textarea' : 'input');
				if (lignes) {
					champ.rows = lignes;
				} else {
					champ.type = 'text';
				}
				champ.name = 'illus_' + clef + '[]';
				champ.value = illusMeta[idx] && illusMeta[idx][clef] ? illusMeta[idx][clef] : '';
				champ.addEventListener('input', function () {
					if (!illusMeta[idx]) { illusMeta[idx] = {}; }
					illusMeta[idx][clef] = champ.value;
				});
				enveloppe.appendChild(champ);
				return enveloppe;
			}

			function renderFileList() {
				fileList.innerHTML = '';
				selected.forEach(function (f, idx) {
					if (!illusMeta[idx]) { illusMeta[idx] = {}; }
					var li = document.createElement('li');
					li.className = 'na-illus';

					var entete = document.createElement('div');
					entete.className = 'na-illus-nom';
					entete.textContent = 'Fig. ' + (idx + 1) + ' — ' + f.name
						+ ' (' + formatMo(f.size) + ' Mo)';
					var rm = document.createElement('span');
					rm.className = 'na-fileremove';
					rm.textContent = 'x retirer';
					rm.addEventListener('click', function () {
						selected.splice(idx, 1);
						illusMeta.splice(idx, 1);
						refreshFileInput();
					});
					entete.appendChild(rm);
					li.appendChild(entete);

					var champs = document.createElement('div');
					champs.className = 'na-illus-champs';
					champs.appendChild(champIllustration(idx, 'titre', 'Titre', 0));
					champs.appendChild(champIllustration(idx, 'legende', 'Légende', 2));
					champs.appendChild(champIllustration(idx, 'credits', 'Crédits (auteur, source, droits)', 0));
					li.appendChild(champs);

					fileList.appendChild(li);
				});
				if (selected.length > 0) {
					var total = document.createElement('li');
					total.className = 'na-illus-total';
					total.textContent = 'Total : ' + formatMo(totalSelectedSize()) + ' Mo / 20 Mo';
					fileList.appendChild(total);
				}
			}
			fileInput.addEventListener('change', function () {
				var incoming = Array.prototype.slice.call(fileInput.files);
				var refused = [];
				incoming.forEach(function (f) {
					var ext = extOf(f.name);
					var dup = selected.some(function (s) { return s.name === f.name && s.size === f.size; });
					if (dup) { return; }
					if (selected.length >= MAX_FILES) {
						refused.push(f.name + ' : 3 fichiers maximum');
						return;
					}
					if (ALLOWED.indexOf(ext) === -1) {
						refused.push(f.name + ' : format refusé');
						return;
					}
					if ((totalSelectedSize() + f.size) > MAX_TOTAL_SIZE) {
						refused.push(f.name + ' : limite totale de 20 Mo dépassée');
						return;
					}
					selected.push(f);
				});
				refreshFileInput();
				if (refused.length) {
					alert('Certains fichiers ont été refusés :\n' + refused.join('\n'));
				}
			});
			// La pièce de vérification. Glissée dans son encoche, elle dépose la
			// valeur scellée que le serveur attend. Le formulaire réclame déjà
			// JavaScript pour son éditeur : on ne perd personne en le réclamant
			// ici aussi.
			(function () {
				var bloc = document.getElementById('na-puzzle');
				if (!bloc) { return; }
				var toile   = document.getElementById('na-puzzle-fond');
				var piece   = document.getElementById('na-puzzle-piece');
				var poignee = document.getElementById('na-puzzle-poignee');
				var etat    = document.getElementById('na-puzzle-etat');
				var champ   = document.getElementById('na-curseur-valeur');
				var rejouer = document.getElementById('na-puzzle-rejouer');

				var CIBLE     = parseInt(bloc.getAttribute('data-cible'), 10);
				var TOLERANCE = parseInt(bloc.getAttribute('data-tolerance'), 10);
				var L = 320, H = 150;          // résolution interne de la toile
				var COTE = 46;                 // côté de la pièce, en unités de toile
				var LANG = Math.ceil(COTE * 0.22) + 2;  // la languette déborde à droite
				var MARGE = 6;
				var utile = L - COTE - MARGE * 2;   // course possible du bord gauche
				var posY = Math.round((H - COTE) / 2);
				var pos = 0;                   // position courante, en centièmes
				var gagne = false;

				// ── Le tracé d'une pièce de puzzle : un carré arrondi, une languette à
				// droite, une encoche en haut. Dessiné à l'origine, à charge de l'appelant
				// de translater le contexte.
				function tracer(ctx, c) {
					var t = c * 0.22;                     // rayon des languettes
					var m = c / 2;
					ctx.beginPath();
					ctx.moveTo(0, 0);
					ctx.lineTo(m - t, 0);
					ctx.arc(m, 0, t, Math.PI, 0, true);   // encoche en haut (creux)
					ctx.lineTo(c, 0);
					ctx.lineTo(c, m - t);
					ctx.arc(c, m, t, -Math.PI / 2, Math.PI / 2, false); // languette à droite
					ctx.lineTo(c, c);
					ctx.lineTo(0, c);
					ctx.closePath();
				}

				// ── Un fond dessiné plutôt qu'une image à servir : rien à téléverser, et
				// il change à chaque affichage, ce qui rend la comparaison de pixels
				// inutile à qui voudrait résoudre le défi de tête.
				function fond(ctx) {
					var d = ctx.createLinearGradient(0, 0, L, H);
					d.addColorStop(0, '#3b4a6b');
					d.addColorStop(1, '#8a6d3b');
					ctx.fillStyle = d;
					ctx.fillRect(0, 0, L, H);
					for (var i = 0; i < 14; i++) {
						ctx.beginPath();
						ctx.globalAlpha = 0.10 + Math.random() * 0.16;
						ctx.fillStyle = i % 2 ? '#ffffff' : '#000000';
						var r = 14 + Math.random() * 46;
						ctx.arc(Math.random() * L, Math.random() * H, r, 0, Math.PI * 2);
						ctx.fill();
					}
					ctx.globalAlpha = 1;
				}

				var scene = document.createElement('canvas');
				scene.width = L; scene.height = H;
				fond(scene.getContext('2d'));

				// Le fond, avec le trou à la place de la pièce.
				var cx = toile.getContext('2d');
				var xCible = MARGE + Math.round(utile * CIBLE / 100);
				cx.drawImage(scene, 0, 0);
				cx.save();
				cx.translate(xCible, posY);
				tracer(cx, COTE);
				cx.restore();
				cx.save();
				cx.translate(xCible, posY);
				tracer(cx, COTE);
				cx.fillStyle = 'rgba(0,0,0,0.55)';
				cx.fill();
				cx.lineWidth = 1.5;
				cx.strokeStyle = 'rgba(255,255,255,0.65)';
				cx.stroke();
				cx.restore();

				// La pièce : les pixels du fond qui manquent au trou. Son canevas doit
				// être plus large que le côté — la languette déborde à droite, et elle
				// était tout bonnement rognée.
				piece.width  = COTE + LANG;
				piece.height = COTE + 2;
				piece.style.width = (100 * (COTE + LANG) / L) + '%';
				var px = piece.getContext('2d');
				px.save();
				tracer(px, COTE);
				px.clip();
				px.drawImage(scene, -xCible, -posY);
				px.restore();
				px.save();
				tracer(px, COTE);
				px.lineWidth = 1.5;
				px.strokeStyle = 'rgba(255,255,255,0.85)';
				px.stroke();
				px.restore();

				function placer(p) {
					pos = Math.max(0, Math.min(100, p));
					piece.style.left = (100 * (MARGE + utile * pos / 100) / L) + '%';
					// La poignée reste dans la piste : posée à « pos % », elle se décale
					// d'autant de sa propre largeur. À zéro elle affleure à gauche, à cent
					// à droite, et jamais elle ne déborde du cadre.
					poignee.style.left = pos + '%';
					poignee.style.transform = 'translate(' + (-pos) + '%, -50%)';
					poignee.setAttribute('aria-valuenow', Math.round(pos));
				}

				function verifier() {
					if (Math.abs(pos - CIBLE) <= TOLERANCE) {
						gagne = true;
						champ.value = String(CIBLE);
						bloc.classList.add('na-fait');
						etat.textContent = '✓ Vérification réussie';
						placer(CIBLE);
						poignee.setAttribute('aria-disabled', 'true');
						return true;
					}
					etat.textContent = 'Pas tout à fait : la pièce doit combler l’encoche.';
					bloc.classList.add('na-rate');
					window.setTimeout(function () { bloc.classList.remove('na-rate'); }, 600);
					placer(0);
					return false;
				}

				// ── Le déplacement. Événements pointeur : une seule écriture pour la
				// souris, le doigt et le stylet, et le navigateur ne nous vole pas le
				// geste en cours de route grâce à la capture.
				var actif = false, depart = 0, posDepart = 0;
				var piste = poignee.parentElement;
				// La course de la poignée, en pixels d'écran. C'est la piste qu'on mesure,
				// et non la scène : la poignée doit rester sous le doigt, et non avancer
				// à la vitesse — plus lente — de la pièce sur son fond.
				function courseEcran() {
					return Math.max(1, piste.getBoundingClientRect().width
						- poignee.getBoundingClientRect().width);
				}
				poignee.addEventListener('pointerdown', function (e) {
					if (gagne) { return; }
					actif = true;
					depart = e.clientX;
					posDepart = pos;
					poignee.setPointerCapture(e.pointerId);
					bloc.classList.add('na-prise');
					e.preventDefault();
				});
				poignee.addEventListener('pointermove', function (e) {
					if (!actif) { return; }
					var d = (e.clientX - depart) / courseEcran() * 100;
					placer(posDepart + d);
					e.preventDefault();
				});
				function relacher(e, valide) {
					if (!actif) { return; }
					actif = false;
					bloc.classList.remove('na-prise');
					try { poignee.releasePointerCapture(e.pointerId); } catch (err) {}
					// Un geste interrompu — le navigateur reprend le pointeur pour faire
					// défiler la page — n'est pas une tentative ratée : on remet la pièce
					// sans rien reprocher.
					if (valide) { verifier(); } else { placer(0); }
				}
				poignee.addEventListener('pointerup', function (e) { relacher(e, true); });
				poignee.addEventListener('pointercancel', function (e) { relacher(e, false); });

				// ── Au clavier : les flèches déplacent, Entrée ou Espace valide.
				poignee.addEventListener('keydown', function (e) {
					if (gagne) { return; }
					var pas = e.shiftKey ? 10 : 2;
					// Les noms courts — « Right », « Left » — sont ceux des navigateurs
					// d'avant la norme actuelle. Les accepter ne coûte rien et évite un
					// clavier muet là où l'on ne pourra pas aller voir.
					var k = e.key;
					if (k === 'ArrowRight' || k === 'Right' || k === 'ArrowUp' || k === 'Up') { placer(pos + pas); }
					else if (k === 'ArrowLeft' || k === 'Left' || k === 'ArrowDown' || k === 'Down') { placer(pos - pas); }
					else if (k === 'Home') { placer(0); }
					else if (k === 'End') { placer(100); }
					else if (k === 'Enter' || k === ' ' || k === 'Spacebar') { verifier(); }
					else { return; }
					e.preventDefault();
				});

				rejouer.addEventListener('click', function () {
					if (!gagne) { placer(0); etat.textContent = 'À faire glisser'; }
				});

				placer(0);
				window.naPuzzleFait = function () { return gagne; };
				window.naPuzzleFocus = function () { poignee.focus(); };
			}());
			document.getElementById('na-form').addEventListener('submit', function (e) {
				if (!quill) {
					e.preventDefault();
					showJsError('Erreur technique : l\u2019éditeur de texte ne s\u2019est pas chargé.');
					return;
				}
				if (document.querySelectorAll('input[name="nature[]"]:checked').length === 0) {
					e.preventDefault();
					alert('Merci de cocher au moins une nature d\'opération.');
					return;
				}
				if (window.naPuzzleFait && !window.naPuzzleFait()) {
					e.preventDefault();
					alert('Faites glisser la pièce dans son encoche avant d\u2019envoyer.');
					window.naPuzzleFocus();
					return;
				}
				if (hasUnselectedPactolsInput()) {
					e.preventDefault();
					document.getElementById('na-pactols-warning').style.display = 'block';
					alert('Un ou plusieurs mots-clés Pactols ont été saisis sans sélection dans la liste. Merci de choisir une proposition afin de transmettre l\u2019ARK.');
					return;
				}
				document.getElementById('pactols_periods').value = JSON.stringify(collectPactols('period'));
				document.getElementById('pactols_subjects').value = JSON.stringify(collectPactols('subject'));
				document.getElementById('pactols_places').value = JSON.stringify(collectPactols('place'));
				var html = quill.root.innerHTML;
				var b64 = btoa(unescape(encodeURIComponent(html)));
				document.getElementById('na-texte-notice').value = 'b64:' + b64;
				// Tous les contrôles sont passés : la page part pour de bon.
				// On le montre, et l'on bloque le bouton — un second clic
				// déposerait la notice deux fois.
				var envoi = document.getElementById('na-envoi');
				if (envoi) { envoi.classList.add('na-visible'); }
				var bouton = document.querySelector('.na-submit');
				if (bouton) {
					bouton.disabled = true;
					bouton.textContent = 'Envoi en cours…';
				}
				// Le bouton désactivé n'est plus envoyé avec le formulaire :
				// son nom porte la valeur que le serveur attend, et il faut
				// donc la poster autrement.
				var relais = document.createElement('input');
				relais.type = 'hidden';
				relais.name = 'notice_archeomed_envoi';
				relais.value = '1';
				this.appendChild(relais);
			});
		});
		</script>
		<?php
		return ob_get_clean();
	}
	/**
	 * Le texte de la notice tel qu'il sort de l'éditeur, en HTML propre.
	 *
	 * Quill l'envoie encodé : du HTML brut dans un champ de formulaire se
	 * fait mutiler par les couches qu'il traverse. Le dépôt le décodait, la
	 * mise de côté non — si bien qu'après un renvoi manqué, l'éditeur se
	 * remplissait de « b64:PHA+bGUgdGV4… » et l'auteur devait tout retaper.
	 * C'est aussi ce qui aurait vidé de son sens le lien de correction.
	 */
	private function texte_notice_pose( $brut ) {
		$brut = (string) $brut;
		if ( 0 === strpos( $brut, 'b64:' ) ) {
			$decode = base64_decode( substr( $brut, 4 ), true );
			$brut   = ( false !== $decode ) ? $decode : '';
		}
		return $this->limit_string( $this->clean_richtext( $brut ), 30000 );
	}

	private function clean_richtext( $html ) {
		$allowed = array(
			'em' => array(),
			'strong' => array(),
			'i' => array(),
			'b' => array(),
			'sup' => array(),
			'sub' => array(),
			'p' => array(),
			'br' => array(),
		);
		return wp_kses( $html, $allowed );
	}
	private function limit_string( $value, $max ) {
		$value = (string) $value;
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}
	private function collect_select( $name, $allowed, $required = false ) {
		$val = isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : '';
		if ( '' === $val && ! $required ) {
			return '';
		}
		return in_array( $val, $allowed, true ) ? $val : '';
	}
	private function collect_checkbox( $name, $allowed ) {
		if ( empty( $_POST[ $name ] ) || ! is_array( $_POST[ $name ] ) ) {
			return '';
		}
		// $allowed peut être une liste simple ou un tableau associatif (libellé => ARK).
		$labels = $this->is_assoc( $allowed ) ? array_keys( $allowed ) : $allowed;
		$values = array();
		foreach ( wp_unslash( $_POST[ $name ] ) as $val ) {
			$val = sanitize_text_field( $val );
			if ( in_array( $val, $labels, true ) ) {
				$values[] = $val;
			}
		}
		return implode( ', ', $values );
	}
	/**
	 * Collecte les natures cochées avec leur ARK : renvoie un tableau d'items { label, ark }.
	 */
	private function collect_natures_items() {
		$items = array();
		if ( empty( $_POST['nature'] ) || ! is_array( $_POST['nature'] ) ) {
			return $items;
		}
		foreach ( wp_unslash( $_POST['nature'] ) as $val ) {
			$val = sanitize_text_field( $val );
			if ( isset( $this->natures[ $val ] ) ) {
				$items[] = array(
					'label'     => $val,
					'ark'       => $this->natures[ $val ],
					'idConcept' => '',
				);
			}
		}
		return $items;
	}
	private function is_assoc( $arr ) {
		if ( ! is_array( $arr ) || array() === $arr ) {
			return false;
		}
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}
	private function collect_pactols_keywords( $field_name ) {
		$raw = isset( $_POST[ $field_name ] ) ? wp_unslash( $_POST[ $field_name ] ) : '';
		if ( '' === $raw ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		$out = array();
		foreach ( $decoded as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$label = isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '';
			$ark = isset( $item['ark'] ) ? esc_url_raw( $item['ark'] ) : '';
			$id_concept = isset( $item['idConcept'] ) ? sanitize_text_field( $item['idConcept'] ) : '';
			$fullpath = isset( $item['fullpath'] ) ? sanitize_text_field( $item['fullpath'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$out[] = array(
				'label' => $this->limit_string( $label, 250 ),
				'ark' => $this->limit_string( $ark, 500 ),
				'idConcept' => $this->limit_string( $id_concept, 120 ),
				'fullpath' => $this->limit_string( $fullpath, 800 ),
			);
		}
		return array_slice( $out, 0, self::PACTOLS_FIELD_COUNT );
	}
	private function pactols_keywords_to_text( $items ) {
		if ( empty( $items ) ) {
			return '';
		}
		$lines = array();
		foreach ( $items as $item ) {
			$line = $item['label'];
			if ( ! empty( $item['ark'] ) ) {
				$line .= ' [' . $item['ark'] . ']';
			} elseif ( ! empty( $item['idConcept'] ) ) {
				$line .= ' [ID concept : ' . $item['idConcept'] . ']';
			}
			$lines[] = $line;
		}
		return implode( ' ; ', $lines );
	}
	/**
	 * Les lieux d'une notice, quelle que soit l'époque où elle a été déposée.
	 *
	 * Les notices reçues avant que le champ ne se répète portent une commune
	 * unique ; le fascicule les refabrique pourtant, et elles doivent sortir
	 * comme les autres.
	 */
	private function lieux_de( $d ) {
		if ( ! empty( $d['lieux'] ) && is_array( $d['lieux'] ) ) {
			return $d['lieux'];
		}
		$nom = isset( $d['commune'] ) ? (string) $d['commune'] : '';
		if ( '' === trim( $nom ) ) {
			return array();
		}
		return array( array(
			'nom' => $nom,
			'ark' => isset( $d['commune_ark'] ) ? (string) $d['commune_ark'] : '',
		) );
	}

	/** « Beneuvre, Bure-les-Temple, Duesme, Vanvey », sans la parenthèse. */
	private function lieux_en_ligne( $d ) {
		$noms = array();
		foreach ( $this->lieux_de( $d ) as $lieu ) {
			$noms[] = $lieu['nom'];
		}
		return implode( ', ', $noms );
	}

	/**
	 * « IV. Sépultures et nécropoles » devient « IV. – Sépultures et nécropoles ».
	 *
	 * Le tiret sépare le numéro du libellé dans le volume ; la liste des
	 * rubriques, elle, reste telle qu'on la coche dans le formulaire.
	 */
	public function titre_de_rubrique( $rubrique ) {
		$rubrique = trim( (string) $rubrique );
		if ( preg_match( '/^([IVX]+\.)\s*(.+)$/u', $rubrique, $m ) ) {
			return $m[1] . ' – ' . $m[2];
		}
		return $rubrique;
	}

	/**
	 * « IV. 1. – Opérations de terrain » : la sous-rubrique, numérotée sous sa
	 * rubrique. C'est la division que la Chronique donne à lire, et elle
	 * s'écrit comme la revue l'écrit — chiffre romain, chiffre arabe, tiret
	 * long.
	 */
	public function titre_de_famille( $d ) {
		$rubrique = isset( $d['rubrique_principale'] ) ? (string) $d['rubrique_principale'] : '';
		$numero   = '';
		if ( preg_match( '/^([IVX]+)\./u', trim( $rubrique ), $m ) ) {
			$numero = $m[1];
		}
		$famille = $this->famille_de( $d );
		return ( '' !== $numero ? $numero . '. ' . $famille['rang'] . '. – ' : '' )
			. $famille['nom'];
	}

	/**
	 * La précision entre parenthèses, débarrassée des parenthèses.
	 *
	 * Le document les pose lui-même : celles que l'on saisit feraient double
	 * emploi. Et le nettoyage d'avant était pire que le mal — il ôtait toute
	 * parenthèse finale, si bien qu'une saisie entièrement parenthésée,
	 * « (Champagne, Alsace, Lorraine) », se vidait d'un coup sans que
	 * personne ne s'en aperçoive avant de lire le document.
	 */
	private function sans_parentheses( $valeur ) {
		$valeur = trim( (string) $valeur );
		// On dépile, car on voit passer « ((Côte-d'Or)) ».
		while ( '' !== $valeur && '(' === substr( $valeur, 0, 1 )
				&& ')' === substr( $valeur, -1 ) ) {
			$valeur = trim( substr( $valeur, 1, -1 ) );
		}
		// Et l'on ôte celles qui resteraient aux extrémités seulement.
		return trim( $valeur, " \t\n\r\0\x0B()" );
	}

	/**
	 * Les lieux de l'opération, dans l'ordre saisi, chacun avec son ARK.
	 *
	 * Une opération porte parfois sur plusieurs communes — « Beneuvre,
	 * Bure-les-Temple, Duesme, Vanvey (Côte-d'Or) » —, et parfois sur un
	 * territoire qui n'est pas une commune du tout : « Grand Est ». Pactols
	 * connaît les deux, et c'est le même champ qui les reçoit.
	 */
	private function collect_lieux() {
		$noms = isset( $_POST['commune'] ) ? wp_unslash( $_POST['commune'] ) : '';
		$arks = isset( $_POST['commune_ark'] ) ? wp_unslash( $_POST['commune_ark'] ) : '';
		// Les envois d'avant la répétition portaient une chaîne.
		$noms = is_array( $noms ) ? array_values( $noms ) : array( (string) $noms );
		$arks = is_array( $arks ) ? array_values( $arks ) : array( (string) $arks );

		$lieux = array();
		foreach ( $noms as $rang => $nom ) {
			if ( count( $lieux ) >= self::MAX_LIEUX ) {
				break;
			}
			$nom = $this->limit_string( sanitize_text_field( (string) $nom ), 120 );
			if ( '' === trim( $nom ) ) {
				continue;   // une ligne laissée vide ne dit rien
			}
			$ark = isset( $arks[ $rang ] )
				? $this->limit_string( sanitize_text_field( (string) $arks[ $rang ] ), 200 ) : '';
			$lieux[] = array( 'nom' => $nom, 'ark' => $ark );
		}
		return $lieux;
	}

	/**
	 * Les organismes qui portent l'opération, dans l'ordre saisi.
	 *
	 * Le champ était unique, et disait « porteur de la fouille » : la
	 * formulation ne valait qu'en archéologie préventive, où un opérateur
	 * unique répond de l'opération. En recherche programmée, la gestion
	 * administrative se partage, et il n'y avait pas de place pour le dire.
	 */
	private function collect_organismes() {
		$brut = isset( $_POST['organisme'] ) ? wp_unslash( $_POST['organisme'] ) : '';
		// Les envois d'avant la répétition portaient une chaîne.
		$brut = is_array( $brut ) ? array_values( $brut ) : array( (string) $brut );

		$organismes = array();
		foreach ( $brut as $nom ) {
			if ( count( $organismes ) >= self::MAX_ORGANISMES ) {
				break;
			}
			$nom = $this->limit_string( sanitize_text_field( (string) $nom ), 200 );
			if ( '' === trim( $nom ) ) {
				continue;   // une ligne laissée vide ne dit rien
			}
			$organismes[] = $nom;
		}
		return $organismes;
	}

	/**
	 * Les organismes d'une notice, quelle que soit l'époque de son dépôt.
	 *
	 * Les notices reçues avant que le champ ne se répète portent une chaîne
	 * unique ; le fascicule les refabrique pourtant.
	 */
	private function organismes_de( $d ) {
		if ( ! empty( $d['organismes'] ) && is_array( $d['organismes'] ) ) {
			return $d['organismes'];
		}
		$nom = isset( $d['organisme'] ) ? trim( (string) $d['organisme'] ) : '';
		return '' === $nom ? array() : array( $nom );
	}

	/**
	 * La casse des libellés Pactols, telle que la revue les imprime.
	 *
	 * Pactols écrit ses libellés en bas de casse — « opération de diagnostic ».
	 * Le formulaire les affiche capitalisés parce qu'ils y sont des intitulés
	 * de cases à cocher. Dans le texte imprimé, la revue suit Pactols pour les
	 * natures et capitalise les périodes, qui sont des noms d'époques.
	 *
	 * « ucfirst » et « lcfirst » coupent l'octet et non le caractère : sur un
	 * « É » ils rendent du charabia. D'où le détour par mb_*, avec repli.
	 */
	private function bas_de_casse( $libelle ) {
		$libelle = (string) $libelle;
		if ( '' === $libelle || ! function_exists( 'mb_substr' ) ) {
			return lcfirst( $libelle );
		}
		return mb_strtolower( mb_substr( $libelle, 0, 1 ) ) . mb_substr( $libelle, 1 );
	}

	private function capitale_initiale( $libelle ) {
		$libelle = (string) $libelle;
		if ( '' === $libelle || ! function_exists( 'mb_substr' ) ) {
			return ucfirst( $libelle );
		}
		return mb_strtoupper( mb_substr( $libelle, 0, 1 ) ) . mb_substr( $libelle, 1 );
	}

	/**
	 * La graphie sous laquelle un terme paraît dans le volume.
	 *
	 * La forme préférée s'imprime telle que le thésaurus la donne : c'est lui
	 * qui sait où vont les majuscules — « Moyen Âge », « église ». La recasser
	 * reviendrait à défaire ce qu'on est allé chercher. Faute de résolution,
	 * la règle de la revue s'applique à la saisie, comme avant.
	 *
	 * Une seule fonction pour le texte et pour le bloc d'index : ce dernier
	 * porte la graphie du texte en « term type="orig" », et deux calculs
	 * séparés finiraient par diverger sans que rien ne le signale.
	 */
	private function graphie_imprimee( $item, $capitale = false ) {
		if ( ! empty( $item['prefLabel'] ) ) {
			return trim( (string) $item['prefLabel'] );
		}
		$label = isset( $item['label'] ) ? trim( (string) $item['label'] ) : '';
		return $capitale ? $this->capitale_initiale( $label ) : $this->bas_de_casse( $label );
	}

	/**
	 * Les blocs d'index d'une notice, prêts à coller dans le XML de Métopes.
	 *
	 * La chaîne attend, sous chaque paragraphe indexé, l'arbre des concepts du
	 * plus général au terme choisi, avec leurs étiquettes dans toutes les
	 * langues. Le composer à la main demande d'ouvrir le moteur d'indexation,
	 * de chercher le terme, de recopier son code — pour chaque mot-clé de
	 * chaque notice, trois cents fois par an.
	 *
	 * Or l'auteur a déjà choisi le concept, et nous en tenons l'identifiant
	 * depuis le dépôt. Il n'y a donc rien à chercher : le bloc se calcule.
	 *
	 * Rend une chaîne vide s'il n'y a rien à indexer.
	 */
	private function indexation_de( $id, $d, &$manquants = 0, &$prochaine = 0 ) {
		$termes = $this->termes_connus( $id );
		// Ce qui manque ne se demande pas ici. La page qui fabrique le dossier
		// a trente secondes, et une rubrique de quarante notices résolues à
		// froid en demandait des minutes : le zip ne sortait pas. On fait le
		// dossier avec ce qu'on sait, on dit ce qui manque, et le planificateur
		// s'en charge — relancé à neuf, puisqu'une personne vient de le
		// demander et que ses essais d'hier ne disent rien de Pactols
		// aujourd'hui.
		$manquants = count( $this->termes_manquants( $d, $termes ) );
		$prochaine = ( $manquants > 0 ) ? $this->relancer_la_resolution( $id, $d ) : 0;
		if ( empty( $termes ) ) {
			return '';
		}
		// Le style du paragraphe qui portera le bloc, et le nom d'index que la
		// chaîne attend dedans. « pactols:Lieux » suit la forme des deux
		// autres ; il se corrige d'un remplacement si la chaîne en nomme un
		// autre.
		$zones = array(
			array( 'nature_items',           'archeoCHR_fieldwork_method',             'pactols:Sujets',      false ),
			array( 'pactols_periods_items',  'archeoCHR_keywords_subjects:chronology', 'pactols:Chronologie', true ),
			array( 'pactols_subjects_items', 'archeoCHR_keywords_subjects',            'pactols:Sujets',      false ),
			array( 'pactols_places_items',   'archeoCHR_keywords_subjects',            'pactols:Lieux',       true ),
		);
		$corps = '';
		foreach ( $zones as $zone ) {
			list( $clef, $rend, $index_name, $capitale ) = $zone;
			$blocs = '';
			foreach ( (array) ( isset( $d[ $clef ] ) ? $d[ $clef ] : array() ) as $item ) {
				$ark = isset( $item['ark'] ) ? trim( (string) $item['ark'] ) : '';
				if ( '' === $ark || empty( $termes[ $ark ] ) ) {
					continue;
				}
				$bloc = Notice_Archeomed_Thesaurus::index_tei(
					$this->graphie_imprimee( $item, $capitale ),
					$termes[ $ark ], $index_name );
				if ( '' !== $bloc ) {
					$blocs .= "\n      " . $bloc;
				}
			}
			if ( '' !== $blocs ) {
				$corps .= "\n   <zone rend=\"" . esc_attr( $rend ) . "\">"
					. $blocs . "\n   </zone>";
			}
		}
		if ( '' === $corps ) {
			return '';
		}
		// Une date, ou deux en intervalle ISO 8601 quand les termes n'ont pas
		// été lus le même jour.
		list( $du, $au ) = $this->millesime( $d, $termes );
		$lu_le = ( '' === $du ) ? '' : ( $du === $au ? $du : $du . '/' . $au );
		$titre   = trim( $d['commune'] . ' ' . $d['lieu_dit'] );
		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<!-- Blocs d\'index Pactols calculés depuis les identifiants choisis au dépôt.' . "\n"
			. '     Chaque « zone » nomme le style du paragraphe où son contenu se colle. -->' . "\n"
			. '<indexation xmlns="http://www.tei-c.org/ns/1.0" notice="' . esc_attr( $titre ) . '"'
			. ( '' !== $lu_le ? ' lu-le="' . esc_attr( $lu_le ) . '"' : '' ) . '>'
			. $corps . "\n" . '</indexation>' . "\n";
	}

	/**
	 * Une liste de termes Pactols, chacun portant son ARK en lien.
	 *
	 * L'identifiant paraissait en clair, entre crochets, dans un bloc
	 * d'indexation au bas de la notice : illisible pour qui lit la revue, et
	 * une masse à supprimer pour qui prépare le XML. Porté par le terme
	 * lui-même, il ne se voit pas, il ne gêne personne, et il suit le mot
	 * qu'il désigne.
	 *
	 * C'est aussi un pari sur la chaîne : un lien vers un ARK est une
	 * indexation en puissance, que Métopes saura peut-être transformer en
	 * balise sans qu'on ait à tenir deux listes.
	 *
	 * Rend du XML brut, à passer à « add_raw_paragraph ».
	 */
	private function termes_pactols_lies( $doc, $items, $capitale = false ) {
		$morceaux = array();
		foreach ( (array) $items as $item ) {
			$label = isset( $item['label'] ) ? trim( (string) $item['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$label = $this->graphie_imprimee( $item, $capitale );
			// Sans ARK — une notice d'avant les identifiants, ou un terme
			// saisi sans sélection — le mot reste du texte ordinaire.
			$morceaux[] = ! empty( $item['ark'] )
				? $doc->hyperlink( $item['ark'], $label )
				: $doc->plain( $label );
		}
		return $morceaux;
	}

	/** Les libellés d'une liste d'items Pactols, dans la casse voulue. */
	private function libelles_pactols( $items, $capitale = false ) {
		$out = array();
		foreach ( (array) $items as $item ) {
			$label = isset( $item['label'] ) ? trim( (string) $item['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$out[] = $capitale ? $this->capitale_initiale( $label )
				: $this->bas_de_casse( $label );
		}
		return $out;
	}

	/** « Organisme porteur de l'opération », au pluriel s'ils sont plusieurs. */
	private function libelle_organisme( $combien ) {
		return $combien > 1
			? "Organismes porteurs de l'opération"
			: "Organisme porteur de l'opération";
	}

	/**
	 * Ce que l'auteur a écrit sur chacune de ses illustrations.
	 *
	 * Un champ libre commun recevait tout — légendes, auteurs, droits — et la
	 * rédaction devait deviner quelle ligne allait avec quelle image. Les
	 * trois renseignements sont maintenant demandés fichier par fichier, dans
	 * l'ordre où les fichiers ont été déposés : le rang du tableau est celui
	 * de la figure.
	 */
	private function collect_illustrations() {
		$parts = array();
		foreach ( array( 'titre', 'legende', 'credits' ) as $clef ) {
			$brut = isset( $_POST[ 'illus_' . $clef ] ) ? wp_unslash( $_POST[ 'illus_' . $clef ] ) : array();
			$parts[ $clef ] = is_array( $brut ) ? array_values( $brut ) : array();
		}
		$combien = 0;
		foreach ( $parts as $liste ) {
			$combien = max( $combien, count( $liste ) );
		}
		$combien = min( $combien, self::MAX_FILES );

		$items = array();
		for ( $i = 0; $i < $combien; $i++ ) {
			$item = array( 'rang' => $i + 1 );
			foreach ( $parts as $clef => $liste ) {
				$valeur = isset( $liste[ $i ] ) ? sanitize_textarea_field( (string) $liste[ $i ] ) : '';
				$item[ $clef ] = $this->limit_string( $valeur, 'legende' === $clef ? 1500 : 400 );
			}
			// Une ligne entièrement vide ne dit rien : on ne la garde pas, et
			// la figure gardera son rang par le nom du fichier joint.
			if ( '' === trim( $item['titre'] . $item['legende'] . $item['credits'] ) ) {
				continue;
			}
			$items[] = $item;
		}
		return $items;
	}

	/**
	 * Rend le nom du champ qui manque, ou une chaîne vide si tout est là.
	 *
	 * Rendait « faux » sans dire quoi : l'auteur voyait un refus et
	 * recommençait à l'identique, faute de savoir ce qu'on lui demandait.
	 */
	private function validate_required_fields( $d ) {
		$required = array( 'rubrique_principale', 'commune', 'departement', 'lieu_dit', 'annee', 'organisme', 'resp_prenom', 'resp_nom', 'resp_inst' );
		foreach ( $required as $field ) {
			if ( empty( $d[ $field ] ) || '' === trim( (string) $d[ $field ] ) ) {
				return $field;
			}
		}
		if ( empty( $d['resp_email'] ) || ! is_email( $d['resp_email'] ) ) {
			return 'resp_email';
		}
		if ( empty( $d['nature'] ) ) {
			return 'nature';
		}
		if ( empty( $d['texte_notice'] ) || '' === trim( wp_strip_all_tags( $d['texte_notice'] ) ) ) {
			return 'texte';
		}
		return '';
	}
	/**
	 * Met en gras l'intitulé d'une ligne de métadonnée (la partie avant " : ").
	 */
	private function meta_line( $label, $value ) {
		return '<strong>' . esc_html( $label ) . ' :</strong> ' . esc_html( $value );
	}

	/**
	 * Rend un ARK en lien cliquable, avec seulement l'identifiant {naan}/{id} comme texte.
	 * Ex. https://ark.frantiq.fr/ark:/26678/pcrtWz1aLZAwdE -> <a href="...">26678/pcrtWz1aLZAwdE</a>
	 */
	private function ark_link( $ark_uri ) {
		$ark_uri = trim( $ark_uri );
		if ( '' === $ark_uri ) {
			return '';
		}
		$id = $ark_uri;
		if ( preg_match( '#ark:/(.+)$#', $ark_uri, $m ) ) {
			$id = $m[1];
		}
		return '[<a href="' . esc_url( $ark_uri ) . '">' . esc_html( $id ) . '</a>]';
	}

	/**
	 * Rend un bloc de mots-clés Pactols : un titre en gras, puis une occurrence par ligne (label + ARK).
	 */
	private function pactols_keywords_block( $title, $items ) {
		if ( empty( $items ) ) {
			return '';
		}
		$lines = array( '<strong>' . esc_html( $title ) . ' :</strong>' );
		foreach ( $items as $item ) {
			$line = esc_html( $item['label'] );
			if ( ! empty( $item['ark'] ) ) {
				$line .= ' ' . $this->ark_link( $item['ark'] );
			} elseif ( ! empty( $item['idConcept'] ) ) {
				$line .= ' [ID concept : ' . esc_html( $item['idConcept'] ) . ']';
			}
			$lines[] = $line;
		}
		return implode( '<br>', $lines );
	}

	/**
	 * Les illustrations telles qu'on les lit dans le courriel : une par ligne,
	 * avec son titre, sa légende et ses crédits.
	 */
	private function illustrations_block( $d ) {
		$items = isset( $d['illustrations'] ) ? (array) $d['illustrations'] : array();
		if ( empty( $items ) ) {
			return '';
		}
		$lignes = array( '<strong>Illustrations :</strong>' );
		foreach ( $items as $item ) {
			$parts = array( '<strong>Fig. ' . (int) $item['rang'] . '</strong>' );
			if ( '' !== $item['titre'] ) {
				$parts[] = esc_html( $item['titre'] );
			}
			$lignes[] = implode( ' — ', $parts );
			if ( '' !== $item['legende'] ) {
				$lignes[] = esc_html( $item['legende'] );
			}
			if ( '' !== $item['credits'] ) {
				$lignes[] = '<em>' . esc_html( $item['credits'] ) . '</em>';
			}
		}
		return implode( '<br>', $lignes );
	}

	private function build_notice( $d ) {
		$departement = $this->sans_parentheses( $d['departement'] );
		$commune_dept = $this->lieux_en_ligne( $d ) . ' (' . $departement . ')';

		// Police sans-serif type Arial pour tout le mail.
		$font = 'font-family:Arial,Helvetica,sans-serif;';
		$style_block = $font . 'font-size:15px;line-height:1.5;margin:0 0 14px;';
		$style_txt   = $font . 'font-size:15px;line-height:1.6;margin:0 0 14px;';

		// --- Bloc d'en-tête (rubriques), groupé ---
		$entete_lines = array();
		$entete_lines[] = $this->meta_line( 'Rubrique principale', $d['rubrique_principale'] );
		if ( '' !== $d['renvoi_1'] ) {
			$entete_lines[] = $this->meta_line( 'Rubrique secondaire', $d['renvoi_1'] );
		}
		if ( '' !== $d['renvoi_2'] ) {
			$entete_lines[] = $this->meta_line( 'Rubrique secondaire', $d['renvoi_2'] );
		}

		// --- Ligne des lieux (chacun avec son ARK cliquable), bloc séparé ---
		$lieux = $this->lieux_de( $d );
		$morceaux = array();
		foreach ( $lieux as $lieu ) {
			$part = esc_html( $lieu['nom'] );
			if ( '' !== $lieu['ark'] ) {
				$part .= ' ' . $this->ark_link( $lieu['ark'] );
			}
			$morceaux[] = $part;
		}
		$commune_html = '';
		if ( ! empty( $morceaux ) ) {
			$commune_html = '<strong>' . ( 1 === count( $morceaux ) ? 'Lieu' : 'Lieux' )
				. ' :</strong> ' . implode( ', ', $morceaux );
			if ( '' !== trim( $departement ) ) {
				$commune_html .= ' (' . esc_html( $departement ) . ')';
			}
		}

		// --- Blocs de mots-clés Pactols, chacun séparé ---
		$periods_block  = $this->pactols_keywords_block( 'Mots-clés Pactols, période historique', $d['pactols_periods_items'] );
		$subjects_block = $this->pactols_keywords_block( 'Mots-clés Pactols, sujets', $d['pactols_subjects_items'] );
		$places_block   = $this->pactols_keywords_block( 'Mots-clés Pactols, lieux autres', $d['pactols_places_items'] );
		$nature_block   = $this->pactols_keywords_block( 'Nature de l\'opération', $d['nature_items'] );

		// --- Bloc année / autorisation / organisme, groupé ---
		$admin_lines = array();
		$admin_lines[] = $this->meta_line( 'Année de l\'opération', $d['annee'] );
		if ( '' !== $d['num_autorisation'] ) {
			$admin_lines[] = $this->meta_line( 'Numéro d\'autorisation', $d['num_autorisation'] );
		}
		if ( ! empty( $d['id_patriarche'] ) ) {
			$admin_lines[] = $this->meta_line( 'Identifiant Patriarche', $d['id_patriarche'] );
		}
		if ( ! empty( $d['rapport_lien'] ) ) {
			$admin_lines[] = $this->meta_line( 'Rapport final', $d['rapport_lien'] );
		}
		$organismes = $this->organismes_de( $d );
		if ( ! empty( $organismes ) ) {
			$admin_lines[] = $this->meta_line(
				$this->libelle_organisme( count( $organismes ) ),
				implode( ', ', $organismes ) );
		}

		// --- Bloc coordonnées des responsables (emails saisis) ---
		$contacts = array();
		$rn = trim( $d['resp_prenom'] . ' ' . $d['resp_nom'] );
		if ( '' !== $d['resp_email'] && '' !== $rn ) {
			$contacts[] = esc_html( $rn ) . ' : <a href="mailto:' . esc_attr( $d['resp_email'] ) . '">' . esc_html( $d['resp_email'] ) . '</a>';
		}
		$crn = trim( $d['coresp_prenom'] . ' ' . $d['coresp_nom'] );
		if ( '' !== $d['coresp_email'] && '' !== $crn ) {
			$contacts[] = esc_html( $crn ) . ' : <a href="mailto:' . esc_attr( $d['coresp_email'] ) . '">' . esc_html( $d['coresp_email'] ) . '</a>';
		}
		$can = trim( $d['coauteur_prenom'] . ' ' . $d['coauteur_nom'] );
		if ( '' !== $d['coauteur_email'] && '' !== $can ) {
			$contacts[] = esc_html( $can ) . ' : <a href="mailto:' . esc_attr( $d['coauteur_email'] ) . '">' . esc_html( $d['coauteur_email'] ) . '</a>';
		}
		$contacts_block = '';
		if ( ! empty( $contacts ) ) {
			$contacts_block = '<strong>Coordonnées des responsables :</strong><br>' . implode( '<br>', $contacts );
		}

		// --- Bloc responsable (collé au point final du texte, entre parenthèses) ---
		$resp_nom_complet = trim( $d['resp_prenom'] . ' ' . $d['resp_nom'] );
		$resp = 'Responsable de l\'opération : ' . $resp_nom_complet . ', ' . $d['resp_inst'];
		$segments = array( $resp );
		$coresp_nom_complet = trim( $d['coresp_prenom'] . ' ' . $d['coresp_nom'] );
		if ( '' !== $coresp_nom_complet ) {
			$co = 'co-responsable de l\'opération : ' . $coresp_nom_complet;
			if ( '' !== $d['coresp_inst'] ) {
				$co .= ', ' . $d['coresp_inst'];
			}
			$segments[] = $co;
		}
		$coauteur_nom_complet = trim( $d['coauteur_prenom'] . ' ' . $d['coauteur_nom'] );
		if ( '' !== $coauteur_nom_complet ) {
			$ca = 'notice rédigée avec : ' . $coauteur_nom_complet;
			if ( '' !== $d['coauteur_inst'] ) {
				$ca .= ', ' . $d['coauteur_inst'];
			}
			$segments[] = $ca;
		}

		// --- Corps de la notice ---
		// Ligne 1 : Commune (département), lieu-dit (italique)
		// Ligne 2 : Nature de l'opération : <valeur> (titre en maigre)
		// Ligne 3+ : texte rédigé, terminé par " (responsable…)" collé au point final.
		$loc_line = esc_html( $commune_dept );
		if ( '' !== $d['lieu_dit'] ) {
			// Un point sépare, comme dans le document et dans le volume : la
			// copie de l'auteur et le fichier joint ne doivent pas se
			// contredire sur la ponctuation d'un titre.
			$loc_line .= '. <em>' . esc_html( $d['lieu_dit'] ) . '</em>';
		}
		$nature_line = 'Nature de l\'opération : ' . esc_html( $d['nature'] );
		$bloc_resp = ' (' . esc_html( implode( ' ; ', $segments ) ) . ')';
		$texte = trim( $d['texte_notice'] );
		// Le bloc responsable se colle juste après le point final, dans le fil du texte.
		if ( '' === $texte ) {
			$texte_html = '<p style="margin:0;">' . $bloc_resp . '</p>';
		} elseif ( preg_match( '#</p>\s*$#', $texte ) ) {
			$texte_html = preg_replace( '#</p>\s*$#', $bloc_resp . '</p>', $texte, 1 );
		} else {
			$texte_html = $texte . $bloc_resp;
		}

		// --- Assemblage final ---
		$html = '';
		// Une notice corrigée arrive comme une notice neuve : rien ne la
		// distinguerait de la première, et la rédaction garderait les deux.
		// Le bandeau se lit avant tout le reste, et nomme le dépôt à effacer.
		if ( ! empty( $d['remplace'] ) ) {
			$html .= '<div style="' . $font . 'font-size:15px;line-height:1.5;'
				. 'background:#f8e6e6;border:2px solid #b00;padding:14px 18px;'
				. 'margin:0 0 18px;">'
				. '<strong style="font-size:19px;color:#b00;">CORRECTION</strong><br>'
				. 'Cette notice remplace le dépôt <strong>'
				. esc_html( $d['remplace'] ) . '</strong>. '
				. '<strong>Le précédent est à supprimer</strong> : c\'est celle-ci qui fait foi.'
				. '</div>';
		}
		$html .= '<div style="' . $style_block . '">' . implode( '<br>', $entete_lines ) . '</div>';
		$html .= '<div style="' . $style_block . '">' . $commune_html . '</div>';
		if ( '' !== $periods_block ) {
			$html .= '<div style="' . $style_block . '">' . $periods_block . '</div>';
		}
		if ( '' !== $subjects_block ) {
			$html .= '<div style="' . $style_block . '">' . $subjects_block . '</div>';
		}
		if ( '' !== $places_block ) {
			$html .= '<div style="' . $style_block . '">' . $places_block . '</div>';
		}
		if ( '' !== $nature_block ) {
			$html .= '<div style="' . $style_block . '">' . $nature_block . '</div>';
		}
		$html .= '<div style="' . $style_block . '">' . implode( '<br>', $admin_lines ) . '</div>';
		if ( '' !== $contacts_block ) {
			$html .= '<div style="' . $style_block . '">' . $contacts_block . '</div>';
		}
		// Corps de notice : localisation + nature, puis texte (responsable collé au point final).
		$style_corps = $font . 'font-size:15px;line-height:1.6;';
		$html .= '<div style="' . $style_corps . 'margin:0;">' . $loc_line . '<br>' . $nature_line . '</div>';
		// On neutralise la marge haute du premier paragraphe du texte pour resserrer l'espace.
		$texte_html = preg_replace( '#^(\s*<p)(\s|>)#i', '$1 style="margin-top:0;"$2', $texte_html, 1 );
		$html .= '<div style="' . $style_corps . '">' . $texte_html . '</div>';
		$suffixe = array();
		$bloc_illustrations = $this->illustrations_block( $d );
		if ( '' !== $bloc_illustrations ) {
			$suffixe[] = $bloc_illustrations;
		}
		if ( '' !== $d['commentaires'] ) {
			$suffixe[] = $this->meta_line( 'Commentaires', $d['commentaires'] );
		}
		if ( ! empty( $suffixe ) ) {
			$html .= '<div style="' . $style_block . 'margin-top:16px;">' . implode( '<br>', $suffixe ) . '</div>';
		}
		return $html;
	}
	/**
	 * Les deux barres qui encadrent une figure.
	 *
	 * Le texte est celui qu'emploie la chaîne Métopes, au caractère près : un
	 * préparateur qui passe d'un outil à l'autre doit retrouver la même chose
	 * sous les yeux.
	 */
	private static function figure_ouvrante() {
		return '— <figure> ' . str_repeat( '—', 50 );
	}

	private static function figure_fermante() {
		return '— </figure> ' . str_repeat( '—', 50 );
	}

	/**
	 * Reçoit une nouvelle feuille de style Métopes.
	 *
	 * On la contrôle avant de la poser : un DOCX est une archive, et une
	 * archive qui ne porte pas de feuille de styles n'est pas un gabarit —
	 * l'accepter priverait la revue de tous ses styles au dépôt suivant, et
	 * l'on ne s'en apercevrait qu'à la relecture.
	 */
	public function deposer_la_feuille() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		check_admin_referer( 'na_feuille' );
		$retour = Notice_Archeomed_Settings::url();

		if ( ! empty( $_POST['na_feuille_retirer'] ) ) {
			foreach ( array( self::DOCX_TEMPLATE, self::RTF_TEMPLATE ) as $nom ) {
				$depot = wp_upload_dir();
				if ( empty( $depot['error'] ) ) {
					@unlink( trailingslashit( $depot['basedir'] ) . 'notice-archeomed/' . $nom );
				}
			}
			wp_safe_redirect( add_query_arg( 'na_feuille', 'retiree', $retour ) );
			exit;
		}

		if ( empty( $_FILES['na_feuille'] ) || UPLOAD_ERR_OK !== $_FILES['na_feuille']['error'] ) {
			wp_safe_redirect( add_query_arg( 'na_feuille', 'vide', $retour ) );
			exit;
		}
		$source = $_FILES['na_feuille']['tmp_name'];
		$nom    = sanitize_file_name( $_FILES['na_feuille']['name'] );
		$ext    = strtolower( (string) pathinfo( $nom, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'docx', 'rtf' ), true ) || ! is_uploaded_file( $source ) ) {
			wp_safe_redirect( add_query_arg( 'na_feuille', 'format', $retour ) );
			exit;
		}
		if ( 'docx' === $ext && ! $this->feuille_docx_valide( $source ) ) {
			wp_safe_redirect( add_query_arg( 'na_feuille', 'invalide', $retour ) );
			exit;
		}
		$depot = wp_upload_dir();
		if ( ! empty( $depot['error'] ) ) {
			wp_safe_redirect( add_query_arg( 'na_feuille', 'ecriture', $retour ) );
			exit;
		}
		$dossier = trailingslashit( $depot['basedir'] ) . 'notice-archeomed';
		wp_mkdir_p( $dossier );
		$cible = trailingslashit( $dossier )
			. ( 'docx' === $ext ? self::DOCX_TEMPLATE : self::RTF_TEMPLATE );
		if ( ! @move_uploaded_file( $source, $cible ) ) {
			wp_safe_redirect( add_query_arg( 'na_feuille', 'ecriture', $retour ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'na_feuille', 'posee', $retour ) );
		exit;
	}

	/** Un DOCX qui porte bien une feuille de styles, et non n'importe quel zip. */
	private function feuille_docx_valide( $chemin ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return true;   // sans ZipArchive on ne sait pas juger : on laisse passer
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $chemin ) ) {
			return false;
		}
		$styles = $zip->getFromName( 'word/styles.xml' );
		$zip->close();
		return is_string( $styles ) && false !== strpos( $styles, '<w:style' );
	}

	/**
	 * Le chemin de la feuille de style à employer, déposée ou livrée.
	 *
	 * Une feuille déposée depuis l'administration ne peut pas vivre dans le
	 * dossier du plugin : **la mise à jour suivante l'effacerait**, puisqu'on
	 * remplace ce dossier en entier. Elle va donc dans les téléversements,
	 * à côté des illustrations conservées, où rien ne la menace.
	 *
	 * Celle du plugin reste le repli : un dépôt raté ou un fichier effacé ne
	 * doit pas priver la revue de ses styles.
	 */
	public static function feuille_de_style( $format ) {
		$nom = ( 'docx' === $format ) ? self::DOCX_TEMPLATE : self::RTF_TEMPLATE;
		$depot = wp_upload_dir();
		if ( empty( $depot['error'] ) ) {
			$depose = trailingslashit( $depot['basedir'] ) . 'notice-archeomed/' . $nom;
			if ( file_exists( $depose ) && filesize( $depose ) > 0 ) {
				return $depose;
			}
		}
		return plugin_dir_path( __FILE__ ) . $nom;
	}

	/**
	 * Ce qu'on sait de la feuille en service : d'où elle vient, et de quand.
	 */
	public static function etat_de_la_feuille( $format = 'docx' ) {
		$chemin = self::feuille_de_style( $format );
		$depose = false !== strpos( $chemin, 'uploads' )
			|| false === strpos( $chemin, plugin_dir_path( __FILE__ ) );
		$etat = array(
			'chemin'  => $chemin,
			'deposee' => $depose,
			'presente' => file_exists( $chemin ),
			'poids'   => file_exists( $chemin ) ? filesize( $chemin ) : 0,
			'posee'   => file_exists( $chemin ) ? filemtime( $chemin ) : 0,
			'modifiee' => '',
			'styles'  => 0,
		);
		if ( ! $etat['presente'] || 'docx' !== $format || ! class_exists( 'ZipArchive' ) ) {
			return $etat;
		}
		// La date que Word inscrit dans le document : c'est elle qui date la
		// feuille elle-même, non le moment où on l'a posée sur le serveur.
		$zip = new ZipArchive();
		if ( true !== $zip->open( $chemin ) ) {
			return $etat;
		}
		$props = $zip->getFromName( 'docProps/core.xml' );
		if ( is_string( $props ) && preg_match( '#<dcterms:modified[^>]*>([^<]+)<#', $props, $m ) ) {
			$etat['modifiee'] = $m[1];
		}
		$styles = $zip->getFromName( 'word/styles.xml' );
		if ( is_string( $styles ) ) {
			$etat['styles'] = preg_match_all( '#<w:style\b#', $styles );
		}
		$zip->close();
		return $etat;
	}

	/**
	 * Ouvre un document au gabarit Métopes, DOCX ou RTF selon l'hébergement.
	 */
	private function ouvrir_un_document( $format, &$erreur = '' ) {
		$erreur = '';
		$modele = self::feuille_de_style( $format );
		$doc    = ( 'docx' === $format )
			? new Notice_Archeomed_DOCX( $modele )
			: new Notice_Archeomed_RTF( $modele );
		if ( ! $doc->is_ready() ) {
			$erreur = $doc->get_error();
			return null;
		}
		return $doc;
	}

	/**
	 * Construit le fichier RTF stylé Métopes correspondant à la notice.
	 * Renvoie le chemin du fichier créé, ou une chaîne vide en cas d'échec
	 * (l'envoi du courriel se poursuit alors sans la pièce jointe).
	 */
	private function build_rtf_file( $d, &$rtf_error = '', $format = 'rtf' ) {
		$doc = $this->ouvrir_un_document( $format, $rtf_error );
		if ( null === $doc ) {
			return '';
		}
		$this->remplir_le_document( $doc, $d );

		// Écriture dans le répertoire temporaire, avec un nom parlant pour le classement.
		$tmp_dir  = trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp';
		$slug     = sanitize_file_name( remove_accents( $d['commune'] . '-' . $d['annee'] ) );
		$slug     = trim( preg_replace( '/-+/', '-', $slug ), '-' );
		$basename = 'notice-archeomed-' . ( '' !== $slug ? $slug . '-' : '' )
			. wp_generate_password( 8, false, false )
			. ( 'docx' === $format ? '.docx' : '.rtf' );

		$path = $doc->write_to( $tmp_dir, $basename );
		if ( '' === $path ) {
			$rtf_error = $doc->get_error();
		}
		return $path;
	}

	/**
	 * Verse une notice dans un document déjà ouvert.
	 *
	 * Séparé de l'écriture du fichier : c'est ce qui permet d'en assembler
	 * plusieurs dans un seul document — le fascicule d'une rubrique, qu'on
	 * relit d'un bloc plutôt que notice par notice dans quarante courriels.
	 */
	private function remplir_le_document( $doc, $d, $rubrique_en_tete = true ) {
		$departement  = $this->sans_parentheses( $d['departement'] );

		// 0. L'avis de correction, quand la notice en remplace une autre.
		//
		// Encadré comme les figures le sont — c'est l'idiome de la maison pour
		// ce qui n'est pas du texte —, et non surligné : le surlignement vient
		// du gabarit Métopes et veut dire autre chose. Il ne paraît que sur le
		// document d'une notice seule ; dans un fascicule, la question ne se
		// pose plus, la rédaction a fait le ménage depuis longtemps.
		if ( $rubrique_en_tete && ! empty( $d['remplace'] ) ) {
			$doc->add_paragraph( 'Normal', array(
				array( 'text' => '— CORRECTION ' . str_repeat( '—', 50 ) ) ) );
			$doc->add_paragraph( 'Normal', array( array(
				'text' => 'Cette notice remplace le dépôt ' . $d['remplace']
					. ' : le précédent est à supprimer.',
				'b'    => true ) ) );
			$doc->add_paragraph( 'Normal', array(
				array( 'text' => str_repeat( '—', 62 ) ) ) );
		}
		$commune_dept = $this->lieux_en_ligne( $d ) . ' (' . $departement . ')';

		// 1. Rubrique principale, en tête de notice : style Titre.
		//
		// Dans un fascicule, elle est posée une fois pour toutes en tête du
		// document : la répéter avant chaque notice donnait un sommaire à
		// chaque page, et faussait la hiérarchie que la chaîne XML en tire.
		if ( $rubrique_en_tete ) {
			$doc->add_paragraph( 'Title', array( array(
				'text' => $this->titre_de_rubrique( $d['rubrique_principale'] ) ) ) );
			// 2. La sous-rubrique, en titre de niveau 1 : « IV.1 – Opérations
			//    de terrain ». Dans un fascicule, elle se pose une fois par
			//    groupe et non par notice, comme la rubrique elle-même.
			$doc->add_paragraph( 'TEI_Titre 1+rubrique', array( array(
				'text' => $this->titre_de_famille( $d ) ) ) );
		}

		// 3. Les renvois ne s'impriment plus ici.
		//
		// Ils paraissaient en tête de la notice qui les porte, dans sa propre
		// rubrique — là où ils ne servent à personne : c'est le préparateur de
		// l'autre rubrique qui doit savoir qu'une notice le concerne. Le
		// fascicule de la rubrique visée les range désormais à leur place
		// alphabétique, parmi ses propres notices.

		// 4. Titre de la notice : « Commune (département). Lieu-dit » —
		// un point sépare, et non une virgule, comme la revue le compose.
		//
		// Chaque lieu porte son ARK en lien, comme les autres termes indexés.
		// C'est la seule place où la commune paraît dans la notice imprimée :
		// la lier ici évite de la répéter plus bas pour son seul identifiant.
		$lieux_titre = $this->lieux_de( $d );
		$titre = '';
		if ( ! empty( $lieux_titre ) ) {
			$morceaux = array();
			foreach ( $lieux_titre as $lieu ) {
				$morceaux[] = ( '' !== $lieu['ark'] )
					? $doc->hyperlink( $lieu['ark'], $lieu['nom'] )
					: $doc->plain( $lieu['nom'] );
			}
			$titre = implode( $doc->plain( ', ' ), $morceaux );
			if ( '' !== trim( $departement ) ) {
				$titre .= $doc->plain( ' (' . $departement . ')' );
			}
		} else {
			$titre = $doc->plain( $commune_dept );
		}
		if ( '' !== $d['lieu_dit'] ) {
			$titre .= $doc->plain( '. ' )
				. $this->run_xml( $doc, array( 'text' => $d['lieu_dit'], 'i' => true ) );
		}
		$doc->add_raw_paragraph( 'TEI_Titre 2+notice', $titre );

		// 4 bis à 5. Les métadonnées de l'opération, telles qu'elles
		// s'impriment désormais dans la notice.
		//
		// Elles ne paraissaient que plus bas, dans le bloc d'indexation, avec
		// leurs identifiants entre crochets — bon pour la chaîne XML,
		// illisible dans un volume. La décision de l'année est de les donner
		// au lecteur : sans ARK, dans l'ordre de la mise en page, et dans la
		// casse de la revue — Pactols pour les natures, capitale initiale
		// pour les périodes. Le bloc détaillé reste en dessous, à supprimer
		// d'un bloc quand on prépare le XML.
		$natures = $this->termes_pactols_lies( $doc,
			isset( $d['nature_items'] ) ? $d['nature_items'] : array() );
		if ( empty( $natures ) && '' !== $d['nature'] ) {
			// Une notice d'avant les identifiants : le libellé seul.
			foreach ( array_filter( array_map( 'trim', explode( ',', $d['nature'] ) ) ) as $nu ) {
				$natures[] = $doc->plain( $this->bas_de_casse( $nu ) );
			}
		}
		if ( ! empty( $natures ) ) {
			$doc->add_raw_paragraph( 'TEI_archeoCHR_fieldwork_method',
				$doc->plain( "Nature de l'opération : " )
					. implode( $doc->plain( ', ' ), $natures ) );
		}

		// Les lieux autres que celui de la notice, sous le titre : ils
		// n'avaient de place que dans le bloc d'indexation, avec leur ARK en
		// clair. Portés ici, ils se lisent et gardent leur identifiant en
		// lien, comme les autres termes indexés.
		$autres_lieux = $this->termes_pactols_lies( $doc,
			isset( $d['pactols_places_items'] ) ? $d['pactols_places_items'] : array(),
			true );
		if ( ! empty( $autres_lieux ) ) {
			$doc->add_raw_paragraph( 'TEI_archeoCHR_keywords_subjects',
				$doc->plain( 1 === count( $autres_lieux ) ? 'Autre lieu : ' : 'Autres lieux : ' )
					. implode( $doc->plain( ', ' ), $autres_lieux ) );
		}

		$periodes = $this->termes_pactols_lies( $doc,
			isset( $d['pactols_periods_items'] ) ? $d['pactols_periods_items'] : array(),
			true );
		if ( ! empty( $periodes ) ) {
			$doc->add_raw_paragraph( 'TEI_archeoCHR_keywords_subjects:chronology',
				$doc->plain( 'Période historique : ' )
					. implode( $doc->plain( ', ' ), $periodes ) );
		}

		$doc->add_paragraph(
			'TEI_archeoCHR_fieldwork_year',
			array( array( 'text' => "Année de l'opération : " . $d['annee'] ) )
		);
		if ( '' !== $d['num_autorisation'] ) {
			$doc->add_paragraph(
				'TEI_archeoCHR_IDpatriarche',
				array( array( 'text' => "Numéro d'autorisation : " . $d['num_autorisation'] ) )
			);
		}
		// L'identifiant Patriarche partage pour l'instant le style du numéro
		// d'autorisation — c'est celui que Métopes nomme « Identifiant
		// Patriarche », et rien d'autre ne lui convient mieux. Les deux
		// renseignements sont distincts : l'un est un arrêté, l'autre une
		// entrée dans la base du ministère. Ils sont donc collectés à part,
		// quitte à ce que la chaîne les distingue plus tard.
		if ( ! empty( $d['id_patriarche'] ) ) {
			$doc->add_paragraph(
				'TEI_archeoCHR_IDpatriarche',
				array( array( 'text' => 'Identifiant Patriarche : ' . $d['id_patriarche'] ) )
			);
		}
		if ( ! empty( $d['rapport_lien'] ) ) {
			$doc->add_raw_paragraph(
				'TEI_archeoCHR_reportlink',
				$doc->plain( 'Rapport final : ' )
					. $doc->hyperlink( $d['rapport_lien'], $d['rapport_lien'] )
			);
		}
		$organismes_doc = $this->organismes_de( $d );
		if ( ! empty( $organismes_doc ) ) {
			$doc->add_paragraph(
				'TEI_archeoCHR_holder',
				array( array( 'text' => $this->libelle_organisme( count( $organismes_doc ) )
					. ' : ' . implode( ', ', $organismes_doc ) ) )
			);
		}
		// Les sujets Pactols, en clair et sans identifiant : ce sont eux que
		// la revue imprime sous le nom de « mots-clés ».
		$sujets = $this->termes_pactols_lies( $doc,
			isset( $d['pactols_subjects_items'] ) ? $d['pactols_subjects_items'] : array() );
		if ( ! empty( $sujets ) ) {
			$doc->add_raw_paragraph( 'TEI_archeoCHR_keywords_subjects',
				$doc->plain( 'Mots-clés : ' )
					. implode( $doc->plain( ', ' ), $sujets ) );
		}

		// 6. Texte de la notice, un paragraphe par <p>, avec le bloc responsable
		// collé au point final du dernier paragraphe.
		$paragraphes = $doc->html_to_paragraphs( $d['texte_notice'] );
		$bloc_resp   = $this->responsables_rtf( $doc, $d );
		if ( empty( $paragraphes ) ) {
			$paragraphes = array( $bloc_resp );
		} else {
			$last                 = count( $paragraphes ) - 1;
			$paragraphes[ $last ] = $paragraphes[ $last ] . $bloc_resp;
		}
		foreach ( $paragraphes as $p ) {
			$doc->add_raw_paragraph( 'Normal', $p );
		}

		// 7. Les illustrations, chacune dans son bloc de figure.
		//
		// La feuille Métopes distingue le titre, la légende et les crédits :
		// « TEI_figure_title », « TEI_figure_caption », « TEI_figure_credits ».
		// Tout partait jusqu'ici dans le seul titre de figure, et la chaîne XML
		// ne pouvait donc pas les séparer — c'était à refaire à la main, notice
		// par notice.
		// L'image se posait dans un paragraphe « TEI_figure_title », c'est-à-dire
		// stylée comme le titre de la figure : le bloc portait deux titres,
		// dont l'un ne contenait qu'un dessin.
		//
		// Elle va en Normal, suivie du titre, de la légende et des crédits :
		// c'est l'usage de la revue. « TEI_figure_alternative » existe dans le
		// modèle et désigne une image de substitution, ce qu'est une basse
		// définition — mais la revue ne s'en sert pas, et le stylage de
		// référence ne la connaît pas. On suit l'usage, pas la déduction.
		$illustrations = isset( $d['illustrations'] ) ? (array) $d['illustrations'] : array();
		foreach ( $illustrations as $item ) {
			// Le bloc s'ouvre et se ferme, comme la chaîne Métopes l'attend
			// pour les figures d'un article : elle sait alors où commence et
			// où finit la figure, et le préparateur le voit à l'œil sans
			// ouvrir le volet des styles.
			$doc->add_paragraph( 'TEI_figure_start',
				array( array( 'text' => self::figure_ouvrante() ) ) );
			// « Fig. 1 Vue générale » et non « Fig. 1 : Vue générale » : c'est
			// ainsi que la revue compose ses légendes.
			$numero = 'Fig. ' . (int) $item['rang'];
			$titre  = $numero . ( '' !== $item['titre'] ? ' ' . $item['titre'] : '' );
			// L'image, appelée en lien depuis « icono/br », entre le repère
			// d'ouverture et le titre. Elle n'est pas dans le document : le
			// paquet la porte à côté, et la mise en page la remplace dans son
			// dossier sans rouvrir le texte. Hors paquet — le fascicule seul,
			// la notice d'un auteur — il n'y a pas de figure à appeler, et le
			// bloc reste ce qu'il était.
			// « fichier » est la clef que le plan du dossier écrit. On testait
			// « lien », que rien ne pose : la branche n'a jamais été prise, et
			// le document du dossier n'a jamais porté une figure. Une clef
			// inventée ne casse rien — elle ne fait rien, ce qui se voit plus
			// tard et coûte plus cher.
			if ( ! empty( $item['figure']['fichier'] )
				&& method_exists( $doc, 'image_liee' ) ) {
				$doc->add_raw_paragraph( 'Normal', $doc->image_liee(
					// Le document vit dans « style/ » : il remonte d'un cran
					// pour atteindre l'icono. Sans ce « ../ », le lien ne
					// résout nulle part et Word pose un cadre vide.
					'../' . $item['figure']['fichier'],
					$item['figure']['largeur'],
					$item['figure']['hauteur'],
					$item['figure']['dpi'],
					$titre
				) );
			} elseif ( ! empty( $item['figure']['apercu'] )
				&& method_exists( $doc, 'image_incluse' ) ) {
				// Hors dossier, le document part seul : il porte donc ses
				// figures. Le lien du dossier reste préférable quand il y en
				// a un — la mise en page remplace alors l'image sans rouvrir
				// le texte — mais un lien qui ne mène nulle part ne vaut rien.
				$dessin = $doc->image_incluse(
					$item['figure']['apercu'],
					$item['figure']['largeur'],
					$item['figure']['hauteur'],
					$item['figure']['dpi'],
					$titre
				);
				if ( '' !== $dessin ) {
					$doc->add_raw_paragraph( 'Normal', $dessin );
				}
			}
			// « Fig. 1 » porte « TEI_figure_num_inline » : la chaîne sait alors
			// où finit le numéro et où commence le titre, au lieu d'avoir à le
			// deviner d'une expression régulière sur le point ou l'espace.
			$doc->add_raw_paragraph( 'TEI_figure_title',
				$doc->char_run( 'TEI_figure_num_inline', $numero )
					. ( '' !== $item['titre'] ? $doc->plain( ' ' . $item['titre'] ) : '' ) );
			if ( '' !== $item['legende'] ) {
				$doc->add_paragraph( 'TEI_figure_caption',
					array( array( 'text' => $item['legende'] ) ) );
			}
			if ( '' !== $item['credits'] ) {
				$doc->add_paragraph( 'TEI_figure_credits',
					array( array( 'text' => $item['credits'] ) ) );
			}
			$doc->add_paragraph( 'TEI_figure_end',
				array( array( 'text' => self::figure_fermante() ) ) );
		}

		// 8. Commentaires de l'auteur.
		if ( '' !== $d['commentaires'] ) {
			$doc->add_paragraph(
				'Normal',
				array(
					array( 'text' => 'Commentaires : ', 'b' => true ),
					array( 'text' => $d['commentaires'] ),
				)
			);
		}

		// 9 et 10. Ce qui sert à la rédaction, non au lecteur.
		//
		// Les coordonnées des responsables et l'indexation Pactols avec ses
		// identifiants n'ont rien à faire dans un volume imprimé : elles
		// servent à instruire la notice, puis à nourrir la chaîne XML. Depuis
		// que les métadonnées paraissent aussi en tête, ce bloc fait double
		// emploi pour qui prépare le texte.
		//
		// Il porte donc un style à lui, « à supprimer » : un clic droit
		// dessus dans le volet des styles de Word, « Sélectionner toutes les
		// occurrences », et tout le bloc s'ôte d'une touche. En RTF — le
		// repli des hébergements sans ZipArchive — le style est inconnu et le
		// paragraphe sort en Normal, sans que rien ne casse.
		$contacts = $this->contacts_list( $d );
		if ( ! empty( $contacts ) ) {
			$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER, array( array( 'text' => 'Coordonnées des responsables :', 'b' => true ) ) );
			foreach ( $contacts as $nom => $infos ) {
				$doc->add_raw_paragraph(
					Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER,
					$doc->char_run( $infos['cs'], $nom )
						. $doc->plain( ' : ' )
						. $doc->hyperlink( 'mailto:' . $infos['mail'], $infos['mail'] )
				);
			}
		}

		// 10. Ce que le texte ne porte pas.
		//
		// L'indexation reprenait tout : natures, périodes, sujets, lieux,
		// chacun suivi de son ARK entre crochets. Depuis que ces termes
		// portent leur identifiant en lien dans le texte, les répéter ici
		// n'ajoutait rien et allongeait d'autant ce qu'il faut supprimer pour
		// préparer le XML.
		//
		// Les lieux autres que celui de la notice y sont restés un temps de
		// plus, faute d'avoir où paraître dans le corps. Ils paraissent
		// maintenant sous le titre, avec leur identifiant en lien comme les
		// autres termes : les garder ici en ferait un doublon, et le bloc à
		// supprimer n'a pas à répéter ce que la notice imprime.
		//
		// Ne subsistent donc que deux notes, qui ne sont pas de l'indexation
		// mais des avis à la rédaction.

		// De quel millésime du thésaurus viennent ces identifiants.
		//
		// Pactols bouge : un concept se déprécie, une forme préférée change.
		// Deux notices déposées à six mois d'écart peuvent donc porter des
		// identifiants qui ne se valent plus, et rien ne le dirait. La date de
		// lecture est peu de chose à écrire et c'est elle qui permettra, le
		// jour où un terme surprendra, de savoir ce qu'on avait sous les yeux.
		if ( ! empty( $d['pactols_lu_du'] ) ) {
			$quand = ( $d['pactols_lu_du'] === $d['pactols_lu_au'] )
				? 'lue le ' . $d['pactols_lu_du']
				: 'lue entre le ' . $d['pactols_lu_du'] . ' et le ' . $d['pactols_lu_au'];
			$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER, array(
				array( 'text' => 'Indexation Pactols ' . $quand . '.' ),
			) );
		}
		// La date ne vaut que pour les termes lus. Ceux qui ne l'ont pas encore
		// été se disent aussi : sans cela, le préparateur tenait l'indexation
		// pour faite, et ne découvrait l'absence qu'en ouvrant le fichier
		// d'index — ou jamais, s'il travaillait depuis le Word.
		if ( ! empty( $d['pactols_manquants'] ) ) {
			$n = (int) $d['pactols_manquants'];
			$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER, array(
				array( 'text' => ( 1 === $n ? 'Un terme' : $n . ' termes' )
					. ' de l\'indexation ' . ( 1 === $n ? 'n\'a' : 'n\'ont' )
					. ' pas encore été lu' . ( 1 === $n ? '' : 's' ) . ' dans Pactols : '
					. ( 1 === $n ? 'sa' : 'leur' ) . ' forme préférée et '
					. ( 1 === $n ? 'son' : 'leur' ) . ' bloc d\'index manquent.', 'b' => true ),
			) );
		}

		// Les termes que le thésaurus a retirés.
		//
		// Le formulaire en avertit désormais l'auteur au moment où il choisit,
		// mais les notices déjà déposées n'ont pas eu cet avis, et un concept
		// peut se déprécier après coup. La chaîne refuse d'indexer sur un
		// terme mort : sans cette ligne, le mot-clé resterait nu et personne
		// ne saurait pourquoi.
		$morts = array();
		foreach ( array_keys( $this->zones_pactols() ) as $clef ) {
			foreach ( (array) ( isset( $d[ $clef ] ) ? $d[ $clef ] : array() ) as $item ) {
				if ( ! empty( $item['deprecie'] ) && ! empty( $item['label'] ) ) {
					$morts[] = $item['label'];
				}
			}
		}
		if ( ! empty( $morts ) ) {
			$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER, array(
				array( 'text' => 'Retirés du thésaurus Pactols, à reprendre avant indexation : ', 'b' => true ),
				array( 'text' => implode( ', ', array_unique( $morts ) ) . '.' ),
			) );
		}
	}

	/**
	 * Un fragment de texte, gras ou italique, dans le langage du document.
	 * La logique de composition s'écrit une fois, quel que soit le document.
	 *
	 * C'était la seule méthode à qui l'on disait le format au lieu de le lui
	 * laisser lire sur le document — et c'est par elle que le défaut est
	 * arrivé : la variable perdue en cours de route valait « nul », donc
	 * jamais « docx », et la ligne de commune sortait en RTF au milieu d'un
	 * DOCX, c'est-à-dire vide. Il n'y a plus rien à lui passer, donc plus
	 * rien à se tromper.
	 */
	private function run_xml( $doc, $run ) {
		if ( $doc instanceof Notice_Archeomed_DOCX ) {
			$props = '';
			if ( ! empty( $run['b'] ) ) {
				$props .= '<w:b/>';
			}
			if ( ! empty( $run['i'] ) ) {
				$props .= '<w:i/>';
			}
			$rpr = '' !== $props ? '<w:rPr>' . $props . '</w:rPr>' : '';
			return '<w:r>' . $rpr . '<w:t xml:space="preserve">'
				. Notice_Archeomed_DOCX::esc( $run['text'] ) . '</w:t></w:r>';
		}
		$text = Notice_Archeomed_RTF::esc( $run['text'] );
		$open = '';
		if ( ! empty( $run['b'] ) ) {
			$open .= '\\b ';
		}
		if ( ! empty( $run['i'] ) ) {
			$open .= '\\i ';
		}
		return '' === $open ? $text : '{' . $open . $text . '}';
	}

	/**
	 * Retire le numéro de rubrique en tête de libellé.
	 * « I. Constructions et habitats civils » devient
	 * « Constructions et habitats civils ».
	 */
	private function rubrique_sans_numero( $libelle ) {
		return trim( preg_replace( '/^[IVXLCDM]+\.\s*/u', '', (string) $libelle ) );
	}

	/**
	 * Le bloc « (Responsable de l'opération : …) », collé à la fin du texte.
	 * Les noms portent TEI_archeoCHR_name:fld (responsable de terrain) ou
	 * TEI_archeoCHR_name:aut (co-auteur), les institutions
	 * TEI_archeoCHR_aff_inline.
	 *
	 * Il recevait un format dont il ne se servait pas : tout passe par
	 * « plain » et « char_run », que le document sait rendre dans son propre
	 * langage. C'est ce qui l'a préservé quand la variable s'est perdue — et
	 * la raison de l'ôter partout ailleurs.
	 */
	private function responsables_rtf( $doc, $d ) {
		$segments = array();

		$resp = trim( $d['resp_prenom'] . ' ' . $d['resp_nom'] );
		if ( '' !== $resp ) {
			$seg = $doc->plain( "Responsable de l'opération : " )
				. $doc->char_run( 'TEI_archeoCHR_name:fld', $resp );
			if ( '' !== $d['resp_inst'] ) {
				$seg .= $doc->plain( ', ' )
					. $doc->char_run( 'TEI_archeoCHR_aff_inline', $d['resp_inst'] );
			}
			$segments[] = $seg;
		}

		$coresp = trim( $d['coresp_prenom'] . ' ' . $d['coresp_nom'] );
		if ( '' !== $coresp ) {
			$seg = $doc->plain( "co-responsable de l'opération : " )
				. $doc->char_run( 'TEI_archeoCHR_name:fld', $coresp );
			if ( '' !== $d['coresp_inst'] ) {
				$seg .= $doc->plain( ', ' )
					. $doc->char_run( 'TEI_archeoCHR_aff_inline', $d['coresp_inst'] );
			}
			$segments[] = $seg;
		}

		$coauteur = trim( $d['coauteur_prenom'] . ' ' . $d['coauteur_nom'] );
		if ( '' !== $coauteur ) {
			$seg = $doc->plain( 'notice rédigée avec : ' )
				. $doc->char_run( 'TEI_archeoCHR_name:aut', $coauteur );
			if ( '' !== $d['coauteur_inst'] ) {
				$seg .= $doc->plain( ', ' )
					. $doc->char_run( 'TEI_archeoCHR_aff_inline', $d['coauteur_inst'] );
			}
			$segments[] = $seg;
		}

		if ( empty( $segments ) ) {
			return '';
		}
		return $doc->plain( ' (' )
			. implode( $doc->plain( ' ; ' ), $segments )
			. $doc->plain( ')' );
	}

	/**
	 * Version en texte simple de la mention de responsabilité, conservée pour
	 * le corps du courriel HTML.
	 */
	private function responsables_inline( $d ) {
		$segments = array(
			"Responsable de l'opération : " . trim( $d['resp_prenom'] . ' ' . $d['resp_nom'] ) . ', ' . $d['resp_inst'],
		);
		$coresp = trim( $d['coresp_prenom'] . ' ' . $d['coresp_nom'] );
		if ( '' !== $coresp ) {
			$seg = "co-responsable de l'opération : " . $coresp;
			if ( '' !== $d['coresp_inst'] ) {
				$seg .= ', ' . $d['coresp_inst'];
			}
			$segments[] = $seg;
		}
		$coauteur = trim( $d['coauteur_prenom'] . ' ' . $d['coauteur_nom'] );
		if ( '' !== $coauteur ) {
			$seg = 'notice rédigée avec : ' . $coauteur;
			if ( '' !== $d['coauteur_inst'] ) {
				$seg .= ', ' . $d['coauteur_inst'];
			}
			$segments[] = $seg;
		}
		return implode( ' ; ', $segments );
	}

	/**
	 * Renvoie les couples « nom complet => adresse » des personnes renseignées.
	 */
	private function contacts_list( $d ) {
		$out   = array();
		$pairs = array(
			array( trim( $d['resp_prenom'] . ' ' . $d['resp_nom'] ), $d['resp_email'], 'TEI_archeoCHR_name:fld' ),
			array( trim( $d['coresp_prenom'] . ' ' . $d['coresp_nom'] ), $d['coresp_email'], 'TEI_archeoCHR_name:fld' ),
			array( trim( $d['coauteur_prenom'] . ' ' . $d['coauteur_nom'] ), $d['coauteur_email'], 'TEI_archeoCHR_name:aut' ),
		);
		foreach ( $pairs as $pair ) {
			list( $nom, $mail, $cs ) = $pair;
			if ( '' !== $nom && '' !== $mail && is_email( $mail ) ) {
				$out[ $nom ] = array( 'mail' => $mail, 'cs' => $cs );
			}
		}
		return $out;
	}

	public function handle_submission() {
		if ( ! isset( $_POST['notice_archeomed_envoi'] ) ) {
			return;
		}
		if ( ! isset( $_POST['notice_archeomed_nonce'] ) || ! wp_verify_nonce( $_POST['notice_archeomed_nonce'], 'notice_archeomed_submit' ) ) {
			error_log( 'Notice Archeomed: nonce verification failed.' );
			$this->redirect_result( false, 'securite' );
		}
		if ( ! empty( $_POST['na_website'] ) ) {
			error_log( 'Notice Archeomed: honeypot triggered.' );
			$this->redirect_result( false, 'securite' );
		}
		$ts = isset( $_POST['na_ts'] ) ? absint( $_POST['na_ts'] ) : 0;
		if ( $ts > 0 && ( time() - $ts ) < 3 ) {
			error_log( 'Notice Archeomed: submission too fast.' );
			$this->redirect_result( false, 'securite' );
		}
		if ( ! $this->check_attempt_limit() ) {
			error_log( 'Notice Archeomed: attempt limit reached.' );
			$this->redirect_result( false, 'quota' );
		}
		if ( ! $this->verifier_la_protection() ) {
			$this->redirect_result( false, 'verification' );
		}
		$d = array();
		$d['rubrique_principale'] = $this->collect_select( 'rubrique_principale', $this->rubriques, true );
		$d['renvoi_1'] = $this->collect_select( 'renvoi_1', $this->rubriques, false );
		$d['renvoi_2'] = $this->collect_select( 'renvoi_2', $this->rubriques, false );
		$text_fields = array(
			'departement', 'lieu_dit', 'annee', 'num_autorisation', 'id_patriarche',
			'resp_prenom', 'resp_nom', 'resp_inst',
			'coresp_prenom', 'coresp_nom', 'coresp_inst',
			'coauteur_prenom', 'coauteur_nom', 'coauteur_inst',
			'commentaires',
		);
		foreach ( $text_fields as $f ) {
			$d[ $f ] = isset( $_POST[ $f ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $f ] ) ) : '';
		}
		// Le lien du rapport passe par « esc_url_raw » et non par le tamis des
		// champs de texte : il finit dans un lien hypertexte du document, et
		// une adresse mal formée y ferait un lien qui ne mène nulle part. Les
		// schémas sont restreints à ce qu'un rapport peut porter.
		$d['rapport_lien'] = isset( $_POST['rapport_lien'] )
			? esc_url_raw( trim( wp_unslash( $_POST['rapport_lien'] ) ), array( 'http', 'https' ) ) : '';
		$d['rapport_lien'] = $this->limit_string( $d['rapport_lien'], 500 );
		$d['resp_email'] = isset( $_POST['resp_email'] ) ? sanitize_email( wp_unslash( $_POST['resp_email'] ) ) : '';
		$d['coresp_email'] = isset( $_POST['coresp_email'] ) ? sanitize_email( wp_unslash( $_POST['coresp_email'] ) ) : '';
		$d['coauteur_email'] = isset( $_POST['coauteur_email'] ) ? sanitize_email( wp_unslash( $_POST['coauteur_email'] ) ) : '';
		foreach ( array( 'departement' => 120, 'lieu_dit' => 200, 'annee' => 20, 'num_autorisation' => 120, 'id_patriarche' => 120, 'resp_prenom' => 100, 'resp_nom' => 100, 'resp_inst' => 200, 'coresp_prenom' => 100, 'coresp_nom' => 100, 'coresp_inst' => 200, 'coauteur_prenom' => 100, 'coauteur_nom' => 100, 'coauteur_inst' => 200, 'commentaires' => 3000 ) as $field => $max ) {
			$d[ $field ] = $this->limit_string( $d[ $field ], $max );
		}
		$d['departement'] = $this->sans_parentheses( $d['departement'] );
		$d['lieux'] = $this->collect_lieux();
		// « commune » reste le premier lieu : c'est lui qui donne le titre de
		// la fiche, le nom du fichier, l'objet du courriel et le rang au
		// classement. Le reste du plugin n'a pas à savoir qu'il peut y en
		// avoir cinq.
		$d['commune']     = isset( $d['lieux'][0] ) ? $d['lieux'][0]['nom'] : '';
		$d['commune_ark'] = isset( $d['lieux'][0] ) ? $d['lieux'][0]['ark'] : '';
		$d['remplace'] = $this->reference_corrigee();
		$d['organismes'] = $this->collect_organismes();
		// Comme pour les lieux : « organisme » reste la ligne unique que le
		// reste du plugin sait lire, et le contrôle des champs obligatoires
		// avec lui.
		$d['organisme'] = implode( ', ', $d['organismes'] );
		$d['illustrations'] = $this->collect_illustrations();
		$d['nature'] = $this->collect_checkbox( 'nature', $this->natures );
		$d['nature_items'] = $this->collect_natures_items();
		$d['pactols_periods_items'] = $this->collect_pactols_keywords( 'pactols_periods' );
		$d['pactols_subjects_items'] = $this->collect_pactols_keywords( 'pactols_subjects' );
		$d['pactols_places_items'] = $this->collect_pactols_keywords( 'pactols_places' );
		$d['pactols_periods_text'] = $this->pactols_keywords_to_text( $d['pactols_periods_items'] );
		$d['pactols_subjects_text'] = $this->pactols_keywords_to_text( $d['pactols_subjects_items'] );
		$d['pactols_places_text'] = $this->pactols_keywords_to_text( $d['pactols_places_items'] );
		$raw_texte = isset( $_POST['texte_notice'] ) ? wp_unslash( $_POST['texte_notice'] ) : '';
		if ( strlen( $raw_texte ) > 200000 ) {
			error_log( 'Notice Archeomed: encoded notice text too long.' );
			$this->redirect_result( false, 'texte' );
		}
		$d['texte_notice'] = $this->texte_notice_pose( $raw_texte );
		$manquant = $this->validate_required_fields( $d );
		if ( '' !== $manquant ) {
			error_log( 'Notice Archeomed: missing required field: ' . $manquant );
			$this->redirect_result( false, 'texte' === $manquant ? 'texte' : 'champs',
				'texte' === $manquant ? '' : $manquant );
		}
		if ( ! $this->check_send_limit( $d['resp_email'] ) ) {
			error_log( 'Notice Archeomed: send limit reached.' );
			$this->redirect_result( false, 'envois' );
		}
		$upload_error = '';
		$attachments = $this->handle_uploads( $upload_error );
		if ( '' !== $upload_error ) {
			error_log( 'Notice Archeomed: upload error: ' . $upload_error );
			$this->redirect_result( false, 'fichiers' );
		}
		$notice = $this->build_notice( $d );
		// Génération du fichier stylé Métopes, joint au courriel de la
		// rédaction. C'est rapide — quelques dizaines de millisecondes — et
		// cela reste donc dans la requête de l'auteur : il vaut mieux qu'il
		// sache tout de suite si son document n'a pas pu être fabriqué.
		// Les figures que le document emportera. Elles se fabriquent avant lui,
		// puisqu'il les porte : le Word envoyé par courriel voyage seul, sans
		// dossier d'icono à côté de lui où aller chercher une image.
		//
		// Ils vont au document et non à la saisie. La saisie se garde, et tout
		// ce qui la relit — le fascicule, le dossier, la page de relecture —
		// aurait hérité d'une figure faite pour un courriel, avec son chemin
		// sur le serveur. Le dossier en a posé une : une image de mille
		// pixels, dans le fichier, que la mise en page ne pouvait remplacer.
		$apercus = $this->fabriquer_les_apercus( $attachments, $d );
		$d_doc   = $this->attacher_les_apercus( $d, $apercus );

		$rtf_error = '';
		// Format de la pièce jointe : DOCX par défaut, RTF en repli si
		// l'extension ZipArchive est absente de l'hébergement.
		$format   = class_exists( 'ZipArchive' ) ? 'docx' : 'rtf';
		$rtf_file = $this->build_rtf_file( $d_doc, $rtf_error, $format );
		if ( '' === $rtf_file && 'docx' === $format ) {
			// Repli sur le RTF si la production du DOCX a échoué.
			error_log( 'Notice Archeomed: DOCX generation failed, falling back to RTF: ' . $rtf_error );
			$rtf_file = $this->build_rtf_file( $d_doc, $rtf_error, 'rtf' );
		}
		if ( '' !== $rtf_error ) {
			error_log( 'Notice Archeomed: RTF generation failed: ' . $rtf_error );
		}

		// Les fichiers vivent désormais plus longtemps que la requête : on les
		// met à l'abri de la purge quotidienne dès maintenant. Le document
		// stylé se suit à part — c'est le seul qui repart aux auteurs — plutôt
		// que d'être repêché en fin de liste, ce qui tenait à l'ordre.
		$illustrations = $this->mettre_a_labri( $attachments );
		$document      = '';
		if ( '' !== $rtf_file ) {
			$mis      = $this->mettre_a_labri( array( $rtf_file ) );
			$document = ! empty( $mis ) ? $mis[0] : '';
		}
		$produits = $illustrations;
		if ( '' !== $document ) {
			$produits[] = $document;
		}
		$reference = strtoupper( wp_generate_password( 6, false, false ) );
		// La saisie est mise en réserve pour un mois, et son lien part avec la
		// copie de l'auteur : c'est en lisant sa notice qu'il voit ce qu'il
		// n'a pas vu en la saisissant. Le lien se calcule ici, dans la requête
		// de l'auteur — le planificateur, lui, n'a plus de page de référence.
		$d['correction_url'] = $this->lien_de_correction( $reference );
		// Le lien par lequel on vient d'arriver a fait son office. Le laisser
		// vivre, c'est permettre qu'une troisième notice vienne corriger la
		// première, et la rédaction en aurait trois sur les bras.
		$this->oublier_le_jeton_repris();

		// ── Le dépôt, et rien de plus ──────────────────────────────────────
		//
		// Tout ce qui est lent — la poignée de main SMTP, le transfert de
		// vingt méga-octets de pièces jointes, les accusés de réception —
		// sort de la requête de l'auteur. Elle rend la main tout de suite, et
		// le processus PHP se libère pour le déposant suivant.
		if ( $this->envoi_differe() ) {
			$id = $this->file()->deposer( $d, $notice, $produits, $document, $reference );
			if ( $id ) {
				if ( ! empty( $apercus ) ) {
					update_post_meta( $id, '_na_apercus', $apercus );
				}
				// Les illustrations gagnent leur place définitive dès
				// maintenant, et la file retient ces chemins-là : c'est eux
				// que le courriel joindra, et eux que le dossier Métopes
				// trouvera même si le courriel ne part jamais.
				$gardees = $this->archiver_les_illustrations( $id, $illustrations );
				$fichiers = $gardees;
				if ( '' !== $document ) {
					$fichiers[] = $document;
				}
				update_post_meta( $id, '_na_fichiers', $fichiers );
				$this->record_send( $d['resp_email'] );
				$this->file()->programmer( $id );
				$this->redirect_result( true );
			}
			// L'inscription a échoué — base de données indisponible. Plutôt
			// que de perdre la notice, on retombe sur l'envoi immédiat.
			error_log( 'Notice Archeomed: dépôt en file impossible, envoi immédiat.' );
		}

		// Sans dépôt, les aperçus n'ont plus d'usage : ils sont dans le
		// document déjà fabriqué, et aucune notice ne les retrouverait. Les
		// laisser, c'était les oublier dans le dossier de dépôt.
		$this->effacer_des_apercus( $apercus );

		$envoyee = $this->expedier( $d, $notice, $produits, $document );
		if ( $envoyee ) {
			$this->record_send( $d['resp_email'] );
			$this->nettoyer( $produits );
			$this->redirect_result( true );
		}
		$this->mettre_de_cote( $d, $produits, $reference );
		error_log( 'Notice Archeomed: notice conservée sous la référence ' . $reference );
		$this->redirect_result( false, 'envoi', '', $reference );
	}
	/**
	 * Garde une notice dont le courriel n'est pas parti, et rend sa référence.
	 *
	 * Tout était effacé sans distinction : le DOCX produit, les illustrations
	 * téléversées, et la saisie avec. Une panne de serveur de courriel — la
	 * plus banale des pannes — coûtait donc à l'auteur une heure de rédaction
	 * et une notice à refaire de mémoire.
	 *
	 * Les fichiers sont renommés « -garde- » pour échapper à la purge
	 * quotidienne, et la saisie est mise en réserve un mois : de quoi laisser
	 * à la rédaction le temps de répondre au courriel de l'auteur.
	 */
	private function mettre_de_cote( $d, $produits, $reference ) {
		$gardes = $this->mettre_a_labri( $produits );
		set_transient(
			'na_garde_' . $reference,
			array(
				'quand'    => current_time( 'mysql' ),
				'commune'  => $d['commune'],
				'annee'    => $d['annee'],
				'courriel' => $d['resp_email'],
				'champs'   => $d,
				'fichiers' => $gardes,
			),
			MONTH_IN_SECONDS
		);
		return $reference;
	}

	/**
	 * Le planificateur appelle ceci pour chaque notice inscrite.
	 */
	public function brancher_la_file() {
		add_action( Notice_Archeomed_File::HOOK_UNE,
			array( $this, 'expedier_de_la_file' ), 10, 1 );
		add_action( self::HOOK_TERMES,
			array( $this, 'resoudre_en_tache' ), 10, 1 );
		add_action( 'admin_init', array( $this, 'reveiller_la_file' ) );
	}

	/**
	 * L'instance de la file d'attente, celle que le plugin a créée au départ.
	 */
	private function file() {
		global $notice_archeomed_file;
		if ( ! ( $notice_archeomed_file instanceof Notice_Archeomed_File ) ) {
			$notice_archeomed_file = new Notice_Archeomed_File();
		}
		return $notice_archeomed_file;
	}

	/**
	 * Envoie la notice à la rédaction, puis sa copie aux personnes citées.
	 *
	 * Appelée soit dans la requête de l'auteur — mode immédiat —, soit par le
	 * planificateur, ce qui est le cas ordinaire. Rend vrai si le courriel de
	 * la rédaction est parti : c'est lui qui compte, les copies sont un
	 * agrément.
	 */
	public function expedier( $d, $notice, $produits, $document = '', &$pourquoi = '', $id = 0,
		&$restees = array() ) {
		$pourquoi = '';
		$restees  = array();
		$wrap_open  = '<html><body><div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#222;">';
		$wrap_close = '</div></body></html>';

		$resp_complet = trim( $d['resp_prenom'] . ' ' . $d['resp_nom'] );
		$subject = 'Notice Archéomed - ' . $d['commune'] . ' (' . $d['departement'] . ')';
		// Une boîte pleine se trie sur les objets : le bandeau du corps ne se
		// voit qu'une fois le message ouvert, et deux notices de la même
		// commune se ressemblent trop pour qu'on ouvre les deux.
		if ( ! empty( $d['remplace'] ) ) {
			$subject = '[CORRECTION de ' . $d['remplace'] . '] ' . $subject;
		}
		$subject = str_replace( array( "\r", "\n" ), ' ', $subject );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( is_email( $d['resp_email'] ) ) {
			$resp_name_safe = str_replace( array( "\r", "\n", '<', '>' ), '', $resp_complet );
			$headers[] = 'Reply-To: ' . $resp_name_safe . ' <' . $d['resp_email'] . '>';
		}

		// Sans destinataire, on ne tente rien : rendre faux laisse la notice en
		// file, où elle attend qu'une adresse soit renseignée plutôt que de
		// partir nulle part.
		$pour_la_redaction = $this->destinataires_des_notices();
		if ( empty( $pour_la_redaction ) ) {
			$pourquoi = 'Aucun destinataire ne reçoit les notices. Réglages ▸ Qui reçoit quoi : '
				. 'cochez « Notices » pour au moins une adresse.';
			error_log( 'Notice Archeomed: ' . $pourquoi );
			return false;
		}
		$redaction = $pour_la_redaction[0];

		$existants = array_values( array_filter( (array) $produits, 'file_exists' ) );
		// Ce que le courriel peut porter. Le serveur refuse un message trop
		// lourd tout entier, et la notice ne partait jamais : deux images de
		// 4 Mo suffisaient. Pour une notice déposée, dont les illustrations
		// sont rangées sur le site, on joint ce qui tient et l'on nomme le
		// reste, avec le lien de la notice où il se télécharge. Sans dépôt —
		// l'envoi immédiat —, rien n'est rangé nulle part : on joint tout, et
		// un refus garde la notice de côté plutôt que d'en perdre une figure.
		$joindre = $existants;
		if ( $id ) {
			$joindre = $this->pieces_qui_tiennent( $existants, $document, strlen( $notice ), $restees );
		}
		$corps = $notice;
		if ( ! empty( $restees ) ) {
			$corps = $this->avis_des_pieces_restees( $restees, $id ) . $notice;
		}
		// Un seul envoi pour toute la rédaction : les pièces jointes pèsent
		// jusqu'à vingt méga-octets, et les répéter par destinataire ferait
		// payer la liste au poids.
		$sent = wp_mail( $pour_la_redaction, $subject, $wrap_open . $corps . $wrap_close,
			$headers, $joindre );
		if ( ! $sent ) {
			// « wp_mail » rend faux sans dire pourquoi. Le crochet
			// « wp_mail_failed » porte l'erreur de PHPMailer — la vraie
			// raison, celle qu'on cherchait dans les journaux du serveur.
			$pourquoi = 'Le serveur de courriel a refusé l’envoi vers '
				. implode( ', ', $pour_la_redaction ) . '.';
			if ( '' !== $this->derniere_erreur_mail ) {
				$pourquoi .= ' ' . $this->derniere_erreur_mail;
			}
			error_log( 'Notice Archeomed: ' . $pourquoi );
			return false;
		}

		$destinataires = array_unique( array_filter(
			array( $d['resp_email'], $d['coresp_email'], $d['coauteur_email'] ), 'is_email' ) );
		if ( empty( $destinataires ) ) {
			return true;
		}
		// Le document stylé part aussi aux auteurs. C'est le même fichier que
		// celui reçu par la rédaction : ils voient ce qui a été produit à
		// partir de leur saisie, peuvent le relire, et le renvoyer corrigé
		// sans qu'on ait à le leur redemander.
		$pour_les_auteurs = ( '' !== $document && file_exists( $document ) )
			? array( $document ) : array();

		$pj_note = '';
		if ( ! empty( $pour_les_auteurs ) ) {
			$pj_note = '<p><strong>Pièce jointe :</strong> le fichier Word de votre notice, mis aux normes de la revue. Il est identique à celui reçu par la rédaction.</p>';
		}
		if ( count( $existants ) > count( $pour_les_auteurs ) ) {
			$pj_note .= '<p>Les illustrations transmises avec le formulaire ont bien été envoyées à la rédaction.</p>';
		}
		// Le lien qui rouvre le formulaire rempli. C'est en lisant sa notice
		// qu'on voit ce qu'on n'a pas vu en la saisissant : sans ce lien, il
		// fallait tout retaper, ou écrire à la rédaction.
		$correction = '';
		if ( ! empty( $d['correction_url'] ) ) {
			$url = esc_url( $d['correction_url'] );
			$correction = '<p style="background:#fdf6e3;border:1px solid #8a6d3b;padding:14px 18px;">'
				. '<strong>Une erreur vous saute aux yeux ?</strong><br>'
				. 'Ce lien rouvre le formulaire rempli de votre saisie : corrigez ce qui doit '
				. 'l\'être, et renvoyez-le. Les illustrations sont à redéposer — aucun '
				. 'navigateur ne permet de les remettre en place. Votre nouveau dépôt '
				. 'préviendra la rédaction qu\'il remplace celui-ci. Le lien vaut un mois.<br>'
				. '<a href="' . $url . '">' . $url . '</a></p>';
		}
		$rappel = '';
		if ( ! empty( $d['remplace'] ) ) {
			$rappel = '<p>Cette notice remplace votre dépôt précédent, référence <strong>'
				. esc_html( $d['remplace'] ) . '</strong> : la rédaction en est avertie et '
				. 'ne gardera que celle-ci.</p>';
		}
		$intro = '<p>Bonjour,</p>'
			// Le titre de la revue s'écrit en italique : c'est un titre
			// d'ouvrage, et la rédaction le compose ainsi partout ailleurs.
			. '<p>Nous vous remercions d\'avoir soumis une notice archéologique pour la Chronique d\'<em>Archéologie médiévale</em>. Vous en trouverez ci-dessous la copie.</p>'
			. $rappel
			. $pj_note
			. $correction
			. '<p>La rédaction harmonise les notices de la Chronique : elle peut apporter à la vôtre, sans vous en aviser, les corrections de forme que cette harmonisation demande. Elle ne reviendra vers vous que sur une question de fond.</p>'
			. '<hr>';
		$headers_conf = array( 'Content-Type: text/html; charset=UTF-8',
			'Reply-To: ' . $redaction );
		$objet_copie = ! empty( $d['remplace'] )
			? 'Copie de votre notice corrigée - Archéologie médiévale'
			: 'Copie de votre notice - Archéologie médiévale';
		foreach ( $destinataires as $dest ) {
			wp_mail( $dest, $objet_copie,
				$wrap_open . $intro . $notice . $wrap_close, $headers_conf,
				$pour_les_auteurs );
		}
		return true;
	}

	/**
	 * Fait passer les courriels par un relais SMTP plutôt que par « mail() ».
	 *
	 * Sur un hébergement où la fonction mail() de PHP n'est pas configurée —
	 * désactivée, ou sans agent de transport derrière —, PHPMailer répond
	 * « Impossible d'instancier la fonction mail » et aucune notice ne part
	 * jamais. Un relais SMTP contourne la question sans rien demander à
	 * personne.
	 *
	 * On ne touche à rien tant que le mode n'est pas choisi : une
	 * installation dont le courriel fonctionne n'a aucune raison de changer
	 * de chemin.
	 */
	public function acheminer_par_smtp( $phpmailer ) {
		if ( 'smtp' !== Notice_Archeomed_Settings::get( 'envoi_mode' ) ) {
			return;
		}
		$hote = trim( (string) Notice_Archeomed_Settings::get( 'smtp_hote' ) );
		if ( '' === $hote ) {
			return;   // mode choisi mais relais non nommé : on ne casse rien
		}
		$phpmailer->isSMTP();
		$phpmailer->Host = $hote;
		$phpmailer->Port = (int) Notice_Archeomed_Settings::get( 'smtp_port' );

		$chiffrement = Notice_Archeomed_Settings::get( 'smtp_chiffrement' );
		if ( 'aucun' === $chiffrement ) {
			$phpmailer->SMTPSecure  = '';
			$phpmailer->SMTPAutoTLS = false;
		} else {
			$phpmailer->SMTPSecure = $chiffrement;
		}

		$utilisateur = trim( (string) Notice_Archeomed_Settings::get( 'smtp_utilisateur' ) );
		$motdepasse  = (string) Notice_Archeomed_Settings::get( 'smtp_motdepasse' );
		if ( '' !== $utilisateur ) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $utilisateur;
			$phpmailer->Password = $motdepasse;
		} else {
			// Un relais institutionnel accepte souvent ses propres machines
			// sans authentification : l'exiger ferait échouer ce qui marche.
			$phpmailer->SMTPAuth = false;
		}

		// L'expéditeur doit appartenir au domaine que le relais accepte,
		// sans quoi il refuse le message pour usurpation.
		$expediteur = trim( (string) Notice_Archeomed_Settings::get( 'smtp_expediteur' ) );
		if ( is_email( $expediteur ) ) {
			$nom = trim( (string) Notice_Archeomed_Settings::get( 'smtp_nom' ) );
			$phpmailer->setFrom( $expediteur, '' !== $nom ? $nom : '', false );
		}
	}

	/**
	 * Envoie un courriel d'essai, et rend ce que le serveur a répondu.
	 *
	 * Sans lui, éprouver l'acheminement demandait de déposer une notice et
	 * d'attendre le planificateur — puis de lire une fiche pour savoir ce qui
	 * s'était passé. Ici la réponse est immédiate.
	 */
	public function tester_l_envoi( $destinataire ) {
		if ( ! is_email( $destinataire ) ) {
			return array( 'ok' => false, 'message' => 'Adresse d’essai non valide.' );
		}
		return $this->poster_un_essai( 'simple', array( $destinataire ) );
	}

	/**
	 * Refait l'envoi d'une notice, élément par élément, pour voir lequel
	 * le fait tomber.
	 *
	 * Un essai nu partait quand une notice était refusée : la cause n'était
	 * donc ni le serveur ni la fonction mail(), mais l'un des quatre écarts
	 * entre les deux envois — plusieurs destinataires, un en-tête Reply-To
	 * bâti avec le nom de l'auteur, une pièce jointe, un corps en HTML long.
	 * Les essayer un à un dit lequel, au lieu de chercher à l'aveugle.
	 */
	public function essais_successifs() {
		$liste = $this->destinataires_des_notices();
		if ( empty( $liste ) ) {
			return array( array( 'ok' => false, 'etape' => 'destinataires',
				'message' => 'Aucun destinataire ne reçoit les notices.' ) );
		}
		$resultats = array();
		$resultats[] = $this->poster_un_essai(
			'un seul destinataire', array( $liste[0] ) );
		if ( count( $liste ) > 1 ) {
			$resultats[] = $this->poster_un_essai(
				'tous les destinataires (' . count( $liste ) . ')', $liste );
		}
		$resultats[] = $this->poster_un_essai(
			'avec un en-tête Reply-To', array( $liste[0] ), true );
		$resultats[] = $this->poster_un_essai(
			'avec une pièce jointe', array( $liste[0] ), false, true );
		$resultats[] = $this->poster_un_essai(
			'comme une notice : tout à la fois', $liste, true, true );
		return $resultats;
	}

	/**
	 * Refait l'envoi d'une notice précise, pièce par pièce.
	 *
	 * Les essais génériques passaient tous, y compris « tout à la fois » :
	 * ce n'est donc pas la forme de l'envoi qui pèche, mais la matière de
	 * cette notice-là. On reprend donc ses propres éléments — son sujet, son
	 * corps, son en-tête de réponse, ses pièces jointes — et on les ajoute un
	 * à un jusqu'à ce que le serveur refuse.
	 *
	 * Rien n'est envoyé à la rédaction : tout part vers l'adresse d'essai.
	 */
	public function diagnostiquer_la_notice( $id, $vers ) {
		$d = get_post_meta( (int) $id, '_na_donnees', true );
		if ( ! is_array( $d ) || empty( $d ) ) {
			return array( array( 'ok' => false, 'etape' => 'notice',
				'message' => 'Saisie illisible en réserve.' ) );
		}
		if ( ! is_email( $vers ) ) {
			return array( array( 'ok' => false, 'etape' => 'adresse',
				'message' => 'Adresse d’essai non valide.' ) );
		}
		$notice   = (string) get_post_meta( (int) $id, '_na_notice', true );
		$produits = array_values( array_filter(
			(array) get_post_meta( (int) $id, '_na_fichiers', true ), 'file_exists' ) );

		// Marqués comme essais : ils portaient le sujet et le corps de la vraie
		// notice, et l'essai qui réussissait avec une seule pièce jointe se
		// prenait pour la notice partie amputée d'une figure.
		$sujet = '[ESSAI] Notice Archéomed - ' . $d['commune'] . ' (' . $d['departement'] . ')';
		$sujet = str_replace( array( "\r", "\n" ), ' ', $sujet );
		$corps = '<html><body><p style="background:#eef;border:1px solid #88a;padding:8px 12px;">'
			. '<strong>Essai de diagnostic</strong> — ce message n’est pas l’envoi de la notice, '
			. 'il ne sert qu’à savoir ce que le serveur accepte.</p><div>' . $notice . '</div></body></html>';
		$resp  = trim( $d['resp_prenom'] . ' ' . $d['resp_nom'] );
		$reply = '';
		if ( is_email( $d['resp_email'] ) ) {
			$sans = str_replace( array( "\r", "\n", '<', '>' ), '', $resp );
			$reply = 'Reply-To: ' . $sans . ' <' . $d['resp_email'] . '>';
		}

		$base = array( 'Content-Type: text/html; charset=UTF-8' );
		$out  = array();
		$out[] = $this->poster_brut( 'le sujet réel, corps court',
			$vers, $sujet, '<html><body><p>Essai.</p></body></html>', $base, array() );
		$out[] = $this->poster_brut( 'le corps réel (' . size_format( strlen( $corps ) ) . ')',
			$vers, $sujet, $corps, $base, array() );
		if ( '' !== $reply ) {
			$out[] = $this->poster_brut( 'l’en-tête de réponse : ' . $reply,
				$vers, $sujet, $corps, array_merge( $base, array( $reply ) ), array() );
		}
		foreach ( $produits as $rang => $fichier ) {
			$out[] = $this->poster_brut(
				'pièce jointe ' . ( $rang + 1 ) . ' : ' . basename( $fichier )
					. ' (' . size_format( filesize( $fichier ) ) . ')',
				$vers, $sujet, $corps,
				'' !== $reply ? array_merge( $base, array( $reply ) ) : $base,
				array_slice( $produits, 0, $rang + 1 ) );
		}
		if ( empty( $produits ) ) {
			$out[] = array( 'ok' => true, 'etape' => 'pièces jointes',
				'message' => 'aucune — rien à joindre pour cette notice' );
		} else {
			// Ce que l'envoi véritable ferait de ces pièces, au poids réglé.
			$restees  = array();
			$document = (string) get_post_meta( (int) $id, '_na_document', true );
			$joindre  = $this->pieces_qui_tiennent( $produits, $document, strlen( $notice ), $restees );
			$out[]    = array( 'ok' => true, 'etape' => 'l’envoi de la notice',
				'message' => empty( $restees )
					? 'joindrait les ' . count( $joindre ) . ' pièces : elles tiennent sous '
						. (int) Notice_Archeomed_Settings::get( 'poids_courriel' ) . ' Mo.'
					: 'joindrait ' . count( $joindre ) . ' pièce(s) sur ' . count( $produits )
						. ' et nommerait les autres, restées sur le site : au-delà de '
						. (int) Notice_Archeomed_Settings::get( 'poids_courriel' ) . ' Mo, le serveur refuse le message.' );
		}
		return $out;
	}

	/** Un envoi, tel quel, sans rien ajouter ni retrancher. */
	private function poster_brut( $etape, $vers, $sujet, $corps, $entetes, $jointes ) {
		$this->derniere_erreur_mail = '';
		$parti = wp_mail( $vers, $sujet, $corps, $entetes, $jointes );
		return $parti
			? array( 'ok' => true, 'etape' => $etape, 'message' => 'parti' )
			: array( 'ok' => false, 'etape' => $etape,
				'message' => 'refusé — ' . ( '' !== $this->derniere_erreur_mail
					? $this->derniere_erreur_mail : 'aucune raison donnée' ) );
	}

	/**
	 * Un envoi d'essai, avec ou sans les éléments qui distinguent une notice.
	 */
	private function poster_un_essai( $etape, $vers, $reply_to = false, $piece_jointe = false ) {
		$this->derniere_erreur_mail = '';
		$entetes = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( $reply_to ) {
			$utilisateur = wp_get_current_user();
			$nom = trim( $utilisateur->display_name );
			$entetes[] = 'Reply-To: ' . $nom . ' <' . $utilisateur->user_email . '>';
		}
		$jointes = array();
		if ( $piece_jointe ) {
			// Une vraie pièce jointe, fabriquée pour l'essai et effacée après :
			// c'est son existence qui change le chemin de PHPMailer, non sa
			// taille.
			$fichier = wp_tempnam( 'na-essai' );
			file_put_contents( $fichier, "Pièce jointe d'essai.\r\n" );
			$jointes[] = $fichier;
		}
		$parti = wp_mail(
			$vers,
			'Essai d’acheminement (' . $etape . ') — notices d’archéologie médiévale',
			'<html><body><p>Essai : ' . esc_html( $etape ) . '.</p></body></html>',
			$entetes,
			$jointes
		);
		foreach ( $jointes as $f ) {
			@unlink( $f );
		}
		if ( $parti ) {
			return array( 'ok' => true, 'etape' => $etape,
				'message' => 'parti vers ' . implode( ', ', $vers ) );
		}
		return array( 'ok' => false, 'etape' => $etape,
			'message' => 'refusé — ' . ( '' !== $this->derniere_erreur_mail
				? $this->derniere_erreur_mail : 'aucune raison donnée' ) );
	}

	/** Retient ce que le serveur de courriel a répondu, pour le dire plus haut. */
	public function retenir_l_erreur_mail( $erreur ) {
		if ( is_wp_error( $erreur ) ) {
			$this->derniere_erreur_mail = trim( (string) $erreur->get_error_message() );
		}
	}

	/**
	 * Ce que le planificateur appelle : une notice inscrite, à expédier.
	 */
	/**
	 * Les pièces que le courriel peut porter sans dépasser le poids réglé.
	 *
	 * Le document d'abord : c'est la notice même, et il est léger. Puis les
	 * illustrations dans l'ordre de leurs figures, tant qu'elles tiennent. Une
	 * pièce jointe voyage encodée en base 64, soit un tiers de plus, avec une
	 * fin de ligne tous les soixante-seize caractères ; le corps et les
	 * en-têtes prennent le reste.
	 */
	private function pieces_qui_tiennent( $existants, $document, $poids_du_corps, &$restees ) {
		$restees = array();
		// En méga-octets décimaux : le binaire en faisait 10 485 760 octets
		// pour « 10 Mo », au-dessus des 10 240 000 de Postfix — un message
		// passait le compte et se faisait refuser quand même.
		$budget  = (int) Notice_Archeomed_Settings::get( 'poids_courriel' ) * 1000 * 1000
			- 3 * $poids_du_corps - 64 * KB_IN_BYTES;
		$ordre   = $existants;
		if ( '' !== $document && in_array( $document, $existants, true ) ) {
			$ordre = array_merge( array( $document ),
				array_values( array_diff( $existants, array( $document ) ) ) );
		}
		$joindre = array();
		$cumul   = 0;
		foreach ( $ordre as $fichier ) {
			$encode = (int) ceil( filesize( $fichier ) * 4 / 3 * 78 / 76 );
			if ( $cumul + $encode <= $budget ) {
				$joindre[] = $fichier;
				$cumul    += $encode;
			} else {
				$restees[] = $fichier;
			}
		}
		return $joindre;
	}

	/**
	 * L'encadré qui ouvre le courriel quand des illustrations sont restées
	 * sur le site : lesquelles, combien elles pèsent, et où les prendre.
	 */
	private function avis_des_pieces_restees( $restees, $id ) {
		$lignes = array();
		foreach ( $restees as $fichier ) {
			$lignes[] = '<li>' . esc_html( basename( $fichier ) ) . ' ('
				. esc_html( size_format( filesize( $fichier ) ) ) . ')</li>';
		}
		$lien = admin_url( 'post.php?post=' . (int) $id . '&action=edit' );
		return '<div style="background:#fdf6e3;border:2px solid #8a6d3b;padding:14px 18px;margin:0 0 18px;">'
			. '<strong>' . esc_html( sprintf(
				_n( '%d illustration n’est pas jointe à ce courriel', '%d illustrations ne sont pas jointes à ce courriel',
					count( $restees ), 'notice-archeomed' ), count( $restees ) ) )
			. '</strong> : elles dépasseraient le poids que le serveur de courriel accepte ('
			. (int) Notice_Archeomed_Settings::get( 'poids_courriel' ) . ' Mo).'
			. '<ul style="margin:8px 0">' . implode( '', $lignes ) . '</ul>'
			. 'Elles sont conservées sur le site, et se téléchargent depuis la fiche de la notice : '
			. '<a href="' . esc_url( $lien ) . '">' . esc_html( $lien ) . '</a>. '
			. 'Elles figurent aussi dans le dossier Métopes de la rubrique.</div>';
	}

	public function expedier_de_la_file( $id ) {
		$id = (int) $id;
		if ( ! $id || ! $this->file()->prendre( $id ) ) {
			return;
		}
		$d        = get_post_meta( $id, '_na_donnees', true );
		$notice   = (string) get_post_meta( $id, '_na_notice', true );
		$produits = (array) get_post_meta( $id, '_na_fichiers', true );
		$document = (string) get_post_meta( $id, '_na_document', true );
		if ( ! is_array( $d ) || empty( $d ) ) {
			$this->file()->marquer( $id, 'echec', 'Notice illisible en réserve.' );
			return;
		}
		// Rattrapage des notices déposées avant que l'archivage ne se sépare
		// de l'envoi : leurs illustrations attendent encore au répertoire
		// temporaire, et rien ne les rangerait si le courriel ne partait
		// jamais. On les range maintenant, une fois pour toutes.
		$deja = (array) get_post_meta( $id, '_na_illustrations', true );
		if ( empty( $deja ) && ! empty( $produits ) ) {
			$a_ranger = array();
			foreach ( $produits as $fichier ) {
				if ( '' !== $document && $fichier === $document ) {
					continue;   // le document se refabrique, il ne s'archive pas
				}
				$a_ranger[] = $fichier;
			}
			if ( ! empty( $a_ranger ) ) {
				$gardees  = $this->archiver_les_illustrations( $id, $a_ranger );
				$produits = $gardees;
				if ( '' !== $document ) {
					$produits[] = $document;
				}
				update_post_meta( $id, '_na_fichiers', $produits );
			}
		}
		// Le thésaurus se consulte dans une tâche à part, et non ici.
		//
		// Il se consultait avant l'envoi : un Pactols lent retardait donc le
		// courriel, et un Pactols en panne pouvait tuer la requête avant qu'il
		// parte — trois appels de huit secondes par terme, dans une requête
		// que l'hébergement coupe à trente. Le courriel n'a pas besoin du
		// thésaurus : il part avec la saisie telle qu'elle a été faite. Ce que
		// le thésaurus dit sert au fascicule, au dossier et aux blocs d'index,
		// qui peuvent attendre une minute.
		$this->mettre_en_attente( $id, $d );

		$pourquoi = '';
		$restees  = array();
		if ( $this->expedier( $d, $notice, $produits, $document, $pourquoi, $id, $restees ) ) {
			// Partie, mais pas entière : la liste le dit, pour qu'on ne croie
			// pas la rédaction en possession de toutes les figures.
			$this->file()->marquer( $id, 'envoyee', empty( $restees ) ? '' : sprintf(
				_n( 'Partie sans %d illustration, trop lourde pour un courriel ; elle reste sur le site.',
					'Partie sans %d illustrations, trop lourdes pour un courriel ; elles restent sur le site.',
					count( $restees ), 'notice-archeomed' ), count( $restees ) ) );
			// Les illustrations sont déjà rangées depuis le dépôt : il ne
			// reste qu'à effacer le document, qui se refabrique à la demande
			// et n'a aucune raison d'encombrer le répertoire temporaire.
			if ( '' !== $document && file_exists( $document ) ) {
				@unlink( $document );
				delete_post_meta( $id, '_na_document' );
			}
			return;
		}
		$this->file()->compter_un_essai( $id, $pourquoi );
	}

	/**
	 * Renomme les fichiers pour qu'ils survivent à la purge quotidienne.
	 *
	 * Une notice en file d'attente peut patienter : si ses pièces jointes
	 * disparaissaient au bout d'un jour, elle partirait amputée sans que
	 * personne s'en aperçoive.
	 */
	private function mettre_a_labri( $fichiers ) {
		$gardes = array();
		foreach ( (array) $fichiers as $file ) {
			if ( ! file_exists( $file ) || false === strpos( $file, 'notice-archeomed-' ) ) {
				continue;
			}
			if ( false !== strpos( $file, 'notice-archeomed-garde-' ) ) {
				$gardes[] = $file;
				continue;
			}
			$garde = str_replace( 'notice-archeomed-', 'notice-archeomed-garde-', $file );
			$gardes[] = @rename( $file, $garde ) ? $garde : $file;
		}
		return $gardes;
	}

	/**
	 * Sert une illustration conservée, à qui a le droit de la voir.
	 *
	 * Le dossier n'est pas servi par le web : c'est cette porte-ci, et elle
	 * demande d'être entré dans l'administration.
	 */
	public function telecharger_une_illustration() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$rang = isset( $_GET['rang'] ) ? (int) $_GET['rang'] : -1;
		check_admin_referer( 'na_illustration_' . $id . '_' . $rang );
		$gardees = (array) get_post_meta( $id, '_na_illustrations', true );
		if ( ! isset( $gardees[ $rang ] ) ) {
			wp_die( esc_html__( 'Illustration introuvable.', 'notice-archeomed' ) );
		}
		$chemin  = $gardees[ $rang ];
		$dossier = $this->dossier_des_illustrations();
		// Le chemin vient de la base, mais on ne sert que depuis le dossier
		// prévu : une valeur trafiquée ne doit pas ouvrir le reste du disque.
		if ( '' === $dossier || 0 !== strpos( $chemin, $dossier ) || ! is_file( $chemin ) ) {
			wp_die( esc_html__( 'Illustration introuvable.', 'notice-archeomed' ) );
		}
		$type = wp_check_filetype( $chemin );
		nocache_headers();
		header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( basename( $chemin ) ) . '"' );
		header( 'Content-Length: ' . filesize( $chemin ) );
		readfile( $chemin );
		exit;
	}

	/**
	 * Efface les illustrations d'une notice qu'on supprime.
	 *
	 * Sans cela, le dossier grossirait indéfiniment de fichiers que plus rien
	 * ne désigne — et sur une campagne de plusieurs milliers de notices à
	 * vingt méga-octets, cela finirait par remplir le serveur.
	 */
	public function effacer_les_illustrations( $id ) {
		if ( Notice_Archeomed_File::CPT !== get_post_type( $id ) ) {
			return;
		}
		// Les aperçus incorporés aux documents vivent dans le même dossier et
		// n'ont pas d'autre usage : ils partent avec la notice.
		$this->effacer_des_apercus(
			$this->apercus_de( $id, get_post_meta( $id, '_na_donnees', true ) ) );
		$this->effacer_dans_le_depot( (array) get_post_meta( $id, '_na_illustrations', true ) );
	}

	/**
	 * Où les illustrations reçues se rangent pour de bon.
	 *
	 * Elles ne s'y trouvaient pas : envoyées par courriel, elles étaient
	 * effacées du serveur dans la foulée. Tout le reste d'une notice se
	 * retrouve — le texte est en réserve, le document se refabrique — mais
	 * l'image d'un auteur, non : c'est un TIFF de vingt méga-octets qu'on ne
	 * redemande pas six mois plus tard à quelqu'un qui a changé d'ordinateur.
	 *
	 * Le dossier est fermé par un « .htaccess » et les noms sont tirés au
	 * sort sur trente-deux caractères : sur un serveur qui n'honore pas les
	 * « .htaccess » — nginx —, c'est le nom imprévisible qui protège. Rien ne
	 * se sert par une adresse directe : le téléchargement passe par
	 * l'administration, et demande d'y être entré.
	 */
	private function dossier_des_illustrations() {
		$depot = wp_upload_dir();
		if ( ! empty( $depot['error'] ) ) {
			return '';
		}
		$dossier = trailingslashit( $depot['basedir'] ) . 'notice-archeomed';
		if ( ! is_dir( $dossier ) ) {
			wp_mkdir_p( $dossier );
		}
		if ( ! is_dir( $dossier ) || ! is_writable( $dossier ) ) {
			return '';
		}
		$garde = trailingslashit( $dossier ) . '.htaccess';
		if ( ! file_exists( $garde ) ) {
			@file_put_contents( $garde, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		}
		$index = trailingslashit( $dossier ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			@file_put_contents( $index, "<?php\n// Silence.\n" );
		}
		return trailingslashit( $dossier );
	}

	/** Le crochet du planificateur qui résout les termes d'une notice. */
	const HOOK_TERMES = 'na_resoudre_les_termes';

	/** Combien de passages on accorde à une notice dont un terme ne se résout pas. */
	const ESSAIS_TERMES = 6;

	/**
	 * L'intervalle entre deux passages après un échec.
	 *
	 * Il doit dépasser la durée pendant laquelle le thésaurus retient un
	 * échec : à une heure tout juste, la reprise arrivait quelques secondes
	 * avant que la réserve expire, relisait l'échec sans interroger Pactols,
	 * et un passage sur deux ne servait à rien. Les deux durées sont liées
	 * ici pour ne plus pouvoir diverger.
	 */
	const REPRISE_TERMES = Notice_Archeomed_Thesaurus::DUREE_ECHEC + 5 * MINUTE_IN_SECONDS;

	/** Combien de notices un passage examine au plus. */
	const LOT_TERMES = 50;

	/**
	 * Le temps qu'un passage s'accorde, en secondes, pour toutes les notices
	 * qu'il examine. Le planificateur enchaîne ses tâches dans une requête que
	 * l'hébergement coupe à trente : dix laissent la place aux courriels qui
	 * attendent derrière. C'est pourquoi il n'y a qu'une tâche pour toutes les
	 * notices et non une par notice — quarante tâches de dix secondes, c'était
	 * quatre cents secondes devant les envois.
	 */
	const BUDGET_TERMES = 10;

	/**
	 * Les quatre zones de la saisie qui portent des termes du thésaurus, avec
	 * le thésaurus dont chacune relève.
	 */
	private function zones_pactols() {
		return array(
			'nature_items'           => self::PACTOLS_SUBJECT_THESO_ID,
			'pactols_periods_items'  => self::PACTOLS_PERIOD_THESO_ID,
			'pactols_subjects_items' => self::PACTOLS_SUBJECT_THESO_ID,
			'pactols_places_items'   => self::PACTOLS_PLACE_THESO_ID,
		);
	}

	/**
	 * Les termes du thésaurus que porte une saisie, par ARK : l'identifiant
	 * numérique quand l'autocomplétion l'a donné, et le thésaurus dont le
	 * terme relève.
	 *
	 * Une seule extraction pour tous ceux qui en ont besoin — résoudre,
	 * compter ce qui manque, dater la lecture. Chacun refaisait la sienne, et
	 * une zone ajoutée à l'une mais pas à l'autre les aurait fait diverger.
	 */
	private function arks_de( $d ) {
		$out = array();
		foreach ( $this->zones_pactols() as $clef => $theso ) {
			foreach ( (array) ( isset( $d[ $clef ] ) ? $d[ $clef ] : array() ) as $item ) {
				$ark = isset( $item['ark'] ) ? trim( (string) $item['ark'] ) : '';
				if ( '' === $ark || isset( $out[ $ark ] ) ) {
					continue;
				}
				$out[ $ark ] = array(
					'id'    => isset( $item['idConcept'] ) ? (string) $item['idConcept'] : '',
					'theso' => $theso,
				);
			}
		}
		return $out;
	}

	/** Les ARK de la saisie que la réserve ne connaît pas encore. */
	private function termes_manquants( $d, $termes, $arks = null ) {
		if ( null === $arks ) {
			$arks = $this->arks_de( $d );
		}
		return array_keys( array_diff_key( $arks, (array) $termes ) );
	}

	/**
	 * Demande à Pactols ce qu'il sait des termes d'une notice, et le garde.
	 *
	 * On repart de ce qui est déjà su : un terme résolu ne se redemande pas, sa
	 * date de lecture dit de quel millésime du thésaurus il vient, et la
	 * redemander la ferait glisser sans qu'on l'ait voulu. Ce qui ne relève
	 * plus de la saisie s'écarte.
	 *
	 * L'échéance est une heure d'horloge, non une durée : passé elle, on
	 * s'arrête entre deux termes et « $interrompu » le dit. La réserve ne
	 * s'écrit que si elle a changé — un terme que Pactols refuse obstinément
	 * ne doit pas coûter une écriture à chaque passage.
	 */
	private function resoudre_les_termes( $id, $d, $echeance, &$interrompu = false ) {
		$interrompu = false;
		$deja       = $this->termes_connus( $id );
		$voulus     = $this->arks_de( $d );
		$termes     = array_intersect_key( $deja, $voulus );
		foreach ( $voulus as $ark => $quoi ) {
			if ( isset( $termes[ $ark ] ) ) {
				continue;
			}
			// La même borne que le thésaurus, qui renonce à appeler quand il
			// reste moins d'une seconde : sans elle, les termes sautés dans
			// cette dernière seconde passaient pour des échecs, et la notice
			// attendait une heure au lieu d'une minute.
			if ( microtime( true ) > $echeance - 1 ) {
				break;
			}
			$concept = Notice_Archeomed_Thesaurus::resoudre( $ark, $quoi['id'],
				$quoi['theso'], $echeance );
			if ( is_array( $concept ) ) {
				$termes[ $ark ] = $concept;
			}
		}
		$interrompu = microtime( true ) > $echeance - 1
			&& count( array_diff_key( $voulus, $termes ) ) > 0;
		if ( $termes != $deja ) {
			if ( empty( $termes ) ) {
				delete_post_meta( (int) $id, '_na_pactols' );
			} else {
				update_post_meta( (int) $id, '_na_pactols', $termes );
			}
		}
		return $termes;
	}

	/**
	 * Met une notice en attente de résolution, si elle n'y est pas déjà.
	 *
	 * L'attente vit dans la notice elle-même — l'heure à partir de laquelle
	 * on peut l'examiner —, non dans une liste commune qu'un dépôt et un
	 * téléchargement simultanés pourraient réécrire l'un par-dessus l'autre.
	 * Une notice qui a épuisé ses essais n'y retourne pas : seul un
	 * téléchargement du dossier, geste explicite, la relance.
	 */
	private function mettre_en_attente( $id, $d = null ) {
		$id = (int) $id;
		if ( ! $id ) {
			return;
		}
		if ( null === $d ) {
			$d = get_post_meta( $id, '_na_donnees', true );
		}
		$d      = is_array( $d ) ? $d : array();
		$termes = $this->termes_connus( $id );
		// Rien à chercher, ou plus d'essai à faire : ni attente, ni tâche.
		// Chaque notice envoyée passait par ici, avec ou sans terme du
		// thésaurus, et réservait un passage qui ne faisait que la retirer.
		if ( empty( $this->termes_manquants( $d, $termes ) )
			|| (int) get_post_meta( $id, '_na_pactols_essais', true ) >= self::ESSAIS_TERMES ) {
			return;
		}
		$apres = (int) get_post_meta( $id, '_na_pactols_apres', true );
		if ( ! $apres ) {
			$apres = $this->premier_examen_utile( $d, $termes );
			update_post_meta( $id, '_na_pactols_apres', $apres );
		}
		self::assurer_la_tache( $apres );
	}

	/** Les termes d'une notice que la réserve connaît déjà. */
	private function termes_connus( $id ) {
		$termes = get_post_meta( (int) $id, '_na_pactols', true );
		return is_array( $termes ) ? $termes : array();
	}

	/**
	 * La première heure à laquelle examiner une notice sert à quelque chose.
	 *
	 * Dans la minute, sauf si l'un de ses termes vient d'échouer — pour elle
	 * ou pour une autre notice, la réserve du thésaurus étant commune. Tant
	 * que cet échec n'est pas oublié, l'examiner ne ferait que le relire, et
	 * gâcherait un essai en promettant à la rédaction une relance qui n'en
	 * est pas une.
	 */
	private function premier_examen_utile( $d, $termes, $arks = null ) {
		$oubli = $this->oubli_des_echecs( $d, $termes, $arks );
		return max( time() + MINUTE_IN_SECONDS, $oubli > 0 ? $oubli + MINUTE_IN_SECONDS : 0 );
	}

	/**
	 * L'heure de la reprise après un échec : une minute après que le
	 * thésaurus aura oublié le dernier échec qui concerne la notice, ou le
	 * délai de reprise ordinaire s'il n'en retient aucun — un terme
	 * interrompu, que rien n'a mis en réserve.
	 *
	 * Qu'elle ait interrogé Pactols elle-même ou relu l'échec d'une autre, la
	 * notice compte un essai : un essai mesure une heure où le terme a
	 * échoué, qui qu'ait posé la question. Sans cela, N notices partageant un
	 * terme cassé ne comptaient qu'un essai par heure à elles toutes, et
	 * mettaient six fois N heures à y renoncer.
	 */
	private function reprise_apres_echec( $d, $termes, $arks = null ) {
		$oubli = $this->oubli_des_echecs( $d, $termes, $arks );
		return $oubli > 0 ? $oubli + MINUTE_IN_SECONDS : time() + self::REPRISE_TERMES;
	}

	/**
	 * L'heure où le thésaurus oubliera le dernier des échecs retenus pour les
	 * termes qui manquent à la notice, ou zéro. Seuls ces termes comptent :
	 * un terme déjà connu n'a pas à retarder sa notice parce qu'il a échoué
	 * ailleurs.
	 */
	private function oubli_des_echecs( $d, $termes, $arks = null ) {
		if ( null === $arks ) {
			$arks = $this->arks_de( $d );
		}
		$oubli = 0;
		foreach ( $this->termes_manquants( $d, $termes, $arks ) as $ark ) {
			$oubli = max( $oubli, Notice_Archeomed_Thesaurus::echec_jusqua( $ark,
				$arks[ $ark ]['id'], $arks[ $ark ]['theso'] ) );
		}
		return $oubli;
	}

	/**
	 * Remet une notice en attente, ses essais à zéro, et rend l'heure du
	 * prochain examen.
	 *
	 * Sans avancer une reprise déjà fixée : si la notice attend parce que
	 * Pactols vient d'échouer, l'examiner dans la minute relirait l'échec en
	 * réserve et gâcherait un essai.
	 */
	private function relancer_la_resolution( $id, $d ) {
		$id    = (int) $id;
		$fixee = (int) get_post_meta( $id, '_na_pactols_apres', true );
		$apres = max( $fixee, $this->premier_examen_utile( $d, $this->termes_connus( $id ) ) );
		delete_post_meta( $id, '_na_pactols_essais' );
		update_post_meta( $id, '_na_pactols_apres', $apres );
		// La tâche se réserve une fois, après la boucle qui relance toutes
		// les notices d'un dossier : la réserver ici réécrivait la liste des
		// tâches de WordPress à chaque notice.
		return $apres;
	}

	/** Une notice sort de l'attente : résolue, ou sans saisie à résoudre. */
	private function sortir_de_l_attente( $id ) {
		delete_post_meta( (int) $id, '_na_pactols_apres' );
		delete_post_meta( (int) $id, '_na_pactols_essais' );
	}

	/**
	 * La tâche unique passera au plus tard à l'heure dite.
	 *
	 * Elle ne vérifiait que son existence. Après un passage où toutes les
	 * notices avaient échoué, elle était réservée une heure plus tard ; une
	 * notice déposée entre-temps, à examiner dans la minute, attendait cette
	 * heure-là. Une tâche prévue trop tard est donc avancée.
	 */
	private static function assurer_la_tache( $quand = 0 ) {
		$quand  = max( (int) $quand, time() + MINUTE_IN_SECONDS );
		$prevue = wp_next_scheduled( self::HOOK_TERMES );
		if ( $prevue && $prevue <= $quand ) {
			return;
		}
		if ( $prevue ) {
			wp_clear_scheduled_hook( self::HOOK_TERMES );
		}
		wp_schedule_single_event( $quand, self::HOOK_TERMES );
	}

	/**
	 * Remet la tâche en route si des notices attendent sans elle — après une
	 * réactivation, qui l'a effacée, ou si WordPress l'a perdue. Sans cela,
	 * rien ne la recréait avant le prochain dépôt ou le prochain fascicule.
	 * La vérification ne coûte qu'une lecture de la liste des tâches ; la
	 * recherche des notices n'a lieu que si la tâche manque.
	 */
	public function reveiller_la_file() {
		if ( wp_next_scheduled( self::HOOK_TERMES ) ) {
			return;
		}
		$premiere = $this->notices_en_attente( 0, 1 );
		if ( ! empty( $premiere ) ) {
			self::assurer_la_tache( (int) get_post_meta( $premiere[0], '_na_pactols_apres', true ) );
		}
	}

	/**
	 * Les notices dont l'heure d'examen est passée, la plus ancienne d'abord,
	 * ou — sans heure — la première de toutes celles qui attendent.
	 */
	private function notices_en_attente( $jusqua, $combien ) {
		$requete = array(
			'post_type'        => Notice_Archeomed_File::CPT,
			'post_status'      => 'any',
			'fields'           => 'ids',
			'posts_per_page'   => (int) $combien,
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_key'         => '_na_pactols_apres',
			'meta_type'        => 'NUMERIC',
			'orderby'          => 'meta_value_num',
			'order'            => 'ASC',
		);
		if ( $jusqua > 0 ) {
			$requete['meta_value']   = (int) $jusqua;
			$requete['meta_compare'] = '<=';
		}
		return array_map( 'intval', (array) get_posts( $requete ) );
	}

	/**
	 * Ce que le planificateur appelle : les termes des notices en attente.
	 *
	 * Une seule tâche pour toutes les notices, avec un seul budget. Elle
	 * examine les notices dues, la plus ancienne d'abord, et s'arrête quand
	 * le temps manque ; celles qu'elle n'a pas vues restent dues et passent
	 * en tête au passage suivant, dans la minute.
	 *
	 * Un filet s'inscrit avant tout travail : WordPress a déjà retiré la
	 * tâche de sa liste au moment de la lancer, et une requête coupée en
	 * route ne la remettrait pas. L'attente, elle, est dans les notices, et
	 * ne se perd pas.
	 *
	 * Reçoit un identifiant quand c'est une tâche d'une seule notice laissée
	 * par la 3.36 : on la verse dans l'attente commune, et l'on continue.
	 */
	public function resoudre_en_tache( $ancien = 0 ) {
		// Une tâche d'une seule notice, laissée par la 3.36 : la notice rejoint
		// l'attente, et c'est tout. Quarante de ces tâches dues ensemble
		// lançaient sinon quarante passages de dix secondes dans la même
		// requête — ce que la tâche unique était venue empêcher.
		if ( $ancien ) {
			$this->mettre_en_attente( (int) $ancien );
			// Sa notice peut n'avoir plus rien à chercher : la file, elle, a
			// peut-être d'autres notices qui attendaient ce passage.
			$this->reveiller_la_file();
			return;
		}
		$echeance = microtime( true ) + self::BUDGET_TERMES;
		// Le filet : une tâche dans cinq minutes au plus tard. « Au plus
		// tard » et non « s'il n'y en a pas » : une tâche réservée loin par
		// un téléchargement simultané aurait sinon tenu lieu de filet, et une
		// requête coupée laissait les notices dues attendre une heure.
		self::assurer_la_tache( time() + 5 * MINUTE_IN_SECONDS );

		$interrompu = false;
		foreach ( $this->notices_en_attente( time(), self::LOT_TERMES ) as $id ) {
			if ( microtime( true ) > $echeance - 1 ) {
				$interrompu = true;
				break;
			}
			$d = get_post_meta( $id, '_na_donnees', true );
			if ( ! is_array( $d ) || empty( $d ) ) {
				$this->sortir_de_l_attente( $id );
				continue;
			}
			$arks   = $this->arks_de( $d );
			$avant  = $this->termes_connus( $id );
			// Tout le budget, mesuré au temps qui reste et non au rang de la
			// notice dans le passage : la première peut avoir perdu du temps
			// à la requête, la deuxième peut commencer avec neuf secondes.
			$entier = ( $echeance - microtime( true ) ) >= self::BUDGET_TERMES - 1;
			$coupe  = false;
			$termes = $this->resoudre_les_termes( $id, $d, $echeance, $coupe );
			$issue  = $this->issue_du_passage(
				count( $this->termes_manquants( $d, $avant, $arks ) ),
				count( $this->termes_manquants( $d, $termes, $arks ) ),
				$coupe, $entier );
			if ( 'resolue' === $issue ) {
				$this->sortir_de_l_attente( $id );
				continue;
			}
			if ( 'reprendre' === $issue ) {
				$interrompu = true;
				break;
			}
			// Un échec, ou une interruption qui n'a rien appris alors que la
			// notice avait tout le budget : elle passe en fin de file. Elle ne
			// peut plus occuper la tête à chaque minute.
			if ( $coupe ) {
				$interrompu = true;
			}
			$essais = (int) get_post_meta( $id, '_na_pactols_essais', true ) + 1;
			update_post_meta( $id, '_na_pactols_essais', $essais );
			if ( $essais >= self::ESSAIS_TERMES ) {
				delete_post_meta( $id, '_na_pactols_apres' );
			} else {
				update_post_meta( $id, '_na_pactols_apres',
					$this->reprise_apres_echec( $d, $termes, $arks ) );
			}
			if ( $interrompu ) {
				break;
			}
		}

		// Le prochain passage : dans la minute si l'on a été interrompu, sinon
		// à l'heure de la notice qui attend le moins longtemps.
		wp_clear_scheduled_hook( self::HOOK_TERMES );
		$prochain = 0;
		if ( $interrompu ) {
			$prochain = time() + MINUTE_IN_SECONDS;
		} else {
			$premiere = $this->notices_en_attente( 0, 1 );
			if ( ! empty( $premiere ) ) {
				$prochain = (int) get_post_meta( $premiere[0], '_na_pactols_apres', true );
			}
		}
		if ( $prochain > 0 ) {
			wp_schedule_single_event( max( $prochain, time() + MINUTE_IN_SECONDS ),
				self::HOOK_TERMES );
		}
	}

	/**
	 * Ce qu'un passage a fait d'une notice : « resolue », « reprendre » ou
	 * « echec ».
	 *
	 * Une interruption ne comptait jamais comme un essai, et la notice restait
	 * due. Un terme trop lent pour tenir dans le budget était donc repris à
	 * chaque minute, sans fin : il ne s'épuisait jamais, et comme la file
	 * sert d'abord la notice la plus ancienne, il occupait tout le budget de
	 * chaque passage et bloquait celles qui attendaient derrière lui.
	 *
	 * Une interruption ne se reprend dans la minute que si le passage a
	 * appris quelque chose — un terme de moins à trouver —, ou si la notice
	 * n'a pas eu tout le budget : commencée avec une ou deux secondes
	 * restantes, elle n'a pas eu sa chance, et la condamner lui prenait un
	 * essai à chaque passage où elle venait en dernier. Elle sera la première
	 * du passage suivant. Hors de ces cas, c'est un échec.
	 */
	private function issue_du_passage( $manquaient, $manquent, $coupe, $budget_entier = true ) {
		if ( 0 === (int) $manquent ) {
			return 'resolue';
		}
		if ( $coupe && ( (int) $manquent < (int) $manquaient || ! $budget_entier ) ) {
			return 'reprendre';
		}
		return 'echec';
	}

	/**
	 * À la désactivation, la tâche s'efface — les siennes comme celles, une
	 * par notice, qu'a laissées la 3.36. L'attente reste dans les notices, et
	 * la première page qui en a besoin la relancera.
	 */
	public static function desactiver() {
		wp_unschedule_hook( self::HOOK_TERMES );
	}

	/**
	 * À l'activation, un passage dans la minute : il examine ce qui attendait
	 * pendant la désactivation, ou ne trouve rien et ne se réinscrit pas.
	 */
	public static function activer() {
		self::assurer_la_tache();
	}

	/**
	 * Pose sur chaque terme la forme préférée du thésaurus, et la date à
	 * laquelle elle a été lue.
	 *
	 * L'autocomplétion rend l'étiquette qui a répondu à la frappe, qui est
	 * souvent une variante — « Eglise copte » pour « église copte ». Le volume
	 * imprime la forme préférée, et la saisie de l'auteur reste intacte
	 * dessous : c'est elle qui vaut si le thésaurus n'a pas répondu.
	 */
	private function poser_les_formes_preferees( $id, $d ) {
		$termes = $this->termes_connus( $id );
		// Un terme qui manque se demande au planificateur, jamais ici : cette
		// fonction sert des pages qu'une personne attend. Les notices d'avant
		// la résolution des termes se rattrapent ainsi à leur premier
		// passage dans un fascicule, sans rien coûter à celui qui le demande.
		$manquants = count( $this->termes_manquants( $d, $termes ) );
		if ( $manquants > 0 ) {
			$this->mettre_en_attente( $id, $d );
			$d['pactols_manquants'] = $manquants;
		}
		if ( empty( $termes ) ) {
			return $d;
		}
		foreach ( array_keys( $this->zones_pactols() ) as $clef ) {
			if ( empty( $d[ $clef ] ) ) {
				continue;
			}
			foreach ( (array) $d[ $clef ] as $i => $item ) {
				$ark = isset( $item['ark'] ) ? trim( (string) $item['ark'] ) : '';
				if ( '' === $ark || empty( $termes[ $ark ] ) ) {
					continue;
				}
				if ( ! empty( $termes[ $ark ]['prefLabel'] ) ) {
					$d[ $clef ][ $i ]['prefLabel'] = $termes[ $ark ]['prefLabel'];
				}
				$d[ $clef ][ $i ]['deprecie'] = ! empty( $termes[ $ark ]['deprecie'] );
			}
		}
		list( $du, $au ) = $this->millesime( $d, $termes );
		if ( '' !== $du ) {
			$d['pactols_lu_du'] = $du;
			$d['pactols_lu_au'] = $au;
		}
		return $d;
	}

	/**
	 * La plus ancienne et la plus récente des dates auxquelles les termes de
	 * cette saisie ont été lus, ou deux chaînes vides.
	 *
	 * On n'en donnait qu'une, celle du premier terme de la réserve. Depuis que
	 * les termes manqués se reprennent plus tard, ils ne sont plus tous lus le
	 * même jour, et une date unique mentait pour ceux qui étaient venus
	 * après — alors qu'elle n'est là que pour savoir ce qu'on avait sous les
	 * yeux. Seuls comptent les termes de la saisie.
	 */
	private function millesime( $d, $termes ) {
		$dates = array();
		foreach ( array_keys( $this->arks_de( $d ) ) as $ark ) {
			if ( ! empty( $termes[ $ark ]['lu_le'] ) ) {
				$dates[] = (string) $termes[ $ark ]['lu_le'];
			}
		}
		if ( empty( $dates ) ) {
			return array( '', '' );
		}
		sort( $dates );
		return array( $dates[0], $dates[ count( $dates ) - 1 ] );
	}

	/**
	 * Les aperçus d'une notice, par rang de figure.
	 *
	 * Ils ont leur méta à eux. Les notices déposées de la 3.28 à la 3.35 les
	 * portent encore dans leur saisie, sous la clef « figure » de chaque
	 * illustration : on les y lit quand la méta manque.
	 */
	private function apercus_de( $id, $d = null ) {
		$apercus = get_post_meta( (int) $id, '_na_apercus', true );
		if ( is_array( $apercus ) && ! empty( $apercus ) ) {
			return $apercus;
		}
		$apercus = array();
		if ( is_array( $d ) && ! empty( $d['illustrations'] ) ) {
			foreach ( (array) $d['illustrations'] as $item ) {
				if ( ! empty( $item['figure']['apercu'] ) && isset( $item['rang'] ) ) {
					$apercus[ (int) $item['rang'] ] = $item['figure'];
				}
			}
		}
		return $apercus;
	}

	/**
	 * La saisie d'une notice, prête pour un document.
	 *
	 * Tous ceux qui fabriquent un document passent par ici : la notice seule,
	 * le fascicule, le dossier. La saisie en sort sans figure — celles que
	 * les notices anciennes y portent encore s'ôtent —, avec les formes
	 * préférées du thésaurus, et avec ses aperçus quand le document part seul
	 * et doit porter ses images. Le dossier les refuse : il appelle les
	 * siennes dans « icono/br ».
	 *
	 * Rend null si la notice n'a pas de saisie exploitable.
	 */
	private function saisie_de( $id, $avec_apercus ) {
		$d = get_post_meta( (int) $id, '_na_donnees', true );
		if ( ! is_array( $d ) || empty( $d ) ) {
			return null;
		}
		$apercus = $avec_apercus ? $this->apercus_de( $id, $d ) : array();
		if ( ! empty( $d['illustrations'] ) ) {
			foreach ( array_keys( (array) $d['illustrations'] ) as $i ) {
				unset( $d['illustrations'][ $i ]['figure'] );
			}
		}
		$d = $this->poser_les_formes_preferees( $id, $d );
		return $this->attacher_les_apercus( $d, $apercus );
	}

	/** Efface des aperçus, pourvu qu'ils soient dans le dossier de dépôt. */
	private function effacer_des_apercus( $apercus ) {
		$chemins = array();
		foreach ( (array) $apercus as $mesure ) {
			if ( ! empty( $mesure['apercu'] ) ) {
				$chemins[] = $mesure['apercu'];
			}
		}
		$this->effacer_dans_le_depot( $chemins );
	}

	/**
	 * Efface des fichiers, mais seulement ceux qui sont dans le dossier de
	 * dépôt. La garde n'existe qu'ici : un chemin venu d'une méta ne doit
	 * jamais faire effacer un fichier ailleurs sur le serveur.
	 */
	private function effacer_dans_le_depot( $chemins ) {
		$dossier = $this->dossier_des_illustrations();
		if ( '' === $dossier ) {
			return;
		}
		foreach ( (array) $chemins as $chemin ) {
			if ( is_string( $chemin ) && 0 === strpos( $chemin, $dossier ) && is_file( $chemin ) ) {
				@unlink( $chemin );
			}
		}
	}

	/**
	 * Fabrique un aperçu par illustration, et rend leur mesure par rang.
	 *
	 * Le rang de la légende désigne le fichier de même rang : c'est la
	 * convention de tout le plugin, et c'est l'ordre du dépôt qui la tient.
	 *
	 * Sans Imagick, il n'y a pas d'aperçu et le document sort comme avant,
	 * avec ses seules légendes. Une figure manquante ne fait pas perdre une
	 * notice.
	 */
	private function fabriquer_les_apercus( $illustrations, $d ) {
		$dossier = $this->dossier_des_illustrations();
		if ( '' === $dossier || ! class_exists( 'Imagick' )
			|| empty( $d['illustrations'] ) ) {
			return array();
		}
		$fichiers = array_values( (array) $illustrations );
		$paquet   = new Notice_Archeomed_Paquet();
		$apercus  = array();
		foreach ( (array) $d['illustrations'] as $item ) {
			$rang = isset( $item['rang'] ) ? (int) $item['rang'] : 0;
			if ( $rang < 1 || ! isset( $fichiers[ $rang - 1 ] )
				|| ! file_exists( $fichiers[ $rang - 1 ] ) ) {
				continue;
			}
			$cible  = $dossier . 'apercu-' . wp_generate_password( 10, false, false ) . '.jpg';
			$mesure = $paquet->apercu_du_document( $fichiers[ $rang - 1 ], $cible );
			if ( is_array( $mesure ) ) {
				$apercus[ $rang ] = $mesure;
			}
		}
		return $apercus;
	}

	/**
	 * Pose les aperçus sur les légendes de la saisie, pour que le document
	 * les trouve. Le dossier Métopes écrase ensuite cette clef par la sienne :
	 * là où l'icono est à côté du texte, c'est le lien qui vaut.
	 */
	private function attacher_les_apercus( $d, $apercus ) {
		if ( empty( $apercus ) || empty( $d['illustrations'] ) ) {
			return $d;
		}
		foreach ( $d['illustrations'] as $i => $item ) {
			$rang = isset( $item['rang'] ) ? (int) $item['rang'] : 0;
			if ( isset( $apercus[ $rang ] ) ) {
				$d['illustrations'][ $i ]['figure'] = $apercus[ $rang ];
			}
		}
		return $d;
	}

	/**
	 * Range les illustrations à demeure, sans attendre que le courriel parte.
	 *
	 * Elles l'attendaient : l'archivage suivait l'envoi réussi. Un serveur de
	 * courriel indisponible emportait donc tout — la notice échouait, et ses
	 * illustrations restaient au répertoire temporaire, invisibles du dossier
	 * Métopes et promises à la purge. On croyait la conversion en cause quand
	 * c'était la poste.
	 *
	 * Ce que l'auteur a envoyé se garde parce qu'il l'a envoyé, non parce
	 * qu'un courriel a abouti. Les deux n'ont rien à voir, et les lier faisait
	 * dépendre le plus précieux — un fichier qu'on ne redemande pas — du plus
	 * fragile.
	 *
	 * Rend les chemins définitifs, ceux-là mêmes que le courriel joindra.
	 */
	private function archiver_les_illustrations( $id, $illustrations ) {
		$dossier = $this->dossier_des_illustrations();
		if ( '' === $dossier ) {
			// Pas de dossier de dépôt : on laisse au répertoire temporaire,
			// où la purge les garde un mois sous leur nom « garde ».
			return array_values( array_filter( (array) $illustrations, 'file_exists' ) );
		}
		$gardees = array();
		foreach ( (array) $illustrations as $fichier ) {
			if ( ! file_exists( $fichier ) ) {
				continue;
			}
			$cible = $dossier . (int) $id . '-' . basename( $fichier );
			$gardees[] = @rename( $fichier, $cible ) ? $cible : $fichier;
		}
		if ( $id ) {
			update_post_meta( $id, '_na_illustrations', $gardees );
		}
		return $gardees;
	}

	private function nettoyer( $fichiers ) {
		foreach ( (array) $fichiers as $file ) {
			if ( file_exists( $file ) && false !== strpos( $file, 'notice-archeomed-' ) ) {
				@unlink( $file );
			}
		}
	}

	private function redirect_result( $ok, $raison = '', $champ = '', $reference = '' ) {
		// Tout refus emporte la saisie avec lui : c'est vrai d'une vérification
		// manquée comme d'une page expirée, et dans les deux cas la personne
		// devant l'écran n'y est pour rien.
		$jeton   = $ok ? '' : $this->garder_la_saisie();
		$referer = $this->url_du_formulaire();
		$redirect = add_query_arg( 'notice_envoyee', $ok ? '1' : '0', $referer );
		if ( ! $ok && '' !== $raison ) {
			$redirect = add_query_arg( 'notice_erreur', rawurlencode( $raison ), $redirect );
		}
		if ( ! $ok && '' !== $champ ) {
			$redirect = add_query_arg( 'notice_champ', rawurlencode( $champ ), $redirect );
		}
		if ( ! $ok && '' !== $reference ) {
			$redirect = add_query_arg( 'notice_ref', rawurlencode( $reference ), $redirect );
		}
		if ( '' !== $jeton ) {
			$redirect = add_query_arg( 'notice_reprise', $jeton, $redirect );
		}
		wp_safe_redirect( $redirect );
		exit;
	}
	private function get_client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '0.0.0.0';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}
	private function verify_turnstile() {
		$secret = $this->turnstile_secret();
		if ( '' === trim( $secret ) || ! $this->turnstile_site_key_is_set() ) {
			return false;
		}
		$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
		if ( '' === $token ) {
			return false;
		}
		$response = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 10,
				'body' => array(
					'secret' => $secret,
					'response' => $token,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			error_log( 'Notice Archeomed: Turnstile request failed, degraded mode: ' . $response->get_error_message() );
			return self::TURNSTILE_FAIL_OPEN_ON_NETWORK_ERROR;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) && ! empty( $body['success'] );
	}
	/**
	 * Combien d'interrogations du thésaurus une même connexion peut lancer.
	 * Assez pour remplir un formulaire entier sans jamais s'en apercevoir,
	 * trop peu pour servir de relais à qui voudrait marteler Pactols.
	 */
	private function check_lookup_limit() {
		$key   = 'na_pactols_ip_' . md5( $this->get_client_ip() );
		$count = (int) get_transient( $key );
		if ( $count >= self::MAX_LOOKUPS_PER_HOUR ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Le garde-fou contre le martèlement : il compte toutes les soumissions,
	 * abouties ou non, et son plafond est large.
	 *
	 * Un seul compteur servait aux deux usages, et il s'incrémentait avant
	 * toute validation : cinq maladresses — un champ oublié, un jeton
	 * Turnstile périmé pendant qu'on rédigeait — et le chercheur était bloqué
	 * une heure, sans savoir pourquoi. C'est l'incident le plus probable en
	 * usage réel, sur un formulaire qu'on remplit trois fois par an.
	 */
	private function check_attempt_limit() {
		$key   = 'na_essais_ip_' . md5( $this->get_client_ip() );
		$count = (int) get_transient( $key );
		if ( $count >= self::MAX_ATTEMPTS_PER_HOUR ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Le quota d'envois, lui, ne compte que ce qui est parti. On le lit avant
	 * d'envoyer ; on ne l'incrémente qu'après.
	 */
	private function check_send_limit( $email ) {
		foreach ( $this->send_limit_keys( $email ) as $key => $plafond ) {
			if ( (int) get_transient( $key ) >= $plafond ) {
				return false;
			}
		}
		return true;
	}

	private function record_send( $email ) {
		foreach ( array_keys( $this->send_limit_keys( $email ) ) as $key ) {
			set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Les compteurs d'envois, chacun avec son plafond.
	 *
	 * Celui de l'adresse électronique est le seul qui désigne vraiment une
	 * personne ; celui de l'IP se contente d'arrêter une machine emballée.
	 */
	private function send_limit_keys( $email ) {
		$keys = array( 'na_envois_ip_' . md5( $this->get_client_ip() ) => self::MAX_SENDS_PER_IP );
		if ( is_email( $email ) ) {
			$keys[ 'na_envois_mail_' . md5( strtolower( $email ) ) ] = self::MAX_SENDS_PER_EMAIL;
		}
		return $keys;
	}
	private function handle_uploads( &$upload_error = '' ) {
		$attachments = array();
		$upload_error = '';
		if ( empty( $_FILES['illustrations'] ) || empty( $_FILES['illustrations']['name'][0] ) ) {
			return $attachments;
		}
		$tmp_dir = trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp';
		if ( ! file_exists( $tmp_dir ) ) {
			wp_mkdir_p( $tmp_dir );
		}
		if ( ! is_dir( $tmp_dir ) || ! is_writable( $tmp_dir ) ) {
			$upload_error = 'upload_tmp_not_writable';
			return $attachments;
		}
		$this->purge_old_tmp_files( $tmp_dir );
		$files = $_FILES['illustrations'];
		// Le champ se nomme « illustrations[] » : PHP en fait des tableaux.
		// Posté sans les crochets, « name » est une chaîne, et « count() » sur
		// une chaîne tue la requête en PHP 8 — une page blanche au lieu d'un
		// refus propre, et sous charge on ne saurait même pas d'où elle vient.
		if ( ! is_array( $files['name'] ) ) {
			$upload_error = 'upload_form';
			return $attachments;
		}
		$count = count( $files['name'] );
		if ( $count > self::MAX_FILES ) {
			$upload_error = 'upload_too_many';
			return $attachments;
		}
		$total_size = 0;
		for ( $i = 0; $i < $count; $i++ ) {
			if ( UPLOAD_ERR_NO_FILE === $files['error'][ $i ] ) {
				continue;
			}
			if ( UPLOAD_ERR_OK !== $files['error'][ $i ] ) {
				$upload_error = 'upload_error';
				return $attachments;
			}
			$total_size += (int) $files['size'][ $i ];
		}
		if ( $total_size > self::MAX_TOTAL_FILESIZE ) {
			$upload_error = 'upload_total_size';
			return $attachments;
		}
		for ( $i = 0; $i < $count; $i++ ) {
			if ( UPLOAD_ERR_NO_FILE === $files['error'][ $i ] || UPLOAD_ERR_OK !== $files['error'][ $i ] ) {
				continue;
			}
			$name = sanitize_file_name( $files['name'][ $i ] );
			$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, self::ALLOWED_EXT, true ) ) {
				$upload_error = 'upload_ext';
				return $attachments;
			}
			if ( ! $this->mime_is_allowed( $files['tmp_name'][ $i ], $name, $ext ) ) {
				$upload_error = 'upload_mime';
				return $attachments;
			}
			$dest = trailingslashit( $tmp_dir ) . 'notice-archeomed-' . wp_generate_password( 32, false, false ) . '.' . $ext;
			if ( move_uploaded_file( $files['tmp_name'][ $i ], $dest ) ) {
				$attachments[] = $dest;
			} else {
				$upload_error = 'upload_move';
				return $attachments;
			}
		}
		return $attachments;
	}
	private function purge_old_tmp_files( $tmp_dir ) {
		foreach ( glob( trailingslashit( $tmp_dir ) . 'notice-archeomed-*' ) as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}
			// Ce qu'un envoi manqué a mis de côté vit un mois : c'est le temps
			// qu'il faut à un auteur pour s'étonner, écrire, et qu'on lui
			// réponde. Le reste ne sert plus dès le lendemain.
			$age = false !== strpos( $file, 'notice-archeomed-garde-' )
				? MONTH_IN_SECONDS : DAY_IN_SECONDS;
			if ( filemtime( $file ) < time() - $age ) {
				@unlink( $file );
			}
		}
	}
	private function pdf_is_valid( $tmp_path ) {
		$handle = @fopen( $tmp_path, 'rb' );
		if ( ! $handle ) {
			return false;
		}
		$header = fread( $handle, 5 );
		fclose( $handle );
		return '%PDF-' === $header;
	}
	private function mime_is_allowed( $tmp_path, $name, $ext ) {
		if ( ! is_uploaded_file( $tmp_path ) ) {
			return false;
		}
		$expected = array(
			'jpg' => array( 'image/jpeg' ),
			'jpeg' => array( 'image/jpeg' ),
			'tif' => array( 'image/tiff' ),
			'tiff' => array( 'image/tiff' ),
			'pdf' => array( 'application/pdf', 'application/x-pdf' ),
		);
		if ( ! isset( $expected[ $ext ] ) ) {
			return false;
		}
		$detected = '';
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			if ( $finfo ) {
				$detected = (string) finfo_file( $finfo, $tmp_path );
				finfo_close( $finfo );
			}
		}
		if ( '' === $detected ) {
			$check = wp_check_filetype_and_ext( $tmp_path, $name );
			if ( empty( $check['ext'] ) || strtolower( $check['ext'] ) !== $ext ) {
				return false;
			}
			return 'pdf' === $ext ? $this->pdf_is_valid( $tmp_path ) : true;
		}
		if ( ! in_array( $detected, $expected[ $ext ], true ) ) {
			return false;
		}
		return 'pdf' === $ext ? $this->pdf_is_valid( $tmp_path ) : true;
	}
}
// L'instance est nommée : la file d'attente a besoin du plugin pour calculer
// le classement d'une notice, et la file naît avant lui.
// « global », comme pour la mise à jour plus haut. WordPress charge d'ordinaire
// les extensions dans la portée globale, et la variable l'est d'office ; mais
// WP-CLI le charge dans une fonction, et elle devenait locale : la file, qui
// retrouve le plugin par cette variable, ne le trouvait plus et les notices
// perdaient leur classement. C'est ce qui arriverait le jour où l'hébergement
// remplace le planificateur de WordPress par « wp cron » — et c'est le site
// d'essai de la publication, monté par WP-CLI, qui l'a montré.
global $notice_archeomed_plugin;
$notice_archeomed_plugin = new Notice_Archeomed_Pactols();
