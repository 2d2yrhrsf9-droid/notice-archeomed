<?php
/**
 * L'onglet « Candidats à Pactols » des réglages : les termes que les auteurs
 * ont gardés hors de la liste du thésaurus.
 *
 * Un terme absent de Pactols n'est pas retenu dans la notice. Il n'est pas
 * perdu pour autant : réunis notice après notice, ces termes disent ce qui
 * manque au thésaurus pour décrire l'archéologie médiévale, et la revue peut
 * les proposer à Frantiq. La liste se tient d'elle-même, à chaque dépôt ;
 * la rédaction y note pour chacun où il en est — à proposer, proposé,
 * écarté — et ce qu'elle en sait, et l'emporte en tableur.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Candidats {

	/** L'option qui garde, par terme, son statut et la note de la rédaction. */
	const OPTION = 'notice_archeomed_candidats';

	/** L'action du formulaire qui enregistre statuts et notes. */
	const ACTION = 'na_candidats';

	/** L'action qui rend la liste en tableur. */
	const ACTION_TABLEUR = 'na_candidats_tableur';

	/** Les catégories où l'auteur choisit dans Pactols, et leur nom. */
	const CATEGORIES = array(
		'pactols_subjects_items' => 'Mots-clés',
		'pactols_periods_items'  => 'Période historique',
		'pactols_places_items'   => 'Autres lieux',
	);

	/** Où en est un terme, du point de vue de la rédaction. */
	const STATUTS = array(
		'a_proposer' => 'À proposer',
		'propose'    => 'Proposé à Frantiq',
		'ecarte'     => 'Écarté',
	);

	public function __construct() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'enregistrer' ) );
		add_action( 'admin_post_' . self::ACTION_TABLEUR, array( $this, 'tableur' ) );
	}

	/**
	 * La clé d'un terme : sa catégorie et sa forme pliée — casse, accents et
	 * blancs —, pour que « Chemin creux » et « chemin  creux » ne fassent
	 * qu'un candidat.
	 */
	public static function cle( $categorie, $terme ) {
		$plie = function_exists( 'mb_strtolower' ) ? mb_strtolower( remove_accents( (string) $terme ), 'UTF-8' ) : strtolower( remove_accents( (string) $terme ) );
		return md5( $categorie . '|' . trim( (string) preg_replace( '/\s+/u', ' ', $plie ) ) );
	}

	/** Les statuts et notes enregistrés, par clé. */
	public static function suivi() {
		$suivi = get_option( self::OPTION, array() );
		return is_array( $suivi ) ? $suivi : array();
	}

	/**
	 * Les candidats, relevés dans toutes les notices reçues : les termes
	 * marqués hors Pactols, regroupés par catégorie et forme pliée, avec les
	 * notices qui les portent. Les notices qu'une correction remplace n'y
	 * sont pas. La saisie se lit par paquets de deux cents.
	 *
	 * Les termes sans identifiant d'une notice antérieure à la marque ne
	 * sont pas relevés : rien ne dit s'ils étaient hors de la liste.
	 */
	public static function recenser() {
		global $wpdb;
		$remplacees = array_map( 'strtoupper', array_map( 'trim', (array) $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
			WHERE m.meta_key = '_na_remplace' AND m.meta_value <> '' AND p.post_type = %s AND p.post_status = 'private'",
			Notice_Archeomed_File::CPT ) ) ) );
		$suivi    = self::suivi();
		$candidats = array();
		$depuis   = 0;
		do {
			$lignes = $wpdb->get_results( $wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_date, m.meta_value FROM {$wpdb->posts} p
				JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_na_donnees'
				WHERE p.post_type = %s AND p.post_status = 'private' AND p.ID > %d ORDER BY p.ID ASC LIMIT 200",
				Notice_Archeomed_File::CPT, $depuis ) );
			foreach ( (array) $lignes as $ligne ) {
				$depuis = (int) $ligne->ID;
				$d      = maybe_unserialize( $ligne->meta_value );
				if ( ! is_array( $d ) ) {
					continue;
				}
				$reference = strtoupper( trim( (string) get_post_meta( (int) $ligne->ID, '_na_reference', true ) ) );
				if ( '' !== $reference && in_array( $reference, $remplacees, true ) ) {
					continue;
				}
				foreach ( self::CATEGORIES as $champ => $categorie ) {
					foreach ( isset( $d[ $champ ] ) && is_array( $d[ $champ ] ) ? $d[ $champ ] : array() as $item ) {
						if ( ! is_array( $item ) || empty( $item['libre'] ) ) {
							continue;
						}
						$terme = trim( (string) ( isset( $item['label'] ) ? $item['label'] : '' ) );
						if ( '' === $terme ) {
							continue;
						}
						$cle = self::cle( $champ, $terme );
						if ( ! isset( $candidats[ $cle ] ) ) {
							$candidats[ $cle ] = array(
								'cle'       => $cle,
								'terme'     => $terme,
								'formes'    => array(),
								'categorie' => $categorie,
								'notices'   => array(),
								'premier'   => (string) $ligne->post_date,
								'dernier'   => (string) $ligne->post_date,
								'statut'    => isset( $suivi[ $cle ]['statut'] ) ? (string) $suivi[ $cle ]['statut'] : 'a_proposer',
								'note'      => isset( $suivi[ $cle ]['note'] ) ? (string) $suivi[ $cle ]['note'] : '',
							);
						}
						$candidats[ $cle ]['formes'][ $terme ] = true;
						$candidats[ $cle ]['notices'][ (int) $ligne->ID ] = array(
							'id'        => (int) $ligne->ID,
							'titre'     => (string) $ligne->post_title,
							'reference' => $reference,
						);
						$candidats[ $cle ]['premier'] = min( $candidats[ $cle ]['premier'], (string) $ligne->post_date );
						$candidats[ $cle ]['dernier'] = max( $candidats[ $cle ]['dernier'], (string) $ligne->post_date );
					}
				}
			}
		} while ( count( (array) $lignes ) === 200 );
		// À proposer d'abord, puis les plus demandés, puis l'ordre alphabétique.
		$rang = array_flip( array_keys( self::STATUTS ) );
		usort( $candidats, function ( $a, $b ) use ( $rang ) {
			$ra = isset( $rang[ $a['statut'] ] ) ? $rang[ $a['statut'] ] : 0;
			$rb = isset( $rang[ $b['statut'] ] ) ? $rang[ $b['statut'] ] : 0;
			if ( $ra !== $rb ) {
				return $ra - $rb;
			}
			if ( count( $a['notices'] ) !== count( $b['notices'] ) ) {
				return count( $b['notices'] ) - count( $a['notices'] );
			}
			return strcmp( remove_accents( $a['terme'] ), remove_accents( $b['terme'] ) );
		} );
		return $candidats;
	}

	/** L'onglet : la liste, son formulaire, et le lien du tableur. */
	public static function onglet() {
		$candidats = self::recenser();
		echo '<h2>' . esc_html__( 'Termes candidats à Pactols', 'notice-archeomed' ) . '</h2>';
		echo '<p class="description" style="max-width:46em">' . esc_html(
			"Les termes que les auteurs ont gardés hors de la liste de Pactols. Ils ne sont pas retenus dans la notice\u{00A0}; réunis ici, dépôt après dépôt, ils disent ce qui manque au thésaurus, et la revue peut les proposer à Frantiq. Les notices remplacées par une correction ne sont pas comptées." ) . '</p>';
		if ( isset( $_GET['na_candidats'] ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Statuts et notes enregistrés.', 'notice-archeomed' ) . '</p></div>';
		}
		if ( empty( $candidats ) ) {
			echo '<p>' . esc_html__( 'Aucun terme hors Pactols pour l’instant.', 'notice-archeomed' ) . '</p>';
			return;
		}
		$compte = array_count_values( array_column( $candidats, 'statut' ) );
		echo '<p>' . esc_html( sprintf( "%1\$d\u{00A0}terme%2\$s\u{00A0}: %3\$d à proposer, %4\$d proposé%5\$s, %6\$d écarté%7\$s.",
			count( $candidats ), count( $candidats ) > 1 ? 's' : '',
			isset( $compte['a_proposer'] ) ? $compte['a_proposer'] : 0,
			isset( $compte['propose'] ) ? $compte['propose'] : 0, ! empty( $compte['propose'] ) && $compte['propose'] > 1 ? 's' : '',
			isset( $compte['ecarte'] ) ? $compte['ecarte'] : 0, ! empty( $compte['ecarte'] ) && $compte['ecarte'] > 1 ? 's' : '' ) )
			. ' <a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_TABLEUR ), self::ACTION_TABLEUR ) ) . '">'
			. esc_html__( 'Télécharger en tableur (CSV)', 'notice-archeomed' ) . '</a></p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		wp_nonce_field( self::ACTION );
		echo '<table class="widefat striped na-candidats"><caption class="screen-reader-text">'
			. esc_html__( 'Termes hors Pactols, avec leur statut', 'notice-archeomed' ) . '</caption><thead><tr>'
			. '<th scope="col">Terme</th><th scope="col">Catégorie</th><th scope="col">Notices</th>'
			. '<th scope="col">Dernier dépôt</th><th scope="col">Statut</th><th scope="col">Note</th></tr></thead><tbody>';
		foreach ( $candidats as $c ) {
			$id_statut = 'na-statut-' . $c['cle'];
			$id_note   = 'na-note-' . $c['cle'];
			$autres    = array_diff( array_keys( $c['formes'] ), array( $c['terme'] ) );
			$liens     = array();
			foreach ( array_slice( $c['notices'], 0, 5 ) as $n ) {
				$liens[] = '<a href="' . esc_url( (string) get_edit_post_link( $n['id'], 'url' ) ) . '">'
					. esc_html( '' !== trim( $n['titre'] ) ? $n['titre'] : '(sans titre)' ) . '</a>';
			}
			if ( count( $c['notices'] ) > 5 ) {
				$liens[] = esc_html( sprintf( "et %d\u{00A0}autres", count( $c['notices'] ) - 5 ) );
			}
			echo '<tr><th scope="row"><label for="' . esc_attr( $id_note ) . '">' . esc_html( $c['terme'] ) . '</label>'
				. ( empty( $autres ) ? '' : '<br><span class="description">' . esc_html( "aussi\u{00A0}: " . implode( ', ', $autres ) ) . '</span>' ) . '</th>'
				. '<td>' . esc_html( $c['categorie'] ) . '</td>'
				. '<td>' . (int) count( $c['notices'] ) . '<br>' . implode( '<br>', $liens ) . '</td>'
				. '<td>' . esc_html( mysql2date( 'j F Y', $c['dernier'] ) ) . '</td>'
				. '<td><label class="screen-reader-text" for="' . esc_attr( $id_statut ) . '">' . esc_html( 'Statut de « ' . $c['terme'] . ' »' ) . '</label>'
				. '<select id="' . esc_attr( $id_statut ) . '" name="statut[' . esc_attr( $c['cle'] ) . ']">';
			foreach ( self::STATUTS as $valeur => $libelle ) {
				echo '<option value="' . esc_attr( $valeur ) . '"' . selected( $c['statut'], $valeur, false ) . '>' . esc_html( $libelle ) . '</option>';
			}
			echo '</select><input type="hidden" name="terme[' . esc_attr( $c['cle'] ) . ']" value="' . esc_attr( $c['terme'] ) . '"></td>'
				. '<td><input type="text" class="regular-text" id="' . esc_attr( $id_note ) . '" name="note[' . esc_attr( $c['cle'] ) . ']"'
				. ' value="' . esc_attr( $c['note'] ) . '" maxlength="300" placeholder="' . esc_attr( 'équivalent, date d’envoi…' ) . '"></td></tr>';
		}
		echo '</tbody></table>';
		submit_button( __( 'Enregistrer les statuts et les notes', 'notice-archeomed' ) );
		echo '</form>';
	}

	/** Enregistre statuts et notes : droit de régler, jeton, valeurs bornées. */
	public function enregistrer() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'notice-archeomed' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );
		$suivi   = self::suivi();
		$statuts = isset( $_POST['statut'] ) && is_array( $_POST['statut'] ) ? wp_unslash( $_POST['statut'] ) : array();
		$notes   = isset( $_POST['note'] ) && is_array( $_POST['note'] ) ? wp_unslash( $_POST['note'] ) : array();
		$termes  = isset( $_POST['terme'] ) && is_array( $_POST['terme'] ) ? wp_unslash( $_POST['terme'] ) : array();
		foreach ( $statuts as $cle => $statut ) {
			$cle = (string) $cle;
			if ( ! preg_match( '/^[0-9a-f]{32}$/', $cle ) ) {
				continue;
			}
			$statut = is_string( $statut ) && isset( self::STATUTS[ $statut ] ) ? $statut : 'a_proposer';
			$note   = isset( $notes[ $cle ] ) && is_string( $notes[ $cle ] ) ? sanitize_text_field( $notes[ $cle ] ) : '';
			$note   = function_exists( 'mb_substr' ) ? mb_substr( $note, 0, 300, 'UTF-8' ) : substr( $note, 0, 300 );
			// Un terme sans statut ni note n'a rien à garder : la liste le
			// retrouve d'elle-même dans les notices.
			if ( 'a_proposer' === $statut && '' === $note ) {
				unset( $suivi[ $cle ] );
				continue;
			}
			$suivi[ $cle ] = array(
				'statut' => $statut,
				'note'   => $note,
				'terme'  => isset( $termes[ $cle ] ) && is_string( $termes[ $cle ] ) ? sanitize_text_field( $termes[ $cle ] ) : '',
			);
		}
		update_option( self::OPTION, $suivi, false );
		wp_safe_redirect( add_query_arg( 'na_candidats', '1', Notice_Archeomed_Settings::url( 'candidats' ) ) );
		exit;
	}

	/**
	 * Une cellule de tableur sans formule : un terme d'auteur qui commence
	 * par « = », « + », « - » ou « @ » serait calculé à l'ouverture.
	 */
	public static function cellule( $texte ) {
		$texte = (string) $texte;
		return preg_match( '/^[=+\-@\t\r]/', $texte ) ? "'" . $texte : $texte;
	}

	/** Les lignes du tableur, en-tête compris. */
	public static function lignes_du_tableur( $candidats ) {
		$lignes = array( array( 'Terme', 'Autres graphies', 'Catégorie', 'Nombre de notices', 'Notices', 'Premier dépôt', 'Dernier dépôt', 'Statut', 'Note' ) );
		foreach ( (array) $candidats as $c ) {
			$notices = array();
			foreach ( $c['notices'] as $n ) {
				$notices[] = trim( $n['titre'] . ( '' !== $n['reference'] ? ' [' . $n['reference'] . ']' : '' ) );
			}
			$lignes[] = array_map( array( __CLASS__, 'cellule' ), array(
				$c['terme'],
				implode( ', ', array_diff( array_keys( $c['formes'] ), array( $c['terme'] ) ) ),
				$c['categorie'],
				(string) count( $c['notices'] ),
				implode( ' ; ', $notices ),
				mysql2date( 'Y-m-d', $c['premier'] ),
				mysql2date( 'Y-m-d', $c['dernier'] ),
				isset( self::STATUTS[ $c['statut'] ] ) ? self::STATUTS[ $c['statut'] ] : $c['statut'],
				$c['note'],
			) );
		}
		return $lignes;
	}

	/**
	 * La liste en tableur : point-virgule et marque d'ordre des octets, pour
	 * qu'un tableur réglé en français l'ouvre en colonnes et avec ses accents.
	 */
	public function tableur() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'notice-archeomed' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION_TABLEUR );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="candidats-pactols-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$sortie = fopen( 'php://output', 'w' );
		fwrite( $sortie, "\xEF\xBB\xBF" );
		foreach ( self::lignes_du_tableur( self::recenser() ) as $ligne ) {
			fputcsv( $sortie, $ligne, ';' );
		}
		fclose( $sortie );
		exit;
	}
}
