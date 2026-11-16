<?php
/**
 * Le rangement des fichiers d'une notice sur sDrive, une fois envoyée.
 *
 * Le disque du serveur ne suffit pas aux originaux d'une campagne : deux
 * giga-octets pour trois cents notices de une à trois figures. Ce que
 * l'auteur a déposé — originaux, versions converties par son navigateur,
 * autorisations — part donc sur sDrive, avec le Word de chaque version,
 * dans « <numéro>/originaux/<référence>/ ». Le serveur ne garde que les
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
	}

	/** À la désactivation, la tâche s'efface ; l'attente reste dans les notices. */
	public static function desactiver() {
		wp_unschedule_hook( self::HOOK );
	}

	/**
	 * Peut-on ranger ? sDrive relié, un dossier choisi, un numéro réglé :
	 * sans numéro, on ne sait pas où ranger.
	 */
	public static function peut_ranger() {
		return Notice_Archeomed_Sdrive::pret()
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
	 * notice. Un verrou périmé se reprend de la même façon.
	 */
	private static function prendre_le_verrou() {
		global $wpdb;
		$maintenant = time();
		$pris = $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
			self::VERROU, (string) $maintenant ) );
		if ( 1 !== (int) $pris ) {
			$pris = $wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d",
				(string) $maintenant, self::VERROU, $maintenant - self::PERIME ) );
		}
		return 1 === (int) $pris ? $maintenant : 0;
	}

	/** Rend le verrou, s'il est encore le nôtre. */
	private static function rendre_le_verrou( $jeton ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
			self::VERROU, (string) $jeton ) );
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
		$echeance = microtime( true ) + self::BUDGET;
		// Le filet : une requête coupée en route ne réserverait rien.
		self::assurer( time() + 5 * MINUTE_IN_SECONDS );
		$interrompu = false;
		$rangees    = 0;
		try {
			// sDrive injoignable ou liaison refusée : rien n'est la faute des
			// notices, aucune ne perd un essai. On repasse dans un quart d'heure.
			$panne = Notice_Archeomed_Sdrive::joignable();
			if ( '' !== $panne ) {
				self::noter( 'sDrive ne répond pas : ' . $panne );
				wp_clear_scheduled_hook( self::HOOK );
				wp_schedule_single_event( time() + 15 * MINUTE_IN_SECONDS, self::HOOK );
				return;
			}
			$raison = '';
			foreach ( self::dues( time(), self::LOT ) as $id ) {
				if ( microtime( true ) > $echeance - self::MARGE ) {
					$interrompu = true;
					break;
				}
				$issue = self::ranger( $id, $echeance, $raison );
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
					self::compter_un_essai( $id, $raison );
				}
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

	/** La notice n'a plus rien à ranger. */
	private static function sortir( $id ) {
		delete_post_meta( $id, '_na_sdrive_apres' );
		delete_post_meta( $id, '_na_sdrive_essais' );
		delete_post_meta( $id, '_na_sdrive_raison' );
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
		return Notice_Archeomed_Nommage::assainir( $numero, 'numero' ) . '/originaux/'
			. Notice_Archeomed_Nommage::assainir( '' !== $reference ? $reference : 'notice-' . (int) $id, 'notice-' . (int) $id );
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
		$distant = $dossier . '/notice_v' . (int) $version . '.' . ( strtolower( pathinfo( $document, PATHINFO_EXTENSION ) ) ?: 'docx' );
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
