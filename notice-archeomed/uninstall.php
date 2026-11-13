<?php
/**
 * Suppression de l'extension depuis l'administration de WordPress.
 *
 * Les réglages disparaissent toujours, secrets compris : le mot de passe du
 * relais de courriel, le jeton GitHub, la clé Turnstile et l'accès à sDrive
 * n'ont rien à faire
 * dans une base dont l'extension est partie.
 *
 * Les notices et leurs fichiers, eux, sont le travail de la revue : ils
 * restent, sauf demande expresse. Pour les supprimer avec l'extension, il
 * faut le déclarer avant de la supprimer, dans wp-config.php :
 *     define( 'NA_SUPPRIMER_LES_NOTICES', true );
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// La liaison à sDrive se révoque chez sDrive avant de s'oublier ici : sans
// quoi un mot de passe d'application resterait valide, que plus rien ne
// sait retirer.
require_once __DIR__ . '/class-notice-archeomed-sdrive.php';
$sdrive = Notice_Archeomed_Sdrive::liaison();
if ( ! empty( $sdrive['compte'] ) ) {
	Notice_Archeomed_Sdrive::delier();
}

foreach ( array(
	'notice_archeomed_options',
	Notice_Archeomed_Sdrive::OPTION,
	'notice_archeomed_accessibilite',
	'notice_archeomed_candidats',
	'na_etat_des_donnees',
	'na_essais_de_poids',
) as $option ) {
	delete_option( $option );
}

// Les attentes qui traînent : saisies gardées après un refus, compteurs
// horaires, dossiers en préparation, diagnostics.
global $wpdb;
$wpdb->query( $wpdb->prepare(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
	$wpdb->esc_like( '_transient_na_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_na_' ) . '%'
) );

if ( defined( 'NA_SUPPRIMER_LES_NOTICES' ) && NA_SUPPRIMER_LES_NOTICES ) {
	$notices = get_posts( array(
		'post_type'   => 'na_notice',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	) );
	foreach ( $notices as $id ) {
		wp_delete_post( (int) $id, true );
	}
	$depot   = wp_upload_dir();
	$dossier = trailingslashit( $depot['basedir'] ) . 'notice-archeomed';
	$reel    = realpath( $dossier );
	// Seul le dossier de l'extension, sous celui des téléversements : un
	// lien symbolique ou un chemin inattendu ne mène pas ailleurs.
	if ( false !== $reel && 0 === strpos( $reel, realpath( $depot['basedir'] ) . DIRECTORY_SEPARATOR )
		&& 'notice-archeomed' === basename( $reel ) ) {
		$parcours = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $reel, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $parcours as $element ) {
			if ( $element->isLink() || $element->isFile() ) {
				@unlink( $element->getPathname() );
			} else {
				@rmdir( $element->getPathname() );
			}
		}
		@rmdir( $reel );
	}
}
