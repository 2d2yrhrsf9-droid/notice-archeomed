<?php
/**
 * Ce qu'une notice laisse voir à qui la relit, avant qu'on la relise.
 *
 * Un texte coupé au collage, une figure appelée qui n'est pas jointe, une
 * photographie trop petite pour la page, « XIIème » : la rédaction le trouvait
 * à la relecture, notice par notice, et devait écrire à l'auteur. Ces
 * contrôles le disent au dépôt.
 *
 * Ce sont des avis, jamais des refus : une notice ne se perd pas parce qu'un
 * contrôle se trompe, et un renvoi aux figures du rapport — « rapport,
 * fig. 12 » — ressemble à s'y méprendre à un appel manqué. On avertit, on ne
 * bloque pas.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Controles {

	/** Ce qui clôt un paragraphe. */
	const PONCTUATION_FINALE = '.!?…:;';

	/** Ce qui peut suivre le point : guillemets, parenthèses, espaces. */
	const FERMANTS = "»\"'’”)] \u{00A0}\u{202F}\u{2009}\u{2007}\u{2008}\u{200A}\u{2002}\u{2003}\u{2005}";

	/**
	 * Les pixels qu'il faut pour la norme des photographies, petit côté puis
	 * grand côté. La norme se règle avec les autres, dans les normes
	 * éditoriales : l'aide du formulaire et ce contrôle la lisent au même
	 * endroit.
	 */
	public static function pixels_de_la_norme() {
		return Notice_Archeomed_Normes::pixels_des_photographies();
	}

	/**
	 * Tous les avis d'une saisie, en phrases prêtes à lire.
	 */
	public static function avis( $d ) {
		$avis = array();
		$paragraphes = self::paragraphes( isset( $d['texte_notice'] ) ? $d['texte_notice'] : '' );
		$figures     = isset( $d['illustrations'] ) ? (array) $d['illustrations'] : array();
		// Chaque famille d'avis se donne ou non, selon les normes de la revue.
		$familles = array(
			'ponctuation'      => function () use ( $paragraphes ) {
				return self::ponctuation_finale( $paragraphes );
			},
			'figures_appelees' => function () use ( $paragraphes, $figures ) {
				return self::appels_de_figure( implode( "\n", $paragraphes ), count( $figures ) );
			},
			'ordinaux'         => function () use ( $paragraphes ) {
				return self::ordinaux_fautifs( $paragraphes );
			},
			'annees_espacees'  => function () use ( $paragraphes ) {
				return self::dates_espacees( $paragraphes );
			},
			'personnes'        => function () use ( $d ) {
				return self::personnes( $d );
			},
		);
		foreach ( $familles as $famille => $calcul ) {
			if ( Notice_Archeomed_Normes::avis_actif( $famille ) ) {
				$avis = array_merge( $avis, $calcul() );
			}
		}
		$avis = array_merge( $avis, self::figures( $figures ) );
		$avis = array_merge( $avis, self::appels_de_note( isset( $d['texte_notice'] ) ? $d['texte_notice'] : '' ) );
		return array_values( array_unique( $avis ) );
	}

	/**
	 * Des appels de note sans leur note.
	 *
	 * Le formulaire ne reçoit pas de notes de bas de page : collée depuis
	 * Word, une note laisse son appel — en exposant, ou « [1] » — et son
	 * texte se perd, ou passe dans le corps. Rien ne le disait. Un exposant
	 * qui suit « m » est une unité (m², cm³) et non un appel.
	 */
	public static function appels_de_note( $html ) {
		$html   = (string) $html;
		$appels = array();
		if ( preg_match_all( '#(?<![mM])<sup>\s*(\d{1,3})\s*</sup>#u', $html, $m ) ) {
			$appels = array_merge( $appels, $m[1] );
		}
		$texte = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( preg_match_all( '#\[(\d{1,3})\]#u', $texte, $m ) ) {
			$appels = array_merge( $appels, $m[1] );
		}
		$appels = array_values( array_unique( $appels ) );
		if ( empty( $appels ) ) {
			return array();
		}
		return array( sprintf( 'Le texte porte %1$s (%2$s) sans le texte des notes, que le formulaire ne reçoit pas'
			. "\u{00A0}: voir avec l’auteur s’il faut les reprendre.",
			count( $appels ) > 1 ? 'des appels de note' : 'un appel de note',
			implode( ', ', array_map( function ( $n ) {
				return "«\u{00A0}" . $n . "\u{00A0}»";
			}, $appels ) ) ) );
	}

	/**
	 * Le texte de la notice en paragraphes de texte simple.
	 */
	public static function paragraphes( $html ) {
		$html = preg_replace( '#</(p|li|h[1-6]|blockquote|div)\s*>|<br\s*/?>#i', "\n", (string) $html );
		$texte = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$out   = array();
		foreach ( preg_split( '/\n+/u', $texte ) as $ligne ) {
			$ligne = trim( $ligne );
			if ( '' !== $ligne ) {
				$out[] = $ligne;
			}
		}
		return $out;
	}

	/**
	 * Un paragraphe qui ne finit par aucune ponctuation : presque toujours
	 * une phrase tronquée au collage. Et, cas à part, le paragraphe coupé en
	 * deux, dont la suite reprend en bas de casse — un retour de trop, venu
	 * d'un PDF de rapport. Proposer d'y « ajouter le point » écrirait « au
	 * contraire,. » : on le nomme donc autrement.
	 */
	public static function ponctuation_finale( $paragraphes ) {
		$avis = array();
		$n    = count( $paragraphes );
		foreach ( $paragraphes as $i => $p ) {
			if ( self::finit_bien( $p ) ) {
				continue;
			}
			$fin = self::extrait( $p, -40 );
			if ( $i + 1 < $n && self::reprend_en_bas_de_casse( $paragraphes[ $i + 1 ] ) ) {
				$avis[] = sprintf( 'Le paragraphe %1$d s’arrête au milieu d’une phrase que le suivant reprend (« …%2$s » puis « %3$s… ») : un retour à la ligne de trop, sans doute.',
					$i + 1, self::extrait( $p, -30 ), self::extrait( $paragraphes[ $i + 1 ], 30 ) );
			} else {
				$avis[] = sprintf( 'Le paragraphe %1$d ne se termine par aucune ponctuation (« …%2$s ») : texte tronqué ?', $i + 1, $fin );
			}
		}
		return $avis;
	}

	/**
	 * Les appels de figure du texte, confrontés aux figures jointes : « fig. 3 »
	 * quand deux figures seulement sont jointes, ou une figure que le texte
	 * n'appelle jamais.
	 */
	public static function appels_de_figure( $texte, $combien ) {
		$appeles = array();
		if ( preg_match_all( '/(*UCP)\b(?:fig(?:ure)?s?\.?|ill(?:ustration)?s?\.?)\s*(\d+[a-z]?(?:\s*(?:[-–—]|à|to|et|and|,|;|\/)\s*\d+[a-z]?){0,12})/iu',
			(string) $texte, $trouves ) ) {
			foreach ( $trouves[1] as $corps ) {
				foreach ( self::developper( $corps ) as $numero ) {
					$appeles[ $numero ] = true;
				}
			}
		}
		$avis = array();
		ksort( $appeles );
		foreach ( array_keys( $appeles ) as $numero ) {
			if ( $numero > $combien ) {
				$avis[] = 0 === $combien
					? sprintf( 'Le texte appelle la fig. %d, mais aucune figure n’est jointe.', $numero )
					: sprintf( 'Le texte appelle la fig. %1$d, mais %2$s seulement %3$s jointe%4$s.', $numero,
						self::en_lettres( $combien ), 1 === $combien ? 'est' : 'sont', 1 === $combien ? '' : 's' );
			}
		}
		if ( ! empty( $appeles ) ) {
			for ( $rang = 1; $rang <= $combien; $rang++ ) {
				if ( ! isset( $appeles[ $rang ] ) ) {
					$avis[] = sprintf( 'La fig. %d n’est appelée nulle part dans le texte.', $rang );
				}
			}
		}
		return $avis;
	}

	/**
	 * « 2ème », « XIIème », « 1ère », « 2nde » : l'abréviation juste est « 2e »,
	 * « XIIe », « 1re », « 2de ». On le signale sans corriger : ce peut être
	 * une citation.
	 */
	public static function ordinaux_fautifs( $paragraphes ) {
		$avis = array();
		$motif = '/(*UCP)\b(\d+|[IVXLC]{1,6})(èmes?|emes?|ièmes?|iemes?|è)\b|\b(1|I)(ères?|eres?)\b|\b(\d+)(nde|nd)\b/u';
		foreach ( $paragraphes as $p ) {
			if ( ! preg_match_all( $motif, $p, $trouves, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $trouves as $m ) {
				$nombre  = '' !== ( isset( $m[1] ) ? $m[1] : '' ) ? $m[1] : ( ! empty( $m[3] ) ? $m[3] : $m[5] );
				$suite   = ! empty( $m[2] ) ? $m[2] : ( ! empty( $m[4] ) ? $m[4] : $m[6] );
				$pluriel = 's' === substr( $suite, -1 ) ? 's' : '';
				$suite   = 'nd' === $suite ? $suite : rtrim( $suite, 's' );
				if ( in_array( $suite, array( 'ère', 'ere' ), true ) ) {
					$juste = $nombre . 're' . $pluriel;
				} elseif ( in_array( $suite, array( 'nde', 'nd' ), true ) ) {
					$juste = $nombre . 'de';
				} elseif ( in_array( $nombre, array( '1', 'I' ), true ) ) {
					$juste = $nombre . 'er' . $pluriel;
				} else {
					$juste = $nombre . 'e' . $pluriel;
				}
				$avis[] = sprintf( '« %1$s » s’abrège « %2$s ».', $m[0], $juste );
			}
		}
		return $avis;
	}

	/**
	 * « vers 1 250 », « les années 1 960 » : une année tapée avec une espace.
	 * La typographie ne la soude pas en tranche de milliers ; on la montre.
	 */
	public static function dates_espacees( $paragraphes ) {
		$avis = array();
		foreach ( $paragraphes as $p ) {
			if ( preg_match_all( '/(*UCP)(?:\b(?:en|vers|an|années?|dès|depuis|jusqu’en|jusqu\'en|avant|après)\s+([12] \d{3})\b|([12] \d{3})\s*(?:av\.|apr?\.|ap\.)\s*J\.-C\.)/iu', $p, $trouves, PREG_SET_ORDER ) ) {
				foreach ( $trouves as $m ) {
					$annee  = trim( ! empty( $m[1] ) ? $m[1] : $m[2] );
					$avis[] = sprintf( '« %1$s » : une année s’écrit sans espace, « %2$s ».', $annee, str_replace( ' ', '', $annee ) );
				}
			}
		}
		return $avis;
	}

	/**
	 * Les figures : leur définition réelle contre la norme annoncée, et
	 * leurs crédits. C'est le nombre de pixels qui décide, non la densité
	 * inscrite dans le fichier : 710 pixels à 300 ppp font six centimètres,
	 * quoi que dise l'en-tête.
	 */
	public static function figures( $figures ) {
		$avis = array();
		list( $petit, $grand ) = self::pixels_de_la_norme();
		foreach ( $figures as $item ) {
			$rang = isset( $item['rang'] ) ? (int) $item['rang'] : 0;
			if ( ! empty( $item['pixels'] ) && is_array( $item['pixels'] ) && Notice_Archeomed_Normes::avis_actif( 'definition' ) ) {
				$l = (int) $item['pixels'][0];
				$h = (int) $item['pixels'][1];
				$ppp = (int) Notice_Archeomed_Normes::valeur( 'photo_ppp' );
				if ( min( $l, $h ) < $petit || max( $l, $h ) < $grand ) {
					$avis[] = sprintf( '%1$s : %2$s × %3$s pixels, soit %4$s × %5$s cm à %6$d ppp — sous la norme de %7$d × %8$d cm. À vérifier s’il s’agit d’une photographie.',
						Notice_Archeomed_Normes::numero_de_figure( $rang ), number_format_i18n( $l ), number_format_i18n( $h ),
						number_format_i18n( $l / $ppp * 2.54, 1 ), number_format_i18n( $h / $ppp * 2.54, 1 ),
						$ppp, (int) Notice_Archeomed_Normes::valeur( 'photo_largeur_cm' ), (int) Notice_Archeomed_Normes::valeur( 'photo_hauteur_cm' ) );
				}
			}
			$titre   = isset( $item['titre'] ) ? trim( $item['titre'] ) : '';
			$legende = isset( $item['legende'] ) ? trim( $item['legende'] ) : '';
			$credits = isset( $item['credits'] ) ? trim( $item['credits'] ) : '';
			if ( '' === $credits && '' !== $titre . $legende && Notice_Archeomed_Normes::avis_actif( 'credits' ) ) {
				$avis[] = sprintf( '%s : pas de crédits (auteur, détenteur des droits).', Notice_Archeomed_Normes::numero_de_figure( $rang ) );
			}
		}
		return $avis;
	}

	/**
	 * Les personnes : une seule par champ, une institution qui n'est pas une
	 * adresse, un nom qui n'est pas tapé en capitales.
	 */
	public static function personnes( $d ) {
		$avis  = array();
		$roles = array(
			'resp'     => 'du responsable',
			'coresp'   => 'du co-responsable',
			'coauteur' => 'du co-auteur',
		);
		foreach ( $roles as $cle => $qui ) {
			$prenom = isset( $d[ $cle . '_prenom' ] ) ? trim( $d[ $cle . '_prenom' ] ) : '';
			$nom    = isset( $d[ $cle . '_nom' ] ) ? trim( $d[ $cle . '_nom' ] ) : '';
			$inst   = isset( $d[ $cle . '_inst' ] ) ? trim( $d[ $cle . '_inst' ] ) : '';
			if ( '' === $prenom . $nom ) {
				continue;
			}
			// Métopes attend une personne par entrée : « Jean Dupont, Marie
			// Martin » dans un champ en fait une seule.
			if ( preg_match( '/(*UCP)[,;&]|\s(?:et|and)\s/u', $prenom . ' ' . $nom ) ) {
				$avis[] = sprintf( 'Le nom %1$s semble contenir plusieurs personnes (« %2$s ») : une seule par champ.', $qui, trim( $prenom . ' ' . $nom ) );
			}
			if ( self::en_capitales( $nom ) ) {
				$avis[] = sprintf( 'Le nom %1$s est en capitales (« %2$s ») : à écrire « %3$s » si c’est bien sa graphie.', $qui, $nom,
					function_exists( 'mb_convert_case' ) ? mb_convert_case( $nom, MB_CASE_TITLE, 'UTF-8' ) : ucwords( strtolower( $nom ) ) );
			}
			if ( '' !== $inst && false !== strpos( $inst, '@' ) ) {
				$avis[] = sprintf( 'L’institution %1$s contient une adresse électronique (« %2$s »).', $qui, $inst );
			}
			if ( '' === $inst && 'resp' !== $cle ) {
				$avis[] = sprintf( 'Pas d’institution de rattachement pour le nom %1$s (« %2$s »).', $qui, trim( $prenom . ' ' . $nom ) );
			}
		}
		return $avis;
	}

	/**
	 * Un titre sans le point final qu'on y met par habitude — sauf quand le
	 * point appartient à une abréviation : « Le XXe s. », « av. J.-C. ».
	 */
	public static function sans_point_final( $titre ) {
		$titre = rtrim( (string) $titre );
		if ( '.' !== substr( $titre, -1 ) || '...' === substr( $titre, -3 )
			|| preg_match( '/(*UCP)(?:^|\s)(?:\w{1,3}|\w+\.\S*)\.$/u', $titre ) ) {
			return $titre;
		}
		return rtrim( substr( $titre, 0, -1 ) );
	}

	/**
	 * Le titre d'une figure sans le numéro que l'auteur y a retapé :
	 * « Fig. 1 : Plan » donnait « Fig. 1 Fig. 1 : Plan ». On ne retire que le
	 * numéro qui est bien celui de la figure.
	 */
	public static function titre_sans_numero( $titre, $rang ) {
		$titre = (string) $titre;
		if ( preg_match( '/(*UCP)^\s*(?:fig(?:ure)?|ill(?:ustration)?)\.?\s*(\d+)\s*[:.,—–-]?\s*/iu', $titre, $m )
			&& (int) $m[1] === (int) $rang ) {
			return (string) substr( $titre, strlen( $m[0] ) );
		}
		return $titre;
	}

	// ── Outils ───────────────────────────────────────────────────────────

	private static function finit_bien( $texte ) {
		$texte = preg_replace( '/[' . preg_quote( self::FERMANTS, '/' ) . ']+$/u', '', trim( $texte ) );
		$dernier = preg_match( '/.$/us', (string) $texte, $m ) ? $m[0] : '';
		return '' !== $dernier && false !== strpos( self::PONCTUATION_FINALE, $dernier );
	}

	private static function reprend_en_bas_de_casse( $texte ) {
		$debut = preg_replace( '/^[«“‘(\[{\x{2009}\x{A0} ]+/u', '', (string) $texte );
		return (bool) preg_match( '/^\p{Ll}/u', $debut );
	}

	/** « 3-5 » donne 3, 4, 5 ; « 4 et 6 » donne 4 et 6 ; « 2a » donne 2. */
	private static function developper( $corps ) {
		$corps = preg_replace( '/\s+/u', ' ', trim( $corps ) );
		if ( preg_match( '/^(\d+)\s*(?:[-–—]|à|to)\s*(\d+)$/iu', $corps, $m )
			&& (int) $m[1] <= (int) $m[2] && (int) $m[2] - (int) $m[1] <= 100 ) {
			return range( (int) $m[1], (int) $m[2] );
		}
		preg_match_all( '/\d+/', $corps, $n );
		return array_unique( array_map( 'intval', $n[0] ) );
	}

	private static function en_capitales( $texte ) {
		$lettres = preg_replace( '/\PL/u', '', (string) $texte );
		$combien = preg_match_all( '/./us', $lettres );
		return $combien >= 4 && ! preg_match( '/\p{Ll}/u', $lettres );
	}

	private static function extrait( $texte, $longueur ) {
		if ( ! function_exists( 'mb_substr' ) ) {
			return $longueur < 0 ? substr( $texte, $longueur ) : substr( $texte, 0, $longueur );
		}
		return $longueur < 0 ? mb_substr( $texte, $longueur, null, 'UTF-8' ) : mb_substr( $texte, 0, $longueur, 'UTF-8' );
	}

	private static function en_lettres( $n ) {
		$mots = array( 1 => 'une figure', 2 => 'deux figures', 3 => 'trois figures' );
		return isset( $mots[ $n ] ) ? $mots[ $n ] : $n . ' figures';
	}
}
