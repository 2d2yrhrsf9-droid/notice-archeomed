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

$lignes   = is_file( $journal ) ? file( $journal, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : array();
$rediges  = array();
$doubles  = array();
foreach ( $lignes as $ligne ) {
	list( $vers, $sujet ) = array_pad( explode( "\t", $ligne, 2 ), 2, '' );
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
