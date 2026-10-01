<?php
/**
 * Plugin Name: Formulaire des notices d’archéologie médiévale
 * Description: Formulaire de soumission de notice d'opération archéologique pour la Chronique d'Archéologie médiévale. Le courriel adressé à la rédaction est accompagné d'un fichier DOCX stylé Métopes. Shortcode : [notice_archeomed_pactols]
 * Version: 3.49
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
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-typographie.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-controles.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-normes.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-styles.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-docx.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-file.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-thesaurus.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-accessibilite.php';

new Notice_Archeomed_Settings();
new Notice_Archeomed_Accessibilite();

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
			echo '<div class="notice notice-error"><p><strong>Chronique&nbsp;:</strong> '
				. esc_html__( 'le gabarit modele-metopes.docx est introuvable. Il porte la feuille de styles Métopes de référence : les notices partiront sans document mis en forme.', 'notice-archeomed' )
				. '</p></div>';
		}
		if ( ! file_exists( plugin_dir_path( __FILE__ ) . 'modele-metopes.rtf' ) && ! class_exists( 'ZipArchive' ) ) {
			echo '<div class="notice notice-warning"><p><strong>Chronique&nbsp;:</strong> '
				. esc_html__( 'cet hébergement n’a pas l’extension ZipArchive, le document doit donc se fabriquer en RTF — et le gabarit modele-metopes.rtf est introuvable.', 'notice-archeomed' )
				. '</p></div>';
		}
		// L'adresse de destination manquante se dit ailleurs, en tête de
		// toutes les pages et avec ce qu'elle retient : voir
		// Notice_Archeomed_File::signaler_l_adresse_manquante().
		//
		// Sans clé secrète, Turnstile rejette toutes les soumissions. On ne le
		// signale que si Turnstile est effectivement la protection retenue :
		// un bandeau rouge permanent sur une installation qui se protège
		// autrement finirait par ne plus être lu du tout.
		$protection = Notice_Archeomed_Settings::get( 'protection' );
		if ( Notice_Archeomed_Pactols::protection_suspendue() ) {
			echo '<div class="notice notice-warning"><p><strong>Chronique&nbsp;:</strong> '
				. esc_html__( 'la vérification anti-robot du formulaire est suspendue (NA_PROTECTION à « aucune », site d’essai). Ne jamais le faire sur le site en ligne.', 'notice-archeomed' )
				. '</p></div>';
		}
		$turnstile_sert = in_array( $protection, array( 'turnstile', 'les_deux' ), true );
		if ( $turnstile_sert && '' === trim( Notice_Archeomed_Settings::get( 'turnstile_secret' ) ) ) {
			$lien = Notice_Archeomed_Settings::url( 'formulaire' );
			echo '<div class="notice notice-error"><p><strong>Chronique&nbsp;:</strong> '
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
	// Une autorisation de reproduction est une lettre ou un formulaire signé :
	// dix méga-octets suffisent largement.
	const MAX_AUTORISATION    = 10485760;
	const AUTORISATION_EXT    = array( 'pdf', 'jpg', 'jpeg', 'png' );
	// L'espace de noms de l'enveloppe des blocs d'index : un nom, non une
	// adresse à consulter.
	const ESPACE_INDEXATION   = 'https://archeomed.cnrs.fr/ns/indexation';
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
		'I. Constructions et habitats civils – Environnement rural et urbain',
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
		// Après « init » : il faut savoir quelle page est demandée.
		add_action( 'template_redirect', array( $this, 'refuser_l_envoi_trop_lourd' ) );
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
		if ( self::protection_suspendue() ) {
			return 'aucune';
		}
		return in_array( $mode, array( 'locale', 'turnstile', 'les_deux' ), true )
			? $mode : 'locale';
	}

	/**
	 * La vérification suspendue, pour un site d'essai seulement.
	 *
	 * Un dépôt complet ne s'essayait qu'à la main : la pièce de puzzle est
	 * faite pour arrêter ce qui n'est pas une personne, et un essai
	 * automatique n'en est pas une. On peut donc la suspendre — mais par la
	 * seule constante « NA_PROTECTION » réglée à « aucune » dans wp-config.php,
	 * jamais depuis la page de réglages, et seulement sur un site qui se
	 * déclare « local » ou « development ». Un site de production ignore la
	 * constante et garde sa protection. Le champ-piège, le délai minimal et
	 * les plafonds d'envoi restent en place.
	 */
	public static function protection_suspendue() {
		return defined( 'NA_PROTECTION' ) && 'aucune' === NA_PROTECTION
			&& function_exists( 'wp_get_environment_type' )
			&& in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
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
	 * Les libellés qu'une rubrique a portés, et celui qui les remplace.
	 *
	 * La rubrique I a pris son nom complet à la campagne 2026. Les notices
	 * reçues avant gardent l'ancien dans leur saisie, et un lien de
	 * correction le renvoie : il se lit comme le nouveau partout où une
	 * rubrique se compare, sans rien réécrire en base, et s'imprime sous le
	 * nouveau.
	 */
	const RUBRIQUES_ANCIENNES = array(
		'I. Constructions et habitats civils' => 'I. Constructions et habitats civils – Environnement rural et urbain',
	);

	/** La rubrique des installations artisanales, qui se divise par matière. */
	const RUBRIQUE_ARTISANAT = 'V. Installations artisanales';

	/**
	 * Les matières de la rubrique V, par leur lettre : « V. A1. », « V. B2. ».
	 */
	const MATIERES = array(
		'A' => 'Céramique, terres cuites architecturales, verrerie',
		'B' => 'Carrières, mines et métallurgie',
		'C' => 'Autres installations artisanales',
	);

	/**
	 * Les familles d'opérations telles que la rubrique V les écrit après sa
	 * matière, par leur rang : « … : opération de terrain ».
	 */
	const FAMILLES_APRES_LA_MATIERE = array(
		1 => 'opération de terrain',
		2 => 'prospections',
		3 => 'projets collectifs de recherche',
	);

	/** Le libellé en vigueur d'une rubrique, ancien libellé compris. */
	public static function rubrique_actuelle( $libelle ) {
		$libelle = is_scalar( $libelle ) ? (string) $libelle : '';
		return array_key_exists( $libelle, self::RUBRIQUES_ANCIENNES ) ? self::RUBRIQUES_ANCIENNES[ $libelle ] : $libelle;
	}

	/**
	 * Tous les libellés sous lesquels une rubrique peut être enregistrée :
	 * le sien, et ceux qu'elle a remplacés.
	 */
	public static function libelles_de_la_rubrique( $rubrique ) {
		$rubrique = self::rubrique_actuelle( $rubrique );
		return array_merge( array( $rubrique ), array_keys( self::RUBRIQUES_ANCIENNES, $rubrique, true ) );
	}

	/**
	 * La lettre de la matière d'une notice de la rubrique V, ou une chaîne
	 * vide : hors de V, ou pour une notice d'avant les matières.
	 */
	public static function matiere_de( $d ) {
		if ( ! is_array( $d ) || self::RUBRIQUE_ARTISANAT !== self::rubrique_actuelle( isset( $d['rubrique_principale'] ) ? $d['rubrique_principale'] : '' ) ) {
			return '';
		}
		$lettre = isset( $d['rubrique_matiere'] ) && is_scalar( $d['rubrique_matiere'] ) ? strtoupper( trim( (string) $d['rubrique_matiere'] ) ) : '';
		return array_key_exists( $lettre, self::MATIERES ) ? $lettre : '';
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
		$rubrique = isset( $d['rubrique_principale'] ) ? self::rubrique_actuelle( $d['rubrique_principale'] ) : '';
		$rang_rubrique = array_search( $rubrique, $this->rubriques, true );
		$rang_rubrique = ( false === $rang_rubrique ) ? 99 : $rang_rubrique + 1;
		$famille = $this->famille_de( $d );
		$commune = remove_accents( (string) ( isset( $d['commune'] ) ? $d['commune'] : '' ) );
		$commune = strtolower( trim( preg_replace( '/[^a-zA-Z0-9]+/', ' ', $commune ) ) );
		// Dans la rubrique V, la matière passe avant la famille : « A1 »,
		// « A2 », puis « B1 ». Une notice sans matière garde le seul chiffre,
		// qui se range en tête.
		return sprintf( '%02d|%s%d|%s', $rang_rubrique, self::matiere_de( $d ), $famille['rang'], $commune );
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
			? self::rubrique_actuelle( sanitize_text_field( wp_unslash( $_GET['rubrique'] ) ) ) : '';
		if ( ! in_array( $rubrique, $this->rubriques, true ) ) {
			wp_die( esc_html__( 'Rubrique inconnue.', 'notice-archeomed' ) );
		}
		$ids = $this->file()->notices_de_la_rubrique( $rubrique );
		if ( empty( $ids ) ) {
			wp_die( esc_html__( 'Aucune notice dans cette rubrique.', 'notice-archeomed' ) );
		}
		// Un dossier à la fois. L'assemblage dure une à trois minutes sans
		// rien montrer : on recliquait, ou l'on demandait la rubrique voisine
		// en attendant, et deux assemblages de cent images se disputaient la
		// mémoire d'un hébergement mutualisé — et les processus qui servent
		// les dépôts des auteurs. Six minutes : au-delà de ce que le serveur
		// accorde à l'assemblage, pour qu'un verrou oublié ne bloque pas.
		if ( get_transient( 'na_assemblage' ) ) {
			wp_die(
				esc_html__( 'Un dossier Métopes est déjà en préparation. Attendez qu’il soit téléchargé avant d’en demander un autre.', 'notice-archeomed' ),
				esc_html__( 'Dossier en préparation', 'notice-archeomed' ),
				array( 'back_link' => true, 'response' => 429 )
			);
		}
		set_transient( 'na_assemblage', time(), 6 * MINUTE_IN_SECONDS );
		// Levé à la fin quoi qu'il arrive : fichier servi, « wp_die » sur une
		// erreur, durée dépassée ou erreur fatale — PHP appelle les fonctions
		// d'arrêt dans tous ces cas, là où un « finally » ne voit pas « exit ».
		register_shutdown_function( 'delete_transient', 'na_assemblage' );
		// Le verrou pris, aucun autre assemblage ne travaille : un atelier de
		// plus d'une heure est celui d'un assemblage mort, qu'on efface avant
		// d'en remplir un nouveau. La purge des dépôts, elle, ne passe que
		// quand un auteur joint une figure.
		Notice_Archeomed_Paquet::purger_les_ateliers( HOUR_IN_SECONDS );
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
			? self::rubrique_actuelle( sanitize_text_field( wp_unslash( $_GET['rubrique'] ) ) ) : '';
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
		$h            = '';
		$descriptions = 0;
		foreach ( (array) $donnees as $d ) {
			if ( ! is_array( $d ) || empty( $d ) ) {
				continue;
			}
			$departement = $this->sans_parentheses( $d['departement'] );
			$titre = esc_html( $this->lieux_en_ligne( $d ) . ' (' . $departement . ')' );
			if ( '' !== $d['lieu_dit'] ) {
				$titre .= esc_html( Notice_Archeomed_Normes::avant_le_lieu_dit() ) . self::lieu_dit_html( $d['lieu_dit'] );
			}
			$h .= '<article><h2>' . $titre . '</h2>';

			// Les mêmes termes qu'au document, portant les mêmes ARK : la page
			// sert aussi à vérifier les liens d'un clic, ce qu'un Word aux
			// liens externes ne permet pas commodément.
			$lier = function ( $items, $capitale = false ) {
				$out = array();
				foreach ( (array) $items as $item ) {
					if ( '' === ( isset( $item['label'] ) ? trim( (string) $item['label'] ) : '' ) ) {
						continue;
					}
					// La graphie du document, forme préférée comprise : la page
					// montrait la variante saisie, le Word du même dossier la
					// forme du thésaurus.
					$label = $this->graphie_imprimee( $item, $capitale );
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
			$autres = $lier( isset( $d['pactols_places_items'] ) ? $d['pactols_places_items'] : array(), true );
			if ( ! empty( $autres ) ) {
				$meta[] = esc_html( 1 === count( $autres ) ? 'Autre lieu : ' : 'Autres lieux : ' )
					. implode( ', ', $autres );
			}
			$h .= '<p class="meta">' . implode( '<br>', $meta ) . '</p>';

			// Le texte a déjà traversé « clean_richtext » au dépôt : il ne
			// porte que huit balises. On le repasse au même tamis plutôt que
			// de s'en remettre à ce qui est en base.
			// La même typographie que le document : la page montrait le texte
			// tel qu'il avait été tapé, le Word du même dossier corrigé.
			$h .= '<div class="texte">' . self::petites_capitales_du_html( self::siecles_du_html(
				self::typographie_du_html( $this->clean_richtext( $d['texte_notice'] ) ) ) ) . '</div>';
			// La mention de responsabilité, que le document accroche au
			// dernier paragraphe : la page l'omettait.
			$h .= '<p class="meta">(' . esc_html( $this->responsables_inline( $d ) ) . ')</p>';

			foreach ( (array) ( isset( $d['illustrations'] ) ? $d['illustrations'] : array() ) as $item ) {
				$h .= '<figure>';
				// La description détaillée, sous la figure, et l'image qui y
				// renvoie : un lecteur d'écran la lit après le texte alternatif.
				$description = isset( $item['description'] ) && is_scalar( $item['description'] )
					? self::paragraphes_simples( $item['description'] ) : array();
				$id_description = 'description-' . ( ++$descriptions );
				if ( ! empty( $item['figure']['fichier'] ) ) {
					// Le texte alternatif de l'auteur ; à défaut, pour une notice
					// d'avant ce champ, le titre.
					$h .= '<img src="' . esc_attr( $item['figure']['fichier'] ) . '" alt="'
						. esc_attr( Notice_Archeomed_Controles::texte_alternatif( $item ) ) . '"'
						. ( empty( $description ) ? '' : ' aria-describedby="' . esc_attr( $id_description ) . '"' ) . '>';
				} else {
					$h .= '<p class="absente">Pas de basse définition pour cette figure.</p>';
				}
				// « Fig. 1 Titre », comme le document le compose.
				$h .= '<figcaption><strong>' . esc_html( Notice_Archeomed_Normes::numero_de_figure( $item['rang'] ) ) . '</strong>';
				$titre_fig = Notice_Archeomed_Controles::titre_sans_numero( $item['titre'], $item['rang'] );
				if ( '' !== $titre_fig ) {
					$h .= esc_html( Notice_Archeomed_Normes::typographie( Notice_Archeomed_Normes::apres_le_numero() . $titre_fig ) );
				}
				if ( '' !== $item['legende'] ) {
					$h .= '<br>' . esc_html( $item['legende'] );
				}
				if ( '' !== $item['credits'] ) {
					$h .= '<br><em>' . esc_html( $item['credits'] ) . '</em>';
				}
				// Le relecteur voit ce que l'image dira à qui ne la voit pas.
				if ( ! empty( $item['alt'] ) ) {
					$h .= '<br><span class="alt">' . esc_html( "Texte alternatif\u{00A0}: " . Notice_Archeomed_Controles::une_ligne( $item['alt'] ) ) . '</span>';
				}
				$h .= '</figcaption>';
				if ( ! empty( $description ) ) {
					$h .= '<div class="description" id="' . esc_attr( $id_description ) . '"><p><strong>'
						. esc_html( "Description détaillée\u{00A0}:" ) . '</strong> '
						. implode( '</p><p>', array_map( 'esc_html', $description ) ) . '</p></div>';
				}
				$h .= '</figure>';
			}
			if ( ! empty( $d['avis'] ) ) {
				$h .= '<aside class="avis"><strong>À vérifier</strong><ul>';
				foreach ( (array) $d['avis'] as $un_avis ) {
					$h .= '<li>' . esc_html( $un_avis ) . '</li>';
				}
				$h .= '</ul></aside>';
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
			. '.avis{font-family:Arial,sans-serif;font-size:.82em;background:#fdf6e3;border-left:4px solid #8a6d3b;padding:.6em 1em;margin:1em 0}'
			. '.avis ul{margin:.4em 0 0;padding-left:1.2em}@media print{.avis{display:none}}'
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
			$contenu .= $doc->plain( Notice_Archeomed_Normes::avant_le_lieu_dit() )
				. $this->run_xml( $doc, array( 'text' => $d['lieu_dit'], 'i' => Notice_Archeomed_Normes::lieu_dit_en_italique() ) );
		}
		$contenu .= $doc->plain( ' — Voir dans la rubrique ' )
			. $this->run_xml( $doc, array(
				'text' => $this->rubrique_sans_numero( $d['rubrique_principale'] ),
				'i'    => true ) )
			. $doc->plain( '.' );
		// Le style du titre d'une notice, comme une entrée sans corps. En
		// Normal, la ligne n'avait pas de titre pour l'ouvrir, et la
		// conversion la rangeait dans la notice précédente. Métopes n'a pas de
		// style pour un contenu qu'on voudrait taire à l'écran : ses styles
		// locaux retombent en paragraphe ordinaire à l'export, ce qui
		// reproduirait le défaut.
		$doc->add_raw_paragraph( Notice_Archeomed_Styles::de( 'renvoi' ), $contenu );
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
		$doc->add_paragraph( Notice_Archeomed_Styles::de( 'rubrique' ), array( array(
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
			// Un renvoi se titre sous la rubrique du fascicule, non sous celle
			// de sa notice : « I. 1. » ouvrait une fausse sous-rubrique dans
			// la rubrique II, et coupait la vraie en deux. La famille, elle,
			// reste celle de l'opération.
			$famille = $this->titre_de_famille( 'renvoi' === $entree['quoi']
				? array_merge( $entree['d'], array( 'rubrique_principale' => $rubrique ) )
				: $entree['d'] );
			if ( $famille !== $famille_en_cours ) {
				$doc->add_paragraph( Notice_Archeomed_Styles::de( 'sous_rubrique' ),
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
			'rubrique_principale', 'rubrique_matiere', 'renvoi_1', 'renvoi_2',
			'resp_prenom', 'resp_nom', 'resp_email', 'resp_inst',
			'coresp_prenom', 'coresp_nom', 'coresp_email', 'coresp_inst',
			'coauteur_prenom', 'coauteur_nom', 'coauteur_email', 'coauteur_inst',
			'departement', 'lieu_dit', 'annee',
			'num_autorisation', 'id_patriarche', 'rapport_lien', 'originaux_lien', 'commentaires',
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
			'illus_titre', 'illus_legende', 'illus_credits', 'illus_alt', 'illus_description' ) as $champ ) {
			if ( isset( $_POST[ $champ ] ) && is_array( $_POST[ $champ ] ) ) {
				$garde[ $champ ] = array_map( 'sanitize_textarea_field',
					array_map( 'strval', wp_unslash( $_POST[ $champ ] ) ) );
			}
		}
		// Le nom de chaque fichier, dans l'ordre des légendes : au retour, les
		// légendes se rattachent au fichier redéposé qui porte ce nom, et non
		// à son rang. Redéposés dans l'autre ordre, deux fichiers échangeaient
		// leurs légendes sans que rien ne le dise.
		if ( isset( $garde['illus_titre'] ) && isset( $_FILES['illustrations']['name'] )
			&& is_array( $_FILES['illustrations']['name'] ) ) {
			// Le nom tel que le navigateur le redonnera : sanitize_file_name()
			// le réécrirait (« fig 1.jpg » en « fig-1.jpg »), et plus rien ne
			// se rattacherait. PHP ne l'échappe pas, comme le reste de $_FILES.
			$garde['illus_nom'] = array_map( 'sanitize_text_field',
				array_map( 'strval', $_FILES['illustrations']['name'] ) );
			// L'autorisation de chaque figure, en drapeau : le fichier ne
			// revient pas plus qu'une illustration, mais le formulaire peut
			// dire qu'il est à rejoindre.
			$garde['illus_autorisation'] = array();
			foreach ( array_keys( $garde['illus_titre'] ) as $i ) {
				$garde['illus_autorisation'][ $i ] = ( isset( $_FILES['illus_autorisation']['error'][ $i ] )
					&& UPLOAD_ERR_OK === (int) $_FILES['illus_autorisation']['error'][ $i ] ) ? '1' : '';
			}
		}
		$garde = $this->garder_les_figures_en_attente( $garde );
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

	/**
	 * Ajoute à la saisie gardée les figures de la réserve d'avant que ce
	 * dépôt n'a pas redéposées : leurs titres, légendes, crédits, textes
	 * alternatifs et la marque de leur autorisation.
	 *
	 * Les textes d'une figure ne se gardaient que si son fichier était posté.
	 * Une correction faite sans redéposer les figures laissait donc une
	 * réserve sans eux, et la correction suivante revenait sans un mot de
	 * ce qui avait été écrit : ils ne vivaient plus que dans la saisie de la
	 * première notice. Ils passent désormais de réserve en réserve, et
	 * attendent leur fichier dans le formulaire comme à la première reprise.
	 */
	private function garder_les_figures_en_attente( $garde ) {
		$jeton = ( isset( $_POST['reprise_jeton'] ) && is_string( $_POST['reprise_jeton'] ) )
			? sanitize_key( wp_unslash( $_POST['reprise_jeton'] ) ) : '';
		if ( '' === $jeton ) {
			return $garde;
		}
		$avant = get_transient( 'na_reprise_' . $jeton );
		if ( ! is_array( $avant ) || empty( $avant['illus_titre'] ) || ! is_array( $avant['illus_titre'] ) ) {
			return $garde;
		}
		$champs = array( 'illus_titre', 'illus_legende', 'illus_credits', 'illus_alt', 'illus_description', 'illus_nom', 'illus_autorisation' );
		$postes = isset( $garde['illus_titre'] ) ? count( $garde['illus_titre'] ) : 0;
		$noms   = isset( $garde['illus_nom'] ) ? array_filter( array_map( 'strval', (array) $garde['illus_nom'] ) ) : array();
		foreach ( $champs as $champ ) {
			$liste = isset( $garde[ $champ ] ) ? array_values( (array) $garde[ $champ ] ) : array();
			$garde[ $champ ] = array_pad( array_slice( $liste, 0, $postes ), $postes, '' );
		}
		$ajoutees = 0;
		foreach ( array_keys( array_values( $avant['illus_titre'] ) ) as $j ) {
			$figure = array();
			foreach ( $champs as $champ ) {
				$liste = isset( $avant[ $champ ] ) && is_array( $avant[ $champ ] ) ? array_values( $avant[ $champ ] ) : array();
				$figure[ $champ ] = ( isset( $liste[ $j ] ) && is_scalar( $liste[ $j ] ) ) ? (string) $liste[ $j ] : '';
			}
			// Redéposée, la figure a repris ses textes dans le formulaire. Sans
			// nom — une réserve d'avant les noms —, on ne sait pas la
			// reconnaître : elle n'attend que si rien n'a été redéposé.
			if ( '' !== $figure['illus_nom'] ? in_array( $figure['illus_nom'], $noms, true ) : $postes > 0 ) {
				continue;
			}
			if ( '' === $figure['illus_nom'] && '' === trim( $figure['illus_titre'] . $figure['illus_legende']
				. $figure['illus_credits'] . $figure['illus_alt'] . $figure['illus_description'] ) ) {
				continue;
			}
			foreach ( $champs as $champ ) {
				$garde[ $champ ][] = $figure[ $champ ];
			}
			++$ajoutees;
		}
		// Rien de posté, rien d'ajouté : la saisie reste sans figures, comme
		// avant.
		if ( 0 === $postes + $ajoutees ) {
			foreach ( $champs as $champ ) {
				unset( $garde[ $champ ] );
			}
		}
		return $garde;
	}

	/**
	 * Efface la réserve du lien de correction qui vient d'être utilisé, et
	 * garde un mois la référence du dépôt qu'il a servi à faire.
	 *
	 * Le lien rouvert ensuite donnait un formulaire vierge, sans un mot :
	 * l'auteur qui reprenait sa première copie retapait tout et déposait une
	 * notice neuve, sans marque de correction. La trace permet de dire que le
	 * lien a servi, et pour quel dépôt.
	 */
	private function oublier_le_jeton_repris( $reference = '' ) {
		$jeton = isset( $_POST['reprise_jeton'] )
			? sanitize_key( wp_unslash( $_POST['reprise_jeton'] ) ) : '';
		if ( '' !== $jeton ) {
			delete_transient( 'na_reprise_' . $jeton );
			if ( '' !== $reference ) {
				set_transient( 'na_reprise_faite_' . $jeton, (string) $reference, MONTH_IN_SECONDS );
			}
		}
	}

	/**
	 * Ce qu'est devenu le lien de reprise par lequel on arrive.
	 *
	 * Rend « etat » : « » quand il n'y a pas de jeton ou que sa saisie est
	 * là ; « utilise » quand il a déjà servi à un dépôt, dont « reference »
	 * donne alors la référence ; « expire » sinon — passé son mois (une heure
	 * après un refus), ou incomplet. Le formulaire s'en sert pour dire
	 * pourquoi il revient vide.
	 */
	private function etat_du_lien_de_reprise() {
		$etat  = array( 'etat' => '', 'reference' => '' );
		$jeton = $this->jeton_de_reprise();
		if ( '' === $jeton || ! empty( $this->reprise() ) ) {
			return $etat;
		}
		$faite = (string) get_transient( 'na_reprise_faite_' . $jeton );
		if ( '' !== $faite ) {
			return array( 'etat' => 'utilise', 'reference' => $faite );
		}
		$etat['etat'] = 'expire';
		return $etat;
	}

	/**
	 * L'avis d'un dépôt envoyé depuis une saisie qui avait déjà été déposée.
	 *
	 * Le bouton Précédent, ou le lien de correction rouvert après usage,
	 * rendent une saisie prête à repartir : la rédaction recevait deux
	 * notices sans le savoir. On ne refuse rien — les avis avertissent —, on
	 * nomme le dépôt que celui-ci double probablement.
	 */
	private function avis_du_lien_repris() {
		$jeton = isset( $_POST['reprise_jeton'] )
			? sanitize_key( wp_unslash( $_POST['reprise_jeton'] ) ) : '';
		if ( '' === $jeton || is_array( get_transient( 'na_reprise_' . $jeton ) ) ) {
			return array();
		}
		$faite = (string) get_transient( 'na_reprise_faite_' . $jeton );
		if ( '' === $faite ) {
			return array();
		}
		return array( 'Cette saisie avait déjà été déposée sous la référence ' . $faite
			. "\u{00A0}: doublon probable. Elle ne porte pas de marque de correction." );
	}

	/**
	 * L'adresse de la page qui porte le formulaire, sans nos paramètres.
	 *
	 * Le référent n'est retenu que s'il est sur ce site : un en-tête
	 * « Referer » brut, qu'un robot choisit, renvoyait l'auteur hors du site
	 * — WordPress le ramenait alors à l'administration.
	 */
	private function url_du_formulaire() {
		$referer = wp_get_referer();
		if ( ! $referer && isset( $_SERVER['HTTP_REFERER'] ) && is_string( $_SERVER['HTTP_REFERER'] ) ) {
			// Le formulaire se renvoie à sa propre adresse, que wp_get_referer
			// écarte : on garde l'en-tête, pourvu qu'il désigne ce site.
			$referer = wp_validate_redirect( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), '' );
		}
		if ( ! $referer ) {
			$referer = $this->page_du_formulaire();
		}
		if ( ! $referer ) {
			$referer = home_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' );
		}
		return remove_query_arg(
			array( 'notice_envoyee', 'notice_erreur', 'notice_champ', 'notice_champs',
				'notice_ref', 'notice_reprise' ),
			$referer );
	}

	/**
	 * L'adresse publiée du formulaire, pour l'onglet qui en contrôle
	 * l'accessibilité.
	 */
	public function adresse_du_formulaire() {
		return $this->page_du_formulaire();
	}

	/**
	 * L'adresse publiée de la page qui porte le formulaire, lue dans le site.
	 *
	 * La première page publiée qui contient le shortcode ; à défaut, un autre
	 * contenu publié. Chaîne vide si aucune ne le porte.
	 */
	private function page_du_formulaire() {
		static $adresse = null;
		if ( null !== $adresse ) {
			return $adresse;
		}
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type NOT IN ( 'revision', 'nav_menu_item', %s )"
			. " AND post_content LIKE %s ORDER BY ( post_type = 'page' ) DESC, ID ASC LIMIT 1",
			Notice_Archeomed_File::CPT, '%' . $wpdb->esc_like( '[notice_archeomed_pactols' ) . '%' ) );
		$lien    = $id ? get_permalink( $id ) : '';
		$adresse = is_string( $lien ) ? $lien : '';
		return $adresse;
	}

	/**
	 * Le lien qui rouvre le formulaire rempli, pour corriger ce qu'on vient
	 * de déposer.
	 *
	 * C'est en recevant sa copie qu'on voit ce qu'on n'avait pas vu en
	 * saisissant. Sans ce lien, il fallait tout retaper, ou écrire à la
	 * rédaction — et la rédaction se retrouvait à corriger à la main.
	 *
	 * L'adresse est celle de la page du site, non le référent de la requête :
	 * un référent forgé faisait partir de la revue, vers l'adresse saisie,
	 * un lien qu'un tiers avait choisi.
	 */
	private function lien_de_correction( $reference ) {
		$jeton = $this->garder_la_saisie( MONTH_IN_SECONDS, $reference );
		if ( '' === $jeton ) {
			return '';
		}
		$page = $this->page_du_formulaire();
		return add_query_arg( 'notice_reprise', $jeton, '' !== $page ? $page : $this->url_du_formulaire() );
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
	/**
	 * Les options d'une liste déroulante, précédées du choix vide.
	 *
	 * Le choix vide disait « - Sélectionner - » partout, y compris sur les
	 * renvois, où ne rien choisir est une réponse et non un oubli.
	 */
	private function options_html( $options, $selected = '', $vide = 'Choisir…' ) {
		$html = '<option value="">' . esc_html( $vide ) . '</option>';
		foreach ( $options as $opt ) {
			$sel   = ( $opt === $selected ) ? ' selected' : '';
			$html .= '<option value="' . esc_attr( $opt ) . '"' . $sel . '>' . esc_html( $opt ) . '</option>';
		}
		return $html;
	}

	/**
	 * Les natures cochables, rangées par famille d'opérations.
	 *
	 * Douze cases à plat se lisaient mal, et ne disaient rien de ce que la
	 * Chronique en fait. Les familles sont celles du volume ; une nature
	 * qu'aucune n'aurait rangée se coche quand même, dans un dernier groupe.
	 */
	private function natures_par_famille() {
		$groupes = array();
		$rangees = array();
		foreach ( $this->familles as $famille => $liste ) {
			$gardees = array();
			foreach ( $liste as $nature ) {
				if ( isset( $this->natures[ $nature ] ) ) {
					$gardees[] = $nature;
					$rangees[] = $nature;
				}
			}
			if ( ! empty( $gardees ) ) {
				$groupes[ $famille ] = $gardees;
			}
		}
		$reste = array_values( array_diff( array_keys( $this->natures ), $rangees ) );
		if ( ! empty( $reste ) ) {
			$groupes['Autres natures'] = $reste;
		}
		return $groupes;
	}

	/**
	 * Le groupe « Nature de l'opération » : un vrai groupe de cases, avec sa
	 * légende, là où il n'y avait qu'un libellé orphelin.
	 */
	private function natures_html() {
		$html  = '<fieldset class="na-sous-groupe na-champ" id="na-nature" aria-describedby="na-nature-aide">';
		$html .= '<legend>Nature de l’opération</legend>';
		$html .= '<p class="na-help" id="na-nature-aide">Cochez toutes celles qui s’appliquent.</p>';
		$html .= '<div class="na-familles na-ancre">';
		$n = 0;
		foreach ( $this->natures_par_famille() as $famille => $liste ) {
			$html .= '<fieldset class="na-famille"><legend>' . esc_html( $famille ) . '</legend>';
			$html .= '<div class="na-checkboxes">';
			foreach ( $liste as $opt ) {
				++$n;
				$html .= '<label class="na-check" for="na-nature-' . $n . '">'
					. '<input type="checkbox" id="na-nature-' . $n . '" name="nature[]" value="'
					. esc_attr( $opt ) . '"' . $this->repris_coche( 'nature', $opt ) . '> '
					. esc_html( $opt ) . '</label>';
			}
			$html .= '</div></fieldset>';
		}
		$html .= '</div></fieldset>';
		return $html;
	}

	/**
	 * Une catégorie de mots-clés Pactols : un seul champ de recherche, et les
	 * termes retenus en jetons.
	 *
	 * Il y avait dix cases numérotées par catégorie, trente en tout, qui
	 * faisaient à elles seules la moitié de la page sur un téléphone. Le
	 * champ caché que le serveur lit (« pactols_periods »…) ne change pas :
	 * le script y tient la liste, dans le même JSON qu'avant.
	 */
	private function pactols_categorie_html( $type, $titre, $aide, $cherche, $cible ) {
		$id    = 'na-kw-' . $type;
		$html  = '<fieldset class="na-kw" id="' . esc_attr( $id ) . '" data-type="' . esc_attr( $type )
			. '" data-cible="' . esc_attr( $cible ) . '">';
		$html .= '<legend>' . esc_html( $titre ) . '</legend>';
		$html .= '<p class="na-help" id="' . esc_attr( $id ) . '-aide">' . $aide . '</p>';
		$html .= '<ul class="na-jetons" id="' . esc_attr( $id ) . '-jetons" aria-label="'
			. esc_attr( $titre . ' retenus' ) . '" hidden></ul>';
		$html .= '<div class="na-combo na-ancre">';
		$html .= '<label class="na-sr" for="' . esc_attr( $id ) . '-champ">' . esc_html( $cherche ) . '</label>';
		$html .= '<input type="text" id="' . esc_attr( $id ) . '-champ" class="na-kw-champ" autocomplete="off"'
			. ' spellcheck="false" enterkeyhint="search" placeholder="Rechercher…"'
			. ' aria-describedby="' . esc_attr( $id ) . '-aide ' . esc_attr( $id ) . '-compte ' . esc_attr( $id ) . '-etat">';
		$html .= '<ul class="na-pactols-suggestions" id="' . esc_attr( $id ) . '-liste" hidden></ul>';
		$html .= '</div>';
		$html .= '<p class="na-kw-compte" id="' . esc_attr( $id ) . '-compte">0 sur '
			. (int) self::PACTOLS_FIELD_COUNT . '</p>';
		$html .= '<p class="na-kw-etat" id="' . esc_attr( $id ) . '-etat" aria-live="polite"></p>';
		$html .= '</fieldset>';
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
	 *
	 * Les refus de fichiers se disent maintenant selon leur cause : « trois
	 * au plus, vingt méga-octets » était faux pour qui avait respecté ces
	 * bornes et buté sur la limite du serveur, plus basse.
	 */
	private function message_derreur( $raison ) {
		$plafonds = $this->plafonds_des_fichiers();
		$total    = $this->en_mo( $plafonds['total'] ) . "\u{00A0}Mo";
		$fichier  = $this->en_mo( $plafonds['fichier'] ) . "\u{00A0}Mo";
		$serveur  = $this->en_mo( $plafonds['requete'] ) . "\u{00A0}Mo";
		$messages = array(
			'securite'        => 'La page était ouverte depuis trop longtemps et a expiré.',
			'quota'           => 'Trop de tentatives depuis cette connexion. Attendez une heure, ou écrivez directement à la rédaction.',
			'envois'          => 'Le nombre de notices envoyées depuis cette adresse a atteint la limite horaire. Attendez une heure, ou écrivez directement à la rédaction.',
			'verification'    => 'La vérification n’a pas abouti. Faites glisser la pièce jusqu’à son encoche, puis renvoyez le formulaire.',
			'champs'          => 'Un renseignement obligatoire manque ou n’est pas valide.',
			'texte'           => 'Le texte de la notice est vide, ou dépasse la longueur admise.',
			'fichiers'        => 'Un fichier joint n’a pas pu être reçu par le serveur. Renvoyez le formulaire.',
			'fichier_lourd'   => 'Un fichier dépasse la taille que le serveur accepte par fichier (' . $fichier . '). Joignez-en une version plus légère.',
			'fichier_coupe'   => 'Un fichier n’est arrivé qu’en partie : la connexion a sans doute été coupée pendant l’envoi. Renvoyez le formulaire.',
			'fichiers_nombre' => 'Trois fichiers au plus peuvent être joints.',
			'fichiers_total'  => 'Les fichiers joints dépassent ' . $total . ' en tout. Joignez des versions plus légères.',
			'fichier_format'  => 'Un fichier joint n’est pas en JPEG, TIFF ou PDF, ou son contenu ne correspond pas à son extension.',
			'autorisation'    => 'Une autorisation de reproduction n’est pas en PDF, JPEG ou PNG, ou dépasse ' . size_format( self::MAX_AUTORISATION ) . '.',
			'trop_lourd'      => 'Votre envoi dépassait ce que le serveur accepte (' . $serveur . ' en tout) : rien n’a été reçu. Joignez des illustrations plus légères, puis renvoyez. Si votre saisie a été gardée sur cet appareil, elle vous est proposée juste en dessous.',
			'envoi'           => 'Le courriel n’a pas pu partir, mais votre notice n’est pas perdue : elle est conservée sur le serveur, fichier stylé compris.',
		);
		$mot = isset( $messages[ $raison ] ) ? $messages[ $raison ] : 'Une erreur est survenue lors de l’envoi.';
		// Un champ oublié se corrige dans la page : l'adresse de la rédaction
		// n'y ajouterait qu'une tentation d'écrire au lieu de corriger.
		if ( in_array( $raison, array( 'champs', 'texte' ), true ) ) {
			return $this->typo( $mot );
		}
		$redaction = $this->adresse_de_contact();
		return $this->typo( '' !== $redaction
			? $mot . ' Si le problème persiste, écrivez à ' . esc_html( $redaction ) . '.'
			: $mot . ' Si le problème persiste, écrivez à la rédaction de la revue.' );
	}

	/**
	 * Les espaces que la typographie française veut insécables, avant « : »,
	 * « ; », « ! » et « ? ». Sans elles, la ponctuation se retrouvait seule
	 * en tête de ligne. L'insécable ordinaire partout, comme dans le reste de
	 * l'extension : l'espace fine manque à certaines polices et s'affichait
	 * en carré.
	 */
	private function typo( $texte ) {
		return preg_replace( '/ ([:;!?])(?=\s|$|<)/u', "\u{00A0}$1", (string) $texte );
	}

	/**
	 * Chaque champ que l'on peut refuser : où il se trouve dans la page, et
	 * ce qu'on dit quand il manque.
	 *
	 * Le navigateur et le serveur parlaient chacun à leur façon — une bulle
	 * ici, « Il manque la rubrique principale » là —, et le serveur
	 * s'arrêtait au premier champ. Une seule table, lue des deux côtés : le
	 * même message sous le champ, qu'il vienne du script ou du retour. Elle
	 * suit l'ordre de la page, qui est celui du récapitulatif.
	 */
	private function champs_du_formulaire() {
		$deux_points = "\u{00A0}:";
		return array(
			'commune'             => array( 'na-lieu-1', 'Indiquez la commune ou le territoire de l’opération' ),
			'departement'         => array( 'na-departement', 'Indiquez ce qui figure entre parenthèses, le plus souvent le département' ),
			'lieu_dit'            => array( 'na-lieu-dit', 'Indiquez ce qui suit le point' . $deux_points . ' lieu-dit, adresse ou nom du projet' ),
			'nature'              => array( 'na-nature-1', 'Cochez au moins une nature d’opération' ),
			'annee'               => array( 'na-annee', 'Indiquez l’année de l’opération, par exemple 2024' ),
			'organisme'           => array( 'na-organisme-1', 'Indiquez l’organisme qui gère l’opération' ),
			'rapport_lien'        => array( 'na-rapport', 'Saisissez l’adresse complète du rapport, par exemple https://hal.science/hal-01234567' ),
			'resp_prenom'         => array( 'na-resp-prenom', 'Indiquez le prénom du responsable d’opération' ),
			'resp_nom'            => array( 'na-resp-nom', 'Indiquez le nom du responsable d’opération' ),
			'resp_email'          => array( 'na-resp-email', 'Indiquez l’adresse électronique du responsable d’opération' ),
			'resp_inst'           => array( 'na-resp-inst', 'Indiquez l’institution de rattachement du responsable d’opération' ),
			'email_forme'         => array( 'na-resp-email', 'Saisissez une adresse électronique complète pour le responsable d’opération, par exemple prenom.nom@exemple.fr' ),
			'coresp_email'        => array( 'na-coresp-email', 'Saisissez une adresse électronique complète pour le co-responsable, par exemple prenom.nom@exemple.fr' ),
			'coauteur_email'      => array( 'na-coauteur-email', 'Saisissez une adresse électronique complète pour le co-auteur, par exemple prenom.nom@exemple.fr' ),
			'texte'               => array( 'na-texte', 'Saisissez ou collez le texte de la notice' ),
			'rubrique_principale' => array( 'na-rubrique', 'Choisissez la rubrique principale de la notice' ),
			'rubrique_matiere'    => array( 'na-matiere', 'Choisissez la matière de l’installation artisanale (A, B ou C)' ),
			// Les champs d'une figure ne sont dans la page qu'une fois son
			// fichier choisi : le lien y mène quand il existe.
			'illus_alt_1'         => array( 'na-illus-1-alt', 'Décrivez en quelques mots ce que montre la figure 1, dans son texte alternatif' ),
			'illus_alt_2'         => array( 'na-illus-2-alt', 'Décrivez en quelques mots ce que montre la figure 2, dans son texte alternatif' ),
			'illus_alt_3'         => array( 'na-illus-3-alt', 'Décrivez en quelques mots ce que montre la figure 3, dans son texte alternatif' ),
			'originaux_lien'      => array( 'na-originaux', 'Saisissez l’adresse complète du lien de téléchargement, par exemple https://filesender.renater.fr/…' ),
			'puzzle'              => array( 'na-puzzle-poignee', 'Faites glisser la pièce jusqu’à son encoche' ),
		);
	}

	/**
	 * Les champs que le retour signale, dans l'ordre de la page.
	 *
	 * « notice_champs » porte la liste ; « notice_champ », le seul que
	 * connaissaient les versions précédentes, est encore lu : un lien de
	 * retour ouvert entre deux versions doit dire la même chose.
	 */
	private function champs_signales() {
		$cles = array();
		if ( isset( $_GET['notice_champs'] ) && is_string( $_GET['notice_champs'] ) ) {
			foreach ( explode( ',', wp_unslash( $_GET['notice_champs'] ) ) as $cle ) {
				$cles[] = sanitize_key( $cle );
			}
		}
		if ( isset( $_GET['notice_champ'] ) && is_string( $_GET['notice_champ'] ) ) {
			$cles[] = sanitize_key( wp_unslash( $_GET['notice_champ'] ) );
		}
		$table = $this->champs_du_formulaire();
		return array_values( array_intersect( array_keys( $table ), $cles ) );
	}

	/**
	 * Ce que le serveur accepte réellement : par fichier, en tout, et pour
	 * la requête entière.
	 *
	 * Le formulaire annonçait vingt méga-octets quelle que soit la
	 * configuration. Sur un hébergement réglé plus bas, PHP vidait la requête
	 * trop lourde sans un mot, et l'auteur retrouvait un formulaire vide.
	 * On garde un méga-octet pour le texte et les autres champs.
	 */
	private function plafonds_des_fichiers() {
		$requete = wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) );
		$total   = self::MAX_TOTAL_FILESIZE;
		if ( $requete > 0 ) {
			$total = min( $total, max( MB_IN_BYTES, $requete - MB_IN_BYTES ) );
		}
		$fichier = (int) wp_max_upload_size();
		$fichier = $fichier > 0 ? min( $fichier, $total ) : $total;
		return array(
			'fichier' => $fichier,
			'total'   => $total,
			'requete' => $requete > 0 ? $requete : $total,
		);
	}

	/** « 20 », « 7,5 » : des méga-octets à la française. */
	private function en_mo( $octets ) {
		$mo = round( $octets / MB_IN_BYTES, 1 );
		return ( floor( $mo ) == $mo ) ? (string) (int) $mo : str_replace( '.', ',', (string) $mo );
	}

	/**
	 * Les fichiers à redéposer, nommés : « fig1.jpg, plan.tif ». Vide pour
	 * une reprise d'avant les noms gardés.
	 */
	private function fichiers_a_redeposer() {
		$garde = $this->reprise();
		if ( empty( $garde['illus_nom'] ) || ! is_array( $garde['illus_nom'] ) ) {
			return '';
		}
		return implode( ', ', array_filter( array_map( 'strval', $garde['illus_nom'] ) ) );
	}

	/**
	 * Les messages qui ouvrent le formulaire au retour d'un refus, ou quand
	 * il se rouvre pour une correction.
	 *
	 * Ils se plaçaient hors du formulaire, sans focus ni annonce, et ne
	 * nommaient que le premier champ manquant. Ils nomment maintenant chacun
	 * d'eux, avec un lien qui y mène.
	 */
	private function messages_du_retour() {
		$html    = '';
		$reprise = $this->reprise();
		if ( isset( $_GET['notice_envoyee'] ) && '0' === $_GET['notice_envoyee'] ) {
			$raison = isset( $_GET['notice_erreur'] )
				? sanitize_key( wp_unslash( $_GET['notice_erreur'] ) ) : '';
			$champs = $this->champs_signales();
			$table  = $this->champs_du_formulaire();
			$corps  = '';
			if ( ! empty( $champs ) ) {
				$corps .= '<h2>' . ( count( $champs ) > 1
					? 'Il reste ' . count( $champs ) . ' points à corriger'
					: 'Il reste un point à corriger' ) . '</h2><ul>';
				foreach ( $champs as $cle ) {
					$corps .= '<li><a href="#' . esc_attr( $table[ $cle ][0] ) . '">'
						. esc_html( $table[ $cle ][1] ) . '</a></li>';
				}
				$corps .= '</ul>';
				if ( 'texte' === $raison && array( 'texte' ) !== $champs ) {
					$corps .= '<p>' . esc_html( $this->message_derreur( 'texte' ) ) . '</p>';
				}
			} else {
				$corps .= '<p>' . wp_kses_post( $this->message_derreur( $raison ) ) . '</p>';
			}
			if ( ! empty( $reprise ) ) {
				$suite = '<strong>Votre saisie est conservée : le formulaire est '
					. 'rempli comme vous l’aviez laissé.</strong>';
				if ( ! empty( $reprise['illus_titre'] ) ) {
					$noms   = $this->fichiers_a_redeposer();
					$suite .= ' Les fichiers, eux, sont à redéposer'
						. ( '' !== $noms ? ' (' . esc_html( $noms ) . ')' : '' )
						. ' — aucun navigateur ne permet de les remettre en place. '
						. 'Ce que vous aviez écrit à leur sujet revient dès que vous '
						. 'les aurez rechoisis. Leur numéro, que le texte cite, suit '
						. 'l’ordre des figures : choisis ensemble, ils reprennent celui d’avant.';
				}
				$corps .= '<p>' . $suite . '</p>';
			}
			$ref = isset( $_GET['notice_ref'] )
				? strtoupper( sanitize_key( wp_unslash( $_GET['notice_ref'] ) ) ) : '';
			if ( 'envoi' === $raison && '' !== $ref ) {
				$corps .= '<p>Citez la référence <strong>' . esc_html( $ref )
					. '</strong> dans votre message : elle permet de la retrouver.</p>';
			}
			$html .= '<div class="na-message na-error" id="na-message" role="alert" tabindex="-1"'
				. ' data-champs="' . esc_attr( implode( ',', $champs ) ) . '">' . $this->typo( $corps ) . '</div>';
		}
		// Arrivée par le lien de correction : il faut le dire avant qu'on se
		// demande pourquoi le formulaire est déjà rempli, et prévenir que le
		// renvoi produit un second dépôt.
		if ( ! empty( $reprise['remplace'] ) ) {
			$html .= '<div class="na-message na-avis"><p>' . $this->typo( '<strong>Vous corrigez la notice déposée '
				. 'sous la référence ' . esc_html( $reprise['remplace'] ) . '.</strong> '
				. 'Le formulaire est rempli de votre saisie ; les illustrations sont à '
				. 'redéposer' . ( '' !== $this->fichiers_a_redeposer()
					? ' (' . esc_html( $this->fichiers_a_redeposer() ) . ')' : '' )
				. ', aucun navigateur ne permettant de les remettre en place. '
				. 'En le renvoyant, vous produisez un nouveau dépôt : la rédaction sera '
				. 'avertie qu’il remplace le précédent, et supprimera celui-ci.' ) . '</p></div>';
		}
		return $html;
	}

	/**
	 * La page qui suit un dépôt réussi : la référence, et ce qui va se passer.
	 *
	 * Le formulaire vide se réaffichait sous un message d'une ligne, ce qui
	 * invitait à déposer une seconde fois ; la référence était produite mais
	 * jamais montrée. On s'arrête ici : le formulaire ne revient que si l'on
	 * demande à déposer une autre notice.
	 */
	private function panneau_de_fin() {
		$ref = isset( $_GET['notice_ref'] )
			? strtoupper( sanitize_key( wp_unslash( $_GET['notice_ref'] ) ) ) : '';
		if ( ! preg_match( '/^[A-Z0-9]{6}$/', $ref ) ) {
			$ref = '';
		}
		$redaction = $this->adresse_de_contact();
		$autre     = remove_query_arg( array( 'notice_envoyee', 'notice_erreur', 'notice_champ',
			'notice_champs', 'notice_ref', 'notice_reprise' ) );
		ob_start();
		?>
		<style>
			.na-fin#na-fin { --na-encre: #1f1a17; --na-sourd: #5b534b; --na-accent: #7d1a1d;
				--na-succes: #2d6a33; --na-succes-fond: #edf5ee;
				max-width: 44rem; margin: 0 0 3rem; padding: 1.25rem 1.5rem 1.5rem;
				font-size: max(16px, 1rem); line-height: 1.55; color: var(--na-encre);
				background: var(--na-succes-fond); border: 1px solid var(--na-succes);
				border-left-width: 4px; border-radius: 6px; }
			.na-fin#na-fin:focus { outline: 3px solid var(--na-accent); outline-offset: 2px; }
			.na-fin#na-fin h2, .na-fin#na-fin h3 { margin: 0 0 .5rem; padding: 0; color: var(--na-encre);
				font-weight: 600; line-height: 1.3; letter-spacing: normal; text-transform: none; }
			.na-fin#na-fin h2 { font-size: max(21px, 1.3125rem); }
			.na-fin#na-fin h3 { margin-top: 1.25rem; font-size: max(18px, 1.125rem); }
			.na-fin#na-fin p { margin: 0 0 .75rem; color: var(--na-encre); }
			.na-fin#na-fin .na-fin-ref { font-size: max(18px, 1.125rem); }
			.na-fin#na-fin .na-fin-ref strong { font-variant-numeric: tabular-nums; letter-spacing: .08em; }
			.na-fin#na-fin a { color: var(--na-accent); text-decoration: underline; text-underline-offset: .18em; }
			.na-fin#na-fin .na-fin-autre { margin: 1.25rem 0 0; }
		</style>
		<div class="na-fin" id="na-fin" tabindex="-1" aria-labelledby="na-fin-titre">
			<h2 id="na-fin-titre">Votre notice est déposée</h2>
			<?php if ( '' !== $ref ) : ?>
				<p class="na-fin-ref">Référence&nbsp;: <strong><?php echo esc_html( $ref ); ?></strong></p>
			<?php endif; ?>
			<p>Une copie part dans quelques minutes à l’adresse du responsable d’opération, et à celles des autres personnes dont vous avez donné l’adresse. Elle contient un lien pour corriger la notice pendant un mois. Si vous avez déposé pour le responsable, c’est lui qui la reçoit&nbsp;: demandez-lui de vous la transmettre.</p>
			<h3>Et ensuite&nbsp;?</h3>
			<p>La rédaction harmonise la forme des notices sans vous consulter&nbsp;; elle ne vous écrira que pour une question de fond.</p>
			<p>Pas de copie d’ici une heure&nbsp;? Regardez dans vos courriers indésirables, puis écrivez à
				<?php if ( '' !== $redaction ) : ?>
					<a href="mailto:<?php echo esc_attr( antispambot( $redaction ) ); ?>"><?php echo esc_html( antispambot( $redaction ) ); ?></a><?php else : ?>la rédaction de la revue<?php endif; ?><?php echo '' !== $ref ? ' en citant la référence.' : '.'; ?></p>
			<p class="na-fin-autre"><a href="<?php echo esc_url( $autre ); ?>">Déposer une autre notice</a></p>
		</div>
		<script>
		// La page du formulaire qui vient d'être déposée : si l'on y revient
		// par Précédent, elle se sait partie, et ne se renvoie pas.
		try {
			var naEnvoi = window.sessionStorage.getItem('na_envoi');
			if (naEnvoi) {
				window.sessionStorage.setItem('na_depose', JSON.stringify({ page: naEnvoi, ref: <?php echo wp_json_encode( $ref ); ?> }));
				window.sessionStorage.removeItem('na_envoi');
			}
		} catch (e) {}
		document.addEventListener('DOMContentLoaded', function () {
			var fin = document.getElementById('na-fin');
			if (fin) { fin.focus(); }
			// La notice est partie : le brouillon gardé sur l'appareil n'a plus
			// d'objet, et le proposer au prochain dépôt serait une erreur.
			try { window.localStorage.removeItem('na_brouillon_' + window.location.pathname); } catch (e) {}
		});
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Un entier à la française, les milliers séparés par une insécable :
	 * « 1 772 ». number_format_i18n() suit la langue du site, et un site en
	 * anglais aurait écrit « 1,772 ».
	 */
	private static function entier( $n ) {
		return number_format( (float) $n, 0, ',', "\u{00A0}" );
	}

	/**
	 * La norme des illustrations, dite dans l'aide d'après les constantes que
	 * le contrôle emploie : l'aide annonçait « 10 × 15 cm à 300 ppp » en dur,
	 * et rien ne l'aurait fait suivre si la norme avait changé.
	 */
	private function norme_des_illustrations() {
		list( $petit, $grand ) = Notice_Archeomed_Controles::pixels_de_la_norme();
		return sprintf( 'Photographies : %1$s × %2$s cm au moins à %3$s ppp, soit %4$s × %5$s pixels ; dessins au trait : %6$s ppp.',
			self::entier( Notice_Archeomed_Normes::valeur( 'photo_largeur_cm' ) ),
			self::entier( Notice_Archeomed_Normes::valeur( 'photo_hauteur_cm' ) ),
			self::entier( Notice_Archeomed_Normes::valeur( 'photo_ppp' ) ),
			self::entier( $petit ), self::entier( $grand ),
			self::entier( Notice_Archeomed_Normes::valeur( 'trait_ppp' ) ) );
	}

	/**
	 * Le formulaire de dépôt.
	 *
	 * Soixante-quinze commandes se suivaient à plat, sans introduction ni
	 * section, le facultatif tout déployé. Elles se rangent maintenant en six
	 * sections numérotées, et ce qui ne sert qu'à certains — co-responsable,
	 * co-auteur, lieux et organismes supplémentaires, renvois — s'ouvre à la
	 * demande. Les noms des champs n'ont pas changé : le serveur reçoit
	 * exactement ce qu'il recevait. Sans script, rien n'est replié.
	 */
	public function render_form() {
		// Le dépôt a abouti : on le dit, et l'on s'arrête là.
		if ( isset( $_GET['notice_envoyee'] ) && '1' === $_GET['notice_envoyee'] ) {
			return $this->panneau_de_fin();
		}
		$reprise    = $this->reprise();
		$correction = ! empty( $reprise['remplace'] );
		$plafonds   = $this->plafonds_des_fichiers();
		// Ce qu'on annonce ne promet jamais plus que ce que le serveur
		// accepte : trois fichiers plafonnés chacun ne pèsent pas davantage
		// que trois fois ce plafond, et les chiffres s'arrondissent par
		// défaut. L'introduction taisait la limite par fichier.
		$dixieme     = MB_IN_BYTES / 10;
		$total_illus = min( $plafonds['total'], self::MAX_FILES * $plafonds['fichier'] );
		$total_mo    = $this->en_mo( floor( $total_illus / $dixieme ) * $dixieme );
		$fichier_mo  = $this->en_mo( floor( $plafonds['fichier'] / $dixieme ) * $dixieme );
		$par_fichier = ( $plafonds['fichier'] < $total_illus )
			? ', ' . $fichier_mo . '&nbsp;Mo par fichier' : '';
		ob_start();
		?>
		<style>
			/* ══ Le formulaire de dépôt ════════════════════════════════════════
			   Toutes les règles partent de « .na-form#na-form » : la classe pour
			   le préfixe, l'identifiant pour le poids. Hueman repeignait le
			   bouton d'envoi (« .themeform button[type=submit] »), grisait le
			   texte saisi (#777, sous le contraste exigé) et effaçait le focus ;
			   aucun de ses sélecteurs ne l'emporte plus sans !important. */
			.na-form#na-form {
				--na-encre: #1f1a17; --na-sourd: #5b534b; --na-indice: #6f675f;
				--na-filet: #857c73; --na-filet-doux: #e2dbd0;
				--na-papier: #fff; --na-fond: #faf8f4; --na-surface: #f3efe7;
				--na-accent: #7d1a1d; --na-accent-fonce: #5e1215; --na-accent-voile: #f5eaea;
				--na-erreur: #b3261e; --na-erreur-fond: #fcefed;
				--na-succes: #2d6a33; --na-succes-fond: #edf5ee;
				--na-avis: #7a4d00; --na-avis-bord: #b07a1a; --na-avis-fond: #fdf5e4;
				--na-inerte: #6b645d; --na-inerte-fond: #ece8e1;
				--na-t-s: max(14px, .875rem); --na-t-m: max(16px, 1rem);
				--na-t-l: max(18px, 1.125rem); --na-t-xl: max(24px, 1.5rem);
				--na-serif: Georgia, "Iowan Old Style", "Palatino Linotype", "Book Antiqua", serif;
				--na-e1: .25rem; --na-e2: .5rem; --na-e3: .75rem; --na-e4: 1rem;
				--na-e5: 1.5rem; --na-e6: 2rem; --na-e7: 3rem;
				--na-r1: 3px; --na-r2: 6px; --na-cible: 2.75rem; --na-mesure: 68ch;
				--na-anneau: 3px solid var(--na-accent);
				position: relative; max-width: 60rem; margin: 0 0 var(--na-e7); padding: 0;
				font-size: var(--na-t-m); font-weight: 400; line-height: 1.5; color: var(--na-encre);
			}
			.na-form#na-form *, .na-form#na-form *::before, .na-form#na-form *::after { box-sizing: border-box; }
			.na-form#na-form [hidden] { display: none !important; }
			.na-form#na-form p { margin: 0; color: inherit; }
			.na-form#na-form ul, .na-form#na-form ol, .na-form#na-form li { margin: 0; padding: 0; list-style: none; }
			.na-form#na-form a { color: var(--na-accent); text-decoration: underline; text-underline-offset: .18em; }
			.na-form#na-form .na-sr { position: absolute !important; width: 1px; height: 1px; margin: -1px; padding: 0;
				overflow: hidden; clip: rect(0 0 0 0); clip-path: inset(50%); white-space: nowrap; border: 0; }

			/* ── Introduction ── */
			.na-form#na-form .na-intro { margin: 0 0 var(--na-e5); padding: var(--na-e4) var(--na-e5);
				font-size: var(--na-t-s); line-height: 1.55; background: var(--na-surface);
				border-left: 3px solid var(--na-accent); border-radius: 0 var(--na-r2) var(--na-r2) 0; }
			.na-form#na-form .na-intro p + p, .na-form#na-form .na-intro ul + p { margin-top: var(--na-e2); }
			.na-form#na-form .na-intro ul { margin: var(--na-e1) 0 0; }
			.na-form#na-form .na-intro li { position: relative; margin: var(--na-e1) 0 0; padding-left: 1.1em; }
			.na-form#na-form .na-intro li::before { content: "–"; position: absolute; left: 0; color: var(--na-sourd); }

			/* ── Sections numérotées ── */
			.na-form#na-form .na-section { margin-top: var(--na-e7); padding-top: var(--na-e5); border-top: 1px solid var(--na-filet-doux); }
			.na-form#na-form .na-section:first-of-type { margin-top: var(--na-e5); }
			.na-form#na-form h2.na-section-titre { display: flex; align-items: baseline; gap: var(--na-e3);
				margin: 0 0 var(--na-e2); padding: 0; font-family: inherit; font-size: var(--na-t-xl);
				font-weight: 600; line-height: 1.25; letter-spacing: -.005em; color: var(--na-encre); text-transform: none; }
			.na-form#na-form h2.na-section-titre .na-section-num { flex: none; min-width: 1.1em; font-family: var(--na-serif);
				font-style: italic; font-weight: 400; font-size: 1.45em; line-height: 1; color: var(--na-accent); }
			.na-form#na-form h2.na-section-titre .na-facultatif { font-size: var(--na-t-m); }
			.na-form#na-form fieldset { min-width: 0; margin: 0; padding: 0; border: 0; }
			.na-form#na-form legend { float: left; width: 100%; margin: 0 0 var(--na-e1); padding: 0; color: var(--na-encre); }
			.na-form#na-form fieldset > legend + * { clear: both; }
			.na-form#na-form .na-sous-groupe { margin-top: var(--na-e5); }
			/* Section à 21 px, sous-groupe à 18, libellé à 16, tous en demi-gras :
			   « Le titre de la notice » et « Commune ou territoire » se
			   confondaient. La section grandit, le sous-groupe passe en petites
			   capitales espacées, et le libellé reste ce qu'on lit. */
			.na-form#na-form .na-sous-groupe > legend,
			.na-form#na-form h3.na-sous-titre { margin: 0 0 var(--na-e1); padding: 0; font-family: inherit;
				font-size: var(--na-t-s); font-weight: 700; line-height: 1.4; letter-spacing: .08em; color: var(--na-sourd); text-transform: uppercase; }
			.na-form#na-form .na-sous-groupe > legend .na-facultatif,
			.na-form#na-form h3.na-sous-titre .na-facultatif { font-weight: 400; letter-spacing: normal; text-transform: none; }
			.na-form#na-form .na-sous-groupe > legend { margin-bottom: var(--na-e2); }
			/* « Nature de l'opération » est une question, non un regroupement :
			   elle garde l'allure d'un libellé. */
			.na-form#na-form .na-sous-groupe.na-champ > legend { font-size: var(--na-t-m); font-weight: 600; letter-spacing: normal;
				color: var(--na-encre); text-transform: none; }
			.na-form#na-form .na-bloc { padding: var(--na-e3) 0 var(--na-e1) var(--na-e4); border-left: 3px solid var(--na-filet-doux); }

			/* ── Champ : libellé, aide, saisie, erreur ── */
			.na-form#na-form .na-champ { margin-top: var(--na-e5); }
			.na-form#na-form .na-sous-groupe .na-champ { margin-top: var(--na-e4); }
			.na-form#na-form label, .na-form#na-form .na-libelle { display: block; margin: 0 0 var(--na-e1); padding: 0;
				font-size: var(--na-t-m); font-weight: 600; line-height: 1.35; color: var(--na-encre);
				text-transform: none; letter-spacing: normal; }
			.na-form#na-form .na-facultatif { font-weight: 400; color: var(--na-sourd); }
			.na-form#na-form .na-help { max-width: var(--na-mesure); margin: 0 0 var(--na-e2);
				font-size: var(--na-t-s); font-weight: 400; line-height: 1.5; color: var(--na-sourd); }
			.na-form#na-form .na-help strong { font-weight: 600; color: var(--na-encre); }
			.na-form#na-form input[type=text], .na-form#na-form input[type=email], .na-form#na-form input[type=url],
			.na-form#na-form select, .na-form#na-form textarea {
				display: block; width: 100%; max-width: 100%; min-height: var(--na-cible); margin: 0;
				padding: .5625rem .75rem; font: inherit; font-size: var(--na-t-m); line-height: 1.5; color: var(--na-encre);
				background-color: var(--na-papier); border: 1px solid var(--na-filet); border-radius: var(--na-r1);
				box-shadow: none; text-shadow: none; -webkit-appearance: none; appearance: none;
				transition: border-color .15s ease; }
			.na-form#na-form textarea { min-height: 6.5rem; resize: vertical; }
			.na-form#na-form select { padding-right: 2.5rem; cursor: pointer;
				background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' fill='none' stroke='%235b534b' stroke-width='2'/%3E%3C/svg%3E");
				background-repeat: no-repeat; background-position: right .875rem center; background-size: 12px 8px; }
			.na-form#na-form ::placeholder { color: var(--na-indice); opacity: 1; }
			.na-form#na-form input[type=text]:hover, .na-form#na-form input[type=email]:hover,
			.na-form#na-form select:hover, .na-form#na-form textarea:hover { border-color: var(--na-encre); }
			.na-form#na-form input[type=text]:focus, .na-form#na-form input[type=email]:focus,
			.na-form#na-form select:focus, .na-form#na-form textarea:focus {
				border-color: var(--na-accent); outline: var(--na-anneau); outline-offset: 2px; box-shadow: none; color: var(--na-encre); }
			/* Un seul filet rouge, posé dans la marge. Le bloc en portait un et le
			   champ un second, parallèle ; et le bloc glissait de seize pixels à
			   chaque erreur, puis revenait quand elle s'effaçait. */
			.na-form#na-form [aria-invalid="true"]:not(fieldset) { border-color: var(--na-erreur); box-shadow: 0 0 0 1px var(--na-erreur); }
			.na-form#na-form .na-champ--erreur { position: relative; }
			.na-form#na-form .na-champ--erreur::before { content: ""; position: absolute; top: 0; bottom: 0;
				left: calc(-1 * (var(--na-e3) + 4px)); width: 0; border-left: 4px solid var(--na-erreur); border-radius: 2px; }
			.na-form#na-form .na-erreur-champ { display: flex; gap: .45em; align-items: flex-start; max-width: var(--na-mesure);
				margin: 0 0 var(--na-e2); font-size: var(--na-t-s); font-weight: 600; line-height: 1.4; color: var(--na-erreur); }
			.na-form#na-form .na-erreur-champ::before { content: "!"; flex: none; display: inline-grid; place-items: center;
				width: 1.3em; height: 1.3em; margin-top: .05em; border-radius: 50%; font-size: .85em; font-weight: 700;
				color: #fff; background: var(--na-erreur); }
			.na-form#na-form .na-auto { margin-top: var(--na-e1); font-size: var(--na-t-s); color: var(--na-avis); }
			.na-form#na-form .na-auto:empty { display: none; }
			.na-form#na-form .na-half { display: grid; gap: 0 var(--na-e5);
				grid-template-columns: repeat(auto-fit, minmax(min(100%, 16rem), 1fr)); }
			.na-form#na-form .na-half > * { min-width: 0; }
			/* Côte à côte, deux aides de longueur inégale décalaient les champs :
			   la saisie se cale en bas de sa colonne. */
			.na-form#na-form .na-half > .na-champ { display: flex; flex-direction: column; }
			.na-form#na-form .na-half > .na-champ > :is(input, select, .na-erreur-champ) { margin-top: auto; }
			.na-form#na-form .na-half > .na-champ > .na-erreur-champ + :is(input, select) { margin-top: 0; }
			.na-form#na-form .na-court input { max-width: 16rem; }

			/* ── Lignes répétées et blocs repliés ── */
			.na-form#na-form .na-lieu, .na-form#na-form .na-ligne { position: relative; margin-top: var(--na-e3); }
			.na-form#na-form .na-lieu:first-of-type, .na-form#na-form .na-ligne:first-of-type { margin-top: 0; }
			.na-form#na-form .na-lieu label, .na-form#na-form .na-ligne label { font-size: var(--na-t-s); }
			.na-form#na-form .na-combo { position: relative; }
			.na-form#na-form button.na-ajout { display: inline-block; width: auto; max-width: 100%; text-align: left;
				min-height: var(--na-cible); margin: var(--na-e3) 0 0; padding: .5625rem .875rem;
				font: inherit; font-size: var(--na-t-m); font-weight: 600; line-height: 1.3; text-transform: none; letter-spacing: normal;
				color: var(--na-accent); background: var(--na-papier); border: 1px dashed var(--na-accent);
				border-radius: var(--na-r1); box-shadow: none; cursor: pointer; -webkit-appearance: none; appearance: none; }
			.na-form#na-form button.na-ajout:hover { background: var(--na-accent-voile); border-style: solid; }
			.na-form#na-form button.na-ajout + .na-help { margin-top: var(--na-e1); }
			.na-form#na-form button.na-retirer, .na-form#na-form button.na-fileremove,
			.na-form#na-form button.na-puzzle-rejouer, .na-form#na-form button.na-pactols-remplacer,
			.na-form#na-form button.na-bouton-lien {
				display: inline-flex; align-items: center; gap: .3em; width: auto; min-height: 2rem; margin: var(--na-e1) 0 0;
				padding: .25rem .125rem; font: inherit; font-size: var(--na-t-s); font-weight: 600; line-height: 1.3;
				text-transform: none; letter-spacing: normal; color: var(--na-accent); background: none; border: 0;
				border-radius: var(--na-r1); text-decoration: underline; text-underline-offset: .2em; box-shadow: none;
				cursor: pointer; -webkit-appearance: none; appearance: none; }
			.na-form#na-form button.na-retirer:hover, .na-form#na-form button.na-fileremove:hover,
			.na-form#na-form button.na-puzzle-rejouer:hover, .na-form#na-form button.na-pactols-remplacer:hover,
			.na-form#na-form button.na-bouton-lien:hover { color: var(--na-accent-fonce); background: none; text-decoration-thickness: 2px; }
			.na-form#na-form button:focus-visible, .na-form#na-form input[type=checkbox]:focus-visible { outline: var(--na-anneau); outline-offset: 2px; }

			/* ── Aperçu du titre ── */
			.na-form#na-form .na-apercu { margin-top: var(--na-e5); padding: var(--na-e3) var(--na-e4);
				background: var(--na-fond); border-left: 3px solid var(--na-accent); border-radius: 0 var(--na-r1) var(--na-r1) 0; }
			.na-form#na-form .na-apercu-mot { font-size: var(--na-t-s); color: var(--na-sourd); }
			.na-form#na-form .na-apercu-titre { margin-top: var(--na-e1); font-size: var(--na-t-l); font-weight: 400; line-height: 1.35; overflow-wrap: anywhere; }
			.na-form#na-form .na-apercu-titre em { font-style: italic; font-weight: 400; }
			.na-form#na-form .na-apercu-titre .na-manque { font-weight: 400; color: var(--na-indice); }

			/* ── Nature de l'opération ── */
			.na-form#na-form .na-familles { display: grid; gap: var(--na-e3); }
			.na-form#na-form .na-famille > legend { margin: 0; font-size: var(--na-t-s); font-weight: 600; color: var(--na-sourd); }
			.na-form#na-form .na-checkboxes { display: grid; gap: 0 var(--na-e5);
				grid-template-columns: repeat(auto-fill, minmax(min(100%, 15rem), 1fr)); }
			.na-form#na-form label.na-check { display: flex; align-items: center; gap: var(--na-e3); min-height: var(--na-cible);
				margin: 0; padding: var(--na-e1) var(--na-e2); font-weight: 400; line-height: 1.35; border-radius: var(--na-r1); cursor: pointer; }
			.na-form#na-form label.na-check:hover { background: var(--na-fond); }
			.na-form#na-form input[type=checkbox] { -webkit-appearance: auto; appearance: auto; flex: none;
				width: 1.25rem; height: 1.25rem; margin: 0; accent-color: var(--na-accent); cursor: pointer; }

			/* ── Autocomplétion ── */
			.na-form#na-form .na-suggestions, .na-form#na-form .na-pactols-suggestions {
				position: absolute; z-index: 60; top: calc(100% + 2px); left: 0; right: 0;
				max-height: min(22rem, 55vh); overflow-y: auto; overscroll-behavior: contain;
				margin: 0; padding: var(--na-e1) 0; background: var(--na-papier);
				border: 1px solid var(--na-filet); border-radius: var(--na-r1); box-shadow: 0 8px 24px rgba(31, 26, 23, .16); }
			.na-form#na-form .na-suggestions > li, .na-form#na-form .na-pactols-suggestions > li {
				display: flex; flex-wrap: wrap; align-items: center; min-height: var(--na-cible); margin: 0;
				padding: .5rem .75rem .5rem calc(.75rem - 3px); font-size: var(--na-t-m); line-height: 1.35;
				color: var(--na-encre); border-left: 3px solid transparent; cursor: pointer; list-style: none; }
			.na-form#na-form .na-suggestions > li:hover, .na-form#na-form .na-pactols-suggestions > li:hover { background: var(--na-fond); }
			.na-form#na-form .na-suggestions > li[aria-selected="true"], .na-form#na-form .na-pactols-suggestions > li[aria-selected="true"] {
				background: var(--na-accent-voile); border-left-color: var(--na-accent); color: var(--na-accent); font-weight: 600; }
			.na-form#na-form .na-suggestion-precision { margin-left: .35em; font-weight: 400; color: var(--na-sourd); }

			/* ── Éditeur ── */
			.na-form#na-form .na-editeur { margin-top: var(--na-e2); }
			.na-form#na-form .ql-toolbar.ql-snow { display: flex; flex-wrap: wrap; gap: var(--na-e1); padding: var(--na-e1) var(--na-e2);
				font-family: inherit; background: var(--na-fond); border: 1px solid var(--na-filet);
				border-bottom-color: var(--na-filet-doux); border-radius: var(--na-r1) var(--na-r1) 0 0; }
			.na-form#na-form .ql-toolbar.ql-snow .ql-formats { display: flex; gap: 2px; margin: 0 var(--na-e3) 0 0; }
			.na-form#na-form .ql-toolbar.ql-snow button { float: none; width: 2.25rem; height: 2.25rem; padding: .4375rem;
				border-radius: var(--na-r1); background: none; color: var(--na-encre); }
			.na-form#na-form .ql-toolbar.ql-snow button:hover { background: var(--na-surface); }
			.na-form#na-form .ql-toolbar.ql-snow button.ql-active { background: var(--na-accent-voile); }
			.na-form#na-form .ql-snow .ql-stroke { stroke: var(--na-encre); }
			.na-form#na-form .ql-snow .ql-fill { fill: var(--na-encre); }
			.na-form#na-form .ql-snow button:hover .ql-stroke, .na-form#na-form .ql-snow button.ql-active .ql-stroke { stroke: var(--na-accent); }
			.na-form#na-form .ql-snow button:hover .ql-fill, .na-form#na-form .ql-snow button.ql-active .ql-fill { fill: var(--na-accent); }
			/* La hauteur fixe de 260 px faisait un défilement dans le défilement
			   sur téléphone : l'éditeur grandit avec le texte. */
			.na-form#na-form #na-editor.ql-container.ql-snow { height: auto; font-family: inherit; font-size: var(--na-t-m);
				background: var(--na-papier); border: 1px solid var(--na-filet); border-top: 0; border-radius: 0 0 var(--na-r1) var(--na-r1); }
			.na-form#na-form #na-editor.ql-container.ql-snow:focus-within { outline: var(--na-anneau); outline-offset: 2px; border-color: var(--na-accent); }
			.na-form#na-form #na-editor .ql-editor { min-height: 20rem; padding: var(--na-e4);
				font-size: var(--na-t-m); line-height: 1.65; color: var(--na-encre); }
			.na-form#na-form #na-editor .ql-editor p { margin: 0; color: inherit; }
			.na-form#na-form #na-editor .ql-editor .na-pc { font-variant: small-caps; }
			.na-form#na-form .ql-toolbar.ql-snow button.ql-petitescapitales { width: auto; min-width: 2.25rem; padding: 0 .375rem;
				font: inherit; font-size: var(--na-t-m); font-weight: 700; font-variant: small-caps; color: var(--na-encre); }
			.na-form#na-form .ql-toolbar.ql-snow button.ql-petitescapitales:hover,
			.na-form#na-form .ql-toolbar.ql-snow button.ql-petitescapitales.ql-active { color: var(--na-accent); }
			.na-form#na-form #na-editor .ql-editor.ql-blank::before { left: var(--na-e4); right: var(--na-e4); font-style: normal; color: var(--na-indice); }
			.na-form#na-form .na-texte--erreur #na-editor.ql-container.ql-snow,
			.na-form#na-form .na-texte--erreur .ql-toolbar.ql-snow { border-color: var(--na-erreur); }
			.na-form#na-form .na-wordcount { margin-top: var(--na-e2); font-size: var(--na-t-s); color: var(--na-sourd); font-variant-numeric: tabular-nums; }
			.na-form#na-form .na-wordcount.na-out { color: var(--na-avis); font-weight: 600; }

			/* ── Mots-clés Pactols ── */
			/* Trois colonnes quand elles tiennent à l'aise (54 rem), une seule
			   sinon : jamais deux plus une, qui laissait un trou. */
			.na-form#na-form .na-pactols-grid { display: grid; gap: var(--na-e4); margin-top: var(--na-e3);
				grid-template-columns: repeat(auto-fit, minmax(min(100%, max(calc((100% - 2rem) / 3), calc((54rem - 100%) * 999))), 1fr)); }
			.na-form#na-form .na-kw { min-width: 0; padding: var(--na-e4); background: var(--na-fond); border: 1px solid var(--na-filet-doux); border-radius: var(--na-r2); }
			.na-form#na-form .na-kw > legend { margin: 0 0 var(--na-e1); font-size: var(--na-t-m); font-weight: 600; line-height: 1.3; }
			.na-form#na-form .na-jetons { display: flex; flex-wrap: wrap; gap: var(--na-e2); margin: 0 0 var(--na-e2); }
			.na-form#na-form .na-jeton { display: inline-flex; align-items: center; gap: .25em; max-width: 100%;
				padding: .1875rem .25rem .1875rem .625rem; font-size: var(--na-t-s); line-height: 1.35;
				background: var(--na-papier); border: 1px solid var(--na-succes); border-radius: 999px; }
			.na-form#na-form .na-jeton-libre { border-style: dashed; border-color: var(--na-filet); }
			.na-form#na-form .na-jeton-texte { min-width: 0; overflow-wrap: break-word; }
			.na-form#na-form .na-jeton-note { color: var(--na-sourd); }
			.na-form#na-form button.na-jeton-retirer { display: inline-grid; place-items: center; flex: none; width: 1.75rem; height: 1.75rem;
				min-height: 0; margin: 0; padding: 0; font: inherit; font-size: 1.1rem; line-height: 1; color: var(--na-sourd);
				background: none; border: 0; border-radius: 50%; box-shadow: none; cursor: pointer; -webkit-appearance: none; appearance: none; }
			.na-form#na-form button.na-jeton-retirer:hover { color: #fff; background: var(--na-accent); }
			.na-form#na-form .na-kw-compte { margin-top: var(--na-e1); font-size: var(--na-t-s); color: var(--na-sourd); font-variant-numeric: tabular-nums; }
			.na-form#na-form .na-kw-etat { margin-top: var(--na-e1); font-size: var(--na-t-s); line-height: 1.45; color: var(--na-avis); }
			.na-form#na-form .na-kw-etat:empty { display: none; }
			.na-form#na-form .na-pactols-avis { margin: var(--na-e2) 0 0; padding: var(--na-e2) var(--na-e3);
				font-size: var(--na-t-s); line-height: 1.45; color: var(--na-avis); background: var(--na-avis-fond);
				border-left: 3px solid var(--na-avis-bord); border-radius: 0 var(--na-r1) var(--na-r1) 0; }

			/* ── Illustrations ── */
			.na-form#na-form .na-depot-fichiers { position: relative; display: grid; justify-items: center; gap: var(--na-e2);
				margin-top: var(--na-e2); padding: var(--na-e5) var(--na-e4); text-align: center; background: var(--na-fond);
				border: 2px dashed var(--na-filet); border-radius: var(--na-r2); }
			/* Le vrai champ couvre toute la zone, invisible : un clic ouvre le
			   sélecteur, un fichier lâché n'importe où y tombe. Cela marche aussi
			   sans script. */
			.na-form#na-form .na-depot-fichiers input[type=file] { position: absolute; top: 0; right: 0; bottom: 0; left: 0; z-index: 1;
				width: 100%; height: 100%; margin: 0; padding: 0; opacity: 0; cursor: pointer; font-size: 16px; }
			.na-form#na-form .na-depot-fichiers:hover, .na-form#na-form .na-depot-fichiers.na-survol { border-color: var(--na-accent); background: var(--na-accent-voile); }
			.na-form#na-form .na-depot-fichiers:focus-within { border-style: solid; border-color: var(--na-accent); outline: var(--na-anneau); outline-offset: 2px; }
			.na-form#na-form .na-depot-invite { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: var(--na-e2) var(--na-e3); }
			.na-form#na-form .na-bouton-faux { display: inline-flex; align-items: center; min-height: var(--na-cible); padding: .5rem 1.25rem;
				font-weight: 600; color: var(--na-accent); background: var(--na-papier); border: 1px solid var(--na-accent); border-radius: var(--na-r1); }
			.na-form#na-form .na-depot-ou { color: var(--na-sourd); font-size: var(--na-t-s); }
			@media (pointer: coarse) { .na-form#na-form .na-depot-ou { display: none; } }
			.na-form#na-form .na-fichiers-refuses:empty { display: none; }
			.na-form#na-form .na-fichiers-refuses { margin-top: var(--na-e2); padding: var(--na-e2) var(--na-e3); font-size: var(--na-t-s);
				color: var(--na-erreur); background: var(--na-erreur-fond); border-left: 3px solid var(--na-erreur); }
			.na-form#na-form .na-filelist { display: grid; gap: var(--na-e3); margin-top: var(--na-e3); }
			.na-form#na-form .na-illus { position: relative; padding: var(--na-e4); background: var(--na-papier);
				border: 1px solid var(--na-filet-doux); border-left: 3px solid var(--na-accent); border-radius: var(--na-r2); }
			.na-form#na-form .na-illus legend { margin: 0 0 var(--na-e2); padding-right: 5.5rem; font-size: var(--na-t-m); font-weight: 600; line-height: 1.35; }
			.na-form#na-form .na-illus-fichier { font-weight: 400; font-size: var(--na-t-s); color: var(--na-sourd); overflow-wrap: anywhere; }
			.na-form#na-form .na-illus button.na-fileremove { position: absolute; top: var(--na-e3); right: var(--na-e3); margin: 0; }
			.na-form#na-form .na-illus-champs { clear: both; display: grid; gap: var(--na-e3); }
			.na-form#na-form .na-illus-champs label { margin: 0; font-size: var(--na-t-s); }
			.na-form#na-form .na-illus-champs .na-help { margin: 0 0 var(--na-e1); }
			.na-form#na-form .na-illus-champs .na-compteur { margin: var(--na-e1) 0 0; }
			.na-form#na-form .na-exemples { max-width: var(--na-mesure); margin: 0 0 var(--na-e1); font-size: var(--na-t-s); line-height: 1.5; color: var(--na-sourd); }
			.na-form#na-form .na-exemples summary { cursor: pointer; font-weight: 600; color: var(--na-encre); }
			.na-form#na-form .na-exemples p { margin: var(--na-e1) 0 0; }
			.na-form#na-form .na-illus-total { font-size: var(--na-t-s); color: var(--na-sourd); font-variant-numeric: tabular-nums; }
			.na-form#na-form .na-illus-vignette { display: block; max-width: 8rem; max-height: 6rem; width: auto; height: auto;
				margin: 0 0 var(--na-e3); border: 1px solid var(--na-filet-doux); border-radius: var(--na-r1); }
			/* Le champ d'autorisation était le seul bouton natif de la page, gris
			   et étranger au reste : il prend l'allure du bouton de dépôt. */
			.na-form#na-form .na-illus-autorisation input[type=file] { display: block; max-width: 100%; font: inherit;
				font-size: var(--na-t-s); color: var(--na-sourd); }
			.na-form#na-form .na-illus-autorisation input[type=file]::file-selector-button { min-height: var(--na-cible);
				margin: 0 var(--na-e3) 0 0; padding: .5rem 1rem; font: inherit; font-size: var(--na-t-m); font-weight: 600;
				color: var(--na-accent); background: var(--na-papier); border: 1px solid var(--na-accent); border-radius: var(--na-r1); cursor: pointer; }
			.na-form#na-form .na-illus-autorisation input[type=file]::-webkit-file-upload-button { min-height: var(--na-cible);
				margin: 0 var(--na-e3) 0 0; padding: .5rem 1rem; font: inherit; font-size: var(--na-t-m); font-weight: 600;
				color: var(--na-accent); background: var(--na-papier); border: 1px solid var(--na-accent); border-radius: var(--na-r1); cursor: pointer; }
			.na-form#na-form .na-illus-autorisation input[type=file]:hover::file-selector-button { background: var(--na-accent-voile); }
			.na-form#na-form .na-illus-autorisation input[type=file]:focus-visible { outline: var(--na-anneau); outline-offset: 2px; }
			/* Les légendes qui attendent leur fichier, après une reprise. */
			.na-form#na-form .na-illus-attente { margin-top: var(--na-e3); padding: var(--na-e3) var(--na-e4); font-size: var(--na-t-s);
				background: var(--na-fond); border: 1px dashed var(--na-filet); border-radius: var(--na-r2); }
			.na-form#na-form .na-illus-attente li { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0 var(--na-e3); margin-top: var(--na-e1); }
			.na-form#na-form .na-illus-attente-nom { font-weight: 600; overflow-wrap: anywhere; }
			/* L'ordre des figures : une poignée pour glisser la carte, Monter et
			   Descendre pour le clavier et le doigt. */
			.na-form#na-form .na-illus-outils { clear: both; display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-start;
				width: 100%; gap: var(--na-e1) var(--na-e3); margin: 0 0 var(--na-e3); }
			.na-form#na-form .na-illus-outils button.na-bouton-lien { margin: 0; }
			.na-form#na-form .na-illus-poignee { display: inline-flex; align-items: center; min-height: 2rem; padding: 0 .5rem;
				font-size: var(--na-t-s); font-weight: 600; color: var(--na-sourd); border: 1px dashed var(--na-filet);
				border-radius: var(--na-r1); cursor: grab; user-select: none; -webkit-user-select: none; }
			@media (pointer: coarse) { .na-form#na-form .na-illus-poignee { display: none; } }
			.na-form#na-form .na-illus-glissee .na-illus { opacity: .55; }
			.na-form#na-form .na-illus-cible .na-illus { outline: 2px dashed var(--na-accent); outline-offset: 2px; }
			.na-form#na-form button.na-ajout-figure { margin-top: var(--na-e3); }

			/* ── Avis : à vérifier, sans rien bloquer ──
			   Distincts des erreurs : ambre et non rouge, un « i » et non un
			   « ! », et jamais dans le récapitulatif. Ils paraissent à la sortie
			   du champ, jamais pendant la frappe. */
			.na-form#na-form .na-conseils { max-width: var(--na-mesure); margin: var(--na-e2) 0 0; padding: var(--na-e2) var(--na-e3);
				font-size: var(--na-t-s); line-height: 1.45; color: var(--na-avis); background: var(--na-avis-fond);
				border-left: 3px solid var(--na-avis-bord); border-radius: 0 var(--na-r1) var(--na-r1) 0; }
			.na-form#na-form .na-conseils-titre { display: flex; gap: .45em; align-items: center; font-weight: 600; }
			.na-form#na-form .na-conseils-titre::before { content: "i"; flex: none; display: inline-grid; place-items: center;
				width: 1.3em; height: 1.3em; border-radius: 50%; font-family: var(--na-serif); font-style: italic; font-size: .9em;
				font-weight: 700; color: #fff; background: var(--na-avis-bord); }
			.na-form#na-form .na-conseils li { position: relative; margin-top: var(--na-e1); padding-left: 1.1em; }
			.na-form#na-form .na-conseils li::before { content: "–"; position: absolute; left: 0; }
			.na-form#na-form .na-conseils button.na-bouton-lien { min-height: 2rem; margin: 0 0 0 var(--na-e1); color: var(--na-accent); }

			/* ── Vérification (pièce de puzzle) ── */
			.na-form#na-form .na-question { padding: var(--na-e4) var(--na-e5); background: var(--na-fond);
				border: 1px solid var(--na-filet-doux); border-radius: var(--na-r2); }
			.na-form#na-form .na-question.na-fait { background: var(--na-succes-fond); border-color: var(--na-succes); }
			.na-form#na-form .na-puzzle-scene { position: relative; max-width: 26.25rem; margin-top: var(--na-e2); border-radius: var(--na-r2); overflow: hidden; line-height: 0; }
			.na-form#na-form .na-puzzle-scene canvas.na-puzzle-fond { display: block; width: 100%; height: auto; }
			.na-form#na-form .na-puzzle-scene canvas.na-puzzle-piece { position: absolute; top: 50%; height: auto; transform: translateY(-50%);
				filter: drop-shadow(0 2px 4px rgba(0, 0, 0, .5)); pointer-events: none; }
			.na-form#na-form .na-puzzle-piste { position: relative; max-width: 26.25rem; height: var(--na-cible); margin-top: var(--na-e3);
				background: var(--na-surface); border: 1px solid var(--na-filet); border-radius: 999px; cursor: pointer; touch-action: pan-y; }
			.na-form#na-form .na-puzzle-poignee { position: absolute; top: 50%; left: 0; display: flex; align-items: center; justify-content: center;
				width: 3.5rem; height: calc(var(--na-cible) - 6px); transform: translate(0, -50%); background: var(--na-papier);
				border: 2px solid var(--na-accent); border-radius: 999px; box-shadow: 0 1px 3px rgba(0, 0, 0, .25); cursor: grab; touch-action: none; }
			.na-form#na-form .na-puzzle-poignee::after { content: ""; width: 22px; height: 12px;
				background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='22' height='12' viewBox='0 0 22 12'%3E%3Cpath d='M6 1L1 6l5 5M16 1l5 5-5 5M1 6h20' fill='none' stroke='%237d1a1d' stroke-width='2'/%3E%3C/svg%3E") center / contain no-repeat; }
			.na-form#na-form .na-puzzle-poignee:focus-visible { outline: var(--na-anneau); outline-offset: 3px; }
			.na-form#na-form .na-prise .na-puzzle-poignee { cursor: grabbing; }
			.na-form#na-form .na-fait .na-puzzle-poignee { border-color: var(--na-succes); cursor: default; }
			.na-form#na-form .na-fait .na-puzzle-poignee::after { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='11' viewBox='0 0 14 11'%3E%3Cpath d='M1 5.5l4 4L13 1' fill='none' stroke='%232d6a33' stroke-width='2.2'/%3E%3C/svg%3E"); }
			.na-form#na-form .na-rate .na-puzzle-scene { animation: na-secousse .3s; }
			@keyframes na-secousse { 25% { transform: translateX(-6px); } 75% { transform: translateX(6px); } }
			.na-form#na-form .na-puzzle-etat { display: block; min-height: 1.5em; margin-top: var(--na-e2); font-size: var(--na-t-s); color: var(--na-sourd); }
			.na-form#na-form .na-fait .na-puzzle-etat { color: var(--na-succes); font-weight: 600; }
			.na-form#na-form .na-turnstile { margin-top: var(--na-e5); }

			/* ── Envoi ── */
			.na-form#na-form .na-envoyer { margin-top: var(--na-e6); padding-top: var(--na-e5); border-top: 1px solid var(--na-filet-doux); }
			/* Le thème l'emportait sur l'ancienne règle (« .themeform
			   button[type=submit] ») : bouton rouge vif, puis gris au survol. */
			.na-form#na-form button.na-submit { display: inline-flex; align-items: center; justify-content: center; width: auto;
				min-height: var(--na-cible); margin: 0; padding: .75rem 2rem; font: inherit; font-size: max(17px, 1.0625rem);
				font-weight: 600; line-height: 1.25; letter-spacing: .01em; text-transform: none; text-decoration: none; text-shadow: none;
				color: #fff; background: var(--na-accent); border: 1px solid var(--na-accent); border-radius: var(--na-r1);
				box-shadow: none; cursor: pointer; -webkit-appearance: none; appearance: none; transition: background-color .15s ease; }
			.na-form#na-form button.na-submit:hover { color: #fff; background: var(--na-accent-fonce); border-color: var(--na-accent-fonce); }
			.na-form#na-form button.na-submit:focus-visible { outline: var(--na-anneau); outline-offset: 3px; }
			.na-form#na-form button.na-submit:disabled { color: #fff; background: var(--na-accent-fonce); border-color: var(--na-accent-fonce); opacity: 1; cursor: progress; }
			.na-form#na-form .na-envoi { margin-top: var(--na-e3); }
			.na-form#na-form .na-envoi-piste { max-width: 24rem; height: 4px; overflow: hidden; background: var(--na-surface); border-radius: 2px; }
			.na-form#na-form .na-envoi-barre { width: 35%; height: 100%; background: var(--na-accent); border-radius: 2px; animation: na-va-et-vient 1.4s ease-in-out infinite; }
			@keyframes na-va-et-vient { 0% { margin-left: -35%; } 100% { margin-left: 100%; } }
			.na-form#na-form .na-envoi-mot { max-width: var(--na-mesure); margin-top: var(--na-e2); font-size: var(--na-t-s); color: var(--na-encre); }
			.na-form#na-form .na-donnees { max-width: var(--na-mesure); margin-top: var(--na-e5); font-size: 13px; line-height: 1.5; color: #555; }

			/* ── Messages ── */
			.na-form#na-form .na-message, .na-form#na-form .na-js-error, .na-form#na-form .na-recap {
				margin: 0 0 var(--na-e5); padding: var(--na-e4) var(--na-e5); font-size: var(--na-t-m); line-height: 1.5;
				color: var(--na-encre); border: 1px solid; border-left-width: 4px; border-radius: var(--na-r2); }
			.na-form#na-form .na-message p + p, .na-form#na-form .na-message ul + p { margin-top: var(--na-e2); }
			.na-form#na-form .na-message.na-error, .na-form#na-form .na-js-error, .na-form#na-form .na-recap { background: var(--na-erreur-fond); border-color: var(--na-erreur); }
			.na-form#na-form .na-message.na-avis { background: var(--na-avis-fond); border-color: var(--na-avis-bord); }
			.na-form#na-form .na-message:focus, .na-form#na-form .na-recap:focus { outline: var(--na-anneau); outline-offset: 2px; }
			.na-form#na-form .na-message h2, .na-form#na-form .na-recap h2 { margin: 0 0 var(--na-e2); padding: 0; font-family: inherit;
				font-size: var(--na-t-l); font-weight: 600; line-height: 1.3; letter-spacing: normal; color: var(--na-erreur); }
			.na-form#na-form .na-message li, .na-form#na-form .na-recap li { margin-top: var(--na-e1); }
			.na-form#na-form .na-message.na-error a, .na-form#na-form .na-recap a { color: var(--na-erreur); font-weight: 600; }
			.na-form#na-form .na-brouillon-choix { display: flex; flex-wrap: wrap; gap: var(--na-e2) var(--na-e4); margin-top: var(--na-e2); }

			/* ── Téléphone ── */
			@media (max-width: 40em) {
				.na-form#na-form .na-section { margin-top: var(--na-e6); }
				.na-form#na-form h2.na-section-titre { font-size: max(21px, 1.3125rem); }
				.na-form#na-form .na-champ--erreur::before { left: calc(-1 * (var(--na-e2) + 4px)); }
				.na-form#na-form .na-question, .na-form#na-form .na-kw, .na-form#na-form .na-intro,
				.na-form#na-form .na-message, .na-form#na-form .na-recap { padding: var(--na-e3) var(--na-e4); }
				.na-form#na-form .na-checkboxes { grid-template-columns: 1fr; }
				.na-form#na-form button.na-submit { width: 100%; }
				.na-form#na-form .na-depot-fichiers { padding: var(--na-e4) var(--na-e3); }
				.na-form#na-form .na-bloc { padding-left: var(--na-e3); }
			}

			/* ── Préférences du système ── */
			@media (prefers-reduced-motion: reduce) {
				.na-form#na-form *, .na-form#na-form *::before, .na-form#na-form *::after {
					animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
				.na-form#na-form .na-envoi-barre { width: 100%; margin-left: 0; animation: none; }
			}
			@media (forced-colors: active) {
				.na-form#na-form .na-puzzle-poignee, .na-form#na-form .na-puzzle-piste, .na-form#na-form .na-depot-fichiers,
				.na-form#na-form .na-illus, .na-form#na-form .na-kw, .na-form#na-form .na-jeton { border-color: CanvasText; }
				.na-form#na-form .na-suggestions > li[aria-selected="true"], .na-form#na-form .na-pactols-suggestions > li[aria-selected="true"] {
					forced-color-adjust: none; color: HighlightText; background: Highlight; }
			}
		</style>
		<form class="na-form" id="na-form" method="post" enctype="multipart/form-data"
			data-reprise="<?php echo empty( $reprise ) ? '0' : '1'; ?>"
			data-brouillon="<?php echo $correction ? '0' : '1'; ?>">
			<?php
			// Déjà échappé pièce à pièce.
			echo $this->messages_du_retour();
			// Un lien de correction déjà servi ou expiré rouvrait un formulaire
			// vierge, sans un mot : l'auteur retapait tout, et déposait une
			// notice neuve que rien ne reliait à la sienne.
			$lien = isset( $_GET['notice_envoyee'] ) ? array( 'etat' => '' ) : $this->etat_du_lien_de_reprise();
			if ( 'utilise' === $lien['etat'] ) :
				?>
				<div class="na-message na-avis" id="na-lien-utilise" role="alert"><p><?php
					echo $this->typo( '<strong>Ce lien de correction a déjà servi</strong> : la notice corrigée a été déposée sous la référence <strong>'
						. esc_html( $lien['reference'] ) . '</strong>. Pour la corriger encore, suivez le lien de la copie reçue pour '
						. 'cette correction, ou écrivez à la rédaction en citant cette référence. Envoyé d’ici, ce formulaire ferait '
						. 'un dépôt nouveau, sans lien avec le précédent.' );
				?></p></div>
				<?php
			elseif ( 'expire' === $lien['etat'] ) :
				$contact = $this->adresse_de_contact();
				?>
				<div class="na-message na-avis" id="na-lien-expire" role="alert"><p><?php
					echo $this->typo( '<strong>Ce lien de correction a expiré</strong> (il vaut un mois) ou est incomplet. Pour corriger '
						. 'votre notice, écrivez à ' . ( '' !== $contact
							? '<a href="mailto:' . esc_attr( antispambot( $contact ) ) . '">' . esc_html( antispambot( $contact ) ) . '</a>'
							: 'la rédaction' )
						. ' en citant sa référence : un dépôt fait d’ici ne remplacerait pas l’ancien.' );
				?></p></div>
				<?php
			endif;
			?>
			<div class="na-recap" id="na-recap" tabindex="-1" hidden></div>
			<div class="na-js-error" id="na-js-error" role="alert" hidden></div>
			<div class="na-intro" id="na-intro">
				<p>Ce formulaire transmet votre notice à la rédaction de la Chronique d’<em>Archéologie médiévale</em>. Comptez une vingtaine de minutes si votre texte est prêt.</p>
				<p><strong>Ayez sous la main&nbsp;:</strong></p>
				<ul>
					<li>le texte de la notice (<?php echo esc_html( self::entier( Notice_Archeomed_Normes::valeur( 'mots_min' ) ) ); ?> à <?php echo esc_html( self::entier( Notice_Archeomed_Normes::valeur( 'mots_max' ) ) ); ?>&nbsp;mots), à coller depuis votre traitement de texte&nbsp;;</li>
					<li>l’année et la nature de l’opération, et, si vous les connaissez, son numéro d’autorisation et son identifiant Patriarche&nbsp;;</li>
					<li>jusqu’à trois illustrations (JPEG, TIFF ou PDF, <?php echo esc_html( $total_mo ); ?>&nbsp;Mo en tout<?php echo $par_fichier; // Chiffre calculé, entités seulement. ?>), avec leurs légendes et crédits.</li>
				</ul>
				<p>Tous les champs sont obligatoires, sauf ceux marqués «&nbsp;facultatif&nbsp;».<?php if ( ! $correction ) : ?> Votre saisie est gardée sur cet appareil jusqu’à l’envoi.<?php endif; ?> Après l’envoi, le responsable d’opération — et, s’il y a lieu, le co-responsable et le co-auteur — reçoit une copie par courriel, avec un lien pour corriger la notice pendant un mois.</p>
			</div>
			<p class="na-sr" id="na-annonce" role="status" aria-live="polite"></p>
			<?php wp_nonce_field( 'notice_archeomed_submit', 'notice_archeomed_nonce' ); ?>
			<div aria-hidden="true" style="position:absolute!important;left:-9999px!important;top:-9999px!important;height:0;overflow:hidden;">
				<label>Ne pas remplir ce champ
					<input type="text" name="na_website" tabindex="-1" autocomplete="off" value="">
				</label>
			</div>
			<input type="hidden" name="na_ts" value="<?php echo esc_attr( time() ); ?>">
			<input type="hidden" name="reprise_jeton" value="<?php echo esc_attr( $this->jeton_de_reprise() ); ?>">

			<section class="na-section" aria-labelledby="na-s1">
				<h2 class="na-section-titre" id="na-s1"><span class="na-section-num" aria-hidden="true">1</span> L’opération</h2>
				<fieldset class="na-sous-groupe" id="na-titre">
					<legend>Le titre de la notice</legend>
					<div class="na-champ">
						<label for="na-lieu-1">Commune ou territoire de l’opération</label>
						<p class="na-help" id="na-lieu-aide">Commencez à taper, puis choisissez dans la liste&nbsp;: le lieu reçoit ainsi son identifiant Pactols. S’il n’y figure pas, écrivez-le simplement.</p>
						<?php
						$lieux_repris = $this->repris_liste( 'commune' );
						$arks_repris  = $this->repris_liste( 'commune_ark' );
						for ( $rang = 0; $rang < self::MAX_LIEUX; $rang++ ) :
							$valeur = isset( $lieux_repris[ $rang ] ) ? $lieux_repris[ $rang ] : '';
							$ark    = isset( $arks_repris[ $rang ] ) ? $arks_repris[ $rang ] : '';
							$n      = $rang + 1;
							?>
							<div class="na-lieu<?php echo $rang ? ' na-repli' : ''; ?>"<?php echo $rang ? ' data-repli="lieu"' : ''; ?>>
								<?php if ( $rang ) : ?>
									<label for="na-lieu-<?php echo (int) $n; ?>">Lieu <?php echo (int) $n; ?> <span class="na-facultatif">(facultatif)</span></label>
								<?php endif; ?>
								<div class="na-combo na-ancre">
									<input type="text" id="na-lieu-<?php echo (int) $n; ?>" name="commune[]" class="na-commune"
										autocomplete="off" spellcheck="false"<?php echo 0 === $rang ? ' required aria-describedby="na-lieu-aide"' : ''; ?>
										value="<?php echo esc_attr( $valeur ); ?>">
									<ul class="na-suggestions" id="na-lieu-<?php echo (int) $n; ?>-liste" hidden></ul>
								</div>
								<input type="hidden" name="commune_ark[]" class="na-commune-ark" value="<?php echo esc_attr( $ark ); ?>">
								<?php if ( $rang ) : ?>
									<button type="button" class="na-retirer" hidden>Retirer ce lieu</button>
								<?php endif; ?>
							</div>
						<?php endfor; ?>
						<button type="button" class="na-ajout" data-ajoute="lieu" aria-describedby="na-lieu-plus-aide" hidden><span aria-hidden="true">+</span> Ajouter un autre lieu</button>
						<p class="na-help" id="na-lieu-plus-aide">Pour une opération sur plusieurs communes, dans l’ordre où elles doivent paraître (cinq au plus).</p>
					</div>
					<div class="na-champ">
						<label for="na-departement">Département ou région</label>
						<p class="na-help" id="na-departement-aide">Le plus souvent le département&nbsp;: Calvados. Ce peut être une ou plusieurs régions&nbsp;: Champagne, Alsace, Lorraine. Rempli d’après la commune choisie&nbsp;; vérifiez-le. Une région nommée ici n’est pas indexée&nbsp;: ajoutez-la aussi dans «&nbsp;Autres lieux&nbsp;» (section&nbsp;4).</p>
						<input type="text" id="na-departement" name="departement" required
							aria-describedby="na-departement-aide na-departement-auto"
							value="<?php echo esc_attr( $this->repris( 'departement' ) ); ?>">
						<p class="na-auto" id="na-departement-auto" aria-live="polite"></p>
					</div>
					<div class="na-champ">
						<label for="na-lieu-dit">Lieu-dit, adresse ou nom du projet</label>
						<p class="na-help" id="na-lieu-dit-aide">Par exemple&nbsp;: Château, salle de l’Échiquier&nbsp;; 12, rue des Carmes&nbsp;; ou le titre d’un projet collectif de recherche.</p>
						<input type="text" id="na-lieu-dit" name="lieu_dit" required
							aria-describedby="na-lieu-dit-aide na-apercu"
							value="<?php echo esc_attr( $this->repris( 'lieu_dit' ) ); ?>">
					</div>
					<div class="na-apercu" id="na-apercu" hidden>
						<p class="na-apercu-mot">Votre notice paraîtra sous le titre&nbsp;:</p>
						<p class="na-apercu-titre" id="na-apercu-titre"></p>
					</div>
				</fieldset>

				<?php echo $this->natures_html(); ?>

				<div class="na-champ na-court">
					<label for="na-annee">Année de l’opération</label>
					<p class="na-help" id="na-annee-aide">Par exemple&nbsp;: 2024, ou 2004-2005 pour une opération sur plusieurs années.</p>
					<input type="text" id="na-annee" name="annee" required aria-describedby="na-annee-aide"
						value="<?php echo esc_attr( $this->repris( 'annee' ) ); ?>">
				</div>

				<div class="na-champ">
					<label for="na-organisme-1">Organisme qui gère l’opération</label>
					<p class="na-help" id="na-organisme-aide">Par exemple&nbsp;: Inrap, service archéologique départemental, université, CNRS, opérateur privé. Les autres partenaires scientifiques se citent dans le texte.</p>
					<?php
					$organismes_repris = $this->repris_liste( 'organisme' );
					for ( $rang = 0; $rang < self::MAX_ORGANISMES; $rang++ ) :
						$valeur = isset( $organismes_repris[ $rang ] ) ? $organismes_repris[ $rang ] : '';
						$n      = $rang + 1;
						?>
						<div class="na-ligne na-ancre<?php echo $rang ? ' na-repli' : ''; ?>"<?php echo $rang ? ' data-repli="organisme"' : ''; ?>>
							<?php if ( $rang ) : ?>
								<label for="na-organisme-<?php echo (int) $n; ?>">Organisme <?php echo (int) $n; ?> <span class="na-facultatif">(facultatif)</span></label>
							<?php endif; ?>
							<input type="text" id="na-organisme-<?php echo (int) $n; ?>" name="organisme[]" class="na-organisme"
								<?php echo 0 === $rang ? 'required aria-describedby="na-organisme-aide"' : ''; ?>
								value="<?php echo esc_attr( $valeur ); ?>">
							<?php if ( $rang ) : ?>
								<button type="button" class="na-retirer" hidden>Retirer cet organisme</button>
							<?php endif; ?>
						</div>
					<?php endfor; ?>
					<button type="button" class="na-ajout" data-ajoute="organisme" aria-describedby="na-organisme-plus-aide" hidden><span aria-hidden="true">+</span> Ajouter un organisme</button>
					<p class="na-help" id="na-organisme-plus-aide">Quand la gestion de l’opération est partagée (trois organismes au plus).</p>
				</div>

				<div class="na-half">
					<div class="na-champ">
						<label for="na-autorisation">Numéro d’autorisation</label>
						<p class="na-help" id="na-autorisation-aide">Le numéro de l’arrêté qui autorise l’opération.</p>
						<input type="text" id="na-autorisation" name="num_autorisation" aria-describedby="na-autorisation-aide"
							value="<?php echo esc_attr( $this->repris( 'num_autorisation' ) ); ?>">
					</div>
					<div class="na-champ">
						<label for="na-patriarche">Identifiant Patriarche <span class="na-facultatif">(facultatif)</span></label>
						<p class="na-help" id="na-patriarche-aide">Le numéro de l’opération dans la base Patriarche du ministère de la Culture, s’il vous est connu.</p>
						<input type="text" id="na-patriarche" name="id_patriarche" aria-describedby="na-patriarche-aide"
							value="<?php echo esc_attr( $this->repris( 'id_patriarche' ) ); ?>">
					</div>
				</div>

				<div class="na-champ">
					<label for="na-rapport">Adresse du rapport final en ligne <span class="na-facultatif">(facultatif)</span></label>
					<p class="na-help" id="na-rapport-aide">Sur Dolia, HAL ou le site du service régional de l’archéologie. Par exemple&nbsp;: https://hal.science/hal-01234567</p>
					<input type="text" inputmode="url" id="na-rapport" name="rapport_lien" autocomplete="off"
						spellcheck="false" autocapitalize="off" aria-describedby="na-rapport-aide"
						value="<?php echo esc_attr( $this->repris( 'rapport_lien' ) ); ?>">
				</div>
			</section>

			<section class="na-section" aria-labelledby="na-s2">
				<h2 class="na-section-titre" id="na-s2"><span class="na-section-num" aria-hidden="true">2</span> Les responsables</h2>
				<fieldset class="na-sous-groupe" id="na-resp">
					<legend>Responsable d’opération</legend>
					<div class="na-half">
						<div class="na-champ">
							<label for="na-resp-prenom">Prénom</label>
							<input type="text" id="na-resp-prenom" name="resp_prenom" required autocomplete="given-name"
								value="<?php echo esc_attr( $this->repris( 'resp_prenom' ) ); ?>">
						</div>
						<div class="na-champ">
							<label for="na-resp-nom">Nom</label>
							<input type="text" id="na-resp-nom" name="resp_nom" required autocomplete="family-name"
								value="<?php echo esc_attr( $this->repris( 'resp_nom' ) ); ?>">
						</div>
					</div>
					<div class="na-champ">
						<label for="na-resp-email">Adresse électronique</label>
						<p class="na-help" id="na-resp-email-aide">La copie de la notice et le lien pour la corriger partiront à cette adresse. Si vous déposez pour le responsable, c’est lui qui les recevra.</p>
						<input type="email" id="na-resp-email" name="resp_email" required autocomplete="email" spellcheck="false"
							aria-describedby="na-resp-email-aide" value="<?php echo esc_attr( $this->repris( 'resp_email' ) ); ?>">
					</div>
					<div class="na-champ">
						<label for="na-resp-inst">Institution de rattachement</label>
						<p class="na-help" id="na-resp-inst-aide">Telle qu’elle doit paraître après son nom dans la notice&nbsp;: employeur, laboratoire.</p>
						<input type="text" id="na-resp-inst" name="resp_inst" required autocomplete="organization"
							aria-describedby="na-resp-inst-aide" value="<?php echo esc_attr( $this->repris( 'resp_inst' ) ); ?>">
					</div>
				</fieldset>
				<?php
				// Le co-responsable et le co-auteur : mêmes quatre champs, et
				// aucune saisie automatique — ils ne décrivent pas la personne
				// qui remplit, et le navigateur y proposait son identité.
				$personnes = array(
					'coresp'   => array( 'Co-responsable de l’opération', 'Ajouter un co-responsable de l’opération',
						'Retirer ce co-responsable', 'Recevra aussi une copie de la notice.', '' ),
					'coauteur' => array( 'Co-auteur de la notice', 'Ajouter un co-auteur de la notice',
						'Retirer ce co-auteur', 'Recevra aussi une copie de la notice.',
						'Une personne qui a rédigé la notice avec le responsable sans codiriger l’opération.' ),
				);
				foreach ( $personnes as $cle => $textes ) :
					list( $titre, $ajout, $retrait, $aide_courriel, $aide ) = $textes;
					?>
					<button type="button" class="na-ajout" aria-controls="na-<?php echo esc_attr( $cle ); ?>" aria-expanded="true"
						<?php echo '' !== $aide ? 'aria-describedby="na-' . esc_attr( $cle ) . '-aide"' : ''; ?> hidden><span aria-hidden="true">+</span> <?php echo esc_html( $ajout ); ?></button>
					<?php if ( '' !== $aide ) : ?>
						<p class="na-help" id="na-<?php echo esc_attr( $cle ); ?>-aide"><?php echo esc_html( $aide ); ?></p>
					<?php endif; ?>
					<fieldset class="na-sous-groupe na-bloc" id="na-<?php echo esc_attr( $cle ); ?>">
						<legend><?php echo esc_html( $titre ); ?> <span class="na-facultatif">(facultatif)</span></legend>
						<div class="na-half">
							<div class="na-champ">
								<label for="na-<?php echo esc_attr( $cle ); ?>-prenom">Prénom</label>
								<input type="text" id="na-<?php echo esc_attr( $cle ); ?>-prenom" name="<?php echo esc_attr( $cle ); ?>_prenom" autocomplete="off"
									value="<?php echo esc_attr( $this->repris( $cle . '_prenom' ) ); ?>">
							</div>
							<div class="na-champ">
								<label for="na-<?php echo esc_attr( $cle ); ?>-nom">Nom</label>
								<input type="text" id="na-<?php echo esc_attr( $cle ); ?>-nom" name="<?php echo esc_attr( $cle ); ?>_nom" autocomplete="off"
									value="<?php echo esc_attr( $this->repris( $cle . '_nom' ) ); ?>">
							</div>
						</div>
						<div class="na-champ">
							<label for="na-<?php echo esc_attr( $cle ); ?>-email">Adresse électronique</label>
							<p class="na-help" id="na-<?php echo esc_attr( $cle ); ?>-email-aide"><?php echo esc_html( $aide_courriel ); ?></p>
							<input type="email" id="na-<?php echo esc_attr( $cle ); ?>-email" name="<?php echo esc_attr( $cle ); ?>_email" autocomplete="off" spellcheck="false"
								aria-describedby="na-<?php echo esc_attr( $cle ); ?>-email-aide"
								value="<?php echo esc_attr( $this->repris( $cle . '_email' ) ); ?>">
						</div>
						<div class="na-champ">
							<label for="na-<?php echo esc_attr( $cle ); ?>-inst">Institution de rattachement</label>
							<input type="text" id="na-<?php echo esc_attr( $cle ); ?>-inst" name="<?php echo esc_attr( $cle ); ?>_inst" autocomplete="off"
								value="<?php echo esc_attr( $this->repris( $cle . '_inst' ) ); ?>">
						</div>
						<button type="button" class="na-retirer" hidden><?php echo esc_html( $retrait ); ?></button>
					</fieldset>
				<?php endforeach; ?>
			</section>

			<section class="na-section" aria-labelledby="na-s3">
				<h2 class="na-section-titre" id="na-s3"><span class="na-section-num" aria-hidden="true">3</span> Le texte</h2>
				<div class="na-champ">
					<p class="na-libelle" id="na-texte-libelle">Texte de la notice</p>
					<p class="na-help" id="na-texte-aide"><?php echo esc_html( self::entier( Notice_Archeomed_Normes::valeur( 'mots_min' ) ) ); ?> à <?php echo esc_html( self::entier( Notice_Archeomed_Normes::valeur( 'mots_max' ) ) ); ?>&nbsp;mots. Collez-le depuis votre traitement de texte&nbsp;: l’italique, le gras, les exposants et les paragraphes sont conservés&nbsp;; titres et listes deviennent des paragraphes. Les notes de bas de page ne sont pas possibles&nbsp;: intégrez l’information au texte. Inutile d’y répéter le titre ou les noms des responsables&nbsp;: ils s’ajoutent d’eux-mêmes. Développez chaque sigle à sa première mention&nbsp;: service régional de l’archéologie (SRA).</p>
					<div class="na-editeur na-ancre" id="na-texte"><div id="na-editor"></div></div>
					<p class="na-wordcount" id="na-wordcount">0 mot</p>
					<input type="hidden" name="texte_notice" id="na-texte-notice" value="<?php echo esc_attr( $this->repris( 'texte_notice' ) ); ?>">
				</div>
			</section>

			<section class="na-section" aria-labelledby="na-s4">
				<h2 class="na-section-titre" id="na-s4"><span class="na-section-num" aria-hidden="true">4</span> Classement et mots-clés</h2>
				<div class="na-champ">
					<label for="na-rubrique">Rubrique de la Chronique</label>
					<p class="na-help" id="na-rubrique-aide">La rubrique où paraîtra la notice.</p>
					<select id="na-rubrique" name="rubrique_principale" required aria-describedby="na-rubrique-aide"><?php echo $this->options_html( $this->rubriques, self::rubrique_actuelle( $this->repris( 'rubrique_principale' ) ), 'Choisir une rubrique…' ); ?></select>
				</div>
				<?php $matiere_reprise = strtoupper( $this->repris( 'rubrique_matiere' ) ); ?>
				<div class="na-champ" id="na-matiere-bloc" data-rubrique="<?php echo esc_attr( self::RUBRIQUE_ARTISANAT ); ?>"<?php echo self::RUBRIQUE_ARTISANAT === self::rubrique_actuelle( $this->repris( 'rubrique_principale' ) ) ? '' : ' hidden'; ?>>
					<label for="na-matiere">Matière</label>
					<p class="na-help" id="na-matiere-aide">La rubrique V se divise par matière&nbsp;: celle de l’installation.</p>
					<select id="na-matiere" name="rubrique_matiere" aria-describedby="na-matiere-aide">
						<option value="">Choisir une matière…</option>
						<?php foreach ( self::MATIERES as $lettre => $matiere ) : ?>
							<option value="<?php echo esc_attr( $lettre ); ?>"<?php echo $lettre === $matiere_reprise ? ' selected' : ''; ?>><?php echo esc_html( $lettre . ' – ' . $matiere ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<button type="button" class="na-ajout" aria-controls="na-renvois" aria-expanded="true" hidden><span aria-hidden="true">+</span> Signaler la notice dans d’autres rubriques</button>
				<fieldset class="na-sous-groupe na-bloc" id="na-renvois" aria-describedby="na-renvois-aide">
					<legend>Autres rubriques concernées <span class="na-facultatif">(facultatif)</span></legend>
					<p class="na-help" id="na-renvois-aide">Deux au plus. La notice y sera signalée par un renvoi.</p>
					<div class="na-half">
						<div class="na-champ">
							<label for="na-renvoi-1">Premier renvoi</label>
							<select id="na-renvoi-1" name="renvoi_1"><?php echo $this->options_html( $this->rubriques, self::rubrique_actuelle( $this->repris( 'renvoi_1' ) ), 'Aucun renvoi' ); ?></select>
						</div>
						<div class="na-champ">
							<label for="na-renvoi-2">Second renvoi</label>
							<select id="na-renvoi-2" name="renvoi_2"><?php echo $this->options_html( $this->rubriques, self::rubrique_actuelle( $this->repris( 'renvoi_2' ) ), 'Aucun renvoi' ); ?></select>
						</div>
					</div>
					<button type="button" class="na-retirer" hidden>Retirer les renvois</button>
				</fieldset>

				<div class="na-sous-groupe" role="group" aria-labelledby="na-kw-titre" aria-describedby="na-kw-aide">
					<h3 class="na-sous-titre" id="na-kw-titre">Mots-clés Pactols <span class="na-facultatif">(facultatif)</span></h3>
					<p class="na-help" id="na-kw-aide">Tapez trois lettres au moins, puis choisissez les termes dans la liste que propose le thésaurus Pactols&nbsp;: la notice sera indexée avec eux. Si le terme n’est pas dans la liste, appuyez deux fois sur Entrée pour le garder tel quel&nbsp;: il partira sans identifiant Pactols. Dix au plus par catégorie.</p>
					<div class="na-pactols-grid">
						<?php
						echo $this->pactols_categorie_html( 'period', 'Périodes',
							'Par exemple&nbsp;: haut Moyen Âge, XII<sup>e</sup>&nbsp;siècle.', 'Chercher une période', 'pactols_periods' );
						echo $this->pactols_categorie_html( 'subject', 'Sujets',
							'Par exemple&nbsp;: château, four de potier, sépulture.', 'Chercher un sujet', 'pactols_subjects' );
						// La commune de la notice est déjà demandée plus haut : ce qu'on
						// attend ici, ce sont les autres lieux — la région, le pays, un
						// ensemble géographique. Sans le dire, on reçoit la commune deux fois.
						echo $this->pactols_categorie_html( 'place', 'Autres lieux',
							'Région, pays ou ensemble géographique&nbsp;: Normandie. La commune de l’opération est déjà dans le titre.',
							'Chercher un lieu', 'pactols_places' );
						?>
					</div>
					<input type="hidden" name="pactols_periods" id="pactols_periods" value="<?php echo esc_attr( $this->repris( 'pactols_periods' ) ); ?>">
					<input type="hidden" name="pactols_subjects" id="pactols_subjects" value="<?php echo esc_attr( $this->repris( 'pactols_subjects' ) ); ?>">
					<input type="hidden" name="pactols_places" id="pactols_places" value="<?php echo esc_attr( $this->repris( 'pactols_places' ) ); ?>">
				</div>
			</section>

			<section class="na-section" aria-labelledby="na-s5">
				<h2 class="na-section-titre" id="na-s5"><span class="na-section-num" aria-hidden="true">5</span> Illustrations <span class="na-facultatif">(facultatif)</span></h2>
				<p class="na-help" id="na-illus-aide">Trois fichiers au plus, <?php echo esc_html( $total_mo ); ?>&nbsp;Mo en tout<?php echo $par_fichier; // Chiffre calculé, entités seulement. ?>, en JPEG, TIFF ou PDF. <?php echo esc_html( $this->norme_des_illustrations() ); ?> Appelez chaque figure dans le texte, à l’endroit qui en parle&nbsp;: (fig.&nbsp;1).</p>
				<div class="na-illus-attente" id="na-illus-attente" hidden></div>
				<label class="na-sr" for="na-illustrations">Choisir des fichiers</label>
				<div class="na-depot-fichiers" id="na-depot-zone">
					<input type="file" name="illustrations[]" id="na-illustrations" multiple accept=".jpg,.jpeg,.tiff,.tif,.pdf" aria-describedby="na-illus-aide">
					<p class="na-depot-invite" aria-hidden="true"><span class="na-bouton-faux">Choisir des fichiers</span><span class="na-depot-ou">ou faites-les glisser ici</span></p>
				</div>
				<p class="na-fichiers-refuses" id="na-fichiers-refuses" role="alert"></p>
				<ul class="na-filelist" id="na-filelist"></ul>
				<button type="button" class="na-ajout na-ajout-figure" id="na-ajout-figure" aria-describedby="na-illus-aide" hidden><span aria-hidden="true">+</span> Ajouter une figure</button>

				<div class="na-champ">
					<label for="na-originaux">Lien vers les originaux <span class="na-facultatif">(facultatif)</span></label>
					<p class="na-help" id="na-originaux-aide">Des originaux trop lourds pour ce formulaire&nbsp;? Joignez-en ici des versions allégées, et déposez les originaux sur un service de transfert — FileSender de RENATER, ou l’espace de partage de votre établissement. Collez ici le lien de téléchargement, qui doit rester valable quelques semaines.</p>
					<input type="text" inputmode="url" id="na-originaux" name="originaux_lien" autocomplete="off"
						spellcheck="false" autocapitalize="off" aria-describedby="na-originaux-aide"
						value="<?php echo esc_attr( $this->repris( 'originaux_lien' ) ); ?>">
				</div>
			</section>

			<section class="na-section" aria-labelledby="na-s6">
				<h2 class="na-section-titre" id="na-s6"><span class="na-section-num" aria-hidden="true">6</span> Envoi</h2>
				<div class="na-champ">
					<label for="na-commentaires">Message à la rédaction <span class="na-facultatif">(facultatif)</span></label>
					<p class="na-help" id="na-commentaires-aide">Ne sera pas publié. Par exemple&nbsp;: une précision sur une illustration, une autorisation en attente.</p>
					<textarea id="na-commentaires" name="commentaires" rows="3" aria-describedby="na-commentaires-aide"><?php echo esc_textarea( $this->repris( 'commentaires' ) ); ?></textarea>
				</div>
				<?php if ( $this->protection_utilise_la_question() ) : $defi = $this->defi_du_puzzle(); ?>
					<div class="na-question na-champ" id="na-puzzle"
						data-cible="<?php echo esc_attr( $defi['cible'] ); ?>"
						data-tolerance="<?php echo esc_attr( self::PUZZLE_TOLERANCE ); ?>">
						<p class="na-libelle" id="na-puzzle-libelle">Vérification&nbsp;: faites glisser la pièce jusqu’à son encoche</p>
						<p class="na-help" id="na-puzzle-aide">Au clavier&nbsp;: flèches, puis Entrée.</p>
						<div class="na-puzzle-scene na-ancre">
							<canvas class="na-puzzle-fond" id="na-puzzle-fond" width="320" height="150"
								role="img" aria-label="Image de vérification portant une encoche à combler"></canvas>
							<canvas class="na-puzzle-piece" id="na-puzzle-piece" width="59" height="48"
								aria-hidden="true"></canvas>
						</div>
						<div class="na-puzzle-piste">
							<div class="na-puzzle-poignee" id="na-puzzle-poignee" tabindex="0"
								role="slider" aria-labelledby="na-puzzle-libelle"
								aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"
								aria-describedby="na-puzzle-aide na-puzzle-etat"></div>
						</div>
						<span class="na-puzzle-etat" id="na-puzzle-etat" role="status">À faire glisser</span>
						<button type="button" class="na-puzzle-rejouer" id="na-puzzle-rejouer">Recommencer</button>
						<input type="hidden" name="na_curseur" id="na-curseur-valeur" value="">
						<input type="hidden" name="na_preuve" value="<?php echo esc_attr( $defi['preuve'] ); ?>">
					</div>
				<?php endif; ?>
				<?php if ( $this->protection_utilise_turnstile() && $this->turnstile_site_key_is_set() ) : ?>
					<div class="na-turnstile">
						<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $this->turnstile_site_key() ); ?>" data-theme="light" data-language="fr"></div>
					</div>
				<?php endif; ?>
				<div class="na-envoyer">
					<button type="submit" name="notice_archeomed_envoi" value="1" class="na-submit">Envoyer la notice</button>
					<div class="na-envoi" id="na-envoi" hidden>
						<div class="na-envoi-piste"><div class="na-envoi-barre"></div></div>
						<p class="na-envoi-mot">Envoi en cours. Avec des illustrations lourdes et une connexion lente, cela peut prendre deux ou trois minutes. Ne fermez pas cette page.</p>
					</div>
					<p class="na-donnees">Les informations transmises via ce formulaire sont utilisées uniquement pour l’instruction éditoriale des notices destinées à la Chronique d’<em>Archéologie médiévale</em>. Elles sont adressées à la rédaction de la revue et ne sont pas utilisées à d’autres fins. Elles sont conservées pendant la durée nécessaire au traitement éditorial de la notice. Pour toute demande relative à ces données, vous pouvez contacter la rédaction à l’adresse indiquée sur le site.</p>
				</div>
			</section>
		</form>
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			var form = document.getElementById('na-form');
			if (!form) { return; }
			// Le script valide lui-même, en une passe et dans la page. Sans lui,
			// la validation du navigateur reste en place.
			form.noValidate = true;

			var CHAMPS = <?php echo wp_json_encode( $this->champs_du_formulaire() ); ?>;
			var MAX_FILES = <?php echo (int) self::MAX_FILES; ?>;
			// Le texte alternatif d'une figure : un avis au-delà du seuil des
			// normes (150 caractères par défaut), coupé au-delà de 300. Les
			// avis d'accessibilité viennent du serveur — phrases, motifs,
			// listes —, pour que le navigateur dise mot pour mot ce que la
			// rédaction lira.
			var A11Y = <?php echo wp_json_encode( Notice_Archeomed_Controles::regles_d_accessibilite() ); ?>;
			var ALT_CONSEILLE = A11Y.conseille;
			var ALT_MAX = A11Y.max;
			var DESCRIPTION_MAX = <?php echo (int) Notice_Archeomed_Controles::DESCRIPTION_MAX; ?>;
			// Les mots d'une couleur qui porte seule l'information, comme au
			// serveur (Notice_Archeomed_Controles::COULEUR_SEULE).
			var COULEUR_SEULE = /\b(?:en\s+(?:rouge|bleu|vert|jaune|orange|violet|rose|gris|noir|blanc)\b|(?:zones?|traits?|points?|tracés?|surfaces?|aplats?|hachures?|parties?|cercles?|flèches?|lignes?|contours?|plages?|secteurs?)\s+(?:rouges?|bleue?s?|verte?s?|jaunes?|oranges?|violette?s?|roses?|grise?s?|noire?s?|blanche?s?)(?![a-zà-ÿ]))/i;
			var MAX_TOTAL_SIZE = <?php echo (int) $plafonds['total']; ?>;
			var MAX_FILE_SIZE = <?php echo (int) $plafonds['fichier']; ?>;
			var ALLOWED = <?php echo wp_json_encode( self::ALLOWED_EXT ); ?>;
			var KW_MAX = <?php echo (int) self::PACTOLS_FIELD_COUNT; ?>;
			// La norme des photographies vient du serveur, qui la contrôle aussi :
			// recopiée ici, elle aurait fini par dire autre chose que lui.
			var NORME = <?php
				list( $norme_petit, $norme_grand ) = Notice_Archeomed_Controles::pixels_de_la_norme();
				echo wp_json_encode( array(
					'petit'     => $norme_petit,
					'grand'     => $norme_grand,
					'largeurCm' => (int) Notice_Archeomed_Normes::valeur( 'photo_largeur_cm' ),
					'hauteurCm' => (int) Notice_Archeomed_Normes::valeur( 'photo_hauteur_cm' ),
					'ppp'       => (int) Notice_Archeomed_Normes::valeur( 'photo_ppp' ),
					'traitPpp'  => (int) Notice_Archeomed_Normes::valeur( 'trait_ppp' ),
				) );
			?>;
			// Les autres normes de la revue que le navigateur applique : la
			// longueur du texte, l'appel d'une figure, le titre, les avis.
			var NORMES = <?php
				echo wp_json_encode( array(
					'motsMin'         => (int) Notice_Archeomed_Normes::valeur( 'mots_min' ),
					'motsMax'         => (int) Notice_Archeomed_Normes::valeur( 'mots_max' ),
					'figure'          => trim( substr( Notice_Archeomed_Normes::numero_de_figure( 1 ), 0, -1 ) ),
					'avis'            => array_values( (array) Notice_Archeomed_Normes::valeur( 'avis' ) ),
					'avantLieuDit'    => Notice_Archeomed_Normes::avant_le_lieu_dit(),
					'lieuDitItalique' => Notice_Archeomed_Normes::lieu_dit_en_italique(),
				) );
			?>;
			// Les avis que la revue a choisi de donner, dans ses normes.
			function avisActif(famille) { return NORMES.avis.indexOf(famille) !== -1; }
			var PACTOLS = 'https://pactols.frantiq.fr/';
			var NBSP = ' ', FINE = ' ';

			// ── Petits outils ──────────────────────────────────────────────
			function vide(v) { return !v || !String(v).replace(/\s+/g, '').length; }
			function net(v) { return String(v || '').replace(/\s+/g, ' ').trim(); }
			function guillemets(t) { return '«' + FINE + t + FINE + '»'; }
			function mo(octets) {
				if (octets < 102400) { return Math.max(1, Math.round(octets / 1024)) + NBSP + 'Ko'; }
				var v = Math.round(octets / 1048576 * 10) / 10;
				return String(v).replace('.', ',') + NBSP + 'Mo';
			}
			// Une limite s'arrondit par défaut : « 8 Mo » pour 7,96 promettait
			// plus que le serveur n'accepte.
			function moPlafond(octets) {
				return String(Math.floor(octets / 1048576 * 10) / 10).replace('.', ',') + NBSP + 'Mo';
			}
			var naAnnonce = document.getElementById('na-annonce');
			function annoncer(texte) {
				if (!naAnnonce) { return; }
				naAnnonce.textContent = '';
				window.setTimeout(function () { naAnnonce.textContent = texte; }, 60);
			}
			function ajouterDescription(el, id) {
				var ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
				if (ids.indexOf(id) === -1) { ids.unshift(id); }
				el.setAttribute('aria-describedby', ids.join(' '));
			}
			function retirerDescription(el, id) {
				var ids = (el.getAttribute('aria-describedby') || '').split(/\s+/)
					.filter(function (x) { return x && x !== id; });
				if (ids.length) { el.setAttribute('aria-describedby', ids.join(' ')); }
				else { el.removeAttribute('aria-describedby'); }
			}
			// Un appel à Pactols qui ne répond pas s'abandonne au bout de quatre
			// secondes : la recherche échouait sans bruit, et l'on attendait une
			// liste qui ne viendrait pas.
			function chercher(url, delai) {
				var ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
				var minuterie = null;
				var attente = new Promise(function (resoudre, rejeter) {
					minuterie = window.setTimeout(function () {
						if (ctrl) { ctrl.abort(); }
						rejeter(new Error('délai'));
					}, delai || 4000);
				});
				var options = { headers: { 'Accept': 'application/json' } };
				if (ctrl) { options.signal = ctrl.signal; }
				// Pactols répond à une recherche sans résultat par un corps vide,
				// non par « [] » : lu comme du JSON, il levait une erreur, et
				// l'auteur lisait « Pactols ne répond pas » au lieu de « aucun terme ».
				var appel = fetch(url, options).then(function (r) {
					if (!r.ok) { throw new Error('HTTP ' + r.status); }
					return r.text().then(function (t) { return String(t).trim() ? JSON.parse(t) : []; });
				});
				return Promise.race([appel, attente]).then(
					function (v) { window.clearTimeout(minuterie); return v; },
					function (e) { window.clearTimeout(minuterie); throw e; });
			}

			// ── Les avis : à vérifier, sans rien bloquer ───────────────────
			// Ce que la rédaction trouvait à la relecture — un texte coupé au
			// collage, « XIIème », une figure appelée qui manque — et devait
			// écrire à l'auteur pour le faire corriger. Le serveur le dit à la
			// rédaction ; le navigateur le dit ici à l'auteur, avec les mêmes
			// mots. Ce ne sont jamais des erreurs : ni récapitulatif, ni envoi
			// retenu, et rien ne paraît pendant qu'on tape.
			//
			// Les lettres accentuées ne sont pas des lettres pour « \b » : les
			// motifs disent « ni lettre ni chiffre » avec \p{L}, et le drapeau
			// « u ». Un navigateur qui ne le connaîtrait pas perd les avis, non
			// le formulaire.
			function motif(source, drapeaux) {
				try { return new RegExp(source, drapeaux); } catch (e) { return null; }
			}
			function points(t) { return Array.from ? Array.from(String(t)) : String(t).split(''); }
			function extrait(t, n) {
				var c = points(t);
				return (n < 0 ? c.slice(n) : c.slice(0, n)).join('');
			}
			function entier(n) { return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, NBSP); }
			function decimal1(x) { return (Math.round(x * 10) / 10).toFixed(1).replace('.', ','); }
			function ajouterEnFin(el, id) {
				var ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
				if (ids.indexOf(id) === -1) { ids.push(id); }
				el.setAttribute('aria-describedby', ids.join(' '));
			}
			// Un cadre d'avis sous le champ : une liste, parfois un bouton qui
			// ne fait rien tant qu'on ne l'a pas pressé.
			function zoneDeConseils(id, apres) {
				var z = document.getElementById(id);
				if (!z) {
					z = document.createElement('div');
					z.id = id;
					z.className = 'na-conseils';
					z.hidden = true;
					apres.parentNode.insertBefore(z, apres.nextSibling);
				}
				return z;
			}
			function montrerConseils(z, liste, champs) {
				var vus = {}, propres = [];
				liste.forEach(function (m) {
					var t = typeof m === 'string' ? m : m.texte;
					if (t && !vus[t]) { vus[t] = true; propres.push(m); }
				});
				z.innerHTML = '';
				(champs || []).forEach(function (c) { if (c) { retirerDescription(c, z.id); } });
				if (!propres.length) { z.hidden = true; return; }
				var titre = document.createElement('p');
				titre.className = 'na-conseils-titre';
				titre.textContent = 'À vérifier — rien n’empêche l’envoi';
				var ul = document.createElement('ul');
				propres.forEach(function (m) {
					var li = document.createElement('li');
					li.appendChild(document.createTextNode(typeof m === 'string' ? m : m.texte));
					if (m.bouton) {
						var b = document.createElement('button');
						b.type = 'button';
						b.className = 'na-bouton-lien';
						b.textContent = m.bouton;
						b.addEventListener('click', m.action);
						li.appendChild(document.createTextNode(' '));
						li.appendChild(b);
					}
					ul.appendChild(li);
				});
				z.appendChild(titre);
				z.appendChild(ul);
				z.hidden = false;
				(champs || []).forEach(function (c) { if (c) { ajouterEnFin(c, z.id); } });
			}
			// Un nom tout en capitales : au moins quatre lettres, aucune minuscule.
			var NON_LETTRE = motif('\\P{L}', 'gu');
			var MINUSCULE = motif('\\p{Ll}', 'u');
			function enCapitales(t) {
				if (!NON_LETTRE || !MINUSCULE) { return false; }
				var lettres = String(t || '').replace(NON_LETTRE, '');
				return points(lettres).length >= 4 && !MINUSCULE.test(lettres);
			}
			// « DUPONT-MARTIN » en « Dupont-Martin », « D'ARCY » en « D'arcy » :
			// une proposition, que l'auteur accepte ou non — « McLean » ne se
			// reconstitue pas.
			function casseDeTitre(t) {
				var avant = '';
				return points(String(t).toLowerCase()).map(function (ch) {
					var lettre = ch.toLowerCase() !== ch.toUpperCase();
					var debut = lettre && !(avant && (avant.toLowerCase() !== avant.toUpperCase() || /['’]/.test(avant)));
					avant = ch;
					return debut ? ch.toUpperCase() : ch;
				}).join('');
			}

			var jsErrorBox = document.getElementById('na-js-error');
			function showJsError(msg) {
				jsErrorBox.textContent = msg;
				jsErrorBox.hidden = false;
			}

			// ── L'éditeur ──────────────────────────────────────────────────
			var quill = null;
			// Ce que le collage a laissé de côté, pour le dire à l'auteur.
			var oteAuCollage = [], commentairesOtes = false, collageEnCours = false;
			if (typeof Quill === 'undefined') {
				showJsError('Erreur technique' + NBSP + ': l’éditeur de texte ne s’est pas chargé. Rechargez la page.');
			} else {
				// Les petites capitales, que la revue emploie pour les siècles :
				// un format de l'éditeur, écrit <span class="na-pc">, que le
				// serveur rend en petites capitales dans le document. Si le
				// navigateur ne sait pas le déclarer, l'éditeur fait sans.
				var petitesCapitales = false;
				try {
					var EnLigne = Quill.import('blots/inline');
					var PetitesCapitales = function () { return Reflect.construct(EnLigne, arguments, PetitesCapitales); };
					Object.setPrototypeOf(PetitesCapitales.prototype, EnLigne.prototype);
					Object.setPrototypeOf(PetitesCapitales, EnLigne);
					PetitesCapitales.blotName = 'petitescapitales';
					PetitesCapitales.tagName = 'span';
					PetitesCapitales.className = 'na-pc';
					// Un « span » passe pour un simple conteneur aux yeux de
					// Quill : c'est la classe qui fait le format.
					PetitesCapitales.formats = function (noeud) {
						return noeud && noeud.classList && noeud.classList.contains('na-pc') ? true : undefined;
					};
					Quill.register(PetitesCapitales, true);
					petitesCapitales = true;
				} catch (e) { petitesCapitales = false; }
				quill = new Quill('#na-editor', {
					theme: 'snow',
					// Les seuls formats que le document sait rendre. Sans cette
					// liste, un collage depuis Word apportait listes, titres et
					// citations, que le serveur aplatissait en un paragraphe.
					formats: ['bold', 'italic', 'script'].concat(petitesCapitales ? ['petitescapitales'] : []),
					modules: {
						toolbar: [
							['bold', 'italic'].concat(petitesCapitales ? ['petitescapitales'] : []),
							[{ 'script': 'super' }, { 'script': 'sub' }],
							['clean']
						],
						// Tab insérait une tabulation : au clavier, on ne sortait
						// plus de l'éditeur qu'à reculons.
						keyboard: { bindings: { tab: { key: 'Tab', handler: function () { return true; } } } }
					}
				});
				// Le collage depuis Word apportait tout ce que porte son
				// presse-papiers : le texte supprimé en suivi des modifications,
				// barré à l'écran, revenait dans la phrase (« entièrement curé
				// puis comblé »), et les commentaires de relecture devenaient le
				// dernier paragraphe de la notice. On ne garde que ce que l'auteur
				// lit comme la version finale, et on dit ce qui a été ôté.
				// [Hypothèse] La forme du presse-papiers de Word n'a pas été
				// vérifiée sur un Word réel : on couvre les marques connues
				// (<del>, <s>, <strike>, line-through, msoDel ; mso-element:comment,
				// MsoCommentReference, msocomtxt, msocomanchor, ancres _msocom_).
				// Le texte commenté lui-même (mso-comment-continuation) reste.
				try {
					var Delta = Quill.import('delta');
					var styleDe = function (n) { return (n.getAttribute && n.getAttribute('style')) || ''; };
					var classeDe = function (n) { return (n.getAttribute && n.getAttribute('class')) || ''; };
					var estBarre = function (n) {
						return /^(DEL|S|STRIKE)$/.test(n.tagName || '') || /(^|\s)msoDel(\s|$)/i.test(classeDe(n))
							|| /text-decoration(-line)?\s*:[^;]*line-through/i.test(styleDe(n));
					};
					var estCommentaire = function (n) {
						return /mso-element\s*:\s*comment|mso-comment-reference/i.test(styleDe(n))
							|| /(^|\s)(MsoCommentReference|MsoCommentText|msocomtxt|msocomoff|msocomanchor)(\s|$)/i.test(classeDe(n))
							|| (n.tagName === 'A' && /^#_msocom_/i.test(n.getAttribute('href') || ''))
							|| (n.tagName === 'A' && /^_msoanchor_/i.test(n.getAttribute('name') || ''));
					};
					quill.container.addEventListener('paste', function () {
						collageEnCours = true;
						oteAuCollage = [];
						commentairesOtes = false;
						window.setTimeout(function () { collageEnCours = false; majConseilsDuTexte(); }, 0);
					}, true);
					quill.clipboard.addMatcher(Node.ELEMENT_NODE, function (node, delta) {
						if (estBarre(node)) {
							// Le texte se compte une fois, au plus haut élément barré.
							var p = node.parentNode, dedans = false;
							while (p && p.nodeType === 1 && !dedans) { dedans = estBarre(p); p = p.parentNode; }
							if (collageEnCours && !dedans && !vide(node.textContent)) { oteAuCollage.push(net(node.textContent)); }
							return new Delta();
						}
						if (estCommentaire(node)) {
							if (collageEnCours) { commentairesOtes = true; }
							return new Delta();
						}
						// Les petites capitales de Word — « xii » d'un siècle —
						// arrivaient en bas de casse.
						if (petitesCapitales && /font-variant\s*:\s*small-caps/i.test(styleDe(node))) {
							return delta.reduce(function (d, op) {
								if (!op.insert) { return d; }
								var a = {};
								Object.keys(op.attributes || {}).forEach(function (k) { a[k] = op.attributes[k]; });
								if (typeof op.insert === 'string' && op.insert !== '\n') { a.petitescapitales = true; }
								return d.insert(op.insert, Object.keys(a).length ? a : undefined);
							}, new Delta());
						}
						return delta;
					});
				} catch (e) {}
				// Le texte revient dans l'éditeur après un renvoi manqué : il
				// était gardé dans le champ caché, dont Quill ne sait rien.
				var texteGarde = document.getElementById('na-texte-notice').value;
				if (texteGarde) {
					quill.clipboard.dangerouslyPasteHTML(texteGarde);
				}
				// Un bloc éditable n'a pas de libellé à lui : on le nomme, sans
				// quoi un lecteur d'écran n'annonçait qu'une « zone modifiable ».
				var racine = quill.root;
				racine.id = 'na-texte-editeur';
				racine.setAttribute('role', 'textbox');
				racine.setAttribute('aria-multiline', 'true');
				racine.setAttribute('aria-required', 'true');
				racine.setAttribute('aria-labelledby', 'na-texte-libelle');
				racine.setAttribute('aria-describedby', 'na-texte-aide na-wordcount');
				var barre = document.querySelector('#na-texte .ql-toolbar');
				if (barre) {
					barre.setAttribute('role', 'toolbar');
					barre.setAttribute('aria-label', 'Mise en forme du texte');
					[['.ql-bold', 'Gras'], ['.ql-italic', 'Italique'], ['.ql-petitescapitales', 'Petites capitales'],
						['.ql-script[value="super"]', 'Exposant'],
						['.ql-script[value="sub"]', 'Indice'], ['.ql-clean', 'Effacer la mise en forme']].forEach(function (p) {
						var b = barre.querySelector(p[0]);
						if (b) { b.setAttribute('aria-label', p[1]); b.title = p[1]; }
					});
					// Quill n'a pas d'icône pour ce format : deux lettres, en
					// petites capitales elles-mêmes.
					var pc = barre.querySelector('.ql-petitescapitales');
					if (pc) { pc.innerHTML = '<span aria-hidden="true">Pc</span>'; }
				}
			}
			// Le compteur passait au rouge dès le premier mot et jusqu'au
			// deux-cent-quatre-vingt-dix-neuvième : il alarmait pendant qu'on
			// écrivait. Sous la fourchette, il ne le dit qu'à la sortie.
			var wc = document.getElementById('na-wordcount');
			var texteQuitte = false;
			function countWords() {
				if (!quill) {
					wc.textContent = 'éditeur indisponible';
					return 0;
				}
				var text = quill.getText().trim();
				var n = text.length ? text.split(/\s+/).length : 0;
				var mots = n + (n > 1 ? ' mots' : ' mot');
				var hors = false;
				if (n > NORMES.motsMax) { mots += ' — au-delà des ' + NORMES.motsMax + ' recommandés'; hors = true; }
				else if (n >= NORMES.motsMin) { mots += ' — dans la fourchette recommandée'; }
				else if (n > 0 && texteQuitte) { mots += ' — la rédaction recommande au moins ' + NORMES.motsMin; hors = true; }
				wc.textContent = mots;
				wc.classList.toggle('na-out', hors);
				return n;
			}
			if (quill) {
				quill.on('text-change', function () { countWords(); reverifier('texte'); });
				quill.root.addEventListener('blur', function () { texteQuitte = true; countWords(); });
				quill.root.addEventListener('focus', function () { texteQuitte = false; countWords(); });
			}
			countWords();

			// ── Combobox (lieux et Pactols) ────────────────────────────────
			// Le focus reste dans le champ ; l'option active est désignée par
			// aria-activedescendant, que les lecteurs d'écran suivent. Flèches,
			// Entrée, Échap ; la souris et le doigt comme avant.
			function armerCombobox(champ, liste, choisir, dire) {
				var actif = -1;
				dire = dire || annoncer;
				champ.setAttribute('role', 'combobox');
				champ.setAttribute('aria-autocomplete', 'list');
				champ.setAttribute('aria-expanded', 'false');
				champ.setAttribute('aria-controls', liste.id);
				liste.setAttribute('role', 'listbox');
				liste.setAttribute('aria-label', 'Propositions');
				liste.hidden = true;
				// Le champ garde le focus : la liste ne se ferme pas sous le
				// doigt avant que le clic n'arrive.
				liste.addEventListener('mousedown', function (e) { e.preventDefault(); });
				function options() { return liste.querySelectorAll('[role="option"]'); }
				function marquer(i) {
					var opts = options();
					actif = i;
					for (var k = 0; k < opts.length; k++) {
						opts[k].setAttribute('aria-selected', k === i ? 'true' : 'false');
					}
					if (i >= 0 && opts[i]) {
						champ.setAttribute('aria-activedescendant', opts[i].id);
						if (opts[i].scrollIntoView) { opts[i].scrollIntoView({ block: 'nearest' }); }
					} else {
						champ.removeAttribute('aria-activedescendant');
					}
				}
				function fermer() {
					liste.hidden = true;
					liste.innerHTML = '';
					champ.setAttribute('aria-expanded', 'false');
					marquer(-1);
				}
				function ouvrir(items, rendre) {
					liste.innerHTML = '';
					// Une réponse arrivée après qu'on a quitté le champ n'ouvre
					// rien : la liste restait déployée sur le champ suivant.
					if (!items.length || document.activeElement !== champ) { fermer(); return; }
					items.forEach(function (item, i) {
						var li = document.createElement('li');
						li.id = liste.id + '-' + i;
						li.setAttribute('role', 'option');
						li.setAttribute('aria-selected', 'false');
						rendre(li, item, i);
						li.addEventListener('click', function () { fermer(); choisir(item); });
						liste.appendChild(li);
					});
					liste.hidden = false;
					champ.setAttribute('aria-expanded', 'true');
					actif = -1;
					dire(items.length + (items.length > 1 ? ' propositions' : ' proposition')
						+ NBSP + ': flèche bas pour les parcourir, Entrée pour choisir.');
				}
				champ.addEventListener('keydown', function (e) {
					var opts = options();
					var ouverte = !liste.hidden && opts.length > 0;
					var k = e.key;
					if (k === 'Escape' || k === 'Esc') {
						if (ouverte) { e.preventDefault(); fermer(); }
						return;
					}
					if (!ouverte) { return; }
					if (k === 'ArrowDown' || k === 'Down') {
						e.preventDefault(); marquer(actif < opts.length - 1 ? actif + 1 : 0);
					} else if (k === 'ArrowUp' || k === 'Up') {
						e.preventDefault(); marquer(actif > 0 ? actif - 1 : opts.length - 1);
					} else if (k === 'Enter' && actif >= 0) {
						e.preventDefault(); opts[actif].click();
					} else if (k === 'Tab') {
						fermer();
					}
				});
				champ.addEventListener('blur', function () { window.setTimeout(fermer, 150); });
				return { ouvrir: ouvrir, fermer: fermer };
			}

			// Extrait « 26678/pcrtd1Ms3ERUXz » d'une URI ARK complète.
			function arkPathFromUri(uri) {
				var m = String(uri).match(/ark:\/(.+)$/);
				return m ? m[1] : '';
			}
			// Le département d'une commune, d'après son ARK : l'avant-dernier
			// élément du chemin, débarrassé de « Département du… ». Chaque ARK
			// n'est demandé qu'une fois.
			var departements = {};
			function getDepartmentFromArk(arkUri) {
				var arkPath = arkPathFromUri(arkUri);
				if (!arkPath) { return Promise.resolve(''); }
				if (departements[arkPath]) { return departements[arkPath]; }
				departements[arkPath] = chercher(PACTOLS + 'api/searchwidgetbyark?q='
					+ encodeURIComponent(arkPath) + '&lang=fr')
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
						return deptLabel.replace(/^D[ée]partement\s+(du\s+|de la\s+|de l['’]\s*|des\s+|de\s+)/i, '').trim();
					})
					.catch(function () { delete departements[arkPath]; return ''; });
				return departements[arkPath];
			}
			function precision(li, texte) {
				var p = document.createElement('span');
				p.className = 'na-suggestion-precision';
				p.textContent = '(' + texte + ')';
				li.appendChild(p);
			}

			// ── Le titre : lieux, parenthèse, lieu-dit, et leur aperçu ──────
			var deptInput = document.getElementById('na-departement');
			var deptAuto = document.getElementById('na-departement-auto');
			var lieuDitInput = document.getElementById('na-lieu-dit');
			// La parenthèse tapée par l'auteur ne s'écrase plus : choisir la
			// commune la vidait, même quand Pactols n'avait rien à y remettre.
			var deptSaisiMain = !vide(deptInput.value);
			deptInput.addEventListener('input', function () {
				deptSaisiMain = !vide(deptInput.value);
				deptAuto.textContent = '';
				majApercu();
			});
			// Une parenthèse présente au chargement — reprise, correction,
			// brouillon — passait pour tapée à la main et ne suivait plus la
			// commune : corriger « Falaise (Ardennes) » en « Falaise (Calvados) »
			// laissait « (Ardennes) ». Si elle est celle que Pactols donne pour
			// la première commune, elle avait été remplie d'office : elle suit.
			function jugerLaParenthese() {
				deptSaisiMain = !vide(deptInput.value);
				var premier = form.querySelector('.na-commune-ark');
				if (!deptSaisiMain || !premier || !premier.value) { return; }
				var valeur = deptInput.value, uri = premier.value;
				getDepartmentFromArk(uri).then(function (d) {
					if (d && deptInput.value === valeur && premier.value === uri
						&& plier(d) === plier(sansParentheses(valeur))) { deptSaisiMain = false; }
				});
			}
			// La parenthèse tapée par l'auteur ne s'écrase pas ; mais si Pactols
			// situe ailleurs la commune choisie, on le dit, et l'on propose.
			function signalerUneAutreParenthese(uri) {
				var premier = form.querySelector('.na-commune-ark');
				deptAuto.textContent = '';
				getDepartmentFromArk(uri).then(function (d) {
					var actuel = sansParentheses(deptInput.value);
					if (!d || !deptSaisiMain || !premier || premier.value !== uri || plier(d) === plier(actuel)) { return; }
					deptAuto.textContent = 'D’après Pactols, la commune choisie relève de ' + guillemets(d) + ', non de '
						+ guillemets(actuel) + NBSP + ': vérifiez la parenthèse. ';
					var b = document.createElement('button');
					b.type = 'button';
					b.className = 'na-bouton-lien';
					b.textContent = 'Mettre ' + guillemets(d);
					b.addEventListener('click', function () {
						deptInput.value = d;
						deptSaisiMain = false;
						// « Falaise (Calvados) » perd alors sa précision, devenue
						// celle de la parenthèse ; l'identifiant reste.
						var lieu = form.querySelector('.na-commune');
						var q = lieu ? QUALIFIE.exec(lieu.value) : null;
						if (q && premier.value === uri && plier(q[2]) === plier(d)) { lieu.value = q[1]; }
						deptAuto.textContent = 'Rempli d’après Pactols' + NBSP + ': vérifiez.';
						reverifier('departement');
						majApercu();
						marquerModifie();
						deptInput.focus();
					});
					deptAuto.appendChild(b);
				});
			}
			function remplirDept(uri) {
				if (deptSaisiMain) { signalerUneAutreParenthese(uri); return; }
				deptInput.value = '';
				deptAuto.textContent = '';
				majApercu();
				getDepartmentFromArk(uri).then(function (d) {
					if (!d || deptSaisiMain) { return; }
					deptInput.value = d;
					deptAuto.textContent = 'Rempli d’après Pactols' + NBSP + ': vérifiez.';
					reverifier('departement');
					majApercu();
				});
			}
			// Le serveur ôte les parenthèses saisies et l'article élidé en tête
			// (« l’Aude ») : l'aperçu en fait autant, pour montrer ce qui paraîtra.
			var ARTICLE = null;
			try { ARTICLE = new RegExp('^l[\'’]\\s*(?=\\p{Lu})', 'u'); } catch (e) { ARTICLE = /^l['’]\s*(?=[A-ZÀ-Ý])/; }
			function sansParentheses(v) {
				v = net(v);
				while (v && v.charAt(0) === '(' && v.charAt(v.length - 1) === ')') { v = v.slice(1, -1).trim(); }
				v = v.replace(/^[\s()]+|[\s()]+$/g, '');
				return v.replace(ARTICLE, '');
			}
			var apercu = document.getElementById('na-apercu');
			var apercuTitre = document.getElementById('na-apercu-titre');
			// Compose comme le document : les lieux joints par des virgules, la
			// parenthèse, puis le point et le lieu-dit en italique. Ce qui manque
			// encore paraît en gris, à sa place.
			function majApercu() {
				var lieux = [].map.call(form.querySelectorAll('.na-commune'), function (c) { return net(c.value); })
					.filter(Boolean).slice(0, <?php echo (int) self::MAX_LIEUX; ?>);
				var dept = sansParentheses(deptInput.value);
				// Sans le point final qu'on y met par habitude, comme au document —
				// sauf abréviation : « Le XXe s. ».
				var lieuDit = net(lieuDitInput.value);
				if (/\.$/.test(lieuDit) && !/\.\.\.$/.test(lieuDit) && !/(^|\s)(\S{1,3}|\S+\.\S*)\.$/.test(lieuDit)) {
					lieuDit = lieuDit.slice(0, -1).trim();
				}
				function morceau(texte, manque, italique) {
					var el = document.createElement(italique ? 'em' : 'span');
					el.textContent = texte;
					if (manque) { el.className = 'na-manque'; }
					apercuTitre.appendChild(el);
				}
				apercuTitre.innerHTML = '';
				morceau(lieux.length ? lieux.join(', ') : 'Commune', !lieux.length);
				morceau(' (', false);
				morceau(dept || 'département', !dept);
				// Le signe et l'italique du lieu-dit suivent les normes de la revue.
				morceau(')' + NORMES.avantLieuDit, false);
				morceau(lieuDit || 'Lieu-dit', !lieuDit, NORMES.lieuDitItalique);
				apercu.hidden = false;
			}
			lieuDitInput.addEventListener('input', majApercu);

			// Chaque ligne de lieu se complète pour son propre compte ; seule la
			// première renseigne la parenthèse.
			function armerLeLieu(ligne, rang) {
				var champ = ligne.querySelector('.na-commune');
				var ark = ligne.querySelector('.na-commune-ark');
				var boite = ligne.querySelector('.na-suggestions');
				if (!champ || !ark || !boite) { return; }
				// Le lieu choisi dans la liste, tel que Pactols le nomme. Ôter à
				// la main la précision de « Falaise (Calvados) » vidait
				// l'identifiant : le nom restait le même, et le lieu partait
				// « sans identifiant ».
				var choisi = ark.value ? { label: net(champ.value), uri: ark.value } : null;
				// Un lieu reposé par le brouillon, avec son identifiant : on le
				// connaît avant la première retouche.
				champ.addEventListener('focus', function () {
					if (ark.value && (!choisi || choisi.uri !== ark.value)) { choisi = { label: net(champ.value), uri: ark.value }; }
				});
				function memeLieu(v) {
					if (!choisi || vide(v)) { return false; }
					var p = plier(v);
					return p === plier(choisi.label) || p === plier(nomSansPrecision(choisi.label));
				}
				function retenir(c) {
					champ.value = c.label;
					ark.value = c.uri;
					choisi = { label: c.label, uri: c.uri };
					annoncer(guillemets(c.label) + ' retenu.');
					reverifier('commune');
					majApercu();
					marquerModifie();
					if (0 === rang) { remplirDept(c.uri); }
					allegerLeNom(c);
				}
				// Pactols distingue les homonymes dans le libellé même :
				// « Falaise (Calvados) ». La parenthèse du titre disant déjà le
				// département, on imprimait « Falaise (Calvados) (Calvados) ».
				// Quand la précision du libellé est celle de la parenthèse, le
				// champ ne garde que le nom ; l'identifiant reste celui du lieu
				// choisi. Une autre précision — « Coulonces (Vire Normandie) » —
				// se garde : elle seule distingue le lieu.
				function allegerLeNom(c) {
					var m = QUALIFIE.exec(c.label);
					if (!m) { return; }
					// Après la parenthèse, que la même réponse remplit d'abord.
					getDepartmentFromArk(c.uri).then(function () {
						if (champ.value !== c.label || ark.value !== c.uri) { return; }
						if (vide(m[2]) || plier(m[2]) !== plier(sansParentheses(deptInput.value))) { return; }
						champ.value = m[1];
						majApercu();
						annoncer(guillemets(m[1]) + ' retenu' + NBSP + ': ' + guillemets(m[2]) + ' est dans la parenthèse.');
					});
				}
				var cb = armerCombobox(champ, boite, retenir);
				// « Caen » tapé en entier puis quitté par Tab restait sans
				// identifiant, alors que la liste proposait Caen. Le lieu identique
				// à une proposition — une seule : deux homonymes ne se départagent
				// pas ainsi — est retenu à la sortie du champ, ou par Entrée.
				var derniers = [];
				function prendreLIdentique() {
					if (ark.value || vide(champ.value)) { return false; }
					var c = exactDans(derniers, champ.value);
					if (c) { retenir(c); }
					return !!c;
				}
				champ.addEventListener('change', prendreLIdentique);
				champ.addEventListener('keydown', function (e) {
					if (e.key === 'Enter' && !e.defaultPrevented && prendreLIdentique()) { cb.fermer(); }
				});
				var minuterie = null, tour = 0;
				champ.addEventListener('input', function () {
					var q = champ.value.trim();
					ark.value = memeLieu(q) ? choisi.uri : '';
					derniers = [];
					majApercu();
					if (minuterie) { window.clearTimeout(minuterie); }
					var ce = ++tour;
					if (q.length < 3) { cb.fermer(); return; }
					minuterie = window.setTimeout(function () {
						chercher(PACTOLS + 'api/autocomplete/' + encodeURIComponent(pourPactols(q)) + '?lang=fr&theso=th17')
							.then(function (data) {
								if (ce !== tour) { return; }   // une réponse périmée n'écrase rien
								var vus = {}, items = [];
								// Les autres libellés d'un même lieu restent attachés
								// à lui : tapés en entier, ils le désignent.
								(Array.isArray(data) ? data : []).forEach(function (c) {
									if (!c || !c.label || !c.uri) { return; }
									if (vus[c.uri]) { vus[c.uri].autres.push(String(c.label)); return; }
									vus[c.uri] = { label: String(c.label), uri: c.uri, autres: [] };
									items.push(vus[c.uri]);
								});
								items = trierLesPropositions(items, q);
								derniers = items;
								// La réponse arrive après qu'on a quitté le champ : le
								// lieu identique se retient quand même.
								if (document.activeElement !== champ) { prendreLIdentique(); cb.fermer(); return; }
								cb.ouvrir(items.slice(0, 20), function (li, c, i) {
									var nom = document.createElement('span');
									nom.textContent = c.label;
									li.appendChild(nom);
									// Le département distingue les homonymes. Une requête
									// par proposition : on s'en tient aux cinq premières.
									if (i < 5) {
										getDepartmentFromArk(c.uri).then(function (d) { if (d) { precision(li, d); } });
									}
								});
							}, function () { if (ce === tour) { cb.fermer(); } });
					}, 250);
				});
			}
			[].forEach.call(form.querySelectorAll('.na-lieu'), armerLeLieu);
			deptInput.addEventListener('change', majApercu);
			jugerLaParenthese();

			// ── Les blocs repliés ──────────────────────────────────────────
			// Tout est dans la page, et visible sans script : le script replie ce
			// qui est vide, et ouvre d'office ce que la reprise a rempli.
			function aDesValeurs(el) {
				return [].some.call(el.querySelectorAll('input, select, textarea'), function (c) {
					if (c.type === 'hidden' || c.type === 'button') { return false; }
					if (c.type === 'checkbox' || c.type === 'radio') { return c.checked; }
					return !vide(c.value);
				});
			}
			function viderLeBloc(el) {
				[].forEach.call(el.querySelectorAll('input, select, textarea'), function (c) {
					if (c.type === 'checkbox' || c.type === 'radio') { c.checked = false; }
					else if (c.tagName === 'SELECT') { c.selectedIndex = 0; }
					else if (c.type !== 'button') { c.value = ''; }
					effacerLErreurDe(c);
				});
			}
			function premierChamp(el) {
				return el.querySelector('input:not([type=hidden]), select, textarea');
			}
			var replis = [];
			[].forEach.call(form.querySelectorAll('.na-ajout[aria-controls]'), function (bouton) {
				var bloc = document.getElementById(bouton.getAttribute('aria-controls'));
				if (!bloc) { return; }
				var retirer = bloc.querySelector('.na-retirer');
				function ouvrir(focus) {
					bloc.hidden = false;
					bouton.hidden = true;
					bouton.setAttribute('aria-expanded', 'true');
					if (focus) { var c = premierChamp(bloc); if (c) { c.focus(); } }
				}
				function fermer() {
					bloc.hidden = true;
					bouton.hidden = false;
					bouton.setAttribute('aria-expanded', 'false');
				}
				bouton.addEventListener('click', function () { ouvrir(true); });
				if (retirer) {
					retirer.hidden = false;
					retirer.addEventListener('click', function () {
						viderLeBloc(bloc);
						fermer();
						bouton.focus();
						marquerModifie();
					});
				}
				replis.push(function () { if (aDesValeurs(bloc)) { ouvrir(false); } else { fermer(); } });
			});
			[].forEach.call(form.querySelectorAll('.na-ajout[data-ajoute]'), function (bouton) {
				var nom = bouton.getAttribute('data-ajoute');
				var lignes = [].slice.call(form.querySelectorAll('.na-repli[data-repli="' + nom + '"]'));
				function maj() { bouton.hidden = lignes.every(function (l) { return !l.hidden; }); }
				bouton.addEventListener('click', function () {
					for (var i = 0; i < lignes.length; i++) {
						if (lignes[i].hidden) {
							lignes[i].hidden = false;
							var c = premierChamp(lignes[i]);
							if (c) { c.focus(); }
							break;
						}
					}
					maj();
				});
				lignes.forEach(function (ligne) {
					var retirer = ligne.querySelector('.na-retirer');
					if (!retirer) { return; }
					retirer.hidden = false;
					retirer.addEventListener('click', function () {
						viderLeBloc(ligne);
						ligne.hidden = true;
						maj();
						bouton.focus();
						majApercu();
						marquerModifie();
					});
				});
				replis.push(function () {
					lignes.forEach(function (l) { l.hidden = !aDesValeurs(l); });
					maj();
				});
			});
			function ajusterLesReplis() { replis.forEach(function (f) { f(); }); }
			ajusterLesReplis();

			// ── Mots-clés Pactols ──────────────────────────────────────────
			function pactolsSearch(type, q) {
				// URL sans /opentheso/ : ce préfixe provoquait une redirection
				// 404 sans en-têtes CORS. Les périodes se restreignent à la
				// branche P2-Entités temporelles (groupe g124).
				var theso = (type === 'place') ? 'th17' : 'TH_1';
				var url = PACTOLS + 'api/autocomplete/' + encodeURIComponent(pourPactols(q))
					+ '?lang=fr&theso=' + encodeURIComponent(theso);
				if (type === 'period') { url += '&group=g124'; }
				return chercher(url).then(function (data) {
					if (!Array.isArray(data)) { return []; }
					// Pactols rend aussi les variantes d'un concept, sous son
					// URI : « château fort » pour « château-fort ». Jetées comme
					// doublons, elles manquaient à qui tapait la variante en
					// entier, et le terme partait sans identifiant. Elles restent
					// attachées au concept.
					var seen = {}, out = [];
					data.forEach(function (item) {
						if (!item || !item.label || !item.uri) { return; }
						if (seen[item.uri]) { seen[item.uri].autres.push(String(item.label)); return; }
						seen[item.uri] = { label: String(item.label), ark: item.uri, idConcept: item.identifier || '', fullpath: '', autres: [] };
						out.push(seen[item.uri]);
					});
					return trierLesPropositions(out, q).slice(0, 20);
				});
			}
			// La forme que l'index de Pactols connaît : il ne trouve rien sous
			// l'apostrophe courbe, que posent macOS, iOS et Word, ni sous
			// « œ » ou « æ » ; « / » et « % » y font une erreur. On envoie la
			// forme pliée ; le champ garde ce que l'auteur a tapé.
			function pourPactols(q) {
				return net(String(q).replace(/[’‘ʼ`]/g, '\'').replace(/œ/g, 'oe').replace(/Œ/g, 'Oe')
					.replace(/æ/g, 'ae').replace(/Æ/g, 'Ae').replace(/[\/%]/g, ' '));
			}
			// Pactols range ses propositions par ordre alphabétique, et l'on en
			// montre vingt : « Saint-Martin (Bas-Rhin) » venait au 51e rang sur
			// 108, jamais proposé, et « château » après « basse-cour (château) ».
			// Ce qui est la saisie même passe devant, puis la saisie précisée
			// (« Saint-Martin (… ) »), puis ce qui commence par elle ; l'ordre de
			// Pactols reste pour le reste.
			function trierLesPropositions(items, q) {
				var cle = sansAccents(q);
				function rangDe(it) {
					return [it.label].concat(it.autres || []).reduce(function (r, l) {
						l = sansAccents(l);
						return Math.min(r, l === cle ? 0 : (l.indexOf(cle + ' (') === 0 ? 1 : (l.indexOf(cle) === 0 ? 2 : 3)));
					}, 3);
				}
				return items.map(function (it, i) { return [it, i, rangDe(it)]; })
					.sort(function (a, b) { return a[2] - b[2] || a[1] - b[1]; })
					.map(function (p) { return p[0]; });
			}
			// Un terme retiré du thésaurus s'indexe dans le vide. On le demande
			// une fois, au moment du choix ; rien n'est bloqué si Pactols se tait,
			// et l'avis nomme le remplaçant quand il y en a un.
			function verifierLeConcept(cat, item) {
				if (!item.idConcept || !item.ark) { return; }
				var theso = (cat.type === 'place') ? 'th17' : 'TH_1';
				chercher(PACTOLS + 'openapi/v1/concept/' + encodeURIComponent(theso) + '/' + encodeURIComponent(item.idConcept), 6000)
					.then(function (data) {
						if (!data || !data[item.ark]) { return; }
						var n = data[item.ark];
						var dep = n['http://www.w3.org/2002/07/owl#deprecated'];
						if (!dep || !dep.length) { return; }
						var rep = n['http://purl.org/dc/terms/isReplacedBy'];
						poserUnAvis(cat, item, theso, rep && rep.length ? rep[0].value : '');
					})
					.catch(function () {});
			}
			function poserUnAvis(cat, item, theso, remplacant) {
				var zone = document.createElement('p');
				zone.className = 'na-pactols-avis';
				zone.setAttribute('data-ark', item.ark);
				zone.textContent = guillemets(item.label) + ' a été retiré du thésaurus Pactols.';
				cat.fs.appendChild(zone);
				if (!remplacant) { return; }
				chercher(PACTOLS + 'openapi/v1/concept/' + encodeURIComponent(theso) + '/'
					+ encodeURIComponent(remplacant.split('/').pop()), 6000)
					.then(function (data) {
						if (!data || !data[remplacant]) { return; }
						var labels = data[remplacant]['http://www.w3.org/2004/02/skos/core#prefLabel'] || [];
						var id = data[remplacant]['http://purl.org/dc/terms/identifier'];
						var fr = '';
						labels.forEach(function (l) { if (l.lang === 'fr') { fr = l.value; } });
						if (!fr) { return; }
						zone.textContent = guillemets(item.label) + ' a été retiré du thésaurus Pactols. Il est remplacé par ' + guillemets(fr) + '. ';
						var b = document.createElement('button');
						b.type = 'button';
						b.className = 'na-pactols-remplacer';
						b.textContent = 'Prendre ' + guillemets(fr);
						b.addEventListener('click', function () {
							cat.remplacer(item, { label: fr, ark: remplacant,
								idConcept: (id && id.length) ? id[0].value : '', fullpath: '' });
							zone.remove();
							cat.champ.focus();
						});
						zone.appendChild(b);
					})
					.catch(function () {});
			}
			// La proposition dont le libellé est mot pour mot ce qu'on a tapé, à
			// la casse, aux apostrophes et aux ligatures près. Deux homonymes —
			// deux communes « Saint-Martin » — ne se départagent pas ainsi : on
			// n'en prend aucun.
			function plier(t) {
				t = net(t).replace(/[’‘ʼ`]/g, '\'').toLowerCase().replace(/œ/g, 'oe').replace(/æ/g, 'ae');
				return t.normalize ? t.normalize('NFC') : t;
			}
			function sansAccents(t) {
				t = plier(t);
				return t.normalize ? t.normalize('NFD').replace(/[̀-ͯ]/g, '') : t;
			}
			// Une variante de Pactols compte comme son concept, et l'on rend le
			// libellé qui correspond. Sans accent, « chateau » tapé puis Entrée
			// partait en terme libre alors que la liste proposait « château » :
			// à défaut d'un libellé identique, un seul qui ne diffère que par les
			// accents est pris.
			function exactDans(liste, t) {
				function trouver(plie) {
					var cle = plie(t), pareils = [];
					(liste || []).forEach(function (it) {
						if (!it || !it.label) { return; }
						var tous = [it.label].concat(it.autres || []);
						for (var i = 0; i < tous.length; i++) {
							if (plie(tous[i]) === cle) { pareils.push([it, String(tous[i])]); return; }
						}
					});
					return pareils;
				}
				var p = trouver(plier);
				if (!p.length) { p = trouver(sansAccents); }
				if (p.length !== 1) { return null; }
				var copie = {};
				Object.keys(p[0][0]).forEach(function (k) { if (k !== 'autres') { copie[k] = p[0][0][k]; } });
				copie.label = p[0][1];
				return copie;
			}
			// « Falaise (Calvados) » : le nom, puis la précision de Pactols.
			var QUALIFIE = /^(.*\S)\s*\(([^()]+)\)$/;
			function nomSansPrecision(l) {
				var m = QUALIFIE.exec(net(l));
				return m ? m[1] : net(l);
			}
			// « Caen » en autre lieu, quand Caen est déjà le lieu de
			// l'opération : la notice l'imprimait deux fois.
			function estUnLieuDuTitre(item) {
				return [].some.call(form.querySelectorAll('.na-lieu'), function (ligne) {
					var c = ligne.querySelector('.na-commune'), a = ligne.querySelector('.na-commune-ark');
					if (!c || vide(c.value)) { return false; }
					return (item.ark && a && a.value === item.ark) || plier(nomSansPrecision(c.value)) === plier(nomSansPrecision(item.label));
				});
			}
			function Categorie(fs) {
				var cat = this;
				cat.fs = fs;
				cat.type = fs.getAttribute('data-type');
				var cache = document.getElementById(fs.getAttribute('data-cible'));
				var jetons = fs.querySelector('.na-jetons');
				var champ = cat.champ = fs.querySelector('.na-kw-champ');
				var liste = fs.querySelector('.na-pactols-suggestions');
				var compte = fs.querySelector('.na-kw-compte');
				var etat = fs.querySelector('.na-kw-etat');
				var items = [];
				function dire(t) { etat.textContent = t; }
				// Le champ caché garde le JSON d'avant, tel que le serveur le lit
				// et que la reprise le rend.
				cat.relire = function () {
					items = [];
					var lu = [];
					try { lu = cache.value ? JSON.parse(cache.value) : []; } catch (e) { lu = []; }
					(Array.isArray(lu) ? lu : []).forEach(function (it) {
						if (it && it.label && items.length < KW_MAX) {
							items.push({ label: String(it.label), ark: it.ark || '', idConcept: it.idConcept || '', fullpath: it.fullpath || '' });
						}
					});
					dessiner();
				};
				function ecrire() { cache.value = JSON.stringify(items); }
				function dessiner() {
					jetons.innerHTML = '';
					items.forEach(function (it, i) {
						var li = document.createElement('li');
						li.className = 'na-jeton' + (it.ark ? '' : ' na-jeton-libre');
						var mot = document.createElement('span');
						mot.className = 'na-jeton-texte';
						mot.textContent = it.label;
						if (!it.ark) {
							var note = document.createElement('span');
							note.className = 'na-jeton-note';
							note.textContent = ' (sans identifiant)';
							mot.appendChild(note);
						}
						li.appendChild(mot);
						var b = document.createElement('button');
						b.type = 'button';
						b.className = 'na-jeton-retirer';
						b.setAttribute('aria-label', 'Retirer ' + it.label);
						b.innerHTML = '<span aria-hidden="true">×</span>';
						b.addEventListener('click', function () {
							items.splice(i, 1);
							ecrire();
							dessiner();
							dire(guillemets(it.label) + ' retiré.');
							champ.focus();
							marquerModifie();
						});
						li.appendChild(b);
						jetons.appendChild(li);
					});
					jetons.hidden = !items.length;
					compte.textContent = items.length + ' sur ' + KW_MAX;
				}
				function ajouter(item) {
					// Le champ caché ne reçoit que ce que le serveur lit : pas les
					// variantes attachées à une proposition.
					item = { label: String(item.label), ark: item.ark || '', idConcept: item.idConcept || '', fullpath: item.fullpath || '' };
					var cle = net(item.label).toLowerCase();
					var deja = items.some(function (it) {
						return (item.ark && it.ark === item.ark) || net(it.label).toLowerCase() === cle;
					});
					if (deja) {
						champ.value = '';
						dire(guillemets(item.label) + ' est déjà retenu.');
						return true;
					}
					if (items.length >= KW_MAX) {
						dire('Dix termes au plus dans cette catégorie' + NBSP + ': retirez-en un pour en ajouter un autre.');
						return false;
					}
					items.push(item);
					ecrire();
					dessiner();
					champ.value = '';
					effacerLErreurDe(champ);
					dire(guillemets(item.label) + (item.ark ? ' ajouté.' : ' ajouté, sans identifiant Pactols.')
						+ (cat.type === 'place' && estUnLieuDuTitre(item) ? ' C’est déjà un lieu de l’opération, dans le titre' + NBSP + ': il y paraîtra deux fois.' : ''));
					marquerModifie();
					verifierLeConcept(cat, item);
					return true;
				}
				cat.remplacer = function (ancien, neuf) {
					var i = items.indexOf(ancien);
					if (i === -1) { return; }
					items[i] = neuf;
					ecrire();
					dessiner();
					dire(guillemets(neuf.label) + ' remplace ' + guillemets(ancien.label) + '.');
				};
				// Les dernières propositions de Pactols, et ce qu'on avait tapé pour
				// les obtenir : un terme tapé en entier, identique à l'une d'elles,
				// est cette proposition-là.
				var derniers = [], derniersPour = '';
				// Le texte laissé dans le champ au moment d'envoyer devient un
				// terme libre : il bloquait l'envoi, par une alerte qui ne disait
				// pas où regarder. S'il est mot pour mot une proposition, il en
				// prend l'identifiant.
				cat.garderLaSaisie = function () {
					var t = net(champ.value);
					if (!t) { return true; }
					return ajouter(exactDans(derniers, t) || { label: t, ark: '', idConcept: '', fullpath: '' });
				};
				cat.ecrire = ecrire;
				var cb = armerCombobox(champ, liste, function (item) { ajouter(item); }, dire);
				function rendre(li, item, i) {
					var s = document.createElement('span');
					s.textContent = item.label;
					li.appendChild(s);
					if (cat.type === 'place' && item.ark && i < 5) {
						getDepartmentFromArk(item.ark).then(function (d) { if (d) { precision(li, d); } });
					}
				}
				var minuterie = null, tour = 0, confirmer = '';
				champ.addEventListener('input', function () {
					var q = champ.value.trim();
					confirmer = '';
					if (minuterie) { window.clearTimeout(minuterie); minuterie = null; }
					var ce = ++tour;
					if (q.length < 3) { cb.fermer(); dire(''); return; }
					minuterie = window.setTimeout(function () {
						minuterie = null;
						dire('Recherche dans Pactols…');
						pactolsSearch(cat.type, q).then(function (res) {
							if (ce !== tour) { return; }
							derniers = res;
							derniersPour = net(q);
							if (!res.length) {
								cb.fermer();
								dire('Aucun terme Pactols ne correspond. Essayez un mot plus court ou un synonyme, ou appuyez sur Entrée pour garder ' + guillemets(q) + ' tel quel.');
								return;
							}
							cb.ouvrir(res, rendre);
						}, function () {
							if (ce !== tour) { return; }
							derniers = [];
							derniersPour = net(q);
							cb.fermer();
							dire('Pactols ne répond pas pour l’instant. Appuyez sur Entrée pour garder ' + guillemets(q) + ' tel quel, sans identifiant.');
						});
					}, 300);
				});
				// Entrée sans option choisie garde le texte tel quel ; elle ne
				// soumet jamais le formulaire.
				//
				// Qui a tapé « Moyen Âge » en entier et presse Entrée, alors que la
				// liste propose « Moyen Âge », veut ce terme : il recevait un terme
				// libre, sans identifiant, que rien ne distinguait à l'œil. Le
				// terme identique est pris ; si la réponse n'est pas encore
				// arrivée, on la demande tout de suite, et l'on attend.
				//
				// Des propositions, mais aucune identique : « chateau » ou « motte
				// cast » partaient d'une seule touche en mots-clés sans
				// identifiant, publiés tels quels. La première Entrée le dit et
				// laisse la liste ouverte ; la seconde garde la saisie.
				function avertirAvantDeGarder(q) {
					confirmer = q;
					dire('Aucune proposition n’est exactement ' + guillemets(q) + NBSP + ': choisissez-en une avec les flèches, '
						+ 'ou appuyez de nouveau sur Entrée pour garder ' + guillemets(q) + ' tel quel, sans identifiant Pactols.');
				}
				champ.addEventListener('keydown', function (e) {
					if (e.key !== 'Enter' || e.defaultPrevented) { return; }
					e.preventDefault();
					if (vide(champ.value)) { return; }
					var q = net(champ.value);
					if (minuterie) { window.clearTimeout(minuterie); minuterie = null; }
					var ce = ++tour;
					var exact = exactDans(derniers, q);
					if (!exact && q.length >= 3 && derniersPour === q && derniers.length && confirmer !== q) {
						cb.ouvrir(derniers, rendre);
						avertirAvantDeGarder(q);
						return;
					}
					cb.fermer();
					if (exact || q.length < 3 || derniersPour === q) {
						confirmer = '';
						cat.garderLaSaisie();
						return;
					}
					dire('Recherche dans Pactols…');
					pactolsSearch(cat.type, q).then(function (res) {
						if (ce !== tour) { return; }
						derniers = res;
						derniersPour = q;
						dire('');
						if (res.length && !exactDans(res, q) && confirmer !== q) {
							cb.ouvrir(res, rendre);
							avertirAvantDeGarder(q);
							return;
						}
						confirmer = '';
						cat.garderLaSaisie();
					}, function () {
						if (ce !== tour) { return; }
						derniers = [];
						derniersPour = q;
						cat.garderLaSaisie();
					});
				});
				// Un texte laissé dans le champ part à l'envoi tel quel (voir
				// garderLaSaisie) : « XIe s », oublié là, devenait une période
				// publiée. Rien n'est retenu ni refusé ici ; on le dit en sortant.
				champ.addEventListener('blur', function () {
					window.setTimeout(function () {
						var t = net(champ.value);
						if (!t || document.activeElement === champ) { return; }
						dire(exactDans(derniers, t)
							? guillemets(t) + ' n’est pas encore retenu' + NBSP + ': il le sera à l’envoi, avec son identifiant Pactols.'
							: guillemets(t) + ' n’est pas retenu' + NBSP + ': choisissez un terme dans la liste, ou appuyez deux fois sur Entrée '
								+ 'pour le garder tel quel. Laissé ainsi, il partira à l’envoi sans identifiant Pactols.');
					}, 200);
				});
				cat.relire();
			}
			var categories = [].map.call(form.querySelectorAll('.na-kw'), function (fs) { return new Categorie(fs); });

			// ── Les illustrations ──────────────────────────────────────────
			var fileInput = document.getElementById('na-illustrations');
			var fileList = document.getElementById('na-filelist');
			var refuses = document.getElementById('na-fichiers-refuses');
			var zone = document.getElementById('na-depot-zone');
			var attente = document.getElementById('na-illus-attente');
			var selected = [];
			function extOf(name) {
				var parts = name.toLowerCase().split('.');
				return parts.length > 1 ? parts.pop() : '';
			}
			// Les champs d'autorisation, un par figure. Ils se gardent d'un dessin
			// de la liste à l'autre : un champ de fichier recréé perd le fichier
			// choisi, et l'auteur ne le verrait pas.
			var illusAuto = [];
			var MAX_AUTORISATION = <?php echo (int) self::MAX_AUTORISATION; ?>;
			function poidsAutorisations() {
				return illusAuto.reduce(function (sum, champ) {
					return sum + (champ && champ.files && champ.files[0] ? champ.files[0].size : 0);
				}, 0);
			}
			function totalSelectedSize() {
				return selected.reduce(function (sum, f) { return sum + f.size; }, 0) + poidsAutorisations();
			}
			function refreshFileInput() {
				try {
					var dt = new DataTransfer();
					selected.forEach(function (f) { dt.items.add(f); });
					fileInput.files = dt.files;
				} catch (e) {}
				renderFileList();
			}
			// Ce que l'auteur a écrit sur chaque illustration, gardé à part des
			// fichiers : la liste se redessine à chaque retrait, et retaper trois
			// légendes parce qu'on a enlevé une image ferait abandonner.
			//
			// Chaque texte porte le nom de son fichier. Rangé par son seul rang,
			// il passait au mauvais fichier quand on redéposait les images dans
			// un autre ordre après une reprise : « Plan du château » sous la coupe
			// du fossé, sans un mot, et publié ainsi. Il se rattache maintenant
			// par le nom ; celui dont le fichier n'est pas encore revenu attend
			// au-dessus de la zone de dépôt.
			var illusMeta = [];
			var orphelins = <?php
				$garde_illus = array();
				$combien     = isset( $reprise['illus_titre'] ) ? count( (array) $reprise['illus_titre'] ) : 0;
				for ( $i = 0; $i < $combien; $i++ ) {
					$garde_illus[] = array(
						'titre'   => isset( $reprise['illus_titre'][ $i ] ) ? $reprise['illus_titre'][ $i ] : '',
						'legende' => isset( $reprise['illus_legende'][ $i ] ) ? $reprise['illus_legende'][ $i ] : '',
						'credits' => isset( $reprise['illus_credits'][ $i ] ) ? $reprise['illus_credits'][ $i ] : '',
						'alt'     => isset( $reprise['illus_alt'][ $i ] ) ? $reprise['illus_alt'][ $i ] : '',
						'description' => isset( $reprise['illus_description'][ $i ] ) ? $reprise['illus_description'][ $i ] : '',
						'nom'     => isset( $reprise['illus_nom'][ $i ] ) ? $reprise['illus_nom'][ $i ] : '',
						// L'autorisation jointe au dépôt d'avant : le fichier ne
						// revient pas, la figure dit qu'il est à rejoindre.
						'autoAvant' => ! empty( $reprise['illus_autorisation'][ $i ] ),
						'numero'  => $i + 1,
					);
				}
				echo wp_json_encode( $garde_illus );
			?>.filter(function (m) { return m && (m.nom || aDuTexte(m)); });
			function aDuTexte(m) { return !!m && !(vide(m.titre) && vide(m.legende) && vide(m.credits) && vide(m.alt) && vide(m.description)); }
			// L'autorisation choisie pour une figure retirée la suit dans
			// l'attente, hors du brouillon : un champ de fichier ne se
			// photographie pas.
			function attacherLAutorisation(m, champ) {
				try { Object.defineProperty(m, 'auto', { value: champ, writable: true, configurable: true, enumerable: false }); } catch (e) {}
			}
			function aUneAutorisation(champ) { return !!(champ && champ.files && champ.files.length); }
			// Chaque fichier choisi reprend le texte qui porte son nom ; à défaut
			// — un brouillon d'avant les noms —, le premier texte sans nom, dans
			// l'ordre : c'était la règle d'avant, et elle ne fait pas pire.
			function rattacher() {
				var libres = illusMeta.concat(orphelins);
				var neuf = [];
				selected.forEach(function (f, i) {
					var j = -1, k;
					for (k = 0; k < libres.length && j === -1; k++) {
						if (libres[k] && libres[k].nom === f.name) { j = k; }
					}
					for (k = 0; k < libres.length && j === -1; k++) {
						if (libres[k] && !libres[k].nom && aDuTexte(libres[k])) { j = k; }
					}
					var m = j === -1 ? {} : libres.splice(j, 1)[0];
					m.nom = f.name;
					if (!m.numero) { m.numero = i + 1; }
					if (aUneAutorisation(m.auto) && !illusAuto[i]) { illusAuto[i] = m.auto; }
					neuf[i] = m;
				});
				orphelins = libres.filter(function (m) { return m && (m.nom || aDuTexte(m)); });
				illusMeta = neuf;
				dessinerLAttente();
			}
			function dessinerLAttente() {
				attente.innerHTML = '';
				if (!orphelins.length) { attente.hidden = true; return; }
				var dit = document.createElement('p');
				// « Dans n'importe quel ordre » trompait : les textes suivent le
				// nom du fichier, mais le numéro suit l'ordre des fichiers.
				dit.textContent = orphelins.length > 1
					? 'Illustrations à redéposer' + NBSP + ': leurs titres, textes alternatifs, descriptions, légendes et crédits reviendront d’eux-mêmes. Leur numéro, que le texte cite, '
						+ 'suit l’ordre des figures' + NBSP + ': choisies ensemble, elles reprennent celui d’avant.'
					: 'Illustration à redéposer' + NBSP + ': son titre, son texte alternatif, sa description, sa légende et ses crédits reviendront d’eux-mêmes.';
				var ul = document.createElement('ul');
				orphelins.forEach(function (m) {
					var li = document.createElement('li');
					var nom = document.createElement('span');
					nom.className = 'na-illus-attente-nom';
					nom.textContent = (m.nom || 'Fichier au nom inconnu') + (m.numero ? ' (fig.' + NBSP + m.numero + ')' : '');
					li.appendChild(nom);
					var texte = net(m.titre) || net(m.alt) || net(m.legende) || net(m.credits) || net(m.description);
					if (texte) {
						var ext = document.createElement('span');
						ext.textContent = guillemets(extrait(texte, 60) + (points(texte).length > 60 ? '…' : ''));
						li.appendChild(ext);
					}
					// Une autorisation jointe au dépôt précédent ne revient pas
					// avec le fichier : elle se perdait sans un mot.
					if (m.autoAvant && !aUneAutorisation(m.auto)) {
						var auto = document.createElement('span');
						auto.textContent = 'autorisation de reproduction à joindre de nouveau';
						li.appendChild(auto);
					}
					var oublier = document.createElement('button');
					oublier.type = 'button';
					oublier.className = 'na-bouton-lien';
					oublier.textContent = 'Oublier';
					oublier.setAttribute('aria-label', 'Oublier ce qui était écrit pour ' + (m.nom || 'ce fichier'));
					oublier.addEventListener('click', function () {
						orphelins.splice(orphelins.indexOf(m), 1);
						dessinerLAttente();
						marquerModifie();
						annoncer('Texte oublié.');
						(attente.querySelector('button') || fileInput).focus();
					});
					li.appendChild(oublier);
					ul.appendChild(li);
				});
				attente.appendChild(dit);
				attente.appendChild(ul);
				attente.hidden = false;
			}
			// Ce que l'on sait de chaque fichier : sa définition, lue une fois, et
			// ce que l'auteur en a déjà quitté — un avis ne paraît qu'à la sortie
			// d'un champ, jamais pendant qu'on le remplit.
			var etats = (typeof WeakMap !== 'undefined') ? new WeakMap() : null;
			var etatsDeSecours = [];
			function etatDe(f) {
				var e = etats ? etats.get(f) : null;
				if (!etats) {
					for (var i = 0; i < etatsDeSecours.length; i++) { if (etatsDeSecours[i][0] === f) { e = etatsDeSecours[i][1]; } }
				}
				if (!e) {
					e = { px: null, quittee: false, vus: {}, vignette: '' };
					if (etats) { etats.set(f, e); } else { etatsDeSecours.push([f, e]); }
				}
				return e;
			}
			var AIDES_ILLUS = {
				titre: 'Une ligne informative, qui nomme ce que montre la figure et son objet' + NBSP + ': Plan des vestiges de la chapelle et du cimetière.',
				alt: 'Pour qui ne voit pas l’image' + NBSP + ': en quelques mots, l’information visuelle utile. Il ne répète pas la légende. '
					+ 'Ne commencez pas par «' + NBSP + 'Image de' + NBSP + '» ou «' + NBSP + 'Photo de' + NBSP + '», '
					+ 'et citez un texte important visible dans l’image.',
				description: 'Pour une figure complexe' + NBSP + ': ce qu’un lecteur qui ne la voit pas doit en savoir — organisation, repères, données.',
				legende: 'Le texte qui accompagne la figure. Reprenez-y ce qu’un lecteur qui ne voit pas l’image doit savoir' + NBSP
					+ ': la clé d’un plan ou d’une carte, les inscriptions, les étiquettes, l’échelle, l’orientation.',
				credits: 'Auteur et détenteur des droits' + NBSP + ': © Prénom Nom, organisme.'
			};
			// Deux exemples de la rédaction, repliés sous l'aide du texte
			// alternatif : on les ouvre quand on hésite, ils ne s'imposent pas
			// à chaque figure.
			var EXEMPLES_ALT = [
				['Fig. 12. Plan général des vestiges de la phase 2.', 'Deux bâtiments occupent l’ouest de l’enclos fossoyé, entourés de fosses et de silos.'],
				['Fig. 8. Vue du foyer du bâtiment 1.', 'Foyer quadrangulaire installé contre la paroi nord du bâtiment.']
			];
			function exemplesDuTexteAlternatif() {
				var d = document.createElement('details');
				d.className = 'na-exemples';
				var s = document.createElement('summary');
				s.textContent = 'Exemples';
				d.appendChild(s);
				EXEMPLES_ALT.forEach(function (x) {
					var p = document.createElement('p');
					p.appendChild(document.createTextNode('Légende' + NBSP + ': ' + guillemetsA11y(x[0])));
					p.appendChild(document.createElement('br'));
					p.appendChild(document.createTextNode('Texte alternatif' + NBSP + ': ' + guillemetsA11y(x[1])));
					d.appendChild(p);
				});
				var rappel = document.createElement('p');
				rappel.textContent = 'Le texte alternatif ne répète pas la légende' + NBSP + ': il restitue l’information visuelle utile.';
				d.appendChild(rappel);
				return d;
			}
			function guillemetsA11y(t) { return '«' + NBSP + t + NBSP + '»'; }
			// Les trois champs sont facultatifs, et l'introduction dit que ce qui
			// l'est porte la mention : ils ne la portaient pas. Le texte
			// alternatif, lui, est obligatoire : sans lui, la figure n'existe
			// pas pour qui ne la voit pas.
			function champIllustration(idx, clef, libelle, lignes) {
				var bloc = document.createElement('div');
				var id = 'na-illus-' + (idx + 1) + '-' + clef;
				var etiquette = document.createElement('label');
				etiquette.htmlFor = id;
				etiquette.innerHTML = libelle + (clef === 'alt' ? '' : ' <span class="na-facultatif">(facultatif)</span>');
				var aide = document.createElement('p');
				aide.className = 'na-help';
				aide.id = id + '-aide';
				aide.textContent = AIDES_ILLUS[clef];
				var champ = document.createElement(lignes ? 'textarea' : 'input');
				var compteur = null, compter = null;
				if (lignes) { champ.rows = lignes; } else { champ.type = 'text'; }
				champ.id = id;
				champ.name = 'illus_' + clef + '[]';
				champ.setAttribute('aria-describedby', aide.id);
				champ.value = illusMeta[idx] && illusMeta[idx][clef] ? illusMeta[idx][clef] : '';
				champ.addEventListener('input', function () {
					if (!illusMeta[idx]) { illusMeta[idx] = {}; }
					illusMeta[idx][clef] = champ.value;
				});
				if (clef === 'alt') {
					champ.required = true;
					champ.maxLength = ALT_MAX;
					champ.setAttribute('autocomplete', 'off');
					// Un compteur sous le champ : la longueur conseillée se voit
					// avant l'avis.
					compteur = document.createElement('p');
					compteur.className = 'na-help na-compteur';
					compteur.id = id + '-compteur';
					compter = function () {
						var n = points(champ.value).length;
						compteur.textContent = entier(n) + NBSP + '/' + NBSP + entier(ALT_MAX) + ' caractères'
							+ (n > ALT_CONSEILLE ? ' — plus de ' + ALT_CONSEILLE + ', c’est long' : ', ' + ALT_CONSEILLE + ' au plus conseillés');
					};
					champ.addEventListener('input', compter);
					champ.setAttribute('aria-describedby', aide.id + ' ' + compteur.id);
				}
				champ.addEventListener('blur', function () {
					avisDeLongueur(champ, { legende: 1500, alt: ALT_MAX, description: DESCRIPTION_MAX }[clef] || 400,
						{ titre: 'Le titre', legende: 'La légende', credits: 'Le crédit', alt: 'Le texte alternatif', description: 'La description détaillée' }[clef]);
				});
				bloc.appendChild(etiquette);
				bloc.appendChild(aide);
				if (clef === 'alt') { bloc.appendChild(exemplesDuTexteAlternatif()); }
				bloc.appendChild(champ);
				if (compteur) { bloc.appendChild(compteur); compter(); }
				return { bloc: bloc, champ: champ };
			}
			function champAutorisation(idx) {
				var bloc = document.createElement('div');
				bloc.className = 'na-illus-autorisation';
				var id = 'na-illus-' + (idx + 1) + '-autorisation';
				if (!illusAuto[idx]) {
					var nouveau = document.createElement('input');
					nouveau.type = 'file';
					nouveau.name = 'illus_autorisation[]';
					nouveau.accept = '.pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png';
					nouveau.addEventListener('change', function () {
						var f = nouveau.files && nouveau.files[0];
						var refus = '';
						if (f && ['pdf', 'jpg', 'jpeg', 'png'].indexOf(extOf(f.name)) === -1) {
							refus = guillemets(f.name) + ' n’est pas en PDF, JPEG ou PNG.';
						} else if (f && f.size > MAX_AUTORISATION) {
							refus = guillemets(f.name) + ' dépasse ' + moPlafond(MAX_AUTORISATION) + '.';
						} else if (f && totalSelectedSize() > MAX_TOTAL_SIZE) {
							refus = guillemets(f.name) + ' ferait dépasser ' + moPlafond(MAX_TOTAL_SIZE) + ' en tout.';
						}
						if (refus) { nouveau.value = ''; }
						refuses.textContent = refus;
						marquerModifie();
					});
					illusAuto[idx] = nouveau;
				}
				var champ = illusAuto[idx];
				champ.id = id;
				var etiquette = document.createElement('label');
				etiquette.htmlFor = id;
				etiquette.innerHTML = 'Autorisation de reproduction <span class="na-facultatif">(facultatif)</span>';
				var aide = document.createElement('p');
				aide.className = 'na-help';
				aide.id = id + '-aide';
				aide.textContent = 'Seulement si vous ne détenez pas les droits de cette figure' + NBSP
					+ ': l’accord écrit de leur détenteur, en PDF, JPEG ou PNG.'
					+ (illusMeta[idx] && illusMeta[idx].autoAvant && !aUneAutorisation(champ)
						? ' Une autorisation était jointe à cette figure lors du dépôt précédent' + NBSP + ': joignez-la de nouveau, elle ne revient pas d’elle-même.'
						: '');
				champ.setAttribute('aria-describedby', aide.id);
				bloc.appendChild(etiquette);
				bloc.appendChild(aide);
				bloc.appendChild(champ);
				return bloc;
			}
			// Les cartes affichées, pour y reposer les avis sans tout redessiner.
			var cartes = [];
			function renderFileList() {
				fileList.innerHTML = '';
				cartes = [];
				selected.forEach(function (f, idx) {
					if (!illusMeta[idx]) { illusMeta[idx] = {}; }
					illusMeta[idx].nom = f.name;
					if (!illusMeta[idx].numero) { illusMeta[idx].numero = idx + 1; }
					var etat = etatDe(f);
					var li = document.createElement('li');
					li.className = 'na-illus-carte';
					var groupe = document.createElement('fieldset');
					groupe.className = 'na-illus';
					var leg = document.createElement('legend');
					leg.textContent = 'Figure ' + (idx + 1) + ' ';
					var nom = document.createElement('span');
					nom.className = 'na-illus-fichier';
					nom.textContent = '— ' + f.name + ' (' + mo(f.size) + ')';
					leg.appendChild(nom);
					function boutonDeCarte(classe, mot, action) {
						var b = document.createElement('button');
						b.type = 'button';
						b.className = classe;
						b.textContent = mot;
						var sr = document.createElement('span');
						sr.className = 'na-sr';
						sr.textContent = ' la figure ' + (idx + 1) + ' (' + f.name + ')';
						b.appendChild(sr);
						b.addEventListener('click', action);
						return b;
					}
					var rm = boutonDeCarte('na-fileremove', 'Retirer', function () {
						selected.splice(idx, 1);
						var parti = illusMeta.splice(idx, 1)[0];
						var auto = illusAuto.splice(idx, 1)[0];
						// Ce qui était écrit sur la figure attend son fichier, comme
						// à une reprise : rajouté, il le retrouve. Titre, légende,
						// crédits et autorisation se perdaient, et la figure
						// revenait vide.
						if (parti && (aDuTexte(parti) || aUneAutorisation(auto))) {
							if (aUneAutorisation(auto)) { attacherLAutorisation(parti, auto); }
							orphelins.push(parti);
							dessinerLAttente();
						}
						refreshFileInput();
						annoncer('Figure ' + (idx + 1) + ' retirée.' + (idx < selected.length
							? ' Les suivantes changent de numéro' + NBSP + ': vérifiez les appels du texte.' : ''));
						(fileList.querySelector('.na-fileremove') || fileInput).focus();
						marquerModifie();
						majConseilsDuTexte();
					});
					groupe.appendChild(leg);
					groupe.appendChild(rm);
					// L'ordre des figures se change en glissant la carte par sa
					// poignée, ou, au clavier et au doigt, par Monter et Descendre.
					if (selected.length > 1) {
						var outils = document.createElement('div');
						outils.className = 'na-illus-outils';
						var poignee = document.createElement('span');
						poignee.className = 'na-illus-poignee';
						poignee.setAttribute('aria-hidden', 'true');
						poignee.title = 'Glisser pour changer l’ordre des figures';
						poignee.textContent = '↕ Glisser';
						poignee.draggable = true;
						outils.appendChild(poignee);
						if (idx > 0) {
							outils.appendChild(boutonDeCarte('na-bouton-lien na-illus-monter', 'Monter', function () {
								deplacerLaFigure(idx, idx - 1, 'na-illus-monter');
							}));
						}
						if (idx < selected.length - 1) {
							outils.appendChild(boutonDeCarte('na-bouton-lien na-illus-descendre', 'Descendre', function () {
								deplacerLaFigure(idx, idx + 1, 'na-illus-descendre');
							}));
						}
						groupe.appendChild(outils);
						// La poignée seule se glisse : une carte entière rendue
						// glissable gênait la sélection du texte dans ses champs.
						poignee.addEventListener('dragstart', function (e) {
							carteGlissee = idx;
							li.classList.add('na-illus-glissee');
							try {
								e.dataTransfer.effectAllowed = 'move';
								e.dataTransfer.setData(FIGURE_GLISSEE, String(idx));
								e.dataTransfer.setData('text/plain', f.name);
								if (e.dataTransfer.setDragImage) { e.dataTransfer.setDragImage(groupe, 24, 24); }
							} catch (x) {}
						});
						poignee.addEventListener('dragend', function () {
							carteGlissee = -1;
							[].forEach.call(fileList.querySelectorAll('.na-illus-glissee, .na-illus-cible'), function (l) {
								l.classList.remove('na-illus-glissee');
								l.classList.remove('na-illus-cible');
							});
						});
						li.addEventListener('dragover', function (e) {
							if (carteGlissee < 0 && !porteUneFigure(e)) { return; }
							e.preventDefault();
							try { e.dataTransfer.dropEffect = 'move'; } catch (x) {}
							if (carteGlissee !== idx) { li.classList.add('na-illus-cible'); }
						});
						li.addEventListener('dragleave', function (e) {
							if (!(e.relatedTarget && li.contains(e.relatedTarget))) { li.classList.remove('na-illus-cible'); }
						});
						li.addEventListener('drop', function (e) {
							if (carteGlissee < 0 && !porteUneFigure(e)) { return; }
							e.preventDefault();
							var de = carteGlissee;
							if (de < 0) { try { de = parseInt(e.dataTransfer.getData(FIGURE_GLISSEE), 10); } catch (x) { de = -1; } }
							carteGlissee = -1;
							if (!isNaN(de)) { deplacerLaFigure(de, idx, ''); }
						});
					}
					// Une vignette, pour qu'on sache de quelle image on écrit la
					// légende : « Figure 1 », c'est ce que le texte cite. Un TIFF ne
					// s'affiche pas dans un navigateur ; il garde son seul nom.
					if (/^image\/(jpeg|png)$/.test(f.type) && window.URL && URL.createObjectURL) {
						if (!etat.vignette) { etat.vignette = URL.createObjectURL(f); }
						var im = document.createElement('img');
						im.src = etat.vignette;
						im.alt = '';
						im.className = 'na-illus-vignette';
						groupe.appendChild(im);
					}
					var champs = document.createElement('div');
					champs.className = 'na-illus-champs';
					var titre = champIllustration(idx, 'titre', 'Titre', 0);
					var alt = champIllustration(idx, 'alt', 'Texte alternatif', 0);
					var description = champIllustration(idx, 'description', 'Description détaillée', 3);
					var legende = champIllustration(idx, 'legende', 'Légende', 2);
					var credits = champIllustration(idx, 'credits', 'Crédits', 0);
					champs.appendChild(titre.bloc);
					champs.appendChild(alt.bloc);
					champs.appendChild(description.bloc);
					champs.appendChild(legende.bloc);
					champs.appendChild(credits.bloc);
					champs.appendChild(champAutorisation(idx));
					var conseils = document.createElement('div');
					conseils.className = 'na-conseils';
					conseils.id = 'na-illus-' + (idx + 1) + '-conseils';
					conseils.hidden = true;
					groupe.appendChild(champs);
					groupe.appendChild(conseils);
					// L'avis « rien n'est dit » paraissait dès le choix du fichier,
					// avant qu'on ait pu écrire un mot : il attend qu'on ait quitté
					// la carte.
					groupe.addEventListener('focusout', function (e) {
						var cle = /^illus_(titre|legende|credits|alt|description)/.exec(e.target.name || '');
						if (cle) { etat.vus[cle[1]] = true; }
						if (!(e.relatedTarget && groupe.contains(e.relatedTarget))) { etat.quittee = true; }
						majFigure(idx);
					});
					li.appendChild(groupe);
					fileList.appendChild(li);
					cartes[idx] = { zone: conseils, titre: titre.champ, alt: alt.champ, description: description.champ, legende: legende.champ, credits: credits.champ };
					majFigure(idx);
				});
				// Les cartes viennent d'être refaites : une erreur de texte
				// alternatif se repose sur le champ neuf, ou s'efface si la
				// figure qui la portait est partie ou décrite.
				revoirLesTextesAlternatifs();
				if (selected.length > 0) {
					var total = document.createElement('li');
					total.className = 'na-illus-total';
					total.textContent = 'Total' + NBSP + ': ' + mo(totalSelectedSize()) + ' sur ' + moPlafond(MAX_TOTAL_SIZE);
					fileList.appendChild(total);
				}
				// Pour la figure suivante, on n'a plus à remonter jusqu'à la zone
				// de dépôt : le même sélecteur, qui ajoute sans rien remplacer.
				ajoutFigure.hidden = !(selected.length > 0 && selected.length < MAX_FILES);
				majNumeros();
			}
			// La carte qu'on glisse ; le même rang voyage aussi dans les données
			// du glisser, que certains navigateurs livrent après « dragend ».
			var carteGlissee = -1, FIGURE_GLISSEE = 'application/x-na-figure';
			function porteUneFigure(e) {
				var t = e.dataTransfer && e.dataTransfer.types;
				return !!t && [].indexOf.call(t, FIGURE_GLISSEE) !== -1;
			}
			var ajoutFigure = document.getElementById('na-ajout-figure');
			ajoutFigure.addEventListener('click', function () { fileInput.click(); });
			// Le fichier, ses textes et son autorisation changent de place
			// ensemble : les trois listes restent alignées, et le champ de
			// fichiers suit le même ordre que les champs « illus_*[] ».
			function deplacerLaFigure(de, vers, focus) {
				var n = selected.length;
				if (de === vers || de < 0 || vers < 0 || de >= n || vers >= n) { return; }
				while (illusAuto.length < n) { illusAuto.push(undefined); }
				[selected, illusMeta, illusAuto].forEach(function (t) {
					var x = t.splice(de, 1)[0];
					t.splice(vers, 0, x);
				});
				refreshFileInput();
				marquerModifie();
				majConseilsDuTexte();
				annoncer('La figure ' + (de + 1) + ' est maintenant la figure ' + (vers + 1) + '.');
				if (focus) {
					var carte = fileList.querySelectorAll('.na-illus-carte')[vers];
					var b = carte && (carte.querySelector('.' + focus) || carte.querySelector('.na-illus-monter, .na-illus-descendre'));
					if (b) { b.focus(); }
				}
			}
			// Le numéro d'une figure suit sa place, et le texte l'appelle par ce
			// numéro : retirée, rajoutée, déplacée, redéposée dans un autre
			// ordre, une figure en changeait sans un mot, et « le plan (fig. 1) »
			// paraissait sous la vue. On le dit jusqu'à ce que l'auteur ait
			// revu ses appels.
			var zoneNumeros = zoneDeConseils('na-illus-numeros', fileList);
			function majNumeros() {
				var changes = [];
				selected.forEach(function (f, i) {
					var m = illusMeta[i];
					if (m && m.numero && m.numero !== i + 1) {
						changes.push(f.name + ', qui était la fig.' + NBSP + m.numero + ', est maintenant la fig.' + NBSP + (i + 1));
					}
				});
				montrerConseils(zoneNumeros, changes.length ? [{
					texte: (changes.length > 1 ? 'Des figures ont changé de numéro' : 'Une figure a changé de numéro') + NBSP + ': '
						+ changes.join(NBSP + '; ') + '. Le texte les appelle par leur numéro' + NBSP + ': vérifiez ses appels.',
					bouton: 'Les appels du texte sont à jour',
					action: function () {
						illusMeta.forEach(function (m, i) { if (m) { m.numero = i + 1; } });
						marquerModifie();
						majNumeros();
						annoncer('Numéros des figures notés comme vérifiés.');
						(ajoutFigure.hidden ? fileInput : ajoutFigure).focus();
					}
				}] : [], [fileInput]);
			}
			// Les avis d'accessibilité d'une figure, comme au serveur
			// (Notice_Archeomed_Controles::accessibilite_de_la_figure) : mêmes
			// phrases, mêmes motifs, mêmes seuils. « vus » dit quels champs
			// l'auteur a déjà quittés : un avis ne paraît qu'après l'un de ceux
			// qu'il regarde.
			var MOTIFS_A11Y = {};
			Object.keys(A11Y.motifs).forEach(function (k) {
				MOTIFS_A11Y[k] = new RegExp(A11Y.motifs[k], k === 'extension' ? 'i' : (k === 'numero' ? 'g' : ''));
			});
			function plierA11y(t) {
				t = String(t || '').replace(/œ/g, 'oe').replace(/Œ/g, 'OE').replace(/æ/g, 'ae').replace(/Æ/g, 'AE').replace(/ß/g, 'ss');
				if (t.normalize) { t = t.normalize('NFD').replace(/[̀-ͯ]/g, ''); }
				return t.replace(/[^a-zA-Z0-9]+/g, ' ').toLowerCase().trim();
			}
			function phraseA11y(cle, valeurs) {
				return String(A11Y.phrases[cle] || '').replace(/\{(\w+)\}/g, function (tout, nom) {
					return valeurs && valeurs[nom] !== undefined ? String(valeurs[nom]) : tout;
				});
			}
			function reprendA11y(alt, autre) {
				var sans = function (t) { return plierA11y(t).replace(MOTIFS_A11Y.numero, ' ').replace(/ +/g, ' ').trim(); };
				var a = sans(alt), b = sans(autre);
				if (!a || !b) { return false; }
				if (a === b) { return true; }
				var ma = a.split(' '), mb = b.split(' ');
				if (Math.min(ma.length, mb.length) < A11Y.repriseMin) { return false; }
				if ((' ' + b + ' ').indexOf(' ' + a + ' ') !== -1 || (' ' + a + ' ').indexOf(' ' + b + ' ') !== -1) { return true; }
				var uniques = function (l) { return l.filter(function (w, i) { return l.indexOf(w) === i; }); };
				var ua = uniques(ma), ub = uniques(mb), communs = 0;
				ua.forEach(function (w) { if (ub.indexOf(w) !== -1) { communs++; } });
				return 2 * communs / (ua.length + ub.length) >= A11Y.repriseDice;
			}
			function nomDeFichierA11y(alt, fichier) {
				var plie = plierA11y(alt);
				if (MOTIFS_A11Y.extension.test(alt) || MOTIFS_A11Y.appareil.test(plie)) { return true; }
				fichier = net(fichier);
				if (!fichier || !plie) { return false; }
				return plie === plierA11y(fichier) || plie === plierA11y(fichier.replace(/\.[A-Za-z0-9]{1,5}$/, ''));
			}
			function avisDAccessibilite(m, rang, vus, fichier) {
				if (!A11Y.actif) { return []; }
				var alt = net(m.alt), titre = net(m.titre), legende = net(m.legende), phrases = [], trouve;
				var longueur = points(alt).length;
				if (vus.alt && longueur > A11Y.conseille) {
					phrases.push(phraseA11y('longueur', { n: longueur, max: A11Y.conseille }));
				}
				if (alt) {
					if (nomDeFichierA11y(alt, fichier)) {
						if (vus.alt) { phrases.push(phraseA11y('fichier', { texte: alt })); }
					} else if (MOTIFS_A11Y.sansContenu.test(plierA11y(alt))) {
						if (vus.alt) { phrases.push(phraseA11y('sans_contenu', { texte: alt })); }
					} else {
						if ((vus.alt || vus.titre) && titre && reprendA11y(alt, titre)) { phrases.push(phraseA11y('titre')); }
						if ((vus.alt || vus.legende) && legende && reprendA11y(alt, legende)) { phrases.push(phraseA11y('legende')); }
						if (vus.alt && /^(image|photo|photographie|illustration)\s+(de|du|des|d’|d')/i.test(alt)) {
							phrases.push(phraseA11y('debut', { debut: alt.split(/\s+/).slice(0, 2).join(' ') }));
						}
					}
				}
				// Une information portée par la seule couleur ne passe ni à qui
				// ne voit pas l'image, ni à qui ne distingue pas les couleurs.
				if ((vus.alt || vus.legende) && (trouve = COULEUR_SEULE.exec(alt + ' | ' + legende))) {
					phrases.push(phraseA11y('couleur', { couleur: trouve[0] }));
				}
				if ((vus.alt || vus.titre || vus.legende || vus.description) && vide(m.description)
					&& (trouve = MOTIFS_A11Y.complexe.exec(plierA11y(titre + ' ' + legende + ' ' + alt)))) {
					var mot = trouve[1];
					A11Y.complexes.forEach(function (c) { if (plierA11y(c) === trouve[1]) { mot = c; } });
					phrases.push(phraseA11y('description', { mot: mot }));
				}
				return phrases.map(function (p) { return NORMES.figure + ' ' + rang + NBSP + ': ' + p; });
			}
			// Les avis d'une figure : sa définition, le numéro retapé, le crédit
			// resté dans la légende, les crédits qui manquent.
			function majFigure(idx) {
				var c = cartes[idx], f = selected[idx];
				if (!c || !f) { return; }
				var e = etatDe(f), m = illusMeta[idx] || {}, rang = idx + 1, liste = [];
				if (e.px) {
					var avis = avisDeDefinition(rang, e.px);
					if (avis) { liste.push(avis); }
				}
				if (e.vus.titre) {
					var numero = numeroRetape(m.titre, rang);
					if (numero) { liste.push(numero); }
				}
				var deplacer = null;
				['titre', 'legende'].forEach(function (clef) {
					if (deplacer || !e.vus[clef] || !vide(m.credits)) { return; }
					var coupe = creditEnFin(m[clef] || '');
					if (!coupe) { return; }
					deplacer = {
						texte: (clef === 'titre' ? 'Le titre' : 'La légende') + ' semble finir par un crédit ('
							+ guillemets(coupe.credit) + ')' + NBSP + ': sa place est dans Crédits.',
						bouton: 'Déplacer dans Crédits',
						action: function () {
							var source = clef === 'titre' ? c.titre : c.legende;
							source.value = coupe.reste;
							c.credits.value = coupe.credit;
							m[clef] = coupe.reste;
							m.credits = coupe.credit;
							illusMeta[idx] = m;
							marquerModifie();
							majFigure(idx);
							annoncer('Crédit déplacé dans le champ Crédits de la figure ' + rang + '.');
							c.credits.focus();
						}
					};
				});
				if (deplacer) { liste.push(deplacer); }
				// L'accessibilité de la figure : les avis du serveur, mot pour
				// mot, chacun paru une fois quitté l'un des champs qu'il regarde.
				avisDAccessibilite(m, rang, e.vus, f.name).forEach(function (phrase) { liste.push(phrase); });
				if (e.quittee && !deplacer) {
					if (!aDuTexte(m)) {
						liste.push('Rien n’est encore dit de cette figure' + NBSP + ': elle partira quand même, mais sans titre ni légende.');
					} else if (vide(m.credits) && avisActif('credits')) {
						liste.push(NORMES.figure + NBSP + rang + NBSP + ': pas de crédits (auteur, détenteur des droits).');
					}
				}
				montrerConseils(c.zone, liste, [c.titre, c.alt, c.description, c.legende, c.credits]);
			}
			fileInput.addEventListener('change', function () {
				var incoming = Array.prototype.slice.call(fileInput.files);
				var refused = [];
				var ajoutes = [];
				incoming.forEach(function (f) {
					var dup = selected.some(function (s) { return s.name === f.name && s.size === f.size; });
					if (dup) { return; }
					if (selected.length >= MAX_FILES) { refused.push(guillemets(f.name) + ' n’a pas été ajouté' + NBSP + ': trois fichiers au plus.'); return; }
					if (ALLOWED.indexOf(extOf(f.name)) === -1) { refused.push(guillemets(f.name) + ' n’est pas en JPEG, TIFF ou PDF.'); return; }
					if (f.size > MAX_FILE_SIZE) { refused.push(guillemets(f.name) + ' dépasse ' + moPlafond(MAX_FILE_SIZE) + ', le plus que le serveur accepte par fichier.'); return; }
					if ((totalSelectedSize() + f.size) > MAX_TOTAL_SIZE) { refused.push(guillemets(f.name) + ' ferait dépasser ' + moPlafond(MAX_TOTAL_SIZE) + ' en tout.'); return; }
					selected.push(f);
					ajoutes.push(f);
				});
				// Redéposées ensemble, les illustrations attendues reprennent
				// l'ordre de leur numéro, quel que soit celui de la boîte de
				// dialogue, qui dépend du système et du geste.
				if (ajoutes.length > 1) {
					var rangDAttente = function (f) {
						for (var k = 0; k < orphelins.length; k++) {
							if (orphelins[k] && orphelins[k].nom === f.name) { return orphelins[k].numero || (100 + k); }
						}
						return 1000 + ajoutes.indexOf(f);
					};
					var ranges = ajoutes.slice().sort(function (a, b) { return rangDAttente(a) - rangDAttente(b); });
					selected = selected.slice(0, selected.length - ajoutes.length).concat(ranges);
					ajoutes = ranges;
				}
				rattacher();
				refreshFileInput();
				refuses.textContent = refused.join(' ');
				majConseilsDuTexte();
				// La définition se lit dans l'en-tête, sans charger l'image.
				ajoutes.forEach(function (f) {
					lireLaDefinition(f).then(function (px) {
						if (!px) { return; }
						etatDe(f).px = px;
						var i = selected.indexOf(f);
						if (i !== -1) { majFigure(i); }
					});
				});
			});
			['dragenter', 'dragover'].forEach(function (t) { zone.addEventListener(t, function () { zone.classList.add('na-survol'); }); });
			['dragleave', 'drop'].forEach(function (t) { zone.addEventListener(t, function () { zone.classList.remove('na-survol'); }); });
			dessinerLAttente();

			// ── La définition des images ───────────────────────────────────
			// L'aide annonçait une norme que rien ne vérifiait : on découvrait à
			// la mise en page qu'une photographie de 800 pixels ne tiendrait qu'en
			// timbre-poste. C'est le nombre de pixels qui décide, non la densité
			// inscrite dans le fichier. On ne lit que l'en-tête — quelques octets
			// —, jamais l'image entière. Les PDF ne se lisent pas ainsi : on les
			// laisse.
			function tranche(f, debut, fin) {
				var b = f.slice(debut, fin);
				if (b.arrayBuffer) { return b.arrayBuffer().then(function (a) { return new DataView(a); }); }
				return new Promise(function (ok, ko) {
					var r = new FileReader();
					r.onload = function () { ok(new DataView(r.result)); };
					r.onerror = ko;
					r.readAsArrayBuffer(b);
				});
			}
			function lireLaDefinition(f) {
				var ext = extOf(f.name);
				var lu = (ext === 'jpg' || ext === 'jpeg') ? definitionJpeg(f)
					: ((ext === 'tif' || ext === 'tiff') ? definitionTiff(f) : Promise.resolve(null));
				return lu.catch(function () { return null; });
			}
			// Un JPEG est une suite de segments ; la taille est dans le premier
			// segment « SOF », souvent après l'EXIF et le profil de couleur. On
			// saute de segment en segment, en ne lisant que leurs en-têtes.
			function definitionJpeg(f) {
				var tours = 0;
				function segment(pos) {
					if (++tours > 300 || pos + 4 > f.size) { return Promise.resolve(null); }
					return tranche(f, pos, pos + 9).then(function (d) {
						if (d.byteLength < 4 || d.getUint8(0) !== 0xFF) { return null; }
						var marque = d.getUint8(1);
						if (marque === 0xFF) { return segment(pos + 1); }
						if (marque === 0x01 || (marque >= 0xD0 && marque <= 0xD8)) { return segment(pos + 2); }
						if (marque === 0xD9 || marque === 0xDA) { return null; }
						if (marque >= 0xC0 && marque <= 0xCF && marque !== 0xC4 && marque !== 0xC8 && marque !== 0xCC) {
							return d.byteLength < 9 ? null : { largeur: d.getUint16(7), hauteur: d.getUint16(5) };
						}
						return segment(pos + 2 + d.getUint16(2));
					});
				}
				return tranche(f, 0, 2).then(function (d) {
					return (d.byteLength === 2 && d.getUint16(0) === 0xFFD8) ? segment(2) : null;
				});
			}
			// Un TIFF dit son ordre des octets (« II » ou « MM »), puis où commence
			// son premier répertoire, où les balises 256 et 257 portent la
			// largeur et la hauteur.
			function definitionTiff(f) {
				return tranche(f, 0, 8).then(function (d) {
					if (d.byteLength < 8) { return null; }
					var ordre = d.getUint16(0);
					var petit = ordre === 0x4949;
					if (!petit && ordre !== 0x4D4D) { return null; }
					if (d.getUint16(2, petit) !== 42) { return null; }   // 43 : BigTIFF, qu'on laisse
					var ifd = d.getUint32(4, petit);
					return tranche(f, ifd, ifd + 2).then(function (c) {
						if (c.byteLength < 2) { return null; }
						var n = Math.min(c.getUint16(0, petit), 500);
						return tranche(f, ifd + 2, ifd + 2 + n * 12).then(function (t) {
							var l = 0, h = 0;
							for (var i = 0; i + 12 <= t.byteLength; i += 12) {
								var balise = t.getUint16(i, petit), type = t.getUint16(i + 2, petit);
								var v = type === 3 ? t.getUint16(i + 8, petit) : (type === 4 ? t.getUint32(i + 8, petit) : 0);
								if (balise === 256) { l = v; } else if (balise === 257) { h = v; }
							}
							return l && h ? { largeur: l, hauteur: h } : null;
						});
					});
				});
			}
			function avisDeDefinition(rang, px) {
				var l = px.largeur, h = px.hauteur;
				if (!avisActif('definition')) { return ''; }
				if (Math.min(l, h) >= NORME.petit && Math.max(l, h) >= NORME.grand) { return ''; }
				function cm(p) { return decimal1(p / NORME.ppp * 2.54); }
				return NORMES.figure + NBSP + rang + NBSP + ': ' + entier(l) + NBSP + '×' + NBSP + entier(h) + NBSP + 'pixels, soit '
					+ cm(l) + NBSP + '×' + NBSP + cm(h) + NBSP + 'cm à ' + entier(NORME.ppp) + NBSP + 'ppp — sous la norme de '
					+ entier(NORME.largeurCm) + NBSP + '×' + NBSP + entier(NORME.hauteurCm) + NBSP
					+ 'cm. À vérifier s’il s’agit d’une photographie.';
			}

			// ── Les champs des figures ─────────────────────────────────────
			// « Fig. 1 : Plan » donnait « Fig. 1 Fig. 1 : Plan » : le numéro
			// s'ajoute de lui-même.
			var DEJA_NUMEROTE = motif('^\\s*((?:fig(?:ure)?s?|ill(?:ustration)?s?|pl(?:anche)?s?|tabl(?:eau)?x?)\\.?\\s*(\\d+))', 'iu');
			function numeroRetape(titre, rang) {
				var m = DEJA_NUMEROTE ? DEJA_NUMEROTE.exec(titre || '') : null;
				if (!m) { return ''; }
				return parseInt(m[2], 10) === rang
					? guillemets(net(m[1])) + ' s’ajoute de lui-même devant le titre' + NBSP + ': inutile de le taper.'
					: 'Le titre commence par ' + guillemets(net(m[1])) + ', mais c’est la figure ' + rang
						+ NBSP + ': le numéro s’ajoute de lui-même, d’après l’ordre des fichiers. Retirez-le du titre.';
			}
			// Un crédit resté en fin de légende. Il doit clore le texte et venir
			// après une légende d'une certaine longueur : une parenthèse au
			// milieu — « L'orthostate (photo S. Cassen) vu depuis le sud » — est
			// une incise, et une parenthèse jamais fermée ne tranche rien. Hors
			// parenthèse, le mot veut sa capitale : « en photo » est de la phrase.
			var CREDIT_AU_MOINS = 20;
			var CREDITS = [
				motif('©', 'y'),
				motif('\\((?:©|photo|cliché|dao|dessin|relevé|source|crédit)(?![\\p{L}\\d])', 'iuy'),
				motif('(?:Photo|Cliché|DAO|Dessin|Relevé|Source|Crédit)(?!\\p{L})', 'uy')
			].filter(Boolean);
			function vaJusquAuBout(t, depart) {
				var avant = t.slice(0, depart);
				if (avant.split('(').length > avant.split(')').length) { return false; }
				if (t.charAt(depart) !== '(') { return true; }
				var profondeur = 0;
				for (var i = depart; i < t.length; i++) {
					if (t.charAt(i) === '(') { profondeur++; }
					else if (t.charAt(i) === ')') {
						profondeur--;
						if (profondeur === 0) { return !t.slice(i + 1).replace(/[\s.;,:!?…»\u00A0\u202F]+/g, ''); }
					}
				}
				return false;
			}
			function creditEnFin(t) {
				t = String(t || '').replace(/\s+$/, '');
				for (var i = CREDIT_AU_MOINS; i < t.length; i++) {
					for (var k = 0; k < CREDITS.length; k++) {
						CREDITS[k].lastIndex = i;
						if (CREDITS[k].test(t) && vaJusquAuBout(t, i)) {
							var credit = t.slice(i).trim();
							if (credit.charAt(0) === '(') { credit = credit.replace(/^\(\s*/, '').replace(/\s*\)[\s.]*$/, ''); }
							var reste = t.slice(0, i).replace(/[\s,;:—–-]+$/, '');
							return credit && reste ? { credit: credit, reste: reste } : null;
						}
					}
				}
				return null;
			}

			// ── Les avis sur le texte ──────────────────────────────────────
			// Les mêmes règles que le serveur, dans le même ordre, avec les mêmes
			// mots : ce que la rédaction lira dans le courriel, l'auteur le lit
			// ici avant d'envoyer.
			var PONCTUATION_FINALE = '.!?…:;';
			var FERMANTS = '»"\'’”)] \u00A0\u202F\u2009\u2007\u2008\u200A\u2002\u2003\u2005';
			var OUVRANTS = motif('^[«“‘(\\[{\\u2009\\u00A0 ]+', 'u');
			var BAS_DE_CASSE = motif('^\\p{Ll}', 'u');
			function finitBien(t) {
				t = String(t).trim();
				while (t && FERMANTS.indexOf(t.charAt(t.length - 1)) !== -1) { t = t.slice(0, -1); }
				return !!t && PONCTUATION_FINALE.indexOf(t.charAt(t.length - 1)) !== -1;
			}
			function reprendEnBasDeCasse(t) {
				if (!OUVRANTS || !BAS_DE_CASSE) { return false; }
				return BAS_DE_CASSE.test(String(t).replace(OUVRANTS, ''));
			}
			// Un paragraphe qui ne finit par aucune ponctuation : presque toujours
			// une phrase tronquée au collage. Et le paragraphe coupé en deux, dont
			// la suite reprend en bas de casse — un retour de trop, venu d'un PDF.
			function ponctuationFinale(paras) {
				var avis = [];
				paras.forEach(function (p, i) {
					if (finitBien(p)) { return; }
					if (i + 1 < paras.length && reprendEnBasDeCasse(paras[i + 1])) {
						avis.push('Le paragraphe ' + (i + 1) + ' s’arrête au milieu d’une phrase que le suivant reprend ('
							+ guillemets('…' + extrait(p, -30)) + ' puis ' + guillemets(extrait(paras[i + 1], 30) + '…')
							+ ')' + NBSP + ': un retour à la ligne de trop, sans doute.');
					} else {
						avis.push('Le paragraphe ' + (i + 1) + ' ne se termine par aucune ponctuation ('
							+ guillemets('…' + extrait(p, -40)) + ')' + NBSP + ': texte tronqué' + NBSP + '?');
					}
				});
				return avis;
			}
			// « fig. 3 » quand deux figures seulement sont jointes, ou une figure
			// que le texte n'appelle jamais. « fig. 3-5 » vaut 3, 4 et 5.
			var APPEL = motif('(^|[^\\p{L}\\d])(?:fig(?:ure)?s?\\.?|ill(?:ustration)?s?\\.?)\\s*(\\d+[a-z]?(?:\\s*(?:[-–—]|à|to|et|and|,|;|\\/)\\s*\\d+[a-z]?){0,12})', 'giu');
			function developper(corps) {
				corps = String(corps).trim().replace(/\s+/g, ' ');
				var m = /^(\d+)\s*(?:[-–—]|à|to)\s*(\d+)$/i.exec(corps);
				var out = [];
				if (m && +m[1] <= +m[2] && +m[2] - +m[1] <= 100) {
					for (var n = +m[1]; n <= +m[2]; n++) { out.push(n); }
					return out;
				}
				(corps.match(/\d+/g) || []).forEach(function (x) { if (out.indexOf(+x) === -1) { out.push(+x); } });
				return out;
			}
			function enLettres(n) {
				return { 1: 'une figure', 2: 'deux figures', 3: 'trois figures' }[n] || (n + ' figures');
			}
			function appelsDeFigure(texte, combien) {
				if (!APPEL) { return []; }
				var appeles = {}, m;
				APPEL.lastIndex = 0;
				while ((m = APPEL.exec(texte)) !== null) {
					developper(m[2]).forEach(function (n) { appeles[n] = true; });
				}
				var numeros = Object.keys(appeles).map(Number).sort(function (a, b) { return a - b; });
				var avis = [];
				numeros.forEach(function (n) {
					if (n <= combien) { return; }
					avis.push(0 === combien
						? 'Le texte appelle la fig.' + NBSP + n + ', mais aucune figure n’est jointe.'
						: 'Le texte appelle la fig.' + NBSP + n + ', mais ' + enLettres(combien) + ' seulement '
							+ (1 === combien ? 'est jointe.' : 'sont jointes.'));
				});
				if (numeros.length) {
					for (var rang = 1; rang <= combien; rang++) {
						if (!appeles[rang]) { avis.push('La fig.' + NBSP + rang + ' n’est appelée nulle part dans le texte.'); }
					}
				}
				return avis;
			}
			// « 2ème », « XIIème », « 1ère », « 2nde » : on le signale sans
			// corriger, ce peut être une citation. Ni « Mère » ni « Cère ».
			var ORDINAL = motif('(^|[^\\p{L}\\d])(?:(\\d+|[IVXLC]{1,6})(èmes?|emes?|ièmes?|iemes?|è)|(1|I)(ères?|eres?)|(\\d+)(nde|nd))(?![\\p{L}\\d])', 'gu');
			function ordinauxFautifs(paras) {
				var avis = [];
				if (!ORDINAL) { return avis; }
				paras.forEach(function (p) {
					var m;
					ORDINAL.lastIndex = 0;
					while ((m = ORDINAL.exec(p)) !== null) {
						var nombre = m[2] || m[4] || m[6];
						var suite = m[3] || m[5] || m[7];
						var pluriel = /s$/.test(suite) ? 's' : '';
						suite = suite === 'nd' ? suite : suite.replace(/s+$/, '');
						var juste;
						if (suite === 'ère' || suite === 'ere') { juste = nombre + 're' + pluriel; }
						else if (suite === 'nde' || suite === 'nd') { juste = nombre + 'de'; }
						else if (nombre === '1' || nombre === 'I') { juste = nombre + 'er' + pluriel; }
						else { juste = nombre + 'e' + pluriel; }
						avis.push(guillemets(m[0].slice(m[1].length)) + ' s’abrège ' + guillemets(juste) + '.');
					}
				});
				return avis;
			}
			// « vers 1 250 » : une année tapée avec une espace.
			var DATE_ESPACEE = motif('(?:(^|[^\\p{L}\\d])(?:en|vers|an|années?|dès|depuis|jusqu’en|jusqu\'en|avant|après)\\s+([12] \\d{3})(?![\\p{L}\\d])|([12] \\d{3})\\s*(?:av\\.|apr?\\.|ap\\.)\\s*J\\.-C\\.)', 'giu');
			function datesEspacees(paras) {
				var avis = [];
				if (!DATE_ESPACEE) { return avis; }
				paras.forEach(function (p) {
					var m;
					DATE_ESPACEE.lastIndex = 0;
					while ((m = DATE_ESPACEE.exec(p)) !== null) {
						var annee = (m[2] || m[3]).trim();
						avis.push(guillemets(annee) + NBSP + ': une année s’écrit sans espace, ' + guillemets(annee.replace(/ /g, '')) + '.');
					}
				});
				return avis;
			}
			var texteVu = false;
			var zoneTexte = zoneDeConseils('na-texte-conseils', wc);
			function majConseilsDuTexte() {
				if (!texteVu || !quill) { return; }
				var paras = quill.getText().split('\n').map(function (p) { return p.trim(); }).filter(Boolean);
				var longueur = quill.root.innerHTML.length;
				montrerConseils(zoneTexte, [].concat(
					longueur > 30000 ? ['Le texte dépasse 30' + NBSP + '000 caractères, mise en forme comprise (' + entier(longueur) + ')'
						+ NBSP + ': le serveur en couperait la fin.'] : [],
					avisDuCollage(paras),
					avisActif('ponctuation') ? ponctuationFinale(paras) : [],
					avisActif('figures_appelees') ? appelsDeFigure(paras.join('\n'), selected.length) : [],
					avisActif('ordinaux') ? ordinauxFautifs(paras) : [],
					avisActif('annees_espacees') ? datesEspacees(paras) : [],
					avisActif('sigles') ? siglesNonDeveloppes(paras) : []
				), [quill.root]);
			}
			// Les sigles de la liste des normes employés sans être développés à
			// leur première mention, comme au serveur
			// (Notice_Archeomed_Controles::sigles_non_developpes) : même liste,
			// même phrase, mêmes règles.
			function siglesNonDeveloppes(paras) {
				var texte = paras.join('\n'), avis = [], L = A11Y.lettres;
				var sansArticles = function (t) {
					return plierA11y(t).split(' ').filter(function (w) { return w && A11Y.motsVides.indexOf(w) === -1; }).join(' ');
				};
				A11Y.sigles.forEach(function (s) {
					var sensible = points(s.sigle).length < 4;
					var echappe = s.sigle.replace(/[.*+?^${}()|[\]\\\/-]/g, '\\$&');
					var m = new RegExp('(^|[^' + L + '])(' + echappe + ')(?=$|[^' + L + '])', sensible ? '' : 'i').exec(texte);
					if (!m) { return; }
					var position = m.index + m[1].length;
					var debut = texte.slice(0, position).split(/[.!?…]\s+|\n/).pop();
					var fin = texte.slice(position + m[2].length).split(/[.!?…](?:\s|$)|\n/)[0];
					// Entre parenthèses : la phrase l'a développé à sa façon.
					if (debut.lastIndexOf('(') !== -1 && debut.lastIndexOf('(') > debut.lastIndexOf(')')) { return; }
					var phrase = ' ' + sansArticles(debut + ' ' + fin) + ' ';
					var trouve = s.formes.some(function (f) { f = sansArticles(f); return f !== '' && phrase.indexOf(' ' + f + ' ') !== -1; });
					if (!trouve) { avis.push(phraseA11y('sigle', { sigle: s.sigle, developpement: s.developpement })); }
				});
				return avis;
			}
			// Ce que le dernier collage a ôté, et ce qu'un collage laisse
			// d'ordinaire derrière lui : un appel de note dont la note n'existe
			// pas ici, une puce, une marque de commentaire. Rien n'est corrigé.
			function avisDuCollage(paras) {
				var avis = [];
				if (oteAuCollage.length) {
					avis.push((oteAuCollage.length > 1 ? 'Textes barrés' : 'Texte barré') + ' (suivi des modifications) ôté'
						+ (oteAuCollage.length > 1 ? 's' : '') + ' au collage' + NBSP + ': '
						+ oteAuCollage.slice(0, 3).map(function (t) { return guillemets(extrait(t, 60) + (points(t).length > 60 ? '…' : '')); }).join(', ')
						+ (oteAuCollage.length > 3 ? ' et ' + (oteAuCollage.length - 3) + ' autre' + (oteAuCollage.length > 4 ? 's' : '') : '')
						+ '. Seule la version finale est gardée' + NBSP + ': vérifiez-la.');
				}
				if (commentairesOtes) {
					avis.push('Les commentaires de relecture du document collé ont été ôtés du texte.');
				}
				var note = null, marque = null;
				paras.forEach(function (p, i) {
					if (!note) { var n = /\[\d{1,3}\]/.exec(p); if (n) { note = n[0]; } }
					if (!marque) { var c = /\[[A-Z]{1,4}\d{1,3}\]/.exec(p); if (c) { marque = c[0]; } }
					if (/^[·•▪◦§o–-]\s{2,}/.test(p)) {
						avis.push('Le paragraphe ' + (i + 1) + ' commence par une puce collée (' + guillemets(p.charAt(0)) + ')'
							+ NBSP + ': les listes deviennent des paragraphes.');
					}
				});
				// Un chiffre en exposant après un mot — non « m² » — est un appel
				// de note venu du traitement de texte.
				var exposant = /(^|[^m])<sup>(\d{1,3})<\/sup>/.exec(quill.root.innerHTML);
				if (note || exposant) {
					avis.push('Le texte contient un appel de note (' + (note ? guillemets(note) : guillemets(exposant[2]) + ' en exposant') + ')' + NBSP
						+ ': les notes de bas de page ne sont pas possibles' + NBSP + '; intégrez l’information au texte.');
				}
				if (marque) {
					avis.push(guillemets(marque) + NBSP + ': une marque de commentaire de Word semble restée dans le texte.');
				}
				return avis;
			}
			if (quill) {
				quill.root.addEventListener('blur', function () { texteVu = true; majConseilsDuTexte(); });
			}

			// ── Les avis sur les personnes et le lieu-dit ──────────────────
			// Métopes attend une personne par entrée, une institution qui n'est
			// pas une adresse, un nom qui ne crie pas.
			[['resp', 'du responsable'], ['coresp', 'du co-responsable'], ['coauteur', 'du co-auteur']].forEach(function (r) {
				var cle = r[0], qui = r[1];
				var bloc = el('na-' + cle);
				var prenom = el('na-' + cle + '-prenom'), nom = el('na-' + cle + '-nom'), inst = el('na-' + cle + '-inst');
				var mel = el('na-' + cle + '-email');
				if (!bloc || !prenom || !nom || !inst) { return; }
				var retirer = bloc.querySelector('.na-retirer');
				var z = document.createElement('div');
				z.className = 'na-conseils';
				z.id = 'na-' + cle + '-conseils';
				z.hidden = true;
				bloc.insertBefore(z, retirer || null);
				var quitte = false;
				function maj() {
					var p = net(prenom.value), n = net(nom.value), i = net(inst.value), liste = [];
					var complet = net(p + ' ' + n);
					if (complet && avisActif('personnes')) {
						if (/[,;&]|\s(?:et|and)\s/.test(complet)) {
							liste.push('Le nom ' + qui + ' semble contenir plusieurs personnes (' + guillemets(complet) + ')' + NBSP + ': une seule par champ.');
						}
						if (enCapitales(n)) {
							liste.push('Le nom ' + qui + ' est en capitales (' + guillemets(n) + ')' + NBSP + ': à écrire '
								+ guillemets(casseDeTitre(n)) + ' si c’est bien sa graphie.');
						}
						if (i && i.indexOf('@') !== -1) {
							liste.push('L’institution ' + qui + ' contient une adresse électronique (' + guillemets(i) + ').');
						}
						if (!i && cle !== 'resp' && quitte) {
							liste.push('Pas d’institution de rattachement pour le nom ' + qui + ' (' + guillemets(complet) + ').');
						}
						if (p && !n && quitte) {
							liste.push('Le prénom ' + qui + ' est donné sans nom' + NBSP + ': la notice n’imprimerait que le prénom.');
						}
					}
					// Une adresse sans nom recevait la copie et le lien de
					// correction, sans paraître nulle part dans la notice ; son
					// institution se perdait.
					if (!complet && mel && !vide(mel.value) && cle !== 'resp' && quitte) {
						liste.push('Une adresse est donnée ' + qui.replace(/^du /, 'pour le ') + ' sans prénom ni nom' + NBSP
							+ ': cette personne recevra la copie et le lien de correction, mais ne paraîtra pas dans la notice.');
					}
					montrerConseils(z, liste, [prenom, nom, inst]);
				}
				bloc.addEventListener('focusout', function (e) {
					if (!(e.relatedTarget && bloc.contains(e.relatedTarget))) { quitte = true; }
					if (e.target === prenom || e.target === nom || e.target === inst || e.target === mel) { maj(); }
				});
			});
			(function () {
				var z = zoneDeConseils('na-lieu-dit-conseils', lieuDitInput);
				lieuDitInput.addEventListener('blur', function () {
					var v = net(lieuDitInput.value);
					montrerConseils(z, enCapitales(v)
						? ['Le lieu-dit est en capitales (' + guillemets(v) + ')' + NBSP
							+ ': il paraîtra ainsi dans le titre. Écrivez-le en minuscules, avec les capitales d’usage.']
						: [], [lieuDitInput]);
				});
			}());
			// « 2O24 » ou « été 2024 » passaient sans un mot, et paraissaient
			// tels quels. Rien n'est refusé : une forme inattendue peut être
			// voulue.
			(function () {
				var annee = el('na-annee');
				if (!annee) { return; }
				var z = zoneDeConseils('na-annee-conseils', annee);
				annee.addEventListener('blur', function () {
					var v = net(annee.value), avis = [];
					var m = /^(\d{4})(?:\s*(?:-|‐|‑|–|—|\/|à|au|et)\s*(\d{2,4}))?$/.exec(v);
					if (v && !m) {
						avis.push('L’année ' + guillemets(v) + ' n’a pas la forme attendue (2024, ou 2004-2005)' + NBSP + ': elle paraîtra telle quelle.');
					} else if (m && (+m[1] < 1900 || +m[1] > new Date().getFullYear() + 1)) {
						avis.push('L’année ' + guillemets(m[1]) + ' semble hors des années possibles.');
					}
					montrerConseils(z, avis, [annee]);
				});
			}());
			// ── Les longueurs que le serveur coupe ─────────────────────────
			// Au-delà, le serveur coupe la fin. On le dit avant l'envoi, sans
			// rien retenir : un « maxlength » couperait lui aussi, en silence,
			// au collage. Les bornes sont celles du serveur (enregistrement de
			// la saisie, figures, lieux, organismes).
			var LONGUEURS = [
				['#na-departement', 120, 'La précision géographique'], ['#na-lieu-dit', 200, 'Le lieu-dit'], ['#na-annee', 20, 'L’année'],
				['#na-autorisation', 120, 'Le numéro d’autorisation'], ['#na-patriarche', 120, 'L’identifiant Patriarche'],
				['.na-commune', 120, 'Le lieu'], ['.na-organisme', 200, 'L’organisme'], ['#na-commentaires', 3000, 'Le message à la rédaction'],
				['#na-rapport', 500, 'L’adresse du rapport'], ['#na-originaux', 500, 'Le lien des originaux']
			];
			['resp', 'coresp', 'coauteur'].forEach(function (c) {
				LONGUEURS.push(['#na-' + c + '-prenom', 100, 'Le prénom'], ['#na-' + c + '-nom', 100, 'Le nom'], ['#na-' + c + '-inst', 200, 'L’institution']);
			});
			function avisDeLongueur(champ, max, nom) {
				var n = points(champ.value).length;
				var z = zoneDeConseils(champ.id + '-longueur', champ.closest('.na-combo') || champ);
				montrerConseils(z, n > max ? [nom + ' dépasse ' + entier(max) + NBSP + 'caractères (' + entier(n) + ')' + NBSP
					+ ': le serveur en couperait la fin.'] : [], [champ]);
			}
			LONGUEURS.forEach(function (l) {
				[].forEach.call(form.querySelectorAll(l[0]), function (champ) {
					if (champ.id) { champ.addEventListener('blur', function () { avisDeLongueur(champ, l[1], l[2]); }); }
				});
			});

			// ── La pièce de vérification ───────────────────────────────────
			// Glissée dans son encoche, elle dépose la valeur scellée que le
			// serveur attend.
			var puzzleFait = function () { return true; };
			var puzzlePoignee = null;
			(function () {
				var bloc = document.getElementById('na-puzzle');
				if (!bloc) { return; }
				var toile   = document.getElementById('na-puzzle-fond');
				var piece   = document.getElementById('na-puzzle-piece');
				var poignee = puzzlePoignee = document.getElementById('na-puzzle-poignee');
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

				// Le tracé d'une pièce : un carré, une languette à droite, une
				// encoche en haut. Dessiné à l'origine.
				function tracer(ctx, c) {
					var t = c * 0.22;
					var m = c / 2;
					ctx.beginPath();
					ctx.moveTo(0, 0);
					ctx.lineTo(m - t, 0);
					ctx.arc(m, 0, t, Math.PI, 0, true);
					ctx.lineTo(c, 0);
					ctx.lineTo(c, m - t);
					ctx.arc(c, m, t, -Math.PI / 2, Math.PI / 2, false);
					ctx.lineTo(c, c);
					ctx.lineTo(0, c);
					ctx.closePath();
				}
				// Un fond dessiné, différent à chaque affichage : rien à servir, et
				// la comparaison de pixels ne sert à rien.
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
				var cx = toile.getContext('2d');
				var xCible = MARGE + Math.round(utile * CIBLE / 100);
				cx.drawImage(scene, 0, 0);
				cx.save();
				cx.translate(xCible, posY);
				tracer(cx, COTE);
				cx.fillStyle = 'rgba(0,0,0,0.55)';
				cx.fill();
				cx.lineWidth = 1.5;
				cx.strokeStyle = 'rgba(255,255,255,0.65)';
				cx.stroke();
				cx.restore();
				// La pièce : les pixels du fond qui manquent au trou. Son canevas
				// est plus large que le côté, pour la languette.
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

				// La position dite en mots : sans la vue, le curseur n'annonçait
				// que 0 à 100, sans cible. L'encoche est déjà dans la page
				// (data-cible) : la dire n'ôte rien à la protection.
				function placer(p) {
					pos = Math.max(0, Math.min(100, p));
					piece.style.left = (100 * (MARGE + utile * pos / 100) / L) + '%';
					poignee.style.left = pos + '%';
					poignee.style.transform = 'translate(' + (-pos) + '%, -50%)';
					poignee.setAttribute('aria-valuenow', Math.round(pos));
					poignee.setAttribute('aria-valuetext', gagne ? 'Pièce en place.'
						: 'Position ' + Math.round(pos) + FINE + '; l’encoche est à ' + CIBLE + '.');
				}
				function verifier() {
					if (Math.abs(pos - CIBLE) <= TOLERANCE) {
						gagne = true;
						champ.value = String(CIBLE);
						bloc.classList.add('na-fait');
						etat.textContent = '✓ Vérification réussie';
						placer(CIBLE);
						poignee.setAttribute('aria-disabled', 'true');
						reverifier('puzzle');
						return true;
					}
					etat.textContent = 'Pas tout à fait' + NBSP + ': la pièce doit combler l’encoche.';
					bloc.classList.add('na-rate');
					window.setTimeout(function () { bloc.classList.remove('na-rate'); }, 600);
					placer(0);
					return false;
				}
				var actif = false, depart = 0, posDepart = 0;
				var piste = poignee.parentElement;
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
					placer(posDepart + (e.clientX - depart) / courseEcran() * 100);
					e.preventDefault();
				});
				function relacher(e, valide) {
					if (!actif) { return; }
					actif = false;
					bloc.classList.remove('na-prise');
					try { poignee.releasePointerCapture(e.pointerId); } catch (err) {}
					// Un geste interrompu par le défilement n'est pas un échec.
					if (valide) { verifier(); } else { placer(0); }
				}
				poignee.addEventListener('pointerup', function (e) { relacher(e, true); });
				poignee.addEventListener('pointercancel', function (e) { relacher(e, false); });
				// Un clic sur la piste y porte la pièce : une alternative au
				// glisser, pour qui ne peut pas maintenir un geste.
				piste.addEventListener('click', function (e) {
					if (gagne || e.target === poignee) { return; }
					var r = piste.getBoundingClientRect();
					var l = poignee.getBoundingClientRect().width;
					placer((e.clientX - r.left - l / 2) / Math.max(1, r.width - l) * 100);
					verifier();
				});
				poignee.addEventListener('keydown', function (e) {
					if (gagne) { return; }
					var pas = e.shiftKey ? 10 : 2;
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
				puzzleFait = function () { return gagne; };
			}());

			// ── Les erreurs, dans la page ──────────────────────────────────
			// Quatre canaux se partageaient les erreurs — bulles du navigateur,
			// alertes, message du serveur, rien sur le champ. Il n'en reste
			// qu'un : un récapitulatif en tête, et un message sous chaque libellé.
			var recap = document.getElementById('na-recap');
			var messageServeur = document.getElementById('na-message');
			var titreDeLaPage = document.title;
			var erreurs = {};
			function courrielValide(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
			function normaliserUneAdresse(r) {
				if (!r) { return; }
				var v = r.value.trim();
				// Une adresse copiée sans « https:// » était refusée par le
				// navigateur ; on l'ajoute, comme le serveur le ferait.
				if (v && !/^[a-z][a-z0-9+.\-]*:\/\//i.test(v)) { v = 'https://' + v.replace(/^\/+/, ''); }
				r.value = v;
			}
			function normaliserRapport() {
				normaliserUneAdresse(document.getElementById('na-rapport'));
				normaliserUneAdresse(document.getElementById('na-originaux'));
			}
			document.getElementById('na-rapport').addEventListener('blur', normaliserRapport);
			if (document.getElementById('na-originaux')) {
				document.getElementById('na-originaux').addEventListener('blur', normaliserRapport);
			}
			function el(id) { return document.getElementById(id); }
			function uneValeur(sel) {
				return [].some.call(form.querySelectorAll(sel), function (c) { return !vide(c.value); });
			}
			// Chaque contrôle rend vrai si le champ est juste. Même ordre que la
			// page, qui est celui du récapitulatif.
			var CONTROLES = {
				commune: function () { return uneValeur('.na-commune'); },
				departement: function () { return !vide(sansParentheses(deptInput.value)); },
				lieu_dit: function () { return !vide(lieuDitInput.value); },
				nature: function () { return !!form.querySelector('input[name="nature[]"]:checked'); },
				annee: function () { return !vide(el('na-annee').value); },
				organisme: function () { return uneValeur('.na-organisme'); },
				rapport_lien: function () {
					var v = el('na-rapport').value.trim();
					return !v || /^https?:\/\/[^\s\/]+\.[^\s]+$/i.test(v);
				},
				resp_prenom: function () { return !vide(el('na-resp-prenom').value); },
				resp_nom: function () { return !vide(el('na-resp-nom').value); },
				resp_email: function () {
					var v = el('na-resp-email').value.trim();
					return v && courrielValide(v) ? true : (v ? 'forme' : false);
				},
				resp_inst: function () { return !vide(el('na-resp-inst').value); },
				coresp_email: function () { var v = el('na-coresp-email').value.trim(); return !v || courrielValide(v); },
				coauteur_email: function () { var v = el('na-coauteur-email').value.trim(); return !v || courrielValide(v); },
				texte: function () { return !quill || !vide(quill.getText()); },
				rubrique_principale: function () { return !vide(el('na-rubrique').value); },
				// La matière, demandée par la seule rubrique V : masquée, elle ne
				// compte pas.
				rubrique_matiere: function () {
					var b = el('na-matiere-bloc');
					return !b || b.hidden || !vide(el('na-matiere').value);
				},
				// Le texte alternatif de chaque figure choisie. Le contrôle ne
				// joue que si la carte est là : sans fichier, rien à décrire.
				illus_alt_1: function () { return texteAlternatifDonne(1); },
				illus_alt_2: function () { return texteAlternatifDonne(2); },
				illus_alt_3: function () { return texteAlternatifDonne(3); },
				originaux_lien: function () {
					var c = el('na-originaux');
					var v = c ? c.value.trim() : '';
					return !v || /^https?:\/\/[^\s\/]+\.[^\s]+$/i.test(v);
				},
				puzzle: function () { return puzzleFait(); }
			};
			function texteAlternatifDonne(rang) {
				var c = el('na-illus-' + rang + '-alt');
				return !c || rang > selected.length || !vide(c.value);
			}
			function cibleDe(cle) {
				if (cle === 'nature') { return el('na-nature'); }
				if (cle === 'texte') { return quill ? quill.root : el('na-texte'); }
				return el(CHAMPS[cle][0]);
			}
			function focaliser(cle) {
				var c = cle === 'nature' ? el('na-nature-1') : cibleDe(cle);
				// Revenu d'un refus, le formulaire n'a plus les fichiers : le
				// lien du texte alternatif mène à la zone où les redéposer.
				if (!c && /^illus_alt_/.test(cle)) { c = fileInput; }
				if (!c) { return; }
				if (c.scrollIntoView) { c.scrollIntoView({ block: 'center' }); }
				try { c.focus({ preventScroll: true }); } catch (e) { c.focus(); }
			}
			function messageDe(cle, resultat) {
				if (cle === 'resp_email' && resultat === 'forme') { return CHAMPS.email_forme[1]; }
				// La figure se nomme aussi par son fichier : le numéro suit
				// l'ordre, qu'on a pu changer.
				var rang = /^illus_alt_(\d)$/.exec(cle);
				if (rang && selected[rang[1] - 1]) { return CHAMPS[cle][1] + ' (' + selected[rang[1] - 1].name + ')'; }
				return CHAMPS[cle][1];
			}
			// Après un retrait, un déplacement ou un ajout, les cartes sont
			// neuves : l'erreur d'un texte alternatif se repose sur le bon
			// champ, ou s'efface si elle n'a plus lieu d'être.
			// Une figure sans texte alternatif qu'on déplace emporte son erreur
			// à sa nouvelle place.
			function revoirLesTextesAlternatifs() {
				if (!erreurs || !Object.keys(erreurs).some(function (k) { return /^illus_alt_/.test(k); })) { return; }
				for (var rang = 1; rang <= MAX_FILES; rang++) {
					var cle = 'illus_alt_' + rang;
					var r = CONTROLES[cle]();
					if (r === true) { effacer(cle); } else { signaler(cle, messageDe(cle, r)); }
				}
			}
			function signaler(cle, message) {
				var cible = cibleDe(cle);
				if (!cible) { return; }
				var id = 'na-erreur-' + cle.replace(/_/g, '-');
				var p = el(id);
				if (!p) {
					p = document.createElement('p');
					p.id = id;
					p.className = 'na-erreur-champ';
					// Entre le libellé et la saisie : au-dessus du champ, ou du bloc
					// qui le porte (liste de cases, éditeur, pièce de puzzle).
					var conteneur = cible.closest('.na-champ');
					var ancre = (conteneur && conteneur.querySelector('.na-ancre')) || cible;
					ancre.parentNode.insertBefore(p, ancre);
				}
				p.textContent = message;
				cible.setAttribute('aria-invalid', 'true');
				ajouterDescription(cible, id);
				var champ = cible.closest('.na-champ');
				if (champ) { champ.classList.add('na-champ--erreur'); }
				if (cle === 'texte') { el('na-texte').classList.add('na-texte--erreur'); }
				erreurs[cle] = { cible: cible, p: p };
			}
			function effacer(cle) {
				var e = erreurs[cle];
				if (!e) { return; }
				e.p.remove();
				e.cible.removeAttribute('aria-invalid');
				retirerDescription(e.cible, e.p.id);
				var champ = e.cible.closest('.na-champ');
				if (champ) { champ.classList.remove('na-champ--erreur'); }
				if (cle === 'texte') { el('na-texte').classList.remove('na-texte--erreur'); }
				delete erreurs[cle];
				retirerDuRecap(cle);
				if (!Object.keys(erreurs).length) { document.title = titreDeLaPage; }
			}
			// Le récapitulatif suit les corrections. Il affichait « Il reste 13
			// points à corriger » quand on les avait tous corrigés, alors que les
			// messages sous les champs, eux, s'effaçaient : on ne savait plus
			// lequel croire.
			function retirerDuRecap(cle) {
				var liens = [];
				if (recap) { liens = liens.concat([].slice.call(recap.querySelectorAll('a[data-cle="' + cle + '"]'))); }
				if (messageServeur && CHAMPS[cle]) {
					liens = liens.concat([].slice.call(messageServeur.querySelectorAll('a[href="#' + CHAMPS[cle][0] + '"]')));
				}
				if (!liens.length) { return; }
				liens.forEach(function (a) { var li = a.closest('li'); if (li) { li.remove(); } });
				[recap, messageServeur].forEach(function (boite) {
					if (!boite || boite.hidden) { return; }
					var ul = boite.querySelector('ul');
					if (!ul) { return; }
					// Réannoncer tout le cadre à chaque correction serait du bruit :
					// il a été lu en entier quand il s'est ouvert.
					boite.removeAttribute('role');
					var n = ul.querySelectorAll('li').length;
					var h = boite.querySelector('h2');
					if (n) {
						if (h) { h.textContent = n > 1 ? 'Il reste ' + n + ' points à corriger' : 'Il reste un point à corriger'; }
						return;
					}
					if (h) { h.remove(); }
					ul.remove();
					if (vide(boite.textContent)) { boite.hidden = true; }
				});
			}
			// Le message s'efface dès que le champ est juste.
			function reverifier(cle) {
				if (erreurs[cle] && CONTROLES[cle] && CONTROLES[cle]() === true) { effacer(cle); }
			}
			function effacerLErreurDe(c) {
				Object.keys(erreurs).forEach(function (cle) {
					var champ = erreurs[cle].cible.closest('.na-champ') || erreurs[cle].cible;
					if (champ.contains(c)) { reverifier(cle); }
				});
			}
			function rafraichir(e) { effacerLErreurDe(e.target); }
			form.addEventListener('input', rafraichir);
			form.addEventListener('change', rafraichir);
			function valider() {
				var liste = [];
				Object.keys(CONTROLES).forEach(function (cle) {
					if (!cibleDe(cle)) { return; }
					effacer(cle);
					var r = CONTROLES[cle]();
					if (r === true) { return; }
					var m = messageDe(cle, r);
					signaler(cle, m);
					liste.push({ cle: cle, message: m });
				});
				return liste;
			}
			function lienVers(cle, message) {
				var li = document.createElement('li');
				var a = document.createElement('a');
				a.href = '#' + CHAMPS[cle][0];
				a.setAttribute('data-cle', cle);
				a.textContent = message;
				a.addEventListener('click', function (ev) { ev.preventDefault(); focaliser(cle); });
				li.appendChild(a);
				return li;
			}
			function montrerRecap(liste) {
				recap.innerHTML = '';
				var h = document.createElement('h2');
				h.textContent = liste.length > 1 ? 'Il reste ' + liste.length + ' points à corriger' : 'Il reste un point à corriger';
				var ul = document.createElement('ul');
				liste.forEach(function (er) { ul.appendChild(lienVers(er.cle, er.message)); });
				recap.appendChild(h);
				recap.appendChild(ul);
				recap.setAttribute('role', 'alert');
				recap.hidden = false;
				if (messageServeur) { messageServeur.hidden = true; }
				document.title = 'Erreur' + NBSP + ': ' + titreDeLaPage;
				recap.scrollIntoView({ block: 'start' });
				try { recap.focus({ preventScroll: true }); } catch (e) { recap.focus(); }
			}
			// La matière paraît quand la rubrique V est choisie, et seulement
			// alors ; masquée, son erreur s'efface.
			function suivreLaMatiere() {
				var b = el('na-matiere-bloc'), r = el('na-rubrique');
				if (!b || !r) { return; }
				b.hidden = r.value !== b.getAttribute('data-rubrique');
				if (b.hidden) { effacer('rubrique_matiere'); }
			}
			el('na-rubrique').addEventListener('change', suivreLaMatiere);
			suivreLaMatiere();
			// Au retour d'un refus, les champs que nomme le serveur se marquent
			// comme ceux que le script aurait trouvés.
			if (messageServeur) {
				var signales = (messageServeur.getAttribute('data-champs') || '').split(',').filter(function (c) { return CHAMPS[c]; });
				signales.forEach(function (cle) { signaler(cle, CHAMPS[cle][1]); });
				if (signales.length) { document.title = 'Erreur' + NBSP + ': ' + titreDeLaPage; }
				[].forEach.call(messageServeur.querySelectorAll('a[href^="#na-"]'), function (a) {
					var id = a.getAttribute('href').slice(1);
					Object.keys(CHAMPS).forEach(function (cle) {
						if (CHAMPS[cle][0] === id) {
							a.addEventListener('click', function (ev) { ev.preventDefault(); focaliser(cle); });
						}
					});
				});
				messageServeur.focus();
			}

			// ── Entrée ne soumet pas depuis un champ d'une ligne ───────────
			// Une recherche Pactols ou une légende validée par Entrée envoyait
			// la notice à moitié remplie. Les listes gardent leur propre Entrée.
			form.addEventListener('keydown', function (e) {
				if (e.key !== 'Enter' || e.defaultPrevented) { return; }
				var t = e.target;
				if (t.tagName === 'INPUT' && /^(text|email|url|search|tel|number)$/.test(t.type)) { e.preventDefault(); }
			});

			// ── Le brouillon gardé sur l'appareil ──────────────────────────
			// Une requête trop lourde, une page fermée, une coupure : la saisie
			// se perdait. Elle est gardée ici, dans le navigateur, et effacée
			// quand la notice est déposée. La reprise du serveur passe toujours
			// devant : le brouillon ne se propose que sur un formulaire vierge.
			var CLE_BROUILLON = 'na_brouillon_' + window.location.pathname;
			var brouillonActif = form.getAttribute('data-brouillon') === '1';
			var modifie = false;
			var avisBrouillon = null;
			function lire() { try { return window.localStorage.getItem(CLE_BROUILLON); } catch (e) { return null; } }
			function ecrireBrouillon(v) { try { window.localStorage.setItem(CLE_BROUILLON, v); return true; } catch (e) { return false; } }
			function oublierBrouillon() { try { window.localStorage.removeItem(CLE_BROUILLON); } catch (e) {} }
			function marquerModifie() { modifie = true; }
			form.addEventListener('input', marquerModifie);
			form.addEventListener('change', marquerModifie);
			if (quill) { quill.on('text-change', function (d, o, source) { if (source === 'user') { marquerModifie(); } }); }
			var EXCLUS = ['notice_archeomed_nonce', '_wp_http_referer', 'na_website', 'na_ts', 'reprise_jeton',
				'na_curseur', 'na_preuve', 'texte_notice', 'cf-turnstile-response', 'notice_archeomed_envoi'];
			function photographier() {
				var champs = {};
				[].forEach.call(form.elements, function (c) {
					if (!c.name || EXCLUS.indexOf(c.name) !== -1 || c.name.indexOf('illus_') === 0
						|| c.type === 'file' || c.type === 'submit' || c.type === 'button') { return; }
					if (c.type === 'checkbox') {
						if (!champs[c.name]) { champs[c.name] = []; }
						if (c.checked) { champs[c.name].push(c.value); }
						return;
					}
					if (/\[\]$/.test(c.name)) {
						if (!champs[c.name]) { champs[c.name] = []; }
						champs[c.name].push(c.value);
						return;
					}
					champs[c.name] = c.value;
				});
				// Les textes des figures avec le nom de leur fichier, et ceux qui
				// attendent encore le leur.
				return { v: 2, quand: Date.now(), champs: champs, texte: quill ? quill.root.innerHTML : '',
					illus: illusMeta.concat(orphelins) };
			}
			// Des légendes seules ne déclenchaient pas l'enregistrement : on
			// perdait trois légendes tapées avant tout le reste.
			function contenuUtile(b) {
				if (b.texte && !vide(b.texte.replace(/<[^>]*>/g, ''))) { return true; }
				if (Array.isArray(b.illus) && b.illus.some(aDuTexte)) { return true; }
				return Object.keys(b.champs || {}).some(function (nom) {
					var v = b.champs[nom];
					if (/^pactols_/.test(nom)) { return v && v !== '[]'; }
					return Array.isArray(v) ? v.some(function (x) { return !vide(x); }) : !vide(v);
				});
			}
			function enregistrer(force) {
				if (!brouillonActif || (!modifie && !force)) { return; }
				modifie = false;
				var photo = photographier();
				if (!contenuUtile(photo)) { return; }
				if (ecrireBrouillon(JSON.stringify(photo)) && avisBrouillon) {
					// L'ancien brouillon vient d'être remplacé : l'offre ne tient plus.
					avisBrouillon.remove();
					avisBrouillon = null;
				}
			}
			function restaurer(b) {
				var ch = b.champs || {};
				var rangs = {};
				[].forEach.call(form.elements, function (c) {
					if (!c.name || !Object.prototype.hasOwnProperty.call(ch, c.name) || c.type === 'file') { return; }
					var v = ch[c.name];
					if (c.type === 'checkbox') { c.checked = Array.isArray(v) && v.indexOf(c.value) !== -1; return; }
					if (/\[\]$/.test(c.name)) {
						var i = rangs[c.name] || 0;
						rangs[c.name] = i + 1;
						c.value = (Array.isArray(v) && typeof v[i] === 'string') ? v[i] : '';
						return;
					}
					if (typeof v === 'string') { c.value = v; }
				});
				if (quill && b.texte) { quill.clipboard.dangerouslyPasteHTML(b.texte); }
				// Les textes des figures attendent leur fichier : ils s'y
				// rattacheront par son nom, dans quelque ordre qu'on les redépose.
				if (Array.isArray(b.illus)) {
					orphelins = orphelins.concat(b.illus.filter(function (m) { return m && (m.nom || aDuTexte(m)); }));
					rattacher();
					renderFileList();
				}
				categories.forEach(function (c) { c.relire(); });
				suivreLaMatiere();
				jugerLaParenthese();
				ajusterLesReplis();
				majApercu();
				countWords();
			}
			function quandLisible(t) {
				var d = new Date(t);
				var jour = d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' });
				var mn = d.getMinutes();
				return 'le ' + jour + ' à ' + d.getHours() + NBSP + 'h' + NBSP + (mn < 10 ? '0' : '') + mn;
			}
			if (brouillonActif && form.getAttribute('data-reprise') === '0') {
				var brouillon = null;
				try { brouillon = JSON.parse(lire() || 'null'); } catch (e) { brouillon = null; }
				if (brouillon && brouillon.champs && contenuUtile(brouillon)) {
					avisBrouillon = document.createElement('div');
					avisBrouillon.className = 'na-message na-avis';
					avisBrouillon.id = 'na-brouillon';
					var dit = document.createElement('p');
					dit.textContent = 'Une saisie enregistrée sur cet appareil ' + quandLisible(brouillon.quand || Date.now()) + ' a été retrouvée.';
					var choix = document.createElement('p');
					choix.className = 'na-brouillon-choix';
					var reprendre = document.createElement('button');
					reprendre.type = 'button';
					reprendre.className = 'na-bouton-lien';
					reprendre.textContent = 'Reprendre cette saisie';
					var jeter = document.createElement('button');
					jeter.type = 'button';
					jeter.className = 'na-bouton-lien';
					jeter.textContent = 'L’effacer';
					reprendre.addEventListener('click', function () {
						restaurer(brouillon);
						avisBrouillon.remove();
						avisBrouillon = null;
						// « Saisie reprise. » ne disait pas que les fichiers manquaient,
						// et la liste des figures était vide.
						var noms = orphelins.map(function (m) { return m.nom; }).filter(Boolean);
						annoncer(noms.length
							? 'Saisie reprise. ' + (noms.length > 1 ? 'Les illustrations sont à redéposer' : 'L’illustration est à redéposer')
								+ NBSP + ': ' + noms.join(', ') + ' — ' + (noms.length > 1 ? 'leurs titres et légendes reviendront' : 'son titre et sa légende reviendront')
								+ ' d’eux-mêmes.'
							: 'Saisie reprise.');
						var premier = el('na-lieu-1');
						if (premier) { premier.focus(); }
					});
					jeter.addEventListener('click', function () {
						oublierBrouillon();
						avisBrouillon.remove();
						avisBrouillon = null;
						annoncer('Saisie effacée.');
						el('na-lieu-1').focus();
					});
					choix.appendChild(reprendre);
					choix.appendChild(jeter);
					avisBrouillon.appendChild(dit);
					avisBrouillon.appendChild(choix);
					form.insertBefore(avisBrouillon, el('na-intro'));
				}
			}
			window.setInterval(enregistrer, 5000);

			majApercu();

			// ── L'envoi ────────────────────────────────────────────────────
			var bouton = form.querySelector('button.na-submit');
			var libelleBouton = bouton ? bouton.textContent : '';
			// Cette page-ci, parmi celles de l'onglet : c'est elle, et elle
			// seule, que la page de fin marque comme déposée.
			var instance = String(Date.now()) + '-' + Math.random().toString(36).slice(2);
			function adresseVierge() {
				var u = window.location.pathname;
				try {
					var url = new URL(window.location.href);
					['notice_reprise', 'notice_envoyee', 'notice_erreur', 'notice_champ', 'notice_champs', 'notice_ref'].forEach(function (k) {
						url.searchParams.delete(k);
					});
					url.hash = '';
					u = url.toString();
				} catch (x) {}
				return u;
			}
			// La notice vient d'être déposée et l'on est revenu en arrière :
			// le formulaire, rechargé vierge, le dit.
			(function () {
				var ref = null;
				try { ref = window.sessionStorage.getItem('na_deja'); window.sessionStorage.removeItem('na_deja'); } catch (x) { ref = null; }
				if (!ref) { return; }
				var m = document.createElement('div');
				m.className = 'na-message na-avis';
				m.id = 'na-deja-deposee';
				m.setAttribute('role', 'status');
				m.tabIndex = -1;
				var p = document.createElement('p');
				p.textContent = 'Votre notice est bien déposée' + (/^[A-Z0-9]{6}$/.test(ref) ? ' sous la référence ' + ref : '')
					+ '. Le formulaire a été vidé pour qu’elle ne parte pas une seconde fois' + NBSP + ': la rédaction la recevrait en double. '
					+ 'Pour la corriger, suivez le lien de la copie reçue par courriel' + NBSP + '; pour déposer une autre notice, remplissez ce formulaire.';
				m.appendChild(p);
				form.insertBefore(m, el('na-intro'));
				m.focus();
			}());
			form.addEventListener('submit', function (e) {
				if (form.getAttribute('data-envoi') === '1') { e.preventDefault(); return; }
				if (!quill) {
					e.preventDefault();
					showJsError('Erreur technique' + NBSP + ': l’éditeur de texte ne s’est pas chargé. Rechargez la page.');
					return;
				}
				normaliserRapport();
				var plafond = [];
				categories.forEach(function (c) { if (!c.garderLaSaisie()) { plafond.push(c); } });
				var liste = valider();
				plafond.forEach(function (c) {
					var champ = c.champ;
					var m = guillemets(net(champ.value)) + ' n’a pas été ajouté' + NBSP + ': dix termes au plus par catégorie' + FINE + '; retirez-en un, ou videz le champ';
					var id = 'na-erreur-kw-' + c.type;
					var p = el(id);
					if (!p) {
						p = document.createElement('p');
						p.id = id;
						p.className = 'na-erreur-champ';
						champ.closest('.na-ancre').parentNode.insertBefore(p, champ.closest('.na-ancre'));
					}
					p.textContent = m;
					champ.setAttribute('aria-invalid', 'true');
					ajouterDescription(champ, id);
					CHAMPS['kw_' + c.type] = [champ.id, m];
					liste.push({ cle: 'kw_' + c.type, message: m });
					champ.addEventListener('input', function efface() {
						p.remove();
						champ.removeAttribute('aria-invalid');
						retirerDescription(champ, id);
						champ.removeEventListener('input', efface);
						retirerDuRecap('kw_' + c.type);
					});
				});
				if (liste.length) {
					e.preventDefault();
					montrerRecap(liste);
					return;
				}
				recap.hidden = true;
				categories.forEach(function (c) { c.ecrire(); });
				var html = quill.root.innerHTML;
				document.getElementById('na-texte-notice').value = 'b64:' + btoa(unescape(encodeURIComponent(html)));
				enregistrer(true);
				// Tous les contrôles sont passés : la page part pour de bon. On
				// bloque le bouton — un second clic déposerait la notice deux fois.
				form.setAttribute('data-envoi', '1');
				// La page de fin saura que c'est cette page-ci qui est partie.
				try { window.sessionStorage.setItem('na_envoi', instance); } catch (x) {}
				document.getElementById('na-envoi').hidden = false;
				annoncer('Envoi en cours. Ne fermez pas cette page.');
				if (bouton) {
					bouton.disabled = true;
					bouton.textContent = 'Envoi en cours…';
				}
				// Le bouton désactivé n'est plus envoyé avec le formulaire : son
				// nom porte la valeur que le serveur attend, on la poste autrement.
				var relais = document.createElement('input');
				relais.type = 'hidden';
				relais.name = 'notice_archeomed_envoi';
				relais.value = '1';
				form.appendChild(relais);
			});
			// Revenu par le bouton Précédent, le navigateur rend la page telle
			// qu'on l'a quittée : bouton bloqué, envoi « en cours ». On la rend
			// utilisable.
			//
			// Sauf si cette page même a été déposée : la saisie, prête à
			// repartir, faisait un doublon, et après une correction une notice
			// neuve sans marque de correction. On repart d'un formulaire vierge,
			// en disant que la notice est déposée. Seule la page dont l'envoi a
			// abouti est vidée : une saisie non envoyée n'est jamais touchée.
			window.addEventListener('pageshow', function (e) {
				var depose = null;
				try { depose = JSON.parse(window.sessionStorage.getItem('na_depose') || 'null'); } catch (x) { depose = null; }
				if (e.persisted && depose && depose.page === instance) {
					try {
						window.sessionStorage.removeItem('na_depose');
						window.sessionStorage.setItem('na_deja', depose.ref || '-');
					} catch (x) {}
					brouillonActif = false;
					window.location.replace(adresseVierge());
					return;
				}
				if (!e.persisted || !bouton) { return; }
				form.removeAttribute('data-envoi');
				bouton.disabled = false;
				bouton.textContent = libelleBouton;
				document.getElementById('na-envoi').hidden = true;
				var relais = form.querySelector('input[type=hidden][name="notice_archeomed_envoi"]');
				if (relais) { relais.remove(); }
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
	private function texte_notice_pose( $brut, $nom = '' ) {
		$brut = (string) $brut;
		if ( 0 === strpos( $brut, 'b64:' ) ) {
			$decode = base64_decode( substr( $brut, 4 ), true );
			$brut   = ( false !== $decode ) ? $decode : '';
		}
		// La coupe peut tomber dans une balise : « <str » sortirait en clair.
		return preg_replace( '/<[^>]*$/', '', $this->limit_string( $this->clean_richtext( $brut ), 30000, $nom ) );
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
			'span' => array( 'class' => true ),
		);
		// Un élément de liste, un intertitre ou une citation collés font
		// chacun un paragraphe. Sans cela, kses ôtait la balise en gardant le
		// texte, et « Phase 1 : fossé » se soudait à « Phase 2 : mur ».
		$html = preg_replace( '#</(li|h[1-6]|blockquote|div|pre|tr)\s*>#i', '</p><p>', (string) $html );
		return self::petites_capitales_seules( wp_kses( $html, $allowed ) );
	}

	/**
	 * Ne garde, des « span », que les petites capitales que l'auteur a posées
	 * avec le bouton de l'éditeur : « <span class="na-pc"> », sans rien
	 * d'autre. Les autres — ceux d'un collage depuis Word, avec leurs styles —
	 * s'ôtent en gardant leur texte, comme avant ; leur fermeture avec eux.
	 */
	private static function petites_capitales_seules( $html ) {
		$pile = array();
		$html = preg_replace_callback( '#<(/?)span\b([^>]*)>#i', function ( $m ) use ( &$pile ) {
			if ( '/' === $m[1] ) {
				return array_pop( $pile ) ? '</span>' : '';
			}
			$garde  = (bool) preg_match( '#\bclass\s*=\s*(["\'])(?:[^"\']*\s)?na-pc(?:\s[^"\']*)?\1#i', $m[2] );
			$pile[] = $garde;
			return $garde ? '<span class="na-pc">' : '';
		}, (string) $html );
		return null === $html ? '' : $html;
	}

	/**
	 * Les petites capitales de l'auteur, visibles hors de l'éditeur : un
	 * courriel ne lit pas la feuille de style du site, seulement l'attribut
	 * posé sur la balise.
	 */
	private static function petites_capitales_du_html( $html ) {
		return str_replace( '<span class="na-pc">', '<span class="na-pc" style="font-variant:small-caps">', (string) $html );
	}
	/**
	 * Une chaîne ramenée à sa borne. « $nom » nomme le champ pour la
	 * rédaction : la coupe se faisait en silence, et un texte de 35 000
	 * caractères perdait sa fin sans que personne le sache.
	 */
	private function limit_string( $value, $max, $nom = '' ) {
		$value  = (string) $value;
		$coupee = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
		if ( '' !== $nom && $coupee !== $value ) {
			$this->coupes[] = $nom . ' (au-delà de ' . number_format( $max, 0, ',', "\u{00A0}" ) . ' caractères)';
		}
		return $coupee;
	}

	/** Les champs coupés pendant ce dépôt, nommés pour la rédaction. */
	private $coupes = array();

	/** L'avis des coupes de ce dépôt, ou rien. */
	private function avis_des_coupes() {
		if ( empty( $this->coupes ) ) {
			return array();
		}
		return array( 'Coupé au dépôt, faute de place'
			. "\u{00A0}: " . implode( ', ', array_unique( $this->coupes ) )
			. '. La fin manque'
			. "\u{00A0}: la redemander à l’auteur." );
	}
	private function collect_select( $name, $allowed, $required = false ) {
		$val = isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : '';
		// Un lien de correction d'avant un changement de libellé renvoie
		// l'ancien : il vaut le nouveau.
		$val = self::rubrique_actuelle( $val );
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
			$label = isset( $item['label'] ) && is_string( $item['label'] )
				? sanitize_text_field( self::sans_controles( $item['label'], true ) ) : '';
			// L'ARK de Frantiq seul, et un identifiant de concept en chiffres :
			// le reste n'identifie rien, et partait en lien dans le Word ou en
			// chemin vers l'API. Le terme se garde, sans identifiant.
			$ark = isset( $item['ark'] ) && is_string( $item['ark'] )
				? Notice_Archeomed_Thesaurus::ark_propre( esc_url_raw( $item['ark'] ) ) : '';
			$id_concept = isset( $item['idConcept'] ) && is_scalar( $item['idConcept'] )
				? trim( (string) $item['idConcept'] ) : '';
			$id_concept = ( '' !== $ark && ctype_digit( $id_concept ) ) ? $id_concept : '';
			$fullpath = isset( $item['fullpath'] ) && is_string( $item['fullpath'] ) ? sanitize_text_field( $item['fullpath'] ) : '';
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
			$lieux = $d['lieux'];
		} else {
			$nom = isset( $d['commune'] ) ? (string) $d['commune'] : '';
			if ( '' === trim( $nom ) ) {
				return array();
			}
			$lieux = array( array(
				'nom' => $nom,
				'ark' => isset( $d['commune_ark'] ) ? (string) $d['commune_ark'] : '',
			) );
		}
		// À la sortie, et non dans la saisie : les notices déjà reçues se
		// redressent sans qu'on touche à ce qu'elles portent.
		$departement = isset( $d['departement'] ) ? (string) $d['departement'] : '';
		foreach ( $lieux as $i => $lieu ) {
			$lieux[ $i ] = array(
				'nom' => $this->nom_du_lieu( isset( $lieu['nom'] ) ? $lieu['nom'] : '', $departement ),
				'ark' => Notice_Archeomed_Thesaurus::ark_propre( isset( $lieu['ark'] ) ? $lieu['ark'] : '' ),
			);
		}
		return $lieux;
	}

	/**
	 * Le nom d'un lieu tel que le titre l'imprime.
	 *
	 * Pactols distingue les homonymes dans la forme même du lieu : « Falaise
	 * (Calvados) ». Le titre posant sa parenthèse, on lisait « Falaise
	 * (Calvados) (Calvados). Château ». La précision de Pactols s'ôte quand
	 * elle redit celle de la parenthèse ; une autre — « Coulonces (Vire
	 * Normandie) » — se garde, faute de savoir ce que la revue en veut.
	 */
	private function nom_du_lieu( $nom, $departement ) {
		$nom  = (string) $nom;
		$dept = $this->sans_parentheses( $departement );
		if ( '' === $dept || ! preg_match( '/^(.*\S)\s*\(([^()]+)\)$/u', $nom, $m ) ) {
			return $nom;
		}
		$plier = function ( $texte ) {
			return strtolower( remove_accents( str_replace( array( '’', '‘' ), "'", trim( $texte ) ) ) );
		};
		return $plier( $m[2] ) === $plier( $dept ) ? $m[1] : $nom;
	}

	/**
	 * Le titre d'une notice dans la liste d'administration et le
	 * récapitulatif : « Caen (Calvados). Château — 2025 ».
	 *
	 * Sans le lieu-dit, deux notices d'une même commune et d'une même année
	 * portaient le même titre ; avec le libellé brut d'un homonyme, « Falaise
	 * (Calvados) (Calvados) ».
	 */
	public function titre_de_liste( $d ) {
		$lieu_dit = isset( $d['lieu_dit'] ) ? trim( (string) $d['lieu_dit'] ) : '';
		$annee    = isset( $d['annee'] ) ? trim( (string) $d['annee'] ) : '';
		return trim( $this->lieux_en_ligne( $d )
			. ' (' . $this->sans_parentheses( isset( $d['departement'] ) ? $d['departement'] : '' ) . ')'
			. ( '' !== $lieu_dit ? Notice_Archeomed_Normes::avant_le_lieu_dit() . $lieu_dit : '' )
			. ( '' !== $annee ? ' — ' . $annee : '' ) );
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
		$rubrique = trim( self::rubrique_actuelle( trim( (string) $rubrique ) ) );
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
		// La rubrique V se divise par matière, puis par famille : « V. A1. –
		// Céramique, terres cuites architecturales, verrerie : opération de
		// terrain ». Sans matière, une notice d'avant garde « V. 1. ».
		$matiere = self::matiere_de( $d );
		if ( '' !== $matiere ) {
			$apres = array_key_exists( $famille['rang'], self::FAMILLES_APRES_LA_MATIERE )
				? self::FAMILLES_APRES_LA_MATIERE[ $famille['rang'] ]
				: ( function_exists( 'mb_strtolower' ) ? mb_strtolower( $famille['nom'], 'UTF-8' ) : strtolower( $famille['nom'] ) );
			return $numero . '. ' . $matiere . $famille['rang'] . '. – ' . self::MATIERES[ $matiere ] . "\u{00A0}: " . $apres;
		}
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
		$valeur = trim( $valeur, " \t\n\r\0\x0B()" );
		// « l'Aude », « l'Orne », « l'Eure » : le remplissage automatique
		// laissait l'article, faute d'admettre l'apostrophe collée au nom.
		// Aucun département ni aucune région ne s'écrit ainsi en tête de
		// parenthèse ; les notices déjà reçues se redressent ici.
		return preg_replace( "/^l['’]\\s*(?=\\p{Lu})/u", '', $valeur );
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
			$nom = $this->limit_string( sanitize_text_field( (string) $nom ), 120, 'un lieu' );
			if ( '' === trim( $nom ) ) {
				continue;   // une ligne laissée vide ne dit rien
			}
			// Un ARK qui n'est pas de Frantiq — « javascript:… » passait le
			// tamis des champs de texte — n'est pas un identifiant : le lieu
			// se garde sans lui.
			$ark = isset( $arks[ $rang ] ) && is_string( $arks[ $rang ] )
				? Notice_Archeomed_Thesaurus::ark_propre( $this->limit_string( sanitize_text_field( $arks[ $rang ] ), 200 ) ) : '';
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
			$nom = $this->limit_string( sanitize_text_field( (string) $nom ), 200, 'un organisme' );
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
	 * la saisie s'imprime telle quelle, la nature exceptée.
	 *
	 * Une seule fonction pour le texte et pour le bloc d'index : ce dernier
	 * porte la graphie du texte en « term type="orig" », et deux calculs
	 * séparés finiraient par diverger sans que rien ne le signale.
	 */
	private function graphie_imprimee( $item, $capitale = false ) {
		// La capitale des périodes vaut aussi pour la forme du thésaurus :
		// sans elle, le dépôt imprimait « Haut Moyen Âge » et le fascicule,
		// une fois les termes lus, « haut Moyen Âge ».
		if ( ! empty( $item['prefLabel'] ) ) {
			$prefere = trim( (string) $item['prefLabel'] );
			return $capitale ? $this->capitale_initiale( $prefere ) : $prefere;
		}
		$label = isset( $item['label'] ) ? trim( (string) $item['label'] ) : '';
		if ( $capitale ) {
			return $this->capitale_initiale( $label );
		}
		// Seule une nature, libellé d'une case du formulaire écrit avec sa
		// capitale, prend le bas de casse de la revue. Un sujet est la forme
		// que Pactols a proposée ou celle que l'auteur a tapée : bas-cassée,
		// « Guillaume 2 (duc de Normandie) » devenait « guillaume 2 », et
		// « Saint-Étienne », « saint-Étienne ». Les mots-clés gardent leur
		// capitale quand ce sont des noms propres ; la rédaction voit le reste.
		return isset( $this->natures[ $label ] ) ? $this->bas_de_casse( $label ) : $label;
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
			array( 'nature_items',           Notice_Archeomed_Styles::rend( 'nature' ),       'pactols:Sujets',      false ),
			array( 'pactols_periods_items',  Notice_Archeomed_Styles::rend( 'periodes' ),     'pactols:Chronologie', true ),
			array( 'pactols_subjects_items', Notice_Archeomed_Styles::rend( 'mots_cles' ),    'pactols:Sujets',      false ),
			array( 'pactols_places_items',   Notice_Archeomed_Styles::rend( 'autres_lieux' ), 'pactols:Lieux',       true ),
		);
		$corps = '';
		foreach ( $zones as $zone ) {
			list( $clef, $rend, $index_name, $capitale ) = $zone;
			$blocs = '';
			foreach ( (array) ( isset( $d[ $clef ] ) ? $d[ $clef ] : array() ) as $item ) {
				$ark = Notice_Archeomed_Thesaurus::ark_propre( isset( $item['ark'] ) ? $item['ark'] : '' );
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
				$corps .= "\n   <na:zone rend=\"" . Notice_Archeomed_Thesaurus::xml( $rend ) . "\">"
					. $blocs . "\n   </na:zone>";
			}
		}
		if ( '' === $corps ) {
			return '';
		}
		// Une date, ou deux en intervalle ISO 8601 quand les termes n'ont pas
		// été lus le même jour.
		list( $du, $au ) = $this->millesime( $d, $termes );
		$lu_le = ( '' === $du ) ? '' : ( $du === $au ? $du : $du . '/' . $au );
		$lieux     = $this->lieux_de( $d );
		$titre     = trim( ( isset( $lieux[0] ) ? $lieux[0]['nom'] : '' ) . ' ' . $d['lieu_dit'] );
		// La référence du dépôt, seul identifiant stable d'une notice : commune
		// et lieu-dit ne la désignent pas sans ambiguïté. Sur l'enveloppe, qui
		// est de l'espace de noms du plugin et non du TEI.
		$reference = (string) get_post_meta( (int) $id, '_na_reference', true );
		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<!-- Blocs d\'index Pactols calculés depuis les identifiants choisis au dépôt.' . "\n"
			. '     Chaque « zone » nomme le style du paragraphe où son contenu se colle ;' . "\n"
			. '     seuls les « index » qu\'elle contient sont du TEI. -->' . "\n"
			// L'enveloppe a son propre espace de noms : « indexation » n'existe
			// pas en TEI, et « zone » y désigne une surface de fac-similé.
			. '<na:indexation xmlns:na="' . self::ESPACE_INDEXATION . '" xmlns="http://www.tei-c.org/ns/1.0"'
			. ' notice="' . Notice_Archeomed_Thesaurus::xml( $titre ) . '"'
			. ( '' !== $reference ? ' ref="' . Notice_Archeomed_Thesaurus::xml( $reference ) . '"' : '' )
			. ( '' !== $lu_le ? ' lu-le="' . Notice_Archeomed_Thesaurus::xml( $lu_le ) . '"' : '' ) . '>'
			. $corps . "\n" . '</na:indexation>' . "\n";
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
			$ark        = Notice_Archeomed_Thesaurus::ark_propre( isset( $item['ark'] ) ? $item['ark'] : '' );
			$morceaux[] = '' !== $ark
				? $doc->hyperlink( $ark, $label )
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
		foreach ( array( 'titre', 'legende', 'credits', 'alt', 'description' ) as $clef ) {
			$brut = isset( $_POST[ 'illus_' . $clef ] ) ? wp_unslash( $_POST[ 'illus_' . $clef ] ) : array();
			$parts[ $clef ] = is_array( $brut ) ? array_values( $brut ) : array();
		}
		$combien = 0;
		foreach ( $parts as $liste ) {
			$combien = max( $combien, count( $liste ) );
		}
		// Un fichier déposé est une figure, légendée ou non. On jetait la
		// ligne vide : la figure disparaissait alors du document, du courriel
		// et du dossier, et la suivante prenait sa place sans que personne
		// ne le voie. Elle garde désormais son bloc, et la rédaction lit
		// qu'il lui manque un titre.
		$combien = max( $combien, $this->fichiers_deposes() );
		$combien = min( $combien, self::MAX_FILES );

		$items = array();
		for ( $i = 0; $i < $combien; $i++ ) {
			$item = array( 'rang' => $i + 1 );
			foreach ( $parts as $clef => $liste ) {
				$brut = isset( $liste[ $i ] ) && is_string( $liste[ $i ] ) ? $liste[ $i ] : '';
				if ( 'alt' === $clef ) {
					// Le texte alternatif, que liront les personnes malvoyantes
					// ou non voyantes : une ligne, sans balise. Le même
					// nettoyage sert à la rédaction qui le corrige sur la fiche.
					$item['alt'] = $this->limit_string( Notice_Archeomed_Controles::texte_alternatif_propre( $brut ),
						Notice_Archeomed_Controles::ALT_MAX, 'le texte alternatif de la fig. ' . ( $i + 1 ) );
					continue;
				}
				if ( 'description' === $clef ) {
					// La description détaillée d'une figure complexe : plusieurs
					// paragraphes permis, sans balise, chaque ligne nettoyée.
					$item['description'] = $this->limit_string( Notice_Archeomed_Controles::description_propre( $brut ),
						Notice_Archeomed_Controles::DESCRIPTION_MAX, 'la description détaillée de la fig. ' . ( $i + 1 ) );
					continue;
				}
				$valeur = sanitize_textarea_field( self::chevrons_a_garder( $brut ) );
				$item[ $clef ] = $this->limit_string( $valeur, 'legende' === $clef ? 1500 : 400,
					( 'legende' === $clef ? 'la légende' : ( 'titre' === $clef ? 'le titre' : 'les crédits' ) ) . ' de la fig. ' . ( $i + 1 ) );
			}
			// « Fig. 1 : Plan » retapé dans le titre donnait « Fig. 1 Fig. 1 :
			// Plan » : le numéro, c'est le plugin qui le pose.
			$item['titre'] = Notice_Archeomed_Controles::titre_sans_numero( $item['titre'], $item['rang'] );
			// Le nom du fichier déposé pour cette figure : un texte alternatif
			// qui ne fait que le reprendre (« DSC_0042 ») reçoit un avis, au
			// dépôt comme sur la fiche.
			if ( isset( $_FILES['illustrations']['name'][ $i ], $_FILES['illustrations']['error'][ $i ] )
				&& is_string( $_FILES['illustrations']['name'][ $i ] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES['illustrations']['error'][ $i ] ) {
				$item['fichier_depose'] = sanitize_text_field( $_FILES['illustrations']['name'][ $i ] );
			}
			// L'autorisation de reproduction jointe à cette figure, s'il y en a
			// une : le document le signale à la rédaction.
			$item['autorisation'] = isset( $_FILES['illus_autorisation']['error'][ $i ] )
				&& UPLOAD_ERR_OK === (int) $_FILES['illus_autorisation']['error'][ $i ];
			// Une ligne vide sans fichier en face ne dit rien : c'est le seul
			// cas où on ne la garde pas.
			if ( $i >= $this->fichiers_deposes()
				&& '' === trim( $item['titre'] . $item['legende'] . $item['credits'] . $item['alt'] . $item['description'] ) ) {
				continue;
			}
			$items[] = $item;
		}
		return $items;
	}

	/**
	 * « L. <5 cm, l. >2 cm » : l'assainissement de WordPress prend « <5 … > »
	 * pour une balise et l'ôte. On écrit d'avance « &lt; » devant un chiffre
	 * ou un signe égal — la forme que WordPress donne lui-même au chevron
	 * isolé, et que le document rend en « < ».
	 */
	private static function chevrons_a_garder( $texte ) {
		return (string) preg_replace( '/<(?=[\d=])/', '&lt;', (string) $texte );
	}

	/**
	 * L'année, ou les années d'une opération pluriannuelle : « 2004-2005 ».
	 *
	 * On voit arriver « 2004 - 2005 », « 2004/2005 », « 2004 à 2005 » ou un
	 * tiret long : la revue écrit « 2004-2005 », et le classement comme le
	 * titre du fichier veulent une seule forme.
	 */
	private static function annee_normalisee( $annee ) {
		$annee = trim( (string) $annee );
		if ( preg_match( '/^(\d{4})\s*(?:-|‐|‑|–|—|\/|à|au|et)\s*(\d{2,4})$/u', $annee, $m ) ) {
			$fin = 2 === strlen( $m[2] ) ? substr( $m[1], 0, 2 ) . $m[2] : $m[2];
			// Le trait est celui que la revue a choisi : « - », « – » ou « / ».
			return $m[1] . Notice_Archeomed_Normes::trait_des_annees() . $fin;
		}
		return $annee;
	}

	/**
	 * Les pixels de chaque figure, rangés sur sa ligne par le rang du fichier.
	 * « getimagesize » ne lit que l'en-tête, pour le JPEG comme pour le TIFF ;
	 * un PDF n'a pas de pixels, et rien n'est posé.
	 */
	private static function poser_les_pixels( $d, $fichiers ) {
		if ( empty( $d['illustrations'] ) ) {
			return $d;
		}
		foreach ( array_values( (array) $fichiers ) as $i => $fichier ) {
			$mesure = @getimagesize( $fichier );
			if ( empty( $mesure[0] ) || empty( $mesure[1] ) ) {
				continue;
			}
			foreach ( $d['illustrations'] as $j => $item ) {
				if ( (int) $item['rang'] === $i + 1 ) {
					$d['illustrations'][ $j ]['pixels'] = array( (int) $mesure[0], (int) $mesure[1] );
				}
			}
		}
		return $d;
	}

	/** Le lieu-dit d'un titre en HTML, en italique si la revue le veut. */
	private static function lieu_dit_html( $lieu_dit ) {
		return Notice_Archeomed_Normes::lieu_dit_en_italique()
			? '<em>' . esc_html( $lieu_dit ) . '</em>' : esc_html( $lieu_dit );
	}

	/**
	 * Le chiffre romain d'un siècle en petites capitales, pour le HTML.
	 *
	 * Le Word compose « xii » en bas de casse sous w:smallCaps, comme
	 * Métopes le veut. Dans le HTML, ce bas de casse était lu tel quel : un
	 * lecteur d'écran disait le mot « vie » pour VI, « mi » pour MI. Le
	 * chiffre s'écrit donc en capitales, et c'est le style qui le réduit
	 * (« all-small-caps » réduit aussi les capitales) : l'œil voit des
	 * petites capitales, la synthèse lit un nombre. Un client de courriel
	 * qui ignore ce style montre des capitales, la forme juste d'un siècle.
	 */
	private static function chiffre_en_petites_capitales( $chiffre ) {
		return '<span style="font-variant: small-caps; font-variant-caps: all-small-caps">' . strtoupper( (string) $chiffre ) . '</span>';
	}

	/**
	 * Les siècles en petites capitales dans la page de relecture, comme le
	 * document les compose : « XII » en petites capitales, « e » en exposant.
	 * Ne sert que sur le texte de la notice, qui n'a pas d'attributs où un
	 * siècle pourrait se trouver.
	 */
	private static function siecles_du_html( $html ) {
		$blanc    = '(?:[\s\x{A0}]|&nbsp;)';
		$mot      = Notice_Archeomed_Normes::valeur( 'siecle_mot' );
		$chiffres = Notice_Archeomed_Normes::valeur( 'siecle_chiffres' );
		$ordinal  = Notice_Archeomed_Normes::valeur( 'siecle_ordinal' );
		// Le mot « siècle » après un siècle en chiffres, comme au document :
		// abrégé, en toutes lettres, ou tel quel.
		if ( 'abrege' === $mot ) {
			$html = preg_replace_callback(
				// « s. » déjà abrégé prend son insécable comme « siècle » ; un
				// siècle mis en petites capitales par l'auteur se reconnaît
				// derrière sa balise.
				'#(*UCP)(?<![\pL\d])([IVXLC]+|[ivxlc]+)((?:</span>)?(?:<sup>)?(?:er|re|e)(?:</sup>)?(?:</span>)?)' . $blanc . '+(?:siècles?(?![\pL\d])\.?|s\.)#u',
				function ( $m ) {
					return preg_match( '/^[lcLC]+$/', $m[1] ) ? $m[0] : $m[1] . $m[2] . "\u{00A0}s.";
				},
				(string) $html
			);
		} elseif ( 'entier' === $mot ) {
			$html = preg_replace_callback(
				'#(*UCP)(?<![\pL\d])([IVXLC]+|[ivxlc]+)((?:</span>)?(?:<sup>)?(?:er|re|e)(?:</sup>)?(?:</span>)?)' . $blanc . '+s\.(?=(' . $blanc . '*<|' . $blanc . '+\p{Lu})?)#u',
				function ( $m ) {
					if ( preg_match( '/^[lcLC]+$/', $m[1] ) ) {
						return $m[0];
					}
					return $m[1] . $m[2] . ' siècle' . ( ! empty( $m[3] ) ? '.' : '' );
				},
				(string) $html
			);
		}
		if ( 'tel' === $chiffres && 'exposant' !== $ordinal ) {
			return null === $html ? '' : $html;
		}
		// La liaison et son article, comme au document : « du Xe au XIIe
		// siècle », « entre le XIe et le XIIe siècle ».
		$annonce = '(?=(?:' . $blanc . '*(?:-|–|—|et|à|au|aux|ou)(?:' . $blanc . '*(?:début|milieu|fin))?(?:' . $blanc . '+(?:le|la|les|du|des))?' . $blanc . '*(?:<sup>)?(?:[IVXLC]+|[ivxlc]+)(?:</sup>)?(?:<sup>)?(?:er|re|e)(?:</sup>)?)*' . $blanc . '+(?:s\.|siècles?\b|millénaires?\b))';
		// Le chiffre que l'auteur a mis en petites capitales par le bouton
		// « Pc », l'ordinal tapé après, hors de la balise — ou dedans,
		// quand « XIIe » entier a été sélectionné. La balise séparait le
		// chiffre de l'ordinal, et celui-ci restait sur la ligne.
		$html = preg_replace_callback(
			'#(*UCP)<span class="na-pc">([IVXLC]+|[ivxlc]+)(?:(er|re|e)|<sup>(er|re|e)</sup>)?</span>(?:<sup>(er|re|e)</sup>|(er|re|e)(?![\pL\d]))?' . $annonce . '#u',
			function ( $m ) use ( $chiffres, $ordinal ) {
				$dedans  = ( isset( $m[2] ) && '' !== $m[2] ) ? $m[2] : ( isset( $m[3] ) ? $m[3] : '' );
				$dehors  = ( isset( $m[4] ) && '' !== $m[4] ) ? $m[4] : ( isset( $m[5] ) ? $m[5] : '' );
				$suffixe = '' !== $dedans ? $dedans : $dehors;
				if ( preg_match( '/^[lcLC]+$/', $m[1] ) || '' === $suffixe || ( '' !== $dedans && '' !== $dehors ) ) {
					return $m[0];
				}
				if ( 'petites_capitales' === $chiffres ) {
					$chiffre = self::chiffre_en_petites_capitales( $m[1] );
				} elseif ( 'capitales' === $chiffres ) {
					$chiffre = strtoupper( $m[1] );
				} else {
					$chiffre = '<span class="na-pc">' . $m[1] . '</span>';
				}
				return $chiffre . ( 'exposant' === $ordinal ? '<sup>' . $suffixe . '</sup>' : $suffixe );
			},
			(string) $html
		);
		$html = preg_replace_callback(
			'#(*UCP)(?<![\pL\d])([IVXLC]+|[ivxlc]+)(?:<sup>(er|re|e)</sup>|(er|re|e)(?![\pL\d]))' . $annonce . '#u',
			function ( $m ) use ( $chiffres, $ordinal ) {
				if ( preg_match( '/^[lcLC]+$/', $m[1] ) ) {
					return $m[0];
				}
				$suffixe = '' !== $m[2] ? $m[2] : $m[3];
				if ( 'petites_capitales' === $chiffres ) {
					$chiffre = self::chiffre_en_petites_capitales( $m[1] );
				} else {
					$chiffre = 'capitales' === $chiffres ? strtoupper( $m[1] ) : $m[1];
				}
				return $chiffre . ( 'exposant' === $ordinal ? '<sup>' . $suffixe . '</sup>' : $suffixe );
			},
			(string) $html
		);
		return null === $html ? '' : $html;
	}

	/**
	 * La typographie posée sur le texte d'un fragment HTML, balises
	 * laissées telles quelles. Chaque morceau voit le texte qui le précède
	 * dans son paragraphe : une ponctuation qui suit un mot en italique
	 * reçoit son insécable.
	 */
	private static function typographie_du_html( $html ) {
		$morceaux = preg_split( '#(<[^>]+>)#', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		$rendu    = '';
		$avant    = '';
		foreach ( $morceaux as $morceau ) {
			if ( '' === $morceau ) {
				continue;
			}
			if ( '<' === $morceau[0] ) {
				if ( preg_match( '#^</?(p|br|li|div)\b#i', $morceau ) ) {
					$avant = '';
				}
				$rendu .= $morceau;
				continue;
			}
			$texte  = html_entity_decode( $morceau, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$rendu .= esc_html( Notice_Archeomed_Normes::typographie( $texte, $avant, $avant ) );
			$avant .= $texte;
		}
		return $rendu;
	}

	/** Le nombre de fichiers réellement joints au formulaire. */
	private function fichiers_deposes() {
		if ( empty( $_FILES['illustrations']['error'] ) || ! is_array( $_FILES['illustrations']['error'] ) ) {
			return 0;
		}
		$n = 0;
		foreach ( $_FILES['illustrations']['error'] as $erreur ) {
			if ( UPLOAD_ERR_NO_FILE !== (int) $erreur ) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * Tous les champs obligatoires qui manquent, dans l'ordre de la page.
	 *
	 * On s'arrêtait au premier : l'auteur corrigeait, renvoyait, découvrait
	 * le suivant — un aller-retour par oubli, et les illustrations à
	 * redéposer à chaque fois. Avant encore, on rendait « faux » sans dire
	 * quoi.
	 */
	private function champs_manquants( $d ) {
		$manquants = array();
		$ordre     = array( 'commune', 'departement', 'lieu_dit', 'nature', 'annee', 'organisme',
			'resp_prenom', 'resp_nom', 'resp_email', 'resp_inst', 'texte', 'rubrique_principale' );
		foreach ( $ordre as $champ ) {
			if ( 'resp_email' === $champ ) {
				$vide = empty( $d['resp_email'] ) || ! is_email( $d['resp_email'] );
			} elseif ( 'texte' === $champ ) {
				$vide = empty( $d['texte_notice'] ) || '' === trim( wp_strip_all_tags( $d['texte_notice'] ) );
			} else {
				$vide = empty( $d[ $champ ] ) || '' === trim( (string) $d[ $champ ] );
			}
			if ( $vide ) {
				$manquants[] = $champ;
			}
		}
		// La matière, que seule la rubrique V demande.
		if ( isset( $d['rubrique_principale'] ) && self::RUBRIQUE_ARTISANAT === $d['rubrique_principale']
			&& '' === self::matiere_de( $d ) ) {
			$manquants[] = 'rubrique_matiere';
		}
		// Le texte alternatif de chaque figure déposée, que la revue veut
		// pour ses lecteurs malvoyants et non voyants. Une figure sans
		// fichier — une notice ancienne, une correction qui ne redépose rien
		// — n'en demande pas.
		$deposes = $this->fichiers_deposes();
		foreach ( isset( $d['illustrations'] ) ? (array) $d['illustrations'] : array() as $item ) {
			$rang = is_array( $item ) && isset( $item['rang'] ) ? (int) $item['rang'] : 0;
			if ( $rang >= 1 && $rang <= min( $deposes, self::MAX_FILES )
				&& '' === trim( isset( $item['alt'] ) ? (string) $item['alt'] : '' ) ) {
				$manquants[] = 'illus_alt_' . $rang;
			}
		}
		return $manquants;
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
			$parts = array( '<strong>' . esc_html( Notice_Archeomed_Normes::numero_de_figure( $item['rang'] ) ) . '</strong>' );
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
			if ( ! empty( $item['alt'] ) ) {
				$lignes[] = esc_html( "Texte alternatif\u{00A0}: " . Notice_Archeomed_Controles::une_ligne( $item['alt'] ) );
			}
			if ( ! empty( $item['description'] ) && is_scalar( $item['description'] ) ) {
				$lignes[] = esc_html( "Description détaillée\u{00A0}: " ) . implode( '<br>', array_map( 'esc_html', self::paragraphes_simples( $item['description'] ) ) );
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
		if ( '' !== self::matiere_de( $d ) ) {
			$entete_lines[] = $this->meta_line( 'Matière', self::matiere_de( $d ) . ' – ' . self::MATIERES[ self::matiere_de( $d ) ] );
		}
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
		// La référence du dépôt, que l'auteur et la rédaction se citeront.
		if ( ! empty( $d['reference'] ) ) {
			$admin_lines[] = $this->meta_line( 'Référence du dépôt', $d['reference'] );
		}
		$admin_lines[] = $this->meta_line( 'Année de l\'opération', $d['annee'] );
		if ( '' !== $d['num_autorisation'] ) {
			$admin_lines[] = $this->meta_line( 'Numéro d\'autorisation', $d['num_autorisation'] );
		}
		if ( ! empty( $d['id_patriarche'] ) ) {
			$admin_lines[] = $this->meta_line( 'Identifiant Patriarche', $d['id_patriarche'] );
		}
		if ( ! empty( $d['originaux_lien'] ) ) {
			$admin_lines[] = $this->meta_line( 'Originaux des figures à télécharger', $d['originaux_lien'] );
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
			$loc_line .= esc_html( Notice_Archeomed_Normes::avant_le_lieu_dit() ) . self::lieu_dit_html( $d['lieu_dit'] );
		}
		$nature_line = 'Nature de l\'opération : ' . esc_html( $d['nature'] );
		$bloc_resp = ' (' . esc_html( implode( ' ; ', $segments ) ) . ')';
		// Le texte sous les normes de la revue, comme au Word et à la page de
		// relecture : le courriel, la copie de l'auteur et la fiche montraient
		// la saisie brute, et l'on croyait les siècles restés en capitales.
		// Les notices déjà reçues gardent ce qui leur a été envoyé.
		$texte = trim( self::petites_capitales_du_html( self::siecles_du_html(
			self::typographie_du_html( (string) $d['texte_notice'] ) ) ) );
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
		// Ce que les contrôles du dépôt ont relevé : l'auteur le lit dans sa
		// copie, avec le lien pour corriger, et la rédaction sait d'avance ce
		// qu'elle aura à vérifier.
		if ( ! empty( $d['avis'] ) ) {
			$html .= '<div style="background:#fdf6e3;border-left:4px solid #8a6d3b;padding:10px 14px;margin:0 0 14px;' . $font . 'font-size:14px;">'
				. '<strong>À vérifier :</strong><ul style="margin:6px 0 0;padding-left:20px;">';
			foreach ( (array) $d['avis'] as $un_avis ) {
				$html .= '<li>' . esc_html( $un_avis ) . '</li>';
			}
			$html .= '</ul></div>';
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
		// Le retour se fait sur l'onglet de la feuille : c'est là que le
		// message s'affiche, et non sur le premier onglet de la page.
		$retour = Notice_Archeomed_Settings::url( 'feuille' );

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
	 * Fabrique le Word joint au courriel et le met à l'abri de la purge ;
	 * rend son chemin, ou une chaîne vide.
	 *
	 * DOCX par défaut, RTF en repli si l'extension ZipArchive manque à
	 * l'hébergement ou si le DOCX a échoué. Une erreur dans la saisie ne fait
	 * pas tomber la requête : la notice est déjà inscrite, et l'auteur qui
	 * verrait une page d'erreur la redéposerait.
	 */
	private function fabriquer_le_document( $d_doc ) {
		$erreur = '';
		try {
			$format  = class_exists( 'ZipArchive' ) ? 'docx' : 'rtf';
			$fichier = $this->build_rtf_file( $d_doc, $erreur, $format );
			if ( '' === $fichier && 'docx' === $format ) {
				error_log( 'Notice Archeomed: DOCX generation failed, falling back to RTF: ' . $erreur );
				$fichier = $this->build_rtf_file( $d_doc, $erreur, 'rtf' );
			}
		} catch ( Throwable $e ) {
			$fichier = '';
			$erreur  = $e->getMessage();
		}
		if ( '' === $fichier ) {
			error_log( 'Notice Archeomed: RTF generation failed: ' . $erreur );
			return '';
		}
		$mis = $this->mettre_a_labri( array( $fichier ) );
		return ! empty( $mis ) ? $mis[0] : '';
	}

	/**
	 * Refait le Word d'une notice dont le fichier a disparu, à partir de sa
	 * saisie ; rend son chemin, ou une chaîne vide.
	 *
	 * Le Word attend l'envoi au répertoire temporaire : la purge l'efface au
	 * bout d'un mois, un redémarrage peut vider « /tmp ». La notice partait
	 * alors sans document, et ni le courriel ni la fiche ne le disaient.
	 */
	private function refaire_le_document( $id ) {
		try {
			$d_doc = $this->saisie_de( $id, true );
		} catch ( Throwable $e ) {
			error_log( 'Notice Archeomed: saisie illisible pour le document : ' . $e->getMessage() );
			$d_doc = null;
		}
		$document = ( null === $d_doc ) ? '' : $this->fabriquer_le_document( $d_doc );
		if ( '' !== $document ) {
			update_post_meta( (int) $id, '_na_document', $document );
		}
		return $document;
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
		if ( method_exists( $doc, 'definir_le_titre' ) ) {
			$doc->definir_le_titre( trim( $this->lieux_en_ligne( $d ) . ' (' . $this->sans_parentheses( $d['departement'] ) . ')'
				. ( '' !== trim( (string) $d['lieu_dit'] ) ? ', ' . trim( (string) $d['lieu_dit'] ) : '' )
				. ( '' !== trim( (string) $d['annee'] ) ? ' — ' . trim( (string) $d['annee'] ) : '' ) ) );
		}

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
	 * Ce que la notice corrigée laisse sur le site quand la correction n'en
	 * reprend pas toutes les figures ; une phrase, ou une chaîne vide.
	 *
	 * Une correction est un dépôt neuf : aucun navigateur ne remet un fichier
	 * dans un formulaire, et l'auteur qui ne redépose pas ses figures envoie
	 * une notice sans elles. La rédaction lisait « le précédent est à
	 * supprimer », et vider la corbeille effaçait les originaux. Rien n'est
	 * recopié : on dit où ils sont.
	 */
	private function figures_de_la_remplacee( $d ) {
		$remplace = isset( $d['remplace'] ) ? strtoupper( trim( (string) $d['remplace'] ) ) : '';
		if ( '' === $remplace ) {
			return '';
		}
		$ids = get_posts( array(
			'post_type'      => Notice_Archeomed_File::CPT,
			'post_status'    => array( 'private', 'trash' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => '_na_reference', 'value' => $remplace ) ),
		) );
		if ( empty( $ids ) ) {
			return '';
		}
		$avant      = count( array_filter( (array) get_post_meta( (int) $ids[0], '_na_illustrations', true ), 'is_string' ) );
		$maintenant = isset( $d['illustrations'] ) ? count( (array) $d['illustrations'] ) : 0;
		$phrases    = array();
		if ( $avant > $maintenant ) {
			// « cette correction en porte aucune » : la négation manquait.
			$phrases[] = sprintf( "La notice remplacée %1\$s portait %2\$s\u{00A0}; cette correction %3\$s.",
				$remplace,
				sprintf( _n( '%d figure', '%d figures', $avant, 'notice-archeomed' ), $avant ),
				0 === $maintenant ? 'n’en porte aucune' : 'en porte ' . $maintenant );
		}
		// L'autorisation de reproduction ne suit pas une correction : aucun
		// navigateur ne remet un fichier dans un formulaire, et l'auteur qui
		// redéposait ses figures sans elle l'ôtait du Word et du courriel
		// sans que personne le sache. Elle reste dans la fiche de la notice
		// remplacée, qu'on demandait de supprimer.
		$autorisations_avant = self::figures_autorisees( get_post_meta( (int) $ids[0], '_na_donnees', true ) );
		$autorisations       = count( self::figures_autorisees( $d ) );
		if ( $autorisations < count( $autorisations_avant ) ) {
			$numeros = array();
			foreach ( $autorisations_avant as $rang ) {
				$numeros[] = Notice_Archeomed_Normes::numero_de_figure( $rang );
			}
			$phrases[] = sprintf( "%1\$s %2\$s (%3\$s)\u{00A0}; cette correction %4\$s.",
				empty( $phrases ) ? 'La notice remplacée ' . $remplace . ' portait' : 'Elle portait aussi',
				1 === count( $autorisations_avant ) ? 'une autorisation de reproduction' : count( $autorisations_avant ) . ' autorisations de reproduction',
				implode( ', ', $numeros ),
				0 === $autorisations ? 'n’en porte aucune' : 'en porte ' . $autorisations );
		}
		if ( empty( $phrases ) ) {
			return '';
		}
		return implode( ' ', $phrases ) . sprintf( " Les fichiers de %s restent sur le site, dans sa fiche"
			. "\u{00A0}: ne la supprimez pas avant d’avoir repris ce qui manque.", $remplace );
	}

	/**
	 * Les rangs des figures d'une saisie qui portent une autorisation de
	 * reproduction ; une liste vide pour une saisie qui n'en a pas, ou
	 * d'avant les autorisations.
	 */
	private static function figures_autorisees( $d ) {
		$rangs = array();
		if ( ! is_array( $d ) || empty( $d['illustrations'] ) || ! is_array( $d['illustrations'] ) ) {
			return $rangs;
		}
		foreach ( $d['illustrations'] as $item ) {
			if ( is_array( $item ) && ! empty( $item['autorisation'] ) && isset( $item['rang'] ) ) {
				$rangs[] = (int) $item['rang'];
			}
		}
		return $rangs;
	}

	/**
	 * Le texte alternatif et la description détaillée d'une figure, à la fin
	 * de son bloc, juste avant le repère de fermeture.
	 *
	 * Le texte alternatif est toujours dans le texte de remplacement de
	 * l'image : c'est là que la documentation Métopes (« Styler les
	 * figures ») le place. Il n'a son paragraphe que si la rédaction donne un
	 * style au bloc « figure_alttext » : le gabarit porte « TEI_figure_alttext »
	 * et « TEI_figure-alttext », que la table des styles ne documente pas.
	 * [HYPOTHÈSE] Ce que la conversion Métopes fait d'un tel paragraphe n'est
	 * pas documenté.
	 *
	 * La description détaillée prend le même style ; sans style réglé, elle
	 * part « à supprimer » pour que la rédaction la lise quand même. L'un et
	 * l'autre sont du texte simple, en romain : ni siècles en petites
	 * capitales ni exposant, que le lecteur d'écran lirait de travers ; les
	 * insécables seules s'appliquent.
	 */
	private function poser_les_textes_d_accessibilite( $doc, $item, $alt ) {
		$style = Notice_Archeomed_Styles::de( 'figure_alttext' );
		if ( '' !== $alt && Notice_Archeomed_Styles::AUCUN !== $style ) {
			$doc->add_paragraph( $style, array( array( 'text' => $alt, 'sans_siecles' => true ) ) );
		}
		$description = isset( $item['description'] ) && is_scalar( $item['description'] ) ? trim( (string) $item['description'] ) : '';
		if ( '' === $description ) {
			return;
		}
		$style_description = Notice_Archeomed_Styles::AUCUN !== $style ? $style : Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER;
		foreach ( self::paragraphes_simples( $description ) as $i => $paragraphe ) {
			$runs = array( array( 'text' => $paragraphe, 'sans_siecles' => true ) );
			if ( 0 === $i && Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER === $style_description ) {
				array_unshift( $runs, array( 'text' => 'Description détaillée : ', 'b' => true ) );
			}
			$doc->add_paragraph( $style_description, $runs );
		}
	}

	/**
	 * Un texte en paragraphes simples : un par ligne non vide, blancs
	 * multiples ramenés à une espace.
	 */
	private static function paragraphes_simples( $texte ) {
		$paragraphes = array();
		foreach ( preg_split( '/\R+/u', (string) $texte ) as $ligne ) {
			$ligne = Notice_Archeomed_Controles::une_ligne( $ligne );
			if ( '' !== $ligne ) {
				$paragraphes[] = $ligne;
			}
		}
		return $paragraphes;
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
		// pose plus, la rédaction a fait le ménage depuis longtemps. Il porte
		// le style « à supprimer » : en Normal, trois paragraphes précédaient
		// le titre de l'unité éditoriale, que Métopes veut en tête.
		if ( $rubrique_en_tete && ! empty( $d['remplace'] ) ) {
			$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER, array(
				array( 'text' => '— CORRECTION ' . str_repeat( '—', 50 ) ) ) );
			$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER, array( array(
				'text' => 'Cette notice remplace le dépôt ' . $d['remplace']
					. ' : le précédent est à supprimer.',
				'b'    => true ) ) );
			$figures_restees = $this->figures_de_la_remplacee( $d );
			if ( '' !== $figures_restees ) {
				$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER, array( array( 'text' => $figures_restees ) ) );
			}
			$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER, array(
				array( 'text' => str_repeat( '—', 62 ) ) ) );
		}
		$commune_dept = $this->lieux_en_ligne( $d ) . ' (' . $departement . ')';

		// 1. Rubrique principale, en tête de notice : style Titre.
		//
		// Dans un fascicule, elle est posée une fois pour toutes en tête du
		// document : la répéter avant chaque notice donnait un sommaire à
		// chaque page, et faussait la hiérarchie que la chaîne XML en tire.
		if ( $rubrique_en_tete ) {
			$doc->add_paragraph( Notice_Archeomed_Styles::de( 'rubrique' ), array( array(
				'text' => $this->titre_de_rubrique( $d['rubrique_principale'] ) ) ) );
			// 2. La sous-rubrique, en titre de niveau 1 : « IV.1 – Opérations
			//    de terrain ». Dans un fascicule, elle se pose une fois par
			//    groupe et non par notice, comme la rubrique elle-même.
			$doc->add_paragraph( Notice_Archeomed_Styles::de( 'sous_rubrique' ), array( array(
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
			$titre .= $doc->plain( Notice_Archeomed_Normes::avant_le_lieu_dit() )
				. $this->run_xml( $doc, array( 'text' => $d['lieu_dit'], 'i' => Notice_Archeomed_Normes::lieu_dit_en_italique() ) );
		}
		$doc->add_raw_paragraph( Notice_Archeomed_Styles::de( 'titre_notice' ), $titre );
		// Une notice de la rubrique V reçue avant les matières se range sous
		// la sous-rubrique sans lettre : la rédaction lit qu'il faut la
		// classer.
		if ( isset( $d['rubrique_principale'] ) && self::RUBRIQUE_ARTISANAT === self::rubrique_actuelle( $d['rubrique_principale'] )
			&& '' === self::matiere_de( $d ) ) {
			$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER,
				array( array( 'text' => 'Matière à choisir (A, B ou C)', 'b' => true ) ) );
		}

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
			$doc->add_raw_paragraph( Notice_Archeomed_Styles::de( 'nature' ),
				$doc->plain( "Nature de l'opération : " )
					. implode( $doc->plain( ', ' ), $natures ) );
		}

		// Les lieux autres que celui de la notice, sous le titre : ils
		// n'avaient de place que dans le bloc d'indexation, avec leur ARK en
		// clair. Portés ici, ils se lisent et gardent leur identifiant en
		// lien, comme les autres termes indexés.
		//
		// Leur style est celui de l'index géographique de Métopes, que la
		// documentation rattache à l'index « Géographie » d'OpenEdition. Ils
		// portaient celui des mots-clés sujets : deux paragraphes du même style,
		// que rien ne distinguait sinon leur intitulé.
		$autres_lieux = $this->termes_pactols_lies( $doc,
			isset( $d['pactols_places_items'] ) ? $d['pactols_places_items'] : array(),
			true );
		if ( ! empty( $autres_lieux ) ) {
			$doc->add_raw_paragraph( Notice_Archeomed_Styles::de( 'autres_lieux' ),
				$doc->plain( 1 === count( $autres_lieux ) ? 'Autre lieu : ' : 'Autres lieux : ' )
					. implode( $doc->plain( ', ' ), $autres_lieux ) );
		}

		$periodes = $this->termes_pactols_lies( $doc,
			isset( $d['pactols_periods_items'] ) ? $d['pactols_periods_items'] : array(),
			true );
		if ( ! empty( $periodes ) ) {
			$doc->add_raw_paragraph( Notice_Archeomed_Styles::de( 'periodes' ),
				$doc->plain( 'Période historique : ' )
					. implode( $doc->plain( ', ' ), $periodes ) );
		}

		$doc->add_paragraph(
			Notice_Archeomed_Styles::de( 'annee' ),
			array( array( 'text' => "Année de l'opération : " . $d['annee'] ) )
		);
		if ( '' !== $d['num_autorisation'] ) {
			$doc->add_paragraph(
				Notice_Archeomed_Styles::de( 'num_autorisation' ),
				array( array( 'text' => "Numéro d'autorisation : " ), array( 'text' => $d['num_autorisation'], 'brut' => true ) )
			);
		}
		// L'identifiant Patriarche partage par défaut le style du numéro
		// d'autorisation — c'est celui que Métopes nomme « Identifiant
		// Patriarche », et rien d'autre ne lui convient mieux. Les deux
		// renseignements sont distincts : l'un est un arrêté, l'autre une
		// entrée dans la base du ministère. Ils sont donc collectés à part, et
		// chacun a sa correspondance dans les réglages.
		if ( ! empty( $d['id_patriarche'] ) ) {
			$doc->add_paragraph(
				Notice_Archeomed_Styles::de( 'id_patriarche' ),
				array( array( 'text' => 'Identifiant Patriarche : ' ), array( 'text' => $d['id_patriarche'], 'brut' => true ) )
			);
		}
		if ( ! empty( $d['rapport_lien'] ) ) {
			$doc->add_raw_paragraph(
				Notice_Archeomed_Styles::de( 'rapport' ),
				$doc->plain( 'Rapport final : ' )
					. $doc->hyperlink( $d['rapport_lien'], $d['rapport_lien'] )
			);
		}
		$organismes_doc = $this->organismes_de( $d );
		if ( ! empty( $organismes_doc ) ) {
			$doc->add_paragraph(
				Notice_Archeomed_Styles::de( 'organismes' ),
				array( array( 'text' => $this->libelle_organisme( count( $organismes_doc ) )
					. ' : ' . implode( ', ', $organismes_doc ) ) )
			);
		}
		// Les sujets Pactols, en clair et sans identifiant : ce sont eux que
		// la revue imprime sous le nom de « mots-clés ».
		$sujets = $this->termes_pactols_lies( $doc,
			isset( $d['pactols_subjects_items'] ) ? $d['pactols_subjects_items'] : array() );
		if ( ! empty( $sujets ) ) {
			$doc->add_raw_paragraph( Notice_Archeomed_Styles::de( 'mots_cles' ),
				$doc->plain( 'Mots-clés : ' )
					. implode( $doc->plain( ', ' ), $sujets ) );
		}

		// 6. Texte de la notice, un paragraphe par <p>, avec le bloc responsable
		// collé au point final du dernier paragraphe.
		//
		// Ou, si la correspondance des styles le demande, dans un paragraphe
		// à lui, sans parenthèses : c'est la forme que la documentation
		// Métopes donne au paragraphe d'autorités.
		$paragraphes = $doc->html_to_paragraphs( $d['texte_notice'] );
		$a_part      = Notice_Archeomed_Styles::responsabilites_a_part();
		$bloc_resp   = $this->responsables_rtf( $doc, $d, ! $a_part );
		if ( ! $a_part ) {
			if ( empty( $paragraphes ) ) {
				$paragraphes = array( $bloc_resp );
			} else {
				$last                 = count( $paragraphes ) - 1;
				$paragraphes[ $last ] = $paragraphes[ $last ] . $bloc_resp;
			}
		}
		foreach ( $paragraphes as $p ) {
			$doc->add_raw_paragraph( Notice_Archeomed_Styles::de( 'texte' ), $p );
		}
		if ( $a_part && '' !== $bloc_resp ) {
			$doc->add_raw_paragraph( Notice_Archeomed_Styles::de( 'responsabilites' ), $bloc_resp );
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
			$doc->add_paragraph( Notice_Archeomed_Styles::de( 'figure_debut' ),
				array( array( 'text' => self::figure_ouvrante() ) ) );
			// « Fig. 1 Vue générale » et non « Fig. 1 : Vue générale » : c'est
			// ainsi que la revue compose ses légendes.
			$item['titre'] = Notice_Archeomed_Controles::titre_sans_numero( $item['titre'], $item['rang'] );
			// L'appel et le signe qui le sépare du titre suivent les normes de
			// la revue : « Fig. 1 Vue », « Fig. 1 : Vue », « Figure 1. Vue ».
			$numero = Notice_Archeomed_Normes::numero_de_figure( $item['rang'] );
			$titre  = $numero . ( '' !== $item['titre'] ? Notice_Archeomed_Normes::apres_le_numero() . $item['titre'] : '' );
			// Le texte alternatif de l'auteur. Une notice d'avant ce champ n'en
			// a pas : l'image garde alors son titre en texte de remplacement.
			$alt = isset( $item['alt'] ) && is_scalar( $item['alt'] ) ? Notice_Archeomed_Controles::une_ligne( $item['alt'] ) : '';
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
				$doc->add_raw_paragraph( Notice_Archeomed_Styles::de( 'figure_image' ), $doc->image_liee(
					// Le document vit dans « style/ » : il remonte d'un cran
					// pour atteindre l'icono. Sans ce « ../ », le lien ne
					// résout nulle part et Word pose un cadre vide.
					'../' . $item['figure']['fichier'],
					$item['figure']['largeur'],
					$item['figure']['hauteur'],
					$item['figure']['dpi'],
					$titre,
					$alt
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
					$titre,
					$alt
				);
				if ( '' !== $dessin ) {
					$doc->add_raw_paragraph( Notice_Archeomed_Styles::de( 'figure_image' ), $dessin );
				}
			}
			// « Fig. 1 » porte « TEI_figure_num_inline » : la chaîne sait alors
			// où finit le numéro et où commence le titre, au lieu d'avoir à le
			// deviner d'une expression régulière sur le point ou l'espace.
			// Le titre prend les siècles comme la légende : « au XIIe siècle »
			// restait tel quel, sous une légende qui l'imprimait en petites
			// capitales.
			$titre_fig = Notice_Archeomed_Normes::apres_le_numero() . $item['titre'];
			$doc->add_raw_paragraph( Notice_Archeomed_Styles::de( 'figure_titre' ),
				$doc->char_run( Notice_Archeomed_Styles::de( 'figure_numero' ), $numero )
					. ( '' === $item['titre'] ? '' : ( method_exists( $doc, 'fragments' )
						? $doc->fragments( array( array( 'text' => $titre_fig ) ) )
						: $doc->plain( $titre_fig ) ) ) );
			if ( '' !== $item['legende'] ) {
				$doc->add_paragraph( Notice_Archeomed_Styles::de( 'figure_legende' ),
					array( array( 'text' => $item['legende'] ) ) );
			}
			if ( '' !== $item['credits'] ) {
				$doc->add_paragraph( Notice_Archeomed_Styles::de( 'figure_credits' ),
					array( array( 'text' => $item['credits'] ) ) );
			}
			// Une figure déposée sans un mot : le bloc reste, pour que la
			// numérotation tienne, et la rédaction lit ce qui manque dans le
			// style qui s'ôte d'un geste avant l'import.
			if ( ! empty( $item['autorisation'] ) ) {
				$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER,
					array( array( 'text' => 'Autorisation de reproduction jointe au dépôt : elle se télécharge depuis la fiche de la notice.' ) ) );
			}
			if ( '' === trim( $item['titre'] . $item['legende'] . $item['credits'] ) ) {
				$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER,
					array( array( 'text' => 'Titre, légende et crédits manquants : à demander à l’auteur.', 'b' => true ) ) );
			}
			$this->poser_les_textes_d_accessibilite( $doc, $item, $alt );
			$doc->add_paragraph( Notice_Archeomed_Styles::de( 'figure_fin' ),
				array( array( 'text' => self::figure_fermante() ) ) );
		}

		// Les illustrations d'une saisie ancienne, décrites en texte libre :
		// à reprendre par la rédaction, qui les lit ici telles quelles.
		if ( ! empty( $d['illustrations_libres'] ) && is_string( $d['illustrations_libres'] ) ) {
			$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER, array(
				array( 'text' => 'Illustrations décrites en texte libre (saisie ancienne) : ', 'b' => true ),
				array( 'text' => $d['illustrations_libres'] ),
			) );
		}

		// Les originaux déposés ailleurs, trop lourds pour le formulaire : un
		// renseignement pour la rédaction, qui s'ôte avec le reste.
		if ( ! empty( $d['originaux_lien'] ) ) {
			$doc->add_raw_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER,
				$doc->plain( 'Originaux des figures à télécharger : ' )
					. $doc->hyperlink( $d['originaux_lien'], $d['originaux_lien'] ) );
		}

		// 8. Commentaires de l'auteur, adressés à la rédaction : ils ne
		// s'impriment pas. En Normal, ils restaient dans le texte à verser
		// après le geste qui ôte le reste du bloc de la rédaction.
		if ( '' !== $d['commentaires'] ) {
			$doc->add_paragraph(
				Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER,
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
		// Les avis du dépôt, en tête de ce qui s'ôte : c'est la première
		// chose que la relecture a à regarder.
		// La référence du dépôt, seul identifiant stable de la notice : celle
		// que l'auteur et la rédaction se citeront.
		if ( ! empty( $d['reference'] ) ) {
			$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER,
				array( array( 'text' => "Référence du dépôt\u{00A0}: " . $d['reference'] ) ) );
		}
		// Les appels de note collés se signalent aussi pour les notices reçues
		// avant que le dépôt ne les relève : leur texte s'est perdu en silence.
		$avis = array_values( array_unique( array_merge(
			isset( $d['avis'] ) ? (array) $d['avis'] : array(),
			Notice_Archeomed_Controles::appels_de_note( isset( $d['texte_notice'] ) ? $d['texte_notice'] : '' ) ) ) );
		if ( ! empty( $avis ) ) {
			$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER, array( array( 'text' => 'À vérifier :', 'b' => true ) ) );
			foreach ( $avis as $un_avis ) {
				$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER, array( array( 'text' => '– ' . $un_avis ) ) );
			}
		}
		$contacts = $this->contacts_list( $d );
		if ( ! empty( $contacts ) ) {
			$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER, array( array( 'text' => 'Coordonnées des responsables :', 'b' => true ) ) );
			foreach ( $contacts as $infos ) {
				$doc->add_raw_paragraph(
					Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER,
					// En texte simple : le style d'autorité est déjà posé dans
					// la mention de responsabilité, et un bloc oublié au
					// ménage baliserait deux fois les mêmes personnes.
					$doc->plain( $infos['nom'] )
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
					// Le remplaçant que Pactols désigne, quand il y en a un :
					// c'est lui qu'on choisira à la place.
					$morts[] = $item['label'] . ( ! empty( $item['remplacant'] )
						? ' (remplacé par ' . Notice_Archeomed_Thesaurus::partie_ark( $item['remplacant'] ) . ')' : '' );
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
			// Le lieu-dit du titre et du renvoi passe par les normes comme le
			// texte : « l'enceinte du XIIe siècle » gardait son apostrophe
			// droite et son siècle en capitales. L'italique suit.
			return $doc->fragments( array( array(
				'text' => (string) $run['text'],
				'b'    => ! empty( $run['b'] ),
				'i'    => ! empty( $run['i'] ),
			) ) );
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
	private function responsables_rtf( $doc, $d, $entre_parentheses = true ) {
		$segments = array();

		$resp = self::nom_d_autorite( $d['resp_prenom'], $d['resp_nom'] );
		if ( '' !== $resp ) {
			$seg = $doc->plain( "Responsable de l'opération : " )
				. $doc->char_run( Notice_Archeomed_Styles::de( 'responsable' ), $resp, true );
			if ( '' !== $d['resp_inst'] ) {
				$seg .= $doc->plain( ', ' )
					. $doc->char_run( Notice_Archeomed_Styles::de( 'affiliation' ), $d['resp_inst'] );
			}
			$segments[] = $seg;
		}

		$coresp = self::nom_d_autorite( $d['coresp_prenom'], $d['coresp_nom'] );
		if ( '' !== $coresp ) {
			$seg = $doc->plain( "co-responsable de l'opération : " )
				. $doc->char_run( Notice_Archeomed_Styles::de( 'coresponsable' ), $coresp, true );
			if ( '' !== $d['coresp_inst'] ) {
				$seg .= $doc->plain( ', ' )
					. $doc->char_run( Notice_Archeomed_Styles::de( 'affiliation' ), $d['coresp_inst'] );
			}
			$segments[] = $seg;
		}

		$coauteur = self::nom_d_autorite( $d['coauteur_prenom'], $d['coauteur_nom'] );
		if ( '' !== $coauteur ) {
			$seg = $doc->plain( 'notice rédigée avec : ' )
				. $doc->char_run( Notice_Archeomed_Styles::de( 'coauteur' ), $coauteur, true );
			if ( '' !== $d['coauteur_inst'] ) {
				$seg .= $doc->plain( ', ' )
					. $doc->char_run( Notice_Archeomed_Styles::de( 'affiliation' ), $d['coauteur_inst'] );
			}
			$segments[] = $seg;
		}

		if ( empty( $segments ) ) {
			return '';
		}
		if ( ! $entre_parentheses ) {
			return implode( $doc->plain( ' ; ' ), $segments );
		}
		return $doc->plain( ' (' )
			. implode( $doc->plain( ' ; ' ), $segments )
			. $doc->plain( ')' );
	}

	/**
	 * Prénom et nom, les blancs du nom rendus insécables.
	 *
	 * La chaîne lit un nom de droite à gauche jusqu'à la première espace
	 * simple pour séparer le nom du prénom : « Jean Le Maho » donnerait
	 * « Maho », prénom « Jean Le ». Avec « Le Maho » soudé, le découpage
	 * tombe juste — c'est ce que la documentation Métopes demande pour les
	 * noms à particule ou doubles.
	 */
	private static function nom_d_autorite( $prenom, $nom ) {
		$nom = trim( (string) preg_replace( '/\s+/u', ' ', (string) $nom ) );
		return trim( trim( (string) $prenom ) . ' ' . str_replace( ' ', "\u{00A0}", $nom ) );
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
		// Une ligne par rôle, et non par nom : un responsable et un co-auteur
		// homonymes ne laissaient qu'une adresse.
		foreach ( $pairs as $pair ) {
			list( $nom, $mail, $cs ) = $pair;
			if ( '' !== $nom && '' !== $mail && is_email( $mail ) ) {
				$out[] = array( 'nom' => $nom, 'mail' => $mail, 'cs' => $cs );
			}
		}
		return $out;
	}

	/**
	 * Une requête plus lourde que « post_max_size » arrive vide : PHP jette
	 * les champs et les fichiers sans rien dire.
	 *
	 * « handle_submission » ne trouvait pas le bouton d'envoi et rendait la
	 * main : la page revenait avec un formulaire vierge, sans un mot, et la
	 * saisie était perdue. On reconnaît le cas — une requête POST qui avait
	 * un corps, dont PHP n'a rien gardé, adressée à la page du formulaire —
	 * et l'on dit ce qui s'est passé.
	 */
	public function refuser_l_envoi_trop_lourd() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}
		$longueur = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
		if ( $longueur <= 0 || ! empty( $_POST ) || ! empty( $_FILES ) ) {
			return;
		}
		if ( ! $this->page_porte_le_formulaire() ) {
			return;
		}
		error_log( 'Notice Archeomed: requête de ' . $longueur . ' octets vidée par post_max_size.' );
		$this->redirect_result( false, 'trop_lourd' );
	}

	/**
	 * Ramène chaque champ reçu à la forme que le formulaire envoie, avant
	 * toute lecture.
	 *
	 * Un champ simple forgé en tableau — « resp_email[] », « lieu_dit[] » —
	 * faisait tomber la requête sur une erreur fatale, ou s'enregistrait
	 * « Array » : il est ignoré, comme s'il manquait. Dans une liste, un
	 * élément qui n'est pas du texte devient vide.
	 *
	 * Les caractères de contrôle collés dans un champ d'une ligne — le saut
	 * de ligne de Word (U+000B) surtout — restaient dans le titre de la
	 * liste, la relecture et l'objet du courriel : ils deviennent une espace,
	 * et les autres s'ôtent. Les champs de plusieurs lignes gardent leurs
	 * retours à la ligne.
	 */
	private static function entrees_attendues() {
		$une_ligne = array(
			'departement', 'lieu_dit', 'annee', 'num_autorisation', 'id_patriarche',
			'resp_prenom', 'resp_nom', 'resp_inst', 'resp_email',
			'coresp_prenom', 'coresp_nom', 'coresp_inst', 'coresp_email',
			'coauteur_prenom', 'coauteur_nom', 'coauteur_inst', 'coauteur_email',
			'rapport_lien', 'originaux_lien',
		);
		$simples = array_merge( $une_ligne, array(
			'commentaires', 'texte_notice', 'pactols_periods', 'pactols_subjects', 'pactols_places',
			'rubrique_principale', 'rubrique_matiere', 'renvoi_1', 'renvoi_2', 'reprise_jeton',
			'notice_archeomed_nonce', 'na_website', 'na_curseur', 'na_preuve', 'cf-turnstile-response',
		) );
		foreach ( $simples as $champ ) {
			if ( isset( $_POST[ $champ ] ) && ! is_string( $_POST[ $champ ] ) ) {
				unset( $_POST[ $champ ] );
			}
		}
		// Les listes. « commune » et « organisme » ont été des champs
		// simples : une chaîne s'y lit encore.
		$listes = array(
			'nature' => true, 'commune' => true, 'commune_ark' => true, 'organisme' => true,
			'illus_titre' => true, 'illus_credits' => true, 'illus_alt' => true, 'illus_legende' => false, 'illus_description' => false,
		);
		foreach ( $listes as $champ => $ligne ) {
			if ( ! isset( $_POST[ $champ ] ) ) {
				continue;
			}
			if ( ! is_array( $_POST[ $champ ] ) ) {
				if ( ! is_string( $_POST[ $champ ] ) ) {
					unset( $_POST[ $champ ] );
				} elseif ( $ligne ) {
					$_POST[ $champ ] = self::sans_controles( $_POST[ $champ ], true );
				}
				continue;
			}
			foreach ( $_POST[ $champ ] as $cle => $valeur ) {
				$_POST[ $champ ][ $cle ] = is_string( $valeur ) ? self::sans_controles( $valeur, $ligne ) : '';
			}
		}
		foreach ( $une_ligne as $champ ) {
			if ( isset( $_POST[ $champ ] ) ) {
				$_POST[ $champ ] = self::sans_controles( $_POST[ $champ ], true );
			}
		}
		if ( isset( $_POST['commentaires'] ) ) {
			$_POST['commentaires'] = self::sans_controles( $_POST['commentaires'], false );
		}
	}

	/**
	 * Un texte sans caractère de contrôle. Sur une ligne, les sauts et les
	 * tabulations deviennent une espace ; sinon, ils restent.
	 */
	private static function sans_controles( $texte, $une_ligne ) {
		$texte  = (string) $texte;
		$propre = $une_ligne
			? str_replace( array( "\t", "\n", "\x0B", "\x0C", "\r" ), ' ', $texte )
			: str_replace( array( "\x0B", "\x0C" ), "\n", $texte );
		$propre = preg_replace( '/[\x{00}-\x{08}\x{0E}-\x{1F}\x{7F}\x{FFFE}\x{FFFF}]/u', '', $propre );
		// Un texte qui n'est pas de l'UTF-8 ne se lit pas au motif : on le
		// laisse aux tamis de WordPress plutôt que de le vider.
		return null === $propre ? $texte : $propre;
	}

	public function handle_submission() {
		if ( ! isset( $_POST['notice_archeomed_envoi'] ) ) {
			return;
		}
		// Un délai d'envoi forgé en tableau passait le contrôle des trois
		// secondes : c'est un robot, on refuse comme pour lui.
		if ( isset( $_POST['na_ts'] ) && ! is_string( $_POST['na_ts'] ) ) {
			error_log( 'Notice Archeomed: forged timestamp.' );
			$this->redirect_result( false, 'securite' );
		}
		self::entrees_attendues();
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
		// La matière ne vaut que pour la rubrique V : ailleurs, elle ne se
		// garde pas, même postée.
		$matiere = isset( $_POST['rubrique_matiere'] ) ? strtoupper( sanitize_key( wp_unslash( $_POST['rubrique_matiere'] ) ) ) : '';
		$d['rubrique_matiere'] = ( self::RUBRIQUE_ARTISANAT === $d['rubrique_principale'] && array_key_exists( $matiere, self::MATIERES ) ) ? $matiere : '';
		$text_fields = array(
			'departement', 'lieu_dit', 'annee', 'num_autorisation', 'id_patriarche',
			'resp_prenom', 'resp_nom', 'resp_inst',
			'coresp_prenom', 'coresp_nom', 'coresp_inst',
			'coauteur_prenom', 'coauteur_nom', 'coauteur_inst',
			'commentaires',
		);
		foreach ( $text_fields as $f ) {
			$d[ $f ] = isset( $_POST[ $f ] ) ? sanitize_textarea_field( self::chevrons_a_garder( wp_unslash( $_POST[ $f ] ) ) ) : '';
		}
		// Le lien du rapport passe par « esc_url_raw » et non par le tamis des
		// champs de texte : il finit dans un lien hypertexte du document, et
		// une adresse mal formée y ferait un lien qui ne mène nulle part. Les
		// schémas sont restreints à ce qu'un rapport peut porter.
		$d['rapport_lien'] = isset( $_POST['rapport_lien'] )
			? esc_url_raw( trim( wp_unslash( $_POST['rapport_lien'] ) ), array( 'http', 'https' ) ) : '';
		$d['rapport_lien'] = $this->limit_string( $d['rapport_lien'], 500, 'le lien du rapport' );
		// Le lien de téléchargement des originaux trop lourds pour le
		// formulaire : la rédaction le trouve dans le courriel et le document.
		$d['originaux_lien'] = isset( $_POST['originaux_lien'] )
			? esc_url_raw( trim( wp_unslash( $_POST['originaux_lien'] ) ), array( 'http', 'https' ) ) : '';
		$d['originaux_lien'] = $this->limit_string( $d['originaux_lien'], 500, 'le lien des originaux' );
		$d['resp_email'] = isset( $_POST['resp_email'] ) ? sanitize_email( wp_unslash( $_POST['resp_email'] ) ) : '';
		$d['coresp_email'] = isset( $_POST['coresp_email'] ) ? sanitize_email( wp_unslash( $_POST['coresp_email'] ) ) : '';
		$d['coauteur_email'] = isset( $_POST['coauteur_email'] ) ? sanitize_email( wp_unslash( $_POST['coauteur_email'] ) ) : '';
		$noms_des_champs = array(
			'departement' => 'la précision géographique', 'lieu_dit' => 'le lieu-dit', 'annee' => 'l’année',
			'num_autorisation' => 'le numéro d’autorisation', 'id_patriarche' => 'l’identifiant Patriarche',
			'resp_prenom' => 'le prénom du responsable', 'resp_nom' => 'le nom du responsable', 'resp_inst' => 'l’institution du responsable',
			'coresp_prenom' => 'le prénom du co-responsable', 'coresp_nom' => 'le nom du co-responsable', 'coresp_inst' => 'l’institution du co-responsable',
			'coauteur_prenom' => 'le prénom du co-auteur', 'coauteur_nom' => 'le nom du co-auteur', 'coauteur_inst' => 'l’institution du co-auteur',
			'commentaires' => 'le message à la rédaction',
		);
		foreach ( array( 'departement' => 120, 'lieu_dit' => 200, 'annee' => 20, 'num_autorisation' => 120, 'id_patriarche' => 120, 'resp_prenom' => 100, 'resp_nom' => 100, 'resp_inst' => 200, 'coresp_prenom' => 100, 'coresp_nom' => 100, 'coresp_inst' => 200, 'coauteur_prenom' => 100, 'coauteur_nom' => 100, 'coauteur_inst' => 200, 'commentaires' => 3000 ) as $field => $max ) {
			$d[ $field ] = $this->limit_string( $d[ $field ], $max, $noms_des_champs[ $field ] );
		}
		$d['departement'] = $this->sans_parentheses( $d['departement'] );
		$d['annee'] = self::annee_normalisee( $d['annee'] );
		// Un titre ne prend pas de point final : « Rue de Reviers. » donnait
		// un titre pointé, au document comme au renvoi.
		$d['lieu_dit'] = Notice_Archeomed_Controles::sans_point_final( $d['lieu_dit'] );
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
		$d['texte_notice'] = $this->texte_notice_pose( $raw_texte, 'le texte, balises comprises' );
		$manquants = $this->champs_manquants( $d );
		if ( ! empty( $manquants ) ) {
			error_log( 'Notice Archeomed: missing required fields: ' . implode( ', ', $manquants ) );
			// « notice_champ » porte le premier champ, comme avant ; la liste
			// entière part à côté, pour le récapitulatif.
			$autres = array_values( array_diff( $manquants, array( 'texte' ) ) );
			$this->redirect_result( false, array( 'texte' ) === $manquants ? 'texte' : 'champs',
				empty( $autres ) ? '' : $autres[0], '', $manquants );
		}
		if ( ! $this->check_send_limit( $d['resp_email'] ) ) {
			error_log( 'Notice Archeomed: send limit reached.' );
			$this->redirect_result( false, 'envois' );
		}
		$upload_error = '';
		$attachments = $this->handle_uploads( $upload_error );
		if ( '' !== $upload_error ) {
			error_log( 'Notice Archeomed: upload error: ' . $upload_error );
			$this->redirect_result( false, $this->raison_du_televersement( $upload_error ) );
		}
		$autorisations = $this->recevoir_les_autorisations( $upload_error );
		if ( '' !== $upload_error ) {
			$this->nettoyer( $attachments );
			error_log( 'Notice Archeomed: autorisation refusée : ' . $upload_error );
			$this->redirect_result( false, 'autorisation' );
		}
		// La définition réelle de chaque figure, lue dans l'en-tête du
		// fichier — rien n'est décodé, le dépôt n'attend pas —, puis les avis
		// que la rédaction lira avec la notice.
		$d = self::poser_les_pixels( $d, $attachments );
		$d['avis'] = Notice_Archeomed_Controles::avis( $d );
		$d['avis'] = array_merge( (array) $d['avis'], $this->avis_du_lien_repris(), $this->avis_des_coupes() );
		// La référence se tire avant le courriel et le Word : seul identifiant
		// stable d'une notice, elle ne voyageait dans aucune sortie, et la
		// rédaction lisait « remplace le dépôt BBEPY0 » sans avoir jamais vu
		// BBEPY0 nulle part.
		$reference      = strtoupper( wp_generate_password( 6, false, false ) );
		$d['reference'] = $reference;
		$notice = $this->build_notice( $d );

		// Les fichiers vivent désormais plus longtemps que la requête : on les
		// met à l'abri de la purge quotidienne dès maintenant. Le document
		// stylé se suit à part — c'est le seul qui repart aux auteurs — plutôt
		// que d'être repêché en fin de liste, ce qui tenait à l'ordre.
		$illustrations = $this->mettre_a_labri( $attachments );
		// Une par une, pour garder le rang de la figure en clef.
		foreach ( $autorisations as $rang => $fichier ) {
			$mise = $this->mettre_a_labri( array( $fichier ) );
			$autorisations[ $rang ] = ! empty( $mise ) ? $mise[0] : $fichier;
		}
		// La saisie est mise en réserve pour un mois, et son lien part avec la
		// copie de l'auteur : c'est en lisant sa notice qu'il voit ce qu'il
		// n'a pas vu en la saisissant. Le lien se calcule ici, dans la requête
		// de l'auteur — le planificateur, lui, n'a plus de page de référence.
		$d['correction_url'] = $this->lien_de_correction( $reference );
		// Le lien par lequel on vient d'arriver a fait son office. Le laisser
		// vivre, c'est permettre qu'une troisième notice vienne corriger la
		// première, et la rédaction en aurait trois sur les bras.
		$this->oublier_le_jeton_repris( $reference );

		// ── L'inscription d'abord, en envoi différé comme immédiat ─────────
		//
		// Les versions allégées et le Word se fabriquaient avant que la
		// notice ne fût inscrite. Imagick décode l'image entière : un TIFF de
		// vingt méga-octets pouvait épuiser la mémoire ou la durée de la
		// requête, et la saisie se perdait avec ses fichiers. Inscrite
		// d'abord, la notice ne craint plus que la requête meure : la file la
		// reprend et refait ce qui manque.
		//
		// L'envoi immédiat passait à côté de tout : ni liste, ni fascicule, ni
		// dossier, des originaux joints sans plafond de poids, effacés après
		// l'envoi, et une notice refusée gardée dans une réserve que rien
		// n'affichait. Il suit désormais le chemin du différé et n'avance que
		// l'heure du départ.
		$id = $this->file()->deposer( $d, $notice,
			array_merge( $illustrations, array_values( $autorisations ) ), '', $reference );
		if ( $id ) {
			// Le temps de la préparation, la file laisse la notice : une
			// relance qui la croiserait referait les versions allégées en
			// même temps que cette requête. Si la requête meurt, la marque
			// vieillit et la file reprend la notice d'elle-même.
			update_post_meta( $id, '_na_preparation', time() );
			// Les illustrations gagnent leur place définitive dès
			// maintenant, et la file retient ces chemins-là : c'est eux
			// que le courriel joindra, et eux que le dossier Métopes
			// trouvera même si le courriel ne part jamais.
			$gardees = $this->archiver_les_illustrations( $id, $illustrations );
			// Les autorisations suivent les figures dans le courriel, et
			// se retrouvent sur la fiche de la notice.
			$fichiers = array_merge( $gardees, $this->archiver_les_autorisations( $id, $autorisations ) );
			update_post_meta( $id, '_na_fichiers', $fichiers );
			$this->record_send( $d['resp_email'] );

			// Les figures que le document emportera. Elles se fabriquent avant
			// lui, puisqu'il les porte : le Word envoyé par courriel voyage
			// seul, sans dossier d'icono à côté de lui où aller chercher une
			// image.
			//
			// Ils vont au document et non à la saisie. La saisie se garde, et
			// tout ce qui la relit — le fascicule, le dossier, la page de
			// relecture — aurait hérité d'une figure faite pour un courriel,
			// avec son chemin sur le serveur. Le dossier en a posé une : une
			// image de mille pixels, dans le fichier, que la mise en page ne
			// pouvait remplacer.
			$apercus = $this->fabriquer_les_apercus( $gardees, $d );
			if ( ! empty( $apercus ) ) {
				update_post_meta( $id, '_na_apercus', $apercus );
			}
			$document = $this->fabriquer_le_document( $this->attacher_les_apercus( $d, $apercus ) );
			if ( '' !== $document ) {
				$fichiers[] = $document;
				update_post_meta( $id, '_na_fichiers', $fichiers );
				update_post_meta( $id, '_na_document', $document );
			}
			delete_post_meta( $id, '_na_preparation' );

			// ── Le dépôt, et rien de plus ─────────────────────────────────
			//
			// Tout ce qui est lent — la poignée de main SMTP, le transfert de
			// vingt méga-octets de pièces jointes, les accusés de réception —
			// sort de la requête de l'auteur. Elle rend la main tout de suite,
			// et le processus PHP se libère pour le déposant suivant.
			if ( $this->envoi_differe() ) {
				$this->file()->programmer( $id );
				$this->redirect_result( true, '', '', $reference );
			}
			// L'envoi immédiat : la même expédition, dans cette requête. Les
			// versions allégées viennent d'être tentées ; la file ne les
			// retente pas avant de partir. Un refus laisse la notice en file,
			// où la relance la reprend : elle est reçue, et l'auteur n'a rien
			// à refaire.
			update_post_meta( $id, '_na_apercus_tente', time() );
			$this->expedier_de_la_file( $id );
			$this->redirect_result( true, '', '', $reference );
		}
		// L'inscription a échoué — base de données indisponible. Plutôt que
		// de perdre la notice, on l'envoie sans dépôt.
		error_log( 'Notice Archeomed: dépôt en file impossible, envoi sans dépôt.' );
		$apercus  = $this->fabriquer_les_apercus( $illustrations, $d );
		$document = $this->fabriquer_le_document( $this->attacher_les_apercus( $d, $apercus ) );
		$produits = array_merge( $illustrations, array_values( $autorisations ) );
		if ( '' !== $document ) {
			$produits[] = $document;
		}

		// Sans dépôt, les aperçus n'ont plus d'usage : ils sont dans le
		// document déjà fabriqué, et aucune notice ne les retrouverait. Les
		// laisser, c'était les oublier dans le dossier de dépôt.
		$this->effacer_des_apercus( $apercus );

		$envoyee = $this->expedier( $d, $notice, $produits, $document );
		if ( $envoyee ) {
			$this->record_send( $d['resp_email'] );
			$this->nettoyer( $produits );
			$this->redirect_result( true, '', '', $reference );
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
		add_action( self::HOOK_APERCUS,
			array( $this, 'fabriquer_les_apercus_en_tache' ), 10, 1 );
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
		&$restees = array(), $avertissement = '' ) {
		$pourquoi = '';
		$restees  = array();
		$wrap_open  = '<html><body><div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#222;">';
		$wrap_close = '</div></body></html>';

		$resp_complet = trim( $d['resp_prenom'] . ' ' . $d['resp_nom'] );
		// Le lieu-dit et l'année : deux notices de Caen étaient indiscernables
		// dans une boîte triée sur les objets.
		$lieux   = $this->lieux_de( $d );
		$subject = 'Notice Archéomed - ' . ( isset( $lieux[0] ) ? $lieux[0]['nom'] : $d['commune'] )
			. ' (' . $d['departement'] . ')'
			. ( '' !== trim( (string) $d['lieu_dit'] ) ? ', ' . trim( (string) $d['lieu_dit'] ) : '' )
			. ( '' !== trim( (string) $d['annee'] ) ? ' — ' . trim( (string) $d['annee'] ) : '' );
		// Une boîte pleine se trie sur les objets : le bandeau du corps ne se
		// voit qu'une fois le message ouvert, et deux notices de la même
		// commune se ressemblent trop pour qu'on ouvre les deux.
		if ( ! empty( $d['remplace'] ) ) {
			$subject = '[CORRECTION de ' . $d['remplace'] . '] ' . $subject;
		}
		// La référence, que la correction citera : « [CORRECTION de BBEPY0] »
		// ne disait rien à qui n'avait jamais lu BBEPY0 nulle part.
		$reference = ! empty( $d['reference'] ) ? (string) $d['reference']
			: ( $id ? (string) get_post_meta( (int) $id, '_na_reference', true ) : '' );
		if ( '' !== $reference ) {
			$subject .= ' — réf. ' . $reference;
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
		// une base indisponible au moment du dépôt —, rien n'est rangé nulle
		// part : on joint tout, et un refus garde la notice de côté plutôt que
		// d'en perdre une figure.
		$joindre = $existants;
		$legeres = array();
		$copies  = array();
		if ( $id ) {
			$joindre = $this->pieces_du_courriel( $id, $d, $existants, $document,
				strlen( $notice ), $restees, $legeres, $copies );
		}
		$corps = $notice;
		if ( ! empty( $legeres ) || ! empty( $restees ) ) {
			$corps = $this->avis_des_illustrations( count( $legeres ), $restees, $id ) . $notice;
		}
		// Une correction qui ne reprend pas toutes les figures de la notice
		// qu'elle remplace : la rédaction doit savoir, avant de supprimer
		// celle-ci, que ses originaux sont encore là.
		$figures_restees = $this->figures_de_la_remplacee( $d );
		if ( '' !== $figures_restees ) {
			$avertissement .= '<div style="background:#fdf6e3;border:2px solid #8a6d3b;padding:14px 18px;margin:0 0 18px;">'
				. esc_html( $figures_restees ) . '</div>';
		}
		// Ce que la file a constaté avant l'envoi — un Word qui n'a pas pu
		// être refait —, pour la rédaction seule.
		$corps = $avertissement . $corps;
		// Un seul envoi pour toute la rédaction : les pièces jointes pèsent
		// jusqu'à vingt méga-octets, et les répéter par destinataire ferait
		// payer la liste au poids.
		$sent = wp_mail( $pour_la_redaction, $subject, $wrap_open . $corps . $wrap_close,
			$headers, $joindre );
		// Les copies nommées des versions allégées n'ont servi qu'à ce
		// courriel : elles partent, qu'il soit parti ou non. Si la requête
		// meurt avant, la purge des fichiers temporaires les rattrape.
		$this->effacer_les_copies( $copies );
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
			. ( '' !== $reference ? '<p>Votre dépôt porte la référence <strong>' . esc_html( $reference )
				. '</strong>&nbsp;: citez-la si vous écrivez à la rédaction à son sujet.</p>' : '' )
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

	/** Les tailles de l'essai de poids, en méga-octets décimaux. */
	const TAILLES_ESSAI = array( 6, 9, 12, 20 );

	/**
	 * Un seul message vers l'adresse d'essai, portant une pièce jointe de la
	 * taille voulue, et ce que le serveur en a dit.
	 *
	 * Le diagnostic « étape par étape » enchaîne cinq ou six messages en
	 * quelques secondes : il ne distingue pas une limite de taille d'une
	 * limite de cadence. Celui-ci n'envoie qu'un message, et sa seule
	 * variable est le poids. Deux ou trois essais bornent la limite, et le
	 * chiffre se donne tel quel à l'hébergeur.
	 *
	 * La pièce est un texte lisible : un fichier d'octets au hasard, ou une
	 * fausse image, se ferait arrêter par un antivirus pour une autre raison
	 * que son poids, et l'essai mentirait.
	 */
	public function essayer_le_poids( $vers, $mo ) {
		if ( ! is_email( $vers ) ) {
			return array( 'ok' => false, 'message' => 'Adresse d’essai non valide.' );
		}
		$mo      = in_array( (int) $mo, self::TAILLES_ESSAI, true ) ? (int) $mo : self::TAILLES_ESSAI[0];
		$octets  = $mo * 1000 * 1000;
		$dossier = trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp';
		wp_mkdir_p( $dossier );
		$fichier = $dossier . '/notice-archeomed-essai-de-poids-' . $mo . '-Mo.txt';
		$ligne   = "Essai de poids du formulaire des notices : ce fichier ne sert qu'à mesurer ce que le serveur de courriel accepte.\n";
		$flux    = @fopen( $fichier, 'wb' );
		if ( ! $flux ) {
			return array( 'ok' => false, 'message' => 'Le fichier d’essai n’a pas pu être écrit.' );
		}
		$bloc  = str_repeat( $ligne, (int) ceil( 64 * KB_IN_BYTES / strlen( $ligne ) ) );
		$ecrit = 0;
		while ( $ecrit < $octets ) {
			$morceau = substr( $bloc, 0, min( strlen( $bloc ), $octets - $ecrit ) );
			fwrite( $flux, $morceau );
			$ecrit += strlen( $morceau );
		}
		fclose( $flux );

		$encode = (int) ceil( $octets * 4 / 3 * 78 / 76 );
		$this->derniere_erreur_mail = '';
		$parti = wp_mail( $vers, '[ESSAI DE POIDS] ' . $mo . ' Mo de pièce jointe',
			'<html><body><p>Essai de poids du formulaire des notices : ce message porte une pièce '
				. 'jointe de ' . $mo . ' Mo, soit environ ' . number_format_i18n( $encode / 1000 / 1000, 1 )
				. ' Mo une fois encodée pour le courriel. S’il vous parvient, le serveur l’a accepté.</p></body></html>',
			array( 'Content-Type: text/html; charset=UTF-8' ), array( $fichier ) );
		@unlink( $fichier );

		$resultat = array(
			'ok'      => (bool) $parti,
			'mo'      => $mo,
			'quand'   => current_time( 'mysql' ),
			'message' => $parti
				? 'Accepté : ' . $mo . ' Mo de pièce jointe, environ '
					. number_format_i18n( $encode / 1000 / 1000, 1 ) . ' Mo une fois encodés. '
					. 'Vérifiez qu’il arrive : le serveur de réception a sa propre limite, et peut refuser plus loin.'
				: 'Refusé : ' . $mo . ' Mo de pièce jointe, environ '
					. number_format_i18n( $encode / 1000 / 1000, 1 ) . ' Mo une fois encodés — '
					. ( '' !== $this->derniere_erreur_mail ? $this->derniere_erreur_mail : 'aucune raison donnée' ),
		);
		$historique = get_option( 'na_essais_de_poids', array() );
		$historique = is_array( $historique ) ? $historique : array();
		$historique[ $mo ] = $resultat;
		ksort( $historique );
		update_option( 'na_essais_de_poids', $historique, false );
		return $resultat;
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
		// Les pièces que l'envoi véritable joindrait — versions allégées
		// comprises —, ajoutées une à une. Le diagnostic envoyait les
		// originaux : il voyait refuser deux images de 6 Mo quand l'envoi,
		// lui, partait avec deux aperçus de 150 Ko, et désignait un problème
		// qui n'existait pas. Rien n'est fabriqué ici : la composition ne
		// touche pas à la notice.
		$restees  = array();
		$legeres  = array();
		$copies   = array();
		$document = (string) get_post_meta( (int) $id, '_na_document', true );
		$pieces   = $this->pieces_du_courriel( $id, $d, $produits, $document, strlen( $notice ),
			$restees, $legeres, $copies );
		$numero = 0;
		foreach ( $pieces as $nom => $fichier ) {
			++$numero;
			$out[] = $this->poster_brut(
				'pièce jointe ' . $numero . ' : ' . ( is_string( $nom ) ? $nom : basename( $fichier ) )
					. ' (' . size_format( filesize( $fichier ) ) . ')',
				$vers, $sujet, $corps,
				'' !== $reply ? array_merge( $base, array( $reply ) ) : $base,
				array_slice( $pieces, 0, $numero, true ) );
		}
		$this->effacer_les_copies( $copies );
		if ( empty( $pieces ) ) {
			$out[] = array( 'ok' => true, 'etape' => 'pièces jointes',
				'message' => 'aucune — rien à joindre pour cette notice' );
		}
		if ( ! empty( $restees ) ) {
			$out[] = array( 'ok' => true, 'etape' => 'ce qui resterait sur le site',
				'message' => count( $restees ) . ' illustration(s), au-delà de '
					. (int) Notice_Archeomed_Settings::get( 'poids_courriel' ) . ' Mo : le courriel les nommerait.' );
		}
		if ( $this->apercus_a_fabriquer( $id ) ) {
			$out[] = array( 'ok' => true, 'etape' => 'versions allégées',
				'message' => 'certaines illustrations n’en ont pas encore : l’envoi les fera fabriquer d’abord, '
					. 'et les joindra à la place des originaux.' );
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

	/** Le nom lisible d'une pièce jointe : « Caen_Chateau_Fig_2.tif ». */
	private function nom_de_piece( $d, $rang, $fichier, $suffixe = '' ) {
		return Notice_Archeomed_Nommage::assainir(
			( isset( $d['commune'] ) ? $d['commune'] : '' ) . '_' . ( isset( $d['lieu_dit'] ) ? $d['lieu_dit'] : '' ) . '_Fig_' . (int) $rang )
			. $suffixe . '.' . strtolower( pathinfo( $fichier, PATHINFO_EXTENSION ) );
	}

	/**
	 * Les pièces du courriel de la rédaction : le document, puis chaque
	 * illustration en version allégée — l'original seulement quand il n'y en
	 * a pas, et s'il tient sous le poids réglé.
	 *
	 * Les originaux partaient en pièces jointes, jusqu'à vingt méga-octets par
	 * notice. Le serveur de la revue refuse tout message de plus de dix
	 * environ : une notice à deux figures ne partait jamais. Et même sans
	 * cette limite, trois fois vingt méga-octets par notice, trois cents
	 * notices par an, c'était charger des boîtes pour des fichiers qui sont
	 * déjà sur le site et dans le dossier Métopes, d'où part la mise en page. La version
	 * allégée — mille pixels, la même que celle du document — suffit à lire.
	 *
	 * Les versions allégées se fabriquent au dépôt, ou dans une tâche à part
	 * pour les notices plus anciennes : jamais ici, où l'on ne fait que
	 * composer. Le diagnostic peut donc appeler cette fonction sans rien
	 * changer à la notice.
	 *
	 * Chaque figure compte une fois, dans son ordre : sa version allégée si
	 * elle en a une, l'original sinon. Si elle ne tient pas sous le poids
	 * réglé, c'est l'original que « $restees » nomme — lui est sur le site,
	 * non la copie faite pour le courriel. « $legeres » dit les versions
	 * allégées effectivement jointes ; « $copies », toutes celles qu'on a
	 * faites, pour les effacer après l'envoi.
	 */
	private function pieces_du_courriel( $id, $d, $existants, $document, $poids_du_corps,
		&$restees, &$legeres, &$copies ) {
		$restees   = array();
		$legeres   = array();
		$copies    = array();
		$originaux = array_values( array_filter(
			(array) get_post_meta( (int) $id, '_na_illustrations', true ), 'is_string' ) );
		$apercus   = $this->apercus_de( $id, $d );
		$budget    = $this->budget_du_courriel( $poids_du_corps );
		$joindre   = array();
		$cumul     = 0;

		if ( '' !== $document && in_array( $document, $existants, true ) ) {
			$joindre[] = $document;
			$cumul    += $this->poids_encode( $document );
		}

		$dossier = '';
		foreach ( $originaux as $i => $original ) {
			$rang   = $i + 1;
			$piece  = '';
			$legere = false;
			if ( ! empty( $apercus[ $rang ]['apercu'] ) && is_readable( $apercus[ $rang ]['apercu'] ) ) {
				if ( '' === $dossier ) {
					$dossier = trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp/courriel-'
						. wp_generate_password( 8, false, false );
					wp_mkdir_p( $dossier );
				}
				$copie = $dossier . '/' . Notice_Archeomed_Nommage::assainir(
					$d['commune'] . '_' . $d['lieu_dit'] . '_Fig_' . $rang ) . '_apercu.jpg';
				if ( @copy( $apercus[ $rang ]['apercu'], $copie ) ) {
					$copies[] = $copie;
					$piece    = $copie;
					$legere   = true;
				}
			}
			if ( '' === $piece && in_array( $original, $existants, true ) ) {
				$piece = $original;
			}
			if ( '' === $piece ) {
				continue;
			}
			$poids = $this->poids_encode( $piece );
			if ( $cumul + $poids <= $budget ) {
				// Un original part sous le nom de sa figure, comme la version
				// allégée : son nom sur le serveur est tiré au sort, et ne
				// disait ni de quelle image il s'agit ni où elle va.
				if ( $legere ) {
					$joindre[] = $piece;
					$legeres[] = $piece;
				} else {
					$joindre[ $this->nom_de_piece( $d, $rang, $piece ) ] = $piece;
				}
				$cumul += $poids;
			} else {
				$restees[] = $original;
			}
		}
		// Ce qui est dans la liste d'envoi sans être ni le document ni une
		// illustration connue — une notice très ancienne — suit la même règle.
		foreach ( $existants as $fichier ) {
			if ( $fichier === $document || in_array( $fichier, $originaux, true ) ) {
				continue;
			}
			$poids = $this->poids_encode( $fichier );
			if ( $cumul + $poids <= $budget ) {
				// Une autorisation de reproduction part sous le nom de sa figure.
				if ( preg_match( '/autorisation-fig-(\d+)-/', basename( $fichier ), $m ) ) {
					$joindre[ $this->nom_de_piece( $d, (int) $m[1], $fichier, '_autorisation' ) ] = $fichier;
				} else {
					$joindre[] = $fichier;
				}
				$cumul += $poids;
			} else {
				$restees[] = $fichier;
			}
		}
		// Toutes les copies ont échoué : le dossier ne doit pas rester vide.
		if ( '' !== $dossier && empty( $copies ) ) {
			@rmdir( $dossier );
		}
		return $joindre;
	}

	/**
	 * Ce que les pièces jointes peuvent peser, une fois encodées, sous le
	 * poids réglé.
	 *
	 * En méga-octets décimaux : le binaire en faisait 10 485 760 octets pour
	 * « 10 Mo », au-dessus des 10 240 000 de Postfix — un message passait le
	 * compte et se faisait refuser quand même. Le corps et les en-têtes
	 * prennent le reste.
	 */
	private function budget_du_courriel( $poids_du_corps ) {
		return (int) Notice_Archeomed_Settings::get( 'poids_courriel' ) * 1000 * 1000
			- 3 * $poids_du_corps - 64 * KB_IN_BYTES;
	}

	/**
	 * Le poids d'une pièce jointe telle qu'elle voyage : encodée en base 64,
	 * un tiers de plus, avec une fin de ligne tous les soixante-seize
	 * caractères.
	 */
	private function poids_encode( $fichier ) {
		return (int) ceil( filesize( $fichier ) * 4 / 3 * 78 / 76 );
	}

	/** Le crochet de la tâche qui fabrique les versions allégées manquantes. */
	const HOOK_APERCUS = 'na_fabriquer_les_apercus';

	/**
	 * Une notice a-t-elle des illustrations sans version allégée, qu'une
	 * tâche pourrait fabriquer ?
	 *
	 * Une fois seulement : la marque se pose avant le travail, si bien qu'une
	 * tâche coupée par l'hébergement au milieu d'un gros TIFF ne recommence
	 * pas à chaque envoi. La notice part alors avec ce qu'elle a.
	 */
	private function apercus_a_fabriquer( $id ) {
		if ( ! class_exists( 'Imagick' ) || get_post_meta( (int) $id, '_na_apercus_tente', true ) ) {
			return false;
		}
		$d = get_post_meta( (int) $id, '_na_donnees', true );
		if ( ! is_array( $d ) || empty( $d['illustrations'] ) ) {
			return false;
		}
		$apercus   = $this->apercus_de( $id, $d );
		$originaux = array_values( array_filter(
			(array) get_post_meta( (int) $id, '_na_illustrations', true ), 'is_string' ) );
		foreach ( (array) $d['illustrations'] as $item ) {
			$rang = isset( $item['rang'] ) ? (int) $item['rang'] : 0;
			if ( $rang >= 1 && isset( $originaux[ $rang - 1 ] ) && file_exists( $originaux[ $rang - 1 ] )
				&& ( empty( $apercus[ $rang ]['apercu'] ) || ! is_readable( $apercus[ $rang ]['apercu'] ) ) ) {
				return true;
			}
		}
		return false;
	}

	/** Inscrit la tâche des versions allégées d'une notice, si elle ne l'est pas. */
	private function programmer_les_apercus( $id ) {
		if ( ! wp_next_scheduled( self::HOOK_APERCUS, array( (int) $id ) ) ) {
			wp_schedule_single_event( time(), self::HOOK_APERCUS, array( (int) $id ) );
		}
	}

	/**
	 * Ce que le planificateur appelle : les versions allégées qui manquent à
	 * une notice, puis son envoi.
	 *
	 * Seuls les rangs qui n'en ont pas : refaire les autres laisserait les
	 * anciennes versions sur le serveur, que plus rien ne désigne.
	 */
	public function fabriquer_les_apercus_en_tache( $id ) {
		$id = (int) $id;
		update_post_meta( $id, '_na_apercus_tente', time() );
		$d = get_post_meta( $id, '_na_donnees', true );
		if ( is_array( $d ) && ! empty( $d ) ) {
			$originaux = array_values( array_filter(
				(array) get_post_meta( $id, '_na_illustrations', true ), 'is_string' ) );
			$apercus   = $this->apercus_de( $id, $d );
			$a_faire   = array();
			foreach ( $originaux as $i => $original ) {
				$a_faire[ $i ] = ( empty( $apercus[ $i + 1 ]['apercu'] )
					|| ! is_readable( $apercus[ $i + 1 ]['apercu'] ) ) ? $original : '';
			}
			$neufs = $this->fabriquer_les_apercus( $a_faire, $d );
			if ( ! empty( $neufs ) ) {
				update_post_meta( $id, '_na_apercus', $neufs + $apercus );
			}
		}
		$this->file()->programmer( $id );
	}

	/** Efface les copies faites pour un courriel, et leur dossier. */
	private function effacer_les_copies( $copies ) {
		$dossiers = array();
		foreach ( (array) $copies as $copie ) {
			if ( is_string( $copie ) && false !== strpos( $copie, 'notice-archeomed-tmp/courriel-' ) ) {
				@unlink( $copie );
				$dossiers[ dirname( $copie ) ] = true;
			}
		}
		foreach ( array_keys( $dossiers ) as $dossier ) {
			@rmdir( $dossier );
		}
	}

	/**
	 * L'encadré qui ouvre le courriel de la rédaction : ce qui est joint, en
	 * quelle définition, et où sont les originaux.
	 */
	private function avis_des_illustrations( $legeres, $restees, $id ) {
		$lien = admin_url( 'post.php?post=' . (int) $id . '&action=edit' );
		$avis = '';
		if ( $legeres > 0 ) {
			$largeur = number_format_i18n( Notice_Archeomed_Paquet::LARGEUR_APERCU );
			$avis   .= '<div style="background:#eef4f9;border:1px solid #72aee6;padding:12px 16px;margin:0 0 14px;">'
				. esc_html( sprintf( _n( '%1$d illustration est jointe en version allégée (au plus %2$s pixels de large), pour la lecture.',
					'%1$d illustrations sont jointes en version allégée (au plus %2$s pixels de large), pour la lecture.',
					$legeres, 'notice-archeomed' ), $legeres, $largeur ) )
				. ' Les originaux, en pleine définition, sont conservés sur le site — '
				. '<a href="' . esc_url( $lien ) . '">fiche de la notice</a> — et figurent dans le '
				. 'dossier Métopes de la rubrique.</div>';
		}
		if ( ! empty( $restees ) ) {
			$avis .= $this->avis_des_pieces_restees( $restees, $id );
		}
		return $avis;
	}

	/**
	 * L'encadré qui ouvre le courriel quand des illustrations sont restées
	 * sur le site : lesquelles, combien elles pèsent, et où les prendre.
	 */
	private function avis_des_pieces_restees( $restees, $id ) {
		// Chaque pièce se nomme par sa figure : le nom du fichier sur le
		// serveur est une suite de caractères tirée au sort, qui ne dit à la
		// rédaction ni de quelle image il s'agit ni où elle va.
		$originaux = array_values( array_filter(
			(array) get_post_meta( (int) $id, '_na_illustrations', true ), 'is_string' ) );
		$saisie    = get_post_meta( (int) $id, '_na_donnees', true );
		$titres    = array();
		if ( is_array( $saisie ) && ! empty( $saisie['illustrations'] ) ) {
			foreach ( (array) $saisie['illustrations'] as $item ) {
				if ( isset( $item['rang'], $item['titre'] ) ) {
					$titres[ (int) $item['rang'] ] = (string) $item['titre'];
				}
			}
		}
		$lignes = array();
		foreach ( $restees as $fichier ) {
			$rang = array_search( $fichier, $originaux, true );
			if ( false === $rang ) {
				$nom = basename( $fichier );
			} else {
				$nom = Notice_Archeomed_Normes::numero_de_figure( $rang + 1 )
					. ( ! empty( $titres[ $rang + 1 ] ) ? ' — ' . $titres[ $rang + 1 ] : '' );
			}
			$lignes[] = '<li>' . esc_html( $nom )
				. ( file_exists( $fichier ) ? ' (' . esc_html( size_format( filesize( $fichier ) ) ) . ')' : '' )
				. '</li>';
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

	/**
	 * Ce que le planificateur appelle : une notice inscrite, à expédier.
	 *
	 * Une notice dont des illustrations n'ont pas encore de version allégée
	 * ne part pas tout de suite : une tâche à part les fabrique d'abord, puis
	 * relance l'envoi. Les fabriquer ici, c'était convertir des TIFF de vingt
	 * méga-octets dans la requête qui envoie, sur un hébergement qui coupe à
	 * trente secondes : la requête mourait après avoir pris la notice et
	 * avant d'avoir rien noté, et l'essai suivant recommençait à l'identique.
	 *
	 * Rend l'issue en un mot — « partie », « refusee », « echec »,
	 * « en_preparation » ou « occupee » — pour le bouton « Relancer l'envoi »,
	 * qui disait « refusée » de tout ce qui n'était pas parti. Le
	 * planificateur, lui, n'écoute pas la réponse.
	 */
	public function expedier_de_la_file( $id ) {
		$id = (int) $id;
		if ( ! $id ) {
			return '';
		}
		// La requête de l'auteur prépare encore la notice — versions
		// allégées, Word. Morte en route, sa marque vieillit et l'on reprend.
		$preparee = (int) get_post_meta( $id, '_na_preparation', true );
		if ( $preparee > time() - Notice_Archeomed_File::ABANDON ) {
			return 'en_preparation';
		}
		if ( $this->apercus_a_fabriquer( $id ) ) {
			$this->programmer_les_apercus( $id );
			return 'en_preparation';
		}
		if ( ! $this->file()->prendre( $id ) ) {
			return 'occupee';
		}
		$d        = get_post_meta( $id, '_na_donnees', true );
		$notice   = (string) get_post_meta( $id, '_na_notice', true );
		$produits = (array) get_post_meta( $id, '_na_fichiers', true );
		$document = (string) get_post_meta( $id, '_na_document', true );
		if ( ! is_array( $d ) || empty( $d ) ) {
			$this->file()->marquer( $id, 'echec', 'Notice illisible en réserve.' );
			return 'echec';
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

		// Le Word qui a disparu du répertoire temporaire — la purge d'un mois,
		// un redémarrage — se refait à partir de la saisie. S'il ne se refait
		// pas, la rédaction le lit en tête du courriel et dans la liste : la
		// notice partait sans document, et rien ne le disait.
		$sans_document = false;
		if ( '' === $document || ! file_exists( $document ) ) {
			$perdu    = $document;
			$document = $this->refaire_le_document( $id );
			$produits = array_values( array_filter( $produits, function ( $fichier ) use ( $perdu ) {
				return '' === $perdu || $fichier !== $perdu;
			} ) );
			if ( '' !== $document ) {
				$produits[] = $document;
			} else {
				$sans_document = true;
			}
			update_post_meta( $id, '_na_fichiers', $produits );
		}
		$avertissement = '';
		if ( $sans_document ) {
			$fiche = admin_url( 'post.php?post=' . $id . '&action=edit' );
			$avertissement = '<div style="background:#fdf6e3;border:2px solid #8a6d3b;padding:14px 18px;margin:0 0 18px;">'
				. '<strong>Le document Word n’est pas joint à ce courriel</strong>&nbsp;: il n’a pas pu être '
				. 'refait à partir de la saisie, qui reste entière sur le site. Fiche de la notice&nbsp;: '
				. '<a href="' . esc_url( $fiche ) . '">' . esc_html( $fiche ) . '</a>.</div>';
		}

		$pourquoi = '';
		$restees  = array();
		if ( $this->expedier( $d, $notice, $produits, $document, $pourquoi, $id, $restees, $avertissement ) ) {
			// Partie, mais pas entière : la liste le dit, pour qu'on ne croie
			// pas la rédaction en possession de toutes les figures.
			$note = empty( $restees ) ? '' : sprintf(
				_n( 'Partie sans %d illustration, trop lourde pour un courriel ; elle reste sur le site.',
					'Partie sans %d illustrations, trop lourdes pour un courriel ; elles restent sur le site.',
					count( $restees ), 'notice-archeomed' ), count( $restees ) );
			if ( $sans_document ) {
				$note = trim( 'Partie sans le document Word, qui n’a pas pu être refait. ' . $note );
			}
			$this->file()->marquer( $id, 'envoyee', $note );
			// Les illustrations sont déjà rangées depuis le dépôt : il ne
			// reste qu'à effacer le document, qui se refabrique à la demande
			// et n'a aucune raison d'encombrer le répertoire temporaire.
			if ( '' !== $document && file_exists( $document ) ) {
				@unlink( $document );
				delete_post_meta( $id, '_na_document' );
			}
			return 'partie';
		}
		$this->file()->compter_un_essai( $id, $pourquoi );
		return 'refusee';
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
			// Le nom seul, jamais le chemin : le dossier s'appelle lui-même
			// « notice-archeomed-tmp ». Le remplacement portait sur le chemin
			// entier, visait un dossier « notice-archeomed-garde-tmp » qui
			// n'existe pas, et le renommage échouait sans bruit — la purge
			// d'un jour emportait ce qu'on croyait gardé un mois.
			$nom = basename( $file );
			if ( ! file_exists( $file ) || 0 !== strpos( $nom, 'notice-archeomed-' ) ) {
				continue;
			}
			if ( 0 === strpos( $nom, 'notice-archeomed-garde-' ) ) {
				$gardes[] = $file;
				continue;
			}
			$garde = dirname( $file ) . '/' . substr_replace( $nom, 'notice-archeomed-garde-', 0, strlen( 'notice-archeomed-' ) );
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
		// La même porte sert l'autorisation de reproduction d'une figure,
		// rangée par le numéro de la figure et non par sa position.
		$autorisation = ! empty( $_GET['autorisation'] );
		check_admin_referer( 'na_illustration_' . $id . '_' . $rang . ( $autorisation ? '_autorisation' : '' ) );
		$gardees = (array) get_post_meta( $id, $autorisation ? '_na_autorisations' : '_na_illustrations', true );
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
		$this->effacer_dans_le_depot( (array) get_post_meta( $id, '_na_autorisations', true ) );
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
				// Un ARK forgé ne part pas interroger l'API ; un « http:// »
				// déjà en base s'y présente en https, la seule forme résolue.
				$ark = Notice_Archeomed_Thesaurus::ark_propre( isset( $item['ark'] ) ? $item['ark'] : '' );
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
		return array_keys( array_diff_key( $arks, self::termes_lus( $termes ) ) );
	}

	/**
	 * Les termes vraiment lus : ceux qui ont leur chaîne ascendante.
	 *
	 * Une expansion tombée laissait un concept sans chaîne, tenu pour résolu
	 * à demeure : le bloc d'index manquait, et rien ne le comptait parmi les
	 * termes non lus. Les notices déjà touchées se relisent ainsi d'elles-mêmes.
	 */
	private static function termes_lus( $termes ) {
		return array_filter( (array) $termes, function ( $terme ) {
			return is_array( $terme ) && ! empty( $terme['chemin'] );
		} );
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
		$termes     = array_intersect_key( self::termes_lus( $deja ), $voulus );
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
			// Une erreur levée par la lecture d'un terme sortait de la tâche
			// entière : aucun essai ne se comptait, la notice restait due, et
			// le même terme arrêtait la file à chaque passage. Elle ne coûte
			// plus que ce terme, compté comme manquant.
			try {
				$concept = Notice_Archeomed_Thesaurus::resoudre( $ark, $quoi['id'],
					$quoi['theso'], $echeance );
			} catch ( Throwable $e ) {
				error_log( 'Notice Archeomed: Pactols ' . $ark . ' : ' . $e->getMessage() );
				$concept = null;
			}
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
		wp_unschedule_hook( self::HOOK_APERCUS );
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
				$ark = Notice_Archeomed_Thesaurus::ark_propre( isset( $item['ark'] ) ? $item['ark'] : '' );
				if ( '' === $ark || empty( $termes[ $ark ] ) ) {
					continue;
				}
				if ( ! empty( $termes[ $ark ]['prefLabel'] ) ) {
					$d[ $clef ][ $i ]['prefLabel'] = $termes[ $ark ]['prefLabel'];
				}
				$d[ $clef ][ $i ]['deprecie'] = ! empty( $termes[ $ark ]['deprecie'] );
				if ( ! empty( $termes[ $ark ]['remplacant'] ) ) {
					$d[ $clef ][ $i ]['remplacant'] = (string) $termes[ $ark ]['remplacant'];
				}
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
	 * Les illustrations d'une saisie, en figures que le document sait lire.
	 *
	 * Une saisie dont les illustrations étaient une chaîne — l'ancien champ
	 * libre commun — arrêtait net le fascicule de toute sa rubrique
	 * (« Cannot use string offset »). Ce texte n'est pas perdu : il passe
	 * en « illustrations_libres », que le document donne à la rédaction
	 * tel quel, et les figures ne gardent que ce qui en est une.
	 */
	private static function figures_lisibles( $d ) {
		if ( ! isset( $d['illustrations'] ) || ( is_array( $d['illustrations'] )
			&& count( array_filter( $d['illustrations'], 'is_array' ) ) === count( $d['illustrations'] ) ) ) {
			return $d;
		}
		$libres  = array();
		$figures = array();
		foreach ( is_array( $d['illustrations'] ) ? $d['illustrations'] : array( $d['illustrations'] ) as $item ) {
			if ( is_array( $item ) ) {
				$figures[] = $item;
			} elseif ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
				$libres[] = trim( (string) $item );
			}
		}
		$d['illustrations'] = $figures;
		if ( ! empty( $libres ) ) {
			$d['illustrations_libres'] = implode( "\n", $libres );
		}
		return $d;
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
		$d       = self::figures_lisibles( $d );
		// Le libellé en vigueur de chaque rubrique : une notice reçue sous un
		// ancien s'imprime sous le nouveau.
		foreach ( array( 'rubrique_principale', 'renvoi_1', 'renvoi_2' ) as $champ ) {
			if ( isset( $d[ $champ ] ) ) {
				$d[ $champ ] = self::rubrique_actuelle( $d[ $champ ] );
			}
		}
		$apercus = $avec_apercus ? $this->apercus_de( $id, $d ) : array();
		if ( ! empty( $d['illustrations'] ) ) {
			foreach ( array_keys( (array) $d['illustrations'] ) as $i ) {
				unset( $d['illustrations'][ $i ]['figure'] );
			}
		}
		$d = $this->poser_les_formes_preferees( $id, $d );
		// Le trait des années suit le réglage du jour, non celui du dépôt : un
		// même fascicule mêlait « 2004-2005 » et « 2004/2005 ». La fonction
		// reconnaît tous les traits, et la saisie reste telle qu'elle est.
		if ( isset( $d['annee'] ) ) {
			$d['annee'] = self::annee_normalisee( $d['annee'] );
		}
		// La référence, que les notices d'avant ne portaient pas dans leur
		// saisie : le Word la donne à la rédaction.
		if ( empty( $d['reference'] ) ) {
			$d['reference'] = (string) get_post_meta( (int) $id, '_na_reference', true );
		}
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

	private function redirect_result( $ok, $raison = '', $champ = '', $reference = '', $champs = array() ) {
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
		if ( ! $ok && ! empty( $champs ) ) {
			$redirect = add_query_arg( 'notice_champs', rawurlencode( implode( ',', $champs ) ), $redirect );
		}
		// Au succès aussi : la page de confirmation montre la référence, que
		// l'auteur cite s'il écrit à la rédaction.
		if ( '' !== $reference ) {
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
	/**
	 * Les autorisations de reproduction, une par figure au plus, par leur
	 * rang : le champ « illus_autorisation[] » suit les figures dans l'ordre.
	 *
	 * Rend « rang => fichier temporaire ». Un fichier refusé arrête le dépôt,
	 * comme une illustration refusée : l'auteur le croirait joint.
	 */
	private function recevoir_les_autorisations( &$erreur = '' ) {
		$erreur = '';
		$recues = array();
		if ( empty( $_FILES['illus_autorisation']['name'] ) || ! is_array( $_FILES['illus_autorisation']['name'] ) ) {
			return $recues;
		}
		$f       = $_FILES['illus_autorisation'];
		$dossier = trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp';
		wp_mkdir_p( $dossier );
		$combien = min( count( $f['name'] ), self::MAX_FILES );
		for ( $i = 0; $i < $combien; $i++ ) {
			$code = isset( $f['error'][ $i ] ) ? (int) $f['error'][ $i ] : UPLOAD_ERR_NO_FILE;
			if ( UPLOAD_ERR_NO_FILE === $code ) {
				continue;
			}
			$nom = sanitize_file_name( (string) $f['name'][ $i ] );
			$ext = strtolower( pathinfo( $nom, PATHINFO_EXTENSION ) );
			if ( UPLOAD_ERR_OK !== $code || (int) $f['size'][ $i ] > self::MAX_AUTORISATION
				|| ! in_array( $ext, self::AUTORISATION_EXT, true )
				|| ! $this->mime_is_allowed( $f['tmp_name'][ $i ], $nom, $ext ) ) {
				$erreur = 'autorisation_' . ( $i + 1 );
				$this->nettoyer( array_values( $recues ) );
				return array();
			}
			$cible = trailingslashit( $dossier ) . 'notice-archeomed-autorisation-fig-' . ( $i + 1 ) . '-'
				. wp_generate_password( 12, false, false ) . '.' . $ext;
			if ( ! move_uploaded_file( $f['tmp_name'][ $i ], $cible ) ) {
				$erreur = 'autorisation_deplacement';
				$this->nettoyer( array_values( $recues ) );
				return array();
			}
			$recues[ $i + 1 ] = $cible;
		}
		return $recues;
	}

	/**
	 * Range les autorisations à demeure, près des illustrations, et les
	 * retient par le rang de leur figure.
	 */
	private function archiver_les_autorisations( $id, $autorisations ) {
		$dossier = $this->dossier_des_illustrations();
		$gardees = array();
		foreach ( (array) $autorisations as $rang => $fichier ) {
			if ( ! file_exists( $fichier ) ) {
				continue;
			}
			$cible = '' === $dossier ? $fichier : $dossier . (int) $id . '-' . basename( $fichier );
			$gardees[ (int) $rang ] = ( $cible === $fichier || ! @rename( $fichier, $cible ) ) ? $fichier : $cible;
		}
		if ( $id && ! empty( $gardees ) ) {
			update_post_meta( $id, '_na_autorisations', $gardees );
		}
		return array_values( $gardees );
	}

	/**
	 * La raison qu'on donne à l'auteur, d'après le refus du téléversement.
	 *
	 * Un seul message couvrait tout — « trois au plus, vingt méga-octets en
	 * tout » —, faux pour qui avait respecté ces bornes.
	 */
	private function raison_du_televersement( $code ) {
		$raisons = array(
			'upload_ini_size'   => 'fichier_lourd',
			'upload_partial'    => 'fichier_coupe',
			'upload_too_many'   => 'fichiers_nombre',
			'upload_total_size' => 'fichiers_total',
			'upload_ext'        => 'fichier_format',
			'upload_mime'       => 'fichier_format',
		);
		return isset( $raisons[ $code ] ) ? $raisons[ $code ] : 'fichiers';
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
				// La cause compte pour l'auteur : un fichier trop lourd pour le
				// serveur ne se corrige pas comme une connexion coupée.
				$code = (int) $files['error'][ $i ];
				if ( UPLOAD_ERR_INI_SIZE === $code || UPLOAD_ERR_FORM_SIZE === $code ) {
					$upload_error = 'upload_ini_size';
				} elseif ( UPLOAD_ERR_PARTIAL === $code ) {
					$upload_error = 'upload_partial';
				} else {
					$upload_error = 'upload_error';
				}
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
		// Les dossiers de copies faites pour un courriel. Ils s'effacent après
		// l'envoi ; une requête coupée en route les laissait pour toujours,
		// la purge ne regardant que les fichiers.
		$courriels = glob( trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp/courriel-*', GLOB_ONLYDIR );
		foreach ( (array) $courriels as $dossier ) {
			if ( filemtime( $dossier ) >= time() - DAY_IN_SECONDS ) {
				continue;
			}
			foreach ( (array) glob( $dossier . '/*' ) as $copie ) {
				@unlink( $copie );
			}
			@rmdir( $dossier );
		}
		// Les ateliers d'un dossier Métopes dont l'assemblage est mort.
		Notice_Archeomed_Paquet::purger_les_ateliers( DAY_IN_SECONDS );
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
			// Pour les autorisations de reproduction seulement : une
			// illustration en PNG est écartée avant, par son extension.
			'png' => array( 'image/png' ),
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
