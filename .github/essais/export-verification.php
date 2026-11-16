<?php
/**
 * L'export sur sDrive, constaté sur le faux sDrive : à lancer par
 * « wp eval-file » une fois l'export passé.
 *
 * Plus rien en cours ni en attente ; chaque rubrique a fini, et tout ce
 * que son manifeste liste est là. Chaque image que le document appelle,
 * chaque image de la page de relecture, existe dans « icono/ » ; chaque
 * figure en basse définition a sa haute définition ; chaque rubrique a
 * son lisez-moi. Aucun fichier des dossiers de l'export n'est orphelin,
 * aucun n'est à deux rubriques. La figure déposée à la main pour un envoi
 * à part est reprise, l'autre est donnée à reprendre. Rien ne reste au
 * répertoire temporaire.
 *
 * Au premier passage, l'empreinte de chaque fichier est gardée. Au second,
 * après le réexport d'une seule rubrique, les fichiers des autres doivent
 * être restés tels quels, et la rubrique réexportée doit l'avoir été de
 * nouveau.
 *
 * Variables : NA_SDRIVE_RACINE (le répertoire du faux sDrive, dossier
 * choisi compris) ; NA_EMPREINTES (le fichier des empreintes) ;
 * NA_REEXPORT (la rubrique réexportée, au second passage seulement).
 */

$racine     = untrailingslashit( (string) getenv( 'NA_SDRIVE_RACINE' ) );
$empreintes = (string) getenv( 'NA_EMPREINTES' );
$reexport   = (string) getenv( 'NA_REEXPORT' );
$echecs     = array();

foreach ( array( Notice_Archeomed_Export::TRAVAIL, Notice_Archeomed_Export::ATTENTE, Notice_Archeomed_Export::MANIFESTES ) as $option ) {
	wp_cache_delete( $option, 'options' );
}
if ( Notice_Archeomed_Export::en_cours() ) {
	$echecs[] = 'L’export n’est pas fini : ' . wp_json_encode( Notice_Archeomed_Export::travail() )
		. ' ; en attente : ' . implode( ', ', Notice_Archeomed_Export::attente() );
}
$numero     = Notice_Archeomed_Export::numero();
$manifestes = Notice_Archeomed_Export::manifestes();
$rubriques  = isset( $manifestes[ $numero ] ) && is_array( $manifestes[ $numero ] ) ? $manifestes[ $numero ] : array();
if ( count( $rubriques ) < 2 ) {
	$echecs[] = 'Deux rubriques au moins devaient être exportées dans « ' . $numero . ' » ; il y en a ' . count( $rubriques ) . '.';
}

$a_qui      = array();
$figures    = 0;
$reprises   = 0;
$a_reprendre = 0;
$nouvelles  = array();
foreach ( $rubriques as $rubrique => $m ) {
	$dit = 'Rubrique « ' . $rubrique . ' » : ';
	if ( ! isset( $m['issue'] ) || 'ok' !== $m['issue'] ) {
		$echecs[] = $dit . 'export non abouti (' . wp_json_encode( $m ) . ').';
		continue;
	}
	$liste    = isset( $m['fichiers'] ) ? (array) $m['fichiers'] : array();
	$docx     = array();
	$pages    = array();
	$lisez    = array();
	$hr       = array();
	$br       = array();
	foreach ( $liste as $distant ) {
		$nouvelles[ $rubrique ][ $distant ] = is_file( $racine . '/' . $distant ) ? sha1_file( $racine . '/' . $distant ) : '';
		if ( isset( $a_qui[ $distant ] ) ) {
			$echecs[] = $dit . $distant . ' est aussi à « ' . $a_qui[ $distant ] . ' ».';
		}
		$a_qui[ $distant ] = $rubrique;
		if ( ! is_file( $racine . '/' . $distant ) ) {
			$echecs[] = $dit . $distant . ' est au manifeste mais pas sur sDrive.';
			continue;
		}
		if ( 0 !== strpos( $distant, $numero . '/' ) ) {
			$echecs[] = $dit . $distant . ' est hors du dossier du numéro.';
		} elseif ( preg_match( '#^[^/]+/style/[^/]+\.docx$#', $distant ) ) {
			$docx[] = $distant;
		} elseif ( preg_match( '#^[^/]+/relecture_[^/]+\.html$#', $distant ) ) {
			$pages[] = $distant;
		} elseif ( preg_match( '#^[^/]+/lisez-moi_[^/]+\.txt$#', $distant ) ) {
			$lisez[] = $distant;
		} elseif ( preg_match( '#^[^/]+/icono/hr/([^/]+)\.[^./]+$#', $distant, $x ) ) {
			$hr[ $x[1] ] = $distant;
		} elseif ( preg_match( '#^[^/]+/icono/br/([^/]+)\.jpg$#', $distant, $x ) ) {
			$br[ $x[1] ] = $distant;
		} elseif ( ! preg_match( '#^[^/]+/XML/indexation/[^/]+\.xml$#', $distant ) ) {
			$echecs[] = $dit . $distant . ' n’a rien à faire dans l’export.';
		}
	}
	if ( 1 !== count( $docx ) || 1 !== count( $pages ) || 1 !== count( $lisez ) ) {
		$echecs[] = $dit . count( $docx ) . ' documents, ' . count( $pages ) . ' pages de relecture, '
			. count( $lisez ) . ' lisez-moi ; il en faut un de chaque.';
		continue;
	}
	foreach ( $br as $nom => $distant ) {
		if ( ! isset( $hr[ $nom ] ) ) {
			$echecs[] = $dit . basename( $distant ) . ' n’a pas sa haute définition.';
		}
	}
	if ( count( $br ) !== (int) $m['figures'] ) {
		$echecs[] = $dit . count( $br ) . ' figures dans « icono/br », le manifeste en annonce ' . (int) $m['figures'] . '.';
	}
	$figures += count( $br );

	// Les images que le document appelle, lues depuis « style/ ».
	$zip = new ZipArchive();
	if ( true !== $zip->open( $racine . '/' . $docx[0] ) ) {
		$echecs[] = $dit . 'le document ne s’ouvre pas.';
	} else {
		$rels = (string) $zip->getFromName( 'word/_rels/document.xml.rels' );
		$zip->close();
		preg_match_all( '#Type="[^"]*/image"\s+Target="([^"]+)"\s+TargetMode="External"#', $rels, $liens );
		if ( count( $liens[1] ) !== count( $br ) ) {
			$echecs[] = $dit . 'le document appelle ' . count( $liens[1] ) . ' images, « icono/br » en a ' . count( $br ) . '.';
		}
		foreach ( $liens[1] as $cible ) {
			$cible = html_entity_decode( $cible, ENT_QUOTES | ENT_XML1 );
			if ( 0 !== strpos( $cible, '../icono/br/' ) || ! isset( $a_qui[ $numero . '/' . substr( $cible, 3 ) ] )
				|| ! is_file( $racine . '/' . $numero . '/' . substr( $cible, 3 ) ) ) {
				$echecs[] = $dit . 'le document appelle « ' . $cible . ' », absent de l’export.';
			}
		}
	}

	// Les images de la page de relecture, lues depuis la racine du numéro.
	$page = (string) file_get_contents( $racine . '/' . $pages[0] );
	preg_match_all( '#<img\s+src="([^"]+)"#', $page, $images );
	foreach ( $images[1] as $src ) {
		$src = html_entity_decode( $src, ENT_QUOTES );
		if ( 0 === strpos( $src, 'data:' ) ) {
			continue;
		}
		if ( ! is_file( $racine . '/' . $numero . '/' . rawurldecode( $src ) ) ) {
			$echecs[] = $dit . 'la relecture montre « ' . $src . ' », absent de l’export.';
		}
	}

	$texte = (string) file_get_contents( $racine . '/' . $lisez[0] );
	$reprises    += substr_count( $texte, 'reprise de l’original déposé sur sDrive' );
	$a_reprendre += preg_match_all( '/envoy[ée]+ à part/u', $texte );
}

// Rien d'orphelin dans les dossiers de l'export.
foreach ( array( 'style', 'icono/hr', 'icono/br', 'XML/indexation', '' ) as $sous ) {
	$dossier = $racine . '/' . $numero . ( '' === $sous ? '' : '/' . $sous );
	foreach ( (array) glob( $dossier . '/*' ) as $fichier ) {
		if ( ! is_file( $fichier ) ) {
			continue;
		}
		$distant = $numero . '/' . ( '' === $sous ? '' : $sous . '/' ) . basename( $fichier );
		if ( ! isset( $a_qui[ $distant ] ) ) {
			$echecs[] = $distant . ' n’est à aucune rubrique : un export précédent l’a laissé.';
		}
	}
}

if ( $reprises < 1 ) {
	$echecs[] = 'La figure déposée à la main pour un envoi à part n’a pas été reprise.';
}
if ( $a_reprendre < 1 ) {
	$echecs[] = 'Aucune figure envoyée à part n’est donnée à reprendre au lisez-moi.';
}

// Le réexport d'une rubrique laisse les autres telles quelles.
if ( '' !== $reexport ) {
	$avant = is_file( $empreintes ) ? json_decode( (string) file_get_contents( $empreintes ), true ) : null;
	if ( ! is_array( $avant ) ) {
		$echecs[] = 'Les empreintes du premier export manquent.';
	} else {
		foreach ( $avant['fichiers'] as $rubrique => $fichiers ) {
			if ( $rubrique === $reexport ) {
				if ( empty( $rubriques[ $rubrique ]['dernier'] ) || (int) $rubriques[ $rubrique ]['dernier'] <= (int) $avant['dernier'][ $rubrique ] ) {
					$echecs[] = 'La rubrique « ' . $rubrique . ' » n’a pas été réexportée.';
				}
				continue;
			}
			$apres = isset( $nouvelles[ $rubrique ] ) ? $nouvelles[ $rubrique ] : array();
			if ( $apres !== $fichiers ) {
				$echecs[] = 'Le réexport de « ' . $reexport . ' » a touché « ' . $rubrique . ' » ('
					. count( array_diff_assoc( $fichiers, $apres ) ) . ' fichiers changés ou perdus).';
			}
		}
	}
} elseif ( '' !== $empreintes ) {
	file_put_contents( $empreintes, wp_json_encode( array(
		'fichiers' => $nouvelles,
		'dernier'  => array_map( function ( $m ) {
			return isset( $m['dernier'] ) ? (int) $m['dernier'] : 0;
		}, $rubriques ),
	) ) );
}

$restes = array_merge(
	(array) glob( trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp/notice-archeomed-sdrive-*' ),
	(array) glob( trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp/atelier-*' )
);
if ( ! empty( $restes ) ) {
	$echecs[] = count( $restes ) . ' restes au répertoire temporaire : ' . implode( ', ', array_map( 'basename', array_slice( $restes, 0, 3 ) ) );
}

echo 'Rubriques : ', count( $rubriques ), ' — figures exportées : ', $figures, ' — fichiers : ', count( $a_qui ),
	' — reprise à part : ', $reprises, "\n";
if ( ! empty( $echecs ) ) {
	foreach ( array_slice( $echecs, 0, 30 ) as $e ) {
		echo '::error::', $e, "\n";
	}
	exit( 1 );
}
echo "L'export passe : chaque image appelée est là, rien d'orphelin, chaque rubrique a son lisez-moi.\n";
