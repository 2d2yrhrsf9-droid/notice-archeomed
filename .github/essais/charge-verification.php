<?php
/**
 * Ce qu'il reste après une charge : à lancer par « wp eval-file », une fois
 * les notices déposées et le planificateur passé.
 *
 * Vérifie, sur la base et sur le journal des courriels, que rien ne s'est
 * gêné : autant de notices que de dépôts, chacune avec sa référence, toutes
 * expédiées une seule fois — pas de notice perdue, pas de courriel double,
 * pas de notice restée en route.
 *
 * Variables : NA_N (le nombre attendu), NA_COURRIELS (le journal des
 * courriels, une ligne « destinataire<TAB>sujet » chacun).
 */

$attendu = (int) getenv( 'NA_N' );
$journal = (string) getenv( 'NA_COURRIELS' );
$echecs  = array();

$ids = get_posts( array(
	'post_type' => Notice_Archeomed_File::CPT, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids',
) );
$etats = array();
$refs  = array();
foreach ( $ids as $id ) {
	$etat = (string) get_post_meta( $id, '_na_etat', true );
	$etats[ $etat ] = isset( $etats[ $etat ] ) ? $etats[ $etat ] + 1 : 1;
	$refs[] = (string) get_post_meta( $id, '_na_reference', true );
}
echo 'Notices en base : ', count( $ids ), ' — états : ', wp_json_encode( $etats ), "\n";
if ( count( $ids ) !== $attendu ) {
	$echecs[] = count( $ids ) . ' notices en base pour ' . $attendu . ' dépôts acceptés.';
}
if ( count( array_unique( array_filter( $refs ) ) ) !== count( $ids ) ) {
	$echecs[] = 'Des références manquent ou se répètent : ' . implode( ',', $refs );
}
if ( array( 'envoyee' => $attendu ) !== $etats ) {
	$echecs[] = 'Toutes les notices ne sont pas « envoyee » : ' . wp_json_encode( $etats );
}

// Le TIFF de la figure 2, que le serveur de l'essai ne sait pas lire : chaque
// notice doit avoir gardé les deux JPEG du navigateur, et en avoir tiré l'aperçu.
$sans_conversion = array();
$sans_apercu     = array();
foreach ( $ids as $id ) {
	$conv = get_post_meta( $id, '_na_conversions', true );
	if ( ! is_array( $conv ) || empty( $conv[2]['hr'] ) || empty( $conv[2]['br'] ) || ! is_file( $conv[2]['hr'] ) || ! is_file( $conv[2]['br'] ) ) {
		$sans_conversion[] = $id;
	}
	$apercus = get_post_meta( $id, '_na_apercus', true );
	if ( ! is_array( $apercus ) || empty( $apercus[2]['apercu'] ) || ! is_file( $apercus[2]['apercu'] ) ) {
		$sans_apercu[] = $id;
	}
}
echo 'TIFF avec conversions gardées : ', count( $ids ) - count( $sans_conversion ), ' — avec aperçu : ', count( $ids ) - count( $sans_apercu ), "\n";
if ( ! empty( $sans_conversion ) ) {
	$echecs[] = count( $sans_conversion ) . ' notices n’ont pas gardé les JPEG convertis de leur TIFF.';
}
if ( ! empty( $sans_apercu ) ) {
	$echecs[] = count( $sans_apercu ) . ' notices n’ont pas d’aperçu pour leur TIFF.';
}

// Une notice sur deux porte une figure envoyée à part, en troisième : un
// fichier témoin tient son rang, et la figure garde son lien.
$a_part_attendues = intdiv( $attendu, 2 );
$a_part_vues      = 0;
$a_part_fautives  = array();
foreach ( $ids as $id ) {
	$d_charge = get_post_meta( $id, '_na_donnees', true );
	if ( ! is_array( $d_charge ) || ! preg_match( '/(\d+)$/', isset( $d_charge['lieu_dit'] ) ? (string) $d_charge['lieu_dit'] : '', $m_lieu ) || 0 !== (int) $m_lieu[1] % 2 ) {
		continue;
	}
	$fichiers_charge = get_post_meta( $id, '_na_illustrations', true );
	$a_part_charge   = isset( $d_charge['illustrations'][2] ) ? Notice_Archeomed_Pactols::figure_a_part( $d_charge['illustrations'][2] ) : null;
	if ( is_array( $fichiers_charge ) && 3 === count( $fichiers_charge )
		&& Notice_Archeomed_Pactols::est_a_part( $fichiers_charge[2] ) && is_file( $fichiers_charge[2] )
		&& ! Notice_Archeomed_Pactols::est_a_part( $fichiers_charge[1] )
		&& null !== $a_part_charge && false !== strpos( (string) $a_part_charge['lien'], 'essai-' . $m_lieu[1] ) ) {
		++$a_part_vues;
	} else {
		$a_part_fautives[] = $id;
	}
}
echo 'Figures envoyées à part : ', $a_part_vues, ' sur ', $a_part_attendues, "\n";
if ( $a_part_vues !== $a_part_attendues || ! empty( $a_part_fautives ) ) {
	$echecs[] = 'Figures envoyées à part mal rangées : ' . $a_part_vues . ' sur ' . $a_part_attendues . ' (notices ' . implode( ',', $a_part_fautives ) . ').';
}

$lignes   = is_file( $journal ) ? file( $journal, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : array();
$rediges  = array();
$doubles  = array();
foreach ( $lignes as $ligne ) {
	list( $vers, $sujet ) = array_pad( explode( "\t", $ligne, 2 ), 2, '' );
	// L'alerte des liens à télécharger part en plus de la notice : elle se
	// compte à part.
	if ( 0 === strpos( $sujet, 'ATTENTION' ) ) {
		$alertes = isset( $alertes ) ? $alertes + 1 : 1;
		continue;
	}
	if ( false !== strpos( $vers, 'redaction@example.org' ) && preg_match( '/réf\. ([A-Z0-9]{6})/u', $sujet, $m ) ) {
		$rediges[ $m[1] ] = isset( $rediges[ $m[1] ] ) ? $rediges[ $m[1] ] + 1 : 1;
	}
}
foreach ( $rediges as $ref => $fois ) {
	if ( $fois > 1 ) {
		$doubles[] = $ref . ' ×' . $fois;
	}
}
echo 'Courriels : ', count( $lignes ), ' — notices distinctes adressées à la rédaction : ', count( $rediges ), "\n";
if ( count( $rediges ) !== $attendu ) {
	$echecs[] = count( $rediges ) . ' notices ont atteint la rédaction pour ' . $attendu . ' dépôts.';
}
$alertes = isset( $alertes ) ? $alertes : 0;
echo 'Alertes des liens à télécharger : ', $alertes, "\n";
if ( $alertes !== $a_part_attendues ) {
	$echecs[] = $alertes . ' alertes de liens à télécharger pour ' . $a_part_attendues . ' notices qui en portent un.';
}
if ( ! empty( $doubles ) ) {
	$echecs[] = 'Courriels en double à la rédaction : ' . implode( ', ', $doubles );
}

if ( ! empty( $echecs ) ) {
	foreach ( $echecs as $e ) {
		echo '::error::', $e, "\n";
	}
	exit( 1 );
}
echo "La charge passe : aucune notice perdue, aucune doublée, toutes expédiées.\n";
