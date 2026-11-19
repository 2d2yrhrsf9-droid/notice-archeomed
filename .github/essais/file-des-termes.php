<?php
/**
 * Essais du plugin dans un WordPress véritable, sans réseau.
 *
 * Lancé par « wp eval-file » sur le site d'essai que la publication monte.
 * Ce que le contrôle de syntaxe ne peut pas voir — une fonction qui rend
 * autre chose que ce qu'on croit, une file qui ne se vide pas — se voit ici,
 * exécuté par PHP. Aucun appel à Pactols : les termes sont posés d'avance
 * dans la réserve, pour que la publication ne dépende pas de la santé d'un
 * service tiers.
 *
 * @package notice-archeomed
 */

$plugin = isset( $GLOBALS['notice_archeomed_plugin'] ) ? $GLOBALS['notice_archeomed_plugin'] : null;
if ( ! $plugin instanceof Notice_Archeomed_Pactols ) {
	WP_CLI::line( "::error::L'instance du plugin n'est pas globale : \$notice_archeomed_plugin est introuvable." );
	WP_CLI::halt( 1 );
}

// « wp eval-file » exécute ce fichier dans une fonction : une variable posée
// ici ne serait pas globale, et les échecs se compteraient dans le vide.
$GLOBALS['na_ratees'] = 0;

/** Appelle une méthode privée. */
function na_appel( $objet, $methode, array $args = array() ) {
	$r = new ReflectionMethod( $objet, $methode );
	$r->setAccessible( true );
	return $r->invokeArgs( is_object( $objet ) ? $objet : null, $args );
}

/** Une vérification : un trait de réussite, ou l'échec avec ce qu'on a obtenu. */
function na_verifier( $vrai, $quoi, $obtenu = null ) {
	if ( $vrai ) {
		WP_CLI::log( '  ✓ ' . $quoi );
		return;
	}
	++$GLOBALS['na_ratees'];
	// En annotation GitHub : le journal complet d'une exécution demande d'être
	// connecté, l'annotation se lit sur la page publique de l'exécution.
	$detail = ( null === $obtenu ) ? '' : ' — obtenu : ' . str_replace( array( "\r", "\n" ), ' ',
		var_export( $obtenu, true ) );
	WP_CLI::line( '::error::' . $quoi . $detail );
}

/** Une notice d'essai, privée comme les vraies. */
function na_notice( $donnees, $termes = null ) {
	$id = wp_insert_post( array(
		'post_type'   => Notice_Archeomed_File::CPT,
		'post_status' => 'private',
		'post_title'  => 'Essai',
	) );
	update_post_meta( $id, '_na_donnees', $donnees );
	if ( null !== $termes ) {
		update_post_meta( $id, '_na_pactols', $termes );
	}
	return $id;
}

$a = 'https://ark.frantiq.fr/ark:/26678/pcrtA';
$b = 'https://ark.frantiq.fr/ark:/26678/pcrtB';
$concept = function ( $ark, $lu_le ) {
	return array(
		'ark'       => $ark,
		'prefLabel' => 'église',
		'labels'    => array( 'fr' => 'église', 'en' => 'church' ),
		'deprecie'  => false,
		'chemin'    => array(
			array( 'ark' => 'https://ark.frantiq.fr/ark:/26678/pcrtRacine', 'labels' => array( 'fr' => 'entités matérielles' ) ),
			array( 'ark' => $ark, 'labels' => array( 'fr' => 'église', 'en' => 'church' ) ),
		),
		'lu_le'     => $lu_le,
	);
};
$saisie = array(
	'commune'                => 'Caen',
	'lieu_dit'               => 'Château',
	'pactols_subjects_items' => array( array( 'label' => 'église', 'ark' => $a, 'idConcept' => '' ) ),
	'pactols_periods_items'  => array( array( 'label' => 'haut Moyen Âge', 'ark' => $b, 'idConcept' => '' ) ),
);

WP_CLI::log( 'Le millésime' );
$m = na_appel( $plugin, 'millesime', array( $saisie, array(
	$a => $concept( $a, '2026-11-15' ),
	$b => $concept( $b, '2026-10-01' ),
	'https://ark.frantiq.fr/ark:/26678/pcrtAncien' => $concept( 'x', '2020-01-01' ),
) ) );
na_verifier( array( '2026-10-01', '2026-11-15' ) === $m,
	'deux dates rendues dans l\'ordre, et le terme sorti de la saisie ignoré', $m );

WP_CLI::log( 'Les termes manquants' );
$manque = na_appel( $plugin, 'termes_manquants', array( $saisie, array( $a => $concept( $a, '2026-10-01' ) ) ) );
na_verifier( array( $b ) === $manque, 'le terme absent de la réserve est désigné', $manque );

WP_CLI::log( 'Le bloc d\'index' );
$bloc = Notice_Archeomed_Thesaurus::index_tei( 'église', $concept( $a, '2026-10-01' ), 'pactols:Sujets' );
$xml  = simplexml_load_string( '<r xmlns:xml="http://www.w3.org/XML/1998/namespace">' . $bloc . '</r>' );
na_verifier( false !== $xml, 'le bloc est du XML bien formé' );
na_verifier( false !== strpos( $bloc, 'source="26678/pcrtRacine"' )
	&& false !== strpos( $bloc, 'indexName="pactols:Sujets" n="1"' ),
	'la racine porte le rang 1, son ARK relatif et le nom d\'index' );

WP_CLI::log( 'Les noms du dossier' );
$registre = array();
$noms     = array();
foreach ( array( 'caen_parcelle_2', 'caen_parcelle', 'caen_parcelle' ) as $nom ) {
	$noms[] = na_appel( 'Notice_Archeomed_Paquet', 'nom_libre', array( $nom, &$registre ) );
}
na_verifier( array( 'caen_parcelle_2', 'caen_parcelle', 'caen_parcelle_3' ) === $noms,
	'un suffixe déjà pris est sauté au lieu d\'être écrasé', $noms );

WP_CLI::log( 'La saisie et ses aperçus' );
$ancienne = $saisie;
$ancienne['illustrations'] = array( array( 'rang' => 1, 'titre' => 'Vue',
	'figure' => array( 'apercu' => '/tmp/apercu.jpg', 'largeur' => 10, 'hauteur' => 10, 'dpi' => 96 ) ) );
$vieille = na_notice( $ancienne );
$pour_le_dossier = na_appel( $plugin, 'saisie_de', array( $vieille, false ) );
na_verifier( ! isset( $pour_le_dossier['illustrations'][0]['figure'] ),
	'le dossier reçoit une saisie sans figure, même d\'une notice de la 3.35' );
$pour_le_courriel = na_appel( $plugin, 'saisie_de', array( $vieille, true ) );
na_verifier( isset( $pour_le_courriel['illustrations'][0]['figure']['apercu'] ),
	'le document autonome retrouve l\'aperçu d\'une notice de la 3.35' );

WP_CLI::log( 'La file des termes' );
// La notice de l'essai précédent s'est mise en attente en passant par
// « saisie_de » (ses termes manquent) : on l'en sort, sans quoi la file ne
// serait jamais vide et les essais de cette partie échoueraient à tort.
na_appel( $plugin, 'sortir_de_l_attente', array( $vieille ) );
wp_unschedule_hook( Notice_Archeomed_Pactols::HOOK_TERMES );
$complete = na_notice( $saisie, array( $a => $concept( $a, '2026-10-01' ), $b => $concept( $b, '2026-10-01' ) ) );
$sans     = na_notice( array( 'commune' => 'Vire', 'lieu_dit' => 'Bourg' ) );
foreach ( array( $complete, $sans ) as $id ) {
	na_appel( $plugin, 'mettre_en_attente', array( $id ) );
}
na_verifier( '' === get_post_meta( $complete, '_na_pactols_apres', true )
	&& '' === get_post_meta( $sans, '_na_pactols_apres', true )
	&& false === wp_next_scheduled( Notice_Archeomed_Pactols::HOOK_TERMES ),
	'une notice sans terme à chercher ne se met pas en attente et ne réserve aucun passage' );

// Des notices mises en attente avant cette version, dues tout de suite.
foreach ( array( $complete, $sans ) as $id ) {
	update_post_meta( $id, '_na_pactols_apres', time() - 1 );
}
$plugin->resoudre_en_tache();
na_verifier( '' === get_post_meta( $complete, '_na_pactols_apres', true ),
	'une notice dont tous les termes sont connus sort de l\'attente' );
na_verifier( '' === get_post_meta( $sans, '_na_pactols_apres', true ),
	'une notice sans terme sort de l\'attente' );
na_verifier( false === wp_next_scheduled( Notice_Archeomed_Pactols::HOOK_TERMES ),
	'la file vide, aucune tâche ne reste inscrite' );

$attend = na_notice( $saisie );
wp_schedule_single_event( time() + 3900, Notice_Archeomed_Pactols::HOOK_TERMES );
na_appel( $plugin, 'mettre_en_attente', array( $attend ) );
$prevue = wp_next_scheduled( Notice_Archeomed_Pactols::HOOK_TERMES );
na_verifier( $prevue && $prevue <= time() + 2 * MINUTE_IN_SECONDS,
	'une notice à examiner dans la minute avance une tâche réservée une heure plus tard',
	$prevue ? $prevue - time() : $prevue );

na_appel( $plugin, 'sortir_de_l_attente', array( $attend ) );
$plugin->resoudre_en_tache( $attend );
na_verifier( '' !== get_post_meta( $attend, '_na_pactols_apres', true )
	&& '' === get_post_meta( $attend, '_na_pactols_essais', true ),
	'une tâche laissée par la 3.36 verse sa notice dans l\'attente sans l\'examiner' );
na_appel( $plugin, 'sortir_de_l_attente', array( $attend ) );

$epuisee = na_notice( $saisie );
update_post_meta( $epuisee, '_na_pactols_essais', Notice_Archeomed_Pactols::ESSAIS_TERMES );
na_appel( $plugin, 'mettre_en_attente', array( $epuisee ) );
na_verifier( '' === get_post_meta( $epuisee, '_na_pactols_apres', true ),
	'une notice qui a épuisé ses essais ne revient pas d\'elle-même' );
$plus_tard = time() + 3000;
update_post_meta( $epuisee, '_na_pactols_apres', $plus_tard );
$quand = na_appel( $plugin, 'relancer_la_resolution', array( $epuisee, $saisie ) );
na_verifier( $plus_tard === $quand && '' === get_post_meta( $epuisee, '_na_pactols_essais', true ),
	'la relance remet les essais à zéro sans avancer une reprise déjà fixée', $quand );
na_appel( $plugin, 'sortir_de_l_attente', array( $epuisee ) );

na_verifier( Notice_Archeomed_Pactols::REPRISE_TERMES > Notice_Archeomed_Thesaurus::DUREE_ECHEC,
	'la reprise vient après l\'oubli de l\'échec' );

WP_CLI::log( 'L\'issue d\'un passage' );
foreach ( array(
	array( 3, 0, false, true,  'resolue',   'plus rien ne manque : résolue' ),
	array( 3, 1, true,  true,  'reprendre', 'interrompue après un progrès : reprise dans la minute' ),
	array( 3, 3, true,  true,  'echec',     'interrompue sans rien apprendre, avec tout le budget : un échec' ),
	array( 3, 3, true,  false, 'reprendre', 'interrompue sans avoir eu tout le budget : ni jugée ni comptée' ),
	array( 3, 3, false, true,  'echec',     'des termes refusés : un échec' ),
) as $cas ) {
	list( $avant, $apres, $coupe, $entier, $attendu, $quoi ) = $cas;
	$obtenu = na_appel( $plugin, 'issue_du_passage', array( $avant, $apres, $coupe, $entier ) );
	na_verifier( $attendu === $obtenu, $quoi, $obtenu );
}

WP_CLI::log( 'L\'échec retenu, et ce qu\'il fait attendre' );
$clef  = na_appel( 'Notice_Archeomed_Thesaurus', 'clef', array( $a, '', 'TH_1' ) );
$oubli = time() + 1800;
set_transient( $clef, array( 'echec_jusqua' => $oubli ), 1800 );
na_verifier( $oubli === Notice_Archeomed_Thesaurus::echec_jusqua( $a, '', 'TH_1' ),
	'l\'échec garde l\'heure où il sera oublié' );
na_verifier( null === Notice_Archeomed_Thesaurus::resoudre( $a, '', 'TH_1' ),
	'un terme en échec ne s\'interroge pas' );
$premier = na_appel( $plugin, 'premier_examen_utile', array( $saisie, array() ) );
na_verifier( $premier > $oubli,
	'le premier examen vient après l\'oubli de l\'échec, même venu d\'une autre notice', $premier );
$premier = na_appel( $plugin, 'premier_examen_utile', array( $saisie, array( $a => $concept( $a, '2026-10-01' ) ) ) );
na_verifier( $premier <= time() + 2 * MINUTE_IN_SECONDS,
	'un terme déjà connu de la notice ne la retarde pas parce qu\'il a échoué ailleurs', $premier - time() );
na_verifier( $oubli + MINUTE_IN_SECONDS === na_appel( $plugin, 'reprise_apres_echec', array( $saisie, array() ) ),
	'après un échec, on reprend une minute après son oubli, qu\'on l\'ait vécu ou relu' );
$reprise = na_appel( $plugin, 'reprise_apres_echec', array( $saisie, array( $a => $concept( $a, '2026-10-01' ) ) ) );
na_verifier( $reprise >= time() + Notice_Archeomed_Pactols::REPRISE_TERMES - 5,
	'sans échec retenu pour ce qui manque — un terme interrompu —, le délai ordinaire', $reprise - time() );
set_transient( $clef, 'vide', 1800 );
na_verifier( Notice_Archeomed_Thesaurus::echec_jusqua( $a, '', 'TH_1' ) > time()
	&& null === Notice_Archeomed_Thesaurus::resoudre( $a, '', 'TH_1' ),
	'un échec posé avant la 3.38 se lit encore comme un échec' );
delete_transient( $clef );

WP_CLI::log( 'Le lisez-moi d\'une rubrique sans illustration' );
$paquet = new Notice_Archeomed_Paquet();
$paquet->noter( 'XML/indexation : un avis d\'essai.' );
$texte = na_appel( $paquet, 'lisez_moi' );
na_verifier( false !== strpos( $texte, 'Aucune illustration' )
	&& false !== strpos( $texte, 'XML/indexation : un avis d\'essai.' ),
	'l\'avis paraît même quand il n\'y a aucune illustration' );
$paquet  = new Notice_Archeomed_Paquet();
$journal = new ReflectionProperty( $paquet, 'journal' );
$journal->setAccessible( true );
$journal->setValue( $paquet, array( 'Caen : 0 fichier(s) pour 2 légende(s).' ) );
$texte = na_appel( $paquet, 'lisez_moi' );
na_verifier( false === strpos( $texte, 'c’est normal' )
	&& false !== strpos( $texte, '0 fichier(s) pour 2 légende(s)' ),
	'des légendes sans fichier ne se lisent pas « c\'est normal »' );

WP_CLI::log( 'Un fichier reçu puis perdu' );
$paquet = new Notice_Archeomed_Paquet();
$erreur = '';
$paquet->preparer( 'I. Constructions et habitats civils', array( array(
	'id'            => 0,
	'd'             => array( 'commune' => 'Caen', 'lieu_dit' => 'Château', 'annee' => '2026',
		'illustrations' => array( array( 'rang' => 1, 'titre' => 'Vue' ) ) ),
	'illustrations' => array( '/tmp/na-essai-fichier-absent.jpg' ),
) ), $erreur );
$texte = na_appel( $paquet, 'lisez_moi' );
na_verifier( false !== strpos( $texte, 'introuvable sur le serveur' )
	&& false === strpos( $texte, 'c’est normal' ),
	'le lisez-moi dit qu\'un fichier reçu manque, au lieu de « c\'est normal »', '' === $erreur ? null : $erreur );
$paquet->nettoyer();

WP_CLI::log( 'Les TIFF convertis par le navigateur' );
// Sans Imagick, le navigateur de l'auteur envoie deux JPEG par TIFF : on les
// reconnaît, on les range, et le dossier Métopes comme l'aperçu s'en servent.
if ( ! Notice_Archeomed_Paquet::imagick_disponible() && function_exists( 'imagecreatetruecolor' ) ) {
	$jpeg_de = function ( $l, $h, $nom ) {
		$chemin = trailingslashit( get_temp_dir() ) . $nom;
		$img = imagecreatetruecolor( $l, $h );
		imagefilledrectangle( $img, 0, 0, $l - 1, $h - 1, imagecolorallocate( $img, 90, 120, 160 ) );
		imagejpeg( $img, $chemin, 90 );
		imagedestroy( $img );
		return $chemin;
	};
	$hr_ok   = $jpeg_de( 1181, 1772, 'na-essai-conv-hr-ok.jpg' );
	$hr_trop = $jpeg_de( 4200, 100, 'na-essai-conv-hr-trop.jpg' );
	$br_ok   = $jpeg_de( 2400, 1600, 'na-essai-conv-br-ok.jpg' );
	$br_trop = $jpeg_de( 3000, 100, 'na-essai-conv-br-trop.jpg' );
	$png_nom = trailingslashit( get_temp_dir() ) . 'na-essai-conv.jpg';
	$img = imagecreatetruecolor( 50, 50 );
	imagepng( $img, $png_nom );
	imagedestroy( $img );
	$faux_nom = trailingslashit( get_temp_dir() ) . 'na-essai-conv-faux.jpg';
	file_put_contents( $faux_nom, str_repeat( 'x', 5000 ) );
	$accepte = array(
		'hr'          => Notice_Archeomed_Pactols::jpeg_converti_acceptable( $hr_ok, 'hr' ),
		'br'          => Notice_Archeomed_Pactols::jpeg_converti_acceptable( $br_ok, 'br' ),
		'hr trop'     => Notice_Archeomed_Pactols::jpeg_converti_acceptable( $hr_trop, 'hr' ),
		'br trop'     => Notice_Archeomed_Pactols::jpeg_converti_acceptable( $br_trop, 'br' ),
		'png déguisé' => Notice_Archeomed_Pactols::jpeg_converti_acceptable( $png_nom, 'hr' ),
		'faux'        => Notice_Archeomed_Pactols::jpeg_converti_acceptable( $faux_nom, 'hr' ),
	);
	na_verifier( true === $accepte['hr'] && true === $accepte['br'] && false === $accepte['hr trop'] && false === $accepte['br trop']
		&& false === $accepte['png déguisé'] && false === $accepte['faux'],
		'un JPEG converti se reconnaît à son contenu et à ses mesures : la haute définition tient dans 4 000 px, la basse dans son côté réglé ; un PNG déguisé ou un faux ne passent pas', $accepte );

	// Un JPEG tronqué en route n'a pas sa marque de fin.
	$tronque = trailingslashit( get_temp_dir() ) . 'na-essai-conv-tronque.jpg';
	$entier  = (string) file_get_contents( $hr_ok );
	file_put_contents( $tronque, substr( $entier, 0, (int) ( strlen( $entier ) * 0.8 ) ) );
	na_verifier( false === Notice_Archeomed_Pactols::jpeg_converti_acceptable( $tronque, 'hr' ),
		'un JPEG converti tronqué en route est écarté' );
	@unlink( $tronque );

	// GD ne s'ouvre pas sur une image que la mémoire ne tiendrait pas.
	$limite_avant = ini_get( 'memory_limit' );
	ini_set( 'memory_limit', '256M' );
	$memoire = array(
		'petite' => Notice_Archeomed_Paquet::memoire_suffit( 1181, 1772 ),
		'enorme' => Notice_Archeomed_Paquet::memoire_suffit( 20000, 20000 ),
	);
	ini_set( 'memory_limit', $limite_avant );
	na_verifier( true === $memoire['petite'] && false === $memoire['enorme'],
		'la mémoire se compte avant que GD déplie une image : 2 Mpx passent, 400 Mpx non', $memoire );

	// Le rangement à demeure, par rang de figure.
	$fiche_conv = wp_insert_post( array( 'post_type' => Notice_Archeomed_File::CPT, 'post_status' => 'private', 'post_title' => 'Conv' ) );
	$tmp_hr = trailingslashit( get_temp_dir() ) . 'notice-archeomed-conv-hr-2-essai.jpg';
	$tmp_br = trailingslashit( get_temp_dir() ) . 'notice-archeomed-conv-br-2-essai.jpg';
	copy( $hr_ok, $tmp_hr );
	copy( $br_ok, $tmp_br );
	$rangees = na_appel( $plugin, 'archiver_les_conversions', array( $fiche_conv, array( 2 => array( 'hr' => $tmp_hr, 'br' => $tmp_br ) ) ) );
	$gardees_conv = Notice_Archeomed_Pactols::conversions_rangees( $fiche_conv );
	na_verifier( isset( $gardees_conv[2]['hr'], $gardees_conv[2]['br'] ) && file_exists( $gardees_conv[2]['hr'] ) && file_exists( $gardees_conv[2]['br'] )
		&& array( 2 ) === array_keys( $gardees_conv ) && $rangees === $gardees_conv,
		'les conversions se rangent à demeure et se retiennent par le rang de leur figure', $gardees_conv );

	// Le dossier Métopes : la haute et la basse définition viennent de la conversion.
	$faux_tif = trailingslashit( get_temp_dir() ) . 'na-essai-conv-original.tif';
	file_put_contents( $faux_tif, "II*\0" . str_repeat( "\0", 64 ) );
	$paquet_conv = new Notice_Archeomed_Paquet();
	$erreur_conv = '';
	$plan_conv = $paquet_conv->preparer( 'I. Constructions et habitats civils', array( array(
		'id'            => $fiche_conv,
		'd'             => array( 'commune' => 'Caen', 'lieu_dit' => 'Essai', 'annee' => '2026',
			'illustrations' => array( array( 'rang' => 1, 'titre' => 'Plan' ) ) ),
		'illustrations' => array( $faux_tif ),
		'conversions'   => array( 1 => array( 'hr' => $hr_ok, 'br' => $br_ok ) ),
	) ), $erreur_conv );
	$atelier = new ReflectionProperty( $paquet_conv, 'atelier' );
	$atelier->setAccessible( true );
	$dossier_atelier = (string) $atelier->getValue( $paquet_conv );
	$figure_conv = isset( $plan_conv[0][1] ) ? $plan_conv[0][1] : ( isset( $plan_conv[ key( $plan_conv ) ][1] ) ? $plan_conv[ key( $plan_conv ) ][1] : null );
	$fichiers_hr = glob( $dossier_atelier . '/icono/hr/*.jpg' );
	$fichiers_br = glob( $dossier_atelier . '/icono/br/*.jpg' );
	$mesure_hr = ! empty( $fichiers_hr ) ? getimagesize( $fichiers_hr[0] ) : false;
	$mesure_br = ! empty( $fichiers_br ) ? getimagesize( $fichiers_br[0] ) : false;
	na_verifier( is_array( $figure_conv ) && ! empty( $figure_conv['fichier'] ) && $mesure_hr && 1181 === $mesure_hr[0] && 1772 === $mesure_hr[1]
		&& $mesure_br && 2400 === $mesure_br[0] && 1600 === $mesure_br[1],
		'sans Imagick, le dossier Métopes prend la haute et la basse définition du navigateur, et la figure est posée', array( $erreur_conv, $plan_conv, $fichiers_hr, $fichiers_br ) );
	$paquet_conv->nettoyer();

	// Un PDF : la haute et la basse définition viennent du navigateur, en JPEG ;
	// le PDF d'origine reste dans « originaux ».
	$faux_pdf = trailingslashit( get_temp_dir() ) . 'na-essai-conv-original.pdf';
	file_put_contents( $faux_pdf, "%PDF-1.4\n%%EOF\n" );
	$paquet_pdf = new Notice_Archeomed_Paquet();
	$erreur_pdf = '';
	$plan_pdf = $paquet_pdf->preparer( 'I. Constructions et habitats civils', array( array(
		'id'            => 0,
		'd'             => array( 'commune' => 'Caen', 'lieu_dit' => 'Essai PDF', 'annee' => '2026',
			'illustrations' => array( array( 'rang' => 1, 'titre' => 'Plan' ) ) ),
		'illustrations' => array( $faux_pdf ),
		'conversions'   => array( 1 => array( 'hr' => $hr_ok, 'br' => $br_ok ) ),
	) ), $erreur_pdf );
	$atelier_pdf = new ReflectionProperty( $paquet_pdf, 'atelier' );
	$atelier_pdf->setAccessible( true );
	$dossier_pdf = (string) $atelier_pdf->getValue( $paquet_pdf );
	$hr_pdf = glob( $dossier_pdf . '/icono/hr/*.jpg' );
	$br_pdf = glob( $dossier_pdf . '/icono/br/*.jpg' );
	$pdf_en_hr = glob( $dossier_pdf . '/icono/hr/*.pdf' );
	$mesure_hr_pdf = ! empty( $hr_pdf ) ? getimagesize( $hr_pdf[0] ) : false;
	$mesure_br_pdf = ! empty( $br_pdf ) ? getimagesize( $br_pdf[0] ) : false;
	na_verifier( $mesure_hr_pdf && 1181 === $mesure_hr_pdf[0] && empty( $pdf_en_hr ) && $mesure_br_pdf && 2400 === $mesure_br_pdf[0],
		'un PDF sans Imagick : la haute et la basse définition viennent du navigateur, en JPEG, comme pour un TIFF', array( $erreur_pdf, $hr_pdf, $br_pdf, $pdf_en_hr ) );
	$paquet_pdf->nettoyer();
	$apercus_pdf = na_appel( $plugin, 'fabriquer_les_apercus', array( array( $faux_pdf ), array( 'illustrations' => array( array( 'rang' => 1 ) ) ),
		array( 1 => array( 'br' => $br_ok ) ) ) );
	na_verifier( isset( $apercus_pdf[1]['apercu'] ) && file_exists( $apercus_pdf[1]['apercu'] ),
		'l\'aperçu d\'un PDF se tire de sa basse définition convertie', $apercus_pdf );
	if ( isset( $apercus_pdf[1]['apercu'] ) ) { @unlink( $apercus_pdf[1]['apercu'] ); }
	@unlink( $faux_pdf );

	// L'aperçu du courriel : tiré de la basse définition, pour un TIFF que le serveur ne lit pas.
	$apercus_conv = na_appel( $plugin, 'fabriquer_les_apercus', array( array( $faux_tif ), array( 'illustrations' => array( array( 'rang' => 1 ) ) ),
		array( 1 => array( 'br' => $br_ok ) ) ) );
	$mesure_ap = ( isset( $apercus_conv[1]['apercu'] ) && file_exists( $apercus_conv[1]['apercu'] ) ) ? getimagesize( $apercus_conv[1]['apercu'] ) : false;
	na_verifier( $mesure_ap && 1000 === $mesure_ap[0] && 667 === $mesure_ap[1] || ( $mesure_ap && 1000 === $mesure_ap[0] && 666 === $mesure_ap[1] ),
		'l\'aperçu d\'un TIFF que le serveur ne lit pas se tire de sa basse définition convertie, à 1 000 px sur le grand côté', $apercus_conv );
	if ( isset( $apercus_conv[1]['apercu'] ) ) { @unlink( $apercus_conv[1]['apercu'] ); }

	// À la suppression de la notice, les conversions partent avec elle.
	$plugin->effacer_les_illustrations( $fiche_conv );
	na_verifier( ! file_exists( $gardees_conv[2]['hr'] ) && ! file_exists( $gardees_conv[2]['br'] ),
		'les conversions sont effacées avec la notice' );
	wp_delete_post( $fiche_conv, true );
	foreach ( array( $hr_ok, $hr_trop, $br_ok, $br_trop, $png_nom, $faux_nom, $faux_tif ) as $f ) { @unlink( $f ); }
} else {
	WP_CLI::log( '  — Imagick présent : la conversion par le navigateur n\'a pas lieu d\'être éprouvée ici.' );
}

WP_CLI::log( 'Les instances, globales même sous WP-CLI' );
na_verifier( isset( $GLOBALS['notice_archeomed_file'] )
	&& $GLOBALS['notice_archeomed_file'] instanceof Notice_Archeomed_File,
	'la file est globale : le plugin ne bâtit pas une seconde file aux crochets doublés' );

WP_CLI::log( 'Le réveil de la file' );
$reveil = na_notice( $saisie );
update_post_meta( $reveil, '_na_pactols_apres', time() + 30 );
wp_unschedule_hook( Notice_Archeomed_Pactols::HOOK_TERMES );
$plugin->reveiller_la_file();
na_verifier( (bool) wp_next_scheduled( Notice_Archeomed_Pactols::HOOK_TERMES ),
	'une notice qui attend sans tâche la voit renaître' );
na_appel( $plugin, 'sortir_de_l_attente', array( $reveil ) );

WP_CLI::log( 'Les pièces du courriel' );
// Tout se calcule d'abord, s'efface ensuite, et ne se vérifie qu'à la fin :
// un essai raté ne doit pas laisser derrière lui des fichiers de 4 Mo.
$dossier_essai = trailingslashit( get_temp_dir() ) . 'na-essai-courriel';
wp_mkdir_p( $dossier_essai );
$doc_essai = $dossier_essai . '/notice.docx';
$orig1     = $dossier_essai . '/orig1.jpg';
$orig2     = $dossier_essai . '/orig2.jpg';
$leger1    = $dossier_essai . '/apercu-1.jpg';
$leger2    = $dossier_essai . '/apercu-2.jpg';
$lourd1    = $dossier_essai . '/apercu-lourd-1.jpg';
file_put_contents( $doc_essai, str_repeat( 'd', 20 * KB_IN_BYTES ) );
file_put_contents( $orig1, str_repeat( 'a', 4 * MB_IN_BYTES ) );
file_put_contents( $orig2, str_repeat( 'b', 4 * MB_IN_BYTES ) );
file_put_contents( $leger1, str_repeat( 'l', 150 * KB_IN_BYTES ) );
file_put_contents( $leger2, str_repeat( 'm', 150 * KB_IN_BYTES ) );
file_put_contents( $lourd1, str_repeat( 'n', 800 * KB_IN_BYTES ) );
$saisie_fig = array( 'commune' => 'Plédéhel', 'lieu_dit' => 'Le Bourg', 'departement' => 'Côtes-d’Armor',
	'resp_prenom' => 'Aude', 'resp_nom' => 'Ferrand', 'resp_email' => 'aude.ferrand@example.org',
	'illustrations' => array( array( 'rang' => 1, 'titre' => 'Vue' ), array( 'rang' => 2, 'titre' => 'Plan' ) ) );
$avec_figures = na_notice( $saisie_fig );
update_post_meta( $avec_figures, '_na_illustrations', array( $orig1, $orig2 ) );
$existants = array( $orig1, $orig2, $doc_essai );
$composer  = function ( $apercus ) use ( $plugin, $avec_figures, $saisie_fig, $existants, $doc_essai ) {
	if ( null === $apercus ) {
		delete_post_meta( $avec_figures, '_na_apercus' );
	} else {
		update_post_meta( $avec_figures, '_na_apercus', $apercus );
	}
	$restees = array();
	$legeres = array();
	$joindre = na_appel( $plugin, 'pieces_du_courriel', array( $avec_figures, $saisie_fig,
		$existants, $doc_essai, 4000, &$restees, &$legeres ) );
	// Le nom sous lequel chaque pièce voyage : la clé, ou le nom du fichier.
	$noms = array();
	foreach ( $joindre as $cle => $fichier ) {
		$noms[] = is_string( $cle ) ? $cle : basename( $fichier );
	}
	return array( 'noms' => $noms, 'restees' => array_map( 'basename', $restees ), 'legeres' => count( $legeres ),
		'joindre' => $joindre );
};
$apercu = function ( $fichier ) {
	return array( 'apercu' => $fichier, 'largeur' => 1000, 'hauteur' => 750, 'dpi' => 96 );
};

// Sans version allégée, sous 10 Mo : le document, un original, et le second
// reste sur le site — ce qu'a montré l'hébergement de la revue.
$sans = $composer( null );
// La figure 1 allégée, la 2 en original : l'ordre des figures est gardé.
$une = $composer( array( 1 => $apercu( $leger1 ) ) );
// La figure 2 seule allégée : l'original de la 1 vient quand même en premier.
$ordre = $composer( array( 2 => $apercu( $leger2 ) ) );
// Sous un poids réglé à 1 Mo, une version allégée de 800 Ko ne tient plus :
// c'est l'original, qui est sur le site, que le courriel doit nommer.
$reglages = get_option( Notice_Archeomed_Settings::OPTION_NAME, array() );
update_option( Notice_Archeomed_Settings::OPTION_NAME,
	array_merge( (array) $reglages, array( 'poids_courriel' => 1 ) ) );
$serre = $composer( array( 1 => $apercu( $lourd1 ), 2 => $apercu( $leger2 ) ) );
update_option( Notice_Archeomed_Settings::OPTION_NAME, $reglages );
$avis_restees = na_appel( $plugin, 'avis_des_pieces_restees', array( array( $orig2 ), $avec_figures ) );
$avis_legeres = na_appel( $plugin, 'avis_des_illustrations', array( 2, array(), $avec_figures ) );

// Le diagnostic ne fabrique rien et ne touche pas à la notice.
update_post_meta( $avec_figures, '_na_apercus', array( 1 => $apercu( $leger1 ) ) );
$avant = get_post_meta( $avec_figures, '_na_apercus', true );
na_appel( $plugin, 'diagnostiquer_la_notice', array( $avec_figures, 'essai@example.org' ) );
$diagnostic_neutre = $avant === get_post_meta( $avec_figures, '_na_apercus', true )
	&& '' === get_post_meta( $avec_figures, '_na_apercus_tente', true );
update_post_meta( $avec_figures, '_na_apercus_tente', time() );
$deja_tente = na_appel( $plugin, 'apercus_a_fabriquer', array( $avec_figures ) );

wp_delete_post( $avec_figures, true );
foreach ( array( $doc_essai, $orig1, $orig2, $leger1, $leger2, $lourd1 ) as $f ) {
	@unlink( $f );
}
@rmdir( $dossier_essai );

na_verifier( array( 'notice.docx', 'Pledehel_Le_Bourg_Fig_1.jpg' ) === $sans['noms'] && array( 'orig2.jpg' ) === $sans['restees'],
	'sans version allégée : le document, puis ce qui tient, sous le nom de sa figure ; le second original reste sur le site', $sans );
na_verifier( array( 'notice.docx', 'Pledehel_Le_Bourg_Fig_1_apercu.jpg', 'Pledehel_Le_Bourg_Fig_2.jpg' ) === $une['noms']
	&& array() === $une['restees'] && 1 === $une['legeres'],
	'la figure 1 en version allégée sous un nom lisible, la 2 en original faute d\'aperçu', $une );
na_verifier( $leger1 === $une['joindre']['Pledehel_Le_Bourg_Fig_1_apercu.jpg'],
	'la version allégée part telle qu\'elle est rangée, sans copie dans un dossier temporaire', $une );
na_verifier( array( 'notice.docx', 'Pledehel_Le_Bourg_Fig_1.jpg', 'Pledehel_Le_Bourg_Fig_2_apercu.jpg' ) === $ordre['noms'],
	'les figures gardent leur ordre, qu\'elles soient allégées ou non', $ordre );
na_verifier( array( 'notice.docx', 'Pledehel_Le_Bourg_Fig_2_apercu.jpg' ) === $serre['noms']
	&& array( 'orig1.jpg' ) === $serre['restees'] && 1 === $serre['legeres'],
	'une version allégée qui ne tient pas : l\'original est nommé, et le compte est juste', $serre );
$sans_meta = na_notice( $saisie_fig );
na_verifier( array() === Notice_Archeomed_Pactols::illustrations_rangees( $sans_meta ),
	'une notice sans illustrations rangées n\'a pas de figure 1 fantôme' );
wp_delete_post( $sans_meta, true );
na_verifier( false !== strpos( $avis_restees, 'Fig. 2' ) && false === strpos( $avis_restees, 'orig2.jpg' )
	&& false !== strpos( $avis_restees, 'post=' . $avec_figures ),
	'le courriel nomme la figure restée par son numéro, non par le nom du fichier, et donne le lien de la notice', $avis_restees );
na_verifier( false !== strpos( $avis_legeres, '2 illustrations sont jointes en version allégée' )
	&& false !== strpos( $avis_legeres, number_format_i18n( Notice_Archeomed_Paquet::LARGEUR_APERCU ) ),
	'le courriel dit combien de figures sont allégées, et à quelle largeur', $avis_legeres );
na_verifier( $diagnostic_neutre, 'le diagnostic ne fabrique rien et ne touche pas à la notice' );
na_verifier( false === $deja_tente, 'une notice déjà tentée ne relance pas la fabrication à chaque envoi' );

WP_CLI::log( 'La version allégée d\'une vraie image' );
if ( function_exists( 'imagecreatetruecolor' ) ) {
	$vraie = trailingslashit( get_temp_dir() ) . 'notice-archeomed-essai-vraie.jpg';
	$gd    = imagecreatetruecolor( 1600, 1200 );
	imagefilledrectangle( $gd, 0, 0, 1599, 1199, imagecolorallocate( $gd, 120, 90, 60 ) );
	imagejpeg( $gd, $vraie, 90 );
	imagedestroy( $gd );
	$faites = na_appel( $plugin, 'fabriquer_les_apercus', array( array( $vraie ), array( 'illustrations' => array( array( 'rang' => 1 ) ) ) ) );
	$faite  = isset( $faites[1]['apercu'] ) ? $faites[1]['apercu'] : '';
	$taille = ( '' !== $faite && file_exists( $faite ) ) ? getimagesize( $faite ) : false;
	@unlink( $vraie );
	if ( '' !== $faite ) {
		@unlink( $faite );
	}
	na_verifier( is_array( $taille ) && Notice_Archeomed_Paquet::LARGEUR_APERCU === (int) $taille[0],
		'une vraie image donne sa version allégée, à la largeur dite (par Imagick, ou à son défaut par GD)', $faites );
	// Le repli : GD seul, quand Imagick échoue ou manque, pour un JPEG comme pour un PNG.
	$paquet_gd = new Notice_Archeomed_Paquet();
	$resultats_gd = array();
	foreach ( array( 'jpg' => 'imagejpeg', 'png' => 'imagepng' ) as $ext => $ecrire ) {
		$source_gd = trailingslashit( get_temp_dir() ) . 'notice-archeomed-essai-gd.' . $ext;
		$cible_gd  = trailingslashit( get_temp_dir() ) . 'notice-archeomed-essai-gd-cible.jpg';
		$img       = imagecreatetruecolor( 2400, 1600 );
		imagefilledrectangle( $img, 0, 0, 2399, 1599, imagecolorallocate( $img, 30, 90, 150 ) );
		$ecrire( $img, $source_gd );
		imagedestroy( $img );
		$fait_gd = na_appel( $paquet_gd, 'reduire_par_gd', array( $source_gd, $cible_gd, 1000, 92, 150 ) );
		$mesure_gd = $fait_gd ? getimagesize( $cible_gd ) : false;
		$resultats_gd[ $ext ] = $mesure_gd ? $mesure_gd[0] . 'x' . $mesure_gd[1] . ' ' . round( filesize( $cible_gd ) / 1024 ) . ' Ko' : 'échec';
		@unlink( $source_gd );
		@unlink( $cible_gd );
	}
	na_verifier( 1 === preg_match( '/^1000x66[67] /', $resultats_gd['jpg'] ) && 1 === preg_match( '/^1000x66[67] /', $resultats_gd['png'] ),
		'sans Imagick, GD réduit un JPEG et un PNG à la largeur dite : la figure ne disparaît pas du document', $resultats_gd );
	// La haute définition par le repli : un JPEG entier part tel quel, un PNG devient un JPEG qui porte sa densité.
	$jpeg_hr = trailingslashit( get_temp_dir() ) . 'notice-archeomed-essai-hr.jpg';
	$png_hr  = trailingslashit( get_temp_dir() ) . 'notice-archeomed-essai-hr.png';
	$sortie_hr_a = trailingslashit( get_temp_dir() ) . 'notice-archeomed-essai-hr-a.jpg';
	$sortie_hr_b = trailingslashit( get_temp_dir() ) . 'notice-archeomed-essai-hr-b.jpg';
	$img = imagecreatetruecolor( 1200, 800 );
	imagefilledrectangle( $img, 0, 0, 1199, 799, imagecolorallocate( $img, 200, 120, 40 ) );
	imagejpeg( $img, $jpeg_hr, 90 );
	imagepng( $img, $png_hr );
	imagedestroy( $img );
	na_appel( $paquet_gd, 'reduire_par_gd', array( $jpeg_hr, $sortie_hr_a, PHP_INT_MAX, 92, Notice_Archeomed_Paquet::SANS_PLAFOND, 300 ) );
	na_appel( $paquet_gd, 'reduire_par_gd', array( $png_hr, $sortie_hr_b, PHP_INT_MAX, 92, Notice_Archeomed_Paquet::SANS_PLAFOND, 300 ) );
	$octets_b = (string) file_get_contents( $sortie_hr_b );
	$densite_b = strlen( $octets_b ) > 18 ? array( ord( $octets_b[13] ), unpack( 'n', substr( $octets_b, 14, 2 ) )[1], unpack( 'n', substr( $octets_b, 16, 2 ) )[1] ) : array();
	$mesure_a = getimagesize( $sortie_hr_a );
	$mesure_b = getimagesize( $sortie_hr_b );
	na_verifier( md5_file( $jpeg_hr ) === md5_file( $sortie_hr_a ) && $mesure_a[0] === 1200 && $mesure_b[0] === 1200 && array( 1, 300, 300 ) === $densite_b,
		'la haute définition par le repli : un JPEG entier part tel quel, un PNG devient un JPEG à sa taille pleine, avec sa densité de 300 ppp', array( $mesure_a, $mesure_b, $densite_b ) );
	foreach ( array( $jpeg_hr, $png_hr, $sortie_hr_a, $sortie_hr_b ) as $f ) { @unlink( $f ); }
	na_verifier( false === na_appel( $paquet_gd, 'reduire_par_gd', array( '/inexistant.tif', '/tmp/x.jpg', 1000, 92, 150 ) ),
		'GD ne lit pas ce qui n\'est pas un JPEG ou un PNG : la figure part alors sans aperçu, sans erreur' );
} else {
	WP_CLI::log( '  — GD absent : la fabrication n\'est pas éprouvée ici.' );
}

WP_CLI::log( 'La note d\'un envoi partiel' );
$partie = na_notice( $saisie );
update_post_meta( $partie, '_na_erreur', 'Impossible d’instancier la fonction mail.' );
$GLOBALS['notice_archeomed_file']->marquer( $partie, 'envoyee', 'Partie sans 1 illustration.' );
na_verifier( '' === get_post_meta( $partie, '_na_erreur', true )
	&& 'Partie sans 1 illustration.' === get_post_meta( $partie, '_na_note', true ),
	'une notice partie perd son ancienne erreur, et garde la note de ce qui manque' );
wp_delete_post( $partie, true );

WP_CLI::log( 'L\'essai de poids' );
$refus = $plugin->essayer_le_poids( 'pas-une-adresse', 6 );
na_verifier( false === $refus['ok'], 'une adresse invalide est refusée avant tout envoi' );
delete_option( 'na_essais_de_poids' );
$essai = $plugin->essayer_le_poids( 'essai@example.org', 9 );
$garde = get_option( 'na_essais_de_poids', array() );
na_verifier( isset( $garde[9] ) && 9 === $garde[9]['mo'] && is_bool( $garde[9]['ok'] ),
	'l\'essai garde sa taille et la réponse du serveur, quelle qu\'elle soit', $essai['message'] );
na_verifier( ! file_exists( trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp/notice-archeomed-essai-de-poids-9-Mo.txt' ),
	'le fichier de 9 Mo ne reste pas sur le serveur' );
$autre = $plugin->essayer_le_poids( 'essai@example.org', 7 );
$garde = get_option( 'na_essais_de_poids', array() );
na_verifier( isset( $garde[6] ) && ! isset( $garde[7] ),
	'une taille hors de la liste retombe sur la plus petite' );
delete_option( 'na_essais_de_poids' );

WP_CLI::log( 'L\'icône du menu' );
$icone = na_appel( 'Notice_Archeomed_File', 'icone_du_menu' );
na_verifier( 0 === strpos( $icone, 'data:image/svg+xml;base64,' ),
	'le menu porte l\'arc de la revue', substr( $icone, 0, 40 ) );

WP_CLI::log( 'La relecture TEI' );
// Un saut de ligne manuel collé depuis Word, et un octet qui n'est pas de
// l'UTF-8 : le XML reste bien formé, et le texte ne disparaît pas.
$echappe = Notice_Archeomed_DOCX::esc( "Vue générale\x0Bdu chantier \x0C< 2 cm \xFF" );
na_verifier( false !== simplexml_load_string( '<t>' . $echappe . '</t>' )
	&& false !== strpos( $echappe, "générale\ndu chantier" ) && false !== strpos( $echappe, '&lt; 2 cm' ),
	'un caractère interdit en XML ne rend plus le document illisible', $echappe );
na_verifier( '&lt; 2 cm' === Notice_Archeomed_DOCX::esc( '&lt; 2 cm' ),
	'le chevron que WordPress a gardé sous forme d\'entité s\'imprime « < »' );
$retire = $concept( $a, '2026-10-01' );
$retire['deprecie'] = true;
na_verifier( '' === Notice_Archeomed_Thesaurus::index_tei( 'partie d\'église', $retire, 'pactols:Sujets' ),
	'un concept retiré du thésaurus ne reçoit pas de bloc d\'index' );
na_verifier( 'Aude' === na_appel( $plugin, 'sans_parentheses', array( "(l'Aude)" ) )
	&& 'Côte-d’Or' === na_appel( $plugin, 'sans_parentheses', array( 'Côte-d’Or' ) ),
	'l\'article collé au département s\'ôte, le reste ne bouge pas' );
$colle = na_appel( $plugin, 'clean_richtext', array( '<p>Trois phases :</p><ol><li>fossé</li><li>mur</li></ol><h2>Fin</h2>' ) );
$paras = ( new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) ) )->html_to_paragraphs( $colle );
na_verifier( 4 === count( $paras ), 'les éléments de liste et les intertitres collés font chacun un paragraphe', $colle );
na_verifier( "Jean Le\u{00A0}Maho" === na_appel( $plugin, 'nom_d_autorite', array( ' Jean ', 'Le  Maho' ) ),
	'le nom à particule reste d\'un bloc pour la chaîne' );
$tmp = trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp';
wp_mkdir_p( $tmp );
$a_garder = $tmp . '/notice-archeomed-essai-' . wp_generate_password( 6, false, false ) . '.docx';
file_put_contents( $a_garder, 'x' );
$gardes = na_appel( $plugin, 'mettre_a_labri', array( array( $a_garder ) ) );
na_verifier( 1 === count( $gardes ) && 0 === strpos( basename( $gardes[0] ), 'notice-archeomed-garde-' )
	&& dirname( $gardes[0] ) === $tmp && file_exists( $gardes[0] ) && ! file_exists( $a_garder ),
	'un fichier mis à l\'abri est vraiment renommé, dans son dossier', $gardes );
@unlink( $gardes[0] );
$_FILES = array( 'illustrations' => array( 'error' => array( UPLOAD_ERR_OK, UPLOAD_ERR_OK ) ) );
$_POST['illus_titre']   = array( '', 'Plan' );
$_POST['illus_legende'] = array( '', '' );
$_POST['illus_credits'] = array( '', '' );
$figures = na_appel( $plugin, 'collect_illustrations' );
$_FILES = array();
unset( $_POST['illus_titre'], $_POST['illus_legende'], $_POST['illus_credits'] );
na_verifier( 2 === count( $figures ) && 1 === $figures[0]['rang'] && 'Plan' === $figures[1]['titre'],
	'une figure déposée sans légende garde sa place et son numéro', $figures );

WP_CLI::log( 'La typographie et les années' );
$nb = "\u{00A0}";
$typo = Notice_Archeomed_Typographie::corriger( "Le mur : l'état du site ; voir « fossé » p. 12, XIIe s. et 10 000 m... Voir https://hal.science/a:b?c=d ; ok ?" );
na_verifier( false !== strpos( $typo, 'mur' . $nb . ':' ) && false !== strpos( $typo, "l\u{2019}état" )
	&& false !== strpos( $typo, '«' . $nb . 'fossé' . $nb . '»' ) && false !== strpos( $typo, 'p.' . $nb . '12' )
	&& false !== strpos( $typo, 'XIIe' . $nb . 's.' ) && false !== strpos( $typo, '10' . $nb . '000' . $nb . 'm…' )
	&& false !== strpos( $typo, 'https://hal.science/a:b?c=d' ) && false !== strpos( $typo, 'ok' . $nb . '?' ),
	'la ponctuation, les guillemets, les renvois et les nombres, les adresses laissées intactes', $typo );
na_verifier( "14:30 (?) R\u{2019}n" === Notice_Archeomed_Typographie::corriger( "14:30 (?) R'n" )
	&& "a\u{00A0}; b" === Notice_Archeomed_Typographie::corriger( "a\u{202F}; b" ),
	'l\'heure collée, le point d\'interrogation entre parenthèses, et pas de fine' );
// Le corpus de référence de la typographie : chaque entrée, la sortie
// attendue. Un écart dit qu'une règle a changé de sens.
$corpus = json_decode( file_get_contents( __DIR__ . '/typographie.json' ), true );
$ordres = array(
	'corriger'                   => array( 'texte', 'avant', 'langue', 'contexte_avant', 'contexte_apres' ),
	'retablir_insecables'        => array( 'texte', 'langue', 'contexte_avant', 'contexte_apres' ),
	'apostrophes_typographiques' => array( 'texte' ),
);
$defauts = array( 'avant' => '', 'langue' => 'fr', 'contexte_avant' => '', 'contexte_apres' => '' );
$ecarts  = array();
foreach ( (array) $corpus as $n => $cas ) {
	$args = array();
	foreach ( $ordres[ $cas['f'] ] as $i => $nom ) {
		$args[] = isset( $cas['a'][ $i ] ) ? $cas['a'][ $i ] : ( isset( $cas['k'][ $nom ] ) ? $cas['k'][ $nom ] : $defauts[ $nom ] );
	}
	if ( call_user_func_array( array( 'Notice_Archeomed_Typographie', $cas['f'] ), $args ) !== $cas['r'] ) {
		$ecarts[] = $n;
	}
}
na_verifier( ! empty( $corpus ) && empty( $ecarts ),
	'le corpus de typographie rend exactement ce qu\'il doit (' . count( (array) $corpus ) . ' cas)', $ecarts );
na_verifier( '2004-2005' === na_appel( $plugin, 'annee_normalisee', array( '2004 – 2005' ) )
	&& '2004-2005' === na_appel( $plugin, 'annee_normalisee', array( '2004/05' ) )
	&& '2024' === na_appel( $plugin, 'annee_normalisee', array( ' 2024 ' ) ),
	'une opération sur plusieurs années s\'écrit 2004-2005' );

WP_CLI::log( 'Les contrôles du dépôt' );
$C = 'Notice_Archeomed_Controles';
$avis_ponct = $C::ponctuation_finale( array( 'Certaines de ces structures sont datées du haut Moyen Âge, comme le montrent les céramiques', 'trouvées dans le comblement du fossé nord de l’enclos.', 'Une fin sans point mais assez longue pour être une phrase tronquée au collage' ) );
na_verifier( 2 === count( $avis_ponct ) && false !== strpos( $avis_ponct[0], 'suivant reprend' )
	&& false !== strpos( $avis_ponct[1], 'aucune ponctuation' ),
	'le paragraphe coupé et le texte tronqué se nomment chacun', $avis_ponct );
$avis_liste = $C::ponctuation_finale( array( 'Fosse 12', 'Silo 4', 'Foyer du bâtiment', 'Trou de poteau' ) );
na_verifier( array( 'Les paragraphes 1 à 4 (« Fosse 12 » … « Trou de poteau ») ne se terminent par aucune ponctuation : des intertitres se posent avec le bouton T1, une liste avec le bouton Liste à puces ; sinon, terminez les phrases.' ) === $avis_liste,
	'des éléments de liste collés (moins de huit mots) font un seul avis, qui ne parle pas de texte tronqué', $avis_liste );
$long_ok = 'Le fossé nord de l’enclos a livré un abondant mobilier céramique.';
$avis_court = $C::ponctuation_finale( array( $long_ok, 'fdgdfgfdg', $long_ok, 'vcvcvcbc', 'cvbvcb', $long_ok, 'Phase 1', 'Phase 2', 'Phase 3', 'Établissement agropastoral carolingien et constructions seigneuriales' ) );
na_verifier( array(
		'Le paragraphe 2 (« fdgdfgfdg ») ne se termine par aucune ponctuation : si c’est un intertitre, posez-le avec le bouton T1 ; sinon, terminez la phrase.',
		'Les paragraphes 4 et 5 (« vcvcvcbc » et « cvbvcb ») ne se terminent par aucune ponctuation : des intertitres se posent avec le bouton T1, une liste avec le bouton Liste à puces ; sinon, terminez les phrases.',
		'Les paragraphes 7 à 10 (« Phase 1 » … « Établissement agropastoral carolingien e… ») ne se terminent par aucune ponctuation : des intertitres se posent avec le bouton T1, une liste avec le bouton Liste à puces ; sinon, terminez les phrases.',
	) === $avis_court,
	'une ligne courte sans ponctuation se signale seule, les lignes courtes qui se suivent ensemble, et un paragraphe ponctué sépare les suites', $avis_court );
$avis_mixte = $C::ponctuation_finale( array( 'Sondage 3', 'Une fin sans point mais assez longue pour être une phrase tronquée au collage', 'Sondage 4' ) );
na_verifier( 3 === count( $avis_mixte ) && false !== strpos( $avis_mixte[0], 'Le paragraphe 1 (« Sondage 3 »)' )
	&& false !== strpos( $avis_mixte[1], 'texte tronqué' ) && false !== strpos( $avis_mixte[2], 'Le paragraphe 3 (« Sondage 4 »)' ),
	'un paragraphe long sans ponctuation coupe la suite des lignes courtes et garde son propre avis', $avis_mixte );
na_verifier( array() === $C::ponctuation_finale( array( 'Fosse 12.', 'Silo 4 ;', $long_ok ) ),
	'une ligne courte ponctuée ne reçoit aucun avis' );
$ordre_fig = $C::appels_de_figure( 'Le mur (fig. 2) et le fossé (fig. 1), puis la coupe (fig. 3).', 3 );
na_verifier( array( "La fig.\u{00A0}2 est appelée avant la fig.\u{00A0}1\u{00A0}: le texte appelle les figures dans l’ordre." ) === $ordre_fig
	&& array() === $C::appels_de_figure( 'Le fossé (fig. 1), le mur (fig. 2) et la coupe (fig. 3), puis encore fig. 1.', 3 )
	&& array() === $C::appels_de_figure( 'Voir fig. 1-3, puis la fig. 2.', 3 ),
	'une figure 2 appelée avant la 1 reçoit un avis ; l\'ordre normal, un rappel de la 1 après la 2, une plage, non', $ordre_fig );
$avis_fig = $C::appels_de_figure( 'Voir fig. 1-2 et la figure 3.', 2 );
na_verifier( 1 === count( $avis_fig ) && false !== strpos( $avis_fig[0], 'fig. 3' ),
	'un appel à une figure qui n\'est pas jointe', $avis_fig );
$sans_appel = $C::appels_de_figure( 'Aucun appel ici.', 3 );
na_verifier( array( 'La fig. 2 n’est appelée nulle part dans le texte.' ) === $C::appels_de_figure( 'Voir fig. 1.', 2 )
	&& 1 === count( $sans_appel ) && false !== strpos( $sans_appel[0], 'aucune des trois figures jointes' )
	&& 1 === count( $C::appels_de_figure( 'Aucun appel ici.', 1 ) )
	&& array() === $C::appels_de_figure( 'Aucun appel ici.', 0 ),
	'une figure jamais appelée ; un texte qui n\'appelle aucune des figures jointes ; rien sans figure', $sans_appel );
$ordinaux = $C::ordinaux_fautifs( array( 'Au XIIème siècle, la 1ère phase, la 2nde tour, la Mère.' ) );
na_verifier( array( '« XIIème » s’abrège « XIIe ».', '« 1ère » s’abrège « 1re ».', '« 2nde » s’abrège « 2de ».' ) === $ordinaux,
	'les ordinaux fautifs, et « Mère » laissée tranquille', $ordinaux );
$mal_ecrits = array(
	'Le mur (figure 3).'           => "«\u{202F}figure 3\u{202F}»\u{00A0}: un appel de figure s’écrit «\u{202F}fig.\u{00A0}3\u{202F}».",
	'Le fossé (voir Fig. 2).'      => "«\u{202F}Fig. 2\u{202F}»\u{00A0}: un appel de figure s’écrit «\u{202F}fig.\u{00A0}2\u{202F}».",
	'Voir fig 3.'                  => "«\u{202F}fig 3\u{202F}»\u{00A0}: un appel de figure s’écrit «\u{202F}fig.\u{00A0}3\u{202F}».",
	'Les murs (figures 3 et 4).'   => "«\u{202F}figures 3 et 4\u{202F}»\u{00A0}: un appel de figure s’écrit «\u{202F}fig.\u{00A0}3 et 4\u{202F}».",
	'Le sol (pl. 2, figure 5).'    => "«\u{202F}figure 5\u{202F}»\u{00A0}: un appel de figure s’écrit «\u{202F}fig.\u{00A0}5\u{202F}».",
);
$mal_lus = array();
foreach ( $mal_ecrits as $phrase => $attendu ) {
	$mal_lus[ $phrase ] = $C::appels_mal_ecrits( array( $phrase ) );
}
na_verifier( count( array_filter( array_map( function ( $phrase ) use ( $mal_ecrits, $mal_lus ) {
		return array( $mal_ecrits[ $phrase ] ) === $mal_lus[ $phrase ];
	}, array_keys( $mal_ecrits ) ) ) ) === count( $mal_ecrits ),
	'« (figure 3) », « (voir Fig. 2) », « fig 3 », « (figures 3 et 4) » reçoivent un avis qui dit la forme attendue', $mal_lus );
$bien_ecrits = $C::appels_mal_ecrits( array( 'La figure 3 montre le mur (fig. 3).', 'Voir (cf. fig. 1-3) et Figeac 3 (transfigure 3).', 'Voir fig. 1-2 et la figure 3.' ) );
na_verifier( array() === $bien_ecrits
	&& 1 === count( $C::appels_mal_ecrits( array( 'Le mur (figure 3), puis (figure 3).' ) ) )
	&& 1 === count( $C::appels_mal_ecrits( array( 'Le sol (fig. 1 ; figure 2).' ) ) ),
	'« la figure 3 montre », « (fig. 3) », « Figeac » ne reçoivent rien ; un même appel répété, un seul avis', $bien_ecrits );
na_verifier( 1 === count( $C::dates_espacees( array( 'occupé vers 1 250 puis abandonné' ) ) ),
	'l\'année espacée' );
na_verifier( array() === $C::dates_espacees( array( 'on y trouve en 1 250 tessons, et après 2 000 m² de décapage, vers 1 100 individus' ) ),
	'un nombre suivi d\'une unité ou d\'un objet (« en 1 250 tessons », « après 2 000 m² ») n\'est pas pris pour une année' );
// Les siècles en petites capitales : tapés en capitales, en bas de casse ou
// l'ordinal en exposant, ils sortent « xii » en petites capitales et « e » en
// exposant. « ce siècle » et un « XII » sans siècle derrière ne bougent pas.
$doc_siecles = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
$siecles_xml = $doc_siecles->html_to_paragraphs( '<p>Au XIIe s., au xiiie siècle, au XIV<sup>e</sup> siècle, aux IIIe-IVe s., au Ier millénaire ; en ce siècle, la tour XII.</p>' );
$abreges = implode( '', $doc_siecles->html_to_paragraphs( '<p>Aux XIIe et XIIIe siècles. Au XIV<sup>e</sup> siècle, et en ce siècle.</p>' ) );
na_verifier( 2 === substr_count( $abreges, "\u{00A0}s." ) && false === strpos( $abreges, 's..' )
	&& false !== strpos( $abreges, 'ce siècle' ) && 1 === substr_count( $abreges, 'siècle' ),
	'« siècle » s\'abrège « s. » après un siècle en chiffres, sans doubler le point, et « ce siècle » reste', $abreges );
$siecles_xml = implode( '', $siecles_xml );
$pc = function ( $chiffre ) {
	return '<w:smallCaps/></w:rPr><w:t xml:space="preserve">' . $chiffre . '</w:t>';
};
$sup = function ( $ordinal ) {
	return '<w:vertAlign w:val="superscript"/></w:rPr><w:t xml:space="preserve">' . $ordinal . '</w:t>';
};
na_verifier( false !== strpos( $siecles_xml, $pc( 'xii' ) ) && false !== strpos( $siecles_xml, $pc( 'xiii' ) )
	&& false !== strpos( $siecles_xml, $pc( 'xiv' ) ) && false !== strpos( $siecles_xml, $pc( 'iii' ) )
	&& false !== strpos( $siecles_xml, $pc( 'iv' ) ) && false !== strpos( $siecles_xml, $pc( 'i' ) )
	&& false !== strpos( $siecles_xml, $sup( 'er' ) ) && 6 === substr_count( $siecles_xml, '<w:smallCaps/>' )
	&& false !== strpos( $siecles_xml, 'tour XII.' ),
	'les siècles en petites capitales, l\'ordinal en exposant, et rien d\'autre', $siecles_xml );
$avis_images = $C::figures( array(
	array( 'rang' => 1, 'pixels' => array( 1000, 800 ), 'titre' => 'Plan', 'legende' => '', 'credits' => 'X', 'alt' => 'Murs du château', 'description' => 'Deux murs.' ),
	array( 'rang' => 2, 'pixels' => array( 1800, 1200 ), 'titre' => 'Vue', 'legende' => '', 'credits' => '', 'alt' => 'Fossé vu du nord' ),
) );
na_verifier( 2 === count( $avis_images ) && false !== strpos( $avis_images[0], 'Fig. 1' ) && false !== strpos( $avis_images[1], 'pas de crédits' ),
	'une figure trop petite pour la norme, une autre sans crédits', $avis_images );
$avis_personnes = $C::personnes( array( 'resp_prenom' => 'Aude', 'resp_nom' => 'FERRAND', 'resp_inst' => 'Inrap',
	'coauteur_prenom' => '', 'coauteur_nom' => 'Jean Dupont, Marie Martin', 'coauteur_inst' => 'a@b.fr' ) );
na_verifier( 3 === count( $avis_personnes ), 'le nom en capitales, deux personnes dans un champ, une adresse dans l\'institution', $avis_personnes );
na_verifier( 'Rue de Reviers' === $C::sans_point_final( 'Rue de Reviers.' ) && 'Le XXe s.' === $C::sans_point_final( 'Le XXe s.' )
	&& 'Plan' === $C::titre_sans_numero( 'Fig. 1 : Plan', 1 ) && 'Fig. 2 Plan' === $C::titre_sans_numero( 'Fig. 2 Plan', 1 ),
	'le point final du titre et le numéro retapé, abréviations et autre numéro laissés' );
$relecture = na_appel( $plugin, 'typographie_du_html', array( '<p>le <em>castrum</em>: il</p>' ) );
na_verifier( false !== strpos( $relecture, "</em>\u{00A0}: il" ), 'la page de relecture pose la typographie par-dessus les balises', $relecture );
$nom_doc = ( new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) ) )->char_run( 'TEI_archeoCHR_name:fld', 'J.-M. Poisson' );
na_verifier( false !== strpos( $nom_doc, 'J.-M. Poisson' ), 'un nom d\'autorité garde son espace ordinaire, que la chaîne coupe', $nom_doc );

$relu = na_appel( $plugin, 'siecles_du_html', array( '<p>Au XII<sup>e</sup>&nbsp;siècle et au xiiie s., en ce siècle.</p>' ) );
na_verifier( 2 === substr_count( $relu, 'all-small-caps' ) && false !== strpos( $relu, '>XII</span><sup>e</sup>' ) && false === strpos( $relu, '>xii<' )
	&& false !== strpos( $relu, "<sup>e</sup>\u{00A0}s. et" ) && false !== strpos( $relu, 'ce siècle' ),
	'la page de relecture montre aussi les siècles en petites capitales', $relu );

WP_CLI::log( 'Les normes éditoriales' );
$N = 'Notice_Archeomed_Normes';
$reglages_avant = get_option( Notice_Archeomed_Settings::OPTION_NAME, array() );
$poser_les_normes = function ( $normes ) use ( $reglages_avant, $N ) {
	update_option( Notice_Archeomed_Settings::OPTION_NAME, array_merge( (array) $reglages_avant, array( $N::CLE => $normes ) ) );
	$N::oublier();
};
$poser_les_normes( array() );
na_verifier( 'abrege' === $N::valeur( 'siecle_mot' ) && 'Fig. 3' === $N::numero_de_figure( 3 ) && '. ' === $N::avant_le_lieu_dit()
	&& array( 1182, 1772 ) === $N::pixels_des_photographies(),
	'sans réglage, les normes sont celles de la revue' );
$poser_les_normes( array( 'siecle_mot' => 'entier', 'siecle_chiffres' => 'capitales', 'siecle_ordinal' => 'ligne',
	'espace_ponctuation' => 'fine', 'annees' => 'demi_cadratin', 'titre_notice' => 'virgule', 'figure_abreviation' => 'figure',
	'figure_numero' => 'deux_points', 'photo_ppp' => 600, 'siecle_inconnu' => 'x', 'avis' => array( 'credits' ) ) );
$doc_normes = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
$xml_normes = implode( '', $doc_normes->html_to_paragraphs( '<p>Au xiie s. Puis aux XIIIe-XIVe s., et le mot ; fin.</p>' ) );
na_verifier( false !== strpos( $xml_normes, 'XII</w:t>' ) && false === strpos( $xml_normes, '<w:smallCaps/>' )
	&& false === strpos( $xml_normes, 'superscript' ) && false !== strpos( $xml_normes, 'siècle. Puis' )
	&& false !== strpos( $xml_normes, 'siècles, et' ) && false !== strpos( $xml_normes, "mot\u{202F};" ),
	'une autre revue : siècle en entier, capitales, ordinal sur la ligne, espace fine', $xml_normes );
na_verifier( '2004–2005' === na_appel( $plugin, 'annee_normalisee', array( '2004-2005' ) )
	&& 'Figure 2' === $N::numero_de_figure( 2 ) && ' : ' === $N::apres_le_numero() && ', ' === $N::avant_le_lieu_dit()
	&& array( 2363, 3544 ) === $N::pixels_des_photographies() && ! $N::avis_actif( 'ordinaux' ) && $N::avis_actif( 'credits' ),
	'les années, l\'appel des figures, le titre, la norme des images et les avis suivent les réglages' );
$propres = $N::nettoyer( array( 'siecle_mot' => 'nimporte', 'photo_ppp' => '99999', 'avis' => array( 'credits', 'pirate' ) ) );
na_verifier( 'abrege' === $propres['siecle_mot'] && 2400 === $propres['photo_ppp'] && array( 'credits' ) === $propres['avis'],
	'une valeur hors du catalogue retombe sur le choix de la revue, un nombre sur sa borne', $propres );
update_option( Notice_Archeomed_Settings::OPTION_NAME, $reglages_avant );
$N::oublier();

WP_CLI::log( 'La correspondance des styles Métopes' );
$ST = 'Notice_Archeomed_Styles';
$reglages_avant = get_option( Notice_Archeomed_Settings::OPTION_NAME, array() );
$feuille = $ST::styles_de_la_feuille();
na_verifier( in_array( 'TEI_archeoCHR_authority', $feuille['paragraphe'], true ) && in_array( 'à supprimer', $feuille['paragraphe'], true )
	&& in_array( 'TEI_archeoCHR_name:fld', $feuille['caractere'], true ) && ! in_array( 'TEI_archeoCHR_name:fld', $feuille['paragraphe'], true ),
	'la feuille installée donne ses styles par type, « à supprimer » compris' );
list( $choisis, $refuses ) = $ST::nettoyer( array(
	'num_autorisation' => 'à supprimer', 'responsabilites' => 'TEI_archeoCHR_authority',
	'autres_lieux' => 'TEI_archeoCHR_keywords_subjects', 'titre_notice' => 'TEI_Titre 2+notice',
	'responsable' => 'TEI_Titre 2+notice', 'annee' => 'Style inventé' ) );
na_verifier( array( 'num_autorisation' => 'à supprimer', 'responsabilites' => 'TEI_archeoCHR_authority', 'autres_lieux' => 'TEI_archeoCHR_keywords_subjects' ) == $choisis
	&& 2 === count( $refuses ),
	'un style de la feuille est retenu ; un style inventé ou du mauvais type est refusé ; l\'origine n\'est pas retenue', array( $choisis, $refuses ) );
update_option( Notice_Archeomed_Settings::OPTION_NAME, array_merge( (array) $reglages_avant, array( $ST::CLE => $choisis ) ) );
$ST::oublier();
$saisie_styles = array_merge( $saisie, array(
	'rubrique_principale' => 'I. Constructions et habitats civils', 'renvoi_1' => '', 'renvoi_2' => '', 'departement' => 'Calvados',
	'annee' => '2025', 'num_autorisation' => 'A-12', 'id_patriarche' => '', 'rapport_lien' => '', 'originaux_lien' => '',
	'resp_prenom' => 'Aude', 'resp_nom' => 'Ferrand', 'resp_inst' => 'Inrap', 'resp_email' => 'a@example.org',
	'coresp_prenom' => '', 'coresp_nom' => '', 'coresp_inst' => '', 'coresp_email' => '',
	'coauteur_prenom' => '', 'coauteur_nom' => '', 'coauteur_inst' => '', 'coauteur_email' => '',
	'commentaires' => '', 'texte_notice' => '<p>Le texte.</p>', 'remplace' => '', 'organismes' => array( 'Inrap' ), 'organisme' => 'Inrap',
	'lieux' => array( array( 'nom' => 'Caen', 'ark' => '' ) ), 'illustrations' => array(), 'nature' => '', 'nature_items' => array(),
	'pactols_places_items' => array( array( 'label' => 'Normandie', 'ark' => '' ) ), 'avis' => array(),
) );
$doc_styles = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
na_appel( $plugin, 'remplir_le_document', array( $doc_styles, $saisie_styles ) );
$corps = ( new ReflectionProperty( $doc_styles, 'body' ) );
$corps->setAccessible( true );
$xml_doc = implode( '', $corps->getValue( $doc_styles ) );
$id_de = function ( $nom ) use ( $doc_styles ) {
	$r = new ReflectionProperty( $doc_styles, 'styles' );
	$r->setAccessible( true );
	$t = $r->getValue( $doc_styles );
	return isset( $t[ $nom ] ) ? $t[ $nom ] : '?';
};
na_verifier( false !== strpos( $xml_doc, 'w:val="' . $id_de( 'à supprimer' ) . '"/></w:pPr><w:r><w:t xml:space="preserve">Numéro d’autorisation' )
	&& false !== strpos( $xml_doc, 'w:val="' . $id_de( 'TEI_archeoCHR_authority' ) . '"/></w:pPr><w:r><w:t xml:space="preserve">Responsable' )
	&& false === strpos( $xml_doc, 'texte. (Responsable' ),
	'le document suit la correspondance réglée : l\'autorisation à supprimer, les autorités dans leur paragraphe', $xml_doc );
na_verifier( 'archeoCHR_keywords_subjects' === $ST::rend( 'autres_lieux' ), 'les blocs d\'index nomment le style réglé' );
$doc_id = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
na_appel( $plugin, 'remplir_le_document', array( $doc_id, array_merge( $saisie_styles, array( 'id_patriarche' => '14 118 0012' ) ) ) );
$xml_id = implode( '', $corps->getValue( $doc_id ) );
na_verifier( false !== strpos( $xml_id, '>14 118 0012<' ), 'un identifiant Patriarche garde ses espaces : la typographie ne le prend pas pour un nombre', $xml_id );
update_option( Notice_Archeomed_Settings::OPTION_NAME, $reglages_avant );
$ST::oublier();

WP_CLI::log( 'Le jeu de données et le point final de la mention' );
$saisie_donnees = array_merge( $saisie_styles, array(
	'rapport_lien' => 'https://dolia.inrap.fr/flora/rapport-12', 'donnees_lien' => 'https://doi.org/10.34847/nkl.abcd1234',
	'coresp_prenom' => 'Claude', 'coresp_nom' => 'Burgelin', 'coresp_inst' => 'Université Sorbonne' ) );
$doc_donnees = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
na_appel( $plugin, 'remplir_le_document', array( $doc_donnees, $saisie_donnees ) );
$xml_donnees = implode( '', $corps->getValue( $doc_donnees ) );
$pos_rapport = strpos( $xml_donnees, 'Rapport final' );
$pos_donnees = strpos( $xml_donnees, 'Jeu de données' );
na_verifier( false !== $pos_rapport && false !== $pos_donnees && $pos_donnees > $pos_rapport
	&& 1 === preg_match( '#Jeu de données[^<]{0,6}</w:t></w:r><w:hyperlink r:id="rIdNA\d+">(?:(?!</w:hyperlink>).)*nkl\.abcd1234#us', $xml_donnees ),
	'au Word, le jeu de données suit le rapport, son adresse en lien', substr( $xml_donnees, (int) $pos_rapport, 900 ) );
$doc_sans = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
na_appel( $plugin, 'remplir_le_document', array( $doc_sans, $saisie_styles ) );
na_verifier( false === strpos( implode( '', $corps->getValue( $doc_sans ) ), 'Jeu de données' ),
	'sans jeu de données, pas de ligne vide au Word' );
na_verifier( false !== strpos( $xml_donnees, 'Université Sorbonne</w:t></w:r><w:r><w:t xml:space="preserve">.)</w:t>' )
	&& false === strpos( $xml_donnees, 'Sorbonne.).' ),
	'au Word, la mention de responsabilité finit par « Sorbonne.) », le point dans la parenthèse',
	substr( $xml_donnees, (int) strpos( $xml_donnees, 'Burgelin' ), 600 ) );
$courriel_donnees = na_appel( $plugin, 'build_notice', array( $saisie_donnees ) );
na_verifier( false !== strpos( $courriel_donnees, '<strong>Jeu de données :</strong> https://doi.org/10.34847/nkl.abcd1234' )
	&& false !== strpos( $courriel_donnees, 'Université Sorbonne.)' ),
	'le courriel donne le jeu de données et le point final dans la parenthèse', $courriel_donnees );
$page_donnees = na_appel( $plugin, 'page_de_relecture', array( 'I', array( $saisie_donnees ) ) );
na_verifier( false !== strpos( $page_donnees, 'Jeu de données : <a href="https://doi.org/10.34847/nkl.abcd1234">' )
	&& false !== strpos( $page_donnees, 'Université Sorbonne.)' ),
	'la page de relecture donne le jeu de données en lien et le point final', substr( $page_donnees, 0, 3000 ) );
na_verifier( array( 'le lien du jeu de données' ) === na_appel( $plugin, 'champs_changes', array( $saisie_donnees, array_merge( $saisie_donnees, array( 'donnees_lien' => 'https://zenodo.org/records/1' ) ), false ) )
	&& array() === na_appel( $plugin, 'champs_changes', array( $saisie_styles, array_merge( $saisie_styles, array( 'donnees_lien' => 'https://zenodo.org/records/1' ) ), false ) ),
	'un jeu de données changé se dit ; apparu sur une notice d\'avant le champ, non' );
$mention = function ( $champs ) use ( $plugin ) {
	return na_appel( $plugin, 'responsables_inline', array( $champs ) );
};
$mentions = array(
	$mention( array( 'resp_prenom' => 'Georges', 'resp_nom' => 'Perec', 'resp_inst' => 'CNRS', 'coresp_prenom' => 'Claude', 'coresp_nom' => 'Burgelin', 'coresp_inst' => 'Université Sorbonne' ) ),
	$mention( array( 'resp_prenom' => 'Georges', 'resp_nom' => 'Perec', 'resp_inst' => '' ) ),
	$mention( array( 'resp_prenom' => 'Georges', 'resp_nom' => 'Perec', 'resp_inst' => 'Univ. de Caen, etc.' ) ),
	$mention( array( 'resp_prenom' => 'Georges', 'resp_nom' => 'Perec', 'resp_inst' => 'Association « Les Amis du vieux Caen. »' ) ),
	$mention( array( 'resp_prenom' => 'Georges', 'resp_nom' => 'Perec', 'resp_inst' => 'Qui sait ?' ) ),
	$mention( array( 'resp_prenom' => 'Georges', 'resp_nom' => 'Perec', 'coauteur_prenom' => 'Paul', 'coauteur_nom' => 'Fournel', 'coauteur_inst' => 'Oulipo ' ) ),
	$mention( array( 'coresp_prenom' => 'Claude', 'coresp_nom' => 'Burgelin', 'coresp_inst' => array( 'x' ) ) ),
);
na_verifier( array(
		"Responsable de l'opération : Georges Perec, CNRS ; co-responsable de l'opération : Claude Burgelin, Université Sorbonne.",
		"Responsable de l'opération : Georges Perec.",
		"Responsable de l'opération : Georges Perec, Univ. de Caen, etc.",
		"Responsable de l'opération : Georges Perec, Association « Les Amis du vieux Caen. »",
		"Responsable de l'opération : Georges Perec, Qui sait ?",
		"Responsable de l'opération : Georges Perec ; notice rédigée avec : Paul Fournel, Oulipo.",
		"Responsable de l'opération :  ; co-responsable de l'opération : Claude Burgelin.",
	) === $mentions,
	'la mention prend son point final, sauf après une abréviation, un « ? » ou un guillemet qui le porte déjà ; une clef absente ne la casse pas', $mentions );
na_verifier( '' === na_appel( $plugin, 'point_final', array( "  \u{00A0}" ) ) && '.' === na_appel( $plugin, 'point_final', array( 'Inrap' ) )
	&& '' === na_appel( $plugin, 'point_final', array( 'Inrap…' ) ) && '' === na_appel( $plugin, 'point_final', array( 'Inrap. ' ) ),
	'le point final : rien sur du vide, sur des points de suspension ou un point suivi d\'une espace' );

WP_CLI::log( 'La prise d\'une notice' );
// Deux passages ne l'expédient jamais tous les deux : un seul gagne la prise,
// même quand l'autre l'a lue « en attente » juste avant qu'elle change.
$course = wp_insert_post( array( 'post_type' => Notice_Archeomed_File::CPT, 'post_status' => 'private', 'post_title' => 'Course' ) );
update_post_meta( $course, '_na_etat', 'en_attente' );
$file_course = $GLOBALS['notice_archeomed_file'];
$premiere = $file_course->prendre( $course );
$seconde  = $file_course->prendre( $course );
update_post_meta( $course, '_na_etat', 'en_attente' );
get_post_meta( $course, '_na_etat', true );   // la valeur en cache : « en_attente »
global $wpdb;
$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = 'en_cours' WHERE post_id = %d AND meta_key = '_na_etat'", $course ) );
$apres_un_autre = $file_course->prendre( $course );
update_post_meta( $course, '_na_etat', 'en_cours' );
update_post_meta( $course, '_na_prise', time() - Notice_Archeomed_File::ABANDON - 5 );
$reprise = $file_course->prendre( $course );
$reprise_bis = $file_course->prendre( $course );
wp_delete_post( $course, true );
na_verifier( $premiere && ! $seconde, 'une notice prise ne se reprend pas' );
na_verifier( ! $apres_un_autre, 'une notice qu\'un autre passage vient de prendre en base ne se prend pas, même lue « en attente » juste avant' );
na_verifier( $reprise && ! $reprise_bis, 'une prise abandonnée se reprend une fois, et une seule' );

// Un envoi qui tue son processus à chaque fois ne recommence pas sans fin :
// chaque prise reprise compte pour un essai.
$boucle = wp_insert_post( array( 'post_type' => Notice_Archeomed_File::CPT, 'post_status' => 'private', 'post_title' => 'Boucle' ) );
update_post_meta( $boucle, '_na_etat', 'en_cours' );
update_post_meta( $boucle, '_na_essais', 0 );
$reprises_comptees = 0;
for ( $tour = 0; $tour < Notice_Archeomed_File::ESSAIS_MAX + 2; $tour++ ) {
	update_post_meta( $boucle, '_na_etat', (string) get_post_meta( $boucle, '_na_etat', true ) === 'echec' ? 'echec' : 'en_cours' );
	update_post_meta( $boucle, '_na_prise', time() - Notice_Archeomed_File::ABANDON - 5 );
	if ( $file_course->prendre( $boucle ) ) {
		++$reprises_comptees;
	}
}
$etat_boucle = (string) get_post_meta( $boucle, '_na_etat', true );
// Un essai raté attend l'heure dite : la reprise périodique ne le représente pas avant.
update_post_meta( $boucle, '_na_etat', 'en_attente' );
update_post_meta( $boucle, '_na_pas_avant', time() + 3600 );
$appels_relance = 0;
add_action( Notice_Archeomed_File::HOOK_UNE, function ( $id ) use ( &$appels_relance, $boucle ) {
	if ( (int) $id === (int) $boucle ) { ++$appels_relance; }
}, 1 );
$file_course->relancer();
$apres_attente = $appels_relance;
update_post_meta( $boucle, '_na_pas_avant', time() - 5 );
$file_course->relancer();
wp_delete_post( $boucle, true );
na_verifier( Notice_Archeomed_File::ESSAIS_MAX - 1 === $reprises_comptees && 'echec' === $etat_boucle,
	'une prise reprise compte pour un essai : au cinquième, la notice est marquée en échec au lieu de repartir sans fin', array( $reprises_comptees, $etat_boucle ) );
na_verifier( 0 === $apres_attente && 1 === $appels_relance,
	'la reprise périodique respecte l\'heure du prochain essai d\'une notice qui vient d\'échouer', array( $apres_attente, $appels_relance ) );

WP_CLI::log( 'Les plafonds, le jeton, l\'adresse du visiteur' );
// L'adresse derrière un relais déclaré, et seulement alors.
na_verifier( true === $plugin::ip_dans_la_liste( '10.1.2.3', array( '10.0.0.0/8' ) ) && false === $plugin::ip_dans_la_liste( '11.1.2.3', array( '10.0.0.0/8' ) )
	&& true === $plugin::ip_dans_la_liste( '192.168.1.5', array( '192.168.1.5' ) ) && true === $plugin::ip_dans_la_liste( '192.168.3.200', array( '192.168.2.0/23' ) )
	&& true === $plugin::ip_dans_la_liste( '2001:db8::1', array( '2001:db8::/32' ) ) && false === $plugin::ip_dans_la_liste( '2001:db9::1', array( '2001:db8::/32' ) )
	&& false === $plugin::ip_dans_la_liste( '10.1.2.3', array( '2001:db8::/32' ) ),
	'les relais de confiance se reconnaissent, en adresses et en blocs CIDR, IPv4 comme IPv6' );
$serveur_avant = $_SERVER;
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';
$ip_sans_relais = na_appel( $plugin, 'get_client_ip' );
$_SERVER = $serveur_avant;
na_verifier( '203.0.113.9' === $ip_sans_relais, 'sans relais déclaré, l\'en-tête X-Forwarded-For est ignoré : n\'importe qui choisirait son adresse' );
// Le jeton du formulaire vit de 24 à 48 heures, pour ce seul formulaire.
$jeton_long = na_appel( $plugin, 'champ_du_jeton' );
preg_match( '/value="([a-z0-9]+)"/', $jeton_long, $m_jeton );
$jeton_valable = isset( $m_jeton[1] ) && na_appel( $plugin, 'verifier_le_jeton', array( $m_jeton[1] ) );
na_verifier( $jeton_valable && ! wp_verify_nonce( 'xx' . ( isset( $m_jeton[1] ) ? $m_jeton[1] : '' ), 'notice_archeomed_submit' ) && false === na_appel( $plugin, 'verifier_le_jeton', array( array( 'x' ) ) ),
	'le jeton du formulaire se vérifie ; un jeton faux ou d\'un autre type est refusé' );
// Le plafond de toute la revue.
$cle_globale  = na_appel( $plugin, 'cle_horaire', array( 'na_envois_global' ) );
$global_avant = get_transient( $cle_globale );
set_transient( $cle_globale, $plugin::MAX_SENDS_GLOBAL_PER_HOUR, HOUR_IN_SECONDS );
$plein = na_appel( $plugin, 'check_global_limit' );
set_transient( $cle_globale, 0, HOUR_IN_SECONDS );
$vide = na_appel( $plugin, 'check_global_limit' );
if ( false === $global_avant ) { delete_transient( $cle_globale ); } else { set_transient( $cle_globale, $global_avant, HOUR_IN_SECONDS ); }
na_verifier( false === $plein && true === $vide, 'le plafond de dépôts de toute la revue se referme à son seuil, et s\'ouvre en dessous' );
// Le compteur se tient à l'heure d'horloge : un dépôt ne repousse pas son échéance.
na_verifier( (bool) preg_match( '/^na_envois_global_\d{10}$/', $cle_globale ) && $cle_globale === 'na_envois_global_' . gmdate( 'YmdH' ),
	'les compteurs horaires portent l\'heure en cours dans leur clé, et repartent de zéro à chaque heure pleine', $cle_globale );
// La saisie gardée après un refus ne gonfle pas la base.
$_POST = array( 'texte_notice' => str_repeat( 'a', 900000 ), 'commentaires' => str_repeat( 'b', 900000 ), 'illus_titre' => array_fill( 0, 40, str_repeat( 'c', 9000 ) ) );
$jeton_gros = na_appel( $plugin, 'garder_la_saisie', array( 60 ) );
$gros = '' === $jeton_gros ? array() : get_transient( 'na_reprise_' . $jeton_gros );
if ( '' !== $jeton_gros ) { delete_transient( 'na_reprise_' . $jeton_gros ); }
$_POST = array();
na_verifier( empty( $gros ) || ( strlen( maybe_serialize( $gros ) ) < 1500000 && count( $gros['illus_titre'] ) <= 10 && strlen( $gros['commentaires'] ) <= 20000 ),
	'la saisie gardée après un refus est bornée : dix éléments par liste, 20 000 caractères par champ, 1,5 Mo en tout', array( strlen( maybe_serialize( $gros ) ) ) );

// Les notices ne se lisent qu'avec le droit de gérer l'extension, non avec celui d'un Éditeur.
$fiche_droits = wp_insert_post( array( 'post_type' => Notice_Archeomed_File::CPT, 'post_status' => 'private', 'post_title' => 'Droits' ) );
$editeur = wp_insert_user( array( 'user_login' => 'na_editeur_essai', 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
$droits_editeur = user_can( $editeur, 'read_post', $fiche_droits ) || user_can( $editeur, 'edit_post', $fiche_droits ) || user_can( $editeur, 'delete_post', $fiche_droits );
$droits_admin   = user_can( 1, 'read_post', $fiche_droits ) && user_can( 1, 'edit_post', $fiche_droits ) && user_can( 1, 'delete_post', $fiche_droits );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $editeur );
wp_delete_post( $fiche_droits, true );
na_verifier( ! $droits_editeur && $droits_admin, 'un Éditeur du site ne lit ni ne supprime les notices ; l\'administrateur le peut', array( $droits_editeur, $droits_admin ) );

WP_CLI::log( 'Les données personnelles' );
$fiche_perso = wp_insert_post( array( 'post_type' => Notice_Archeomed_File::CPT, 'post_status' => 'private', 'post_title' => 'Perso' ) );
update_post_meta( $fiche_perso, '_na_donnees', wp_slash( array(
	'resp_prenom' => 'Aude', 'resp_nom' => 'Ferrand', 'resp_email' => 'resp@example.org', 'resp_inst' => 'Inrap',
	'coauteur_prenom' => 'Jean', 'coauteur_nom' => 'Martin', 'coauteur_email' => 'Co.Auteur@example.org', 'coauteur_inst' => 'CNRS' ) ) );
update_post_meta( $fiche_perso, '_na_reference', 'ABC123' );
$confidentialite = new Notice_Archeomed_Confidentialite();
$export_resp = $confidentialite->exporter( 'resp@example.org' );
$export_co   = $confidentialite->exporter( 'co.auteur@EXAMPLE.org' );
$export_nul  = $confidentialite->exporter( 'inconnu@example.org' );
$effacement  = $confidentialite->effacer( 'resp@example.org' );
$effacement_nul = $confidentialite->effacer( 'inconnu@example.org' );
$exporteurs = apply_filters( 'wp_privacy_personal_data_exporters', array() );
wp_delete_post( $fiche_perso, true );
$valeurs_resp = array_column( $export_resp['data'][0]['data'], 'value', 'name' );
na_verifier( 1 === count( $export_resp['data'] ) && 'Responsable d’opération' === $valeurs_resp['Rôle'] && 'Aude' === $valeurs_resp['Prénom'] && 'ABC123' === $valeurs_resp['Référence']
	&& 1 === count( $export_co['data'] ) && 'Co-auteur' === array_column( $export_co['data'][0]['data'], 'value', 'name' )['Rôle'] && array() === $export_nul['data']
	&& isset( $exporteurs['notice-archeomed'] ),
	'l\'exportation de WordPress rend les notices où figure l\'adresse, avec le rôle de la personne ; une adresse inconnue n\'a rien', array( $export_resp, $export_co ) );
na_verifier( false === $effacement['items_removed'] && true === $effacement['items_retained'] && ! empty( $effacement['messages'] )
	&& false === $effacement_nul['items_retained'],
	'l\'effacement de WordPress n\'efface aucune notice, le dit, et laisse la décision à la rédaction' );

WP_CLI::log( 'Les bandeaux de l\'administration' );
// Ils s'exécutent sur chaque page d'administration : une erreur fatale dans
// l'un d'eux rend toute l'administration inaccessible. On les joue donc,
// connecté comme administrateur, dans les deux positions de la protection.
wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/admin.php';
set_current_screen( 'edit-' . Notice_Archeomed_File::CPT );
ob_start();
do_action( 'admin_notices' );
$bandeaux = ob_get_clean();
na_verifier( is_string( $bandeaux ), 'les bandeaux de l\'administration s\'affichent sans erreur' );
na_verifier( false === Notice_Archeomed_Pactols::protection_suspendue() || 'production' !== wp_get_environment_type(),
	'la vérification anti-robot ne se suspend jamais sur un site de production' );

WP_CLI::log( 'Les réglages enregistrés sur une option vide' );
// WordPress repasse la valeur par add_option tant que l'option vaut son
// défaut : le second nettoyage, sans les témoins d'onglet, perdait tout.
$reglages_avant = get_option( Notice_Archeomed_Settings::OPTION_NAME, null );
$pages_reglages = new Notice_Archeomed_Settings();
$pages_reglages->register_settings();
delete_option( Notice_Archeomed_Settings::OPTION_NAME );
update_option( Notice_Archeomed_Settings::OPTION_NAME, array( 'styles_presents' => 1,
	'styles' => array( 'responsabilites' => 'TEI_archeoCHR_authority' ) ) );
wp_cache_delete( 'alloptions', 'options' );
$enregistres = get_option( Notice_Archeomed_Settings::OPTION_NAME );
unregister_setting( Notice_Archeomed_Settings::OPTION_GROUP, Notice_Archeomed_Settings::OPTION_NAME );
if ( null === $reglages_avant ) {
	delete_option( Notice_Archeomed_Settings::OPTION_NAME );
} else {
	update_option( Notice_Archeomed_Settings::OPTION_NAME, $reglages_avant );
}
Notice_Archeomed_Styles::oublier();
na_verifier( isset( $enregistres['styles']['responsabilites'] ) && 'TEI_archeoCHR_authority' === $enregistres['styles']['responsabilites'],
	'le premier réglage des styles se garde, même sur une option absente', $enregistres );

WP_CLI::log( 'Les petites capitales de l\'auteur' );
$pc_propre = na_appel( $plugin, 'clean_richtext', array(
	'<p><span style="color:red">Le <span class="autre na-pc" onclick="x">Moyen Âge</span></span> et <span class="autre">ici</span>.</p>' ) );
na_verifier( '<p>Le <span class="na-pc">Moyen Âge</span> et ici.</p>' === $pc_propre,
	'seules les petites capitales de l\'éditeur gardent leur « span », sans autre attribut', $pc_propre );
$pc_docx = implode( '', ( new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) ) )->html_to_paragraphs( $pc_propre ) );
na_verifier( false !== strpos( $pc_docx, '<w:smallCaps/></w:rPr><w:t xml:space="preserve">Moyen Âge</w:t>' )
	&& 1 === substr_count( $pc_docx, '<w:smallCaps/>' ),
	'le Word les rend en petites capitales, et rien d\'autre', $pc_docx );
$pc_rtf = implode( '', ( new Notice_Archeomed_RTF( Notice_Archeomed_Pactols::feuille_de_style( 'rtf' ) ) )->html_to_paragraphs( $pc_propre ) );
na_verifier( false !== strpos( $pc_rtf, '{\\scaps ' ) && substr_count( $pc_rtf, '{' ) === substr_count( $pc_rtf, '}' ),
	'le RTF de repli aussi, ses groupes équilibrés', $pc_rtf );
na_verifier( '<p>Sans balise.</p>' === na_appel( $plugin, 'clean_richtext', array( '<p>Sans balise.</p>' ) ),
	'un texte déjà enregistré passe tel quel' );

WP_CLI::log( 'Les siècles au Word, au courriel et à la fiche' );
$doc_xii = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
$sans_balises = function ( $xml ) {
	// « [xii] » pour les petites capitales, « ^e » pour l'exposant.
	preg_match_all( '#<w:r>(?:<w:rPr>(.*?)</w:rPr>)?(.*?)</w:r>#s', $xml, $m, PREG_SET_ORDER );
	$o = '';
	foreach ( $m as $r ) {
		$t = html_entity_decode( preg_replace( '#<[^>]+>#', '', $r[2] ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
		$o .= false !== strpos( $r[1], 'smallCaps' ) ? '[' . $t . ']' : ( false !== strpos( $r[1], 'superscript' ) ? '^' . $t : $t );
	}
	return str_replace( "\u{00A0}", '_', $o );
};
$siecles_vus = array();
foreach ( array(
	'XIIe siècle'                       => '[xii]^e_s.',
	'XII<sup>e</sup> siècle'            => '[xii]^e_s.',
	'XIIe s.'                           => '[xii]^e_s.',
	'xiie s.'                           => '[xii]^e_s.',
	'du Xe au XIIe siècle'              => 'du [x]^e au [xii]^e_s.',
	'fin du XIIe-début du XIIIe siècle' => 'fin du [xii]^e-début du [xiii]^e_s.',
	'Louis XIV et le XVe siècle'        => 'Louis XIV et le [xv]^e_s.',
) as $saisi => $attendu ) {
	$obtenu = $sans_balises( implode( '', $doc_xii->html_to_paragraphs( '<p>' . $saisi . '</p>' ) ) );
	if ( $obtenu !== $attendu ) {
		$siecles_vus[ $saisi ] = $obtenu;
	}
}
na_verifier( empty( $siecles_vus ), 'le Word compose chaque forme de siècle saisie, liaisons comprises', $siecles_vus );
$relu_xii = na_appel( $plugin, 'siecles_du_html', array( '<p>fin du XIIe-début du XIIIe siècle, xiie s.</p>' ) );
na_verifier( 3 === substr_count( $relu_xii, 'all-small-caps' ) && false !== strpos( $relu_xii, "<sup>e</sup>\u{00A0}s.</p>" ),
	'la page de relecture aussi, et « s. » prend son insécable', $relu_xii );
$courriel_xii = na_appel( $plugin, 'build_notice', array( array_merge( $saisie_styles, array(
	'texte_notice' => '<p>Le mur du XIIe siècle et le <span class="na-pc">Moyen Âge</span> : fin.</p>', 'reference' => 'REFXII' ) ) ) );
na_verifier( false !== strpos( $courriel_xii, '<span style="font-variant: small-caps; font-variant-caps: all-small-caps">XII</span><sup>e</sup>' )
	&& false !== strpos( $courriel_xii, '<span class="na-pc" style="font-variant:small-caps">Moyen Âge</span>' )
	&& false !== strpos( $courriel_xii, "Âge</span>\u{00A0}: fin" ) && false !== strpos( $courriel_xii, 'REFXII' ),
	'le courriel et la fiche montrent le texte aux normes, petites capitales et référence comprises', $courriel_xii );
$courriel_t1 = na_appel( $plugin, 'build_notice', array( array_merge( $saisie_styles, array(
	'texte_notice' => '<h3>Le site</h3><p>Le mur.</p>', 'illustrations' => array( array( 'rang' => 1, 'titre' => 'Plan',
		'legende' => 'Le plan.', 'credits' => '', 'type' => '' ) ) ) ) ) );
na_verifier( false !== strpos( $courriel_t1, 'CAEN (Calvados)' )
	&& false !== strpos( $courriel_t1, '<h3 style="font-size:15px;font-weight:bold;margin:14px 0 4px;">Le site</h3>' )
	&& false !== strpos( $courriel_t1, '<!--na-figure-1--><strong>Fig. 1</strong> — Plan' ),
	'le titre du courriel met la commune en capitales, l\'intertitre T1 n\'y est qu\'en gras, et chaque figure marque la place de son aperçu', $courriel_t1 );
$t1_png  = trailingslashit( get_temp_dir() ) . 'na-essai-apercu.png';
file_put_contents( $t1_png, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=' ) );
$t1_tif  = trailingslashit( get_temp_dir() ) . 'na-essai-original.tif';
file_put_contents( $t1_tif, 'II*' );
$t1_corps   = '<p><!--na-figure-1--><strong>Fig. 1</strong></p><p><!--na-figure-2--><strong>Fig. 2</strong></p>';
$t1_joindre = array( '/doc.docx', 'Caen_fig1_apercu.png' => $t1_png, 'Caen_fig2.tif' => $t1_tif );
$t1_args    = array( &$t1_corps, &$t1_joindre, array( 1 => $t1_png, 2 => $t1_tif ) );
$t1_integrees = na_appel( $plugin, 'figures_dans_le_corps', $t1_args );
na_verifier( 1 === count( $t1_integrees ) && 'na-figure-1@archeomed' === $t1_integrees[0]['cid']
	&& 'Caen_fig1_apercu.png' === $t1_integrees[0]['nom']
	&& false !== strpos( $t1_corps, '<img src="cid:na-figure-1@archeomed" width="1"' ) && false !== strpos( $t1_corps, '<!--na-figure-2-->' )
	&& ! isset( $t1_joindre['Caen_fig1_apercu.png'] ) && isset( $t1_joindre['Caen_fig2.tif'] ) && 2 === count( $t1_joindre ),
	'l\'aperçu d\'une figure se pose dans le corps au-dessus de sa légende et quitte les pièces jointes ; un TIFF reste joint',
	array( $t1_corps, $t1_joindre ) );
@unlink( $t1_png );
@unlink( $t1_tif );

WP_CLI::log( 'Le titre et les figures sous les normes' );
$doc_titre = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
$lieu_dit_xml = na_appel( $plugin, 'run_xml', array( $doc_titre, array( 'text' => "l'enceinte du XIIe siècle", 'i' => true ) ) );
na_verifier( false !== strpos( $lieu_dit_xml, "l\u{2019}enceinte" ) && false !== strpos( $lieu_dit_xml, '<w:smallCaps/>' )
	&& 0 === substr_count( $lieu_dit_xml, '<w:r><w:t' ) && false !== strpos( $lieu_dit_xml, '<w:i/>' ),
	'le lieu-dit du titre prend apostrophe et siècles, en gardant son italique', $lieu_dit_xml );

WP_CLI::log( 'La mise en forme du lieu-dit' );
$LD = 'Notice_Archeomed_Lieu_Dit';
$ld_runs = $LD::morceaux( ' <span class="na-pc">xii</span><sup>e</sup> s., <em>Pourquoi-Pas</em>&amp;Cie <b>x</b> ' );
na_verifier( array(
		array( 'text' => 'xii', 'i' => false, 'pc' => true, 'sup' => false ),
		array( 'text' => 'e', 'i' => false, 'pc' => false, 'sup' => true ),
		array( 'text' => ' s., ', 'i' => false, 'pc' => false, 'sup' => false ),
		array( 'text' => 'Pourquoi-Pas', 'i' => true, 'pc' => false, 'sup' => false ),
		array( 'text' => '&Cie x', 'i' => false, 'pc' => false, 'sup' => false ),
	) === $ld_runs,
	'la forme se lit en morceaux : petites capitales, exposant, italique ; une balise inconnue laisse son texte, les bouts sont rognés', $ld_runs );
na_verifier( 'La rue <em>Haute</em>' === $LD::accorder( 'la rue <em>Haute</em>.', 'La rue Haute' ),
	'la forme reçoit la capitale initiale et perd le point final, comme le champ brut' );
na_verifier( '' === $LD::accorder( 'la rue <em>Basse</em>', 'La rue Haute' ) && '' === $LD::accorder( 'La rue Haute', 'La rue Haute' )
	&& '' === $LD::accorder( '<em>A</em>', '' ),
	'une forme qui ne dit pas le même texte, ou ne marque rien, ne se garde pas' );
na_verifier( '<em>a</em>&lt;b&gt;' === $LD::accorder( '<img src=x onerror=alert(1)><em>a</em>&lt;b&gt;<script>', 'a<b>' )
	&& '' === $LD::accorder( str_repeat( '<em>a</em>', 600 ), str_repeat( 'a', 600 ) ),
	'la forme gardée est réécrite : ni attribut ni balise étrangère, les chevrons échappés, une forme démesurée refusée' );
$ld_d = array( 'lieu_dit' => 'Épave du Pourquoi-Pas, XIIe s', 'lieu_dit_forme' => 'Épave du <em>Pourquoi-Pas</em>, <span class="na-pc">XII</span><sup>e</sup> s' );
$ld_html = $LD::html_du_titre( $ld_d );
na_verifier( Notice_Archeomed_Normes::lieu_dit_en_italique()
	? '<em>Épave du </em>Pourquoi-Pas<em>, </em><span class="na-pc" style="font-variant:small-caps"><em>XII</em></span><em><sup>e</sup></em><em> s</em>' === $ld_html
	: false !== strpos( $ld_html, '<em>Pourquoi-Pas</em>' ),
	'au titre, l\'italique de l\'auteur passe en romain dans le lieu-dit en italique', $ld_html );
na_verifier( '<em>Autre lieu</em>' === $LD::html_du_titre( array( 'lieu_dit' => 'Autre lieu', 'lieu_dit_forme' => $ld_d['lieu_dit_forme'] ) )
	&& '<em>Sans forme</em>' === $LD::html_du_titre( array( 'lieu_dit' => 'Sans forme' ) ) && '' === $LD::html_du_titre( array() ),
	'un lieu-dit retouché ailleurs perd sa forme, jamais une lettre' );
$ld_xml = na_appel( $plugin, 'runs_xml', array( $doc_titre, $LD::morceaux_du_titre( $ld_d ) ) );
$ld_vus = array();
preg_match_all( '#<w:r>(?:<w:rPr>(.*?)</w:rPr>)?<w:t[^>]*>(.*?)</w:t></w:r>#s', $ld_xml, $ld_m, PREG_SET_ORDER );
foreach ( $ld_m as $r ) {
	$ld_vus[] = ( false !== strpos( $r[1], '<w:i/>' ) ? 'i' : '' ) . ( false !== strpos( $r[1], 'smallCaps' ) ? 'pc' : '' )
		. ( false !== strpos( $r[1], 'superscript' ) ? 'sup' : '' ) . ':' . html_entity_decode( $r[2], ENT_QUOTES | ENT_XML1, 'UTF-8' );
}
$ld_romain = implode( '', array_map( function ( $v ) { return substr( $v, 1 ); }, preg_grep( '/^:/', $ld_vus ) ) );
na_verifier( 'Pourquoi-Pas' === $ld_romain && 1 === count( preg_grep( '/^ipc:xii$/i', $ld_vus ) ) && in_array( 'isup:e', $ld_vus, true )
	&& 0 === count( preg_grep( '/^[^:]*:.*<\/?(em|sup|span)/', $ld_vus ) ),
	'au Word, le nom du navire en romain, le siècle en petites capitales et son exposant, dans le titre en italique', $ld_vus );
$ld_rtf = na_appel( $plugin, 'runs_xml', array( null, $LD::morceaux_du_titre( $ld_d ) ) );
na_verifier( false !== strpos( $ld_rtf, 'Pourquoi-Pas' ) && false !== strpos( $ld_rtf, '\\scaps ' ) && false !== strpos( $ld_rtf, '\\super ' )
	&& substr_count( $ld_rtf, '{' ) === substr_count( $ld_rtf, '}' ),
	'le RTF de repli aussi, ses groupes équilibrés', $ld_rtf );
$ld_post = $_POST;
$_POST   = array( 'lieu_dit' => 'la rue <i>Haute</i>.', 'lieu_dit_forme' => array( 'x' ) );
na_appel( $plugin, 'entrees_attendues' );
na_verifier( ! isset( $_POST['lieu_dit_forme'] ), 'une forme envoyée en tableau est écartée' );
$_POST = array( 'lieu_dit' => 'la rue Haute.', 'lieu_dit_forme' => wp_slash( 'la rue <em>Haute</em>.' ) );
$ld_recu = na_appel( $plugin, 'textes_postes', array( array( 'lieu_dit' ) ) );
$_POST = array( 'lieu_dit' => 'la rue Haute.' );
$ld_sans = na_appel( $plugin, 'textes_postes', array( array( 'lieu_dit' ) ) );
$_POST = $ld_post;
na_verifier( 'La rue Haute' === $ld_recu['lieu_dit'] && 'La rue <em>Haute</em>' === $ld_recu['lieu_dit_forme'] && ! array_key_exists( 'lieu_dit_forme', $ld_sans ),
	'la forme postée est reçue avec le lieu-dit ; sans elle, rien ne s\'écrit à sa place', array( $ld_recu, $ld_sans ) );
na_verifier( array( 'Falaise', 'Coulonces (Vire Normandie)' ) === array_column( na_appel( $plugin, 'lieux_de', array( array(
	'departement' => '(Calvados)', 'lieux' => array( array( 'nom' => 'Falaise (Calvados)', 'ark' => '' ),
		array( 'nom' => 'Coulonces (Vire Normandie)', 'ark' => 'javascript:alert(1)' ) ) ) ) ), 'nom' ),
	'la précision d\'un homonyme s\'ôte quand elle redit la parenthèse, et seulement alors' );
na_verifier( array( '', '' ) === array_column( na_appel( $plugin, 'lieux_de', array( array( 'departement' => 'Calvados',
	'lieux' => array( array( 'nom' => 'A', 'ark' => 'javascript:alert(1)' ), array( 'nom' => 'B', 'ark' => 'https://exemple.org/ark:/26678/pcrtX' ) ) ) ) ), 'ark' ),
	'un lieu ne garde pas en lien un ARK qui n\'est pas de Frantiq' );
na_verifier( 'https://ark.frantiq.fr/ark:/26678/pcrtA1' === Notice_Archeomed_Thesaurus::ark_propre( ' http://ark.frantiq.fr/ark:/26678/pcrtA1 ' )
	&& '' === Notice_Archeomed_Thesaurus::ark_propre( 'https://ark.frantiq.fr/ark:/26678/../../x' ),
	'un ARK en http est ramené à https ; un chemin forgé est refusé' );

WP_CLI::log( 'Les sorties qui se recalculent' );
$N::oublier();
$sortie = na_notice( array_merge( $saisie_styles, array( 'annee' => '2004/2005',
	'pactols_subjects_items' => array( array( 'label' => 'Saint-Étienne', 'ark' => '' ) ),
	'nature_items' => array( array( 'label' => 'Fouille préventive', 'ark' => '' ) ),
	'texte_notice' => '<p>Le mur<sup>1</sup> de 12 m<sup>2</sup> [3].</p>' ) ) );
update_post_meta( $sortie, '_na_reference', 'REFOUT' );
$saisie_sortie = na_appel( $plugin, 'saisie_de', array( $sortie, false ) );
na_appel( $plugin, 'sortir_de_l_attente', array( $sortie ) );
na_verifier( '2004-2005' === $saisie_sortie['annee'] && 'REFOUT' === $saisie_sortie['reference'],
	'l\'année suit le trait réglé à la sortie, et la référence d\'une notice ancienne la rejoint', $saisie_sortie['annee'] );
na_verifier( 'Saint-Étienne' === na_appel( $plugin, 'graphie_imprimee', array( $saisie_sortie['pactols_subjects_items'][0] ) )
	&& 'fouille préventive' === na_appel( $plugin, 'graphie_imprimee', array( $saisie_sortie['nature_items'][0] ) ),
	'un mot-clé libre garde sa capitale ; une nature prend le bas de casse' );
$doc_sortie = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
na_appel( $plugin, 'remplir_le_document', array( $doc_sortie, $saisie_sortie ) );
$xml_sortie = implode( '', $corps->getValue( $doc_sortie ) );
na_verifier( false !== strpos( $xml_sortie, 'Référence du dépôt' ) && false !== strpos( $xml_sortie, 'REFOUT' )
	&& false !== strpos( $xml_sortie, 'appels de note' ) && false !== strpos( $xml_sortie, '«' . $nb . '1' . $nb . '»' )
	&& false !== strpos( $xml_sortie, '«' . $nb . '3' . $nb . '»' ) && false === strpos( $xml_sortie, '«' . $nb . '2' . $nb . '»' ),
	'le Word donne la référence et les appels de note, sans prendre « m² » pour un appel', $xml_sortie );
wp_delete_post( $sortie, true );
$coupe = na_appel( $plugin, 'limit_string', array( str_repeat( 'a', 12 ), 10, 'le lieu-dit' ) );
$coupes = na_appel( $plugin, 'avis_des_coupes' );
na_verifier( 10 === strlen( $coupe ) && 1 === count( $coupes ) && false !== strpos( $coupes[0], 'le lieu-dit' ),
	'une coupe se nomme dans les avis', $coupes );
na_verifier( 'Falaise (Calvados). Château — 2025' === $plugin->titre_de_liste( array( 'lieux' => array( array( 'nom' => 'Falaise (Calvados)', 'ark' => '' ) ),
	'departement' => 'Calvados', 'lieu_dit' => 'Château', 'annee' => '2025' ) ),
	'le titre de la liste porte le lieu-dit, sans redire la parenthèse', $plugin->titre_de_liste( array( 'commune' => 'Falaise (Calvados)', 'departement' => 'Calvados', 'lieu_dit' => 'Château', 'annee' => '2025' ) ) );
$homonymes = na_appel( $plugin, 'contacts_list', array( array_merge( $saisie_styles, array( 'resp_prenom' => 'Jean', 'resp_nom' => 'Martin',
	'resp_email' => 'jean1@example.org', 'coauteur_prenom' => 'Jean', 'coauteur_nom' => 'Martin', 'coauteur_email' => 'jean2@example.org' ) ) ) );
na_verifier( 2 === count( $homonymes ), 'deux personnes homonymes gardent chacune leur adresse', $homonymes );
$doc_mort = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
na_appel( $plugin, 'remplir_le_document', array( $doc_mort, array_merge( $saisie_styles, array( 'pactols_subjects_items' => array(
	array( 'label' => 'rapport final', 'ark' => $a, 'deprecie' => true, 'remplacant' => 'https://ark.frantiq.fr/ark:/26678/pcrtVLJq3mXSe8' ) ) ) ) ) );
na_verifier( false !== strpos( implode( '', $corps->getValue( $doc_mort ) ), 'remplacé par 26678/pcrtVLJq3mXSe8' ),
	'un terme retiré nomme son remplaçant' );

WP_CLI::log( 'Le fascicule et ses renvois' );
$fasc_d = function ( $commune, $rubrique, $renvoi = '' ) use ( $saisie_styles ) {
	return array_merge( $saisie_styles, array( 'commune' => $commune, 'lieux' => array( array( 'nom' => $commune, 'ark' => '' ) ),
		'rubrique_principale' => $rubrique, 'renvoi_1' => $renvoi, 'nature' => 'Fouille préventive' ) );
};
$renvoyee = na_notice( $fasc_d( 'Caen', 'I. Constructions et habitats civils', 'II. Constructions et habitats ecclésiastiques' ) );
$GLOBALS['notice_archeomed_file']->poser_le_classement( $renvoyee, $fasc_d( 'Caen', 'I. Constructions et habitats civils' ) );
// Un renvoi venu de V porte sa matière : il se rangeait après « II. 2. » et
// rouvrait « II. 1. » derrière elle.
$d_artisan = array_merge( $fasc_d( 'Lisieux', 'V. Installations artisanales', 'II. Constructions et habitats ecclésiastiques' ), array( 'rubrique_matiere' => 'A' ) );
$artisan   = na_notice( $d_artisan );
$GLOBALS['notice_archeomed_file']->poser_le_classement( $artisan, $d_artisan );
$erreur_fasc = '';
$fascicule   = na_appel( $plugin, 'fabriquer_le_fascicule', array( 'II. Constructions et habitats ecclésiastiques', array(
	$fasc_d( 'Bayeux', 'II. Constructions et habitats ecclésiastiques' ), $fasc_d( 'Falaise', 'II. Constructions et habitats ecclésiastiques' ),
	array_merge( $fasc_d( 'Vire', 'II. Constructions et habitats ecclésiastiques' ), array( 'nature' => 'Prospection archéologique' ) ) ), &$erreur_fasc ) );
$xml_fasc = '';
if ( '' !== $fascicule && class_exists( 'ZipArchive' ) ) {
	$zip_fasc = new ZipArchive();
	if ( true === $zip_fasc->open( $fascicule ) ) {
		$xml_fasc = (string) $zip_fasc->getFromName( 'word/document.xml' );
		$zip_fasc->close();
	}
	@unlink( $fascicule );
}
$xml_fasc = str_replace( "\u{00A0}", ' ', $xml_fasc );
// Le sommaire du coordinateur de II range ses renvois comme le fascicule :
// Caen puis Lisieux, l'un et l'autre sous « II. 1. ».
$renvois_ii = Notice_Archeomed_Relecture::sommaire( 'II. Constructions et habitats ecclésiastiques' )['renvois'];
$ordre_ii   = array_values( array_intersect( array_map( 'intval', wp_list_pluck( $renvois_ii, 'id' ) ), array( $renvoyee, $artisan ) ) );
$familles_ii = array_values( array_unique( array_map( function ( $e ) { return (string) preg_replace( '/[\s\x{00A0}\x{202F}]+/u', ' ', $e['famille'] ); },
	array_filter( $renvois_ii, function ( $e ) use ( $renvoyee, $artisan ) { return in_array( (int) $e['id'], array( $renvoyee, $artisan ), true ); } ) ) ) );
na_verifier( array( $renvoyee, $artisan ) === $ordre_ii && 1 === count( $familles_ii ) && 0 === strpos( $familles_ii[0], 'II. 1.' ),
	'au sommaire aussi, les renvois se rangent par commune sous la sous-rubrique du fascicule, sans la matière de V', array( $ordre_ii, $familles_ii ) );
wp_delete_post( $renvoyee, true );
wp_delete_post( $artisan, true );
$pos_lisieux = strpos( $xml_fasc, 'Lisieux' );
$pos_ii_2    = strpos( $xml_fasc, 'II. 2. –' );
na_verifier( 1 === substr_count( $xml_fasc, 'II. 1. – Opérations de terrain' ) && false === strpos( $xml_fasc, '>I. 1. –' )
	&& false !== strpos( $xml_fasc, 'Voir dans la rubrique' ),
	'un renvoi se range sous la sous-rubrique du fascicule, sans en ouvrir une fausse', '' !== $erreur_fasc ? $erreur_fasc : substr_count( $xml_fasc, '1. –' ) );
na_verifier( false !== $pos_lisieux && false !== $pos_ii_2 && $pos_lisieux < $pos_ii_2 && 1 === substr_count( $xml_fasc, 'II. 2. –' ),
	'un renvoi venu de V se range avec sa famille, avant « II. 2. », sans la matière de sa rubrique', array( $pos_lisieux, $pos_ii_2, substr_count( $xml_fasc, '. –' ) ) );

WP_CLI::log( 'Une expansion tombée n\'est pas un terme lu' );
$ark_tombe = 'https://ark.frantiq.fr/ark:/26678/pcrtTombe';
$noeud_tombe = array( $ark_tombe => array(
	Notice_Archeomed_Thesaurus::DCT . 'identifier' => array( array( 'value' => '4242' ) ),
	Notice_Archeomed_Thesaurus::SKOS . 'prefLabel' => array( array( 'value' => 'terme', 'lang' => 'fr' ) ),
) );
$simuler = function ( $pre, $args, $url ) use ( $noeud_tombe ) {
	if ( false !== strpos( $url, 'pactols.frantiq.fr' ) ) {
		if ( false !== strpos( $url, '/expansion' ) ) {
			return new WP_Error( 'http_request_failed', 'cURL error 28' );
		}
		return array( 'headers' => array(), 'body' => wp_json_encode( $noeud_tombe ), 'cookies' => array(), 'filename' => null,
			'response' => array( 'code' => 200, 'message' => 'OK' ) );
	}
	return $pre;
};
add_filter( 'pre_http_request', $simuler, 10, 3 );
$tombe = Notice_Archeomed_Thesaurus::resoudre( $ark_tombe, '', 'TH_1' );
remove_filter( 'pre_http_request', $simuler, 10 );
na_verifier( null === $tombe && Notice_Archeomed_Thesaurus::echec_jusqua( $ark_tombe, '', 'TH_1' ) > time(),
	'un concept sans chaîne n\'est pas résolu : il compte pour un échec d\'une heure', $tombe );
delete_transient( na_appel( 'Notice_Archeomed_Thesaurus', 'clef', array( $ark_tombe, '', 'TH_1' ) ) );
$sans_chaine = $concept( $a, '2026-10-01' );
$sans_chaine['chemin'] = array();
na_verifier( array( $a ) === na_appel( $plugin, 'termes_manquants', array( $saisie, array( $a => $sans_chaine, $b => $concept( $b, '2026-10-01' ) ) ) ),
	'un terme gardé sans chaîne par une version d\'avant se compte comme non lu, et se relira' );

WP_CLI::log( 'Le nom de l\'expéditeur' );
$reglages_exp = get_option( Notice_Archeomed_Settings::OPTION_NAME, array() );
$regler_exp = function ( $valeurs ) use ( $reglages_exp ) {
	update_option( Notice_Archeomed_Settings::OPTION_NAME, array_merge( (array) $reglages_exp, $valeurs ) );
};
$regler_exp( array( 'expediteur_nom' => '', 'smtp_nom' => '' ) );
$nom_defaut = Notice_Archeomed_Settings::nom_d_expediteur();
$regler_exp( array( 'expediteur_nom' => '', 'smtp_nom' => 'Secrétariat' ) );
$nom_smtp = Notice_Archeomed_Settings::nom_d_expediteur();
$regler_exp( array( 'expediteur_nom' => "Revue \"AM\" <b>x</b>\n", 'smtp_nom' => 'Secrétariat' ) );
$nom_propre = Notice_Archeomed_Settings::nom_d_expediteur();
$regler_exp( array( 'expediteur_nom' => '', 'smtp_nom' => '' ) );
na_verifier( 'Revue Archéologie médiévale' === $nom_defaut && 'Secrétariat' === $nom_smtp && 'Revue AM x' === $nom_propre,
	'le nom de l\'expéditeur : la revue par défaut, le nom SMTP à défaut du réglage, sans balise ni guillemet qui couperait l\'en-tête',
	array( $nom_defaut, $nom_smtp, $nom_propre ) );
$facteur = new stdClass();
$facteur->FromName = 'hébergement no-reply';
Notice_Archeomed_Pactols::nommer_l_expediteur( $facteur );
$nom_hors = $facteur->FromName;
$nom_pendant = null;
$capter_nom = function ( $retour, $atts ) use ( &$nom_pendant ) {
	$m = new stdClass();
	$m->FromName = 'hébergement no-reply';
	Notice_Archeomed_Pactols::nommer_l_expediteur( $m );
	$nom_pendant = array( Notice_Archeomed_Pactols::courriel_en_cours(), $m->FromName );
	return true;
};
add_filter( 'pre_wp_mail', $capter_nom, 10, 2 );
Notice_Archeomed_Pactols::courriel( 'a@example.org', 'Objet', 'Corps' );
remove_filter( 'pre_wp_mail', $capter_nom, 10 );
update_option( Notice_Archeomed_Settings::OPTION_NAME, $reglages_exp );
na_verifier( 'hébergement no-reply' === $nom_hors && array( true, 'Revue Archéologie médiévale' ) === $nom_pendant
	&& false === Notice_Archeomed_Pactols::courriel_en_cours()
	&& PHP_INT_MAX === has_action( 'phpmailer_init', array( 'Notice_Archeomed_Pactols', 'nommer_l_expediteur' ) ),
	'un courriel du formulaire part au nom de la revue ; un autre courriel du site garde le sien', array( $nom_hors, $nom_pendant ) );

WP_CLI::log( 'Le lien de correction' );
$_POST['reprise_jeton'] = 'jetondessai1';
set_transient( 'na_reprise_jetondessai1', array( 'remplace' => 'ANCIEN' ), 60 );
na_appel( $plugin, 'oublier_le_jeton_repris', array( 'NOUVEL' ) );
$avis_double = na_appel( $plugin, 'avis_du_lien_repris' );
$_GET['notice_reprise'] = 'jetondessai1';
$etat_lien = na_appel( $plugin, 'etat_du_lien_de_reprise' );
$_GET['notice_reprise'] = 'jetoninconnu';
$reprise_prop = new ReflectionProperty( $plugin, 'reprise' );
$reprise_prop->setAccessible( true );
$reprise_prop->setValue( $plugin, null );
$etat_inconnu = na_appel( $plugin, 'etat_du_lien_de_reprise' );
$reprise_prop->setValue( $plugin, null );
unset( $_POST['reprise_jeton'], $_GET['notice_reprise'] );
delete_transient( 'na_reprise_faite_jetondessai1' );
na_verifier( array( 'etat' => 'utilise', 'reference' => 'NOUVEL' ) === $etat_lien && 'expire' === $etat_inconnu['etat']
	&& 1 === count( $avis_double ) && false !== strpos( $avis_double[0], 'NOUVEL' ),
	'un lien qui a servi dit pour quel dépôt ; un lien inconnu se dit expiré ; le renvoi se signale en doublon', array( $etat_lien, $etat_inconnu, $avis_double ) );
$_SERVER['HTTP_REFERER'] = 'https://phishing.example/depot/';
$formulaire = na_appel( $plugin, 'url_du_formulaire' );
unset( $_SERVER['HTTP_REFERER'] );
na_verifier( false === strpos( $formulaire, 'phishing.example' ), 'un référent d\'un autre site ne donne pas l\'adresse du formulaire', $formulaire );

WP_CLI::log( 'Une correction qui ne reprend pas les figures' );
$corrigee = na_notice( $saisie );
update_post_meta( $corrigee, '_na_reference', 'AVANT1' );
update_post_meta( $corrigee, '_na_illustrations', array( '/tmp/a.jpg', '/tmp/b.tif' ) );
$phrase = na_appel( $plugin, 'figures_de_la_remplacee', array( array( 'remplace' => 'AVANT1', 'illustrations' => array( array( 'rang' => 1 ) ) ) ) );
wp_delete_post( $corrigee, true );
na_verifier( false !== strpos( $phrase, 'AVANT1' ) && false !== strpos( $phrase, '2 figures' ) && false !== strpos( $phrase, 'restent sur le site' ),
	'la rédaction lit que les figures de la notice remplacée sont encore sur le site', $phrase );

WP_CLI::log( 'Le Word disparu avant l\'envoi' );
$reglages_avant = get_option( Notice_Archeomed_Settings::OPTION_NAME, array() );
update_option( Notice_Archeomed_Settings::OPTION_NAME, array_merge( (array) $reglages_avant, array(
	'destinataires' => array( array( 'email' => 'redaction@example.org', 'notices' => 1, 'recap' => 0 ) ) ) ) );
$partis = array();
$capter = function ( $rendu, $atts ) use ( &$partis ) {
	$pieces = array();
	foreach ( (array) $atts['attachments'] as $piece ) {
		$pieces[] = array( basename( $piece ), file_exists( $piece ) );
	}
	$partis[] = array( 'a' => $atts['to'], 'pieces' => $pieces, 'corps' => $atts['message'], 'objet' => $atts['subject'] );
	return true;
};
add_filter( 'pre_wp_mail', $capter, 10, 2 );
$perdue = na_notice( array_merge( $saisie_styles, array( 'resp_email' => '' ) ) );
foreach ( array( '_na_etat' => 'en_attente', '_na_notice' => '<p>La notice.</p>', '_na_reference' => 'PERDU1',
	'_na_document' => '/tmp/na-essai-word-disparu.docx', '_na_fichiers' => array( '/tmp/na-essai-word-disparu.docx' ) ) as $cle => $valeur ) {
	update_post_meta( $perdue, $cle, $valeur );
}
$issue_perdue = $plugin->expedier_de_la_file( $perdue );
$etat_perdue  = get_post_meta( $perdue, '_na_etat', true );
// Un Word qui ne peut pas se refaire : les deux feuilles de style, DOCX et
// RTF, sont illisibles. (Les illustrations en chaîne, qui servaient ici,
// se lisent désormais.)
$illisible = na_notice( array_merge( $saisie_styles, array( 'resp_email' => '' ) ) );
foreach ( array( '_na_etat' => 'en_attente', '_na_notice' => '<p>La notice.</p>', '_na_document' => '' ) as $cle => $valeur ) {
	update_post_meta( $illisible, $cle, $valeur );
}
$depot_feuilles = wp_upload_dir();
$dossier_feuilles = trailingslashit( $depot_feuilles['basedir'] ) . 'notice-archeomed/';
wp_mkdir_p( $dossier_feuilles );
$feuilles_avant = array();
foreach ( array( Notice_Archeomed_Pactols::DOCX_TEMPLATE, Notice_Archeomed_Pactols::RTF_TEMPLATE ) as $nom_feuille ) {
	$feuilles_avant[ $nom_feuille ] = file_exists( $dossier_feuilles . $nom_feuille ) ? file_get_contents( $dossier_feuilles . $nom_feuille ) : null;
	file_put_contents( $dossier_feuilles . $nom_feuille, 'feuille illisible' );
}
$issue_illisible = $plugin->expedier_de_la_file( $illisible );
foreach ( $feuilles_avant as $nom_feuille => $contenu_feuille ) {
	if ( null === $contenu_feuille ) {
		@unlink( $dossier_feuilles . $nom_feuille );
	} else {
		file_put_contents( $dossier_feuilles . $nom_feuille, $contenu_feuille );
	}
}
$note_illisible  = get_post_meta( $illisible, '_na_note', true );
$preparee = na_notice( $saisie_styles );
update_post_meta( $preparee, '_na_etat', 'en_attente' );
update_post_meta( $preparee, '_na_preparation', time() );
$issue_preparee = $plugin->expedier_de_la_file( $preparee );
remove_filter( 'pre_wp_mail', $capter, 10 );
update_option( Notice_Archeomed_Settings::OPTION_NAME, $reglages_avant );
foreach ( array( $perdue, $illisible, $preparee ) as $id ) {
	wp_delete_post( $id, true );
}
na_verifier( 'partie' === $issue_perdue && 'envoyee' === $etat_perdue && isset( $partis[0]['pieces'][0] )
	&& '.docx' === substr( $partis[0]['pieces'][0][0], -5 ) && $partis[0]['pieces'][0][1]
	&& false !== strpos( $partis[0]['objet'], 'réf. PERDU1' ),
	'le Word disparu se refait à partir de la saisie et part avec le courriel', $partis );
na_verifier( 'partie' === $issue_illisible && isset( $partis[1] ) && empty( $partis[1]['pieces'] )
	&& false !== strpos( $partis[1]['corps'], 'Le document Word n’est pas joint' ) && false !== strpos( $note_illisible, 'document Word' ),
	'un Word qui ne se refait pas se dit dans le courriel et dans la liste', array( $issue_illisible, $note_illisible ) );
na_verifier( 'en_preparation' === $issue_preparee && 2 === count( $partis ),
	'une notice que la requête de l\'auteur prépare encore ne part pas', $issue_preparee );

WP_CLI::log( 'Les ateliers abandonnés' );
$atelier_mort = trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp/atelier-essaimort';
wp_mkdir_p( $atelier_mort . '/icono/hr' );
file_put_contents( $atelier_mort . '/icono/hr/f.jpg', 'x' );
touch( $atelier_mort, time() - 2 * DAY_IN_SECONDS );
Notice_Archeomed_Paquet::purger_les_ateliers( DAY_IN_SECONDS );
na_verifier( ! is_dir( $atelier_mort ), 'l\'atelier d\'un assemblage mort s\'efface' );

WP_CLI::log( 'La sortie vers Pactols' );
$refuse_proxy = function ( $pre, $args, $url ) {
	return false !== strpos( $url, 'pactols.frantiq.fr' ) ? new WP_Error( 'http_request_failed', 'cURL error 56: Received HTTP code 403 from proxy after CONNECT' ) : $pre;
};
add_filter( 'pre_http_request', $refuse_proxy, 10, 3 );
$essai_pactols = na_appel( $pages_reglages, 'test_pactols' );
remove_filter( 'pre_http_request', $refuse_proxy, 10 );
na_verifier( false === $essai_pactols['ok'] && false !== strpos( $essai_pactols['message'], 'proxy' ),
	'l\'essai dit que le proxy refuse Pactols', $essai_pactols );

WP_CLI::log( 'L\'essai de charge' );
$ch_option = Notice_Archeomed_Settings::OPTION_ESSAI_DE_CHARGE;
delete_option( $ch_option );
na_verifier( null === Notice_Archeomed_Settings::essai_de_charge() && ! Notice_Archeomed_Settings::cle_d_essai_valide( '' ),
	'sans essai ouvert, aucune clé ne vaut' );
Notice_Archeomed_Settings::ouvrir_l_essai_de_charge();
$ch = Notice_Archeomed_Settings::essai_de_charge();
na_verifier( is_array( $ch ) && 40 === strlen( $ch['cle'] ) && Notice_Archeomed_Settings::cle_d_essai_valide( $ch['cle'] )
	&& ! Notice_Archeomed_Settings::cle_d_essai_valide( strrev( $ch['cle'] ) )
	&& ! Notice_Archeomed_Settings::cle_d_essai_valide( substr( $ch['cle'], 0, 20 ) )
	&& ! Notice_Archeomed_Settings::cle_d_essai_valide( array( $ch['cle'] ) )
	&& (int) $ch['jusqua'] > time() + DAY_IN_SECONDS && (int) $ch['jusqua'] <= time() + 2 * DAY_IN_SECONDS,
	'un essai ouvert pour deux jours : sa clé vaut, aucune autre', $ch );
update_option( $ch_option, array( 'cle' => $ch['cle'], 'jusqua' => time() - 1 ), false );
na_verifier( ! Notice_Archeomed_Settings::cle_d_essai_valide( $ch['cle'] ), 'un essai échu ne fait plus rien passer' );
Notice_Archeomed_Settings::fermer_l_essai_de_charge();
na_verifier( false === get_option( $ch_option ), 'fermé, l\'essai ne laisse rien' );
$ch_id    = na_notice( array( 'commune' => 'Essaiville', 'resp_nom' => 'Fictif', 'resp_prenom' => 'Jean', 'resp_email' => 'jean@charge.invalid' ) );
$ch_vraie = na_notice( array( 'commune' => 'Vraieville', 'resp_nom' => 'Réel', 'resp_prenom' => 'Anne', 'resp_email' => 'anne@charge.test' ) );
update_post_meta( $ch_id, '_na_essai', 1 );
$ch_ann = array_column( Notice_Archeomed_Annuaire::recenser(), 'courriel' );
na_verifier( ! in_array( 'jean@charge.invalid', $ch_ann, true ) && in_array( 'anne@charge.test', $ch_ann, true ),
	'l\'annuaire ignore les auteurs d\'une notice d\'essai, pas les autres', $ch_ann );
$ch_etats  = apply_filters( 'display_post_states', array(), get_post( $ch_id ) );
$ch_autres = apply_filters( 'display_post_states', array(), get_post( $ch_vraie ) );
na_verifier( isset( $ch_etats['na_essai'] ) && ! isset( $ch_autres['na_essai'] ),
	'la liste marque « Essai » la seule notice d\'essai', array( $ch_etats, $ch_autres ) );
na_verifier( array( $ch_id ) === Notice_Archeomed_Settings::notices_d_essai(), 'les notices d\'essai se retrouvent toutes, et elles seules',
	Notice_Archeomed_Settings::notices_d_essai() );
$ch_r = Notice_Archeomed_Settings::ecarter_les_notices_d_essai();
na_verifier( ! empty( $ch_r['ok'] ) && 'trash' === get_post_status( $ch_id ) && 'private' === get_post_status( $ch_vraie )
	&& array() === Notice_Archeomed_Settings::notices_d_essai(),
	'la corbeille ne prend que les notices d\'essai', $ch_r );
wp_delete_post( $ch_id, true );
wp_delete_post( $ch_vraie, true );

WP_CLI::log( 'La sortie vers Cloudflare' );
$cf_vu     = array();
$cf_reponse = null;
$cf_simul  = function ( $pre, $args, $url ) use ( &$cf_vu, &$cf_reponse ) {
	if ( false === strpos( $url, 'challenges.cloudflare.com' ) ) {
		return $pre;
	}
	$cf_vu[] = $args['body'];
	return $cf_reponse;
};
add_filter( 'pre_http_request', $cf_simul, 10, 3 );
$cf_reponse = new WP_Error( 'http_request_failed', 'cURL error 56: Received HTTP code 403 from proxy after CONNECT' );
$essai_cf   = na_appel( $pages_reglages, 'test_cloudflare', array( '' ) );
na_verifier( false === $essai_cf['ok'] && false !== strpos( $essai_cf['message'], 'proxy' ) && false !== strpos( $essai_cf['message'], 'challenges.cloudflare.com' ),
	'l\'essai dit que le proxy refuse Cloudflare', $essai_cf );
na_verifier( 1 === count( $cf_vu ) && '' !== $cf_vu[0]['secret'], 'sans clé, l\'essai interroge quand même Cloudflare', $cf_vu );
$cf_reponse = array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'headers' => array(), 'cookies' => array(), 'body' => '{"success":false,"error-codes":["invalid-input-secret"]}' );
$essai_cf   = na_appel( $pages_reglages, 'test_cloudflare', array( '' ) );
na_verifier( true === $essai_cf['ok'] && false !== strpos( $essai_cf['message'], 'joignable' ) && false !== strpos( $essai_cf['message'], 'enregistrez' ),
	'sans clé, Cloudflare joint suffit, et l\'essai demande la clé', $essai_cf );
$essai_cf = na_appel( $pages_reglages, 'test_cloudflare', array( 'cle-mauvaise' ) );
na_verifier( false === $essai_cf['ok'] && false !== strpos( $essai_cf['message'], 'refuse la clé' ), 'une clé refusée se dit', $essai_cf );
$cf_reponse = array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'headers' => array(), 'cookies' => array(), 'body' => '{"success":false,"error-codes":["invalid-input-response"]}' );
$essai_cf   = na_appel( $pages_reglages, 'test_cloudflare', array( 'cle-bonne' ) );
na_verifier( true === $essai_cf['ok'] && false !== strpos( $essai_cf['message'], 'reconnue' ) && 'cle-bonne' === end( $cf_vu )['secret'],
	'une clé reconnue se dit', $essai_cf );
remove_filter( 'pre_http_request', $cf_simul, 10 );

WP_CLI::log( 'La liaison à sDrive' );
// Un sDrive simulé, qui répond comme le vrai : la demande, son relevé
// avant puis après l'autorisation, la révocation, et le dossier en WebDAV.
$S        = 'Notice_Archeomed_Sdrive';
$sd_mot   = 'mot-d-application';
$sd_vus   = array();
$sd_etat  = array( 'autorise' => false, 'ailleurs' => false, 'proxy' => false, 'sans_id' => false );
$sd_auth  = 'Basic ' . base64_encode( '862404:' . $sd_mot );
$sd_simul = function ( $pre, $args, $url ) use ( &$sd_vus, &$sd_etat, $sd_auth, $sd_mot ) {
	if ( 0 !== strpos( $url, 'https://sdrive.cnrs.fr/' ) ) {
		return $pre;
	}
	$methode  = isset( $args['method'] ) ? strtoupper( $args['method'] ) : 'GET';
	$sd_vus[] = $methode . ' ' . $url;
	$rep      = function ( $code, $corps = '' ) {
		return array( 'headers' => array(), 'body' => $corps, 'response' => array( 'code' => $code, 'message' => '' ),
			'cookies' => array(), 'filename' => null );
	};
	$chemin = (string) wp_parse_url( $url, PHP_URL_PATH );
	if ( $sd_etat['proxy'] ) {
		return new WP_Error( 'http_request_failed', 'cURL error 56: Received HTTP code 403 from proxy after CONNECT' );
	}
	if ( '/status.php' === $chemin ) {
		return $rep( 200, wp_json_encode( array( 'installed' => true, 'maintenance' => false, 'versionstring' => '33.0.10 Enterprise' ) ) );
	}
	if ( '/index.php/login/v2' === $chemin && 'POST' === $methode ) {
		return $rep( 200, wp_json_encode( array(
			'poll'  => array( 'token' => 'jeton-de-releve',
				'endpoint' => $sd_etat['ailleurs'] ? 'https://ailleurs.example/login/v2/poll' : 'https://sdrive.cnrs.fr/login/v2/poll' ),
			'login' => 'https://sdrive.cnrs.fr/login/v2/flow/demande',
		) ) );
	}
	if ( '/login/v2/poll' === $chemin && 'POST' === $methode ) {
		$jeton = isset( $args['body']['token'] ) ? $args['body']['token'] : '';
		return ( $sd_etat['autorise'] && 'jeton-de-releve' === $jeton )
			? $rep( 200, wp_json_encode( array( 'server' => 'https://sdrive.cnrs.fr', 'loginName' => '862404', 'appPassword' => $sd_mot ) ) )
			: $rep( 404 );
	}
	$authentifie = isset( $args['headers']['Authorization'] ) && $sd_auth === $args['headers']['Authorization'];
	if ( '/ocs/v2.php/core/apppassword' === $chemin && 'DELETE' === $methode ) {
		return $rep( $authentifie && isset( $args['headers']['OCS-APIRequest'] ) ? 200 : 401 );
	}
	// L'identifiant interne diffère du nom de connexion, comme pour un
	// compte de la fédération d'identité.
	if ( '/ocs/v2.php/cloud/user' === $chemin && 'GET' === $methode ) {
		if ( $sd_etat['sans_id'] ) {
			return $rep( 500 );
		}
		return ( $authentifie && isset( $args['headers']['OCS-APIRequest'] ) )
			? $rep( 200, wp_json_encode( array( 'ocs' => array( 'meta' => array( 'statuscode' => 200 ),
				'data' => array( 'id' => 'f3a1-862404', 'display-name' => 'Compte de la revue' ) ) ) ) )
			: $rep( 401 );
	}
	if ( 0 === strpos( $chemin, '/remote.php/dav/files/f3a1-862404/Arch%C3%A9ologie%20m%C3%A9di%C3%A9vale/Notices/' ) ) {
		if ( ! $authentifie ) {
			return $rep( 401 );
		}
		$codes = array( 'PROPFIND' => 207, 'PUT' => 201, 'DELETE' => 204 );
		return $rep( isset( $codes[ $methode ] ) ? $codes[ $methode ] : 405 );
	}
	return $rep( 404 );
};
add_filter( 'pre_http_request', $sd_simul, 10, 3 );
delete_option( $S::OPTION );
$sd_rendu = function () {
	ob_start();
	na_appel( 'Notice_Archeomed_Settings', 'liaison_sdrive' );
	return ob_get_clean();
};

$sd_essai = na_appel( $pages_reglages, 'test_sdrive' );
na_verifier( true === $sd_essai['ok'] && false !== strpos( $sd_essai['message'], 'reliez le site' )
	&& false !== strpos( $sd_rendu(), 'Relier le site à sDrive' ),
	'sans liaison, l\'essai dit sDrive joignable et invite à relier le site, sans parler de wp-config.php', $sd_essai );

$sd_fin = $S::finir_la_liaison();
na_verifier( false === $sd_fin['ok'] && false !== strpos( $sd_fin['message'], 'Aucune demande' ),
	'terminer une liaison jamais demandée le dit', $sd_fin );

$sd_etat['ailleurs'] = true;
$sd_dem              = $S::demander_la_liaison();
$sd_etat['ailleurs'] = false;
na_verifier( false === $sd_dem['ok'] && null === $S::demande_en_cours(),
	'une demande dont le relevé mènerait hors de sDrive est refusée : le jeton n\'y part pas', $sd_dem );

$sd_dem = $S::demander_la_liaison();
$sd_en  = $S::demande_en_cours();
na_verifier( true === $sd_dem['ok'] && is_array( $sd_en ) && 'https://sdrive.cnrs.fr/login/v2/flow/demande' === $sd_en['login']
	&& false !== strpos( $sd_rendu(), 'Terminer la liaison' ) && false !== strpos( $sd_rendu(), 'login/v2/flow/demande' ),
	'la demande ouverte, la page donne le lien où autoriser et le bouton qui termine', $sd_en );

$sd_fin = $S::finir_la_liaison();
na_verifier( false === $sd_fin['ok'] && false !== strpos( $sd_fin['message'], 'pas encore reçu' ) && null !== $S::demande_en_cours(),
	'avant l\'autorisation, terminer le dit et garde la demande', $sd_fin );

$sd_etat['autorise'] = true;
$sd_fin              = $S::finir_la_liaison();
$sd_lien             = $S::liaison();
$sd_acces            = $S::acces();
na_verifier( true === $sd_fin['ok'] && null === $S::demande_en_cours() && '862404' === $sd_lien['compte']
	&& false === strpos( serialize( get_option( $S::OPTION ) ), $sd_mot )
	&& is_array( $sd_acces ) && $sd_mot === $sd_acces['mot_de_passe'] && '862404' === $sd_acces['compte']
	&& 'https://sdrive.cnrs.fr/remote.php/dav/files/f3a1-862404/' === $sd_acces['webdav'],
	'l\'autorisation donnée, le site est relié : le mot de passe est scellé dans la base, et se descelle pour parler à sDrive ; '
	. 'l\'adresse WebDAV prend l\'identifiant interne du compte, la connexion son nom',
	array( $sd_fin, $sd_lien, $sd_acces ) );

$sd_etat['sans_id'] = true;
$sd_sans            = na_appel( $S, 'identifiant_du_compte', array( 'https://sdrive.cnrs.fr', '862404', $sd_mot ) );
$sd_etat['sans_id'] = false;
$sd_ancien          = $sd_lien;
unset( $sd_ancien['id'] );
update_option( $S::OPTION, $sd_ancien, false );
$sd_repli = $S::acces();
update_option( $S::OPTION, $sd_lien, false );
na_verifier( '' === $sd_sans && 'https://sdrive.cnrs.fr/remote.php/dav/files/862404/' === $sd_repli['webdav'],
	'sans identifiant rendu par sDrive, ou pour une liaison plus ancienne, l\'adresse retombe sur le nom de connexion',
	array( $sd_sans, $sd_repli ) );
na_verifier( false !== strpos( $sd_rendu(), 'Délier le site' ) && false !== strpos( $sd_rendu(), '862404' ),
	'relié, la page nomme le compte et offre de délier' );

$sd_essai = na_appel( $pages_reglages, 'test_sdrive' );
na_verifier( false === $sd_essai['ok'] && false !== strpos( $sd_essai['message'], 'aucun dossier' ),
	'relié sans dossier, l\'essai refuse d\'écrire à la racine du compte', $sd_essai );

$S::enregistrer_le_dossier( ' /Archéologie médiévale/ ./Notices/ ' );
$sd_lien = $S::liaison();
na_verifier( 'Archéologie médiévale/Notices' === $sd_lien['dossier'] && '862404' === $sd_lien['compte'],
	'le dossier se range sans barres ni segments vides, et la liaison reste', $sd_lien );

$sd_vus   = array();
$sd_essai = na_appel( $pages_reglages, 'test_sdrive' );
$sd_gestes = implode( ' ', array_map( function ( $v ) {
	return strtok( $v, ' ' );
}, $sd_vus ) );
na_verifier( true === $sd_essai['ok'] && false !== strpos( $sd_essai['message'], 's’efface' )
	&& 'GET PROPFIND PUT DELETE' === $sd_gestes,
	'relié, avec un dossier : l\'essai lit, écrit, puis efface son fichier, accents du chemin compris', array( $sd_essai, $sd_vus ) );

// Les clés de wp-config.php changées : le sceau ne s'ouvre plus.
$sd_garde          = $S::liaison();
$sd_faux           = $sd_garde;
$sd_faux['scelle'] = base64_encode( str_repeat( 'x', 60 ) );
update_option( $S::OPTION, $sd_faux, false );
$sd_essai = na_appel( $pages_reglages, 'test_sdrive' );
na_verifier( $S::liaison_illisible() && null === $S::acces() && false !== strpos( $sd_essai['message'], 'ne se lit plus' ),
	'une liaison dont le sceau ne s\'ouvre plus le dit, et n\'envoie rien à sDrive', $sd_essai );
update_option( $S::OPTION, $sd_garde, false );

$sd_vus = array();
$sd_del = $S::delier();
$sd_lien = $S::liaison();
na_verifier( true === $sd_del['ok'] && array( 'DELETE https://sdrive.cnrs.fr/ocs/v2.php/core/apppassword' ) === $sd_vus
	&& empty( $sd_lien['compte'] ) && 'Archéologie médiévale/Notices' === $sd_lien['dossier'] && null === $S::acces(),
	'délier révoque le mot de passe chez sDrive, l\'oublie ici et garde le dossier', array( $sd_del, $sd_vus, $sd_lien ) );

$sd_etat['proxy'] = true;
$sd_essai         = na_appel( $pages_reglages, 'test_sdrive' );
$sd_dem           = $S::demander_la_liaison();
$sd_etat['proxy'] = false;
na_verifier( false === $sd_essai['ok'] && false !== strpos( $sd_essai['message'], 'proxy' )
	&& false === $sd_dem['ok'] && false !== strpos( $sd_dem['message'], 'proxy' ),
	'un proxy qui refuse sDrive se nomme, à l\'essai comme à la liaison', array( $sd_essai, $sd_dem ) );

remove_filter( 'pre_http_request', $sd_simul, 10 );
delete_option( $S::OPTION );
$S::oublier_la_demande();

WP_CLI::log( 'Le dépôt, en envoi différé puis immédiat' );
// La requête de l'auteur se joue entière : la redirection finale, qui
// sortirait du script, est interceptée et rendue.
$reglages_avant = get_option( Notice_Archeomed_Settings::OPTION_NAME, array() );
$sortir = function ( $vers ) {
	throw new RuntimeException( (string) $vers );
};
$deposes = array();
$capter_depot = function ( $rendu, $atts ) use ( &$deposes ) {
	$pieces = array();
	foreach ( (array) $atts['attachments'] as $piece ) {
		$pieces[] = basename( $piece );
	}
	$deposes[] = array( 'objet' => $atts['subject'], 'pieces' => $pieces, 'corps' => $atts['message'] );
	return true;
};
add_filter( 'wp_redirect', $sortir );
add_filter( 'pre_wp_mail', $capter_depot, 10, 2 );
$jouer_un_depot = function ( $mode ) use ( $plugin, $reglages_avant ) {
	update_option( Notice_Archeomed_Settings::OPTION_NAME, array_merge( (array) $reglages_avant, array(
		'mode_envoi'    => $mode,
		'destinataires' => array( array( 'email' => 'redaction@example.org', 'notices' => 1, 'recap' => 0 ) ) ) ) );
	// Une seconde d'écart par dépôt : deux sceaux identiques se
	// prendraient l'un pour l'autre, déjà servi.
	static $decalage = 0;
	$ts_defi = time() - 30 - ( $decalage++ );
	$defi    = na_appel( $plugin, 'defi_du_puzzle', array( $ts_defi ) );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SERVER['HTTP_REFERER']   = 'https://phishing.example/depot/';
	$_POST = array(
		'notice_archeomed_envoi' => '1', 'notice_archeomed_nonce' => wp_create_nonce( 'notice_archeomed_submit' ),
		'na_ts' => (string) $ts_defi, 'na_curseur' => (string) $defi['cible'], 'na_preuve' => $defi['preuve'],
		'rubrique_principale' => 'I. Constructions et habitats civils', 'commune' => array( 'Caen' ), 'commune_ark' => array( '' ),
		'departement' => 'Calvados', 'lieu_dit' => 'Château ' . $mode, 'annee' => '2025', 'nature' => array( 'Fouille préventive' ),
		'organisme' => array( 'Inrap' ), 'resp_prenom' => 'Aude', 'resp_nom' => 'Ferrand', 'resp_email' => 'aude.' . $mode . '@example.org',
		'resp_inst' => 'Inrap', 'texte_notice' => '<p>Le mur du XIIe siècle.</p>',
		'pactols_periods' => wp_slash( wp_json_encode( array( array( 'label' => 'XIIe siècle', 'ark' => '' ) ) ) ),
		'pactols_subjects' => wp_slash( wp_json_encode( array( array( 'label' => 'château', 'ark' => '' ) ) ) ),
	);
	$vers = '';
	try {
		$plugin->handle_submission();
	} catch ( RuntimeException $e ) {
		$vers = $e->getMessage();
	}
	$_POST = array();
	unset( $_SERVER['HTTP_REFERER'] );
	parse_str( (string) wp_parse_url( $vers, PHP_URL_QUERY ), $retour );
	$ref = isset( $retour['notice_ref'] ) ? $retour['notice_ref'] : '';
	$ids = '' === $ref ? array() : get_posts( array( 'post_type' => Notice_Archeomed_File::CPT, 'post_status' => 'private',
		'fields' => 'ids', 'meta_query' => array( array( 'key' => '_na_reference', 'value' => $ref ) ) ) );
	return array( 'vers' => $vers, 'ref' => $ref, 'id' => empty( $ids ) ? 0 : (int) $ids[0] );
};
$differe = $jouer_un_depot( 'differe' );
$differe_etat = array(
	'etat'        => get_post_meta( $differe['id'], '_na_etat', true ),
	'document'    => (string) get_post_meta( $differe['id'], '_na_document', true ),
	'preparation' => get_post_meta( $differe['id'], '_na_preparation', true ),
	'word_fait'   => file_exists( (string) get_post_meta( $differe['id'], '_na_document', true ) ),
	'prevue'      => (bool) wp_next_scheduled( Notice_Archeomed_File::HOOK_UNE, array( $differe['id'] ) ),
	'courriels'   => count( $deposes ),
);
$immediat = $jouer_un_depot( 'immediat' );
$donnees_immediat = get_post_meta( $immediat['id'], '_na_donnees', true );
$immediat_etat = array(
	'etat'       => get_post_meta( $immediat['id'], '_na_etat', true ),
	'correction' => isset( $donnees_immediat['correction_url'] ) ? $donnees_immediat['correction_url'] : '',
);
$deposes_immediat = $deposes;
// En différé, le Word ne se fait plus dans la requête de l'auteur : la file
// le refait d'après la saisie gardée, et le courriel le joint comme avant.
$differe_issue    = $differe['id'] ? $plugin->expedier_de_la_file( $differe['id'] ) : '';
$differe_courriel = array_slice( $deposes, count( $deposes_immediat ) );
remove_filter( 'wp_redirect', $sortir );
remove_filter( 'pre_wp_mail', $capter_depot, 10 );
update_option( Notice_Archeomed_Settings::OPTION_NAME, $reglages_avant );
foreach ( array( $differe, $immediat ) as $depot ) {
	if ( $depot['id'] ) {
		$doc_depot = (string) get_post_meta( $depot['id'], '_na_document', true );
		if ( '' !== $doc_depot ) {
			@unlink( $doc_depot );
		}
		wp_unschedule_hook( Notice_Archeomed_File::HOOK_UNE );
		wp_delete_post( $depot['id'], true );
	}
}
na_verifier( $differe['id'] > 0 && 'en_attente' === $differe_etat['etat'] && '' === $differe_etat['document']
	&& ! $differe_etat['word_fait'] && '' === $differe_etat['preparation'] && $differe_etat['prevue']
	&& 0 === $differe_etat['courriels'] && false !== strpos( $differe['vers'], 'notice_envoyee=1' ),
	'en différé : la notice est inscrite, la préparation levée, l\'envoi programmé, ni Word ni courriel dans la requête', array( $differe, $differe_etat ) );
na_verifier( 'partie' === $differe_issue && ! empty( $differe_courriel )
	&& false !== strpos( $differe_courriel[0]['objet'], 'réf. ' . $differe['ref'] )
	&& 1 === count( $differe_courriel[0]['pieces'] ) && '.docx' === substr( $differe_courriel[0]['pieces'][0], -5 ),
	'en différé : la file fait le Word et le joint au courriel', array( $differe_issue, $differe_courriel ) );
na_verifier( $immediat['id'] > 0 && 'envoyee' === $immediat_etat['etat'] && 2 === count( $deposes_immediat )
	&& isset( $deposes_immediat[0] ) && false !== strpos( $deposes_immediat[0]['objet'], 'réf. ' . $immediat['ref'] )
	&& 1 === count( $deposes_immediat[0]['pieces'] ) && '.docx' === substr( $deposes_immediat[0]['pieces'][0], -5 ),
	'en immédiat : la notice est inscrite comme en différé, et part par la file, Word joint', array( $immediat, $immediat_etat, $deposes_immediat ) );
na_verifier( false === strpos( $differe['vers'] . $immediat['vers'] . $immediat_etat['correction'], 'phishing' )
	&& '' !== $immediat_etat['correction'],
	'ni le retour ni le lien de correction ne suivent un référent étranger', array( $differe['vers'], $immediat_etat['correction'] ) );

WP_CLI::log( 'Les siècles liés par « au début du », « ou début »' );
// Le premier siècle de ces liaisons restait en capitales au Word.
$doc_liaisons = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
$liaisons_vues = array();
foreach ( array(
	'du XIIe au début du XIIIe s.' => 'du [xii]^e au début du [xiii]^e_s.',
	'fin XIe ou début XIIe s.'     => 'fin [xi]^e ou début [xii]^e_s.',
	'entre le XIe et le XIIe siècle' => 'entre le [xi]^e et le [xii]^e_s.',
) as $saisi => $attendu ) {
	$obtenu = $sans_balises( implode( '', $doc_liaisons->html_to_paragraphs( '<p>' . $saisi . '</p>' ) ) );
	if ( $obtenu !== $attendu ) {
		$liaisons_vues[ $saisi ] = $obtenu;
	}
}
na_verifier( empty( $liaisons_vues ), 'chaque siècle d\'une liaison passe en petites capitales, le premier compris', $liaisons_vues );

WP_CLI::log( 'Le titre de figure sous les normes' );
$doc_figure = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
na_appel( $plugin, 'remplir_le_document', array( $doc_figure, array_merge( $saisie_styles, array( 'illustrations' => array(
	array( 'rang' => 1, 'titre' => 'Plan de l\'enceinte au XIIe siècle', 'legende' => '', 'credits' => '' ) ) ) ) ) );
$xml_figure = implode( '', $corps->getValue( $doc_figure ) );
na_verifier( false === strpos( $xml_figure, 'au XIIe siècle' ) && false !== strpos( $xml_figure, '<w:smallCaps/></w:rPr><w:t xml:space="preserve">xii</w:t>' )
	&& false !== strpos( $xml_figure, "l\u{2019}enceinte" ),
	'le titre d\'une figure prend apostrophe et siècles', $xml_figure );

WP_CLI::log( 'Un concept que Pactols dit « non retiré »' );
// « owl:deprecated » à « false » se lisait comme retiré : la présence de la
// propriété comptait, non sa valeur.
$ark_vif    = 'https://ark.frantiq.fr/ark:/26678/pcrtVif';
$ark_racine = 'https://ark.frantiq.fr/ark:/26678/pcrtVifRacine';
$retire_vaut = 'false';
$simuler_retire = function ( $pre, $args, $url ) use ( $ark_vif, $ark_racine, &$retire_vaut ) {
	if ( false === strpos( $url, 'pactols.frantiq.fr' ) ) {
		return $pre;
	}
	$graphe = array(
		$ark_vif    => array(
			Notice_Archeomed_Thesaurus::DCT . 'identifier' => array( array( 'value' => '4343' ) ),
			Notice_Archeomed_Thesaurus::SKOS . 'prefLabel' => array( array( 'value' => 'terme', 'lang' => 'fr' ) ),
			Notice_Archeomed_Thesaurus::SKOS . 'broader'   => array( array( 'value' => $ark_racine ) ),
			Notice_Archeomed_Thesaurus::OWL . 'deprecated' => array( array( 'value' => $retire_vaut ) ),
		),
		$ark_racine => array( Notice_Archeomed_Thesaurus::SKOS . 'prefLabel' => array( array( 'value' => 'racine', 'lang' => 'fr' ) ) ),
	);
	return array( 'headers' => array(), 'body' => wp_json_encode( $graphe ), 'cookies' => array(), 'filename' => null,
		'response' => array( 'code' => 200, 'message' => 'OK' ) );
};
add_filter( 'pre_http_request', $simuler_retire, 10, 3 );
$vif = Notice_Archeomed_Thesaurus::resoudre( $ark_vif, '4343', 'TH_1' );
delete_transient( na_appel( 'Notice_Archeomed_Thesaurus', 'clef', array( $ark_vif, '4343', 'TH_1' ) ) );
$retire_vaut = 'true';
$mort = Notice_Archeomed_Thesaurus::resoudre( $ark_vif, '4343', 'TH_1' );
delete_transient( na_appel( 'Notice_Archeomed_Thesaurus', 'clef', array( $ark_vif, '4343', 'TH_1' ) ) );
remove_filter( 'pre_http_request', $simuler_retire, 10 );
na_verifier( is_array( $vif ) && false === $vif['deprecie'] && is_array( $mort ) && true === $mort['deprecie'],
	'« false » laisse le concept vivant, « true » le dit retiré', array( $vif, $mort ) );

WP_CLI::log( 'Les identifiants Pactols reçus du navigateur' );
$_POST['pactols_subjects'] = wp_slash( wp_json_encode( array(
	array( 'label' => 'forgé', 'ark' => 'https://exemple.org/ark:/26678/pcrtX', 'idConcept' => '12' ),
	array( 'label' => 'chemin', 'ark' => 'https://ark.frantiq.fr/ark:/26678/pcrtY', 'idConcept' => '../../thesaurus' ),
	array( 'label' => 'en http', 'ark' => 'http://ark.frantiq.fr/ark:/26678/pcrtZ', 'idConcept' => '77' ),
) ) );
$_POST['commune']     = array( 'Caen', 'Vire' );
$_POST['commune_ark'] = array( 'javascript:alert(1)', 'https://ark.frantiq.fr/ark:/26678/pcrtkeqj9I3nbw' );
$recus_termes = na_appel( $plugin, 'collect_pactols_keywords', array( 'pactols_subjects' ) );
$recus_lieux  = na_appel( $plugin, 'collect_lieux' );
unset( $_POST['pactols_subjects'], $_POST['commune'], $_POST['commune_ark'] );
na_verifier( array( '', 'https://ark.frantiq.fr/ark:/26678/pcrtY', 'https://ark.frantiq.fr/ark:/26678/pcrtZ' ) === array_column( $recus_termes, 'ark' )
	&& array( '', '', '77' ) === array_column( $recus_termes, 'idConcept' )
	&& array( '', 'https://ark.frantiq.fr/ark:/26678/pcrtkeqj9I3nbw' ) === array_column( $recus_lieux, 'ark' ),
	'un ARK étranger ou forgé ne se garde pas, un ARK en http passe en https, un identifiant non numérique tombe', array( $recus_termes, $recus_lieux ) );

WP_CLI::log( 'Les termes gardés hors de Pactols' );
$_POST['pactols_subjects'] = wp_slash( wp_json_encode( array(
	array( 'label' => 'motte castrale', 'ark' => 'https://ark.frantiq.fr/ark:/26678/pcrtY', 'idConcept' => '77' ),
	array( 'label' => 'chemin creux', 'ark' => '', 'idConcept' => '' ),
) ) );
$recus_libres = na_appel( $plugin, 'collect_pactols_keywords', array( 'pactols_subjects' ) );
unset( $_POST['pactols_subjects'] );
na_verifier( empty( $recus_libres[0]['libre'] ) && true === $recus_libres[1]['libre'],
	'un terme gardé hors de la liste de Pactols est marqué à la réception', $recus_libres );
$doc_libres = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
na_appel( $plugin, 'remplir_le_document', array( $doc_libres, array_merge( $saisie_styles, array(
	'pactols_subjects_items' => array_merge( $recus_libres, array( array( 'label' => 'ancien terme', 'ark' => '' ) ) ) ) ) ) );
$xml_libres = str_replace( "\u{00A0}", ' ', html_entity_decode( strip_tags( str_replace( '</w:p>', "\n", implode( '', $corps->getValue( $doc_libres ) ) ) ), ENT_QUOTES | ENT_XML1, 'UTF-8' ) );
$ligne_mots = preg_grep( '/^Mots-clés/u', explode( "\n", $xml_libres ) );
na_verifier( 1 === count( $ligne_mots ) && false !== strpos( implode( '', $ligne_mots ), 'motte castrale' )
	&& false !== strpos( implode( '', $ligne_mots ), 'ancien terme' ) && false === strpos( implode( '', $ligne_mots ), 'chemin creux' )
	&& false !== strpos( $xml_libres, 'Terme pris hors de la liste de Pactols, non retenu (mots-clés) : chemin creux.' ),
	'le terme hors Pactols sort de la ligne des mots-clés et se lit « à supprimer » ; un terme sans ARK d\'une notice ancienne reste', $ligne_mots );
$courriel_libres = na_appel( $plugin, 'pactols_keywords_block', array( 'Mots-clés', $recus_libres ) );
na_verifier( false !== strpos( $courriel_libres, 'chemin creux — pris hors de la liste de Pactols, non retenu' ),
	'le courriel dit le terme hors Pactols non retenu', $courriel_libres );

WP_CLI::log( 'La lecture des termes par le serveur, coupée' );
$non_lue = na_notice( array( 'commune' => 'Caen', 'lieu_dit' => 'Lecture', 'pactols_subjects_items' => array(
	array( 'label' => 'église', 'ark' => 'https://ark.frantiq.fr/ark:/26678/pcrtNONLU', 'idConcept' => '' ) ) ) );
$reglages_lecture = get_option( Notice_Archeomed_Settings::OPTION_NAME, array() );
$saisie_lue = na_appel( $plugin, 'saisie_de', array( $non_lue, false ) );
update_option( Notice_Archeomed_Settings::OPTION_NAME, array_merge( (array) $reglages_lecture, array( 'pactols_serveur' => 'non' ) ) );
delete_post_meta( $non_lue, '_na_pactols_apres' );
$saisie_coupee  = na_appel( $plugin, 'saisie_de', array( $non_lue, false ) );
$manquants_coupe = 9;
na_appel( $plugin, 'indexation_de', array( $non_lue, $saisie_coupee, &$manquants_coupe ) );
$plugin->resoudre_en_tache();
$attente_coupee = get_post_meta( $non_lue, '_na_pactols_apres', true );
$tache_coupee   = wp_next_scheduled( Notice_Archeomed_Pactols::HOOK_TERMES );
update_option( Notice_Archeomed_Settings::OPTION_NAME, $reglages_lecture );
na_verifier( ! empty( $saisie_lue['pactols_manquants'] ) && empty( $saisie_coupee['pactols_manquants'] ) && 0 === $manquants_coupe
	&& '' === $attente_coupee && false === $tache_coupee && Notice_Archeomed_Pactols::lit_pactols(),
	'lecture coupée : ni terme « non lu » dans le Word, ni attente, ni tâche ; rétablie, elle reprend', array( $saisie_coupee, $attente_coupee, $tache_coupee ) );
wp_delete_post( $non_lue, true );

WP_CLI::log( 'Le bloc d\'index d\'une commune homonyme' );
$homonyme = na_notice( array_merge( $saisie_styles, array( 'lieu_dit' => 'Château', 'departement' => 'Calvados',
	'lieux' => array( array( 'nom' => 'Falaise (Calvados)', 'ark' => '' ) ), 'commune' => 'Falaise (Calvados)' ) ),
	array( $a => $concept( $a, '2026-10-01' ), $b => $concept( $b, '2026-10-01' ) ) );
update_post_meta( $homonyme, '_na_reference', 'HOMONY' );
$bloc_homonyme = na_appel( $plugin, 'indexation_de', array( $homonyme, na_appel( $plugin, 'saisie_de', array( $homonyme, false ) ) ) );
na_appel( $plugin, 'sortir_de_l_attente', array( $homonyme ) );
wp_delete_post( $homonyme, true );
na_verifier( false !== strpos( $bloc_homonyme, 'notice="Falaise Château"' ) && false !== strpos( $bloc_homonyme, 'ref="HOMONY"' ),
	'le bloc d\'index nomme la commune sans redire sa parenthèse, et porte la référence', substr( $bloc_homonyme, 0, 600 ) );

WP_CLI::log( 'Le formulaire dit qu\'un lien de correction a servi, ou expiré' );
$reprise_prop->setValue( $plugin, null );
$_GET['notice_reprise'] = 'jetonrendu01';
set_transient( 'na_reprise_faite_jetonrendu01', 'REFAIT', 60 );
$rendu_servi = $plugin->render_form();
$reprise_prop->setValue( $plugin, null );
$_GET['notice_reprise'] = 'jetonrendu02';
$rendu_expire = $plugin->render_form();
$reprise_prop->setValue( $plugin, null );
$_GET['notice_reprise'] = 'jetonrendu01';
$_GET['notice_envoyee'] = '1';
$rendu_fin = $plugin->render_form();
unset( $_GET['notice_reprise'], $_GET['notice_envoyee'] );
$reprise_prop->setValue( $plugin, null );
delete_transient( 'na_reprise_faite_jetonrendu01' );
na_verifier( false !== strpos( $rendu_servi, 'a déjà servi' ) && false !== strpos( $rendu_servi, 'REFAIT' )
	&& false !== strpos( $rendu_expire, 'a expiré' ) && false === strpos( $rendu_fin, 'a déjà servi' ),
	'lien servi : la référence du dépôt ; lien inconnu : expiré ; rien sur la page de fin',
	array( false !== strpos( $rendu_servi, 'a déjà servi' ), false !== strpos( $rendu_expire, 'a expiré' ), false !== strpos( $rendu_fin, 'a déjà servi' ) ) );

WP_CLI::log( 'La limite annoncée des illustrations' );
// L'annonce ne promet jamais plus que ce que le serveur accepte : trois
// fichiers de 2 Mo font 6 Mo, non les 40 du plafond général.
$deux_mo = function () {
	return 2 * MB_IN_BYTES;
};
add_filter( 'upload_size_limit', $deux_mo, 99 );
$rendu_limite = $plugin->render_form();
remove_filter( 'upload_size_limit', $deux_mo, 99 );
na_verifier( false !== strpos( $rendu_limite, '6&nbsp;Mo en tout, 2&nbsp;Mo par fichier' ) && false === strpos( $rendu_limite, '40&nbsp;Mo en tout' ),
	'trois fichiers de 2 Mo s\'annoncent « 6 Mo en tout, 2 Mo par fichier »',
	preg_match( '#[\d,]+&nbsp;Mo en tout[^<)]*#', $rendu_limite, $m_limite ) ? $m_limite[0] : '' );

WP_CLI::log( 'Une réponse Pactols de forme inattendue' );
// Une valeur nue au lieu d'une liste d'objets « value » faisait lever
// « reset() » en PHP 8 : l'erreur sortait de la tâche, et la file se
// bloquait sur ce terme.
$premiere = function ( $noeud, $propriete ) {
	$r = new ReflectionMethod( 'Notice_Archeomed_Thesaurus', 'premiere' );
	$r->setAccessible( true );
	return $r->invoke( null, $noeud, $propriete );
};
$prop_id = Notice_Archeomed_Thesaurus::DCT . 'identifier';
na_verifier( '12' === $premiere( array( $prop_id => '12' ), $prop_id )
	&& '12' === $premiere( array( $prop_id => array( array( 'value' => '12' ) ) ), $prop_id )
	&& '12' === $premiere( array( $prop_id => array( 'value' => '12' ) ), $prop_id )
	&& '' === $premiere( 'pas un noeud', $prop_id ) && '' === $premiere( array( $prop_id => array( array( 'value' => array( 1 ) ) ) ), $prop_id ),
	'la première valeur se lit en liste, en objet seul ou nue ; le reste ne vaut rien, sans erreur' );
$ark_mal = 'https://ark.frantiq.fr/ark:/26678/pcrtMalforme';
$ark_autre = 'https://ark.frantiq.fr/ark:/26678/pcrtAutreMal';
$malforme = function ( $pre, $args, $url ) use ( $ark_mal ) {
	if ( false === strpos( $url, 'pactols.frantiq.fr' ) ) {
		return $pre;
	}
	return array( 'headers' => array(), 'cookies' => array(), 'filename' => null,
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'body' => wp_json_encode( array( $ark_mal => array( 'http://purl.org/dc/terms/identifier' => '12',
			'http://www.w3.org/2004/02/skos/core#prefLabel' => array( 'value' => 'malformé', 'lang' => 'fr' ) ) ) ) );
};
add_filter( 'pre_http_request', $malforme, 10, 3 );
$mal_ids = array();
foreach ( array( $ark_mal, $ark_autre ) as $ark_essai ) {
	delete_transient( 'na_pactols_c_' . md5( $ark_essai . '||TH_1' ) );
	delete_transient( 'na_pactols_id_' . md5( $ark_essai ) );
	$mal = na_notice( array( 'commune' => 'Caen', 'lieu_dit' => 'Essai', 'pactols_subjects_items' => array(
		array( 'label' => 'x', 'ark' => $ark_essai, 'idConcept' => '' ) ) ) );
	update_post_meta( $mal, '_na_pactols_apres', time() - 10 );
	$mal_ids[] = $mal;
}
$sortie_mal = '';
try {
	$plugin->resoudre_en_tache();
} catch ( Throwable $e ) {
	$sortie_mal = get_class( $e ) . ' : ' . $e->getMessage();
}
remove_filter( 'pre_http_request', $malforme, 10 );
$essais_mal = array();
foreach ( $mal_ids as $mal ) {
	$lus_mal      = get_post_meta( $mal, '_na_pactols', true );
	$essais_mal[] = array( (int) get_post_meta( $mal, '_na_pactols_essais', true ), is_array( $lus_mal ) ? count( $lus_mal ) : 0 );
	wp_delete_post( $mal, true );
}
foreach ( array( $ark_mal, $ark_autre ) as $ark_essai ) {
	delete_transient( 'na_pactols_c_' . md5( $ark_essai . '||TH_1' ) );
	delete_transient( 'na_pactols_id_' . md5( $ark_essai ) );
}
wp_clear_scheduled_hook( Notice_Archeomed_Pactols::HOOK_TERMES );
na_verifier( '' === $sortie_mal && array( array( 0, 1 ), array( 1, 0 ) ) === $essais_mal,
	'la réponse atypique se lit sans erreur ; la notice suivante est examinée, son échec compté', array( $sortie_mal, $essais_mal ) );

WP_CLI::log( 'Une correction qui perd une autorisation de reproduction' );
$autorisee = na_notice( array_merge( $saisie, array( 'illustrations' => array(
	array( 'rang' => 1, 'titre' => 'Plan', 'legende' => '', 'credits' => '' ),
	array( 'rang' => 2, 'titre' => 'Vue', 'legende' => '', 'credits' => '', 'autorisation' => true ) ) ) ) );
update_post_meta( $autorisee, '_na_reference', 'AUTOR1' );
update_post_meta( $autorisee, '_na_illustrations', array( '/tmp/a.jpg', '/tmp/b.jpg' ) );
$sans_autorisation = na_appel( $plugin, 'figures_de_la_remplacee', array( array( 'remplace' => 'AUTOR1', 'illustrations' => array(
	array( 'rang' => 1, 'titre' => 'Plan' ), array( 'rang' => 2, 'titre' => 'Vue', 'autorisation' => false ) ) ) ) );
$avec_autorisation = na_appel( $plugin, 'figures_de_la_remplacee', array( array( 'remplace' => 'AUTOR1', 'illustrations' => array(
	array( 'rang' => 1, 'titre' => 'Plan' ), array( 'rang' => 2, 'titre' => 'Vue', 'autorisation' => true ) ) ) ) );
$sans_figure = na_appel( $plugin, 'figures_de_la_remplacee', array( array( 'remplace' => 'AUTOR1', 'illustrations' => array() ) ) );
wp_delete_post( $autorisee, true );
na_verifier( false !== strpos( $sans_autorisation, 'une autorisation de reproduction (Fig. 2)' )
	&& false !== strpos( $sans_autorisation, 'n’en porte aucune' ) && false !== strpos( $sans_autorisation, 'restent sur le site' )
	&& '' === $avec_autorisation,
	'la rédaction lit qu\'une autorisation n\'a pas suivi la correction, et rien quand elle a suivi', array( $sans_autorisation, $avec_autorisation ) );
na_verifier( false !== strpos( $sans_figure, 'cette correction n’en porte aucune' ) && false === strpos( $sans_figure, 'correction en porte aucune' )
	&& false !== strpos( $sans_figure, 'Elle portait aussi une autorisation' ),
	'« cette correction n’en porte aucune », avec sa négation', $sans_figure );

WP_CLI::log( 'Les figures qui attendent, de correction en correction' );
// Une correction faite sans redéposer les figures gardait une réserve sans
// leurs textes : la correction suivante les avait perdus.
$_POST = array( 'reprise_jeton' => 'jetonfigures1', 'lieu_dit' => 'Château' );
$_FILES = array();
set_transient( 'na_reprise_jetonfigures1', array( 'remplace' => 'PREMIE',
	'illus_titre' => array( 'Plan', 'Vue' ), 'illus_legende' => array( 'Le plan', '' ), 'illus_credits' => array( '© A', '' ),
	'illus_alt' => array( 'Plan des murs', 'Vue du fossé' ), 'illus_nom' => array( 'plan.jpg', 'vue.jpg' ),
	'illus_autorisation' => array( '', '1' ) ), 60 );
$jeton_suite = na_appel( $plugin, 'garder_la_saisie', array( 60, 'SECOND' ) );
$reserve_suite = get_transient( 'na_reprise_' . $jeton_suite );
// Redéposée, la vue reprend ses textes du formulaire ; le plan attend.
$_POST = array( 'reprise_jeton' => 'jetonfigures1', 'illus_titre' => array( 'Vue revue' ), 'illus_legende' => array( '' ),
	'illus_credits' => array( '' ), 'illus_alt' => array( 'Vue du fossé, au nord' ) );
$_FILES = array( 'illustrations' => array( 'name' => array( 'vue.jpg' ), 'error' => array( 0 ) ) );
$jeton_partiel = na_appel( $plugin, 'garder_la_saisie', array( 60, 'TROIS' ) );
$reserve_partielle = get_transient( 'na_reprise_' . $jeton_partiel );
$_POST = array();
$_FILES = array();
foreach ( array( 'jetonfigures1', $jeton_suite, $jeton_partiel ) as $j ) {
	delete_transient( 'na_reprise_' . $j );
}
na_verifier( is_array( $reserve_suite ) && array( 'Plan', 'Vue' ) === $reserve_suite['illus_titre']
	&& array( 'Plan des murs', 'Vue du fossé' ) === $reserve_suite['illus_alt'] && array( '', '1' ) === $reserve_suite['illus_autorisation']
	&& array( 'plan.jpg', 'vue.jpg' ) === $reserve_suite['illus_nom'] && 'SECOND' === $reserve_suite['remplace'],
	'une correction sans figures garde titres, légendes, crédits, textes alternatifs et autorisations pour la suivante', $reserve_suite );
na_verifier( is_array( $reserve_partielle ) && array( 'Vue revue', 'Plan' ) === $reserve_partielle['illus_titre']
	&& array( 'vue.jpg', 'plan.jpg' ) === $reserve_partielle['illus_nom'] && array( 'Vue du fossé, au nord', 'Plan des murs' ) === $reserve_partielle['illus_alt'],
	'une figure redéposée garde ce que dit le formulaire ; celle qui ne l\'est pas attend avec ses textes', $reserve_partielle );

WP_CLI::log( 'Le siècle mis en petites capitales par le bouton' );
$doc_pc = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
$pc_vus = array();
foreach ( array(
	'Au <span class="na-pc">xv</span>e siècle.'                                            => 'Au [xv]^e_s.',
	'fin du <span class="na-pc">xii</span>e-début du <span class="na-pc">xiii</span>e siècle' => 'fin du [xii]^e-début du [xiii]^e_s.',
	'<span class="na-pc">XIIe</span> siècle'                                                => '[xii]^e_s.',
	'<span class="na-pc">Louis</span> et le roi'                                            => '[Louis] et le roi',
) as $saisi => $attendu ) {
	$obtenu = $sans_balises( implode( '', $doc_pc->html_to_paragraphs( '<p>' . $saisi . '</p>' ) ) );
	if ( $obtenu !== $attendu ) {
		$pc_vus[ $saisi ] = $obtenu;
	}
}
na_verifier( empty( $pc_vus ), 'au Word, l\'ordinal tapé après « Pc » passe en exposant, jamais en petites capitales', $pc_vus );
$relu_pc = na_appel( $plugin, 'siecles_du_html', array( '<p>Au <span class="na-pc">xv</span>e siècle, au <span class="na-pc">XIIe</span> s.</p>' ) );
na_verifier( "<p>Au <span style=\"font-variant: small-caps; font-variant-caps: all-small-caps\">XV</span><sup>e</sup>\u{00A0}s., au <span style=\"font-variant: small-caps; font-variant-caps: all-small-caps\">XII</span><sup>e</sup>\u{00A0}s.</p>" === $relu_pc,
	'au courriel et à la relecture aussi', $relu_pc );
$relu_vi = na_appel( $plugin, 'siecles_du_html', array( '<p>Au vie s. et au XIe siècle.</p>' ) );
na_verifier( false !== strpos( $relu_vi, 'all-small-caps">VI</span><sup>e</sup>' ) && false !== strpos( $relu_vi, 'all-small-caps">XI</span><sup>e</sup>' )
	&& false === strpos( $relu_vi, '>vi<' ) && false === strpos( $relu_vi, '>xi<' ),
	'dans le HTML, le chiffre du siècle s\'écrit en capitales que le style réduit : un lecteur d\'écran lit « VI », non le mot « vie »', $relu_vi );

WP_CLI::log( 'Les entrées forgées et les caractères de contrôle' );
$_POST = array( 'resp_email' => array( 'a@example.org' ), 'lieu_dit' => "Le\x0Bchâteau\x01 neuf", 'commentaires' => "Ligne 1\x0BLigne 2\x02",
	'commune' => array( 'Caen', array( 'x' ) ), 'illus_titre' => array( array( 'x' ), 'Plan' ), 'texte_notice' => array( 'x' ),
	'organisme' => "Inrap\x07" );
na_appel( $plugin, 'entrees_attendues' );
$entrees = $_POST;
$_POST = array();
na_verifier( ! isset( $entrees['resp_email'] ) && ! isset( $entrees['texte_notice'] ) && 'Le château neuf' === $entrees['lieu_dit']
	&& "Ligne 1\nLigne 2" === $entrees['commentaires'] && array( 'Caen', '' ) === $entrees['commune']
	&& array( '', 'Plan' ) === $entrees['illus_titre'] && 'Inrap' === $entrees['organisme'],
	'un champ forgé en tableau est ignoré ; les contrôles s\'ôtent, le saut de ligne de Word devient une espace', $entrees );
$forger = function ( $vers ) {
	throw new RuntimeException( (string) $vers );
};
add_filter( 'wp_redirect', $forger );
$cible_vieille = 50;
$ts_vieux     = time() - 22 * HOUR_IN_SECONDS;
$ts_perime    = time() - 30 * HOUR_IN_SECONDS;
$_POST = array( 'na_ts' => (string) $ts_vieux, 'na_curseur' => (string) $cible_vieille, 'na_preuve' => na_appel( $plugin, 'sceau_du_defi', array( $cible_vieille, $ts_vieux ) ) );
$puzzle_vieux = na_appel( $plugin, 'verifier_le_puzzle' );
$_POST = array( 'na_ts' => (string) $ts_perime, 'na_curseur' => (string) $cible_vieille, 'na_preuve' => na_appel( $plugin, 'sceau_du_defi', array( $cible_vieille, $ts_perime ) ) );
$puzzle_perime = na_appel( $plugin, 'verifier_le_puzzle' );
$_POST = array();
na_verifier( true === $puzzle_vieux && false === $puzzle_perime,
	'un défi du puzzle reste valable plus de vingt heures (limite de temps du RGAA), et se périme ensuite' );
// Le sceau porte l'heure d'affichage : la changer le défait. Et il ne sert
// qu'une fois — un dépôt réussi le retient.
$ts_sceau = time() - 60;
$sceau    = na_appel( $plugin, 'defi_du_puzzle', array( $ts_sceau ) );
$_POST = array( 'na_ts' => (string) ( $ts_sceau - 3600 ), 'na_curseur' => (string) $sceau['cible'], 'na_preuve' => $sceau['preuve'] );
$sceau_deplace = na_appel( $plugin, 'verifier_le_puzzle' );
$_POST = array( 'na_ts' => (string) $ts_sceau, 'na_curseur' => (string) $sceau['cible'], 'na_preuve' => $sceau['preuve'] );
$sceau_premier = na_appel( $plugin, 'verifier_le_puzzle' );
na_appel( $plugin, 'consommer_le_sceau' );
$sceau_second = na_appel( $plugin, 'verifier_le_puzzle' );
$_POST = array();
delete_transient( 'na_sceau_' . substr( $sceau['preuve'], 0, 40 ) );
na_verifier( false === $sceau_deplace && true === $sceau_premier && false === $sceau_second,
	'le sceau du puzzle tient à l\'heure d\'affichage de la page, et ne sert qu\'une fois',
	array( $sceau_deplace, $sceau_premier, $sceau_second ) );
// Vingt secondes, et non trente : les dépôts joués plus haut ont pris
// trente secondes et plus. Dans la même seconde, l'un d'eux pouvait avoir
// déjà servi le même sceau, une fois sur cinquante-neuf encoches.
$ts_forge   = time() - 20;
$defi_forge = na_appel( $plugin, 'defi_du_puzzle', array( $ts_forge ) );
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'notice_archeomed_envoi' => '1', 'notice_archeomed_nonce' => wp_create_nonce( 'notice_archeomed_submit' ),
	'na_ts' => (string) $ts_forge, 'na_curseur' => (string) $defi_forge['cible'], 'na_preuve' => $defi_forge['preuve'],
	'rubrique_principale' => 'I. Constructions et habitats civils', 'commune' => array( 'Caen' ), 'departement' => 'Calvados',
	'lieu_dit' => 'Essai forgé', 'annee' => '2025', 'nature' => array( 'Fouille préventive' ), 'organisme' => array( 'Inrap' ),
	'resp_prenom' => 'Aude', 'resp_nom' => array( 'Ferrand' ), 'resp_email' => array( 'a@example.org' ), 'resp_inst' => 'Inrap',
	'rapport_lien' => array( 'x' ), 'texte_notice' => '<p>Le mur.</p>',
);
$vers_forge = '';
$erreur_forge = '';
try {
	$plugin->handle_submission();
} catch ( RuntimeException $e ) {
	$vers_forge = $e->getMessage();
} catch ( Throwable $e ) {
	$erreur_forge = get_class( $e ) . ' : ' . $e->getMessage();
}
$_POST = array(
	'notice_archeomed_envoi' => '1', 'notice_archeomed_nonce' => wp_create_nonce( 'notice_archeomed_submit' ),
	'na_ts' => array( '1' ),
);
$vers_ts = '';
try {
	$plugin->handle_submission();
} catch ( RuntimeException $e ) {
	$vers_ts = $e->getMessage();
}
// L'heure d'affichage est exigée : sans elle, le délai minimal se contournait.
$sans_heure = array();
foreach ( array( 'absente' => null, 'dans le futur' => (string) ( time() + 7200 ), 'très ancienne' => '1000', 'trop rapide' => (string) time() ) as $cas => $heure ) {
	$_POST = array( 'notice_archeomed_envoi' => '1', 'notice_archeomed_nonce' => wp_create_nonce( 'notice_archeomed_submit' ), 'rubrique_principale' => 'I' );
	if ( null !== $heure ) { $_POST['na_ts'] = $heure; }
	$sans_heure[ $cas ] = '';
	try {
		$plugin->handle_submission();
	} catch ( RuntimeException $e ) {
		$sans_heure[ $cas ] = $e->getMessage();
	}
}
$sans_heure_ok = array_filter( $sans_heure, function ( $vers ) { return false !== strpos( $vers, 'notice_erreur=securite' ); } );
$_POST = array();
remove_filter( 'wp_redirect', $forger );
parse_str( (string) wp_parse_url( $vers_forge, PHP_URL_QUERY ), $retour_forge );
foreach ( array( 'na_reprise_' ) as $prefixe ) {
	if ( preg_match( '/notice_reprise=([a-z0-9]+)/', $vers_forge, $j_forge ) ) {
		delete_transient( $prefixe . $j_forge[1] );
	}
}
na_verifier( '' === $erreur_forge && isset( $retour_forge['notice_envoyee'] ) && '0' === $retour_forge['notice_envoyee']
	&& isset( $retour_forge['notice_champs'] ) && false !== strpos( $retour_forge['notice_champs'], 'resp_nom' )
	&& false !== strpos( $retour_forge['notice_champs'], 'resp_email' ),
	'un dépôt aux champs forgés en tableau est refusé proprement, les champs nommés, sans erreur', array( $erreur_forge, $retour_forge ) );
na_verifier( false !== strpos( $vers_ts, 'notice_erreur=securite' ), 'un délai d\'envoi forgé en tableau est refusé', $vers_ts );
na_verifier( 4 === count( $sans_heure_ok ), 'une heure d\'affichage absente, dans le futur, très ancienne ou trop récente est refusée', $sans_heure );

WP_CLI::log( 'Les illustrations d\'une saisie ancienne, en texte libre' );
$libre = na_notice( array_merge( $saisie_styles, array( 'illustrations' => 'Fig. 1 : plan ; fig. 2 : coupe' ) ) );
$saisie_libre = null;
$erreur_libre = '';
try {
	$saisie_libre = na_appel( $plugin, 'saisie_de', array( $libre, true ) );
	$doc_libre = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
	na_appel( $plugin, 'remplir_le_document', array( $doc_libre, $saisie_libre ) );
	$xml_libre = implode( '', $corps->getValue( $doc_libre ) );
} catch ( Throwable $e ) {
	$erreur_libre = $e->getMessage();
	$xml_libre = '';
}
na_appel( $plugin, 'sortir_de_l_attente', array( $libre ) );
wp_delete_post( $libre, true );
na_verifier( '' === $erreur_libre && array() === $saisie_libre['illustrations']
	&& false !== strpos( $xml_libre, 'texte libre' ) && false !== strpos( $xml_libre, 'coupe' ),
	'le fascicule ne tombe plus : le texte libre passe à la rédaction, sans figure inventée',
	array( $erreur_libre, $saisie_libre['illustrations'], preg_match( '#texte libre.{0,300}#u', $xml_libre, $m_libre ) ? $m_libre[0] : '' ) );

WP_CLI::log( 'Le texte alternatif des figures' );
$_POST = array( 'illus_titre' => array( 'Plan', 'Vue' ), 'illus_legende' => array( '', '' ), 'illus_credits' => array( '', '' ),
	'illus_alt' => array( ' Plan des murs du château ', '' ) );
$_FILES = array( 'illustrations' => array( 'name' => array( 'plan.jpg', 'vue.jpg' ), 'error' => array( 0, 0 ) ) );
$figures_alt = na_appel( $plugin, 'collect_illustrations' );
$manquent_alt = na_appel( $plugin, 'champs_manquants', array( array_merge( $saisie_styles, array( 'illustrations' => $figures_alt ) ) ) );
$manquent_figures = array_values( preg_grep( '/^illus_/', $manquent_alt ) );
$_FILES = array();
$manquent_sans_fichier = na_appel( $plugin, 'champs_manquants', array( array_merge( $saisie_styles, array( 'illustrations' => $figures_alt ) ) ) );
$_POST = array();
$table_alt = na_appel( $plugin, 'champs_du_formulaire' );
na_verifier( 'Plan des murs du château' === $figures_alt[0]['alt'] && '' === $figures_alt[1]['alt'],
	'le texte alternatif de chaque figure se collecte avec elle', $figures_alt );
$manquent_alt          = array_values( preg_grep( '/^illus_alt_/', $manquent_alt ) );
$manquent_sans_fichier = array_values( preg_grep( '/^illus_alt_/', $manquent_sans_fichier ) );
na_verifier( array( 'illus_alt_2' ) === $manquent_alt && array() === $manquent_sans_fichier
	&& isset( $table_alt['illus_alt_2'] ) && false !== strpos( $table_alt['illus_alt_2'][1], 'figure 2' )
	&& 'na-illus-2-alt' === $table_alt['illus_alt_2'][0],
	'il est obligatoire pour une figure déposée, et le message dit laquelle ; sans fichier, rien n\'est demandé', array( $manquent_alt, $manquent_sans_fichier ) );
na_verifier( isset( $table_alt['illus_titre_3'], $table_alt['illus_credits_3'] ) && 'na-illus-2-credits' === $table_alt['illus_credits_2'][0]
	&& in_array( 'illus_credits_1', $manquent_figures, true ) && in_array( 'illus_alt_2', $manquent_figures, true )
	&& array() === preg_grep( '/^illus_type_/', $manquent_figures ),
	'le titre, les crédits et le texte alternatif d\'une figure déposée sont obligatoires ; un formulaire d\'avant la nature de la figure ne se voit pas refuser pour elle', $manquent_figures );
$_POST = array( 'illus_titre' => array( 'Plan', 'Vue' ), 'illus_legende' => array( '', '' ), 'illus_credits' => array( 'A', 'A' ),
	'illus_alt' => array( 'Plan des murs', 'Vue du fossé' ), 'illus_type' => array( '', 'carte' ) );
$_FILES = array( 'illustrations' => array( 'name' => array( 'plan.jpg', 'vue.jpg' ), 'error' => array( 0, 0 ) ) );
$figures_type = na_appel( $plugin, 'collect_illustrations' );
$manquent_type = array_values( preg_grep( '/^illus_/', na_appel( $plugin, 'champs_manquants', array( array_merge( $saisie_styles, array( 'illustrations' => $figures_type ) ) ) ) ) );
$_POST = array();
$_FILES = array();
na_verifier( array( 'illus_type_1' ) === $manquent_type && 'na-illus-1-type' === $table_alt['illus_type_1'][0]
	&& false !== strpos( $table_alt['illus_type_1'][1], 'figure 1' ),
	'la nature de la figure est obligatoire quand le formulaire la propose, et le message dit laquelle manque', $manquent_type );
$sans_date  = na_appel( $plugin, 'champs_manquants', array( array_merge( $saisie_styles, array( 'originaux_lien' => 'https://filesender.renater.fr/x', 'originaux_jusqua' => '' ) ) ) );
$avec_date  = na_appel( $plugin, 'champs_manquants', array( array_merge( $saisie_styles, array( 'originaux_lien' => 'https://filesender.renater.fr/x', 'originaux_jusqua' => '2026-12-31' ) ) ) );
$dates_lues = array( na_appel( $plugin, 'date_valide', array( '2026-02-30' ) ), na_appel( $plugin, 'date_valide', array( '2026-12-31' ) ), na_appel( $plugin, 'date_valide', array( '31/12/2026' ) ) );
na_verifier( in_array( 'originaux_jusqua', $sans_date, true ) && ! in_array( 'originaux_jusqua', $avec_date, true ) && array( false, true, false ) === $dates_lues,
	'un lien vers les originaux demande sa date d\'expiration, une date qui existe au calendrier', array( $sans_date, $dates_lues ) );
$alertes = array();
$capter_alerte = function ( $retour, $atts ) use ( &$alertes ) {
	$alertes[] = $atts;
	return true;
};
add_filter( 'pre_wp_mail', $capter_alerte, 10, 2 );
$dans_douze = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '+12 days' )->format( 'Y-m-d' );
na_appel( $plugin, 'alerter_pour_les_originaux', array( array( 'originaux_lien' => 'https://filesender.renater.fr/x', 'originaux_jusqua' => $dans_douze, 'resp_email' => 'aude.ferrand@example.org' ),
	array( 'redaction@example.org' ), array( 'Content-Type: text/html; charset=UTF-8' ), 'Caen (Calvados), Le Château', 'ABC123' ) );
na_appel( $plugin, 'alerter_pour_les_originaux', array( array( 'originaux_lien' => '' ), array( 'redaction@example.org' ), array(), 'Rien', '' ) );
remove_filter( 'pre_wp_mail', $capter_alerte, 10 );
$mois_douze = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( wp_date( 'F', ( new DateTimeImmutable( $dans_douze . ' 12:00', wp_timezone() ) )->getTimestamp() ), 'UTF-8' ) : '';
na_verifier( 1 === count( $alertes ) && 0 === strpos( $alertes[0]['subject'], 'ATTENTION — LIEN À TÉLÉCHARGER DANS 12 JOURS, JUSQU’AU ' )
	&& false !== strpos( $alertes[0]['subject'], $mois_douze ) && false !== strpos( $alertes[0]['subject'], 'Caen (Calvados), Le Château — réf. ABC123' )
	&& false !== strpos( $alertes[0]['message'], 'https://filesender.renater.fr/x' ) && array( 'redaction@example.org' ) === (array) $alertes[0]['to'],
	'un lien vers les originaux vaut à la rédaction un courriel à part, dont l\'objet dit le délai et la date ; sans lien, rien', $alertes );
na_verifier( 'Plan des murs' === Notice_Archeomed_Controles::texte_alternatif( array( 'rang' => 1, 'titre' => 'Plan', 'alt' => 'Plan des murs' ) )
	&& 'Plan général' === Notice_Archeomed_Controles::texte_alternatif( array( 'rang' => 1, 'titre' => 'Fig. 1 : Plan général' ) ),
	'une notice d\'avant le champ retombe sur le titre de la figure' );
$avis_alt = Notice_Archeomed_Controles::figures( array(
	array( 'rang' => 1, 'titre' => 'Plan', 'legende' => '', 'credits' => 'A', 'alt' => str_repeat( 'mur ', 40 ) ),
	array( 'rang' => 2, 'titre' => 'Fig. 2 : Vue du fossé', 'legende' => '', 'credits' => 'A', 'alt' => 'Vue du fossé' ),
	array( 'rang' => 3, 'titre' => 'Coupe', 'legende' => 'Les couches en rouge.', 'credits' => 'A', 'alt' => 'Photo de la coupe' ),
	array( 'rang' => 4, 'titre' => 'Mur', 'legende' => '', 'credits' => 'A', 'alt' => str_repeat( 'mur ', 30 ) ) ) );
na_verifier( 1 === count( preg_grep( '/Fig\. 1\x{00A0}: la description de l’image fait 159\x{00A0}caractères, pour 150/u', $avis_alt ) )
	&& 0 === count( preg_grep( '/Fig\. 4.*fait/u', $avis_alt ) ),
	'au-delà de 150 caractères, un avis, sans refus ; en deçà, rien', $avis_alt );
na_verifier( 1 === count( preg_grep( '/Fig\. 2.*reprend sa légende/u', $avis_alt ) )
	&& 1 === count( preg_grep( '/Fig\. 3.*commence par «\x{00A0}Photo de/u', $avis_alt ) )
	&& 1 === count( preg_grep( '/Fig\. 3.*«\x{00A0}en rouge\x{00A0}».*seule couleur/u', $avis_alt ) ),
	'un avis quand le texte alternatif reprend le titre, commence par « Photo de », ou que la couleur porte seule l\'information', $avis_alt );
na_verifier( 0 === preg_match( $C::ALT_DEBUTS, 'Image depuis le nord du bâtiment 1' ) && 0 === preg_match( $C::ALT_DEBUTS, 'Photo désaturée du mur' )
	&& 1 === preg_match( $C::ALT_DEBUTS, 'Image de la tour' ) && 1 === preg_match( $C::ALT_DEBUTS, 'Photo d’ensemble' ) && 1 === preg_match( $C::ALT_DEBUTS, 'Photo des fosses' ),
	'« Image de » se reconnaît mot à mot : « Image depuis » n\'est pas visé' );
$_POST = array( 'illus_titre' => array( 'Plan' ), 'illus_alt' => array( "Plan des   murs\t" . str_repeat( 'x', 400 ) ) );
$_FILES = array( 'illustrations' => array( 'name' => array( 'plan.jpg' ), 'error' => array( 0 ) ) );
$coupee = na_appel( $plugin, 'collect_illustrations' );
$_POST = array( 'illus_titre' => array( 'Plan' ), 'illus_alt' => array( "  Plan des deux bâtiments.\r\n\r\nLe fossé longe le mur ouest.\nLe puits est au centre.  " ) );
$_FILES = array( 'illustrations' => array( 'name' => array( 'plan.jpg' ), 'error' => array( 0 ) ) );
$ecoute = na_appel( $plugin, 'collect_illustrations' );
$_POST = array( 'illus_titre' => array( 'Plan' ), 'illus_alt' => array( 'Plan des deux bâtiments.' ), 'illus_description' => array( 'Envoyée à part.' ) );
$ancienne = na_appel( $plugin, 'collect_illustrations' );
$_POST = array();
$_FILES = array();
na_verifier( 300 === mb_strlen( $coupee[0]['alt'], 'UTF-8' ) && 0 === strpos( $coupee[0]['alt'], 'Plan des murs x' ),
	'le texte alternatif tient sur une ligne et se coupe à 300 caractères', $coupee[0]['alt'] );
na_verifier( 'Plan des deux bâtiments.' === $ecoute[0]['alt'] && "Le fossé longe le mur ouest.\nLe puits est au centre." === $ecoute[0]['description']
	&& 'Plan des deux bâtiments.' === $ancienne[0]['alt'] && 'Envoyée à part.' === $ancienne[0]['description'],
	'le champ « Pour un public empêché de voir » : le premier paragraphe est le texte alternatif, la suite la description ; un formulaire d\'avant, qui envoie la description à part, est encore lu',
	array( $ecoute, $ancienne ) );
$ST::oublier();
$figure_du_dossier = array( 'fichier' => 'icono/br/plan.jpg', 'largeur' => 800, 'hauteur' => 600, 'dpi' => 300 );
$saisie_alt = array_merge( $saisie_styles, array( 'illustrations' => array(
	array( 'rang' => 1, 'titre' => 'Plan', 'legende' => 'Le plan.', 'credits' => 'DAO A.', 'alt' => 'Plan des murs & du fossé', 'figure' => $figure_du_dossier,
		'description' => "Deux murs parallèles, orientés nord-sud.\n\nLe fossé longe le mur ouest." ),
	array( 'rang' => 2, 'titre' => 'Vue', 'legende' => '', 'credits' => '', 'figure' => $figure_du_dossier ) ) ) );
// Le premier bloc de figure, et l'ordre de ses paragraphes.
$bloc_de_figure = function ( $xml ) {
	$debut = strpos( $xml, 'TEIfigurestart' );
	$fin   = strpos( $xml, 'TEIfigureend', $debut );
	preg_match_all( '#<w:pStyle w:val="([^"]+)"/></w:pPr>(.*?)</w:p>#', substr( $xml, $debut - 30, $fin - $debut + 800 ), $m, PREG_SET_ORDER );
	$suite = array();
	foreach ( $m as $p ) {
		if ( ! empty( $suite ) && 0 === strpos( (string) end( $suite ), 'TEIfigureend' ) ) {
			break;
		}
		$suite[] = $p[1] . ' ' . str_replace( "\u{00A0}", ' ', mb_substr( html_entity_decode( preg_replace( '#<w:drawing>.*?</w:drawing>#s', '[image]', preg_replace( '#<(?!w:drawing|/w:drawing)[^>]+>#', '', $p[2] ) ), ENT_QUOTES | ENT_XML1, 'UTF-8' ), 0, 40 ) );
	}
	return $suite;
};
$doc_alt = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
na_appel( $plugin, 'remplir_le_document', array( $doc_alt, $saisie_alt ) );
$xml_alt = implode( '', $corps->getValue( $doc_alt ) );
$suite_alt = $bloc_de_figure( $xml_alt );
na_verifier( false !== strpos( $xml_alt, 'descr="Plan des murs &amp; du fossé"' ) && false !== strpos( $xml_alt, 'descr="Fig. 2 Vue"' )
	&& false === strpos( $xml_alt, 'TEIfigurealttext' ),
	'au Word, « descr » porte le texte alternatif, ou le titre d\'une figure ancienne ; pas de paragraphe par défaut', $suite_alt );
na_verifier( false !== strpos( $xml_alt, 'descr="Plan des murs &amp; du fossé" title="Plan des murs &amp; du fossé"' )
	&& false !== strpos( $xml_alt, 'descr="Fig. 2 Vue" title="Fig. 2 Vue"' ),
	'le texte alternatif va aussi dans « title », que LibreOffice affiche comme texte alternatif',
	preg_match_all( '#<wp:docPr[^>]*>#u', $xml_alt, $m_titre ) ? $m_titre[0] : array() );
$fin_alt = array_slice( $suite_alt, -5 );
na_verifier( 'TEIfigurecaption Le plan.' === $fin_alt[0] && 0 === strpos( $fin_alt[1], 'TEIfigurecaption Description : Deux murs' )
	&& 'TEIfigurecaption Le fossé longe le mur ouest.' === $fin_alt[2] && 0 === strpos( $fin_alt[3], 'TEIfigurecredits' )
	&& 0 === strpos( $fin_alt[4], 'TEIfigureend' ) && false === strpos( $xml_alt, 'Description détaillée' ),
	'la description détaillée se publie en fin de légende, ouverte par « Description : », avant les crédits', $suite_alt );
list( $alt_choisi, $alt_refuse ) = $ST::nettoyer( array( 'figure_alttext' => 'TEI_figure_alttext' ) );
list( $alt_aucun ) = $ST::nettoyer( array( 'figure_alttext' => '' ), $alt_choisi );
na_verifier( array( 'figure_alttext' => 'TEI_figure_alttext' ) === $alt_choisi && empty( $alt_refuse ) && array() === $alt_aucun
	&& '' === $ST::libelle_du_vide( array( 'type' => 'paragraphe' ) ),
	'le paragraphe du texte alternatif se règle : un style de la feuille, ou aucun paragraphe', array( $alt_choisi, $alt_aucun ) );
$reglages_alt = get_option( Notice_Archeomed_Settings::OPTION_NAME, array() );
update_option( Notice_Archeomed_Settings::OPTION_NAME, array_merge( (array) $reglages_alt, array( $ST::CLE => $alt_choisi ) ) );
$ST::oublier();
$doc_alt2 = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
na_appel( $plugin, 'remplir_le_document', array( $doc_alt2, $saisie_alt ) );
$xml_alt2 = implode( '', $corps->getValue( $doc_alt2 ) );
update_option( Notice_Archeomed_Settings::OPTION_NAME, $reglages_alt );
$ST::oublier();
$suite_alt2 = $bloc_de_figure( $xml_alt2 );
na_verifier( 1 === substr_count( $xml_alt2, '<w:pStyle w:val="TEIfigurealttext"/></w:pPr><w:r><w:t xml:space="preserve">Plan des murs' )
	&& array( 'TEIfigurecaption Le fossé longe le mur ouest.', 'TEIfigurecredits DAO A.', 'TEIfigurealttext Plan des murs & du fossé' )
		=== array_slice( $suite_alt2, -4, 3 )
	&& 0 === strpos( (string) end( $suite_alt2 ), 'TEIfigureend' ) && 1 === substr_count( $xml_alt2, 'TEIfigurealttext' ),
	'réglé, le texte alternatif a son paragraphe stylé à la fin du bloc, après la légende et sa description ; une figure ancienne n\'en a pas',
	$suite_alt2 );
$relu_alt = na_appel( $plugin, 'page_de_relecture', array( 'I', array( $saisie_alt ) ) );
$courriel_alt = na_appel( $plugin, 'illustrations_block', array( $saisie_alt ) );
$resp_relu = isset( $saisie_alt['resp_nom'] ) ? $saisie_alt['resp_nom'] : '';
na_verifier( '' !== $resp_relu && 1 === preg_match( '#<div class="texte">.*\(Responsable[^<]*' . preg_quote( $resp_relu, '#' ) . '[^<]*\)</p></div>#us', $relu_alt )
	&& false === strpos( $relu_alt, '<p class="meta">(Responsable' ),
	'la page de relecture colle la mention de responsabilité au dernier paragraphe, dans la police du texte, comme le document', substr( $relu_alt, (int) strpos( $relu_alt, 'class="texte"' ), 400 ) );
na_verifier( false !== strpos( $relu_alt, 'alt="Plan des murs &amp; du fossé" aria-describedby="description-1"' ) && false !== strpos( $relu_alt, 'alt="Vue">' )
	&& false !== strpos( $relu_alt, '<div class="description" id="description-1">' ) && false !== strpos( $relu_alt, '<p>Le fossé longe le mur ouest.</p>' )
	&& false !== strpos( $courriel_alt, "Texte alternatif\u{00A0}: Plan des murs &amp; du fossé" )
	&& false !== strpos( $courriel_alt, "Description détaillée\u{00A0}: Deux murs parallèles, orientés nord-sud.<br>Le fossé longe" ),
	'la relecture donne le texte alternatif à l\'image (le titre à défaut) et la relie à sa description ; le courriel montre l\'un et l\'autre', array( $courriel_alt ) );
$_POST = array( 'reprise_jeton' => '', 'illus_titre' => array( 'Plan' ), 'illus_legende' => array( '' ), 'illus_credits' => array( '' ),
	'illus_alt' => array( 'Plan des murs' ), 'illus_description' => array( "Deux murs.\nUn fossé." ), 'lieu_dit' => 'Château' );
$_FILES = array( 'illustrations' => array( 'name' => array( 'plan.jpg' ), 'error' => array( 0 ) ) );
$jeton_alt = na_appel( $plugin, 'garder_la_saisie', array( 60 ) );
$reserve_alt = get_transient( 'na_reprise_' . $jeton_alt );
delete_transient( 'na_reprise_' . $jeton_alt );
$_POST = array();
$_FILES = array();
na_verifier( is_array( $reserve_alt ) && array( 'Plan des murs' ) === $reserve_alt['illus_alt'] && array( "Deux murs.\nUn fossé." ) === $reserve_alt['illus_description'],
	'la saisie gardée, au refus comme au lien de correction, garde le texte alternatif et la description', $reserve_alt );
$_POST = array( 'illus_titre' => array( 'Plan' ), 'illus_alt' => array( 'Plan' ),
	'illus_description' => array( "Premier  paragraphe,\n\n\n second <b>gras</b>.\n" . str_repeat( 'y', 2100 ) ) );
$_FILES = array( 'illustrations' => array( 'name' => array( 'plan.jpg' ), 'error' => array( 0 ) ) );
$decrite = na_appel( $plugin, 'collect_illustrations' );
$_POST = array();
$_FILES = array();
na_verifier( 0 === strpos( $decrite[0]['description'], "Premier paragraphe,\nsecond gras.\nyyy" ) && 2000 === mb_strlen( $decrite[0]['description'], 'UTF-8' ),
	'la description détaillée se collecte en paragraphes simples, sans balise, coupée à 2 000 caractères', mb_substr( $decrite[0]['description'], 0, 60 ) );

WP_CLI::log( 'La mise en forme des textes des figures' );
$FO = 'Notice_Archeomed_Forme';
na_verifier( 'Plan du <em>castrum</em>' === $FO::accorder( 'Plan du <em>castrum</em>', 'Plan du castrum' )
	&& 'Plan du <em>castrum</em>' === $FO::accorder( ' plan  du <em>castrum</em>. ', 'Plan du castrum' )
	&& 'Plan du <em>castrum</em>' === $FO::accorder( 'Fig. 3 : Plan du <em>castrum</em>', 'Plan du castrum', array( 'rang' => 3 ) ),
	'la forme d\'une figure s\'accorde au texte gardé : capitale, blancs et point final, « Fig. 3 » retapé au titre' );
na_verifier( '' === $FO::accorder( 'Plan du <em>donjon</em>', 'Plan du castrum' ) && '' === $FO::accorder( 'Plan du castrum', 'Plan du castrum' )
	&& '' === $FO::accorder( 'Fig. 2 : Plan du <em>castrum</em>', 'Plan du castrum', array( 'rang' => 3 ) ) && '' === $FO::accorder( '<em>a</em>', '' )
	&& '' === $FO::accorder( array( '<em>a</em>' ), 'a' ),
	'une forme qui ment sur le texte, ne marque rien ou porte un autre numéro se perd, sans erreur' );
na_verifier( '<em>a</em>&lt;b&gt;' === $FO::accorder( '<img src=x onerror=alert(1)><em>a</em>&lt;b&gt;<script>', 'a<b>' )
	&& 'L. <em>&amp;lt;5</em> cm' === $FO::accorder( 'L. <em>&lt;5</em> cm', 'L. &lt;5 cm' ),
	'la forme est réécrite sans attribut ni balise étrangère ; le chevron que le serveur écrit « &lt; » s\'y accorde' );
na_verifier( '<em>' . str_repeat( 'a', 400 ) . '</em>' === $FO::accorder( '<em>' . str_repeat( 'a', 450 ) . '</em>', str_repeat( 'a', 400 ), array( 'max' => 400 ) )
	&& '' === $FO::accorder( '<em>' . str_repeat( 'a', 450 ) . '</em>', str_repeat( 'a', 400 ) )
	&& '' === $FO::accorder( '<em>' . str_repeat( 'a', 450 ) . '</em>', str_repeat( 'a', 399 ), array( 'max' => 400 ) ),
	'un texte coupé à sa borne garde la forme du début ; sous la borne, une fin qui manque la fait perdre' );
$fo_par = $FO::paragraphes( '<p>Deux murs <em>in situ</em>.</p><p></p><p>Le  fossé.</p>', "Deux murs in situ.\nLe fossé." );
na_verifier( 2 === count( $fo_par ) && 'Deux murs <em>in situ</em>.' === $FO::html( $fo_par[0], false ) && 'Le fossé.' === $FO::html( $fo_par[1], false )
	&& array( array( array( 'text' => 'Sans forme.', 'i' => false, 'pc' => false, 'sup' => false ) ) ) === $FO::paragraphes( '<em>Autre</em>', 'Sans  forme.' ),
	'la description se forme paragraphe par paragraphe, les lignes vides ôtées ; une forme qui ne s\'accorde pas laisse le texte en romain', $fo_par );
$_POST = array(
	'illus_titre'             => array( 'Fig. 1 : Plan du castrum, tour Nord', 'Vue' ),
	'illus_titre_forme'       => array( 'Fig. 1 : Plan du <em>castrum</em>, tour <span class="na-pc">Nord</span>', '<em>Coupe</em>' ),
	'illus_legende'           => array( 'Le  castrum vu du nord, cote Ab', 'Rien' ),
	'illus_legende_forme'     => array( 'Le <em>castrum</em> vu du nord, cote A<sup>b</sup>', array( '<em>Rien</em>' ) ),
	'illus_credits'           => array( 'DAO A.', '' ),
	'illus_alt'               => array( "Plan des murs\nDeux murs in situ.\n\nLe fossé de la tour Nord c.", 'Vue du site' ),
	'illus_description_forme' => array( 'Deux murs <em>in situ</em>.<br><br>Le fossé de la tour <span class="na-pc">Nord</span> <sup>c</sup>.', '' ),
);
$_FILES = array( 'illustrations' => array( 'name' => array( 'plan.jpg', 'vue.jpg' ), 'error' => array( 0, 0 ) ) );
$fo_items = na_appel( $plugin, 'collect_illustrations' );
$_POST  = array();
$_FILES = array();
$fo_1 = isset( $fo_items[0] ) ? $fo_items[0] : array();
$fo_2 = isset( $fo_items[1] ) ? $fo_items[1] : array();
na_verifier( isset( $fo_1['titre_forme'] ) && 'Plan du <em>castrum</em>, tour <span class="na-pc">Nord</span>' === $fo_1['titre_forme'] && 'Plan du castrum, tour Nord' === $fo_1['titre']
	&& 1 === preg_match( '#^Le\s+<em>castrum</em> vu du nord, cote A<sup>b</sup>$#u', (string) $fo_1['legende_forme'] )
	&& 'Plan des murs' === $fo_1['alt'] && ! isset( $fo_1['alt_forme'] )
	&& 1 === preg_match( '#^Deux murs <em>in situ</em>\.<br>Le fossé de la tour <span class="na-pc">Nord</span> <sup>c</sup>\.$#u', (string) $fo_1['description_forme'] ),
	'au dépôt, titre, légende et description gardent leur forme, accordée au texte ; le texte alternatif n\'en a pas', $fo_1 );
na_verifier( array( '', '', '' ) === array( $fo_2['titre_forme'], $fo_2['legende_forme'], $fo_2['description_forme'] ) && 'Vue' === $fo_2['titre'],
	'une forme menteuse ou envoyée en tableau s\'oublie : les trois clefs restent, vides', $fo_2 );
$fo_saisie = array_merge( $saisie_styles, array( 'illustrations' => array( array_merge( $fo_1, array( 'figure' => $figure_du_dossier ) ) ) ) );
$fo_doc = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
na_appel( $plugin, 'remplir_le_document', array( $fo_doc, $fo_saisie ) );
$fo_xml  = implode( '', $corps->getValue( $fo_doc ) );
$fo_bloc = substr( $fo_xml, (int) strpos( $fo_xml, 'TEIfigurestart' ) );
$fo_bloc = substr( $fo_bloc, 0, (int) strpos( $fo_bloc, 'TEIfigureend' ) );
$fo_vus  = array();
preg_match_all( '#<w:r>(?:<w:rPr>(.*?)</w:rPr>)?<w:t[^>]*>(.*?)</w:t></w:r>#s', $fo_bloc, $fo_m, PREG_SET_ORDER );
foreach ( $fo_m as $r ) {
	$fo_vus[] = ( false !== strpos( $r[1], '<w:i/>' ) ? 'i' : '' ) . ( false !== strpos( $r[1], 'smallCaps' ) ? 'pc' : '' )
		. ( false !== strpos( $r[1], 'superscript' ) ? 'sup' : '' ) . ':' . html_entity_decode( $r[2], ENT_QUOTES | ENT_XML1, 'UTF-8' );
}
na_verifier( 2 <= count( preg_grep( '/^i:castrum$/u', $fo_vus ) ) && 2 === count( preg_grep( '/^pc:Nord$/u', $fo_vus ) )
	&& 1 === count( preg_grep( '/^sup:b$/u', $fo_vus ) ) && 1 === count( preg_grep( '/^sup:c$/u', $fo_vus ) ) && 1 === count( preg_grep( '/^i:in situ$/u', $fo_vus ) )
	&& 0 === count( preg_grep( '#</?(?:em|sup|span)\b|na-pc#', $fo_vus ) ),
	'au Word, l\'italique, les petites capitales et l\'exposant de l\'auteur passent au titre, à la légende et à la description', $fo_vus );
$fo_suite = $bloc_de_figure( $fo_xml );
na_verifier( false !== strpos( $fo_xml, 'descr="Plan des murs" title="Plan des murs"' ) && false === strpos( $fo_xml, 'TEIfigurealttext' )
	&& 1 === count( preg_grep( '/^TEIfigurecaption Description : Deux murs in situ\.$/u', $fo_suite ) )
	&& 1 === count( preg_grep( '/^TEIfigurecaption Le fossé de la tour Nord c\.$/u', $fo_suite ) ),
	'le texte alternatif reste sans forme dans l\'image, la description garde ses deux paragraphes', $fo_suite );
$fo_retouche = $fo_saisie;
$fo_retouche['illustrations'][0]['titre'] = 'Plan du donjon, tour Nord';
$fo_doc2 = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
na_appel( $plugin, 'remplir_le_document', array( $fo_doc2, $fo_retouche ) );
$fo_xml2 = implode( '', $corps->getValue( $fo_doc2 ) );
na_verifier( 1 === preg_match( '#Plan du donjon, tour Nord</w:t>#u', $fo_xml2 ) && false === strpos( $fo_xml2, 'castrum, tour' ),
	'un titre retouché ailleurs perd sa forme au Word, jamais une lettre' );
$fo_page     = na_appel( $plugin, 'page_de_relecture', array( 'I', array( $fo_saisie ) ) );
$fo_courriel = na_appel( $plugin, 'illustrations_block', array( $fo_saisie ) );
na_verifier( 1 === preg_match( '#<figcaption><strong>Fig\.[^<]*1</strong>.*?Plan du <em>castrum</em>, tour <span class="na-pc" style="font-variant:small-caps">Nord</span>#us', $fo_page )
	&& 1 === preg_match( '#<br>Le\s+<em>castrum</em> vu du nord, cote A<sup>b</sup>#u', $fo_page )
	&& 1 === preg_match( '#</strong> Deux murs <em>in situ</em>\.</p><p>Le fossé de la tour <span class="na-pc"[^>]*>Nord</span> <sup>c</sup>\.</p>#u', $fo_page )
	&& false !== strpos( $fo_page, 'alt="Plan des murs"' ),
	'la page de relecture montre la forme du titre, de la légende et de la description ; l\'image garde son texte alternatif brut', $fo_page );
na_verifier( false !== strpos( $fo_courriel, 'Plan du <em>castrum</em>, tour <span class="na-pc" style="font-variant:small-caps">Nord</span>' )
	&& 1 === preg_match( '#Description détaillée\x{00A0}: Deux murs <em>in situ</em>\.<br>Le fossé de la tour <span[^>]*>Nord</span> <sup>c</sup>\.#u', $fo_courriel )
	&& false !== strpos( $fo_courriel, "Texte alternatif\u{00A0}: Plan des murs<" ),
	'le courriel aussi, les petites capitales avec leur style', $fo_courriel );
na_verifier( false !== strpos( (string) na_appel( $plugin, 'illustrations_block', array( array( 'illustrations' => array(
		array( 'rang' => 1, 'titre' => 'A <b> & c', 'legende' => '', 'credits' => '', 'titre_forme' => '<em>Autre</em>' ) ) ) ) ), 'A &lt;b&gt; &amp; c' ),
	'sans forme accordée, le titre s\'affiche échappé comme avant' );
$fo_avant = array( 'illustrations' => array( $fo_1 ) );
$fo_apres = $fo_avant;
$fo_apres['illustrations'][0]['legende_forme'] = '';
na_verifier( na_appel( $plugin, 'textes_des_figures', array( $fo_avant['illustrations'] ) ) !== na_appel( $plugin, 'textes_des_figures', array( $fo_apres['illustrations'] ) )
	&& na_appel( $plugin, 'textes_des_figures', array( array( array( 'titre' => 'A' ) ) ) ) === na_appel( $plugin, 'textes_des_figures', array( array( array( 'titre' => 'A', 'titre_forme' => '' ) ) ) ),
	'ôter la seule forme d\'une légende change la figure ; une clef vide d\'une notice ancienne ne la change pas' );
$_POST = array( 'reprise_jeton' => '', 'illus_titre' => array( 'Plan' ), 'illus_alt' => array( 'Plan des murs' ),
	'illus_titre_forme' => array( '<em onclick="x()">Plan</em><script>y</script>' ), 'illus_legende_forme' => array( array( 'x' ) ),
	'illus_description_forme' => array( 'Rien de marqué' ), 'lieu_dit' => 'Château' );
$_FILES = array( 'illustrations' => array( 'name' => array( 'plan.jpg' ), 'error' => array( 0 ) ) );
$fo_jeton = na_appel( $plugin, 'garder_la_saisie', array( 60 ) );
$fo_reserve = get_transient( 'na_reprise_' . $fo_jeton );
delete_transient( 'na_reprise_' . $fo_jeton );
$_POST  = array();
$_FILES = array();
na_verifier( is_array( $fo_reserve ) && array( '<em>Plan</em>y' ) === $fo_reserve['illus_titre_forme']
	&& array( '' ) === $fo_reserve['illus_legende_forme'] && array( '' ) === $fo_reserve['illus_description_forme'],
	'la saisie gardée réécrit la forme : rien que les marques et le texte, rien pour ce qui n\'en est pas une', $fo_reserve );

WP_CLI::log( 'Les avis d\'accessibilité des figures' );
$C = 'Notice_Archeomed_Controles';
$avis_de = function ( $item ) use ( $C ) {
	return $C::accessibilite_de_la_figure( array_merge( array( 'rang' => 1, 'titre' => '', 'legende' => '', 'credits' => 'A', 'description' => 'Décrite.' ), $item ) );
};
$a_la_legende = function ( $alt, $legende ) use ( $avis_de ) {
	return 1 === count( preg_grep( '/reprend le complément de légende/u', $avis_de( array( 'alt' => $alt, 'legende' => $legende ) ) ) );
};
na_verifier( $a_la_legende( 'Plan général des vestiges de la phase 2', 'Fig. 12. Plan général des vestiges de la phase 2.' )
	&& $a_la_legende( 'PLAN GÉNÉRAL des vestiges de la phase 2 !', 'Plan general des vestiges de la phase 2' )
	&& $a_la_legende( 'Vue du foyer du bâtiment 1, côté nord', 'Fig. 8. Vue du foyer du bâtiment 1.' )
	&& $a_la_legende( 'Plan général des vestiges, phase 2', 'Plan général des vestiges de la phase 2' )
	&& ! $a_la_legende( 'Deux bâtiments occupent l’ouest de l’enclos fossoyé, entourés de fosses et de silos.', 'Fig. 12. Plan général des vestiges de la phase 2.' )
	&& ! $a_la_legende( 'Foyer quadrangulaire installé contre la paroi nord du bâtiment.', 'Fig. 8. Vue du foyer du bâtiment 1.' )
	&& ! $a_la_legende( 'Le fossé', 'Le fossé nord et la palissade.' ),
	'un texte alternatif qui reprend la légende — identique à la casse, à la ponctuation et au « Fig. N » près, contenu, ou presque tous ses mots — reçoit un avis ; les exemples de la rédaction, non' );
$sans_contenu = array();
foreach ( array( 'Image', 'photo 3', 'Fig. 2', 'Plan', 'Illustration n° 2', 'Figure 2a', 'dessin.' ) as $vide_de_sens ) {
	$sans_contenu[ $vide_de_sens ] = count( preg_grep( '/ne dit pas ce qu’on voit/u', $avis_de( array( 'alt' => $vide_de_sens ) ) ) );
}
na_verifier( array( 1 ) === array_values( array_unique( $sans_contenu ) )
	&& array() === preg_grep( '/ne dit pas/u', $avis_de( array( 'alt' => 'Plan du château et de ses fossés' ) ) ),
	'« Image », « Photo 3 », « Fig. 2 », « Plan »… ne disent rien de l\'image ; « Plan du château et de ses fossés », si', $sans_contenu );
$fichiers = array();
foreach ( array( 'image1.jpg' => '', 'DSC_0042.JPG' => '', 'IMG_20240512_101010' => '', 'P1030456' => '', 'scan 0012.tif' => '',
	'coupe-sud' => 'coupe-sud.tif', 'Coupe sud.TIF' => 'autre.jpg' ) as $alt_fichier => $depose ) {
	$fichiers[ $alt_fichier ] = count( preg_grep( '/est un nom de fichier/u', $avis_de( array( 'alt' => $alt_fichier, 'fichier_depose' => $depose ) ) ) );
}
na_verifier( array( 1 ) === array_values( array_unique( $fichiers ) )
	&& array() === $avis_de( array( 'alt' => 'Vue aérienne du site, depuis le sud', 'fichier_depose' => 'vue.jpg' ) )
	&& 1 === count( $avis_de( array( 'alt' => 'image1.jpg', 'titre' => 'image1.jpg' ) ) ),
	'un nom de fichier — extension d\'image, nom d\'appareil, nom du fichier déposé — reçoit un avis, et lui seul', $fichiers );
// Un plan, une carte, une coupe : le texte alternatif suffit, on ne réclame pas
// une description de plus à qui a rempli le champ.
$figures_a_decrire = array(
	array( 'titre' => 'Coupe stratigraphique du fossé', 'alt' => 'Couches de remblai sur le fond du fossé' ),
	array( 'legende' => 'Relevé de l’élévation nord.', 'alt' => 'Mur à trois assises' ),
	array( 'type' => 'plan', 'alt' => 'Deux bâtiments à l’ouest d’un enclos' ),
	array( 'type' => 'carte', 'alt' => 'Les voies autour du château' ),
	array( 'type' => 'planche', 'alt' => 'Dix tessons numérotés' ),
);
$reclames = array();
foreach ( $figures_a_decrire as $figure ) {
	$reclames = array_merge( $reclames, preg_grep( '/description détaillée/u', $avis_de( array_merge( array( 'description' => '' ), $figure ) ) ) );
}
na_verifier( array() === $reclames, 'un plan, une carte, une planche, une coupe dont le texte alternatif est rempli ne reçoivent aucun avis de description', $reclames );
// La nature de la figure décide de ce qu'on demande.
$donnees = function ( $item ) use ( $avis_de ) {
	return count( preg_grep( '/ne se lit pas à l’écoute/u', $avis_de( array_merge( array( 'description' => '' ), $item ) ) ) );
};
na_verifier( 1 === $donnees( array( 'type' => 'graphique', 'alt' => 'Courbe des effectifs' ) )
	&& 1 === $donnees( array( 'type' => 'tableau', 'alt' => 'Tableau des mesures' ) )
	&& 0 === $donnees( array( 'type' => 'graphique', 'alt' => 'Courbe', 'description' => 'Valeurs : 1, 2, 3.' ) )
	&& 0 === $donnees( array( 'type' => 'photo', 'alt' => 'Vue du foyer' ) )
	&& 0 === $donnees( array( 'alt' => 'Courbe des effectifs' ) ),
	'un graphique ou un tableau en image sans données en texte reçoit un avis ; décrit, une photographie ou sans nature, non' );
na_verifier( 'graphique' === $C::type_de_figure( ' Graphique ' ) && '' === $C::type_de_figure( 'image' ) && '' === $C::type_de_figure( array( 'photo' ) ),
	'la nature de la figure se reconnaît, espaces et casse ôtés ; une valeur inconnue se perd' );
$_POST = array( 'illus_titre' => array( 'Courbe', 'Vue' ), 'illus_alt' => array( 'Courbe', 'Vue' ), 'illus_type' => array( 'graphique', 'inconnue' ) );
$_FILES = array( 'illustrations' => array( 'name' => array( 'a.jpg', 'b.jpg' ), 'error' => array( 0, 0 ) ) );
$natures = na_appel( $plugin, 'collect_illustrations' );
$_POST = array();
$_FILES = array();
na_verifier( 'graphique' === $natures[0]['type'] && '' === $natures[1]['type'],
	'la nature de chaque figure se collecte dans l\'ordre des fichiers, et une valeur inconnue est écartée', array( $natures[0]['type'], $natures[1]['type'] ) );
$figures_a11y = array(
	array( 'rang' => 1, 'titre' => 'Coupe du fossé', 'legende' => 'Fig. 1. Coupe du fossé nord.', 'credits' => 'A', 'alt' => 'Coupe du fossé nord' ),
	array( 'rang' => 2, 'titre' => 'Vue', 'legende' => '', 'credits' => 'A', 'alt' => 'DSC_0042.JPG', 'description' => 'x' ) );
$avis_tous = $C::figures( $figures_a11y );
na_verifier( 1 === count( preg_grep( "/^Fig\\. 1\x{00A0}: la description de l’image reprend le complément de légende\x{00A0}; elle ne le répète pas, elle dit ce qu’on voit\\.$/u", $avis_tous ) )
	&& 1 === count( preg_grep( "/^Fig\\. 2\x{00A0}: la description de l’image «\x{00A0}DSC_0042\\.JPG\x{00A0}» est un nom de fichier/u", $avis_tous ) ),
	'au serveur, chaque avis suit « Fig. N : », dans la phrase même que reçoit le navigateur', $avis_tous );
$regles = $C::regles_d_accessibilite();
$motifs_js_sans_ucp = true;
foreach ( $regles['motifs'] as $motif ) {
	$motifs_js_sans_ucp = $motifs_js_sans_ucp && false === strpos( $motif, '(*' ) && false === strpos( $motif, '(?<' ) && false === strpos( $motif, '\\p' );
}
na_verifier( $regles['phrases'] === $C::phrases_d_accessibilite() && true === $regles['actif'] && 150 === $regles['conseille'] && 300 === $regles['max']
	&& $motifs_js_sans_ucp,
	'le formulaire reçoit du serveur les phrases, les motifs (lisibles par le navigateur) et les seuils', array_keys( $regles ) );
$reglages_a11y = get_option( Notice_Archeomed_Settings::OPTION_NAME, array() );
$poser_les_normes_a11y = function ( $normes ) use ( $reglages_a11y ) {
	update_option( Notice_Archeomed_Settings::OPTION_NAME, array_merge( (array) $reglages_a11y, array( Notice_Archeomed_Normes::CLE => $normes ) ) );
	Notice_Archeomed_Normes::oublier();
};
$sans_case = Notice_Archeomed_Normes::nettoyer( array( 'avis' => array( 'credits' ) ) );
$poser_les_normes_a11y( $sans_case );
$avis_eteints = $C::figures( $figures_a11y );
$actif_eteint = $C::regles_d_accessibilite()['actif'];
$poser_les_normes_a11y( array( 'avis' => array( 'credits', 'ordinaux' ) ) );
$avis_d_avant = $C::figures( $figures_a11y );
$poser_les_normes_a11y( array( 'alt_conseille' => 100 ) );
$long_100 = $C::accessibilite_de_la_figure( array( 'rang' => 1, 'alt' => str_repeat( 'mur ', 30 ), 'description' => 'x' ) );
$conseille_100 = $C::regles_d_accessibilite()['conseille'];
$propres_a11y = Notice_Archeomed_Normes::nettoyer( array( 'alt_conseille' => '999' ) );
$poser_les_normes_a11y( array() );
$long_150 = $C::accessibilite_de_la_figure( array( 'rang' => 1, 'alt' => str_repeat( 'mur ', 30 ), 'description' => 'x' ) );
update_option( Notice_Archeomed_Settings::OPTION_NAME, $reglages_a11y );
Notice_Archeomed_Normes::oublier();
na_verifier( array() === $avis_eteints && false === $actif_eteint && isset( $sans_case[ Notice_Archeomed_Normes::CASES_VUES ]['avis'] )
	&& 3 === count( $avis_d_avant ),
	'la case « accessibilité des figures » tait ces avis ; des avis enregistrés avant elle ne l\'éteignent pas', array( $avis_eteints, $avis_d_avant ) );
na_verifier( 100 === $conseille_100 && 1 === count( preg_grep( '/fait 119\x{00A0}caractères, pour 100 au plus/u', $long_100 ) )
	&& array() === $long_150 && 300 === $propres_a11y['alt_conseille'],
	'le seuil de longueur se règle dans les normes (150 par défaut), borné par la limite dure de 300', array( $long_100, $propres_a11y ) );
$reprise_prop->setValue( $plugin, null );
unset( $_GET['notice_reprise'] );
$rendu_a11y_form = (string) $plugin->render_form();
na_verifier( false !== strpos( $rendu_a11y_form, 'var A11Y = {' ) && false !== strpos( $rendu_a11y_form, wp_json_encode( $C::phrase( 'legende' ) ) )
	&& false !== strpos( $rendu_a11y_form, 'Deux bâtiments occupent l’ouest de l’enclos fossoyé' ) && false !== strpos( $rendu_a11y_form, 'Développez chaque sigle à sa première mention' )
	&& false !== strpos( $rendu_a11y_form, 'Appelez chaque figure dans le texte' ) && false === strpos( $rendu_a11y_form, '?.' ),
	'le formulaire porte les règles du serveur, les exemples du texte alternatif et les aides des sigles et des appels de figure' );
// Accessibilité du formulaire : voie de secours, déclaration, langue, nature.
$reglages_acces = get_option( Notice_Archeomed_Settings::OPTION_NAME );
$acces_avant    = get_option( Notice_Archeomed_Accessibilite::OPTION, null );
update_option( Notice_Archeomed_Settings::OPTION_NAME, array_merge( (array) $reglages_acces, array(
	'destinataires' => array( array( 'email' => 'redaction@example.org', 'notices' => 1, 'recap' => 0 ) ) ) ) );
update_option( Notice_Archeomed_Accessibilite::OPTION, array( 'ara' => array( 'declaration' => 'https://www.cnrs.fr/fr/accessibilite' ) ), false );
$rendu_acces = (string) $plugin->render_form();
update_option( Notice_Archeomed_Accessibilite::OPTION, array(), false );
$rendu_sans_declaration = (string) $plugin->render_form();
update_option( Notice_Archeomed_Settings::OPTION_NAME, $reglages_acces );
if ( null === $acces_avant ) { delete_option( Notice_Archeomed_Accessibilite::OPTION ); } else { update_option( Notice_Archeomed_Accessibilite::OPTION, $acces_avant, false ); }
na_verifier( false !== strpos( $rendu_acces, 'id="na-form" lang="fr"' )
	&& 1 === preg_match( '#class="na-intro-acces".*Envoyez votre notice par courriel.*mailto:.*href="https://www\.cnrs\.fr/fr/accessibilite">Déclaration d’accessibilité#us', $rendu_acces )
	&& false === strpos( $rendu_sans_declaration, 'Déclaration d’accessibilité' ) && false !== strpos( $rendu_sans_declaration, 'Envoyez votre notice par courriel' )
	&& false !== strpos( $rendu_acces, 'Nature de la figure' ) && false !== strpos( $rendu_acces, 'Ctrl+B' ),
	'le formulaire est en français, donne la voie de secours par courriel et, si elle est réglée, la déclaration d\'accessibilité ; il propose la nature de la figure et les raccourcis' );

WP_CLI::log( 'Les siècles sur la fiche' );
$fiche_siecle = wp_insert_post( array( 'post_type' => Notice_Archeomed_File::CPT, 'post_status' => 'private', 'post_title' => 'Siècles' ) );
update_post_meta( $fiche_siecle, '_na_notice', '<p>Église du <span style="font-variant: small-caps; font-variant-caps: all-small-caps">XII</span><sup>e</sup>&nbsp;s.</p>' );
ob_start();
$GLOBALS['notice_archeomed_file']->afficher_la_notice( get_post( $fiche_siecle ) );
$rendu_fiche_siecle = ob_get_clean();
na_verifier( false !== strpos( $rendu_fiche_siecle, 'all-small-caps' ) && false !== strpos( $rendu_fiche_siecle, '>XII<' )
	&& ! in_array( 'font-variant-caps', (array) apply_filters( 'safe_style_css', array() ), true ),
	'la fiche garde la réduction des siècles écrits en capitales, sans ouvrir le filtre au-delà de cet affichage', $rendu_fiche_siecle );
wp_delete_post( $fiche_siecle, true );

WP_CLI::log( 'Les sigles à développer' );
$liste_sigles = $C::sigles_de_la_norme( "SRA = service régional de l’archéologie\n  \n# une note = ignorée\nsans signe égal\nX = un seul caractère\nPCR =\n"
	. "Lidar = détection et télémétrie par la lumière (light detection and ranging)\nUS = unité stratigraphique   # la plus courante\nSRA = doublon\nInrap = Institut national de recherches archéologiques préventives" );
na_verifier( array( 'SRA', 'Lidar', 'US', 'Inrap' ) === array_column( $liste_sigles, 'sigle' )
	&& array( 'détection et télémétrie par la lumière', 'light detection and ranging' ) === $liste_sigles[1]['formes']
	&& 'unité stratigraphique' === $liste_sigles[2]['developpement'] && 'service régional de l’archéologie' === $liste_sigles[0]['developpement'],
	'la liste des sigles se lit ligne à ligne ; une ligne vide, une note ou une ligne mal formée sont ignorées, un doublon aussi', $liste_sigles );
$sigles_de = function ( $texte ) use ( $C, $liste_sigles ) {
	return $C::sigles_non_developpes( $C::paragraphes( $texte ), $liste_sigles );
};
na_verifier( array( "Le sigle «\u{00A0}SRA\u{00A0}» n’est pas développé à sa première mention\u{00A0}: écrivez par exemple «\u{00A0}service régional de l’archéologie (SRA)\u{00A0}»." )
		=== $sigles_de( '<p>Le SRA a prescrit la fouille.</p><p>Le service régional de l’archéologie (SRA) l’a suivie.</p>' ),
	'un sigle non développé à sa première mention reçoit l\'avis, dans la phrase dite ; la mention suivante, développée, n\'y change rien' );
na_verifier( array() === $sigles_de( '<p>Le service régional de l’archéologie (SRA) a prescrit la fouille. Le SRA l’a suivie.</p>' )
	&& array() === $sigles_de( '<p>Le SRA (Service Régional de l\'Archeologie) a prescrit.</p>' )
	&& array() === $sigles_de( '<p>Prescrite par le service archéologique de Normandie (SRA), la fouille a duré un mois.</p>' )
	&& array() === $sigles_de( '<p>Un relevé lidar (light detection and ranging) couvre le site.</p>' )
	&& array() === $sigles_de( '<p>Les fouilleurs ont bien travaillé : us et coutumes du chantier.</p>' ),
	'développé avant ou après dans la même phrase — casse, accents, articles comptés pour rien —, ou entre parenthèses : pas d\'avis ; « us » n\'est pas « US »' );
$avis_sigles = $sigles_de( '<p>L’INRAP a fouillé l’US 1023. Unité stratigraphique est un terme du métier.</p>' );
na_verifier( 2 === count( $avis_sigles ) && false !== strpos( $avis_sigles[0], "«\u{00A0}US\u{00A0}»" ) && false !== strpos( $avis_sigles[1], "«\u{00A0}Inrap\u{00A0}»" ),
	'« INRAP » est reconnu en toute casse ; le développement d\'une autre phrase ne compte pas', $avis_sigles );
$liste_casse = $C::sigles_de_la_norme( "CNRS = Centre national de la recherche scientifique\nInrap = Institut national de recherches archéologiques préventives\nUS = unité stratigraphique" );
$avis_casse  = $C::sigles_non_developpes( $C::paragraphes( '<p>Le rapport est en ligne sur https://www.inrap.fr/falaise. Un relevé lidar couvre le site.</p>' ), $liste_casse );
$avis_cnrs   = $C::sigles_non_developpes( $C::paragraphes( '<p>Le Cnrs a financé l’étude, le cnrs aussi.</p>' ), $liste_casse );
na_verifier( array() === $avis_casse && array() === $avis_cnrs,
	'un sigle épelé se reconnaît à sa seule casse ; un sigle dans une adresse web n\'est pas une mention', array( $avis_casse, $avis_cnrs ) );
$mal_ecrits = $C::sigles_mal_ecrits( $C::paragraphes( '<p>L’INRAP et le Cnrs ont fouillé. Us et coutumes. Voir https://INRAP.fr.</p>' ), $liste_casse );
$bien_ecrits = $C::sigles_mal_ecrits( $C::paragraphes( '<p>L’Inrap et le CNRS ont fouillé l’US 12.</p>' ), $liste_casse );
na_verifier( array(
		"«\u{00A0}Cnrs\u{00A0}» s’écrit «\u{00A0}CNRS\u{00A0}»\u{00A0}: un sigle qu’on épelle prend toutes ses capitales, un sigle qu’on prononce comme un mot n’en prend qu’une, à l’initiale.",
		"«\u{00A0}INRAP\u{00A0}» s’écrit «\u{00A0}Inrap\u{00A0}»\u{00A0}: un sigle qu’on épelle prend toutes ses capitales, un sigle qu’on prononce comme un mot n’en prend qu’une, à l’initiale.",
	) === $mal_ecrits && array() === $bien_ecrits,
	'la règle de la revue : « CNRS » qu’on épelle, « Inrap » qu’on prononce ; « Us » en tête de phrase n’est pas visé', array( $mal_ecrits, $bien_ecrits ) );
$reglages_sigles = get_option( Notice_Archeomed_Settings::OPTION_NAME, array() );
$texte_sigles = array( 'texte_notice' => '<p>Le SRA a prescrit la fouille.</p>', 'illustrations' => array() );
$avec_sigles = preg_grep( '/Le sigle/u', $C::avis( $texte_sigles ) );
update_option( Notice_Archeomed_Settings::OPTION_NAME, array_merge( (array) $reglages_sigles, array( Notice_Archeomed_Normes::CLE => Notice_Archeomed_Normes::nettoyer( array(
	'avis' => array( 'credits' ), 'sigles' => "<b>CAG</b> = Carte archéologique de la Gaule\nSRA = service régional de l’archéologie" ) ) ) ) );
Notice_Archeomed_Normes::oublier();
$sans_sigles = preg_grep( '/Le sigle/u', $C::avis( $texte_sigles ) );
$sigles_reglees = Notice_Archeomed_Normes::valeur( 'sigles' );
$regles_sans_sigles = $C::regles_d_accessibilite();
update_option( Notice_Archeomed_Settings::OPTION_NAME, $reglages_sigles );
Notice_Archeomed_Normes::oublier();
na_verifier( 1 === count( $avec_sigles ) && array() === $sans_sigles && array() === $regles_sans_sigles['sigles']
	&& "CAG = Carte archéologique de la Gaule\nSRA = service régional de l’archéologie" === $sigles_reglees
	&& false !== strpos( Notice_Archeomed_Normes::valeur( 'sigles' ), 'Inrap = Institut national de recherches archéologiques préventives' ),
	'l\'avis des sigles se donne par défaut, se tait par sa case ; la liste se règle dans les normes, sans balise', array( $avec_sigles, $sigles_reglees ) );
$regles_sigles = $C::regles_d_accessibilite();
na_verifier( count( $regles_sigles['sigles'] ) >= 20 && isset( $regles_sigles['phrases']['sigle'] ) && $C::LETTRES === $regles_sigles['lettres'],
	'le formulaire reçoit la liste des sigles et la phrase de l\'avis', count( $regles_sigles['sigles'] ) );

WP_CLI::log( 'L\'accessibilité des illustrations sur la fiche' );
$A = 'Notice_Archeomed_Accessibilite';
$saisie_fiche = array_merge( $saisie_styles, array( 'reference' => 'ACCES1', 'illustrations' => array(
	array( 'rang' => 1, 'titre' => 'Plan', 'legende' => 'Plan des vestiges.', 'credits' => 'DAO A.', 'alt' => 'Plan des murs', 'type' => 'graphique',
		'figure' => array( 'fichier' => 'icono/br/plan.jpg', 'largeur' => 800, 'hauteur' => 600, 'dpi' => 300 ) ),
	array( 'rang' => 2, 'titre' => 'Vue du fossé', 'legende' => '', 'credits' => '',
		'figure' => array( 'fichier' => 'icono/br/vue.jpg', 'largeur' => 800, 'hauteur' => 600, 'dpi' => 300 ) ) ) ) );
$fiche_a11y = na_notice( $saisie_fiche );
na_verifier( 'a_completer' === $C::etat_d_accessibilite( $saisie_fiche['illustrations'][1] )['etat']
	&& 'a_verifier' === $C::etat_d_accessibilite( $saisie_fiche['illustrations'][0] )['etat']
	&& 'ok' === $C::etat_d_accessibilite( array( 'rang' => 1, 'titre' => 'Vue', 'alt' => 'Foyer contre la paroi nord', 'description' => '' ) )['etat'],
	'une figure sans texte alternatif — une notice ancienne — est « à compléter » ; un avis la met « à vérifier » ; sinon « OK »' );
$utilisateur_avant = get_current_user_id();
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
$admin_a11y = empty( $admins ) ? 1 : (int) $admins[0];
wp_set_current_user( $admin_a11y );
$encart = new Notice_Archeomed_Accessibilite();
ob_start();
$encart->afficher_l_encart( get_post( $fiche_a11y ) );
$encart->poser_le_formulaire_des_figures();
$rendu_encart = (string) ob_get_clean();
na_verifier( false !== strpos( $rendu_encart, '<th scope="col">Figure</th><th scope="col">Légende</th><th scope="col">Crédits</th><th scope="col">Texte alternatif</th><th scope="col">Description détaillée</th><th scope="col">État</th>' )
	&& false !== strpos( $rendu_encart, 'À compléter' ) && false !== strpos( $rendu_encart, 'Le Word porte le titre à sa place' )
	&& false !== strpos( $rendu_encart, 'À vérifier' ) && false !== strpos( $rendu_encart, 'Figure de type graphique' )
	&& 2 === substr_count( $rendu_encart, 'name="na_alt[' ) && 5 === substr_count( $rendu_encart, 'form="na-a11y-figures"' )
	&& false !== strpos( $rendu_encart, '<form id="na-a11y-figures"' ) && false !== strpos( $rendu_encart, 'value="' . $A::ACTION_FIGURES . '"' )
	&& false !== strpos( $rendu_encart, '>Fig. 2<' ),
	'la fiche montre le tableau Figure | Légende | Crédits | Texte alternatif | Description détaillée | État, ses champs rattachés à un formulaire hors de celui de la fiche',
	substr( wp_strip_all_tags( $rendu_encart ), 0, 400 ) );
$vers_fiche = '';
$sortie_fiche = function ( $vers ) {
	throw new RuntimeException( (string) $vers );
};
$mourir_fiche = function () {
	return function ( $message ) {
		throw new RuntimeException( 'wp_die' );
	};
};
add_filter( 'wp_redirect', $sortie_fiche );
add_filter( 'wp_die_handler', $mourir_fiche );
$jouer_la_fiche = function ( $post ) use ( $encart ) {
	$_POST = $_REQUEST = $post;
	try {
		$encart->enregistrer_les_figures();
	} catch ( RuntimeException $e ) {
		return $e->getMessage();
	}
	return '';
};
$nonce_fiche = wp_create_nonce( $A::ACTION_FIGURES . '_' . $fiche_a11y );
$sans_jeton = $jouer_la_fiche( array( 'post' => $fiche_a11y, '_wpnonce' => 'faux', 'na_alt' => array( 1 => 'Pirate' ) ) );
$abonne = wp_insert_user( array( 'user_login' => 'abonne_a11y_' . wp_generate_password( 6, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
wp_set_current_user( (int) $abonne );
$sans_droit = $jouer_la_fiche( array( 'post' => $fiche_a11y, '_wpnonce' => wp_create_nonce( $A::ACTION_FIGURES . '_' . $fiche_a11y ), 'na_alt' => array( 1 => 'Pirate' ) ) );
wp_set_current_user( $admin_a11y );
$intacte = get_post_meta( $fiche_a11y, '_na_donnees', true );
// La fiche porte la version de la notice qu'elle a montrée.
$version_fiche = (string) $GLOBALS['notice_archeomed_file']->version_de( $fiche_a11y );
$vers_fiche = $jouer_la_fiche( array( 'post' => $fiche_a11y, '_wpnonce' => $nonce_fiche, 'na_version' => $version_fiche,
	'na_alt' => wp_slash( array( 1 => " Deux murs parallèles, <b>orientés</b>\n nord-sud ", 2 => 'L. <5 m ; fossé en V' . str_repeat( ' x', 200 ), 9 => 'Figure absente' ) ),
	'na_description' => wp_slash( array( 1 => "Le mur ouest.\n\n\nLe mur est.", 2 => '' ) ) ) );
$corrigee = get_post_meta( $fiche_a11y, '_na_donnees', true );
$historique = get_post_meta( $fiche_a11y, $A::HISTORIQUE, false );
$vers_rien = $jouer_la_fiche( array( 'post' => $fiche_a11y, '_wpnonce' => $nonce_fiche, 'na_version' => (string) $GLOBALS['notice_archeomed_file']->version_de( $fiche_a11y ),
	'na_alt' => array( 1 => 'Deux murs parallèles, orientés nord-sud' ) ) );
// Une fiche ouverte avant cet enregistrement porte encore l'ancienne version.
$vers_devancee = $jouer_la_fiche( array( 'post' => $fiche_a11y, '_wpnonce' => $nonce_fiche, 'na_version' => $version_fiche,
	'na_alt' => array( 1 => 'Valeur d\'une fiche restée ouverte' ) ) );
$apres_devancee = get_post_meta( $fiche_a11y, '_na_donnees', true );
$_POST = $_REQUEST = array();
remove_filter( 'wp_redirect', $sortie_fiche );
remove_filter( 'wp_die_handler', $mourir_fiche );
$sans_les_figures = function ( $d ) {
	unset( $d['illustrations'] );
	return $d;
};
na_verifier( 'wp_die' === $sans_jeton && 'wp_die' === $sans_droit && $saisie_fiche === $intacte,
	'sans jeton valable, ou sans le droit de régler l\'extension, rien n\'est enregistré' );
na_verifier( 'Deux murs parallèles, orientés nord-sud' === $corrigee['illustrations'][0]['alt']
	&& 300 === mb_strlen( $corrigee['illustrations'][1]['alt'], 'UTF-8' ) && 0 === strpos( $corrigee['illustrations'][1]['alt'], 'L. &lt;5 m ; fossé en V x' )
	&& "Le mur ouest.\nLe mur est." === $corrigee['illustrations'][0]['description'] && ! isset( $corrigee['illustrations'][1]['description'] )
	&& 2 === count( $corrigee['illustrations'] ) && $sans_les_figures( $saisie_fiche ) === $sans_les_figures( $corrigee )
	&& $saisie_fiche['illustrations'][0]['figure'] === $corrigee['illustrations'][0]['figure'] && 'Plan des vestiges.' === $corrigee['illustrations'][0]['legende'],
	'la fiche enregistre le texte alternatif et la description avec les nettoyages et les limites du dépôt, sans rien toucher d\'autre de la saisie',
	$corrigee['illustrations'] );
na_verifier( 3 === count( $historique ) && 'Plan des murs' === $historique[0]['avant'] && 'alt' === $historique[0]['champ'] && 1 === $historique[0]['figure']
	&& $admin_a11y === $historique[0]['utilisateur'] && '' !== $historique[0]['date'] && 'description' === $historique[1]['champ']
	&& '' === $historique[2]['avant'] && 2 === $historique[2]['figure'] && false !== strpos( $vers_fiche, 'na_figures=3' ) && false !== strpos( $vers_fiche, 'na_figures_coupe=1' )
	&& false !== strpos( $vers_fiche, '#na_accessibilite' ) && false !== strpos( $vers_rien, 'na_figures=0' ),
	'chaque valeur remplacée reste dans l\'historique (date, utilisateur, figure, champ, avant, après) ; un texte inchangé n\'y entre pas', array( $historique, $vers_fiche ) );
na_verifier( false !== strpos( $vers_devancee, 'na_figures=conflit' ) && $apres_devancee === $corrigee
	&& 3 === count( get_post_meta( $fiche_a11y, $A::HISTORIQUE, false ) ),
	'une fiche ouverte avant le dernier enregistrement ne l\'écrase pas : elle dit le conflit et n\'écrit rien', $vers_devancee );
$doc_fiche = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
// La saisie telle que le Word la reçoit ; l'image, que le site d'essai n'a
// pas, est posée comme le dossier la poserait.
$saisie_refaite = na_appel( $plugin, 'saisie_de', array( $fiche_a11y, false ) );
foreach ( array_keys( $saisie_refaite['illustrations'] ) as $i_fiche ) {
	$saisie_refaite['illustrations'][ $i_fiche ]['figure'] = $saisie_fiche['illustrations'][ $i_fiche ]['figure'];
}
na_appel( $plugin, 'remplir_le_document', array( $doc_fiche, $saisie_refaite ) );
$xml_fiche = implode( '', $corps->getValue( $doc_fiche ) );
na_verifier( false !== strpos( $xml_fiche, 'descr="Deux murs parallèles, orientés nord-sud"' ) && false === strpos( $xml_fiche, 'descr="Plan des murs"' )
	&& false !== strpos( $xml_fiche, 'descr="L. &lt;5 m ; fossé en V x' ),
	'le Word refait après la correction porte la valeur corrigée dans « descr »', preg_match_all( '#descr="[^"]{0,60}#u', $xml_fiche, $m_fiche ) ? $m_fiche[0] : array() );
$_GET['na_figures'] = '3';
ob_start();
$encart->afficher_l_encart( get_post( $fiche_a11y ) );
$rendu_encart2 = (string) ob_get_clean();
unset( $_GET['na_figures'] );
na_verifier( false !== strpos( $rendu_encart2, "3\u{00A0}textes enregistrés" ) && false !== strpos( $rendu_encart2, 'Historique des corrections (3)' )
	&& false !== strpos( $rendu_encart2, "était «\u{00A0}Plan des murs\u{00A0}», devient «\u{00A0}Deux murs parallèles" ) && false !== strpos( $rendu_encart2, 'L. &lt;5 m' ),
	'la fiche dit ce qui a été enregistré et montre l\'historique' );
$ancienne_a11y = na_notice( array_merge( $saisie_styles, array( 'illustrations' => array( array( 'rang' => 1, 'titre' => 'Plan', 'legende' => '', 'credits' => '' ) ) ) ) );
$recap_a11y = $A::recapitulatif_des_figures();
$ligne_ancienne = array_values( array_filter( $recap_a11y['liste'], function ( $l ) use ( $ancienne_a11y ) {
	return $l['id'] === $ancienne_a11y;
} ) );
ob_start();
$A::onglet();
$onglet_a11y = (string) ob_get_clean();
wp_set_current_user( $utilisateur_avant );
na_verifier( 1 === count( $ligne_ancienne ) && 1 === $ligne_ancienne[0]['a_completer'] && $recap_a11y['a_completer'] >= 1
	&& false !== strpos( $onglet_a11y, 'Les illustrations des notices reçues' ) && preg_match( '#post=' . $ancienne_a11y . '&(?:amp;|\#038;)action=edit\#na_accessibilite#', $onglet_a11y ),
	'l\'onglet Accessibilité compte les figures à compléter et à vérifier, avec un lien vers chaque fiche — une notice ancienne y est « à compléter »', $ligne_ancienne );
wp_delete_post( $fiche_a11y, true );
wp_delete_post( $ancienne_a11y, true );
wp_delete_user( (int) $abonne );

WP_CLI::log( 'Le texte alternatif, texte simple' );
$saisie_simple = array_merge( $saisie_styles, array( 'illustrations' => array(
	array( 'rang' => 1, 'titre' => 'Mur', 'legende' => '', 'credits' => '', 'alt' => "Mur du XIIe siècle,\n vu   du sud",
		'figure' => array( 'fichier' => 'icono/br/mur.jpg', 'largeur' => 800, 'hauteur' => 600, 'dpi' => 300 ) ) ) ) );
$reglages_simple = get_option( Notice_Archeomed_Settings::OPTION_NAME, array() );
update_option( Notice_Archeomed_Settings::OPTION_NAME, array_merge( (array) $reglages_simple, array( $ST::CLE => array( 'figure_alttext' => 'TEI_figure_alttext' ) ) ) );
$ST::oublier();
$doc_simple = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
na_appel( $plugin, 'remplir_le_document', array( $doc_simple, $saisie_simple ) );
$xml_simple = implode( '', $corps->getValue( $doc_simple ) );
update_option( Notice_Archeomed_Settings::OPTION_NAME, $reglages_simple );
$ST::oublier();
$para_simple = preg_match( '#<w:p><w:pPr><w:pStyle w:val="TEIfigurealttext"/></w:pPr>(.*?)</w:p>#', $xml_simple, $m_simple ) ? $m_simple[1] : '';
na_verifier( false !== strpos( $xml_simple, 'descr="Mur du XIIe siècle, vu du sud"' )
	&& false !== strpos( $para_simple, 'XIIe' ) && false === strpos( $para_simple, 'smallCaps' ) && false === strpos( $para_simple, 'vertAlign' )
	&& false === strpos( $para_simple, '<w:i/>' ) && false === strpos( $para_simple, '<w:b/>' ) && 1 === substr_count( $para_simple, '<w:r>' ),
	'texte simple, sur une ligne, sans siècles en petites capitales ni exposant, ni italique ni gras', $para_simple );
$langue_de = function ( $xml ) {
	$r = new ReflectionMethod( 'Notice_Archeomed_DOCX', 'langue_francaise' );
	$r->setAccessible( true );
	return $r->invoke( null, $xml );
};
$sans_langue = $langue_de( '<w:styles xmlns:w="x"><w:docDefaults><w:rPrDefault><w:rPr><w:sz w:val="24"/></w:rPr></w:rPrDefault></w:docDefaults></w:styles>' );
$sans_defaut = $langue_de( '<w:styles xmlns:w="x"><w:style/></w:styles>' );
$autre_langue = $langue_de( '<w:styles><w:docDefaults><w:rPrDefault><w:rPr><w:lang w:val="en-GB"/></w:rPr></w:rPrDefault></w:docDefaults></w:styles>' );
$feuille_livree = $langue_de( (string) file_get_contents( 'zip://' . Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) . '#word/styles.xml' ) );
na_verifier( false !== strpos( $sans_langue, '<w:sz w:val="24"/><w:lang w:val="fr-FR"/></w:rPr>' )
	&& false !== strpos( $sans_defaut, '<w:docDefaults><w:rPrDefault><w:rPr><w:lang w:val="fr-FR"/>' )
	&& false !== strpos( $autre_langue, 'en-GB' ) && false === strpos( $autre_langue, 'fr-FR' )
	&& 1 === preg_match( '#<w:rPrDefault><w:rPr>.*?<w:lang w:val="fr-FR"#s', $feuille_livree ),
	'le Word déclare le français par défaut ; une feuille sans langue le reçoit, une autre langue se garde', array( $sans_langue, $sans_defaut ) );

WP_CLI::log( 'La rubrique I renommée' );
$ancienne_i = 'I. Constructions et habitats civils';
$nouvelle_i = 'I. Constructions et habitats civils – Environnement rural et urbain';
$_POST = array( 'rubrique_principale' => $ancienne_i );
$collectee_i = na_appel( $plugin, 'collect_select', array( 'rubrique_principale', array( $nouvelle_i ), true ) );
$_POST = array();
$notice_i = na_notice( array_merge( $saisie_styles, array( 'rubrique_principale' => $ancienne_i ) ) );
update_post_meta( $notice_i, '_na_rubrique', $ancienne_i );
update_post_meta( $notice_i, '_na_classement', '01|1|caen' );
$trouvees_i = na_appel( $plugin, 'file' )->notices_de_la_rubrique( $nouvelle_i );
$saisie_i = na_appel( $plugin, 'saisie_de', array( $notice_i, false ) );
na_appel( $plugin, 'sortir_de_l_attente', array( $notice_i ) );
wp_delete_post( $notice_i, true );
na_verifier( $nouvelle_i === Notice_Archeomed_Pactols::rubrique_actuelle( $ancienne_i )
	&& in_array( $ancienne_i, Notice_Archeomed_Pactols::libelles_de_la_rubrique( $nouvelle_i ), true )
	&& $nouvelle_i === $collectee_i && in_array( $notice_i, $trouvees_i, true ) && $nouvelle_i === $saisie_i['rubrique_principale']
	&& 'I. – Constructions et habitats civils – Environnement rural et urbain' === $plugin->titre_de_rubrique( $ancienne_i ),
	'l\'ancien libellé de la rubrique I vaut le nouveau : reprise, fascicule, sortie imprimée', array( $collectee_i, $trouvees_i ) );

WP_CLI::log( 'La rubrique V par matière' );
$rub_v = Notice_Archeomed_Pactols::RUBRIQUE_ARTISANAT;
$notice_v = function ( $commune, $nature, $matiere ) use ( $saisie_styles, $rub_v ) {
	return array_merge( $saisie_styles, array( 'rubrique_principale' => $rub_v, 'commune' => $commune,
		'lieux' => array( array( 'nom' => $commune, 'ark' => '' ) ), 'nature' => $nature, 'rubrique_matiere' => $matiere ) );
};
$titres_v = array();
foreach ( array( array( 'A', 'Fouille préventive' ), array( 'A', 'Prospection pédestre' ), array( 'A', 'Projet collectif de recherche' ),
	array( 'B', 'Fouille préventive' ), array( 'B', 'Prospection pédestre' ), array( 'B', 'Projet collectif de recherche' ),
	array( 'C', 'Fouille préventive' ), array( 'C', 'Prospection pédestre' ), array( 'C', 'Projet collectif de recherche' ) ) as $cas_v ) {
	$titres_v[] = $plugin->titre_de_famille( $notice_v( 'Caen', $cas_v[1], $cas_v[0] ) );
}
na_verifier( array(
	"V. A1. – Céramique, terres cuites architecturales, verrerie\u{00A0}: opération de terrain",
	"V. A2. – Céramique, terres cuites architecturales, verrerie\u{00A0}: prospections",
	"V. A3. – Céramique, terres cuites architecturales, verrerie\u{00A0}: projets collectifs de recherche",
	"V. B1. – Carrières, mines et métallurgie\u{00A0}: opération de terrain",
	"V. B2. – Carrières, mines et métallurgie\u{00A0}: prospections",
	"V. B3. – Carrières, mines et métallurgie\u{00A0}: projets collectifs de recherche",
	"V. C1. – Autres installations artisanales\u{00A0}: opération de terrain",
	"V. C2. – Autres installations artisanales\u{00A0}: prospections",
	"V. C3. – Autres installations artisanales\u{00A0}: projets collectifs de recherche",
) === $titres_v, 'les sous-rubriques V. A1 à C3 s\'écrivent comme la rédaction les écrit', $titres_v );
$v_ordre = array( $notice_v( 'Dives', 'Fouille préventive', 'C' ), $notice_v( 'Bayeux', 'Prospection pédestre', 'A' ),
	$notice_v( 'Lisieux', 'Fouille préventive', 'A' ), $notice_v( 'Alençon', 'Fouille préventive', '' ),
	$notice_v( 'Argentan', 'Fouille préventive', 'A' ) );
$erreur_v = '';
$chemin_v = na_appel( $plugin, 'fabriquer_le_fascicule', array( $rub_v, $v_ordre, &$erreur_v ) );
$xml_v = '' !== $chemin_v ? (string) file_get_contents( 'zip://' . $chemin_v . '#word/document.xml' ) : '';
if ( '' !== $chemin_v ) {
	@unlink( $chemin_v );
}
preg_match_all( '#<w:pStyle w:val="(TEITitre1rubrique|TEITitre2notice)"/></w:pPr>(.*?)</w:p>#', $xml_v, $m_v, PREG_SET_ORDER );
$suite_v = array();
foreach ( $m_v as $p_v ) {
	$texte_v = html_entity_decode( preg_replace( '#<[^>]+>#', '', $p_v[2] ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
	$suite_v[] = 'TEITitre1rubrique' === $p_v[1]
		? ( preg_match( '/^V\.\s*([A-C]?\d)\./u', $texte_v, $mm_v ) ? '# V. ' . $mm_v[1] . '.' : $texte_v ) : strtok( $texte_v, ' ' );
}
na_verifier( array( '# V. 1.', 'Alençon', '# V. A1.', 'Argentan', 'Lisieux', '# V. A2.', 'Bayeux', '# V. C1.', 'Dives' ) === $suite_v
	&& false !== strpos( $xml_v, 'Sous-rubrique à choisir (A, B ou C)' ),
	'le fascicule V se range par matière, puis par famille, puis par commune ; une notice sans matière reste sans lettre, et le dit', $suite_v );
na_verifier( 0 === substr_count( $xml_v, 'Sous-rubrique à choisir' ) - 1, 'la ligne « matière à choisir » ne vaut que pour la notice sans matière' );
$manque_v = na_appel( $plugin, 'champs_manquants', array( $notice_v( 'Caen', 'Fouille préventive', '' ) ) );
$manque_i = na_appel( $plugin, 'champs_manquants', array( array_merge( $saisie_styles, array( 'rubrique_principale' => $nouvelle_i, 'rubrique_matiere' => '' ) ) ) );
na_verifier( in_array( 'rubrique_matiere', $manque_v, true ) && ! in_array( 'rubrique_matiere', $manque_i, true )
	&& ! in_array( 'rubrique_matiere', na_appel( $plugin, 'champs_manquants', array( $notice_v( 'Caen', 'Fouille préventive', 'B' ) ) ), true ),
	'la matière est obligatoire pour la rubrique V, et pour elle seule', array( $manque_v, $manque_i ) );
$libre_k   = array( array( 'label' => 'four à chaux', 'ark' => '', 'idConcept' => '' ) );
$manque_k  = na_appel( $plugin, 'champs_manquants', array( array_merge( $saisie_styles, array( 'pactols_periods_items' => array(), 'pactols_subjects_items' => array(), 'pactols_places_items' => array() ) ) ) );
$libres_k  = na_appel( $plugin, 'champs_manquants', array( array_merge( $saisie_styles, array( 'pactols_periods_items' => $libre_k, 'pactols_subjects_items' => $libre_k, 'pactols_places_items' => array() ) ) ) );
$ordre_k   = array_keys( na_appel( $plugin, 'champs_du_formulaire' ) );
na_verifier( in_array( 'pactols_periods', $manque_k, true ) && in_array( 'pactols_subjects', $manque_k, true )
	&& ! in_array( 'pactols_places', $manque_k, true )
	&& ! array_intersect( array( 'pactols_periods', 'pactols_subjects' ), $libres_k )
	&& array_search( 'rubrique_principale', $ordre_k, true ) < array_search( 'nature', $ordre_k, true ),
	'une période et un sujet sont obligatoires, un terme libre suffit, les autres lieux restent facultatifs ; la rubrique vient avant la nature', array( $manque_k, $libres_k ) );
$courriel_v = na_appel( $plugin, 'build_notice', array( array_merge( $notice_v( 'Caen', 'Fouille préventive', 'B' ), array( 'reference' => 'REFV01' ) ) ) );
na_verifier( false !== strpos( $courriel_v, 'Sous-rubrique' ) && false !== strpos( $courriel_v, 'B – Carrières, mines et métallurgie' ),
	'le courriel et la fiche disent la matière' );
$_POST = array( 'rubrique_principale' => $rub_v, 'rubrique_matiere' => 'b', 'lieu_dit' => 'Château' );
$jeton_v = na_appel( $plugin, 'garder_la_saisie', array( 60 ) );
$reserve_v = get_transient( 'na_reprise_' . $jeton_v );
delete_transient( 'na_reprise_' . $jeton_v );
$_POST = array();
na_verifier( is_array( $reserve_v ) && 'b' === $reserve_v['rubrique_matiere'], 'la matière se garde à la reprise et à la correction', $reserve_v );

WP_CLI::log( 'L\'onglet Accessibilité' );
$A = 'Notice_Archeomed_Accessibilite';
$controle_brut = array(
	'page'       => 'http://127.0.0.1/depot/',
	'moteur'     => '4.10.2',
	'score'      => 100,
	'conformes'  => array( array( 'id' => 'image-alt', 'explication' => 'Image sans texte alternatif.' ), array( 'id' => 'label' ), array( 'pas' => 'de id' ) ),
	'echecs'     => array( array( 'id' => 'color-contrast', 'gravite' => 'grave', 'n' => '11', 'explication' => '<b>Contraste</b>', 'origine' => 'thème' ),
		array( 'id' => 'link-name', 'gravite' => 'pirate', 'n' => -4, 'origine' => 'ailleurs' ) ),
	'a_verifier' => array_fill( 0, 200, array( 'id' => 'color-contrast', 'n' => 1 ) ),
);
$controle_propre = $A::nettoyer_le_controle( $controle_brut );
na_verifier( 50 === $controle_propre['score'] && 2 === count( $controle_propre['conformes'] ) && 2 === count( $controle_propre['echecs'] )
	&& 'Contraste' === $controle_propre['echecs'][0]['explication'] && 11 === $controle_propre['echecs'][0]['n']
	&& '' === $controle_propre['echecs'][1]['gravite'] && '' === $controle_propre['echecs'][1]['origine'] && 0 === $controle_propre['echecs'][1]['n']
	&& $A::PLAFOND === count( $controle_propre['a_verifier'] ) && '4.10.2' === $controle_propre['moteur'],
	'un contrôle reçu se nettoie : score recalculé (conformes / conformes + échecs), textes tamisés, listes plafonnées', $controle_propre );
list( $audit_propre, $audit_refuses ) = $A::nettoyer_l_audit( array( 'taux' => '87,5 %', 'date' => '2026-09-30', 'lien' => 'https://ara.numerique.gouv.fr/rapport/x', 'declaration' => 'https://www.cnrs.fr/fr/accessibilite' ) );
list( $audit_faux, $refuses_faux ) = $A::nettoyer_l_audit( array( 'taux' => '120', 'date' => '2026-02-30', 'lien' => 'javascript:alert(1)', 'declaration' => 'ftp://x.fr/d' ) );
na_verifier( array( 'taux' => '87.5', 'date' => '2026-09-30', 'lien' => 'https://ara.numerique.gouv.fr/rapport/x', 'declaration' => 'https://www.cnrs.fr/fr/accessibilite' ) === $audit_propre && array() === $audit_refuses
	&& array( 'taux', 'date', 'lien', 'declaration' ) === $refuses_faux && array( 'taux' => '', 'date' => '', 'lien' => '', 'declaration' => '' ) === $audit_faux,
	'l\'audit Ara se garde : taux à virgule, date du calendrier, liens http(s) (rapport et déclaration) ; le reste est écarté', array( $audit_propre, $refuses_faux ) );
$option_avant = get_option( $A::OPTION, null );
$utilisateur_avant = get_current_user_id();
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
wp_set_current_user( empty( $admins ) ? 1 : (int) $admins[0] );
$sortie_a11y = function ( $vers ) {
	throw new RuntimeException( (string) $vers );
};
$mourir_a11y = function () {
	return function ( $message ) {
		throw new RuntimeException( 'wp_die' );
	};
};
add_filter( 'wp_redirect', $sortie_a11y );
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', $mourir_a11y );
$_POST = $_REQUEST = array( '_ajax_nonce' => wp_create_nonce( $A::ACTION ), 'resultat' => wp_slash( wp_json_encode( $controle_brut ) ) );
ob_start();
try {
	( new Notice_Archeomed_Accessibilite() )->enregistrer_le_controle();
} catch ( RuntimeException $e ) {
	unset( $e );
}
$reponse_a11y = json_decode( (string) ob_get_clean(), true );
$garde_a11y = $A::lire();
$_POST = $_REQUEST = array( '_ajax_nonce' => 'faux', 'resultat' => '{}' );
ob_start();
$refus_a11y = '';
try {
	( new Notice_Archeomed_Accessibilite() )->enregistrer_le_controle();
} catch ( RuntimeException $e ) {
	$refus_a11y = $e->getMessage();
}
ob_end_clean();
$_POST = $_REQUEST = array( '_wpnonce' => wp_create_nonce( $A::ACTION_ARA ), 'na_ara_taux' => '92', 'na_ara_date' => '2026-09-30', 'na_ara_lien' => 'https://ara.numerique.gouv.fr/r/1' );
$vers_ara = '';
try {
	( new Notice_Archeomed_Accessibilite() )->enregistrer_l_audit();
} catch ( RuntimeException $e ) {
	$vers_ara = $e->getMessage();
}
$_POST = $_REQUEST = array( '_wpnonce' => wp_create_nonce( $A::ACTION_ARA ), 'na_ara_taux' => 'beaucoup', 'na_ara_date' => '', 'na_ara_lien' => 'https://ara.numerique.gouv.fr/r/1' );
$vers_ara2 = '';
try {
	( new Notice_Archeomed_Accessibilite() )->enregistrer_l_audit();
} catch ( RuntimeException $e ) {
	$vers_ara2 = $e->getMessage();
}
$garde_ara = $A::lire();
$_POST = $_REQUEST = array();
remove_filter( 'wp_redirect', $sortie_a11y );
remove_filter( 'wp_doing_ajax', '__return_true' );
remove_filter( 'wp_die_ajax_handler', $mourir_a11y );
ob_start();
$A::onglet();
$rendu_a11y = (string) ob_get_clean();
wp_set_current_user( $utilisateur_avant );
if ( null === $option_avant ) {
	delete_option( $A::OPTION );
} else {
	update_option( $A::OPTION, $option_avant, false );
}
na_verifier( is_array( $reponse_a11y ) && ! empty( $reponse_a11y['success'] ) && 50 === $reponse_a11y['data']['score']
	&& 50 === $garde_a11y['controle']['score'] && '' !== $garde_a11y['controle']['date'] && 'wp_die' === $refus_a11y,
	'le contrôle s\'enregistre par une requête authentifiée, avec sa date ; sans jeton valable, il est refusé', array( $reponse_a11y, $refus_a11y ) );
na_verifier( false !== strpos( $vers_ara, 'tab=accessibilite' ) && false !== strpos( $vers_ara, 'na_ara=ok' ) && false !== strpos( $vers_ara2, 'na_ara=taux' )
	&& '92' === $garde_ara['ara']['taux'] && '' === $garde_ara['ara']['date'] && 'https://ara.numerique.gouv.fr/r/1' === $garde_ara['ara']['lien'],
	'l\'audit Ara s\'enregistre ; un taux faux garde le précédent, un champ vidé s\'efface', array( $vers_ara, $vers_ara2, $garde_ara['ara'] ) );
na_verifier( isset( Notice_Archeomed_Settings::onglets()['accessibilite'] ) && false !== strpos( $rendu_a11y, 'n’est pas le taux de conformité RGAA' )
	&& false !== strpos( $rendu_a11y, 'https://ara.numerique.gouv.fr/' ) && false !== strpos( $rendu_a11y, 'Score indicatif' )
	&& false !== strpos( $rendu_a11y, 'déclaration' ),
	'l\'onglet dit que le score n\'est pas le taux RGAA, renvoie à Ara, rappelle la déclaration, et montre le dernier contrôle' );

WP_CLI::log( 'Le stockage' );
$sto_dossier = trailingslashit( wp_upload_dir()['basedir'] ) . 'notice-archeomed/';
wp_mkdir_p( $sto_dossier );
$sto_passage = trailingslashit( get_temp_dir() ) . 'notice-archeomed-tmp/';
wp_mkdir_p( $sto_passage . 'atelier-essaistock' );
$sto_fichiers = array(
	'original'    => array( $sto_dossier . 'essai-stock-original.tif', 1000 ),
	'apercu'      => array( $sto_dossier . 'essai-stock-apercu.jpg', 200 ),
	'ancien'      => array( $sto_dossier . 'essai-stock-ancien-apercu.jpg', 30 ),
	'conversion'  => array( $sto_dossier . 'essai-stock-conv.jpg', 300 ),
	'autorisation' => array( $sto_dossier . 'essai-stock-autorisation.pdf', 40 ),
	'orphelin'    => array( $sto_dossier . 'essai-stock-orphelin.jpg', 7 ),
	'word'        => array( $sto_passage . 'notice-archeomed-essai-stock.docx', 500 ),
	'atelier'     => array( $sto_passage . 'atelier-essaistock/figure.tif', 60 ),
);
foreach ( $sto_fichiers as $sto_f ) {
	file_put_contents( $sto_f[0], str_repeat( 'x', $sto_f[1] ) );
}
$sto_avant = Notice_Archeomed_Stockage::mesurer();
$sto_id    = na_notice( array( 'commune' => 'Lisieux' ) );
$sto_vieux = na_notice( array( 'commune' => 'Falaise', 'illustrations' => array(
	array( 'rang' => 1, 'figure' => array( 'apercu' => $sto_fichiers['ancien'][0] ) ) ) ) );
update_post_meta( $sto_id, '_na_reference', 'STOCK-Lisieux' );
update_post_meta( $sto_id, '_na_illustrations', array( $sto_fichiers['original'][0] ) );
update_post_meta( $sto_id, '_na_apercus', array( 1 => array( 'apercu' => $sto_fichiers['apercu'][0] ) ) );
update_post_meta( $sto_id, '_na_conversions', array( 1 => array( 'hr' => $sto_fichiers['conversion'][0] ) ) );
update_post_meta( $sto_id, '_na_autorisations', array( 1 => $sto_fichiers['autorisation'][0] ) );
update_post_meta( $sto_id, '_na_document', $sto_fichiers['word'][0] );
update_post_meta( $sto_id, '_na_fichiers', array( $sto_fichiers['word'][0], $sto_fichiers['original'][0] ) );
update_post_meta( $sto_id, '_na_sdrive', array(
	'/ailleurs/parti.tif' => array( 'distant' => 'Numero/originaux/x.tif', 'taille' => 4000, 'le' => time() ),
	'word:v1'             => array( 'distant' => 'Numero/originaux/Lisieux_v1.docx', 'taille' => 900, 'le' => time() ) ) );
$sto = Notice_Archeomed_Stockage::mesurer();
$sto_ecart = function ( $ou, $cat ) use ( $sto, $sto_avant ) {
	return array( $sto[ $ou ][ $cat ]['n'] - $sto_avant[ $ou ][ $cat ]['n'], $sto[ $ou ][ $cat ]['octets'] - $sto_avant[ $ou ][ $cat ]['octets'] );
};
na_verifier( array( 1, 1000 ) === $sto_ecart( 'serveur', 'originaux' ) && array( 2, 230 ) === $sto_ecart( 'serveur', 'apercus' )
	&& array( 1, 300 ) === $sto_ecart( 'serveur', 'conversions' ) && array( 1, 40 ) === $sto_ecart( 'serveur', 'autorisations' )
	&& array( -5, -1570 ) === $sto_ecart( 'serveur', 'orphelins' ) && array( 1, 500 ) === $sto_ecart( 'passage', 'word' )
	&& array( -1, -500 ) === $sto_ecart( 'passage', 'fascicules' ) && $sto['passage']['ateliers']['octets'] >= 60
	&& array( 1, 900 ) === $sto_ecart( 'sdrive', 'word' ) && array( 1, 4000 ) === $sto_ecart( 'sdrive', 'orphelins' )
	&& 2040 === $sto['notices'][ $sto_id ]['serveur'] && 4900 === $sto['notices'][ $sto_id ]['sdrive']
	&& 30 === $sto['notices'][ $sto_vieux ]['serveur'] && $sto['nombre'] - $sto_avant['nombre'] === 2,
	'chaque fichier se compte dans la catégorie de la métadonnée qui le désigne, l\'aperçu ancien dans la saisie, le Word une seule fois ; ce qu\'aucune notice ne désigne, à part ; sDrive d\'après la table',
	array( $sto['serveur'], $sto['passage'], $sto['sdrive'], $sto['notices'] ) );
ob_start();
Notice_Archeomed_Stockage::onglet();
$rendu_stockage = ob_get_clean();
na_verifier( isset( Notice_Archeomed_Settings::onglets()['stockage'] ) && false !== strpos( $rendu_stockage, 'JPEG tirés des TIFF' )
	&& false !== strpos( $rendu_stockage, 'Ateliers d’export du numéro' ) && false !== strpos( $rendu_stockage, 'Sur sDrive' )
	&& false !== strpos( $rendu_stockage, 'Saisies des notices' ) && false !== strpos( $rendu_stockage, 'Lisieux' ),
	'l\'onglet Stockage montre le serveur, le passage, sDrive, la base et les notices les plus lourdes', $rendu_stockage );
foreach ( $sto_fichiers as $sto_f ) {
	@unlink( $sto_f[0] );
}
@rmdir( $sto_passage . 'atelier-essaistock' );
wp_delete_post( $sto_id, true );
wp_delete_post( $sto_vieux, true );

WP_CLI::log( 'L\'annuaire' );
$ann_ids   = array();
$ann_ids[] = na_notice( array( 'commune' => 'Caen', 'resp_nom' => 'Durand', 'resp_prenom' => 'Élodie',
	'resp_email' => 'elodie@annuaire.test', 'resp_inst' => 'Inrap' ) );
$ann_ids[] = na_notice( array( 'commune' => 'Vire', 'resp_nom' => ' Durand ', 'resp_prenom' => 'Élodie',
	'resp_email' => 'ELODIE@annuaire.test', 'resp_inst' => 'CNRS UMR 6273' ) );
$ann_ids[] = na_notice( array( 'commune' => 'Bayeux', 'resp_nom' => 'Aubry', 'resp_prenom' => 'Bernard', 'resp_email' => 'bernard@annuaire.test',
	'coresp_nom' => 'd’Arc', 'coresp_prenom' => 'Jeanne', 'coresp_email' => 'jeanne@annuaire.test', 'coresp_inst' => 'Université de Caen',
	'coauteur_nom' => 'Sans', 'coauteur_prenom' => 'Adresse', 'coauteur_email' => 'pas-une-adresse',
	'deposant_prenom' => 'Chloé', 'deposant_nom' => 'Bon', 'deposant_email' => 'chloe@annuaire.test' ) );
$ann_ids[] = na_notice( array( 'commune' => 'Lisieux', 'resp_nom' => 'Éluard', 'resp_prenom' => "Paul\n\tÉmile",
	'resp_email' => 'paul@annuaire.test', 'resp_inst' => '@lab' ) );
$ann_ids[] = na_notice( array( 'commune' => 'Falaise', 'resp_nom' => 'Zorro', 'resp_prenom' => 'Diego', 'resp_email' => 'zorro@annuaire.test' ) );
update_post_meta( $ann_ids[0], '_na_reference', 'ANN-Caen' );
update_post_meta( $ann_ids[4], '_na_reference', 'ANNREF' );
$ann_ids[] = na_notice( array( 'commune' => 'Falaise' ) );
update_post_meta( $ann_ids[5], '_na_remplace', 'annref' );
$annuaire = array_values( array_filter( Notice_Archeomed_Annuaire::recenser(), function ( $p ) {
	return '@annuaire.test' === strtolower( substr( $p['courriel'], -14 ) );
} ) );
na_verifier( array( 'Aubry', 'Bon', 'd’Arc', 'Durand', 'Éluard' ) === array_column( $annuaire, 'nom' )
	&& 'ELODIE@annuaire.test' === $annuaire[3]['courriel'] && 'CNRS UMR 6273' === $annuaire[3]['institution']
	&& 2 === count( $annuaire[3]['notices'] ) && 'ANN-Caen' === $annuaire[3]['notices'][ $ann_ids[0] ]
	&& array( 'co-responsable' ) === array_keys( $annuaire[2]['roles'] ) && 'Paul Émile' === $annuaire[4]['prenom']
	&& array( 'déposant' ) === array_keys( $annuaire[1]['roles'] ) && '' === $annuaire[1]['institution'],
	'une ligne par adresse, sans égard à la casse, au nom de la notice la plus récente, classée par nom sans égard aux accents ni à l\'apostrophe ; le déposant y est ; ni adresse invalide, ni notice remplacée',
	$annuaire );
na_verifier( array( 'bernard@annuaire.test Bernard Aubry', 'chloe@annuaire.test Chloé Bon', 'jeanne@annuaire.test Jeanne d’Arc', 'ELODIE@annuaire.test Élodie Durand',
	'paul@annuaire.test Paul Émile Éluard' ) === Notice_Archeomed_Annuaire::lignes_sympa( $annuaire ),
	'le fichier pour Sympa porte une ligne par personne : l\'adresse, le prénom, le nom', Notice_Archeomed_Annuaire::lignes_sympa( $annuaire ) );
$ann_tableur = Notice_Archeomed_Annuaire::lignes_du_tableur( $annuaire );
na_verifier( 6 === count( $ann_tableur ) && 'Nom' === $ann_tableur[0][0] && "'@lab" === $ann_tableur[5][3]
	&& 'co-responsable' === $ann_tableur[3][4] && false !== strpos( $ann_tableur[4][5], 'ANN-Caen' ),
	'le tableur porte une ligne par personne, et une cellule qui commence par « @ » ne devient pas une formule', $ann_tableur );
ob_start();
Notice_Archeomed_Annuaire::onglet();
$rendu_annuaire = ob_get_clean();
na_verifier( isset( Notice_Archeomed_Settings::onglets()['annuaire'] ) && false !== strpos( $rendu_annuaire, 'action=na_annuaire_sympa' )
	&& false !== strpos( $rendu_annuaire, 'action=na_annuaire_tableur' ) && false !== strpos( $rendu_annuaire, 'mailto:jeanne@annuaire.test' )
	&& false !== strpos( $rendu_annuaire, '(co-responsable)' ) && false === strpos( $rendu_annuaire, 'zorro@annuaire.test' ),
	'l\'onglet Annuaire montre les personnes, leurs notices, et les deux liens d\'export', $rendu_annuaire );
na_verifier( false !== strpos( $rendu_annuaire, 'name="courriel" value="jeanne@annuaire.test"' )
	&& false !== strpos( $rendu_annuaire, 'value="' . Notice_Archeomed_Annuaire::ACTION_RETIRER . '"' )
	&& 1 === substr_count( $rendu_annuaire, 'name="_wpnonce"' ) && false === strpos( $rendu_annuaire, 'id="_wpnonce"' )
	&& false === strpos( $rendu_annuaire, 'Retirées de l’annuaire' ),
	'chaque ligne de l\'annuaire porte un bouton pour la retirer, sous un seul jeton', $rendu_annuaire );

// Retirer une personne : l'onglet et les deux exports l'écartent, ses
// notices ne changent pas, et une nouvelle notice ne la ramène pas.
WP_CLI::log( 'Retirer une personne de l\'annuaire' );
$ann_utilisateur = get_current_user_id();
wp_set_current_user( 1 );
$ann_sortie = function ( $vers ) {
	throw new RuntimeException( (string) $vers );
};
$ann_mourir = function () {
	return function () {
		throw new RuntimeException( 'wp_die' );
	};
};
add_filter( 'wp_redirect', $ann_sortie );
add_filter( 'wp_die_handler', $ann_mourir );
$ann_jouer = function ( $post ) {
	$_POST = $_REQUEST = $post;
	try {
		( new Notice_Archeomed_Annuaire() )->retirer_ou_retablir();
	} catch ( RuntimeException $e ) {
		return $e->getMessage();
	}
	return '';
};
$ann_jeton  = wp_create_nonce( Notice_Archeomed_Annuaire::ACTION_RETIRER );
$ann_faux   = $ann_jouer( array( '_wpnonce' => 'faux', 'sens' => 'retirer', 'courriel' => 'jeanne@annuaire.test' ) );
na_verifier( 'wp_die' === $ann_faux && array() === Notice_Archeomed_Annuaire::retires(),
	'sans jeton, personne n\'est retiré', $ann_faux );
$ann_vers = $ann_jouer( array( '_wpnonce' => $ann_jeton, 'sens' => 'retirer', 'courriel' => 'JEANNE@annuaire.test',
	'nom' => 'd’Arc', 'prenom' => 'Jeanne' ) );
$ann_apres = array_column( array_filter( Notice_Archeomed_Annuaire::recenser(), function ( $p ) {
	return '@annuaire.test' === strtolower( substr( $p['courriel'], -14 ) );
} ), 'courriel' );
na_verifier( false !== strpos( $ann_vers, 'tab=annuaire' ) && false !== strpos( $ann_vers, 'na_annuaire=retire' )
	&& ! in_array( 'jeanne@annuaire.test', $ann_apres, true ) && in_array( 'bernard@annuaire.test', $ann_apres, true )
	&& 'Jeanne' === Notice_Archeomed_Annuaire::retires()['jeanne@annuaire.test']['prenom']
	&& 'jeanne@annuaire.test' === get_post_meta( $ann_ids[2], '_na_donnees', true )['coresp_email'],
	'une personne retirée sort de l\'annuaire, garde son nom dans la liste des retirées, et sa notice ne change pas',
	array( $ann_vers, $ann_apres, Notice_Archeomed_Annuaire::retires() ) );
$ann_ids[] = na_notice( array( 'commune' => 'Lisieux', 'resp_nom' => 'd’Arc', 'resp_prenom' => 'Jeanne', 'resp_email' => 'Jeanne@Annuaire.test' ) );
$ann_sympa = implode( "\n", Notice_Archeomed_Annuaire::lignes_sympa( Notice_Archeomed_Annuaire::recenser() ) );
$ann_csv   = wp_json_encode( Notice_Archeomed_Annuaire::lignes_du_tableur( Notice_Archeomed_Annuaire::recenser() ) );
$_GET['na_annuaire'] = 'retire';
ob_start();
Notice_Archeomed_Annuaire::onglet();
$rendu_retire = ob_get_clean();
unset( $_GET['na_annuaire'] );
na_verifier( false === stripos( $ann_sympa, 'jeanne@annuaire.test' ) && false === stripos( $ann_csv, 'jeanne@annuaire.test' )
	&& false === stripos( $rendu_retire, 'mailto:jeanne@annuaire.test' ) && false !== strpos( $rendu_retire, 'Retirées de l’annuaire (1)' )
	&& false !== strpos( $rendu_retire, 'name="courriel" value="JEANNE@annuaire.test"' ) && false !== strpos( $rendu_retire, 'value="retablir"' )
	&& false !== strpos( $rendu_retire, 'Ses notices n’ont pas changé' ) && 2 === substr_count( $rendu_retire, 'name="_wpnonce"' ),
	'une adresse retirée ne revient ni dans les exports ni dans l\'onglet, même avec une nouvelle notice, et se rétablit d\'un bouton',
	$rendu_retire );
$ann_vers = $ann_jouer( array( '_wpnonce' => $ann_jeton, 'sens' => 'retablir', 'courriel' => 'JEANNE@annuaire.test' ) );
na_verifier( false !== strpos( $ann_vers, 'na_annuaire=retabli' ) && array() === Notice_Archeomed_Annuaire::retires()
	&& in_array( 'jeanne@annuaire.test', array_map( 'strtolower', array_column( Notice_Archeomed_Annuaire::recenser(), 'courriel' ) ), true ),
	'une adresse rétablie revient dans l\'annuaire', array( $ann_vers, Notice_Archeomed_Annuaire::retires() ) );

// Supprimer définitivement : seulement une adresse retirée ; elle quitte la
// liste des retirées, ne revient pas, et rien d'elle ne reste écrit.
$ann_vers = $ann_jouer( array( '_wpnonce' => $ann_jeton, 'sens' => 'retablir', 'supprimer' => 'jeanne@annuaire.test' ) );
na_verifier( false === strpos( $ann_vers, 'na_annuaire=supprime' ) && array() === Notice_Archeomed_Annuaire::supprimes(),
	'une adresse qui n\'est pas retirée ne se supprime pas', array( $ann_vers, Notice_Archeomed_Annuaire::supprimes() ) );
$ann_jouer( array( '_wpnonce' => $ann_jeton, 'sens' => 'retirer', 'courriel' => 'jeanne@annuaire.test', 'nom' => 'd’Arc', 'prenom' => 'Jeanne' ) );
ob_start();
Notice_Archeomed_Annuaire::onglet();
$rendu_a_supprimer = ob_get_clean();
na_verifier( false !== strpos( $rendu_a_supprimer, 'name="supprimer" value="jeanne@annuaire.test"' )
	&& false !== strpos( $rendu_a_supprimer, 'Supprimer définitivement Jeanne d’Arc' ),
	'une adresse retirée porte un bouton pour la supprimer définitivement', $rendu_a_supprimer );
$ann_vers = $ann_jouer( array( '_wpnonce' => $ann_jeton, 'sens' => 'retablir', 'supprimer' => 'JEANNE@annuaire.test' ) );
$ann_ids[] = na_notice( array( 'commune' => 'Honfleur', 'resp_nom' => 'd’Arc', 'resp_prenom' => 'Jeanne', 'resp_email' => 'jeanne@ANNUAIRE.test' ) );
$_GET['na_annuaire'] = 'supprime';
ob_start();
Notice_Archeomed_Annuaire::onglet();
$rendu_supprime = ob_get_clean();
unset( $_GET['na_annuaire'] );
$ann_reste = wp_json_encode( array( get_option( Notice_Archeomed_Annuaire::OPTION_RETIRES ), get_option( Notice_Archeomed_Annuaire::OPTION_SUPPRIMES ) ) );
na_verifier( false !== strpos( $ann_vers, 'na_annuaire=supprime' ) && array() === Notice_Archeomed_Annuaire::retires()
	&& 1 === count( Notice_Archeomed_Annuaire::supprimes() ) && false === stripos( $ann_reste, 'jeanne' ) && false === stripos( $ann_reste, 'Arc' )
	&& ! in_array( 'jeanne@annuaire.test', array_map( 'strtolower', array_column( Notice_Archeomed_Annuaire::recenser(), 'courriel' ) ), true )
	&& false === stripos( implode( "\n", Notice_Archeomed_Annuaire::lignes_sympa( Notice_Archeomed_Annuaire::recenser() ) ), 'jeanne@' )
	&& false === stripos( $rendu_supprime, 'jeanne@annuaire.test' ) && false === strpos( $rendu_supprime, 'Retirées de l’annuaire' )
	&& false !== strpos( $rendu_supprime, '1 adresse supprimée définitivement' ) && false !== strpos( $rendu_supprime, 'supprimée définitivement : elle ne reviendra plus' )
	&& 'jeanne@annuaire.test' === get_post_meta( $ann_ids[2], '_na_donnees', true )['coresp_email'],
	'une adresse supprimée quitte les retirées, ne revient pas avec une nouvelle notice, ne reste écrite nulle part, et ses notices ne changent pas',
	array( $ann_vers, $ann_reste, $rendu_supprime ) );
remove_filter( 'wp_redirect', $ann_sortie );
remove_filter( 'wp_die_handler', $ann_mourir );
$_POST = $_REQUEST = array();
wp_set_current_user( $ann_utilisateur );
delete_option( Notice_Archeomed_Annuaire::OPTION_RETIRES );
delete_option( Notice_Archeomed_Annuaire::OPTION_SUPPRIMES );
foreach ( $ann_ids as $ann_id ) {
	wp_delete_post( $ann_id, true );
}

WP_CLI::log( 'Les candidats à Pactols' );
$cand_a = na_notice( array( 'commune' => 'Caen', 'pactols_subjects_items' => array(
	array( 'label' => 'Chemin creux', 'ark' => '', 'libre' => true ),
	array( 'label' => 'motte castrale', 'ark' => 'https://ark.frantiq.fr/ark:/26678/pcrtY' ),
	array( 'label' => 'ancien terme', 'ark' => '' ) ) ) );
$cand_b = na_notice( array( 'commune' => 'Vire', 'pactols_subjects_items' => array( array( 'label' => 'chemin  creux', 'ark' => '', 'libre' => true ) ),
	'pactols_places_items' => array( array( 'label' => '=Bocage', 'ark' => '', 'libre' => true ) ) ) );
$cand_remplacee = na_notice( array( 'commune' => 'Bayeux', 'pactols_subjects_items' => array( array( 'label' => 'fossé bordier', 'ark' => '', 'libre' => true ) ) ) );
update_post_meta( $cand_remplacee, '_na_reference', 'CANDREF' );
$cand_correction = na_notice( array( 'commune' => 'Bayeux' ) );
update_post_meta( $cand_correction, '_na_remplace', 'CANDREF' );
update_option( Notice_Archeomed_Candidats::OPTION, array(
	Notice_Archeomed_Candidats::cle( 'pactols_places_items', '=bocage' ) => array( 'statut' => 'ecarte', 'note' => 'trop vague', 'terme' => '=Bocage' ) ) );
$candidats = Notice_Archeomed_Candidats::recenser();
$par_terme = array_column( $candidats, null, 'terme' );
na_verifier( 2 === count( $candidats ) && isset( $par_terme['Chemin creux'], $par_terme['=Bocage'] )
	&& 2 === count( $par_terme['Chemin creux']['notices'] ) && array( 'Chemin creux', 'chemin  creux' ) === array_keys( $par_terme['Chemin creux']['formes'] )
	&& 'Mots-clés' === $par_terme['Chemin creux']['categorie'] && 'a_proposer' === $par_terme['Chemin creux']['statut']
	&& 'ecarte' === $par_terme['=Bocage']['statut'] && 'trop vague' === $par_terme['=Bocage']['note'] && 'Chemin creux' === $candidats[0]['terme'],
	'les termes hors Pactols se relèvent, regroupés malgré la casse et les blancs ; ni terme d\'ARK, ni terme ancien, ni notice remplacée ; le statut gardé se relit',
	$candidats );
$tableur = Notice_Archeomed_Candidats::lignes_du_tableur( $candidats );
na_verifier( 3 === count( $tableur ) && 'Terme' === $tableur[0][0] && "'=Bocage" === $tableur[2][0] && 'Écarté' === $tableur[2][7]
	&& 'chemin  creux' === $tableur[1][1] && '2' === $tableur[1][3],
	'le tableur porte une ligne par terme, et un terme qui commence par « = » ne devient pas une formule', $tableur );
ob_start();
Notice_Archeomed_Candidats::onglet();
$rendu_candidats = ob_get_clean();
na_verifier( isset( Notice_Archeomed_Settings::onglets()['candidats'] ) && false !== strpos( $rendu_candidats, '2' . "\u{00A0}" . 'termes' )
	&& false !== strpos( $rendu_candidats, '<label class="screen-reader-text" for="na-statut-' ) && false !== strpos( $rendu_candidats, 'action=na_candidats_tableur' )
	&& false !== strpos( $rendu_candidats, 'value="trop vague"' ),
	'l\'onglet montre les candidats, leur statut à choisir, leur note, et le lien du tableur', $rendu_candidats );
delete_option( Notice_Archeomed_Candidats::OPTION );
foreach ( array( $cand_a, $cand_b, $cand_remplacee, $cand_correction ) as $cand_id ) {
	wp_delete_post( $cand_id, true );
}

WP_CLI::log( 'Les intertitres du texte et le lieu-dit' );
$propre_titres = na_appel( $plugin, 'clean_richtext', array( '<h1 class="x">Le site</h1><p>Texte.</p><h2>Phase 1</h2><h5>Le mur</h5><h3>Garde</h3><ul><li>un</li></ul>' ) );
na_verifier( '<h3>Le site</h3><p>Texte.</p><h3>Phase 1</h3><h3>Le mur</h3><h3>Garde</h3>' === substr( $propre_titres, 0, strlen( '<h3>Le site</h3><p>Texte.</p><h3>Phase 1</h3><h3>Le mur</h3><h3>Garde</h3>' ) )
	&& '<h3>Le comblement</h3>' === na_appel( $plugin, 'clean_richtext', array( '<h4>Le comblement</h4>' ) ),
	'les titres collés, et le « h4 » d\'une notice ancienne, rejoignent l\'unique niveau d\'intertitre, « h3 »', $propre_titres );
$doc_titres = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
na_appel( $plugin, 'remplir_le_document', array( $doc_titres, array_merge( $saisie_styles, array(
	'texte_notice' => '<p>Le site domine la vallée.</p><h3>La phase 1</h3><p>Un fossé.</p><h4>Le comblement</h4><p>Des tessons.</p><h3>Bilan</h3>' ) ) ) );
$xml_titres = implode( '', $corps->getValue( $doc_titres ) );
$index_titres = new ReflectionProperty( $doc_titres, 'styles' );
$index_titres->setAccessible( true );
$ids_titres    = $index_titres->getValue( $doc_titres );
$styles_titres = array();
foreach ( array( 'heading 3', 'heading 4' ) as $nom_titre ) {
	$styles_titres[ $nom_titre ] = isset( $ids_titres[ $nom_titre ] ) ? $ids_titres[ $nom_titre ] : '';
}
na_verifier( '' !== $styles_titres['heading 3']
	&& 1 === preg_match( '#<w:pStyle w:val="' . $styles_titres['heading 3'] . '"/>.*?La phase 1#s', $xml_titres )
	&& 1 === preg_match( '#<w:pStyle w:val="' . $styles_titres['heading 3'] . '"/>(?:(?!</w:p>).)*Le comblement#s', $xml_titres )
	&& ( '' === $styles_titres['heading 4'] || false === strpos( $xml_titres, '<w:pStyle w:val="' . $styles_titres['heading 4'] . '"/>' ) )
	&& 3 === substr_count( $xml_titres, '<w:pStyle w:val="' . $styles_titres['heading 3'] . '"/>' )
	&& 1 === preg_match( '#Bilan</w:t>.*?</w:p><w:p><w:pPr><w:pStyle w:val="[^"]+"/></w:pPr><w:r><w:t[^>]*>\(</w:t>.*?Responsable#s', $xml_titres ),
	'au Word, les intertitres, même le « h4 » d\'une notice ancienne, prennent le seul style « Titre 3 » ; la responsabilité ne se colle pas à un intertitre final, et son paragraphe ne commence pas par une espace', $styles_titres );
na_verifier( array() === Notice_Archeomed_Controles::avis( array( 'texte_notice' => '<h3>La phase 1</h3><p>Un fossé.</p>' ) + array( 'illustrations' => array() ) )
	|| ! preg_grep( '/La phase 1/u', Notice_Archeomed_Controles::avis( array( 'texte_notice' => '<h3>La phase 1</h3><p>Un fossé.</p>', 'illustrations' => array() ) ) ),
	'un intertitre sans point final ne reçoit pas l\'avis de ponctuation' );
WP_CLI::log( 'La liste à puces du texte' );
$propre_liste = na_appel( $plugin, 'clean_richtext', array( '<p>Trois phases :</p><ol><li data-list="ordered"><span class="ql-ui" contenteditable="false"></span>le fossé</li><li><p>le mur</p><ul><li>sa tranchée</li></ul></li><li>  </li></ol><p>Fin.</p>' ) );
na_verifier( '<p>Trois phases :</p><ul><li>le fossé</li><li>le mur</li><li>sa tranchée</li></ul><p>Fin.</p>' === $propre_liste,
	'une liste numérotée ou emboîtée devient une liste à puces d\'un seul niveau, sans élément vide ni paragraphe dans ses éléments', $propre_liste );
$propre_isole = na_appel( $plugin, 'clean_richtext', array( '<p>Avant.</p><li>orphelin</li><p>Après.</p>' ) );
na_verifier( '<p>Avant.</p><ul><li>orphelin</li></ul><p>Après.</p>' === $propre_isole,
	'un élément de liste égaré reçoit sa liste', $propre_isole );
$blocs_liste = ( new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) ) )->html_to_blocs( $propre_liste );
na_verifier( 5 === count( $blocs_liste )
	&& array( false, true, true, true, false ) === array_column( $blocs_liste, 'liste' ),
	'au Word, chaque élément de liste fait son bloc, marqué comme tel', $blocs_liste );
$blocs_rtf = ( new Notice_Archeomed_RTF( Notice_Archeomed_Pactols::feuille_de_style( 'rtf' ) ) )->html_to_blocs( $propre_liste );
na_verifier( array( false, true, true, true, false ) === array_column( $blocs_rtf, 'liste' ),
	'au RTF de même', $blocs_rtf );
$doc_liste = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
na_appel( $plugin, 'remplir_le_document', array( $doc_liste, array_merge( $saisie_styles, array(
	'texte_notice' => '<p>Trois phases :</p><ul><li>le fossé</li><li>le mur</li></ul>' ) ) ) );
$xml_liste   = implode( '', $corps->getValue( $doc_liste ) );
$index_liste = new ReflectionProperty( $doc_liste, 'styles' );
$index_liste->setAccessible( true );
$ids_liste   = $index_liste->getValue( $doc_liste );
$id_liste    = isset( $ids_liste['List Paragraph'] ) ? $ids_liste['List Paragraph'] : '';
$num_liste   = new ReflectionProperty( $doc_liste, 'num_liste' );
$num_liste->setAccessible( true );
$numero_liste = (int) $num_liste->getValue( $doc_liste );
na_verifier( '' !== $id_liste && $numero_liste > 0
	&& 2 === substr_count( $xml_liste, '<w:pPr><w:pStyle w:val="' . $id_liste . '"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="' . $numero_liste . '"/></w:numPr></w:pPr>' )
	&& 1 === preg_match( '#le mur</w:t>.*?</w:p><w:p><w:pPr><w:pStyle w:val="[^"]+"/></w:pPr><w:r><w:t[^>]*>\(</w:t>.*?Ferrand#si', $xml_liste ),
	'au Word, les éléments de liste prennent le style « Paragraphe de liste » et la puce de Word ; la responsabilité ne se colle pas au dernier élément', array( $id_liste, $numero_liste ) );
$dir_liste  = trailingslashit( get_temp_dir() ) . 'notice-archeomed-liste-' . wp_generate_password( 6, false, false );
$docx_liste = $doc_liste->write_to( $dir_liste, 'liste.docx' );
$numerotation = '';
$document_liste = '';
if ( '' !== $docx_liste && class_exists( 'ZipArchive' ) ) {
	$zip_liste = new ZipArchive();
	if ( true === $zip_liste->open( $docx_liste ) ) {
		$numerotation   = (string) $zip_liste->getFromName( 'word/numbering.xml' );
		$document_liste = (string) $zip_liste->getFromName( 'word/document.xml' );
		$zip_liste->close();
	}
}
$abstrait_liste = new ReflectionProperty( $doc_liste, 'abstract_liste' );
$abstrait_liste->setAccessible( true );
$abstrait = (int) $abstrait_liste->getValue( $doc_liste );
$xml_num  = '' !== $numerotation ? simplexml_load_string( $numerotation ) : false;
na_verifier( false !== $xml_num
	&& 1 === substr_count( $numerotation, '<w:num w:numId="' . $numero_liste . '"><w:abstractNumId w:val="' . $abstrait . '"/></w:num>' )
	&& 1 === substr_count( $numerotation, '<w:abstractNum w:abstractNumId="' . $abstrait . '">' )
	&& false !== strpos( $numerotation, '<w:numFmt w:val="bullet"/>' )
	&& strpos( $numerotation, '<w:abstractNum w:abstractNumId="' . $abstrait . '">' ) < strpos( $numerotation, '<w:num ' )
	&& strrpos( $numerotation, '<w:num w:numId="' . $numero_liste . '">' ) > strrpos( $numerotation, '</w:abstractNum>' )
	&& false !== strpos( $document_liste, '<w:numId w:val="' . $numero_liste . '"/>' ),
	'le fichier Word porte la définition de la puce avant les numéros, et son numéro après ceux du modèle', substr( $numerotation, 0, 300 ) );
$doc_sans = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
na_appel( $plugin, 'remplir_le_document', array( $doc_sans, $saisie_styles ) );
$docx_sans = $doc_sans->write_to( $dir_liste, 'sans.docx' );
$num_sans  = '';
if ( '' !== $docx_sans && class_exists( 'ZipArchive' ) ) {
	$zip_sans = new ZipArchive();
	if ( true === $zip_sans->open( $docx_sans ) ) {
		$num_sans = (string) $zip_sans->getFromName( 'word/numbering.xml' );
		$zip_sans->close();
	}
}
na_verifier( '' !== $num_sans && false === strpos( $num_sans, '<w:num w:numId="' . $numero_liste . '">' ),
	'sans liste, la numérotation du modèle reste telle quelle' );
@unlink( $docx_liste );
@unlink( $docx_sans );
@rmdir( $dir_liste );
$courriel_liste = na_appel( $plugin, 'build_notice', array( array_merge( $saisie_styles, array(
	'texte_notice' => '<p>Trois phases :</p><ul><li>le fossé</li><li>le mur</li></ul>', 'reference' => 'REFLISTE' ) ) ) );
na_verifier( false !== strpos( $courriel_liste, '<ul style="margin:0 0 14px;padding-left:24px;"><li>le fossé</li><li>le mur</li></ul><p style="margin:0;">(' ),
	'au courriel, la liste garde ses puces et la responsabilité prend son paragraphe après elle', $courriel_liste );
na_verifier( ! preg_grep( '/aucune ponctuation/u', Notice_Archeomed_Controles::avis( array( 'texte_notice' => '<p>Trois phases :</p><ul><li>Fosse 12</li><li>Silo 4</li><li>Trou de poteau</li></ul><p>Le reste suit.</p>', 'illustrations' => array() ) ) ),
	'un élément de liste sans point final ne reçoit pas l\'avis de ponctuation' );
$avis_titres = Notice_Archeomed_Controles::intertitres_ponctues( '<h3>La phase 1.</h3><p>Un fossé.</p><h4>Le comblement&nbsp;:</h4><h3>Un enclos ?</h3><h4>Et ensuite...</h4><h3>Bilan</h3>' );
na_verifier( 2 === count( $avis_titres )
	&& false !== strpos( $avis_titres[0], '« La phase 1. » finit par « . »' )
	&& false !== strpos( $avis_titres[1], 'finit par « : »' )
	&& preg_grep( '/La phase 1\./u', Notice_Archeomed_Controles::avis( array( 'texte_notice' => '<h3>La phase 1.</h3><p>Un fossé.</p>', 'illustrations' => array() ) ) ),
	'un intertitre qui finit par un point ou un deux-points reçoit un avis ; « ? » et les points de suspension restent permis', $avis_titres );
$avis_italiques = Notice_Archeomed_Controles::figures( array(
	array( 'rang' => 1, 'titre' => 'Plan', 'legende' => 'Plan de la phase 2 : *bâtiment 1*, silos ; _Le Mans_.', 'credits' => '© Inrap' ),
	array( 'rang' => 2, 'titre' => 'Plan', 'legende' => 'Fichier plan_general_evreux.tif ; * sondage négatif.', 'credits' => '© Inrap' ),
) );
na_verifier( 1 === count( preg_grep( '/italique/u', $avis_italiques ) )
	&& preg_grep( '/« \*bâtiment 1\* ».*« bâtiment 1 » en italique/u', $avis_italiques ),
	'un italique écrit « *ainsi* » dans une légende se signale ; un nom de fichier ou un renvoi « * » non', $avis_italiques );
$avis_abreges = Notice_Archeomed_Controles::intertitres_ponctues( '<h4>Phase 1 (xiie–xiiie s.)</h4><h4>Phase 2 : xive s.</h4><h4>Le ier s. av. J.-C.</h4><h4>Les fossés de la rue.</h4>' );
na_verifier( 1 === count( $avis_abreges ) && false !== strpos( $avis_abreges[0], 'Les fossés de la rue.' ),
	'le point d\'une abréviation (« s. », « J.-C. ») n\'est pas une ponctuation finale d\'intertitre ; celui d\'un mot l\'est', $avis_abreges );
na_verifier( 'La Croix-Blanche' === na_appel( $plugin, 'capitale_initiale', array( 'la Croix-Blanche' ) )
	&& 'Église Saint-Martin' === na_appel( $plugin, 'capitale_initiale', array( 'église Saint-Martin' ) ),
	'le lieu-dit prend une capitale initiale, accents compris' );

WP_CLI::log( 'La documentation' );
$D = 'Notice_Archeomed_Documentation';
$rendu_md = $D::rendre( "# Titre\n\nUn **gras**, un *italique*, du `<code>` et un [lien](https://exemple.org) ; [piège](javascript:alert(1)).\n\n- un\n  - sous-élément\n- deux\n  suite de deux\n\n1. premier\n2. second\n\n| Clé | Sens |\n|---|---|\n| `a` | b |\n\n```\n<script>x</script>\n```\n\n> cité\n\n<script>alert(1)</script>" );
na_verifier( false !== strpos( $rendu_md, '<h2 id="titre">Titre</h2>' ) && false !== strpos( $rendu_md, '<strong>gras</strong>' ) && false !== strpos( $rendu_md, '<em>italique</em>' )
	&& false !== strpos( $rendu_md, '<code>&lt;code&gt;</code>' ) && false !== strpos( $rendu_md, '<a href="https://exemple.org">lien</a>' )
	&& false === strpos( $rendu_md, 'javascript:' ) && false !== strpos( $rendu_md, '<li>un<ul><li>sous-élément</li></ul></li><li>deux suite de deux</li>' )
	&& false !== strpos( $rendu_md, '<ol><li>premier</li><li>second</li></ol>' ) && false !== strpos( $rendu_md, '<th scope="col">Clé</th>' )
	&& false !== strpos( $rendu_md, '<pre><code>&lt;script&gt;x&lt;/script&gt;</code></pre>' ) && false !== strpos( $rendu_md, '<blockquote><p>cité</p></blockquote>' )
	&& false === strpos( $rendu_md, '<script>' ),
	'le Markdown se rend : titres, gras, italique, code, liens sûrs, listes imbriquées, tableaux, citations ; rien n\'y devient du HTML actif', $rendu_md );
$docs_ok = true;
$docs_vus = array();
foreach ( array_keys( $D::DOCUMENTS ) as $cle_doc ) {
	$source_doc = (string) file_get_contents( $D::chemin( $cle_doc ) );
	$rendu_doc  = $D::rendre( $source_doc );
	$texte_doc  = wp_strip_all_tags( $rendu_doc );
	$docs_vus[ $cle_doc ] = strlen( $source_doc );
	// Ni marque de Markdown restée en clair, ni rien qui rattache le texte
	// à une personne ou à un laboratoire.
	if ( '' === $source_doc || preg_match( '/\*\*|^\s*\|?-{3,}\|/m', $texte_doc )
		|| preg_match( '/[\w.+-]+@[\w-]+\.[\w.]+|cnrs|craham|umr\s*\d|unicaen|archeomed\.cnrs/i', $source_doc ) ) {
		$docs_ok = false;
	}
}
ob_start();
$D::onglet();
$rendu_onglet_doc = ob_get_clean();
na_verifier( $docs_ok && isset( Notice_Archeomed_Settings::onglets()['documentation'] )
	&& false !== strpos( $rendu_onglet_doc, 'aria-current="page">Présentation' ) && false !== strpos( $rendu_onglet_doc, '<div class="na-doc"><h2' ),
	'les deux documents se rendent sans marque restée en clair, sans adresse ni rattachement, et l\'onglet les propose', $docs_vus );

WP_CLI::log( 'Le disque' );
delete_transient( 'na_disque' );
$depot_essai = wp_upload_dir();
$dossier_essai = trailingslashit( $depot_essai['basedir'] ) . 'notice-archeomed';
wp_mkdir_p( $dossier_essai );
file_put_contents( $dossier_essai . '/essai-disque.bin', str_repeat( 'x', 3000 ) );
$mesure_disque = Notice_Archeomed_Settings::mesurer_le_disque();
na_verifier( $mesure_disque['complet'] && $mesure_disque['notices'] >= 3000 && $mesure_disque['site'] >= $mesure_disque['notices'] && $mesure_disque['fichiers'] >= 1,
	'le disque compte les fichiers du dossier des notices, comprises dans les téléversements du site' );
$etat_disque = array();
foreach ( Notice_Archeomed_Settings::etat_du_serveur() as $l_disque ) {
	$etat_disque[ $l_disque['quoi'] ] = $l_disque;
}
na_verifier( isset( $etat_disque['Disque : dossier des notices'], $etat_disque['Disque : téléversements du site'] )
	&& 'ok' === $etat_disque['Disque : téléversements du site']['etat'],
	'le tableau de l\'hébergement porte les deux lignes du disque, et un site presque vide n\'est pas en alerte' );
add_filter( 'notice_archeomed_plafond_disque', function () { return 1000; } );
$plein = Notice_Archeomed_Settings::etat_du_serveur();
$plein_etat = '';
foreach ( $plein as $l_disque ) {
	if ( 'Disque : téléversements du site' === $l_disque['quoi'] ) {
		$plein_etat = $l_disque['etat'];
	}
}
na_verifier( 'manque' === $plein_etat, 'au-delà de 85 % du plafond, le disque est signalé comme manquant' );
remove_all_filters( 'notice_archeomed_plafond_disque' );
@unlink( $dossier_essai . '/essai-disque.bin' );
delete_transient( 'na_disque' );

WP_CLI::log( 'Les figures envoyées à part' );
na_verifier( Notice_Archeomed_Pactols::est_a_part( '/tmp/notice-archeomed-a-part-abcdef0123456789abcdef0123456789.txt' )
	&& Notice_Archeomed_Pactols::est_a_part( '/x/12-notice-archeomed-garde-a-part-ABCDEFGH12.txt' )
	&& ! Notice_Archeomed_Pactols::est_a_part( '/x/figure-a-part.jpg' )
	&& ! Notice_Archeomed_Pactols::est_a_part( '/x/plan-a-part-court.txt' )
	&& ! Notice_Archeomed_Pactols::est_a_part( null ),
	'le fichier témoin se reconnaît à son nom, et seulement lui' );
// Le champ de fichiers ne porte que les vrais fichiers : la figure à part
// doit retrouver sa place entre eux, sans décaler les suivants.
$_POST['illus_source']    = array( 'fichier', 'a_part', 'fichier' );
$_POST['illus_lien']      = array( '', 'https://filesender.renater.fr/?s=download&token=abc', '' );
$_POST['illus_jusqua']    = array( '', '2030-01-02', '' );
$_POST['illus_nom_envoi'] = array( '', 'coupe.tif', '' );
$_FILES = array( 'illustrations' => array(
	'name'     => array( 'plan.jpg', 'vue.jpg' ),
	'type'     => array( 'image/jpeg', 'image/jpeg' ),
	'tmp_name' => array( '/tmp/php-a', '/tmp/php-b' ),
	'error'    => array( UPLOAD_ERR_OK, UPLOAD_ERR_OK ),
	'size'     => array( 10, 20 ),
) );
na_appel( $plugin, 'ranger_les_figures_a_part' );
$prop_a_part = new ReflectionProperty( $plugin, 'figures_a_part' );
$prop_a_part->setAccessible( true );
$rangees = $prop_a_part->getValue( $plugin );
na_verifier( array( 'plan.jpg', '', 'vue.jpg' ) === $_FILES['illustrations']['name']
	&& UPLOAD_ERR_NO_FILE === $_FILES['illustrations']['error'][1] && 20 === $_FILES['illustrations']['size'][2]
	&& array( 1 ) === array_keys( $rangees ) && '2030-01-02' === $rangees[1]['jusqua'] && 'coupe.tif' === $rangees[1]['fichier']
	&& 0 === strpos( $rangees[1]['lien'], 'https://filesender.renater.fr/' ),
	'la figure envoyée à part garde son rang entre deux fichiers joints', array( $_FILES, $rangees ) );
na_verifier( 3 === na_appel( $plugin, 'fichiers_deposes' ), 'deux fichiers et une figure à part font trois figures déposées' );
$_POST['illus_source'] = array( 'fichier', 'fichier' );
na_appel( $plugin, 'ranger_les_figures_a_part' );
na_verifier( array() === $prop_a_part->getValue( $plugin ) && array( 'plan.jpg', '', 'vue.jpg' ) === $_FILES['illustrations']['name'],
	'sans figure à part, on ne touche pas aux fichiers' );
$_FILES = array();
$prop_a_part->setValue( $plugin, array() );
unset( $_POST['illus_source'], $_POST['illus_lien'], $_POST['illus_jusqua'], $_POST['illus_nom_envoi'] );

$d_a_part = array(
	'commune'        => 'Caen',
	'reference'      => 'AM-2026-0001',
	'originaux_lien' => 'https://partage.exemple.fr/ancien',
	'illustrations'  => array(
		array( 'rang' => 1, 'titre' => 'Coupe', 'a_part' => array( 'lien' => 'https://filesender.renater.fr/x', 'jusqua' => '2030-02-01', 'fichier' => 'coupe.tif' ) ),
		array( 'rang' => 2, 'titre' => 'Plan' ),
		array( 'rang' => 3, 'titre' => 'Vue', 'a_part' => array( 'lien' => 'https://filesender.renater.fr/x', 'jusqua' => '2030-01-15', 'fichier' => 'vue.tif' ) ),
	),
);
$transferts = na_appel( 'Notice_Archeomed_Pactols', 'transferts_de', array( $d_a_part ) );
na_verifier( 2 === count( $transferts ) && 'https://filesender.renater.fr/x' === $transferts[0]['lien']
	&& '2030-01-15' === $transferts[0]['jusqua'] && 2 === count( $transferts[0]['figures'] )
	&& false !== strpos( $transferts[0]['figures'][1], 'vue.tif' )
	&& 'https://partage.exemple.fr/ancien' === $transferts[1]['lien'] && array() === $transferts[1]['figures'],
	'deux figures d\'un même envoi font un seul lien, à la date la plus proche ; l\'ancien lien unique vient après', $transferts );

$doc_a_part = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
na_appel( $plugin, 'remplir_le_document', array( $doc_a_part, array_merge( $saisie_styles, array( 'illustrations' => array(
	array( 'rang' => 1, 'titre' => 'Coupe', 'legende' => '', 'credits' => '', 'a_part' => $d_a_part['illustrations'][0]['a_part'] ) ) ) ) ) );
$xml_a_part = implode( '', $corps->getValue( $doc_a_part ) );
na_verifier( false !== strpos( $xml_a_part, '<w:pBdr>' ) && false !== strpos( $xml_a_part, 'w:color="C00000"' )
	&& false !== strpos( $xml_a_part, 'Figure envoyée à part' ) && false !== strpos( $xml_a_part, 'coupe.tif' )
	&& false !== strpos( $xml_a_part, 'filesender.renater.fr/x' ),
	'le Word pose un cadre rouge, avec le fichier et le lien, à la place de l\'aperçu', $xml_a_part );

$paquet = new Notice_Archeomed_Paquet();
$erreur = '';
$paquet->preparer( 'I. Constructions et habitats civils', array( array(
	'id'            => 0,
	'd'             => array_merge( $d_a_part, array( 'lieu_dit' => 'Château', 'annee' => '2026' ) ),
	'illustrations' => array( '/tmp/notice-archeomed-a-part-abcdef0123456789abcdef0123456789.txt' ),
) ), $erreur );
$texte = na_appel( $paquet, 'lisez_moi' );
na_verifier( false !== strpos( $texte, 'envoyée à part par l’auteur, à reprendre' ) && false !== strpos( $texte, 'coupe.tif' )
	&& false !== strpos( $texte, 'AM-2026-0001' ) && false === strpos( $texte, 'introuvable sur le serveur' ),
	'le lisez-moi donne la figure à reprendre, avec de quoi la retrouver', $texte );
$paquet->nettoyer();

WP_CLI::log( 'La relecture par les coordinateurs' );
// Trois notices dans la rubrique I, une dans la II ; un coordinateur par
// rubrique, et la direction. Tout passe par les fonctions que l'écran
// appelle : le droit se juge sur la notice, jamais sur la requête.
require_once ABSPATH . 'wp-admin/includes/user.php';
$R = 'Notice_Archeomed_Relecture';
$R::assurer_les_roles();
$file_rel = $GLOBALS['notice_archeomed_file'];
$rubs_rel = $plugin->rubriques();
$compte_rel = function ( $identifiant, $nom, $role ) {
	return (int) wp_insert_user( array( 'user_login' => $identifiant, 'user_pass' => wp_generate_password( 24 ),
		'user_email' => $identifiant . '@example.org', 'display_name' => $nom, 'role' => $role ) );
};
$coord_1 = $compte_rel( 'coord-un', 'Jeanne Martin', $R::ROLE );
$coord_2 = $compte_rel( 'coord-deux', 'Paul Durand', $R::ROLE );
// L'ancien rôle de la rédaction passe à la direction : son compte d'abord.
add_role( $R::ROLE_REDACTION, 'Rédaction (Chronique)', array( 'read' => true, $R::CAP => true, $R::CAP_TOUT => true ) );
$redac = $compte_rel( 'redaction-essai', 'Direction Essai', $R::ROLE_REDACTION );
$R::assurer_les_roles();
$migre = new WP_User( $redac );
na_verifier( in_array( $R::ROLE_DIRECTION, $migre->roles, true ) && ! in_array( $R::ROLE_REDACTION, $migre->roles, true )
	&& null === get_role( $R::ROLE_REDACTION ) && Notice_Archeomed_Settings::peut_regler( $migre )
	&& $R::est_direction_seule( $migre ) && ! user_can( $migre, 'manage_options' ) && ! get_role( 'administrator' )->has_cap( Notice_Archeomed_Settings::CAPACITE ),
	'un compte de l\'ancienne rédaction passe à la direction : il relit tout et règle le plugin, sans administrer le site', $migre->roles );
$admin_cf = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
na_verifier( ! empty( $admin_cf ) && user_can( (int) $admin_cf[0], Notice_Archeomed_Settings::CAPACITE ) && ! $R::est_direction_seule( (int) $admin_cf[0] )
	&& user_can( $migre, get_post_type_object( Notice_Archeomed_File::CPT )->cap->edit_posts )
	&& ! user_can( $coord_1, get_post_type_object( Notice_Archeomed_File::CPT )->cap->edit_posts ),
	'la direction a le menu des notices reçues, comme l\'administrateur, que son rôle n\'ait pas à changer ; un coordinateur ne l\'a pas' );
$ark_four = 'https://ark.frantiq.fr/ark:/26678/pcrtFourEssai';
$notice_rel = function ( $ajouts ) use ( $saisie_styles, $file_rel, $rubs_rel ) {
	$d = array_merge( $saisie_styles, array(
		'rubrique_principale' => $rubs_rel[0], 'nature' => 'Fouille programmée',
		'nature_items' => array( array( 'label' => 'Fouille programmée', 'ark' => 'https://ark.frantiq.fr/ark:/26678/crtSrWQs2w2KV', 'idConcept' => '' ) ),
		'texte_notice' => '<p>Le texte de départ, tel que l’auteur l’a déposé.</p>', 'pactols_places_items' => array(),
	), $ajouts );
	$d['lieux'] = array( array( 'nom' => $d['commune'], 'ark' => '' ) );
	$id = na_notice( $d );
	$file_rel->mettre_a_jour( $id, $d, '' );
	update_post_meta( $id, '_na_etat', 'envoyee' );
	update_post_meta( $id, '_na_version', '1' );
	update_post_meta( $id, '_na_reference', 'REL' . $id );
	return $id;
};
$rel_a = $notice_rel( array( 'commune' => 'Vire', 'illustrations' => array(
	array( 'rang' => 1, 'titre' => 'Plan du four', 'legende' => '', 'credits' => 'A. Ferrand', 'alt' => 'Plan du four.', 'description' => '', 'type' => 'plan' ),
	array( 'rang' => 2, 'titre' => 'Vue zénithale de la tranchée', 'legende' => '', 'credits' => 'A. Ferrand', 'alt' => 'Vue de la tranchée.', 'description' => '', 'type' => 'photo' ),
) ) );
$rel_b = $notice_rel( array( 'commune' => 'Bayeux', 'nature' => 'Prospection pédestre',
	'nature_items' => array( array( 'label' => 'Prospection pédestre', 'ark' => 'https://ark.frantiq.fr/ark:/26678/crtVqcSVS0rm7', 'idConcept' => '' ) ) ) );
$rel_c = $notice_rel( array( 'commune' => 'Argentan' ) );
$rel_d = $notice_rel( array( 'commune' => 'Lisieux', 'rubrique_principale' => $rubs_rel[1] ) );
$lire_rel = function ( $id ) {
	wp_cache_delete( $id, 'post_meta' );
	return get_post_meta( $id, '_na_donnees', true );
};
$xml_du_docx = function ( $chemin ) {
	$zip = new ZipArchive();
	if ( '' === (string) $chemin || true !== $zip->open( $chemin ) ) {
		return '';
	}
	$xml = (string) $zip->getFromName( 'word/document.xml' );
	$zip->close();
	return $xml;
};

// Avant la clôture, personne ne relit, et le lien de l'auteur corrige.
$R::regler( false, array( 0 => array( $coord_1 ), 1 => array( $coord_2 ) ) );
$garde_rel = array( 'corrige_id' => $rel_a, 'vivant' => true, 'par_la_redaction' => 0 );
na_verifier( null === $R::droit_sur( $rel_a, $coord_1 ) && null === $R::droit_sur( $rel_a, $redac ),
	'avant la clôture de la campagne, la relecture n\'est ouverte à personne' );
na_verifier( $rel_a === na_appel( $plugin, 'contexte_valide', array( $garde_rel ) ),
	'avant la clôture, le lien de correction de l\'auteur ouvre sa notice' );
$admins_rel = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$admin_rel  = (int) $admins_rel[0];
$garde_redaction = array( 'corrige_id' => $rel_a, 'vivant' => true, 'par_la_redaction' => $admin_rel );
wp_set_current_user( $admin_rel );
$redaction_avant = na_appel( $plugin, 'contexte_valide', array( $garde_redaction ) );
$R::regler( true, array( 0 => array( $coord_1, 999999 ), 1 => array( $coord_2 ), 9 => array( $coord_1 ) ) );
$reglages_rel = $R::reglages();
na_verifier( $R::campagne_close() && array( $coord_1 ) === $reglages_rel['coordinateurs'][ $rubs_rel[0] ] && 2 === count( $reglages_rel['coordinateurs'] ),
	'les réglages ne retiennent que des comptes qui peuvent relire, pour des rubriques qui existent', $reglages_rel );
na_verifier( 0 === na_appel( $plugin, 'contexte_valide', array( $garde_rel ) ),
	'la campagne close, le lien de correction de l\'auteur ne rouvre plus sa notice' );
$redaction_apres = na_appel( $plugin, 'contexte_valide', array( $garde_redaction ) );
wp_set_current_user( $coord_1 );
$redaction_autre = na_appel( $plugin, 'contexte_valide', array( $garde_redaction ) );
wp_set_current_user( 0 );
na_verifier( $rel_a === $redaction_avant && $rel_a === $redaction_apres && 0 === $redaction_autre
	&& false !== strpos( Notice_Archeomed_File::lien_pour_corriger( $rel_a ), 'na_corriger=1' ),
	'le lien « modifier » du courriel reçu par la revue ouvre la notice avant comme après la clôture, pour la direction seule',
	array( $redaction_avant, $redaction_apres, $redaction_autre ) );
set_transient( 'na_reprise_jetonclos1', $garde_rel, HOUR_IN_SECONDS );
$reprise_rel = new ReflectionProperty( $plugin, 'reprise' );
$reprise_rel->setAccessible( true );
$reprise_rel->setValue( $plugin, null );
$_GET['notice_reprise'] = 'jetonclos1';
$lien_clos = na_appel( $plugin, 'etat_du_lien_de_reprise' );
unset( $_GET['notice_reprise'] );
$reprise_rel->setValue( $plugin, null );
delete_transient( 'na_reprise_jetonclos1' );
na_verifier( 'close' === $lien_clos['etat'], 'qui suit ce lien lit que la campagne est close, non que le lien a expiré', $lien_clos );

// Le cloisonnement par rubrique.
$droit_1 = $R::droit_sur( $rel_a, $coord_1 );
$droit_r = $R::droit_sur( $rel_a, $redac );
na_verifier( is_array( $droit_1 ) && 'principale' === $droit_1['sorte'] && null === $R::droit_sur( $rel_a, $coord_2 )
	&& null === $R::droit_sur( $rel_d, $coord_1 ) && is_array( $droit_r ) && 'tout' === $droit_r['sorte'],
	'un coordinateur ne peut rien d\'une notice hors de sa rubrique ; la direction les relit toutes' );
na_verifier( array( $rubs_rel[0] ) === $R::rubriques_de( $coord_1 ) && $rubs_rel === $R::rubriques_de( $redac ) && array() === $R::rubriques_de( 0 ),
	'chacun a ses rubriques : celle qu\'on lui confie, toutes pour la direction, aucune sans compte' );
$_POST = wp_slash( array( 'texte_notice' => '<p>Texte forgé.</p>', 'remarque' => 'Remarque forgée.' ) );
$forge = $R::enregistrer( $rel_a, 1, $coord_2 );
$d_forge = $lire_rel( $rel_a );
na_verifier( 'interdit' === $forge['issue'] && false === strpos( $d_forge['texte_notice'], 'forgé' ) && array() === $R::journal_de( $rel_a )
	&& 1 === $file_rel->version_de( $rel_a ),
	'un identifiant forgé dans la requête n\'écrit rien dans la notice d\'une autre rubrique, pas même une remarque', $forge );

// L'ordre du fascicule.
$sommaire_rel = $R::sommaire( $rubs_rel[0] );
$ordre_rel    = array_values( array_intersect( array_map( 'intval', wp_list_pluck( $sommaire_rel['notices'], 'id' ) ), array( $rel_a, $rel_b, $rel_c ) ) );
$erreur_rel   = '';
$fascicule_rel = $xml_du_docx( na_appel( $plugin, 'fabriquer_le_fascicule', array( $rubs_rel[0],
	na_appel( $plugin, 'donnees_des_notices', array( $plugin->ids_de_la_rubrique( $rubs_rel[0] ) ) ), &$erreur_rel ) ) );
$pos_rel = array( strpos( $fascicule_rel, 'Argentan' ), strpos( $fascicule_rel, 'Vire' ), strpos( $fascicule_rel, 'Bayeux' ) );
na_verifier( array( $rel_c, $rel_a, $rel_b ) === $ordre_rel && false !== $pos_rel[0] && $pos_rel[0] < $pos_rel[1] && $pos_rel[1] < $pos_rel[2],
	'le sommaire suit l\'ordre du fascicule : la sous-rubrique, puis la commune', array( $ordre_rel, $pos_rel ) );
na_verifier( ! in_array( $rel_d, array_map( 'intval', wp_list_pluck( $sommaire_rel['notices'], 'id' ) ), true ),
	'le sommaire d\'une rubrique ne porte pas les notices d\'une autre' );

// Le verrou.
$verrou_1 = $R::prendre_le_verrou( $rel_a, $coord_1 );
$verrou_2 = $R::prendre_le_verrou( $rel_a, $redac );
$verrou_3 = $R::prendre_le_verrou( $rel_a, $coord_1 );
$_POST    = wp_slash( array( 'texte_notice' => '<p>La direction passe outre.</p>' ) );
$refus_verrou = $R::enregistrer( $rel_a, 1, $redac );
na_verifier( $verrou_1['tenu'] && ! $verrou_2['tenu'] && $coord_1 === $verrou_2['par'] && $verrou_3['tenu']
	&& 'verrouillee' === $refus_verrou['issue'] && 'Jeanne Martin' === $refus_verrou['par'] && 1 === $file_rel->version_de( $rel_a ),
	'une notice ouverte est verrouillée pour les autres, au nom de qui l\'a ouverte, et ne s\'enregistre pas sous son verrou', array( $verrou_2, $refus_verrou ) );
update_post_meta( $rel_a, '_na_relecture_verrou', ( time() - $R::DUREE_DU_VERROU - 5 ) . ':' . $coord_1 );
$verrou_4 = $R::prendre_le_verrou( $rel_a, $redac );
$R::liberer_le_verrou( $rel_a, $redac );
$verrou_5 = $R::verrou_de( $rel_a );
na_verifier( $verrou_4['tenu'] && 0 === $verrou_5['par'], 'un verrou sans nouvelles expire seul, et un verrou rendu est libre' );

// Retirer une figure demande un motif.
$_POST = wp_slash( array( 'garde_action' => array( 2 => 'retirer' ) ) );
$sans_motif = $R::enregistrer( $rel_a, 1, $coord_1 );
$_POST = wp_slash( array( 'garde_action' => array( 2 => 'retirer' ), 'garde_motif' => array( 2 => 'autre' ), 'garde_precision' => array( 2 => '  ' ) ) );
$sans_precision = $R::enregistrer( $rel_a, 1, $coord_1 );
na_verifier( 'motif' === $sans_motif['issue'] && 'motif' === $sans_precision['issue'] && 1 === $file_rel->version_de( $rel_a ),
	'une figure ne se retire pas sans motif, ni pour « autre » sans précision', array( $sans_motif, $sans_precision ) );

// L'enregistrement d'un coordinateur.
$_POST = wp_slash( array(
	'texte_notice'        => '<p>Le texte de départ, tel que le coordinateur l’a relu (fig. 2).</p>',
	'resp_nom'            => 'Ferrand-Dupont',
	'resp_email'          => 'pirate@example.org',
	'coresp_email'        => 'autre@example.org',
	'rubrique_principale' => $rubs_rel[1],
	'pactols_subjects'    => wp_json_encode( array(
		array( 'label' => 'four de potier', 'ark' => $ark_four, 'idConcept' => '12', 'fullpath' => '' ),
		array( 'label' => 'terme inventé', 'ark' => '' ),
	) ),
	'illus_titre'         => array( 'Figure neuve' ),
	'illus_alt'           => array( 'Figure neuve.' ),
	'garde_titre'         => array( 1 => 'Plan du four, état 2', 3 => 'Troisième figure' ),
	'garde_action'        => array( 1 => 'remplacer', 2 => 'retirer' ),
	'garde_motif'         => array( 2 => 'redondante' ),
	'relue'               => '1',
	'remarque'            => 'La figure 2 double la première.',
) );
$_FILES = array( 'illustrations' => array( 'name' => array( 'neuve.jpg' ), 'type' => array( 'image/jpeg' ), 'tmp_name' => array( '/tmp/neuve.jpg' ), 'error' => array( 0 ), 'size' => array( 10 ) ) );
$relu   = $R::enregistrer( $rel_a, 1, $coord_1 );
$_FILES = array();
$d_relu = $lire_rel( $rel_a );
na_verifier( 'enregistree' === $relu['issue'] && 2 === $relu['version'] && 2 === $file_rel->version_de( $rel_a )
	&& false !== strpos( $d_relu['texte_notice'], 'coordinateur' ) && 'Ferrand-Dupont' === $d_relu['resp_nom'],
	'le texte et les champs se corrigent, et la notice passe à la version suivante', $relu );
na_verifier( 'a@example.org' === $d_relu['resp_email'] && '' === $d_relu['coresp_email'],
	'le courriel des responsables ne se modifie pas, quoi que poste la requête', array( $d_relu['resp_email'], $d_relu['coresp_email'] ) );
na_verifier( $rubs_rel[0] === $d_relu['rubrique_principale'], 'un coordinateur ne change pas la rubrique principale', $d_relu['rubrique_principale'] );
$sujets_relus = wp_list_pluck( $d_relu['pactols_subjects_items'], 'label' );
na_verifier( array( 'four de potier' ) === $sujets_relus && array( 'église' ) === $relu['faits']['termes_retires'],
	'un mot-clé se retire, un autre s\'ajoute par le thésaurus ; un terme neuf sans identifiant n\'entre pas', $sujets_relus );
$masque_relu = Notice_Archeomed_Pactols::figure_masquee( $d_relu['illustrations'][1] );
na_verifier( 2 === count( $d_relu['illustrations'] ) && 'Plan du four, état 2' === $d_relu['illustrations'][0]['titre']
	&& null === Notice_Archeomed_Pactols::figure_masquee( $d_relu['illustrations'][0] ),
	'aucune illustration ne s\'ajoute ni ne se remplace : les deux figures restent deux, la première corrigée', $d_relu['illustrations'] );
na_verifier( is_array( $masque_relu ) && 'redondante' === $masque_relu['motif'] && 'Jeanne Martin' === $masque_relu['par']
	&& 2 === $d_relu['illustrations'][1]['rang'] && 'Vue zénithale de la tranchée' === $d_relu['illustrations'][1]['titre'],
	'une illustration retirée est masquée, avec son motif : elle garde son rang, ses textes et ses fichiers', $masque_relu );
$rappel_rel = $plugin->appels_a_verifier( $rel_a );
na_verifier( false !== strpos( implode( ' | ', $rappel_rel ), 'fig. 2' ),
	'après le retrait, le contrôle des appels dit que le texte appelle une figure qui n\'est plus là', $rappel_rel );

// Les versions, les états et le journal.
$versions_rel = $R::versions_gardees( $rel_a );
na_verifier( array( 1, 2 ) === array_keys( $versions_rel ) && 'Jeanne Martin' === $versions_rel[2]['par'] && '' !== $versions_rel[2]['le']
	&& false !== strpos( $versions_rel[1]['donnees']['texte_notice'], 'l’auteur l’a déposé' )
	&& 2 === count( $versions_rel[1]['donnees']['pactols_subjects_items'] ) + count( $versions_rel[2]['donnees']['pactols_subjects_items'] ),
	'chaque enregistrement garde la version précédente, avec la date et l\'auteur de la suivante', array_keys( $versions_rel ) );
$journal_rel = $R::journal_de( $rel_a );
$dit_rel     = implode( ' | ', $journal_rel[0]['quoi'] );
na_verifier( 1 === count( $journal_rel ) && 'Jeanne Martin' === $journal_rel[0]['par'] && $coord_1 === $journal_rel[0]['par_id']
	&& false !== strpos( $dit_rel, 'figure 2 retirée' ) && false !== strpos( $dit_rel, 'Redondante' ) && false !== strpos( $dit_rel, 'termes retirés : église' )
	&& false !== strpos( $dit_rel, 'À relire → Relue par le coordinateur' ) && 'La figure 2 double la première.' === $journal_rel[0]['remarque'],
	'le journal dit qui, quand, quoi : la figure et le mot-clé retirés, le motif, l\'état, la remarque à la direction', $journal_rel );
na_verifier( 'relue' === $R::etat_de( $rel_a ) && ! isset( $d_relu['remarque'] ) && false === strpos( wp_json_encode( $d_relu ), 'double la première' ),
	'la notice est marquée relue, et la remarque à la direction reste hors de la saisie publiée' );
na_verifier( array( 2 ) === array_map( 'intval', (array) get_post_meta( $rel_a, '_na_relecture_a_deposer', false ) ),
	'le Word de relecture attend sDrive hors de la requête : sans sDrive, la correction est enregistrée quand même' );
$R::en_tache();
na_verifier( array( 2 ) === array_map( 'intval', (array) get_post_meta( $rel_a, '_na_relecture_a_deposer', false ) ) && 2 === $file_rel->version_de( $rel_a ),
	'un passage de la tâche sans sDrive ne perd ni l\'attente ni la notice' );
$_POST = wp_slash( array( 'etat' => 'validee', 'garde_action' => array( 2 => 'retablir' ) ) );
$coord_valide = $R::enregistrer( $rel_a, 2, $coord_1 );
$d_coord      = $lire_rel( $rel_a );
na_verifier( 'relue' === $R::etat_de( $rel_a ) && null !== Notice_Archeomed_Pactols::figure_masquee( $d_coord['illustrations'][1] ) && 'inchangee' === $coord_valide['issue'],
	'le coordinateur ne valide pas une notice et ne rétablit pas une figure : ce sont les gestes de la direction', $coord_valide );
$_POST = wp_slash( array( 'fond' => '1' ) );
$R::enregistrer( $rel_a, 2, $coord_1 );
na_verifier( 'a_soumettre' === $R::etat_de( $rel_a ), 'la case « modification de fond » fait passer la notice à « à soumettre à l\'auteur »', $R::etat_de( $rel_a ) );

// Le Word de relecture, et le fascicule.
$erreur_word = '';
$word_rel    = $plugin->word_de_relecture( $rel_a, 2, $erreur_word );
$xml_word    = $xml_du_docx( $word_rel );
if ( '' !== $word_rel ) {
	@unlink( $word_rel );
}
na_verifier( '' !== $xml_word && false !== strpos( $xml_word, '<w:ins ' ) && false !== strpos( $xml_word, '<w:del ' ) && false !== strpos( $xml_word, '<w:delText' )
	&& false !== strpos( $xml_word, 'w:author="Jeanne Martin"' ) && false !== simplexml_load_string( $xml_word ),
	'le Word de relecture porte des « w:ins » et des « w:del » signés du relecteur, dans un XML valide', '' !== $xml_word ? substr( $xml_word, 0, 600 ) : $erreur_word );
na_verifier( 1 === preg_match( '#<w:ins [^>]*>(?:(?!</w:ins>).)*coordinateur#s', $xml_word ) && 1 === preg_match( '#<w:delText[^>]*>[^<]*auteur#', $xml_word )
	&& 1 === preg_match( '#<w:delText[^>]*>[^<]*zénithale#', $xml_word ) && 1 === preg_match( '#<w:delText[^>]*>[^<]*église#', $xml_word )
	&& false === strpos( $xml_word, '<w:drawing' ) && false === strpos( $xml_word, 'r:embed' ),
	'le suivi montre le mot ajouté, le mot ôté, le mot-clé et la figure retirés ; aucune image n\'y est incorporée' );
$word_coord = $plugin->word_de_relecture( $rel_a, 2, $erreur_word, 0, true );
$xml_coord  = $xml_du_docx( $word_coord );
if ( '' !== $word_coord ) {
	@unlink( $word_coord );
}
na_verifier( '' !== $xml_coord && false === strpos( $xml_coord, 'a@example.org' ) && false !== strpos( $xml_word, 'a@example.org' ),
	'le Word rendu à un coordinateur ne porte pas le courriel des responsables ; celui de la direction, si' );
na_verifier( 'notice_v2_relecture_Jeanne_Martin.docx' === $R::nom_du_word( 0, 2, 'Jeanne Martin' ), 'le Word de relecture porte la version et le nom du relecteur', $R::nom_du_word( 0, 2, 'Jeanne Martin' ) );
na_verifier( 'Saint_Lo_Le_Goff' === Notice_Archeomed_Nommage::tige_du_word( array( 'commune' => 'Saint-Lô', 'resp_nom' => 'Le Goff' ) )
	&& 'Vire' === Notice_Archeomed_Nommage::tige_du_word( array( 'commune' => 'Vire', 'resp_nom' => '  ' ) )
	&& 'notice' === Notice_Archeomed_Nommage::tige_du_word( '' )
	&& 0 === strpos( $R::nom_du_word( $rel_a, 2, 'Jeanne Martin' ), 'Vire_' ) && '_v2_relecture_Jeanne_Martin.docx' === substr( $R::nom_du_word( $rel_a, 2, 'Jeanne Martin' ), -32 ),
	'le Word porte la commune et le nom du responsable d’opération, « notice » à défaut', $R::nom_du_word( $rel_a, 2, 'Jeanne Martin' ) );
$fascicule_relu = $xml_du_docx( na_appel( $plugin, 'fabriquer_le_fascicule', array( $rubs_rel[0],
	na_appel( $plugin, 'donnees_des_notices', array( $plugin->ids_de_la_rubrique( $rubs_rel[0] ) ) ), &$erreur_rel ) ) );
na_verifier( '' !== $fascicule_relu && false === strpos( $fascicule_relu, '<w:ins' ) && false === strpos( $fascicule_relu, '<w:del' )
	&& false !== strpos( $fascicule_relu, 'coordinateur' ) && false !== strpos( $fascicule_relu, 'Plan du four' ),
	'le fascicule se fabrique de la dernière version, vierge de tout suivi' );
na_verifier( false === strpos( $fascicule_relu, 'zénithale' ), 'le fascicule ne porte pas la figure masquée' );
$page_rel = $plugin->page_a_lire( $rel_a, function ( $rang ) {
	return 'apercu-' . $rang;
} );
na_verifier( false !== strpos( $page_rel, 'Plan du four' ) && false === strpos( $page_rel, 'zénithale' ) && false === strpos( $page_rel, 'a@example.org' ),
	'la lecture seule ne montre ni la figure masquée ni le courriel des responsables' );

// L'écran d'un coordinateur ne reçoit pas les courriels ; celui de la direction, si.
$ecran_rel = ( new ReflectionClass( $R ) )->newInstanceWithoutConstructor();
$notice_ecran = $plugin->notice_a_relire( $rel_a );
$rendre_rel = function ( $compte ) use ( $ecran_rel, $rel_a, $notice_ecran, $rubs_rel, $R ) {
	wp_set_current_user( $compte );
	ob_start();
	na_appel( $ecran_rel, 'afficher_le_formulaire', array( $rel_a, $notice_ecran, $R::droit_sur( $rel_a, $compte ), $rubs_rel[0], $R::etat_de( $rel_a ) ) );
	return (string) ob_get_clean();
};
$page_coord = $rendre_rel( $coord_1 );
$page_redac = $rendre_rel( $redac );
wp_set_current_user( 0 );
na_verifier( false === strpos( $page_coord, 'a@example.org' ) && false !== strpos( $page_redac, 'a@example.org' )
	&& false === strpos( $page_redac, 'name="resp_email"' ) && false !== strpos( $page_coord, 'name="resp_nom"' ),
	'le formulaire du coordinateur ne porte pas les courriels ; la direction les lit, sans champ pour les changer' );
na_verifier( false !== strpos( $page_coord, 'Vous pouvez retirer une illustration si elle ne vous semble pas utile.' )
	&& false === strpos( $page_coord, 'type="file"' ) && false !== strpos( $page_coord, 'value="suivante"' ) && false !== strpos( $page_coord, 'name="garde_motif[1]"' )
	&& false === strpos( $page_coord, 'name="rubrique_principale"' ) && false !== strpos( $page_redac, 'name="rubrique_principale"' )
	&& false !== strpos( $page_redac, 'value="valider"' ) && false === strpos( $page_coord, 'value="valider"' )
	&& false === strpos( $page_redac, '<option value="validee"' ),
	'le formulaire dit qu\'on peut retirer une illustration, sans champ pour en ajouter, avec ses deux boutons' );

// La direction : soumettre à l'auteur, rétablir, valider, rouvrir.
$R::liberer_le_verrou( $rel_a, $coord_1 );
$courriel_rel = array( 'part' => false, 'vu' => array() );
$poste_rel    = function ( $rendu, $atts ) use ( &$courriel_rel ) {
	$courriel_rel['vu'] = $atts;
	return $courriel_rel['part'];
};
add_filter( 'pre_wp_mail', $poste_rel, 10, 2 );
$refus_envoi = $R::soumettre_la_notice( $rel_a, $redac );
$etat_refus  = $R::etat_de( $rel_a );
$courriel_rel['part'] = true;
$envoi_rel   = $R::soumettre_la_notice( $rel_a, $redac );
$vu_envoi           = $courriel_rel['vu'];
$courriel_rel['vu'] = array();
$envoi_rejoue       = $R::soumettre_la_notice( $rel_a, $redac );
$vu_rejoue          = $courriel_rel['vu'];
$courriel_rel['vu'] = $vu_envoi;
remove_filter( 'pre_wp_mail', $poste_rel, 10 );
na_verifier( '' !== $refus_envoi && 'a_soumettre' === $etat_refus && 2 === $file_rel->version_de( $rel_a ),
	'un courriel à l\'auteur qui ne part pas ne change ni l\'état ni la notice', $refus_envoi );
na_verifier( '' === $envoi_rel && 'soumise' === $R::etat_de( $rel_a ) && 'a@example.org' === $courriel_rel['vu']['to']
	&& 1 === count( (array) $courriel_rel['vu']['attachments'] ),
	'la direction soumet la notice à l\'auteur d\'un geste : un courriel au responsable, le Word joint, l\'état « soumise »', $envoi_rel );
na_verifier( '' !== $envoi_rejoue && array() === $vu_rejoue && false !== strpos( $envoi_rejoue, 'à soumettre' ) && 'soumise' === $R::etat_de( $rel_a ),
	'le serveur refuse de soumettre une notice qui n\'est pas « à soumettre à l\'auteur » : aucun courriel ne part', $envoi_rejoue );
$_POST = wp_slash( array( 'texte_notice' => '<p>Le coordinateur revient après coup.</p>' ) );
$trop_tard = $R::enregistrer( $rel_a, 2, $coord_1 );
$d_tard    = $lire_rel( $rel_a );
na_verifier( 'inchangee' === $trop_tard['issue'] && false === strpos( $d_tard['texte_notice'], 'après coup' ),
	'une notice soumise à l\'auteur ne se corrige plus par le coordinateur', $trop_tard );
$_POST = wp_slash( array( 'etat' => 'validee', 'rubrique_principale' => $rubs_rel[0] ) );
$par_le_menu = $R::enregistrer( $rel_a, 2, $redac );
$R::liberer_le_verrou( $rel_a, $redac );
na_verifier( 'inchangee' === $par_le_menu['issue'] && 'soumise' === $R::etat_de( $rel_a ), 'la validation ne passe pas par la liste des états', $par_le_menu );
$_POST = wp_slash( array( 'suite' => 'valider', 'garde_action' => array( 2 => 'retablir' ), 'rubrique_principale' => $rubs_rel[0] ) );
$retabli   = $R::enregistrer( $rel_a, 2, $redac );
$d_retabli = $lire_rel( $rel_a );
na_verifier( 'enregistree' === $retabli['issue'] && 3 === $retabli['version'] && null === Notice_Archeomed_Pactols::figure_masquee( $d_retabli['illustrations'][1] )
	&& 'validee' === $R::etat_de( $rel_a ) && array( 1, 2, 3 ) === array_keys( $R::versions_gardees( $rel_a ) ),
	'la direction rétablit la figure avec le même écran, valide la notice, et son enregistrement est versionné de même', array( $par_le_menu, $retabli ) );
$verrou_valide = $R::verrou_de( $rel_a );
na_verifier( 0 === $verrou_valide['par'], 'valider libère la notice', $verrou_valide );

// Une notice validée est figée : rien ne la corrige, ni l'écran, ni le lien du courriel.
$_POST = wp_slash( array( 'etat' => 'a_relire' ) );
$etat_fige = $R::enregistrer( $rel_a, 3, $redac );
$_POST = wp_slash( array( 'texte_notice' => '<p>La direction récrit la notice validée.</p>' ) );
$texte_fige = $R::enregistrer( $rel_a, 3, $redac );
$R::liberer_le_verrou( $rel_a, $redac );
$d_fige = $lire_rel( $rel_a );
wp_set_current_user( $redac );
$lien_fige = na_appel( $plugin, 'contexte_valide', array( array( 'corrige_id' => $rel_a, 'vivant' => true, 'par_la_redaction' => $redac ) ) );
wp_set_current_user( 0 );
na_verifier( 'validee' === $R::etat_de( $rel_a ) && 'figee' === $texte_fige['issue'] && false === strpos( $d_fige['texte_notice'], 'récrit' )
	&& 3 === $file_rel->version_de( $rel_a ) && 0 === $lien_fige && false === $R::peut_corriger( $R::droit_sur( $rel_a, $redac ), 'validee' ),
	'une notice validée ne se corrige plus : ni son état ni son texte ne changent, et le lien du courriel ne l\'ouvre plus', array( $etat_fige, $texte_fige, $lien_fige ) );

// La réouverture : la direction seule, avec un motif, au journal.
$refus_reouv = $R::rouvrir_la_notice( $rel_a, $coord_1, 'Le coordinateur voudrait reprendre.' );
$reouv       = $R::rouvrir_la_notice( $rel_a, $redac, 'Une coquille dans le titre.' );
$journal_reouv = $R::journal_de( $rel_a );
$ligne_reouv   = end( $journal_reouv );
$rejoue_reouv  = $R::rouvrir_la_notice( $rel_a, $redac, '' );
na_verifier( '' !== $refus_reouv && '' === $reouv && 'soumise' === $R::etat_de( $rel_a ) && '' !== $rejoue_reouv
	&& in_array( 'rouverte après validation', (array) $ligne_reouv['quoi'], true ) && 'Une coquille dans le titre.' === $ligne_reouv['remarque']
	&& 'Direction Essai' === $ligne_reouv['par'],
	'seule la direction rouvre une notice validée : elle reprend son état d\'avant, et le journal garde qui, quand et pourquoi', array( $refus_reouv, $reouv, $ligne_reouv ) );
$_POST = wp_slash( array( 'etat' => 'a_relire' ) );
$R::enregistrer( $rel_a, 3, $redac );
$R::liberer_le_verrou( $rel_a, $redac );
na_verifier( 'a_relire' === $R::etat_de( $rel_a ) && 6 <= count( $R::journal_de( $rel_a ) ), 'rouverte, la notice change d\'état de nouveau, et chaque changement est au journal', count( $R::journal_de( $rel_a ) ) );

// Les renvois : ajout par le coordinateur de la rubrique, retrait par chacun des deux.
$_POST = wp_slash( array( 'renvoi_1' => $rubs_rel[1], 'renvoi_2' => $rubs_rel[0] ) );
$ajout_renvoi = $R::enregistrer( $rel_a, 3, $coord_1 );
$d_renvoi     = $lire_rel( $rel_a );
$R::liberer_le_verrou( $rel_a, $coord_1 );
$droit_renvoi = $R::droit_sur( $rel_a, $coord_2 );
$sommaire_2   = $R::sommaire( $rubs_rel[1] );
na_verifier( 'enregistree' === $ajout_renvoi['issue'] && $rubs_rel[1] === $d_renvoi['renvoi_1'] && '' === $d_renvoi['renvoi_2']
	&& array( $rubs_rel[1] ) === $ajout_renvoi['faits']['renvois_ajoutes'],
	'le coordinateur de la rubrique ajoute un renvoi ; il ne peut viser la rubrique de la notice elle-même', $ajout_renvoi );
na_verifier( is_array( $droit_renvoi ) && 'renvoi' === $droit_renvoi['sorte'] && in_array( $rel_a, array_map( 'intval', wp_list_pluck( $sommaire_2['renvois'], 'id' ) ), true )
	&& ! in_array( $rel_a, array_map( 'intval', wp_list_pluck( $sommaire_2['notices'], 'id' ) ), true ),
	'la notice ajoutée en renvoi paraît aussitôt, à part, dans le sommaire du coordinateur de l\'autre rubrique' );
$_POST = wp_slash( array( 'renvoi_2' => $rubs_rel[1] ) );
$doublon_renvoi = $R::enregistrer( $rel_a, 4, $coord_1 );
$R::liberer_le_verrou( $rel_a, $coord_1 );
na_verifier( 'inchangee' === $doublon_renvoi['issue'], 'un renvoi ne vise pas une rubrique déjà visée', $doublon_renvoi );
$_POST = wp_slash( array( 'texte_notice' => '<p>Le coordinateur du renvoi récrit.</p>', 'renvoi_2' => $rubs_rel[3], 'remarque' => 'À rapprocher de Lisieux.' ) );
$lecture_seule = $R::enregistrer( $rel_a, 4, $coord_2 );
$d_lecture     = $lire_rel( $rel_a );
$journal_renvoi = $R::journal_de( $rel_a );
$derniere_rel   = end( $journal_renvoi );
na_verifier( 'inchangee' === $lecture_seule['issue'] && false === strpos( $d_lecture['texte_notice'], 'récrit' ) && '' === $d_lecture['renvoi_2']
	&& 'À rapprocher de Lisieux.' === $derniere_rel['remarque'] && 'Paul Durand' === $derniere_rel['par'],
	'le coordinateur d\'une rubrique de renvoi lit sans corriger ni ajouter de renvoi, et laisse une remarque à la direction', $lecture_seule );
$_POST = wp_slash( array( 'retirer_renvoi' => array( 'renvoi_1' => '1' ), 'texte_notice' => '<p>Et récrit encore.</p>' ) );
$retrait_renvoi = $R::enregistrer( $rel_a, 4, $coord_2 );
$d_retrait      = $lire_rel( $rel_a );
$journal_renvoi = $R::journal_de( $rel_a );
$derniere_rel   = end( $journal_renvoi );
na_verifier( 'enregistree' === $retrait_renvoi['issue'] && '' === $d_retrait['renvoi_1'] && false === strpos( $d_retrait['texte_notice'], 'récrit' )
	&& null === $R::droit_sur( $rel_a, $coord_2 ) && false !== strpos( implode( ' | ', $derniere_rel['quoi'] ), 'renvoi retiré : ' . $rubs_rel[1] ),
	'le coordinateur d\'une rubrique de renvoi retire le renvoi vers la sienne, et rien d\'autre ; le retrait est au journal', $retrait_renvoi );
$_POST = wp_slash( array( 'renvoi_1' => $rubs_rel[1], 'renvoi_2' => $rubs_rel[3] ) );
$R::enregistrer( $rel_a, 5, $coord_1 );
$_POST = wp_slash( array( 'retirer_renvoi' => array( 'renvoi_2' => '1' ), 'renvoi_1' => $rubs_rel[1], 'renvoi_2' => $rubs_rel[3] ) );
$retrait_principal = $R::enregistrer( $rel_a, 6, $coord_1 );
$d_principal       = $lire_rel( $rel_a );
$R::liberer_le_verrou( $rel_a, $coord_1 );
na_verifier( 'enregistree' === $retrait_principal['issue'] && $rubs_rel[1] === $d_principal['renvoi_1'] && '' === $d_principal['renvoi_2']
	&& array( $rubs_rel[3] ) === $retrait_principal['faits']['renvois_retires'],
	'le coordinateur de la rubrique principale retire n\'importe quel renvoi de sa notice', $retrait_principal );

// Un renvoi vers V se range dans l'une de ses trois sous-rubriques : qui
// relit V la choisit, au-dessus de la notice qu'il ne corrige pas.
$rub_v   = Notice_Archeomed_Pactols::RUBRIQUE_ARTISANAT;
$rang_v  = array_search( $rub_v, $rubs_rel, true );
$coord_v = $compte_rel( 'coord-cinq', 'Claire Petit', $R::ROLE );
$R::regler( true, array( 0 => array( $coord_1, 999999 ), 1 => array( $coord_2 ), 9 => array( $coord_1 ), $rang_v => array( $coord_v ) ) );
$_POST = wp_slash( array( 'renvoi_1' => $rubs_rel[1], 'renvoi_2' => $rub_v ) );
$R::enregistrer( $rel_a, $file_rel->version_de( $rel_a ), $coord_1 );
$R::liberer_le_verrou( $rel_a, $coord_1 );
$famille_en_v = function () use ( $R, $rub_v, $rel_a ) {
	foreach ( $R::sommaire( $rub_v )['renvois'] as $e ) {
		if ( $rel_a === (int) $e['id'] ) {
			return (string) preg_replace( '/[\s\x{00A0}\x{202F}]+/u', ' ', $e['famille'] );
		}
	}
	return null;
};
$famille_v_avant = $famille_en_v();
$droit_v         = $R::droit_sur( $rel_a, $coord_v );
na_verifier( false !== $rang_v && is_string( $famille_v_avant ) && false === strpos( $famille_v_avant, 'V. B' )
	&& is_array( $droit_v ) && 'renvoi' === $droit_v['sorte'] && $R::peut_classer_le_renvoi( $droit_v, $coord_v )
	&& ! $R::peut_classer_le_renvoi( $R::droit_sur( $rel_a, $coord_2 ), $coord_2 ),
	'un renvoi vers V paraît au sommaire de V sans sous-rubrique, et seul qui relit V peut l\'y ranger', array( $famille_v_avant, $droit_v ) );
$_POST = wp_slash( array( 'renvoi_matiere' => 'b' ) );
$classe_par_ii = $R::enregistrer( $rel_a, $file_rel->version_de( $rel_a ), $coord_2 );
$d_classe_ii   = $lire_rel( $rel_a );
na_verifier( 'inchangee' === $classe_par_ii['issue'] && empty( $d_classe_ii['renvoi_matiere'] ),
	'le coordinateur d\'une autre rubrique de renvoi ne range pas le renvoi dans V', $classe_par_ii );
$version_v  = $file_rel->version_de( $rel_a );
$classe_v   = $R::enregistrer( $rel_a, $version_v, $coord_v );
$d_classe_v = $lire_rel( $rel_a );
$journal_v  = $R::journal_de( $rel_a );
$derniere_v = end( $journal_v );
$famille_v  = $famille_en_v();
na_verifier( 'enregistree' === $classe_v['issue'] && 'B' === $d_classe_v['renvoi_matiere'] && $version_v + 1 === $file_rel->version_de( $rel_a )
	&& false !== strpos( implode( ' | ', $derniere_v['quoi'] ), 'renvoi rangé dans V sous B' ) && 'Claire Petit' === $derniere_v['par']
	&& false === strpos( $d_classe_v['texte_notice'], 'récrit' ) && $rubs_rel[0] === $d_classe_v['rubrique_principale']
	&& empty( $d_classe_v['rubrique_matiere'] ),
	'qui relit V range le renvoi dans sa sous-rubrique, sans toucher à la notice ; le geste est au journal', array( $classe_v, $derniere_v ) );
na_verifier( is_string( $famille_v ) && 0 === strpos( $famille_v, 'V. B1. –' ),
	'au sommaire de V, le renvoi rangé paraît sous sa sous-rubrique', $famille_v );
$famille_ii = null;
foreach ( $R::sommaire( $rubs_rel[1] )['renvois'] as $e ) {
	if ( $rel_a === (int) $e['id'] ) {
		$famille_ii = (string) preg_replace( '/[\s\x{00A0}\x{202F}]+/u', ' ', $e['famille'] );
	}
}
na_verifier( is_string( $famille_ii ) && 0 === strpos( $famille_ii, 'II. 1.' ),
	'dans une autre rubrique, le même renvoi garde la sous-rubrique de celle-ci', $famille_ii );
$classe_redite = $R::enregistrer( $rel_a, $file_rel->version_de( $rel_a ), $coord_v );
na_verifier( 'inchangee' === $classe_redite['issue'] && $coord_v !== $R::verrou_de( $rel_a )['par'],
	'redire le même rang ne fait pas de version', $classe_redite );
$d_v_a = array_merge( $saisie_styles, array( 'commune' => 'Bayeux', 'lieux' => array( array( 'nom' => 'Bayeux', 'ark' => '' ) ),
	'rubrique_principale' => $rub_v, 'rubrique_matiere' => 'A', 'renvoi_1' => '', 'nature' => 'Fouille préventive' ) );
$erreur_v = '';
$fasc_v   = na_appel( $plugin, 'fabriquer_le_fascicule', array( $rub_v, array( $d_v_a ), &$erreur_v ) );
$xml_v    = str_replace( "\u{00A0}", ' ', $xml_du_docx( $fasc_v ) );
if ( '' !== (string) $fasc_v ) {
	@unlink( $fasc_v );
}
$pos_va = strpos( $xml_v, 'V. A1. –' );
$pos_vb = strpos( $xml_v, 'V. B1. –' );
$pos_vi = false !== $pos_vb ? strpos( $xml_v, 'Vire', $pos_vb ) : false;
na_verifier( false !== $pos_va && false !== $pos_vb && $pos_va < $pos_vb && false !== $pos_vi,
	'au fascicule de V, le renvoi rangé paraît sous sa sous-rubrique, après la matière qui la précède', '' !== $erreur_v ? $erreur_v : array( $pos_va, $pos_vb, $pos_vi ) );
$_POST = wp_slash( array( 'retirer_renvoi' => array( 'renvoi_2' => '1' ), 'renvoi_1' => $rubs_rel[1], 'renvoi_2' => $rub_v ) );
$retrait_v = $R::enregistrer( $rel_a, $file_rel->version_de( $rel_a ), $coord_1 );
$R::liberer_le_verrou( $rel_a, $coord_1 );
$d_retrait_v = $lire_rel( $rel_a );
na_verifier( 'enregistree' === $retrait_v['issue'] && '' === $d_retrait_v['renvoi_2'] && '' === $d_retrait_v['renvoi_matiere']
	&& null === $famille_en_v() && ! isset( $retrait_v['faits']['renvoi_classe'] ),
	'le renvoi vers V retiré emporte sa sous-rubrique', $retrait_v );
$R::regler( true, array( 0 => array( $coord_1, 999999 ), 1 => array( $coord_2 ), 9 => array( $coord_1 ) ) );
wp_delete_user( $coord_v );

// Les lieux du titre : un nom choisi prend l'identifiant du terme, un nom inchangé garde le sien.
$ark_falaise = 'https://ark.frantiq.fr/ark:/26678/pcrtFalaiseEssai';
update_post_meta( $rel_a, '_na_sdrive_dossier', 'AM99/originaux/Vire' );
$_POST = wp_slash( array( 'commune' => array( 'Falaise' ), 'commune_ark' => array( $ark_falaise ) ) );
$lieu_choisi = $R::enregistrer( $rel_a, $file_rel->version_de( $rel_a ), $coord_1 );
$d_lieu      = $lire_rel( $rel_a );
na_verifier( 'enregistree' === $lieu_choisi['issue'] && array( array( 'nom' => 'Falaise', 'ark' => $ark_falaise ) ) === $d_lieu['lieux']
	&& 'Falaise' === $d_lieu['commune'] && $ark_falaise === $d_lieu['commune_ark'],
	'une commune changée prend l\'identifiant du terme choisi dans le thésaurus', $d_lieu['lieux'] );
$_POST = wp_slash( array( 'commune' => array( 'Falaise', 'Bayeux', 'Vire' ),
	'commune_ark' => array( 'https://ark.frantiq.fr/ark:/26678/pcrtUnAutre', 'javascript:alert(1)', '' ) ) );
$R::enregistrer( $rel_a, $file_rel->version_de( $rel_a ), $coord_1 );
$d_lieu = $lire_rel( $rel_a );
$R::liberer_le_verrou( $rel_a, $coord_1 );
na_verifier( 3 === count( $d_lieu['lieux'] ) && $ark_falaise === $d_lieu['lieux'][0]['ark'] && '' === $d_lieu['lieux'][1]['ark'] && '' === $d_lieu['lieux'][2]['ark'],
	'un nom inchangé garde son identifiant quoi que poste la requête ; un identifiant qui n\'est pas de Frantiq n\'entre pas', $d_lieu['lieux'] );
na_verifier( 'AM99/originaux/Vire' === Notice_Archeomed_Rangement::dossier_de( $rel_a ),
	'le dossier de la notice sur sDrive garde son nom quand la commune change', Notice_Archeomed_Rangement::dossier_de( $rel_a ) );

// L'écran public : le coordinateur relit sur le site, jamais dans l'administration.
$page_ecran  = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Relecture', 'post_content' => '[relecture_archeomed]' ) );
$adresse_rel = $R::page();
$abonne_rel  = $compte_rel( 'abonne-essai', 'Abonné Essai', 'subscriber' );
na_verifier( '' !== $adresse_rel && shortcode_exists( $R::SHORTCODE ) && 0 === strpos( $R::url( $rubs_rel[0], $rel_a ), $adresse_rel )
	&& false !== strpos( $R::url( $rubs_rel[0], $rel_a ), 'na_notice=' . $rel_a ),
	'l\'écran de relecture vit sur la page publique qui porte le shortcode', $R::url( $rubs_rel[0], $rel_a ) );
na_verifier( $adresse_rel === $R::renvoi_de_l_administration( $coord_1, 'index.php' ) && $adresse_rel === $R::renvoi_de_l_administration( $coord_1, 'profile.php' )
	&& '' === $R::renvoi_de_l_administration( $coord_1, 'admin-post.php' ) && '' === $R::renvoi_de_l_administration( $coord_1, 'admin-ajax.php' )
	&& '' === $R::renvoi_de_l_administration( $coord_1, 'index.php', true ),
	'un coordinateur qui ouvre l\'administration est renvoyé à la page de relecture, sauf par les deux portes que l\'écran emploie' );
na_verifier( '' === $R::renvoi_de_l_administration( $redac, 'index.php' ) && '' === $R::renvoi_de_l_administration( $admin_rel, 'index.php' )
	&& '' === $R::renvoi_de_l_administration( $abonne_rel, 'index.php' ),
	'la direction, un administrateur et un compte sans rapport avec la relecture ne sont pas renvoyés à la relecture' );
$reglages_dir = Notice_Archeomed_Settings::url();
$liste_dir    = admin_url( 'edit.php?post_type=' . Notice_Archeomed_File::CPT );
$article_dir  = wp_insert_post( array( 'post_title' => 'Un article', 'post_status' => 'publish', 'post_type' => 'post' ) );
na_verifier( Notice_Archeomed_File::CPT === get_post_type( $rel_a )
	&& '' === $R::renvoi_de_la_direction( $redac, 'edit.php', array( 'post_type' => Notice_Archeomed_File::CPT ) )
	&& '' === $R::renvoi_de_la_direction( $redac, 'edit.php', array( 'post_type' => Notice_Archeomed_File::CPT, 'page' => Notice_Archeomed_Settings::PAGE_SLUG, 'tab' => 'annuaire' ) )
	&& '' === $R::renvoi_de_la_direction( $redac, 'post.php', array( 'post' => (string) $rel_a, 'action' => 'edit' ) )
	&& '' === $R::renvoi_de_la_direction( $redac, 'post.php', array( 'post_ID' => $rel_a ) )
	&& '' === $R::renvoi_de_la_direction( $redac, 'admin.php', array( 'page' => $R::PAGE ) ) && '' === $R::renvoi_de_la_direction( $redac, 'options.php', array() ),
	'la direction entre dans la Chronique entière : la liste des notices, leurs fiches, les réglages, la relecture' );
na_verifier( $liste_dir === $R::renvoi_de_la_direction( $redac, 'index.php', array() ) && $liste_dir === $R::renvoi_de_la_direction( $redac, 'profile.php', array() )
	&& $liste_dir === $R::renvoi_de_la_direction( $redac, 'edit.php', array( 'post_type' => 'post' ) )
	&& $liste_dir === $R::renvoi_de_la_direction( $redac, 'post.php', array( 'post' => (string) $article_dir, 'action' => 'edit' ) )
	&& add_query_arg( 'tab', 'annuaire', $reglages_dir ) === $R::renvoi_de_la_direction( $redac, 'admin.php', array( 'page' => Notice_Archeomed_Settings::PAGE_SLUG, 'tab' => 'annuaire' ) )
	&& '' === $R::renvoi_de_la_direction( $admin_rel, 'index.php', array() ) && '' === $R::renvoi_de_la_direction( $coord_1, 'index.php', array() ),
	'hors de la Chronique, la direction revient aux notices reçues ; l\'adresse des réglages de la 3.85 mène à la nouvelle' );
wp_delete_post( $article_dir, true );
na_verifier( $adresse_rel === $R::apres_la_connexion( admin_url(), admin_url(), get_userdata( $coord_1 ) )
	&& $R::url( $rubs_rel[0], $rel_a ) === $R::apres_la_connexion( admin_url(), $R::url( $rubs_rel[0], $rel_a ), get_userdata( $coord_1 ) )
	&& $adresse_rel === $R::apres_la_connexion( admin_url(), admin_url(), get_userdata( $redac ) )
	&& $reglages_dir === $R::apres_la_connexion( $reglages_dir, $reglages_dir, get_userdata( $redac ) )
	&& $liste_dir === $R::apres_la_connexion( $liste_dir, $liste_dir, get_userdata( $redac ) )
	&& admin_url() === $R::apres_la_connexion( admin_url(), admin_url(), get_userdata( $admin_rel ) ),
	'après sa connexion, un coordinateur ou la direction arrive sur la page de relecture, ou sur la page qu\'il demandait' );
wp_set_current_user( $coord_1 );
$barre_coord = $R::sans_barre_d_administration( true );
$_GET        = array( 'na_notice' => (string) $rel_a );
$ecran_coord = do_shortcode( '[' . $R::SHORTCODE . ']' );
$_GET        = array( 'na_notice' => (string) $rel_d );
$ecran_autre = do_shortcode( '[' . $R::SHORTCODE . ']' );
$R::liberer_le_verrou( $rel_a, $coord_1 );
wp_set_current_user( $redac );
$barre_redac = $R::sans_barre_d_administration( true );
$_GET        = array( 'na_notice' => (string) $rel_a );
$ecran_redac = do_shortcode( '[' . $R::SHORTCODE . ']' );
$R::liberer_le_verrou( $rel_a, $redac );
wp_set_current_user( $abonne_rel );
$ecran_abonne = do_shortcode( '[' . $R::SHORTCODE . ']' );
wp_set_current_user( 0 );
$ecran_anonyme = do_shortcode( '[' . $R::SHORTCODE . ']' );
$_GET          = array();
na_verifier( false === $barre_coord && true === $barre_redac, 'un coordinateur n\'a pas de barre d\'administration sur le site ; la direction garde la sienne' );
na_verifier( false !== strpos( $ecran_coord, 'na-rel-sommaire' ) && false !== strpos( $ecran_coord, 'name="resp_nom"' ) && false !== strpos( $ecran_coord, 'name="commune_ark[]"' )
	&& false !== strpos( $ecran_coord, 'Journal et versions' ) && false === strpos( $ecran_coord, '<script' ) && false === strpos( $ecran_coord, 'wp-admin/admin.php' ),
	'le shortcode rend au coordinateur le sommaire, le formulaire et le journal, ses scripts renvoyés au pied de page' );
// Le sommaire garde sa place d'une notice à l'autre ; la CI vérifie que ce
// script est du JavaScript valide.
ob_start();
na_appel( $R, 'poser_le_script_du_sommaire' );
$script_sommaire = (string) ob_get_clean();
file_put_contents( '/tmp/relecture-sommaire.js', (string) preg_replace( '#^\s*<script>|</script>\s*$#', '', $script_sommaire ) );
na_verifier( false !== strpos( $script_sommaire, 'sessionStorage' ) && false !== strpos( $script_sommaire, '.na-rel-ouverte' ),
	'le sommaire garde sa position au clic, et montre la notice ouverte à défaut' );
na_verifier( false === strpos( $ecran_coord, 'a@example.org' ) && false === strpos( $ecran_coord, 'example.org' ) && false !== strpos( $ecran_redac, 'a@example.org' ),
	'le HTML rendu au coordinateur ne contient aucun courriel des responsables ; celui de la direction les montre' );
na_verifier( false === strpos( $ecran_autre, 'name="resp_nom"' ) && false !== strpos( $ecran_autre, 'Cette notice n’est pas de vos rubriques.' ) && false === strpos( $ecran_autre, 'Lisieux' ),
	'une notice d\'une autre rubrique, demandée par son numéro dans l\'adresse, ne se montre pas' );
na_verifier( false === strpos( $ecran_abonne, 'na-rel-sommaire' ) && false !== strpos( $ecran_abonne, 'réservée aux relecteurs' ) && false === strpos( $ecran_abonne, 'Falaise' )
	&& false === strpos( $ecran_anonyme, 'na-rel-sommaire' ) && false !== strpos( $ecran_anonyme, 'wp-login.php' ),
	'un compte sans droit lit une phrase, et rien de plus ; sans connexion, l\'écran ne propose que de se connecter' );
// La relecture vue comme un coordinateur : la direction emprunte son
// regard, voit l'écran comme lui, et rien de ce qu'elle y fait ne reste.
$vue_cle   = function ( $compte ) {
	return 'na_relecture_vue_' . (int) $compte;
};
$rendre_vue = function ( $compte, $notice ) use ( $R ) {
	wp_set_current_user( $compte );
	$_GET = array( 'na_notice' => (string) $notice );
	$html = do_shortcode( '[' . $R::SHORTCODE . ']' );
	$_GET = array();
	return $html;
};
$R::liberer_le_verrou( $rel_a, $coord_1 );
$version_vue = $file_rel->version_de( $rel_a );
$journal_vue = count( $R::journal_de( $rel_a ) );
$regard_1    = $R::regard_choisi( array( 'sorte' => 'compte', 'valeur' => $coord_1 ) );
$regard_2    = $R::regard_choisi( array( 'sorte' => 'rubrique', 'valeur' => $rubs_rel[1] ) );
$memes_droits = true;
foreach ( array( $rel_a, $rel_b, $rel_c, $rel_d ) as $id ) {
	$memes_droits = $memes_droits && $R::droit_sur( $id, $coord_1 ) === $R::droit_sur( $id, $regard_1 )
		&& $R::droit_sur( $id, $coord_2 ) === $R::droit_sur( $id, $regard_2 );
}
na_verifier( $regard_1 instanceof Notice_Archeomed_Regard && $coord_1 === (int) $regard_1->ID && $R::rubriques_de( $coord_1 ) === $R::rubriques_de( $regard_1 )
	&& $regard_2 instanceof Notice_Archeomed_Regard && array( $rubs_rel[1] ) === $R::rubriques_de( $regard_2 ) && -1 === (int) $regard_2->ID
	&& ! $R::peut_tout( $regard_1 ) && ! $R::peut_tout( $regard_2 ) && $memes_droits,
	'le regard d\'un coordinateur a ses rubriques et ses droits, ni plus ni moins ; celui d\'une rubrique sans compte, cette rubrique seule' );
na_verifier( null === $R::regard_choisi( array( 'sorte' => 'compte', 'valeur' => $redac ) ) && null === $R::regard_choisi( array( 'sorte' => 'compte', 'valeur' => $abonne_rel ) )
	&& null === $R::regard_choisi( array( 'sorte' => 'compte', 'valeur' => 999999 ) ) && null === $R::regard_choisi( array( 'sorte' => 'rubrique', 'valeur' => 'XX. Rubrique forgée' ) )
	&& null === $R::regard_choisi( 'compte:' . $coord_1 ),
	'on n\'emprunte que le regard d\'un coordinateur des réglages, ou d\'une rubrique du fascicule' );
$possibles_vue = $R::regards_possibles();
na_verifier( isset( $possibles_vue['comptes'][ 'compte:' . $coord_1 ], $possibles_vue['comptes'][ 'compte:' . $coord_2 ] )
	&& ! isset( $possibles_vue['comptes'][ 'compte:' . $redac ] ) && count( $rubs_rel ) === count( $possibles_vue['rubriques'] )
	&& 0 === strpos( $possibles_vue['comptes'][ 'compte:' . $coord_1 ], 'Jeanne Martin — rubrique ' ),
	'la liste propose les coordinateurs des réglages, avec leurs rubriques, puis chaque rubrique', $possibles_vue );

// Rien ne s'écrit par un regard.
$_POST       = wp_slash( array( 'texte_notice' => '<p>Le regard récrit la notice.</p>', 'remarque' => 'Remarque du regard.' ) );
$ecrit_vue   = $R::enregistrer( $rel_a, $version_vue, $regard_1 );
$R::noter_au_journal( $rel_a, $regard_1, array( 'essai' ), 'a_relire', 'a_relire', 'Trace du regard.' );
$soumis_vue  = $R::soumettre_la_notice( $rel_a, $regard_1 );
$_POST       = array();
$d_vue       = $lire_rel( $rel_a );
$verrou_vue  = $R::verrou_de( $rel_a );
na_verifier( 'interdit' === $ecrit_vue['issue'] && $version_vue === $file_rel->version_de( $rel_a ) && false === strpos( $d_vue['texte_notice'], 'Le regard récrit' )
	&& $journal_vue === count( $R::journal_de( $rel_a ) ) && 0 === $verrou_vue['par'] && '' !== $soumis_vue,
	'le regard n\'enregistre rien, ne note rien au journal, ne prend pas le verrou et n\'envoie rien à l\'auteur', array( $ecrit_vue, $soumis_vue ) );

// Le choix : pour soi seul, et seulement pour qui relit tout.
wp_set_current_user( $coord_1 );
$emprunt_coord = $R::emprunter_le_regard( $coord_1, 'compte:' . $coord_2 );
set_transient( $vue_cle( $coord_1 ), array( 'sorte' => 'compte', 'valeur' => $coord_2 ), HOUR_IN_SECONDS );
$vue_du_coord  = $R::vue();
$reste_coord   = get_transient( $vue_cle( $coord_1 ) );
na_verifier( false === $emprunt_coord && null === $vue_du_coord && false === $reste_coord,
	'un coordinateur n\'emprunte pas le regard d\'un autre : un choix posé pour lui ne vaut rien, et s\'efface' );
wp_set_current_user( $redac );
$emprunt_redac = $R::emprunter_le_regard( $redac, 'compte:' . $coord_1 );
$refus_redac   = ! $R::emprunter_le_regard( $redac, 'compte:' . $redac ) && ! $R::emprunter_le_regard( $redac, 'compte:' . $abonne_rel )
	&& ! $R::emprunter_le_regard( $redac, 'rubrique:99' ) && ! $R::emprunter_le_regard( $redac, 'n’importe quoi' );
$vue_redac     = $R::vue();
wp_set_current_user( $admin_rel );
$vue_admin     = $R::vue();
na_verifier( $emprunt_redac && $refus_redac && $vue_redac instanceof Notice_Archeomed_Regard && $coord_1 === (int) $vue_redac->ID && null === $vue_admin,
	'la direction emprunte le regard d\'un coordinateur pour son compte seul ; un choix refusé ne remplace pas le sien' );

// L'écran sous ce regard : celui du coordinateur, bandeau en tête, inerte.
$ecran_vue   = $rendre_vue( $redac, $rel_a );
$verrou_vue  = $R::verrou_de( $rel_a );
$actifs_vue  = preg_match_all( '#<(input|select|textarea|button)\b(?![^>]*\sdisabled\b)(?![^>]*na-rel-agrandir)[^>]*>#i', $ecran_vue );
na_verifier( false !== strpos( $ecran_vue, esc_html( $R::phrase_du_regard( $regard_1 ) ) ) && false !== strpos( $ecran_vue, 'Jeanne Martin (coordination de la rubrique ' )
	&& false !== strpos( $ecran_vue, 'Rien de ce que vous faites ici ne s’enregistre.' ) && false !== strpos( $ecran_vue, 'na_relecture_vue_quitter' )
	&& false === strpos( $ecran_vue, 'La campagne n’est pas close' ) && false === strpos( $ecran_vue, 'Vous voyez toutes les rubriques' ),
	'l\'écran dit qui l\'on regarde, que rien ne s\'enregistre, et propose d\'en sortir', substr( $ecran_vue, 0, 1500 ) );
na_verifier( 1 === preg_match( '#<input disabled[^>]*name="resp_nom"#', $ecran_vue ) && 0 === $actifs_vue && false === strpos( $ecran_vue, 'example.org' )
	&& false === strpos( $ecran_vue, 'na-rel-sommaire--tout' ) && 0 === $verrou_vue['par'],
	'sous le regard du coordinateur, le formulaire est le sien, sans courriel, champs et boutons désactivés, et la notice n\'est pas verrouillée', $actifs_vue );
$ecran_vue_d = $rendre_vue( $redac, $rel_d );
na_verifier( false !== strpos( $ecran_vue_d, 'Cette notice n’est pas de vos rubriques.' ) && false === strpos( $ecran_vue_d, 'name="resp_nom"' ),
	'une notice hors de ses rubriques ne se montre pas plus sous son regard qu\'à lui' );
$R::prendre_le_verrou( $rel_a, $redac );
$ecran_tenu = $rendre_vue( $redac, $rel_a );
$R::liberer_le_verrou( $rel_a, $redac );
na_verifier( false !== strpos( $ecran_tenu, 'Cette notice est ouverte par Direction Essai' ) && false === strpos( $ecran_tenu, 'name="resp_nom"' ),
	'une notice qu\'un autre tient se montre au regard comme au coordinateur : ouverte par lui, en lecture' );

// Avant la clôture : la direction ne voit rien, le regard voit comme si c'était clos.
$R::regler( false, array( 0 => array( $coord_1 ), 1 => array( $coord_2 ) ) );
$ecran_avant = $rendre_vue( $redac, $rel_a );
$vue_avant   = $R::vue();
delete_transient( $vue_cle( $redac ) );
$ecran_seul  = $rendre_vue( $redac, $rel_a );
na_verifier( null === $R::droit_sur( $rel_a, $redac ) && null === $R::droit_sur( $rel_a, $coord_1 ) && is_array( $R::droit_sur( $rel_a, $regard_1 ) )
	&& $vue_avant instanceof Notice_Archeomed_Regard && false !== strpos( $ecran_avant, 'La campagne n’est pas close : aujourd’hui, Jeanne Martin ne voit encore aucune notice.' )
	&& false !== strpos( $ecran_avant, 'name="resp_nom"' ) && false !== strpos( $ecran_seul, 'La relecture s’ouvrira quand la campagne sera close.' )
	&& false === strpos( $ecran_seul, 'name="resp_nom"' ),
	'avant la clôture, ni la direction ni le coordinateur ne relisent ; le regard montre la relecture comme si elle l\'était, et le dit' );

// Le regard d'une rubrique, et un choix que les réglages ne permettent plus.
$R::regler( true, array( 0 => array( $coord_1 ), 1 => array( $coord_2 ) ) );
wp_set_current_user( $redac );
$emprunt_rub = $R::emprunter_le_regard( $redac, 'rubrique:2' );
$ecran_rub   = $rendre_vue( $redac, $rel_d );
na_verifier( $emprunt_rub && false !== strpos( $ecran_rub, esc_html( $R::phrase_du_regard( $regard_2 ) ) ) && false !== strpos( $ecran_rub, 'comme la verrait la coordination de la rubrique ' )
	&& false !== strpos( $ecran_rub, 'na-rel-titre' ) && false === strpos( $ecran_rub, 'Cette notice n’est pas de vos rubriques.' ),
	'le regard d\'une rubrique montre ses notices comme à sa coordination, même sans compte', substr( $ecran_rub, 0, 800 ) );
$R::emprunter_le_regard( $redac, 'compte:' . $coord_1 );
$R::regler( true, array( 1 => array( $coord_2 ) ) );
wp_set_current_user( $redac );
$vue_perdue = $R::vue();
na_verifier( null === $vue_perdue && false === get_transient( $vue_cle( $redac ) ) && null === $R::regard_choisi( array( 'sorte' => 'compte', 'valeur' => $coord_1 ) ),
	'le regard d\'un compte qui ne coordonne plus de rubrique s\'efface à la requête suivante' );
$R::regler( true, array( 0 => array( $coord_1 ), 1 => array( $coord_2 ) ) );
foreach ( array( $redac, $coord_1, $admin_rel ) as $compte ) {
	delete_transient( $vue_cle( $compte ) );
}
wp_set_current_user( 0 );
$_GET  = array();
$_POST = array();

wp_delete_post( $page_ecran, true );
wp_delete_user( $abonne_rel );

// Deux relectures de la même version : une seule passe.
$_POST = wp_slash( array( 'lieu_dit' => 'Le Bourg' ) );
$premier_rel = $R::enregistrer( $rel_c, 1, $coord_1 );
$_POST = wp_slash( array( 'lieu_dit' => 'Le Vieux Bourg' ) );
$second_rel  = $R::enregistrer( $rel_c, 1, $coord_1 );
$d_conflit   = $lire_rel( $rel_c );
na_verifier( 'enregistree' === $premier_rel['issue'] && 'conflit' === $second_rel['issue'] && 'Le Bourg' === $d_conflit['lieu_dit'],
	'deux enregistrements partis de la même version ne s\'écrasent pas : le second est rendu au conflit', $second_rel );
$_POST = wp_slash( array( 'lieu_dit' => 'Le Bourg' ) );
$rien_rel = $R::enregistrer( $rel_c, 2, $coord_1 );
na_verifier( 'inchangee' === $rien_rel['issue'] && 2 === $file_rel->version_de( $rel_c ), 'un enregistrement qui ne change rien ne fait pas de version', $rien_rel );

// La mise en forme des figures, à la relecture.
$rel_e = $notice_rel( array( 'commune' => 'Falaise', 'illustrations' => array(
	array( 'rang' => 1, 'titre' => 'Plan du castrum', 'titre_forme' => 'Plan du <em>castrum</em>', 'legende' => 'Vue du nord',
		'legende_forme' => '<span class="na-pc">Vue</span> du nord', 'credits' => 'A. Ferrand', 'alt' => 'Plan du castrum.',
		'description' => "Deux murs in situ.\nLe fossé.", 'description_forme' => 'Deux murs <em>in situ</em>.<br>Le fossé.', 'type' => 'plan' ),
) ) );
$ecran_e = $rendre_vue( $coord_1, $rel_e );
na_verifier( 1 === preg_match( '#name="garde_titre_forme\[1\]" value="Plan du &lt;em&gt;castrum&lt;/em&gt;"#', $ecran_e )
	&& 1 === preg_match( '#name="garde_legende_forme\[1\]" value="&lt;span class=&quot;na-pc&quot;&gt;Vue&lt;/span&gt; du nord"#', $ecran_e )
	&& 1 === preg_match( '#name="garde_description_forme\[1\]" value="Deux murs &lt;em&gt;in situ&lt;/em&gt;\.&lt;br&gt;Le fossé\."#u', $ecran_e )
	&& 1 === preg_match( '#name="garde_alt\[1\]"[^>]*>Plan du castrum\.\nDeux murs in situ\.\nLe fossé\.</textarea>#u', $ecran_e ),
	'la relecture porte la forme de chaque texte à côté de son champ, le champ pour l\'écoute restant brut', substr( $ecran_e, (int) strpos( $ecran_e, 'na-rel-forme' ), 900 ) );
$_POST = wp_slash( array(
	'garde_titre'             => array( 1 => 'Plan du castrum' ),
	'garde_titre_forme'       => array( 1 => 'Plan du <em>castrum</em>' ),
	'garde_legende'           => array( 1 => 'Vue du nord' ),
	'garde_legende_forme'     => array( 1 => 'Vue du <em>nord</em>' ),
	'garde_alt'               => array( 1 => "Plan du castrum.\nDeux murs in situ.\nLe grand fossé." ),
	'garde_description_forme' => array( 1 => 'Deux murs <em>in situ</em>.<br>Le fossé.' ),
) );
$forme_rel = $R::enregistrer( $rel_e, 1, $coord_1 );
$d_forme   = $lire_rel( $rel_e );
$fig_forme = isset( $d_forme['illustrations'][0] ) ? $d_forme['illustrations'][0] : array();
na_verifier( 'enregistree' === $forme_rel['issue'] && 'Plan du <em>castrum</em>' === $fig_forme['titre_forme'] && 'Vue du <em>nord</em>' === $fig_forme['legende_forme']
	&& "Deux murs in situ.\nLe grand fossé." === $fig_forme['description'] && '' === $fig_forme['description_forme'] && 'Plan du castrum.' === $fig_forme['alt'],
	'la relecture corrige la forme d\'une légende ; une description retouchée sans sa forme la perd, pas une lettre', $fig_forme );
$_POST = wp_slash( array( 'garde_legende_forme' => array( 1 => '<span class="na-pc">Vue</span> du nord' ) ) );
$forme_rel2 = $R::enregistrer( $rel_e, 2, $coord_1 );
$d_forme2   = $lire_rel( $rel_e );
na_verifier( 'enregistree' === $forme_rel2['issue'] && '<span class="na-pc">Vue</span> du nord' === $d_forme2['illustrations'][0]['legende_forme']
	&& 'Vue du nord' === $d_forme2['illustrations'][0]['legende'] && 'Plan du <em>castrum</em>' === $d_forme2['illustrations'][0]['titre_forme'],
	'changer la seule mise en forme est déjà corriger la figure', array( $forme_rel2, $d_forme2['illustrations'][0] ) );
$R::liberer_le_verrou( $rel_e, $coord_1 );
wp_set_current_user( 0 );

$_POST = array();
foreach ( array( $rel_a, $rel_b, $rel_c, $rel_d, $rel_e ) as $id ) {
	wp_delete_post( $id, true );
}
foreach ( array( $coord_1, $coord_2, $redac ) as $compte ) {
	wp_delete_user( $compte );
}
delete_option( $R::OPTION );

WP_CLI::log( 'La purge des notices' );
$S_   = 'Notice_Archeomed_Settings';
$A_   = 'Notice_Archeomed_Annuaire';
$fondu = $A_::fondre(
	array( 'pur@annuaire.test' => array( 'nom' => 'Ancien', 'prenom' => 'Jean', 'courriel' => 'pur@annuaire.test', 'institution' => 'Inrap',
		'roles' => array( 'responsable' => true ), 'notices' => array( 'PURGE-1', 'PURGE-0' ) ),
		'seul@annuaire.test' => array( 'nom' => 'Seul', 'prenom' => '', 'courriel' => 'seul@annuaire.test', 'institution' => '', 'roles' => array(), 'notices' => array( 'X-1' ) ) ),
	array( 'pur@annuaire.test' => array( 'nom' => 'Nouveau', 'prenom' => '', 'courriel' => 'Pur@annuaire.test', 'institution' => '',
		'roles' => array( 'co-auteur' => true ), 'notices' => array( 12 => 'PURGE-1', 13 => 'PURGE-2' ) ) ) );
na_verifier( 'Nouveau' === $fondu['pur@annuaire.test']['nom'] && 'Jean' === $fondu['pur@annuaire.test']['prenom'] && 'Inrap' === $fondu['pur@annuaire.test']['institution']
	&& 'Pur@annuaire.test' === $fondu['pur@annuaire.test']['courriel']
	&& array( 'responsable' => true, 'co-auteur' => true ) === $fondu['pur@annuaire.test']['roles']
	&& array( 'g1' => 'PURGE-0', 12 => 'PURGE-1', 13 => 'PURGE-2' ) === $fondu['pur@annuaire.test']['notices']
	&& isset( $fondu['seul@annuaire.test'] ) && array( 'g0' => 'X-1' ) === $fondu['seul@annuaire.test']['notices'],
	'l\'annuaire fond les auteurs gardés et ceux des notices : la notice récente nomme, une référence revenue ne compte qu\'une fois', $fondu );

delete_option( $A_::OPTION_GARDEES );
$pur_1 = na_notice( array( 'commune' => 'Évreux', 'resp_nom' => 'Purgé', 'resp_prenom' => 'Jean', 'resp_email' => 'pur@annuaire.test', 'resp_inst' => 'Inrap' ) );
update_post_meta( $pur_1, '_na_reference', 'PURGE-1' );
$pur_2 = na_notice( array( 'commune' => 'Dreux', 'resp_nom' => 'Purgé', 'resp_prenom' => 'Jean', 'resp_email' => 'pur@annuaire.test',
	'coauteur_nom' => 'Second', 'coauteur_prenom' => 'Anne', 'coauteur_email' => 'anne@annuaire.test' ) );
update_post_meta( $pur_2, '_na_reference', 'PURGE-2' );
$pur_jusqua = $S_::derniere_notice();
$pur_3 = na_notice( array( 'commune' => 'Vernon', 'resp_nom' => 'Tardif', 'resp_prenom' => 'Luc', 'resp_email' => 'tardif@annuaire.test' ) );
wp_schedule_single_event( time() + 600, Notice_Archeomed_File::HOOK_UNE, array( (int) $pur_1 ) );
update_option( Notice_Archeomed_Export::TRAVAIL, array( 'essai' => 1 ), false );
update_option( Notice_Archeomed_Export::ATTENTE, array( 'essai' => 1 ), false );
update_option( Notice_Archeomed_Export::MANIFESTES, array( 'AM-essai' => array( 'essai' => 1 ) ), false );
wp_schedule_single_event( time() + 600, Notice_Archeomed_Export::HOOK );
$pur_noms = array( 'na_dossier_' . md5( 'essai-purge/Evreux' ) => $pur_1, 'na_dossier_' . md5( 'essai-purge/Vernon' ) => $pur_3 );
foreach ( $pur_noms as $pur_cle => $pur_id ) {
	update_option( $pur_cle, (string) $pur_id, false );
}

$pur_faux = $S_::purger_les_notices( $pur_jusqua, 'purger' );
na_verifier( false === $pur_faux['ok'] && 'private' === get_post_status( $pur_1 ) && array() === $A_::gardees()
	&& false !== get_option( Notice_Archeomed_Export::ATTENTE ),
	'sans le mot en capitales, la purge ne fait rien', $pur_faux );

$pur_pris = Notice_Archeomed_Rangement::prendre_le_verrou( Notice_Archeomed_Export::VERROU );
$pur_occupe = $S_::purger_les_notices( $pur_jusqua, 'PURGER' );
$pur_libres = array();
foreach ( array( Notice_Archeomed_Rangement::VERROU, Notice_Archeomed_Relecture::VERROU_DEPOT ) as $pur_nom ) {
	$pur_libres[ $pur_nom ] = Notice_Archeomed_Rangement::prendre_le_verrou( $pur_nom );
	Notice_Archeomed_Rangement::rendre_le_verrou( $pur_libres[ $pur_nom ], $pur_nom );
}
Notice_Archeomed_Rangement::rendre_le_verrou( $pur_pris, Notice_Archeomed_Export::VERROU );
na_verifier( $pur_pris && false === $pur_occupe['ok'] && 'private' === get_post_status( $pur_1 ) && array() === $A_::gardees()
	&& ! in_array( 0, $pur_libres, true ),
	'quand l\'export travaille, la purge attend et rend les verrous qu\'elle avait pris', array( $pur_occupe, $pur_libres ) );

$pur_fait = $S_::purger_les_notices( $pur_jusqua, ' PURGER ' );
$pur_ann  = array();
foreach ( $A_::recenser() as $pur_p ) {
	$pur_ann[ strtolower( $pur_p['courriel'] ) ] = $pur_p;
}
$pur_verrous = array();
foreach ( array( Notice_Archeomed_Rangement::VERROU, Notice_Archeomed_Export::VERROU, Notice_Archeomed_Relecture::VERROU_DEPOT ) as $pur_nom ) {
	$pur_verrous[ $pur_nom ] = Notice_Archeomed_Rangement::prendre_le_verrou( $pur_nom );
	Notice_Archeomed_Rangement::rendre_le_verrou( $pur_verrous[ $pur_nom ], $pur_nom );
}
na_verifier( true === $pur_fait['ok'] && 'trash' === get_post_status( $pur_1 ) && 'trash' === get_post_status( $pur_2 ) && 'private' === get_post_status( $pur_3 )
	&& false === wp_next_scheduled( Notice_Archeomed_File::HOOK_UNE, array( (int) $pur_1 ) ) && false === wp_next_scheduled( Notice_Archeomed_Export::HOOK )
	&& false === get_option( Notice_Archeomed_Export::TRAVAIL ) && false === get_option( Notice_Archeomed_Export::ATTENTE )
	&& array( 'AM-essai' => array( 'essai' => 1 ) ) === get_option( Notice_Archeomed_Export::MANIFESTES )
	&& ! in_array( 0, $pur_verrous, true ),
	'la purge met à la corbeille les notices de la page, non celle déposée depuis ; elle vide la file d\'export, garde les manifestes et rend les verrous',
	array( $pur_fait, get_post_status( $pur_1 ), get_post_status( $pur_3 ), $pur_verrous ) );
na_verifier( isset( $pur_ann['pur@annuaire.test'], $pur_ann['anne@annuaire.test'], $pur_ann['tardif@annuaire.test'] )
	&& 'Purgé' === $pur_ann['pur@annuaire.test']['nom'] && 'Inrap' === $pur_ann['pur@annuaire.test']['institution']
	&& array( 'PURGE-1', 'PURGE-2' ) === array_values( $pur_ann['pur@annuaire.test']['notices'] )
	&& array( 'co-auteur' => true ) === $pur_ann['anne@annuaire.test']['roles'],
	'après la purge, l\'annuaire garde les auteurs des notices purgées, avec la référence de leurs notices', $pur_ann );

wp_untrash_post( $pur_2 );
$pur_revenu = array();
foreach ( $A_::recenser() as $pur_p ) {
	$pur_revenu[ strtolower( $pur_p['courriel'] ) ] = $pur_p['notices'];
}
na_verifier( 'private' === get_post_status( $pur_2 ) && 2 === count( $pur_revenu['pur@annuaire.test'] )
	&& isset( $pur_revenu['pur@annuaire.test'][ $pur_2 ] ) && array( 'PURGE-1', 'PURGE-2' ) === array_values( $pur_revenu['pur@annuaire.test'] ),
	'une notice remise de la corbeille redevient privée et ne compte pas deux fois dans l\'annuaire', $pur_revenu );
wp_trash_post( $pur_2 );

ob_start();
$A_::onglet();
$pur_rendu = ob_get_clean();
na_verifier( false !== strpos( $pur_rendu, 'PURGE-1' ) && false !== strpos( $pur_rendu, 'Une purge des notices n’y touche pas' ),
	'l\'onglet de l\'annuaire montre les notices purgées par leur référence', $pur_rendu );

$_GET = array();
ob_start();
na_appel( $S_, 'la_purge', array( array( 'quoi' => '', 'resultat' => null ) ) );
$pur_onglet = ob_get_clean();
na_verifier( false !== strpos( $pur_onglet, 'id="na-purge"' ) && false !== strpos( $pur_onglet, 'value="purge_corbeille"' )
	&& false !== strpos( $pur_onglet, 'value="purge_effacer"' ) && false !== strpos( $pur_onglet, 'name="na_purge_jusqua" value="' . $S_::derniere_notice() . '"' )
	&& false !== strpos( $pur_onglet, 'name="na_purge_noms"' ) && false === strpos( $pur_onglet, 'checked' ),
	'l\'onglet Stockage propose la corbeille puis l\'effacement, la case des noms de dossier décochée', $pur_onglet );

$pur_mal = $S_::vider_la_corbeille_des_notices( 'effacer', true );
na_verifier( false === $pur_mal['ok'] && 'trash' === get_post_status( $pur_1 ),
	'sans le mot en capitales, la corbeille ne se vide pas', $pur_mal );
$pur_efface = $S_::vider_la_corbeille_des_notices( 'EFFACER', true );
$pur_ann2   = array_map( 'strtolower', array_column( $A_::recenser(), 'courriel' ) );
na_verifier( true === $pur_efface['ok'] && null === get_post( $pur_1 ) && null === get_post( $pur_2 ) && 'private' === get_post_status( $pur_3 )
	&& 0 === $S_::compter_au_statut( 'trash' )
	&& false === get_option( 'na_dossier_' . md5( 'essai-purge/Evreux' ) ) && (string) $pur_3 === get_option( 'na_dossier_' . md5( 'essai-purge/Vernon' ) )
	&& in_array( 'pur@annuaire.test', $pur_ann2, true ) && in_array( 'anne@annuaire.test', $pur_ann2, true ),
	'l\'effacement vide la corbeille, rend les noms de dossier des seules notices effacées, et l\'annuaire garde leurs auteurs',
	array( $pur_efface, $pur_ann2, get_post_status( $pur_1 ), get_post_status( $pur_3 ), $S_::compter_au_statut( 'trash' ),
		get_option( 'na_dossier_' . md5( 'essai-purge/Evreux' ) ), get_option( 'na_dossier_' . md5( 'essai-purge/Vernon' ) ) ) );

$pur_conf   = new Notice_Archeomed_Confidentialite();
$pur_export = $pur_conf->exporter( 'PUR@annuaire.test', 1 );
$pur_groupes = array_column( $pur_export['data'], 'group_id' );
$pur_efface2 = $pur_conf->effacer( 'pur@annuaire.test', 1 );
na_verifier( in_array( 'na_annuaire', $pur_groupes, true ) && true === $pur_efface2['items_removed']
	&& ! in_array( 'pur@annuaire.test', array_map( 'strtolower', array_column( $A_::recenser(), 'courriel' ) ), true ),
	'l\'outil de confidentialité exporte puis efface la ligne que l\'annuaire gardait', array( $pur_export, $pur_efface2 ) );

$A_::retirer( 'anne@annuaire.test', 'Second', 'Anne' );
$A_::supprimer( 'anne@annuaire.test' );
na_verifier( ! isset( $A_::gardees()['anne@annuaire.test'] ) && false === stripos( wp_json_encode( get_option( $A_::OPTION_GARDEES ) ), 'anne@' ),
	'une adresse supprimée définitivement ne reste pas écrite parmi les auteurs gardés', $A_::gardees() );

foreach ( $pur_noms as $pur_cle => $pur_id ) {
	delete_option( $pur_cle );
}
delete_option( Notice_Archeomed_Export::MANIFESTES );
delete_option( $A_::OPTION_GARDEES );
delete_option( $A_::OPTION_RETIRES );
delete_option( $A_::OPTION_SUPPRIMES );
wp_delete_post( $pur_3, true );

WP_CLI::log( 'La désactivation' );
Notice_Archeomed_Pactols::desactiver();
na_verifier( false === wp_next_scheduled( Notice_Archeomed_Pactols::HOOK_TERMES ),
	'la désactivation efface la tâche' );

foreach ( array( $vieille, $complete, $sans, $epuisee, $attend, $reveil ) as $id ) {
	wp_delete_post( $id, true );
}

if ( $GLOBALS['na_ratees'] > 0 ) {
	WP_CLI::error( $GLOBALS['na_ratees'] . ' essai(s) raté(s).' );
}
WP_CLI::success( 'Tous les essais passent.' );
