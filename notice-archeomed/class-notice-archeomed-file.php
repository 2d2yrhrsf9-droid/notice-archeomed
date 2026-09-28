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
		add_action( 'admin_notices', array( $this, 'signaler_les_retards' ) );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( $this, 'colonnes' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( $this, 'colonne' ), 10, 2 );
		add_filter( 'manage_edit-' . self::CPT . '_sortable_columns', array( $this, 'colonnes_triables' ) );
		add_action( 'restrict_manage_posts', array( $this, 'filtre_par_rubrique' ) );
		add_filter( 'views_edit-' . self::CPT, array( $this, 'liens_des_fascicules' ) );
		add_action( 'pre_get_posts', array( $this, 'appliquer_le_filtre' ) );
		// La fiche d'une notice : ce qu'on vient y lire, et rien d'autre.
		add_action( 'add_meta_boxes_' . self::CPT, array( $this, 'poser_la_fiche' ) );
		add_action( 'do_meta_boxes', array( $this, 'ecarter_les_intrus' ), 99 );
		add_action( 'admin_post_na_expedier_maintenant', array( $this, 'expedier_maintenant' ) );
		add_action( 'admin_post_na_reessayer', array( $this, 'reessayer' ) );
	}

	/**
	 * Les notices reçues ne sont pas du contenu public : elles ne s'affichent
	 * nulle part, ne s'indexent pas, et ne se voient que de l'administration.
	 */
	/**
	 * Repose les rendez-vous manquants.
	 *
	 * Une mise à jour par téléversement ne repasse pas toujours par
	 * l'activation : sans ce rattrapage, la relance et le récapitulatif
	 * disparaissaient au premier remplacement de l'extension, et personne ne
	 * s'en apercevait avant que la file ne s'allonge.
	 */
	/** Ce que la version en cours attend d'avoir écrit dans chaque notice. */
	const ETAT_DES_DONNEES = 2;

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

	public function verifier_les_rendez_vous() {
		if ( ! wp_next_scheduled( self::HOOK_RELANCE ) || ! wp_next_scheduled( self::HOOK_RECAP ) ) {
			self::activer();
		}
	}

	public function declarer_le_type() {
		$this->verifier_les_rendez_vous();
		if ( is_admin() ) {
			$this->rattraper_les_anciennes();
		}
		register_post_type(
			self::CPT,
			array(
				'labels'              => array(
					'name'          => __( 'Notices reçues', 'notice-archeomed' ),
					'singular_name' => __( 'Notice reçue', 'notice-archeomed' ),
					'menu_name'     => __( 'Notices d’archéologie médiévale', 'notice-archeomed' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-media-document',
				'capability_type'     => 'post',
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
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
		$titre = trim( $donnees['commune'] . ' (' . $donnees['departement'] . ') — ' . $donnees['annee'] );
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
	 * Le bouton « Expédier maintenant » de l'administration, pour le jour où
	 * le planificateur de WordPress est désactivé sur l'hébergement.
	 */
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
		update_post_meta( $id, '_na_essais', 0 );
		update_post_meta( $id, '_na_etat', 'en_attente' );
		delete_post_meta( $id, '_na_erreur' );
		$this->programmer( $id );
		wp_safe_redirect( add_query_arg( 'na_relancee', '1', get_edit_post_link( $id, '' ) ) );
		exit;
	}

	public function expedier_maintenant() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		check_admin_referer( 'na_expedier_maintenant' );
		$this->relancer();
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . self::CPT ) );
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
		$etat = (string) get_post_meta( $id, '_na_etat', true );
		if ( 'en_cours' === $etat ) {
			$depuis = (int) get_post_meta( $id, '_na_prise', true );
			if ( $depuis > time() - self::ABANDON ) {
				return false;   // quelqu'un s'en occupe à l'instant
			}
		} elseif ( 'en_attente' !== $etat ) {
			return false;       // déjà partie, ou renoncée
		}
		update_post_meta( $id, '_na_etat', 'en_cours' );
		update_post_meta( $id, '_na_prise', time() );
		return true;
	}

	/**
	 * La fiche d'une notice : on vient la lire, pas la rédiger.
	 *
	 * L'écran d'édition ne portait que le titre — le reste vit en métadonnées.
	 * Il restait donc vide, et les extensions tierces y déposaient leurs
	 * propres encarts, dont un « Product Review » sans le moindre rapport.
	 */
	public function poser_la_fiche() {
		add_meta_box( 'na_fiche', __( 'La notice', 'notice-archeomed' ),
			array( $this, 'afficher_la_notice' ), self::CPT, 'normal', 'high' );
		add_meta_box( 'na_suivi', __( 'Suivi', 'notice-archeomed' ),
			array( $this, 'afficher_le_suivi' ), self::CPT, 'side', 'high' );
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

	public function afficher_le_suivi( $post ) {
		$lignes = array(
			__( 'État', 'notice-archeomed' )      => (string) get_post_meta( $post->ID, '_na_etat', true ),
			__( 'Référence', 'notice-archeomed' ) => (string) get_post_meta( $post->ID, '_na_reference', true ),
			__( 'Remplace la notice', 'notice-archeomed' ) => (string) get_post_meta( $post->ID, '_na_remplace', true ),
			__( 'Rubrique', 'notice-archeomed' )  => (string) get_post_meta( $post->ID, '_na_rubrique', true ),
			__( 'Responsable', 'notice-archeomed' ) => (string) get_post_meta( $post->ID, '_na_responsable', true ),
			__( 'Courriel', 'notice-archeomed' )  => (string) get_post_meta( $post->ID, '_na_courriel', true ),
			__( 'Reçue le', 'notice-archeomed' )  => get_the_date( 'j F Y à H:i', $post ),
			__( 'Envoyée le', 'notice-archeomed' ) => (string) get_post_meta( $post->ID, '_na_envoyee_le', true ),
			__( 'Tentatives', 'notice-archeomed' ) => (string) (int) get_post_meta( $post->ID, '_na_essais', true ),
		);
		echo '<table class="widefat striped"><tbody>';
		foreach ( $lignes as $intitule => $valeur ) {
			if ( '' === trim( $valeur ) ) {
				continue;
			}
			echo '<tr><td><strong>' . esc_html( $intitule ) . '</strong></td><td>'
				. esc_html( $valeur ) . '</td></tr>';
		}
		echo '</tbody></table>';

		$url = wp_nonce_url(
			add_query_arg(
				array( 'action' => 'na_document', 'post' => (int) $post->ID ),
				admin_url( 'admin-post.php' )
			),
			'na_document_' . (int) $post->ID
		);
		echo '<p style="margin-top:12px"><a class="button button-primary" href="'
			. esc_url( $url ) . '">'
			. esc_html__( 'Télécharger le document', 'notice-archeomed' ) . '</a></p>';
		echo '<p class="description">'
			. esc_html__( 'Le document est refabriqué à partir de la saisie : il ne peut pas se perdre, et suit les évolutions de la feuille de styles.', 'notice-archeomed' )
			. '</p>';

		$erreur = (string) get_post_meta( $post->ID, '_na_erreur', true );
		if ( '' !== $erreur ) {
			echo '<p style="color:#b32d2e"><strong>' . esc_html( $erreur ) . '</strong></p>';
		}
		// Une notice en échec ne repart pas d'elle-même : la relance générale
		// ne reprend que celles en attente. Le bouton la remet dans la file,
		// une fois la cause du refus levée.
		if ( 'echec' === (string) get_post_meta( $post->ID, '_na_etat', true ) ) {
			$relance = wp_nonce_url(
				add_query_arg(
					array( 'action' => 'na_reessayer', 'post' => (int) $post->ID ),
					admin_url( 'admin-post.php' )
				),
				'na_reessayer_' . (int) $post->ID
			);
			echo '<p><a class="button" href="' . esc_url( $relance ) . '">'
				. esc_html__( 'Réessayer l’envoi', 'notice-archeomed' ) . '</a></p>';
			echo '<p class="description">'
				. esc_html__( 'Corrigez d’abord ce que l’erreur signale — le plus souvent une adresse de destinataire. Le compteur repart à zéro.', 'notice-archeomed' )
				. '</p>';
		}

		$this->afficher_les_illustrations( $post );
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
		$gardees = (array) get_post_meta( $post->ID, '_na_illustrations', true );
		$gardees = array_values( array_filter( $gardees, 'file_exists' ) );
		$donnees = get_post_meta( $post->ID, '_na_donnees', true );
		$dites   = ( is_array( $donnees ) && ! empty( $donnees['illustrations'] ) )
			? (array) $donnees['illustrations'] : array();

		echo '<p style="margin-top:14px"><strong>'
			. esc_html__( 'Illustrations', 'notice-archeomed' ) . '</strong></p>';

		if ( empty( $gardees ) ) {
			$anciennes = (array) get_post_meta( $post->ID, '_na_fichiers', true );
			echo '<p class="description">' . esc_html(
				empty( $anciennes )
					? __( 'Aucune illustration reçue.', 'notice-archeomed' )
					: __( 'Reçues avant que le plugin ne les conserve : elles ne sont plus que dans le courriel de la rédaction.', 'notice-archeomed' )
			) . '</p>';
			return;
		}

		$poids = 0;
		echo '<ul style="margin:0">';
		foreach ( $gardees as $rang => $chemin ) {
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
			echo '<li><a href="' . esc_url( $url ) . '">'
				. esc_html( sprintf( 'Fig. %d', $rang + 1 ) ) . '</a>'
				. ( '' !== $titre ? ' — ' . esc_html( $titre ) : '' )
				. ' <span class="description">('
				. esc_html( size_format( filesize( $chemin ) ) ) . ')</span></li>';
		}
		echo '</ul>';
		echo '<p class="description">' . esc_html( sprintf(
			/* translators: %s : poids total, déjà mis en forme. */
			__( '%s sur le serveur. Supprimer la notice les efface aussi.', 'notice-archeomed' ),
			size_format( $poids ) ) ) . '</p>';
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
		$gardes = array( 'na_fiche', 'na_suivi', 'submitdiv' );
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
	 * Les notices d'une rubrique, dans l'ordre où elles seront relues.
	 */
	public function notices_de_la_rubrique( $rubrique ) {
		return get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'private',
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_na_rubrique',
						'value' => $rubrique,
					),
				),
				'meta_key'       => '_na_classement',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Un fascicule par rubrique, à télécharger d'un clic.
	 *
	 * On ne relit pas quarante notices dans quarante courriels : on les ouvre
	 * une à une, on perd le fil, et l'on ne voit ni les doublons ni les
	 * communes qui se suivent mal.
	 */
	public function liens_des_fascicules( $vues ) {
		$rubriques = $this->rubriques_presentes();
		if ( empty( $rubriques ) ) {
			return $vues;
		}
		$liens = array();
		foreach ( $rubriques as $rubrique ) {
			$url = wp_nonce_url(
				add_query_arg(
					array( 'action' => 'na_fascicule', 'rubrique' => rawurlencode( $rubrique ) ),
					admin_url( 'admin-post.php' )
				),
				'na_fascicule'
			);
			// Le numéro suffit à désigner la rubrique, et tient sur une ligne.
			$court = trim( strtok( $rubrique, '.' ) );
			$liens[] = '<a href="' . esc_url( $url ) . '" title="'
				. esc_attr( $rubrique ) . '">' . esc_html( $court ) . '</a>';
		}
		$vues['na_fascicules'] = '<span style="display:block;margin:8px 0 2px">'
			. '<strong>' . esc_html__( 'Fascicule en Word stylé :', 'notice-archeomed' )
			. '</strong> ' . implode( ' · ', $liens ) . '</span>';

		// Le paquet complet, à côté du fascicule seul : le même document, plus
		// les illustrations rangées aux dossiers de Métopes.
		$paquets = array();
		foreach ( $rubriques as $rubrique ) {
			$url = wp_nonce_url(
				add_query_arg(
					array( 'action' => 'na_paquet', 'rubrique' => rawurlencode( $rubrique ) ),
					admin_url( 'admin-post.php' )
				),
				'na_paquet'
			);
			$court = trim( strtok( $rubrique, '.' ) );
			$paquets[] = '<a href="' . esc_url( $url ) . '" title="'
				. esc_attr( $rubrique ) . '">' . esc_html( $court ) . '</a>';
		}
		$vues['na_paquets'] = '<span style="display:block;margin:0 0 8px">'
			. '<strong>' . esc_html__( 'Dossier Métopes (zip) :', 'notice-archeomed' )
			. '</strong> ' . implode( ' · ', $paquets )
			. ' <span class="description">'
			. esc_html__( 'document, illustrations en haute et basse définition, arborescence icono.', 'notice-archeomed' )
			. '</span></span>';
		return $vues;
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
			$rubrique = (string) get_post_meta( $id, '_na_rubrique', true );
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
				$corps .= '<li><a href="' . esc_url( get_edit_post_link( $id, '' ) ) . '">'
					. esc_html( get_the_title( $id ) ) . '</a>'
					. ( '' !== $famille ? ' — ' . esc_html( $famille ) : '' )
					. ' — ' . esc_html( (string) get_post_meta( $id, '_na_responsable', true ) )
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
		if ( '' !== $erreur ) {
			update_post_meta( $id, '_na_erreur', $erreur );
		}
		if ( 'envoyee' === $etat ) {
			update_post_meta( $id, '_na_envoyee_le', current_time( 'mysql' ) );
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
			$nouvelles[ $clef ] = $titre;
			if ( 'title' === $clef ) {
				// L'ordre est celui du travail : de quelle rubrique relève la
				// notice, qui l'a signée, où elle en est. Le numéro de suivi
				// ne sert qu'en cas d'incident : il vient en dernier.
				$nouvelles['na_rubrique']    = __( 'Rubrique', 'notice-archeomed' );
				$nouvelles['na_responsable'] = __( 'Responsable', 'notice-archeomed' );
				$nouvelles['na_etat']        = __( 'État', 'notice-archeomed' );
				$nouvelles['na_reference']   = __( 'Référence', 'notice-archeomed' );
			}
		}
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
		return $colonnes;
	}

	public function filtre_par_rubrique() {
		global $typenow;
		if ( self::CPT !== $typenow ) {
			return;
		}
		$choisie = isset( $_GET['na_rubrique'] )
			? sanitize_text_field( wp_unslash( $_GET['na_rubrique'] ) ) : '';
		$rubriques = $this->rubriques_presentes();
		if ( empty( $rubriques ) ) {
			return;
		}
		echo '<select name="na_rubrique"><option value="">'
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
	 */
	private function rubriques_presentes() {
		global $wpdb;
		$valeurs = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} m
				 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				 WHERE m.meta_key = %s AND p.post_type = %s AND m.meta_value <> ''
				 ORDER BY meta_value ASC",
				'_na_rubrique',
				self::CPT
			)
		);
		return is_array( $valeurs ) ? $valeurs : array();
	}

	public function appliquer_le_filtre( $requete ) {
		if ( ! is_admin() || ! $requete->is_main_query() ) {
			return;
		}
		if ( self::CPT !== $requete->get( 'post_type' ) ) {
			return;
		}
		$choisie = isset( $_GET['na_rubrique'] )
			? sanitize_text_field( wp_unslash( $_GET['na_rubrique'] ) ) : '';
		if ( '' !== $choisie ) {
			$requete->set( 'meta_query', array(
				array( 'key' => '_na_rubrique', 'value' => $choisie ),
			) );
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
			echo esc_html( $this->rattraper( $id, '_na_rubrique',
				array( 'rubrique_principale' ) ) );
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
		if ( 'na_etat' !== $colonne ) {
			return;
		}
		$etat = (string) get_post_meta( $id, '_na_etat', true );
		$mots = array(
			'en_attente' => __( 'en attente', 'notice-archeomed' ),
			'en_cours'   => __( 'en cours d’envoi', 'notice-archeomed' ),
			'envoyee'    => __( 'envoyée', 'notice-archeomed' ),
			'echec'      => __( 'en échec', 'notice-archeomed' ),
		);
		$mot = isset( $mots[ $etat ] ) ? $mots[ $etat ] : $etat;
		if ( 'echec' === $etat ) {
			$erreur = (string) get_post_meta( $id, '_na_erreur', true );
			echo '<strong style="color:#b32d2e">' . esc_html( $mot ) . '</strong>';
			if ( '' !== $erreur ) {
				echo '<br><span class="description">' . esc_html( $erreur ) . '</span>';
			}
			return;
		}
		echo esc_html( $mot );
	}

	/**
	 * Une file qui s'allonge est le signe que le planificateur ne tourne pas.
	 * Mieux vaut le voir dans l'administration que de le découvrir en
	 * cherchant pourquoi la rédaction ne reçoit plus rien.
	 */
	public function signaler_les_retards() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$attente = $this->en_attente( 50 );
		if ( count( $attente ) < 5 ) {
			return;
		}
		$lien = wp_nonce_url(
			admin_url( 'admin-post.php?action=na_expedier_maintenant' ),
			'na_expedier_maintenant'
		);
		echo '<div class="notice notice-warning"><p><strong>Formulaire des notices d’archéologie médiévale :</strong> '
			. esc_html(
				sprintf(
					/* translators: %d : nombre de notices en attente. */
					__( '%d notices attendent d’être expédiées. Si le nombre ne baisse pas, le planificateur de WordPress est probablement désactivé sur cet hébergement.', 'notice-archeomed' ),
					count( $attente )
				)
			)
			. ' <a href="' . esc_url( $lien ) . '">'
			. esc_html__( 'Expédier maintenant', 'notice-archeomed' ) . '</a></p></div>';
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
