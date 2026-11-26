// Les propositions de lieu-dit, sur des réponses réelles de l'IGN (ign.json,
// lu dans D). Les fonctions naIgn… viennent du script tel que la page le
// sert ; ce qui manque s'inscrit dans « faux ».
var faux = [];
function egal(nom, vu, attendu) {
	var a = JSON.stringify(vu), b = JSON.stringify(attendu);
	if (a !== b) { faux.push(nom + ' donne ' + a + ', attendu ' + b); }
}
function noms(l) { return l.map(function (x) { return x.nom + ' (' + x.genre + ')'; }); }

// Le découpage : numéro avec ou sans virgule, ce qui précède gardé.
egal('« 12, rue des carm »', naIgnDecouper('12, rue des carm'), { debut: '', numero: '12', voie: 'rue des carm', a: 0 });
egal('« 12 rue des carm »', naIgnDecouper('12 rue des carm'), { debut: '', numero: '12', voie: 'rue des carm', a: 0 });
egal('« Château, abbaye aux d »', naIgnDecouper('Château, abbaye aux d'), { debut: 'Château,', numero: '', voie: 'abbaye aux d', a: 8 });
egal('« Château, 3 bis, place s »', naIgnDecouper('Château, 3 bis, place s'), { debut: 'Château,', numero: '3 bis', voie: 'place s', a: 8 });
egal('« Château ; 4 rue s »', naIgnDecouper('Château ; 4 rue s'), { debut: 'Château ;', numero: '4', voie: 'rue s', a: 9 });
egal('« rue des carm »', naIgnDecouper('rue des carm'), { debut: '', numero: '', voie: 'rue des carm', a: 0 });
egal('« 1944 »', naIgnDecouper('1944'), { debut: '', numero: '', voie: '1944', a: 0 });
egal('« 12, »', naIgnDecouper('12,').voie, '');

// Ce qui se cherche.
egal('trois lettres', [naIgnCherchable('ru'), naIgnCherchable('rue'), naIgnCherchable('1944'), naIgnCherchable('12 b'),
	naIgnCherchable(new Array(102).join('a'))], [false, true, false, false, false]);

// La commune : le département ou la région départage, sinon rien.
egal('Caen', [naIgnCommune(D['commune-caen'], 'Caen', 'Calvados'), naIgnCommune(D['commune-caen'], 'Caen', '')], ['14118', '14118']);
egal('Falaise', [naIgnCommune(D['commune-falaise'], 'Falaise', 'Calvados'), naIgnCommune(D['commune-falaise'], 'Falaise', 'Ardennes'),
	naIgnCommune(D['commune-falaise'], 'Falaise', ''), naIgnCommune(D['commune-falaise'], 'Falaise', 'Normandie'),
	naIgnCommune(D['commune-falaise'], 'falaise', 'calvados'), naIgnCommune(D['commune-falaise'], 'Falaise', 'Gironde')],
	['14258', '08164', '', '14258', '14258', '']);
egal('Paris', [naIgnCommune(D['commune-paris'], 'Paris', ''), naIgnCommune(D['commune-paris'], 'Paris', 'Paris')], ['75056', '75056']);
egal('Coulonces, commune déléguée', naIgnCommune(D['commune-coulonces'], 'Coulonces', 'Calvados'), '');
egal('réponse vide', [naIgnCommune([], 'Caen', ''), naIgnCommune(null, 'Caen', ''), naIgnCommune(D['commune-caen'], '', '')], ['', '', '']);

// Les propositions : le tri écarte les voisins, les arrêts, les doublons.
egal('Caen, « rue des carm »', noms(naIgnNoms(D['caen-carm-adr'], D['caen-carm-poi'], '14118', 'rue des carm')),
	['Rue des Carmes (voie)', 'Rue des Carmélites (voie)']);
egal('Caen, « abbaye aux d »', noms(naIgnNoms(D['caen-abb-adr'], D['caen-abb-poi'], '14118', 'abbaye aux d')),
	['Abbaye aux Dames (construction)']);
egal('Caen, « rue st jean »', noms(naIgnNoms(D['caen-stj-adr'], D['caen-stj-poi'], '14118', 'rue st jean')),
	['Rue Saint-Jean (voie)', 'Rue Neuve Saint-Jean (voie)']);
var stp = noms(naIgnNoms(D['caen-stp-adr'], D['caen-stp-poi'], '14118', 'saint pierre'));
egal('Caen, « saint pierre », en tête', stp[0], 'Saint-Pierre (culte chrétien)');
egal('Caen, « saint pierre », six au plus', stp.length, 6);
if (stp.join().indexOf('tramway') !== -1 || stp.join().indexOf('Port de Caen') !== -1) { faux.push('Caen, « saint pierre » propose un arrêt : ' + stp.join(' | ')); }
if (stp.filter(function (x) { return x.indexOf('Bassin Saint-Pierre (') === 0; }).length > 1) { faux.push('Caen, « saint pierre » propose deux fois le bassin'); }
egal('Paris, « rue mouff », par l\'arrondissement', noms(naIgnNoms(D['paris-mouf-adr'], D['paris-mouf-poi'], '75056', 'rue mouff')),
	['Rue Mouffetard (voie)']);
var nd = noms(naIgnNoms([], D['paris-notre-poi'], '75056', 'notre dame'));
if (nd.join().indexOf('métro') !== -1) { faux.push('Paris, « notre dame » propose une station de métro'); }
if (nd.indexOf('Cathédrale Notre-Dame (culte chrétien)') === -1) { faux.push('Paris, « notre dame » perd la cathédrale : ' + nd.join(' | ')); }
var fal = noms(naIgnNoms(D['falaise-chat-adr'], D['falaise-chat-poi'], '14258', 'chateau'));
if (/château d'eau|réservoir/i.test(fal.join())) { faux.push('Falaise, « chateau » propose un château d\'eau : ' + fal.join(' | ')); }
egal('Falaise, « chateau », en tête', fal[0], 'Château (château)');
egal('une autre commune', naIgnNoms(D['caen-carm-adr'], D['caen-carm-poi'], '14258', 'rue des carm'), []);
egal('réponses vides', naIgnNoms(null, undefined, '14118', 'rue des carm'), []);

// Ce qui ressemble, et ce qui ne ressemble pas.
egal('ressemblances', [naIgnRessemble('rue des carm', 'Rue des Carmes'), naIgnRessemble('rue des carm', 'Place des Carmes'),
	naIgnRessemble('rue st jean', 'Rue Saint-Jean'), naIgnRessemble('rue de la paix', 'Rue de la Paix'),
	naIgnRessemble('rue de la pai', 'Rue de la Paix'), naIgnRessemble('boulevard marchal leclerc', 'Boulevard Maréchal Leclerc'),
	naIgnRessemble('bd leclerc', 'Boulevard Leclerc'), naIgnRessemble('rue jean', 'Rue Jeanne'),
	naIgnRessemble('rue des ca', 'Rue des Carmes'), naIgnRessemble('eglise saint', 'Église Saint-Étienne')],
	[true, false, true, true, true, true, true, true, true, true]);
egal('distance', [naIgnDistance('carmes', 'carnes'), naIgnDistance('carmes', 'camres'), naIgnDistance('carmes', 'carme'),
	naIgnDistance('carmes', 'cames'), naIgnDistance('abc', 'xyz'), naIgnDistance('carmes', 'carmes')], [1, 1, 1, 1, 2, 0]);

// Le remplacement : « 12, » toujours, minuscule après un numéro ou une virgule.
egal('« 12 rue des carm »', naIgnRemplacement(naIgnDecouper('12 rue des carm'), 'Rue des Carmes'), '12, rue des Carmes');
egal('« 12, rue des carm »', naIgnRemplacement(naIgnDecouper('12, rue des carm'), 'Rue des Carmes'), '12, rue des Carmes');
egal('« rue des carm »', naIgnRemplacement(naIgnDecouper('rue des carm'), 'Rue des Carmes'), 'Rue des Carmes');
egal('« Château, rue des carm »', naIgnRemplacement(naIgnDecouper('Château, rue des carm'), 'Rue des Carmes'), ' rue des Carmes');
egal('« Château, abbaye aux d »', naIgnRemplacement(naIgnDecouper('Château, abbaye aux d'), 'Abbaye aux Dames'), ' Abbaye aux Dames');
egal('« 3 bis place s »', naIgnRemplacement(naIgnDecouper('3 bis place s'), 'Place Saint-Pierre'), '3 bis, place Saint-Pierre');
egal('« le calvaire »', naIgnRemplacement(naIgnDecouper('le calvaire'), 'le Calvaire Saint-Pierre'), 'Le Calvaire Saint-Pierre');
egal('« 2 allee »', naIgnRemplacement(naIgnDecouper('2 allee'), 'Allée des Tilleuls'), '2, allée des Tilleuls');
egal('arrondissements', [naIgnDeLaCommune('75056', '75105'), naIgnDeLaCommune('75056', '75121'), naIgnDeLaCommune('69123', '69389'),
	naIgnDeLaCommune('13055', '13216'), naIgnDeLaCommune('14118', '14118'), naIgnDeLaCommune('14118', '75105')],
	[true, false, true, true, true, false]);
egal('pliage', naIgnPlier('Œuvre de l’Église — Saint-Jean'), 'oeuvre de l eglise saint jean');
