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
	 * La longueur au-delà de laquelle un texte alternatif reçoit un avis :
	 * cent cinquante caractères, règle de la rédaction. Le RGAA 4.1, plus
	 * strict, « recommande fortement » quatre-vingts caractères au glossaire
	 * (« Alternative courte et concise », test 1.3.9) : une plage braille ou
	 * une loupe se manipulent à chaque ligne de trop.
	 */
	const ALT_CONSEILLE = 150;

	/**
	 * La longueur au-delà de laquelle il se coupe — trois cents caractères,
	 * règle de la rédaction —, la coupe se nommant dans les avis comme pour
	 * les autres champs. Le formulaire ne laisse pas taper au-delà.
	 */
	const ALT_MAX = 300;

	/**
	 * La longueur au-delà de laquelle la description détaillée d'une figure
	 * se coupe, la coupe se nommant dans les avis. Règle de la rédaction.
	 */
	const DESCRIPTION_MAX = 2000;

	/**
	 * Les débuts qu'un texte alternatif n'a pas à prendre : le lecteur
	 * d'écran annonce déjà une image.
	 */
	const ALT_DEBUTS = '/(*UCP)^(?:image|photo|photographie|illustration)\s+(?:de|du|des|d[’\'])/iu';

	/**
	 * Une information portée par la seule couleur : « en rouge », « zones
	 * vertes ». [HYPOTHÈSE] La liste des couleurs et des mots qu'elles
	 * qualifient est une heuristique : elle avertit, elle ne refuse rien.
	 */
	const COULEUR_SEULE = '/(*UCP)\b(?:en\s+(?:rouge|bleu|vert|jaune|orange|violet|rose|gris|noir|blanc)\b|(?:zones?|traits?|points?|tracés?|surfaces?|aplats?|hachures?|parties?|cercles?|flèches?|lignes?|contours?|plages?|secteurs?)\s+(?:rouges?|bleue?s?|verte?s?|jaunes?|oranges?|violette?s?|roses?|grise?s?|noire?s?|blanche?s?)\b)/iu';

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
			// Un texte alternatif trop long se lit mal à la plage braille : on
			// le dit, sans rien refuser. Sans réglage : c'est une exigence
			// d'accessibilité de la revue, non une préférence de style.
			$alt = isset( $item['alt'] ) && is_scalar( $item['alt'] ) ? self::une_ligne( $item['alt'] ) : '';
			$fig = Notice_Archeomed_Normes::numero_de_figure( $rang );
			$longueur = function_exists( 'mb_strlen' ) ? mb_strlen( $alt, 'UTF-8' ) : strlen( $alt );
			if ( $longueur > self::ALT_CONSEILLE ) {
				$avis[] = sprintf( "%1\$s\u{00A0}: le texte alternatif fait %2\$d caractères, pour %3\$d au plus conseillés\u{00A0}; le détail a sa place dans la légende.",
					$fig, $longueur, self::ALT_CONSEILLE );
			}
			if ( '' !== $alt && '' !== $titre && self::plier( $alt ) === self::plier( self::titre_sans_numero( $titre, $rang ) ) ) {
				$avis[] = sprintf( "%s\u{00A0}: le texte alternatif recopie le titre, au lieu de dire ce que montre l’image.", $fig );
			}
			if ( '' !== $alt && preg_match( self::ALT_DEBUTS, $alt ) ) {
				$avis[] = sprintf( "%1\$s\u{00A0}: le texte alternatif commence par «\u{00A0}%2\$s\u{00A0}», que le lecteur d’écran annonce déjà.",
					$fig, implode( ' ', array_slice( preg_split( '/\s+/u', $alt ), 0, 2 ) ) );
			}
			if ( preg_match( self::COULEUR_SEULE, $alt . "\n" . $legende, $couleur ) ) {
				$avis[] = sprintf( "%1\$s\u{00A0}: «\u{00A0}%2\$s\u{00A0}» — l’information ne doit pas reposer sur la seule couleur.",
					$fig, $couleur[0] );
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

	/**
	 * Le texte alternatif d'une figure : celui que l'auteur a écrit, ou, pour
	 * une notice d'avant le champ, son titre sans le numéro. Vide si la
	 * figure n'a ni l'un ni l'autre.
	 */
	public static function texte_alternatif( $item ) {
		if ( ! is_array( $item ) ) {
			return '';
		}
		$alt = isset( $item['alt'] ) && is_scalar( $item['alt'] ) ? self::une_ligne( $item['alt'] ) : '';
		if ( '' !== $alt ) {
			return $alt;
		}
		$titre = isset( $item['titre'] ) && is_scalar( $item['titre'] ) ? (string) $item['titre'] : '';
		return self::une_ligne( self::titre_sans_numero( $titre, isset( $item['rang'] ) ? (int) $item['rang'] : 0 ) );
	}

	/** Un texte sur une ligne : retours et blancs multiples ramenés à une espace. */
	public static function une_ligne( $texte ) {
		$propre = preg_replace( '/[\s\x{00A0}]+/u', ' ', (string) $texte );
		return trim( null === $propre ? (string) $texte : $propre );
	}

	// ── Outils ───────────────────────────────────────────────────────────

	/** Un texte comparé sans casse, accents ni ponctuation. */
	private static function plier( $texte ) {
		$texte = function_exists( 'remove_accents' ) ? remove_accents( (string) $texte ) : (string) $texte;
		return trim( strtolower( (string) preg_replace( '/[^a-zA-Z0-9]+/', ' ', $texte ) ) );
	}

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
