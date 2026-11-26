<?php
/**
 * Les propositions de lieu-dit, d'après la Géoplateforme de l'IGN.
 *
 * Quand la commune est connue, le champ du lieu-dit propose, à mesure qu'on
 * tape, les voies et les lieux nommés de cette commune : « 12, rue des
 * carm » offre « 12, rue des Carmes », « abbaye aux d » l'Abbaye aux Dames.
 * Rien ne s'impose : la liste se referme sans rien changer, et ce qu'on a
 * tapé reste tel quel.
 *
 * Le navigateur interroge le service lui-même, comme il le fait de Pactols :
 * le serveur du site n'y prend aucune part et n'en attend rien. Une commune
 * que l'IGN ne départage pas — deux homonymes sans département qui les
 * sépare —, un service muet ou lent, et la liste ne paraît pas : rien n'est
 * dit, rien ne manque au dépôt. Un réglage coupe le tout.
 *
 * Les fonctions « naIgn… » qui ne touchent pas la page sont essayées en CI,
 * avec les réponses telles que l'IGN les donne.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Geocodage {

	/** Le service de géocodage de la Géoplateforme (CORS ouvert, sans clé). */
	const SERVICE = 'https://data.geopf.fr/geocodage/search';

	/** Les propositions sont-elles offertes ? « non » dans les réglages les coupe. */
	public static function actif() {
		return 'non' !== Notice_Archeomed_Settings::get( 'ign_lieu_dit' );
	}

	/** L'adresse que le navigateur interroge, vide quand le réglage coupe le service. */
	public static function service() {
		return self::actif() ? self::SERVICE : '';
	}

	/**
	 * Le script : des fonctions pures, puis le branchement sur l'éditeur du
	 * lieu-dit. Il s'insère dans le script du formulaire, dont il emploie
	 * les outils — la liste de propositions, l'appel borné dans le temps,
	 * les annonces.
	 */
	public static function script() {
		return <<<'JS'
			// Plié pour comparer : casse, accents, apostrophes, ligatures ;
			// tirets et ponctuation deviennent des blancs.
			function naIgnPlier(t) {
				t = String(t || '').toLowerCase().replace(/[’‘ʼ`]/g, '\'').replace(/œ/g, 'oe').replace(/æ/g, 'ae');
				if (t.normalize) { t = t.normalize('NFD').replace(/[̀-ͯ]/g, ''); }
				return t.replace(/[^a-z0-9]+/g, ' ').trim();
			}
			// Les abréviations d'une adresse, que l'IGN écrit en entier.
			var NA_IGN_ABREGES = { st: 'saint', ste: 'sainte', sts: 'saints', stes: 'saintes', bd: 'boulevard', bld: 'boulevard',
				av: 'avenue', ave: 'avenue', pl: 'place', imp: 'impasse', rte: 'route', fg: 'faubourg', fbg: 'faubourg', chem: 'chemin', sq: 'square' };
			// Les petits mots qui ne départagent rien : « rue de la paix »
			// trouve « Rue de la Paix » sans qu'on les compte.
			var NA_IGN_VIDES = { le: 1, la: 1, les: 1, l: 1, de: 1, d: 1, du: 1, des: 1, au: 1, aux: 1, a: 1, et: 1, en: 1, sur: 1, sous: 1, lez: 1 };
			function naIgnMots(t) {
				return naIgnPlier(t).split(' ').filter(Boolean);
			}
			// Les modifications qui séparent deux mots, à une près : au-delà,
			// on s'arrête.
			function naIgnDistance(a, b) {
				if (a === b) { return 0; }
				if (Math.abs(a.length - b.length) > 1) { return 2; }
				var i = 0;
				while (i < a.length && i < b.length && a.charAt(i) === b.charAt(i)) { i++; }
				var x = a.slice(i + 1), y = b.slice(i + 1);
				if (a.length === b.length) {
					// Une lettre changée, ou deux lettres voisines échangées.
					return (x === y || (a.charAt(i) === b.charAt(i + 1) && b.charAt(i) === a.charAt(i + 1) && a.slice(i + 2) === b.slice(i + 2))) ? 1 : 2;
				}
				return (a.length > b.length ? a.slice(i + 1) === b.slice(i) : b.slice(i + 1) === a.slice(i)) ? 1 : 2;
			}
			// Le nom proposé répond-il à ce qu'on a tapé ? Chaque mot tapé y
			// trouve le sien — le dernier en début de mot, puisqu'on l'écrit
			// encore ; les autres entiers, une faute admise dès cinq lettres.
			// L'IGN rend volontiers des voisins : « rue des carm » lui fait
			// proposer la place des Carmes, que ce tri écarte.
			function naIgnRessemble(tape, nom) {
				var q = naIgnMots(tape), n = naIgnMots(nom), pris = [];
				if (!q.length || !n.length) { return false; }
				for (var k = 0; k < q.length; k++) {
					var w = q[k], dernier = k === q.length - 1;
					if (!dernier && NA_IGN_VIDES[w]) { continue; }
					var formes = NA_IGN_ABREGES[w] ? [w, NA_IGN_ABREGES[w]] : [w];
					var trouve = -1;
					for (var j = 0; j < n.length && trouve < 0; j++) {
						if (pris.indexOf(j) !== -1) { continue; }
						for (var f = 0; f < formes.length; f++) {
							var v = formes[f];
							if (dernier ? n[j].indexOf(v) === 0
								: (n[j] === v || (v.length >= 5 && naIgnDistance(v, n[j]) <= 1))) { trouve = j; break; }
						}
					}
					if (trouve < 0) { return false; }
					pris.push(trouve);
				}
				return true;
			}
			// Le lieu-dit tapé, en trois morceaux : ce qui précède et reste tel
			// quel (« Château, »), le numéro (« 12 »), et la voie qu'on écrit,
			// seule cherchée. « a » est l'endroit où commence ce qu'un choix
			// remplace. Un numéro se reconnaît suivi d'une virgule ou d'un
			// blanc : « 12, rue… » comme « 12 rue… », « 3 bis, place… ».
			function naIgnDecouper(texte) {
				texte = String(texte || '');
				var i = Math.max(texte.lastIndexOf(';'), texte.lastIndexOf(','));
				var avant = i >= 0 ? texte.slice(0, i + 1) : '';
				var voie = i >= 0 ? texte.slice(i + 1) : texte;
				var numero = '';
				var NUMERO = '(\\d{1,5}(?:\\s*(?:bis|ter|quater|[a-z]))?)';
				var m = new RegExp('^\\s*' + NUMERO + '\\s+(?=\\S)', 'i').exec(voie);
				if (m) {
					numero = m[1];
					voie = voie.slice(m[0].length);
				} else {
					var p = new RegExp('(^|[;,])(\\s*)' + NUMERO + '\\s*,$', 'i').exec(avant);
					if (p) {
						numero = p[3];
						avant = avant.slice(0, p.index + p[1].length);
					}
				}
				avant = avant.replace(/\s+$/, '');
				return { debut: avant, numero: numero.replace(/\s+/g, ' '), voie: voie.replace(/\s+/g, ' ').trim(), a: avant.length };
			}
			// Ce qui vaut d'être cherché : trois lettres au moins, et pas un
			// titre de projet entier.
			function naIgnCherchable(voie) {
				return naIgnPlier(voie).replace(/[^a-z]/g, '').length >= 3 && String(voie).length <= 100;
			}
			// Le nom de la voie, après un numéro ou une virgule, commence par
			// une minuscule : « 12, rue des Carmes ». En tête du lieu-dit, il
			// prend la capitale.
			var NA_IGN_GENERIQUES = ['rue', 'ruelle', 'place', 'placette', 'avenue', 'boulevard', 'chemin', 'impasse', 'allee',
				'quai', 'route', 'square', 'cours', 'passage', 'sentier', 'faubourg', 'rond point', 'promenade', 'esplanade',
				'voie', 'venelle', 'montee', 'cote', 'carrefour', 'parvis', 'cite', 'traverse', 'mail', 'sente', 'ruette'];
			function naIgnRemplacement(morceaux, nom) {
				nom = String(nom || '').replace(/\s+/g, ' ').trim();
				var suite = !!(morceaux.numero || morceaux.debut);
				if (suite) {
					var m = /^[^\s'’]+/.exec(nom);
					if (m && NA_IGN_GENERIQUES.indexOf(naIgnPlier(m[0])) !== -1) { nom = m[0].toLowerCase() + nom.slice(m[0].length); }
				} else {
					nom = nom.charAt(0).toUpperCase() + nom.slice(1);
				}
				return (morceaux.debut ? ' ' : '') + (morceaux.numero ? morceaux.numero + ', ' : '') + nom;
			}
			// Paris, Lyon et Marseille rangent leurs voies sous le code de
			// l'arrondissement : la rue Mouffetard est au 75105, non au 75056.
			function naIgnDeLaCommune(commune, code) {
				code = String(code || '');
				if (code === commune) { return true; }
				var arr = { '75056': /^751(0[1-9]|1[0-9]|20)$/, '69123': /^6938[1-9]$/, '13055': /^132(0[1-9]|1[0-6])$/ };
				return !!(arr[commune] && arr[commune].test(code));
			}
			// La commune tapée, chez l'IGN : le même nom, et le département ou
			// la région de la parenthèse dans son contexte (« 14, Calvados,
			// Normandie »). Faute de parenthèse, un nom que porte une seule
			// commune. Autrement rien : mieux vaut ne rien proposer que les
			// rues d'un homonyme.
			function naIgnCommune(reponses, nom, dept) {
				var cle = naIgnPlier(nom), d = naIgnPlier(dept);
				if (!cle) { return ''; }
				var memes = (reponses || []).filter(function (f) {
					var p = f && f.properties;
					return !!p && p.type === 'municipality' && naIgnPlier(p.name) === cle
						&& /^(\d{5}|2[AB]\d{3})$/.test(String(p.citycode || ''));
				});
				if (d) {
					memes = memes.filter(function (f) {
						return String(f.properties.context || '').split(',').some(function (x) { return naIgnPlier(x) === d; });
					});
				}
				return memes.length === 1 ? String(memes[0].properties.citycode) : '';
			}
			// Les catégories de lieux que l'on ne propose pas : arrêts, stations
			// et ouvrages d'eau portent le nom d'un lieu sans en être un.
			var NA_IGN_ECARTES = /^(transport|equipement de transport|chateau d eau|reservoir|reservoir d eau ou chateau d eau au sol)$/;
			// Les propositions, des deux réponses : les voies et lieux-dits de
			// l'index des adresses, puis les lieux nommés. Celles qui commencent
			// par ce qu'on a tapé d'abord ; un nom ne paraît qu'une fois ; six
			// au plus.
			function naIgnNoms(adresses, lieux, commune, tape) {
				var vus = {}, tete = [], suite = [], debut = naIgnPlier(tape);
				function prendre(nom, genre) {
					nom = String(nom || '').replace(/\s+/g, ' ').trim();
					if (!nom || nom.length > 150) { return; }
					var cle = naIgnPlier(nom);
					if (!cle || vus[cle] || !naIgnRessemble(tape, nom)) { return; }
					vus[cle] = true;
					(cle.indexOf(debut) === 0 ? tete : suite).push({ nom: nom, genre: genre });
				}
				(adresses || []).forEach(function (f) {
					var p = f && f.properties;
					if (!p || (p.type !== 'street' && p.type !== 'locality') || !naIgnDeLaCommune(commune, p.citycode)) { return; }
					prendre(p.name, p.type === 'street' ? 'voie' : 'lieu-dit');
				});
				(lieux || []).forEach(function (f) {
					var p = f && f.properties;
					if (!p) { return; }
					var codes = [].concat(p.citycode || []);
					if (!codes.some(function (c) { return naIgnDeLaCommune(commune, c); })) { return; }
					var genres = [].concat(p.category || []).map(String);
					if (genres.some(function (g) { return NA_IGN_ECARTES.test(naIgnPlier(g)); })) { return; }
					prendre([].concat(p.name || [])[0], genres[0] || '');
				});
				return tete.concat(suite).slice(0, 6);
			}

			// ── Le branchement sur l'éditeur du lieu-dit ──────────────────
			// « riche » est ce que rend naLieuDitRiche ; « o » donne le service,
			// le formulaire et le champ de la parenthèse.
			function naIgnLieuDit(riche, o) {
				if (!riche || !riche.quill || !o || !o.service || typeof Promise === 'undefined') { return null; }
				var q = riche.quill, racine = q.root, Delta = Quill.import('delta');
				var liste = document.createElement('ul');
				liste.className = 'na-suggestions';
				liste.id = 'na-lieu-dit-liste';
				liste.hidden = true;
				riche.boite.appendChild(liste);
				var cb = armerCombobox(racine, liste, choisir);
				liste.setAttribute('aria-label', 'Propositions pour le lieu-dit');
				var communes = {}, reponses = {}, tour = 0, minuterie = null;
				function demander(params) {
					return chercher(o.service + '?' + params).then(function (r) {
						return r && Array.isArray(r.features) ? r.features : [];
					});
				}
				// Le code de la commune : la première qu'on a écrite, avec la
				// parenthèse pour départager. L'IGN ne cherche pas moins de trois
				// signes : Eu et Y restent sans propositions.
				function laCommune() {
					var champs = o.form.querySelectorAll('.na-commune'), nom = '';
					for (var i = 0; i < champs.length && !nom; i++) { nom = nomSansPrecision(champs[i].value); }
					var dept = sansParentheses(o.dept.value);
					if (String(nom).length < 3 || String(nom).length > 200) { return Promise.resolve(''); }
					var cle = naIgnPlier(nom) + '|' + naIgnPlier(dept);
					if (!communes[cle]) {
						communes[cle] = demander('q=' + encodeURIComponent(nom) + '&index=address&type=municipality&limit=20')
							.then(function (f) { return naIgnCommune(f, nom, dept); })
							.catch(function () { delete communes[cle]; return ''; });
					}
					return communes[cle];
				}
				function propositions(code, voie) {
					var cle = code + '|' + naIgnPlier(voie);
					if (!reponses[cle]) {
						var t = encodeURIComponent(voie);
						var a = demander('q=' + t + '&index=address&citycode=' + code + '&autocomplete=1&limit=10').catch(function () { return null; });
						var b = demander('q=' + t + '&index=poi&citycode=' + code + '&limit=10').catch(function () { return null; });
						reponses[cle] = Promise.all([a, b]).then(function (r) {
							// Un service muet n'est pas une réponse : on redemandera.
							if (r[0] === null && r[1] === null) { delete reponses[cle]; return []; }
							if (r[0] === null || r[1] === null) { delete reponses[cle]; }
							return naIgnNoms(r[0] || [], r[1] || [], code, voie);
						});
					}
					return reponses[cle];
				}
				function texte() { return q.getText().replace(/\n$/, ''); }
				function chercherIci() {
					var n = ++tour, t = texte(), sel = q.getSelection();
					// On ne propose que pour ce qu'on écrit au bout du champ.
					if (!sel || sel.length || sel.index < t.length) { cb.fermer(); return; }
					var morceaux = naIgnDecouper(t);
					if (!naIgnCherchable(morceaux.voie)) { cb.fermer(); return; }
					laCommune().then(function (code) {
						if (n !== tour) { return; }
						if (!code) { cb.fermer(); return; }
						return propositions(code, morceaux.voie).then(function (noms) {
							if (n !== tour) { return; }
							// Ce qu'on a déjà écrit en entier ne se propose plus.
							noms = noms.filter(function (x) { return naIgnPlier(x.nom) !== naIgnPlier(morceaux.voie); });
							cb.ouvrir(noms, function (li, x) {
								var s = document.createElement('span');
								s.textContent = naIgnRemplacement(morceaux, x.nom).replace(/^\s+/, '');
								li.appendChild(s);
								if (x.genre) { precision(li, x.genre); }
							});
						});
					}).catch(function () { if (n === tour) { cb.fermer(); } });
				}
				// La voie choisie remplace celle qu'on tapait ; ce qui précède
				// reste, numéro compris, et la mise en forme de la voie se garde
				// (l'exposant non : un nom de rue n'en porte pas).
				function choisir(x) {
					tour++;
					var t = texte(), m = naIgnDecouper(t);
					var neuf = naIgnRemplacement(m, x.nom);
					// La forme est celle de la lettre qu'on vient de taper.
					var f = t.length ? q.getFormat(t.length - 1, 1) : {}, garde = {};
					if (f.italic) { garde.italic = true; }
					if (f.petitescapitales) { garde.petitescapitales = true; }
					q.updateContents(new Delta().retain(m.a).delete(t.length - m.a).insert(neuf, garde), 'api');
					q.setSelection(m.a + neuf.length, 0, 'silent');
					annoncer(guillemets(neuf.replace(/^\s+/, '')) + ' retenu.');
				}
				q.on('text-change', function (d, avant, source) {
					if (source !== 'user') { return; }
					window.clearTimeout(minuterie);
					minuterie = window.setTimeout(chercherIci, 350);
				});
				return { liste: liste, fermer: cb.fermer };
			}
JS;
	}
}
