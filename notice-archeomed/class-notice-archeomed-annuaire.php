<?php
/**
 * L'onglet « Annuaire » des réglages : les personnes nommées dans les
 * notices, avec leur adresse et leur institution, classées par nom.
 *
 * Chaque campagne s'ouvre par un envoi aux chercheurs, et la liste de
 * diffusion se tenait à la main. L'annuaire réunit les responsables
 * d'opération — et les co-responsables et co-auteurs des notices
 * anciennes — une ligne par adresse, quel que soit le nombre de leurs
 * notices, et s'emporte en deux formes : un fichier que Sympa, le
 * gestionnaire des listes du CNRS, accepte tel quel pour ajouter plusieurs
 * abonnés, et un tableur.
 *
 * Qui dépose pour le responsable ne laisse que son adresse : il n'est pas
 * dans l'annuaire, qui ne nomme que des auteurs.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Annuaire {

	/** L'action qui rend le fichier pour Sympa. */
	const ACTION_SYMPA = 'na_annuaire_sympa';

	/** L'action qui rend l'annuaire en tableur. */
	const ACTION_TABLEUR = 'na_annuaire_tableur';

	/** Les personnes d'une notice : préfixe des champs ⇒ rôle. */
	const ROLES = array(
		'resp'     => 'responsable',
		'coresp'   => 'co-responsable',
		'coauteur' => 'co-auteur',
	);

	public function __construct() {
		add_action( 'admin_post_' . self::ACTION_SYMPA, array( $this, 'fichier_sympa' ) );
		add_action( 'admin_post_' . self::ACTION_TABLEUR, array( $this, 'tableur' ) );
	}

	/**
	 * Les personnes des notices reçues, une par adresse, classées par nom
	 * puis par prénom, sans tenir compte des accents ni de la casse.
	 *
	 * Le nom, le prénom et l'institution sont ceux de la notice la plus
	 * récente qui les donne : une personne qui a changé de laboratoire
	 * paraît sous le nouveau. Les notices qu'une correction remplace ne sont
	 * pas comptées. La saisie se lit par paquets de deux cents.
	 */
	public static function recenser() {
		global $wpdb;
		$remplacees = array_map( 'strtoupper', array_map( 'trim', (array) $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
			WHERE m.meta_key = '_na_remplace' AND m.meta_value <> '' AND p.post_type = %s AND p.post_status = 'private'",
			Notice_Archeomed_File::CPT ) ) ) );
		$personnes = array();
		$depuis    = 0;
		do {
			// Par identifiant croissant : la dernière notice lue est la plus
			// récente, et c'est elle qui nomme.
			$lignes = $wpdb->get_results( $wpdb->prepare(
				"SELECT p.ID, p.post_title, m.meta_value, r.meta_value AS reference FROM {$wpdb->posts} p
				JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_na_donnees'
				LEFT JOIN {$wpdb->postmeta} r ON r.post_id = p.ID AND r.meta_key = '_na_reference'
				WHERE p.post_type = %s AND p.post_status = 'private' AND p.ID > %d ORDER BY p.ID ASC LIMIT 200",
				Notice_Archeomed_File::CPT, $depuis ) );
			foreach ( (array) $lignes as $ligne ) {
				$depuis = (int) $ligne->ID;
				$d      = maybe_unserialize( $ligne->meta_value );
				if ( ! is_array( $d ) ) {
					continue;
				}
				$reference = trim( (string) $ligne->reference );
				if ( '' !== $reference && in_array( strtoupper( $reference ), $remplacees, true ) ) {
					continue;
				}
				foreach ( self::ROLES as $prefixe => $role ) {
					$courriel = self::champ( $d, $prefixe . '_email' );
					if ( ! is_email( $courriel ) ) {
						continue;
					}
					$cle = strtolower( $courriel );
					if ( ! isset( $personnes[ $cle ] ) ) {
						$personnes[ $cle ] = array(
							'nom'         => '',
							'prenom'      => '',
							'courriel'    => $courriel,
							'institution' => '',
							'roles'       => array(),
							'notices'     => array(),
						);
					}
					foreach ( array( 'nom', 'prenom' ) as $partie ) {
						$valeur = self::champ( $d, $prefixe . '_' . $partie );
						if ( '' !== $valeur ) {
							$personnes[ $cle ][ $partie ] = $valeur;
						}
					}
					$institution = self::champ( $d, $prefixe . '_inst' );
					if ( '' !== $institution ) {
						$personnes[ $cle ]['institution'] = $institution;
					}
					$personnes[ $cle ]['courriel']         = $courriel;
					$personnes[ $cle ]['roles'][ $role ]   = true;
					$personnes[ $cle ]['notices'][ (int) $ligne->ID ] = '' !== $reference ? $reference
						: ( '' !== trim( (string) $ligne->post_title ) ? trim( (string) $ligne->post_title ) : '#' . (int) $ligne->ID );
				}
			}
		} while ( count( (array) $lignes ) === 200 );

		$personnes = array_values( $personnes );
		usort( $personnes, function ( $a, $b ) {
			$ordre = strcmp( self::cle_de_tri( $a['nom'] ), self::cle_de_tri( $b['nom'] ) );
			if ( 0 === $ordre ) {
				$ordre = strcmp( self::cle_de_tri( $a['prenom'] ), self::cle_de_tri( $b['prenom'] ) );
			}
			return 0 !== $ordre ? $ordre : strcmp( $a['courriel'], $b['courriel'] );
		} );
		return $personnes;
	}

	/** Un champ texte de la saisie, sans les blancs qui l'entourent. */
	private static function champ( $d, $cle ) {
		return isset( $d[ $cle ] ) && is_scalar( $d[ $cle ] ) ? trim( preg_replace( '/\s+/u', ' ', (string) $d[ $cle ] ) ) : '';
	}

	/**
	 * La forme sous laquelle un nom se classe : sans accents ni capitales,
	 * et sans apostrophe ni tiret, pour que « d’Ancien » et « Dancien »,
	 * « Le Goff » et « Le-Goff » se suivent. Un nom vide va à la fin.
	 */
	public static function cle_de_tri( $nom ) {
		$nom = strtolower( remove_accents( (string) $nom ) );
		$nom = preg_replace( '/[^a-z0-9]+/', '', $nom );
		return '' === $nom ? "\u{FFFF}" : $nom;
	}

	/**
	 * Le fichier pour Sympa : une ligne par personne, l'adresse puis le
	 * prénom et le nom. C'est la forme de l'ajout de plusieurs abonnés.
	 */
	public static function lignes_sympa( $personnes ) {
		$lignes = array();
		foreach ( $personnes as $p ) {
			// Une fin de ligne dans un nom couperait l'entrée en deux.
			$nom      = trim( preg_replace( '/[\r\n\t]+/', ' ', $p['prenom'] . ' ' . $p['nom'] ) );
			$lignes[] = trim( $p['courriel'] . ' ' . $nom );
		}
		return $lignes;
	}

	/**
	 * Les lignes du tableur, en-tête compris. Une cellule qui commence par
	 * « = », « + », « - » ou « @ » serait lue comme une formule : elle prend
	 * une apostrophe.
	 */
	public static function lignes_du_tableur( $personnes ) {
		$lignes = array( array( 'Nom', 'Prénom', 'Courriel', 'Institution', 'Rôle', 'Notices' ) );
		foreach ( $personnes as $p ) {
			$ligne = array( $p['nom'], $p['prenom'], $p['courriel'], $p['institution'],
				implode( ', ', array_keys( $p['roles'] ) ), implode( ', ', $p['notices'] ) );
			$lignes[] = array_map( function ( $cellule ) {
				return preg_match( '/^[=+\-@]/', (string) $cellule ) ? "'" . $cellule : (string) $cellule;
			}, $ligne );
		}
		return $lignes;
	}

	/** L'onglet. */
	public static function onglet() {
		$personnes = self::recenser();
		echo '<h2>' . esc_html__( 'Annuaire des auteurs', 'notice-archeomed' ) . '</h2>';
		echo '<p class="description" style="max-width:46em">' . esc_html(
			"Les personnes nommées dans les notices reçues, une ligne par adresse, classées par nom. Le nom et l’institution sont ceux de la notice la plus récente. Qui dépose pour le responsable ne laisse que son adresse\u{00A0}: il n’est pas ici." ) . '</p>';
		if ( empty( $personnes ) ) {
			echo '<p>' . esc_html__( 'Aucune notice reçue pour l’instant.', 'notice-archeomed' ) . '</p>';
			return;
		}
		echo '<p>' . esc_html( sprintf( _n( '%s personne.', '%s personnes.', count( $personnes ), 'notice-archeomed' ),
			number_format_i18n( count( $personnes ) ) ) ) . ' '
			. '<a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_SYMPA ), self::ACTION_SYMPA ) ) . '">'
			. esc_html__( 'Télécharger pour la liste de diffusion (Sympa)', 'notice-archeomed' ) . '</a> '
			. '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_TABLEUR ), self::ACTION_TABLEUR ) ) . '">'
			. esc_html__( 'Télécharger en tableur (CSV)', 'notice-archeomed' ) . '</a></p>';
		echo '<p class="description" style="max-width:46em">' . esc_html(
			"Le fichier pour la liste porte une ligne par personne\u{00A0}: l’adresse, puis le prénom et le nom. Sur listes.services.cnrs.fr, dans l’administration de la liste, l’ajout de plusieurs abonnés l’accepte tel quel\u{00A0}: on y colle son contenu. Sympa écarte les adresses déjà abonnées." ) . '</p>';
		echo '<p class="description" style="max-width:46em">' . esc_html(
			"Les auteurs ont donné leur adresse pour l’instruction de leur notice. Les inscrire à une liste de diffusion est un autre usage\u{00A0}: mieux vaut le leur dire dans le premier envoi, avec le moyen de se désabonner." ) . '</p>';

		echo '<table class="widefat striped" style="max-width:80em"><caption class="screen-reader-text">'
			. esc_html__( 'Les auteurs des notices, classés par nom', 'notice-archeomed' ) . '</caption><thead><tr>';
		foreach ( array( 'Nom', 'Prénom', 'Courriel', 'Institution', 'Notices' ) as $titre ) {
			echo '<th scope="col">' . esc_html( $titre ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $personnes as $p ) {
			$notices = array();
			foreach ( $p['notices'] as $id => $libelle ) {
				$lien      = get_edit_post_link( $id, '' );
				$notices[] = $lien ? '<a href="' . esc_url( $lien ) . '">' . esc_html( $libelle ) . '</a>' : esc_html( $libelle );
			}
			$roles = array_keys( $p['roles'] );
			echo '<tr><th scope="row">' . esc_html( '' !== $p['nom'] ? $p['nom'] : '—' ) . '</th>'
				. '<td>' . esc_html( $p['prenom'] ) . '</td>'
				. '<td><a href="mailto:' . esc_attr( $p['courriel'] ) . '">' . esc_html( $p['courriel'] ) . '</a></td>'
				. '<td>' . esc_html( $p['institution'] ) . '</td>'
				. '<td>' . implode( ', ', $notices )
				. ( array( 'responsable' ) !== $roles ? ' <span class="description">(' . esc_html( implode( ', ', $roles ) ) . ')</span>' : '' )
				. '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/** Rend le fichier pour Sympa, en UTF-8 et sans marque d'ordre. */
	public function fichier_sympa() {
		self::verifier( self::ACTION_SYMPA );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="annuaire-liste-' . gmdate( 'Y-m-d' ) . '.txt"' );
		echo implode( "\n", self::lignes_sympa( self::recenser() ) ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- texte brut.
		exit;
	}

	/**
	 * Rend l'annuaire en tableur, séparé par des points-virgules et précédé
	 * de la marque UTF-8, pour qu'un tableur réglé en français l'ouvre en
	 * colonnes et avec ses accents.
	 */
	public function tableur() {
		self::verifier( self::ACTION_TABLEUR );
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="annuaire-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$sortie = fopen( 'php://output', 'w' );
		fwrite( $sortie, "\xEF\xBB\xBF" );
		foreach ( self::lignes_du_tableur( self::recenser() ) as $ligne ) {
			fputcsv( $sortie, $ligne, ';' );
		}
		fclose( $sortie );
		exit;
	}

	/** Les adresses ne sortent que pour qui règle l'extension. */
	private static function verifier( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'notice-archeomed' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
		nocache_headers();
	}
}
