<?php
/**
 * La relecture des notices sur le site, par les coordinateurs de rubrique
 * puis par la direction de la revue, qui valide chaque notice.
 *
 * Chaque coordinateur relisait sa partie dans un Word stylé, et en cassait
 * souvent les styles : une métadonnée ajoutée à la main, le fichier ouvert
 * dans un autre traitement de texte. La relecture se fait donc ici, dans un
 * formulaire qui écrit dans la saisie gardée — celle d'où se refont le
 * Word, le fascicule et le dossier Métopes.
 *
 * L'écran vit sur une page publique du site, dans le thème, par le
 * shortcode « [relecture_archeomed] » : un coordinateur se connecte, mais
 * n'entre jamais dans l'administration. Sans connexion, la page renvoie à
 * la connexion ; sans droit, elle ne montre rien.
 *
 * Elle s'ouvre quand la campagne est close, et les liens de correction des
 * auteurs se ferment au même instant. Un coordinateur ne voit que les
 * notices de ses rubriques, dans l'ordre du fascicule ; la direction les
 * voit toutes, dans un sommaire dont chaque rubrique se déplie. Elle
 * corrige, puis valide : la version validée est la version définitive, et
 * ne se corrige plus que si la direction la rouvre. Une notice ouverte
 * est verrouillée pour les autres, chaque enregistrement garde la version
 * d'avant, et le Word de relecture — le suivi des modifications, signé du
 * relecteur — part sur sDrive par une tâche à part : une panne de sDrive
 * ne coûte pas une correction.
 *
 * La direction peut voir l'écran comme le voit un coordinateur — un compte
 * nommé, ou la coordination d'une rubrique qui n'a pas encore de compte —,
 * pour préparer la relecture avant de l'ouvrir. Cette vue n'est qu'un
 * regard : tout s'y calcule avec les droits du coordinateur, mais rien ne
 * s'y enregistre, ne s'y verrouille ni n'entre au journal.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Relecture {

	/** Le rôle d'un coordinateur de rubrique : lire son profil, relire, rien d'autre. */
	const ROLE = 'na_coordinateur';

	/**
	 * Le rôle de la direction de la revue : relire et valider toutes les
	 * rubriques, régler la Chronique, rien d'autre dans l'administration.
	 */
	const ROLE_DIRECTION = 'na_direction';

	/** L'ancien rôle de la rédaction, que la direction remplace. Ses comptes passent à la direction. */
	const ROLE_REDACTION = 'na_redaction';

	/** La capacité de relire les rubriques qu'on vous a confiées. */
	const CAP = 'na_relire';

	/** La capacité de la direction : toutes les rubriques, tous les états. */
	const CAP_TOUT = 'na_relire_tout';

	/** L'option des réglages : campagne close, coordinateurs par rubrique. */
	const OPTION = 'notice_archeomed_relecture';

	/** Le shortcode qui pose l'écran de relecture sur une page du site. */
	const SHORTCODE = 'relecture_archeomed';

	/** L'entrée du menu d'administration, qui ne fait que mener à cette page. */
	const PAGE = 'na-relecture';

	/** Le crochet de la tâche qui dépose les Word de relecture sur sDrive. */
	const HOOK = 'na_deposer_les_relectures';

	/** Le verrou de cette tâche : une seule dépose à la fois. */
	const VERROU_DEPOT = 'na_relecture_verrou_depot';

	/** Les passages qu'un dépôt peut manquer avant qu'on le laisse. */
	const ESSAIS = 8;

	/**
	 * Le temps qu'un verrou tient sans nouvelles de la page qui l'a pris. La
	 * page le renouvelle chaque minute ; fermée sans prévenir, elle le
	 * laisse expirer.
	 */
	const DUREE_DU_VERROU = 300;

	/**
	 * Le temps que dure le choix de voir la relecture comme un coordinateur.
	 * Assez pour parcourir ses rubriques ; on n'y reste pas pris le
	 * lendemain, si l'on a oublié d'en sortir.
	 */
	const DUREE_DE_LA_VUE = 2 * HOUR_IN_SECONDS;

	/** Pourquoi une figure se retire : le motif est obligatoire. */
	const MOTIFS = array(
		'redondante'   => 'Redondante',
		'qualite'      => 'Qualité insuffisante',
		'autorisation' => 'Autorisation manquante',
		'nombre'       => 'Au-delà du nombre admis par les normes',
		'autre'        => 'Autre',
	);

	/** Où en est la relecture d'une notice. */
	const ETATS = array(
		'a_relire'    => 'À relire',
		'relue'       => 'Relue par le coordinateur',
		'a_soumettre' => 'À soumettre à l’auteur',
		'soumise'     => 'Soumise à l’auteur',
		'accord'      => 'Accord reçu',
		'validee'     => 'Validée par la direction',
	);

	/** Les états où le coordinateur de la rubrique corrige encore. */
	const ETATS_DU_COORDINATEUR = array( 'a_relire', 'relue', 'a_soumettre' );

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'assurer_les_roles' ) );
		add_shortcode( self::SHORTCODE, array( $this, 'afficher' ) );
		add_action( 'template_redirect', array( $this, 'garder_la_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'charger_l_editeur' ) );
		add_action( 'wp_head', array( __CLASS__, 'poser_les_styles' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'sans_barre_d_administration' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'apres_la_connexion' ), 10, 3 );
		add_action( 'admin_init', array( $this, 'sortir_de_l_administration' ) );
		// Avant que WordPress ne refuse la page : son contrôle d'accès passe
		// avant « admin_init ».
		add_action( 'auth_redirect', array( __CLASS__, 'garder_la_direction' ) );
		add_action( 'admin_menu', array( $this, 'poser_le_menu' ) );
		add_action( 'admin_post_na_relecture', array( $this, 'recevoir' ) );
		add_action( 'admin_post_na_relecture_reglages', array( $this, 'recevoir_les_reglages' ) );
		add_action( 'admin_post_na_relecture_word', array( $this, 'rendre_le_word' ) );
		add_action( 'admin_post_na_relecture_apercu', array( $this, 'rendre_l_apercu' ) );
		add_action( 'admin_post_na_relecture_lire', array( $this, 'rendre_la_lecture' ) );
		add_action( 'admin_post_na_relecture_soumettre', array( $this, 'soumettre' ) );
		add_action( 'admin_post_na_relecture_rouvrir', array( $this, 'recevoir_la_reouverture' ) );
		add_action( 'admin_post_na_relecture_vue', array( $this, 'choisir_la_vue' ) );
		add_action( 'admin_post_na_relecture_vue_quitter', array( $this, 'quitter_la_vue' ) );
		add_action( 'wp_ajax_na_relecture_verrou', array( $this, 'tenir_le_verrou' ) );
		add_action( 'wp_ajax_na_relecture_liberer', array( $this, 'rendre_le_verrou' ) );
		add_action( self::HOOK, array( __CLASS__, 'en_tache' ), 10, 0 );
		// Au passage de la relance, après la file et le rangement.
		add_action( Notice_Archeomed_File::HOOK_RELANCE, array( __CLASS__, 'reveiller' ), 22, 0 );
	}

	// ── Les droits ─────────────────────────────────────────────────────────

	/**
	 * Les deux rôles, et la capacité de la direction pour l'administrateur.
	 *
	 * Vérifiés à chaque chargement et non à l'activation seule : une mise à
	 * jour par téléversement de l'archive ne repasse pas par l'activation,
	 * et les rôles auraient manqué jusqu'à ce qu'on désactive et réactive.
	 * Rien ne s'écrit quand tout est en place.
	 */
	public static function assurer_les_roles() {
		if ( null === get_role( self::ROLE ) ) {
			add_role( self::ROLE, 'Coordinateur de rubrique (Chronique)', array( 'read' => true, self::CAP => true ) );
		}
		if ( null === get_role( self::ROLE_DIRECTION ) ) {
			add_role( self::ROLE_DIRECTION, 'Direction de la revue',
				array( 'read' => true, self::CAP => true, self::CAP_TOUT => true, Notice_Archeomed_Settings::CAPACITE => true ) );
		}
		// La rédaction d'avant devient la direction : ses comptes changent de
		// rôle, puis le rôle disparaît. Une fois, tant qu'il existe.
		if ( null !== get_role( self::ROLE_REDACTION ) ) {
			foreach ( get_users( array( 'role' => self::ROLE_REDACTION ) ) as $ancien ) {
				$ancien->add_role( self::ROLE_DIRECTION );
				$ancien->remove_role( self::ROLE_REDACTION );
			}
			remove_role( self::ROLE_REDACTION );
		}
		$administrateur = get_role( 'administrator' );
		if ( null !== $administrateur && ! $administrateur->has_cap( self::CAP_TOUT ) ) {
			$administrateur->add_cap( self::CAP );
			$administrateur->add_cap( self::CAP_TOUT );
		}
	}

	/** Les réglages : { close, coordinateurs par rubrique }. */
	public static function reglages() {
		$reglages = get_option( self::OPTION, array() );
		$reglages = is_array( $reglages ) ? $reglages : array();
		return array(
			'close'         => ! empty( $reglages['close'] ),
			'coordinateurs' => ( isset( $reglages['coordinateurs'] ) && is_array( $reglages['coordinateurs'] ) ) ? $reglages['coordinateurs'] : array(),
		);
	}

	/**
	 * La campagne est-elle close ? C'est ce qui ouvre la relecture, et ce qui
	 * ferme les liens de correction des auteurs.
	 */
	public static function campagne_close() {
		$reglages = self::reglages();
		return $reglages['close'];
	}

	/** Le plugin, d'où viennent rubriques, saisies et documents. */
	private static function plugin() {
		global $notice_archeomed_plugin;
		return ( $notice_archeomed_plugin instanceof Notice_Archeomed_Pactols ) ? $notice_archeomed_plugin : null;
	}

	/** Le compte donné, ou celui qui est connecté. */
	private static function compte( $utilisateur = null ) {
		if ( $utilisateur instanceof WP_User ) {
			return $utilisateur;
		}
		// Rien de précisé : le compte connecté. Un numéro nul n'est personne.
		return null === $utilisateur ? wp_get_current_user() : get_userdata( (int) $utilisateur );
	}

	/** Ce compte relit-il pour la direction : toutes les rubriques, tous les états ? */
	public static function peut_tout( $utilisateur = null ) {
		$compte = self::compte( $utilisateur );
		return ( $compte instanceof WP_User ) && $compte->exists()
			&& ( user_can( $compte, self::CAP_TOUT ) || user_can( $compte, 'manage_options' ) );
	}

	/**
	 * Les rubriques qu'un compte relit : toutes pour la direction, celles
	 * que les réglages lui confient pour un coordinateur, aucune sinon.
	 */
	public static function rubriques_de( $utilisateur = null ) {
		$compte = self::compte( $utilisateur );
		$plugin = self::plugin();
		if ( null === $plugin || ! ( $compte instanceof WP_User ) || ! $compte->exists() ) {
			return array();
		}
		if ( self::peut_tout( $compte ) ) {
			return $plugin->rubriques();
		}
		if ( ! user_can( $compte, self::CAP ) ) {
			return array();
		}
		// Le regard d'une rubrique sans compte : cette rubrique, si elle
		// est toujours du fascicule.
		if ( $compte instanceof Notice_Archeomed_Regard && null !== $compte->rubriques_fixees ) {
			return array_values( array_intersect( $plugin->rubriques(), $compte->rubriques_fixees ) );
		}
		$reglages  = self::reglages();
		$rubriques = array();
		foreach ( $plugin->rubriques() as $rubrique ) {
			$confiee = isset( $reglages['coordinateurs'][ $rubrique ] ) ? array_map( 'intval', (array) $reglages['coordinateurs'][ $rubrique ] ) : array();
			if ( in_array( (int) $compte->ID, $confiee, true ) ) {
				$rubriques[] = $rubrique;
			}
		}
		return $rubriques;
	}

	/**
	 * Ce qu'un compte peut d'une notice, ou null s'il n'en peut rien :
	 * { sorte, rubrique }. La sorte est « tout » pour la direction,
	 * « principale » pour le coordinateur de sa rubrique, « renvoi » pour
	 * celui d'une rubrique où elle renvoie, qui la lit sans la corriger.
	 *
	 * Tout passe par ici, l'affichage comme chaque écriture : l'identifiant
	 * d'une notice se forge dans une adresse ou un champ caché, et c'est la
	 * notice elle-même qui dit sa rubrique — jamais la requête. Avant la
	 * clôture de la campagne, personne ne relit, la direction pas plus
	 * qu'un autre ; seul le regard d'un coordinateur, que la direction
	 * emprunte pour préparer la relecture, la voit comme si elle l'était.
	 */
	public static function droit_sur( $id, $utilisateur = null ) {
		$id     = (int) $id;
		$compte = self::compte( $utilisateur );
		$plugin = self::plugin();
		if ( ! $id || null === $plugin || ! ( $compte instanceof WP_User ) || ! $compte->exists() ) {
			return null;
		}
		if ( ! self::campagne_close() && ! ( $compte instanceof Notice_Archeomed_Regard ) ) {
			return null;
		}
		$notice = get_post( $id );
		$d      = get_post_meta( $id, '_na_donnees', true );
		if ( ! $notice || Notice_Archeomed_File::CPT !== $notice->post_type || 'private' !== $notice->post_status || ! is_array( $d ) || empty( $d ) ) {
			return null;
		}
		$rubrique = Notice_Archeomed_Pactols::rubrique_actuelle( (string) get_post_meta( $id, '_na_rubrique', true ) );
		if ( self::peut_tout( $compte ) ) {
			return array( 'sorte' => 'tout', 'rubrique' => $rubrique );
		}
		$siennes = self::rubriques_de( $compte );
		if ( empty( $siennes ) ) {
			return null;
		}
		// Une notice qu'une correction déposée à part a remplacée n'est plus
		// au fascicule : elle ne se relit pas davantage.
		if ( in_array( $rubrique, $siennes, true ) ) {
			return in_array( $id, array_map( 'intval', (array) $plugin->ids_de_la_rubrique( $rubrique ) ), true )
				? array( 'sorte' => 'principale', 'rubrique' => $rubrique ) : null;
		}
		foreach ( array( 'renvoi_1', 'renvoi_2' ) as $champ ) {
			$renvoi = Notice_Archeomed_Pactols::rubrique_actuelle( isset( $d[ $champ ] ) ? $d[ $champ ] : '' );
			if ( '' !== $renvoi && in_array( $renvoi, $siennes, true ) ) {
				return array( 'sorte' => 'renvoi', 'rubrique' => $renvoi );
			}
		}
		return null;
	}

	/** L'état de relecture d'une notice ; « à relire » tant que rien n'est dit. */
	public static function etat_de( $id ) {
		$etat = (string) get_post_meta( (int) $id, '_na_relecture_etat', true );
		return array_key_exists( $etat, self::ETATS ) ? $etat : 'a_relire';
	}

	/**
	 * Ce droit permet-il de corriger la notice dans cet état ? La direction
	 * tant qu'elle ne l'a pas validée ; le coordinateur de la rubrique tant
	 * que la notice n'est pas partie vers l'auteur ni validée ; celui d'un
	 * renvoi jamais. Une notice validée ne se corrige plus : il faut la
	 * rouvrir.
	 */
	public static function peut_corriger( $droit, $etat ) {
		if ( ! is_array( $droit ) || 'validee' === $etat ) {
			return false;
		}
		return 'tout' === $droit['sorte']
			|| ( 'principale' === $droit['sorte'] && in_array( $etat, self::ETATS_DU_COORDINATEUR, true ) );
	}

	// ── Le verrou ──────────────────────────────────────────────────────────

	/** Qui tient une notice, et depuis quand : { par, depuis }, zéro si personne. */
	public static function verrou_de( $id ) {
		wp_cache_delete( (int) $id, 'post_meta' );
		$valeur = (string) get_post_meta( (int) $id, '_na_relecture_verrou', true );
		$partie = explode( ':', $valeur );
		$depuis = isset( $partie[0] ) ? (int) $partie[0] : 0;
		$par    = isset( $partie[1] ) ? (int) $partie[1] : 0;
		if ( ! $par || $depuis < time() - self::DUREE_DU_VERROU ) {
			return array( 'par' => 0, 'depuis' => 0 );
		}
		return array( 'par' => $par, 'depuis' => $depuis );
	}

	/**
	 * Prend ou renouvelle le verrou d'une notice, et dit qui la tient :
	 * { tenu, par, depuis }.
	 *
	 * Sur le modèle du verrou d'édition de WordPress — une heure et un
	 * compte dans une métadonnée, qui expire seule —, mais pris par une
	 * écriture conditionnelle, comme toute prise ici : la base ne change la
	 * ligne que si le verrou est libre, périmé ou déjà le nôtre, et deux
	 * relecteurs qui ouvrent la notice au même instant ne l'ont pas tous
	 * deux. Le changement de version, à l'enregistrement, reste le dernier
	 * rempart.
	 */
	public static function prendre_le_verrou( $id, $compte ) {
		global $wpdb;
		$id     = (int) $id;
		$compte = (int) $compte;
		if ( ! $id || ! $compte ) {
			return array( 'tenu' => false, 'par' => 0, 'depuis' => 0 );
		}
		$maintenant = time();
		add_post_meta( $id, '_na_relecture_verrou', '0:0', true );
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %d AND meta_key = '_na_relecture_verrou'"
			. ' AND ( meta_value LIKE %s OR CAST( meta_value AS UNSIGNED ) < %d )',
			$maintenant . ':' . $compte, $id, '%:' . $compte, $maintenant - self::DUREE_DU_VERROU ) );
		$tenant          = self::verrou_de( $id );
		$tenant['tenu']  = $tenant['par'] === $compte;
		return $tenant;
	}

	/** Rend le verrou d'une notice, s'il est encore celui de ce compte. */
	public static function liberer_le_verrou( $id, $compte ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_value = '0:0' WHERE post_id = %d AND meta_key = '_na_relecture_verrou' AND meta_value LIKE %s",
			(int) $id, '%:' . (int) $compte ) );
		wp_cache_delete( (int) $id, 'post_meta' );
	}

	/** La page ouverte renouvelle son verrou, chaque minute. */
	public function tenir_le_verrou() {
		$id = isset( $_POST['notice'] ) ? absint( $_POST['notice'] ) : 0;
		check_ajax_referer( 'na_relecture_verrou_' . $id );
		// Rien ne se tient pendant qu'on voit la relecture comme un autre.
		if ( null !== self::vue() ) {
			wp_send_json_error();
		}
		$droit = self::droit_sur( $id );
		if ( ! self::peut_corriger( $droit, self::etat_de( $id ) ) ) {
			wp_send_json_error();
		}
		$verrou = self::prendre_le_verrou( $id, get_current_user_id() );
		wp_send_json_success( array( 'tenu' => $verrou['tenu'] ) );
	}

	/** La page qu'on quitte rend son verrou sans attendre qu'il expire. */
	public function rendre_le_verrou() {
		$id = isset( $_POST['notice'] ) ? absint( $_POST['notice'] ) : 0;
		check_ajax_referer( 'na_relecture_verrou_' . $id );
		if ( null !== self::droit_sur( $id ) ) {
			self::liberer_le_verrou( $id, get_current_user_id() );
		}
		wp_send_json_success();
	}

	// ── Les versions gardées et le journal ─────────────────────────────────

	/**
	 * Garde la saisie d'une version, une fois : qui l'a faite, quand, ce
	 * qu'elle a changé. Chaque version a sa ligne — rien ne se relit pour
	 * être récrit, et deux enregistrements ne se marchent pas dessus.
	 */
	public static function garder_la_version( $id, $version, $donnees, $par, $par_id, $champs ) {
		$id      = (int) $id;
		$version = (int) $version;
		if ( ! $id || $version < 1 || ! is_array( $donnees ) || null !== self::version_gardee( $id, $version ) ) {
			return;
		}
		add_post_meta( $id, '_na_relecture_version', wp_slash( array(
			'version' => $version,
			'le'      => '' !== (string) $par ? current_time( 'mysql' ) : '',
			'par'     => (string) $par,
			'par_id'  => (int) $par_id,
			'champs'  => array_values( array_map( 'strval', (array) $champs ) ),
			'donnees' => $donnees,
		) ) );
	}

	/** Les versions gardées d'une notice, par numéro, de la plus ancienne à la dernière. */
	public static function versions_gardees( $id ) {
		$versions = array();
		foreach ( (array) get_post_meta( (int) $id, '_na_relecture_version', false ) as $ligne ) {
			if ( is_array( $ligne ) && ! empty( $ligne['version'] ) && isset( $ligne['donnees'] ) && is_array( $ligne['donnees'] ) ) {
				$versions[ (int) $ligne['version'] ] = array(
					'version' => (int) $ligne['version'],
					'le'      => isset( $ligne['le'] ) ? (string) $ligne['le'] : '',
					'par'     => isset( $ligne['par'] ) ? (string) $ligne['par'] : '',
					'par_id'  => isset( $ligne['par_id'] ) ? (int) $ligne['par_id'] : 0,
					'champs'  => isset( $ligne['champs'] ) ? (array) $ligne['champs'] : array(),
					'donnees' => $ligne['donnees'],
				);
			}
		}
		ksort( $versions );
		return $versions;
	}

	/** Une version gardée, ou null. */
	public static function version_gardee( $id, $version ) {
		$versions = self::versions_gardees( $id );
		return isset( $versions[ (int) $version ] ) ? $versions[ (int) $version ] : null;
	}

	/**
	 * Le nom du Word de relecture d'une version : « Caen_Dupont_v3_relecture_
	 * Jeanne_Martin.docx ». Il porte la commune, le responsable d'opération,
	 * la version et le nom du relecteur, à côté des « Caen_Dupont_v1.docx »
	 * que le rangement dépose.
	 */
	public static function nom_du_word( $id, $version, $par ) {
		$d = (int) $id > 0 ? get_post_meta( (int) $id, '_na_donnees', true ) : array();
		return Notice_Archeomed_Nommage::tige_du_word( $d ) . '_v' . (int) $version . '_relecture_'
			. Notice_Archeomed_Nommage::assainir( (string) $par, 'relecteur' ) . '.docx';
	}

	/**
	 * Ajoute une ligne au journal de la notice : qui, quand, quoi, l'état
	 * d'avant et celui d'après, la remarque laissée.
	 *
	 * Le journal ne se récrit pas : chaque ligne est une métadonnée de plus,
	 * et rien dans l'écran de relecture n'en modifie ni n'en retire.
	 */
	public static function noter_au_journal( $id, $compte, $quoi, $etat_avant, $etat_apres, $remarque = '', $version = 0 ) {
		$compte = self::compte( $compte );
		// Le regard d'un coordinateur ne laisse pas de trace.
		if ( $compte instanceof Notice_Archeomed_Regard ) {
			return;
		}
		add_post_meta( (int) $id, '_na_relecture_journal', wp_slash( array(
			'le'         => current_time( 'mysql' ),
			'par'        => ( $compte instanceof WP_User ) && $compte->exists() ? (string) $compte->display_name : '',
			'par_id'     => ( $compte instanceof WP_User ) && $compte->exists() ? (int) $compte->ID : 0,
			'quoi'       => array_values( array_map( 'strval', (array) $quoi ) ),
			'etat_avant' => (string) $etat_avant,
			'etat_apres' => (string) $etat_apres,
			'remarque'   => (string) $remarque,
			'version'    => (int) $version,
		) ) );
	}

	/** Le journal d'une notice, dans l'ordre où il s'est écrit. */
	public static function journal_de( $id ) {
		$journal = array();
		foreach ( (array) get_post_meta( (int) $id, '_na_relecture_journal', false ) as $ligne ) {
			if ( is_array( $ligne ) && isset( $ligne['le'] ) ) {
				$journal[] = array_merge( array( 'par' => '', 'par_id' => 0, 'quoi' => array(), 'etat_avant' => '', 'etat_apres' => '', 'remarque' => '', 'version' => 0 ), $ligne );
			}
		}
		return $journal;
	}

	// ── L'enregistrement ───────────────────────────────────────────────────

	/**
	 * Enregistre ce que le formulaire de relecture a posté pour une notice,
	 * au nom d'un compte : la saisie corrigée, l'état, la remarque à la
	 * direction, et la ligne du journal qui les dit.
	 *
	 * Le droit se relit ici, sur la notice : un identifiant forgé ne donne
	 * rien. Le coordinateur d'un renvoi ne peut que retirer le renvoi vers
	 * sa rubrique et laisser une remarque ; celui de la rubrique ne peut
	 * faire avancer l'état que vers « relue » ou « à soumettre à l'auteur ».
	 * La direction valide par « suite = valider » : la notice est alors
	 * figée, et ce qui voudrait encore la changer est refusé (« figee ») —
	 * un formulaire resté ouvert pendant qu'une autre la validait, par
	 * exemple. Une remarque passe toujours.
	 *
	 * Rend ce que rend l'enregistrement de la saisie, avec l'état ; l'issue
	 * peut être aussi « interdit », « figee » ou « verrouillee » (avec « par »).
	 * Le regard d'un coordinateur n'enregistre rien : « interdit ».
	 */
	public static function enregistrer( $id, $lue, $utilisateur = null ) {
		$id     = (int) $id;
		$compte = self::compte( $utilisateur );
		$plugin = self::plugin();
		$droit  = self::droit_sur( $id, $compte );
		$rendu  = array( 'issue' => 'interdit', 'version' => 0, 'champs' => array(), 'faits' => array(), 'etat' => '', 'par' => '' );
		if ( null === $droit || null === $plugin || $compte instanceof Notice_Archeomed_Regard ) {
			return $rendu;
		}
		$etat          = self::etat_de( $id );
		$rendu['etat'] = $etat;
		$valider       = isset( $_POST['suite'] ) && 'valider' === $_POST['suite'];
		$fige          = 'validee' === $etat;
		if ( $fige && ( isset( $_POST['texte_notice'] ) || ! empty( $_POST['retirer_renvoi'] ) || $valider ) ) {
			$rendu['issue'] = 'figee';
			return $rendu;
		}
		$corrige       = ( 'renvoi' === $droit['sorte'] ) ? ! empty( $_POST['retirer_renvoi'] ) : self::peut_corriger( $droit, $etat );
		$rendu['issue'] = 'inchangee';
		if ( $corrige ) {
			$verrou = self::prendre_le_verrou( $id, $compte->ID );
			if ( ! $verrou['tenu'] ) {
				$tenant         = get_userdata( $verrou['par'] );
				$rendu['issue'] = 'verrouillee';
				$rendu['par']   = $tenant ? (string) $tenant->display_name : '';
				return $rendu;
			}
			$portee = array( 'tout' => 'tout' === $droit['sorte'], 'renvoi' => 'renvoi' === $droit['sorte'] ? $droit['rubrique'] : '' );
			$rendu  = array_merge( $rendu, $plugin->enregistrer_la_relecture( $id, $lue, $compte, $portee ) );
			if ( 'renvoi' === $droit['sorte'] ) {
				self::liberer_le_verrou( $id, $compte->ID );
			}
			if ( ! in_array( $rendu['issue'], array( 'enregistree', 'inchangee' ), true ) ) {
				return $rendu;
			}
		}

		// ── L'état. Le coordinateur ne le fait qu'avancer ; la direction le
		// pose où elle veut avant de valider. Valider ne se fait que par son
		// bouton, et une notice validée ne se rouvre que par le sien. ─────
		$nouvel = $etat;
		if ( 'tout' === $droit['sorte'] && ! $fige ) {
			$demande = isset( $_POST['etat'] ) && is_string( $_POST['etat'] ) ? sanitize_key( wp_unslash( $_POST['etat'] ) ) : '';
			if ( $valider ) {
				$nouvel = 'validee';
			} elseif ( array_key_exists( $demande, self::ETATS ) && 'validee' !== $demande ) {
				$nouvel = $demande;
			}
		} elseif ( 'principale' === $droit['sorte'] && $corrige ) {
			if ( ! empty( $_POST['fond'] ) ) {
				$nouvel = 'a_soumettre';
			} elseif ( ! empty( $_POST['relue'] ) && 'a_relire' === $etat ) {
				$nouvel = 'relue';
			}
		}
		if ( $nouvel !== $etat ) {
			update_post_meta( $id, '_na_relecture_etat', $nouvel );
		}
		if ( 'validee' === $nouvel && ! $fige ) {
			// L'état d'avant, que la réouverture rendra ; la notice figée
			// n'a plus à être tenue.
			update_post_meta( $id, '_na_relecture_avant_validation', $etat );
			self::liberer_le_verrou( $id, $compte->ID );
		}
		$rendu['etat'] = $nouvel;

		// ── La remarque à la direction : hors de la notice, donc hors de ce
		// qui se publie. Chacune reste au journal, datée et signée. ───────
		$remarque = isset( $_POST['remarque'] ) && is_string( $_POST['remarque'] )
			? trim( sanitize_textarea_field( wp_unslash( $_POST['remarque'] ) ) ) : '';
		$remarque = function_exists( 'mb_substr' ) ? mb_substr( $remarque, 0, 2000 ) : substr( $remarque, 0, 2000 );

		$quoi = self::ce_qui_s_est_fait( $rendu );
		if ( $nouvel !== $etat ) {
			$quoi[] = 'état : ' . self::ETATS[ $etat ] . ' → ' . self::ETATS[ $nouvel ];
		}
		if ( ! empty( $quoi ) || '' !== $remarque ) {
			self::noter_au_journal( $id, $compte, $quoi, $etat, $nouvel, $remarque,
				'enregistree' === $rendu['issue'] ? (int) $rendu['version'] : 0 );
		}

		// ── Le Word de relecture attend sDrive, hors de cette requête. Rien
		// de ce qui suit ne peut défaire ce qui vient d'être enregistré. ──
		if ( 'enregistree' === $rendu['issue'] ) {
			try {
				self::inscrire_au_depot( $id, (int) $rendu['version'] );
				// Le Word net de la version, que le rangement dépose à côté.
				Notice_Archeomed_Rangement::programmer( $id );
			} catch ( Throwable $e ) {
				error_log( 'Notice Archeomed: dépôt du Word de relecture non inscrit pour la notice ' . $id . ' (' . $e->getMessage() . ').' );
			}
		}
		return $rendu;
	}

	/** Ce qu'un enregistrement a fait, en lignes pour le journal. */
	private static function ce_qui_s_est_fait( $rendu ) {
		$quoi = array();
		if ( 'enregistree' === $rendu['issue'] && ! empty( $rendu['champs'] ) ) {
			$quoi[] = 'version ' . (int) $rendu['version'] . ' — corrigé : ' . implode( ', ', (array) $rendu['champs'] );
		}
		$faits = isset( $rendu['faits'] ) && is_array( $rendu['faits'] ) ? $rendu['faits'] : array();
		if ( 'enregistree' !== $rendu['issue'] ) {
			return $quoi;
		}
		foreach ( isset( $faits['figures_masquees'] ) ? (array) $faits['figures_masquees'] : array() as $figure ) {
			$quoi[] = 'figure ' . (int) $figure['rang'] . ' retirée' . ( '' !== $figure['titre'] ? ' (« ' . $figure['titre'] . ' »)' : '' )
				. ' — motif : ' . $figure['motif'];
		}
		foreach ( isset( $faits['figures_retablies'] ) ? (array) $faits['figures_retablies'] : array() as $figure ) {
			$quoi[] = 'figure ' . (int) $figure['rang'] . ' rétablie' . ( '' !== $figure['titre'] ? ' (« ' . $figure['titre'] . ' »)' : '' );
		}
		if ( ! empty( $faits['termes_retires'] ) ) {
			$quoi[] = 'termes retirés : ' . implode( ', ', (array) $faits['termes_retires'] );
		}
		foreach ( isset( $faits['renvois_retires'] ) ? (array) $faits['renvois_retires'] : array() as $rubrique ) {
			$quoi[] = 'renvoi retiré : ' . $rubrique;
		}
		foreach ( isset( $faits['renvois_ajoutes'] ) ? (array) $faits['renvois_ajoutes'] : array() as $rubrique ) {
			$quoi[] = 'renvoi ajouté : ' . $rubrique;
		}
		return $quoi;
	}

	// ── Le dépôt des Word de relecture sur sDrive ──────────────────────────

	/**
	 * Inscrit le Word de relecture d'une version au dépôt sur sDrive. Même
	 * quand sDrive n'est pas relié : il partira dès qu'il le sera.
	 */
	public static function inscrire_au_depot( $id, $version ) {
		$id = (int) $id;
		add_post_meta( $id, '_na_relecture_a_deposer', (int) $version );
		update_post_meta( $id, '_na_relecture_apres', time() );
		delete_post_meta( $id, '_na_relecture_essais' );
		delete_post_meta( $id, '_na_relecture_raison' );
		if ( Notice_Archeomed_Rangement::peut_ranger() && ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time(), self::HOOK );
		}
	}

	/** Les notices dont un Word de relecture attend, la plus ancienne d'abord. */
	private static function dues( $jusqua, $combien ) {
		$requete = array(
			'post_type'        => Notice_Archeomed_File::CPT,
			'post_status'      => 'any',
			'fields'           => 'ids',
			'posts_per_page'   => (int) $combien,
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_key'         => '_na_relecture_apres',
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

	/** Combien de notices attendent le dépôt d'un Word de relecture. */
	public static function en_attente() {
		return count( self::dues( 0, -1 ) );
	}

	/** Remet la tâche en route si des Word attendent sans elle. */
	public static function reveiller() {
		if ( ! Notice_Archeomed_Rangement::peut_ranger() || wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		$premiere = self::dues( 0, 1 );
		if ( ! empty( $premiere ) ) {
			wp_schedule_single_event( max( time(), (int) get_post_meta( $premiere[0], '_na_relecture_apres', true ) ), self::HOOK );
		}
	}

	/** À la désactivation, la tâche s'efface ; l'attente reste dans les notices. */
	public static function desactiver() {
		wp_unschedule_hook( self::HOOK );
	}

	/**
	 * Ce que le planificateur appelle : les Word de relecture qui attendent,
	 * déposés un à un dans le dossier de leur notice, sur le modèle du
	 * rangement — un verrou, un budget de temps, et l'attente dans les
	 * notices. Une panne de sDrive arrête le passage sans rien coûter ; un
	 * refus propre à une notice lui coûte un essai.
	 */
	public static function en_tache() {
		if ( ! Notice_Archeomed_Rangement::peut_ranger() ) {
			wp_clear_scheduled_hook( self::HOOK );
			return;
		}
		$jeton = Notice_Archeomed_Rangement::prendre_le_verrou( self::VERROU_DEPOT );
		if ( ! $jeton ) {
			return;
		}
		$echeance = Notice_Archeomed_Rangement::echeance();
		$prochain = 0;
		try {
			$panne = Notice_Archeomed_Sdrive::joignable();
			if ( '' !== $panne ) {
				$prochain = time() + 15 * MINUTE_IN_SECONDS;
			} else {
				foreach ( self::dues( time(), 20 ) as $id ) {
					if ( microtime( true ) > $echeance - Notice_Archeomed_Rangement::MARGE ) {
						$prochain = time() + MINUTE_IN_SECONDS;
						break;
					}
					$raison = self::deposer_les_words( $id );
					if ( '' === $raison ) {
						delete_post_meta( $id, '_na_relecture_apres' );
						delete_post_meta( $id, '_na_relecture_essais' );
						delete_post_meta( $id, '_na_relecture_raison' );
						continue;
					}
					// Le compte plein ou sDrive tombé en route ne sont pas la
					// faute de cette notice : elle ne perd pas d'essai.
					if ( in_array( Notice_Archeomed_Sdrive::dernier_code(), array( 429, 507 ), true ) || '' !== Notice_Archeomed_Sdrive::joignable() ) {
						$prochain = time() + 15 * MINUTE_IN_SECONDS;
						break;
					}
					$essais = (int) get_post_meta( $id, '_na_relecture_essais', true ) + 1;
					update_post_meta( $id, '_na_relecture_essais', $essais );
					update_post_meta( $id, '_na_relecture_raison', $raison );
					if ( $essais >= self::ESSAIS ) {
						delete_post_meta( $id, '_na_relecture_apres' );
					} else {
						update_post_meta( $id, '_na_relecture_apres', time() + min( 6 * HOUR_IN_SECONDS, 5 * MINUTE_IN_SECONDS * ( 1 << ( $essais - 1 ) ) ) );
					}
				}
			}
		} finally {
			Notice_Archeomed_Rangement::rendre_le_verrou( $jeton, self::VERROU_DEPOT );
		}
		wp_clear_scheduled_hook( self::HOOK );
		if ( ! $prochain ) {
			$premiere = self::dues( 0, 1 );
			if ( ! empty( $premiere ) ) {
				$prochain = (int) get_post_meta( $premiere[0], '_na_relecture_apres', true );
			}
		}
		if ( $prochain > 0 ) {
			wp_schedule_single_event( max( $prochain, time() + MINUTE_IN_SECONDS ), self::HOOK );
		}
	}

	/**
	 * Dépose les Word de relecture qu'une notice attend, dans son dossier
	 * sur sDrive — celui que le rangement lui a fixé. Chacun se refait
	 * d'après les versions gardées, se dépose, puis s'efface du serveur.
	 * Rend une chaîne vide, ou ce qui a manqué.
	 */
	private static function deposer_les_words( $id ) {
		$plugin  = self::plugin();
		$dossier = Notice_Archeomed_Rangement::dossier_de( $id );
		if ( null === $plugin || '' === $dossier ) {
			return 'aucun dossier sur sDrive pour cette notice';
		}
		$cree = Notice_Archeomed_Sdrive::creer_le_dossier( $dossier );
		if ( '' !== $cree ) {
			return $cree;
		}
		foreach ( (array) get_post_meta( $id, '_na_relecture_a_deposer', false ) as $attendue ) {
			$version = (int) $attendue;
			$gardee  = self::version_gardee( $id, $version );
			if ( null === $gardee ) {
				delete_post_meta( $id, '_na_relecture_a_deposer', $attendue );
				continue;
			}
			$erreur = '';
			$word   = $plugin->word_de_relecture( $id, $version, $erreur );
			if ( '' === $word ) {
				return 'Word de la version ' . $version . ' : ' . $erreur;
			}
			$distant = $dossier . '/' . self::nom_du_word( $id, $version, $gardee['par'] );
			$refus   = Notice_Archeomed_Sdrive::deposer( $word, $distant );
			@unlink( $word );
			if ( '' !== $refus ) {
				return basename( $distant ) . ' : ' . $refus;
			}
			$deposes             = get_post_meta( $id, '_na_relecture_deposes', true );
			$deposes             = is_array( $deposes ) ? $deposes : array();
			$deposes[ $version ] = $distant;
			update_post_meta( $id, '_na_relecture_deposes', $deposes );
			delete_post_meta( $id, '_na_relecture_a_deposer', $attendue );
		}
		return '';
	}

	// ── Le sommaire ────────────────────────────────────────────────────────

	/**
	 * Le sommaire d'une rubrique : ses notices dans l'ordre du fascicule, et
	 * à part celles d'autres rubriques qui renvoient vers elle, rangées
	 * comme le fascicule range ses renvois.
	 */
	public static function sommaire( $rubrique ) {
		global $notice_archeomed_file;
		$plugin = self::plugin();
		if ( null === $plugin ) {
			return array( 'notices' => array(), 'renvois' => array() );
		}
		$renvois = array();
		if ( $notice_archeomed_file instanceof Notice_Archeomed_File ) {
			foreach ( $notice_archeomed_file->renvois_vers( $rubrique, true ) as $id => $d ) {
				$renvois[] = array(
					'id'      => (int) $id,
					// Rangé et titré sous la rubrique lue, comme au fascicule.
					'clef'    => $plugin->classement_de( array_merge( $d, array( 'rubrique_principale' => $rubrique ) ) ),
					'famille' => $plugin->titre_de_famille( array_merge( $d, array( 'rubrique_principale' => $rubrique ) ) ),
					'titre'   => $plugin->titre_de_liste( $d ),
				);
			}
		}
		return array(
			'notices' => $plugin->sommaire_de_la_rubrique( $rubrique ),
			'renvois' => $plugin->ranger_comme_au_fascicule( $renvois ),
		);
	}

	// ── L'écran ────────────────────────────────────────────────────────────

	/**
	 * L'adresse de la page publiée qui porte l'écran de relecture, ou une
	 * chaîne vide si aucune ne porte le shortcode.
	 */
	public static function page() {
		static $adresse = '';
		if ( '' !== $adresse ) {
			return $adresse;
		}
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type NOT IN ( 'revision', 'nav_menu_item', %s )"
			. " AND post_content LIKE %s ORDER BY ( post_type = 'page' ) DESC, ID ASC LIMIT 1",
			Notice_Archeomed_File::CPT, '%' . $wpdb->esc_like( '[' . self::SHORTCODE ) . '%' ) );
		$lien    = $id ? get_permalink( $id ) : '';
		$adresse = is_string( $lien ) ? $lien : '';
		return $adresse;
	}

	/** La page affichée porte-t-elle l'écran de relecture ? */
	private static function porte_l_ecran() {
		if ( is_admin() ) {
			return false;
		}
		$objet = get_post();
		return ( $objet instanceof WP_Post ) && has_shortcode( $objet->post_content, self::SHORTCODE );
	}

	/**
	 * Ce compte ne fait-il que relire ? Un coordinateur n'a rien à faire
	 * dans l'administration ; un administrateur, ou qui écrit par ailleurs
	 * sur le site, y garde sa place. La direction a sa propre garde.
	 */
	public static function est_coordinateur_seul( $utilisateur = null ) {
		$compte = self::compte( $utilisateur );
		return ( $compte instanceof WP_User ) && $compte->exists() && user_can( $compte, self::CAP )
			&& ! self::peut_tout( $compte ) && ! user_can( $compte, 'edit_posts' );
	}

	/**
	 * Ce compte est-il la direction, sans rien administrer d'autre ? Il a
	 * toute la Chronique — les notices reçues, leurs fiches, les réglages —
	 * et relit ; le reste de l'administration lui est fermé.
	 */
	public static function est_direction_seule( $utilisateur = null ) {
		$compte = self::compte( $utilisateur );
		return ( $compte instanceof WP_User ) && $compte->exists()
			&& user_can( $compte, Notice_Archeomed_Settings::CAPACITE )
			&& ! user_can( $compte, 'manage_options' ) && ! user_can( $compte, 'edit_posts' );
	}

	/**
	 * La page de relecture ne se met pas en cache, et ne se montre pas sans
	 * connexion : on est renvoyé à la connexion, qui ramène ici.
	 */
	public function garder_la_page() {
		if ( ! self::porte_l_ecran() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		if ( ! is_user_logged_in() ) {
			$args = array();
			foreach ( array( 'na_rubrique', 'na_notice' ) as $cle ) {
				if ( isset( $_GET[ $cle ] ) && is_scalar( $_GET[ $cle ] ) && absint( $_GET[ $cle ] ) ) {
					$args[ $cle ] = absint( $_GET[ $cle ] );
				}
			}
			wp_safe_redirect( wp_login_url( add_query_arg( $args, (string) get_permalink() ) ) );
			exit;
		}
	}

	/** L'éditeur du dépôt, servi par le site, sur la seule page de relecture. */
	public function charger_l_editeur() {
		if ( ! self::porte_l_ecran() || ! is_user_logged_in() ) {
			return;
		}
		$base = plugin_dir_url( __FILE__ ) . 'assets/quill/';
		wp_enqueue_style( 'quill-snow', $base . 'quill.snow.css', array(), '2.0.3' );
		wp_enqueue_script( 'quill', $base . 'quill.js', array(), '2.0.3', true );
	}

	/** Un coordinateur ne voit pas la barre d'administration sur le site. */
	public static function sans_barre_d_administration( $montrer ) {
		return self::est_coordinateur_seul() ? false : $montrer;
	}

	/**
	 * Après sa connexion, un coordinateur arrive sur la page de relecture —
	 * ou sur la notice qu'il demandait, si c'est d'elle qu'on l'a renvoyé.
	 */
	public static function apres_la_connexion( $vers, $demande, $compte ) {
		$page = self::page();
		// La direction qui ne demandait rien de précis va relire ; sans page
		// de relecture, aux notices reçues. Le tableau de bord ne lui sert à rien.
		if ( ( $compte instanceof WP_User ) && self::est_direction_seule( $compte ) ) {
			$rien = ! is_string( $demande ) || '' === $demande
				|| untrailingslashit( $demande ) === untrailingslashit( admin_url() );
			if ( ! $rien ) {
				return $vers;
			}
			return '' !== $page ? $page : self::notices_recues();
		}
		if ( '' === $page || ! ( $compte instanceof WP_User ) || ! self::est_coordinateur_seul( $compte ) ) {
			return $vers;
		}
		return ( is_string( $demande ) && 0 === strpos( $demande, $page ) ) ? $demande : $page;
	}

	/**
	 * Où renvoyer un compte qui ouvre une page de l'administration : la page
	 * de relecture pour un coordinateur, une chaîne vide pour qui y a sa
	 * place. Les deux portes que l'écran emploie restent ouvertes :
	 * « admin-ajax.php » pour le verrou, « admin-post.php » pour les gestes.
	 */
	public static function renvoi_de_l_administration( $utilisateur, $porte, $ajax = false ) {
		if ( $ajax || in_array( (string) $porte, array( 'admin-ajax.php', 'admin-post.php' ), true ) || ! self::est_coordinateur_seul( $utilisateur ) ) {
			return '';
		}
		$page = self::page();
		return '' !== $page ? $page : home_url( '/' );
	}

	/** La liste des notices reçues, première entrée du menu de la Chronique. */
	private static function notices_recues() {
		return admin_url( 'edit.php?post_type=' . Notice_Archeomed_File::CPT );
	}

	/**
	 * Où renvoyer la direction qui ouvre une page de l'administration : une
	 * chaîne vide pour ce qui est la Chronique — la liste des notices reçues
	 * et ce qui s'y range, dont les réglages, la fiche d'une notice,
	 * l'entrée « Relecture » et « options.php », qui enregistre les
	 * réglages ; la liste des notices sinon. L'adresse des réglages du temps
	 * où ils avaient leur propre menu mène à la nouvelle, avec son onglet.
	 *
	 * « admin-post.php » et « admin-ajax.php » ne passent pas par ici :
	 * chaque geste y contrôle son droit.
	 */
	public static function renvoi_de_la_direction( $utilisateur, $porte, $parametres ) {
		if ( ! self::est_direction_seule( $utilisateur ) ) {
			return '';
		}
		$porte      = (string) $porte;
		$parametres = (array) $parametres;
		$page       = isset( $parametres['page'] ) && is_scalar( $parametres['page'] ) ? sanitize_key( (string) $parametres['page'] ) : '';
		$type       = isset( $parametres['post_type'] ) && is_scalar( $parametres['post_type'] ) ? sanitize_key( (string) $parametres['post_type'] ) : '';
		// La fiche s'ouvre par « post », s'enregistre par « post_ID ».
		$fiche = 0;
		foreach ( array( 'post', 'post_ID' ) as $cle ) {
			if ( ! $fiche && isset( $parametres[ $cle ] ) && is_scalar( $parametres[ $cle ] ) ) {
				$fiche = absint( $parametres[ $cle ] );
			}
		}
		if ( 'options.php' === $porte
			|| ( 'admin.php' === $porte && self::PAGE === $page )
			|| ( 'edit.php' === $porte && Notice_Archeomed_File::CPT === $type )
			|| ( 'post.php' === $porte && $fiche && Notice_Archeomed_File::CPT === get_post_type( $fiche ) ) ) {
			return '';
		}
		if ( 'admin.php' === $porte && Notice_Archeomed_Settings::PAGE_SLUG === $page ) {
			$reglages = Notice_Archeomed_Settings::url();
			$garder   = array();
			foreach ( $parametres as $cle => $valeur ) {
				if ( is_string( $cle ) && is_scalar( $valeur ) && 'page' !== $cle ) {
					$garder[ sanitize_key( $cle ) ] = rawurlencode( (string) $valeur );
				}
			}
			return add_query_arg( $garder, $reglages );
		}
		return self::notices_recues();
	}

	/** La direction n'entre que dans ses pages de l'administration. */
	public static function garder_la_direction() {
		global $pagenow;
		$parametres = wp_unslash( $_GET );
		// Du formulaire, seule compte la fiche qu'il enregistre.
		if ( isset( $_POST['post_ID'] ) && is_scalar( $_POST['post_ID'] ) ) {
			$parametres['post_ID'] = absint( $_POST['post_ID'] );
		}
		$vers = self::renvoi_de_la_direction( null, (string) $pagenow, $parametres );
		if ( '' !== $vers ) {
			wp_safe_redirect( $vers );
			exit;
		}
	}

	/** Le coordinateur qui ouvre l'administration est ramené à sa page. */
	public function sortir_de_l_administration() {
		global $pagenow;
		$vers = self::renvoi_de_l_administration( null, (string) $pagenow, wp_doing_ajax() );
		if ( '' !== $vers ) {
			wp_safe_redirect( $vers );
			exit;
		}
	}

	/**
	 * L'entrée « Relecture » du menu d'administration : un lien vers la page
	 * du site, pour qui part de là.
	 */
	public function poser_le_menu() {
		// La direction n'a que la Chronique et la relecture : le tableau de
		// bord et le profil la renverraient aux notices reçues.
		if ( self::est_direction_seule() ) {
			remove_menu_page( 'index.php' );
			remove_menu_page( 'profile.php' );
		}
		add_menu_page(
			__( 'Relecture de la Chronique', 'notice-archeomed' ),
			__( 'Relecture', 'notice-archeomed' ),
			self::CAP,
			self::PAGE,
			array( $this, 'afficher_le_lien' ),
			'dashicons-visibility',
			26
		);
	}

	/**
	 * Ce que l'entrée du menu montre : où est la page de relecture, et, pour
	 * la direction, de quoi la voir comme un coordinateur.
	 */
	public function afficher_le_lien() {
		echo '<div class="wrap"><h1>' . esc_html__( 'Relecture de la Chronique', 'notice-archeomed' ) . '</h1>';
		self::dire_la_page();
		if ( self::peut_tout() ) {
			self::afficher_le_choix_du_regard();
		}
		echo '</div>';
	}

	/** L'adresse de la page de relecture, ou ce qu'il faut faire pour en avoir une. */
	private static function dire_la_page() {
		$page = self::page();
		if ( '' !== $page ) {
			echo '<p>' . esc_html__( 'La relecture se fait sur le site, à cette adresse :', 'notice-archeomed' )
				. ' <a href="' . esc_url( $page ) . '">' . esc_html( $page ) . '</a></p>';
			return;
		}
		echo '<div class="notice inline notice-warning"><p>' . esc_html( sprintf(
			'Aucune page publiée ne porte l’écran de relecture. Créez une page, posez-y le shortcode [%s], publiez-la : c’est là que coordinateurs et direction relisent.',
			self::SHORTCODE ) ) . '</p></div>';
	}

	// ── La relecture vue comme un coordinateur ────────────────────────────

	/**
	 * Le regard que ce compte emprunte, ou null s'il voit la relecture comme
	 * lui-même : celui d'un coordinateur nommé, ou celui de la coordination
	 * d'une rubrique, qui n'a pas forcément de compte.
	 *
	 * Le choix est gardé pour ce compte seul, et relu à chaque requête : il
	 * ne vaut que pour un compte qui relit tout, et que pour un regard que
	 * les réglages permettent encore. Un choix qui ne vaut plus s'efface.
	 * Rien ne le lit hors de l'écran de relecture et des gestes qu'il
	 * appelle : l'administration, sa barre et la connexion l'ignorent.
	 */
	public static function vue() {
		$reel = wp_get_current_user();
		if ( ! ( $reel instanceof WP_User ) || ! $reel->exists() ) {
			return null;
		}
		$cle   = self::cle_de_la_vue( $reel->ID );
		$choix = get_transient( $cle );
		if ( false === $choix ) {
			return null;
		}
		$regard = self::peut_tout( $reel ) ? self::regard_choisi( $choix ) : null;
		if ( null === $regard ) {
			delete_transient( $cle );
		}
		return $regard;
	}

	/** Le compte dont l'écran prend les droits : le regard emprunté, ou soi. */
	private static function regard() {
		$vue = self::vue();
		return null !== $vue ? $vue : wp_get_current_user();
	}

	/** Où se garde le choix d'un compte. */
	private static function cle_de_la_vue( $compte ) {
		return 'na_relecture_vue_' . (int) $compte;
	}

	/**
	 * Le regard qu'un choix désigne, s'il est permis, ou null. Un compte doit
	 * relire sans tout relire, et tenir au moins une rubrique des réglages ;
	 * une rubrique doit être du fascicule.
	 */
	public static function regard_choisi( $choix ) {
		$plugin = self::plugin();
		if ( null === $plugin || ! is_array( $choix ) || ! isset( $choix['sorte'], $choix['valeur'] ) || ! is_scalar( $choix['valeur'] ) ) {
			return null;
		}
		if ( 'compte' === $choix['sorte'] ) {
			$compte = get_userdata( absint( $choix['valeur'] ) );
			if ( ! ( $compte instanceof WP_User ) || self::peut_tout( $compte ) || ! user_can( $compte, self::CAP ) || empty( self::rubriques_de( $compte ) ) ) {
				return null;
			}
			return Notice_Archeomed_Regard::du_compte( $compte );
		}
		if ( 'rubrique' === $choix['sorte'] && in_array( (string) $choix['valeur'], $plugin->rubriques(), true ) ) {
			return Notice_Archeomed_Regard::de_la_rubrique( (string) $choix['valeur'] );
		}
		return null;
	}

	/**
	 * Le choix que porte une valeur de la liste : « compte:12 » pour un
	 * compte, « rubrique:2 » pour la deuxième rubrique ; null sinon. La
	 * rubrique se garde par son nom, qui ne bouge pas quand l'ordre change.
	 */
	private static function choix_de_la_valeur( $valeur ) {
		$plugin = self::plugin();
		if ( null === $plugin || ! preg_match( '/^(compte|rubrique):([0-9]+)$/', (string) $valeur, $m ) ) {
			return null;
		}
		if ( 'compte' === $m[1] ) {
			return array( 'sorte' => 'compte', 'valeur' => (int) $m[2] );
		}
		$toutes = $plugin->rubriques();
		return isset( $toutes[ (int) $m[2] - 1 ] ) ? array( 'sorte' => 'rubrique', 'valeur' => $toutes[ (int) $m[2] - 1 ] ) : null;
	}

	/** La valeur de la liste qui désigne ce regard. */
	private static function valeur_du_regard( $regard ) {
		$plugin = self::plugin();
		if ( ! ( $regard instanceof Notice_Archeomed_Regard ) || null === $plugin ) {
			return '';
		}
		if ( null === $regard->rubriques_fixees ) {
			return 'compte:' . (int) $regard->ID;
		}
		$rang = array_search( $regard->rubriques_fixees[0], $plugin->rubriques(), true );
		return false !== $rang ? 'rubrique:' . ( $rang + 1 ) : '';
	}

	/**
	 * Les regards qu'on peut emprunter, en valeurs de la liste : les comptes
	 * que les réglages affectent à une rubrique, puis chaque rubrique.
	 */
	public static function regards_possibles() {
		$plugin   = self::plugin();
		$possible = array( 'comptes' => array(), 'rubriques' => array() );
		if ( null === $plugin ) {
			return $possible;
		}
		$reglages = self::reglages();
		$vus      = array();
		foreach ( $plugin->rubriques() as $rang => $rubrique ) {
			$confiee = isset( $reglages['coordinateurs'][ $rubrique ] ) ? array_map( 'intval', (array) $reglages['coordinateurs'][ $rubrique ] ) : array();
			foreach ( $confiee as $compte ) {
				if ( isset( $vus[ $compte ] ) ) {
					continue;
				}
				$vus[ $compte ] = true;
				$regard         = self::regard_choisi( array( 'sorte' => 'compte', 'valeur' => $compte ) );
				if ( null !== $regard ) {
					$possible['comptes'][ 'compte:' . $compte ] = $regard->display_name . ' — ' . self::rubriques_courtes( self::rubriques_de( $regard ) );
				}
			}
			$possible['rubriques'][ 'rubrique:' . ( $rang + 1 ) ] = $rubrique . ( empty( $confiee ) ? ' — sans coordinateur' : '' );
		}
		asort( $possible['comptes'], SORT_STRING | SORT_FLAG_CASE );
		return $possible;
	}

	/** « rubrique II », « rubriques II et IV » : les rubriques par leur seul numéro. */
	private static function rubriques_courtes( $rubriques ) {
		$numeros = array();
		foreach ( (array) $rubriques as $rubrique ) {
			$numeros[] = (string) preg_replace( '/^([IVX]+)\..*$/u', '$1', (string) $rubrique );
		}
		$derniere = array_pop( $numeros );
		return empty( $numeros ) ? 'rubrique ' . $derniere : 'rubriques ' . implode( ', ', $numeros ) . ' et ' . $derniere;
	}

	/**
	 * Ce qu'on voit, en une phrase : « comme Jeanne Martin (coordination de
	 * la rubrique II) », ou, sans compte, « comme la verrait la coordination
	 * de la rubrique II ». Le nom tel que le compte le porte, sans accord
	 * deviné d'après le prénom.
	 */
	public static function phrase_du_regard( $regard ) {
		$rubriques = self::rubriques_courtes( self::rubriques_de( $regard ) );
		$de        = ( 0 === strpos( $rubriques, 'rubriques ' ) ? 'des ' : 'de la ' ) . $rubriques;
		if ( $regard instanceof Notice_Archeomed_Regard && null === $regard->rubriques_fixees ) {
			return sprintf( 'Vous voyez la relecture comme %1$s (coordination %2$s).', $regard->display_name, $de );
		}
		return sprintf( 'Vous voyez la relecture comme la verrait la coordination %s.', $de );
	}

	/**
	 * Garde pour ce compte le regard que désigne une valeur de la liste ;
	 * rend vrai si le choix est permis. Le bouton passe par ici, et les
	 * essais aussi.
	 */
	public static function emprunter_le_regard( $compte, $valeur ) {
		$reel  = get_userdata( (int) $compte );
		$choix = self::choix_de_la_valeur( $valeur );
		if ( ! ( $reel instanceof WP_User ) || ! self::peut_tout( $reel ) || null === $choix || null === self::regard_choisi( $choix ) ) {
			return false;
		}
		set_transient( self::cle_de_la_vue( $reel->ID ), $choix, self::DUREE_DE_LA_VUE );
		return true;
	}

	/** Le choix d'un regard, dans l'entrée « Relecture » du menu. */
	private static function afficher_le_choix_du_regard() {
		$possibles = self::regards_possibles();
		$vue       = self::vue();
		$choisie   = self::valeur_du_regard( $vue );
		$page      = self::page();
		echo '<h2>' . esc_html__( 'Voir la relecture comme un coordinateur', 'notice-archeomed' ) . '</h2>'
			. '<p class="description" style="max-width:46em">' . esc_html__( 'La page de relecture telle que la voit un coordinateur : ses rubriques, ses notices et ses renvois, ce qu’il corrige et ce qu’il lit seulement, avec ses boutons. Rien de ce qu’on y fait ne s’enregistre, et rien ne s’y verrouille. Le choix ne vaut que pour votre compte, deux heures au plus ; l’administration ne change pas. Avant la clôture de la campagne, la page se montre comme si elle était close.', 'notice-archeomed' ) . '</p>';
		if ( isset( $_GET['na_vue'] ) && 'refusee' === $_GET['na_vue'] ) {
			echo '<div class="notice inline notice-error"><p>' . esc_html__( 'Ce choix n’est plus possible : le compte ne coordonne plus de rubrique, ou la page de relecture manque.', 'notice-archeomed' ) . '</p></div>';
		}
		if ( null !== $vue ) {
			echo '<div class="notice inline notice-info"><p>' . esc_html( self::phrase_du_regard( $vue ) ) . '</p><p>'
				. ( '' !== $page ? '<a class="button button-primary" href="' . esc_url( $page ) . '">' . esc_html__( 'Voir la relecture', 'notice-archeomed' ) . '</a> ' : '' )
				. '<a class="button" href="' . esc_url( self::url_pour_quitter_la_vue( 'admin' ) ) . '">' . esc_html__( 'Revenir à ma vue', 'notice-archeomed' ) . '</a></p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="na_relecture_vue">';
		wp_nonce_field( 'na_relecture_vue' );
		echo '<p><label for="na-rel-regard">' . esc_html__( 'Voir comme', 'notice-archeomed' ) . '</label> <select id="na-rel-regard" name="regard">';
		$aucun = array( 'comptes' => 'Aucun compte n’est affecté à une rubrique', 'rubriques' => 'Aucune rubrique' );
		foreach ( array( 'comptes' => 'Un coordinateur', 'rubriques' => 'Une rubrique' ) as $sorte => $titre ) {
			echo '<optgroup label="' . esc_attr( $titre ) . '">';
			if ( empty( $possibles[ $sorte ] ) ) {
				echo '<option value="" disabled>' . esc_html( $aucun[ $sorte ] ) . '</option>';
			}
			foreach ( $possibles[ $sorte ] as $valeur => $libelle ) {
				echo '<option value="' . esc_attr( $valeur ) . '"' . selected( $valeur, $choisie, false ) . '>' . esc_html( $libelle ) . '</option>';
			}
			echo '</optgroup>';
		}
		echo '</select> ';
		submit_button( __( 'Voir la relecture comme lui', 'notice-archeomed' ), 'secondary', 'submit', false, '' === $page ? array( 'disabled' => 'disabled' ) : null );
		echo '</p></form>';
	}

	/** L'adresse qui rend à chacun sa propre vue ; vers l'administration si l'on en vient. */
	public static function url_pour_quitter_la_vue( $retour = '' ) {
		$args = array( 'action' => 'na_relecture_vue_quitter' );
		if ( 'admin' === $retour ) {
			$args['retour'] = 'admin';
		}
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'na_relecture_vue_quitter' );
	}

	/** Le bouton « Voir la relecture comme lui » : le choix, gardé pour ce compte. */
	public function choisir_la_vue() {
		if ( ! is_user_logged_in() || ! self::peut_tout( wp_get_current_user() ) ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		check_admin_referer( 'na_relecture_vue' );
		$valeur = isset( $_POST['regard'] ) && is_string( $_POST['regard'] ) ? sanitize_text_field( wp_unslash( $_POST['regard'] ) ) : '';
		$page   = self::page();
		if ( '' === $page || ! self::emprunter_le_regard( get_current_user_id(), $valeur ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'na_vue' => 'refusee' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		wp_safe_redirect( $page );
		exit;
	}

	/**
	 * « Revenir à ma vue » : toujours permis, à qui est connecté, même s'il
	 * a perdu entre-temps le droit de tout relire. On n'efface que son choix.
	 */
	public function quitter_la_vue() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		check_admin_referer( 'na_relecture_vue_quitter' );
		delete_transient( self::cle_de_la_vue( get_current_user_id() ) );
		$page = self::page();
		$vers = ( '' === $page || ( isset( $_GET['retour'] ) && 'admin' === $_GET['retour'] ) ) ? add_query_arg( 'page', self::PAGE, admin_url( 'admin.php' ) ) : $page;
		wp_safe_redirect( $vers );
		exit;
	}

	/** Le refus d'un geste pendant la vue : rien n'est fait, et l'on dit pourquoi. */
	private static function refuser_pendant_la_vue( $vue, $id ) {
		self::dire( 'warning', array( self::phrase_du_regard( $vue ) . ' Rien n’est enregistré : revenez à votre vue pour corriger.' ) );
		wp_safe_redirect( self::url( self::rubrique_demandee( self::rubriques_de( $vue ), $_POST ), null === self::droit_sur( $id, $vue ) ? 0 : $id ) );
		exit;
	}

	/**
	 * Le bandeau de la vue : qui l'on regarde, que rien ne s'enregistre, et
	 * le bouton pour en sortir. Avant la clôture, il dit aussi que ce
	 * coordinateur ne voit encore rien.
	 */
	private static function afficher_le_bandeau( $vue ) {
		echo '<div class="na-rel-message na-rel-message--warning na-rel-bandeau" role="status"><p><strong>'
			. esc_html( self::phrase_du_regard( $vue ) . ' Rien de ce que vous faites ici ne s’enregistre.' ) . '</strong></p>';
		if ( ! self::campagne_close() ) {
			$qui = null === $vue->rubriques_fixees ? $vue->display_name : 'la coordination de la ' . self::rubriques_courtes( $vue->rubriques_fixees );
			echo '<p>' . esc_html( sprintf( 'La campagne n’est pas close : aujourd’hui, %s ne voit encore aucune notice.', $qui ) ) . '</p>';
		}
		echo '<p><a class="na-rel-bouton" href="' . esc_url( self::url_pour_quitter_la_vue() ) . '">' . esc_html__( 'Revenir à ma vue', 'notice-archeomed' ) . '</a></p></div>';
	}

	/**
	 * Rend inertes les champs et les boutons d'un écran vu comme un autre :
	 * ils restent à leur place, tels qu'il les verrait, mais rien ne se
	 * remplit ni ne s'envoie. Seul l'agrandissement d'une figure reste
	 * permis : il ne fait que montrer. Les scripts ne sont pas touchés.
	 */
	private static function neutraliser( $html ) {
		return (string) preg_replace_callback( '#<script\b.*?</script>|<(input|select|textarea|button)\b([^>]*)>#is', function ( $m ) {
			if ( empty( $m[1] ) || false !== strpos( $m[2], 'na-rel-agrandir' ) || preg_match( '/\sdisabled\b/i', $m[2] ) ) {
				return $m[0];
			}
			return '<' . $m[1] . ' disabled' . $m[2] . '>';
		}, (string) $html );
	}

	/** L'adresse de l'écran, pour une rubrique et une notice. */
	public static function url( $rubrique = '', $notice = 0 ) {
		$plugin = self::plugin();
		$rang   = ( null !== $plugin && '' !== $rubrique ) ? array_search( $rubrique, $plugin->rubriques(), true ) : false;
		$page   = self::page();
		$args   = array();
		if ( false !== $rang ) {
			$args['na_rubrique'] = $rang + 1;
		}
		if ( $notice ) {
			$args['na_notice'] = (int) $notice;
		}
		return add_query_arg( $args, '' !== $page ? $page : add_query_arg( 'page', self::PAGE, admin_url( 'admin.php' ) ) );
	}

	/** L'adresse qui sert l'aperçu d'une figure à qui peut lire la notice. */
	public static function url_de_l_apercu( $id, $rang ) {
		return wp_nonce_url( add_query_arg( array( 'action' => 'na_relecture_apercu', 'notice' => (int) $id, 'rang' => (int) $rang ),
			admin_url( 'admin-post.php' ) ), 'na_relecture_apercu_' . (int) $id );
	}

	/** Garde un message pour la page suivante de ce compte. */
	private static function dire( $type, $lignes ) {
		set_transient( 'na_relecture_message_' . get_current_user_id(),
			array( 'type' => (string) $type, 'lignes' => array_values( array_map( 'strval', (array) $lignes ) ) ), 5 * MINUTE_IN_SECONDS );
	}

	/** La rubrique demandée dans l'adresse, parmi celles du compte ; la première sinon. */
	private static function rubrique_demandee( $siennes, $source, $cle = 'rubrique' ) {
		$plugin = self::plugin();
		$rang   = isset( $source[ $cle ] ) && is_scalar( $source[ $cle ] ) ? absint( $source[ $cle ] ) : 0;
		$toutes = null !== $plugin ? $plugin->rubriques() : array();
		if ( $rang >= 1 && isset( $toutes[ $rang - 1 ] ) && in_array( $toutes[ $rang - 1 ], $siennes, true ) ) {
			return $toutes[ $rang - 1 ];
		}
		return ! empty( $siennes ) ? $siennes[0] : '';
	}

	/** Les scripts de l'écran, mis de côté pour le pied de page. */
	private $scripts_du_pied = array();

	/**
	 * Le regard sous lequel l'écran se dessine, le temps de le dessiner :
	 * celui d'un coordinateur, que la direction emprunte, ou null.
	 */
	private $regard_de_l_ecran = null;

	/** Le compte dont l'écran en cours prend les droits. */
	private function vu_par() {
		return null !== $this->regard_de_l_ecran ? $this->regard_de_l_ecran : wp_get_current_user();
	}

	/**
	 * Le shortcode : l'écran de relecture dans le contenu de la page, ses
	 * scripts renvoyés au pied de page — dans le contenu, un thème à blocs
	 * changeait des « && » en entités et le script tombait en erreur, comme
	 * pour le formulaire de dépôt.
	 */
	public function afficher() {
		$this->regard_de_l_ecran = self::vue();
		ob_start();
		$this->ecran();
		$html = (string) ob_get_clean();
		// Vu comme un autre, rien ne se remplit ni ne s'envoie.
		if ( null !== $this->regard_de_l_ecran ) {
			$html = self::neutraliser( $html );
		}
		$this->regard_de_l_ecran = null;
		if ( did_action( 'wp_footer' ) ) {
			return $html;
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

	/** Les scripts de l'écran, après ceux que la page charge. */
	public function poser_les_scripts_du_pied() {
		echo implode( "\n", $this->scripts_du_pied );   // écrits par l'écran, déjà échappés
		$this->scripts_du_pied = array();
	}

	/** L'écran de relecture : le sommaire d'un côté, la notice de l'autre. */
	private function ecran() {
		echo '<div class="na-rel">';
		self::poser_les_styles( true );
		if ( ! is_user_logged_in() ) {
			echo '<p><a href="' . esc_url( wp_login_url( (string) get_permalink() ) ) . '">' . esc_html__( 'Connectez-vous pour relire.', 'notice-archeomed' ) . '</a></p></div>';
			return;
		}
		// Vu comme un coordinateur, tout se calcule avec ses droits à lui :
		// rubriques, notices, ce qui se corrige et ce qui se lit seulement.
		$vue     = $this->regard_de_l_ecran;
		$compte  = $this->vu_par();
		$plugin  = self::plugin();
		$siennes = self::rubriques_de( $compte );
		// Un compte sans droit ne lit qu'une phrase : ni rubrique, ni notice,
		// ni même si la relecture est ouverte.
		if ( null === $plugin || ( ! user_can( $compte, self::CAP ) && ! self::peut_tout( $compte ) ) ) {
			echo '<p>' . esc_html__( 'Cette page est réservée aux relecteurs de la Chronique.', 'notice-archeomed' ) . '</p></div>';
			return;
		}
		$message = get_transient( 'na_relecture_message_' . get_current_user_id() );
		if ( is_array( $message ) && ! empty( $message['lignes'] ) ) {
			delete_transient( 'na_relecture_message_' . get_current_user_id() );
			echo '<div class="na-rel-message na-rel-message--' . esc_attr( in_array( $message['type'], array( 'success', 'warning', 'error' ), true ) ? $message['type'] : 'info' ) . '" role="status">';
			foreach ( (array) $message['lignes'] as $ligne ) {
				echo '<p>' . esc_html( $ligne ) . '</p>';
			}
			echo '</div>';
		}
		if ( null !== $vue ) {
			self::afficher_le_bandeau( $vue );
		}
		// Le regard d'un coordinateur voit la relecture comme si la campagne
		// était close : c'est pour la préparer qu'on l'emprunte.
		if ( null === $vue && ! self::campagne_close() ) {
			echo '<p>' . esc_html__( 'La relecture s’ouvrira quand la campagne sera close.', 'notice-archeomed' );
			if ( Notice_Archeomed_Settings::peut_regler() ) {
				echo ' <a href="' . esc_url( Notice_Archeomed_Settings::url( 'relecture' ) ) . '">' . esc_html__( 'Régler la relecture', 'notice-archeomed' ) . '</a>';
			}
			echo '</p></div>';
			return;
		}
		if ( empty( $siennes ) ) {
			echo '<p>' . esc_html__( 'Aucune rubrique ne vous est confiée.', 'notice-archeomed' ) . '</p></div>';
			return;
		}
		$rubrique = self::rubrique_demandee( $siennes, $_GET, 'na_rubrique' );
		$id       = isset( $_GET['na_notice'] ) && is_scalar( $_GET['na_notice'] ) ? absint( $_GET['na_notice'] ) : 0;
		$droit    = $id ? self::droit_sur( $id, $compte ) : null;
		$tout     = self::peut_tout( $compte );
		// La direction lit la notice sous sa rubrique, quoi que dise l'adresse.
		if ( $tout && null !== $droit && in_array( $droit['rubrique'], $siennes, true ) ) {
			$rubrique = $droit['rubrique'];
		}

		// La direction voit toutes les rubriques ; un coordinateur, celles que
		// les réglages lui confient, et rien d'autre. On le dit à qui essaie
		// l'écran depuis un compte de la direction.
		if ( $tout ) {
			echo '<p class="na-rel-note">' . esc_html__( 'Vous voyez toutes les rubriques. Un coordinateur ne voit que celles qui lui sont confiées dans les réglages de la relecture, et les notices qui y renvoient.', 'notice-archeomed' );
			if ( Notice_Archeomed_Settings::peut_regler() ) {
				echo ' <a href="' . esc_url( Notice_Archeomed_Settings::url( 'relecture' ) ) . '">' . esc_html__( 'Confier les rubriques', 'notice-archeomed' ) . '</a>';
			}
			echo '</p>';
		}
		if ( ! $tout && count( $siennes ) > 1 ) {
			echo '<nav class="na-rel-onglets" aria-label="' . esc_attr__( 'Rubriques', 'notice-archeomed' ) . '">';
			foreach ( $siennes as $une ) {
				echo '<a class="na-rel-onglet' . ( $une === $rubrique ? ' na-rel-onglet--actif' : '' ) . '" href="' . esc_url( self::url( $une ) ) . '"'
					. ( $une === $rubrique ? ' aria-current="page"' : '' ) . ' title="' . esc_attr( $une ) . '">' . esc_html( preg_replace( '/^([IVX]+)\..*$/u', '$1', $une ) ) . '</a>';
			}
			echo '</nav>';
		}
		echo '<div class="na-rel-cadre">';
		if ( $tout ) {
			$this->afficher_le_sommaire_de_tout( $rubrique, $id );
		} else {
			$this->afficher_le_sommaire( $rubrique, self::sommaire( $rubrique ), $id );
		}
		echo '<div class="na-rel-notice">';
		if ( $id && null === $droit ) {
			echo '<p>' . esc_html__( 'Cette notice n’est pas de vos rubriques.', 'notice-archeomed' ) . '</p>';
		} elseif ( $id ) {
			$this->afficher_la_notice( $id, $droit, $rubrique );
		} else {
			echo '<p>' . esc_html__( 'Choisissez une notice dans le sommaire.', 'notice-archeomed' ) . '</p>';
		}
		echo '</div></div></div>';
	}

	/** Le sommaire latéral : sous-rubriques, notices, puis renvois à part. */
	private function afficher_le_sommaire( $rubrique, $sommaire, $ouverte ) {
		echo '<nav class="na-rel-sommaire" aria-label="' . esc_attr__( 'Sommaire de la rubrique', 'notice-archeomed' ) . '">';
		echo '<h2>' . esc_html( $rubrique ) . '</h2>';
		// La sous-rubrique ne s'écrit que lorsqu'elle change, d'un bloc à
		// l'autre aussi : sous les renvois, la redire quand c'est la même que
		// celle de la dernière notice chargeait le sommaire sans rien apprendre.
		$famille = null;
		foreach ( array( 'notices' => '', 'renvois' => __( 'Renvois vers cette rubrique (lecture seule)', 'notice-archeomed' ) ) as $bloc => $titre ) {
			if ( empty( $sommaire[ $bloc ] ) ) {
				if ( 'notices' === $bloc ) {
					echo '<p>' . esc_html__( 'Aucune notice dans cette rubrique.', 'notice-archeomed' ) . '</p>';
				}
				continue;
			}
			if ( '' !== $titre ) {
				echo '<h2 class="na-rel-renvois">' . esc_html( $titre ) . '</h2>';
			}
			$this->lister_les_notices( $rubrique, $sommaire[ $bloc ], $ouverte, $famille );
		}
		echo '</nav>';
	}

	/**
	 * Le sommaire de la direction : tout le fascicule, une rubrique par
	 * volet replié, qu'un clic sur son triangle déplie. Seule la rubrique de
	 * la notice ouverte l'est d'emblée. Chaque volet dit combien de ses
	 * notices sont validées. Pas de renvois : chaque notice y est déjà, sous
	 * sa propre rubrique.
	 */
	private function afficher_le_sommaire_de_tout( $courante, $ouverte ) {
		$plugin = self::plugin();
		echo '<nav class="na-rel-sommaire na-rel-sommaire--tout" aria-label="' . esc_attr__( 'Sommaire du fascicule', 'notice-archeomed' ) . '">';
		echo '<h2>' . esc_html__( 'Sommaire du fascicule', 'notice-archeomed' ) . '</h2>';
		foreach ( $plugin->rubriques() as $rubrique ) {
			$entrees  = $plugin->sommaire_de_la_rubrique( $rubrique );
			$validees = 0;
			foreach ( $entrees as $entree ) {
				$validees += 'validee' === self::etat_de( $entree['id'] ) ? 1 : 0;
			}
			$faite = ! empty( $entrees ) && count( $entrees ) === $validees;
			echo '<details class="na-rel-rubrique' . ( $faite ? ' na-rel-rubrique--faite' : '' ) . '"' . ( $rubrique === $courante ? ' open' : '' ) . '>'
				. '<summary><span class="na-rel-rubrique-nom">' . esc_html( $rubrique ) . '</span> <span class="na-rel-compte">'
				. esc_html( empty( $entrees ) ? 'aucune notice'
					: sprintf( $validees > 1 ? '%1$d validées sur %2$d' : '%1$d validée sur %2$d', $validees, count( $entrees ) ) )
				. '</span></summary>';
			if ( ! empty( $entrees ) ) {
				$famille = null;
				$this->lister_les_notices( $rubrique, $entrees, $ouverte, $famille );
			}
			echo '</details>';
		}
		echo '</nav>';
	}

	/**
	 * Les notices d'un bloc du sommaire, sous leurs sous-rubriques, avec
	 * l'état de chacune et qui la tient ouverte. La sous-rubrique en cours
	 * passe d'un bloc à l'autre, pour ne pas se redire.
	 */
	private function lister_les_notices( $rubrique, $entrees, $ouverte, &$famille ) {
		$liste = false;
		foreach ( $entrees as $entree ) {
			if ( $entree['famille'] !== $famille ) {
				echo ( $liste ? '</ol>' : '' ) . '<h3>' . esc_html( $entree['famille'] ) . '</h3><ol>';
				$famille = $entree['famille'];
				$liste   = true;
			} elseif ( ! $liste ) {
				echo '<ol>';
				$liste = true;
			}
			$etat   = self::etat_de( $entree['id'] );
			$verrou = self::verrou_de( $entree['id'] );
			$tenant = ( $verrou['par'] && $verrou['par'] !== (int) $this->vu_par()->ID ) ? get_userdata( $verrou['par'] ) : null;
			echo '<li' . ( (int) $entree['id'] === (int) $ouverte ? ' class="na-rel-ouverte"' : '' ) . '>'
				. '<a href="' . esc_url( self::url( $rubrique, $entree['id'] ) ) . '"' . ( (int) $entree['id'] === (int) $ouverte ? ' aria-current="true"' : '' ) . '>'
				. esc_html( $entree['titre'] ) . '</a>'
				. ' <span class="na-rel-etat na-rel-etat--' . esc_attr( $etat ) . '">' . esc_html( self::ETATS[ $etat ] ) . '</span>'
				. ( $tenant ? ' <span class="na-rel-verrou">' . esc_html( sprintf( 'ouverte par %s', $tenant->display_name ) ) . '</span>' : '' )
				. '</li>';
		}
		if ( $liste ) {
			echo '</ol>';
		}
	}

	/** La notice ouverte : son en-tête, le formulaire ou la lecture seule, puis son histoire. */
	private function afficher_la_notice( $id, $droit, $rubrique ) {
		$plugin = self::plugin();
		$notice = $plugin->notice_a_relire( $id );
		if ( null === $notice ) {
			echo '<p>' . esc_html__( 'Notice inconnue.', 'notice-archeomed' ) . '</p>';
			return;
		}
		$etat    = self::etat_de( $id );
		$corrige = 'renvoi' !== $droit['sorte'] && self::peut_corriger( $droit, $etat );
		$tenant  = null;
		if ( $corrige ) {
			// Vu comme un coordinateur, on regarde le verrou sans le prendre :
			// la notice se montre comme il la verrait, ouverte par un autre
			// si un autre la tient.
			if ( null !== $this->regard_de_l_ecran ) {
				$verrou         = self::verrou_de( $id );
				$verrou['tenu'] = 0 === $verrou['par'] || (int) $this->regard_de_l_ecran->ID === $verrou['par'];
			} else {
				$verrou = self::prendre_le_verrou( $id, get_current_user_id() );
			}
			if ( ! $verrou['tenu'] ) {
				$corrige = false;
				$tenant  = get_userdata( $verrou['par'] );
			}
		}
		echo '<h2 class="na-rel-titre">' . esc_html( $plugin->titre_de_liste( $notice['d'] ) ) . '</h2>';
		echo '<p class="na-rel-entete"><span class="na-rel-etat na-rel-etat--' . esc_attr( $etat ) . '">' . esc_html( self::ETATS[ $etat ] ) . '</span> '
			. esc_html( sprintf( 'Version %d', $notice['version'] ) ) . '</p>';
		if ( $tenant ) {
			echo '<div class="na-rel-message na-rel-message--warning"><p>' . esc_html( sprintf(
				'Cette notice est ouverte par %s : elle se lit, mais ne se corrige pas tant qu’elle ne l’a pas rendue.', $tenant->display_name ) ) . '</p></div>';
		} elseif ( 'a_soumettre' === $etat && 'tout' === $droit['sorte'] ) {
			echo '<div class="na-rel-message na-rel-message--warning"><p>' . esc_html__( 'Modification de fond : un accord écrit de l’auteur est nécessaire. Le Word à suivi de modifications lui part quand vous le demandez.', 'notice-archeomed' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="na_relecture_soumettre">'
				. '<input type="hidden" name="notice" value="' . (int) $id . '"><input type="hidden" name="rubrique" value="' . esc_attr( (string) ( array_search( $rubrique, $plugin->rubriques(), true ) + 1 ) ) . '">';
			wp_nonce_field( 'na_relecture_soumettre_' . (int) $id );
			echo '<p><button type="submit" class="na-rel-bouton">' . esc_html__( 'Soumettre à l’auteur', 'notice-archeomed' ) . '</button></p></form></div>';
		}
		if ( 'validee' === $etat ) {
			echo '<div class="na-rel-message na-rel-message--success"><p><strong>' . esc_html__( 'Validée — version définitive.', 'notice-archeomed' ) . '</strong> ';
			if ( 'tout' === $droit['sorte'] ) {
				echo esc_html__( 'Elle ne se corrige plus. Pour la reprendre, rouvrez-la : la réouverture reste au journal, avec son motif.', 'notice-archeomed' ) . '</p>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="na-rel-form na-rel-rouvrir"><input type="hidden" name="action" value="na_relecture_rouvrir">'
					. '<input type="hidden" name="notice" value="' . (int) $id . '"><input type="hidden" name="rubrique" value="' . esc_attr( (string) ( array_search( $rubrique, $plugin->rubriques(), true ) + 1 ) ) . '">';
				wp_nonce_field( 'na_relecture_rouvrir_' . (int) $id );
				echo '<p class="na-rel-champ"><label for="na-rel-motif">' . esc_html__( 'Motif de la réouverture', 'notice-archeomed' ) . '</label>'
					. '<textarea id="na-rel-motif" name="motif" rows="2"></textarea></p>'
					. '<p><button type="submit" class="na-rel-bouton">' . esc_html__( 'Rouvrir la notice', 'notice-archeomed' ) . '</button></p></form>';
			} else {
				echo esc_html__( 'La direction l’a validée : elle ne se corrige plus, mais une remarque peut encore lui être laissée.', 'notice-archeomed' ) . '</p>';
			}
			echo '</div>';
		}
		$rappels = $plugin->appels_a_verifier( $id );
		$masquee = false;
		foreach ( $notice['figures'] as $figure ) {
			$masquee = $masquee || null !== $figure['masquee'];
		}
		if ( $masquee ) {
			echo '<div class="na-rel-message na-rel-message--info"><p>' . esc_html__( 'Une figure a été retirée : les figures qui restent se numérotent à la suite. Vérifiez les appels « (fig. N) » du texte.', 'notice-archeomed' ) . '</p>';
			if ( ! empty( $rappels ) ) {
				echo '<ul>';
				foreach ( $rappels as $rappel ) {
					echo '<li>' . esc_html( $rappel ) . '</li>';
				}
				echo '</ul>';
			}
			echo '</div>';
		}
		if ( $corrige ) {
			$this->afficher_le_formulaire( $id, $notice, $droit, $rubrique, $etat );
		} else {
			$this->afficher_la_lecture( $id, $notice, $droit, $rubrique );
		}
		$this->afficher_l_histoire( $id, $droit );
	}

	/** Un champ d'une ligne du formulaire, avec son étiquette. */
	private static function champ( $nom, $libelle, $valeur, $id = '' ) {
		$id = '' !== $id ? $id : 'na-rel-' . sanitize_html_class( str_replace( array( '[', ']' ), '-', $nom ) );
		echo '<p class="na-rel-champ"><label for="' . esc_attr( $id ) . '">' . esc_html( $libelle ) . '</label>'
			. '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $nom ) . '" value="' . esc_attr( (string) $valeur ) . '"></p>';
	}

	/**
	 * Le titre de la notice au-dessus de son texte, tel qu'on le lit :
	 * « CAEN (Calvados), Château ». Il ne se saisit pas ici : il suit les
	 * champs du lieu, et le script le refait quand on les change.
	 */
	private static function titre_au_dessus_du_texte( $lieux, $departement, $lieu_dit ) {
		$noms = array();
		foreach ( (array) $lieux as $lieu ) {
			if ( isset( $lieu['nom'] ) && '' !== trim( (string) $lieu['nom'] ) ) {
				$noms[] = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( trim( (string) $lieu['nom'] ), 'UTF-8' ) : strtoupper( trim( (string) $lieu['nom'] ) );
			}
		}
		$departement = trim( (string) $departement, " \t\n\r\0\x0B()" );
		$lieu_dit    = trim( (string) $lieu_dit );
		$titre       = esc_html( implode( ', ', $noms ) . ( '' !== $departement ? ' (' . $departement . ')' : '' ) );
		if ( '' !== $lieu_dit ) {
			$titre .= esc_html( Notice_Archeomed_Normes::avant_le_lieu_dit() )
				. ( Notice_Archeomed_Normes::lieu_dit_en_italique() ? '<em>' . esc_html( $lieu_dit ) . '</em>' : esc_html( $lieu_dit ) );
		}
		return '<p class="na-rel-titre-texte" id="na-rel-titre-texte" data-avant="' . esc_attr( Notice_Archeomed_Normes::avant_le_lieu_dit() ) . '"'
			. ' data-italique="' . ( Notice_Archeomed_Normes::lieu_dit_en_italique() ? '1' : '0' ) . '">' . $titre . '</p>';
	}

	/** Une liste déroulante de rubriques, avec son choix vide. */
	private static function choix_de_rubrique( $nom, $libelle, $rubriques, $choisie, $vide ) {
		echo '<p class="na-rel-champ"><label for="na-rel-' . esc_attr( $nom ) . '">' . esc_html( $libelle ) . '</label>'
			. '<select id="na-rel-' . esc_attr( $nom ) . '" name="' . esc_attr( $nom ) . '">';
		if ( null !== $vide ) {
			echo '<option value="">' . esc_html( $vide ) . '</option>';
		}
		foreach ( $rubriques as $rubrique ) {
			echo '<option value="' . esc_attr( $rubrique ) . '"' . selected( $rubrique, $choisie, false ) . '>' . esc_html( $rubrique ) . '</option>';
		}
		echo '</select></p>';
	}

	/** Le texte d'une notice, réduit aux balises que l'éditeur connaît. */
	private static function texte_propre( $html ) {
		return wp_kses( (string) $html, array(
			'p' => array(), 'br' => array(), 'em' => array(), 'strong' => array(), 'i' => array(), 'b' => array(),
			'sup' => array(), 'sub' => array(), 'h3' => array(), 'h4' => array(), 'span' => array( 'class' => true ),
		) );
	}

	/**
	 * Le formulaire de relecture : celui du dépôt, sans ses explications.
	 * Les courriels des responsables n'y sont pas pour un coordinateur ; la
	 * direction les lit, sans pouvoir les changer ici.
	 */
	private function afficher_le_formulaire( $id, $notice, $droit, $rubrique, $etat ) {
		$plugin = self::plugin();
		$d      = $notice['d'];
		$tout   = 'tout' === $droit['sorte'];
		$valeur = function ( $cle ) use ( $d ) {
			return isset( $d[ $cle ] ) && is_scalar( $d[ $cle ] ) ? (string) $d[ $cle ] : '';
		};
		$principale = $valeur( 'rubrique_principale' );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="na-rel-form" id="na-rel-form">';
		echo '<input type="hidden" name="action" value="na_relecture"><input type="hidden" name="notice" value="' . (int) $id . '">'
			. '<input type="hidden" name="version" value="' . (int) $notice['version'] . '">'
			. '<input type="hidden" name="rubrique" value="' . esc_attr( (string) ( array_search( $rubrique, $plugin->rubriques(), true ) + 1 ) ) . '">';
		wp_nonce_field( 'na_relecture_' . (int) $id );

		// ── Rubrique et renvois ──
		echo '<fieldset><legend>' . esc_html__( 'Rubrique', 'notice-archeomed' ) . '</legend>';
		if ( $tout ) {
			self::choix_de_rubrique( 'rubrique_principale', 'Rubrique principale', $plugin->rubriques(), $principale, null );
		} else {
			echo '<p class="na-rel-champ"><span class="na-rel-libelle">' . esc_html__( 'Rubrique principale', 'notice-archeomed' ) . '</span> ' . esc_html( $principale ) . '</p>';
		}
		// La matière ne divise que la rubrique V. La direction, qui peut
		// changer la rubrique, la voit paraître quand elle choisit V.
		$en_v = Notice_Archeomed_Pactols::RUBRIQUE_ARTISANAT === Notice_Archeomed_Pactols::rubrique_actuelle( $principale );
		if ( $en_v || $tout ) {
			echo '<p class="na-rel-champ" id="na-rel-matiere-bloc" data-rubrique="' . esc_attr( Notice_Archeomed_Pactols::RUBRIQUE_ARTISANAT ) . '"' . ( $en_v ? '' : ' hidden' ) . '>'
				. '<label for="na-rel-matiere">' . esc_html__( 'Sous-rubrique', 'notice-archeomed' ) . '</label><select id="na-rel-matiere" name="rubrique_matiere"><option value="">—</option>';
			foreach ( Notice_Archeomed_Pactols::MATIERES as $lettre => $matiere ) {
				echo '<option value="' . esc_attr( $lettre ) . '"' . selected( $lettre, Notice_Archeomed_Pactols::matiere_de( $d ), false ) . '>' . esc_html( $lettre . ' — ' . $matiere ) . '</option>';
			}
			echo '</select></p>';
		}
		$autres = array_values( array_diff( $plugin->rubriques(), array( $principale ) ) );
		self::choix_de_rubrique( 'renvoi_1', 'Renvoi', $autres, $valeur( 'renvoi_1' ), 'Aucun' );
		self::choix_de_rubrique( 'renvoi_2', 'Second renvoi', $autres, $valeur( 'renvoi_2' ), 'Aucun' );
		echo '</fieldset>';

		// ── Lieu ──
		echo '<fieldset><legend>' . esc_html__( 'Lieu et opération', 'notice-archeomed' ) . '</legend>';
		$lieux = $notice['lieux'];
		for ( $i = 0; $i < Notice_Archeomed_Pactols::MAX_LIEUX; $i++ ) {
			if ( $i > max( 0, count( $lieux ) ) ) {
				break;
			}
			$nom = isset( $lieux[ $i ] ) ? $lieux[ $i ]['nom'] : '';
			$ark = isset( $lieux[ $i ] ) ? $lieux[ $i ]['ark'] : '';
			// Le nom se tape ou se choisit dans le thésaurus des lieux : choisi,
			// il prend l'identifiant du terme ; inchangé, il garde le sien.
			echo '<div class="na-rel-champ na-rel-lieu"><label for="na-rel-commune-' . (int) $i . '">' . esc_html( 0 === $i ? 'Commune ou lieu' : 'Autre lieu du titre' ) . '</label>'
				. '<input type="text" id="na-rel-commune-' . (int) $i . '" name="commune[]" value="' . esc_attr( $nom ) . '" autocomplete="off"'
				. ' data-nom="' . esc_attr( $nom ) . '" data-ark="' . esc_attr( $ark ) . '">'
				. '<input type="hidden" name="commune_ark[]" value="' . esc_attr( $ark ) . '">'
				. '<ul class="na-rel-propositions" hidden></ul><span class="na-rel-dit" aria-live="polite"></span></div>';
		}
		self::champ( 'departement', 'Précision géographique', $valeur( 'departement' ) );
		self::champ( 'lieu_dit', 'Lieu-dit', $valeur( 'lieu_dit' ) );
		self::champ( 'annee', 'Année de l’opération', $valeur( 'annee' ) );
		self::champ( 'num_autorisation', 'Numéro d’arrêté', $valeur( 'num_autorisation' ) );
		self::champ( 'id_patriarche', 'Code Patriarche', $valeur( 'id_patriarche' ) );
		self::champ( 'rapport_lien', 'Lien du rapport', $valeur( 'rapport_lien' ) );
		$organismes = $notice['organismes'];
		for ( $i = 0; $i < Notice_Archeomed_Pactols::MAX_ORGANISMES; $i++ ) {
			if ( $i > count( $organismes ) ) {
				break;
			}
			self::champ( 'organisme[]', 0 === $i ? 'Organisme' : 'Autre organisme', isset( $organismes[ $i ] ) ? $organismes[ $i ] : '', 'na-rel-organisme-' . $i );
		}
		echo '<div class="na-rel-champ"><span class="na-rel-libelle" id="na-rel-natures">' . esc_html__( 'Nature de l’opération', 'notice-archeomed' ) . '</span><div class="na-rel-cases" role="group" aria-labelledby="na-rel-natures">';
		$natures = $plugin->natures_proposees();
		foreach ( $notice['natures'] as $portee ) {
			if ( ! isset( $natures[ $portee ] ) ) {
				$natures[ $portee ] = $portee;
			}
		}
		foreach ( $natures as $nature => $libelle ) {
			echo '<label><input type="checkbox" name="nature[]" value="' . esc_attr( $nature ) . '"' . checked( in_array( $nature, $notice['natures'], true ), true, false ) . '> ' . esc_html( $libelle ) . '</label>';
		}
		echo '</div></div></fieldset>';

		// ── Thésaurus ──
		echo '<fieldset><legend>' . esc_html__( 'Mots-clés', 'notice-archeomed' ) . '</legend>';
		foreach ( array( 'periods' => array( 'period', 'Périodes' ), 'subjects' => array( 'subject', 'Sujets' ), 'places' => array( 'place', 'Autres lieux' ) ) as $liste => $sorte ) {
			$items  = ( isset( $d[ 'pactols_' . $liste . '_items' ] ) && is_array( $d[ 'pactols_' . $liste . '_items' ] ) ) ? array_values( array_filter( $d[ 'pactols_' . $liste . '_items' ], 'is_array' ) ) : array();
			$propres = array();
			foreach ( $items as $item ) {
				$propres[] = array(
					'label'     => isset( $item['label'] ) && is_scalar( $item['label'] ) ? (string) $item['label'] : '',
					'ark'       => isset( $item['ark'] ) && is_scalar( $item['ark'] ) ? (string) $item['ark'] : '',
					'idConcept' => isset( $item['idConcept'] ) && is_scalar( $item['idConcept'] ) ? (string) $item['idConcept'] : '',
					'fullpath'  => '',
				);
			}
			echo '<div class="na-rel-termes" data-type="' . esc_attr( $sorte[0] ) . '">'
				. '<span class="na-rel-libelle" id="na-rel-termes-' . esc_attr( $liste ) . '">' . esc_html( $sorte[1] ) . '</span>'
				. '<input type="hidden" name="pactols_' . esc_attr( $liste ) . '" value="' . esc_attr( (string) wp_json_encode( $propres ) ) . '">'
				. '<ul class="na-rel-jetons" aria-labelledby="na-rel-termes-' . esc_attr( $liste ) . '"></ul>'
				. '<label class="na-rel-sr" for="na-rel-cherche-' . esc_attr( $liste ) . '">' . esc_html( sprintf( 'Chercher dans le thésaurus : %s', $sorte[1] ) ) . '</label>'
				. '<input type="text" class="na-rel-cherche" id="na-rel-cherche-' . esc_attr( $liste ) . '" autocomplete="off" placeholder="' . esc_attr__( 'Ajouter depuis le thésaurus…', 'notice-archeomed' ) . '">'
				. '<ul class="na-rel-propositions" hidden></ul><span class="na-rel-dit" aria-live="polite"></span></div>';
		}
		echo '</fieldset>';

		// ── Texte ──
		$texte = $valeur( 'texte_notice' );
		echo '<fieldset><legend id="na-rel-texte-libelle">' . esc_html__( 'Texte', 'notice-archeomed' ) . '</legend>'
			. self::titre_au_dessus_du_texte( $notice['lieux'], $valeur( 'departement' ), $valeur( 'lieu_dit' ) )
			. '<input type="hidden" name="texte_notice" id="na-rel-texte" value="' . esc_attr( 'b64:' . base64_encode( $texte ) ) . '">'
			. '<div id="na-rel-editeur">' . self::texte_propre( $texte ) . '</div></fieldset>';

		// ── Responsables ──
		echo '<fieldset><legend>' . esc_html__( 'Responsables', 'notice-archeomed' ) . '</legend>';
		foreach ( array( 'resp' => 'Responsable', 'coresp' => 'Co-responsable', 'coauteur' => 'Co-auteur' ) as $qui => $libelle ) {
			echo '<div class="na-rel-personne"><span class="na-rel-libelle">' . esc_html( $libelle ) . '</span>';
			self::champ( $qui . '_prenom', 'Prénom', $valeur( $qui . '_prenom' ) );
			self::champ( $qui . '_nom', 'Nom', $valeur( $qui . '_nom' ) );
			self::champ( $qui . '_inst', 'Institution', $valeur( $qui . '_inst' ) );
			// Le courriel : jamais dans la page d'un coordinateur, et pour
			// la direction un texte, non un champ — il ne se change pas ici.
			if ( $tout && '' !== $valeur( $qui . '_email' ) ) {
				echo '<p class="na-rel-champ"><span class="na-rel-libelle">' . esc_html__( 'Courriel', 'notice-archeomed' ) . '</span> ' . esc_html( $valeur( $qui . '_email' ) ) . '</p>';
			}
			echo '</div>';
		}
		echo '</fieldset>';

		// ── Illustrations ──
		if ( ! empty( $notice['figures'] ) ) {
			echo '<fieldset><legend>' . esc_html__( 'Illustrations', 'notice-archeomed' ) . '</legend>'
				. '<p>' . esc_html__( 'Vous pouvez retirer une illustration si elle ne vous semble pas utile.', 'notice-archeomed' ) . '</p>';
			foreach ( $notice['figures'] as $figure ) {
				$this->afficher_la_figure( $id, $figure, $tout );
			}
			echo '</fieldset>';
		}

		// ── État, remarque, boutons ──
		echo '<fieldset><legend>' . esc_html__( 'Relecture', 'notice-archeomed' ) . '</legend>';
		if ( $tout ) {
			echo '<p class="na-rel-champ"><label for="na-rel-etat">' . esc_html__( 'État', 'notice-archeomed' ) . '</label><select id="na-rel-etat" name="etat">';
			foreach ( self::ETATS as $cle => $libelle ) {
				// « Validée » ne se choisit pas : elle a son bouton.
				if ( 'validee' === $cle ) {
					continue;
				}
				echo '<option value="' . esc_attr( $cle ) . '"' . selected( $cle, $etat, false ) . '>' . esc_html( $libelle ) . '</option>';
			}
			echo '</select></p>';
		} else {
			echo '<p><label><input type="checkbox" name="relue" value="1"' . checked( 'a_relire' !== $etat, true, false ) . ( 'a_relire' !== $etat ? ' disabled' : '' ) . '> ' . esc_html__( 'Notice relue', 'notice-archeomed' ) . '</label></p>'
				. '<p><label><input type="checkbox" name="fond" value="1"' . checked( 'a_soumettre' === $etat, true, false ) . ( 'a_soumettre' === $etat ? ' disabled' : '' ) . '> ' . esc_html__( 'Modification de fond (accord de l’auteur nécessaire)', 'notice-archeomed' ) . '</label></p>';
		}
		echo '<p class="na-rel-champ"><label for="na-rel-remarque">' . esc_html( $tout ? __( 'Remarque', 'notice-archeomed' ) : __( 'Remarque à la direction', 'notice-archeomed' ) ) . '</label>'
			. '<textarea id="na-rel-remarque" name="remarque" rows="3"></textarea></p></fieldset>';
		// Valider enregistre ce qui est corrigé, fige la notice et passe à la
		// suivante : c'est la version définitive.
		echo '<p class="na-rel-boutons"><button type="submit" name="suite" value="rester" class="na-rel-bouton na-rel-bouton--principal">' . esc_html__( 'Enregistrer', 'notice-archeomed' ) . '</button> '
			. ( $tout ? '<button type="submit" name="suite" value="valider" class="na-rel-bouton na-rel-bouton--valider">' . esc_html__( 'Valider', 'notice-archeomed' ) . '</button> ' : '' )
			. '<button type="submit" name="suite" value="suivante" class="na-rel-bouton">' . esc_html__( 'Suivante', 'notice-archeomed' ) . '</button></p>';
		echo '</form>';
		$this->poser_le_script( $id );
	}

	/** Une illustration du formulaire : son aperçu, ses textes, et de quoi la retirer. */
	private function afficher_la_figure( $id, $figure, $tout ) {
		$k = (int) $figure['k'];
		echo '<div class="na-rel-figure' . ( null !== $figure['masquee'] ? ' na-rel-figure--masquee' : '' ) . '">';
		if ( ! empty( $figure['apercu'] ) ) {
			// Un clic l'agrandit : on décide d'un retrait sur l'image entière.
			$adresse = self::url_de_l_apercu( $id, $k );
			echo '<button type="button" class="na-rel-agrandir" data-src="' . esc_url( $adresse ) . '" data-titre="' . esc_attr( Notice_Archeomed_Normes::numero_de_figure( $k ) . ' — ' . (string) $figure['titre'] ) . '"'
				. ' aria-label="' . esc_attr( sprintf( 'Agrandir : %s', Notice_Archeomed_Normes::numero_de_figure( $k ) ) ) . '">'
				. '<img src="' . esc_url( $adresse ) . '" alt="' . esc_attr( Notice_Archeomed_Controles::une_ligne( strtok( (string) $figure['alt'], "\n" ) ) ) . '" loading="lazy">'
				. '<span class="na-rel-loupe" aria-hidden="true">Agrandir</span></button>';
		} elseif ( null !== $figure['a_part'] ) {
			echo '<p class="na-rel-sans-apercu">' . esc_html__( 'Figure envoyée à part par l’auteur : pas d’aperçu.', 'notice-archeomed' ) . '</p>';
		} elseif ( ! empty( $figure['apercu_attendu'] ) ) {
			// La tâche des aperçus ne l'a pas encore faite : quelques secondes
			// après un dépôt ou une correction d'auteur.
			echo '<p class="na-rel-sans-apercu">' . esc_html__( 'Aperçu en préparation : il paraîtra en rechargeant la page dans un instant.', 'notice-archeomed' ) . '</p>';
		} else {
			echo '<p class="na-rel-sans-apercu">' . esc_html__( 'Pas d’aperçu pour cette figure.', 'notice-archeomed' ) . '</p>';
		}
		echo '<div class="na-rel-figure-textes"><p><strong>' . esc_html( Notice_Archeomed_Normes::numero_de_figure( $k ) ) . '</strong></p>';
		if ( null !== $figure['masquee'] ) {
			$motif = isset( $figure['masquee']['motif'], self::MOTIFS[ $figure['masquee']['motif'] ] ) ? self::MOTIFS[ $figure['masquee']['motif'] ] : '';
			echo '<p>' . esc_html( sprintf( 'Retirée par %1$s — %2$s%3$s.', isset( $figure['masquee']['par'] ) ? (string) $figure['masquee']['par'] : '', $motif,
				! empty( $figure['masquee']['precision'] ) ? ' : ' . $figure['masquee']['precision'] : '' ) ) . '</p>'
				. '<p>' . esc_html( (string) $figure['titre'] ) . '</p>';
			if ( $tout ) {
				echo '<p><label><input type="checkbox" name="garde_action[' . $k . ']" value="retablir"> ' . esc_html__( 'Rétablir cette illustration', 'notice-archeomed' ) . '</label></p>';
			}
			echo '</div></div>';
			return;
		}
		self::champ( 'garde_titre[' . $k . ']', 'Titre', $figure['titre'] );
		echo '<p class="na-rel-champ"><label for="na-rel-legende-' . $k . '">' . esc_html__( 'Légende', 'notice-archeomed' ) . '</label><textarea id="na-rel-legende-' . $k . '" name="garde_legende[' . $k . ']" rows="2">' . esc_textarea( (string) $figure['legende'] ) . '</textarea></p>';
		self::champ( 'garde_credits[' . $k . ']', 'Crédits', $figure['credits'] );
		echo '<p class="na-rel-champ"><label for="na-rel-alt-' . $k . '">' . esc_html__( 'Pour un public empêché de voir', 'notice-archeomed' ) . '</label><textarea id="na-rel-alt-' . $k . '" name="garde_alt[' . $k . ']" rows="3">' . esc_textarea( (string) $figure['alt'] ) . '</textarea></p>';
		echo '<p class="na-rel-champ"><label for="na-rel-type-' . $k . '">' . esc_html__( 'Nature de la figure', 'notice-archeomed' ) . '</label><select id="na-rel-type-' . $k . '" name="garde_type[' . $k . ']"><option value="">—</option>';
		foreach ( Notice_Archeomed_Controles::TYPES_DE_FIGURE as $cle => $libelle ) {
			echo '<option value="' . esc_attr( $cle ) . '"' . selected( $cle, (string) $figure['type'], false ) . '>' . esc_html( $libelle ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label><input type="checkbox" class="na-rel-retirer" name="garde_action[' . $k . ']" value="retirer" aria-controls="na-rel-motif-bloc-' . $k . '"> ' . esc_html__( 'Retirer cette illustration', 'notice-archeomed' ) . '</label></p>'
			. '<div id="na-rel-motif-bloc-' . $k . '" class="na-rel-motif" hidden><p class="na-rel-champ"><label for="na-rel-motif-' . $k . '">' . esc_html__( 'Motif', 'notice-archeomed' ) . '</label>'
			. '<select id="na-rel-motif-' . $k . '" name="garde_motif[' . $k . ']"><option value="">' . esc_html__( 'Choisir…', 'notice-archeomed' ) . '</option>';
		foreach ( self::MOTIFS as $cle => $libelle ) {
			echo '<option value="' . esc_attr( $cle ) . '">' . esc_html( $libelle ) . '</option>';
		}
		echo '</select></p>';
		self::champ( 'garde_precision[' . $k . ']', 'Précision', '' );
		echo '</div></div></div>';
	}

	/**
	 * La notice en lecture seule : pour le coordinateur d'une rubrique de
	 * renvoi, pour une notice qu'un autre tient ouverte, qui n'est plus au
	 * coordinateur, ou que la direction a validée. On peut y laisser une
	 * remarque, et le coordinateur d'un renvoi y retire le renvoi vers sa
	 * rubrique — tant que la notice n'est pas validée.
	 */
	private function afficher_la_lecture( $id, $notice, $droit, $rubrique ) {
		$plugin = self::plugin();
		echo '<iframe class="na-rel-lecture" title="' . esc_attr__( 'La notice, en lecture seule', 'notice-archeomed' ) . '" src="'
			. esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'na_relecture_lire', 'notice' => (int) $id ), admin_url( 'admin-post.php' ) ), 'na_relecture_lire_' . (int) $id ) ) . '"></iframe>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="na-rel-form">'
			. '<input type="hidden" name="action" value="na_relecture"><input type="hidden" name="notice" value="' . (int) $id . '">'
			. '<input type="hidden" name="version" value="' . (int) $notice['version'] . '">'
			. '<input type="hidden" name="rubrique" value="' . esc_attr( (string) ( array_search( $rubrique, $plugin->rubriques(), true ) + 1 ) ) . '">';
		wp_nonce_field( 'na_relecture_' . (int) $id );
		if ( 'renvoi' === $droit['sorte'] && 'validee' !== self::etat_de( $id ) ) {
			foreach ( array( 'renvoi_1', 'renvoi_2' ) as $champ ) {
				if ( isset( $notice['d'][ $champ ] ) && $notice['d'][ $champ ] === $droit['rubrique'] ) {
					echo '<p><label><input type="checkbox" name="retirer_renvoi[' . esc_attr( $champ ) . ']" value="1"> '
						. esc_html( sprintf( 'Retirer le renvoi vers « %s »', $droit['rubrique'] ) ) . '</label></p>';
				}
			}
		}
		echo '<p class="na-rel-champ"><label for="na-rel-remarque">' . esc_html( 'tout' === $droit['sorte'] ? __( 'Remarque', 'notice-archeomed' ) : __( 'Remarque à la direction', 'notice-archeomed' ) ) . '</label>'
			. '<textarea id="na-rel-remarque" name="remarque" rows="3"></textarea></p>'
			. '<p class="na-rel-boutons"><button type="submit" name="suite" value="rester" class="na-rel-bouton na-rel-bouton--principal">' . esc_html__( 'Enregistrer', 'notice-archeomed' ) . '</button> '
			. '<button type="submit" name="suite" value="suivante" class="na-rel-bouton">' . esc_html__( 'Suivante', 'notice-archeomed' ) . '</button></p></form>';
	}

	/** Le journal de la notice et ses versions, avec le Word de chacune. */
	private function afficher_l_histoire( $id, $droit ) {
		$journal  = self::journal_de( $id );
		$versions = self::versions_gardees( $id );
		if ( empty( $journal ) && count( $versions ) < 2 ) {
			return;
		}
		echo '<details class="na-rel-histoire"' . ( 'tout' === $droit['sorte'] ? ' open' : '' ) . '><summary>' . esc_html__( 'Journal et versions', 'notice-archeomed' ) . '</summary>';
		if ( ! empty( $journal ) ) {
			echo '<ul class="na-rel-journal">';
			foreach ( array_reverse( $journal ) as $ligne ) {
				echo '<li><strong>' . esc_html( Notice_Archeomed_File::date_lisible( $ligne['le'], true ) ) . '</strong> — ' . esc_html( '' !== $ligne['par'] ? $ligne['par'] : '—' );
				foreach ( (array) $ligne['quoi'] as $quoi ) {
					echo '<br>' . esc_html( $quoi );
				}
				if ( '' !== $ligne['remarque'] ) {
					echo '<br><em>' . esc_html__( 'Remarque :', 'notice-archeomed' ) . '</em> ' . nl2br( esc_html( $ligne['remarque'] ) );
				}
				echo '</li>';
			}
			echo '</ul>';
		}
		$deposes  = get_post_meta( $id, '_na_relecture_deposes', true );
		$deposes  = is_array( $deposes ) ? $deposes : array();
		$attendus = array_map( 'intval', (array) get_post_meta( $id, '_na_relecture_a_deposer', false ) );
		$premiere = true;
		echo '<ul class="na-rel-versions">';
		foreach ( $versions as $numero => $version ) {
			if ( $premiere ) {
				$premiere = false;
				continue;
			}
			$lien = wp_nonce_url( add_query_arg( array( 'action' => 'na_relecture_word', 'notice' => (int) $id, 'version' => (int) $numero ), admin_url( 'admin-post.php' ) ), 'na_relecture_word_' . (int) $id );
			echo '<li>' . esc_html( sprintf( 'Version %1$d — %2$s, %3$s', $numero, '' !== $version['par'] ? $version['par'] : '—',
				Notice_Archeomed_File::date_lisible( $version['le'], true ) ) );
			if ( null !== self::version_gardee( $id, $numero - 1 ) ) {
				echo ' — <a href="' . esc_url( $lien ) . '">' . esc_html__( 'Word avec suivi des modifications', 'notice-archeomed' ) . '</a>';
			}
			if ( isset( $deposes[ $numero ] ) ) {
				echo ' <span class="na-rel-note">' . esc_html__( '(déposé sur sDrive)', 'notice-archeomed' ) . '</span>';
			} elseif ( in_array( (int) $numero, $attendus, true ) ) {
				$raison = (string) get_post_meta( $id, '_na_relecture_raison', true );
				echo ' <span class="na-rel-note">' . esc_html( '' !== $raison ? sprintf( '(pas encore sur sDrive : %s)', $raison ) : '(en attente de sDrive)' ) . '</span>';
			}
			echo '</li>';
		}
		echo '</ul></details>';
	}

	/**
	 * Les styles de l'écran, une fois par page : dans l'en-tête quand la
	 * page porte le shortcode, sinon avec l'écran lui-même.
	 *
	 * Tout est cloisonné sous « .na-rel » : rien ici ne touche au thème, et
	 * l'écran ne compte sur aucune classe de l'administration. Sous 800
	 * pixels, le sommaire passe au-dessus de la notice et chaque champ prend
	 * toute la largeur.
	 */
	public static function poser_les_styles( $dans_l_ecran = false ) {
		static $poses = false;
		if ( $poses || ( true !== $dans_l_ecran && ! self::porte_l_ecran() ) ) {
			return;
		}
		$poses = true;
		echo '<style id="na-rel-styles">'
			. '.na-rel,.na-rel *,.na-rel *::before,.na-rel *::after{box-sizing:border-box}'
			. '.na-rel{font-size:16px;line-height:1.5;color:#1d2327;max-width:none}'
			. '.na-rel-cadre{display:flex;gap:24px;align-items:flex-start;margin-top:12px}'
			. '.na-rel-sommaire{flex:0 0 300px;max-width:300px;position:sticky;top:16px;max-height:calc(100vh - 32px);overflow:auto;background:#fff;border:1px solid #c3c4c7;padding:10px 14px}'
			. '.na-rel .na-rel-sommaire h2{font-size:1em;margin:.4em 0}.na-rel .na-rel-sommaire h3{font-size:.92em;margin:1em 0 .3em}'
			. '.na-rel .na-rel-sommaire ol{margin:0 0 0 1.4em;padding:0}.na-rel .na-rel-sommaire li{margin:0 0 .5em}'
			. '.na-rel-sommaire .na-rel-ouverte a{font-weight:700}'
			. '.na-rel-rubrique{border-top:1px solid #dcdcde;padding:.3em 0}'
			. '.na-rel .na-rel-rubrique>summary{display:list-item;cursor:pointer;font-weight:700;font-size:.95em;padding:.2em 0}'
			. '.na-rel-compte{display:block;font-weight:400;font-size:.85em;color:#50575e}'
			. '.na-rel-rubrique--faite .na-rel-compte{color:#007017}'
			. '.na-rel .na-rel-bouton--valider{border-color:#007017;color:#007017}'
			. '.na-rel .na-rel-bouton--valider:hover,.na-rel .na-rel-bouton--valider:focus{background:#007017;border-color:#007017;color:#fff}'
			. '.na-rel .na-rel-renvois{border-top:1px solid #c3c4c7;padding-top:.8em;margin-top:1.2em}'
			. '.na-rel-notice{flex:1 1 auto;min-width:0;max-width:60em}'
			. '.na-rel .na-rel-titre{font-size:1.4em;line-height:1.3;margin:0 0 .3em}'
			. '.na-rel-onglets{display:flex;flex-wrap:wrap;gap:4px;border-bottom:1px solid #c3c4c7;margin:0 0 12px}'
			. '.na-rel-onglet{display:inline-block;padding:.4em .9em;border:1px solid #c3c4c7;border-bottom:0;background:#f6f7f7;color:#1d2327;text-decoration:none}'
			. '.na-rel-onglet--actif{background:#fff;font-weight:700}'
			. '.na-rel-message{border-left:4px solid #72aee6;background:#fff;padding:.5em 1em;margin:0 0 16px;box-shadow:0 1px 1px rgba(0,0,0,.08)}'
			. '.na-rel-message p{margin:.4em 0}.na-rel-message--success{border-left-color:#007017}'
			. '.na-rel-message--warning{border-left-color:#bd8600}.na-rel-message--error{border-left-color:#b32d2e}'
			. '.na-rel-etat{display:inline-block;font-size:.85em;padding:0 .5em;border:1px solid #8c8f94;border-radius:2px;background:#f6f7f7;color:#1d2327;white-space:nowrap}'
			. '.na-rel-etat--relue,.na-rel-etat--accord{border-color:#007017;color:#007017}'
			. '.na-rel-etat--a_soumettre,.na-rel-etat--soumise{border-color:#825900;color:#825900}'
			. '.na-rel-etat--validee{background:#007017;border-color:#007017;color:#fff}'
			. '.na-rel-verrou{font-size:.85em;color:#b32d2e}.na-rel-note{font-size:.9em;color:#50575e}'
			. '.na-rel-form fieldset{background:#fff;border:1px solid #c3c4c7;padding:8px 16px 12px;margin:0 0 16px;min-width:0}'
			. '.na-rel-form legend{font-weight:700;padding:0 .4em;width:auto;font-size:1em}'
			. '.na-rel-champ{margin:.6em 0}.na-rel-champ label,.na-rel-libelle{display:block;font-weight:700;margin-bottom:2px}'
			. '.na-rel-form input[type=text],.na-rel-form textarea,.na-rel-form select{display:block;width:100%;max-width:40em;font:inherit;padding:.35em .5em;border:1px solid #8c8f94;border-radius:3px;background:#fff;color:#1d2327}'
			. '.na-rel-form input[type=checkbox]{width:auto;margin-right:.3em}'
			. '.na-rel-cases label{display:inline-block;font-weight:400;margin:0 1.2em .3em 0}'
			. '.na-rel-personne{border-top:1px solid #dcdcde;padding-top:.6em;margin-top:.6em}.na-rel-personne:first-of-type{border-top:0}'
			. '.na-rel-termes{margin:.8em 0}.na-rel .na-rel-jetons{margin:.2em 0;padding:0;list-style:none}'
			. '.na-rel .na-rel-jetons li{display:inline-block;margin:0 .4em .4em 0;padding:.1em .2em .1em .6em;background:#f0f0f1;border:1px solid #c3c4c7;border-radius:2px}'
			. '.na-rel .na-rel-jetons button{border:0;background:none;cursor:pointer;color:#b32d2e;padding:0 .5em;font:inherit;min-height:1.8em}'
			. '.na-rel .na-rel-propositions{margin:0;padding:0;list-style:none;border:1px solid #8c8f94;background:#fff;max-width:40em;max-height:16em;overflow:auto}'
			. '.na-rel .na-rel-propositions li{margin:0}.na-rel .na-rel-propositions button{display:block;width:100%;text-align:left;border:0;background:none;color:#1d2327;padding:.5em .6em;cursor:pointer;font:inherit}'
			. '.na-rel .na-rel-propositions button:hover,.na-rel .na-rel-propositions button:focus{background:#2271b1;color:#fff}'
			. '.na-rel-dit{display:block;font-size:.9em;color:#50575e}'
			. '.na-rel-sr{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}'
			. '#na-rel-editeur{background:#fff;min-height:16em;font-size:16px}.na-rel-form .ql-editor{min-height:16em}.na-rel .na-pc{font-variant:small-caps}'
			. '.na-rel-figure{display:flex;gap:16px;border-top:1px solid #dcdcde;padding-top:12px;margin-top:12px}'
			. '.na-rel .na-rel-agrandir{flex:0 0 220px;width:220px;max-width:100%;align-self:flex-start;position:relative;display:block;padding:0;margin:0;border:1px solid #c3c4c7;background:#fff;cursor:zoom-in}'
			. '.na-rel .na-rel-agrandir img{display:block;width:100%;height:auto;border:0}'
			. '.na-rel .na-rel-loupe{position:absolute;right:4px;bottom:4px;font-size:.8em;padding:0 .4em;background:rgba(29,35,39,.75);color:#fff;border-radius:2px}'
			. '.na-rel .na-rel-agrandir:focus{outline:2px solid #2271b1;outline-offset:2px}'
			. '.na-rel-boite{width:auto;max-width:96vw;max-height:96vh;padding:0;border:0;background:#1d2327;color:#fff}'
			. '.na-rel-boite::backdrop{background:rgba(0,0,0,.8)}'
			. '.na-rel-boite img{display:block;max-width:96vw;max-height:calc(96vh - 3em);width:auto;height:auto;margin:0 auto;background:#fff}'
			. '.na-rel-boite-barre{display:flex;justify-content:space-between;align-items:center;gap:1em;padding:.4em .8em;font-size:15px}'
			. '.na-rel-boite button{font:inherit;padding:.3em .9em;border:1px solid #fff;border-radius:3px;background:none;color:#fff;cursor:pointer}'
			. '.na-rel .na-rel-titre-texte{font-size:1.1em;font-weight:700;margin:.2em 0 .8em}'
			. '.na-rel-sans-apercu{flex:0 0 220px;width:220px;color:#646970;font-style:italic}'
			. '.na-rel-figure-textes{flex:1 1 auto;min-width:0}.na-rel-figure--masquee{opacity:.7}'
			. '.na-rel-boutons{display:flex;flex-wrap:wrap;gap:8px}'
			. '.na-rel .na-rel-bouton{display:inline-block;font:inherit;line-height:1.4;padding:.5em 1.1em;border:1px solid #2271b1;border-radius:3px;background:#f6f7f7;color:#2271b1;cursor:pointer;text-decoration:none}'
			. '.na-rel .na-rel-bouton--principal{background:#2271b1;color:#fff}'
			. '.na-rel .na-rel-bouton:hover,.na-rel .na-rel-bouton:focus{background:#135e96;border-color:#135e96;color:#fff}'
			. '.na-rel-lecture{display:block;width:100%;height:70vh;border:1px solid #c3c4c7;background:#fff}'
			. '.na-rel-histoire{background:#fff;border:1px solid #c3c4c7;padding:8px 16px;margin:16px 0}.na-rel-histoire summary{font-weight:700;cursor:pointer}'
			. '.na-rel .na-rel-journal,.na-rel .na-rel-versions{margin:.6em 0;padding:0 0 0 1.2em}.na-rel-journal li,.na-rel-versions li{margin:.6em 0}'
			. '@media (max-width:800px){.na-rel-cadre{display:block}'
			. '.na-rel-sommaire{position:static;max-width:none;max-height:40vh;margin-bottom:16px}'
			. '.na-rel-form fieldset{padding:6px 10px 10px}.na-rel-form input[type=text],.na-rel-form textarea,.na-rel-form select{max-width:none}'
			. '.na-rel-figure{display:block}.na-rel .na-rel-agrandir,.na-rel-sans-apercu{width:100%;max-width:320px;margin-bottom:8px}'
			. '.na-rel .na-rel-bouton{flex:1 1 auto;text-align:center;padding:.7em 1em}.na-rel-lecture{height:60vh}}'
			. '</style>';
	}

	/**
	 * Le script du formulaire : l'éditeur du dépôt, la recherche dans le
	 * thésaurus pour les lieux et les mots-clés, le motif d'un retrait, et
	 * le verrou tenu tant que la page est ouverte. Il part au pied de page,
	 * après l'éditeur que la page a chargé.
	 */
	private function poser_le_script( $id ) {
		$reglages = array(
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'notice'  => (int) $id,
			'jeton'   => wp_create_nonce( 'na_relecture_verrou_' . (int) $id ),
			'pactols' => 'https://pactols.frantiq.fr/',
			'maximum' => (int) Notice_Archeomed_Pactols::PACTOLS_FIELD_COUNT,
			'vue'     => null !== $this->regard_de_l_ecran,
		);
		?>
		<script>
		(function () {
			var R = <?php echo wp_json_encode( $reglages ); ?>;
			var form = document.getElementById('na-rel-form');
			if (!form) { return; }
			var envoi = false;

			// L'éditeur : les formats du dépôt, et eux seuls. Le champ caché
			// garde le texte tel qu'il est tant qu'on n'y a pas touché.
			var cache = document.getElementById('na-rel-texte');
			var quill = null, touche = false;
			if (typeof Quill !== 'undefined') {
				var petitesCapitales = false;
				try {
					var EnLigne = Quill.import('blots/inline');
					var PetitesCapitales = function () { return Reflect.construct(EnLigne, arguments, PetitesCapitales); };
					Object.setPrototypeOf(PetitesCapitales.prototype, EnLigne.prototype);
					Object.setPrototypeOf(PetitesCapitales, EnLigne);
					PetitesCapitales.blotName = 'petitescapitales';
					PetitesCapitales.tagName = 'span';
					PetitesCapitales.className = 'na-pc';
					PetitesCapitales.formats = function (noeud) {
						return noeud && noeud.classList && noeud.classList.contains('na-pc') ? true : undefined;
					};
					Quill.register(PetitesCapitales, true);
					petitesCapitales = true;
				} catch (e) { petitesCapitales = false; }
				quill = new Quill('#na-rel-editeur', {
					theme: 'snow',
					readOnly: !!R.vue,
					formats: ['bold', 'italic', 'script', 'header'].concat(petitesCapitales ? ['petitescapitales'] : []),
					modules: {
						toolbar: [
							[{ 'header': 3 }],
							['bold', 'italic'].concat(petitesCapitales ? ['petitescapitales'] : []),
							[{ 'script': 'super' }, { 'script': 'sub' }],
							['clean']
						],
						keyboard: { bindings: { tab: { key: 'Tab', handler: function () { return true; } } } }
					}
				});
				quill.root.setAttribute('role', 'textbox');
				quill.root.setAttribute('aria-multiline', 'true');
				quill.root.setAttribute('aria-labelledby', 'na-rel-texte-libelle');
				var barre = form.querySelector('.ql-toolbar');
				if (barre) {
					[['.ql-header[value="3"]', 'Intertitre', 'T1'],
						['.ql-bold', 'Gras', ''], ['.ql-italic', 'Italique', ''], ['.ql-petitescapitales', 'Petites capitales', 'Pc'],
						['.ql-script[value="super"]', 'Exposant', ''], ['.ql-script[value="sub"]', 'Indice', ''],
						['.ql-clean', 'Effacer la mise en forme', '']].forEach(function (p) {
						var b = barre.querySelector(p[0]);
						if (!b) { return; }
						b.setAttribute('aria-label', p[1]);
						b.title = p[1];
						if (p[2]) { b.textContent = p[2]; }
					});
				}
				if (R.vue && barre) {
					[].forEach.call(barre.querySelectorAll('button, select'), function (b) { b.disabled = true; });
				}
				quill.on('text-change', function (delta, avant, source) {
					if (source === 'user') { touche = true; }
				});
			}

			// La recherche dans Pactols, depuis le navigateur comme au dépôt :
			// trois lettres au moins, puis on choisit dans la liste.
			function chercher(type, q) {
				var theso = (type === 'place') ? 'th17' : 'TH_1';
				var propre = String(q).replace(/[’‘ʼ`]/g, '\'').replace(/œ/g, 'oe').replace(/Œ/g, 'Oe')
					.replace(/æ/g, 'ae').replace(/Æ/g, 'Ae').replace(/[\/%]/g, ' ').replace(/\s+/g, ' ').trim();
				var url = R.pactols + 'api/autocomplete/' + encodeURIComponent(propre) + '?lang=fr&theso=' + encodeURIComponent(theso);
				if (type === 'period') { url += '&group=g124'; }
				return fetch(url, { headers: { 'Accept': 'application/json' } }).then(function (r) {
					if (!r.ok) { throw new Error('HTTP ' + r.status); }
					return r.text();
				}).then(function (t) {
					var data = String(t).trim() ? JSON.parse(t) : [];
					var vus = {}, sortie = [];
					(Array.isArray(data) ? data : []).forEach(function (item) {
						if (!item || !item.label || !item.uri || vus[item.uri]) { return; }
						vus[item.uri] = true;
						sortie.push({ label: String(item.label), ark: String(item.uri), idConcept: item.identifier ? String(item.identifier) : '', fullpath: '' });
					});
					return sortie.slice(0, 20);
				});
			}
			// Un champ de recherche et sa liste de propositions ; « choisir »
			// reçoit le terme retenu.
			function proposer(champ, liste, dit, type, choisir) {
				var minuterie = null, tour = 0;
				function fermer() { liste.hidden = true; liste.textContent = ''; }
				champ.addEventListener('input', function () {
					window.clearTimeout(minuterie);
					var q = champ.value.trim();
					if (q.length < 3) { fermer(); return; }
					minuterie = window.setTimeout(function () {
						var mien = ++tour;
						chercher(type, q).then(function (resultats) {
							if (mien !== tour) { return; }
							liste.textContent = '';
							if (!resultats.length) { fermer(); dit.textContent = 'Aucun terme du thésaurus pour cette recherche.'; return; }
							dit.textContent = resultats.length + ' propositions.';
							resultats.forEach(function (item) {
								var li = document.createElement('li');
								var b = document.createElement('button');
								b.type = 'button';
								b.textContent = item.label;
								b.addEventListener('click', function () {
									fermer();
									choisir(item);
									champ.focus();
								});
								li.appendChild(b);
								liste.appendChild(li);
							});
							liste.hidden = false;
						}, function () {
							if (mien !== tour) { return; }
							fermer();
							dit.textContent = 'Le thésaurus ne répond pas pour l’instant.';
						});
					}, 300);
				});
				champ.addEventListener('keydown', function (e) {
					if (e.key === 'Escape') { fermer(); }
				});
				return fermer;
			}

			// Le département d'une commune, d'après son ARK, comme au dépôt :
			// l'avant-dernier élément du chemin dans Pactols, débarrassé de
			// « Département du… ». Chaque ARK n'est demandé qu'une fois.
			var departements = {};
			function departementDe(uri) {
				var m = String(uri).match(/ark:\/(.+)$/);
				if (!m) { return Promise.resolve(''); }
				if (departements[m[1]]) { return departements[m[1]]; }
				departements[m[1]] = fetch(R.pactols + 'api/searchwidgetbyark?q=' + encodeURIComponent(m[1]) + '&lang=fr',
					{ headers: { 'Accept': 'application/json' } }).then(function (r) {
					if (!r.ok) { throw new Error('HTTP ' + r.status); }
					return r.json();
				}).then(function (data) {
					if (!Array.isArray(data) || !data.length) { return ''; }
					var chemin = Array.isArray(data[0]) ? data[0] : data;
					if (chemin.length < 2) { return ''; }
					var nom = (chemin[chemin.length - 2] && chemin[chemin.length - 2].label) ? chemin[chemin.length - 2].label : '';
					if (nom.indexOf('épartement') === -1) {
						for (var i = chemin.length - 2; i >= 0; i--) {
							if (chemin[i].label && chemin[i].label.indexOf('épartement') !== -1) { nom = chemin[i].label; break; }
						}
					}
					return nom.replace(/^D[ée]partement\s+(du\s+|de la\s+|de l['’]\s*|des\s+|de\s+)/i, '').trim();
				}).catch(function () { delete departements[m[1]]; return ''; });
				return departements[m[1]];
			}
			function plier(t) {
				t = String(t || '').replace(/\s+/g, ' ').trim().replace(/[’‘ʼ`]/g, '\'').toLowerCase();
				t = t.replace(/^[\s()]+|[\s()]+$/g, '').replace(/^l'\s*/, '');
				return t.normalize ? t.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : t;
			}
			var QUALIFIE = /^(.*\S)\s*\(([^()]+)\)$/;
			var deptChamp = document.getElementById('na-rel-departement');

			// Les lieux du titre : un nom choisi dans la liste prend
			// l'identifiant du terme ; un nom retapé le perd, sauf à
			// redevenir celui que la notice portait. Comme au dépôt, la
			// première commune choisie remplit la parenthèse, et un libellé
			// « Falaise (Calvados) » perd la précision que la parenthèse dit
			// déjà ; l'identifiant reste celui du lieu choisi.
			[].forEach.call(form.querySelectorAll('.na-rel-lieu'), function (bloc, rang) {
				var champ = bloc.querySelector('input[type="text"]');
				var ark = bloc.querySelector('input[type="hidden"]');
				var dit = bloc.querySelector('.na-rel-dit');
				champ.addEventListener('input', function () {
					ark.value = (champ.value.trim() === champ.getAttribute('data-nom')) ? champ.getAttribute('data-ark') : '';
				});
				proposer(champ, bloc.querySelector('.na-rel-propositions'), dit, 'place', function (item) {
					champ.value = item.label;
					ark.value = item.ark;
					dit.textContent = item.label + ' retenu, avec son identifiant.';
					departementDe(item.ark).then(function (dept) {
						if (champ.value !== item.label || ark.value !== item.ark) { return; }
						var phrases = [item.label + ' retenu, avec son identifiant.'];
						if (0 === rang && dept && deptChamp && plier(deptChamp.value) !== plier(dept)) {
							deptChamp.value = dept;
							phrases.push('Précision géographique remplie d’après Pactols\u00a0: ' + dept + ', vérifiez.');
						}
						var m = QUALIFIE.exec(item.label);
						if (m && deptChamp && plier(m[2]) === plier(deptChamp.value)) {
							champ.value = m[1];
							phrases[0] = m[1] + ' retenu, avec son identifiant\u00a0: « ' + m[2] + ' » est dans la parenthèse.';
						}
						dit.textContent = phrases.join(' ');
						refaireLeTitre();
					});
					refaireLeTitre();
				});
			});

			// Le titre au-dessus du texte suit le lieu, le département et le
			// lieu-dit tels qu'ils sont dans le formulaire.
			var titre = document.getElementById('na-rel-titre-texte');
			function refaireLeTitre() {
				if (!titre) { return; }
				var noms = [];
				[].forEach.call(form.querySelectorAll('.na-rel-lieu input[type="text"]'), function (c) {
					if (c.value.trim()) { noms.push(c.value.trim().toLocaleUpperCase('fr')); }
				});
				var dept = deptChamp ? deptChamp.value.replace(/^[\s()]+|[\s()]+$/g, '') : '';
				var ld = document.getElementById('na-rel-lieu_dit');
				var lieuDit = ld ? ld.value.trim() : '';
				titre.textContent = noms.join(', ') + (dept ? ' (' + dept + ')' : '') + (lieuDit ? titre.getAttribute('data-avant') : '');
				if (lieuDit) {
					var bout = document.createElement(titre.getAttribute('data-italique') === '1' ? 'em' : 'span');
					bout.textContent = lieuDit;
					titre.appendChild(bout);
				}
			}
			form.addEventListener('input', function (e) {
				if (e.target && (e.target.id === 'na-rel-departement' || e.target.id === 'na-rel-lieu_dit' || /^na-rel-commune-/.test(e.target.id))) { refaireLeTitre(); }
			});

			// La matière ne vaut que pour la rubrique V : elle paraît quand la
			// direction choisit V, et se vide quand elle en sort.
			var matiere = document.getElementById('na-rel-matiere-bloc');
			var principale = document.getElementById('na-rel-rubrique_principale');
			if (matiere && principale) {
				principale.addEventListener('change', function () {
					matiere.hidden = principale.value !== matiere.getAttribute('data-rubrique');
					if (matiere.hidden) { document.getElementById('na-rel-matiere').value = ''; }
				});
			}

			// Une figure s'agrandit dans une fenêtre par-dessus la page ;
			// Échap, le bouton ou un clic à côté la referment.
			var boite = null;
			function ouvrir(bouton) {
				if (!boite) {
					boite = document.createElement('dialog');
					boite.className = 'na-rel-boite';
					boite.setAttribute('aria-label', 'Figure agrandie');
					boite.innerHTML = '<div class="na-rel-boite-barre"><span></span><button type="button">Fermer</button></div><img alt="">';
					boite.querySelector('button').addEventListener('click', function () { boite.close(); });
					boite.addEventListener('click', function (e) { if (e.target === boite) { boite.close(); } });
					boite.addEventListener('close', function () { if (boite.retour) { boite.retour.focus(); } });
					document.body.appendChild(boite);
				}
				var image = bouton.querySelector('img');
				boite.querySelector('span').textContent = bouton.getAttribute('data-titre');
				boite.querySelector('img').src = bouton.getAttribute('data-src');
				boite.querySelector('img').alt = image ? image.alt : '';
				boite.retour = bouton;
				if (typeof boite.showModal === 'function') {
					boite.showModal();
				} else {
					window.open(bouton.getAttribute('data-src'), '_blank', 'noopener');
				}
			}
			[].forEach.call(form.querySelectorAll('.na-rel-agrandir'), function (bouton) {
				bouton.addEventListener('click', function () { ouvrir(bouton); });
			});

			// Les mots-clés : on en retire, et l'on en ajoute en choisissant
			// dans la liste.
			[].forEach.call(form.querySelectorAll('.na-rel-termes'), function (bloc) {
				var champ = bloc.querySelector('input[type="hidden"]');
				var jetons = bloc.querySelector('.na-rel-jetons');
				var cherche = bloc.querySelector('.na-rel-cherche');
				var dit = bloc.querySelector('.na-rel-dit');
				var items = [];
				try { items = JSON.parse(champ.value) || []; } catch (e) { items = []; }
				function dessiner() {
					jetons.textContent = '';
					items.forEach(function (item, i) {
						var li = document.createElement('li');
						li.appendChild(document.createTextNode(item.label + (item.ark ? '' : ' (hors thésaurus)')));
						var b = document.createElement('button');
						b.type = 'button';
						b.disabled = !!R.vue;
						b.textContent = '×';
						b.setAttribute('aria-label', 'Retirer ' + item.label);
						b.addEventListener('click', function () {
							items.splice(i, 1);
							dessiner();
							dit.textContent = item.label + ' retiré.';
						});
						li.appendChild(b);
						jetons.appendChild(li);
					});
					champ.value = JSON.stringify(items);
				}
				proposer(cherche, bloc.querySelector('.na-rel-propositions'), dit, bloc.getAttribute('data-type'), function (item) {
					var deja = items.some(function (x) { return x.ark === item.ark; });
					if (deja) { dit.textContent = item.label + ' est déjà retenu.'; }
					else if (items.length >= R.maximum) { dit.textContent = R.maximum + ' termes au plus.'; }
					else { items.push(item); dessiner(); dit.textContent = item.label + ' ajouté.'; }
					cherche.value = '';
				});
				dessiner();
			});

			// Retirer une illustration demande un motif.
			[].forEach.call(form.querySelectorAll('.na-rel-retirer'), function (caseARetirer) {
				var bloc = document.getElementById(caseARetirer.getAttribute('aria-controls'));
				var motif = bloc ? bloc.querySelector('select') : null;
				function maj() {
					if (!bloc) { return; }
					bloc.hidden = !caseARetirer.checked;
					if (motif) { motif.required = caseARetirer.checked; }
				}
				caseARetirer.addEventListener('change', maj);
				maj();
			});

			// Entrée dans un champ d'une ligne n'envoie pas le formulaire.
			form.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' && e.target && e.target.tagName === 'INPUT' && e.target.type === 'text') { e.preventDefault(); }
			});
			form.addEventListener('submit', function (e) {
				// Vu comme un coordinateur, rien ne part.
				if (R.vue) { e.preventDefault(); return; }
				envoi = true;
				if (quill && touche && cache) {
					cache.value = 'b64:' + btoa(unescape(encodeURIComponent(quill.root.innerHTML)));
				}
			});

			// Le verrou : renouvelé chaque minute, rendu quand on quitte la page.
			// Vu comme un coordinateur, il n'y en a pas.
			if (R.vue) { return; }
			function poster(action) {
				var corps = new URLSearchParams();
				corps.append('action', action);
				corps.append('notice', String(R.notice));
				corps.append('_ajax_nonce', R.jeton);
				return corps;
			}
			window.setInterval(function () {
				fetch(R.ajax, { method: 'POST', credentials: 'same-origin', body: poster('na_relecture_verrou') }).catch(function () {});
			}, 60000);
			window.addEventListener('pagehide', function () {
				if (envoi || !navigator.sendBeacon) { return; }
				navigator.sendBeacon(R.ajax, poster('na_relecture_liberer'));
			});
		}());
		</script>
		<?php
	}

	// ── Les requêtes ───────────────────────────────────────────────────────

	/** Reçoit le formulaire de relecture, puis ramène à la notice ou mène à la suivante. */
	public function recevoir() {
		$id = isset( $_POST['notice'] ) ? absint( $_POST['notice'] ) : 0;
		if ( ! is_user_logged_in() || ( ! current_user_can( self::CAP ) && ! self::peut_tout() ) ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		check_admin_referer( 'na_relecture_' . $id );
		// Vu comme un coordinateur, rien ne s'enregistre, d'où que vienne
		// la demande.
		$vue = self::vue();
		if ( null !== $vue ) {
			self::refuser_pendant_la_vue( $vue, $id );
		}
		$plugin   = self::plugin();
		$rubrique = self::rubrique_demandee( self::rubriques_de(), $_POST );
		$rendu    = self::enregistrer( $id, isset( $_POST['version'] ) ? absint( $_POST['version'] ) : 0 );
		$bon      = in_array( $rendu['issue'], array( 'enregistree', 'inchangee' ), true );
		$lignes   = array();
		switch ( $rendu['issue'] ) {
			case 'enregistree':
				$lignes[] = sprintf( 'Enregistré : version %d (%s).', $rendu['version'], implode( ', ', (array) $rendu['champs'] ) );
				if ( ! empty( $rendu['faits']['figures_masquees'] ) && null !== $plugin ) {
					$lignes[] = 'Une figure a été retirée : vérifiez les appels « (fig. N) » du texte.';
					$lignes   = array_merge( $lignes, $plugin->appels_a_verifier( $id ) );
				}
				break;
			case 'inchangee':
				$lignes[] = 'Enregistré. La notice elle-même n’a pas changé.';
				break;
			case 'conflit':
				$lignes[] = 'La notice a été corrigée par ailleurs pendant que vous la relisiez : votre correction n’est pas enregistrée. Elle se rouvre telle qu’elle est maintenant.';
				break;
			case 'occupee':
				$lignes[] = 'La notice part en ce moment vers la rédaction : votre correction n’est pas enregistrée. Réessayez dans une minute.';
				break;
			case 'verrouillee':
				$lignes[] = sprintf( 'La notice est ouverte par %s : votre correction n’est pas enregistrée.', '' !== $rendu['par'] ? $rendu['par'] : 'quelqu’un d’autre' );
				break;
			case 'texte':
				$lignes[] = 'Le texte de la notice est vide, ou dépasse la longueur admise : rien n’est enregistré.';
				break;
			case 'motif':
				$lignes[] = 'Retirer une illustration demande un motif, et une précision pour « Autre » : rien n’est enregistré.';
				break;
			case 'figee':
				$lignes[] = 'La notice a été validée par la direction : elle ne se corrige plus. Rien n’est enregistré.';
				break;
			default:
				$lignes[] = 'Cette notice n’est pas de vos rubriques : rien n’est enregistré.';
		}
		$valide = $bon && 'validee' === $rendu['etat'] && isset( $_POST['suite'] ) && 'valider' === $_POST['suite'];
		if ( $valide ) {
			$lignes[] = 'La notice est validée : c’est sa version définitive.';
		}
		$vers = $id;
		if ( $bon && isset( $_POST['suite'] ) && in_array( $_POST['suite'], array( 'suivante', 'valider' ), true ) ) {
			self::liberer_le_verrou( $id, get_current_user_id() );
			$suite = self::ordre_de_lecture( $rubrique );
			$rang  = array_search( $id, array_map( 'intval', wp_list_pluck( $suite, 'id' ) ), true );
			if ( false !== $rang && isset( $suite[ $rang + 1 ] ) ) {
				$vers     = (int) $suite[ $rang + 1 ]['id'];
				$rubrique = $suite[ $rang + 1 ]['rubrique'];
			} elseif ( false === $rang && ! empty( $suite ) ) {
				$vers     = (int) $suite[0]['id'];
				$rubrique = $suite[0]['rubrique'];
			} else {
				$lignes[] = self::peut_tout() ? 'C’était la dernière notice du fascicule.' : 'C’était la dernière notice de la rubrique.';
			}
		}
		self::dire( $bon ? 'success' : 'error', $lignes );
		wp_safe_redirect( self::url( $rubrique, null === self::droit_sur( $vers ) ? 0 : $vers ) );
		exit;
	}

	/**
	 * Le bouton « Soumettre à l'auteur » de la direction : le courriel part
	 * avec le Word à suivi de modifications, et la notice passe à « soumise
	 * à l'auteur ». Un courriel refusé laisse tout en l'état, et se dit.
	 */
	public function soumettre() {
		$id = isset( $_POST['notice'] ) ? absint( $_POST['notice'] ) : 0;
		if ( ! self::peut_tout() ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		check_admin_referer( 'na_relecture_soumettre_' . $id );
		// Vu comme un coordinateur, rien ne s'enregistre, d'où que vienne
		// la demande.
		$vue = self::vue();
		if ( null !== $vue ) {
			self::refuser_pendant_la_vue( $vue, $id );
		}
		$plugin = self::plugin();
		$droit  = self::droit_sur( $id );
		if ( null === $plugin || null === $droit ) {
			wp_die( esc_html__( 'Notice inconnue.', 'notice-archeomed' ) );
		}
		$refus = self::soumettre_la_notice( $id );
		self::dire( '' === $refus ? 'success' : 'error', '' === $refus
			? 'Le courriel est parti vers l’auteur, avec le Word à suivi de modifications.'
			: 'Rien n’est parti : ' . $refus . '.' );
		wp_safe_redirect( self::url( self::rubrique_demandee( self::rubriques_de(), $_POST ), $id ) );
		exit;
	}

	/**
	 * Soumet une notice à son auteur et note l'envoi ; rend une chaîne vide,
	 * ou ce qui l'a empêché. L'état ne change que si le courriel est parti.
	 */
	public static function soumettre_la_notice( $id, $utilisateur = null ) {
		$plugin = self::plugin();
		if ( null === $plugin ) {
			return 'le plugin n’est pas chargé';
		}
		if ( self::compte( $utilisateur ) instanceof Notice_Archeomed_Regard ) {
			return 'la relecture vue comme un coordinateur n’envoie rien';
		}
		// Rien ne part à l'auteur sans modification de fond : le bouton ne
		// se montre que pour une notice « à soumettre », et le serveur le
		// vérifie — une requête rejouée ou forgée n'envoie rien.
		$etat = self::etat_de( $id );
		if ( 'a_soumettre' !== $etat ) {
			return 'la notice n’est pas « à soumettre à l’auteur » (elle est « ' . self::ETATS[ $etat ] . ' »)';
		}
		$refus = $plugin->soumettre_a_l_auteur( $id );
		if ( '' !== $refus ) {
			return $refus;
		}
		update_post_meta( (int) $id, '_na_relecture_etat', 'soumise' );
		self::noter_au_journal( $id, $utilisateur, array( 'courriel à l’auteur, avec le Word à suivi de modifications',
			'état : ' . self::ETATS[ $etat ] . ' → ' . self::ETATS['soumise'] ), $etat, 'soumise' );
		return '';
	}

	/**
	 * Rouvre une notice validée : elle reprend l'état qu'elle avait avant la
	 * validation, et se corrige de nouveau. Réservé à la direction ; la
	 * ligne du journal garde qui l'a rouverte, quand, et pourquoi. Rend une
	 * chaîne vide, ou ce qui l'a empêché.
	 */
	public static function rouvrir_la_notice( $id, $utilisateur = null, $motif = '' ) {
		$id     = (int) $id;
		$compte = self::compte( $utilisateur );
		if ( ! self::peut_tout( $compte ) || null === self::droit_sur( $id, $compte ) ) {
			return 'seule la direction rouvre une notice validée';
		}
		if ( 'validee' !== self::etat_de( $id ) ) {
			return 'la notice n’est pas validée';
		}
		$avant = (string) get_post_meta( $id, '_na_relecture_avant_validation', true );
		$avant = ( array_key_exists( $avant, self::ETATS ) && 'validee' !== $avant ) ? $avant : 'a_relire';
		update_post_meta( $id, '_na_relecture_etat', $avant );
		delete_post_meta( $id, '_na_relecture_avant_validation' );
		self::noter_au_journal( $id, $compte, array( 'rouverte après validation',
			'état : ' . self::ETATS['validee'] . ' → ' . self::ETATS[ $avant ] ), 'validee', $avant, (string) $motif );
		return '';
	}

	/** Le bouton « Rouvrir » d'une notice validée. */
	public function recevoir_la_reouverture() {
		$id = isset( $_POST['notice'] ) ? absint( $_POST['notice'] ) : 0;
		if ( ! self::peut_tout() ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		check_admin_referer( 'na_relecture_rouvrir_' . $id );
		// Vu comme un coordinateur, rien ne s'enregistre, d'où que vienne
		// la demande.
		$vue = self::vue();
		if ( null !== $vue ) {
			self::refuser_pendant_la_vue( $vue, $id );
		}
		$motif = isset( $_POST['motif'] ) && is_string( $_POST['motif'] )
			? trim( sanitize_textarea_field( wp_unslash( $_POST['motif'] ) ) ) : '';
		$motif = function_exists( 'mb_substr' ) ? mb_substr( $motif, 0, 2000 ) : substr( $motif, 0, 2000 );
		$refus = self::rouvrir_la_notice( $id, null, $motif );
		self::dire( '' === $refus ? 'success' : 'error', '' === $refus
			? 'La notice est rouverte : elle se corrige de nouveau, et devra être validée encore.'
			: 'Rien n’est changé : ' . $refus . '.' );
		wp_safe_redirect( self::url( self::rubrique_demandee( self::rubriques_de(), $_POST ), null === self::droit_sur( $id ) ? 0 : $id ) );
		exit;
	}

	/**
	 * Les notices dans l'ordre où l'on passe de l'une à la suivante : pour
	 * la direction, toutes, rubrique après rubrique ; pour un coordinateur,
	 * celles de la rubrique, puis les renvois vers elle.
	 */
	private static function ordre_de_lecture( $rubrique ) {
		$plugin = self::plugin();
		if ( null === $plugin ) {
			return array();
		}
		if ( self::peut_tout() ) {
			$ordre = array();
			foreach ( $plugin->rubriques() as $une ) {
				foreach ( $plugin->sommaire_de_la_rubrique( $une ) as $entree ) {
					$ordre[] = array( 'id' => (int) $entree['id'], 'rubrique' => $une );
				}
			}
			return $ordre;
		}
		$sommaire = self::sommaire( $rubrique );
		$ordre    = array();
		foreach ( array_merge( $sommaire['notices'], $sommaire['renvois'] ) as $entree ) {
			$ordre[] = array( 'id' => (int) $entree['id'], 'rubrique' => $rubrique );
		}
		return $ordre;
	}

	/** Rend le Word de relecture d'une version à qui peut lire la notice. */
	public function rendre_le_word() {
		$id = isset( $_GET['notice'] ) ? absint( $_GET['notice'] ) : 0;
		check_admin_referer( 'na_relecture_word_' . $id );
		// Vu comme un coordinateur : ce qu'il obtiendrait, ni plus ni moins.
		$droit  = self::droit_sur( $id, self::regard() );
		$plugin = self::plugin();
		if ( null === $droit || null === $plugin ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		$version = isset( $_GET['version'] ) ? absint( $_GET['version'] ) : 0;
		$erreur  = '';
		// Sans les adresses des responsables pour un coordinateur.
		$chemin  = $plugin->word_de_relecture( $id, $version, $erreur, 0, 'tout' !== $droit['sorte'] );
		if ( '' === $chemin || ! is_readable( $chemin ) ) {
			wp_die( esc_html( 'Le Word de relecture ne se fabrique pas : ' . $erreur . '.' ) );
		}
		$gardee = self::version_gardee( $id, $version );
		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( self::nom_du_word( $id, $version, null !== $gardee ? $gardee['par'] : '' ) ) . '"' );
		header( 'Content-Length: ' . filesize( $chemin ) );
		readfile( $chemin );
		@unlink( $chemin );
		exit;
	}

	/** Sert l'aperçu d'une figure à qui peut lire la notice, et à lui seul. */
	public function rendre_l_apercu() {
		$id = isset( $_GET['notice'] ) ? absint( $_GET['notice'] ) : 0;
		check_admin_referer( 'na_relecture_apercu_' . $id );
		$plugin = self::plugin();
		if ( null === self::droit_sur( $id, self::regard() ) || null === $plugin ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		$chemin = $plugin->chemin_de_l_apercu( $id, isset( $_GET['rang'] ) ? absint( $_GET['rang'] ) : 0 );
		if ( '' === $chemin ) {
			status_header( 404 );
			exit;
		}
		$type = wp_check_filetype( $chemin );
		header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'image/jpeg' ) );
		header( 'Content-Length: ' . filesize( $chemin ) );
		header( 'Cache-Control: private, max-age=600' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $chemin );
		exit;
	}

	/** Sert la notice en lecture seule, dans le cadre de l'écran. */
	public function rendre_la_lecture() {
		$id = isset( $_GET['notice'] ) ? absint( $_GET['notice'] ) : 0;
		check_admin_referer( 'na_relecture_lire_' . $id );
		$plugin = self::plugin();
		if ( null === self::droit_sur( $id, self::regard() ) || null === $plugin ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		// Déjà échappé pièce à pièce par la page de relecture.
		echo $plugin->page_a_lire( $id, function ( $rang ) use ( $id ) {
			return self::url_de_l_apercu( $id, $rang );
		} );
		exit;
	}

	// ── Les réglages ───────────────────────────────────────────────────────

	/** L'onglet « Relecture » des réglages : la clôture, et qui relit quelle rubrique. */
	public static function onglet() {
		$plugin   = self::plugin();
		$reglages = self::reglages();
		$comptes  = get_users( array( 'role__in' => array( self::ROLE ), 'orderby' => 'display_name', 'fields' => array( 'ID', 'display_name' ) ) );
		echo '<h2>' . esc_html__( 'La page de relecture', 'notice-archeomed' ) . '</h2>';
		self::dire_la_page();
		if ( self::peut_tout() ) {
			echo '<p><a href="' . esc_url( add_query_arg( 'page', self::PAGE, admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Voir la relecture comme un coordinateur', 'notice-archeomed' ) . '</a> — '
				. esc_html__( 'un compte nommé, ou une rubrique même sans compte, sans rien enregistrer.', 'notice-archeomed' ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="na_relecture_reglages">';
		wp_nonce_field( 'na_relecture_reglages' );
		echo '<h2>' . esc_html__( 'Campagne', 'notice-archeomed' ) . '</h2>'
			. '<p><label><input type="checkbox" name="close" value="1"' . checked( $reglages['close'], true, false ) . '> '
			. esc_html__( 'La campagne est close', 'notice-archeomed' ) . '</label></p>'
			. '<p class="description" style="max-width:46em">' . esc_html__( 'Cochée, la relecture s’ouvre aux coordinateurs et à la direction, et les liens de correction envoyés aux auteurs ne corrigent plus : qui les suit lit que la campagne est close. Le dépôt de nouvelles notices, lui, reste ouvert tant que la page du formulaire est publiée.', 'notice-archeomed' ) . '</p>';
		echo '<h2>' . esc_html__( 'Coordinateurs de rubrique', 'notice-archeomed' ) . '</h2>';
		if ( empty( $comptes ) ) {
			echo '<p>' . esc_html__( 'Aucun compte n’a le rôle « Coordinateur de rubrique (Chronique) ». Créez les comptes des coordinateurs avec ce rôle, puis revenez les associer à leur rubrique.', 'notice-archeomed' ) . '</p>';
		} elseif ( null !== $plugin ) {
			echo '<table class="widefat striped" style="max-width:60em"><thead><tr><th scope="col">' . esc_html__( 'Rubrique', 'notice-archeomed' ) . '</th><th scope="col">' . esc_html__( 'Coordinateurs', 'notice-archeomed' ) . '</th></tr></thead><tbody>';
			foreach ( $plugin->rubriques() as $rang => $rubrique ) {
				$confiee = isset( $reglages['coordinateurs'][ $rubrique ] ) ? array_map( 'intval', (array) $reglages['coordinateurs'][ $rubrique ] ) : array();
				echo '<tr><th scope="row">' . esc_html( $rubrique ) . '</th><td>';
				foreach ( $comptes as $compte ) {
					echo '<label style="display:inline-block;margin:0 1.2em .3em 0"><input type="checkbox" name="coordinateurs[' . (int) $rang . '][]" value="' . (int) $compte->ID . '"'
						. checked( in_array( (int) $compte->ID, $confiee, true ), true, false ) . '> ' . esc_html( $compte->display_name ) . '</label>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<p class="description" style="max-width:46em">' . esc_html__( 'La direction de la revue relit toutes les rubriques et valide chaque notice : les comptes du rôle « Direction de la revue », et les administrateurs du site.', 'notice-archeomed' ) . '</p>';
		submit_button( __( 'Enregistrer', 'notice-archeomed' ) );
		echo '</form>';
	}

	/** Enregistre l'onglet « Relecture ». */
	public function recevoir_les_reglages() {
		if ( ! Notice_Archeomed_Settings::peut_regler() ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		check_admin_referer( 'na_relecture_reglages' );
		self::regler( ! empty( $_POST['close'] ),
			isset( $_POST['coordinateurs'] ) && is_array( $_POST['coordinateurs'] ) ? wp_unslash( $_POST['coordinateurs'] ) : array() );
		add_settings_error( Notice_Archeomed_Settings::OPTION_NAME, 'relecture', __( 'Réglages enregistrés.', 'notice-archeomed' ), 'success' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( 'settings-updated', 'true', Notice_Archeomed_Settings::url( 'relecture' ) ) );
		exit;
	}

	/**
	 * Pose les réglages : la clôture, et pour chaque rubrique — désignée par
	 * son rang — les comptes qui la relisent. Seuls les comptes qui ont la
	 * capacité de relire sont retenus.
	 */
	public static function regler( $close, $par_rang ) {
		$plugin        = self::plugin();
		$coordinateurs = array();
		foreach ( null !== $plugin ? $plugin->rubriques() : array() as $rang => $rubrique ) {
			$comptes = array();
			foreach ( ( isset( $par_rang[ $rang ] ) && is_array( $par_rang[ $rang ] ) ) ? $par_rang[ $rang ] : array() as $compte ) {
				$compte = is_scalar( $compte ) ? absint( $compte ) : 0;
				if ( $compte && user_can( $compte, self::CAP ) && ! in_array( $compte, $comptes, true ) ) {
					$comptes[] = $compte;
				}
			}
			if ( ! empty( $comptes ) ) {
				$coordinateurs[ $rubrique ] = $comptes;
			}
		}
		update_option( self::OPTION, array( 'close' => $close ? 1 : 0, 'coordinateurs' => $coordinateurs ), false );
	}
}

/**
 * Le regard d'un coordinateur, que la direction emprunte pour voir la
 * relecture comme lui.
 *
 * C'est un compte au sens de WordPress, que les fonctions de droits
 * reçoivent comme un autre : celui d'un coordinateur nommé porte son
 * numéro, ses rôles et ses capacités tels que la base les donne, et ne
 * voit donc ni plus ni moins que lui. Celui d'une rubrique sans compte n'a
 * pas de numéro réel (-1, que WordPress ne rattache à personne) : il porte
 * le rôle de coordinateur et la seule rubrique choisie.
 *
 * Être un regard suffit à tout refuser : la relecture n'enregistre, ne
 * verrouille, n'envoie ni ne note au journal rien de ce qu'il demande.
 */
class Notice_Archeomed_Regard extends WP_User {

	/**
	 * La rubrique d'un regard sans compte, en liste ; null pour le regard
	 * d'un compte, dont les réglages disent les rubriques.
	 */
	public $rubriques_fixees = null;

	/** Le regard d'un coordinateur nommé : ses droits, relus de la base. */
	public static function du_compte( WP_User $compte ) {
		return new self( $compte );
	}

	/** Le regard de la coordination d'une rubrique, qu'elle ait un compte ou non. */
	public static function de_la_rubrique( $rubrique ) {
		$regard = new self();
		$numero = (string) preg_replace( '/^([IVX]+)\..*$/u', '$1', (string) $rubrique );
		// Les champs qu'on lit d'un compte, posés d'avance : sans eux,
		// WordPress les chercherait dans les métadonnées d'un compte réel.
		$regard->data = (object) array(
			'ID'            => -1,
			'user_login'    => '',
			'user_email'    => '',
			'user_nicename' => '',
			'display_name'  => 'Coordination de la rubrique ' . $numero,
		);
		$regard->ID   = -1;
		$role         = get_role( Notice_Archeomed_Relecture::ROLE );
		$capacites    = null !== $role ? array_filter( (array) $role->capabilities ) : array( 'read' => true );
		$capacites[ Notice_Archeomed_Relecture::CAP ] = true;
		$regard->roles   = array( Notice_Archeomed_Relecture::ROLE );
		$regard->caps    = array( Notice_Archeomed_Relecture::ROLE => true );
		$regard->allcaps = $capacites;
		$regard->rubriques_fixees = array( (string) $rubrique );
		return $regard;
	}
}
