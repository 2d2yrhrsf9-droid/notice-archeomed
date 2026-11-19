<?php
/**
 * Plugin Name: Formulaire des notices d’archéologie médiévale
 * Description: Formulaire de soumission de notice d'opération archéologique pour la Chronique d'Archéologie médiévale. Le courriel adressé à la rédaction est accompagné d'un fichier DOCX stylé Métopes. Shortcode : [notice_archeomed_pactols]
 * Version:           3.107
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
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-lieu-dit.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-forme.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-styles.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-docx.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-sdrive.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-rangement.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-export.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-file.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-thesaurus.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-accessibilite.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-candidats.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-documentation.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-confidentialite.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-relecture.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-stockage.php';
require_once plugin_dir_path( __FILE__ ) . 'class-notice-archeomed-annuaire.php';

new Notice_Archeomed_Relecture();
new Notice_Archeomed_Settings();
new Notice_Archeomed_Accessibilite();
new Notice_Archeomed_Candidats();
new Notice_Archeomed_Annuaire();
new Notice_Archeomed_Documentation();
new Notice_Archeomed_Confidentialite();

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
		if ( ! Notice_Archeomed_Settings::peut_regler() ) {
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
		// Sans Imagick, un TIFF ou un PDF n'a ni version allégée ni basse
		// définition : le courriel joint l'original, le Word et la relecture
		// ne posent pas la figure. Une mise à jour de PHP chez l'hébergeur
		// suffit à faire disparaître l'extension sans que rien ne s'en
		// plaigne : on le dit en tête de l'administration.
		if ( ! Notice_Archeomed_Paquet::imagick_disponible() ) {
			echo '<div class="notice notice-warning"><p><strong>Chronique&nbsp;:</strong> '
				. esc_html__( 'l’extension PHP Imagick est absente de ce serveur. Les TIFF et les PDF sont convertis en JPEG par le navigateur de l’auteur, qui doit donc être récent ; un fichier que son navigateur ne lit pas lui est refusé. Les JPEG et les PNG passent par GD. À demander à l’hébergeur, pour retrouver le comportement complet : l’installation de php-imagick.', 'notice-archeomed' )
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
	// Les illustrations d'une notice, toutes ensemble. Vingt méga-octets
	// tant que PHP coupait à vingt-cinq ; quarante depuis que le serveur de la
	// revue accepte soixante-quatre, sans aller jusque-là : une figure ne pèse
	// sur le disque que le temps d'être rangée sur sDrive, mais une panne de
	// sDrive les y garderait toutes. Le courriel n'en dépend pas : il ne
	// porte que les aperçus.
	const MAX_TOTAL_FILESIZE  = 41943040;
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
	// Par adresse IP, envois aboutis. Il était de 150 : un robot qui passait
	// la protection remplissait le disque avant de le rencontrer. Quinze
	// dépôts aboutis en une heure, c'est déjà un laboratoire entier qui
	// dépose ensemble ; au-delà, le filtre « notice_archeomed_plafond_par_poste »
	// le relève.
	const MAX_SENDS_PER_IP      = 15;
	const MAX_SENDS_PER_EMAIL   = 25;    // par personne : là, une IP vaut bien une personne
	const MAX_LOOKUPS_PER_HOUR  = 400;
	// Tous dépôts confondus, par heure : un humain n'y arrive jamais, une
	// boucle de robots si.
	const MAX_SENDS_GLOBAL_PER_HOUR = 100;
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
	// Combien d'heures un défi reste valable. Un formulaire ouvert le soir et
	// envoyé le lendemain ne doit pas être refusé sans un mot : la limite de
	// temps du RGAA (13.1) veut vingt heures au moins.
	const PUZZLE_VALIDITE     = 24;
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
	/** Ce qui a fait refuser un envoi, en clair, pour le diagnostic. */
	private $cause_du_refus = '';
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
		// Les types que le formulaire propose, chacun seul ou dans son cadre,
		// puis les termes qu'il proposait avant, que portent des notices
		// reçues. Le titre du volume ne change pas avec celui du formulaire.
		'Opérations de terrain' => array(
			'Fouille',
			'Fouille préventive',
			'Fouille programmée',
			'Étude de bâti',
			'Étude de bâti préventive',
			'Étude de bâti programmée',
			'Sondage',
			'Sondage préventif',
			'Sondage programmé',
			'Diagnostic',
			'Diagnostic préventif',
			'Diagnostic programmé',
			'Surveillance de travaux',
			'Surveillance de travaux préventive',
			'Surveillance de travaux programmée',
			'Archéologie du bâti',
			'Opération de diagnostic',
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

	/**
	 * Chaque terme que la notice peut porter, et son concept dans Pactols.
	 *
	 * Un type composé avec son cadre n'a pas toujours de concept à lui : il
	 * prend alors celui de son type — « Étude de bâti programmée », celui de
	 * l'archéologie du bâti ; « Sondage préventif », celui du diagnostic,
	 * dont Pactols fait un synonyme.
	 */
	private $natures = array(
		'Fouille'                           => 'https://ark.frantiq.fr/ark:/26678/pcrtb1E0Dz7cSX',
		'Étude de bâti'                     => 'https://ark.frantiq.fr/ark:/26678/pcrtaodMT8j83O',
		'Étude de bâti préventive'          => 'https://ark.frantiq.fr/ark:/26678/pcrtaodMT8j83O',
		'Étude de bâti programmée'          => 'https://ark.frantiq.fr/ark:/26678/pcrtaodMT8j83O',
		'Sondage'                           => 'https://ark.frantiq.fr/ark:/26678/pcrt1t17kd7jn6982pgfp859',
		'Sondage préventif'                 => 'https://ark.frantiq.fr/ark:/26678/pcrtWWQS75V5Bc',
		'Diagnostic'                        => 'https://ark.frantiq.fr/ark:/26678/pcrtWWQS75V5Bc',
		'Diagnostic préventif'              => 'https://ark.frantiq.fr/ark:/26678/pcrtWWQS75V5Bc',
		'Diagnostic programmé'              => 'https://ark.frantiq.fr/ark:/26678/pcrtWWQS75V5Bc',
		'Surveillance de travaux préventive' => 'https://ark.frantiq.fr/ark:/26678/crtZ49Dtn1aMT',
		'Surveillance de travaux programmée' => 'https://ark.frantiq.fr/ark:/26678/crtZ49Dtn1aMT',
		'Opération d’archéologie préventive' => 'https://ark.frantiq.fr/ark:/26678/pcrt9A9KDllfAq',
		'Opération d’archéologie programmée' => 'https://ark.frantiq.fr/ark:/26678/pcrtUpTKpEpXJ5',
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
		'Sondage programmé'             => 'https://ark.frantiq.fr/ark:/26678/pcrtjf21909jq940v0h8gm44',
		'Surveillance de travaux'       => 'https://ark.frantiq.fr/ark:/26678/crtZ49Dtn1aMT',
	);
	/**
	 * Ce que la case affiche, quand ce n'est pas le terme Pactols lui-même.
	 *
	 * « Prospection archéologique » se lisait comme le nom commun de toutes
	 * les prospections, et l'on hésitait à la cocher à côté d'une
	 * prospection pédestre. La valeur envoyée, enregistrée et publiée reste
	 * le terme Pactols : seules les notices déjà reçues la portent, et elles
	 * doivent garder leur famille et leur ARK.
	 */
	private $libelles_des_natures = array(
		'Prospection archéologique' => 'Prospection (autre ou sans précision)',
	);
	/**
	 * Les termes qui s'impriment tels que la case les dit, et non sous la
	 * forme préférée de leur concept.
	 *
	 * Leur concept est celui d'un voisin : sous sa forme préférée, un
	 * sondage s'imprimait « tranchée archéologique », une étude de bâti
	 * programmée « archéologie du bâti » — le cadre se perdait.
	 */
	private $natures_a_la_lettre = array(
		'Étude de bâti', 'Étude de bâti préventive', 'Étude de bâti programmée',
		'Sondage', 'Sondage préventif',
		'Diagnostic', 'Diagnostic préventif', 'Diagnostic programmé',
		'Surveillance de travaux préventive', 'Surveillance de travaux programmée',
	);
	/**
	 * Le cadre d'une opération, coché une fois pour tous ses types, et ses
	 * deux accords.
	 *
	 * La rédaction a sorti « préventive » et « programmée » des fouilles
	 * pour les mettre en tête : le cadre vaut aussi pour un sondage, un
	 * diagnostic, une étude de bâti. Le serveur compose le terme —
	 * « Sondage préventif », « Étude de bâti programmée » — et c'est lui que
	 * la notice garde : le Word, le fascicule et les courriels le lisent
	 * comme avant.
	 */
	const CADRES = array(
		'Préventive' => array( 'f' => 'préventive', 'm' => 'préventif' ),
		'Programmée' => array( 'f' => 'programmée', 'm' => 'programmé' ),
	);
	/** Les types d'opération, dans l'ordre que la rédaction a fixé, et leur genre. */
	const TYPES_D_OPERATION = array(
		'Fouille'                 => 'f',
		'Étude de bâti'           => 'f',
		'Sondage'                 => 'm',
		'Diagnostic'              => 'm',
		'Surveillance de travaux' => 'f',
	);
	/**
	 * Un cadre coché sans type, à côté d'une prospection ou d'un projet
	 * collectif : il ne se perd pas, il devient ce terme.
	 */
	const NATURES_DU_CADRE = array(
		'Préventive' => 'Opération d’archéologie préventive',
		'Programmée' => 'Opération d’archéologie programmée',
	);
	/** Les termes d'avant les types, et le type qui les remplace. */
	const NATURES_RENOMMEES = array(
		'Archéologie du bâti'     => 'Étude de bâti',
		'Opération de diagnostic' => 'Diagnostic',
	);

	/** Les scripts du formulaire, mis de côté pour le pied de page. */
	private $scripts_du_pied = array();
	/**
	 * Les figures envoyées à part de ce dépôt, par leur place dans la liste
	 * des figures (0 pour la première) : lien, date, nom du fichier.
	 */
	private $figures_a_part = array();
	/**
	 * Si un refus garde la saisie en base pour la rendre au formulaire.
	 *
	 * Tout refus l'emportait, et chacune pèse jusqu'à 1,5 Mo : un robot qui
	 * postait en boucle sans jeton écrivait en base à chaque requête, le
	 * plafond de tentatives compris, puisqu'il refusait lui aussi. Un refus
	 * que seul un robot rencontre — pas de jeton, champ piège rempli, envoi
	 * en moins de trois secondes, plafond de tentatives — ne garde plus rien.
	 * Une personne n'y perd pas sa saisie : le navigateur la garde aussi.
	 */
	private $saisie_a_garder = true;
	/**
	 * Combien de saisies refusées un même poste fait garder par heure. Un
	 * auteur qui se reprend dix fois dans l'heure est déjà rare ; au-delà, la
	 * base ne se remplit plus pour lui, et le navigateur garde sa saisie.
	 */
	const MAX_RESERVES_PAR_IP = 10;

	/**
	 * Vrai quand le disque n'a pas pu prendre les fichiers de ce dépôt : la
	 * notice entre, ses figures sont « envoyées à part », et la page de fin
	 * le dit.
	 */
	private $fichiers_refuses = false;

	/** Vrai quand ce dépôt présente la clé de l'essai de charge ouvert. */
	private $essai = false;

	// Ce que tous les dépôts peuvent écrire en une heure. Une campagne n'en
	// approche pas : trois cents notices par an, de quelques Mo à quarante.
	const MAX_POIDS_PAR_HEURE = 500 * MB_IN_BYTES;

	public function __construct() {
		add_shortcode( 'notice_archeomed_pactols', array( $this, 'afficher_le_formulaire' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'template_redirect', array( $this, 'ne_pas_mettre_en_cache' ) );
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
		// En dernier : un relais ou une extension qui pose son propre nom
		// passe avant, et le nom de la revue doit être celui qu'on lit.
		add_action( 'phpmailer_init', array( __CLASS__, 'nommer_l_expediteur' ), PHP_INT_MAX );
		add_action( 'admin_post_na_feuille', array( $this, 'deposer_la_feuille' ) );
		add_action( 'admin_post_na_document', array( $this, 'telecharger_le_document' ) );
		add_action( 'admin_post_na_fascicule', array( $this, 'telecharger_le_fascicule' ) );
		add_action( 'admin_post_na_paquet', array( $this, 'telecharger_le_paquet' ) );
		add_action( 'admin_post_na_export', array( $this, 'demander_l_export' ) );
		add_action( 'wp_ajax_na_export_etat', array( $this, 'etat_des_exports' ) );
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
		$mode = in_array( $mode, array( 'locale', 'turnstile', 'les_deux' ), true ) ? $mode : 'locale';
		// Cloudflare injoignable : Turnstile laisse passer, et ne protège
		// plus rien. La pièce de puzzle s'y ajoute le temps que la sortie
		// revienne.
		if ( 'turnstile' === $mode && false !== get_transient( 'na_turnstile_injoignable' ) ) {
			return 'les_deux';
		}
		return $mode;
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
	 * l'affichage de la page et l'envoi du formulaire.
	 *
	 * Le sceau ne portait d'abord que la réponse : un couple (réponse, sceau)
	 * récolté une fois valait indéfiniment, pour tout le monde. L'heure y
	 * entra ensuite, et le couple valut vingt-quatre heures — de quoi, pour
	 * un robot, le récolter une fois et le rejouer des centaines de fois.
	 * Il porte maintenant l'heure d'affichage de la page (« na_ts »), qui ne
	 * se change donc plus sans le défaire, et il ne sert qu'une fois : un
	 * dépôt réussi le retient. Il vaut PUZZLE_VALIDITE heures : on ne refuse
	 * pas qui a laissé son formulaire ouvert pour la nuit.
	 */
	private function sceau_du_defi( $cible, $ts ) {
		return hash_hmac( 'sha256', 'puzzle|' . (int) $cible . '|' . (int) $ts,
			wp_salt( 'nonce' ) );
	}

	/** L'heure d'affichage de la page, la même pour « na_ts » et pour le sceau. */
	private function ts_de_la_page() {
		if ( ! $this->ts_de_la_page ) {
			$this->ts_de_la_page = time();
		}
		return $this->ts_de_la_page;
	}

	/** L'heure d'affichage de la page en cours de rendu. */
	private $ts_de_la_page = 0;

	/** Le sceau que ce dépôt a présenté, à retenir s'il aboutit. */
	private $sceau_presente = '';

	/** La page qui envoie en arrière-plan, par le jeton qu'elle a tiré. */
	private $page_envoyee = '';

	/** Ce dépôt a retenu la page : il dira ce qu'il est devenu. */
	private $page_reservee = false;

	/** La clé qui retient un sceau déjà servi. */
	private static function cle_du_sceau( $preuve ) {
		return 'na_sceau_' . substr( (string) $preuve, 0, 40 );
	}

	/** Retient le sceau d'un dépôt qui a abouti : il ne resservira pas. */
	private function consommer_le_sceau() {
		if ( '' !== $this->sceau_presente ) {
			set_transient( self::cle_du_sceau( $this->sceau_presente ), 1, ( self::PUZZLE_VALIDITE + 1 ) * HOUR_IN_SECONDS );
			$this->sceau_presente = '';
		}
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
	private function defi_du_puzzle( $ts = null ) {
		// La position de l'encoche, en centièmes de la largeur utile. Tirée au
		// sort plutôt que constante : le corps d'un envoi capté sur une page ne
		// se rejoue pas tel quel sur une autre. Les bornes laissent la pièce
		// visible aux deux extrémités.
		$cible = wp_rand( self::PUZZLE_MIN, self::PUZZLE_MAX );
		return array(
			'cible'  => $cible,
			'preuve' => $this->sceau_du_defi( $cible, null === $ts ? $this->ts_de_la_page() : (int) $ts ),
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
		$ts = isset( $_POST['na_ts'] ) && is_string( $_POST['na_ts'] ) ? absint( $_POST['na_ts'] ) : 0;
		if ( $ts <= 0 || time() - $ts > self::PUZZLE_VALIDITE * HOUR_IN_SECONDS
			|| ! hash_equals( $this->sceau_du_defi( $cible, $ts ), $preuve ) ) {
			return false;
		}
		if ( false !== get_transient( self::cle_du_sceau( $preuve ) ) ) {
			error_log( 'Notice Archeomed: sceau du puzzle déjà servi.' );
			return false;
		}
		$this->sceau_presente = $preuve;
		return true;
	}

	/**
	 * Une page affichée avant que Cloudflare se révèle injoignable n'a pas
	 * de puzzle : on ne la refuse pas pour cela, le premier jour. Au-delà,
	 * toute page ouverte depuis le porte.
	 */
	private function puzzle_absent_admis() {
		$depuis = get_transient( 'na_turnstile_injoignable' );
		if ( false === $depuis || 'turnstile' !== Notice_Archeomed_Settings::get( 'protection' )
			|| ! empty( $_POST['na_preuve'] ) ) {
			return false;
		}
		$ts = isset( $_POST['na_ts'] ) && is_string( $_POST['na_ts'] ) ? absint( $_POST['na_ts'] ) : 0;
		return $ts > 0 && $ts <= (int) $depuis && time() - (int) $depuis < DAY_IN_SECONDS;
	}

	/**
	 * Le passage obligé, quelle que soit la protection choisie.
	 */
	private function verifier_la_protection() {
		if ( $this->protection_utilise_la_question() && ! $this->puzzle_absent_admis() && ! $this->verifier_le_puzzle() ) {
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
	 * La sous-rubrique où se range, dans la rubrique V, le renvoi qu'une
	 * notice d'une autre rubrique y fait ; une chaîne vide sans renvoi vers
	 * V, ou tant que personne ne l'a classé.
	 *
	 * Distincte de la matière de la notice : l'auteur ne la choisit pas, le
	 * coordinateur de V la pose à la relecture. Sans elle, tous les renvois
	 * se rangeaient en tête de V, sous « V. 1. ».
	 */
	public static function renvoi_matiere_de( $d ) {
		if ( ! is_array( $d ) || self::RUBRIQUE_ARTISANAT === self::rubrique_actuelle( isset( $d['rubrique_principale'] ) && is_scalar( $d['rubrique_principale'] ) ? (string) $d['rubrique_principale'] : '' ) ) {
			return '';
		}
		$vers_v = false;
		foreach ( array( 'renvoi_1', 'renvoi_2' ) as $champ ) {
			if ( isset( $d[ $champ ] ) && is_scalar( $d[ $champ ] ) && self::RUBRIQUE_ARTISANAT === self::rubrique_actuelle( (string) $d[ $champ ] ) ) {
				$vers_v = true;
			}
		}
		$lettre = isset( $d['renvoi_matiere'] ) && is_scalar( $d['renvoi_matiere'] ) ? strtoupper( trim( (string) $d['renvoi_matiere'] ) ) : '';
		return ( $vers_v && array_key_exists( $lettre, self::MATIERES ) ) ? $lettre : '';
	}

	/**
	 * La saisie d'une notice telle que la rubrique d'un de ses renvois la
	 * range et la titre : sous cette rubrique, et dans V sous la
	 * sous-rubrique que le coordinateur a choisie pour le renvoi.
	 *
	 * Le fascicule et le sommaire de la relecture passent tous deux par ici,
	 * pour ranger un renvoi au même endroit.
	 */
	public static function saisie_du_renvoi( $d, $rubrique ) {
		$rubrique = self::rubrique_actuelle( $rubrique );
		return array_merge( (array) $d, array(
			'rubrique_principale' => $rubrique,
			'rubrique_matiere'    => self::RUBRIQUE_ARTISANAT === $rubrique ? self::renvoi_matiere_de( $d ) : '',
		) );
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
		if ( ! Notice_Archeomed_Settings::peut_regler() ) {
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
		if ( ! Notice_Archeomed_Settings::peut_regler() ) {
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
		// Les fichiers rangés sur sDrive et effacés d'ici reviennent le temps
		// de l'assemblage, puis s'effacent quoi qu'il arrive.
		$temporaires = array();
		register_shutdown_function( function () use ( &$temporaires ) {
			Notice_Archeomed_Rangement::effacer_les_temporaires( $temporaires );
		} );
		$notices = $this->notices_a_assembler( $ids );
		foreach ( $notices as $rang_notice => $notice ) {
			$illustrations = $notice['illustrations'];
			foreach ( $illustrations as $i => $chemin ) {
				$lu = Notice_Archeomed_Rangement::rapatrier( $notice['id'], $chemin, $temporaires );
				$illustrations[ $i ] = '' !== $lu ? $lu : $chemin;
			}
			$conversions = $notice['conversions'];
			foreach ( $conversions as $rang => $familles ) {
				foreach ( (array) $familles as $famille => $chemin ) {
					$lu = Notice_Archeomed_Rangement::rapatrier( $notice['id'], $chemin, $temporaires );
					$conversions[ $rang ][ $famille ] = '' !== $lu ? $lu : $chemin;
				}
			}
			$notices[ $rang_notice ]['illustrations'] = $illustrations;
			$notices[ $rang_notice ]['conversions']   = $conversions;
		}

		$paquet = new Notice_Archeomed_Paquet();
		$plan   = $paquet->preparer( $rubrique, $notices, $erreur );
		if ( '' !== $erreur ) {
			$paquet->nettoyer();
			wp_die( esc_html( $erreur ) );
		}

		$assemble = $this->assembler_la_rubrique( $rubrique, $notices, $plan, $paquet, $erreur );
		if ( null === $assemble ) {
			$paquet->nettoyer();
			wp_die( esc_html( $erreur ) );
		}
		$document = $assemble['document'];

		$archive = $paquet->emballer( $document, $assemble['relecture'], $erreur );
		@unlink( $document );
		$paquet->nettoyer();
		if ( '' === $archive ) {
			wp_die( esc_html( '' !== $erreur ? $erreur : "Le paquet n'a pas pu être assemblé." ) );
		}
		$this->rendre_le_fichier( $archive, basename( $archive ) );
	}

	/**
	 * Inscrit une rubrique à l'export sur sDrive, puis revient à la liste
	 * des notices, qui dit où en est l'export.
	 */
	public function demander_l_export() {
		if ( ! Notice_Archeomed_Settings::peut_regler() ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		check_admin_referer( 'na_export' );
		$rubrique = isset( $_GET['rubrique'] )
			? self::rubrique_actuelle( sanitize_text_field( wp_unslash( $_GET['rubrique'] ) ) ) : '';
		if ( ! in_array( $rubrique, $this->rubriques, true ) ) {
			wp_die( esc_html__( 'Rubrique inconnue.', 'notice-archeomed' ) );
		}
		if ( empty( $this->ids_de_la_rubrique( $rubrique ) ) ) {
			wp_die( esc_html__( 'Aucune notice dans cette rubrique.', 'notice-archeomed' ) );
		}
		$message = Notice_Archeomed_Export::demander( $rubrique );
		set_transient( 'na_export_message_' . get_current_user_id(), $message, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'post_type', Notice_Archeomed_File::CPT, admin_url( 'edit.php' ) ) );
		exit;
	}

	/**
	 * Où en est l'export de chaque rubrique, pour la liste des notices qui
	 * le demande tant qu'un export est en route. Chaque demande est aussi
	 * une visite : c'est elle qui fait passer la tâche.
	 */
	public function etat_des_exports() {
		if ( ! Notice_Archeomed_Settings::peut_regler() ) {
			wp_send_json_error( null, 403 );
		}
		check_ajax_referer( 'na_export_etat' );
		$etats = array();
		foreach ( $this->rubriques as $rubrique ) {
			$etats[ $rubrique ] = Notice_Archeomed_Export::etat_de( $rubrique );
		}
		wp_send_json_success( array( 'etats' => $etats, 'en_cours' => Notice_Archeomed_Export::en_cours() ) );
	}

	/** Les notices qu'une rubrique porte au fascicule. */
	public function ids_de_la_rubrique( $rubrique ) {
		return $this->file()->notices_de_la_rubrique( $rubrique );
	}

	/**
	 * Les notices d'une rubrique telles que le dossier les assemble : la
	 * saisie, et les chemins de leurs fichiers tels qu'enregistrés, sans
	 * écarter ceux qui manquent — c'est au paquet de dire qu'un fichier reçu
	 * n'est plus là. Un fichier rangé sur sDrive garde son chemin d'ici :
	 * à l'appelant de le rapatrier.
	 */
	public function notices_a_assembler( $ids ) {
		$notices = array();
		foreach ( (array) $ids as $id ) {
			$d = $this->saisie_de( $id, false );
			if ( null === $d ) {
				continue;
			}
			// Les fichiers d'une figure masquée restent où ils sont, mais le
			// dossier ne les emporte pas : la saisie vient d'en sortir sans elle.
			list( $illustrations, $conversions ) = self::fichiers_sans_les_masquees(
				self::figures_lisibles( (array) get_post_meta( (int) $id, '_na_donnees', true ) ),
				self::illustrations_rangees( $id ), self::conversions_rangees( $id ) );
			$notices[] = array(
				'id'            => (int) $id,
				'd'             => $d,
				'illustrations' => $illustrations,
				'conversions'   => $conversions,
			);
		}
		return $notices;
	}

	/**
	 * Le document et la page de relecture d'une rubrique, une fois ses
	 * figures posées, et ses blocs d'index dans l'atelier du paquet.
	 *
	 * L'archive et l'export sur sDrive passent par ici : une seule façon
	 * d'assembler une rubrique, et non deux qui divergeraient. « $plan »
	 * donne, par notice et par rang, la mesure de la basse définition.
	 *
	 * Rend { document, relecture }, ou null avec l'erreur.
	 */
	public function assembler_la_rubrique( $rubrique, $notices, $plan, $paquet, &$erreur ) {
		$erreur = '';
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
		$blocs_poses        = 0;
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
			if ( '' !== $paquet->poser_une_indexation(
				Notice_Archeomed_Nommage::assainir(
					trim( $notice['d']['commune'] . '-' . $notice['d']['lieu_dit'] ) ),
				$xml ) ) {
				++$blocs_poses;
			}
		}
		// Le lisez-moi annonce « un fichier par notice » : un dossier vide doit
		// dire pourquoi, sans quoi on le croit perdu en route. Ce qui reste à
		// résoudre a son avis plus bas ; ici, les cas où rien ne viendra.
		if ( 0 === $blocs_poses && 0 === $termes_manquants && ! empty( $notices ) ) {
			$paquet->noter( self::lit_pactols()
				? 'XML/indexation est vide : aucune notice de la rubrique ne porte de terme'
					. ' choisi dans Pactols.'
				: 'XML/indexation est vide : la lecture des termes par le serveur est coupée'
					. ' (page Hébergement, « Lecture des termes par le serveur »). Les'
					. ' identifiants ARK restent en lien dans le Word ; quand le serveur'
					. ' pourra joindre Pactols, rétablissez la lecture et exportez de nouveau'
					. ' la rubrique.' );
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
			return null;
		}
		return array(
			'document'  => $document,
			'relecture' => $this->page_de_relecture( $rubrique, $donnees ),
		);
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
		if ( ! Notice_Archeomed_Settings::peut_regler() ) {
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
				$titre .= esc_html( Notice_Archeomed_Normes::avant_le_lieu_dit() ) . self::lieu_dit_html( $d );
			}
			$h .= '<article><h2>' . $titre . '</h2>';

			// Les mêmes termes qu'au document, portant les mêmes ARK : la page
			// sert aussi à vérifier les liens d'un clic, ce qu'un Word aux
			// liens externes ne permet pas commodément.
			$lier = function ( $items, $capitale = false ) {
				$out = array();
				foreach ( (array) $items as $item ) {
					// Ni vide, ni gardé hors de Pactols : comme au document.
					if ( '' === ( isset( $item['label'] ) ? trim( (string) $item['label'] ) : '' ) || self::hors_pactols( $item ) ) {
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
			if ( ! empty( $d['donnees_lien'] ) ) {
				$meta[] = esc_html( 'Jeu de données : ' ) . '<a href="'
					. esc_url( $d['donnees_lien'] ) . '">'
					. esc_html( $d['donnees_lien'] ) . '</a>';
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
			$texte_html = self::petites_capitales_du_html( self::siecles_du_html(
				self::typographie_du_html( $this->clean_richtext( $d['texte_notice'] ) ) ) );
			// La mention de responsabilité, comme le document la pose : collée au
			// point final du dernier paragraphe, dans la police du texte — ou, si
			// la correspondance des styles lui donne son paragraphe, à part et
			// sans parenthèses. Elle prenait un paragraphe de métadonnée, en
			// police sans empattement et plus petite, détaché du texte.
			$responsables = esc_html( $this->responsables_inline( $d ) );
			if ( ! Notice_Archeomed_Styles::responsabilites_a_part() && preg_match( '#</p>\s*$#i', $texte_html ) ) {
				$texte_html = preg_replace( '#</p>\s*$#i', ' (' . $responsables . ')</p>', $texte_html, 1 );
			} else {
				$texte_html .= '<p>' . ( Notice_Archeomed_Styles::responsabilites_a_part() ? $responsables : '(' . $responsables . ')' ) . '</p>';
			}
			$h .= '<div class="texte">' . $texte_html . '</div>';

			foreach ( (array) ( isset( $d['illustrations'] ) ? $d['illustrations'] : array() ) as $item ) {
				$h .= '<figure>';
				// La description détaillée, sous la figure, et l'image qui y
				// renvoie : un lecteur d'écran la lit après le texte alternatif.
				$description = self::description_en_html( $item );
				$id_description = 'description-' . ( ++$descriptions );
				if ( ! empty( $item['figure']['fichier'] ) ) {
					// Le texte alternatif de l'auteur ; à défaut, pour une notice
					// d'avant ce champ, le titre.
					$h .= '<img src="' . esc_attr( $item['figure']['fichier'] ) . '" alt="'
						. esc_attr( Notice_Archeomed_Controles::texte_alternatif( $item ) ) . '"'
						. ( empty( $description ) ? '' : ' aria-describedby="' . esc_attr( $id_description ) . '"' ) . '>';
				} elseif ( null !== self::figure_a_part( $item ) ) {
					$a_part = self::figure_a_part( $item );
					$h     .= '<p class="absente">Figure envoyée à part par l’auteur, à reprendre d’après l’original'
						. ( ! empty( $a_part['fichier'] ) ? ' (« ' . esc_html( $a_part['fichier'] ) . ' »)' : '' )
						. ( ! empty( $a_part['lien'] ) ? ' : <a href="' . esc_url( $a_part['lien'] ) . '">' . esc_html( $a_part['lien'] ) . '</a>' : '' )
						. '.</p>';
				} else {
					$h .= '<p class="absente">Pas de basse définition pour cette figure.</p>';
				}
				// « Fig. 1 Titre », comme le document le compose.
				$h .= '<figcaption><strong>' . esc_html( Notice_Archeomed_Normes::numero_de_figure( $item['rang'] ) ) . '</strong>';
				$titre_fig = Notice_Archeomed_Controles::titre_sans_numero( $item['titre'], $item['rang'] );
				if ( '' !== $titre_fig ) {
					$h .= self::figure_en_html( $item, 'titre', $titre_fig, Notice_Archeomed_Normes::apres_le_numero() );
				}
				if ( '' !== $item['legende'] ) {
					$h .= '<br>' . self::figure_en_html( $item, 'legende', $item['legende'] );
				}
				if ( '' !== $item['credits'] ) {
					$h .= '<br><em>' . esc_html( $item['credits'] ) . '</em>';
				}
				// Le relecteur voit ce que l'image dira à qui ne la voit pas.
				$nature = Notice_Archeomed_Controles::type_de_figure( isset( $item['type'] ) ? $item['type'] : '' );
				if ( '' !== $nature ) {
					$h .= '<br><span class="alt">' . esc_html( "Nature\u{00A0}: " . Notice_Archeomed_Controles::TYPES_DE_FIGURE[ $nature ] ) . '</span>';
				}
				if ( ! empty( $item['alt'] ) ) {
					$h .= '<br><span class="alt">' . esc_html( "Texte alternatif\u{00A0}: " . Notice_Archeomed_Controles::une_ligne( $item['alt'] ) ) . '</span>';
				}
				$h .= '</figcaption>';
				if ( ! empty( $description ) ) {
					$h .= '<div class="description" id="' . esc_attr( $id_description ) . '"><p><strong>'
						. esc_html( "Description détaillée\u{00A0}:" ) . '</strong> '
						. implode( '</p><p>', $description ) . '</p></div>';
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
				. $this->runs_xml( $doc, Notice_Archeomed_Lieu_Dit::morceaux_du_titre( $d ) );
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
	 * Range des entrées dans l'ordre du fascicule : la sous-rubrique, puis la
	 * commune par ordre alphabétique. Chaque entrée porte sa « clef », celle
	 * du classement.
	 *
	 * Le fascicule et le sommaire de la relecture passent tous deux par ici :
	 * le coordinateur lit ses notices dans l'ordre où le volume les donnera,
	 * et un second tri écrit à côté aurait fini par ranger autrement.
	 */
	public function ranger_comme_au_fascicule( $entrees ) {
		$entrees = array_values( (array) $entrees );
		usort( $entrees, function ( $a, $b ) {
			// La clef porte le rang de rubrique en tête ; ici toutes les
			// entrées ne sont pas de la même, et c'est la sous-rubrique puis
			// la commune qui comptent.
			$sa = substr( $a['clef'], strpos( $a['clef'], '|' ) + 1 );
			$sb = substr( $b['clef'], strpos( $b['clef'], '|' ) + 1 );
			return strcmp( $sa, $sb );
		} );
		return $entrees;
	}

	/**
	 * Le sommaire d'une rubrique pour la relecture : ses notices, celles que
	 * le fascicule portera, dans son ordre, chacune avec sa sous-rubrique et
	 * son titre.
	 */
	public function sommaire_de_la_rubrique( $rubrique ) {
		$entrees = array();
		foreach ( $this->ids_de_la_rubrique( $rubrique ) as $id ) {
			$d = $this->saisie_de( $id, false );
			if ( null === $d ) {
				continue;
			}
			$entrees[] = array(
				'id'      => (int) $id,
				'clef'    => $this->classement_de( $d ),
				'famille' => $this->titre_de_famille( $d ),
				'titre'   => $this->titre_de_liste( $d ),
			);
		}
		return $this->ranger_comme_au_fascicule( $entrees );
	}

	/** Les rubriques de la Chronique, dans l'ordre du volume. */
	public function rubriques() {
		return $this->rubriques;
	}

	/**
	 * Les cases de la nature, hors cadre, par groupe et dans l'ordre de la
	 * page : les types, les prospections, les projets collectifs.
	 *
	 * Les termes composés et ceux d'avant les types ne s'y cochent pas : ils
	 * se lisent dans les notices, et reviennent à leur type et à leur cadre.
	 */
	private function groupes_de_natures() {
		$groupes = array( 'Type d’opération' => array_keys( self::TYPES_D_OPERATION ) );
		foreach ( array( 'Prospections', 'Projets collectifs de recherche' ) as $famille ) {
			$groupes[ $famille ] = array_values( array_filter( $this->familles[ $famille ], function ( $nature ) {
				return isset( $this->natures[ $nature ] );
			} ) );
		}
		return $groupes;
	}

	/** Les cases de la nature que le formulaire propose, hors cadre : valeur => libellé. */
	public function natures_proposees() {
		$natures = array();
		foreach ( $this->groupes_de_natures() as $liste ) {
			foreach ( $liste as $nature ) {
				$natures[ $nature ] = isset( $this->libelles_des_natures[ $nature ] ) ? $this->libelles_des_natures[ $nature ] : $nature;
			}
		}
		return $natures;
	}

	/** Les cadres que le formulaire propose, dans leur ordre. */
	public function cadres_proposes() {
		return array_keys( self::CADRES );
	}

	/**
	 * Les termes que la notice garde, composés des cases cochées.
	 *
	 * Chaque type se compose avec chaque cadre coché — une fouille à la fois
	 * préventive et programmée porte les deux termes —, ou reste seul sans
	 * cadre. Une prospection ou un projet collectif passe tel quel, comme un
	 * terme d'avant les types qu'envoie encore une page ouverte avant la
	 * mise à jour. Un cadre sans aucun type, à côté d'une prospection, garde
	 * son sens par « Opération d'archéologie programmée ». Un cadre seul ne
	 * fait aucune nature : il reste à cocher ce qui a été fait.
	 */
	public function composer_les_natures( $cadres, $cochees ) {
		$cadres = array_values( array_intersect( array_keys( self::CADRES ), array_map( 'strval', (array) $cadres ) ) );
		$termes = array();
		$type   = false;
		$autre  = false;
		foreach ( array_map( 'strval', (array) $cochees ) as $cochee ) {
			if ( isset( self::TYPES_D_OPERATION[ $cochee ] ) ) {
				$type = true;
				if ( empty( $cadres ) ) {
					$termes[] = $cochee;
				}
				foreach ( $cadres as $cadre ) {
					$termes[] = $cochee . ' ' . self::CADRES[ $cadre ][ self::TYPES_D_OPERATION[ $cochee ] ];
				}
			} elseif ( isset( $this->natures[ $cochee ] ) ) {
				$termes[] = $cochee;
				if ( in_array( $cochee, $this->familles['Opérations de terrain'], true ) ) {
					$type = true;
				} else {
					$autre = true;
				}
			}
		}
		if ( ! $type && $autre ) {
			foreach ( $cadres as $cadre ) {
				$termes[] = self::NATURES_DU_CADRE[ $cadre ];
			}
		}
		return array_values( array_unique( $termes ) );
	}

	/**
	 * Les termes composés des cases, plus ceux que la notice portait déjà et
	 * qu'on ne propose plus ni ne sait défaire : la relecture les laisse
	 * cochés, ils ne doivent pas se perdre en passant par elle.
	 */
	public function recomposer_les_natures( $cadres, $cochees, $gardees ) {
		$termes = $this->composer_les_natures( $cadres, $cochees );
		foreach ( array_map( 'strval', (array) $cochees ) as $cochee ) {
			if ( ! isset( $this->natures[ $cochee ] ) && in_array( $cochee, (array) $gardees, true ) ) {
				$termes[] = $cochee;
			}
		}
		return array_values( array_unique( $termes ) );
	}

	/**
	 * Les termes qui ne sont plus une case à eux, et celles qu'ils cochent
	 * aujourd'hui : un brouillon gardé dans le navigateur avant les types
	 * porte encore « Fouille préventive ».
	 */
	private function natures_d_avant() {
		$table = array();
		foreach ( array_keys( $this->natures ) as $terme ) {
			$etat = $this->decomposer_les_natures( array( $terme ) );
			if ( array( $terme ) !== $etat['natures'] || ! empty( $etat['cadres'] ) ) {
				$table[ $terme ] = $etat;
			}
		}
		return $table;
	}

	/**
	 * Les cases qu'il faut cocher pour retrouver des termes gardés : les
	 * cadres d'un côté, les types et les autres natures de l'autre.
	 *
	 * Une notice reçue avant les types se rouvre ainsi dans le formulaire
	 * d'aujourd'hui : « Fouille préventive » coche Fouille et Préventive,
	 * « Archéologie du bâti » coche Étude de bâti. Un terme qu'on ne sait
	 * pas défaire reste tel quel, pour que rien ne se perde.
	 */
	public function decomposer_les_natures( $termes ) {
		$cadres  = array();
		$natures = array();
		foreach ( array_map( 'strval', (array) $termes ) as $terme ) {
			$terme = trim( $terme );
			if ( '' === $terme ) {
				continue;
			}
			$cadre = array_search( $terme, self::NATURES_DU_CADRE, true );
			if ( false !== $cadre ) {
				$cadres[] = $cadre;
				continue;
			}
			if ( isset( self::NATURES_RENOMMEES[ $terme ] ) ) {
				$terme = self::NATURES_RENOMMEES[ $terme ];
			}
			foreach ( self::TYPES_D_OPERATION as $type => $genre ) {
				foreach ( self::CADRES as $nom => $accords ) {
					if ( $type . ' ' . $accords[ $genre ] === $terme ) {
						$cadres[] = $nom;
						$terme    = $type;
						break 2;
					}
				}
			}
			$natures[] = $terme;
		}
		return array(
			'cadres'  => array_values( array_intersect( array_keys( self::CADRES ), $cadres ) ),
			'natures' => array_values( array_unique( $natures ) ),
		);
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
			// Le renvoi se classe dans la famille de la notice qui le porte :
			// c'est la même opération, vue d'une autre rubrique. Mais il se
			// range sous la rubrique du fascicule, comme il s'y titre : un
			// renvoi venu de V gardait sa matière, « A1 », qui se classe après
			// « 2 », et rouvrait « I. 1. » derrière « I. 2. ». Dans V, il prend
			// la sous-rubrique que son coordinateur lui a donnée.
			$entrees[] = array( 'quoi' => 'renvoi', 'd' => $d, 'clef' => $this->classement_de(
				self::saisie_du_renvoi( $d, $rubrique ) ) );
		}
		$entrees = $this->ranger_comme_au_fascicule( $entrees );

		$famille_en_cours = null;
		foreach ( $entrees as $entree ) {
			// Un renvoi se titre sous la rubrique du fascicule, non sous celle
			// de sa notice : « I. 1. » ouvrait une fausse sous-rubrique dans
			// la rubrique II, et coupait la vraie en deux. La famille, elle,
			// reste celle de l'opération.
			$famille = $this->titre_de_famille( 'renvoi' === $entree['quoi']
				? self::saisie_du_renvoi( $entree['d'], $rubrique )
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
		return ( isset( $_GET['notice_reprise'] ) && is_string( $_GET['notice_reprise'] ) )
			? sanitize_key( wp_unslash( $_GET['notice_reprise'] ) ) : '';
	}

	/** Le jeton que le formulaire renvoie, s'il en porte un. */
	private function jeton_poste() {
		return ( isset( $_POST['reprise_jeton'] ) && is_string( $_POST['reprise_jeton'] ) )
			? sanitize_key( wp_unslash( $_POST['reprise_jeton'] ) ) : '';
	}

	private function reprise() {
		if ( null !== $this->reprise ) {
			return $this->reprise;
		}
		$this->reprise = array();
		$jeton = $this->jeton_de_reprise();
		if ( '' !== $jeton ) {
			$garde = get_transient( 'na_reprise_' . $jeton );
			if ( is_array( $garde ) && ! empty( $garde['corrige_id'] ) ) {
				// Une notice à corriger en place : le lien vivant la lit telle
				// qu'elle est aujourd'hui, la reprise d'un refus telle qu'on
				// l'avait saisie. Supprimée, ou la rédaction qui l'a ouverte
				// n'étant plus là, elle ne se rouvre pas.
				$id = $this->contexte_valide( $garde );
				if ( $id ) {
					$this->reprise = ! empty( $garde['vivant'] )
						? $this->saisie_pour_le_formulaire( $id, $garde, $jeton ) : $garde;
				}
			} elseif ( is_array( $garde ) ) {
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
		$jeton = $this->jeton_poste();
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
			'resp_prenom', 'resp_nom', 'resp_email', 'resp_inst', 'deposant_prenom', 'deposant_nom', 'deposant_email',
			'coresp_prenom', 'coresp_nom', 'coresp_email', 'coresp_inst',
			'coauteur_prenom', 'coauteur_nom', 'coauteur_email', 'coauteur_inst',
			'departement', 'lieu_dit', 'lieu_dit_forme', 'annee',
			'num_autorisation', 'id_patriarche', 'rapport_lien', 'donnees_lien', 'originaux_lien', 'originaux_jusqua', 'commentaires',
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
			// Les bornes comptent : cette saisie se range en base avant tout
			// contrôle, y compris pour qui n'a pas de jeton valable. Le texte
			// de la notice a sa place, les autres champs sont courts. Le
			// texte est coupé avant d'être lu, non après.
			$valeur = wp_unslash( $_POST[ $champ ] );
			if ( 'texte_notice' === $champ ) {
				$garde[ $champ ] = $this->texte_notice_pose( function_exists( 'mb_substr' ) ? mb_substr( $valeur, 0, 200000 ) : substr( $valeur, 0, 200000 ) );
			} else {
				$garde[ $champ ] = $this->limit_string( $valeur, 20000 );
			}
		}
		if ( isset( $_POST['nature'] ) && is_array( $_POST['nature'] ) ) {
			$garde['nature'] = array_map( 'sanitize_text_field',
				array_map( 'strval', array_slice( wp_unslash( $_POST['nature'] ), 0, 30 ) ) );
		}
		if ( isset( $_POST['cadre'] ) && is_array( $_POST['cadre'] ) ) {
			$garde['cadre'] = array_map( 'sanitize_text_field',
				array_map( 'strval', array_slice( wp_unslash( $_POST['cadre'] ), 0, 5 ) ) );
		}
		foreach ( array( 'commune', 'commune_ark', 'organisme',
			'illus_titre', 'illus_legende', 'illus_credits', 'illus_alt', 'illus_description', 'illus_type',
			'illus_source', 'illus_lien', 'illus_jusqua', 'illus_nom_envoi' ) as $champ ) {
			if ( isset( $_POST[ $champ ] ) && is_array( $_POST[ $champ ] ) ) {
				// Au plus dix éléments, de 5 000 caractères : un formulaire
				// n'en porte jamais autant.
				$garde[ $champ ] = array_map( 'sanitize_textarea_field',
					array_map( function ( $v ) { return $this->limit_string( (string) $v, 5000 ); },
						array_slice( wp_unslash( $_POST[ $champ ] ), 0, 10 ) ) );
			}
		}
		// La mise en forme des textes de chaque figure : l'assainissement
		// ôterait ses balises, elle est donc réécrite, rien que les trois
		// marques et le texte. Elle se raccorde au texte à l'envoi.
		foreach ( array_keys( Notice_Archeomed_Forme::CLEFS ) as $clef ) {
			$champ = 'illus_' . $clef;
			if ( isset( $_POST[ $champ ] ) && is_array( $_POST[ $champ ] ) ) {
				$garde[ $champ ] = array_map( function ( $v ) {
					return is_string( $v ) ? Notice_Archeomed_Forme::propre( $v ) : '';
				}, array_slice( array_values( wp_unslash( $_POST[ $champ ] ) ), 0, 10 ) );
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
		// Un plafond d'ensemble : au-delà, ce n'est plus une saisie d'auteur.
		if ( empty( $garde ) || strlen( maybe_serialize( $garde ) ) > 1500000 ) {
			return '';
		}
		// Une correction qui échoue reste une correction : la référence
		// remplacée suit la saisie de reprise en reprise.
		$remplace = ( null === $remplace ) ? $this->reference_corrigee() : $remplace;
		if ( '' !== $remplace ) {
			$garde['remplace'] = $remplace;
		}
		// Un refus pendant une correction en place reste une correction en
		// place : la notice visée et ses figures suivent la saisie.
		$garde = $this->reporter_le_contexte( $garde );
		// Une correction que son lien n'ouvre plus revient à lire, non à
		// renvoyer : renvoyée, elle ferait une notice de plus.
		if ( $this->correction_sans_lien() ) {
			$garde['perimee'] = 1;
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
		$jeton = $this->jeton_poste();
		if ( '' === $jeton ) {
			return $garde;
		}
		$avant = get_transient( 'na_reprise_' . $jeton );
		if ( ! is_array( $avant ) || empty( $avant['illus_titre'] ) || ! is_array( $avant['illus_titre'] ) ) {
			return $garde;
		}
		$champs = array( 'illus_titre', 'illus_legende', 'illus_credits', 'illus_alt', 'illus_description', 'illus_type', 'illus_nom', 'illus_autorisation',
			'illus_source', 'illus_lien', 'illus_jusqua', 'illus_nom_envoi',
			'illus_titre_forme', 'illus_legende_forme', 'illus_description_forme' );
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
		$jeton = $this->jeton_poste();
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
		// Le lien vit encore, mais la campagne est close : il ne faut pas le
		// dire expiré, l'auteur en redemanderait un.
		$garde = get_transient( 'na_reprise_' . $jeton );
		if ( is_array( $garde ) && ! empty( $garde['corrige_id'] ) && empty( $garde['par_la_redaction'] )
			&& Notice_Archeomed_Relecture::campagne_close() ) {
			$etat['etat'] = 'close';
			return $etat;
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
		$jeton = $this->jeton_poste();
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
				'notice_ref', 'notice_reprise', 'notice_corrigee', 'notice_sans_fichiers' ),
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
	 * La dernière page publiée qui contient le shortcode ; à défaut, un autre
	 * contenu publié. Chaîne vide si aucune ne le porte.
	 *
	 * La dernière, non la première : une page d'essai plus ancienne, restée
	 * en ligne, prenait la place du formulaire dans les liens de correction.
	 */
	private function page_du_formulaire() {
		static $adresse = null;
		if ( null !== $adresse ) {
			return $adresse;
		}
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type NOT IN ( 'revision', 'nav_menu_item', %s )"
			. " AND post_content LIKE %s ORDER BY ( post_type = 'page' ) DESC, post_date DESC, ID DESC LIMIT 1",
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

	/**
	 * Le lien de correction corrige-t-il la notice en place ? Une notice
	 * inscrite avant cette version porte encore un lien qui en dépose une
	 * autre, et son courriel doit le dire : les illustrations y sont à
	 * redéposer.
	 */
	private static function lien_vivant( $url ) {
		$requete = wp_parse_url( (string) $url, PHP_URL_QUERY );
		$params  = array();
		parse_str( is_string( $requete ) ? $requete : '', $params );
		if ( empty( $params['notice_reprise'] ) || ! is_string( $params['notice_reprise'] ) ) {
			return false;
		}
		$garde = get_transient( 'na_reprise_' . sanitize_key( $params['notice_reprise'] ) );
		return is_array( $garde ) && ! empty( $garde['vivant'] );
	}

	/**
	 * Vrai quand la correction vient d'être refusée parce qu'une autre l'a
	 * devancée : la saisie gardée reprend alors les figures de la notice
	 * telle qu'elle est devenue, non celles qu'on croyait corriger.
	 */
	private $conflit = false;

	/**
	 * L'adresse du formulaire rempli d'une notice reçue, que la rédaction
	 * ouvre depuis sa fiche pour la corriger en place.
	 *
	 * Ajouter un mot-clé, retirer une période, reprendre une légende :
	 * c'était refaire le Word à la main, sans que la saisie le sache. Le
	 * formulaire de l'auteur sait tout cela, avec ses listes et ses
	 * contrôles ; il suffit qu'il écrive dans la notice au lieu d'en déposer
	 * une autre. Le jeton ne vaut que deux heures, et que pour la personne
	 * qui l'a demandé.
	 */
	public function ouvrir_la_correction( $id ) {
		$page = $this->page_du_formulaire();
		if ( '' === $page ) {
			return '';
		}
		$jeton = strtolower( wp_generate_password( 12, false, false ) );
		set_transient( 'na_reprise_' . $jeton, array(
			'corrige_id'       => (int) $id,
			'vivant'           => true,
			'par_la_redaction' => get_current_user_id(),
		), 2 * HOUR_IN_SECONDS );
		return add_query_arg( 'notice_reprise', $jeton, $page );
	}

	/**
	 * La notice qu'une réserve désigne, si elle peut encore être corrigée :
	 * son identifiant, ou zéro.
	 *
	 * Supprimée ou mise à la corbeille, elle ne se rouvre pas. Ouverte par la
	 * direction, elle ne se corrige que par la même personne, toujours de la
	 * direction : le lien recopié dans un autre navigateur ne donne rien.
	 * Validée, elle ne se corrige plus du tout, ni par un formulaire ouvert
	 * avant la validation : c'est sa version définitive.
	 */
	private function contexte_valide( $garde ) {
		if ( ! is_array( $garde ) || empty( $garde['corrige_id'] ) ) {
			return 0;
		}
		$id   = (int) $garde['corrige_id'];
		$post = get_post( $id );
		if ( ! $post || Notice_Archeomed_File::CPT !== $post->post_type || 'private' !== $post->post_status
			|| ! is_array( get_post_meta( $id, '_na_donnees', true ) ) ) {
			return 0;
		}
		$par = isset( $garde['par_la_redaction'] ) ? (int) $garde['par_la_redaction'] : 0;
		if ( $par > 0 && ( get_current_user_id() !== $par || ! Notice_Archeomed_Relecture::peut_tout() ) ) {
			return 0;
		}
		if ( 'validee' === Notice_Archeomed_Relecture::etat_de( $id ) ) {
			return 0;
		}
		// La campagne close, les notices sont en relecture : le lien de
		// l'auteur ne les rouvre plus, ni pour les lire ni pour les écrire.
		// Une correction d'auteur croisant celle d'un coordinateur aurait
		// renvoyé l'un des deux au conflit, et la rubrique relue aurait
		// changé sous les yeux de qui la relit.
		if ( 0 === $par && Notice_Archeomed_Relecture::campagne_close() ) {
			return 0;
		}
		return $id;
	}

	/**
	 * La notice telle qu'elle est aujourd'hui, mise à la forme d'une saisie
	 * gardée : le formulaire se remplit d'elle comme d'une reprise.
	 *
	 * Les figures n'y sont pas sous leurs champs ordinaires, qui attendent un
	 * fichier redéposé : elles ont leurs fichiers sur le serveur, et viennent
	 * à part, sous « gardees », chacune avec ses textes.
	 */
	private function saisie_pour_le_formulaire( $id, $garde, $jeton ) {
		$d      = get_post_meta( $id, '_na_donnees', true );
		$saisie = array();
		foreach ( array(
			'rubrique_principale', 'rubrique_matiere', 'renvoi_1', 'renvoi_2',
			'resp_prenom', 'resp_nom', 'resp_email', 'resp_inst', 'deposant_prenom', 'deposant_nom', 'deposant_email',
			'coresp_prenom', 'coresp_nom', 'coresp_email', 'coresp_inst',
			'coauteur_prenom', 'coauteur_nom', 'coauteur_email', 'coauteur_inst',
			'departement', 'lieu_dit', 'lieu_dit_forme', 'annee',
			'num_autorisation', 'id_patriarche', 'rapport_lien', 'donnees_lien', 'originaux_lien', 'originaux_jusqua', 'commentaires',
			'texte_notice',
		) as $champ ) {
			if ( isset( $d[ $champ ] ) && is_scalar( $d[ $champ ] ) ) {
				$saisie[ $champ ] = (string) $d[ $champ ];
			}
		}
		// Les natures par leurs libellés : la chaîne jointe se couperait mal
		// sur un libellé qui porterait une virgule.
		$natures = array();
		if ( ! empty( $d['nature_items'] ) && is_array( $d['nature_items'] ) ) {
			foreach ( $d['nature_items'] as $item ) {
				if ( is_array( $item ) && isset( $item['label'] ) ) {
					$natures[] = (string) $item['label'];
				}
			}
		} elseif ( ! empty( $d['nature'] ) ) {
			$natures = array_map( 'trim', explode( ', ', (string) $d['nature'] ) );
		}
		$saisie['nature'] = $natures;
		// Les lieux tels qu'ils ont été saisis, non redressés comme à la
		// sortie : la correction les réécrira.
		$lieux = ( ! empty( $d['lieux'] ) && is_array( $d['lieux'] ) ) ? $d['lieux']
			: ( ! empty( $d['commune'] ) ? array( array( 'nom' => (string) $d['commune'],
				'ark' => isset( $d['commune_ark'] ) ? (string) $d['commune_ark'] : '' ) ) : array() );
		$saisie['commune']     = array();
		$saisie['commune_ark'] = array();
		foreach ( $lieux as $lieu ) {
			$saisie['commune'][]     = isset( $lieu['nom'] ) ? (string) $lieu['nom'] : '';
			$saisie['commune_ark'][] = isset( $lieu['ark'] ) ? (string) $lieu['ark'] : '';
		}
		$saisie['organisme'] = array_values( $this->organismes_de( $d ) );
		foreach ( array( 'periods', 'subjects', 'places' ) as $liste ) {
			$items = isset( $d[ 'pactols_' . $liste . '_items' ] ) && is_array( $d[ 'pactols_' . $liste . '_items' ] )
				? array_values( $d[ 'pactols_' . $liste . '_items' ] ) : array();
			$saisie[ 'pactols_' . $liste ] = empty( $items ) ? '' : (string) wp_json_encode( $items );
		}
		$saisie['corrige_id']       = (int) $id;
		$saisie['corrige_version']  = $this->file()->version_de( $id );
		$saisie['gardees']          = $this->figures_gardees_de( $id, $d );
		$saisie['par_la_redaction'] = isset( $garde['par_la_redaction'] ) ? (int) $garde['par_la_redaction'] : 0;
		$saisie['jeton_vivant']     = (string) $jeton;
		return $saisie;
	}

	/**
	 * Les figures d'une notice, telles que le formulaire les montre pour
	 * qu'on les garde, les remplace ou les retire : leurs textes, le nom du
	 * fichier déposé et la présence d'une autorisation.
	 */
	private function figures_gardees_de( $id, $d ) {
		$fichiers      = self::figures_d_avant( $id );
		$fichiers      = ! empty( $fichiers ) ? $fichiers : self::illustrations_rangees( $id );
		$autorisations = get_post_meta( $id, '_na_autorisations', true );
		$autorisations = is_array( $autorisations ) ? $autorisations : array();
		$gardees       = array();
		foreach ( ( isset( $d['illustrations'] ) && is_array( $d['illustrations'] ) ) ? $d['illustrations'] : array() as $item ) {
			if ( ! is_array( $item ) || empty( $item['rang'] ) ) {
				continue;
			}
			$k        = (int) $item['rang'];
			$texte    = function ( $cle ) use ( $item ) {
				return isset( $item[ $cle ] ) && is_scalar( $item[ $cle ] ) ? (string) $item[ $cle ] : '';
			};
			$fichier  = isset( $fichiers[ $k - 1 ] ) ? (string) $fichiers[ $k - 1 ] : '';
			$alt      = $texte( 'alt' );
			$gardees[] = array(
				'k'            => $k,
				'titre'        => $texte( 'titre' ),
				'legende'      => $texte( 'legende' ),
				'credits'      => $texte( 'credits' ),
				// Le champ unique du formulaire : le texte alternatif, puis la
				// description détaillée à la ligne.
				'alt'          => '' !== $texte( 'description' ) ? $alt . "\n" . $texte( 'description' ) : $alt,
				// La mise en forme du titre, de la légende et de la
				// description, que les éditeurs du formulaire reprennent.
				'titre_forme'       => $texte( 'titre_forme' ),
				'legende_forme'     => $texte( 'legende_forme' ),
				'description_forme' => $texte( 'description_forme' ),
				'type'         => $texte( 'type' ),
				'nom'          => '' !== $texte( 'fichier_depose' ) ? $texte( 'fichier_depose' )
					: ( '' !== $fichier ? preg_replace( '/^\d+-notice-archeomed-(garde-)?/', '', basename( $fichier ) ) : '' ),
				'a_fichier'    => Notice_Archeomed_Rangement::present( $id, $fichier ),
				'autorisation' => ! empty( $autorisations[ $k ] ) || ! empty( $item['autorisation'] ),
				// Envoyée à part : le formulaire redonne son lien à corriger.
				'a_part'       => self::figure_a_part( $item ),
			);
		}
		return $gardees;
	}

	/**
	 * La notice que le formulaire posté corrige en place, avec ce que la
	 * réserve en dit ; null pour un dépôt ordinaire.
	 *
	 * L'identifiant ne vient jamais du formulaire : un champ caché se forge.
	 * Seul le jeton y passe, et c'est la réserve, écrite par nous, qui dit
	 * quelle notice il ouvre.
	 */
	private function contexte_poste() {
		static $lu = false, $contexte = null;
		if ( $lu ) {
			return $contexte;
		}
		$lu    = true;
		$jeton = $this->jeton_poste();
		if ( '' === $jeton ) {
			return null;
		}
		$garde = get_transient( 'na_reprise_' . $jeton );
		$id    = $this->contexte_valide( $garde );
		if ( ! $id ) {
			return null;
		}
		$contexte = array(
			'id'               => $id,
			'jeton'            => $jeton,
			'vivant'           => ! empty( $garde['vivant'] ),
			'jeton_vivant'     => ! empty( $garde['vivant'] ) ? $jeton
				: ( isset( $garde['jeton_vivant'] ) ? sanitize_key( (string) $garde['jeton_vivant'] ) : '' ),
			'par_la_redaction' => isset( $garde['par_la_redaction'] ) ? (int) $garde['par_la_redaction'] : 0,
			'gardees'          => isset( $garde['gardees'] ) && is_array( $garde['gardees'] ) ? $garde['gardees'] : null,
			'version'          => isset( $_POST['corrige_version'] ) && is_string( $_POST['corrige_version'] )
				? absint( $_POST['corrige_version'] ) : 0,
		);
		return $contexte;
	}

	/**
	 * Le formulaire posté corrigeait une notice en place, mais son lien ne
	 * l'ouvre plus : il a expiré, ou la notice a été retirée. Le champ de
	 * version le dit — posé par toute correction, et par la saisie qui en
	 * revient périmée.
	 */
	private function correction_sans_lien() {
		return null === $this->contexte_poste() && isset( $_POST['corrige_version'] )
			&& is_string( $_POST['corrige_version'] ) && '' !== $_POST['corrige_version'];
	}

	/**
	 * Fait suivre à une saisie refusée la notice qu'elle corrigeait en
	 * place : au retour, le formulaire la corrige encore, ses figures
	 * gardées à leur place, avec ce qu'on venait d'en écrire.
	 */
	private function reporter_le_contexte( $garde ) {
		$contexte = $this->contexte_poste();
		if ( null === $contexte ) {
			return $garde;
		}
		$id = $contexte['id'];
		$garde['corrige_id']       = $id;
		$garde['par_la_redaction'] = $contexte['par_la_redaction'];
		$garde['jeton_vivant']     = $contexte['jeton_vivant'];
		if ( $this->conflit ) {
			// Une autre correction est passée : on repart de la notice
			// telle qu'elle est, et de sa version.
			$garde['corrige_version'] = $this->file()->version_de( $id );
			$garde['gardees']         = $this->figures_gardees_de( $id, get_post_meta( $id, '_na_donnees', true ) );
			return $garde;
		}
		$garde['corrige_version'] = $contexte['version'];
		$gardees = null !== $contexte['gardees'] ? $contexte['gardees']
			: $this->figures_gardees_de( $id, get_post_meta( $id, '_na_donnees', true ) );
		// Ce qu'on venait d'écrire pour chaque figure gardée recouvre ce que
		// la notice en disait.
		foreach ( $gardees as $i => $figure ) {
			$k = (int) $figure['k'];
			foreach ( array( 'titre', 'legende', 'credits', 'alt', 'type' ) as $champ ) {
				$valeur = $this->garde_postee( $champ, $k );
				if ( null !== $valeur ) {
					$gardees[ $i ][ $champ ] = sanitize_textarea_field( $this->limit_string( $valeur, 5000 ) );
				}
			}
			// La mise en forme revient avec, réécrite : rien que les trois
			// marques et le texte. Elle se raccorde au texte à l'envoi.
			foreach ( array_keys( Notice_Archeomed_Forme::CLEFS ) as $champ ) {
				$valeur = $this->garde_postee( $champ, $k );
				if ( null !== $valeur ) {
					$gardees[ $i ][ $champ ] = Notice_Archeomed_Forme::propre( $valeur );
				}
			}
			$action = $this->garde_postee( 'action', $k );
			if ( null !== $action ) {
				// Le choix revient tel quel. Un fichier de remplacement ne revient
				// pas plus qu'un autre : « Remplacer » revient sans lui, et le
				// formulaire demandera de le choisir de nouveau, plutôt que de laisser
				// croire la figure changée.
				$action = sanitize_key( $action );
				$gardees[ $i ]['action'] = in_array( $action, array( 'remplacer', 'retirer' ), true ) ? $action : 'garder';
			}
		}
		$garde['gardees'] = $gardees;
		return $garde;
	}

	/**
	 * Le lien, la date et le nom du fichier d'une figure gardée envoyée à
	 * part, tels que le formulaire les renvoie : un lien éteint se remplace
	 * sans retirer la figure. Un champ absent garde ce que la notice disait.
	 */
	private function a_part_de_la_garde( $k, $a_part ) {
		$a_part = is_array( $a_part ) ? $a_part : array();
		$a_part = array_merge( array( 'lien' => '', 'jusqua' => '', 'fichier' => '' ), $a_part );
		$lien   = $this->garde_postee( 'lien', $k );
		if ( null !== $lien ) {
			$a_part['lien'] = esc_url_raw( $this->limit_string( trim( $lien ), 500 ), array( 'http', 'https' ) );
		}
		$date = $this->garde_postee( 'jusqua', $k );
		if ( null !== $date ) {
			$date             = trim( $date );
			$a_part['jusqua'] = self::date_valide( $date ) ? $date : '';
		}
		$nom = $this->garde_postee( 'nom_envoi', $k );
		if ( null !== $nom ) {
			$a_part['fichier'] = sanitize_text_field( $this->limit_string( trim( $nom ), 255 ) );
		}
		return $a_part;
	}

	/**
	 * Ce que le formulaire a posté pour la figure gardée de rang $k, sous
	 * « garde_$champ[$k] » : une chaîne, ou null s'il n'a rien envoyé.
	 */
	private function garde_postee( $champ, $k ) {
		$cle = 'garde_' . $champ;
		return ( isset( $_POST[ $cle ] ) && is_array( $_POST[ $cle ] )
			&& isset( $_POST[ $cle ][ $k ] ) && is_string( $_POST[ $cle ][ $k ] ) )
			? wp_unslash( $_POST[ $cle ][ $k ] ) : null;
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

	/**
	 * Ce que le formulaire doit savoir pour convertir un TIFF lui-même.
	 *
	 * « imagick » dit si le serveur lit le TIFF et le PDF : sinon, le navigateur
	 * convertit le TIFF et le PDF en deux JPEG (la haute et la basse définition). Les mesures sont celles des réglages : la haute
	 * définition tient dans la norme des photographies (10 × 15 cm à 300 ppp,
	 * sans agrandir), la basse dans son côté et son poids maximaux.
	 */
	public function reglages_de_conversion() {
		return array(
			'imagick'    => Notice_Archeomed_Paquet::imagick_disponible(),
			// Le lecteur de PDF (pdf.js) ne se charge que si un auteur choisit un PDF.
			'pdfjs'      => Notice_Archeomed_Paquet::imagick_disponible() ? '' : plugins_url( 'assets/pdfjs/', __FILE__ ),
			// Le même, pour la seule vignette de la carte quand le serveur
			// convertit : chargé au premier PDF choisi, jamais avant.
			'lecteurPdf' => plugins_url( 'assets/pdfjs/', __FILE__ ),
			// Le lecteur de TIFF, chargé à la demande quand Imagick est là : un
			// TIFF trop lourd se passe encore en JPEG dans le navigateur.
			'utif'       => array(
				add_query_arg( 'ver', '2.1.0', plugins_url( 'assets/utif/pako_inflate.min.js', __FILE__ ) ),
				add_query_arg( 'ver', '3.1.0', plugins_url( 'assets/utif/UTIF.js', __FILE__ ) ),
			),
			// Little CMS (lcms-wasm 1.0.5), chargé au premier TIFF qui porte
			// un profil de couleur : les autres n'en ont pas l'usage.
			'lcms'       => plugins_url( 'assets/lcms/', __FILE__ ),
			'pixelsMax'  => 36000000,
			'hr'         => array(
				'dpi'       => (int) Notice_Archeomed_Settings::get( 'hr_photo_dpi' ),
				'largeurCm' => (float) Notice_Archeomed_Normes::valeur( 'photo_largeur_cm' ),
				'hauteurCm' => (float) Notice_Archeomed_Normes::valeur( 'photo_hauteur_cm' ),
				'qualite'   => (int) Notice_Archeomed_Settings::get( 'hr_photo_qualite' ),
			),
			'br'         => array(
				'cote'      => (int) Notice_Archeomed_Settings::get( 'br_largeur' ),
				'dpi'       => (int) Notice_Archeomed_Settings::get( 'br_dpi' ),
				'poidsKo'   => (int) Notice_Archeomed_Settings::get( 'br_poids' ),
				'tolerance' => (int) Notice_Archeomed_Settings::get( 'br_tolerance' ),
			),
		);
	}

	public function enqueue_assets() {
		if ( ! $this->page_porte_le_formulaire() ) {
			return;
		}
		// Le décodeur de TIFF ne se charge que si le serveur ne sait pas lire
		// les TIFF : sinon, ces 80 Ko n'auraient aucun usage.
		if ( ! Notice_Archeomed_Paquet::imagick_disponible() ) {
			wp_enqueue_script( 'na-pako', plugins_url( 'assets/utif/pako_inflate.min.js', __FILE__ ), array(), '2.1.0', true );
			wp_enqueue_script( 'na-utif', plugins_url( 'assets/utif/UTIF.js', __FILE__ ), array( 'na-pako' ), '3.1.0', true );
		}
		// L'éditeur est servi par le site lui-même (assets/quill, version 2.0.3) :
		// plus de dépendance à un CDN qui, en tombant, rendait le dépôt
		// impossible, et plus d'adresse IP de visiteur envoyée à un tiers.
		wp_enqueue_style( 'quill-snow', plugins_url( 'assets/quill/quill.snow.css', __FILE__ ), array(), '2.0.3' );
		wp_enqueue_script( 'quill', plugins_url( 'assets/quill/quill.js', __FILE__ ), array(), '2.0.3', true );
		if ( $this->protection_utilise_turnstile() && $this->turnstile_site_key_is_set() ) {
			wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
		}
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
	 * Une case de la nature est-elle à cocher, d'après la saisie reprise ?
	 *
	 * La reprise d'un refus garde les cases telles qu'elles ont été
	 * envoyées ; celle d'une notice à corriger, les termes gardés, qui
	 * reviennent ici à leur type et à leur cadre.
	 */
	private function repris_nature( $champ, $valeur ) {
		$garde = $this->reprise();
		$etat  = $this->decomposer_les_natures( isset( $garde['nature'] ) ? (array) $garde['nature'] : array() );
		$liste = 'cadre' === $champ
			? array_merge( $etat['cadres'], isset( $garde['cadre'] ) ? (array) $garde['cadre'] : array() )
			: $etat['natures'];
		return in_array( $valeur, $liste, true ) ? ' checked' : '';
	}

	/**
	 * Le groupe « Nature de l'opération » : le cadre en tête, puis les types
	 * d'opération, les prospections et les projets collectifs.
	 *
	 * La rédaction a voulu le cadre à part, juste sous la consigne : il vaut
	 * pour tous les types, et une fouille préventive n'est plus une case
	 * parmi six. Les cases gardent leur nom, « nature[] », et le cadre le
	 * sien, « cadre[] ».
	 */
	private function natures_html() {
		$html  = '<fieldset class="na-sous-groupe na-champ" id="na-nature" aria-describedby="na-nature-aide">';
		$html .= '<legend>Nature de l’opération <span class="na-requis" aria-hidden="true">*</span></legend>';
		$html .= '<p class="na-help" id="na-nature-aide">Cochez toutes celles qui s’appliquent.</p>';
		$html .= '<div class="na-checkboxes na-cadres" role="group" aria-label="Cadre de l’opération">';
		$n = 0;
		foreach ( $this->cadres_proposes() as $cadre ) {
			++$n;
			$html .= '<label class="na-check" for="na-cadre-' . $n . '">'
				. '<input type="checkbox" id="na-cadre-' . $n . '" name="cadre[]" value="'
				. esc_attr( $cadre ) . '"' . $this->repris_nature( 'cadre', $cadre ) . '> '
				. esc_html( $cadre ) . '</label>';
		}
		$html .= '</div>';
		$html .= '<div class="na-familles na-ancre">';
		$n = 0;
		foreach ( $this->groupes_de_natures() as $groupe => $liste ) {
			$html .= '<fieldset class="na-famille"><legend>' . esc_html( $groupe ) . '</legend>';
			$html .= '<div class="na-checkboxes">';
			foreach ( $liste as $opt ) {
				++$n;
				$html .= '<label class="na-check" for="na-nature-' . $n . '">'
					. '<input type="checkbox" id="na-nature-' . $n . '" name="nature[]" value="'
					. esc_attr( $opt ) . '"' . $this->repris_nature( 'nature', $opt ) . '> '
					. esc_html( isset( $this->libelles_des_natures[ $opt ] ) ? $this->libelles_des_natures[ $opt ] : $opt )
					. '</label>';
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
	 *
	 * Périodes et sujets demandent un terme au moins, libre ou du thésaurus ;
	 * le bloc est alors un « na-champ », où se pose le message d'erreur.
	 */
	private function pactols_categorie_html( $type, $titre, $aide, $cherche, $cible, $requis = false ) {
		$id    = 'na-kw-' . $type;
		$html  = '<fieldset class="na-kw' . ( $requis ? ' na-champ' : '' ) . '" id="' . esc_attr( $id ) . '" data-type="' . esc_attr( $type )
			. '" data-cible="' . esc_attr( $cible ) . '">';
		$html .= '<legend>' . esc_html( $titre ) . ( $requis ? ' <span class="na-requis" aria-hidden="true">*</span>' : '' ) . '</legend>';
		$html .= '<p class="na-help" id="' . esc_attr( $id ) . '-aide">' . $aide . '</p>';
		$html .= '<ul class="na-jetons" id="' . esc_attr( $id ) . '-jetons" aria-label="'
			. esc_attr( $titre . ' retenus' ) . '" hidden></ul>';
		$html .= '<div class="na-combo na-ancre">';
		$html .= '<label class="na-sr" for="' . esc_attr( $id ) . '-champ">' . esc_html( $cherche ) . '</label>';
		$html .= '<input type="text" id="' . esc_attr( $id ) . '-champ" class="na-kw-champ" autocomplete="off"'
			. ' spellcheck="false" enterkeyhint="search" placeholder="Rechercher…"'
			. ( $requis ? ' aria-required="true"' : '' )
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
			'piege'           => 'Un champ invisible du formulaire a été rempli, sans doute par votre navigateur ou votre gestionnaire de mots de passe. Renvoyez le formulaire sans les laisser remplir la page.',
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
			'conflit'         => 'La notice a été corrigée par ailleurs pendant que vous la relisiez : votre correction n’est pas enregistrée. Le formulaire reprend votre saisie, mais les figures sont celles de la notice telle qu’elle est maintenant. Vérifiez-les, puis renvoyez.',
			'occupee'         => 'La notice part en ce moment vers la rédaction : votre correction n’est pas enregistrée. Votre saisie est conservée ; renvoyez-la dans une minute.',
			'perimee'         => 'Ce lien de correction n’est plus valable : il a expiré, ou la notice a été retirée. Rien n’a été enregistré, et aucun nouveau dépôt n’a été fait. Votre saisie est reprise ci-dessous pour que vous puissiez la copier ; ce formulaire ne l’enverra plus.',
			'close'           => 'La campagne est close : les notices sont en relecture, et ce lien ne les corrige plus. Rien n’a été enregistré, et aucun nouveau dépôt n’a été fait. Votre saisie est reprise ci-dessous pour que vous puissiez la copier ; ce formulaire ne l’enverra plus.',
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
	 * Le refus d'une correction qu'une autre a devancée, avec ce que dit
	 * l'historique de la notice : quand, par qui, sur quoi.
	 *
	 * « Corrigée par ailleurs », sans plus, laissait l'auteur chercher ce
	 * qui avait bougé dans toute la notice. Le nom de qui a corrigé ne se
	 * montre qu'à la rédaction ; l'auteur lit « la rédaction ».
	 */
	private function message_de_conflit( $id, $pour_la_redaction ) {
		$histoire = get_post_meta( $id, '_na_versions', true );
		$derniere = is_array( $histoire ) && ! empty( $histoire ) ? end( $histoire ) : array();
		$precis   = array();
		$quand    = isset( $derniere['le'] ) ? Notice_Archeomed_File::date_lisible( $derniere['le'] ) : '';
		if ( '' !== $quand ) {
			$precis[] = 'le ' . $quand;
		}
		$par = isset( $derniere['par'] ) ? (string) $derniere['par'] : '';
		if ( '' !== $par ) {
			$precis[] = 'l’auteur' === $par ? 'depuis le lien de correction de l’auteur'
				: 'par ' . ( $pour_la_redaction ? $par : 'la rédaction' );
		}
		$champs = isset( $derniere['champs'] ) ? array_filter( array_map( 'strval', (array) $derniere['champs'] ) ) : array();
		$dit    = implode( ', ', $precis ) . ( ! empty( $champs ) ? ( empty( $precis ) ? '' : ' : ' ) . implode( ', ', $champs ) : '' );
		return $this->typo( 'La notice a été corrigée par ailleurs pendant que vous la relisiez'
			. ( '' !== $dit ? ' (' . esc_html( $dit ) . ')' : '' )
			. ' : votre correction n’est pas enregistrée. Le formulaire garde votre saisie ; les figures sont celles '
			. 'de la notice telle qu’elle est maintenant. Relisez les points changés par l’autre correction, puis renvoyez.' );
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
		$table = array(
			'commune'             => array( 'na-lieu-1', 'Indiquez la commune ou le territoire de l’opération' ),
			'departement'         => array( 'na-departement', 'Indiquez ce qui figure entre parenthèses, le plus souvent le département' ),
			'lieu_dit'            => array( 'na-lieu-dit', 'Indiquez un lieu-dit, une adresse ou le nom du projet' ),
			'rubrique_principale' => array( 'na-rubrique', 'Choisissez la rubrique principale de la notice' ),
			'rubrique_matiere'    => array( 'na-matiere', 'Choisissez la sous-rubrique de l’installation artisanale (A, B ou C)' ),
			'nature'              => array( 'na-nature-1', 'Cochez au moins un type d’opération, une prospection ou un projet collectif de recherche' ),
			'annee'               => array( 'na-annee', 'Indiquez l’année de l’opération, par exemple 2024' ),
			'organisme'           => array( 'na-organisme-1', 'Indiquez l’opérateur ou l’organisme porteur de l’opération' ),
			'id_patriarche'       => array( 'na-patriarche', 'Indiquez le code d’opération Patriarche (n° d’OA), que le service régional de l’archéologie peut vous donner' ),
			'rapport_lien'        => array( 'na-rapport', 'Saisissez l’adresse complète du rapport, par exemple https://hal.science/hal-01234567' ),
			'donnees_lien'        => array( 'na-donnees', 'Saisissez l’adresse complète du jeu de données, par exemple https://doi.org/10.34847/nkl.abcd1234' ),
			'resp_prenom'         => array( 'na-resp-prenom', 'Indiquez le prénom du responsable d’opération' ),
			'resp_nom'            => array( 'na-resp-nom', 'Indiquez le nom du responsable d’opération' ),
			'resp_email'          => array( 'na-resp-email', 'Indiquez l’adresse électronique du responsable d’opération' ),
			'resp_inst'           => array( 'na-resp-inst', 'Indiquez l’institution de rattachement du responsable d’opération' ),
			'email_forme'         => array( 'na-resp-email', 'Saisissez une adresse électronique complète pour le responsable d’opération, par exemple prenom.nom@exemple.fr' ),
			'coresp_email'        => array( 'na-coresp-email', 'Saisissez une adresse électronique complète pour le co-responsable, par exemple prenom.nom@exemple.fr' ),
			'coauteur_email'      => array( 'na-coauteur-email', 'Saisissez une adresse électronique complète pour le co-auteur, par exemple prenom.nom@exemple.fr' ),
			'deposant_email'      => array( 'na-deposant-email', 'Saisissez votre adresse électronique complète, par exemple prenom.nom@exemple.fr, ou laissez ce champ vide' ),
			'pactols_periods'     => array( 'na-kw-period-champ', 'Ajoutez au moins une période, choisie dans la liste ou gardée telle quelle (deux fois Entrée)' ),
			'pactols_subjects'    => array( 'na-kw-subject-champ', 'Ajoutez au moins un sujet, choisi dans la liste ou gardé tel quel (deux fois Entrée)' ),
			'texte'               => array( 'na-texte', 'Saisissez ou collez le texte de la notice' ),
			// Les champs d'une figure ne sont dans la page qu'une fois son
			// fichier choisi : le lien y mène quand il existe.
			'illus_lien_1'        => array( 'na-illus-1-lien', 'Collez le lien de téléchargement de la figure 1, envoyée à part' ),
			'illus_jusqua_1'      => array( 'na-illus-1-jusqua', 'Indiquez jusqu’à quelle date le lien de la figure 1 reste valable (aujourd’hui ou plus tard)' ),
			'illus_titre_1'       => array( 'na-illus-1-titre', 'Écrivez la légende de la figure 1' ),
			'illus_credits_1'     => array( 'na-illus-1-credits', 'Indiquez les crédits de la figure 1, son auteur et le détenteur des droits' ),
			'illus_type_1'        => array( 'na-illus-1-type', 'Choisissez la nature de la figure 1' ),
			'illus_alt_1'         => array( 'na-illus-1-alt', 'Décrivez en une ou deux phrases ce qu’on voit sur la figure 1, pour les lecteurs qui ne voient pas l’image' ),
			'illus_lien_2'        => array( 'na-illus-2-lien', 'Collez le lien de téléchargement de la figure 2, envoyée à part' ),
			'illus_jusqua_2'      => array( 'na-illus-2-jusqua', 'Indiquez jusqu’à quelle date le lien de la figure 2 reste valable (aujourd’hui ou plus tard)' ),
			'illus_titre_2'       => array( 'na-illus-2-titre', 'Écrivez la légende de la figure 2' ),
			'illus_credits_2'     => array( 'na-illus-2-credits', 'Indiquez les crédits de la figure 2, son auteur et le détenteur des droits' ),
			'illus_type_2'        => array( 'na-illus-2-type', 'Choisissez la nature de la figure 2' ),
			'illus_alt_2'         => array( 'na-illus-2-alt', 'Décrivez en une ou deux phrases ce qu’on voit sur la figure 2, pour les lecteurs qui ne voient pas l’image' ),
			'illus_lien_3'        => array( 'na-illus-3-lien', 'Collez le lien de téléchargement de la figure 3, envoyée à part' ),
			'illus_jusqua_3'      => array( 'na-illus-3-jusqua', 'Indiquez jusqu’à quelle date le lien de la figure 3 reste valable (aujourd’hui ou plus tard)' ),
			'illus_titre_3'       => array( 'na-illus-3-titre', 'Écrivez la légende de la figure 3' ),
			'illus_credits_3'     => array( 'na-illus-3-credits', 'Indiquez les crédits de la figure 3, son auteur et le détenteur des droits' ),
			'illus_type_3'        => array( 'na-illus-3-type', 'Choisissez la nature de la figure 3' ),
			'illus_alt_3'         => array( 'na-illus-3-alt', 'Décrivez en une ou deux phrases ce qu’on voit sur la figure 3, pour les lecteurs qui ne voient pas l’image' ),
			'originaux_lien'      => array( 'na-originaux', 'Saisissez l’adresse complète du lien de téléchargement, par exemple https://filesender.renater.fr/…' ),
			'originaux_jusqua'    => array( 'na-originaux-jusqua', 'Indiquez jusqu’à quelle date le lien des originaux reste valable (aujourd’hui ou plus tard)' ),
			'puzzle'              => array( 'na-puzzle-poignee', 'Faites glisser la pièce jusqu’à son encoche' ),
		);
		// Les figures déjà déposées d'une notice qu'on corrige : leur carte
		// vient avant celles des fichiers nouveaux, leurs champs avant les
		// leurs dans la liste.
		$gardees = array();
		for ( $k = 1; $k <= self::MAX_FILES; $k++ ) {
			$gardees[ 'garde_fichier_' . $k ] = array( 'na-garde-' . $k . '-fichier', 'Choisissez le nouveau fichier de la figure déjà déposée ' . $k . ", ou cochez «\u{00A0}La garder\u{00A0}»" );
			$gardees[ 'garde_lien_' . $k ]    = array( 'na-garde-' . $k . '-lien', 'Collez le lien de téléchargement de la figure déjà déposée ' . $k . ', envoyée à part' );
			$gardees[ 'garde_jusqua_' . $k ]  = array( 'na-garde-' . $k . '-jusqua', 'Indiquez jusqu’à quelle date le lien de la figure déjà déposée ' . $k . ' reste valable' );
			$gardees[ 'garde_titre_' . $k ]   = array( 'na-garde-' . $k . '-titre', 'Écrivez la légende de la figure déjà déposée ' . $k );
			$gardees[ 'garde_credits_' . $k ] = array( 'na-garde-' . $k . '-credits', 'Indiquez les crédits de la figure déjà déposée ' . $k . ', son auteur et le détenteur des droits' );
			$gardees[ 'garde_type_' . $k ]    = array( 'na-garde-' . $k . '-type', 'Choisissez la nature de la figure déjà déposée ' . $k );
			$gardees[ 'garde_alt_' . $k ]     = array( 'na-garde-' . $k . '-alt', 'Décrivez en une ou deux phrases ce qu’on voit sur la figure déjà déposée ' . $k . ', pour les lecteurs qui ne voient pas l’image' );
		}
		$avant = array_search( 'illus_lien_1', array_keys( $table ), true );
		return array_slice( $table, 0, $avant, true ) + $gardees + array_slice( $table, $avant, null, true );
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
			// Le refus d'une correction devancée dit déjà que la saisie est
			// gardée : la phrase ordinaire le répétait juste en dessous.
			$garde_dite = false;
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
			} elseif ( 'conflit' === $raison && ! empty( $reprise['corrige_id'] ) ) {
				$corps     .= '<p>' . $this->message_de_conflit( (int) $reprise['corrige_id'], ! empty( $reprise['par_la_redaction'] ) ) . '</p>';
				$garde_dite = true;
			} else {
				$corps .= '<p>' . wp_kses_post( $this->message_derreur( $raison ) ) . '</p>';
			}
			if ( ! empty( $reprise ) && empty( $reprise['perimee'] ) && ! $garde_dite ) {
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
		// La correction en place : la notice reste la même, sous la même
		// référence. La rédaction ne prévient personne ; l'auteur, lui, doit
		// savoir que la rédaction sera avertie.
		if ( ! empty( $reprise['corrige_id'] ) ) {
			$ref = (string) get_post_meta( (int) $reprise['corrige_id'], '_na_reference', true );
			$qui = '' !== $ref ? 'la notice ' . esc_html( $ref ) : 'cette notice';
			$html .= '<div class="na-message na-avis"><p>' . $this->typo( ! empty( $reprise['par_la_redaction'] )
				? '<strong>Vous corrigez ' . $qui . ' pour la rédaction.</strong> '
					. 'Le formulaire montre la notice telle qu’elle est enregistrée. En le renvoyant, '
					. 'vous la remplacez : même référence, Word et aperçus refaits. Aucun courriel ne part, '
					. 'et la notice garde la trace de votre correction.'
				: '<strong>Vous corrigez ' . $qui . '.</strong> '
					. 'Le formulaire montre la notice telle que la rédaction l’a reçue, figures comprises : '
					. 'gardez-les, remplacez-les ou retirez-les une à une. En le renvoyant, vous mettez à jour '
					. 'la même notice, sans nouveau dépôt ; la rédaction reçoit la notice corrigée, '
					. 'avec la liste de ce qui a changé.' ) . '</p></div>';
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
		$corrigee  = isset( $_GET['notice_corrigee'] ) && '1' === $_GET['notice_corrigee'];
		$sans_fichiers = isset( $_GET['notice_sans_fichiers'] ) && '1' === $_GET['notice_sans_fichiers'];
		$autre     = remove_query_arg( array( 'notice_envoyee', 'notice_erreur', 'notice_champ',
			'notice_champs', 'notice_ref', 'notice_reprise', 'notice_corrigee', 'notice_sans_fichiers' ) );
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
			<h2 id="na-fin-titre"><?php echo $corrigee ? 'Votre correction est enregistrée' : 'Votre notice est déposée'; ?></h2>
			<?php if ( '' !== $ref ) : ?>
				<p class="na-fin-ref">Référence&nbsp;: <strong><?php echo esc_html( $ref ); ?></strong></p>
			<?php endif; ?>
			<?php if ( $corrigee ) : ?>
			<p>La notice garde sa référence&nbsp;: c’est elle qui a été mise à jour, aucun second dépôt n’a été fait. La rédaction reçoit dans quelques minutes la notice corrigée, avec la liste de ce qui a changé. Le lien de correction reste valable un mois à compter d’aujourd’hui&nbsp;: vous pouvez y revenir.</p>
			<?php else : ?>
			<p>Une copie part dans quelques minutes à l’adresse du responsable d’opération, et à celles des autres personnes dont vous avez donné l’adresse. Elle contient un lien pour corriger la notice pendant un mois. Si vous avez déposé pour le responsable sans donner votre adresse, c’est lui seul qui la reçoit&nbsp;: demandez-lui de vous la transmettre.</p>
			<?php endif; ?>
			<?php if ( $sans_fichiers ) : ?>
			<h3>Vos fichiers ne sont pas arrivés</h3>
			<p>Le site manquait de place&nbsp;: la notice est enregistrée avec ses légendes, mais sans les fichiers joints. Envoyez vos figures, et leurs autorisations de reproduction s’il y en a, à
				<?php if ( '' !== $redaction ) : ?>
					<a href="mailto:<?php echo esc_attr( antispambot( $redaction ) ); ?>"><?php echo esc_html( antispambot( $redaction ) ); ?></a><?php else : ?>la rédaction de la revue<?php endif; ?><?php echo '' !== $ref ? ' en citant la référence' : ''; ?>, par un service de transfert si elles sont lourdes. Vous n’avez rien d’autre à refaire.</p>
			<?php endif; ?>
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
		return sprintf( 'Photographies : %1$s × %2$s cm au moins à %3$s ppp, soit %4$s × %5$s pixels. Plans, cartes, relevés et graphiques : de préférence en PDF vectoriel, exporté de votre logiciel de dessin ; sinon %6$s ppp.',
			self::entier( Notice_Archeomed_Normes::valeur( 'photo_largeur_cm' ) ),
			self::entier( Notice_Archeomed_Normes::valeur( 'photo_hauteur_cm' ) ),
			self::entier( Notice_Archeomed_Normes::valeur( 'photo_ppp' ) ),
			self::entier( $petit ), self::entier( $grand ),
			self::entier( Notice_Archeomed_Normes::valeur( 'trait_ppp' ) ) );
	}

	/**
	 * Ce que le shortcode pose dans la page : le formulaire, ses scripts
	 * renvoyés au pied de page.
	 *
	 * Le formulaire porte son script en ligne. Dans le contenu d'une page,
	 * un thème à blocs le fait passer par des filtres qui changent une partie
	 * des « && » en « &#038;&#038; » : le script entier tombait en erreur de
	 * syntaxe, et le formulaire restait inerte — ni avis, ni figures, ni
	 * vérification. Au pied de page, aucun filtre de contenu ne le touche ;
	 * il s'exécute encore avant « DOMContentLoaded », qu'il attend.
	 */
	public function afficher_le_formulaire() {
		$html = (string) $this->render_form();
		if ( did_action( 'wp_footer' ) ) {
			return $html;   // trop tard pour le pied de page : le script reste en place
		}
		$html = (string) preg_replace_callback( '#<script\b[^>]*>.*?</script>#is', function ( $m ) {
			$this->scripts_du_pied[] = $m[0];
			return '';
		}, $html );
		if ( ! empty( $this->scripts_du_pied ) && ! has_action( 'wp_footer', array( $this, 'poser_les_scripts_du_pied' ) ) ) {
			add_action( 'wp_footer', array( $this, 'poser_les_scripts_du_pied' ), 25 );
		}
		return $html;
	}

	/** Les scripts du formulaire, au pied de la page, une seule fois. */
	public function poser_les_scripts_du_pied() {
		echo implode( "\n", $this->scripts_du_pied );   // écrits par render_form, déjà échappés
		$this->scripts_du_pied = array();
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
		// La correction en place met à jour la notice déposée ; l'autre, par un
		// lien d'avant, fait un dépôt nouveau qui remplace l'ancien.
		$en_place         = ! empty( $reprise['corrige_id'] );
		$par_la_redaction = $en_place && ! empty( $reprise['par_la_redaction'] );
		// Une correction revenue sans lien valable : la saisie se montre, pour
		// être copiée, mais ne repart pas.
		$perimee          = ! $en_place && ! empty( $reprise['perimee'] );
		$correction       = ! empty( $reprise['remplace'] ) || $en_place || $perimee;
		$gardees          = ( $en_place && isset( $reprise['gardees'] ) && is_array( $reprise['gardees'] ) ) ? $reprise['gardees'] : array();
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
				--na-anneau: 3px solid var(--na-encre);
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
			.na-form#na-form .na-deposant { margin-top: var(--na-e5); padding-top: var(--na-e4); border-top: 1px solid var(--na-filet-doux); }
			.na-form#na-form .na-deposant-titre { margin: 0 0 var(--na-e1); font-weight: 600; }
			.na-form#na-form label, .na-form#na-form .na-libelle { display: block; margin: 0 0 var(--na-e1); padding: 0;
				font-size: var(--na-t-m); font-weight: 600; line-height: 1.35; color: var(--na-encre);
				text-transform: none; letter-spacing: normal; }
			.na-form#na-form .na-facultatif { font-weight: 400; color: var(--na-sourd); }
			.na-form#na-form .na-requis { font-weight: 700; color: var(--na-erreur); }
			.na-form#na-form .na-help { max-width: var(--na-mesure); margin: 0 0 var(--na-e2);
				font-size: var(--na-t-s); font-weight: 400; line-height: 1.5; color: var(--na-sourd); }
			.na-form#na-form .na-help strong { font-weight: 600; color: var(--na-encre); }
			.na-form#na-form input[type=text], .na-form#na-form input[type=email], .na-form#na-form input[type=url],
			.na-form#na-form input[type=date], .na-form#na-form select, .na-form#na-form textarea {
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
			.na-form#na-form input[type=url]:hover, .na-form#na-form input[type=date]:hover,
			.na-form#na-form select:hover, .na-form#na-form textarea:hover { border-color: var(--na-encre); }
			.na-form#na-form input[type=text]:focus, .na-form#na-form input[type=email]:focus,
			.na-form#na-form input[type=url]:focus, .na-form#na-form input[type=date]:focus,
			.na-form#na-form select:focus, .na-form#na-form textarea:focus {
				border-color: var(--na-accent); outline: var(--na-anneau); outline-offset: 2px; box-shadow: none; color: var(--na-encre); }
			.na-form#na-form a:focus-visible { outline: var(--na-anneau); outline-offset: 2px; border-radius: var(--na-r1); }
			.na-form#na-form input[type=date] { max-width: 16rem; }
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
			/* La commune en capitales, comme dans la revue : par le style seul,
			   pour qu'un lecteur d'écran lise le nom et non des lettres. */
			.na-form#na-form .na-apercu-titre .na-apercu-commune { text-transform: uppercase; }

			/* ── Nature de l'opération ── */
			.na-form#na-form .na-familles { display: grid; gap: var(--na-e3); }
			.na-form#na-form .na-cadres { margin-bottom: var(--na-e3); }
			.na-form#na-form .na-famille > legend { margin: 0; font-size: var(--na-t-s); font-weight: 600; color: var(--na-sourd); }
			.na-form#na-form .na-checkboxes { display: grid; gap: 0 var(--na-e5);
				grid-template-columns: repeat(auto-fill, minmax(min(100%, 15rem), 1fr)); }
			.na-form#na-form label.na-check { display: flex; align-items: center; gap: var(--na-e3); min-height: var(--na-cible);
				margin: 0; padding: var(--na-e1) var(--na-e2); font-weight: 400; line-height: 1.35; border-radius: var(--na-r1); cursor: pointer; }
			.na-form#na-form label.na-check:hover { background: var(--na-fond); }
			.na-form#na-form input[type=checkbox] { -webkit-appearance: auto; appearance: auto; flex: none;
				width: 1.5rem; height: 1.5rem; margin: 0; accent-color: var(--na-accent); cursor: pointer; }

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
			.na-form#na-form .ql-toolbar.ql-snow { position: sticky; top: var(--wp-admin--admin-bar--height, 0px); z-index: 5;
				display: flex; flex-wrap: wrap; gap: var(--na-e1); padding: var(--na-e1) var(--na-e2);
				font-family: inherit; background: var(--na-fond); border: 1px solid var(--na-filet);
				border-bottom-color: var(--na-filet-doux); border-radius: var(--na-r1) var(--na-r1) 0 0; }
			/* Le collage se défait quand le thème enveloppe la page dans un bloc
			   à défilement : la barre se fixe alors d'elle-même, le long du texte. */
			.na-form#na-form .ql-toolbar.ql-snow.na-barre-flottante { position: fixed; z-index: 20;
				box-shadow: 0 4px 12px rgba(31, 26, 23, .12); }
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
			.na-form#na-form .ql-toolbar.ql-snow button.na-bouton-titre { width: auto; min-width: 2.25rem; padding: 0 .375rem;
				font-family: var(--na-serif); font-size: var(--na-t-s); font-weight: 700; color: var(--na-encre); }
			.na-form#na-form .ql-toolbar.ql-snow button.na-bouton-titre.ql-active { color: var(--na-accent); }
			.na-form#na-form .ql-editor h3 { margin: 1em 0 .4em; font-family: inherit; font-size: inherit; line-height: inherit; font-weight: 700; letter-spacing: normal; text-transform: none; color: inherit; }
			.na-form#na-form .ql-editor h4 { margin: .9em 0 .3em; font-family: var(--na-serif); font-size: var(--na-t-m); font-weight: 700; font-style: italic; }
			.na-form#na-form .ql-toolbar.ql-snow button.ql-petitescapitales { width: auto; min-width: 2.25rem; padding: 0 .375rem;
				font: inherit; font-size: var(--na-t-m); font-weight: 700; font-variant: small-caps; color: var(--na-encre); }
			.na-form#na-form .ql-toolbar.ql-snow button.ql-petitescapitales:hover,
			.na-form#na-form .ql-toolbar.ql-snow button.ql-petitescapitales.ql-active { color: var(--na-accent); }
			.na-form#na-form #na-editor .ql-editor.ql-blank::before { left: var(--na-e4); right: var(--na-e4); font-style: normal; color: var(--na-indice); }
			.na-form#na-form .na-texte--erreur #na-editor.ql-container.ql-snow,
			.na-form#na-form .na-texte--erreur .ql-toolbar.ql-snow { border-color: var(--na-erreur); }
			/* Le lieu-dit : un champ d'une ligne, sa petite barre au-dessus. */
			.na-form#na-form .na-lieu-dit-riche { background: var(--na-papier); border: 1px solid var(--na-filet); border-radius: var(--na-r1);
				transition: border-color .15s ease; }
			.na-form#na-form .na-lieu-dit-riche:hover { border-color: var(--na-encre); }
			.na-form#na-form .na-lieu-dit-riche:focus-within { border-color: var(--na-accent); outline: var(--na-anneau); outline-offset: 2px; }
			.na-form#na-form .na-lieu-dit-riche.na-lieu-dit-riche--erreur { border-color: var(--na-erreur); box-shadow: 0 0 0 1px var(--na-erreur); }
			.na-form#na-form .na-lieu-dit-riche .ql-toolbar.ql-snow { position: static; padding: 2px var(--na-e1); background: var(--na-fond);
				border: 0; border-bottom: 1px solid var(--na-filet-doux); border-radius: var(--na-r1) var(--na-r1) 0 0; }
			.na-form#na-form .na-lieu-dit-riche .ql-toolbar.ql-snow button { width: 2rem; height: 2rem; padding: .375rem; }
			.na-form#na-form .na-lieu-dit-riche .ql-toolbar.ql-snow button.ql-petitescapitales { width: auto; min-width: 2rem; padding: 0 .375rem; }
			.na-form#na-form .na-lieu-dit-riche .ql-container.ql-snow { height: auto; font-family: inherit; font-size: var(--na-t-m); border: 0; }
			.na-form#na-form .na-lieu-dit-riche .ql-editor { min-height: 0; padding: .5625rem .75rem; line-height: 1.5; color: var(--na-encre); }
			.na-form#na-form .na-lieu-dit-riche .ql-editor:focus { outline: none; }
			.na-form#na-form .na-lieu-dit-riche .ql-editor[aria-invalid="true"] { box-shadow: none; }
			.na-form#na-form .na-lieu-dit-riche .ql-editor p { margin: 0; color: inherit; }
			.na-form#na-form .na-lieu-dit-riche .ql-editor .na-pc { font-variant: small-caps; }
			/* Les textes d'une figure : la même barre ; la légende et le texte
			   pour l'écoute, sur plusieurs lignes. Dans le texte alternatif, la
			   barre s'éteint. */
			.na-form#na-form .na-figure-riche--lignes .ql-editor { min-height: 4.5em; }
			.na-form#na-form .na-figure-riche .na-figure-note { margin: 0; padding: var(--na-e1) .75rem; border-top: 1px solid var(--na-filet-doux); }
			.na-form#na-form .na-figure-riche--inerte .ql-toolbar.ql-snow button { opacity: .4; cursor: not-allowed; }
			.na-form#na-form .na-wordcount { margin-top: var(--na-e2); font-size: var(--na-t-s); color: var(--na-sourd); font-variant-numeric: tabular-nums; }
			.na-form#na-form .na-wordcount.na-out { color: var(--na-avis); font-weight: 600; }

			/* ── Mots-clés Pactols ── */
			/* Trois colonnes quand elles tiennent à l'aise (54 rem), une seule
			   sinon : jamais deux plus une, qui laissait un trou. */
			.na-form#na-form .na-pactols-grid { display: grid; gap: var(--na-e4); margin-top: var(--na-e3);
				grid-template-columns: repeat(auto-fit, minmax(min(100%, max(calc((100% - 2rem) / 3), calc((54rem - 100%) * 999))), 1fr)); }
			.na-form#na-form .na-kw { min-width: 0; padding: var(--na-e4); background: var(--na-fond); border: 1px solid var(--na-filet-doux); border-radius: var(--na-r2); }
			.na-form#na-form .na-pactols-grid > .na-champ { margin-top: 0; }
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
			.na-form#na-form .na-alleger { display: flex; flex-wrap: wrap; gap: var(--na-e2); margin-top: var(--na-e2); }
			.na-form#na-form .na-alleger .na-ajout { margin-top: 0; border-style: solid; }
			.na-form#na-form .na-fichiers-refuses { margin-top: var(--na-e2); padding: var(--na-e2) var(--na-e3); font-size: var(--na-t-s);
				color: var(--na-erreur); background: var(--na-erreur-fond); border-left: 3px solid var(--na-erreur); }
			.na-form#na-form .na-filelist { display: grid; grid-template-columns: minmax(0, 1fr); gap: var(--na-e3); margin-top: var(--na-e3); }
			/* Une carte par figure, qui se détache de la page : fond teinté,
			   filet appuyé, titre en grand. Les champs restent sur fond blanc. */
			.na-form#na-form .na-illus { position: relative; padding: var(--na-e5); background: var(--na-surface);
				border: 1px solid var(--na-filet); border-left: 6px solid var(--na-accent); border-radius: var(--na-r2); }
			.na-form#na-form .na-illus legend { float: left; width: 100%; margin: 0 0 var(--na-e4); padding: 0 5.5rem var(--na-e2) 0;
				font-family: var(--na-serif); font-size: var(--na-t-xl); font-weight: 600; line-height: 1.25;
				border-bottom: 1px solid var(--na-filet-doux); }
			.na-form#na-form .na-illus-fichier { display: block; margin-top: var(--na-e1); font-family: inherit; font-weight: 400;
				font-size: var(--na-t-s); color: var(--na-sourd); overflow-wrap: anywhere; }
			.na-form#na-form .na-illus-partie { margin: 0; font-size: var(--na-t-s); font-weight: 700; letter-spacing: .06em;
				text-transform: uppercase; color: var(--na-sourd); }
			/* Le texte pour qui ne voit pas l'image : un autre travail, encadré
			   et teinté, en fin de carte. */
			.na-form#na-form .na-illus-ecoute { margin-top: var(--na-e3); padding: var(--na-e4); background: var(--na-fond);
				border: 1px solid var(--na-filet); border-radius: var(--na-r2); }
			.na-form#na-form .na-originaux { margin-top: var(--na-e4); }
			.na-form#na-form .na-originaux > summary { cursor: pointer; font-size: var(--na-t-m); font-weight: 600; color: var(--na-accent); }
			.na-form#na-form .na-originaux > summary:focus-visible { outline: var(--na-anneau); outline-offset: 2px; border-radius: var(--na-r1); }
			.na-form#na-form .na-originaux[open] > summary { margin-bottom: var(--na-e3); }
			.na-form#na-form .na-lieu-etat { margin: var(--na-e2) 0 0; padding: var(--na-e2) var(--na-e3); line-height: 1.45; color: var(--na-avis);
				background: var(--na-avis-fond); border-left: 3px solid var(--na-avis-bord); border-radius: 0 var(--na-r1) var(--na-r1) 0; }
			.na-form#na-form .na-illus-ecoute > .na-illus-partie { margin-bottom: var(--na-e3); }
			.na-form#na-form .na-confirmer { margin: var(--na-e3) 0 0; }
			.na-form#na-form .na-confirmer label { display: flex; gap: var(--na-e2); align-items: flex-start; font-size: var(--na-t-m); cursor: pointer; }
			.na-form#na-form .na-illus-type { margin-bottom: var(--na-e4); }
			.na-form#na-form .na-illus-champs .na-illus-type .na-illus-type-conseil { margin: var(--na-e3) 0 0; }
			/* L'anneau de focus déborde de 5 px : sans cet écart, il mordait le libellé au-dessus. */
			.na-form#na-form .na-illus-champs .na-illus-type > label { display: block; margin: 0 0 var(--na-e2); }
			.na-form#na-form .na-illus-type select { margin-top: 0; }
			.na-form#na-form .na-illus-impression { margin: 0 0 var(--na-e3); font-size: var(--na-t-s); }
			.na-form#na-form .na-illus-sans-apercu { margin: 0 0 var(--na-e3); padding: var(--na-e2) var(--na-e3); font-size: var(--na-t-s);
				color: var(--na-sourd); background: var(--na-fond); border: 1px dashed var(--na-filet); border-radius: var(--na-r1); }
			.na-form#na-form .na-illus-type select { max-width: 22rem; }
			.na-form#na-form .na-illus-ecoute > label { font-size: var(--na-t-m); color: var(--na-accent-fonce); }
			.na-form#na-form .na-illus-terme { font-weight: 400; color: var(--na-sourd); }
			.na-form#na-form .na-illus button.na-fileremove { position: absolute; top: var(--na-e3); right: var(--na-e3); margin: 0; }
			.na-form#na-form .na-illus-champs { clear: both; display: grid; gap: var(--na-e3); }
			.na-form#na-form .na-illus-champs label { margin: 0; font-size: var(--na-t-s); }
			.na-form#na-form .na-illus-champs .na-help { margin: 0 0 var(--na-e1); }
			.na-form#na-form .na-illus-champs .na-compteur { margin: var(--na-e1) 0 0; }
			/* La figure envoyée à part n'a pas de vignette : un filet tient sa place. */
			.na-form#na-form .na-illus-a-part { border-left: 3px solid var(--na-accent); padding-left: var(--na-e3); }
			.na-form#na-form .na-exemples { max-width: var(--na-mesure); margin: 0 0 var(--na-e1); font-size: var(--na-t-s); line-height: 1.5; color: var(--na-sourd); }
			.na-form#na-form .na-exemples summary { cursor: pointer; font-weight: 600; color: var(--na-encre); }
			.na-form#na-form .na-exemples p { margin: var(--na-e1) 0 0; }
			.na-form#na-form .na-illus-total { font-size: var(--na-t-s); color: var(--na-sourd); font-variant-numeric: tabular-nums; }
			.na-form#na-form .na-illus-vignette { display: block; max-width: 8rem; max-height: 6rem; width: auto; height: auto;
				margin: 0 0 var(--na-e3); border: 1px solid var(--na-filet-doux); border-radius: var(--na-r1); }
			/* La vignette se clique pour voir l'image en grand. */
			.na-form#na-form button.na-illus-agrandir { display: block; width: auto; min-height: 0; margin: 0 0 var(--na-e3); padding: 0;
				font: inherit; text-transform: none; letter-spacing: normal; background: none; border: 0; border-radius: var(--na-r1);
				box-shadow: none; cursor: zoom-in; -webkit-appearance: none; appearance: none; }
			.na-form#na-form button.na-illus-agrandir .na-illus-vignette { margin: 0; }
			.na-form#na-form button.na-illus-agrandir:hover .na-illus-vignette { border-color: var(--na-accent); }
			.na-form#na-form dialog.na-agrandie { width: auto; max-width: min(96vw, 80rem); max-height: 94vh; margin: auto; padding: var(--na-e3);
				color: var(--na-encre); background: var(--na-papier); border: 1px solid var(--na-filet-doux); border-radius: var(--na-r2); }
			.na-form#na-form dialog.na-agrandie::backdrop { background: rgba(20, 16, 14, .8); }
			.na-form#na-form dialog.na-agrandie img { display: block; max-width: 100%; max-height: calc(94vh - 5rem); width: auto; height: auto; margin: 0 auto; }
			.na-form#na-form dialog.na-agrandie .na-agrandie-bas { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between;
				gap: var(--na-e2); margin: var(--na-e2) 0 0; font-size: var(--na-t-s); color: var(--na-sourd); overflow-wrap: anywhere; }
			.na-form#na-form dialog.na-agrandie .na-agrandie-bas button { margin: 0; }
			/* Le champ d'autorisation était le seul bouton natif de la page, gris
			   et étranger au reste : il prend l'allure du bouton de dépôt. */
			.na-form#na-form .na-illus-autorisation input[type=file] { display: block; width: 100%; min-width: 0; max-width: 100%; font: inherit;
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
			/* Les figures déjà déposées d'une notice qu'on corrige : la même
			   carte, avec trois choix au lieu du bouton Retirer. */
			.na-form#na-form .na-gardees { display: grid; grid-template-columns: minmax(0, 1fr); gap: var(--na-e3); margin: var(--na-e3) 0 0; padding: 0; list-style: none; }
			.na-form#na-form .na-garde-actions { clear: both; display: flex; flex-wrap: wrap; gap: var(--na-e2) var(--na-e4); margin: 0 0 var(--na-e4); }
			.na-form#na-form .na-garde-choix { display: inline-flex; align-items: center; gap: var(--na-e2); min-height: var(--na-cible); margin: 0;
				font-size: var(--na-t-m); font-weight: 400; cursor: pointer; }
			.na-form#na-form .na-garde-choix input { margin: 0; }
			.na-form#na-form .na-garde-remplacement { margin: 0 0 var(--na-e4); }
			.na-form#na-form .na-garde-remplacement > label { display: block; margin: 0 0 var(--na-e1); font-size: var(--na-t-s); }
			.na-form#na-form .na-garde-absente { color: var(--na-erreur); }
			.na-form#na-form .na-garde-retiree .na-illus { background: var(--na-fond); border-left-color: var(--na-filet); }
			.na-form#na-form .na-garde-retiree .na-illus legend { color: var(--na-sourd); }

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
			/* Ce qui est parti se mesure : la barre avance, au lieu d'aller et venir. */
			.na-form#na-form .na-envoi-barre.na-envoi-barre--mesuree { margin-left: 0; animation: none; transition: width .3s ease; }
			.na-form#na-form .na-envoi-rate { max-width: var(--na-mesure); margin: var(--na-e3) 0 0; }
			.na-form#na-form .na-envoi-mot { max-width: var(--na-mesure); margin-top: var(--na-e2); font-size: var(--na-t-s); color: var(--na-encre); }
			.na-form#na-form .na-donnees { max-width: var(--na-mesure); margin-top: var(--na-e5); font-size: 13px; line-height: 1.5; color: #555; }
			.na-form#na-form .na-credit { max-width: var(--na-mesure); margin-top: var(--na-e3); font-size: 11px; line-height: 1.4; color: #777; }

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
		<form class="na-form" id="na-form" lang="fr" method="post" enctype="multipart/form-data"
			data-reprise="<?php echo empty( $reprise ) ? '0' : '1'; ?>"
			data-brouillon="<?php echo $correction ? '0' : '1'; ?>"
			data-perimee="<?php echo $perimee ? '1' : '0'; ?>">
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
			elseif ( 'close' === $lien['etat'] ) :
				$contact = $this->adresse_de_contact();
				?>
				<div class="na-message na-avis" id="na-lien-clos" role="alert"><p><?php
					echo $this->typo( '<strong>La campagne est close</strong> : les notices sont en relecture, et ce lien ne les '
						. 'corrige plus. Pour signaler une correction, écrivez à ' . ( '' !== $contact
							? '<a href="mailto:' . esc_attr( antispambot( $contact ) ) . '">' . esc_html( antispambot( $contact ) ) . '</a>'
							: 'la rédaction' )
						. ' en citant la référence de votre notice : un dépôt fait d’ici ne remplacerait pas l’ancien.' );
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
					<li>l’année et la nature de l’opération, son code Patriarche (n° d’OA) et, si vous le connaissez, son numéro d’arrêté&nbsp;;</li>
					<li>jusqu’à trois illustrations, de préférence un plan, une vue d’ensemble et un échantillon du mobilier (JPEG, TIFF ou PDF, <?php echo esc_html( $total_mo ); ?>&nbsp;Mo en tout<?php echo $par_fichier; // Chiffre calculé, entités seulement. ?>), avec pour chacune sa légende, son crédit et une phrase qui décrit l’image pour les lecteurs aveugles ou malvoyants.</li>
				</ul>
				<p>Les champs obligatoires portent un astérisque (<span class="na-requis">*</span>)&nbsp;; les autres sont marqués «&nbsp;facultatif&nbsp;».<?php if ( ! $correction ) : ?></p>
				<p><strong>Vous pouvez vous interrompre</strong> et revenir plus tard à cette page, sur le même appareil et dans le même navigateur&nbsp;: votre saisie y est gardée trente jours. Seuls les fichiers des illustrations sont à redéposer&nbsp;; leurs textes reviennent d’eux-mêmes.<?php endif; ?><?php if ( $en_place ) : ?> La correction met à jour la notice sous sa référence&nbsp;: aucun second dépôt n’est fait.<?php else : ?> Après l’envoi, le responsable d’opération — et, s’il y a lieu, le co-responsable et le co-auteur — reçoit une copie par courriel, avec un lien pour corriger la notice pendant un mois.<?php endif; ?></p>
				<?php
				$secours     = $this->adresse_de_contact();
				$accessible  = Notice_Archeomed_Accessibilite::lire();
				$declaration = isset( $accessible['ara']['declaration'] ) ? (string) $accessible['ara']['declaration'] : '';
				if ( '' !== $secours || '' !== $declaration ) :
					?>
				<p class="na-intro-acces"><?php
					if ( '' !== $secours ) {
						echo $this->typo( 'Ce formulaire vous pose difficulté (lecteur d’écran, clavier, vue…)&nbsp;? Envoyez votre notice par courriel à <a href="mailto:'
							. esc_attr( antispambot( $secours ) ) . '">' . esc_html( antispambot( $secours ) ) . '</a>.' );
					}
					if ( '' !== $declaration ) {
						echo ( '' !== $secours ? ' ' : '' ) . '<a href="' . esc_url( $declaration ) . '">Déclaration d’accessibilité</a>.';
					}
				?></p>
				<?php endif; ?>
			</div>
			<p class="na-sr" id="na-annonce" role="status" aria-live="polite"></p>
			<?php echo $this->champ_du_jeton(); // Champ caché fabriqué par WordPress. ?>
			<div aria-hidden="true" style="position:absolute!important;left:-9999px!important;top:-9999px!important;height:0;overflow:hidden;">
				<label>Ne pas remplir ce champ
					<input type="text" name="na_website" tabindex="-1" autocomplete="off" value="" data-1p-ignore data-lpignore="true" data-bwignore="true" data-form-type="other">
				</label>
			</div>
			<input type="hidden" name="na_ts" value="<?php echo esc_attr( $this->ts_de_la_page() ); ?>">
			<input type="hidden" name="reprise_jeton" value="<?php echo esc_attr( $this->jeton_de_reprise() ); ?>">
			<?php if ( $en_place ) : ?>
				<input type="hidden" name="corrige_version" value="<?php echo esc_attr( isset( $reprise['corrige_version'] ) ? (string) absint( $reprise['corrige_version'] ) : '' ); ?>">
			<?php elseif ( $perimee ) : ?>
				<input type="hidden" name="corrige_version" value="0">
			<?php endif; ?>

			<section class="na-section" aria-labelledby="na-s1">
				<h2 class="na-section-titre" id="na-s1"><span class="na-section-num" aria-hidden="true">1</span> L’opération</h2>
				<fieldset class="na-sous-groupe" id="na-titre">
					<legend>Le titre de la notice</legend>
					<div class="na-champ">
						<label for="na-lieu-1">Commune ou territoire de l’opération <span class="na-requis" aria-hidden="true">*</span></label>
						<p class="na-help" id="na-lieu-aide">Commencez à taper le nom de la commune, puis choisissez-la dans la liste&nbsp;: elle sera reliée au référentiel des lieux Pactols, qui sert à retrouver la notice. Si elle n’y figure pas, écrivez-la simplement.</p>
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
						<label for="na-departement">Département ou région <span class="na-requis" aria-hidden="true">*</span></label>
						<p class="na-help" id="na-departement-aide">Le plus souvent le département, rempli automatiquement (ex.&nbsp;Calvados). Ce peut être une ou plusieurs régions&nbsp;: Champagne, Alsace, Lorraine. Vérifiez-le. Une région nommée ici n’est pas indexée&nbsp;: ajoutez-la aussi dans «&nbsp;Autres lieux&nbsp;» (section&nbsp;5).</p>
						<input type="text" id="na-departement" name="departement" required
							aria-describedby="na-departement-aide na-departement-auto"
							value="<?php echo esc_attr( $this->repris( 'departement' ) ); ?>">
						<p class="na-auto" id="na-departement-auto" aria-live="polite"></p>
					</div>
					<div class="na-champ">
						<label for="na-lieu-dit">Lieu-dit, adresse ou nom du projet <span class="na-requis" aria-hidden="true">*</span></label>
						<p class="na-help" id="na-lieu-dit-aide">Par exemple&nbsp;: Château, salle de l’Échiquier&nbsp;; 12, rue des Carmes&nbsp;; ou le titre d’un projet collectif de recherche.</p>
						<input type="text" id="na-lieu-dit" name="lieu_dit" required
							aria-describedby="na-lieu-dit-aide na-apercu"
							value="<?php echo esc_attr( $this->repris( 'lieu_dit' ) ); ?>">
						<input type="hidden" id="na-lieu-dit-forme" name="lieu_dit_forme"
							value="<?php echo esc_attr( $this->repris( 'lieu_dit_forme' ) ); ?>">
					</div>
					<div class="na-apercu" id="na-apercu" hidden>
						<p class="na-apercu-mot">Votre notice paraîtra sous le titre&nbsp;:</p>
						<p class="na-apercu-titre" id="na-apercu-titre"></p>
					</div>
				</fieldset>

				<div class="na-champ">
					<label for="na-rubrique">Rubrique de la Chronique <span class="na-requis" aria-hidden="true">*</span></label>
					<select id="na-rubrique" name="rubrique_principale" required><?php echo $this->options_html( $this->rubriques, self::rubrique_actuelle( $this->repris( 'rubrique_principale' ) ), 'Choisir une rubrique…' ); ?></select>
				</div>
				<?php $matiere_reprise = strtoupper( $this->repris( 'rubrique_matiere' ) ); ?>
				<div class="na-champ" id="na-matiere-bloc" data-rubrique="<?php echo esc_attr( self::RUBRIQUE_ARTISANAT ); ?>"<?php echo self::RUBRIQUE_ARTISANAT === self::rubrique_actuelle( $this->repris( 'rubrique_principale' ) ) ? '' : ' hidden'; ?>>
					<label for="na-matiere">Sous-rubrique <span class="na-requis" aria-hidden="true">*</span></label>
					<select id="na-matiere" name="rubrique_matiere" aria-required="true">
						<option value="">Choisir une sous-rubrique…</option>
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

				<?php echo $this->natures_html(); ?>

				<div class="na-champ na-court">
					<label for="na-annee">Année de l’opération <span class="na-requis" aria-hidden="true">*</span></label>
					<p class="na-help" id="na-annee-aide">Par exemple&nbsp;: 2024, ou 2004-2005 pour une opération sur plusieurs années.</p>
					<input type="text" id="na-annee" name="annee" required aria-describedby="na-annee-aide"
						value="<?php echo esc_attr( $this->repris( 'annee' ) ); ?>">
				</div>

				<div class="na-champ">
					<label for="na-organisme-1">Opérateur ou organisme porteur de l’opération <span class="na-requis" aria-hidden="true">*</span></label>
					<p class="na-help" id="na-organisme-aide">Celui qui a conduit l’opération ou qui la porte, et non l’employeur de chacun&nbsp;: Inrap, service archéologique de collectivité, opérateur privé agréé, université, CNRS, association…</p>
					<?php
					$organismes_repris = $this->repris_liste( 'organisme' );
					for ( $rang = 0; $rang < self::MAX_ORGANISMES; $rang++ ) :
						$valeur = isset( $organismes_repris[ $rang ] ) ? $organismes_repris[ $rang ] : '';
						$n      = $rang + 1;
						?>
						<div class="na-ligne na-ancre<?php echo $rang ? ' na-repli' : ''; ?>"<?php echo $rang ? ' data-repli="organisme"' : ''; ?>>
							<?php if ( $rang ) : ?>
								<label for="na-organisme-<?php echo (int) $n; ?>">Opérateur ou organisme <?php echo (int) $n; ?> <span class="na-facultatif">(facultatif)</span></label>
							<?php endif; ?>
							<input type="text" id="na-organisme-<?php echo (int) $n; ?>" name="organisme[]" class="na-organisme"
								<?php echo 0 === $rang ? 'required aria-describedby="na-organisme-aide"' : ''; ?>
								value="<?php echo esc_attr( $valeur ); ?>">
							<?php if ( $rang ) : ?>
								<button type="button" class="na-retirer" hidden>Retirer cet opérateur</button>
							<?php endif; ?>
						</div>
					<?php endfor; ?>
					<button type="button" class="na-ajout" data-ajoute="organisme" aria-describedby="na-organisme-plus-aide" hidden><span aria-hidden="true">+</span> Ajouter un opérateur ou un organisme</button>
					<p class="na-help" id="na-organisme-plus-aide">Quand l’opération est portée à plusieurs (trois au plus).</p>
				</div>

				<div class="na-half">
					<div class="na-champ">
						<label for="na-autorisation">Numéro d’arrêté <span class="na-facultatif">(facultatif)</span></label>
						<p class="na-help" id="na-autorisation-aide">Le numéro de l’arrêté qui autorise ou prescrit l’opération, tel qu’il figure sur l’arrêté.</p>
						<input type="text" id="na-autorisation" name="num_autorisation" aria-describedby="na-autorisation-aide"
							value="<?php echo esc_attr( $this->repris( 'num_autorisation' ) ); ?>">
					</div>
					<div class="na-champ">
						<label for="na-patriarche">Code d’opération Patriarche (n° d’OA) <span class="na-requis" aria-hidden="true">*</span></label>
						<p class="na-help" id="na-patriarche-aide">Le numéro d’opération archéologique (OA) que le service régional de l’archéologie a donné à l’opération dans Patriarche&nbsp;: c’est lui qui permet de retrouver le rapport.</p>
						<input type="text" id="na-patriarche" name="id_patriarche" required aria-describedby="na-patriarche-aide"
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

				<div class="na-champ">
					<label for="na-donnees">Adresse du jeu de données en ligne <span class="na-facultatif">(facultatif)</span></label>
					<p class="na-help" id="na-donnees-aide">Les données de l’opération déposées sur Nakala, Zenodo ou un autre entrepôt, ou leur DOI. Par exemple&nbsp;: https://doi.org/10.34847/nkl.abcd1234</p>
					<input type="text" inputmode="url" id="na-donnees" name="donnees_lien" autocomplete="off"
						spellcheck="false" autocapitalize="off" aria-describedby="na-donnees-aide"
						value="<?php echo esc_attr( $this->repris( 'donnees_lien' ) ); ?>">
				</div>
			</section>

			<section class="na-section" aria-labelledby="na-s2">
				<h2 class="na-section-titre" id="na-s2"><span class="na-section-num" aria-hidden="true">2</span> Les responsables</h2>
				<fieldset class="na-sous-groupe" id="na-resp">
					<legend>Responsable d’opération</legend>
					<div class="na-half">
						<div class="na-champ">
							<label for="na-resp-prenom">Prénom <span class="na-requis" aria-hidden="true">*</span></label>
							<input type="text" id="na-resp-prenom" name="resp_prenom" required autocomplete="given-name"
								value="<?php echo esc_attr( $this->repris( 'resp_prenom' ) ); ?>">
						</div>
						<div class="na-champ">
							<label for="na-resp-nom">Nom <span class="na-requis" aria-hidden="true">*</span></label>
							<input type="text" id="na-resp-nom" name="resp_nom" required autocomplete="family-name"
								value="<?php echo esc_attr( $this->repris( 'resp_nom' ) ); ?>">
						</div>
					</div>
					<div class="na-champ">
						<label for="na-resp-email">Adresse électronique <span class="na-requis" aria-hidden="true">*</span></label>
						<p class="na-help" id="na-resp-email-aide">La copie de la notice et le lien pour la corriger partiront à cette adresse.</p>
						<input type="email" id="na-resp-email" name="resp_email" required autocomplete="email" spellcheck="false"
							aria-describedby="na-resp-email-aide" value="<?php echo esc_attr( $this->repris( 'resp_email' ) ); ?>">
					</div>
					<div class="na-champ">
						<label for="na-resp-inst">Institution de rattachement <span class="na-requis" aria-hidden="true">*</span></label>
						<p class="na-help" id="na-resp-inst-aide">Celle du responsable, telle qu’elle doit paraître après son nom dans la notice&nbsp;: son employeur ou son laboratoire. Elle peut différer de l’opérateur.</p>
						<input type="text" id="na-resp-inst" name="resp_inst" required autocomplete="organization"
							aria-describedby="na-resp-inst-aide" value="<?php echo esc_attr( $this->repris( 'resp_inst' ) ); ?>">
					</div>
					<?php
					// Qui dépose pour le responsable se nomme : il figure ainsi sur la
					// fiche et dans l'annuaire, et non plus comme une adresse seule.
					// La saisie automatique vise une section à part, pour que le
					// navigateur ne mette pas son identité dans les champs du
					// responsable.
					?>
					<div class="na-deposant" role="group" aria-labelledby="na-deposant-titre">
						<p class="na-deposant-titre" id="na-deposant-titre">Si vous déposez pour le responsable <span class="na-facultatif">(facultatif)</span></p>
						<p class="na-help" id="na-deposant-email-aide">Vous recevrez vous aussi la copie et le lien de correction. Vos coordonnées ne sont pas publiées.</p>
						<div class="na-half">
							<div class="na-champ">
								<label for="na-deposant-prenom">Votre prénom</label>
								<input type="text" id="na-deposant-prenom" name="deposant_prenom" autocomplete="section-deposant given-name"
									value="<?php echo esc_attr( $this->repris( 'deposant_prenom' ) ); ?>">
							</div>
							<div class="na-champ">
								<label for="na-deposant-nom">Votre nom</label>
								<input type="text" id="na-deposant-nom" name="deposant_nom" autocomplete="section-deposant family-name"
									value="<?php echo esc_attr( $this->repris( 'deposant_nom' ) ); ?>">
							</div>
						</div>
						<div class="na-champ">
							<label for="na-deposant-email">Votre adresse électronique</label>
							<input type="email" id="na-deposant-email" name="deposant_email" autocomplete="section-deposant email" spellcheck="false"
								aria-describedby="na-deposant-email-aide" value="<?php echo esc_attr( $this->repris( 'deposant_email' ) ); ?>">
						</div>
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
				<h2 class="na-section-titre" id="na-s3"><span class="na-section-num" aria-hidden="true">3</span> Mots-clés</h2>
				<div class="na-sous-groupe" role="group" aria-labelledby="na-s3" aria-describedby="na-kw-aide">
					<p class="na-help" id="na-kw-aide">Ils servent à retrouver la notice. Une période et un sujet au moins&nbsp;; les autres lieux peuvent rester vides. Tapez trois lettres au moins, puis choisissez dans la liste, qui vient du thésaurus Pactols&nbsp;: seuls les termes choisis ainsi indexent la notice. Deux à cinq par liste suffisent (dix au plus). Un terme absent de la liste peut être gardé tel quel en appuyant deux fois sur Entrée&nbsp;; il reste visible pour la rédaction, mais n’indexe pas la notice.</p>
					<div class="na-pactols-grid">
						<?php
						echo $this->pactols_categorie_html( 'period', 'Périodes',
							'Un siècle se cherche en chiffres romains ou arabes (« XII<sup>e</sup> » ou « 12<sup>e</sup> »), une époque par son nom (« haut Moyen Âge »).', 'Chercher une période', 'pactols_periods', true );
						echo $this->pactols_categorie_html( 'subject', 'Sujets',
							'Par exemple&nbsp;: château, four de potier, sépulture.', 'Chercher un sujet', 'pactols_subjects', true );
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

			<section class="na-section" aria-labelledby="na-s4">
				<h2 class="na-section-titre" id="na-s4"><span class="na-section-num" aria-hidden="true">4</span> Le texte</h2>
				<div class="na-champ">
					<p class="na-libelle" id="na-texte-libelle">Texte de la notice <span class="na-requis" aria-hidden="true">*</span></p>
					<p class="na-help" id="na-texte-aide"><?php echo esc_html( self::entier( Notice_Archeomed_Normes::valeur( 'mots_min' ) ) ); ?> à <?php echo esc_html( self::entier( Notice_Archeomed_Normes::valeur( 'mots_max' ) ) ); ?>&nbsp;mots. Collez-le depuis votre traitement de texte&nbsp;: l’italique, le gras, les exposants, les paragraphes et les listes sont conservés. Un intertitre se pose par le bouton T1, une liste par le bouton Liste à puces&nbsp;; une liste numérotée devient une liste à puces. Au clavier, Ctrl+B (⌘+B sur Mac) met en gras et Ctrl+I en italique. Les notes de bas de page ne sont pas possibles&nbsp;: intégrez l’information au texte. Inutile d’y répéter le titre ou les noms des responsables&nbsp;: ils s’ajoutent d’eux-mêmes. Développez chaque sigle à sa première mention&nbsp;: service régional de l’archéologie (SRA).</p>
					<div class="na-editeur na-ancre" id="na-texte"><div id="na-editor"></div></div>
					<p class="na-wordcount" id="na-wordcount">0 mot</p>
					<input type="hidden" name="texte_notice" id="na-texte-notice" value="<?php echo esc_attr( $this->repris( 'texte_notice' ) ); ?>">
				</div>
			</section>

			<section class="na-section" aria-labelledby="na-s5">
				<h2 class="na-section-titre" id="na-s5"><span class="na-section-num" aria-hidden="true">5</span> Illustrations <span class="na-facultatif">(facultatif)</span></h2>
				<p class="na-help" id="na-illus-aide">Trois fichiers au plus<?php echo ( $en_place && ! empty( $gardees ) ) ? ', figures déjà déposées comprises' : ''; ?>, <?php echo esc_html( $total_mo ); ?>&nbsp;Mo en tout<?php echo $par_fichier; // Chiffre calculé, entités seulement. ?>, en JPEG, TIFF ou PDF. Privilégiez un plan, une vue d’ensemble et un échantillon du mobilier. <?php echo esc_html( $this->norme_des_illustrations() ); ?> Appelez chaque figure dans le texte, à l’endroit qui en parle&nbsp;: (fig.&nbsp;1).</p>
				<?php if ( $en_place && ! empty( $gardees ) ) : ?>
				<?php
				// Les figures que la notice porte déjà : leurs fichiers sont sur
				// le serveur, et ne se redéposent pas. Chacune se garde, se
				// remplace ou se retire ; ses textes se corrigent sur place. Elles
				// passent avant les fichiers nouveaux, qui les suivent en numéro.
				?>
				<ul class="na-gardees" id="na-gardees" aria-label="Figures déjà déposées">
					<?php foreach ( array_values( $gardees ) as $n => $g ) :
						$k       = (int) $g['k'];
						$base    = 'na-garde-' . $k;
						$retiree = isset( $g['action'] ) && 'retirer' === $g['action'];
						$texte   = function ( $cle ) use ( $g ) {
							return isset( $g[ $cle ] ) && is_scalar( $g[ $cle ] ) ? (string) $g[ $cle ] : '';
						};
						?>
					<li class="na-illus-carte na-garde<?php echo $retiree ? ' na-garde-retiree' : ''; ?>" id="<?php echo esc_attr( $base ); ?>" data-k="<?php echo esc_attr( $k ); ?>">
						<fieldset class="na-illus">
							<?php $a_part = ! empty( $g['a_part'] ) && is_array( $g['a_part'] ) ? array_merge( array( 'lien' => '', 'jusqua' => '', 'fichier' => '' ), $g['a_part'] ) : null; ?>
							<legend><span class="na-garde-numero">Figure <?php echo (int) $n + 1; ?></span> <span class="na-illus-fichier">— <?php echo null !== $a_part ? 'envoyée à part' : esc_html( '' !== $texte( 'nom' ) ? $texte( 'nom' ) : 'fichier sans nom' ); ?> (déjà déposée)</span></legend>
							<?php if ( empty( $g['a_fichier'] ) && null === $a_part ) : ?>
								<p class="na-help na-garde-absente">Le fichier de cette figure n’est plus sur le serveur&nbsp;: remplacez-le, ou retirez la figure.</p>
							<?php endif; ?>
							<div class="na-garde-actions" role="radiogroup" aria-label="Que faire de cette figure">
								<?php foreach ( array( 'garder' => 'La garder', 'remplacer' => 'Remplacer son fichier', 'retirer' => 'La retirer' ) as $valeur => $libelle ) :
									$coche = isset( $g['action'] ) && in_array( $g['action'], array( 'remplacer', 'retirer' ), true )
										? $g['action'] === $valeur : 'garder' === $valeur;
									?>
									<label class="na-garde-choix"><input type="radio" name="garde_action[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $valeur ); ?>"<?php echo $coche ? ' checked' : ''; ?>> <?php echo esc_html( $libelle ); ?></label>
								<?php endforeach; ?>
							</div>
							<div class="na-garde-remplacement na-illus-autorisation" id="<?php echo esc_attr( $base . '-remplacement' ); ?>">
								<label for="<?php echo esc_attr( $base . '-fichier' ); ?>">Nouveau fichier <span class="na-facultatif">(seulement pour remplacer)</span></label>
								<p class="na-help" id="<?php echo esc_attr( $base . '-fichier-aide' ); ?>">En JPEG, TIFF ou PDF. Les textes ci-dessous restent ceux de la figure&nbsp;: corrigez-les s’il le faut.</p>
								<input type="file" id="<?php echo esc_attr( $base . '-fichier' ); ?>" name="garde_fichier_<?php echo esc_attr( $k ); ?>" accept=".jpg,.jpeg,.tiff,.tif,.pdf" aria-describedby="<?php echo esc_attr( $base . '-fichier-aide' ); ?>">
							</div>
							<?php if ( null !== $a_part ) : ?>
							<?php
							// Le lien d'un envoi s'éteint : l'auteur le renouvelle ici
							// sans retirer la figure. Pas de date minimale : la rédaction
							// a pu télécharger l'original avant l'échéance passée.
							?>
							<div class="na-illus-champs na-illus-a-part na-garde-a-part" id="<?php echo esc_attr( $base . '-a-part' ); ?>">
								<p class="na-illus-partie">Le fichier envoyé à part</p>
								<div>
									<label for="<?php echo esc_attr( $base . '-lien' ); ?>">Lien de téléchargement <span class="na-requis" aria-hidden="true">*</span></label>
									<input type="text" inputmode="url" id="<?php echo esc_attr( $base . '-lien' ); ?>" name="garde_lien[<?php echo esc_attr( $k ); ?>]" autocomplete="off" spellcheck="false" autocapitalize="off" value="<?php echo esc_attr( $a_part['lien'] ); ?>">
								</div>
								<div>
									<label for="<?php echo esc_attr( $base . '-jusqua' ); ?>">Lien valable jusqu’au <span class="na-requis" aria-hidden="true">*</span></label>
									<input type="date" id="<?php echo esc_attr( $base . '-jusqua' ); ?>" name="garde_jusqua[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $a_part['jusqua'] ); ?>">
								</div>
								<div>
									<label for="<?php echo esc_attr( $base . '-nom-envoi' ); ?>">Nom du fichier dans l’envoi <span class="na-facultatif">(facultatif)</span></label>
									<input type="text" id="<?php echo esc_attr( $base . '-nom-envoi' ); ?>" name="garde_nom_envoi[<?php echo esc_attr( $k ); ?>]" autocomplete="off" spellcheck="false" value="<?php echo esc_attr( $a_part['fichier'] ); ?>">
								</div>
							</div>
							<?php endif; ?>
							<div class="na-illus-champs na-garde-champs">
								<div class="na-illus-type">
									<label for="<?php echo esc_attr( $base . '-type' ); ?>">Nature de la figure <span class="na-requis" aria-hidden="true">*</span></label>
									<select id="<?php echo esc_attr( $base . '-type' ); ?>" name="garde_type[<?php echo esc_attr( $k ); ?>]">
										<option value="">Choisir…</option>
										<?php foreach ( Notice_Archeomed_Controles::TYPES_DE_FIGURE as $cle => $nature ) : ?>
											<option value="<?php echo esc_attr( $cle ); ?>"<?php echo (string) $cle === $texte( 'type' ) ? ' selected' : ''; ?>><?php echo esc_html( $nature ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<p class="na-illus-partie">Pour la publication</p>
								<div>
									<label for="<?php echo esc_attr( $base . '-titre' ); ?>">Légende de la figure <span class="na-requis" aria-hidden="true">*</span></label>
									<input type="text" id="<?php echo esc_attr( $base . '-titre' ); ?>" name="garde_titre[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $texte( 'titre' ) ); ?>">
									<input type="hidden" id="<?php echo esc_attr( $base . '-titre-forme' ); ?>" name="garde_titre_forme[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $texte( 'titre_forme' ) ); ?>">
								</div>
								<div>
									<label for="<?php echo esc_attr( $base . '-legende' ); ?>">Complément de légende <span class="na-facultatif">(facultatif)</span></label>
									<textarea id="<?php echo esc_attr( $base . '-legende' ); ?>" name="garde_legende[<?php echo esc_attr( $k ); ?>]" rows="2"><?php echo esc_textarea( $texte( 'legende' ) ); ?></textarea>
									<input type="hidden" id="<?php echo esc_attr( $base . '-legende-forme' ); ?>" name="garde_legende_forme[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $texte( 'legende_forme' ) ); ?>">
								</div>
								<div>
									<label for="<?php echo esc_attr( $base . '-credits' ); ?>">Crédits <span class="na-requis" aria-hidden="true">*</span></label>
									<input type="text" id="<?php echo esc_attr( $base . '-credits' ); ?>" name="garde_credits[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $texte( 'credits' ) ); ?>">
								</div>
								<div class="na-illus-autorisation">
									<?php if ( ! empty( $g['autorisation'] ) ) : ?>
										<p class="na-help">Une autorisation de reproduction est jointe à cette figure.</p>
										<label class="na-garde-choix"><input type="checkbox" name="garde_autorisation_retirer[<?php echo esc_attr( $k ); ?>]" value="1"> La retirer</label>
									<?php endif; ?>
									<label for="<?php echo esc_attr( $base . '-autorisation' ); ?>"><?php echo empty( $g['autorisation'] ) ? 'Autorisation de reproduction' : 'Une autre autorisation, qui remplace la première'; ?> <span class="na-facultatif">(facultatif)</span></label>
									<p class="na-help" id="<?php echo esc_attr( $base . '-autorisation-aide' ); ?>">Seulement si vous ne détenez pas les droits de cette figure&nbsp;: l’accord écrit de leur détenteur, en PDF, JPEG ou PNG.</p>
									<input type="file" id="<?php echo esc_attr( $base . '-autorisation' ); ?>" name="garde_autorisation_<?php echo esc_attr( $k ); ?>" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="<?php echo esc_attr( $base . '-autorisation-aide' ); ?>">
								</div>
								<div class="na-illus-ecoute" role="group" aria-labelledby="<?php echo esc_attr( $base . '-ecoute-titre' ); ?>">
									<p class="na-illus-partie" id="<?php echo esc_attr( $base . '-ecoute-titre' ); ?>">Description pour les lecteurs qui ne voient pas l’image</p>
									<label for="<?php echo esc_attr( $base . '-alt' ); ?>">Description de l’image <span class="na-requis" aria-hidden="true">*</span></label>
									<p class="na-help" id="<?php echo esc_attr( $base . '-alt-aide' ); ?>">Une ou deux phrases qui disent ce qu’on voit, comme si vous décriviez l’image au téléphone. Ce n’est pas la légende. Pour un plan ou une carte, poursuivez à la ligne&nbsp;: le détail est publié sous la légende.</p>
									<textarea id="<?php echo esc_attr( $base . '-alt' ); ?>" name="garde_alt[<?php echo esc_attr( $k ); ?>]" rows="4" autocomplete="off" aria-describedby="<?php echo esc_attr( $base . '-alt-aide' ); ?>"><?php echo esc_textarea( $texte( 'alt' ) ); ?></textarea>
									<input type="hidden" id="<?php echo esc_attr( $base . '-alt-forme' ); ?>" name="garde_description_forme[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $texte( 'description_forme' ) ); ?>">
								</div>
							</div>
						</fieldset>
					</li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
				<div class="na-illus-attente" id="na-illus-attente" hidden></div>
				<label class="na-sr" for="na-illustrations">Choisir des fichiers</label>
				<div class="na-depot-fichiers" id="na-depot-zone">
					<input type="file" name="illustrations[]" id="na-illustrations" multiple accept=".jpg,.jpeg,.tiff,.tif,.pdf" aria-describedby="na-illus-aide">
					<p class="na-depot-invite" aria-hidden="true"><span class="na-bouton-faux">Choisir des fichiers</span><span class="na-depot-ou">ou faites-les glisser ici</span></p>
				</div>
				<div class="na-refus" id="na-refus">
					<p class="na-fichiers-refuses" id="na-fichiers-refuses" role="alert"></p>
					<div class="na-alleger" id="na-alleger" hidden></div>
				</div>
				<ul class="na-filelist" id="na-filelist"></ul>
				<button type="button" class="na-ajout na-ajout-figure" id="na-ajout-figure" aria-describedby="na-illus-aide" hidden><span aria-hidden="true">+</span> Ajouter une figure</button>

				<?php
				$originaux_remplis = '' !== $this->repris( 'originaux_lien' ) || '' !== $this->repris( 'originaux_jusqua' );
				// Une figure envoyée à part revenue d'un refus : le bloc s'ouvre,
				// pour qu'on en voie le bouton.
				$a_part_repris = isset( $reprise['illus_source'] ) && in_array( 'a_part', (array) $reprise['illus_source'], true );
				?>
				<details class="na-originaux" id="na-originaux-details"<?php echo ( $originaux_remplis || $a_part_repris ) ? ' open' : ''; ?>>
					<summary>Un fichier est trop lourd, ou d’un autre format (AI, EPS)&nbsp;?</summary>
					<p class="na-help" id="na-a-part-aide">Déposez l’original sur un service de transfert (FileSender de RENATER, espace de votre établissement), puis ajoutez une figure envoyée à part&nbsp;: vous y collerez le lien de téléchargement et remplirez ses textes comme pour les autres figures. Elle compte parmi les trois. Un fichier AI ou EPS peut aussi s’enregistrer en PDF et se joindre ci-dessus.</p>
					<button type="button" class="na-ajout na-ajout-figure" id="na-ajout-a-part" aria-describedby="na-a-part-aide"><span aria-hidden="true">+</span> Ajouter une figure envoyée à part</button>
					<?php
					// Le lien unique d'avant les figures envoyées à part : il ne
					// reparaît que pour une notice qui en portait un, afin que sa
					// correction ne le perde pas.
					if ( $originaux_remplis ) :
						?>
					<div class="na-champ">
						<label for="na-originaux">Lien vers les originaux <span class="na-facultatif">(facultatif)</span></label>
						<p class="na-help" id="na-originaux-aide">Le lien donné lors du dépôt précédent, pour l’ensemble des originaux. Videz-le si chaque figure a désormais le sien.</p>
						<input type="text" inputmode="url" id="na-originaux" name="originaux_lien" autocomplete="off"
							spellcheck="false" autocapitalize="off" aria-describedby="na-originaux-aide"
							value="<?php echo esc_attr( $this->repris( 'originaux_lien' ) ); ?>">
					</div>
					<div class="na-champ">
						<label for="na-originaux-jusqua">Lien valable jusqu’au <span class="na-requis" aria-hidden="true">*</span> <span class="na-facultatif">si vous donnez un lien</span></label>
						<p class="na-help" id="na-originaux-jusqua-aide">La date à laquelle le service effacera les fichiers&nbsp;: la rédaction les télécharge avant.</p>
						<input type="date" id="na-originaux-jusqua" name="originaux_jusqua" aria-describedby="na-originaux-jusqua-aide"
							min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"
							value="<?php echo esc_attr( $this->repris( 'originaux_jusqua' ) ); ?>">
					</div>
					<?php endif; ?>
				</details>
			</section>

			<section class="na-section" aria-labelledby="na-s6">
				<h2 class="na-section-titre" id="na-s6"><span class="na-section-num" aria-hidden="true">6</span> Envoi</h2>
				<div class="na-champ">
					<label for="na-commentaires">Message à la rédaction <span class="na-facultatif">(facultatif)</span></label>
					<p class="na-help" id="na-commentaires-aide">Ne sera pas publié. Par exemple&nbsp;: une précision sur une illustration, une autorisation en attente.</p>
					<textarea id="na-commentaires" name="commentaires" rows="3" aria-describedby="na-commentaires-aide"><?php echo esc_textarea( $this->repris( 'commentaires' ) ); ?></textarea>
				</div>
				<?php if ( $perimee ) : $contact = $this->adresse_de_contact(); ?>
					<div class="na-message na-avis" id="na-perimee"><p><?php
						echo $this->typo( '<strong>Ce formulaire ne peut plus enregistrer votre correction</strong> : son lien n’est plus valable. '
							. 'Votre saisie reste affichée pour que vous puissiez la copier. Pour corriger la notice, écrivez à '
							. ( '' !== $contact
								? '<a href="mailto:' . esc_attr( antispambot( $contact ) ) . '">' . esc_html( antispambot( $contact ) ) . '</a>'
								: 'la rédaction' )
							. ' en citant sa référence : un dépôt fait d’ici en créerait une seconde.' );
					?></p></div>
				<?php endif; ?>
				<?php if ( $this->protection_utilise_la_question() && ! $par_la_redaction && ! $perimee ) : $defi = $this->defi_du_puzzle(); ?>
					<div class="na-question na-champ" id="na-puzzle"
						data-cible="<?php echo esc_attr( $defi['cible'] ); ?>"
						data-tolerance="<?php echo esc_attr( self::PUZZLE_TOLERANCE ); ?>">
						<p class="na-libelle" id="na-puzzle-libelle">Vérification&nbsp;: faites glisser la pièce jusqu’à son encoche <span class="na-requis" aria-hidden="true">*</span></p>
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
				<?php if ( $this->protection_utilise_turnstile() && $this->turnstile_site_key_is_set() && ! $par_la_redaction && ! $perimee ) : ?>
					<div class="na-turnstile">
						<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $this->turnstile_site_key() ); ?>" data-theme="light" data-language="fr"></div>
					</div>
				<?php endif; ?>
				<div class="na-envoyer">
					<?php if ( ! $perimee ) : ?>
					<button type="submit" name="notice_archeomed_envoi" value="1" class="na-submit"><?php echo $en_place ? 'Enregistrer la correction' : 'Envoyer la notice'; ?></button>
					<?php endif; ?>
					<?php if ( ! $correction ) : ?>
					<p class="na-help na-temoin" id="na-temoin" hidden></p>
					<?php endif; ?>
					<div class="na-envoi" id="na-envoi" hidden>
						<div class="na-envoi-piste"><div class="na-envoi-barre"></div></div>
						<p class="na-envoi-mot">Envoi en cours. Avec des illustrations lourdes et une connexion lente, cela peut prendre deux ou trois minutes. Ne fermez pas cette page.</p>
					</div>
					<p class="na-message na-error na-envoi-rate" id="na-envoi-rate" role="alert" tabindex="-1" hidden></p>
					<p class="na-donnees">Les informations transmises via ce formulaire sont utilisées uniquement pour l’instruction éditoriale des notices destinées à la Chronique d’<em>Archéologie médiévale</em>. Elles sont adressées à la rédaction de la revue et ne sont pas utilisées à d’autres fins. Elles sont conservées pendant la durée nécessaire au traitement éditorial de la notice. Pour toute demande relative à ces données, vous pouvez contacter la rédaction à l’adresse indiquée sur le site.</p>
					<p class="na-credit">Ce formulaire est une extension WordPress développée par la rédaction de la revue à l’aide de l’IA.</p>
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
			// Si une erreur de script interrompt l'initialisation, plus rien ne
			// validerait ni n'avertirait : on rend alors la validation au
			// navigateur et l'on dit quoi faire. Armée jusqu'à la dernière ligne.
			var initialisationFinie = false;
			window.addEventListener('error', function () {
				if (initialisationFinie) { return; }
				initialisationFinie = true;
				form.noValidate = false;
				showJsError('Une erreur technique empêche une partie du formulaire de fonctionner.');
			});

			var CHAMPS = <?php echo wp_json_encode( $this->champs_du_formulaire(), JSON_HEX_TAG | JSON_HEX_AMP ); ?>;
			var MAX_FILES = <?php echo (int) self::MAX_FILES; ?>;
			// Le texte alternatif d'une figure : un avis au-delà du seuil des
			// normes (150 caractères par défaut), arrêté net à 300. Les
			// avis d'accessibilité viennent du serveur — phrases, motifs,
			// listes —, pour que le navigateur dise mot pour mot ce que la
			// rédaction lira.
			var A11Y = <?php echo wp_json_encode( Notice_Archeomed_Controles::regles_d_accessibilite(), JSON_HEX_TAG | JSON_HEX_AMP ); ?>;
			// Ce que le serveur sait faire des images, et les mesures des JPEG que
			// le navigateur fabrique à sa place quand il ne sait pas lire un TIFF.
			var CONV = <?php echo wp_json_encode( $this->reglages_de_conversion(), JSON_HEX_TAG | JSON_HEX_AMP ); ?>;
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
			// Un avis qui apparaît se dit, une fois, au lecteur d'écran : il est
			// sous le champ, hors de portée de qui continue de taper. On attend
			// la fin d'une rafale d'avis pour les dire d'un trait, et l'on ne
			// redit pas ceux qu'une zone montrait déjà.
			var avisAnnonces = [], attenteAnnonceAvis = null, avisParZone = {}, avisDits = {};
			function annoncerLesAvis(nouveaux) {
				avisAnnonces = avisAnnonces.concat(nouveaux);
				window.clearTimeout(attenteAnnonceAvis);
				attenteAnnonceAvis = window.setTimeout(function () {
					var tous = avisAnnonces;
					avisAnnonces = [];
					if (!tous.length) { return; }
					var dits = tous.slice(0, 2).map(function (t) { return t.replace(/[.\s]+$/, ''); }).join(NBSP + '; ');
					annoncer('Merci de bien vouloir vérifier les informations' + NBSP + ': ' + (tous.length > 1 ? tous.length + ' points' : 'un point') + NBSP + '— ' + dits
						+ (tous.length > 2 ? ', et ' + (tous.length - 2) + (tous.length - 2 > 1 ? ' autres' : ' autre') : '') + '.');
				}, 1500);
			}
			function montrerConseils(z, liste, champs) {
				var vus = {}, propres = [];
				liste.forEach(function (m) {
					var t = typeof m === 'string' ? m : m.texte;
					if (t && !vus[t]) { vus[t] = true; propres.push(m); }
				});
				// Ce que les zones montrent, gardé hors de la page : les cartes de
				// figures se redessinent à chaque ajout, retrait ou déplacement, et
				// leurs avis déjà dits ne doivent pas l'être de nouveau.
				var textes = propres.map(function (m) { return typeof m === 'string' ? m : m.texte; });
				var avant = avisParZone[z.id] || [];
				avisParZone[z.id] = textes;
				avant.forEach(function (t) {
					if (textes.indexOf(t) === -1 && !Object.keys(avisParZone).some(function (k) { return avisParZone[k].indexOf(t) !== -1; })) {
						delete avisDits[t];
					}
				});
				var nouveaux = textes.filter(function (t) { return !avisDits[t]; });
				nouveaux.forEach(function (t) { avisDits[t] = true; });
				if (nouveaux.length) { annoncerLesAvis(nouveaux); }
				z.innerHTML = '';
				(champs || []).forEach(function (c) { if (c) { retirerDescription(c, z.id); } });
				if (!propres.length) { z.hidden = true; return; }
				var titre = document.createElement('p');
				titre.className = 'na-conseils-titre';
				titre.textContent = 'Merci de bien vouloir vérifier les informations';
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
			// L'adresse où envoyer la notice si le formulaire ne marche pas : c'est
			// le moment où l'auteur en a le plus besoin.
			var SECOURS = <?php echo wp_json_encode( $secours, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
			function showJsError(msg) {
				jsErrorBox.textContent = msg + (SECOURS ? ' Vous pouvez aussi envoyer votre notice par courriel à ' + SECOURS + '.' : '');
				jsErrorBox.hidden = false;
			}

			// ── L'éditeur ──────────────────────────────────────────────────
			var quill = null;
			// Ce que le collage a laissé de côté, pour le dire à l'auteur.
			// « apresCollage » : le dernier changement du texte est un collage,
			// non une frappe. Le paragraphe où se pose le curseur n'est alors pas
			// en cours d'écriture, et ses avis valent tout de suite.
			var oteAuCollage = [], commentairesOtes = false, collageEnCours = false, apresCollage = false;
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
					placeholder: 'Collez ici le texte de votre notice, ou écrivez-le.',
					// Les seuls formats que le document sait rendre. Sans cette
					// liste, un collage depuis Word apportait listes, titres et
					// citations, que le serveur aplatissait en un paragraphe.
					// Un seul niveau d'intertitre, « h3 » : sous le titre de la
					// notice, c'est un titre de niveau 3, que le document pose sous
					// le style Métopes réglé pour lui. Le second niveau, « h4 », a
					// quitté la barre ; le serveur le ramène au premier.
					// La liste est à puces et d'un niveau : le Word la pose en
					// « Paragraphe de liste », avec la puce de Word.
					formats: ['bold', 'italic', 'script', 'header', 'list'].concat(petitesCapitales ? ['petitescapitales'] : []),
					modules: {
						toolbar: [
							[{ 'header': 3 }, { 'list': 'bullet' }],
							['bold', 'italic'].concat(petitesCapitales ? ['petitescapitales'] : []),
							[{ 'script': 'super' }, { 'script': 'sub' }],
							['clean']
						],
						// Tab insérait une tabulation : au clavier, on ne sortait
						// plus de l'éditeur qu'à reculons. Dans une liste, il
						// l'aurait décalée d'un cran, que le document n'a pas.
						// « 1. » ou « - » en tête de ligne ne font pas de liste
						// d'eux-mêmes : un paragraphe qui commence par une date
						// ou un numéro de phase en serait devenu une.
						keyboard: { bindings: {
							tab: { key: 'Tab', handler: function () { return true; } },
							indent: false, outdent: false, 'list autofill': false
						} }
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
						apresCollage = true;
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
						// Un titre collé devient l'intertitre, le seul niveau
						// qu'offre la barre, quel que soit son rang ; une liste
						// numérotée ou à cocher, la liste à puces.
						delta = naUneListeAPuces(delta, Delta, true);
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
					[['.ql-header[value="3"]', 'Intertitre'], ['.ql-list[value="bullet"]', 'Liste à puces'],
						['.ql-bold', 'Gras'], ['.ql-italic', 'Italique'], ['.ql-petitescapitales', 'Petites capitales'],
						['.ql-script[value="super"]', 'Exposant'],
						['.ql-script[value="sub"]', 'Indice'], ['.ql-clean', 'Effacer la mise en forme']].forEach(function (p) {
						var b = barre.querySelector(p[0]);
						if (b) { b.setAttribute('aria-label', p[1]); b.title = p[1]; }
					});
					// Une barre d'outils ne prend qu'un arrêt de tabulation : une
					// seule touche Tab la franchit, et les flèches passent d'un
					// bouton à l'autre.
					var boutons = [].slice.call(barre.querySelectorAll('button'));
					function poserLArret(b) {
						boutons.forEach(function (x) { x.setAttribute('tabindex', x === b ? '0' : '-1'); });
					}
					if (boutons.length) {
						poserLArret(boutons[0]);
						boutons.forEach(function (b) { b.addEventListener('focus', function () { poserLArret(b); }); });
						barre.addEventListener('keydown', function (e) {
							var i = boutons.indexOf(document.activeElement);
							if (i < 0) { return; }
							var vers = e.key === 'ArrowRight' || e.key === 'Right' ? (i + 1) % boutons.length
								: e.key === 'ArrowLeft' || e.key === 'Left' ? (i + boutons.length - 1) % boutons.length
								: e.key === 'Home' ? 0 : e.key === 'End' ? boutons.length - 1 : -1;
							if (vers < 0) { return; }
							e.preventDefault();
							boutons[vers].focus();
						});
					}
					// Quill n'a pas d'icône pour ce format : deux lettres, en
					// petites capitales elles-mêmes.
					var pc = barre.querySelector('.ql-petitescapitales');
					if (pc) { pc.innerHTML = '<span aria-hidden="true">Pc</span>'; }
					// L'intertitre : « T1 », plus parlant que le « H3 » du HTML.
					[['3', 'T1']].forEach(function (t) {
						var h = barre.querySelector('.ql-header[value="' + t[0] + '"]');
						if (h) { h.innerHTML = '<span aria-hidden="true">' + t[1] + '</span>'; h.classList.add('na-bouton-titre'); }
					});
					// La barre reste en haut de l'écran tant que le texte y est, sans
					// quoi il fallait remonter à chaque mot à mettre en italique dans
					// une longue notice. Le collage CSS y suffit, sauf sous un thème
					// qui enveloppe la page dans un bloc à défilement : la barre s'y
					// fixe alors d'elle-même, une cale tenant sa place, et revient
					// quand le texte quitte l'écran.
					var editeurTexte = document.getElementById('na-texte');
					var cale = document.createElement('div');
					cale.setAttribute('aria-hidden', 'true');
					var barreFixee = false, placementPrevu = false;
					function placerLaBarre() {
						placementPrevu = false;
						var e = editeurTexte.getBoundingClientRect();
						var h = barre.offsetHeight;
						var fixer = barreAFixer({ haut: parseFloat(window.getComputedStyle(barre).top) || 0, hBarre: h,
							edHaut: e.top, edBas: e.bottom, barreHaut: barre.getBoundingClientRect().top, fixee: barreFixee });
						if (fixer && !barreFixee) {
							cale.style.height = h + 'px';
							barre.parentNode.insertBefore(cale, barre);
							barre.classList.add('na-barre-flottante');
						} else if (!fixer && barreFixee) {
							barre.classList.remove('na-barre-flottante');
							barre.style.left = '';
							barre.style.width = '';
							if (cale.parentNode) { cale.parentNode.removeChild(cale); }
						}
						barreFixee = fixer;
						if (barreFixee) {
							barre.style.left = e.left + 'px';
							barre.style.width = e.width + 'px';
							cale.style.height = barre.offsetHeight + 'px';
						}
					}
					function prevoirLaBarre() {
						if (placementPrevu) { return; }
						placementPrevu = true;
						(window.requestAnimationFrame || function (f) { window.setTimeout(f, 16); })(placerLaBarre);
					}
					document.addEventListener('scroll', prevoirLaBarre, { capture: true, passive: true });
					window.addEventListener('resize', prevoirLaBarre);
					quill.on('text-change', prevoirLaBarre);
					prevoirLaBarre();
				}
			}
			// La barre se fixe quand le haut du texte a passé le haut de l'écran
			// (barre d'administration comprise), que son bas y laisse encore la
			// place de la barre, et que le collage CSS ne la retient pas déjà.
			function barreAFixer(m) {
				if (m.edBas - m.edHaut <= 0) { return false; }
				var dedans = m.edHaut < m.haut && m.edBas > m.haut + m.hBarre;
				return m.fixee ? dedans : dedans && m.barreHaut < m.haut - 0.5;
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
				// Un nom par liste : une page en porte plusieurs, et « Propositions »
				// seul ne dit pas de quel champ elles viennent.
				var etiquette = champ.id ? document.querySelector('label[for="' + champ.id + '"]') : null;
				var nomDuChamp = etiquette ? etiquette.textContent.replace(/\s*\(facultatif\)\s*$/, '').replace(/\s*\*\s*$/, '').replace(/\s+/g, ' ').trim() : '';
				liste.setAttribute('aria-label', nomDuChamp ? 'Propositions pour ' + nomDuChamp : 'Propositions');
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
<?php echo Notice_Archeomed_Lieu_Dit::script(); ?>
<?php echo Notice_Archeomed_Forme::script(); ?>
			var deptInput = document.getElementById('na-departement');
			var deptAuto = document.getElementById('na-departement-auto');
			var lieuDitInput = document.getElementById('na-lieu-dit');
			// Petites capitales, italique, exposant : le lieu-dit s'écrit dans un
			// éditeur d'une ligne, qui remplit le champ et garde la mise en forme
			// à côté.
			var lieuDitForme = document.getElementById('na-lieu-dit-forme');
			var lieuDitRiche = quill ? naLieuDitRiche(lieuDitInput, lieuDitForme, { classe: 'na-ancre', italiqueDuTitre: !!NORMES.lieuDitItalique }) : null;
			// Comme au serveur (capitale_initiale) : la première lettre, et elle
			// seule, passe en capitale ; les blancs de tête ne comptent pas.
			function capitaleInitiale(t) {
				return String(t || '').replace(/^(\s*)(\S)/, function (tout, blancs, l) { return blancs + l.toUpperCase(); });
			}
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
				var lieuDit = capitaleInitiale(net(lieuDitInput.value));
				if (/\.$/.test(lieuDit) && !/\.\.\.$/.test(lieuDit) && !/(^|\s)(\S{1,3}|\S+\.\S*)\.$/.test(lieuDit)) {
					lieuDit = lieuDit.slice(0, -1).trim();
				}
				function morceau(texte, manque, italique, classe) {
					var el = document.createElement(italique ? 'em' : 'span');
					el.textContent = texte;
					el.className = ((manque ? 'na-manque ' : '') + (classe || '')).trim();
					apercuTitre.appendChild(el);
				}
				apercuTitre.innerHTML = '';
				// La commune paraît en capitales dans la revue.
				morceau(lieux.length ? lieux.join(', ') : 'Commune', !lieux.length, false, 'na-apercu-commune');
				morceau(' (', false);
				morceau(dept || 'département', !dept);
				// Le signe et l'italique du lieu-dit suivent les normes de la revue.
				morceau(')' + NORMES.avantLieuDit, false);
				if (lieuDit) {
					// La mise en forme de l'auteur, accordée au texte tel que le
					// titre l'imprime ; l'italique renversé dans l'italique.
					var brut = capitaleInitiale(naLieuDitPropre(lieuDitInput.value).trim());
					if (/\.$/.test(brut) && !/\.$/.test(lieuDit)) { brut = brut.slice(0, -1).trim(); }
					naLieuDitPoser(apercuTitre, lieuDitForme ? lieuDitForme.value : '', brut, NORMES.lieuDitItalique);
				} else {
					morceau('Lieu-dit', true, NORMES.lieuDitItalique);
				}
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
				// Quand la recherche ne donne rien ou ne répond pas, la ligne le dit
				// sous le champ pour qui voit, et l'annonce pour qui écoute : il
				// n'y avait que l'annonce.
				var etatLieu = document.createElement('p');
				etatLieu.className = 'na-help na-lieu-etat';
				etatLieu.hidden = true;
				boite.parentNode.insertBefore(etatLieu, boite.nextSibling);
				function direLieu(texte) {
					etatLieu.textContent = texte;
					etatLieu.hidden = !texte;
					if (texte) { annoncer(texte); }
				}
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
					direLieu('');
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
								if (!items.length) {
									cb.fermer();
									direLieu('Aucun lieu ne correspond. Votre saisie sera gardée telle quelle.');
									return;
								}
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
							}, function () {
								if (ce !== tour) { return; }
								cb.fermer();
								direLieu('Pactols ne répond pas pour l’instant. Votre saisie sera gardée telle quelle.');
							});
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
			// Les originaux : le bloc replié s'ouvre si un brouillon ou une reprise
			// l'a rempli.
			replis.push(function () {
				var d = el('na-originaux-details');
				if (d && aDesValeurs(d)) { d.open = true; }
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
				// Le champ caché ne dit rien quand il change : la liste prévient,
				// pour qu'une erreur « au moins un terme » s'efface aussitôt.
				function ecrire() {
					cache.value = JSON.stringify(items);
					var ev;
					try { ev = new Event('na-termes', { bubbles: true }); } catch (e) { ev = document.createEvent('Event'); ev.initEvent('na-termes', true, false); }
					fs.dispatchEvent(ev);
				}
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
					dire(guillemets(item.label) + (item.ark ? ' ajouté.' : ' gardé hors de la liste de Pactols' + NBSP + ': il ne sera pas utilisé dans la notice.')
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
						+ 'ou appuyez de nouveau sur Entrée pour garder ' + guillemets(q) + ' tel quel' + NBSP + '; pris hors de la liste de Pactols, il ne sera pas utilisé dans la notice.');
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
								+ 'pour le garder tel quel. Absent de Pactols, il ne sera pas utilisé dans la notice.');
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
				return selected.reduce(function (sum, f) {
					var c = etatDe(f).conv;
					return sum + f.size + (c && c.etat === 'ok' ? (c.hr ? c.hr.size : 0) + c.br.size : 0);
				}, 0) + poidsAutorisations() + poidsDesGardees();
			}
			function refreshFileInput() {
				try {
					var dt = new DataTransfer();
					// Une figure envoyée à part n'a pas de fichier : elle a sa
					// carte, non sa place dans le champ.
					selected.forEach(function (f) { if (!f.aPart) { dt.items.add(f); } });
					fileInput.files = dt.files;
				} catch (e) {}
				poserLesConversions();
				renderFileList();
			}

			// ── Les TIFF et les PDF, convertis dans le navigateur ──────────
			// Sans Imagick, le serveur ne lit pas un TIFF : ni version allégée, ni
			// basse définition. Le navigateur de l'auteur le lit à sa place
			// (UTIF.js) et en tire deux JPEG qui voyagent avec la figure : la
			// haute définition, dans la norme des photographies (10 × 15 cm à
			// 300 ppp, sans agrandir), et la basse, de 2 500 px au plus sur son
			// grand côté et d'un méga-octet environ. Le TIFF original part aussi.
			// Ce que le navigateur ne sait pas lire, ou ce qui est trop gros, est
			// refusé : l'auteur l'enregistre lui-même en JPEG.
			var CONV_BASE = !CONV.imagick && !!document.createElement('canvas').getContext && typeof Promise !== 'undefined'
				&& (function () { try { new DataTransfer(); return true; } catch (e) { return false; } }());
			var CONV_PRET = CONV_BASE && typeof UTIF !== 'undefined';
			var PDF_PRET = CONV_BASE && !!CONV.pdfjs;
			function lireLeBuffer(blob) {
				if (blob.arrayBuffer) { return blob.arrayBuffer(); }
				return new Promise(function (ok, ko) {
					var r = new FileReader();
					r.onload = function () { ok(r.result); };
					r.onerror = ko;
					r.readAsArrayBuffer(blob);
				});
			}
			// L'en-tête JFIF d'un JPEG porte la densité : un canvas ne l'écrit pas
			// (ou la met à 1 par 1), et la mise en page lirait 72 ppp.
			function jpegAvecDensite(buffer, dpi) {
				var b = new Uint8Array(buffer), sortie;
				var hi = (dpi >> 8) & 255, lo = dpi & 255;
				if (b[2] === 0xFF && b[3] === 0xE0 && b[6] === 0x4A && b[7] === 0x46 && b[8] === 0x49 && b[9] === 0x46) {
					sortie = b.slice(0);
					sortie[13] = 1; sortie[14] = hi; sortie[15] = lo; sortie[16] = hi; sortie[17] = lo;
				} else {
					var jfif = [0xFF, 0xE0, 0x00, 0x10, 0x4A, 0x46, 0x49, 0x46, 0x00, 0x01, 0x01, 0x01, hi, lo, hi, lo, 0x00, 0x00];
					sortie = new Uint8Array(b.length + jfif.length);
					sortie.set(b.subarray(0, 2), 0);
					sortie.set(jfif, 2);
					sortie.set(b.subarray(2), 2 + jfif.length);
				}
				return sortie;
			}
			function enJpeg(canvas, qualite) {
				return new Promise(function (ok, ko) {
					canvas.toBlob(function (b) { if (b) { ok(b); } else { ko(new Error('jpeg')); } }, 'image/jpeg', qualite);
				});
			}
			function etiqueterLeJpeg(blob, dpi, nom) {
				return lireLeBuffer(blob).then(function (buf) {
					return new File([jpegAvecDensite(buf, dpi)], nom, { type: 'image/jpeg' });
				});
			}
			// Une réduction par moitiés tant que l'écart est grand : d'un coup,
			// le navigateur saute des pixels et l'image se crénelle.
			function reduireLeCanvas(source, largeur, hauteur) {
				var c = source, cl = source.width, ch = source.height;
				while (cl / 2 >= largeur && ch / 2 >= hauteur) {
					var m = document.createElement('canvas');
					m.width = Math.max(1, Math.round(cl / 2));
					m.height = Math.max(1, Math.round(ch / 2));
					var mx = m.getContext('2d');
					mx.fillStyle = '#fff'; mx.fillRect(0, 0, m.width, m.height);
					mx.imageSmoothingQuality = 'high';
					mx.drawImage(c, 0, 0, m.width, m.height);
					c = m; cl = m.width; ch = m.height;
				}
				var fin = document.createElement('canvas');
				fin.width = Math.max(1, largeur);
				fin.height = Math.max(1, hauteur);
				var fx = fin.getContext('2d');
				fx.fillStyle = '#fff'; fx.fillRect(0, 0, fin.width, fin.height);
				fx.imageSmoothingQuality = 'high';
				fx.drawImage(c, 0, 0, fin.width, fin.height);
				return fin;
			}
			// La qualité la plus haute dont le fichier tient sous le plafond de poids :
			// une recherche par moitiés, qui s'arrête dès que le poids tombe entre
			// le plafond moins la tolérance et le plafond.
			function jpegSousLePlafond(canvas, plafondOctets, tolerance) {
				var bas = plafondOctets * (100 - tolerance) / 100;
				var lo = 0.3, hi = 0.95, q = 0.9, meilleur = null, essais = 0;
				function tour() {
					return enJpeg(canvas, q).then(function (b) {
						if (b.size <= plafondOctets) {
							meilleur = b;
							if (b.size >= bas || essais >= 8) { return meilleur; }
							lo = q;
						} else {
							hi = q;
							if (essais >= 8 && meilleur) { return meilleur; }
						}
						if (++essais > 10) { return meilleur; }
						q = (lo + hi) / 2;
						return tour();
					});
				}
				return tour().then(function (b) {
					if (!b) { throw new Error('lourd'); }
					return b;
				});
			}
			// L'échelle qui fait tenir une image dans la boîte de la haute définition
			// (10 × 15 cm à 300 ppp par défaut), tournée comme elle.
			function echelleDeLaBoiteHr(w, h) {
				var grand = Math.round(Math.max(CONV.hr.largeurCm, CONV.hr.hauteurCm) * CONV.hr.dpi / 2.54);
				var court = Math.round(Math.min(CONV.hr.largeurCm, CONV.hr.hauteurCm) * CONV.hr.dpi / 2.54);
				return Math.min(grand / Math.max(w, h), court / Math.min(w, h));
			}
			// UTIF ne lit le CMYK qu'en 8 bits par couche : un CMYK en 16 bits,
			// tel que Photoshop l'enregistre pour l'impression, sortait rayé,
			// dédoublé et teinté. Les images en 8 et en 16 bits se lisent donc
			// ici, quelles que soient leurs couleurs ; UTIF garde le reste (1, 2
			// ou 4 bits, palette). Après UTIF.decodeImage, un échantillon de
			// 16 bits a son octet fort en second, quel que soit l'ordre du
			// fichier. Une couche de transparence se pose sur du blanc, puisque
			// le JPEG n'en a pas.
			// Le CMYK vers le RVB sans profil ICC : la formule de pdf.js (licence
			// Apache 2.0), ajustée sur les encres d'imprimerie américaines
			// (SWOP). Le calcul simple, 255 × (1 − c) × (1 − k), donnait un papier
			// ancien vert au lieu de beige.
			var leRvb = [0, 0, 0];
			function rvbDuCmjn(c, m, y, k) {
				leRvb[0] = 255 + c * (-4.387332384609988 * c + 54.48615194189176 * m + 18.82290502165302 * y + 212.25662451639585 * k - 285.2331026137004)
					+ m * (1.7149763477362134 * m - 5.6096736904047315 * y - 17.873870861415444 * k - 5.497006427196366)
					+ y * (-2.5217340131683033 * y - 21.248923337353073 * k + 17.5119270841813) - k * (21.86122147463605 * k + 189.48180835922747);
				leRvb[1] = 255 + c * (8.841041422036149 * c + 60.118027045597366 * m + 6.871425592049007 * y + 31.159100130055922 * k - 79.2970844816548)
					+ m * (-15.310361306967817 * m + 17.575251261109482 * y + 131.35250912493976 * k - 190.9453302588951)
					+ y * (4.444339102852739 * y + 9.8632861493405 * k - 24.86741582555878) - k * (20.737325471181034 * k + 187.80453709719578);
				leRvb[2] = 255 + c * (0.8842522430003296 * c + 8.078677503112928 * m + 30.89978309703729 * y - 0.23883238689178934 * k - 14.183576799673286)
					+ m * (10.49593273432072 * m + 63.02378494754052 * y + 50.606957656360734 * k - 112.23884253719248)
					+ y * (0.03296041114873217 * y + 115.60384449646641 * k - 193.58209356861505) - k * (22.33816807309886 * k + 180.12613974708367);
				return leRvb;
			}
			// Le nom qu'un profil ICC se donne (« sRGB IEC61966-2.1 », « Coated
			// FOGRA39 »…), lu dans son étiquette « desc » : en ASCII dans un
			// profil de version 2, en UTF-16 dans un profil de version 4.
			function descriptionIcc(p) {
				try {
					var v = new DataView(p.buffer, p.byteOffset, p.byteLength), n = Math.min(100, v.getUint32(128)), s = '', k;
					for (var i = 0; i < n && 144 + 12 * i <= p.length; i++) {
						if (v.getUint32(132 + 12 * i) !== 0x64657363) { continue; }
						var off = v.getUint32(136 + 12 * i), type = v.getUint32(off);
						if (type === 0x64657363) {
							var l = Math.min(256, v.getUint32(off + 8));
							for (k = 0; k < l && p[off + 12 + k]; k++) { s += String.fromCharCode(p[off + 12 + k]); }
						} else if (type === 0x6d6c7563) {
							var lg = Math.min(512, v.getUint32(off + 20)), deb = off + v.getUint32(off + 24);
							for (k = 0; k + 1 < lg; k += 2) { s += String.fromCharCode(v.getUint16(deb + k)); }
						}
						return s;
					}
				} catch (e) { /* un profil tronqué n'a pas de nom */ }
				return '';
			}
			// Little CMS, le moteur de couleur de GIMP et d'ImageMagick, compilé
			// pour le navigateur. Il ne se charge qu'au premier profil à lire.
			var lcmsPret = null;
			function chargerLcms() {
				if (!lcmsPret) {
					lcmsPret = import(CONV.lcms + 'lcms.min.js').then(function (mod) {
						return mod.instantiate({ locateFile: function (nom) { return CONV.lcms + nom; } }).then(function (l) {
							return { l: l, mod: mod };
						});
					});
					lcmsPret.catch(function () { lcmsPret = null; });
				}
				return lcmsPret;
			}
			// Une figure préparée pour l'imprimeur porte son profil ICC : FOGRA39
			// pour un CMJN, Adobe RGB pour une photo. Sans lui, le CMJN passait au
			// RVB par une formule réglée sur les encres américaines, et l'Adobe
			// RGB perdait ses couleurs vives : l'auteur ne reconnaissait pas sa
			// figure. Avec lui, Little CMS convertit vers le sRGB comme Photoshop
			// (colorimétrie relative, compensation du point noir).
			// Rend de quoi convertir une suite de pixels, ou null : sans profil,
			// avec un sRGB, ou si Little CMS ne se charge pas, on fait comme avant.
			function preparerLesCouleurs(ifd) {
				var icc = ifd.t34675, intp = ifd.t262 ? ifd.t262[0] : 2, bps = ifd.t258 ? ifd.t258[0] : 1;
				if (!icc || icc.length < 132 || !CONV.lcms || (bps !== 8 && bps !== 16) || (intp !== 2 && intp !== 5)) { return Promise.resolve(null); }
				if (intp === 2 && /srgb/i.test(descriptionIcc(icc))) { return Promise.resolve(null); }
				return chargerLcms().then(function (x) {
					var l = x.l, mod = x.mod;
					var source = l.cmsOpenProfileFromMem(new Uint8Array(icc), icc.length);
					if (!source) { return null; }
					// Un profil qui ne parle pas des couleurs de l'image ne sert à rien.
					if (l.cmsGetColorSpace(source) !== (intp === 5 ? mod.cmsSigCmykData : mod.cmsSigRgbData)) {
						l.cmsCloseProfile(source);
						return null;
					}
					var srgb = l.cmsCreate_sRGBProfile();
					var format = intp === 5 ? (bps === 16 ? mod.TYPE_CMYK_16 : mod.TYPE_CMYK_8) : (bps === 16 ? mod.TYPE_RGB_16 : mod.TYPE_RGB_8);
					var t = l.cmsCreateTransform(source, format, srgb, mod.TYPE_RGB_8, mod.INTENT_RELATIVE_COLORIMETRIC, mod.cmsFLAGS_BLACKPOINTCOMPENSATION);
					l.cmsCloseProfile(source);
					l.cmsCloseProfile(srgb);
					if (!t) { return null; }
					return {
						convertir: function (entree, n) { return l.cmsDoTransform(t, entree, n); },
						fin: function () { l.cmsDeleteTransform(t); }
					};
				}).catch(function () { return null; });
			}
			function rgbaDuTiff(ifd, cms) {
				var bps = ifd.t258 ? ifd.t258[0] : 1, intp = ifd.t262 ? ifd.t262[0] : 2;
				var spp = ifd.t277 ? ifd.t277[0] : (ifd.t258 ? ifd.t258.length : 1);
				var couleurs = { 0: 1, 1: 1, 2: 3, 5: 4 }[intp];
				if ((bps !== 8 && bps !== 16) || !couleurs) { return UTIF.toRGBA8(ifd); }
				// Les couches rangées l'une après l'autre ne se lisent pas.
				if (ifd.t284 && ifd.t284[0] === 2) { throw new Error('illisible'); }
				var n = ifd.width * ifd.height, octets = bps / 8, d = ifd.data;
				if (spp < couleurs || !d || d.length < n * spp * octets) { throw new Error('illisible'); }
				var alpha = spp > couleurs && ifd.t338 ? ifd.t338[0] : 0; // 1 : prémultipliée ; 2 : non
				var out = new Uint8ClampedArray(n * 4), pas = spp * octets, fort = octets - 1;
				// Little CMS reçoit les pixels par blocs : l'image entière, recopiée
				// dans sa mémoire, la doublerait.
				var BLOC = 65536, entree = cms ? new (octets === 2 ? Uint16Array : Uint8Array)(BLOC * couleurs) : null, rvbs = null;
				for (var i = 0, o = fort, q = 0; i < n; i++, o += pas, q += 4) {
					var r, g, b;
					if (cms) {
						var j = i % BLOC;
						if (j === 0) {
							var nb = Math.min(BLOC, n - i), e = nb === BLOC ? entree : entree.subarray(0, nb * couleurs);
							for (var x = 0, s = i * pas, z = 0; x < nb; x++, s += pas) {
								for (var c = 0; c < couleurs; c++, z++) {
									e[z] = octets === 2 ? d[s + 2 * c] | (d[s + 2 * c + 1] << 8) : d[s + c];
								}
							}
							rvbs = cms.convertir(e, nb);
						}
						r = rvbs[3 * j]; g = rvbs[3 * j + 1]; b = rvbs[3 * j + 2];
					} else if (intp === 2) { r = d[o]; g = d[o + octets]; b = d[o + 2 * octets]; }
					else if (intp === 5) { var rvb = rvbDuCmjn(d[o] / 255, d[o + octets] / 255, d[o + 2 * octets] / 255, d[o + 3 * octets] / 255); r = rvb[0]; g = rvb[1]; b = rvb[2]; } else { r = g = b = intp === 0 ? 255 - d[o] : d[o]; }
					if (alpha) {
						var a = d[o + couleurs * octets], blanc = 255 - a;
						if (alpha === 2) { r = r * a / 255; g = g * a / 255; b = b * a / 255; }
						r += blanc; g += blanc; b += blanc;
					}
					out[q] = r; out[q + 1] = g; out[q + 2] = b; out[q + 3] = 255;
				}
				return out;
			}
			// Le TIFF lu en entier, dans un canvas à sa pleine définition, avec la
			// densité qu'il déclare (0 s'il n'en dit rien).
			function decoderLeTiff(f) {
				return lireLeBuffer(f).then(function (buf) {
					var ifds = UTIF.decode(buf), ifd = ifds && ifds[0];
					var w = ifd && ifd.t256 && ifd.t256[0], h = ifd && ifd.t257 && ifd.t257[0];
					if (!w || !h) { throw new Error('illisible'); }
					if (w * h > CONV.pixelsMax) { throw new Error('grand'); }
					UTIF.decodeImage(buf, ifd);
					return preparerLesCouleurs(ifd).then(function (cms) {
						try { return { ifd: ifd, rgba: rgbaDuTiff(ifd, cms), w: w, h: h }; } finally { if (cms) { cms.fin(); } }
					});
				}).then(function (lu) {
					var ifd = lu.ifd, rgba = lu.rgba, w = lu.w, h = lu.h;
					// Un décodage manqué laisse l'image vide : rien d'opaque nulle part.
					var opaque = false;
					for (var p = 3; p < rgba.length; p += 4 * 997) { if (rgba[p] !== 0) { opaque = true; break; } }
					if (!opaque) { throw new Error('illisible'); }
					var plein = document.createElement('canvas');
					plein.width = w; plein.height = h;
					plein.getContext('2d').putImageData(new ImageData(new Uint8ClampedArray(rgba.buffer, rgba.byteOffset, w * h * 4), w, h), 0, 0);
					// La résolution, en pouces (2) ou en centimètres (3).
					var x = ifd.t282 && ifd.t282[0], unite = ifd.t296 ? ifd.t296[0] : 2, dpi = 0;
					if (x > 0 && (unite === 2 || unite === 3)) { dpi = Math.round(unite === 3 ? x * 2.54 : x); }
					return { plein: plein, w: w, h: h, dpi: dpi };
				});
			}
			function convertirLeTiff(f) {
				var base = f.name.replace(/\.[^.]+$/, '');
				return decoderLeTiff(f).then(function (t) {
					var plein = t.plein, w = t.w, h = t.h;
					// La haute définition : dans la boîte 10 × 15 cm à 300 ppp, tournée comme l'image.
					var dpiHr = CONV.hr.dpi;
					var echelleHr = Math.min(1, echelleDeLaBoiteHr(w, h));
					var cHr = reduireLeCanvas(plein, Math.round(w * echelleHr), Math.round(h * echelleHr));
					// La basse définition : le grand côté ne dépasse pas la mesure réglée.
					var echelleBr = Math.min(1, CONV.br.cote / Math.max(w, h));
					var cBr = reduireLeCanvas(plein, Math.round(w * echelleBr), Math.round(h * echelleBr));
					plein.width = plein.height = 1;
					return enJpeg(cHr, CONV.hr.qualite / 100).then(function (b) {
						return etiqueterLeJpeg(b, dpiHr, base + '-hr.jpg');
					}).then(function (hr) {
						return jpegSousLePlafond(cBr, CONV.br.poidsKo * 1024, CONV.br.tolerance).then(function (b) {
							return etiqueterLeJpeg(b, CONV.br.dpi, base + '-br.jpg');
						}).then(function (br) { return { hr: hr, br: br }; });
					});
				});
			}
			// Un PDF : pdf.js, chargé à la demande, dessine la première page aux
			// mesures des deux définitions, comme pour un TIFF. Le PDF d'origine
			// reste dans « originaux ».
			function chargerPdfJs() {
				if (window.pdfjsLib) { return Promise.resolve(window.pdfjsLib); }
				var racine = CONV.pdfjs || CONV.lecteurPdf;
				if (!racine) { return Promise.reject(new Error('pdfjs')); }
				return new Promise(function (ok, ko) {
					var s = document.createElement('script');
					s.src = racine + 'pdf.min.js';
					s.onload = function () {
						if (!window.pdfjsLib) { ko(new Error('pdfjs')); return; }
						window.pdfjsLib.GlobalWorkerOptions.workerSrc = racine + 'pdf.worker.min.js';
						ok(window.pdfjsLib);
					};
					s.onerror = function () { ko(new Error('pdfjs')); };
					document.head.appendChild(s);
				});
			}
			function convertirLePdf(f) {
				var base = f.name.replace(/\.[^.]+$/, '');
				var delai = new Promise(function (ok, ko) { window.setTimeout(function () { ko(new Error('delai')); }, 60000); });
				var travail = Promise.all([chargerPdfJs(), lireLeBuffer(f)]).then(function (r) {
					// Sans « eval » : un PDF n'a pas à faire exécuter de code à la bibliothèque.
					return r[0].getDocument({ data: new Uint8Array(r[1]), isEvalSupported: false, enableXfa: false }).promise;
				}).then(function (pdf) {
					return pdf.getPage(1).then(function (page) {
						var v1 = page.getViewport({ scale: 1 });
						function dessiner(echelle) {
							var vue = page.getViewport({ scale: echelle });
							var canvas = document.createElement('canvas');
							canvas.width = Math.max(1, Math.round(vue.width));
							canvas.height = Math.max(1, Math.round(vue.height));
							if (canvas.width * canvas.height > CONV.pixelsMax) { return Promise.reject(new Error('grand')); }
							return page.render({ canvasContext: canvas.getContext('2d'), viewport: vue, background: '#ffffff' }).promise.then(function () { return canvas; });
						}
						// La haute définition : un JPEG dans la boîte 10 × 15 cm à 300 ppp, comme
						// celle d'un TIFF — l'auteur passait ses PDF en JPEG pour la mise en
						// page. Le PDF d'origine reste, lui, dans « originaux ».
						return dessiner(echelleDeLaBoiteHr(v1.width, v1.height)).then(function (cHr) {
							return enJpeg(cHr, CONV.hr.qualite / 100);
						}).then(function (b) {
							return etiqueterLeJpeg(b, CONV.hr.dpi, base + '-hr.jpg');
						}).then(function (hr) {
							// La basse définition : le grand côté à la mesure réglée.
							return dessiner(CONV.br.cote / Math.max(v1.width, v1.height)).then(function (cBr) {
								return jpegSousLePlafond(cBr, CONV.br.poidsKo * 1024, CONV.br.tolerance);
							}).then(function (b) {
								return etiqueterLeJpeg(b, CONV.br.dpi, base + '-br.jpg');
							}).then(function (br) {
								var pages = pdf.numPages;
								try { pdf.destroy(); } catch (x) {}
								return { hr: hr, br: br, pages: pages };
							});
						});
					});
				});
				return Promise.race([travail, delai]);
			}
			function aConvertir(f) {
				var ext = extOf(f.name);
				return !CONV.imagick && (ext === 'tif' || ext === 'tiff' || ext === 'pdf');
			}
			// Les JPEG convertis suivent leur figure dans des champs cachés, par
			// rang : une figure déplacée ou retirée emporte les siens.
			var champsConv = {};
			function champDeConversion(famille, rang) {
				var nom = 'na_conv_' + famille + '_' + rang;
				if (!champsConv[nom]) {
					var c = document.createElement('input');
					c.type = 'file'; c.name = nom; c.hidden = true; c.tabIndex = -1;
					c.setAttribute('aria-hidden', 'true');
					form.appendChild(c);
					champsConv[nom] = c;
				}
				return champsConv[nom];
			}
			function poserLesConversions() {
				if (CONV.imagick || !CONV_PRET) { return; }
				for (var r = 1; r <= MAX_FILES; r++) {
					['hr', 'br'].forEach(function (famille) {
						var f = selected[r - 1], e = f ? etatDe(f).conv : null;
						try {
							var dt = new DataTransfer();
							if (e && e.etat === 'ok' && e[famille]) { dt.items.add(e[famille]); }
							champDeConversion(famille, r).files = dt.files;
						} catch (x) {}
					});
				}
			}
			function messageDeConversion(f, erreur) {
				var raison = erreur && erreur.message;
				var fin = ' Enregistrez la figure vous-même en JPEG de bonne qualité, puis redéposez-la.';
				if (raison === 'plafond' && peutSAlleger(f)) {
					return guillemets(f.name) + ' ferait dépasser ' + moPlafond(MAX_TOTAL_SIZE) + ' en tout une fois converti.' + ALLEGER_FIN;
				}
				if (raison === 'delai') {
					return guillemets(f.name) + ' est trop long à lire dans votre navigateur.' + fin;
				}
				if (raison === 'grand') {
					return guillemets(f.name) + ' est trop grand pour être converti dans le navigateur (plus de ' + entier(CONV.pixelsMax / 1000000) + NBSP + 'millions de pixels).' + fin;
				}
				if (raison === 'plafond') {
					return guillemets(f.name) + ' ferait dépasser ' + moPlafond(MAX_TOTAL_SIZE) + ' en tout une fois converti.' + fin;
				}
				return guillemets(f.name) + ' n’a pas pu être lu par votre navigateur.' + fin;
			}
			function majLeStatutDeConversion(f) {
				var e = etatDe(f), el = e.statutEl;
				if (!el || !el.parentNode || !e.conv || e.conv.etat !== 'ok') { return; }
				el.parentNode.replaceChild(vignetteDe(e.vignette, f.name), el);
				e.statutEl = null;
			}
			// Quand le serveur convertit le TIFF et le PDF, la carte n'avait plus
			// d'image : la vignette venait de la conversion faite ici. Le
			// navigateur en tire une pour l'écran seulement, rien n'en part avec
			// le dépôt. Ce qu'il ne sait pas lire garde « Pas d'aperçu », sans
			// refus : le serveur, lui, le lira.
			var VIGNETTE_COTE = 1200;
			// L'image d'écran d'un TIFF ou d'un PDF, en JPEG : la première page,
			// réduite à VIGNETTE_COTE pixels de côté.
			function dessinerLaVignette(f) {
				var travail;
				if (extOf(f.name) === 'pdf') {
					travail = Promise.all([chargerPdfJs(), lireLeBuffer(f)]).then(function (r) {
						return r[0].getDocument({ data: new Uint8Array(r[1]), isEvalSupported: false, enableXfa: false }).promise;
					}).then(function (pdf) {
						return pdf.getPage(1).then(function (page) {
							var v1 = page.getViewport({ scale: 1 });
							var vue = page.getViewport({ scale: VIGNETTE_COTE / Math.max(v1.width, v1.height) });
							var c = document.createElement('canvas');
							c.width = Math.max(1, Math.round(vue.width));
							c.height = Math.max(1, Math.round(vue.height));
							return page.render({ canvasContext: c.getContext('2d'), viewport: vue, background: '#ffffff' }).promise.then(function () {
								try { pdf.destroy(); } catch (x) {}
								return c;
							});
						});
					});
				} else {
					travail = chargerUtif().then(function () { return decoderLeTiff(f); }).then(function (t) {
						var echelle = Math.min(1, VIGNETTE_COTE / Math.max(t.w, t.h));
						var c = reduireLeCanvas(t.plein, Math.round(t.w * echelle), Math.round(t.h * echelle));
						t.plein.width = t.plein.height = 1;
						return c;
					});
				}
				return travail.then(function (c) { return enJpeg(c, 0.85); });
			}
			function tirerLaVignette(f) {
				var e = etatDe(f), ext = extOf(f.name);
				if (e.vignette || e.vignetteEnCours || !ALLEGER_BASE || !(window.URL && URL.createObjectURL)) { return; }
				e.vignetteEnCours = true;
				if (e.statutEl) { e.statutEl.textContent = 'Aperçu du ' + ext.toUpperCase() + ' en préparation…'; }
				dessinerLaVignette(f).then(function (b) {
					e.vignetteEnCours = false;
					if (selected.indexOf(f) === -1) { return; }
					e.vignette = URL.createObjectURL(b);
					var el = e.statutEl;
					if (el && el.parentNode) {
						el.parentNode.replaceChild(vignetteDe(e.vignette, f.name), el);
						e.statutEl = null;
					}
				}).catch(function () {
					e.vignetteEnCours = false;
					if (e.statutEl) { e.statutEl.textContent = 'Pas d’aperçu pour ce format (' + ext.toUpperCase() + ')' + NBSP + ': le fichier est bien reçu.'; }
				});
			}
			// La vignette est un bouton : on la clique pour voir l'image en grand,
			// et décrire dans la légende ce qu'elle montre vraiment. La fenêtre se
			// ferme par Échap, par son bouton, ou d'un clic à côté de l'image.
			var agrandie = null;
			function vignetteDe(src, nom) {
				var b = document.createElement('button');
				b.type = 'button';
				b.className = 'na-illus-agrandir';
				b.setAttribute('aria-label', 'Voir en grand ' + guillemets(nom));
				var im = document.createElement('img');
				im.src = src; im.alt = ''; im.className = 'na-illus-vignette';
				b.appendChild(im);
				b.addEventListener('click', function () { voirEnGrand(src, nom, b); });
				return b;
			}
			function voirEnGrand(src, nom, retour) {
				if (!agrandie) {
					agrandie = document.createElement('dialog');
					if (typeof agrandie.showModal !== 'function') {
						// Un navigateur sans fenêtre de dialogue : l'image dans un onglet.
						agrandie = null;
						window.open(src, '_blank', 'noopener');
						return;
					}
					agrandie.className = 'na-agrandie';
					agrandie.appendChild(document.createElement('img'));
					var bas = document.createElement('p');
					bas.className = 'na-agrandie-bas';
					bas.appendChild(document.createElement('span'));
					var fermer = document.createElement('button');
					fermer.type = 'button';
					fermer.className = 'na-bouton-lien';
					fermer.textContent = 'Fermer';
					fermer.addEventListener('click', function () { agrandie.close(); });
					bas.appendChild(fermer);
					agrandie.appendChild(bas);
					agrandie.addEventListener('click', function (e) { if (e.target === agrandie) { agrandie.close(); } });
					// L'image quittée ne reste pas en mémoire de la fenêtre, et le
					// clavier revient à la vignette : Safari ne le fait pas de lui-même.
					agrandie.addEventListener('close', function () {
						agrandie.querySelector('img').removeAttribute('src');
						if (agrandie.retour && document.body.contains(agrandie.retour)) { agrandie.retour.focus(); }
					});
					form.appendChild(agrandie);
				}
				var img = agrandie.querySelector('img');
				img.src = src;
				img.alt = 'Image ' + guillemets(nom) + ' en grand';
				agrandie.querySelector('.na-agrandie-bas span').textContent = nom;
				agrandie.setAttribute('aria-label', nom);
				agrandie.retour = retour;
				agrandie.showModal();
			}
			function lancerLaConversion(f) {
				(extOf(f.name) === 'pdf' ? convertirLePdf(f) : convertirLeTiff(f)).then(function (r) {
					var e = etatDe(f);
					e.conv = { etat: 'ok', hr: r.hr, br: r.br, pages: r.pages || 0 };
					if (selected.indexOf(f) !== -1 && totalSelectedSize() > MAX_TOTAL_SIZE) { throw new Error('plafond'); }
					try { e.vignette = URL.createObjectURL(r.br); } catch (x) {}
					poserLesConversions();
					majLeStatutDeConversion(f);
					annoncer(guillemets(f.name) + ' est converti.' + (r.pages > 1 ? ' Seule la première page de ce PDF est retenue.' : ''));
					if (r.pages > 1) {
						refuses.textContent = (refuses.textContent ? refuses.textContent + ' ' : '') + guillemets(f.name) + ' compte ' + r.pages + ' pages : seule la première est retenue pour la figure.';
					}
				}).catch(function (erreur) {
					var i = selected.indexOf(f);
					etatDe(f).conv = null;
					if (i !== -1) {
						selected.splice(i, 1);
						illusMeta.splice(i, 1);
						illusAuto.splice(i, 1);
						refreshFileInput();
						majConseilsDuTexte();
					}
					var phrase = messageDeConversion(f, erreur);
					if (erreur && erreur.message === 'plafond') { proposerDAlleger(f, phrase); }
					refuses.textContent = (refuses.textContent ? refuses.textContent + ' ' : '') + phrase;
				});
			}
			// ── Un TIFF trop lourd, passé en JPEG de haute qualité ─────────
			// Refusé pour son poids, un TIFF peut partir en JPEG à sa pleine
			// définition, sans l'original : dix à vingt fois plus léger, et
			// sans perte visible à l'impression. Le navigateur le fait sur un
			// bouton, sous le refus ; le JPEG prend alors la place du TIFF,
			// comme si l'auteur l'avait choisi lui-même. S'il ne tient pas
			// encore, sa définition descend, jamais sous la norme des
			// photographies.
			var ALLEGER_FIN = ' En JPEG de haute qualité, sans l’original TIFF, l’image pèserait bien moins'
				+ NBSP + ': le bouton ci-dessous la convertit.';
			var refusZone = document.getElementById('na-refus');
			var allegerEl = document.getElementById('na-alleger');
			var aAlleger = [];
			// Les refus encore à dire quand le JPEG allégé entre dans la liste.
			var refusGardes = '';
			// Sans Imagick, le lecteur de TIFF est déjà là ; avec lui, on ne le
			// charge qu'au premier TIFF à alléger.
			var ALLEGER_BASE = !!document.createElement('canvas').getContext && typeof Promise !== 'undefined'
				&& (function () { try { new DataTransfer(); return true; } catch (e) { return false; } }());
			var utifEnRoute = null;
			function chargerUtif() {
				if (typeof UTIF !== 'undefined') { return Promise.resolve(); }
				if (!utifEnRoute) {
					utifEnRoute = (CONV.utif || []).reduce(function (avant, src) {
						return avant.then(function () {
							return new Promise(function (ok, ko) {
								var s = document.createElement('script');
								s.src = src;
								s.onload = ok;
								s.onerror = function () { ko(new Error('illisible')); };
								document.head.appendChild(s);
							});
						});
					}, Promise.resolve()).then(function () {
						if (typeof UTIF === 'undefined') { throw new Error('illisible'); }
					}).catch(function (erreur) { utifEnRoute = null; throw erreur; });
				}
				return utifEnRoute;
			}
			function peutSAlleger(f) {
				var ext = extOf(f.name);
				return ALLEGER_BASE && !!allegerEl && (ext === 'tif' || ext === 'tiff')
					&& (typeof UTIF !== 'undefined' || !!(CONV.utif && CONV.utif.length));
			}
			function proposerDAlleger(f, phrase) {
				if (!peutSAlleger(f)) { return; }
				aAlleger = aAlleger.filter(function (p) { return p.f !== f; });
				aAlleger.push({ f: f, phrase: phrase });
			}
			function dessinerLesAllegements() {
				if (!allegerEl) { return; }
				allegerEl.textContent = '';
				aAlleger.forEach(function (p) {
					var b = document.createElement('button');
					b.type = 'button';
					b.className = 'na-ajout';
					b.textContent = p.enCours ? 'Conversion de ' + guillemets(p.f.name) + ' en cours…' : 'Passer ' + guillemets(p.f.name) + ' en JPEG de haute qualité';
					b.disabled = !!p.enCours;
					b.addEventListener('click', function () { alleger(p); });
					allegerEl.appendChild(b);
				});
				allegerEl.hidden = !aAlleger.length;
			}
			// Le JPEG le plus fin qui tienne dans la place : pleine définition
			// d'abord, puis réduite par paliers, jusqu'à la norme au plus bas.
			function allegerLeTiff(f, place) {
				var base = f.name.replace(/\.[^.]+$/, '');
				return chargerUtif().then(function () { return decoderLeTiff(f); }).then(function (t) {
					var mini = Math.min(1, echelleDeLaBoiteHr(t.w, t.h)), echelle = 1, essais = 0;
					function tour() {
						var c = echelle >= 1 ? t.plein
							: reduireLeCanvas(t.plein, Math.max(1, Math.round(t.w * echelle)), Math.max(1, Math.round(t.h * echelle)));
						return enJpeg(c, 0.92).then(function (b) {
							if (b.size <= place) { return { blob: b, echelle: echelle }; }
							if (echelle <= mini || ++essais > 6) { throw new Error('lourd'); }
							echelle = Math.max(mini, echelle * Math.sqrt(place / b.size) * 0.95);
							return tour();
						});
					}
					return tour().then(function (r) {
						t.plein.width = t.plein.height = 1;
						var dpi = t.dpi ? Math.round(t.dpi * r.echelle) : CONV.hr.dpi;
						return etiqueterLeJpeg(r.blob, Math.max(72, Math.min(65535, dpi)), base + '.jpg');
					}, function (erreur) {
						t.plein.width = t.plein.height = 1;
						throw erreur;
					});
				});
			}
			function alleger(p) {
				var f = p.f;
				function remplacer(texte) {
					refuses.textContent = refuses.textContent.replace(p.phrase, texte);
				}
				if (decalage() + selected.length >= MAX_FILES) {
					remplacer(guillemets(f.name) + NBSP + ': trois figures au plus en tout, retirez-en une avant de l’ajouter.');
					return;
				}
				// La place qui reste, moins de quoi écrire la densité dans l'en-tête.
				var place = Math.min(MAX_FILE_SIZE, MAX_TOTAL_SIZE - totalSelectedSize()) - 1024;
				if (place < 102400) {
					remplacer(guillemets(f.name) + NBSP + ': il ne reste plus de place, même pour un JPEG. Retirez une figure, ou envoyez celle-ci à part.');
					return;
				}
				p.enCours = true;
				dessinerLesAllegements();
				annoncer('Conversion de ' + guillemets(f.name) + ' en JPEG…');
				allegerLeTiff(f, place).then(function (jpeg) {
					aAlleger = aAlleger.filter(function (q) { return q !== p; });
					refusGardes = refuses.textContent.replace(p.phrase, '').replace(/\s+/g, ' ').trim();
					annoncer(guillemets(f.name) + ' est passé en JPEG' + NBSP + ': ' + mo(jpeg.size) + ' au lieu de ' + mo(f.size) + '.');
					var dt = new DataTransfer();
					dt.items.add(jpeg);
					fileInput.files = dt.files;
					fileInput.dispatchEvent(new Event('change'));
				}).catch(function (erreur) {
					p.enCours = false;
					var raison = erreur && erreur.message;
					if (raison === 'lourd') {
						remplacer(guillemets(f.name) + ' reste trop lourd, même en JPEG' + NBSP + ': retirez une autre figure, ou envoyez celle-ci à part.');
					} else if (raison === 'grand') {
						remplacer(guillemets(f.name) + ' est trop grand pour être converti dans le navigateur (plus de ' + entier(CONV.pixelsMax / 1000000) + NBSP + 'millions de pixels). Enregistrez-le vous-même en JPEG, ou envoyez-le à part.');
					} else {
						remplacer(guillemets(f.name) + ' n’a pas pu être lu par votre navigateur. Enregistrez-le vous-même en JPEG, puis redéposez-le.');
					}
					dessinerLesAllegements();
				});
			}
			// Le refus se dit là où l'on ajoute : sous la dernière carte quand il
			// y en a, sous la zone de dépôt sinon. Il ne se cache plus au-dessus
			// des figures, où on le découvrait des minutes plus tard. Un bouton
			// d'allègement ne vit qu'avec la phrase qui le propose.
			function placerLesRefus() {
				if (!refusZone) { return; }
				aAlleger = aAlleger.filter(function (p) { return refuses.textContent.indexOf(p.phrase) !== -1; });
				dessinerLesAllegements();
				if (!refuses.textContent) { return; }
				var avant = selected.length ? ajoutFigure : fileList;
				if (refusZone.nextElementSibling !== avant) { avant.parentNode.insertBefore(refusZone, avant); }
				try { refusZone.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); } catch (x) { refusZone.scrollIntoView(false); }
			}
			if (refusZone && window.MutationObserver) {
				new MutationObserver(placerLesRefus).observe(refuses, { childList: true, characterData: true, subtree: true });
			}
			// ── Les figures déjà déposées, quand on corrige une notice ─────
			// Leurs cartes viennent du serveur, avant celles des fichiers
			// nouveaux, qui les suivent en numéro : deux figures gardées, et le
			// premier fichier choisi devient la figure 3. Une figure retirée ne
			// compte plus, ni dans les numéros ni dans les trois permises.
			var gardees = [].map.call(form.querySelectorAll('.na-garde'), function (li) {
				var k = li.getAttribute('data-k'), base = 'na-garde-' + k;
				return {
					li: li, k: k, conv: null,
					fichier: document.getElementById(base + '-fichier'),
					auto: document.getElementById(base + '-autorisation'),
					remplacement: document.getElementById(base + '-remplacement'),
					aPart: document.getElementById(base + '-a-part'),
					champs: li.querySelector('.na-garde-champs'),
					numero: li.querySelector('.na-garde-numero')
				};
			});
			// Leurs textes se mettent en forme comme ceux des figures nouvelles :
			// la légende, son complément, la description détaillée, chacun dans
			// son éditeur, la forme gardée au champ caché voisin.
			var gardeesRiches = [];
			if (quill) {
				gardees.forEach(function (g) {
					var base = 'na-garde-' + g.k;
					[['titre', false, false, 'Mise en forme de la légende'],
						['legende', true, false, 'Mise en forme du complément de légende'],
						['alt', true, true, 'Mise en forme de la description détaillée']].forEach(function (d) {
						var riche = naFigureRiche(document.getElementById(base + '-' + d[0]), document.getElementById(base + '-' + d[0] + '-forme'),
							{ lignes: d[1], premierBrut: d[2], barre: d[3] });
						if (riche) { gardeesRiches.push(riche); }
					});
				});
			}
			function actionDe(g) {
				var c = g.li.querySelector('input[name="garde_action[' + g.k + ']"]:checked');
				return c ? c.value : 'garder';
			}
			function decalage() {
				return gardees.filter(function (g) { return actionDe(g) !== 'retirer'; }).length;
			}
			function poidsDesGardees() {
				return gardees.reduce(function (sum, g) {
					if (actionDe(g) === 'retirer') { return sum; }
					var f = actionDe(g) === 'remplacer' && g.fichier.files && g.fichier.files[0], c = g.conv;
					return sum + (f ? f.size : 0) + (f && c && c.etat === 'ok' ? (c.hr ? c.hr.size : 0) + c.br.size : 0)
						+ (g.auto && g.auto.files && g.auto.files[0] ? g.auto.files[0].size : 0);
				}, 0);
			}
			function poserLaConversionGardee(g) {
				if (CONV.imagick || !CONV_PRET) { return; }
				['hr', 'br'].forEach(function (famille) {
					try {
						var dt = new DataTransfer();
						if (g.conv && g.conv.etat === 'ok' && g.conv[famille] && actionDe(g) === 'remplacer') { dt.items.add(g.conv[famille]); }
						champDeConversion('garde_' + famille, g.k).files = dt.files;
					} catch (x) {}
				});
			}
			// Le fichier qui remplace une figure déjà déposée se montre sur sa
			// carte, comme celui d'une figure nouvelle : on voit d'un coup d'œil
			// si l'on a pris la bonne image. « image » est l'image d'écran déjà
			// tirée — la basse définition d'un TIFF converti ici ; sans elle, un
			// JPEG se montre tel quel, un TIFF ou un PDF se dessine à part.
			function montrerLeRemplacant(g, f, image, statut) {
				if (g.vignetteUrl) { try { URL.revokeObjectURL(g.vignetteUrl); } catch (x) {} }
				g.vignetteUrl = null;
				if (g.vignetteEl && g.vignetteEl.parentNode) { g.vignetteEl.parentNode.removeChild(g.vignetteEl); }
				g.vignetteEl = null;
				if (!f || !(window.URL && URL.createObjectURL)) { return; }
				var ext = extOf(f.name);
				var poser = function (el) { g.vignetteEl = el; g.remplacement.appendChild(el); };
				if (image || ext === 'jpg' || ext === 'jpeg' || /^image\/(jpeg|png)$/.test(f.type)) {
					g.vignetteUrl = URL.createObjectURL(image || f);
					poser(vignetteDe(g.vignetteUrl, f.name));
					return;
				}
				var sans = document.createElement('p');
				sans.className = 'na-illus-sans-apercu';
				sans.setAttribute('role', 'status');
				var aucun = 'Pas d’aperçu pour ce format (' + ext.toUpperCase() + ')' + NBSP + ': le fichier est bien reçu.';
				poser(sans);
				if (statut) { sans.textContent = statut; return; }
				if (!ALLEGER_BASE) { sans.textContent = aucun; return; }
				sans.textContent = 'Aperçu du ' + ext.toUpperCase() + ' en préparation…';
				dessinerLaVignette(f).then(function (b) {
					if (g.vignetteEl !== sans || !(g.fichier.files && g.fichier.files[0] === f)) { return; }
					montrerLeRemplacant(g, f, b);
				}).catch(function () {
					if (g.vignetteEl === sans) { sans.textContent = aucun; }
				});
			}
			function majGardees() {
				var n = 0;
				gardees.forEach(function (g) {
					var a = actionDe(g);
					g.li.classList.toggle('na-garde-retiree', a === 'retirer');
					g.remplacement.hidden = a !== 'remplacer';
					if (g.aPart) { g.aPart.hidden = a !== 'garder'; }
					g.champs.hidden = a === 'retirer';
					g.numero.textContent = a === 'retirer' ? 'Figure retirée' : 'Figure ' + (++n);
					poserLaConversionGardee(g);
				});
			}
			gardees.forEach(function (g) {
				[].forEach.call(g.li.querySelectorAll('input[name="garde_action[' + g.k + ']"]'), function (r) {
					r.addEventListener('change', function () {
						majGardees();
						renderFileList();
						majConseilsDuTexte();
						marquerModifie();
						// Revenu à « La garder », le fichier manquant n'est plus à dire.
						if (typeof reverifier === 'function') { reverifier('garde_fichier_' + g.k); }
						if (actionDe(g) === 'retirer') {
							annoncer('Figure retirée' + NBSP + ': les suivantes changent de numéro. Vérifiez les appels du texte.');
						} else if (decalage() + selected.length > MAX_FILES) {
							// Reprise après qu'on a choisi d'autres fichiers : il y a
							// maintenant une figure de trop, et l'envoi le refusera.
							refuses.textContent = 'Trois figures au plus en tout' + NBSP + ': retirez une figure avant d’envoyer.';
						}
					});
				});
				g.fichier.addEventListener('change', function () {
					var f = g.fichier.files && g.fichier.files[0], refus = '';
					g.conv = null;
					if (f) {
						var ext = extOf(f.name);
						if (ALLOWED.indexOf(ext) === -1) {
							refus = guillemets(f.name) + ' n’est pas en JPEG, TIFF ou PDF.';
						} else if (aConvertir(f) && !(ext === 'pdf' ? PDF_PRET : CONV_PRET)) {
							refus = guillemets(f.name) + ' : ce navigateur ne sait pas convertir un ' + ext.toUpperCase() + '. Enregistrez la figure en JPEG, puis redéposez-la.';
						} else if (f.size > MAX_FILE_SIZE) {
							refus = guillemets(f.name) + ' dépasse ' + moPlafond(MAX_FILE_SIZE) + ', le plus que le serveur accepte par fichier.';
						} else if (totalSelectedSize() > MAX_TOTAL_SIZE) {
							refus = guillemets(f.name) + ' ferait dépasser ' + moPlafond(MAX_TOTAL_SIZE) + ' en tout.';
						}
					}
					if (refus) { g.fichier.value = ''; f = null; }
					refuses.textContent = refus;
					poserLaConversionGardee(g);
					montrerLeRemplacant(g, f, null, f && aConvertir(f) ? 'Conversion du ' + extOf(f.name).toUpperCase() + ' en cours…' : '');
					if (f && aConvertir(f)) {
						g.conv = { etat: 'cours' };
						(extOf(f.name) === 'pdf' ? convertirLePdf(f) : convertirLeTiff(f)).then(function (r) {
							if (!(g.fichier.files && g.fichier.files[0] === f)) { return; }
							g.conv = { etat: 'ok', hr: r.hr, br: r.br, pages: r.pages || 0 };
							if (totalSelectedSize() > MAX_TOTAL_SIZE) { throw new Error('plafond'); }
							poserLaConversionGardee(g);
							montrerLeRemplacant(g, f, r.br);
							renderFileList();
							annoncer(guillemets(f.name) + ' est converti.' + (r.pages > 1 ? ' Seule la première page de ce PDF est retenue.' : ''));
						}).catch(function (erreur) {
							if (!(g.fichier.files && g.fichier.files[0] === f)) { return; }
							g.conv = null;
							g.fichier.value = '';
							poserLaConversionGardee(g);
							montrerLeRemplacant(g, null);
							renderFileList();
							refuses.textContent = messageDeConversion(f, erreur);
						});
					}
					renderFileList();
					marquerModifie();
				});
				if (g.auto) {
					g.auto.addEventListener('change', function () {
						var f = g.auto.files && g.auto.files[0], refus = '';
						if (f && ['pdf', 'jpg', 'jpeg', 'png'].indexOf(extOf(f.name)) === -1) {
							refus = guillemets(f.name) + ' n’est pas en PDF, JPEG ou PNG.';
						} else if (f && f.size > MAX_AUTORISATION) {
							refus = guillemets(f.name) + ' dépasse ' + moPlafond(MAX_AUTORISATION) + '.';
						} else if (f && totalSelectedSize() > MAX_TOTAL_SIZE) {
							refus = guillemets(f.name) + ' ferait dépasser ' + moPlafond(MAX_TOTAL_SIZE) + ' en tout.';
						}
						if (refus) { g.auto.value = ''; }
						refuses.textContent = refus;
						renderFileList();
						marquerModifie();
					});
				}
				[].forEach.call(g.champs.querySelectorAll('input[type="text"], textarea, select'), function (c) {
					c.addEventListener(c.tagName === 'SELECT' ? 'change' : 'input', marquerModifie);
				});
				if (g.aPart) {
					[].forEach.call(g.aPart.querySelectorAll('input'), function (c) {
						c.addEventListener(c.type === 'date' ? 'change' : 'input', function () {
							marquerModifie();
							if (typeof reverifier === 'function') { reverifier('garde_lien_' + g.k); reverifier('garde_jusqua_' + g.k); }
						});
						if (c.id === 'na-garde-' + g.k + '-lien') {
							c.addEventListener('blur', function () {
								normaliserUneAdresse(c);
								if (typeof reverifier === 'function') { reverifier('garde_lien_' + g.k); }
							});
						}
					});
				}
			});
			majGardees();
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
						// Le champ unique pour l'écoute : le texte alternatif,
						// puis à la ligne la description d'une saisie ancienne.
						'alt'     => trim( ( isset( $reprise['illus_alt'][ $i ] ) ? $reprise['illus_alt'][ $i ] : '' )
							. ( ! empty( $reprise['illus_description'][ $i ] ) ? "\n" . $reprise['illus_description'][ $i ] : '' ) ),
						'description' => '',
						// La mise en forme des trois textes, gardée à part :
						// l'éditeur la reprend si elle s'accorde au texte.
						'titreForme'       => isset( $reprise['illus_titre_forme'][ $i ] ) ? (string) $reprise['illus_titre_forme'][ $i ] : '',
						'legendeForme'     => isset( $reprise['illus_legende_forme'][ $i ] ) ? (string) $reprise['illus_legende_forme'][ $i ] : '',
						'descriptionForme' => isset( $reprise['illus_description_forme'][ $i ] ) ? (string) $reprise['illus_description_forme'][ $i ] : '',
						'type'    => isset( $reprise['illus_type'][ $i ] ) ? Notice_Archeomed_Controles::type_de_figure( $reprise['illus_type'][ $i ] ) : '',
						'nom'     => isset( $reprise['illus_nom'][ $i ] ) ? $reprise['illus_nom'][ $i ] : '',
						// L'autorisation jointe au dépôt d'avant : le fichier ne
						// revient pas, la figure dit qu'il est à rejoindre.
						'autoAvant' => ! empty( $reprise['illus_autorisation'][ $i ] ),
						'numero'  => $i + 1,
					);
					// La figure envoyée à part revient comme une carte : elle
					// n'a pas de fichier à attendre.
					if ( isset( $reprise['illus_source'][ $i ] ) && 'a_part' === $reprise['illus_source'][ $i ] ) {
						$j = count( $garde_illus ) - 1;
						$garde_illus[ $j ]['aPart']    = true;
						$garde_illus[ $j ]['nom']      = '';
						$garde_illus[ $j ]['lien']     = isset( $reprise['illus_lien'][ $i ] ) ? (string) $reprise['illus_lien'][ $i ] : '';
						$garde_illus[ $j ]['jusqua']   = isset( $reprise['illus_jusqua'][ $i ] ) ? (string) $reprise['illus_jusqua'][ $i ] : '';
						$garde_illus[ $j ]['nomEnvoi'] = isset( $reprise['illus_nom_envoi'][ $i ] ) ? (string) $reprise['illus_nom_envoi'][ $i ] : '';
					}
				}
				echo wp_json_encode( $garde_illus, JSON_HEX_TAG | JSON_HEX_AMP );
			?>.filter(function (m) { return m && (m.aPart || m.nom || aDuTexte(m)); });
			function aDuTexte(m) { return !!m && !(vide(m.titre) && vide(m.legende) && vide(m.credits) && vide(m.alt) && vide(m.description)); }
			// Une figure envoyée à part : une carte sans fichier, qui tient sa
			// place parmi les autres — même numéro, mêmes textes, même rang dans
			// le maximum de trois — et porte le lien où la rédaction ira la
			// chercher. Ses textes voyagent avec elle.
			function figureAPart(m) {
				m = m || {};
				m.aPart = true;
				m.nom = '';
				return { aPart: true, name: '', size: 0, type: '', meta: m };
			}
			function nomDeCarte(f) { return f.aPart ? 'envoyée à part' : f.name; }
			// Revenues d'un refus ou d'un brouillon, les figures envoyées à part
			// reprennent leur carte aussitôt, dans l'ordre de leur numéro : elles
			// n'ont pas de fichier à attendre.
			function reprendreLesFiguresAPart(liste) {
				var reste = [];
				liste.filter(function (m) { return m && m.aPart; })
					.sort(function (a, b) { return (a.numero || 99) - (b.numero || 99); })
					.forEach(function (m) {
						if (decalage() + selected.length < MAX_FILES) { selected.push(figureAPart(m)); }
					});
				liste.forEach(function (m) { if (m && !m.aPart) { reste.push(m); } });
				return reste;
			}
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
					// Une figure envoyée à part porte ses textes avec elle : un
					// fichier sans nom ne doit pas les lui prendre.
					if (f.aPart) {
						j = libres.indexOf(f.meta);
						if (j !== -1) { libres.splice(j, 1); }
						if (!f.meta.numero) { f.meta.numero = decalage() + i + 1; }
						if (aUneAutorisation(f.meta.auto) && !illusAuto[i]) { illusAuto[i] = f.meta.auto; }
						neuf[i] = f.meta;
						return;
					}
					for (k = 0; k < libres.length && j === -1; k++) {
						if (libres[k] && !libres[k].aPart && libres[k].nom === f.name) { j = k; }
					}
					for (k = 0; k < libres.length && j === -1; k++) {
						if (libres[k] && !libres[k].aPart && !libres[k].nom && aDuTexte(libres[k])) { j = k; }
					}
					var m = j === -1 ? {} : libres.splice(j, 1)[0];
					m.nom = f.name;
					if (!m.numero) { m.numero = decalage() + i + 1; }
					if (aUneAutorisation(m.auto) && !illusAuto[i]) { illusAuto[i] = m.auto; }
					neuf[i] = m;
				});
				orphelins = libres.filter(function (m) { return m && !m.aPart && (m.nom || aDuTexte(m)); });
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
					? 'Illustrations à redéposer' + NBSP + ': leurs légendes, descriptions et crédits reviendront d’eux-mêmes. Leur numéro, que le texte cite, '
						+ 'suit l’ordre des figures' + NBSP + ': choisies ensemble, elles reprennent celui d’avant.'
					: 'Illustration à redéposer' + NBSP + ': sa légende, sa description et ses crédits reviendront d’eux-mêmes.';
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
				titre: 'Ce qui s’imprimera après «' + NBSP + 'Fig.' + NBSP + '1' + NBSP + ':' + NBSP + '». Par exemple' + NBSP + ': Plan des vestiges de la chapelle et du cimetière. '
					+ 'Ne tapez pas «' + NBSP + 'Fig.' + NBSP + '1' + NBSP + '»' + NBSP + ': la numérotation est automatique.',
				alt: 'Une ou deux phrases qui disent ce qu’on voit, comme si vous décriviez l’image au téléphone' + NBSP + ': '
					+ 'le lecteur d’écran des personnes aveugles ou malvoyantes lit ce texte à la place de l’image. '
					+ 'Ce n’est pas la légende' + NBSP + '; ne commencez pas par «' + NBSP + 'Image de' + NBSP + '». '
					+ 'Pour un plan ou une carte, poursuivez à la ligne' + NBSP + ': le détail est publié sous la légende.',
				legende: 'Seulement s’il faut expliquer ce que porte la figure' + NBSP + ': couleurs, numéros, abréviations, échelle, orientation.',
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
					p.appendChild(document.createTextNode('Description' + NBSP + ': ' + guillemetsA11y(x[1])));
					d.appendChild(p);
				});
				var rappel = document.createElement('p');
				rappel.textContent = 'La description ne répète pas la légende' + NBSP + ': elle dit ce qu’on voit.';
				d.appendChild(rappel);
				return d;
			}
			function guillemetsA11y(t) { return '«' + NBSP + t + NBSP + '»'; }
			// Comme au serveur (Notice_Archeomed_Controles::separer_l_ecoute) :
			// le premier paragraphe est le texte alternatif, la suite la
			// description détaillée.
			function separerEcoute(t) {
				var lignes = String(t || '').split(/\r?\n/).map(net).filter(function (l) { return l !== ''; });
				return { alt: lignes.length ? lignes[0] : '', description: lignes.slice(1).join('\n') };
			}
			// Le texte alternatif ne dépasse pas sa limite : ce qui est tapé ou
			// collé au-delà, dans le premier paragraphe, n'entre pas. La suite,
			// à la ligne, reste libre. Rend vrai si le champ a été retouché.
			function bornerLeTexteAlternatif(champ) {
				var v = champ.value, lignes = v.split('\n'), i, debut = 0;
				for (i = 0; i < lignes.length; i++) {
					if (net(lignes[i]) !== '') { break; }
					debut += lignes[i].length + 1;
				}
				if (i === lignes.length) { return false; }
				var ligne = lignes[i], exces = points(net(ligne)).length - ALT_MAX;
				if (exces <= 0) { return false; }
				var fin = debut + ligne.length, curseur = champ.selectionEnd;
				while (exces > 0) {
					ligne = extrait(ligne, points(ligne).length - exces);
					exces = points(net(ligne)).length - ALT_MAX;
				}
				lignes[i] = ligne;
				champ.value = lignes.join('\n');
				var retire = v.length - champ.value.length;
				curseur = curseur > fin ? curseur - retire : Math.min(curseur, debut + ligne.length);
				try { champ.setSelectionRange(curseur, curseur); } catch (e) { /* champ caché */ }
				return true;
			}
			// Le titre, les crédits et le texte pour l'écoute sont obligatoires,
			// et portent l'astérisque ; la légende est facultative, et le dit.
			// Sans texte pour l'écoute, la figure n'existe pas pour qui ne la
			// voit pas.
			var OBLIGATOIRES_ILLUS = ['titre', 'credits', 'alt'];
			function champIllustration(idx, clef, libelle, lignes) {
				var bloc = document.createElement('div');
				var id = 'na-illus-' + (idx + 1) + '-' + clef;
				var obligatoire = OBLIGATOIRES_ILLUS.indexOf(clef) !== -1;
				var etiquette = document.createElement('label');
				etiquette.htmlFor = id;
				etiquette.innerHTML = libelle + (obligatoire ? ' <span class="na-requis" aria-hidden="true">*</span>'
					: ' <span class="na-facultatif">(facultatif)</span>');
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
				// Une description détaillée d'avant le champ unique rejoint le
				// texte pour l'écoute, à la ligne : c'est ainsi qu'elle s'y écrit.
				if (clef === 'alt' && illusMeta[idx] && !vide(illusMeta[idx].description)) {
					illusMeta[idx].alt = net(illusMeta[idx].alt || '') + '\n' + illusMeta[idx].description;
					illusMeta[idx].description = '';
				}
				champ.value = illusMeta[idx] && illusMeta[idx][clef] ? illusMeta[idx][clef] : '';
				// La mise en forme du titre, de la légende, de la description
				// détaillée : un champ caché à côté, que l'éditeur remplit. Il
				// est posté même vide, pour que chaque figure garde son rang.
				var nomDeForme = { titre: 'titre', legende: 'legende', alt: 'description' }[clef];
				var cache = null;
				if (nomDeForme) {
					cache = document.createElement('input');
					cache.type = 'hidden';
					cache.id = id + '-forme';
					cache.name = 'illus_' + nomDeForme + '_forme[]';
					cache.value = illusMeta[idx] && typeof illusMeta[idx][nomDeForme + 'Forme'] === 'string' ? illusMeta[idx][nomDeForme + 'Forme'] : '';
				}
				if (obligatoire) { champ.required = true; }
				champ.addEventListener('input', function () {
					if (!illusMeta[idx]) { illusMeta[idx] = {}; }
					illusMeta[idx][clef] = champ.value;
				});
				if (clef === 'alt') {
					champ.maxLength = ALT_MAX + DESCRIPTION_MAX + 20;
					champ.setAttribute('autocomplete', 'off');
					// Un compteur sous le champ : le premier paragraphe — le
					// texte alternatif — s'arrête à sa limite, et le compteur le
					// dit avant qu'on y bute.
					compteur = document.createElement('p');
					compteur.className = 'na-help na-compteur';
					compteur.id = id + '-compteur';
					compter = function () {
						var n = points(separerEcoute(champ.value).alt).length;
						compteur.textContent = entier(n) + NBSP + '/' + NBSP + entier(ALT_MAX) + ' caractères'
							+ (n >= ALT_MAX ? ' — c’est la limite' + NBSP + '; le détail se met à la ligne' : '');
					};
					champ.addEventListener('input', function () {
						if (bornerLeTexteAlternatif(champ)) {
							if (!illusMeta[idx]) { illusMeta[idx] = {}; }
							illusMeta[idx][clef] = champ.value;
						}
					});
					champ.addEventListener('input', compter);
					champ.setAttribute('aria-describedby', aide.id + ' ' + compteur.id);
				}
				if (clef !== 'alt') {
					champ.addEventListener('blur', function () {
						avisDeLongueur(champ, { legende: 1500 }[clef] || 400,
							{ titre: 'La légende', legende: 'Le complément de légende', credits: 'Le crédit' }[clef]);
					});
				}
				bloc.appendChild(etiquette);
				bloc.appendChild(aide);
				if (clef === 'alt') { bloc.appendChild(exemplesDuTexteAlternatif()); }
				bloc.appendChild(champ);
				if (cache) { bloc.appendChild(cache); }
				if (compteur) { bloc.appendChild(compteur); compter(); }
				return { bloc: bloc, champ: champ, cache: cache, forme: nomDeForme };
			}
			// La nature de la figure : obligatoire, comme le texte alternatif
			// qu'elle sert — « Autre » est là pour ce qui n'entre nulle part.
			// Elle adapte le conseil donné pour le
			// texte alternatif, et fait demander les données d'un graphique ou
			// d'un tableau en image.
			function champType(idx) {
				var bloc = document.createElement('div');
				bloc.className = 'na-illus-type';
				var id = 'na-illus-' + (idx + 1) + '-type';
				var etiquette = document.createElement('label');
				etiquette.htmlFor = id;
				etiquette.innerHTML = 'Nature de la figure <span class="na-requis" aria-hidden="true">*</span>';
				var champ = document.createElement('select');
				champ.id = id;
				champ.name = 'illus_type[]';
				champ.required = true;
				var vide0 = document.createElement('option');
				vide0.value = '';
				vide0.textContent = 'Choisir…';
				champ.appendChild(vide0);
				Object.keys(A11Y.types).forEach(function (k) {
					var o = document.createElement('option');
					o.value = k;
					o.textContent = A11Y.types[k];
					champ.appendChild(o);
				});
				champ.value = illusMeta[idx] && illusMeta[idx].type && Object.prototype.hasOwnProperty.call(A11Y.types, illusMeta[idx].type) ? illusMeta[idx].type : '';
				var conseil = document.createElement('p');
				conseil.className = 'na-help na-illus-type-conseil';
				conseil.id = id + '-conseil';
				function poser() { conseil.textContent = A11Y.aidesTypes[champ.value] || ''; conseil.hidden = !champ.value; }
				poser();
				champ.setAttribute('aria-describedby', conseil.id);
				champ.addEventListener('change', function () {
					if (!illusMeta[idx]) { illusMeta[idx] = {}; }
					illusMeta[idx].type = champ.value;
					poser();
					marquerModifie();
				});
				bloc.appendChild(etiquette);
				bloc.appendChild(champ);
				bloc.appendChild(conseil);
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
					if (f.aPart) { illusMeta[idx] = f.meta; }
					if (!illusMeta[idx]) { illusMeta[idx] = {}; }
					illusMeta[idx].nom = f.name;
					if (!illusMeta[idx].numero) { illusMeta[idx].numero = decalage() + idx + 1; }
					var etat = etatDe(f), num = decalage() + idx + 1;
					var li = document.createElement('li');
					li.className = 'na-illus-carte';
					var groupe = document.createElement('fieldset');
					groupe.className = 'na-illus';
					var leg = document.createElement('legend');
					leg.textContent = 'Figure ' + num + ' ';
					var nom = document.createElement('span');
					nom.className = 'na-illus-fichier';
					nom.textContent = f.aPart ? '— envoyée à part' : '— ' + f.name + ' (' + mo(f.size) + ')';
					leg.appendChild(nom);
					function boutonDeCarte(classe, mot, action) {
						var b = document.createElement('button');
						b.type = 'button';
						b.className = classe;
						b.textContent = mot;
						var sr = document.createElement('span');
						sr.className = 'na-sr';
						sr.textContent = ' la figure ' + num + ' (' + nomDeCarte(f) + ')';
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
						// revenait vide. Une figure envoyée à part n'a pas de
						// fichier à attendre : elle part avec ses textes.
						if (!f.aPart && parti && (aDuTexte(parti) || aUneAutorisation(auto))) {
							if (aUneAutorisation(auto)) { attacherLAutorisation(parti, auto); }
							orphelins.push(parti);
							dessinerLAttente();
						}
						refreshFileInput();
						annoncer('Figure ' + num + ' retirée.' + (idx < selected.length
							? ' Les suivantes changent de numéro' + NBSP + ': vérifiez les appels du texte.' : ''));
						// Le focus va à la figure qui prend la place, non au premier
						// « Retirer » de la liste : un second Entrée supprimerait la
						// figure 1.
						var voisine = el('na-illus-' + (Math.min(idx, selected.length - 1) + 1) + '-titre');
						(voisine || fileInput).focus();
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
								e.dataTransfer.setData('text/plain', nomDeCarte(f));
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
					// légende : « Figure 1 », c'est ce que le texte cite. Un TIFF ou un
					// PDF ne s'affiche pas tel quel : sa vignette se dessine à part.
					if (f.aPart) {
						groupe.appendChild(champsDeLEnvoi(f, idx));
					}
					else if (/^image\/(jpeg|png)$/.test(f.type) && window.URL && URL.createObjectURL) {
						if (!etat.vignette) { etat.vignette = URL.createObjectURL(f); }
						groupe.appendChild(vignetteDe(etat.vignette, f.name));
					}
					else if (etat.vignette && !(etat.conv && etat.conv.etat === 'cours')) {
						// Un TIFF converti : la vignette est tirée de sa basse définition ;
						// lu par le serveur, d'une image faite pour l'écran.
						groupe.appendChild(vignetteDe(etat.vignette, f.name));
					}
					else {
						// Un TIFF en cours de conversion, ou un format qui ne s'affiche pas
						// dans un navigateur : on le dit, pour que l'absence d'image ne passe
						// pas pour un fichier perdu.
						var sans = document.createElement('p');
						sans.className = 'na-illus-sans-apercu';
						sans.setAttribute('role', 'status');
						sans.textContent = (etat.conv && etat.conv.etat === 'cours')
							? 'Conversion du ' + extOf(f.name).toUpperCase() + ' en cours…'
							: etat.vignetteEnCours ? 'Aperçu du ' + extOf(f.name).toUpperCase() + ' en préparation…'
							: 'Pas d’aperçu pour ce format (' + extOf(f.name).toUpperCase() + ')' + NBSP + ': le fichier est bien reçu.';
						etat.statutEl = sans;
						groupe.appendChild(sans);
					}
					var impression = null;
					if (!f.aPart) {
						impression = document.createElement('p');
						impression.className = 'na-illus-impression';
						impression.hidden = true;
						groupe.appendChild(impression);
					}
					var champs = document.createElement('div');
					champs.className = 'na-illus-champs';
					// Deux parties : ce qui s'imprime avec la figure, puis, encadré,
					// le texte pour qui ne la voit pas — un autre travail, qui
					// demande d'expliquer l'image et non de la légender.
					var publication = document.createElement('p');
					publication.className = 'na-illus-partie';
					publication.textContent = 'Pour la publication';
					// Les noms de ce que l'auteur possède : une légende, parfois un
					// complément ; « Titre » faisait écrire « Fig. 1 » ou « Plan »,
					// et la vraie légende allait dans la case facultative.
					var titre = champIllustration(idx, 'titre', 'Légende de la figure', 0);
					var legende = champIllustration(idx, 'legende', 'Complément de légende', 2);
					var credits = champIllustration(idx, 'credits', 'Crédits', 0);
					var alt = champIllustration(idx, 'alt', 'Description de l’image', 4);
					alt.bloc.className = 'na-illus-ecoute';
					// La nature d'abord : elle se choisit en une seconde, et règle le
					// conseil donné plus bas.
					var nature = champType(idx);
					var titreEcoute = document.createElement('p');
					titreEcoute.className = 'na-illus-partie';
					titreEcoute.id = 'na-illus-' + (idx + 1) + '-ecoute-titre';
					titreEcoute.textContent = 'Description pour les lecteurs qui ne voient pas l’image';
					alt.bloc.setAttribute('role', 'group');
					alt.bloc.setAttribute('aria-labelledby', titreEcoute.id);
					alt.bloc.insertBefore(titreEcoute, alt.bloc.firstChild);
					champs.appendChild(nature.bloc);
					champs.appendChild(publication);
					champs.appendChild(titre.bloc);
					champs.appendChild(legende.bloc);
					champs.appendChild(credits.bloc);
					champs.appendChild(champAutorisation(idx));
					champs.appendChild(alt.bloc);
					var conseils = document.createElement('div');
					conseils.className = 'na-conseils';
					conseils.id = 'na-illus-' + (idx + 1) + '-conseils';
					conseils.hidden = true;
					groupe.appendChild(champs);
					groupe.appendChild(conseils);
					// Chaque carte dit d'où vient sa figure ; les listes du lien,
					// de la date et du nom restent ainsi alignées sur les cartes,
					// vides pour un fichier joint.
					groupe.appendChild(champCache('illus_source[]', f.aPart ? 'a_part' : 'fichier'));
					if (!f.aPart) {
						['illus_lien[]', 'illus_jusqua[]', 'illus_nom_envoi[]'].forEach(function (n) { groupe.appendChild(champCache(n, '')); });
					}
					// L'avis « rien n'est dit » paraissait dès le choix du fichier,
					// avant qu'on ait pu écrire un mot : il attend qu'on ait quitté
					// la carte.
					groupe.addEventListener('focusout', function (e) {
						// Un éditeur de mise en forme porte le nom de son champ.
						var cle = /^illus_(titre|legende|credits|alt|description|type)/.exec(e.target.name
							|| (e.target.getAttribute && e.target.getAttribute('data-na-nom')) || '');
						if (cle) { etat.vus[cle[1]] = true; }
						if (!(e.relatedTarget && groupe.contains(e.relatedTarget))) { etat.quittee = true; }
						majFigure(idx);
					});
					li.appendChild(groupe);
					fileList.appendChild(li);
					cartes[idx] = { zone: conseils, titre: titre.champ, alt: alt.champ, description: null, legende: legende.champ, credits: credits.champ, type: nature.champ, impression: impression };
					// Petites capitales, italique, exposant : la légende, son
					// complément et la description détaillée s'écrivent dans un
					// éditeur, posé une fois la carte dans la page. Le texte
					// alternatif, premier paragraphe du champ pour l'écoute, reste
					// sans marque. Chaque forme suit la figure dans illusMeta.
					if (quill) {
						[[titre, 'titreRiche', false, false, 'Mise en forme de la légende'],
							[legende, 'legendeRiche', true, false, 'Mise en forme du complément de légende'],
							[alt, 'altRiche', true, true, 'Mise en forme de la description détaillée']].forEach(function (d) {
							var champ = d[0];
							if (!champ.cache) { return; }
							cartes[idx][d[1]] = naFigureRiche(champ.champ, champ.cache, {
								lignes: d[2], premierBrut: d[3], barre: d[4],
								garder: function (forme) {
									if (!illusMeta[idx]) { illusMeta[idx] = {}; }
									illusMeta[idx][champ.forme + 'Forme'] = forme;
								}
							});
						});
					}
					// La nature change la densité qui compte : la taille se refait.
					nature.champ.addEventListener('change', function () { majFigure(idx); });
					majFigure(idx);
				});
				// Les cartes viennent d'être refaites : une erreur de texte
				// alternatif se repose sur le champ neuf, ou s'efface si la
				// figure qui la portait est partie ou décrite.
				revoirLesTextesAlternatifs();
				if (selected.length > 0 || poidsDesGardees() > 0) {
					var total = document.createElement('li');
					total.className = 'na-illus-total';
					total.textContent = 'Total' + NBSP + ': ' + mo(totalSelectedSize()) + ' sur ' + moPlafond(MAX_TOTAL_SIZE);
					fileList.appendChild(total);
				}
				// Pour la figure suivante, on n'a plus à remonter jusqu'à la zone
				// de dépôt : le même sélecteur, qui ajoute sans rien remplacer.
				ajoutFigure.hidden = !(selected.some(function (f) { return !f.aPart; }) && decalage() + selected.length < MAX_FILES);
				if (ajoutAPart) { ajoutAPart.hidden = decalage() + selected.length >= MAX_FILES; }
				majNumeros();
			}
			function champCache(nom, valeur) {
				var c = document.createElement('input');
				c.type = 'hidden';
				c.name = nom;
				c.value = valeur;
				return c;
			}
			function aujourdhuiISO() {
				var j = new Date();
				return j.getFullYear() + '-' + ('0' + (j.getMonth() + 1)).slice(-2) + '-' + ('0' + j.getDate()).slice(-2);
			}
			// Le lien de l'envoi, la date où il s'éteint, le nom du fichier
			// dedans : de quoi télécharger l'original, à la place de l'aperçu.
			function champsDeLEnvoi(f, idx) {
				var m = f.meta, bloc = document.createElement('div');
				bloc.className = 'na-illus-champs na-illus-a-part';
				var partie = document.createElement('p');
				partie.className = 'na-illus-partie';
				partie.textContent = 'Le fichier envoyé à part';
				bloc.appendChild(partie);
				var dit = document.createElement('p');
				dit.className = 'na-help';
				dit.textContent = 'Pas de fichier ici' + NBSP + ': la rédaction téléchargera l’original par ce lien, avant la date indiquée. '
					+ 'Il n’y a pas d’aperçu' + NBSP + ': la figure sera placée d’après l’original.';
				bloc.appendChild(dit);
				[
					['lien', 'lien', 'Lien de téléchargement', true, 'Le lien que le service de transfert vous a donné, par exemple https://filesender.renater.fr/…'],
					['jusqua', 'jusqua', 'Lien valable jusqu’au', true, 'La date à laquelle le service effacera le fichier' + NBSP + ': la rédaction le télécharge avant.'],
					['nom-envoi', 'nomEnvoi', 'Nom du fichier dans l’envoi', false, 'Si l’envoi contient plusieurs fichiers' + NBSP + ': celui de cette figure.']
				].forEach(function (d) {
					var id = 'na-illus-' + (idx + 1) + '-' + d[0];
					var div = document.createElement('div');
					var etiquette = document.createElement('label');
					etiquette.htmlFor = id;
					etiquette.innerHTML = d[2] + (d[3] ? ' <span class="na-requis" aria-hidden="true">*</span>' : ' <span class="na-facultatif">(facultatif)</span>');
					var aide = document.createElement('p');
					aide.className = 'na-help';
					aide.id = id + '-aide';
					aide.textContent = d[4];
					var champ = document.createElement('input');
					champ.id = id;
					champ.name = { lien: 'illus_lien[]', jusqua: 'illus_jusqua[]', nomEnvoi: 'illus_nom_envoi[]' }[d[1]];
					if (d[1] === 'jusqua') {
						champ.type = 'date';
						champ.min = aujourdhuiISO();
					} else {
						champ.type = 'text';
						champ.setAttribute('autocomplete', 'off');
						champ.setAttribute('spellcheck', 'false');
						champ.setAttribute('autocapitalize', 'off');
						if (d[1] === 'lien') { champ.setAttribute('inputmode', 'url'); }
					}
					if (d[3]) { champ.required = true; }
					champ.value = typeof m[d[1]] === 'string' ? m[d[1]] : '';
					champ.setAttribute('aria-describedby', aide.id);
					champ.addEventListener(d[1] === 'jusqua' ? 'change' : 'input', function () {
						m[d[1]] = champ.value;
						marquerModifie();
						if (typeof reverifier === 'function' && d[1] !== 'nomEnvoi') { reverifier('illus_' + d[1] + '_' + (idx + 1)); }
					});
					// Un lien copié sans « https:// » : on l'ajoute, comme pour
					// le lien du rapport.
					if (d[1] === 'lien') {
						champ.addEventListener('blur', function () {
							normaliserUneAdresse(champ);
							if (m.lien !== champ.value) {
								m.lien = champ.value;
								if (typeof reverifier === 'function') { reverifier('illus_lien_' + (idx + 1)); }
							}
						});
					}
					div.appendChild(etiquette);
					div.appendChild(aide);
					div.appendChild(champ);
					bloc.appendChild(div);
				});
				return bloc;
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
			// Un fichier trop lourd : la figure est envoyée à part, et sa carte
			// prend place dans la liste. La suivante reprend le lien et la date
			// de la précédente — d'ordinaire, un seul envoi les porte toutes.
			var ajoutAPart = document.getElementById('na-ajout-a-part');
			if (ajoutAPart) {
				ajoutAPart.addEventListener('click', function () {
					if (decalage() + selected.length >= MAX_FILES) {
						refuses.textContent = 'Trois figures au plus en tout' + NBSP + ': retirez une figure avant d’en ajouter une.';
						return;
					}
					var avant = null;
					// Le dernier lien donné, non la dernière carte : une carte
					// encore vide ne doit pas effacer ce qu'on reprendrait.
					selected.forEach(function (f) { if (f.aPart && f.meta.lien) { avant = f.meta; } });
					selected.push(figureAPart({ lien: avant && avant.lien ? avant.lien : '', jusqua: avant && avant.jusqua ? avant.jusqua : '' }));
					rattacher();
					refreshFileInput();
					refuses.textContent = '';
					marquerModifie();
					majConseilsDuTexte();
					var rang = selected.length;
					annoncer('Figure ' + (decalage() + rang) + ' ajoutée, envoyée à part' + NBSP + ': donnez son lien et ses textes.');
					var cible = el('na-illus-' + rang + (avant && avant.lien ? '-nom-envoi' : '-lien'));
					if (cible) { cible.focus(); }
				});
			}
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
				annoncer('La figure ' + (decalage() + de + 1) + ' est maintenant la figure ' + (decalage() + vers + 1) + '.');
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
					if (m && m.numero && m.numero !== decalage() + i + 1) {
						changes.push((f.aPart ? 'La figure envoyée à part' : f.name) + ', qui était la fig.' + NBSP + m.numero + ', est maintenant la fig.' + NBSP + (decalage() + i + 1));
					}
				});
				montrerConseils(zoneNumeros, changes.length ? [{
					texte: (changes.length > 1 ? 'Des figures ont changé de numéro' : 'Une figure a changé de numéro') + NBSP + ': '
						+ changes.join(NBSP + '; ') + '. Le texte les appelle par leur numéro' + NBSP + ': vérifiez ses appels.',
					bouton: 'Les appels du texte sont à jour',
					action: function () {
						illusMeta.forEach(function (m, i) { if (m) { m.numero = decalage() + i + 1; } });
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
			function avisDAccessibilite(m, rang, vus, fichier, cles) {
				if (!A11Y.actif) { return []; }
				var alt = net(m.alt), titre = net(m.titre), legende = net(m.legende), phrases = [], trouve;
				// « cles » : ne rendre que ces avis-là (ceux qui bloquent l'envoi).
				function ajouter(cle, valeurs) {
					if (!cles || cles.indexOf(cle) !== -1) { phrases.push(phraseA11y(cle, valeurs)); }
				}
				var longueur = points(alt).length;
				if (vus.alt && longueur > A11Y.conseille) {
					ajouter('longueur', { n: longueur, max: A11Y.conseille });
				}
				if (alt) {
					if (nomDeFichierA11y(alt, fichier)) {
						if (vus.alt) { ajouter('fichier', { texte: alt }); }
					} else if (MOTIFS_A11Y.sansContenu.test(plierA11y(alt))) {
						if (vus.alt) { ajouter('sans_contenu', { texte: alt }); }
					} else {
						if ((vus.alt || vus.titre) && titre && reprendA11y(alt, titre)) { ajouter('titre'); }
						if ((vus.alt || vus.legende) && legende && reprendA11y(alt, legende)) { ajouter('legende'); }
						if (vus.alt && /^(image|photo|photographie|illustration)\s+((de|du|des)(?=\s|$)|d’|d')/i.test(alt)) {
							ajouter('debut', { debut: alt.split(/\s+/).slice(0, 2).join(' ') });
						}
					}
				}
				// Une information portée par la seule couleur ne passe ni à qui
				// ne voit pas l'image, ni à qui ne distingue pas les couleurs.
				if ((vus.alt || vus.legende) && (trouve = COULEUR_SEULE.exec(alt + ' | ' + legende))) {
					ajouter('couleur', { couleur: trouve[0] });
				}
				// Comme au serveur : seules les figures qu'on ne comprend que par
				// leurs données — un graphique, un tableau en image — demandent ces
				// données en texte, à la suite du texte alternatif. Pour un plan,
				// une carte ou une photographie, le texte alternatif suffit : on ne
				// réclame pas une description de plus à qui a déjà rempli le champ.
				var type = Object.prototype.hasOwnProperty.call(A11Y.types, m.type) ? m.type : '';
				if ((vus.alt || vus.type) && vide(m.description) && A11Y.avecDonnees.indexOf(type) !== -1) {
					ajouter('donnees', { mot: A11Y.typesMots[type] });
				}
				return phrases.map(function (p) { return NORMES.figure + ' ' + rang + NBSP + ': ' + p; });
			}
			// Les avis d'une figure : sa définition, le numéro retapé, le crédit
			// resté dans la légende, les crédits qui manquent.
			function majFigure(idx) {
				var c = cartes[idx], f = selected[idx];
				if (!c || !f) { return; }
				var e = etatDe(f), m = illusMeta[idx] || {}, rang = decalage() + idx + 1, liste = [];
				if (c.impression) {
					var taille = e.px ? tailleDImpression(e.px, m.type, f.name) : '';
					c.impression.textContent = taille;
					c.impression.hidden = !taille;
				}
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
						texte: (clef === 'titre' ? 'La légende' : 'Le complément de légende') + ' semble finir par un crédit ('
							+ guillemets(coupe.credit) + ')' + NBSP + ': sa place est dans Crédits.',
						bouton: 'Déplacer dans Crédits',
						action: function () {
							var source = clef === 'titre' ? c.titre : c.legende;
							source.value = coupe.reste;
							c.credits.value = coupe.credit;
							m[clef] = coupe.reste;
							m.credits = coupe.credit;
							illusMeta[idx] = m;
							// L'éditeur reprend le texte raccourci, ses marques
							// gardées sur ce qui reste.
							var riche = c[clef + 'Riche'];
							if (riche) { riche.charger(); }
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
				// Le champ pour l'écoute porte, dans son premier paragraphe, le
				// texte alternatif et, à la suite, la description détaillée : les
				// avis les lisent séparés, comme le serveur. Titre et crédits
				// manquants ne sont plus des avis : ce sont des champs
				// obligatoires, que la vérification de l'envoi signale.
				var ecoute = separerEcoute(m.alt);
				var lu = { titre: m.titre, legende: m.legende, credits: m.credits, alt: ecoute.alt, description: ecoute.description, type: m.type };
				avisDAccessibilite(lu, rang, e.vus, f.aPart ? (m.nomEnvoi || '') : f.name).forEach(function (phrase) { liste.push(phrase); });
				montrerConseils(c.zone, liste, [c.titre, c.alt, c.description, c.legende, c.credits, c.type]);
			}
			fileInput.addEventListener('change', function () {
				var incoming = Array.prototype.slice.call(fileInput.files);
				var refused = [];
				var ajoutes = [];
				incoming.forEach(function (f) {
					var dup = selected.some(function (s) { return s.name === f.name && s.size === f.size; });
					if (dup) { return; }
					if (decalage() + selected.length >= MAX_FILES) {
						refused.push(guillemets(f.name) + ' n’a pas été ajouté' + NBSP + ': ' + (decalage() ? 'trois figures au plus en tout, figures déjà déposées comprises.' : 'trois fichiers au plus.'));
						return;
					}
					if (ALLOWED.indexOf(extOf(f.name)) === -1) { refused.push(guillemets(f.name) + ' n’est pas en JPEG, TIFF ou PDF.'); return; }
					// Sans Imagick, le serveur ne lit ni le PDF ni le TIFF : ils se
					// convertissent ici, dans le navigateur.
					if (aConvertir(f) && !(extOf(f.name) === 'pdf' ? PDF_PRET : CONV_PRET)) {
						refused.push(guillemets(f.name) + ' : ce navigateur ne sait pas convertir un ' + extOf(f.name).toUpperCase() + '. Enregistrez la figure en JPEG, puis redéposez-la.');
						return;
					}
					var trop = '';
					if (f.size > MAX_FILE_SIZE) {
						trop = guillemets(f.name) + ' dépasse ' + moPlafond(MAX_FILE_SIZE) + ', le plus que le serveur accepte par fichier.';
					} else if ((totalSelectedSize() + f.size) > MAX_TOTAL_SIZE) {
						trop = guillemets(f.name) + ' ferait dépasser ' + moPlafond(MAX_TOTAL_SIZE) + ' en tout.';
					}
					if (trop) {
						if (peutSAlleger(f)) { trop += ALLEGER_FIN; proposerDAlleger(f, trop); }
						refused.push(trop);
						return;
					}
					selected.push(f);
					ajoutes.push(f);
					if (aConvertir(f)) { etatDe(f).conv = { etat: 'cours' }; }
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
				// Une figure envoyée à part a repris sa carte dès le retour ; le
				// fichier redéposé qui la précédait reprend sa place devant elle,
				// et les numéros que le texte cite tiennent.
				if (selected.some(function (f) { return f.aPart; })) {
					ajoutes.forEach(function (f) {
						var n = 0, k, de = selected.indexOf(f), vers = -1;
						for (k = 0; k < orphelins.length && !n; k++) {
							if (orphelins[k] && orphelins[k].nom === f.name) { n = orphelins[k].numero || 0; }
						}
						for (k = 0; n && k < de && vers === -1; k++) {
							if (selected[k].aPart && selected[k].meta.numero > n) { vers = k; }
						}
						if (vers === -1) { return; }
						while (illusAuto.length < selected.length) { illusAuto.push(undefined); }
						selected.splice(vers, 0, selected.splice(de, 1)[0]);
						illusAuto.splice(vers, 0, illusAuto.splice(de, 1)[0]);
					});
				}
				rattacher();
				refreshFileInput();
				refuses.textContent = [refusGardes].concat(refused).filter(Boolean).join(' ');
				refusGardes = '';
				majConseilsDuTexte();
				ajoutes.forEach(function (f) { if (etatDe(f).conv && etatDe(f).conv.etat === 'cours') { lancerLaConversion(f); } });
				ajoutes.forEach(function (f) { if (!aConvertir(f) && /^(tiff?|pdf)$/.test(extOf(f.name))) { tirerLaVignette(f); } });
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
			orphelins = reprendreLesFiguresAPart(orphelins);
			if (selected.length) { rattacher(); refreshFileInput(); }
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
					+ 'cm. Vérifier que la taille d’impression convient.';
			}

			// La taille à laquelle la figure peut s'imprimer, dite sur chaque
			// carte et non seulement quand elle manque : l'auteur juge mieux
			// d'un « 25 × 17 cm » que d'un nombre de pixels. Un plan, une carte,
			// un graphique ou un tableau se comptent à la densité du trait.
			var TYPES_AU_TRAIT = ['plan', 'carte', 'graphique', 'tableau'];
			// Un dessin en pixels a sa meilleure issue dans le PDF vectoriel que
			// le logiciel de dessin exporte : il s'imprime net à toute taille.
			function tailleDImpression(px, type, nom) {
				var auTrait = TYPES_AU_TRAIT.indexOf(type) !== -1;
				var ppp = auTrait && NORME.traitPpp ? NORME.traitPpp : NORME.ppp;
				if (!px || !ppp) { return ''; }
				function cm(p) {
					var v = p / ppp * 2.54;
					return v < 1 ? decimal1(v) : entier(Math.round(v));
				}
				return 'Imprimable jusqu’à ' + cm(px.largeur) + NBSP + '×' + NBSP + cm(px.hauteur) + NBSP + 'cm environ, à '
					+ entier(ppp) + NBSP + 'ppp (' + entier(px.largeur) + NBSP + '×' + NBSP + entier(px.hauteur) + NBSP + 'pixels).'
					+ (auTrait && extOf(nom || '') !== 'pdf'
						? ' Si vous avez ce document en PDF vectoriel, joignez plutôt celui-ci' + NBSP + ': il s’imprime net à toute taille.' : '');
			}

			// ── Les champs des figures ─────────────────────────────────────
			// « Fig. 1 : Plan » donnait « Fig. 1 Fig. 1 : Plan » : le numéro
			// s'ajoute de lui-même.
			var DEJA_NUMEROTE = motif('^\\s*((?:fig(?:ure)?s?|ill(?:ustration)?s?|pl(?:anche)?s?|tabl(?:eau)?x?)\\.?\\s*(\\d+))', 'iu');
			function numeroRetape(titre, rang) {
				var m = DEJA_NUMEROTE ? DEJA_NUMEROTE.exec(titre || '') : null;
				if (!m) { return ''; }
				return parseInt(m[2], 10) === rang
					? guillemets(net(m[1])) + ' s’ajoute de lui-même devant la légende' + NBSP + ': inutile de le taper.'
					: 'La légende commence par ' + guillemets(net(m[1])) + ', mais c’est la figure ' + rang
						+ NBSP + ': le numéro s’ajoute de lui-même, d’après l’ordre des fichiers. Retirez-le de la légende.';
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
			// Le paragraphe en cours de frappe (« sauf ») attend sa fin.
			var PARAGRAPHE_COURT = <?php echo (int) Notice_Archeomed_Controles::PARAGRAPHE_COURT; ?>;
			function citeCourt(t) { return guillemets(points(t).length > 40 ? extrait(t, 40) + '…' : t); }
			// Moins de huit mots : un intertitre posé sans le bouton T1, une
			// liste tapée sans ses puces, une cellule de tableau, non une
			// phrase tronquée.
			// Les lignes courtes qui se suivent ne font qu'un avis (comme au
			// serveur, Notice_Archeomed_Controles::lignes_courtes).
			function lignesCourtes(paras, suite) {
				var premier = suite[0], dernier = suite[suite.length - 1];
				if (suite.length === 1) {
					return 'Le paragraphe ' + (premier + 1) + ' (' + citeCourt(paras[premier]) + ') ne se termine par aucune ponctuation'
						+ NBSP + ': si c’est un intertitre, posez-le avec le bouton T1' + NBSP + '; sinon, terminez la phrase.';
				}
				var deux = suite.length === 2;
				return 'Les paragraphes ' + (premier + 1) + (deux ? ' et ' : ' à ') + (dernier + 1)
					+ ' (' + citeCourt(paras[premier]) + (deux ? ' et ' : ' … ') + citeCourt(paras[dernier]) + ') ne se terminent par aucune ponctuation'
					+ NBSP + ': des intertitres se posent avec le bouton T1, une liste avec le bouton Liste à puces' + NBSP + '; sinon, terminez les phrases.';
			}
			function ponctuationFinale(paras, sauf) {
				var avis = [], suite = [];
				paras.forEach(function (p, i) {
					// Le paragraphe en cours de frappe coupe la suite des lignes
					// courtes : il n'en est pas, tant qu'on y écrit.
					if (i !== sauf && !finitBien(p) && p.trim().split(/\s+/).length < PARAGRAPHE_COURT) { suite.push(i); return; }
					if (suite.length) { avis.push(lignesCourtes(paras, suite)); suite = []; }
					if (i === sauf || finitBien(p)) { return; }
					if (i + 1 < paras.length && reprendEnBasDeCasse(paras[i + 1])) {
						avis.push('Le paragraphe ' + (i + 1) + ' s’arrête au milieu d’une phrase que le suivant reprend ('
							+ guillemets('…' + extrait(p, -30)) + ' puis ' + guillemets(extrait(paras[i + 1], 30) + '…')
							+ ')' + NBSP + ': un retour à la ligne de trop, sans doute.');
					} else {
						avis.push('Le paragraphe ' + (i + 1) + ' ne se termine par aucune ponctuation ('
							+ guillemets('…' + extrait(p, -40)) + ')' + NBSP + ': texte tronqué' + NBSP + '?');
					}
				});
				if (suite.length) { avis.push(lignesCourtes(paras, suite)); }
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
				var appeles = {}, ordre = [], m;
				APPEL.lastIndex = 0;
				while ((m = APPEL.exec(texte)) !== null) {
					developper(m[2]).forEach(function (n) {
						if (!appeles[n]) { ordre.push(n); }
						appeles[n] = true;
					});
				}
				var numeros = Object.keys(appeles).map(Number).sort(function (a, b) { return a - b; });
				var avis = [];
				// Comme au serveur : les figures s'appellent dans l'ordre du texte.
				var plusGrand = 0;
				ordre.forEach(function (n) {
					if (n < plusGrand) {
						avis.push('La fig.' + NBSP + plusGrand + ' est appelée avant la fig.' + NBSP + n + NBSP + ': le texte appelle les figures dans l’ordre.');
					}
					plusGrand = Math.max(plusGrand, n);
				});
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
				} else if (combien > 0) {
					// Comme au serveur : trois figures jointes et pas un appel, c'est
					// le cas le plus courant, et il passait sans rien dire.
					avis.push(1 === combien
						? 'Le texte n’appelle pas la figure jointe' + NBSP + ': appelez-la, par exemple «' + NBSP + '(fig.' + NBSP + '1)' + NBSP + '».'
						: 'Le texte n’appelle aucune des ' + enLettres(combien) + ' jointes' + NBSP + ': appelez chacune, par exemple «' + NBSP + '(fig.' + NBSP + '1)' + NBSP + '».');
				}
				return avis;
			}
			// « (figure 3) », « (voir Fig. 2) », « fig 3 » : la revue écrit
			// « (fig. 3) ». Le mot entier ne compte qu'entre parenthèses, « la
			// figure 3 montre » étant une phrase. Comme au serveur
			// (Notice_Archeomed_Controles::appels_mal_ecrits).
			var CORPS_D_APPEL = '(\\d+[a-z]?(?:\\s*(?:[-–—]|à|et|,|;|\\/)\\s*\\d+[a-z]?){0,12})';
			var APPELS_MAL_ECRITS = [
				motif('\\((?:[^()]*?[^\\p{L}\\d()])?()([Ff]igures?|FIGURES?|Fig\\.?|FIG\\.?)\\s*' + CORPS_D_APPEL, 'gu'),
				motif('(^|[^\\p{L}\\d])([Ff]igs\\.?|[Ff]ig)\\s*' + CORPS_D_APPEL, 'gu')
			];
			function appelsMalEcrits(paras) {
				var avis = [];
				APPELS_MAL_ECRITS.forEach(function (re) {
					if (!re) { return; }
					paras.forEach(function (p) {
						var m;
						re.lastIndex = 0;
						while ((m = re.exec(p)) !== null) {
							var corps = m[3].replace(/\s+/g, ' ');
							var phrase = guillemets(m[2] + ' ' + corps) + NBSP + ': un appel de figure s’écrit ' + guillemets('fig.' + NBSP + corps) + '.';
							if (avis.indexOf(phrase) === -1) { avis.push(phrase); }
						}
					});
				});
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
			var DATE_ESPACEE = motif('(?:(^|[^\\p{L}\\d])(?:en|vers|an|années?|dès|depuis|jusqu’en|jusqu\'en|avant|après)\\s+([12] \\d{3})(?![\\p{L}\\d])(?!\\s*(?:' + <?php echo wp_json_encode( Notice_Archeomed_Controles::UNITES_APRES_UN_NOMBRE ); ?> + ')(?![\\p{L}\\d]))|([12] \\d{3})\\s*(?:av\\.|apr?\\.|ap\\.)\\s*J\\.-C\\.)', 'giu');
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
			function estIntertitre(l) { return !!(l.formats && l.formats().header); }
			function estElementDeListe(l) { return !!(l.formats && l.formats().list); }
			function texteDeLigne(l) { return quill.getText(quill.getIndex(l), l.length()).trim(); }
			// Les paragraphes du texte sans ses intertitres ni les éléments de
			// ses listes, qui ne prennent pas de point final : comme au
			// serveur. « courant » est le rang de celui où l'on écrit, quand on
			// y écrit.
			function parasSansIntertitres(enCours) {
				var sel = enCours && quill.hasFocus() ? quill.getSelection() : null;
				var ligne = sel ? quill.getLine(sel.index)[0] : null;
				var textes = [], courant = -1;
				quill.getLines().forEach(function (l) {
					var t = estIntertitre(l) || estElementDeListe(l) ? '' : texteDeLigne(l);
					if (!t) { return; }
					if (l === ligne) { courant = textes.length; }
					textes.push(t);
				});
				return { textes: textes, courant: courant };
			}
			// Un intertitre qui finit par « . », « , », « ; » ou « : », comme
			// au serveur (Notice_Archeomed_Controles::intertitres_ponctues).
			// Le point d'une abréviation n'est pas une ponctuation finale :
			// « (xiie–xiiie s.) », « av. J.-C. » (ABREVIATION_FINALE au serveur).
			var ABREVIATION_FINALE = /(?:^|[\s(\[«’'\-–])(?:s|ss|av|apr|ap|env|ca|cf|etc|éd|dir|coll|vol|fig|pl|J\.-C)\.$/i;
			function intertitresPonctues() {
				var avis = [];
				quill.getLines().filter(estIntertitre).forEach(function (l) {
					var t = texteDeLigne(l), nu = t;
					while (nu && FERMANTS.indexOf(nu.charAt(nu.length - 1)) !== -1) { nu = nu.slice(0, -1); }
					var fin = nu.charAt(nu.length - 1);
					if (!fin || '.,;:'.indexOf(fin) === -1 || nu.slice(-2) === '..'
						|| (fin === '.' && ABREVIATION_FINALE.test(nu))) { return; }
					avis.push('L’intertitre ' + guillemets(points(t).length > 40 ? '…' + extrait(t, -40) : t)
						+ ' finit par ' + guillemets(fin) + NBSP + ': un intertitre ne prend pas de ponctuation finale.');
				});
				return avis;
			}
			// Les avis suivent la frappe : une seconde après la dernière touche,
			// sans attendre qu'on quitte le texte.
			function majConseilsDuTexte(enCours) {
				if (!texteVu || !quill) { return; }
				var paras = quill.getText().split('\n').map(function (p) { return p.trim(); }).filter(Boolean);
				var sansTitres = parasSansIntertitres(enCours);
				var longueur = quill.root.innerHTML.length;
				montrerConseils(zoneTexte, [].concat(
					longueur > 30000 ? ['Le texte dépasse 30' + NBSP + '000 caractères, mise en forme comprise (' + entier(longueur) + ')'
						+ NBSP + ': le serveur en couperait la fin.'] : [],
					avisDuCollage(paras),
					avisActif('ponctuation') ? ponctuationFinale(sansTitres.textes, sansTitres.courant).concat(intertitresPonctues()) : [],
					avisActif('figures_appelees') ? appelsDeFigure(paras.join('\n'), decalage() + selected.length).concat(appelsMalEcrits(paras)) : [],
					avisActif('ordinaux') ? ordinauxFautifs(paras) : [],
					avisActif('annees_espacees') ? datesEspacees(paras) : [],
					avisActif('sigles') ? siglesNonDeveloppes(paras).concat(siglesMalEcrits(paras)) : []
				), [quill.root]);
			}
			// Les sigles de la liste des normes employés sans être développés à
			// leur première mention, comme au serveur
			// (Notice_Archeomed_Controles::sigles_non_developpes) : même liste,
			// même phrase, mêmes règles.
			function siglesNonDeveloppes(paras) {
				var texte = paras.join('\n').replace(/(?:https?:\/\/|www\.)\S+/gi, function (u) { return new Array(u.length + 1).join(' '); }),
					avis = [], L = A11Y.lettres;
				var sansArticles = function (t) {
					return plierA11y(t).split(' ').filter(function (w) { return w && A11Y.motsVides.indexOf(w) === -1; }).join(' ');
				};
				A11Y.sigles.forEach(function (s) {
					var m = new RegExp('(^|[^' + L + '])(' + formesDuSigle(s.sigle) + ')(?=$|[^' + L + '])').exec(texte);
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
			// Comme au serveur (formes_du_sigle) : la forme de la liste, et ses
			// capitales si le sigle se prononce.
			function echappeSigle(t) { return t.replace(/[.*+?^${}()|[\]\\\/-]/g, '\\$&'); }
			function formesDuSigle(sigle) {
				var capitales = sigle.toUpperCase();
				return capitales === sigle ? echappeSigle(sigle) : echappeSigle(sigle) + '|' + echappeSigle(capitales);
			}
			// Les sigles écrits dans une autre casse que celle de la liste,
			// comme au serveur (Notice_Archeomed_Controles::sigles_mal_ecrits).
			function siglesMalEcrits(paras) {
				var texte = paras.join('\n').replace(/(?:https?:\/\/|www\.)\S+/gi, ' '), avis = [], L = A11Y.lettres;
				A11Y.sigles.forEach(function (s) {
					var sigle = s.sigle, capitales = sigle.toUpperCase(), fautif;
					if (capitales !== sigle) { fautif = capitales; }
					else if (points(sigle).length >= 4) { fautif = sigle.charAt(0) + sigle.slice(1).toLowerCase(); }
					else { return; }
					if (new RegExp('(^|[^' + L + '])' + echappeSigle(fautif) + '(?=$|[^' + L + '])').test(texte)) {
						avis.push(phraseA11y('sigle_casse', { ecrit: fautif, sigle: sigle }));
					}
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
							+ NBSP + ': retirez-la et posez la liste avec le bouton Liste à puces.');
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
				var attenteDesConseils = null;
				quill.root.addEventListener('blur', function () {
					window.clearTimeout(attenteDesConseils);
					texteVu = true;
					majConseilsDuTexte(false);
				});
				quill.on('text-change', function (d, o, source) {
					if (source === 'user') { texteVu = true; }
					window.clearTimeout(attenteDesConseils);
					// L'avis d'un paragraphe collé sans point final paraissait au
					// collage, s'effaçait une seconde après — le curseur étant au bout,
					// on le croyait en cours de frappe — et ne revenait qu'en quittant
					// le texte. Après un collage, rien n'est en cours.
					if (!collageEnCours) { apresCollage = false; }
					attenteDesConseils = window.setTimeout(function () { majConseilsDuTexte(!apresCollage); }, 1000);
				});
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
					// La première lettre en capitale, comme le titre l'imprimera :
					// « la Croix-Blanche » devient « La Croix-Blanche ».
					var cap = capitaleInitiale(lieuDitInput.value);
					if (cap !== lieuDitInput.value) { lieuDitInput.value = cap; marquerModifie(); }
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
				['.na-commune', 120, 'Le lieu'], ['.na-organisme', 200, 'L’opérateur'], ['#na-commentaires', 3000, 'Le message à la rédaction'],
				['#na-rapport', 500, 'L’adresse du rapport'], ['#na-donnees', 500, 'L’adresse du jeu de données'], ['#na-originaux', 500, 'Le lien des originaux']
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
			// Le caractère qu'aucune adresse ne porte : « ; », « , », une
			// espace (rendue « ␣ »), un second « @ »… Il se dit dès la frappe,
			// quand une adresse seulement inachevée attend la sortie du champ
			// pour ne pas alerter à chaque lettre.
			function horsDUneAdresse(v) {
				v = String(v).trim();
				var m = /[\s;,:<>()[\]\\"]/.exec(v);
				if (m) { return /\s/.test(m[0]) ? '␣' : m[0]; }
				var a = v.indexOf('@');
				return a !== -1 && v.indexOf('@', a + 1) !== -1 ? '@' : '';
			}
			var COURRIELS = ['resp_email', 'coresp_email', 'coauteur_email', 'deposant_email'];
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
				normaliserUneAdresse(document.getElementById('na-donnees'));
				normaliserUneAdresse(document.getElementById('na-originaux'));
			}
			['na-rapport', 'na-donnees', 'na-originaux'].forEach(function (id) {
				if (document.getElementById(id)) {
					document.getElementById(id).addEventListener('blur', normaliserRapport);
				}
			});
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
				rubrique_principale: function () { return !vide(el('na-rubrique').value); },
				// La matière, demandée par la seule rubrique V : masquée, elle ne
				// compte pas.
				rubrique_matiere: function () {
					var b = el('na-matiere-bloc');
					return !b || b.hidden || !vide(el('na-matiere').value);
				},
				nature: function () { return !!form.querySelector('input[name="nature[]"]:checked'); },
				annee: function () { return !vide(el('na-annee').value); },
				organisme: function () { return uneValeur('.na-organisme'); },
				id_patriarche: function () { return !vide(el('na-patriarche').value); },
				rapport_lien: function () {
					var v = el('na-rapport').value.trim();
					return !v || /^https?:\/\/[^\s\/]+\.[^\s]+$/i.test(v);
				},
				donnees_lien: function () {
					var v = el('na-donnees').value.trim();
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
				deposant_email: function () { var v = el('na-deposant-email').value.trim(); return !v || courrielValide(v); },
				texte: function () { return !quill || !vide(quill.getText()); },
				// Le titre, les crédits et le texte alternatif de chaque figure
				// choisie. Le contrôle ne joue que si la carte est là : sans
				// fichier, rien à décrire.
				illus_titre_1: function () { return champDeFigureDonne(1, 'titre'); },
				illus_credits_1: function () { return champDeFigureDonne(1, 'credits'); },
				illus_type_1: function () { return champDeFigureDonne(1, 'type'); },
				illus_alt_1: function () { return champDeFigureDonne(1, 'alt'); },
				illus_titre_2: function () { return champDeFigureDonne(2, 'titre'); },
				illus_credits_2: function () { return champDeFigureDonne(2, 'credits'); },
				illus_type_2: function () { return champDeFigureDonne(2, 'type'); },
				illus_alt_2: function () { return champDeFigureDonne(2, 'alt'); },
				illus_titre_3: function () { return champDeFigureDonne(3, 'titre'); },
				illus_credits_3: function () { return champDeFigureDonne(3, 'credits'); },
				illus_type_3: function () { return champDeFigureDonne(3, 'type'); },
				illus_alt_3: function () { return champDeFigureDonne(3, 'alt'); },
				originaux_lien: function () {
					var c = el('na-originaux');
					var v = c ? c.value.trim() : '';
					return !v || /^https?:\/\/[^\s\/]+\.[^\s]+$/i.test(v);
				},
				// La date d'expiration, demandée avec un lien : elle ne peut être
				// passée.
				originaux_jusqua: function () {
					var lien = el('na-originaux'), date = el('na-originaux-jusqua');
					if (!lien || !date || vide(lien.value)) { return true; }
					var j = new Date(), aujourdhui = j.getFullYear() + '-' + ('0' + (j.getMonth() + 1)).slice(-2) + '-' + ('0' + j.getDate()).slice(-2);
					return /^\d{4}-\d{2}-\d{2}$/.test(date.value) && date.value >= aujourdhui;
				},
				// Une période et un sujet au moins, libres ou du thésaurus.
				pactols_periods: function () { return termesRetenus('pactols_periods'); },
				pactols_subjects: function () { return termesRetenus('pactols_subjects'); },
				puzzle: function () { return puzzleFait(); }
			};
			function termesRetenus(id) {
				var c = el(id), lu = [];
				if (!c) { return true; }
				try { lu = c.value ? JSON.parse(c.value) : []; } catch (e) { lu = []; }
				return Array.isArray(lu) && lu.some(function (it) { return it && !vide(String(it.label || '')); });
			}
			function champDeFigureDonne(rang, clef) {
				var c = el('na-illus-' + rang + '-' + clef);
				return !c || rang > selected.length || !vide(c.value);
			}
			// Le lien et la date d'une figure envoyée à part : sans eux, la
			// rédaction ne saurait où ni jusqu'à quand la télécharger.
			function lienValable(v) { return /^https?:\/\/[^\s\/]+\.[^\s]+$/i.test(String(v || '').trim()); }
			function envoiDeFigureDonne(rang, clef) {
				var f = selected[rang - 1], c = el('na-illus-' + rang + '-' + clef);
				if (!f || !f.aPart || !c) { return true; }
				return clef === 'lien' ? lienValable(c.value)
					: /^\d{4}-\d{2}-\d{2}$/.test(c.value) && c.value >= aujourdhuiISO();
			}
			for (var rangEnvoi = 1; rangEnvoi <= MAX_FILES; rangEnvoi++) {
				(function (rang) {
					CONTROLES['illus_lien_' + rang] = function () { return envoiDeFigureDonne(rang, 'lien'); };
					CONTROLES['illus_jusqua_' + rang] = function () { return envoiDeFigureDonne(rang, 'jusqua'); };
				}(rangEnvoi));
			}
			// Les figures déjà déposées : mêmes champs obligatoires, sauf pour
			// celle qu'on retire.
			gardees.forEach(function (g) {
				// « Remplacer » sans fichier passait pour « garder ».
				CONTROLES['garde_fichier_' + g.k] = function () {
					return actionDe(g) !== 'remplacer' || !!(g.fichier && g.fichier.files && g.fichier.files.length);
				};
				['titre', 'credits', 'type', 'alt'].forEach(function (clef) {
					CONTROLES['garde_' + clef + '_' + g.k] = function () {
						var c = el('na-garde-' + g.k + '-' + clef);
						return !c || actionDe(g) === 'retirer' || !vide(c.value);
					};
				});
				// Gardée telle quelle, la figure envoyée à part garde son lien
				// et sa date ; remplacée par un fichier, elle n'en a plus besoin.
				CONTROLES['garde_lien_' + g.k] = function () {
					var c = el('na-garde-' + g.k + '-lien');
					return !c || actionDe(g) !== 'garder' || lienValable(c.value);
				};
				CONTROLES['garde_jusqua_' + g.k] = function () {
					var c = el('na-garde-' + g.k + '-jusqua');
					return !c || actionDe(g) !== 'garder' || /^\d{4}-\d{2}-\d{2}$/.test(c.value);
				};
			});
			function cibleDe(cle) {
				if (cle === 'nature') { return el('na-nature'); }
				if (cle === 'texte') { return quill ? quill.root : el('na-texte'); }
				return el(CHAMPS[cle][0]);
			}
			function focaliser(cle) {
				var c = cle === 'nature' ? el('na-nature-1') : cibleDe(cle);
				// Revenu d'un refus, le formulaire n'a plus les fichiers : le
				// lien du texte alternatif mène à la zone où les redéposer.
				if (!c && /^illus_(titre|credits|alt|type)_/.test(cle)) { c = fileInput; }
				if (!c) { return; }
				// Un champ dans un bloc replié ne reçoit pas le focus : on l'ouvre.
				var replie = c.closest ? c.closest('details') : null;
				if (replie) { replie.open = true; }
				if (c.scrollIntoView) { c.scrollIntoView({ block: 'center' }); }
				try { c.focus({ preventScroll: true }); } catch (e) { c.focus(); }
			}
			function messageDe(cle, resultat) {
				if (COURRIELS.indexOf(cle) !== -1) {
					var base = cle === 'resp_email' && resultat === 'forme' ? CHAMPS.email_forme[1] : CHAMPS[cle][1];
					var c = cibleDe(cle), hors = c ? horsDUneAdresse(c.value) : '';
					if (!hors) { return base; }
					return (hors === '␣' ? 'Une adresse électronique ne contient pas d’espace'
						: hors === '@' ? 'Une adresse électronique ne contient qu’un ' + guillemets('@')
						: 'Une adresse électronique ne contient pas de ' + guillemets(hors)) + '. ' + base;
				}
				// La figure se nomme aussi par son fichier : le numéro suit
				// l'ordre, qu'on a pu changer.
				var rang = /^illus_(?:titre|credits|alt|type|lien|jusqua)_(\d)$/.exec(cle);
				if (rang && selected[rang[1] - 1]) {
					// Après des figures déjà déposées, le numéro est celui de la page.
					return CHAMPS[cle][1].replace(/figure \d/, 'figure ' + (decalage() + Number(rang[1]))) + ' (' + nomDeCarte(selected[rang[1] - 1]) + ')';
				}
				return CHAMPS[cle][1];
			}
			// Après un retrait, un déplacement ou un ajout, les cartes sont
			// neuves : l'erreur d'un texte alternatif se repose sur le bon
			// champ, ou s'efface si elle n'a plus lieu d'être.
			// Une figure sans texte alternatif qu'on déplace emporte son erreur
			// à sa nouvelle place.
			function revoirLesTextesAlternatifs() {
				if (!erreurs || !Object.keys(erreurs).some(function (k) { return /^illus_(titre|credits|alt|type)_/.test(k); })) { return; }
				for (var rang = 1; rang <= MAX_FILES; rang++) {
					['titre', 'credits', 'type', 'alt'].forEach(function (clef) {
						var cle = 'illus_' + clef + '_' + rang;
						var r = CONTROLES[cle]();
						if (r === true) { effacer(cle); } else { signaler(cle, messageDe(cle, r)); }
					});
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
			// Le titre d'une liste du cadre, selon ce qu'elle tient : ce qu'il faut
			// corriger, ce qu'il suffit de vérifier, ou, dans le cadre rendu par
			// le serveur, les points restants.
			function titreDeListe(groupe, n) {
				if (groupe === 'corriger') { return 'À corriger (' + n + ')'; }
				if (groupe === 'verifier') { return 'À vérifier avant l’envoi (' + n + ')'; }
				return n > 1 ? 'Il reste ' + n + ' points à corriger' : 'Il reste un point à corriger';
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
					[].slice.call(boite.querySelectorAll('ul')).forEach(function (liste) {
						var n = liste.querySelectorAll('li').length;
						var h = liste.previousElementSibling && liste.previousElementSibling.tagName === 'H2' ? liste.previousElementSibling : null;
						var groupe = liste.getAttribute('data-groupe');
						if (n) {
							if (h) { h.textContent = titreDeListe(groupe, n); }
							return;
						}
						if (h) { h.remove(); }
						if (groupe === 'verifier') {
							var conf = boite.querySelector('.na-confirmer');
							if (conf) { conf.remove(); }
						}
						liste.remove();
					});
					if (!boite.querySelector('ul') && vide(boite.textContent)) { boite.hidden = true; }
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
			form.addEventListener('na-termes', rafraichir);
			// « ajkdk@df;fr » se signale au « ; », sans attendre qu'on quitte
			// le champ. Le caractère ôté, l'alerte tombe ; la sortie du champ
			// dira si l'adresse reste incomplète.
			var signaleEnFrappe = {};
			form.addEventListener('input', function (e) {
				var c = e.target, cle = '';
				COURRIELS.forEach(function (k) { if (c && c.id && CHAMPS[k][0] === c.id) { cle = k; } });
				if (!cle || !cibleDe(cle)) { return; }
				if (horsDUneAdresse(c.value)) {
					signaler(cle, messageDe(cle, CONTROLES[cle]()));
					signaleEnFrappe[cle] = true;
				} else if (signaleEnFrappe[cle]) {
					delete signaleEnFrappe[cle];
					if (CONTROLES[cle]() !== true) { effacer(cle); }
				}
			});
			// Une adresse mal formée se dit dès qu'on quitte le champ : on ne
			// l'apprenait qu'à l'envoi, loin de la saisie. Un champ laissé vide
			// ne se signale pas en passant ; l'envoi dira s'il était attendu.
			var VERIFIES_EN_SORTANT = {
				'na-resp-email': 'resp_email', 'na-coresp-email': 'coresp_email',
				'na-coauteur-email': 'coauteur_email', 'na-deposant-email': 'deposant_email',
				'na-rapport': 'rapport_lien', 'na-donnees': 'donnees_lien', 'na-originaux': 'originaux_lien'
			};
			form.addEventListener('focusout', function (e) {
				var c = e.target;
				var cle = c && c.id && Object.prototype.hasOwnProperty.call(VERIFIES_EN_SORTANT, c.id) ? VERIFIES_EN_SORTANT[c.id] : '';
				if (!cle || !CONTROLES[cle] || !cibleDe(cle)) { return; }
				if (/^na-(rapport|donnees|originaux)$/.test(c.id)) { normaliserUneAdresse(c); }
				delete signaleEnFrappe[cle];
				if (vide(c.value)) { return; }
				var r = CONTROLES[cle]();
				if (r === true) { effacer(cle); } else { signaler(cle, messageDe(cle, r)); }
			});
			function valider() {
				var liste = [];
				signaleEnFrappe = {};
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
			// Les avis qui empêchent l'envoi, sauf confirmation. Tous les autres
			// avis restent des conseils ; ceux-ci touchent à ce que la revue
			// imprime : les appels de figure (la 2 avant la 1, une figure jamais
			// appelée ou appelée sans être jointe) et un texte alternatif qui ne
			// décrit rien (il reprend le titre ou la légende, il n'est qu'un nom
			// de fichier ou un « Photo 3 »). La définition d'une image et les
			// données d'un graphique n'en font pas partie.
			//
			// La confirmation existe parce qu'un texte cite des figures d'autres
			// publications (« fig. 12 de l'article précédent ») : le formulaire
			// ne peut pas le savoir, et une notice ne doit pas rester en rade
			// pour cela. Elle ne se coche qu'une fois les points lus.
			var AVIS_BLOQUANTS_A11Y = ['titre', 'legende', 'fichier', 'sans_contenu'];
			var avisConfirmes = false;
			function avisBloquants() {
				var liste = [];
				if (quill && avisActif('figures_appelees')) {
					var paras = quill.getText().split('\n').map(function (p) { return p.trim(); }).filter(Boolean);
					appelsDeFigure(paras.join('\n'), decalage() + selected.length).forEach(function (t) {
						liste.push({ message: t, id: 'na-texte-editeur' });
					});
				}
				var tous = { titre: true, legende: true, alt: true, description: true, type: true };
				selected.forEach(function (f, idx) {
					var m = illusMeta[idx] || {}, ecoute = separerEcoute(m.alt);
					var lu = { titre: m.titre, legende: m.legende, credits: m.credits, alt: ecoute.alt, description: ecoute.description, type: m.type };
					avisDAccessibilite(lu, decalage() + idx + 1, tous, f.aPart ? (m.nomEnvoi || '') : f.name, AVIS_BLOQUANTS_A11Y).forEach(function (t) {
						liste.push({ message: t, id: 'na-illus-' + (idx + 1) + '-alt' });
					});
				});
				return liste;
			}
			// Deux listes : ce qui est faux et doit se corriger, puis ce que le
			// formulaire ne peut pas trancher (les appels de figure, les textes
			// alternatifs) et que l'auteur vérifie avant d'envoyer tel quel.
			// Réunis sous « Il reste N points à corriger », les seconds
			// passaient pour des erreurs, et la case qui les confirmait se
			// lisait de deux façons.
			function montrerRecap(liste) {
				recap.innerHTML = '';
				var groupes = {
					corriger: liste.filter(function (er) { return !er.bloquant; }),
					verifier: liste.filter(function (er) { return er.bloquant; })
				};
				['corriger', 'verifier'].forEach(function (groupe) {
					if (!groupes[groupe].length) { return; }
					var h = document.createElement('h2');
					h.textContent = titreDeListe(groupe, groupes[groupe].length);
					var ul = document.createElement('ul');
					ul.setAttribute('data-groupe', groupe);
					groupes[groupe].forEach(function (er) { ul.appendChild(lienVers(er.cle, er.message)); });
					recap.appendChild(h);
					recap.appendChild(ul);
				});
				if (groupes.verifier.length) {
					var conf = document.createElement('p');
					conf.className = 'na-confirmer';
					var etiquette = document.createElement('label');
					var coche = document.createElement('input');
					coche.type = 'checkbox';
					coche.id = 'na-avis-confirmes';
					coche.checked = avisConfirmes;
					coche.addEventListener('change', function () { avisConfirmes = coche.checked; });
					etiquette.appendChild(coche);
					etiquette.appendChild(document.createTextNode(' J’ai vérifié ces points' + NBSP + ': ma notice est correcte ainsi (par exemple, elle renvoie à la figure d’un autre article). L’envoyer telle quelle.'));
					conf.appendChild(etiquette);
					recap.appendChild(conf);
				}
				recap.setAttribute('role', 'alert');
				recap.hidden = false;
				if (messageServeur) { messageServeur.hidden = true; }
				document.title = (groupes.corriger.length ? 'Erreur' : 'À vérifier') + NBSP + ': ' + titreDeLaPage;
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
			// Les cases à cocher, les boutons radio et les dates envoyaient
			// encore : on parcourt la nature d'opération au clavier, et Entrée
			// partait avec tout le formulaire. Seul le bouton d'envoi envoie.
			form.addEventListener('keydown', function (e) {
				if (e.key !== 'Enter' || e.defaultPrevented) { return; }
				var t = e.target;
				if (t.tagName === 'INPUT' && !/^(submit|button|image|reset|file)$/.test(t.type)) { e.preventDefault(); }
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
			// Rien ne disait que la saisie était gardée : on n'osait pas fermer
			// la page. Le témoin le dit, à la minute près, sans l'annoncer au
			// lecteur d'écran toutes les cinq secondes.
			var temoin = el('na-temoin');
			function enregistrer(force) {
				if (!brouillonActif || (!modifie && !force)) { return; }
				modifie = false;
				var photo = photographier();
				if (!contenuUtile(photo)) { return; }
				if (!ecrireBrouillon(JSON.stringify(photo))) {
					if (temoin) {
						temoin.textContent = 'Ce navigateur ne garde pas la saisie (navigation privée ou stockage refusé)' + NBSP + ': ne fermez pas la page avant l’envoi.';
						temoin.hidden = false;
					}
					return;
				}
				if (temoin) {
					var h = new Date(photo.quand);
					var mn = h.getMinutes();
					temoin.textContent = 'Saisie enregistrée sur cet appareil à ' + h.getHours() + NBSP + 'h' + NBSP + (mn < 10 ? '0' : '') + mn + '.';
					temoin.hidden = false;
				}
				if (avisBrouillon) {
					// L'ancien brouillon vient d'être remplacé : l'offre ne tient plus.
					avisBrouillon.remove();
					avisBrouillon = null;
				}
			}
			// Un brouillon d'avant les types gardait « Fouille préventive » en
			// une case : elle revient à Fouille et à Préventive, sans quoi la
			// nature se perdrait à la reprise.
			var NATURES_D_AVANT = <?php echo wp_json_encode( $this->natures_d_avant(), JSON_HEX_TAG | JSON_HEX_AMP ); ?>;
			function casesDAujourdhui(ch) {
				if (!Array.isArray(ch['nature[]'])) { return ch; }
				var copie = {};
				Object.keys(ch).forEach(function (k) { copie[k] = ch[k]; });
				var cadres = Array.isArray(ch['cadre[]']) ? ch['cadre[]'].slice() : [];
				var natures = [];
				ch['nature[]'].forEach(function (t) {
					var d = Object.prototype.hasOwnProperty.call(NATURES_D_AVANT, t) ? NATURES_D_AVANT[t] : { cadres: [], natures: [t] };
					d.cadres.forEach(function (c) { if (cadres.indexOf(c) === -1) { cadres.push(c); } });
					d.natures.forEach(function (n) { if (natures.indexOf(n) === -1) { natures.push(n); } });
				});
				copie['nature[]'] = natures;
				copie['cadre[]'] = cadres;
				return copie;
			}
			function restaurer(b) {
				var ch = casesDAujourdhui(b.champs || {});
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
				if (lieuDitRiche) { lieuDitRiche.charger(); }
				gardeesRiches.forEach(function (r) { r.charger(); });
				// Les textes des figures attendent leur fichier : ils s'y
				// rattacheront par son nom, dans quelque ordre qu'on les redépose.
				if (Array.isArray(b.illus)) {
					orphelins = orphelins.concat(reprendreLesFiguresAPart(b.illus.filter(function (m) { return m && (m.aPart || m.nom || aDuTexte(m)); })));
					rattacher();
					refreshFileInput();
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
				// Un brouillon garde noms et courriels sur l'appareil : au bout de
				// trente jours, on ne le propose plus et on l'oublie.
				if (brouillon && brouillon.quand && (Date.now() - brouillon.quand) > 30 * 86400000) {
					oublierBrouillon();
					brouillon = null;
				}
				if (brouillon && brouillon.champs && contenuUtile(brouillon)) {
					avisBrouillon = document.createElement('div');
					avisBrouillon.className = 'na-message na-avis';
					avisBrouillon.id = 'na-brouillon';
					var dit = document.createElement('p');
					// Deux notices en cours sur le même poste : l'offre ne disait
					// pas laquelle elle reprenait.
					var nomDuBrouillon = (function (ch) {
						var communes = Array.isArray(ch['commune[]']) ? ch['commune[]'] : [];
						var commune = communes.map(function (x) { return String(x || '').trim(); }).filter(Boolean)[0] || '';
						var lieuDit = typeof ch.lieu_dit === 'string' ? ch.lieu_dit.trim() : '';
						return [commune, lieuDit].filter(Boolean).join(', ');
					})(brouillon.champs);
					dit.textContent = (nomDuBrouillon
						? 'Une saisie de la notice ' + guillemets(nomDuBrouillon) + ', enregistrée sur cet appareil '
						: 'Une saisie enregistrée sur cet appareil ')
						+ quandLisible(brouillon.quand || Date.now()) + ', a été retrouvée.';
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
								+ NBSP + ': ' + noms.join(', ') + ' — ' + (noms.length > 1 ? 'leurs légendes et descriptions reviendront' : 'sa légende et sa description reviendront')
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
					['notice_reprise', 'notice_envoyee', 'notice_erreur', 'notice_champ', 'notice_champs', 'notice_ref', 'notice_corrigee', 'notice_sans_fichiers'].forEach(function (k) {
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
			// ── L'envoi en arrière-plan ──
			//
			// La page envoie elle-même, et non le navigateur : une connexion
			// qui flanchait laissait une page blanche, « Request Time-out »,
			// sans dire si la notice était partie. La barre mesure désormais
			// ce qui est parti. Une coupure laisse la page en place, saisie et
			// fichiers compris ; elle demande au serveur s'il a reçu la notice,
			// renvoie une fois d'elle-même si rien n'est arrivé, puis dit quoi
			// faire. Le jeton, le même à chaque renvoi, empêche le doublon.
			var jetonDEnvoi = (function () {
				var t = '';
				try {
					var a = new Uint8Array(16);
					window.crypto.getRandomValues(a);
					t = Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
				} catch (x) { t = ''; }
				while (t.length < 24) { t += Math.random().toString(36).slice(2); }
				return t.replace(/[^a-z0-9]/g, '').slice(0, 32);
			}());
			var enArrierePlan = (function () {
				try { return !!(window.FormData && window.XMLHttpRequest && ('upload' in new XMLHttpRequest())); } catch (x) { return false; }
			}());
			var envoiRate = el('na-envoi-rate');
			var pisteEnvoi = form.querySelector('.na-envoi-piste');
			var barreEnvoi = form.querySelector('.na-envoi-barre');
			var motEnvoi = form.querySelector('.na-envoi-mot');
			var motEnvoiInitial = motEnvoi ? motEnvoi.textContent : '';
			var renvoisAuto = 0;
			var attentes = 0;
			function megaOctets(n) {
				return (n / 1048576).toFixed(n < 10485760 ? 1 : 0).replace('.', ',') + NBSP + 'Mo';
			}
			function direEnvoi(texte) { if (motEnvoi) { motEnvoi.textContent = texte; } }
			function mesurerEnvoi(fait, total) {
				if (!barreEnvoi || !pisteEnvoi) { return; }
				if (!(total > 0)) {
					barreEnvoi.classList.remove('na-envoi-barre--mesuree');
					barreEnvoi.style.width = '';
					['role', 'aria-valuemin', 'aria-valuemax', 'aria-valuenow', 'aria-label'].forEach(function (a) { pisteEnvoi.removeAttribute(a); });
					return;
				}
				var pc = Math.max(0, Math.min(100, Math.floor(fait * 100 / total)));
				barreEnvoi.classList.add('na-envoi-barre--mesuree');
				barreEnvoi.style.width = pc + '%';
				pisteEnvoi.setAttribute('role', 'progressbar');
				pisteEnvoi.setAttribute('aria-label', 'Envoi de la notice');
				pisteEnvoi.setAttribute('aria-valuemin', '0');
				pisteEnvoi.setAttribute('aria-valuemax', '100');
				pisteEnvoi.setAttribute('aria-valuenow', String(pc));
				direEnvoi('Envoi en cours' + NBSP + ': ' + pc + NBSP + '%, ' + megaOctets(fait) + ' sur ' + megaOctets(total) + '. Ne fermez pas cette page.');
			}
			// La page redevient utilisable, telle qu'avant le clic.
			function rendreLaPage(message) {
				form.removeAttribute('data-envoi');
				if (bouton) {
					bouton.disabled = false;
					bouton.textContent = libelleBouton;
				}
				document.getElementById('na-envoi').hidden = true;
				mesurerEnvoi(0, 0);
				direEnvoi(motEnvoiInitial);
				var relais = form.querySelector('input[type=hidden][name="notice_archeomed_envoi"]');
				if (relais) { relais.remove(); }
				try { window.sessionStorage.removeItem('na_envoi'); } catch (x) {}
				if (envoiRate && message) {
					envoiRate.textContent = message;
					envoiRate.hidden = false;
					envoiRate.scrollIntoView({ block: 'center' });
					envoiRate.focus();
				}
			}
			function messageDeCoupure() {
				return 'L’envoi n’a pas abouti' + NBSP + ': la connexion avec le serveur s’est interrompue. Votre saisie et vos fichiers sont toujours dans cette page. '
					+ 'Vérifiez votre connexion, puis cliquez de nouveau sur ' + guillemets(libelleBouton) + NBSP + ': la notice ne partira pas en double.';
			}
			function messageTropLourd() {
				return 'L’envoi dépasse ce que le serveur accepte en une fois' + NBSP + ': rien n’a été reçu. Votre saisie et vos fichiers sont toujours dans cette page. '
					+ 'Allégez les illustrations, ou envoyez la plus lourde à part par un service de transfert, puis cliquez de nouveau sur ' + guillemets(libelleBouton) + '.';
			}
			function poster(donnees, progres, fin) {
				var xhr = new XMLHttpRequest();
				var fini = false;
				function finir(r) { if (fini) { return; } fini = true; fin(r); }
				xhr.open('POST', form.action || window.location.href, true);
				xhr.setRequestHeader('X-NA-Envoi', '1');
				if (progres && xhr.upload) {
					xhr.upload.onprogress = function (ev) { if (ev.lengthComputable) { progres(ev.loaded, ev.total); } };
					xhr.upload.onload = function () { progres(-1, 0); };
				}
				xhr.onload = function () { finir(xhr); };
				xhr.onerror = xhr.ontimeout = xhr.onabort = function () { finir(null); };
				try { xhr.send(donnees); } catch (x) { finir(null); }
			}
			// Ce que le serveur a répondu : une adresse où aller, une attente,
			// rien de reçu, ou un échec avec son code.
			function issue(xhr) {
				if (!xhr) { return { echec: 0 }; }
				var r = null;
				if (xhr.status === 200) {
					try { r = JSON.parse(xhr.responseText); } catch (x) { r = null; }
				}
				if (r && typeof r === 'object' && (r.redirect || r.attendre || r.absente)) { return r; }
				// Un serveur qui aurait redirigé quand même : le navigateur a
				// suivi, et l'adresse finale est la page du résultat.
				if (xhr.status === 200 && xhr.responseURL && /[?&]notice_envoyee=/.test(xhr.responseURL)) { return { redirect: xhr.responseURL }; }
				return { echec: xhr.status };
			}
			function aller(adresse) {
				var u = null;
				try { u = new URL(adresse, window.location.href); } catch (x) { u = null; }
				if (!u || u.origin !== window.location.origin) { return false; }
				// Trop lourd : rien n'a été reçu ni gardé. La page reste, avec
				// ses fichiers, pour qu'on en retire un.
				if (u.searchParams.get('notice_erreur') === 'trop_lourd') {
					rendreLaPage(messageTropLourd());
					return true;
				}
				window.location.assign(u.toString());
				return true;
			}
			function envoyerLaNotice() {
				if (envoiRate) { envoiRate.hidden = true; }
				var donnees = new FormData(form);
				donnees.append('na_arriere_plan', '1');
				donnees.append('na_page', jetonDEnvoi);
				poster(donnees, function (fait, total) {
					if (fait < 0) {
						mesurerEnvoi(0, 0);
						direEnvoi('Tout est parti. Le serveur enregistre la notice' + FINE + '; ne fermez pas cette page.');
						annoncer('Fichiers transmis. Le serveur enregistre la notice.');
						return;
					}
					mesurerEnvoi(fait, total);
				}, function (xhr) { suiteDeLEnvoi(issue(xhr), true); });
			}
			// La page demande ce que sa notice est devenue, sans renvoyer ses
			// fichiers.
			function sonder() {
				var donnees = new FormData();
				donnees.append('notice_archeomed_envoi', '1');
				donnees.append('na_arriere_plan', '1');
				donnees.append('na_page', jetonDEnvoi);
				donnees.append('na_sonde', '1');
				poster(donnees, null, function (xhr) { suiteDeLEnvoi(issue(xhr), false); });
			}
			function suiteDeLEnvoi(r, complet) {
				if (r.redirect) {
					if (!aller(r.redirect)) { rendreLaPage(messageDeCoupure()); }
					return;
				}
				if (r.attendre) {
					// Une autre requête de cette page est en route : on attend
					// sa fin, cinq minutes au plus, sans rien renvoyer.
					attentes += 1;
					if (attentes <= 60) {
						direEnvoi('Le serveur enregistre la notice' + FINE + '; ne fermez pas cette page.');
						window.setTimeout(sonder, Math.max(2, Math.min(30, Number(r.attendre) || 5)) * 1000);
						return;
					}
					attentes = 0;
					rendreLaPage('Le serveur n’a pas confirmé la réception de la notice. Cliquez de nouveau sur ' + guillemets(libelleBouton)
						+ NBSP + ': si elle est reçue, la confirmation s’affichera, et elle ne partira pas en double.');
					return;
				}
				if (r.absente) {
					// Rien n'est arrivé : une fois, la page renvoie d'elle-même.
					if (renvoisAuto < 1) {
						renvoisAuto += 1;
						direEnvoi('La connexion a flanché' + FINE + '; la page renvoie la notice. Ne fermez pas cette page.');
						annoncer('La connexion a flanché' + NBSP + ': la page renvoie la notice.');
						envoyerLaNotice();
						return;
					}
					rendreLaPage(messageDeCoupure());
					return;
				}
				if (r.echec === 413) {
					rendreLaPage(messageTropLourd());
					return;
				}
				// Un échec sans réponse lisible : la notice est peut-être
				// arrivée. On le demande avant tout renvoi.
				if (complet) {
					sonder();
					return;
				}
				rendreLaPage(messageDeCoupure());
			}
			form.addEventListener('submit', function (e) {
				if (form.getAttribute('data-envoi') === '1') { e.preventDefault(); return; }
				// Entrée dans un champ enverrait encore une saisie qui n'a plus
				// où aller.
				if (form.getAttribute('data-perimee') === '1') { e.preventDefault(); return; }
				if (!quill) {
					e.preventDefault();
					showJsError('Erreur technique' + NBSP + ': l’éditeur de texte ne s’est pas chargé. Rechargez la page.');
					return;
				}
				if (selected.some(function (f) { var c = etatDe(f).conv; return c && c.etat === 'cours'; })
					|| gardees.some(function (g) { return g.conv && g.conv.etat === 'cours'; })) {
					e.preventDefault();
					refuses.textContent = 'La conversion d’une figure est en cours : patientez quelques secondes, puis envoyez de nouveau.';
					refuses.scrollIntoView({ block: 'center' });
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
				if (!avisConfirmes) {
					avisBloquants().forEach(function (b, i) {
						var cle = 'avis_a_corriger_' + i;
						CHAMPS[cle] = [b.id, b.message];
						liste.push({ cle: cle, message: b.message, bloquant: true });
					});
				}
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
				// Sans ce qu'il faut pour envoyer elle-même, la page laisse le
				// navigateur envoyer, comme avant.
				if (enArrierePlan) {
					e.preventDefault();
					renvoisAuto = 0;
					attentes = 0;
					mesurerEnvoi(0, 0);
					direEnvoi(motEnvoiInitial);
					envoyerLaNotice();
				}
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
				if (envoiRate) { envoiRate.hidden = true; }
				var relais = form.querySelector('input[type=hidden][name="notice_archeomed_envoi"]');
				if (relais) { relais.remove(); }
			});
			initialisationFinie = true;
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
			'h3' => array(),
			'ul' => array(),
			'li' => array(),
		);
		// La notice n'a qu'un niveau d'intertitre, « h3 » : un titre d'un
		// autre niveau, collé d'ailleurs ou gardé d'une notice ancienne, le
		// rejoint. Le second niveau, « h4 », en donnait un quatrième au
		// fascicule, sous la rubrique, la sous-rubrique et le titre.
		$html = preg_replace( '#<(/?)h[1-6](\s[^>]*)?>#i', '<$1h3>', (string) $html );
		// Une citation collée fait un paragraphe. Sans cela, kses ôtait la
		// balise en gardant le texte, et deux blocs se soudaient.
		$html = preg_replace( '#</(blockquote|div|pre|tr)\s*>#i', '</p><p>', (string) $html );
		// La liste est à puces, et n'a qu'un niveau : une liste numérotée la
		// rejoint, comme le titre d'un autre niveau rejoint l'intertitre.
		$html = preg_replace( '#<(/?)ol\b[^>]*>#i', '<$1ul>', (string) $html );
		return self::listes_a_un_niveau( self::petites_capitales_seules( wp_kses( $html, $allowed ) ) );
	}

	/**
	 * Les listes du texte, à plat : « <ul><li>…</li></ul> », un élément par
	 * ligne de la liste, sans liste dans la liste.
	 *
	 * Une liste emboîtée, collée d'ailleurs, donne ses éléments à la liste
	 * qui la contient. Un paragraphe dans un élément s'y réduit à un saut de
	 * ligne ; un élément vide s'ôte, une liste vide aussi. Un élément égaré
	 * hors de toute liste en reçoit une.
	 */
	private static function listes_a_un_niveau( $html ) {
		$html = (string) $html;
		if ( ! preg_match( '#<(?:ul|li)\b#i', $html ) ) {
			return $html;
		}
		$morceaux  = preg_split( '#(</?(?:ul|li)\b[^>]*>)#i', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		$sortie    = '';
		$elements  = array();
		$profondeur = 0;
		$element   = null;
		$clore     = function () use ( &$element, &$elements ) {
			if ( null === $element ) {
				return;
			}
			$propre  = preg_replace( '#<(?:p|h3)\b[^>]*>#i', '', $element );
			$propre  = preg_replace( '#</(?:p|h3)\s*>#i', '<br>', $propre );
			$propre  = preg_replace( '#^(?:\s|<br\s*/?>)+|(?:\s|<br\s*/?>)+$#i', '', $propre );
			if ( '' !== trim( wp_strip_all_tags( $propre ) ) ) {
				$elements[] = $propre;
			}
			$element = null;
		};
		$vider     = function () use ( &$elements, &$sortie ) {
			if ( $elements ) {
				$sortie  .= '<ul><li>' . implode( '</li><li>', $elements ) . '</li></ul>';
				$elements = array();
			}
		};
		foreach ( $morceaux as $i => $morceau ) {
			if ( 1 === $i % 2 ) {
				$balise = strtolower( preg_replace( '#^</?(\w+).*$#s', '$1', $morceau ) );
				$ferme  = '/' === $morceau[1];
				if ( 'ul' === $balise ) {
					$profondeur = max( 0, $profondeur + ( $ferme ? -1 : 1 ) );
					if ( $ferme && 0 === $profondeur ) {
						$clore();
						$vider();
					}
				} elseif ( $ferme ) {
					$clore();
				} else {
					$clore();
					$element = '';
				}
				continue;
			}
			if ( null !== $element ) {
				$element .= $morceau;
			} elseif ( $profondeur > 0 ) {
				// Du texte entre deux éléments, après une liste emboîtée :
				// il devient un élément, plutôt que de se perdre.
				if ( '' !== trim( wp_strip_all_tags( $morceau ) ) ) {
					$element = $morceau;
					$clore();
				}
			} else {
				$vider();
				$sortie .= $morceau;
			}
		}
		$clore();
		$vider();
		return $sortie;
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
	/** Les cases d'un groupe envoyé, nettoyées : des chaînes, rien d'autre. */
	private function cases_postees( $name ) {
		if ( empty( $_POST[ $name ] ) || ! is_array( $_POST[ $name ] ) ) {
			return array();
		}
		$cases = array();
		foreach ( array_slice( wp_unslash( $_POST[ $name ] ), 0, 30 ) as $val ) {
			if ( is_string( $val ) ) {
				$cases[] = sanitize_text_field( $val );
			}
		}
		return $cases;
	}

	/**
	 * Les termes de la nature, composés des cases cochées, avec leur ARK :
	 * un tableau d'items { label, ark }.
	 */
	private function collect_natures_items() {
		$items = array();
		foreach ( $this->composer_les_natures( $this->cases_postees( 'cadre' ), $this->cases_postees( 'nature' ) ) as $terme ) {
			$items[] = array(
				'label'     => $terme,
				'ark'       => $this->natures[ $terme ],
				'idConcept' => '',
			);
		}
		return $items;
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
			$terme = array(
				'label' => $this->limit_string( $label, 250 ),
				'ark' => $this->limit_string( $ark, 500 ),
				'idConcept' => $this->limit_string( $id_concept, 120 ),
				'fullpath' => $this->limit_string( $fullpath, 800 ),
			);
			// Un terme gardé hors de la liste de Pactols : l'auteur a été
			// prévenu qu'il ne serait pas utilisé. Il se garde, marqué, pour
			// que la rédaction le lise ; une notice d'avant cette marque garde
			// ses termes sans identifiant tels qu'ils étaient.
			if ( '' === $ark ) {
				$terme['libre'] = true;
			}
			$out[] = $terme;
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
		$label = isset( $item['label'] ) ? trim( (string) $item['label'] ) : '';
		if ( ! empty( $item['prefLabel'] ) && ! in_array( $label, $this->natures_a_la_lettre, true ) ) {
			$prefere = trim( (string) $item['prefLabel'] );
			return $capitale ? $this->capitale_initiale( $prefere ) : $prefere;
		}
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
		// Lecture coupée : rien ne manque qu'on attende, et rien ne se dit.
		$manquants = self::lit_pactols() ? count( $this->termes_manquants( $d, $termes ) ) : 0;
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
			if ( self::hors_pactols( $item ) ) {
				continue;
			}
			$label = $this->graphie_imprimee( $item, $capitale );
			// Sans ARK — une notice d'avant les identifiants — le mot reste
			// du texte ordinaire.
			$ark        = Notice_Archeomed_Thesaurus::ark_propre( isset( $item['ark'] ) ? $item['ark'] : '' );
			$morceaux[] = '' !== $ark
				? $doc->hyperlink( $ark, $label )
				: $doc->plain( $label );
		}
		return $morceaux;
	}

	/** Un terme que l'auteur a gardé hors de la liste de Pactols. */
	private static function hors_pactols( $item ) {
		return is_array( $item ) && ! empty( $item['libre'] );
	}

	/**
	 * Les termes gardés hors de Pactols, sous la ligne de leur catégorie, en
	 * « à supprimer » : ils ne sont pas retenus, comme l'auteur en a été
	 * prévenu, mais la rédaction les lit et peut en choisir l'équivalent.
	 */
	private function poser_les_termes_hors_pactols( $doc, $categorie, $items, $capitale = false ) {
		$libres = array();
		foreach ( (array) $items as $item ) {
			if ( self::hors_pactols( $item ) && '' !== trim( (string) $item['label'] ) ) {
				$libres[] = $capitale ? $this->capitale_initiale( $item['label'] ) : $item['label'];
			}
		}
		if ( ! empty( $libres ) ) {
			$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER, array(
				array( 'text' => ( 1 === count( $libres ) ? 'Terme pris hors de la liste de Pactols, non retenu' : 'Termes pris hors de la liste de Pactols, non retenus' )
					. ' (' . $categorie . ') : ' . implode( ', ', $libres ) . '.' ),
			) );
		}
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
		$types = isset( $_POST['illus_type'] ) && is_array( $_POST['illus_type'] ) ? array_values( wp_unslash( $_POST['illus_type'] ) ) : array();
		// La mise en forme du titre, de la légende et de la description, à
		// part : elle ne compte pas les figures, elle habille leurs textes.
		$formes = array();
		foreach ( array_keys( Notice_Archeomed_Forme::CLEFS ) as $clef ) {
			$brut = isset( $_POST[ 'illus_' . $clef ] ) ? wp_unslash( $_POST[ 'illus_' . $clef ] ) : array();
			$formes[ $clef ] = is_array( $brut ) ? array_values( $brut ) : array();
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
			$item  = array( 'rang' => $i + 1 );
			$item['type'] = Notice_Archeomed_Controles::type_de_figure( isset( $types[ $i ] ) ? $types[ $i ] : '' );
			// Un formulaire d'avant ce champ (un onglet resté ouvert pendant une
			// mise à jour, une page en cache) ne l'envoie pas du tout : ce n'est
			// pas un oubli de l'auteur, et le refuser ferait redéposer des
			// fichiers de vingt méga-octets pour rien.
			if ( ! isset( $_POST['illus_type'] ) ) {
				$item['type_absent'] = true;
			}
			$brut = array();
			foreach ( $parts + $formes as $clef => $liste ) {
				$brut[ $clef ] = isset( $liste[ $i ] ) && is_string( $liste[ $i ] ) ? $liste[ $i ] : '';
			}
			$item = array_merge( $item, $this->textes_de_figure( $brut, $item['rang'] ) );
			// Le nom du fichier déposé pour cette figure : un texte alternatif
			// qui ne fait que le reprendre (« DSC_0042 ») reçoit un avis, au
			// dépôt comme sur la fiche.
			if ( isset( $_FILES['illustrations']['name'][ $i ], $_FILES['illustrations']['error'][ $i ] )
				&& is_string( $_FILES['illustrations']['name'][ $i ] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES['illustrations']['error'][ $i ] ) {
				$item['fichier_depose'] = sanitize_text_field( $_FILES['illustrations']['name'][ $i ] );
			}
			// La figure envoyée à part : son lien, sa date d'expiration et le
			// nom du fichier dans l'envoi la suivent partout.
			if ( isset( $this->figures_a_part[ $i ] ) ) {
				$item['a_part'] = $this->figures_a_part[ $i ];
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
	 * Les textes d'une figure, nettoyés comme au dépôt : titre, légende,
	 * crédits, texte alternatif et description détaillée. « $brut » porte
	 * ce que le formulaire a posté pour elle ; la correction en place s'en
	 * sert pour les figures qu'elle garde, qui doivent sortir comme les
	 * neuves.
	 */
	private function textes_de_figure( $brut, $rang ) {
		$item  = array();
		$suite = '';
		foreach ( array( 'titre', 'legende', 'credits', 'alt', 'description' ) as $clef ) {
			$valeur = isset( $brut[ $clef ] ) && is_string( $brut[ $clef ] ) ? $brut[ $clef ] : '';
			if ( 'alt' === $clef ) {
				// Le texte alternatif, que liront les personnes malvoyantes
				// ou non voyantes : une ligne, sans balise. Le même
				// nettoyage sert à la rédaction qui le corrige sur la fiche.
				// Le champ unique du formulaire porte à la suite la
				// description détaillée.
				list( $valeur, $suite ) = Notice_Archeomed_Controles::separer_l_ecoute( $valeur );
				$item['alt'] = $this->limit_string( Notice_Archeomed_Controles::texte_alternatif_propre( $valeur ),
					Notice_Archeomed_Controles::ALT_MAX, 'le texte alternatif de la fig. ' . $rang );
				continue;
			}
			if ( 'description' === $clef ) {
				// Un formulaire d'avant le champ unique l'envoyait à part.
				$valeur = '' !== trim( $valeur ) ? $valeur : $suite;
				// La description détaillée d'une figure complexe : plusieurs
				// paragraphes permis, sans balise, chaque ligne nettoyée.
				$item['description'] = $this->limit_string( Notice_Archeomed_Controles::description_propre( $valeur ),
					Notice_Archeomed_Controles::DESCRIPTION_MAX, 'la description détaillée de la fig. ' . $rang );
				continue;
			}
			$item[ $clef ] = $this->limit_string( sanitize_textarea_field( self::chevrons_a_garder( $valeur ) ),
				'legende' === $clef ? 1500 : 400,
				( 'legende' === $clef ? 'la légende' : ( 'titre' === $clef ? 'le titre' : 'les crédits' ) ) . ' de la fig. ' . $rang );
		}
		// « Fig. 1 : Plan » retapé dans le titre donnait « Fig. 1 Fig. 1 :
		// Plan » : le numéro, c'est le plugin qui le pose.
		$item['titre'] = Notice_Archeomed_Controles::titre_sans_numero( $item['titre'], $rang );
		// La mise en forme de l'auteur — titre, légende, description —,
		// accordée aux textes qu'on vient de garder : les trois clefs
		// toujours, vides quand rien ne s'accorde, pour qu'une forme d'avant
		// ne survive pas à son texte. Le texte alternatif n'en a jamais.
		return array_merge( $item, Notice_Archeomed_Forme::accorder_la_figure( $brut, $item, $rang ) );
	}

	/**
	 * La mise en forme du titre d'une figure qui change de rang, accordée au
	 * titre qui a pu perdre son « Fig. N » : sinon, elle s'oublie.
	 */
	private static function titre_forme_au_rang( $item, $rang ) {
		if ( ! empty( $item['titre_forme'] ) && is_string( $item['titre_forme'] ) ) {
			$item['titre_forme'] = Notice_Archeomed_Forme::accorder( $item['titre_forme'],
				isset( $item['titre'] ) && is_scalar( $item['titre'] ) ? (string) $item['titre'] : '',
				array( 'rang' => (int) $rang, 'max' => Notice_Archeomed_Forme::BORNES['titre'] ) );
		}
		return $item;
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

	/**
	 * Le lieu-dit d'un titre en HTML, en italique si la revue le veut, avec
	 * la mise en forme de l'auteur quand elle dit encore le même texte.
	 */
	private static function lieu_dit_html( $d ) {
		return Notice_Archeomed_Lieu_Dit::html_du_titre( $d );
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

	/**
	 * Le nombre de figures réellement déposées : les fichiers joints au
	 * formulaire, et les figures envoyées à part.
	 */
	private function fichiers_deposes() {
		$n = count( $this->figures_a_part );
		if ( empty( $_FILES['illustrations']['error'] ) || ! is_array( $_FILES['illustrations']['error'] ) ) {
			return $n;
		}
		foreach ( $_FILES['illustrations']['error'] as $erreur ) {
			if ( UPLOAD_ERR_NO_FILE !== (int) $erreur ) {
				$n++;
			}
		}
		return $n;
	}

	/** Ce que pèsent les fichiers postés, tous champs confondus. */
	private static function poids_des_fichiers_postes() {
		$total = 0;
		foreach ( $_FILES as $champ ) {
			if ( ! is_array( $champ ) || ! isset( $champ['size'], $champ['error'] ) ) {
				continue;
			}
			$tailles  = (array) $champ['size'];
			$erreurs  = (array) $champ['error'];
			foreach ( $tailles as $i => $octets ) {
				if ( is_scalar( $octets ) && isset( $erreurs[ $i ] ) && is_scalar( $erreurs[ $i ] )
					&& UPLOAD_ERR_OK === (int) $erreurs[ $i ] ) {
					$total += max( 0, (int) $octets );
				}
			}
		}
		return $total;
	}

	/**
	 * Reçoit la notice sans ses fichiers quand le disque ne peut plus les
	 * prendre.
	 *
	 * Le quota du site est de 2 Go, et un disque plein ne refuserait pas
	 * seulement les notices : WordPress cesserait d'écrire. Le plafond
	 * d'envois, lui, ne tenait pas le disque — cent dépôts de vingt Mo
	 * l'emplissaient en une heure. Au-delà de 85 % du plafond, ou quand
	 * l'heure a déjà reçu plus qu'une campagne n'envoie, la notice entre
	 * quand même : chaque figure devient « envoyée à part », son fichier
	 * témoin le dit à la rédaction, et la page de fin à l'auteur. Refuser
	 * la notice, c'était la perdre.
	 *
	 * La part du disque s'estime sans le parcourir : le parcours prend
	 * jusqu'à cinq secondes, que chaque dépôt ferait attendre.
	 */
	private function garder_le_disque() {
		$poids = self::poids_des_fichiers_postes();
		if ( $poids <= 0 ) {
			return;
		}
		$part    = Notice_Archeomed_Settings::part_du_disque( $poids );
		$plein   = null !== $part && $part >= Notice_Archeomed_Settings::PART_SANS_FICHIERS;
		$deborde = self::lire_le_compteur( 'na_poids_global' ) + $poids
			> (int) apply_filters( 'notice_archeomed_poids_par_heure', self::MAX_POIDS_PAR_HEURE );
		if ( ! $plein && ! $deborde ) {
			// Compté dès maintenant, avant d'être écrit : deux dépôts
			// simultanés ne se croient pas chacun seuls sur le disque.
			self::ajouter_au_compteur( 'na_poids_global', $poids );
			Notice_Archeomed_Settings::noter_le_poids_recu( $poids );
			return;
		}
		error_log( 'Notice Archeomed: ' . ( $plein ? 'disque presque plein' : 'poids de l\'heure dépassé' ) . ', notice reçue sans ses fichiers.' );
		$this->fichiers_refuses = true;
		$figures = isset( $_FILES['illustrations'] ) && is_array( $_FILES['illustrations'] ) ? $_FILES['illustrations'] : array();
		if ( isset( $figures['name'], $figures['error'] ) && is_array( $figures['name'] ) && is_array( $figures['error'] ) ) {
			foreach ( $figures['name'] as $i => $nom ) {
				if ( isset( $this->figures_a_part[ $i ] ) || ! isset( $figures['error'][ $i ] )
					|| UPLOAD_ERR_NO_FILE === (int) $figures['error'][ $i ] ) {
					continue;
				}
				$this->figures_a_part[ $i ] = array(
					'lien'    => '',
					'jusqua'  => '',
					'fichier' => is_string( $nom ) ? sanitize_text_field( $nom ) : '',
					'refusee' => 'place',
				);
			}
		}
		// Tous les fichiers s'effacent : figures, autorisations, conversions,
		// remplaçants. Leur nom reste, pour la saisie gardée.
		foreach ( array_keys( $_FILES ) as $cle ) {
			$champ = $_FILES[ $cle ];
			if ( ! is_array( $champ ) || ! isset( $champ['error'] ) ) {
				continue;
			}
			$vide = array( 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0 );
			if ( ! is_array( $champ['error'] ) ) {
				$_FILES[ $cle ] = array( 'name' => isset( $champ['name'] ) && is_string( $champ['name'] ) ? $champ['name'] : '' ) + $vide;
				continue;
			}
			$rangs = array_keys( $champ['error'] );
			$range = array( 'name' => array() );
			foreach ( $rangs as $i ) {
				$range['name'][ $i ] = isset( $champ['name'][ $i ] ) && is_string( $champ['name'][ $i ] ) ? $champ['name'][ $i ] : '';
			}
			foreach ( $vide as $k => $v ) {
				$range[ $k ] = array_fill_keys( $rangs, $v );
			}
			$_FILES[ $cle ] = $range;
		}
	}

	/**
	 * Range les figures envoyées à part parmi les fichiers déposés.
	 *
	 * Une figure trop lourde pour le formulaire part par un service de
	 * transfert : sa carte n'a pas de fichier. Le champ « illustrations[] »
	 * ne porte que les fichiers, quand tout le reste — légendes,
	 * autorisations, conversions, le Word, le courriel, le paquet — tient
	 * que la figure de rang N a le N-ième fichier. On insère donc, à la
	 * place de chaque figure à part, une entrée vide : le rang ne bouge pas,
	 * et la réception y pose un fichier témoin.
	 *
	 * « illus_source[] » dit, carte par carte, « fichier » ou « a_part ». Un
	 * formulaire d'avant ce champ ne l'envoie pas : rien ne change.
	 */
	private function ranger_les_figures_a_part() {
		$this->figures_a_part = array();
		if ( ! isset( $_POST['illus_source'] ) || ! is_array( $_POST['illus_source'] ) ) {
			return;
		}
		$sources = array_slice( array_values( wp_unslash( $_POST['illus_source'] ) ), 0, self::MAX_FILES + 1 );
		if ( ! in_array( 'a_part', $sources, true ) ) {
			return;
		}
		$liens   = isset( $_POST['illus_lien'] ) && is_array( $_POST['illus_lien'] ) ? array_values( wp_unslash( $_POST['illus_lien'] ) ) : array();
		$dates   = isset( $_POST['illus_jusqua'] ) && is_array( $_POST['illus_jusqua'] ) ? array_values( wp_unslash( $_POST['illus_jusqua'] ) ) : array();
		$noms    = isset( $_POST['illus_nom_envoi'] ) && is_array( $_POST['illus_nom_envoi'] ) ? array_values( wp_unslash( $_POST['illus_nom_envoi'] ) ) : array();
		$champs  = array( 'name', 'type', 'tmp_name', 'error', 'size' );
		$fichiers = isset( $_FILES['illustrations'] ) && is_array( $_FILES['illustrations'] ) ? $_FILES['illustrations'] : array();
		// Posté sans les crochets, le champ n'est pas un tableau : la
		// réception le refusera, on n'y touche pas.
		if ( isset( $fichiers['name'] ) && ! is_array( $fichiers['name'] ) ) {
			return;
		}
		$reels = array();
		foreach ( isset( $fichiers['error'] ) ? (array) $fichiers['error'] : array() as $i => $erreur ) {
			if ( UPLOAD_ERR_NO_FILE !== (int) $erreur ) {
				$reels[] = $i;
			}
		}
		$vide    = array( 'name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0 );
		$range   = array_fill_keys( $champs, array() );
		$ajouter = function ( $i ) use ( &$range, $champs, $fichiers, $vide ) {
			foreach ( $champs as $champ ) {
				$range[ $champ ][] = null === $i ? $vide[ $champ ]
					: ( isset( $fichiers[ $champ ][ $i ] ) ? $fichiers[ $champ ][ $i ] : $vide[ $champ ] );
			}
		};
		$suivant = 0;
		foreach ( $sources as $place => $source ) {
			if ( 'a_part' === $source ) {
				$texte = function ( $liste, $max ) use ( $place ) {
					return isset( $liste[ $place ] ) && is_string( $liste[ $place ] )
						? $this->limit_string( trim( $liste[ $place ] ), $max ) : '';
				};
				$lien  = esc_url_raw( $texte( $liens, 500 ), array( 'http', 'https' ) );
				$date  = $texte( $dates, 10 );
				$this->figures_a_part[ $place ] = array(
					'lien'    => $lien,
					'jusqua'  => self::date_valide( $date ) ? $date : '',
					'fichier' => sanitize_text_field( $texte( $noms, 255 ) ),
				);
				$ajouter( null );
				continue;
			}
			$ajouter( isset( $reels[ $suivant ] ) ? $reels[ $suivant ] : null );
			++$suivant;
		}
		// Plus de fichiers que de cartes : ils suivent, et la réception
		// jugera du nombre.
		for ( ; isset( $reels[ $suivant ] ); ++$suivant ) {
			$ajouter( $reels[ $suivant ] );
		}
		$_FILES['illustrations'] = $range;
	}

	/**
	 * Le fichier témoin d'une figure envoyée à part : il tient sa place
	 * parmi les fichiers de la notice. On le reconnaît à son nom, qu'il
	 * garde de la réception au dépôt (« notice-archeomed-a-part-… »,
	 * « notice-archeomed-garde-a-part-… », « 12-notice-archeomed-garde-a-part-… »).
	 */
	public static function est_a_part( $chemin ) {
		return is_string( $chemin ) && (bool) preg_match( '/-a-part-[A-Za-z0-9]{8,}\.txt$/', basename( $chemin ) );
	}

	/** Ce que dit le fichier témoin, pour qui l'ouvrirait. */
	private static function texte_du_temoin( $a_part ) {
		$lignes = ! empty( $a_part['refusee'] )
			? array(
				'Figure jointe par l’auteur, mais non reçue : le disque du site était presque plein.',
				'L’auteur a été invité à l’envoyer à la rédaction, avec son autorisation de reproduction s’il y en a une. Ce fichier ne tient que sa place.',
			)
			: array(
				'Figure envoyée à part par l’auteur, faute de pouvoir passer par le formulaire.',
				'Ce fichier ne tient que sa place : l’original est à télécharger, puis à ranger avec la notice.',
			);
		if ( ! empty( $a_part['lien'] ) ) {
			$lignes[] = 'Lien : ' . $a_part['lien'];
		}
		if ( ! empty( $a_part['jusqua'] ) ) {
			$lignes[] = 'Valable jusqu’au : ' . self::date_en_lettres( $a_part['jusqua'] );
		}
		if ( ! empty( $a_part['fichier'] ) ) {
			$lignes[] = 'Nom du fichier dans l’envoi : ' . $a_part['fichier'];
		}
		return implode( "\n", $lignes ) . "\n";
	}

	/**
	 * La figure envoyée à part d'une ligne de figure, si c'en est une : son
	 * lien, sa date et le nom du fichier, ou null.
	 */
	public static function figure_a_part( $item ) {
		return is_array( $item ) && ! empty( $item['a_part'] ) && is_array( $item['a_part'] ) ? $item['a_part'] : null;
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
		$ordre     = array( 'commune', 'departement', 'lieu_dit', 'rubrique_principale', 'nature', 'annee', 'organisme', 'id_patriarche',
			'resp_prenom', 'resp_nom', 'resp_email', 'resp_inst', 'texte' );
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
			// La matière, que seule la rubrique V demande, suit la rubrique.
			if ( 'rubrique_principale' === $champ && isset( $d['rubrique_principale'] )
				&& self::RUBRIQUE_ARTISANAT === $d['rubrique_principale'] && '' === self::matiere_de( $d ) ) {
				$manquants[] = 'rubrique_matiere';
			}
		}
		// Les figures déjà déposées d'une notice corrigée en place viennent
		// les premières, comme sur la page : mêmes exigences que les
		// nouvelles, sauf pour celle qu'on retire. Un champ que le formulaire
		// n'a pas envoyé garde ce que la notice en disait.
		$contexte = $this->contexte_poste();
		if ( null !== $contexte ) {
			foreach ( $this->figures_gardees_de( $contexte['id'], get_post_meta( $contexte['id'], '_na_donnees', true ) ) as $figure ) {
				$k      = (int) $figure['k'];
				$action = $this->action_de_la_garde( $k );
				// « Remplacer » sans fichier passait pour « garder », sans un
				// mot : l'auteur croyait sa figure changée.
				// Le disque plein a effacé le fichier : la figure reste comme
				// elle était, et la page de fin demande de l'envoyer à part.
				if ( 'remplacer' === $action && ! $this->fichier_poste( 'garde_fichier_' . $k ) && ! $this->fichiers_refuses ) {
					$manquants[] = 'garde_fichier_' . $k;
				}
				if ( 'retirer' === $action
					|| ( ! $figure['a_fichier'] && ! ( 'remplacer' === $action && $this->fichier_poste( 'garde_fichier_' . $k ) ) ) ) {
					continue;
				}
				foreach ( array( 'titre', 'credits', 'type', 'alt' ) as $clef ) {
					$valeur = $this->garde_postee( $clef, $k );
					$valeur = null !== $valeur ? $valeur : (string) $figure[ $clef ];
					if ( 'alt' === $clef ) {
						list( $valeur ) = Notice_Archeomed_Controles::separer_l_ecoute( $valeur );
					} elseif ( 'type' === $clef ) {
						$valeur = Notice_Archeomed_Controles::type_de_figure( $valeur );
					}
					if ( '' === trim( (string) $valeur ) ) {
						$manquants[] = 'garde_' . $clef . '_' . $k;
					}
				}
				// Gardée telle quelle, une figure envoyée à part garde aussi
				// l'obligation d'un lien et d'une date.
				if ( ! empty( $figure['a_part'] ) && 'remplacer' !== $action ) {
					$a_part = $this->a_part_de_la_garde( $k, $figure['a_part'] );
					if ( '' === $a_part['lien'] ) {
						$manquants[] = 'garde_lien_' . $k;
					}
					if ( '' === $a_part['jusqua'] ) {
						$manquants[] = 'garde_jusqua_' . $k;
					}
				}
			}
		}
		// Le titre, les crédits, la nature et le texte alternatif de chaque
		// figure déposée, dans l'ordre de la carte : le titre et les crédits
		// s'impriment avec elle, la nature et le texte alternatif servent les
		// lecteurs malvoyants et non voyants. Une figure sans fichier — une notice
		// ancienne, une correction qui ne redépose rien — n'en demande pas.
		$deposes = $this->fichiers_deposes();
		foreach ( isset( $d['illustrations'] ) ? (array) $d['illustrations'] : array() as $item ) {
			$rang = is_array( $item ) && isset( $item['rang'] ) ? (int) $item['rang'] : 0;
			if ( $rang < 1 || $rang > min( $deposes, self::MAX_FILES ) ) {
				continue;
			}
			foreach ( array( 'titre', 'credits', 'type', 'alt' ) as $clef ) {
				if ( 'type' === $clef && ! empty( $item['type_absent'] ) ) {
					continue;
				}
				if ( '' === trim( isset( $item[ $clef ] ) ? (string) $item[ $clef ] : '' ) ) {
					$manquants[] = 'illus_' . $clef . '_' . $rang;
				}
			}
			// Une figure envoyée à part sans son lien, ou sans la date où il
			// s'éteint : la rédaction ne pourrait pas la télécharger.
			$a_part = self::figure_a_part( $item );
			if ( null !== $a_part ) {
				if ( empty( $a_part['lien'] ) ) {
					$manquants[] = 'illus_lien_' . $rang;
				}
				if ( empty( $a_part['jusqua'] ) ) {
					$manquants[] = 'illus_jusqua_' . $rang;
				}
			}
		}
		// Un lien vers les originaux sans sa date d'expiration : la
		// rédaction ne saurait pas jusqu'à quand le télécharger.
		if ( ! empty( $d['originaux_lien'] ) && empty( $d['originaux_jusqua'] ) ) {
			$manquants[] = 'originaux_jusqua';
		}
		// Une période et un sujet au moins ; un terme libre compte.
		foreach ( array( 'pactols_periods', 'pactols_subjects' ) as $liste ) {
			if ( empty( $d[ $liste . '_items' ] ) ) {
				$manquants[] = $liste;
			}
		}
		return $manquants;
	}

	/** Une date « AAAA-MM-JJ » qui existe au calendrier. */
	private static function date_valide( $date ) {
		return is_string( $date ) && preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m )
			&& checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/** « 2026-10-24 » devient « 24 octobre 2026 », dans la langue du site. */
	public static function date_en_lettres( $date ) {
		if ( ! self::date_valide( $date ) ) {
			return (string) $date;
		}
		$jour = new DateTimeImmutable( $date . ' 12:00:00', wp_timezone() );
		return wp_date( 'j F Y', $jour->getTimestamp() );
	}

	/**
	 * Combien de jours restent avant la date dite, ce jour compris :
	 * 0 le jour même, négatif une fois passée.
	 */
	private static function jours_avant( $date ) {
		$aujourdhui = new DateTimeImmutable( 'today', wp_timezone() );
		$fin        = new DateTimeImmutable( $date, wp_timezone() );
		return (int) $aujourdhui->diff( $fin )->format( '%r%a' );
	}

	/**
	 * L'alerte des originaux : un courriel à part, à la rédaction, quand
	 * l'auteur a déposé ses originaux sur un service de transfert. Le lien
	 * se perdait dans la notice, et le service effaçait les fichiers avant
	 * qu'on les ait téléchargés. L'objet dit tout, en capitales, pour être
	 * vu dans une boîte pleine.
	 *
	 * Un échec ne compte pas comme un échec de la notice, qui est partie :
	 * il est consigné, et le lien reste dans la notice et le Word.
	 */
	private function alerter_pour_les_originaux( $d, $destinataires, $entetes, $titre, $reference ) {
		$transferts = self::transferts_de( $d );
		if ( empty( $transferts ) || empty( $destinataires ) ) {
			return;
		}
		// L'objet dit la plus proche des dates : c'est elle qui presse.
		$date = '';
		foreach ( $transferts as $t ) {
			if ( '' !== $t['jusqua'] && ( '' === $date || $t['jusqua'] < $date ) ) {
				$date = $t['jusqua'];
			}
		}
		$quand = '';
		if ( self::date_valide( $date ) ) {
			$jours  = self::jours_avant( $date );
			$lettre = self::date_en_lettres( $date );
			if ( $jours > 1 ) {
				$quand = ' DANS ' . $jours . ' JOURS, JUSQU’AU ' . $lettre;
			} elseif ( 1 === $jours ) {
				$quand = ' AVANT DEMAIN SOIR, ' . $lettre;
			} elseif ( 0 === $jours ) {
				$quand = ' AUJOURD’HUI, ' . $lettre;
			} else {
				$quand = ' — LIEN PEUT-ÊTRE EXPIRÉ DEPUIS LE ' . $lettre;
			}
			$quand = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $quand, 'UTF-8' ) : strtoupper( $quand );
		}
		$pluriel = count( $transferts ) > 1;
		$objet   = str_replace( array( "\r", "\n" ), ' ', 'ATTENTION — ' . ( $pluriel ? 'LIENS' : 'LIEN' ) . ' À TÉLÉCHARGER' . $quand . ' — ' . $titre
			. ( '' !== $reference ? ' — réf. ' . $reference : '' ) );
		$liste = '';
		foreach ( $transferts as $t ) {
			$liste .= '<li style="margin:0 0 8px;">'
				. ( ! empty( $t['figures'] ) ? esc_html( implode( ', ', $t['figures'] ) ) . ' : ' : '' )
				. '<a href="' . esc_url( $t['lien'] ) . '">' . esc_html( $t['lien'] ) . '</a>'
				. esc_html( '' !== $t['jusqua']
					? ' — avant le ' . self::date_en_lettres( $t['jusqua'] ) . ', date à laquelle le service de transfert les efface'
					: ' — avant que le service de transfert ne les efface' )
				. '</li>';
		}
		$corps = '<html lang="fr"><body><div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#222;">'
			. '<div style="background:#fcefed;border:2px solid #b3261e;padding:14px 18px;margin:0 0 18px;">'
			. '<p style="margin:0 0 8px;font-size:17px;"><strong>Originaux à télécharger</strong></p>'
			. '<p style="margin:0 0 8px;">L’auteur de la notice « ' . esc_html( $titre ) . ' » a envoyé des figures par un service de transfert. '
			. 'Téléchargez-les à temps, puis rangez-les avec la notice.</p>'
			. '<ul style="margin:0;padding-left:20px;">' . $liste . '</ul>'
			. '</div>'
			. ( ! empty( $d['resp_email'] ) && is_email( $d['resp_email'] )
				? '<p>Si un lien a expiré, il faut redemander les fichiers à l’auteur&nbsp;: ' . esc_html( $d['resp_email'] ) . '.</p>' : '' )
			. '</div></body></html>';
		if ( ! self::courriel( $destinataires, $objet, $corps, $entetes ) ) {
			error_log( 'Notice Archeomed: alerte des originaux non envoyée (' . $titre . ').' );
		}
	}

	/**
	 * Les envois par un service de transfert d'une notice : un par lien,
	 * avec la plus proche de ses dates et les figures qu'il porte (« Fig. 2
	 * (« coupe.tif ») »). Plusieurs figures d'un même transfert ne font
	 * qu'un lien à télécharger. Le lien unique des originaux, qu'avaient
	 * les notices d'avant les figures envoyées à part, vient en dernier,
	 * sans figure nommée.
	 */
	private static function transferts_de( $d ) {
		$liens = array();
		$items = is_array( $d ) && isset( $d['illustrations'] ) ? (array) $d['illustrations'] : array();
		foreach ( $items as $item ) {
			$a_part = self::figure_a_part( $item );
			if ( null === $a_part || empty( $a_part['lien'] ) ) {
				continue;
			}
			$cle = (string) $a_part['lien'];
			if ( ! isset( $liens[ $cle ] ) ) {
				$liens[ $cle ] = array( 'lien' => $cle, 'jusqua' => '', 'figures' => array() );
			}
			$jusqua = isset( $a_part['jusqua'] ) ? (string) $a_part['jusqua'] : '';
			if ( self::date_valide( $jusqua ) && ( '' === $liens[ $cle ]['jusqua'] || $jusqua < $liens[ $cle ]['jusqua'] ) ) {
				$liens[ $cle ]['jusqua'] = $jusqua;
			}
			$rang = isset( $item['rang'] ) ? (int) $item['rang'] : 0;
			$liens[ $cle ]['figures'][] = ( $rang > 0 ? Notice_Archeomed_Normes::numero_de_figure( $rang ) : 'Figure' )
				. ( ! empty( $a_part['fichier'] ) ? ' (« ' . $a_part['fichier'] . ' »)' : '' );
		}
		if ( is_array( $d ) && ! empty( $d['originaux_lien'] ) && ! isset( $liens[ (string) $d['originaux_lien'] ] ) ) {
			$jusqua = isset( $d['originaux_jusqua'] ) ? (string) $d['originaux_jusqua'] : '';
			$liens[ (string) $d['originaux_lien'] ] = array(
				'lien'    => (string) $d['originaux_lien'],
				'jusqua'  => self::date_valide( $jusqua ) ? $jusqua : '',
				'figures' => array(),
			);
		}
		return array_values( $liens );
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
			} elseif ( self::hors_pactols( $item ) ) {
				$line .= esc_html( " — pris hors de la liste de Pactols, non retenu" );
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
				$parts[] = self::figure_en_html( $item, 'titre', $item['titre'], '', false );
			}
			// La marque où le courriel de la rédaction pose l'aperçu de la
			// figure, au-dessus de sa légende ; ailleurs, elle ne se voit pas.
			$lignes[] = '<!--na-figure-' . (int) $item['rang'] . '-->' . implode( ' — ', $parts );
			if ( '' !== $item['legende'] ) {
				$lignes[] = self::figure_en_html( $item, 'legende', $item['legende'] );
			}
			if ( '' !== $item['credits'] ) {
				$lignes[] = '<em>' . esc_html( $item['credits'] ) . '</em>';
			}
			$nature = Notice_Archeomed_Controles::type_de_figure( isset( $item['type'] ) ? $item['type'] : '' );
			if ( '' !== $nature ) {
				$lignes[] = esc_html( "Nature\u{00A0}: " . Notice_Archeomed_Controles::TYPES_DE_FIGURE[ $nature ] );
			}
			if ( ! empty( $item['alt'] ) ) {
				$lignes[] = esc_html( "Texte alternatif\u{00A0}: " . Notice_Archeomed_Controles::une_ligne( $item['alt'] ) );
			}
			if ( ! empty( $item['description'] ) && is_scalar( $item['description'] ) ) {
				$lignes[] = esc_html( "Description détaillée\u{00A0}: " ) . implode( '<br>', self::description_en_html( $item ) );
			}
		}
		return implode( '<br>', $lignes );
	}

	private function build_notice( $d ) {
		$departement = $this->sans_parentheses( $d['departement'] );

		// Police sans-serif type Arial pour tout le mail.
		$font = 'font-family:Arial,Helvetica,sans-serif;';
		$style_block = $font . 'font-size:15px;line-height:1.5;margin:0 0 14px;';
		$style_txt   = $font . 'font-size:15px;line-height:1.6;margin:0 0 14px;';

		// --- Bloc d'en-tête (rubriques), groupé ---
		$entete_lines = array();
		$entete_lines[] = $this->meta_line( 'Rubrique principale', $d['rubrique_principale'] );
		if ( '' !== self::matiere_de( $d ) ) {
			$entete_lines[] = $this->meta_line( 'Sous-rubrique', self::matiere_de( $d ) . ' – ' . self::MATIERES[ self::matiere_de( $d ) ] );
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
		// Les figures envoyées à part, lien par lien, et le lien unique des
		// originaux des notices plus anciennes.
		foreach ( self::transferts_de( $d ) as $t ) {
			$admin_lines[] = $this->meta_line(
				( empty( $t['figures'] ) ? 'Originaux des figures' : 'Envoyé à part — ' . implode( ', ', $t['figures'] ) )
				. ' à télécharger'
				. ( '' !== $t['jusqua'] ? ' avant le ' . self::date_en_lettres( $t['jusqua'] ) : '' ),
				$t['lien'] );
		}
		if ( ! empty( $d['rapport_lien'] ) ) {
			$admin_lines[] = $this->meta_line( 'Rapport final', $d['rapport_lien'] );
		}
		if ( ! empty( $d['donnees_lien'] ) ) {
			$admin_lines[] = $this->meta_line( 'Jeu de données', $d['donnees_lien'] );
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
		if ( ! empty( $d['deposant_email'] ) && is_email( $d['deposant_email'] ) ) {
			$dn = isset( $d['deposant_prenom'], $d['deposant_nom'] ) ? trim( $d['deposant_prenom'] . ' ' . $d['deposant_nom'] ) : '';
			$contacts[] = 'Déposée par : ' . ( '' !== $dn ? esc_html( $dn ) . ' : ' : '' )
				. '<a href="mailto:' . esc_attr( $d['deposant_email'] ) . '">' . esc_html( $d['deposant_email'] ) . '</a>';
		}
		$contacts_block = '';
		if ( ! empty( $contacts ) ) {
			$contacts_block = '<strong>Coordonnées des responsables :</strong><br>' . implode( '<br>', $contacts );
		}

		// --- Bloc responsable (collé au point final du texte, entre parenthèses) ---
		// Composé une seule fois, pour la page et le courriel : les deux
		// copies se suivaient à la main, et l'une aurait fini sans son point.

		// --- Corps de la notice ---
		// Ligne 1 : Commune (département), lieu-dit (italique)
		// Ligne 2 : Nature de l'opération : <valeur> (titre en maigre)
		// Ligne 3+ : texte rédigé, terminé par " (responsable…)" collé au point final.
		// Les communes en capitales, comme à l'aperçu du titre dans le
		// formulaire et dans le volume.
		$loc_line = esc_html( mb_strtoupper( $this->lieux_en_ligne( $d ), 'UTF-8' ) . ' (' . $departement . ')' );
		if ( '' !== $d['lieu_dit'] ) {
			// Un point sépare, comme dans le document et dans le volume : la
			// copie de l'auteur et le fichier joint ne doivent pas se
			// contredire sur la ponctuation d'un titre.
			$loc_line .= esc_html( Notice_Archeomed_Normes::avant_le_lieu_dit() ) . self::lieu_dit_html( $d );
		}
		$nature_line = 'Nature de l\'opération : ' . esc_html( $d['nature'] );
		$bloc_resp = ' (' . esc_html( $this->responsables_inline( $d ) ) . ')';
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
		} elseif ( preg_match( '#</(?:ul|h3)>\s*$#', $texte ) ) {
			// Après une liste ou un intertitre final, le bloc prend son
			// paragraphe, comme au Word : il flottait sous la liste.
			$texte_html = $texte . '<p style="margin:0;">' . ltrim( $bloc_resp ) . '</p>';
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
		// L'intertitre T1 en simple gras, à la taille du texte : sans style,
		// les messageries le grossissaient comme un titre de page.
		$texte_html = preg_replace( '#<h3(\s[^>]*)?>#i', '<h3 style="font-size:15px;font-weight:bold;margin:14px 0 4px;">', $texte_html );
		// La liste, en retrait mesuré : sans style, chaque messagerie
		// choisissait le sien.
		$texte_html = preg_replace( '#<ul(\s[^>]*)?>#i', '<ul style="margin:0 0 14px;padding-left:24px;">', $texte_html );
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
		if ( ! Notice_Archeomed_Settings::peut_regler() ) {
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
	 * Le Word d'une notice, refait d'après sa saisie pour être rangé sur
	 * sDrive ; son chemin, ou une chaîne vide. Le rangement l'efface ensuite.
	 */
	public function document_a_ranger( $id ) {
		return $this->refaire_le_document( (int) $id );
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
		$avant      = count( self::illustrations_rangees( (int) $ids[0] ) );
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
	 * Le cadre rouge d'une figure envoyée à part, à la place de son image :
	 * télécharger l'original, le mettre ici. Le lien, la date où il s'éteint
	 * et le nom du fichier dans l'envoi, pour qu'on ne prenne pas la figure
	 * voisine d'un même transfert.
	 */
	private function poser_le_cadre_a_part( $doc, $a_part ) {
		$xml = $doc->plain( 'Figure envoyée à part : télécharger l’original et le placer ici, à la place de ce cadre.' );
		if ( ! empty( $a_part['fichier'] ) ) {
			$xml .= $doc->plain( ' Fichier « ' . $a_part['fichier'] . ' » dans l’envoi.' );
		}
		if ( ! empty( $a_part['lien'] ) ) {
			$xml .= $doc->plain( ' Lien'
				. ( ! empty( $a_part['jusqua'] ) ? ', valable jusqu’au ' . self::date_en_lettres( $a_part['jusqua'] ) : '' )
				. ' : ' ) . $doc->hyperlink( $a_part['lien'], $a_part['lien'] );
		}
		if ( method_exists( $doc, 'add_framed_paragraph' ) ) {
			$doc->add_framed_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER, $xml );
		} else {
			$doc->add_raw_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER, $xml );
		}
	}

	/**
	 * La description détaillée d'une figure, en fin de légende : c'est la
	 * seule place qui passe aujourd'hui à la publication, un seul texte
	 * alternatif suivant l'image. Elle s'ouvre sur « Description : », pour
	 * que le lecteur sache ce qu'il lit et que la mise en page la retrouve ;
	 * le bloc « figure_description » des styles en décide le style.
	 */
	private function poser_la_description( $doc, $item ) {
		$description = isset( $item['description'] ) && is_scalar( $item['description'] ) ? trim( (string) $item['description'] ) : '';
		if ( '' === $description ) {
			return;
		}
		// Les paragraphes sont ceux de « paragraphes_simples », chacun avec la
		// mise en forme de l'auteur quand elle dit encore le même texte.
		$forme = isset( $item['description_forme'] ) ? $item['description_forme'] : '';
		foreach ( Notice_Archeomed_Forme::paragraphes( $forme, $description ) as $i => $runs ) {
			$doc->add_paragraph( Notice_Archeomed_Styles::de( 'figure_description' ),
				Notice_Archeomed_Forme::precede( 0 === $i ? "Description\u{00A0}: " : '', $runs ) );
		}
	}

	/**
	 * Le texte alternatif d'une figure, à la fin de son bloc, juste avant le
	 * repère de fermeture.
	 *
	 * Le texte alternatif est toujours dans le texte de remplacement de
	 * l'image : c'est là que Métopes le lit et le convertit en « figDesc ».
	 * Il n'a son paragraphe que si la rédaction donne un style au bloc
	 * « figure_alttext » ; un document stylé pour Métopes perd à la
	 * conversion le paragraphe « TEI_figure_alttext », et Métopes n'affiche
	 * le sien, sous l'image, qu'à la relecture.
	 *
	 * C'est du texte simple, en romain : ni siècles en petites capitales ni
	 * exposant, que le lecteur d'écran lirait de travers ; les insécables
	 * seules s'appliquent.
	 */
	private function poser_le_texte_alternatif( $doc, $item, $alt ) {
		$style = Notice_Archeomed_Styles::de( 'figure_alttext' );
		if ( '' !== $alt && Notice_Archeomed_Styles::AUCUN !== $style ) {
			$doc->add_paragraph( $style, array( array( 'text' => $alt, 'sans_siecles' => true ) ) );
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
	 * Le titre ou la légende d'une figure en HTML d'affichage, précédé de
	 * « $tete ». Avec la mise en forme de l'auteur quand elle dit encore le
	 * même texte ; sinon tel qu'il s'affichait, échappé. Le titre prend la
	 * typographie, comme avant ; « $typo » à faux la lui laisse.
	 */
	private static function figure_en_html( $item, $champ, $texte, $tete = '', $typo = null ) {
		$typo    = null === $typo ? 'titre' === $champ : $typo;
		$options = array( 'max' => Notice_Archeomed_Forme::BORNES[ $champ ] );
		if ( 'titre' === $champ ) {
			$options['rang'] = isset( $item['rang'] ) ? (int) $item['rang'] : 0;
		}
		$forme = isset( $item[ $champ . '_forme' ] ) && is_string( $item[ $champ . '_forme' ] ) ? $item[ $champ . '_forme' ] : '';
		$runs  = '' === $forme ? array() : Notice_Archeomed_Forme::morceaux_de( $forme, $texte, $options );
		if ( ! Notice_Archeomed_Lieu_Dit::a_une_forme( $runs ) ) {
			return esc_html( $typo ? Notice_Archeomed_Normes::typographie( $tete . $texte ) : $tete . $texte );
		}
		return Notice_Archeomed_Forme::html( Notice_Archeomed_Forme::precede( $tete, $runs ), true, $typo );
	}

	/**
	 * Les paragraphes de la description détaillée d'une figure, en HTML
	 * d'affichage : ceux de « paragraphes_simples », avec la mise en forme
	 * de l'auteur.
	 */
	private static function description_en_html( $item ) {
		if ( ! isset( $item['description'] ) || ! is_scalar( $item['description'] ) || '' === (string) $item['description'] ) {
			return array();
		}
		$forme = isset( $item['description_forme'] ) && is_string( $item['description_forme'] ) ? $item['description_forme'] : '';
		$html  = array();
		foreach ( Notice_Archeomed_Forme::paragraphes( $forme, (string) $item['description'] ) as $runs ) {
			$html[] = Notice_Archeomed_Forme::html( $runs, true );
		}
		return $html;
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
				. $this->runs_xml( $doc, Notice_Archeomed_Lieu_Dit::morceaux_du_titre( $d ) );
		}
		$doc->add_raw_paragraph( Notice_Archeomed_Styles::de( 'titre_notice' ), $titre );
		// Une notice de la rubrique V reçue avant les matières se range sous
		// la sous-rubrique sans lettre : la rédaction lit qu'il faut la
		// classer.
		if ( isset( $d['rubrique_principale'] ) && self::RUBRIQUE_ARTISANAT === self::rubrique_actuelle( $d['rubrique_principale'] )
			&& '' === self::matiere_de( $d ) ) {
			$doc->add_paragraph( Notice_Archeomed_DOCX::STYLE_A_SUPPRIMER,
				array( array( 'text' => 'Sous-rubrique à choisir (A, B ou C)', 'b' => true ) ) );
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
		$this->poser_les_termes_hors_pactols( $doc, 'autres lieux',
			isset( $d['pactols_places_items'] ) ? $d['pactols_places_items'] : array(), true );

		$periodes = $this->termes_pactols_lies( $doc,
			isset( $d['pactols_periods_items'] ) ? $d['pactols_periods_items'] : array(),
			true );
		if ( ! empty( $periodes ) ) {
			$doc->add_raw_paragraph( Notice_Archeomed_Styles::de( 'periodes' ),
				$doc->plain( 'Période historique : ' )
					. implode( $doc->plain( ', ' ), $periodes ) );
		}
		$this->poser_les_termes_hors_pactols( $doc, 'période historique',
			isset( $d['pactols_periods_items'] ) ? $d['pactols_periods_items'] : array(), true );

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
		// Le jeu de données de l'opération, sous le rapport : c'est encore
		// une métadonnée, que la direction gardera ou non à l'impression.
		if ( ! empty( $d['donnees_lien'] ) ) {
			$doc->add_raw_paragraph(
				Notice_Archeomed_Styles::de( 'donnees' ),
				$doc->plain( 'Jeu de données : ' )
					. $doc->hyperlink( $d['donnees_lien'], $d['donnees_lien'] )
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
		$this->poser_les_termes_hors_pactols( $doc, 'mots-clés',
			isset( $d['pactols_subjects_items'] ) ? $d['pactols_subjects_items'] : array() );

		// 6. Texte de la notice, un paragraphe par <p>, avec le bloc responsable
		// collé au point final du dernier paragraphe.
		//
		// Ou, si la correspondance des styles le demande, dans un paragraphe
		// à lui, sans parenthèses : c'est la forme que la documentation
		// Métopes donne au paragraphe d'autorités.
		// Les paragraphes et les intertitres, chacun sous son style. Un
		// « h4 » d'une notice ancienne est un intertitre comme un autre : la
		// notice n'en a qu'un niveau.
		$blocs     = $doc->html_to_blocs( $d['texte_notice'] );
		$a_part    = Notice_Archeomed_Styles::responsabilites_a_part();
		$bloc_resp = $this->responsables_rtf( $doc, $d, ! $a_part );
		if ( ! $a_part ) {
			$last = count( $blocs ) - 1;
			// Collé au dernier paragraphe — jamais à un intertitre ni à un
			// élément de liste, qu'un texte finirait par hasard : il prend
			// alors son paragraphe.
			if ( $last < 0 || 0 !== $blocs[ $last ]['niveau'] || ! empty( $blocs[ $last ]['liste'] ) ) {
				// Sans l'espace qui le séparait du point final : en tête de
				// paragraphe, elle s'imprimait « ␣(Responsable… ».
				$blocs[] = array( 'niveau' => 0, 'runs' => $this->responsables_rtf( $doc, $d, true, false ) );
			} else {
				$blocs[ $last ]['runs'] .= $bloc_resp;
			}
		}
		foreach ( $blocs as $bloc ) {
			if ( ! empty( $bloc['liste'] ) ) {
				$doc->add_list_item( Notice_Archeomed_Styles::de( 'liste' ), $bloc['runs'] );
			} else {
				$doc->add_raw_paragraph( Notice_Archeomed_Styles::de( 0 === $bloc['niveau'] ? 'texte' : 'intertitre_1' ), $bloc['runs'] );
			}
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
			} elseif ( null !== self::figure_a_part( $item ) ) {
				// Une figure envoyée à part n'a pas d'image sur le site : un
				// cadre rouge tient sa place et dit où prendre l'original.
				// Il porte le style qui s'ôte avant l'import, comme les
				// autres renseignements de la rédaction.
				$this->poser_le_cadre_a_part( $doc, self::figure_a_part( $item ) );
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
			// L'italique, les petites capitales et les exposants que l'auteur
			// a posés au titre et à la légende les suivent, quand leur forme
			// dit encore le même texte.
			$titre_fig = Notice_Archeomed_Normes::apres_le_numero() . $item['titre'];
			$doc->add_raw_paragraph( Notice_Archeomed_Styles::de( 'figure_titre' ),
				$doc->char_run( Notice_Archeomed_Styles::de( 'figure_numero' ), $numero )
					. ( '' === $item['titre'] ? '' : ( method_exists( $doc, 'fragments' )
						? $doc->fragments( Notice_Archeomed_Forme::precede( Notice_Archeomed_Normes::apres_le_numero(),
							Notice_Archeomed_Forme::morceaux_de( isset( $item['titre_forme'] ) ? $item['titre_forme'] : '', $item['titre'],
								array( 'rang' => (int) $item['rang'], 'max' => Notice_Archeomed_Forme::BORNES['titre'] ) ) ) )
						: $doc->plain( $titre_fig ) ) ) );
			if ( '' !== $item['legende'] ) {
				$doc->add_paragraph( Notice_Archeomed_Styles::de( 'figure_legende' ),
					Notice_Archeomed_Forme::morceaux_de( isset( $item['legende_forme'] ) ? $item['legende_forme'] : '', $item['legende'],
						array( 'max' => Notice_Archeomed_Forme::BORNES['legende'] ) ) );
			}
			$this->poser_la_description( $doc, $item );
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
			$this->poser_le_texte_alternatif( $doc, $item, $alt );
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
				$doc->plain( 'Originaux des figures à télécharger'
					. ( ! empty( $d['originaux_jusqua'] ) ? ' avant le ' . self::date_en_lettres( $d['originaux_jusqua'] ) : '' ) . ' : ' )
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
	 * Plusieurs morceaux à la suite — le lieu-dit avec ses petites
	 * capitales et ses exposants. En DOCX, ils passent ensemble par la
	 * typographie : une apostrophe ou un siècle à cheval sur deux morceaux
	 * se reconnaît encore.
	 */
	private function runs_xml( $doc, $runs ) {
		$runs = array_values( array_filter( (array) $runs, function ( $run ) {
			return isset( $run['text'] ) && '' !== (string) $run['text'];
		} ) );
		if ( $doc instanceof Notice_Archeomed_DOCX ) {
			$propres = array();
			foreach ( $runs as $run ) {
				$propres[] = array(
					'text' => (string) $run['text'],
					'i'    => ! empty( $run['i'] ),
					'pc'   => ! empty( $run['pc'] ),
					'sup'  => ! empty( $run['sup'] ),
				);
			}
			return $doc->fragments( $propres );
		}
		$rtf = '';
		foreach ( $runs as $run ) {
			$open = ( ! empty( $run['i'] ) ? '\\i ' : '' ) . ( ! empty( $run['pc'] ) ? '\\scaps ' : '' ) . ( ! empty( $run['sup'] ) ? '\\super ' : '' );
			$text = Notice_Archeomed_RTF::esc( $run['text'] );
			$rtf .= '' === $open ? $text : '{' . $open . $text . '}';
		}
		return $rtf;
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
	private function responsables_rtf( $doc, $d, $entre_parentheses = true, $apres_le_texte = true ) {
		$segments = array();
		// Le dernier mot écrit : c'est lui qui dit si la mention a déjà son
		// point final — « Univ. de Caen, etc. » n'en prend pas un second.
		$fin = '';

		$resp = self::nom_d_autorite( $d['resp_prenom'], $d['resp_nom'] );
		if ( '' !== $resp ) {
			$seg = $doc->plain( "Responsable de l'opération : " )
				. $doc->char_run( Notice_Archeomed_Styles::de( 'responsable' ), $resp, true );
			$fin = $resp;
			if ( '' !== $d['resp_inst'] ) {
				$seg .= $doc->plain( ', ' )
					. $doc->char_run( Notice_Archeomed_Styles::de( 'affiliation' ), $d['resp_inst'] );
				$fin  = $d['resp_inst'];
			}
			$segments[] = $seg;
		}

		$coresp = self::nom_d_autorite( $d['coresp_prenom'], $d['coresp_nom'] );
		if ( '' !== $coresp ) {
			$seg = $doc->plain( "co-responsable de l'opération : " )
				. $doc->char_run( Notice_Archeomed_Styles::de( 'coresponsable' ), $coresp, true );
			$fin = $coresp;
			if ( '' !== $d['coresp_inst'] ) {
				$seg .= $doc->plain( ', ' )
					. $doc->char_run( Notice_Archeomed_Styles::de( 'affiliation' ), $d['coresp_inst'] );
				$fin  = $d['coresp_inst'];
			}
			$segments[] = $seg;
		}

		$coauteur = self::nom_d_autorite( $d['coauteur_prenom'], $d['coauteur_nom'] );
		if ( '' !== $coauteur ) {
			$seg = $doc->plain( 'notice rédigée avec : ' )
				. $doc->char_run( Notice_Archeomed_Styles::de( 'coauteur' ), $coauteur, true );
			$fin = $coauteur;
			if ( '' !== $d['coauteur_inst'] ) {
				$seg .= $doc->plain( ', ' )
					. $doc->char_run( Notice_Archeomed_Styles::de( 'affiliation' ), $d['coauteur_inst'] );
				$fin  = $d['coauteur_inst'];
			}
			$segments[] = $seg;
		}

		if ( empty( $segments ) ) {
			return '';
		}
		// Le point final de la notice, dans la parenthèse : « (Responsable
		// de l'opération : …, Université Sorbonne.) ». Il est hors du style
		// d'affiliation, comme la virgule qui la précède.
		$point = self::point_final( $fin );
		if ( ! $entre_parentheses ) {
			return implode( $doc->plain( ' ; ' ), $segments ) . ( '' !== $point ? $doc->plain( $point ) : '' );
		}
		return $doc->plain( $apres_le_texte ? ' (' : '(' )
			. implode( $doc->plain( ' ; ' ), $segments )
			. $doc->plain( $point . ')' );
	}

	/**
	 * Le point qui clôt la mention de responsabilité, ou rien si elle finit
	 * déjà sur un point, une abréviation, un « ? », un « ! » ou des points
	 * de suspension — guillemets fermants compris. Une mention vide n'en
	 * prend pas.
	 */
	private static function point_final( $fin ) {
		$fin = (string) preg_replace( '/^[\s\x{00A0}\x{202F}]+|[\s\x{00A0}\x{202F}]+$/u', '', (string) $fin );
		if ( '' === $fin || preg_match( '/[.?!…][\s\x{00A0}\x{202F}»”’"\']*$/u', $fin ) ) {
			return '';
		}
		return '.';
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
		$segments = array();
		$fin      = '';
		$roles    = array(
			'resp'     => "Responsable de l'opération : ",
			'coresp'   => "co-responsable de l'opération : ",
			'coauteur' => 'notice rédigée avec : ',
		);
		$lu = function ( $cle ) use ( $d ) {
			return isset( $d[ $cle ] ) && is_scalar( $d[ $cle ] ) ? trim( (string) $d[ $cle ] ) : '';
		};
		foreach ( $roles as $role => $libelle ) {
			$nom = trim( $lu( $role . '_prenom' ) . ' ' . $lu( $role . '_nom' ) );
			// Le responsable est toujours nommé, même vide : la mention dit
			// alors ce qui manque au lieu de commencer par un co-responsable.
			if ( '' === $nom && 'resp' !== $role ) {
				continue;
			}
			$seg = $libelle . $nom;
			$fin = $nom;
			// Une institution absente ne laisse plus une virgule pendante.
			if ( '' !== $lu( $role . '_inst' ) ) {
				$seg .= ', ' . $lu( $role . '_inst' );
				$fin  = $lu( $role . '_inst' );
			}
			$segments[] = $seg;
		}
		return implode( ' ; ', $segments ) . self::point_final( $fin );
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
		$this->oublier_la_page();
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
			'resp_prenom', 'resp_nom', 'resp_inst', 'resp_email', 'deposant_prenom', 'deposant_nom', 'deposant_email',
			'coresp_prenom', 'coresp_nom', 'coresp_inst', 'coresp_email',
			'coauteur_prenom', 'coauteur_nom', 'coauteur_inst', 'coauteur_email',
			'rapport_lien', 'donnees_lien', 'originaux_lien', 'originaux_jusqua',
		);
		$simples = array_merge( $une_ligne, array(
			'commentaires', 'texte_notice', 'pactols_periods', 'pactols_subjects', 'pactols_places',
			'rubrique_principale', 'rubrique_matiere', 'renvoi_1', 'renvoi_2', 'reprise_jeton',
			'notice_archeomed_nonce', 'na_website', 'na_curseur', 'na_preuve', 'cf-turnstile-response',
			'corrige_version', 'lieu_dit_forme',
		) );
		foreach ( $simples as $champ ) {
			if ( isset( $_POST[ $champ ] ) && ! is_string( $_POST[ $champ ] ) ) {
				unset( $_POST[ $champ ] );
			}
		}
		// Les listes. « commune » et « organisme » ont été des champs
		// simples : une chaîne s'y lit encore.
		//
		// Le texte pour l'écoute garde ses lignes : la première est le texte
		// alternatif, la suite la description détaillée. Plié sur une ligne,
		// il ne se séparait plus, et la description finissait coupée dans le
		// texte alternatif.
		$listes = array(
			'nature' => true, 'commune' => true, 'commune_ark' => true, 'organisme' => true,
			'illus_titre' => true, 'illus_credits' => true, 'illus_alt' => false, 'illus_legende' => false, 'illus_description' => false, 'illus_type' => true,
			'illus_source' => true, 'illus_lien' => true, 'illus_jusqua' => true, 'illus_nom_envoi' => true,
			'garde_lien' => true, 'garde_jusqua' => true, 'garde_nom_envoi' => true,
			'garde_titre' => true, 'garde_credits' => true, 'garde_alt' => false, 'garde_legende' => false, 'garde_type' => true,
			'garde_action' => true, 'garde_autorisation_retirer' => true,
			// La mise en forme des textes d'une figure : du HTML court, qui
			// garde ses fins de ligne comme le texte qu'il double.
			'illus_titre_forme' => false, 'illus_legende_forme' => false, 'illus_description_forme' => false,
			'garde_titre_forme' => false, 'garde_legende_forme' => false, 'garde_description_forme' => false,
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
	 * Les champs de texte d'une ligne ou de quelques-unes, tels que le
	 * formulaire les poste : nettoyés, bornés, et ramenés à la forme de la
	 * revue. Rend « champ => valeur » pour les champs demandés.
	 *
	 * Le dépôt et la relecture reçoivent les mêmes champs : une seule
	 * réception, pour qu'une borne changée d'un côté le soit de l'autre.
	 */
	private function textes_postes( $champs ) {
		$noms_des_champs = array(
			'departement' => 'la précision géographique', 'lieu_dit' => 'le lieu-dit', 'annee' => 'l’année',
			'num_autorisation' => 'le numéro d’arrêté', 'id_patriarche' => 'le code Patriarche',
			'resp_prenom' => 'le prénom du responsable', 'resp_nom' => 'le nom du responsable', 'resp_inst' => 'l’institution du responsable',
			'coresp_prenom' => 'le prénom du co-responsable', 'coresp_nom' => 'le nom du co-responsable', 'coresp_inst' => 'l’institution du co-responsable',
			'coauteur_prenom' => 'le prénom du co-auteur', 'coauteur_nom' => 'le nom du co-auteur', 'coauteur_inst' => 'l’institution du co-auteur',
			'deposant_prenom' => 'votre prénom', 'deposant_nom' => 'votre nom',
			'commentaires' => 'le message à la rédaction',
		);
		$bornes = array( 'departement' => 120, 'lieu_dit' => 200, 'annee' => 20, 'num_autorisation' => 120, 'id_patriarche' => 120, 'resp_prenom' => 100, 'resp_nom' => 100, 'resp_inst' => 200, 'coresp_prenom' => 100, 'coresp_nom' => 100, 'coresp_inst' => 200, 'coauteur_prenom' => 100, 'coauteur_nom' => 100, 'coauteur_inst' => 200, 'deposant_prenom' => 100, 'deposant_nom' => 100, 'commentaires' => 3000 );
		$d      = array();
		foreach ( (array) $champs as $f ) {
			$d[ $f ] = isset( $_POST[ $f ] ) ? sanitize_textarea_field( self::chevrons_a_garder( wp_unslash( $_POST[ $f ] ) ) ) : '';
			if ( isset( $bornes[ $f ] ) ) {
				$d[ $f ] = $this->limit_string( $d[ $f ], $bornes[ $f ], $noms_des_champs[ $f ] );
			}
		}
		if ( isset( $d['departement'] ) ) {
			$d['departement'] = $this->sans_parentheses( $d['departement'] );
		}
		if ( isset( $d['annee'] ) ) {
			$d['annee'] = self::annee_normalisee( $d['annee'] );
		}
		if ( isset( $d['lieu_dit'] ) ) {
			// Un titre ne prend pas de point final : « Rue de Reviers. » donnait
			// un titre pointé, au document comme au renvoi.
			$d['lieu_dit'] = Notice_Archeomed_Controles::sans_point_final( $d['lieu_dit'] );
			// La première lettre en capitale : le lieu-dit ouvre une phrase du
			// titre, après le point — « Caen (Calvados). La Croix-Blanche ».
			$d['lieu_dit'] = $this->capitale_initiale( $d['lieu_dit'] );
			// Sa mise en forme, quand le formulaire la poste : elle reçoit les
			// mêmes corrections, et ne se garde que si elle dit le même texte.
			if ( isset( $_POST['lieu_dit_forme'] ) && is_string( $_POST['lieu_dit_forme'] ) ) {
				$d['lieu_dit_forme'] = Notice_Archeomed_Lieu_Dit::accorder( wp_unslash( $_POST['lieu_dit_forme'] ), $d['lieu_dit'] );
			}
		}
		return $d;
	}

	/**
	 * Un lien posté, restreint à « http » et « https » et borné : il finit en
	 * lien hypertexte dans le document, où une adresse mal formée ne mènerait
	 * nulle part.
	 */
	private function lien_poste( $champ, $nom ) {
		$lien = isset( $_POST[ $champ ] ) && is_string( $_POST[ $champ ] )
			? esc_url_raw( trim( wp_unslash( $_POST[ $champ ] ) ), array( 'http', 'https' ) ) : '';
		return $this->limit_string( $lien, 500, $nom );
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
		// Une page qui renvoie après une coupure : si sa notice est déjà
		// reçue, ou en train de l'être, on le lui dit sans rien refaire.
		$this->reponse_deja_connue();
		// Avant tout refus : la saisie gardée doit retrouver chaque figure à
		// sa place, celles envoyées à part comprises.
		$this->ranger_les_figures_a_part();
		// Un délai d'envoi forgé en tableau passait le contrôle des trois
		// secondes : c'est un robot, on refuse comme pour lui.
		if ( isset( $_POST['na_ts'] ) && ! is_string( $_POST['na_ts'] ) ) {
			error_log( 'Notice Archeomed: forged timestamp.' );
			$this->cause_du_refus = 'heure d’affichage forgée';
			$this->saisie_a_garder = false;
			$this->redirect_result( false, 'securite' );
		}
		// La notice que ce formulaire corrige en place, s'il en corrige une.
		// Ouvert par la rédaction depuis la fiche, il ne passe ni les plafonds
		// ni la protection anti-robot : la personne est entrée dans
		// l'administration, et c'est elle qui tient la revue.
		$contexte  = $this->contexte_poste();
		$redaction = null !== $contexte && $contexte['par_la_redaction'] > 0;
		// Un essai de charge ouvert par la rédaction : le dépôt qui présente
		// sa clé ne passe ni la protection anti-robot ni les plafonds par
		// poste. Le plafond de toute la revue le borne, sans qu'il s'y
		// compte : les vrais déposants du même jour gardent le leur entier.
		$essai = ! $redaction && Notice_Archeomed_Settings::cle_d_essai_valide(
			isset( $_POST['na_essai'] ) && is_string( $_POST['na_essai'] ) ? wp_unslash( $_POST['na_essai'] ) : '' );
		$this->essai = $essai;
		// Le plafond de tentatives d'abord, et il compte les refus : un robot
		// qui martèle sans jeton valable ne doit pas passer, lui, sous le
		// plafond que seuls ses essais aboutis feraient monter.
		if ( ! $redaction && ! $this->check_attempt_limit() ) {
			error_log( 'Notice Archeomed: attempt limit reached.' );
			$this->cause_du_refus = 'plafond de tentatives du poste';
			$this->saisie_a_garder = false;
			$this->redirect_result( false, 'quota' );
		}
		self::entrees_attendues();
		if ( ! isset( $_POST['notice_archeomed_nonce'] ) || ! $this->verifier_le_jeton( $_POST['notice_archeomed_nonce'], ! $redaction ) ) {
			error_log( 'Notice Archeomed: nonce verification failed.' );
			$this->cause_du_refus = 'jeton refusé';
			// Un jeton périmé est le lot d'une page restée ouverte : la
			// personne retrouve sa saisie. Pas de jeton du tout, c'est un robot.
			$this->saisie_a_garder = ! empty( $_POST['notice_archeomed_nonce'] ) && is_string( $_POST['notice_archeomed_nonce'] );
			$this->redirect_result( false, 'securite' );
		}
		if ( ! empty( $_POST['na_website'] ) ) {
			error_log( 'Notice Archeomed: honeypot triggered.' );
			$this->cause_du_refus = 'champ piège rempli';
			$this->saisie_a_garder = false;
			$this->redirect_result( false, 'piege' );
		}
		// L'heure d'affichage de la page est exigée : sans elle, le délai
		// minimal se contournait en l'omettant. Un humain l'a toujours, posée
		// par la page ; elle ne peut pas être dans le futur, ni vieille de
		// plus d'une semaine.
		$ts = isset( $_POST['na_ts'] ) ? absint( $_POST['na_ts'] ) : 0;
		if ( $ts <= 0 || $ts > time() + 300 || ( time() - $ts ) < 3 || ( time() - $ts ) > WEEK_IN_SECONDS ) {
			error_log( 'Notice Archeomed: horodatage absent, invraisemblable ou trop rapide.' );
			$this->cause_du_refus = $ts <= 0 ? 'heure d’affichage absente'
				: ( $ts > time() + 300 ? 'heure d’affichage dans le futur'
				: ( ( time() - $ts ) < 3 ? 'envoyé moins de trois secondes après l’affichage' : 'page affichée il y a plus d’une semaine' ) );
			// Seule une page restée ouverte plus d'une semaine est le fait
			// d'une personne : elle retrouve sa saisie.
			$this->saisie_a_garder = $ts > 0 && ( time() - $ts ) > WEEK_IN_SECONDS;
			$this->redirect_result( false, 'securite' );
		}
		// Un formulaire de correction dont le lien a expiré, ou dont la notice
		// a été supprimée entre-temps : déposé tel quel, il ferait une seconde
		// notice, que personne n'a demandée.
		if ( $this->correction_sans_lien() ) {
			error_log( 'Notice Archeomed: correction en place sans réserve valable.' );
			$this->redirect_result( false, Notice_Archeomed_Relecture::campagne_close() ? 'close' : 'perimee' );
		}
		if ( ! $redaction && ! $essai && ! $this->verifier_la_protection() ) {
			$this->redirect_result( false, 'verification' );
		}
		// Le plafond de toute la revue, quelle que soit l'adresse : la barrière
		// ultime contre un remplissage du disque ou une pluie de courriels, que
		// la protection anti-robot ne tiendrait pas (Turnstile laisse passer
		// quand Cloudflare est injoignable). Largement au-dessus d'une
		// campagne : 250 à 300 notices par an.
		if ( ! $redaction && ! $this->check_global_limit() ) {
			error_log( 'Notice Archeomed: plafond global de dépôts atteint.' );
			$this->cause_du_refus = 'plafond de dépôts de toute la revue';
			$this->redirect_result( false, 'quota' );
		}
		$this->reserver_la_page();
		$this->garder_le_disque();
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
			'deposant_prenom', 'deposant_nom',
			'commentaires',
		);
		$d = array_merge( $d, $this->textes_postes( $text_fields ) );
		// Le lien du rapport passe par « esc_url_raw » et non par le tamis des
		// champs de texte : il finit dans un lien hypertexte du document, et
		// une adresse mal formée y ferait un lien qui ne mène nulle part. Les
		// schémas sont restreints à ce qu'un rapport peut porter.
		$d['rapport_lien'] = $this->lien_poste( 'rapport_lien', 'le lien du rapport' );
		$d['donnees_lien'] = $this->lien_poste( 'donnees_lien', 'le lien du jeu de données' );
		// Le lien de téléchargement des originaux trop lourds pour le
		// formulaire : la rédaction le trouve dans le courriel et le document.
		$d['originaux_lien'] = $this->lien_poste( 'originaux_lien', 'le lien des originaux' );
		// Le jour où le service de transfert efface les originaux : la
		// rédaction reçoit une alerte qui le dit. Une date mal formée vaut
		// une date manquante.
		$jusqua = isset( $_POST['originaux_jusqua'] ) ? trim( (string) wp_unslash( $_POST['originaux_jusqua'] ) ) : '';
		$d['originaux_jusqua'] = ( '' !== $d['originaux_lien'] && self::date_valide( $jusqua ) ) ? $jusqua : '';
		$d['resp_email'] = isset( $_POST['resp_email'] ) ? sanitize_email( wp_unslash( $_POST['resp_email'] ) ) : '';
		$d['coresp_email'] = isset( $_POST['coresp_email'] ) ? sanitize_email( wp_unslash( $_POST['coresp_email'] ) ) : '';
		$d['coauteur_email'] = isset( $_POST['coauteur_email'] ) ? sanitize_email( wp_unslash( $_POST['coauteur_email'] ) ) : '';
		// Qui dépose pour le responsable n'apprenait rien de son dépôt :
		// la copie partait au seul responsable. Son adresse reçoit la même
		// copie ; elle ne sort jamais de la rédaction ni de la notice.
		$d['deposant_email'] = isset( $_POST['deposant_email'] ) ? sanitize_email( wp_unslash( $_POST['deposant_email'] ) ) : '';
		$d['lieux'] = $this->collect_lieux();
		// « commune » reste le premier lieu : c'est lui qui donne le titre de
		// la fiche, le nom du fichier, l'objet du courriel et le rang au
		// classement. Le reste du plugin n'a pas à savoir qu'il peut y en
		// avoir cinq.
		$d['commune']     = isset( $d['lieux'][0] ) ? $d['lieux'][0]['nom'] : '';
		$d['commune_ark'] = isset( $d['lieux'][0] ) ? $d['lieux'][0]['ark'] : '';
		// Corrigée en place, la notice ne remplace rien : elle reste elle-même.
		$d['remplace'] = null !== $contexte ? '' : $this->reference_corrigee();
		// Le rang de son renvoi dans V, que le coordinateur a posé à la
		// relecture et que le formulaire ne montre pas, demeure tant que le
		// renvoi demeure.
		if ( null !== $contexte ) {
			$ancienne = get_post_meta( (int) $contexte['id'], '_na_donnees', true );
			$classe   = self::renvoi_matiere_de( array_merge( $d, array(
				'renvoi_matiere' => is_array( $ancienne ) && isset( $ancienne['renvoi_matiere'] ) ? $ancienne['renvoi_matiere'] : '' ) ) );
			if ( '' !== $classe ) {
				$d['renvoi_matiere'] = $classe;
			}
		}
		$d['organismes'] = $this->collect_organismes();
		// Comme pour les lieux : « organisme » reste la ligne unique que le
		// reste du plugin sait lire, et le contrôle des champs obligatoires
		// avec lui.
		$d['organisme'] = implode( ', ', $d['organismes'] );
		$d['illustrations'] = $this->collect_illustrations();
		$d['nature_items'] = $this->collect_natures_items();
		$d['nature']       = implode( ', ', wp_list_pluck( $d['nature_items'], 'label' ) );
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
		if ( ! $redaction && ! $essai && ! $this->check_send_limit( $d['resp_email'] ) ) {
			error_log( 'Notice Archeomed: send limit reached.' );
			$this->redirect_result( false, 'envois' );
		}
		// La notice se corrige sur elle-même : elle garde sa référence, sa
		// fiche et ses figures, et la rédaction n'en a jamais deux.
		if ( null !== $contexte ) {
			$this->corriger_en_place( $contexte, $d );
		}
		$upload_error = '';
		$attachments = $this->handle_uploads( $upload_error );
		if ( '' !== $upload_error ) {
			error_log( 'Notice Archeomed: upload error: ' . $upload_error );
			$this->redirect_result( false, $this->raison_du_televersement( $upload_error ) );
		}
		// Les JPEG tirés des TIFF par le navigateur, quand le serveur n'a pas
		// Imagick : ils se reçoivent ici, avec les figures, et ne refusent rien.
		$conversions = $this->recevoir_les_conversions( $attachments );
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
		// Le lien de correction part avec la copie de l'auteur : c'est en
		// lisant sa notice qu'il voit ce qu'il n'a pas vu en la saisissant.
		// Il se calcule ici, dans la requête de l'auteur — le planificateur,
		// lui, n'a plus de page de référence.
		//
		// Il rouvre cette notice-ci, et la corrige : un nouveau dépôt qui
		// remplaçait l'ancien laissait à la rédaction deux notices à
		// rapprocher, et à l'auteur ses illustrations à redéposer. La
		// réserve qui le fait vivre s'écrit une fois la notice inscrite.
		$vivant              = strtolower( wp_generate_password( 12, false, false ) );
		$page                = $this->page_du_formulaire();
		$d['correction_url'] = add_query_arg( 'notice_reprise', $vivant, '' !== $page ? $page : $this->url_du_formulaire() );
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
			// La notice est reçue : une page qui renverrait, la réponse
			// perdue ou la requête morte plus loin, apprend qu'elle l'est.
			$this->retenir_la_page( $this->adresse_du_resultat( true, '', '', $reference ) );
			set_transient( 'na_reprise_' . $vivant, array(
				'corrige_id'       => $id,
				'vivant'           => true,
				'par_la_redaction' => 0,
			), MONTH_IN_SECONDS );
			// La notice d'un essai de charge se reconnaît, et son déposant
			// automatique reçoit le lien de correction que la copie aurait
			// porté : ses auteurs n'ont pas d'adresse où la lire.
			if ( $essai ) {
				update_post_meta( $id, '_na_essai', 1 );
				header( 'X-NA-Correction: ' . esc_url_raw( $d['correction_url'] ) );
			}
			// Le temps de la préparation, la file laisse la notice : la
			// marque est posée par l'inscription même. Si la requête meurt,
			// elle vieillit et la file reprend la notice d'elle-même.
			//
			// Les illustrations gagnent leur place définitive dès
			// maintenant, et la file retient ces chemins-là : c'est eux
			// que le courriel joindra, et eux que le dossier Métopes
			// trouvera même si le courriel ne part jamais.
			$gardees = $this->archiver_les_illustrations( $id, $illustrations );
			$conv_gardees = $this->archiver_les_conversions( $id, $conversions );
			// Les autorisations suivent les figures dans le courriel, et
			// se retrouvent sur la fiche de la notice.
			$fichiers = array_merge( $gardees, $this->archiver_les_autorisations( $id, $autorisations ) );
			update_post_meta( $id, '_na_fichiers', $fichiers );
			$this->record_send( $d['resp_email'] );
			$this->record_global_send();

			// ── Le dépôt, et rien de plus ─────────────────────────────────
			//
			// Tout ce qui est lent — la poignée de main SMTP, le transfert de
			// vingt méga-octets de pièces jointes, les accusés de réception —
			// sort de la requête de l'auteur. Elle rend la main tout de suite,
			// et le processus PHP se libère pour le déposant suivant.
			//
			// Les versions allégées et le Word en sortent aussi. Une planche
			// en PDF demandait quinze secondes à elle seule, dans la requête
			// de l'auteur : il attendait devant le formulaire, et l'un des
			// cinq processus du serveur restait pris pendant ce temps. Dix
			// déposants à la fois, et le onzième trouvait porte close. La
			// tâche des aperçus les fabrique juste après, puis appelle la
			// file ; la file refait le Word à partir de la saisie gardée,
			// qui est celle-ci, avec les mêmes aperçus. Le courriel ne
			// change pas.
			if ( $this->envoi_differe() ) {
				delete_post_meta( $id, '_na_preparation' );
				if ( $this->apercus_a_fabriquer( $id ) ) {
					$this->programmer_les_apercus( $id );
				}
				$this->file()->programmer( $id );
				$this->redirect_result( true, '', '', $reference );
			}
			// L'envoi immédiat : tout dans cette requête, puisqu'il sert là où
			// le planificateur ne passe pas.
			//
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
			// Ni les aperçus ni le Word ne doivent faire échouer un dépôt déjà
			// inscrit : une erreur ici, même fatale pour PHP (« Error »), ne
			// doit pas montrer une page d'échec à l'auteur, qui redéposerait
			// une notice que la rédaction a déjà.
			$apercus  = array();
			$document = '';
			try {
				$apercus = $this->fabriquer_les_apercus( $gardees, $d, $conv_gardees );
				if ( ! empty( $apercus ) ) {
					update_post_meta( $id, '_na_apercus', $apercus );
				}
				$document = $this->fabriquer_le_document( $this->attacher_les_apercus( $d, $apercus ) );
			} catch ( Throwable $e ) {
				error_log( 'Notice Archeomed: aperçus ou document non fabriqués (' . get_class( $e ) . ' : ' . $e->getMessage() . ').' );
			}
			if ( '' !== $document ) {
				$fichiers[] = $document;
				update_post_meta( $id, '_na_fichiers', $fichiers );
				update_post_meta( $id, '_na_document', $document );
			}
			delete_post_meta( $id, '_na_preparation' );
			// L'envoi immédiat : la même expédition, dans cette requête. Les
			// versions allégées viennent d'être tentées ; la file ne les
			// retente pas avant de partir. Un refus laisse la notice en file,
			// où la relance la reprend : elle est reçue, et l'auteur n'a rien
			// à refaire.
			update_post_meta( $id, '_na_apercus_tente', time() );
			// Une erreur pendant l'expédition ne doit pas s'afficher à
			// l'auteur, qui redéposerait une notice déjà reçue : elle est
			// consignée, et la relance reprend la notice.
			try {
				$this->expedier_de_la_file( $id );
			} catch ( Throwable $e ) {
				error_log( 'Notice Archeomed: expédition immédiate interrompue (' . $e->getMessage() . '), la relance reprendra la notice ' . $id . '.' );
			}
			$this->redirect_result( true, '', '', $reference );
		}
		// L'inscription a échoué — base de données indisponible. Plutôt que
		// de perdre la notice, on l'envoie sans dépôt.
		error_log( 'Notice Archeomed: dépôt en file impossible, envoi sans dépôt.' );
		// Sans notice inscrite, rien à corriger en place : le lien rouvre la
		// saisie, et l'auteur dépose une notice qui remplace celle-ci.
		$d['correction_url'] = $this->lien_de_correction( $reference );
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
			$this->record_global_send();
			$this->nettoyer( $produits );
			$this->redirect_result( true, '', '', $reference );
		}
		// Conservée, la notice est reçue : la même page ne la redéposera pas.
		$this->consommer_le_sceau();
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
		// La tâche des termes se remet en route au passage de la relance, toutes
		// les cinq minutes, et non à chaque page d'administration.
		add_action( Notice_Archeomed_File::HOOK_RELANCE, array( $this, 'reveiller_la_file' ), 20, 0 );
		// Le rangement sur sDrive, après l'envoi ; l'export du numéro, à la demande.
		Notice_Archeomed_Rangement::brancher();
		Notice_Archeomed_Export::brancher();
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
		&$restees = array(), $avertissement = '', &$legeres = array() ) {
		$pourquoi = '';
		$restees  = array();
		$wrap_open  = '<html lang="fr"><body><div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#222;">';
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
		// Une notice déjà reçue, que l'auteur a corrigée en place : même
		// référence, et l'objet le dit pour qu'on ne la croie pas en double.
		$corrigee = $id ? get_post_meta( (int) $id, '_na_corrigee', true ) : '';
		$corrigee = is_array( $corrigee ) ? $corrigee : null;
		if ( null !== $corrigee ) {
			$subject = '[CORRIGÉE] ' . $subject;
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
			// Aussi la virgule, le point-virgule et le guillemet : dans un nom, ils
			// ouvriraient une seconde adresse dans l'en-tête.
			$resp_name_safe = str_replace( array( "\r", "\n", '<', '>', ',', ';', '"' ), '', $resp_complet );
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
		$joindre  = $existants;
		$legeres  = array();
		$par_rang = array();
		if ( $id ) {
			$joindre = $this->pieces_du_courriel( $id, $d, $existants, $document,
				strlen( $notice ), $restees, $legeres, $par_rang );
		}
		$corps = $notice;
		$integrees = $this->figures_dans_le_corps( $corps, $joindre, $par_rang );
		if ( $id ) {
			$corps = $this->correction_pour_la_redaction( $id, $d, $subject ) . $corps;
		}
		if ( ! empty( $legeres ) || ! empty( $restees ) ) {
			$corps = $this->avis_des_illustrations( count( $legeres ), $restees, $id ) . $corps;
		}
		// Une correction qui ne reprend pas toutes les figures de la notice
		// qu'elle remplace : la rédaction doit savoir, avant de supprimer
		// celle-ci, que ses originaux sont encore là.
		$figures_restees = $this->figures_de_la_remplacee( $d );
		if ( '' !== $figures_restees ) {
			$avertissement .= '<div style="background:#fdf6e3;border:2px solid #8a6d3b;padding:14px 18px;margin:0 0 18px;">'
				. esc_html( $figures_restees ) . '</div>';
		}
		// Ce qui a changé depuis la version reçue : la rédaction relit ces
		// passages-là, et non la notice entière.
		if ( null !== $corrigee ) {
			$fiche = admin_url( 'post.php?post=' . (int) $id . '&action=edit' );
			$avertissement = '<div style="background:#eef4f9;border:2px solid #72aee6;padding:14px 18px;margin:0 0 18px;">'
				. '<strong>Notice corrigée</strong>&nbsp;: elle vous était déjà parvenue sous la même référence, '
				. 'et cette version remplace la précédente. '
				. ( ! empty( $corrigee )
					? 'Ce qui a changé&nbsp;: ' . esc_html( implode( ', ', $corrigee ) ) . '.'
					: 'Rien de ce que la notice dit n’a changé.' )
				. ' L’historique est sur la fiche&nbsp;: <a href="' . esc_url( $fiche ) . '">' . esc_html( $fiche ) . '</a>.</div>'
				. $avertissement;
		}
		// Ce que la file a constaté avant l'envoi — un Word qui n'a pas pu
		// être refait —, pour la rédaction seule.
		$corps = $avertissement . $corps;
		// Un seul envoi pour toute la rédaction : les pièces jointes pèsent
		// jusqu'à vingt méga-octets, et les répéter par destinataire ferait
		// payer la liste au poids.
		$integrer = function ( $mailer ) use ( $integrees ) {
			foreach ( $integrees as $f ) {
				$mailer->addEmbeddedImage( $f['fichier'], $f['cid'], $f['nom'] );
			}
		};
		if ( ! empty( $integrees ) ) {
			add_action( 'phpmailer_init', $integrer );
		}
		$sent = self::courriel( $pour_la_redaction, $subject, $wrap_open . $corps . $wrap_close,
			$headers, $joindre );
		remove_action( 'phpmailer_init', $integrer );
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

		// Les originaux déposés ailleurs ont leur propre courriel, que l'objet
		// rend impossible à manquer.
		$this->alerter_pour_les_originaux( $d, $pour_la_redaction, $headers,
			( isset( $lieux[0] ) ? $lieux[0]['nom'] : $d['commune'] ) . ' (' . $d['departement'] . ')'
				. ( '' !== trim( (string) $d['lieu_dit'] ) ? ', ' . trim( (string) $d['lieu_dit'] ) : '' ),
			$reference );

		$destinataires = array_unique( array_filter(
			array( $d['resp_email'], $d['coresp_email'], $d['coauteur_email'],
				isset( $d['deposant_email'] ) ? $d['deposant_email'] : '' ), 'is_email' ) );
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
		// Reçues, et non « envoyées » : une figure trop lourde pour le
		// courriel reste sur le site, où la rédaction la télécharge.
		if ( count( $existants ) > count( $pour_les_auteurs ) ) {
			$pj_note .= '<p>Les illustrations transmises avec le formulaire ont bien été reçues par la rédaction.</p>';
		}
		// Le lien qui rouvre le formulaire rempli. C'est en lisant sa notice
		// qu'on voit ce qu'on n'a pas vu en la saisissant : sans ce lien, il
		// fallait tout retaper, ou écrire à la rédaction.
		$correction = '';
		if ( ! empty( $d['correction_url'] ) ) {
			$url = esc_url( $d['correction_url'] );
			$correction = '<p style="background:#fdf6e3;border:1px solid #8a6d3b;padding:14px 18px;">'
				. '<strong>Une erreur vous saute aux yeux ?</strong><br>'
				. ( self::lien_vivant( $d['correction_url'] )
					? 'Ce lien rouvre le formulaire rempli de votre saisie, avec vos illustrations : '
						. 'corrigez ce qui doit l\'être, et renvoyez-le. Chaque illustration se garde, '
						. 'se remplace ou se retire. Votre notice est corrigée sous la même référence, '
						. 'et la rédaction en est avertie. Le lien vaut un mois.<br>'
					: 'Ce lien rouvre le formulaire rempli de votre saisie : corrigez ce qui doit '
						. 'l\'être, et renvoyez-le. Les illustrations sont à redéposer — aucun '
						. 'navigateur ne permet de les remettre en place. Votre nouveau dépôt '
						. 'préviendra la rédaction qu\'il remplace celui-ci. Le lien vaut un mois.<br>' )
				. '<a href="' . $url . '">' . $url . '</a></p>';
		}
		$rappel = '';
		if ( null !== $corrigee ) {
			$rappel = '<p>Votre correction a bien été enregistrée : elle remplace la version précédente '
				. 'de votre notice, sous la même référence, et la rédaction en est avertie.</p>';
		} elseif ( ! empty( $d['remplace'] ) ) {
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
		$objet_copie = ( ! empty( $d['remplace'] ) || null !== $corrigee )
			? 'Copie de votre notice corrigée - Archéologie médiévale'
			: 'Copie de votre notice - Archéologie médiévale';
		// Chaque copie garde son sort sur la fiche : un déposant qui disait ne
		// rien avoir reçu ne laissait aucune trace où chercher pourquoi.
		$copies = array();
		foreach ( $destinataires as $dest ) {
			$this->derniere_erreur_mail = '';
			$partie = self::courriel( $dest, $objet_copie,
				$wrap_open . $intro . $notice . $wrap_close, $headers_conf,
				$pour_les_auteurs );
			$copies[] = array( 'a' => $dest, 'partie' => (bool) $partie,
				'raison' => $partie ? '' : $this->derniere_erreur_mail );
			if ( ! $partie ) {
				error_log( 'Notice Archeomed: copie refusée vers ' . $dest
					. ( '' !== $this->derniere_erreur_mail ? ' — ' . $this->derniere_erreur_mail : '' ) );
			}
		}
		if ( $id ) {
			update_post_meta( $id, '_na_copies', array( 'le' => current_time( 'mysql' ), 'copies' => $copies ) );
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
		// Quinze secondes, non les cinq minutes de PHPMailer : un relais muet
		// retiendrait sinon un processus PHP par envoi, et la campagne serait
		// bloquée par ce qu'on cherchait à ne pas faire attendre.
		$phpmailer->Timeout = 15;
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
			$phpmailer->setFrom( $expediteur, Notice_Archeomed_Settings::nom_d_expediteur(), false );
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
		// L'encodeur du courriel tient la pièce en mémoire, encodée, et plus
		// d'une fois : à vingt méga-octets, une limite de PHP trop basse
		// arrêtait la page sur une erreur fatale, sans résultat. On le dit
		// avant d'essayer.
		$limite = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		if ( $limite > 0 && memory_get_usage() + 4 * $octets > $limite ) {
			return array( 'ok' => false, 'mo' => $mo, 'quand' => current_time( 'mysql' ),
				'message' => 'Essai non lancé : la mémoire de PHP (' . size_format( $limite ) . ') ne suffit pas à encoder '
					. $mo . ' Mo de pièce jointe. Un courriel aussi lourd ferait tomber l’envoi de la même façon.' );
		}
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
			// Un disque plein donnait un fichier tronqué, et l'essai mesurait
			// un poids qu'il n'avait pas envoyé.
			if ( strlen( $morceau ) !== (int) fwrite( $flux, $morceau ) ) {
				fclose( $flux );
				@unlink( $fichier );
				return array( 'ok' => false, 'message' => 'Le fichier d’essai n’a pas pu être écrit en entier (disque plein ?).' );
			}
			$ecrit += strlen( $morceau );
		}
		fclose( $flux );

		$encode = (int) ceil( $octets * 4 / 3 * 78 / 76 );
		$this->derniere_erreur_mail = '';
		$parti = self::courriel( $vers, '[ESSAI DE POIDS] ' . $mo . ' Mo de pièce jointe',
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
			$sans = str_replace( array( "\r", "\n", '<', '>', ',', ';', '"' ), '', $resp );
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
		$document = (string) get_post_meta( (int) $id, '_na_document', true );
		$pieces   = $this->pieces_du_courriel( $id, $d, $produits, $document, strlen( $notice ),
			$restees, $legeres );
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
		$parti = self::courriel( $vers, $sujet, $corps, $entetes, $jointes );
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
		$parti = self::courriel(
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

	/** Vrai pendant qu'un courriel du formulaire est en train de partir. */
	private static $courriel_en_cours = false;

	/**
	 * Envoie un courriel du formulaire, sous le nom de la revue.
	 *
	 * Les destinataires lisaient « hébergement no-reply » : le nom que
	 * l'hébergement donne à tout ce que le site envoie. Celui de la revue ne
	 * vaut que pour ce qui part d'ici — un mot de passe oublié, le courriel
	 * d'une autre extension gardent le leur. L'adresse ne change pas : c'est
	 * celle que le serveur a le droit d'employer, et en prendre une autre
	 * ferait passer le message pour une usurpation.
	 */
	public static function courriel( $vers, $objet, $corps, $entetes = '', $jointes = array() ) {
		self::$courriel_en_cours = true;
		try {
			return wp_mail( $vers, $objet, $corps, $entetes, $jointes );
		} finally {
			self::$courriel_en_cours = false;
		}
	}

	/** Vrai si le courriel qui part est un courriel du formulaire. */
	public static function courriel_en_cours() {
		return self::$courriel_en_cours;
	}

	/**
	 * Pose le nom de la revue sur un courriel du formulaire, juste avant
	 * qu'il parte : rien de ce qui a été réglé ailleurs ne passe après.
	 */
	public static function nommer_l_expediteur( $phpmailer ) {
		if ( ! self::$courriel_en_cours || ! is_object( $phpmailer ) ) {
			return;
		}
		$nom = Notice_Archeomed_Settings::nom_d_expediteur();
		if ( '' !== $nom ) {
			$phpmailer->FromName = $nom;
		}
	}

	/** Retient ce que le serveur de courriel a répondu, pour le dire plus haut. */
	public function retenir_l_erreur_mail( $erreur ) {
		if ( is_wp_error( $erreur ) ) {
			$this->derniere_erreur_mail = trim( (string) $erreur->get_error_message() );
		}
	}

	/**
	 * Les chemins des illustrations rangées d'une notice, dans l'ordre des
	 * figures. Une métadonnée absente se lisait « array('') » : un rang 1
	 * fantôme, et une pièce jointe en double.
	 */
	public static function illustrations_rangees( $id ) {
		return array_values( array_filter( (array) get_post_meta( (int) $id, '_na_illustrations', true ), function ( $chemin ) {
			return is_string( $chemin ) && '' !== $chemin;
		} ) );
	}

	/**
	 * Les illustrations d'une notice déposée avant que l'archivage ne se
	 * sépare de l'envoi : elle n'en a pas la liste, elles sont parmi ses
	 * fichiers produits, hors le document. Rien pour une notice qui a sa
	 * liste, fût-elle vide.
	 *
	 * La correction ne lisait que la liste : ouverte sur une de ces notices,
	 * elle la récrivait sans ses figures, que plus rien ne désignait.
	 */
	private static function figures_d_avant( $id ) {
		if ( metadata_exists( 'post', (int) $id, '_na_illustrations' ) ) {
			return array();
		}
		$document = (string) get_post_meta( (int) $id, '_na_document', true );
		$figures  = array();
		foreach ( (array) get_post_meta( (int) $id, '_na_fichiers', true ) as $fichier ) {
			if ( is_string( $fichier ) && '' !== $fichier && $fichier !== $document ) {
				$figures[] = $fichier;
			}
		}
		return $figures;
	}

	/**
	 * Range une fois pour toutes ces illustrations-là, et rend les fichiers
	 * produits tels qu'ils sont désormais. L'appelant tient la notice : la
	 * file l'a prise, ou une correction la récrit.
	 */
	private function ranger_les_figures_d_avant( $id ) {
		$a_ranger = self::figures_d_avant( $id );
		if ( empty( $a_ranger ) ) {
			return (array) get_post_meta( (int) $id, '_na_fichiers', true );
		}
		$document = (string) get_post_meta( (int) $id, '_na_document', true );
		$produits = $this->archiver_les_illustrations( $id, $a_ranger, true );
		if ( '' !== $document ) {
			$produits[] = $document;
		}
		update_post_meta( (int) $id, '_na_fichiers', $produits );
		return $produits;
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
	 * réglé, c'est l'original que « $restees » nomme — lui est sur le site.
	 * « $legeres » dit les versions allégées effectivement jointes.
	 *
	 * La version allégée part telle qu'elle est rangée, sous le nom de sa
	 * figure, que la clé du tableau des pièces donne à « wp_mail » : on en
	 * faisait une copie nommée, dans un dossier temporaire effacé après
	 * l'envoi — qu'une extension d'envoi différé trouvait vide, et qu'une
	 * requête coupée laissait derrière elle.
	 */
	private function pieces_du_courriel( $id, $d, $existants, $document, $poids_du_corps,
		&$restees, &$legeres, &$par_rang = array() ) {
		$restees   = array();
		$legeres   = array();
		$par_rang  = array();
		$originaux = self::illustrations_rangees( (int) $id );
		$apercus   = $this->apercus_de( $id, $d );
		$budget    = $this->budget_du_courriel( $poids_du_corps );
		$joindre   = array();
		$cumul     = 0;

		if ( '' !== $document && in_array( $document, $existants, true ) ) {
			$joindre[] = $document;
			$cumul    += $this->poids_encode( $document );
		}

		foreach ( $originaux as $i => $original ) {
			$rang   = $i + 1;
			$piece  = '';
			$legere = false;
			// Le témoin d'une figure envoyée à part ne part pas : le courriel
			// donne son lien.
			if ( self::est_a_part( $original ) ) {
				continue;
			}
			if ( ! empty( $apercus[ $rang ]['apercu'] ) && is_readable( $apercus[ $rang ]['apercu'] ) ) {
				$piece  = $apercus[ $rang ]['apercu'];
				$legere = true;
			}
			if ( '' === $piece && in_array( $original, $existants, true ) ) {
				$piece = $original;
			}
			if ( '' === $piece ) {
				continue;
			}
			$poids = $this->poids_encode( $piece );
			if ( $cumul + $poids <= $budget ) {
				// Chaque figure part sous son nom : celui du serveur est tiré
				// au sort, et ne disait ni de quelle image il s'agit ni où
				// elle va.
				$joindre[ $this->nom_de_piece( $d, $rang, $piece, $legere ? '_apercu' : '' ) ] = $piece;
				$par_rang[ $rang ] = $piece;
				if ( $legere ) {
					$legeres[] = $piece;
				}
				$cumul += $poids;
			} else {
				$restees[] = $original;
			}
		}
		// Ce qui est dans la liste d'envoi sans être ni le document ni une
		// illustration connue — une notice très ancienne — suit la même règle.
		$autorisations = get_post_meta( (int) $id, '_na_autorisations', true );
		$autorisations = is_array( $autorisations ) ? $autorisations : array();
		foreach ( $existants as $fichier ) {
			if ( $fichier === $document || in_array( $fichier, $originaux, true ) ) {
				continue;
			}
			$poids = $this->poids_encode( $fichier );
			if ( $cumul + $poids <= $budget ) {
				// Une autorisation de reproduction part sous le nom de sa figure.
				// Le rang se lit dans la notice : une correction renumérote les
				// figures, et le nom du fichier garde celui du premier dépôt.
				$rang = array_search( $fichier, $autorisations, true );
				if ( false === $rang && preg_match( '/autorisation-fig-(\d+)-/', basename( $fichier ), $m ) ) {
					$rang = (int) $m[1];
				}
				if ( false !== $rang ) {
					$joindre[ $this->nom_de_piece( $d, (int) $rang, $fichier, '_autorisation' ) ] = $fichier;
				} else {
					$joindre[] = $fichier;
				}
				$cumul += $poids;
			} else {
				$restees[] = $fichier;
			}
		}
		return $joindre;
	}

	/**
	 * Les figures du courriel de la rédaction, posées dans le corps au-dessus
	 * de leur légende, et non plus en pièces jointes que la messagerie range
	 * au bas du message, loin du texte qui les décrit.
	 *
	 * Seules les images qu'un navigateur affiche y vont ; un TIFF reste joint.
	 * Une notice composée avant la marque n'en a pas : ses figures restent
	 * jointes, comme avant. Le poids est le même, le budget reste juste.
	 */
	private function figures_dans_le_corps( &$corps, &$joindre, $par_rang ) {
		$integrees = array();
		foreach ( $par_rang as $rang => $piece ) {
			$marque = '<!--na-figure-' . (int) $rang . '-->';
			$cle    = array_search( $piece, $joindre, true );
			if ( false === strpos( $corps, $marque ) || false === $cle
				|| ! preg_match( '/\.(jpe?g|png|gif)$/i', $piece ) ) {
				continue;
			}
			$cid    = 'na-figure-' . (int) $rang . '@archeomed';
			$taille = @getimagesize( $piece );
			$large  = $taille ? min( 600, (int) $taille[0] ) : 600;
			$corps  = str_replace( $marque, '<img src="cid:' . esc_attr( $cid ) . '" width="' . $large
				. '" alt="' . esc_attr( 'Aperçu — ' . Notice_Archeomed_Normes::numero_de_figure( (int) $rang ) ) . '"'
				. ' style="display:block;width:' . $large . 'px;max-width:100%;height:auto;margin:10px 0 6px;border:1px solid #ddd;">',
				$corps );
			$integrees[] = array( 'fichier' => $piece, 'cid' => $cid,
				'nom' => is_string( $cle ) ? $cle : basename( $piece ) );
			unset( $joindre[ $cle ] );
		}
		return $integrees;
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
		if ( ! ( Notice_Archeomed_Paquet::imagick_disponible() || function_exists( 'imagecreatetruecolor' ) ) || get_post_meta( (int) $id, '_na_apercus_tente', true ) ) {
			return false;
		}
		$d = get_post_meta( (int) $id, '_na_donnees', true );
		if ( ! is_array( $d ) || empty( $d['illustrations'] ) ) {
			return false;
		}
		$apercus   = $this->apercus_de( $id, $d );
		$originaux = self::illustrations_rangees( (int) $id );
		foreach ( (array) $d['illustrations'] as $item ) {
			$rang = isset( $item['rang'] ) ? (int) $item['rang'] : 0;
			if ( $rang >= 1 && isset( $originaux[ $rang - 1 ] ) && file_exists( $originaux[ $rang - 1 ] )
				&& ! self::est_a_part( $originaux[ $rang - 1 ] )
				&& ( empty( $apercus[ $rang ]['apercu'] ) || ! is_readable( $apercus[ $rang ]['apercu'] ) ) ) {
				return true;
			}
		}
		return false;
	}

	/** Une requête prépare ou récrit la notice, et n'est pas morte en route. */
	private function en_preparation( $id ) {
		return (int) get_post_meta( (int) $id, '_na_preparation', true ) > time() - Notice_Archeomed_File::ABANDON;
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
	 *
	 * Une correction passée pendant le travail a changé les figures : ce
	 * qu'on vient de fabriquer vaut pour les anciennes, et s'efface au lieu
	 * de s'écrire par-dessus les siennes. On le voit à la marque de
	 * préparation et aux figures elles-mêmes, non à la version : elle
	 * avance avant que la correction récrive, et elle avance aussi quand
	 * la fiche enregistre un texte alternatif, qui ne change aucune image.
	 * Jugée sur la version, la tâche s'effaçait alors pour de bon.
	 */
	public function fabriquer_les_apercus_en_tache( $id ) {
		$id = (int) $id;
		if ( $this->en_preparation( $id ) ) {
			return;   // la correction programmera sa propre tâche
		}
		// La marque du travail en cours se pose avant celle de l'essai : entre
		// les deux, la file verrait une notice dont l'essai est fait et qui
		// n'a pas ses aperçus, et l'enverrait sans eux. Le dépôt ne les
		// fabrique plus lui-même ; la relance des cinq minutes, ou un second
		// passage du planificateur, peut donc tomber en plein travail.
		//
		// Elle sert aussi de verrou : deux passages du planificateur qui
		// lancent la même tâche fabriquaient deux fois chaque aperçu, et
		// l'un des deux restait sur le disque sans que rien le désigne. Le
		// second s'efface devant le premier, qui relancera l'envoi ; une
		// marque restée d'une tâche tuée se reprend passé le délai d'abandon.
		if ( ! add_post_meta( $id, '_na_apercus_en_cours', time(), true ) ) {
			wp_cache_delete( $id, 'post_meta' );
			if ( $this->apercus_en_cours( $id ) ) {
				return;
			}
			update_post_meta( $id, '_na_apercus_en_cours', time() );
		}
		update_post_meta( $id, '_na_apercus_tente', time() );
		try {
			$d = get_post_meta( $id, '_na_donnees', true );
			if ( is_array( $d ) && ! empty( $d ) ) {
				$originaux = self::illustrations_rangees( $id );
				$apercus   = $this->apercus_de( $id, $d );
				$a_faire   = array();
				foreach ( $originaux as $i => $original ) {
					$a_faire[ $i ] = ( empty( $apercus[ $i + 1 ]['apercu'] )
						|| ! is_readable( $apercus[ $i + 1 ]['apercu'] ) ) ? $original : '';
				}
				$neufs = $this->fabriquer_les_apercus( $a_faire, $d, self::conversions_rangees( $id ) );
				if ( ! empty( $neufs ) ) {
					wp_cache_delete( $id, 'post_meta' );
					if ( $this->en_preparation( $id ) || self::illustrations_rangees( $id ) !== $originaux ) {
						$this->effacer_des_apercus( $neufs );
						// La correction programme sa propre tâche ; levée, la
						// marque de l'essai laisse aussi l'envoi en refaire une.
						delete_post_meta( $id, '_na_apercus_tente' );
						return;
					}
					update_post_meta( $id, '_na_apercus', $neufs + $apercus );
				}
			}
		} finally {
			// Levée quoi qu'il arrive, sauf si l'hébergement tue la tâche :
			// la marque vieillit alors, et passé le délai d'abandon la file
			// envoie avec ce qu'il y a. La notice part sans aperçu, jamais
			// ne reste en souffrance.
			delete_post_meta( $id, '_na_apercus_en_cours' );
		}
		$this->file()->programmer( $id );
	}

	/** La tâche des aperçus travaille sur la notice, et n'est pas morte en route. */
	private function apercus_en_cours( $id ) {
		return (int) get_post_meta( (int) $id, '_na_apercus_en_cours', true ) > time() - Notice_Archeomed_File::ABANDON;
	}

	/**
	 * Le lien qui rouvre la notice dans le formulaire, pour la rédaction :
	 * un mot-clé à ajouter, une phrase à reprendre, et le Word se refait.
	 *
	 * Il n'était que sur la fiche, d'où l'on ne savait pas qu'il existait.
	 *
	 * À côté, un lien qui ouvre un message à toutes les adresses de la
	 * notice — responsable, correspondant, coauteur, déposant —, l'objet
	 * déjà rempli : écrire aux auteurs ne demande plus de recopier quatre
	 * adresses.
	 */
	private function correction_pour_la_redaction( $id, $d = array(), $objet = '' ) {
		$adresses = array_values( array_unique( array_filter( array(
			isset( $d['resp_email'] ) ? (string) $d['resp_email'] : '',
			isset( $d['coresp_email'] ) ? (string) $d['coresp_email'] : '',
			isset( $d['coauteur_email'] ) ? (string) $d['coauteur_email'] : '',
			isset( $d['deposant_email'] ) ? (string) $d['deposant_email'] : '',
		), 'is_email' ) ) );
		$ecrire = '';
		if ( ! empty( $adresses ) ) {
			// Sans « [CORRIGÉE] » ni « [CORRECTION de …] » : ces marques
			// servent au tri de la rédaction, non aux auteurs.
			$objet  = trim( (string) preg_replace( '/^(\[[^\]]*\]\s*)+/u', '', (string) $objet ) );
			$mailto = 'mailto:' . implode( ',', array_map( function ( $a ) {
				return str_replace( '%40', '@', rawurlencode( $a ) );
			}, $adresses ) ) . ( '' !== $objet ? '?subject=' . rawurlencode( $objet ) : '' );
			$ecrire = '<br><a href="' . esc_url( $mailto, array( 'mailto' ) ) . '">'
				. esc_html( _n( 'Écrire au responsable de la notice', 'Écrire aux responsables de la notice', count( $adresses ), 'notice-archeomed' ) )
				. '</a> — ' . esc_html( implode( ', ', $adresses ) ) . '.';
		}
		return '<div style="background:#f6f7f7;border:1px solid #c3c4c7;padding:12px 16px;margin:0 0 14px;">'
			. '<a href="' . esc_url( Notice_Archeomed_File::lien_pour_corriger( $id ) ) . '">Corriger cette notice dans le formulaire</a>'
			. ' — texte, mots-clés, métadonnées. La notice garde sa référence et son document Word est refait ;'
			. ' personne n’est prévenu. Le lien reste valable, il demande seulement d’être connecté au site.'
			. $ecrire . '</div>';
	}

	/**
	 * L'encadré qui ouvre le courriel de la rédaction : ce qui est joint, en
	 * quelle définition, et où sont les originaux.
	 */
	private function avis_des_illustrations( $legeres, $restees, $id ) {
		$lien   = admin_url( 'post.php?post=' . (int) $id . '&action=edit' );
		$avis   = '';
		$bloque = Notice_Archeomed_Rangement::ce_qui_bloque();
		if ( $legeres > 0 ) {
			$largeur = number_format_i18n( Notice_Archeomed_Paquet::LARGEUR_APERCU );
			$avis   .= '<div style="background:#eef4f9;border:1px solid #72aee6;padding:12px 16px;margin:0 0 14px;">'
				. esc_html( sprintf( _n( '%1$d illustration est jointe en version allégée (au plus %2$s pixels sur le plus grand côté), pour la lecture.',
					'%1$d illustrations sont jointes en version allégée (au plus %2$s pixels sur le plus grand côté), pour la lecture.',
					$legeres, 'notice-archeomed' ), $legeres, $largeur ) )
				. ( Notice_Archeomed_Rangement::peut_ranger()
					// Rangés sur sDrive après l'envoi : la fiche les sert encore.
					? ' Les originaux, en pleine définition, sont rangés sur sDrive dans « '
						. esc_html( Notice_Archeomed_Rangement::dossier_de( $id ) ) . ' » ; ils se téléchargent aussi depuis la '
						. '<a href="' . esc_url( $lien ) . '">fiche de la notice</a>.</div>'
					: ( '' !== $bloque
						// Relié, mais rien ne se range : le dire, plutôt que
						// laisser chercher les fichiers sur sDrive.
						? ' Les originaux, en pleine définition, sont conservés sur le site — '
							. '<a href="' . esc_url( $lien ) . '">fiche de la notice</a> — mais ne sont pas encore rangés sur sDrive : '
							. esc_html( $bloque ) . '. Ils s’y rangeront dès que ce sera réglé.</div>'
						: ' Les originaux, en pleine définition, sont conservés sur le site — '
							. '<a href="' . esc_url( $lien ) . '">fiche de la notice</a> — et figurent dans le '
							. 'dossier Métopes de la rubrique.</div>' ) );
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
		$originaux = self::illustrations_rangees( (int) $id );
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
		// La tâche des aperçus travaille en ce moment : elle relancera
		// l'envoi en finissant. Partir maintenant, c'était envoyer un Word
		// sans ses figures.
		if ( $this->apercus_en_cours( $id ) ) {
			return 'en_preparation';
		}
		if ( ! $this->file()->prendre( $id ) ) {
			return 'occupee';
		}
		// Une correction en place a pu poser sa marque entre la lecture
		// d'en haut et la prise — ou la tâche des aperçus commencer : la
		// notice se récrit, on la rend à la file plutôt que d'envoyer un
		// mélange de l'ancienne et de la nouvelle.
		wp_cache_delete( $id, 'post_meta' );
		$preparee = (int) get_post_meta( $id, '_na_preparation', true );
		if ( $preparee > time() - Notice_Archeomed_File::ABANDON || $this->apercus_en_cours( $id ) ) {
			update_post_meta( $id, '_na_etat', 'en_attente' );
			delete_post_meta( $id, '_na_prise' );
			return 'en_preparation';
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
		// Une notice corrigée qui n'a plus de figure a une liste vide, mais
		// une liste : ce qui reste dans ses fichiers n'est pas à ranger.
		$produits = $this->ranger_les_figures_d_avant( $id );
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
		$legeres  = array();
		if ( $this->expedier( $d, $notice, $produits, $document, $pourquoi, $id, $restees, $avertissement, $legeres ) ) {
			// Partie, mais pas entière : la liste le dit, pour qu'on ne croie
			// pas la rédaction en possession de toutes les figures.
			$note = empty( $restees ) ? '' : sprintf(
				_n( 'Partie sans %d illustration, trop lourde pour un courriel ; elle reste sur le site.',
					'Partie sans %d illustrations, trop lourdes pour un courriel ; elles restent sur le site.',
					count( $restees ), 'notice-archeomed' ), count( $restees ) );
			// Et en quelle définition : un original n'est pas dans la boîte.
			if ( ! empty( $legeres ) ) {
				$note = trim( sprintf( _n( '%d illustration jointe en version allégée.',
					'%d illustrations jointes en version allégée.', count( $legeres ), 'notice-archeomed' ),
					count( $legeres ) ) . ' ' . $note );
			}
			if ( $sans_document ) {
				$note = trim( 'Partie sans le document Word, qui n’a pas pu être refait. ' . $note );
			}
			$this->file()->marquer( $id, 'envoyee', $note );
			// La rédaction a lu ce que la correction changeait.
			delete_post_meta( $id, '_na_corrigee' );
			// Les illustrations sont déjà rangées depuis le dépôt : il ne
			// reste qu'à effacer le document, qui se refabrique à la demande
			// et n'a aucune raison d'encombrer le répertoire temporaire.
			if ( '' !== $document && file_exists( $document ) ) {
				@unlink( $document );
				delete_post_meta( $id, '_na_document' );
			}
			// Ses fichiers peuvent partir sur sDrive : la rédaction a ce
			// qu'il lui faut, et le serveur garde les aperçus.
			Notice_Archeomed_Rangement::programmer( $id );
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
		if ( ! Notice_Archeomed_Settings::peut_regler() ) {
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
		if ( '' === $dossier || ! is_string( $chemin ) || 0 !== strpos( $chemin, $dossier ) ) {
			wp_die( esc_html__( 'Illustration introuvable.', 'notice-archeomed' ) );
		}
		// Rangée sur sDrive et effacée d'ici : on la rapatrie le temps de la
		// servir, sous le nom qu'elle a là-bas.
		$nom         = basename( $chemin );
		$temporaires = array();
		if ( ! is_file( $chemin ) ) {
			$copie  = Notice_Archeomed_Rangement::copie_de( $id, $chemin );
			$chemin = Notice_Archeomed_Rangement::rapatrier( $id, $chemin, $temporaires );
			if ( '' === $chemin ) {
				wp_die( esc_html( null !== $copie
					? __( 'L’illustration est rangée sur sDrive, qui ne la rend pas pour l’instant. Réessayez plus tard, ou ouvrez-la dans sDrive.', 'notice-archeomed' )
					: __( 'Illustration introuvable.', 'notice-archeomed' ) ) );
			}
			$nom = basename( $copie['distant'] );
		}
		$type = wp_check_filetype( $nom );
		nocache_headers();
		header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $nom ) . '"' );
		header( 'Content-Length: ' . filesize( $chemin ) );
		readfile( $chemin );
		Notice_Archeomed_Rangement::effacer_les_temporaires( $temporaires );
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
		// Les JPEG tirés des TIFF par le navigateur de l'auteur.
		$conversions = array();
		foreach ( self::conversions_rangees( $id ) as $fichiers ) {
			$conversions = array_merge( $conversions, array_values( (array) $fichiers ) );
		}
		$this->effacer_dans_le_depot( $conversions );
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
		$arrete     = false;
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
				$arrete = true;
				break;
			}
			// Une erreur levée par la lecture d'un terme sortait de la tâche
			// entière : aucun essai ne se comptait, la notice restait due, et
			// le même terme arrêtait la file à chaque passage. Elle ne coûte
			// plus que ce terme, compté comme manquant.
			$coupe = false;
			try {
				$concept = Notice_Archeomed_Thesaurus::resoudre( $ark, $quoi['id'],
					$quoi['theso'], $echeance, $coupe );
			} catch ( Throwable $e ) {
				error_log( 'Notice Archeomed: Pactols ' . $ark . ' : ' . $e->getMessage() );
				$concept = null;
			}
			if ( is_array( $concept ) ) {
				$termes[ $ark ] = $concept;
			}
			// Le thésaurus dit lui-même qu'il a été coupé : l'horloge ne le
			// devinait qu'à la seconde près.
			$interrompu = $interrompu || $coupe;
		}
		$interrompu = ( $interrompu || $arrete ) && count( array_diff_key( $voulus, $termes ) ) > 0;
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
		if ( ! $id || ! self::lit_pactols() ) {
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
		if ( ! self::lit_pactols() ) {
			return 0;
		}
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

	/**
	 * Le serveur lit-il les termes dans Pactols ? Un réglage le coupe, quand
	 * l'hébergement refuse la sortie : les ARK restent sur les termes du
	 * Word, et la chaîne de transformation peut s'en servir ; seuls la
	 * forme préférée et les blocs d'index calculés ici manquent, sans que
	 * chaque Word le répète ni que la tâche réessaie pour rien.
	 */
	public static function lit_pactols() {
		return 'non' !== Notice_Archeomed_Settings::get( 'pactols_serveur' );
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
		if ( ! self::lit_pactols() || wp_next_scheduled( self::HOOK_TERMES ) ) {
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
		// La lecture coupée dans les réglages : la tâche s'efface, et rien ne
		// la réserve plus tant qu'on ne la rouvre pas.
		if ( ! self::lit_pactols() ) {
			wp_clear_scheduled_hook( self::HOOK_TERMES );
			return;
		}
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
		Notice_Archeomed_Rangement::desactiver();
		Notice_Archeomed_Export::desactiver();
		Notice_Archeomed_Relecture::desactiver();
		wp_unschedule_hook( Notice_Archeomed_Settings::HOOK_DISQUE );
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
		$manquants = self::lit_pactols() ? count( $this->termes_manquants( $d, $termes ) ) : 0;
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
		return $this->preparer_la_saisie( $id, $d, $avec_apercus ? $this->apercus_de( $id, self::figures_lisibles( $d ) ) : array() );
	}

	/**
	 * Une saisie mise à la forme que les documents attendent : celle de la
	 * notice aujourd'hui, ou celle d'une de ses versions gardées, d'où se
	 * tire le Word de relecture. Une seule préparation pour les deux : la
	 * version d'avant et celle d'après doivent sortir par le même chemin,
	 * sans quoi le suivi des modifications montrerait des écarts que
	 * personne n'a écrits.
	 */
	private function preparer_la_saisie( $id, $d, $apercus ) {
		$rangs = array();
		$d     = self::sans_les_masquees( self::figures_lisibles( $d ), $rangs );
		// Les aperçus suivent leur figure au rang que le document lui donne.
		if ( ! empty( $apercus ) && ! empty( $rangs ) ) {
			$suivis = array();
			foreach ( $rangs as $origine => $nouveau ) {
				if ( isset( $apercus[ $origine ] ) ) {
					$suivis[ $nouveau ] = $apercus[ $origine ];
				}
			}
			$apercus = $suivis;
		}
		// Le libellé en vigueur de chaque rubrique : une notice reçue sous un
		// ancien s'imprime sous le nouveau.
		foreach ( array( 'rubrique_principale', 'renvoi_1', 'renvoi_2' ) as $champ ) {
			if ( isset( $d[ $champ ] ) ) {
				$d[ $champ ] = self::rubrique_actuelle( $d[ $champ ] );
			}
		}
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
	private function fabriquer_les_apercus( $illustrations, $d, $conversions = array() ) {
		$dossier = $this->dossier_des_illustrations();
		if ( '' === $dossier || ! ( Notice_Archeomed_Paquet::imagick_disponible() || function_exists( 'imagecreatetruecolor' ) )
			|| empty( $d['illustrations'] ) ) {
			return array();
		}
		$fichiers = array_values( (array) $illustrations );
		$paquet   = new Notice_Archeomed_Paquet();
		$apercus  = array();
		foreach ( (array) $d['illustrations'] as $item ) {
			$rang = isset( $item['rang'] ) ? (int) $item['rang'] : 0;
			// Une figure envoyée à part n'a qu'un témoin : rien à réduire.
			if ( $rang < 1 || ! isset( $fichiers[ $rang - 1 ] )
				|| ! file_exists( $fichiers[ $rang - 1 ] ) || self::est_a_part( $fichiers[ $rang - 1 ] ) ) {
				continue;
			}
			$cible  = $dossier . 'apercu-' . wp_generate_password( 10, false, false ) . '.jpg';
			$source = $fichiers[ $rang - 1 ];
			// Un TIFF ou un PDF que le serveur ne sait pas lire : l'aperçu se tire
			// de la basse définition que le navigateur de l'auteur a fabriquée.
			if ( ! Notice_Archeomed_Paquet::imagick_disponible() && in_array( strtolower( pathinfo( $source, PATHINFO_EXTENSION ) ), array( 'tif', 'tiff', 'pdf' ), true )
				&& ! empty( $conversions[ $rang ]['br'] ) && is_readable( $conversions[ $rang ]['br'] ) ) {
				$source = $conversions[ $rang ]['br'];
			}
			$mesure = $paquet->apercu_du_document( $source, $cible );
			if ( is_array( $mesure ) ) {
				$apercus[ $rang ] = $mesure;
			} else {
				// Sans version allégée, c'est l'original qui partira : que le
				// journal dise pourquoi le courriel est lourd.
				error_log( 'Notice Archeomed: version allégée de la figure ' . $rang . ' impossible ('
					. basename( $fichiers[ $rang - 1 ] ) . ') ; l\'original la remplacera.' );
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
	 *
	 * $a_leur_rang garde à sa place le chemin d'un fichier disparu, au lieu
	 * de resserrer la liste : pour les figures d'une notice ancienne, que la
	 * correction désigne par leur rang. Resserrée, la figure 3 y prenait le
	 * fichier de la 4, et le remplacement de la 3 effaçait celui-ci.
	 */
	private function archiver_les_illustrations( $id, $illustrations, $a_leur_rang = false ) {
		$dossier = $this->dossier_des_illustrations();
		$gardees = array();
		foreach ( (array) $illustrations as $fichier ) {
			if ( ! file_exists( $fichier ) ) {
				if ( $a_leur_rang ) {
					$gardees[] = $fichier;
				}
				continue;
			}
			if ( '' === $dossier ) {
				// Pas de dossier de dépôt : on laisse au répertoire temporaire,
				// où la purge les garde un mois sous leur nom « garde ».
				$gardees[] = $fichier;
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
		$jeton = '';
		if ( ! $ok && $this->saisie_a_garder ) {
			$cle_des_reserves = 'na_reserves_ip_' . md5( $this->get_client_ip() );
			if ( self::lire_le_compteur( $cle_des_reserves ) < self::MAX_RESERVES_PAR_IP ) {
				$jeton = $this->garder_la_saisie();
				if ( '' !== $jeton ) {
					self::ajouter_au_compteur( $cle_des_reserves );
				}
			}
		}
		if ( ! $ok ) {
			$this->noter_le_refus( $raison, $champs );
		}
		$redirect = $this->adresse_du_resultat( $ok, $raison, $champ, $reference, $champs, $jeton );
		// Une notice qui porte une référence est reçue, même refusée à
		// l'envoi du courriel : la page qui renverrait l'apprend. Sans
		// référence, rien n'est reçu, et la page peut renvoyer.
		if ( $ok || '' !== $reference ) {
			$this->retenir_la_page( $redirect );
		} else {
			$this->liberer_la_page();
		}
		if ( $this->en_arriere_plan() ) {
			$this->repondre_en_arriere_plan( array( 'redirect' => self::adresse_sure( $redirect ) ) );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/** L'adresse de la page de résultat, refus ou dépôt. */
	private function adresse_du_resultat( $ok, $raison = '', $champ = '', $reference = '', $champs = array(), $jeton = '' ) {
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
		if ( $ok && $this->fichiers_refuses ) {
			$redirect = add_query_arg( 'notice_sans_fichiers', '1', $redirect );
		}
		return $redirect;
	}

	// ── L'envoi en arrière-plan ────────────────────────────────────────────
	//
	// Le navigateur envoyait la page lui-même. Une connexion qui flanche le
	// temps de téléverser une figure lourde — un réseau sans fil d'université,
	// un train —, et l'hébergeur répondait « Request Time-out » sur une page
	// blanche : la personne ne savait ni si la notice était partie, ni si
	// elle pouvait renvoyer sans faire un doublon.
	//
	// La page envoie désormais elle-même, en mesurant ce qui est parti, et
	// lit une courte réponse au lieu d'être redirigée. Elle tire un jeton,
	// le même à chaque renvoi : le serveur retient, sous ce jeton, que la
	// notice est en route, puis ce qu'elle est devenue. Une page qui renvoie
	// après une coupure apprend ainsi que sa notice est reçue, ou en train
	// de l'être, au lieu d'en déposer une seconde.

	/**
	 * Une requête ne calcule pas plus de deux minutes chez l'hébergeur, et
	 * la page n'est retenue qu'une fois les fichiers arrivés : au-delà de
	 * trois, un envoi resté « en route » est mort avec sa requête.
	 */
	const ATTENTE_D_UN_ENVOI = 180;

	/** L'envoi vient-il de la page elle-même, qui attend une réponse courte ? */
	private function en_arriere_plan() {
		return ! empty( $_SERVER['HTTP_X_NA_ENVOI'] )
			|| ( isset( $_POST['na_arriere_plan'] ) && '1' === $_POST['na_arriere_plan'] );
	}

	/** Le jeton de la page, s'il est bien formé ; sinon une chaîne vide. */
	private static function page_postee() {
		$page = isset( $_POST['na_page'] ) && is_string( $_POST['na_page'] ) ? strtolower( trim( wp_unslash( $_POST['na_page'] ) ) ) : '';
		return 1 === preg_match( '/^[a-z0-9]{16,40}$/', $page ) ? $page : '';
	}

	private static function cle_de_la_page( $page ) {
		return 'na_page_' . md5( (string) $page );
	}

	/**
	 * Ce que le serveur sait de la page : null, ou l'heure où l'envoi a été
	 * retenu et l'adresse du résultat, vide tant qu'il est en route. Lu en
	 * base et non dans la mémoire de WordPress : un autre processus l'écrit.
	 */
	private static function etat_de_la_page( $page ) {
		global $wpdb;
		$valeur = $wpdb->get_var( $wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::cle_de_la_page( $page ) ) );
		if ( null === $valeur ) {
			return null;
		}
		$morceaux = explode( '|', (string) $valeur, 2 );
		return array(
			'valeur'  => (string) $valeur,
			'ts'      => (int) $morceaux[0],
			'adresse' => isset( $morceaux[1] ) ? $morceaux[1] : '',
		);
	}

	/**
	 * Avant tout contrôle : la page qui renvoie apprend ce que sa notice est
	 * devenue. Une sonde — la page qui demande, sans renvoyer ses fichiers —
	 * apprend aussi que rien n'est arrivé.
	 */
	private function reponse_deja_connue() {
		$this->oublier_la_page();
		$page = self::page_postee();
		if ( '' === $page || ! $this->en_arriere_plan() ) {
			return;
		}
		$this->page_envoyee = $page;
		$etat = self::etat_de_la_page( $page );
		if ( null !== $etat && '' !== $etat['adresse'] ) {
			$this->repondre_en_arriere_plan( array( 'redirect' => self::adresse_sure( $etat['adresse'] ) ) );
		}
		if ( null !== $etat && time() - $etat['ts'] < self::ATTENTE_D_UN_ENVOI ) {
			$this->repondre_en_arriere_plan( array( 'attendre' => 5 ) );
		}
		if ( null !== $etat ) {
			// Un envoi mort en route, sans notice inscrite : la page peut
			// renvoyer. Effacé seulement s'il n'a pas changé entre-temps.
			global $wpdb;
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				self::cle_de_la_page( $page ), $etat['valeur'] ) );
		}
		if ( isset( $_POST['na_sonde'] ) ) {
			$this->repondre_en_arriere_plan( array( 'absente' => true ) );
		}
	}

	/**
	 * Retient la page, une fois les contrôles passés, par une écriture qui
	 * ne réussit qu'une fois : deux renvois de la même page arrivés ensemble
	 * ne déposent pas deux notices. Le second apprend que le premier est en
	 * route. Les jetons de plus d'un jour s'effacent au passage.
	 */
	private function reserver_la_page() {
		if ( '' === $this->page_envoyee ) {
			return;
		}
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(SUBSTRING_INDEX(option_value, '|', 1) AS UNSIGNED) < %d",
			$wpdb->esc_like( 'na_page_' ) . '%', time() - DAY_IN_SECONDS ) );
		$pris = $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
			self::cle_de_la_page( $this->page_envoyee ), time() . '|' ) );
		if ( 1 !== (int) $pris ) {
			$this->repondre_en_arriere_plan( array( 'attendre' => 5 ) );
		}
		$this->page_reservee = true;
	}

	/** Chaque requête part sans page retenue. */
	private function oublier_la_page() {
		$this->page_envoyee  = '';
		$this->page_reservee = false;
	}

	/** Ce que la notice est devenue, pour la page qui renverrait. */
	private function retenir_la_page( $adresse ) {
		if ( ! $this->page_reservee ) {
			return;
		}
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s",
			time() . '|' . (string) $adresse, self::cle_de_la_page( $this->page_envoyee ) ) );
	}

	/** Rien n'est reçu : la page pourra renvoyer. */
	private function liberer_la_page() {
		if ( ! $this->page_reservee ) {
			return;
		}
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s",
			self::cle_de_la_page( $this->page_envoyee ) ) );
		$this->page_reservee = false;
	}

	/** Une adresse de ce site, ou l'accueil : la page s'y rendra d'elle-même. */
	private static function adresse_sure( $adresse ) {
		return wp_validate_redirect( (string) $adresse, home_url( '/' ) );
	}

	/**
	 * La réponse courte que la page lit. L'action laisse les essais la lire
	 * avant la sortie.
	 */
	private function repondre_en_arriere_plan( $donnees ) {
		do_action( 'notice_archeomed_reponse_en_arriere_plan', $donnees );
		if ( ! headers_sent() ) {
			nocache_headers();
			status_header( 200 );
			header( 'Content-Type: application/json; charset=utf-8' );
		}
		echo wp_json_encode( $donnees );
		exit;
	}
	/**
	 * L'adresse du visiteur.
	 *
	 * REMOTE_ADDR, et rien d'autre, sauf derrière un relais déclaré : sans
	 * liste blanche, lire « X-Forwarded-For » laisserait n'importe qui choisir
	 * son adresse et contourner les plafonds. Derrière un frontal, tous les
	 * chercheurs partagent pourtant la sienne et se compteraient comme un seul.
	 * Les relais de confiance se déclarent donc dans wp-config.php :
	 *     define( 'NA_PROXYS', '10.0.0.0/8, 192.168.1.5' );
	 * (adresses ou CIDR, IPv4 ou IPv6). L'en-tête n'est lu que si la requête
	 * vient de l'un d'eux, et l'on remonte la chaîne jusqu'à la première
	 * adresse qui n'en est pas un.
	 */
	private function get_client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '0.0.0.0';
		$ip = filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
		$relais = defined( 'NA_PROXYS' ) ? array_filter( array_map( 'trim', explode( ',', (string) NA_PROXYS ) ) ) : array();
		if ( empty( $relais ) || ! self::ip_dans_la_liste( $ip, $relais ) || empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			return $ip;
		}
		$chaine = array_reverse( array_map( 'trim', explode( ',', (string) wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) );
		foreach ( $chaine as $candidate ) {
			if ( ! filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $ip;   // chaîne illisible : l'adresse du relais, par prudence
			}
			if ( ! self::ip_dans_la_liste( $candidate, $relais ) ) {
				return $candidate;
			}
		}
		return $ip;
	}

	/** Une adresse est-elle dans la liste d'adresses et de blocs CIDR ? */
	public static function ip_dans_la_liste( $ip, $liste ) {
		$binaire = @inet_pton( $ip );
		if ( false === $binaire ) {
			return false;
		}
		foreach ( $liste as $entree ) {
			$morceaux = explode( '/', $entree, 2 );
			$reseau   = @inet_pton( $morceaux[0] );
			if ( false === $reseau || strlen( $reseau ) !== strlen( $binaire ) ) {
				continue;
			}
			$bits = isset( $morceaux[1] ) && ctype_digit( $morceaux[1] ) ? min( (int) $morceaux[1], 8 * strlen( $reseau ) ) : 8 * strlen( $reseau );
			$octets = intdiv( $bits, 8 );
			if ( substr( $binaire, 0, $octets ) !== substr( $reseau, 0, $octets ) ) {
				continue;
			}
			$reste = $bits % 8;
			if ( 0 === $reste ) {
				return true;
			}
			$masque = ( 0xFF << ( 8 - $reste ) ) & 0xFF;
			if ( ( ord( $binaire[ $octets ] ) & $masque ) === ( ord( $reseau[ $octets ] ) & $masque ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Le jeton du formulaire, valable de 24 à 48 heures pour ce seul
	 * formulaire (le défaut de WordPress est de 12 à 24 heures, trop court
	 * pour qui laisse sa page ouverte la nuit : critère 13.1 du RGAA).
	 */
	private function verifier_le_jeton( $jeton, $anonyme_admis = false ) {
		$jeton = is_string( $jeton ) ? $jeton : '';
		$duree = function () { return 2 * DAY_IN_SECONDS; };
		add_filter( 'nonce_life', $duree );
		$ok = wp_verify_nonce( $jeton, 'notice_archeomed_submit' );
		// WordPress lie le jeton à la session : une page ouverte sans être
		// connecté, puis envoyée après s'être connecté dans un autre onglet,
		// était refusée comme expirée. Hors correction par la rédaction, le
		// jeton d'un visiteur anonyme vaut pour tous, puisque n'importe qui
		// l'obtient en affichant la page.
		if ( ! $ok && $anonyme_admis && is_user_logged_in() ) {
			$ok = self::jeton_anonyme_valable( $jeton );
		}
		remove_filter( 'nonce_life', $duree );
		// Une page servie avant cette durée (un onglet resté ouvert pendant la
		// mise à jour) porte un jeton de durée ordinaire : on l'accepte encore,
		// pour ne pas refuser des notices que leur auteur vient d'écrire.
		if ( ! $ok ) {
			$ok = wp_verify_nonce( $jeton, 'notice_archeomed_submit' );
		}
		return $ok;
	}

	/**
	 * Le jeton est-il celui qu'aurait reçu un visiteur anonyme ?
	 *
	 * Même calcul que wp_verify_nonce(), pour l'identifiant zéro et une
	 * session vide : l'heure présente ou la précédente.
	 */
	private static function jeton_anonyme_valable( $jeton ) {
		if ( '' === $jeton ) {
			return false;
		}
		$action = 'notice_archeomed_submit';
		$uid    = (int) apply_filters( 'nonce_user_logged_out', 0, $action );
		$tic    = wp_nonce_tick( $action );
		foreach ( array( $tic, $tic - 1 ) as $i ) {
			$attendu = substr( wp_hash( $i . '|' . $action . '|' . $uid . '|', 'nonce' ), -12, 10 );
			if ( hash_equals( $attendu, $jeton ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Garde trace des derniers refus, pour le diagnostic.
	 *
	 * Le premier essai en 3.70 a reçu « la page a expiré » sans qu'on sache
	 * laquelle des quatre causes jouait : elle n'était écrite qu'au journal
	 * PHP, que l'administrateur du site ne lit pas. On garde les vingt
	 * derniers, sans adresse ni contenu : l'heure, la raison, la cause, l'âge
	 * de la page et si la personne était connectée.
	 */
	private function noter_le_refus( $raison, $champs = array() ) {
		$ts    = isset( $_POST['na_ts'] ) && is_string( $_POST['na_ts'] ) ? absint( $_POST['na_ts'] ) : 0;
		$cause = $this->cause_du_refus;
		if ( '' === $cause && ! empty( $champs ) ) {
			$cause = implode( ', ', array_map( 'sanitize_key', (array) $champs ) );
		}
		$refus = get_option( 'na_derniers_refus', array() );
		$refus = is_array( $refus ) ? $refus : array();
		array_unshift( $refus, array(
			'quand'    => time(),
			'raison'   => sanitize_key( $raison ),
			'cause'    => $cause,
			'age'      => $ts > 0 ? time() - $ts : null,
			'connecte' => is_user_logged_in(),
		) );
		update_option( 'na_derniers_refus', array_slice( $refus, 0, 20 ), false );
	}

	/** Les derniers refus, du plus récent au plus ancien. */
	public static function derniers_refus() {
		$refus = get_option( 'na_derniers_refus', array() );
		return is_array( $refus ) ? $refus : array();
	}

	/** Le champ du jeton, de la même durée que sa vérification. */
	private function champ_du_jeton() {
		$duree = function () { return 2 * DAY_IN_SECONDS; };
		add_filter( 'nonce_life', $duree );
		$champ = wp_nonce_field( 'notice_archeomed_submit', 'notice_archeomed_nonce', true, false );
		remove_filter( 'nonce_life', $duree );
		return $champ;
	}

	/**
	 * La page du formulaire ne doit jamais être servie d'un cache : elle porte
	 * un jeton, une heure et un défi propres à chaque affichage.
	 */
	public function ne_pas_mettre_en_cache() {
		if ( ! $this->page_porte_le_formulaire() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
	}

	/**
	 * Les compteurs horaires se tiennent par heure d'horloge.
	 *
	 * Chaque incrément reposait le compteur pour une heure : l'échéance
	 * repartait à chaque dépôt, et un compteur « par heure » comptait en fait
	 * depuis la dernière heure creuse. Un jour de clôture, un dépôt toutes
	 * les cinquante minutes suffisait à le mener au plafond, et les auteurs
	 * suivants étaient refusés une heure entière pour dix dépôts à l'heure.
	 * La clé porte désormais l'heure en cours : le compteur repart de zéro à
	 * chaque heure pleine, et l'ancien s'efface seul.
	 */
	private static function cle_horaire( $base ) {
		return $base . '_' . gmdate( 'YmdH' );
	}

	/** La valeur d'un compteur horaire, pour l'heure en cours. */
	private static function lire_le_compteur( $base ) {
		return (int) get_transient( self::cle_horaire( $base ) );
	}

	/** Ajoute à un compteur horaire, sans repousser son heure. */
	private static function ajouter_au_compteur( $base, $combien = 1 ) {
		$cle = self::cle_horaire( $base );
		set_transient( $cle, (int) get_transient( $cle ) + (int) $combien, 2 * HOUR_IN_SECONDS );
	}

	/** Le plafond de toute la revue : lu avant, incrémenté après un dépôt abouti. */
	private function check_global_limit() {
		return self::lire_le_compteur( 'na_envois_global' ) < self::MAX_SENDS_GLOBAL_PER_HOUR;
	}

	private function record_global_send() {
		if ( ! $this->essai ) {
			self::ajouter_au_compteur( 'na_envois_global' );
		}
		$this->consommer_le_sceau();
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
			// Le formulaire ajoute le puzzle tant que dure la panne : le
			// moment où elle a commencé se garde, renouvelé à chaque échec.
			$depuis = get_transient( 'na_turnstile_injoignable' );
			set_transient( 'na_turnstile_injoignable', false !== $depuis ? (int) $depuis : time(), DAY_IN_SECONDS );
			// Ouvert par défaut : derrière un proxy qui bloque Cloudflare, fermer
			// perdrait toutes les notices. Une plateforme qui a ouvert la
			// sortie le ferme (« add_filter( 'notice_archeomed_turnstile_ouvert', '__return_false' ) »).
			return (bool) apply_filters( 'notice_archeomed_turnstile_ouvert', self::TURNSTILE_FAIL_OPEN_ON_NETWORK_ERROR );
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
		$key = 'na_pactols_ip_' . md5( $this->get_client_ip() );
		if ( self::lire_le_compteur( $key ) >= self::MAX_LOOKUPS_PER_HOUR ) {
			return false;
		}
		self::ajouter_au_compteur( $key );
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
		$key = 'na_essais_ip_' . md5( $this->get_client_ip() );
		if ( self::lire_le_compteur( $key ) >= self::MAX_ATTEMPTS_PER_HOUR ) {
			return false;
		}
		self::ajouter_au_compteur( $key );
		return true;
	}

	/**
	 * Le quota d'envois, lui, ne compte que ce qui est parti. On le lit avant
	 * d'envoyer ; on ne l'incrémente qu'après.
	 */
	private function check_send_limit( $email ) {
		foreach ( $this->send_limit_keys( $email ) as $key => $plafond ) {
			if ( self::lire_le_compteur( $key ) >= $plafond ) {
				return false;
			}
		}
		return true;
	}

	private function record_send( $email ) {
		foreach ( array_keys( $this->send_limit_keys( $email ) ) as $key ) {
			self::ajouter_au_compteur( $key );
		}
	}

	/**
	 * Les compteurs d'envois, chacun avec son plafond.
	 *
	 * Celui de l'adresse électronique est le seul qui désigne vraiment une
	 * personne ; celui de l'IP se contente d'arrêter une machine emballée.
	 */
	private function send_limit_keys( $email ) {
		$keys = array( 'na_envois_ip_' . md5( $this->get_client_ip() )
			=> max( 1, (int) apply_filters( 'notice_archeomed_plafond_par_poste', self::MAX_SENDS_PER_IP ) ) );
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
	 * Les JPEG qu'un navigateur a tirés des TIFF et des PDF de l'auteur, par rang
	 * de figure : « na_conv_hr_N » (la haute définition) et « na_conv_br_N » (la
	 * basse, pour un TIFF comme pour un PDF). Ils n'existent que si le serveur
	 * n'a pas Imagick pour lire le fichier lui-même.
	 *
	 * Rien ici n'arrête le dépôt. Le navigateur a déjà tout vérifié, et une
	 * conversion absente ou douteuse laisse simplement la figure sans version
	 * allégée, comme avant : on l'écarte et on le dit au journal.
	 *
	 * Rend « rang => array( « hr » => fichier, « br » => fichier ) ».
	 */
	private function recevoir_les_conversions( $attachments ) {
		$recues = array();
		$dossier = trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp';
		wp_mkdir_p( $dossier );
		foreach ( array_values( (array) $attachments ) as $i => $original ) {
			$rang = $i + 1;
			if ( $rang > self::MAX_FILES || ! in_array( strtolower( pathinfo( (string) $original, PATHINFO_EXTENSION ) ), array( 'tif', 'tiff', 'pdf' ), true ) ) {
				continue;
			}
			foreach ( array( 'hr', 'br' ) as $famille ) {
				$cible = $this->recevoir_une_conversion( 'na_conv_' . $famille . '_' . $rang, $famille, (string) $rang );
				if ( '' !== $cible ) {
					$recues[ $rang ][ $famille ] = $cible;
				}
			}
		}
		return $recues;
	}

	/**
	 * Une conversion postée sous « $cle », si elle est le JPEG attendu : son
	 * chemin au répertoire temporaire, ou une chaîne vide. « $etiquette » la
	 * nomme dans le fichier et le journal — le rang d'une figure neuve, ou
	 * « garde-K » pour le fichier qui remplace une figure gardée.
	 */
	private function recevoir_une_conversion( $cle, $famille, $etiquette ) {
		if ( empty( $_FILES[ $cle ]['tmp_name'] ) || ! is_string( $_FILES[ $cle ]['tmp_name'] )
			|| ! isset( $_FILES[ $cle ]['error'] ) || ! is_scalar( $_FILES[ $cle ]['error'] )
			|| UPLOAD_ERR_OK !== (int) $_FILES[ $cle ]['error'] || ! is_uploaded_file( $_FILES[ $cle ]['tmp_name'] ) ) {
			return '';
		}
		if ( ! self::jpeg_converti_acceptable( $_FILES[ $cle ]['tmp_name'], $famille ) ) {
			error_log( 'Notice Archeomed: conversion ' . $famille . ' de la figure ' . $etiquette . ' écartée (pas un JPEG acceptable).' );
			return '';
		}
		$dossier = trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp';
		wp_mkdir_p( $dossier );
		$cible = trailingslashit( $dossier ) . 'notice-archeomed-conv-' . $famille . '-' . $etiquette . '-'
			. wp_generate_password( 12, false, false ) . '.jpg';
		return move_uploaded_file( $_FILES[ $cle ]['tmp_name'], $cible ) ? $cible : '';
	}

	/**
	 * Un fichier est-il bien le JPEG qu'on attend d'une conversion ?
	 *
	 * Reconnu par son contenu et non par son nom, lisible par GD (donc entier),
	 * et de mesures raisonnables : la haute définition ne dépasse pas la
	 * mesure d'une planche, la basse son plafond de poids et de côté, à
	 * quelques pour cent près.
	 */
	public static function jpeg_converti_acceptable( $chemin, $famille ) {
		if ( ! is_readable( $chemin ) ) {
			return false;
		}
		$octets = (int) filesize( $chemin );
		$mesure = @getimagesize( $chemin );
		if ( ! is_array( $mesure ) || IMAGETYPE_JPEG !== $mesure[2] ) {
			return false;
		}
		$cote = max( (int) $mesure[0], (int) $mesure[1] );
		if ( 'hr' === $famille ) {
			$limite_octets = 12 * MB_IN_BYTES;
			$limite_cote   = 4000;
		} else {
			$limite_octets = (int) round( (int) Notice_Archeomed_Settings::get( 'br_poids' ) * 1024 * 1.5 );
			$limite_cote   = (int) ceil( (int) Notice_Archeomed_Settings::get( 'br_largeur' ) * 1.05 );
		}
		if ( $octets < 200 || $octets > $limite_octets || $cote < 1 || $cote > $limite_cote ) {
			return false;
		}
		// Entier : un JPEG tiré d'un canevas finit par sa marque de fin, et
		// un fichier tronqué ne l'a pas.
		$fin = @file_get_contents( $chemin, false, null, $octets - 2, 2 );
		if ( "\xFF\xD9" !== $fin ) {
			return false;
		}
		// Lisible : un JPEG forgé ne passe pas. GD le déplie entier ; quand la
		// mémoire n'y suffirait pas, la marque de fin tient lieu d'épreuve.
		if ( function_exists( 'imagecreatefromjpeg' ) && Notice_Archeomed_Paquet::memoire_suffit( $mesure[0], $mesure[1] ) ) {
			$image = @imagecreatefromjpeg( $chemin );
			if ( ! $image ) {
				return false;
			}
			imagedestroy( $image );
		}
		return true;
	}

	/**
	 * Range les conversions à demeure, près des illustrations, et les retient
	 * par le rang de leur figure.
	 */
	private function archiver_les_conversions( $id, $conversions ) {
		$dossier = $this->dossier_des_illustrations();
		$gardees = array();
		foreach ( (array) $conversions as $rang => $fichiers ) {
			foreach ( (array) $fichiers as $famille => $fichier ) {
				if ( ! file_exists( $fichier ) ) {
					continue;
				}
				$cible = '' === $dossier ? $fichier : $dossier . (int) $id . '-' . basename( $fichier );
				$gardees[ (int) $rang ][ (string) $famille ] = ( $cible === $fichier || ! @rename( $fichier, $cible ) ) ? $fichier : $cible;
			}
		}
		if ( $id && ! empty( $gardees ) ) {
			update_post_meta( $id, '_na_conversions', $gardees );
		}
		return $gardees;
	}

	/** Les conversions gardées d'une notice : « rang => array( hr, br ) ». */
	public static function conversions_rangees( $id ) {
		$gardees = get_post_meta( (int) $id, '_na_conversions', true );
		return is_array( $gardees ) ? $gardees : array();
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
		// Une figure envoyée à part laisse la première entrée vide : on ne
		// juge plus sur elle seule.
		if ( empty( $this->figures_a_part )
			&& ( empty( $_FILES['illustrations'] ) || empty( $_FILES['illustrations']['name'][0] ) ) ) {
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
			// La figure envoyée à part : un témoin tient sa place, que le
			// serveur écrit lui-même et qui ne passe donc par aucun contrôle
			// de format.
			if ( isset( $this->figures_a_part[ $i ] ) ) {
				$dest = trailingslashit( $tmp_dir ) . 'notice-archeomed-a-part-' . wp_generate_password( 32, false, false ) . '.txt';
				if ( false === @file_put_contents( $dest, self::texte_du_temoin( $this->figures_a_part[ $i ] ) ) ) {
					$upload_error = 'upload_move';
					return $attachments;
				}
				$attachments[] = $dest;
				continue;
			}
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

	/**
	 * Ce qu'on fait d'une figure gardée de rang $k : « garder », « remplacer »
	 * ou « retirer ». Un formulaire qui n'en dit rien la garde : on ne perd
	 * pas une figure parce qu'un bouton n'est pas arrivé.
	 */
	private function action_de_la_garde( $k ) {
		$action = $this->garde_postee( 'action', $k );
		if ( null === $action ) {
			return 'garder';
		}
		$action = sanitize_key( $action );
		return in_array( $action, array( 'remplacer', 'retirer' ), true ) ? $action : 'garder';
	}

	/** Un fichier a-t-il été choisi pour le champ « $cle » ? */
	private function fichier_poste( $cle ) {
		return isset( $_FILES[ $cle ]['error'] ) && is_scalar( $_FILES[ $cle ]['error'] )
			&& UPLOAD_ERR_NO_FILE !== (int) $_FILES[ $cle ]['error'];
	}

	/**
	 * Reçoit le fichier unique du champ « $cle » au répertoire temporaire,
	 * avec les mêmes contrôles qu'une illustration : son chemin, ou une
	 * chaîne vide si rien n'a été choisi.
	 *
	 * Un refus pose « $erreur » dans les mots de « handle_uploads », pour que
	 * l'auteur lise la même raison que pour une figure neuve.
	 */
	private function recevoir_un_fichier( $cle, $extensions, $plafond, $prefixe, &$erreur ) {
		$erreur = '';
		if ( ! $this->fichier_poste( $cle ) ) {
			return '';
		}
		$f = $_FILES[ $cle ];
		if ( ! isset( $f['name'], $f['tmp_name'] ) || ! is_string( $f['name'] ) || ! is_string( $f['tmp_name'] ) ) {
			$erreur = 'upload_form';
			return '';
		}
		$code = (int) $f['error'];
		if ( UPLOAD_ERR_OK !== $code ) {
			if ( UPLOAD_ERR_INI_SIZE === $code || UPLOAD_ERR_FORM_SIZE === $code ) {
				$erreur = 'upload_ini_size';
			} elseif ( UPLOAD_ERR_PARTIAL === $code ) {
				$erreur = 'upload_partial';
			} else {
				$erreur = 'upload_error';
			}
			return '';
		}
		if ( isset( $f['size'] ) && (int) $f['size'] > $plafond ) {
			$erreur = 'upload_total_size';
			return '';
		}
		$nom = sanitize_file_name( $f['name'] );
		$ext = strtolower( pathinfo( $nom, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $extensions, true ) ) {
			$erreur = 'upload_ext';
			return '';
		}
		if ( ! $this->mime_is_allowed( $f['tmp_name'], $nom, $ext ) ) {
			$erreur = 'upload_mime';
			return '';
		}
		$dossier = trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp';
		wp_mkdir_p( $dossier );
		$cible = trailingslashit( $dossier ) . 'notice-archeomed-' . $prefixe . '-'
			. wp_generate_password( 32, false, false ) . '.' . $ext;
		if ( ! move_uploaded_file( $f['tmp_name'], $cible ) ) {
			$erreur = 'upload_move';
			return '';
		}
		return $cible;
	}

	/**
	 * Range un fichier reçu dans le dossier de dépôt, au nom de la notice.
	 * Sans dossier, ou si le renommage échoue, il reste au répertoire
	 * temporaire sous son nom « garde », que la purge laisse un mois.
	 */
	private function ranger_dans_le_depot( $id, $fichier ) {
		$dossier = $this->dossier_des_illustrations();
		if ( '' !== $dossier ) {
			$cible = $dossier . (int) $id . '-' . basename( $fichier );
			if ( @rename( $fichier, $cible ) ) {
				return $cible;
			}
		}
		$mis = $this->mettre_a_labri( array( $fichier ) );
		return ! empty( $mis ) ? $mis[0] : $fichier;
	}

	/**
	 * Ce qu'une correction a changé, en mots que la rédaction lit dans
	 * l'objet de son courriel et dans l'historique de la fiche.
	 *
	 * Une notice corrigée lui revenait entière, et rien ne disait où
	 * regarder : elle relisait trois pages pour un mot-clé ajouté.
	 */
	private function champs_changes( $avant, $d, $figures_changees ) {
		$groupes = array(
			'la rubrique'                 => array( 'rubrique_principale', 'rubrique_matiere', 'renvoi_1', 'renvoi_2', 'renvoi_matiere' ),
			'les lieux'                   => array( 'lieux', 'commune', 'commune_ark' ),
			'la précision géographique'   => array( 'departement' ),
			'le lieu-dit'                 => array( 'lieu_dit', 'lieu_dit_forme' ),
			'l’année'                     => array( 'annee' ),
			'le numéro d’arrêté'          => array( 'num_autorisation' ),
			'le code Patriarche'          => array( 'id_patriarche' ),
			'l’opérateur'                 => array( 'organismes', 'organisme' ),
			'le responsable'              => array( 'resp_prenom', 'resp_nom', 'resp_email', 'resp_inst' ),
			'le co-responsable'           => array( 'coresp_prenom', 'coresp_nom', 'coresp_email', 'coresp_inst' ),
			'le co-auteur'                => array( 'coauteur_prenom', 'coauteur_nom', 'coauteur_email', 'coauteur_inst' ),
			'la personne qui a déposé'    => array( 'deposant_prenom', 'deposant_nom', 'deposant_email' ),
			'la nature'                   => array( 'nature', 'nature_items' ),
			'les périodes'                => array( 'pactols_periods_items' ),
			'les mots-clés'               => array( 'pactols_subjects_items' ),
			'les autres lieux'            => array( 'pactols_places_items' ),
			'le texte'                    => array( 'texte_notice' ),
			'le lien du rapport'          => array( 'rapport_lien' ),
			'le lien du jeu de données'   => array( 'donnees_lien' ),
			'le lien des originaux'       => array( 'originaux_lien', 'originaux_jusqua' ),
			'le message à la rédaction'   => array( 'commentaires' ),
		);
		$changes = array();
		foreach ( $groupes as $libelle => $cles ) {
			foreach ( $cles as $cle ) {
				// Une clef que l'ancienne saisie ne portait pas — une notice
				// d'avant le champ — n'a pas « changé » : elle est apparue.
				// Sauf la sous-rubrique d'un renvoi vers V, que nul dépôt ne
				// porte : la poser la première fois est déjà la changer.
				if ( ! array_key_exists( $cle, (array) $avant ) && 'renvoi_matiere' !== $cle ) {
					continue;
				}
				$vieux = isset( $avant[ $cle ] ) ? $avant[ $cle ] : '';
				$neuf  = isset( $d[ $cle ] ) ? $d[ $cle ] : '';
				if ( wp_json_encode( $vieux ) !== wp_json_encode( $neuf ) ) {
					$changes[] = $libelle;
					break;
				}
			}
		}
		if ( $figures_changees ) {
			$changes[] = 'les figures';
		}
		return $changes;
	}

	/** Les textes d'une liste de figures, dans un ordre fixe, pour les comparer. */
	private static function textes_des_figures( $items ) {
		$textes = array();
		foreach ( (array) $items as $item ) {
			$ligne = array();
			// Une mise en forme changée est un changement de la figure.
			foreach ( array( 'titre', 'legende', 'credits', 'alt', 'description', 'type', 'titre_forme', 'legende_forme', 'description_forme' ) as $cle ) {
				$ligne[] = isset( $item[ $cle ] ) && is_scalar( $item[ $cle ] ) ? (string) $item[ $cle ] : '';
			}
			$ligne[]  = ! empty( $item['autorisation'] );
			// Un lien d'envoi à part remplacé est un changement de la figure.
			$ligne[]  = (array) self::figure_a_part( $item );
			$textes[] = $ligne;
		}
		return $textes;
	}

	/**
	 * Corrige une notice reçue avec ce que le formulaire vient de poster, et
	 * ne revient pas : la page suivante est la fiche, pour la rédaction, ou
	 * l'accusé, pour l'auteur.
	 *
	 * La notice garde sa référence et sa fiche. Ses figures se gardent, se
	 * remplacent ou se retirent une à une, et les nouvelles s'ajoutent à la
	 * suite. Tout ce qui peut refuser — un fichier, le nombre, le poids, une
	 * autre correction passée entre-temps — refuse avant qu'on écrive quoi
	 * que ce soit : une correction refusée laisse la notice telle qu'elle
	 * était.
	 *
	 * Deux corrections de la même notice ne s'écrasent pas : la version lue
	 * par la page ne passe à la suivante qu'une fois, et la seconde est
	 * renvoyée au formulaire. Deux notices différentes, elles, ne se croisent
	 * jamais : rien ici n'est commun à deux fiches.
	 */
	private function corriger_en_place( $contexte, $d ) {
		$id        = (int) $contexte['id'];
		$redaction = $contexte['par_la_redaction'] > 0;
		// Une page ouverte sur une version dépassée ne se juge pas sur la
		// notice d'aujourd'hui : ses figures ne sont plus celles qu'elle
		// montrait, et elle était refusée pour « trop de fichiers » quand la
		// correction passée avant elle en avait ajouté un. La version se relit
		// donc d'abord ; l'écriture conditionnelle, plus bas, tranche encore
		// entre deux corrections parties ensemble.
		$lue = (int) $contexte['version'];
		if ( $lue < 1 || $this->file()->version_de( $id ) !== $lue ) {
			$this->conflit = true;
			error_log( 'Notice Archeomed: correction de la notice ' . $id . ' refusée, version ' . $lue . ' dépassée.' );
			$this->redirect_result( false, 'conflit' );
		}
		$avant     = self::figures_lisibles( get_post_meta( $id, '_na_donnees', true ) );
		$avant     = is_array( $avant ) ? $avant : array();
		$gardees   = $this->figures_gardees_de( $id, $avant );
		$items_anc = array();
		foreach ( isset( $avant['illustrations'] ) ? (array) $avant['illustrations'] : array() as $item ) {
			if ( is_array( $item ) && ! empty( $item['rang'] ) ) {
				$items_anc[ (int) $item['rang'] ] = $item;
			}
		}

		// ── Ce qui arrive : tout est reçu au répertoire temporaire ─────────
		$recus  = array();
		$erreur = '';
		$neufs  = $this->handle_uploads( $erreur );
		if ( '' !== $erreur ) {
			$this->nettoyer( $neufs );
			error_log( 'Notice Archeomed: correction, upload error: ' . $erreur );
			$this->redirect_result( false, $this->raison_du_televersement( $erreur ) );
		}
		$recus       = array_merge( $recus, $neufs );
		$conv_neuves = $this->recevoir_les_conversions( $neufs );
		foreach ( $conv_neuves as $fichiers ) {
			$recus = array_merge( $recus, array_values( $fichiers ) );
		}
		$auto_neuves = $this->recevoir_les_autorisations( $erreur );
		if ( '' !== $erreur ) {
			$this->nettoyer( $recus );
			error_log( 'Notice Archeomed: correction, autorisation refusée : ' . $erreur );
			$this->redirect_result( false, 'autorisation' );
		}
		$recus = array_merge( $recus, array_values( $auto_neuves ) );

		// Les figures déjà déposées, dans l'ordre de la notice.
		$plan  = array();
		$poids = 0;
		foreach ( $neufs as $fichier ) {
			$poids += (int) @filesize( $fichier );
		}
		foreach ( $gardees as $figure ) {
			$k      = (int) $figure['k'];
			$action = $this->action_de_la_garde( $k );
			if ( 'retirer' === $action ) {
				continue;
			}
			$remplacant = '';
			$conv       = array();
			if ( 'remplacer' === $action ) {
				$remplacant = $this->recevoir_un_fichier( 'garde_fichier_' . $k, self::ALLOWED_EXT,
					self::MAX_TOTAL_FILESIZE, 'remplacement', $erreur );
				if ( '' !== $erreur ) {
					$this->nettoyer( $recus );
					error_log( 'Notice Archeomed: correction, remplacement de la fig. ' . $k . ' refusé : ' . $erreur );
					$this->redirect_result( false, $this->raison_du_televersement( $erreur ) );
				}
				if ( '' === $remplacant ) {
					// « Remplacer » sans fichier : la figure reste comme elle était.
					$action = 'garder';
				} else {
					$recus[] = $remplacant;
					$poids  += (int) @filesize( $remplacant );
					if ( in_array( strtolower( pathinfo( $remplacant, PATHINFO_EXTENSION ) ), array( 'tif', 'tiff', 'pdf' ), true ) ) {
						foreach ( array( 'hr', 'br' ) as $famille ) {
							$cible = $this->recevoir_une_conversion( 'na_conv_garde_' . $famille . '_' . $k, $famille, 'garde-' . $k );
							if ( '' !== $cible ) {
								$conv[ $famille ] = $cible;
								$recus[]          = $cible;
							}
						}
					}
				}
			}
			$auto = $this->recevoir_un_fichier( 'garde_autorisation_' . $k, self::AUTORISATION_EXT,
				self::MAX_AUTORISATION, 'autorisation-garde-' . $k, $erreur );
			if ( '' !== $erreur ) {
				$this->nettoyer( $recus );
				error_log( 'Notice Archeomed: correction, autorisation de la fig. ' . $k . ' refusée : ' . $erreur );
				$this->redirect_result( false, 'autorisation' );
			}
			if ( '' !== $auto ) {
				$recus[] = $auto;
			}
			$retirer_auto = '' === $auto && isset( $_POST['garde_autorisation_retirer'] )
				&& is_array( $_POST['garde_autorisation_retirer'] ) && isset( $_POST['garde_autorisation_retirer'][ $k ] )
				&& is_string( $_POST['garde_autorisation_retirer'][ $k ] )
				&& '' !== $_POST['garde_autorisation_retirer'][ $k ] && '0' !== $_POST['garde_autorisation_retirer'][ $k ];
			$plan[] = array(
				'figure'       => $figure,
				'remplacant'   => $remplacant,
				'conv'         => $conv,
				'auto'         => $auto,
				'retirer_auto' => $retirer_auto,
			);
		}

		// ── Les bornes du dépôt valent pour la notice corrigée ─────────────
		if ( count( $plan ) + count( $neufs ) > self::MAX_FILES ) {
			$this->nettoyer( $recus );
			$this->redirect_result( false, 'fichiers_nombre' );
		}
		if ( $poids > self::MAX_TOTAL_FILESIZE ) {
			$this->nettoyer( $recus );
			$this->redirect_result( false, 'fichiers_total' );
		}

		// ── La version : une seule correction passe ────────────────────────
		if ( ! $this->file()->changer_de_version( $id, $lue ) ) {
			$this->conflit = true;
			$this->nettoyer( $recus );
			error_log( 'Notice Archeomed: correction de la notice ' . $id . ' refusée, version ' . $lue . ' dépassée.' );
			$this->redirect_result( false, 'conflit' );
		}
		// La file ne doit pas envoyer la notice pendant qu'on la récrit, ni la
		// récrire pendant qu'elle part.
		wp_cache_delete( $id, 'post_meta' );
		if ( (int) get_post_meta( $id, '_na_preparation', true ) > time() - Notice_Archeomed_File::ABANDON ) {
			$this->file()->rendre_la_version( $id, $lue );
			$this->nettoyer( $recus );
			$this->redirect_result( false, 'occupee' );
		}
		update_post_meta( $id, '_na_preparation', time() );
		wp_cache_delete( $id, 'post_meta' );
		$etat_avant = (string) get_post_meta( $id, '_na_etat', true );
		if ( 'en_cours' === $etat_avant
			&& (int) get_post_meta( $id, '_na_prise', true ) > time() - Notice_Archeomed_File::ABANDON ) {
			$this->file()->rendre_la_version( $id, $lue );
			delete_post_meta( $id, '_na_preparation' );
			$this->nettoyer( $recus );
			$this->redirect_result( false, 'occupee' );
		}

		// Les textes n'ont pas pu changer — la version y veille —, mais les
		// fichiers si : l'envoi range ceux d'une ancienne notice, la tâche
		// des aperçus en ajoute, sans changer de version. On ne les lit que
		// maintenant que la notice est à nous, et l'on range d'abord ceux
		// qui attendaient encore.
		$this->ranger_les_figures_d_avant( $id );
		wp_cache_delete( $id, 'post_meta' );
		$originaux = self::illustrations_rangees( $id );
		$conv_anc  = self::conversions_rangees( $id );
		$ap_anc    = $this->apercus_de( $id, $avant );
		$auto_anc  = get_post_meta( $id, '_na_autorisations', true );
		$auto_anc  = is_array( $auto_anc ) ? $auto_anc : array();

		// ── Les figures de la notice corrigée ──────────────────────────────
		//
		// Celles qui ont un fichier d'abord, gardées puis nouvelles : le rang
		// d'une figure est celui de son fichier, et la liste des fichiers ne
		// souffre pas de trou. Celles qui n'en ont pas viennent ensuite.
		$avec = array();
		$sans = array();
		foreach ( $plan as $p ) {
			$figure = $p['figure'];
			$k      = (int) $figure['k'];
			$ancien = isset( $items_anc[ $k ] ) ? $items_anc[ $k ] : array();
			unset( $ancien['figure'], $ancien['type_absent'], $ancien['pixels'] );
			$brut = array( 'description' => '' );
			foreach ( array( 'titre', 'legende', 'credits', 'alt' ) as $cle ) {
				$valeur       = $this->garde_postee( $cle, $k );
				$brut[ $cle ] = null !== $valeur ? $valeur : (string) $figure[ $cle ];
			}
			// La mise en forme postée, ou celle que la figure avait : elle se
			// raccorde aux textes corrigés, ou s'oublie.
			foreach ( array_keys( Notice_Archeomed_Forme::CLEFS ) as $cle ) {
				$valeur       = $this->garde_postee( $cle, $k );
				$brut[ $cle ] = null !== $valeur ? $valeur : ( isset( $figure[ $cle ] ) && is_string( $figure[ $cle ] ) ? $figure[ $cle ] : '' );
			}
			$type = $this->garde_postee( 'type', $k );
			$ancien['type'] = Notice_Archeomed_Controles::type_de_figure(
				null !== $type && '' !== $type ? $type : (string) $figure['type'] );
			if ( '' !== $p['remplacant'] && isset( $_FILES[ 'garde_fichier_' . $k ]['name'] )
				&& is_string( $_FILES[ 'garde_fichier_' . $k ]['name'] ) ) {
				$ancien['fichier_depose'] = sanitize_text_field( $_FILES[ 'garde_fichier_' . $k ]['name'] );
			}
			// Une figure envoyée à part que l'auteur remplace par un fichier
			// n'est plus à part ; gardée, elle prend le lien renvoyé.
			if ( '' !== $p['remplacant'] ) {
				unset( $ancien['a_part'] );
			} elseif ( null !== self::figure_a_part( $ancien ) ) {
				$ancien['a_part'] = $this->a_part_de_la_garde( $k, $ancien['a_part'] );
			}
			// Gardée, une figure garde son original, sur le serveur ou rangé
			// sur sDrive : la table de la notice le retrouve d'une version à
			// l'autre.
			$fichier = '' !== $p['remplacant'] ? $p['remplacant']
				: ( isset( $originaux[ $k - 1 ] ) && Notice_Archeomed_Rangement::present( $id, $originaux[ $k - 1 ] ) ? $originaux[ $k - 1 ] : '' );
			if ( '' !== $p['auto'] ) {
				$auto = $p['auto'];
			} elseif ( $p['retirer_auto'] ) {
				$auto = '';
			} else {
				$auto = ! empty( $auto_anc[ $k ] ) ? (string) $auto_anc[ $k ] : '';
			}
			$entree = array(
				'k'        => $k,
				'item'     => $ancien,
				'brut'     => $brut,
				'fichier'  => $fichier,
				'conv'     => '' !== $p['remplacant'] ? $p['conv'] : ( isset( $conv_anc[ $k ] ) ? (array) $conv_anc[ $k ] : array() ),
				'apercu'   => '' === $p['remplacant'] && isset( $ap_anc[ $k ] ) ? $ap_anc[ $k ] : null,
				'auto'     => $auto,
				'drapeau'  => '' !== $p['auto'] ? true
					: ( $p['retirer_auto'] ? false : ( '' !== $auto || ! empty( $ancien['autorisation'] ) ) ),
			);
			if ( '' !== $fichier ) {
				$avec[] = $entree;
			} else {
				$sans[] = $entree;
			}
		}
		$neufs_avec = array();
		$neufs_sans = array();
		foreach ( (array) $d['illustrations'] as $item ) {
			$r       = (int) $item['rang'];
			$fichier = isset( $neufs[ $r - 1 ] ) ? $neufs[ $r - 1 ] : '';
			$entree  = array(
				'k'       => 0,
				'item'    => $item,
				'brut'    => null,
				'fichier' => $fichier,
				'conv'    => isset( $conv_neuves[ $r ] ) ? $conv_neuves[ $r ] : array(),
				'apercu'  => null,
				'auto'    => isset( $auto_neuves[ $r ] ) ? $auto_neuves[ $r ] : '',
				'drapeau' => isset( $auto_neuves[ $r ] ),
			);
			if ( '' !== $fichier ) {
				$neufs_avec[] = $entree;
			} else {
				$neufs_sans[] = $entree;
			}
		}
		$entrees = array_slice( array_merge( $avec, $neufs_avec, $sans, $neufs_sans ), 0, self::MAX_FILES );

		$items    = array();
		$fichiers = array();
		foreach ( $entrees as $i => $e ) {
			$rang = $i + 1;
			$item = $e['item'];
			if ( null !== $e['brut'] ) {
				$item = array_merge( $item, $this->textes_de_figure( $e['brut'], $rang ) );
			} elseif ( (int) $item['rang'] !== $rang ) {
				// Le titre d'une figure neuve a été nettoyé de « Fig. N » pour
				// son rang parmi les neuves ; il le redevient pour le sien.
				$item['titre'] = Notice_Archeomed_Controles::titre_sans_numero( $item['titre'], $rang );
				$item          = self::titre_forme_au_rang( $item, $rang );
			}
			$item['rang']         = $rang;
			$item['autorisation'] = $e['drapeau'];
			$items[]              = $item;
			if ( '' !== $e['fichier'] ) {
				$fichiers[] = $e['fichier'];
			}
		}

		// ── La saisie corrigée, avant tout déplacement de fichier ──────────
		$d['illustrations'] = $items;
		$d['reference']     = ! empty( $avant['reference'] ) ? (string) $avant['reference']
			: (string) get_post_meta( $id, '_na_reference', true );
		$d['remplace']      = isset( $avant['remplace'] ) ? (string) $avant['remplace'] : '';
		// Ce que la saisie portait et que le formulaire ne renvoie pas — le
		// champ libre des anciennes notices, par exemple — se garde.
		foreach ( $avant as $cle => $valeur ) {
			if ( ! array_key_exists( $cle, $d ) && ! in_array( $cle, array( 'avis', 'correction_url' ), true ) ) {
				$d[ $cle ] = $valeur;
			}
		}
		if ( $redaction ) {
			// La rédaction ne touche pas au lien que l'auteur a reçu.
			$d['correction_url'] = isset( $avant['correction_url'] ) ? (string) $avant['correction_url'] : '';
			$vivant              = '';
		} else {
			$vivant = '' !== $contexte['jeton_vivant'] ? $contexte['jeton_vivant']
				: strtolower( wp_generate_password( 12, false, false ) );
			$page   = $this->page_du_formulaire();
			$d['correction_url'] = add_query_arg( 'notice_reprise', $vivant, '' !== $page ? $page : $this->url_du_formulaire() );
		}
		$d         = self::poser_les_pixels( $d, $fichiers );
		$d['avis'] = array_merge( (array) Notice_Archeomed_Controles::avis( $d ), $this->avis_des_coupes() );
		$notice    = $this->build_notice( $d );
		// Ce qui a changé se compte avant qu'un fichier bouge : une figure
		// gardée garde son chemin, une neuve en a un autre.
		$autos_prevues = array();
		foreach ( $entrees as $i => $e ) {
			if ( '' !== $e['auto'] ) {
				$autos_prevues[ $i + 1 ] = $e['auto'];
			}
		}
		$figures_changees = self::textes_des_figures( isset( $avant['illustrations'] ) ? $avant['illustrations'] : array() )
				!== self::textes_des_figures( $items )
			|| array_values( $originaux ) !== $fichiers
			|| $auto_anc !== $autos_prevues;
		$champs = $this->champs_changes( $avant, $d, $figures_changees );

		// ── Les fichiers à leur place ──────────────────────────────────────
		//
		// Un fichier reçu qui n'a trouvé aucune place — une figure au-delà de
		// la troisième — ne doit pas traîner.
		$places = $fichiers;
		foreach ( $entrees as $e ) {
			$places = array_merge( $places, array_values( (array) $e['conv'] ) );
			if ( '' !== $e['auto'] ) {
				$places[] = $e['auto'];
			}
		}
		$this->nettoyer( array_diff( $recus, $places ) );
		$a_ranger = array_flip( $recus );
		$range    = function ( $chemin ) use ( $id, $a_ranger ) {
			return ( '' !== $chemin && isset( $a_ranger[ $chemin ] ) && file_exists( $chemin ) )
				? $this->ranger_dans_le_depot( $id, $chemin ) : $chemin;
		};
		$illustrations = array();
		$conversions   = array();
		$autorisations = array();
		$apercus       = array();
		foreach ( $entrees as $i => $e ) {
			$rang = $i + 1;
			if ( '' !== $e['fichier'] ) {
				$illustrations[] = $range( $e['fichier'] );
			}
			foreach ( (array) $e['conv'] as $famille => $chemin ) {
				$conversions[ $rang ][ (string) $famille ] = $range( (string) $chemin );
			}
			if ( '' !== $e['auto'] ) {
				$autorisations[ $rang ] = $range( $e['auto'] );
			}
			if ( null !== $e['apercu'] && '' !== $e['fichier'] ) {
				$apercus[ $rang ] = $e['apercu'];
			}
		}
		update_post_meta( $id, '_na_illustrations', $illustrations );
		foreach ( array( '_na_conversions' => $conversions, '_na_autorisations' => $autorisations,
			'_na_apercus' => $apercus ) as $meta => $valeur ) {
			if ( empty( $valeur ) ) {
				delete_post_meta( $id, $meta );
			} else {
				update_post_meta( $id, $meta, $valeur );
			}
		}
		update_post_meta( $id, '_na_fichiers', array_merge( $illustrations, array_values( $autorisations ) ) );

		// Une figure remplacée ou ajoutée a sa version allégée tout de suite
		// quand la rédaction corrige, ou quand l'envoi est immédiat. Laissée
		// à la tâche, elle attendait une visite du site, et la relecture
		// disait « Pas d'aperçu » d'un JPEG qu'on venait d'envoyer. Un échec
		// ne coûte pas la correction : la tâche reprend ce qui manque.
		//
		// L'auteur qui corrige, lui, ne l'attend plus, pas plus qu'au dépôt :
		// une planche en PDF le tenait quinze secondes devant le formulaire
		// et prenait l'un des cinq processus du serveur, en pleine campagne,
		// quand les corrections arrivent avec les dépôts. La tâche s'inscrit
		// avant la file, plus bas : la visite qui suit la redirection les
		// fait passer l'une après l'autre, et la relecture dit en attendant
		// que l'aperçu se prépare.
		if ( $redaction || ! $this->envoi_differe() ) {
			try {
				$a_faire = array();
				foreach ( $illustrations as $i => $chemin ) {
					$a_faire[ $i ] = isset( $apercus[ $i + 1 ] ) ? '' : $chemin;
				}
				if ( array_filter( $a_faire ) ) {
					$faits = $this->fabriquer_les_apercus( $a_faire, $d, $conversions );
					if ( ! empty( $faits ) ) {
						$apercus = $faits + $apercus;
						update_post_meta( $id, '_na_apercus', $apercus );
					}
				}
			} catch ( Throwable $e ) {
				error_log( 'Notice Archeomed: aperçus de la correction ' . $id . ' non fabriqués (' . get_class( $e ) . ' : ' . $e->getMessage() . ').' );
			}
		}

		// ── La notice ──────────────────────────────────────────────────────
		$this->file()->mettre_a_jour( $id, $d, $notice );
		$utilisateur = wp_get_current_user();
		$this->file()->noter_la_version( $id, $lue + 1,
			$redaction && $utilisateur->exists() ? $utilisateur->display_name : 'l’auteur', $champs );
		// Le thésaurus se relit sur les termes d'aujourd'hui.
		delete_post_meta( $id, '_na_pactols_essais' );
		delete_post_meta( $id, '_na_pactols_apres' );
		$this->mettre_en_attente( $id, $d );
		// Le Word se refait à la demande, d'après la saisie corrigée ; les
		// versions allégées qui manquent, par la tâche qui les fabrique.
		// L'ancien Word reste au répertoire temporaire, que la purge vide le
		// lendemain : un envoi attardé au-delà de l'abandon peut le joindre
		// encore.
		delete_post_meta( $id, '_na_document' );
		delete_post_meta( $id, '_na_apercus_tente' );

		// Ce que la notice ne désigne plus s'efface — dans le dossier de
		// dépôt seulement.
		$apres_chemins = array_merge( $illustrations, array_values( $autorisations ) );
		foreach ( $conversions as $paire ) {
			$apres_chemins = array_merge( $apres_chemins, array_values( $paire ) );
		}
		foreach ( $apercus as $mesure ) {
			if ( ! empty( $mesure['apercu'] ) ) {
				$apres_chemins[] = $mesure['apercu'];
			}
		}
		$avant_chemins = array_merge( array_values( $originaux ), array_values( $auto_anc ) );
		foreach ( $conv_anc as $paire ) {
			$avant_chemins = array_merge( $avant_chemins, array_values( (array) $paire ) );
		}
		foreach ( $ap_anc as $mesure ) {
			if ( ! empty( $mesure['apercu'] ) ) {
				$avant_chemins[] = $mesure['apercu'];
			}
		}
		$this->effacer_dans_le_depot( array_diff( $avant_chemins, $apres_chemins ) );

		$a_signaler = get_post_meta( $id, '_na_corrigee', true );
		$a_signaler = is_array( $a_signaler ) ? $a_signaler : null;

		if ( $redaction ) {
			// La rédaction corrige pour elle-même : personne n'est prévenu.
			// Une notice pas encore partie part corrigée ; une notice partie
			// le reste.
			delete_transient( 'na_reprise_' . $contexte['jeton'] );
			if ( '' !== $contexte['jeton_vivant'] && $contexte['jeton_vivant'] !== $contexte['jeton'] ) {
				delete_transient( 'na_reprise_' . $contexte['jeton_vivant'] );
			}
			if ( null !== $a_signaler ) {
				update_post_meta( $id, '_na_corrigee', array_values( array_unique( array_merge( $a_signaler, $champs ) ) ) );
			}
			if ( 'echec' === $etat_avant ) {
				// Elle n'était jamais arrivée : corrigée, elle retente sa chance.
				$this->file()->remettre_en_file( $id );
			} elseif ( 'en_attente' === $etat_avant ) {
				$this->file()->programmer( $id );
			}
			// La marque se lève en dernier : levée plus tôt, la file pouvait
			// envoyer la notice, puis la correction la remettre en attente.
			// Les versions allégées viennent après elle, que leur tâche ne
			// prenne pas la notice pour encore en chantier.
			delete_post_meta( $id, '_na_preparation' );
			$this->programmer_les_apercus( $id );
			$fiche = get_edit_post_link( $id, '' );
			wp_safe_redirect( add_query_arg( 'na_corrigee', '1',
				$fiche ? $fiche : admin_url( 'post.php?post=' . $id . '&action=edit' ) ) );
			exit;
		}

		// L'auteur : la notice repart vers la rédaction, qui lit ce qui a
		// changé. Le lien reste le même, et vaut un mois de plus.
		set_transient( 'na_reprise_' . $vivant, array(
			'corrige_id'       => $id,
			'vivant'           => true,
			'par_la_redaction' => 0,
		), MONTH_IN_SECONDS );
		if ( $contexte['jeton'] !== $vivant ) {
			delete_transient( 'na_reprise_' . $contexte['jeton'] );
		}
		// Une notice qui n'est pas encore partie partira corrigée, sans
		// qu'on la dise corrigée : la rédaction ne l'a jamais lue.
		if ( 'envoyee' === $etat_avant || null !== $a_signaler ) {
			update_post_meta( $id, '_na_corrigee', array_values( array_unique( array_merge(
				null !== $a_signaler ? $a_signaler : array(), $champs ) ) ) );
		}
		// Les aperçus s'inscrivent avant l'envoi : à la même seconde, le
		// planificateur passe les tâches dans l'ordre où elles sont venues,
		// et la notice part dans la même visite, avec ses figures. Après,
		// l'envoi passait le premier, se voyait renvoyé aux aperçus, et
		// n'était relancé qu'à la visite suivante. La tâche ne commence
		// rien tant que la marque de préparation tient : elle se lève juste
		// en dessous, avant que la requête rende la main.
		if ( $this->apercus_a_fabriquer( $id ) ) {
			$this->programmer_les_apercus( $id );
		}
		$this->file()->remettre_en_file( $id );
		delete_post_meta( $id, '_na_preparation' );
		$this->record_send( $d['resp_email'] );
		$this->record_global_send();
		wp_safe_redirect( add_query_arg( array_filter( array(
			'notice_envoyee'       => '1',
			'notice_corrigee'      => '1',
			'notice_ref'           => $d['reference'],
			'notice_sans_fichiers' => $this->fichiers_refuses ? '1' : '',
		) ), $this->url_du_formulaire() ) );
		exit;
	}

	/**
	 * Ce que l'écran de relecture montre d'une notice : sa saisie telle
	 * qu'elle est gardée, sa version, ses lieux, ses organismes, ses natures,
	 * et ses figures avec la présence de leur aperçu. Null sans saisie.
	 */
	public function notice_a_relire( $id ) {
		$id = (int) $id;
		$d  = get_post_meta( $id, '_na_donnees', true );
		if ( ! is_array( $d ) || empty( $d ) ) {
			return null;
		}
		$d = self::figures_lisibles( $d );
		foreach ( array( 'rubrique_principale', 'renvoi_1', 'renvoi_2' ) as $champ ) {
			if ( isset( $d[ $champ ] ) ) {
				$d[ $champ ] = self::rubrique_actuelle( $d[ $champ ] );
			}
		}
		$figures = array();
		// Les aperçus ne se font plus dans la requête du dépôt ni dans celle
		// d'une correction d'auteur : ouverte juste après, la page les attend
		// encore. Elle le dit, au lieu d'annoncer qu'il n'y en a pas.
		$attendus = $this->apercus_en_cours( $id ) || $this->apercus_a_fabriquer( $id );
		foreach ( $this->figures_gardees_de( $id, $d ) as $figure ) {
			$figure['apercu']  = '' !== $this->chemin_de_l_apercu( $id, (int) $figure['k'] );
			$figure['apercu_attendu'] = ! $figure['apercu'] && $attendus;
			$figure['masquee'] = null;
			foreach ( (array) $d['illustrations'] as $item ) {
				if ( is_array( $item ) && isset( $item['rang'] ) && (int) $item['rang'] === (int) $figure['k'] ) {
					$figure['masquee'] = self::figure_masquee( $item );
				}
			}
			$figures[] = $figure;
		}
		return array(
			'd'          => $d,
			'version'    => $this->file()->version_de( $id ),
			'figures'    => $figures,
			'lieux'      => self::lieux_saisis( $d ),
			'organismes' => array_values( $this->organismes_de( $d ) ),
			'natures'    => self::natures_saisies( $d ),
		);
	}

	/**
	 * Les lieux d'une saisie tels qu'on les a saisis, non redressés comme à
	 * la sortie : ceux d'une notice ancienne tiennent dans sa seule commune.
	 */
	private static function lieux_saisis( $d ) {
		$lieux = ( ! empty( $d['lieux'] ) && is_array( $d['lieux'] ) ) ? $d['lieux']
			: ( ! empty( $d['commune'] ) ? array( array( 'nom' => (string) $d['commune'],
				'ark' => isset( $d['commune_ark'] ) ? (string) $d['commune_ark'] : '' ) ) : array() );
		$propres = array();
		foreach ( $lieux as $lieu ) {
			if ( is_array( $lieu ) && isset( $lieu['nom'] ) && is_scalar( $lieu['nom'] ) && '' !== trim( (string) $lieu['nom'] ) ) {
				$propres[] = array(
					'nom' => (string) $lieu['nom'],
					'ark' => isset( $lieu['ark'] ) && is_scalar( $lieu['ark'] ) ? (string) $lieu['ark'] : '',
				);
			}
		}
		return $propres;
	}

	/** Les natures cochées d'une saisie, par leur libellé. */
	private static function natures_saisies( $d ) {
		$natures = array();
		if ( ! empty( $d['nature_items'] ) && is_array( $d['nature_items'] ) ) {
			foreach ( $d['nature_items'] as $item ) {
				if ( is_array( $item ) && isset( $item['label'] ) && is_scalar( $item['label'] ) ) {
					$natures[] = (string) $item['label'];
				}
			}
		}
		if ( empty( $natures ) && ! empty( $d['nature'] ) && is_scalar( $d['nature'] ) ) {
			$natures = array_map( 'trim', explode( ',', (string) $d['nature'] ) );
		}
		return array_values( array_filter( $natures, 'strlen' ) );
	}

	/**
	 * L'aperçu d'une figure, celui qui a été fabriqué pour le Word : son
	 * chemin, ou une chaîne vide s'il n'y en a pas sur le serveur.
	 *
	 * Les originaux partent sur sDrive ; l'aperçu, lui, reste — le rangement
	 * n'efface un original que si son aperçu se lit. C'est donc lui que la
	 * relecture montre, et jamais un fichier hors du dossier de dépôt.
	 */
	public function chemin_de_l_apercu( $id, $rang ) {
		$d       = get_post_meta( (int) $id, '_na_donnees', true );
		$apercus = $this->apercus_de( (int) $id, is_array( $d ) ? self::figures_lisibles( $d ) : null );
		$rang    = (int) $rang;
		$chemin  = ( isset( $apercus[ $rang ] ) && is_array( $apercus[ $rang ] ) && ! empty( $apercus[ $rang ]['apercu'] )
			&& is_string( $apercus[ $rang ]['apercu'] ) ) ? $apercus[ $rang ]['apercu'] : '';
		$dossier = $this->dossier_des_illustrations();
		return ( '' !== $chemin && '' !== $dossier && 0 === strpos( $chemin, $dossier ) && is_file( $chemin ) ) ? $chemin : '';
	}

	/**
	 * La notice à lire sans la corriger, en page HTML : celle que le dossier
	 * Métopes donne à relire, figures masquées ôtées, et sans aucune adresse
	 * de courriel. « $adresse_des_apercus » rend, pour un rang de figure,
	 * l'adresse qui sert son aperçu ; chaîne vide sans saisie.
	 */
	public function page_a_lire( $id, $adresse_des_apercus ) {
		$brute = get_post_meta( (int) $id, '_na_donnees', true );
		$d     = $this->saisie_de( (int) $id, false );
		if ( null === $d || ! is_array( $brute ) ) {
			return '';
		}
		// Les figures qui paraissent, chacune avec l'aperçu de son rang
		// d'origine : c'est sous lui que le fichier est rangé.
		$rangs = array();
		self::sans_les_masquees( self::figures_lisibles( $brute ), $rangs );
		$origines = array_flip( $rangs );
		foreach ( ( isset( $d['illustrations'] ) ? (array) $d['illustrations'] : array() ) as $i => $item ) {
			$rang    = isset( $item['rang'] ) ? (int) $item['rang'] : 0;
			$origine = isset( $origines[ $rang ] ) ? (int) $origines[ $rang ] : $rang;
			if ( '' !== $this->chemin_de_l_apercu( $id, $origine ) ) {
				$d['illustrations'][ $i ]['figure'] = array( 'fichier' => (string) call_user_func( $adresse_des_apercus, $origine ) );
			}
		}
		return $this->page_de_relecture( isset( $d['rubrique_principale'] ) ? (string) $d['rubrique_principale'] : '', array( $d ) );
	}

	/** L'identité d'un terme du thésaurus : son ARK, ou son libellé s'il n'en a pas. */
	private static function cle_de_terme( $item ) {
		$ark = isset( $item['ark'] ) && is_scalar( $item['ark'] ) ? trim( (string) $item['ark'] ) : '';
		return '' !== $ark ? $ark : 'libre:' . ( isset( $item['label'] ) && is_scalar( $item['label'] ) ? trim( (string) $item['label'] ) : '' );
	}

	/**
	 * Ce qui masque une figure — son motif, qui l'a retirée et quand —, ou
	 * null si elle paraît.
	 */
	public static function figure_masquee( $item ) {
		return ( is_array( $item ) && ! empty( $item['masquee'] ) && is_array( $item['masquee'] ) ) ? $item['masquee'] : null;
	}

	/**
	 * Une saisie sans ses figures masquées, celles qui restent numérotées à
	 * la suite ; « $rangs » rend, pour chaque figure gardée, son rang
	 * d'origine et le nouveau.
	 *
	 * Tous les documents passent par ici : le Word, le fascicule, le dossier
	 * Métopes, la page de relecture. La saisie gardée, elle, ne bouge pas :
	 * chaque figure y garde son rang, donc ses fichiers, et se rétablit
	 * sans rien avoir à remettre en place.
	 */
	private static function sans_les_masquees( $d, &$rangs = array() ) {
		$rangs = array();
		if ( empty( $d['illustrations'] ) || ! is_array( $d['illustrations'] ) ) {
			return $d;
		}
		// Sans figure masquée, rien ne se renumérote : la saisie sort telle
		// qu'elle est, comme avant qu'on puisse en masquer.
		if ( empty( self::rangs_masques( $d ) ) ) {
			foreach ( $d['illustrations'] as $item ) {
				if ( is_array( $item ) && isset( $item['rang'] ) ) {
					$rangs[ (int) $item['rang'] ] = (int) $item['rang'];
				}
			}
			return $d;
		}
		$restent = array();
		foreach ( $d['illustrations'] as $item ) {
			if ( null !== self::figure_masquee( $item ) ) {
				continue;
			}
			$nouveau = count( $restent ) + 1;
			if ( is_array( $item ) && isset( $item['rang'] ) ) {
				$rangs[ (int) $item['rang'] ] = $nouveau;
				if ( (int) $item['rang'] !== $nouveau && isset( $item['titre'] ) && is_scalar( $item['titre'] ) ) {
					$item['titre'] = Notice_Archeomed_Controles::titre_sans_numero( (string) $item['titre'], $nouveau );
					$item          = self::titre_forme_au_rang( $item, $nouveau );
				}
				$item['rang'] = $nouveau;
			}
			$restent[] = $item;
		}
		$d['illustrations'] = $restent;
		return $d;
	}

	/** Les rangs des figures masquées d'une saisie. */
	private static function rangs_masques( $d ) {
		$masques = array();
		foreach ( ( is_array( $d ) && isset( $d['illustrations'] ) && is_array( $d['illustrations'] ) ) ? $d['illustrations'] : array() as $item ) {
			if ( null !== self::figure_masquee( $item ) && ! empty( $item['rang'] ) ) {
				$masques[] = (int) $item['rang'];
			}
		}
		return $masques;
	}

	/**
	 * Les fichiers d'une notice sans ceux de ses figures masquées : ceux des
	 * figures qui paraissent, chacun au rang que les documents lui donnent.
	 *
	 * La liste des originaux se lit par position, les conversions par rang ;
	 * rien n'est effacé, le dossier Métopes ne les emporte simplement pas.
	 */
	private static function fichiers_sans_les_masquees( $d, $illustrations, $conversions ) {
		$masques = self::rangs_masques( $d );
		if ( empty( $masques ) ) {
			return array( $illustrations, $conversions );
		}
		$dernier = count( $illustrations );
		foreach ( (array) $d['illustrations'] as $item ) {
			if ( is_array( $item ) && ! empty( $item['rang'] ) ) {
				$dernier = max( $dernier, (int) $item['rang'] );
			}
		}
		$originaux = array();
		$converties = array();
		$rang      = 0;
		for ( $k = 1; $k <= $dernier; $k++ ) {
			if ( in_array( $k, $masques, true ) ) {
				continue;
			}
			++$rang;
			if ( isset( $illustrations[ $k - 1 ] ) ) {
				$originaux[] = $illustrations[ $k - 1 ];
			}
			if ( ! empty( $conversions[ $k ] ) ) {
				$converties[ $rang ] = $conversions[ $k ];
			}
		}
		return array( $originaux, $converties );
	}

	/**
	 * Le rappel à donner après le retrait d'une figure : ce que le contrôle
	 * des appels trouve dans le texte, maintenant que les figures qui restent
	 * se numérotent à la suite. Vide si le texte n'appelle rien de travers.
	 */
	public function appels_a_verifier( $id ) {
		$d = get_post_meta( (int) $id, '_na_donnees', true );
		if ( ! is_array( $d ) ) {
			return array();
		}
		$d = self::sans_les_masquees( self::figures_lisibles( $d ) );
		return Notice_Archeomed_Controles::appels_de_figure(
			wp_strip_all_tags( str_replace( array( '</p>', '</h3>', '</h4>', '</li>', '<br>' ), "\n", isset( $d['texte_notice'] ) ? (string) $d['texte_notice'] : '' ) ),
			count( isset( $d['illustrations'] ) ? (array) $d['illustrations'] : array() ) );
	}

	/**
	 * La saisie d'une notice telle que la relecture postée la corrige, ou
	 * une raison de refus : « texte » (vide, ou trop long), « motif » (une
	 * figure retirée sans dire pourquoi).
	 *
	 * On part de la saisie gardée et l'on n'y change que ce que le
	 * formulaire de relecture renvoie : un champ absent garde sa valeur, et
	 * ce que la relecture ne montre pas — le message à la rédaction, le lien
	 * des originaux, le lien de correction — reste tel quel.
	 *
	 * Ces règles tiennent ici, côté serveur, quoi que poste le formulaire :
	 * les courriels des responsables ne se lisent jamais dans la requête ;
	 * une illustration se retire, avec son motif, ou garde ses textes
	 * corrigés — aucune ne s'ajoute ni ne se remplace, et une figure retirée
	 * n'est que masquée, que la rédaction seule rétablit ; un mot-clé neuf ne
	 * vient que du thésaurus, avec son identifiant ; la rédaction seule
	 * change la rubrique principale.
	 *
	 * « $portee » dit qui relit : { tout } pour la rédaction, { renvoi } avec
	 * sa rubrique pour le coordinateur d'une rubrique de renvoi, qui ne peut
	 * que retirer le renvoi vers elle.
	 *
	 * Rend array( saisie, ce qui a été retiré ou rétabli ), ou la raison du
	 * refus.
	 */
	private function saisie_relue( $id, $avant, $portee, $nom ) {
		$toutes_rubriques = ! empty( $portee['tout'] );
		$faits            = array( 'figures_masquees' => array(), 'figures_retablies' => array(), 'termes_retires' => array(), 'renvois_retires' => array(), 'renvois_ajoutes' => array() );
		$d                = $avant;
		$rubrique_anc     = self::rubrique_actuelle( isset( $avant['rubrique_principale'] ) ? $avant['rubrique_principale'] : '' );

		// ── La rubrique : la rédaction seule la change, le coordinateur n'en
		// sort pas une notice. ────────────────────────────────────────────
		if ( $toutes_rubriques && isset( $_POST['rubrique_principale'] ) ) {
			$rubrique = $this->collect_select( 'rubrique_principale', $this->rubriques, true );
			if ( '' !== $rubrique && $rubrique !== $rubrique_anc ) {
				$d['rubrique_principale'] = $rubrique;
			}
		}
		$rubrique = self::rubrique_actuelle( isset( $d['rubrique_principale'] ) ? $d['rubrique_principale'] : '' );

		// ── Les renvois, dans leurs deux emplacements. Qui relit la
		// rubrique principale en retire et en pose ; le coordinateur d'une
		// rubrique de renvoi ne retire que le renvoi vers la sienne, et ne
		// corrige rien d'autre. Un renvoi ne vise ni la rubrique de la
		// notice, ni une rubrique déjà visée. ─────────────────────────────
		$renvois = array();
		foreach ( array( 'renvoi_1', 'renvoi_2' ) as $champ ) {
			$ancien = self::rubrique_actuelle( isset( $avant[ $champ ] ) ? $avant[ $champ ] : '' );
			$renvoi = $ancien;
			if ( empty( $portee['renvoi'] ) && isset( $_POST[ $champ ] ) ) {
				$renvoi = $this->collect_select( $champ, $this->rubriques, false );
			}
			if ( '' !== $ancien && ! empty( $_POST['retirer_renvoi'] ) && is_array( $_POST['retirer_renvoi'] ) && ! empty( $_POST['retirer_renvoi'][ $champ ] )
				&& ( empty( $portee['renvoi'] ) || $portee['renvoi'] === $ancien ) ) {
				$renvoi = '';
			}
			if ( '' !== $renvoi && $renvoi !== $ancien && ( $renvoi === $rubrique || in_array( $renvoi, $renvois, true ) ) ) {
				$renvoi = $ancien;
			}
			$renvois[ $champ ] = $renvoi;
		}
		// Le second emplacement ne garde pas ce que le premier vient de prendre.
		if ( '' !== $renvois['renvoi_1'] && $renvois['renvoi_1'] === $renvois['renvoi_2'] ) {
			$renvois['renvoi_2'] = '';
		}
		$avant_vises = array();
		foreach ( array( 'renvoi_1', 'renvoi_2' ) as $champ ) {
			$ancien = self::rubrique_actuelle( isset( $avant[ $champ ] ) ? $avant[ $champ ] : '' );
			if ( '' !== $ancien ) {
				$avant_vises[] = $ancien;
			}
			if ( $renvois[ $champ ] !== $ancien ) {
				$d[ $champ ] = $renvois[ $champ ];
			}
		}
		$apres_vises              = array_values( array_filter( $renvois, 'strlen' ) );
		$faits['renvois_retires'] = array_values( array_diff( $avant_vises, $apres_vises ) );
		$faits['renvois_ajoutes'] = array_values( array_diff( $apres_vises, $avant_vises ) );

		// ── La sous-rubrique d'un renvoi vers V : qui relit V la pose, ou la
		// direction. Le renvoi retiré, elle s'efface avec lui. ────────────
		$classe_avant = isset( $avant['renvoi_matiere'] ) && is_scalar( $avant['renvoi_matiere'] ) ? (string) $avant['renvoi_matiere'] : '';
		$classe       = self::renvoi_matiere_de( $avant );
		if ( self::RUBRIQUE_ARTISANAT === $rubrique || ! in_array( self::RUBRIQUE_ARTISANAT, $apres_vises, true ) ) {
			$classe = '';
		} elseif ( ! empty( $portee['classer_renvoi'] ) && isset( $_POST['renvoi_matiere'] ) && is_string( $_POST['renvoi_matiere'] ) ) {
			$postee = strtoupper( sanitize_key( wp_unslash( $_POST['renvoi_matiere'] ) ) );
			$classe = array_key_exists( $postee, self::MATIERES ) ? $postee : '';
		}
		if ( $classe !== $classe_avant && ( '' !== $classe || array_key_exists( 'renvoi_matiere', $avant ) ) ) {
			$d['renvoi_matiere'] = $classe;
			// Le renvoi retiré emporte son classement sans qu'on le redise.
			if ( in_array( self::RUBRIQUE_ARTISANAT, $apres_vises, true ) ) {
				$faits['renvoi_classe'] = $classe;
			}
		}
		if ( ! empty( $portee['renvoi'] ) ) {
			return array( $d, $faits );
		}

		$matiere  = self::matiere_de( $avant );
		if ( self::RUBRIQUE_ARTISANAT !== $rubrique ) {
			$matiere = '';
		} elseif ( isset( $_POST['rubrique_matiere'] ) ) {
			$postee  = strtoupper( sanitize_key( wp_unslash( $_POST['rubrique_matiere'] ) ) );
			$matiere = array_key_exists( $postee, self::MATIERES ) ? $postee : '';
		}
		if ( $matiere !== self::matiere_de( $avant ) || $rubrique !== $rubrique_anc ) {
			$d['rubrique_matiere'] = $matiere;
		}

		// ── Les champs de texte, reçus comme au dépôt. Les courriels n'y
		// sont pas : ils restent ceux de la saisie gardée. ────────────────
		$postes = array();
		foreach ( array( 'departement', 'lieu_dit', 'annee', 'num_autorisation', 'id_patriarche',
			'resp_prenom', 'resp_nom', 'resp_inst', 'coresp_prenom', 'coresp_nom', 'coresp_inst',
			'coauteur_prenom', 'coauteur_nom', 'coauteur_inst' ) as $champ ) {
			if ( isset( $_POST[ $champ ] ) && is_string( $_POST[ $champ ] ) ) {
				$postes[] = $champ;
			}
		}
		foreach ( $this->textes_postes( $postes ) as $champ => $valeur ) {
			if ( array_key_exists( $champ, $avant ) || '' !== $valeur ) {
				$d[ $champ ] = $valeur;
			}
		}
		foreach ( array( 'rapport_lien' => 'le lien du rapport', 'donnees_lien' => 'le lien du jeu de données' ) as $champ_lien => $nom_lien ) {
			if ( isset( $_POST[ $champ_lien ] ) && is_string( $_POST[ $champ_lien ] ) ) {
				$lien = $this->lien_poste( $champ_lien, $nom_lien );
				if ( array_key_exists( $champ_lien, $avant ) || '' !== $lien ) {
					$d[ $champ_lien ] = $lien;
				}
			}
		}

		// ── Les lieux. Un nom inchangé garde l'identifiant qu'il avait,
		// quoi que poste la requête ; un nom changé ou ajouté prend celui du
		// terme choisi dans le thésaurus — un ARK de Frantiq, ou rien. Une
		// liste vidée ne s'enregistre pas. Le dossier de la notice sur
		// sDrive, lui, garde le nom qu'il a reçu au premier rangement. ────
		if ( isset( $_POST['commune'] ) ) {
			$anciens = self::lieux_saisis( $avant );
			$connus  = array();
			foreach ( $anciens as $lieu ) {
				$connus[ $lieu['nom'] ] = $lieu['ark'];
			}
			$lieux = array();
			foreach ( $this->collect_lieux() as $lieu ) {
				$lieux[] = array( 'nom' => $lieu['nom'], 'ark' => isset( $connus[ $lieu['nom'] ] ) ? $connus[ $lieu['nom'] ] : $lieu['ark'] );
			}
			if ( ! empty( $lieux ) && $lieux !== $anciens ) {
				$d['lieux']       = $lieux;
				$d['commune']     = $lieux[0]['nom'];
				$d['commune_ark'] = $lieux[0]['ark'];
			}
		}
		if ( isset( $_POST['organisme'] ) ) {
			$organismes = $this->collect_organismes();
			if ( $organismes !== array_values( $this->organismes_de( $avant ) ) ) {
				$d['organismes'] = $organismes;
				$d['organisme']  = implode( ', ', $organismes );
			}
		}

		// ── Les natures : celles du formulaire, composées avec le cadre, et
		// celles que la notice portait déjà — une nature qu'on ne propose
		// plus ne se perd pas à la relecture. Les décocher toutes ne
		// s'enregistre pas. On compare à la notice telle que les cases la
		// rouvrent : « Fouille préventive » et « Sondage » se rouvrent en
		// Fouille, Sondage et Préventive, et la page enregistrée sans y
		// toucher ne doit pas en faire un sondage préventif. ──────────────
		if ( isset( $_POST['nature'] ) && is_array( $_POST['nature'] ) ) {
			$anciennes = self::natures_saisies( $avant );
			$items_anc = array();
			foreach ( ( isset( $avant['nature_items'] ) && is_array( $avant['nature_items'] ) ) ? $avant['nature_items'] : array() as $item ) {
				if ( is_array( $item ) && isset( $item['label'] ) && is_scalar( $item['label'] ) ) {
					$items_anc[ (string) $item['label'] ] = $item;
				}
			}
			$etat    = $this->decomposer_les_natures( $anciennes );
			$cochees = $this->recomposer_les_natures( $this->cases_postees( 'cadre' ), $this->cases_postees( 'nature' ), $anciennes );
			$triees_neuves = $cochees;
			$triees_avant  = $this->recomposer_les_natures( $etat['cadres'], $etat['natures'], $anciennes );
			sort( $triees_neuves );
			sort( $triees_avant );
			if ( ! empty( $cochees ) && $triees_neuves !== $triees_avant ) {
				$items = array();
				foreach ( $cochees as $libelle ) {
					if ( isset( $items_anc[ $libelle ] ) ) {
						$items[] = $items_anc[ $libelle ];
					} elseif ( isset( $this->natures[ $libelle ] ) ) {
						$items[] = array( 'label' => $libelle, 'ark' => $this->natures[ $libelle ], 'idConcept' => '' );
					}
				}
				$d['nature']       = implode( ', ', $cochees );
				$d['nature_items'] = $items;
			}
		}

		// ── Les termes du thésaurus : on en retire, et l'on en ajoute par
		// les listes de Pactols. Un terme gardé reste tel qu'il était ; un
		// terme neuf sans identifiant n'entre pas. ────────────────────────
		foreach ( array( 'periods', 'subjects', 'places' ) as $liste ) {
			$champ = 'pactols_' . $liste;
			if ( ! isset( $_POST[ $champ ] ) || ! is_string( $_POST[ $champ ] ) || '' === trim( $_POST[ $champ ] ) ) {
				continue;
			}
			$anciens = ( isset( $avant[ $champ . '_items' ] ) && is_array( $avant[ $champ . '_items' ] ) )
				? array_values( array_filter( $avant[ $champ . '_items' ], 'is_array' ) ) : array();
			$connus  = array();
			foreach ( $anciens as $item ) {
				$connus[ self::cle_de_terme( $item ) ] = $item;
			}
			$items = array();
			$vus   = array();
			foreach ( $this->collect_pactols_keywords( $champ ) as $item ) {
				$cle = self::cle_de_terme( $item );
				if ( isset( $vus[ $cle ] ) ) {
					continue;
				}
				$vus[ $cle ] = true;
				if ( isset( $connus[ $cle ] ) ) {
					$items[] = $connus[ $cle ];
				} elseif ( '' !== $item['ark'] ) {
					$items[] = $item;
				}
			}
			foreach ( $connus as $cle => $item ) {
				if ( ! isset( $vus[ $cle ] ) && isset( $item['label'] ) && is_scalar( $item['label'] ) ) {
					$faits['termes_retires'][] = (string) $item['label'];
				}
			}
			if ( $items !== $anciens ) {
				$d[ $champ . '_items' ] = $items;
				$d[ $champ . '_text' ]  = $this->pactols_keywords_to_text( $items );
			}
		}

		// ── Le texte. Vide, il ne s'enregistre pas : un éditeur qui ne
		// s'est pas chargé ne doit pas effacer une notice. ────────────────
		if ( isset( $_POST['texte_notice'] ) && is_string( $_POST['texte_notice'] ) ) {
			$brut = wp_unslash( $_POST['texte_notice'] );
			if ( strlen( $brut ) > 200000 ) {
				return 'texte';
			}
			$texte = $this->texte_notice_pose( $brut, 'le texte, balises comprises' );
			if ( '' === trim( wp_strip_all_tags( $texte ) ) ) {
				return 'texte';
			}
			$d['texte_notice'] = $texte;
		}

		// ── Les illustrations : chacune garde sa place, son rang, ses
		// fichiers et ses textes corrigés. Retirée, elle n'est que masquée,
		// avec son motif : rien ne s'efface, et la rédaction la rétablit.
		// Rien de ce que la requête porterait d'autre — une figure neuve, un
		// fichier — n'est lu. ─────────────────────────────────────────────
		$items = array();
		foreach ( ( isset( $avant['illustrations'] ) && is_array( $avant['illustrations'] ) ) ? $avant['illustrations'] : array() as $item ) {
			if ( ! is_array( $item ) || empty( $item['rang'] ) ) {
				$items[] = $item;
				continue;
			}
			$k       = (int) $item['rang'];
			$action  = $this->garde_postee( 'action', $k );
			$action  = null === $action ? '' : sanitize_key( $action );
			$titre   = isset( $item['titre'] ) && is_scalar( $item['titre'] ) ? (string) $item['titre'] : '';
			if ( null !== self::figure_masquee( $item ) ) {
				// Masquée, elle ne se corrige pas ; la rédaction seule la rétablit.
				if ( 'retablir' === $action && $toutes_rubriques ) {
					unset( $item['masquee'] );
					$faits['figures_retablies'][] = array( 'rang' => $k, 'titre' => $titre );
				}
				$items[] = $item;
				continue;
			}
			$brut   = array( 'description' => '' );
			$touche = false;
			foreach ( array( 'titre', 'legende', 'credits', 'alt' ) as $cle ) {
				$valeur = $this->garde_postee( $cle, $k );
				$touche = $touche || null !== $valeur;
				if ( null === $valeur ) {
					$valeur = isset( $item[ $cle ] ) && is_scalar( $item[ $cle ] ) ? (string) $item[ $cle ] : '';
					if ( 'alt' === $cle && isset( $item['description'] ) && is_scalar( $item['description'] ) && '' !== (string) $item['description'] ) {
						$valeur .= "\n" . (string) $item['description'];
					}
				}
				$brut[ $cle ] = $valeur;
			}
			// La mise en forme postée, ou celle que la figure avait. Changer
			// la seule mise en forme est déjà corriger la figure.
			foreach ( array_keys( Notice_Archeomed_Forme::CLEFS ) as $cle ) {
				$valeur = $this->garde_postee( $cle, $k );
				$touche = $touche || null !== $valeur;
				$brut[ $cle ] = null !== $valeur ? $valeur : ( isset( $item[ $cle ] ) && is_string( $item[ $cle ] ) ? $item[ $cle ] : '' );
			}
			if ( $touche ) {
				foreach ( $this->textes_de_figure( $brut, $k ) as $cle => $valeur ) {
					// Une clef que la figure ne portait pas ne naît pas vide :
					// la notice ne doit pas sembler changée pour si peu.
					if ( array_key_exists( $cle, $item ) || '' !== $valeur ) {
						$item[ $cle ] = $valeur;
					}
				}
			}
			$type = $this->garde_postee( 'type', $k );
			if ( null !== $type && '' !== Notice_Archeomed_Controles::type_de_figure( $type ) ) {
				$item['type'] = Notice_Archeomed_Controles::type_de_figure( $type );
			}
			if ( 'retirer' === $action ) {
				$motif     = $this->garde_postee( 'motif', $k );
				$motif     = null === $motif ? '' : sanitize_key( $motif );
				$precision = $this->garde_postee( 'precision', $k );
				$precision = null === $precision ? '' : trim( $this->limit_string( sanitize_text_field( $precision ), 300 ) );
				if ( ! array_key_exists( $motif, Notice_Archeomed_Relecture::MOTIFS ) || ( 'autre' === $motif && '' === $precision ) ) {
					return 'motif';
				}
				$item['masquee'] = array(
					'motif'     => $motif,
					'precision' => $precision,
					'par'       => (string) $nom,
					'le'        => current_time( 'mysql' ),
				);
				$faits['figures_masquees'][] = array( 'rang' => $k, 'titre' => $titre,
					'motif' => Notice_Archeomed_Relecture::MOTIFS[ $motif ] . ( '' !== $precision ? ' : ' . $precision : '' ) );
			}
			$items[] = $item;
		}
		if ( isset( $avant['illustrations'] ) && is_array( $avant['illustrations'] ) ) {
			$d['illustrations'] = $items;
		}
		return array( $d, $faits );
	}

	/**
	 * Enregistre la relecture d'une notice : la saisie corrigée devient la
	 * dernière version, et la précédente est gardée.
	 *
	 * L'appelant a vérifié qui relit, sa rubrique, son jeton et son verrou ;
	 * « $lue » est la version que sa page montrait, « $portee » ce que ce
	 * relecteur peut changer. Comme pour toute correction, une seule
	 * écriture conditionnelle fait passer la notice à la version suivante :
	 * deux enregistrements partis ensemble ne s'écrasent pas, le second est
	 * rendu au conflit. Tout ce qui peut refuser refuse avant qu'on écrive
	 * quoi que ce soit.
	 *
	 * Personne n'est prévenu, et la notice ne repart pas vers la rédaction :
	 * elle se relit sur le site. Rien de ce qui touche sDrive ne se fait
	 * ici — une panne de sDrive ne coûte pas une correction.
	 *
	 * Rend { issue, version, champs, faits } ; l'issue est « enregistree »,
	 * « inchangee », « conflit », « occupee », « texte », « motif » ou
	 * « introuvable ».
	 */
	public function enregistrer_la_relecture( $id, $lue, $relecteur, $portee = array() ) {
		$id    = (int) $id;
		$lue   = (int) $lue;
		$rendu = array( 'issue' => 'introuvable', 'version' => 0, 'champs' => array(), 'faits' => array() );
		$avant = get_post_meta( $id, '_na_donnees', true );
		if ( ! $id || ! is_array( $avant ) || empty( $avant ) ) {
			return $rendu;
		}
		$avant            = self::figures_lisibles( $avant );
		$rendu['version'] = $this->file()->version_de( $id );
		if ( $lue < 1 || $rendu['version'] !== $lue ) {
			$rendu['issue'] = 'conflit';
			return $rendu;
		}
		$utilisateur  = ( $relecteur instanceof WP_User ) ? $relecteur : wp_get_current_user();
		$nom          = $utilisateur->exists() ? (string) $utilisateur->display_name : 'la rédaction';
		$this->coupes = array();
		$relue        = $this->saisie_relue( $id, $avant, (array) $portee, $nom );
		if ( ! is_array( $relue ) ) {
			$rendu['issue'] = (string) $relue;
			return $rendu;
		}
		list( $d, $faits ) = $relue;
		$rendu['faits']    = $faits;

		// Ce qui a changé, en mots. Rien de changé : pas de version de plus,
		// ni de Word vide de tout suivi.
		$sans_avis = function ( $saisie ) {
			unset( $saisie['avis'] );
			return wp_json_encode( $saisie );
		};
		if ( $sans_avis( $avant ) === $sans_avis( $d ) ) {
			$rendu['issue'] = 'inchangee';
			return $rendu;
		}
		$figures_changees = ! empty( $faits['figures_masquees'] ) || ! empty( $faits['figures_retablies'] )
			|| self::textes_des_figures( isset( $avant['illustrations'] ) ? $avant['illustrations'] : array() )
				!== self::textes_des_figures( isset( $d['illustrations'] ) ? $d['illustrations'] : array() );
		$champs = $this->champs_changes( $avant, $d, $figures_changees );
		if ( empty( $champs ) ) {
			$champs = array( 'la saisie' );
		}

		// ── La version : un seul enregistrement passe ──────────────────────
		if ( ! $this->file()->changer_de_version( $id, $lue ) ) {
			$rendu['issue']   = 'conflit';
			$rendu['version'] = $this->file()->version_de( $id );
			return $rendu;
		}
		// La file ne doit pas envoyer la notice pendant qu'on la récrit, ni la
		// récrire pendant qu'elle part.
		wp_cache_delete( $id, 'post_meta' );
		if ( (int) get_post_meta( $id, '_na_preparation', true ) > time() - Notice_Archeomed_File::ABANDON ) {
			$this->file()->rendre_la_version( $id, $lue );
			$rendu['issue'] = 'occupee';
			return $rendu;
		}
		update_post_meta( $id, '_na_preparation', time() );
		wp_cache_delete( $id, 'post_meta' );
		$etat_avant = (string) get_post_meta( $id, '_na_etat', true );
		if ( 'en_cours' === $etat_avant
			&& (int) get_post_meta( $id, '_na_prise', true ) > time() - Notice_Archeomed_File::ABANDON ) {
			$this->file()->rendre_la_version( $id, $lue );
			delete_post_meta( $id, '_na_preparation' );
			$rendu['issue'] = 'occupee';
			return $rendu;
		}

		// ── La version d'avant se garde, avant toute écriture ──────────────
		Notice_Archeomed_Relecture::garder_la_version( $id, $lue, $avant, '', 0, array() );

		// ── La notice. Aucun fichier ne bouge : une figure retirée garde
		// les siens, à son rang. ───────────────────────────────────────────
		$d['avis'] = array_merge( (array) Notice_Archeomed_Controles::avis( self::sans_les_masquees( $d ) ), $this->avis_des_coupes() );
		$this->file()->mettre_a_jour( $id, $d, $this->build_notice( self::sans_les_masquees( $d ) ) );
		wp_cache_delete( $id, 'post_meta' );
		Notice_Archeomed_Relecture::garder_la_version( $id, $lue + 1, $d,
			$nom, $utilisateur->exists() ? (int) $utilisateur->ID : 0, $champs );
		$this->file()->noter_la_version( $id, $lue + 1, $nom, $champs );
		// Le thésaurus se relit sur les termes d'aujourd'hui, et le Word se
		// refait à la demande, d'après la saisie relue.
		delete_post_meta( $id, '_na_pactols_essais' );
		delete_post_meta( $id, '_na_pactols_apres' );
		$this->mettre_en_attente( $id, $d );
		delete_post_meta( $id, '_na_document' );
		// Une notice qui n'était jamais partie part relue ; une notice
		// partie le reste, et ce qu'on devait signaler d'elle s'allonge.
		$a_signaler = get_post_meta( $id, '_na_corrigee', true );
		if ( is_array( $a_signaler ) ) {
			update_post_meta( $id, '_na_corrigee', array_values( array_unique( array_merge( $a_signaler, $champs ) ) ) );
		}
		if ( 'echec' === $etat_avant ) {
			$this->file()->remettre_en_file( $id );
		} elseif ( 'en_attente' === $etat_avant ) {
			$this->file()->programmer( $id );
		}
		// La marque se lève en dernier, comme pour toute correction.
		delete_post_meta( $id, '_na_preparation' );
		$rendu['issue']   = 'enregistree';
		$rendu['version'] = $lue + 1;
		$rendu['champs']  = $champs;
		return $rendu;
	}

	/**
	 * Le Word de relecture d'une version : la notice telle que le relecteur
	 * l'a laissée, avec le suivi des modifications par rapport à la version
	 * précédente, signé de son nom. Rend son chemin au répertoire
	 * temporaire, ou une chaîne vide avec l'erreur.
	 *
	 * « $depuis » compare à une version plus ancienne que la précédente :
	 * c'est le Word qu'on soumet à l'auteur, toutes relectures réunies,
	 * signé alors de « $signature ». « $sans_courriels » ôte les adresses
	 * des responsables : le coordinateur ne les lit nulle part.
	 *
	 * Il se tire des versions gardées, et se refait donc à volonté : le
	 * dépôt sur sDrive peut attendre, échouer et reprendre sans que rien ne
	 * soit à conserver sur le serveur. Il ne porte aucune image : les
	 * figures du Word restent des liens, que seul le dossier Métopes sait
	 * résoudre. Le fascicule, lui, ne passe pas par ici — il se fabrique de
	 * la dernière saisie, sans aucun suivi.
	 */
	public function word_de_relecture( $id, $version, &$erreur = '', $depuis = 0, $sans_courriels = false, $signature = '' ) {
		$erreur = '';
		$id     = (int) $id;
		$apres  = Notice_Archeomed_Relecture::version_gardee( $id, (int) $version );
		$avant  = Notice_Archeomed_Relecture::version_gardee( $id, $depuis > 0 ? (int) $depuis : (int) $version - 1 );
		if ( null === $apres || null === $avant ) {
			$erreur = 'la version ' . (int) $version . ' ou celle à laquelle on la compare n’est pas gardée';
			return '';
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			$erreur = 'le suivi des modifications demande un DOCX, donc l’extension ZipArchive';
			return '';
		}
		$chemin = '';
		try {
			$ancien = $this->ouvrir_un_document( 'docx', $erreur );
			$neuf   = ( null === $ancien ) ? null : $this->ouvrir_un_document( 'docx', $erreur );
			if ( null === $ancien || null === $neuf ) {
				return '';
			}
			$saisies = array();
			foreach ( array( $avant['donnees'], $apres['donnees'] ) as $saisie ) {
				if ( $sans_courriels ) {
					foreach ( array( 'resp_email', 'coresp_email', 'coauteur_email', 'deposant_email' ) as $champ ) {
						$saisie[ $champ ] = '';
					}
				}
				$saisies[] = $this->preparer_la_saisie( $id, $saisie, array() );
			}
			$this->remplir_le_document( $ancien, $saisies[0] );
			$this->remplir_le_document( $neuf, $saisies[1] );
			$quand = '' !== $apres['le'] ? strtotime( get_gmt_from_date( $apres['le'] ) . ' UTC' ) : time();
			$par   = '' !== (string) $signature ? (string) $signature : $apres['par'];
			$neuf->suivre_les_modifications( $ancien, $par, $quand ? $quand : time() );
			$neuf->definir_le_titre( trim( $this->titre_de_liste( $saisies[1] ) . ' — relecture, version ' . (int) $version ) );
			$chemin = $neuf->write_to( trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp',
				'notice-archeomed-' . wp_generate_password( 8, false, false ) . '-'
					. Notice_Archeomed_Relecture::nom_du_word( $id, (int) $version, $par ) );
			if ( '' === $chemin ) {
				$erreur = $neuf->get_error();
			}
		} catch ( Throwable $e ) {
			$erreur = $e->getMessage();
			return '';
		}
		return $chemin;
	}

	/**
	 * Soumet à l'auteur une notice dont la relecture a touché le fond : un
	 * courriel au responsable de l'opération, avec le Word où se lisent
	 * toutes les modifications depuis sa version. Rend une chaîne vide, ou
	 * ce qui a empêché l'envoi.
	 *
	 * Rien ne part de soi-même : c'est la rédaction qui le demande, d'un
	 * bouton. Un courriel refusé ne change rien à la notice — on recommence.
	 */
	public function soumettre_a_l_auteur( $id ) {
		$id = (int) $id;
		$d  = get_post_meta( $id, '_na_donnees', true );
		if ( ! is_array( $d ) || empty( $d['resp_email'] ) || ! is_email( $d['resp_email'] ) ) {
			return 'le responsable de l’opération n’a pas d’adresse valide';
		}
		$versions = Notice_Archeomed_Relecture::versions_gardees( $id );
		if ( count( $versions ) < 2 ) {
			return 'aucune relecture n’est enregistrée pour cette notice';
		}
		$numeros = array_keys( $versions );
		$erreur  = '';
		$word    = $this->word_de_relecture( $id, max( $numeros ), $erreur, min( $numeros ), false, 'Relecture de la revue' );
		if ( '' === $word ) {
			return 'le Word de relecture ne se fabrique pas (' . $erreur . ')';
		}
		$reference = (string) get_post_meta( $id, '_na_reference', true );
		$titre     = $this->titre_de_liste( self::figures_lisibles( $d ) );
		$contact   = $this->adresse_de_contact();
		$corps     = '<p>Bonjour,</p>'
			. '<p>' . esc_html( 'Votre notice « ' . $titre . ' »' . ( '' !== $reference ? ' (référence ' . $reference . ')' : '' )
				. ' a été relue pour la Chronique d’Archéologie médiévale. La relecture en a modifié le fond : votre accord écrit est nécessaire avant publication.' ) . '</p>'
			. '<p>' . esc_html( 'Le document joint montre chaque modification en suivi : ce qui a été ajouté, ce qui a été retiré.'
				. ' Répondez à ce courriel pour donner votre accord, ou pour dire ce que vous souhaitez reprendre.' ) . '</p>'
			. '<p>' . esc_html( 'La rédaction' ) . '</p>';
		$entetes = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( '' !== $contact && is_email( $contact ) ) {
			$entetes[] = 'Reply-To: ' . $contact;
		}
		$this->derniere_erreur_mail = '';
		$parti = false;
		try {
			$parti = self::courriel( $d['resp_email'], 'Votre notice relue : accord demandé — ' . $titre, $corps, $entetes,
				array( Notice_Archeomed_Relecture::nom_du_word( $id, max( $numeros ), 'pour accord' ) => $word ) );
		} catch ( Throwable $e ) {
			$this->derniere_erreur_mail = $e->getMessage();
		}
		@unlink( $word );
		if ( ! $parti ) {
			return 'le courriel n’est pas parti' . ( '' !== $this->derniere_erreur_mail
				? ' (' . Notice_Archeomed_File::expliquer_l_erreur( $this->derniere_erreur_mail ) . ')' : '' );
		}
		return '';
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
		// Les dossiers de copies que les versions d'avant la 3.53 faisaient
		// pour un courriel : une requête coupée en route les laissait pour
		// toujours, la purge ne regardant que les fichiers.
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
