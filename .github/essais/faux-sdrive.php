<?php
/**
 * Un sDrive d'essai : ce que le plugin demande au WebDAV de Nextcloud, pas
 * davantage, servi depuis /tmp/sdrive.
 *
 * Usage : php -S 127.0.0.1:8081 faux-sdrive.php
 *
 * Répond à PROPFIND (profondeur 0 ou 1, taille et nature de chaque entrée),
 * MKCOL, PUT, GET et DELETE sous /remote.php/dav/files/essai/. Toute
 * authentification Basic passe ; son absence vaut 401, comme sur sDrive.
 * Chaque requête est notée dans /tmp/sdrive.log.
 *
 * Une panne se joue en écrivant dans /tmp/sdrive.panne une méthode et un
 * code : « PUT 507 » refuse les dépôts comme un compte plein, « * 401 »
 * refuse tout comme une liaison révoquée.
 */

const RACINE  = '/tmp/sdrive';
const PREFIXE = '/remote.php/dav/files/essai';

$methode = $_SERVER['REQUEST_METHOD'];
$chemin  = rawurldecode( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
file_put_contents( '/tmp/sdrive.log', $methode . ' ' . $chemin . "\n", FILE_APPEND | LOCK_EX );

$auth = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? (string) $_SERVER['HTTP_AUTHORIZATION'] : '';
if ( 0 !== stripos( $auth, 'Basic ' ) && ! isset( $_SERVER['PHP_AUTH_USER'] ) ) {
	http_response_code( 401 );
	header( 'WWW-Authenticate: Basic realm="essai"' );
	exit;
}
$panne = is_file( '/tmp/sdrive.panne' ) ? (string) file_get_contents( '/tmp/sdrive.panne' ) : '';
if ( preg_match( '/^(\S+)\s+(\d{3})/', $panne, $p ) && ( '*' === $p[1] || $methode === $p[1] ) ) {
	http_response_code( (int) $p[2] );
	exit;
}
if ( 0 !== strpos( $chemin, PREFIXE ) ) {
	http_response_code( 404 );
	exit;
}
$relatif = trim( substr( $chemin, strlen( PREFIXE ) ), '/' );
if ( false !== strpos( '/' . $relatif . '/', '/../' ) ) {
	http_response_code( 400 );
	exit;
}
if ( ! is_dir( RACINE ) ) {
	mkdir( RACINE, 0777, true );
}
$local = RACINE . ( '' === $relatif ? '' : '/' . $relatif );

/** Une réponse PROPFIND pour une entrée. */
function entree( $href, $local ) {
	$dossier = is_dir( $local );
	$xml     = '<d:response><d:href>' . htmlspecialchars( $href, ENT_XML1 ) . '</d:href><d:propstat><d:prop>';
	$xml    .= $dossier ? '<d:resourcetype><d:collection/></d:resourcetype>'
		: '<d:resourcetype/><d:getcontentlength>' . filesize( $local ) . '</d:getcontentlength>';
	return $xml . '</d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>';
}

/** Le href d'un chemin relatif, segment par segment, comme Nextcloud l'écrit. */
function href( $relatif, $dossier ) {
	$segments = array_filter( explode( '/', $relatif ), 'strlen' );
	return PREFIXE . '/' . implode( '/', array_map( 'rawurlencode', $segments ) ) . ( $dossier && ! empty( $segments ) ? '/' : '' );
}

clearstatcache();
switch ( $methode ) {
	case 'PROPFIND':
		if ( ! file_exists( $local ) ) {
			http_response_code( 404 );
			exit;
		}
		$xml = '<?xml version="1.0"?><d:multistatus xmlns:d="DAV:">' . entree( href( $relatif, is_dir( $local ) ), $local );
		if ( is_dir( $local ) && isset( $_SERVER['HTTP_DEPTH'] ) && '1' === $_SERVER['HTTP_DEPTH'] ) {
			foreach ( scandir( $local ) as $nom ) {
				if ( '.' !== $nom && '..' !== $nom ) {
					$xml .= entree( href( $relatif . '/' . $nom, is_dir( $local . '/' . $nom ) ), $local . '/' . $nom );
				}
			}
		}
		http_response_code( 207 );
		header( 'Content-Type: application/xml; charset=utf-8' );
		echo $xml, '</d:multistatus>';
		exit;

	case 'MKCOL':
		if ( file_exists( $local ) ) {
			http_response_code( 405 );
			exit;
		}
		if ( ! is_dir( dirname( $local ) ) ) {
			http_response_code( 409 );
			exit;
		}
		mkdir( $local );
		http_response_code( 201 );
		exit;

	case 'PUT':
		if ( ! is_dir( dirname( $local ) ) ) {
			http_response_code( 409 );
			exit;
		}
		$existait = file_exists( $local );
		$corps    = (string) file_get_contents( 'php://input' );
		if ( isset( $_SERVER['HTTP_OC_CHECKSUM'] ) && preg_match( '/^SHA1:([0-9a-f]{40})$/i', $_SERVER['HTTP_OC_CHECKSUM'], $m )
			&& strtolower( $m[1] ) !== sha1( $corps ) ) {
			http_response_code( 400 );
			exit;
		}
		file_put_contents( $local, $corps, LOCK_EX );
		http_response_code( $existait ? 204 : 201 );
		exit;

	case 'GET':
		if ( ! is_file( $local ) ) {
			http_response_code( 404 );
			exit;
		}
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Length: ' . filesize( $local ) );
		readfile( $local );
		exit;

	case 'DELETE':
		if ( ! file_exists( $local ) ) {
			http_response_code( 404 );
			exit;
		}
		if ( is_dir( $local ) ) {
			exec( 'rm -rf ' . escapeshellarg( $local ) );
		} else {
			unlink( $local );
		}
		http_response_code( 204 );
		exit;
}
http_response_code( 405 );
