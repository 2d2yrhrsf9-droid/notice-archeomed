<?php
/**
 * La mise en forme des textes d'une figure : titre, légende, description
 * détaillée. Petites capitales, italique, exposant, comme au lieu-dit.
 *
 * Chaque texte garde son champ brut — « titre », « legende »,
 * « description » — : c'est lui que lisent les contrôles, la liste, le
 * dossier et le texte alternatif. Sa mise en forme voyage à côté, sous
 * « titre_forme », « legende_forme », « description_forme », dans le HTML
 * restreint du lieu-dit. Le texte alternatif, que lit le lecteur d'écran,
 * n'en a jamais.
 *
 * Le serveur retouche les textes qu'il reçoit : il ôte « Fig. 3 » retapé au
 * début du titre, les blancs des bouts, les lignes vides de la description,
 * il ramène les blancs multiples à une espace, il coupe ce qui dépasse. La
 * forme est donc accordée au texte final, lettre à lettre : ce qui en sort
 * a toujours exactement le texte gardé, et seules les marques viennent de
 * la forme. Une forme qu'on ne peut pas accorder s'oublie, et le texte
 * s'imprime en romain — jamais une lettre perdue, jamais une erreur.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Forme {

	/** Les clefs des formes d'une figure, et le texte que chacune habille. */
	const CLEFS = array(
		'titre_forme'       => 'titre',
		'legende_forme'     => 'legende',
		'description_forme' => 'description',
	);

	/** Les bornes des textes, celles du dépôt : la coupe se reconnaît. */
	const BORNES = array(
		'titre'       => 400,
		'legende'     => 1500,
		'description' => 2000,
	);

	/** Les blancs que le serveur ramène l'un à l'autre. */
	const BLANC = '/^[\s\x{00A0}\x{202F}]$/u';

	/**
	 * La forme postée, réécrite en HTML canonique avant d'être gardée : une
	 * saisie mise de côté ne garde que les trois marques et le texte.
	 */
	public static function propre( $forme ) {
		$runs = self::lire( $forme );
		return Notice_Archeomed_Lieu_Dit::a_une_forme( $runs ) ? self::html( $runs, false ) : '';
	}

	/**
	 * La forme accordée au texte que le serveur a gardé, en HTML canonique ;
	 * une chaîne vide si elle n'a aucune marque ou ne lui correspond pas.
	 * « rang » ôte de la forme le numéro que le titre a perdu ; « max » est
	 * la borne du texte, pour reconnaître une coupe.
	 */
	public static function accorder( $forme, $texte, $options = array() ) {
		$runs = self::accordes( $forme, $texte, $options );
		return ( null !== $runs && Notice_Archeomed_Lieu_Dit::a_une_forme( $runs ) ) ? self::html( $runs, false ) : '';
	}

	/**
	 * Les morceaux d'un texte, prêts à imprimer : ceux de la forme accordée,
	 * ou le texte seul en un morceau. Leur texte est toujours le texte gardé.
	 */
	public static function morceaux_de( $forme, $texte, $options = array() ) {
		$texte = (string) $texte;
		$runs  = self::accordes( $forme, $texte, $options );
		if ( null !== $runs && Notice_Archeomed_Lieu_Dit::texte( $runs ) === $texte ) {
			return $runs;
		}
		return '' === $texte ? array() : array( array( 'text' => $texte, 'i' => false, 'pc' => false, 'sup' => false ) );
	}

	/**
	 * Les paragraphes d'une description, un par ligne non vide, les blancs
	 * ramenés à une espace — ceux que le document imprimait déjà —, chacun
	 * en morceaux. Leur texte est celui de « paragraphes simples ».
	 */
	public static function paragraphes( $forme, $texte ) {
		$texte  = (string) $texte;
		$tous   = self::morceaux_de( $forme, $texte, array( 'max' => self::BORNES['description'] ) );
		$sortie = array();
		$ligne  = array();
		$finir  = function () use ( &$ligne, &$sortie ) {
			$brut  = Notice_Archeomed_Lieu_Dit::texte( $ligne );
			$cible = Notice_Archeomed_Controles::une_ligne( $brut );
			if ( '' !== $cible ) {
				$runs     = self::aligner( $ligne, $cible, 0 );
				$sortie[] = null !== $runs ? $runs : array( array( 'text' => $cible, 'i' => false, 'pc' => false, 'sup' => false ) );
			}
			$ligne = array();
		};
		foreach ( self::caracteres( $tous ) as $c ) {
			// Les fins de ligne de « \R » : chacune ferme un paragraphe, et
			// les paragraphes vides s'oublient, comme au document.
			if ( preg_match( '/^[\n\r\x0B\x0C\x{0085}\x{2028}\x{2029}]$/u', $c[0] ) ) {
				$finir();
				continue;
			}
			$ligne[] = array( 'text' => $c[0], 'i' => $c[1], 'pc' => $c[2], 'sup' => $c[3] );
		}
		$finir();
		return $sortie;
	}

	/**
	 * Des morceaux en HTML, les retours à la ligne en « <br> ».
	 *
	 * La forme gardée échappe tout, pour que sa relecture redonne la même
	 * lettre. L'affichage échappe comme « esc_html », qui laisse « &lt; »
	 * dire « < » : le texte brut s'affichait ainsi. Les petites capitales y
	 * portent leur style, une copie envoyée par courriel n'ayant pas la
	 * feuille du site ; « $tete » est le texte qui précède, « Fig. 1 : »,
	 * pour que la typographie, demandée par « $typo », sache où elle est.
	 */
	public static function html( $runs, $affiche, $typo = false, $tete = '' ) {
		$runs  = array_values( (array) $runs );
		$tout  = $tete . Notice_Archeomed_Lieu_Dit::texte( $runs );
		$avant = $tete;
		$html  = '';
		foreach ( $runs as $run ) {
			$texte = (string) $run['text'];
			$rendu = $texte;
			if ( $typo && class_exists( 'Notice_Archeomed_Normes' ) ) {
				$rendu = Notice_Archeomed_Normes::typographie( $texte, $avant, $avant, (string) substr( $tout, strlen( $avant ) + strlen( $texte ) ) );
			}
			$avant .= $texte;
			$lignes = array();
			foreach ( explode( "\n", $rendu ) as $ligne ) {
				$lignes[] = $affiche
					? ( function_exists( 'esc_html' ) ? esc_html( $ligne ) : htmlspecialchars( $ligne, ENT_QUOTES, 'UTF-8', false ) )
					: htmlspecialchars( $ligne, ENT_NOQUOTES, 'UTF-8' );
			}
			$bouts = array();
			foreach ( $lignes as $bout ) {
				if ( '' !== $bout ) {
					if ( ! empty( $run['sup'] ) ) {
						$bout = '<sup>' . $bout . '</sup>';
					}
					if ( ! empty( $run['i'] ) ) {
						$bout = '<em>' . $bout . '</em>';
					}
					if ( ! empty( $run['pc'] ) ) {
						$bout = '<span class="na-pc"' . ( $affiche ? ' style="font-variant:small-caps"' : '' ) . '>' . $bout . '</span>';
					}
				}
				$bouts[] = $bout;
			}
			$html .= implode( '<br>', $bouts );
		}
		return $html;
	}

	/**
	 * Les morceaux précédés d'un texte sans marque — « : » après le numéro,
	 * « Description : » —, fondu dans le premier morceau quand il n'en a
	 * pas non plus : sans forme, le document reçoit le même fragment
	 * qu'avant.
	 */
	public static function precede( $tete, $runs ) {
		$runs = array_values( (array) $runs );
		if ( '' === (string) $tete ) {
			return $runs;
		}
		if ( ! empty( $runs ) && empty( $runs[0]['i'] ) && empty( $runs[0]['pc'] ) && empty( $runs[0]['sup'] ) ) {
			$runs[0]['text'] = $tete . $runs[0]['text'];
			return $runs;
		}
		array_unshift( $runs, array( 'text' => (string) $tete, 'i' => false, 'pc' => false, 'sup' => false ) );
		return $runs;
	}

	/**
	 * La forme d'une figure gardée ou postée, accordée à ses textes : rend
	 * les trois clefs, vides quand rien ne s'accorde. « $brut » porte les
	 * formes postées, « $item » les textes que le serveur a gardés.
	 */
	public static function accorder_la_figure( $brut, $item, $rang ) {
		$formes = array();
		foreach ( self::CLEFS as $clef => $champ ) {
			$forme   = isset( $brut[ $clef ] ) && is_string( $brut[ $clef ] ) ? $brut[ $clef ] : '';
			$texte   = isset( $item[ $champ ] ) && is_scalar( $item[ $champ ] ) ? (string) $item[ $champ ] : '';
			$options = array( 'max' => self::BORNES[ $champ ] );
			if ( 'titre' === $champ ) {
				$options['rang'] = (int) $rang;
			}
			$formes[ $clef ] = '' === $forme ? '' : self::accorder( $forme, $texte, $options );
		}
		return $formes;
	}

	// ── L'accord ─────────────────────────────────────────────────────────

	/** Les morceaux d'une forme, sur plusieurs lignes, sans jamais d'erreur. */
	private static function lire( $forme ) {
		if ( ! is_string( $forme ) || '' === $forme ) {
			return array();
		}
		try {
			return Notice_Archeomed_Lieu_Dit::morceaux( $forme, true );
		} catch ( Throwable $e ) {
			return array();
		}
	}

	/** Les morceaux de la forme accordés au texte, ou null. */
	private static function accordes( $forme, $texte, $options ) {
		$texte = (string) $texte;
		$runs  = self::lire( $forme );
		if ( '' === $texte || empty( $runs ) ) {
			return null;
		}
		try {
			// Le titre gardé a déjà perdu son « Fig. 3 » : la forme gardée
			// aussi, et elle s'accorde telle quelle. Celle qui arrive du
			// formulaire le perd comme le titre.
			$max     = isset( $options['max'] ) ? (int) $options['max'] : 0;
			$accorde = self::aligner( $runs, $texte, $max );
			if ( null === $accorde && ! empty( $options['rang'] ) ) {
				$accorde = self::aligner( self::sans_numero( $runs, (int) $options['rang'] ), $texte, $max );
			}
			return $accorde;
		} catch ( Throwable $e ) {
			return null;
		}
	}

	/**
	 * Les morceaux sans le « Fig. 3 : » que le serveur a ôté du titre : les
	 * mêmes octets, pris en tête.
	 */
	private static function sans_numero( $runs, $rang ) {
		$source = Notice_Archeomed_Lieu_Dit::texte( $runs );
		$reste  = Notice_Archeomed_Controles::titre_sans_numero( $source, $rang );
		$coupe  = strlen( $source ) - strlen( $reste );
		if ( $coupe <= 0 || substr( $source, $coupe ) !== $reste ) {
			return $runs;
		}
		$sortie = array();
		foreach ( $runs as $run ) {
			$long = strlen( $run['text'] );
			if ( $coupe >= $long ) {
				$coupe -= $long;
				continue;
			}
			$run['text'] = (string) substr( $run['text'], $coupe );
			$coupe       = 0;
			$sortie[]    = $run;
		}
		return $sortie;
	}

	/**
	 * Accorde les morceaux au texte, lettre à lettre. Rend des morceaux
	 * dont le texte est exactement « $texte », ou null.
	 *
	 * Les écarts permis sont ceux du serveur : les blancs de tête ôtés, un
	 * groupe de blancs — retours à la ligne et insécables compris — ramené à
	 * un autre, le chevron écrit « &lt; », une casse changée, un point final
	 * ôté, une fin coupée à la borne. Tout autre écart rend null.
	 */
	public static function aligner( $runs, $texte, $max = 0 ) {
		$source = self::caracteres( $runs );
		$cible  = preg_split( '//u', (string) $texte, -1, PREG_SPLIT_NO_EMPTY );
		if ( false === $cible || empty( $cible ) ) {
			return null;
		}
		$n    = count( $source );
		$m    = count( $cible );
		$i    = 0;
		$j    = 0;
		$sort = array();
		while ( $i < $n && self::blanc( $source[ $i ][0] ) ) {
			++$i;
		}
		while ( $j < $m ) {
			$c = $cible[ $j ];
			if ( self::blanc( $c ) ) {
				// Un groupe de blancs face à un groupe de blancs : on garde
				// ceux du texte, avec les marques du premier blanc de la forme.
				if ( $i >= $n || ! self::blanc( $source[ $i ][0] ) ) {
					return null;
				}
				$marques = $source[ $i ];
				while ( $i < $n && self::blanc( $source[ $i ][0] ) ) {
					++$i;
				}
				while ( $j < $m && self::blanc( $cible[ $j ] ) ) {
					$sort[] = array( $cible[ $j ], $marques[1], $marques[2], $marques[3] );
					++$j;
				}
				continue;
			}
			if ( $i >= $n ) {
				return null;
			}
			$s = $source[ $i ];
			if ( $s[0] === $c || ( function_exists( 'mb_strtolower' ) && mb_strtolower( $s[0], 'UTF-8' ) === mb_strtolower( $c, 'UTF-8' ) ) ) {
				$sort[] = array( $c, $s[1], $s[2], $s[3] );
				++$i;
				++$j;
				continue;
			}
			// « < » que le serveur a écrit « &lt; ».
			if ( '<' === $s[0] && '&' === $c && '&lt;' === implode( '', array_slice( $cible, $j, 4 ) ) ) {
				foreach ( array_slice( $cible, $j, 4 ) as $lettre ) {
					$sort[] = array( $lettre, $s[1], $s[2], $s[3] );
				}
				++$i;
				$j += 4;
				continue;
			}
			return null;
		}
		// Le reste de la forme : des blancs, un point final ôté, ou la fin
		// d'un texte coupé à sa borne.
		$reste = '';
		for ( ; $i < $n; $i++ ) {
			$reste .= $source[ $i ][0];
		}
		$coupe = $max > 0 && $m >= $max;
		if ( '' !== $reste && ! $coupe && ! preg_match( '/^[\s\x{00A0}\x{202F}]*\.?[\s\x{00A0}\x{202F}]*$/u', $reste ) ) {
			return null;
		}
		$runs = self::regrouper( $sort );
		return Notice_Archeomed_Lieu_Dit::texte( $runs ) === (string) $texte ? $runs : null;
	}

	/** Les lettres des morceaux, chacune avec ses trois marques. */
	private static function caracteres( $runs ) {
		$lettres = array();
		foreach ( (array) $runs as $run ) {
			$texte = isset( $run['text'] ) ? (string) $run['text'] : '';
			$bouts = '' === $texte ? array() : preg_split( '//u', $texte, -1, PREG_SPLIT_NO_EMPTY );
			foreach ( (array) $bouts as $c ) {
				$lettres[] = array( $c, ! empty( $run['i'] ), ! empty( $run['pc'] ), ! empty( $run['sup'] ) );
			}
		}
		return $lettres;
	}

	/** Les lettres remises en morceaux, ceux de mêmes marques fondus. */
	private static function regrouper( $lettres ) {
		$runs = array();
		foreach ( $lettres as $c ) {
			$d = count( $runs ) - 1;
			if ( $d >= 0 && $runs[ $d ]['i'] === $c[1] && $runs[ $d ]['pc'] === $c[2] && $runs[ $d ]['sup'] === $c[3] ) {
				$runs[ $d ]['text'] .= $c[0];
			} else {
				$runs[] = array( 'text' => $c[0], 'i' => $c[1], 'pc' => $c[2], 'sup' => $c[3] );
			}
		}
		return $runs;
	}

	private static function blanc( $c ) {
		return 1 === preg_match( self::BLANC, $c );
	}

	// ── L'éditeur ────────────────────────────────────────────────────────

	/**
	 * Le script des éditeurs d'une figure, à poser après celui du lieu-dit,
	 * dont il reprend le marquage et l'allure.
	 *
	 * Chaque éditeur remplit le champ brut, qui reste celui qu'on poste et
	 * que lisent le compteur, les avis et les contrôles ; la forme va dans
	 * le champ caché voisin. Sur plusieurs lignes, Entrée fait un nouveau
	 * paragraphe. Pour le texte pour l'écoute, le premier paragraphe — le
	 * texte alternatif — ne prend aucune marque : les boutons s'y éteignent,
	 * et ce qui s'y pose quand même s'ôte aussitôt. Sans Quill, rien ne
	 * change : le champ brut reste à l'écran.
	 */
	public static function script() {
		return <<<'JS'
			// ── Les textes d'une figure : petites capitales, italique, exposant ──
			// Comme au serveur : sur plusieurs lignes, le retour à la ligne
			// reste ; sur une seule, ce qui sépare devient une espace.
			function naFigurePropre(t, lignes) {
				t = String(t || '');
				t = lignes ? t.replace(/\r\n?/g, '\n').replace(/[\t\v\f]/g, ' ') : t.replace(/[\t\n\v\f\r]/g, ' ');
				return t.replace(/[\x00-\x08\x0E-\x1F\x7F]/g, '');
			}
			// Le texte d'une notice n'a qu'une liste, à puces : une liste
			// collée, numérotée ou à cocher, la rejoint. Avec « titres », un
			// titre de tout rang devient l'intertitre, le seul niveau.
			function naUneListeAPuces(delta, Delta, titres) {
				var aRevoir = function (a) {
					return !!a && ((!!a.list && a.list !== 'bullet') || (!!titres && !!a.header && a.header !== 3));
				};
				if (!delta.ops.some(function (op) { return aRevoir(op.attributes); })) { return delta; }
				return delta.reduce(function (d, op) {
					var a = op.attributes;
					if (!aRevoir(a)) { return d.insert(op.insert, a); }
					var b = {};
					Object.keys(a).forEach(function (k) { b[k] = a[k]; });
					if (b.list) { b.list = 'bullet'; }
					if (titres && b.header) { b.header = 3; }
					return d.insert(op.insert, b);
				}, new Delta());
			}
			var NA_SANS_MARQUE = { i: false, pc: false, sup: false };
			var NA_BLANC = /^[\s  ]$/;
			function naFigurePousser(runs, texte, m) {
				if (!texte) { return; }
				var d = runs[runs.length - 1];
				if (d && d.i === !!m.i && d.pc === !!m.pc && d.sup === !!m.sup) { d.text += texte; return; }
				runs.push({ text: texte, i: !!m.i, pc: !!m.pc, sup: !!m.sup });
			}
			// La forme gardée, lue sans rien exécuter ; « <br> » et la fin d'un
			// paragraphe y sont des retours à la ligne, sans marque.
			function naFigureLire(forme) {
				var runs = [];
				if (!forme) { return runs; }
				var t = document.createElement('template');
				t.innerHTML = String(forme);
				(function parcourir(n, m) {
					[].forEach.call(n.childNodes, function (c) {
						if (c.nodeType === 3) { naFigurePousser(runs, naFigurePropre(c.data, true), m); return; }
						if (c.nodeType !== 1) { return; }
						var b = c.tagName.toLowerCase();
						if (b === 'br') { naFigurePousser(runs, '\n', NA_SANS_MARQUE); return; }
						parcourir(c, {
							i: m.i || b === 'em' || b === 'i',
							pc: m.pc || (b === 'span' && c.classList.contains('na-pc')),
							sup: m.sup || b === 'sup'
						});
						if (b === 'p' || b === 'div') { naFigurePousser(runs, '\n', NA_SANS_MARQUE); }
					});
				}(t.content || t, NA_SANS_MARQUE));
				return runs;
			}
			// La forme écrite comme le serveur la garde : les lignes séparées
			// par « <br> », chacune dans ses marques.
			function naFigureHtml(runs) {
				return runs.map(function (r) {
					return r.text.split('\n').map(function (l) {
						if (!l) { return ''; }
						var b = l.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
						if (r.sup) { b = '<sup>' + b + '</sup>'; }
						if (r.i) { b = '<em>' + b + '</em>'; }
						if (r.pc) { b = '<span class="na-pc">' + b + '</span>'; }
						return b;
					}).join('<br>');
				}).join('');
			}
			function naFigureLettres(runs) {
				var l = [];
				runs.forEach(function (r) {
					r.text.split('').forEach(function (c) { l.push({ c: c, i: r.i, pc: r.pc, sup: r.sup }); });
				});
				return l;
			}
			function naFigureRegrouper(lettres) {
				var runs = [];
				lettres.forEach(function (x) { naFigurePousser(runs, x.c, x); });
				return runs;
			}
			// Les morceaux accordés au texte du champ, lettre à lettre : les
			// blancs se répondent par groupes, la casse peut changer, et la
			// forme peut finir plus loin que le texte — un crédit déplacé, une
			// fin coupée. Tout autre écart rend null, et le texte reste seul.
			function naFigureAccorder(runs, texte) {
				var src = naFigureLettres(runs), cib = String(texte || '').split(''), i = 0, j = 0, sortie = [];
				while (i < src.length && NA_BLANC.test(src[i].c)) { i++; }
				while (j < cib.length && NA_BLANC.test(cib[j])) { sortie.push({ c: cib[j], i: false, pc: false, sup: false }); j++; }
				while (j < cib.length) {
					var c = cib[j];
					if (NA_BLANC.test(c)) {
						if (i >= src.length || !NA_BLANC.test(src[i].c)) { return null; }
						var m = src[i];
						while (i < src.length && NA_BLANC.test(src[i].c)) { i++; }
						while (j < cib.length && NA_BLANC.test(cib[j])) { sortie.push({ c: cib[j], i: m.i, pc: m.pc, sup: m.sup }); j++; }
						continue;
					}
					if (i >= src.length) { return null; }
					// « < » que le serveur a gardé écrit « &lt; ».
					if (src[i].c === '<' && c === '&' && cib.slice(j, j + 4).join('') === '&lt;') {
						for (var k = 0; k < 4; k++) { sortie.push({ c: cib[j + k], i: src[i].i, pc: src[i].pc, sup: src[i].sup }); }
						i++;
						j += 4;
						continue;
					}
					if (src[i].c !== c && src[i].c.toLowerCase() !== c.toLowerCase()) { return null; }
					sortie.push({ c: c, i: src[i].i, pc: src[i].pc, sup: src[i].sup });
					i++;
					j++;
				}
				return naFigureRegrouper(sortie);
			}
			// Où finit le premier paragraphe non vide — le texte alternatif —,
			// fin de ligne comprise, comme le serveur les sépare (trim, puis
			// la première ligne qui reste).
			function naFigureApresLeTexteAlternatif(t) {
				var lignes = String(t || '').split('\n'), debut = 0;
				for (var k = 0; k < lignes.length; k++) {
					if (/[^ \t\r\0\x0B]/.test(lignes[k])) {
						return { debut: debut, fin: debut + lignes[k].length, suite: Math.min(t.length, debut + lignes[k].length + 1) };
					}
					debut += lignes[k].length + 1;
				}
				return null;
			}
			// L'éditeur d'un texte de figure, posé devant le champ brut qu'il
			// remplit. « lignes » : plusieurs paragraphes ; « premierBrut » : le
			// premier reste sans marque ; « garder » reçoit chaque forme écrite.
			function naFigureRiche(input, cache, reglages) {
				reglages = reglages || {};
				if (!input || !cache || !input.parentNode || typeof Quill === 'undefined' || !Quill.imports) { return null; }
				var Delta = Quill.import('delta');
				var pc = !!Quill.imports['formats/petitescapitales'];
				var lignes = !!reglages.lignes, premierBrut = !!reglages.premierBrut;
				var lecture = !!reglages.lecture || input.disabled || input.readOnly;
				var boite = document.createElement('div');
				boite.className = 'na-lieu-dit-riche na-figure-riche' + (lignes ? ' na-figure-riche--lignes' : '') + (reglages.classe ? ' ' + reglages.classe : '');
				var barre = document.createElement('div');
				barre.setAttribute('role', 'toolbar');
				barre.setAttribute('aria-label', reglages.barre || 'Mise en forme');
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
				bouton('ql-italic', '', 'Italique');
				bouton('ql-script', 'super', 'Exposant');
				barre.appendChild(groupe);
				var zone = document.createElement('div');
				boite.appendChild(barre);
				boite.appendChild(zone);
				var note = null;
				if (premierBrut) {
					note = document.createElement('p');
					note.className = 'na-help na-figure-note';
					note.id = (input.id || 'na-figure') + '-forme-note';
					note.textContent = 'Le premier paragraphe, lu à qui ne voit pas l’image, reste sans mise en forme'
						+ ' : petites capitales, italique et exposant valent pour la suite, à la ligne.';
					boite.appendChild(note);
				}
				input.parentNode.insertBefore(boite, input);
				var q;
				try {
					var touches = { tab: { key: 'Tab', handler: function () { return true; } } };
					// Sur une ligne, Entrée ne coupe pas le texte.
					if (!lignes) { touches.entree = { key: 'Enter', shiftKey: null, handler: function () { return false; } }; }
					q = new Quill(zone, {
						theme: 'snow',
						readOnly: lecture,
						formats: ['italic', 'script'].concat(pc ? ['petitescapitales'] : []),
						modules: { toolbar: { container: barre }, keyboard: { bindings: touches } }
					});
				} catch (e) {
					boite.parentNode.removeChild(boite);
					return null;
				}
				var p = barre.querySelector('.ql-petitescapitales');
				if (p) { p.innerHTML = '<span aria-hidden="true">Pc</span>'; }
				barre.addEventListener('mousedown', function (e) { e.preventDefault(); });
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
				// Dans le texte alternatif, un bouton éteint ne fait rien : le clic
				// s'arrête avant d'atteindre l'éditeur.
				var inertes = false;
				barre.addEventListener('click', function (e) {
					if (inertes && e.target && e.target.closest && e.target.closest('button')) {
						e.preventDefault();
						e.stopPropagation();
					}
				}, true);

				// L'éditeur prend la place du champ, ses noms et ses états.
				var racine = q.root;
				var libelle = input.id ? document.querySelector('label[for="' + input.id + '"]') : null;
				if (libelle) {
					if (!libelle.id) { libelle.id = input.id + '-libelle'; }
					racine.setAttribute('aria-labelledby', libelle.id);
					libelle.addEventListener('click', function (e) { e.preventDefault(); q.focus(); });
				}
				racine.setAttribute('role', 'textbox');
				racine.setAttribute('aria-multiline', lignes ? 'true' : 'false');
				// Le nom du champ, pour ceux qui reconnaissent un champ à son nom
				// quand on le quitte.
				if (input.name) { racine.setAttribute('data-na-nom', input.name); }
				if (input.required || input.getAttribute('aria-required') === 'true') { racine.setAttribute('aria-required', 'true'); }
				if (lecture) { racine.setAttribute('aria-readonly', 'true'); }
				input.required = false;
				input.hidden = true;
				input.style.display = 'none';
				function recopier() {
					var d = [input.getAttribute('aria-describedby') || '', note ? note.id : ''].join(' ').trim();
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

				function marques(a) {
					var f = {};
					if (a && a.italic) { f.italic = true; }
					if (a && a.petitescapitales && pc) { f.petitescapitales = true; }
					if (a && a.script === 'super') { f.script = 'super'; }
					return f;
				}
				// Les morceaux en opérations de l'éditeur : un retour à la ligne
				// n'y porte jamais de marque.
				function operations(runs) {
					var ops = [];
					runs.forEach(function (r) {
						var a = marques({ italic: r.i, petitescapitales: r.pc, script: r.sup ? 'super' : '' });
						r.text.split('\n').forEach(function (l, k) {
							if (k > 0) { ops.push({ insert: '\n' }); }
							if (l) { ops.push(Object.keys(a).length ? { insert: l, attributes: a } : { insert: l }); }
						});
					});
					return ops;
				}
				function texteDeLEditeur() {
					return naFigurePropre(q.getText().replace(/\n$/, ''), lignes);
				}
				function runsDeLEditeur() {
					var runs = [], ops = q.getContents().ops, n = ops.length;
					ops.forEach(function (op, k) {
						if (typeof op.insert !== 'string') { return; }
						var t = op.insert;
						if (k === n - 1) { t = t.replace(/\n$/, ''); }
						var a = op.attributes || {}, m = { i: !!a.italic, pc: !!a.petitescapitales, sup: a.script === 'super' };
						t.split('\n').forEach(function (l, j) {
							if (j > 0) { naFigurePousser(runs, '\n', NA_SANS_MARQUE); }
							naFigurePousser(runs, naFigurePropre(l, lignes), m);
						});
					});
					return runs;
				}
				function formeDeLEditeur() {
					var runs = runsDeLEditeur();
					if (premierBrut) {
						var lettres = naFigureLettres(runs);
						var coupe = naFigureApresLeTexteAlternatif(lettres.map(function (x) { return x.c; }).join(''));
						runs = coupe ? naFigureRegrouper(lettres.slice(coupe.suite)) : [];
					}
					return naLieuDitMarque(runs) ? naFigureHtml(runs) : '';
				}
				function garder(forme) {
					cache.value = forme;
					if (typeof reglages.garder === 'function') {
						try { reglages.garder(forme); } catch (e) { /* la forme reste au champ caché */ }
					}
				}
				var enCours = false;
				// Un retour à la ligne collé dans un texte d'une ligne devient une
				// espace.
				function aplatir() {
					var ops = q.getContents().ops, n = ops.length, change = false, neufs = [];
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
					q.setContents(new Delta(neufs), 'silent');
					if (sel) { q.setSelection(sel.index, sel.length, 'silent'); }
				}
				// Le texte alternatif sans marque, quoi qu'on y ait collé ou
				// tapé.
				function desmarquer() {
					if (!premierBrut) { return; }
					var coupe = naFigureApresLeTexteAlternatif(q.getText());
					if (!coupe || coupe.fin <= coupe.debut) { return; }
					var marque = q.getContents(coupe.debut, coupe.fin - coupe.debut).ops.some(function (op) {
						return Object.keys(marques(op.attributes)).length > 0;
					});
					if (!marque) { return; }
					var sans = { italic: false, script: false };
					if (pc) { sans.petitescapitales = false; }
					q.formatText(coupe.debut, coupe.fin - coupe.debut, sans, 'silent');
				}
				// Le champ brut, retouché par ce qui l'écoute — le texte alternatif
				// borné, un crédit déplacé —, se recopie dans l'éditeur : seul
				// l'endroit changé est réécrit, le reste garde ses marques.
				function suivre() {
					var t = texteDeLEditeur(), v = naFigurePropre(input.value || '', lignes);
					if (t === v) { return false; }
					var a = 0, n = Math.min(t.length, v.length), b = 0;
					while (a < n && t.charAt(a) === v.charAt(a)) { a++; }
					while (b < n - a && t.charAt(t.length - 1 - b) === v.charAt(v.length - 1 - b)) { b++; }
					var sel = q.getSelection(), neuf = v.slice(a, v.length - b);
					var f = a > 0 ? marques(q.getFormat(a - 1, 1)) : {};
					enCours = true;
					try {
						if (t.length - b > a) { q.deleteText(a, t.length - b - a, 'silent'); }
						if (neuf) { q.insertText(a, neuf, f, 'silent'); }
						desmarquer();
					} finally { enCours = false; }
					if (sel) {
						var pos = sel.index >= t.length - b ? sel.index + v.length - t.length : Math.min(sel.index, a + neuf.length);
						q.setSelection(Math.max(0, Math.min(pos, q.getLength() - 1)), 0, 'silent');
					}
					garder(formeDeLEditeur());
					return true;
				}
				function ecrire(annoncer) {
					input.value = texteDeLEditeur();
					garder(formeDeLEditeur());
					if (annoncer) { input.dispatchEvent(new Event('input', { bubbles: true })); }
				}
				// Ce qui dépasse la longueur permise au champ n'entre pas, comme
				// dans le champ lui-même : l'excédent s'ôte à la fin de ce qui
				// vient d'entrer, sinon juste avant le curseur.
				function borner(delta) {
					var max = input.maxLength;
					if (!(max > 0)) { return; }
					var exces = texteDeLEditeur().length - max;
					if (exces <= 0) { return; }
					var pos = 0, entre = -1;
					((delta && delta.ops) || []).forEach(function (op) {
						if (typeof op.retain === 'number') { pos += op.retain; }
						else if (typeof op.insert === 'string') { pos += op.insert.length; entre = pos; }
						else if (op.insert) { pos += 1; }
					});
					var sel = q.getSelection(), fin = entre >= 0 ? entre : sel ? sel.index : q.getLength() - 1;
					var debut = Math.max(0, fin - exces);
					q.deleteText(debut, fin - debut, 'silent');
					exces = texteDeLEditeur().length - max;
					if (exces > 0) { q.deleteText(Math.max(0, q.getLength() - 1 - exces), exces, 'silent'); }
				}
				q.on('text-change', function (delta, avant, source) {
					if (enCours) { return; }
					enCours = true;
					try {
						if (!lignes) { aplatir(); }
						borner(delta);
						desmarquer();
					} finally { enCours = false; }
					ecrire(source !== 'silent');
					suivre();
				});
				function boutonsAJour() {
					var r = q.getSelection();
					if (!r) { return; }
					if (premierBrut) {
						var coupe = naFigureApresLeTexteAlternatif(q.getText());
						inertes = !coupe || r.index + r.length <= coupe.fin;
						boutons.forEach(function (b) { b.setAttribute('aria-disabled', inertes ? 'true' : 'false'); });
						boite.classList.toggle('na-figure-riche--inerte', inertes);
					}
					var f = q.getFormat(r);
					boutons.forEach(function (b) {
						var actif = !inertes && (b.classList.contains('ql-petitescapitales') ? !!f.petitescapitales
							: b.classList.contains('ql-italic') ? !!f.italic : f.script === 'super');
						b.setAttribute('aria-pressed', actif ? 'true' : 'false');
					});
				}
				q.on('editor-change', boutonsAJour);
				boite.addEventListener('focusout', function (e) {
					if (e.relatedTarget && boite.contains(e.relatedTarget)) { return; }
					window.setTimeout(function () {
						if (boite.contains(document.activeElement)) { return; }
						input.dispatchEvent(new Event('blur'));
						suivre();
					}, 0);
				});
				// Le champ brut et la forme gardée, remis dans l'éditeur : au
				// chargement, quand un brouillon les a rendus, quand un crédit a
				// changé de champ. Une forme qui ne s'accorde plus au texte
				// s'oublie ; le texte, lui, reste entier.
				function charger() {
					var texte = naFigurePropre(input.value || '', lignes), tete = '', queue = texte;
					if (premierBrut) {
						var coupe = naFigureApresLeTexteAlternatif(texte);
						tete = coupe ? texte.slice(0, coupe.suite) : texte;
						queue = coupe ? texte.slice(coupe.suite) : '';
					}
					var runs = naFigureLire(cache.value);
					var accord = queue && naLieuDitMarque(runs) ? naFigureAccorder(runs, queue) : null;
					enCours = true;
					try {
						if (accord && naLieuDitMarque(accord) && naLieuDitTexte(accord) === queue) {
							var ops = tete ? [{ insert: tete }] : [];
							q.setContents(new Delta(ops.concat(operations(accord), [{ insert: '\n' }])), 'silent');
						} else {
							q.setText(texte, 'silent');
						}
						desmarquer();
					} finally { enCours = false; }
					// Un éditeur qui aurait perdu une lettre rend la main au texte
					// seul.
					if (texteDeLEditeur().replace(/\n+$/, '') !== texte.replace(/\n+$/, '')) {
						enCours = true;
						try { q.setText(texte, 'silent'); } finally { enCours = false; }
					}
					garder(formeDeLEditeur());
					if (q.history) { q.history.clear(); }
				}
				charger();
				return { quill: q, boite: boite, charger: charger };
			}
JS;
	}
}
