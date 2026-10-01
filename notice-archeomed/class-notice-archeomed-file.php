<?php
/**
 * La file d'attente des notices : accepter vite, expédier ensuite.
 *
 * Le formulaire faisait tout dans la requête de l'auteur — vérification
 * Turnstile, fabrication du DOCX, puis un courriel à la rédaction portant
 * jusqu'à vingt méga-octets de pièces jointes, et trois accusés de réception.
 * Un processus PHP restait donc pris une trentaine de secondes par notice,
 * parfois davantage.
 *
 * Un hébergement mutualisé en compte cinq ou dix. Dix dépôts simultanés, et
 * le site entier cesse de répondre — y compris pour ceux qui lisent la revue.
 * Pire : quand la durée d'exécution est dépassée, PHP tue la requête au
 * milieu de l'envoi, et la notice n'est ni partie ni conservée. Elle est
 * perdue sans que personne le sache. Sur une campagne où l'on sollicite
 * plusieurs milliers de personnes, c'est exactement ce qu'il ne faut pas.
 *
 * D'où ce dépôt en deux temps. La requête de l'auteur ne fait plus que ce
 * qu'elle doit : vérifier, ranger les fichiers, fabriquer le document,
 * inscrire la notice. Elle rend la main en une fraction de seconde. Les
 * courriels partent après, par petits paquets, sous le planificateur de
 * WordPress — et une notice qui n'a pas pu partir reste inscrite, visible
 * dans l'administration, et se represente d'elle-même.
 *
 * @package Notice_Archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_File {

	/** Le type d'objet où vivent les notices reçues. */
	const CPT = 'na_notice';

	/** L'action déclenchée pour expédier une notice donnée. */
	const HOOK_UNE = 'na_expedier_notice';

	/** La relance périodique, qui rattrape ce qui serait resté en plan. */
	const HOOK_RELANCE = 'na_relancer_les_notices';

	/** Le récapitulatif du jour, envoyé à la rédaction. */
	const HOOK_RECAP = 'na_recapituler_les_notices';

	/**
	 * Combien de notices une relance expédie au plus.
	 *
	 * Une relance est une requête comme une autre : lui faire vider une file
	 * de cent notices reviendrait à recréer le blocage qu'on supprime.
	 */
	const PAR_PASSAGE = 5;

	/** Au-delà, on cesse de represser et l'on demande une main humaine. */
	const ESSAIS_MAX = 5;

	/**
	 * Au bout de combien de temps une expédition commencée est tenue pour
	 * abandonnée. Un processus tué en plein envoi laisserait sinon sa notice
	 * marquée « en cours » pour toujours, et personne ne la reprendrait.
	 */
	const ABANDON = 600;

	public function __construct() {
		add_action( 'init', array( $this, 'declarer_le_type' ) );
		add_action( self::HOOK_RELANCE, array( $this, 'relancer' ) );
		add_action( self::HOOK_RECAP, array( $this, 'recapituler' ) );
		// La cause avant ses effets : sans adresse, les bandeaux d'échec et de
		// retard poussaient à relancer ce qui ne pouvait pas partir.
		add_action( 'admin_notices', array( $this, 'signaler_l_adresse_manquante' ), 5 );
		add_action( 'admin_notices', array( $this, 'signaler_les_echecs' ) );
		add_action( 'admin_notices', array( $this, 'signaler_les_retards' ) );
		add_action( 'admin_notices', array( $this, 'rendre_compte' ) );
		add_action( 'admin_head', array( $this, 'poser_les_styles' ) );
		add_action( 'admin_footer', array( $this, 'poser_le_script_des_dossiers' ) );
		add_action( 'load-edit.php', array( $this, 'preparer_la_liste' ) );
		add_filter( 'removable_query_args', array( $this, 'arguments_a_effacer' ) );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( $this, 'colonnes' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( $this, 'colonne' ), 10, 2 );
		add_filter( 'manage_edit-' . self::CPT . '_sortable_columns', array( $this, 'colonnes_triables' ) );
		add_action( 'restrict_manage_posts', array( $this, 'filtre_par_rubrique' ) );
		// Les vues par état d'abord, le bloc des fascicules ensuite : il se
		// pose en tête de ce que les vues ont laissé.
		add_filter( 'views_edit-' . self::CPT, array( $this, 'vues_par_etat' ), 5 );
		add_filter( 'views_edit-' . self::CPT, array( $this, 'liens_des_fascicules' ) );
		add_action( 'pre_get_posts', array( $this, 'appliquer_le_filtre' ) );
		add_filter( 'posts_search', array( $this, 'etendre_la_recherche' ), 10, 2 );
		// Les chemins par lesquels une notice quittait la file sans qu'on le
		// veuille : modification rapide, statut changé, restauration.
		add_filter( 'post_row_actions', array( $this, 'actions_de_ligne' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . self::CPT, array( $this, 'actions_groupees' ) );
		add_filter( 'display_post_states', array( $this, 'etats_affiches' ), 10, 2 );
		add_filter( 'wp_untrash_post_status', array( $this, 'statut_a_la_restauration' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( $this, 'garder_privee' ), 10, 2 );
		// La fiche d'une notice : ce qu'on vient y lire, et rien d'autre.
		add_action( 'add_meta_boxes_' . self::CPT, array( $this, 'poser_la_fiche' ) );
		add_action( 'do_meta_boxes', array( $this, 'ecarter_les_intrus' ), 99 );
		add_action( 'edit_form_after_title', array( $this, 'afficher_l_entete' ) );
		add_filter( 'postbox_classes_' . self::CPT . '_na_depannage', array( $this, 'fermer_le_depannage' ) );
		add_action( 'admin_post_na_expedier_maintenant', array( $this, 'expedier_maintenant' ) );
		add_action( 'admin_post_na_reessayer', array( $this, 'reessayer' ) );
		add_action( 'admin_post_na_diagnostic', array( $this, 'diagnostiquer' ) );
	}

	/** Ce que la version en cours attend d'avoir écrit dans chaque notice. */
	const ETAT_DES_DONNEES = 3;

	/**
	 * Donne aux notices déjà reçues ce que les versions suivantes leur
	 * demandent.
	 *
	 * Le classement, la rubrique et le responsable sont recopiés à part pour
	 * que la liste puisse trier — une requête d'administration ne sait pas
	 * fouiller un tableau sérialisé. Les notices d'avant ne les portent pas :
	 * plutôt que de les laisser en arrière, on les rattrape ici, par paquets,
	 * et l'on note où l'on en est pour ne pas recommencer à chaque page.
	 */
	public function rattraper_les_anciennes() {
		if ( (int) get_option( 'na_etat_des_donnees' ) >= self::ETAT_DES_DONNEES ) {
			return;
		}
		// Une notice restaurée de la corbeille revenait en brouillon, et une
		// modification rapide pouvait la publier : dans les deux cas elle
		// quittait la file et les fascicules, qui ne lisent que les notices
		// privées. Le filtre de statut l'empêche désormais ; celles qui ont
		// déjà glissé reviennent ici.
		$egarees = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => array( 'draft', 'pending', 'publish', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $egarees as $id ) {
			wp_update_post( array( 'ID' => (int) $id, 'post_status' => 'private' ) );
		}
		$restantes = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => '_na_classement', 'compare' => 'NOT EXISTS' ),
				),
			)
		);
		foreach ( $restantes as $id ) {
			$donnees = get_post_meta( $id, '_na_donnees', true );
			if ( ! is_array( $donnees ) ) {
				// Sans saisie, on ne peut rien recomposer : une clef vide vaut
				// mieux qu'une notice qu'on reprend à chaque passage.
				update_post_meta( $id, '_na_classement', '99|9|' );
				continue;
			}
			$this->rattraper( $id, '_na_rubrique', array( 'rubrique_principale' ) );
			$this->rattraper( $id, '_na_responsable', array( 'resp_prenom', 'resp_nom' ) );
			$this->rattraper( $id, '_na_courriel', array( 'resp_email' ) );
			$this->poser_le_classement( $id, $donnees );
			if ( '' === (string) get_post_meta( $id, '_na_classement', true ) ) {
				update_post_meta( $id, '_na_classement', '99|9|' );
			}
		}
		if ( count( $restantes ) < 50 ) {
			update_option( 'na_etat_des_donnees', self::ETAT_DES_DONNEES );
		}
	}

	/**
	 * Repose les rendez-vous manquants.
	 *
	 * Une mise à jour par téléversement ne repasse pas toujours par
	 * l'activation : sans ce rattrapage, la relance et le récapitulatif
	 * disparaissaient au premier remplacement de l'extension, et personne ne
	 * s'en apercevait avant que la file ne s'allonge.
	 */
	public function verifier_les_rendez_vous() {
		if ( ! wp_next_scheduled( self::HOOK_RELANCE ) || ! wp_next_scheduled( self::HOOK_RECAP ) ) {
			self::activer();
		}
	}

	/**
	 * L'icône du menu : les initiales de la revue, dans l'italique de son
	 * titre, d'une seule couleur que WordPress accorde au thème de
	 * l'administration. Si le fichier manque, l'icône de document d'avant.
	 */
	private static function icone_du_menu() {
		$svg = @file_get_contents( plugin_dir_path( __FILE__ ) . 'assets/icone-menu.svg' );
		return ( false !== $svg && '' !== $svg )
			? 'data:image/svg+xml;base64,' . base64_encode( $svg )
			: 'dashicons-media-document';
	}

	/**
	 * Les notices reçues ne sont pas du contenu public : elles ne s'affichent
	 * nulle part, ne s'indexent pas, et ne se voient que de l'administration.
	 */
	public function declarer_le_type() {
		$this->verifier_les_rendez_vous();
		if ( is_admin() ) {
			$this->rattraper_les_anciennes();
		}
		register_post_type(
			self::CPT,
			array(
				// Chaque libellé que WordPress aurait tiré de « Article » : une
				// secrétaire lisait « Modifier l'article » au-dessus d'une notice
				// qu'on ne modifie pas, et « Aucun article trouvé » devant une
				// liste simplement vide.
				'labels'              => array(
					'name'                  => __( 'Notices reçues', 'notice-archeomed' ),
					'singular_name'         => __( 'Notice', 'notice-archeomed' ),
					'menu_name'             => __( 'Chronique', 'notice-archeomed' ),
					'all_items'             => __( 'Notices reçues', 'notice-archeomed' ),
					'edit_item'             => __( 'Fiche de la notice', 'notice-archeomed' ),
					'search_items'          => __( 'Rechercher une notice', 'notice-archeomed' ),
					'not_found'             => __( 'Aucune notice ne correspond.', 'notice-archeomed' ),
					'not_found_in_trash'    => __( 'La corbeille est vide.', 'notice-archeomed' ),
					'items_list'            => __( 'Liste des notices', 'notice-archeomed' ),
					'items_list_navigation' => __( 'Pagination de la liste des notices', 'notice-archeomed' ),
					'filter_items_list'     => __( 'Filtrer la liste des notices', 'notice-archeomed' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => self::icone_du_menu(),
				'capability_type'     => 'post',
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
				// Rien à rédiger : le titre modifiable invitait à retoucher une
				// notice dont la saisie, seule, fait foi. « false » et non un
				// tableau vide, que WordPress lirait comme « titre et texte ».
				'supports'            => false,
				'has_archive'         => false,
				'rewrite'             => false,
			)
		);
	}

	/**
	 * Inscrit une notice et rend son identifiant.
	 *
	 * Tout est en réserve avant qu'un seul courriel ne parte : c'est ce qui
	 * fait qu'une panne d'envoi, une coupure ou un dépassement de durée ne
	 * coûtent plus une notice.
	 */
	public function deposer( $donnees, $notice_html, $fichiers, $document, $reference ) {
		// Le titre de la liste porte le lieu-dit : trois « Argentan (Orne) —
		// 2025 » ne se distinguaient pas. Les notices déjà reçues gardent le
		// leur.
		$plugin = $this->plugin();
		$titre  = ( null !== $plugin ) ? $plugin->titre_de_liste( $donnees )
			: trim( $donnees['commune'] . ' (' . $donnees['departement'] . ') — ' . $donnees['annee'] );
		$id    = wp_insert_post(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'private',
				'post_title'  => '' !== $titre ? $titre : __( 'Notice sans commune', 'notice-archeomed' ),
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}
		update_post_meta( $id, '_na_donnees', wp_slash( $donnees ) );
		update_post_meta( $id, '_na_notice', wp_slash( $notice_html ) );
		update_post_meta( $id, '_na_fichiers', $fichiers );
		// Le document stylé, suivi à part : c'est celui qui repart aux auteurs.
		update_post_meta( $id, '_na_document', $document );
		update_post_meta( $id, '_na_reference', $reference );
		// La référence que ce dépôt corrige : c'est ici, dans la liste, qu'on
		// supprimera l'ancienne — le bandeau du courriel dit de le faire, la
		// fiche dit laquelle.
		update_post_meta( $id, '_na_remplace',
			isset( $donnees['remplace'] ) ? (string) $donnees['remplace'] : '' );
		// Ce sur quoi la liste trie et filtre. Recopié à part plutôt que lu
		// dans le tableau des données : une requête d'administration ne sait
		// pas fouiller un tableau sérialisé.
		update_post_meta( $id, '_na_rubrique',
			isset( $donnees['rubrique_principale'] ) ? $donnees['rubrique_principale'] : '' );
		update_post_meta( $id, '_na_responsable',
			trim( ( isset( $donnees['resp_prenom'] ) ? $donnees['resp_prenom'] : '' )
				. ' ' . ( isset( $donnees['resp_nom'] ) ? $donnees['resp_nom'] : '' ) ) );
		update_post_meta( $id, '_na_courriel',
			isset( $donnees['resp_email'] ) ? $donnees['resp_email'] : '' );
		$this->poser_le_classement( $id, $donnees );
		update_post_meta( $id, '_na_etat', 'en_attente' );
		update_post_meta( $id, '_na_essais', 0 );
		return (int) $id;
	}

	/**
	 * Demande l'expédition dès que possible.
	 *
	 * Le planificateur de WordPress se déclenche à la requête suivante : sur
	 * un site consulté, c'est l'affaire de quelques secondes.
	 */
	public function programmer( $id ) {
		if ( ! wp_next_scheduled( self::HOOK_UNE, array( (int) $id ) ) ) {
			wp_schedule_single_event( time(), self::HOOK_UNE, array( (int) $id ) );
		}
	}

	/**
	 * Les notices qui attendent encore, de la plus ancienne à la plus récente.
	 */
	public function en_attente( $combien = 20 ) {
		return get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'private',
				'posts_per_page' => (int) $combien,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_na_etat',
						'value'   => array( 'en_attente', 'en_cours' ),
						'compare' => 'IN',
					),
				),
			)
		);
	}

	/**
	 * Le filet : ce que le planificateur aurait manqué repart ici.
	 */
	public function relancer() {
		foreach ( $this->en_attente( self::PAR_PASSAGE ) as $id ) {
			do_action( self::HOOK_UNE, (int) $id );
		}
	}

	/**
	 * Remet une notice en échec dans la file.
	 *
	 * Après cinq refus, une notice restait bloquée pour toujours : la relance
	 * générale ne reprend que celles en attente. Or l'échec vient le plus
	 * souvent de ce qui se répare — une adresse oubliée, un serveur de
	 * courriel indisponible une heure. Une fois la cause levée, il faut
	 * pouvoir redemander l'envoi sans que l'auteur redépose sa notice.
	 *
	 * Le compteur repart à zéro : ce sont cinq nouvelles chances, non la
	 * sixième d'une série perdue.
	 */
	public function reessayer() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		check_admin_referer( 'na_reessayer_' . $id );
		if ( ! $id || self::CPT !== get_post_type( $id ) ) {
			wp_die( esc_html__( 'Notice inconnue.', 'notice-archeomed' ) );
		}
		$retour = get_edit_post_link( $id, '' );
		$etat   = (string) get_post_meta( $id, '_na_etat', true );
		// Un lien resté ouvert dans un autre onglet ne renvoie pas une notice
		// déjà partie : la rédaction la recevrait deux fois.
		if ( 'envoyee' === $etat ) {
			wp_safe_redirect( add_query_arg( 'na_relancee', 'partie', $retour ) );
			exit;
		}
		// Remettre « en attente » une notice qu'un autre passage est en train
		// d'envoyer, c'était la lui reprendre des mains, et l'envoyer deux
		// fois. On la laisse finir.
		if ( 'en_cours' === $etat
			&& (int) get_post_meta( $id, '_na_prise', true ) > time() - self::ABANDON ) {
			wp_safe_redirect( add_query_arg( 'na_relancee', 'occupee', $retour ) );
			exit;
		}
		update_post_meta( $id, '_na_essais', 0 );
		update_post_meta( $id, '_na_etat', 'en_attente' );
		delete_post_meta( $id, '_na_erreur' );
		// On envoie sur-le-champ plutôt que d'inscrire un rendez-vous. Le
		// planificateur de WordPress attend la requête suivante, et une seule
		// notice en attente ne déclenche rien. Qui presse « Relancer » veut
		// savoir tout de suite, et c'est à ce moment-là que la raison d'un
		// refus a le plus de valeur.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 );
		}
		$plugin = $this->plugin();
		$issue  = '';
		if ( null !== $plugin ) {
			$issue = (string) $plugin->expedier_de_la_file( $id );
		} else {
			do_action( self::HOOK_UNE, $id );
		}
		// Tout ce qui n'était pas « partie » se disait « refusée » : une notice
		// qui attendait ses versions allégées, ou qu'un autre passage tenait,
		// passait pour rejetée par le serveur, et l'on cherchait une panne
		// qui n'existait pas. L'état et le compteur, relus après l'appel,
		// disent ce qui s'est vraiment passé.
		$etat   = (string) get_post_meta( $id, '_na_etat', true );
		$essais = (int) get_post_meta( $id, '_na_essais', true );
		if ( 'envoyee' === $etat ) {
			$issue = 'partie';
		} elseif ( $essais > 0 || 'echec' === $etat ) {
			$issue = 'refusee';
		} elseif ( 'en_preparation' !== $issue ) {
			$issue = 'occupee';
		}
		wp_safe_redirect( add_query_arg( 'na_relancee', $issue, $retour ) );
		exit;
	}

	/**
	 * Refait l'envoi de cette notice pièce par pièce, vers l'adresse de
	 * l'utilisateur en cours, et garde le résultat pour l'afficher.
	 */
	public function diagnostiquer() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		check_admin_referer( 'na_diagnostic_' . $id );
		if ( ! $id || self::CPT !== get_post_type( $id ) ) {
			wp_die( esc_html__( 'Notice inconnue.', 'notice-archeomed' ) );
		}
		$plugin = $this->plugin();
		$lignes = ( null !== $plugin )
			? $plugin->diagnostiquer_la_notice( $id, wp_get_current_user()->user_email )
			: array();
		// Le résultat vit le temps d'un aller-retour : il n'a pas à encombrer
		// la notice, et il sera faux dès qu'on aura corrigé la cause.
		set_transient( 'na_diagnostic_' . $id, $lignes, 300 );
		wp_safe_redirect( add_query_arg( 'na_diagnostic', '1', get_edit_post_link( $id, '' ) ) );
		exit;
	}

	/**
	 * Le bouton « Les envoyer maintenant » du bandeau de retard, pour le jour
	 * où le planificateur de WordPress est désactivé sur l'hébergement.
	 *
	 * Il ramenait à la liste sans rien dire : on ne savait pas si le clic
	 * avait servi, ni s'il fallait recommencer. Le retour compte désormais
	 * ce qui est parti et ce qui reste.
	 */
	public function expedier_maintenant() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		check_admin_referer( 'na_expedier_maintenant' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 );
		}
		$ids     = $this->en_attente( self::PAR_PASSAGE );
		$parties = 0;
		$refus   = 0;
		foreach ( $ids as $id ) {
			$essais = (int) get_post_meta( $id, '_na_essais', true );
			do_action( self::HOOK_UNE, (int) $id );
			$etat = (string) get_post_meta( $id, '_na_etat', true );
			if ( 'envoyee' === $etat ) {
				++$parties;
			} elseif ( 'echec' === $etat || (int) get_post_meta( $id, '_na_essais', true ) > $essais ) {
				++$refus;
			}
		}
		$comptes = $this->compter_les_etats( true );
		wp_safe_redirect( add_query_arg(
			array(
				'post_type'  => self::CPT,
				'na_parties' => $parties,
				'na_refus'   => $refus,
				'na_restent' => $comptes['file'],
			),
			admin_url( 'edit.php' )
		) );
		exit;
	}

	/**
	 * Réserve une notice avant de l'expédier, ou dit qu'un autre s'en charge.
	 *
	 * Deux passages du planificateur peuvent se croiser — l'événement propre à
	 * la notice et la relance périodique. Sans cette prise, la rédaction
	 * recevait la même notice deux fois, et l'auteur deux accusés.
	 */
	public function prendre( $id ) {
		global $wpdb;
		$id = (int) $id;
		// La lecture puis l'écriture laissaient une fenêtre : deux passages
		// lisaient « en_attente » l'un après l'autre avant que le premier
		// n'écrive, et la notice partait deux fois — la seconde sans son
		// document, que la première venait d'effacer. On le voyait sur un
		// site d'essai lent, une relance à la main croisant le planificateur.
		//
		// La prise est donc une seule écriture conditionnelle : la base ne
		// change la ligne que si elle porte encore l'ancienne valeur, et un
		// seul des passages la trouve ainsi.
		wp_cache_delete( $id, 'post_meta' );
		$etat = (string) get_post_meta( $id, '_na_etat', true );
		if ( 'en_attente' === $etat ) {
			$pris = $wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = 'en_cours' WHERE post_id = %d AND meta_key = '_na_etat' AND meta_value = 'en_attente'",
				$id ) );
		} elseif ( 'en_cours' === $etat ) {
			// Une prise abandonnée — la requête qui la tenait est morte en
			// route — se reprend, mais par la même écriture conditionnelle,
			// sur l'heure de la prise : deux repreneurs ne la reprennent pas
			// tous les deux.
			$depuis = (string) get_post_meta( $id, '_na_prise', true );
			if ( (int) $depuis > time() - self::ABANDON ) {
				return false;   // quelqu'un s'en occupe à l'instant
			}
			$pris = $wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %d AND meta_key = '_na_prise' AND meta_value = %s",
				(string) time(), $id, $depuis ) );
		} else {
			return false;       // déjà partie, ou renoncée
		}
		wp_cache_delete( $id, 'post_meta' );
		if ( 1 !== (int) $pris ) {
			return false;       // un autre passage l'a prise juste avant
		}
		update_post_meta( $id, '_na_prise', time() );
		return true;
	}

	/**
	 * La fiche d'une notice : on vient la lire, pas la rédiger.
	 *
	 * L'écran d'édition ne portait que le titre — le reste vit en métadonnées.
	 * Il restait donc vide, et les extensions tierces y déposaient leurs
	 * propres encarts, dont un « Product Review » sans le moindre rapport.
	 *
	 * Le dépannage a son propre encart, fermé : le diagnostic, les tentatives
	 * et la réponse brute du serveur voisinaient avec l'état de la notice, et
	 * la secrétaire qui venait savoir si elle était partie lisait d'abord une
	 * erreur PHPMailer.
	 */
	public function poser_la_fiche() {
		add_meta_box( 'na_fiche', __( 'La notice', 'notice-archeomed' ),
			array( $this, 'afficher_la_notice' ), self::CPT, 'normal', 'high' );
		add_meta_box( 'na_suivi', __( 'Suivi', 'notice-archeomed' ),
			array( $this, 'afficher_le_suivi' ), self::CPT, 'side', 'high' );
		add_meta_box( 'na_depannage', __( 'Dépannage', 'notice-archeomed' ),
			array( $this, 'afficher_le_depannage' ), self::CPT, 'normal', 'low' );
	}

	/**
	 * Le dépannage reste fermé, sauf au retour d'un diagnostic : c'est là que
	 * ses résultats s'affichent, et les chercher dans un encart replié
	 * laissait croire qu'il n'avait rien donné.
	 */
	public function fermer_le_depannage( $classes ) {
		$classes = (array) $classes;
		if ( isset( $_GET['na_diagnostic'] ) ) {
			return array_diff( $classes, array( 'closed' ) );
		}
		if ( ! in_array( 'closed', $classes, true ) ) {
			$classes[] = 'closed';
		}
		return $classes;
	}

	public function afficher_la_notice( $post ) {
		$html = (string) get_post_meta( $post->ID, '_na_notice', true );
		if ( '' === trim( $html ) ) {
			echo '<p>' . esc_html__( 'Cette notice ne porte aucun contenu lisible.',
				'notice-archeomed' ) . '</p>';
			return;
		}
		// Le même contenu que le courriel reçu par la rédaction : c'est ce
		// qu'on veut relire, et il a déjà été échappé à la construction.
		echo '<div style="font-size:14px;line-height:1.6;max-width:52em">'
			. wp_kses_post( $html ) . '</div>';
	}

	/** L'adresse qui relance l'envoi d'une notice. */
	private function url_de_relance( $id ) {
		return wp_nonce_url(
			add_query_arg( array( 'action' => 'na_reessayer', 'post' => (int) $id ),
				admin_url( 'admin-post.php' ) ),
			'na_reessayer_' . (int) $id
		);
	}

	/** L'adresse qui refabrique et télécharge le document Word d'une notice. */
	private function url_du_document( $id ) {
		return wp_nonce_url(
			add_query_arg( array( 'action' => 'na_document', 'post' => (int) $id ),
				admin_url( 'admin-post.php' ) ),
			'na_document_' . (int) $id
		);
	}

	/** Une notice dont la saisie est gardée peut refabriquer son document. */
	private function a_un_document( $id ) {
		$d = get_post_meta( (int) $id, '_na_donnees', true );
		return is_array( $d ) && ! empty( $d );
	}

	/**
	 * La tête de la fiche : de quoi il s'agit, où en est l'envoi, et le seul
	 * geste qui compte à cet instant.
	 *
	 * Deux boutons bleus se disputaient l'encart de suivi — « Télécharger le
	 * document » et « Expédier maintenant » — et il fallait lire l'état brut,
	 * « en_attente », pour savoir lequel presser. Il n'y en a plus qu'un,
	 * choisi d'après l'état : relancer ce qui n'est pas parti, télécharger ce
	 * qui l'est.
	 */
	public function afficher_l_entete( $post ) {
		if ( ! $post || self::CPT !== $post->post_type ) {
			return;
		}
		$id   = (int) $post->ID;
		$etat = (string) get_post_meta( $id, '_na_etat', true );
		echo '<h2 class="na-titre-fiche">' . esc_html( get_the_title( $post ) ) . '</h2>';

		$types = array(
			'envoyee'  => 'success',
			'echec'    => 'error',
			'en_cours' => 'info',
		);
		$type = isset( $types[ $etat ] ) ? $types[ $etat ] : 'info';
		if ( 'en_attente' === $etat && (int) get_post_meta( $id, '_na_essais', true ) > 0 ) {
			$type = 'warning';
		}
		echo '<div class="notice inline notice-' . esc_attr( $type ) . ' na-bandeau"><p>'
			. $this->libelle_de_l_etat( $id ) . '</p>';
		if ( 'envoyee' === $etat ) {
			if ( $this->a_un_document( $id ) ) {
				echo '<p><a class="button button-primary" href="' . esc_url( $this->url_du_document( $id ) ) . '">'
					. esc_html__( 'Télécharger la notice (Word)', 'notice-archeomed' ) . '</a></p>';
			}
		} elseif ( 'en_cours' === $etat ) {
			echo '<p>' . esc_html__( 'Un envoi est en train de se faire. Rechargez la page dans une minute pour en connaître l’issue.', 'notice-archeomed' ) . '</p>';
		} else {
			// Une notice déjà refusée dit pourquoi dès la tête de fiche : la
			// raison ne figurait qu'au dépannage, fermé, et l'on relançait
			// sans avoir rien corrigé.
			$erreur = (string) get_post_meta( $id, '_na_erreur', true );
			$refus  = (int) get_post_meta( $id, '_na_essais', true ) > 0;
			if ( 'en_attente' === $etat && $refus && '' !== $erreur ) {
				echo '<p>' . esc_html( self::expliquer_l_erreur( $erreur ) ) . '</p>';
			}
			// Sans destinataire, la relance échoue à coup sûr : on offrait le
			// bouton bleu, on cliquait, et l'échec suivant ne disait pas qu'il
			// venait de là. Le seul geste utile est alors de renseigner
			// l'adresse.
			if ( ! self::quelqu_un_recoit_les_notices() ) {
				echo '<p><a class="button button-primary" href="' . esc_url( Notice_Archeomed_Settings::url( 'destinataires' ) ) . '">'
					. esc_html__( 'Renseigner d’abord l’adresse de la rédaction', 'notice-archeomed' ) . '</a></p>';
				echo '<p class="description">'
					. esc_html__( 'La relance échouerait tant que personne ne reçoit les notices. Une fois l’adresse enregistrée, revenez ici pour l’envoyer.', 'notice-archeomed' )
					. '</p></div>';
				$this->afficher_les_liens_de_correction( $id );
				return;
			}
			// Le bouton paraît pour tout ce qui n'est pas parti — en échec
			// comme en attente. Réservé au seul échec, il disparaissait
			// précisément quand on en avait besoin : une notice en attente ne
			// repart pas d'elle-même si le planificateur tarde.
			echo '<p><a class="button button-primary" href="' . esc_url( $this->url_de_relance( $id ) ) . '">'
				. esc_html__( 'Relancer l’envoi', 'notice-archeomed' ) . '</a></p>';
			echo '<p class="description">' . esc_html( ( 'echec' === $etat || $refus )
				? __( 'L’envoi part aussitôt, et son issue s’affiche en haut de cette page. Corrigez d’abord ce que l’explication ci-dessus signale : le compteur d’essais repart à zéro.', 'notice-archeomed' )
				: __( 'La notice partira d’elle-même dans les minutes qui viennent. Ce bouton l’envoie tout de suite.', 'notice-archeomed' ) )
				. '</p>';
		}
		echo '</div>';
		$this->afficher_les_liens_de_correction( $id );
	}

	/**
	 * La correction et ce qu'elle corrige : la notice corrigée ne paraît plus
	 * au fascicule ni au dossier, et c'est ici qu'on la retrouve pour la
	 * mettre à la corbeille.
	 */
	private function afficher_les_liens_de_correction( $id ) {
		$remplace = strtoupper( trim( (string) get_post_meta( $id, '_na_remplace', true ) ) );
		if ( '' !== $remplace ) {
			$chercher = add_query_arg( array( 'post_type' => self::CPT, 's' => $remplace ),
				admin_url( 'edit.php' ) );
			echo '<div class="notice inline notice-info"><p>'
				. esc_html( sprintf(
					/* translators: %s : la référence de la notice corrigée. */
					__( 'Cette notice corrige la notice %s.', 'notice-archeomed' ), $remplace ) )
				. ' <a href="' . esc_url( $chercher ) . '">'
				. esc_html__( 'Retrouver la notice corrigée', 'notice-archeomed' ) . '</a></p></div>';
		}
		$reference = strtoupper( trim( (string) get_post_meta( $id, '_na_reference', true ) ) );
		if ( '' !== $reference ) {
			$correctrices = get_posts( array(
				'post_type'      => self::CPT,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => '_na_remplace', 'value' => $reference ) ),
			) );
			if ( ! empty( $correctrices ) ) {
				echo '<div class="notice inline notice-warning"><p>'
					. esc_html__( 'Une correction remplace cette notice : elle ne paraît plus dans les fascicules ni dans les dossiers Métopes.', 'notice-archeomed' )
					. ' <a href="' . esc_url( get_edit_post_link( (int) $correctrices[0], '' ) ) . '">'
					. esc_html__( 'Ouvrir la correction', 'notice-archeomed' ) . '</a></p></div>';
			}
		}
	}

	public function afficher_le_suivi( $post ) {
		$id     = (int) $post->ID;
		$envoye = (string) get_post_meta( $id, '_na_envoyee_le', true );
		$lignes = array(
			__( 'Envoi', 'notice-archeomed' )       => $this->libelle_de_l_etat( $id, false, true ),
			__( 'Référence', 'notice-archeomed' )   => esc_html( (string) get_post_meta( $id, '_na_reference', true ) ),
			__( 'Rubrique', 'notice-archeomed' )    => esc_html( Notice_Archeomed_Pactols::rubrique_actuelle( (string) get_post_meta( $id, '_na_rubrique', true ) ) ),
			__( 'Responsable', 'notice-archeomed' ) => esc_html( (string) get_post_meta( $id, '_na_responsable', true ) ),
			__( 'Courriel', 'notice-archeomed' )    => esc_html( (string) get_post_meta( $id, '_na_courriel', true ) ),
			__( 'Reçue le', 'notice-archeomed' )    => esc_html( self::date_lisible( $post->post_date ) ),
			__( 'Envoyée le', 'notice-archeomed' )  => esc_html( self::date_lisible( $envoye ) ),
		);
		echo '<table class="widefat striped"><tbody>';
		foreach ( $lignes as $intitule => $valeur ) {
			if ( '' === trim( wp_strip_all_tags( $valeur ) ) ) {
				continue;
			}
			echo '<tr><th scope="row">' . esc_html( $intitule ) . '</th><td>'
				. $valeur . '</td></tr>';
		}
		echo '</tbody></table>';

		// Le téléchargement est le bouton principal d'une notice partie, en
		// tête de fiche ; ici, il n'est que secondaire, pour relire une
		// notice qui attend encore.
		if ( 'envoyee' !== (string) get_post_meta( $id, '_na_etat', true ) && $this->a_un_document( $id ) ) {
			echo '<p style="margin-top:12px"><a class="button" href="'
				. esc_url( $this->url_du_document( $id ) ) . '">'
				. esc_html__( 'Télécharger la notice (Word)', 'notice-archeomed' ) . '</a></p>';
		}
		echo '<p class="description">'
			. esc_html__( 'Le document est refabriqué à partir de la saisie : il ne peut pas se perdre, et suit les évolutions de la feuille de styles.', 'notice-archeomed' )
			. '</p>';

		$this->afficher_les_illustrations( $post );

		// L'encart de publication de WordPress est ôté — il offrait
		// « Enregistrer » et un statut à changer, deux façons de faire sortir
		// une notice de la file. Seul son lien de corbeille avait un usage.
		if ( current_user_can( 'delete_post', $id ) ) {
			echo '<p style="margin-top:14px"><a class="submitdelete" href="'
				. esc_url( get_delete_post_link( $id ) ) . '">'
				. esc_html__( 'Mettre cette notice à la corbeille', 'notice-archeomed' ) . '</a></p>';
		}
	}

	/**
	 * Ce qui sert quand un envoi échoue, et seulement alors : les essais, la
	 * réponse exacte du serveur, le diagnostic pièce par pièce.
	 */
	public function afficher_le_depannage( $post ) {
		$id     = (int) $post->ID;
		$etat   = (string) get_post_meta( $id, '_na_etat', true );
		$essais = (int) get_post_meta( $id, '_na_essais', true );
		echo '<p>' . esc_html( sprintf(
			/* translators: 1 : essais faits ; 2 : essais permis avant l'échec. */
			__( 'Tentatives d’envoi comptées : %1$d sur %2$d avant l’échec.', 'notice-archeomed' ),
			$essais, self::ESSAIS_MAX ) ) . '</p>';
		$erreur = (string) get_post_meta( $id, '_na_erreur', true );
		if ( '' !== $erreur ) {
			echo '<p><strong>' . esc_html( self::expliquer_l_erreur( $erreur ) ) . '</strong></p>';
			echo '<p>' . esc_html__( 'Réponse exacte du serveur :', 'notice-archeomed' )
				. ' <code>' . esc_html( $erreur ) . '</code></p>';
		}
		$note = (string) get_post_meta( $id, '_na_note', true );
		if ( '' !== $note ) {
			echo '<p>' . esc_html( $note ) . '</p>';
		}
		if ( 'envoyee' === $etat ) {
			echo '<p class="description">' . esc_html__( 'La notice est partie : il n’y a rien à dépanner.', 'notice-archeomed' ) . '</p>';
			return;
		}
		$diag = wp_nonce_url(
			add_query_arg( array( 'action' => 'na_diagnostic', 'post' => $id ),
				admin_url( 'admin-post.php' ) ),
			'na_diagnostic_' . $id
		);
		echo '<p><a class="button" href="' . esc_url( $diag ) . '">'
			. esc_html__( 'Diagnostiquer l’envoi de cette notice', 'notice-archeomed' ) . '</a></p>';
		echo '<p class="description">'
			. esc_html__( 'Refait l’envoi vers votre propre adresse, en ajoutant un à un les éléments de cette notice : sujet, corps, en-tête de réponse, pièces jointes. La première ligne marquée « échec » nomme ce qui le fait échouer. Rien ne part à la rédaction.', 'notice-archeomed' )
			. '</p>';
		$lignes = get_transient( 'na_diagnostic_' . $id );
		if ( is_array( $lignes ) && ! empty( $lignes ) ) {
			echo '<table class="widefat striped" style="max-width:52em"><tbody>';
			foreach ( $lignes as $l ) {
				echo '<tr><td style="width:44%">' . esc_html( $l['etape'] ) . '</td>'
					. '<td>' . self::verdict( ! empty( $l['ok'] ) ) . ' ' . esc_html( $l['message'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}

	/**
	 * « Réussi » ou « Échec », en mot et en icône : le vert et le rouge seuls
	 * ne disent rien à qui ne les distingue pas.
	 */
	public static function verdict( $ok ) {
		return $ok
			? '<span class="na-etat na-etat--ok"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> '
				. esc_html__( 'réussi :', 'notice-archeomed' ) . '</span>'
			: '<span class="na-etat na-etat--echec"><span class="dashicons dashicons-warning" aria-hidden="true"></span> '
				. esc_html__( 'échec :', 'notice-archeomed' ) . '</span>';
	}

	/**
	 * Les illustrations reçues, gardées et téléchargeables.
	 *
	 * Elles étaient effacées après l'envoi : tout le reste d'une notice se
	 * retrouve — le texte est en réserve, le document se refabrique — mais
	 * l'image d'un auteur, non. On la garde donc, et on la donne à reprendre
	 * ici plutôt que dans une boîte aux lettres.
	 */
	private function afficher_les_illustrations( $post ) {
		// Chaque figure garde son rang, présente ou non : renuméroter après
		// avoir écarté un fichier perdu faisait pointer « Fig. 1 » sur lui,
		// puisque le téléchargement lit la liste telle qu'enregistrée.
		$gardees = array_values( array_filter(
			(array) get_post_meta( $post->ID, '_na_illustrations', true ), 'is_string' ) );
		$donnees = get_post_meta( $post->ID, '_na_donnees', true );
		// Les légendes par leur rang, et non par leur position : une figure
		// sans légende n'en décale plus les suivantes.
		$dites = array();
		if ( is_array( $donnees ) && ! empty( $donnees['illustrations'] ) ) {
			foreach ( (array) $donnees['illustrations'] as $item ) {
				if ( isset( $item['rang'] ) ) {
					$dites[ (int) $item['rang'] - 1 ] = $item;
				}
			}
		}

		echo '<h3 style="margin:14px 0 6px;font-size:1em">'
			. esc_html__( 'Illustrations', 'notice-archeomed' ) . '</h3>';

		if ( empty( $gardees ) ) {
			$anciennes = (array) get_post_meta( $post->ID, '_na_fichiers', true );
			echo '<p class="description">' . esc_html(
				empty( $anciennes )
					? __( 'Aucune illustration reçue.', 'notice-archeomed' )
					: __( 'Reçues avant que le plugin ne les conserve : elles ne sont plus que dans le courriel de la rédaction.', 'notice-archeomed' )
			) . '</p>';
			return;
		}

		$poids = 0;
		echo '<ul style="margin:0">';
		foreach ( $gardees as $rang => $chemin ) {
			if ( ! file_exists( $chemin ) ) {
				echo '<li>' . esc_html( Notice_Archeomed_Normes::numero_de_figure( $rang + 1 ) ) . ' — <em>'
					. esc_html__( 'fichier introuvable sur le serveur', 'notice-archeomed' ) . '</em></li>';
				continue;
			}
			$poids += (int) filesize( $chemin );
			$url = wp_nonce_url(
				add_query_arg(
					array( 'action' => 'na_illustration', 'post' => (int) $post->ID,
						'rang' => (int) $rang ),
					admin_url( 'admin-post.php' )
				),
				'na_illustration_' . (int) $post->ID . '_' . (int) $rang
			);
			$titre = isset( $dites[ $rang ]['titre'] ) ? $dites[ $rang ]['titre'] : '';
			$alt   = isset( $dites[ $rang ]['alt'] ) && is_scalar( $dites[ $rang ]['alt'] ) ? trim( (string) $dites[ $rang ]['alt'] ) : '';
			$description = isset( $dites[ $rang ]['description'] ) && is_scalar( $dites[ $rang ]['description'] ) ? trim( (string) $dites[ $rang ]['description'] ) : '';
			echo '<li><a href="' . esc_url( $url ) . '">'
				. esc_html( Notice_Archeomed_Normes::numero_de_figure( $rang + 1 ) ) . '</a>'
				. ( '' !== $titre ? ' — ' . esc_html( $titre ) : '' )
				. ' <span class="description">('
				. esc_html( size_format( filesize( $chemin ) ) ) . ')</span>'
				. $this->lien_de_l_autorisation( $post->ID, $rang + 1, $poids )
				// Le texte alternatif, que les notices d'avant ce champ n'ont
				// pas : leur titre en tient lieu.
				. '<br><span class="description">' . esc_html( '' !== $alt
					? "Texte alternatif\u{00A0}: " . $alt
					: "Pas de texte alternatif\u{00A0}: le titre en tient lieu." ) . '</span>'
				. ( '' !== $description ? '<br><span class="description">' . esc_html( "Description détaillée\u{00A0}: " )
					. nl2br( esc_html( $description ) ) . '</span>' : '' ) . '</li>';
		}
		echo '</ul>';
		echo '<p class="description">' . esc_html( sprintf(
			/* translators: %s : poids total, déjà mis en forme. */
			__( '%s sur le serveur. Supprimer la notice les efface aussi.', 'notice-archeomed' ),
			size_format( $poids ) ) ) . '</p>';
	}

	/**
	 * Le lien vers l'autorisation de reproduction d'une figure, s'il y en a
	 * une. Son poids s'ajoute à celui des illustrations.
	 */
	private function lien_de_l_autorisation( $id, $figure, &$poids ) {
		$autorisations = (array) get_post_meta( $id, '_na_autorisations', true );
		if ( empty( $autorisations[ $figure ] ) || ! is_string( $autorisations[ $figure ] )
			|| ! file_exists( $autorisations[ $figure ] ) ) {
			return '';
		}
		$poids += (int) filesize( $autorisations[ $figure ] );
		$url = wp_nonce_url(
			add_query_arg(
				array( 'action' => 'na_illustration', 'post' => (int) $id,
					'rang' => (int) $figure, 'autorisation' => 1 ),
				admin_url( 'admin-post.php' )
			),
			'na_illustration_' . (int) $id . '_' . (int) $figure . '_autorisation'
		);
		return '<br><span class="dashicons dashicons-media-document" aria-hidden="true"></span> <a href="'
			. esc_url( $url ) . '">' . esc_html__( 'Autorisation de reproduction', 'notice-archeomed' ) . '</a>';
	}

	/**
	 * Écarte de la fiche les encarts des autres extensions.
	 *
	 * Une notice reçue n'est ni un article ni un produit : les encarts de
	 * référencement, de partage ou d'avis n'y ont rien à faire, et le premier
	 * mouvement devant « Product Review Extra Setting : is it a review post ? »
	 * est de se demander ce qu'on a cassé.
	 */
	public function ecarter_les_intrus( $type ) {
		if ( self::CPT !== $type ) {
			return;
		}
		global $wp_meta_boxes;
		if ( empty( $wp_meta_boxes[ $type ] ) ) {
			return;
		}
		// L'encart de publication part aussi : il offrait « Enregistrer » et
		// un statut à changer sur une notice qu'on ne modifie pas, et un
		// passage en brouillon la sortait de la file sans rien dire.
		$gardes = array( 'na_fiche', 'na_accessibilite', 'na_suivi', 'na_depannage' );
		foreach ( $wp_meta_boxes[ $type ] as $contexte => $priorites ) {
			foreach ( $priorites as $priorite => $boites ) {
				foreach ( array_keys( (array) $boites ) as $identifiant ) {
					if ( ! in_array( $identifiant, $gardes, true ) ) {
						unset( $wp_meta_boxes[ $type ][ $contexte ][ $priorite ][ $identifiant ] );
					}
				}
			}
		}
	}

	/**
	 * L'ordre du volume, écrit avec la notice : rubrique, famille, commune.
	 *
	 * Le calcul appartient au plugin principal, qui connaît les rubriques et
	 * les familles ; la file se contente de l'enregistrer pour pouvoir trier.
	 */
	public function poser_le_classement( $id, $donnees ) {
		$plugin = is_array( $donnees ) ? $this->plugin() : null;
		if ( null === $plugin ) {
			// Le tri se fait sur cette clef : une notice qui n'en porterait
			// pas sortirait de la liste au lieu d'y figurer en queue. On en
			// écrit donc une, même faute de mieux.
			update_post_meta( $id, '_na_classement', '99|9|' );
			return;
		}
		$famille = $plugin->famille_de( $donnees );
		update_post_meta( $id, '_na_classement', $plugin->classement_de( $donnees ) );
		update_post_meta( $id, '_na_famille', $famille['nom'] );
	}

	private function plugin() {
		global $notice_archeomed_plugin;
		return ( $notice_archeomed_plugin instanceof Notice_Archeomed_Pactols )
			? $notice_archeomed_plugin : null;
	}

	/**
	 * Les notices d'autres rubriques qui renvoient vers celle-ci.
	 *
	 * Un renvoi s'imprimait en tête de la notice qui le porte, dans sa propre
	 * rubrique — là où il ne sert à personne : c'est le préparateur de l'autre
	 * rubrique qui doit savoir qu'une notice le concerne, et il ne l'apprenait
	 * qu'en lisant le fascicule voisin. On le range donc où il est utile, à sa
	 * place alphabétique.
	 *
	 * Les renvois vivent dans la saisie et non dans une métadonnée à part :
	 * les lire ici évite une migration, et vaut pour les notices anciennes
	 * comme pour les neuves. Le coût est de quelques centaines de lectures sur
	 * une action demandée à la main, qui en fait bien davantage par ailleurs.
	 */
	public function renvois_vers( $rubrique ) {
		// Un ancien libellé de rubrique vaut le nouveau, des deux côtés.
		$rubrique   = Notice_Archeomed_Pactols::rubrique_actuelle( $rubrique );
		$remplacees = $this->references_remplacees();
		$ids = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_na_classement',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			if ( isset( $remplacees[ (string) get_post_meta( $id, '_na_reference', true ) ] ) ) {
				continue;
			}
			$d = get_post_meta( $id, '_na_donnees', true );
			if ( ! is_array( $d ) || empty( $d ) ) {
				continue;
			}
			// Une notice ne se renvoie pas à elle-même : sa rubrique
			// principale la porte déjà.
			if ( isset( $d['rubrique_principale'] ) && $rubrique === Notice_Archeomed_Pactols::rubrique_actuelle( $d['rubrique_principale'] ) ) {
				continue;
			}
			foreach ( array( 'renvoi_1', 'renvoi_2' ) as $champ ) {
				if ( isset( $d[ $champ ] ) && $rubrique === Notice_Archeomed_Pactols::rubrique_actuelle( $d[ $champ ] ) ) {
					$out[] = $d;
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * Les notices d'une rubrique, dans l'ordre où elles seront relues.
	 */
	public function notices_de_la_rubrique( $rubrique ) {
		// Sans plafond : à 500, une rubrique de deux campagnes non purgées
		// perdait des notices sans avis.
		$ids = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// Les notices reçues sous un ancien libellé de la rubrique en
				// sont, sans que rien soit réécrit.
				'meta_query'     => array(
					array(
						'key'     => '_na_rubrique',
						'value'   => Notice_Archeomed_Pactols::libelles_de_la_rubrique( $rubrique ),
						'compare' => 'IN',
					),
				),
				'meta_key'       => '_na_classement',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
			)
		);
		// Une notice corrigée ne paraît qu'une fois : sa correction la
		// remplace. Les deux figuraient au fascicule et au dossier, et le
		// bandeau qui l'aurait dit n'y est pas.
		$remplacees = $this->references_remplacees();
		if ( empty( $remplacees ) ) {
			return $ids;
		}
		return array_values( array_filter( $ids, function ( $id ) use ( $remplacees ) {
			return ! isset( $remplacees[ (string) get_post_meta( $id, '_na_reference', true ) ] );
		} ) );
	}

	/**
	 * Les références que des corrections ont remplacées, en clefs, chacune
	 * avec la référence de la correction qui la remplace.
	 */
	private function references_remplacees() {
		$ids = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_na_remplace',
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);
		$refs = array();
		foreach ( $ids as $id ) {
			$ref = strtoupper( trim( (string) get_post_meta( $id, '_na_remplace', true ) ) );
			if ( '' !== $ref ) {
				$refs[ $ref ] = (string) get_post_meta( $id, '_na_reference', true );
			}
		}
		return $refs;
	}

	/**
	 * Un fascicule par rubrique, à télécharger d'un clic.
	 *
	 * On ne relit pas quarante notices dans quarante courriels : on les ouvre
	 * une à une, on perd le fil, et l'on ne voit ni les doublons ni les
	 * communes qui se suivent mal.
	 *
	 * Les liens tenaient en deux lignes de chiffres romains, « I · II · III »,
	 * larges de dix pixels : la rubrique ne se nommait qu'au survol, rien ne
	 * disait combien de notices elle portait — on téléchargeait un fascicule
	 * vide —, et sur un téléphone la page s'élargissait jusqu'à cacher la
	 * moitié des liens. Un tableau les range : la rubrique en toutes lettres,
	 * son nombre de notices, et un lien par usage.
	 */
	public function liens_des_fascicules( $vues ) {
		$rubriques = $this->rubriques_presentes();
		if ( empty( $rubriques ) ) {
			return $vues;
		}
		$comptes = $this->comptes_par_rubrique();
		$lignes  = '';
		foreach ( $rubriques as $rubrique ) {
			$n = isset( $comptes[ $rubrique ] ) ? (int) $comptes[ $rubrique ] : 0;
			$lignes .= '<tr><th scope="row">' . esc_html( $rubrique ) . '</th>'
				. '<td class="na-dossiers-nombre">' . esc_html( number_format_i18n( $n ) )
				. '<span class="na-dossiers-mot"> ' . esc_html( $n > 1 ? __( 'notices', 'notice-archeomed' ) : __( 'notice', 'notice-archeomed' ) ) . '</span></td>'
				. '<td>' . $this->lien_de_rubrique( 'na_fascicule', $rubrique,
					__( 'Fascicule Word', 'notice-archeomed' ) ) . '</td>'
				// Le paquet complet, à côté du fascicule seul : le même document,
				// plus les illustrations rangées aux dossiers de Métopes (chaîne
				// d'édition XML créée par le Pôle document numérique et
				// l'infrastructure Métopes de l'université de Caen Normandie,
				// https://www.metopes.fr).
				. '<td>' . $this->lien_de_rubrique( 'na_paquet', $rubrique,
					__( 'Dossier Métopes (zip)', 'notice-archeomed' ) ) . '</td></tr>';
		}
		// Le bloc passe en tête des vues, sur sa propre ligne. WordPress écrit
		// les vues comme une ligne de texte — « Tous | Privées » — et ajoute
		// une barre après chacune : la taille de police nulle efface celle-ci,
		// que le tableau n'a pas à porter. Sur un téléphone, c'est le tableau
		// qui défile, non la page.
		// WordPress interdit aussi le retour à la ligne dans les vues : la
		// phrase sous le tableau débordait de l'écran.
		$bloc = '<style>ul.subsubsub li.na_dossiers{display:block;width:100%;font-size:0;margin:0 0 10px;white-space:normal}'
			. 'ul.subsubsub li.na_dossiers>*{font-size:13px}'
			. '.na-dossiers-cadre{max-width:52rem}'
			. '.na-dossiers{margin:8px 0 4px}'
			. 'table.na-dossiers>tbody>tr>th,table.na-dossiers>tbody>tr>td{vertical-align:middle;padding-top:6px;padding-bottom:6px}'
			. 'table.na-dossiers>tbody>tr>th{font-weight:400}'
			. '.na-dossiers .na-dossiers-nombre{text-align:right;font-variant-numeric:tabular-nums;width:5em}'
			. '.na-dossiers a.na-telechargement{display:inline-block;padding:4px 0}'
			. '.na-dossiers-mot{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}'
			// Sur un téléphone, chaque rubrique devient un petit bloc : son nom,
			// puis le compte et les deux liens. Un tableau à quatre colonnes y
			// élargissait la page entière.
			. '@media screen and (max-width:782px){'
			// La liste des vues flotte, et prenait la largeur de sa plus longue
			// ligne : c'est elle qui élargissait la page.
			. 'ul.subsubsub{float:none;max-width:100%}'
			. 'table.na-dossiers>thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}'
			. 'table.na-dossiers,table.na-dossiers>tbody,table.na-dossiers>tbody>tr{display:block}'
			. 'table.na-dossiers>tbody>tr>th{display:block;padding:10px 10px 0;font-weight:600}'
			. 'table.na-dossiers>tbody>tr>td{display:inline-block;width:auto;padding:4px 10px 10px}'
			. 'table.na-dossiers .na-dossiers-mot{position:static;width:auto;height:auto;overflow:visible;clip:auto}'
			. 'table.na-dossiers a.na-telechargement{min-height:24px;padding:6px 0}}</style>'
			. '<div class="na-dossiers-cadre">'
			. '<table class="widefat striped na-dossiers">'
			. '<caption class="screen-reader-text">' . esc_html__( 'Fascicules et dossiers Métopes, par rubrique', 'notice-archeomed' ) . '</caption>'
			. '<thead><tr><th scope="col">' . esc_html__( 'Rubrique', 'notice-archeomed' ) . '</th>'
			. '<th scope="col" class="na-dossiers-nombre">' . esc_html__( 'Notices', 'notice-archeomed' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Pour relire', 'notice-archeomed' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Pour la mise en page', 'notice-archeomed' ) . '</th></tr></thead>'
			. '<tbody>' . $lignes . '</tbody></table></div>'
			. '<p class="description">' . esc_html__( 'Le dossier Métopes réunit le document, les illustrations en haute et basse définition, et l’arborescence icono.', 'notice-archeomed' ) . '</p>'
			// Où s'annonce l'attente, hors du lien : un lien marqué « occupé »
			// fait taire ce qu'il contient, pas ce qui est à côté.
			. '<p class="na-attente" role="status" aria-live="polite"></p>';
		return array( 'na_dossiers' => $bloc ) + $vues;
	}

	/**
	 * Combien de notices chaque rubrique portera, en une seule requête.
	 *
	 * Le compte suit le fascicule : les notices privées, moins celles qu'une
	 * correction remplace. Une lecture par notice aurait coûté trois cents
	 * requêtes à chaque ouverture de la liste.
	 */
	private function comptes_par_rubrique() {
		global $wpdb;
		$rangs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.meta_value AS rubrique, f.meta_value AS reference
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} r ON r.post_id = p.ID AND r.meta_key = '_na_rubrique'
				 LEFT JOIN {$wpdb->postmeta} f ON f.post_id = p.ID AND f.meta_key = '_na_reference'
				 WHERE p.post_type = %s AND p.post_status = %s",
				self::CPT,
				'private'
			),
			ARRAY_A
		);
		$remplacees = $this->references_remplacees();
		$comptes    = array();
		foreach ( (array) $rangs as $rang ) {
			if ( isset( $remplacees[ (string) $rang['reference'] ] ) ) {
				continue;
			}
			$cle = Notice_Archeomed_Pactols::rubrique_actuelle( (string) $rang['rubrique'] );
			$comptes[ $cle ] = isset( $comptes[ $cle ] ) ? $comptes[ $cle ] + 1 : 1;
		}
		return $comptes;
	}

	/**
	 * Un lien de rubrique : l'usage à l'écran, la rubrique en plus pour qui
	 * l'entend lire.
	 *
	 * Hors du tableau, un lecteur d'écran qui parcourt les liens annoncerait
	 * « Fascicule Word » autant de fois qu'il y a de rubriques. La rubrique
	 * suit donc, pour lui seul : ce qui s'entend contient ce qui se voit, et
	 * l'on peut encore désigner le lien à la voix.
	 */
	private function lien_de_rubrique( $action, $rubrique, $libelle ) {
		$url = wp_nonce_url(
			add_query_arg(
				array( 'action' => $action, 'rubrique' => rawurlencode( $rubrique ) ),
				admin_url( 'admin-post.php' )
			),
			$action
		);
		return '<a class="na-telechargement" href="' . esc_url( $url ) . '">'
			. esc_html( $libelle )
			. '<span class="screen-reader-text"> — ' . esc_html( $rubrique ) . '</span></a>';
	}

	/**
	 * Le récapitulatif du jour, adressé à la rédaction.
	 *
	 * Sur une campagne de plusieurs milliers de sollicitations, un courriel
	 * par notice enterre la boîte de la rédaction : on ne sait plus ce qui est
	 * arrivé, ni combien, ni si l'on a tout vu. Ce message-là tient sur un
	 * écran, range les notices dans l'ordre du volume, et renvoie à
	 * l'administration où tout est consultable.
	 *
	 * L'envoi individuel continue par ailleurs : le récapitulatif s'ajoute, il
	 * ne remplace pas — c'est ce qui a été demandé le temps des essais.
	 */
	public function recapituler() {
		$depuis = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		$ids = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'private',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_key'       => '_na_classement',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'date_query'     => array( array( 'after' => $depuis, 'inclusive' => true ) ),
			)
		);
		if ( empty( $ids ) ) {
			return;   // un récapitulatif vide n'apprend rien et se met à ignorer
		}

		// Le récapitulatif a ses propres destinataires : on peut vouloir la
		// liste du jour sans recevoir les quarante notices qui la composent.
		$destinataires = Notice_Archeomed_Settings::destinataires_de( 'recap' );
		if ( empty( $destinataires ) ) {
			return;
		}

		$par_rubrique = array();
		foreach ( $ids as $id ) {
			$rubrique = Notice_Archeomed_Pactols::rubrique_actuelle( (string) get_post_meta( $id, '_na_rubrique', true ) );
			$rubrique = '' !== $rubrique ? $rubrique : __( 'Sans rubrique', 'notice-archeomed' );
			$par_rubrique[ $rubrique ][] = $id;
		}
		ksort( $par_rubrique );

		$corps = '<p>Bonjour,</p><p>' . esc_html( sprintf(
			/* translators: %d : nombre de notices. */
			_n( '%d notice reçue depuis hier.', '%d notices reçues depuis hier.',
				count( $ids ), 'notice-archeomed' ),
			count( $ids ) ) ) . '</p>';
		foreach ( $par_rubrique as $rubrique => $liste ) {
			$corps .= '<p><strong>' . esc_html( $rubrique ) . '</strong></p><ul>';
			foreach ( $liste as $id ) {
				$famille = (string) get_post_meta( $id, '_na_famille', true );
				// Le titre seul : « get_the_title » le préfixe de « Privé : »,
				// que le courriel répétait à chaque ligne.
				$corps .= '<li><a href="' . esc_url( get_edit_post_link( $id, '' ) ) . '">'
					. esc_html( get_post_field( 'post_title', $id ) ) . '</a>'
					. ( '' !== $famille ? ' — ' . esc_html( $famille ) : '' )
					. ' — ' . esc_html( (string) get_post_meta( $id, '_na_responsable', true ) )
					// L'état, dans les mêmes mots que la liste : une notice en
					// échec se voyait dans le récapitulatif comme les autres,
					// et l'on croyait l'avoir reçue.
					. ' — <em>' . esc_html( $this->libelle_de_l_etat( $id, true ) ) . '</em>'
					. '</li>';
			}
			$corps .= '</ul>';
		}
		$corps .= '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=' . self::CPT ) )
			. '">Toutes les notices reçues</a> — le fascicule de chaque rubrique s’y '
			. 'télécharge d’un clic, prêt à relire.</p>';

		wp_mail(
			$destinataires,
			sprintf( 'Notices Archéomed — %d reçue(s) le %s',
				count( $ids ), date_i18n( 'j F Y' ) ),
			'<html><body><div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#222">'
				. $corps . '</div></body></html>',
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
	}

	/**
	 * Trie sur une métadonnée sans faire disparaître ce qui ne la porte pas.
	 *
	 * Demander « meta_key » à WordPress lui fait joindre la table des
	 * métadonnées en jointure fermante : une notice à qui la clef manque sort
	 * de la liste, purement et simplement. C'est ainsi que les notices
	 * déposées avant l'ordre du volume ont paru effacées alors qu'elles
	 * étaient bien là. La clause nommée, avec son « NOT EXISTS », impose une
	 * jointure ouvrante : rien ne se perd, et ce qui manque passe en queue.
	 */
	private function trier_sur( $requete, $meta, $sens = 'ASC' ) {
		// Une clause nommée bâtie sur « OR » ne donne pas d'alias utilisable
		// pour trier : WordPress l'accepte sans broncher, et ne trie rien du
		// tout. C'était le cas de la liste, qui restait dans l'ordre des
		// dates. On revient donc au tri simple — sûr, puisque le rattrapage
		// a donné sa clef de classement à chaque notice, et que le dépôt en
		// écrit une systématiquement.
		$requete->set( 'meta_key', $meta );
		$requete->set( 'orderby', 'meta_value' );
		$requete->set( 'order', $sens );
	}

	public function marquer( $id, $etat, $erreur = '' ) {
		update_post_meta( $id, '_na_etat', $etat );
		if ( 'envoyee' === $etat ) {
			// Une notice partie n'a plus d'erreur : la raison d'un échec
			// ancien restait affichée en rouge sur sa fiche, comme si elle
			// n'était jamais partie. Ce qui accompagne un envoi réussi — des
			// illustrations restées sur le site — est une note, non une erreur.
			delete_post_meta( $id, '_na_erreur' );
			if ( '' !== $erreur ) {
				update_post_meta( $id, '_na_note', $erreur );
			} else {
				delete_post_meta( $id, '_na_note' );
			}
			update_post_meta( $id, '_na_envoyee_le', current_time( 'mysql' ) );
			return;
		}
		if ( '' !== $erreur ) {
			update_post_meta( $id, '_na_erreur', $erreur );
		}
	}

	/**
	 * Compte un essai et dit s'il faut en tenter d'autres.
	 */
	public function compter_un_essai( $id, $pourquoi = '' ) {
		$essais = (int) get_post_meta( $id, '_na_essais', true ) + 1;
		update_post_meta( $id, '_na_essais', $essais );
		// On rend la notice à la file : la prise ne vaut que le temps d'un essai.
		update_post_meta( $id, '_na_etat', 'en_attente' );
		// La raison du dernier refus se garde dès le premier essai : attendre
		// le cinquième, c'est laisser vingt minutes sans rien dire à qui
		// regarde la liste.
		if ( '' !== $pourquoi ) {
			update_post_meta( $id, '_na_erreur', $pourquoi );
		}
		if ( $essais >= self::ESSAIS_MAX ) {
			$this->marquer( $id, 'echec', '' !== $pourquoi
				? 'Cinq tentatives sans succès. ' . $pourquoi
				: 'Cinq tentatives d’envoi sans succès.' );
			return false;
		}
		// Espacement croissant : une panne de serveur de courriel dure
		// rarement dix secondes, et représenter aussitôt ne fait qu'ajouter
		// à la charge.
		wp_schedule_single_event( time() + ( $essais * 5 * MINUTE_IN_SECONDS ),
			self::HOOK_UNE, array( (int) $id ) );
		return true;
	}

	public function colonnes( $colonnes ) {
		$nouvelles = array();
		foreach ( $colonnes as $clef => $titre ) {
			if ( 'date' === $clef ) {
				continue;   // remplacée par « Reçue le », en fin de ligne
			}
			$nouvelles[ $clef ] = $titre;
			if ( 'title' === $clef ) {
				$nouvelles['title'] = __( 'Notice', 'notice-archeomed' );
				// L'ordre est celui du travail : de quelle rubrique relève la
				// notice, qui l'a signée, où en est son envoi. Le numéro de
				// suivi ne sert qu'en cas d'incident : il vient après.
				$nouvelles['na_rubrique']    = __( 'Rubrique', 'notice-archeomed' );
				$nouvelles['na_responsable'] = __( 'Responsable', 'notice-archeomed' );
				// « Envoi » et non « État » : c'est de l'envoi à la rédaction
				// qu'il s'agit, non de la notice elle-même, qu'aucun état
				// n'empêche de relire ni de mettre au fascicule.
				$nouvelles['na_etat']        = __( 'Envoi', 'notice-archeomed' );
				$nouvelles['na_reference']   = __( 'Référence', 'notice-archeomed' );
			}
		}
		// La colonne de WordPress disait « Privé » et « Dernière
		// modification » : deux renseignements qui ne concernent pas une
		// notice reçue, et une date au format anglais.
		$nouvelles['na_recue'] = __( 'Reçue le', 'notice-archeomed' );
		return $nouvelles;
	}

	/**
	 * Le classement par rubrique est celui du volume : c'est dans cet ordre
	 * que la Chronique se monte, et donc dans cet ordre qu'on relit.
	 */
	public function colonnes_triables( $colonnes ) {
		$colonnes['na_rubrique']    = 'na_rubrique';
		$colonnes['na_responsable'] = 'na_responsable';
		$colonnes['na_etat']        = 'na_etat';
		// Les plus récentes d'abord au premier clic : c'est la question qu'on
		// se pose en ouvrant la liste un matin de campagne.
		$colonnes['na_recue']       = array( 'date', true );
		return $colonnes;
	}

	public function filtre_par_rubrique() {
		global $typenow;
		if ( self::CPT !== $typenow ) {
			return;
		}
		// Le filtre garde la vue : sans ce champ, filtrer les notices en
		// échec par rubrique ramenait à toutes les notices de la rubrique.
		$vue = self::etat_demande();
		if ( '' !== $vue ) {
			echo '<input type="hidden" name="na_etat" value="' . esc_attr( $vue ) . '">';
		}
		$choisie = isset( $_GET['na_rubrique'] )
			? sanitize_text_field( wp_unslash( $_GET['na_rubrique'] ) ) : '';
		$rubriques = $this->rubriques_presentes();
		if ( empty( $rubriques ) ) {
			return;
		}
		echo '<label for="na-filtre-rubrique" class="screen-reader-text">'
			. esc_html__( 'Filtrer par rubrique', 'notice-archeomed' ) . '</label>';
		echo '<select name="na_rubrique" id="na-filtre-rubrique"><option value="">'
			. esc_html__( 'Toutes les rubriques', 'notice-archeomed' ) . '</option>';
		foreach ( $rubriques as $rubrique ) {
			echo '<option value="' . esc_attr( $rubrique ) . '"'
				. selected( $choisie, $rubrique, false ) . '>'
				. esc_html( $rubrique ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Les rubriques réellement représentées, et elles seules : offrir un
	 * filtre sur une rubrique vide ne rend service à personne.
	 *
	 * Les notices privées seules, comme le fascicule : une rubrique dont la
	 * dernière notice était à la corbeille gardait ses liens, et le clic
	 * répondait « Aucune notice dans cette rubrique ».
	 */
	private function rubriques_presentes() {
		global $wpdb;
		$valeurs = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} m
				 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				 WHERE m.meta_key = %s AND p.post_type = %s AND p.post_status = %s
				   AND m.meta_value <> ''
				 ORDER BY meta_value ASC",
				'_na_rubrique',
				self::CPT,
				'private'
			)
		);
		// Un ancien libellé se range sous le nouveau : une seule ligne, un
		// seul fascicule.
		return is_array( $valeurs )
			? array_values( array_unique( array_map( array( 'Notice_Archeomed_Pactols', 'rubrique_actuelle' ), $valeurs ) ) )
			: array();
	}

	/** La vue par état demandée dans l'adresse, si elle est connue. */
	private static function etat_demande() {
		$vue = isset( $_GET['na_etat'] ) ? sanitize_key( wp_unslash( $_GET['na_etat'] ) ) : '';
		return in_array( $vue, array( 'a_traiter', 'echec', 'file', 'envoyees' ), true ) ? $vue : '';
	}

	/**
	 * La condition d'une vue, dans la langue des requêtes de WordPress.
	 *
	 * « À traiter », c'est ce qui demande une main : l'échec, et ce qui
	 * attend encore mais a déjà été refusé une fois — la cause, souvent, ne
	 * se lèvera pas toute seule.
	 */
	private static function clause_de_la_vue( $vue ) {
		$file = array( 'key' => '_na_etat', 'value' => array( 'en_attente', 'en_cours' ), 'compare' => 'IN' );
		if ( 'echec' === $vue ) {
			return array( 'key' => '_na_etat', 'value' => 'echec' );
		}
		if ( 'envoyees' === $vue ) {
			return array( 'key' => '_na_etat', 'value' => 'envoyee' );
		}
		if ( 'file' === $vue ) {
			return $file;
		}
		return array(
			'relation' => 'OR',
			array( 'key' => '_na_etat', 'value' => 'echec' ),
			array(
				'relation' => 'AND',
				$file,
				array( 'key' => '_na_essais', 'value' => array( '', '0' ), 'compare' => 'NOT IN' ),
			),
		);
	}

	public function appliquer_le_filtre( $requete ) {
		if ( ! is_admin() || ! $requete->is_main_query() ) {
			return;
		}
		if ( self::CPT !== $requete->get( 'post_type' ) ) {
			return;
		}
		$clauses = array();
		$choisie = isset( $_GET['na_rubrique'] )
			? sanitize_text_field( wp_unslash( $_GET['na_rubrique'] ) ) : '';
		if ( '' !== $choisie ) {
			$clauses[] = array( 'key' => '_na_rubrique', 'compare' => 'IN',
				'value' => Notice_Archeomed_Pactols::libelles_de_la_rubrique( $choisie ) );
		}
		$vue = self::etat_demande();
		if ( '' !== $vue ) {
			$clauses[] = self::clause_de_la_vue( $vue );
			// Les comptes des vues ne portent que sur les notices privées : la
			// liste doit dire le même nombre que le lien qui y mène.
			$requete->set( 'post_status', 'private' );
		}
		if ( ! empty( $clauses ) ) {
			$requete->set( 'meta_query', array_merge( array( 'relation' => 'AND' ), $clauses ) );
		}
		// C'est l'adresse qui dit si l'on a cliqué sur une colonne, non la
		// requête : WordPress y pose un tri par défaut, si bien qu'attendre
		// une valeur vide revenait à ne jamais appliquer l'ordre du volume.
		$demande = isset( $_GET['orderby'] )
			? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
		$colonnes = array(
			'na_rubrique'    => '_na_classement',
			'na_responsable' => '_na_responsable',
			'na_etat'        => '_na_etat',
		);
		if ( isset( $colonnes[ $demande ] ) ) {
			$this->trier_sur( $requete, $colonnes[ $demande ],
				'desc' === strtolower( (string) $requete->get( 'order' ) ) ? 'DESC' : 'ASC' );
			return;
		}
		if ( '' !== $demande ) {
			return;   // un tri demandé sur une autre colonne : on le laisse faire
		}
		// Sans rien de demandé, c'est l'ordre du volume : rubrique, famille
		// d'opération, commune. C'est celui dans lequel on relit.
		$this->trier_sur( $requete, '_na_classement' );
	}

	/**
	 * La recherche lit aussi la référence, le responsable et son courriel.
	 *
	 * WordPress ne cherche que dans le titre et le texte : la référence que
	 * cite un auteur au téléphone — « ma notice ABC123 » — ne trouvait rien,
	 * ni son nom, alors que la liste les affiche l'un et l'autre.
	 */
	public function etendre_la_recherche( $recherche, $requete ) {
		if ( '' === (string) $recherche || ! is_admin() || ! $requete->is_main_query()
			|| self::CPT !== $requete->get( 'post_type' ) ) {
			return $recherche;
		}
		$terme = trim( (string) $requete->get( 's' ) );
		if ( '' === $terme || ! preg_match( '/^\s*AND\s*\(/', $recherche, $debut ) ) {
			return $recherche;
		}
		global $wpdb;
		$existe = $wpdb->prepare(
			"EXISTS ( SELECT 1 FROM {$wpdb->postmeta} na_m
			  WHERE na_m.post_id = {$wpdb->posts}.ID
			    AND na_m.meta_key IN ( '_na_reference', '_na_responsable', '_na_courriel' )
			    AND na_m.meta_value LIKE %s )",
			'%' . $wpdb->esc_like( $terme ) . '%'
		);
		// Une alternative de plus, en tête de la condition de WordPress : ce
		// qui la vérifiait la vérifie encore.
		return ' AND ( ' . $existe . ' OR ' . substr( $recherche, strlen( $debut[0] ) );
	}

	/**
	 * Une métadonnée de classement, retrouvée dans la notice si elle manque.
	 *
	 * Les notices déposées avant que ces colonnes n'existent n'en portent pas.
	 * Plutôt que de les laisser vides — ou de faire relancer une migration —,
	 * on les recompose à la première lecture et on les enregistre au passage.
	 */
	public function rattraper( $id, $meta, $depuis ) {
		$valeur = (string) get_post_meta( $id, $meta, true );
		if ( '' !== $valeur ) {
			return $valeur;
		}
		$donnees = get_post_meta( $id, '_na_donnees', true );
		if ( ! is_array( $donnees ) ) {
			return '';
		}
		$morceaux = array();
		foreach ( (array) $depuis as $champ ) {
			if ( isset( $donnees[ $champ ] ) ) {
				$morceaux[] = (string) $donnees[ $champ ];
			}
		}
		$valeur = trim( implode( ' ', $morceaux ) );
		if ( '' !== $valeur ) {
			update_post_meta( $id, $meta, $valeur );
		}
		return $valeur;
	}

	public function colonne( $colonne, $id ) {
		if ( 'na_rubrique' === $colonne ) {
			echo esc_html( Notice_Archeomed_Pactols::rubrique_actuelle( $this->rattraper( $id, '_na_rubrique',
				array( 'rubrique_principale' ) ) ) );
			$famille = (string) get_post_meta( $id, '_na_famille', true );
			if ( '' === $famille ) {
				// Les notices déposées avant le classement se rattrapent ici.
				$this->poser_le_classement( $id, get_post_meta( $id, '_na_donnees', true ) );
				$famille = (string) get_post_meta( $id, '_na_famille', true );
			}
			if ( '' !== $famille ) {
				echo '<br><span class="description">' . esc_html( $famille ) . '</span>';
			}
			return;
		}
		if ( 'na_responsable' === $colonne ) {
			$nom = $this->rattraper( $id, '_na_responsable',
				array( 'resp_prenom', 'resp_nom' ) );
			$courriel = $this->rattraper( $id, '_na_courriel', array( 'resp_email' ) );
			echo esc_html( $nom );
			if ( '' !== $courriel ) {
				echo '<br><a href="mailto:' . esc_attr( $courriel ) . '">'
					. esc_html( $courriel ) . '</a>';
			}
			return;
		}
		if ( 'na_reference' === $colonne ) {
			echo esc_html( (string) get_post_meta( $id, '_na_reference', true ) );
			return;
		}
		if ( 'na_recue' === $colonne ) {
			$post = get_post( $id );
			if ( $post ) {
				echo '<time datetime="' . esc_attr( get_post_time( 'c', true, $post ) ) . '">'
					. esc_html( self::date_lisible( $post->post_date, true ) ) . '</time>';
			}
			return;
		}
		if ( 'na_etat' === $colonne ) {
			echo $this->libelle_de_l_etat( $id );
			// La notice qu'une correction remplace ne paraît plus au fascicule :
			// la liste le dit, pour qu'on la mette à la corbeille.
			if ( null === $this->remplacees ) {
				$this->remplacees = $this->references_remplacees();
			}
			$ref = strtoupper( (string) get_post_meta( $id, '_na_reference', true ) );
			if ( '' !== $ref && isset( $this->remplacees[ $ref ] ) ) {
				echo '<br><strong>' . esc_html( sprintf(
					/* translators: %s : référence de la correction. */
					__( 'Remplacée par %s', 'notice-archeomed' ), $this->remplacees[ $ref ] ) )
					. '</strong><span class="description"> — '
					. esc_html__( 'à mettre à la corbeille', 'notice-archeomed' ) . '</span>';
			}
		}
	}

	/** Les références remplacées, lues une fois par affichage de la liste. */
	private $remplacees = null;

	/**
	 * Une date lisible : « 30 septembre 2026 à 14 h 22 », ou, pour une
	 * colonne, « 30 sept. 2026, 14 h 22 ».
	 *
	 * La fiche affichait la date telle que la base la garde,
	 * « 2026-09-30 14:22:07 » : exacte, mais personne ne la lit sans
	 * s'arrêter. Les espaces autour du « h » sont insécables, pour que
	 * l'heure ne se coupe pas en fin de ligne.
	 */
	public static function date_lisible( $mysql, $abregee = false ) {
		$mysql = trim( (string) $mysql );
		if ( '' === $mysql || 0 === strpos( $mysql, '0000' ) || false === strtotime( $mysql ) ) {
			return '';
		}
		// Le mois en entier même dans la colonne : l'abréviation de WordPress
		// dépend de la traduction installée, et sortait « Sep ».
		$jour  = mysql2date( 'j F Y', $mysql );
		// « G » seul, WordPress le lit comme une demande d'horodatage : on
		// prend l'heure sur deux chiffres, et l'on ôte le zéro.
		$heure = (int) mysql2date( 'H', $mysql ) . ' h ' . mysql2date( 'i', $mysql );
		return $abregee ? $jour . ', ' . $heure : $jour . ' à ' . $heure;
	}

	/** L'heure d'un horodatage, à la française : « 14 h 22 ». */
	private static function heure_lisible( $horodatage ) {
		return wp_date( 'G', (int) $horodatage ) . ' h ' . wp_date( 'i', (int) $horodatage );
	}

	/**
	 * Où en est l'envoi d'une notice, dit en mots et non en clefs.
	 *
	 * La liste affichait « en_attente », la fiche aussi, et le récapitulatif
	 * rien du tout : trois endroits, trois façons de dire, et aucune qu'une
	 * secrétaire comprenne sans qu'on la lui traduise. Une seule fonction les
	 * sert tous les trois, pour qu'ils ne divergent plus.
	 *
	 * L'état ne tient jamais à la couleur seule : chaque état a son icône et
	 * son mot. En courriel, où ni l'une ni les classes ne passent, le texte
	 * seul.
	 */
	public function libelle_de_l_etat( $id, $pour_un_courriel = false, $bref = false ) {
		$id     = (int) $id;
		$etat   = (string) get_post_meta( $id, '_na_etat', true );
		$detail = '';
		if ( 'envoyee' === $etat ) {
			$classe = 'ok';
			$icone  = 'dashicons-yes-alt';
			$texte  = __( 'Envoyée à la rédaction', 'notice-archeomed' );
			$note   = (string) get_post_meta( $id, '_na_note', true );
			$quand  = self::date_lisible( get_post_meta( $id, '_na_envoyee_le', true ) );
			if ( '' !== $note ) {
				$detail = $note;
			} elseif ( '' !== $quand ) {
				$detail = sprintf( /* translators: %s : la date d'envoi. */
					__( 'le %s', 'notice-archeomed' ), $quand );
			}
		} elseif ( 'en_cours' === $etat ) {
			$classe = 'attente';
			$icone  = 'dashicons-update';
			$texte  = __( 'Envoi en cours', 'notice-archeomed' );
		} elseif ( 'echec' === $etat ) {
			$classe = 'echec';
			$icone  = 'dashicons-warning';
			$texte  = __( 'Échec de l’envoi — à relancer', 'notice-archeomed' );
			$detail = self::expliquer_l_erreur( (string) get_post_meta( $id, '_na_erreur', true ) );
		} else {
			$classe = 'attente';
			$icone  = 'dashicons-clock';
			$texte  = __( 'En file d’envoi', 'notice-archeomed' );
			$essais = (int) get_post_meta( $id, '_na_essais', true );
			if ( $essais > 0 ) {
				$detail = sprintf(
					/* translators: 1 : essais ratés ; 2 : essais permis. */
					__( 'essai %1$d sur %2$d raté', 'notice-archeomed' ), $essais, self::ESSAIS_MAX );
				$suivant = wp_next_scheduled( self::HOOK_UNE, array( $id ) );
				if ( $suivant ) {
					$detail .= ' — ' . sprintf(
						/* translators: %s : l'heure du prochain essai. */
						__( 'nouvel essai vers %s', 'notice-archeomed' ), self::heure_lisible( $suivant ) );
				}
			}
		}
		// L'encart de suivi de la fiche se tait sur la raison d'un échec : le
		// bandeau d'en tête la donne déjà, et la lire deux fois l'une sous
		// l'autre ne l'éclaire pas.
		if ( $bref && 'echec' === $etat ) {
			$detail = '';
		}
		if ( $pour_un_courriel ) {
			return $texte . ( '' !== $detail ? ' (' . $detail . ')' : '' );
		}
		return '<span class="na-etat na-etat--' . esc_attr( $classe ) . '">'
			. '<span class="dashicons ' . esc_attr( $icone ) . '" aria-hidden="true"></span> '
			. esc_html( $texte ) . '</span>'
			. ( '' !== $detail ? '<br><span class="description">' . esc_html( $detail ) . '</span>' : '' );
	}

	/**
	 * Ce qu'une réponse du serveur de courriel veut dire, et ce qu'on y peut.
	 *
	 * « SMTP Error: Could not authenticate. » ne dit rien à qui ne connaît
	 * pas PHPMailer, et c'est pourtant ce qu'affichaient la liste et la
	 * fiche. On le traduit ici pour l'affichage seulement : la réponse brute
	 * reste en base, et la fiche la montre telle quelle dans son encart de
	 * dépannage — c'est elle qu'on donne à l'hébergeur.
	 */
	public static function expliquer_l_erreur( $brut ) {
		$connue = self::explication_connue( $brut );
		return '' !== $connue ? $connue
			: __( 'Le serveur de courriel a refusé l’envoi. Ouvrez la fiche et lancez le diagnostic.', 'notice-archeomed' );
	}

	/** L'explication d'une réponse qu'on sait reconnaître, ou rien. */
	private static function explication_connue( $brut ) {
		$brut = (string) $brut;
		if ( '' === trim( $brut ) ) {
			return '';
		}
		// L'ordre compte : « Aucun destinataire » d'abord, qui vient du
		// plugin et non du serveur ; l'authentification avant la connexion,
		// dont elle partage des mots.
		$cas = array(
			array( array( 'Aucun destinataire' ),
				__( 'Personne ne reçoit les notices : cochez « Chaque notice » pour au moins une adresse dans les réglages.', 'notice-archeomed' ) ),
			array( array( 'instantiate mail', 'instancier' ),
				__( 'Le serveur du site ne sait pas envoyer de courriel, ou a refusé ce courriel (souvent trop lourd). Baissez « Poids maximal d’un courriel » dans les réglages, ou configurez un relais.', 'notice-archeomed' ) ),
			array( array( 'Could not authenticate', 'authentification' ),
				__( 'Le relais de courriel a refusé l’identifiant ou le mot de passe.', 'notice-archeomed' ) ),
			array( array( 'connect', 'timed out', 'refused' ),
				__( 'Le relais de courriel ne répond pas.', 'notice-archeomed' ) ),
			array( array( '552', 'size', 'exceeds' ),
				__( 'Le courriel était trop lourd pour le serveur. Baissez « Poids maximal d’un courriel », puis relancez.', 'notice-archeomed' ) ),
			array( array( 'Invalid address' ),
				__( 'Une adresse de destinataire ou d’expéditeur est refusée.', 'notice-archeomed' ) ),
		);
		foreach ( $cas as $un ) {
			foreach ( $un[0] as $signe ) {
				if ( false !== stripos( $brut, $signe ) ) {
					return $un[1];
				}
			}
		}
		return '';
	}

	/** Les comptes par vue, lus une fois par page. */
	private $comptes = null;

	/**
	 * Combien de notices dans chaque vue : envoyées, en file, en échec, et à
	 * traiter. Une requête groupée, et non quatre : elle sert aux vues de la
	 * liste et au bandeau d'échec, sur des pages qu'on ouvre souvent.
	 */
	private function compter_les_etats( $relire = false ) {
		if ( null !== $this->comptes && ! $relire ) {
			return $this->comptes;
		}
		global $wpdb;
		$rangs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.meta_value AS etat, COUNT(*) AS n,
				        SUM( CASE WHEN e.meta_value IS NOT NULL AND e.meta_value NOT IN ( '', '0' )
				                  THEN 1 ELSE 0 END ) AS refus
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_na_etat'
				 LEFT JOIN {$wpdb->postmeta} e ON e.post_id = p.ID AND e.meta_key = '_na_essais'
				 WHERE p.post_type = %s AND p.post_status = %s
				 GROUP BY m.meta_value",
				self::CPT,
				'private'
			),
			ARRAY_A
		);
		$comptes = array( 'a_traiter' => 0, 'echec' => 0, 'file' => 0, 'envoyees' => 0 );
		foreach ( (array) $rangs as $rang ) {
			$n = (int) $rang['n'];
			if ( 'envoyee' === $rang['etat'] ) {
				$comptes['envoyees'] += $n;
			} elseif ( 'echec' === $rang['etat'] ) {
				$comptes['echec']     += $n;
				$comptes['a_traiter'] += $n;
			} elseif ( in_array( $rang['etat'], array( 'en_attente', 'en_cours' ), true ) ) {
				$comptes['file']      += $n;
				$comptes['a_traiter'] += (int) $rang['refus'];
			}
		}
		$this->comptes = $comptes;
		return $comptes;
	}

	/**
	 * Les vues de la liste, par état d'envoi plutôt que par statut.
	 *
	 * « Privé » était la seule vue offerte, et elle comptait toutes les
	 * notices : un statut technique, que toutes portent, et qui ne dit rien
	 * de ce qu'il reste à faire. Ce qu'on cherche en ouvrant la liste, c'est
	 * ce qui n'est pas parti.
	 */
	public function vues_par_etat( $vues ) {
		unset( $vues['private'] );
		$comptes  = $this->compter_les_etats();
		$demandee = self::etat_demande();
		$libelles = array(
			'a_traiter' => __( 'À traiter', 'notice-archeomed' ),
			'echec'     => __( 'En échec', 'notice-archeomed' ),
			'file'      => __( 'En file d’envoi', 'notice-archeomed' ),
			'envoyees'  => __( 'Envoyées', 'notice-archeomed' ),
		);
		$etats = array();
		foreach ( $libelles as $vue => $libelle ) {
			// « À traiter (0) » se montre toujours : c'est la réponse qu'on
			// vient chercher. Les autres vues vides se taisent.
			if ( 0 === $comptes[ $vue ] && 'a_traiter' !== $vue && $demandee !== $vue ) {
				continue;
			}
			$url = add_query_arg( array( 'post_type' => self::CPT, 'na_etat' => $vue ),
				admin_url( 'edit.php' ) );
			$etats[ 'na_' . $vue ] = '<a href="' . esc_url( $url ) . '"'
				. ( $demandee === $vue ? ' class="current" aria-current="page"' : '' ) . '>'
				. esc_html( $libelle ) . ' <span class="count">('
				. esc_html( number_format_i18n( $comptes[ $vue ] ) ) . ')</span></a>';
		}
		if ( ! isset( $vues['all'] ) ) {
			return $etats + $vues;
		}
		$nouvelles = array();
		foreach ( $vues as $clef => $vue ) {
			$nouvelles[ $clef ] = $vue;
			if ( 'all' === $clef ) {
				$nouvelles += $etats;
			}
		}
		return $nouvelles;
	}

	/**
	 * La liste vide parle de ce qui viendra, non d'une recherche ratée.
	 *
	 * WordPress n'a qu'un message, « Aucune notice ne correspond », qui
	 * laissait croire, le premier jour d'une campagne, qu'on avait mal
	 * cherché ou que les notices s'étaient perdues.
	 */
	public function preparer_la_liste() {
		$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		if ( self::CPT !== $type ) {
			return;
		}
		$total = 0;
		foreach ( (array) wp_count_posts( self::CPT ) as $statut => $nombre ) {
			if ( ! in_array( $statut, array( 'trash', 'auto-draft' ), true ) ) {
				$total += (int) $nombre;
			}
		}
		$objet = get_post_type_object( self::CPT );
		if ( 0 === $total && $objet ) {
			$objet->labels->not_found = __( 'Aucune notice reçue pour l’instant. Chaque dépôt fait par le formulaire paraîtra ici, avec l’état de son envoi à la rédaction.', 'notice-archeomed' );
		}
	}

	/**
	 * Les actions d'une ligne : ouvrir la fiche, prendre le document.
	 *
	 * La modification rapide offrait de changer le statut d'une notice :
	 * passée en brouillon, elle quittait la file et les fascicules, sans
	 * qu'aucun écran ne le dise. « Modifier » promettait une rédaction qui
	 * n'existe pas ; on ouvre une fiche, on ne la modifie pas.
	 */
	public function actions_de_ligne( $actions, $post ) {
		if ( ! $post || self::CPT !== $post->post_type ) {
			return $actions;
		}
		unset( $actions['inline hide-if-no-js'] );
		$titre = get_the_title( $post );
		if ( isset( $actions['edit'] ) ) {
			$actions['edit'] = '<a href="' . esc_url( get_edit_post_link( $post->ID, '' ) ) . '" aria-label="'
				. esc_attr( sprintf( /* translators: %s : le titre de la notice. */
					__( 'Ouvrir la fiche de « %s »', 'notice-archeomed' ), $titre ) ) . '">'
				. esc_html__( 'Ouvrir la fiche', 'notice-archeomed' ) . '</a>';
		}
		if ( 'trash' === $post->post_status || ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}
		$ajouts = array();
		if ( $this->a_un_document( $post->ID ) ) {
			$ajouts['na_word'] = '<a href="' . esc_url( $this->url_du_document( $post->ID ) ) . '" aria-label="'
				. esc_attr( sprintf( /* translators: %s : le titre de la notice. */
					__( 'Télécharger « %s » en Word', 'notice-archeomed' ), $titre ) ) . '">'
				. esc_html__( 'Télécharger (Word)', 'notice-archeomed' ) . '</a>';
		}
		// L'état disait « à relancer », et il fallait ouvrir la fiche pour le
		// faire. Rien n'est offert sans destinataire : la relance échouerait.
		$etat = (string) get_post_meta( $post->ID, '_na_etat', true );
		if ( in_array( $etat, array( 'echec', 'en_attente' ), true ) && self::quelqu_un_recoit_les_notices() ) {
			$ajouts['na_relancer'] = '<a href="' . esc_url( $this->url_de_relance( $post->ID ) ) . '" aria-label="'
				. esc_attr( sprintf( /* translators: %s : le titre de la notice. */
					__( 'Relancer l’envoi de « %s »', 'notice-archeomed' ), $titre ) ) . '">'
				. esc_html__( 'Relancer l’envoi', 'notice-archeomed' ) . '</a>';
		}
		if ( empty( $ajouts ) ) {
			return $actions;
		}
		$nouvelles = array();
		$poses     = false;
		foreach ( $actions as $clef => $action ) {
			$nouvelles[ $clef ] = $action;
			if ( 'edit' === $clef ) {
				$nouvelles += $ajouts;
				$poses      = true;
			}
		}
		return $poses ? $nouvelles : $ajouts + $nouvelles;
	}

	/** « Modifier » en masse ouvrait la même modification rapide, pour dix notices à la fois. */
	public function actions_groupees( $actions ) {
		unset( $actions['edit'] );
		return $actions;
	}

	/**
	 * « — Privé » après chaque titre : toutes les notices le sont, et le
	 * mot laissait croire à une notice qu'on aurait cachée.
	 */
	public function etats_affiches( $etats, $post ) {
		if ( $post && self::CPT === $post->post_type ) {
			unset( $etats['private'] );
		}
		return $etats;
	}

	/**
	 * Une notice restaurée revient privée.
	 *
	 * WordPress rend un brouillon à qui sort de la corbeille ; or la file,
	 * les fascicules et les dossiers ne lisent que les notices privées. La
	 * notice restaurée reparaissait dans la liste, et nulle part ailleurs.
	 */
	public function statut_a_la_restauration( $statut, $id ) {
		return self::CPT === get_post_type( $id ) ? 'private' : $statut;
	}

	/**
	 * Une notice est privée, à la corbeille, ou en cours de création : rien
	 * d'autre. Le dernier rempart, si un chemin oublié ici — une extension,
	 * un appel d'API — tentait de la publier ou d'en faire un brouillon.
	 */
	public function garder_privee( $donnees, $brut ) {
		if ( isset( $donnees['post_type'], $donnees['post_status'] ) && self::CPT === $donnees['post_type']
			&& ! in_array( $donnees['post_status'], array( 'private', 'trash', 'auto-draft' ), true ) ) {
			$donnees['post_status'] = 'private';
		}
		return $donnees;
	}

	/** L'écran courant est-il la liste, une fiche ou les réglages de l'extension ? */
	private function est_un_ecran_de_l_extension( $ecran ) {
		return $ecran && ( self::CPT === $ecran->post_type
			|| false !== strpos( (string) $ecran->id, Notice_Archeomed_Settings::PAGE_SLUG ) );
	}

	/**
	 * Les couleurs des états, sur les seuls écrans de l'extension.
	 *
	 * Vert, ambre, rouge, assez sombres pour se lire sur le gris des lignes
	 * alternées et sur le rose d'un bandeau d'erreur : ceux de WordPress
	 * (#008a20, #d63638) y tombaient sous 4,5:1. Ils ne portent jamais seuls
	 * le sens — chaque état a son icône et son mot.
	 *
	 * Sur un téléphone, WordPress ne montre d'une ligne que son titre : l'état
	 * de l'envoi, qui est ce qu'on vient chercher, se dépliait à la main. Il
	 * reste visible sous le titre.
	 */
	public function poser_les_styles() {
		$ecran = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $this->est_un_ecran_de_l_extension( $ecran ) ) {
			return;
		}
		echo '<style>'
			. '.na-etat{font-weight:600}'
			. '.na-etat .dashicons{font-size:18px;width:18px;height:18px;vertical-align:text-bottom}'
			. '.na-etat--ok{color:#007017}.na-etat--attente{color:#825900}.na-etat--echec{color:#b32d2e}'
			// L'encart de suivi est étroit : l'adresse insécable et l'intitulé
			// en gras le faisaient déborder de vingt pixels.
			. '#na_suivi .widefat th{width:6.5em;white-space:normal;font-weight:600}'
			. '#na_suivi .widefat td{overflow-wrap:anywhere}'
			. '@media screen and (max-width:782px){'
			. 'body.post-type-na_notice .wp-list-table tr:not(.inline-edit-row):not(.no-items) .column-primary~td.column-na_etat{display:block}}'
			. '#poststuff h2.na-titre-fiche{font-size:1.5em;line-height:1.3;margin:.2em 0 .6em;padding:0}'
			. '.na-bandeau>p:first-child{font-size:1.1em}'
			. '.fixed .column-na_etat{width:17em}.fixed .column-na_recue{width:11em}'
			. '</style>';
	}

	/**
	 * L'attente d'un fascicule ou d'un dossier, dite à l'écran.
	 *
	 * Un dossier Métopes se prépare en une à trois minutes, sans que rien ne
	 * bouge : on croyait le clic perdu, on recliquait, et le serveur
	 * assemblait deux fois le même dossier — assez pour épuiser un
	 * hébergement mutualisé. Le lien se marque occupé, et le second clic
	 * n'a plus d'effet.
	 */
	public function poser_le_script_des_dossiers() {
		$ecran = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $ecran || 'edit-' . self::CPT !== $ecran->id ) {
			return;
		}
		?>
		<script>
		(function () {
			var bloc = document.querySelector('li.na_dossiers');
			if (!bloc) { return; }
			var etat = bloc.querySelector('.na-attente');
			var texte = <?php echo wp_json_encode( __( 'Préparation… une à trois minutes. Le téléchargement démarrera seul ; ne cliquez qu’une fois.', 'notice-archeomed' ) ); ?>;
			bloc.addEventListener('click', function (e) {
				var lien = e.target.closest('a.na-telechargement');
				if (!lien) { return; }
				if (lien.getAttribute('aria-busy') === 'true') {
					e.preventDefault();
					return;
				}
				lien.setAttribute('aria-busy', 'true');
				var roue = document.createElement('span');
				roue.className = 'spinner is-active';
				roue.style.cssText = 'float:none;margin:0 4px;vertical-align:middle';
				lien.parentNode.insertBefore(roue, lien.nextSibling);
				if (etat) { etat.textContent = texte; }
				// Aucun signal ne dit qu'un téléchargement est fini : on rend
				// la main passé le délai où le serveur lève son propre verrou.
				window.setTimeout(function () {
					lien.removeAttribute('aria-busy');
					if (roue.parentNode) { roue.parentNode.removeChild(roue); }
					if (etat && !bloc.querySelector('a[aria-busy="true"]')) { etat.textContent = ''; }
				}, 360000);
			});
		}());
		</script>
		<?php
	}

	/** Quelqu'un est-il inscrit pour recevoir chaque notice ? */
	public static function quelqu_un_recoit_les_notices() {
		return ! empty( Notice_Archeomed_Settings::destinataires_de( 'notices' ) );
	}

	/**
	 * Sans destinataire, aucune notice ne part : un seul bandeau, en tête de
	 * toutes les pages, qui dit la cause et ce qu'elle retient.
	 *
	 * Trois bandeaux s'empilaient — « 1 notice n'a pas pu être envoyée »,
	 * « 2 notices attendent… Les envoyer maintenant », puis, en dernier, la
	 * cause. On cliquait sur le deuxième, l'envoi échouait, et rien ne
	 * reliait l'échec à l'adresse manquante.
	 */
	public function signaler_l_adresse_manquante() {
		if ( ! current_user_can( 'manage_options' ) || self::quelqu_un_recoit_les_notices() ) {
			return;
		}
		$comptes  = $this->compter_les_etats();
		$attente  = (int) $comptes['file'];
		$echec    = (int) $comptes['echec'];
		$retenues = array();
		if ( $attente > 0 ) {
			$retenues[] = 1 === $attente
				? __( '1 notice attend', 'notice-archeomed' )
				: sprintf( /* translators: %d : nombre de notices. */
					__( '%d notices attendent', 'notice-archeomed' ), $attente );
		}
		if ( $echec > 0 ) {
			$retenues[] = 1 === $echec
				? __( '1 a échoué', 'notice-archeomed' )
				: sprintf( /* translators: %d : nombre de notices. */
					__( '%d ont échoué', 'notice-archeomed' ), $echec );
		}
		if ( 0 === $attente && $echec > 0 ) {
			$retenues = array( 1 === $echec
				? __( '1 notice a échoué', 'notice-archeomed' )
				: sprintf( /* translators: %d : nombre de notices. */
					__( '%d notices ont échoué', 'notice-archeomed' ), $echec ) );
		}
		$cause = empty( Notice_Archeomed_Settings::destinataires() )
			? __( 'Aucune notice ne peut partir : l’adresse de la rédaction manque.', 'notice-archeomed' )
			: __( 'Aucune notice ne peut partir : aucune adresse n’est cochée pour recevoir chaque notice.', 'notice-archeomed' );
		$suite = empty( $retenues )
			? __( 'Les notices déposées seront conservées en attendant.', 'notice-archeomed' )
			: sprintf( /* translators: 1 : « 2 notices attendent et 1 a échoué » ; 2 : « elle sera » ou « elles seront ». */
				__( '%1$s ; %2$s à envoyer une fois l’adresse enregistrée.', 'notice-archeomed' ),
				implode( __( ' et ', 'notice-archeomed' ), $retenues ),
				// Une seule notice en tout : « elle sera », et non « elles seront ».
				1 === $attente + $echec ? __( 'elle sera', 'notice-archeomed' ) : __( 'elles seront', 'notice-archeomed' ) );
		echo '<div class="notice notice-error"><p><strong>'
			. esc_html__( 'Chronique :', 'notice-archeomed' ) . '</strong> '
			. esc_html( $cause . ' ' . $suite )
			. ' <a href="' . esc_url( Notice_Archeomed_Settings::url( 'destinataires' ) ) . '">'
			. esc_html__( 'Renseigner l’adresse', 'notice-archeomed' ) . '</a></p></div>';
	}

	/**
	 * Des notices en échec, dites où on les verra : sur le tableau de bord
	 * et sur les écrans de l'extension.
	 *
	 * Une notice en échec ne repart plus seule ; rien ne l'annonçait hors de
	 * la liste, et l'on pouvait rester une semaine sans l'ouvrir.
	 */
	public function signaler_les_echecs() {
		// Sans destinataire, le bandeau de l'adresse porte déjà ce compte.
		if ( ! current_user_can( 'manage_options' ) || ! self::quelqu_un_recoit_les_notices() ) {
			return;
		}
		$ecran = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $ecran || ( 'dashboard' !== $ecran->id && ! $this->est_un_ecran_de_l_extension( $ecran ) ) ) {
			return;
		}
		$comptes = $this->compter_les_etats();
		$n       = (int) $comptes['echec'];
		if ( $n < 1 ) {
			return;
		}
		$lien = add_query_arg( array( 'post_type' => self::CPT, 'na_etat' => 'echec' ),
			admin_url( 'edit.php' ) );
		echo '<div class="notice notice-error"><p><strong>'
			. esc_html__( 'Chronique :', 'notice-archeomed' ) . '</strong> '
			. esc_html( 1 === $n
				? __( '1 notice n’a pas pu être envoyée à la rédaction.', 'notice-archeomed' )
				: sprintf( /* translators: %d : nombre de notices. */
					__( '%d notices n’ont pas pu être envoyées à la rédaction.', 'notice-archeomed' ), $n ) )
			. ' <a href="' . esc_url( $lien ) . '">'
			. esc_html__( 'Voir les notices en échec', 'notice-archeomed' ) . '</a></p></div>';
	}

	/**
	 * Une file qui s'allonge est le signe que le planificateur ne tourne pas.
	 * Mieux vaut le voir dans l'administration que de le découvrir en
	 * cherchant pourquoi la rédaction ne reçoit plus rien.
	 *
	 * À cinq notices, comme avant ; mais aussi pour une seule qui attend
	 * depuis une demi-heure sans avoir jamais été essayée. Une campagne qui
	 * démarre doucement n'atteignait jamais cinq, et la première notice
	 * pouvait attendre des jours sans que rien ne le dise.
	 */
	public function signaler_les_retards() {
		// « Les envoyer maintenant » échouerait tant que personne ne les
		// reçoit : le bandeau de l'adresse le dit à sa place.
		if ( ! current_user_can( 'manage_options' ) || ! self::quelqu_un_recoit_les_notices() ) {
			return;
		}
		$attente = $this->en_attente( 50 );
		if ( empty( $attente ) ) {
			return;
		}
		update_meta_cache( 'post', $attente );
		$oubliee = false;
		foreach ( $attente as $id ) {
			if ( 0 === (int) get_post_meta( $id, '_na_essais', true )
				&& time() - (int) get_post_time( 'U', true, $id ) > 30 * MINUTE_IN_SECONDS ) {
				$oubliee = true;
				break;
			}
		}
		if ( count( $attente ) < 5 && ! $oubliee ) {
			return;
		}
		$n      = count( $attente );
		$depuis = human_time_diff( (int) get_post_time( 'U', true, $attente[0] ), time() );
		$lien   = wp_nonce_url(
			admin_url( 'admin-post.php?action=na_expedier_maintenant' ),
			'na_expedier_maintenant'
		);
		echo '<div class="notice notice-warning"><p><strong>'
			. esc_html__( 'Chronique :', 'notice-archeomed' ) . '</strong> '
			. esc_html( 1 === $n
				? sprintf( /* translators: %s : durée, « 2 heures ». */
					__( '1 notice attend son envoi à la rédaction depuis %s.', 'notice-archeomed' ), $depuis )
				: sprintf( /* translators: 1 : nombre de notices ; 2 : durée, « 2 heures ». */
					__( '%1$d notices attendent leur envoi à la rédaction, la plus ancienne depuis %2$s.', 'notice-archeomed' ),
					$n, $depuis ) )
			. ' <a href="' . esc_url( $lien ) . '">'
			. esc_html( 1 === $n ? __( 'L’envoyer maintenant', 'notice-archeomed' )
				: __( 'Les envoyer maintenant', 'notice-archeomed' ) ) . '</a></p></div>';
	}

	/**
	 * Ce qu'a donné le geste qu'on vient de faire : relancer une notice,
	 * vider la file, diagnostiquer.
	 */
	public function rendre_compte() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$ecran = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $ecran || self::CPT !== $ecran->post_type ) {
			return;
		}
		if ( 'post' === $ecran->base && isset( $_GET['na_relancee'] ) ) {
			$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
			$this->dire_l_issue( $id, sanitize_key( wp_unslash( $_GET['na_relancee'] ) ) );
		}
		if ( 'post' === $ecran->base && isset( $_GET['na_diagnostic'] ) ) {
			self::avis( 'info', __( 'Le diagnostic est fait : ses résultats sont dans l’encart « Dépannage », plus bas.', 'notice-archeomed' ) );
		}
		if ( 'edit' === $ecran->base && isset( $_GET['na_parties'] ) ) {
			$parties = (int) $_GET['na_parties'];
			$restent = isset( $_GET['na_restent'] ) ? (int) $_GET['na_restent'] : 0;
			$refus   = isset( $_GET['na_refus'] ) ? (int) $_GET['na_refus'] : 0;
			$message = sprintf( '%s, %s.',
				sprintf( $parties > 1
					? /* translators: %d : nombre. */ __( '%d notices envoyées', 'notice-archeomed' )
					: /* translators: %d : nombre. */ __( '%d notice envoyée', 'notice-archeomed' ), $parties ),
				sprintf( $restent > 1
					? /* translators: %d : nombre. */ __( '%d restent en file', 'notice-archeomed' )
					: /* translators: %d : nombre. */ __( '%d reste en file', 'notice-archeomed' ), $restent ) );
			if ( $refus > 0 ) {
				$message .= ' ' . sprintf( $refus > 1
					? /* translators: %d : nombre. */ __( '%d ont été refusées par le serveur de courriel : voyez la vue « À traiter ».', 'notice-archeomed' )
					: /* translators: %d : nombre. */ __( '%d a été refusée par le serveur de courriel : voyez la vue « À traiter ».', 'notice-archeomed' ), $refus );
			}
			self::avis( $refus > 0 ? 'warning' : 'success', $message );
		}
	}

	/** L'issue d'une relance, dite en une phrase. */
	private function dire_l_issue( $id, $issue ) {
		if ( 'partie' === $issue ) {
			self::avis( 'success', __( 'La notice est partie à la rédaction.', 'notice-archeomed' ) );
		} elseif ( 'en_preparation' === $issue ) {
			self::avis( 'info', __( 'Les versions allégées des figures sont en préparation ; l’envoi suivra de lui-même dans quelques minutes.', 'notice-archeomed' ) );
		} elseif ( 'occupee' === $issue ) {
			self::avis( 'warning', __( 'Un envoi de cette notice est déjà en cours. Rechargez la page dans une minute.', 'notice-archeomed' ) );
		} elseif ( 'refusee' === $issue ) {
			$brut    = (string) get_post_meta( $id, '_na_erreur', true );
			$connue  = self::explication_connue( $brut );
			// Sans destinataire, rien n'a été tenté : dire que le serveur a
			// refusé enverrait chercher la panne au mauvais endroit.
			$message = ( false !== stripos( $brut, 'Aucun destinataire' ) )
				? __( 'La notice n’est pas partie.', 'notice-archeomed' )
				: __( 'Le serveur de courriel a refusé l’envoi.', 'notice-archeomed' );
			$message .= ' ' . ( '' !== $connue
				? sprintf( /* translators: %s : l'explication. */ __( 'Raison : %s', 'notice-archeomed' ), $connue )
				: __( 'Ouvrez l’encart « Dépannage », plus bas, et lancez le diagnostic.', 'notice-archeomed' ) );
			$suivant = wp_next_scheduled( self::HOOK_UNE, array( (int) $id ) );
			if ( $suivant && 'echec' !== (string) get_post_meta( $id, '_na_etat', true ) ) {
				$message .= ' ' . sprintf( /* translators: %s : une heure, « 14 h 22 ». */
					__( 'Un nouvel essai se fera de lui-même vers %s.', 'notice-archeomed' ), self::heure_lisible( $suivant ) );
			}
			self::avis( 'error', $message );
		}
	}

	/** Un avis refermable en tête de page. */
	private static function avis( $type, $message ) {
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>'
			. esc_html( $message ) . '</p></div>';
	}

	/**
	 * Les paramètres de retour s'effacent de l'adresse une fois lus : sans
	 * cela, recharger la page redisait « La notice est partie » d'un envoi
	 * vieux d'une heure.
	 */
	public function arguments_a_effacer( $arguments ) {
		return array_merge( (array) $arguments,
			array( 'na_relancee', 'na_diagnostic', 'na_parties', 'na_restent', 'na_refus', 'na_essai', 'na_figures', 'na_figures_coupe' ) );
	}

	/**
	 * La relance périodique se pose à l'activation et se retire à l'arrêt.
	 */
	public static function activer() {
		if ( ! wp_next_scheduled( self::HOOK_RELANCE ) ) {
			wp_schedule_event( time() + 300, 'na_cinq_minutes', self::HOOK_RELANCE );
		}
		if ( ! wp_next_scheduled( self::HOOK_RECAP ) ) {
			// Le lendemain à sept heures, heure du site : le récapitulatif
			// attend la rédaction quand elle ouvre sa boîte, non au milieu
			// de la nuit où il se noiera dans le reste.
			$demain = strtotime( 'tomorrow 07:00', current_time( 'timestamp' ) );
			wp_schedule_event( $demain - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ),
				'daily', self::HOOK_RECAP );
		}
	}

	public static function desactiver() {
		foreach ( array( self::HOOK_RELANCE, self::HOOK_RECAP ) as $rendez_vous ) {
			$suivant = wp_next_scheduled( $rendez_vous );
			if ( $suivant ) {
				wp_unschedule_event( $suivant, $rendez_vous );
			}
		}
	}

	/**
	 * WordPress ne connaît pas d'intervalle de cinq minutes ; on le lui donne.
	 */
	public static function ajouter_intervalle( $intervalles ) {
		$intervalles['na_cinq_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Toutes les cinq minutes', 'notice-archeomed' ),
		);
		return $intervalles;
	}
}
