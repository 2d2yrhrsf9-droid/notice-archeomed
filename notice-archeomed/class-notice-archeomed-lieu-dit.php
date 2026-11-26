<?php
/**
 * La mise en forme du lieu-dit : petites capitales, italique, exposant.
 *
 * Le lieu-dit garde son texte brut sous « lieu_dit » : c'est lui que lisent
 * les noms de fichiers, le dossier sur sDrive, les courriels, la liste et le
 * sommaire. Sa mise en forme voyage à côté, sous « lieu_dit_forme », en HTML
 * restreint — « <span class="na-pc"> », « <em> », « <sup> » — et ne vaut que
 * si son texte est exactement celui du champ brut. Une forme qui ne lui
 * correspond plus, parce qu'on a retouché le lieu-dit ailleurs, s'efface :
 * le titre perd son italique intérieur, jamais une lettre.
 *
 * Le lieu-dit s'imprime en italique. Ce que l'auteur y met en italique —
 * un nom de navire, un titre d'œuvre — s'imprime donc en romain : la règle
 * de l'italique dans l'italique, appliquée là où le titre se compose.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Lieu_Dit {

	/** Une forme plus longue n'est pas celle d'un lieu-dit de deux cents signes. */
	const BORNE = 4000;

	/** La borne d'une forme de plusieurs paragraphes : une légende, une description. */
	const BORNE_LONGUE = 100000;

	/**
	 * Un numéro suivi d'un nom de voie, en tête du lieu-dit ou après une
	 * virgule ou un point-virgule : « 53 rue Haute », « Château, 3 bis place
	 * Saint-Pierre ». Le même motif sert au navigateur, qui le reçoit tel
	 * quel. Seuls les noms de voie le déclenchent : « 3 Moulins » reste
	 * comme il est écrit.
	 */
	const VOIE_NUMEROTEE = '(^|[;,])(\\s*)(\\d{1,5}(?:\\s*(?:bis|ter|quater|[a-z]))?)(?:\\s*,\\s*|\\s+)'
		. '(rond[\\s-]point|ruelle|ruette|rue|placette|place|avenue|boulevard|chemin|impasse|all[ée]e|quai|route|square'
		. '|cours|passage|sentier|faubourg|promenade|esplanade|voie|venelle|mont[ée]e|c[ôo]te|carrefour|parvis|cit[ée]'
		. '|traverse|mail|sente)(?=[\\s\'’,;.]|$)';

	/**
	 * La virgule après le numéro d'une voie, et le nom de voie en minuscule :
	 * « 53 rue Haute » devient « 53, rue Haute », comme quand on choisit une
	 * voie proposée par l'IGN — mais pour une voie qu'il ne connaît pas.
	 */
	public static function virgule_du_numero( $texte ) {
		$texte = (string) $texte;
		$fait  = preg_replace_callback(
			'/(*UCP)' . self::VOIE_NUMEROTEE . '/iu',
			function ( $m ) {
				return $m[1] . $m[2] . self::numero( $m[3] ) . ', ' . self::en_minuscules( $m[4] );
			},
			$texte
		);
		return null === $fait ? $texte : $fait;
	}

	/** « 3  bis » s'écrit « 3 bis ». */
	private static function numero( $numero ) {
		return preg_replace( '/\s+/u', ' ', $numero );
	}

	private static function en_minuscules( $mot ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $mot, 'UTF-8' ) : strtolower( $mot );
	}

	/**
	 * La même virgule, posée dans les morceaux : le numéro, la virgule et le
	 * nom de voie prennent la mise en forme du numéro, le reste garde la
	 * sienne.
	 */
	private static function virgule_dans_les_morceaux( $runs ) {
		$brut = self::texte( $runs );
		if ( ! preg_match_all( '/(*UCP)' . self::VOIE_NUMEROTEE . '/iu', $brut, $vus, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return $runs;
		}
		foreach ( array_reverse( $vus ) as $m ) {
			$debut = $m[3][1];
			$fin   = $m[4][1] + strlen( $m[4][0] );
			$runs  = self::remplacer( $runs, $debut, $fin, self::numero( $m[3][0] ) . ', ' . self::en_minuscules( $m[4][0] ) );
		}
		return $runs;
	}

	/**
	 * Les octets de « $debut » à « $fin » du texte des morceaux, remplacés
	 * par « $par », placé dans le morceau où commence l'endroit remplacé.
	 */
	private static function remplacer( $runs, $debut, $fin, $par ) {
		$pos   = 0;
		$pose  = false;
		$garde = array();
		foreach ( $runs as $run ) {
			$texte = $run['text'];
			$a     = $pos;
			$n     = strlen( $texte );
			$pos  += $n;
			if ( $a + $n > $debut && $a < $fin ) {
				$de          = max( 0, $debut - $a );
				$run['text'] = substr( $texte, 0, $de ) . ( $pose ? '' : $par ) . substr( $texte, min( $n, $fin - $a ) );
				$pose        = true;
			}
			if ( '' !== $run['text'] ) {
				$garde[] = $run;
			}
		}
		return $garde;
	}

	/**
	 * Les morceaux d'une forme, dans l'ordre : chacun porte son texte et ses
	 * trois marques. Les balises inconnues s'ignorent, leur texte reste ; les
	 * blancs des deux bouts s'ôtent, comme au champ brut.
	 *
	 * « $multiligne » sert aux textes des figures : un « <br> » ou la fin
	 * d'un paragraphe y donne un retour à la ligne, qui se garde. Le
	 * lieu-dit, lui, tient sur une ligne.
	 */
	public static function morceaux( $forme, $multiligne = false ) {
		$forme = (string) $forme;
		if ( '' === $forme || strlen( $forme ) > ( $multiligne ? self::BORNE_LONGUE : self::BORNE ) ) {
			return array();
		}
		if ( function_exists( 'wp_check_invalid_utf8' ) ) {
			$forme = wp_check_invalid_utf8( $forme );
		}
		$parts = preg_split( '#(<[^>]*>)#', $forme, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		$pile  = array();
		$runs  = array();
		foreach ( (array) $parts as $part ) {
			if ( '<' === $part[0] && '>' === substr( $part, -1 ) ) {
				if ( $multiligne && preg_match( '#^(?:<\s*br\b[^>]*|</\s*(?:p|div)\s*)>$#i', $part ) ) {
					$part = "\n";
				} elseif ( preg_match( '#^</\s*(em|i|sup|span)\s*>$#i', $part, $m ) ) {
					$cherche = array( 'em' => array( 'i' ), 'i' => array( 'i' ), 'sup' => array( 'sup' ), 'span' => array( 'pc', 'x' ) );
					for ( $k = count( $pile ) - 1; $k >= 0; $k-- ) {
						if ( in_array( $pile[ $k ], $cherche[ strtolower( $m[1] ) ], true ) ) {
							array_splice( $pile, $k, 1 );
							break;
						}
					}
				} elseif ( preg_match( '#^<\s*(em|i|sup|span)\b([^>]*)>$#i', $part, $m ) && ! preg_match( '#/\s*>$#', $part ) ) {
					$balise = strtolower( $m[1] );
					if ( 'span' === $balise ) {
						$pile[] = preg_match( '#\bclass\s*=\s*["\'][^"\']*\bna-pc\b#i', $m[2] ) ? 'pc' : 'x';
					} else {
						$pile[] = 'sup' === $balise ? 'sup' : 'i';
					}
				}
				if ( "\n" !== $part ) {
					continue;
				}
			}
			$texte = html_entity_decode( $part, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			// Comme au champ brut : ce qui sépare devient une espace, les
			// autres caractères de contrôle s'ôtent. Sur plusieurs lignes, le
			// retour à la ligne reste.
			if ( $multiligne ) {
				$texte = str_replace( array( "\r\n", "\r", "\t", "\x0B", "\x0C" ), array( "\n", "\n", ' ', ' ', ' ' ), $texte );
			} else {
				$texte = str_replace( array( "\t", "\n", "\x0B", "\x0C", "\r" ), ' ', $texte );
			}
			$texte = (string) preg_replace( '/[\x00-\x08\x0E-\x1F\x7F]/', '', $texte );
			if ( '' === $texte ) {
				continue;
			}
			$run = array(
				'text' => $texte,
				'i'    => in_array( 'i', $pile, true ),
				'pc'   => in_array( 'pc', $pile, true ),
				'sup'  => in_array( 'sup', $pile, true ),
			);
			$dernier = count( $runs ) - 1;
			if ( $dernier >= 0 && $runs[ $dernier ]['i'] === $run['i'] && $runs[ $dernier ]['pc'] === $run['pc'] && $runs[ $dernier ]['sup'] === $run['sup'] ) {
				$runs[ $dernier ]['text'] .= $texte;
			} else {
				$runs[] = $run;
			}
		}
		return self::sans_blancs_aux_bouts( $runs );
	}

	/** Les blancs de tête du premier morceau et de queue du dernier s'ôtent. */
	private static function sans_blancs_aux_bouts( $runs ) {
		while ( ! empty( $runs ) ) {
			$runs[0]['text'] = ltrim( $runs[0]['text'], " \t\n\r\0\x0B" );
			if ( '' !== $runs[0]['text'] ) {
				break;
			}
			array_shift( $runs );
		}
		while ( ! empty( $runs ) ) {
			$n                    = count( $runs ) - 1;
			$runs[ $n ]['text'] = rtrim( $runs[ $n ]['text'], " \t\n\r\0\x0B" );
			if ( '' !== $runs[ $n ]['text'] ) {
				break;
			}
			array_pop( $runs );
		}
		return array_values( $runs );
	}

	/** Le texte des morceaux mis bout à bout. */
	public static function texte( $runs ) {
		return implode( '', array_column( (array) $runs, 'text' ) );
	}

	/** Un morceau au moins porte-t-il une marque ? */
	public static function a_une_forme( $runs ) {
		foreach ( (array) $runs as $run ) {
			if ( ! empty( $run['i'] ) || ! empty( $run['pc'] ) || ! empty( $run['sup'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * La forme postée, accordée au lieu-dit tel que le serveur l'a reçu.
	 *
	 * Le serveur ôte le point final, met la virgule après le numéro d'une
	 * voie et la première lettre en capitale : la forme reçoit les mêmes
	 * corrections. Rend la forme canonique, ou une
	 * chaîne vide si elle n'a aucune marque ou ne correspond pas au texte.
	 */
	public static function accorder( $forme, $texte ) {
		$texte = trim( (string) $texte );
		$runs  = self::morceaux( $forme );
		if ( '' === $texte || ! self::a_une_forme( $runs ) ) {
			return '';
		}
		if ( self::texte( $runs ) !== $texte ) {
			$runs = self::corriger_comme_le_texte( $runs );
			if ( self::texte( $runs ) !== $texte ) {
				return '';
			}
		}
		return self::en_html( $runs, false );
	}

	/** Le point final ôté, la virgule et la capitale initiale posées, comme au champ brut. */
	private static function corriger_comme_le_texte( $runs ) {
		if ( empty( $runs ) ) {
			return $runs;
		}
		$brut = self::texte( $runs );
		if ( Notice_Archeomed_Controles::sans_point_final( $brut ) !== $brut ) {
			$n                    = count( $runs ) - 1;
			$runs[ $n ]['text'] = substr( $runs[ $n ]['text'], 0, -1 );
			$runs                 = self::sans_blancs_aux_bouts( $runs );
		}
		$runs = self::virgule_dans_les_morceaux( $runs );
		if ( ! empty( $runs ) ) {
			$premier         = $runs[0]['text'];
			$runs[0]['text'] = function_exists( 'mb_substr' )
				? mb_strtoupper( mb_substr( $premier, 0, 1 ) ) . mb_substr( $premier, 1 )
				: ucfirst( $premier );
		}
		return $runs;
	}

	/**
	 * Les morceaux du lieu-dit d'une notice, prêts pour le titre : l'italique
	 * des normes posé, et celui de l'auteur renversé dedans. Sans forme qui
	 * vaille, un seul morceau, comme avant.
	 */
	public static function morceaux_du_titre( $d ) {
		$texte = isset( $d['lieu_dit'] ) ? trim( (string) $d['lieu_dit'] ) : '';
		if ( '' === $texte ) {
			return array();
		}
		$runs = ! empty( $d['lieu_dit_forme'] ) ? self::morceaux( $d['lieu_dit_forme'] ) : array();
		if ( empty( $runs ) || self::texte( $runs ) !== $texte ) {
			$runs = array( array( 'text' => $texte, 'i' => false, 'pc' => false, 'sup' => false ) );
		}
		$italique = Notice_Archeomed_Normes::lieu_dit_en_italique();
		foreach ( $runs as $k => $run ) {
			$runs[ $k ]['i'] = ( $italique !== ! empty( $run['i'] ) );
		}
		return $runs;
	}

	/** Le lieu-dit du titre en HTML, pour les pages et les copies. */
	public static function html_du_titre( $d ) {
		return self::en_html( self::morceaux_du_titre( $d ), true );
	}

	/**
	 * Des morceaux en HTML. Pour l'affichage, les petites capitales portent
	 * leur style : une copie envoyée par courriel n'a pas la feuille du site.
	 */
	private static function en_html( $runs, $affiche ) {
		$html = '';
		foreach ( (array) $runs as $run ) {
			$bout = htmlspecialchars( (string) $run['text'], ENT_NOQUOTES, 'UTF-8' );
			if ( ! empty( $run['sup'] ) ) {
				$bout = '<sup>' . $bout . '</sup>';
			}
			if ( ! empty( $run['i'] ) ) {
				$bout = '<em>' . $bout . '</em>';
			}
			if ( ! empty( $run['pc'] ) ) {
				$bout = '<span class="na-pc"' . ( $affiche ? ' style="font-variant:small-caps"' : '' ) . '>' . $bout . '</span>';
			}
			$html .= $bout;
		}
		return $html;
	}

	/**
	 * Le script de l'éditeur du lieu-dit, commun au dépôt et à la relecture.
	 *
	 * Le champ brut reste dans la page, caché : c'est lui que lisent les
	 * contrôles, le brouillon et l'envoi. L'éditeur d'une ligne le remplit à
	 * chaque frappe, et pose la mise en forme dans le champ caché voisin.
	 * Sans Quill, le champ brut reste visible, tel qu'il était.
	 */
	public static function script() {
		// Le motif de la virgule après le numéro, le même qu'au serveur.
		return "\t\t\tvar NA_LIEU_DIT_VOIE = " . wp_json_encode( self::VOIE_NUMEROTEE ) . ";\n" . <<<'JS'
			// ── Le lieu-dit : petites capitales, italique, exposant ──────────
			// Comme au serveur (virgule_du_numero) : « 53 rue Haute » devient
			// « 53, rue Haute », même pour une voie que l'IGN ne connaît pas.
			function naLieuDitVoie() {
				try { return new RegExp(NA_LIEU_DIT_VOIE, 'giu'); } catch (e) { return null; }
			}
			function naLieuDitVirgule(t) {
				t = String(t || '');
				var re = naLieuDitVoie();
				return re ? t.replace(re, function (tout, avant, blancs, numero, voie) {
					return avant + blancs + numero.replace(/\s+/g, ' ') + ', ' + voie.toLowerCase();
				}) : t;
			}
			// La même virgule dans les morceaux : le numéro, la virgule et le
			// nom de voie prennent la mise en forme du numéro.
			function naLieuDitVirguleDesMorceaux(runs) {
				var re = naLieuDitVoie();
				if (!re) { return runs; }
				var brut = naLieuDitTexte(runs), vus = [], m;
				while ((m = re.exec(brut))) { vus.push(m); }
				for (var k = vus.length - 1; k >= 0; k--) {
					m = vus[k];
					runs = naLieuDitRemplacer(runs, m.index + m[1].length + m[2].length, m.index + m[0].length,
						m[3].replace(/\s+/g, ' ') + ', ' + m[4].toLowerCase());
				}
				return runs;
			}
			// Les signes de « debut » à « fin », remplacés par « par », placé
			// dans le morceau où commence l'endroit remplacé.
			function naLieuDitRemplacer(runs, debut, fin, par) {
				var pos = 0, pose = false, garde = [];
				runs.forEach(function (r) {
					var a = pos, n = r.text.length;
					pos += n;
					if (a + n > debut && a < fin) {
						r.text = r.text.slice(0, Math.max(0, debut - a)) + (pose ? '' : par) + r.text.slice(Math.min(n, fin - a));
						pose = true;
					}
					if (r.text) { garde.push(r); }
				});
				return garde;
			}
			// Ce qui change d'un texte à l'autre, en endroits du premier : on
			// y ôte « n » signes et l'on y met « ins ». Chaque lettre gardée
			// garde ainsi sa mise en forme. Trop long, rien : on réécrit tout.
			function naLieuDitEcarts(a, b) {
				a = String(a); b = String(b);
				var n = a.length, m = b.length, i, j;
				if ((n + 1) * (m + 1) > 160000) { return null; }
				var L = [];
				for (i = n; i >= 0; i--) {
					L[i] = [];
					for (j = m; j >= 0; j--) {
						L[i][j] = i === n || j === m ? 0 : a.charAt(i) === b.charAt(j) ? L[i + 1][j + 1] + 1 : Math.max(L[i + 1][j], L[i][j + 1]);
					}
				}
				var out = [], cur = null;
				i = 0; j = 0;
				while (i < n || j < m) {
					if (i < n && j < m && a.charAt(i) === b.charAt(j)) { cur = null; i++; j++; continue; }
					if (!cur) { cur = { a: i, n: 0, ins: '' }; out.push(cur); }
					if (j < m && (i === n || L[i][j + 1] >= L[i + 1][j])) { cur.ins += b.charAt(j); j++; } else { cur.n++; i++; }
				}
				return out;
			}
			// Ce qui sépare devient une espace, les autres contrôles s'ôtent :
			// comme au serveur.
			function naLieuDitPropre(t) {
				return String(t || '').replace(/[\t\n\v\f\r]/g, ' ').replace(/[\x00-\x08\x0E-\x1F\x7F]/g, '');
			}
			function naLieuDitPousser(runs, texte, m) {
				texte = naLieuDitPropre(texte);
				if (!texte) { return; }
				var d = runs[runs.length - 1];
				if (d && d.i === m.i && d.pc === m.pc && d.sup === m.sup) { d.text += texte; return; }
				runs.push({ text: texte, i: !!m.i, pc: !!m.pc, sup: !!m.sup });
			}
			function naLieuDitRogner(runs) {
				while (runs.length) {
					runs[0].text = runs[0].text.replace(/^[ \t\n\r\0\x0B]+/, '');
					if (runs[0].text) { break; }
					runs.shift();
				}
				while (runs.length) {
					var n = runs.length - 1;
					runs[n].text = runs[n].text.replace(/[ \t\n\r\0\x0B]+$/, '');
					if (runs[n].text) { break; }
					runs.pop();
				}
				return runs;
			}
			function naLieuDitTexte(runs) {
				return runs.map(function (r) { return r.text; }).join('');
			}
			function naLieuDitMarque(runs) {
				return runs.some(function (r) { return r.i || r.pc || r.sup; });
			}
			// La forme gardée, lue sans rien exécuter : un « template » ne charge
			// ni image ni script.
			function naLieuDitLire(forme) {
				var runs = [];
				if (!forme) { return runs; }
				var t = document.createElement('template');
				t.innerHTML = String(forme);
				(function parcourir(n, m) {
					[].forEach.call(n.childNodes, function (c) {
						if (c.nodeType === 3) { naLieuDitPousser(runs, c.data, m); return; }
						if (c.nodeType !== 1) { return; }
						var b = c.tagName.toLowerCase();
						parcourir(c, {
							i: m.i || b === 'em' || b === 'i',
							pc: m.pc || (b === 'span' && c.classList.contains('na-pc')),
							sup: m.sup || b === 'sup'
						});
					});
				}(t.content || t, { i: false, pc: false, sup: false }));
				return naLieuDitRogner(runs);
			}
			// Les morceaux accordés au texte tel que le titre l'imprime : la
			// capitale initiale posée, le point final ôté. À défaut d'accord, le
			// texte seul.
			function naLieuDitAccorder(runs, texte) {
				texte = String(texte || '').trim();
				var copie = runs.map(function (r) { return { text: r.text, i: r.i, pc: r.pc, sup: r.sup }; });
				if (copie.length && naLieuDitTexte(copie) !== texte) {
					var n = copie.length - 1;
					if (/\.$/.test(copie[n].text) && !/\.$/.test(texte)) {
						copie[n].text = copie[n].text.slice(0, -1);
						naLieuDitRogner(copie);
					}
					copie = naLieuDitVirguleDesMorceaux(copie);
					if (copie.length) {
						copie[0].text = copie[0].text.replace(/^(\s*)(\S)/, function (tout, blancs, l) { return blancs + l.toUpperCase(); });
					}
				}
				return copie.length && naLieuDitTexte(copie) === texte ? copie : (texte ? [{ text: texte, i: false, pc: false, sup: false }] : []);
			}
			function naLieuDitHtml(runs) {
				return runs.map(function (r) {
					var b = r.text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
					if (r.sup) { b = '<sup>' + b + '</sup>'; }
					if (r.i) { b = '<em>' + b + '</em>'; }
					if (r.pc) { b = '<span class="na-pc">' + b + '</span>'; }
					return b;
				}).join('');
			}
			// Le lieu-dit d'un aperçu de titre : l'italique du titre, et celui de
			// l'auteur renversé dedans, comme au document.
			function naLieuDitPoser(parent, forme, texte, italique, classe) {
				var bloc = document.createElement('span');
				if (classe) { bloc.className = classe; }
				naLieuDitAccorder(naLieuDitLire(forme), texte).forEach(function (r) {
					var n = document.createTextNode(r.text);
					if (r.sup) { var s = document.createElement('sup'); s.appendChild(n); n = s; }
					if (!!italique !== !!r.i) { var e = document.createElement('em'); e.appendChild(n); n = e; }
					if (r.pc) { var p = document.createElement('span'); p.className = 'na-pc'; p.style.fontVariant = 'small-caps'; p.appendChild(n); n = p; }
					bloc.appendChild(n);
				});
				parent.appendChild(bloc);
				return bloc;
			}
			// L'éditeur d'une ligne, posé devant le champ brut qu'il remplit.
			function naLieuDitRiche(input, cache, reglages) {
				reglages = reglages || {};
				if (!input || !cache || typeof Quill === 'undefined' || !Quill.imports) { return null; }
				var Delta = Quill.import('delta');
				var pc = !!Quill.imports['formats/petitescapitales'];
				var lecture = !!reglages.lecture || input.disabled || input.readOnly;
				var boite = document.createElement('div');
				boite.className = 'na-lieu-dit-riche' + (reglages.classe ? ' ' + reglages.classe : '');
				var barre = document.createElement('div');
				barre.setAttribute('role', 'toolbar');
				barre.setAttribute('aria-label', 'Mise en forme du lieu-dit');
				var groupe = document.createElement('span');
				groupe.className = 'ql-formats';
				var boutons = [];
				function bouton(classe, valeur, libelle) {
					var b = document.createElement('button');
					b.type = 'button';
					b.className = classe;
					if (valeur) { b.value = valeur; }
					b.setAttribute('aria-label', libelle);
					b.setAttribute('aria-pressed', 'false');
					b.title = libelle;
					groupe.appendChild(b);
					boutons.push(b);
				}
				if (pc) { bouton('ql-petitescapitales', '', 'Petites capitales'); }
				// Le titre imprime le lieu-dit en italique : ce qu'on y met en
				// italique y paraît en romain. Le bouton le dit.
				bouton('ql-italic', '', reglages.italiqueDuTitre === false ? 'Italique' : 'Italique (en romain dans le titre, qui est en italique)');
				bouton('ql-script', 'super', 'Exposant');
				barre.appendChild(groupe);
				var zone = document.createElement('div');
				boite.appendChild(barre);
				boite.appendChild(zone);
				input.parentNode.insertBefore(boite, input);
				var q;
				try {
					q = new Quill(zone, {
						theme: 'snow',
						readOnly: lecture,
						formats: ['italic', 'script'].concat(pc ? ['petitescapitales'] : []),
						modules: {
							toolbar: { container: barre },
							// Une seule ligne : Entrée ne coupe pas le lieu-dit, et Tab
							// mène au champ suivant.
							keyboard: { bindings: {
								tab: { key: 'Tab', handler: function () { return true; } },
								entree: { key: 'Enter', shiftKey: null, handler: function () { return false; } }
							} }
						}
					});
				} catch (e) {
					boite.parentNode.removeChild(boite);
					return null;
				}
				var p = barre.querySelector('.ql-petitescapitales');
				if (p) { p.innerHTML = '<span aria-hidden="true">Pc</span>'; }
				// Cliquer un bouton ne fait pas quitter l'éditeur.
				barre.addEventListener('mousedown', function (e) { e.preventDefault(); });
				// Une barre d'outils, un seul arrêt de tabulation ; les flèches vont
				// d'un bouton à l'autre.
				function arret(b) { boutons.forEach(function (x) { x.setAttribute('tabindex', x === b ? '0' : '-1'); }); }
				arret(boutons[0]);
				boutons.forEach(function (b) {
					b.addEventListener('focus', function () { arret(b); });
					if (lecture) { b.disabled = true; }
				});
				barre.addEventListener('keydown', function (e) {
					var i = boutons.indexOf(document.activeElement);
					if (i < 0) { return; }
					var vers = e.key === 'ArrowRight' || e.key === 'Right' ? (i + 1) % boutons.length
						: e.key === 'ArrowLeft' || e.key === 'Left' ? (i + boutons.length - 1) % boutons.length
						: e.key === 'Home' ? 0 : e.key === 'End' ? boutons.length - 1 : -1;
					if (vers < 0) { return; }
					e.preventDefault();
					boutons[vers].focus();
				});

				// L'éditeur prend la place du champ, ses noms et ses états.
				var racine = q.root;
				var libelle = input.id ? document.querySelector('label[for="' + input.id + '"]') : null;
				if (libelle) {
					if (!libelle.id) { libelle.id = input.id + '-libelle'; }
					racine.setAttribute('aria-labelledby', libelle.id);
					libelle.addEventListener('click', function (e) { e.preventDefault(); q.focus(); });
				}
				racine.setAttribute('role', 'textbox');
				racine.setAttribute('aria-multiline', 'false');
				if (input.required || input.getAttribute('aria-required') === 'true') { racine.setAttribute('aria-required', 'true'); }
				if (lecture) { racine.setAttribute('aria-readonly', 'true'); }
				input.required = false;
				input.hidden = true;
				input.style.display = 'none';
				function recopier() {
					var d = input.getAttribute('aria-describedby');
					if (d) { racine.setAttribute('aria-describedby', d); } else { racine.removeAttribute('aria-describedby'); }
					var faux = input.getAttribute('aria-invalid') === 'true';
					if (faux) { racine.setAttribute('aria-invalid', 'true'); } else { racine.removeAttribute('aria-invalid'); }
					boite.classList.toggle('na-lieu-dit-riche--erreur', faux);
				}
				recopier();
				if (typeof MutationObserver !== 'undefined') {
					new MutationObserver(recopier).observe(input, { attributes: true, attributeFilter: ['aria-invalid', 'aria-describedby'] });
				}
				input.focus = function (o) { q.focus(o); };
				input.scrollIntoView = function (o) { boite.scrollIntoView(o); };

				function runsDeLEditeur() {
					var runs = [];
					q.getContents().ops.forEach(function (op) {
						if (typeof op.insert !== 'string') { return; }
						var a = op.attributes || {};
						naLieuDitPousser(runs, op.insert, { i: !!a.italic, pc: !!a.petitescapitales, sup: a.script === 'super' });
					});
					return naLieuDitRogner(runs);
				}
				function texteDeLEditeur() {
					return naLieuDitPropre(q.getText().replace(/\n$/, ''));
				}
				function formeDeLEditeur() {
					var runs = runsDeLEditeur();
					return naLieuDitMarque(runs) ? naLieuDitHtml(runs) : '';
				}
				var enCours = false;
				// Un retour à la ligne collé devient une espace : le lieu-dit tient
				// sur une ligne.
				function aplatir() {
					var ops = q.getContents().ops, n = ops.length, change = false;
					var neufs = [];
					ops.forEach(function (op, k) {
						if (typeof op.insert !== 'string') { change = true; return; }
						var t = op.insert, fin = '';
						if (k === n - 1 && /\n$/.test(t)) { t = t.slice(0, -1); fin = '\n'; }
						if (t.indexOf('\n') >= 0) { change = true; t = t.replace(/\n/g, ' '); }
						var o = { insert: t + fin };
						if (op.attributes) { o.attributes = op.attributes; }
						if (o.insert) { neufs.push(o); }
					});
					if (!change) { return; }
					var sel = q.getSelection();
					enCours = true;
					q.setContents(new Delta(neufs), 'silent');
					enCours = false;
					if (sel) { q.setSelection(sel.index, sel.length, 'silent'); }
				}
				function ecrire(annoncer) {
					input.value = texteDeLEditeur();
					cache.value = formeDeLEditeur();
					if (annoncer) { input.dispatchEvent(new Event('input', { bubbles: true })); }
				}
				q.on('text-change', function (delta, avant, source) {
					if (enCours) { return; }
					aplatir();
					ecrire(source !== 'silent');
				});
				function boutonsAJour() {
					var r = q.getSelection();
					if (!r) { return; }
					var f = q.getFormat(r);
					boutons.forEach(function (b) {
						var actif = b.classList.contains('ql-petitescapitales') ? !!f.petitescapitales
							: b.classList.contains('ql-italic') ? !!f.italic : f.script === 'super';
						b.setAttribute('aria-pressed', actif ? 'true' : 'false');
					});
				}
				q.on('editor-change', boutonsAJour);
				// En sortant, le champ brut reçoit sa sortie à lui — la capitale
				// initiale, la virgule après le numéro, les conseils —, et
				// l'éditeur reprend ce qu'il en a fait. Les lettres gardées
				// gardent leur mise en forme ; une lettre mise prend celle de la
				// lettre qu'elle remplace, ou de celle qui la précède.
				function reprendre() {
					var t = texteDeLEditeur(), v = input.value || '';
					if (t === v) { return; }
					enCours = true;
					var ecarts = naLieuDitEcarts(q.getText().replace(/\n$/, ''), v);
					if (ecarts) {
						for (var k = ecarts.length - 1; k >= 0; k--) {
							var e = ecarts[k], f = q.getFormat(e.n ? e.a : Math.max(0, e.a - 1), 1);
							if (e.n) { q.deleteText(e.a, e.n, 'silent'); }
							if (e.ins) { q.insertText(e.a, e.ins, f, 'silent'); }
						}
					} else {
						q.setText(v, 'silent');
					}
					enCours = false;
					cache.value = formeDeLEditeur();
				}
				boite.addEventListener('focusout', function (e) {
					if (e.relatedTarget && boite.contains(e.relatedTarget)) { return; }
					window.setTimeout(function () {
						if (boite.contains(document.activeElement)) { return; }
						input.dispatchEvent(new Event('blur'));
						reprendre();
					}, 0);
				});
				// Le champ brut et la forme gardée, remis dans l'éditeur : au
				// chargement, et quand un brouillon les a rendus. Une forme qui ne
				// dit plus le même texte s'oublie.
				function charger() {
					var texte = naLieuDitPropre(input.value || '');
					var runs = naLieuDitLire(cache.value);
					enCours = true;
					if (runs.length && naLieuDitMarque(runs) && naLieuDitTexte(runs) === texte.trim()) {
						var ops = runs.map(function (r) {
							var a = {};
							if (r.i) { a.italic = true; }
							if (r.pc && pc) { a.petitescapitales = true; }
							if (r.sup) { a.script = 'super'; }
							return Object.keys(a).length ? { insert: r.text, attributes: a } : { insert: r.text };
						});
						ops.push({ insert: '\n' });
						q.setContents(new Delta(ops), 'silent');
						cache.value = formeDeLEditeur();
					} else {
						q.setText(texte, 'silent');
						cache.value = '';
					}
					enCours = false;
					if (q.history) { q.history.clear(); }
				}
				charger();
				return { quill: q, boite: boite, charger: charger };
			}
JS;
	}
}
