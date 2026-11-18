<?php
/**
 * L'export d'une rubrique sur sDrive, dans le dossier du numéro.
 *
 * Le dossier Métopes ne passe plus par une archive : ses fichiers vont
 * directement dans « <numéro>/ », à côté de « originaux/ » où le
 * rangement a mis ce que les auteurs ont envoyé. La rubrique y écrit son
 * document sous « style/ », ses figures sous « icono/hr » et « icono/br »,
 * ses blocs d'index sous « XML/indexation », sa page de relecture et son
 * lisez-moi à la racine.
 *
 * Rien ne tient dans une seule requête : cent figures à rapatrier, réduire
 * et déposer dépassent les 120 secondes de l'hébergement. L'export passe
 * donc par une tâche, une figure après l'autre, et garde entre deux
 * passages ce qu'il a fait. Une rubrique à la fois ; les suivantes
 * attendent leur tour.
 *
 * Toutes les rubriques d'un numéro partagent les mêmes dossiers. Chacune
 * tient la liste de ce qu'elle y a écrit — son manifeste — et un nouvel
 * export n'efface que cette liste : jamais « originaux/ », ni « indd/ »,
 * ni le reste de « XML/ », ni ce qu'une autre rubrique a posé.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Export {

	/** Le crochet de la tâche. */
	const HOOK = 'na_exporter_sur_sdrive';

	/** L'export en cours, d'un passage à l'autre. */
	const TRAVAIL = 'na_export';

	/** Les rubriques qui attendent leur tour. */
	const ATTENTE = 'na_export_attente';

	/** Ce que chaque rubrique a écrit dans chaque numéro, et comment s'est fini son dernier export. */
	const MANIFESTES = 'na_export_manifestes';

	/** Le verrou : un seul passage exporte à la fois. */
	const VERROU = 'na_export_verrou';

	/** Le temps qu'on garde pour une figure avant de la commencer. */
	const MARGE = 30;

	/** Les passages manqués avant qu'on renonce, à une figure ou à la rubrique. */
	const ESSAIS = 5;

	/** Les passages coupés par le serveur sur une même figure avant qu'on la laisse. */
	const COUPURES = 2;

	/** Branche la tâche et son réveil. */
	public static function brancher() {
		add_action( self::HOOK, array( __CLASS__, 'en_tache' ), 10, 0 );
		add_action( Notice_Archeomed_File::HOOK_RELANCE, array( __CLASS__, 'reveiller' ), 22, 0 );
	}

	/** À la désactivation, la tâche s'efface ; l'export en cours et l'attente restent. */
	public static function desactiver() {
		wp_unschedule_hook( self::HOOK );
	}

	/** L'export n'a lieu que là où le rangement a lieu : sDrive prêt, un numéro réglé. */
	public static function peut_exporter() {
		return Notice_Archeomed_Rangement::peut_ranger();
	}

	/** Le nom du numéro tel qu'il s'écrit sur sDrive. */
	public static function numero() {
		$numero = trim( (string) Notice_Archeomed_Settings::get( 'numero' ) );
		return '' === $numero ? '' : Notice_Archeomed_Nommage::assainir( $numero, 'numero' );
	}

	/**
	 * Inscrit une rubrique à l'export. Une rubrique déjà en attente, ou en
	 * cours, ne s'inscrit pas deux fois. Rend une phrase pour la rédaction.
	 */
	public static function demander( $rubrique ) {
		$rubrique = (string) $rubrique;
		if ( ! self::peut_exporter() ) {
			return 'sDrive n’est pas relié, ou aucun numéro n’est réglé : rien ne peut être exporté.';
		}
		$travail = self::travail();
		if ( null !== $travail && $travail['rubrique'] === $rubrique ) {
			return 'Cette rubrique est déjà en cours d’export.';
		}
		$attente = self::attente();
		if ( in_array( $rubrique, $attente, true ) ) {
			return 'Cette rubrique attend déjà son tour.';
		}
		$attente[] = $rubrique;
		update_option( self::ATTENTE, $attente, false );
		self::assurer( time() );
		return ( null === $travail && 1 === count( $attente ) )
			? 'L’export commence ; il se poursuit seul, une figure après l’autre.'
			: 'L’export est inscrit : il suivra ceux qui le précèdent.';
	}

	/** L'export en cours, ou null. */
	public static function travail() {
		$travail = get_option( self::TRAVAIL );
		return ( is_array( $travail ) && ! empty( $travail['rubrique'] ) ) ? $travail : null;
	}

	/** Les rubriques en attente, dans l'ordre. */
	public static function attente() {
		$attente = get_option( self::ATTENTE );
		return is_array( $attente ) ? array_values( array_map( 'strval', $attente ) ) : array();
	}

	/** Les manifestes de tous les numéros. */
	public static function manifestes() {
		$m = get_option( self::MANIFESTES );
		return is_array( $m ) ? $m : array();
	}

	/** Le manifeste d'une rubrique dans un numéro. */
	public static function manifeste( $numero, $rubrique ) {
		$m = self::manifestes();
		return isset( $m[ $numero ][ $rubrique ] ) && is_array( $m[ $numero ][ $rubrique ] )
			? $m[ $numero ][ $rubrique ] + array( 'fichiers' => array() )
			: array( 'fichiers' => array() );
	}

	/** Récrit une partie du manifeste d'une rubrique, relu juste avant. */
	private static function retoucher( $numero, $rubrique, $champs ) {
		wp_cache_delete( self::MANIFESTES, 'options' );
		$m = self::manifestes();
		$actuel = isset( $m[ $numero ][ $rubrique ] ) && is_array( $m[ $numero ][ $rubrique ] )
			? $m[ $numero ][ $rubrique ] : array( 'fichiers' => array() );
		$m[ $numero ][ $rubrique ] = array_merge( $actuel, $champs );
		update_option( self::MANIFESTES, $m, false );
	}

	/** Un fichier déposé entre au manifeste aussitôt : un passage coupé ne l'oublie pas. */
	private static function inscrire( $numero, $rubrique, $distant ) {
		$fichiers = self::manifeste( $numero, $rubrique )['fichiers'];
		if ( ! in_array( $distant, $fichiers, true ) ) {
			$fichiers[] = $distant;
			self::retoucher( $numero, $rubrique, array( 'fichiers' => $fichiers ) );
		}
	}

	/** Un fichier effacé quitte le manifeste aussitôt. */
	private static function rayer( $numero, $rubrique, $distant ) {
		$fichiers = array_values( array_diff( self::manifeste( $numero, $rubrique )['fichiers'], array( $distant ) ) );
		self::retoucher( $numero, $rubrique, array( 'fichiers' => $fichiers ) );
	}

	/**
	 * Ce que la liste des rubriques dit de l'export d'une rubrique : en
	 * cours, en attente, fini, ou rien.
	 */
	public static function etat_de( $rubrique ) {
		$travail = self::travail();
		if ( null !== $travail && $travail['rubrique'] === $rubrique ) {
			$total = isset( $travail['figures'] ) ? count( $travail['figures'] ) : 0;
			$texte = 'Export en cours';
			if ( 'figures' === $travail['etape'] && $total > 0 ) {
				$texte .= ' : figure ' . min( $total, (int) $travail['suivante'] + 1 ) . ' sur ' . $total;
			} elseif ( 'rubrique' === $travail['etape'] ) {
				$texte .= ' : le document et la relecture';
			} elseif ( 'nettoyer' === $travail['etape'] ) {
				$texte .= ' : l’export précédent s’efface';
			}
			if ( ! empty( $travail['raison'] ) ) {
				$texte .= ' (nouvel essai : ' . $travail['raison'] . ')';
			}
			return array( 'etat' => 'cours', 'texte' => $texte . '…' );
		}
		if ( in_array( $rubrique, self::attente(), true ) ) {
			return array( 'etat' => 'attente', 'texte' => 'En attente d’export…' );
		}
		$numero = self::numero();
		$m      = self::manifeste( $numero, $rubrique );
		if ( empty( $m['dernier'] ) ) {
			return array( 'etat' => '', 'texte' => '' );
		}
		$quand = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $m['dernier'] );
		if ( isset( $m['issue'] ) && 'echec' === $m['issue'] ) {
			return array( 'etat' => 'echec', 'texte' => 'Export interrompu le ' . $quand
				. ( ! empty( $m['texte'] ) ? ' : ' . $m['texte'] : '' ) . '. Relancez-le.' );
		}
		$texte = 'Exporté le ' . $quand . ' dans « ' . $numero . ' »';
		if ( ! empty( $m['avis'] ) ) {
			$texte .= ' — ' . (int) $m['avis'] . ' avis au lisez-moi';
		}
		return array( 'etat' => 'fait', 'texte' => $texte . '.' );
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

	/** Remet la tâche en route si un export attend sans elle. */
	public static function reveiller() {
		if ( ! self::peut_exporter() || wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		$travail = self::travail();
		if ( null !== $travail ) {
			self::assurer( isset( $travail['apres'] ) ? (int) $travail['apres'] : time() );
		} elseif ( ! empty( self::attente() ) ) {
			self::assurer( time() );
		}
	}

	/** Y a-t-il encore quelque chose à exporter ? */
	public static function en_cours() {
		return null !== self::travail() || ! empty( self::attente() );
	}

	/**
	 * Ce que le planificateur appelle : l'export en cours avance jusqu'à ce
	 * que le temps manque, puis la rubrique suivante commence.
	 */
	public static function en_tache() {
		if ( ! self::peut_exporter() ) {
			wp_clear_scheduled_hook( self::HOOK );
			return;
		}
		$jeton = Notice_Archeomed_Rangement::prendre_le_verrou( self::VERROU );
		if ( ! $jeton ) {
			return;
		}
		$echeance = Notice_Archeomed_Rangement::echeance();
		// Le filet : une requête coupée en route ne réserverait rien.
		self::assurer( time() + 5 * MINUTE_IN_SECONDS );
		$prochain = 0;
		$temporaires = array();
		$paquet      = null;
		try {
			$prochain = self::avancer( $echeance, $temporaires, $paquet );
		} finally {
			if ( $paquet instanceof Notice_Archeomed_Paquet ) {
				$paquet->nettoyer();
			}
			Notice_Archeomed_Rangement::effacer_les_temporaires( $temporaires );
			Notice_Archeomed_Rangement::rendre_le_verrou( $jeton, self::VERROU );
		}
		wp_clear_scheduled_hook( self::HOOK );
		if ( $prochain > 0 ) {
			wp_schedule_single_event( max( $prochain, time() + MINUTE_IN_SECONDS ), self::HOOK );
		}
	}

	/**
	 * Le travail d'un passage. Rend l'heure du prochain, zéro s'il n'y a
	 * plus rien à faire.
	 */
	private static function avancer( $echeance, &$temporaires, &$paquet ) {
		wp_cache_delete( self::TRAVAIL, 'options' );
		wp_cache_delete( self::ATTENTE, 'options' );
		$travail = self::travail();
		if ( null !== $travail && ! empty( $travail['apres'] ) && (int) $travail['apres'] > time() ) {
			return (int) $travail['apres'];
		}
		if ( null === $travail && empty( self::attente() ) ) {
			return 0;
		}
		// sDrive injoignable : la faute n'est pas à l'export, aucun essai ne
		// se compte. On repasse dans un quart d'heure.
		$panne = Notice_Archeomed_Sdrive::joignable();
		if ( '' !== $panne ) {
			if ( null !== $travail ) {
				$travail['raison'] = 'sDrive ne répond pas : ' . $panne;
				update_option( self::TRAVAIL, $travail, false );
			}
			return time() + 15 * MINUTE_IN_SECONDS;
		}

		while ( microtime( true ) < $echeance - self::MARGE ) {
			if ( null === $travail ) {
				$travail = self::commencer();
				if ( null === $travail ) {
					return 0;
				}
			}
			$raison = '';
			if ( ! empty( $travail['entame'] ) ) {
				// Le passage précédent est mort pendant ce pas sans rien
				// noter : le serveur l'a coupé, faute de temps ou de mémoire.
				// Le pas compte comme manqué, sans quoi une figure trop
				// lourde reviendrait sans fin et bloquerait la file.
				$issue  = 'manque';
				$raison = 'le passage précédent a été coupé par le serveur pendant ce pas';
				// Une figure qui fait couper le serveur le refera : elle a eu
				// un passage entier pour elle, on n'en attend pas trois autres
				// (une demi-heure de plus) avant de passer à la suivante.
				$travail['coupures'] = ( isset( $travail['coupures'] ) ? (int) $travail['coupures'] : 0 ) + 1;
				if ( 'figures' === $travail['etape'] && $travail['coupures'] >= self::COUPURES ) {
					$travail['essais'] = max( (int) $travail['essais'], self::ESSAIS - 1 );
				}
			} else {
				$travail['entame'] = time();
				update_option( self::TRAVAIL, $travail, false );
				$issue = self::un_pas( $travail, $echeance, $temporaires, $paquet, $raison );
			}
			unset( $travail['entame'] );
			Notice_Archeomed_Rangement::effacer_les_temporaires( $temporaires );
			$temporaires = array();
			if ( $paquet instanceof Notice_Archeomed_Paquet ) {
				$paquet->nettoyer();
			}
			if ( 'fini' === $issue ) {
				delete_option( self::TRAVAIL );
				$travail = null;
				continue;
			}
			if ( 'avance' === $issue ) {
				$travail['essais']   = 0;
				$travail['coupures'] = 0;
				$travail['raison']   = '';
				unset( $travail['apres'] );
				update_option( self::TRAVAIL, $travail, false );
				continue;
			}
			// Un pas manqué : on le reprendra plus tard, de plus en plus tard.
			$travail['essais'] = (int) $travail['essais'] + 1;
			$travail['raison'] = $raison;
			if ( $travail['essais'] >= self::ESSAIS ) {
				if ( 'figures' === $travail['etape'] ) {
					// Une figure qui ne passe pas n'arrête pas la rubrique : le
					// lisez-moi la donne à reprendre, et l'on continue.
					$etat = Notice_Archeomed_Paquet::reprendre( $travail['paquet'] );
					$figure = $travail['figures'][ (int) $travail['suivante'] ];
					$etat->signaler( $figure['nom'] . ' : la figure ' . (int) $figure['n']
						. ' n’a pas pu être exportée (' . $raison . ') ; reprenez-la d’après « originaux/ ».' );
					$travail['paquet'] = $etat->etat();
					++$travail['suivante'];
					$travail['essais']   = 0;
					$travail['coupures'] = 0;
					$travail['raison']   = '';
					update_option( self::TRAVAIL, $travail, false );
					continue;
				}
				self::retoucher( $travail['numero'], $travail['rubrique'], array(
					'dernier' => time(), 'issue' => 'echec', 'texte' => $raison, 'avis' => 0,
				) );
				delete_option( self::TRAVAIL );
				$travail = null;
				continue;
			}
			$travail['apres'] = time() + min( HOUR_IN_SECONDS, 2 * MINUTE_IN_SECONDS * ( 1 << ( $travail['essais'] - 1 ) ) );
			update_option( self::TRAVAIL, $travail, false );
			return (int) $travail['apres'];
		}
		// Le temps manque : la suite au prochain passage, sans attendre.
		return ( null !== $travail || ! empty( self::attente() ) ) ? time() + MINUTE_IN_SECONDS : 0;
	}

	/** La rubrique suivante sort de l'attente et devient l'export en cours. */
	private static function commencer() {
		$attente = self::attente();
		if ( empty( $attente ) ) {
			return null;
		}
		$rubrique = array_shift( $attente );
		update_option( self::ATTENTE, $attente, false );
		$travail = array(
			'rubrique' => $rubrique,
			'numero'   => self::numero(),
			'etape'    => 'debut',
			'essais'   => 0,
			'raison'   => '',
			'commence' => time(),
		);
		update_option( self::TRAVAIL, $travail, false );
		return $travail;
	}

	/**
	 * Un pas de l'export : « avance », « fini », ou « manque » avec sa
	 * raison. Le travail est modifié en place ; c'est l'appelant qui
	 * l'enregistre.
	 */
	private static function un_pas( &$travail, $echeance, &$temporaires, &$paquet, &$raison ) {
		global $notice_archeomed_plugin;
		if ( ! ( $notice_archeomed_plugin instanceof Notice_Archeomed_Pactols ) ) {
			$raison = 'le plugin n’est pas chargé';
			return 'manque';
		}
		$numero   = $travail['numero'];
		$rubrique = $travail['rubrique'];

		switch ( $travail['etape'] ) {
			case 'debut':
				return self::planifier( $travail, $notice_archeomed_plugin, $raison );

			case 'nettoyer':
				// L'export précédent de la rubrique s'efface, fichier par
				// fichier, et chacun quitte le manifeste à mesure : un passage
				// coupé reprend où il en était.
				foreach ( self::manifeste( $numero, $rubrique )['fichiers'] as $distant ) {
					if ( microtime( true ) > $echeance - self::MARGE ) {
						return 'avance';
					}
					$refus = Notice_Archeomed_Sdrive::effacer( $distant );
					if ( '' !== $refus ) {
						$raison = $distant . ' : ' . $refus;
						return 'manque';
					}
					self::rayer( $numero, $rubrique, $distant );
				}
				foreach ( array( 'style', 'icono/hr', 'icono/br', 'XML/indexation' ) as $dossier ) {
					$refus = Notice_Archeomed_Sdrive::creer_le_dossier( $numero . '/' . $dossier );
					if ( '' !== $refus ) {
						$raison = $refus;
						return 'manque';
					}
				}
				$travail['etape'] = empty( $travail['figures'] ) ? 'rubrique' : 'figures';
				return 'avance';

			case 'figures':
				return self::une_figure( $travail, $temporaires, $paquet, $raison );

			case 'rubrique':
				return self::finir( $travail, $notice_archeomed_plugin, $paquet, $raison );
		}
		$raison = 'étape inconnue';
		return 'manque';
	}

	/**
	 * Le plan de la rubrique : ses notices, ses figures dans l'ordre et le
	 * nom de chacune, les noms que les autres rubriques du numéro ont déjà
	 * pris étant réservés.
	 */
	private static function planifier( &$travail, $plugin, &$raison ) {
		$notices = $plugin->notices_a_assembler( $plugin->ids_de_la_rubrique( $travail['rubrique'] ) );
		$paquet  = new Notice_Archeomed_Paquet();
		$erreur  = '';
		if ( ! $paquet->commencer( $travail['rubrique'], $erreur, false ) ) {
			$raison = $erreur;
			return 'manque';
		}
		$figures = array();
		$index   = array();
		foreach ( self::manifestes() as $numero => $rubriques ) {
			if ( $numero !== $travail['numero'] ) {
				continue;
			}
			foreach ( (array) $rubriques as $autre => $m ) {
				if ( $autre === $travail['rubrique'] ) {
					continue;
				}
				foreach ( isset( $m['fichiers'] ) ? (array) $m['fichiers'] : array() as $distant ) {
					$sans_ext = pathinfo( $distant, PATHINFO_FILENAME );
					if ( preg_match( '#/icono/(hr|br)/#', $distant ) ) {
						$figures[] = $sans_ext;
					} elseif ( false !== strpos( $distant, '/XML/indexation/' ) ) {
						$index[] = $sans_ext;
					}
				}
			}
		}
		$paquet->reserver( $figures, $index );
		$plan = $paquet->planifier( $notices, function ( $source, $notice ) {
			return Notice_Archeomed_Rangement::present( isset( $notice['id'] ) ? (int) $notice['id'] : 0, $source );
		}, true );
		$paquet->nettoyer();
		// Ce qu'il faut garder de chaque figure, rien de plus : l'option
		// tient entre deux passages.
		foreach ( $plan as $i => $figure ) {
			unset( $plan[ $i ]['notice'] );
		}
		$travail['ids']        = array_values( array_map( 'intval', wp_list_pluck( $notices, 'id' ) ) );
		$travail['figures']    = array_values( $plan );
		$travail['suivante']   = 0;
		$travail['mesures']    = array();
		$travail['exportees']  = 0;
		$travail['paquet']     = $paquet->etat();
		$travail['etape']      = 'nettoyer';
		return 'avance';
	}

	/**
	 * Une figure : rapatriée de sDrive, réduite dans un atelier neuf, ses
	 * deux définitions déposées dans le dossier du numéro.
	 */
	private static function une_figure( &$travail, &$temporaires, &$paquet, &$raison ) {
		$rang = (int) $travail['suivante'];
		if ( ! isset( $travail['figures'][ $rang ] ) ) {
			$travail['etape'] = 'rubrique';
			return 'avance';
		}
		$figure = $travail['figures'][ $rang ];
		$id     = (int) $figure['id'];
		$paquet = Notice_Archeomed_Paquet::reprendre( $travail['paquet'] );
		$erreur = '';
		if ( ! $paquet->atelier_neuf( $erreur ) ) {
			$raison = $erreur;
			return 'manque';
		}

		if ( null !== $figure['a_part'] ) {
			// Une figure envoyée à part : son original, si quelqu'un l'a
			// déposé à côté des autres, sous le nom de l'envoi ou « Fig_N ».
			$dossier = Notice_Archeomed_Rangement::dossier_de( $id );
			$entrees = '' === $dossier ? array() : Notice_Archeomed_Sdrive::lister( $dossier );
			if ( null === $entrees ) {
				$raison = 'le dossier « ' . $dossier . ' » ne se lit pas';
				return 'manque';
			}
			$attendu = isset( $figure['a_part']['fichier'] ) ? strtolower( (string) $figure['a_part']['fichier'] ) : '';
			$trouve  = '';
			foreach ( $entrees as $entree ) {
				if ( $entree['dossier'] || $entree['taille'] <= 0 ) {
					continue;
				}
				if ( ( '' !== $attendu && strtolower( $entree['nom'] ) === $attendu )
					|| preg_match( '/^Fig_' . (int) $figure['n'] . '\.[a-z0-9]+$/i', $entree['nom'] ) ) {
					$trouve = $entree['nom'];
					break;
				}
			}
			if ( '' === $trouve ) {
				$paquet->signaler( $figure['reprendre'] );
				$travail['paquet'] = $paquet->etat();
				++$travail['suivante'];
				return 'avance';
			}
			wp_mkdir_p( trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp' );
			$local = trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp/notice-archeomed-sdrive-'
				. wp_generate_password( 8, false, false ) . '-' . sanitize_file_name( $trouve );
			$refus = Notice_Archeomed_Sdrive::telecharger( $dossier . '/' . $trouve, $local );
			if ( '' !== $refus ) {
				$raison = $trouve . ' : ' . $refus;
				return 'manque';
			}
			$temporaires[] = $local;
			$source        = $local;
			$conversions   = array();
			$paquet->compter_une_illustration();
			$paquet->noter( Notice_Archeomed_Normes::numero_de_figure( (int) $figure['n'] ) . ' de « ' . $figure['nom']
				. ' » : reprise de l’original déposé sur sDrive (« ' . $trouve . ' »).' );
		} else {
			$source = Notice_Archeomed_Rangement::rapatrier( $id, $figure['source'], $temporaires );
			if ( '' === $source ) {
				$raison = basename( $figure['source'] ) . ' ne se rapatrie pas de sDrive';
				return 'manque';
			}
			$conversions = array();
			foreach ( (array) $figure['conversions'] as $famille => $chemin ) {
				$lu = Notice_Archeomed_Rangement::rapatrier( $id, $chemin, $temporaires );
				if ( '' !== $lu ) {
					$conversions[ $famille ] = $lu;
				}
			}
		}

		$mesure = $paquet->poser_une_figure( $source, $figure['nom'], $conversions );
		$numero = $travail['numero'];
		foreach ( array( 'icono/hr', 'icono/br' ) as $sous ) {
			foreach ( (array) glob( $paquet->atelier() . '/' . $sous . '/*' ) as $fichier ) {
				if ( ! is_file( $fichier ) ) {
					continue;
				}
				$distant = $numero . '/' . $sous . '/' . basename( $fichier );
				$refus   = Notice_Archeomed_Sdrive::deposer( $fichier, $distant );
				if ( '' !== $refus ) {
					$raison = basename( $fichier ) . ' : ' . $refus;
					return 'manque';
				}
				self::inscrire( $numero, $travail['rubrique'], $distant );
			}
		}
		if ( null !== $mesure ) {
			$travail['mesures'][ $id ][ (int) $figure['n'] ] = $mesure;
		}
		++$travail['exportees'];
		$travail['paquet'] = $paquet->etat();
		++$travail['suivante'];
		if ( $travail['suivante'] >= count( $travail['figures'] ) ) {
			$travail['etape'] = 'rubrique';
		}
		return 'avance';
	}

	/**
	 * Le document, les blocs d'index, la relecture et le lisez-moi, une fois
	 * toutes les figures posées. Puis la rubrique est faite.
	 */
	private static function finir( &$travail, $plugin, &$paquet, &$raison ) {
		$numero   = $travail['numero'];
		$rubrique = $travail['rubrique'];
		$paquet   = Notice_Archeomed_Paquet::reprendre( $travail['paquet'] );
		$erreur   = '';
		if ( ! $paquet->atelier_neuf( $erreur ) ) {
			$raison = $erreur;
			return 'manque';
		}
		// Les notices relues telles qu'elles sont, et chaque mesure rendue à
		// la sienne par son identifiant : une notice sortie de la rubrique
		// entre-temps ne décale pas les autres.
		$notices = $plugin->notices_a_assembler( isset( $travail['ids'] ) ? $travail['ids'] : array() );
		$plan    = array();
		foreach ( $notices as $rang_notice => $notice ) {
			$plan[ $rang_notice ] = isset( $travail['mesures'][ $notice['id'] ] ) ? (array) $travail['mesures'][ $notice['id'] ] : array();
		}
		$assemble = $plugin->assembler_la_rubrique( $rubrique, $notices, $plan, $paquet, $erreur );
		if ( null === $assemble ) {
			$raison = $erreur;
			return 'manque';
		}
		$nom = $paquet->nom_paquet();
		$a_deposer = array();
		foreach ( (array) glob( $paquet->atelier() . '/XML/indexation/*.xml' ) as $fichier ) {
			$a_deposer[ $fichier ] = $numero . '/XML/indexation/' . basename( $fichier );
		}
		$a_deposer[ $assemble['document'] ] = $numero . '/style/' . $nom . '.' . ( strtolower( pathinfo( $assemble['document'], PATHINFO_EXTENSION ) ) ?: 'docx' );
		foreach ( $a_deposer as $local => $distant ) {
			$refus = Notice_Archeomed_Sdrive::deposer( $local, $distant );
			if ( '' !== $refus ) {
				@unlink( $assemble['document'] );
				$raison = basename( $distant ) . ' : ' . $refus;
				return 'manque';
			}
			self::inscrire( $numero, $rubrique, $distant );
		}
		@unlink( $assemble['document'] );
		$textes = array(
			$numero . '/relecture_' . $nom . '.html' => (string) $assemble['relecture'],
			$numero . '/lisez-moi_' . $nom . '.txt'  => $paquet->lisez_moi_de_l_export(),
		);
		foreach ( $textes as $distant => $texte ) {
			if ( '' === $texte ) {
				continue;
			}
			$refus = Notice_Archeomed_Sdrive::ecrire( $texte, $distant );
			if ( '' !== $refus ) {
				$raison = basename( $distant ) . ' : ' . $refus;
				return 'manque';
			}
			self::inscrire( $numero, $rubrique, $distant );
		}
		self::retoucher( $numero, $rubrique, array(
			'dernier' => time(),
			'issue'   => 'ok',
			'texte'   => '',
			'figures' => (int) $travail['exportees'],
			'avis'    => $paquet->avis(),
		) );
		return 'fini';
	}
}
