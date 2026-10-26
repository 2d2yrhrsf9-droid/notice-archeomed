<?php
/**
 * Dépose N notices en même temps sur un WordPress d'essai, comme le feraient
 * N chercheurs qui répondent le même matin à l'envoi de la campagne.
 *
 * Usage : php charge.php <adresse du site> <nombre de notices>
 *
 * Chaque dépôt porte deux figures (un JPEG de plusieurs centaines de Ko, et un TIFF avec les deux JPEG que le navigateur en aurait tirés), un
 * texte de quatre cents mots et une adresse de responsable qui lui est propre.
 * Le script lit la page du formulaire comme le ferait un navigateur — jeton,
 * heure d'affichage, rubrique et nature offertes —, attend les trois secondes
 * du délai minimal, puis lance tous les envois ensemble. Il rend un code de
 * sortie non nul si un envoi n'est pas accepté ; ce qui arrive ensuite aux
 * notices (file, courriels) se vérifie à part, par charge-verification.php.
 *
 * À n'utiliser que sur un site d'essai, protection suspendue (NA_PROTECTION).
 */

$site = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';
$n    = isset( $argv[2] ) ? (int) $argv[2] : 25;
if ( '' === $site || $n < 1 ) {
	fwrite( STDERR, "Usage : php charge.php <adresse> <nombre>\n" );
	exit( 2 );
}

// La page du formulaire, telle qu'un visiteur la reçoit.
$page = file_get_contents( $site . '/?page_id=' . ( getenv( 'NA_PAGE_ID' ) ?: '' ) );
if ( false === $page || false === strpos( $page, 'id="na-form"' ) ) {
	fwrite( STDERR, "La page ne porte pas le formulaire.\n" );
	exit( 2 );
}
$dom = new DOMDocument();
@$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $page );
$xp = new DOMXPath( $dom );
$valeur = function ( $requete ) use ( $xp ) {
	$noeud = $xp->query( $requete )->item( 0 );
	return $noeud ? $noeud->getAttribute( 'value' ) : '';
};
$jeton = $valeur( '//input[@name="notice_archeomed_nonce"]' );
$ts    = $valeur( '//input[@name="na_ts"]' );
$rubrique = $valeur( '//select[@id="na-rubrique"]/option[string-length(@value)>0][1]' );
$nature   = $valeur( '//input[@name="nature[]"][1]' );
if ( '' === $jeton || '' === $ts || '' === $rubrique || '' === $nature ) {
	fwrite( STDERR, "Page illisible : jeton, heure, rubrique ou nature manquent.\n" );
	exit( 2 );
}

// Deux figures, fabriquées une fois : du bruit, pour qu'elles pèsent.
$dossier = sys_get_temp_dir() . '/na-charge-' . getmypid();
@mkdir( $dossier );
$figures = array();
for ( $f = 1; $f <= 2; $f++ ) {
	$img = imagecreatetruecolor( 1600, 1100 );
	mt_srand( $f );
	for ( $i = 0; $i < 4000; $i++ ) {
		imagefilledrectangle( $img, mt_rand( 0, 1590 ), mt_rand( 0, 1090 ), mt_rand( 0, 1599 ), mt_rand( 0, 1099 ),
			imagecolorallocate( $img, mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) ) );
	}
	$chemin = $dossier . '/figure-' . $f . '.jpg';
	imagejpeg( $img, $chemin, 88 );
	$figures[ $f ] = $chemin;
}

// La figure 2 est un TIFF, comme en envoient les auteurs : un vrai fichier TIFF
// minimal (non compressé, 16 × 16), que PHP reconnaît pour tel. Le serveur de
// l'essai n'a pas Imagick ; le navigateur de l'auteur aurait donc converti ce
// TIFF en deux JPEG, que le script joint de même (haute et basse définition).
function tiff_minimal() {
	$donnees = '';
	for ( $y = 0; $y < 16; $y++ ) {
		for ( $x = 0; $x < 16; $x++ ) {
			$donnees .= str_repeat( chr( ( $x * 16 ) % 256 ), 3 );
		}
	}
	$entrees = array( array( 256, 3, 1, 16 ), array( 257, 3, 1, 16 ), array( 258, 3, 3, 0 ), array( 259, 3, 1, 1 ),
		array( 262, 3, 1, 2 ), array( 273, 4, 1, 0 ), array( 277, 3, 1, 3 ), array( 278, 3, 1, 16 ), array( 279, 4, 1, strlen( $donnees ) ) );
	$n          = count( $entrees );
	$bits       = 8 + 2 + $n * 12 + 4;
	$debut      = $bits + 6;
	$sortie     = "II*\0" . pack( 'V', 8 ) . pack( 'v', $n );
	foreach ( $entrees as $e ) {
		$valeur = 258 === $e[0] ? $bits : ( 273 === $e[0] ? $debut : $e[3] );
		$sortie .= ( 3 === $e[1] && 258 !== $e[0] ) ? pack( 'vvVvv', $e[0], $e[1], $e[2], $valeur, 0 ) : pack( 'vvVV', $e[0], $e[1], $e[2], $valeur );
	}
	return $sortie . pack( 'V', 0 ) . pack( 'vvv', 8, 8, 8 ) . $donnees;
}
file_put_contents( $dossier . '/figure-2.tif', tiff_minimal() );
@unlink( $figures[2] );
$figures[2] = $dossier . '/figure-2.tif';
foreach ( array( 'hr' => array( 1181, 787 ), 'br' => array( 1600, 1066 ) ) as $famille => $dim ) {
	$img = imagecreatetruecolor( $dim[0], $dim[1] );
	mt_srand( 9 );
	for ( $i = 0; $i < 600; $i++ ) {
		imagefilledrectangle( $img, mt_rand( 0, $dim[0] - 10 ), mt_rand( 0, $dim[1] - 10 ), mt_rand( 0, $dim[0] - 1 ), mt_rand( 0, $dim[1] - 1 ),
			imagecolorallocate( $img, mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) ) );
	}
	imagejpeg( $img, $dossier . '/conv-' . $famille . '.jpg', 88 );
	imagedestroy( $img );
}

$paragraphe = 'Les vestiges mis au jour comprennent un bâtiment sur solins de pierre, un fossé comblé de rejets domestiques et plusieurs fosses dont le mobilier céramique date du haut Moyen Âge. ';
$texte      = '<p>' . str_repeat( $paragraphe, 10 ) . '</p><p>' . str_repeat( $paragraphe, 8 ) . '</p>';

sleep( 4 );   // le délai minimal de trois secondes entre l'affichage et l'envoi

$multi   = curl_multi_init();
$poignees = array();
for ( $i = 1; $i <= $n; $i++ ) {
	$champs = array(
		'notice_archeomed_envoi' => '1', 'notice_archeomed_nonce' => $jeton, 'na_ts' => $ts, 'na_website' => '',
		'rubrique_principale' => $rubrique,
		'commune[0]' => 'Caen', 'departement' => 'Calvados', 'lieu_dit' => 'ESSAI-CHARGE ' . $i, 'annee' => '2025',
		'nature[0]' => $nature, 'organisme[0]' => 'Inrap',
		'resp_prenom' => 'Essai', 'resp_nom' => 'Charge' . $i, 'resp_email' => 'charge' . $i . '@example.org', 'resp_inst' => 'Inrap',
		'texte_notice' => $texte,
	);
	foreach ( array( 1, 2 ) as $f ) {
		$champs[ 'illustrations[' . ( $f - 1 ) . ']' ] = 2 === $f
			? new CURLFile( $figures[ $f ], 'image/tiff', 'figure-' . $i . '-' . $f . '.tif' )
			: new CURLFile( $figures[ $f ], 'image/jpeg', 'figure-' . $i . '-' . $f . '.jpg' );
		$champs[ 'illus_titre[' . ( $f - 1 ) . ']' ]   = 'Vue ' . $f . ' de l’opération ' . $i;
		$champs[ 'illus_legende[' . ( $f - 1 ) . ']' ] = '';
		$champs[ 'illus_credits[' . ( $f - 1 ) . ']' ] = '© Essai ' . $i . ', Inrap';
		$champs[ 'illus_type[' . ( $f - 1 ) . ']' ]    = 1 === $f ? 'photo' : 'plan';
		$champs[ 'illus_alt[' . ( $f - 1 ) . ']' ]     = 1 === $f ? 'Mur en moellons vu du nord' : 'Deux bâtiments à l’ouest d’un enclos fossoyé';
	}
	$champs['na_conv_hr_2'] = new CURLFile( $dossier . '/conv-hr.jpg', 'image/jpeg', 'figure-' . $i . '-2-hr.jpg' );
	$champs['na_conv_br_2'] = new CURLFile( $dossier . '/conv-br.jpg', 'image/jpeg', 'figure-' . $i . '-2-br.jpg' );
	$c = curl_init( $site . '/' );
	curl_setopt_array( $c, array(
		CURLOPT_POST => true, CURLOPT_POSTFIELDS => $champs, CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 180,
	) );
	curl_multi_add_handle( $multi, $c );
	$poignees[ $i ] = $c;
}

$debut = microtime( true );
do {
	curl_multi_exec( $multi, $actif );
	if ( $actif ) {
		curl_multi_select( $multi, 1.0 );
	}
} while ( $actif );
$duree = microtime( true ) - $debut;

$acceptes = 0;
$refus    = array();
foreach ( $poignees as $i => $c ) {
	$code = curl_getinfo( $c, CURLINFO_HTTP_CODE );
	$tete = substr( (string) curl_multi_getcontent( $c ), 0, (int) curl_getinfo( $c, CURLINFO_HEADER_SIZE ) );
	$lieu = preg_match( '/^Location:\s*(.+)$/mi', $tete, $m ) ? trim( $m[1] ) : '';
	if ( $code >= 300 && $code < 400 && false !== strpos( $lieu, 'notice_envoyee=1' ) ) {
		++$acceptes;
	} else {
		$refus[ $i ] = $code . ' ' . $lieu . ' ' . curl_error( $c );
	}
	curl_multi_remove_handle( $multi, $c );
}
foreach ( array_merge( $figures, array( $dossier . '/conv-hr.jpg', $dossier . '/conv-br.jpg' ) ) as $chemin ) {
	@unlink( $chemin );
}
@rmdir( $dossier );

printf( "%d envois sur %d acceptés en %.1f s (%.1f s par envoi en moyenne).\n", $acceptes, $n, $duree, $duree / $n );
foreach ( $refus as $i => $raison ) {
	printf( "  refusé : notice %d — %s\n", $i, $raison );
}
exit( $acceptes === $n ? 0 : 1 );
