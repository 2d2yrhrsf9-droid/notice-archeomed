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
	 *
	 * « $interrompu » dit que le terme n'a pas échoué mais n'a pas eu le
	 * temps : l'échéance est venue, ou un appel a été coupé. L'appelant le
	 * reprend au passage suivant, sans le déduire de l'horloge.
	 */
	public static function resoudre( $ark, $id_concept = '', $theso = 'TH_1', $echeance = 0, &$interrompu = false ) {
		$interrompu = false;
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
		// Un concept gardé sans sa chaîne, avant que cela ne compte pour un
		// échec, se relit au lieu de servir encore un mois.
		if ( is_array( $connu ) && ! empty( $connu['chemin'] ) ) {
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
				$interrompu = true;
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
				$interrompu = true;
				return null;
			}
			$graphe = self::appeler( 'concept/' . $theso . '/' . rawurlencode( $id ) . '/expansion?way=top', $echeance, $coupe );
			$ecourte = $ecourte || $coupe;
			if ( is_array( $graphe ) && ! empty( $graphe ) ) {
				$chemin = self::remonter( $graphe, $ark );
				$noeud  = isset( $graphe[ $ark ] ) ? $graphe[ $ark ] : null;
			}
		}
		// L'expansion coupée en route : le repli par le concept seul ne
		// donnerait pas de chaîne, et coûterait un appel pour rien. Le terme
		// se reprend au passage suivant.
		if ( null === $noeud && $ecourte ) {
			$interrompu = true;
			return null;
		}
		if ( null === $noeud ) {
			if ( self::reste( $echeance ) < 1 ) {
				$interrompu = true;
				return null;
			}
			$seul  = self::appeler( 'concept/ark:/' . self::partie_ark( $ark ), $echeance, $coupe );
			$ecourte = $ecourte || $coupe;
			$noeud = ( is_array( $seul ) && isset( $seul[ $ark ] ) ) ? $seul[ $ark ] : null;
			// L'expansion ne contenait pas le concept : un identifiant faux,
			// un lieu de th17 lu comme TH_1. La route par ARK donne le vrai
			// identifiant et le vrai thésaurus ; on refait l'expansion une fois
			// avec eux.
			$vrai   = self::valeur( $seul, $ark, self::DCT . 'identifier' );
			$schema = self::valeur( $seul, $ark, self::SKOS . 'inScheme' );
			$schema = ( '' !== $schema ) ? basename( $schema ) : $theso;
			if ( null !== $noeud && '' !== $vrai && ( $vrai !== $id || $schema !== $theso ) && self::reste( $echeance ) >= 1 ) {
				$graphe  = self::appeler( 'concept/' . $schema . '/' . rawurlencode( $vrai ) . '/expansion?way=top', $echeance, $coupe );
				$ecourte = $ecourte || $coupe;
				if ( is_array( $graphe ) && isset( $graphe[ $ark ] ) ) {
					$chemin = self::remonter( $graphe, $ark );
					$id     = $vrai;
				}
			}
		}
		// Un concept sans chaîne ascendante n'est pas résolu. Il se gardait un
		// mois, et à demeure dans la notice : le bloc d'index manquait pour
		// toujours, et rien ne le comptait parmi les termes non lus. Interrompu,
		// il se reprend ; tombé, il compte comme un échec d'une heure.
		if ( null !== $noeud && empty( $chemin ) ) {
			$noeud = null;
		}
		if ( null === $noeud && $ecourte ) {
			// Un appel dont l'échéance a raccourci le délai n'a pas échoué : on
			// l'a interrompu. Le retenir comme une panne d'une heure bloquait
			// ce terme pour toutes les notices, à cause d'une seconde qu'on ne
			// lui avait pas laissée — et la reprise promise à la notice venue
			// en fin de passage relisait cette fausse panne au lieu d'appeler.
			$interrompu = true;
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
			// « false » se lisait comme retiré : c'est la valeur, non la
			// présence de la propriété, qui compte.
			'deprecie'   => 'true' === strtolower( self::premiere( $noeud, self::OWL . 'deprecated' ) ),
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
		// Un concept retiré du thésaurus n'est jamais indexé : sa chaîne
		// remonte à « ~[termes dépréciés] », et le bloc partirait tel quel
		// dans le TEI, puisqu'il se colle sans se relire. Le document le
		// signale déjà, à reprendre avant indexation.
		if ( ! empty( $concept['deprecie'] ) ) {
			return '';
		}
		$dedans = '';
		$rang   = count( $concept['chemin'] );
		// On compose de l'intérieur vers l'extérieur : chaque niveau enveloppe
		// le précédent, et le plus profond est le terme choisi.
		foreach ( array_reverse( $concept['chemin'] ) as $etage ) {
			$attributs = ' n="' . $rang . '" rendition="oe"'
				. ' source="' . self::xml( self::partie_ark( $etage['ark'] ) ) . '"';
			if ( 1 === $rang ) {
				$attributs = ' indexName="' . self::xml( $index_name ) . '"' . $attributs
					. ' xml:base="' . self::BASE_ARK . '"';
			}
			$termes = '';
			foreach ( $etage['labels'] as $langue => $mot ) {
				$termes .= '<term xml:lang="' . self::xml( $langue ) . '">'
					. self::xml( $mot ) . '</term>';
			}
			$dedans = '<index' . $attributs . '>' . $termes . $dedans . '</index>';
			--$rang;
		}
		return '<index indexName="Index"><term type="orig">'
			. self::xml( $graphie ) . '</term>' . $dedans . '</index>';
	}

	/**
	 * L'échappement XML, et non HTML : « esc_html » garde les entités
	 * nommées — « &nbsp; », « &eacute; » —, que XML ne connaît pas, et une
	 * autre extension peut le filtrer.
	 */
	public static function xml( $texte ) {
		return Notice_Archeomed_DOCX::esc( $texte );
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
		if ( ! is_array( $noeud ) || ! isset( $noeud[ self::SKOS . 'prefLabel' ] ) ) {
			return $out;
		}
		$items = $noeud[ self::SKOS . 'prefLabel' ];
		// Un objet seul, au lieu d'une liste, se lit comme une liste d'un.
		if ( is_array( $items ) && isset( $items['value'] ) ) {
			$items = array( $items );
		}
		foreach ( (array) $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['value'] ) || ! is_scalar( $item['value'] ) ) {
				continue;
			}
			$langue = ( isset( $item['lang'] ) && is_scalar( $item['lang'] ) ) ? (string) $item['lang'] : '';
			if ( '' === $langue ) {
				continue;
			}
			$valeur = (string) $item['value'];
			// Pactols rend quelques libellés hors de la forme NFC — en arabe
			// surtout. On les normalise, comme le reste de ce qu'on écrit.
			if ( class_exists( 'Normalizer' ) ) {
				$normal = Normalizer::normalize( $valeur, Normalizer::FORM_C );
				$valeur = false === $normal ? $valeur : $normal;
			}
			$out[ $langue ] = $valeur;
		}
		return $out;
	}

	/**
	 * La première valeur d'une propriété, ou une chaîne vide.
	 *
	 * L'API rend une liste d'objets « value » ; une réponse qui portait une
	 * valeur nue — « "12" » au lieu de « [{"value":"12"}] » — faisait lever
	 * « reset() » en PHP 8 : l'erreur sortait de la tâche, aucun essai ne se
	 * comptait, et le même terme bloquait la file à chaque passage. Les deux
	 * formes se lisent, et un objet seul aussi ; le reste vaut rien.
	 */
	private static function premiere( $noeud, $propriete ) {
		if ( ! is_array( $noeud ) || empty( $noeud[ $propriete ] ) ) {
			return '';
		}
		$valeurs = $noeud[ $propriete ];
		if ( is_scalar( $valeurs ) ) {
			return (string) $valeurs;
		}
		if ( ! is_array( $valeurs ) ) {
			return '';
		}
		$item = isset( $valeurs['value'] ) ? $valeurs : reset( $valeurs );
		if ( is_scalar( $item ) ) {
			return (string) $item;
		}
		return ( is_array( $item ) && isset( $item['value'] ) && is_scalar( $item['value'] ) )
			? (string) $item['value'] : '';
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
	 * Un ARK de Frantiq sous sa forme d'usage, ou une chaîne vide.
	 *
	 * Rien ne le contrôlait : un lieu gardait « javascript:… », un terme un
	 * ARK d'un autre hôte, et l'un comme l'autre partait en lien dans le
	 * Word. Un « http:// » ne se résolvait jamais, les clefs de l'API étant en
	 * https : il est ramené à https. Le reste n'est pas un identifiant, et le
	 * terme se garde sans lui.
	 */
	public static function ark_propre( $ark ) {
		$ark = trim( (string) $ark );
		if ( 0 === stripos( $ark, 'http://' ) ) {
			$ark = 'https://' . substr( $ark, 7 );
		}
		return preg_match( '#^https://ark\.frantiq\.fr/ark:/26678/[A-Za-z0-9_-]+$#', $ark ) ? $ark : '';
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
		// Seul un échec de transport peut être une coupure : une réponse,
		// même lente, même en erreur, est une réponse de Pactols.
		$coupe = is_wp_error( $reponse ) && $delai < self::DELAI && microtime( true ) - $debut >= $delai - 0.5;
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
