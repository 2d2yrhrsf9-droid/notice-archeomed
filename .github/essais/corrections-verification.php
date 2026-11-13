<?php
/**
 * Les corrections en place, constatées sur la base : à lancer par
 * « wp eval-file », une fois avant corrections.php et une fois après le
 * passage du planificateur.
 *
 * Avant (NA_ETAPE=avant) : note chaque notice — sa référence, sa version,
 * ses fichiers — dans NA_INSTANTANE, et écrit son lien de correction dans
 * NA_LIENS, dans l'ordre des identifiants.
 *
 * Après : la correction doit avoir récrit chaque notice, non en avoir fait
 * une autre. Même nombre de notices, même référence, une version de plus
 * exactement — les corrections disputées n'en ont fait passer qu'une —,
 * le lieu-dit corrigé, la figure 1 gardée telle quelle, la figure 2
 * remplacée et ses anciens fichiers effacés, la figure 3 ajoutée, toutes
 * avec leur aperçu ; la notice repartie une seule fois vers la rédaction,
 * marquée corrigée ; rien laissé au répertoire temporaire.
 *
 * Variables : NA_ETAPE, NA_INSTANTANE, NA_LIENS, NA_COURRIELS (le journal
 * des courriels, une ligne « destinataire<TAB>sujet » chacun).
 */

$etape      = (string) getenv( 'NA_ETAPE' );
$instantane = (string) getenv( 'NA_INSTANTANE' );

$ids = get_posts( array(
	'post_type' => Notice_Archeomed_File::CPT, 'post_status' => 'any', 'posts_per_page' => -1,
	'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC',
) );
// Tout ce que le plugin pose au répertoire temporaire porte ce préfixe : une
// figure reçue, sa conversion, une autorisation, un Word.
$au_temporaire = function () {
	$fichiers = glob( trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp/notice-archeomed-*' );
	return array_values( array_filter( is_array( $fichiers ) ? $fichiers : array(), 'is_file' ) );
};
$apercus_de = function ( $id ) {
	$apercus = get_post_meta( $id, '_na_apercus', true );
	$chemins = array();
	foreach ( is_array( $apercus ) ? $apercus : array() as $rang => $mesure ) {
		$chemins[ (int) $rang ] = isset( $mesure['apercu'] ) ? (string) $mesure['apercu'] : '';
	}
	return $chemins;
};

if ( 'avant' === $etape ) {
	$notes = array();
	$liens = array();
	foreach ( $ids as $id ) {
		$d = get_post_meta( $id, '_na_donnees', true );
		$notes[ $id ] = array(
			'reference'     => (string) get_post_meta( $id, '_na_reference', true ),
			'version'       => (int) get_post_meta( $id, '_na_version', true ),
			'lieu_dit'      => is_array( $d ) && isset( $d['lieu_dit'] ) ? (string) $d['lieu_dit'] : '',
			'illustrations' => Notice_Archeomed_Pactols::illustrations_rangees( $id ),
			'conversions'   => Notice_Archeomed_Pactols::conversions_rangees( $id ),
			'apercus'       => $apercus_de( $id ),
		);
		$liens[] = is_array( $d ) && isset( $d['correction_url'] ) ? (string) $d['correction_url'] : '';
	}
	file_put_contents( $instantane, wp_json_encode( array( 'notices' => $notes, 'temporaire' => $au_temporaire() ) ) );
	file_put_contents( (string) getenv( 'NA_LIENS' ), implode( "\n", $liens ) . "\n" );
	echo count( $notes ), " notices notées, ", count( array_filter( $liens ) ), " liens de correction.\n";
	exit( count( array_filter( $liens ) ) === count( $ids ) && ! empty( $ids ) ? 0 : 1 );
}

$lu     = json_decode( (string) file_get_contents( $instantane ), true );
$notes  = isset( $lu['notices'] ) && is_array( $lu['notices'] ) ? $lu['notices'] : array();
$deja   = isset( $lu['temporaire'] ) && is_array( $lu['temporaire'] ) ? $lu['temporaire'] : array();
$echecs = array();
$fautes = function ( $id, $quoi ) use ( &$echecs ) {
	$echecs[] = 'Notice ' . $id . ' : ' . $quoi;
};

if ( array_map( 'intval', array_keys( $notes ) ) !== array_map( 'intval', $ids ) ) {
	$echecs[] = count( $ids ) . ' notices en base après les corrections, ' . count( $notes ) . ' avant : une correction en a fait une autre, ou en a perdu une.';
}

$refs = array();
foreach ( $notes as $id => $avant ) {
	$id = (int) $id;
	$d  = get_post_meta( $id, '_na_donnees', true );
	if ( ! is_array( $d ) ) {
		$fautes( $id, 'saisie illisible.' );
		continue;
	}
	$refs[ $avant['reference'] ] = $id;
	if ( (string) get_post_meta( $id, '_na_reference', true ) !== $avant['reference']
		|| ! isset( $d['reference'] ) || $d['reference'] !== $avant['reference'] ) {
		$fautes( $id, 'la référence a changé.' );
	}
	$version = (int) get_post_meta( $id, '_na_version', true );
	if ( max( 1, $avant['version'] ) + 1 !== $version ) {
		$fautes( $id, 'version ' . $version . ', ' . ( max( 1, $avant['version'] ) + 1 ) . ' attendue.' );
	}
	$histoire = get_post_meta( $id, '_na_versions', true );
	$derniere = is_array( $histoire ) && ! empty( $histoire ) ? end( $histoire ) : array();
	if ( empty( $derniere['champs'] ) || ! in_array( 'le lieu-dit', $derniere['champs'], true )
		|| ! in_array( 'les figures', $derniere['champs'], true ) ) {
		$fautes( $id, 'l’historique ne dit pas ce qui a changé : ' . wp_json_encode( $derniere ) );
	}
	if ( ! isset( $d['lieu_dit'] ) || $avant['lieu_dit'] . ' (corrigé)' !== $d['lieu_dit'] ) {
		$fautes( $id, 'lieu-dit « ' . ( isset( $d['lieu_dit'] ) ? $d['lieu_dit'] : '' ) . ' ».' );
	}
	if ( 'envoyee' !== get_post_meta( $id, '_na_etat', true ) ) {
		$fautes( $id, 'pas repartie (« ' . get_post_meta( $id, '_na_etat', true ) . ' »).' );
	}
	if ( metadata_exists( 'post', $id, '_na_corrigee' ) ) {
		$fautes( $id, 'encore marquée corrigée après son envoi.' );
	}

	// Les figures : 1 gardée, 2 remplacée, 3 ajoutée.
	$fichiers = Notice_Archeomed_Pactols::illustrations_rangees( $id );
	$conv     = Notice_Archeomed_Pactols::conversions_rangees( $id );
	$apercus  = $apercus_de( $id );
	$items    = isset( $d['illustrations'] ) && is_array( $d['illustrations'] ) ? array_values( $d['illustrations'] ) : array();
	if ( 3 !== count( $fichiers ) || 3 !== count( $items ) ) {
		$fautes( $id, count( $fichiers ) . ' fichiers et ' . count( $items ) . ' légendes, 3 attendus.' );
		continue;
	}
	foreach ( $items as $i => $item ) {
		if ( (int) $item['rang'] !== $i + 1 ) {
			$fautes( $id, 'la figure ' . ( $i + 1 ) . ' porte le rang ' . (int) $item['rang'] . '.' );
		}
	}
	foreach ( $fichiers as $i => $chemin ) {
		if ( ! is_file( $chemin ) ) {
			$fautes( $id, 'le fichier de la figure ' . ( $i + 1 ) . ' manque.' );
		}
		if ( empty( $apercus[ $i + 1 ] ) || ! is_file( $apercus[ $i + 1 ] ) ) {
			$fautes( $id, 'pas d’aperçu pour la figure ' . ( $i + 1 ) . '.' );
		}
	}
	if ( $fichiers[0] !== $avant['illustrations'][0] ) {
		$fautes( $id, 'la figure gardée a changé de fichier.' );
	}
	if ( 0 !== strpos( (string) $items[0]['titre'], 'Vue 1' ) ) {
		$fautes( $id, 'la figure gardée a perdu son titre (« ' . $items[0]['titre'] . ' »).' );
	}
	if ( 'tif' !== strtolower( pathinfo( $fichiers[1], PATHINFO_EXTENSION ) ) || $fichiers[1] === $avant['illustrations'][1] ) {
		$fautes( $id, 'la figure 2 n’a pas été remplacée.' );
	}
	if ( empty( $conv[2]['hr'] ) || empty( $conv[2]['br'] ) || ! is_file( $conv[2]['hr'] ) || ! is_file( $conv[2]['br'] ) ) {
		$fautes( $id, 'le TIFF de remplacement n’a pas gardé ses JPEG convertis.' );
	}
	if ( 0 !== strpos( (string) $items[2]['titre'], 'Figure ajoutée' ) ) {
		$fautes( $id, 'la figure ajoutée n’est pas la troisième (« ' . $items[2]['titre'] . ' »).' );
	}
	// Ce que la notice ne désigne plus a quitté le serveur.
	$anciens = array( $avant['illustrations'][1] );
	foreach ( array( 'hr', 'br' ) as $famille ) {
		if ( ! empty( $avant['conversions'][2][ $famille ] ) ) {
			$anciens[] = $avant['conversions'][2][ $famille ];
		}
	}
	if ( ! empty( $avant['apercus'][2] ) ) {
		$anciens[] = $avant['apercus'][2];
	}
	foreach ( $anciens as $chemin ) {
		if ( is_file( $chemin ) ) {
			$fautes( $id, 'l’ancien fichier ' . basename( $chemin ) . ' est resté.' );
		}
	}
}

// Rien de reçu ne traîne au répertoire temporaire, pas même ce que les
// corrections renvoyées pour conflit avaient apporté — figures neuves
// comprises, qui n'ont pour nom qu'un tirage au sort.
$restes = array_values( array_diff( $au_temporaire(), $deja ) );
if ( ! empty( $restes ) ) {
	$echecs[] = count( $restes ) . ' fichiers reçus restés au répertoire temporaire : ' . implode( ', ', array_map( 'basename', array_slice( $restes, 0, 5 ) ) );
}

// Une seule « [CORRIGÉE] » par notice à la rédaction.
$journal  = (string) getenv( 'NA_COURRIELS' );
$lignes   = is_file( $journal ) ? file( $journal, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : array();
$corriges = array();
foreach ( $lignes as $ligne ) {
	list( $vers, $sujet ) = array_pad( explode( "\t", $ligne, 2 ), 2, '' );
	if ( false !== strpos( $vers, 'redaction@example.org' ) && 0 === strpos( $sujet, '[CORRIGÉE]' )
		&& preg_match( '/réf\. ([A-Z0-9]{6})/u', $sujet, $m ) ) {
		$corriges[ $m[1] ] = isset( $corriges[ $m[1] ] ) ? $corriges[ $m[1] ] + 1 : 1;
	}
}
foreach ( $refs as $ref => $id ) {
	$fois = isset( $corriges[ $ref ] ) ? $corriges[ $ref ] : 0;
	if ( 1 !== $fois ) {
		$fautes( $id, $fois . ' courriels « [CORRIGÉE] » à la rédaction, 1 attendu.' );
	}
}

echo 'Notices corrigées : ', count( $notes ), ' — courriels « [CORRIGÉE] » : ', array_sum( $corriges ), "\n";
if ( ! empty( $echecs ) ) {
	foreach ( array_slice( $echecs, 0, 30 ) as $e ) {
		echo '::error::', $e, "\n";
	}
	exit( 1 );
}
echo "Les corrections passent : chaque notice récrite en place, une seule fois, et repartie corrigée.\n";
