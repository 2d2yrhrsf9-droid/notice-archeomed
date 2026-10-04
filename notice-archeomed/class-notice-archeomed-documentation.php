<?php
/**
 * L'onglet « Documentation » des réglages : la présentation de l'extension
 * et sa notice technique, lues sur place.
 *
 * Les deux textes vivent en Markdown dans « doc/ », où un développeur les
 * trouve avec le code et les relit dans n'importe quel éditeur. L'onglet les
 * rend en HTML par un convertisseur volontairement restreint — titres,
 * paragraphes, listes, tableaux, blocs de code, citations, liens, gras,
 * italique, code en ligne — : assez pour ces deux documents, sans
 * bibliothèque à embarquer. Le texte est échappé avant toute mise en forme ;
 * rien de ce qu'il contient ne devient du HTML actif.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Documentation {

	/** Les documents de l'onglet, par clé : fichier et intitulé. */
	const DOCUMENTS = array(
		'presentation' => array( 'fichier' => 'doc/presentation.md', 'titre' => 'Présentation' ),
		'technique'    => array( 'fichier' => 'doc/notice-technique.md', 'titre' => 'Notice technique' ),
	);

	public function __construct() {
		add_action( 'admin_head', array( $this, 'poser_le_style' ) );
	}

	/** Le document demandé dans l'adresse, ou le premier. */
	private static function document_demande() {
		$cle = isset( $_GET['doc'] ) ? sanitize_key( wp_unslash( $_GET['doc'] ) ) : '';
		return array_key_exists( $cle, self::DOCUMENTS ) ? $cle : 'presentation';
	}

	/** Le chemin d'un document livré avec l'extension. */
	public static function chemin( $cle ) {
		return isset( self::DOCUMENTS[ $cle ] ) ? plugin_dir_path( __FILE__ ) . self::DOCUMENTS[ $cle ]['fichier'] : '';
	}

	/** L'onglet : le choix du document, le document rendu, et son fichier. */
	public static function onglet() {
		$courant = self::document_demande();
		echo '<nav class="na-doc-choix" aria-label="' . esc_attr__( 'Documents', 'notice-archeomed' ) . '"><ul>';
		foreach ( self::DOCUMENTS as $cle => $document ) {
			$lien = add_query_arg( 'doc', $cle, Notice_Archeomed_Settings::url( 'documentation' ) );
			echo '<li><a href="' . esc_url( $lien ) . '"' . ( $cle === $courant ? ' aria-current="page"' : '' ) . '>'
				. esc_html( $document['titre'] ) . '</a></li>';
		}
		echo '</ul></nav>';
		$chemin = self::chemin( $courant );
		$texte  = ( '' !== $chemin && is_readable( $chemin ) ) ? (string) file_get_contents( $chemin ) : '';
		if ( '' === $texte ) {
			echo '<p>' . esc_html__( 'Ce document manque à l’installation.', 'notice-archeomed' ) . '</p>';
			return;
		}
		echo '<p class="description">' . esc_html( 'Le fichier source, en Markdown, est dans le dossier de l’extension : ' )
			. '<code>' . esc_html( self::DOCUMENTS[ $courant ]['fichier'] ) . '</code>. '
			. '<a href="' . esc_url( plugins_url( self::DOCUMENTS[ $courant ]['fichier'], __FILE__ ) ) . '" download>'
			. esc_html__( 'Le télécharger', 'notice-archeomed' ) . '</a></p>';
		echo '<div class="na-doc">' . self::rendre( $texte ) . '</div>';   // échappé par rendre()
	}

	/**
	 * Du Markdown en HTML : les blocs d'abord, ligne à ligne, puis le
	 * contenu de chaque bloc. Les titres reçoivent une ancre tirée de leur
	 * texte. Les titres du document descendent d'un niveau sous celui de la
	 * page : « # » devient un « h2 ».
	 */
	public static function rendre( $markdown ) {
		$lignes = preg_split( '/\R/u', str_replace( "\t", '    ', (string) $markdown ) );
		$html   = '';
		$n      = count( $lignes );
		$i      = 0;
		while ( $i < $n ) {
			$ligne = $lignes[ $i ];
			// Bloc de code, jusqu'à sa clôture.
			if ( preg_match( '/^```/', $ligne ) ) {
				$code = array();
				for ( ++$i; $i < $n && ! preg_match( '/^```/', $lignes[ $i ] ); ++$i ) {
					$code[] = $lignes[ $i ];
				}
				++$i;
				$html .= '<pre><code>' . esc_html( implode( "\n", $code ) ) . '</code></pre>';
				continue;
			}
			if ( '' === trim( $ligne ) ) {
				++$i;
				continue;
			}
			if ( preg_match( '/^(#{1,5})\s+(.+?)\s*#*$/u', $ligne, $m ) ) {
				$niveau = min( 6, strlen( $m[1] ) + 1 );
				$html  .= '<h' . $niveau . ' id="' . esc_attr( sanitize_title( $m[2] ) ) . '">' . self::en_ligne( $m[2] ) . '</h' . $niveau . '>';
				++$i;
				continue;
			}
			if ( preg_match( '/^\s*(-{3,}|\*{3,})\s*$/', $ligne ) ) {
				$html .= '<hr>';
				++$i;
				continue;
			}
			// Tableau : une ligne d'en-tête, puis la ligne des tirets.
			if ( false !== strpos( $ligne, '|' ) && $i + 1 < $n && preg_match( '/^\s*\|?\s*:?-{3,}/', $lignes[ $i + 1 ] ) ) {
				$entete = self::cellules( $ligne );
				$i     += 2;
				$corps  = '';
				while ( $i < $n && false !== strpos( $lignes[ $i ], '|' ) && '' !== trim( $lignes[ $i ] ) ) {
					$corps .= '<tr>';
					foreach ( self::cellules( $lignes[ $i ] ) as $cellule ) {
						$corps .= '<td>' . self::en_ligne( $cellule ) . '</td>';
					}
					$corps .= '</tr>';
					++$i;
				}
				$tete = '';
				foreach ( $entete as $cellule ) {
					$tete .= '<th scope="col">' . self::en_ligne( $cellule ) . '</th>';
				}
				// Un en-tête vide ne dit rien au lecteur d'écran : le tableau
				// n'est alors qu'une liste de paires.
				$html .= '<table class="widefat striped">'
					. ( '' !== trim( implode( '', $entete ) ) ? '<thead><tr>' . $tete . '</tr></thead>' : '' )
					. '<tbody>' . $corps . '</tbody></table>';
				continue;
			}
			// Citation.
			if ( preg_match( '/^>\s?/', $ligne ) ) {
				$cite = array();
				while ( $i < $n && preg_match( '/^>\s?(.*)$/', $lignes[ $i ], $m ) ) {
					$cite[] = $m[1];
					++$i;
				}
				$html .= '<blockquote>' . self::rendre( implode( "\n", $cite ) ) . '</blockquote>';
				continue;
			}
			// Liste, à puces ou numérotée, éléments sur plusieurs lignes et
			// sous-listes indentées compris.
			if ( preg_match( '/^(\s*)([-*]|\d+\.)\s+/', $ligne, $m ) ) {
				list( $liste, $i ) = self::liste( $lignes, $i, strlen( $m[1] ) );
				$html .= $liste;
				continue;
			}
			// Paragraphe, jusqu'à la ligne vide ou au bloc suivant.
			$para = array( trim( $ligne ) );
			for ( ++$i; $i < $n && '' !== trim( $lignes[ $i ] ) && ! self::ouvre_un_bloc( $lignes, $i ); ++$i ) {
				$para[] = trim( $lignes[ $i ] );
			}
			$html .= '<p>' . self::en_ligne( implode( ' ', $para ) ) . '</p>';
		}
		return $html;
	}

	/** Cette ligne ouvre-t-elle un autre bloc qu'un paragraphe ? */
	private static function ouvre_un_bloc( $lignes, $i ) {
		$ligne = $lignes[ $i ];
		return (bool) preg_match( '/^(```|#{1,5}\s|>|\s*([-*]|\d+\.)\s+|\s*(-{3,}|\*{3,})\s*$)/', $ligne )
			|| ( false !== strpos( $ligne, '|' ) && isset( $lignes[ $i + 1 ] ) && preg_match( '/^\s*\|?\s*:?-{3,}/', $lignes[ $i + 1 ] ) );
	}

	/**
	 * Une liste qui commence à la ligne « $i », à l'indentation « $retrait ».
	 * Rend son HTML et l'indice de la ligne qui la suit.
	 */
	private static function liste( $lignes, $i, $retrait ) {
		$n       = count( $lignes );
		$ordonne = (bool) preg_match( '/^\s*\d+\./', $lignes[ $i ] );
		$items   = array();
		while ( $i < $n ) {
			$ligne = $lignes[ $i ];
			if ( preg_match( '/^(\s*)([-*]|\d+\.)\s+(.*)$/', $ligne, $m ) && strlen( $m[1] ) === $retrait ) {
				$items[] = array( 'texte' => array( $m[3] ), 'sous' => '' );
				++$i;
				continue;
			}
			if ( empty( $items ) ) {
				break;
			}
			if ( preg_match( '/^(\s*)([-*]|\d+\.)\s+/', $ligne, $m ) && strlen( $m[1] ) > $retrait ) {
				list( $sous, $i ) = self::liste( $lignes, $i, strlen( $m[1] ) );
				$items[ count( $items ) - 1 ]['sous'] .= $sous;
				continue;
			}
			// La suite d'un élément : une ligne indentée, non vide.
			if ( '' !== trim( $ligne ) && strlen( $ligne ) - strlen( ltrim( $ligne ) ) > $retrait && ! preg_match( '/^\s*```/', $ligne ) ) {
				$items[ count( $items ) - 1 ]['texte'][] = trim( $ligne );
				++$i;
				continue;
			}
			break;
		}
		$balise = $ordonne ? 'ol' : 'ul';
		$html   = '<' . $balise . '>';
		foreach ( $items as $item ) {
			$html .= '<li>' . self::en_ligne( implode( ' ', $item['texte'] ) ) . $item['sous'] . '</li>';
		}
		return array( $html . '</' . $balise . '>', $i );
	}

	/** Les cellules d'une ligne de tableau, sans les barres de bord. */
	private static function cellules( $ligne ) {
		$ligne = trim( $ligne );
		$ligne = preg_replace( '/^\||\|$/', '', $ligne );
		return array_map( 'trim', explode( '|', $ligne ) );
	}

	/**
	 * Le contenu d'un bloc : échappé d'abord, puis le code en ligne mis à
	 * l'abri, puis liens, gras et italique. Un lien ne mène qu'en http(s) ou
	 * à une ancre de la page.
	 */
	public static function en_ligne( $texte ) {
		$texte = esc_html( $texte );
		$codes = array();
		$texte = preg_replace_callback( '/`([^`]+)`/', function ( $m ) use ( &$codes ) {
			$codes[] = '<code>' . $m[1] . '</code>';
			return "\u{E000}" . ( count( $codes ) - 1 ) . "\u{E000}";
		}, $texte );
		$texte = preg_replace_callback( '/\[([^\]]+)\]\(([^)\s]+)\)/', function ( $m ) {
			$url = html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' );
			if ( ! preg_match( '#^(https?://|\#)#', $url ) ) {
				return $m[1];
			}
			return '<a href="' . esc_url( $url ) . '">' . $m[1] . '</a>';
		}, $texte );
		$texte = preg_replace_callback( '/&lt;(https?:\/\/[^\s&]+)&gt;/', function ( $m ) {
			return '<a href="' . esc_url( $m[1] ) . '">' . $m[1] . '</a>';
		}, $texte );
		$texte = preg_replace( '/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $texte );
		$texte = preg_replace( '/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/u', '<em>$1</em>', $texte );
		// Un caractère d'usage privé, et non l'octet nul, que PHP avant 8.2
		// refuse dans un motif.
		return preg_replace_callback( '/\x{E000}(\d+)\x{E000}/u', function ( $m ) use ( $codes ) {
			return $codes[ (int) $m[1] ];
		}, $texte );
	}

	/** Le style de l'onglet, et seulement de lui. */
	public function poser_le_style() {
		if ( ! isset( $_GET['page'], $_GET['tab'] )
			|| Notice_Archeomed_Settings::PAGE_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) )
			|| 'documentation' !== sanitize_key( wp_unslash( $_GET['tab'] ) ) ) {
			return;
		}
		echo '<style>'
			. '.na-doc{max-width:52em;font-size:14px;line-height:1.6}'
			. '.na-doc h2{font-size:1.6em;margin:1.2em 0 .6em}.na-doc h3{font-size:1.3em;margin:1.6em 0 .5em}.na-doc h4{font-size:1.1em;margin:1.2em 0 .4em}'
			. '.na-doc pre{background:#f6f7f7;border:1px solid #dcdcde;padding:12px;overflow-x:auto;white-space:pre}'
			. '.na-doc code{font-size:.92em}.na-doc table{margin:.8em 0 1.2em}.na-doc td,.na-doc th{vertical-align:top}'
			. '.na-doc ul,.na-doc ol{margin-left:1.6em}.na-doc ul{list-style:disc}.na-doc ul ul{list-style:circle}'
			. '.na-doc blockquote{border-left:4px solid #dcdcde;margin:1em 0;padding:0 1em}'
			. '.na-doc-choix ul{display:flex;gap:1.4em;margin:1em 0}.na-doc-choix a[aria-current]{font-weight:600;color:#1d2327;text-decoration:none}'
			. '</style>';
	}
}
