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
na_verifier( 2 === substr_count( $relu, 'small-caps' ) && false !== strpos( $relu, '>xii</span><sup>e</sup>' )
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
na_verifier( 3 === substr_count( $relu_xii, 'small-caps' ) && false !== strpos( $relu_xii, "<sup>e</sup>\u{00A0}s.</p>" ),
	'la page de relecture aussi, et « s. » prend son insécable', $relu_xii );
$courriel_xii = na_appel( $plugin, 'build_notice', array( array_merge( $saisie_styles, array(
	'texte_notice' => '<p>Le mur du XIIe siècle et le <span class="na-pc">Moyen Âge</span> : fin.</p>', 'reference' => 'REFXII' ) ) ) );
na_verifier( false !== strpos( $courriel_xii, '<span style="font-variant:small-caps">xii</span><sup>e</sup>' )
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
$illisible = na_notice( array_merge( $saisie_styles, array( 'resp_email' => '', 'illustrations' => 'un ancien champ libre' ) ) );
foreach ( array( '_na_etat' => 'en_attente', '_na_notice' => '<p>La notice.</p>', '_na_document' => '' ) as $cle => $valeur ) {
	update_post_meta( $illisible, $cle, $valeur );
}
$issue_illisible = $plugin->expedier_de_la_file( $illisible );
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
