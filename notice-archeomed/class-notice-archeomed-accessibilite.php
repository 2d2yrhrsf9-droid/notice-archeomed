<?php
/**
 * L'onglet « Accessibilité » des réglages : un contrôle automatique du
 * formulaire publié, et la trace de l'audit officiel.
 *
 * La revue s'engage dans une démarche d'accessibilité, et rien ne disait où
 * en était le formulaire par lequel les auteurs déposent. Deux mesures, qui
 * ne se valent pas :
 *
 * — le contrôle automatique, que l'onglet lance lui-même : la page publiée
 *   du formulaire, chargée dans le navigateur de l'administrateur, passe au
 *   moteur libre axe-core. Il dit vite ce qui est en défaut, et d'où cela
 *   vient — l'extension ou le thème —, mais ses tests ne couvrent qu'une
 *   partie des critères : son score n'est pas un taux de conformité ;
 * — l'audit RGAA, manuel, que l'État outille avec Ara : c'est son taux qui
 *   compte, et l'onglet en garde la trace.
 *
 * Aucun appel réseau ne part du serveur : axe-core se charge dans le
 * navigateur, et le résultat revient par une requête authentifiée.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Accessibilite {

	/** L'option qui garde le dernier contrôle et le dernier audit. */
	const OPTION = 'notice_archeomed_accessibilite';

	/** L'action de la requête qui enregistre un contrôle. */
	const ACTION = 'na_accessibilite';

	/** L'action du formulaire qui enregistre l'audit Ara. */
	const ACTION_ARA = 'na_accessibilite_ara';

	/** Le moteur, à une version fixée : le score ne bouge pas d'un jour à l'autre. */
	const AXE = 'https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.10.2/axe.min.js';

	/**
	 * L'empreinte du moteur : il s'exécute dans une page de même origine que
	 * l'administration, chez un administrateur connecté ; un fichier altéré
	 * sur le réseau de diffusion est refusé par le navigateur.
	 */
	const AXE_EMPREINTE = 'sha512-L2dmfC6LEkRQ7X+1d1KEBA3Qe/E8OvB4kSSr+luMNilFtuBsvglrQcdM9jd6uiFbeI+Pqax+M6s/vak/okidBw==';

	/** Les règles jouées : WCAG 2.0 et 2.1, niveaux A et AA. */
	const NORMES = array( 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' );

	/** Ce qu'on garde au plus de chaque liste : de quoi lire, non archiver. */
	const PLAFOND = 120;

	/** L'action du formulaire de la fiche qui corrige les textes des figures. */
	const ACTION_FIGURES = 'na_figures_accessibles';

	/**
	 * La métadonnée, une ligne par correction, qui garde la valeur remplacée
	 * d'un texte de figure : aucune ne disparaît.
	 */
	const HISTORIQUE = '_na_historique_figures';

	/** La notice dont l'encart a posé des champs : son formulaire suit en pied de page. */
	private $formulaire_des_figures = 0;

	public function __construct() {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'enregistrer_le_controle' ) );
		add_action( 'admin_post_' . self::ACTION_ARA, array( $this, 'enregistrer_l_audit' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'charger' ) );
		// La fiche d'une notice : les textes d'accessibilité de ses figures,
		// leur état, et leur correction par la rédaction.
		add_action( 'add_meta_boxes_' . Notice_Archeomed_File::CPT, array( $this, 'poser_l_encart' ) );
		add_action( 'admin_post_' . self::ACTION_FIGURES, array( $this, 'enregistrer_les_figures' ) );
		add_action( 'admin_footer', array( $this, 'poser_le_formulaire_des_figures' ) );
		add_action( 'admin_head', array( $this, 'poser_le_style_de_l_encart' ) );
	}

	/** Ce qui est gardé : « controle » et « ara », chacun peut-être vide. */
	public static function lire() {
		$garde = get_option( self::OPTION, array() );
		$garde = is_array( $garde ) ? $garde : array();
		return array(
			'controle' => isset( $garde['controle'] ) && is_array( $garde['controle'] ) ? $garde['controle'] : array(),
			'ara'      => isset( $garde['ara'] ) && is_array( $garde['ara'] ) ? $garde['ara'] : array(),
		);
	}

	/** Est-on sur l'onglet ? Le script et son style ne se chargent que là. */
	private static function sur_l_onglet() {
		return is_admin() && isset( $_GET['page'], $_GET['tab'] )
			&& Notice_Archeomed_Settings::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) )
			&& 'accessibilite' === sanitize_key( wp_unslash( $_GET['tab'] ) );
	}

	/**
	 * Le résultat d'un contrôle reçu du navigateur, réduit à ce qu'on garde.
	 *
	 * Tout vient d'une page qu'un administrateur a chargée : on ne lui fait
	 * pas confiance pour autant. Les nombres sont bornés, les textes passés au
	 * tamis, les listes plafonnées ; le score se recalcule ici, des comptes
	 * reçus, au lieu d'être cru.
	 */
	public static function nettoyer_le_controle( $brut ) {
		$brut  = is_array( $brut ) ? $brut : array();
		$liste = function ( $cle, $champs ) use ( $brut ) {
			$out = array();
			foreach ( isset( $brut[ $cle ] ) && is_array( $brut[ $cle ] ) ? $brut[ $cle ] : array() as $regle ) {
				if ( ! is_array( $regle ) || empty( $regle['id'] ) || ! is_scalar( $regle['id'] ) ) {
					continue;
				}
				$propre = array( 'id' => substr( sanitize_key( (string) $regle['id'] ), 0, 80 ) );
				foreach ( $champs as $champ ) {
					$valeur = isset( $regle[ $champ ] ) && is_scalar( $regle[ $champ ] ) ? (string) $regle[ $champ ] : '';
					$propre[ $champ ] = 'n' === $champ ? max( 0, min( 100000, (int) $valeur ) ) : substr( sanitize_text_field( $valeur ), 0, 300 );
				}
				if ( isset( $propre['gravite'] ) && ! in_array( $propre['gravite'], array( 'critique', 'grave', 'modérée', 'mineure', '' ), true ) ) {
					$propre['gravite'] = '';
				}
				if ( isset( $propre['origine'] ) && ! in_array( $propre['origine'], array( 'extension', 'thème', 'extension et thème' ), true ) ) {
					$propre['origine'] = '';
				}
				$out[] = $propre;
				if ( count( $out ) >= self::PLAFOND ) {
					break;
				}
			}
			return $out;
		};
		$controle = array(
			'conformes'  => $liste( 'conformes', array( 'explication' ) ),
			'echecs'     => $liste( 'echecs', array( 'gravite', 'n', 'explication', 'origine' ) ),
			'a_verifier' => $liste( 'a_verifier', array( 'n', 'explication' ) ),
			'page'       => isset( $brut['page'] ) && is_string( $brut['page'] ) ? esc_url_raw( $brut['page'], array( 'http', 'https' ) ) : '',
			'moteur'     => isset( $brut['moteur'] ) && is_scalar( $brut['moteur'] ) ? substr( sanitize_text_field( (string) $brut['moteur'] ), 0, 40 ) : '',
		);
		$conformes = count( $controle['conformes'] );
		$echecs    = count( $controle['echecs'] );
		$controle['score'] = ( $conformes + $echecs ) > 0 ? (int) round( 100 * $conformes / ( $conformes + $echecs ) ) : 0;
		return $controle;
	}

	/**
	 * Les champs de l'audit Ara, réduits à ce qu'on garde : un taux entre 0
	 * et 100 (virgule admise), une date au format du calendrier, un lien.
	 * Un champ vide efface la valeur ; un champ faux est écarté et le dit.
	 */
	public static function nettoyer_l_audit( $brut ) {
		$brut    = is_array( $brut ) ? $brut : array();
		$audit   = array( 'taux' => '', 'date' => '', 'lien' => '' );
		$refuses = array();
		$taux    = isset( $brut['taux'] ) && is_scalar( $brut['taux'] ) ? trim( str_replace( array( ',', '%', ' ', "\u{00A0}" ), array( '.', '', '', '' ), (string) $brut['taux'] ) ) : '';
		if ( '' !== $taux ) {
			if ( is_numeric( $taux ) && (float) $taux >= 0 && (float) $taux <= 100 ) {
				$audit['taux'] = rtrim( rtrim( number_format( (float) $taux, 2, '.', '' ), '0' ), '.' );
			} else {
				$refuses[] = 'taux';
			}
		}
		$date = isset( $brut['date'] ) && is_scalar( $brut['date'] ) ? trim( (string) $brut['date'] ) : '';
		if ( '' !== $date ) {
			if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
				$audit['date'] = $date;
			} else {
				$refuses[] = 'date';
			}
		}
		$lien = isset( $brut['lien'] ) && is_scalar( $brut['lien'] ) ? trim( (string) $brut['lien'] ) : '';
		if ( '' !== $lien ) {
			$propre = esc_url_raw( $lien, array( 'http', 'https' ) );
			if ( '' !== $propre && preg_match( '#^https?://[^\s/]+\.[^\s]+#', $propre ) ) {
				$audit['lien'] = $propre;
			} else {
				$refuses[] = 'lien';
			}
		}
		return array( $audit, $refuses );
	}

	/**
	 * La requête du navigateur qui garde le contrôle qu'il vient de faire.
	 * Réservée à qui règle l'extension, et protégée par un jeton.
	 */
	public function enregistrer_le_controle() {
		check_ajax_referer( self::ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Droits insuffisants.' ), 403 );
		}
		$brut = isset( $_POST['resultat'] ) && is_string( $_POST['resultat'] )
			? json_decode( wp_unslash( $_POST['resultat'] ), true ) : null;
		if ( ! is_array( $brut ) ) {
			wp_send_json_error( array( 'message' => 'Résultat illisible.' ), 400 );
		}
		$controle         = self::nettoyer_le_controle( $brut );
		$controle['date'] = current_time( 'mysql' );
		$garde            = self::lire();
		$garde['controle'] = $controle;
		update_option( self::OPTION, $garde, false );
		wp_send_json_success( array( 'score' => $controle['score'], 'date' => $controle['date'] ) );
	}

	/** Le formulaire de l'audit Ara : on garde, puis on revient à l'onglet. */
	public function enregistrer_l_audit() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		check_admin_referer( self::ACTION_ARA );
		list( $audit, $refuses ) = self::nettoyer_l_audit( array(
			'taux' => isset( $_POST['na_ara_taux'] ) ? wp_unslash( $_POST['na_ara_taux'] ) : '',
			'date' => isset( $_POST['na_ara_date'] ) ? wp_unslash( $_POST['na_ara_date'] ) : '',
			'lien' => isset( $_POST['na_ara_lien'] ) ? wp_unslash( $_POST['na_ara_lien'] ) : '',
		) );
		$garde = self::lire();
		$avant = $garde['ara'];
		// Un champ refusé garde sa valeur d'avant : une faute de frappe ne
		// doit pas effacer le taux d'un audit.
		foreach ( $refuses as $champ ) {
			$audit[ $champ ] = isset( $avant[ $champ ] ) ? (string) $avant[ $champ ] : '';
		}
		$garde['ara'] = $audit;
		update_option( self::OPTION, $garde, false );
		wp_safe_redirect( add_query_arg( 'na_ara', empty( $refuses ) ? 'ok' : implode( ',', $refuses ),
			Notice_Archeomed_Settings::url( 'accessibilite' ) ) );
		exit;
	}

	/**
	 * Les figures d'une saisie, par leur rang : seules celles qui en sont
	 * — un tableau numéroté. Une saisie ancienne aux illustrations en texte
	 * libre n'en a aucune à régler une à une.
	 */
	public static function figures_de( $donnees ) {
		$figures = array();
		if ( ! is_array( $donnees ) || empty( $donnees['illustrations'] ) || ! is_array( $donnees['illustrations'] ) ) {
			return $figures;
		}
		foreach ( $donnees['illustrations'] as $cle => $item ) {
			if ( is_array( $item ) && isset( $item['rang'] ) && (int) $item['rang'] > 0 && ! isset( $figures[ (int) $item['rang'] ] ) ) {
				$figures[ (int) $item['rang'] ] = array( 'cle' => $cle, 'item' => $item );
			}
		}
		ksort( $figures );
		return $figures;
	}

	/**
	 * Les textes d'accessibilité corrigés par la rédaction, posés dans la
	 * saisie sans rien toucher d'autre. Mêmes nettoyages et mêmes limites
	 * qu'au dépôt. Rend la saisie, les entrées d'historique — date,
	 * utilisateur, figure, champ, valeur d'avant et d'après —, et si un
	 * texte a dû être coupé.
	 */
	public static function corriger_les_figures( $donnees, $alts, $descriptions, $utilisateur = 0 ) {
		$entrees = array();
		$coupe   = false;
		$qui     = get_userdata( (int) $utilisateur );
		$champs  = array(
			'alt'         => array( (array) $alts, Notice_Archeomed_Controles::ALT_MAX ),
			'description' => array( (array) $descriptions, Notice_Archeomed_Controles::DESCRIPTION_MAX ),
		);
		foreach ( self::figures_de( $donnees ) as $rang => $figure ) {
			foreach ( $champs as $champ => $reglage ) {
				list( $soumis, $max ) = $reglage;
				if ( ! isset( $soumis[ $rang ] ) || ! is_string( $soumis[ $rang ] ) ) {
					continue;     // champ absent : on n'y touche pas
				}
				$propre = 'alt' === $champ
					? Notice_Archeomed_Controles::texte_alternatif_propre( $soumis[ $rang ] )
					: Notice_Archeomed_Controles::description_propre( $soumis[ $rang ] );
				if ( mb_strlen( $propre, 'UTF-8' ) > $max ) {
					$propre = mb_substr( $propre, 0, $max, 'UTF-8' );
					$coupe  = true;
				}
				$avant = isset( $figure['item'][ $champ ] ) && is_scalar( $figure['item'][ $champ ] ) ? (string) $figure['item'][ $champ ] : '';
				if ( $propre === $avant ) {
					continue;
				}
				$donnees['illustrations'][ $figure['cle'] ][ $champ ] = $propre;
				$entrees[] = array(
					'date'        => current_time( 'mysql' ),
					'utilisateur' => (int) $utilisateur,
					'nom'         => $qui ? (string) $qui->display_name : '',
					'figure'      => (int) $rang,
					'champ'       => $champ,
					'avant'       => $avant,
					'apres'       => $propre,
				);
			}
		}
		return array( $donnees, $entrees, $coupe );
	}

	/**
	 * L'enregistrement depuis la fiche. L'historique s'écrit d'abord : si la
	 * saisie ne pouvait s'écrire ensuite, la valeur d'avant ne serait pas
	 * perdue pour autant.
	 */
	public function enregistrer_les_figures() {
		$id = isset( $_POST['post'] ) ? (int) $_POST['post'] : 0;
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'notice-archeomed' ) );
		}
		check_admin_referer( self::ACTION_FIGURES . '_' . $id );
		$donnees = get_post_meta( $id, '_na_donnees', true );
		$issue   = 'illisible';
		$coupe   = false;
		if ( Notice_Archeomed_File::CPT === get_post_type( $id ) && ! empty( self::figures_de( $donnees ) ) ) {
			list( $donnees, $entrees, $coupe ) = self::corriger_les_figures( $donnees,
				isset( $_POST['na_alt'] ) && is_array( $_POST['na_alt'] ) ? wp_unslash( $_POST['na_alt'] ) : array(),
				isset( $_POST['na_description'] ) && is_array( $_POST['na_description'] ) ? wp_unslash( $_POST['na_description'] ) : array(),
				get_current_user_id() );
			foreach ( $entrees as $entree ) {
				add_post_meta( $id, self::HISTORIQUE, wp_slash( $entree ) );
			}
			if ( ! empty( $entrees ) ) {
				update_post_meta( $id, '_na_donnees', wp_slash( $donnees ) );
			}
			$issue = (string) count( $entrees );
		}
		wp_safe_redirect( add_query_arg( array( 'post' => $id, 'action' => 'edit', 'na_figures' => $issue, 'na_figures_coupe' => $coupe ? 1 : 0 ),
			admin_url( 'post.php' ) ) . '#na_accessibilite' );
		exit;
	}

	/** L'encart sur la fiche d'une notice, après « La notice ». */
	public function poser_l_encart() {
		add_meta_box( 'na_accessibilite', __( 'Accessibilité des illustrations', 'notice-archeomed' ),
			array( $this, 'afficher_l_encart' ), Notice_Archeomed_File::CPT, 'normal', 'default' );
	}

	/** Le mot et l'icône d'un état : jamais la couleur seule. */
	private static function etat_lisible( $etat ) {
		$etats = array(
			'ok'          => array( 'na-etat--ok', 'dashicons-yes-alt', 'OK' ),
			'a_verifier'  => array( 'na-etat--attente', 'dashicons-warning', 'À vérifier' ),
			'a_completer' => array( 'na-etat--echec', 'dashicons-edit', 'À compléter' ),
		);
		$e = isset( $etats[ $etat ] ) ? $etats[ $etat ] : $etats['a_completer'];
		return '<span class="na-etat ' . esc_attr( $e[0] ) . '"><span class="dashicons ' . esc_attr( $e[1] ) . '" aria-hidden="true"></span> '
			. esc_html( $e[2] ) . '</span>';
	}

	/**
	 * Le tableau des figures : légende et crédits pour mémoire, texte
	 * alternatif et description à corriger, et l'état de chacune.
	 *
	 * L'encart vit dans le formulaire de la fiche, que WordPress ouvre
	 * autour de tous les encarts : un formulaire ne s'imbrique pas dans un
	 * autre. Les champs se rattachent donc, par leur attribut « form », à un
	 * formulaire posé hors de lui, en pied de page.
	 */
	public function afficher_l_encart( $post ) {
		$id      = (int) $post->ID;
		$donnees = get_post_meta( $id, '_na_donnees', true );
		$figures = self::figures_de( $donnees );
		$issue   = isset( $_GET['na_figures'] ) ? sanitize_key( wp_unslash( $_GET['na_figures'] ) ) : '';
		if ( 'illisible' === $issue ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Rien n’a été enregistré : la saisie de cette notice ne porte pas de figures lisibles.', 'notice-archeomed' ) . '</p></div>';
		} elseif ( preg_match( '/^\d+$/', $issue ) ) {
			$n = (int) $issue;
			echo '<div class="notice notice-success inline"><p>' . esc_html( 0 === $n
				? 'Rien n’a changé : aucun texte n’était différent.'
				: sprintf( "%d\u{00A0}texte%s enregistré%s. Le Word retéléchargé, le fascicule et le dossier Métopes les porteront.", $n, $n > 1 ? 's' : '', $n > 1 ? 's' : '' ) )
				. ( ! empty( $_GET['na_figures_coupe'] ) ? ' ' . esc_html( "Un texte dépassait sa limite\u{00A0}: il a été coupé, l’historique garde la valeur d’avant." ) : '' )
				. '</p></div>';
		}
		if ( empty( $figures ) ) {
			echo '<p>' . esc_html( is_array( $donnees ) && ! empty( $donnees['illustrations'] ) && ! is_array( $donnees['illustrations'] )
				? "Les illustrations de cette notice sont décrites en texte libre (saisie ancienne)\u{00A0}: rien à régler figure par figure."
				: 'Aucune figure dans la saisie de cette notice.' ) . '</p>';
			return;
		}
		$this->formulaire_des_figures = $id;
		echo '<p class="description na-a11y-mesure">' . esc_html( "Le texte alternatif et la description détaillée de chaque figure se corrigent ici, avec les limites du dépôt (300 et 2\u{00A0}000\u{00A0}caractères). Le Word retéléchargé, le fascicule et le dossier Métopes produits ensuite portent la valeur corrigée\u{00A0}; le courriel déjà parti et l’encart «\u{00A0}La notice\u{00A0}» gardent le texte reçu. Chaque valeur remplacée reste dans l’historique." ) . '</p>';
		echo '<table class="widefat striped na-a11y-figures"><caption class="screen-reader-text">'
			. esc_html__( 'Textes d’accessibilité des figures de la notice', 'notice-archeomed' ) . '</caption><thead><tr>'
			. '<th scope="col">Figure</th><th scope="col">Légende</th><th scope="col">Crédits</th>'
			. '<th scope="col">Texte alternatif</th><th scope="col">Description détaillée</th><th scope="col">État</th></tr></thead><tbody>';
		foreach ( $figures as $rang => $figure ) {
			$item  = $figure['item'];
			$texte = function ( $cle ) use ( $item ) {
				return isset( $item[ $cle ] ) && is_scalar( $item[ $cle ] ) ? str_replace( '&lt;', '<', (string) $item[ $cle ] ) : '';
			};
			$fig   = Notice_Archeomed_Normes::numero_de_figure( $rang );
			$etat  = Notice_Archeomed_Controles::etat_d_accessibilite( $item );
			$id_alt  = 'na-a11y-alt-' . $rang;
			$id_desc = 'na-a11y-description-' . $rang;
			$id_etat = 'na-a11y-etat-' . $rang;
			echo '<tr><th scope="row">' . esc_html( $fig )
				. ( '' !== $texte( 'titre' ) ? '<br><span class="description">' . esc_html( $texte( 'titre' ) ) . '</span>' : '' ) . '</th>'
				. '<td data-colonne="Légende">' . nl2br( esc_html( $texte( 'legende' ) ) ) . '</td>'
				. '<td data-colonne="Crédits">' . nl2br( esc_html( $texte( 'credits' ) ) ) . '</td>'
				. '<td data-colonne="Texte alternatif"><label class="screen-reader-text" for="' . esc_attr( $id_alt ) . '">' . esc_html( 'Texte alternatif de la ' . $fig ) . '</label>'
				. '<textarea id="' . esc_attr( $id_alt ) . '" name="na_alt[' . (int) $rang . ']" form="na-a11y-figures" rows="4" maxlength="'
				. (int) Notice_Archeomed_Controles::ALT_MAX . '" aria-describedby="' . esc_attr( $id_etat ) . '">' . esc_textarea( $texte( 'alt' ) ) . '</textarea></td>'
				. '<td data-colonne="Description détaillée"><label class="screen-reader-text" for="' . esc_attr( $id_desc ) . '">' . esc_html( 'Description détaillée de la ' . $fig ) . '</label>'
				. '<textarea id="' . esc_attr( $id_desc ) . '" name="na_description[' . (int) $rang . ']" form="na-a11y-figures" rows="4" maxlength="'
				. (int) Notice_Archeomed_Controles::DESCRIPTION_MAX . '">' . esc_textarea( $texte( 'description' ) ) . '</textarea></td>'
				. '<td data-colonne="État" id="' . esc_attr( $id_etat ) . '">' . self::etat_lisible( $etat['etat'] );
			if ( ! empty( $etat['raisons'] ) ) {
				echo '<ul class="na-a11y-raisons">';
				foreach ( $etat['raisons'] as $raison ) {
					echo '<li>' . esc_html( $raison ) . '</li>';
				}
				if ( 'a_completer' === $etat['etat'] && '' !== $texte( 'titre' ) ) {
					echo '<li>' . esc_html( "Le Word porte le titre à sa place." ) . '</li>';
				}
				echo '</ul>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p><button type="submit" class="button button-primary" form="na-a11y-figures">'
			. esc_html__( 'Enregistrer les textes des figures', 'notice-archeomed' ) . '</button></p>';
		self::afficher_l_historique( $id );
	}

	/** Les corrections faites depuis la fiche, la plus récente d'abord. */
	private static function afficher_l_historique( $id ) {
		$entrees = array_filter( (array) get_post_meta( $id, self::HISTORIQUE, false ), 'is_array' );
		if ( empty( $entrees ) ) {
			return;
		}
		$entrees = array_reverse( $entrees );
		echo '<details class="na-a11y-liste"><summary>' . esc_html( sprintf( 'Historique des corrections (%d)', count( $entrees ) ) ) . '</summary><ul>';
		foreach ( $entrees as $e ) {
			$champ = ( isset( $e['champ'] ) && 'description' === $e['champ'] ) ? 'description détaillée' : 'texte alternatif';
			$valeur = function ( $cle ) use ( $e ) {
				$v = isset( $e[ $cle ] ) && is_scalar( $e[ $cle ] ) ? str_replace( '&lt;', '<', (string) $e[ $cle ] ) : '';
				return '' === $v ? '(vide)' : "«\u{00A0}" . $v . "\u{00A0}»";
			};
			echo '<li>' . esc_html( sprintf( "%1\$s, %2\$s — %3\$s, %4\$s\u{00A0}: était %5\$s, devient %6\$s.",
				isset( $e['date'] ) ? mysql2date( 'j F Y à G\hi', (string) $e['date'] ) : '',
				! empty( $e['nom'] ) ? (string) $e['nom'] : 'utilisateur ' . ( isset( $e['utilisateur'] ) ? (int) $e['utilisateur'] : 0 ),
				Notice_Archeomed_Normes::numero_de_figure( isset( $e['figure'] ) ? (int) $e['figure'] : 0 ),
				$champ, $valeur( 'avant' ), $valeur( 'apres' ) ) ) . '</li>';
		}
		echo '</ul></details>';
	}

	/**
	 * Le formulaire auquel se rattachent les champs de l'encart, hors du
	 * formulaire de la fiche. Le jeton s'écrit sans identifiant : celui de
	 * WordPress, « _wpnonce », est déjà dans la page.
	 */
	public function poser_le_formulaire_des_figures() {
		if ( ! $this->formulaire_des_figures ) {
			return;
		}
		$id = (int) $this->formulaire_des_figures;
		echo '<form id="na-a11y-figures" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_FIGURES ) . '">'
			. '<input type="hidden" name="post" value="' . $id . '">'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( self::ACTION_FIGURES . '_' . $id ) ) . '">'
			. '</form>';
	}

	/** Le style de l'encart : un tableau qui tient dans la largeur de la fiche. */
	public function poser_le_style_de_l_encart() {
		$ecran = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $ecran || Notice_Archeomed_File::CPT !== $ecran->post_type || 'post' !== $ecran->base ) {
			return;
		}
		// Six largeurs qui font cent : laissée au calcul, la colonne de l'état,
		// la dernière, tombait à quelques pixels et ses mots se lisaient une
		// lettre par ligne.
		echo '<style>.na-a11y-figures{table-layout:fixed}.na-a11y-figures thead th:nth-child(1){width:12%}'
			. '.na-a11y-figures thead th:nth-child(2){width:16%}.na-a11y-figures thead th:nth-child(3){width:10%}'
			. '.na-a11y-figures thead th:nth-child(4),.na-a11y-figures thead th:nth-child(5){width:21%}.na-a11y-figures thead th:nth-child(6){width:20%}'
			. '.na-a11y-figures textarea{width:100%;min-height:7em}.na-a11y-figures td,.na-a11y-figures th{vertical-align:top;overflow-wrap:break-word}'
			. '.na-a11y-raisons{margin:.4em 0 0 1.2em;list-style:disc}.na-a11y-raisons li{margin:0 0 .3em}'
			. '@media screen and (max-width:782px){.na-a11y-figures,.na-a11y-figures tbody,.na-a11y-figures tr,.na-a11y-figures td,.na-a11y-figures th{display:block;width:auto}'
			. '.na-a11y-figures thead{display:none}.na-a11y-figures td:before{content:attr(data-colonne);display:block;font-weight:600}}'
			. '</style>';
	}

	/**
	 * Le récapitulatif des figures de toutes les notices : combien sont à
	 * compléter, combien à vérifier, et dans quelles notices. Les notices
	 * qu'une correction remplace n'y sont pas : elles ne paraîtront pas.
	 *
	 * La saisie se lit par paquets, en une requête chacun : quelques
	 * centaines de notices par an, à garder des années, ne doivent pas
	 * charger toutes leurs métadonnées d'un coup.
	 */
	public static function recapitulatif_des_figures() {
		global $wpdb;
		$remplacees = array_map( 'strtoupper', array_map( 'trim', (array) $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
			WHERE m.meta_key = '_na_remplace' AND m.meta_value <> '' AND p.post_type = %s AND p.post_status = 'private'",
			Notice_Archeomed_File::CPT ) ) ) );
		$recap  = array( 'notices' => 0, 'ok' => 0, 'a_verifier' => 0, 'a_completer' => 0, 'liste' => array() );
		$depuis = 0;
		do {
			$lignes = $wpdb->get_results( $wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_date, m.meta_value FROM {$wpdb->posts} p
				JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_na_donnees'
				WHERE p.post_type = %s AND p.post_status = 'private' AND p.ID > %d ORDER BY p.ID ASC LIMIT 200",
				Notice_Archeomed_File::CPT, $depuis ) );
			foreach ( (array) $lignes as $ligne ) {
				$depuis = (int) $ligne->ID;
				$figures = self::figures_de( maybe_unserialize( $ligne->meta_value ) );
				if ( empty( $figures ) ) {
					continue;
				}
				$reference = strtoupper( trim( (string) get_post_meta( (int) $ligne->ID, '_na_reference', true ) ) );
				if ( '' !== $reference && in_array( $reference, $remplacees, true ) ) {
					continue;
				}
				++$recap['notices'];
				$compte = array( 'ok' => 0, 'a_verifier' => 0, 'a_completer' => 0 );
				foreach ( $figures as $figure ) {
					$etat = Notice_Archeomed_Controles::etat_d_accessibilite( $figure['item'] );
					++$compte[ $etat['etat'] ];
					++$recap[ $etat['etat'] ];
				}
				if ( $compte['a_verifier'] + $compte['a_completer'] > 0 ) {
					$recap['liste'][] = array( 'id' => (int) $ligne->ID, 'titre' => (string) $ligne->post_title,
						'date' => (string) $ligne->post_date, 'a_verifier' => $compte['a_verifier'], 'a_completer' => $compte['a_completer'] );
				}
			}
		} while ( count( (array) $lignes ) === 200 );
		// Les plus récentes d'abord : ce sont elles qui partent au prochain numéro.
		usort( $recap['liste'], function ( $a, $b ) {
			return strcmp( $b['date'], $a['date'] );
		} );
		return $recap;
	}

	/** Le récapitulatif, dans l'onglet. */
	private static function afficher_le_recapitulatif() {
		$recap = self::recapitulatif_des_figures();
		echo '<h2>' . esc_html__( 'Les illustrations des notices reçues', 'notice-archeomed' ) . '</h2>';
		if ( 0 === $recap['notices'] ) {
			echo '<p>' . esc_html__( 'Aucune notice reçue ne porte de figure.', 'notice-archeomed' ) . '</p>';
			return;
		}
		echo '<p>' . esc_html( sprintf( "Dans %1\$d\u{00A0}notice%2\$s qui portent des figures\u{00A0}: %3\$d\u{00A0}figure%4\$s à compléter (sans texte alternatif), %5\$d à vérifier, %6\$d en ordre.",
			$recap['notices'], $recap['notices'] > 1 ? 's' : '', $recap['a_completer'], $recap['a_completer'] > 1 ? 's' : '',
			$recap['a_verifier'], $recap['ok'] ) ) . '</p>';
		if ( empty( $recap['liste'] ) ) {
			return;
		}
		echo '<p class="description na-a11y-mesure">' . esc_html( "Chaque fiche donne le détail, figure par figure, et permet de corriger le texte alternatif et la description détaillée. Les notices remplacées par une correction ne sont pas comptées." ) . '</p>';
		echo '<table class="widefat striped na-a11y-recap"><caption class="screen-reader-text">'
			. esc_html__( 'Notices dont des figures sont à compléter ou à vérifier', 'notice-archeomed' ) . '</caption><thead><tr>'
			. '<th scope="col">Notice</th><th scope="col">Reçue le</th><th scope="col">À compléter</th><th scope="col">À vérifier</th></tr></thead><tbody>';
		foreach ( array_slice( $recap['liste'], 0, 300 ) as $notice ) {
			$lien = get_edit_post_link( $notice['id'], 'url' );
			echo '<tr><th scope="row"><a href="' . esc_url( $lien . '#na_accessibilite' ) . '">'
				. esc_html( '' !== trim( $notice['titre'] ) ? $notice['titre'] : '(sans titre)' ) . '</a></th>'
				. '<td>' . esc_html( mysql2date( 'j F Y', $notice['date'] ) ) . '</td>'
				. '<td>' . (int) $notice['a_completer'] . '</td><td>' . (int) $notice['a_verifier'] . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( count( $recap['liste'] ) > 300 ) {
			echo '<p class="description">' . esc_html( sprintf( "Et %d\u{00A0}autres notices.", count( $recap['liste'] ) - 300 ) ) . '</p>';
		}
	}

	/** Le script et le style de l'onglet, et seulement de lui. */
	public function charger() {
		if ( ! self::sur_l_onglet() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_register_script( 'na-accessibilite', false, array(), '1', true );
		wp_enqueue_script( 'na-accessibilite' );
		wp_add_inline_script( 'na-accessibilite', 'window.naAccessibilite = ' . wp_json_encode( array(
			'ajax'      => admin_url( 'admin-ajax.php' ),
			'action'    => self::ACTION,
			'nonce'     => wp_create_nonce( self::ACTION ),
			'axe'       => self::AXE,
			'empreinte' => self::AXE_EMPREINTE,
			'normes'    => self::NORMES,
		) ) . ';' . self::script() );
		wp_register_style( 'na-accessibilite', false, array(), '1' );
		wp_enqueue_style( 'na-accessibilite' );
		wp_add_inline_style( 'na-accessibilite', self::style() );
	}

	/** L'adresse publiée du formulaire, ou une chaîne vide. */
	private static function page_du_formulaire() {
		$plugin = isset( $GLOBALS['notice_archeomed_plugin'] ) ? $GLOBALS['notice_archeomed_plugin'] : null;
		return ( is_object( $plugin ) && method_exists( $plugin, 'adresse_du_formulaire' ) )
			? (string) $plugin->adresse_du_formulaire() : '';
	}

	/** L'onglet lui-même. */
	public static function onglet() {
		$garde = self::lire();
		$page  = self::page_du_formulaire();
		$retour = isset( $_GET['na_ara'] ) ? sanitize_text_field( wp_unslash( $_GET['na_ara'] ) ) : '';
		if ( 'ok' === $retour ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Audit enregistré.', 'notice-archeomed' ) . '</p></div>';
		} elseif ( '' !== $retour ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( "Valeur écartée, la précédente est gardée\u{00A0}: "
				. str_replace( array( 'taux', 'date', 'lien' ), array( 'le taux (entre 0 et 100)', 'la date', 'le lien' ), $retour ) . '.' ) . '</p></div>';
		}
		self::afficher_le_recapitulatif();
		?>
		<h2><?php esc_html_e( 'Contrôle automatique du formulaire', 'notice-archeomed' ); ?></h2>
		<p class="description na-a11y-mesure">
			Le bouton charge ci-dessous la page publiée du formulaire et la fait
			passer au moteur libre <a href="https://github.com/dequelabs/axe-core">axe-core</a>,
			règles WCAG&nbsp;2.0 et 2.1, niveaux A et AA. Le moteur se charge dans
			votre navigateur&nbsp;; le serveur n’appelle aucun service.
		</p>
		<div class="notice notice-warning inline"><p>
			<strong>Le score indicatif n’est pas le taux de conformité RGAA.</strong>
			Il compte les règles automatiques satisfaites parmi celles qui
			s’appliquent à la page. Les tests automatiques ne couvrent qu’une
			partie des critères&nbsp;: le reste demande un audit manuel (voir plus bas).
		</p></div>
		<?php if ( '' === $page ) : ?>
			<p><?php esc_html_e( 'Aucune page publiée ne porte le formulaire : rien à contrôler.', 'notice-archeomed' ); ?></p>
		<?php else : ?>
			<p>
				<button type="button" class="button button-primary" id="na-a11y-lancer" data-page="<?php echo esc_url( $page ); ?>">
					<?php esc_html_e( 'Contrôler l’accessibilité du formulaire', 'notice-archeomed' ); ?>
				</button>
				<span id="na-a11y-etat" role="status"></span>
			</p>
			<div id="na-a11y-cadre" hidden>
				<p class="description"><?php echo esc_html( "Page contrôlée\u{00A0}: " . $page ); ?></p>
				<iframe id="na-a11y-page" title="<?php esc_attr_e( 'Page du formulaire en cours de contrôle', 'notice-archeomed' ); ?>"></iframe>
			</div>
		<?php endif; ?>
		<?php self::afficher_le_controle( $garde['controle'] ); ?>

		<h2><?php esc_html_e( 'Audit officiel RGAA', 'notice-archeomed' ); ?></h2>
		<div class="na-a11y-encadre">
			<p>
				L’outil que recommande l’État est <a href="https://ara.numerique.gouv.fr/">Ara</a>
				(DINUM), libre et gratuit, qui suit le RGAA&nbsp;4.1. Il guide un
				<strong>audit manuel</strong>, critère par critère&nbsp;: c’est son
				<strong>taux de conformité</strong> qui compte, non le score ci-dessus.
			</p>
			<p>
				Un organisme public doit publier une <strong>déclaration
				d’accessibilité</strong> de son site, qui donne ce taux.
			</p>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_ARA ); ?>">
			<?php wp_nonce_field( self::ACTION_ARA ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="na-ara-taux"><?php esc_html_e( 'Dernier taux de conformité RGAA (Ara)', 'notice-archeomed' ); ?></label></th>
					<td><input type="text" inputmode="decimal" id="na-ara-taux" name="na_ara_taux" class="small-text"
						value="<?php echo esc_attr( isset( $garde['ara']['taux'] ) ? str_replace( '.', ',', (string) $garde['ara']['taux'] ) : '' ); ?>"
						aria-describedby="na-ara-taux-aide"> %
						<p class="description" id="na-ara-taux-aide"><?php esc_html_e( 'Entre 0 et 100, par exemple 87,5.', 'notice-archeomed' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="na-ara-date"><?php esc_html_e( 'Date de l’audit', 'notice-archeomed' ); ?></label></th>
					<td><input type="date" id="na-ara-date" name="na_ara_date"
						value="<?php echo esc_attr( isset( $garde['ara']['date'] ) ? (string) $garde['ara']['date'] : '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="na-ara-lien"><?php esc_html_e( 'Lien vers le rapport', 'notice-archeomed' ); ?></label></th>
					<td><input type="url" id="na-ara-lien" name="na_ara_lien" class="regular-text"
						value="<?php echo esc_attr( isset( $garde['ara']['lien'] ) ? (string) $garde['ara']['lien'] : '' ); ?>"></td>
				</tr>
			</table>
			<?php submit_button( __( 'Enregistrer l’audit', 'notice-archeomed' ) ); ?>
		</form>
		<?php
	}

	/** Le dernier contrôle gardé : score, échecs, conformes, à vérifier. */
	private static function afficher_le_controle( $controle ) {
		echo '<div id="na-a11y-resultat">';
		if ( empty( $controle ) || ! isset( $controle['score'] ) ) {
			echo '<p>' . esc_html__( 'Aucun contrôle n’a encore été fait.', 'notice-archeomed' ) . '</p></div>';
			return;
		}
		$echecs     = isset( $controle['echecs'] ) ? (array) $controle['echecs'] : array();
		$conformes  = isset( $controle['conformes'] ) ? (array) $controle['conformes'] : array();
		$a_verifier = isset( $controle['a_verifier'] ) ? (array) $controle['a_verifier'] : array();
		echo '<h3>' . esc_html( sprintf( "Dernier contrôle\u{00A0}: %s", mysql2date( 'j F Y à G\hi', (string) $controle['date'] ) ) ) . '</h3>';
		echo '<p class="na-a11y-score"><strong>' . esc_html( sprintf( "Score indicatif\u{00A0}: %d\u{00A0}%%", (int) $controle['score'] ) ) . '</strong> '
			. esc_html( sprintf( '(%1$d règles conformes, %2$d en échec%3$s)', count( $conformes ), count( $echecs ),
				! empty( $controle['moteur'] ) ? ', axe-core ' . $controle['moteur'] : '' ) ) . '</p>';
		echo '<h4>' . esc_html( sprintf( "En échec (%d)", count( $echecs ) ) ) . '</h4>';
		if ( empty( $echecs ) ) {
			echo '<p>' . esc_html__( 'Aucune règle en échec.', 'notice-archeomed' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th scope="col">Règle</th><th scope="col">Gravité</th>'
				. '<th scope="col">Éléments</th><th scope="col">Ce qui ne va pas</th><th scope="col">Vient de</th></tr></thead><tbody>';
			foreach ( $echecs as $regle ) {
				echo '<tr><td><code>' . esc_html( $regle['id'] ) . '</code></td><td>' . esc_html( isset( $regle['gravite'] ) ? $regle['gravite'] : '' )
					. '</td><td>' . esc_html( isset( $regle['n'] ) ? (string) $regle['n'] : '' ) . '</td><td>' . esc_html( isset( $regle['explication'] ) ? $regle['explication'] : '' )
					. '</td><td>' . esc_html( isset( $regle['origine'] ) ? $regle['origine'] : '' ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		foreach ( array( 'À vérifier à la main' => $a_verifier, 'Conformes' => $conformes ) as $titre => $liste ) {
			echo '<details class="na-a11y-liste"><summary>' . esc_html( sprintf( '%1$s (%2$d)', $titre, count( $liste ) ) ) . '</summary><ul>';
			foreach ( $liste as $regle ) {
				echo '<li><code>' . esc_html( $regle['id'] ) . '</code> ' . esc_html( isset( $regle['explication'] ) ? $regle['explication'] : '' )
					. ( ! empty( $regle['n'] ) ? esc_html( sprintf( ' (%d)', (int) $regle['n'] ) ) : '' ) . '</li>';
			}
			echo '</ul></details>';
		}
		echo '</div>';
	}

	/** Le style de l'onglet : le cadre de la page contrôlée, le score. */
	private static function style() {
		return '#na-a11y-page{width:100%;max-width:1200px;height:640px;border:1px solid #8c8f94;background:#fff}'
			. '.na-a11y-score{font-size:1.25em}'
			. '.na-a11y-encadre{max-width:46em;padding:4px 16px;border-left:4px solid #2271b1;background:#fff}'
			. '.na-a11y-liste{margin:8px 0}.na-a11y-liste summary{cursor:pointer;font-weight:600}'
			. '#na-a11y-etat{margin-left:8px}';
	}

	/**
	 * Le script de l'onglet. Il charge la page dans le cadre, y pose le
	 * moteur, le lance, classe ce qu'il trouve et l'envoie au serveur ; la
	 * page se recharge alors sur le résultat gardé.
	 */
	private static function script() {
		return <<<'JS'
(function () {
	var bouton = document.getElementById('na-a11y-lancer');
	if (!bouton || !window.naAccessibilite) { return; }
	var R = window.naAccessibilite;
	var etat = document.getElementById('na-a11y-etat');
	var cadre = document.getElementById('na-a11y-cadre');
	var page = document.getElementById('na-a11y-page');
	// Des explications courtes, en français, pour les règles qu'on rencontre ;
	// les autres gardent l'aide du moteur, en anglais.
	var FR = {
		'area-alt': 'Zone de carte cliquable sans texte alternatif.',
		'aria-allowed-attr': 'Attribut ARIA non permis sur cet élément.',
		'aria-command-name': 'Commande ARIA sans nom accessible.',
		'aria-hidden-body': 'Le corps de la page est masqué aux technologies d’assistance.',
		'aria-hidden-focus': 'Élément masqué par ARIA mais atteignable au clavier.',
		'aria-input-field-name': 'Champ ARIA sans nom accessible.',
		'aria-meter-name': 'Jauge ARIA sans nom accessible.',
		'aria-progressbar-name': 'Barre de progression sans nom accessible.',
		'aria-required-attr': 'Attribut ARIA obligatoire manquant.',
		'aria-required-children': 'Rôle ARIA sans les enfants qu’il exige.',
		'aria-required-parent': 'Rôle ARIA hors du parent qu’il exige.',
		'aria-roles': 'Rôle ARIA invalide.',
		'aria-toggle-field-name': 'Interrupteur ARIA sans nom accessible.',
		'aria-tooltip-name': 'Infobulle ARIA sans nom accessible.',
		'aria-valid-attr': 'Attribut ARIA inconnu.',
		'aria-valid-attr-value': 'Valeur d’attribut ARIA invalide.',
		'autocomplete-valid': 'Valeur d’autocomplete invalide.',
		'blink': 'Texte clignotant.',
		'button-name': 'Bouton sans nom accessible.',
		'bypass': 'Pas de moyen d’éviter les blocs répétés (lien d’évitement).',
		'color-contrast': 'Contraste insuffisant entre le texte et son fond.',
		'definition-list': 'Liste de définitions mal construite.',
		'dlitem': 'Terme ou définition hors d’une liste de définitions.',
		'document-title': 'La page n’a pas de titre.',
		'duplicate-id-aria': 'Identifiant en double, référencé par ARIA.',
		'form-field-multiple-labels': 'Champ de formulaire à plusieurs étiquettes.',
		'frame-focusable-content': 'Cadre au contenu atteignable mais exclu du clavier.',
		'frame-title': 'Cadre sans titre.',
		'html-has-lang': 'La page ne déclare pas sa langue.',
		'html-lang-valid': 'La langue déclarée de la page n’est pas valide.',
		'html-xml-lang-mismatch': 'Les langues lang et xml:lang ne concordent pas.',
		'image-alt': 'Image sans texte alternatif.',
		'input-button-name': 'Bouton de formulaire sans texte.',
		'input-image-alt': 'Bouton image sans texte alternatif.',
		'label': 'Champ de formulaire sans étiquette.',
		'link-in-text-block': 'Lien qu’on ne distingue du texte que par la couleur.',
		'link-name': 'Lien sans texte accessible.',
		'list': 'Liste mal construite.',
		'listitem': 'Élément de liste hors d’une liste.',
		'marquee': 'Texte défilant.',
		'meta-refresh': 'Rechargement automatique de la page.',
		'meta-viewport': 'Le zoom est empêché.',
		'nested-interactive': 'Élément interactif dans un autre.',
		'no-autoplay-audio': 'Son joué automatiquement.',
		'object-alt': 'Objet intégré sans texte alternatif.',
		'role-img-alt': 'Élément de rôle image sans texte alternatif.',
		'scrollable-region-focusable': 'Zone défilante inaccessible au clavier.',
		'select-name': 'Liste déroulante sans étiquette.',
		'server-side-image-map': 'Carte image traitée par le serveur.',
		'svg-img-alt': 'Image SVG sans texte alternatif.',
		'td-headers-attr': 'Cellule qui renvoie à des en-têtes absents.',
		'th-has-data-cells': 'En-tête de tableau sans cellules.',
		'valid-lang': 'Langue de passage invalide.',
		'video-caption': 'Vidéo sans sous-titres.'
	};
	var GRAVITE = { critical: 'critique', serious: 'grave', moderate: 'modérée', minor: 'mineure' };
	function dire(t) { etat.textContent = t; }
	function explication(r) { return FR[r.id] || r.help || ''; }
	function chargerLaPage(adresse) {
		return new Promise(function (ok, ko) {
			var fini = false;
			page.onload = function () { if (!fini) { fini = true; setTimeout(ok, 1500); } };
			setTimeout(function () { if (!fini) { fini = true; ko(new Error('La page du formulaire ne s’est pas chargée.')); } }, 30000);
			page.src = adresse + (adresse.indexOf('?') === -1 ? '?' : '&') + 'na_controle=' + Date.now();
		});
	}
	function poserLeMoteur(doc) {
		return new Promise(function (ok, ko) {
			if (page.contentWindow.axe) { ok(); return; }
			var s = doc.createElement('script');
			s.integrity = R.empreinte;
			s.crossOrigin = 'anonymous';
			s.src = R.axe;
			s.onload = function () { ok(); };
			s.onerror = function () { ko(new Error('Le moteur axe-core ne s’est pas chargé (réseau ?).')); };
			doc.head.appendChild(s);
		});
	}
	// D'où vient chaque élément : du formulaire de l'extension, ou du thème
	// qui l'entoure.
	function origine(doc, regle) {
		var ext = 0, theme = 0;
		regle.nodes.forEach(function (n) {
			var el = null;
			try { el = doc.querySelector(n.target[n.target.length - 1]); } catch (e) { el = null; }
			if (el && el.closest && el.closest('.na-form, #na-form')) { ext++; } else { theme++; }
		});
		return ext && theme ? 'extension et thème' : (ext ? 'extension' : 'thème');
	}
	function envoyer(resultat) {
		var corps = new FormData();
		corps.append('action', R.action);
		corps.append('_ajax_nonce', R.nonce);
		corps.append('resultat', JSON.stringify(resultat));
		return fetch(R.ajax, { method: 'POST', credentials: 'same-origin', body: corps }).then(function (r) { return r.json(); });
	}
	bouton.addEventListener('click', function () {
		var adresse = bouton.getAttribute('data-page');
		bouton.disabled = true;
		cadre.hidden = false;
		dire('Chargement de la page du formulaire…');
		chargerLaPage(adresse).then(function () {
			var doc = page.contentDocument;
			if (!doc) { throw new Error('La page n’est pas lisible depuis cet onglet (autre origine ?).'); }
			dire('Chargement du moteur axe-core…');
			return poserLeMoteur(doc).then(function () {
				dire('Contrôle en cours…');
				return page.contentWindow.axe.run(doc, { runOnly: { type: 'tag', values: R.normes } });
			}).then(function (r) {
				var resultat = {
					page: adresse,
					moteur: r.testEngine && r.testEngine.version ? r.testEngine.version : '',
					conformes: r.passes.map(function (x) { return { id: x.id, explication: explication(x) }; }),
					echecs: r.violations.map(function (x) {
						return { id: x.id, gravite: GRAVITE[x.impact] || '', n: x.nodes.length, explication: explication(x), origine: origine(doc, x) };
					}),
					a_verifier: r.incomplete.map(function (x) { return { id: x.id, n: x.nodes.length, explication: explication(x) }; })
				};
				dire('Enregistrement du résultat…');
				return envoyer(resultat);
			});
		}).then(function (rep) {
			if (!rep || !rep.success) { throw new Error('Le résultat n’a pas pu être enregistré.'); }
			dire('Contrôle enregistré : score indicatif ' + rep.data.score + ' %. La page se recharge.');
			window.location.reload();
		}).catch(function (e) {
			dire('Échec du contrôle : ' + e.message);
			bouton.disabled = false;
		});
	});
}());
JS;
	}
}
