// La virgule après le numéro d'une voie, et la mise en forme qu'elle garde.
// Les fonctions naLieuDit… viennent du script tel que la page le sert ; ce
// qui manque s'inscrit dans « faux ».
var faux = [];
function egal(nom, vu, attendu) {
	var a = JSON.stringify(vu), b = JSON.stringify(attendu);
	if (a !== b) { faux.push(nom + ' donne ' + a + ', attendu ' + b); }
}

// La virgule : après un numéro en tête, ou après une virgule ou un
// point-virgule, devant un nom de voie seulement. Les cas (virgule.json,
// lu dans V) sont ceux du serveur.
V.forEach(function (c) { egal('« ' + c[0] + ' »', naLieuDitVirgule(c[0]), c[1]); });
egal('deux fois', naLieuDitVirgule(naLieuDitVirgule('53 rue machin')), '53, rue machin');

// La mise en forme accordée au texte qui a reçu la virgule.
function m(text, i) { return { text: text, i: !!i, pc: false, sup: false }; }
egal('l’italique après la voie', naLieuDitAccorder([m('53 rue du '), m('Pourquoi-Pas', true)], '53, rue du Pourquoi-Pas'),
	[m('53, rue du '), m('Pourquoi-Pas', true)]);
egal('le numéro en italique', naLieuDitAccorder([m('53 ', true), m('Rue Haute')], '53, rue Haute'), [m('53, rue', true), m(' Haute')]);
egal('la virgule et la capitale', naLieuDitAccorder([m('château, 3 rue du '), m('Roi', true)], 'Château, 3, rue du Roi'),
	[m('Château, 3, rue du '), m('Roi', true)]);
egal('la virgule et le point', naLieuDitAccorder([m('3 rue du '), m('Moulin', true), m('.')], '3, rue du Moulin'),
	[m('3, rue du '), m('Moulin', true)]);
egal('un autre texte', naLieuDitAccorder([m('3 rue du '), m('Roi', true)], '4, rue du Roi'), [m('4, rue du Roi')]);

// Ce qui change d'un texte à l'autre : appliqué, il redonne le second.
function appliquer(a, ecarts) {
	for (var k = ecarts.length - 1; k >= 0; k--) {
		var e = ecarts[k];
		a = a.slice(0, e.a) + e.ins + a.slice(e.a + e.n);
	}
	return a;
}
egal('« 53 Rue X »', naLieuDitEcarts('53 Rue X', '53, rue X'), [{ a: 2, n: 0, ins: ',' }, { a: 3, n: 1, ins: 'r' }]);
egal('rien', naLieuDitEcarts('Château', 'Château'), []);
egal('la capitale', naLieuDitEcarts('château', 'Château'), [{ a: 0, n: 1, ins: 'C' }]);
[['', 'Bourg'], ['Bourg', ''], ['château, 3 rue du Roi.', 'Château, 3, rue du Roi'], ['3  bis   place X', '3 bis, place X'],
	['abc', 'xyz'], ['Le Bourg', 'Bourg le Haut']].forEach(function (c) {
	egal('« ' + c[0] + ' » vers « ' + c[1] + ' »', appliquer(c[0], naLieuDitEcarts(c[0], c[1])), c[1]);
});
egal('trop long', naLieuDitEcarts(new Array(500).join('a'), new Array(500).join('b')), null);
