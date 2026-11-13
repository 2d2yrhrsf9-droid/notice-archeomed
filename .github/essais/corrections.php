<?php
/**
 * Corrige en même temps, par leur lien, les notices que charge.php a
 * déposées, comme le feraient leurs auteurs relisant leur copie le même
 * matin.
 *
 * Usage : php corrections.php <adresse du site> <fichier des liens> <disputées>
 *
 * Le fichier porte un lien de correction par ligne. Chaque correction ouvre
 * la page comme un navigateur — le formulaire rempli de la notice, ses
 * figures gardées —, change le lieu-dit, garde la figure 1, remplace la
 * figure 2 par un TIFF (avec les deux JPEG que le navigateur en aurait
 * tirés) et ajoute une figure 3 en JPEG.
 *
 * Les <disputées> premières notices sont corrigées deux fois à la même
 * seconde, depuis deux pages ouvertes sur la même version : une seule
 * correction doit passer, l'autre revenir au formulaire (« conflit »).
 * Le code de sortie est non nul si ce n'est pas exactement ce qui arrive.
 *
 * À n'utiliser que sur un site d'essai, protection suspendue (NA_PROTECTION).
 */

$site     = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';
$liens    = isset( $argv[2] ) && is_file( $argv[2] ) ? file( $argv[2], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : array();
$disputes = isset( $argv[3] ) ? (int) $argv[3] : 5;
if ( '' === $site || empty( $liens ) ) {
	fwrite( STDERR, "Usage : php corrections.php <adresse> <fichier des liens> <disputées>\n" );
	exit( 2 );
}

/**
 * Ce qu'un navigateur enverrait du formulaire tel que la page le montre :
 * les champs non désactivés, les cases cochées seulement, l'option choisie
 * de chaque liste. Les « nom[] » prennent leur rang, un tableau PHP ne
 * pouvant porter deux fois la même clef.
 */
function champs_du_formulaire( $page ) {
	$dom = new DOMDocument();
	@$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $page );
	$xp     = new DOMXPath( $dom );
	$champs = array();
	$rangs  = array();
	$poser  = function ( $nom, $valeur ) use ( &$champs, &$rangs ) {
		if ( '[]' === substr( $nom, -2 ) ) {
			$base         = substr( $nom, 0, -2 );
			$rangs[ $base ] = isset( $rangs[ $base ] ) ? $rangs[ $base ] + 1 : 0;
			$nom          = $base . '[' . $rangs[ $base ] . ']';
		}
		$champs[ $nom ] = $valeur;
	};
	foreach ( $xp->query( '//form[@id="na-form"]//input[@name]' ) as $e ) {
		$type = strtolower( $e->getAttribute( 'type' ) );
		if ( $e->hasAttribute( 'disabled' ) || in_array( $type, array( 'file', 'submit', 'button', 'image', 'reset' ), true ) ) {
			continue;
		}
		if ( in_array( $type, array( 'checkbox', 'radio' ), true ) && ! $e->hasAttribute( 'checked' ) ) {
			continue;
		}
		$poser( $e->getAttribute( 'name' ), $e->hasAttribute( 'value' ) ? $e->getAttribute( 'value' ) : 'on' );
	}
	foreach ( $xp->query( '//form[@id="na-form"]//textarea[@name]' ) as $e ) {
		if ( ! $e->hasAttribute( 'disabled' ) ) {
			$poser( $e->getAttribute( 'name' ), $e->textContent );
		}
	}
	foreach ( $xp->query( '//form[@id="na-form"]//select[@name]' ) as $e ) {
		if ( $e->hasAttribute( 'disabled' ) ) {
			continue;
		}
		$choisies = $xp->query( './/option[@selected]', $e );
		if ( 0 === $choisies->length && ! $e->hasAttribute( 'multiple' ) ) {
			$choisies = $xp->query( './/option[1]', $e );
		}
		foreach ( $choisies as $o ) {
			$poser( $e->getAttribute( 'name' ), $o->hasAttribute( 'value' ) ? $o->getAttribute( 'value' ) : $o->textContent );
		}
	}
	return $champs;
}

// Le TIFF de remplacement et ses deux JPEG, comme dans charge.php.
function tiff_minimal() {
	$donnees = '';
	for ( $y = 0; $y < 16; $y++ ) {
		for ( $x = 0; $x < 16; $x++ ) {
			$donnees .= str_repeat( chr( ( $y * 16 ) % 256 ), 3 );
		}
	}
	$entrees = array( array( 256, 3, 1, 16 ), array( 257, 3, 1, 16 ), array( 258, 3, 3, 0 ), array( 259, 3, 1, 1 ),
		array( 262, 3, 1, 2 ), array( 273, 4, 1, 0 ), array( 277, 3, 1, 3 ), array( 278, 3, 1, 16 ), array( 279, 4, 1, strlen( $donnees ) ) );
	$n      = count( $entrees );
	$bits   = 8 + 2 + $n * 12 + 4;
	$debut  = $bits + 6;
	$sortie = "II*\0" . pack( 'V', 8 ) . pack( 'v', $n );
	foreach ( $entrees as $e ) {
		$valeur  = 258 === $e[0] ? $bits : ( 273 === $e[0] ? $debut : $e[3] );
		$sortie .= ( 3 === $e[1] && 258 !== $e[0] ) ? pack( 'vvVvv', $e[0], $e[1], $e[2], $valeur, 0 ) : pack( 'vvVV', $e[0], $e[1], $e[2], $valeur );
	}
	return $sortie . pack( 'V', 0 ) . pack( 'vvv', 8, 8, 8 ) . $donnees;
}
$dossier = sys_get_temp_dir() . '/na-corrections-' . getmypid();
@mkdir( $dossier );
file_put_contents( $dossier . '/remplacement.tif', tiff_minimal() );
foreach ( array( 'hr' => array( 1181, 787 ), 'br' => array( 1600, 1066 ), 'neuve' => array( 1400, 950 ) ) as $nom => $dim ) {
	$img = imagecreatetruecolor( $dim[0], $dim[1] );
	mt_srand( 17 );
	for ( $i = 0; $i < 600; $i++ ) {
		imagefilledrectangle( $img, mt_rand( 0, $dim[0] - 10 ), mt_rand( 0, $dim[1] - 10 ), mt_rand( 0, $dim[0] - 1 ), mt_rand( 0, $dim[1] - 1 ),
			imagecolorallocate( $img, mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) ) );
	}
	imagejpeg( $img, $dossier . '/' . $nom . '.jpg', 88 );
	imagedestroy( $img );
}

// Chaque page ouverte, puis chaque correction préparée : deux pour les
// notices disputées, ouvertes l'une après l'autre sur la même version.
$envois = array();
foreach ( $liens as $n => $lien ) {
	$fois = $n < $disputes ? 2 : 1;
	for ( $j = 0; $j < $fois; $j++ ) {
		$page = file_get_contents( $lien );
		if ( false === $page || false === strpos( $page, 'id="na-form"' ) ) {
			fwrite( STDERR, "Le lien $lien ne rouvre pas le formulaire.\n" );
			exit( 2 );
		}
		$champs = champs_du_formulaire( $page );
		foreach ( array( 'reprise_jeton', 'corrige_version', 'notice_archeomed_nonce', 'na_ts', 'lieu_dit', 'texte_notice', 'resp_email', 'garde_titre[1]', 'garde_titre[2]' ) as $attendu ) {
			if ( ! isset( $champs[ $attendu ] ) || '' === $champs[ $attendu ] ) {
				fwrite( STDERR, "Le formulaire rouvert par $lien n'a pas « $attendu » rempli.\n" );
				exit( 2 );
			}
		}
		$champs['notice_archeomed_envoi'] = '1';
		$champs['lieu_dit']              .= ' (corrigé)';
		$champs['garde_action[1]']        = 'garder';
		$champs['garde_action[2]']        = 'remplacer';
		$champs['garde_fichier_2']        = new CURLFile( $dossier . '/remplacement.tif', 'image/tiff', 'remplacement-' . $n . '.tif' );
		$champs['na_conv_garde_hr_2']     = new CURLFile( $dossier . '/hr.jpg', 'image/jpeg', 'remplacement-' . $n . '-hr.jpg' );
		$champs['na_conv_garde_br_2']     = new CURLFile( $dossier . '/br.jpg', 'image/jpeg', 'remplacement-' . $n . '-br.jpg' );
		$champs['illustrations[0]']       = new CURLFile( $dossier . '/neuve.jpg', 'image/jpeg', 'ajout-' . $n . '.jpg' );
		$champs['illus_titre[0]']         = 'Figure ajoutée';
		$champs['illus_legende[0]']       = '';
		$champs['illus_credits[0]']       = '© Essai, Inrap';
		$champs['illus_type[0]']          = 'photo';
		$champs['illus_alt[0]']           = 'Sondage vu de l’est';
		$envois[] = array( 'notice' => $n, 'champs' => $champs );
	}
}

sleep( 4 );   // le délai minimal de trois secondes entre l'affichage et l'envoi

$multi = curl_multi_init();
foreach ( $envois as $i => $envoi ) {
	$c = curl_init( $site . '/' );
	curl_setopt_array( $c, array(
		CURLOPT_POST => true, CURLOPT_POSTFIELDS => $envoi['champs'], CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 180,
	) );
	curl_multi_add_handle( $multi, $c );
	$envois[ $i ]['poignee'] = $c;
}
$debut = microtime( true );
do {
	curl_multi_exec( $multi, $actif );
	if ( $actif ) {
		curl_multi_select( $multi, 1.0 );
	}
} while ( $actif );
$duree = microtime( true ) - $debut;

$passees  = array();
$conflits = 0;
$refus    = array();
foreach ( $envois as $envoi ) {
	$c    = $envoi['poignee'];
	$code = curl_getinfo( $c, CURLINFO_HTTP_CODE );
	$tete = substr( (string) curl_multi_getcontent( $c ), 0, (int) curl_getinfo( $c, CURLINFO_HEADER_SIZE ) );
	$lieu = preg_match( '/^Location:\s*(.+)$/mi', $tete, $m ) ? trim( $m[1] ) : '';
	if ( $code >= 300 && $code < 400 && false !== strpos( $lieu, 'notice_corrigee=1' ) ) {
		$passees[ $envoi['notice'] ] = isset( $passees[ $envoi['notice'] ] ) ? $passees[ $envoi['notice'] ] + 1 : 1;
	} elseif ( false !== strpos( $lieu, 'notice_erreur=conflit' ) ) {
		++$conflits;
	} else {
		$refus[] = 'notice ' . ( $envoi['notice'] + 1 ) . ' — ' . $code . ' ' . $lieu . ' ' . curl_error( $c );
	}
	curl_multi_remove_handle( $multi, $c );
}
foreach ( glob( $dossier . '/*' ) as $chemin ) {
	@unlink( $chemin );
}
@rmdir( $dossier );

$echecs = array();
if ( count( $passees ) !== count( $liens ) ) {
	$echecs[] = count( $passees ) . ' notices corrigées sur ' . count( $liens ) . '.';
}
foreach ( $passees as $n => $fois ) {
	if ( $fois > 1 ) {
		$echecs[] = 'La notice ' . ( $n + 1 ) . ' a pris ' . $fois . ' corrections lues sur la même version.';
	}
}
if ( $conflits !== min( $disputes, count( $liens ) ) ) {
	$echecs[] = $conflits . ' corrections renvoyées pour conflit, ' . min( $disputes, count( $liens ) ) . ' attendues.';
}
printf( "%d corrections envoyées en %.1f s : %d notices corrigées, %d renvoyées pour conflit.\n",
	count( $envois ), $duree, count( $passees ), $conflits );
foreach ( $refus as $raison ) {
	printf( "  refusée : %s\n", $raison );
}
foreach ( $echecs as $e ) {
	printf( "  %s\n", $e );
}
exit( empty( $echecs ) && empty( $refus ) ? 0 : 1 );
