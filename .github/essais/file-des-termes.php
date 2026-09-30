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
	$copies  = array();
	$joindre = na_appel( $plugin, 'pieces_du_courriel', array( $avec_figures, $saisie_fig,
		$existants, $doc_essai, 4000, &$restees, &$legeres, &$copies ) );
	$noms    = array_map( 'basename', $joindre );
	$reste   = array_map( 'basename', $restees );
	$copies_existaient = ! empty( $copies ) && file_exists( $copies[0] );
	na_appel( $plugin, 'effacer_les_copies', array( $copies ) );
	return array( 'noms' => $noms, 'restees' => $reste, 'legeres' => count( $legeres ),
		'copies_existaient' => $copies_existaient,
		'copies_effacees' => empty( $copies ) || ! file_exists( $copies[0] ) );
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

na_verifier( array( 'notice.docx', 'orig1.jpg' ) === $sans['noms'] && array( 'orig2.jpg' ) === $sans['restees'],
	'sans version allégée : le document, puis ce qui tient ; le second original reste sur le site', $sans );
na_verifier( array( 'notice.docx', 'Pledehel_Le_Bourg_Fig_1_apercu.jpg', 'orig2.jpg' ) === $une['noms']
	&& array() === $une['restees'] && 1 === $une['legeres'],
	'la figure 1 en version allégée sous un nom lisible, la 2 en original faute d\'aperçu', $une );
na_verifier( array( 'notice.docx', 'orig1.jpg', 'Pledehel_Le_Bourg_Fig_2_apercu.jpg' ) === $ordre['noms'],
	'les figures gardent leur ordre, qu\'elles soient allégées ou non', $ordre );
na_verifier( array( 'notice.docx', 'Pledehel_Le_Bourg_Fig_2_apercu.jpg' ) === $serre['noms']
	&& array( 'orig1.jpg' ) === $serre['restees'] && 1 === $serre['legeres'],
	'une version allégée qui ne tient pas : l\'original est nommé, non la copie, et le compte est juste', $serre );
na_verifier( $une['copies_existaient'] && $une['copies_effacees'],
	'la copie faite pour le courriel existe pendant l\'envoi et s\'efface après' );
na_verifier( false !== strpos( $avis_restees, 'Fig. 2' ) && false === strpos( $avis_restees, 'orig2.jpg' )
	&& false !== strpos( $avis_restees, 'post=' . $avec_figures ),
	'le courriel nomme la figure restée par son numéro, non par le nom du fichier, et donne le lien de la notice', $avis_restees );
na_verifier( false !== strpos( $avis_legeres, '2 illustrations sont jointes en version allégée' )
	&& false !== strpos( $avis_legeres, number_format_i18n( Notice_Archeomed_Paquet::LARGEUR_APERCU ) ),
	'le courriel dit combien de figures sont allégées, et à quelle largeur', $avis_legeres );
na_verifier( $diagnostic_neutre, 'le diagnostic ne fabrique rien et ne touche pas à la notice' );
na_verifier( false === $deja_tente, 'une notice déjà tentée ne relance pas la fabrication à chaque envoi' );

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
$avis_ponct = $C::ponctuation_finale( array( 'Certaines de ces', 'structures sont datées.', 'Une fin sans point' ) );
na_verifier( 2 === count( $avis_ponct ) && false !== strpos( $avis_ponct[0], 'suivant reprend' )
	&& false !== strpos( $avis_ponct[1], 'aucune ponctuation' ),
	'le paragraphe coupé et le texte tronqué se nomment chacun', $avis_ponct );
$avis_fig = $C::appels_de_figure( 'Voir fig. 1-2 et la figure 3.', 2 );
na_verifier( 1 === count( $avis_fig ) && false !== strpos( $avis_fig[0], 'fig. 3' ),
	'un appel à une figure qui n\'est pas jointe', $avis_fig );
na_verifier( array( 'La fig. 2 n’est appelée nulle part dans le texte.' ) === $C::appels_de_figure( 'Voir fig. 1.', 2 )
	&& array() === $C::appels_de_figure( 'Aucun appel ici.', 2 ),
	'une figure jamais appelée, et rien sans appel du tout' );
$ordinaux = $C::ordinaux_fautifs( array( 'Au XIIème siècle, la 1ère phase, la 2nde tour, la Mère.' ) );
na_verifier( array( '« XIIème » s’abrège « XIIe ».', '« 1ère » s’abrège « 1re ».', '« 2nde » s’abrège « 2de ».' ) === $ordinaux,
	'les ordinaux fautifs, et « Mère » laissée tranquille', $ordinaux );
na_verifier( 1 === count( $C::dates_espacees( array( 'occupé vers 1 250 puis abandonné' ) ) ),
	'l\'année espacée' );
// Les siècles en petites capitales : tapés en capitales, en bas de casse ou
// l'ordinal en exposant, ils sortent « xii » en petites capitales et « e » en
// exposant. « ce siècle » et un « XII » sans siècle derrière ne bougent pas.
$doc_siecles = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
$siecles_xml = $doc_siecles->html_to_paragraphs( '<p>Au XIIe s., au xiiie siècle, au XIV<sup>e</sup> siècle, aux IIIe-IVe s., au Ier millénaire ; en ce siècle, la tour XII.</p>' );
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
	array( 'rang' => 1, 'pixels' => array( 1000, 800 ), 'titre' => 'Plan', 'legende' => '', 'credits' => 'X' ),
	array( 'rang' => 2, 'pixels' => array( 1800, 1200 ), 'titre' => 'Vue', 'legende' => '', 'credits' => '' ),
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
na_verifier( 2 === substr_count( $relu, 'small-caps' ) && false !== strpos( $relu, '>xii</span><sup>e</sup>' ),
	'la page de relecture montre aussi les siècles en petites capitales', $relu );

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
