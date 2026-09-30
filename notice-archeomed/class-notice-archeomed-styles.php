<?php
/**
 * La correspondance des blocs de la notice avec les styles Métopes, en
 * réglages.
 *
 * Chaque bloc — le titre, la nature, l'année, une légende, le nom d'un
 * responsable — sortait sous un style écrit dans le code. Or certaines
 * correspondances sont des questions ouvertes avec Métopes : le numéro
 * d'autorisation partage le style de l'identifiant Patriarche, le bloc de
 * responsabilité se colle au texte au lieu d'avoir son paragraphe. Les voici
 * réglables, bloc par bloc, parmi les styles que la feuille installée porte
 * vraiment, avec la correspondance actuelle par défaut.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Styles {

	/** La clé, dans l'option du plugin, qui porte les choix enregistrés. */
	const CLE = 'styles';

	/**
	 * Le choix « collé au texte » du bloc de responsabilité : il n'a pas de
	 * paragraphe à lui, il suit le dernier paragraphe de la notice.
	 */
	const COLLE_AU_TEXTE = '';

	/** Les choix lus une fois par requête. */
	private static $enregistres = null;

	/** Les styles de la feuille installée, lus une fois par requête. */
	private static $feuille = null;

	/**
	 * Le catalogue : groupes, puis blocs. « type » dit si le bloc est un
	 * paragraphe ou une suite de caractères dans un paragraphe.
	 */
	public static function catalogue() {
		return array(
			'tete' => array(
				'titre'  => 'La tête de la notice et du fascicule',
				'blocs'  => array(
					'rubrique'      => array( 'libelle' => 'Rubrique (tête de fascicule)', 'type' => 'paragraphe', 'defaut' => 'Title' ),
					'sous_rubrique' => array( 'libelle' => 'Sous-rubrique (famille d’opérations)', 'type' => 'paragraphe', 'defaut' => 'TEI_Titre 1+rubrique' ),
					'titre_notice'  => array( 'libelle' => 'Titre de la notice : commune (département). Lieu-dit', 'type' => 'paragraphe', 'defaut' => 'TEI_Titre 2+notice' ),
					'renvoi'        => array( 'libelle' => 'Renvoi vers une autre rubrique (fascicule)', 'type' => 'paragraphe', 'defaut' => 'TEI_Titre 2+notice' ),
				),
			),
			'metadonnees' => array(
				'titre'  => 'Les métadonnées de l’opération',
				'blocs'  => array(
					'nature'           => array( 'libelle' => 'Nature de l’opération', 'type' => 'paragraphe', 'defaut' => 'TEI_archeoCHR_fieldwork_method' ),
					'autres_lieux'     => array( 'libelle' => 'Autres lieux', 'type' => 'paragraphe', 'defaut' => 'TEI_keywords_subjects:geography' ),
					'periodes'         => array( 'libelle' => 'Période historique', 'type' => 'paragraphe', 'defaut' => 'TEI_archeoCHR_keywords_subjects:chronology' ),
					'annee'            => array( 'libelle' => 'Année de l’opération', 'type' => 'paragraphe', 'defaut' => 'TEI_archeoCHR_fieldwork_year' ),
					'num_autorisation' => array( 'libelle' => 'Numéro d’autorisation', 'type' => 'paragraphe', 'defaut' => 'TEI_archeoCHR_IDpatriarche' ),
					'id_patriarche'    => array( 'libelle' => 'Identifiant Patriarche', 'type' => 'paragraphe', 'defaut' => 'TEI_archeoCHR_IDpatriarche' ),
					'rapport'          => array( 'libelle' => 'Rapport final (lien)', 'type' => 'paragraphe', 'defaut' => 'TEI_archeoCHR_reportlink' ),
					'organismes'       => array( 'libelle' => 'Organisme(s) porteur(s) de l’opération', 'type' => 'paragraphe', 'defaut' => 'TEI_archeoCHR_holder' ),
					'mots_cles'        => array( 'libelle' => 'Mots-clés', 'type' => 'paragraphe', 'defaut' => 'TEI_archeoCHR_keywords_subjects' ),
				),
			),
			'texte' => array(
				'titre'  => 'Le texte et les responsabilités',
				'blocs'  => array(
					'texte'           => array( 'libelle' => 'Texte de la notice', 'type' => 'paragraphe', 'defaut' => 'Normal' ),
					'responsabilites' => array( 'libelle' => 'Bloc de responsabilité « (Responsable de l’opération : … ; …) »', 'type' => 'paragraphe', 'defaut' => self::COLLE_AU_TEXTE, 'colle' => true ),
					'responsable'     => array( 'libelle' => 'Nom du responsable', 'type' => 'caractere', 'defaut' => 'TEI_archeoCHR_name:fld' ),
					'coresponsable'   => array( 'libelle' => 'Nom du co-responsable', 'type' => 'caractere', 'defaut' => 'TEI_archeoCHR_name:fld' ),
					'coauteur'        => array( 'libelle' => 'Nom du co-auteur', 'type' => 'caractere', 'defaut' => 'TEI_archeoCHR_name:aut' ),
					'affiliation'     => array( 'libelle' => 'Institution de rattachement', 'type' => 'caractere', 'defaut' => 'TEI_archeoCHR_aff_inline' ),
				),
			),
			'figures' => array(
				'titre'  => 'Les figures',
				'blocs'  => array(
					'figure_debut'   => array( 'libelle' => 'Ouverture du bloc de figure', 'type' => 'paragraphe', 'defaut' => 'TEI_figure_start' ),
					'figure_image'   => array( 'libelle' => 'Image', 'type' => 'paragraphe', 'defaut' => 'Normal' ),
					'figure_titre'   => array( 'libelle' => 'Titre de la figure', 'type' => 'paragraphe', 'defaut' => 'TEI_figure_title' ),
					'figure_numero'  => array( 'libelle' => 'Numéro « Fig. 1 » dans le titre', 'type' => 'caractere', 'defaut' => 'TEI_figure_num_inline' ),
					'figure_legende' => array( 'libelle' => 'Légende', 'type' => 'paragraphe', 'defaut' => 'TEI_figure_caption' ),
					'figure_credits' => array( 'libelle' => 'Crédits', 'type' => 'paragraphe', 'defaut' => 'TEI_figure_credits' ),
					'figure_fin'     => array( 'libelle' => 'Fermeture du bloc de figure', 'type' => 'paragraphe', 'defaut' => 'TEI_figure_end' ),
				),
			),
			'liens' => array(
				'titre'  => 'Les liens',
				'blocs'  => array(
					'lien' => array( 'libelle' => 'Lien (ARK, adresse, rapport)', 'type' => 'caractere', 'defaut' => 'Hyperlink' ),
				),
			),
		);
	}

	/** Tous les blocs, à plat, par leur clé. */
	public static function definitions() {
		$tous = array();
		foreach ( self::catalogue() as $groupe ) {
			foreach ( $groupe['blocs'] as $cle => $bloc ) {
				$tous[ $cle ] = $bloc;
			}
		}
		return $tous;
	}

	/**
	 * Le style d'un bloc : celui que la rédaction a choisi, ou la
	 * correspondance d'origine.
	 */
	public static function de( $cle ) {
		$definitions = self::definitions();
		if ( ! isset( $definitions[ $cle ] ) ) {
			return '';
		}
		if ( null === self::$enregistres ) {
			$options = get_option( Notice_Archeomed_Settings::OPTION_NAME, array() );
			self::$enregistres = ( is_array( $options ) && isset( $options[ self::CLE ] ) && is_array( $options[ self::CLE ] ) )
				? $options[ self::CLE ] : array();
		}
		return array_key_exists( $cle, self::$enregistres )
			? (string) self::$enregistres[ $cle ] : $definitions[ $cle ]['defaut'];
	}

	/** Le bloc de responsabilité a-t-il son propre paragraphe ? */
	public static function responsabilites_a_part() {
		return self::COLLE_AU_TEXTE !== self::de( 'responsabilites' );
	}

	/**
	 * Le nom qu'une zone de blocs d'index porte dans « rend » : le style du
	 * paragraphe sans son préfixe « TEI_ », comme la chaîne le lit.
	 */
	public static function rend( $cle ) {
		return (string) preg_replace( '/^TEI_/', '', self::de( $cle ) );
	}

	/**
	 * Les styles de la feuille installée, par type, noms affichés. Le style
	 * « à supprimer » s'y ajoute : il n'est pas dans la feuille, le plugin
	 * l'injecte à l'écriture.
	 */
	public static function styles_de_la_feuille() {
		if ( null === self::$feuille ) {
			self::$feuille = array( 'paragraphe' => array(), 'caractere' => array() );
			if ( class_exists( 'Notice_Archeomed_DOCX' ) && class_exists( 'Notice_Archeomed_Pactols' ) ) {
				$doc = new Notice_Archeomed_DOCX( Notice_Archeomed_Pactols::feuille_de_style( 'docx' ) );
				if ( $doc->is_ready() ) {
					self::$feuille = array(
						'paragraphe' => $doc->noms_des_styles( 'paragraphe' ),
						'caractere'  => $doc->noms_des_styles( 'caractere' ),
					);
				}
			}
		}
		return self::$feuille;
	}

	/**
	 * Les choix soumis, nettoyés : un style doit être du bon type et exister
	 * dans la feuille, sans quoi le bloc sortirait en Normal sans rien dire.
	 * Un choix refusé garde la valeur d'avant.
	 */
	public static function nettoyer( $soumis, $avant = array() ) {
		$feuille = self::styles_de_la_feuille();
		$propres = is_array( $avant ) ? $avant : array();
		$refuses = array();
		foreach ( self::definitions() as $cle => $bloc ) {
			if ( ! isset( $soumis[ $cle ] ) ) {
				continue;
			}
			$style = (string) $soumis[ $cle ];
			$admis = in_array( $style, $feuille[ $bloc['type'] ], true )
				|| ( ! empty( $bloc['colle'] ) && self::COLLE_AU_TEXTE === $style );
			if ( ! $admis && ! empty( $feuille[ $bloc['type'] ] ) ) {
				$refuses[] = $bloc['libelle'];
				continue;
			}
			if ( $style === $bloc['defaut'] ) {
				unset( $propres[ $cle ] );   // la correspondance d'origine n'a pas à être retenue
			} else {
				$propres[ $cle ] = $style;
			}
		}
		return array( $propres, $refuses );
	}

	/** Oublie ce qui a été lu : après un enregistrement, ou dans les essais. */
	public static function oublier() {
		self::$enregistres = null;
		self::$feuille     = null;
	}
}
