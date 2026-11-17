<?php
/**
 * L'onglet « Stockage » des réglages : la place que prennent les notices,
 * catégorie par catégorie.
 *
 * L'hébergeur plafonne le disque du site, et l'onglet Hébergement ne disait
 * qu'un total. Quand la place manque, il faut savoir ce qui la prend : les
 * originaux en attente de rangement sur sDrive, les aperçus, les fichiers de
 * passage qu'une exportation a laissés, ou les médias du reste du site.
 *
 * Un fichier se range dans une catégorie d'après la notice qui le désigne,
 * et non d'après son nom : les noms des fichiers du serveur sont tirés au
 * sort. Ce qu'aucune notice ne désigne plus se compte à part.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Stockage {

	/** Les catégories du dossier des notices, dans l'ordre du tableau. */
	const SERVEUR = array(
		'originaux'     => 'Illustrations originales',
		'apercus'       => 'Aperçus des figures',
		'conversions'   => 'JPEG tirés des TIFF',
		'autorisations' => 'Autorisations de reproduction',
		'word'          => 'Word des notices',
		'styles'        => 'Feuille de styles Métopes',
		'orphelins'     => 'Fichiers qu’aucune notice ne désigne',
	);

	/** Les catégories du répertoire de passage. */
	const PASSAGE = array(
		'word'       => 'Word des notices',
		'originaux'  => 'Illustrations des notices',
		'fascicules' => 'Word de relecture et fascicules',
		'ateliers'   => 'Ateliers d’export du numéro',
		'rapatries'  => 'Copies ramenées de sDrive',
		'recus'      => 'Fichiers reçus ou produits en passant',
	);

	/** Les métadonnées d'une notice qui désignent ses fichiers, et leur catégorie. */
	const METAS = array(
		'_na_illustrations' => 'originaux',
		'_na_autorisations' => 'autorisations',
		'_na_conversions'   => 'conversions',
		'_na_apercus'       => 'apercus',
		'_na_document'      => 'word',
		// Les notices d'avant l'archivage séparé n'ont que la liste des
		// fichiers produits : hors le document, ce sont leurs illustrations.
		'_na_fichiers'      => 'originaux',
	);

	/** Les métadonnées de la base qu'on nomme ; les autres se réunissent. */
	const BASE = array(
		'_na_donnees'  => 'Saisies des notices',
		'_na_versions' => 'Versions antérieures (corrections)',
		'_na_pactols'  => 'Termes Pactols retenus',
		'_na_sdrive'   => 'Table du rangement sur sDrive',
	);

	/** Le temps qu'on s'accorde pour parcourir les dossiers, en secondes. */
	const BUDGET = 8;

	/**
	 * Ce que pèsent les fichiers et les données des notices.
	 *
	 * Rend « serveur » et « passage » (catégorie ⇒ nombre et octets), le
	 * répertoire de passage et s'il est dans le site, « base » (libellé ⇒
	 * octets), « sdrive » (catégorie ⇒ nombre et octets), « notices »
	 * (identifiant ⇒ octets sur le serveur et sur sDrive), le nombre de
	 * notices, et « complet », faux si le parcours a été interrompu.
	 */
	public static function mesurer() {
		$mesure = array(
			'serveur'          => self::vides( self::SERVEUR ),
			'passage'          => self::vides( self::PASSAGE ),
			'passage_dossier'  => trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp',
			'passage_dans_site' => false,
			'base'             => array(),
			'sdrive'           => self::vides( self::SERVEUR ),
			'notices'          => array(),
			'nombre'           => 0,
			'complet'          => true,
		);
		$fichiers = self::fichiers_designes( $mesure );
		$fin      = microtime( true ) + self::BUDGET;

		// Le dossier des notices, dans les téléversements.
		$depot = wp_upload_dir();
		if ( empty( $depot['error'] ) ) {
			$dossier = realpath( trailingslashit( $depot['basedir'] ) . 'notice-archeomed' );
			if ( false !== $dossier ) {
				foreach ( self::parcourir( $dossier, $fin, $mesure['complet'] ) as $chemin => $poids ) {
					$nom = basename( $chemin );
					if ( '.htaccess' === $nom || 'index.php' === $nom ) {
						continue;
					}
					if ( isset( $fichiers[ $chemin ] ) ) {
						$categorie = $fichiers[ $chemin ]['categorie'];
						self::ajouter_a_la_notice( $mesure, $fichiers[ $chemin ]['id'], 'serveur', $poids );
					} elseif ( 0 === strpos( $nom, 'modele-metopes.' ) ) {
						$categorie = 'styles';
					} else {
						$categorie = 'orphelins';
					}
					self::ajouter( $mesure['serveur'], $categorie, $poids );
				}
			}
		}

		// Le répertoire de passage, hors des téléversements.
		$passage = realpath( $mesure['passage_dossier'] );
		if ( false !== $passage ) {
			$mesure['passage_dossier'] = $passage;
			$site                      = realpath( ABSPATH );
			$mesure['passage_dans_site'] = false !== $site && 0 === strpos( $passage . DIRECTORY_SEPARATOR, trailingslashit( $site ) );
			foreach ( self::parcourir( $passage, $fin, $mesure['complet'] ) as $chemin => $poids ) {
				$relatif = ltrim( substr( $chemin, strlen( $passage ) ), DIRECTORY_SEPARATOR );
				$nom     = basename( $chemin );
				if ( isset( $fichiers[ $chemin ] ) && isset( $mesure['passage'][ $fichiers[ $chemin ]['categorie'] ] ) ) {
					$categorie = $fichiers[ $chemin ]['categorie'];
					self::ajouter_a_la_notice( $mesure, $fichiers[ $chemin ]['id'], 'serveur', $poids );
				} elseif ( 0 === strpos( $relatif, 'atelier-' ) ) {
					$categorie = 'ateliers';
				} elseif ( 0 === strpos( $nom, 'notice-archeomed-sdrive-' ) ) {
					$categorie = 'rapatries';
				} elseif ( preg_match( '/\.(docx|rtf|zip)$/i', $nom ) ) {
					$categorie = 'fascicules';
				} else {
					$categorie = 'recus';
				}
				self::ajouter( $mesure['passage'], $categorie, $poids );
			}
		}

		$mesure['base'] = self::poids_de_la_base();
		uasort( $mesure['notices'], function ( $a, $b ) {
			return $b['serveur'] - $a['serveur'];
		} );
		return $mesure;
	}

	/**
	 * Les fichiers que les notices désignent : chemin ⇒ catégorie et notice.
	 * Remplit au passage ce qui est rangé sur sDrive et le nombre de notices.
	 *
	 * La lecture passe par la base, une ligne à la fois : charger toutes les
	 * métadonnées de toutes les notices tiendrait trop de mémoire sur une
	 * campagne de trois cents dépôts. Les saisies ne se lisent que pour les
	 * notices qui gardent leurs aperçus dedans.
	 */
	private static function fichiers_designes( &$mesure ) {
		global $wpdb;
		$type = Notice_Archeomed_File::CPT;
		$mesure['nombre'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", $type ) );

		$fichiers = array();
		$cles     = array_keys( self::METAS );
		$marques  = implode( ',', array_fill( 0, count( $cles ), '%s' ) );
		$lignes   = $wpdb->get_results( $wpdb->prepare(
			"SELECT pm.post_id, pm.meta_key, pm.meta_value FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type = %s AND pm.meta_key IN ($marques)",
			array_merge( array( $type ), $cles ) ) );
		// Une même pièce peut figurer sous deux clefs — le document parmi les
		// fichiers produits : la première catégorie de la liste l'emporte.
		$rang_de = array_flip( $cles );
		usort( $lignes, function ( $a, $b ) use ( $rang_de ) {
			return $rang_de[ $a->meta_key ] - $rang_de[ $b->meta_key ];
		} );
		foreach ( $lignes as $ligne ) {
			$valeur = maybe_unserialize( $ligne->meta_value );
			if ( '_na_apercus' === $ligne->meta_key && is_array( $valeur ) ) {
				$valeur = array_map( function ( $apercu ) {
					return is_array( $apercu ) && isset( $apercu['apercu'] ) ? $apercu['apercu'] : '';
				}, $valeur );
			}
			self::retenir( $fichiers, $valeur, self::METAS[ $ligne->meta_key ], (int) $ligne->post_id );
		}

		// Les aperçus des notices déposées de la 3.28 à la 3.35, dans leur saisie.
		$anciennes = $wpdb->get_results( $wpdb->prepare(
			"SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type = %s AND pm.meta_key = '_na_donnees' AND pm.meta_value LIKE %s",
			$type, '%' . $wpdb->esc_like( '"apercu"' ) . '%' ) );
		foreach ( $anciennes as $ligne ) {
			$d = maybe_unserialize( $ligne->meta_value );
			if ( ! is_array( $d ) || empty( $d['illustrations'] ) || ! is_array( $d['illustrations'] ) ) {
				continue;
			}
			foreach ( $d['illustrations'] as $item ) {
				if ( is_array( $item ) && ! empty( $item['figure']['apercu'] ) ) {
					self::retenir( $fichiers, $item['figure']['apercu'], 'apercus', (int) $ligne->post_id );
				}
			}
		}

		// Ce que la table du rangement dit avoir déposé sur sDrive.
		$tables = $wpdb->get_results( $wpdb->prepare(
			"SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type = %s AND pm.meta_key = '_na_sdrive'", $type ) );
		foreach ( $tables as $ligne ) {
			$table = maybe_unserialize( $ligne->meta_value );
			if ( ! is_array( $table ) ) {
				continue;
			}
			foreach ( $table as $cle => $copie ) {
				if ( ! is_array( $copie ) || empty( $copie['distant'] ) ) {
					continue;
				}
				$poids = isset( $copie['taille'] ) ? max( 0, (int) $copie['taille'] ) : 0;
				if ( 0 === strpos( (string) $cle, 'word:' ) ) {
					$categorie = 'word';
				} else {
					$reel      = self::chemin( (string) $cle );
					$categorie = isset( $fichiers[ $reel ] ) ? $fichiers[ $reel ]['categorie'] : 'orphelins';
				}
				self::ajouter( $mesure['sdrive'], $categorie, $poids );
				self::ajouter_a_la_notice( $mesure, (int) $ligne->post_id, 'sdrive', $poids );
			}
		}
		return $fichiers;
	}

	/** Note chaque chemin d'une valeur, à quelque profondeur qu'il soit. */
	private static function retenir( &$fichiers, $valeur, $categorie, $id ) {
		if ( is_array( $valeur ) ) {
			foreach ( $valeur as $element ) {
				self::retenir( $fichiers, $element, $categorie, $id );
			}
			return;
		}
		if ( ! is_string( $valeur ) || '' === $valeur ) {
			return;
		}
		$chemin = self::chemin( $valeur );
		if ( ! isset( $fichiers[ $chemin ] ) ) {
			$fichiers[ $chemin ] = array( 'categorie' => $categorie, 'id' => $id );
		}
	}

	/**
	 * Le chemin tel que le parcours le rend : un lien symbolique dans le
	 * chemin des téléversements le ferait sinon passer pour un autre fichier.
	 */
	private static function chemin( $chemin ) {
		$reel = @realpath( $chemin );
		return false !== $reel ? $reel : $chemin;
	}

	/** Les fichiers d'un dossier et leur poids, jusqu'à l'échéance. */
	private static function parcourir( $dossier, $fin, &$complet ) {
		$trouves = array();
		try {
			$parcours = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dossier, FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $parcours as $element ) {
				if ( microtime( true ) > $fin ) {
					$complet = false;
					break;
				}
				if ( $element->isFile() && ! $element->isLink() ) {
					$trouves[ $element->getPathname() ] = (int) $element->getSize();
				}
			}
		} catch ( Exception $e ) {
			// Un dossier illisible : on rend ce qu'on a compté, et l'on dit que c'est partiel.
			$complet = false;
		}
		return $trouves;
	}

	/** Ce que les notices et l'extension prennent dans la base : libellé ⇒ octets. */
	private static function poids_de_la_base() {
		global $wpdb;
		$type   = Notice_Archeomed_File::CPT;
		$base   = array();
		$autres = 0;
		$lignes = $wpdb->get_results( $wpdb->prepare(
			"SELECT pm.meta_key, SUM(LENGTH(pm.meta_value)) AS octets FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type = %s GROUP BY pm.meta_key", $type ) );
		foreach ( self::BASE as $libelle ) {
			$base[ $libelle ] = 0;
		}
		foreach ( $lignes as $ligne ) {
			if ( isset( self::BASE[ $ligne->meta_key ] ) ) {
				$base[ self::BASE[ $ligne->meta_key ] ] += (int) $ligne->octets;
			} else {
				$autres += (int) $ligne->octets;
			}
		}
		$base['Autres renseignements (états, journal, relecture…)'] = $autres
			+ (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT SUM(LENGTH(post_content) + LENGTH(post_title)) FROM {$wpdb->posts} WHERE post_type = %s", $type ) );
		$base['Réglages et réserves de l’extension'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( 'notice_archeomed' ) . '%', $wpdb->esc_like( 'na_' ) . '%' ) );
		return $base;
	}

	private static function vides( $categories ) {
		return array_map( function () {
			return array( 'n' => 0, 'octets' => 0 );
		}, $categories );
	}

	private static function ajouter( &$categories, $categorie, $poids ) {
		$categories[ $categorie ]['n']++;
		$categories[ $categorie ]['octets'] += $poids;
	}

	private static function ajouter_a_la_notice( &$mesure, $id, $ou, $poids ) {
		if ( ! isset( $mesure['notices'][ $id ] ) ) {
			$mesure['notices'][ $id ] = array( 'serveur' => 0, 'sdrive' => 0 );
		}
		$mesure['notices'][ $id ][ $ou ] += $poids;
	}

	/** Le total d'un tableau de catégories : nombre et octets. */
	public static function total( $categories ) {
		return array(
			'n'      => array_sum( array_column( $categories, 'n' ) ),
			'octets' => array_sum( array_column( $categories, 'octets' ) ),
		);
	}

	/** L'onglet. */
	public static function onglet() {
		$mesure = self::mesurer();
		echo '<h2>' . esc_html__( 'Stockage', 'notice-archeomed' ) . '</h2>';
		echo '<p class="description" style="max-width:46em">' . esc_html(
			"Ce que prennent les notices, catégorie par catégorie. Les fichiers se comptent à l’ouverture de la page\u{00A0}; le total du site, lui, est mesuré au plus tous les quarts d’heure." ) . '</p>';
		if ( ! $mesure['complet'] ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html(
				"Le parcours des dossiers a été interrompu au bout de quelques secondes\u{00A0}: les chiffres sont des minimums." ) . '</p></div>';
		}

		self::le_site( $mesure );

		echo '<h3>' . esc_html__( 'Le dossier des notices', 'notice-archeomed' ) . '</h3>';
		echo '<p class="description" style="max-width:46em">' . esc_html(
			"Les fichiers gardés à demeure, dans les téléversements du site. Un original rangé sur sDrive s’efface d’ici dès que sa copie est vérifiée et que sa figure a un aperçu." ) . '</p>';
		self::tableau( $mesure['serveur'], self::SERVEUR, __( 'Fichiers du dossier des notices, par catégorie', 'notice-archeomed' ) );

		echo '<h3>' . esc_html__( 'Les fichiers de passage', 'notice-archeomed' ) . '</h3>';
		echo '<p class="description" style="max-width:46em">' . esc_html( sprintf(
			"Ce que l’extension écrit le temps d’un dépôt, d’un envoi ou d’un export, dans %1\$s. %2\$s",
			$mesure['passage_dossier'],
			$mesure['passage_dans_site']
				? "Ce répertoire est dans celui du site\u{00A0}: il compte dans le quota."
				: "Ce répertoire est hors de celui du site\u{00A0}: le quota ne le compte probablement pas." ) ) . '</p>';
		self::tableau( $mesure['passage'], self::PASSAGE, __( 'Fichiers de passage, par catégorie', 'notice-archeomed' ) );

		echo '<h3>' . esc_html__( 'Sur sDrive', 'notice-archeomed' ) . '</h3>';
		$sdrive = self::total( $mesure['sdrive'] );
		if ( 0 === $sdrive['n'] ) {
			echo '<p>' . esc_html__( 'Rien n’est encore rangé sur sDrive.', 'notice-archeomed' ) . '</p>';
		} else {
			echo '<p class="description" style="max-width:46em">' . esc_html(
				"Ce que l’extension a déposé sur sDrive, d’après ce qu’elle en a noté. Cette place n’est pas prise sur le serveur du site." ) . '</p>';
			$libelles              = self::SERVEUR;
			$libelles['orphelins'] = 'Fichiers de versions remplacées';
			unset( $libelles['styles'] );
			self::tableau( $mesure['sdrive'], $libelles, __( 'Fichiers rangés sur sDrive, par catégorie', 'notice-archeomed' ) );
		}

		echo '<h3>' . esc_html__( 'Dans la base de données', 'notice-archeomed' ) . '</h3>';
		echo '<p class="description" style="max-width:46em">' . esc_html(
			"Le texte des notices et ce que l’extension en sait. La base est d’ordinaire comptée à part du quota des fichiers." ) . '</p>';
		echo '<table class="widefat striped" style="max-width:46em"><caption class="screen-reader-text">'
			. esc_html__( 'Place prise dans la base de données', 'notice-archeomed' ) . '</caption><thead><tr><th scope="col">'
			. esc_html__( 'Contenu', 'notice-archeomed' ) . '</th><th scope="col" style="text-align:right">'
			. esc_html__( 'Place', 'notice-archeomed' ) . '</th></tr></thead><tbody>';
		foreach ( $mesure['base'] as $libelle => $octets ) {
			echo '<tr><td>' . esc_html( $libelle ) . '</td><td style="text-align:right">' . esc_html( self::taille( $octets ) ) . '</td></tr>';
		}
		echo '</tbody><tfoot><tr><th scope="row">' . esc_html__( 'Total', 'notice-archeomed' ) . '</th><th style="text-align:right">'
			. esc_html( self::taille( array_sum( $mesure['base'] ) ) ) . '</th></tr></tfoot></table>';

		self::les_plus_lourdes( $mesure );
	}

	/** Le site entier, rapporté au plafond de l'hébergeur. */
	private static function le_site( $mesure ) {
		$disque  = Notice_Archeomed_Settings::mesurer_le_disque();
		$plafond = (int) apply_filters( 'notice_archeomed_plafond_disque', Notice_Archeomed_Settings::PLAFOND_DISQUE );
		$notices = self::total( $mesure['serveur'] );
		echo '<h3>' . esc_html__( 'Le site entier', 'notice-archeomed' ) . '</h3>';
		echo '<table class="widefat striped" style="max-width:46em"><caption class="screen-reader-text">'
			. esc_html__( 'Place prise par les téléversements du site', 'notice-archeomed' ) . '</caption><tbody>';
		$lignes = array(
			__( 'Téléversements du site', 'notice-archeomed' ) => self::taille( $disque['site'] )
				. ( $plafond > 0 ? sprintf( " sur %1\$s (%2\$s\u{00A0}%%)", self::taille( $plafond ),
					number_format_i18n( 100 * $disque['site'] / $plafond, 1 ) ) : '' ),
			__( 'dont le dossier des notices', 'notice-archeomed' ) => self::taille( $notices['octets'] ),
			__( 'dont le reste du site (médias, thèmes…)', 'notice-archeomed' ) => self::taille( max( 0, $disque['site'] - $disque['notices'] ) ),
			__( 'Notices enregistrées', 'notice-archeomed' ) => number_format_i18n( $mesure['nombre'] ),
		);
		if ( $plafond > 0 && $disque['site'] < $plafond && $mesure['nombre'] > 0 && $notices['octets'] > 0 ) {
			$par_notice = $notices['octets'] / $mesure['nombre'];
			$lignes[ __( 'Moyenne par notice sur le serveur', 'notice-archeomed' ) ] = self::taille( $par_notice );
		}
		foreach ( $lignes as $libelle => $valeur ) {
			echo '<tr><th scope="row">' . esc_html( $libelle ) . '</th><td style="text-align:right">' . esc_html( $valeur ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( empty( $disque['complet'] ) ) {
			echo '<p class="description">' . esc_html(
				"La mesure du site a été interrompue\u{00A0}: le total est un minimum." ) . '</p>';
		}
	}

	/** Un tableau de catégories, avec le nombre de fichiers et la place. */
	private static function tableau( $categories, $libelles, $legende ) {
		echo '<table class="widefat striped" style="max-width:46em"><caption class="screen-reader-text">'
			. esc_html( $legende ) . '</caption><thead><tr><th scope="col">'
			. esc_html__( 'Catégorie', 'notice-archeomed' ) . '</th><th scope="col" style="text-align:right">'
			. esc_html__( 'Fichiers', 'notice-archeomed' ) . '</th><th scope="col" style="text-align:right">'
			. esc_html__( 'Place', 'notice-archeomed' ) . '</th></tr></thead><tbody>';
		foreach ( $libelles as $cle => $libelle ) {
			$ligne = isset( $categories[ $cle ] ) ? $categories[ $cle ] : array( 'n' => 0, 'octets' => 0 );
			echo '<tr><td>' . esc_html( $libelle ) . '</td><td style="text-align:right">'
				. esc_html( number_format_i18n( $ligne['n'] ) ) . '</td><td style="text-align:right">'
				. esc_html( self::taille( $ligne['octets'] ) ) . '</td></tr>';
		}
		$total = self::total( $categories );
		echo '</tbody><tfoot><tr><th scope="row">' . esc_html__( 'Total', 'notice-archeomed' ) . '</th><th style="text-align:right">'
			. esc_html( number_format_i18n( $total['n'] ) ) . '</th><th style="text-align:right">'
			. esc_html( self::taille( $total['octets'] ) ) . '</th></tr></tfoot></table>';
	}

	/** Les dix notices qui prennent le plus de place sur le serveur. */
	private static function les_plus_lourdes( $mesure ) {
		$lourdes = array_slice( array_filter( $mesure['notices'], function ( $n ) {
			return $n['serveur'] > 0;
		} ), 0, 10, true );
		if ( empty( $lourdes ) ) {
			return;
		}
		echo '<h3>' . esc_html__( 'Les notices les plus lourdes sur le serveur', 'notice-archeomed' ) . '</h3>';
		echo '<table class="widefat striped" style="max-width:46em"><caption class="screen-reader-text">'
			. esc_html__( 'Les dix notices qui prennent le plus de place sur le serveur', 'notice-archeomed' ) . '</caption><thead><tr><th scope="col">'
			. esc_html__( 'Notice', 'notice-archeomed' ) . '</th><th scope="col" style="text-align:right">'
			. esc_html__( 'Sur le serveur', 'notice-archeomed' ) . '</th><th scope="col" style="text-align:right">'
			. esc_html__( 'Sur sDrive', 'notice-archeomed' ) . '</th></tr></thead><tbody>';
		foreach ( $lourdes as $id => $poids ) {
			$reference = (string) get_post_meta( $id, '_na_reference', true );
			$titre     = get_the_title( $id );
			$nom       = trim( ( '' !== $reference ? $reference . ' — ' : '' ) . ( '' !== $titre ? $titre : '#' . $id ) );
			$lien      = get_edit_post_link( $id, '' );
			echo '<tr><td>' . ( $lien ? '<a href="' . esc_url( $lien ) . '">' . esc_html( $nom ) . '</a>' : esc_html( $nom ) )
				. '</td><td style="text-align:right">' . esc_html( self::taille( $poids['serveur'] ) )
				. '</td><td style="text-align:right">' . esc_html( $poids['sdrive'] > 0 ? self::taille( $poids['sdrive'] ) : '—' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/** Une taille lisible : « 12,4 Mo », « 0 o ». */
	private static function taille( $octets ) {
		$octets = max( 0, (int) round( $octets ) );
		return 0 === $octets ? '0 o' : (string) size_format( $octets, $octets >= MB_IN_BYTES ? 1 : 0 );
	}
}
