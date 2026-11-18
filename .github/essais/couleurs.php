<?php
/**
 * Les couleurs des aperçus, éprouvées sur un ImageMagick véritable : le
 * poste qui prépare n'a ni PHP ni Imagick.
 *
 *     php .github/essais/couleurs.php /tmp/lineaire.icc
 *
 * Le profil d'essai (« profil-icc lineaire ») a les primaires du sRGB sans
 * sa courbe : un rouge 128 qui le porte vaut 188 en sRGB. S'il ressort à 128,
 * le profil n'a pas été lu. On vérifie aussi qu'un sRGB n'est pas touché,
 * qu'un CMJN sans profil passe encore au sRGB, et qu'un profil qui ne
 * convient pas à l'image ne l'empêche pas.
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__, 2 ) . '/notice-archeomed/class-notice-archeomed-paquet.php';

$echecs = array();
$verifier = function ( $condition, $quoi ) use ( &$echecs ) {
	echo ( $condition ? 'ok    ' : 'ÉCHEC ' ) . $quoi . "\n";
	if ( ! $condition ) {
		$echecs[] = $quoi;
		// En annotation, l'échec se lit sur la page de la vérification
		// sans ouvrir le journal.
		echo '::error::' . $quoi . "\n";
	}
};

$lineaire = (string) file_get_contents( $argv[1] );
$srgb     = (string) file_get_contents( dirname( __DIR__, 2 ) . '/notice-archeomed/assets/srgb.icc' );

// Le nom des profils.
$verifier( 'sRGB IEC61966-2.1' === Notice_Archeomed_Paquet::description_icc( $srgb ), 'le profil du plugin se nomme sRGB' );
$verifier( 'Essai lineaire' === Notice_Archeomed_Paquet::description_icc( $lineaire ), 'le profil d\'essai se nomme' );
$verifier( '' === Notice_Archeomed_Paquet::description_icc( '' ), 'un profil vide n\'a pas de nom' );
$verifier( '' === Notice_Archeomed_Paquet::description_icc( substr( $srgb, 0, 140 ) ), 'un profil tronqué n\'a pas de nom' );
$verifier( '' === Notice_Archeomed_Paquet::description_icc( str_repeat( "\xff", 600 ) ), 'du bruit n\'a pas de nom' );

// Le moteur de couleur d'ImageMagick.
$options = (array) Imagick::getConfigureOptions( 'DELEGATES' );
$verifier( isset( $options['DELEGATES'] ) && preg_match( '/\blcms/i', $options['DELEGATES'] ), 'ImageMagick a Little CMS' );

$vers_le_srgb = new ReflectionMethod( 'Notice_Archeomed_Paquet', 'vers_le_srgb' );
$vers_le_srgb->setAccessible( true );

$image = function ( $couleur, $espace = null, $profil = null ) {
	$im = new Imagick();
	$im->newImage( 4, 4, new ImagickPixel( $couleur ) );
	if ( null !== $espace ) {
		$im->setImageColorspace( $espace );
	}
	if ( null !== $profil ) {
		$im->setImageProfile( 'icc', $profil );
	}
	$im->setImageFormat( 'tiff' );
	// Relue comme un fichier déposé, et non telle qu'on l'a fabriquée.
	$relue = new Imagick();
	$relue->readImageBlob( $im->getImageBlob() );
	return $relue;
};
$rouge = function ( $im ) {
	$c = $im->getImagePixelColor( 1, 1 )->getColor();
	return (int) $c['r'];
};
$nom = function ( $im ) {
	$p = $im->getImageProfiles( 'icc', true );
	return isset( $p['icc'] ) ? Notice_Archeomed_Paquet::description_icc( $p['icc'] ) : '(aucun)';
};

// Un RVB qui porte un autre profil que le sRGB. La couleur n'est pas un
// gris : un gris pur se relit en niveaux de gris, qu'on ne convertit pas.
// Les primaires étant celles du sRGB, chaque canal se convertit seul : le
// rouge 128 devient 188.
$im = $image( 'rgb(128,64,200)', null, $lineaire );
$espace_lu = $im->getImageColorspace();
$vers_le_srgb->invoke( null, $im );
$verifier( abs( $rouge( $im ) - 188 ) <= 2, 'un rouge 128 du profil linéaire devient 188 (lu : ' . $rouge( $im ) . ', espace ' . $espace_lu . ')' );
$verifier( false !== stripos( $nom( $im ), 'srgb' ), 'il porte ensuite le profil sRGB (lu : ' . $nom( $im ) . ')' );

// Un sRGB n'est pas touché.
$im = $image( 'rgb(128,64,200)', null, $srgb );
$vers_le_srgb->invoke( null, $im );
$verifier( 128 === $rouge( $im ), 'un sRGB reste tel quel (lu : ' . $rouge( $im ) . ')' );

// Un RVB sans profil n'est pas touché.
$im = $image( 'rgb(128,64,200)' );
$vers_le_srgb->invoke( null, $im );
$verifier( 128 === $rouge( $im ), 'un RVB sans profil reste tel quel (lu : ' . $rouge( $im ) . ')' );

// Un CMJN sans profil passe encore au sRGB, par la formule.
$im = $image( 'cmyk(0,0,0,0)', Imagick::COLORSPACE_CMYK );
$cmjn = Imagick::COLORSPACE_CMYK === $im->getImageColorspace();
$vers_le_srgb->invoke( null, $im );
$verifier( $cmjn && Imagick::COLORSPACE_CMYK !== $im->getImageColorspace(), 'un CMJN sans profil passe au RVB' );
$verifier( $rouge( $im ) >= 250, 'le blanc du papier reste blanc (lu : ' . $rouge( $im ) . ')' );

// Un CMJN dont le profil ne lui convient pas : la formule, et plus de profil.
$im = $image( 'cmyk(0,0,0,0)', Imagick::COLORSPACE_CMYK, $lineaire );
$vers_le_srgb->invoke( null, $im );
$verifier( Imagick::COLORSPACE_CMYK !== $im->getImageColorspace(), 'un CMJN au profil faux passe au RVB' );
$verifier( '(aucun)' === $nom( $im ) || false !== stripos( $nom( $im ), 'srgb' ), 'et ne garde pas un profil qui ne le décrit plus (lu : ' . $nom( $im ) . ')' );

// La résolution d'un PDF. Une planche qui se déclare de 2 480 × 1 754
// points — la taille en pixels d'une A4 à 300 ppp écrite en points — se
// dessinait à 600 ppp en trois cents millions de pixels, et le serveur
// coupait l'export sur elle. Une A4 ordinaire garde ses 600 ppp.
$rendu_du_pdf = new ReflectionMethod( 'Notice_Archeomed_Paquet', 'rendu_du_pdf' );
$rendu_du_pdf->setAccessible( true );
$pdf = function ( $boites ) {
	$chemin = tempnam( sys_get_temp_dir(), 'na-pdf' ) . '.pdf';
	$corps  = "%PDF-1.4\n";
	foreach ( $boites as $boite ) {
		$corps .= "1 0 obj << /Type /Page /MediaBox [0 0 " . $boite . "] >> endobj\n";
	}
	file_put_contents( $chemin, $corps . "%%EOF\n" );
	return $chemin;
};
$geante = $pdf( array( '612 792', '2480 1754' ) );
$a4     = $pdf( array( '595 842' ) );
$hr     = $rendu_du_pdf->invoke( null, $geante, PHP_INT_MAX, 1200, false );
$pixels = ( 2480 * $hr / 72 ) * ( 1754 * $hr / 72 );
$verifier( $pixels <= Notice_Archeomed_Paquet::MEGAPIXELS_PDF * 1000000, 'une page géante ne passe pas le plafond de pixels (' . $hr . ' ppp, ' . round( $pixels / 1e6 ) . ' Mpx)' );
$br = $rendu_du_pdf->invoke( null, $geante, 2500, 150, true );
$verifier( 91 === $br, 'sa basse définition se dessine d\'après sa plus grande page (lu : ' . $br . ' ppp)' );
$verifier( 600 === $rendu_du_pdf->invoke( null, $a4, PHP_INT_MAX, 1200, false ), 'une A4 garde ses 600 ppp en haute définition' );
$verifier( 107 === $rendu_du_pdf->invoke( null, $a4, 1000, 150, true ), 'une A4 se dessine juste assez pour son aperçu' );
$verifier( 300 === $rendu_du_pdf->invoke( null, $a4, PHP_INT_MAX, 300, false ), 'une A4 suit la densité réglée' );
@unlink( $geante );
@unlink( $a4 );

if ( $echecs ) {
	echo "\n" . count( $echecs ) . " échec(s).\n";
	exit( 1 );
}
echo "\nTout est juste.\n";
