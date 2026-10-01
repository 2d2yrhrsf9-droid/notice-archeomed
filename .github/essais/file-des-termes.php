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
	$noms    = array_values( array_map( 'basename', $joindre ) );
	$reste   = array_map( 'basename', $restees );
	$copies_existaient = ! empty( $copies ) && file_exists( $copies[0] );
	na_appel( $plugin, 'effacer_les_copies', array( $copies ) );
	return array( 'noms' => $noms, 'restees' => $reste, 'legeres' => count( $legeres ),
		'cles' => array_values( array_filter( array_keys( $joindre ), 'is_string' ) ),
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
na_verifier( array( 'Pledehel_Le_Bourg_Fig_2.jpg' ) === $une['cles'],
	'un original joint part sous le nom de sa figure, non sous son nom tiré au sort', $une );
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

WP_CLI::log( 'Le titre et les figures sous les normes' );
$doc_titre = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
$lieu_dit_xml = na_appel( $plugin, 'run_xml', array( $doc_titre, array( 'text' => "l'enceinte du XIIe siècle", 'i' => true ) ) );
na_verifier( false !== strpos( $lieu_dit_xml, "l\u{2019}enceinte" ) && false !== strpos( $lieu_dit_xml, '<w:smallCaps/>' )
	&& 0 === substr_count( $lieu_dit_xml, '<w:r><w:t' ) && false !== strpos( $lieu_dit_xml, '<w:i/>' ),
	'le lieu-dit du titre prend apostrophe et siècles, en gardant son italique', $lieu_dit_xml );
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
$erreur_fasc = '';
$fascicule   = na_appel( $plugin, 'fabriquer_le_fascicule', array( 'II. Constructions et habitats ecclésiastiques', array(
	$fasc_d( 'Bayeux', 'II. Constructions et habitats ecclésiastiques' ), $fasc_d( 'Falaise', 'II. Constructions et habitats ecclésiastiques' ) ), &$erreur_fasc ) );
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
wp_delete_post( $renvoyee, true );
na_verifier( 1 === substr_count( $xml_fasc, 'II. 1. – Opérations de terrain' ) && false === strpos( $xml_fasc, '>I. 1. –' )
	&& false !== strpos( $xml_fasc, 'Voir dans la rubrique' ),
	'un renvoi se range sous la sous-rubrique du fascicule, sans en ouvrir une fausse', '' !== $erreur_fasc ? $erreur_fasc : substr_count( $xml_fasc, '1. –' ) );

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
	$defi = na_appel( $plugin, 'defi_du_puzzle' );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SERVER['HTTP_REFERER']   = 'https://phishing.example/depot/';
	$_POST = array(
		'notice_archeomed_envoi' => '1', 'notice_archeomed_nonce' => wp_create_nonce( 'notice_archeomed_submit' ),
		'na_ts' => (string) ( time() - 30 ), 'na_curseur' => (string) $defi['cible'], 'na_preuve' => $defi['preuve'],
		'rubrique_principale' => 'I. Constructions et habitats civils', 'commune' => array( 'Caen' ), 'commune_ark' => array( '' ),
		'departement' => 'Calvados', 'lieu_dit' => 'Château ' . $mode, 'annee' => '2025', 'nature' => array( 'Fouille préventive' ),
		'organisme' => array( 'Inrap' ), 'resp_prenom' => 'Aude', 'resp_nom' => 'Ferrand', 'resp_email' => 'aude.' . $mode . '@example.org',
		'resp_inst' => 'Inrap', 'texte_notice' => '<p>Le mur du XIIe siècle.</p>',
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
na_verifier( $differe['id'] > 0 && 'en_attente' === $differe_etat['etat'] && '' !== $differe_etat['document']
	&& $differe_etat['word_fait'] && '' === $differe_etat['preparation'] && $differe_etat['prevue']
	&& 0 === $differe_etat['courriels'] && false !== strpos( $differe['vers'], 'notice_envoyee=1' ),
	'en différé : la notice est inscrite, son Word fait, la préparation levée, l\'envoi programmé, rien d\'expédié', array( $differe, $differe_etat ) );
na_verifier( $immediat['id'] > 0 && 'envoyee' === $immediat_etat['etat'] && 2 === count( $deposes )
	&& isset( $deposes[0] ) && false !== strpos( $deposes[0]['objet'], 'réf. ' . $immediat['ref'] )
	&& 1 === count( $deposes[0]['pieces'] ) && '.docx' === substr( $deposes[0]['pieces'][0], -5 ),
	'en immédiat : la notice est inscrite comme en différé, et part par la file, Word joint', array( $immediat, $immediat_etat, $deposes ) );
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
// fichiers de 2 Mo font 6 Mo, non les 20 du plafond général.
$deux_mo = function () {
	return 2 * MB_IN_BYTES;
};
add_filter( 'upload_size_limit', $deux_mo, 99 );
$rendu_limite = $plugin->render_form();
remove_filter( 'upload_size_limit', $deux_mo, 99 );
na_verifier( false !== strpos( $rendu_limite, '6&nbsp;Mo en tout, 2&nbsp;Mo par fichier' ) && false === strpos( $rendu_limite, '20&nbsp;Mo en tout' ),
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
$defi_forge = na_appel( $plugin, 'defi_du_puzzle' );
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'notice_archeomed_envoi' => '1', 'notice_archeomed_nonce' => wp_create_nonce( 'notice_archeomed_submit' ),
	'na_ts' => (string) ( time() - 30 ), 'na_curseur' => (string) $defi_forge['cible'], 'na_preuve' => $defi_forge['preuve'],
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
na_verifier( 'Plan des murs' === Notice_Archeomed_Controles::texte_alternatif( array( 'rang' => 1, 'titre' => 'Plan', 'alt' => 'Plan des murs' ) )
	&& 'Plan général' === Notice_Archeomed_Controles::texte_alternatif( array( 'rang' => 1, 'titre' => 'Fig. 1 : Plan général' ) ),
	'une notice d\'avant le champ retombe sur le titre de la figure' );
$avis_alt = Notice_Archeomed_Controles::figures( array(
	array( 'rang' => 1, 'titre' => 'Plan', 'legende' => '', 'credits' => 'A', 'alt' => str_repeat( 'mur ', 40 ) ),
	array( 'rang' => 2, 'titre' => 'Fig. 2 : Vue du fossé', 'legende' => '', 'credits' => 'A', 'alt' => 'Vue du fossé' ),
	array( 'rang' => 3, 'titre' => 'Coupe', 'legende' => 'Les couches en rouge.', 'credits' => 'A', 'alt' => 'Photo de la coupe' ),
	array( 'rang' => 4, 'titre' => 'Mur', 'legende' => '', 'credits' => 'A', 'alt' => str_repeat( 'mur ', 30 ) ) ) );
na_verifier( 1 === count( preg_grep( '/Fig\. 1\x{00A0}: le texte alternatif fait 159\x{00A0}caractères, pour 150/u', $avis_alt ) )
	&& 0 === count( preg_grep( '/Fig\. 4.*fait/u', $avis_alt ) ),
	'au-delà de 150 caractères, un avis, sans refus ; en deçà, rien', $avis_alt );
na_verifier( 1 === count( preg_grep( '/Fig\. 2.*reprend le titre/u', $avis_alt ) )
	&& 1 === count( preg_grep( '/Fig\. 3.*commence par «\x{00A0}Photo de/u', $avis_alt ) )
	&& 1 === count( preg_grep( '/Fig\. 3.*«\x{00A0}en rouge\x{00A0}».*seule couleur/u', $avis_alt ) ),
	'un avis quand le texte alternatif reprend le titre, commence par « Photo de », ou que la couleur porte seule l\'information', $avis_alt );
$_POST = array( 'illus_titre' => array( 'Plan' ), 'illus_alt' => array( "Plan\n des   murs\t" . str_repeat( 'x', 400 ) ) );
$_FILES = array( 'illustrations' => array( 'name' => array( 'plan.jpg' ), 'error' => array( 0 ) ) );
$coupee = na_appel( $plugin, 'collect_illustrations' );
$_POST = array();
$_FILES = array();
na_verifier( 300 === mb_strlen( $coupee[0]['alt'], 'UTF-8' ) && 0 === strpos( $coupee[0]['alt'], 'Plan des murs x' ),
	'le texte alternatif tient sur une ligne et se coupe à 300 caractères', $coupee[0]['alt'] );
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
na_verifier( 2 === count( preg_grep( '/^naasupprimer (Description détaillée|Le fossé longe)/u', $suite_alt ) )
	&& 0 === strpos( (string) end( $suite_alt ), 'TEIfigureend' ) && 0 === strpos( $suite_alt[ count( $suite_alt ) - 3 ], 'naasupprimer Description détaillée' )
	&& 0 === strpos( $suite_alt[ count( $suite_alt ) - 4 ], 'TEIfigurecredits' ),
	'sans style réglé, la description détaillée part « à supprimer », à la fin du bloc de figure', $suite_alt );
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
	&& array( 'TEIfigurecaption Le plan.', 'TEIfigurecredits DAO A.', 'TEIfigurealttext Plan des murs & du fossé',
		'TEIfigurealttext Deux murs parallèles, orientés nord-sud.', 'TEIfigurealttext Le fossé longe le mur ouest.' )
		=== array_slice( $suite_alt2, -6, 5 )
	&& 0 === strpos( (string) end( $suite_alt2 ), 'TEIfigureend' ) && 3 === substr_count( $xml_alt2, 'TEIfigurealttext' ),
	'réglé, le texte alternatif puis la description ont leur paragraphe stylé à la fin du bloc, juste avant sa fermeture ; une figure ancienne n\'en a pas',
	$suite_alt2 );
$relu_alt = na_appel( $plugin, 'page_de_relecture', array( 'I', array( $saisie_alt ) ) );
$courriel_alt = na_appel( $plugin, 'illustrations_block', array( $saisie_alt ) );
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

WP_CLI::log( 'Les avis d\'accessibilité des figures' );
$C = 'Notice_Archeomed_Controles';
$avis_de = function ( $item ) use ( $C ) {
	return $C::accessibilite_de_la_figure( array_merge( array( 'rang' => 1, 'titre' => '', 'legende' => '', 'credits' => 'A', 'description' => 'Décrite.' ), $item ) );
};
$a_la_legende = function ( $alt, $legende ) use ( $avis_de ) {
	return 1 === count( preg_grep( '/reprend la légende/u', $avis_de( array( 'alt' => $alt, 'legende' => $legende ) ) ) );
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
	$sans_contenu[ $vide_de_sens ] = count( preg_grep( '/ne dit pas ce que montre l’image/u', $avis_de( array( 'alt' => $vide_de_sens ) ) ) );
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
$recommandee = function ( $item ) use ( $avis_de ) {
	$avis = preg_grep( '/description détaillée recommandée/u', $avis_de( array_merge( array( 'description' => '' ), $item ) ) );
	return empty( $avis ) ? '' : (string) reset( $avis );
};
na_verifier( false !== strpos( $recommandee( array( 'titre' => 'Coupe stratigraphique du fossé', 'alt' => 'Couches de remblai sur le fond du fossé' ) ), "«\u{00A0}coupe\u{00A0}»" )
	&& false !== strpos( $recommandee( array( 'legende' => 'Relevé de l’élévation nord.', 'alt' => 'Mur à trois assises' ) ), "«\u{00A0}relevé\u{00A0}»" )
	&& false !== strpos( $recommandee( array( 'alt' => 'Histogrammes des datations' ) ), "«\u{00A0}histogramme\u{00A0}»" )
	&& '' === $recommandee( array( 'titre' => 'Vue du foyer', 'alt' => 'Foyer contre la paroi nord' ) )
	&& '' === $recommandee( array( 'titre' => 'Coupe stratigraphique', 'alt' => 'Couches', 'description' => 'Trois couches.' ) ),
	'un plan, une coupe, un relevé, un graphique sans description détaillée : elle est recommandée ; décrite, rien' );
$figures_a11y = array(
	array( 'rang' => 1, 'titre' => 'Coupe du fossé', 'legende' => 'Fig. 1. Coupe du fossé nord.', 'credits' => 'A', 'alt' => 'Coupe du fossé nord' ),
	array( 'rang' => 2, 'titre' => 'Vue', 'legende' => '', 'credits' => 'A', 'alt' => 'DSC_0042.JPG', 'description' => 'x' ) );
$avis_tous = $C::figures( $figures_a11y );
na_verifier( 1 === count( preg_grep( "/^Fig\\. 1\x{00A0}: le texte alternatif reprend la légende\x{00A0}; il ne la répète pas, il restitue l’information visuelle utile\\.$/u", $avis_tous ) )
	&& 1 === count( preg_grep( "/^Fig\\. 1\x{00A0}: description détaillée recommandée \\(«\x{00A0}coupe\x{00A0}»\\)/u", $avis_tous ) )
	&& 1 === count( preg_grep( "/^Fig\\. 2\x{00A0}: le texte alternatif «\x{00A0}DSC_0042\\.JPG\x{00A0}» est un nom de fichier/u", $avis_tous ) ),
	'au serveur, chaque avis suit « Fig. N : », dans la phrase même que reçoit le navigateur', $avis_tous );
$regles = $C::regles_d_accessibilite();
$motifs_js_sans_ucp = true;
foreach ( $regles['motifs'] as $motif ) {
	$motifs_js_sans_ucp = $motifs_js_sans_ucp && false === strpos( $motif, '(*' ) && false === strpos( $motif, '(?<' ) && false === strpos( $motif, '\\p' );
}
na_verifier( $regles['phrases'] === $C::phrases_d_accessibilite() && true === $regles['actif'] && 150 === $regles['conseille'] && 300 === $regles['max']
	&& $motifs_js_sans_ucp && in_array( 'élévation', $regles['complexes'], true ),
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
	&& 4 === count( $avis_d_avant ),
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
	array( 'rang' => 1, 'titre' => 'Plan', 'legende' => 'Plan des vestiges.', 'credits' => 'DAO A.', 'alt' => 'Plan des murs',
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
	&& false !== strpos( $rendu_encart, 'À vérifier' ) && false !== strpos( $rendu_encart, 'Description détaillée recommandée' )
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
$vers_fiche = $jouer_la_fiche( array( 'post' => $fiche_a11y, '_wpnonce' => $nonce_fiche,
	'na_alt' => wp_slash( array( 1 => " Deux murs parallèles, <b>orientés</b>\n nord-sud ", 2 => 'L. <5 m ; fossé en V' . str_repeat( ' x', 200 ), 9 => 'Figure absente' ) ),
	'na_description' => wp_slash( array( 1 => "Le mur ouest.\n\n\nLe mur est.", 2 => '' ) ) ) );
$corrigee = get_post_meta( $fiche_a11y, '_na_donnees', true );
$historique = get_post_meta( $fiche_a11y, $A::HISTORIQUE, false );
$vers_rien = $jouer_la_fiche( array( 'post' => $fiche_a11y, '_wpnonce' => $nonce_fiche, 'na_alt' => array( 1 => 'Deux murs parallèles, orientés nord-sud' ) ) );
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
	&& false !== strpos( $xml_v, 'Matière à choisir (A, B ou C)' ),
	'le fascicule V se range par matière, puis par famille, puis par commune ; une notice sans matière reste sans lettre, et le dit', $suite_v );
na_verifier( 0 === substr_count( $xml_v, 'Matière à choisir' ) - 1, 'la ligne « matière à choisir » ne vaut que pour la notice sans matière' );
$manque_v = na_appel( $plugin, 'champs_manquants', array( $notice_v( 'Caen', 'Fouille préventive', '' ) ) );
$manque_i = na_appel( $plugin, 'champs_manquants', array( array_merge( $saisie_styles, array( 'rubrique_principale' => $nouvelle_i, 'rubrique_matiere' => '' ) ) ) );
na_verifier( in_array( 'rubrique_matiere', $manque_v, true ) && ! in_array( 'rubrique_matiere', $manque_i, true )
	&& ! in_array( 'rubrique_matiere', na_appel( $plugin, 'champs_manquants', array( $notice_v( 'Caen', 'Fouille préventive', 'B' ) ) ), true ),
	'la matière est obligatoire pour la rubrique V, et pour elle seule', array( $manque_v, $manque_i ) );
$courriel_v = na_appel( $plugin, 'build_notice', array( array_merge( $notice_v( 'Caen', 'Fouille préventive', 'B' ), array( 'reference' => 'REFV01' ) ) ) );
na_verifier( false !== strpos( $courriel_v, 'Matière' ) && false !== strpos( $courriel_v, 'B – Carrières, mines et métallurgie' ),
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
list( $audit_propre, $audit_refuses ) = $A::nettoyer_l_audit( array( 'taux' => '87,5 %', 'date' => '2026-09-30', 'lien' => 'https://ara.numerique.gouv.fr/rapport/x' ) );
list( $audit_faux, $refuses_faux ) = $A::nettoyer_l_audit( array( 'taux' => '120', 'date' => '2026-02-30', 'lien' => 'javascript:alert(1)' ) );
na_verifier( array( 'taux' => '87.5', 'date' => '2026-09-30', 'lien' => 'https://ara.numerique.gouv.fr/rapport/x' ) === $audit_propre && array() === $audit_refuses
	&& array( 'taux', 'date', 'lien' ) === $refuses_faux && array( 'taux' => '', 'date' => '', 'lien' => '' ) === $audit_faux,
	'l\'audit Ara se garde : taux à virgule, date du calendrier, lien http(s) ; le reste est écarté', array( $audit_propre, $refuses_faux ) );
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
