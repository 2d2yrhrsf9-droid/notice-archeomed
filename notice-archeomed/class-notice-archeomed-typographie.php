<?php
/**
 * La typographie française du texte imprimé.
 *
 * Métopes fournit deux macros que le relecteur lance avant toute chose :
 * rétablir les espaces insécables devant la ponctuation double, et remplacer
 * les apostrophes droites par des apostrophes typographiques. Ce sont des
 * corrections sans arbitrage — la réponse est toujours la même —, et les
 * laisser à la relecture revient à faire à la main, trois cents notices par
 * an, ce qu'une règle sait faire.
 *
 * La maison ne veut pas d'espace fine, mais l'insécable : les fines que Word
 * sème deviennent donc des insécables. Une adresse ne se corrige jamais :
 * « https://… » n'a pas d'espace avant ses deux-points, et une insécable ou
 * une apostrophe courbe y casserait le lien.
 *
 * Toutes les expressions portent « (*UCP) » : sans lui, « \b », « \w » et
 * « \s » ne connaissent que l'ASCII, et « église » ou l'insécable leur
 * échapperaient.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Typographie {

	const INSECABLE = "\u{00A0}";

	/** La famille des fines : fine, ultra-fine, fine insécable, et leurs cousines. */
	const FINES = "\u{2009}\u{200A}\u{202F}\u{2005}\u{2006}";

	const CAPITALE = 'A-ZÀ-ÖØ-Þ';

	/** Une initiale, éventuellement composée : « J.-P. ». */
	const INITIALE = '[A-ZÀ-ÖØ-Þ]\.(?:-[A-ZÀ-ÖØ-Þ]\.)*';

	/**
	 * Les abréviations de renvoi suivies de leur numéro : « p. 12 »,
	 * « fig. 3 », « pl. IV ». En minuscules : « P. Durand » est une initiale.
	 */
	const ABREGES = 'pp|p|vol|col|t|fig|pl|n|fasc|chap|suppl|ill|tabl|not|cat|inv';

	/**
	 * Les mots-outils de l'anglais. « An » et « New » n'y sont pas : « AN
	 * 19900208/87 » cote les Archives nationales, et « New York » est un lieu
	 * d'édition.
	 */
	const MOTS_ANGLAIS = 'the of and in to for with from by at its their is are was were into toward towards between through during after before among this that which what how why study studies or since we our these those has have been not can';

	/**
	 * Et ceux de l'espagnol, de l'allemand, de l'italien et du portugais, qui
	 * n'existent pas en français.
	 */
	const MOTS_ETRANGERS = 'el los las del y una para por con sobre como sus desde hasta según sin der die das und von zu im mit für auf ein eine einer eines den dem zur zum bei aus über nach vom ist sind nicht auch oder wie della delle dei degli nel nella nelle negli per di da sul sulla alla alle agli come tra fra gli os do dos das no na nas em um uma pelo pela ao aos';

	const MOTS_FRANCAIS = 'le la les l des du de d et un une en dans pour par sur au aux est sont que qui ce cette ces il elle ils elles se sa son ses leur leurs pas plus ne avec entre comme où mais ou donc voir cf à notamment ainsi suivant suivants suivante suivantes exemple également aussi dont selon soit puis alors après avant depuis lors chez sans sous vers très tout tous toute toutes quel quelle quels quelles';

	/** Des mots français que d'autres langues écrivent aussi : ils ne comptent pour personne. */
	const MOTS_COMMUNS = 'la de d l en que entre il se son des nos un';

	/** Les pronoms qui ouvrent une phrase française. */
	const PRONOMS = 'il elle ils elles on nous vous je j';

	/** Les listes, lues une fois. */
	private static $listes = null;

	/**
	 * Les deux corrections, dans l'ordre où Métopes les enchaîne.
	 *
	 * « $avant » porte ce qui précède immédiatement, dans le fragment voisin :
	 * un texte se coupe en fragments où bon lui semble — au milieu d'un mot,
	 * juste devant un deux-points —, et corriger chaque fragment isolément
	 * laissait passer toute ponctuation tombée en tête de fragment.
	 * « $contexte_avant » et « $contexte_apres » portent le reste du
	 * paragraphe : ils disent si un deux-points tombe dans un titre anglais.
	 */
	public static function corriger( $texte, $avant = '', $langue = 'fr', $contexte_avant = '', $contexte_apres = '' ) {
		$texte = (string) $texte;
		$avant = (string) $avant;
		// Un texte qui n'est pas de l'UTF-8 valide ferait échouer chaque
		// expression : on le rend tel quel, l'échappement s'en charge.
		if ( '' === $texte || ! preg_match( '//u', $texte . $avant . $contexte_avant . $contexte_apres ) ) {
			return $texte;
		}
		// Un guillemet ouvrant en fin de fragment : c'est le fragment d'après
		// qui pose l'insécable, en le voyant par son amorce. Sans cela on en
		// aurait deux.
		$queue = '';
		if ( '«' === self::dernier( $texte ) ) {
			$texte = substr( $texte, 0, -strlen( '«' ) );
			$queue = '«';
		}
		$amorce = '' !== $avant ? self::dernier( $avant ) : '';
		// Le contexte de gauche s'arrête avant l'amorce, qui est déjà dans le
		// texte qu'on corrige.
		$gauche = ( '' !== $amorce && self::finit_par( $contexte_avant, $amorce ) )
			? substr( $contexte_avant, 0, -strlen( $amorce ) ) : $contexte_avant;
		$rendu = self::apostrophes_typographiques(
			self::retablir_insecables( $amorce . $texte, $langue, $gauche, $contexte_apres ) );
		if ( '' !== $amorce ) {
			// La règle peut glisser une espace devant l'amorce — un guillemet
			// fermant en réclame une : on la retire avec elle.
			// « ltrim » lit des octets : il rognerait le premier octet d'un
			// « « » (C2 AB) comme celui d'une insécable (C2 A0).
			$sans  = preg_replace( '/^[ \x{A0}\x{202F}]+/u', '', $rendu );
			$rendu = 0 === strpos( $sans, $amorce )
				? substr( $sans, strlen( $amorce ) ) : substr( $rendu, strlen( $amorce ) );
		}
		$rendu .= $queue;
		// L'amorce ne porte qu'un caractère — assez pour la ponctuation
		// double, trop peu pour un nom : l'initiale s'attache à part.
		return self::attacher_l_initiale( $rendu, $avant );
	}

	/**
	 * Pose l'espace insécable que la ponctuation double française réclame,
	 * et celles que réclament les nombres, les renvois et les initiales.
	 */
	public static function retablir_insecables( $texte, $langue = 'fr', $contexte_avant = '', $contexte_apres = '' ) {
		$texte = (string) $texte;
		if ( '' === $texte ) {
			return $texte;
		}
		$ins      = self::INSECABLE;
		$espacee  = self::espace_la_ponctuation( $langue );
		// L'espace qu'un passage étranger ne veut pas devant « : » s'ôte
		// d'abord — partout, si le texte entier est d'une langue qui colle sa
		// ponctuation.
		foreach ( array_reverse( self::espaces_anglaises( $texte, $contexte_avant, $contexte_apres, ! $espacee ) ) as $zone ) {
			$texte = substr( $texte, 0, $zone[0] ) . substr( $texte, $zone[1] );
		}
		// Une fine entre deux lettres n'a rien à tenir : c'est une espace de
		// mot que Word a composée fine. Elle redevient ordinaire ; les autres,
		// insécables.
		$texte = preg_replace( '/(*UCP)(?<=[^\W\d_])[' . self::FINES . '](?=[^\W\d_])/u', ' ', $texte );
		$texte = preg_replace( '/[' . self::FINES . ']/u', $ins, $texte );
		$zones = self::protegees( $texte );

		// La ponctuation double veut l'insécable devant elle. Pas après une
		// parenthèse ou un crochet ouvrant — « (?) », constant en archéologie
		// pour une datation incertaine —, ni après un autre signe double.
		$rendu = $texte;
		if ( $espacee ) {
			$rendu = self::remplacer( '/(*UCP)(?<=[^\s\x{A0}\x{202F}(\[{?!])([ \t]*)([;:!?])/u', $texte,
				function ( $m, $debut, $fin ) use ( $zones, $texte, $contexte_avant, $contexte_apres, $ins ) {
					if ( self::dans( $m[2][1], $zones ) ) {
						return $m[0][0];
					}
					// Un titre anglais au milieu du français : le signe reste
					// collé, comme l'imprimé l'écrit.
					$gauche = $contexte_avant . substr( $texte, 0, $debut );
					if ( self::gauche_etrangere( $gauche ) && self::ponctuation_etrangere(
						$gauche, substr( $texte, $fin ) . $contexte_apres, $m[2][0] ) ) {
						return $m[0][0];
					}
					return $ins . $m[2][0];
				} );
		}
		// Les guillemets : l'insécable à l'intérieur du couple, une espace
		// ordinaire à l'extérieur.
		$rendu = preg_replace( '/«[ \t\x{A0}]*/u', '«' . $ins, $rendu );
		$rendu = preg_replace( '/[ \t\x{A0}]*»/u', $ins . '»', $rendu );
		$rendu = preg_replace( '/(*UCP)(?<=\S)[ \t\x{A0}]+(?=«)/u', ' ', $rendu );

		$adresses = self::adresses( $rendu );
		// Le pourcentage et les monnaies, après un nombre : « 50 % ».
		if ( $espacee ) {
			$rendu = self::remplacer( '/(?<=[0-9])[ \t\x{A0}]*([%€$£¥])/u', $rendu,
				function ( $m, $debut ) use ( $adresses, $ins ) {
					return self::dans( $debut, $adresses ) ? $m[0][0] : $ins . $m[1][0];
				} );
		}
		// Le nombre et son unité, là où une espace les sépare déjà.
		$rendu = self::remplacer( '/(*UCP)(?<=\d)[ \t](?=(?:[kcmµ]?m|m²|m³|km²|ha|[km]?g|°C|°|‰|CHF|min|h)(?![\w’\']))/u', $rendu,
			function ( $m, $debut ) use ( $adresses, $ins ) {
				return self::dans( $debut, $adresses ) ? $m[0][0] : $ins;
			} );
		// Les tranches de trois chiffres : « 10 000 ». Une année tapée avec
		// une espace — « vers 1 250 » — ne se soude pas.
		$dates = array();
		if ( preg_match_all( '/(*UCP)(?:\b(?:en|vers|an|années?|dès|depuis|jusqu’en|jusqu\'en|avant|après)\s+([12] \d{3})\b|([12] \d{3})\s*(?:av\.|apr?\.|ap\.)\s*J\.-C\.)/iu',
			$rendu, $trouves, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $trouves[0] as $t ) {
				$dates[] = array( $t[1], $t[1] + strlen( $t[0] ) );
			}
		}
		$rendu = self::remplacer( '/(*UCP)(?<![\d,.])(?<!\d )\d{1,3}(?: \d{3})+(?!\d)/u', $rendu,
			function ( $m, $debut ) use ( $dates, $ins ) {
				return self::dans( $debut, $dates ) ? $m[0][0] : str_replace( ' ', $ins, $m[0][0] );
			} );
		// Le siècle et son « s. », l'ère, l'heure, les civilités, le signe
		// de multiplication.
		$rendu = self::hors_adresse( '/(*UCP)\b([IVXLC]+(?:e|er|re))[ \t](?=s\.)/u', $rendu,
			function ( $m ) use ( $ins ) {
				return $m[1][0] . $ins;
			} );
		$rendu = preg_replace( '/(*UCP)(?<=\d)[ \t](?=(?:av|apr?)\.[ \t\x{A0}]*J\.-C\.)/u', $ins, $rendu );
		$rendu = preg_replace( '/(*UCP)\b((?:av|apr?)\.)[ \t](?=J\.-C\.)/u', '$1' . $ins, $rendu );
		$rendu = preg_replace( '/(*UCP)(?<=\d)[ \t\x{A0}]h[ \t](?=\d{2}\b)/u', $ins . 'h' . $ins, $rendu );
		$rendu = preg_replace( '/(*UCP)\b(M\.|MM\.|Mme|Mmes|Mlle|Mlles|Dr|Pr|Me|Mgr|St|Ste)[ \t](?=[A-ZÀ-Ý])/u', '$1' . $ins, $rendu );
		$rendu = preg_replace( '/(*UCP)(?<=\d)[ \t\x{A0}][xX][ \t\x{A0}](?=\d)/u', $ins . '×' . $ins, $rendu );
		// Le signe égal, là où des espaces le séparent déjà : « n = 12 ».
		$rendu = self::remplacer( '/[ \t\x{A0}]+=[ \t\x{A0}]+/u', $rendu,
			function ( $m, $debut ) use ( $adresses, $ins ) {
				return self::dans( $debut, $adresses ) ? $m[0][0] : $ins . '=' . $ins;
			} );

		// Ce que Métopes corrige de son côté, hors des adresses.
		$rendu = self::hors_adresse( '/[ \t]{2,}/u', $rendu, ' ' );
		// Jamais de points de suspension après « etc. ».
		$rendu = self::hors_adresse( '/(*UCP)\betc(?:\.\.\.|…)/u', $rendu, 'etc.' );
		$rendu = self::hors_adresse( '/(?<!\.)\.\.\.(?!\.)/u', $rendu, '…' );
		$rendu = self::hors_adresse( '/(*UCP)\bet[ \t\x{A0}]+al\./iu', $rendu,
			function ( $m ) use ( $ins ) {
				$mots = preg_split( '/(*UCP)\s+/u', $m[0][0] );
				return $mots[0] . $ins . 'al.';
			} );
		$rendu = self::hors_adresse( '/(*UCP)(?<![\w°.\-])(' . self::ABREGES . ')(\.)[ \t\x{A0}]*(?=\d|[IVXLC]+\b)/u', $rendu,
			function ( $m ) use ( $ins ) {
				return $m[1][0] . $m[2][0] . $ins;
			} );
		$rendu = self::hors_adresse( '/(*UCP)(?<![\w])([Nn]°)[ \t\x{A0}]*(?=\d)/u', $rendu,
			function ( $m ) use ( $ins ) {
				return $m[1][0] . $ins;
			} );
		$rendu = self::hors_adresse( '/(*UCP)(?<=\S)[ \t\x{A0}]+(?=&)/u', $rendu, $ins );
		// Les initiales en dernier : « J. Guilaine », puis le nom devant ses
		// initiales, en deux passes — « CANTI M. G. ».
		$rendu = self::hors_adresse( '/(*UCP)\b(' . self::INITIALE . ')[ \t]+(?=[' . self::CAPITALE . '])/u', $rendu,
			function ( $m ) use ( $ins ) {
				return $m[1][0] . $ins;
			} );
		for ( $i = 0; $i < 2; $i++ ) {
			$rendu = self::hors_adresse( '/(*UCP)\b([' . self::CAPITALE . '][^\W\d_]*(?:[-\'’][^\W\d_]+)*)[ \t]+(?=' . self::INITIALE . ')/u', $rendu,
				function ( $m ) use ( $ins ) {
					return $m[1][0] . $ins;
				} );
		}
		return $rendu;
	}

	/**
	 * Remplace l'apostrophe droite du clavier par la courbe — sauf dans une
	 * adresse, qui se casserait, et après un chiffre, où c'est un prime :
	 * « 43°36'12'' N » veut ′ et ″.
	 */
	public static function apostrophes_typographiques( $texte ) {
		$texte  = (string) $texte;
		$rendu  = '';
		$depuis = 0;
		foreach ( self::adresses( $texte ) as $zone ) {
			$rendu .= self::courbes( substr( $texte, $depuis, $zone[0] - $depuis ) )
				. substr( $texte, $zone[0], $zone[1] - $zone[0] );
			$depuis = $zone[1];
		}
		return $rendu . self::courbes( substr( $texte, $depuis ) );
	}

	private static function courbes( $texte ) {
		$texte = preg_replace( "/(?<=\\d)''/u", "\u{2033}", $texte );
		$texte = preg_replace( "/(?<=\\d)'/u", "\u{2032}", $texte );
		return str_replace( "'", "\u{2019}", $texte );
	}

	/**
	 * L'initiale tombée en tête de fragment : son nom est dans le fragment
	 * d'avant, ou l'inverse.
	 */
	private static function attacher_l_initiale( $texte, $avant ) {
		if ( '' === $avant || '' === $texte ) {
			return $texte;
		}
		$cap = self::CAPITALE;
		$ini = self::INITIALE;
		$attache = ( preg_match( '/(*UCP)^[ \t]' . $ini . '/u', $texte )
				&& preg_match( '/(*UCP)\b[' . $cap . '][^\W\d_]*(?:[-\'’][^\W\d_]+)*$/u', $avant ) )
			|| ( preg_match( '/(*UCP)^[ \t][' . $cap . ']/u', $texte )
				&& preg_match( '/(*UCP)\b' . $ini . '$/u', $avant ) );
		return $attache ? self::INSECABLE . substr( $texte, 1 ) : $texte;
	}

	// ── Les passages en langue étrangère ────────────────────────────────

	/** Les langues qui séparent le mot de sa ponctuation double. */
	private static function espace_la_ponctuation( $langue ) {
		$langue = strtolower( (string) ( '' !== (string) $langue ? $langue : 'fr' ) );
		$langue = explode( '-', $langue );
		return in_array( $langue[0], array( 'fr', 'br', 'oc', 'co' ), true );
	}

	/**
	 * Le signe entre ces deux textes appartient-il à un passage en langue
	 * étrangère, qui colle la ponctuation double à son mot ?
	 *
	 * Il faut des mots-outils de ces langues et aucun français dans le
	 * segment qui le porte — deux, ou un seul si le segment a la casse des
	 * titres anglais.
	 */
	public static function ponctuation_etrangere( $gauche, $droite, $signe = ':', $texte_etranger = false ) {
		$l      = self::listes();
		$avant  = self::mots_du_segment( $gauche, true );
		$apres  = self::mots_du_segment( $droite, false );
		// La norme bibliographique de la revue, en toute langue : « In : »,
		// le deux-points devant les pages, entre le lieu et l'éditeur.
		if ( ! empty( $avant ) && in_array( self::bas( end( $avant ) ), array( 'in', 'dans', 'en' ), true ) ) {
			return false;
		}
		if ( preg_match( '/(*UCP)^\s*\d/u', $droite ) ) {
			return false;
		}
		$editeurs = '/(*UCP)\b(?:Press|Publishers?|Publishing|University|Universit[yé]|Verlag|Books|Éditions|Editions|Academic)\b/u';
		if ( ( preg_match( $editeurs, implode( ' ', $avant ) ) && count( $apres ) <= 3 )
			|| ( preg_match( $editeurs, implode( ' ', $apres ) ) && count( $avant ) <= 2 ) ) {
			return false;
		}
		// Un mot français juste après le signe, ou juste avant : c'est la
		// ponctuation d'un passage français.
		$morceaux_droite = self::couper_aux_bornes( $droite );
		$morceaux_gauche = self::couper_aux_bornes( $gauche );
		$premiers_apres  = array_slice( self::mots( $morceaux_droite[0] ), 0, 3 );
		$derniers_avant  = array_slice( self::mots( end( $morceaux_gauche ) ), -3 );
		$voisins         = array_merge( $premiers_apres, $derniers_avant );
		foreach ( $voisins as $mot ) {
			if ( self::francais( $mot ) ) {
				return false;
			}
		}
		if ( ! empty( $premiers_apres ) && isset( $l['pronoms'][ self::bas( $premiers_apres[0] ) ] ) ) {
			return false;
		}
		foreach ( $voisins as $mot ) {
			if ( preg_match( '/[èêîûëïœÈÊÎÛËÏŒ]/u', $mot ) && ! self::majuscule( $mot ) ) {
				return false;
			}
		}
		if ( $texte_etranger ) {
			return true;
		}
		// Un signe après un nombre ouvre une liste ou une pagination.
		if ( preg_match( '/(*UCP)\d\W*$/u', rtrim( $gauche ) ) ) {
			return false;
		}
		// Un intitulé seul en tête de paragraphe suit la langue de l'article,
		// sauf si la suite parle clairement une autre langue.
		if ( 1 === count( self::mots( $gauche ) ) && ! preg_match( '/[.,;!?()«»]/u', $gauche ) ) {
			$outils = 0;
			foreach ( $apres as $mot ) {
				if ( isset( $l['etrangers'][ self::bas( $mot ) ] ) ) {
					$outils++;
				}
			}
			if ( $outils < 2 ) {
				return false;
			}
		}
		$mots = array_merge( $avant, $apres );
		if ( empty( $mots ) ) {
			return false;
		}
		// Le point-virgule sépare le plus souvent les membres d'une phrase
		// française : il n'est étranger que si les deux côtés le sont.
		if ( ';' === $signe && ! ( self::anglais_de( $avant ) && self::anglais_de( $apres ) ) ) {
			return false;
		}
		$anglais = self::anglais_de( $mots );
		// « Sapiens: a brief history » : l'article indéfini juste après le
		// signe ne peut pas être le verbe français.
		if ( count( $apres ) >= 2 && in_array( self::bas( $apres[0] ), array( 'a', 'an' ), true ) ) {
			$anglais++;
		}
		$capitales = 0;
		foreach ( $mots as $mot ) {
			if ( self::majuscule( $mot ) ) {
				$capitales++;
			}
		}
		return $anglais >= 2 || ( 1 === $anglais && $capitales * 2 > count( $mots ) );
	}

	/**
	 * Les espaces à ôter devant une ponctuation double d'un passage
	 * étranger, en intervalles d'octets.
	 */
	private static function espaces_anglaises( $texte, $contexte_avant = '', $contexte_apres = '', $tout = false ) {
		$zones  = array_merge( self::adresses( $texte ), self::protegees( $texte ) );
		$rendus = array();
		if ( ! preg_match_all( '/[ \t\x{A0}' . self::FINES . ']+([;:!?])/u', $texte, $trouves, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return $rendus;
		}
		foreach ( $trouves as $m ) {
			$debut = $m[0][1];
			if ( self::dans( $m[1][1], $zones ) || 0 === $debut ) {
				continue;
			}
			$gauche = $contexte_avant . substr( $texte, 0, $debut );
			// Dans un texte français, on n'ôte une espace existante que si ce
			// qui la précède est lui-même étranger.
			if ( ! $tout && ! self::gauche_etrangere( $gauche ) ) {
				continue;
			}
			$fin = $debut + strlen( $m[0][0] );
			if ( self::ponctuation_etrangere( $gauche, substr( $texte, $fin ) . $contexte_apres, $m[1][0], $tout ) ) {
				$rendus[] = array( $debut, $m[1][1] );
			}
		}
		return $rendus;
	}

	/**
	 * Ce qui précède le signe parle-t-il une autre langue ? Un mot-outil
	 * étranger parmi les derniers mots, ou un dernier mot à capitale qui
	 * n'est pas français.
	 */
	private static function gauche_etrangere( $gauche ) {
		$l        = self::listes();
		$morceaux = self::couper_aux_bornes( $gauche );
		$derniers = array_slice( self::mots( end( $morceaux ) ), -6 );
		if ( empty( $derniers ) ) {
			return false;
		}
		foreach ( $derniers as $mot ) {
			if ( isset( $l['etrangers'][ self::bas( $mot ) ] ) ) {
				return true;
			}
		}
		$dernier = end( $derniers );
		return self::majuscule( $dernier ) && ! self::francais( $dernier );
	}

	/**
	 * Les mots du segment, du plus proche du signe au plus lointain,
	 * jusqu'au premier mot français.
	 */
	private static function mots_du_segment( $texte, $vers_la_gauche ) {
		$morceaux = self::couper_aux_bornes( $texte );
		if ( $vers_la_gauche ) {
			$mots = array_reverse( self::mots( end( $morceaux ) ) );
		} else {
			$mots = self::mots( $morceaux[0] );
		}
		$rendus = array();
		foreach ( array_slice( $mots, 0, 8 ) as $mot ) {
			if ( self::francais( $mot ) ) {
				break;
			}
			$rendus[] = $mot;
		}
		return $vers_la_gauche ? array_reverse( $rendus ) : $rendus;
	}

	/** Un mot qui n'est que français. */
	private static function francais( $mot ) {
		$l    = self::listes();
		$plat = str_replace( '’', "'", self::bas( $mot ) );
		$tete = explode( "'", $plat );
		$tete = $tete[0];
		return ( isset( $l['francais'][ $plat ] ) && ! isset( $l['communs'][ $plat ] ) )
			|| ( $tete !== $plat && isset( $l['francais'][ $tete ] ) && ! in_array( $tete, array( 'd', 'l' ), true ) );
	}

	/**
	 * Les mots-outils étrangers d'une liste, plus les mots en lettres
	 * étrangères quand il y en a deux au moins, et en minuscules.
	 */
	private static function anglais_de( $liste ) {
		$l         = self::listes();
		$outils    = 0;
		$accentues = 0;
		foreach ( $liste as $mot ) {
			if ( isset( $l['etrangers'][ self::bas( $mot ) ] ) ) {
				$outils++;
			} elseif ( ! self::majuscule( $mot ) && preg_match( '/[áíóúñãõìòßäö¿¡ÁÍÓÚÑÃÕÌÒÄÖ]/u', $mot ) ) {
				$accentues++;
			}
		}
		return $outils + ( $accentues >= 2 ? $accentues : 0 );
	}

	// ── Outils ───────────────────────────────────────────────────────────

	/** Les listes de mots, en clefs. */
	private static function listes() {
		if ( null === self::$listes ) {
			$lire = function ( $texte ) {
				return array_fill_keys( preg_split( '/\s+/u', trim( $texte ) ), true );
			};
			$anglais = $lire( self::MOTS_ANGLAIS );
			self::$listes = array(
				'etrangers' => $anglais + $lire( self::MOTS_ETRANGERS ),
				'francais'  => $lire( self::MOTS_FRANCAIS ),
				'communs'   => $lire( self::MOTS_COMMUNS ),
				'pronoms'   => $lire( self::PRONOMS ),
			);
		}
		return self::$listes;
	}

	/** Les mots d'un texte : des lettres, avec au plus une apostrophe. */
	private static function mots( $texte ) {
		preg_match_all( '/(*UCP)[^\W\d_]+(?:[\'’][^\W\d_]+)?/u', (string) $texte, $m );
		return $m[0];
	}

	/**
	 * Le texte coupé à ce qui borne un segment : une fin de phrase, une
	 * virgule, une parenthèse, un guillemet.
	 */
	private static function couper_aux_bornes( $texte ) {
		return preg_split( '/[.,;!?()«»“”\[\]]/u', (string) $texte );
	}

	private static function bas( $mot ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $mot, 'UTF-8' ) : strtolower( (string) $mot );
	}

	private static function majuscule( $mot ) {
		return (bool) preg_match( '/^\p{Lu}/u', (string) $mot );
	}

	private static function dernier( $texte ) {
		return preg_match( '/.$/us', (string) $texte, $m ) ? $m[0] : '';
	}

	private static function finit_par( $texte, $fin ) {
		return '' !== $fin && strlen( $texte ) >= strlen( $fin ) && substr( $texte, -strlen( $fin ) ) === $fin;
	}

	/** Les adresses entières : rien de ce qu'elles portent ne se corrige. */
	private static function adresses( $texte ) {
		$zones = array();
		if ( preg_match_all( '~(*UCP)\b(?:https?|ftp|mailto|doi):\S+|\bwww\.\S+|\b10\.\d{4,9}/\S+|\bark:/\S+|[\w.+-]+@[\w-]+(?:\.[\w-]+)+~iu',
			(string) $texte, $m, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $m[0] as $t ) {
				$zones[] = array( $t[1], $t[1] + strlen( $t[0] ) );
			}
		}
		return $zones;
	}

	/** Là où la ponctuation ne se corrige pas : adresses, schémas, heures. */
	private static function protegees( $texte ) {
		$zones = array();
		foreach ( array(
			'~(*UCP)\b(?:https?|ftp):(?=//)|\bmailto:|\bdoi:(?=10\.)~iu',
			'/(?<=\d):(?=\d)/u',
		) as $motif ) {
			if ( preg_match_all( $motif, (string) $texte, $m, PREG_OFFSET_CAPTURE ) ) {
				foreach ( $m[0] as $t ) {
					$zones[] = array( $t[1], $t[1] + strlen( $t[0] ) );
				}
			}
		}
		return array_merge( $zones, self::adresses( $texte ) );
	}

	private static function dans( $position, $zones ) {
		foreach ( $zones as $zone ) {
			if ( $zone[0] <= $position && $position < $zone[1] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Un remplacement dont la fonction reçoit la trouvaille, avec ses
	 * positions, et le début et la fin de ce qu'elle remplace.
	 * « preg_replace_callback » ne donne les positions qu'à partir de PHP 7.4.
	 */
	private static function remplacer( $motif, $texte, $fonction ) {
		if ( ! preg_match_all( $motif, $texte, $trouves, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return $texte;
		}
		$rendu  = '';
		$depuis = 0;
		foreach ( $trouves as $m ) {
			$debut  = $m[0][1];
			$fin    = $debut + strlen( $m[0][0] );
			$rendu .= substr( $texte, $depuis, $debut - $depuis ) . call_user_func( $fonction, $m, $debut, $fin );
			$depuis = $fin;
		}
		return $rendu . substr( $texte, $depuis );
	}

	/** Un remplacement qui laisse les adresses tranquilles. */
	private static function hors_adresse( $motif, $texte, $remplacement ) {
		$adresses = self::adresses( $texte );
		return self::remplacer( $motif, $texte,
			function ( $m, $debut ) use ( $adresses, $remplacement ) {
				if ( self::dans( $debut, $adresses ) ) {
					return $m[0][0];
				}
				return is_callable( $remplacement ) && ! is_string( $remplacement )
					? call_user_func( $remplacement, $m ) : $remplacement;
			} );
	}
}
