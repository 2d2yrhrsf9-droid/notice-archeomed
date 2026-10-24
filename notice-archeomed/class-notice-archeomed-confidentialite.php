<?php
/**
 * Les données personnelles des auteurs, vues par les outils de WordPress.
 *
 * Une notice porte des noms, des institutions et des adresses de courriel.
 * WordPress sait exporter et effacer les données d'une personne, à sa
 * demande, à condition que l'extension le lui apprenne : c'est ici.
 *
 * L'effacement ne supprime rien. Une notice est une archive éditoriale,
 * publiée ou à publier ; l'ôter à la demande d'un co-auteur est une décision
 * de la rédaction, non d'un bouton. L'outil le dit et laisse les notices.
 * L'exportation, elle, rend à la personne ce qu'on a d'elle.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Confidentialite {

	/** Combien de notices un passage de l'exportation lit. */
	const PAR_PAGE = 50;

	/** Les rôles de la saisie, avec les champs qui les portent. */
	const ROLES = array(
		'Responsable d’opération' => array( 'resp_prenom', 'resp_nom', 'resp_email', 'resp_inst' ),
		'Co-responsable'          => array( 'coresp_prenom', 'coresp_nom', 'coresp_email', 'coresp_inst' ),
		'Co-auteur'               => array( 'coauteur_prenom', 'coauteur_nom', 'coauteur_email', 'coauteur_inst' ),
	);

	public function __construct() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'declarer_l_exportation' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'declarer_l_effacement' ) );
		add_action( 'admin_init', array( $this, 'proposer_le_texte' ) );
	}

	public function declarer_l_exportation( $exportateurs ) {
		$exportateurs['notice-archeomed'] = array(
			'exporter_friendly_name' => __( 'Notices d’archéologie médiévale', 'notice-archeomed' ),
			'callback'               => array( $this, 'exporter' ),
		);
		return $exportateurs;
	}

	public function declarer_l_effacement( $effaceurs ) {
		$effaceurs['notice-archeomed'] = array(
			'eraser_friendly_name' => __( 'Notices d’archéologie médiévale', 'notice-archeomed' ),
			'callback'             => array( $this, 'effacer' ),
		);
		return $effaceurs;
	}

	/**
	 * Les notices où figure l'adresse, avec le rôle de la personne : celle du
	 * responsable, du co-responsable ou du co-auteur.
	 */
	public function notices_de( $courriel, $page = 1 ) {
		$courriel = strtolower( trim( (string) $courriel ) );
		$trouvees = array();
		if ( '' === $courriel ) {
			return array( $trouvees, true );
		}
		$ids = get_posts( array(
			'post_type'      => Notice_Archeomed_File::CPT,
			'post_status'    => 'any',
			'posts_per_page' => self::PAR_PAGE,
			'paged'          => max( 1, (int) $page ),
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
		) );
		foreach ( $ids as $id ) {
			$donnees = get_post_meta( $id, '_na_donnees', true );
			if ( ! is_array( $donnees ) ) {
				continue;
			}
			foreach ( self::ROLES as $role => $champs ) {
				if ( isset( $donnees[ $champs[2] ] ) && strtolower( trim( (string) $donnees[ $champs[2] ] ) ) === $courriel ) {
					$trouvees[] = array( 'id' => (int) $id, 'role' => $role, 'champs' => $champs, 'donnees' => $donnees );
				}
			}
		}
		return array( $trouvees, count( $ids ) < self::PAR_PAGE );
	}

	/** L'exportation que WordPress demande, page après page. */
	public function exporter( $courriel, $page = 1 ) {
		list( $trouvees, $fini ) = $this->notices_de( $courriel, $page );
		$export = array();
		foreach ( $trouvees as $t ) {
			$d    = $t['donnees'];
			$ligne = function ( $cle ) use ( $d ) {
				return isset( $d[ $cle ] ) && is_scalar( $d[ $cle ] ) ? (string) $d[ $cle ] : '';
			};
			$export[] = array(
				'group_id'    => 'na_notices',
				'group_label' => __( 'Notices déposées', 'notice-archeomed' ),
				'item_id'     => 'na-notice-' . $t['id'] . '-' . sanitize_key( $t['role'] ),
				'data'        => array(
					array( 'name' => __( 'Notice', 'notice-archeomed' ), 'value' => get_the_title( $t['id'] ) ),
					array( 'name' => __( 'Référence', 'notice-archeomed' ), 'value' => (string) get_post_meta( $t['id'], '_na_reference', true ) ),
					array( 'name' => __( 'Rôle', 'notice-archeomed' ), 'value' => $t['role'] ),
					array( 'name' => __( 'Prénom', 'notice-archeomed' ), 'value' => $ligne( $t['champs'][0] ) ),
					array( 'name' => __( 'Nom', 'notice-archeomed' ), 'value' => $ligne( $t['champs'][1] ) ),
					array( 'name' => __( 'Adresse de courriel', 'notice-archeomed' ), 'value' => $ligne( $t['champs'][2] ) ),
					array( 'name' => __( 'Institution', 'notice-archeomed' ), 'value' => $ligne( $t['champs'][3] ) ),
					array( 'name' => __( 'Reçue le', 'notice-archeomed' ), 'value' => get_the_date( 'Y-m-d', $t['id'] ) ),
				),
			);
		}
		return array( 'data' => $export, 'done' => $fini );
	}

	/**
	 * Aucune notice n'est effacée par l'outil de WordPress : on le dit, avec
	 * ce qu'il reste à faire à la rédaction.
	 */
	public function effacer( $courriel, $page = 1 ) {
		list( $trouvees, $fini ) = $this->notices_de( $courriel, $page );
		$messages = array();
		if ( ! empty( $trouvees ) ) {
			$messages[] = sprintf(
				/* translators: %d : nombre de notices */
				_n( '%d notice porte cette adresse. Les notices sont des archives éditoriales : elles ne sont pas effacées par cet outil. La rédaction décide de leur anonymisation ou de leur suppression, depuis la liste des notices.',
					'%d notices portent cette adresse. Les notices sont des archives éditoriales : elles ne sont pas effacées par cet outil. La rédaction décide de leur anonymisation ou de leur suppression, depuis la liste des notices.',
					count( $trouvees ), 'notice-archeomed' ),
				count( $trouvees ) );
		}
		return array(
			'items_removed'  => false,
			'items_retained' => ! empty( $trouvees ),
			'messages'       => $messages,
			'done'           => $fini,
		);
	}

	/**
	 * Le texte proposé à la page de politique de confidentialité du site. Il
	 * décrit ce que l'extension fait, non ce que la revue décide : le
	 * responsable de traitement, la durée de conservation et la base légale
	 * restent à écrire par elle.
	 */
	public function proposer_le_texte() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$texte  = '<h3>' . esc_html__( 'Dépôt des notices d’archéologie médiévale', 'notice-archeomed' ) . '</h3>';
		$texte .= '<p>' . esc_html__( 'Le formulaire de dépôt recueille le nom, le prénom, l’institution et l’adresse de courriel du responsable d’opération, du co-responsable et du co-auteur, le texte de la notice, ses illustrations et les autorisations de reproduction jointes.', 'notice-archeomed' ) . '</p>';
		$texte .= '<p>' . esc_html__( 'Ces informations sont conservées sur ce site et adressées par courriel à la rédaction de la revue ; une copie est envoyée aux personnes citées. Elles servent à l’instruction éditoriale des notices.', 'notice-archeomed' ) . '</p>';
		$texte .= '<p>' . esc_html__( 'Pendant la saisie, un brouillon est gardé dans le navigateur, sur l’appareil de l’auteur, trente jours au plus ; il est effacé après l’envoi.', 'notice-archeomed' ) . '</p>';
		$texte .= '<p>' . esc_html__( 'La recherche des lieux et des mots-clés interroge directement, depuis le navigateur, le service Pactols du réseau Frantiq ; l’adresse IP du visiteur lui est donc transmise. Selon la protection choisie, le service Turnstile de Cloudflare vérifie qu’un visiteur n’est pas un robot.', 'notice-archeomed' ) . '</p>';
		$texte .= '<p><em>' . esc_html__( 'À compléter par la revue : le responsable du traitement, la base légale, la durée de conservation des notices et la manière d’exercer ses droits.', 'notice-archeomed' ) . '</em></p>';
		wp_add_privacy_policy_content( __( 'Formulaire des notices d’archéologie médiévale', 'notice-archeomed' ), wp_kses_post( wpautop( $texte, false ) ) );
	}
}
