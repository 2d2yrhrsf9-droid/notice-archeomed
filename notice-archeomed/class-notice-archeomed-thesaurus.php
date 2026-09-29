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
 * Elle ne s'appelle pas « Pactols » : ce nom est celui de la classe
 * principale du plugin, qu'il tient du shortcode « notice_archeomed_pactols ».
 * Deux classes de même nom dans un même chargement, et PHP s'arrête net — le
 * formulaire avec lui, pour tout le monde.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Thesaurus {

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

	/** Ce qu'on accorde à un appel, en secondes, quand rien ne presse. */
	const DELAI = 8;

	/** Un mois : le thésaurus bouge moins vite qu'une campagne. */
	const DUREE = MONTH_IN_SECONDS;

	/**
	 * Une heure : ce qu'on retient d'un échec, pour ne pas marteler l'API.
	 * Nommée parce que la reprise des notices en dépend — elle doit venir
	 * après, faute de quoi elle relit l'échec au lieu d'interroger.
	 */
	const DUREE_ECHEC = HOUR_IN_SECONDS;

	/**
	 * Ce que vaut un terme, ou null si Pactols n'a pas répondu.
	 *
	 * Rend un tableau : l'ARK, la forme préférée française, les étiquettes de
	 * toutes les langues, l'état de dépréciation avec son remplaçant s'il y en
	 * a un, la chaîne ascendante du plus général au terme lui-même, et la date
	 * à laquelle tout cela a été lu — sans quoi on ne saurait pas de quel
	 * millésime du thésaurus vient un identifiant.
	 */
	public static function resoudre( $ark, $id_concept = '', $theso = 'TH_1', $echeance = 0 ) {
		$ark = trim( (string) $ark );
		if ( '' === $ark && '' === $id_concept ) {
			return null;
		}
		$clef    = self::clef( $ark, $id_concept, $theso );
		$connu   = get_transient( $clef );
		$ecourte = false;
		$coupe   = false;
		if ( self::est_un_echec( $connu ) ) {
			return null;   // échec récent : on n'insiste pas à chaque page
		}
		if ( is_array( $connu ) ) {
			return $connu;
		}

		// L'identifiant numérique trouvé lors d'un passage précédent. Sans lui,
		// un passage interrompu après ce premier appel le perdait, et le
		// suivant le redemandait : un terme qui demande trois appels lents ne
		// progressait jamais d'un passage à l'autre.
		$id = trim( (string) $id_concept );
		if ( '' === $id ) {
			$id = (string) get_transient( self::clef_id( $ark ) );
		}
		if ( '' === $id ) {
			// Sans identifiant numérique — c'est le cas des natures
			// d'opération, dont la liste est tenue à la main avec leurs seuls
			// ARK — un premier appel va le chercher.
			// Par l'ARK, et non par le couple thésaurus/identifiant : cette
			// seconde route rend parfois 200 avec un corps vide, ce qui se
			// lit comme un concept sans propriétés et n'en est pas un.
			if ( self::reste( $echeance ) < 1 ) {
				return null;
			}
			$seul = self::appeler( 'concept/ark:/' . self::partie_ark( $ark ), $echeance, $coupe );
			$ecourte = $ecourte || $coupe;
			$id   = self::valeur( $seul, $ark, self::DCT . 'identifier' );
			if ( '' !== $id ) {
				set_transient( self::clef_id( $ark ), $id, self::DUREE );
			}
		}

		$chemin = array();
		$noeud  = null;
		if ( '' !== $id ) {
			// L'expansion rend d'un coup le concept et tous ses ancêtres, avec
			// leurs étiquettes dans toutes les langues. C'est de quoi composer
			// le bloc d'index entier sans autre appel.
			if ( self::reste( $echeance ) < 1 ) {
				return null;
			}
			$graphe = self::appeler( 'concept/' . $theso . '/' . rawurlencode( $id ) . '/expansion?way=top', $echeance, $coupe );
			$ecourte = $ecourte || $coupe;
			if ( is_array( $graphe ) && ! empty( $graphe ) ) {
				$chemin = self::remonter( $graphe, $ark );
				$noeud  = isset( $graphe[ $ark ] ) ? $graphe[ $ark ] : null;
			}
		}
		if ( null === $noeud ) {
			if ( self::reste( $echeance ) < 1 ) {
				return null;
			}
			$seul  = self::appeler( 'concept/ark:/' . self::partie_ark( $ark ), $echeance, $coupe );
			$ecourte = $ecourte || $coupe;
			$noeud = ( is_array( $seul ) && isset( $seul[ $ark ] ) ) ? $seul[ $ark ] : null;
		}
		if ( null === $noeud && $ecourte ) {
			// Un appel dont l'échéance a raccourci le délai n'a pas échoué : on
			// l'a interrompu. Le retenir comme une panne d'une heure bloquait
			// ce terme pour toutes les notices, à cause d'une seconde qu'on ne
			// lui avait pas laissée — et la reprise promise à la notice venue
			// en fin de passage relisait cette fausse panne au lieu d'appeler.
			return null;
		}
		if ( null === $noeud ) {
			// Une panne de réseau ne se met pas en réserve pour un mois : une
			// heure suffit à ne pas marteler l'API, et la notice suivante
			// réessaiera.
			// L'échec garde l'heure où il sera oublié : c'est elle qui permet de
			// dire à la rédaction quand Pactols sera vraiment réinterrogé, au
			// lieu de promettre « dans quelques minutes » une relecture de
			// l'échec. La réserve est commune à toutes les notices : un terme qui
			// vient d'échouer pour l'une échoue aussi, pour l'heure, pour les
			// autres.
			set_transient( $clef, array( 'echec_jusqua' => time() + self::DUREE_ECHEC ),
				self::DUREE_ECHEC );
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

	/**
	 * L'heure à laquelle l'échec retenu pour ce terme sera oublié, ou zéro
	 * s'il n'y a pas d'échec en réserve.
	 *
	 * Un échec posé avant la 3.38 n'a pas d'heure ; on le suppose tout frais,
	 * ce qui ne peut que retarder d'au plus une heure, et seulement la
	 * première heure qui suit la mise à jour.
	 */
	public static function echec_jusqua( $ark, $id_concept = '', $theso = 'TH_1' ) {
		$connu = get_transient( self::clef( trim( (string) $ark ), $id_concept, $theso ) );
		if ( is_array( $connu ) && isset( $connu['echec_jusqua'] ) ) {
			return (int) $connu['echec_jusqua'];
		}
		return ( 'vide' === $connu ) ? time() + self::DUREE_ECHEC : 0;
	}

	/** Un échec en réserve, sous sa forme d'avant la 3.38 ou d'après. */
	private static function est_un_echec( $connu ) {
		return 'vide' === $connu || ( is_array( $connu ) && isset( $connu['echec_jusqua'] ) );
	}

	/** La clef de réserve d'un terme. */
	private static function clef( $ark, $id_concept, $theso ) {
		return 'na_pactols_c_' . md5( $ark . '|' . $id_concept . '|' . $theso );
	}

	/** La clef de réserve de l'identifiant numérique d'un ARK. */
	private static function clef_id( $ark ) {
		return 'na_pactols_id_' . md5( (string) $ark );
	}

	/**
	 * Les secondes qui restent avant l'échéance, ou huit si aucune n'est fixée.
	 *
	 * Une résolution ne doit jamais emporter la requête qui la porte : le
	 * planificateur, comme la page qui fabrique un dossier, a un temps
	 * d'exécution borné — trente secondes sur l'hébergement de la revue — et
	 * un concept se paie jusqu'à trois appels. L'échéance se consulte avant
	 * chacun, et raccourcit l'attente du dernier. Un terme laissé en route
	 * n'est pas mis en réserve comme un échec : il n'a pas échoué, on l'a
	 * interrompu, et le passage suivant le reprendra.
	 */
	private static function reste( $echeance ) {
		if ( $echeance <= 0 ) {
			return self::DELAI;
		}
		return (int) floor( $echeance - microtime( true ) );
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
	 * 500. Le délai est court — la tâche qui appelle a un budget, et mieux
	 * vaut un index manquant qu'une requête coupée par l'hébergement.
	 *
	 * « $coupe » dit si l'appel a épuisé un délai que l'échéance avait
	 * raccourci : il n'a pas échoué, on ne lui a pas laissé le temps. Une
	 * erreur rendue aussitôt par Pactols, elle, reste une panne, même en fin
	 * de budget.
	 */
	private static function appeler( $chemin, $echeance = 0, &$coupe = false ) {
		$delai   = max( 1, min( self::DELAI, self::reste( $echeance ) ) );
		$debut   = microtime( true );
		$reponse = wp_remote_get(
			self::BASE . $chemin,
			array(
				'timeout' => $delai,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		$coupe = $delai < self::DELAI && microtime( true ) - $debut >= $delai - 0.5;
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
