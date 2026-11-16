<?php
/**
 * La relecture des notices sur le site, par les coordinateurs de rubrique
 * puis par la rédaction.
 *
 * Chaque coordinateur relisait sa partie dans un Word stylé, et en cassait
 * souvent les styles : une métadonnée ajoutée à la main, le fichier ouvert
 * dans un autre traitement de texte. La relecture se fait donc ici, dans un
 * formulaire qui écrit dans la saisie gardée — celle d'où se refont le
 * Word, le fascicule et le dossier Métopes.
 *
 * Elle s'ouvre quand la campagne est close, et les liens de correction des
 * auteurs se ferment au même instant. Un coordinateur ne voit que les
 * notices de ses rubriques, dans l'ordre du fascicule ; la rédaction les
 * voit toutes, avec le même écran. Une notice ouverte est verrouillée pour
 * les autres, chaque enregistrement garde la version d'avant, et le Word
 * de relecture — le suivi des modifications, signé du relecteur — part
 * sur sDrive par une tâche à part : une panne de sDrive ne coûte pas une
 * correction.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Relecture {

	/** Le rôle d'un coordinateur de rubrique : lire son profil, relire, rien d'autre. */
	const ROLE = 'na_coordinateur';

	/** Le rôle de la rédaction, pour qui n'administre pas le site. */
	const ROLE_REDACTION = 'na_redaction';

	/** La capacité de relire les rubriques qu'on vous a confiées. */
	const CAP = 'na_relire';

	/** La capacité de la rédaction : toutes les rubriques, tous les états. */
	const CAP_TOUT = 'na_relire_tout';

	/** L'option des réglages : campagne close, coordinateurs par rubrique. */
	const OPTION = 'notice_archeomed_relecture';

	/** L'écran de relecture. */
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
		'validee'     => 'Validée par la rédaction',
	);

	/** Les états où le coordinateur de la rubrique corrige encore. */
	const ETATS_DU_COORDINATEUR = array( 'a_relire', 'relue', 'a_soumettre' );

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'assurer_les_roles' ) );
		add_action( 'admin_menu', array( $this, 'poser_le_menu' ) );
		add_action( 'admin_post_na_relecture', array( $this, 'recevoir' ) );
		add_action( 'admin_post_na_relecture_reglages', array( $this, 'recevoir_les_reglages' ) );
		add_action( 'admin_post_na_relecture_word', array( $this, 'rendre_le_word' ) );
		add_action( 'admin_post_na_relecture_apercu', array( $this, 'rendre_l_apercu' ) );
		add_action( 'admin_post_na_relecture_lire', array( $this, 'rendre_la_lecture' ) );
		add_action( 'admin_post_na_relecture_soumettre', array( $this, 'soumettre' ) );
		add_action( 'wp_ajax_na_relecture_verrou', array( $this, 'tenir_le_verrou' ) );
		add_action( 'wp_ajax_na_relecture_liberer', array( $this, 'rendre_le_verrou' ) );
		add_action( self::HOOK, array( __CLASS__, 'en_tache' ), 10, 0 );
		// Au passage de la relance, après la file et le rangement.
		add_action( Notice_Archeomed_File::HOOK_RELANCE, array( __CLASS__, 'reveiller' ), 22, 0 );
	}

	// ── Les droits ─────────────────────────────────────────────────────────

	/**
	 * Les deux rôles, et la capacité de la rédaction pour l'administrateur.
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
		if ( null === get_role( self::ROLE_REDACTION ) ) {
			add_role( self::ROLE_REDACTION, 'Rédaction de la Chronique (relecture)',
				array( 'read' => true, self::CAP => true, self::CAP_TOUT => true ) );
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
		return $utilisateur ? get_userdata( (int) $utilisateur ) : wp_get_current_user();
	}

	/** Ce compte relit-il pour la rédaction : toutes les rubriques, tous les états ? */
	public static function peut_tout( $utilisateur = null ) {
		$compte = self::compte( $utilisateur );
		return ( $compte instanceof WP_User ) && $compte->exists()
			&& ( user_can( $compte, self::CAP_TOUT ) || user_can( $compte, 'manage_options' ) );
	}

	/**
	 * Les rubriques qu'un compte relit : toutes pour la rédaction, celles
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
	 * { sorte, rubrique }. La sorte est « tout » pour la rédaction,
	 * « principale » pour le coordinateur de sa rubrique, « renvoi » pour
	 * celui d'une rubrique où elle renvoie, qui la lit sans la corriger.
	 *
	 * Tout passe par ici, l'affichage comme chaque écriture : l'identifiant
	 * d'une notice se forge dans une adresse ou un champ caché, et c'est la
	 * notice elle-même qui dit sa rubrique — jamais la requête. Avant la
	 * clôture de la campagne, personne ne relit.
	 */
	public static function droit_sur( $id, $utilisateur = null ) {
		$id     = (int) $id;
		$compte = self::compte( $utilisateur );
		$plugin = self::plugin();
		if ( ! $id || null === $plugin || ! self::campagne_close() || ! ( $compte instanceof WP_User ) || ! $compte->exists() ) {
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
	 * Ce droit permet-il de corriger la notice dans cet état ? La rédaction
	 * toujours ; le coordinateur de la rubrique tant que la notice n'est pas
	 * partie vers l'auteur ni validée ; celui d'un renvoi jamais.
	 */
	public static function peut_corriger( $droit, $etat ) {
		if ( ! is_array( $droit ) ) {
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
	 * Le nom du Word de relecture d'une version : « notice_v3_relecture_
	 * Jeanne_Martin.docx ». Il porte la version et le nom du relecteur, à
	 * côté des « notice_v1.docx » que le rangement dépose.
	 */
	public static function nom_du_word( $version, $par ) {
		return 'notice_v' . (int) $version . '_relecture_' . Notice_Archeomed_Nommage::assainir( (string) $par, 'relecteur' ) . '.docx';
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
	 * rédaction, et la ligne du journal qui les dit.
	 *
	 * Le droit se relit ici, sur la notice : un identifiant forgé ne donne
	 * rien. Le coordinateur d'un renvoi ne peut que retirer le renvoi vers
	 * sa rubrique et laisser une remarque ; celui de la rubrique ne peut
	 * faire avancer l'état que vers « relue » ou « à soumettre à l'auteur ».
	 *
	 * Rend ce que rend l'enregistrement de la saisie, avec l'état ; l'issue
	 * peut être aussi « interdit » ou « verrouillee » (avec « par »).
	 */
	public static function enregistrer( $id, $lue, $utilisateur = null ) {
		$id     = (int) $id;
		$compte = self::compte( $utilisateur );
		$plugin = self::plugin();
		$droit  = self::droit_sur( $id, $compte );
		$rendu  = array( 'issue' => 'interdit', 'version' => 0, 'champs' => array(), 'faits' => array(), 'etat' => '', 'par' => '' );
		if ( null === $droit || null === $plugin ) {
			return $rendu;
		}
		$etat          = self::etat_de( $id );
		$rendu['etat'] = $etat;
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

		// ── L'état. Le coordinateur ne le fait qu'avancer ; la rédaction le
		// pose où elle veut, et rouvre une notice validée. ────────────────
		$nouvel = $etat;
		if ( 'tout' === $droit['sorte'] ) {
			$demande = isset( $_POST['etat'] ) && is_string( $_POST['etat'] ) ? sanitize_key( wp_unslash( $_POST['etat'] ) ) : '';
			if ( array_key_exists( $demande, self::ETATS ) ) {
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
		$rendu['etat'] = $nouvel;

		// ── La remarque à la rédaction : hors de la notice, donc hors de ce
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
			$distant = $dossier . '/' . self::nom_du_word( $version, $gardee['par'] );
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
					'clef'    => $plugin->classement_de( $d ),
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

	/** L'entrée « Relecture » du menu, pour qui relit. */
	public function poser_le_menu() {
		add_menu_page(
			__( 'Relecture de la Chronique', 'notice-archeomed' ),
			__( 'Relecture', 'notice-archeomed' ),
			self::CAP,
			self::PAGE,
			array( $this, 'afficher' ),
			'dashicons-visibility',
			26
		);
	}

	/** L'adresse de l'écran, pour une rubrique et une notice. */
	public static function url( $rubrique = '', $notice = 0 ) {
		$plugin = self::plugin();
		$rang   = ( null !== $plugin && '' !== $rubrique ) ? array_search( $rubrique, $plugin->rubriques(), true ) : false;
		$args   = array( 'page' => self::PAGE );
		if ( false !== $rang ) {
			$args['rubrique'] = $rang + 1;
		}
		if ( $notice ) {
			$args['notice'] = (int) $notice;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
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
	private static function rubrique_demandee( $siennes, $source ) {
		$plugin = self::plugin();
		$rang   = isset( $source['rubrique'] ) && is_scalar( $source['rubrique'] ) ? absint( $source['rubrique'] ) : 0;
		$toutes = null !== $plugin ? $plugin->rubriques() : array();
		if ( $rang >= 1 && isset( $toutes[ $rang - 1 ] ) && in_array( $toutes[ $rang - 1 ], $siennes, true ) ) {
			return $toutes[ $rang - 1 ];
		}
		return ! empty( $siennes ) ? $siennes[0] : '';
	}

	/** L'écran de relecture : le sommaire à gauche, la notice à droite. */
	public function afficher() {
		if ( ! current_user_can( self::CAP ) && ! self::peut_tout() ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		$plugin = self::plugin();
		echo '<div class="wrap na-rel"><h1>' . esc_html__( 'Relecture de la Chronique', 'notice-archeomed' ) . '</h1>';
		self::poser_les_styles();
		$message = get_transient( 'na_relecture_message_' . get_current_user_id() );
		if ( is_array( $message ) && ! empty( $message['lignes'] ) ) {
			delete_transient( 'na_relecture_message_' . get_current_user_id() );
			echo '<div class="notice inline notice-' . esc_attr( in_array( $message['type'], array( 'success', 'warning', 'error' ), true ) ? $message['type'] : 'info' ) . '" role="status">';
			foreach ( (array) $message['lignes'] as $ligne ) {
				echo '<p>' . esc_html( $ligne ) . '</p>';
			}
			echo '</div>';
		}
		if ( null === $plugin ) {
			echo '</div>';
			return;
		}
		if ( ! self::campagne_close() ) {
			echo '<p>' . esc_html__( 'La relecture s’ouvrira quand la campagne sera close.', 'notice-archeomed' );
			if ( current_user_can( 'manage_options' ) ) {
				echo ' <a href="' . esc_url( Notice_Archeomed_Settings::url( 'relecture' ) ) . '">' . esc_html__( 'Régler la relecture', 'notice-archeomed' ) . '</a>';
			}
			echo '</p></div>';
			return;
		}
		$siennes = self::rubriques_de();
		if ( empty( $siennes ) ) {
			echo '<p>' . esc_html__( 'Aucune rubrique ne vous est confiée.', 'notice-archeomed' ) . '</p></div>';
			return;
		}
		$rubrique = self::rubrique_demandee( $siennes, $_GET );
		$sommaire = self::sommaire( $rubrique );
		$id       = isset( $_GET['notice'] ) ? absint( $_GET['notice'] ) : 0;
		$droit    = $id ? self::droit_sur( $id ) : null;

		if ( count( $siennes ) > 1 ) {
			echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Rubriques', 'notice-archeomed' ) . '">';
			foreach ( $siennes as $une ) {
				echo '<a class="nav-tab' . ( $une === $rubrique ? ' nav-tab-active' : '' ) . '" href="' . esc_url( self::url( $une ) ) . '"'
					. ( $une === $rubrique ? ' aria-current="page"' : '' ) . '>' . esc_html( preg_replace( '/^([IVX]+)\..*$/u', '$1', $une ) ) . '</a>';
			}
			echo '</nav>';
		}
		echo '<div class="na-rel-cadre">';
		$this->afficher_le_sommaire( $rubrique, $sommaire, $id );
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
			$famille = null;
			foreach ( $sommaire[ $bloc ] as $entree ) {
				if ( $entree['famille'] !== $famille ) {
					echo ( null !== $famille ? '</ol>' : '' ) . '<h3>' . esc_html( $entree['famille'] ) . '</h3><ol>';
					$famille = $entree['famille'];
				}
				$etat   = self::etat_de( $entree['id'] );
				$verrou = self::verrou_de( $entree['id'] );
				$tenant = ( $verrou['par'] && $verrou['par'] !== get_current_user_id() ) ? get_userdata( $verrou['par'] ) : null;
				echo '<li' . ( (int) $entree['id'] === (int) $ouverte ? ' class="na-rel-ouverte"' : '' ) . '>'
					. '<a href="' . esc_url( self::url( $rubrique, $entree['id'] ) ) . '"' . ( (int) $entree['id'] === (int) $ouverte ? ' aria-current="true"' : '' ) . '>'
					. esc_html( $entree['titre'] ) . '</a>'
					. ' <span class="na-rel-etat na-rel-etat--' . esc_attr( $etat ) . '">' . esc_html( self::ETATS[ $etat ] ) . '</span>'
					. ( $tenant ? ' <span class="na-rel-verrou">' . esc_html( sprintf( 'ouverte par %s', $tenant->display_name ) ) . '</span>' : '' )
					. '</li>';
			}
			echo '</ol>';
		}
		echo '</nav>';
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
			$verrou = self::prendre_le_verrou( $id, get_current_user_id() );
			if ( ! $verrou['tenu'] ) {
				$corrige = false;
				$tenant  = get_userdata( $verrou['par'] );
			}
		}
		echo '<h2 class="na-rel-titre">' . esc_html( $plugin->titre_de_liste( $notice['d'] ) ) . '</h2>';
		echo '<p class="na-rel-entete"><span class="na-rel-etat na-rel-etat--' . esc_attr( $etat ) . '">' . esc_html( self::ETATS[ $etat ] ) . '</span> '
			. esc_html( sprintf( 'Version %d', $notice['version'] ) ) . '</p>';
		if ( $tenant ) {
			echo '<div class="notice inline notice-warning"><p>' . esc_html( sprintf(
				'Cette notice est ouverte par %s : elle se lit, mais ne se corrige pas tant qu’elle ne l’a pas rendue.', $tenant->display_name ) ) . '</p></div>';
		} elseif ( 'a_soumettre' === $etat && 'tout' === $droit['sorte'] ) {
			echo '<div class="notice inline notice-warning"><p>' . esc_html__( 'Modification de fond : un accord écrit de l’auteur est nécessaire. Le Word à suivi de modifications lui part quand vous le demandez.', 'notice-archeomed' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="na_relecture_soumettre">'
				. '<input type="hidden" name="notice" value="' . (int) $id . '"><input type="hidden" name="rubrique" value="' . esc_attr( (string) ( array_search( $rubrique, $plugin->rubriques(), true ) + 1 ) ) . '">';
			wp_nonce_field( 'na_relecture_soumettre_' . (int) $id );
			echo '<p><button type="submit" class="button">' . esc_html__( 'Soumettre à l’auteur', 'notice-archeomed' ) . '</button></p></form></div>';
		}
		$rappels = $plugin->appels_a_verifier( $id );
		$masquee = false;
		foreach ( $notice['figures'] as $figure ) {
			$masquee = $masquee || null !== $figure['masquee'];
		}
		if ( $masquee ) {
			echo '<div class="notice inline notice-info"><p>' . esc_html__( 'Une figure a été retirée : les figures qui restent se numérotent à la suite. Vérifiez les appels « (fig. N) » du texte.', 'notice-archeomed' ) . '</p>';
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
	 * rédaction les lit, sans pouvoir les changer ici.
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
		if ( Notice_Archeomed_Pactols::RUBRIQUE_ARTISANAT === $principale || $tout ) {
			echo '<p class="na-rel-champ"><label for="na-rel-matiere">' . esc_html__( 'Matière (rubrique V)', 'notice-archeomed' ) . '</label><select id="na-rel-matiere" name="rubrique_matiere"><option value="">—</option>';
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
			self::champ( 'commune[]', 0 === $i ? 'Commune ou lieu' : 'Autre lieu du titre', isset( $lieux[ $i ] ) ? $lieux[ $i ]['nom'] : '', 'na-rel-commune-' . $i );
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
				. '<label class="screen-reader-text" for="na-rel-cherche-' . esc_attr( $liste ) . '">' . esc_html( sprintf( 'Chercher dans le thésaurus : %s', $sorte[1] ) ) . '</label>'
				. '<input type="text" class="na-rel-cherche" id="na-rel-cherche-' . esc_attr( $liste ) . '" autocomplete="off" placeholder="' . esc_attr__( 'Ajouter depuis le thésaurus…', 'notice-archeomed' ) . '">'
				. '<ul class="na-rel-propositions" hidden></ul><span class="na-rel-dit" aria-live="polite"></span></div>';
		}
		echo '</fieldset>';

		// ── Texte ──
		$texte = $valeur( 'texte_notice' );
		echo '<fieldset><legend id="na-rel-texte-libelle">' . esc_html__( 'Texte', 'notice-archeomed' ) . '</legend>'
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
			// la rédaction un texte, non un champ — il ne se change pas ici.
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
				echo '<option value="' . esc_attr( $cle ) . '"' . selected( $cle, $etat, false ) . '>' . esc_html( $libelle ) . '</option>';
			}
			echo '</select></p>';
		} else {
			echo '<p><label><input type="checkbox" name="relue" value="1"' . checked( 'a_relire' !== $etat, true, false ) . ( 'a_relire' !== $etat ? ' disabled' : '' ) . '> ' . esc_html__( 'Notice relue', 'notice-archeomed' ) . '</label></p>'
				. '<p><label><input type="checkbox" name="fond" value="1"' . checked( 'a_soumettre' === $etat, true, false ) . ( 'a_soumettre' === $etat ? ' disabled' : '' ) . '> ' . esc_html__( 'Modification de fond (accord de l’auteur nécessaire)', 'notice-archeomed' ) . '</label></p>';
		}
		echo '<p class="na-rel-champ"><label for="na-rel-remarque">' . esc_html__( 'Remarque à la rédaction', 'notice-archeomed' ) . '</label>'
			. '<textarea id="na-rel-remarque" name="remarque" rows="3"></textarea></p></fieldset>';
		echo '<p class="na-rel-boutons"><button type="submit" name="suite" value="rester" class="button button-primary">' . esc_html__( 'Enregistrer', 'notice-archeomed' ) . '</button> '
			. '<button type="submit" name="suite" value="suivante" class="button">' . esc_html__( 'Suivante', 'notice-archeomed' ) . '</button></p>';
		echo '</form>';
		$this->poser_le_script( $id );
	}

	/** Une illustration du formulaire : son aperçu, ses textes, et de quoi la retirer. */
	private function afficher_la_figure( $id, $figure, $tout ) {
		$k = (int) $figure['k'];
		echo '<div class="na-rel-figure' . ( null !== $figure['masquee'] ? ' na-rel-figure--masquee' : '' ) . '">';
		if ( ! empty( $figure['apercu'] ) ) {
			echo '<img src="' . esc_url( self::url_de_l_apercu( $id, $k ) ) . '" alt="' . esc_attr( Notice_Archeomed_Controles::une_ligne( strtok( (string) $figure['alt'], "\n" ) ) ) . '" loading="lazy">';
		} elseif ( null !== $figure['a_part'] ) {
			echo '<p class="na-rel-sans-apercu">' . esc_html__( 'Figure envoyée à part par l’auteur : pas d’aperçu.', 'notice-archeomed' ) . '</p>';
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
	 * renvoi, pour une notice qu'un autre tient ouverte, ou qui n'est plus
	 * au coordinateur. On peut y laisser une remarque à la rédaction, et le
	 * coordinateur d'un renvoi y retire le renvoi vers sa rubrique.
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
		if ( 'renvoi' === $droit['sorte'] ) {
			foreach ( array( 'renvoi_1', 'renvoi_2' ) as $champ ) {
				if ( isset( $notice['d'][ $champ ] ) && $notice['d'][ $champ ] === $droit['rubrique'] ) {
					echo '<p><label><input type="checkbox" name="retirer_renvoi[' . esc_attr( $champ ) . ']" value="1"> '
						. esc_html( sprintf( 'Retirer le renvoi vers « %s »', $droit['rubrique'] ) ) . '</label></p>';
				}
			}
		}
		echo '<p class="na-rel-champ"><label for="na-rel-remarque">' . esc_html__( 'Remarque à la rédaction', 'notice-archeomed' ) . '</label>'
			. '<textarea id="na-rel-remarque" name="remarque" rows="3"></textarea></p>'
			. '<p class="na-rel-boutons"><button type="submit" name="suite" value="rester" class="button button-primary">' . esc_html__( 'Enregistrer', 'notice-archeomed' ) . '</button> '
			. '<button type="submit" name="suite" value="suivante" class="button">' . esc_html__( 'Suivante', 'notice-archeomed' ) . '</button></p></form>';
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
					echo '<br><em>' . esc_html__( 'Remarque à la rédaction :', 'notice-archeomed' ) . '</em> ' . nl2br( esc_html( $ligne['remarque'] ) );
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
				echo ' <span class="description">' . esc_html__( '(déposé sur sDrive)', 'notice-archeomed' ) . '</span>';
			} elseif ( in_array( (int) $numero, $attendus, true ) ) {
				$raison = (string) get_post_meta( $id, '_na_relecture_raison', true );
				echo ' <span class="description">' . esc_html( '' !== $raison ? sprintf( '(pas encore sur sDrive : %s)', $raison ) : '(en attente de sDrive)' ) . '</span>';
			}
			echo '</li>';
		}
		echo '</ul></details>';
	}

	/** Les styles de l'écran. */
	private static function poser_les_styles() {
		echo '<style>'
			. '.na-rel-cadre{display:flex;gap:24px;align-items:flex-start;margin-top:12px}'
			. '.na-rel-sommaire{flex:0 0 300px;max-width:300px;position:sticky;top:42px;max-height:calc(100vh - 60px);overflow:auto;background:#fff;border:1px solid #c3c4c7;padding:10px 14px}'
			. '.na-rel-sommaire h2{font-size:1em;margin:.4em 0}.na-rel-sommaire h3{font-size:.92em;margin:1em 0 .3em}'
			. '.na-rel-sommaire ol{margin:0 0 0 1.4em}.na-rel-sommaire li{margin:0 0 .5em}'
			. '.na-rel-sommaire .na-rel-ouverte>a{font-weight:600}'
			. '.na-rel-renvois{border-top:1px solid #c3c4c7;padding-top:.8em;margin-top:1.2em}'
			. '.na-rel-notice{flex:1 1 auto;min-width:0;max-width:60em}'
			. '.na-rel-etat{display:inline-block;font-size:.85em;padding:0 .5em;border:1px solid #8c8f94;border-radius:2px;background:#f6f7f7;white-space:nowrap}'
			. '.na-rel-etat--relue,.na-rel-etat--accord{border-color:#007017;color:#007017}'
			. '.na-rel-etat--a_soumettre,.na-rel-etat--soumise{border-color:#825900;color:#825900}'
			. '.na-rel-etat--validee{background:#007017;border-color:#007017;color:#fff}'
			. '.na-rel-verrou{font-size:.85em;color:#b32d2e}'
			. '.na-rel-form fieldset{background:#fff;border:1px solid #c3c4c7;padding:8px 16px 12px;margin:0 0 16px}'
			. '.na-rel-form legend{font-weight:600;padding:0 .4em}'
			. '.na-rel-champ{margin:.6em 0}.na-rel-champ label,.na-rel-libelle{display:block;font-weight:600;margin-bottom:2px}'
			. '.na-rel-champ input[type=text],.na-rel-champ textarea,.na-rel-champ select,.na-rel-cherche{width:100%;max-width:40em}'
			. '.na-rel-cases label{display:inline-block;font-weight:400;margin:0 1.2em .3em 0}'
			. '.na-rel-personne{border-top:1px solid #dcdcde;padding-top:.6em;margin-top:.6em}.na-rel-personne:first-of-type{border-top:0}'
			. '.na-rel-termes{margin:.8em 0;position:relative}.na-rel-jetons{margin:.2em 0;padding:0}'
			. '.na-rel-jetons li{display:inline-block;margin:0 .4em .4em 0;padding:.1em .2em .1em .6em;background:#f0f0f1;border:1px solid #c3c4c7;border-radius:2px}'
			. '.na-rel-jetons button{border:0;background:none;cursor:pointer;color:#b32d2e;padding:0 .4em}'
			. '.na-rel-propositions{margin:0;padding:0;border:1px solid #8c8f94;background:#fff;max-width:40em;max-height:16em;overflow:auto}'
			. '.na-rel-propositions li{margin:0}.na-rel-propositions button{display:block;width:100%;text-align:left;border:0;background:none;padding:.4em .6em;cursor:pointer}'
			. '.na-rel-propositions button:hover,.na-rel-propositions button:focus{background:#2271b1;color:#fff}'
			. '#na-rel-editeur{background:#fff;min-height:16em;font-size:15px}.na-rel-form .ql-editor{min-height:16em}.na-pc{font-variant:small-caps}'
			. '.na-rel-figure{display:flex;gap:16px;border-top:1px solid #dcdcde;padding-top:12px;margin-top:12px}'
			. '.na-rel-figure img{flex:0 0 220px;width:220px;height:auto;align-self:flex-start;border:1px solid #c3c4c7}'
			. '.na-rel-sans-apercu{flex:0 0 220px;width:220px;color:#646970;font-style:italic}'
			. '.na-rel-figure-textes{flex:1 1 auto;min-width:0}.na-rel-figure--masquee{opacity:.7}'
			. '.na-rel-lecture{width:100%;height:70vh;border:1px solid #c3c4c7;background:#fff}'
			. '.na-rel-histoire{background:#fff;border:1px solid #c3c4c7;padding:8px 16px;margin:16px 0}.na-rel-histoire summary{font-weight:600;cursor:pointer}'
			. '.na-rel-journal li,.na-rel-versions li{margin:.6em 0}'
			. '@media (max-width:960px){.na-rel-cadre{display:block}.na-rel-sommaire{position:static;max-width:none;max-height:none;margin-bottom:16px}.na-rel-figure{display:block}}'
			. '</style>';
	}

	/**
	 * Le script du formulaire : l'éditeur du dépôt, les listes du thésaurus,
	 * le motif d'un retrait, et le verrou tenu tant que la page est ouverte.
	 */
	private function poser_le_script( $id ) {
		$base = plugin_dir_url( __FILE__ ) . 'assets/quill/';
		echo '<link rel="stylesheet" href="' . esc_url( $base . 'quill.snow.css' ) . '?ver=2.0.3">';
		echo '<script src="' . esc_url( $base . 'quill.js' ) . '?ver=2.0.3"></script>';
		$reglages = array(
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'notice'  => (int) $id,
			'jeton'   => wp_create_nonce( 'na_relecture_verrou_' . (int) $id ),
			'pactols' => 'https://pactols.frantiq.fr/',
			'maximum' => (int) Notice_Archeomed_Pactols::PACTOLS_FIELD_COUNT,
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
					formats: ['bold', 'italic', 'script', 'header'].concat(petitesCapitales ? ['petitescapitales'] : []),
					modules: {
						toolbar: [
							[{ 'header': 3 }, { 'header': 4 }],
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
					[['.ql-header[value="3"]', 'Intertitre, premier niveau', 'T1'], ['.ql-header[value="4"]', 'Intertitre, second niveau', 'T2'],
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
				quill.on('text-change', function (delta, avant, source) {
					if (source === 'user') { touche = true; }
				});
			}

			// Les termes du thésaurus : on en retire, et l'on en ajoute en
			// choisissant dans la liste que Pactols propose.
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
			[].forEach.call(form.querySelectorAll('.na-rel-termes'), function (bloc) {
				var type = bloc.getAttribute('data-type');
				var champ = bloc.querySelector('input[type="hidden"]');
				var jetons = bloc.querySelector('.na-rel-jetons');
				var cherche = bloc.querySelector('.na-rel-cherche');
				var liste = bloc.querySelector('.na-rel-propositions');
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
				function fermer() { liste.hidden = true; liste.textContent = ''; }
				var minuterie = null, tour = 0;
				cherche.addEventListener('input', function () {
					window.clearTimeout(minuterie);
					var q = cherche.value.trim();
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
									var deja = items.some(function (x) { return x.ark === item.ark; });
									if (deja) { dit.textContent = item.label + ' est déjà retenu.'; }
									else if (items.length >= R.maximum) { dit.textContent = R.maximum + ' termes au plus.'; }
									else { items.push(item); dessiner(); dit.textContent = item.label + ' ajouté.'; }
									cherche.value = '';
									fermer();
									cherche.focus();
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
				cherche.addEventListener('keydown', function (e) {
					if (e.key === 'Enter') { e.preventDefault(); }
					if (e.key === 'Escape') { fermer(); }
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
			form.addEventListener('submit', function () {
				envoi = true;
				if (quill && touche && cache) {
					cache.value = 'b64:' + btoa(unescape(encodeURIComponent(quill.root.innerHTML)));
				}
			});

			// Le verrou : renouvelé chaque minute, rendu quand on quitte la page.
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
			default:
				$lignes[] = 'Cette notice n’est pas de vos rubriques : rien n’est enregistré.';
		}
		$vers = $id;
		if ( $bon && isset( $_POST['suite'] ) && 'suivante' === $_POST['suite'] ) {
			self::liberer_le_verrou( $id, get_current_user_id() );
			$sommaire = self::sommaire( $rubrique );
			$suite    = array_merge( wp_list_pluck( $sommaire['notices'], 'id' ), wp_list_pluck( $sommaire['renvois'], 'id' ) );
			$rang     = array_search( $id, array_map( 'intval', $suite ), true );
			if ( false !== $rang && isset( $suite[ $rang + 1 ] ) ) {
				$vers = (int) $suite[ $rang + 1 ];
			} elseif ( false === $rang && ! empty( $suite ) ) {
				$vers = (int) $suite[0];
			} else {
				$lignes[] = 'C’était la dernière notice de la rubrique.';
			}
		}
		self::dire( $bon ? 'success' : 'error', $lignes );
		wp_safe_redirect( self::url( $rubrique, null === self::droit_sur( $vers ) ? 0 : $vers ) );
		exit;
	}

	/**
	 * Le bouton « Soumettre à l'auteur » de la rédaction : le courriel part
	 * avec le Word à suivi de modifications, et la notice passe à « soumise
	 * à l'auteur ». Un courriel refusé laisse tout en l'état, et se dit.
	 */
	public function soumettre() {
		$id = isset( $_POST['notice'] ) ? absint( $_POST['notice'] ) : 0;
		if ( ! self::peut_tout() ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		check_admin_referer( 'na_relecture_soumettre_' . $id );
		$plugin = self::plugin();
		$droit  = self::droit_sur( $id );
		if ( null === $plugin || null === $droit ) {
			wp_die( esc_html__( 'Notice inconnue.', 'notice-archeomed' ) );
		}
		$refus = self::soumettre_la_notice( $id );
		self::dire( '' === $refus ? 'success' : 'error', '' === $refus
			? 'Le courriel est parti vers l’auteur, avec le Word à suivi de modifications.'
			: 'Rien n’est parti : ' . $refus . '. La notice reste « à soumettre à l’auteur ».' );
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
		$refus = $plugin->soumettre_a_l_auteur( $id );
		if ( '' !== $refus ) {
			return $refus;
		}
		$etat = self::etat_de( $id );
		update_post_meta( (int) $id, '_na_relecture_etat', 'soumise' );
		self::noter_au_journal( $id, $utilisateur, array( 'courriel à l’auteur, avec le Word à suivi de modifications',
			'état : ' . self::ETATS[ $etat ] . ' → ' . self::ETATS['soumise'] ), $etat, 'soumise' );
		return '';
	}

	/** Rend le Word de relecture d'une version à qui peut lire la notice. */
	public function rendre_le_word() {
		$id = isset( $_GET['notice'] ) ? absint( $_GET['notice'] ) : 0;
		check_admin_referer( 'na_relecture_word_' . $id );
		$droit  = self::droit_sur( $id );
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
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( self::nom_du_word( $version, null !== $gardee ? $gardee['par'] : '' ) ) . '"' );
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
		if ( null === self::droit_sur( $id ) || null === $plugin ) {
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
		if ( null === self::droit_sur( $id ) || null === $plugin ) {
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
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="na_relecture_reglages">';
		wp_nonce_field( 'na_relecture_reglages' );
		echo '<h2>' . esc_html__( 'Campagne', 'notice-archeomed' ) . '</h2>'
			. '<p><label><input type="checkbox" name="close" value="1"' . checked( $reglages['close'], true, false ) . '> '
			. esc_html__( 'La campagne est close', 'notice-archeomed' ) . '</label></p>'
			. '<p class="description" style="max-width:46em">' . esc_html__( 'Cochée, la relecture s’ouvre aux coordinateurs et à la rédaction, et les liens de correction envoyés aux auteurs ne corrigent plus : qui les suit lit que la campagne est close. Le dépôt de nouvelles notices, lui, reste ouvert tant que la page du formulaire est publiée.', 'notice-archeomed' ) . '</p>';
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
		echo '<p class="description" style="max-width:46em">' . esc_html__( 'La rédaction relit toutes les rubriques : les administrateurs du site, et les comptes du rôle « Rédaction de la Chronique (relecture) ».', 'notice-archeomed' ) . '</p>';
		submit_button( __( 'Enregistrer', 'notice-archeomed' ) );
		echo '</form>';
	}

	/** Enregistre l'onglet « Relecture ». */
	public function recevoir_les_reglages() {
		if ( ! current_user_can( 'manage_options' ) ) {
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
