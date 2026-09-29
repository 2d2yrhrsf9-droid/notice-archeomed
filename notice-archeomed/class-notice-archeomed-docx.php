<?php
/**
 * Générateur de fichier DOCX stylé Métopes (chaîne d'édition XML créée par le Pôle document numérique et l'infrastructure Métopes de l'université de Caen Normandie, https://www.metopes.fr) pour les notices Archéomed.
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
	 * Identifiant de relation => chemin de l'image liée, relatif au document.
	 *
	 * @var array
	 */
	private $images = array();

	/**
	 * Identifiant de relation => image incorporée { nom, ext, octets }.
	 *
	 * Les octets entrent dans le fichier, sous « word/media ». C'est ce qu'il
	 * faut quand le document voyage seul — en pièce jointe d'un courriel, il
	 * n'a aucun dossier à côté de lui où aller chercher une figure.
	 *
	 * @var array
	 */
	private $medias = array();

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
	 * Le style du bloc que la rédaction ôte avant de passer au XML.
	 *
	 * Il n'est pas dans le gabarit Métopes, et il n'y sera pas : ce gabarit
	 * s'exporte à neuf à chaque évolution de la feuille, et tout ce qu'on y
	 * ajouterait à la main serait à refaire. Il est donc injecté dans la
	 * feuille de styles au moment d'écrire le fichier — le gabarit livré
	 * reste intact, et le document produit porte le style.
	 *
	 * Son intérêt tient à un geste de Word : un clic droit sur le style, dans
	 * le volet des styles, puis « Sélectionner toutes les occurrences ». Tout
	 * le bloc se prend d'un coup, et se supprime d'une touche.
	 */
	// L'unité de Word pour les longueurs : 914 400 unités par pouce.
	const EMU_PAR_POUCE = 914400;
	// La largeur utile d'une page du gabarit, à la louche : A4 moins ses
	// marges. Une figure plus large déborde de la justification.
	const LARGEUR_UTILE_EMU = 5486400;   // 6 pouces, soit 15,24 cm

	const STYLE_A_SUPPRIMER = 'à supprimer';
	const ID_A_SUPPRIMER    = 'naasupprimer';

	/**
	 * La définition XML du style, à glisser avant la fin de la feuille.
	 */
	private function style_a_supprimer_xml() {
		$base = isset( $this->styles['Normal'] )
			? '<w:basedOn w:val="' . self::esc( $this->styles['Normal'] ) . '"/>' : '';
		return '<w:style w:type="paragraph" w:customStyle="1" w:styleId="'
			. self::ID_A_SUPPRIMER . '">'
			. '<w:name w:val="' . self::esc( self::STYLE_A_SUPPRIMER ) . '"/>'
			. $base
			// « qFormat » le fait paraître dans la galerie des styles : c'est
			// par là qu'on le trouve pour tout sélectionner.
			. '<w:qFormat/>'
			// Gris : le bloc se distingue du texte à l'œil, sans emprunter le
			// surlignement, qui appartient à Métopes et veut dire autre chose.
			. '<w:rPr><w:color w:val="808080"/></w:rPr>'
			. '</w:style>';
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
			return;
		}
		// Le style d'effacement n'est pas dans le gabarit : on l'y ajoute pour
		// la durée du document, et « write_to » l'écrira dans la feuille.
		$this->styles[ self::STYLE_A_SUPPRIMER ] = self::ID_A_SUPPRIMER;
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
	 * Une image liée, posée dans le fil du texte.
	 *
	 * Le fichier n'entre pas dans le document : le paquet le porte dans
	 * « icono/br » et le document le désigne par un chemin relatif. C'est
	 * ainsi que la mise en page travaille — l'image se remplace dans son
	 * dossier sans rouvrir le texte — et c'est ce qui évite d'embarquer cent
	 * figures dans un fichier qu'on s'échange par courriel.
	 *
	 * La taille se donne en pixels et en points par pouce ; elle se convertit
	 * en EMU, l'unité de Word — 914 400 par pouce. Au-delà de la largeur utile
	 * de la page, l'image est réduite en gardant ses proportions : une figure
	 * plus large que la justification déborderait sans que rien ne le dise.
	 *
	 * Rend le XML d'un run, à passer à « add_raw_paragraph ».
	 */
	public function image_liee( $cible, $largeur_px, $hauteur_px, $dpi = 96, $titre = '' ) {
		$rid                  = $this->prochain_rid();
		$this->images[ $rid ] = $cible;
		return $this->dessin( $rid, $largeur_px, $hauteur_px, $dpi,
			'' !== $titre ? $titre : basename( $cible ), true );
	}

	/**
	 * Une image incorporée : ses octets entrent dans le fichier.
	 *
	 * Le lien de « image_liee » ne résout que si l'icono est à côté du
	 * document, ce qui n'est vrai que dans le dossier. Le document envoyé par
	 * courriel, lui, part seul : Word y posait un cadre vide, et l'auteur
	 * croyait ses illustrations perdues. Il porte donc ses figures avec lui —
	 * une basse définition, la même que celle du dossier, qui n'alourdit pas
	 * la pièce jointe au-delà du raisonnable.
	 *
	 * Rend une chaîne vide si le fichier est illisible : une figure manquante
	 * vaut mieux qu'un document que Word refuse d'ouvrir.
	 */
	public function image_incluse( $chemin, $largeur_px, $hauteur_px, $dpi = 96, $titre = '' ) {
		$chemin = (string) $chemin;
		$ext    = strtolower( pathinfo( $chemin, PATHINFO_EXTENSION ) );
		if ( '' === self::type_image( $ext ) || ! is_readable( $chemin ) ) {
			return '';
		}
		$octets = @file_get_contents( $chemin );
		if ( false === $octets || '' === $octets ) {
			return '';
		}
		$rid = $this->prochain_rid();
		// Le préfixe « na- » écarte le risque d'écraser une image du gabarit :
		// « media/image1.jpg » est le nom que Word donne aux siennes.
		$this->medias[ $rid ] = array(
			'nom'    => 'media/na-' . count( $this->medias ) . '.' . $ext,
			'ext'    => $ext,
			'octets' => $octets,
		);
		return $this->dessin( $rid, $largeur_px, $hauteur_px, $dpi,
			'' !== $titre ? $titre : basename( $chemin ), false );
	}

	/**
	 * Un identifiant de relation qui n'a pas encore servi. Les deux registres
	 * se comptent ensemble : deux relations de même nom, et Word ouvre le
	 * document sur une figure de travers.
	 */
	private function prochain_rid() {
		return 'rIdNAimg' . ( count( $this->images ) + count( $this->medias ) + 1 );
	}

	/**
	 * Le type MIME d'une extension d'image, ou une chaîne vide si l'extension
	 * n'est pas de celles qu'un DOCX sait porter.
	 */
	private static function type_image( $ext ) {
		$types = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'tif'  => 'image/tiff',
			'tiff' => 'image/tiff',
		);
		$ext = strtolower( (string) $ext );
		return isset( $types[ $ext ] ) ? $types[ $ext ] : '';
	}

	/**
	 * Le dessin proprement dit, commun aux deux manières de poser une image.
	 * Seul l'attribut du « blip » les sépare : « r:link » désigne un fichier
	 * voisin, « r:embed » un fichier que le document porte.
	 */
	private function dessin( $rid, $largeur_px, $hauteur_px, $dpi, $nom, $lie ) {
		$largeur_px = max( 1, (int) $largeur_px );
		$hauteur_px = max( 1, (int) $hauteur_px );
		$dpi        = $dpi > 0 ? (int) $dpi : 96;

		$cx = (int) round( $largeur_px / $dpi * self::EMU_PAR_POUCE );
		$cy = (int) round( $hauteur_px / $dpi * self::EMU_PAR_POUCE );
		if ( $cx > self::LARGEUR_UTILE_EMU ) {
			$cy = (int) round( $cy * self::LARGEUR_UTILE_EMU / $cx );
			$cx = self::LARGEUR_UTILE_EMU;
		}
		$cx = max( 1, $cx );
		$cy = max( 1, $cy );

		$id = 1000 + count( $this->images ) + count( $this->medias );

		return '<w:r><w:drawing>'
			. '<wp:inline distT="0" distB="0" distL="0" distR="0">'
			. '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
			. '<wp:effectExtent l="0" t="0" r="0" b="0"/>'
			. '<wp:docPr id="' . $id . '" name="' . self::esc( $nom ) . '"/>'
			. '<wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>'
			. '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
			. '<pic:pic>'
			. '<pic:nvPicPr><pic:cNvPr id="' . $id . '" name="' . self::esc( $nom ) . '"/>'
			. '<pic:cNvPicPr/></pic:nvPicPr>'
			// « r:link » désigne l'image sans l'inclure ; « r:embed » la prend
			// dans le fichier. Le dossier veut la première, le courriel la
			// seconde.
			. '<pic:blipFill><a:blip ' . ( $lie ? 'r:link' : 'r:embed' ) . '="' . $rid . '"/>'
			. '<a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
			. '<pic:spPr><a:xfrm><a:off x="0" y="0"/>'
			. '<a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
			. '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
			. '</pic:pic></a:graphicData></a:graphic>'
			. '</wp:inline></w:drawing></w:r>';
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
			$contenu = $src->getFromIndex( $i );
			// Le gabarit garde le chemin du modèle Word auquel il était
			// attaché quand quelqu'un l'a enregistré : un chemin absolu sur
			// un poste, avec un nom d'utilisateur et un compte d'hébergement.
			// Il était recopié tel quel dans chaque document produit — donc
			// envoyé à chaque auteur — et il ne sert à rien : la machine qu'il
			// désigne n'est celle de personne d'autre. Il s'ôte ici plutôt
			// qu'à la main dans le gabarit, qui se remplace à neuf à chaque
			// évolution de la feuille et le rapporterait.
			if ( 'word/settings.xml' === $name && is_string( $contenu ) ) {
				$contenu = preg_replace( '#<w:attachedTemplate\b[^>]*/>#', '', $contenu );
			}
			if ( 'word/_rels/settings.xml.rels' === $name && is_string( $contenu ) ) {
				$contenu = preg_replace(
					'#<Relationship\b[^>]*attachedTemplate[^>]*/>#', '', $contenu );
			}
			if ( '[Content_Types].xml' === $name && is_string( $contenu ) ) {
				$contenu = $this->types_de_contenu( $contenu );
			}
			if ( 'word/styles.xml' === $name && is_string( $contenu ) ) {
				// Le style d'effacement entre ici, juste avant la fermeture.
				// Si la balise manquait, on laisse la feuille telle quelle
				// plutôt que d'écrire un XML bancal.
				$pos = strrpos( $contenu, '</w:styles>' );
				if ( false !== $pos ) {
					$contenu = substr( $contenu, 0, $pos )
						. $this->style_a_supprimer_xml()
						. substr( $contenu, $pos );
				}
			}
			$dest->addFromString( $name, $contenu );
		}
		$src->close();

		foreach ( $this->medias as $media ) {
			$dest->addFromString( 'word/' . $media['nom'], $media['octets'] );
		}
		$dest->addFromString( 'word/document.xml', $this->document_xml( $sect ) );
		$dest->addFromString( 'word/_rels/document.xml.rels', $this->rels_xml( $rels_src ) );
		$dest->close();

		return $path;
	}

	/**
	 * Déclare au manifeste les extensions des images incorporées.
	 *
	 * Un DOCX qui porte un fichier dont le type n'est pas déclaré est refusé
	 * par Word — non pas dégradé, refusé. Le gabarit déclare déjà les siennes
	 * s'il a des images ; on n'ajoute que ce qui manque.
	 */
	private function types_de_contenu( $xml ) {
		$ajouts = '';
		$vus    = array();
		foreach ( $this->medias as $media ) {
			$ext = $media['ext'];
			if ( isset( $vus[ $ext ] ) ) {
				continue;
			}
			$vus[ $ext ] = true;
			if ( preg_match( '#<Default\b[^>]*Extension="' . preg_quote( $ext, '#' ) . '"#i', $xml ) ) {
				continue;
			}
			$ajouts .= '<Default Extension="' . self::esc( $ext ) . '"'
				. ' ContentType="' . self::type_image( $ext ) . '"/>';
		}
		if ( '' === $ajouts ) {
			return $xml;
		}
		$pos = strrpos( $xml, '</Types>' );
		if ( false === $pos ) {
			// Manifeste inattendu : on le laisse intact plutôt que d'écrire
			// un XML bancal, quitte à perdre les figures.
			return $xml;
		}
		return substr( $xml, 0, $pos ) . $ajouts . substr( $xml, $pos );
	}

	/**
	 * Corps du document.
	 */
	private function document_xml( $sect_pr ) {
		$ns = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
			. ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
			// Les trois espaces de noms du dessin. Sans eux, Word refuse
			// d'ouvrir le document au lieu d'ignorer les images : une balise
			// dont le préfixe n'est pas déclaré rend le XML invalide.
			. ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
			. ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
			. ' xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';
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
		// Les images liées : le fichier n'entre pas dans le document, il est
		// désigné à côté de lui. C'est ce que la chaîne attend — l'icono vit
		// dans son dossier, le document l'appelle.
		$image_type = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
		foreach ( $this->images as $rid => $cible ) {
			$new .= '<Relationship Id="' . self::esc( $rid ) . '"'
				. ' Type="' . $image_type . '"'
				. ' Target="' . self::esc( $cible ) . '"'
				. ' TargetMode="External"/>';
		}
		// Les images incorporées : le fichier est dans le document, et la
		// cible se lit depuis « word/ ». Pas de « TargetMode », qui ferait
		// chercher au dehors ce qui est au dedans.
		foreach ( $this->medias as $rid => $media ) {
			$new .= '<Relationship Id="' . self::esc( $rid ) . '"'
				. ' Type="' . $image_type . '"'
				. ' Target="' . self::esc( $media['nom'] ) . '"/>';
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
