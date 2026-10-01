<?php
/**
 * Générateur de fichier RTF stylé Métopes (chaîne d'édition XML créée par le Pôle document numérique et l'infrastructure Métopes de l'université de Caen Normandie, https://www.metopes.fr) — le repli, non la voie normale.
 *
 * **La feuille de styles de référence est « modele-metopes.docx »**, et le
 * document joint à chaque notice est un DOCX : c'est lui que la rédaction
 * reçoit et que la chaîne Métopes traite. Ce générateur-ci ne sert que sur un
 * hébergement dépourvu de l'extension ZipArchive, sans laquelle un DOCX — qui
 * est une archive zip — ne peut pas se fabriquer. Le RTF permet alors de
 * livrer tout de même un document stylé plutôt que rien.
 *
 * « modele-metopes.rtf » est donc l'exact pendant du gabarit DOCX, enregistré
 * dans l'autre format : mêmes styles, mêmes noms. Les deux se remplacent
 * ensemble quand Métopes fait évoluer sa feuille.
 *
 * Principe : on ne réinvente pas la feuille de styles. On lit le fichier
 * modèle « modele-metopes.rtf » livré avec le plugin, on en extrait l'en-tête
 * (fonttbl, colortbl, stylesheet…) jusqu'à la fin de la table des styles,
 * puis on y ajoute un corps de document construit paragraphe par paragraphe.
 * Les paragraphes référencent les styles par leur index (\sNNN), index qui est
 * résolu dynamiquement depuis la table des styles du modèle à partir du nom
 * du style. Si Métopes fait évoluer sa feuille de styles, il suffit de
 * remplacer le fichier modèle.
 *
 * @package Notice_Archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_RTF {

	/**
	 * En-tête RTF du modèle (jusqu'à la fermeture de \stylesheet incluse).
	 *
	 * @var string
	 */
	private $header = '';

	/**
	 * Correspondance nom de style => index numérique (ex. « TEI_Titre 2+notice » => 231).
	 *
	 * @var array
	 */
	private $styles = array();

	/**
	 * Correspondance nom de style de caractère => index (ex. « Hyperlink » => 17).
	 *
	 * @var array
	 */
	private $char_styles = array();

	/**
	 * Paragraphes du corps, déjà encodés en RTF.
	 *
	 * @var array
	 */
	private $body = array();

	/**
	 * Message d'erreur si le modèle est illisible.
	 *
	 * @var string
	 */
	private $error = '';

	public function __construct( $template_path ) {
		$this->load_template( $template_path );
	}

	public function get_error() {
		return $this->error;
	}

	public function is_ready() {
		return '' === $this->error;
	}

	/**
	 * Lit le modèle et en extrait en-tête + index des styles.
	 */
	private function load_template( $path ) {
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			$this->error = 'Modèle RTF introuvable : ' . $path;
			return;
		}
		$raw = file_get_contents( $path );
		if ( false === $raw || '' === $raw ) {
			$this->error = 'Modèle RTF illisible.';
			return;
		}
		$start = strpos( $raw, '{\stylesheet' );
		if ( false === $start ) {
			$this->error = 'Table des styles absente du modèle RTF.';
			return;
		}
		$end = $this->matching_brace( $raw, $start );
		if ( false === $end ) {
			$this->error = 'Table des styles malformée dans le modèle RTF.';
			return;
		}
		$this->header    = substr( $raw, 0, $end + 1 );
		$stylesheet      = substr( $raw, $start, $end - $start + 1 );
		$this->index_styles( $stylesheet );
		if ( empty( $this->styles ) ) {
			$this->error = 'Aucun style reconnu dans le modèle RTF.';
		}
	}

	/**
	 * Renvoie la position de l'accolade fermante correspondant à celle en $open,
	 * en ignorant les accolades échappées (\{ et \}).
	 */
	private function matching_brace( $s, $open ) {
		$depth = 0;
		$len   = strlen( $s );
		for ( $i = $open; $i < $len; $i++ ) {
			$c = $s[ $i ];
			if ( '\\' === $c ) {
				$i++; // On saute le caractère échappé.
				continue;
			}
			if ( '{' === $c ) {
				$depth++;
			} elseif ( '}' === $c ) {
				$depth--;
				if ( 0 === $depth ) {
					return $i;
				}
			}
		}
		return false;
	}

	/**
	 * Construit la table nom de style => index à partir de la \stylesheet.
	 * Chaque définition se termine par « NomDuStyle;} ».
	 */
	private function index_styles( $stylesheet ) {
		$len   = strlen( $stylesheet );
		$start = 0;
		while ( true ) {
			$open = strpos( $stylesheet, '{', $start + 1 );
			if ( false === $open ) {
				break;
			}
			$close = $this->matching_brace( $stylesheet, $open );
			if ( false === $close ) {
				break;
			}
			$def = substr( $stylesheet, $open, $close - $open + 1 );
			// Index : \sNNN pour un style de paragraphe, \csNNN pour un style de caractère.
			// Attention : Word insère parfois une espace ou un saut de ligne entre
			// l'accolade ouvrante et le mot de contrôle (« { \s231\ql… »).
			$is_char = false;
			$index   = null;
			if ( preg_match( '/^\{\s*(?:\\\\\*\s*)?\\\\cs(\d+)/', $def, $m ) ) {
				$is_char = true;
				$index   = (int) $m[1];
			} elseif ( preg_match( '/^\{\s*(?:\\\\\*\s*)?\\\\s(\d+)/', $def, $m ) ) {
				$index = (int) $m[1];
			} elseif ( preg_match( '/^\{\s*(?:\\\\\*\s*)?\\\\ts\d+/', $def ) ) {
				$index = null; // Style de tableau : non utilisé ici.
			} elseif ( preg_match( '/^\{\s*\\\\q[jlcr]/', $def ) || preg_match( '/^\{\s*\\\\li0/', $def ) ) {
				$index = 0; // Style Normal, sans numéro.
			}
			if ( null !== $index ) {
				$name = $this->style_name_from_def( $def );
				if ( '' !== $name ) {
					if ( $is_char ) {
						$this->char_styles[ $name ] = $index;
					} else {
						$this->styles[ $name ] = $index;
					}
				}
			}
			$start = $close;
		}
	}

	/**
	 * Extrait le libellé d'une définition de style.
	 * Le nom est le texte littéral placé juste avant le « ; » final : on retire
	 * les groupes imbriqués, puis tous les mots de contrôle RTF, et l'on garde
	 * ce qui reste.
	 */
	private function style_name_from_def( $def ) {
		$inner = substr( $def, 1, -1 );
		$pos   = strrpos( $inner, ';' );
		if ( false === $pos ) {
			return '';
		}
		$name = substr( $inner, 0, $pos );
		// Suppression des groupes imbriqués ({\*\panose …}, etc.).
		$prev = null;
		while ( $prev !== $name ) {
			$prev = $name;
			$name = preg_replace( '/\{[^{}]*\}/', '', $name );
		}
		// Le libellé est tout ce qui suit le dernier mot de contrôle : on
		// localise la fin de la dernière séquence \mot[nombre][espace] et on
		// garde le reste. Un nom comme « Normal (Web) » ou « TEI_Titre 1+rubrique »
		// est ainsi conservé intact, parenthèses et chiffres compris.
		if ( preg_match_all( '/\\\\(?:[a-zA-Z]+-?\d* ?|[^a-zA-Z])/', $name, $m, PREG_OFFSET_CAPTURE ) ) {
			$last = end( $m[0] );
			$name = substr( $name, $last[1] + strlen( $last[0] ) );
		}
		return trim( $name, " \t\r\n" );
	}

	/**
	 * Renvoie le code \sNNN d'un style de paragraphe, ou '' si absent du modèle.
	 * Un style absent ne bloque pas la génération : le paragraphe sort en Normal
	 * et sera repéré à la relecture.
	 */
	private function style_code( $name ) {
		if ( ! isset( $this->styles[ $name ] ) ) {
			return '';
		}
		$i = (int) $this->styles[ $name ];
		return ( 0 === $i ) ? '' : '\\s' . $i;
	}

	/**
	 * Échappe une chaîne UTF-8 pour RTF : accolades, antislash, et tout
	 * caractère non-ASCII converti en \uN? (notation Unicode RTF).
	 */
	public static function esc( $text ) {
		$text = (string) $text;
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$out  = '';
		foreach ( self::codepoints( $text ) as $code ) {
			if ( 0x5C === $code ) { // \
				$out .= '\\\\';
			} elseif ( 0x7B === $code ) { // {
				$out .= '\\{';
			} elseif ( 0x7D === $code ) { // }
				$out .= '\\}';
			} elseif ( 0x0A === $code ) {
				$out .= '\\line ';
			} elseif ( 0x09 === $code ) {
				$out .= '\\tab ';
			} elseif ( $code < 128 ) {
				$out .= chr( $code );
			} elseif ( $code > 65535 ) {
				// Hors BMP : paire de substitution, exigée par la spécification RTF.
				$code -= 0x10000;
				$hi    = 0xD800 + ( $code >> 10 );
				$lo    = 0xDC00 + ( $code & 0x3FF );
				$out  .= '\\u' . $hi . '?\\u' . $lo . '?';
			} else {
				// Word attend un entier signé sur 16 bits.
				$signed = ( $code > 32767 ) ? $code - 65536 : $code;
				$out   .= '\\u' . $signed . '?';
			}
		}
		return $out;
	}

	/**
	 * Décode une chaîne UTF-8 en liste de points de code, sans dépendre de
	 * l'extension mbstring (absente de certains hébergements). Les octets
	 * invalides sont ignorés silencieusement.
	 */
	private static function codepoints( $s ) {
		$out = array();
		$len = strlen( $s );
		$i   = 0;
		while ( $i < $len ) {
			$b = ord( $s[ $i ] );
			if ( $b < 0x80 ) {
				$out[] = $b;
				$i++;
			} elseif ( $b >= 0xC2 && $b <= 0xDF && $i + 1 < $len ) {
				$out[] = ( ( $b & 0x1F ) << 6 ) | ( ord( $s[ $i + 1 ] ) & 0x3F );
				$i    += 2;
			} elseif ( $b >= 0xE0 && $b <= 0xEF && $i + 2 < $len ) {
				$out[] = ( ( $b & 0x0F ) << 12 )
					| ( ( ord( $s[ $i + 1 ] ) & 0x3F ) << 6 )
					| ( ord( $s[ $i + 2 ] ) & 0x3F );
				$i += 3;
			} elseif ( $b >= 0xF0 && $b <= 0xF4 && $i + 3 < $len ) {
				$out[] = ( ( $b & 0x07 ) << 18 )
					| ( ( ord( $s[ $i + 1 ] ) & 0x3F ) << 12 )
					| ( ( ord( $s[ $i + 2 ] ) & 0x3F ) << 6 )
					| ( ord( $s[ $i + 3 ] ) & 0x3F );
				$i += 4;
			} else {
				$i++; // Octet invalide : ignoré.
			}
		}
		return $out;
	}

	/**
	 * Ajoute un paragraphe stylé. $runs est soit une chaîne déjà encodée,
	 * soit un tableau de segments : array( 'text' => '…', 'b' => true, 'i' => true ).
	 */
	public function add_paragraph( $style_name, $runs ) {
		$code = $this->style_code( $style_name );
		$rtf  = '\\pard\\plain' . $code . ' ';
		if ( is_array( $runs ) ) {
			foreach ( $runs as $run ) {
				$rtf .= $this->render_run( $run );
			}
		} else {
			$rtf .= (string) $runs;
		}
		$rtf .= '\\par';
		$this->body[] = $rtf;
	}

	/**
	 * Ajoute un paragraphe dont le contenu est déjà du RTF (texte riche converti).
	 */
	public function add_raw_paragraph( $style_name, $rtf_content ) {
		$code         = $this->style_code( $style_name );
		$this->body[] = '\\pard\\plain' . $code . ' ' . $rtf_content . '\\par';
	}

	private function render_run( $run ) {
		if ( is_string( $run ) ) {
			return self::esc( $run );
		}
		$text = isset( $run['text'] ) ? self::esc( $run['text'] ) : '';
		if ( '' === $text && empty( $run['raw'] ) ) {
			return '';
		}
		if ( ! empty( $run['raw'] ) ) {
			$text = $run['raw'];
		}
		$open  = '';
		$close = '';
		// Style de caractère Métopes (ex. TEI_archeoCHR_name:fld).
		if ( ! empty( $run['cs'] ) ) {
			$code = $this->char_style_code( $run['cs'] );
			if ( '' !== $code ) {
				$open .= $code . ' ';
			}
		}
		if ( ! empty( $run['b'] ) ) {
			$open  .= '\\b ';
			$close .= '\\b0 ';
		}
		if ( ! empty( $run['i'] ) ) {
			$open  .= '\\i ';
			$close .= '\\i0 ';
		}
		if ( '' === $open ) {
			return $text;
		}
		return '{' . $open . $text . $close . '}';
	}

	/**
	 * Renvoie le code \csNNN d'un style de caractère, ou '' s'il est absent
	 * du modèle. Un style manquant n'interrompt pas la génération.
	 */
	private function char_style_code( $name ) {
		if ( ! isset( $this->char_styles[ $name ] ) ) {
			return '';
		}
		return '\\cs' . (int) $this->char_styles[ $name ];
	}

	/**
	 * Rend du texte sans mise en forme, prêt à être concaténé dans un
	 * paragraphe assemblé à la main. Pendant de la méthode homonyme du
	 * générateur DOCX, où un tel fragment doit être encapsulé dans un run.
	 */
	public function plain( $text ) {
		return self::esc( $text );
	}

	/**
	 * Construit un fragment RTF portant un style de caractère, utilisable
	 * dans un paragraphe assemblé à la main.
	 */
	public function char_run( $style_name, $text ) {
		return $this->render_run( array( 'cs' => $style_name, 'text' => $text ) );
	}

	/**
	 * Construit un lien hypertexte RTF (champ HYPERLINK) affichant $label.
	 */
	public function hyperlink( $url, $label ) {
		$url_esc   = self::esc( $url );
		$label_esc = self::esc( $label );
		$cs        = isset( $this->char_styles['Hyperlink'] ) ? '\\cs' . (int) $this->char_styles['Hyperlink'] : '';
		return '{\\field{\\*\\fldinst {HYPERLINK "' . $url_esc . '"}}{\\fldrslt {' . $cs . '\\ul\\cf2 ' . $label_esc . '}}}';
	}

	/**
	 * Convertit le HTML restreint de l'éditeur (p, br, em, i, strong, b, sup, sub)
	 * en une liste de paragraphes RTF. Renvoie un tableau de chaînes RTF,
	 * une par paragraphe.
	 */
	public function html_to_paragraphs( $html ) {
		$html = (string) $html;
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return array();
		}
		// Normalisation : on force des <p> autour du contenu hors paragraphe.
		$html = preg_replace( '#<br\s*/?>#i', "\n", $html );
		$html = preg_replace( '#</p>#i', "\x00", $html );
		$html = preg_replace( '#<p[^>]*>#i', '', $html );
		$chunks = explode( "\x00", $html );
		$out    = array();
		foreach ( $chunks as $chunk ) {
			if ( '' === trim( wp_strip_all_tags( $chunk ) ) ) {
				continue;
			}
			$out[] = $this->inline_html_to_rtf( $chunk );
		}
		return $out;
	}

	/**
	 * Convertit les balises en ligne d'un fragment HTML en runs RTF.
	 */
	private function inline_html_to_rtf( $fragment ) {
		// Balise ouvrante => code RTF ouvrant. Chaque ouverture pose un groupe
		// « { », refermé par « } » à la balise fermante correspondante.
		$open_tags = array(
			'strong' => '{\\b ',
			'b'      => '{\\b ',
			'em'     => '{\\i ',
			'i'      => '{\\i ',
			'sup'    => '{\\super ',
			'sub'    => '{\\sub ',
		);
		$parts = preg_split( '#(<[^>]+>)#', $fragment, -1, PREG_SPLIT_DELIM_CAPTURE );
		$rtf   = '';
		$stack = array();
		foreach ( $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( '<' === $part[0] ) {
				if ( preg_match( '#^</\s*([a-zA-Z0-9]+)#', $part, $m ) ) {
					$tag = strtolower( $m[1] );
					// On ne ferme que si la balise a bien été ouverte.
					if ( ( isset( $open_tags[ $tag ] ) || 'span' === $tag ) && ! empty( $stack ) ) {
						$last = array_pop( $stack );
						if ( $last === $tag ) {
							$rtf .= '}';
						} else {
							// Imbrication incohérente : on remet en place et on ignore.
							$stack[] = $last;
						}
					}
				} elseif ( preg_match( '#^<\s*([a-zA-Z0-9]+)#', $part, $m ) ) {
					$tag = strtolower( $m[1] );
					if ( 'span' === $tag && ! preg_match( '#/\s*>$#', $part ) ) {
						// Les petites capitales de l'éditeur ; un autre « span »
						// ouvre un groupe neutre, que sa fermeture referme.
						$rtf    .= preg_match( '#\bclass\s*=\s*["\'][^"\']*\bna-pc\b#i', $part ) ? '{\\scaps ' : '{';
						$stack[] = 'span';
					} elseif ( isset( $open_tags[ $tag ] ) && ! preg_match( '#/\s*>$#', $part ) ) {
						// Balise auto-fermante : aucun effet ici (les <br> ont déjà été traités).
						$rtf    .= $open_tags[ $tag ];
						$stack[] = $tag;
					}
				}
				// Toute autre balise est simplement ignorée.
			} else {
				$rtf .= self::esc( html_entity_decode( $part, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			}
		}
		// Fermeture de sécurité si le HTML était déséquilibré.
		$rtf .= str_repeat( '}', count( $stack ) );
		return $rtf;
	}

	/**
	 * Assemble le document complet.
	 */
	public function render() {
		if ( ! $this->is_ready() ) {
			return '';
		}
		$doc  = $this->header;
		$doc .= "\n";
		// Section par défaut, marges et orientation reprises d'un document A4 simple.
		$doc .= '\\sectd\\linex0\\headery708\\footery708\\colsx708\\sectdefaultcl' . "\n";
		$doc .= implode( "\n", $this->body );
		$doc .= "\n}";
		return $doc;
	}

	/**
	 * Écrit le document dans un fichier temporaire et renvoie son chemin,
	 * ou une chaîne vide en cas d'échec.
	 */
	public function write_to( $dir, $basename ) {
		$content = $this->render();
		if ( '' === $content ) {
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
		if ( false === file_put_contents( $path, $content ) ) {
			$this->error = 'Écriture du fichier RTF impossible.';
			return '';
		}
		return $path;
	}

	/**
	 * Liste des styles reconnus, utile pour un contrôle en développement.
	 */
	public function known_styles() {
		return array_keys( $this->styles );
	}
}
