<?php
/**
 * Le rangement des fichiers d'une notice sur sDrive, une fois envoyée.
 *
 * Le disque du serveur ne suffit pas aux originaux d'une campagne : deux
 * giga-octets pour trois cents notices de une à trois figures. Ce que
 * l'auteur a déposé — originaux, versions converties par son navigateur,
 * autorisations — part donc sur sDrive, avec le Word de chaque version,
 * dans « <numéro>/originaux/<rubrique>/<commune>/ », la rubrique V
 * partagée en ses sous-rubriques. Le serveur ne garde que les
 * aperçus, qui suffisent au Word, au courriel et à la fiche.
 *
 * Rien ne se range pendant le dépôt : un auteur n'attend pas sDrive. Une
 * seule tâche range, notice après notice, avec un budget de temps et un
 * verrou, sur le modèle de celle des termes ; l'attente est dans les
 * notices et ne se perd pas avec une requête coupée.
 *
 * Une copie du serveur ne s'efface que sur un dépôt vérifié — sa taille
 * relue chez sDrive —, et seulement après que la notice a retenu où il est.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Rangement {

	/** Le crochet de la tâche unique. */
	const HOOK = 'na_ranger_sur_sdrive';

	/** Le temps d'un passage, sous les 120 secondes de l'hébergement. */
	const BUDGET = 60;

	/** Le temps qu'on garde pour un dépôt avant de commencer le suivant. */
	const MARGE = 25;

	/** Les notices qu'un passage examine au plus. */
	const LOT = 20;

	/** Les passages qu'une notice peut manquer avant qu'on la laisse. */
	const ESSAIS = 8;

	/** Le verrou : une seule tâche range à la fois. */
	const VERROU = 'na_sdrive_verrou';

	/** Un verrou plus vieux que ceci est celui d'une requête morte. */
	const PERIME = 300;

	/** L'option où le dernier passage dit ce qu'il a fait. */
	const ETAT = 'na_sdrive_etat';

	/** Le nom du fichier qui dit les figures à déposer à la main. */
	const A_DEPOSER = 'a-deposer.txt';

	/** Branche la tâche et son réveil. */
	public static function brancher() {
		add_action( self::HOOK, array( __CLASS__, 'en_tache' ), 10, 0 );
		// Au passage de la relance, toutes les cinq minutes, après la file.
		add_action( Notice_Archeomed_File::HOOK_RELANCE, array( __CLASS__, 'reveiller' ), 21, 0 );
		add_action( 'admin_notices', array( __CLASS__, 'signaler_le_blocage' ) );
	}

	/**
	 * Ce qui empêche de ranger, en quelques mots ; vide quand rien ne
	 * manque, ou quand le site n'a jamais été relié à sDrive.
	 *
	 * Au premier essai réel, aucun dossier n'était enregistré : le
	 * rangement attendait sans rien dire, le courriel parlait des originaux
	 * « conservés sur le site », et l'on cherchait les fichiers sur sDrive.
	 */
	public static function ce_qui_bloque() {
		if ( self::peut_ranger() ) {
			return '';
		}
		$lien = Notice_Archeomed_Sdrive::liaison();
		if ( ! Notice_Archeomed_Sdrive::par_constantes() && empty( $lien['compte'] ) ) {
			return '';
		}
		if ( ! Notice_Archeomed_Sdrive::par_constantes() && Notice_Archeomed_Sdrive::liaison_illisible() ) {
			return 'le mot de passe de la liaison ne se lit plus : il faut relier de nouveau le site à sDrive';
		}
		if ( ! Notice_Archeomed_Sdrive::pret() ) {
			return 'aucun dossier sDrive n’est choisi';
		}
		if ( Notice_Archeomed_Sdrive::refusee() ) {
			return 'sDrive refuse le mot de passe de la liaison (code HTTP 401) : il faut relier de nouveau le site à sDrive';
		}
		return 'aucun numéro n’est réglé';
	}

	/**
	 * Un bandeau, sur toutes les pages de l'administration, tant que des
	 * notices attendent un rangement que rien ne permet.
	 */
	public static function signaler_le_blocage() {
		if ( ! Notice_Archeomed_Settings::peut_regler() ) {
			return;
		}
		self::signaler_les_laissees();
		$bloque = self::ce_qui_bloque();
		if ( '' === $bloque ) {
			return;
		}
		$attente = self::en_attente();
		if ( 0 === $attente ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>'
			. esc_html( sprintf( _n( '%s notice attend d’être rangée sur sDrive, mais rien ne se range : %s.',
				'%s notices attendent d’être rangées sur sDrive, mais rien ne se range : %s.', $attente, 'notice-archeomed' ),
				number_format_i18n( $attente ), $bloque ) )
			. ' ' . esc_html__( 'Leurs fichiers restent sur le site en attendant.', 'notice-archeomed' )
			. ' <a href="' . esc_url( Notice_Archeomed_Settings::url( 'aucun numéro n’est réglé' === $bloque ? 'numero' : 'diagnostic' ) ) . '">'
			. esc_html( 'aucun numéro n’est réglé' === $bloque ? 'Régler le numéro' : 'Ouvrir la liaison à sDrive (onglet Hébergement)' ) . '</a></p></div>';
	}

	/**
	 * Un bandeau pour les notices qu'on a laissées après leurs essais : elles
	 * n'attendent plus rien, et sans lui personne ne les verrait.
	 */
	private static function signaler_les_laissees() {
		$laissees = count( self::laissees() );
		if ( 0 === $laissees ) {
			return;
		}
		echo '<div class="notice notice-error"><p>'
			. esc_html( sprintf( _n( '%1$s notice n’a pas pu être rangée sur sDrive après %2$s essais : ses fichiers restent sur le site.',
				'%1$s notices n’ont pas pu être rangées sur sDrive après %2$s essais : leurs fichiers restent sur le site.', $laissees, 'notice-archeomed' ),
				number_format_i18n( $laissees ), number_format_i18n( self::ESSAIS ) ) )
			. ' ' . esc_html__( 'La raison est notée sur chaque fiche ; « Ranger maintenant » les reprend.', 'notice-archeomed' )
			. ' <a href="' . esc_url( Notice_Archeomed_Settings::url( 'diagnostic' ) ) . '">'
			. esc_html( 'Ouvrir le rangement (onglet Hébergement)' ) . '</a></p></div>';
	}

	/** Les notices laissées après leurs essais, qu'aucun passage ne reprend seul. */
	public static function laissees() {
		return array_map( 'intval', (array) get_posts( array(
			'post_type'        => Notice_Archeomed_File::CPT,
			'post_status'      => 'any',
			'fields'           => 'ids',
			'posts_per_page'   => -1,
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => array(
				array( 'key' => '_na_sdrive_essais', 'compare' => 'EXISTS' ),
				array( 'key' => '_na_sdrive_apres', 'compare' => 'NOT EXISTS' ),
			),
		) ) );
	}

	/** À la désactivation, la tâche s'efface ; l'attente reste dans les notices. */
	public static function desactiver() {
		wp_unschedule_hook( self::HOOK );
	}

	/**
	 * Peut-on ranger ? sDrive relié, un dossier choisi, un mot de passe
	 * qu'il accepte encore, un numéro réglé : sans numéro, on ne sait pas
	 * où ranger.
	 */
	public static function peut_ranger() {
		return Notice_Archeomed_Sdrive::pret()
			&& ! Notice_Archeomed_Sdrive::refusee()
			&& '' !== trim( (string) Notice_Archeomed_Settings::get( 'numero' ) );
	}

	/**
	 * Inscrit une notice au rangement : après son envoi, et après chaque
	 * envoi d'une version corrigée.
	 *
	 * L'inscription se fait même quand sDrive n'est pas relié : les notices
	 * envoyées avant la liaison se rangeront dès qu'elle sera faite.
	 */
	public static function programmer( $id ) {
		$id = (int) $id;
		if ( ! $id ) {
			return;
		}
		update_post_meta( $id, '_na_sdrive_apres', time() );
		delete_post_meta( $id, '_na_sdrive_essais' );
		delete_post_meta( $id, '_na_sdrive_raison' );
		delete_post_meta( $id, '_na_sdrive_entame' );
		if ( self::peut_ranger() ) {
			self::assurer( time() );
		}
	}

	/** La tâche passera au plus tard à l'heure dite. */
	private static function assurer( $quand ) {
		$quand  = max( (int) $quand, time() );
		$prevue = wp_next_scheduled( self::HOOK );
		if ( $prevue && $prevue <= $quand ) {
			return;
		}
		if ( $prevue ) {
			wp_clear_scheduled_hook( self::HOOK );
		}
		wp_schedule_single_event( $quand, self::HOOK );
	}

	/**
	 * Remet la tâche en route si des notices attendent sans elle : après une
	 * liaison, un numéro réglé, une réactivation.
	 */
	public static function reveiller() {
		if ( ! self::peut_ranger() || wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		$premiere = self::dues( 0, 1 );
		if ( ! empty( $premiere ) ) {
			self::assurer( (int) get_post_meta( $premiere[0], '_na_sdrive_apres', true ) );
		}
	}

	/** Les notices à ranger dont l'heure est passée, la plus ancienne d'abord. */
	private static function dues( $jusqua, $combien ) {
		$requete = array(
			'post_type'        => Notice_Archeomed_File::CPT,
			'post_status'      => 'any',
			'fields'           => 'ids',
			'posts_per_page'   => (int) $combien,
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_key'         => '_na_sdrive_apres',
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

	/** Combien de notices attendent d'être rangées, pour le diagnostic. */
	public static function en_attente() {
		$ids = get_posts( array(
			'post_type'        => Notice_Archeomed_File::CPT,
			'post_status'      => 'any',
			'fields'           => 'ids',
			'posts_per_page'   => -1,
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_key'         => '_na_sdrive_apres',
		) );
		return count( (array) $ids );
	}

	/**
	 * Prend le verrou par une écriture qui ne réussit qu'une fois : deux
	 * passages lancés ensemble par deux visites ne rangent pas la même
	 * notice. Un verrou périmé se reprend de la même façon. L'export du
	 * numéro a le sien, sous un autre nom.
	 */
	public static function prendre_le_verrou( $nom = self::VERROU ) {
		global $wpdb;
		$maintenant = time();
		$pris = $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
			$nom, (string) $maintenant ) );
		if ( 1 !== (int) $pris ) {
			$pris = $wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d",
				(string) $maintenant, $nom, $maintenant - self::PERIME ) );
		}
		return 1 === (int) $pris ? $maintenant : 0;
	}

	/** Rend le verrou, s'il est encore le nôtre. */
	public static function rendre_le_verrou( $jeton, $nom = self::VERROU ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
			$nom, (string) $jeton ) );
	}

	/**
	 * L'heure où le passage doit avoir fini, comptée depuis le début de la
	 * requête et non depuis le sien : le planificateur peut lancer le
	 * rangement et l'export l'un après l'autre dans la même requête, et deux
	 * budgets comptés chacun de son départ dépasseraient ensemble les
	 * 120 secondes de l'hébergement.
	 */
	public static function echeance() {
		$debut = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true );
		return min( $debut, microtime( true ) ) + self::BUDGET;
	}

	/** Ce que le passage dit au diagnostic. */
	private static function noter( $raison, $rangees = 0 ) {
		update_option( self::ETAT, array( 'le' => time(), 'raison' => (string) $raison, 'rangees' => (int) $rangees ), false );
	}

	/**
	 * Ce que le planificateur appelle : les notices dues, la plus ancienne
	 * d'abord, jusqu'à ce que le temps manque.
	 */
	public static function en_tache() {
		if ( ! self::peut_ranger() ) {
			wp_clear_scheduled_hook( self::HOOK );
			return;
		}
		$jeton = self::prendre_le_verrou();
		if ( ! $jeton ) {
			// Un autre passage range ; il réservera le suivant.
			return;
		}
		$echeance = self::echeance();
		// Le filet : une requête coupée en route ne réserverait rien.
		self::assurer( time() + 5 * MINUTE_IN_SECONDS );
		$interrompu = false;
		$rangees    = 0;
		try {
			// sDrive injoignable ou liaison refusée : rien n'est la faute des
			// notices, aucune ne perd un essai. On repasse dans un quart d'heure.
			$panne = Notice_Archeomed_Sdrive::joignable();
			if ( '' !== $panne ) {
				self::en_panne( 'sDrive ne répond pas : ' . $panne, 15 * MINUTE_IN_SECONDS, 0 );
				return;
			}
			$raison = '';
			$panne  = null;
			foreach ( self::dues( time(), self::LOT ) as $id ) {
				if ( microtime( true ) > $echeance - self::MARGE ) {
					$interrompu = true;
					break;
				}
				if ( get_post_meta( $id, '_na_sdrive_entame', true ) ) {
					// Le passage précédent est mort sur cette notice sans rien
					// noter : le serveur l'a coupé. L'essai compte, sans quoi
					// une notice trop lourde reviendrait en tête à chaque
					// passage et retiendrait les suivantes.
					delete_post_meta( $id, '_na_sdrive_entame' );
					$raison = 'le passage précédent a été coupé par le serveur pendant le rangement';
					self::compter_un_essai( $id, $raison );
					continue;
				}
				update_post_meta( $id, '_na_sdrive_entame', time() );
				$issue = self::ranger( $id, $echeance, $raison );
				delete_post_meta( $id, '_na_sdrive_entame' );
				if ( 'rangee' === $issue ) {
					++$rangees;
					self::sortir( $id );
				} elseif ( 'sortie' === $issue ) {
					self::sortir( $id );
				} elseif ( 'reportee' === $issue ) {
					update_post_meta( $id, '_na_sdrive_apres', time() + Notice_Archeomed_File::ABANDON );
				} elseif ( 'coupee' === $issue ) {
					$interrompu = true;
					break;
				} else {
					// Le compte plein ou sDrive tombé en route ne sont pas
					// la faute de cette notice : elle ne perd pas d'essai,
					// et les suivantes n'essaient pas en vain.
					$panne = self::panne_du_service();
					if ( null !== $panne ) {
						break;
					}
					self::compter_un_essai( $id, $raison );
				}
			}
			if ( null !== $panne ) {
				self::en_panne( $panne[0], $panne[1], $rangees );
				return;
			}
			self::noter( '' !== $raison ? $raison : 'ok', $rangees );
		} finally {
			self::rendre_le_verrou( $jeton );
		}

		wp_clear_scheduled_hook( self::HOOK );
		$prochain = 0;
		if ( $interrompu ) {
			$prochain = time() + MINUTE_IN_SECONDS;
		} else {
			$premiere = self::dues( 0, 1 );
			if ( ! empty( $premiere ) ) {
				$prochain = (int) get_post_meta( $premiere[0], '_na_sdrive_apres', true );
			}
		}
		if ( $prochain > 0 ) {
			wp_schedule_single_event( max( $prochain, time() + MINUTE_IN_SECONDS ), self::HOOK );
		}
	}

	/**
	 * Le bouton « Ranger maintenant » : sans attendre la relance, qui ne
	 * passe qu'aux visites du site.
	 *
	 * Les notices en attente redeviennent dues, celles qu'on avait laissées
	 * après leurs essais reprennent, et un passage se fait aussitôt, sous le
	 * verrou de la tâche : un passage lancé par une visite n'est pas doublé.
	 */
	public static function ranger_maintenant() {
		// Un essai demandé lève l'arrêt sur les refus du mot de passe : s'il
		// est encore refusé, le passage le dira.
		Notice_Archeomed_Sdrive::oublier_le_refus();
		if ( ! self::peut_ranger() ) {
			$bloque = self::ce_qui_bloque();
			return array( 'ok' => false, 'message' => 'Rien ne peut se ranger : '
				. ( '' !== $bloque ? $bloque : 'le site n’est pas relié à sDrive' ) . '.' );
		}
		$maintenant = time();
		foreach ( self::laissees() as $id ) {
			delete_post_meta( (int) $id, '_na_sdrive_essais' );
			update_post_meta( (int) $id, '_na_sdrive_apres', $maintenant );
		}
		foreach ( self::dues( 0, -1 ) as $id ) {
			if ( (int) get_post_meta( $id, '_na_sdrive_apres', true ) > $maintenant ) {
				update_post_meta( $id, '_na_sdrive_apres', $maintenant );
			}
		}
		if ( 0 === self::en_attente() ) {
			return array( 'ok' => true, 'message' => 'Aucune notice n’attend d’être rangée.' );
		}
		$avant = get_option( self::ETAT );
		self::en_tache();
		$etat = get_option( self::ETAT );
		if ( $etat === $avant || ! is_array( $etat ) ) {
			return array( 'ok' => false, 'message' => 'Un passage est déjà en cours, lancé par une visite du site : rechargez cette page dans une minute.' );
		}
		$rangees = (int) $etat['rangees'];
		$reste   = self::en_attente();
		$raison  = ( 'ok' !== $etat['raison'] ) ? (string) $etat['raison'] : '';
		$message = sprintf( _n( '%s notice rangée sur sDrive.', '%s notices rangées sur sDrive.', $rangees, 'notice-archeomed' ),
			number_format_i18n( $rangees ) );
		if ( $reste > 0 ) {
			$message .= ' ' . sprintf( _n( '%s attend encore', '%s attendent encore', $reste, 'notice-archeomed' ), number_format_i18n( $reste ) )
				. ( '' !== $raison ? ' — ' . $raison . '.' : ' : le passage suivant les prendra dans une minute.' );
		}
		return array( 'ok' => 0 === $reste || $rangees > 0, 'message' => $message );
	}

	/**
	 * Après un échec, la faute en revient-elle au service plutôt qu'à la
	 * notice ? Un compte plein (507) ou un sDrive qui demande de ralentir
	 * (429) valent pour toutes ; sinon, on regarde si le dossier choisi
	 * répond encore. Rend la raison et le délai avant le passage suivant,
	 * ou null quand seule la notice est en cause.
	 */
	private static function panne_du_service() {
		$code = Notice_Archeomed_Sdrive::dernier_code();
		if ( 507 === $code ) {
			return array( 'l’espace du compte sDrive est plein (code HTTP 507)', HOUR_IN_SECONDS );
		}
		if ( 429 === $code ) {
			return array( 'sDrive demande de ralentir (code HTTP 429)', HOUR_IN_SECONDS );
		}
		$panne = Notice_Archeomed_Sdrive::joignable();
		return '' === $panne ? null : array( 'sDrive ne répond plus : ' . $panne, 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Le passage s'arrête sur une panne de sDrive : aucune notice ne perd
	 * d'essai, on repasse plus tard. Quand le mot de passe est refusé de
	 * suite, on cesse : seule une nouvelle liaison, ou un essai demandé, y
	 * remédie.
	 */
	private static function en_panne( $panne, $delai, $rangees ) {
		wp_clear_scheduled_hook( self::HOOK );
		if ( Notice_Archeomed_Sdrive::refusee() ) {
			self::noter( 'sDrive refuse le mot de passe de la liaison : le rangement est arrêté jusqu’à une nouvelle liaison', $rangees );
			return;
		}
		self::noter( $panne, $rangees );
		wp_schedule_single_event( time() + (int) $delai, self::HOOK );
	}

	/** La notice n'a plus rien à ranger. */
	private static function sortir( $id ) {
		delete_post_meta( $id, '_na_sdrive_apres' );
		delete_post_meta( $id, '_na_sdrive_essais' );
		delete_post_meta( $id, '_na_sdrive_raison' );
		delete_post_meta( $id, '_na_sdrive_entame' );
	}

	/**
	 * Un passage manqué : la notice repasse plus tard, de plus en plus tard,
	 * et l'on cesse au bout des essais. Ses fichiers restent alors sur le
	 * serveur, et la fiche dit pourquoi.
	 */
	private static function compter_un_essai( $id, $raison ) {
		$essais = (int) get_post_meta( $id, '_na_sdrive_essais', true ) + 1;
		update_post_meta( $id, '_na_sdrive_essais', $essais );
		update_post_meta( $id, '_na_sdrive_raison', (string) $raison );
		if ( $essais >= self::ESSAIS ) {
			delete_post_meta( $id, '_na_sdrive_apres' );
			return;
		}
		update_post_meta( $id, '_na_sdrive_apres', time() + min( 6 * HOUR_IN_SECONDS, 5 * MINUTE_IN_SECONDS * ( 1 << ( $essais - 1 ) ) ) );
	}

	/** Une requête prépare ou récrit la notice, et n'est pas morte en route. */
	private static function en_preparation( $id ) {
		return (int) get_post_meta( (int) $id, '_na_preparation', true ) > time() - Notice_Archeomed_File::ABANDON;
	}

	/**
	 * Le dossier de la notice sur sDrive, sous le dossier choisi. Fixé au
	 * premier rangement : une correction reçue après qu'on a changé de
	 * numéro rejoint ses premières versions.
	 */
	public static function dossier_de( $id ) {
		$dossier = (string) get_post_meta( (int) $id, '_na_sdrive_dossier', true );
		if ( '' !== $dossier ) {
			return $dossier;
		}
		$numero    = trim( (string) Notice_Archeomed_Settings::get( 'numero' ) );
		$reference = (string) get_post_meta( (int) $id, '_na_reference', true );
		if ( '' === $numero ) {
			return '';
		}
		// Un dossier par rubrique, et dans la V par sous-rubrique : la
		// rédaction y prend une rubrique d'un seul geste.
		$d    = get_post_meta( (int) $id, '_na_donnees', true );
		$base = Notice_Archeomed_Nommage::assainir( $numero, 'numero' ) . '/originaux/' . self::dossiers_de_rubrique( $d ) . '/';
		// Une correction déposée à part rejoint le dossier de la notice
		// qu'elle remplace : ses originaux sont les mêmes, à une figure près.
		$remplace = strtoupper( trim( (string) get_post_meta( (int) $id, '_na_remplace', true ) ) );
		if ( '' !== $remplace ) {
			$premieres = get_posts( array(
				'post_type'        => Notice_Archeomed_File::CPT,
				'post_status'      => 'any',
				'fields'           => 'ids',
				'posts_per_page'   => 1,
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => array( array( 'key' => '_na_reference', 'value' => $remplace ) ),
			) );
			$sien = empty( $premieres ) ? '' : (string) get_post_meta( (int) $premieres[0], '_na_sdrive_dossier', true );
			if ( '' !== $sien ) {
				update_post_meta( (int) $id, '_na_sdrive_dossier', $sien );
				return $sien;
			}
		}
		// Le nom de la commune, que la rédaction reconnaît d'un coup d'œil ;
		// la référence tirée au sort ne disait rien. Deux notices de la même
		// commune se numérotent : « Caen », « Caen_2 ».
		$commune = is_array( $d ) && isset( $d['commune'] ) && is_string( $d['commune'] ) ? $d['commune'] : '';
		$nom     = Notice_Archeomed_Nommage::assainir( $commune, '' );
		if ( '' !== $nom ) {
			for ( $n = 1; $n <= 99; $n++ ) {
				$chemin = $base . ( 1 === $n ? $nom : $nom . '_' . $n );
				if ( self::reserver_le_nom( $chemin, (int) $id ) ) {
					update_post_meta( (int) $id, '_na_sdrive_dossier', $chemin );
					return $chemin;
				}
			}
		}
		return $base . Notice_Archeomed_Nommage::assainir( '' !== $reference ? $reference : 'notice-' . (int) $id, 'notice-' . (int) $id );
	}

	/**
	 * Les dossiers de la rubrique d'une notice : « IV_Sepultures_et_necropoles »,
	 * ou pour la V « V_Installations_artisanales/B_Carrieres_mines_et_metallurgie ».
	 * Une notice V d'avant les sous-rubriques reste au niveau de la V.
	 */
	public static function dossiers_de_rubrique( $d ) {
		$rubrique = Notice_Archeomed_Pactols::rubrique_actuelle( is_array( $d ) && isset( $d['rubrique_principale'] ) ? $d['rubrique_principale'] : '' );
		$chemin   = Notice_Archeomed_Nommage::assainir( $rubrique, 'Sans_rubrique' );
		$lettre   = Notice_Archeomed_Pactols::matiere_de( $d );
		if ( '' !== $lettre ) {
			$chemin .= '/' . Notice_Archeomed_Nommage::assainir( $lettre . ' ' . Notice_Archeomed_Pactols::MATIERES[ $lettre ], $lettre );
		}
		return $chemin;
	}

	/**
	 * Retient un nom de dossier pour une notice, par une écriture qui ne
	 * réussit qu'une fois : deux notices de Caen expédiées au même instant,
	 * par deux passages de la file, n'ont pas toutes deux « Caen ».
	 */
	private static function reserver_le_nom( $chemin, $id ) {
		global $wpdb;
		$cle  = 'na_dossier_' . md5( $chemin );
		$pris = $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
			$cle, (string) $id ) );
		if ( 1 === (int) $pris ) {
			return true;
		}
		return (string) $id === (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $cle ) );
	}

	/**
	 * Rend les noms de dossier retenus pour des notices qui n'existent plus.
	 * À ne faire qu'une fois leurs dossiers supprimés de sDrive : une
	 * nouvelle notice de la même commune reprendrait le nom, et ses fichiers
	 * iraient dans l'ancien dossier. Rend le nombre de noms rendus.
	 *
	 * Les noms sont lus avant d'être effacés : WordPress garde en mémoire
	 * les réglages qu'il a lus, et un nom rendu en base resterait pris pour
	 * lui jusqu'à la fin de la page. Un nom orphelin ne change plus de
	 * valeur — une notice nouvelle ne le prend pas tant qu'il existe —, et
	 * l'effacement le vérifie encore.
	 */
	public static function liberer_les_noms_orphelins() {
		global $wpdb;
		$orphelin = "FROM {$wpdb->options} o LEFT JOIN {$wpdb->posts} p ON p.ID = CAST(o.option_value AS UNSIGNED)
			WHERE o.option_name LIKE %s AND p.ID IS NULL";
		$motif = $wpdb->esc_like( 'na_dossier_' ) . '%';
		$noms  = (array) $wpdb->get_col( $wpdb->prepare( "SELECT o.option_name {$orphelin}", $motif ) );
		$rendus = (int) $wpdb->query( $wpdb->prepare( "DELETE o {$orphelin}", $motif ) );
		foreach ( $noms as $nom ) {
			wp_cache_delete( $nom, 'options' );
		}
		if ( $noms ) {
			wp_cache_delete( 'alloptions', 'options' );
		}
		return $rendus;
	}

	/** Ce que la notice sait de ses fichiers rangés : chemin du serveur ⇒ copie. */
	public static function table_de( $id ) {
		$table = get_post_meta( (int) $id, '_na_sdrive', true );
		return is_array( $table ) ? $table : array();
	}

	/** La copie sur sDrive d'un fichier de la notice, ou null. */
	public static function copie_de( $id, $chemin ) {
		$table = self::table_de( $id );
		return ( is_string( $chemin ) && '' !== $chemin && isset( $table[ $chemin ]['distant'] ) ) ? $table[ $chemin ] : null;
	}

	/**
	 * Le fichier est-il quelque part : sur le serveur, ou rangé sur sDrive
	 * après qu'on l'a effacé d'ici ?
	 */
	public static function present( $id, $chemin ) {
		return is_string( $chemin ) && '' !== $chemin && ( file_exists( $chemin ) || null !== self::copie_de( $id, $chemin ) );
	}

	/**
	 * Un fichier de la notice, lisible sur le serveur : lui-même s'il y est
	 * encore, sinon une copie rapatriée de sDrive dans le répertoire
	 * temporaire, que l'appelant efface. Vide si rien ne se lit.
	 */
	public static function rapatrier( $id, $chemin, &$temporaires ) {
		if ( ! is_string( $chemin ) || '' === $chemin ) {
			return '';
		}
		if ( file_exists( $chemin ) ) {
			return $chemin;
		}
		$copie = self::copie_de( $id, $chemin );
		if ( null === $copie || ! Notice_Archeomed_Sdrive::pret() ) {
			return '';
		}
		$dossier = trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp';
		if ( ! is_dir( $dossier ) ) {
			wp_mkdir_p( $dossier );
		}
		// Le nom finit comme l'original : le paquet et la porte des fichiers
		// lisent la nature d'une figure à son extension.
		$local = trailingslashit( $dossier ) . 'notice-archeomed-sdrive-' . wp_generate_password( 8, false, false )
			. '-' . sanitize_file_name( basename( $copie['distant'] ) );
		if ( '' !== Notice_Archeomed_Sdrive::telecharger( $copie['distant'], $local ) ) {
			return '';
		}
		$temporaires[] = $local;
		return $local;
	}

	/** Efface les copies rapatriées. */
	public static function effacer_les_temporaires( $temporaires ) {
		foreach ( (array) $temporaires as $fichier ) {
			if ( is_string( $fichier ) && false !== strpos( basename( $fichier ), 'notice-archeomed-sdrive-' ) && file_exists( $fichier ) ) {
				@unlink( $fichier );
			}
		}
	}

	/**
	 * Range une notice : « rangee », « sortie » (rien à faire, ou plus
	 * maintenant), « reportee » (une correction est en cours), « coupee »
	 * (le temps manque), « echec » avec sa raison.
	 */
	private static function ranger( $id, $echeance, &$raison ) {
		$raison = '';
		wp_cache_delete( $id, 'post_meta' );
		if ( self::en_preparation( $id ) ) {
			return 'reportee';
		}
		// Une notice qui n'est pas envoyée — corrigée, et revenue dans la
		// file — se range après son prochain envoi, qui l'inscrira de nouveau.
		if ( 'envoyee' !== (string) get_post_meta( $id, '_na_etat', true ) ) {
			return 'sortie';
		}
		$d = get_post_meta( $id, '_na_donnees', true );
		if ( ! is_array( $d ) ) {
			return 'sortie';
		}
		$dossier = self::dossier_de( $id );
		if ( '' === $dossier ) {
			$raison = 'aucun numéro n’est réglé';
			return 'coupee';
		}
		$cree = Notice_Archeomed_Sdrive::creer_le_dossier( $dossier );
		if ( '' !== $cree ) {
			$raison = $cree;
			return 'echec';
		}
		update_post_meta( $id, '_na_sdrive_dossier', $dossier );

		$version = max( 1, (int) get_post_meta( $id, '_na_version', true ) );
		$a_faire = self::fichiers_a_ranger( $id, $d, $version );
		$table   = self::table_de( $id );
		foreach ( $a_faire as $chemin => $nom ) {
			if ( isset( $table[ $chemin ] ) || ! file_exists( $chemin ) ) {
				continue;
			}
			if ( microtime( true ) > $echeance - self::MARGE ) {
				return 'coupee';
			}
			$distant = $dossier . '/' . $nom;
			$refus   = Notice_Archeomed_Sdrive::deposer( $chemin, $distant );
			if ( '' !== $refus ) {
				$raison = $nom . ' : ' . $refus;
				return 'echec';
			}
			// Retenu avant tout effacement : la notice sait où est sa copie
			// avant que le serveur la perde.
			$table[ $chemin ] = array( 'distant' => $distant, 'taille' => (int) filesize( $chemin ), 'le' => time() );
			self::retenir( $id, $chemin, $table[ $chemin ] );
		}

		// Le Word de cette version, refait d'après la saisie : il a été effacé
		// à l'envoi, et l'on garde sur sDrive celui de chaque version.
		$cle_word = 'word:v' . $version;
		if ( ! isset( $table[ $cle_word ] ) ) {
			if ( microtime( true ) > $echeance - self::MARGE ) {
				return 'coupee';
			}
			$refus = self::ranger_le_word( $id, $dossier, $version, $table );
			if ( '' !== $refus ) {
				$raison = 'Word : ' . $refus;
				return 'echec';
			}
		}

		$refus = self::ecrire_a_deposer( $id, $d, $dossier );
		if ( '' !== $refus ) {
			$raison = self::A_DEPOSER . ' : ' . $refus;
			return 'echec';
		}

		self::purger( $id, array_keys( $a_faire ) );
		return 'rangee';
	}

	/** Note une copie dans la table de la notice, relue juste avant. */
	private static function retenir( $id, $cle, $copie ) {
		wp_cache_delete( $id, 'post_meta' );
		$table         = self::table_de( $id );
		$table[ $cle ] = $copie;
		update_post_meta( $id, '_na_sdrive', $table );
	}

	/**
	 * Ce que la notice désigne aujourd'hui, avec le nom de chaque fichier sur
	 * sDrive : originaux, conversions, autorisations. Le témoin d'une figure
	 * envoyée à part ne monte pas : il ne tient que la place de l'original.
	 *
	 * Le nom porte le rang et la version : « Fig_2_v1_tranchee_nord.tif »,
	 * « Fig_2_v1_hr.jpg », « Fig_2_v1_autorisation.pdf ». Une figure gardée
	 * d'une version à l'autre n'est pas recopiée : la table la retrouve
	 * sous le nom de sa première version.
	 */
	private static function fichiers_a_ranger( $id, $d, $version ) {
		$items = array();
		foreach ( ( isset( $d['illustrations'] ) && is_array( $d['illustrations'] ) ) ? $d['illustrations'] : array() as $item ) {
			if ( is_array( $item ) && ! empty( $item['rang'] ) ) {
				$items[ (int) $item['rang'] ] = $item;
			}
		}
		$v       = '_v' . (int) $version;
		$a_faire = array();
		foreach ( Notice_Archeomed_Pactols::illustrations_rangees( $id ) as $i => $chemin ) {
			if ( Notice_Archeomed_Pactols::est_a_part( $chemin ) ) {
				continue;
			}
			$rang  = $i + 1;
			$ext   = strtolower( pathinfo( $chemin, PATHINFO_EXTENSION ) );
			$depos = isset( $items[ $rang ]['fichier_depose'] ) && is_string( $items[ $rang ]['fichier_depose'] )
				? pathinfo( $items[ $rang ]['fichier_depose'], PATHINFO_FILENAME ) : '';
			$tige  = Notice_Archeomed_Nommage::assainir( $depos, '' );
			$a_faire[ $chemin ] = 'Fig_' . $rang . $v . ( '' !== $tige ? '_' . $tige : '' ) . ( '' !== $ext ? '.' . $ext : '' );
		}
		foreach ( Notice_Archeomed_Pactols::conversions_rangees( $id ) as $rang => $familles ) {
			foreach ( (array) $familles as $famille => $chemin ) {
				if ( is_string( $chemin ) && '' !== $chemin ) {
					$a_faire[ $chemin ] = 'Fig_' . (int) $rang . $v . '_' . Notice_Archeomed_Nommage::assainir( (string) $famille, 'conversion' ) . '.jpg';
				}
			}
		}
		$autorisations = get_post_meta( $id, '_na_autorisations', true );
		foreach ( is_array( $autorisations ) ? $autorisations : array() as $rang => $chemin ) {
			if ( is_string( $chemin ) && '' !== $chemin ) {
				$ext = strtolower( pathinfo( $chemin, PATHINFO_EXTENSION ) );
				$a_faire[ $chemin ] = 'Fig_' . (int) $rang . $v . '_autorisation' . ( '' !== $ext ? '.' . $ext : '' );
			}
		}
		return $a_faire;
	}

	/** Refait le Word de la version, le dépose, puis l'efface du serveur. */
	private static function ranger_le_word( $id, $dossier, $version, &$table ) {
		global $notice_archeomed_plugin;
		if ( ! ( $notice_archeomed_plugin instanceof Notice_Archeomed_Pactols ) ) {
			return 'le plugin n’est pas chargé';
		}
		$document = $notice_archeomed_plugin->document_a_ranger( $id );
		if ( '' === $document || ! file_exists( $document ) ) {
			return 'il ne se refait pas d’après la saisie';
		}
		$tige    = Notice_Archeomed_Nommage::tige_du_word( get_post_meta( $id, '_na_donnees', true ) );
		$distant = $dossier . '/' . $tige . '_v' . (int) $version . '.' . ( strtolower( pathinfo( $document, PATHINFO_EXTENSION ) ) ?: 'docx' );
		$refus   = Notice_Archeomed_Sdrive::deposer( $document, $distant );
		if ( '' === $refus ) {
			$cle           = 'word:v' . (int) $version;
			$table[ $cle ] = array( 'distant' => $distant, 'taille' => (int) filesize( $document ), 'le' => time() );
			self::retenir( $id, $cle, $table[ $cle ] );
		}
		// Le Word se refait à la demande : il n'encombre pas le serveur.
		wp_cache_delete( $id, 'post_meta' );
		if ( (string) get_post_meta( $id, '_na_document', true ) === $document ) {
			delete_post_meta( $id, '_na_document' );
		}
		@unlink( $document );
		return $refus;
	}

	/**
	 * « a-deposer.txt » : les figures que l'auteur a envoyées à part, avec
	 * de quoi les télécharger et le nom sous lequel les déposer à côté.
	 * Récrit quand il change, effacé quand il n'y en a plus.
	 */
	private static function ecrire_a_deposer( $id, $d, $dossier ) {
		$texte  = self::texte_a_deposer( $id, $d );
		$avant  = (string) get_post_meta( $id, '_na_sdrive_a_deposer', true );
		$empreinte = '' === $texte ? '' : sha1( $texte );
		if ( $empreinte === $avant ) {
			return '';
		}
		if ( '' === $texte ) {
			$refus = Notice_Archeomed_Sdrive::effacer( $dossier . '/' . self::A_DEPOSER );
			if ( '' === $refus ) {
				delete_post_meta( $id, '_na_sdrive_a_deposer' );
			}
			return $refus;
		}
		$refus = Notice_Archeomed_Sdrive::ecrire( $texte, $dossier . '/' . self::A_DEPOSER );
		if ( '' === $refus ) {
			update_post_meta( $id, '_na_sdrive_a_deposer', $empreinte );
		}
		return $refus;
	}

	/** Le texte de « a-deposer.txt », vide s'il n'y a pas de figure à part. */
	public static function texte_a_deposer( $id, $d ) {
		$blocs = array();
		foreach ( ( isset( $d['illustrations'] ) && is_array( $d['illustrations'] ) ) ? $d['illustrations'] : array() as $item ) {
			$a_part = Notice_Archeomed_Pactols::figure_a_part( $item );
			if ( null === $a_part || empty( $item['rang'] ) ) {
				continue;
			}
			$rang   = (int) $item['rang'];
			$titre  = isset( $item['titre'] ) && is_scalar( $item['titre'] ) ? trim( wp_strip_all_tags( (string) $item['titre'] ) ) : '';
			$lignes = array( 'Figure ' . $rang . ( '' !== $titre ? ' — ' . $titre : '' ) );
			if ( ! empty( $a_part['lien'] ) ) {
				$lignes[] = '  Envoi : ' . $a_part['lien'];
			}
			if ( ! empty( $a_part['jusqua'] ) ) {
				$lignes[] = '  Valable jusqu’au : ' . Notice_Archeomed_Pactols::date_en_lettres( $a_part['jusqua'] );
			}
			$fichier = ! empty( $a_part['fichier'] ) ? (string) $a_part['fichier'] : '';
			if ( '' !== $fichier ) {
				$lignes[] = '  Nom du fichier dans l’envoi : ' . $fichier;
			}
			$lignes[] = '  À déposer dans ce dossier ' . ( '' !== $fichier ? 'sous ce même nom, ou renommé' : 'sous le nom' )
				. ' « Fig_' . $rang . ' » suivi de son extension.';
			$blocs[] = implode( "\n", $lignes );
		}
		if ( empty( $blocs ) ) {
			return '';
		}
		$reference = (string) get_post_meta( (int) $id, '_na_reference', true );
		$commune   = isset( $d['commune'] ) && is_scalar( $d['commune'] ) ? trim( (string) $d['commune'] ) : '';
		$tete      = 'Figures envoyées à part — notice ' . ( '' !== $reference ? $reference : (int) $id )
			. ( '' !== $commune ? ', ' . $commune : '' );
		return $tete . "\n\n" . implode( "\n\n", $blocs ) . "\n\n"
			. 'L’export du numéro reprend l’original qu’il trouve ici ; une figure sans original y est signalée à reprendre.' . "\n";
	}

	/**
	 * Efface du serveur ce qui est rangé, à quatre conditions : la copie est
	 * vérifiée, aucune correction n'est en cours, la notice désigne encore le
	 * fichier, et sa figure a un aperçu lisible — le Word, le courriel et la
	 * fiche n'ont plus que lui. Sinon le fichier reste, et rien n'est perdu.
	 */
	private static function purger( $id, $chemins ) {
		wp_cache_delete( $id, 'post_meta' );
		if ( self::en_preparation( $id ) || 'envoyee' !== (string) get_post_meta( $id, '_na_etat', true ) ) {
			return;
		}
		$d       = get_post_meta( $id, '_na_donnees', true );
		$version = max( 1, (int) get_post_meta( $id, '_na_version', true ) );
		$encore  = self::fichiers_a_ranger( $id, is_array( $d ) ? $d : array(), $version );
		$table   = self::table_de( $id );
		$apercus = get_post_meta( $id, '_na_apercus', true );
		$apercus = is_array( $apercus ) ? $apercus : array();
		$rangs   = self::rangs_des_fichiers( $id );
		$autorisations = get_post_meta( $id, '_na_autorisations', true );
		$autorisations = is_array( $autorisations ) ? array_flip( array_filter( $autorisations, 'is_string' ) ) : array();
		foreach ( (array) $chemins as $chemin ) {
			if ( ! isset( $encore[ $chemin ], $table[ $chemin ] ) || ! file_exists( $chemin ) ) {
				continue;
			}
			if ( (int) filesize( $chemin ) !== (int) $table[ $chemin ]['taille'] ) {
				continue;
			}
			// Une autorisation ne sert plus à rien d'ici une fois envoyée.
			$rang = isset( $rangs[ $chemin ] ) ? $rangs[ $chemin ] : 0;
			if ( ! isset( $autorisations[ $chemin ] ) && ( $rang < 1 || empty( $apercus[ $rang ]['apercu'] ) || ! is_readable( $apercus[ $rang ]['apercu'] ) ) ) {
				continue;
			}
			@unlink( $chemin );
		}
	}

	/** Le rang de figure de chaque fichier que la notice désigne. */
	private static function rangs_des_fichiers( $id ) {
		$rangs = array();
		foreach ( Notice_Archeomed_Pactols::illustrations_rangees( $id ) as $i => $chemin ) {
			$rangs[ $chemin ] = $i + 1;
		}
		foreach ( Notice_Archeomed_Pactols::conversions_rangees( $id ) as $rang => $familles ) {
			foreach ( (array) $familles as $chemin ) {
				if ( is_string( $chemin ) ) {
					$rangs[ $chemin ] = (int) $rang;
				}
			}
		}
		$autorisations = get_post_meta( $id, '_na_autorisations', true );
		foreach ( is_array( $autorisations ) ? $autorisations : array() as $rang => $chemin ) {
			if ( is_string( $chemin ) ) {
				$rangs[ $chemin ] = (int) $rang;
			}
		}
		return $rangs;
	}
}
