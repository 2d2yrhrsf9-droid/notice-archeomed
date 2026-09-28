<?php
/**
 * Ce que Pactols sait d'un terme, et que l'autocomplétion ne dit pas.
 *
 * L'autocomplétion rend trois champs : une étiquette, un ARK, un identifiant.
 * Elle ne dit ni si l'étiquette est la forme préférée ou une variante, ni si
 * le concept est encore vivant, ni où il se range dans le thésaurus. Ces trois
 * silences coûtent chacun quelque chose :
 *
 * — une variante imprimée oblige la rédaction à la corriger notice par
 *   notice, puisque l'indexation en aval ne reconnaît que la forme préférée ;
 * — un concept déprécié s'indexe dans le vide : la chaîne le refuse, et le
 *   mot-clé reste nu sans que personne l'ait vu passer ;
 * — sans la chaîne ascendante, le bloc d'index se compose à la main, terme
 *   par terme, dans le moteur d'indexation.
 *
 * L'API d'Opentheso répond aux trois, depuis le seul identifiant du concept.
 * Une réponse se garde un mois : un thésaurus ne bouge pas d'un jour à
 * l'autre, et la campagne d'une revue tient dans cette réserve.
 *
 * Aucun appel ne se fait dans la requête d'un auteur qui dépose. Le dépôt
 * s'accepte et s'expédie ensuite ; c'est à l'expédition, hors de sa vue, que
 * les termes se résolvent.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Pactols {

	/**
	 * L'API qui rend un concept entier. Elle n'est pas celle de
	 * l'autocomplétion, et elle exige l'en-tête « Accept » : sans lui, elle
	 * répond 500 — ce qui se lit comme une panne et n'en est pas une.
	 */
	const BASE = 'https://pactols.frantiq.fr/openapi/v1/';

	const SKOS = 'http://www.w3.org/2004/02/skos/core#';
	const OWL  = 'http://www.w3.org/2002/07/owl#';
	const DCT  = 'http://purl.org/dc/terms/';

	/** Le préfixe des ARK de Frantiq, ôté pour l'attribut « source ». */
	const NAAN = '26678/';
	const BASE_ARK = 'https://ark.frantiq.fr/ark:/';

	/** Un mois : le thésaurus bouge moins vite qu'une campagne. */
	const DUREE = MONTH_IN_SECONDS;

	/**
	 * Ce que vaut un terme, ou null si Pactols n'a pas répondu.
	 *
	 * Rend un tableau : l'ARK, la forme préférée française, les étiquettes de
	 * toutes les langues, l'état de dépréciation avec son remplaçant s'il y en
	 * a un, la chaîne ascendante du plus général au terme lui-même, et la date
	 * à laquelle tout cela a été lu — sans quoi on ne saurait pas de quel
	 * millésime du thésaurus vient un identifiant.
	 */
	public static function resoudre( $ark, $id_concept = '', $theso = 'TH_1' ) {
		$ark = trim( (string) $ark );
		if ( '' === $ark && '' === $id_concept ) {
			return null;
		}
		$clef = 'na_pactols_c_' . md5( $ark . '|' . $id_concept . '|' . $theso );
		$connu = get_transient( $clef );
		if ( is_array( $connu ) ) {
			return $connu;
		}
		if ( 'vide' === $connu ) {
			return null;   // échec récent : on n'insiste pas à chaque page
		}

		$id = trim( (string) $id_concept );
		if ( '' === $id ) {
			// Sans identifiant numérique — c'est le cas des natures
			// d'opération, dont la liste est tenue à la main avec leurs seuls
			// ARK — un premier appel va le chercher.
			// Par l'ARK, et non par le couple thésaurus/identifiant : cette
			// seconde route rend parfois 200 avec un corps vide, ce qui se
			// lit comme un concept sans propriétés et n'en est pas un.
			$seul = self::appeler( 'concept/ark:/' . self::partie_ark( $ark ) );
			$id   = self::valeur( $seul, $ark, self::DCT . 'identifier' );
		}

		$chemin = array();
		$noeud  = null;
		if ( '' !== $id ) {
			// L'expansion rend d'un coup le concept et tous ses ancêtres, avec
			// leurs étiquettes dans toutes les langues. C'est de quoi composer
			// le bloc d'index entier sans autre appel.
			$graphe = self::appeler( 'concept/' . $theso . '/' . rawurlencode( $id ) . '/expansion?way=top' );
			if ( is_array( $graphe ) && ! empty( $graphe ) ) {
				$chemin = self::remonter( $graphe, $ark );
				$noeud  = isset( $graphe[ $ark ] ) ? $graphe[ $ark ] : null;
			}
		}
		if ( null === $noeud ) {
			$seul  = self::appeler( 'concept/ark:/' . self::partie_ark( $ark ) );
			$noeud = ( is_array( $seul ) && isset( $seul[ $ark ] ) ) ? $seul[ $ark ] : null;
		}
		if ( null === $noeud ) {
			// Une panne de réseau ne se met pas en réserve pour un mois : une
			// heure suffit à ne pas marteler l'API, et la notice suivante
			// réessaiera.
			set_transient( $clef, 'vide', HOUR_IN_SECONDS );
			return null;
		}

		$etiquettes = self::etiquettes( $noeud );
		$remplacant = self::premiere( $noeud, self::DCT . 'isReplacedBy' );
		$concept = array(
			'ark'        => $ark,
			'id'         => $id,
			'prefLabel'  => isset( $etiquettes['fr'] ) ? $etiquettes['fr'] : '',
			'labels'     => $etiquettes,
			'deprecie'   => '' !== self::premiere( $noeud, self::OWL . 'deprecated' ),
			'remplacant' => $remplacant,
			'chemin'     => $chemin,
			'lu_le'      => gmdate( 'Y-m-d' ),
		);
		set_transient( $clef, $concept, self::DUREE );
		return $concept;
	}

	/**
	 * Le bloc d'index TEI d'un terme, prêt à coller dans le XML.
	 *
	 * C'est la forme que la chaîne attend : la graphie du texte en « orig »,
	 * puis les concepts emboîtés du plus général au plus précis, chacun avec
	 * son rang, son ARK relatif et ses étiquettes. Seul le premier niveau
	 * porte « indexName » et « xml:base ».
	 *
	 * Rend une chaîne vide si la chaîne ascendante manque : un index tronqué
	 * serait plus coûteux à reprendre qu'un index absent.
	 */
	public static function index_tei( $graphie, $concept, $index_name ) {
		if ( ! is_array( $concept ) || empty( $concept['chemin'] ) ) {
			return '';
		}
		$dedans = '';
		$rang   = count( $concept['chemin'] );
		// On compose de l'intérieur vers l'extérieur : chaque niveau enveloppe
		// le précédent, et le plus profond est le terme choisi.
		foreach ( array_reverse( $concept['chemin'] ) as $etage ) {
			$attributs = ' n="' . $rang . '" rendition="oe"'
				. ' source="' . esc_attr( self::partie_ark( $etage['ark'] ) ) . '"';
			if ( 1 === $rang ) {
				$attributs = ' indexName="' . esc_attr( $index_name ) . '"' . $attributs
					. ' xml:base="' . self::BASE_ARK . '"';
			}
			$termes = '';
			foreach ( $etage['labels'] as $langue => $mot ) {
				$termes .= '<term xml:lang="' . esc_attr( $langue ) . '">'
					. esc_html( $mot ) . '</term>';
			}
			$dedans = '<index' . $attributs . '>' . $termes . $dedans . '</index>';
			--$rang;
		}
		return '<index indexName="Index"><term type="orig">'
			. esc_html( $graphie ) . '</term>' . $dedans . '</index>';
	}

	/**
	 * Ordonne le graphe rendu par l'expansion, du plus général au terme.
	 *
	 * L'API rend les concepts en vrac ; c'est « broader » qui les enchaîne. On
	 * part du terme et l'on remonte, avec un garde-fou : un thésaurus mal formé
	 * pourrait boucler, et une boucle ici arrêterait la fabrication d'un
	 * fascicule entier.
	 */
	private static function remonter( $graphe, $ark ) {
		$chemin = array();
		$vus    = array();
		$actuel = $ark;
		for ( $i = 0; $i < 40; $i++ ) {
			if ( '' === $actuel || isset( $vus[ $actuel ] ) || ! isset( $graphe[ $actuel ] ) ) {
				break;
			}
			$vus[ $actuel ] = true;
			$chemin[] = array(
				'ark'    => $actuel,
				'labels' => self::etiquettes( $graphe[ $actuel ] ),
			);
			$actuel = self::premiere( $graphe[ $actuel ], self::SKOS . 'broader' );
		}
		return array_reverse( $chemin );
	}

	/** Les formes préférées d'un concept, par langue. */
	private static function etiquettes( $noeud ) {
		$out = array();
		if ( ! isset( $noeud[ self::SKOS . 'prefLabel' ] ) ) {
			return $out;
		}
		foreach ( (array) $noeud[ self::SKOS . 'prefLabel' ] as $item ) {
			if ( ! is_array( $item ) || empty( $item['value'] ) ) {
				continue;
			}
			$langue = isset( $item['lang'] ) ? (string) $item['lang'] : '';
			if ( '' === $langue ) {
				continue;
			}
			$out[ $langue ] = (string) $item['value'];
		}
		return $out;
	}

	/** La première valeur d'une propriété, ou une chaîne vide. */
	private static function premiere( $noeud, $propriete ) {
		if ( empty( $noeud[ $propriete ] ) ) {
			return '';
		}
		$item = reset( $noeud[ $propriete ] );
		return ( is_array( $item ) && isset( $item['value'] ) ) ? (string) $item['value'] : '';
	}

	/** Une propriété d'un concept désigné par son ARK dans une réponse. */
	private static function valeur( $reponse, $ark, $propriete ) {
		if ( ! is_array( $reponse ) || ! isset( $reponse[ $ark ] ) ) {
			return '';
		}
		return self::premiere( $reponse[ $ark ], $propriete );
	}

	/** « https://ark.frantiq.fr/ark:/26678/pcrt… » devient « 26678/pcrt… ». */
	public static function partie_ark( $ark ) {
		$pos = strpos( (string) $ark, 'ark:/' );
		return ( false !== $pos ) ? substr( $ark, $pos + 5 ) : (string) $ark;
	}

	/**
	 * Un appel à l'API, rendu en tableau.
	 *
	 * L'en-tête « Accept » n'est pas une politesse : sans lui, l'API répond
	 * 500. Le délai est court — la rédaction attend devant sa liste, et mieux
	 * vaut un index manquant qu'une page qui ne rend jamais la main.
	 */
	private static function appeler( $chemin ) {
		$reponse = wp_remote_get(
			self::BASE . $chemin,
			array(
				'timeout' => 8,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $reponse ) ) {
			error_log( 'Notice Archeomed: Pactols ' . $chemin . ' : ' . $reponse->get_error_message() );
			return null;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $reponse ) ) {
			return null;
		}
		$data = json_decode( wp_remote_retrieve_body( $reponse ), true );
		if ( ! is_array( $data ) || isset( $data['status'] ) ) {
			return null;
		}
		return $data;
	}
}
