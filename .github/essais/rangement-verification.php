<?php
/**
 * Le rangement sur sDrive, constaté sur la base et sur le faux sDrive : à
 * lancer par « wp eval-file » une fois le rangement passé.
 *
 * Pour chaque notice : plus rien en attente ni d'erreur ; chaque fichier
 * qu'elle désigne — original, conversion, autorisation, sauf le témoin
 * d'une figure envoyée à part — est sur sDrive à la taille notée, et n'est
 * plus sur le serveur quand son aperçu y est ; les aperçus restent ; le
 * Word de la version est rangé, et celui de la version 1 avec lui ; le
 * fichier « a-deposer.txt » est là, avec le lien, pour une notice qui a une
 * figure envoyée à part, et seulement pour elle. Une figure rangée se
 * rapatrie entière, et sa copie s'efface.
 *
 * Variables : NA_SDRIVE_RACINE (le répertoire du faux sDrive, dossier
 * choisi compris).
 */

$racine = untrailingslashit( (string) getenv( 'NA_SDRIVE_RACINE' ) );
$echecs = array();
$fautes = function ( $id, $quoi ) use ( &$echecs ) {
	$echecs[] = 'Notice ' . $id . ' : ' . $quoi;
};

$ids = get_posts( array(
	'post_type' => Notice_Archeomed_File::CPT, 'post_status' => 'any', 'posts_per_page' => -1,
	'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC',
) );
$distants = 0;
$purges   = 0;
$a_rapatrier = null;
foreach ( $ids as $id ) {
	wp_cache_delete( $id, 'post_meta' );
	$d = get_post_meta( $id, '_na_donnees', true );
	if ( ! is_array( $d ) ) {
		$fautes( $id, 'saisie illisible.' );
		continue;
	}
	foreach ( array( '_na_sdrive_apres', '_na_sdrive_essais', '_na_sdrive_raison' ) as $cle ) {
		if ( metadata_exists( 'post', $id, $cle ) ) {
			$fautes( $id, $cle . ' reste posé (« ' . get_post_meta( $id, $cle, true ) . ' »).' );
		}
	}
	$dossier = (string) get_post_meta( $id, '_na_sdrive_dossier', true );
	if ( '' === $dossier || 0 !== strpos( $dossier, 'AM99/originaux/' ) ) {
		$fautes( $id, 'dossier sur sDrive « ' . $dossier . ' ».' );
		continue;
	}
	$table   = Notice_Archeomed_Rangement::table_de( $id );
	$apercus = get_post_meta( $id, '_na_apercus', true );
	$apercus = is_array( $apercus ) ? $apercus : array();
	foreach ( $apercus as $rang => $mesure ) {
		if ( empty( $mesure['apercu'] ) || ! is_file( $mesure['apercu'] ) ) {
			$fautes( $id, 'l’aperçu de la figure ' . (int) $rang . ' a quitté le serveur.' );
		}
	}

	// Ce que la notice désigne, avec le rang de chaque fichier.
	$designes = array();
	foreach ( Notice_Archeomed_Pactols::illustrations_rangees( $id ) as $i => $chemin ) {
		if ( ! Notice_Archeomed_Pactols::est_a_part( $chemin ) ) {
			$designes[ $chemin ] = $i + 1;
		} elseif ( isset( $table[ $chemin ] ) ) {
			$fautes( $id, 'le témoin d’une figure envoyée à part est monté sur sDrive.' );
		}
	}
	foreach ( Notice_Archeomed_Pactols::conversions_rangees( $id ) as $rang => $familles ) {
		foreach ( (array) $familles as $chemin ) {
			$designes[ $chemin ] = (int) $rang;
		}
	}
	$autorisations = get_post_meta( $id, '_na_autorisations', true );
	foreach ( is_array( $autorisations ) ? $autorisations : array() as $rang => $chemin ) {
		$designes[ $chemin ] = (int) $rang;
	}
	foreach ( $designes as $chemin => $rang ) {
		if ( ! isset( $table[ $chemin ] ) ) {
			$fautes( $id, basename( $chemin ) . ' n’est pas rangé sur sDrive.' );
			continue;
		}
		$distant = $racine . '/' . $table[ $chemin ]['distant'];
		clearstatcache( true, $distant );
		if ( 0 !== strpos( $table[ $chemin ]['distant'], $dossier . '/Fig_' . $rang . '_v' ) ) {
			$fautes( $id, 'nom sur sDrive inattendu : ' . $table[ $chemin ]['distant'] );
		}
		if ( ! is_file( $distant ) || filesize( $distant ) !== (int) $table[ $chemin ]['taille'] ) {
			$fautes( $id, $table[ $chemin ]['distant'] . ' manque sur sDrive ou n’a pas sa taille.' );
			continue;
		}
		++$distants;
		if ( ! empty( $apercus[ $rang ]['apercu'] ) && is_file( $apercus[ $rang ]['apercu'] ) ) {
			if ( file_exists( $chemin ) ) {
				$fautes( $id, basename( $chemin ) . ' est rangé mais resté sur le serveur.' );
			} else {
				++$purges;
				if ( null === $a_rapatrier ) {
					$a_rapatrier = array( $id, $chemin, (int) $table[ $chemin ]['taille'] );
				}
			}
		}
	}

	// Le Word de chaque version, refait et rangé, puis effacé du serveur.
	$version = max( 1, (int) get_post_meta( $id, '_na_version', true ) );
	foreach ( array_unique( array( 1, $version ) ) as $v ) {
		if ( empty( $table[ 'word:v' . $v ] ) || ! is_file( $racine . '/' . $dossier . '/notice_v' . $v . '.docx' ) ) {
			$fautes( $id, 'le Word de la version ' . $v . ' n’est pas sur sDrive.' );
		}
	}
	$document = (string) get_post_meta( $id, '_na_document', true );
	if ( '' !== $document && is_file( $document ) ) {
		$fautes( $id, 'le Word est resté sur le serveur.' );
	}

	// « a-deposer.txt » pour une figure envoyée à part, et pour elle seule.
	$a_part = null;
	foreach ( isset( $d['illustrations'] ) && is_array( $d['illustrations'] ) ? $d['illustrations'] : array() as $item ) {
		$a_part = $a_part ? $a_part : Notice_Archeomed_Pactols::figure_a_part( $item );
	}
	$texte = $racine . '/' . $dossier . '/' . Notice_Archeomed_Rangement::A_DEPOSER;
	if ( null !== $a_part ) {
		$lu = is_file( $texte ) ? (string) file_get_contents( $texte ) : '';
		if ( false === strpos( $lu, (string) $a_part['lien'] ) || false === strpos( $lu, 'Fig_' ) ) {
			$fautes( $id, Notice_Archeomed_Rangement::A_DEPOSER . ' manque ou ne donne pas le lien.' );
		}
	} elseif ( file_exists( $texte ) ) {
		$fautes( $id, Notice_Archeomed_Rangement::A_DEPOSER . ' sans figure envoyée à part.' );
	}
}

// Une figure rangée revient entière le temps d'en avoir besoin.
if ( null === $a_rapatrier ) {
	$echecs[] = 'Aucun original n’a quitté le serveur : rien à rapatrier.';
} else {
	$temporaires = array();
	$lu          = Notice_Archeomed_Rangement::rapatrier( $a_rapatrier[0], $a_rapatrier[1], $temporaires );
	if ( '' === $lu || ! is_file( $lu ) || filesize( $lu ) !== $a_rapatrier[2] ) {
		$echecs[] = 'La figure ' . basename( $a_rapatrier[1] ) . ' ne se rapatrie pas entière.';
	}
	Notice_Archeomed_Rangement::effacer_les_temporaires( $temporaires );
	if ( '' !== $lu && file_exists( $lu ) ) {
		$echecs[] = 'La copie rapatriée ne s’est pas effacée.';
	}
}
$restes = glob( trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp/notice-archeomed-sdrive-*' );
if ( ! empty( $restes ) ) {
	$echecs[] = count( $restes ) . ' copies rapatriées restées au répertoire temporaire.';
}
$etat = get_option( Notice_Archeomed_Rangement::ETAT );
if ( Notice_Archeomed_Rangement::en_attente() > 0 ) {
	$echecs[] = Notice_Archeomed_Rangement::en_attente() . ' notices encore à ranger ; dernier passage : ' . wp_json_encode( $etat );
}

echo 'Notices : ', count( $ids ), ' — fichiers sur sDrive : ', $distants, ' — effacés du serveur : ', $purges, "\n";
if ( ! empty( $echecs ) ) {
	foreach ( array_slice( $echecs, 0, 30 ) as $e ) {
		echo '::error::', $e, "\n";
	}
	exit( 1 );
}
echo "Le rangement passe : tout est sur sDrive, le serveur ne garde que les aperçus.\n";
