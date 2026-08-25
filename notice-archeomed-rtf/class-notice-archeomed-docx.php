<?php
/**
 * Générateur de fichier DOCX stylé Métopes pour les notices Archéomed.
 *
 * Même principe que le générateur RTF : on ne recrée pas la feuille de
 * styles. On lit le modèle « modele-metopes.docx » livré avec le plugin,
 * on en recopie toutes les pièces (styles.xml, fontTable.xml, thème,
 * numérotation…) et l'on remplace uniquement word/document.xml par un corps
 * de document construit paragraphe par paragraphe.
 *
 * Les styles sont désignés par leur nom affiché dans Word (« TEI_Titre
 * 2+notice »), résolu vers l'identifiant interne (« TEITitre2notice ») en
 * lisant styles.xml. Remplacer le modèle suffit donc à suivre une évolution
 * du gabarit Métopes, sans toucher au code.
 *
 * L'API reproduit celle de Notice_Archeomed_RTF : add_paragraph(),
 * add_raw_paragraph(), char_run(), hyperlink(), html_to_paragraphs(),
 * write_to(). Le contenu « brut » manipulé ici est du XML WordprocessingML,
 * là où la classe RTF manipulait des séquences RTF.
 *
 * @package Notice_Archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_DOCX {

	/**
	 * Chemin du modèle .docx.
	 *
	 * @var string
	 */
	private $template = '';

	/**
	 * Nom affiché => identifiant de style, pour les styles de paragraphe.
	 *
	 * @var array
	 */
	private $styles = array();

	/**
	 * Nom affiché => identifiant de style, pour les styles de caractère.
	 *
	 * @var array
	 */
	private $char_styles = array();

	/**
	 * Paragraphes du corps, déjà encodés en WordprocessingML.
	 *
	 * @var array
	 */
	private $body = array();

	/**
	 * Liens hypertexte à déclarer dans document.xml.rels.
	 * Chaque entrée : rId => URL.
	 *
	 * @var array
	 */
	private $links = array();

	/**
	 * Message d'erreur éventuel.
	 *
	 * @var string
	 */
	private $error = '';

	public function __construct( $template_path ) {
		$this->template = $template_path;
		$this->load_template();
	}

	public function get_error() {
		return $this->error;
	}

	public function is_ready() {
		return '' === $this->error;
	}

	/**
	 * Vérifie le modèle et construit l'index des styles.
	 */
	private function load_template() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->error = "L'extension PHP ZipArchive est requise pour produire un fichier DOCX.";
			return;
		}
		if ( ! file_exists( $this->template ) || ! is_readable( $this->template ) ) {
			$this->error = 'Modèle DOCX introuvable : ' . $this->template;
			return;
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $this->template ) ) {
			$this->error = 'Modèle DOCX illisible (archive invalide).';
			return;
		}
		$styles = $zip->getFromName( 'word/styles.xml' );
		$zip->close();
		if ( false === $styles || '' === $styles ) {
			$this->error = 'Le modèle DOCX ne contient pas de feuille de styles.';
			return;
		}
		$this->index_styles( $styles );
		if ( empty( $this->styles ) ) {
			$this->error = 'Aucun style reconnu dans le modèle DOCX.';
		}
	}

	/**
	 * Construit la correspondance nom affiché => styleId à partir de styles.xml.
	 */
	private function index_styles( $xml ) {
		if ( ! preg_match_all( '#<w:style\b([^>]*)>(.*?)</w:style>#s', $xml, $matches, PREG_SET_ORDER ) ) {
			return;
		}
		foreach ( $matches as $m ) {
			$attrs = $m[1];
			$inner = $m[2];
			if ( ! preg_match( '#w:styleId="([^"]+)"#', $attrs, $id ) ) {
				continue;
			}
			if ( ! preg_match( '#<w:name\s+w:val="([^"]+)"#', $inner, $name ) ) {
				continue;
			}
			$type = preg_match( '#w:type="([^"]+)"#', $attrs, $t ) ? $t[1] : 'paragraph';
			$label = html_entity_decode( $name[1], ENT_QUOTES | ENT_XML1, 'UTF-8' );
			if ( 'character' === $type ) {
				$this->char_styles[ $label ] = $id[1];
			} elseif ( 'paragraph' === $type ) {
				$this->styles[ $label ] = $id[1];
			}
		}
	}

	/**
	 * Échappe le texte pour insertion dans du XML.
	 */
	public static function esc( $text ) {
		$text = (string) $text;
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		return htmlspecialchars( $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/**
	 * Encapsule du texte dans un <w:t>, en préservant les espaces de bord et
	 * en convertissant les sauts de ligne en <w:br/>.
	 */
	private static function text_nodes( $escaped_text ) {
		$parts = explode( "\n", $escaped_text );
		$out   = '';
		foreach ( $parts as $i => $part ) {
			if ( $i > 0 ) {
				$out .= '<w:br/>';
			}
			if ( '' !== $part ) {
				$out .= '<w:t xml:space="preserve">' . $part . '</w:t>';
			}
		}
		return $out;
	}

	/**
	 * Construit un run (<w:r>) à partir d'une description de segment.
	 */
	private function render_run( $run ) {
		if ( is_string( $run ) ) {
			$run = array( 'text' => $run );
		}
		if ( ! empty( $run['raw'] ) ) {
			return $run['raw'];
		}
		$text = isset( $run['text'] ) ? self::esc( $run['text'] ) : '';
		if ( '' === $text ) {
			return '';
		}
		$props = '';
		if ( ! empty( $run['cs'] ) && isset( $this->char_styles[ $run['cs'] ] ) ) {
			$props .= '<w:rStyle w:val="' . self::esc( $this->char_styles[ $run['cs'] ] ) . '"/>';
		}
		if ( ! empty( $run['b'] ) ) {
			$props .= '<w:b/>';
		}
		if ( ! empty( $run['i'] ) ) {
			$props .= '<w:i/>';
		}
		if ( ! empty( $run['sup'] ) ) {
			$props .= '<w:vertAlign w:val="superscript"/>';
		}
		if ( ! empty( $run['sub'] ) ) {
			$props .= '<w:vertAlign w:val="subscript"/>';
		}
		$rpr = '' !== $props ? '<w:rPr>' . $props . '</w:rPr>' : '';
		return '<w:r>' . $rpr . self::text_nodes( $text ) . '</w:r>';
	}

	/**
	 * Renvoie le bloc <w:pPr> portant le style de paragraphe demandé.
	 */
	private function paragraph_props( $style_name ) {
		if ( ! isset( $this->styles[ $style_name ] ) ) {
			return '';
		}
		return '<w:pPr><w:pStyle w:val="' . self::esc( $this->styles[ $style_name ] ) . '"/></w:pPr>';
	}

	/**
	 * Ajoute un paragraphe stylé. $runs est une chaîne de XML déjà construite
	 * ou un tableau de segments.
	 */
	public function add_paragraph( $style_name, $runs ) {
		$xml = '<w:p>' . $this->paragraph_props( $style_name );
		if ( is_array( $runs ) ) {
			foreach ( $runs as $run ) {
				$xml .= $this->render_run( $run );
			}
		} else {
			$xml .= (string) $runs;
		}
		$xml         .= '</w:p>';
		$this->body[] = $xml;
	}

	/**
	 * Ajoute un paragraphe dont le contenu est déjà du WordprocessingML.
	 */
	public function add_raw_paragraph( $style_name, $xml_content ) {
		$this->body[] = '<w:p>' . $this->paragraph_props( $style_name ) . $xml_content . '</w:p>';
	}

	/**
	 * Rend du texte sans mise en forme sous la forme d'un run, seule façon
	 * valide d'insérer du texte dans un paragraphe WordprocessingML.
	 */
	public function plain( $text ) {
		return $this->render_run( array( 'text' => $text ) );
	}

	/**
	 * Construit un run portant un style de caractère Métopes.
	 */
	public function char_run( $style_name, $text ) {
		return $this->render_run( array( 'cs' => $style_name, 'text' => $text ) );
	}

	/**
	 * Construit un lien hypertexte. La relation est enregistrée pour être
	 * écrite dans document.xml.rels au moment de l'assemblage.
	 */
	public function hyperlink( $url, $label ) {
		$rid                 = 'rIdNA' . ( count( $this->links ) + 1 );
		$this->links[ $rid ] = $url;
		$style               = isset( $this->char_styles['Hyperlink'] )
			? '<w:rStyle w:val="' . self::esc( $this->char_styles['Hyperlink'] ) . '"/>'
			: '';
		return '<w:hyperlink r:id="' . $rid . '">'
			. '<w:r><w:rPr>' . $style . '</w:rPr>'
			. self::text_nodes( self::esc( $label ) )
			. '</w:r></w:hyperlink>';
	}

	/**
	 * Convertit le HTML restreint de l'éditeur en une liste de paragraphes,
	 * chacun rendu sous forme de runs WordprocessingML.
	 */
	public function html_to_paragraphs( $html ) {
		$html = (string) $html;
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return array();
		}
		$html   = preg_replace( '#<br\s*/?>#i', "\n", $html );
		$html   = preg_replace( '#</p>#i', "\x00", $html );
		$html   = preg_replace( '#<p[^>]*>#i', '', $html );
		$chunks = explode( "\x00", $html );
		$out    = array();
		foreach ( $chunks as $chunk ) {
			if ( '' === trim( wp_strip_all_tags( $chunk ) ) ) {
				continue;
			}
			$out[] = $this->inline_html_to_runs( $chunk );
		}
		return $out;
	}

	/**
	 * Convertit les balises en ligne d'un fragment HTML en runs. Le formatage
	 * est cumulatif : une pile suit les balises ouvertes, et chaque portion de
	 * texte est émise avec l'ensemble des attributs actifs.
	 */
	private function inline_html_to_runs( $fragment ) {
		$known = array(
			'strong' => 'b',
			'b'      => 'b',
			'em'     => 'i',
			'i'      => 'i',
			'sup'    => 'sup',
			'sub'    => 'sub',
		);
		$parts = preg_split( '#(<[^>]+>)#', $fragment, -1, PREG_SPLIT_DELIM_CAPTURE );
		$stack = array();
		$xml   = '';
		foreach ( $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( '<' === $part[0] ) {
				if ( preg_match( '#^</\s*([a-zA-Z0-9]+)#', $part, $m ) ) {
					$tag = strtolower( $m[1] );
					if ( isset( $known[ $tag ] ) ) {
						$idx = array_search( $known[ $tag ], $stack, true );
						if ( false !== $idx ) {
							array_splice( $stack, $idx, 1 );
						}
					}
				} elseif ( preg_match( '#^<\s*([a-zA-Z0-9]+)#', $part, $m ) ) {
					$tag = strtolower( $m[1] );
					if ( isset( $known[ $tag ] ) && ! preg_match( '#/\s*>$#', $part ) ) {
						$stack[] = $known[ $tag ];
					}
				}
				continue;
			}
			$text = html_entity_decode( $part, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( '' === $text ) {
				continue;
			}
			$run = array( 'text' => $text );
			foreach ( $stack as $attr ) {
				$run[ $attr ] = true;
			}
			$xml .= $this->render_run( $run );
		}
		return $xml;
	}

	/**
	 * Assemble le document et l'écrit sur disque. Toutes les pièces du modèle
	 * sont recopiées ; seuls document.xml et ses relations sont régénérés.
	 */
	public function write_to( $dir, $basename ) {
		if ( ! $this->is_ready() ) {
			return '';
		}
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			$this->error = 'Répertoire temporaire non accessible en écriture.';
			return '';
		}
		$path = trailingslashit( $dir ) . $basename;

		$src = new ZipArchive();
		if ( true !== $src->open( $this->template ) ) {
			$this->error = 'Modèle DOCX illisible au moment de l\'assemblage.';
			return '';
		}
		// On récupère la mise en page (format de page, marges) du modèle.
		$original   = $src->getFromName( 'word/document.xml' );
		$sect       = '';
		if ( is_string( $original ) && preg_match( '#<w:sectPr\b.*?</w:sectPr>#s', $original, $m ) ) {
			$sect = $m[0];
		}
		$rels_src = $src->getFromName( 'word/_rels/document.xml.rels' );

		$dest = new ZipArchive();
		if ( true !== $dest->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			$src->close();
			$this->error = 'Création du fichier DOCX impossible.';
			return '';
		}
		for ( $i = 0; $i < $src->numFiles; $i++ ) {
			$name = $src->getNameIndex( $i );
			if ( 'word/document.xml' === $name || 'word/_rels/document.xml.rels' === $name ) {
				continue; // Régénérés ci-dessous.
			}
			$dest->addFromString( $name, $src->getFromIndex( $i ) );
		}
		$src->close();

		$dest->addFromString( 'word/document.xml', $this->document_xml( $sect ) );
		$dest->addFromString( 'word/_rels/document.xml.rels', $this->rels_xml( $rels_src ) );
		$dest->close();

		return $path;
	}

	/**
	 * Corps du document.
	 */
	private function document_xml( $sect_pr ) {
		$ns = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
			. ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<w:document ' . $ns . '><w:body>'
			. implode( '', $this->body )
			. $sect_pr
			. '</w:body></w:document>';
	}

	/**
	 * Relations du document : celles du modèle, moins ses anciens liens
	 * hypertexte, plus ceux que nous venons de créer.
	 */
	private function rels_xml( $original ) {
		$hyperlink_type = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink';
		$kept           = '';
		if ( is_string( $original ) && '' !== $original ) {
			if ( preg_match_all( '#<Relationship\b[^>]*/>#', $original, $m ) ) {
				foreach ( $m[0] as $rel ) {
					// On écarte les liens du modèle : ils ne sont plus référencés.
					if ( false !== strpos( $rel, $hyperlink_type ) ) {
						continue;
					}
					$kept .= $rel;
				}
			}
		}
		$new = '';
		foreach ( $this->links as $rid => $url ) {
			$new .= '<Relationship Id="' . self::esc( $rid ) . '"'
				. ' Type="' . $hyperlink_type . '"'
				. ' Target="' . self::esc( $url ) . '"'
				. ' TargetMode="External"/>';
		}
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. $kept . $new . '</Relationships>';
	}

	/**
	 * Liste des styles de paragraphe reconnus, utile au diagnostic.
	 */
	public function known_styles() {
		return array_keys( $this->styles );
	}

	/**
	 * Liste des styles de caractère reconnus.
	 */
	public function known_char_styles() {
		return array_keys( $this->char_styles );
	}
}
