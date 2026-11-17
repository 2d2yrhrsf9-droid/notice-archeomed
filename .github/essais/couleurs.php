<?php
/**
 * Les couleurs des aperçus, éprouvées sur un ImageMagick véritable : le
 * poste qui prépare n'a ni PHP ni Imagick.
 *
 *     php .github/essais/couleurs.php /tmp/lineaire.icc
 *
 * Le profil d'essai (« profil-icc lineaire ») a les primaires du sRGB sans
 * sa courbe : un gris 128 qui le porte vaut 188 en sRGB. S'il ressort à 128,
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

// Un RVB qui porte un autre profil que le sRGB.
$im = $image( 'rgb(128,128,128)', null, $lineaire );
$vers_le_srgb->invoke( null, $im );
$verifier( abs( $rouge( $im ) - 188 ) <= 2, 'un gris 128 du profil linéaire devient 188 (lu : ' . $rouge( $im ) . ')' );
$verifier( false !== stripos( $nom( $im ), 'srgb' ), 'il porte ensuite le profil sRGB (lu : ' . $nom( $im ) . ')' );

// Un sRGB n'est pas touché.
$im = $image( 'rgb(128,128,128)', null, $srgb );
$vers_le_srgb->invoke( null, $im );
$verifier( 128 === $rouge( $im ), 'un sRGB reste tel quel (lu : ' . $rouge( $im ) . ')' );

// Un RVB sans profil n'est pas touché.
$im = $image( 'rgb(128,128,128)' );
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

if ( $echecs ) {
	echo "\n" . count( $echecs ) . " échec(s).\n";
	exit( 1 );
}
echo "\nTout est juste.\n";
