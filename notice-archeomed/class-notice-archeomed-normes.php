<?php
/**
 * Les normes éditoriales de la revue, en réglages.
 *
 * Tout ce que la rédaction a décidé pour ses notices — « s. » ou « siècle »,
 * petites capitales ou capitales, la forme d'un titre, la norme des images —
 * était écrit dans le code : juste pour la Chronique, faux pour toute autre
 * revue qui voudrait du même formulaire. Le voici en un catalogue : chaque
 * norme, ses choix, un exemple pour chacun, et le choix de la revue par
 * défaut. La page de réglages se construit à partir de lui, et le code ne
 * lit plus que lui.
 *
 * La correspondance des champs avec les styles Métopes n'y est pas : elle
 * n'est pas une norme de revue, mais le contrat avec la chaîne XML.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Normes {

	/** La clé, dans l'option du plugin, qui porte les choix enregistrés. */
	const CLE = 'normes';

	/** Les choix lus une fois par requête. */
	private static $enregistrees = null;

	/**
	 * Le catalogue : groupes, puis normes. Une norme à choix porte « choix »
	 * (valeur => array( libellé, exemple )) ; une norme chiffrée porte « min »
	 * et « max » ; une liste de cases porte « cases ».
	 */
	public static function catalogue() {
		return array(
			'siecles' => array(
				'titre'  => 'Les siècles',
				'normes' => array(
					'siecle_mot' => array(
						'libelle' => 'Le mot « siècle » après un siècle en chiffres',
						'defaut'  => 'abrege',
						'choix'   => array(
							'abrege' => array( 'Abrégé', 'au XII<sup>e</sup> s.' ),
							'entier' => array( 'En toutes lettres', 'au XII<sup>e</sup> siècle' ),
							'tel'    => array( 'Tel que l’auteur l’a écrit', '' ),
						),
					),
					'siecle_chiffres' => array(
						'libelle' => 'Les chiffres romains du siècle',
						'defaut'  => 'petites_capitales',
						'choix'   => array(
							'petites_capitales' => array( 'En petites capitales', '<span style="font-variant:small-caps">xii</span><sup>e</sup> s.' ),
							'capitales'         => array( 'En capitales', 'XII<sup>e</sup> s.' ),
							'tel'               => array( 'Tels que l’auteur les a écrits', '' ),
						),
					),
					'siecle_ordinal' => array(
						'libelle' => 'L’ordinal du siècle (e, er, re)',
						'defaut'  => 'exposant',
						'choix'   => array(
							'exposant' => array( 'En exposant', 'XII<sup>e</sup>' ),
							'ligne'    => array( 'Sur la ligne', 'XIIe' ),
						),
					),
				),
			),
			'typographie' => array(
				'titre'  => 'La typographie',
				'normes' => array(
					'typographie' => array(
						'libelle' => 'Corriger la typographie du texte imprimé',
						'aide'    => 'Espaces devant la ponctuation et dans les guillemets, apostrophes courbes, points de suspension, espaces dans les nombres et après « p. », « fig. », « n° ».',
						'defaut'  => 'oui',
						'choix'   => array(
							'oui' => array( 'Oui', 'l’état du site : voir « fossé »…' ),
							'non' => array( 'Non, laisser le texte tel que l’auteur l’a écrit', '' ),
						),
					),
					'espace_ponctuation' => array(
						'libelle' => 'L’espace devant ; ! ? et dans les guillemets',
						'aide'    => 'Devant le deux-points, l’espace reste une insécable ordinaire dans les deux cas.',
						'defaut'  => 'insecable',
						'choix'   => array(
							'insecable' => array( 'Espace insécable', 'mot&nbsp;; «&nbsp;mot&nbsp;»' ),
							'fine'      => array( 'Espace fine insécable', 'mot&#8239;; «&#8239;mot&#8239;»' ),
						),
					),
					'annees' => array(
						'libelle' => 'Une opération sur plusieurs années',
						'defaut'  => 'trait_union',
						'choix'   => array(
							'trait_union'   => array( 'Trait d’union', '2004-2005' ),
							'demi_cadratin' => array( 'Tiret demi-cadratin', '2004–2005' ),
							'barre'         => array( 'Barre oblique', '2004/2005' ),
						),
					),
				),
			),
			'composition' => array(
				'titre'  => 'La composition de la notice',
				'normes' => array(
					'titre_notice' => array(
						'libelle' => 'Entre la parenthèse et le lieu-dit, dans le titre',
						'defaut'  => 'point',
						'choix'   => array(
							'point'   => array( 'Un point', 'Caen (Calvados). <em>Château</em>' ),
							'virgule' => array( 'Une virgule', 'Caen (Calvados), <em>Château</em>' ),
						),
					),
					'lieu_dit_italique' => array(
						'libelle' => 'Le lieu-dit dans le titre',
						'defaut'  => 'oui',
						'choix'   => array(
							'oui' => array( 'En italique', '<em>Château</em>' ),
							'non' => array( 'En romain', 'Château' ),
						),
					),
					'figure_abreviation' => array(
						'libelle' => 'L’appel d’une figure',
						'defaut'  => 'fig',
						'choix'   => array(
							'fig'    => array( 'Fig.', 'Fig. 1' ),
							'figure' => array( 'Figure', 'Figure 1' ),
							'ill'    => array( 'Ill.', 'Ill. 1' ),
						),
					),
					'figure_numero' => array(
						'libelle' => 'Entre le numéro et le titre d’une figure',
						'defaut'  => 'espace',
						'choix'   => array(
							'espace'      => array( 'Une espace', 'Fig. 1 Vue générale' ),
							'deux_points' => array( 'Un deux-points', 'Fig. 1&nbsp;: Vue générale' ),
							'point'       => array( 'Un point', 'Fig. 1. Vue générale' ),
						),
					),
				),
			),
			'depot' => array(
				'titre'  => 'Ce qu’on demande aux auteurs',
				'normes' => array(
					'photo_largeur_cm' => array( 'libelle' => 'Photographies : largeur minimale', 'unite' => 'cm', 'defaut' => 10, 'min' => 1, 'max' => 100 ),
					'photo_hauteur_cm' => array( 'libelle' => 'Photographies : hauteur minimale', 'unite' => 'cm', 'defaut' => 15, 'min' => 1, 'max' => 100 ),
					'photo_ppp'        => array( 'libelle' => 'Photographies : résolution', 'unite' => 'ppp', 'defaut' => 300, 'min' => 72, 'max' => 2400 ),
					'trait_ppp'        => array( 'libelle' => 'Dessins au trait : résolution', 'unite' => 'ppp', 'defaut' => 1200, 'min' => 72, 'max' => 4800 ),
					'mots_min'         => array( 'libelle' => 'Texte : longueur recommandée, au moins', 'unite' => 'mots', 'defaut' => 300, 'min' => 0, 'max' => 10000 ),
					'mots_max'         => array( 'libelle' => 'Texte : longueur recommandée, au plus', 'unite' => 'mots', 'defaut' => 700, 'min' => 1, 'max' => 20000 ),
				),
			),
			'controles' => array(
				'titre'  => 'Les avis donnés au dépôt',
				'aide'   => 'Ce sont des avis, jamais des refus : l’auteur les lit en remplissant le formulaire, la rédaction les retrouve sous « À vérifier ».',
				'normes' => array(
					'avis' => array(
						'libelle' => 'Signaler',
						'defaut'  => array( 'ponctuation', 'figures_appelees', 'definition', 'credits', 'ordinaux', 'annees_espacees', 'personnes' ),
						'cases'   => array(
							'ponctuation'      => 'un texte qui s’arrête sans ponctuation, ou un paragraphe coupé en deux',
							'figures_appelees' => 'une figure appelée dans le texte et non jointe, ou jointe et jamais appelée',
							'definition'       => 'une photographie sous la norme demandée',
							'credits'          => 'une figure sans crédits',
							'ordinaux'         => '« XIIème » pour « XIIe », « 1ère » pour « 1re »',
							'annees_espacees'  => 'une année tapée avec une espace, « 1 250 »',
							'personnes'        => 'un nom en capitales, deux personnes dans un champ, une adresse dans l’institution',
						),
					),
				),
			),
		);
	}

	/** Toutes les normes, à plat, par leur clé. */
	public static function definitions() {
		$toutes = array();
		foreach ( self::catalogue() as $groupe ) {
			foreach ( $groupe['normes'] as $cle => $norme ) {
				$toutes[ $cle ] = $norme;
			}
		}
		return $toutes;
	}

	/**
	 * La valeur d'une norme : celle que la rédaction a choisie, ou celle de
	 * la revue. Une valeur enregistrée qui n'est plus au catalogue retombe
	 * sur le choix par défaut.
	 */
	public static function valeur( $cle ) {
		$definitions = self::definitions();
		if ( ! isset( $definitions[ $cle ] ) ) {
			return null;
		}
		$norme = $definitions[ $cle ];
		if ( null === self::$enregistrees ) {
			$options = get_option( Notice_Archeomed_Settings::OPTION_NAME, array() );
			self::$enregistrees = ( is_array( $options ) && isset( $options[ self::CLE ] ) && is_array( $options[ self::CLE ] ) )
				? $options[ self::CLE ] : array();
		}
		if ( ! array_key_exists( $cle, self::$enregistrees ) ) {
			return $norme['defaut'];
		}
		return self::valider( $norme, self::$enregistrees[ $cle ], $norme['defaut'] );
	}

	/** Un avis est-il à donner ? */
	public static function avis_actif( $avis ) {
		return in_array( $avis, (array) self::valeur( 'avis' ), true );
	}

	/**
	 * Une valeur soumise, ramenée à ce que la norme admet.
	 */
	public static function valider( $norme, $valeur, $repli ) {
		if ( isset( $norme['choix'] ) ) {
			return ( is_string( $valeur ) && isset( $norme['choix'][ $valeur ] ) ) ? $valeur : $repli;
		}
		if ( isset( $norme['cases'] ) ) {
			return array_values( array_intersect( array_keys( $norme['cases'] ), (array) $valeur ) );
		}
		if ( ! is_numeric( $valeur ) ) {
			return $repli;
		}
		return max( $norme['min'], min( $norme['max'], (int) $valeur ) );
	}

	/** Les choix soumis par la page de réglages, nettoyés. */
	public static function nettoyer( $soumis ) {
		$propres = array();
		foreach ( self::definitions() as $cle => $norme ) {
			if ( isset( $norme['cases'] ) ) {
				// Une case décochée n'arrive pas : l'absence vaut « aucune ».
				$propres[ $cle ] = self::valider( $norme, isset( $soumis[ $cle ] ) ? $soumis[ $cle ] : array(), array() );
				continue;
			}
			if ( isset( $soumis[ $cle ] ) ) {
				$propres[ $cle ] = self::valider( $norme, $soumis[ $cle ], $norme['defaut'] );
			}
		}
		return $propres;
	}

	/** Oublie les choix lus : après un enregistrement, ou dans les essais. */
	public static function oublier() {
		self::$enregistrees = null;
	}

	// ── Ce que les normes composent ─────────────────────────────────────

	/** « Fig. », « Figure » ou « Ill. », suivi du numéro. */
	public static function numero_de_figure( $rang ) {
		$abreviations = array( 'fig' => 'Fig.', 'figure' => 'Figure', 'ill' => 'Ill.' );
		$abreviation  = self::valeur( 'figure_abreviation' );
		return ( isset( $abreviations[ $abreviation ] ) ? $abreviations[ $abreviation ] : 'Fig.' ) . ' ' . (int) $rang;
	}

	/** Ce qui sépare le numéro d'une figure de son titre. */
	public static function apres_le_numero() {
		$separateurs = array( 'espace' => ' ', 'deux_points' => ' : ', 'point' => '. ' );
		$choix       = self::valeur( 'figure_numero' );
		return isset( $separateurs[ $choix ] ) ? $separateurs[ $choix ] : ' ';
	}

	/** Ce qui sépare la parenthèse du lieu-dit dans le titre d'une notice. */
	public static function avant_le_lieu_dit() {
		return 'virgule' === self::valeur( 'titre_notice' ) ? ', ' : '. ';
	}

	/** Le lieu-dit du titre est-il en italique ? */
	public static function lieu_dit_en_italique() {
		return 'non' !== self::valeur( 'lieu_dit_italique' );
	}

	/** Le trait entre deux années. */
	public static function trait_des_annees() {
		$traits = array( 'trait_union' => '-', 'demi_cadratin' => '–', 'barre' => '/' );
		$choix  = self::valeur( 'annees' );
		return isset( $traits[ $choix ] ) ? $traits[ $choix ] : '-';
	}

	/** Les pixels qu'il faut pour la norme des photographies, petit côté puis grand. */
	public static function pixels_des_photographies() {
		$pouce = 2.54;
		$ppp   = (int) self::valeur( 'photo_ppp' );
		$cotes = array( (int) self::valeur( 'photo_largeur_cm' ), (int) self::valeur( 'photo_hauteur_cm' ) );
		sort( $cotes );
		return array(
			(int) ceil( $cotes[0] / $pouce * $ppp ),
			(int) ceil( $cotes[1] / $pouce * $ppp ),
		);
	}

	/**
	 * La typographie d'un texte, selon les normes : corrigée ou non, et
	 * l'espace des signes doubles fine ou insécable.
	 */
	public static function typographie( $texte, $avant = '', $contexte_avant = '', $contexte_apres = '' ) {
		if ( 'non' === self::valeur( 'typographie' ) ) {
			return (string) $texte;
		}
		$rendu = Notice_Archeomed_Typographie::corriger( $texte, $avant, 'fr', $contexte_avant, $contexte_apres );
		return self::espaces_de_la_revue( $rendu );
	}

	/**
	 * La fine insécable, pour une revue qui la veut : devant ; ! ? et dans
	 * les guillemets. La correction pose l'insécable partout ; on ne fait
	 * que la changer là où la revue en veut une autre.
	 */
	public static function espaces_de_la_revue( $texte ) {
		if ( 'fine' !== self::valeur( 'espace_ponctuation' ) ) {
			return $texte;
		}
		$fine  = "\u{202F}";
		$texte = preg_replace( '/\x{A0}(?=[;!?])/u', $fine, (string) $texte );
		$texte = str_replace( array( "«\u{00A0}", "\u{00A0}»" ), array( '«' . $fine, $fine . '»' ), $texte );
		return $texte;
	}
}
